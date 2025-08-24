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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Document Templates</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      display: flex; font-family: Arial, sans-serif; height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: #fff;
    }

    .sidebar {
      width: 250px; background: #2e4b4f; padding: 20px 0;
      display: flex; flex-direction: column; align-items: center;
    }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar .label {
      font-size: 13px; font-weight: bold; text-align: center; color: #dff2ff;
      text-shadow: 1px 1px 2px #000; margin-bottom: 25px; line-height: 1.3;
    }
    .sidebar a {
      color: #fff; text-decoration: none; padding: 10px 15px;
      margin: 6px 0; border-radius: 8px; width: 90%;
      display: flex; align-items: center; gap: 10px; font-weight: bold;
    }
    .sidebar a.active { background-color: #2ec8b5; }

    .main { flex: 1; padding: 30px; overflow-y: auto; }

    .top-bar {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 15px;
    }
    .search-bar {
      background: rgba(255, 255, 255, 0.2); border-radius: 30px;
      padding: 10px 20px; display: flex; align-items: center; gap: 10px;
      width: 400px; color: #fff;
    }
    .search-bar input {
      background: transparent; border: none; color: #fff; font-size: 16px;
      outline: none; width: 100%;
    }
    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }
    .user-icon {
      width: 36px; height: 36px; border-radius: 50%;
      background: white; display: flex; align-items: center; justify-content: center;
      font-size: 18px; color: #333;
    }

    /* Fund Remaining badge style (same as Overview) */
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

    /* New button */
    .new-btn {
      background: #dcdcdc; color: #000; border: none;
      padding: 8px 20px; border-radius: 20px; font-weight: bold;
      cursor: pointer; margin-bottom: 20px;
    }

    .file-cards {
      display: flex; flex-wrap: wrap; gap: 20px;
    }
    .file-card {
      background: #e5e5e5; color: #000; border-radius: 20px;
      min-width: 240px; max-width: 240px; padding: 16px 18px 12px;
      display: flex; flex-direction: column; justify-content: space-between;
      position: relative; font-weight: bold; height: 90px;
    }
    .file-card .title { display: flex; align-items: center; font-size: 14px; margin-right: 20px; }
    .file-card .title img { width: 20px; margin-right: 8px; }
    .file-card .menu {
      position: absolute; top: 12px; right: 12px; font-weight: bold;
      cursor: pointer; color: #333;
    }
    .file-card .time {
      font-size: 12px; color: #333; text-align: right;
      font-weight: normal; margin-top: 8px;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
     <a href="index.php"> 📊 Dashboard</a>
    <a href="manage_proposals.php">📁 Manage Proposals</a>
    <a href="user_management.php">👥 User Management</a>
    <a href="document_template.php" class="active">📄 Document Templates</a>
    <a href="reports.php">📑 Reports</a>
  </div>

  <!-- Main Content -->
  <div class="main">
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="search-bar"> 🔍 <input type="text" placeholder="Search..." /> ⚙️ </div>
      <div class="icons"> 🔔 <div class="user-icon">👤</div> </div>
    </div>

    <!-- FUND REMAINING (badge) -->
    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount">₱ <?php echo $fundRemaining; ?></span>
      </div>
    </div>

    <!-- New Button -->
    <button class="new-btn">+ New</button>

    <!-- File Cards -->
    <div class="file-cards">
      <div class="file-card">
        <div class="menu">⋮</div>
        <div class="title">
          <img src="https://upload.wikimedia.org/wikipedia/commons/8/87/PDF_file_icon.svg" alt="PDF Icon" />
          Liquidation Templates
        </div>
        <div class="time">1:15PM</div>
      </div>

      <div class="file-card">
        <div class="menu">⋮</div>
        <div class="title">
          <img src="word.png" alt="Word Icon" />
          Proposal Templates
        </div>
        <div class="time">12:09AM</div>
      </div>

      <div class="file-card">
        <div class="menu">⋮</div>
        <div class="title">
          <img src="word.png" alt="Word Icon" />
          ABIP Templates
        </div>
        <div class="time">3:23AM</div>
      </div>
    </div>
  </div>
</body>
</html>
