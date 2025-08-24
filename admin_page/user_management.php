<?php
session_start();

$users = [
  [
    'name' => 'Juan Dela Cruz',
    'from' => 'Barangay Lonoy',
    'position' => 'Secretary',
    'status' => 'Activated'
  ],
  [
    'name' => 'Allen Dave Nueva',
    'from' => 'Barangay Dagum',
    'position' => 'SK Chairperson',
    'status' => 'Activated'
  ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>User Management</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, sans-serif;
      display: flex;
      height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      color: #000;
    }

    /* === SIDEBAR (UPDATED) === */
    .sidebar {
      width: 250px;
      background: #2e4b4f;
      padding: 20px 0;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar .label {
      font-size: 13px; font-weight: bold; text-align: center; color: #dff2ff;
      text-shadow: 1px 1px 2px #000; margin-bottom: 25px; line-height: 1.3;
    }
    .sidebar a {
      color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0;
      border-radius: 8px; width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold;
    }
    .sidebar a.active { background-color: #2ec8b5; color: white; }

    /* === MAIN & CONTENT === */
    .main {
      flex: 1;
      padding: 30px;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }

    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;   /* align bell + user vertically */
      margin-bottom: 20px;
    }
    .icons {                 /* <- added to match other pages */
      display: flex;
      align-items: center;
      gap: 15px;
      font-size: 20px;
    }

    .search-bar {
      background: rgba(255, 255, 255, 0.2);
      border-radius: 30px;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      width: 400px;
      color: #fff;
    }
    .search-bar input {
      background: transparent;
      border: none;
      color: #fff;
      font-size: 16px;
      outline: none;
      width: 100%;
    }

    .user-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: #333;
    }

    .user-list-container {
      background: rgba(255, 255, 255, 0.1);
      padding: 20px;
      border-radius: 15px;
    }

    .user-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 15px;
    }
    .user-header-left { display: flex; align-items: center; gap: 10px; }
    .user-header-left input[type="checkbox"] { transform: scale(1.2); }
    .user-header-left button {
      padding: 6px 16px; border-radius: 10px; border: none;
      background: #e4e4e4; font-weight: bold; color: #000; cursor: pointer;
    }

    .user-row {
      background: #efefef; color: #000; border-radius: 10px;
      display: flex; align-items: center; justify-content: space-between;
      padding: 12px 16px; margin-bottom: 10px;
    }
    .user-left { display: flex; align-items: center; gap: 10px; }
    .user-left input[type="checkbox"] { transform: scale(1.2); }

    .user-info { display: flex; flex-direction: column; }
    .user-info .name { font-weight: bold; }
    .user-info .from { font-size: 13px; color: #444; }

    .user-position { font-weight: bold; font-size: 14px; }
    .status {
      background: #e3f9e6; color: #2ecc71; padding: 5px 12px; border-radius: 20px;
      font-size: 13px; font-weight: bold;
    }
  </style>
</head>
<body>

  <!-- ✅ Sidebar Updated -->
  <div class="sidebar">
    <img src="sklogo.png" class="logo" alt="SK Logo" />
    <div class="label"></div>
    <a href="index.php">📊 Dashboard</a>
    <a href="manage_proposals.php">📁 Manage Proposals</a>
    <a href="user_management.php" class="active">👥 User Management</a>
    <a href="document_template.php">📄 Document Templates</a>
    <a href="reports.php">📑 Reports</a>
  </div>

  <!-- Main -->
  <div class="main">
    <div class="top-bar">
      <div class="search-bar">🔍 <input type="text" placeholder="Search..." /> ⚙️</div>
      <div class="icons">🔔 <div class="user-icon">👤</div></div>
    </div>

    <div class="user-list-container">
      <div class="user-header">
        <div class="user-header-left">
          <input type="checkbox" />
          <button>+ New</button>
        </div>
      </div>

      <?php foreach ($users as $user): ?>
        <div class="user-row">
          <div class="user-left">
            <input type="checkbox" />
            <div class="user-info">
              <div class="name"><?= $user['name'] ?></div>
              <div class="from">From: <?= $user['from'] ?></div>
            </div>
          </div>
          <div class="user-position"><?= $user['position'] ?></div>
          <div class="status"><?= $user['status'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</body>
</html>
