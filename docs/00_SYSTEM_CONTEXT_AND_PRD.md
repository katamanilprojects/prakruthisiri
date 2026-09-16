# 00 — System Context and Product Requirements Document (PRD)

**Project Name:** Prakruthi Siri  
**Target Domain:** Direct Farm-to-Table Chemical-Free Organic Vegetables Platform  
**Target Operating Region:** Hanamkonda & Warangal Urban Agglomerations, Telangana, India  
**System Class:** Hyperlocal Agricultural E-Commerce, Multi-Leg Routing Dispatch & Mobile Driver PWA  

---

## 1. Product Vision & Operational Domain

### 1.1 Product Vision
Prakruthi Siri is a localized, direct farm-to-doorstep distribution platform engineered to provide chemical-free, organic vegetables harvested on-demand for urban households in the twin cities of Hanamkonda and Warangal. Unlike conventional grocery delivery platforms that maintain refrigerated warehouse inventories resulting in degraded produce freshness, Prakruthi Siri couples ordering cutoffs directly to farm harvest cycles. Vegetables are harvested fresh from farm beds on the evening preceding the delivery run, packed into order-specific crates, and dispatched for early-morning doorstep delivery.

### 1.2 Central Depot Hub & Logistics Center
All distribution logistics, vegetable crate packing, route optimization origin/destinations, and driver dispatches originate from the centralized farm and sorting hub:
* **Depot Name:** Prakruthi Siri Central Hub & Organic Farm
* **Street Address:** KU Cross Road, Naimnagar, Hanamkonda, Warangal, Telangana — 506009
* **Exact Geographic Coordinates:**
  * Latitude: `18.02843900` (or `18.02844440` in GeoFence spatial checks)
  * Longitude: `79.63594100` (or `79.63594440` in GeoFence spatial checks)
* **Official Dispatch WhatsApp Number:** `+91 9393767927` (`919393767927`)

---

## 2. Core Operating Localities & Geographic Bounds

### 2.1 Service Regions
The system operates exclusively within two administrative municipal sectors:
1. `Hanamkonda`
2. `Warangal`

All customer profile registrations, address verifications, schedule mappings, and route partitions strictly validate that `region IN ('Hanamkonda', 'Warangal')`. Any other locality string is rejected by database check constraints and PHP domain validation exceptions.

### 2.2 Two-Layer Geofence Strategy
To maintain the freshness promise and guarantee cold-chain-free delivery efficiency within early morning hours, the platform enforces a strict, two-tier geofence boundary:

```
                  [ 11.5 km Radial Circle from Hub ]
                                  │
      Excluded Region             │          Permitted Zones
   (Kazipet Urban Cluster)        │     (Hanamkonda & Warangal)
                                  │
  ◄── West of 79.540°E ──────────┼───────── East of 79.540°E ──►
   (Fatima Nagar / Waddepally)    │
                                  │
```

#### Layer 1: Longitudinal Western Boundary (Kazipet Hard Gate)
* **Western Longitude Cutoff:** `79.54000000°E` (`GeoFenceService::WESTERN_LNG_LIMIT`).
* **Kazipet Rejection Logic:** Kazipet begins immediately west of the Fatima Nagar / Waddepally corridor. Any coordinate with a longitude `< 79.540°E` is strictly classified as outside the delivery radius.
* **Urban Cluster Bounding Box:** To prevent distant western cities (e.g., Secunderabad at $78.4983^\circ\text{E}$ or Hyderabad at $78.4867^\circ\text{E}$) from receiving a confusing "Kazipet Exclusion" error message, the algorithm applies a localized bounding box:
  * Latitude: between `17.9200` and `18.0600`
  * Longitude: between `79.4200` and `79.5400`
  * Distance from Hub: $\le 18.0\text{ km}$
  * If within this box: Returns localized Telugu error: `"క్షమించండి! మేము కాజీపేట (Kazipet) ప్రాంతానికి డెలివరీ చేయట్లేదు. ప్రస్తుతం హనుమకొండ మరియు వరంగల్ నగరాలకు మాత్రమే డెలివరీలు ఉన్నాయి."`
  * If outside this box: Returns distance-based boundary error (e.g., `"exceeding the maximum 11.5 km delivery radius"`).
* **Blocked Postal Codes:** Manual postal code entry matching `506003` or `506004` (Kazipet Head Post Office / Railway Junction) is blocked outright.

#### Layer 2: Radial Distance Cap (Haversine Formula)
* **Maximum Allowable Radius:** `11.5 km` (`GeoFenceService::MAX_RADIUS_KM = 11.5`).
* **Distance Calculation:** Computed using the great-circle Haversine formula implemented in `GeoFenceService::getDistanceKm`:

$$\Delta\text{lat} = \text{deg2rad}(\text{lat} - \text{HUB\_LAT})$$

$$\Delta\text{lng} = \text{deg2rad}(\text{lng} - \text{HUB\_LNG})$$

$$a = \sin^2\left(\frac{\Delta\text{lat}}{2}\right) + \cos(\text{deg2rad}(\text{HUB\_LAT})) \cdot \cos(\text{deg2rad}(\text{lat})) \cdot \sin^2\left(\frac{\Delta\text{lng}}{2}\right)$$

$$d = 2 \cdot R \cdot \text{atan2}(\sqrt{a}, \sqrt{1 - a}) \quad \text{where } R = 6371.0\text{ km}$$

Any location with $d > 11.5\text{ km}$ is denied order placement at checkout.

### 2.3 Regional Centroid Coordinate Fallbacks
When customers enter an address manually without granting browser GPS permissions, the system prevents checkout failures by automatically latching to regional centroid coordinates:
* **Hanamkonda Centroid:** `Latitude: 17.9856, Longitude: 79.5892`
* **Warangal Centroid:** `Latitude: 17.9689, Longitude: 79.5941`
* **Outskirts Centroid:** `Latitude: 18.0200, Longitude: 79.6200`

### 2.4 Autonomous Locality Classification
If raw GPS coordinates are supplied without an explicit region selected, `GeoFenceService::resolveRegionFromCoords` evaluates the municipal dividing latitude:
* Latitude $\ge 18.0050^\circ\text{N} \implies$ **Hanamkonda**
* Latitude $< 18.0050^\circ\text{N} \implies$ **Warangal**

---

## 3. Vegetable Unit & Pricing Architecture

### 3.1 Base Measurement Unit: 0.5 kg Packet
To eliminate decimal weight errors, scale calibration discrepancies at customer doorsteps, and friction during packing, **all platform operations operate strictly in half-kilogram (0.5 kg) discrete packet units**.
* **Base Physical Unit:** strictly $0.5\text{ kg}$ (1 packet).
* **Metric Conversion Formulas:**
  * $\text{Packets} = \text{round}(\text{Kilograms} \times 2.0)$
  * $\text{Kilograms} = \text{Packets} \times 0.5$
* **Catalog Constraints:**
  * Prices are configured and displayed per $0.5\text{ kg}$ packet (`price_per_half_kg`).
  * Equivalent price per kilogram is calculated dynamically as $\text{price\_per\_half\_kg} \times 2$.
  * Customer carts accumulate in integer packet counts ($1, 2, 3, \dots$ packets representing $0.5\text{ kg}, 1.0\text{ kg}, 1.5\text{ kg}, \dots$).
  * Database line items in `order_items` record `half_kg_quantity` as an unsigned integer.

### 3.2 Product Classification
Vegetables are segregated into two distinct categories:
1. `standard`: Everyday staples (Country Tomato, Okra, Brinjal, Ridge Gourd, Spinach, Ivy Gourd).
2. `premium`: High-care or protected-cultivation crops (Hydroponic English Cucumber, French Haricot Beans).

---

## 4. Dual Time Window & Scheduling Engine

### 4.1 Automated Time Boundaries
The platform implements a dual-time-window ordering schedule locked to the `Asia/Kolkata` (IST) timezone.

```
Harvest Day (T - 1)                               Delivery Day (T)
05:00 AM                 19:00 (7:00 PM)          06:00 AM - 09:30 AM
   ├────────────────────────────┼──────────────────────────┤
   ▲                            ▲                          ▲
   │                            │                          │
Ordering Window Opens      Hard Cutoff             Doorstep Delivery Run
(Customers browse          (Orders lock;           (Drivers navigate via
 & place orders)            Harvest sheets pull)    PWA manifest)
```

1. **Window Opening:** Exactly `05:00:00 AM` on the harvest day ($T-1$ prior to scheduled delivery run).
2. **Hard Cutoff:** Exactly `19:00:00` (7:00 PM IST) on the harvest day ($T-1$).
   * Post-cutoff, customer ordering for that run is locked (`is_ordering_open = 0`).
   * Emergency admin extensions can be triggered via `schedule-api.php?action=extend_cutoff` (in increments of 1 to 24 hours).

### 4.2 Dedicated Locality Delivery Batches
To maximize delivery density and minimize travel times, farm runs are isolated by locality:
* **Tuesday Batch:** Dedicated to **Hanamkonda**
* **Saturday Batch:** Dedicated to **Warangal**
* **Dynamic Schedules:** Managed dynamically via the `delivery_schedules` table, supporting additional batches (e.g., Thursday runs) when demand expands.

### 4.3 Target Delivery Date Resolution Algorithm
Implemented in `PrakruthiSiri\TimeWindow::getTargetDeliveryDate`:
* Order placed between `00:00:00` and `17:59:59`: Targets Tomorrow morning ($T + 1$).
* Order placed between `18:00:00` and `23:59:59`: Cutoff passed for next-day harvest; targets Day-After-Tomorrow morning ($T + 2$).
* Production run calculation (`getActiveProductionRunDate`): Operations running during evening packing (18:00–23:59) are packing for tomorrow morning ($T + 1$), while early morning dispatch (< 06:00 AM) operates on current day ($T$).

### 4.4 Hard Batch Capacity & Administrative Override
* **Standard Capacity Ceiling:** Maximum **30 booked orders** per delivery schedule run.
* **Storefront Enforcement:** When `booked_orders_count >= 30`, storefront catalog displays `is_batch_full = true` and rejects checkout:
  * Telugu: `"క్షమించండి! ఈ డెలివరీ బ్యాచ్ పూర్తిగా నిండిపోయింది (30/30 ఆర్డర్లు బుక్ అయ్యాయి). దయచేసి తదుపరి బ్యాచ్ని ఎంచుకోండి."`
* **Administrative Emergency Override:** Orders originating from WhatsApp ingestion (`admin/orders.php` $\to$ `create_manual_order`) support a boolean `override_capacity = true` flag, allowing platform operators to append VIP or emergency customer bookings beyond the 30-order threshold.

---

## 5. Financial Architecture

### 5.1 Payment Channels
1. **Cash on Delivery (COD):**
   * Default channel for new and unverified customers.
   * Order is placed with `payment_status = 'pending'`.
   * Requires mandatory doorstep cash reconciliation by the field delivery driver before marking the order delivered.
2. **Unified Payments Interface (UPI):**
   * Customer initiates direct UPI transfer or scans the official store UPI QR code.
   * Can be verified manually by platform administrator via `orders-api.php?action=verify_payment` or auto-settled upon verified driver doorstep handover.

### 5.2 Dynamic Fee & Minimum Order Value (MOV) Calculation
Configured via `system_settings` key-value pairs and evaluated dynamically by `ConfigService::calculateDeliveryFee($subtotal)`:
* **`mov_threshold`:** Minimum Order Value threshold (System default: ₹1.00; production standard: ₹150.00).
* **`delivery_fee_amount`:** Fee charged when subtotal is below `mov_threshold` (System default: ₹0.00; standard tier: ₹30.00).
* **Rule:**
  * If $\text{Subtotal} \ge \text{MOV} \implies \text{Delivery Fee} = \text{₹0.00}$ (Free Delivery).
  * If $\text{Subtotal} < \text{MOV} \implies \text{Delivery Fee} = \text{delivery\_fee\_amount}$.

### 5.3 Order Code Standardization
Order codes follow the deterministic, collision-resistant format:
$$\text{PS-YYYYMMDD-XXXX}$$
* Example: `PS-20260916-K7M2`
* Prefix: `PS-` followed by 8-digit date representation.
* Suffix: 4 alphanumeric characters sampled from unambiguous alphabet `23456789ABCDEFGHJKLMNPQRSTUVWXYZ` (excludes easily confused glyphs `0`, `O`, `1`, `I`).
* Guaranteed unique via database lookup collision loop with microtime fallback.

---

## 6. Bilingual Support & Localization Architecture

### 6.1 Pure Language Separation
To serve both native Telugu-speaking local residents and English-speaking urban professionals without visual clutter or slash strings (e.g. avoiding "Tomato / టమాటా"), Prakruthi Siri enforces a **pure single-language presentation standard**:
* **Language Keys:** Exactly `'te'` (Telugu, default) and `'en'` (English).
* **Persistence:** Preserved across client sessions via browser `localStorage.getItem('ps_lang')` and `localStorage.getItem('ps_driver_lang')`.
* **Zero Mixed Strings:** Dictionaries (`i18n-customer.js`, `i18n-admin.js`, `i18n-driver.js`) define every key twice. No bilingual slashes or hybrid placeholders are permitted in the user interface.

### 6.2 Unicode UTF-8 BOM Compatibility for Spreadsheets
Field harvest pull sheets and financial revenue exports are consumed by farm supervisors and accountants using Microsoft Excel on desktop computers. To prevent Telugu font glyphs (such as `టమాటా`, `బెండకాయ`, `హనుమకొండ`) from rendering as broken characters (`mojibake`), all CSV download endpoints write the 3-byte UTF-8 Byte Order Mark:
```php
echo "\xEF\xBB\xBF"; // UTF-8 BOM Header
```
This forces Microsoft Excel to decode the streaming data as UTF-8 Unicode rather than falling back to legacy Windows-1252/ANSI codepages.
