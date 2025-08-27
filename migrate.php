<?php
// migrate.php
// Run all SQL migrations in the /migrations folder

$host = "localhost";
$db = "sk_capstone_db";   // make sure DB exists first in phpMyAdmin
$user = "root";
$pass = "";

// connect
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "✅ Connected to database: $db\n";

// migrations folder
$migrationsDir = __DIR__ . "/migrations";

// scan files (sorted)
$files = glob($migrationsDir . "/*.sql");
sort($files);

foreach ($files as $file) {
    echo "\nRunning migration: " . basename($file) . "...\n";
    $sql = file_get_contents($file);

    if ($conn->multi_query($sql)) {
        do {
            // flush results (needed for multi_query)
        } while ($conn->more_results() && $conn->next_result());
        echo "   ✅ Success\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

$conn->close();
echo "\nAll migrations executed!\n";
?>