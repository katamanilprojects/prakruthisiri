<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Dashboard Legacy Redirection
 * Seamlessly redirects legacy dashboard requests to the Financials & Operations portal hub.
 */

require_once __DIR__ . '/auth_guard.php';

header('Location: revenue.php');
exit;
