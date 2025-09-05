<?php
/**
 * config/db.php
 *
 * Centralized database connection file.
 * Provides:
 *   - A db() helper function (preferred for new code)
 *   - A global $conn for legacy compatibility
 *
 * Uses mysqli with strict error reporting and UTF-8 encoding.
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database credentials (adjust as needed for deployment)
$DB_HOST = "localhost";
$DB_NAME = "sk_capstone_db";
$DB_USER = "root";
$DB_PASS = "";

/**
 * Returns a shared mysqli connection instance.
 * Uses a static variable to ensure only one connection is created.
 *
 * @return mysqli
 */
function db(): mysqli {
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;

    try {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        $conn->set_charset("utf8mb4"); // ensure full Unicode support
        return $conn;
    } catch (Throwable $e) {
        // In production: avoid leaking details; show generic message.
        http_response_code(500);
        exit("Database connection error.");
    }
}

// Legacy support: some files may expect a $conn global variable
$conn = db();
