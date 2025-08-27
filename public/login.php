<?php
// public/login.php
session_start();
if (!empty($_SESSION['user_id'])) {
    header("Location: /capstone_website/public/index.php");
    exit();
}

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = "Invalid request. Please try again.";
    } else {
        [$ok, $msg] = auth_login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
        if ($ok) {
            require_once __DIR__ . '/../helpers/auth.php';
            redirect_by_role();
        }
        $error = $msg ?: "Login failed.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            display: grid;
            place-items: center;
            height: 100vh;
        }

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