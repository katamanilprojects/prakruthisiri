<?php
declare(strict_types=1);

// Seamless inclusion of public batch traceability page
if (file_exists(__DIR__ . '/public/batch.php')) {
    require __DIR__ . '/public/batch.php';
    exit;
}

header('Location: public/batch.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
