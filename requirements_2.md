# Prakruthi Siri (ప్రకృతి సిరి) – System Requirements & Product Specification Document
> **Document Version:** 2.1 (Production Baseline)  
> **Status:** Fully Implemented, Verified & Synchronized with Active Codebase & MariaDB  
> **Target Environment:** Standard Shared PHP 8.x / MariaDB 10.4+ (Hostinger Production & Local XAMPP)

---

## 1. System Overview & Operational Context

### 1.1 Farm Setup & Cultivation Cycle

* **Land Parcel:** 3 acres of certified organic farmland located in Telangana.
* **Crop Partitioning:** The land is partitioned into 4 equal quarters (approx. 0.75 acres each: Quarter 1, Quarter 2, Quarter 3, Quarter 4).
* **Staggered Sowing:** Sowing across the 4 plots is staggered with a 3-month gap between plots to sustain continuous year-round harvests and prevent cyclic dry spells.
* **Agronomic Lifecycle Stages:** Each plot quarter tracks one of 6 lifecycle stages:
  1. `land_preparation` (భూమి సిద్ధం చేయడం)
  2. `sown` (విత్తనం నాటడం)
  3. `vegetative` (మొక్కల పెరుగుదల)
  4. `flowering` (పూత దశ)
  5. `active_harvesting` (కూరగాయల కోత దశ)
  6. `fallow` (విశ్రాంతి దశ)
* **Yield Dynamics:** Daily harvest yields vary by plot. Yield forecasts are declared by the farmer 48 hours before delivery and synced directly to schedule-level inventory (`run_inventory`).
* **Packaging & Multi-Unit Pricing (`REQ-PROD-01`):** The system natively supports three pricing and measurement units via the `pricing_unit` ENUM:
  * `half_kg` — Weight-based produce sold in 0.5 kg (500g) packets (e.g., tomatoes, okra, gourds, chillies).
  * `piece` — Individual unit items (e.g., bottle gourd / సొరకాయ per piece).
  * `bunch` — Bundled produce (e.g., leafy greens / పాలకూర, గోంగూర, కొత్తిమీర).
* **Active 12-Item Farm Catalog:** The catalog is standardized to 12 chemical-free produce items with high-resolution JPEG imagery (all legacy SVG/WebP assets have been removed):
  1. Country Tomato (Tomato / నాటు టమాటా) — ₹30.00 / 0.5 kg (`half_kg`)
  2. Green Chilli (Mirchi / పచ్చిమిర్చి) — ₹30.00 / 0.5 kg (`half_kg`)
  3. Okra (Benda / బెండకాయ) — ₹30.00 / 0.5 kg (`half_kg`)
  4. Brinjal (Vankaya / వంకాయ) — ₹30.00 / 0.5 kg (`half_kg`)
  5. Ridge Gourd (Beera / బీరకాయ) — ₹35.00 / 0.5 kg (`half_kg`)
  6. Bottle Gourd (Sora / సొరకాయ) — ₹20.00 / 1 Piece (`piece`)
  7. Bitter Gourd (Kakara / కాకరకాయ) — ₹30.00 / 0.5 kg (`half_kg`)
  8. Spinach (Palakura / పాలకూర) — ₹20.00 / 200g Bunch (`bunch`)
  9. Green Sorrel (Chukkakura / చుక్కకూర) — ₹20.00 / 200g Bunch (`bunch`)
  10. Roselle Leaves (Gongura / గోంగూర) — ₹20.00 / 300g Bunch (`bunch`)
  11. Fenugreek Leaves (Methi / మెంతికూర) — ₹20.00 / 200g Bunch (`bunch`)
  12. Coriander Leaves (Kothimeer / కొత్తిమీర) — ₹20.00 / 150g Bunch (`bunch`)

---

### 1.2 Logistics, Distribution & Contacts

* **Permitted Delivery Regions:** Strictly restricted to the urban boundaries of **Hanamakonda** and **Warangal**.
* **Strict Route Exclusions:** **Kazipet** (longitude < 79.55°E / latitude < 18.00°N) is strictly excluded due to two-wheeler transit time constraints. Locations beyond an 11.5 km radial distance from the central hub are rejected with user-friendly distance feedback.
* **Delivery Schedule Cadence:**
  * **Hanamakonda:** Monday / Thursday delivery runs.
  * **Warangal:** Wednesday / Saturday delivery runs.
* **Single Delivery Driver:** Exactly one dedicated delivery driver operates a two-wheeler for all delivery runs:
  * **Driver Name:** Chandu
  * **Driver Mobile:** `9550899622` (Active in `staff_users`, role `driver`, PIN: `123456`)
* **Customer WhatsApp Support:**
  * **Store WhatsApp Number:** `+91-7702162276` (Stored in `system_settings.store_whatsapp_number`)
* **Farmer Account:**
  * **Farmer Name:** Mallesh Farmer
  * **Farmer Mobile:** `9876543212` (Active in `staff_users`, role `farmer`, PIN: `123456`)
* **Platform Administrator:**
  * **Admin Identity:** `admin@prakruthisiri.com` (Active in `staff_users`, role `admin`)
* **Zero-Cost Constraint:** No paid SaaS subscriptions, mapping API keys, or SMS gateway charges. Uses native HTML5 Geolocation, device camera APIs, free inline/Server QR generation (`api.qrserver.com`), chained Google Maps direction URLs, and `https://wa.me/` deep links.

---

### 1.3 Target Audience Profile & UX Standards

* **Customer Persona:** WhatsApp-first consumers who prefer low-friction web interactions without mandatory app downloads or complex passwords.
* **Farmer Persona:** Mobile-first field user needing a simplified, isolated portal (`/farmer/`) with large touch targets and no financial clutter.
* **Single-Language Standard (`REQ-I18N-01`):** Pure single-language text switched via an explicit `[EN | తె]` top/header toggle switch on every portal. No combined bilingual stacking (e.g., avoided "టమాట / Country Tomato"). Preference persists in `localStorage` across page navigation.
* **Organic Radical Transparency (`REQ-TRC-01`, `REQ-TRC-02`):** Direct-to-consumer traceability linking every delivery batch to its origin 0.75-acre quarter-plot, sowing dates, organic inputs (Jeevamrutham, Neem oil), milestone photos, and embedded organic farming video tutorials.

---

## 2. Current State Audit: Implemented Codebase & Database

### 2.1 Database Schema (`u522254309_prakruthi_siri`)

The live MariaDB database contains the following verified tables:

* **`customers`:** Primary customer identity keyed by unique 10-digit `phone_number`. Stores `full_name`, `delivery_address`, `landmark`, `region` (`ENUM('Hanamkonda', 'Warangal')`), `latitude`, `longitude`, `gate_photo_path`, and `is_location_verified`.
* **`customer_addresses`:** Multi-address auxiliary store supporting customer delivery address histories.
* **`delivery_schedules`:** Scheduled delivery runs by date and day. Contains `target_region`, `order_open_datetime`, `cutoff_datetime`, status flags, and dynamic logistics hub foreign keys:
  * `source_hub_id` (`INT UNSIGNED` &rarr; `hub_locations.id`)
  * `destination_hub_id` (`INT UNSIGNED NULL` &rarr; `hub_locations.id`, supports "Finish at Last Stop")
* **`hub_locations`:** Logistics hubs and operating points. Fields: `name`, `region`, `address`, `latitude`, `longitude`, `is_source`, `is_destination`, `is_default_source`, `is_default_destination`, `is_active`.
* **`farm_plots`:** 4 quarter-plots (Quarter 1 to 4). Fields: `plot_number`, `quarter_name`, `crop_type`, `status` (ENUM of 6 agronomic stages), `sown_date`, `notes`, `updated_at`.
* **`crop_milestones`:** Photographic and agronomic stage audit log. Fields: `plot_id`, `stage`, `photo_path`, `notes`, `logged_at`.
* **`expansion_leads`:** Demand capture table for unserved localities. Fields: `phone_number`, `full_name`, `locality`, `landmark`, `latitude`, `longitude`, `created_at`.
* **`products`:** 12 active organic vegetables. Fields: `name`, `telugu_name`, `category`, `price_per_half_kg`, `pricing_unit` (`ENUM('half_kg', 'piece', 'bunch')`), `unit_label`, `unit_weight_kg`, `image_path` (JPEG), `available_half_kg_stock`, `is_active`.
* **`run_inventory`:** Schedule-isolated inventory. Fields: `schedule_id`, `product_id`, `harvest_kg`, `available_half_kg_stock`, `price_per_half_kg`, `pricing_unit`, `unit_label`, `unit_weight_kg`, `plot_id`, `is_active`, plus virtual generated columns:
  * `unit_price` (`AS (price_per_half_kg) VIRTUAL`)
  * `available_stock` (`AS (available_half_kg_stock) VIRTUAL`)
* **`orders`:** Order headers. Fields: `customer_id`, `schedule_id`, `order_code`, `target_delivery_date`, `order_status`, `payment_method`, `payment_status`, `subtotal`, `delivery_fee`, `total_amount`, `assigned_driver_id`, `route_sequence_number`, `route_leg_number`, `estimated_delivery_start` (`TIME`), `estimated_delivery_end` (`TIME`), `delivered_at`. Foreign key: `fk_orders_schedule` on `schedule_id`.
* **`order_items`:** Line items. Fields: `order_id`, `product_id`, `half_kg_quantity`, `unit_price_applied`, `pricing_unit`, `line_total`.
* **`staff_users`:** Role-based operational access. `role` ENUM: `'admin'`, `'driver'`, `'farmer'`.
* **`system_settings`:** Key-value configuration (`store_whatsapp_number`, `cutoff_time`, `mov_threshold`, `delivery_fee_amount`, `store_hub_address`, `store_hub_latitude`, `store_hub_longitude`, `store_hub_name`, `store_override_status`).

---

### 2.2 Features Verified in the Active Codebase

* **1-Tap Region Gating Modal (`REQ-LOC-01`):** Storefront intercepts first-time visitors with an immediate region selector modal (`[ Warangal ]` / `[ Hanamakonda ]`), storing preference in `localStorage.getItem('ps_selected_region')`. Phone onboarding is deferred to cart checkout.
* **Strict Catalog Locking (`REQ-LOC-02`):** Prevents cross-region leakage. If a Warangal customer visits during an active Hanamakonda run, vegetables are completely hidden and replaced with an informative lock card showing Warangal's next unlock schedule and a 1-tap WhatsApp reminder deep link.
* **Geofence & Kazipet Exclusion (`REQ-LOC-03`):** [`src/GeoFenceService.php`](file:///Applications/XAMPP/xamppfiles/htdocs/hostinger/prakruthisiri/src/GeoFenceService.php) enforces an 11.5 km maximum hub radius and blocks western coordinates (< 79.55°E) with clear Telugu rejection notices.
* **Expansion Demand Waitlist (`REQ-EXP-01`):** Out-of-bounds orders seamlessly prompt the customer to register for their colony's waitlist via [`public/api/expansion-lead.php`](file:///Applications/XAMPP/xamppfiles/htdocs/hostinger/prakruthisiri/public/api/expansion-lead.php), saving their phone, name, and GPS coordinates without discarding user input.
* **Delivery ETA Window (`REQ-ETA-01`):** Order tracking displays an expected 1-to-2-hour delivery window (e.g., *"Thursday between 08:30 AM – 10:00 AM"*) populated during route sequencing in [`admin/api/dispatch-api.php`](file:///Applications/XAMPP/xamppfiles/htdocs/hostinger/prakruthisiri/admin/api/dispatch-api.php), with an automatic sequenced fallback.
* **Real-Time Queue Position Tracking (`REQ-ETA-02`):** Tracking screen displays dynamic queue states: *"Stop X of Y"* during Placed/Packed, and *"Driver is currently at Stop Z; your delivery is Stop X"* during Out for Delivery.
* **Dedicated Farmer Role & Portal (`REQ-FARM-01`):** Isolated mobile-first portal at `/farmer/` with dedicated PIN login (`farmer/login.php`), session guard (`farmer/auth_guard.php`), and restricted views omitting customer PII and financial metrics.
* **4 Quarter-Plot Lifecycle Manager (`REQ-FARM-02`):** Interactive dashboard at `farmer/index.php` and `farmer/api/plot-api.php` managing the 4 staggered quarter-plots (crop types, stage, sowing dates, and field notes).
* **Farmer Harvest Yield Entry (`REQ-FARM-03`):** Harvest estimate interface at `farmer/harvest.php` and `farmer/api/harvest-api.php` pushing declared kilograms directly into `run_inventory`.
* **Crop Milestone Photo Logger (`REQ-TRC-01`):** Native camera capture interface at `farmer/milestones.php` and `farmer/api/milestone-api.php` uploading verified photos to `uploads/milestones/` tagged by plot and cultivation stage.
* **Public Batch Transparency & QR Traceability (`REQ-TRC-02`):** Public transparency page at `public/batch.php` rendering plot origin, sowing dates, organic inputs, dawn harvest confirmation, and an embedded zero-cost YouTube organic farming tutorial. Dynamic QR codes are generated on receipts and tracking pages.
* **Assisted Proxy Ordering (`REQ-ADM-01`):** Admin booking interface for phone and WhatsApp orders directly integrated into daily operations.
* **1-Click WhatsApp Menu Generator (`REQ-ADM-02`):** Generates clean single-language Telugu and English formatted broadcast messages with deep ordering links and instant clipboard copying in `admin/daily-hub.php`.
* **Consolidated 4-Step Daily Operations Hub (`REQ-ADM-03`):** Unified single-screen operational hub at `admin/daily-hub.php` consolidating:
  1. Harvest & Pricing Review
  2. Assisted Proxy Ordering
  3. WhatsApp Menu Generator
  4. Printable Harvest Picking Sheet & Customer Packing Tags (`@media print`)
* **Dynamic Origin & Return Hubs (`REQ-LOC-04`):** Hub manager at `admin/locations.php` and `src/RouteDispatchService.php` supporting multi-point dispatch routing (selectable pickup hubs and return destinations or "Finish at Last Customer Stop").
* **Multi-Unit Pricing Engine (`REQ-PROD-01`):** Unified order, inventory, and picking sheet engine supporting weight units (`half_kg`) alongside piece and bunch units (`piece`, `bunch`).
* **Unified Language Toggle (`REQ-I18N-01`):** Header `[EN | తె]` toggle switches all storefront, tracking, farmer, driver, and admin interfaces between pure English and pure Telugu without dual-stacked text.

---

## 3. Deficiencies Resolved in Current Release

| Deficiencies in Prior Version | Resolution in Current Implementation |
|---|---|
| **Cross-Region Catalog Leakage** | Storefront requires 1-tap region selection upfront (`ps_selected_region`); displays locked card with WhatsApp reminder if the selected region is closed. |
| **Dead-End Rejection for Unserved Areas** | Out-of-bounds coordinates transition to an Expansion Waitlist modal, persisting leads to `expansion_leads` via idempotent API. |
| **Missing Delivery ETA & Queue Position** | `track.php` displays 1-to-2-hour delivery windows (`estimated_delivery_start/end`) and contextual stop progress ("Stop X of Y" / "Driver at Stop Z"). |
| **Admin UI Overload (8 Fragmented Pages)** | Consolidated into a linear 4-step daily workflow on `admin/daily-hub.php`. |
| **Missing Isolated Farmer Role** | Implemented `farmer` role in `staff_users` and built a dedicated mobile portal at `/farmer/`. |
| **Bilingual Visual Clutter (Dual-Stacked Text)** | Replaced slash-stacked labels with persistent top/header `[EN \| తె]` language toggles across all portals. |
| **Hardcoded Route Origin / Return Hubs** | Implemented `hub_locations` table, admin management screen, and multi-hub dispatch URL generation. |
| **Absence of Batch Traceability** | Seeded 4 quarter-plots, built milestone photo logging, and launched public batch traceability with QR codes. |
| **Hardcoded Half-Kg Packaging Schema** | Migrated schema and services to support `pricing_unit` (`half_kg`, `piece`, `bunch`) with decoupled picking sheet tallies. |

---

## 4. Implemented Operational Harvest Flows

### Flow 1: Scheduled Pre-Order Flow (Default Model)

*Applied for predictable crops (tomatoes, chillies, okra, brinjal, gourds).*

```
T-2 Days (Evening)          T-1 Day (13-Hour Window)      T-Day (Dawn: 05:00 - 08:00 AM)   T-Day (Morning: 08:30 AM - 01:00 PM)
┌──────────────────────┐   ┌────────────────────────┐   ┌───────────────────────────┐   ┌────────────────────────────┐
│ Farmer Harvest Est.  │   │ Ordering Window Opens  │   │ Dawn Picking & Packing    │   │ Delivery Route Execution   │
│ • Farmer inputs kg   │──>│ • WhatsApp blast sent  │──>│ • Harvest sheet tally     │──>│ • Driver loads packed bags │
│ • Safety buffer set  │   │ • Proxy & web orders   │   │ • Pack 0.5kg/bunch bundles│   │ • Customer views queue/ETA │
│ • Schedule unlocked  │   │ • Auto-closes on cutoff│   │ • Route sequenced         │   │ • Chandu completes stops   │
└──────────────────────┘   └────────────────────────┘   └───────────────────────────┘   └────────────────────────────┘
```

1. **T-2 Days (48h Before Delivery - 06:00 PM):**
   * Farmer Mallesh inspects the active quarter and inputs estimated kilograms via `/farmer/harvest.php`.
   * Forecast syncs to `run_inventory` as available stock for the target schedule.
2. **T-1 Day (Ordering Window - 07:00 AM to 08:00 PM):**
   * Admin generates formatted broadcast menu on `admin/daily-hub.php` (Step 3) and copies it to customer WhatsApp groups.
   * Customers access the open region catalog; opposite-region customers view locked schedule cards.
   * Admin enters offline phone orders via Assisted Proxy Ordering (Step 2).
   * Ordering closes automatically at cutoff time (08:00 PM).
3. **T-Day Dawn (Delivery Day - 05:00 AM to 08:00 AM):**
   * Farmer prints the consolidated Harvest Picking Sheet from `admin/daily-hub.php` (Step 4).
   * Fresh produce is harvested, weighed, sorted into customer crates, and labeled with customer packing tags.
4. **T-Day Dispatch (08:30 AM to 01:00 PM):**
   * Admin sequences the route in `admin/api/dispatch-api.php`, computing ETA windows.
   * Delivery driver Chandu departs with packed crates, using `/driver/` to view stops and confirm deliveries.
   * Customers track progress and queue position on `track.php`.

---

### Flow 2: Harvest-First Flash Flow (Alternative Model)

*Applied for delicate leafy greens or weather recovery periods.*

1. **T-1 Day (07:00 PM):** Teaser announcement posted on WhatsApp: *"Fresh palakura, gongura, and sora will be harvested at dawn for Hanamakonda. Flash ordering opens at 07:15 AM."*
2. **T-Day Dawn (05:00 AM to 07:00 AM):** Greens harvested, washed, weighed, and bundled. Farmer inputs exact scale counts into `run_inventory`.
3. **T-Day Flash Window (07:15 AM to 09:00 AM):** Storefront catalog opens for ~1 hour 45 minutes with real-time stock decrements.
4. **T-Day Express Dispatch (10:15 AM to 01:30 PM):** Driver departs at 10:15 AM, delivering fresh harvest to kitchens within 3 to 4 hours of cutting.

---

## 5. User Role & Access Control Matrix

| Feature / Capability | Customer | Farmer | Delivery Driver | System Admin |
|---|:---:|:---:|:---:|:---:|
| **Pre-Catalog Region Selection Modal** | Yes | No | No | Optional |
| **Browse Active Region Catalog** | Yes (Own Region) | View Only | No | Full Access |
| **Place Orders Online** | Yes | No | No | Full Access |
| **Assisted Proxy Ordering (Take Phone Orders)** | No | No | No | Yes |
| **Submit Out-of-Area Waitlist Lead** | Yes | No | No | View / Export |
| **Track Order ETA Window & Live Queue Position** | Yes (Own Order) | No | No | Full List |
| **View Sequenced Delivery Manifest & Chained Maps** | No | No | Yes | Full Access |
| **Confirm Delivery (GPS & Photo Upload)** | No | No | Yes | Override Access |
| **Manage 4 Plot Quarters & Crop Lifecycles** | No | Full Access | No | Full Access |
| **Input Estimated Harvest Yields (Kg)** | No | Yes | No | Full Access |
| **Upload Plot Milestone Photos** | No | Yes | No | Full Access |
| **1-Click WhatsApp Menu Generator** | No | No | No | Yes |
| **Manage Logistics Hub Locations (Source/Dest)** | No | No | No | Full Access |
| **Header Language Toggle Switch (`[EN \| తె]`)** | Yes (All Pages) | Yes (All Pages) | Yes (All Pages) | Yes (All Pages) |

---

## 6. Database Schema Migrations Applied

All migrations defined in [`database/migrate_v2.sql`](file:///Applications/XAMPP/xamppfiles/htdocs/hostinger/prakruthisiri/database/migrate_v2.sql) are 100% applied:

```sql
-- 1. Dedicated Farmer Role
ALTER TABLE `staff_users` MODIFY COLUMN `role` ENUM('admin', 'driver', 'farmer') NOT NULL DEFAULT 'driver';

-- 2. Farm Plots Table (Seeded Quarter 1 to Quarter 4)
CREATE TABLE IF NOT EXISTS `farm_plots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plot_number` TINYINT UNSIGNED NOT NULL,
    `quarter_name` VARCHAR(50) NOT NULL,
    `crop_type` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('land_preparation', 'sown', 'vegetative', 'flowering', 'active_harvesting', 'fallow') NOT NULL DEFAULT 'land_preparation',
    `sown_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_plot_number` (`plot_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Crop Milestones Table
CREATE TABLE IF NOT EXISTS `crop_milestones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plot_id` INT UNSIGNED NOT NULL,
    `stage` ENUM('sowing', 'fertilizer_application', 'flowering', 'harvesting', 'other') NOT NULL,
    `photo_path` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `logged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_milestones_plot` (`plot_id`),
    CONSTRAINT `fk_milestones_plot` FOREIGN KEY (`plot_id`) REFERENCES `farm_plots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Expansion Leads Table
CREATE TABLE IF NOT EXISTS `expansion_leads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `phone_number` VARCHAR(15) NOT NULL,
    `full_name` VARCHAR(100) DEFAULT NULL,
    `locality` VARCHAR(150) NOT NULL,
    `landmark` VARCHAR(150) DEFAULT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expansion_locality` (`locality`),
    KEY `idx_expansion_phone` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Hub Locations Table (Seeded with Operating Logistics Hubs)
CREATE TABLE IF NOT EXISTS `hub_locations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `region` ENUM('Hanamkonda', 'Warangal', 'Both') NOT NULL DEFAULT 'Hanamkonda',
    `address` VARCHAR(255) NOT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `is_source` TINYINT(1) NOT NULL DEFAULT 1,
    `is_destination` TINYINT(1) NOT NULL DEFAULT 1,
    `is_default_source` TINYINT(1) NOT NULL DEFAULT 0,
    `is_default_destination` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Dynamic Origin & Destination Hubs on Delivery Schedules
ALTER TABLE `delivery_schedules`
    ADD COLUMN `source_hub_id` INT UNSIGNED DEFAULT NULL AFTER `target_region`,
    ADD COLUMN `destination_hub_id` INT UNSIGNED DEFAULT NULL AFTER `source_hub_id`,
    ADD CONSTRAINT `fk_sched_source_hub` FOREIGN KEY (`source_hub_id`) REFERENCES `hub_locations` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_sched_dest_hub` FOREIGN KEY (`destination_hub_id`) REFERENCES `hub_locations` (`id`) ON DELETE SET NULL;

-- 7. Delivery ETA Window Columns on Orders
ALTER TABLE `orders`
    ADD COLUMN `estimated_delivery_start` TIME DEFAULT NULL AFTER `route_leg_number`,
    ADD COLUMN `estimated_delivery_end` TIME DEFAULT NULL AFTER `estimated_delivery_start`;

-- 8. Multi-Unit Pricing Support
ALTER TABLE `products` ADD COLUMN `pricing_unit` ENUM('half_kg', 'piece', 'bunch') NOT NULL DEFAULT 'half_kg' AFTER `price_per_half_kg`;
ALTER TABLE `order_items` ADD COLUMN `pricing_unit` ENUM('half_kg', 'piece', 'bunch') NOT NULL DEFAULT 'half_kg' AFTER `half_kg_quantity`;
ALTER TABLE `run_inventory` 
    ADD COLUMN `pricing_unit` ENUM('half_kg', 'piece', 'bunch') NOT NULL DEFAULT 'half_kg' AFTER `price_per_half_kg`,
    ADD COLUMN `unit_price` DECIMAL(8,2) AS (`price_per_half_kg`) VIRTUAL AFTER `price_per_half_kg`,
    ADD COLUMN `available_stock` INT UNSIGNED AS (`available_half_kg_stock`) VIRTUAL AFTER `available_half_kg_stock`;

-- 9. Foreign Key Constraint on Orders Schedule ID
ALTER TABLE `orders`
    ADD CONSTRAINT `fk_orders_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `delivery_schedules` (`id`) ON UPDATE CASCADE;
```

---

## 7. Requirements Traceability Matrix

| Requirement Code | Description | Implementation Status | Implementation File(s) | Verification Suite |
|---|---|:---:|---|---|
| **REQ-LOC-01** | Pre-catalog 1-tap region selector modal; deferred phone entry. | **COMPLETE** | `storefront.view.php`, `storefront-app.js` | `verify_run_pipeline.php` |
| **REQ-LOC-02** | Strict region and day catalog locking (Warangal vs. Hanamakonda). | **COMPLETE** | `card-locked.php`, `storefront-app.js`, `i18n-customer.js` | `verify_run_pipeline.php` |
| **REQ-LOC-03** | Exclusion of Kazipet (< 79.55°E) and > 11.5 km radial distance. | **COMPLETE** | `src/GeoFenceService.php`, `src/OrderService.php` | `verify_run_pipeline.php` |
| **REQ-EXP-01** | Expansion lead capture waitlist for unserved colonies. | **COMPLETE** | `public/api/expansion-lead.php`, `card-locked.php` | `verify_new_phases.php` |
| **REQ-ETA-01** | Expected 1-to-2-hour delivery window display on tracking page. | **COMPLETE** | `public/track.php`, `admin/api/dispatch-api.php` | `verify_new_phases.php` |
| **REQ-ETA-02** | Customer queue position display ("Stop X of Y" / "Driver at Stop Z"). | **COMPLETE** | `public/track.php`, `public/assets/js/i18n-customer.js` | `verify_new_phases.php` |
| **REQ-FARM-01** | Dedicated Farmer Role and isolated mobile portal at `/farmer/`. | **COMPLETE** | `farmer/auth_guard.php`, `farmer/login.php`, `farmer/index.php` | `verify_new_phases.php` |
| **REQ-FARM-02** | 4 staggered quarter-plot management (0.75 acres each, 6 stages). | **COMPLETE** | `farmer/index.php`, `farmer/api/plot-api.php`, `src/FarmService.php` | `verify_new_phases.php` |
| **REQ-FARM-03** | Harvest yield estimation (kg) entry syncing directly to `run_inventory`. | **COMPLETE** | `farmer/harvest.php`, `farmer/api/harvest-api.php`, `src/FarmService.php` | `verify_new_phases.php` |
| **REQ-TRC-01** | Crop milestone photo logging tagged by plot stage. | **COMPLETE** | `farmer/milestones.php`, `farmer/api/milestone-api.php` | `verify_new_phases.php` |
| **REQ-TRC-02** | Customer crop journey transparency view & dynamic QR codes. | **COMPLETE** | `public/batch.php`, `public/order-success.php`, `public/track.php` | `verify_new_phases.php` |
| **REQ-ADM-01** | Admin assisted proxy order entry for WhatsApp/phone orders. | **COMPLETE** | `admin/daily-hub.php` (Step 2), `admin/orders.php` | `verify_run_pipeline.php` |
| **REQ-ADM-02** | 1-Click WhatsApp menu text generator with clipboard copy. | **COMPLETE** | `admin/daily-hub.php` (Step 3) | Manual / Browser verified |
| **REQ-ADM-03** | Consolidated 4-step daily administrative workflow hub. | **COMPLETE** | `admin/daily-hub.php`, `admin/dashboard.php` | Manual / Browser verified |
| **REQ-LOC-04** | Dynamic source and return destination hubs for delivery plan. | **COMPLETE** | `admin/locations.php`, `src/RouteDispatchService.php` | `verify_new_phases.php` |
| **REQ-PROD-01** | Multi-unit pricing support (`half_kg`, `piece`, `bunch`). | **COMPLETE** | `src/OrderService.php`, `src/RunInventoryService.php` | `verify_run_pipeline.php` |
| **REQ-I18N-01** | Pure single-language top/header toggle switch (`[EN \| తె]`). | **COMPLETE** | All Storefront, Tracking, Batch, Farmer, Driver & Admin headers | Browser verified |