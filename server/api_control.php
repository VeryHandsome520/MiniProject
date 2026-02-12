<?php
include 'config.php';
include 'telegram.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$device_id = $_POST['device_id'] ?? 0;
$pin = $_POST['pin'] ?? 0;

// Get Device Name for Notifications
$devRes = $conn->query("SELECT name, mac_address FROM devices WHERE id='$device_id'");
$devRow = $devRes->fetch_assoc();
$devName = $devRow ? $devRow['name'] : "Unknown ($device_id)";

if ($action == 'toggle') {
    $state = $_POST['state']; // ON or OFF
    $sql = "UPDATE device_pins SET mode='MANUAL', state='$state', duration_end=NULL WHERE device_id='$device_id' AND pin_number='$pin'";
    if ($conn->query($sql)) {
        sendTelegram("🔧 <b>Manual Control</b>\nBoard: $devName\nPin: $pin\nSet to: <b>$state</b>");
        echo json_encode(["status" => "ok"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} else if ($action == 'set_timer') {
    $on = $_POST['on_time'];
    $off = $_POST['off_time'];
    $sql = "UPDATE device_pins SET mode='TIMER', timer_on='$on', timer_off='$off' WHERE device_id='$device_id' AND pin_number='$pin'";
    if ($conn->query($sql)) {
        sendTelegram("⏰ <b>Timer Set</b>\nBoard: $devName\nPin: $pin\nON: $on\nOFF: $off");
        echo json_encode(["status" => "ok"]);
    }
} else if ($action == 'set_duration') {
    $minutes = (int) $_POST['minutes'];
    $sql = "UPDATE device_pins SET mode='DURATION', state='ON', duration_end=DATE_ADD(NOW(), INTERVAL $minutes MINUTE) WHERE device_id='$device_id' AND pin_number='$pin'";
    if ($conn->query($sql)) {
        sendTelegram("⏳ <b>Duration Start</b>\nBoard: $devName\nPin: $pin\nTime: $minutes mins");
        echo json_encode(["status" => "ok"]);
    }
}

// --- New Pin Management Actions ---
else if ($action == 'add_pin') {
    $pin = (int) $_POST['pin'];
    // Check if exists
    $check = $conn->query("SELECT id FROM device_pins WHERE device_id='$device_id' AND pin_number='$pin'");
    if ($check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Pin already exists!"]);
    } else {
        $sql = "INSERT INTO device_pins (device_id, pin_number, mode, state) VALUES ('$device_id', '$pin', 'MANUAL', 'OFF')";
        if ($conn->query($sql))
            echo json_encode(["status" => "ok"]);
        else
            echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} else if ($action == 'delete_pin') {
    $pin = (int) $_POST['pin'];
    $sql = "DELETE FROM device_pins WHERE device_id='$device_id' AND pin_number='$pin'";
    if ($conn->query($sql))
        echo json_encode(["status" => "ok"]);
    else
        echo json_encode(["status" => "error", "message" => $conn->error]);
} else if ($action == 'rename_device') {
    $name = $conn->real_escape_string($_POST['name']);
    $sql = "UPDATE devices SET name='$name' WHERE id='$device_id'";
    if ($conn->query($sql))
        echo json_encode(["status" => "ok"]);
    else
        echo json_encode(["status" => "error", "message" => $conn->error]);
}

$conn->close();
?>