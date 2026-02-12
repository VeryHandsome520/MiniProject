<?php
include 'config.php';
$device_id = $_GET['id'];
$devRes = $conn->query("SELECT * FROM devices WHERE id='$device_id'");
$device = $devRes->fetch_assoc();

if (!$device)
    die("Device not found.");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Graph: <?php echo $device['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include_once 'common_ui.php';
    renderThemeHead(); ?>
</head>

<body>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="board_view.php?id=<?php echo $device_id; ?>" class="btn btn-secondary">&larr; Back to Controls</a>
            <h2 class="m-0">Activity Log: <?php echo $device['name']; ?></h2>
        </div>

        <div class="card p-4">
            <canvas id="logChart"></canvas>
        </div>

        <div class="mt-4">
            <h4>Recent Activity</h4>
            <ul class="list-group">
                <?php
                // Fetch logs
                // Parsing simple logic: Just counting actions per hour or showing list
                // For a real graph we need structured data, but here we plot "Events over time"
                $sql = "SELECT * FROM logs WHERE device_id='$device_id' ORDER BY timestamp DESC LIMIT 50";
                $result = $conn->query($sql);

                $labels = [];
                $dataCount = [];

                while ($row = $result->fetch_assoc()) {
                    echo "<li class='list-group-item'><b>{$row['timestamp']}</b>: {$row['action']}</li>";
                }
                ?>
            </ul>
        </div>
    </div>

    <script>
        // Placeholder for a real activity chart
        // In a real production app, we would aggregate 'logs' by hour/day
        const ctx = document.getElementById('logChart').getContext('2d');
        const myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['10:00', '10:05', '10:10', '10:15', '10:20'], // Mock data for demo
                datasets: [{
                    label: 'Actions Triggered',
                    data: [1, 3, 0, 2, 1],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            }
        });
    </script>

    <?php renderThemeBody(); ?>

</body>

</html>