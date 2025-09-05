<?php
// /views/user/programs.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php'; // db(), auth_*, constants

auth_required();

$TYPE = 'Annual Budget';
function peso(float $n){ return '₱' . number_format($n, 0); }

// ---------- inputs ----------
$q   = trim((string)($_GET['q'] ?? ''));
$msg = trim((string)($_GET['msg'] ?? ''));

// ---------- current user + location ----------
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: ' . LOGIN_URL); exit(); }

$stmtU = db()->prepare("SELECT COALESCE(location,'') AS loc FROM users WHERE id=?");
$stmtU->bind_param('i', $userId);
$stmtU->execute();
$userLoc = (string)($stmtU->get_result()->fetch_assoc()['loc'] ?? '');

// ---------- fund remaining ----------
$fundRemaining = 0.0;
$settingsRow   = db()->query("SELECT total_budget FROM settings WHERE id = 1")->fetch_assoc();
if ($settingsRow) {
  $totalBudget = (float)$settingsRow['total_budget'];
  $usedRow = db()->query("
    SELECT COALESCE(SUM(budget),0) AS used
    FROM proposals
    WHERE status IN ('Approved','Completed')
  ")->fetch_assoc();
  $used = (float)($usedRow['used'] ?? 0);
  $fundRemaining = max(0.0, $totalBudget - $used);
}
$fundRemDisplay = peso($fundRemaining);

// ---------- list programs ----------
$params = [$TYPE, $userLoc];
$types  = 'ss';
$where  = "WHERE COALESCE(p.source,'') = ? AND COALESCE(u.location,'') = ?";

if ($q !== '') {
  $where .= " AND (p.title LIKE ? OR COALESCE(p.status,'') LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like;
  $types   .= 'ss';
}

$sql = "
  SELECT
    p.id,
    p.title,
    COALESCE(p.status,'Pending')    AS status,
    COALESCE(p.budget,0)            AS budget,
    COALESCE(p.ppa_ref,'')          AS ppa_ref,
    COALESCE(p.attachment_path,'')  AS attachment_path,
    COALESCE(p.submitted_at, p.updated_at, NOW()) AS dt
  FROM proposals p
  LEFT JOIN users u ON u.id = p.submitted_by
  $where
  ORDER BY dt DESC, p.id DESC
";
$stmt = db()->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($TYPE, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { display: flex; height: 100vh; font-family: Arial, sans-serif;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: white; }
    .sidebar { width: 270px; background: linear-gradient(to bottom, #2e4f4f, #33676b); padding: 20px; display:flex; flex-direction:column; gap:20px; }
    .sidebar img { width: 100px; align-self: center; }
    .nav-item { font-weight:bold; display:flex; align-items:center; gap:10px; padding:10px 15px; border-radius:8px; text-decoration:none; color:white; }
    .nav-item:hover, .nav-item.active { background-color: rgba(255,255,255,0.15); }
    .sub-links { margin-left:30px; display:flex; flex-direction:column; gap:4px; }
    .sub-links a { font-weight:bold; color:white; font-size:14px; text-decoration:none; padding:4px 0; }
    .sub-links a:hover { text-decoration: underline; }
    .main { flex:1; display:flex; flex-direction:column; padding:30px 40px 20px; }
    .top-bar { display:flex; justify-content:space-between; align-items:center; }
    .top-bar .title { font-size:28px; font-weight:bold; text-transform:uppercase; }
    .right { display:flex; align-items:center; gap:18px; }
    .icons { font-size:20px; display:flex; align-items:center; gap:15px; cursor:pointer; }
    .user-icon { width:36px; height:36px; border-radius:50%; background:white; display:flex; align-items:center; justify-content:center; font-size:18px; color:#333; }
    .fund-remaining { display:flex; justify-content:flex-end; margin:10px 0 12px; }
    .fund-badge { display:inline-flex; align-items:center; gap:10px; padding:8px 14px; border-radius:999px; background: rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.35); font-weight:700; letter-spacing:.3px; backdrop-filter: blur(2px); }
    .fund-badge .label { opacity:.95; font-size:12px; } .fund-badge .amount { font-size:14px; }

    .content-box { background: rgba(0,0,0,0.25); border-radius:12px; padding:20px; flex:1; overflow-y:auto; }

    /* Search sa itaas, Upload sa ibaba */
    .search-container { display:flex; justify-content:flex-end; margin-bottom:12px; }
    .search-bar { display:flex; align-items:center; background:rgba(255,255,255,0.3); padding:8px 15px; border-radius:30px; width:300px; }
    .search-bar input { border:none; background:transparent; color:white; font-size:14px; flex:1; outline:none; }
    .search-bar::before { content:'🔍'; margin-right:8px; font-size:16px; } .search-bar::after { content:'⚙️'; margin-left:8px; font-size:16px; }

    .toolbar { display:flex; align-items:center; gap:12px; margin:16px 0 14px; }
    .chk { width:18px; height:18px; border-radius:4px; border:2px solid #d6d6d6; background:transparent; display:inline-block; }

    .btn-upload {
      background:#e9edf1; color:#111; font-weight:700; font-size:12px; border:none;
      padding:6px 14px; border-radius:999px; cursor:pointer; box-shadow: inset 0 -1px 0 rgba(0,0,0,.15);
    }
    .btn-upload:active { transform: translateY(1px); }

    .p-item { background:#e5e5e5; color:#000; border-radius:10px; padding:12px 14px; margin:12px 0; display:flex; justify-content:space-between; align-items:center; }
    .p-left .t { font-weight:bold; }
    .p-left .m { font-size:13px; color:#333; margin-top:2px; }
    .p-right { text-align:right; }
    .p-right .b { font-weight:bold; }
    .p-right .d { font-size:12px; color:#333; }

    .content-box::-webkit-scrollbar { width:6px; } .content-box::-webkit-scrollbar-thumb{ background:#ffffff88; border-radius:10px; }
    @media (max-width:768px){ .main{padding:20px;} .search-bar{width:100%;} .p-item{flex-direction:column; align-items:flex-start; gap:6px;} .p-right{text-align:left;} }
    .msg { margin:8px 0 12px; }

    /* ===== MODAL SIZE & LOOK (centered panel) ===== */
    .overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.72);
      display: none; z-index: 50;
    }
    .modal {
      position: fixed;
      left: 50%; top: 50%;
      transform: translate(-50%, -50%);
      width: 60vw; height: 60vh;
      max-width: 980px;
      background: #0c1330;
      border: 3px solid #fbfcfcff;
      box-shadow: 0 12px 40px rgba(0,0,0,.5),
                  0 0 0 2px rgba(138,92,255,.25) inset,
                  0 0 22px rgba(138,92,255,.35);
      border-radius: 8px;
      display: none; z-index: 60; color:#fff;
    }
    .modal.show, .overlay.show { display: block; }
    .modal-inner{ position:relative; height:100%; padding:18px 18px 64px; }
    .modal-title { text-align:center; font-weight:800; letter-spacing:.5px; }
    .modal-title h2 { font-size:20px; }
    .modal-title .line { width:70%; height:2px; background:#10182f; border-bottom:2px solid #2c2f49; margin:6px auto 0; }

    .modal-close { position:absolute; top:10px; right:12px; font-size:22px; cursor:pointer; opacity:.9; }
    .pill-new { position:absolute; top:56px; left:16px; background:#e9edf1; color:#111; font-weight:800; font-size:12px; padding:6px 12px; border-radius:999px; }
    .modal-budget { position:absolute; top:18px; right:22px; font-weight:800; }

    /* (Removed modal-search styles) */

    .modal-done {
      position:absolute; right:18px; bottom:16px; background:#e9edf1; color:#111;
      font-weight:800; border:none; padding:8px 18px; border-radius:999px; cursor:pointer;
    }

    @media (max-width: 700px){
      .modal{ width: 70vw; height: 70vh; }
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/icons/sklogo.png" alt="SK Logo">

    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">📊 Dashboard</a>

    <div class="nav-item active">🗂️ Proposals ▾</div>
    <div class="sub-links">
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/programs.php">Programs</a>
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/projects.php">Project Proposal</a>
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/activities.php">Activities</a>
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/annual_budget.php">Annual Budget</a>
    </div>

    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/templates.php">📄 Document Templates</a>
    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/reports.php">📑 Reports</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <div class="title"><?= htmlspecialchars($TYPE, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="right"><div class="icons">🔔 <div class="user-icon">👤</div></div></div>
    </div>

    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount"><?= htmlspecialchars($fundRemDisplay, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

    <div class="content-box">
      <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

      <!-- SEARCH BAR -->
      <div class="search-container">
        <form class="search-bar" method="get" action="">
          <input type="text" name="q" placeholder="Search title or status…" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
        </form>
      </div>

      <!-- UPLOAD TOOLBAR -->
      <div class="toolbar">
        <span class="chk" aria-hidden="true"></span>
        <button type="button" class="btn-upload" id="openUpload">UPLOAD</button>
      </div>

      <?php if (!$userLoc): ?>
        <div style="opacity:.9; margin-top:14px;">No location set on your account. Ask an admin to add your location to see <?= strtolower($TYPE) ?>s.</div>
      <?php elseif (!$rows): ?>
        <div style="opacity:.9; margin-top:14px;">No <?= strtolower($TYPE) ?>s found for <?= htmlspecialchars($userLoc, ENT_QUOTES, 'UTF-8') ?>.</div>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $title = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
          $open  = $r['attachment_path'] ? '../../' . ltrim($r['attachment_path'], '/') : '';
        ?>
          <div class="p-item">
            <div class="p-left">
              <div class="t">
                <?php if ($open): ?>
                  <a href="<?= htmlspecialchars($open, ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="text-decoration:none; color:inherit;"><?= $title ?></a>
                <?php else: ?>
                  <?= $title ?>
                <?php endif; ?>
              </div>
              <div class="m">
                <?php if (!empty($r['ppa_ref'])): ?>
                  PPA Ref: <?= htmlspecialchars($r['ppa_ref'], ENT_QUOTES, 'UTF-8') ?>&nbsp;•&nbsp;
                <?php endif; ?>
                Status: <?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?>
                <?php if ($userLoc !== ''): ?>&nbsp;•&nbsp; Location: <?= htmlspecialchars($userLoc, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
              </div>
            </div>
            <div class="p-right">
              <div class="b"><?= peso((float)$r['budget']) ?></div>
              <div class="d"><?= $r['dt'] ? date('M j, Y', strtotime((string)$r['dt'])) : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Overlay + Modal -->
  <div class="overlay" id="overlay"></div>
  <div class="modal" id="uploadModal" role="dialog" aria-modal="true" aria-labelledby="mTitle">
    <div class="modal-inner">
      <div class="modal-title">
        <h2 id="mTitle">ANNUAL PLAN</h2>
        <div class="line"></div>
      </div>
      <div class="modal-close" id="closeUpload" title="Close">×</div>
      <div class="pill-new">NEW</div>
      <div class="modal-budget">Budget Funds: <?= htmlspecialchars(number_format((float)$fundRemaining, 0), ENT_QUOTES, 'UTF-8') ?></div>

      <button class="modal-done" id="doneUpload">DONE</button>
    </div>
  </div>

  <script>
    const openBtn = document.getElementById('openUpload');
    const closeBtn = document.getElementById('closeUpload');
    const doneBtn  = document.getElementById('doneUpload');
    const modal    = document.getElementById('uploadModal');
    const overlay  = document.getElementById('overlay');

    function openModal(){
      modal.classList.add('show');
      overlay.classList.add('show');
      document.body.style.overflow = 'hidden';
    }
    function closeModal(){
      modal.classList.remove('show');
      overlay.classList.remove('show');
      document.body.style.overflow = '';
      openBtn.focus();
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    doneBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && modal.classList.contains('show')) closeModal(); });
  </script>
</body>
</html>
