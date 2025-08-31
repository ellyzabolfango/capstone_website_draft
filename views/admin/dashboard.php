<?php
// views/admin/dashboard.php
session_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php'; // loads DB, CSRF, AUTH, constants

auth_required();
if (!is_admin()) {
  header("Location: " . PUBLIC_URL . "/index.php");
  exit();
}

// ---------- Dynamic data ----------

// 1) Proposal status counts
$statuses = ['Approved', 'Rejected', 'Pending', 'Completed'];
$statusCounts = array_fill_keys($statuses, 0);

$stmt = db()->prepare("SELECT status, COUNT(*) AS c FROM proposals GROUP BY status");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $k = $row['status'];
  if (isset($statusCounts[$k]))
    $statusCounts[$k] = (int) $row['c'];
}

// 2) PPA chart (simple mapping)
$programs = db()->query("SELECT COUNT(*) AS c FROM proposals WHERE source='Program'")->fetch_assoc()['c'] ?? 0;
$projects = db()->query("SELECT COUNT(*) AS c FROM proposals WHERE source='Project'")->fetch_assoc()['c'] ?? 0;
$activities = db()->query("SELECT COUNT(*) AS c FROM activities")->fetch_assoc()['c'] ?? 0;
$ppaDataForJs = [(int) $programs, (int) $projects, (int) $activities];

// 3) Fund remaining = settings.total_budget - SUM(proposals.budget of approved/completed)
$settingsRow = db()->query("SELECT total_budget FROM settings WHERE id=1")->fetch_assoc();
if ($settingsRow) {
  $totalBudget = (float) $settingsRow['total_budget'];
  $usedRow = db()->query("SELECT COALESCE(SUM(budget),0) AS used FROM proposals WHERE status IN ('Approved','Completed')")->fetch_assoc();
  $used = (float) ($usedRow['used'] ?? 0);
  $fundRemaining = max(0.0, $totalBudget - $used);
}
$fundRemainingDisplay = number_format($fundRemaining, 0);
$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Overview</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      display: flex;
      height: 100vh;
      font-family: Arial, sans-serif;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      color: white;
    }

    /* Sidebar */
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
      transition: background .3s;
    }

    .sidebar a:hover {
      background: rgba(255, 255, 255, .15);
    }

    .sidebar a.active {
      background: #2ec8b5;
      color: #fff;
    }

    /* Main */
    .main {
      flex: 1;
      padding: 30px;
      overflow-y: auto;
    }

    /* Top bar (with user menu) */
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .header {
      font-size: 55px;
      font-weight: bold;
      text-shadow: 2px 2px #000, 0 0 8px #fff;
    }

    .user-menu {
      position: relative;
    }

    .user-btn {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      user-select: none;
    }

    .user-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: #333;
    }

    .caret {
      font-size: 14px;
      opacity: .9;
    }

    .menu {
      position: absolute;
      right: 0;
      top: 120%;
      background: #ffffff;
      color: #222;
      min-width: 200px;
      border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
      overflow: hidden;
      display: none;
      z-index: 100;
    }

    .menu.show {
      display: block;
    }

    .menu-header {
      padding: 12px 14px;
      font-weight: bold;
      background: #f2f5f8;
    }

    .menu-item a,
    .menu-item button {
      display: block;
      width: 100%;
      text-align: left;
      padding: 12px 14px;
      color: #222;
      text-decoration: none;
      border: none;
      background: none;
      cursor: pointer;
    }

    .menu-item a:hover,
    .menu-item button:hover {
      background: #eef3f9;
    }

    /* Fund badge */
    .fund-remaining {
      display: flex;
      justify-content: flex-end;
      margin: 12px 0 20px;
    }

    .fund-badge {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      padding: 10px 18px;
      border-radius: 999px;
      background: rgba(0, 0, 0, .28);
      border: 1px solid rgba(255, 255, 255, .38);
      font-weight: 800;
      letter-spacing: .35px;
      backdrop-filter: blur(2px);
      font-size: 16px;
    }

    .fund-badge .label {
      opacity: .95;
      font-size: 13px;
    }

    /* Summary */
    .summary {
      background: rgba(255, 255, 255, 0.3);
      margin-top: 10px;
      border-radius: 20px;
      padding: 18px 20px;
    }

    .summary h2 {
      margin-bottom: 12px;
      text-align: left;
      font-size: 26px;
      font-weight: 700;
    }

    .status-grid {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 14px;
    }

    /* 4 boxes (unchanged) */
    .status-box {
      flex: 1;
      border-radius: 14px;
      padding: 10px 8px 8px;
      height: 120px;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      font-weight: 800;
      font-size: 18px;
      background: #eeeeee;
    }

    .approved {
      color: #00cc44;
    }

    .rejected {
      color: #cc0000;
      background: #b7cde3;
    }

    .pending {
      color: #ff9900;
    }

    .completed {
      color: #00cc44;
    }

    /* Chart area */
    .chart-area {
      background: rgba(255, 255, 255, 0.2);
      padding: 10px;
      border-radius: 15px;
    }

    .chart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
      font-weight: bold;
      color: #fff;
    }

    .chart-header select {
      border-radius: 6px;
      padding: 2px 6px;
      border: 1px solid #ccc;
      font-size: 12px;
    }

    canvas {
      width: 100%;
      max-height: 220px;
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL) ?>/assets/icons/sklogo.png" alt="Logo" class="logo" />
    <div class="label"></div>
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/index.php" class="active"> 📊 Dashboard</a>
    <a href="<?= htmlspecialchars(ADMIN_URL) ?>/manage_proposals.php">📁 Manage Proposals</a>
    <a href="<?= htmlspecialchars(ADMIN_URL) ?>/user_management.php">👥 User Management</a>
    <a href="<?= htmlspecialchars(ADMIN_URL) ?>/document_template.php">📄 Document Templates</a>
    <a href="<?= htmlspecialchars(ADMIN_URL) ?>/reports.php">📑 Reports</a>
  </div>

  <!-- Main Content -->
  <div class="main">
    <div class="top-bar">
      <div class="header">ADMIN • OVERVIEW</div>

      <!-- User menu -->
      <div class="user-menu" id="userMenu">
        <div class="user-btn" id="userBtn" aria-haspopup="true" aria-expanded="false">
          🔔
          <div class="user-icon">👤</div>
          <span class="caret">▾</span>
        </div>
        <div class="menu" id="menu">
          <div class="menu-header">@<?= htmlspecialchars($username, ENT_QUOTES) ?></div>
          <!-- <div class="menu-item">
            <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/profile.php">Profile</a>
          </div>
          <div class="menu-item">
            <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/settings.php">Settings</a>
          </div> -->
          <div class="menu-item">
            <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/logout.php">Logout</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Fund Remaining -->
    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount">₱ <?= htmlspecialchars($fundRemainingDisplay, ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="summary">
      <h2>Overview Proposals</h2>
      <div class="status-grid">
        <div class="status-box approved" data-count="<?= (int) $statusCounts['Approved'] ?>">APPROVED</div>
        <div class="status-box rejected" data-count="<?= (int) $statusCounts['Rejected'] ?>">REJECTED</div>
        <div class="status-box pending" data-count="<?= (int) $statusCounts['Pending'] ?>">PENDING</div>
        <div class="status-box completed" data-count="<?= (int) $statusCounts['Completed'] ?>">COMPLETED</div>
      </div>

      <div class="chart-area">
        <div class="chart-header">
          <span>PPA Chart</span>
          <select>
            <option>This year</option>
          </select>
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
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { color: 'white' }, grid: { color: 'rgba(255,255,255,0.2)' } },
          x: { ticks: { color: 'white' }, grid: { color: 'rgba(255,255,255,0.1)' } }
        }
      }
    });

    // Simple dropdown toggle + click-outside to close
    (function () {
      const btn = document.getElementById('userBtn');
      const menu = document.getElementById('menu');

      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const open = menu.classList.toggle('show');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });

      document.addEventListener('click', function (e) {
        if (!menu.classList.contains('show')) return;
        // Close if clicking outside menu or btn
        if (!menu.contains(e.target) && !btn.contains(e.target)) {
          menu.classList.remove('show');
          btn.setAttribute('aria-expanded', 'false');
        }
      });

      // Close on ESC
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && menu.classList.contains('show')) {
          menu.classList.remove('show');
          btn.setAttribute('aria-expanded', 'false');
        }
      });
    })();
  </script>
</body>

</html>