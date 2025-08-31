<?php
// bootstrap.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ----- Define paths -----
define('BASE_PATH', __DIR__);                         // Project root
define('BASE_URL', '/capstone_website_draft');        // Change if folder name changes
define('PUBLIC_URL', BASE_URL . '/public');
define('ADMIN_URL', BASE_URL . '/views/admin');
define('USER_URL', BASE_URL . '/views/user');

// Common landing pages
define('ADMIN_DASH', ADMIN_URL . '/dashboard.php');
define('USER_DASH', USER_URL . '/dashboard.php');
define('LOGIN_URL', PUBLIC_URL . '/login.php');

// ----- Includes -----
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/helpers/csrf.php';
require_once BASE_PATH . '/helpers/auth.php';
