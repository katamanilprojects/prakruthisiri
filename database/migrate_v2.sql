-- =============================================================================
-- Prakruthi Siri — Phase 1 Schema Migration (v2)
-- Idempotent: safe to run multiple times on the same database.
-- Run against: u522254309_prakruthi_siri
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. REQ-FARM-01 — Add 'farmer' to staff_users.role ENUM
-- -----------------------------------------------------------------------------
ALTER TABLE `staff_users`
  MODIFY COLUMN `role` ENUM('admin','driver','farmer') NOT NULL;

-- -----------------------------------------------------------------------------
-- 2. REQ-FARM-02 / REQ-TRC-01 — farm_plots & crop_milestones
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `farm_plots` (
  `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `plot_number`  tinyint(1) UNSIGNED NOT NULL COMMENT '1–4',
  `quarter_name` varchar(50) NOT NULL DEFAULT '',
  `crop_type`    varchar(100) DEFAULT NULL,
  `status`       ENUM('land_preparation','sown','vegetative','flowering','active_harvesting','fallow') NOT NULL DEFAULT 'land_preparation',
  `sown_date`    date DEFAULT NULL,
  `notes`        text DEFAULT NULL,
  `updated_at`   timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plot_number` (`plot_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the 4 quarter-plots if not already present
INSERT IGNORE INTO `farm_plots` (`plot_number`, `quarter_name`, `status`) VALUES
  (1, 'Quarter 1', 'active_harvesting'),
  (2, 'Quarter 2', 'vegetative'),
  (3, 'Quarter 3', 'sown'),
  (4, 'Quarter 4', 'land_preparation');

CREATE TABLE IF NOT EXISTS `crop_milestones` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `plot_id`    int(10) UNSIGNED NOT NULL,
  `stage`      ENUM('sowing','fertilizer_application','flowering','harvesting','other') NOT NULL DEFAULT 'other',
  `photo_path` varchar(255) DEFAULT NULL,
  `notes`      text DEFAULT NULL,
  `logged_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_milestones_plot` (`plot_id`),
  CONSTRAINT `fk_milestone_plot` FOREIGN KEY (`plot_id`) REFERENCES `farm_plots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Batch Traceability — add plot_id FK to run_inventory
-- -----------------------------------------------------------------------------
ALTER TABLE `run_inventory`
  ADD COLUMN IF NOT EXISTS `plot_id` int(10) UNSIGNED DEFAULT NULL AFTER `unit_weight_kg`;

-- Add FK only if it doesn't already exist
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'run_inventory'
    AND CONSTRAINT_NAME = 'fk_run_inv_plot'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `run_inventory` ADD CONSTRAINT `fk_run_inv_plot` FOREIGN KEY (`plot_id`) REFERENCES `farm_plots` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 4. REQ-EXP-01 — expansion_leads
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expansion_leads` (
  `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) NOT NULL,
  `full_name`    varchar(100) DEFAULT NULL,
  `locality`     varchar(150) NOT NULL,
  `landmark`     varchar(150) DEFAULT NULL,
  `latitude`     decimal(10,8) DEFAULT NULL,
  `longitude`    decimal(11,8) DEFAULT NULL,
  `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leads_phone` (`phone_number`),
  KEY `idx_leads_locality` (`locality`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. REQ-LOC-04 — hub_locations + delivery_schedules FK columns
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hub_locations` (
  `id`                   int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                 varchar(100) NOT NULL,
  `region`               varchar(50) DEFAULT NULL,
  `address`              varchar(255) DEFAULT NULL,
  `latitude`             decimal(10,8) DEFAULT NULL,
  `longitude`            decimal(11,8) DEFAULT NULL,
  `is_source`            tinyint(1) NOT NULL DEFAULT 1,
  `is_destination`       tinyint(1) NOT NULL DEFAULT 1,
  `is_default_source`    tinyint(1) NOT NULL DEFAULT 0,
  `is_default_destination` tinyint(1) NOT NULL DEFAULT 0,
  `is_active`            tinyint(1) NOT NULL DEFAULT 1,
  `created_at`           timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hub_source` (`is_source`,`is_active`),
  KEY `idx_hub_dest` (`is_destination`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the existing system_settings hub as the default hub_location
INSERT IGNORE INTO `hub_locations`
  (`id`, `name`, `region`, `address`, `latitude`, `longitude`, `is_source`, `is_destination`, `is_default_source`, `is_default_destination`, `is_active`)
VALUES
  (1, 'Prakruthi Siri Central Hub & Organic Farm', 'Hanamkonda',
   'KU Cross Road, Naimnagar, Hanamkonda, Warangal - 506009',
   18.02843900, 79.63594100, 1, 1, 1, 1, 1);

-- Add source_hub_id / destination_hub_id to delivery_schedules (idempotent)
ALTER TABLE `delivery_schedules`
  ADD COLUMN IF NOT EXISTS `source_hub_id`      int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `destination_hub_id` int(10) UNSIGNED NULL DEFAULT NULL AFTER `source_hub_id`;

-- Add FKs only if absent
SET @fk1 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_schedules'
    AND CONSTRAINT_NAME = 'fk_sched_source_hub' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql1 = IF(@fk1 = 0,
  'ALTER TABLE `delivery_schedules` ADD CONSTRAINT `fk_sched_source_hub` FOREIGN KEY (`source_hub_id`) REFERENCES `hub_locations` (`id`) ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @fk2 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_schedules'
    AND CONSTRAINT_NAME = 'fk_sched_dest_hub' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql2 = IF(@fk2 = 0,
  'ALTER TABLE `delivery_schedules` ADD CONSTRAINT `fk_sched_dest_hub` FOREIGN KEY (`destination_hub_id`) REFERENCES `hub_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

-- -----------------------------------------------------------------------------
-- 6. REQ-ETA-01 — ETA time window columns on orders
-- -----------------------------------------------------------------------------
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `estimated_delivery_start` TIME DEFAULT NULL AFTER `route_leg_number`,
  ADD COLUMN IF NOT EXISTS `estimated_delivery_end`   TIME DEFAULT NULL AFTER `estimated_delivery_start`;

-- -----------------------------------------------------------------------------
-- 7. REQ-PROD-01 — pricing_unit on products, order_items, run_inventory
--    Also add aliased columns (unit_price, available_stock) to run_inventory
--    so non-weight units decrement correctly without breaking existing queries.
-- -----------------------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `pricing_unit` ENUM('half_kg','piece','bunch') NOT NULL DEFAULT 'half_kg' AFTER `unit_weight_kg`;

ALTER TABLE `order_items`
  ADD COLUMN IF NOT EXISTS `pricing_unit` ENUM('half_kg','piece','bunch') NOT NULL DEFAULT 'half_kg' AFTER `half_kg_quantity`;

-- run_inventory: add unit_price and available_stock as real columns (not views)
-- so existing code using price_per_half_kg / available_half_kg_stock still works,
-- and new code can use the generic names.
ALTER TABLE `run_inventory`
  ADD COLUMN IF NOT EXISTS `pricing_unit`    ENUM('half_kg','piece','bunch') NOT NULL DEFAULT 'half_kg' AFTER `unit_weight_kg`,
  ADD COLUMN IF NOT EXISTS `unit_price`      decimal(8,2) GENERATED ALWAYS AS (`price_per_half_kg`) VIRTUAL AFTER `price_per_half_kg`,
  ADD COLUMN IF NOT EXISTS `available_stock` int(10) UNSIGNED GENERATED ALWAYS AS (`available_half_kg_stock`) VIRTUAL AFTER `available_half_kg_stock`;

-- -----------------------------------------------------------------------------
-- 8. Missing FK — orders.schedule_id → delivery_schedules.id
-- -----------------------------------------------------------------------------
SET @fk3 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
    AND CONSTRAINT_NAME = 'fk_orders_schedule' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql3 = IF(@fk3 = 0,
  'ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `delivery_schedules` (`id`) ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;

-- -----------------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 1;
-- =============================================================================
-- Migration complete.
-- =============================================================================
