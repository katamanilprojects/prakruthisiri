<?php
declare(strict_types=1);

/**
 * Prakruthi Siri - Harvest Sheet Forwarder
 * Seamlessly forwards to unified Harvest & Packing portal in inventory.php
 */

require_once __DIR__ . '/auth_guard.php';

$query = $_GET;
if (!isset($query['tab'])) {
    $query['tab'] = 'harvest';
}

$targetUrl = 'inventory.php?' . http_build_query($query);
header('Location: ' . $targetUrl);
exit;
