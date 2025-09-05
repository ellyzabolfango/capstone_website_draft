<?php
/**
 * /views/user/templates.php
 * User view for document templates (read-only).
 *
 * Uses the same `templates` table as the admin page:
 *   templates(id, name, file_path, uploaded_by, uploaded_at)
 *
 * Searchable fields: name, file_path
 * Links open/download the stored relative file (served from /public/.. path).
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';


// ------------------------------------------------------------------
// Inputs
// ------------------------------------------------------------------
$q = trim($_GET['q'] ?? '');

// ------------------------------------------------------------------
// Query templates (NOTE: use the existing `templates` table)
// ------------------------------------------------------------------
$where  = '';
$params = [];
$types  = '';

if ($q !== '') {
  // Search by template name or file path
  $where  = "WHERE name LIKE ? OR file_path LIKE ?";
  $like   = "%{$q}%";
  $params = [$like, $like];
  $types  = 'ss';
}

$sql = "
  SELECT
    id,
    name AS title,                       -- normalize to `title` for UI
    file_path,
    ''   AS file_type,                   -- optional UI field; not stored
    COALESCE(uploaded_at, NOW()) AS created_at
  FROM templates
  $where
  ORDER BY uploaded_at DESC, id DESC
";

$stmt = $conn->prepare($sql);
if ($where) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ------------------------------------------------------------------
// (Optional) fund display — static demo number to match your other pages
// ------------------------------------------------------------------
$fundRemaining = "40,000";

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------
/**
 * Choose an icon for a file based on extension.
 * Replace external URLs with your own assets if preferred.
 */
function icon_for(string $pathOrType): string {
  $ext = strtolower(pathinfo($pathOrType, PATHINFO_EXTENSION) ?: $pathOrType);
  if (in_array($ext, ['pdf'], true))     return 'https://upload.wikimedia.org/wikipedia/commons/8/87/PDF_file_icon.svg';
  if (in_array($ext, ['doc','docx'], true)) return 'word.png'; // local asset (place in same folder or adjust path)
  if (in_array($ext, ['xls','xlsx'], true)) return 'https://upload.wikimedia.org/wikipedia/commons/7/73/Microsoft_Office_Excel_%282019–present%29.svg';
  if (in_array($ext, ['ppt','pptx'], true)) return 'https://upload.wikimedia.org/wikipedia/commons/3/3b/Microsoft_Office_PowerPoint_%282019–present%29.svg';
  // generic file icon
  return 'https://upload.wikimedia.org/wikipedia/commons/8/82/File_icon.svg';
}

/**
 * Build a browser link for a stored relative path.
 * This page lives at /views/user/*.php, so we walk up two levels to /.
 */
function link_path(?string $rel): string {
  if (!$rel) return '#';
  return '../../' . ltrim($rel, '/');
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
    .sidebar { width: 250px; background: #2e4b4f; padding: 20px 0;
      display: flex; flex-direction: column; align-items: center; }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar .label { font-size: 13px; font-weight: bold; text-align: center;
      color: #dff2ff; text-shadow: 1px 1px 2px #000; margin-bottom: 25px; line-height: 1.3; }
    .sidebar a { color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0;
      border-radius: 8px; width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold; }
    .sidebar a.active { background-color: #2ec8b5; }

    .main { flex: 1; padding: 30px; overflow-y: auto; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }

    .search-bar {
      background: rgba(255, 255, 255, 0.2); border-radius: 30px;
      padding: 10px 20px; display: flex; align-items: center; gap: 10px;
      width: 400px; color: #fff;
    }
    .search-bar input { background: transparent; border: none; color: #fff;
      font-size: 16px; outline: none; width: 100%; }

    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }
    .user-icon { width: 36px; height: 36px; border-radius: 50%; background: white;
      display: flex; align-items: center; justify-content: center; font-size: 18px; color: #333; }

    .fund-remaining { display: flex; justify-content: flex-end; margin: 12px 0 20px; }
    .fund-badge {
      display: inline-flex; align-items: center; gap: 12px; padding: 10px 18px; border-radius: 999px;
      background: rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.38);
      font-weight: 800; letter-spacing: 0.35px; backdrop-filter: blur(2px); font-size: 16px;
    }
    .fund-badge .label { opacity: 0.95; font-size: 13px; }
    .fund-badge .amount { font-size: 16px; }

    .file-cards { display: flex; flex-wrap: wrap; gap: 20px; }
    .file-card {
      background: #e5e5e5; color: #000; border-radius: 20px;
      min-width: 240px; max-width: 240px; padding: 16px 18px 12px;
      display: flex; flex-direction: column; justify-content: space-between;
      position: relative; font-weight: bold; height: 90px;
    }
    .file-card .title { display: flex; align-items: center; font-size: 14px; margin-right: 20px; }
    .file-card .title img { width: 20px; margin-right: 8px; }
    .file-card .menu { position: absolute; top: 12px; right: 12px; font-weight: bold;
      cursor: pointer; color: #333; user-select: none; }
    .file-card .time { font-size: 12px; color: #333; text-align: right; font-weight: normal; margin-top: 8px; }

    /* tiny menu */
    .pop { position:absolute; top:28px; right:8px; background:#fff; color:#000; border-radius:8px;
      box-shadow:0 6px 18px rgba(0,0,0,.2); padding:6px; display:none; z-index:5; }
    .pop a { display:block; text-decoration:none; color:#111; font-weight:600; font-size:12px; padding:6px 10px; border-radius:6px; }
    .pop a:hover { background:#f1f1f1; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL) ?>/assets/icons/sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">📊 Dashboard</a>
    <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/proposals.php">📁 Proposals</a>
    <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/templates.php"  class="active">📄 Document Templates</a>
    <a href="<?= htmlspecialchars(USER_URL, ENT_QUOTES, 'UTF-8') ?>/reports.php">📑 Reports</a>
  </div>

  <!-- Main Content -->
  <div class="main">
    <!-- Top Bar -->
    <div class="top-bar">
      <form class="search-bar" method="get" action="">
        🔍 <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" /> ⚙️
      </form>
      <div class="icons"> 🔔 <div class="user-icon">👤</div> </div>
    </div>

    <!-- FUND REMAINING (badge) -->
    <div class="fund-remaining">
      <div class="fund-badge">
        <span class="label">FUND REMAINING:</span>
        <span class="amount">₱ <?= htmlspecialchars($fundRemaining, ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="file-cards">
      <?php if (!$rows): ?>
        <div style="opacity:.9;">No templates found<?= $q ? " for “".htmlspecialchars($q,ENT_QUOTES)."”" : "" ?>.</div>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $title = $r['title'] ?: basename($r['file_path']);
          $icon  = icon_for($r['file_path'] ?: $r['file_type']);
          $href  = link_path($r['file_path']);
          $tm    = $r['created_at'] ? date('g:iA', strtotime($r['created_at'])) : '';
          $id    = (int)$r['id'];
        ?>
          <div class="file-card" data-id="<?= $id ?>">
            <div class="menu" title="More">⋮</div>
            <div class="pop">
              <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" target="_blank">Open</a>
              <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" download>Download</a>
            </div>
            <div class="title">
              <img src="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" alt="Icon" />
              <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" target="_blank" style="text-decoration:none; color:inherit;">
                <?= htmlspecialchars($title, ENT_QUOTES) ?>
              </a>
            </div>
            <div class="time"><?= htmlspecialchars($tm, ENT_QUOTES) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // tiny pop menu toggles
    document.querySelectorAll('.file-card .menu').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const pop = btn.parentElement.querySelector('.pop');
        const open = document.querySelector('.pop[style*="display: block"]');
        if (open && open !== pop) open.style.display = 'none';
        pop.style.display = (pop.style.display === 'block') ? 'none' : 'block';
      });
    });
    document.addEventListener('click', () => {
      document.querySelectorAll('.pop').forEach(p => p.style.display = 'none');
    });
  </script>
</body>
</html>
