<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartBot IoT Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include_once 'common_ui.php';
    renderThemeHead(); ?>
</head>

<body>

    <div class="container mt-5">
        <h1 class="text-center mb-4">IoT Control Center</h1>

        <div class="row" id="deviceList">
            <?php
            include 'config.php';
            $result = $conn->query("SELECT * FROM devices ORDER BY last_seen DESC");

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $last_seen = strtotime($row['last_seen']);
                    $is_online = (time() - $last_seen) < 20;
                    $status_class = $is_online ? "online" : "offline";
                    $status_text = $is_online ? "Online" : "Offline (" . $row['last_seen'] . ")";
                    $type_badge = ($row['device_type'] == 'FULL')
                        ? '<span class="badge bg-success ms-2">FULL</span>'
                        : '<span class="badge bg-secondary ms-2">BASIC</span>';

                    // ดึงข้อมูลเซนเซอร์ล่าสุดสำหรับอุปกรณ์แบบ FULL
                    $sensorInfo = '';
                    if ($row['device_type'] == 'FULL') {
                        $sensorRes = $conn->query("SELECT light_level, voltage FROM sensor_data WHERE device_id=" . $row['id'] . " ORDER BY timestamp DESC LIMIT 1");
                        if ($sensorRes && $sensorRes->num_rows > 0) {
                            $sensor = $sensorRes->fetch_assoc();
                            $sensorInfo = '<div class="mt-2 small">
                                <span class="badge bg-info text-dark">💡 ' . $sensor['light_level'] . '</span>
                                <span class="badge bg-warning text-dark">⚡ ' . round($sensor['voltage'], 2) . 'V</span>
                            </div>';
                        }
                    }

                    echo '
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">' . htmlspecialchars($row['name']) . $type_badge . '</h5>
                                <p class="card-text">Status: <span class="' . $status_class . '">' . $status_text . '</span></p>
                                <p class="text-muted small">MAC: ' . $row['mac_address'] . '</p>
                                ' . $sensorInfo . '
                                <a href="board_view.php?id=' . $row['id'] . '" class="btn btn-primary w-100 mt-2">Manage Board</a>
                            </div>
                        </div>
                    </div>';
                }
            } else {
                echo '<div class="alert alert-warning">No devices connected yet. Turn on your ESP32!</div>';
            }
            ?>
        </div>
    </div>

    <script>
        // รีเฟรชหน้าอัตโนมัติทุก 10 วินาที
        setTimeout(function () { location.reload(); }, 10000);
    </script>

    <?php renderThemeBody(); ?>

</body>

</html>