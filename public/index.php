<?php
/**
 * /public/index.php
 *
 * Entry point for the system.
 * - Ensures user is authenticated
 * - Redirects based on role (admin → admin dashboard, user → user dashboard)
 */

declare(strict_types=1);

// -----------------------------------------------------
// Start session (needed for auth)
// -----------------------------------------------------

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// -----------------------------------------------------
// Bootstrap: loads DB, helpers, constants
// -----------------------------------------------------

require_once __DIR__ . '/../bootstrap.php';

// -----------------------------------------------------
// Force login if not authenticated
// -----------------------------------------------------

auth_required();

// -----------------------------------------------------
// If logged in, redirect by role
//   - Admins → ADMIN_DASH
//   - Users  → USER_DASH
// -----------------------------------------------------

redirect_by_role();
