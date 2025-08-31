<?php
// /views/admin/user_management.php
session_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php'; // loads DB, CSRF, AUTH, constants

auth_required();
if (!is_admin()) {
  header("Location: " . PUBLIC_URL . "/index.php");
  exit();
}

// --- handle actions (activate/deactivate) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $ids    = array_filter(array_map('intval', $_POST['ids'] ?? []));
  if ($ids && in_array($action, ['activate','deactivate'], true)) {
    $flag = $action === 'activate' ? 1 : 0;
    // dynamic IN (...) placeholders
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id IN ($ph)");
    // bind param list: first 'i' for $flag then ids
    $bindTypes = 'i' . $types;
    $bindValues = array_merge([$flag], $ids);
    $stmt->bind_param($bindTypes, ...$bindValues);
    $stmt->execute();
  }
  // keep current search after POST
  $q = urlencode($_POST['q'] ?? '');
  header("Location: user_management.php?q={$q}");
  exit();
}

// --- search ---
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
$types = '';

if ($q !== '') {
  $where = "WHERE fullname LIKE ? OR username LIKE ? OR email LIKE ? OR location LIKE ? OR position LIKE ?";
  $like = "%{$q}%";
  $params = [$like,$like,$like,$like,$like];
  $types  = 'sssss';
}

// --- fetch users ---
$sql = "
  SELECT id, fullname, location, position, role,
         COALESCE(is_active, 1) as is_active
  FROM users
  $where
  ORDER BY fullname ASC
";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>User Management</title>
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
      width: 250px;
      background: #2e4b4f;
      padding: 20px 0;
      display: flex; flex-direction: column; align-items: center;
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

    .main { flex: 1; padding: 30px; display: flex; flex-direction: column; overflow-y: auto; }

    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .icons { display: flex; align-items: center; gap: 15px; font-size: 20px; }

    .search-bar {
      background: rgba(255, 255, 255, 0.2);
      border-radius: 30px; padding: 10px 20px;
      display: flex; align-items: center; gap: 10px;
      width: 400px; color: #fff;
    }
    .search-bar input {
      background: transparent; border: none; color: #fff;
      font-size: 16px; outline: none; width: 100%;
    }

    .user-icon {
      width: 36px; height: 36px; border-radius: 50%;
      background: white; display: flex; align-items: center; justify-content: center;
      font-size: 18px; color: #333;
    }

    .user-list-container { background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 15px; }

    .user-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
    .user-header-left { display: flex; align-items: center; gap: 10px; }
    .user-header-left input[type="checkbox"] { transform: scale(1.2); }
    .user-header-left button {
      padding: 6px 16px; border-radius: 10px; border: none;
      background: #e4e4e4; font-weight: bold; color: #000; cursor: pointer;
    }

    .user-row {
      background: #efefef; color: #000; border-radius: 10px;
      display: flex; align-items: center; justify-content: space-between;
      padding: 12px 16px; margin-bottom: 10px;
    }
    .user-left { display: flex; align-items: center; gap: 10px; }
    .user-left input[type="checkbox"] { transform: scale(1.2); }

    .user-info { display: flex; flex-direction: column; }
    .user-info .name { font-weight: bold; }
    .user-info .from { font-size: 13px; color: #444; }

    .user-position { font-weight: bold; font-size: 14px; }
    .status {
      background: #e3f9e6; color: #2ecc71; padding: 5px 12px; border-radius: 20px;
      font-size: 13px; font-weight: bold;
    }
    .status.inactive { background: #fde2e1; color: #e74c3c; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <img src="/public/assets/icons/sklogo.png" class="logo" alt="SK Logo" />
    <div class="label"></div>
    <a href="index.php">📊 Dashboard</a>
    <a href="manage_proposals.php">📁 Manage Proposals</a>
    <a href="user_management.php" class="active">👥 User Management</a>
    <a href="document_template.php">📄 Document Templates</a>
    <a href="reports.php">📑 Reports</a>
  </div>

  <!-- Main -->
  <div class="main">
    <div class="top-bar">
      <form class="search-bar" method="get" action="">
        🔍
        <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" />
        ⚙️
      </form>
      <div class="icons">🔔 <div class="user-icon">👤</div></div>
    </div>

    <div class="user-list-container">
      <form method="post" id="bulkForm">
        <input type="hidden" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" />
        <div class="user-header">
          <div class="user-header-left">
            <input type="checkbox" id="checkAll" />
            <button type="button" onclick="location.href='../../public/register.php'">+ New</button>
            <button type="submit" name="action" value="activate">Activate</button>
            <button type="submit" name="action" value="deactivate">Deactivate</button>
          </div>
        </div>

        <?php if (!$rows): ?>
          <div style="opacity:.9;color:#fff">No users found.</div>
        <?php else: ?>
          <?php foreach ($rows as $u): ?>
            <div class="user-row">
              <div class="user-left">
                <input type="checkbox" name="ids[]" value="<?= (int)$u['id'] ?>" class="rowCheck" />
                <div class="user-info">
                  <div class="name"><?= htmlspecialchars($u['fullname'], ENT_QUOTES) ?> (<?= htmlspecialchars($u['role'], ENT_QUOTES) ?>)</div>
                  <div class="from">From: <?= htmlspecialchars($u['location'], ENT_QUOTES) ?></div>
                </div>
              </div>
              <div class="user-position"><?= htmlspecialchars($u['position'], ENT_QUOTES) ?></div>
              <?php
                $active = (int)$u['is_active'] === 1;
                $cls = $active ? 'status' : 'status inactive';
                $txt = $active ? 'Activated' : 'Deactivated';
              ?>
              <div class="<?= $cls ?>"><?= $txt ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <script>
    // Select/Deselect all
    const checkAll = document.getElementById('checkAll');
    const checks = document.querySelectorAll('.rowCheck');
    if (checkAll) {
      checkAll.addEventListener('change', e => {
        checks.forEach(c => c.checked = e.target.checked);
      });
    }
  </script>
</body>
</html>
