<?php
/**
 * /views/user/reports.php
 * User: list and export YOUR proposals as “Reports”.
 * - Source = proposals (+ users for barangay & prepared-by label)
 * - Filters only rows where created_by = current user
 * - Search by title/category/status/creator name/barangay
 * - CSV download of the current filtered view
 */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

auth_required();

// ---------- Current user ----------
$userId   = (int)($_SESSION['user_id'] ?? 0);
$username = (string)($_SESSION['username'] ?? 'User');
if ($userId <= 0) { header('Location: ' . LOGIN_URL); exit(); }

// ---------- Inputs ----------
$q      = trim((string)($_GET['q'] ?? ''));
$params = [$userId];
$types  = 'i';
$where  = "WHERE p.created_by = ?"; // only this user's proposals

if ($q !== '') {
  // Search title, category (type), status, prepared_by (fullname/username), barangay
  $where .= "
    AND (
         p.title LIKE ?
      OR COALESCE(p.category,'') LIKE ?
      OR COALESCE(p.status,'') LIKE ?
      OR COALESCE(u.fullname,u.username,'') LIKE ?
      OR COALESCE(u.barangay,'') LIKE ?
    )
  ";
  $like   = "%{$q}%";
  array_push($params, $like, $like, $like, $like, $like);
  $types .= 'sssss';
}

// ---------- Query ----------
$sqlBase = "
  SELECT
    p.id,
    p.title,
    COALESCE(p.category,'')       AS type,       -- Program/Project/Activity
    COALESCE(u.barangay,'—')      AS barangay,   -- user’s barangay
    COALESCE(p.status,'Pending')  AS status,
    TRIM(CONCAT(
      COALESCE(u.fullname, COALESCE(u.username, '')),
      CASE WHEN COALESCE(u.position,'') <> '' THEN CONCAT(' (', u.position, ')') ELSE '' END
    ))                             AS prepared_by,
    COALESCE(p.budget,0)          AS budget,
    p.submitted_at                AS created_at
  FROM proposals p
  LEFT JOIN users u ON u.id = p.created_by
  $where
  ORDER BY p.submitted_at DESC, p.id DESC
";

// ---------- CSV download ----------
if (isset($_GET['download'])) {
  $stmt = db()->prepare($sqlBase);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=my_reports_'.date('Ymd_His').'.csv');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['#','Title','Type','Barangay','Status','Prepared by','Budgetary Requirement','Date']);

  $i = 1;
  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      $i++,
      (string)$row['title'],
      (string)$row['type'],
      (string)$row['barangay'],
      (string)$row['status'],
      (string)$row['prepared_by'],
      number_format((float)$row['budget'], 2, '.', ''), // numeric in CSV
      !empty($row['created_at']) ? date('F j, Y', strtotime((string)$row['created_at'])) : '',
    ]);
  }
  fclose($out);
  exit();
}

// ---------- Fetch rows for page ----------
$stmt = db()->prepare($sqlBase);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      display: flex; font-family: Arial, sans-serif; height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: #fff;
    }

    /* Sidebar (user) */
    .sidebar { width: 270px; background: linear-gradient(to bottom, #2e4f4f, #33676b); padding: 20px; display:flex; flex-direction:column; gap:20px; }
    .sidebar img.logo { width: 110px; align-self:center; }
    .nav-item { font-weight:bold; display:flex; align-items:center; gap:10px; padding:10px 15px; border-radius:8px; text-decoration:none; color:#fff; }
    .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.15); }
    .sub-links { margin-left:30px; display:flex; flex-direction:column; gap:4px; }
    .sub-links a { font-weight:bold; color:#fff; font-size:14px; text-decoration:none; padding:4px 0; }
    .sub-links a:hover { text-decoration: underline; }

    .main { flex: 1; padding: 30px; overflow-y: auto; }
    .top-bar { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .search-bar { background: rgba(255,255,255,0.2); border-radius: 25px; padding: 10px 20px; display: flex; align-items: center; gap: 10px; width: 420px; color: #fff; }
    .search-bar input { background: transparent; border: none; color: #fff; outline: none; width: 100%; }

    .user-menu { position: relative; display:flex; flex-direction:column; align-items:flex-end; gap:10px; }
    .user-btn { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
    .user-icon { width: 36px; height: 36px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.3); }
    .caret { font-size: 14px; opacity: .9; }
    .menu { position: absolute; right: 0; top: 120%; background: #fff; color: #222; min-width: 200px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.25); overflow: hidden; display: none; z-index: 100; }
    .menu.show { display: block; }
    .menu-header { padding: 12px 14px; font-weight: bold; background: #f2f5f8; }
    .menu-item a { display: block; width: 100%; text-align: left; padding: 12px 14px; color: #222; text-decoration: none; }
    .menu-item a:hover { background: #eef3f9; }

    .download-btn { background-color: rgba(255, 255, 255, 0.15); padding: 10px; border-radius: 12px; cursor: pointer; font-size: 20px; color: white; transition: background-color 0.3s; user-select: none; margin-top: 20px; }
    .download-btn:hover { background-color: #279fbb; }

    table { width: 100%; border-collapse: collapse; border-radius: 12px; overflow: hidden; background-color: rgba(0, 0, 0, 0.2); }
    th, td { padding: 14px 16px; text-align: left; color: #fff; }
    th { font-weight: bold; }
    tr:nth-child(even) td { background-color: rgba(255, 255, 255, 0.07); }
  </style>
</head>
<body>
  <div class="sidebar">
    <img src="<?= htmlspecialchars(BASE_URL) ?>/assets/icons/sklogo.png" alt="SK Logo" class="logo" />
    <a class="nav-item" href="<?= htmlspecialchars(USER_URL) ?>/dashboard.php">📊 Dashboard</a>

    <div class="nav-item">🗂️ Proposals ▾</div>
    <div class="sub-links">
      <a href="<?= htmlspecialchars(USER_URL) ?>/programs.php">Programs</a>
      <a href="<?= htmlspecialchars(USER_URL) ?>/projects.php">Project Proposal</a>
      <a href="<?= htmlspecialchars(USER_URL) ?>/activities.php">Activities</a>
      <a href="<?= htmlspecialchars(USER_URL) ?>/annualplan.php">Annual Budget</a>
    </div>

    <a class="nav-item" href="<?= htmlspecialchars(USER_URL) ?>/templates.php">📄 Document Templates</a>
    <a class="nav-item active" href="<?= htmlspecialchars(USER_URL) ?>/reports.php">📑 Reports</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <form class="search-bar" method="get" action="">
        🔍 <input id="q" type="text" name="q" placeholder="Search title, type, status, prepared by, barangay..." value="<?= htmlspecialchars($q) ?>" />
      </form>

      <div class="user-menu" id="userMenu">
        <div style="display:flex; gap:15px; align-items:center;">
          <div class="user-btn" id="userBtn" aria-haspopup="true" aria-expanded="false">
            🔔
            <div class="user-icon">👤</div>
            <span class="caret">▾</span>
          </div>
          <div class="menu" id="menu">
            <div class="menu-header">@<?= htmlspecialchars($username) ?></div>
            <div class="menu-item"><a href="<?= htmlspecialchars(PUBLIC_URL) ?>/logout.php">Logout</a></div>
          </div>
        </div>
        <div class="download-btn" id="dlBtn" title="Download CSV">⬇️</div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Type</th>
          <th>Barangay</th>
          <th>Status</th>
          <th>Prepared by</th>
          <th>Budgetary Requirement</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" style="text-align:center; opacity:.9;">No reports found.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= ($i + 1) . '.' ?></td>
              <td><?= htmlspecialchars($r['title'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['type'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['barangay'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['status'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['prepared_by'] ?? '') ?></td>
              <td>₱<?= number_format((float)($r['budget'] ?? 0), 2) ?></td>
              <td><?= !empty($r['created_at']) ? htmlspecialchars(date('F j, Y', strtotime((string)$r['created_at']))) : '' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    // Download current view as CSV
    (function () {
      const dlBtn = document.getElementById('dlBtn');
      const q     = document.getElementById('q');
      if (!dlBtn) return;
      dlBtn.addEventListener('click', () => {
        const qs = new URLSearchParams(window.location.search);
        if (q && q.value) qs.set('q', q.value);
        qs.set('download', '1');
        window.location = 'reports.php?' + qs.toString();
      });
    })();

    // User dropdown
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
