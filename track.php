<?php
declare(strict_types=1);

// Seamless inclusion of public order track page
if (file_exists(__DIR__ . '/public/track.php')) {
    require __DIR__ . '/public/track.php';
    exit;
}

header('Location: public/track.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
