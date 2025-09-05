<?php
/**
 * /views/admin/manage_proposals.php — Admin only
 * - Navigation and top bar match the Admin Dashboard
 * - Search proposals by title/category
 * - Left list: proposals; Right pane: details + status update (CSRF)
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

auth_required();
if (!is_admin()) { header("Location: " . PUBLIC_URL . "/index.php"); exit(); }

// ---------- Inputs ----------
$q  = trim((string)($_GET['q'] ?? ''));
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- Fetch proposals ----------
$proposals = [];
$params = [];
$types  = '';
$where  = '';

if ($q !== '') {
  $where = "WHERE p.title LIKE ? OR COALESCE(p.category,'') LIKE ?";
  $like  = "%{$q}%";
  $params[] = $like; 
  $params[] = $like; 
  $types .= 'ss';
}

$sql = "
  SELECT
    p.id,
    p.title,
    COALESCE(p.category,'')        AS category,
    COALESCE(p.status,'Pending')   AS status,
    p.submitted_at,
    COALESCE(p.description,'')     AS description,
    COALESCE(p.attachment_path,'') AS attachment_path,
    p.budget,
    COALESCE(u.fullname, u.username, '') AS creator_name
  FROM proposals p
  LEFT JOIN users u ON u.id = p.created_by
  $where
  ORDER BY p.submitted_at DESC, p.id DESC
";
$stmt = db()->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
  $timeStr = $row['submitted_at'] ? date('g:iA', strtotime((string)$row['submitted_at'])) : '';
  $proposals[] = [
    'id'          => (int)$row['id'],
    'title'       => (string)$row['title'],
    'category'    => (string)$row['category'],
    'status'      => (string)$row['status'],
    'time'        => $timeStr,
    'creator'     => (string)$row['creator_name'],
    'description' => (string)$row['description'],
    'attachment'  => (string)$row['attachment_path'],
    'budget'      => $row['budget']
  ];
}

// Default selected proposal
$selected = null;
if (!empty($proposals)) {
  if ($id) foreach ($proposals as $p) { if ($p['id'] === $id) { $selected = $p; break; } }
  if ($selected === null) $selected = $proposals[0];
}

function file_label(?string $path): string {
  if (!$path) return '';
  $bn = basename($path);
  return $bn ?: (string)$path;
}

$username = (string)($_SESSION['username'] ?? 'Admin');

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Proposals</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    /* ===== Base / Layout (match dashboard look) ===== */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Arial, sans-serif; display: flex; height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: #fff;
    }

    /* Sidebar */
    .sidebar {
      width: 250px; background: #2e4b4f; padding: 20px 0;
      display: flex; flex-direction: column; align-items: center;
    }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar a {
      color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0;
      border-radius: 8px; width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold; transition: background .3s;
    }
    .sidebar a:hover { background: rgba(255,255,255,.15); }
    .sidebar a.active { background: #2ec8b5; color: #fff; }

    /* Main */
    .main { flex: 1; padding: 30px; overflow-y: auto; }

    /* Top bar */
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .header { font-size: 40px; font-weight: bold; text-shadow: 2px 2px #000, 0 0 8px #fff; }

    /* User menu */
    .user-menu { position: relative; }
    .user-btn { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
    .user-icon {
      width: 36px; height: 36px; border-radius: 50%; background: #fff; display: flex;
      align-items: center; justify-content: center; font-size: 18px; color: #333;
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

    /* Search */
    .search-bar {
      background: rgba(255,255,255,0.2); border-radius: 30px; padding: 10px 16px;
      display: flex; align-items: center; gap: 10px; width: 420px; color: #fff;
    }
    .search-bar input {
      background: transparent; border: none; color: #fff; font-size: 16px; outline: none; width: 100%;
    }

    /* Content */
    .content { display: flex; gap: 20px; margin-top: 20px; }
    .proposal-list { flex: 1; display: flex; flex-direction: column; gap: 15px; }
    .block-link { text-decoration: none; color: inherit; display: block; }

    .proposal-item {
      background: #d3d8e0; color: #000; padding: 15px 15px 25px 15px; border-radius: 6px;
      position: relative; font-weight: bold; border-left: 6px solid transparent;
    }
    .proposal-item .status {
      background: orange; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold;
      position: absolute; top: 15px; right: 15px;
    }
    .proposal-item small { font-weight: normal; font-size: 13px; display: block; color: #444; }
    .proposal-time { font-size: 11px; color: #333; text-align: right; margin-top: 5px; }

    .detail {
      flex: 1.3; background: #efefef; color: #000;
      padding: 40px 50px; border-radius: 15px; display: flex; flex-direction: column; gap: 12px; position: relative;
    }
    .detail h2 { text-align: center; font-weight: bold; font-size: 22px; margin-top: 10px; margin-bottom: 12px; }
    .detail label { font-weight: bold; display: inline-block; min-width: 260px; }
    .detail p { margin: 6px 0; font-size: 15px; }

    .file-box { display: inline-flex; align-items: center; background: white; padding: 5px 10px; border-radius: 6px; font-size: 13px; border: 1px solid #aaa; margin-left: 5px; }
    .update-btn { background: #06004d; color: white; padding: 10px 24px; border: none; border-radius: 10px; cursor: pointer; font-weight: bold; align-self: center; margin-top: 16px; }

    .date { position: absolute; bottom: 20px; right: 30px; font-size: 12px; color: #555; }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL) ?>/assets/icons/sklogo.png" alt="Logo" class="logo" />
    <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/index.php"> 📊 Dashboard</a>
    <a href="<?= htmlspecialchars(ADMIN_URL) ?>/manage_proposals.php" class="active">📁 Manage Proposals</a>
    <a href="<?= htmlspecialchars(ADMIN_URL) ?>/user_management.php">👥 User Management</a>
    <a href="<?= htmlspecialchars(ADMIN_URL) ?>/document_template.php">📄 Document Templates</a>
    <a href="<?= htmlspecialchars(ADMIN_URL) ?>/reports.php">📑 Reports</a>
  </div>

  <!-- Main -->
  <div class="main">
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="header">ADMIN • MANAGE PROPOSALS</div>
      <div style="display:flex; align-items:center; gap:20px;">
        <!-- Search -->
        <form class="search-bar" method="get" action="">
          🔍
          <input type="text" name="q" placeholder="Search by title or category..." 
                 value="<?= htmlspecialchars($q) ?>">
        </form>

        <!-- User menu -->
        <div class="user-menu" id="userMenu">
          <div class="user-btn" id="userBtn">
            🔔
            <div class="user-icon">👤</div>
            <span class="caret">▾</span>
          </div>
          <div class="menu" id="menu">
            <div class="menu-header">@<?= htmlspecialchars($username) ?></div>
            <div class="menu-item">
              <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/logout.php">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="content">
      <!-- Proposal List -->
      <div class="proposal-list">
        <?php if (empty($proposals)): ?>
          <div>No proposals found.</div>
        <?php else: ?>
          <?php foreach ($proposals as $p): ?>
            <a class="block-link" href="?<?= http_build_query(['q' => $q, 'id' => $p['id']]) ?>">
              <div class="proposal-item" style="border-left-color:
                <?= $p['status']==='Approved' ? '#12b76a' :
                   ($p['status']==='Rejected' ? '#e11d48' :
                   ($p['status']==='Completed' ? '#22c55e' : '#f59e0b')) ?>;">
                <div><?= htmlspecialchars($p['title']) ?></div>
                <small>Category: <?= htmlspecialchars($p['category']) ?></small>
                <div class="status" style="background:
                  <?= $p['status']==='Approved' ? '#12b76a' :
                     ($p['status']==='Rejected' ? '#e11d48' :
                     ($p['status']==='Completed' ? '#22c55e' : '#f59e0b')) ?>;">
                  <?= htmlspecialchars($p['status']) ?>
                </div>
                <div class="proposal-time"><?= htmlspecialchars($p['time']) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Proposal Details -->
      <div class="detail">
        <?php if ($selected): ?>
          <p><label>CREATED BY:</label> <?= htmlspecialchars($selected['creator'] ?: 'N/A') ?></p>
          <h2>PROPOSAL<br><small>NO.<?= (int)$selected['id'] ?></small></h2>
          <p><label>Category:</label> <?= htmlspecialchars($selected['category']) ?></p>
          <p><label>Description:</label>
            <?= $selected['description'] !== '' ? htmlspecialchars($selected['description']) : '<i>(none)</i>' ?>
          </p>
          <p><label>Budget:</label> ₱ <?= number_format((float)$selected['budget'], 2) ?></p>
          <p><label>File Attachment:</label>
            <?php if ($selected['attachment']): ?>
              <?php $label = file_label($selected['attachment']); ?>
              <a class="file-box" href="<?= htmlspecialchars($selected['attachment']) ?>" target="_blank" rel="noopener">
                📄 <?= htmlspecialchars($label) ?>
              </a>
            <?php else: ?>
              <i>No file</i>
            <?php endif; ?>
          </p>

          <!-- Update Status -->
          <form method="POST" action="<?= htmlspecialchars(PUBLIC_URL) ?>/update_proposal_status.php" style="text-align:center; margin-top:10px;">
            <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
            <?= csrf_field() ?>
            <select name="status" required>
              <?php foreach (['Pending','Approved','Rejected','Completed'] as $st): ?>
                <option value="<?= $st ?>" <?= $st === $selected['status'] ? 'selected' : '' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
            <button class="update-btn" type="submit">Update Status</button>
          </form>

          <div class="date"><?= date("F j, Y") ?></div>
        <?php else: ?>
          <h2>Proposal</h2>
          <p>No proposal selected.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    // User dropdown
    (function () {
      const btn = document.getElementById('userBtn');
      const menu = document.getElementById('menu');

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
