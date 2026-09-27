-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 27, 2026 at 06:06 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u522254309_prakruthi_siri`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `delivery_address` text NOT NULL,
  `landmark` varchar(150) DEFAULT NULL,
  `region` enum('Hanamkonda','Warangal') NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `gate_photo_path` varchar(255) DEFAULT NULL,
  `is_location_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

CREATE TABLE `customer_addresses` (
  `id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(50) NOT NULL DEFAULT 'Home',
  `delivery_address` text NOT NULL,
  `landmark` varchar(150) DEFAULT NULL,
  `region` enum('Hanamkonda','Warangal') NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `gate_photo_path` varchar(255) DEFAULT NULL,
  `is_location_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_schedules`
--

CREATE TABLE `delivery_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL DEFAULT 'Thursday',
  `target_region` enum('Hanamkonda','Warangal') NOT NULL DEFAULT 'Hanamkonda',
  `order_open_datetime` datetime NOT NULL DEFAULT '2026-01-01 05:00:00',
  `cutoff_datetime` datetime NOT NULL,
  `harvest_date` date NOT NULL,
  `is_ordering_open` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('scheduled','open','closed','dispatched','completed') NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_code` varchar(20) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED DEFAULT NULL,
  `subtotal` decimal(8,2) NOT NULL,
  `delivery_fee` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(8,2) NOT NULL,
  `target_delivery_date` date NOT NULL,
  `order_status` enum('placed','packed','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'placed',
  `payment_method` enum('COD','UPI') NOT NULL,
  `payment_status` enum('pending','verified','failed') NOT NULL DEFAULT 'pending',
  `assigned_driver_id` int(10) UNSIGNED DEFAULT NULL,
  `route_sequence_number` int(11) DEFAULT NULL,
  `route_leg_number` int(10) UNSIGNED DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `half_kg_quantity` int(10) UNSIGNED NOT NULL,
  `unit_price_applied` decimal(8,2) NOT NULL,
  `line_total` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `telugu_name` varchar(100) NOT NULL,
  `category` enum('standard','premium') NOT NULL DEFAULT 'standard',
  `unit_label` varchar(50) NOT NULL DEFAULT '0.5 kg (500g)',
  `unit_weight_kg` decimal(5,3) NOT NULL DEFAULT 0.500,
  `price_per_half_kg` decimal(8,2) NOT NULL,
  `available_half_kg_stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `run_inventory`
--

CREATE TABLE `run_inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `harvest_kg` decimal(8,2) NOT NULL DEFAULT 0.00,
  `available_half_kg_stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `price_per_half_kg` decimal(8,2) NOT NULL,
  `unit_label` varchar(50) NOT NULL DEFAULT '0.5 kg (500g)',
  `unit_weight_kg` decimal(5,3) NOT NULL DEFAULT 0.500,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_users`
--

CREATE TABLE `staff_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(100) NOT NULL,
  `role` enum('admin','driver') NOT NULL,
  `auth_secret` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('active_delivery_date', '2026-09-20', '2026-09-18 16:49:22'),
('cutoff_time', '19:00:00', '2026-09-15 07:35:04'),
('delivery_fee_amount', '0.00', '2026-09-15 07:35:04'),
('mov_threshold', '1.00', '2026-09-15 07:35:04'),
('store_hub_address', 'KU Cross Road, Naimnagar, Hanamkonda, Warangal - 506009', '2026-09-16 07:23:43'),
('store_hub_latitude', '18.02843900', '2026-09-15 07:35:04'),
('store_hub_longitude', '79.63594100', '2026-09-15 07:35:04'),
('store_hub_name', 'Prakruthi Siri Central Hub & Organic Farm', '2026-09-15 07:35:04'),
('store_override_status', 'AUTO', '2026-09-15 07:35:04'),
('store_whatsapp_number', '919393767927', '2026-09-15 12:37:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_customers_phone` (`phone_number`),
  ADD KEY `idx_customers_region` (`region`),
  ADD KEY `idx_customers_verified` (`is_location_verified`);

--
-- Indexes for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_address_customer` (`customer_id`),
  ADD KEY `idx_address_default` (`customer_id`,`is_default`);

--
-- Indexes for table `delivery_schedules`
--
ALTER TABLE `delivery_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_run_date_region` (`delivery_date`,`target_region`),
  ADD KEY `idx_run_lookup` (`target_region`,`is_ordering_open`,`cutoff_datetime`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_orders_code` (`order_code`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_target_date` (`target_delivery_date`),
  ADD KEY `idx_orders_status` (`order_status`),
  ADD KEY `idx_orders_driver` (`assigned_driver_id`),
  ADD KEY `idx_orders_route_seq` (`target_delivery_date`,`assigned_driver_id`,`route_sequence_number`),
  ADD KEY `idx_orders_dispatch_leg` (`target_delivery_date`,`assigned_driver_id`,`route_leg_number`),
  ADD KEY `idx_orders_target_date_status` (`target_delivery_date`,`order_status`),
  ADD KEY `idx_orders_created_at_status` (`created_at`,`order_status`),
  ADD KEY `idx_orders_schedule` (`schedule_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`),
  ADD KEY `idx_order_items_order_product` (`order_id`,`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_products_active_category` (`is_active`,`category`),
  ADD KEY `idx_products_stock` (`available_half_kg_stock`);

--
-- Indexes for table `run_inventory`
--
ALTER TABLE `run_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_schedule_product` (`schedule_id`,`product_id`),
  ADD KEY `idx_run_inv_schedule` (`schedule_id`,`is_active`),
  ADD KEY `fk_run_inv_product` (`product_id`);

--
-- Indexes for table `staff_users`
--
ALTER TABLE `staff_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_staff_phone` (`phone_number`),
  ADD KEY `idx_staff_role_active` (`role`,`is_active`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_schedules`
--
ALTER TABLE `delivery_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `run_inventory`
--
ALTER TABLE `run_inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_users`
--
ALTER TABLE `staff_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD CONSTRAINT `fk_addresses_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `run_inventory`
--
ALTER TABLE `run_inventory`
  ADD CONSTRAINT `fk_run_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_run_inv_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `delivery_schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
