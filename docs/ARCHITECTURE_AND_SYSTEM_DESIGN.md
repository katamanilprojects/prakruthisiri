# Architecture and System Design (Developer Blueprint)

**System:** Prakruthi Siri Platform Architecture  
**Target Environment:** PHP 8.2+, MySQL 8.0+ (InnoDB Engine), Vanilla ES6+ JavaScript, Progressive Web Apps (Service Workers)  
**Host Environment Compatibility:** Hostinger Cloud / cPanel / Local Apache (XAMPP macOS & Linux)  

---

## 1. High-Level System Topology

The Prakruthi Siri platform is architected as a lightweight, high-performance monolith with clean physical and logical separation across three specialized client tiers, supported by a central domain service layer and an atomic database connection manager.

```
                                    ┌────────────────────────────────────────────────────────┐
                                    │                     CLIENT TIERS                       │
                                    ├────────────────────┬──────────────────┬────────────────┤
                                    │ Public Storefront  │    Driver PWA    │ Admin Hub      │
                                    │ (public/)          │   (driver/)      │ (admin/)       │
                                    └─────────┬──────────┴────────┬─────────┴────────┬───────┘
                                              │                   │                  │
                                     JSON/REST│          JSON/REST│         JSON/REST│
                                              ▼                   ▼                  ▼
┌────────────────────────────────────────────────────────────────────────────────────────────┐
│                                    SERVICE LAYER (src/)                                    │
├───────────────────┬───────────────────┬───────────────────┬────────────────────────────────┤
│ OrderService      │ RunInventoryServ. │ GeoFenceService   │ RouteDispatchService           │
│ DriverManifestSvc │ ConfigService     │ DatabaseMigration │ TimeWindow                     │
└───────────────────┴───────────────────┴───────────────────┴────────────────────────────────┘
                                              │
                                              ▼
┌────────────────────────────────────────────────────────────────────────────────────────────┐
│                             DATABASE LAYER (config/database.php)                           │
│                 MySQL 8.0+ InnoDB Engine • Nested Transaction SAVEPOINT Manager             │
└────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 1.1 Public Storefront (`public/`)
* **Role:** Customer acquisition, product catalog discovery, phone-first registration, multi-address selection, real-time geofence evaluation, 0.5 kg packet cart accumulation, checkout execution, and live self-service order tracking (`track.php`).
* **Design Philosophy:** Zero heavy JavaScript framework dependencies. Uses vanilla JavaScript (`assets/js/app-engine.js`), modular CSS (`assets/css/theme.css`), Service Worker PWA caching (`sw.js`), and pure language dictionary switching (`assets/js/i18n-customer.js`).
* **Key Entry Points:**
  * `public/index.php`: Complete single-page storefront app.
  * `public/track.php`: Zero-cost 4-step vertical progress timeline (`placed` $\to$ `packed` $\to$ `out_for_delivery` $\to$ `delivered`) requiring Order Code and 10-digit customer phone number.
  * `public/order-success.php`: Instant receipt and WhatsApp confirmation router.
  * `public/api/checkout.php`: Transactional checkout endpoint and batch catalog resolver.
  * `public/api/customer-lookup.php`: Phone-based profile auto-lookup and multi-address management.

### 1.2 Driver PWA (`driver/`)
* **Role:** Dedicated mobile application for field delivery riders navigating early-morning distribution runs in Hanamkonda and Warangal.
* **Design Philosophy:** Touch-optimized for single-handed motorcycle navigation. Fast loading over 3G/4G rural networks with offline Service Worker support (`driver/sw.js`).
* **Key Features:**
  * Native system numpad PIN authentication (`index.php`).
  * Locked manifest view: stops remain hidden until the admin dispatches the run (`isDispatched` gate in `route.php`).
  * 3-finger friendly action buttons per stop: Native Call (`tel:`), One-Tap WhatsApp Dispatch, and Direct Google Maps navigation.
  * Two-tier progressive proof of delivery (`api/verify-delivery.php`): 1-tap quick confirmation for returning verified customers vs. mandatory gate photo and GPS auto-latch for new customers.
  * COD cash reconciliation with mandatory acknowledgment checkbox before completion.

### 1.3 Operations Hub (`admin/`)
* **Role:** Command center for farm management, scheduling, inventory stocking, route optimization, field harvest aggregation, crate packing verification, and financial accounting.
* **Key Modules:**
  * `admin/dashboard.php`: Real-time operational KPI telemetry and upcoming batch health.
  * `admin/inventory.php`: Unified 3-tab portal:
    * *Tab 1:* Run-isolated stock and pricing input (Packets = Kg $\times$ 2).
    * *Tab 2:* Field harvest requirements aggregated into bulk pull sheets for harvest laborers.
    * *Tab 3:* Sequential crate packing checklist sorted by Stop #1..N.
  * `admin/routes.php` & `admin/api/dispatch-api.php`: Centroid distance sorting, driver allocation, and 7-stop Google Maps URL partitioning.
  * `admin/orders.php`: Order lifecycle auditor, payment verification, and manual WhatsApp order ingestion with capacity overrides.
  * `admin/schedules.php`: Calendar engine for scheduling delivery batches, opening windows, and cutoff extensions.
  * `admin/revenue.php` & `admin/api/reports.php`: Multi-tab financial reporting engine with UTF-8 BOM CSV exports.

---

## 2. Service Layer Directory (`src/`)

All domain logic, operational boundaries, and validation algorithms are encapsulated within strict PHP classes in the `src/` directory.

### 2.1 `OrderService.php`
* **Namespace:** `PrakruthiSiri`
* **Core Responsibilities:**
  * Atomic customer resolution: Checks if customer exists by 10-digit phone; updates name/address/coordinates or inserts new customer and synchronizes with `customer_addresses`.
  * Deterministic cart sorting: Deduplicates items and sorts array strictly by `product_id ASC` using `ksort()`. This ensures consistent row lock acquisition order across concurrent checkout requests, completely eliminating MySQL InnoDB deadlocks.
  * Atomic stock decrement: Executes conditional SQL `UPDATE run_inventory SET available_half_kg_stock = available_half_kg_stock - :qty WHERE schedule_id = :sid AND product_id = :pid AND available_half_kg_stock >= :qty`. Throws `InsufficientStockException` if stock is exhausted.
  * Batch capacity locking: Checks `SELECT COUNT(*) FROM orders WHERE schedule_id = :sid AND order_status != 'cancelled' FOR UPDATE` to enforce the 30-order limit.
  * MOV & Delivery Fee calculation: Evaluates subtotal against `ConfigService::calculateDeliveryFee()`.
  * Collision-resistant order code generator: Creates unique `PS-YYYYMMDD-XXXX` identifiers.
  * Atomic cancellation (`cancelOrder`): Changes status to `cancelled` and restores reserved packet quantities back to `run_inventory` (or master catalog if legacy).

### 2.2 `RunInventoryService.php`
* **Namespace:** `PrakruthiSiri`
* **Core Responsibilities:**
  * Day-wise isolated stocking: Manages `run_inventory`, decoupling delivery runs (e.g. Tuesday Hanamkonda vs Saturday Warangal) from master product catalog defaults.
  * Auto-provisioning (`ensureScheduleInventory`): Automatically copies active master catalog products into `run_inventory` for a newly scheduled batch if not already present.
  * Catalog resolution (`getRunCatalog`): Delivers schedule-specific prices, harvest kilograms, available half-kg stock, and active flags.
  * Bulk updates (`bulkUpdateRunInventory`): Performs batch upserts (`ON DUPLICATE KEY UPDATE`) for stock and pricing within an active schedule.
  * Atomic decrements and restorations: `decrementStock()` and `restoreStock()` locked to a specific `schedule_id`.

### 2.3 `GeoFenceService.php`
* **Namespace:** `PrakruthiSiri`
* **Constants:**
  * `HUB_LAT = 18.02844440`, `HUB_LNG = 79.63594440`
  * `MAX_RADIUS_KM = 11.5`
  * `WESTERN_LNG_LIMIT = 79.54000000`
  * `BLOCKED_PINCODES = ['506003', '506004']`
  * `ALLOWED_REGIONS = ['Hanamkonda', 'Warangal']`
* **Core Responsibilities:**
  * `getDistanceKm(float $lat, float $lng)`: Computes Haversine radial distance in kilometers from the central farm depot.
  * `isKazipet(?float $lat, ?float $lng)`: Checks if coordinates fall west of `79.540°E` within the local Warangal urban agglomeration (lat 17.92–18.06, lng 79.42–79.54, distance $\le 18\text{ km}$).
  * `isWithinDeliveryRadius(?float $lat, ?float $lng)`: Asserts coordinates pass both the longitude gate ($\ge 79.540^\circ\text{E}$) and distance cap ($\le 11.5\text{ km}$).
  * `resolveRegionFromCoords(float $lat, float $lng)`: Evaluates dividing latitude $18.0050^\circ\text{N}$ to classify unassigned stops as Hanamkonda or Warangal.

### 2.4 `RouteDispatchService.php`
* **Namespace:** `PrakruthiSiri`
* **Core Responsibilities:**
  * Coordinates fallback injection: Attaches regional centroid coordinates (`Hanamkonda`, `Warangal`, `Outskirts`) to orders lacking exact GPS coordinates.
  * Sequential stop sorting: Orders delivery stops by straight-line distance from the farm hub.
  * Google Maps URL generator: Produces chained route URLs partitioned into 7-stop legs (`ceil($seq / 7)`), with origin, intermediate waypoints, and return to the depot on the final leg.
  * Route manifest CSV generator (`exportManifestCsv`): Streams UTF-8 BOM formatted manifests for physical driver clipboards.

### 2.5 `DriverManifestService.php`
* **Namespace:** `PrakruthiSiri`
* **Core Responsibilities:**
  * Driver-specific manifest filtering: Queries orders assigned to `assigned_driver_id` where `order_status IN ('placed', 'packed', 'out_for_delivery', 'delivered')`.
  * Active run dispatch locking: Evaluates schedule status; prevents field riders from viewing stop lists until the batch is marked `dispatched` or `completed` by admin operations.
  * Navigation pin generation: Constructs direct single-stop navigation URLs and pending-stop round-trip links originating from the farm hub.

### 2.6 `ConfigService.php`
* **Namespace:** `PrakruthiSiri`
* **Core Responsibilities:**
  * In-memory cached settings repository: Loads all `system_settings` key-value pairs in a single query via `PDO::FETCH_KEY_PAIR`.
  * Operational parameters: Provides typed accessors for `getMovThreshold()`, `getStandardDeliveryFee()`, `getCutoffTime()`, `getStoreOverrideStatus()`, and `getStoreWhatsAppNumber()`.
  * Depot management: Reads and updates the farm dispatch coordinates (`store_hub_latitude`, `store_hub_longitude`, `store_hub_name`, `store_hub_address`).

### 2.7 `DatabaseMigration.php` & `TimeWindow.php`
* **`DatabaseMigration.php`:**
  * Idempotent self-healing schema manager (`ensureMigrated`): Automatically verifies and provisions all 9 MySQL tables, enforces foreign keys, ensures missing columns (such as `route_leg_number` and `schedule_id`) and performance indexes exist.
  * Purges legacy out-of-bounds Kazipet data across customer and address tables.
  * Seeds operational defaults (admin account, demo drivers, 8 foundational vegetable varieties, system settings).
* **`TimeWindow.php`:**
  * Standardizes all temporal operations to `Asia/Kolkata`.
  * Computes target delivery dates ($T+1$ daytime prior to 19:00 IST vs $T+2$ post-cutoff).
  * Computes active production run dates ($T$ early morning dispatch vs $T+1$ evening harvest).

---

## 3. Security & Authorization Framework

### 3.1 Dual Session Separation
The platform implements complete cryptographic and cookie isolation between platform administrators and field delivery drivers to prevent privilege escalation:

| Parameter | Admin Session | Driver Session |
| :--- | :--- | :--- |
| **Session Cookie Name** | `admin_session` | `driver_session` |
| **Authentication Superglobal** | `$_SESSION['admin_id']` | `$_SESSION['driver_id']` |
| **Cookie Flags** | `HttpOnly=true`, `SameSite=Lax` | `HttpOnly=true`, `SameSite=Lax` |
| **Auth Guard File** | `admin/auth_guard.php` | Native inline check in `driver/route.php` |
| **API Unauthorized Action** | HTTP 401 JSON (`{"success": false, "error": "Unauthorized"}`) | HTTP 401 JSON (`{"success": false, "error": "Unauthorized"}`) |
| **Web Page Action** | 302 Redirect to `admin/login.php?redirect=...` | 302 Redirect to `driver/index.php` |

### 3.2 Driver 6-Digit PIN Authentication
* **Native Input Trigger:** Rather than complex custom virtual keypads that suffer from mobile latency, the driver login form utilizes native HTML5 attributes:
  ```html
  <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" id="pin-input" name="pin" required>
  ```
  This immediately triggers the native iOS / Android numeric keypad.
* **PIN Validation & Password Hashing:**
  * Driver PINs are stored in `staff_users.auth_secret` hashed via PHP `password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12])`.
  * Authentication checks `strlen($pin) === 6 && ctype_digit($pin)` before invoking `password_verify($pin, $driver['auth_secret'])`.
  * Successful authentication invokes `session_regenerate_id(true)` to prevent session fixation attacks.

### 3.3 Multi-Scope Cookie Invalidation on Logout
Standard PHP `session_destroy()` calls frequently leave orphan session cookies in mobile browsers due to path parameter mismatches. Prakruthi Siri enforces a multi-scope cookie invalidation protocol in both `admin/logout.php` and `driver/logout.php`:
```php
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // 1. Clear with exact session parameters
    setcookie(session_name(), '', [
        'expires'  => time() - 86400,
        'path'     => $params['path'] ?: '/',
        'domain'   => $params['domain'] ?: '',
        'secure'   => $params['secure'] ?? false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // 2. Defensive clearance across common path scopes
    setcookie(session_name(), '', time() - 86400, '/');
    setcookie(session_name(), '', time() - 86400, '');
    setcookie('PHPSESSID', '', time() - 86400, '/');
}
session_destroy();
```

### 3.4 Image Sanitization & Doorstep Photo Pipeline
Customer gate photos captured by drivers at doorsteps represent an external input vector vulnerable to polyglot payloads, embedded PHP scripts, and oversized memory exhaustion. The upload pipeline in `driver/api/verify-delivery.php` implements a 5-step sanitization pipeline:

```
[ Uploaded Gate Photo ]
         │
         ▼
[ 1. MIME Validation via finfo_file() ] ──► Reject non JPEG/PNG (HTTP 415)
         │
         ▼
[ 2. PHP GD imagecreatefromstring() ] ────► Strips EXIF, metadata, script tags
         │
         ▼
[ 3. imagescale() Downsampling ] ────────► Max 1280px bounding box
         │
         ▼
[ 4. imagejpeg($img, $path, 75) ] ───────► Re-encodes cleanly at quality 75
         │
         ▼
[ 5. SHA-256 Hashed Storage ] ───────────► public/uploads/gates/{sha256}.jpg
```

1. **Size Limits:** Enforces `@ini_set('upload_max_filesize', '25M')` and maximum file size cap of $20\text{ MB}$ (HTTP 413 Payload Too Large).
2. **True MIME Verification:** Reads file magic bytes via `finfo_file(finfo_open(FILEINFO_MIME_TYPE))`; strictly permits `image/jpeg`, `image/png`, `image/jpg`. Rejects client-supplied `$_FILES['type']`.
3. **GD Re-Encoding:** Decodes raw binary bytes using `imagecreatefromstring($rawBytes)`. This completely strips all EXIF metadata, camera geolocation tags, injected comments, and executable polyglot code.
4. **Proportional Downscaling:** If width or height exceeds $1280\text{ px}$, `imagescale()` resizes the image while maintaining aspect ratio, saving mobile bandwidth and storage.
5. **Re-Compression & Hashed Storage:** Re-encodes the image as a standard JPEG at 75% quality (`imagejpeg($img, $targetFile, 75)`). Saves to `public/uploads/gates/` with a collision-resistant filename:
   ```php
   $fileName = hash('sha256', (string) $customerId . bin2hex(random_bytes(16))) . '.jpg';
   ```
6. **Filesystem Script Isolation:** The user upload directory (`uploads/`) is protected by an aggressive `.htaccess` policy that disables script execution:
   ```apache
   Options -Indexes -ExecCGI
   <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|php7|php8|phar|sh|cgi|pl|py|bash|exe|jsp|asp|aspx)$">
       Require all denied
   </FilesMatch>
   <FilesMatch "(?i)\.(jpg|jpeg|png|gif|webp|svg|pdf)$">
       Require all granted
   </FilesMatch>
   ```
