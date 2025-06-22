<?php
session_start();
$data_dir = realpath(__DIR__ . '/../data');
$username = $_SESSION['username'] ?? null;
if (!$username) {
    http_response_code(403);
    exit("Unauthorized");
}

$config_db = new PDO("sqlite:$data_dir/config.db");
$config_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$workout_db = new PDO("sqlite:$data_dir/{$username}_workout_log.db");
$workout_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// When loading the page, return a suggested exercise per group
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['mode'] === 'initial') {
    $results = [];

    // Get recent exercises (last 6 days excluding today)
    $cutoff = (new DateTime('6 days ago'))->format('Y-m-d');
    $today = (new DateTime())->format('Y-m-d');
    $recent_stmt = $workout_db->prepare("
        SELECT DISTINCT exercise FROM workouts
        WHERE date >= ? AND date < ? AND user = ?
    ");
    $recent_stmt->execute([$cutoff, $today, $username]);
    $recent_exercises = $recent_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Load muscle groups and exercises
    $groups = $config_db->query("SELECT id, name, category FROM muscle_groups ORDER BY category, display_order, name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($groups as $group) {
        $key = strtolower(str_replace([' ', '-'], '_', $group['category'] . ' - ' . $group['name']));

        // Find exercises for this group
        $stmt = $config_db->prepare("SELECT name FROM exercises WHERE group_id = ? AND subgroup_id IS NULL ORDER BY name");
        $stmt->execute([$group['id']]);
        $all_exercises = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Exclude recently used exercises
        $candidates = array_diff($all_exercises, $recent_exercises);
        $exercise = $candidates ? $candidates[array_rand($candidates)] : null;

        // Get last weight used for this group+exercise
        $weight = null;
        if ($exercise) {
            $wstmt = $workout_db->prepare("SELECT weight FROM workouts WHERE exercise = ? AND muscle_group = ? ORDER BY date DESC LIMIT 1");
            $group_label = $group['category'] . ' - ' . $group['name'];
            $wstmt->execute([$exercise, $group_label]);
            $weight = $wstmt->fetchColumn();
        }

        $results[$key] = [
            'exercise' => $exercise,
            'weight' => $weight,
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Default response for POST (dropdown selection)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $exercise = $_POST['exercise'] ?? '';
    $group = $_POST['muscle_group'] ?? '';

    if ($exercise && $group) {
        $stmt = $workout_db->prepare("SELECT weight, reps1, reps2, reps3 FROM workouts WHERE exercise = ? AND muscle_group = ? ORDER BY date DESC LIMIT 1");
        $stmt->execute([$exercise, $group]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'text' => "Last: {$row['weight']} lbs, Reps: {$row['reps1']}, {$row['reps2']}, {$row['reps3']}",
                'weight' => $row['weight']
            ]);
        } else {
            echo json_encode([
                'text' => "No history found for this exercise.",
                'weight' => null
            ]);
        }
    } else {
        echo json_encode([
            'text' => "Invalid input.",
            'weight' => null
        ]);
    }

    exit;
}
