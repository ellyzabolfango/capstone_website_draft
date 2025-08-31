<?php
// helpers/auth.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../config/db.php';   // provides db()
require_once __DIR__ . '/csrf.php';           // optional here, but handy if you add CSRF to login later

// Adjust these to match your actual folder names once:
const BASE_URL   = '/capstone_website_draft';     // <<< change if your folder is different
const PUBLIC_URL = BASE_URL . '/public';
const ADMIN_DASH = BASE_URL . '/views/admin/dashboard.php'; // or /public/index.php if that’s your entry
const USER_DASH  = BASE_URL . '/views/user/dashboard.php';  // same note as above
const LOGIN_URL  = PUBLIC_URL . '/login.php';

function auth_login(string $username, string $password): array {
  $stmt = db()->prepare("SELECT id, username, password, role, is_active FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();

  if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password'])) {
    return [false, "Invalid username or password."];
  }

  // Harden session on login
  session_regenerate_id(true);
  $_SESSION['user_id']  = (int)$user['id'];
  $_SESSION['username'] = $user['username'];
  $_SESSION['role']     = $user['role'];

  return [true, null];
}

function auth_logout(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
  }
  session_destroy();
}

function auth_required(): void {
  if (empty($_SESSION['user_id'])) {
    header("Location: " . LOGIN_URL);
    exit();
  }
}

function is_admin(): bool {
  return ($_SESSION['role'] ?? 'user') === 'admin';
}

function current_user_id(): ?int { return $_SESSION['user_id'] ?? null; }
function current_role(): string  { return $_SESSION['role'] ?? 'user'; }

function require_role($roles): void {
  $roles = is_array($roles) ? $roles : [$roles];
  if (!in_array(current_role(), $roles, true)) {
    http_response_code(403);
    echo "Forbidden";
    exit();
  }
}

function redirect_by_role(): void {
  if (is_admin()) {
    header("Location: " . ADMIN_DASH);
  } else {
    header("Location: " . USER_DASH);
  }
  exit();
}
