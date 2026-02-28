<?php
include 'config.php';

header('Content-Type: application/json');

$device_id = isset($_GET['device_id']) ? (int) $_GET['device_id'] : 0;
$range = isset($_GET['range']) ? $_GET['range'] : '24h';

if (!$device_id) {
    echo json_encode(["status" => "error", "message" => "Missing device_id"]);
    exit;
}

// กำหนดช่วงเวลาที่ต้องการดึงข้อมูล
switch ($range) {
    case '1h':
        $interval = '1 HOUR';
        break;
    case '6h':
        $interval = '6 HOUR';
        break;
    case '7d':
        $interval = '7 DAY';
        break;
    case '24h':
    default:
        $interval = '24 HOUR';
        break;
}

// ดึงข้อมูลเซนเซอร์จากฐานข้อมูล
$sql = "SELECT light_level, voltage, timestamp 
        FROM sensor_data 
        WHERE device_id=$device_id 
        AND timestamp >= DATE_SUB(NOW(), INTERVAL $interval)
        ORDER BY timestamp ASC";

$result = $conn->query($sql);

$labels = [];
$lightData = [];
$voltageData = [];

if ($result && $result->num_rows > 0) {
    // ลดจำนวนจุดข้อมูลถ้ามากเกินไป (สูงสุด 200 จุด เพื่อประสิทธิภาพ)
    $totalRows = $result->num_rows;
    $skipFactor = max(1, floor($totalRows / 200));
    $index = 0;

    while ($row = $result->fetch_assoc()) {
        if ($index % $skipFactor == 0) {
            $labels[] = date('H:i', strtotime($row['timestamp']));
            $lightData[] = $row['light_level'] !== null ? (int) $row['light_level'] : null;
            $voltageData[] = $row['voltage'] !== null ? round((float) $row['voltage'], 2) : null;
        }
        $index++;
    }
}

// ดึงค่าล่าสุด
$latestSql = "SELECT light_level, voltage, timestamp FROM sensor_data WHERE device_id=$device_id ORDER BY timestamp DESC LIMIT 1";
$latestResult = $conn->query($latestSql);
$latest = null;
if ($latestResult && $latestResult->num_rows > 0) {
    $latest = $latestResult->fetch_assoc();
    $latest['light_level'] = (int) $latest['light_level'];
    $latest['voltage'] = round((float) $latest['voltage'], 2);
}

echo json_encode([
    "status" => "ok",
    "labels" => $labels,
    "light" => $lightData,
    "voltage" => $voltageData,
    "latest" => $latest
]);

$conn->close();
?>