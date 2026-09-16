<?php
declare(strict_types=1);

// Seamless inclusion of the public customer storefront (eliminates 302 redirect)
if (file_exists(__DIR__ . '/public/index.php')) {
    require __DIR__ . '/public/index.php';
    exit;
}

header('Location: public/', true, 301);
exit;