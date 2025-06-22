Repository Overview

This project is a simple PHP web application for logging workouts and tracking body weight. It stores data in SQLite databases located under the data/ directory (which is ignored by git via .gitignore). There are no additional subdirectories or configuration files—everything is at the repository root.

.
├── create_user.php
├── index.php
├── logout.php
├── suggest.php
├── weight_log.php
├── index_backup.php            (unused placeholder page)
├── data/
│   └── placeholder.txt         (empty placeholder; real DBs created at runtime)
└── LICENSE
Main Components
User Authentication & Workout Logging (index.php):

Implements a login form and verifies credentials against a SQLite database (user_credentials.db), using hashed passwords. The first portion demonstrates session handling and the login logic:

session_start();
// Connect to user credentials database
$credentials_db = new PDO('sqlite:/var/www/html/data/user_credentials.db');
...
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        ...
        if ($row && password_verify($_POST['password'], $row['password'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $_POST['username'];
            ...
        } else {
            $error = "Invalid username or password.";
        }
    }
    ...
}

After login, a user-specific workout database ({username}_workout_log.db) is created or opened, and workout entries are saved to it.

The HTML page presents a dynamic form listing exercises by muscle group and allows the user to log sets, weights, and reps. Logged entries can be viewed in the same page.

Weight Tracking (weight_log.php):

Handles CSV import/export of body weight data, allows setting goal trajectories, and displays a chart of weight over time using Chart.js. It stores user-specific data in {username}_weight_log.db.

Example of CSV import/export logic and storage:

$db = new PDO("sqlite:/var/www/html/data/{$username}_weight_log.db");
...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        ...
        $stmt = $db->prepare("INSERT INTO weights (date, weight) VALUES (?, ?)");
        $stmt->execute([$date, $weight]);
    }
    ...
}
...
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"{$username}_weight_log.csv\"");
    ...
}

The page plots both recorded weights and a biweekly moving average, with optional goal trajectory lines.

AJAX Suggestion Endpoint (suggest.php):

Provides last-used weight and rep count for a given exercise, enabling quick hints on the workout form:

session_start();
if (!isset($_SESSION['username'])) { ... }
...
$db = new PDO("sqlite:$db_path");
$stmt = $db->prepare("SELECT weight, reps1, reps2, reps3 FROM workouts WHERE muscle_group = ? AND exercise = ? ORDER BY id DESC LIMIT 3");
$stmt->execute([$muscle_group, $exercise]);
...
echo "Last used $latest_weight lbs for up to $max_reps reps";

User Creation Script (create_user.php):

A command-line utility that initializes the credentials database and inserts a default user with a hashed password. It refuses to run through the web:

// Prevent web-based execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

Logout (logout.php):

Destroys the session and redirects back to the login page.

General Notes
Databases: Each user has separate databases for workouts and weight logs. These files are placed in the /var/www/html/data/ directory and are named using the username as a prefix.

Data Directory: The repo contains a data/placeholder.txt to keep the folder in version control, but real .db files are generated at runtime.

Client-Side Charting: Weight history visualization uses Chart.js, with a moving average and optional goal trajectory lines.

Next Steps to Explore
Security Hardening

Input validation and sanitization could be improved (e.g., ensuring numeric values for weights/reps, limiting file uploads).

Consider using HTTPS and secure session cookies if deploying publicly.

Adding a README

Document setup steps (PHP environment, file permissions, how to run create_user.php) and usage instructions for future contributors.

Extending Functionality

Add user registration from the web (not just the CLI script).

Implement password resets or update features.

Testing

Explore writing unit tests or integration tests for the PHP endpoints.

Consider splitting logic into reusable functions/classes for easier maintenance.

Overall, the codebase is a straightforward PHP project with minimal dependencies. Learning more about PHP sessions, PDO for SQLite, and basic frontend JavaScript (especially Chart.js) will help when extending or maintaining this application.
