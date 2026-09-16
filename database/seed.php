<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Database Seeder
 * 
 * 1. Purges existing test data (orders, order items, customer addresses, customers, run inventory, schedules).
 * 2. Seeds system settings, staff accounts (admin & drivers), and 8 foundational organic products (0.5 kg base units).
 * 3. Seeds 10 authentic customer profiles across Hanamkonda and Warangal with GPS coordinates within 11.5 km of Farm Hub.
 * 4. Seeds customer multi-addresses (Home, Office, Parents, Shop) with GPS verification status.
 * 5. Seeds delivery schedules:
 *    - Tuesday 2026-09-15 (Hanamkonda, Dispatched batch for driver manifest)
 *    - Saturday 2026-09-19 (Warangal, Open batch for upcoming weekend orders)
 *    - Tuesday 2026-09-22 (Hanamkonda, Open batch for next week)
 * 6. Seeds isolated run inventory for all schedules.
 * 7. Seeds realistic sample orders across both Hanamkonda (dispatched with stops 1-5, delivered/out for delivery) and Warangal (placed/packed).
 * 
 * Execution:
 *   CLI: php database/seed.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/TimeWindow.php';
require_once __DIR__ . '/../src/DatabaseMigration.php';
require_once __DIR__ . '/../src/GeoFenceService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\DatabaseMigration;
use PrakruthiSiri\TimeWindow;
use PrakruthiSiri\GeoFenceService;

header('Content-Type: text/plain; charset=utf-8');

function logMessage(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    if (PHP_SAPI !== 'cli') {
        @ob_flush();
        @flush();
    }
}

try {
    logMessage("Connecting to Prakruthi Siri database...");
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    logMessage("Ensuring database migrations, tables, and columns...");
    DatabaseMigration::ensureMigrated($pdo);

    // ------------------------------------------------------------------------
    // 0. Clean Purge of Existing Test Data
    // ------------------------------------------------------------------------
    logMessage("Purging existing test data (orders, items, addresses, customers, run_inventory, schedules)...");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE `order_items`;");
    $pdo->exec("TRUNCATE TABLE `orders`;");
    $pdo->exec("TRUNCATE TABLE `customer_addresses`;");
    $pdo->exec("TRUNCATE TABLE `customers`;");
    $pdo->exec("TRUNCATE TABLE `run_inventory`;");
    $pdo->exec("TRUNCATE TABLE `delivery_schedules`;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    logMessage("All transactional and customer test data purged successfully.");

    logMessage("Starting transactional database seeding...");
    $pdo->beginTransaction();

    // ------------------------------------------------------------------------
    // 1. Seed System Settings
    // ------------------------------------------------------------------------
    logMessage("Seeding system_settings...");

    $systemSettings = [
        ['key' => 'cutoff_time', 'value' => '19:00:00'],
        ['key' => 'mov_threshold', 'value' => '1.00'],
        ['key' => 'delivery_fee_amount', 'value' => '0.00'],
        ['key' => 'store_override_status', 'value' => 'AUTO'],
        ['key' => 'store_hub_name', 'value' => 'Prakruthi Siri Central Hub & Organic Farm'],
        ['key' => 'store_hub_address', 'value' => 'KU Cross Road, Naimnagar, Hanamkonda, Warangal - 506009'],
        ['key' => 'store_hub_latitude', 'value' => '18.02843900'],
        ['key' => 'store_hub_longitude', 'value' => '79.63594100'],
        ['key' => 'store_whatsapp_number', 'value' => '919393767927'],
    ];

    $settingsStmt = $pdo->prepare('
        INSERT INTO `system_settings` (`setting_key`, `setting_value`)
        VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE
            `setting_value` = VALUES(`setting_value`),
            `updated_at` = CURRENT_TIMESTAMP
    ');

    foreach ($systemSettings as $setting) {
        $settingsStmt->execute([
            ':key'   => $setting['key'],
            ':value' => $setting['value'],
        ]);
        logMessage(" - Setting '{$setting['key']}' => '{$setting['value']}'");
    }

    // ------------------------------------------------------------------------
    // 2. Seed Staff Users (Admin & Drivers)
    // ------------------------------------------------------------------------
    logMessage("Seeding staff_users...");

    $adminHashedPassword = password_hash('Admin@Prakruthi2026', PASSWORD_BCRYPT, ['cost' => 12]);
    $driverHashedPin     = password_hash('123456', PASSWORD_BCRYPT, ['cost' => 12]);

    $staffUsers = [
        [
            'full_name'    => 'Platform Administrator',
            'phone_number' => 'admin@prakruthisiri.com',
            'role'         => 'admin',
            'auth_secret'  => $adminHashedPassword,
            'is_active'    => 1,
        ],
        [
            'full_name'    => 'Ramesh Goud',
            'phone_number' => '9876543210',
            'role'         => 'driver',
            'auth_secret'  => $driverHashedPin,
            'is_active'    => 1,
        ],
        [
            'full_name'    => 'Suresh Kumar',
            'phone_number' => '9876543211',
            'role'         => 'driver',
            'auth_secret'  => $driverHashedPin,
            'is_active'    => 1,
        ],
    ];

    $staffStmt = $pdo->prepare('
        INSERT INTO `staff_users` (`full_name`, `phone_number`, `role`, `auth_secret`, `is_active`)
        VALUES (:full_name, :phone_number, :role, :auth_secret, :is_active)
        ON DUPLICATE KEY UPDATE
            `full_name`   = VALUES(`full_name`),
            `role`        = VALUES(`role`),
            `auth_secret` = VALUES(`auth_secret`),
            `is_active`   = VALUES(`is_active`)
    ');

    foreach ($staffUsers as $staff) {
        $staffStmt->execute([
            ':full_name'    => $staff['full_name'],
            ':phone_number' => $staff['phone_number'],
            ':role'         => $staff['role'],
            ':auth_secret'  => $staff['auth_secret'],
            ':is_active'    => $staff['is_active'],
        ]);
        logMessage(" - Staff '{$staff['full_name']}' ({$staff['role']}, identifier: {$staff['phone_number']})");
    }

    // ------------------------------------------------------------------------
    // 3. Seed Products (Strictly 0.5 kg / Half-kg Base Units)
    // ------------------------------------------------------------------------
    logMessage("Seeding products (cataloged in 0.5 kg units)...");

    $products = [
        [
            'name'                    => 'Country Tomato',
            'telugu_name'             => 'నాటు టమాటా',
            'category'                => 'standard',
            'price_per_half_kg'       => 25.00,
            'available_half_kg_stock' => 80, // 40 kg total (80 x 0.5 kg)
            'image_path'              => 'uploads/products/country_tomato.svg',
            'is_active'               => 1,
        ],
        [
            'name'                    => 'Okra (Lady Finger)',
            'telugu_name'             => 'బెండకాయ',
            'category'                => 'standard',
            'price_per_half_kg'       => 30.00,
            'available_half_kg_stock' => 60, // 30 kg total (60 x 0.5 kg)
            'image_path'              => 'uploads/products/okra.svg',
            'is_active'               => 1,
        ],
        [
            'name'                    => 'Green Round Brinjal',
            'telugu_name'             => 'వంకాయ',
            'category'                => 'standard',
            'price_per_half_kg'       => 28.00,
            'available_half_kg_stock' => 50, // 25 kg total (50 x 0.5 kg)
            'image_path'              => 'uploads/products/brinjal.svg',
            'is_active'               => 1,
        ],
        [
            'name'                    => 'Fresh Ridge Gourd',
            'telugu_name'             => 'బీరకాయ',
            'category'                => 'standard',
            'price_per_half_kg'       => 35.00,
            'available_half_kg_stock' => 40, // 20 kg total (40 x 0.5 kg)
            'image_path'              => 'uploads/products/ridge_gourd.svg',
            'is_active'               => 1,
        ],
        [
            'name'                    => 'Organic Palak (Spinach)',
            'telugu_name'             => 'పాలకూర',
            'category'                => 'standard',
            'price_per_half_kg'       => 20.00,
            'available_half_kg_stock' => 70, // 35 kg total (70 x 0.5 kg)
            'image_path'              => 'uploads/products/spinach.svg',
            'is_active'               => 1,
        ],
        [
            'name'                    => 'Fresh Ivy Gourd (Dondakaya)',
            'telugu_name'             => 'దొండకాయ',
            'category'                => 'standard',
            'price_per_half_kg'       => 32.00,
            'available_half_kg_stock' => 45, // 22.5 kg total (45 x 0.5 kg)
            'image_path'              => 'uploads/products/ivy_gourd.svg',
            'is_active'               => 1,
        ],
        [
            'name'                    => 'Premium Hydroponic English Cucumber',
            'telugu_name'             => 'ప్రీమియం సలాడ్ దోసకాయ',
            'category'                => 'premium',
            'price_per_half_kg'       => 45.00,
            'available_half_kg_stock' => 30, // 15 kg total (30 x 0.5 kg)
            'image_path'              => 'uploads/products/english_cucumber.svg',
            'is_active'               => 1,
        ],
        [
            'name'                    => 'Premium French Haricot Beans',
            'telugu_name'             => 'నాణ్యమైన ఫ్రెంచ్ బీన్స్',
            'category'                => 'premium',
            'price_per_half_kg'       => 55.00,
            'available_half_kg_stock' => 35, // 17.5 kg total (35 x 0.5 kg)
            'image_path'              => 'uploads/products/french_beans.svg',
            'is_active'               => 1,
        ],
    ];

    $productCheckStmt = $pdo->prepare('SELECT `id` FROM `products` WHERE `name` = :name LIMIT 1');
    $productInsertStmt = $pdo->prepare('
        INSERT INTO `products` (
            `name`, `telugu_name`, `category`, `price_per_half_kg`, `available_half_kg_stock`, `image_path`, `is_active`
        ) VALUES (
            :name, :telugu_name, :category, :price_per_half_kg, :available_half_kg_stock, :image_path, :is_active
        )
    ');
    $productUpdateStmt = $pdo->prepare('
        UPDATE `products` SET
            `telugu_name`             = :telugu_name,
            `category`                = :category,
            `price_per_half_kg`       = :price_per_half_kg,
            `available_half_kg_stock` = :available_half_kg_stock,
            `image_path`              = :image_path,
            `is_active`               = :is_active
        WHERE `id` = :id
    ');

    $productMap = []; // name => id
    foreach ($products as $product) {
        $productCheckStmt->execute([':name' => $product['name']]);
        $existingId = $productCheckStmt->fetchColumn();

        if ($existingId) {
            $productUpdateStmt->execute([
                ':id'                      => $existingId,
                ':telugu_name'             => $product['telugu_name'],
                ':category'                => $product['category'],
                ':price_per_half_kg'       => $product['price_per_half_kg'],
                ':available_half_kg_stock' => $product['available_half_kg_stock'],
                ':image_path'              => $product['image_path'],
                ':is_active'               => $product['is_active'],
            ]);
            $productMap[$product['name']] = (int) $existingId;
            logMessage(" - Updated product: {$product['name']} (ID: {$existingId})");
        } else {
            $productInsertStmt->execute([
                ':name'                    => $product['name'],
                ':telugu_name'             => $product['telugu_name'],
                ':category'                => $product['category'],
                ':price_per_half_kg'       => $product['price_per_half_kg'],
                ':available_half_kg_stock' => $product['available_half_kg_stock'],
                ':image_path'              => $product['image_path'],
                ':is_active'               => $product['is_active'],
            ]);
            $newId = (int) $pdo->lastInsertId();
            $productMap[$product['name']] = $newId;
            logMessage(" - Inserted product: {$product['name']} (ID: {$newId})");
        }
    }

    // ------------------------------------------------------------------------
    // 4. Seed Authentic Customers (5 Hanamkonda, 5 Warangal)
    // ------------------------------------------------------------------------
    logMessage("Seeding 10 authentic customers (5 Hanamkonda, 5 Warangal)...");

    $customers = [
        // Hanamkonda (strictly <= 11.5 km, non-Kazipet)
        [
            'phone_number'         => '9988776655',
            'full_name'            => 'S. Rajendra Prasad',
            'delivery_address'     => 'Flat 402, Sri Sai Residency, Subedari',
            'landmark'             => 'Near Pochamma Temple',
            'region'               => 'Hanamkonda',
            'latitude'             => 17.99420000,
            'longitude'            => 79.57140000,
            'gate_photo_path'      => 'uploads/gates/sample_gate.svg',
            'is_location_verified' => 1,
        ],
        [
            'phone_number'         => '9848011223',
            'full_name'            => 'K. Srilatha',
            'delivery_address'     => 'House #2-5-184, Teachers Colony, Naimnagar',
            'landmark'             => 'Near Little Flower School',
            'region'               => 'Hanamkonda',
            'latitude'             => 18.01250000,
            'longitude'            => 79.56300000,
            'gate_photo_path'      => null,
            'is_location_verified' => 1,
        ],
        [
            'phone_number'         => '9849122334',
            'full_name'            => 'M. Padmavati',
            'delivery_address'     => 'Plot 18, Lake View Colony, Waddepally',
            'landmark'             => 'Opposite Tank Bund Road',
            'region'               => 'Hanamkonda',
            'latitude'             => 18.00510000,
            'longitude'            => 79.55420000,
            'gate_photo_path'      => null,
            'is_location_verified' => 0,
        ],
        [
            'phone_number'         => '9440133445',
            'full_name'            => 'Dr. B. Srinivas',
            'delivery_address'     => 'Quarters Q-7, Kakatiya University Staff Quarters',
            'landmark'             => 'KU Campus Road, Naimnagar',
            'region'               => 'Hanamkonda',
            'latitude'             => 18.02100000,
            'longitude'            => 79.54900000,
            'gate_photo_path'      => 'uploads/gates/sample_gate.svg',
            'is_location_verified' => 1,
        ],
        [
            'phone_number'         => '9701144556',
            'full_name'            => 'V. Ramesh Rao',
            'delivery_address'     => 'House #1-7-890, Kishanpura',
            'landmark'             => 'Near 1000 Pillar Temple Main Arch',
            'region'               => 'Hanamkonda',
            'latitude'             => 18.00180000,
            'longitude'            => 79.57850000,
            'gate_photo_path'      => null,
            'is_location_verified' => 0,
        ],
        // Warangal (strictly <= 11.5 km, non-Kazipet)
        [
            'phone_number'         => '9866155667',
            'full_name'            => 'T. Anjaneyulu',
            'delivery_address'     => 'D.No 12-4-85, Pochamma Maidan',
            'landmark'             => 'Near Pochamma Maidan Circle',
            'region'               => 'Warangal',
            'latitude'             => 17.97850000,
            'longitude'            => 79.60120000,
            'gate_photo_path'      => 'uploads/gates/sample_gate.svg',
            'is_location_verified' => 1,
        ],
        [
            'phone_number'         => '9949166778',
            'full_name'            => 'G. Madhavi',
            'delivery_address'     => 'Flat 201, Venkataramana Residency, Girmajipet',
            'landmark'             => 'Opposite Arya Vaishya Bhavan',
            'region'               => 'Warangal',
            'latitude'             => 17.97100000,
            'longitude'            => 79.59800000,
            'gate_photo_path'      => null,
            'is_location_verified' => 1,
        ],
        [
            'phone_number'         => '9490177889',
            'full_name'            => 'K. Venkateshwarlu',
            'delivery_address'     => 'House #8-11-230, Shiva Nagar',
            'landmark'             => 'Near Shiva Temple Arch',
            'region'               => 'Warangal',
            'latitude'             => 17.95800000,
            'longitude'            => 79.60900000,
            'gate_photo_path'      => null,
            'is_location_verified' => 0,
        ],
        [
            'phone_number'         => '9848188990',
            'full_name'            => 'Ch. Lakshmi',
            'delivery_address'     => 'House #15-3-112, Matwada',
            'landmark'             => 'Behind Matwada Police Station',
            'region'               => 'Warangal',
            'latitude'             => 17.97400000,
            'longitude'            => 79.60850000,
            'gate_photo_path'      => 'uploads/gates/sample_gate.svg',
            'is_location_verified' => 1,
        ],
        [
            'phone_number'         => '9618199001',
            'full_name'            => 'P. Satyanarayana',
            'delivery_address'     => 'Plot 55, Srinivasa Colony, Hunter Road',
            'landmark'             => 'Near CKM Hospital',
            'region'               => 'Warangal',
            'latitude'             => 17.96200000,
            'longitude'            => 79.58500000,
            'gate_photo_path'      => null,
            'is_location_verified' => 0,
        ],
    ];

    $custInsertStmt = $pdo->prepare('
        INSERT INTO `customers` (
            `phone_number`, `full_name`, `delivery_address`, `landmark`, `region`,
            `latitude`, `longitude`, `gate_photo_path`, `is_location_verified`
        ) VALUES (
            :phone_number, :full_name, :delivery_address, :landmark, :region,
            :latitude, :longitude, :gate_photo_path, :is_location_verified
        )
    ');

    $customerMap = []; // phone => id
    foreach ($customers as $c) {
        $custInsertStmt->execute([
            ':phone_number'         => $c['phone_number'],
            ':full_name'            => $c['full_name'],
            ':delivery_address'     => $c['delivery_address'],
            ':landmark'             => $c['landmark'],
            ':region'               => $c['region'],
            ':latitude'             => $c['latitude'],
            ':longitude'            => $c['longitude'],
            ':gate_photo_path'      => $c['gate_photo_path'],
            ':is_location_verified' => $c['is_location_verified'],
        ]);
        $cid = (int) $pdo->lastInsertId();
        $customerMap[$c['phone_number']] = $cid;
        logMessage(" - Customer #{$cid}: {$c['full_name']} ({$c['phone_number']}) | {$c['region']} | GPS: {$c['latitude']}, {$c['longitude']}");
    }

    // ------------------------------------------------------------------------
    // 5. Seed Customer Multi-Addresses
    // ------------------------------------------------------------------------
    logMessage("Seeding customer_addresses...");

    $addrInsertStmt = $pdo->prepare('
        INSERT INTO `customer_addresses` (
            `customer_id`, `label`, `delivery_address`, `landmark`, `region`,
            `latitude`, `longitude`, `gate_photo_path`, `is_location_verified`, `is_default`
        ) VALUES (
            :cid, :lbl, :addr, :lm, :reg,
            :lat, :lng, :gate, :ver, :def
        )
    ');

    $multiAddresses = [
        // Rajendra Prasad: Home + Office
        [
            'phone'                => '9988776655',
            'label'                => 'Home',
            'delivery_address'     => 'Flat 402, Sri Sai Residency, Subedari',
            'landmark'             => 'Near Pochamma Temple',
            'region'               => 'Hanamkonda',
            'latitude'             => 17.99420000,
            'longitude'            => 79.57140000,
            'gate_photo_path'      => 'uploads/gates/sample_gate.svg',
            'is_location_verified' => 1,
            'is_default'           => 1,
        ],
        [
            'phone'                => '9988776655',
            'label'                => 'Office / Chambers',
            'delivery_address'     => 'Chamber #14, District Court Complex, Subedari',
            'landmark'             => 'Behind Zilla Parishad',
            'region'               => 'Hanamkonda',
            'latitude'             => 17.99200000,
            'longitude'            => 79.57800000,
            'gate_photo_path'      => null,
            'is_location_verified' => 1,
            'is_default'           => 0,
        ],
        // Srilatha: Home + Parents
        [
            'phone'                => '9848011223',
            'label'                => 'Home',
            'delivery_address'     => 'House #2-5-184, Teachers Colony, Naimnagar',
            'landmark'             => 'Near Little Flower School',
            'region'               => 'Hanamkonda',
            'latitude'             => 18.01250000,
            'longitude'            => 79.56300000,
            'gate_photo_path'      => null,
            'is_location_verified' => 1,
            'is_default'           => 1,
        ],
        [
            'phone'                => '9848011223',
            'label'                => 'Parents Home',
            'delivery_address'     => 'House #3-4-52, Near Old Post Office, Balasamudram',
            'landmark'             => 'Near Balasamudram Community Hall',
            'region'               => 'Hanamkonda',
            'latitude'             => 17.98920000,
            'longitude'            => 79.58210000,
            'gate_photo_path'      => null,
            'is_location_verified' => 1,
            'is_default'           => 0,
        ],
        // Anjaneyulu: Home + Shop
        [
            'phone'                => '9866155667',
            'label'                => 'Home',
            'delivery_address'     => 'D.No 12-4-85, Pochamma Maidan',
            'landmark'             => 'Near Pochamma Maidan Circle',
            'region'               => 'Warangal',
            'latitude'             => 17.97850000,
            'longitude'            => 79.60120000,
            'gate_photo_path'      => 'uploads/gates/sample_gate.svg',
            'is_location_verified' => 1,
            'is_default'           => 1,
        ],
        [
            'phone'                => '9866155667',
            'label'                => 'Shop / Mandi Bazar',
            'delivery_address'     => 'Shop #4, Main Cloth Market, Mandi Bazar',
            'landmark'             => 'Near Clock Tower',
            'region'               => 'Warangal',
            'latitude'             => 17.96550000,
            'longitude'            => 79.60400000,
            'gate_photo_path'      => null,
            'is_location_verified' => 1,
            'is_default'           => 0,
        ],
    ];

    // Add default Home address for the other 7 customers
    $coveredPhones = ['9988776655', '9848011223', '9866155667'];
    foreach ($customers as $c) {
        if (in_array($c['phone_number'], $coveredPhones, true)) {
            continue;
        }
        $multiAddresses[] = [
            'phone'                => $c['phone_number'],
            'label'                => 'Home',
            'delivery_address'     => $c['delivery_address'],
            'landmark'             => $c['landmark'],
            'region'               => $c['region'],
            'latitude'             => $c['latitude'],
            'longitude'            => $c['longitude'],
            'gate_photo_path'      => $c['gate_photo_path'],
            'is_location_verified' => $c['is_location_verified'],
            'is_default'           => 1,
        ];
    }

    foreach ($multiAddresses as $ma) {
        $cid = $customerMap[$ma['phone']] ?? 0;
        if ($cid <= 0) continue;

        $addrInsertStmt->execute([
            ':cid'  => $cid,
            ':lbl'  => $ma['label'],
            ':addr' => $ma['delivery_address'],
            ':lm'   => $ma['landmark'],
            ':reg'  => $ma['region'],
            ':lat'  => $ma['latitude'],
            ':lng'  => $ma['longitude'],
            ':gate' => $ma['gate_photo_path'],
            ':ver'  => $ma['is_location_verified'],
            ':def'  => $ma['is_default'],
        ]);
        logMessage(" - Address '{$ma['label']}' for Customer #{$cid} ({$ma['region']})");
    }

    // ------------------------------------------------------------------------
    // 6. Seed Delivery Schedules
    // ------------------------------------------------------------------------
    logMessage("Seeding delivery schedules (Hanamkonda Tuesday & Warangal Saturday)...");

    $schedules = [
        // Schedule 1: Hanamkonda - Tuesday Batch (2026-09-15) - Today, Dispatched
        [
            'region'   => 'Hanamkonda',
            'delivery' => '2026-09-15',
            'day'      => 'Tuesday',
            'open'     => '2026-09-14 05:00:00',
            'cutoff'   => '2026-09-14 19:00:00',
            'harvest'  => '2026-09-14',
            'is_open'  => 1,
            'status'   => 'dispatched',
        ],
        // Schedule 2: Warangal - Saturday Batch (2026-09-19) - Open for Booking
        [
            'region'   => 'Warangal',
            'delivery' => '2026-09-19',
            'day'      => 'Saturday',
            'open'     => '2026-09-15 05:00:00',
            'cutoff'   => '2026-09-18 19:00:00',
            'harvest'  => '2026-09-18',
            'is_open'  => 1,
            'status'   => 'open',
        ],
        // Schedule 3: Hanamkonda - Next Tuesday Batch (2026-09-22) - Open for Booking
        [
            'region'   => 'Hanamkonda',
            'delivery' => '2026-09-22',
            'day'      => 'Tuesday',
            'open'     => '2026-09-15 05:00:00',
            'cutoff'   => '2026-09-21 19:00:00',
            'harvest'  => '2026-09-21',
            'is_open'  => 1,
            'status'   => 'open',
        ],
    ];

    $schedInsertStmt = $pdo->prepare("
        INSERT INTO `delivery_schedules` 
            (`delivery_date`, `delivery_day`, `target_region`, `order_open_datetime`, `cutoff_datetime`, `harvest_date`, `is_ordering_open`, `status`)
        VALUES 
            (:del, :day, :reg, :open, :cut, :har, :is_open, :status)
    ");

    $scheduleMap = []; // "date_region" => id
    foreach ($schedules as $sc) {
        $schedInsertStmt->execute([
            ':del'     => $sc['delivery'],
            ':day'     => $sc['day'],
            ':reg'     => $sc['region'],
            ':open'    => $sc['open'],
            ':cut'     => $sc['cutoff'],
            ':har'     => $sc['harvest'],
            ':is_open' => $sc['is_open'],
            ':status'  => $sc['status'],
        ]);
        $sid = (int) $pdo->lastInsertId();
        $scheduleMap[$sc['delivery'] . '_' . $sc['region']] = $sid;
        logMessage(" - Schedule #{$sid}: {$sc['region']} on {$sc['day']} ({$sc['delivery']}), Status: {$sc['status']}, Open: {$sc['open']} -> Cutoff: {$sc['cutoff']}");
    }

    // ------------------------------------------------------------------------
    // 7. Seed Run Inventory
    // ------------------------------------------------------------------------
    logMessage("Seeding isolated run_inventory for all delivery schedules...");
    DatabaseMigration::ensureRunInventorySeeded($pdo);

    // ------------------------------------------------------------------------
    // 8. Seed Realistic Sample Orders
    // ------------------------------------------------------------------------
    logMessage("Seeding sample orders and order items...");

    $sched1Id = $scheduleMap['2026-09-15_Hanamkonda'] ?? 1;
    $sched2Id = $scheduleMap['2026-09-19_Warangal'] ?? 2;

    $driverRameshId = 2; // Ramesh Goud

    $ordersToSeed = [
        // --------------------------------------------------------------------
        // Hanamkonda Batch (2026-09-15, Dispatched) - 5 Stops
        // --------------------------------------------------------------------
        [
            'order_code'            => 'PS-20260914-0101',
            'phone'                 => '9988776655', // S. Rajendra Prasad
            'schedule_id'           => $sched1Id,
            'target_delivery_date'  => '2026-09-15',
            'order_status'          => 'delivered',
            'payment_method'        => 'COD',
            'payment_status'        => 'verified',
            'assigned_driver_id'    => $driverRameshId,
            'route_sequence_number' => 1,
            'route_leg_number'      => 1,
            'delivered_at'          => '2026-09-15 09:30:00',
            'delivery_notes'        => 'Ring bell twice, leave crate near front porch',
            'created_at'            => '2026-09-14 09:15:00',
            'items'                 => [
                ['name' => 'Country Tomato', 'qty' => 2],          // 2 * 25 = 50
                ['name' => 'Okra (Lady Finger)', 'qty' => 2],      // 2 * 30 = 60
                ['name' => 'Organic Palak (Spinach)', 'qty' => 3], // 3 * 20 = 60 => Subtotal: 170
            ],
        ],
        [
            'order_code'            => 'PS-20260914-0102',
            'phone'                 => '9848011223', // K. Srilatha
            'schedule_id'           => $sched1Id,
            'target_delivery_date'  => '2026-09-15',
            'order_status'          => 'out_for_delivery',
            'payment_method'        => 'UPI',
            'payment_status'        => 'verified',
            'assigned_driver_id'    => $driverRameshId,
            'route_sequence_number' => 2,
            'route_leg_number'      => 1,
            'delivered_at'          => null,
            'delivery_notes'        => 'Call before arriving',
            'created_at'            => '2026-09-14 11:20:00',
            'items'                 => [
                ['name' => 'Country Tomato', 'qty' => 3],                     // 3 * 25 = 75
                ['name' => 'Green Round Brinjal', 'qty' => 2],                // 2 * 28 = 56
                ['name' => 'Premium Hydroponic English Cucumber', 'qty' => 2],// 2 * 45 = 90 => Subtotal: 221
            ],
        ],
        [
            'order_code'            => 'PS-20260914-0103',
            'phone'                 => '9849122334', // M. Padmavati
            'schedule_id'           => $sched1Id,
            'target_delivery_date'  => '2026-09-15',
            'order_status'          => 'packed',
            'payment_method'        => 'COD',
            'payment_status'        => 'pending',
            'assigned_driver_id'    => $driverRameshId,
            'route_sequence_number' => 3,
            'route_leg_number'      => 1,
            'delivered_at'          => null,
            'delivery_notes'        => 'First-time delivery. Gate is green color.',
            'created_at'            => '2026-09-14 14:05:00',
            'items'                 => [
                ['name' => 'Fresh Ridge Gourd', 'qty' => 2],          // 2 * 35 = 70
                ['name' => 'Fresh Ivy Gourd (Dondakaya)', 'qty' => 2], // 2 * 32 = 64
                ['name' => 'Organic Palak (Spinach)', 'qty' => 2],    // 2 * 20 = 40 => Subtotal: 174
            ],
        ],
        [
            'order_code'            => 'PS-20260914-0104',
            'phone'                 => '9440133445', // Dr. B. Srinivas
            'schedule_id'           => $sched1Id,
            'target_delivery_date'  => '2026-09-15',
            'order_status'          => 'placed',
            'payment_method'        => 'UPI',
            'payment_status'        => 'verified',
            'assigned_driver_id'    => $driverRameshId,
            'route_sequence_number' => 4,
            'route_leg_number'      => 1,
            'delivered_at'          => null,
            'delivery_notes'        => 'Staff quarters security gate will allow entry',
            'created_at'            => '2026-09-14 16:30:00',
            'items'                 => [
                ['name' => 'Premium French Haricot Beans', 'qty' => 2],       // 2 * 55 = 110
                ['name' => 'Premium Hydroponic English Cucumber', 'qty' => 2],// 2 * 45 = 90
                ['name' => 'Country Tomato', 'qty' => 2],                     // 2 * 25 = 50 => Subtotal: 250
            ],
        ],
        [
            'order_code'            => 'PS-20260914-0105',
            'phone'                 => '9701144556', // V. Ramesh Rao
            'schedule_id'           => $sched1Id,
            'target_delivery_date'  => '2026-09-15',
            'order_status'          => 'placed',
            'payment_method'        => 'COD',
            'payment_status'        => 'pending',
            'assigned_driver_id'    => $driverRameshId,
            'route_sequence_number' => 5,
            'route_leg_number'      => 1,
            'delivered_at'          => null,
            'delivery_notes'        => 'Please keep exact change ready',
            'created_at'            => '2026-09-14 18:45:00',
            'items'                 => [
                ['name' => 'Green Round Brinjal', 'qty' => 3], // 3 * 28 = 84
                ['name' => 'Okra (Lady Finger)', 'qty' => 2],  // 2 * 30 = 60
                ['name' => 'Fresh Ridge Gourd', 'qty' => 1],   // 1 * 35 = 35 => Subtotal: 179
            ],
        ],

        // --------------------------------------------------------------------
        // Warangal Batch (2026-09-19, Open / Upcoming) - 4 Orders
        // --------------------------------------------------------------------
        [
            'order_code'            => 'PS-20260915-0201',
            'phone'                 => '9866155667', // T. Anjaneyulu
            'schedule_id'           => $sched2Id,
            'target_delivery_date'  => '2026-09-19',
            'order_status'          => 'packed',
            'payment_method'        => 'UPI',
            'payment_status'        => 'verified',
            'assigned_driver_id'    => null,
            'route_sequence_number' => null,
            'route_leg_number'      => null,
            'delivered_at'          => null,
            'delivery_notes'        => 'Weekend morning delivery',
            'created_at'            => '2026-09-15 06:10:00',
            'items'                 => [
                ['name' => 'Country Tomato', 'qty' => 4],     // 4 * 25 = 100
                ['name' => 'Okra (Lady Finger)', 'qty' => 2], // 2 * 30 = 60
                ['name' => 'Fresh Ridge Gourd', 'qty' => 2],  // 2 * 35 = 70 => Subtotal: 230
            ],
        ],
        [
            'order_code'            => 'PS-20260915-0202',
            'phone'                 => '9949166778', // G. Madhavi
            'schedule_id'           => $sched2Id,
            'target_delivery_date'  => '2026-09-19',
            'order_status'          => 'placed',
            'payment_method'        => 'COD',
            'payment_status'        => 'pending',
            'assigned_driver_id'    => null,
            'route_sequence_number' => null,
            'route_leg_number'      => null,
            'delivered_at'          => null,
            'delivery_notes'        => 'Deliver to Flat 201',
            'created_at'            => '2026-09-15 07:30:00',
            'items'                 => [
                ['name' => 'Organic Palak (Spinach)', 'qty' => 4],    // 4 * 20 = 80
                ['name' => 'Fresh Ivy Gourd (Dondakaya)', 'qty' => 3],// 3 * 32 = 96 => Subtotal: 176
            ],
        ],
        [
            'order_code'            => 'PS-20260915-0203',
            'phone'                 => '9490177889', // K. Venkateshwarlu
            'schedule_id'           => $sched2Id,
            'target_delivery_date'  => '2026-09-19',
            'order_status'          => 'placed',
            'payment_method'        => 'COD',
            'payment_status'        => 'pending',
            'assigned_driver_id'    => null,
            'route_sequence_number' => null,
            'route_leg_number'      => null,
            'delivered_at'          => null,
            'delivery_notes'        => 'Deliver before 10 AM',
            'created_at'            => '2026-09-15 08:45:00',
            'items'                 => [
                ['name' => 'Green Round Brinjal', 'qty' => 2],                // 2 * 28 = 56
                ['name' => 'Premium Hydroponic English Cucumber', 'qty' => 2],// 2 * 45 = 90
                ['name' => 'Country Tomato', 'qty' => 2],                     // 2 * 25 = 50 => Subtotal: 196
            ],
        ],
        [
            'order_code'            => 'PS-20260915-0204',
            'phone'                 => '9848188990', // Ch. Lakshmi
            'schedule_id'           => $sched2Id,
            'target_delivery_date'  => '2026-09-19',
            'order_status'          => 'placed',
            'payment_method'        => 'UPI',
            'payment_status'        => 'verified',
            'assigned_driver_id'    => null,
            'route_sequence_number' => null,
            'route_leg_number'      => null,
            'delivered_at'          => null,
            'delivery_notes'        => 'Gate latch is on the inside right',
            'created_at'            => '2026-09-15 09:50:00',
            'items'                 => [
                ['name' => 'Premium French Haricot Beans', 'qty' => 2],// 2 * 55 = 110
                ['name' => 'Okra (Lady Finger)', 'qty' => 2],         // 2 * 30 = 60
                ['name' => 'Organic Palak (Spinach)', 'qty' => 2],    // 2 * 20 = 40 => Subtotal: 210
            ],
        ],
    ];

    $orderInsertStmt = $pdo->prepare('
        INSERT INTO `orders` (
            `order_code`, `customer_id`, `schedule_id`, `subtotal`, `delivery_fee`, `total_amount`,
            `target_delivery_date`, `order_status`, `payment_method`, `payment_status`,
            `assigned_driver_id`, `route_sequence_number`, `route_leg_number`,
            `delivery_notes`, `delivered_at`, `created_at`
        ) VALUES (
            :order_code, :customer_id, :schedule_id, :subtotal, :delivery_fee, :total_amount,
            :target_delivery_date, :order_status, :payment_method, :payment_status,
            :assigned_driver_id, :route_sequence_number, :route_leg_number,
            :delivery_notes, :delivered_at, :created_at
        )
    ');

    $itemInsertStmt = $pdo->prepare('
        INSERT INTO `order_items` (
            `order_id`, `product_id`, `half_kg_quantity`, `unit_price_applied`, `line_total`
        ) VALUES (
            :order_id, :product_id, :half_kg_quantity, :unit_price_applied, :line_total
        )
    ');

    $decrementStockStmt = $pdo->prepare('
        UPDATE `run_inventory`
        SET `available_half_kg_stock` = GREATEST(0, `available_half_kg_stock` - :qty)
        WHERE `schedule_id` = :sid AND `product_id` = :pid
    ');

    // Fetch product prices for order items
    $priceStmt = $pdo->prepare('SELECT `price_per_half_kg` FROM `products` WHERE `id` = :pid');

    foreach ($ordersToSeed as $ord) {
        $cid = $customerMap[$ord['phone']] ?? 0;
        if ($cid <= 0) continue;

        // Calculate subtotal
        $subtotal = 0.00;
        $orderLines = [];
        foreach ($ord['items'] as $it) {
            $pid = $productMap[$it['name']] ?? 0;
            if ($pid <= 0) continue;

            $priceStmt->execute([':pid' => $pid]);
            $uprice = (float) $priceStmt->fetchColumn();
            $lineTotal = round($uprice * $it['qty'], 2);
            $subtotal += $lineTotal;

            $orderLines[] = [
                'product_id' => $pid,
                'qty'        => $it['qty'],
                'unit_price' => $uprice,
                'line_total' => $lineTotal,
            ];
        }

        $deliveryFee = 0.00;
        $totalAmount = round($subtotal + $deliveryFee, 2);

        $orderInsertStmt->execute([
            ':order_code'            => $ord['order_code'],
            ':customer_id'           => $cid,
            ':schedule_id'           => $ord['schedule_id'],
            ':subtotal'              => $subtotal,
            ':delivery_fee'          => $deliveryFee,
            ':total_amount'          => $totalAmount,
            ':target_delivery_date'  => $ord['target_delivery_date'],
            ':order_status'          => $ord['order_status'],
            ':payment_method'        => $ord['payment_method'],
            ':payment_status'        => $ord['payment_status'],
            ':assigned_driver_id'    => $ord['assigned_driver_id'],
            ':route_sequence_number' => $ord['route_sequence_number'],
            ':route_leg_number'      => $ord['route_leg_number'],
            ':delivery_notes'        => $ord['delivery_notes'],
            ':delivered_at'          => $ord['delivered_at'],
            ':created_at'            => $ord['created_at'],
        ]);

        $orderId = (int) $pdo->lastInsertId();

        foreach ($orderLines as $line) {
            $itemInsertStmt->execute([
                ':order_id'           => $orderId,
                ':product_id'         => $line['product_id'],
                ':half_kg_quantity'   => $line['qty'],
                ':unit_price_applied' => $line['unit_price'],
                ':line_total'         => $line['line_total'],
            ]);

            // Decrement run_inventory for this schedule
            $decrementStockStmt->execute([
                ':qty' => $line['qty'],
                ':sid' => $ord['schedule_id'],
                ':pid' => $line['product_id'],
            ]);
        }

        logMessage(" - Seeded Order #{$orderId} ({$ord['order_code']}): ₹{$totalAmount} | Status: {$ord['order_status']} | Customer: {$ord['phone']}");
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    logMessage("Seeding completed successfully without errors.");

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        logMessage("Transaction rolled back due to error.");
    }
    logMessage("FATAL ERROR: " . $e->getMessage());
    exit(1);
}
