<?php
// /views/user/dashboard.php (or your current file path)
session_start();
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
auth_required();

// ---------- dynamic data ----------

// 1) Proposal status counts for THIS user
$statuses = ['Approved','Rejected','Pending','Completed'];
$statusCounts = array_fill_keys($statuses, 0);

$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare("SELECT status, COUNT(*) AS c FROM proposals WHERE submitted_by = ? GROUP BY status");
if ($stmt) {
  $stmt->bind_param('i', $userId);
  if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $k = $row['status'];
      if (isset($statusCounts[$k])) $statusCounts[$k] = (int)$row['c'];
    }
  }
}

// 2) Fund remaining (global): settings.total_fund - SUM(reports.budget_used)
//    Falls back to ₱40,000 if no settings/reports yet.
$fundRemaining = 40000.00; // fallback
$totalFund = null;

$trySettings = $conn->query("SELECT value FROM settings WHERE `key`='total_fund' LIMIT 1");
if ($trySettings && $trySettings->num_rows) {
  $totalFund = (float)$trySettings->fetch_assoc()['value'];
}
$used = 0.0;
$sumUsed = $conn->query("SELECT COALESCE(SUM(budget_used),0) AS used FROM reports");
if ($sumUsed) $used = (float)$sumUsed->fetch_assoc()['used'];
if ($totalFund !== null) $fundRemaining = max(0.0, $totalFund - $used);

$fundRemainingDisplay = number_format($fundRemaining, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Overview</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      display: flex; height: 100vh; font-family: Arial, sans-serif;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: white;
    }

    /* Sidebar (unified style across pages) */
    .sidebar {
      width: 250px;
      background: #2e4b4f;
      padding: 20px 0;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .sidebar img.logo {
      width: 120px;
      margin-bottom: 10px;
    }
    .sidebar .label {
      font-size: 13px;
      font-weight: bold;
      text-align: center;
      color: #dff2ff;
      text-shadow: 1px 1px 2px #000;
      margin-bottom: 25px;
      line-height: 1.3;
    }
    .sidebar a {
      color: #fff;
      text-decoration: none;
      padding: 10px 15px;
      margin: 6px 0;
      border-radius: 8px;
      width: 90%;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: bold;
      transition: background 0.3s;
    }
    .sidebar a:hover {
      background: rgba(255,255,255,0.15);
    }
    .sidebar a.active {
      background-color: #2ec8b5;
      color: white;
    }

    /* Main */
    .main { flex: 1; padding: 30px; overflow-y: auto; }

    /* Top bar */
    .top-bar {
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
    }
    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }
    .user-icon {
      width: 36px; height: 36px; border-radius: 50%; background: white; display: flex;
      align-items: center; justify-content: center; font-size: 18px; color: #333;
    }

    /* OVERVIEW title */
    .header {
      font-size: 55px; font-weight: bold; text-shadow: 2px 2px #000, 0 0 8px #fff; margin-bottom: 5px;
    }

    /* Fund Remaining badge */
    .fund-remaining {
      display: flex; justify-content: flex-end; margin: 12px 0 20px;
    }
    .fund-badge {
      display: inline-flex; align-items: center; gap: 12px;
      padding: 10px 18px; border-radius: 999px;
      background: rgba(0,0,0,0.28);
      border: 1px solid rgba(255,255,255,0.38);
      font-weight: 800; letter-spacing: 0.35px; backdrop-filter: blur(2px);
      font-size: 16px;
    }
    .fund-badge .label { opacity: 0.95; font-size: 13px; }
    .fund-badge .amount { font-size: 16px; }

    /* Summary cards */
    .summary {
      background: rgba(255, 255, 255, 0.3); margin-top: 10px; border-radius: 20px; padding: 30px;
    }
    .summary h2 {
      margin-bottom: 20px; text-align: center; font-size: 28px;
    }

    .status-grid { display: flex; justify-content: space-between; margin-bottom: 30px; }
    .status-box {
      flex: 1; margin: 0 10px; border-radius: 15px; padding: 20px 10px; height: 170px;
      display: flex; align-items: flex-start; justify-content: center; font-weight: bold; font-size: 20px; background: #eeeeee;
    }
    .approved { color: #00cc44; }
    .rejected { color: #cc0000; background: #b7cde3; }
    .pending  { color: #ff9900; }
    .completed{ color: #00cc44; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="/public/assets/icons/sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="admin_pov.php" class="active"> 📊 Dashboard</a>
    <a href="proposals.php">📁 Proposals</a>
    <a href="templates.php">📄 Document Templates</a>
    <a href="reports.php">📑 Reports</a>
  </div>

  <!-- Main Content -->
  <div class="main">

    <!-- Top Bar -->
    <div class="top-bar">
      <div class="header">OVERVIEW</div>
      <div class="icons">
        🔔
        <div class="user-icon">👤</div>
      </div>
    </div>

    <!-- FUND REMAINING -->
    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount">₱ <?= htmlspecialchars($fundRemainingDisplay, ENT_QUOTES) ?></span>
      </div>
    </div>

    <!-- Summary Section -->
    <div class="summary">
      <h2>Overview Proposals</h2>
      <div class="status-grid">
        <!-- keep your visual text; we only add data-count (hidden) -->
        <div class="status-box approved"  data-count="<?= (int)$statusCounts['Approved'] ?>">APPROVED</div>
        <div class="status-box rejected"  data-count="<?= (int)$statusCounts['Rejected'] ?>">REJECTED</div>
        <div class="status-box pending"   data-count="<?= (int)$statusCounts['Pending'] ?>">PENDING</div>
        <div class="status-box completed" data-count="<?= (int)$statusCounts['Completed'] ?>">COMPLETED</div>
      </div>
    </div>
  </div>
</body>
</html>
