<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Orders Lifecycle & Operations REST API
 * 
 * Manages order auditing, customer inspections, doorstep gate photos,
 * manual payment verification, status progressions, and inventory-restoring cancellations.
 */

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/TimeWindow.php';
require_once __DIR__ . '/../../src/ConfigService.php';
require_once __DIR__ . '/../../src/InventoryService.php';
require_once __DIR__ . '/../../src/RunInventoryService.php';
require_once __DIR__ . '/../../src/OrderService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\ConfigService;
use PrakruthiSiri\RunInventoryService;
use PrakruthiSiri\TimeWindow;
use PrakruthiSiri\OrderService;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::getInstance()->getConnection();

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '', true) ?: [];
    $data = array_merge($_GET, $_POST, $payload);

    $action = (string) ($data['action'] ?? 'get_orders');

    switch ($action) {
        // --------------------------------------------------------------------
        // 1. Fetch Orders List with Tab Badge Counts & Filter Support
        // --------------------------------------------------------------------
        case 'get_orders':
            $statusFilter = strtolower(trim((string) ($data['status'] ?? 'all')));
            $searchQuery  = trim((string) ($data['search'] ?? ''));
            $regionFilter = trim((string) ($data['region'] ?? ''));
            $targetDate   = trim((string) ($data['target_date'] ?? ''));
            $limit        = max(1, min(500, (int) ($data['limit'] ?? 100)));
            $offset       = max(0, (int) ($data['offset'] ?? 0));

            // Base WHERE conditions
            $where = ['1=1'];
            $params = [];

            if ($statusFilter !== '' && $statusFilter !== 'all') {
                $where[] = 'o.`order_status` = :status';
                $params[':status'] = $statusFilter;
            }

            if ($regionFilter !== '' && $regionFilter !== 'All') {
                $where[] = 'c.`region` = :region';
                $params[':region'] = $regionFilter;
            }

            if ($targetDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
                $where[] = 'o.`target_delivery_date` = :tdate';
                $params[':tdate'] = $targetDate;
            }

            if ($searchQuery !== '') {
                $where[] = '(o.`order_code` LIKE :sq1 OR c.`phone_number` LIKE :sq2 OR c.`full_name` LIKE :sq3)';
                $params[':sq1'] = '%' . $searchQuery . '%';
                $params[':sq2'] = '%' . $searchQuery . '%';
                $params[':sq3'] = '%' . $searchQuery . '%';
            }

            $whereSql = implode(' AND ', $where);

            // 1. Query Tab Counts across all statuses (respecting date/region/search if applicable)
            $countWhere = ['1=1'];
            $countParams = [];
            if ($regionFilter !== '' && $regionFilter !== 'All') {
                $countWhere[] = 'c.`region` = :cregion';
                $countParams[':cregion'] = $regionFilter;
            }
            if ($targetDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
                $countWhere[] = 'o.`target_delivery_date` = :ctdate';
                $countParams[':ctdate'] = $targetDate;
            }
            if ($searchQuery !== '') {
                $countWhere[] = '(o.`order_code` LIKE :csq1 OR c.`phone_number` LIKE :csq2 OR c.`full_name` LIKE :csq3)';
                $countParams[':csq1'] = '%' . $searchQuery . '%';
                $countParams[':csq2'] = '%' . $searchQuery . '%';
                $countParams[':csq3'] = '%' . $searchQuery . '%';
            }
            $countWhereSql = implode(' AND ', $countWhere);

            $tabCountSql = "
                SELECT 
                    COUNT(*) AS `cnt_all`,
                    SUM(CASE WHEN o.`order_status` = 'placed' THEN 1 ELSE 0 END) AS `cnt_placed`,
                    SUM(CASE WHEN o.`order_status` = 'packed' THEN 1 ELSE 0 END) AS `cnt_packed`,
                    SUM(CASE WHEN o.`order_status` = 'out_for_delivery' THEN 1 ELSE 0 END) AS `cnt_out_for_delivery`,
                    SUM(CASE WHEN o.`order_status` = 'delivered' THEN 1 ELSE 0 END) AS `cnt_delivered`,
                    SUM(CASE WHEN o.`order_status` = 'cancelled' THEN 1 ELSE 0 END) AS `cnt_cancelled`
                FROM `orders` o
                JOIN `customers` c ON o.`customer_id` = c.`id`
                WHERE {$countWhereSql}
            ";
            $tcStmt = $pdo->prepare($tabCountSql);
            $tcStmt->execute($countParams);
            $tabCounts = $tcStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // 2. Query Orders Rows
            $sql = "
                SELECT 
                    o.`id` AS `order_id`,
                    o.`order_code`,
                    o.`created_at`,
                    o.`target_delivery_date`,
                    o.`order_status`,
                    o.`payment_method`,
                    o.`payment_status`,
                    o.`subtotal`,
                    o.`delivery_fee`,
                    o.`total_amount`,
                    o.`assigned_driver_id`,
                    o.`route_sequence_number`,
                    o.`route_leg_number`,
                    o.`delivery_notes`,
                    c.`id` AS `customer_id`,
                    c.`full_name` AS `customer_name`,
                    c.`phone_number` AS `customer_phone`,
                    c.`delivery_address`,
                    c.`region`,
                    c.`landmark`,
                    c.`latitude`,
                    c.`longitude`,
                    c.`gate_photo_path`,
                    c.`is_location_verified`,
                    u.`full_name` AS `driver_name`
                FROM `orders` o
                JOIN `customers` c ON o.`customer_id` = c.`id`
                LEFT JOIN `staff_users` u ON o.`assigned_driver_id` = u.`id`
                WHERE {$whereSql}
                ORDER BY o.`target_delivery_date` DESC, o.`id` DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Query items summary for these orders
            $orderIds = array_column($orders, 'order_id');
            $itemsMap = [];
            if (!empty($orderIds)) {
                $inClause = implode(',', array_fill(0, count($orderIds), '?'));
                $itemStmt = $pdo->prepare("
                    SELECT 
                        oi.`order_id`,
                        oi.`half_kg_quantity`,
                        p.`name`,
                        p.`telugu_name`
                    FROM `order_items` oi
                    JOIN `products` p ON oi.`product_id` = p.`id`
                    WHERE oi.`order_id` IN ($inClause)
                    ORDER BY p.`name` ASC
                ");
                $itemStmt->execute($orderIds);
                $allIt = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($allIt as $it) {
                    $itemsMap[$it['order_id']][] = $it['name'] . ' (' . ($it['half_kg_quantity'] * 0.5) . 'kg)';
                }
            }

            foreach ($orders as &$o) {
                $oId = (int) $o['order_id'];
                $o['items_summary'] = isset($itemsMap[$oId]) ? implode(', ', $itemsMap[$oId]) : 'No items';
                $o['subtotal_fmt'] = '₹' . number_format((float) $o['subtotal'], 2);
                $o['delivery_fee_fmt'] = '₹' . number_format((float) $o['delivery_fee'], 2);
                $o['total_amount_fmt'] = '₹' . number_format((float) $o['total_amount'], 2);
                $o['created_fmt'] = date('d M Y, h:i A', strtotime($o['created_at']));
                $o['target_run_fmt'] = date('D, d M Y', strtotime($o['target_delivery_date']));
            }
            unset($o);

            echo json_encode([
                'success'    => true,
                'orders'     => $orders,
                'counts'     => [
                    'all'              => (int) ($tabCounts['cnt_all'] ?? 0),
                    'placed'           => (int) ($tabCounts['cnt_placed'] ?? 0),
                    'packed'           => (int) ($tabCounts['cnt_packed'] ?? 0),
                    'out_for_delivery' => (int) ($tabCounts['cnt_out_for_delivery'] ?? 0),
                    'delivered'        => (int) ($tabCounts['cnt_delivered'] ?? 0),
                    'cancelled'        => (int) ($tabCounts['cnt_cancelled'] ?? 0),
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 2. Fetch Single Order Details (Itemized Packets & Doorstep Audit)
        // --------------------------------------------------------------------
        case 'get_order_details':
            $orderId = (int) ($data['order_id'] ?? 0);
            if ($orderId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid order ID.']);
                exit;
            }

            $orderStmt = $pdo->prepare("
                SELECT 
                    o.*,
                    c.`id` AS `customer_id`,
                    c.`full_name` AS `customer_name`,
                    c.`phone_number` AS `customer_phone`,
                    c.`delivery_address`,
                    c.`region`,
                    c.`landmark`,
                    c.`latitude`,
                    c.`longitude`,
                    c.`gate_photo_path`,
                    c.`is_location_verified`,
                    u.`full_name` AS `driver_name`,
                    u.`phone_number` AS `driver_phone`
                FROM `orders` o
                JOIN `customers` c ON o.`customer_id` = c.`id`
                LEFT JOIN `staff_users` u ON o.`assigned_driver_id` = u.`id`
                WHERE o.`id` = :id
                LIMIT 1
            ");
            $orderStmt->execute([':id' => $orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Order not found.']);
                exit;
            }

            // Fetch order items
            $itemStmt = $pdo->prepare("
                SELECT 
                    oi.`id`,
                    oi.`product_id`,
                    oi.`half_kg_quantity`,
                    oi.`unit_price_applied`,
                    oi.`line_total`,
                    p.`name`,
                    p.`telugu_name`,
                    p.`category`,
                    p.`image_path`
                FROM `order_items` oi
                JOIN `products` p ON oi.`product_id` = p.`id`
                WHERE oi.`order_id` = :order_id
                ORDER BY p.`category` DESC, p.`name` ASC
            ");
            $itemStmt->execute([':order_id' => $orderId]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($items as &$it) {
                $qty = (int) $it['half_kg_quantity'];
                $it['weight_kg'] = number_format($qty * 0.5, 1) . ' kg';
                $it['unit_price_fmt'] = '₹' . number_format((float) $it['unit_price_applied'], 2);
                $it['line_total_fmt'] = '₹' . number_format((float) $it['line_total'], 2);
            }
            unset($it);

            $order['items'] = $items;
            $order['subtotal_fmt'] = '₹' . number_format((float) $order['subtotal'], 2);
            $order['delivery_fee_fmt'] = '₹' . number_format((float) $order['delivery_fee'], 2);
            $order['total_amount_fmt'] = '₹' . number_format((float) $order['total_amount'], 2);
            $order['created_fmt'] = date('d M Y, h:i A', strtotime($order['created_at']));
            $order['target_run_fmt'] = date('l, d M Y', strtotime($order['target_delivery_date']));

            // Resolve gate photo URL
            if (!empty($order['gate_photo_path'])) {
                $order['gate_photo_url'] = '../public/' . ltrim($order['gate_photo_path'], '/');
            } else {
                $order['gate_photo_url'] = null;
            }

            echo json_encode([
                'success' => true,
                'order'   => $order,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 3. Update Order Status Lifecycle
        // --------------------------------------------------------------------
        case 'update_status':
            $orderId   = (int) ($data['order_id'] ?? 0);
            $newStatus = strtolower(trim((string) ($data['new_status'] ?? '')));
            $allowed   = ['placed', 'packed', 'out_for_delivery', 'delivered', 'cancelled'];

            if ($orderId <= 0 || !in_array($newStatus, $allowed, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid order ID or target status.']);
                exit;
            }

            // If newStatus is cancelled, delegate to cancel_order to restore stock!
            if ($newStatus === 'cancelled') {
                $reason = trim((string) ($data['reason'] ?? 'Status updated to Cancelled'));
                $orderService = new OrderService($pdo);
                $res = $orderService->cancelOrder($orderId, $reason);
                echo json_encode([
                    'success' => true,
                    'message' => "Order {$res['order_code']} marked as Cancelled and stock restored.",
                    'data'    => $res,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $updateSql = "UPDATE `orders` SET `order_status` = :st";
            $uParams = [':st' => $newStatus, ':id' => $orderId];

            if ($newStatus === 'delivered') {
                $updateSql .= ", `delivered_at` = CURRENT_TIMESTAMP";
            }

            $updateSql .= " WHERE `id` = :id";
            $uStmt = $pdo->prepare($updateSql);
            $uStmt->execute($uParams);

            echo json_encode([
                'success'    => true,
                'message'    => "Order status advanced to '{$newStatus}'.",
                'order_id'   => $orderId,
                'new_status' => $newStatus,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 4. Verify Payment (COD Cash Settled or Manual UPI Confirmed)
        // --------------------------------------------------------------------
        case 'verify_payment':
            $orderId = (int) ($data['order_id'] ?? 0);
            if ($orderId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid order ID.']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE `orders` 
                SET `payment_status` = 'verified' 
                WHERE `id` = :id
            ");
            $stmt->execute([':id' => $orderId]);

            echo json_encode([
                'success'  => true,
                'message'  => "Payment status marked as Verified.",
                'order_id' => $orderId,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 5. Cancel Order with Inventory Stock Restoration
        // --------------------------------------------------------------------
        case 'cancel_order':
            $orderId = (int) ($data['order_id'] ?? 0);
            $reason  = trim((string) ($data['reason'] ?? 'Cancelled by Admin'));

            if ($orderId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valid order_id is required.']);
                exit;
            }

            $orderService = new OrderService($pdo);
            $result = $orderService->cancelOrder($orderId, $reason);

            echo json_encode([
                'success' => true,
                'message' => "Order {$result['order_code']} successfully cancelled and vegetables stock restored to inventory.",
                'data'    => $result,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 6. Customer Fast Lookup for WhatsApp Ingestion
        // --------------------------------------------------------------------
        case 'lookup_customer':
            $phone = preg_replace('/[^0-9]/', '', (string) ($data['phone'] ?? ''));
            if (strlen($phone) < 10) {
                echo json_encode(['success' => true, 'found' => false]);
                exit;
            }
            $phone10 = substr($phone, -10);
            $stmt = $pdo->prepare("
                SELECT `id`, `full_name`, `phone_number`, `delivery_address`, `region`, `landmark`, `latitude`, `longitude`, `is_location_verified`
                FROM `customers`
                WHERE `phone_number` LIKE :p
                ORDER BY `id` DESC
                LIMIT 1
            ");
            $stmt->execute([':p' => '%' . $phone10]);
            $cust = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cust) {
                echo json_encode([
                    'success'  => true,
                    'found'    => true,
                    'customer' => $cust,
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => true,
                    'found'   => false,
                ], JSON_UNESCAPED_UNICODE);
            }
            exit;

        // --------------------------------------------------------------------
        // 7. Delivery Batch Catalog & Capacity Status
        // --------------------------------------------------------------------
        case 'get_batch_catalog':
            $schedId = (int)($data['schedule_id'] ?? 0);
            if ($schedId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valid schedule_id required.']);
                exit;
            }
            $runInv = new RunInventoryService($pdo);
            $schedule = $runInv->getScheduleById($schedId);
            if (!$schedule) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Delivery Batch not found.']);
                exit;
            }
            $catalog = $runInv->getRunCatalog($schedId, false);
            $bookedCount = $runInv->getBookedOrdersCount($schedId);
            echo json_encode([
                'success'             => true,
                'schedule'            => $schedule,
                'booked_orders_count' => $bookedCount,
                'max_orders_limit'    => 30,
                'is_batch_full'       => ($bookedCount >= 30),
                'catalog'             => $catalog,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 8. Create Manual WhatsApp Order (with Capacity Override)
        // --------------------------------------------------------------------
        case 'create_manual_order':
            $schedId = (int)($data['schedule_id'] ?? 0);
            $overrideCapacity = !empty($data['override_capacity']);
            $customerData = $data['customer'] ?? [];
            $cartItems = $data['items'] ?? [];
            $paymentMethod = strtoupper(trim((string)($data['payment_method'] ?? 'COD')));
            $deliveryNotes = trim((string)($data['delivery_notes'] ?? 'WhatsApp Order (Admin Ingestion)'));

            if ($schedId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Delivery Batch is required.']);
                exit;
            }
            if (empty($cartItems)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'At least one vegetable item must be selected.']);
                exit;
            }

            $runInv = new RunInventoryService($pdo);
            $schedule = $runInv->getScheduleById($schedId);
            if (!$schedule) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Delivery Batch not found.']);
                exit;
            }

            $bookedCount = $runInv->getBookedOrdersCount($schedId);
            if ($bookedCount >= 30 && !$overrideCapacity) {
                http_response_code(400);
                echo json_encode([
                    'success'    => false,
                    'error'      => 'Delivery Batch capacity reached (30/30 orders). Check "Admin Capacity Override" to force-book.',
                    'batch_full' => true,
                ]);
                exit;
            }

            $configService = new ConfigService($pdo);
            $orderService = new OrderService($pdo, $configService, null, null, $runInv);

            $result = $orderService->createOrder(
                $customerData,
                $cartItems,
                $paymentMethod,
                $deliveryNotes,
                TimeWindow::now(),
                (string)$schedule['delivery_date'],
                $schedId,
                true
            );

            echo json_encode([
                'success' => true,
                'message' => "WhatsApp order {$result['order_code']} booked successfully.",
                'order'   => $result,
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
        'error'   => 'Orders API error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
