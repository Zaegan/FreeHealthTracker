<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'] ?? 'default';
$db = new PDO("sqlite:/var/www/html/data/{$username}_weight_log.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS weights (id INTEGER PRIMARY KEY AUTOINCREMENT, date TEXT, weight REAL)");
$db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        fgetcsv($handle); // Skip header
        while (($data = fgetcsv($handle)) !== false) {
            $date = date('Y-m-d', strtotime($data[0]));
            $weight = floatval($data[1]);
            $stmt = $db->prepare("INSERT INTO weights (date, weight) VALUES (?, ?)");
            $stmt->execute([$date, $weight]);
        }
        fclose($handle);
    }
    header("Location: weight_log.php");
    exit();
}

// Handle CSV export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"{$username}_weight_log.csv\"");
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Weight']);
    $rows = $db->query("SELECT date, weight FROM weights ORDER BY date ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        fputcsv($output, [$row['date'], $row['weight']]);
    }
    fclose($output);
    exit();
}

function getSetting($key) {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

function saveSetting($key, $value) {
    global $db;
    $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    $stmt->execute([$key, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['startDate'], $_POST['endDate'])) {
        saveSetting('startDate', $_POST['startDate']);
        saveSetting('endDate', $_POST['endDate']);
    }
    if (isset($_POST['goalStartDate'], $_POST['goalStartWeight'], $_POST['goalEndDate'], $_POST['goalEndWeight'])) {
        saveSetting('goalStartDate', $_POST['goalStartDate']);
        saveSetting('goalStartWeight', $_POST['goalStartWeight']);
        saveSetting('goalEndDate', $_POST['goalEndDate']);
        saveSetting('goalEndWeight', $_POST['goalEndWeight']);
    }
    header("Location: weight_log.php");
    exit();
}

$storedStartDate = getSetting('startDate');
$storedEndDate = getSetting('endDate');
$goalStartDate = getSetting('goalStartDate');
$goalStartWeight = getSetting('goalStartWeight');
$goalEndDate = getSetting('goalEndDate');
$goalEndWeight = getSetting('goalEndWeight');

$endDate = $storedEndDate; // for form display only
$startDate = $storedStartDate;

// Temporary fallback logic for display logic only
if (empty($storedEndDate)) {
    $lastWeightDate = $db->query("SELECT date FROM weights ORDER BY date DESC LIMIT 1")->fetchColumn();
    $latestGoalDate = max($goalEndDate, $goalStartDate);
    $computedEndDate = max($lastWeightDate, $latestGoalDate);
} else {
    $computedEndDate = $storedEndDate;
}
if (empty($storedStartDate)) {
    $firstWeightDate = $db->query("SELECT date FROM weights ORDER BY date ASC LIMIT 1")->fetchColumn();
    $firstGoalDate = min($goalEndDate, $goalStartDate);
    $computedStartDate = min($firstWeightDate, $firstGoalDate);
} else {
    $computedStartDate = $storedStartDate;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Weight Log</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #f0f0f0; }
        form { margin: 20px 0; }

        label, button, input[type="date"], input[type="number"], input[type="file"] {
            font-size: 1.1em;
            padding: 0.5em;
        }

        .touch label, .touch button, .touch input[type="date"], .touch input[type="number"], .touch input[type="file"] {
            font-size: 1.4em;
            padding: 0.8em;
            width: auto;
            display: inline-block;
        }

        .touch .form-pair {
            display: flex;
            gap: 1em;
            flex-wrap: wrap;
            margin-bottom: 1em;
        }

        #chart-container {
            position: relative;
            width: 100%;
            height: 750px;
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1em;
        }
    </style>
</head>
<body>
    <script>
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            document.body.classList.add('touch');
        }
    </script>
    <div class="header-bar">
        <a href="index.php">Back to Workout</a>
        <h1 style="font-size: 2.5em; margin: 0;">Weight Log</h1>
        <a href="logout.php">Logout</a>
    </div>

    <form method="post" enctype="multipart/form-data">
        <label>Import CSV: <input type="file" name="csv_file" accept=".csv" required onchange="this.form.submit()"></label>
    </form>

    <form method="get">
        <button type="submit" name="export" value="1">Export CSV</button>
    </form>

    <form method="post">
        <div class="form-pair">
            <label for="startDate">Start Date: </label>
            <input type="date" id="startDate" name="startDate" value="<?php echo htmlspecialchars($startDate); ?>">
            <label for="endDate">End Date: </label>
            <input type="date" id="endDate" name="endDate" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>
        <button type="submit">Update Graph</button>
    </form>

    <form method="post">
        <h3>Set Goal Trajectory</h3>
        <div class="form-pair">
            <label>Start Date: <input type="date" name="goalStartDate" value="<?php echo htmlspecialchars($goalStartDate); ?>"></label>
            <label>Start Weight: <input type="number" name="goalStartWeight" step="0.1" value="<?php echo htmlspecialchars($goalStartWeight); ?>"></label>
        </div>
        <div class="form-pair">
            <label>End Date: <input type="date" name="goalEndDate" value="<?php echo htmlspecialchars($goalEndDate); ?>"></label>
            <label>End Weight: <input type="number" name="goalEndWeight" step="0.1" value="<?php echo htmlspecialchars($goalEndWeight); ?>"></label>
        </div>
        <button type="submit">Save Goal</button>
    </form>

    <div id="chart-container">
        <canvas id="weightChart"></canvas>
    </div>

    <table>
        <tr><th>Date</th><th>Weight (lbs)</th></tr>
        <?php
        $entries = $db->query("SELECT date, weight FROM weights ORDER BY date ASC")->fetchAll();
        $dates = [];
        $weights = [];
        foreach ($entries as $row) {
            echo "<tr><td>{$row['date']}</td><td>{$row['weight']}</td></tr>";
            $dates[] = $row['date'];
            $weights[] = $row['weight'];
        }
        ?>
    </table>

    <script>
        const rawDates = <?php echo json_encode($dates); ?>.map(d => new Date(d));
        const rawWeights = <?php echo json_encode($weights); ?>;

        let fullDataPoints = rawDates.map((date, i) => ({ date, weight: rawWeights[i] }));
        fullDataPoints.sort((a, b) => a.date - b.date);

        function calculateMovingAverage(dataPoints, windowSizeDays) {
            const msPerDay = 86400000;
            return dataPoints.map((point) => {
                const window = dataPoints.filter(p => Math.abs((p.date - point.date) / msPerDay) <= windowSizeDays);
                const avg = window.reduce((sum, p) => sum + p.weight, 0) / window.length;
                return { x: point.date, y: avg };
            });
        }


        function addGoalTrajectoryDataset(datasets) {
            const goalStartDate = <?php echo $goalStartDate ? 'new Date("' . $goalStartDate . '")' : 'null'; ?>;
            const goalStartWeight = <?php echo is_numeric($goalStartWeight) ? floatval($goalStartWeight) : 'null'; ?>;
            const goalEndDate = <?php echo $goalEndDate ? 'new Date("' . $goalEndDate . '")' : 'null'; ?>;
            const goalEndWeight = <?php echo is_numeric($goalEndWeight) ? floatval($goalEndWeight) : 'null'; ?>;

            if (!(goalStartDate && goalEndDate && goalStartWeight != null && goalEndWeight != null)) return;

            const msPerDay = 86400000;
            const inRange = (d) => (!computedStartDate || d >= computedStartDate) && (!computedEndDate || d <= computedEndDate);
            let points = [];

            if (inRange(goalStartDate) && inRange(goalEndDate)) {
                points = [
                    { x: goalStartDate, y: goalStartWeight },
                    { x: goalEndDate, y: goalEndWeight }
                ];
            } else {
                const totalDays = (goalEndDate - goalStartDate) / msPerDay;
                const weightDiff = goalEndWeight - goalStartWeight;
                const slope = weightDiff / totalDays;

                const startIn = computedStartDate && goalStartDate <= computedStartDate && goalEndDate >= computedStartDate;
                const endIn = computedEndDate && goalStartDate <= computedEndDate && goalEndDate >= computedEndDate;

                if (startIn && endIn) {
                    const interpStartY = goalStartWeight + slope * ((computedStartDate - goalStartDate) / msPerDay);
                    const interpEndY = goalStartWeight + slope * ((computedEndDate - goalStartDate) / msPerDay);
                    points = [
                        { x: computedStartDate, y: interpStartY },
                        { x: computedEndDate, y: interpEndY }
                    ];
                } else if (inRange(goalStartDate) && goalEndDate > computedEndDate) {
                    const newY = goalStartWeight + slope * ((computedEndDate - goalStartDate) / msPerDay);
                    points = [
                        { x: goalStartDate, y: goalStartWeight },
                        { x: computedEndDate, y: newY }
                    ];
                } else if (computedStartDate && goalStartDate < computedStartDate && inRange(goalEndDate)) {
                    const newY = goalEndWeight - slope * ((goalEndDate - computedStartDate) / msPerDay);
                    points = [
                        { x: computedStartDate, y: newY },
                        { x: goalEndDate, y: goalEndWeight }
                    ];
                }
            }

            if (points.length === 2) {
                datasets.push({
                    label: 'Goal Trajectory',
                    data: points,
                    borderColor: 'gold',
                    backgroundColor: 'gold',
                    fill: false,
                    tension: 0,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                });
            }
        }

        function renderChart(dataPoints) {
            const smoothed = calculateMovingAverage(dataPoints, 14);

            const datasets = [
                {
                    label: 'Biweekly Moving Avg Weight',
                    data: smoothed,
                    borderColor: 'blue',
                    backgroundColor: 'lightblue',
                    fill: false,
                    tension: 0.1,
                    showLine: true,
                    pointRadius: 0
                },
                {
                    label: 'Recorded Weights',
                    data: dataPoints.map(p => ({ x: p.date, y: p.weight })),
                    borderColor: 'red',
                    backgroundColor: 'red',
                    fill: false,
                    showLine: false,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    type: 'scatter'
                }
            ];

            addGoalTrajectoryDataset(datasets);

            const ctx = document.getElementById('weightChart').getContext('2d');
            if (window.weightChartInstance) window.weightChartInstance.destroy();
            window.weightChartInstance = new Chart(ctx, {
                type: 'line',
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day',
                                displayFormats: { day: 'MMM d, yyyy' }
                            },
                            title: { display: true, text: 'Date' }
                        },
                        y: {
                            title: { display: true, text: 'Weight (lbs)' }
                        }
                    }
                }
            });
        }

        const computedStartDate = new Date('<?php echo $computedStartDate; ?>');
        const computedEndDate = new Date('<?php echo $computedEndDate; ?>');
        const filtered = fullDataPoints.filter(p => (!isNaN(computedStartDate) ? p.date >= computedStartDate : true) && (!isNaN(computedEndDate) ? p.date <= computedEndDate : true));

        renderChart(filtered);
    </script>
</body>
</html>
