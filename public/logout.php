<?php
/**
 * /public/logout.php
 *
 * Logs out the current user by:
 *   - Destroying the session (via auth_logout helper)
 *   - Redirecting to the login page
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php'; 

// End session and clear cookies
auth_logout();

// Redirect to login page
header("Location: " . LOGIN_URL);
exit;