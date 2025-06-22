<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
$data_dir = realpath(__DIR__ . '/../data');

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header("Location: index.php");
    exit();
}

// Connect to credentials database
$path = "$data_dir/user_credentials.db";
$db = new PDO("sqlite:$path");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create users table if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
)");

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username and password are required.";
    } else {
        // Check if user already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = "That username is already taken.";
        } else {
            // Create user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashed]);

            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;

            header("Location: index.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Join</title>
    <style>
        body { font-family: sans-serif; padding: 2em; max-width: 500px; margin: auto; }
        form { display: flex; flex-direction: column; gap: 1em; }
        input[type="text"], input[type="password"] { font-size: 1.2em; padding: 0.5em; }
        button { font-size: 1.2em; padding: 0.5em; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Create Account</h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Username:
            <input type="text" name="username" required>
        </label>
        <label>Password:
            <input type="password" name="password" required>
        </label>
        <button type="submit">Join</button>
    </form>
    <p><a href="index.php">Back to Login</a></p>
</body>
</html>
