<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use PDO;
use DateTimeImmutable;

require_once __DIR__ . '/TimeWindow.php';

/**
 * Prakruthi Siri - Automated Database Schema & Migration Manager
 * 
 * Provides idempotent self-healing schema migration and seed verification:
 * 1. Verifies/creates all 8 platform tables (InnoDB utf8mb4)
 * 2. Ensures columns (e.g., `route_leg_number`) and indexes (`idx_orders_dispatch_leg`) exist
 * 3. Guarantees an active delivery schedule exists with open ordering and future cutoff
 * 4. Seeds initial platform settings, staff accounts, and vegetables catalog if empty
 */
class DatabaseMigration
{
    private static bool $migrated = false;

    /**
     * Checks and applies necessary database migrations and ensures an active delivery schedule.
     * Idempotent and executed once per request lifecycle.
     */
    public static function ensureMigrated(PDO $pdo): void
    {
        if (self::$migrated) {
            return;
        }

        // 1. Create or migrate delivery_schedules table (Single Source of Truth for Farm Runs)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `delivery_schedules` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `delivery_date` DATE NOT NULL,
                `delivery_day` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
                `target_region` ENUM('Hanamkonda', 'Warangal') NOT NULL,
                `order_open_datetime` DATETIME NOT NULL,
                `cutoff_datetime` DATETIME NOT NULL,
                `harvest_date` DATE NOT NULL,
                `is_ordering_open` TINYINT(1) NOT NULL DEFAULT 1,
                `status` ENUM('scheduled', 'open', 'closed', 'dispatched', 'completed') NOT NULL DEFAULT 'scheduled',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_run_date_region` (`delivery_date`, `target_region`),
                KEY `idx_run_lookup` (`target_region`, `is_ordering_open`, `cutoff_datetime`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Purge any existing Kazipet data across all tables and modify ENUM
        try {
            $pdo->exec("DELETE FROM `customer_addresses` WHERE `region` = 'Kazipet'");
            $pdo->exec("DELETE FROM `customers` WHERE `region` = 'Kazipet'");
            $pdo->exec("DELETE ri FROM `run_inventory` ri JOIN `delivery_schedules` ds ON ri.`schedule_id` = ds.`id` WHERE ds.`target_region` = 'Kazipet'");
            $pdo->exec("DELETE FROM `delivery_schedules` WHERE `target_region` = 'Kazipet'");
            $pdo->exec("ALTER TABLE `delivery_schedules` MODIFY COLUMN `target_region` ENUM('Hanamkonda', 'Warangal') NOT NULL DEFAULT 'Hanamkonda'");
        } catch (\Throwable $e) {
            error_log('Kazipet purge / target_region ENUM alter notice: ' . $e->getMessage());
        }

        // Ensure columns and indexes exist in delivery_schedules for legacy tables
        try {
            $cols = $pdo->query("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_schedules'
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

            if (!in_array('delivery_day', $cols, true)) {
                $pdo->exec("ALTER TABLE `delivery_schedules` ADD COLUMN `delivery_day` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL DEFAULT 'Thursday' AFTER `delivery_date`");
                $pdo->exec("UPDATE `delivery_schedules` SET `delivery_day` = DAYNAME(`delivery_date`)");
            }

            if (!in_array('target_region', $cols, true)) {
                $pdo->exec("ALTER TABLE `delivery_schedules` ADD COLUMN `target_region` ENUM('Hanamkonda', 'Warangal') NOT NULL DEFAULT 'Hanamkonda' AFTER `delivery_day`");
            }

            if (!in_array('order_open_datetime', $cols, true)) {
                $pdo->exec("ALTER TABLE `delivery_schedules` ADD COLUMN `order_open_datetime` DATETIME NOT NULL DEFAULT '2026-01-01 05:00:00' AFTER `target_region`");
                $pdo->exec("UPDATE `delivery_schedules` SET `order_open_datetime` = CONCAT(DATE_SUB(`delivery_date`, INTERVAL 1 DAY), ' 05:00:00')");
            }

            // Modify status enum if needed
            $pdo->exec("ALTER TABLE `delivery_schedules` MODIFY COLUMN `status` ENUM('scheduled', 'open', 'closed', 'dispatched', 'completed') NOT NULL DEFAULT 'scheduled'");

            // Migrate unique key and indexes
            $idxNames = $pdo->query("
                SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_schedules'
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

            if (in_array('uk_delivery_date', $idxNames, true)) {
                $pdo->exec("ALTER TABLE `delivery_schedules` DROP INDEX `uk_delivery_date`");
            }
            if (!in_array('uk_run_date_region', $idxNames, true)) {
                $pdo->exec("ALTER TABLE `delivery_schedules` ADD UNIQUE KEY `uk_run_date_region` (`delivery_date`, `target_region`)");
            }
            if (!in_array('idx_run_lookup', $idxNames, true)) {
                $pdo->exec("ALTER TABLE `delivery_schedules` ADD KEY `idx_run_lookup` (`target_region`, `is_ordering_open`, `cutoff_datetime`)");
            }
        } catch (\Throwable $e) {
            error_log('delivery_schedules auto-migration notice: ' . $e->getMessage());
        }

        // 2. Create customer_addresses table if missing
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

        // 3. Create run_inventory table if missing
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `run_inventory` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `schedule_id` INT UNSIGNED NOT NULL,
                `product_id` INT UNSIGNED NOT NULL,
                `harvest_kg` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
                `available_half_kg_stock` INT UNSIGNED NOT NULL DEFAULT 0,
                `price_per_half_kg` DECIMAL(8, 2) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_schedule_product` (`schedule_id`, `product_id`),
                KEY `idx_run_inv_schedule` (`schedule_id`, `is_active`),
                CONSTRAINT `fk_run_inv_schedule` 
                    FOREIGN KEY (`schedule_id`) 
                    REFERENCES `delivery_schedules` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE CASCADE,
                CONSTRAINT `fk_run_inv_product` 
                    FOREIGN KEY (`product_id`) 
                    REFERENCES `products` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 4. Ensure orders table has route_leg_number and schedule_id columns
        try {
            $colStmt = $pdo->query("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'orders'
            ");
            $orderCols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];

            if (!in_array('route_leg_number', $orderCols, true)) {
                $pdo->exec("
                    ALTER TABLE `orders` 
                    ADD COLUMN `route_leg_number` INT UNSIGNED NULL DEFAULT NULL AFTER `route_sequence_number`
                ");
            }

            if (!in_array('schedule_id', $orderCols, true)) {
                $pdo->exec("
                    ALTER TABLE `orders` 
                    ADD COLUMN `schedule_id` INT UNSIGNED NULL DEFAULT NULL AFTER `customer_id`,
                    ADD INDEX `idx_orders_schedule` (`schedule_id`)
                ");
            }

            // Backfill schedule_id on existing orders where null
            $pdo->exec("
                UPDATE `orders` o 
                JOIN `delivery_schedules` ds ON ds.delivery_date = o.target_delivery_date
                SET o.schedule_id = ds.id 
                WHERE o.schedule_id IS NULL
            ");
        } catch (\Throwable) {
            // Table orders might not exist yet if fresh DB
        }

        // 5. Ensure orders table has dispatch and reporting indexes
        try {
            $idxStmt = $pdo->query("
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'orders' 
                  AND INDEX_NAME = 'idx_orders_dispatch_leg'
            ");
            if ($idxStmt && (int) $idxStmt->fetchColumn() === 0) {
                $pdo->exec("
                    ALTER TABLE `orders`
                    ADD INDEX `idx_orders_dispatch_leg` (`target_delivery_date`, `assigned_driver_id`, `route_leg_number`)
                ");
            }

            // Index: target_delivery_date + order_status for revenue and order filtering
            $idxStmt2 = $pdo->query("
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'orders' 
                  AND INDEX_NAME = 'idx_orders_target_date_status'
            ");
            if ($idxStmt2 && (int) $idxStmt2->fetchColumn() === 0) {
                $pdo->exec("
                    ALTER TABLE `orders`
                    ADD INDEX `idx_orders_target_date_status` (`target_delivery_date`, `order_status`)
                ");
            }

            // Index: created_at + order_status for fiscal year / date period reporting
            $idxStmt3 = $pdo->query("
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'orders' 
                  AND INDEX_NAME = 'idx_orders_created_at_status'
            ");
            if ($idxStmt3 && (int) $idxStmt3->fetchColumn() === 0) {
                $pdo->exec("
                    ALTER TABLE `orders`
                    ADD INDEX `idx_orders_created_at_status` (`created_at`, `order_status`)
                ");
            }

            // Index: order_items(order_id, product_id)
            $idxStmt4 = $pdo->query("
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'order_items' 
                  AND INDEX_NAME = 'idx_order_items_order_product'
            ");
            if ($idxStmt4 && (int) $idxStmt4->fetchColumn() === 0) {
                $pdo->exec("
                    ALTER TABLE `order_items`
                    ADD INDEX `idx_order_items_order_product` (`order_id`, `product_id`)
                ");
            }
        } catch (\Throwable) {
            // Tables might not exist yet if fresh DB
        }

        // 6. Ensure active delivery schedules and run inventory are populated
        self::ensureActiveSchedule($pdo);
        self::ensureRunInventorySeeded($pdo);

        self::$migrated = true;
    }

    /**
     * Ensures an open delivery schedule exists with a future cutoff datetime.
     */
    public static function ensureActiveSchedule(PDO $pdo): void
    {
        try {
            $now = TimeWindow::now();
            $nowStr = $now->format('Y-m-d H:i:s');
            $timeWindow = new TimeWindow();
            $primaryRunDate = $timeWindow->getTargetDeliveryDate($now);

            $regionConfigs = [
                'Hanamkonda' => ['offset' => 0],
                'Warangal'   => ['offset' => 2],
            ];

            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM `delivery_schedules` 
                WHERE `target_region` = :reg
                  AND `is_ordering_open` = 1 
                  AND `cutoff_datetime` > :now_str
            ");

            $insertStmt = $pdo->prepare("
                INSERT INTO `delivery_schedules` 
                    (`delivery_date`, `delivery_day`, `target_region`, `order_open_datetime`, `cutoff_datetime`, `harvest_date`, `is_ordering_open`, `status`)
                VALUES 
                    (:del, :day, :reg, :open, :cut, :har, 1, 'scheduled')
                ON DUPLICATE KEY UPDATE
                    `cutoff_datetime`     = VALUES(`cutoff_datetime`),
                    `order_open_datetime` = VALUES(`order_open_datetime`),
                    `is_ordering_open`    = 1,
                    `status`              = 'scheduled'
            ");

            foreach ($regionConfigs as $regName => $cfg) {
                $checkStmt->execute([
                    ':reg'     => $regName,
                    ':now_str' => $nowStr,
                ]);
                $activeCount = (int) $checkStmt->fetchColumn();

                if ($activeCount === 0) {
                    $targetDeliveryDate = $primaryRunDate->modify('+' . $cfg['offset'] . ' days');
                    $deliveryDateStr = $targetDeliveryDate->format('Y-m-d');
                    $deliveryDayStr  = $targetDeliveryDate->format('l');

                    $prevDate = $targetDeliveryDate->modify('-1 day');
                    $openStr = $prevDate->format('Y-m-d 05:00:00');
                    $cutoffStr = $prevDate->format('Y-m-d 19:00:00');
                    $harvestDateStr = $prevDate->format('Y-m-d');

                    if (strtotime($cutoffStr) <= time()) {
                        $openStr = $now->modify('-1 hour')->format('Y-m-d H:i:s');
                        $cutoffStr = $now->modify('+12 hours')->format('Y-m-d H:i:s');
                    }

                    $insertStmt->execute([
                        ':del'  => $deliveryDateStr,
                        ':day'  => $deliveryDayStr,
                        ':reg'  => $regName,
                        ':open' => $openStr,
                        ':cut'  => $cutoffStr,
                        ':har'  => $harvestDateStr,
                    ]);
                }
            }

            // Also ensure active_delivery_date setting is aligned
            $setStmt = $pdo->prepare("
                INSERT INTO `system_settings` (`setting_key`, `setting_value`)
                VALUES ('active_delivery_date', :del)
                ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
            ");
            $setStmt->execute([':del' => $primaryRunDate->format('Y-m-d')]);
        } catch (\Throwable) {
            // Silently continue if table creation was in progress
        }
    }

    /**
     * Initializes or backfills run_inventory items from products catalog for existing delivery schedules.
     */
    public static function ensureRunInventorySeeded(PDO $pdo): void
    {
        try {
            $schedules = $pdo->query("SELECT id FROM `delivery_schedules`")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (empty($schedules)) {
                return;
            }

            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `run_inventory` WHERE `schedule_id` = :sid");
            $insertStmt = $pdo->prepare("
                INSERT INTO `run_inventory` (`schedule_id`, `product_id`, `harvest_kg`, `available_half_kg_stock`, `price_per_half_kg`, `is_active`)
                SELECT :sid, p.`id`, ROUND(p.`available_half_kg_stock` * 0.5, 2), p.`available_half_kg_stock`, p.`price_per_half_kg`, p.`is_active`
                FROM `products` p
                WHERE p.`is_active` = 1
                ON DUPLICATE KEY UPDATE `run_inventory`.`id` = `run_inventory`.`id`
            ");

            foreach ($schedules as $sid) {
                $checkStmt->execute([':sid' => $sid]);
                if ((int) $checkStmt->fetchColumn() === 0) {
                    $insertStmt->execute([':sid' => $sid]);
                }
            }
        } catch (\Throwable $e) {
            error_log('ensureRunInventorySeeded notice: ' . $e->getMessage());
        }
    }

    /**
     * Comprehensive runner that verifies all tables, triggers all column/index migrations,
     * and seeds all default operational data.
     *
     * @return array<string, string> Log messages
     */
    public static function runFullMigrationAndSeed(PDO $pdo): array
    {
        $logs = [];

        // 1. Create Base Tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `system_settings` (
                `setting_key` VARCHAR(50) NOT NULL,
                `setting_value` VARCHAR(255) NOT NULL,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `staff_users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `full_name` VARCHAR(100) NOT NULL,
                `phone_number` VARCHAR(100) NOT NULL,
                `role` ENUM('admin', 'driver') NOT NULL,
                `auth_secret` VARCHAR(255) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_staff_phone` (`phone_number`),
                KEY `idx_staff_role_active` (`role`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `customers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `phone_number` VARCHAR(20) NOT NULL,
                `full_name` VARCHAR(100) NOT NULL,
                `delivery_address` TEXT NOT NULL,
                `landmark` VARCHAR(150) NULL DEFAULT NULL,
                `region` ENUM('Hanamkonda', 'Warangal') NOT NULL,
                `latitude` DECIMAL(10, 8) NULL DEFAULT NULL,
                `longitude` DECIMAL(11, 8) NULL DEFAULT NULL,
                `gate_photo_path` VARCHAR(255) NULL DEFAULT NULL,
                `is_location_verified` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_customers_phone` (`phone_number`),
                KEY `idx_customers_region` (`region`),
                KEY `idx_customers_verified` (`is_location_verified`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

            CREATE TABLE IF NOT EXISTS `products` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `telugu_name` VARCHAR(100) NOT NULL,
                `category` ENUM('standard', 'premium') NOT NULL DEFAULT 'standard',
                `price_per_half_kg` DECIMAL(8, 2) NOT NULL,
                `available_half_kg_stock` INT UNSIGNED NOT NULL DEFAULT 0,
                `image_path` VARCHAR(255) NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_products_active_category` (`is_active`, `category`),
                KEY `idx_products_stock` (`available_half_kg_stock`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `delivery_schedules` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `delivery_date` DATE NOT NULL,
                `delivery_day` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
                `target_region` ENUM('Hanamkonda', 'Warangal') NOT NULL,
                `order_open_datetime` DATETIME NOT NULL,
                `cutoff_datetime` DATETIME NOT NULL,
                `harvest_date` DATE NOT NULL,
                `is_ordering_open` TINYINT(1) NOT NULL DEFAULT 1,
                `status` ENUM('scheduled', 'open', 'closed', 'dispatched', 'completed') NOT NULL DEFAULT 'scheduled',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_run_date_region` (`delivery_date`, `target_region`),
                KEY `idx_run_lookup` (`target_region`, `is_ordering_open`, `cutoff_datetime`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `run_inventory` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `schedule_id` INT UNSIGNED NOT NULL,
                `product_id` INT UNSIGNED NOT NULL,
                `harvest_kg` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
                `available_half_kg_stock` INT UNSIGNED NOT NULL DEFAULT 0,
                `price_per_half_kg` DECIMAL(8, 2) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_schedule_product` (`schedule_id`, `product_id`),
                KEY `idx_run_inv_schedule` (`schedule_id`, `is_active`),
                CONSTRAINT `fk_run_inv_schedule` 
                    FOREIGN KEY (`schedule_id`) 
                    REFERENCES `delivery_schedules` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE CASCADE,
                CONSTRAINT `fk_run_inv_product` 
                    FOREIGN KEY (`product_id`) 
                    REFERENCES `products` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `orders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_code` VARCHAR(20) NOT NULL,
                `customer_id` INT UNSIGNED NOT NULL,
                `schedule_id` INT UNSIGNED NULL DEFAULT NULL,
                `subtotal` DECIMAL(8, 2) NOT NULL,
                `delivery_fee` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
                `total_amount` DECIMAL(8, 2) NOT NULL,
                `target_delivery_date` DATE NOT NULL,
                `order_status` ENUM('placed', 'packed', 'out_for_delivery', 'delivered', 'cancelled') NOT NULL DEFAULT 'placed',
                `payment_method` ENUM('COD', 'UPI') NOT NULL,
                `payment_status` ENUM('pending', 'verified', 'failed') NOT NULL DEFAULT 'pending',
                `assigned_driver_id` INT UNSIGNED NULL DEFAULT NULL,
                `route_sequence_number` INT NULL DEFAULT NULL,
                `route_leg_number` INT UNSIGNED NULL DEFAULT NULL,
                `delivery_notes` TEXT NULL DEFAULT NULL,
                `delivered_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_orders_code` (`order_code`),
                KEY `idx_orders_customer` (`customer_id`),
                KEY `idx_orders_schedule` (`schedule_id`),
                KEY `idx_orders_target_date` (`target_delivery_date`),
                KEY `idx_orders_status` (`order_status`),
                KEY `idx_orders_driver` (`assigned_driver_id`),
                KEY `idx_orders_route_seq` (`target_delivery_date`, `assigned_driver_id`, `route_sequence_number`),
                KEY `idx_orders_dispatch_leg` (`target_delivery_date`, `assigned_driver_id`, `route_leg_number`),
                CONSTRAINT `fk_orders_customer` 
                    FOREIGN KEY (`customer_id`) 
                    REFERENCES `customers` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE RESTRICT,
                CONSTRAINT `fk_orders_schedule` 
                    FOREIGN KEY (`schedule_id`) 
                    REFERENCES `delivery_schedules` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE SET NULL,
                CONSTRAINT `fk_orders_driver` 
                    FOREIGN KEY (`assigned_driver_id`) 
                    REFERENCES `staff_users` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `order_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` INT UNSIGNED NOT NULL,
                `product_id` INT UNSIGNED NOT NULL,
                `half_kg_quantity` INT UNSIGNED NOT NULL,
                `unit_price_applied` DECIMAL(8, 2) NOT NULL,
                `line_total` DECIMAL(8, 2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_order_items_order` (`order_id`),
                KEY `idx_order_items_product` (`product_id`),
                CONSTRAINT `fk_order_items_order` 
                    FOREIGN KEY (`order_id`) 
                    REFERENCES `orders` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE CASCADE,
                CONSTRAINT `fk_order_items_product` 
                    FOREIGN KEY (`product_id`) 
                    REFERENCES `products` (`id`) 
                    ON UPDATE CASCADE 
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $logs[] = 'Verified/created all 9 database tables.';

        // 2. Ensure columns & indexes
        self::ensureMigrated($pdo);
        $logs[] = 'Verified column `route_leg_number`, `schedule_id`, and index `idx_orders_dispatch_leg`.';

        // 3. Seed System Settings
        $settings = [
            'cutoff_time'           => '19:00:00',
            'mov_threshold'         => '1.00',
            'delivery_fee_amount'   => '0.00',
            'store_override_status' => 'AUTO',
            'store_hub_name'        => 'Prakruthi Siri Central Hub & Organic Farm',
            'store_hub_address'     => 'KU Cross Road, Naimnagar, Hanamkonda, Warangal - 506009',
            'store_hub_latitude'    => '18.02843900',
            'store_hub_longitude'   => '79.63594100',
            'store_whatsapp_number' => '919393767927',
        ];
        $stmt = $pdo->prepare("
            INSERT INTO `system_settings` (`setting_key`, `setting_value`)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
        ");
        foreach ($settings as $k => $v) {
            $stmt->execute([':k' => $k, ':v' => $v]);
        }
        $logs[] = 'Seeded default system settings.';

        // 4. Seed Staff Users
        $adminPass = password_hash('Admin@Prakruthi2026', PASSWORD_BCRYPT, ['cost' => 12]);
        $driverPin = password_hash('123456', PASSWORD_BCRYPT, ['cost' => 12]);

        $staff = [
            ['Platform Administrator', 'admin@prakruthisiri.com', 'admin', $adminPass],
            ['Ramesh Goud', '9876543210', 'driver', $driverPin],
            ['Suresh Kumar', '9876543211', 'driver', $driverPin],
        ];
        $staffStmt = $pdo->prepare("
            INSERT INTO `staff_users` (`full_name`, `phone_number`, `role`, `auth_secret`, `is_active`)
            VALUES (:name, :phone, :role, :secret, 1)
            ON DUPLICATE KEY UPDATE
                `full_name` = VALUES(`full_name`),
                `role` = VALUES(`role`),
                `auth_secret` = VALUES(`auth_secret`),
                `is_active` = 1
        ");
        foreach ($staff as $s) {
            $staffStmt->execute([':name' => $s[0], ':phone' => $s[1], ':role' => $s[2], ':secret' => $s[3]]);
        }
        $logs[] = 'Seeded administrator and driver accounts.';

        // 5. Seed Vegetables Catalog (8 products)
        $products = [
            ['Country Tomato', 'నాటు టమాటా', 'standard', 25.00, 80, 'uploads/products/country_tomato.svg'],
            ['Okra (Lady Finger)', 'బెండకాయ', 'standard', 30.00, 60, 'uploads/products/okra.svg'],
            ['Green Round Brinjal', 'వంకాయ', 'standard', 28.00, 50, 'uploads/products/brinjal.svg'],
            ['Fresh Ridge Gourd', 'బీరకాయ', 'standard', 35.00, 40, 'uploads/products/ridge_gourd.svg'],
            ['Organic Palak (Spinach)', 'పాలకూర', 'standard', 20.00, 70, 'uploads/products/spinach.svg'],
            ['Fresh Ivy Gourd (Dondakaya)', 'దొండకాయ', 'standard', 32.00, 45, 'uploads/products/ivy_gourd.svg'],
            ['Premium Hydroponic English Cucumber', 'ప్రీమియం సలాడ్ దోసకాయ', 'premium', 45.00, 30, 'uploads/products/english_cucumber.svg'],
            ['Premium French Haricot Beans', 'నాణ్యమైన ఫ్రెంచ్ బీన్స్', 'premium', 55.00, 35, 'uploads/products/french_beans.svg'],
        ];

        $prodCheck = $pdo->prepare("SELECT `id` FROM `products` WHERE `name` = :name LIMIT 1");
        $prodInsert = $pdo->prepare("
            INSERT INTO `products` (`name`, `telugu_name`, `category`, `price_per_half_kg`, `available_half_kg_stock`, `image_path`, `is_active`)
            VALUES (:name, :tel, :cat, :price, :stock, :img, 1)
        ");
        $prodUpdate = $pdo->prepare("
            UPDATE `products` SET
                `telugu_name` = :tel,
                `category` = :cat,
                `price_per_half_kg` = :price,
                `available_half_kg_stock` = :stock,
                `image_path` = :img,
                `is_active` = 1
            WHERE `id` = :id
        ");

        foreach ($products as $p) {
            $prodCheck->execute([':name' => $p[0]]);
            $existingId = $prodCheck->fetchColumn();
            if ($existingId) {
                $prodUpdate->execute([
                    ':id'    => $existingId,
                    ':tel'   => $p[1],
                    ':cat'   => $p[2],
                    ':price' => $p[3],
                    ':stock' => $p[4],
                    ':img'   => $p[5],
                ]);
            } else {
                $prodInsert->execute([
                    ':name'  => $p[0],
                    ':tel'   => $p[1],
                    ':cat'   => $p[2],
                    ':price' => $p[3],
                    ':stock' => $p[4],
                    ':img'   => $p[5],
                ]);
            }
        }
        $logs[] = 'Seeded 8 foundational organic vegetable varieties (0.5 kg base units).';

        // 6. Seed Active & Upcoming Delivery Schedules
        $now = TimeWindow::now();
        $timeWindow = new TimeWindow();
        $primaryRunDate = $timeWindow->getTargetDeliveryDate($now);

        $schedules = [
            [
                'delivery' => $primaryRunDate->format('Y-m-d'),
                'cutoff'   => $primaryRunDate->modify('-1 day')->format('Y-m-d 19:00:00'),
                'harvest'  => $primaryRunDate->modify('-1 day')->format('Y-m-d'),
                'open'     => 1,
            ],
            [
                'delivery' => $primaryRunDate->modify('+2 days')->format('Y-m-d'),
                'cutoff'   => $primaryRunDate->modify('+1 day')->format('Y-m-d 19:00:00'),
                'harvest'  => $primaryRunDate->modify('+1 day')->format('Y-m-d'),
                'open'     => 1,
            ],
        ];

        $schedStmt = $pdo->prepare("
            INSERT INTO `delivery_schedules` (`delivery_date`, `cutoff_datetime`, `harvest_date`, `is_ordering_open`, `status`)
            VALUES (:del, :cut, :har, :open, 'scheduled')
            ON DUPLICATE KEY UPDATE
                `cutoff_datetime` = VALUES(`cutoff_datetime`),
                `is_ordering_open` = VALUES(`is_ordering_open`)
        ");

        foreach ($schedules as $sc) {
            // Adjust cutoff if in past
            $cutoff = $sc['cutoff'];
            if (strtotime($cutoff) <= time()) {
                $cutoff = $now->modify('+12 hours')->format('Y-m-d H:i:s');
            }
            $schedStmt->execute([
                ':del'  => $sc['delivery'],
                ':cut'  => $cutoff,
                ':har'  => $sc['harvest'],
                ':open' => $sc['open'],
            ]);
        }
        $logs[] = 'Seeded active delivery schedules with open ordering windows.';

        // 7. Seed Demo Customers & Addresses
        $customers = [
            [
                'phone'   => '9988776655',
                'name'    => 'S. Rajendra Prasad',
                'addr'    => 'Plot 42, Green Meadows Colony',
                'lm'      => 'Near Pochamma Temple, Subedari',
                'reg'     => 'Hanamkonda',
                'lat'     => 17.98560000,
                'lng'     => 79.58920000,
                'gate'    => 'uploads/gates/sample_gate.svg',
                'ver'     => 1,
            ],
            [
                'phone'   => '9949166778',
                'name'    => 'G. Madhavi',
                'addr'    => '12-4-56, Ramannapet Road',
                'lm'      => 'Near Warangal Fort entrance',
                'reg'     => 'Warangal',
                'lat'     => 17.96200000,
                'lng'     => 79.60500000,
                'gate'    => null,
                'ver'     => 1,
            ],
        ];

        $custCheck = $pdo->prepare("SELECT `id` FROM `customers` WHERE `phone_number` = :p LIMIT 1");
        $custInsert = $pdo->prepare("
            INSERT INTO `customers` (`phone_number`, `full_name`, `delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `gate_photo_path`, `is_location_verified`)
            VALUES (:p, :name, :addr, :lm, :reg, :lat, :lng, :gate, :ver)
        ");
        $custUpdate = $pdo->prepare("
            UPDATE `customers` SET
                `full_name` = :name,
                `delivery_address` = :addr,
                `landmark` = :lm,
                `region` = :reg,
                `latitude` = :lat,
                `longitude` = :lng,
                `gate_photo_path` = :gate,
                `is_location_verified` = :ver
            WHERE `id` = :id
        ");

        foreach ($customers as $c) {
            $custCheck->execute([':p' => $c['phone']]);
            $cid = $custCheck->fetchColumn();
            if ($cid) {
                $custUpdate->execute([
                    ':id'   => $cid,
                    ':name' => $c['name'],
                    ':addr' => $c['addr'],
                    ':lm'   => $c['lm'],
                    ':reg'  => $c['reg'],
                    ':lat'  => $c['lat'],
                    ':lng'  => $c['lng'],
                    ':gate' => $c['gate'],
                    ':ver'  => $c['ver'],
                ]);
            } else {
                $custInsert->execute([
                    ':p'    => $c['phone'],
                    ':name' => $c['name'],
                    ':addr' => $c['addr'],
                    ':lm'   => $c['lm'],
                    ':reg'  => $c['reg'],
                    ':lat'  => $c['lat'],
                    ':lng'  => $c['lng'],
                    ':gate' => $c['gate'],
                    ':ver'  => $c['ver'],
                ]);
                $cid = (int) $pdo->lastInsertId();
            }

            // Also seed default address into customer_addresses
            $addrCheck = $pdo->prepare("SELECT `id` FROM `customer_addresses` WHERE `customer_id` = :cid AND `label` = 'Home' LIMIT 1");
            $addrCheck->execute([':cid' => $cid]);
            if (!$addrCheck->fetchColumn()) {
                $addrInsert = $pdo->prepare("
                    INSERT INTO `customer_addresses` (`customer_id`, `label`, `delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `gate_photo_path`, `is_location_verified`, `is_default`)
                    VALUES (:cid, 'Home', :addr, :lm, :reg, :lat, :lng, :gate, :ver, 1)
                ");
                $addrInsert->execute([
                    ':cid'  => $cid,
                    ':addr' => $c['addr'],
                    ':lm'   => $c['lm'],
                    ':reg'  => $c['reg'],
                    ':lat'  => $c['lat'],
                    ':lng'  => $c['lng'],
                    ':gate' => $c['gate'],
                    ':ver'  => $c['ver'],
                ]);
            }
        }
        $logs[] = 'Seeded demo customers and verified address records.';

        return $logs;
    }
}
