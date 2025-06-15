<?php
session_start();

// Require user to be logged in
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo "User not authenticated";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed";
    exit();
}

if (empty($_POST['exercise']) || empty($_POST['muscle_group'])) {
    http_response_code(400);
    echo "Missing parameters";
    exit();
}

$exercise = $_POST['exercise'];
$muscle_group = $_POST['muscle_group'];
$username = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['username']); // Sanitize username
$db_path = "/var/www/html/data/{$username}_workout_log.db";

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure the workouts table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS workouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            muscle_group TEXT NOT NULL,
            exercise TEXT NOT NULL,
            weight REAL NOT NULL,
            reps1 INTEGER,
            reps2 INTEGER,
            reps3 INTEGER
        )
    ");

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT weight, reps1, reps2, reps3 FROM workouts WHERE muscle_group = ? AND exercise = ? ORDER BY id DESC LIMIT 3");
    $stmt->execute([$muscle_group, $exercise]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) === 0) {
        echo "No history for $exercise";
        exit();
    }

    $latest_weight = $rows[0]['weight'];
    $max_reps = max(array_merge(
        array_column($rows, 'reps1'),
        array_column($rows, 'reps2'),
        array_column($rows, 'reps3')
    ));

    echo "Last used $latest_weight lbs for up to $max_reps reps";

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>
