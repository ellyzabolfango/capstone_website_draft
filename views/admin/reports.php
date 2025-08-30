<?php
// /views/admin/reports.php
session_start();
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
auth_required();
if (!is_admin()) { header("Location: ../index.php"); exit(); }

// ----- inputs -----
$q = trim($_GET['q'] ?? '');
$params = [];
$types  = '';
$where  = '';

if ($q !== '') {
  $where = "WHERE r.title LIKE ? OR r.type LIKE ? OR r.barangay LIKE ? OR r.status LIKE ? OR r.prepared_by LIKE ?";
  $like  = "%{$q}%";
  $params = [$like, $like, $like, $like, $like];
  $types  = 'sssss';
}

$sqlBase = "
  SELECT
    r.id,
    r.title,
    r.type,
    r.barangay,
    r.status,
    r.prepared_by,
    COALESCE(p.budget, r.budget_used, 0) AS budget,
    r.created_at
  FROM reports r
  LEFT JOIN proposals p ON p.id = r.proposal_id
  $where
  ORDER BY r.created_at DESC, r.id DESC
";

// ----- CSV download -----
if (isset($_GET['download'])) {
  $stmt = $conn->prepare($sqlBase);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=reports_'.date('Ymd_His').'.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['#','Title','Type','Barangay','Status','Prepared by','Budgetary Requirement','Date']);

  $i = 1;
  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      $i++,
      $row['title'],
      $row['type'],
      $row['barangay'],
      $row['status'],
      $row['prepared_by'],
      $row['budget'],
      date('F j, Y', strtotime($row['created_at'] ?? 'now')),
    ]);
  }
  fclose($out);
  exit();
}

// ----- fetch rows for page -----
$stmt = $conn->prepare($sqlBase);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reports</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      display: flex;
      font-family: Arial, sans-serif;
      height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      color: #fff;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      background: #2e4b4f;
      padding: 20px 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      z-index: 10;
    }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar .label {
      font-size: 13px; font-weight: bold; text-align: center; color: #dff2ff;
      text-shadow: 1px 1px 2px #000; margin-bottom: 25px; line-height: 1.3;
    }
    .sidebar a {
      color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0;
      border-radius: 8px; width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold;
    }
    .sidebar a.active { background-color: #2ec8b5; color: white; }

    /* Main */
    .main { flex: 1; padding: 30px; overflow-y: auto; }

    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .search-bar {
      background-color: rgba(255, 255, 255, 0.2);
      border-radius: 25px;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      width: 350px;
      color: #fff;
    }
    .search-bar input {
      background: transparent;
      border: none;
      color: #fff;
      outline: none;
      width: 100%;
    }

    .top-right {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 10px;
    }

    .icon-group {
      display: flex;
      gap: 15px;
      align-items: center;
    }
    .bell-icon { font-size: 24px; }
    .user-icon {
      width: 36px; height: 36px; border-radius: 50%; background: white;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }

    .download-btn {
      background-color: rgba(255, 255, 255, 0.15);
      padding: 10px;
      border-radius: 12px;
      cursor: pointer;
      font-size: 20px;
      color: white;
      transition: background-color 0.3s;
      user-select: none;
      margin-top: 20px;
    }
    .download-btn:hover { background-color: #279fbb; }

    table {
      width: 100%;
      border-collapse: collapse;
      border-radius: 12px;
      overflow: hidden;
      background-color: rgba(0, 0, 0, 0.2);
    }
    th, td { padding: 14px 16px; text-align: left; color: #fff; }
    th { font-weight: bold; }
    tr:nth-child(even) td { background-color: rgba(255, 255, 255, 0.1); }
  </style>
</head>
<body>
  <div class="sidebar">
    <img src="/public/assets/icons/sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="index.php">📊 Dashboard</a>
    <a href="manage_proposals.php">📁 Manage Proposals</a>
    <a href="user_management.php">👥 User Management</a>
    <a href="document_template.php">📄 Document Templates</a>
    <a href="reports.php" class="active">📑 Reports</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <!-- same look; just a form so search works -->
      <form class="search-bar" method="get" action="">
        🔍 <input id="q" type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" />
      </form>
      <div class="top-right">
        <div class="icon-group">
          <div class="bell-icon">🔔</div>
          <div class="user-icon">👤</div>
        </div>
        <div class="download-btn" id="dlBtn" title="Download">⬇️</div>
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
          <tr>
            <td colspan="8" style="text-align:center; opacity:.9;">No reports found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= ($i + 1) . '.' ?></td>
              <td><?= htmlspecialchars($r['title'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($r['type'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($r['barangay'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($r['status'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($r['prepared_by'], ENT_QUOTES) ?></td>
              <td>₱<?= number_format((float)$r['budget'], 2) ?></td>
              <td><?= htmlspecialchars(date('F j, Y', strtotime($r['created_at'] ?? 'now')), ENT_QUOTES) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    // Download current view as CSV
    const dlBtn = document.getElementById('dlBtn');
    const q     = document.getElementById('q');
    if (dlBtn) {
      dlBtn.addEventListener('click', () => {
        const qs = new URLSearchParams(window.location.search);
        if (q && q.value) qs.set('q', q.value);
        qs.set('download', '1');
        window.location = 'reports.php?' + qs.toString();
      });
    }
  </script>
</body>
</html>
