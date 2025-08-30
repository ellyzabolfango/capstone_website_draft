<?php
// config/db.php
// Minimal, safe-ish mysqli setup with a global $conn (for existing code)
// and a db() helper for new code. UTF-8 ready.

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = "localhost";
$DB_NAME = "sk_capstone_db";
$DB_USER = "root";
$DB_PASS = "";

function db() {
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;

    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    try {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Throwable $e) {
        // In production, avoid echoing raw errors. Keep it generic.
        http_response_code(500);
        exit("Database connection error.");
    }
}

// Back-compat for files that expect $conn
$conn = db();
