<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use PDO;
use DateTimeImmutable;

require_once __DIR__ . '/ConfigService.php';
require_once __DIR__ . '/TimeWindow.php';

/**
 * Prakruthi Siri - Driver Manifest Service
 * Handles driver manifest retrieval, regional centroid coordinate fallbacks,
 * and direct Google Maps navigation URLs.
 */
class DriverManifestService
{
    private PDO $pdo;
    private ConfigService $configService;
    private TimeWindow $timeWindow;

    /**
     * Regional Centroid Coordinates for fallback location latching.
     */
    public const REGION_CENTROIDS = [
        'Hanamkonda' => ['lat' => 17.9856, 'lng' => 79.5892],
        'Warangal'   => ['lat' => 17.9689, 'lng' => 79.5941],
        'Outskirts'  => ['lat' => 18.0200, 'lng' => 79.6200],
    ];

    public function __construct(
        PDO $pdo,
        ?ConfigService $configService = null,
        ?TimeWindow $timeWindow = null
    ) {
        $this->pdo = $pdo;
        $this->configService = $configService ?? new ConfigService($pdo);
        $this->timeWindow = $timeWindow ?? new TimeWindow();
    }

    /**
     * Resolves the target delivery run date for driver manifest.
     * Prioritizes explicit requested date, then upcoming date in delivery_schedules,
     * and finally TimeWindow arithmetic fallback.
     */
    public function resolveTargetDate(?string $requestedDate = null): string
    {
        if ($requestedDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
            return $requestedDate;
        }

        $now = TimeWindow::now();
        $todayIst = $now->format('Y-m-d');

        try {
            $schedStmt = $this->pdo->prepare("
                SELECT `delivery_date` 
                FROM `delivery_schedules` 
                WHERE `delivery_date` >= :today_ist 
                ORDER BY `delivery_date` ASC 
                LIMIT 1
            ");
            $schedStmt->execute([':today_ist' => $todayIst]);
            $activeRunDate = $schedStmt->fetchColumn();

            if ($activeRunDate) {
                return (string) $activeRunDate;
            }
        } catch (\Throwable $e) {
            // Fallback gracefully if delivery_schedules table is missing
        }

        return $this->timeWindow->getActiveProductionRunDate($now)->format('Y-m-d');
    }

    /**
     * Retrieves store depot hub location.
     */
    public function getStoreHub(): array
    {
        $hub = $this->configService->getStoreHubLocation();
        if ($hub === null) {
            $hub = [
                'name'      => ConfigService::DEFAULT_HUB_NAME,
                'address'   => ConfigService::DEFAULT_HUB_ADDRESS,
                'latitude'  => ConfigService::DEFAULT_HUB_LATITUDE,
                'longitude' => ConfigService::DEFAULT_HUB_LONGITUDE,
            ];
        }
        return $hub;
    }

    /**
     * Loads full driver manifest data for a specific driver and target date or delivery schedule.
     */
    public function getDriverManifest(int $driverId, string $selectedDate, ?int $scheduleId = null): array
    {
        $hub = $this->getStoreHub();
        $prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
        $nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));
        $selectedFormatted = date('l, d M Y', strtotime($selectedDate));

        // Resolve schedule details if available
        $scheduleInfo = null;
        if ($scheduleId !== null && $scheduleId > 0) {
            $sStmt = $this->pdo->prepare("SELECT `id`, `delivery_date`, `delivery_day`, `target_region`, `status` FROM `delivery_schedules` WHERE `id` = :sid LIMIT 1");
            $sStmt->execute([':sid' => $scheduleId]);
            $scheduleInfo = $sStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$scheduleInfo) {
            $sStmt = $this->pdo->prepare("SELECT `id`, `delivery_date`, `delivery_day`, `target_region`, `status` FROM `delivery_schedules` WHERE `delivery_date` = :d LIMIT 1");
            $sStmt->execute([':d' => $selectedDate]);
            $scheduleInfo = $sStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $sidFilter = $scheduleInfo ? (int) $scheduleInfo['id'] : 0;
        if ($scheduleInfo && !empty($scheduleInfo['delivery_date'])) {
            $selectedDate = (string) $scheduleInfo['delivery_date'];
            $selectedFormatted = date('l, d M Y', strtotime($selectedDate));
            $prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
            $nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));
        }

        // Strict SQL query filtering (Enforce role separation: orders assigned to this driver)
        $where = ["o.assigned_driver_id = :driver_id", "o.order_status IN ('placed', 'packed', 'out_for_delivery', 'delivered')"];
        $params = [':driver_id' => $driverId];

        if ($sidFilter > 0) {
            $where[] = "(o.schedule_id = :sid OR (o.schedule_id IS NULL AND o.target_delivery_date = :selected_date))";
            $params[':sid'] = $sidFilter;
            $params[':selected_date'] = $selectedDate;
        } else {
            $where[] = "o.target_delivery_date = :selected_date";
            $params[':selected_date'] = $selectedDate;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                o.id AS order_id, 
                o.order_code, 
                o.customer_id, 
                o.subtotal, 
                o.delivery_fee, 
                o.total_amount, 
                o.target_delivery_date, 
                o.order_status, 
                o.payment_method, 
                o.payment_status, 
                o.delivery_notes, 
                o.assigned_driver_id, 
                o.route_sequence_number, 
                o.route_leg_number,
                o.schedule_id,
                c.full_name AS customer_name, 
                c.phone_number AS customer_phone, 
                c.delivery_address, 
                c.landmark, 
                c.region, 
                c.latitude, 
                c.longitude,
                c.is_location_verified,
                c.gate_photo_path
            FROM `orders` o
            JOIN `customers` c ON o.customer_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY 
                (o.order_status = 'delivered') ASC,
                COALESCE(o.route_sequence_number, 9999) ASC, 
                o.id ASC
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totalStops = count($orders);

        // Fetch Line Items for Orders
        $orderIds = array_column($orders, 'order_id');
        $itemsByOrder = [];
        if (!empty($orderIds)) {
            $inClause = implode(',', array_fill(0, count($orderIds), '?'));
            $itemsStmt = $this->pdo->prepare("
                SELECT 
                    oi.order_id, 
                    oi.product_id, 
                    oi.half_kg_quantity, 
                    oi.unit_price_applied, 
                    oi.line_total,
                    p.name AS product_name, 
                    p.telugu_name,
                    p.unit_label
                FROM `order_items` oi
                JOIN `products` p ON oi.product_id = p.id
                WHERE oi.order_id IN ($inClause)
            ");
            $itemsStmt->execute($orderIds);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($items as $it) {
                $itemsByOrder[$it['order_id']][] = $it;
            }
        }

        $allStopWaypoints = [];
        $pendingStopWaypoints = [];
        $deliveredStops = 0;

        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['order_id']] ?? [];

            if ($order['order_status'] === 'delivered') {
                $deliveredStops++;
            }

            // Fallback to regional centroid coordinates if GPS is unlatched
            $lat = isset($order['latitude']) ? (float) $order['latitude'] : 0.0;
            $lng = isset($order['longitude']) ? (float) $order['longitude'] : 0.0;

            if ($lat <= 0.0 || $lng <= 0.0) {
                $reg = (string) ($order['region'] ?? 'Hanamkonda');
                $centroid = self::REGION_CENTROIDS[$reg] ?? self::REGION_CENTROIDS['Hanamkonda'];
                $lat = $centroid['lat'];
                $lng = $centroid['lng'];
                $order['is_fallback_coord'] = true;
            } else {
                $order['is_fallback_coord'] = false;
            }

            $order['effective_lat'] = $lat;
            $order['effective_lng'] = $lng;

            // Direct single-stop navigation pin
            $order['maps_url'] = ($lat > 0 && $lng > 0 && !$order['is_fallback_coord'])
                ? "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}"
                : "https://www.google.com/maps/search/?api=1&query=" . urlencode($order['delivery_address'] . ', ' . ($order['region'] ?? 'Hanamkonda'));

            $allStopWaypoints[] = ['lat' => $lat, 'lng' => $lng];
            if ($order['order_status'] !== 'delivered') {
                $pendingStopWaypoints[] = ['lat' => $lat, 'lng' => $lng];
            }
        }
        unset($order);

        // Direct round-trip link if pending stops exist
        $fullRoundTripUrl = null;
        if (!empty($pendingStopWaypoints)) {
            $wptStrings = array_map(fn($w) => $w['lat'] . ',' . $w['lng'], array_slice($pendingStopWaypoints, 0, 9));
            $fullRoundTripUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . 
                urlencode($hub['latitude'] . ',' . $hub['longitude']) . 
                '&destination=' . urlencode($hub['latitude'] . ',' . $hub['longitude']) . 
                '&waypoints=' . urlencode(implode('|', $wptStrings));
        }

        // First pending stop for quick highlight
        $firstPendingStop = null;
        foreach ($orders as $o) {
            if ($o['order_status'] !== 'delivered') {
                $firstPendingStop = $o;
                break;
            }
        }

        return [
            'selected_date'          => $selectedDate,
            'prev_date'              => $prevDate,
            'next_date'              => $nextDate,
            'selected_formatted'     => $selectedFormatted,
            'schedule_info'          => $scheduleInfo,
            'orders'                 => $orders,
            'total_stops'            => $totalStops,
            'delivered_stops'        => $deliveredStops,
            'hub'                    => $hub,
            'full_round_trip_url'    => $fullRoundTripUrl,
            'first_pending_stop'     => $firstPendingStop,
        ];
    }
}
