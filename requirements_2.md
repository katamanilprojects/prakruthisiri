# Prakruthi Siri (ప్రకృతి సిరి) – System Requirements & Product Specification Document

---

## 1. System Overview & Operational Context

### 1.1 Farm Setup & Cultivation Cycle

* **Land Parcel:** 3 acres of organic farmland in Telangana.
* **Crop Partitioning:** The land is partitioned into 4 equal quarters (approx. 0.75 acres each).
* **Staggered Sowing:** Sowing across the 4 plots is staggered with a 3-month gap between plots to sustain year-round continuous harvests and prevent cyclic dry spells.
* **Current Agronomic Stage:** The first plot has begun fruiting and yielding vegetables; subsequent plots are in earlier growth and preparation stages.
* **Yield Dynamics:** Daily harvest yields are variable, resulting in unexpected surplus or deficit on harvest mornings.
* **Packaging Standardization:** Produce is primarily measured and packed in uniform **0.5 kg (500g)** units. The current database schema is hardcoded to half-kg calculations (`price_per_half_kg`, `half_kg_quantity`, `available_half_kg_stock`); per-piece or per-bunch pricing for designated vegetables (e.g., leafy greens) is a **planned future capability (REQ-PROD-01)** and requires a schema migration before it can be supported.



### 1.2 Logistics & Distribution Footprint

* **Permitted Delivery Regions:** Exclusively restricted to the urban boundaries of **Warangal** and **Hanamakonda**.


* **Explicit Route Exclusions:** **Kazipet** is strictly unserved due to delivery boy transit times and distance limitations. Outer suburban zones and bypass corridors are also excluded.
* **Delivery Schedule:** Harvest and delivery occur twice weekly:
* One dedicated day for Warangal (e.g., Tuesday).
* One dedicated day for Hanamakonda (e.g., Thursday).


* **Delivery Personnel:** Exactly one delivery driver on a two-wheeler handles all deliveries for both regions.
* **Cost & Infrastructure Constraints:** The platform operates under a strict low-to-zero budget policy. It relies entirely on standard shared PHP/MySQL hosting with no paid SaaS, third-party mapping subscriptions, or SMS gateway fees.



### 1.3 Target Audience Profile & Market Needs

* **Customer Behavior:** Customers are habituated entirely to WhatsApp, possess limited web browsing experience, and prefer minimal, low-friction interactions.
* **Farmer Persona:** A beginner web user who is overwhelmed by dense dashboards, complex multi-screen navigation, and nested configurations.
* **Long-Term Brand Vision:** Transform the direct-farm delivery into a recognized, premium organic brand through radical transparency: tracing vegetables from plot to kitchen, displaying photographic crop milestone histories, and hosting organic farming tutorial videos.

---

## 2. Current State Audit: What Is Present in Code & Database

### 2.1 Database Schema Verification (`u522254309_prakruthi_siri`)

The active database schema provides the following structures:

* **`customers` & `customer_addresses`:**
* `customers` stores the customer's phone number (unique identifier) and full name.


* `customer_addresses` stores delivery-specific fields only: `label`, `delivery_address`, `landmark`, `region` (`ENUM('Hanamkonda', 'Warangal')`), GPS coordinates (`latitude`, `longitude`), gate photo path, verification flag (`is_location_verified`), and `is_default`. It does **not** contain `phone_number` or `full_name`; those exist exclusively on the parent `customers` record.
* **Note on active address usage:** The `customers` table itself also carries address columns (`delivery_address`, `landmark`, `region`, `latitude`, `longitude`, `gate_photo_path`, `is_location_verified`). In the current codebase (`src/OrderService.php`, `public/api/checkout.php`), orders read and update address data directly from `customers`. The `customer_addresses` table functions as a secondary, dormant multi-address store and is not yet used in the active order flow.




* **`delivery_schedules`:**
* Tracks scheduled delivery runs by date and day.


* Defines target region per run (`target_region` ENUM).


* Enforces time windows using `order_open_datetime`, `cutoff_datetime`, and status flags (`is_ordering_open`, `status`).




* **`run_inventory`:**
* Maps products directly to specific schedule runs.


* Manages schedule-specific available half-kg stock, prices, harvest kilograms, and active flags independent of master product values.




* **`orders` & `order_items`:**
* Records orders tied to customer IDs, schedule IDs, and target delivery dates.


* Tracks statuses (`placed`, `packed`, `out_for_delivery`, `delivered`, `cancelled`).


* Contains driver assignments and sequencing fields (`assigned_driver_id`, `route_sequence_number`, `route_leg_number`, `delivered_at`).


* `order_items` tracks item counts in `half_kg_quantity` increments with applied unit pricing.




* **`products`:**
* Master catalog storing English name, Telugu name, default price per half-kg, stock, unit labels, and product images.




* **`staff_users`:**
* Stores authentication and operational roles (`role` ENUM: `'admin'`, `'driver'`).




* **`system_settings`:**
* Key-value configuration storage.





### 2.2 Features Verified in the Existing Codebase

* **Phone-First Customer Identification (Checkout Drawer Only):** The phone lookup modal (`card-onboarding.php`, `customer-lookup.php`) is triggered inside the checkout drawer. Visitors can currently load the storefront and browse whichever schedule catalog is open without entering a phone number. A pre-catalog region gating step is **not yet implemented** (see REQ-LOC-01).


* **Kazipet & Locality Boundary Validation:** Locality validation and geofence checking (`GeoFenceService.php`) exist to prevent delivery commitments to Kazipet and out-of-bounds coordinates.


* **Admin Proxy Ordering ("On Behalf of Customer"):** The admin panel allows the administrator to manually take phone orders, look up or create customer records, and place orders directly into the database (`admin/orders.php`, `admin/api/orders-api.php`).


* **Driver Dispatch Module:** A mobile-friendly PWA interface exists for the delivery driver to view assigned stops sequentially and log deliveries with camera and GPS validation (`driver/index.php`, `driver/api/verify-delivery.php`).


* **Harvest Aggregation Engine:** Aggregates order item quantities into a consolidated harvest list for field picking (`admin/harvest-sheet.php`).


* **Bilingual Translation Baseline:** Telugu and English dictionaries are implemented for customer, admin, and driver modules (`i18n-customer.js`, `i18n-admin.js`, `i18n-driver.js`).



---

## 3. Identified Functional & UX Deficiencies

* **Cross-Region Catalog Leakage:** The storefront displays whichever delivery schedule is open regardless of customer region. When Hanamakonda's 24-hour window is active, a Warangal customer can view Hanamakonda vegetables, add them to their cart, and only encounter an error at checkout.


* **Dead-End Rejection for Unserved Areas:** If a customer enters a Kazipet or out-of-boundary address, the system terminates the process with an error message. It fails to retain the customer's phone number or record their demand for future route expansions.


* **Absence of Customer Delivery ETA & Queue Position:** While `route_sequence_number` exists in the database, the customer tracking screen (`track.php`) does not display an expected 1-to-2-hour delivery window (e.g., 10:00 AM – 12:00 PM) or their place in the delivery queue (e.g., "Stop 12 of 20").


* **Admin UI Overload for Beginner Farmers:** The admin panel is divided into eight separate pages (`dashboard.php`, `inventory.php`, `orders.php`, `schedules.php`, `routes.php`, `harvest-sheet.php`, `revenue.php`, `customers.php`). This fragmented setup creates confusion for a farmer who needs a simple, linear daily routine.


* **Missing Dedicated Farmer Role:** The system lacks an isolated role for farm field operations. Agricultural plotting, crop stage logging, and yield estimations are combined with administrative and financial operations.


* **Language Inconsistency & Dual-Language Visual Clutter:** UI screens display both Telugu and English stacked together (e.g., "టమాట / Country Tomato"). Users lack a clean single-language toggle switch at the top/header of each page to choose either English or Telugu consistently across every page and role.

* **Inflexible / Hardcoded Route Origins and Return Destinations:** The routing and delivery dispatch implementation hardcodes the Farm Hub as a single identical source and final destination. In current operational practice, delivery pickup (source) and return points (destination) differ and must be selectable from 2 to 3 predefined locations or managed via a dedicated location management page for delivery plans.

* **No Plot or Batch Traceability Records:** The system lacks data entities to track the 4 staggered quarter-plots, crop planting dates, or photo milestone histories.

---

## 4. Detailed Functional Requirements (Planned & Proposed)

### 4.1 Strict Location-and-Day Catalog Access (Zero-Leak Gatekeeper)

* **Pre-Catalog Locality Resolution:** The storefront must never render a vegetable catalog upon initial load. The application must first identify the customer's region via a **1-tap Region Selector modal** (`[ Warangal ]` / `[ Hanamakonda ]`) shown on first arrival. Full phone identification is required only when the customer adds an item to the cart or proceeds to checkout. This minimises bounce rates for first-time visitors arriving via shared WhatsApp links while still enforcing region-locked catalog display.
* **Single-Region Access Enforcement:**
* If a verified Hanamakonda customer accesses the app during an open Hanamakonda schedule window, the system displays only Hanamakonda's harvest catalog.


* If a verified Warangal customer accesses the app during Hanamakonda's open window, the system must show zero vegetables. It must display a locked schedule card:
* *Notice (Telugu & English):* State that orders are open exclusively for Hanamakonda, and display the date and time when Warangal orders will unlock.
* *Action:* Provide a 1-tap "Remind Me on WhatsApp" option.


* If a customer enters during a closed window (after cutoff time), produce must not be shown. The screen must state that orders for the current harvest are closed and indicate the next scheduled opening.



### 4.2 Out-of-Service Area Handling & Demand Capture (Kazipet / Expansion)

* **Preservation of User Input:** When an address is outside the delivery zone (e.g., Kazipet), the system must not discard the customer's phone number, name, or address text.
* **Expansion Waitlist Card:** Instead of displaying a hard error, the UI must present an informative expansion screen:
* Inform the customer that delivery is currently restricted to Warangal and Hanamakonda to maintain farm-to-door transit limits.
* Explain that once a threshold of interest (e.g., 15–20 households) is reached in their colony, a dedicated delivery route will be launched.


* **Lead Storage:** Provide a single-tap button to submit their details to an expansion waitlist table to help track regional demand clusters.

### 4.3 Expected Delivery Time Window & Delivery Queue Tracking

* **Delivery Window Calculation:** Following order cutoff and route sequencing, the system must calculate a 1-to-2-hour delivery window for each order based on dispatch start time and an average drop duration.
* **Customer Tracking Display Requirements (`track.php`)**:


* **Order Confirmed / Packing Stage:** Display the estimated delivery window (e.g., "Thursday between 10:00 AM – 11:30 AM") and total queue context (e.g., "Your order is Stop 12 of 20").
* **Out for Delivery Stage:** Update the status in real time to show current driver progress (e.g., "Driver is currently at Stop 8; your delivery is Stop 12").
* **Delivered Stage:** Display final delivery confirmation timestamp.





### 4.4 Dedicated "Farmer" Role & Plot Management

* **Role Separation:** Introduce a distinct `farmer` role in staff management, separate from `admin` and `driver`.


* **Dedicated Farmer Interface (`/farmer/`):**
* Provide a mobile-first, clean interface restricted to agricultural tasks.
* Hide revenue numbers, customer personal details, and driver routing configurations.


* **3-Acre Plot Division Support:**
* Represent the 4 staggered quarter-plots (Quarter 1, Quarter 2, Quarter 3, Quarter 4).
* Display the current operational status of each plot (e.g., Land Preparation, Sown, Vegetative, Flowering, Active Harvesting).


* **Yield Forecast Entry:**
* Allow the farmer to select their active harvesting plot and enter estimated harvest quantities (in kilograms) 48 hours prior to delivery day.
* Automatically push this forecast into `run_inventory` as available stock for the target schedule run.




* **Milestone Photo Logging:**
* Allow the farmer to capture and upload photos of crop stages directly from their phone camera.
* Tag uploads with plot identifier, crop type, and cultivation stage (Sowing, Organic Fertilizer Application, Flowering, Harvesting).



### 4.5 Organic Brand Building & Farm Transparency

* **Batch Traceability:** Link each harvest run to its originating plot quarter and crop batch.
* **Customer-Facing Transparency View:**
* Allow customers to view their vegetable batch history via a link on their order success or tracking page.
* Display the planting date, organic input logs (e.g., Jeevamrutham, Neem oil), and dawn harvest confirmation.


* **Educational Video Showcase:**
* Provide an embedded section on the storefront for farm videos ("Why our vegetables are harvested fresh on delivery day," "Pest management without synthetic pesticides").
* Videos must be hosted via free external platforms (e.g., YouTube unlisted/public embeds) to prevent server storage and bandwidth overhead.



### 4.6 Simplified Daily Operational Flow for Admin

* **Consolidated Single-Screen Hub:** Replace the multi-page admin navigation with a sequential 4-step daily task list:
1. *Step 1 (Catalog & Pricing Check):* Review the farmer's estimated harvest quantities and adjust unit prices.
2. *Step 2 (Assisted Proxy Ordering):* A prominent form to quickly record phone or WhatsApp orders from offline customers.


3. *Step 3 (1-Click WhatsApp Group Broadcast):* Automatically format the day's vegetable list, pricing, and ordering link into a formatted Telugu/English text message for easy copying to WhatsApp.
4. *Step 4 (Harvest & Packing Manifest):* Generate a printable picking sheet showing total kilograms to harvest, along with individual customer packing tags.




### 4.7 Dynamic Route Source & Destination Location Management (Delivery Plan)

* **Separation of Source & Destination Points:**
  * Update routing logic to discard the assumption that the dispatch origin (source) and final return point (destination) are identical.
  * In current delivery operations, produce dispatch may start from the farm, a packing hub, or a transit collection depot, while the driver may conclude the delivery run at a different return hub, collection point, or residency.

* **Source & Destination Selection (2 to 3 Predefined Hub Locations):**
  * For each delivery schedule run or dispatch plan, allow the administrator or dispatcher to select:
    * **Source Location (Origin):** Dropdown selection from 2 to 3 configured operating points (e.g., *Farm Hub / Ag Center*, *Naimnagar Transit Hub*, *Warangal City Sorting Center*).
    * **Destination Location (End / Return):** Dropdown selection from the configured locations (or option for "None / Finish at Last Customer Stop").
  * Chained Google Maps URL generation (7-stop legs) must calculate Leg 1 departing from the selected Source and the Final Leg terminating at the selected Destination.

* **Dedicated Location Management Page / Interface:**
  * Provide a dedicated administrative settings/management screen (e.g., `admin/locations.php` or dedicated tab under Delivery Plan Management) to:
    * Create, edit, and deactivate logistics hubs/locations.
    * Store Location Name, Locality/Region, exact GPS Latitude/Longitude, Landmark address, and Type flag (`is_source`, `is_destination`, `is_default_source`, `is_default_destination`).
    * Provide quick coordinates validation on an embedded map or coordinate picker.

---

## 5. End-to-End Operational Harvest Flows

### Flow 1: Scheduled Pre-Order Flow (Default Model)

*Recommended for predictable crops (tomatoes, gourds, okra, brinjal) where yield can be reasonably estimated in advance.*

```
T-2 Days (Evening)          T-1 Day (13-Hour Window)      T-Day (Dawn: 05:00 - 08:00 AM)   T-Day (Morning: 08:30 AM - 01:00 PM)
┌──────────────────────┐   ┌────────────────────────┐   ┌───────────────────────────┐   ┌────────────────────────────┐
│ Farmer Harvest Est.  │   │ Ordering Window Opens  │   │ Dawn Picking & Packing    │   │ Delivery Route Execution   │
│ • Farmer inputs kg   │──>│ • WhatsApp blast sent  │──>│ • Harvest sheet tally     │──>│ • Driver loads packed bags │
│ • Safety buffer set  │   │ • Proxy & web orders   │   │ • Pack 0.5kg bundles      │   │ • Customer views queue/ETA │
│ • Schedule unlocked  │   │ • Auto-closes on cutoff│   │ • Route sequenced         │   │ • Cash / UPI collected     │
└──────────────────────┘   └────────────────────────┘   └───────────────────────────┘   └────────────────────────────┘

```

1. **T-2 Days (48h Before Delivery - 06:00 PM):**
* Farmer inspects the active quarter and logs estimated yield (e.g., 30 kg Country Tomato).
* A safety margin (e.g., 15%) is applied, opening 25 kg for pre-orders to prevent deficits.
* Admin confirms the upcoming schedule for the target region (Warangal or Hanamakonda).




2. **T-1 Day (13-Hour Window - 07:00 AM to 08:00 PM):**
* Admin generates and posts the formatted menu to the WhatsApp customer group.
* Customers click the link, verify their phone/location, and access the open catalog. Customers from the opposite region see a locked schedule notice.


* The admin inputs manual WhatsApp/phone orders using the Proxy Order interface.


* The ordering window automatically closes at 08:00 PM.




3. **T-Day Morning (Delivery Day - 05:00 AM to 08:00 AM):**
* The farmer reviews the automated Harvest Sheet showing exact required weights.


* Vegetables are cut fresh, weighed, packed into 0.5 kg bundles, and sorted into customer crates.


* Any excess harvest beyond pre-orders is flagged as surplus for walk-in buyers or quick flash sales.


4. **T-Day Dispatch (08:30 AM to 01:00 PM):**
* Orders are sequenced, generating estimated delivery time windows for each stop.


* The delivery boy follows the stop sequence in `/driver/`.


* Customers monitor their 1-to-2-hour delivery window and queue position on `track.php`.





---

### Flow 2: Harvest-First Flash Flow (Alternative Model)

*Designed for weather disruptions, pest-recovery periods, or delicate greens (spinach, coriander) that cannot be accurately estimated before cutting.*

```
T-1 Day (Evening)           T-Day (Dawn: 05:00 - 07:00 AM)  T-Day (Flash: 07:15 - 09:00 AM)  T-Day (Express: 10:15 AM - 01:30 PM)
┌──────────────────────┐   ┌───────────────────────────┐   ┌────────────────────────────┐   ┌────────────────────────────┐
│ Teaser Broadcast     │   │ Dawn Picking & Scale Weigh│   │ ~1h 45m Flash Window       │   │ Express Dispatch           │
│ • "Greens harvest    │──>│ • Harvest ripe produce    │──>│ • Real stock live in app   │──>│ • Produce delivered within │
│    tomorrow morning" │   │ • Enter exact scale weight│   │ • First-come, first-served │   │   3-4 hours of harvest     │
│ • Lock region day    │   │ • Zero deficit risk       │   │ • Orders close at 09:00 AM │   │ • Maximum freshness        │
└──────────────────────┘   └───────────────────────────┘   └────────────────────────────┘   └────────────────────────────┘

```

1. **T-1 Day (07:00 PM):** An announcement is posted on WhatsApp: *"Fresh spinach and tender ridge gourd will be harvested tomorrow at dawn for Hanamakonda. Flash ordering opens at 07:15 AM."*
2. **T-Day Morning (05:00 AM to 07:00 AM):** Produce is harvested, washed, and weighed on physical scales. The farmer inputs exact picked quantities into `run_inventory` (e.g., exactly 28 half-kg packs).


3. **T-Day Flash Window (07:15 AM to 09:00 AM):** The catalog goes live for a ~1h 45m window. Stock counts are fixed and decrement in real time, preventing overselling or deficits.
4. **T-Day Express Delivery (10:15 AM to 01:30 PM):** Packed crates are handed to the delivery driver, reaching customer kitchens within 3 to 4 hours of being picked.

> **Operational Note:** The original 09:15 AM close / 09:45 AM dispatch allowed only 30 minutes for a single farmer/packer to reconcile payments, tally the picking list, package 20–30 customised bundles, label crates, and hand off to the driver — an unfeasible bottleneck. The window has been expanded to a minimum of **60–75 minutes**: ordering closes at **09:00 AM**, dispatch departs at **10:15 AM**.

---

## 6. Non-Functional, UI/UX & Design Specifications

* **Design Philosophy:** Minimalist, high-contrast, clean layout. Avoid complex dashboards, heavy illustrations, or visually busy color schemes.
* **Mobile-First Constraints:**
* Optimized for small mobile screens with large touch targets (minimum 48px height) for easy tapping.
* Simple quantity adjusters with large **`[-] 0.5kg [+]`** controls.


* **Language Preference & Top/Header Toggle Standard:**
  * **No Combined Bilingual Text:** Do not display Telugu and English combined/stacked together (e.g., avoid "టమాట / Country Tomato").
  * **Single-Language Toggle Switch:** Provide an explicit English / Telugu language toggle (`[EN | తె]`) at the top header of every page.
  * **Role & Page Consistency:** The selected language preference must apply uniformly and consistently across each page for all roles (Customer storefront & tracking, Farmer plot/yield portal, Delivery driver PWA, and Admin operations dashboard).
  * **Session & Persistence:** Store the active language preference in client local storage / session cookie so navigating between pages maintains the chosen language seamlessly.


* **Zero-Cost Technical Guardrails:**
* Must operate on standard shared PHP 8.x hosting using native MariaDB/MySQL.


* No paid external APIs for geocoding, turn-by-turn navigation, SMS gateways, or transactional messaging.
* Use deep links (`[https://wa.me/](https://wa.me/)...`) for WhatsApp messaging and native device sensors (HTML5 Geolocation, Camera API) for location and photos.




* **Performance & Network Efficiency:**
* Keep CSS and JavaScript lightweight with zero heavy frontend framework dependencies.


* Fast asset loading over constrained 3G/4G networks in semi-urban areas.



---

## 7. User Role & Access Control Matrix

| Feature / Capability | Customer | Farmer | Delivery Driver | System Admin |
| --- | --- | --- | --- | --- |
| **Phone-First Locality Verification** | Yes

 | No | No | Optional

 |
| **View Active Region Catalog** | Yes (Own Region Only)

 | View Only

 | No | Full Access

 |
| **Place Orders Online** | Yes

 | No | No | Full Access

 |
| **Add Proxy Orders (On Behalf of WhatsApp Users)** | No | No | No | Yes

 |
| **Submit Out-of-Area Waitlist Lead** | Yes | No | No | View / Export |
| **View Delivery Queue Position & ETA Window** | Yes (Own Order)

 | No | No | Full List

 |
| **View Sequenced Delivery Manifest** | No | No | Yes

 | Full Access

 |
| **Confirm Delivery (GPS & Photo Upload)** | No | No | Yes

 | Override Access

 |
| **Manage 4 Plot Quarters & Crop Lifecycles** | No | Full Access | No | Full Access |
| **Input Estimated Harvest Yields (Kg)** | No | Yes | No | Full Access

 |
| **Upload Plot Milestone Photos** | No | Yes | No | Full Access |
| **1-Click WhatsApp Menu Generator** | No | No | No | Yes |
| **View Revenue, Costs & Financial Summaries** | No | No | No | Full Access |
| **Header Language Toggle (English / Telugu)** | Yes (All Pages) | Yes (All Pages) | Yes (All Pages) | Yes (All Pages) |
| **Manage Delivery Route Source & Destination Locations** | No | No | No | Full Access |
| **Select Route Origin & Final Destination for Dispatch** | No | No | View Assigned | Full Access |

---

## 8. Required Database Schema Migrations

The following schema changes are prerequisites for the features proposed in Sections 4.2–4.5 and 4.7. None of these tables or columns exist in the current dump.

| Proposed Feature | Required Migration |
| --- | --- |
| **Dedicated Farmer Role** (REQ-FARM-01) | `ALTER TABLE staff_users MODIFY COLUMN role ENUM('admin', 'driver', 'farmer');` |
| **Plot Quarters & Crop Lifecycles** (REQ-FARM-02, REQ-TRC-01) | Create `farm_plots` (plot number, quarter name, status) and `crop_milestones` (plot_id, stage, photo_path, notes, logged_at). |
| **Batch Traceability Linking** (Section 4.5) | Add `plot_id` / `batch_id` FK to `run_inventory` to link harvest items to originating plots. |
| **Expansion Lead Capture** (REQ-EXP-01) | Create `expansion_leads` (phone_number, full_name, locality, landmark, latitude, longitude, created_at). |
| **Dynamic Origin / Return Hubs** (REQ-LOC-04) | Create `hub_locations` (name, region, lat, lng, is_source, is_destination, is_default_source, is_default_destination). Then: `ALTER TABLE delivery_schedules ADD COLUMN source_hub_id INT(10) UNSIGNED NOT NULL, ADD COLUMN destination_hub_id INT(10) UNSIGNED NULL, ADD CONSTRAINT fk_sched_source_hub FOREIGN KEY (source_hub_id) REFERENCES hub_locations(id), ADD CONSTRAINT fk_sched_dest_hub FOREIGN KEY (destination_hub_id) REFERENCES hub_locations(id) ON DELETE SET NULL;` — `destination_hub_id` is nullable to support the "None / Finish at Last Customer Stop" option. |
| **Delivery Time Window ETA** (REQ-ETA-01) | Add `estimated_delivery_start` (TIME) and `estimated_delivery_end` (TIME) columns to `orders`. |
| **Multi-Unit Pricing** (REQ-PROD-01) | Add `pricing_unit` ENUM (`'half_kg'`, `'piece'`, `'bunch'`) to `products` and `order_items`. Also update `run_inventory`: rename/alias `price_per_half_kg` → `unit_price` and `available_half_kg_stock` → `available_stock` so schedule-level stock decrements correctly for non-weight units. Decouple harvest weight tally in `admin/harvest-sheet.php` from `half_kg_quantity` where `pricing_unit` is non-weight. |
| **Missing FK: `orders.schedule_id`** | `ALTER TABLE orders ADD CONSTRAINT fk_orders_schedule FOREIGN KEY (schedule_id) REFERENCES delivery_schedules(id) ON UPDATE CASCADE;` — the column is indexed in the dump but no FK constraint is defined. |

---

## 9. Requirements Traceability Matrix

| Requirement Code | Description | Current State | Target State | Target Module |
| --- | --- | --- | --- | --- |
| **REQ-LOC-01** | Mandatory phone and locality entry before catalog display. | **Partially Implemented (Checkout Drawer Only)** — phone prompt fires inside checkout; no pre-catalog gating exists.

 | Add 1-tap Region Selector modal on initial storefront visit; defer phone lookup to cart/checkout. | Storefront Onboarding

 |
| **REQ-LOC-02** | Strict region and day catalog locking (Warangal vs. Hanamakonda). | Catalog leaks cross-region

 | Restrict product display to matching region and active window only. | Catalog & Schedule Engine

 |
| **REQ-LOC-03** | Exclusion of Kazipet and out-of-boundary regions. | Implemented

 | Retain geofence boundary checks. | GeoFence Service

 |
| **REQ-EXP-01** | Expansion lead capture for unserved localities. | Missing (Hard error shown)

 | Display route expansion card and log customer interest. | Waitlist & Lead Module |
| **REQ-ETA-01** | Expected 1-to-2-hour delivery window display. | Missing

 | Compute time windows from sequence numbers and display on tracking screen. | Route Dispatch & Tracking

 |
| **REQ-ETA-02** | Customer queue position display (e.g., Stop 12/20). | Missing

 | Show real-time queue position and driver progress. | Tracking Screen (`track.php`)

 |
| **REQ-FARM-01** | Dedicated Farmer Role and isolated mobile portal. | Missing (`admin` and `driver` only)

 | Create `farmer` role with access limited to plot operations. | Staff & Farmer Module

 |
| **REQ-FARM-02** | Management of 4 staggered quarter-plots (3 acres). | Missing | Track status, crop types, and growth stages per quarter. | Farm Plot Module |
| **REQ-FARM-03** | Harvest estimation input by the farmer. | Missing | Allow direct kg entry that populates schedule inventory. | Inventory & Yield Forecast

 |
| **REQ-TRC-01** | Crop milestone photo logging and batch tracking. | Missing | Enable camera photo uploads tagged by plot stage. | Traceability Module |
| **REQ-TRC-02** | Customer crop transparency and journey view. | Missing | Public batch timeline showing planting, inputs, and harvest. | Brand Transparency View |
| **REQ-ADM-01** | Admin proxy order entry on behalf of customers. | Implemented

 | Streamline onto the main daily operations dashboard. | Admin Order Desk

 |
| **REQ-ADM-02** | 1-Click WhatsApp menu text generator. | Missing | Single-click button copying formatted text to clipboard. | Admin Daily Hub |
| **REQ-ADM-03** | Simplified single-screen daily administrative workflow. | Fragmented across 8 pages | Consolidate into a 4-step daily operational screen. | Admin Daily Hub |
| **REQ-I18N-01** | Unified Language Preference Toggle (Top/Header). | Dual bilingual labels stacked | Provide top/header toggle between English and Telugu consistently across all pages for every role without combining text. | Global UI / Header Component |
| **REQ-LOC-04** | Dynamic Source & Destination Management for Delivery Plan. | Farm Hub hardcoded as both source and destination | Support selecting origin and destination from 2 to 3 locations and provide a dedicated location management screen. | Delivery Route & Dispatch Module |
| **REQ-PROD-01** | Multi-unit pricing support (per-piece / per-bunch). | Missing — schema hardcoded to half-kg (`price_per_half_kg`, `half_kg_quantity`); `unit_label` text change alone breaks harvest weight tallies. | Migrate `products` and `order_items` to support a `pricing_unit` type alongside weight-based units. | Products & Inventory |