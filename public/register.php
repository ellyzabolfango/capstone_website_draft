<?php
// public/register.php
// -------------------------------------------------------------------
// This page lets a new user create an account.
// It uses bootstrap.php so we get: DB connection (db()), CSRF helpers,
// and project-wide constants like BASE_URL / PUBLIC_URL.
// -------------------------------------------------------------------

require_once __DIR__ . '/../bootstrap.php'; // loads DB, CSRF, auth, constants

// These hold the feedback we show above the form
$success = '';
$error   = '';

// (Optional) Make sure the users table exists with all needed columns.
// Safe to run every load; it will do nothing if the table already exists.
db()->query("
  CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    fullname VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    location VARCHAR(100),
    position VARCHAR(100),
    role ENUM('admin','user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Handle the submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // 1) CSRF check (prevents forged form submissions)
  if (!csrf_check($_POST['csrf_token'] ?? null)) {
    $error = "Invalid request. Please reload and try again.";
  } else {
    // 2) Get inputs (trim text so accidental spaces don’t cause issues)
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_in  = $_POST['role'] ?? 'user';

    // Allow only 'admin' or 'user'. If anything else appears, fallback to 'user'
    $role = in_array($role_in, ['admin', 'user'], true) ? $role_in : 'user';

    // 3) Basic validations (simple, human-friendly checks)
    if ($fullname === '' || $email === '' || $location === '' || $position === '' || $username === '' || $password === '') {
      $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = "Please enter a valid email address.";
    } elseif (strlen($username) < 3) {
      $error = "Username must be at least 3 characters.";
    } elseif (strlen($password) < 6) {
      $error = "Password must be at least 6 characters.";
    } else {

      // 4) Check if username OR email already exists
      $stmt = db()->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
      $stmt->bind_param("ss", $username, $email);
      $stmt->execute();
      $exists = $stmt->get_result()->fetch_assoc();

      if ($exists) {
        $error = "Username or Email already exists.";
      } else {
        // 5) Hash the password (encrypt so we never store plain text)
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // 6) Insert the new user
        $stmt = db()->prepare("
          INSERT INTO users (username, fullname, email, location, position, role, password)
          VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssss", $username, $fullname, $email, $location, $position, $role, $hash);

        if ($stmt->execute()) {
          // Friendly success notice
          $success = "Registration successful! You can now sign in.";
          // Optional: auto-redirect to login after 2 seconds
          header("Refresh: 2; url=" . PUBLIC_URL . "/login.php");
        } else {
          $error = "Registration failed. Please try again.";
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Register</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <style>
    /* =================== PAGE STYLING =================== */
    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      font-family: Arial, sans-serif;
      color: #fff;
    }

    .login-title {
      text-align: center;
      font-size: 1.8rem;
      font-weight: bold;
      margin-bottom: 12px;
      color: #fff;
      text-shadow: 0 0 6px rgba(0,0,0,.4);
    }

    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      padding: 0 16px;
    }

    .login-box {
      background: rgba(255, 255, 255, 0.12);
      border-radius: 14px;
      padding: 28px 42px;
      backdrop-filter: blur(10px);
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
      display: flex;
      flex-direction: column;
      align-items: center;
      color: white;
      width: 100%;
      max-width: 420px;
    }

    /* Logo at the top */
    .logo {
      width: 90px;
      margin-bottom: 20px;
      filter: drop-shadow(0 0 5px white);
    }

    .message {
      margin: 8px 0 14px;
      padding: 10px 12px;
      border-radius: 6px;
      width: 100%;
      text-align: center;
      font-weight: bold;
    }
    .success { background-color: #0e6f2c; }
    .error { background-color: #8b0000; }

    .input {
      width: 100%;
      padding: 12px;
      margin: 8px 0;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      color: #222;
    }

    .muted { opacity: .9; font-size: .92rem; }

    .role-row { display: flex; gap: 18px; margin: 6px 0 2px; }

    .sign-in-button {
      background-color: #000d3d;
      color: white;
      border: none;
      padding: 12px;
      margin-top: 10px;
      width: 100%;
      border-radius: 10px;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.25s ease;
      font-weight: bold;
    }
    .sign-in-button:hover { background-color: #1a1a5c; }
    a { color: #fff; }
  </style>
</head>
<body>
  <!-- =================== REGISTRATION FORM UI =================== -->
  <h2 class="login-title">Register</h2>

  <div class="login-container">
    <form class="login-box" method="POST" action="">
      <!-- Logo (uses BASE_URL so path always works) -->
            <img src= "<?= htmlspecialchars(BASE_URL) ?>/assets/icons/sklogo.png" alt="Logo" class="logo" />

      <!-- Show success or error card (only when set) -->
      <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
      <?php elseif ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- CSRF hidden token (security against forged submits) -->
      <?= csrf_field(); ?>

      <!-- Input fields -->
      <input type="text" name="fullname" placeholder="Full Name" class="input" required />
      <input type="email" name="email" placeholder="Email" class="input" required />
      <input type="text" name="location" placeholder="Location" class="input" required />
      <input type="text" name="position" placeholder="Position" class="input" required />
      <input type="text" name="username" placeholder="Username" class="input" required />
      <input type="password" name="password" placeholder="Password" class="input" required />

      <br>

      <!-- Role selection -->
      <label class="muted">Select Role</label>
      <div class="role-row">
        <label><input type="radio" name="role" value="user" checked> User</label>
        <label><input type="radio" name="role" value="admin"> Admin</label>
      </div>

      <br>

      <!-- Submit -->
      <button type="submit" class="sign-in-button">Register</button>


      <p class="muted" style="margin-top:10px;">
        Have an account? <a href="<?= htmlspecialchars(PUBLIC_URL) ?>/login.php">Sign in</a>
      </p>
    </form>
  </div>
</body>
</html>
