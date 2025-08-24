<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

// Optional: make dynamic from DB later
$fundRemaining = "40,000";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Overview</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      display: flex; height: 100vh; font-family: Arial, sans-serif;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: white;
    }

    /* Sidebar */
    .sidebar { width: 250px; background: #2e4b4f; padding: 20px 0; display: flex; flex-direction: column; align-items: center; }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar .label { font-size: 13px; font-weight: bold; text-align: center; color: #dff2ff; text-shadow: 1px 1px 2px #000; margin-bottom: 25px; line-height: 1.3; }
    .sidebar a { color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0; border-radius: 8px; width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold; transition: background 0.3s; }
    .sidebar a:hover { background: rgba(255,255,255,0.15); }
    .sidebar a.active { background-color: #2ec8b5; color: white; }

    /* Main */
    .main { flex: 1; padding: 30px; overflow-y: auto; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }
    .user-icon { width: 36px; height: 36px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #333; }

    .header { font-size: 55px; font-weight: bold; text-shadow: 2px 2px #000, 0 0 8px #fff; margin-bottom: 5px; }

    /* Fund Remaining badge */
    .fund-remaining { display: flex; justify-content: flex-end; margin: 12px 0 20px; }
    .fund-badge {
      display: inline-flex; align-items: center; gap: 12px; padding: 10px 18px; border-radius: 999px;
      background: rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.38);
      font-weight: 800; letter-spacing: 0.35px; backdrop-filter: blur(2px); font-size: 16px;
    }
    .fund-badge .label { opacity: 0.95; font-size: 13px; }
    .fund-badge .amount { font-size: 16px; }

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

    .status-grid { display: flex; justify-content: space-between; gap: 14px; margin-bottom: 14px; }

    /* 4 boxes (unchanged) */
    .status-box {
      flex: 1; border-radius: 14px; padding: 10px 8px 8px; height: 120px;
      display: flex; align-items: flex-start; justify-content: center;
      font-weight: 800; font-size: 18px; background: #eeeeee;
    }
    .approved { color: #00cc44; }
    .rejected { color: #cc0000; background: #b7cde3; }
    .pending  { color: #ff9900; }
    .completed{ color: #00cc44; }

    /* Chart area */
    .chart-area { background: rgba(255,255,255,0.2); padding: 10px; border-radius: 15px; }
    .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-weight: bold; color: #fff; }
    .chart-header select { border-radius: 6px; padding: 2px 6px; border: 1px solid #ccc; font-size: 12px; }
    canvas { width: 100%; max-height: 220px; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="index.php" class="active"> 📊 Dashboard</a>
    <a href="manage_proposals.php">📁 Manage Proposals</a>
    <a href="user_management.php">👥 User Management</a>
    <a href="document_template.php">📄 Document Templates</a>
    <a href="reports.php">📑 Reports</a>
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
        <span class="amount">₱ <?php echo $fundRemaining; ?></span>
      </div>
    </div>

    <div class="summary">
      <h2>Overview Proposals</h2>

      <div class="status-grid">
        <div class="status-box approved">APPROVED</div>
        <div class="status-box rejected">REJECTED</div>
        <div class="status-box pending">PENDING</div>
        <div class="status-box completed">COMPLETED</div>
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
    const ctx = document.getElementById('ppaChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Programs', 'Projects', 'Activities'],
        datasets: [{
          label: 'PPAs',
          data: [18, 26, 21],
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
