<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use PDO;
use DateTimeImmutable;

/**
 * Prakruthi Siri - Route Dispatch Service
 * Handles dispatch order retrieval, regional centroid coordinate fallbacks,
 * sequential stop ordering, and route manifest CSV exports.
 */
class RouteDispatchService
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
     * Resolves the target delivery run date.
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
     * Retrieves active drivers for order assignment.
     */
    public function getActiveDrivers(): array
    {
        $stmt = $this->pdo->query("
            SELECT `id`, `full_name`, `phone_number`
            FROM `staff_users`
            WHERE `role` = 'driver' AND `is_active` = 1
            ORDER BY `full_name` ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
     * REQ-LOC-04: Retrieves all configured operating hubs.
     */
    public function getAllHubs(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM `hub_locations` WHERE `is_active` = 1 ORDER BY `is_default_source` DESC, `id` ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * REQ-LOC-04: Resolves source origin and return destination hubs for a target run.
     * Supports "None / Finish at Last Customer Stop" when destination is null.
     */
    public function resolveScheduleHubs(?string $targetDate = null, ?int $scheduleId = null): array
    {
        $sourceHub = null;
        $destHub   = null;

        try {
            if ($scheduleId !== null && $scheduleId > 0) {
                $stmt = $this->pdo->prepare("SELECT `source_hub_id`, `destination_hub_id` FROM `delivery_schedules` WHERE `id` = ?");
                $stmt->execute([$scheduleId]);
                $sched = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($targetDate !== null) {
                $stmt = $this->pdo->prepare("SELECT `source_hub_id`, `destination_hub_id` FROM `delivery_schedules` WHERE `delivery_date` = ? LIMIT 1");
                $stmt->execute([$targetDate]);
                $sched = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $sched = false;
            }

            if ($sched) {
                if (!empty($sched['source_hub_id'])) {
                    $hStmt = $this->pdo->prepare("SELECT * FROM `hub_locations` WHERE `id` = ?");
                    $hStmt->execute([$sched['source_hub_id']]);
                    $sourceHub = $hStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                if (!empty($sched['destination_hub_id'])) {
                    $hStmt = $this->pdo->prepare("SELECT * FROM `hub_locations` WHERE `id` = ?");
                    $hStmt->execute([$sched['destination_hub_id']]);
                    $destHub = $hStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        } catch (\Throwable $e) {
            // graceful fallback
        }

        if (!$sourceHub) {
            $sourceHub = $this->getStoreHub();
        }

        return [
            'source'      => $sourceHub,
            'destination' => $destHub, // null = Finish at Last Stop
        ];
    }

    /**
     * Loads full dispatch data bundle for the given target date.
     */
    public function getDispatchData(string $targetDate): array
    {
        $hubs          = $this->resolveScheduleHubs($targetDate);
        $hub           = $hubs['source'];
        $sourceHub     = $hubs['source'];
        $destHub       = $hubs['destination'];
        $drivers       = $this->getActiveDrivers();
        $formattedDate = date('l, d M Y', strtotime($targetDate));

        // Fetch Orders for Selected Delivery Run
        $ordersStmt = $this->pdo->prepare("
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
            WHERE o.target_delivery_date = :target_date
              AND o.order_status != 'cancelled'
            ORDER BY 
                COALESCE(o.route_sequence_number, 9999) ASC, 
                o.id ASC
        ");
        $ordersStmt->execute([':target_date' => $targetDate]);
        $rawOrders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totalOrders = count($rawOrders);

        // Fetch Line Items for Orders
        $orderIds = array_column($rawOrders, 'order_id');
        $itemsMap = [];
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
                    p.telugu_name
                FROM `order_items` oi
                JOIN `products` p ON oi.product_id = p.id
                WHERE oi.order_id IN ($inClause)
            ");
            $itemsStmt->execute($orderIds);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($items as $it) {
                $itemsMap[$it['order_id']][] = $it;
            }
        }

        $ordersByLeg = [];
        $mapOrders = [];
        $initialGmapsWaypoints = [];
        $rawWaypointsNumeric = [];

        foreach ($rawOrders as $idx => &$ro) {
            $orderItems = $itemsMap[$ro['order_id']] ?? [];
            $ro['items'] = $orderItems;

            $legNum = !empty($ro['route_sequence_number'])
                ? (int) ceil((int) $ro['route_sequence_number'] / 8)
                : (int) floor($idx / 8) + 1;

            $ordersByLeg[$legNum][] = $ro;

            $lat = isset($ro['latitude']) ? (float) $ro['latitude'] : 0.0;
            $lng = isset($ro['longitude']) ? (float) $ro['longitude'] : 0.0;
            $isFallback = false;

            if ($lat <= 0.0 || $lng <= 0.0) {
                $reg = (string) ($ro['region'] ?? 'Hanamkonda');
                $centroid = self::REGION_CENTROIDS[$reg] ?? self::REGION_CENTROIDS['Hanamkonda'];
                $lat = $centroid['lat'];
                $lng = $centroid['lng'];
                $isFallback = true;
            }

            $initialGmapsWaypoints[] = ['lat' => $lat, 'lng' => $lng];
            $rawWaypointsNumeric[] = [$lat, $lng];

            $lightItems = [];
            foreach ($orderItems as $it) {
                $lightItems[] = [
                    'product_name'     => $it['product_name'] ?? '',
                    'telugu_name'      => $it['telugu_name'] ?? '',
                    'half_kg_quantity' => (int) ($it['half_kg_quantity'] ?? 1),
                ];
            }

            $mapOrders[] = [
                'order_id'              => (int) $ro['order_id'],
                'order_code'            => $ro['order_code'],
                'customer_name'         => $ro['customer_name'],
                'delivery_address'      => $ro['delivery_address'],
                'latitude'              => $lat,
                'longitude'             => $lng,
                'is_fallback'           => $isFallback,
                'route_sequence_number' => $ro['route_sequence_number'],
                'total_amount'          => (float) $ro['total_amount'],
                'items'                 => $lightItems,
            ];
        }
        unset($ro);
        ksort($ordersByLeg);

        // REQ-LOC-04: Calculate final destination.
        // If destination hub is specified, leg terminates at that hub.
        // If destination is null ("Finish at Last Customer Stop"), leg terminates at last customer stop.
        if (!empty($rawWaypointsNumeric)) {
            $originCoords = $sourceHub['latitude'] . ',' . $sourceHub['longitude'];
            if ($destHub !== null) {
                $destCoords = $destHub['latitude'] . ',' . $destHub['longitude'];
                $waypointsForGmaps = array_slice($rawWaypointsNumeric, 0, 8);
            } else {
                // Finish at last stop
                $lastStop = end($rawWaypointsNumeric);
                $destCoords = $lastStop[0] . ',' . $lastStop[1];
                $waypointsForGmaps = count($rawWaypointsNumeric) > 1 ? array_slice($rawWaypointsNumeric, 0, count($rawWaypointsNumeric) - 1) : [];
                $waypointsForGmaps = array_slice($waypointsForGmaps, 0, 8);
            }

            $wpParam = !empty($waypointsForGmaps)
                ? '&waypoints=' . urlencode(implode('|', array_map(fn($w) => $w[0] . ',' . $w[1], $waypointsForGmaps)))
                : '';

            $initialGoogleMapsUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($originCoords) .
                                    '&destination=' . urlencode($destCoords) . $wpParam;
        } else {
            $initialGoogleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($sourceHub['address'] ?? 'Warangal');
        }

        return [
            'hub'                   => $sourceHub,
            'source_hub'            => $sourceHub,
            'destination_hub'       => $destHub,
            'drivers'               => $drivers,
            'target_date'           => $targetDate,
            'formatted_date'        => $formattedDate,
            'total_orders'          => $totalOrders,
            'orders_by_leg'         => $ordersByLeg,
            'raw_orders'            => $rawOrders,
            'map_orders'            => $mapOrders,
            'initial_gmaps_url'     => $initialGoogleMapsUrl,
            'initial_gmaps_stops'   => $initialGmapsWaypoints,
        ];
    }

    /**
     * Generates and streams Route Manifest CSV download.
     */
    public function exportManifestCsv(string $targetDate, array $rawOrders, array $drivers, string $formattedDate, int $totalOrders): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Prakruthi_Siri_Route_Manifest_' . $targetDate . '.csv"');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        $out = fopen('php://output', 'w');

        fputcsv($out, ['PRAKRUTHI SIRI - REGIONAL DELIVERY ROUTE MANIFEST']);
        fputcsv($out, ['Delivery Date', $targetDate, $formattedDate]);
        fputcsv($out, ['Total Orders Scheduled', $totalOrders]);
        fputcsv($out, []);

        fputcsv($out, [
            'Stop #',
            'Circuit Leg',
            'Region',
            'Order Code',
            'Status',
            'Customer Name',
            'Customer Phone',
            'Delivery Address',
            'Landmark',
            'Items (0.5kg pkts)',
            'Total Amount (₹)',
            'Payment Mode',
            'Assigned Driver',
            'Google Maps Pin URL',
        ]);

        $driverNameMap = array_column($drivers, 'full_name', 'id');

        $sortedExportOrders = $rawOrders;
        usort($sortedExportOrders, function ($a, $b) {
            $seqA = isset($a['route_sequence_number']) && $a['route_sequence_number'] !== null ? (int) $a['route_sequence_number'] : 9999;
            $seqB = isset($b['route_sequence_number']) && $b['route_sequence_number'] !== null ? (int) $b['route_sequence_number'] : 9999;
            if ($seqA === $seqB) {
                return ((int) $a['order_id']) <=> ((int) $b['order_id']);
            }
            return $seqA <=> $seqB;
        });

        foreach ($sortedExportOrders as $idx => $ord) {
            $itemsSummary = [];
            if (!empty($ord['items']) && is_array($ord['items'])) {
                foreach ($ord['items'] as $it) {
                    $itemsSummary[] = "{$it['telugu_name']} ({$it['product_name']}) x {$it['half_kg_quantity']}";
                }
            }

            $driverName = $driverNameMap[$ord['assigned_driver_id']] ?? 'Unassigned';
            $seq = $ord['route_sequence_number'] ?: ($idx + 1);
            $legNum = $ord['route_leg_number'] ?: (int) ceil($seq / 8);

            if (!empty($ord['latitude']) && !empty($ord['longitude']) && (float) $ord['latitude'] > 0) {
                $mapsUrl = "https://www.google.com/maps/dir/?api=1&destination=" . $ord['latitude'] . ',' . $ord['longitude'];
            } else {
                $mapsUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($ord['delivery_address'] . ', ' . $ord['region'] . ', Warangal');
            }

            fputcsv($out, [
                $seq,
                "Leg #{$legNum}",
                $ord['region'] ?? 'Outskirts',
                $ord['order_code'],
                $ord['order_status'],
                $ord['customer_name'],
                $ord['customer_phone'],
                $ord['delivery_address'],
                $ord['landmark'] ?: '-',
                implode(' | ', $itemsSummary),
                number_format((float) $ord['total_amount'], 2),
                $ord['payment_method'],
                $driverName,
                $mapsUrl,
            ]);
        }

        fclose($out);
        exit;
    }
}
