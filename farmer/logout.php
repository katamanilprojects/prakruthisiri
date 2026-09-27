<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_name('farmer_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
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
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: login.php');
exit;
