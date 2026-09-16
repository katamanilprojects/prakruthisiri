<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Route Dispatch & Multi-Leg TSP REST API
 * 
 * Endpoints for:
 *  - Real-Road TSP Closed-Loop Sequencing via OSRM
 *  - Auto-Partitioning into 8-Stop Legs for mobile navigation
 *  - Driver assignment (bulk per-leg or per-run)
 *  - Finalize & Dispatch transition (placed -> packed / out_for_delivery)
 *  - Farm / Store Hub Depot Location Updates
 */

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/ConfigService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\ConfigService;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::getInstance()->getConnection();

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '', true) ?: [];
    $data = array_merge($_GET, $_POST, $payload);

    $action = (string) ($data['action'] ?? 'optimize_route');

    $configService = new ConfigService($pdo);

    switch ($action) {
        // --------------------------------------------------------------------
        // 1. Update Farm / Store Hub Depot Location
        // --------------------------------------------------------------------
        case 'update_hub_location':
            $name    = trim((string) ($data['name'] ?? ''));
            $address = trim((string) ($data['address'] ?? ''));
            $lat     = (float) ($data['latitude'] ?? 0);
            $lng     = (float) ($data['longitude'] ?? 0);

            if ($name === '' || $lat <= 0 || $lng <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valid hub name and latitude/longitude coordinates are required.']);
                exit;
            }

            $configService->updateStoreHubLocation($name, $address, $lat, $lng);

            echo json_encode([
                'success' => true,
                'message' => 'Store / Farm Dispatch Hub location updated successfully.',
                'hub'     => $configService->getStoreHubLocation(),
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 2. Real-Road TSP Route Optimization (Hub -> Stops -> Hub)
        // --------------------------------------------------------------------
        case 'optimize_route':
            $targetDate = trim((string) ($data['target_date'] ?? date('Y-m-d')));
            $driverId   = isset($data['driver_id']) && is_numeric($data['driver_id']) ? (int) $data['driver_id'] : null;
            $region     = isset($data['region']) && trim((string) $data['region']) !== '' ? trim((string) $data['region']) : null;
            $scheduleId = isset($data['schedule_id']) && is_numeric($data['schedule_id']) && (int) $data['schedule_id'] > 0 ? (int) $data['schedule_id'] : null;

            $hub = $configService->getStoreHubLocation();

            // Query active orders for delivery batch (scoped by schedule_id if provided)
            if ($scheduleId !== null && $scheduleId > 0) {
                $where = ['(o.`schedule_id` = :sid OR (o.`schedule_id` IS NULL AND o.`target_delivery_date` = :tdate))', "o.`order_status` != 'cancelled'"];
                $params = [':sid' => $scheduleId, ':tdate' => $targetDate];
            } else {
                $where = ['o.`target_delivery_date` = :tdate', "o.`order_status` != 'cancelled'"];
                $params = [':tdate' => $targetDate];
            }

            if ($driverId !== null && $driverId > 0) {
                $where[] = 'o.`assigned_driver_id` = :driver_id';
                $params[':driver_id'] = $driverId;
            }

            if ($region !== null && $region !== 'All') {
                $where[] = 'c.`region` = :region';
                $params[':region'] = $region;
            }

            $sql = "
                SELECT 
                    o.`id` AS `order_id`,
                    o.`order_code`,
                    o.`customer_id`,
                    o.`assigned_driver_id`,
                    o.`route_sequence_number`,
                    o.`total_amount`,
                    c.`full_name` AS `customer_name`,
                    c.`phone_number` AS `customer_phone`,
                    c.`delivery_address`,
                    c.`landmark`,
                    c.`region`,
                    c.`latitude`,
                    c.`longitude`,
                    c.`is_location_verified`
                FROM `orders` o
                JOIN `customers` c ON o.`customer_id` = c.`id`
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(o.`route_sequence_number`, 9999) ASC, o.`id` ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (empty($orders)) {
                echo json_encode([
                    'success'       => true,
                    'message'       => 'No active orders found for this delivery date and filter.',
                    'total_stops'   => 0,
                    'ordered_stops' => [],
                    'hub'           => $hub,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Region centroid fallbacks for orders without exact GPS pin
            $regionCentroids = [
                'Hanamkonda' => ['lat' => 17.9856, 'lng' => 79.5892],
                'Warangal'   => ['lat' => 17.9689, 'lng' => 79.5941],
                'Outskirts'  => ['lat' => 18.0200, 'lng' => 79.6200],
            ];

            foreach ($orders as &$ord) {
                $lat = isset($ord['latitude']) ? (float) $ord['latitude'] : 0.0;
                $lng = isset($ord['longitude']) ? (float) $ord['longitude'] : 0.0;

                if ($lat <= 0.0 || $lng <= 0.0) {
                    $reg = (string) ($ord['region'] ?? 'Hanamkonda');
                    $centroid = $regionCentroids[$reg] ?? $regionCentroids['Hanamkonda'];
                    $ord['latitude']  = $centroid['lat'];
                    $ord['longitude'] = $centroid['lng'];
                    $ord['is_estimated_coord'] = true;
                } else {
                    $ord['is_estimated_coord'] = false;
                }
            }
            unset($ord);

            // Sequential ordering by straight-line distance from Farm Hub
            $hubLat = (float)($hub['latitude'] ?? 18.028439);
            $hubLng = (float)($hub['longitude'] ?? 79.635941);
            usort($orders, function ($a, $b) use ($hubLat, $hubLng, $regionCentroids) {
                $regA = (string)($a['region'] ?? 'Hanamkonda');
                $centroidA = $regionCentroids[$regA] ?? $regionCentroids['Hanamkonda'];
                $latA = (!empty($a['latitude']) && (float)$a['latitude'] > 0) ? (float)$a['latitude'] : $centroidA['lat'];
                $lngA = (!empty($a['longitude']) && (float)$a['longitude'] > 0) ? (float)$a['longitude'] : $centroidA['lng'];

                $regB = (string)($b['region'] ?? 'Hanamkonda');
                $centroidB = $regionCentroids[$regB] ?? $regionCentroids['Hanamkonda'];
                $latB = (!empty($b['latitude']) && (float)$b['latitude'] > 0) ? (float)$b['latitude'] : $centroidB['lat'];
                $lngB = (!empty($b['longitude']) && (float)$b['longitude'] > 0) ? (float)$b['longitude'] : $centroidB['lng'];

                $distA = hypot($latA - $hubLat, $lngA - $hubLng);
                $distB = hypot($latB - $hubLat, $lngB - $hubLng);
                return ($distA <=> $distB) ?: ((int)$a['order_id'] <=> (int)$b['order_id']);
            });

            $orderedStops = [];
            $waypoints = [];
            foreach ($orders as $idx => $stop) {
                $stop['route_sequence_number'] = $idx + 1;
                $orderedStops[] = $stop;
                if (!empty($stop['latitude']) && !empty($stop['longitude'])) {
                    $waypoints[] = $stop['latitude'] . ',' . $stop['longitude'];
                }
            }

            // Persist sequence numbers and 8-stop leg numbers atomically
            if (!empty($orderedStops)) {
                $seqCases = [];
                $legCases = [];
                $ids = [];

                foreach ($orderedStops as $stop) {
                    $oId = (int) $stop['order_id'];
                    $seq = (int) $stop['route_sequence_number'];
                    $leg = (int) ceil($seq / 8);

                    $seqCases[] = "WHEN {$oId} THEN {$seq}";
                    $legCases[] = "WHEN {$oId} THEN {$leg}";
                    $ids[]      = $oId;
                }

                $idList = implode(',', $ids);
                $seqSql = implode(' ', $seqCases);
                $legSql = implode(' ', $legCases);

                $bulkSql = "
                    UPDATE `orders`
                    SET 
                        `route_sequence_number` = CASE `id` {$seqSql} END,
                        `route_leg_number`      = CASE `id` {$legSql} END
                    WHERE `id` IN ({$idList})
                ";

                $pdo->beginTransaction();
                $pdo->exec($bulkSql);
                $pdo->commit();
            }

            $gmapsUrl = !empty($waypoints)
                ? 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($hub['latitude'] . ',' . $hub['longitude']) .
                  '&destination=' . urlencode($hub['latitude'] . ',' . $hub['longitude']) .
                  '&waypoints=' . urlencode(implode('|', array_slice($waypoints, 0, 9)))
                : 'https://www.google.com/maps/search/?api=1&query=' . urlencode($hub['address']);

            echo json_encode([
                'success'                => true,
                'message'                => sprintf('Successfully sequenced %d stops.', count($orderedStops)),
                'total_stops'            => count($orderedStops),
                'total_distance_km'      => 0,
                'total_duration_minutes' => 0,
                'routing_engine'         => 'sequential',
                'hub'                    => $hub,
                'google_maps_url'        => $gmapsUrl,
                'ordered_stops'          => $orderedStops,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 3. Bulk Assign Driver to Stops
        // --------------------------------------------------------------------
        case 'assign_routes':
            $assignments = $data['assignments'] ?? [];
            if (!is_array($assignments) || empty($assignments)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No route assignments supplied.']);
                exit;
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE `orders`
                SET 
                    `assigned_driver_id`    = :driver_id,
                    `route_sequence_number` = :seq_number,
                    `route_leg_number`      = :leg_number
                WHERE `id` = :order_id
            ");

            $count = 0;
            foreach ($assignments as $a) {
                $orderId  = (int) ($a['order_id'] ?? 0);
                $driverId = !empty($a['driver_id']) ? (int) $a['driver_id'] : null;
                $seq      = !empty($a['sequence']) ? (int) $a['sequence'] : null;
                $leg      = $seq !== null ? (int) ceil($seq / 8) : null;

                if ($orderId <= 0) continue;

                $stmt->execute([
                    ':driver_id'   => $driverId,
                    ':seq_number'  => $seq,
                    ':leg_number'  => $leg,
                    ':order_id'    => $orderId,
                ]);
                $count++;
            }

            $pdo->commit();

            echo json_encode([
                'success'        => true,
                'message'        => "Successfully updated {$count} dispatch assignments.",
                'assigned_count' => $count,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 4. Finalize & Dispatch Orders (placed -> packed / out_for_delivery)
        // --------------------------------------------------------------------
        case 'finalize_dispatch':
            $targetDate = trim((string) ($data['target_date'] ?? ''));
            $driverId   = isset($data['driver_id']) ? (int) $data['driver_id'] : 0;
            $orderIds   = $data['order_ids'] ?? [];
            $scheduleId = isset($data['schedule_id']) ? (int) $data['schedule_id'] : 0;
            $region     = trim((string) ($data['region'] ?? ''));

            if ($targetDate === '' && $scheduleId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Target delivery date or schedule ID is required.']);
                exit;
            }

            // Resolve scheduleId from orders or date/region if not passed directly
            if ($scheduleId <= 0 && !empty($orderIds) && is_array($orderIds)) {
                $inList = implode(',', array_map('intval', $orderIds));
                $schLookup = $pdo->query("SELECT `schedule_id` FROM `orders` WHERE `id` IN ($inList) AND `schedule_id` IS NOT NULL LIMIT 1")->fetchColumn();
                if ($schLookup) {
                    $scheduleId = (int) $schLookup;
                }
            }

            if ($scheduleId <= 0 && $region !== '' && $targetDate !== '') {
                $schLookup = $pdo->prepare("SELECT `id` FROM `delivery_schedules` WHERE `delivery_date` = :tdate AND `target_region` = :reg LIMIT 1");
                $schLookup->execute([':tdate' => $targetDate, ':reg' => $region]);
                $foundId = $schLookup->fetchColumn();
                if ($foundId) {
                    $scheduleId = (int) $foundId;
                }
            }

            $pdo->beginTransaction();

            if (!empty($orderIds) && is_array($orderIds)) {
                $inClause = implode(',', array_fill(0, count($orderIds), '?'));
                if ($driverId > 0) {
                    $sql = "UPDATE `orders` SET `order_status` = 'packed', `assigned_driver_id` = ? WHERE `id` IN ($inClause)";
                    $params = array_merge([$driverId], $orderIds);
                } else {
                    $sql = "UPDATE `orders` SET `order_status` = 'packed' WHERE `id` IN ($inClause)";
                    $params = $orderIds;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $count = $stmt->rowCount();
            } elseif ($scheduleId > 0) {
                // Precise scoping by primary key schedule_id to prevent over-dispatch across regions
                if ($driverId > 0) {
                    $sql = "
                        UPDATE `orders`
                        SET `assigned_driver_id` = :driver_id,
                            `order_status` = CASE WHEN `order_status` = 'placed' THEN 'packed' ELSE `order_status` END
                        WHERE `schedule_id` = :sid
                          AND `order_status` != 'cancelled'
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':driver_id' => $driverId, ':sid' => $scheduleId]);
                } else {
                    $sql = "
                        UPDATE `orders`
                        SET `order_status` = 'packed'
                        WHERE `schedule_id` = :sid
                          AND `assigned_driver_id` IS NOT NULL
                          AND `order_status` = 'placed'
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':sid' => $scheduleId]);
                }
                $count = $stmt->rowCount();
            } elseif ($driverId > 0) {
                $sql = "
                    UPDATE `orders`
                    SET `assigned_driver_id` = :driver_id,
                        `order_status` = CASE WHEN `order_status` = 'placed' THEN 'packed' ELSE `order_status` END
                    WHERE `target_delivery_date` = :tdate
                      AND `order_status` != 'cancelled'
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':driver_id' => $driverId, ':tdate' => $targetDate]);
                $count = $stmt->rowCount();
            } else {
                $sql = "
                    UPDATE `orders`
                    SET `order_status` = 'packed'
                    WHERE `target_delivery_date` = :tdate
                      AND `assigned_driver_id` IS NOT NULL
                      AND `order_status` = 'placed'
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':tdate' => $targetDate]);
                $count = $stmt->rowCount();
            }

            // Synchronize delivery schedule state: scope strictly by primary key id if known
            if ($scheduleId > 0) {
                $schStmt = $pdo->prepare("
                    UPDATE `delivery_schedules`
                    SET `status` = 'dispatched', `is_ordering_open` = 0
                    WHERE `id` = :sid
                ");
                $schStmt->execute([':sid' => $scheduleId]);
            } elseif ($region !== '') {
                $schStmt = $pdo->prepare("
                    UPDATE `delivery_schedules`
                    SET `status` = 'dispatched', `is_ordering_open` = 0
                    WHERE `delivery_date` = :tdate AND `target_region` = :reg
                ");
                $schStmt->execute([':tdate' => $targetDate, ':reg' => $region]);
            } else {
                $schStmt = $pdo->prepare("
                    UPDATE `delivery_schedules`
                    SET `status` = 'dispatched', `is_ordering_open` = 0
                    WHERE `delivery_date` = :tdate
                ");
                $schStmt->execute([':tdate' => $targetDate]);
            }

            $pdo->commit();

            echo json_encode([
                'success'          => true,
                'message'          => "Successfully finalized & dispatched {$count} orders. Delivery batch marked as Dispatched.",
                'dispatched_count' => $count,
                'schedule_id'      => $scheduleId,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action '{$action}'."]);
            exit;
    }

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Dispatch API error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
