<?php
// /views/user/activities.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php'; // db(), auth_*, constants (+ csrf_*)

auth_required();

$TYPE = 'Activity';
function peso(float $n): string { return '₱' . number_format($n, 0); }

/* Inputs */
$q   = trim((string)($_GET['q'] ?? ''));
$msg = trim((string)($_GET['msg'] ?? ''));

/* Current user + barangay (optional display) */
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('barangay: ' . LOGIN_URL); exit(); }

$stmtU = db()->prepare("SELECT COALESCE(barangay,'') AS loc FROM users WHERE id=?");
$stmtU->bind_param('i', $userId);
$stmtU->execute();
$userLoc = (string)($stmtU->get_result()->fetch_assoc()['loc'] ?? '');

/* HANDLE CREATE (modal Save) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  if (function_exists('csrf_check') && !csrf_check($_POST['csrf_token'] ?? null)) {
    header('barangay: activities.php?msg=' . urlencode('Invalid request.')); exit();
  }

  $title       = trim((string)($_POST['title'] ?? ''));
  $budget      = (float)($_POST['budget'] ?? 0);
  $description = trim((string)($_POST['description'] ?? ''));
  $implRaw     = trim((string)($_POST['impl_date'] ?? '')); // optional

  $errors = [];
  if ($title === '')  $errors[] = 'Title is required';
  if ($budget <= 0)   $errors[] = 'Budget must be greater than zero';

  // Normalize implementation date (nullable)
  $implDate = null;
  if ($implRaw !== '') {
    $ts = strtotime($implRaw);
    if ($ts === false) $errors[] = 'Invalid implementation date';
    else $implDate = date('Y-m-d', $ts);
  }

  // Optional file
  $savedRel = null;
  if (!empty($_FILES['file']['name'])) {
    if ((int)$_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'Upload failed';
    } else {
      $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','png','jpg','jpeg','gif'];
      $ext = strtolower((string)pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, $allowed, true)) {
        $errors[] = 'Unsupported file type';
      } elseif ((int)$_FILES['file']['size'] > 15 * 1024 * 1024) {
        $errors[] = 'File too large (max 15MB)';
      } else {
        $dirDisk = dirname(__DIR__, 2) . '/uploads/proposals';
        if (!is_dir($dirDisk)) @mkdir($dirDisk, 0777, true);
        $saved    = uniqid('prop_', true) . '.' . $ext;
        $diskPath = $dirDisk . '/' . $saved;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $diskPath)) {
          $savedRel = 'uploads/proposals/' . $saved;
        } else {
          $errors[] = 'Failed to save file';
        }
      }
    }
  }

  if ($errors) {
    header('barangay: activities.php?msg=' . urlencode(implode(' • ', $errors))); exit();
  }

  // Insert using current schema
  $sqlIns = "
    INSERT INTO proposals
      (title, description, attachment_path, implementation_date, budget, category, status, created_by, submitted_at)
    VALUES
      (?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())
  ";
  $stmt = db()->prepare($sqlIns);
  // s=title, s=desc, s=file, s=impl_date (nullable ok), d=budget, s=category, i=created_by
  $stmt->bind_param('ssssdsi', $title, $description, $savedRel, $implDate, $budget, $TYPE, $userId);
  $ok = $stmt->execute();

  header('barangay: activities.php?msg=' . urlencode($ok ? 'Saved!' : 'Save failed'));
  exit();
}

/* Fund Remaining (guarded if settings missing) */
$fundRemaining = 0.0;
if ($res = db()->query("SHOW TABLES LIKE 'settings'")) {
  if ($res->num_rows) {
    $row = db()->query("SELECT total_budget FROM settings WHERE id = 1")->fetch_assoc();
    if ($row) {
      $totalBudget = (float)$row['total_budget'];
      $usedRow = db()->query("
        SELECT COALESCE(SUM(budget),0) AS used
        FROM proposals
        WHERE status IN ('Approved','Completed')
      ")->fetch_assoc();
      $used = (float)($usedRow['used'] ?? 0);
      $fundRemaining = max(0.0, $totalBudget - $used);
    }
  }
}
$fundRemDisplay = peso($fundRemaining);

/* List this user's Activity proposals */
$params = [$TYPE, $userId];
$types  = 'si';
$where  = "WHERE COALESCE(p.category,'') = ? AND p.created_by = ?";

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
    COALESCE(p.status,'Pending')            AS status,
    COALESCE(p.budget,0)                    AS budget,
    COALESCE(p.attachment_path,'')          AS attachment_path,
    p.implementation_date                   AS impl_date,
    COALESCE(p.submitted_at, p.updated_at, NOW()) AS dt
  FROM proposals p
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

    .sidebar { width: 270px; background: linear-gradient(to bottom, #2e4f4f, #33676b);
      padding: 20px; display: flex; flex-direction: column; gap: 20px; }
    .sidebar img { width: 100px; align-self: center; }
    .nav-item { font-weight: bold; display: flex; align-items: center; gap: 10px;
      padding: 10px 15px; border-radius: 8px; text-decoration: none; color: white; }
    .nav-item:hover, .nav-item.active { background-color: rgba(255,255,255,0.15); }
    .sub-links { margin-left: 30px; display: flex; flex-direction: column; gap: 4px; }
    .sub-links a { font-weight: bold; color: white; font-size: 14px; text-decoration: none; padding: 4px 0; }
    .sub-links a:hover { text-decoration: underline; }

    .main { flex: 1; display: flex; flex-direction: column; padding: 30px 40px 20px; }

    .top-bar { display: flex; justify-content: space-between; align-items: center; }
    .top-bar .title { font-size: 28px; font-weight: bold; text-transform: uppercase; }
    .right { display: flex; align-items: center; gap: 18px; }
    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }
    .user-icon {
      width: 36px; height: 36px; border-radius: 50%; background: white;
      display: flex; align-items: center; justify-content: center; font-size: 18px; color: #333; }

    .fund-remaining { display: flex; justify-content: flex-end; margin: 10px 0 12px; }
    .fund-badge { display: inline-flex; align-items: center; gap: 10px;
      padding: 8px 14px; border-radius: 999px; background: rgba(0,0,0,0.25);
      border: 1px solid rgba(255,255,255,0.35); font-weight: 700; letter-spacing: 0.3px; backdrop-filter: blur(2px); }
    .fund-badge .label { opacity: 0.95; font-size: 12px; }
    .fund-badge .amount { font-size: 14px; }

    .content-box { background: rgba(0,0,0,0.25); border-radius: 12px; padding: 20px; flex: 1; overflow-y: auto; }

    .search-container { display: flex; justify-content: flex-end; margin-bottom: 12px; }
    .search-bar { display: flex; align-items: center; background: rgba(255,255,255,0.3);
      padding: 8px 15px; border-radius: 30px; width: 300px; }
    .search-bar input { border: none; background: transparent; color: white; font-size: 14px; flex: 1; outline: none; }
    .search-bar::before { content: '🔍'; margin-right: 8px; font-size: 16px; }
    .search-bar::after  { content: '⚙️'; margin-left: 8px; font-size: 16px; }

    .toolbar { display: flex; align-items: center; gap: 12px; margin: 16px 0 14px; }
    .chk { width: 18px; height: 18px; border-radius: 4px; border: 2px solid #d6d6d6; background: transparent; display: inline-block; }
    .btn-upload {
      background:#e9edf1; color:#111; font-weight:700; font-size:12px; border:none;
      padding:6px 14px; border-radius:999px; cursor:pointer; box-shadow: inset 0 -1px 0 rgba(0,0,0,.15); }
    .btn-upload:active { transform: translateY(1px); }

    .a-item { background:#e5e5e5; color:#000; border-radius:10px;
      padding:12px 14px; margin:12px 0; display:flex; justify-content:space-between; align-items:center; }
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
      .a-item { flex-direction:column; align-items:flex-start; gap:6px; }
      .a-right { text-align:left; }
    }

    .msg { margin:8px 0 12px; }

    /* ===== MODAL (centered, purple border/glow) ===== */
    .overlay { position: fixed; inset: 0; background: rgba(0,0,0,.72); display: none; z-index: 50; }
    .modal {
      position: fixed; left: 50%; top: 50%; transform: translate(-50%, -50%);
      width: 74vw; max-width: 980px; background: #0b0f2b; color:#fff;
      border: 2px solid #fbfcfcff; box-shadow: 0 12px 40px rgba(0,0,0,.5), 0 0 22px rgba(138,92,255,.35);
      border-radius: 10px; display: none; z-index: 60;
    }
    .modal.show, .overlay.show { display: block; }
    .modal-inner{ position:relative; padding:18px 18px 72px; }
    .modal-title { text-align:center; font-weight:800; letter-spacing:.5px; }
    .modal-title h2 { font-size:20px; margin-bottom:6px; }
    .modal-title .line { width:70%; height:2px; background:#10182f; border-bottom:2px solid #2c2f49; margin:0 auto 12px; }
    .modal-close { position:absolute; top:12px; right:12px; font-size:22px; cursor:pointer; opacity:.9; }
    .pill-new { position:absolute; top:56px; left:16px; background:#e9edf1; color:#111; font-weight:800; font-size:12px; padding:6px 12px; border-radius:999px; }
    .modal-budget { position:absolute; top:18px; right:22px; font-weight:800; }

    .form-grid { margin: 12px auto 0; width: 88%; border: 2px solid #2a2f4a; padding: 22px; border-radius: 8px; background: #0e1436; }
    .row { display:flex; align-items:center; gap:16px; margin-bottom:14px; }
    .row label { width: 240px; font-weight:700; font-size: 12px; opacity:.95; }
    .row input[type="text"], .row input[type="date"], .row select, .row textarea {
      background:#e5e6ed; color:#000; border:1px solid #b9bfd3; border-radius:6px; padding:8px 10px; font-size:14px; }
    .row select { width: 160px; }
    .row input[type="text"] { flex: 1; }
    .row textarea{ width: 420px; height: 90px; resize: vertical; }
    .file-box { width: 120px; height: 84px; background:#e5e6ed; color:#000; border:1px solid #b9bfd3; border-radius:6px;
      display:flex; align-items:center; justify-content:center; flex-direction:column; gap:6px; }
    .file-box input { display:none; }

    .modal-done { position:absolute; right:18px; bottom:16px; background:#e9edf1; color:#111; font-weight:800;
      border:none; padding:8px 18px; border-radius:999px; cursor:pointer; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/icons/sklogo.png" alt="SK Logo">
    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">📊 Dashboard</a>

    <div class="nav-item active">🗂️ Proposals ▾</div>
    <div class="sub-links">
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/programs.php">Programs</a>
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/projects.php">Project</a>
      <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/activities.php">Activities</a>
    </div>

    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/templates.php">📄 Document Templates</a>
    <a class="nav-item" href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/reports.php">📑 Reports</a>
  </div>

  <!-- Main -->
  <div class="main">
    <!-- Top bar -->
    <div class="top-bar">
      <div class="title"><?= htmlspecialchars($TYPE, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="right"><div class="icons">🔔 <div class="user-icon">👤</div></div></div>
    </div>

    <!-- Fund Remaining -->
    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount"><?= htmlspecialchars($fundRemDisplay, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

    <!-- Content -->
    <div class="content-box">
      <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

      <!-- Search bar ABOVE -->
      <div class="search-container">
        <form class="search-bar" method="get" action="">
          <input type="text" name="q" placeholder="Search title or status…" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
        </form>
      </div>

      <!-- Upload toolbar BELOW -->
      <div class="toolbar">
        <span class="chk" aria-hidden="true"></span>
        <button type="button" class="btn-upload" id="openUpload">UPLOAD</button>
      </div>

      <?php if (!$rows): ?>
        <div style="opacity:.9; margin-top:14px;">No <?= strtolower($TYPE) ?>s yet. Upload using the button above.</div>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $title = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
          $open  = $r['attachment_path'] ? '../../' . ltrim($r['attachment_path'], '/') : '';
          $impl  = $r['impl_date'] ? date('M j, Y', strtotime((string)$r['impl_date'])) : '—';
        ?>
          <div class="a-item">
            <div class="a-left">
              <div class="t">
                <?php if ($open): ?>
                  <a href="<?= htmlspecialchars($open, ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="text-decoration:none; color:inherit;"><?= $title ?></a>
                <?php else: ?>
                  <?= $title ?>
                <?php endif; ?>
              </div>
              <div class="m">
                Status: <?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?>
                <?php if ($userLoc !== ''): ?>&nbsp;•&nbsp; barangay: <?= htmlspecialchars($userLoc, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                &nbsp;•&nbsp; Implementation Date: <?= htmlspecialchars($impl, ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <div class="a-right">
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
        <h2 id="mTitle">ANNUAL PLAN FOR ACTIVITY</h2>
        <div class="line"></div>
      </div>
      <div class="modal-close" id="closeUpload" title="Close">×</div>
      <div class="pill-new">NEW</div>
      <div class="modal-budget">Budget Funds: <?= htmlspecialchars(number_format((float)$fundRemaining, 0), ENT_QUOTES, 'UTF-8') ?></div>

      <!-- FORM -->
      <form id="createForm" method="post" enctype="multipart/form-data" class="form-grid">
        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
        <input type="hidden" name="action" value="create" />

        <div class="row"><label>NO. 1</label></div>

        <div class="row">
          <label>BUDGET:</label>
          <select name="budget" required>
            <option value="5000">5,000</option>
            <option value="10000">10,000</option>
            <option value="15000">15,000</option>
            <option value="20000">20,000</option>
            <option value="25000">25,000</option>
            <option value="50000">50,000</option>
          </select>
        </div>

        <div class="row">
          <label>TITLE:</label>
          <input type="text" name="title" placeholder="e.g., Community Engagement" required />
        </div>

        <div class="row">
          <label>DESCRIPTION:</label>
          <textarea name="description" placeholder="Describe the activity…"></textarea>
        </div>

        <div class="row">
          <label>IMPLEMENTATION COMPLETION DATE:</label>
          <input type="date" name="impl_date" />
        </div>

        <div class="row">
          <label>Upload your files</label>
          <label class="file-box">
            <span>📄</span>
            <small>Choose file</small>
            <input type="file" name="file" />
          </label>
        </div>
      </form>

      <button class="modal-done" id="doneUpload">SAVE</button>
    </div>
  </div>

  <script>
    const openBtn = document.getElementById('openUpload');
    const closeBtn = document.getElementById('closeUpload');
    const doneBtn  = document.getElementById('doneUpload');
    const modal    = document.getElementById('uploadModal');
    const overlay  = document.getElementById('overlay');
    const form     = document.getElementById('createForm');

    function openModal(){
      modal.classList.add('show');
      overlay.classList.add('show');
      document.body.style.overflow = 'hidden';
    }
    function closeModal(){
      modal.classList.remove('show');
      overlay.classList.remove('show');
      document.body.style.overflow = '';
      if (openBtn) openBtn.focus();
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (overlay)  overlay.addEventListener('click', closeModal);
    if (doneBtn)  doneBtn.addEventListener('click', () => form.requestSubmit());

    document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && modal.classList.contains('show')) closeModal(); });
  </script>
</body>
</html>
