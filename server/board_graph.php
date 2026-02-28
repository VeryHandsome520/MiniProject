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
    <title>Graph: <?php echo $device['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include_once 'common_ui.php';
    renderThemeHead(); ?>
</head>

<body>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="board_view.php?id=<?php echo $device_id; ?>" class="btn btn-secondary">&larr; Back to Controls</a>
            <h2 class="m-0">Graphs: <?php echo $device['name']; ?></h2>
            <div>
                <select id="rangeSelect" class="form-select form-select-sm" onchange="loadCharts()">
                    <option value="1h">1 ชั่วโมง</option>
                    <option value="6h">6 ชั่วโมง</option>
                    <option value="24h" selected>24 ชั่วโมง</option>
                    <option value="7d">7 วัน</option>
                </select>
            </div>
        </div>

        <?php if ($isFull): ?>
            <!-- กราฟแรงดันไฟฟ้า -->
            <div class="card p-4 mb-4">
                <h5>⚡ Voltage Usage (แรงดันไฟฟ้า)</h5>
                <canvas id="voltageChart"></canvas>
            </div>

            <!-- กราฟค่าแสง -->
            <div class="card p-4 mb-4">
                <h5>💡 Light Level (ค่าแสง)</h5>
                <canvas id="lightChart"></canvas>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                บอร์ดนี้เป็นแบบ <strong>BASIC</strong> — ไม่มีเซนเซอร์ กราฟแสดงเฉพาะ Activity Log
            </div>
        <?php endif; ?>

        <!-- บันทึกกิจกรรม -->
        <div class="card p-4">
            <h5>📋 Activity Log</h5>
            <canvas id="logChart"></canvas>
        </div>

        <div class="mt-4">
            <h4>Recent Activity</h4>
            <ul class="list-group">
                <?php
                $sql = "SELECT * FROM logs WHERE device_id='$device_id' ORDER BY timestamp DESC LIMIT 50";
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<li class='list-group-item'><b>{$row['timestamp']}</b>: {$row['action']}</li>";
                    }
                } else {
                    echo "<li class='list-group-item text-muted'>No activity yet.</li>";
                }
                ?>
            </ul>
        </div>
    </div>

    <script>
        let voltageChartInstance = null;
        let lightChartInstance = null;

        function loadCharts() {
            const range = document.getElementById('rangeSelect').value;
            const isFull = <?php echo $isFull ? 'true' : 'false'; ?>;

            if (!isFull) return;

            $.getJSON('api_sensor.php?device_id=<?php echo $device_id; ?>&range=' + range, function (data) {
                // --- กราฟแรงดันไฟฟ้า ---
                const voltageCtx = document.getElementById('voltageChart').getContext('2d');
                if (voltageChartInstance) voltageChartInstance.destroy();

                voltageChartInstance = new Chart(voltageCtx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Voltage (V)',
                            data: data.voltage,
                            borderColor: 'rgb(255, 159, 64)',
                            backgroundColor: 'rgba(255, 159, 64, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: true }
                        },
                        scales: {
                            y: {
                                beginAtZero: false,
                                title: { display: true, text: 'Volts (V)' }
                            },
                            x: {
                                title: { display: true, text: 'เวลา' }
                            }
                        }
                    }
                });

                // --- กราฟค่าแสง ---
                const lightCtx = document.getElementById('lightChart').getContext('2d');
                if (lightChartInstance) lightChartInstance.destroy();

                lightChartInstance = new Chart(lightCtx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Light Level',
                            data: data.light,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.15)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: true }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 4095,
                                title: { display: true, text: 'ADC Value (0-4095)' }
                            },
                            x: {
                                title: { display: true, text: 'เวลา' }
                            }
                        }
                    }
                });
            });
        }

        // --- กราฟบันทึกกิจกรรม ---
        const logCtx = document.getElementById('logChart').getContext('2d');
        new Chart(logCtx, {
            type: 'bar',
            data: {
                labels: ['Actions'],
                datasets: [{
                    label: 'Activity Count',
                    data: [<?php
                    $countRes = $conn->query("SELECT COUNT(*) as cnt FROM logs WHERE device_id='$device_id' AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $cnt = $countRes ? $countRes->fetch_assoc()['cnt'] : 0;
                    echo $cnt;
                    ?>],
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // โหลดกราฟตอนเปิดหน้า
        loadCharts();
        // รีเฟรชอัตโนมัติทุก 30 วินาที
        setInterval(loadCharts, 30000);
    </script>

    <?php renderThemeBody(); ?>

</body>

</html>