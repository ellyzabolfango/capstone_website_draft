<?php
/**
 * migrate.php (Capstone Edition)
 *
 * - Ensures database exists
 * - Executes SQL migrations in /migrations
 * - Seeds default admin + settings row (idempotent)
 *
 *    All schema definitions (DDL) should stay in /migrations/*.sql
 *    to avoid duplication or drift. This file is only for orchestration + seeding.
 */

declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =========================
|  0) CONFIGURATION
|========================= */
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'sk_capstone_db';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

/** Default admin (password will be hashed) */
$ADMIN_USERNAME  = getenv('ADMIN_USERNAME') ?: 'admin';
$ADMIN_EMAIL     = getenv('ADMIN_EMAIL')    ?: 'admin@example.com';
$ADMIN_FULLNAME  = getenv('ADMIN_FULLNAME') ?: 'Administrator';
$ADMIN_PASSWORD  = getenv('ADMIN_PASSWORD') ?: 'Admin123';

/** Migrations directory (relative to this file) */
$MIGRATIONS_DIR = __DIR__ . '/migrations';

/* =========================
|  Helpers (simple HTML output)
|========================= */
function out(string $msg): void {
  echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "<br>\n";
}
function section(string $title): void {
  out(str_repeat('=', 60));
  out($title);
  out(str_repeat('=', 60));
}
function execMulti(mysqli $conn, string $sql): void {
  if (trim($sql) === '') return;
  $ok = $conn->multi_query($sql);
  if (!$ok) throw new RuntimeException("multi_query failed: " . $conn->error);
  while ($conn->more_results()) {
    $conn->next_result();
    if ($res = $conn->store_result()) $res->free();
  }
}
function runSqlFiles(mysqli $conn, string $dir): int {
  if (!is_dir($dir)) {
    out("⚠️ No migrations directory found at: $dir (skipping)");
    return 0;
  }
  $files = array_values(array_filter(scandir($dir), fn($f) => preg_match('/\.sql$/i', $f)));
  natsort($files);
  $count = 0;
  foreach ($files as $f) {
    $path = $dir . DIRECTORY_SEPARATOR . $f;
    $sql  = @file_get_contents($path);
    if (!$sql || trim($sql) === '') {
      out("→ $f skipped (empty file)");
      continue;
    }
    echo "→ Running $f ... ";
    try {
      execMulti($conn, $sql);
      echo "✅<br>\n";
      $count++;
    } catch (Throwable $e) {
      echo "❌ " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "<br>\n";
    }
  }
  return $count;
}

/* =========================
|  1) CONNECT / CREATE DB
|========================= */
try {
  section('Database Setup');
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
  $conn->set_charset('utf8mb4');

  $conn->query(sprintf(
    "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
    $conn->real_escape_string($DB_NAME)
  ));
  $conn->select_db($DB_NAME);
  out("✅ Database ensured and selected: {$DB_NAME}");

  /* =========================
  |  2) TEMPORARILY DISABLE FKs
  |========================= */
  try {
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    out("🧩 FOREIGN_KEY_CHECKS disabled during migration");
  } catch (Throwable $e) {
    out("⚠️ Could not disable FOREIGN_KEY_CHECKS: " . $e->getMessage());
  }

  /* =========================
  |  3) EXECUTE MIGRATION FILES
  |========================= */
  section('Running SQL Migrations');
  $ranFiles = runSqlFiles($conn, $MIGRATIONS_DIR);
  out("✔ SQL migration files executed: {$ranFiles}");

  /* =========================
  |  4) SEED DEFAULT ADMIN
  |========================= */
  section('Seeding Admin');
  try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $ADMIN_USERNAME, $ADMIN_EMAIL);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;

    if ($exists) {
      out("👤 Admin already exists — seed skipped");
    } else {
      $hash = password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT);
      $stmt = $conn->prepare(
        "INSERT INTO users (username, fullname, email, role, password, is_active, barangay, position)
         VALUES (?, ?, ?, 'admin', ?, 1, 'Calbayog City', 'SK Federation')"
      );
      $stmt->bind_param("ssss", $ADMIN_USERNAME, $ADMIN_FULLNAME, $ADMIN_EMAIL, $hash);
      $stmt->execute();
      out("👤 Seeded default admin (username: {$ADMIN_USERNAME} / password: {$ADMIN_PASSWORD})");
    }
  } catch (Throwable $e) {
    out("⚠️ Admin seed error: " . $e->getMessage());
  }

} catch (Throwable $fatal) {
  section('FATAL ERROR');
  out('❌ ' . $fatal->getMessage());
} finally {
  if (isset($conn) && $conn instanceof mysqli) {
    try { $conn->query("SET FOREIGN_KEY_CHECKS=1"); } catch (Throwable $e) { /* ignore */ }
    $conn->close();
  }
}

out('');
out("✅ Migration complete (capstone schema).");
