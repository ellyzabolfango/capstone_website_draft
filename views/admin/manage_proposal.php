<?php
// views/manage_proposals.php  (admin view)
session_start();
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../config/db.php';
auth_required(); 
if (!is_admin()) { header("Location: ../index.php"); exit(); }


// --- inputs ---
$q  = trim($_GET['q'] ?? '');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- fetch proposals from DB (title, source/barangay, status, time) ---
$proposals = [];
$params = [];
$types  = '';
$where  = '';

if ($q !== '') {
  $where = "WHERE title LIKE ? OR COALESCE(source,'') LIKE ?";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like;
  $types   .= 'ss';
}

$sql = "
  SELECT
    id,
    title,
    COALESCE(source,'') AS source,
    COALESCE(status,'Pending') AS status,
    submitted_at,
    COALESCE(description,'') AS description
  FROM proposals
  $where
  ORDER BY submitted_at DESC, id DESC
";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
  $timeStr = $row['submitted_at'] ? date('g:iA', strtotime($row['submitted_at'])) : '';
  $proposals[] = [
    'id'          => (int)$row['id'],
    'title'       => $row['title'],
    'from'        => $row['source'],
    'status'      => $row['status'],
    'time'        => $timeStr,
    'secretary'   => $row['source'],          // placeholder to match your draft label
    'ppa'         => $row['title'],           // use title as PPA name
    'description' => $row['description'],
    'completion_date' => '',                  // not in schema (shows N/A below)
    'attachment'  => ''                       // not in schema (shows No file below)
  ];
}

// default selected proposal
$selected = null;
if (!empty($proposals)) {
  if ($id) {
    foreach ($proposals as $p) {
      if ($p['id'] === $id) { $selected = $p; break; }
    }
  }
  if ($selected === null) $selected = $proposals[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Proposals</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Arial, sans-serif;
      display: flex;
      height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      color: #000;
    }
    .sidebar {
      width: 250px; background: #2e4b4f; padding: 20px 0;
      display: flex; flex-direction: column; align-items: center;
    }
    .sidebar img.logo { width: 120px; margin-bottom: 10px; }
    .sidebar .label { font-size: 13px; font-weight: bold; text-align: center; color: #dff2ff; text-shadow: 1px 1px 2px #000; margin-bottom: 25px; line-height: 1.3; }
    .sidebar a {
      color: #fff; text-decoration: none; padding: 10px 15px; margin: 6px 0; border-radius: 8px;
      width: 90%; display: flex; align-items: center; gap: 10px; font-weight: bold;
    }
    .sidebar a.active { background-color: #2ec8b5; color: white; }

    .main { flex: 1; padding: 20px; display: flex; flex-direction: column; position: relative; color: #fff; }

    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }

    .search-bar {
      background: rgba(255, 255, 255, 0.2);
      border-radius: 30px; padding: 10px 20px; display: flex; align-items: center; gap: 10px;
      width: 400px; color: #fff;
    }
    .search-bar input {
      background: transparent; border: none; color: #fff; font-size: 16px; outline: none; width: 100%;
    }

    .content { display: flex; gap: 20px; flex: 1; margin-top: 20px; }
    .proposal-list { flex: 1; display: flex; flex-direction: column; gap: 15px; }

    .block-link { text-decoration: none; color: inherit; display: block; } /* keeps UI identical while making item clickable */

    .proposal-item {
      background: #d3d8e0; color: #000; padding: 15px 15px 25px 15px;
      border-radius: 6px; position: relative; font-weight: bold; border-left: 6px solid transparent;
    }
    .proposal-item .status {
      background: orange; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold;
      position: absolute; top: 15px; right: 15px;
    }
    .proposal-item small { font-weight: normal; font-size: 13px; display: block; color: #444; }
    .proposal-time { font-size: 11px; color: #333; text-align: right; margin-top: 5px; }

    .detail {
      flex: 1.3; background: #efefef; padding: 40px 50px; border-radius: 15px;
      display: flex; flex-direction: column; gap: 15px; justify-content: flex-start; position: relative; color: #000;
    }
    .detail h2 { text-align: center; font-weight: bold; font-size: 22px; margin-top: 10px; margin-bottom: 20px; }
    .detail label { font-weight: bold; display: inline-block; min-width: 260px; }
    .detail p { margin: 8px 0; font-size: 15px; }

    .file-box { display: inline-flex; align-items: center; background: white; padding: 5px 10px; border-radius: 6px; font-size: 13px; border: 1px solid #aaa; margin-left: 5px; }

    .update-btn { background: #06004d; color: white; padding: 10px 24px; border: none; border-radius: 10px; cursor: pointer; font-weight: bold; align-self: center; margin-top: 20px; }

    .date { position: absolute; bottom: 20px; right: 30px; font-size: 12px; color: #555; }

    .user-icon { width: 36px; height: 36px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #333; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="/public/assets/icons/sklogo.png" alt="SK Logo" class="logo" />
    <div class="label"></div>
    <a href="index.php"> 📊 Dashboard</a>
    <a href="manage_proposals.php" class="active">📁 Manage Proposals</a>
    <a href="user_management.php">👥 User Management</a>
    <a href="document_template.php">📄 Document Templates</a>
    <a href="reports.php">📑 Reports</a>
  </div>

  <!-- Main -->
  <div class="main">
    <!-- Top Bar -->
    <div class="top-bar">
      <form class="search-bar" method="get" action="">
        🔍
        <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
        ⚙️
      </form>
      <div class="icons">🔔 <div class="user-icon">👤</div></div>
    </div>

    <div class="content">
      <!-- Proposal List -->
      <div class="proposal-list">
        <?php if (empty($proposals)): ?>
          <div style="opacity:.9">No proposals found.</div>
        <?php else: ?>
          <?php foreach ($proposals as $p): ?>
            <a class="block-link" href="?<?= http_build_query(['q'=>$q,'id'=>$p['id']]) ?>">
              <div class="proposal-item">
                <div><?= htmlspecialchars($p['title'], ENT_QUOTES) ?></div>
                <small>From: <?= htmlspecialchars($p['from'], ENT_QUOTES) ?></small>
                <div class="status"><?= htmlspecialchars($p['status'], ENT_QUOTES) ?></div>
                <div class="proposal-time"><?= htmlspecialchars($p['time'], ENT_QUOTES) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Proposal Details -->
      <div class="detail">
        <?php if ($selected): ?>
          <p><label>SECRETARY:</label> From: <?= htmlspecialchars($selected['secretary'], ENT_QUOTES) ?></p>
          <h2>ACTIVITY PROPOSAL<br><small>NO.<?= (int)($selected['id']) ?></small></h2>
          <p><label>PPA:</label> <?= htmlspecialchars($selected['ppa'], ENT_QUOTES) ?></p>
          <p><label>DESCRIPTION:</label> <?= $selected['description'] !== '' ? htmlspecialchars($selected['description'], ENT_QUOTES) : '<i>(none)</i>' ?></p>
          <p><label>IMPLEMENTATION COMPLETION DATE:</label> <?= $selected['completion_date'] !== '' ? htmlspecialchars($selected['completion_date'], ENT_QUOTES) : 'N/A' ?></p>
          <p><label>FILE ATTACHMENT:</label>
            <?php if ($selected['attachment']): ?>
              <span class="file-box">📄 <?= htmlspecialchars($selected['attachment'], ENT_QUOTES) ?></span>
            <?php else: ?>
              <i>No file</i>
            <?php endif; ?>
          </p>
          <button class="update-btn" onclick="location.href='update_proposal_status.php?id=<?= (int)$selected['id'] ?>'">Update Status</button>
          <div class="date"><?= date("F j, Y") ?></div>
        <?php else: ?>
          <h2>ACTIVITY PROPOSAL</h2>
          <p>No proposal selected.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
