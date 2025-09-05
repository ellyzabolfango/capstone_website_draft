<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

auth_required();

function peso(float $n): string { return '₱' . number_format($n, 0); }

// ---------- Inputs ----------
$q = trim((string)($_GET['q'] ?? ''));

$totalBudget = 4000;
$fundRemaining = 4000;
$fundRemainingDisplay = number_format($fundRemaining, 0);


// ---------- Proposals for current user (READ-ONLY VIEW) ----------
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('barangay: ' . LOGIN_URL); exit(); }

$where  = "WHERE p.created_by = ?";
$params = [$userId];
$types  = 'i';

if ($q !== '') {
  // match title / category(type) / status
  $where .= " AND (p.title LIKE ? OR COALESCE(p.category,'') LIKE ? OR COALESCE(p.status,'') LIKE ?)";
  $like = "%{$q}%";
  array_push($params, $like, $like, $like);
  $types .= 'sss';
}

$sql = "
  SELECT
    p.id,
    p.title,
    COALESCE(p.category, '')       AS category,   -- Type: Program/Project/Activity
    COALESCE(p.status, 'Pending')  AS status,
    COALESCE(p.budget, 0)          AS budget,
    COALESCE(p.submitted_at, p.updated_at) AS dt
  FROM proposals p
  $where
  ORDER BY dt DESC, p.id DESC
";
$stmt = db()->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$username = (string)($_SESSION['username'] ?? 'User');
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
      display: flex; height: 100vh; font-family: Arial, sans-serif;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: #fff;
    }
    .sidebar { width: 270px; background: linear-gradient(to bottom, #2e4f4f, #33676b); padding: 20px; display: flex; flex-direction: column; gap: 20px; }
    .sidebar img { width: 100px; align-self: center; }
    .nav-item { font-weight: bold; display: flex; align-items: center; gap: 10px; padding: 10px 15px; border-radius: 8px; text-decoration: none; color: #fff; }
    .nav-item:hover, .nav-item.active { background-color: rgba(255,255,255,0.15); }
    .sub-links { margin-left: 30px; display: flex; flex-direction: column; gap: 4px; }
    .sub-links a { font-weight: bold; color: #fff; font-size: 14px; text-decoration: none; padding: 4px 0; }
    .sub-links a:hover { text-decoration: underline; }
    .main { flex: 1; display: flex; flex-direction: column; padding: 30px 40px 20px; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; }
    .top-bar .title { font-size: 28px; font-weight: bold; text-transform: uppercase; }
    .right { display: flex; align-items: center; gap: 18px; }
    .icons { font-size: 20px; display: flex; gap: 15px; align-items: center; }
    .user-icon { width: 36px; height: 36px; border-radius: 50%; background: #fff; color: #333; font-size: 18px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.25); }
    .fund-remaining { display: flex; justify-content: flex-end; margin: 10px 0 12px; }
    .fund-badge { display: inline-flex; align-items: center; gap: 10px; padding: 8px 14px; border-radius: 999px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.35); font-weight: 700; letter-spacing: 0.3px; backdrop-filter: blur(2px); }
    .fund-badge .label { opacity: .95; font-size: 12px; }
    .fund-badge .amount { font-size: 14px; }
    .content-box { background: rgba(0,0,0,0.25); border-radius: 12px; padding: 20px; flex: 1; overflow-y: auto; }
    .search-container { display: flex; justify-content: flex-end; margin-bottom: 20px; }
    .search-bar { display: flex; align-items: center; background: rgba(255,255,255,0.3); padding: 8px 15px; border-radius: 30px; width: 300px; }
    .search-bar input { border: none; background: transparent; color: #fff; font-size: 14px; flex: 1; outline: none; }
    .search-bar::before { content: '🔍'; margin-right: 8px; font-size: 16px; }
    .search-bar::after  { content: '⚙️'; margin-left: 8px; font-size: 16px; }
    .content-box::-webkit-scrollbar { width: 6px; }
    .content-box::-webkit-scrollbar-thumb { background: #ffffff88; border-radius: 10px; }
    .p-item { background:#e5e5e5; color:#000; border-radius:10px; padding:12px 14px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; }
    .p-title { font-weight:bold; }
    .p-meta  { font-size:13px; color:#333; margin-top:2px; }
    .p-right { text-align:right; }
    .p-budget{ font-weight:bold; }
    .p-date  { font-size:12px; color:#333; }
    @media (max-width: 640px) { .p-item { flex-direction:column; align-items:flex-start; gap:6px; } .p-right { text-align:left; } }
  </style>
</head>
<body>

  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/icons/sklogo.png" alt="SK Logo">
    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">📊 Dashboard</a>

    <div class="nav-item active">🗂️ Proposals ▾</div>
    <div class="sub-links">
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/programs.php">Programs</a>
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/projects.php">Projects</a>
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/activities.php">Activities</a>
    </div>

    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/templates.php">📄 Document Templates</a>
    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/reports.php">📑 Reports</a>
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
        <span class="amount">₱ <?= htmlspecialchars($fundRemainingDisplay, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

    <div class="content-box">
      <div class="search-container">
        <form class="search-bar" method="get" action="">
          <input type="text" name="q" placeholder="Search title, type, or status…" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
        </form>
      </div>

      <?php if (!$rows): ?>
        <div style="opacity:.9;">No proposals yet. Create one from your Programs / Projects / Activities / Annual Budget page.</div>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <div class="p-item">
            <div>
              <div class="p-title"><?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="p-meta">
                Type: <?= htmlspecialchars($r['category'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                &nbsp;•&nbsp; Status: <?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <div class="p-right">
              <div class="p-budget"><?= peso((float)$r['budget']) ?></div>
              <div class="p-date"><?= $r['dt'] ? date('M j, Y', strtotime((string)$r['dt'])) : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
