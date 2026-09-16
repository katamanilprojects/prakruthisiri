<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Customer Phone Auto-Lookup & Multi-Address API Endpoint
 * 
 * Provides phone-first authentication, multi-address retrieval, address addition,
 * and default address switching with atomic rate limiting and data integrity.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Method Not Allowed. Accepts GET or POST.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// ----------------------------------------------------------------------------
// Rate Limiting: Atomic Serialized Lock via flock()
// Maximum 15 lookups per 5-minute rolling window per client IP (Bypassed for admin session)
// ----------------------------------------------------------------------------
$isAdmin = !empty($_SESSION['admin_id']);
if (!$isAdmin) {
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $rateLimitFile = sys_get_temp_dir() . '/prakruthi_rl_' . md5($clientIp) . '.lock';
    $now = time();
    $windowSeconds = 300; // 5 minutes
    $maxAttempts = 15;

    $fp = @fopen($rateLimitFile, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            $fileSize = filesize($rateLimitFile);
            $raw = ($fileSize > 0) ? fread($fp, $fileSize) : '';
            $timestamps = [];
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $timestamps = array_filter($decoded, fn($t) => is_numeric($t) && ($now - (int)$t) < $windowSeconds);
                }
            }

            if (count($timestamps) >= $maxAttempts) {
                flock($fp, LOCK_UN);
                fclose($fp);
                http_response_code(429);
                header('Retry-After: ' . $windowSeconds);
                echo json_encode([
                    'success' => false,
                    'found'   => false,
                    'error'   => 'Too many lookup requests. Please wait a few moments or enter your details directly.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $timestamps[] = $now;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(array_values($timestamps)));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/GeoFenceService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\GeoFenceService;

/**
 * Ensures customer_addresses table exists and self-heals if missing.
 */
function ensureCustomerAddressesTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `customer_addresses` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `customer_id` INT UNSIGNED NOT NULL,
            `label` VARCHAR(50) NOT NULL DEFAULT 'Home',
            `delivery_address` TEXT NOT NULL,
            `landmark` VARCHAR(150) NULL DEFAULT NULL,
            `region` ENUM('Hanamkonda', 'Warangal') NOT NULL DEFAULT 'Hanamkonda',
            `latitude` DECIMAL(10, 8) NULL DEFAULT NULL,
            `longitude` DECIMAL(11, 8) NULL DEFAULT NULL,
            `gate_photo_path` VARCHAR(255) NULL DEFAULT NULL,
            `is_location_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_address_customer` (`customer_id`),
            KEY `idx_address_default` (`customer_id`, `is_default`),
            CONSTRAINT `fk_addresses_customer` 
                FOREIGN KEY (`customer_id`) 
                REFERENCES `customers` (`id`) 
                ON UPDATE CASCADE 
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    ensureCustomerAddressesTable($pdo);

    // ========================================================================
    // 1. GET Request: Customer Lookup by Phone
    // ========================================================================
    if ($method === 'GET') {
        $rawPhone = (string) ($_GET['phone'] ?? $_GET['phone_number'] ?? '');
        $phone = preg_replace('/\D/', '', $rawPhone);
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }

        if (strlen($phone) !== 10) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'found'   => false,
                'error'   => 'Please provide a valid 10-digit mobile number.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare('
            SELECT `id`, `phone_number`, `full_name`, `delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `gate_photo_path`, `is_location_verified`
            FROM `customers`
            WHERE `phone_number` = :p OR `phone_number` = :p_prefix
            LIMIT 1
        ');
        $stmt->execute([':p' => $phone, ':p_prefix' => '+91' . $phone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'exists'  => false,
                'found'   => false,
                'message' => 'New customer. Please set up your delivery profile.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $customerId = (int) $customer['id'];

        // Fetch all addresses for this customer
        $addrStmt = $pdo->prepare('
            SELECT `id`, `label`, `delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `gate_photo_path`, `is_location_verified`, `is_default`
            FROM `customer_addresses`
            WHERE `customer_id` = :cid
            ORDER BY `is_default` DESC, `id` DESC
        ');
        $addrStmt->execute([':cid' => $customerId]);
        $rawAddresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // If no addresses in customer_addresses yet, migrate current customer record
        if (empty($rawAddresses) && !empty($customer['delivery_address'])) {
            $insAddr = $pdo->prepare('
                INSERT INTO `customer_addresses` 
                    (`customer_id`, `label`, `delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `gate_photo_path`, `is_location_verified`, `is_default`)
                VALUES 
                    (:cid, :lbl, :addr, :lm, :reg, :lat, :lng, :gate, :ver, 1)
            ');
            $insAddr->execute([
                ':cid'  => $customerId,
                ':lbl'  => 'Home',
                ':addr' => $customer['delivery_address'],
                ':lm'   => $customer['landmark'],
                ':reg'  => $customer['region'] ?: 'Hanamkonda',
                ':lat'  => $customer['latitude'],
                ':lng'  => $customer['longitude'],
                ':gate' => $customer['gate_photo_path'],
                ':ver'  => $customer['is_location_verified'] ?? 0,
            ]);
            $newAddrId = (int) $pdo->lastInsertId();

            $rawAddresses = [[
                'id'                   => $newAddrId,
                'label'                => 'Home',
                'delivery_address'     => (string) $customer['delivery_address'],
                'landmark'             => (string) ($customer['landmark'] ?? ''),
                'region'               => (string) ($customer['region'] ?? 'Hanamkonda'),
                'latitude'             => $customer['latitude'],
                'longitude'            => $customer['longitude'],
                'gate_photo_path'      => $customer['gate_photo_path'],
                'is_location_verified' => (int) ($customer['is_location_verified'] ?? 0),
                'is_default'           => 1,
            ]];
        }

        $formattedAddresses = array_map(function ($a) {
            return [
                'id'                   => (int) $a['id'],
                'label'                => (string) ($a['label'] ?? 'Home'),
                'delivery_address'     => (string) $a['delivery_address'],
                'landmark'             => $a['landmark'] !== null ? (string) $a['landmark'] : '',
                'region'               => (string) $a['region'],
                'latitude'             => $a['latitude'] !== null ? (float) $a['latitude'] : null,
                'longitude'            => $a['longitude'] !== null ? (float) $a['longitude'] : null,
                'gate_photo_path'      => $a['gate_photo_path'] !== null ? (string) $a['gate_photo_path'] : null,
                'is_location_verified' => (int) $a['is_location_verified'] === 1,
                'is_default'           => (int) $a['is_default'] === 1,
            ];
        }, $rawAddresses);

        $activeAddress = null;
        foreach ($formattedAddresses as $fa) {
            if ($fa['is_default']) {
                $activeAddress = $fa;
                break;
            }
        }
        if (!$activeAddress && !empty($formattedAddresses)) {
            $activeAddress = $formattedAddresses[0];
        }

        // Fetch up to 5 recent orders for this customer for 1-tap re-ordering
        $recentOrders = [];
        try {
            $ordersStmt = $pdo->prepare('
                SELECT `id`, `order_code`, `total_amount`, `order_status`, `target_delivery_date`, `created_at`
                FROM `orders`
                WHERE `customer_id` = :cid
                ORDER BY `id` DESC
                LIMIT 5
            ');
            $ordersStmt->execute([':cid' => $customerId]);
            $rawOrders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rawOrders as $ro) {
                $itemStmt = $pdo->prepare('
                    SELECT 
                        oi.`product_id`,
                        oi.`half_kg_quantity`,
                        oi.`unit_price_applied`,
                        p.`name` AS `product_name`,
                        p.`telugu_name`,
                        p.`image_path`,
                        p.`available_half_kg_stock`
                    FROM `order_items` oi
                    JOIN `products` p ON oi.`product_id` = p.`id`
                    WHERE oi.`order_id` = :oid
                    ORDER BY oi.`id` ASC
                ');
                $itemStmt->execute([':oid' => (int) $ro['id']]);
                $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $recentOrders[] = [
                    'id'                   => (int) $ro['id'],
                    'order_code'           => (string) $ro['order_code'],
                    'total_amount'         => (float) $ro['total_amount'],
                    'order_status'         => (string) ($ro['order_status'] ?? 'pending'),
                    'target_delivery_date' => (string) ($ro['target_delivery_date'] ?? ''),
                    'created_at'           => (string) $ro['created_at'],
                    'items'                => array_map(function ($it) {
                        return [
                            'product_id'        => (int) $it['product_id'],
                            'quantity'          => (int) $it['half_kg_quantity'],
                            'price'             => (float) $it['unit_price_applied'],
                            'product_name'      => (string) $it['product_name'],
                            'telugu_name'       => (string) ($it['telugu_name'] ?? $it['product_name']),
                            'image_path'        => (string) ($it['image_path'] ?? ''),
                            'available_stock'   => (int) ($it['available_half_kg_stock'] ?? 0),
                        ];
                    }, $items),
                ];
            }
        } catch (\Throwable $oe) {
            $recentOrders = [];
        }

        http_response_code(200);
        echo json_encode([
            'success'        => true,
            'exists'         => true,
            'found'          => true,
            'customer'       => [
                'id'             => $customerId,
                'phone'          => (string) $customer['phone_number'],
                'phone_number'   => (string) $customer['phone_number'],
                'full_name'      => (string) $customer['full_name'],
                'addresses'      => $formattedAddresses,
                'active_address' => $activeAddress,
                'recent_orders'  => $recentOrders,
                // Backward compatibility flat properties
                'delivery_address'     => $activeAddress ? $activeAddress['delivery_address'] : '',
                'landmark'             => $activeAddress ? $activeAddress['landmark'] : '',
                'region'               => $activeAddress ? $activeAddress['region'] : 'Hanamkonda',
                'latitude'             => $activeAddress ? $activeAddress['latitude'] : null,
                'longitude'            => $activeAddress ? $activeAddress['longitude'] : null,
                'gate_photo_path'      => $activeAddress ? $activeAddress['gate_photo_path'] : null,
                'is_location_verified' => $activeAddress ? $activeAddress['is_location_verified'] : false,
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ========================================================================
    // 2. POST Request: Register New Customer or Add/Set Address
    // ========================================================================
    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $payload = [];
        if ($rawInput !== false && trim($rawInput) !== '') {
            $payload = json_decode($rawInput, true) ?: [];
        }
        if (empty($payload)) {
            $payload = $_POST;
        }

        $action = (string) ($payload['action'] ?? 'register');
        $phone = preg_replace('/\D/', '', (string) ($payload['phone_number'] ?? $payload['phone'] ?? ''));
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }

        if (strlen($phone) !== 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'A valid 10-digit mobile number is required.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->beginTransaction();

        // Check if customer exists
        $cStmt = $pdo->prepare('SELECT `id`, `full_name`, `phone_number` FROM `customers` WHERE `phone_number` = :p OR `phone_number` = :p_prefix LIMIT 1');
        $cStmt->execute([':p' => $phone, ':p_prefix' => '+91' . $phone]);
        $existing = $cStmt->fetch(PDO::FETCH_ASSOC);

        $name = trim((string) ($payload['full_name'] ?? ''));
        $customerId = 0;

        if (!$existing) {
            if ($name === '') {
                throw new InvalidArgumentException('Full name is required to create a profile.');
            }
            $lat = !empty($payload['latitude']) ? (float) $payload['latitude'] : null;
            $lng = !empty($payload['longitude']) ? (float) $payload['longitude'] : null;
            if ($lat !== null && $lng !== null) {
                if (GeoFenceService::isKazipet($lat, $lng)) {
                    throw new InvalidArgumentException('క్షమించండి! మేము కాజీపేట (Kazipet) ప్రాంతానికి డెలివరీ చేయట్లేదు.');
                }
                if (!GeoFenceService::isWithinDeliveryRadius($lat, $lng)) {
                    $dist = GeoFenceService::getDistanceKm($lat, $lng);
                    throw new InvalidArgumentException("మీ లొకేషన్ మా ఫామ్ హబ్ నుండి " . number_format($dist, 1) . " km దూరంలో ఉంది (గరిష్ట పరిధి 11.5 km).");
                }
            }

            $insCust = $pdo->prepare('
                INSERT INTO `customers` (`phone_number`, `full_name`, `delivery_address`, `region`, `landmark`, `latitude`, `longitude`, `is_location_verified`)
                VALUES (:p, :n, :a, :r, :lm, :lat, :lng, 0)
            ');
            $insCust->execute([
                ':p'   => $phone,
                ':n'   => $name,
                ':a'   => trim((string) ($payload['delivery_address'] ?? '')),
                ':r'   => (string) ($payload['region'] ?? 'Hanamkonda'),
                ':lm'  => trim((string) ($payload['landmark'] ?? '')) ?: null,
                ':lat' => $lat,
                ':lng' => $lng,
            ]);
            $customerId = (int) $pdo->lastInsertId();
        } else {
            $customerId = (int) $existing['id'];
            if ($name !== '' && $name !== $existing['full_name']) {
                $updCust = $pdo->prepare('UPDATE `customers` SET `full_name` = :n WHERE `id` = :id');
                $updCust->execute([':n' => $name, ':id' => $customerId]);
            }
        }

        // Handle Action: set_default (switch active address)
        if ($action === 'set_default') {
            $targetAddressId = (int) ($payload['address_id'] ?? 0);
            if ($targetAddressId <= 0) {
                throw new InvalidArgumentException('Valid address ID required to set default.');
            }

            // Verify address belongs to customer
            $addrCheck = $pdo->prepare('SELECT * FROM `customer_addresses` WHERE `id` = :aid AND `customer_id` = :cid LIMIT 1');
            $addrCheck->execute([':aid' => $targetAddressId, ':cid' => $customerId]);
            $targetAddr = $addrCheck->fetch(PDO::FETCH_ASSOC);

            if (!$targetAddr) {
                throw new InvalidArgumentException('Address not found for this account.');
            }

            // Unmark old defaults
            $pdo->prepare('UPDATE `customer_addresses` SET `is_default` = 0 WHERE `customer_id` = :cid')->execute([':cid' => $customerId]);
            // Set new default
            $pdo->prepare('UPDATE `customer_addresses` SET `is_default` = 1 WHERE `id` = :aid')->execute([':aid' => $targetAddressId]);

            // Sync with customers table for backward compatibility
            $syncCust = $pdo->prepare('
                UPDATE `customers` SET
                    `delivery_address`     = :addr,
                    `landmark`             = :lm,
                    `region`               = :reg,
                    `latitude`             = :lat,
                    `longitude`            = :lng,
                    `gate_photo_path`      = :gate,
                    `is_location_verified` = :ver
                WHERE `id` = :cid
            ');
            $syncCust->execute([
                ':addr' => $targetAddr['delivery_address'],
                ':lm'   => $targetAddr['landmark'],
                ':reg'  => $targetAddr['region'],
                ':lat'  => $targetAddr['latitude'],
                ':lng'  => $targetAddr['longitude'],
                ':gate' => $targetAddr['gate_photo_path'],
                ':ver'  => $targetAddr['is_location_verified'],
                ':cid'  => $customerId,
            ]);

            $pdo->commit();

            // Fetch refreshed list
            $listStmt = $pdo->prepare('SELECT * FROM `customer_addresses` WHERE `customer_id` = :cid ORDER BY `is_default` DESC, `id` DESC');
            $listStmt->execute([':cid' => $customerId]);
            $allAddrs = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            http_response_code(200);
            echo json_encode([
                'success'   => true,
                'message'   => 'Default address updated successfully.',
                'customer'  => [
                    'id'        => $customerId,
                    'phone'     => $phone,
                    'full_name' => $name ?: ($existing['full_name'] ?? ''),
                    'addresses' => $allAddrs,
                    'active_address' => $targetAddr,
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Handle Adding / Registering Address
        $address  = trim((string) ($payload['delivery_address'] ?? ''));
        if ($address === '') {
            throw new InvalidArgumentException('Delivery address is required.');
        }

        $label    = trim((string) ($payload['label'] ?? 'Home')) ?: 'Home';
        $region   = (string) ($payload['region'] ?? 'Hanamkonda');
        $landmark = trim((string) ($payload['landmark'] ?? '')) ?: null;
        $latitude = !empty($payload['latitude']) ? (float) $payload['latitude'] : null;
        $longitude = !empty($payload['longitude']) ? (float) $payload['longitude'] : null;
        $isDefault = !empty($payload['is_default']) ? 1 : 0;

        // Validate Coordinates Geofencing if supplied
        if ($latitude !== null && $longitude !== null) {
            if (GeoFenceService::isKazipet($latitude, $longitude)) {
                throw new InvalidArgumentException('క్షమించండి! మేము కాజీపేట (Kazipet) ప్రాంతానికి డెలివరీ చేయట్లేదు.');
            }
            if (!GeoFenceService::isWithinDeliveryRadius($latitude, $longitude)) {
                $dist = GeoFenceService::getDistanceKm($latitude, $longitude);
                throw new InvalidArgumentException("మీ లొకేషన్ మా ఫామ్ హబ్ నుండి " . number_format($dist, 1) . " km దూరంలో ఉంది (గరిష్ట పరిధి 11.5 km).");
            }
        }

        // If marked default or first address, reset other addresses
        if ($isDefault === 1 || !$existing) {
            $isDefault = 1;
            $pdo->prepare('UPDATE `customer_addresses` SET `is_default` = 0 WHERE `customer_id` = :cid')->execute([':cid' => $customerId]);
        }

        $addrIns = $pdo->prepare('
            INSERT INTO `customer_addresses` 
                (`customer_id`, `label`, `delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `is_default`)
            VALUES 
                (:cid, :lbl, :addr, :lm, :reg, :lat, :lng, :def)
        ');
        $addrIns->execute([
            ':cid'  => $customerId,
            ':lbl'  => $label,
            ':addr' => $address,
            ':lm'   => $landmark,
            ':reg'  => $region,
            ':lat'  => $latitude,
            ':lng'  => $longitude,
            ':def'  => $isDefault,
        ]);
        $newAddressId = (int) $pdo->lastInsertId();

        // If set as default, keep customers table updated
        if ($isDefault === 1) {
            $syncCust = $pdo->prepare('
                UPDATE `customers` SET
                    `delivery_address` = :addr,
                    `landmark`         = :lm,
                    `region`           = :reg,
                    `latitude`         = :lat,
                    `longitude`        = :lng
                WHERE `id` = :cid
            ');
            $syncCust->execute([
                ':addr' => $address,
                ':lm'   => $landmark,
                ':reg'  => $region,
                ':lat'  => $latitude,
                ':lng'  => $longitude,
                ':cid'  => $customerId,
            ]);
        }

        $pdo->commit();

        // Fetch all addresses for response
        $allAddrsStmt = $pdo->prepare('SELECT * FROM `customer_addresses` WHERE `customer_id` = :cid ORDER BY `is_default` DESC, `id` DESC');
        $allAddrsStmt->execute([':cid' => $customerId]);
        $allAddresses = $allAddrsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $activeAddr = [
            'id'                   => $newAddressId,
            'label'                => $label,
            'delivery_address'     => $address,
            'landmark'             => $landmark ?: '',
            'region'               => $region,
            'latitude'             => $latitude,
            'longitude'            => $longitude,
            'is_location_verified' => false,
            'is_default'           => (bool) $isDefault,
        ];

        http_response_code(200);
        echo json_encode([
            'success'    => true,
            'customer'   => [
                'id'             => $customerId,
                'phone'          => $phone,
                'phone_number'   => $phone,
                'full_name'      => $name ?: ($existing['full_name'] ?? ''),
                'active_address' => $activeAddr,
                'addresses'      => $allAddresses,
                // Backward compatibility flat properties
                'delivery_address'     => $address,
                'landmark'             => $landmark ?: '',
                'region'               => $region,
                'latitude'             => $latitude,
                'longitude'            => $longitude,
                'is_location_verified' => false,
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
