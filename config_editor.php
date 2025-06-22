<?php
// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

// Connect to config DB
$db = new PDO("sqlite:/var/www/html/data/config.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables if not exist
$db->exec("
CREATE TABLE IF NOT EXISTS muscle_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    category TEXT CHECK(category IN ('Compound', 'Isolation')) NOT NULL,
    display_order INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS subgroups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    FOREIGN KEY(group_id) REFERENCES muscle_groups(id)
);
CREATE TABLE IF NOT EXISTS exercises (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subgroup_id INTEGER,
    group_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    sets INTEGER DEFAULT 3,
    FOREIGN KEY(group_id) REFERENCES muscle_groups(id),
    FOREIGN KEY(subgroup_id) REFERENCES subgroups(id)
);");

// Argument parsing
$args = $argv;
array_shift($args); // Remove script name

if (count($args) === 0) {
    echo "Usage:\n";
    echo "  php config_editor.php -list\n";
    echo "  php config_editor.php -add [compound|isolation] [group] [exercise name] [sets]\n";
    exit(0);
}

$action = $args[0];

if ($action === '-list') {
    echo "Current Exercises:\n";

    $groups = $db->query("SELECT id, name, category FROM muscle_groups ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($groups as $group) {
        $group_id = $group['id'];
        $group_name = $group['name'];
        $category = $group['category'];
        echo "[$category] $group_name:\n";

        $stmt = $db->prepare("SELECT name, sets FROM exercises WHERE group_id = ? AND subgroup_id IS NULL ORDER BY name");
        $stmt->execute([$group_id]);
        $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($exercises as $ex) {
            echo "  - {$ex['name']} ({$ex['sets']} sets)\n";
        }
    }

} elseif ($action === '-add' && count($args) === 5) {
    list($_, $category, $group, $exercise, $sets) = $args;

    $category = ucfirst(strtolower($category));
    if (!in_array($category, ['Compound', 'Isolation'])) {
        exit("Invalid category: $category (must be 'Compound' or 'Isolation')\n");
    }

    // Check if muscle group exists
    $stmt = $db->prepare("SELECT id FROM muscle_groups WHERE name = ? AND category = ?");
    $stmt->execute([$group, $category]);
    $group_id = $stmt->fetchColumn();

    if (!$group_id) {
        // Get max display_order in this category
        $stmt = $db->prepare("SELECT MAX(display_order) FROM muscle_groups WHERE category = ?");
        $stmt->execute([$category]);
        $max_order = $stmt->fetchColumn();
        $next_order = is_numeric($max_order) ? $max_order + 1 : 0;

        $stmt = $db->prepare("INSERT INTO muscle_groups (name, category, display_order) VALUES (?, ?, ?)");
        $stmt->execute([$group, $category, $next_order]);
        $group_id = $db->lastInsertId();
        echo "Created new muscle group: $group under $category (order $next_order)\n";
    }

    // Add exercise
    $stmt = $db->prepare("INSERT INTO exercises (group_id, name, sets) VALUES (?, ?, ?)");
    $stmt->execute([$group_id, $exercise, (int)$sets]);

    echo "Exercise '$exercise' added to $category -> $group with $sets sets.\n";

} else {
    echo "Invalid command or arguments.\n";
}
