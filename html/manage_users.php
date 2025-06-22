<?php
// Allow only CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

$path = '/var/www/html/data/user_credentials.db';

try {
    $db = new PDO("sqlite:$path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    exit("Failed to connect to database: " . $e->getMessage() . "\n");
}

// Ensure users table exists
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
)");

$args = $argv;
array_shift($args); // Remove the script name

if (count($args) === 0) {
    // List all usernames
    echo "Registered users:\n";
    $stmt = $db->query("SELECT username FROM users ORDER BY username ASC");
    foreach ($stmt as $row) {
        echo " - " . $row['username'] . "\n";
    }
    exit(0);
}

if (count($args) === 2 && $args[0] === '-remove') {
    $target = preg_replace('/[^a-zA-Z0-9_-]/', '', $args[1]);

    if ($target === '') {
        exit("Invalid username format.\n");
    }

    $stmt = $db->prepare("DELETE FROM users WHERE username = ?");
    $stmt->execute([$target]);

    if ($stmt->rowCount() > 0) {
        echo "User '$target' removed successfully.\n";
    } else {
        echo "User '$target' not found.\n";
    }
    exit(0);
}

// Help message
echo "Usage:\n";
echo "  php manage_users.php           # List all usernames\n";
echo "  php manage_users.php -remove USERNAME   # Remove a specific user\n";
exit(1);
