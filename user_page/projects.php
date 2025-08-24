<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}

// (Optional) Dynamic fund value example:
// $fundRemaining = number_format(40000, 2);
$fundRemaining = "40,000";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Project</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      display: flex;
      height: 100vh;
      font-family: Arial, sans-serif;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      color: white;
    }

    .sidebar {
      width: 270px;
      background: linear-gradient(to bottom, #2e4f4f, #33676b);
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .sidebar img { width: 100px; align-self: center; }

    .nav-item {
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 15px;
      border-radius: 8px;
      text-decoration: none;
      color: white;
    }
    .nav-item:hover, .nav-item.active { background-color: rgba(255,255,255,0.15); }

    .sub-links {
      margin-left: 30px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .sub-links a {
      font-weight: bold;
      color: white;
      font-size: 14px;
      text-decoration: none;
      padding: 4px 0;
    }
    .sub-links a:hover { text-decoration: underline; }

    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      padding: 30px 40px 20px;
    }

    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .top-bar .title {
      font-size: 28px;
      font-weight: bold;
      text-transform: uppercase;
    }
    .top-bar .right {
      display: flex;
      align-items: center;
      gap: 18px;
    }
    .icons {
      font-size: 20px;
      display: flex;
      align-items: center;
      gap: 15px;
      cursor: pointer;
    }
    /* User icon (same look as Templates page) */
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

    /* Fund Remaining badge */
    .fund-remaining {
      display: flex;
      justify-content: flex-end;
      margin: 10px 0 12px;
    }
    .fund-badge {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(0,0,0,0.25);
      border: 1px solid rgba(255,255,255,0.35);
      font-weight: 700;
      letter-spacing: 0.3px;
      backdrop-filter: blur(2px);
    }
    .fund-badge .label { opacity: 0.95; font-size: 12px; }
    .fund-badge .amount { font-size: 14px; }

    .content-box {
      background: rgba(0,0,0,0.25);
      border-radius: 12px;
      padding: 20px;
      flex: 1;
      overflow-y: auto;
    }

    .search-container {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 20px;
    }
    .search-bar {
      display: flex;
      align-items: center;
      background: rgba(255,255,255,0.3);
      padding: 8px 15px;
      border-radius: 30px;
      width: 300px;
    }
    .search-bar input {
      border: none;
      background: transparent;
      color: white;
      font-size: 14px;
      flex: 1;
      outline: none;
    }
    .search-bar::before { content: '🔍'; margin-right: 8px; font-size: 16px; }
    .search-bar::after { content: '⚙️'; margin-left: 8px; font-size: 16px; }

    /* Upload row */
    .upload-row { display: flex; align-items: center; gap: 12px; }
    .select-box {
      width: 20px; height: 20px;
      border: 2px solid #0a0a0a;
      background: transparent; border-radius: 2px;
    }
    .upload-wrap { position: relative; display: inline-block; }
    .upload-wrap input[type="file"] { display: none; }
    .upload-btn {
      display: inline-block; padding: 8px 16px;
      font-weight: 700; font-size: 12px; border-radius: 999px; /* pill */
      background: #e7e7e7; color: #1f1f1f; border: 1px solid #cfcfcf;
      cursor: pointer; user-select: none;
      box-shadow: 0 1px 0 rgba(0,0,0,0.15); letter-spacing: 0.5px;
    }
    .upload-btn:hover { filter: brightness(0.96); }
    .upload-btn:active { transform: translateY(1px); }

    /* Scrollbar */
    .content-box::-webkit-scrollbar { width: 6px; }
    .content-box::-webkit-scrollbar-thumb { background: #ffffff88; border-radius: 10px; }

    /* Small screens */
    @media (max-width: 768px) {
      .main { padding: 20px; }
      .search-bar { width: 100%; }
      .fund-badge { padding: 6px 12px; }
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <img src="sklogo.png" alt="SK Logo">
    <a class="nav-item" href="admin_pov.php">📊 Dashboard</a>
    <div class="nav-item active">🗂️ Proposals ▾</div>
    <div class="sub-links">
      <a href="programs.php">Programs</a>
      <a href="projects.php">Projects</a>
      <a href="activities.php">Activities</a>
    </div>
    <a class="nav-item" href="templates.php">📄 Templates</a>
    <a class="nav-item" href="reports.php">📑 Reports</a>
  </div>

  <div class="main">
    <!-- Top Bar (icons only on the right) -->
    <div class="top-bar">
      <div class="title">Project</div>
      <div class="right">
        <div class="icons">
          🔔
          <div class="user-icon">👤</div>
        </div>
      </div>
    </div>

    <!-- FUND REMAINING: below the top bar, right-aligned -->
    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount">₱ <?php echo $fundRemaining; ?></span>
      </div>
    </div>

    <div class="content-box">
      <div class="search-container">
        <div class="search-bar">
          <input type="text" placeholder="Search">
        </div>
      </div>

      <!-- Upload row (top-left, like in the picture) -->
      <div class="upload-row">
        <div class="select-box" aria-hidden="true"></div>

        <form class="upload-wrap" action="upload_project.php" method="post" enctype="multipart/form-data">
          <input id="fileUploadProject" type="file" name="project_file">
          <label for="fileUploadProject" class="upload-btn">UPLOAD</label>
        </form>
      </div>

      <!-- Your Project list/content goes here -->

    </div>
  </div>
</body>
</html>
