<?php
// migrate.php — creates DB (if missing), runs migrations/*.sql, backfills simplified tables, seeds admin & settings
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$db = "sk_capstone_db";
$user = "root";
$pass = "";

// Admin seed (via PHP hash)
$ADMIN_USERNAME = "admin";
$ADMIN_EMAIL = "admin@example.com";
$ADMIN_FULLNAME = "Administrator";
$ADMIN_PASSWORD = "Admin123"; // will be hashed

function line($msg)
{
  echo $msg . "<br>\n";
}

try {
  // 1) Ensure DB exists and select it
  $conn = new mysqli($host, $user, $pass);
  $conn->set_charset("utf8mb4");
  $conn->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $conn->select_db($db);
  line("✅ Database ensured and selected: $db");

  // 2) Disable FK checks during migration (restore in finally)
  $fkDisabled = false;
  try {
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $fkDisabled = true;
  } catch (Throwable $e) {
    line("⚠️ Could not disable FOREIGN_KEY_CHECKS: " . htmlspecialchars($e->getMessage()));
  }

  // 3) Run all .sql files (sorted)
  $ran = 0;
  $migDir = __DIR__ . '/migrations';
  if (is_dir($migDir)) {
    $files = array_values(array_filter(scandir($migDir), fn($f) => preg_match('/\.sql$/i', $f)));
    sort($files, SORT_NATURAL);
    foreach ($files as $f) {
      $path = $migDir . DIRECTORY_SEPARATOR . $f;
      $sql = @file_get_contents($path);
      if (!$sql || !trim($sql)) {
        line("→ $f skipped (empty)");
        continue;
      }

      echo "→ Running $f ... ";
      try {
        if ($conn->multi_query($sql)) {
          while ($conn->more_results() && $conn->next_result()) { /* flush */
          }
          echo "✅<br>";
          $ran++;
        } else {
          echo "❌ " . htmlspecialchars($conn->error) . "<br>";
        }
      } catch (Throwable $e) {
        echo "❌ " . htmlspecialchars($e->getMessage()) . "<br>";
      }
    }
  } else {
    line("⚠️ No /migrations folder found; skipping SQL files.");
  }

  // 4) Backfills (match the schemas)
  //    Safe to run anytime because of IF NOT EXISTS.
  $backfills = [
    // 001_create_users.sql (unchanged)
    'users' => <<<SQL
CREATE TABLE IF NOT EXISTS users (
   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  fullname VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  location VARCHAR(100) NOT NULL,
  position VARCHAR(100) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  password VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    // 002_create_proposals.sql (SIMPLE: single budget; optional ppa_ref & fiscal_year)
    'proposals' => <<<SQL
CREATE TABLE IF NOT EXISTS proposals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  attachment_path VARCHAR(255) NULL,

  source VARCHAR(100) NULL,
  ppa_ref VARCHAR(100) NULL,
  fiscal_year YEAR NULL,

  budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('Pending','Approved','Rejected','Completed') NOT NULL DEFAULT 'Pending',

  submitted_by INT UNSIGNED NULL,
  approved_by  INT UNSIGNED NULL,

  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (status),
  INDEX idx_fiscal (fiscal_year),

  CONSTRAINT fk_proposals_submitter FOREIGN KEY (submitted_by)
    REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_proposals_approver FOREIGN KEY (approved_by)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    // 003_create_activities.sql (SIMPLE)
    'activities' => <<<SQL
CREATE TABLE IF NOT EXISTS activities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,

  activity_name VARCHAR(150) NOT NULL,
  description TEXT NULL,

  start_date DATE NULL,
  end_date DATE NULL,

  status ENUM('Not Started','Ongoing','Completed') NOT NULL DEFAULT 'Not Started',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_proposal (proposal_id),

  CONSTRAINT fk_activities_proposal FOREIGN KEY (proposal_id)
    REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    // 004_create_templates.sql (SIMPLE)
    'templates' => <<<SQL
CREATE TABLE IF NOT EXISTS templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,

  uploaded_by INT UNSIGNED NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_uploader (uploaded_by),

  CONSTRAINT fk_templates_user FOREIGN KEY (uploaded_by)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    // 005_create_reports.sql (SIMPLE)
    'reports' => <<<SQL
CREATE TABLE IF NOT EXISTS reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  proposal_id INT UNSIGNED NULL,
  report_type VARCHAR(50) NOT NULL,
  title VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,

  generated_by INT UNSIGNED NULL,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_type (report_type),
  INDEX idx_proposal (proposal_id),

  CONSTRAINT fk_reports_proposal FOREIGN KEY (proposal_id)
    REFERENCES proposals(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_user FOREIGN KEY (generated_by)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    // 006_create_settings.sql (optional; seed handled below)
    'settings' => <<<SQL
CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  total_budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  fiscal_year YEAR NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
  ];

  $conn->begin_transaction();
  try {
    foreach ($backfills as $table => $ddl) {
      $conn->query($ddl);
      line("✅ Ensured table: $table");
    }
    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    line("❌ Backfill transaction failed: " . htmlspecialchars($e->getMessage()));
  }

  // 5) Seed admin (idempotent)
  try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
    $stmt->bind_param("ss", $ADMIN_USERNAME, $ADMIN_EMAIL);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;

    if ($exists) {
      line("👤 Admin already exists — seed skipped");
    } else {
      $hash = password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO users (username, fullname, email, role, password, is_active)
                              VALUES (?, ?, ?, 'admin', ?, 1)");
      $stmt->bind_param("ssss", $ADMIN_USERNAME, $ADMIN_FULLNAME, $ADMIN_EMAIL, $hash);
      $stmt->execute();
      line("👤 Seeded default admin ($ADMIN_USERNAME / $ADMIN_PASSWORD)");
    }
  } catch (Throwable $e) {
    line("⚠️ Admin seed error: " . htmlspecialchars($e->getMessage()));
  }

  // 6) Seed settings row if empty
  try {
    if ($conn->query("SHOW TABLES LIKE 'settings'")->num_rows) {
      $row = $conn->query("SELECT COUNT(*) c FROM settings")->fetch_assoc();
      if ((int) $row['c'] === 0) {
        $conn->query("INSERT INTO settings (id, total_budget, fiscal_year) VALUES (1, 0.00, YEAR(CURDATE()))");
        line("⚙️ Seeded settings (total_budget=0.00)");
      } else {
        line("⚙️ Settings already present — seed skipped");
      }
    } else {
      line("⚠️ 'settings' table not found (skipped seed)");
    }
  } catch (Throwable $e) {
    line("⚠️ Settings seed error: " . htmlspecialchars($e->getMessage()));
  }

} finally {
  try {
    $conn?->query("SET FOREIGN_KEY_CHECKS=1");
  } catch (Throwable $e) { /* noop */
  }
  $conn?->close();
}

echo "<br>✅ Migration complete.";
