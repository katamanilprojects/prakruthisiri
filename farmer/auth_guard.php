<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Farmer Authentication Guard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('farmer_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

if (empty($_SESSION['farmer_id']) || ($_SESSION['farmer_role'] ?? '') !== 'farmer') {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
    header('Location: login.php?redirect=' . $redirect);
    exit;
}
