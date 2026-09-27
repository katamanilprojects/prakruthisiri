<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PrakruthiSiri\Config\Database;
use PrakruthiSiri\Exceptions\InsufficientStockException;
use PrakruthiSiri\Exceptions\OrderValidationException;
use PrakruthiSiri\Exceptions\ProductNotFoundException;

require_once __DIR__ . '/TimeWindow.php';
require_once __DIR__ . '/ConfigService.php';
require_once __DIR__ . '/InventoryService.php';
require_once __DIR__ . '/RunInventoryService.php';
require_once __DIR__ . '/GeoFenceService.php';
require_once __DIR__ . '/Exceptions/InsufficientStockException.php';
require_once __DIR__ . '/Exceptions/OrderValidationException.php';
require_once __DIR__ . '/Exceptions/ProductNotFoundException.php';

use PrakruthiSiri\RunInventoryService;
use PrakruthiSiri\GeoFenceService;

/**
 * Core order processing service enforcing atomic concurrency control,
 * run-isolated inventory decrements, and cutoff delivery scheduling.
 */
class OrderService
{
    public const ALLOWED_REGIONS = ['Hanamkonda', 'Warangal'];
    public const ALLOWED_PAYMENT_METHODS = ['COD', 'UPI'];

    private PDO $pdo;
    private ConfigService $configService;
    private InventoryService $inventoryService;
    private RunInventoryService $runInventoryService;
    private TimeWindow $timeWindow;

    public function __construct(
        ?PDO $pdo = null,
        ?ConfigService $configService = null,
        ?InventoryService $inventoryService = null,
        ?TimeWindow $timeWindow = null,
        ?RunInventoryService $runInventoryService = null
    ) {
        if ($pdo === null) {
            require_once __DIR__ . '/../config/database.php';
            $this->pdo = Database::getInstance()->getConnection();
        } else {
            $this->pdo = $pdo;
        }

        $this->configService       = $configService ?? new ConfigService($this->pdo);
        $this->inventoryService    = $inventoryService ?? new InventoryService($this->pdo);
        $this->runInventoryService = $runInventoryService ?? new RunInventoryService($this->pdo);
        $this->timeWindow          = $timeWindow ?? new TimeWindow();
    }

    /**
     * Places a new customer order with atomic inventory decrements and transactional integrity.
     *
     * @param array<string, mixed> $customerData Customer attributes (phone, name, address, region, etc.)
     * @param array<int, array<string, mixed>> $cartItems Array of items [['product_id' => int, 'half_kg_quantity' => int], ...]
     * @param string $paymentMethod 'COD' or 'UPI'
     * @param string|null $deliveryNotes Optional delivery instructions
     * @param DateTimeImmutable|null $orderTimestamp Optional custom order placement time (defaults to current Kolkata time)
     * 
     * @return array<string, mixed> Success payload
     * 
     * @throws OrderValidationException
     * @throws ProductNotFoundException
     * @throws InsufficientStockException
     * @throws \Throwable
     */
    public function createOrder(
        array $customerData,
        array $cartItems,
        string $paymentMethod = 'COD',
        ?string $deliveryNotes = null,
        ?DateTimeImmutable $orderTimestamp = null,
        ?string $targetDeliveryDateStr = null,
        ?int $scheduleId = null,
        bool $bypassCutoff = false
    ): array {
        // 1. Validate Payment Method
        $paymentMethod = strtoupper(trim($paymentMethod));
        if (!in_array($paymentMethod, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw new OrderValidationException(
                sprintf('Invalid payment method "%s". Allowed: %s.', $paymentMethod, implode(', ', self::ALLOWED_PAYMENT_METHODS))
            );
        }

        // 2. Validate Region & Geofencing
        $customerRegion = trim((string) ($customerData['region'] ?? ''));
        if ($customerRegion !== '' && !GeoFenceService::isValidRegion($customerRegion)) {
            throw new OrderValidationException(
                sprintf('Deliveries are strictly restricted to %s. "%s" is outside our operational service area.', implode(', ', GeoFenceService::ALLOWED_REGIONS), $customerRegion)
            );
        }
        if ($customerRegion === '') {
            $customerRegion = 'Hanamkonda';
            $customerData['region'] = $customerRegion;
        }

        $cLat = isset($customerData['latitude']) && is_numeric($customerData['latitude']) ? (float) $customerData['latitude'] : null;
        $cLng = isset($customerData['longitude']) && is_numeric($customerData['longitude']) ? (float) $customerData['longitude'] : null;

        if ($cLat !== null && $cLng !== null) {
            if (GeoFenceService::isKazipet($cLat, $cLng)) {
                throw new OrderValidationException(
                    'క్షమించండి! మేము కాజీపేట (Kazipet) ప్రాంతానికి డెలివరీ చేయట్లేదు. ప్రస్తుతం హనుమకొండ మరియు వరంగల్ నగరాలకు మాత్రమే డెలివరీలు ఉన్నాయి.'
                );
            }
            if (!GeoFenceService::isWithinDeliveryRadius($cLat, $cLng)) {
                $distanceKm = round(GeoFenceService::getDistanceKm((float)$cLat, (float)$cLng), 1);
                throw new OrderValidationException(
                    "Your delivery coordinates are {$distanceKm} km from our central hub, exceeding the maximum 11.5 km delivery radius."
                );
            }
        } else {
            // Quiet centroid fallback for manual address entry
            $centroids = [
                'Hanamkonda' => ['lat' => 17.9856, 'lng' => 79.5892],
                'Warangal'   => ['lat' => 17.9689, 'lng' => 79.5941],
            ];
            $centroid = $centroids[$customerRegion] ?? $centroids['Hanamkonda'];
            $customerData['latitude']  = $centroid['lat'];
            $customerData['longitude'] = $centroid['lng'];
        }

        // 3. Validate, Aggregate & Normalize Cart Items
        // Deduplicate product entries and sort deterministically by product_id ASC
        // to guarantee uniform lock acquisition order and completely eliminate MySQL transaction deadlocks
        if (empty($cartItems)) {
            throw new OrderValidationException('Cart cannot be empty. Please select at least one vegetable item.');
        }

        $aggregatedItems = [];
        foreach ($cartItems as $itemIndex => $cartItem) {
            $productId = (int) ($cartItem['product_id'] ?? $cartItem['id'] ?? 0);
            $quantity  = (int) ($cartItem['half_kg_quantity'] ?? $cartItem['quantity'] ?? $cartItem['packets'] ?? 0);

            if ($productId <= 0) {
                throw new OrderValidationException("Invalid product ID at index {$itemIndex}.");
            }

            if ($quantity <= 0) {
                throw new OrderValidationException("Quantity for product ID {$productId} must be at least 1 half-kg packet.");
            }

            if (!isset($aggregatedItems[$productId])) {
                $aggregatedItems[$productId] = [
                    'product_id'       => $productId,
                    'half_kg_quantity' => $quantity,
                ];
            } else {
                $aggregatedItems[$productId]['half_kg_quantity'] += $quantity;
            }
        }

        // Sort strictly by product_id ASC
        ksort($aggregatedItems);
        $cartItems = array_values($aggregatedItems);

        // 4. Resolve and Validate Scheduled Delivery Run & Dual Ordering Window (5:00 AM - 7:00 PM)
        $now = $orderTimestamp ?? TimeWindow::now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $schedule = null;
        if ($scheduleId !== null && $scheduleId > 0) {
            $schedule = $this->runInventoryService->getScheduleById($scheduleId);
            if (!$schedule) {
                throw new OrderValidationException("Delivery schedule #{$scheduleId} not found.");
            }
            if ($schedule['target_region'] !== $customerRegion) {
                throw new OrderValidationException(
                    "Delivery run #{$scheduleId} is scheduled for {$schedule['target_region']}, but your delivery address is in {$customerRegion}."
                );
            }
        } else {
            $schedule = $this->runInventoryService->getActiveScheduleForRegion($customerRegion, $now);
            if ($schedule) {
                $scheduleId = (int) $schedule['id'];
            }
        }

        if ($schedule) {
            if (!$bypassCutoff) {
                if ((int) $schedule['is_ordering_open'] !== 1) {
                    throw new OrderValidationException("Ordering is currently closed for the {$schedule['delivery_day']} ({$schedule['target_region']}) run.");
                }
                if ($schedule['order_open_datetime'] > $nowStr) {
                    $opensFormatted = date('l, d M \a\t h:i A', strtotime($schedule['order_open_datetime']));
                    throw new OrderValidationException("Ordering for the {$schedule['delivery_day']} ({$schedule['target_region']}) run opens on {$opensFormatted}.");
                }
                if ($schedule['cutoff_datetime'] <= $nowStr) {
                    $closedFormatted = date('l, d M \a\t h:i A', strtotime($schedule['cutoff_datetime']));
                    throw new OrderValidationException("Ordering for the {$schedule['delivery_day']} ({$schedule['target_region']}) run closed on {$closedFormatted}.");
                }
            }

            $targetDateStr = (string) $schedule['delivery_date'];
            $scheduleId = (int) $schedule['id'];
        } else {
            $upcoming = $this->runInventoryService->getNextUpcomingScheduleForRegion($customerRegion, $now);
            if ($upcoming) {
                $opensFormatted = date('l, d M \a\t h:i A', strtotime($upcoming['order_open_datetime']));
                throw new OrderValidationException("No active ordering window open for {$customerRegion}. The next run ({$upcoming['delivery_day']}, " . date('d M', strtotime($upcoming['delivery_date'])) . ") opens on {$opensFormatted}.");
            }
            throw new OrderValidationException("No scheduled delivery runs available for {$customerRegion}. Please check back soon.");
        }

        $targetDeliveryDate = new DateTimeImmutable($targetDateStr, TimeWindow::getTimeZone());

        // 5. Begin Strict MySQL Transaction
        $this->pdo->beginTransaction();

        try {
            // Step 0: Strictly enforce 30-order hard cap per batch under row lock
            if ($scheduleId !== null && !$bypassCutoff) {
                $countStmt = $this->pdo->prepare("
                    SELECT COUNT(*) 
                    FROM `orders` 
                    WHERE `schedule_id` = :sid 
                      AND `order_status` != 'cancelled' 
                    FOR UPDATE
                ");
                $countStmt->execute([':sid' => $scheduleId]);
                $bookedCount = (int) $countStmt->fetchColumn();
                if ($bookedCount >= 30) {
                    throw new OrderValidationException('క్షమించండి! ఈ డెలివరీ బ్యాచ్ పూర్తిగా నిండిపోయింది (30/30 ఆర్డర్లు బుక్ అయ్యాయి). దయచేసి తదుపరి బ్యాచ్ని ఎంచుకోండి.');
                }
            }

            // ----------------------------------------------------------------
            // Step A: Resolve or Create Customer
            // ----------------------------------------------------------------
            $customerId = $this->resolveOrCreateCustomer($customerData);

            // ----------------------------------------------------------------
            // Step B: Fetch and Validate Cart Products, Price Calculation, & Atomic Stock Decrement
            // ----------------------------------------------------------------
            $this->runInventoryService->ensureScheduleInventory($scheduleId);

            $productSelectStmt = $this->pdo->prepare('
                SELECT 
                    p.`id`,
                    p.`name`,
                    p.`telugu_name`,
                    p.`category`,
                    COALESCE(ri.`pricing_unit`, p.`pricing_unit`, \'half_kg\') AS `pricing_unit`,
                    ri.`price_per_half_kg`,
                    ri.`available_half_kg_stock`,
                    ri.`is_active`
                FROM `run_inventory` ri
                JOIN `products` p ON p.`id` = ri.`product_id`
                WHERE ri.`schedule_id` = :sid AND ri.`product_id` = :pid
                LIMIT 1
            ');

            $decrementStockStmt = $this->pdo->prepare('
                UPDATE `run_inventory`
                SET `available_half_kg_stock` = `available_half_kg_stock` - :deduct_qty
                WHERE `schedule_id` = :sid 
                  AND `product_id` = :pid 
                  AND `available_half_kg_stock` >= :min_stock_qty
            ');

            $processedItems = [];
            $subtotal = 0.00;

            foreach ($cartItems as $itemIndex => $cartItem) {
                $productId = (int) ($cartItem['product_id'] ?? $cartItem['id'] ?? 0);
                $quantity  = (int) ($cartItem['half_kg_quantity'] ?? $cartItem['quantity'] ?? 0);

                if ($productId <= 0) {
                    throw new OrderValidationException("Invalid product ID at index {$itemIndex}.");
                }

                if ($quantity <= 0) {
                    throw new OrderValidationException("Quantity for product ID {$productId} must be at least 1 half-kg packet.");
                }

                // Query product data from run_inventory
                $productSelectStmt->execute([':sid' => $scheduleId, ':pid' => $productId]);
                $product = $productSelectStmt->fetch(PDO::FETCH_ASSOC);

                if (!$product || (int) $product['is_active'] !== 1) {
                    throw new ProductNotFoundException($productId);
                }

                // Atomic Concurrency Control: Decrement stock directly using conditional UPDATE
                $decrementStockStmt->execute([
                    ':deduct_qty'    => $quantity,
                    ':sid'           => $scheduleId,
                    ':pid'           => $productId,
                    ':min_stock_qty' => $quantity,
                ]);

                if ($decrementStockStmt->rowCount() === 0) {
                    // Fetch latest stock count to provide detailed error telemetry
                    $productSelectStmt->execute([':sid' => $scheduleId, ':pid' => $productId]);
                    $freshProduct = $productSelectStmt->fetch(PDO::FETCH_ASSOC);
                    $available = (int) ($freshProduct['available_half_kg_stock'] ?? 0);

                    throw new InsufficientStockException(
                        productId: $productId,
                        productName: (string) $product['name'],
                        requestedQuantity: $quantity,
                        availableStock: $available
                    );
                }

                $unitPrice = (float) $product['price_per_half_kg'];
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;

                $processedItems[] = [
                    'product_id'          => $productId,
                    'product_name'        => $product['name'],
                    'telugu_name'         => $product['telugu_name'],
                    'half_kg_quantity'    => $quantity,
                    'pricing_unit'        => $product['pricing_unit'] ?? 'half_kg',
                    'unit_price_applied'  => $unitPrice,
                    'line_total'          => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);

            // ----------------------------------------------------------------
            // Step C: Delivery Fee Calculation
            // ----------------------------------------------------------------
            $deliveryFee = $this->configService->calculateDeliveryFee($subtotal);
            $totalAmount = round($subtotal + $deliveryFee, 2);

            // ----------------------------------------------------------------
            // Step D: Generate Unique Order Code (PS-YYYYMMDD-XXXX)
            // ----------------------------------------------------------------
            $orderCode = $this->generateUniqueOrderCode($now);

            // ----------------------------------------------------------------
            // Step E: Insert Order Header Record
            // ----------------------------------------------------------------
            $orderInsertStmt = $this->pdo->prepare('
                INSERT INTO `orders` (
                    `order_code`,
                    `customer_id`,
                    `schedule_id`,
                    `subtotal`,
                    `delivery_fee`,
                    `total_amount`,
                    `target_delivery_date`,
                    `order_status`,
                    `payment_method`,
                    `payment_status`,
                    `delivery_notes`,
                    `created_at`
                ) VALUES (
                    :order_code,
                    :customer_id,
                    :schedule_id,
                    :subtotal,
                    :delivery_fee,
                    :total_amount,
                    :target_delivery_date,
                    :order_status,
                    :payment_method,
                    :payment_status,
                    :delivery_notes,
                    :created_at
                )
            ');

            $orderInsertStmt->execute([
                ':order_code'            => $orderCode,
                ':customer_id'           => $customerId,
                ':schedule_id'           => $scheduleId,
                ':subtotal'              => $subtotal,
                ':delivery_fee'          => $deliveryFee,
                ':total_amount'          => $totalAmount,
                ':target_delivery_date'  => $targetDateStr,
                ':order_status'          => 'placed',
                ':payment_method'        => $paymentMethod,
                ':payment_status'        => 'pending',
                ':delivery_notes'        => $deliveryNotes !== null && trim($deliveryNotes) !== '' ? trim($deliveryNotes) : null,
                ':created_at'            => $now->format('Y-m-d H:i:s'),
            ]);

            $orderId = (int) $this->pdo->lastInsertId();

            // ----------------------------------------------------------------
            // Step F: Insert Order Line Items
            // ----------------------------------------------------------------
            $itemInsertStmt = $this->pdo->prepare('
                INSERT INTO `order_items` (
                    `order_id`,
                    `product_id`,
                    `half_kg_quantity`,
                    `pricing_unit`,
                    `unit_price_applied`,
                    `line_total`
                ) VALUES (
                    :order_id,
                    :product_id,
                    :half_kg_quantity,
                    :pricing_unit,
                    :unit_price_applied,
                    :line_total
                )
            ');

            foreach ($processedItems as $item) {
                $itemInsertStmt->execute([
                    ':order_id'           => $orderId,
                    ':product_id'         => $item['product_id'],
                    ':half_kg_quantity'   => $item['half_kg_quantity'],
                    ':pricing_unit'       => $item['pricing_unit'] ?? 'half_kg',
                    ':unit_price_applied' => $item['unit_price_applied'],
                    ':line_total'         => $item['line_total'],
                ]);
            }

            // Commit atomic transaction
            $this->pdo->commit();

            return [
                'success'              => true,
                'order_id'             => $orderId,
                'order_code'           => $orderCode,
                'customer_id'          => $customerId,
                'schedule_id'          => $scheduleId,
                'target_delivery_date' => $targetDateStr,
                'subtotal'             => $subtotal,
                'delivery_fee'         => $deliveryFee,
                'total_amount'         => $totalAmount,
                'payment_method'       => $paymentMethod,
                'order_status'         => 'placed',
                'items_count'          => count($processedItems),
                'items'                => $processedItems,
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resolves an existing customer by phone number or inserts a newly registered customer.
     *
     * @param array<string, mixed> $data
     * @return int Customer ID
     * @throws OrderValidationException
     */
    private function resolveOrCreateCustomer(array $data): int
    {
        $phone = preg_replace('/[^0-9]/', '', (string) ($data['phone_number'] ?? $data['phone'] ?? ''));

        if (strlen($phone) < 10) {
            throw new OrderValidationException('A valid 10-digit phone number is required.');
        }

        // Combine structured address if delivery_address is empty
        if (empty($data['delivery_address']) && (!empty($data['flat_building']) || !empty($data['street_colony']))) {
            $parts = array_filter([
                trim((string) ($data['flat_building'] ?? '')),
                trim((string) ($data['street_colony'] ?? ''))
            ]);
            $data['delivery_address'] = implode(', ', $parts);
        }

        // Check if customer exists
        $stmt = $this->pdo->prepare('
            SELECT `id`, `full_name`, `delivery_address`, `region` 
            FROM `customers` 
            WHERE `phone_number` = :phone 
            LIMIT 1
        ');
        $stmt->execute([':phone' => $phone]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $customerId = (int) $existing['id'];

            // Optionally update address/region/coordinates if explicitly provided
            $updates = [];
            $params  = [];

            if (!empty($data['full_name']) && $data['full_name'] !== $existing['full_name']) {
                $updates[] = '`full_name` = :full_name';
                $params[':full_name'] = trim((string) $data['full_name']);
            }
            if (!empty($data['delivery_address']) && $data['delivery_address'] !== $existing['delivery_address']) {
                $updates[] = '`delivery_address` = :delivery_address';
                $params[':delivery_address'] = trim((string) $data['delivery_address']);
            }
            if (!empty($data['region']) && in_array($data['region'], self::ALLOWED_REGIONS, true) && $data['region'] !== $existing['region']) {
                $updates[] = '`region` = :region';
                $params[':region'] = $data['region'];
            }
            if (isset($data['landmark'])) {
                $updates[] = '`landmark` = :landmark';
                $params[':landmark'] = trim((string) $data['landmark']) ?: null;
            }
            if (isset($data['latitude']) && is_numeric($data['latitude'])) {
                $updates[] = '`latitude` = :latitude';
                $params[':latitude'] = (float) $data['latitude'];
            }
            if (isset($data['longitude']) && is_numeric($data['longitude'])) {
                $updates[] = '`longitude` = :longitude';
                $params[':longitude'] = (float) $data['longitude'];
            }

            if (!empty($updates)) {
                $params[':id'] = $customerId;
                $updateSql = 'UPDATE `customers` SET ' . implode(', ', $updates) . ' WHERE `id` = :id';
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute($params);
            }

            // Sync with customer_addresses table
            $addr = trim((string) ($data['delivery_address'] ?? $existing['delivery_address']));
            $reg  = (string) ($data['region'] ?? $existing['region']);
            $lm   = isset($data['landmark']) ? trim((string) $data['landmark']) : null;
            $lat  = isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
            $lng  = isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null;
            $label = trim((string) ($data['label'] ?? 'Home')) ?: 'Home';

            try {
                // Check if this address is already saved
                $addrCheck = $this->pdo->prepare('
                    SELECT `id` FROM `customer_addresses` 
                    WHERE `customer_id` = :cid AND (`delivery_address` = :addr OR `label` = :lbl) 
                    LIMIT 1
                ');
                $addrCheck->execute([':cid' => $customerId, ':addr' => $addr, ':lbl' => $label]);
                $existingAddrId = $addrCheck->fetchColumn();

                $this->pdo->prepare('UPDATE `customer_addresses` SET `is_default` = 0 WHERE `customer_id` = :cid')->execute([':cid' => $customerId]);

                if ($existingAddrId) {
                    $updAddr = $this->pdo->prepare('
                        UPDATE `customer_addresses` SET 
                            `delivery_address` = :addr, `landmark` = :lm, `region` = :reg, 
                            `latitude` = COALESCE(:lat, `latitude`), `longitude` = COALESCE(:lng, `longitude`), 
                            `is_default` = 1 
                        WHERE `id` = :aid
                    ');
                    $updAddr->execute([
                        ':addr' => $addr, ':lm' => $lm, ':reg' => $reg,
                        ':lat'  => $lat,  ':lng' => $lng, ':aid' => $existingAddrId
                    ]);
                } else if ($addr !== '') {
                    $insAddr = $this->pdo->prepare('
                        INSERT INTO `customer_addresses` 
                            (`customer_id`, `label`, `delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `is_default`)
                        VALUES 
                            (:cid, :lbl, :addr, :lm, :reg, :lat, :lng, 1)
                    ');
                    $insAddr->execute([
                        ':cid' => $customerId, ':lbl' => $label, ':addr' => $addr,
                        ':lm'  => $lm, ':reg' => $reg, ':lat' => $lat, ':lng' => $lng
                    ]);
                }
            } catch (\Throwable $e) {
                // Ignore if customer_addresses table is not yet created
            }

            return $customerId;
        }

        // New customer requires full details
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $address  = trim((string) ($data['delivery_address'] ?? ''));
        $region   = (string) ($data['region'] ?? '');
        $landmark = isset($data['landmark']) ? trim((string) $data['landmark']) : null;
        $latitude = isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
        $longitude = isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null;
        $label    = trim((string) ($data['label'] ?? 'Home')) ?: 'Home';

        if ($fullName === '') {
            throw new OrderValidationException('Customer full name is required for new registration.');
        }

        if ($address === '') {
            throw new OrderValidationException('Delivery address is required.');
        }

        if (!in_array($region, self::ALLOWED_REGIONS, true)) {
            throw new OrderValidationException(
                sprintf('Invalid delivery region "%s". Allowed regions: %s.', $region, implode(', ', self::ALLOWED_REGIONS))
            );
        }

        $insertStmt = $this->pdo->prepare('
            INSERT INTO `customers` (
                `phone_number`,
                `full_name`,
                `delivery_address`,
                `landmark`,
                `region`,
                `latitude`,
                `longitude`,
                `is_location_verified`
            ) VALUES (
                :phone_number,
                :full_name,
                :delivery_address,
                :landmark,
                :region,
                :latitude,
                :longitude,
                0
            )
        ');

        $insertStmt->execute([
            ':phone_number'      => $phone,
            ':full_name'         => $fullName,
            ':delivery_address'  => $address,
            ':landmark'          => $landmark ?: null,
            ':region'            => $region,
            ':latitude'          => $latitude,
            ':longitude'         => $longitude,
        ]);

        $customerId = (int) $this->pdo->lastInsertId();

        try {
            $insAddr = $this->pdo->prepare('
                INSERT INTO `customer_addresses` 
                    (`customer_id`, `label`, `delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `is_default`)
                VALUES 
                    (:cid, :lbl, :addr, :lm, :reg, :lat, :lng, 1)
            ');
            $insAddr->execute([
                ':cid' => $customerId, ':lbl' => $label, ':addr' => $address,
                ':lm'  => $landmark, ':reg' => $region, ':lat' => $latitude, ':lng' => $longitude
            ]);
        } catch (\Throwable $e) {
            // Non-blocking fallback
        }

        return $customerId;
    }

    /**
     * Generates a unique, collision-resistant order code with format: PS-YYYYMMDD-XXXX.
     */
    private function generateUniqueOrderCode(DateTimeImmutable $date): string
    {
        $datePrefix = 'PS-' . $date->format('Ymd') . '-';
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $charsLen = strlen($chars);

        $checkStmt = $this->pdo->prepare('SELECT `id` FROM `orders` WHERE `order_code` = :code LIMIT 1');

        // Loop guarantees uniqueness against any possible collision
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $chars[random_int(0, $charsLen - 1)];
            }
            $code = $datePrefix . $suffix;

            $checkStmt->execute([':code' => $code]);
            if (!$checkStmt->fetch()) {
                return $code;
            }
        }

        // Fallback with microtime suffix in extreme contention scenario
        return $datePrefix . strtoupper(substr(uniqid(), -4));
    }

    /**
     * Cancels an order and atomically restores the 0.5 kg packet quantities back to the harvest stock.
     *
     * @param int $orderId
     * @param string $reason Optional cancellation reason
     * @return array{success: bool, order_id: int, order_code: string, previous_status: string, new_status: string, restored_items: array}
     * @throws OrderValidationException
     */
    public function cancelOrder(int $orderId, string $reason = ''): array
    {
        $this->pdo->beginTransaction();

        try {
            // Lock order row for update
            $orderStmt = $this->pdo->prepare('
                SELECT `id`, `order_code`, `order_status`, `schedule_id`, `delivery_notes`
                FROM `orders`
                WHERE `id` = :id
                FOR UPDATE
            ');
            $orderStmt->execute([':id' => $orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new OrderValidationException("Order #{$orderId} not found.");
            }

            $currentStatus = (string) $order['order_status'];
            if ($currentStatus === 'cancelled') {
                throw new OrderValidationException("Order {$order['order_code']} is already cancelled.");
            }
            if ($currentStatus === 'delivered') {
                throw new OrderValidationException("Delivered order {$order['order_code']} cannot be cancelled.");
            }

            // Fetch order items to restore
            $itemsStmt = $this->pdo->prepare('
                SELECT `product_id`, `half_kg_quantity`
                FROM `order_items`
                WHERE `order_id` = :order_id
            ');
            $itemsStmt->execute([':order_id' => $orderId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $orderScheduleId = !empty($order['schedule_id']) ? (int) $order['schedule_id'] : null;
            $restoredItems = [];

            foreach ($items as $it) {
                $pId = (int) $it['product_id'];
                $qty = (int) $it['half_kg_quantity'];
                if ($orderScheduleId !== null && $orderScheduleId > 0) {
                    $this->runInventoryService->restoreStock($orderScheduleId, $pId, $qty);
                } else {
                    $this->inventoryService->restoreStock($pId, $qty);
                }
                $restoredItems[] = [
                    'product_id'       => $pId,
                    'packets_restored' => $qty,
                ];
            }

            // Update order status to cancelled
            $cancelNote = 'Cancelled on ' . date('Y-m-d H:i:s') . ($reason !== '' ? ': ' . trim($reason) : '');
            $existingNotes = (string) ($order['delivery_notes'] ?? '');
            $newNotes = $existingNotes !== '' ? $existingNotes . ' | ' . $cancelNote : $cancelNote;

            $updateOrderStmt = $this->pdo->prepare('
                UPDATE `orders`
                SET `order_status` = \'cancelled\',
                    `delivery_notes` = :notes
                WHERE `id` = :id
            ');
            $updateOrderStmt->execute([
                ':notes' => $newNotes,
                ':id'    => $orderId,
            ]);

            $this->pdo->commit();

            return [
                'success'          => true,
                'order_id'         => $orderId,
                'order_code'       => (string) $order['order_code'],
                'previous_status'  => $currentStatus,
                'new_status'       => 'cancelled',
                'restored_items'   => $restoredItems,
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resolves target delivery date: checks active delivery_schedules table first,
     * falling back to standard TimeWindow calculation if none scheduled.
     */
    private function resolveTargetDeliveryDate(PDO $pdo, ?DateTimeImmutable $now = null): string
    {
        $now = $now ?? TimeWindow::now();
        try {
            $stmt = $pdo->prepare("
                SELECT `delivery_date` 
                FROM `delivery_schedules`
                WHERE `is_ordering_open` = 1 
                  AND `cutoff_datetime` > :current_time
                ORDER BY `delivery_date` ASC 
                LIMIT 1
            ");
            $stmt->execute([':current_time' => $now->format('Y-m-d H:i:s')]);
            $scheduledDate = $stmt->fetchColumn();

            if ($scheduledDate) {
                return (string) $scheduledDate;
            }
        } catch (\Throwable $e) {
            // Fallback gracefully if delivery_schedules is unseeded or missing
        }

        return $this->timeWindow->getTargetDeliveryDate($now)->format('Y-m-d');
    }
}

