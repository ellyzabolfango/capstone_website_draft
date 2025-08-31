<?php
// ---------------------------
// LOGIN PAGE
// ---------------------------

// Start or resume the session (used to track logged-in user)
session_start();

// loads DB, CSRF, auth, constants
require_once __DIR__ . '/../bootstrap.php'; 

// Variable to hold error message (if login fails)
$login_error = '';

// Check if the login form was submitted (via POST method)
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  // Get the input values from the form
  $username = trim($_POST['username'] ?? '');   // username entered
  $passwordInput = $_POST['password'] ?? '';    // password entered

  // Look for the user in the database by username
  $stmt = db()->prepare("SELECT id, username, password, role, is_active FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  // If a user record is found
  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Check if the account is active
    if ((int) $user['is_active'] !== 1) {
      $login_error = "Account is inactive.";  // message if user is blocked
    }
    // Verify the password entered matches the hashed password in the DB
    elseif (password_verify($passwordInput, $user['password'])) {

      // ✅ Login success
      session_regenerate_id(true); // creates a new session ID (extra security)
      $_SESSION['user_id'] = $user['id'];       // store user id
      $_SESSION['username'] = $user['username']; // store username
      $_SESSION['role'] = $user['role'];     // store role (admin/user)

      // Redirect to homepage (can adjust to dashboard later)
      header("Location: index.php");
      exit;
    }
    // Wrong password
    else {
      $login_error = "Invalid username or password.";
    }
  }
  // No user found with that username
  else {
    $login_error = "Invalid username or password.";
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
    <!-- Login Title -->
    <div class="login-title">Login</div>

    <!-- Login Form -->
    <form class="login-box" method="POST" action="">
      <!-- Logo -->
      <img src= "<?= htmlspecialchars(BASE_URL) ?>/assets/icons/sklogo.png" alt="Logo" class="logo" />

      <!-- Error message if login fails -->
      <?php if (!empty($login_error)): ?>
        <div class="error-message"><?= htmlspecialchars($login_error) ?></div>
      <?php endif; ?>

      <!-- Username field -->
      <input type="text" name="username" placeholder="Username" class="input" required />

      <!-- Password field -->
      <input type="password" name="password" placeholder="Password" class="input" required />

      <!-- Submit button -->
      <button type="submit" class="sign-in-button">Sign in</button>
    </form>
  </div>
</body>

</html>