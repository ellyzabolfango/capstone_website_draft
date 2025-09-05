<?php
/**
 * helpers/csrf.php
 *
 * Provides CSRF protection helpers:
 *   - csrf_token():  Generate/retrieve the current session token
 *   - csrf_field():  Return a hidden <input> field for HTML forms
 *   - csrf_check():  Validate a submitted token against the session
 */

declare(strict_types=1);

// Ensure session is active (tokens live in $_SESSION)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Generate or return the existing CSRF token for this session.
 *
 * @return string  A 64-character hex string (32 bytes random)
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return a hidden input field with the CSRF token.
 * Useful for embedding inside <form> tags.
 *
 * @return string
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
           htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
           '">';
}

/**
 * Check whether the provided token matches the session token.
 *
 * @param ?string $token  Token from POST/GET form submission
 * @return bool
 */
function csrf_check(?string $token): bool
{
    return $token
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
