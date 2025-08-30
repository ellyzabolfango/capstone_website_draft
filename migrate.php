<?php
// migrate.php — creates DB (if missing), runs migrations/*.sql, backfills missing tables, seeds admin & settings
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$db   = "sk_capstone_db"; // <-- keep consistent with config
$user = "root";
$pass = "";

// Defaults for admin seeding (PHP-hashed)
$ADMIN_USERNAME = "admin";
$ADMIN_EMAIL    = "admin@example.com";
$ADMIN_FULLNAME = "Administrator";
$ADMIN_PASSWORD = "Admin123"; // hashed below

function line($msg) { echo $msg . "<br>\n"; }

try {
  // 1) Connect without DB, ensure DB exists, select it
  $conn = new mysqli($host, $user, $pass);
  $conn->set_charset("utf8mb4");
  $conn->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $conn->select_db($db);
  line("✅ Database ensured and selected: $db");
} catch (Throwable $e) {
  http_response_code(500);
  exit("❌ DB connection failed: " . htmlspecialchars($e->getMessage()));
}

// 2) Turn off FK checks during migration (avoids ordering issues)
try {
  $conn->query("SET FOREIGN_KEY_CHECKS=0");
} catch (Throwable $e) {
  line("⚠️ Could not disable FOREIGN_KEY_CHECKS: " . htmlspecialchars($e->getMessage()));
}

// 3) Run all .sql files in /migrations (sorted by natural order)
$migDir = __DIR__ . '/migrations';
$ran = 0;
if (is_dir($migDir)) {
  $files = array_values(array_filter(scandir($migDir), fn($f) => preg_match('/\.sql$/i', $f)));
  sort($files, SORT_NATURAL);

  foreach ($files as $f) {
    $path = $migDir . DIRECTORY_SEPARATOR . $f;
    $sql  = @file_get_contents($path);
    if (!$sql || !trim($sql)) { line("→ $f skipped (empty)"); continue; }

    echo "→ Running $f ... ";
    try {
      if ($conn->multi_query($sql)) {
        while ($conn->more_results() && $conn->next_result()) { /* flush */ }
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

// 4) Backfill: ensure core tables exist even if a migration was missing/commented
//    (These create statements are safe because of IF NOT EXISTS)
$backfills = [
  'users' => <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  fullname VARCHAR(100) NOT NULL DEFAULT '',
  email VARCHAR(100) NOT NULL UNIQUE,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  password VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
  'proposals' => <<<SQL
CREATE TABLE IF NOT EXISTS proposals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  budget_allocated DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('Pending','Approved','Rejected','Completed') NOT NULL DEFAULT 'Pending',
  start_date DATE NULL,
  end_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_proposals_user_id (user_id),
  CONSTRAINT fk_proposals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
  'activities' => <<<SQL
CREATE TABLE IF NOT EXISTS activities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  status ENUM('Not Started','Ongoing','Completed') NOT NULL DEFAULT 'Not Started',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activities_proposal_id (proposal_id),
  CONSTRAINT fk_activities_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
  // If your "reports" migration actually creates "templates", keep both as needed:
  'reports' => <<<SQL
CREATE TABLE IF NOT EXISTS reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,
  report_type VARCHAR(50) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reports_proposal_id (proposal_id),
  CONSTRAINT fk_reports_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
  'templates' => <<<SQL
CREATE TABLE IF NOT EXISTS templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  file_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
  'settings' => <<<SQL
CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  total_budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  fiscal_year YEAR NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

foreach ($backfills as $table => $ddl) {
  try {
    $conn->query($ddl);
    line("✅ Ensured table: $table");
  } catch (Throwable $e) {
    line("❌ Ensure table $table failed: " . htmlspecialchars($e->getMessage()));
  }
}

// 5) Restore FK checks
try {
  $conn->query("SET FOREIGN_KEY_CHECKS=1");
} catch (Throwable $e) {
  line("⚠️ Could not re-enable FOREIGN_KEY_CHECKS: " . htmlspecialchars($e->getMessage()));
}

// 6) Idempotent admin seed via PHP
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

// 7) Seed settings row if table exists & empty
try {
  if ($conn->query("SHOW TABLES LIKE 'settings'")->num_rows) {
    $row = $conn->query("SELECT COUNT(*) c FROM settings")->fetch_assoc();
    if ((int)$row['c'] === 0) {
      $conn->query("INSERT INTO settings (id, total_budget, fiscal_year) VALUES (1, 0.00, YEAR(CURDATE()))");
      line("⚙️  Seeded settings (total_budget=0.00)");
    } else {
      line("⚙️  Settings already present — seed skipped");
    }
  } else {
    line("⚠️  'settings' table not found (skipped seed)");
  }
} catch (Throwable $e) {
  line("⚠️ Settings seed error: " . htmlspecialchars($e->getMessage()));
}

echo "<br>✅ Migration complete. Ran $ran SQL migration file(s).";
