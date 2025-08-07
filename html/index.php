<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// --- LOGIN SYSTEM WITH HASHED CREDENTIALS ---

$data_dir = realpath(__DIR__ . '/../data');
// Connect to user credentials database
$credentials_db = new PDO("sqlite:$data_dir/user_credentials.db");
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
        <p><a href="join.php">Create Account</a></p>
    </body>
    </html>
    <?php
    exit();
}

// --- END LOGIN HANDLING ---

// Connect to user-specific workout DB
$raw_username = $_SESSION['username'];
// Sanitize username for filesystem usage
$username = preg_replace('/[^a-zA-Z0-9_-]/', '', $raw_username);
$workout_db_path = "$data_dir/{$username}_workout_log.db";
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

// Load workout exercise definitions from config.db
$config_db = new PDO("sqlite:$data_dir/config.db");
$config_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$workout_exercises = [];
$group_stmt = $config_db->query("SELECT id, name, category FROM muscle_groups ORDER BY category, display_order, name");
while ($group = $group_stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $group['category'] . ' - ' . $group['name'];
    $ex_stmt = $config_db->prepare("SELECT name FROM exercises WHERE group_id = ? AND subgroup_id IS NULL ORDER BY name");
    $ex_stmt->execute([$group['id']]);
    $workout_exercises[$key] = $ex_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_weight'])) {
    $weight = floatval($_POST['bodyweight']);
    $weight_db = new PDO("sqlite:$data_dir/{$username}_weight_log.db");
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
    foreach ($workout_exercises as $group => $exercises) {
        $safe_key = strtolower(str_replace([' ', '-'], '_', $group));
        $exercise = $_POST["{$safe_key}_exercise"] ?? null;
        $weight = $_POST["{$safe_key}_weight"] ?? null;
        $reps1 = $_POST["{$safe_key}_reps1"] ?? null;
        $reps2 = $_POST["{$safe_key}_reps2"] ?? null;
        $reps3 = $_POST["{$safe_key}_reps3"] ?? null;

        $has_reps = ($reps1 !== null && $reps1 !== '') || ($reps2 !== null && $reps2 !== '') || ($reps3 !== null && $reps3 !== '');
        if ($exercise && $has_reps) {
            $stmt = $db->prepare("INSERT INTO workouts (date, user, muscle_group, exercise, weight, reps1, reps2, reps3) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$date, $user, $group, $exercise, $weight, $reps1, $reps2, $reps3]);
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
        foreach ($workout_exercises as $group => $exercises) {
            $safe_key = strtolower(str_replace([' ', '-'], '_', $group));
            $escaped_group = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
            $group_js = htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8');
            echo "<div class='muscle-group'>";
            echo "<h3>{$escaped_group}</h3>";
            echo "<label>Exercise: <select name='{$safe_key}_exercise' onchange=\"fetchSuggestion(this, {$group_js})\">";
            echo "<option value=''>-- Select --</option>";
            foreach ($exercises as $exercise) echo "<option>$exercise</option>";
            echo "</select></label>";
            echo "<p class='suggestion'></p>";
            echo "<label>Weight (lbs): <input type='number' name='{$safe_key}_weight'></label>";
            echo "<label>Set 1 Reps: <input type='number' name='{$safe_key}_reps1'></label>";
            echo "<label>Set 2 Reps: <input type='number' name='{$safe_key}_reps2'></label>";
            echo "<label>Set 3 Reps: <input type='number' name='{$safe_key}_reps3'></label>";
            echo "</div>";
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
            echo "<strong>Date:</strong> " . htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') . "<br>";
            echo "<strong>Muscle Group:</strong> " . htmlspecialchars($row['muscle_group'], ENT_QUOTES, 'UTF-8') . "<br>";
            echo "<strong>Exercise:</strong> " . htmlspecialchars($row['exercise'], ENT_QUOTES, 'UTF-8') . "<br>";
            echo "<strong>Weight:</strong> " . htmlspecialchars($row['weight'], ENT_QUOTES, 'UTF-8') . " lbs<br>";
            echo "<strong>Reps:</strong> " . htmlspecialchars($row['reps1'], ENT_QUOTES, 'UTF-8') . ", " . htmlspecialchars($row['reps2'], ENT_QUOTES, 'UTF-8') . ", " . htmlspecialchars($row['reps3'], ENT_QUOTES, 'UTF-8');
            echo "</div>";
        }
    }
    echo "</div>";
}
?>
</body>
</html>

<script>


function fetchSuggestion(selectElement, muscleGroup) {
    const exercise = selectElement.value;
    const groupDiv = selectElement.closest('.muscle-group');
    const suggestionBox = groupDiv.querySelector('.suggestion');
    const weightInput = groupDiv.querySelector('input[type=number][name$="_weight"]');

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
    .then(response => response.json())
    .then(data => {
        suggestionBox.textContent = data.text || '';
        if (weightInput && data.weight !== null && data.weight !== undefined) {
            weightInput.value = data.weight;
        }
    })
    .catch(() => {
        suggestionBox.textContent = 'Error retrieving suggestion';
    });
}
function prepopulateFields() {
    fetch("suggest.php?mode=initial")
        .then(res => res.json())
        .then(data => {
            for (const [group, suggestion] of Object.entries(data)) {
                const select = document.querySelector(`select[name="${group}_exercise"]`);
                const input = document.querySelector(`input[name="${group}_weight"]`);

                if (select && suggestion.exercise) {
                    for (const opt of select.options) {
                        if (opt.value === suggestion.exercise) {
                            opt.selected = true;
                            break;
                        }
                    }

                    // Also trigger POST suggestion to display guidance
                    const fullGroup = select.closest('.muscle-group').querySelector('h3')?.textContent ?? '';
                    fetchSuggestion(select, fullGroup);

                }

                if (input && suggestion.weight !== null) {
                    input.value = suggestion.weight;
                }
            }
        })
        .catch(err => {
            console.error("Error fetching suggestions:", err);
        });
}



function waitForFieldsThenPopulate() {
    const ready = document.querySelector('select[name$="_exercise"]') && document.querySelector('input[name$="_weight"]');
    if (ready) {
        prepopulateFields();
    } else {
        setTimeout(waitForFieldsThenPopulate, 100);
    }
}

document.addEventListener("DOMContentLoaded", waitForFieldsThenPopulate);
</script>
