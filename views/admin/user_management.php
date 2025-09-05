<?php
/**
 * /views/admin/user_management.php
 * Admin-only page to search users and bulk Activate/Deactivate.
 * - Matches Admin Dashboard navigation/top bar styling
 * - GET ?q=... to search users (fullname/username/email/location/position)
 * - POST bulk actions with CSRF protection
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php'; // db(), csrf_*, auth_*, constants

auth_required();
if (!is_admin()) {
  header('Location: ' . PUBLIC_URL . '/index.php');
  exit();
}

/* -------------------------------------------------------
| Handle bulk actions (Activate/Deactivate) — POST
|--------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Keep current search after POST
  $qKeep = (string)($_POST['q'] ?? '');

  // CSRF check
  if (!csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: user_management.php?q=' . urlencode($qKeep));
    exit();
  }

  $action = (string)($_POST['action'] ?? '');
  $ids = array_values(array_filter(array_map('intval', $_POST['ids'] ?? []), fn($v) => $v > 0));

  // ✅ Prevent deactivating yourself (avoid lockout)
  $me = (int)(current_user_id() ?? 0);
  if ($me > 0 && $ids) {
    $ids = array_values(array_diff($ids, [$me]));
  }

  if ($ids && in_array($action, ['activate', 'deactivate'], true)) {
    $flag   = $action === 'activate' ? 1 : 0;
    $ph     = implode(',', array_fill(0, count($ids), '?'));
    $types  = str_repeat('i', count($ids));
    $sql    = "UPDATE users SET is_active = ? WHERE id IN ($ph)";

    $stmt = db()->prepare($sql);
    $bindTypes  = 'i' . $types;
    $bindValues = array_merge([$flag], $ids);
    $stmt->bind_param($bindTypes, ...$bindValues);
    $stmt->execute();
  }

  header('Location: user_management.php?q=' . urlencode($qKeep));
  exit();
}

/* -------------------------------------------------------
| Search — GET
|--------------------------------------------------------*/
$q      = trim((string)($_GET['q'] ?? ''));
$where  = '';
$params = [];
$types  = '';

if ($q !== '') {
  // ✅ COALESCE to handle NULLs on location/position
  $where  = "WHERE fullname LIKE ? OR username LIKE ? OR email LIKE ? OR COALESCE(location,'') LIKE ? OR COALESCE(position,'') LIKE ?";
  $like   = "%{$q}%";
  $params = [$like, $like, $like, $like, $like];
  $types  = 'sssss';
}

/* -------------------------------------------------------
| Fetch users
|--------------------------------------------------------*/
$sql = "
  SELECT id, fullname, barangay, position, role,
         COALESCE(is_active, 1) AS is_active
  FROM users
  $where
  ORDER BY fullname ASC, id ASC
";
$stmt = db()->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Header user label
$username = (string)($_SESSION['username'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>User Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <style>
    /* ===== Base & Layout (match dashboard) ===== */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Arial, sans-serif; display: flex; height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: #fff;
    }

    /* Sidebar (same as dashboard) */
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
      color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0;
      border-radius: 8px; width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold; transition: background .3s;
    }
    .sidebar a:hover { background: rgba(255,255,255,.15); }
    .sidebar a.active { background: #2ec8b5; color: #fff; }

    /* Main */
    .main { flex: 1; padding: 30px; display: flex; flex-direction: column; overflow-y: auto; }

    /* ===== Top bar (EXACT layout as dashboard.php) ===== */
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .header  { font-size: 45px; font-weight: bold; text-shadow: 2px 2px #000, 0 0 8px #fff; }

    .right-wrap { display: flex; align-items: center; gap: 20px; }

    /* Search bar */
    .search-bar{
      background: rgba(255,255,255,0.2); border-radius: 30px; padding: 10px 16px;
      display:flex; align-items:center; gap:10px; width: 420px; color:#fff;
    }
    .search-bar input{
      background: transparent; border: none; color: #fff; font-size: 16px; outline: none; width: 100%;
    }

    /* ===== User menu (EXACT sizes) ===== */
    .user-menu { position: relative; }
    .user-btn  { display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; }
    .user-icon {
      width: 36px; height: 36px; border-radius: 50%; background: #fff;
      display:flex; align-items:center; justify-content:center; font-size: 18px; color: #333;
    }
    .caret { font-size: 14px; opacity: .9; }

    .menu {
      position: absolute; right: 0; top: 120%; background: #fff; color: #222; min-width: 200px;
      border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.25); overflow: hidden; display: none; z-index: 100;
    }
    .menu.show { display: block; }
    .menu-header { padding: 12px 14px; font-weight: bold; background: #f2f5f8; }
    .menu-item a { display: block; width: 100%; text-align: left; padding: 12px 14px; color: #222; text-decoration: none; }
    .menu-item a:hover { background: #eef3f9; }

    /* Users list */
    .user-list-container{ background: rgba(255,255,255,0.1); padding:20px; border-radius:15px; }
    .user-header{ display:flex; align-items:center; justify-content:space-between; margin-bottom:15px; }
    .user-header-left{ display:flex; align-items:center; gap:10px; }
    .user-header-left input[type="checkbox"]{ transform: scale(1.2); }
    .user-header-left button{
      padding:6px 16px; border-radius:10px; border:none;
      background:#e4e4e4; font-weight:bold; color:#000; cursor:pointer;
    }

    .user-row{
      background:#efefef; color:#000; border-radius:10px;
      display:flex; align-items:center; justify-content:space-between;
      padding:12px 16px; margin-bottom:10px;
    }
    .user-left{ display:flex; align-items:center; gap:10px; }
    .user-left input[type="checkbox"]{ transform: scale(1.2); }

    .user-info{ display:flex; flex-direction:column; }
    .user-info .name{ font-weight:bold; }
    .user-info .from{ font-size:13px; color:#444; }

    .user-position{ font-weight:bold; font-size:14px; }
    .status{
      background:#e3f9e6; color:#2ecc71; padding:5px 12px; border-radius:20px;
      font-size:13px; font-weight:bold;
    }
    .status.inactive{ background:#fde2e1; color:#e74c3c; }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/icons/sklogo.png" class="logo" alt="SK Logo" />
    <div class="label"></div>
    <a href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8') ?>/index.php">📊 Dashboard</a>
    <a href="<?= htmlspecialchars(ADMIN_URL,  ENT_QUOTES, 'UTF-8') ?>/manage_proposals.php">📁 Manage Proposals</a>
    <a href="<?= htmlspecialchars(ADMIN_URL,  ENT_QUOTES, 'UTF-8') ?>/user_management.php" class="active">👥 User Management</a>
    <a href="<?= htmlspecialchars(ADMIN_URL,  ENT_QUOTES, 'UTF-8') ?>/document_template.php">📄 Document Templates</a>
    <a href="<?= htmlspecialchars(ADMIN_URL,  ENT_QUOTES, 'UTF-8') ?>/reports.php">📑 Reports</a>
  </div>

  <!-- Main -->
  <div class="main">
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="header">USER MANAGEMENT</div>

      <div class="right-wrap">
        <!-- Search -->
        <form class="search-bar" method="get" action="">
          🔍
          <input type="text" name="q" placeholder="Search name, username, email, location, position..."
                 value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" />
        </form>

        <!-- User menu -->
        <div class="user-menu" id="userMenu">
          <div class="user-btn" id="userBtn" aria-haspopup="true" aria-expanded="false">
            🔔
            <div class="user-icon">👤</div>
            <span class="caret">▾</span>
          </div>
          <div class="menu" id="menu">
            <div class="menu-header">@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="menu-item"><a href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Logout</a></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Users -->
    <div class="user-list-container">
      <form method="post" id="bulkForm">
        <input type="hidden" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" />
        <?= csrf_field() ?>

        <div class="user-header">
          <div class="user-header-left">
            <input type="checkbox" id="checkAll" />
            <button type="button" onclick="location.href='<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8') ?>/register.php'">+ New</button>
            <button type="submit" name="action" value="activate">Activate</button>
            <button type="submit" name="action" value="deactivate">Deactivate</button>
          </div>
        </div>

        <?php if (!$rows): ?>
          <div style="opacity:.9;">No users found.</div>
        <?php else: ?>
          <?php foreach ($rows as $u): ?>
            <?php
              $active = (int)$u['is_active'] === 1;
              $cls    = $active ? 'status' : 'status inactive';
              $txt    = $active ? 'Activated' : 'Deactivated';
            ?>
            <div class="user-row">
              <div class="user-left">
                <input type="checkbox" name="ids[]" value="<?= (int)$u['id'] ?>" class="rowCheck" />
                <div class="user-info">
                  <div class="name">
                    <?= htmlspecialchars($u['fullname'] ?: '(No name)', ENT_QUOTES, 'UTF-8') ?>
                    (<?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?>)
                  </div>
                  <div class="from">From: <?= htmlspecialchars($u['barangay'] ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
              </div>
              <div class="user-position"><?= htmlspecialchars($u['position'] ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
              <div class="<?= $cls ?>"><?= $txt ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <script>
    // User dropdown (same behavior as dashboard)
    (function () {
      const btn = document.getElementById('userBtn');
      const menu = document.getElementById('menu');
      if (btn && menu) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          const open = menu.classList.toggle('show');
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
          if (!menu.classList.contains('show')) return;
          if (!menu.contains(e.target) && !btn.contains(e.target)) {
            menu.classList.remove('show');
            btn.setAttribute('aria-expanded', 'false');
          }
        });
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && menu.classList.contains('show')) {
            menu.classList.remove('show');
            btn.setAttribute('aria-expanded', 'false');
          }
        });
      }
    })();

    // Select/Deselect all
    (function () {
      const checkAll = document.getElementById('checkAll');
      const checks   = document.querySelectorAll('.rowCheck');
      if (checkAll) {
        checkAll.addEventListener('change', e => {
          checks.forEach(c => c.checked = e.target.checked);
        });
      }
    })();
  </script>
</body>
</html>
