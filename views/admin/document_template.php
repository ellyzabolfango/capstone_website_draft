<?php
// /views/admin/document_template.php
session_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php'; // loads DB, CSRF, AUTH, constants

auth_required();
if (!is_admin()) {
  header("Location: " . PUBLIC_URL . "/index.php");
  exit();
}

$msg = '';

// ---------- DELETE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if (!csrf_check($_POST['csrf_token'] ?? null)) {
    $msg = "Invalid request.";
  } else {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      // get path
      $stmt = $conn->prepare("SELECT file_path FROM templates WHERE id=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $res = $stmt->get_result()->fetch_assoc();
      if ($res) {
        $rel = $res['file_path']; // e.g., uploads/templates/xxx.docx
        // compute disk path
        $root = realpath(__DIR__ . '/../../');
        $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
        if (is_file($full)) { @unlink($full); }
        // delete row
        $del = $conn->prepare("DELETE FROM templates WHERE id=?");
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

// ---------- UPLOAD ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
  if (!csrf_check($_POST['csrf_token'] ?? null)) {
    $msg = "Invalid request.";
  } else {
    $name = trim($_POST['template_name'] ?? '');
    if ($name === '' || empty($_FILES['file']['name'])) {
      $msg = "Template name and file are required.";
    } else {
      $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx'];
      $orig = $_FILES['file']['name'];
      $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      if (!in_array($ext, $allowed, true)) {
        $msg = "Unsupported file type.";
      } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $msg = "Upload error.";
      } elseif ($_FILES['file']['size'] > 15 * 1024 * 1024) {
        $msg = "File too large (max 15MB).";
      } else {
        $dirDisk = __DIR__ . '/../../uploads/templates';
        if (!is_dir($dirDisk)) { @mkdir($dirDisk, 0777, true); }
        $saved = uniqid('tpl_', true) . '.' . $ext;
        $diskPath = $dirDisk . '/' . $saved;
        $webRel   = 'uploads/templates/' . $saved;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $diskPath)) {
          $uid = $_SESSION['user_id'] ?? null;
          $stmt = $conn->prepare("INSERT INTO templates (name, file_path, uploaded_by) VALUES (?, ?, ?)");
          $stmt->bind_param('ssi', $name, $webRel, $uid);
          if ($stmt->execute()) {
            $msg = "Uploaded successfully.";
          } else {
            $msg = "DB save failed.";
          }
        } else {
          $msg = "Failed to save file.";
        }
      }
    }
  }
}

// ---------- SEARCH & LIST ----------
$q = trim($_GET['q'] ?? '');
$where = ''; $types = ''; $params = [];
if ($q !== '') { $where = "WHERE name LIKE ?"; $like = "%{$q}%"; $types='s'; $params[]=$like; }

$sql = "SELECT id, name, file_path, uploaded_at FROM templates $where ORDER BY uploaded_at DESC, id DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// icon map
function template_icon($path) {
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  // put these icons under /public/assets/icons/ and adjust below if needed
  $icons = [
    'pdf'  => '../../public/assets/icons/pdf.png',
    'doc'  => '../../public/assets/icons/word.png',
    'docx' => '../../public/assets/icons/word.png',
    'xls'  => '../../public/assets/icons/excel.png',
    'xlsx' => '../../public/assets/icons/excel.png',
    'ppt'  => '../../public/assets/icons/ppt.png',
    'pptx' => '../../public/assets/icons/ppt.png',
    'default' => '../../public/assets/icons/file.png',
  ];
  return $icons[$ext] ?? $icons['default'];
}
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
    .sidebar a { color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0; border-radius: 8px; width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold; }
    .sidebar a.active { background-color: #2ec8b5; }

    .main { flex: 1; padding: 30px; overflow-y: auto; }

    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .search-bar { background: rgba(255, 255, 255, 0.2); border-radius: 30px; padding: 10px 20px; display: flex; align-items: center; gap: 10px; width: 400px; color: #fff; }
    .search-bar input { background: transparent; border: none; color: #fff; font-size: 16px; outline: none; width: 100%; }
    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }
    .user-icon { width: 36px; height: 36px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #333; }

    .fund-remaining { display: flex; justify-content: flex-end; margin: 12px 0 20px; }
    .fund-badge { display: inline-flex; align-items: center; gap: 12px; padding: 10px 18px; border-radius: 999px; background: rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.38); font-weight: 800; letter-spacing: 0.35px; backdrop-filter: blur(2px); font-size: 16px; }
    .fund-badge .label { opacity: 0.95; font-size: 13px; }
    .fund-badge .amount { font-size: 16px; }

    .new-btn { background: #dcdcdc; color: #000; border: none; padding: 8px 20px; border-radius: 20px; font-weight: bold; cursor: pointer; margin-bottom: 20px; }

    .file-cards { display: flex; flex-wrap: wrap; gap: 20px; }
    .file-card { background: #e5e5e5; color: #000; border-radius: 20px; min-width: 240px; max-width: 240px; padding: 16px 18px 12px; display: flex; flex-direction: column; justify-content: space-between; position: relative; font-weight: bold; height: 90px; }
    .file-card .title { display: flex; align-items: center; font-size: 14px; margin-right: 20px; }
    .file-card .title img { width: 20px; margin-right: 8px; }
    .file-card .menu { position: absolute; top: 12px; right: 12px; font-weight: bold; cursor: pointer; color: #333; user-select: none; }

    /* tiny popup menu under ⋮ */
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
    <img src="/public/assets/icons/sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="index.php"> 📊 Dashboard</a>
    <a href="manage_proposals.php">📁 Manage Proposals</a>
    <a href="user_management.php">👥 User Management</a>
    <a href="document_template.php" class="active">📄 Document Templates</a>
    <a href="reports.php">📑 Reports</a>
  </div>

  <!-- Main Content -->
  <div class="main">
    <div class="top-bar">
      <form class="search-bar" method="get" action="">
        🔍 <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" /> ⚙️
      </form>
      <div class="icons"> 🔔 <div class="user-icon">👤</div> </div>
    </div>

    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount">₱ <?= htmlspecialchars($fundRemaining, ENT_QUOTES) ?></span>
      </div>
    </div>

    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div><?php endif; ?>

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
          $time = $r['uploaded_at'] ? date('g:iA', strtotime($r['uploaded_at'])) : '';
          $href = '../../' . ltrim($r['file_path'], '/');
        ?>
          <div class="file-card" title="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>">
            <div class="menu" data-menu>⋮</div>

            <!-- tiny popup under ⋮ -->
            <div class="menu-pop">
              <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" target="_blank">Open</a>
              <form method="post" onsubmit="return confirm('Delete template \"<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>\"?');">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit">Delete</button>
              </form>
            </div>

            <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" target="_blank" style="text-decoration:none; color:inherit;">
              <div class="title">
                <img src="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" alt="Icon" />
                <?= htmlspecialchars($r['name'], ENT_QUOTES) ?>
              </div>
            </a>
            <div class="time"><?= htmlspecialchars($time, ENT_QUOTES) ?></div>
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
      if (!isMenuBtn) {
        // click outside: close all
        allMenus.forEach(m => m.style.display = 'none');
        return;
      }
      const card = e.target.closest('.file-card');
      const pop  = card.querySelector('.menu-pop');
      // close others
      allMenus.forEach(m => { if (m !== pop) m.style.display = 'none'; });
      // toggle this
      pop.style.display = (pop.style.display === 'block') ? 'none' : 'block';
    });
  </script>
</body>
</html>
