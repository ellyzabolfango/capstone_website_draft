<?php
/**
 * helpers/auth.php
 *
 * Authentication helpers:
 *   - auth_login(): verify credentials, start session, set role
 *   - auth_logout(): clear session & cookies
 *   - auth_required(): guard routes (redirect to login if unauthenticated)
 *   - is_admin(), current_user_id(), current_role(): convenience getters
 *   - require_role(): guard routes by allowed roles
 *   - redirect_by_role(): send users to the correct dashboard
 *
 */

declare(strict_types=1);

// Ensure a session exists
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// -----------------------------------------------------------------------------
// Fallbacks (for test contexts where constants may not be defined)
// -----------------------------------------------------------------------------
// if (!defined('LOGIN_URL'))  define('LOGIN_URL', '/login.php');
// if (!defined('ADMIN_DASH')) define('ADMIN_DASH', '/views/admin/dashboard.php');
// if (!defined('USER_DASH'))  define('USER_DASH', '/views/user/dashboard.php');

/**
 * Attempt to authenticate a user by username/password.
 *
 * @param string $username
 * @param string $password
 * @return array{0: bool, 1:?string}  [success, errorMessageOrNull]
 */
function auth_login(string $username, string $password): array
{
    // Guard: empty inputs (cheap fail-fast)
    if ($username === '' || $password === '') {
        return [false, 'Invalid username or password.'];
    }

    // Look up user by username
    $stmt = db()->prepare(
        "SELECT id, username, password, role, is_active
         FROM users WHERE username = ? LIMIT 1"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res->fetch_assoc();

    // Validate account & password
    $active = $user && (int)$user['is_active'] === 1;
    $okPass = $active && password_verify($password, $user['password']);

    if (!$okPass) {
        // Generic message (don’t leak which part failed)
        return [false, "Invalid username or password."];
    }

    // (Optional) Rehash if algorithm parameters changed
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $newHash, $user['id']);
        $upd->execute();
    }

    // Harden session on privilege change/login
    session_regenerate_id(true);

    // Store minimal, safe user data in the session
    $_SESSION['user_id']  = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['role']     = (string)$user['role'];

    return [true, null];
}

/**
 * Log the user out and destroy their session.
 */
function auth_logout(): void
{
    // Clear session array
    $_SESSION = [];

    // Invalidate the session cookie
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'] ?? '/',
            $p['domain'] ?? '',
            (bool)($p['secure'] ?? false),
            (bool)($p['httponly'] ?? true)
        );
    }

    // Destroy session data
    session_destroy();
}

/**
 * Guard: require a logged-in user; otherwise redirect to the login page.
 */
function auth_required(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . LOGIN_URL);
        exit();
    }
}

/**
 * Returns true if the current user has the 'admin' role.
 */
function is_admin(): bool
{
    return (($_SESSION['role'] ?? 'user') === 'admin');
}

/**
 * Getters for current user context.
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_role(): string
{
    return (string)($_SESSION['role'] ?? 'user');
}

/**
 * Guard: require that the current user’s role is in the allowed list.
 *
 * @param string|string[] $roles
 */
function require_role($roles): void
{
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array(current_role(), $allowed, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit();
    }
}

/**
 * Redirect user to their appropriate dashboard based on role.
 */
function redirect_by_role(): void
{
    if (is_admin()) {
        header('Location: ' . ADMIN_DASH);
    } else {
        header('Location: ' . USER_DASH);
    }
    exit();
}
