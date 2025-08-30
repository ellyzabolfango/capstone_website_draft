<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/csrf.php';

// If already logged in, bounce to dashboard (change to your overview page)
if (!empty($_SESSION['user_id'])) {
  header("Location: /capstone_website_draft/public/index.php");
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify(); // 🔒 must be first on POST

  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($username === '' || $password === '') {
    $error = 'Please enter username and password.';
  } else {
    $stmt = db()->prepare("SELECT id, username, password, role, is_active FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user) {
      $error = 'Invalid credentials.';
    } elseif ((int)$user['is_active'] !== 1) {
      $error = 'Your account is inactive.';
    } elseif (!password_verify($password, $user['password'])) {
      $error = 'Invalid credentials.';
    } else {
      // ✅ success
      session_regenerate_id(true); // 🔒 prevent session fixation
      $_SESSION['user_id']  = (int)$user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role']     = $user['role'];

      // Optional: track last login
      // db()->query("UPDATE users SET last_login = NOW() WHERE id=".(int)$user['id']);

      // redirect per role (customize destinations)
      if ($user['role'] === 'admin') {
        header("Location: /capstone_website_draft/public/index.php");
      } else {
        header("Location: /capstone_website_draft/public/index.php");
      }
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>/* keep your existing styles; minimal here */ body{font-family:Arial;padding:20px;} .box{max-width:400px;margin:40px auto;padding:20px;border-radius:12px;background:#fff;color:#333} .field{margin:10px 0} .error{color:#b00020;margin:8px 0}</style>
</head>
<body>
  <div class="box">
    <h2>Sign in</h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label>Username</label><br>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="field">
        <label>Password</label><br>
        <input type="password" name="password" required>
      </div>
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>
.card {
            width: 340px;
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            font-size: 20px;
            margin: 0 0 14px;
        }

        .field {
            margin-bottom: 12px;
        }

        input[type=text],
        input[type=password] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        button {
            width: 100%;
            padding: 10px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            background: #2e4b4f;
            color: #fff;
            font-weight: bold;
        }

        .err {
            color: #b00020;
            margin-bottom: 8px;
            font-size: 14px;
            min-height: 18px;
        }

        .muted {
            margin-top: 10px;
            font-size: 13px;
            text-align: center;
        }

        a {
            color: #2e4b4f;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>Sign in</h1>
        <div class="err"><?= htmlspecialchars($error ?? '', ENT_QUOTES) ?></div>
        <form method="post">
            <?= csrf_field(); ?>
            <div class="field">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="field">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p class="muted">No account? <a href="/capstone_website/public/register.php">Create one</a></p>
    </div>
</body>


</html>
