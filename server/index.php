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
            <!-- Devices will be loaded here -->
            <?php
            include 'config.php';
            $result = $conn->query("SELECT * FROM devices ORDER BY last_seen DESC");

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $last_seen = strtotime($row['last_seen']);
                    $is_online = (time() - $last_seen) < 10; // Online if seen in last 10 seconds
                    $status_class = $is_online ? "online" : "offline";
                    $status_text = $is_online ? "Online" : "Offline (" . $row['last_seen'] . ")";

                    echo '
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">' . htmlspecialchars($row['name']) . '</h5>
                                <p class="card-text">Status: <span class="' . $status_class . '">' . $status_text . '</span></p>
                                <p class="text-muted small">MAC: ' . $row['mac_address'] . '</p>
                                <a href="board_view.php?id=' . $row['id'] . '" class="btn btn-primary w-100">Manage Board</a>
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
        // Simple auto-refresh to see status updates
        setTimeout(function () { location.reload(); }, 10000);
    </script>

    <?php renderThemeBody(); ?>

</body>

</html>