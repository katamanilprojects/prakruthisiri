<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Delivery Run Calendar & Cutoff Settings REST API
 * 
 * Manages delivery schedules, automated cutoff datetimes, harvest dates,
 * emergency ordering pauses, and cutoff extensions.
 */

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/TimeWindow.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\TimeWindow;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::getInstance()->getConnection();

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '', true) ?: [];
    $data = array_merge($_GET, $_POST, $payload);

    $action = (string) ($data['action'] ?? 'get_schedules');

    switch ($action) {
        // --------------------------------------------------------------------
        // 1. Get Delivery Schedules with Booked Orders Count
        // --------------------------------------------------------------------
        case 'get_schedules':
            $stmt = $pdo->query("
                SELECT 
                    s.`id`,
                    s.`delivery_date`,
                    s.`delivery_day`,
                    s.`target_region`,
                    s.`order_open_datetime`,
                    s.`cutoff_datetime`,
                    s.`harvest_date`,
                    s.`is_ordering_open`,
                    s.`status`,
                    s.`created_at`,
                    s.`updated_at`,
                    COUNT(o.`id`) AS `orders_count`,
                    COALESCE(SUM(o.`total_amount`), 0.00) AS `orders_revenue`
                FROM `delivery_schedules` s
                LEFT JOIN `orders` o ON s.`delivery_date` = o.`target_delivery_date` AND o.`order_status` != 'cancelled'
                GROUP BY s.`id`, s.`delivery_date`, s.`delivery_day`, s.`target_region`, s.`order_open_datetime`, s.`cutoff_datetime`, s.`harvest_date`, s.`is_ordering_open`, s.`status`, s.`created_at`, s.`updated_at`
                ORDER BY s.`delivery_date` DESC
                LIMIT 50
            ");
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $tz = TimeWindow::getTimeZone();
            $now = TimeWindow::now();
            foreach ($schedules as &$sch) {
                $cutoff = !empty($sch['cutoff_datetime']) ? new DateTimeImmutable($sch['cutoff_datetime'], $tz) : null;
                $openDt = !empty($sch['order_open_datetime']) ? new DateTimeImmutable($sch['order_open_datetime'], $tz) : null;
                $isCutoffPassed = ($cutoff !== null && $cutoff <= $now);
                $isOpenWindow   = ($openDt === null || $openDt <= $now) && !$isCutoffPassed;
                $isEffectivelyOpen = ((int) $sch['is_ordering_open'] === 1) && $isOpenWindow;

                $sch['is_cutoff_passed']   = $isCutoffPassed;
                $sch['is_effectively_open'] = $isEffectivelyOpen;
                $sch['delivery_date_fmt']   = (new DateTimeImmutable($sch['delivery_date'] . ' 00:00:00', $tz))->format('l, d M Y');
                $sch['order_open_fmt']      = $openDt ? $openDt->format('d M Y, h:i A') : '-';
                $sch['cutoff_datetime_fmt'] = $cutoff ? $cutoff->format('d M Y, h:i A') : '-';
                $sch['harvest_date_fmt']    = !empty($sch['harvest_date']) ? (new DateTimeImmutable($sch['harvest_date'] . ' 00:00:00', $tz))->format('d M Y') : '-';
                $sch['orders_count']        = (int) $sch['orders_count'];
                $sch['orders_revenue_fmt']  = '₹' . number_format((float) $sch['orders_revenue'], 2);
            }
            unset($sch);

            echo json_encode(['success' => true, 'schedules' => $schedules], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 2. Save / Create Schedule (Day + Date + Target Locality)
        // --------------------------------------------------------------------
        case 'save_schedule':
            $deliveryDate = trim((string) ($data['delivery_date'] ?? ''));
            $targetRegion = trim((string) ($data['target_region'] ?? 'Hanamkonda'));
            $deliveryDay  = trim((string) ($data['delivery_day'] ?? ''));
            $orderOpenTime= trim((string) ($data['order_open_datetime'] ?? ''));
            $cutoffTime   = trim((string) ($data['cutoff_datetime'] ?? ''));
            $harvestDate  = trim((string) ($data['harvest_date'] ?? ''));
            $status       = trim((string) ($data['status'] ?? 'scheduled'));
            $isOpen       = isset($data['is_ordering_open']) ? (int) (bool) $data['is_ordering_open'] : 1;

            if ($deliveryDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryDate)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valid delivery_date (YYYY-MM-DD) is required.']);
                exit;
            }

            if (!in_array($targetRegion, ['Hanamkonda', 'Warangal'], true)) {
                $targetRegion = 'Hanamkonda';
            }

            $tz = TimeWindow::getTimeZone();
            $delDt = new DateTimeImmutable($deliveryDate . ' 00:00:00', $tz);

            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $cleanDay = trim(str_ireplace(['batch', 'బ్యాచ్'], '', $deliveryDay));
            if (!in_array($cleanDay, $validDays, true)) {
                $deliveryDay = $delDt->format('l');
            } else {
                $deliveryDay = $cleanDay;
            }

            // Defaults: Open at 5:00 AM on previous day, Cutoff at 7:00 PM (19:00 IST) on previous day
            if ($orderOpenTime === '') {
                $orderOpenTime = $delDt->modify('-1 day')->setTime(5, 0, 0)->format('Y-m-d H:i:s');
            } else {
                $orderOpenTime = str_replace('T', ' ', $orderOpenTime);
                if (strlen($orderOpenTime) === 16) $orderOpenTime .= ':00';
            }

            if ($cutoffTime === '') {
                $cutoffTime = $delDt->modify('-1 day')->setTime(19, 0, 0)->format('Y-m-d H:i:s');
            } else {
                $cutoffTime = str_replace('T', ' ', $cutoffTime);
                if (strlen($cutoffTime) === 16) $cutoffTime .= ':00';
            }

            if ($harvestDate === '') {
                $harvestDate = $delDt->modify('-1 day')->format('Y-m-d');
            }

            $validStatuses = ['scheduled', 'open', 'closed', 'dispatched', 'completed'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'scheduled';
            }

            $stmt = $pdo->prepare("
                INSERT INTO `delivery_schedules` 
                    (`delivery_date`, `delivery_day`, `target_region`, `order_open_datetime`, `cutoff_datetime`, `harvest_date`, `is_ordering_open`, `status`)
                VALUES 
                    (:del, :day, :reg, :open, :cut, :har, :is_open, :st)
                ON DUPLICATE KEY UPDATE
                    `delivery_day`        = VALUES(`delivery_day`),
                    `order_open_datetime` = VALUES(`order_open_datetime`),
                    `cutoff_datetime`     = VALUES(`cutoff_datetime`),
                    `harvest_date`        = VALUES(`harvest_date`),
                    `is_ordering_open`    = VALUES(`is_ordering_open`),
                    `status`              = VALUES(`status`),
                    `updated_at`          = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                ':del'     => $deliveryDate,
                ':day'     => $deliveryDay,
                ':reg'     => $targetRegion,
                ':open'    => $orderOpenTime,
                ':cut'     => $cutoffTime,
                ':har'     => $harvestDate,
                ':is_open' => $isOpen,
                ':st'      => $status,
            ]);

            // Sync with system_settings for target active delivery date
            $setStmt = $pdo->prepare("
                INSERT INTO `system_settings` (`setting_key`, `setting_value`)
                VALUES ('active_delivery_date', :val)
                ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
            ");
            $setStmt->execute([':val' => $deliveryDate]);

            echo json_encode([
                'success'       => true,
                'message'       => "Delivery batch for {$deliveryDay}, {$deliveryDate} ({$targetRegion}) scheduled successfully.",
                'delivery_date' => $deliveryDate,
                'delivery_day'  => $deliveryDay,
                'target_region' => $targetRegion,
                'cutoff_time'   => $cutoffTime,
                'harvest_date'  => $harvestDate,
                'is_open'       => $isOpen,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 3. Toggle Ordering Open/Closed
        // --------------------------------------------------------------------
        case 'toggle_ordering':
            $scheduleId = (int) ($data['schedule_id'] ?? 0);
            $deliveryDate = trim((string) ($data['delivery_date'] ?? ''));

            if ($scheduleId <= 0 && $deliveryDate === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Schedule ID or delivery date is required.']);
                exit;
            }

            if ($scheduleId > 0) {
                $sStmt = $pdo->prepare("SELECT `id`, `is_ordering_open`, `delivery_date` FROM `delivery_schedules` WHERE `id` = :id");
                $sStmt->execute([':id' => $scheduleId]);
            } else {
                $sStmt = $pdo->prepare("SELECT `id`, `is_ordering_open`, `delivery_date` FROM `delivery_schedules` WHERE `delivery_date` = :del");
                $sStmt->execute([':del' => $deliveryDate]);
            }
            $sch = $sStmt->fetch(PDO::FETCH_ASSOC);

            if (!$sch) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Delivery schedule record not found.']);
                exit;
            }

            $currentOpen = (int) $sch['is_ordering_open'];
            $newOpen = ($currentOpen === 1) ? 0 : 1;

            $uStmt = $pdo->prepare("
                UPDATE `delivery_schedules` 
                SET `is_ordering_open` = :open 
                WHERE `id` = :id
            ");
            $uStmt->execute([':open' => $newOpen, ':id' => (int) $sch['id']]);

            echo json_encode([
                'success'          => true,
                'message'          => ($newOpen === 1) ? "Ordering re-opened for {$sch['delivery_date']}." : "Ordering paused instantly for {$sch['delivery_date']}.",
                'is_ordering_open' => $newOpen,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 4. Emergency: Extend Cutoff by 1 Hour
        // --------------------------------------------------------------------
        case 'extend_cutoff':
            $scheduleId = (int) ($data['schedule_id'] ?? 0);
            $deliveryDate = trim((string) ($data['delivery_date'] ?? ''));
            $hoursToAdd = max(1, min(24, (int) ($data['hours'] ?? 1)));

            if ($scheduleId <= 0 && $deliveryDate === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Schedule ID or delivery date is required.']);
                exit;
            }

            if ($scheduleId > 0) {
                $sStmt = $pdo->prepare("SELECT `id`, `cutoff_datetime`, `delivery_date` FROM `delivery_schedules` WHERE `id` = :id");
                $sStmt->execute([':id' => $scheduleId]);
            } else {
                $sStmt = $pdo->prepare("SELECT `id`, `cutoff_datetime`, `delivery_date` FROM `delivery_schedules` WHERE `delivery_date` = :del");
                $sStmt->execute([':del' => $deliveryDate]);
            }
            $sch = $sStmt->fetch(PDO::FETCH_ASSOC);

            if (!$sch) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Delivery schedule record not found.']);
                exit;
            }

            $tz = TimeWindow::getTimeZone();
            $now = TimeWindow::now();
            $currentCutoff = !empty($sch['cutoff_datetime']) ? new DateTimeImmutable($sch['cutoff_datetime'], $tz) : $now;
            // If cutoff is already in the past, extend from now!
            $base = ($currentCutoff < $now) ? $now : $currentCutoff;
            $newCutoff = $base->modify("+{$hoursToAdd} hours");
            $newCutoffStr = $newCutoff->format('Y-m-d H:i:s');

            $uStmt = $pdo->prepare("
                UPDATE `delivery_schedules` 
                SET `cutoff_datetime` = :cut,
                    `is_ordering_open` = 1
                WHERE `id` = :id
            ");
            $uStmt->execute([':cut' => $newCutoffStr, ':id' => (int) $sch['id']]);

            echo json_encode([
                'success'             => true,
                'message'             => "Cutoff successfully extended by {$hoursToAdd} hour(s) to {$newCutoffStr}.",
                'new_cutoff_datetime' => $newCutoffStr,
                'new_cutoff_fmt'      => $newCutoff->format('d M Y, h:i A'),
            ], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action '{$action}'."]);
            exit;
    }

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Schedule API error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
