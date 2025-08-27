<?php
// /views/user/proposals.php
session_start();
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
auth_required();

function peso($n) { return '₱' . number_format((float)$n, 0); }

// ---------- inputs ----------
$q = trim($_GET['q'] ?? '');

// ---------- fund remaining (global) ----------
$fundRemaining = 40000.0; // fallback
$totalFund = null;
$try = $conn->query("SELECT value FROM settings WHERE `key`='total_fund' LIMIT 1");
if ($try && $try->num_rows) $totalFund = (float)$try->fetch_assoc()['value'];
$used = 0.0;
$sum = $conn->query("SELECT COALESCE(SUM(budget_used),0) AS used FROM reports");
if ($sum) $used = (float)$sum->fetch_assoc()['used'];
if ($totalFund !== null) $fundRemaining = max(0.0, $totalFund - $used);
$fundRemainingDisplay = number_format($fundRemaining, 0);

// ---------- proposals for this user ----------
$userId = (int)($_SESSION['user_id'] ?? 0);
$where = "WHERE submitted_by = ?";
$params = [$userId];
$types  = 'i';

if ($q !== '') {
  $where .= " AND (title LIKE ? OR COALESCE(type,'') LIKE ? OR COALESCE(barangay, source, location, '') LIKE ? OR COALESCE(status,'') LIKE ?)";
  $like = "%{$q}%";
  array_push($params, $like, $like, $like, $like);
  $types .= 'ssss';
}

$sql = "
  SELECT
    id,
    title,
    COALESCE(type, 'Activity')    AS type,
    COALESCE(barangay, source, location, '') AS barangay,
    COALESCE(status, 'Pending')   AS status,
    COALESCE(budget, 0)           AS budget,
    COALESCE(submitted_at, created_at, NOW()) AS dt
  FROM proposals
  $where
  ORDER BY dt DESC, id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Proposals</title>
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
      display: flex; flex-direction: column; gap: 20px;
    }
    .sidebar img { width: 100px; align-self: center; }
    .nav-item {
      font-weight: bold; display: flex; align-items: center; gap: 10px;
      padding: 10px 15px; border-radius: 8px; text-decoration: none; color: white;
    }
    .nav-item:hover, .nav-item.active { background-color: rgba(255, 255, 255, 0.15); }

    .sub-links { margin-left: 30px; display: flex; flex-direction: column; gap: 4px; }
    .sub-links a { font-weight: bold; color: white; font-size: 14px; text-decoration: none; padding: 4px 0; }
    .sub-links a:hover { text-decoration: underline; }

    .main { flex: 1; display: flex; flex-direction: column; padding: 30px 40px 20px; }

    .top-bar { display: flex; justify-content: space-between; align-items: center; }
    .top-bar .title { font-size: 28px; font-weight: bold; text-transform: uppercase; }
    .right { display: flex; align-items: center; gap: 18px; }
    .icons { font-size: 20px; display: flex; gap: 15px; align-items: center; }

    .user-icon{
      width: 36px; height: 36px; border-radius: 50%;
      background: #fff; color: #333; font-size: 18px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.25);
    }

    .fund-remaining { display: flex; justify-content: flex-end; margin: 10px 0 12px; }
    .fund-badge {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 8px 14px; border-radius: 999px;
      background: rgba(0,0,0,0.25);
      border: 1px solid rgba(255,255,255,0.35);
      font-weight: 700; letter-spacing: 0.3px; backdrop-filter: blur(2px);
    }
    .fund-badge .label { opacity: 0.95; font-size: 12px; }
    .fund-badge .amount { font-size: 14px; }

    .content-box { background: rgba(0, 0, 0, 0.25); border-radius: 12px; padding: 20px; flex: 1; overflow-y: auto; }

    .search-container { display: flex; justify-content: flex-end; margin-bottom: 20px; }
    .search-bar { display: flex; align-items: center; background: rgba(255, 255, 255, 0.3); padding: 8px 15px; border-radius: 30px; width: 300px; }
    .search-bar input { border: none; background: transparent; color: white; font-size: 14px; flex: 1; outline: none; }
    .search-bar::before { content: '🔍'; margin-right: 8px; font-size: 16px; }
    .search-bar::after  { content: '⚙️'; margin-left: 8px; font-size: 16px; }

    .content-box::-webkit-scrollbar { width: 6px; }
    .content-box::-webkit-scrollbar-thumb { background: #ffffff88; border-radius: 10px; }

    /* proposal list items (kept subtle to match your style) */
    .p-item {
      background:#e5e5e5; color:#000; border-radius:10px;
      padding:12px 14px; margin-bottom:10px;
      display:flex; justify-content:space-between; align-items:center;
    }
    .p-title { font-weight:bold; }
    .p-meta  { font-size:13px; color:#333; margin-top:2px; }
    .p-right { text-align:right; }
    .p-budget{ font-weight:bold; }
    .p-date  { font-size:12px; color:#333; }
    @media (max-width: 640px) {
      .p-item { flex-direction:column; align-items:flex-start; gap:6px; }
      .p-right { text-align:left; }
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <img src="sklogo.png" alt="SK Logo">
    <a class="nav-item" href="index.php">📊 Dashboard</a>

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
    <div class="top-bar">
      <div class="title">Proposals</div>
      <div class="right">
        <div class="icons">🔔 <div class="user-icon">👤</div></div>
      </div>
    </div>

    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount"><?= htmlspecialchars(peso($fundRemainingDisplay), ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="content-box">
      <div class="search-container">
        <!-- same visuals, just make it a form so search works -->
        <form class="search-bar" method="get" action="">
          <input type="text" name="q" placeholder="Search" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
        </form>
      </div>

      <?php if (!$rows): ?>
        <div style="opacity:.9;">No proposals found.</div>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <div class="p-item">
            <div>
              <div class="p-title"><?= htmlspecialchars($r['title'], ENT_QUOTES) ?></div>
              <div class="p-meta">
                Type: <?= htmlspecialchars($r['type'], ENT_QUOTES) ?>
                &nbsp;•&nbsp; Barangay: <?= htmlspecialchars($r['barangay'], ENT_QUOTES) ?>
                &nbsp;•&nbsp; Status: <?= htmlspecialchars($r['status'], ENT_QUOTES) ?>
              </div>
            </div>
            <div class="p-right">
              <div class="p-budget"><?= peso($r['budget']) ?></div>
              <div class="p-date"><?= $r['dt'] ? date('M j, Y', strtotime($r['dt'])) : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- keep your demo checkbox (unchanged) -->
      <input type="checkbox" class="custom-checkbox" aria-label="Select box sa loob ng content">
    </div>
  </div>

</body>
</html>
