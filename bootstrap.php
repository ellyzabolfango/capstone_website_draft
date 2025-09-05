<?php
/**
 * bootstrap.php
 *
 * Initializes core settings for the project:
 *   - Starts session (if not already active)
 *   - Defines project paths and URLs
 *   - Loads common helpers (DB, CSRF, Auth)
 */

// -------------------------------------------------
// Session handling
// -------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// -------------------------------------------------
// Path & URL constants
// -------------------------------------------------

// Absolute base path of the project (server-side filesystem)
define('BASE_PATH', __DIR__);

// Base URL (relative to your localhost or domain root)
// ⚠️ Adjust if the folder name changes (e.g., '/capstone')
define('BASE_URL', '/capstone_website_draft');

// Public-facing URL paths
define('PUBLIC_URL', BASE_URL . '/public');
define('ADMIN_URL',  BASE_URL . '/views/admin');
define('USER_URL',   BASE_URL . '/views/user');

// Common landing pages (for redirects after login, etc.)
define('ADMIN_DASH', ADMIN_URL . '/dashboard.php');
define('USER_DASH',  USER_URL . '/dashboard.php');
define('LOGIN_URL',  PUBLIC_URL . '/login.php');

// -------------------------------------------------
// Load dependencies
// -------------------------------------------------
require_once BASE_PATH . '/config/db.php';    // Database connection
require_once BASE_PATH . '/helpers/csrf.php'; // CSRF protection helper
require_once BASE_PATH . '/helpers/auth.php'; // Authentication helper
