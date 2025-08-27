<?php
// helpers/auth.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/db.php';

function auth_login(string $username, string $password): array {
  global $conn;

  $sql  = "SELECT id, username, password, role FROM users WHERE username = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();

  if (!$user || !password_verify($password, $user['password'])) {
    return [false, "Invalid username or password."];
  }

  $_SESSION['user_id']  = (int)$user['id'];
  $_SESSION['username'] = $user['username'];
  $_SESSION['role']     = $user['role'];
  return [true, null];
}

function auth_logout(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
}

function auth_required(): void {
  if (empty($_SESSION['user_id'])) {
    header("Location: /capstone_website/public/login.php");
    exit();
  }
}

function is_admin(): bool {
  return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function current_user_id(): ?int {
  return $_SESSION['user_id'] ?? null;
}

function current_role(): string {
  return $_SESSION['role'] ?? 'user';
}

function require_role($roles): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $roles = is_array($roles) ? $roles : [$roles];
  $ok = in_array(current_role(), $roles, true);
  if (!$ok) {
    http_response_code(403);
    echo "Forbidden";
    exit();
  }
}

function redirect_by_role(): void {
  $role = current_role();
  if ($role === 'admin') {
    header("Location: /capstone_website/views/admin/dashboard.php");
  } else {
    header("Location: /capstone_website/views/user/dashboard.php");
  }
  exit();
}