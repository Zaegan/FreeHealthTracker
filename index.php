<?php
session_start();

// --- LOGIN SYSTEM WITH HASHED CREDENTIALS ---

// Connect to user credentials database
$credentials_db = new PDO('sqlite:/var/www/html/data/user_credentials.db');
$credentials_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle login
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        $stmt = $credentials_db->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($_POST['password'], $row['password'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $_POST['username'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login</title>
        <style>
            body { font-family: sans-serif; padding: 20px; text-align: center; }
            form { display: inline-block; margin-top: 50px; }
            input[type=text], input[type=password] { display: block; margin: 10px auto; padding: 10px; width: 200px; }
            input[type=submit] { padding: 10px 20px; }
            .error { color: red; }
        </style>
    </head>
    <body>
        <h1>Please Log In</h1>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="submit" value="Login">
        </form>
    </body>
    </html>
    <?php
    exit();
}

// --- END LOGIN HANDLING ---

// Connect to user-specific workout DB
$username = $_SESSION['username'];
$workout_db_path = "/var/www/html/data/{$username}_workout_log.db";
$db = new PDO("sqlite:" . $workout_db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Initialize workout table
$db->exec("CREATE TABLE IF NOT EXISTS workouts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT,
    user TEXT,
    muscle_group TEXT,
    exercise TEXT,
    weight INTEGER,
    reps1 INTEGER,
    reps2 INTEGER,
    reps3 INTEGER
)");

// Define exercise categories
$workout_exercises = [
    'Compound' => [
        'Chest' => ['Bench Press', 'Incline Dumbbell Bench Press', 'Flat Dumbbell Bench Press', 'Pushups', 'Pec Dips', 'Pec Fly Machine'],
        'Quads' => ['Back Squat', 'Goblet Squat', 'Walking Lunges', 'Deadlift'],
        'Lats' => [
            'Vertical' => ['Lat Pulldown', 'Assisted Pullup'],
            'Horizontal' => ['Cable rows', 'Single arm Dumbbell rows', 'Australian Pushups', 'Dumbbell Rows']
        ],
        'Shoulders' => ['Dumbbell Overhead Press', 'Machine Overhead Press']
    ],
    'Isolation' => [
        'Biceps' => ['Barbell Preacher Curl', 'Dumbbell Preacher curl', 'Dumbbell curls', 'Cable Curls', 'Hammer curls', 'Barbell 21 Curls'],
        'Triceps' => ['Skull Crusher', 'Bench Dips', 'Cable pushdowns', 'Cable overheads'],
        'Shoulders' => ['Dumbbell Shrugs', 'Lateral Raises', 'Farmers Carries', 'Cable Lateral Raises'],
        'Hamstrings' => ['Single Leg Glute Bridge', 'Single Leg Bench Hip Thrust', 'Weighted Bench Hip Thrust', 'Slider Hamstring Curl', 'Single Leg Romanian Deadlift', 'Machine Hamstring Curl', 'Romanian Deadlift'],
        'Core' => ['Hanging Leg Raises', 'Cable Crunches', 'Forearm Plank']
    ]
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_weight'])) {
    $weight = floatval($_POST['bodyweight']);
    $weight_db = new PDO("sqlite:/var/www/html/data/{$username}_weight_log.db");
    $weight_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $weight_db->exec("CREATE TABLE IF NOT EXISTS weights (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT,
        weight REAL
    )");
    $today = date('Y-m-d');
    $stmt = $weight_db->prepare("INSERT INTO weights (date, weight) VALUES (?, ?)");
    $stmt->execute([$today, $weight]);
    echo "<p style='text-align:center;'>Weight recorded: {$weight} lbs</p>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $date = date('Y-m-d');
    $user = $_SESSION['username'];
    foreach ($workout_exercises as $category) {
        foreach ($category as $group => $exercises) {
            if ($group === 'Lats' && is_array($exercises) && isset($exercises['Vertical'])) {
                foreach ($exercises as $subtype => $list) {
                    $label = "{$group}_{$subtype}";
                    $exercise = $_POST[$label.'_exercise'] ?? '';
                    $weight = intval($_POST[$label.'_weight'] ?? 0);
                    $reps1 = intval($_POST[$label.'_reps1'] ?? 0);
                    $reps2 = intval($_POST[$label.'_reps2'] ?? 0);
                    $reps3 = intval($_POST[$label.'_reps3'] ?? 0);
                    if ($exercise) {
                        $stmt = $db->prepare("INSERT INTO workouts (date, user, muscle_group, exercise, weight, reps1, reps2, reps3)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$date, $user, "$group-$subtype", $exercise, $weight, $reps1, $reps2, $reps3]);
                    }
                }
            } else {
                $exercise = $_POST[$group.'_exercise'] ?? '';
                $weight = intval($_POST[$group.'_weight'] ?? 0);
                $reps1 = intval($_POST[$group.'_reps1'] ?? 0);
                $reps2 = intval($_POST[$group.'_reps2'] ?? 0);
                $reps3 = intval($_POST[$group.'_reps3'] ?? 0);
                if ($exercise) {
                    $stmt = $db->prepare("INSERT INTO workouts (date, user, muscle_group, exercise, weight, reps1, reps2, reps3)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$date, $user, $group, $exercise, $weight, $reps1, $reps2, $reps3]);
                }
            }
        }
    }
    echo "<p>Workout saved!</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workout Logger</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        h1 { text-align: center; }
        form { max-width: 600px; margin: auto; }
        .muscle-group { border: 1px solid #ccc; margin: 10px 0; padding: 10px; border-radius: 8px; }
        label { display: block; margin-top: 5px; }
        input[type=text], input[type=number], select { width: 100%; padding: 5px; }
        .suggestion { font-style: italic; color: #555; margin-top: 5px; }
    </style>
    <script>
    function fetchSuggestion(selectElement, muscleGroup) {
        const exercise = selectElement.value;
        const suggestionBox = selectElement.closest('.muscle-group').querySelector('.suggestion');
        if (!exercise) {
            suggestionBox.textContent = '';
            return;
        }

        const formData = new FormData();
        formData.append('exercise', exercise);
        formData.append('muscle_group', muscleGroup);

        fetch('suggest.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => suggestionBox.textContent = text)
        .catch(() => suggestionBox.textContent = 'Error retrieving suggestion');
    }
    </script>
</head>
<body>
    <div style="text-align: right;"><a href="?logout=1">Logout</a></div>
    <h1>Workout for <?= date('Y-m-d') ?></h1>

  
<!-- Quick Weight Entry (only after login) -->
<form method="post" style="max-width: 600px; margin: auto; text-align: center; margin-bottom: 20px;">
    <label for="bodyweight">Today's Body Weight (lbs):</label>
    <input type="number" name="bodyweight" id="bodyweight" step="0.1" required>
    <input type="submit" name="save_weight" value="Log Weight">
</form>
<p style='text-align:center;'><a href="weight_log.php">View Weight History</a></p>
    <form method="post">
        <?php
        foreach ($workout_exercises as $category => $muscles) {
            echo "<h2>$category</h2>";
            foreach ($muscles as $group => $options) {
                if ($group === 'Lats' && is_array($options) && isset($options['Vertical'])) {
                    foreach ($options as $sub => $list) {
                        $label = "{$group}_{$sub}";
                        $muscleKey = "$group-$sub";
                        echo "<div class='muscle-group'>";
                        echo "<h3>$group ($sub)</h3>";
                        echo "<label>Exercise: <select name='{$label}_exercise' onchange=\"fetchSuggestion(this, '$muscleKey')\">";
                        echo "<option value=''>-- Select --</option>";
                        foreach ($list as $exercise) echo "<option>$exercise</option>";
                        echo "</select></label>";
                        echo "<p class='suggestion'></p>";
                        echo "<label>Weight (lbs): <input type='number' name='{$label}_weight'></label>";
                        echo "<label>Set 1 Reps: <input type='number' name='{$label}_reps1'></label>";
                        echo "<label>Set 2 Reps: <input type='number' name='{$label}_reps2'></label>";
                        echo "<label>Set 3 Reps: <input type='number' name='{$label}_reps3'></label>";
                        echo "</div>";
                    }
                } else {
                    $muscleKey = $group;
                    echo "<div class='muscle-group'>";
                    echo "<h3>$group</h3>";
                    echo "<label>Exercise: <select name='{$group}_exercise' onchange=\"fetchSuggestion(this, '$muscleKey')\">";
                    echo "<option value=''>-- Select --</option>";
                    foreach ($options as $exercise) echo "<option>$exercise</option>";
                    echo "</select></label>";
                    echo "<p class='suggestion'></p>";
                    echo "<label>Weight (lbs): <input type='number' name='{$group}_weight'></label>";
                    echo "<label>Set 1 Reps: <input type='number' name='{$group}_reps1'></label>";
                    echo "<label>Set 2 Reps: <input type='number' name='{$group}_reps2'></label>";
                    echo "<label>Set 3 Reps: <input type='number' name='{$group}_reps3'></label>";
                    echo "</div>";
                }
            }
        }
        ?>
        <input type="submit" name="save" value="Save Workout">
    </form>

<form method="post" style="text-align: center; margin-top: 20px;">
    <input type="submit" name="view_log" value="View Workout Log">
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_log'])) {
    echo "<h2 style='text-align:center;'>Workout History</h2>";
    echo "<div style='max-width: 800px; margin: auto;'>";
    $result = $db->query("SELECT * FROM workouts ORDER BY date ASC, id ASC");
    $entries = $result->fetchAll();
    if (count($entries) === 0) {
        echo "<p style='text-align:center; font-style:italic;'>No workouts have been recorded yet.</p>";
    } else {
        foreach ($entries as $row) {
            echo "<div style='border:1px solid #ccc; padding:10px; margin:5px 0; border-radius:6px;'>";
            echo "<strong>Date:</strong> {$row['date']}<br>";
            echo "<strong>Muscle Group:</strong> {$row['muscle_group']}<br>";
            echo "<strong>Exercise:</strong> {$row['exercise']}<br>";
            echo "<strong>Weight:</strong> {$row['weight']} lbs<br>";
            echo "<strong>Reps:</strong> {$row['reps1']}, {$row['reps2']}, {$row['reps3']}";
            echo "</div>";
        }
    }
    echo "</div>";
}
?>
</body>
</html>

