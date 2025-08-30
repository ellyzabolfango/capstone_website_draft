<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../../config/db.php';

function peso($n){ return '₱' . number_format((float)$n, 0); }

// --- inputs ---
$q       = trim($_GET['q'] ?? '');
$export  = isset($_GET['export']) && $_GET['export'] === 'csv';

// --- user location (to filter) ---
$userId  = (int)($_SESSION['user_id'] ?? 0);
$stmtU   = $conn->prepare("SELECT COALESCE(location,'') AS loc, COALESCE(fullname, username, '') AS name FROM users WHERE id=?");
$stmtU->bind_param('i', $userId);
$stmtU->execute();
$urow    = $stmtU->get_result()->fetch_assoc() ?: [];
$userLoc = $urow['loc'] ?? '';

// --- detect if a dedicated 'reports' table exists ---
$hasReportsTbl = $conn->query("SHOW TABLES LIKE 'reports'")->num_rows > 0;

// --- build query (same-location only) ---
$params = [];
$types  = '';

if ($hasReportsTbl) {
  // Prefer dedicated reports table if present
  $sql = "SELECT
            r.id,
            r.title,
            COALESCE(r.type, '') AS type,
            COALESCE(r.barangay, r.location, r.source, '') AS barangay,
            COALESCE(r.status, '') AS status,
            COALESCE(r.prepared_by, '') AS prepared_by,
            COALESCE(r.budget, 0) AS budget,
            COALESCE(r.date, r.created_at, r.updated_at, NOW()) AS dt
          FROM reports r
          WHERE COALESCE(r.barangay, r.location, r.source, '') = ?";
  $params[] = $userLoc; $types .= 's';
  if ($q !== '') {
    $sql .= " AND (r.title LIKE ? OR r.type LIKE ? OR r.status LIKE ? OR r.prepared_by LIKE ?)";
    $like = "%{$q}%"; $params[]=$like; $params[]=$like; $params[]=$like; $params[]=$like; $types.='ssss';
  }
  $sql .= " ORDER BY dt DESC, r.id DESC";
} else {
  // Fallback: derive "reports" from proposals (e.g., completed ones)
  $sql = "SELECT
            p.id,
            p.title,
            COALESCE(p.type,'') AS type,
            COALESCE(p.barangay, p.location, p.source, '') AS barangay,
            COALESCE(p.status,'') AS status,
            COALESCE(u.fullname, u.username, CONCAT('User #', p.submitted_by)) AS prepared_by,
            COALESCE(p.budget, 0) AS budget,
            COALESCE(p.implementation_completion_date, p.submitted_at, p.created_at, NOW()) AS dt
          FROM proposals p
          LEFT JOIN users u ON u.id = p.submitted_by
          WHERE COALESCE(p.barangay, p.location, p.source, '') = ?";
  $params[] = $userLoc; $types .= 's';
  // If you only want completed items here, uncomment the next line:
  // $sql .= " AND UPPER(COALESCE(p.status,'')) = 'COMPLETED'";
  if ($q !== '') {
    $sql .= " AND (p.title LIKE ? OR p.type LIKE ? OR p.status LIKE ? OR COALESCE(u.fullname, u.username, '') LIKE ?)";
    $like = "%{$q}%"; $params[]=$like; $params[]=$like; $params[]=$like; $params[]=$like; $types.='ssss';
  }
  $sql .= " ORDER BY dt DESC, p.id DESC";
}

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- CSV export (keeps same filters) ---
if ($export) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=reports_' . date('Ymd_His') . '.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['#','Title','Type','Barangay','Status','Prepared by','Budgetary Requirement','Date']);
  $i=1;
  foreach ($rows as $r) {
    fputcsv($out, [
      $i++,
      $r['title'],
      $r['type'],
      $r['barangay'],
      $r['status'],
      $r['prepared_by'],
      (string) $r['budget'],
      $r['dt'] ? date('F j, Y', strtotime($r['dt'])) : ''
    ]);
  }
  fclose($out);
  exit;
}

// static for now (you can compute this like on other pages)
$fundRemaining = "40,000";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { display: flex; font-family: Arial, sans-serif; height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff); color: #fff; }
    .sidebar { width: 250px; background: #2e4b4f; padding: 20px 0; display:flex; flex-direction:column; align-items:center; position:relative; z-index:10; }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar .label { font-size:13px; font-weight:bold; text-align:center; color:#dff2ff; text-shadow:1px 1px 2px #000; margin-bottom:25px; line-height:1.3; }
    .sidebar a { color:#fff; text-decoration:none; padding:10px 15px; margin:6px 0; border-radius:8px; width:90%; display:flex; align-items:center; gap:10px; font-weight:bold; }
    .sidebar a.active { background-color:#2ec8b5; color:#fff; }
    .main { flex:1; padding:30px; overflow-y:auto; }
    .top-bar { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; }
    .search-bar { background-color:rgba(255,255,255,0.2); border-radius:25px; padding:10px 20px; display:flex; align-items:center; gap:10px; width:350px; color:#fff; }
    .search-bar input { background:transparent; border:none; color:#fff; outline:none; width:100%; }
    .top-right { display:flex; flex-direction:column; align-items:flex-end; gap:10px; }
    .icon-group { display:flex; gap:15px; align-items:center; }
    .bell-icon { font-size:24px; }
    .user-icon { width:36px; height:36px; border-radius:50%; background:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; color:#333; box-shadow:0 2px 5px rgba(0,0,0,0.3); }
    .download-btn { background-color:rgba(255,255,255,0.15); padding:10px; border-radius:12px; cursor:pointer; font-size:20px; color:#fff; transition:background-color .3s; user-select:none; margin-top:20px; }
    .download-btn:hover { background-color:#279fbb; }
    table { width:100%; border-collapse:collapse; border-radius:12px; overflow:hidden; background-color:rgba(0,0,0,0.2); }
    th, td { padding:14px 16px; text-align:left; color:#fff; }
    th { font-weight:bold; }
    tr:nth-child(even) td { background-color:rgba(255,255,255,0.1); }
  </style>
</head>
<body>
  <div class="sidebar">
    <img src="sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="admin_pov.php"> 📊 Dashboard</a>
    <a href="proposals.php">📁 Proposals</a>
    <a href="templates.php">📄 Document Templates</a>
    <a href="reports.php" class="active">📑 Reports</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <form class="search-bar" method="get" action="">
        🔍 <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" />
      </form>
      <div class="top-right">
        <div class="icon-group">
          <div class="bell-icon">🔔</div>
          <div class="user-icon">👤</div>
        </div>
        <!-- Download keeps current search filter -->
        <a class="download-btn" title="Download" href="?export=csv<?= $q!=='' ? '&q='.urlencode($q):'' ?>">⬇️</a>
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
        <?php if (!$userLoc): ?>
          <tr><td colspan="8">No location set on your account. Ask an admin to add your location to see reports.</td></tr>
        <?php elseif (!$rows): ?>
          <tr><td colspan="8">No reports found<?= $q? ' for “'.htmlspecialchars($q, ENT_QUOTES).'”':''; ?> in <?= htmlspecialchars($userLoc, ENT_QUOTES) ?>.</td></tr>
        <?php else: $i=1; foreach ($rows as $r): ?>
          <tr>
            <td><?= $i++ ?>.</td>
            <td><?= htmlspecialchars($r['title'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['type'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['barangay'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['prepared_by'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars(peso($r['budget'] ?? 0), ENT_QUOTES) ?></td>
            <td><?= !empty($r['dt']) ? htmlspecialchars(date('F j, Y', strtotime($r['dt'])), ENT_QUOTES) : '' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
