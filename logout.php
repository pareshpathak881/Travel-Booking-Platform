<?php
declare(strict_types=1);
/**
 * IndiaYatra — Logout
 * Destroys the session and redirects to login.
 * No HTML output — pure action file.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Regenerate session ID before destroy to prevent session fixation
session_regenerate_id(true);

// Unset all session variables
$_SESSION = [];

// Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict',
        ]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;