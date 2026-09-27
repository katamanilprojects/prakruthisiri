<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/TimeWindow.php';
require_once __DIR__ . '/../src/ConfigService.php';
require_once __DIR__ . '/../src/RunInventoryService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\TimeWindow;
use PrakruthiSiri\ConfigService;
use PrakruthiSiri\RunInventoryService;

$pdo = Database::getInstance()->getConnection();
$configService = new ConfigService($pdo);
$runInventoryService = new RunInventoryService($pdo);
$now = TimeWindow::now();

$regions = ['Hanamkonda', 'Warangal'];
$regionSchedules = [];

// REQ-LOC-01/02: Enrich every region schedule with status metadata.
// No default region is pre-selected server-side; JS picks it from localStorage
// or shows the 1-tap region selector modal on first visit.
foreach ($regions as $reg) {
    $sched = $runInventoryService->getActiveScheduleForRegion($reg, $now);
    $status = 'OPEN';
    if (!$sched) {
        $sched = $runInventoryService->getNextUpcomingScheduleForRegion($reg, $now);
        $status = 'UPCOMING';
    }
    if ($sched) {
        $bookedCount = $runInventoryService->getBookedOrdersCount((int)$sched['id']);
        $sched['status_mode']       = $status;
        $sched['booked_orders_count'] = $bookedCount;
        $sched['max_orders_limit']  = 30;
        $sched['is_batch_full']     = ($bookedCount >= 30);
        $sched['cutoff_fmt']        = !empty($sched['cutoff_datetime'])
            ? date('D, d M - h:i A', strtotime($sched['cutoff_datetime'])) : '';
        $sched['open_fmt']          = !empty($sched['order_open_datetime'])
            ? date('D, d M - h:i A', strtotime($sched['order_open_datetime'])) : '';
        $sched['delivery_fmt']      = !empty($sched['delivery_date'])
            ? date('l, d M Y', strtotime($sched['delivery_date'])) : '';
    }
    $regionSchedules[$reg] = $sched;
}

// Catalog is empty on initial page load; populated via switchRegion() AJAX
// after the customer selects their region in the modal.
$activeSchedule = null;
$initialCatalog = [];

$movThreshold  = $configService->getMovThreshold();
$deliveryFee   = $configService->getStandardDeliveryFee();
$storeWhatsApp = $configService->getStoreWhatsAppNumber();

require __DIR__ . '/views/storefront.view.php';
