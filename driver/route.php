<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Driver Route Manifest Controller
 * Validates driver authentication session, retrieves manifest data via DriverManifestService,
 * and renders presentation view.
 */

session_name('driver_session');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

if (empty($_SESSION['driver_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/TimeWindow.php';
require_once __DIR__ . '/../src/ConfigService.php';
require_once __DIR__ . '/../src/DriverManifestService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\DriverManifestService;

$driverId   = (int) $_SESSION['driver_id'];
$driverName = (string) $_SESSION['driver_name'];

try {
    $pdo = Database::getInstance()->getConnection();
    $manifestService = new DriverManifestService($pdo);

    $requestedScheduleId = isset($_GET['run_id']) && (int)$_GET['run_id'] > 0 ? (int)$_GET['run_id'] : null;
    $requestedDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? (string) $_GET['date'] : null;
    $selectedDate = $manifestService->resolveTargetDate($requestedDate);

    $manifest = $manifestService->getDriverManifest($driverId, $selectedDate, $requestedScheduleId);

    // Extract view variables
    $prevDate           = $manifest['prev_date'];
    $nextDate           = $manifest['next_date'];
    $selectedFormatted  = $manifest['selected_formatted'];
    $scheduleInfo       = $manifest['schedule_info'] ?? null;
    $orders             = $manifest['orders'];
    $totalStops         = $manifest['total_stops'];
    $deliveredStops     = $manifest['delivered_stops'];
    $hub                = $manifest['hub'];
    $firstPendingStop   = $manifest['first_pending_stop'];

    // Only reveal stops to the driver once the admin has approved and released the batch
    $isDispatched = ($scheduleInfo && in_array($scheduleInfo['status'] ?? '', ['dispatched', 'completed'], true));
    $isBatchReleased = $isDispatched;
    if (!$isDispatched) {
        $orders           = [];
        $totalStops       = 0;
        $deliveredStops   = 0;
        $firstPendingStop = null;
    }

    // Fetch upcoming runs for the run switcher
    $schedStmt = $pdo->query("
        SELECT `id`, `delivery_date`, `delivery_day`, `target_region` 
        FROM `delivery_schedules` 
        WHERE `delivery_date` >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        ORDER BY `delivery_date` ASC
    ");
    $availableRuns = $schedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (\Throwable $e) {
    error_log('Driver manifest error: ' . $e->getMessage());
    $selectedDate       = $requestedDate ?: date('Y-m-d');
    $prevDate           = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
    $nextDate           = date('Y-m-d', strtotime($selectedDate . ' +1 day'));
    $selectedFormatted  = date('l, d M Y', strtotime($selectedDate));
    $scheduleInfo       = null;
    $orders             = [];
    $totalStops         = 0;
    $deliveredStops     = 0;
    $hub                = null;
    $firstPendingStop   = null;
    $availableRuns      = [];
}

// Render Presentation View
require __DIR__ . '/views/route.view.php';
