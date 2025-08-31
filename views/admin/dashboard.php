<?php
// views/admin/dashboard.php
session_start();

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
auth_required();
if (!is_admin()) { header("Location: " . PUBLIC_URL . "/index.php"); exit(); }

// ---------- Dynamic data ----------

// 0) Base URL for assets
$logoPath = BASE_URL . "/public/assets/icons/sklogo.png";

// 1) Proposal status counts
$statuses = ['Approved','Rejected','Pending','Completed'];
$statusCounts = array_fill_keys($statuses, 0);

$stmt = db()->prepare("SELECT status, COUNT(*) AS c FROM proposals GROUP BY status");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $k = $row['status'];
  if (isset($statusCounts[$k])) $statusCounts[$k] = (int)$row['c'];
}

// 2) PPA chart (simple mapping)
$programs  = db()->query("SELECT COUNT(*) AS c FROM proposals WHERE source='Program'")->fetch_assoc()['c'] ?? 0;
$projects  = db()->query("SELECT COUNT(*) AS c FROM proposals WHERE source='Project'")->fetch_assoc()['c'] ?? 0;
$activities= db()->query("SELECT COUNT(*) AS c FROM activities")->fetch_assoc()['c'] ?? 0;
$ppaDataForJs = [(int)$programs, (int)$projects, (int)$activities];

// 3) Fund remaining = settings.total_budget - SUM(proposals.budget of approved/completed)
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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>/* (keep your existing CSS exactly as you posted) */</style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <img src="<?= htmlspecialchars($logoPath) ?>" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/index.php" class="active"> 📊 Dashboard</a>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/manage_proposals.php">📁 Manage Proposals</a>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/user_management.php">👥 User Management</a>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/document_template.php">📄 Document Templates</a>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/reports.php">📑 Reports</a>
  </div>

  <!-- Main Content -->
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

      <div class="chart-area">
        <div class="chart-header">
          <span>PPA Chart</span>
          <select><option>This year</option></select>
        </div>
        <canvas id="ppaChart"></canvas>
      </div>
    </div>
  </div>

  <script>
    const ppaData = <?= json_encode($ppaDataForJs) ?>;
    const ctx = document.getElementById('ppaChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Programs', 'Projects', 'Activities'],
        datasets: [{
          label: 'PPAs',
          data: ppaData,
          borderColor: '#00ff00',
          backgroundColor: 'transparent',
          tension: 0.4,
          pointBackgroundColor: '#00ff00'
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }},
        scales: {
          y: { beginAtZero: true, ticks: { color: 'white' }, grid: { color: 'rgba(255,255,255,0.2)' } },
          x: { ticks: { color: 'white' }, grid: { color: 'rgba(255,255,255,0.1)' } }
        }
      }
    });
  </script>
</body>
</html>
