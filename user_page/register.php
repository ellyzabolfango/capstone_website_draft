<?php
// =================== DATABASE CONNECTION SETUP ===================

// Database configuration
$host = "localhost";
$db = "capstone_db";
$user = "root";
$password = "";

// Message placeholders for feedback
$success = "";
$error = "";

// Connect to MySQL server
$conn = new mysqli($host, $user, $password);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS $db");

// Select the database
$conn->select_db($db);

// Create the users table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    fullname VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    location VARCHAR(100),
    position VARCHAR(100),
    password VARCHAR(255)
)");

// =================== FORM SUBMISSION HANDLING ===================

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Retrieve form inputs
  $username = $_POST['username'];
  $fullname = $_POST['fullname'];
  $email = $_POST['email'];
  $location = $_POST['location'];
  $position = $_POST['position'];
  $plainPassword = $_POST['password'];

  // Hash the password for security
  $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

  // Check if the username or email already exists
  $check = $conn->prepare("SELECT * FROM users WHERE username=? OR email=?");
  $check->bind_param("ss", $username, $email);
  $check->execute();
  $result = $check->get_result();

  if ($result->num_rows > 0) {
    // If duplicate found
    $error = "Username or Email already exists.";
  } else {
    // Insert user data into the users table
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, location, position, username, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $fullname, $email, $location, $position, $username, $hashedPassword);

    // Execute insertion and show success or error
    if ($stmt->execute()) {
      $success = "Registration successful! Redirecting to login...";
      header("refresh:3; url=login.php"); // Redirect after 3 seconds
      exit;
    } else {
      $error = "Registration failed. Try again.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register</title>
  <style>
    /* =================== PAGE STYLING =================== */
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
    }

    .login-title {
      text-align: center;
      font-size: 1.5rem;
      font-weight: bold;
      margin-bottom: 10px;
      color: white;
    }

    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
    }

    .login-box {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 30px 50px;
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
      display: flex;
      flex-direction: column;
      align-items: center;
      color: white;
    }

    .logo {
      width: 80px;
      margin-bottom: 20px;
      background-color: rgba(255, 255, 255, 0.4);
      padding: 10px;
      border-radius: 50%;
    }

    .input {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
    }

    .sign-in-button {
      background-color: #000d3d;
      color: white;
      border: none;
      padding: 12px;
      margin-top: 10px;
      width: 100%;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .sign-in-button:hover {
      background-color: #1a1a5c;
    }

    .message {
      margin: 10px 0;
      padding: 10px;
      border-radius: 5px;
      width: 100%;
      text-align: center;
    }

    .success {
      background-color: #006400;
      color: white;
    }

    .error {
      background-color: #8b0000;
      color: white;
    }
  </style>
</head>

<body>
  <!-- =================== REGISTRATION FORM UI =================== -->
  <h2 class="login-title">Register</h2>

  <div class="login-container">
    <form class="login-box" method="POST" action="">
      <!-- Logo image -->
      <img src="sklogo.png" alt="Logo" class="logo" />

      <!-- Display success or error messages -->
      <?php if ($success): ?>
        <div class="message success"><?php echo $success; ?></div>
      <?php elseif ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
      <?php endif; ?>

      <!-- Input fields for registration -->
      <input type="text" name="fullname" placeholder="Full Name" class="input" required />
      <input type="email" name="email" placeholder="Email" class="input" required />
      <input type="text" name="location" placeholder="Location" class="input" required />
      <input type="text" name="position" placeholder="Position" class="input" required />
      <input type="text" name="username" placeholder="Username" class="input" required />
      <input type="password" name="password" placeholder="Password" class="input" required />

      <!-- Submit button -->
      <button type="submit" class="sign-in-button">Register</button>
    </form>
  </div>
</body>

</html>