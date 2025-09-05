<?php
// -----------
// LOGIN PAGE 
// -----------
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../bootstrap.php'; 

$login_error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // ⚠️ Add CSRF protection
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $login_error = "Invalid request (CSRF). Please refresh the page.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $passwordInput = $_POST['password'] ?? '';

        // ✅ Reuse auth_login() helper (less duplicate logic)
        [$ok, $err] = auth_login($username, $passwordInput);

        if ($ok) {
            // ✅ Redirect by role (admin → dashboard, user → dashboard)
            redirect_by_role();
        } else {
            $login_error = $err ?? "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login</title>
  <style>
    /* Reset some browser defaults */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    /* Full screen with gradient background */
    body {
      font-family: Arial, sans-serif;
      height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      display: flex;
      justify-content: center;
      align-items: center;
      color: #fff;
    }

    /* Wrapper to center content vertically */
    .container {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    /* Title above the form */
    .login-title {
      font-size: 2rem;
      font-weight: bold;
      margin-bottom: 20px;
      text-align: center;
    }

    /* Main login box */
    .login-box {
      background: rgba(20, 20, 20, 0.4);
      /* transparent black */
      backdrop-filter: blur(12px);
      /* glass effect */
      padding: 40px 30px;
      border-radius: 20px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
      /* soft shadow */
      width: 340px;
      text-align: center;
    }

    /* Logo at the top */
    .logo {
      width: 90px;
      margin-bottom: 20px;
      filter: drop-shadow(0 0 5px white);
    }

    /* Text and password inputs */
    .input {
      width: 100%;
      padding: 14px;
      margin: 12px 0;
      border: none;
      border-radius: 10px;
      background-color: #d8cdd3;
      color: #3e322e;
      font-size: 1rem;
    }

    /* Placeholder text color */
    .input::placeholder {
      color: #6b6b6b;
    }

    /* Sign-in button */
    .sign-in-button {
      background-color: #0a0a59;
      color: white;
      border: none;
      padding: 14px;
      width: 100%;
      border-radius: 30px;
      font-size: 1rem;
      margin-top: 15px;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    /* Button hover effect */
    .sign-in-button:hover {
      background-color: #00003f;
    }

    /* Error message (red background) */
    .error-message {
      color: white;
      background-color: #b0423c;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="login-title">Login</div>
    <form class="login-box" method="POST" action="">
      <img src="<?= htmlspecialchars(BASE_URL) ?>/assets/icons/sklogo.png" alt="Logo" class="logo" />

      <!-- Embed CSRF token -->
      <?= csrf_field() ?>

      <?php if (!empty($login_error)): ?>
        <div class="error-message"><?= htmlspecialchars($login_error) ?></div>
      <?php endif; ?>

      <input type="text" name="username" placeholder="Username" class="input" required />
      <input type="password" name="password" placeholder="Password" class="input" required />
      <button type="submit" class="sign-in-button">Sign in</button>
    </form>
  </div>
</body>

</html>