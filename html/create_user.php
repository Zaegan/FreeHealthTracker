<?php
// Prevent web-based execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

// Check for correct number of arguments
if ($argc !== 3) {
    echo "Usage: php create_user.php <username> <password>\n";
    exit(1);
}

$username = $argv[1];
$plaintext = $argv[2];

$data_dir = realpath(__DIR__ . '/../data');

// Connect to the database
$path = "$data_dir/user_credentials.db";
$db = new PDO("sqlite:$path");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
)");

// Hash the password
$hashed = password_hash($plaintext, PASSWORD_DEFAULT);

// Insert user
$stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password) VALUES (?, ?)");
$stmt->execute([$username, $hashed]);

echo "User '{$username}' created or already exists.\n";
