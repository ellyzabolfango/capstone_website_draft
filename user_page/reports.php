<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      display: flex;
      font-family: Arial, sans-serif;
      height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      color: #fff;
    }

    .sidebar {
      width: 250px;
      background: #2e4b4f;
      padding: 20px 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      z-index: 10;
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
    }

    .sidebar a.active {
      background-color: #2ec8b5;
      color: white;
    }

    .main {
      flex: 1;
      padding: 30px;
      overflow-y: auto;
    }

    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .search-bar {
      background-color: rgba(255, 255, 255, 0.2);
      border-radius: 25px;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      width: 350px;
      color: #fff;
    }

    .search-bar input {
      background: transparent;
      border: none;
      color: #fff;
      outline: none;
      width: 100%;
    }

    .top-right {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 10px;
    }

    .icon-group {
      display: flex;
      gap: 15px;
      align-items: center;
    }

    .bell-icon {
      font-size: 24px;
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
      box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }

    .download-btn {
      background-color: rgba(255, 255, 255, 0.15);
      padding: 10px;
      border-radius: 12px;
      cursor: pointer;
      font-size: 20px;
      color: white;
      transition: background-color 0.3s;
      user-select: none;
      margin-top: 20px;
    }

    .download-btn:hover {
      background-color: #279fbb;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      border-radius: 12px;
      overflow: hidden;
      background-color: rgba(0, 0, 0, 0.2);
    }

    th, td {
      padding: 14px 16px;
      text-align: left;
      color: #fff;
    }

    th {
      font-weight: bold;
    }

    tr:nth-child(even) td {
      background-color: rgba(255, 255, 255, 0.1);
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <img src="sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="admin_pov.php"> 📊 Dashboard</a>
    <a href="proposals.php">📁 Proposals</a>
    <a href="templates.php">📄 Document Templates</a>
    <a href="reports.php" class="active">📑 Reports</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <div class="search-bar">
        🔍 <input type="text" placeholder="Search..." />
      </div>
      <div class="top-right">
        <div class="icon-group">
          <div class="bell-icon">🔔</div>
          <div class="user-icon">👤</div>
        </div>
        <div class="download-btn" title="Download">⬇️</div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Type</th>
          <th>Barangay</th>
          <th>Status</th>
          <th>Prepared by</th>
          <th>Budgetary Requirement</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>1.</td>
          <td>Leadership Training</td>
          <td>Activity</td>
          <td>Lonoy</td>
          <td>Completed</td>
          <td>Secretary</td>
          <td>₱15,000</td>
          <td>November 24, 2024</td>
        </tr>
      </tbody>
    </table>
  </div>
</body>
</html>
