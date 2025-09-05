<?php
/**
 * /views/admin/document_template.php
 * Admin-only: upload, list, open, and delete document templates.
 * - Uses db() everywhere (no raw $conn)
 * - CSRF on upload/delete
 * - Safe delete: ensures file is under uploads/templates
 * - Consistent BASE_URL / PUBLIC_URL usage for assets and links
 * - Shows Fund Remaining badge (same logic as dashboard)
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

auth_required();
if (!is_admin()) {
  header('Location: ' . PUBLIC_URL . '/index.php');
  exit();
}

$msg = '';

$totalBudget = 4000;
$fundRemaining = 4000;
$fundRemainingDisplay = number_format($fundRemaining, 0);


// -----------------------------------------------------
// DELETE
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if (!csrf_check($_POST['csrf_token'] ?? null)) {
    $msg = "Invalid request.";
  } else {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $stmt = db()->prepare("SELECT file_path FROM templates WHERE id=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $res = $stmt->get_result()->fetch_assoc();

      if ($res && $res['file_path']) {
        // Ensure deletion is limited to /uploads/templates
        $root      = realpath(dirname(__DIR__, 2)); // project root
        $uploads   = realpath($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'templates');
        $rel       = ltrim((string)$res['file_path'], '/'); // e.g., uploads/templates/xxx.docx
        $fullGuess = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $fullReal  = realpath($fullGuess);

        // Only delete if inside the uploads/templates directory
        if ($uploads && $fullReal && str_starts_with($fullReal, $uploads)) {
          if (is_file($fullReal)) { @unlink($fullReal); }
        }

        // Delete DB row regardless of whether file existed
        $del = db()->prepare("DELETE FROM templates WHERE id=?");
        $del->bind_param('i', $id);
        $del->execute();

        $msg = "Template deleted.";
      } else {
        $msg = "Not found.";
      }
    } else {
      $msg = "Invalid ID.";
    }
  }
}

// -----------------------------------------------------
// UPLOAD
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
  if (!csrf_check($_POST['csrf_token'] ?? null)) {
    $msg = "Invalid request.";
  } else {
    $name = trim((string)($_POST['template_name'] ?? ''));
    if ($name === '' || empty($_FILES['file']['name'])) {
      $msg = "Template name and file are required.";
    } else {
      $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx'];
      $orig = (string)$_FILES['file']['name'];
      $ext  = strtolower((string)pathinfo($orig, PATHINFO_EXTENSION));

      if (!in_array($ext, $allowed, true)) {
        $msg = "Unsupported file type.";
      } elseif ((int)$_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $msg = "Upload error.";
      } elseif ((int)$_FILES['file']['size'] > 15 * 1024 * 1024) {
        $msg = "File too large (max 15MB).";
      } else {
        $dirDisk = dirname(__DIR__, 2) . '/uploads/templates';
        if (!is_dir($dirDisk)) { @mkdir($dirDisk, 0777, true); }

        // Safer unique name (no user-supplied filename)
        $saved    = uniqid('tpl_', true) . '.' . $ext;
        $diskPath = $dirDisk . '/' . $saved;
        $webRel   = 'uploads/templates/' . $saved; // stored path

        if (move_uploaded_file($_FILES['file']['tmp_name'], $diskPath)) {
          $uid = $_SESSION['user_id'] ?? null;
          $stmt = db()->prepare("INSERT INTO templates (name, file_path, uploaded_by) VALUES (?, ?, ?)");
          $stmt->bind_param('ssi', $name, $webRel, $uid);
          $msg = $stmt->execute() ? "Uploaded successfully." : "DB save failed.";
        } else {
          $msg = "Failed to save file.";
        }
      }
    }
  }
}

// -----------------------------------------------------
// SEARCH & LIST
// -----------------------------------------------------
$q = trim((string)($_GET['q'] ?? ''));
$where = ''; $types = ''; $params = [];
if ($q !== '') { $where = "WHERE name LIKE ?"; $like = "%{$q}%"; $types='s'; $params[]=$like; }

$sql = "SELECT id, name, file_path, uploaded_at FROM templates $where ORDER BY uploaded_at DESC, id DESC";
$stmt = db()->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// icon map → serve from public/assets/icons via BASE_URL
function template_icon(string $path): string {
  $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
  $map = [
    'pdf'  => '/assets/icons/pdf.png',
    'doc'  => '/assets/icons/word.png',
    'docx' => '/assets/icons/word.png',
    'xls'  => '/assets/icons/excel.png',
    'xlsx' => '/assets/icons/excel.png',
    'ppt'  => '/assets/icons/ppt.png',
    'pptx' => '/assets/icons/ppt.png',
    'default' => '/assets/icons/file.png',
  ];
  $rel = $map[$ext] ?? $map['default'];
  return rtrim(BASE_URL, '/') . $rel;
}

$username = (string)($_SESSION['username'] ?? 'Admin');
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
    .sidebar { width: 250px; background: #2e4b4f; padding: 20px 0; display: flex; flex-direction: column; align-items: center; }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar .label { font-size: 13px; font-weight: bold; text-align: center; color: #dff2ff; text-shadow: 1px 1px 2px #000; margin-bottom: 25px; line-height: 1.3; }
    .sidebar a { color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0; border-radius: 8px; width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold; transition: background .3s; }
    .sidebar a:hover { background: rgba(255,255,255,.15); }
    .sidebar a.active { background-color: #2ec8b5; }

    .main { flex: 1; padding: 30px; overflow-y: auto; }

    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .search-bar { background: rgba(255,255,255,0.2); border-radius: 30px; padding: 10px 20px; display: flex; align-items: center; gap: 10px; width: 400px; color: #fff; }
    .search-bar input { background: transparent; border: none; color: #fff; font-size: 16px; outline: none; width: 100%; }

    .user-menu { position: relative; }
    .user-btn  { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
    .user-icon { width: 36px; height: 36px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #333; }
    .caret { font-size: 14px; opacity: .9; }
    .menu {
      position: absolute; right: 0; top: 120%; background: #fff; color: #222; min-width: 200px;
      border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.25); overflow: hidden; display: none; z-index: 100;
    }
    .menu.show { display: block; }
    .menu-header { padding: 12px 14px; font-weight: bold; background: #f2f5f8; }
    .menu-item a { display: block; width: 100%; text-align: left; padding: 12px 14px; color: #222; text-decoration: none; }
    .menu-item a:hover { background: #eef3f9; }

    .fund-remaining { display: flex; justify-content: flex-end; margin: 12px 0 20px; }
    .fund-badge { display: inline-flex; align-items: center; gap: 12px; padding: 10px 18px; border-radius: 999px; background: rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.38); font-weight: 800; letter-spacing: .35px; backdrop-filter: blur(2px); font-size: 16px; }
    .fund-badge .label { opacity: .95; font-size: 13px; }
    .fund-badge .amount { font-size: 16px; }

    .new-btn { background: #dcdcdc; color: #000; border: none; padding: 8px 20px; border-radius: 20px; font-weight: bold; cursor: pointer; margin-bottom: 20px; }

    .file-cards { display: flex; flex-wrap: wrap; gap: 20px; }
    .file-card { background: #e5e5e5; color: #000; border-radius: 20px; min-width: 240px; max-width: 240px; padding: 16px 18px 12px; display: flex; flex-direction: column; justify-content: space-between; position: relative; font-weight: bold; height: 90px; }
    .file-card .title { display: flex; align-items: center; font-size: 14px; margin-right: 20px; }
    .file-card .title img { width: 20px; margin-right: 8px; }
    .file-card .menu { position: absolute; top: 12px; right: 12px; font-weight: bold; cursor: pointer; color: #333; user-select: none; }

    .menu-pop {
      position: absolute; top: 30px; right: 10px; background: #fff; color: #000;
      border: 1px solid #ccc; border-radius: 8px; font-weight: normal; font-size: 13px;
      display: none; z-index: 10; min-width: 110px; box-shadow: 0 8px 18px rgba(0,0,0,.12);
    }
    .menu-pop button, .menu-pop a {
      background: transparent; border: 0; width: 100%; text-align: left; padding: 8px 10px; cursor: pointer;
      display: block; color: #000; text-decoration: none;
    }
    .menu-pop button:hover, .menu-pop a:hover { background: #f2f2f2; }

    .upload-wrap { display:none; margin-bottom: 16px; }
    .msg { margin: 8px 0 12px; font-size: 14px; color: #fff; opacity: .95; }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/icons/sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8') ?>/index.php"> 📊 Dashboard</a>
    <a href="<?= htmlspecialchars(ADMIN_URL,  ENT_QUOTES, 'UTF-8') ?>/manage_proposals.php">📁 Manage Proposals</a>
    <a href="<?= htmlspecialchars(ADMIN_URL,  ENT_QUOTES, 'UTF-8') ?>/user_management.php">👥 User Management</a>
    <a href="<?= htmlspecialchars(ADMIN_URL,  ENT_QUOTES, 'UTF-8') ?>/document_template.php" class="active">📄 Document Templates</a>
    <a href="<?= htmlspecialchars(ADMIN_URL,  ENT_QUOTES, 'UTF-8') ?>/reports.php">📑 Reports</a>
  </div>

  <!-- Main Content -->
  <div class="main">
    <div class="top-bar">
      <form class="search-bar" method="get" action="">
        🔍 <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" /> ⚙️
      </form>

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

    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount">₱ <?= htmlspecialchars($fundRemainingDisplay, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <button class="new-btn" id="newBtn">+ New</button>
    <div class="upload-wrap" id="uploadWrap">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field(); ?>
        <input type="hidden" name="action" value="upload" />
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <input type="text" name="template_name" placeholder="Template name (e.g., ABYIP Form)" required
                 style="padding:8px 12px; border-radius:8px; border:1px solid #ccc; min-width:260px; color:#000;">
          <input type="file" name="file" required
                 style="padding:6px 8px; border-radius:8px; border:1px solid #ccc; background:#fff; color:#000;">
          <button type="submit" class="new-btn">Upload</button>
        </div>
      </form>
    </div>

    <div class="file-cards">
      <?php if (!$rows): ?>
        <div style="opacity:.9">No templates found.</div>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $icon = template_icon($r['file_path']);
          $time = $r['uploaded_at'] ? date('g:iA', strtotime((string)$r['uploaded_at'])) : '';
          $href = rtrim(BASE_URL, '/') . '/' . ltrim((string)$r['file_path'], '/');
        ?>
          <div class="file-card" title="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="menu" data-menu>⋮</div>

            <div class="menu-pop">
              <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open</a>
              <form method="post" onsubmit="return confirm('Delete template &quot;<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>&quot;?');">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit">Delete</button>
              </form>
            </div>

            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="text-decoration:none; color:inherit;">
              <div class="title">
                <img src="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" alt="Icon" />
                <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </a>
            <div class="time"><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Toggle upload form
    const newBtn = document.getElementById('newBtn');
    const wrap   = document.getElementById('uploadWrap');
    if (newBtn && wrap) newBtn.addEventListener('click', () => {
      wrap.style.display = (!wrap.style.display || wrap.style.display === 'none') ? 'block' : 'none';
    });

    // Tiny ⋮ menu toggles (one open at a time)
    document.addEventListener('click', (e) => {
      const isMenuBtn = e.target.matches('[data-menu]');
      const allMenus = document.querySelectorAll('.menu-pop');
      if (!isMenuBtn) { allMenus.forEach(m => m.style.display = 'none'); return; }
      const card = e.target.closest('.file-card');
      const pop  = card.querySelector('.menu-pop');
      allMenus.forEach(m => { if (m !== pop) m.style.display = 'none'; });
      pop.style.display = (pop.style.display === 'block') ? 'none' : 'block';
    });

    // User dropdown (same as dashboard)
    (function () {
      const btn = document.getElementById('userBtn');
      const menu = document.getElementById('menu');
      if (!btn || !menu) return;
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
    })();
  </script>
</body>
</html>
