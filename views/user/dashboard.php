<?php
// /views/user/dashboard.php
session_start();
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
auth_required();

$logoPath = BASE_URL . "/public/assets/icons/sklogo.png";

// 1) Status counts for this user
$statuses = ['Approved','Rejected','Pending','Completed'];
$statusCounts = array_fill_keys($statuses, 0);

$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = db()->prepare("SELECT status, COUNT(*) AS c FROM proposals WHERE submitted_by = ? GROUP BY status");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $k = $row['status'];
  if (isset($statusCounts[$k])) $statusCounts[$k] = (int)$row['c'];
}

// 2) Fund remaining (global): settings.total_budget - SUM(approved/completed proposal budgets)
$fundRemaining = 40000.00; // fallback
$settingsRow = db()->query("SELECT total_budget FROM settings WHERE id=1")->fetch_assoc();
if ($settingsRow) {
  $totalBudget = (float)$settingsRow['total_budget'];
  $usedRow = db()->query("SELECT COALESCE(SUM(budget),0) AS used FROM proposals WHERE status IN ('Approved','Completed')")->fetch_assoc();
  $used = (float)($usedRow['used'] ?? 0);
  $fundRemaining = max(0.0, $totalBudget - $used);
}
$fundRemainingDisplay = number_format($fundRemaining, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Overview</title>
  <style>/* keep your CSS exactly as posted */</style>
</head>
<body>
  <div class="sidebar">
    <img src="<?= htmlspecialchars($logoPath) ?>" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/admin_pov.php" class="active"> 📊 Dashboard</a>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/proposals.php">📁 Proposals</a>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/templates.php">📄 Document Templates</a>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/reports.php">📑 Reports</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <div class="header">OVERVIEW</div>
      <div class="icons">🔔 <div class="user-icon">👤</div></div>
    </div>

    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount">₱ <?= htmlspecialchars($fundRemainingDisplay, ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="summary">
      <h2>Overview Proposals</h2>
      <div class="status-grid">
        <div class="status-box approved"  data-count="<?= (int)$statusCounts['Approved'] ?>">APPROVED</div>
        <div class="status-box rejected"  data-count="<?= (int)$statusCounts['Rejected'] ?>">REJECTED</div>
        <div class="status-box pending"   data-count="<?= (int)$statusCounts['Pending'] ?>">PENDING</div>
        <div class="status-box completed" data-count="<?= (int)$statusCounts['Completed'] ?>">COMPLETED</div>
      </div>
    </div>
  </div>
</body>
</html>
