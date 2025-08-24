<?php
session_start();

$login_error = '';

$host = "localhost";
$db = "capstone_db";
$user = "root";
$password = "";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = $_POST['username'] ?? '';
  $passwordInput = $_POST['password'] ?? '';

  if ($username === 'admin' && $passwordInput === '1234') {
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
    header("Location: index.php");
    exit;
  }

  $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if (password_verify($passwordInput, $user['password'])) {
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = 'user';
      header("Location: index.php");
      exit;
    } else {
      $login_error = "Invalid username or password.";
    }
  } else {
    $login_error = "Invalid username or password.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: Arial, sans-serif;
      height: 100vh;
      background: linear-gradient(to right, #4d2c3d, #3b4371, #00c6ff);
      display: flex;
      justify-content: center;
      align-items: center;
      color: #fff;
    }

    .container {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .login-title {
      font-size: 2rem;
      font-weight: bold;
      margin-bottom: 20px;
      text-align: center;
    }

    .login-box {
      background: rgba(20, 20, 20, 0.4);
      backdrop-filter: blur(12px);
      padding: 40px 30px;
      border-radius: 20px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
      width: 340px;
      text-align: center;
    }

    .logo {
      width: 90px;
      margin-bottom: 20px;
      filter: drop-shadow(0 0 5px white);
    }

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

    .input::placeholder {
      color: #6b6b6b;
    }

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

    .sign-in-button:hover {
      background-color: #00003f;
    }

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
      <img src="sklogo.png" alt="Logo" class="logo" />

      <?php if (!empty($login_error)): ?>
        <div class="error-message"><?php echo $login_error; ?></div>
      <?php endif; ?>

      <input type="text" name="username" placeholder="Username" class="input" required />
      <input type="password" name="password" placeholder="Password" class="input" required />
      <button type="submit" class="sign-in-button">Sign in</button>
    </form>
  </div>

</body>
</html>
