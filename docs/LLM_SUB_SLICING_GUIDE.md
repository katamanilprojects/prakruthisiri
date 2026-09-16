# LLM Sub-Slicing Guide (Context-Window Optimization)

**Target Audience:** Autonomous AI Coding Agents, LLM Pair Programmers, Systems Architects  
**Objective:** Decompose the Prakruthi Siri monolithic repository into minimal, high-density context slices ($< 5,000$ tokens each) to maximize reasoning precision, eliminate context overflow, and avoid hallucination during refactoring.

---

## 1. Task-to-File Routing Matrix

When assigning a coding task to an AI subagent or loading files into a prompt context, use this routing matrix to select only the essential domain files:

| Feature / Domain Task | Primary PHP Services (`src/`) | Endpoints / Controllers | Frontend Engines & Views | Schema / Migration Files |
| :--- | :--- | :--- | :--- | :--- |
| **Geofencing & Distance Boundaries** | `src/GeoFenceService.php`<br>`src/ConfigService.php` | `public/api/checkout.php`<br>`public/api/customer-lookup.php` | `assets/js/app-engine.js` | `database/schema.sql` (Customers table) |
| **Scheduling, Cutoff & Time Windows** | `src/TimeWindow.php`<br>`src/RunInventoryService.php` | `admin/api/schedule-api.php`<br>`public/api/checkout.php` | `admin/schedules.php`<br>`assets/js/app-engine.js` | `database/schema.sql` (`delivery_schedules`) |
| **Run Inventory & Catalog Stocking** | `src/RunInventoryService.php`<br>`src/InventoryService.php` | `admin/api/catalog-api.php`<br>`public/api/checkout.php` | `admin/inventory.php` (Tab 1)<br>`assets/js/app-engine.js` | `database/schema.sql` (`run_inventory`, `products`) |
| **Harvest Aggregation & Crate Packing** | `src/RunInventoryService.php`<br>`src/TimeWindow.php` | `admin/inventory.php`<br>`admin/harvest-sheet.php` | `admin/inventory.php` (Tabs 2 & 3) | `database/schema.sql` (`orders`, `order_items`) |
| **Route Dispatch, TSP & Google Maps** | `src/RouteDispatchService.php`<br>`src/ConfigService.php` | `admin/api/dispatch-api.php`<br>`admin/routes.php` | `admin/routes.php`<br>`driver/views/route.view.php` | `database/schema.sql` (`orders` leg columns) |
| **Driver PWA Manifest & Delivery POD** | `src/DriverManifestService.php`<br>`src/ConfigService.php` | `driver/api/verify-delivery.php`<br>`driver/route.php` | `driver/views/route.view.php`<br>`driver/assets/js/i18n-driver.js` | `database/schema.sql` (`customers.is_location_verified`) |
| **Financial Aggregations & CSV Reports**| `src/ConfigService.php`<br>`src/TimeWindow.php` | `admin/api/reports.php` | `admin/revenue.php`<br>`admin/assets/js/revenue-report.js` | `database/schema.sql` (Orders indexes) |
| **Customer Lookup & Multi-Address** | `src/OrderService.php`<br>`src/GeoFenceService.php` | `public/api/customer-lookup.php` | `assets/js/app-engine.js`<br>`public/track.php` | `database/schema.sql` (`customer_addresses`) |
| **Atomic Database & Migrations** | `src/DatabaseMigration.php` | `config/database.php` | None | `database/migrate.php`<br>`database/seed.php` |

---

## 2. Shell Packaging Scripts (< 5,000 Tokens)

Run these bash/zsh shell one-liners from the repository root to assemble focused context files. Each command concatenates only the critical files, strips redundant blank lines, and outputs a compact bundle ready to be pasted into an LLM prompt.

### 2.1 Slice A: Geofencing & Locality Validation (~2,800 Tokens)
Packages boundary coordinates, Haversine formulas, Kazipet longitude limits, and checkout validation.
```bash
cat src/GeoFenceService.php \
    public/api/checkout.php \
    tests/verify_run_pipeline.php | \
    grep -vE '^\s*//' | cat -s > /tmp/slice_geofencing.php
echo "Slice A created: $(wc -c < /tmp/slice_geofencing.php) bytes"
```

### 2.2 Slice B: Run Inventory & Atomic Concurrency (~3,600 Tokens)
Packages isolated batch stocking, conditional update decrement, and deadlock prevention.
```bash
cat src/RunInventoryService.php \
    src/OrderService.php \
    admin/api/catalog-api.php | \
    grep -vE '^\s*//' | cat -s > /tmp/slice_inventory_concurrency.php
echo "Slice B created: $(wc -c < /tmp/slice_inventory_concurrency.php) bytes"
```

### 2.3 Slice C: Route Dispatch & 7-Stop Partitioning (~3,400 Tokens)
Packages straight-line sorting, Google Maps chained URL generation, and driver assignment.
```bash
cat src/RouteDispatchService.php \
    admin/api/dispatch-api.php \
    admin/routes.php | \
    grep -vE '^\s*//' | cat -s > /tmp/slice_dispatch_routes.php
echo "Slice C created: $(wc -c < /tmp/slice_dispatch_routes.php) bytes"
```

### 2.4 Slice D: Driver Doorstep POD & Image Pipeline (~3,100 Tokens)
Packages native numpad login, progressive delivery verification, GD re-encoding, and EXIF stripping.
```bash
cat driver/route.php \
    driver/api/verify-delivery.php \
    driver/views/route.view.php | \
    grep -vE '^\s*//' | cat -s > /tmp/slice_driver_pod.php
echo "Slice D created: $(wc -c < /tmp/slice_driver_pod.php) bytes"
```

### 2.5 Slice E: Financial Reporting & SQL Aggregations (~3,200 Tokens)
Packages periodic groupings, crop-wise breakdown, itemized ledger, and UTF-8 BOM CSV exports.
```bash
cat admin/api/reports.php \
    admin/assets/js/revenue-report.js | \
    grep -vE '^\s*//' | cat -s > /tmp/slice_reports.php
echo "Slice E created: $(wc -c < /tmp/slice_reports.php) bytes"
```

---

## 3. Test & Verification Command Reference

Whenever changes are made to the service layer or database schema, execute the following commands in sequence to guarantee zero regressions:

### 3.1 Run End-to-End Pipeline Verification
Runs 7 automated test suites testing stock isolation, 11.5 km radial boundary, Kazipet longitude exclusion, cross-region mismatch, dual window opening/cutoff, atomic stock decrement, and order cancellation stock restoration:
```bash
php tests/verify_run_pipeline.php
```
*Expected Output:*
```
============================================================
TEST RESULTS: 7 PASSED, 0 FAILED
============================================================
```

### 3.2 Run Automated Idempotent Schema Migration
Verifies all 9 InnoDB tables, creates missing columns (`route_leg_number`, `schedule_id`), and asserts reporting performance indexes:
```bash
php database/migrate.php
```

### 3.3 Re-Seed Operational Test Data
Seeds system configuration keys, default administrator account (`admin@prakruthisiri.com`), demo drivers (`9876543210`), 8 foundational organic vegetable varieties (0.5 kg base units), and active delivery schedules:
```bash
php database/seed.php
```

---

## 4. Architectural Rules for Future Code Modifications

When prompting or developing new features:
1. **Never use 1 kg as the base unit:** All cart quantities, pricing calculations, and inventory tables must operate in strictly half-kg discrete packet units (`0.5 kg`, $\text{Packets} = \text{Kg} \times 2$).
2. **Never modify master product stock during checkout:** Always decrement `run_inventory` scoped by `schedule_id`.
3. **Never allow mixed Telugu/English strings:** Use separate keys in `CUSTOMER_I18N`, `ADMIN_I18N`, and `DRIVER_I18N`. Never use slash formats (e.g. `Tomato / టమాటా`).
4. **Never allow raw image file storage:** All uploaded gate photos must pass through PHP GD `imagecreatefromstring()` to strip EXIF and re-encode to clean JPEG at 75% quality.
5. **Always preserve UTF-8 BOM on CSV exports:** Stream `\xEF\xBB\xBF` prior to `fputcsv()` calls to preserve Telugu font rendering in Microsoft Excel.
