<?php
include 'config.php';
include 'telegram.php';

// ปิดการแสดง error เพื่อให้ output เป็น JSON ที่สะอาด
error_reporting(0);
header('Content-Type: application/json');

// --- จำกัดจำนวนคำขอ (Rate Limiting ตาม IP) ---
session_start();
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'rate_' . md5($clientIP);
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'reset' => time() + 60];
}
if (time() > $_SESSION[$rateLimitKey]['reset']) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'reset' => time() + 60];
}
$_SESSION[$rateLimitKey]['count']++;
if ($_SESSION[$rateLimitKey]['count'] > RATE_LIMIT_PER_MINUTE) {
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Rate limit exceeded"]);
    exit;
}

// --- ตรวจสอบ API Key ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== DEFAULT_API_KEY) {
    // ตรวจสอบว่าเป็น key เฉพาะอุปกรณ์หรือไม่
    $keyCheck = $conn->query("SELECT id FROM devices WHERE api_key='" . $conn->real_escape_string($apiKey) . "'");
    if (!$keyCheck || $keyCheck->num_rows == 0) {
        if ($apiKey !== '') {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid API key"]);
            exit;
        }
        // อนุญาต key ว่างเพื่อรองรับอุปกรณ์เดิม (จะถูกกำหนดค่า default)
    }
}

$json = file_get_contents('php://input');
file_put_contents('debug_input.txt', $json);

$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

$mac = $conn->real_escape_string($data['mac']);
$ip = $conn->real_escape_string($data['ip']);
$deviceType = isset($data['device_type']) ? $conn->real_escape_string($data['device_type']) : 'BASIC';

// 1. ลงทะเบียนหรืออัปเดตอุปกรณ์
$sql = "INSERT INTO devices (mac_address, ip_address, last_seen, device_type, api_key) 
        VALUES ('$mac', '$ip', NOW(), '$deviceType', '" . $conn->real_escape_string(DEFAULT_API_KEY) . "') 
        ON DUPLICATE KEY UPDATE ip_address='$ip', last_seen=NOW(), device_type='$deviceType'";
$conn->query($sql);

// ดึงข้อมูลอุปกรณ์
$result = $conn->query("SELECT id, name, light_auto_mode, light_threshold FROM devices WHERE mac_address='$mac'");
$device = $result->fetch_assoc();
$device_id = $device['id'];

// 2. บันทึกข้อมูลเซนเซอร์ (เฉพาะอุปกรณ์แบบ FULL)
if ($deviceType == 'FULL') {
    $light = isset($data['light']) ? (int) $data['light'] : null;
    $voltageVal = isset($data['voltage']) ? (float) $data['voltage'] : null;

    if ($light !== null || $voltageVal !== null) {
        $lightSQL = ($light !== null) ? $light : "NULL";
        $voltageSQL = ($voltageVal !== null) ? $voltageVal : "NULL";
        $conn->query("INSERT INTO sensor_data (device_id, light_level, voltage) VALUES ($device_id, $lightSQL, $voltageSQL)");

        // แจ้งเตือน Telegram เมื่อแรงดันไฟฟ้าผิดปกติ
        if ($voltageVal !== null && ($voltageVal < 3.0 || $voltageVal > 5.5)) {
            sendTelegram("⚠️ <b>Voltage Alert</b>\nBoard: {$device['name']}\nVoltage: {$voltageVal}V\nStatus: " . ($voltageVal < 3.0 ? "LOW" : "HIGH"));
        }
    }

    // ลบข้อมูลเซนเซอร์เก่า (เก็บแค่ 7 วันล่าสุด)
    $conn->query("DELETE FROM sensor_data WHERE device_id=$device_id AND timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)");
}

// 3. ประมวลผลลอจิกสำหรับ Pin
$responsePins = [];

$pinResult = $conn->query("SELECT * FROM device_pins WHERE device_id='$device_id'");

while ($row = $pinResult->fetch_assoc()) {
    $pin = $row['pin_number'];
    $mode = $row['mode'];
    $currentState = $row['state'];
    $targetState = $currentState;

    // --- โหมดตั้งเวลา (TIMER) ---
    if ($mode == 'TIMER') {
        $now = date('H:i:s');
        $on = $row['timer_on'];
        $off = $row['timer_off'];

        if ($on < $off) {
            if ($now >= $on && $now < $off)
                $targetState = 'ON';
            else
                $targetState = 'OFF';
        } else {
            // ข้ามเที่ยงคืน (เช่น 22:00 ถึง 06:00)
            if ($now >= $on || $now < $off)
                $targetState = 'ON';
            else
                $targetState = 'OFF';
        }

        if ($targetState != $currentState) {
            $conn->query("UPDATE device_pins SET state='$targetState' WHERE id=" . $row['id']);
            sendTelegram("⏰ <b>Timer Action</b>\nBoard: {$device['name']}\nPin: $pin\nState: $targetState");
        }
    }

    // --- โหมดนับเวลาถอยหลัง (DURATION) ---
    else if ($mode == 'DURATION') {
        if ($row['duration_end']) {
            if (new DateTime() > new DateTime($row['duration_end'])) {
                $targetState = 'OFF';
                $conn->query("UPDATE device_pins SET mode='MANUAL', state='OFF', duration_end=NULL WHERE id=" . $row['id']);
                sendTelegram("⏳ <b>Duration Ended</b>\nBoard: {$device['name']}\nPin: $pin\n Turned OFF");
            } else {
                $targetState = 'ON';
            }
        }
    }

    $responsePins[$pin] = $targetState;
}

// 4. สร้าง Pin อัตโนมัติถ้ายังไม่มี
$allPins = [4, 5, 12, 13, 14, 15, 16, 17, 18, 19, 21, 22, 23, 25, 26, 27, 32, 33];
$values = [];
foreach ($allPins as $p) {
    $values[] = "($device_id, $p)";
}
$valuesStr = implode(", ", $values);
$conn->query("INSERT IGNORE INTO device_pins (device_id, pin_number) VALUES $valuesStr");

// 5. สร้าง Response ส่งกลับ
    $response = [
        "status" => "ok",
        "commands" => $responsePins,
        "light_auto_mode" => $device['light_auto_mode'],
        "light_threshold" => (int) $device['light_threshold']
    ];

echo json_encode($response);

$conn->close();
?>