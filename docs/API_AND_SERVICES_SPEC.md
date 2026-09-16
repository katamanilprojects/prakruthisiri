# API and Services Specification (Interface Registry)

**Platform:** Prakruthi Siri REST Interface Contract  
**Payload Encoding:** `application/json; charset=utf-8` (or `multipart/form-data` for file uploads, `text/csv` for streaming reports)  
**Standard Error Format:** `{"success": false, "error": "<Description>"}`  

---

## 1. Storefront Public Interfaces (`public/api/`)

### 1.1 `GET /public/api/checkout.php?action=get_run_catalog`
Fetches the active delivery schedule and run-isolated vegetable catalog for a customer's municipal region.

* **Auth Guard:** Public / Unauthenticated.
* **Query Parameters:**
  * `region` *(string, optional)*: `'Hanamkonda'` (default) or `'Warangal'`.
* **Response Schema (200 OK):**
  ```json
  {
    "success": true,
    "schedule": {
      "id": 12,
      "delivery_date": "2026-09-18",
      "delivery_day": "Tuesday",
      "target_region": "Hanamkonda",
      "order_open_datetime": "2026-09-17 05:00:00",
      "cutoff_datetime": "2026-09-17 19:00:00",
      "harvest_date": "2026-09-17",
      "is_ordering_open": 1,
      "status": "open",
      "status_mode": "OPEN",
      "booked_orders_count": 14,
      "max_orders_limit": 30,
      "is_batch_full": false,
      "cutoff_fmt": "Mon, 17 Sep - 07:00 PM",
      "order_open_fmt": "Mon, 17 Sep - 05:00 AM",
      "delivery_fmt": "Tuesday, 18 Sep 2026"
    },
    "catalog": [
      {
        "id": 1,
        "product_id": 1,
        "run_inventory_id": 105,
        "schedule_id": 12,
        "name": "Country Tomato",
        "telugu_name": "నాటు టమాటా",
        "category": "standard",
        "image_path": "uploads/products/country_tomato.svg",
        "harvest_kg": 40.0,
        "price_per_half_kg": 25.0,
        "price_per_kg_equivalent": 50.0,
        "available_half_kg_stock": 80,
        "available_kg_equivalent": 40.0,
        "is_in_stock": true,
        "is_active": true
      }
    ]
  }
  ```
* **Error Codes:**
  * `200 OK` with `{"success": false, "message": "No active or upcoming delivery runs scheduled for Warangal."}`

---

### 1.2 `POST /public/api/checkout.php`
Submits a customer order with atomic inventory decrement, geofence validation, and cutoff verification.

* **Auth Guard:** Public / Unauthenticated.
* **Request Headers:** `Content-Type: application/json`
* **Request Schema:**
  ```json
  {
    "lang": "te",
    "schedule_id": 12,
    "payment_method": "COD",
    "customer": {
      "phone_number": "9876543210",
      "full_name": "K. Srinivas Rao",
      "delivery_address": "Flat 204, Surya Residency, Naimnagar",
      "landmark": "Near KU Cross Road",
      "region": "Hanamkonda",
      "latitude": 18.0285,
      "longitude": 79.6360
    },
    "items": [
      { "product_id": 1, "half_kg_quantity": 2 },
      { "product_id": 2, "half_kg_quantity": 1 }
    ]
  }
  ```
* **Response Schema (201 Created):**
  ```json
  {
    "success": true,
    "order_id": 1045,
    "order_code": "PS-20260917-X8K2",
    "customer_id": 48,
    "schedule_id": 12,
    "target_delivery_date": "2026-09-18",
    "subtotal": 80.0,
    "delivery_fee": 0.0,
    "total_amount": 80.0,
    "payment_method": "COD",
    "order_status": "placed",
    "items_count": 2,
    "items": [
      {
        "product_id": 1,
        "product_name": "Country Tomato",
        "telugu_name": "నాటు టమాటా",
        "half_kg_quantity": 2,
        "unit_price_applied": 25.0,
        "line_total": 50.0
      },
      {
        "product_id": 2,
        "product_name": "Okra (Lady Finger)",
        "telugu_name": "బెండకాయ",
        "half_kg_quantity": 1,
        "unit_price_applied": 30.0,
        "line_total": 30.0
      }
    ],
    "batch_name": "Tuesday Batch — 18 Sep 2026",
    "store_whatsapp": "919393767927"
  }
  ```
* **Status & Error Codes:**
  * `400 Bad Request`: Validation failure (Empty cart, invalid region, Kazipet exclusion, window not open, batch full).
  * `404 Not Found`: Invalid product ID in cart or delivery schedule not found.
  * `409 Conflict`: Insufficient stock available for one or more vegetable varieties.
  * `500 Internal Server Error`: Database transaction error.

---

### 1.3 `GET /public/api/customer-lookup.php`
Phone-first customer authentication and multi-address retrieval. Rate-limited via atomic `flock()` to 15 requests per 5 minutes per IP.

* **Auth Guard:** Rate-limited by IP (bypassed if `admin_session` is active).
* **Query Parameters:**
  * `phone` *(string, required)*: 10-digit mobile number.
* **Response Schema (200 OK — Returning Customer):**
  ```json
  {
    "success": true,
    "exists": true,
    "found": true,
    "customer": {
      "id": 48,
      "phone": "9876543210",
      "full_name": "K. Srinivas Rao",
      "addresses": [
        {
          "id": 102,
          "label": "Home",
          "delivery_address": "Flat 204, Surya Residency, Naimnagar",
          "landmark": "Near KU Cross Road",
          "region": "Hanamkonda",
          "latitude": 18.0285,
          "longitude": 79.6360,
          "gate_photo_path": "uploads/gates/7f9a8b...jpg",
          "is_location_verified": true,
          "is_default": true
        }
      ],
      "active_address": {
        "id": 102,
        "label": "Home",
        "delivery_address": "Flat 204, Surya Residency, Naimnagar",
        "landmark": "Near KU Cross Road",
        "region": "Hanamkonda",
        "latitude": 18.0285,
        "longitude": 79.6360,
        "gate_photo_path": "uploads/gates/7f9a8b...jpg",
        "is_location_verified": true,
        "is_default": true
      },
      "recent_orders": [
        {
          "id": 1020,
          "order_code": "PS-20260910-A9B1",
          "total_amount": 165.0,
          "order_status": "delivered",
          "target_delivery_date": "2026-09-11",
          "created_at": "2026-09-10 14:20:00"
        }
      ]
    }
  }
  ```
* **Response Schema (200 OK — New Customer):**
  ```json
  {
    "success": true,
    "exists": false,
    "found": false,
    "message": "New customer. Please set up your delivery profile."
  }
  ```
* **Status & Error Codes:**
  * `400 Bad Request`: Phone number is not a valid 10-digit number.
  * `429 Too Many Requests`: Exceeded 15 requests in rolling 5-minute window (`Retry-After: 300`).

---

### 1.4 `POST /public/api/customer-lookup.php`
Registers a new customer profile, adds a saved address, or switches the default active address.

* **Action: `register` (Default):**
  * **Payload:**
    ```json
    {
      "action": "register",
      "phone_number": "9876543210",
      "full_name": "R. Mallaiah",
      "delivery_address": "House 4-12, Subedari",
      "landmark": "Opposite SBI Branch",
      "region": "Hanamkonda",
      "label": "Home",
      "latitude": 17.9856,
      "longitude": 79.5892
    }
    ```
* **Action: `set_default`:**
  * **Payload:**
    ```json
    {
      "action": "set_default",
      "phone_number": "9876543210",
      "address_id": 102
    }
    ```
* **Status & Error Codes:**
  * `200 OK`: Address saved or switched successfully.
  * `400 Bad Request`: Name missing, invalid phone, or Kazipet coordinates submitted.

---

## 2. Operations Hub Interfaces (`admin/api/`)

All endpoints in `admin/api/` require an active administrative session (`$_SESSION['admin_id']`). Unauthenticated requests receive HTTP 401 Unauthorized.

### 2.1 `admin/api/orders-api.php`

#### Action: `get_orders`
* **Method:** `GET` or `POST`
* **Parameters:** `status` (`'all'`, `'placed'`, `'packed'`, `'out_for_delivery'`, `'delivered'`, `'cancelled'`), `region`, `target_date`, `search`, `limit`, `offset`.
* **Response (200 OK):** Array of order summaries along with badge counts across all status tabs.

#### Action: `get_order_details`
* **Method:** `GET` or `POST`
* **Parameters:** `order_id` *(int, required)*.
* **Response (200 OK):** Order header, itemized list of vegetables, doorstep gate photo URL, and driver contact details.

#### Action: `update_status`
* **Method:** `POST`
* **Parameters:** `order_id` *(int, required)*, `new_status` (`'placed'`, `'packed'`, `'out_for_delivery'`, `'delivered'`, `'cancelled'`).
* **Note:** Setting `new_status = 'cancelled'` delegates automatically to `cancel_order`, atomically restoring stock.

#### Action: `verify_payment`
* **Method:** `POST`
* **Parameters:** `order_id` *(int, required)*.
* **Response (200 OK):** Marks `payment_status = 'verified'`.

#### Action: `cancel_order`
* **Method:** `POST`
* **Parameters:** `order_id` *(int, required)*, `reason` *(string, optional)*.
* **Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Order PS-20260918-M8K2 successfully cancelled and vegetables stock restored to inventory.",
    "data": {
      "success": true,
      "order_id": 1045,
      "order_code": "PS-20260918-M8K2",
      "previous_status": "placed",
      "new_status": "cancelled",
      "restored_items": [
        { "product_id": 1, "packets_restored": 2 },
        { "product_id": 2, "packets_restored": 1 }
      ]
    }
  }
  ```

#### Action: `lookup_customer`
* **Method:** `GET` or `POST`
* **Parameters:** `phone` *(string, required)*.
* **Response (200 OK):** Fast profile lookup for WhatsApp order entry.

#### Action: `get_batch_catalog`
* **Method:** `GET` or `POST`
* **Parameters:** `schedule_id` *(int, required)*.
* **Response (200 OK):** Batch schedule details, booked count, batch full flag, and vegetable varieties.

#### Action: `create_manual_order`
* **Method:** `POST`
* **Parameters:** `schedule_id`, `customer`, `items`, `payment_method`, `delivery_notes`, `override_capacity` *(boolean)*.
* **Note:** Bypasses normal customer cutoff restrictions; allows booking beyond 30 orders when `override_capacity = true`.

---

### 2.2 `admin/api/dispatch-api.php`

#### Action: `optimize_route`
* **Method:** `POST`
* **Parameters:** `target_date`, `schedule_id`, `driver_id` *(optional)*, `region` *(optional)*.
* **Processing:** Sequences orders by straight-line distance from farm hub, sets `route_sequence_number` (1..N) and `route_leg_number` (`ceil(seq / 8)`), generates initial Google Maps multi-waypoint URL.
* **Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Successfully sequenced 21 stops.",
    "total_stops": 21,
    "hub": {
      "name": "Prakruthi Siri Central Hub & Organic Farm",
      "latitude": 18.028439,
      "longitude": 79.635941
    },
    "google_maps_url": "https://www.google.com/maps/dir/18.028439,79.635941/18.0285,79.6360/.../18.028439,79.635941",
    "ordered_stops": [...]
  }
  ```

#### Action: `assign_routes`
* **Method:** `POST`
* **Parameters:** `assignments: [{ order_id: 1045, driver_id: 2, sequence: 1 }, ...]`.
* **Response (200 OK):** Updates driver and sequence numbers.

#### Action: `finalize_dispatch`
* **Method:** `POST`
* **Parameters:** `target_date`, `schedule_id`, `driver_id` *(optional)*, `order_ids` *(optional)*.
* **Response (200 OK):** Transitions orders from `placed` $\to$ `packed`, sets `delivery_schedules.status = 'dispatched'`, and unlocks stops for field drivers.

#### Action: `update_hub_location`
* **Method:** `POST`
* **Parameters:** `name`, `address`, `latitude`, `longitude`.
* **Response (200 OK):** Updates central farm depot coordinates in `system_settings`.

---

### 2.3 `admin/api/catalog-api.php`

#### Action: `get_catalog`
* **Method:** `GET`
* **Parameters:** `schedule_id` *(optional)*.
* **Response (200 OK):** Returns run-isolated catalog if `schedule_id > 0`, or master product catalog otherwise.

#### Action: `bulk_update_inventory`
* **Method:** `POST`
* **Parameters:** `schedule_id`, `items: [{ product_id: 1, stock_kg: 25.0, price: 25.0, is_active: 1 }, ...]`.
* **Rule:** Converts `stock_kg` to half-kg packet integer (`round(kg * 2)`).
* **Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Run inventory updated successfully for 8 varieties in schedule #12.",
    "updated_count": 8,
    "zero_stock_alerts": 0
  }
  ```

#### Action: `update_product`
* **Method:** `POST`
* **Parameters:** `product_id`, `stock_kg`, `price_per_half_kg`, `is_active`.
* **Response (200 OK):** Updates master catalog entry.

---

### 2.4 `admin/api/schedule-api.php`

#### Action: `get_schedules`
* **Method:** `GET`
* **Response (200 OK):** List of upcoming and past delivery schedules with booked order counts and total booked revenue.

#### Action: `save_schedule`
* **Method:** `POST`
* **Parameters:** `delivery_date`, `target_region`, `delivery_day`, `order_open_datetime`, `cutoff_datetime`, `harvest_date`, `status`, `is_ordering_open`.
* **Response (200 OK):** Creates or updates schedule batch.

#### Action: `toggle_ordering`
* **Method:** `POST`
* **Parameters:** `schedule_id` or `delivery_date`.
* **Response (200 OK):** Toggles `is_ordering_open` between `1` and `0`.

#### Action: `extend_cutoff`
* **Method:** `POST`
* **Parameters:** `schedule_id`, `hours` *(int, default 1)*.
* **Response (200 OK):** Extends cutoff datetime by $N$ hours from now or existing cutoff and re-opens ordering.

---

### 2.5 `admin/api/reports.php`

#### Report Actions: JSON Data vs. CSV Export
* **Parameters:**
  * `date_dimension`: `'today'`, `'yesterday'`, `'specific_date'`, `'this_week'`, `'this_month'`, `'specific_month'`, `'financial_year'`, `'calendar_year'`.
  * `payment_mode`: `'ALL'`, `'COD'`, `'UPI'`.
  * `order_status`: `'delivered'` (default), `'completed'`, `'cancelled'`.
  * `crop_id`: Specific crop ID to filter (optional).
  * `action`: Default returns JSON; set `action=export_csv` for direct CSV streaming.
  * `export_tab`: When exporting, selects `'tab_periodic'`, `'tab_crops'`, or `'tab_ledger'`.
* **JSON Response Structure (200 OK):**
  * `kpi`: `{ gross_revenue, net_vegetable_sales, total_delivery_fees, total_orders, total_kg_sold, total_packets, cod_total, upi_total, aov }`
  * `tab_periodic`: Grouped by date/month with revenue, packets, and COD/UPI split.
  * `tab_crops`: Crop-wise volume sold in kg and packets, average price, revenue, and % share of sales.
  * `tab_ledger`: Itemized order log with customer details, purchased items, payment status, and subtotal.
* **CSV Export Response:**
  * `Content-Type: text/csv; charset=utf-8`
  * Includes Unicode UTF-8 BOM (`\xEF\xBB\xBF`) prepended to allow Microsoft Excel to render Telugu crop names without encoding issues.

---

## 3. Driver PWA Interfaces (`driver/api/`)

All driver endpoints require an active `driver_session` (`$_SESSION['driver_id']`). Unauthenticated calls return HTTP 401 Unauthorized.

### 3.1 `POST /driver/api/verify-delivery.php`

#### Fast-Path: `quick_deliver` (Returning Verified Customers)
* **Request Schema (JSON):**
  ```json
  {
    "action": "quick_deliver",
    "order_id": 1045
  }
  ```
* **Response Schema (200 OK):**
  ```json
  {
    "success": true,
    "message": "Delivery completed successfully.",
    "order_id": 1045,
    "delivered_at": "2026-09-18 07:14:22"
  }
  ```

#### Standard Path: Multi-Part Photo & GPS Verification (First-Time Customers)
* **Request Headers:** `Content-Type: multipart/form-data`
* **Form Fields:**
  * `order_id` *(int, required)*
  * `customer_id` *(int, required)*
  * `latitude` *(float, required)*: Doorstep GPS latitude acquired by rider's device.
  * `longitude` *(float, required)*: Doorstep GPS longitude acquired by rider's device.
  * `gate_photo` *(binary file, required)*: Uploaded JPEG/PNG image (max 20 MB).
* **Processing:**
  1. Validates MIME type via `finfo_file` (`image/jpeg`, `image/png`).
  2. Re-encodes via PHP GD `imagecreatefromstring()` to strip all EXIF metadata and malicious payloads.
  3. Downsamples to max 1280px bounding box via `imagescale()`.
  4. Saves JPEG at 75% quality to `public/uploads/gates/{sha256}.jpg`.
  5. Updates `customers` and `customer_addresses` with `latitude`, `longitude`, `gate_photo_path`, and sets `is_location_verified = 1`.
  6. Updates `orders` table: `order_status = 'delivered'`, `delivered_at = CURRENT_TIMESTAMP`, `payment_status = (payment_method == 'COD' ? 'verified' : payment_status)`.
* **Response Schema (200 OK):**
  ```json
  {
    "success": true,
    "message": "Doorstep delivery verified successfully.",
    "order_id": 1045,
    "customer_id": 48,
    "delivered_at": "2026-09-18 07:18:04",
    "gate_photo_path": "uploads/gates/9c8e7d...jpg",
    "latitude": 18.02844,
    "longitude": 79.63594
  }
  ```
* **Status & Error Codes:**
  * `400 Bad Request`: Missing order ID, customer ID, or invalid image file.
  * `413 Payload Too Large`: Photo exceeds 20 MB size limit.
  * `415 Unsupported Media Type`: File is not a valid JPEG or PNG image.
  * `500 Internal Server Error`: Server storage or database transaction failure.
