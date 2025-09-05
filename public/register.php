<?php
/**
 * /public/register.php
 *
 * Registration page:
 *  - Shows a registration form with CSRF protection
 *  - Validates input (required fields, email format, username/password rules)
 *  - Ensures username/email are unique
 *  - Hashes password and inserts user
 *  - Redirects to login after success
 *
 * Note:
 *  - Table creation should live in migrations; a guard DDL is kept optional (commented).
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../bootstrap.php'; // db(), csrf_*, constants, etc.

$success = '';
$error   = '';

// Preserve previously entered values on error (NOT the password)
$old = [
  'fullname' => '',
  'email'    => '',
  'barangay' => '',
  'position' => '',
  'username' => '',
  'role'     => 'user',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = "Invalid request. Please reload and try again.";
    } else {
        // Gather inputs
        $fullname = trim((string)($_POST['fullname'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $barangay = trim((string)($_POST['barangay'] ?? ''));
        $position = trim((string)($_POST['position'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role_in  = (string)($_POST['role'] ?? 'user');

        // Save old values for re-render
        $old = [
          'fullname' => $fullname,
          'email'    => $email,
          'barangay' => $barangay,
          'position' => $position,
          'username' => $username,
          'role'     => in_array($role_in, ['admin','user'], true) ? $role_in : 'user',
        ];

        // Normalize fields
        $emailNorm    = mb_strtolower($email, 'UTF-8');
        $usernameNorm = mb_strtolower($username, 'UTF-8');
        $role         = in_array($role_in, ['admin','user'], true) ? $role_in : 'user';

        // Basic validations
        if (
            $fullname === '' || $emailNorm === '' || $barangay === '' ||
            $position === '' || $usernameNorm === '' || $password === ''
        ) {
            $error = "All fields are required.";
        } elseif (!filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (!preg_match('/^[a-z0-9_.-]{3,50}$/i', $username)) {
            // 3+ chars, letters/digits/._-
            $error = "Username must be at least 3 characters and use letters, numbers, dot, underscore, or hyphen only.";
        } elseif (mb_strlen($password, 'UTF-8') < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            // Check uniqueness (username/email)
            $stmt = db()->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->bind_param("ss", $usernameNorm, $emailNorm);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();

            if ($exists) {
                $error = "Username or Email already exists.";
            } else {
                // Hash password
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert
                $stmt = db()->prepare("
                    INSERT INTO users (username, fullname, email, barangay, position, role, password, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->bind_param(
                    "sssssss",
                    $usernameNorm, $fullname, $emailNorm, $barangay, $position, $role, $hash
                );

                if ($stmt->execute()) {
                    $success = "Registration successful! Redirecting to sign in…";
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
      margin: 0; padding: 0;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      font-family: Arial, sans-serif; color: #fff;
    }

    .login-title {
      text-align: center; font-size: 1.8rem; font-weight: bold; margin-bottom: 12px; color: #fff;
      text-shadow: 0 0 6px rgba(0,0,0,.4);
    }

    .login-container {
      display: flex; justify-content: center; align-items: center; width: 100%; padding: 0 16px;
    }

    .login-box {
      background: rgba(255, 255, 255, 0.12); border-radius: 14px; padding: 28px 42px;
      backdrop-filter: blur(10px);
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
      display: flex; flex-direction: column; align-items: center; color: white;
      width: 100%; max-width: 420px;
    }

    .logo {
      width: 90px; margin-bottom: 20px; filter: drop-shadow(0 0 5px white);
    }

    .message {
      margin: 8px 0 14px; padding: 10px 12px; border-radius: 6px; width: 100%;
      text-align: center; font-weight: bold;
    }
    .success { background-color: #0e6f2c; }
    .error   { background-color: #8b0000; }

    .input {
      width: 100%; padding: 12px; margin: 8px 0;
      border: none; border-radius: 8px; font-size: 1rem; color: #222;
    }

    .muted { opacity: .9; font-size: .92rem; }

    .role-row { display: flex; gap: 18px; margin: 6px 0 2px; }

    .sign-in-button {
      background-color: #000d3d; color: white; border: none; padding: 12px; margin-top: 10px;
      width: 100%; border-radius: 10px; font-size: 1rem; cursor: pointer; transition: background .25s ease; font-weight: bold;
    }
    .sign-in-button:hover { background-color: #1a1a5c; }

    a { color: #fff; }
  </style>
</head>
<body>
  <h2 class="login-title">Register</h2>

  <div class="login-container">
    <form class="login-box" method="POST" action="">
      <!-- Logo -->
      <img src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/icons/sklogo.png" alt="Logo" class="logo" />

      <!-- Messages -->
      <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
      <?php elseif ($error): ?>
        <div class="message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <!-- CSRF -->
      <?= csrf_field(); ?>

      <!-- Inputs -->
      <input type="text"     name="fullname" placeholder="Full Name" class="input" required
             value="<?= htmlspecialchars($old['fullname'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="name" />
      <input type="email"    name="email"    placeholder="Email"      class="input" required
             value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" />
      <input type="text"     name="barangay" placeholder="barangay"   class="input" required
             value="<?= htmlspecialchars($old['barangay'], ENT_QUOTES, 'UTF-8') ?>" />
      <input type="text"     name="position" placeholder="Position"   class="input" required
             value="<?= htmlspecialchars($old['position'], ENT_QUOTES, 'UTF-8') ?>" />
      <input type="text"     name="username" placeholder="Username"   class="input" required
             value="<?= htmlspecialchars($old['username'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" />
      <input type="password" name="password" placeholder="Password"   class="input" required autocomplete="new-password" />

      <br>

      <!-- Role selection -->
      <label class="muted">Select Role</label>
      <div class="role-row">
        <label><input type="radio" name="role" value="user"  <?= $old['role'] === 'user'  ? 'checked' : '' ?>> User</label>
        <label><input type="radio" name="role" value="admin" <?= $old['role'] === 'admin' ? 'checked' : '' ?>> Admin</label>
      </div>

      <br>

      <button type="submit" class="sign-in-button">Register</button>

      <p class="muted" style="margin-top:10px;">
        Have an account? <a href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8') ?>/login.php">Sign in</a>
      </p>
    </form>
  </div>
</body>
</html>
