<?php
include 'config.php';
$device_id = $_GET['id'];
$devRes = $conn->query("SELECT * FROM devices WHERE id='$device_id'");
$device = $devRes->fetch_assoc();

if (!$device)
    die("Device not found.");

$isFull = ($device['device_type'] == 'FULL');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage <?php echo $device['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include_once 'common_ui.php';
    renderThemeHead(); ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="index.php" class="btn btn-secondary btn-custom me-2">&larr; Back</a>
                <h2 class="d-inline-block align-middle">
                    <?php echo htmlspecialchars($device['name']); ?>
                    <?php if ($isFull): ?>
                        <span class="badge bg-success">FULL</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">BASIC</span>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary ms-2"
                        onclick="renameDevice(<?php echo $device_id; ?>, '<?php echo addslashes($device['name']); ?>')">✏️</button>
                </h2>
            </div>
            <div>
                <button class="btn btn-success btn-custom me-2" data-bs-toggle="modal" data-bs-target="#addPinModal">+
                    Add Pin</button>
                <a href="board_graph.php?id=<?php echo $device_id; ?>" class="btn btn-info btn-custom text-white">View
                    Graph 📊</a>
            </div>
        </div>

        <?php if ($isFull): ?>
        <!-- การ์ดแสดงข้อมูลเซนเซอร์ -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="text-muted small text-uppercase fw-bold">💡 Light Level</h6>
                        <h2 id="sensorLight" class="mb-0">--</h2>
                        <small class="text-muted">/ 4095</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="text-muted small text-uppercase fw-bold">⚡ Voltage</h6>
                        <h2 id="sensorVoltage" class="mb-0">--</h2>
                        <small class="text-muted">Volts</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="text-muted small text-uppercase fw-bold">🌙 Auto Light Mode</h6>
                        <div class="form-check form-switch d-flex justify-content-center mt-2">
                            <input class="form-check-input" type="checkbox" id="lightAutoToggle"
                                <?php echo ($device['light_auto_mode'] == 'ON') ? 'checked' : ''; ?>
                                onchange="setLightAuto(<?php echo $device_id; ?>, this.checked)">
                        </div>
                        <div class="input-group input-group-sm mt-2">
                            <span class="input-group-text">Threshold</span>
                            <input type="number" class="form-control" id="lightThreshold"
                                value="<?php echo $device['light_threshold']; ?>" min="0" max="4095">
                            <button class="btn btn-outline-primary btn-sm"
                                onclick="setLightAuto(<?php echo $device_id; ?>, document.getElementById('lightAutoToggle').checked)">Set</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row" id="pinContainer">
            <?php
            $pins = $conn->query("SELECT * FROM device_pins WHERE device_id='$device_id' ORDER BY pin_number");
            if ($pins->num_rows > 0) {
                while ($pin = $pins->fetch_assoc()) {
                    $p = $pin['pin_number'];
                    $isOn = ($pin['state'] == 'ON');
                    echo '
                <div class="col-md-6 col-xl-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>GPIO <span class="badge bg-dark">' . $p . '</span></span>
                            <button class="btn btn-outline-danger btn-sm" onclick="deletePin(' . $device_id . ', ' . $p . ')" title="Delete Pin">&times;</button>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-' . ($pin['mode'] == 'MANUAL' ? 'primary' : 'warning text-dark') . '">' . $pin['mode'] . '</span>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="switch_' . $p . '" ' . ($isOn ? 'checked' : '') . ' onchange="togglePin(' . $device_id . ', ' . $p . ', this.checked)">
                                    <label class="form-check-label" for="switch_' . $p . '">' . ($isOn ? 'ON' : 'OFF') . '</label>
                                </div>
                            </div>

                            <hr class="opacity-25">
                            
                            <!-- ตั้งเวลา -->
                            <h6 class="text-uppercase text-muted small fw-bold">Schedule</h6>
                            <div class="input-group mb-2">
                                <input type="time" class="form-control form-control-sm" id="on_' . $p . '" value="' . $pin['timer_on'] . '">
                                <span class="input-group-text small">-</span>
                                <input type="time" class="form-control form-control-sm" id="off_' . $p . '" value="' . $pin['timer_off'] . '">
                                <button class="btn btn-outline-primary btn-sm" onclick="setTimer(' . $device_id . ', ' . $p . ')">Set</button>
                            </div>
                            
                            <!-- นับเวลาถอยหลัง -->
                            <h6 class="text-uppercase text-muted small fw-bold mt-3">Timer (Minutes)</h6>
                            <div class="input-group">
                                <input type="number" class="form-control form-control-sm" id="dur_' . $p . '" placeholder="Min">
                                <button class="btn btn-outline-warning btn-sm text-dark" onclick="setDuration(' . $device_id . ', ' . $p . ')">Start</button>
                            </div>
                        </div>
                    </div>
                </div>';
                }
            } else {
                echo '<div class="col-12 text-center py-5 text-muted">No Pins Configured. Click "+ Add Pin" to start.</div>';
            }
            ?>
        </div>
    </div>

    <!-- หน้าต่างเพิ่ม Pin -->
    <div class="modal fade" id="addPinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add GPIO Pin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>GPIO Number (e.g., 4, 5, 13)</label>
                        <input type="number" id="newPinNum" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="addPin(<?php echo $device_id; ?>)">Add
                        Pin</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- จัดการ Pin ---
        function togglePin(did, pin, checked) {
            let state = checked ? 'ON' : 'OFF';
            $.post('api_control.php', { action: 'toggle', device_id: did, pin: pin, state: state }, function (res) {
                if (res.status !== 'ok') { alert(res.message); location.reload(); }
            });
        }

        function addPin(did) {
            let p = $('#newPinNum').val();
            if (!p) return;
            $.post('api_control.php', { action: 'add_pin', device_id: did, pin: p }, function (res) {
                if (res.status == 'ok') location.reload();
                else alert(res.message);
            });
        }

        function deletePin(did, pin) {
            if (!confirm('Are you sure you want to delete GPIO ' + pin + '?')) return;
            $.post('api_control.php', { action: 'delete_pin', device_id: did, pin: pin }, function (res) {
                if (res.status == 'ok') location.reload();
                else alert(res.message);
            });
        }

        function renameDevice(did, oldName) {
            let newName = prompt("Enter new device name:", oldName);
            if (newName && newName !== oldName) {
                $.post('api_control.php', { action: 'rename_device', device_id: did, name: newName }, function (res) {
                    if (res.status == 'ok') location.reload();
                    else alert(res.message);
                });
            }
        }

        function setDuration(did, pin) {
            let min = $('#dur_' + pin).val();
            if (!min) return;
            $.post('api_control.php', { action: 'set_duration', device_id: did, pin: pin, minutes: min }, function (res) {
                if (res.status == 'ok') location.reload();
            });
        }

        function setTimer(did, pin) {
            let on = $('#on_' + pin).val();
            let off = $('#off_' + pin).val();
            $.post('api_control.php', { action: 'set_timer', device_id: did, pin: pin, on_time: on, off_time: off }, function (res) {
                if (res.status == 'ok') location.reload();
            });
        }

        // --- โหมดไฟอัตโนมัติ ---
        function setLightAuto(did, enabled) {
            let mode = enabled ? 'ON' : 'OFF';
            let threshold = $('#lightThreshold').val() || 500;
            $.post('api_control.php', { action: 'set_light_auto', device_id: did, mode: mode, threshold: threshold }, function (res) {
                if (res.status !== 'ok') alert(res.message);
            });
        }

        <?php if ($isFull): ?>
        // --- รีเฟรชข้อมูลเซนเซอร์อัตโนมัติ ---
        function loadSensorData() {
            $.getJSON('api_sensor.php?device_id=<?php echo $device_id; ?>&range=1h', function (data) {
                if (data.latest) {
                    $('#sensorLight').text(data.latest.light_level);
                    $('#sensorVoltage').text(data.latest.voltage + 'V');
                }
            });
        }
        loadSensorData();
        setInterval(loadSensorData, 15000); // รีเฟรชทุก 15 วินาที
        <?php endif; ?>
    </script>

    <?php renderThemeBody(); ?>

</body>

</html>