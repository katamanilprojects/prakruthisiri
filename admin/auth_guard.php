<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Admin Authentication Guard
 * 
 * Enforces strict session-based authentication for administrative dashboard pages
 * and operations API endpoints.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('admin_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// Verify valid admin session exists
if (empty($_SESSION['admin_id'])) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isApi = str_contains($requestUri, '/api/') 
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Unauthorized. Valid administrative session required.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $loginUrl = 'login.php';
    if (!empty($requestUri) && !str_contains($requestUri, 'login.php')) {
        $loginUrl .= '?redirect=' . urlencode($requestUri);
    }

    header('Location: ' . $loginUrl);
    exit;
}
