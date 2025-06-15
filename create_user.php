<?php
// Prevent web-based execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

$path = '/var/www/html/data/user_credentials.db';
$db = new PDO("sqlite:$path");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
)");

// Insert default test user with hashed password
$username = 'user';
$plaintext = 'defaultpassword';
$hashed = password_hash($plaintext, PASSWORD_DEFAULT);

$stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password) VALUES (?, ?)");
$stmt->execute([$username, $hashed]);

echo "User created or already exists.\n";
