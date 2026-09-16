-- ============================================================================
-- Prakruthi Siri - Chemical-Free Organic Vegetables Platform
-- Database Schema for MySQL 8.0+
-- Service Regions: Hanamkonda, Warangal
-- Base Vegetable Unit: 0.5 kg (1/2 kg)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Table: system_settings
-- Global platform operational parameters (cutoff time, MOV, delivery fees)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(50) NOT NULL,
    `setting_value` VARCHAR(255) NOT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: staff_users
-- Platform administrators and field delivery personnel (Drivers)
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Table: customers
-- Customers residing in Hanamkonda or Warangal
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Table: customer_addresses
-- Decoupled multiple saved delivery locations per customer (Home, Office, Parents)
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Table: products
-- Catalog of organic vegetables cataloged strictly in 0.5 kg (1/2 kg) base units
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- ----------------------------------------------------------------------------
-- Table: delivery_schedules (Single Source of Truth for Farm Runs)
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Table: run_inventory
-- Day-wise isolated harvest stock and price per delivery run
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Table: orders
-- Customer orders with target delivery date, run schedule, and driver routing assignments
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- Table: order_items
-- Line items purchased in 0.5 kg increments, tied to specific products
-- ----------------------------------------------------------------------------
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

SET FOREIGN_KEY_CHECKS = 1;
