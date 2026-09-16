<?php
declare(strict_types=1);

// Seamless inclusion of public order-success receipt page
if (file_exists(__DIR__ . '/public/order-success.php')) {
    require __DIR__ . '/public/order-success.php';
    exit;
}

header('Location: public/order-success.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
