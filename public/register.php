<?php
// public/register.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/csrf.php';

$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf_token'] ?? null)) {
    $err = "Invalid request. Please reload and try again.";
  } else {
    // Collect inputs (trim basic strings)
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_in  = $_POST['role'] ?? 'user';  // 'admin' or 'user'

    // Sanitize role: accept only 'admin' or 'user'; default to 'user'
    $role = in_array($role_in, ['admin','user'], true) ? $role_in : 'user';

    // Basic validations
    if ($fullname === '' || $email === '' || $location === '' || $position === '' || $username === '' || $password === '') {
      $err = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = "Invalid email address.";
    } elseif (strlen($username) < 3) {
      $err = "Username must be at least 3 characters.";
    } elseif (strlen($password) < 6) {
      $err = "Password must be at least 6 characters.";
    } else {
      // Check if username OR email already exists
      $sql  = "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ss", $username, $email);
      $stmt->execute();
      $exists = $stmt->get_result()->fetch_assoc();

      if ($exists) {
        $err = "Username or Email already exists.";
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql  = "INSERT INTO users (username, fullname, email, location, position, role, password)
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $username, $fullname, $email, $location, $position, $role, $hash);

        if ($stmt->execute()) {
          $ok = "Account created as " . htmlspecialchars($role, ENT_QUOTES) . ". You can now log in.";
        } else {
          $err = "Registration failed. Please try again.";
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Register</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f7f7f7; display:grid; place-items:center; min-height:100vh; margin:0; }
    .card { width: 420px; max-width: 92vw; background:#fff; padding:22px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.08); }
    h1 { font-size:20px; margin:0 0 14px; }
    .field { margin-bottom:12px; display:flex; flex-direction:column; gap:6px; }
    .input { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
    .role-row { display:flex; gap:16px; align-items:center; margin:8px 0 4px; }
    button { width:100%; padding:10px; border:0; border-radius:8px; cursor:pointer; background:#2e4b4f; color:#fff; font-weight:bold; }
    .err { color:#b00020; margin-bottom:8px; font-size:14px; min-height:18px; }
    .ok  { color:#006400; margin-bottom:8px; font-size:14px; min-height:18px; }
    .muted { color:#666; font-size:12px; }
    a { color:#2e4b4f; text-decoration:none; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Create account</h1>
    <div class="ok"><?= htmlspecialchars($ok, ENT_QUOTES) ?></div>
    <div class="err"><?= htmlspecialchars($err, ENT_QUOTES) ?></div>

    <form method="post" autocomplete="off">
      <?= csrf_field(); ?>

      <!-- Your draft fields -->
      <div class="field">
        <input type="text" name="fullname" placeholder="Full Name" class="input" required />
      </div>
      <div class="field">
        <input type="email" name="email" placeholder="Email" class="input" required />
      </div>
      <div class="field">
        <input type="text" name="location" placeholder="Location" class="input" required />
      </div>
      <div class="field">
        <input type="text" name="position" placeholder="Position" class="input" required />
      </div>
      <div class="field">
        <input type="text" name="username" placeholder="Username" class="input" required />
      </div>
      <div class="field">
        <input type="password" name="password" placeholder="Password" class="input" required />
      </div>

      <div class="field">
        <label class="muted">Select Role</label>
        <div class="role-row">
          <label><input type="radio" name="role" value="user" checked> User</label>
          <label><input type="radio" name="role" value="admin"> Admin</label>
        </div>
      </div>

      <button type="submit">Register</button>
    </form>

    <p class="muted" style="margin-top:10px;">Have an account? <a href="/capstone_website_draft/public/login.php">Sign in</a></p>
  </div>
</body>
</html>
