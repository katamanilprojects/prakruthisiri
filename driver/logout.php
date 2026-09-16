<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Driver Logout Controller
 * 
 * Securely terminates the driver session, clears driver session cookies across all
 * browser path scopes, and redirects to the driver PIN login interface.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('driver_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// 1. Unset all session superglobals
$_SESSION = [];

// 2. Invalidate session cookie in browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    
    // Clear with exact cookie parameters
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 86400,
            'path'     => $params['path'] ?: '/',
            'domain'   => $params['domain'] ?: '',
            'secure'   => $params['secure'] ?? false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );

    // Defensive fallback clearance across common path scopes
    setcookie(session_name(), '', time() - 86400, '/');
    setcookie(session_name(), '', time() - 86400, '');
    setcookie('PHPSESSID', '', time() - 86400, '/');
}

// 3. Destroy session data on server
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// 4. Redirect to driver login
header('Location: index.php');
exit;
