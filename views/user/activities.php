<?php
// /views/user/activities.php
session_start();
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../config/db.php';
auth_required();

function peso($n){ return '₱' . number_format((float)$n, 0); }

$TYPE = 'Activity';
$q    = trim($_GET['q'] ?? '');
$msg  = trim($_GET['msg'] ?? '');

// current user's location
$userId = (int)($_SESSION['user_id'] ?? 0);
$stmtU  = $conn->prepare("SELECT COALESCE(location,'') AS loc FROM users WHERE id=?");
$stmtU->bind_param('i', $userId);
$stmtU->execute();
$userLoc = $stmtU->get_result()->fetch_assoc()['loc'] ?? '';

// fund remaining (global)
$fund = 40000.0;
$set  = $conn->query("SELECT value FROM settings WHERE `key`='total_fund' LIMIT 1");
if ($set && $set->num_rows) $fund = (float)$set->fetch_assoc()['value'];
$used = 0.0;
$sum  = $conn->query("SELECT COALESCE(SUM(budget_used),0) AS u FROM reports");
if ($sum) $used = (float)$sum->fetch_assoc()['u'];
$fundRem = max(0.0, $fund - $used);

// list activities for same location
$where  = "WHERE UPPER(COALESCE(type,'$TYPE')) = UPPER(?) AND COALESCE(barangay, source, location, '') = ?";
$params = [$TYPE, $userLoc];
$types  = 'ss';
if ($q !== '') {
  $where .= " AND (title LIKE ? OR COALESCE(status,'') LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like;
  $types   .= 'ss';
}
$sql = "
  SELECT id, title,
         COALESCE(status,'Pending') AS status,
         COALESCE(budget,0)         AS budget,
         COALESCE(barangay, source, location, '') AS barangay,
         COALESCE(attachment_path,'') AS attachment_path,
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
  <title>Activity</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body { display: flex; height: 100vh; font-family: Arial, sans-serif;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: white; }

    .sidebar { width: 270px; background: linear-gradient(to bottom, #2e4f4f, #33676b);
      padding: 20px; display: flex; flex-direction: column; gap: 20px; }
    .sidebar img { width: 100px; align-self: center; }
    .nav-item { font-weight: bold; display: flex; align-items: center; gap: 10px;
      padding: 10px 15px; border-radius: 8px; text-decoration: none; color: white; }
    .nav-item:hover, .nav-item.active { background-color: rgba(255, 255, 255, 0.15); }
    .sub-links { margin-left: 30px; display: flex; flex-direction: column; gap: 4px; }
    .sub-links a { font-weight: bold; color: white; font-size: 14px; text-decoration: none; padding: 4px 0; }
    .sub-links a:hover { text-decoration: underline; }

    .main { flex: 1; display: flex; flex-direction: column; padding: 30px 40px 20px; }

    .top-bar { display: flex; justify-content: space-between; align-items: center; }
    .top-bar .title { font-size: 28px; font-weight: bold; text-transform: uppercase; }
    .top-bar .right { display: flex; align-items: center; gap: 18px; }

    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }
    .user-icon {
      width: 36px; height: 36px; border-radius: 50%;
      background: white; display: flex; align-items: center; justify-content: center;
      font-size: 18px; color: #333;
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
    .search-bar { display: flex; align-items: center; background: rgba(255, 255, 255, 0.3);
      padding: 8px 15px; border-radius: 30px; width: 300px; }
    .search-bar input { border: none; background: transparent; color: white; font-size: 14px; flex: 1; outline: none; }
    .search-bar::before { content: '🔍'; margin-right: 8px; font-size: 16px; }
    .search-bar::after  { content: '⚙️'; margin-left: 8px; font-size: 16px; }

    .upload-row { display: flex; align-items: center; gap: 12px; }
    .select-box { width: 20px; height: 20px; border: 2px solid #0a0a0a; background: transparent; border-radius: 2px; }
    .upload-wrap { position: relative; display: inline-block; }
    .upload-wrap input[type="file"] { display: none; }
    .upload-btn { display: inline-block; padding: 8px 16px; font-weight: 700; font-size: 12px; border-radius: 999px;
      background: #e7e7e7; color: #1f1f1f; border: 1px solid #cfcfcf; cursor: pointer; user-select: none;
      box-shadow: 0 1px 0 rgba(0,0,0,0.15); letter-spacing: 0.5px; }
    .upload-btn:hover { filter: brightness(0.96); }
    .upload-btn:active { transform: translateY(1px); }

    /* list items (subtle cards, same vibe as your other pages) */
    .a-item { background:#e5e5e5; color:#000; border-radius:10px; padding:12px 14px; margin:12px 0;
      display:flex; justify-content:space-between; align-items:center; }
    .a-left .t { font-weight:bold; }
    .a-left .m { font-size:13px; color:#333; margin-top:2px; }
    .a-right { text-align:right; }
    .a-right .b { font-weight:bold; }
    .a-right .d { font-size:12px; color:#333; }

    .content-box::-webkit-scrollbar { width: 6px; }
    .content-box::-webkit-scrollbar-thumb { background: #ffffff88; border-radius: 10px; }

    @media (max-width: 768px) {
      .main { padding: 20px; }
      .search-bar { width: 100%; }
      .fund-badge { padding: 6px 12px; }
      .a-item { flex-direction:column; align-items:flex-start; gap:6px; }
      .a-right { text-align:left; }
    }
    .msg { margin:8px 0 12px; }
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
    <!-- Top bar -->
    <div class="top-bar">
      <div class="title"><?= htmlspecialchars($TYPE, ENT_QUOTES) ?></div>
      <div class="right">
        <div class="icons">🔔 <div class="user-icon">👤</div></div>
      </div>
    </div>

    <!-- FUND REMAINING -->
    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount"><?= htmlspecialchars(peso($fundRem), ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="content-box">
      <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div><?php endif; ?>

      <div class="search-container">
        <form class="search-bar" method="get" action="">
          <input type="text" name="q" placeholder="Search" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
        </form>
      </div>

      <!-- Upload row -->
      <div class="upload-row">
        <div class="select-box" aria-hidden="true"></div>
        <form class="upload-wrap" action="upload_proposal.php" method="post" enctype="multipart/form-data">
          <?= csrf_field(); ?>
          <input type="hidden" name="type" value="<?= htmlspecialchars($TYPE, ENT_QUOTES) ?>">
          <input id="fileUpload" type="file" name="proposal_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
          <label for="fileUpload" class="upload-btn">UPLOAD</label>
        </form>
      </div>

      <!-- Activity list -->
      <?php if (!$userLoc): ?>
        <div style="opacity:.9; margin-top:14px;">No location set on your account. Ask an admin to add your location to see activities.</div>
      <?php elseif (!$rows): ?>
        <div style="opacity:.9; margin-top:14px;">No activities found for <?= htmlspecialchars($userLoc, ENT_QUOTES) ?>.</div>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $title = htmlspecialchars($r['title'], ENT_QUOTES);
          $open  = $r['attachment_path'] ? '../../' . ltrim($r['attachment_path'], '/') : '';
        ?>
          <div class="a-item">
            <div class="a-left">
              <div class="t">
                <?php if ($open): ?>
                  <a href="<?= htmlspecialchars($open, ENT_QUOTES) ?>" target="_blank" style="text-decoration:none; color:inherit;"><?= $title ?></a>
                <?php else: ?>
                  <?= $title ?>
                <?php endif; ?>
              </div>
              <div class="m">
                Barangay: <?= htmlspecialchars($r['barangay'], ENT_QUOTES) ?>
                &nbsp;•&nbsp; Status: <?= htmlspecialchars($r['status'], ENT_QUOTES) ?>
              </div>
            </div>
            <div class="a-right">
              <div class="b"><?= peso($r['budget']) ?></div>
              <div class="d"><?= $r['dt'] ? date('M j, Y', strtotime($r['dt'])) : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const up = document.getElementById('fileUpload');
    if (up) up.addEventListener('change', () => { if (up.files.length) up.closest('form').submit(); });
  </script>
</body>
</html>
