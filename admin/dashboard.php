<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Dashboard Redirection to Daily Operations Hub
 */

require_once __DIR__ . '/auth_guard.php';

header('Location: daily-hub.php');
exit;
