<?php
include 'config.php';
include 'telegram.php';

// disable error reporting in output for clear JSON
error_reporting(0);
header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

$mac = $conn->real_escape_string($data['mac']);
$ip = $conn->real_escape_string($data['ip']);

// 1. Register or Update Device
$sql = "INSERT INTO devices (mac_address, ip_address, last_seen) 
        VALUES ('$mac', '$ip', NOW()) 
        ON DUPLICATE KEY UPDATE ip_address='$ip', last_seen=NOW()";
$conn->query($sql);

// Get Device ID
$result = $conn->query("SELECT id, name FROM devices WHERE mac_address='$mac'");
$device = $result->fetch_assoc();
$device_id = $device['id'];

// 2. Process Logic for Pins
$responsePins = [];

// Fetch all pins for this device
$pinResult = $conn->query("SELECT * FROM device_pins WHERE device_id='$device_id'");

while ($row = $pinResult->fetch_assoc()) {
    $pin = $row['pin_number'];
    $mode = $row['mode'];
    $currentState = $row['state'];
    $targetState = $currentState;

    // --- TIMER MODE ---
    if ($mode == 'TIMER') {
        $now = date('H:i:s');
        $on = $row['timer_on'];
        $off = $row['timer_off'];

        // Simple logic: If ON < OFF (e.g., 08:00 to 18:00)
        if ($on < $off) {
            if ($now >= $on && $now < $off)
                $targetState = 'ON';
            else
                $targetState = 'OFF';
        } else {
            // Crosspoint midnight (e.g., 22:00 to 06:00)
            if ($now >= $on || $now < $off)
                $targetState = 'ON';
            else
                $targetState = 'OFF';
        }

        // Update DB if changed by Timer
        if ($targetState != $currentState) {
            $conn->query("UPDATE device_pins SET state='$targetState' WHERE id=" . $row['id']);
            sendTelegram("⏰ <b>Timer Action</b>\nBoard: {$device['name']}\nPin: $pin\nState: $targetState");
        }
    }

    // --- DURATION MODE ---
    else if ($mode == 'DURATION') {
        if ($row['duration_end']) {
            if (new DateTime() > new DateTime($row['duration_end'])) {
                $targetState = 'OFF';
                // Switch back to MANUAL OFF
                $conn->query("UPDATE device_pins SET mode='MANUAL', state='OFF', duration_end=NULL WHERE id=" . $row['id']);
                sendTelegram("⏳ <b>Duration Ended</b>\nBoard: {$device['name']}\nPin: $pin\n Turned OFF");
            } else {
                $targetState = 'ON';
            }
        }
    }

    $responsePins[$pin] = $targetState;
}

// 3. Auto-initialize pins if not exist (Helper for new boards)
// If the board sends "pins": [2, 4] in its first handshake, we could create them.
// For now, we assume pins are created via UI or manually inserted.
// 3. Auto-initialize pins if not exist (Helper for new boards)
// Define all output pins
$allPins = [2, 4, 5, 12, 13, 14, 15, 16, 17, 18, 19, 21, 22, 23, 25, 26, 27, 32, 33];
$values = [];
foreach ($allPins as $p) {
    $values[] = "($device_id, $p)";
}
$valuesStr = implode(", ", $values);
$conn->query("INSERT IGNORE INTO device_pins (device_id, pin_number) VALUES $valuesStr");

echo json_encode([
    "status" => "ok",
    "commands" => $responsePins
]);

$conn->close();
?>