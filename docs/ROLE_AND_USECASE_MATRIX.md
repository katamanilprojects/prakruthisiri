# Role and Usecase Matrix (Actor Workflows & Operations)

**Platform:** Prakruthi Siri Operational Workflows  
**Actor Classification:** Customer (Guest/Registered), Field Delivery Driver, Operations Administrator  

---

## 1. Role Permission Matrix

| Capability / Resource | Guest / Customer | Field Delivery Driver | Operations Administrator |
| :--- | :---: | :---: | :---: |
| **Storefront Catalog Browsing** | Read-Only | Read-Only | Full Access |
| **Phone-First Customer Registration** | Self-Service | No Access | Full Access (Manual Ingestion) |
| **Multi-Address Profile Management** | Own Addresses Only | No Access | Full Access |
| **Cart Accumulation (0.5 kg steps)** | Full Access | No Access | Full Access |
| **Geofenced Order Checkout** | Self-Service | No Access | Override Mode Allowed |
| **Live Order Timeline Tracking (`track.php`)** | Verified (Code + Phone) | View Order Details | Full Access |
| **Driver PIN Authentication** | No Access | Self-Service (Native Numpad)| No Access |
| **Driver Delivery Manifest** | No Access | Assigned Stops (When Dispatched)| All Drivers / Unassigned |
| **Customer Doorstep Contact (Call/WhatsApp)** | No Access | Assigned Stops | Full Access |
| **Doorstep Photo & GPS Verification** | No Access | Assigned Stops | Audit / View Only |
| **Doorstep COD Cash Handover Check** | No Access | Mandatory Checkbox | Settle / Verify |
| **Run Inventory & Price Stocking (Tab 1)** | No Access | No Access | Full Create / Update |
| **Field Harvest Pull Sheet (Tab 2)** | No Access | No Access | View / Export / Print |
| **Crate Packing Checklist (Tab 3)** | No Access | No Access | View / Check / Print |
| **TSP Route Optimization & Driver Dispatch** | No Access | No Access | Full Execute |
| **7-Stop Google Maps Navigation Legs** | No Access | View Assigned Leg | Full Partition & Launch |
| **Batch Scheduling & Cutoff Extensions** | No Access | No Access | Full Control |
| **WhatsApp Phone-Lookup & Ingestion** | No Access | No Access | Full Create with Override |
| **Financial Aggregations & UTF-8 BOM CSV**| No Access | No Access | Full Export / Audit |

---

## 2. End-to-End Operational Workflows

### 2.1 Customer Workflow: Storefront Discovery to Doorstep Delivery

```
[Customer Lands on Storefront]
              │
              ▼
[Step 1: Mobile Check-In (10-Digit Phone)]
              │
              ├──► [Existing Customer]: Displays saved addresses (Home, Office). User selects active address.
              │
              └──► [New Customer]: Enters Full Name, Street Address, Landmark, Region (Hanamkonda/Warangal).
              │
              ▼
[Step 2: Locality Geofence Validation]
              │
              ├──► Lat/Lng checked: Must be >= 79.540°E (No Kazipet) and <= 11.5 km from Depot.
              │    If no browser GPS: Latch to Regional Centroid (Hanamkonda or Warangal).
              │
              ▼
[Step 3: Schedule Batch & Catalog Resolution]
              │
              ├──► Resolves open delivery batch for customer region (e.g. Tuesday Hanamkonda).
              │    Checks ordering window (Open >= 05:00 AM, Cutoff < 19:00 PM, Booked < 30).
              │
              ▼
[Step 4: Vegetable Selection in 0.5 kg Packet Increments]
              │
              ├──► Customer increments items (+ / -). Cart accumulates packets.
              │    Subtotal updates dynamically in real-time.
              │
              ▼
[Step 5: Checkout Execution]
              │
              ├──► Selects Payment Method: Cash on Delivery (COD) or UPI.
              │    Executes POST /public/api/checkout.php.
              │    MySQL transaction performs atomic stock decrement on run_inventory.
              │
              ▼
[Step 6: Instant Order Confirmation & Receipt]
              │
              ├──► Receives unique order code: PS-YYYYMMDD-XXXX.
              │    Optionally triggers pre-filled WhatsApp confirmation to Farm Depot (+91 9393767927).
              │
              ▼
[Step 7: Self-Service Progress Tracking (track.php)]
              └──► Enters Order Code + Registered Phone. 4-step vertical progress timeline updates live:
                   Placed ──► Packed ──► Out for Delivery ──► Delivered
```

* **Trigger:** Customer visits `public/index.php`.
* **Inputs:** 10-digit mobile number, full name, address, optional landmark, payment method (`COD` or `UPI`), cart array `[{ product_id: 1, quantity: 2 }, ...]`.
* **Validation:**
  * Phone must be exactly 10 digits.
  * Coordinates must be within 11.5 km radial distance and east of 79.540°E.
  * Batch capacity must not exceed 30 orders.
  * Ordering window must be open (between 05:00 AM and 19:00 PM on harvest day).
* **Mutations:**
  * `customers` / `customer_addresses` upserted with verified delivery coordinates.
  * `run_inventory` stock decremented atomically for each line item.
  * `orders` and `order_items` records inserted.
* **Outputs:** HTTP 201 JSON payload with `order_code`, `total_amount`, itemized summary, and redirect to `order-success.php`.

---

### 2.2 Administrator Workflow: 3-Stage Harvest & Packing Operations (`admin/inventory.php`)

```
                          ┌────────────────────────────────────────────────────────┐
                          │     ADMIN 3-STAGE RUN OPERATIONS (inventory.php)       │
                          └──────────────────────────┬─────────────────────────────┘
                                                     │
                 ┌───────────────────────────────────┼───────────────────────────────────┐
                 ▼                                   ▼                                   ▼
       [Tab 1: Stock Entry]               [Tab 2: Field Harvest Plan]         [Tab 3: Crate Packing]
  • Selects Delivery Run Batch        • Aggregates all placed orders      • Orders sequenced #1..N
  • Sets expected harvest in Kg       • Generates bulk crop pull sheet    • Displays customer details,
  • Packets auto-compute: Kg × 2        for farm harvest laborers           address, landmark, and phone
  • Sets price per 0.5 kg packet      • Displays total Kg and Packets     • Checkbox for each packet
  • Toggles live catalog status       • Printable harvest pull sheet        assembled into crate
  • Saves atomically to DB            • Streams UTF-8 BOM CSV export      • Displays COD collection flag
```

#### Stage 1: Batch Setup & Stock Entry (Tab 1)
* **Trigger:** Admin prepares upcoming Tuesday or Saturday delivery run.
* **Interface:** `admin/inventory.php?tab=stock&run_id={id}`
* **Input:** Harvest stock in kilograms per crop (`input-kg`), price per 0.5 kg packet (`input-price`), and active visibility checkbox (`input-active`).
* **Operational Rule:** Packets automatically calculate in the interface as $\text{Packets} = \text{Kg} \times 2$.
* **Mutation:** Invokes `catalog-api.php?action=bulk_update_inventory` with `schedule_id`, executing batch upserts into `run_inventory`.

#### Stage 2: Field Harvest Aggregation (Tab 2)
* **Trigger:** Order cutoff passes (19:00 IST on harvest day). Farm labor supervisor pulls harvesting requirements.
* **Interface:** `admin/inventory.php?tab=harvest&run_id={id}`
* **Data Processing:** Aggregates all non-cancelled orders for the run:
  ```sql
  SELECT p.name, p.telugu_name, p.category, 
         SUM(oi.half_kg_quantity) AS total_packets, 
         (SUM(oi.half_kg_quantity) * 0.5) AS total_kg
  FROM order_items oi
  JOIN orders o ON oi.order_id = o.id
  WHERE o.schedule_id = :sid AND o.order_status != 'cancelled'
  GROUP BY p.id
  ```
* **Output:**
  * Clean, single-sheet printable view (`@media print` stylesheet hides UI controls, formats black-and-white print table).
  * Direct CSV export with UTF-8 BOM (`\xEF\xBB\xBF`) ensuring Telugu crop names render cleanly in Excel.
  * Harvest laborers use this sheet in the fields to cut exact crop quantities without excess wastage.

#### Stage 3: Sequential Crate Packing Checklist (Tab 3)
* **Trigger:** Freshly harvested produce arrives at the central sorting hub for night/early-morning crate assembly.
* **Interface:** `admin/inventory.php?tab=packing&run_id={id}`
* **Data Processing:** Orders are ordered sequentially by Stop #1..N (`route_sequence_number`).
* **Packing Action:**
  * Warehouse packers pack one crate per customer.
  * Each crate is labeled with `Stop #`, `Order Code`, and `Customer Name`.
  * Interactive checkboxes per line item allow packers to cross-check each vegetable packet (e.g. `Country Tomato × 2`, `Okra × 1`) as it is placed in the crate.
  * COD order cards prominently display the exact cash amount to be collected by the driver.

---

### 2.3 Administrator Workflow: Route Dispatch & 7-Stop Partitioning (`admin/routes.php`)

```
[Fetch Orders for Delivery Date]
              │
              ▼
[Sequential Straight-Line Centroid Sorting from Farm Depot Hub]
              │
              ▼
[Partition Stops into 7-Stop Chained Google Maps Circuits]
              │
              ├── Leg 1: Farm Hub ────────► Stops #1 to #7
              ├── Leg 2: Stop #7 ─────────► Stops #8 to #14
              └── Leg 3: Stop #14 ────────► Stops #15 to #21 ──► Return to Farm Hub
              │
              ▼
[Assign Field Drivers to Delivery Run / Legs]
              │
              ▼
[Finalize & Dispatch Batch]
              │
              ├── Orders transition: placed ──► packed
              └── Schedule transitions: open ──► dispatched (Manifest UNLOCKED for Drivers)
```

* **Trigger:** Admin organizes delivery logistics prior to morning driver departure.
* **Interface:** `admin/routes.php` and `admin/api/dispatch-api.php`.
* **Algorithm:**
  1. Orders are sorted by straight-line Euclidean distance from the Central Farm Hub (`18.028439, 79.635941`) using regional centroids for orders without pinned GPS coordinates.
  2. Sequential numbers (`route_sequence_number`) $1, 2, \dots, N$ and leg numbers (`route_leg_number = ceil(seq / 8)`) are persisted to the database.
  3. Google Maps navigation circuits are partitioned into **7-stop chunks**:
     * **Leg 1 Origin:** Farm Hub Depot (`18.028439,79.635941`). Destination/Waypoints: Stops #1 to #7.
     * **Leg 2 Origin:** Stop #7 coordinate. Destination/Waypoints: Stops #8 to #14.
     * **Final Leg:** Automatically appends the return waypoint back to the Farm Hub (`18.028439,79.635941`).
     * **Generated URL Structure:**
       ```
       https://www.google.com/maps/dir/{origin_coords}/{stop1}/{stop2}/.../{stop7}[/{hub_coords}]
       ```
* **Dispatch Finalization:** Invoking `finalize_dispatch` updates orders to `packed`, assigns `assigned_driver_id`, and sets `delivery_schedules.status = 'dispatched'`. This immediately unlocks the stops within the Driver PWA.

---

### 2.4 Administrator Workflow: WhatsApp Order Ingestion (`admin/orders.php`)

```
[Customer Calls or WhatsApps Farm Dispatch]
              │
              ▼
[Admin Opens Manual Booking Modal (orders.php)]
              │
              ▼
[Enters 10-Digit Mobile Number]
              │
              ├──► Invokes orders-api.php?action=lookup_customer
              └──► Auto-populates Full Name, Saved Address, Landmark, and Region
              │
              ▼
[Selects Target Delivery Batch Schedule]
              │
              ├──► Displays current booked order count (e.g. 29/30 or 30/30)
              │
              ▼
[Assembles Vegetable Line Items in 0.5 kg Increments]
              │
              ▼
[Batch Capacity Check]
              │
              ├── If Booked < 30: Books normally.
              │
              └── If Booked >= 30: Displays "Batch Full" Warning.
                   Admin checks: "[✓] Emergency Admin Capacity Override"
              │
              ▼
[Submits Order via create_manual_order]
              └──► Bypasses cutoff and capacity gate; creates order atomically.
```

* **Trigger:** A customer calls the farm office or sends a vegetable list over WhatsApp.
* **Fast Customer Auto-Lookup:** Admin enters the phone number; the modal queries `orders-api.php?action=lookup_customer`. If an existing customer matches, their address, coordinates, and name populate instantly.
* **Capacity Override:** If the batch has already reached its 30-order capacity, the modal blocks submission unless the admin explicitly toggles the `override_capacity = true` checkbox.

---

### 2.5 Field Driver Workflow: Progressive Doorstep Delivery (`driver/route.php`)

```
[Driver Logs In via 6-Digit PIN (Native Numpad)]
                    │
                    ▼
[Inspects Assigned Route Manifest]
                    │
                    ├── If Batch NOT Dispatched: Displays "Delivery Batch Not Yet Dispatched" Lock.
                    │
                    └── If Dispatched: Displays Stop List (#1 to #N) with Customer Details & COD Dues.
                    │
                    ▼
[Driver Navigates to Stop]
                    │
                    ├── Native "Call" Button (tel:)
                    ├── Native "WhatsApp" Alert Button ("Arriving in 5 mins")
                    └── Native "Navigate" Button (Opens Single-Stop Google Maps Pin)
                    │
                    ▼
[Doorstep Verification Decision Tree]
                    │
        ┌───────────┴────────────────────────────────────────┐
        ▼                                                    ▼
[Returning Verified Customer]                         [First-Time / Unverified Customer]
  • 1-Tap "Mark Delivered"                              • Taps "First Delivery: Verify & Deliver"
  • Opens Quick-Deliver Modal                           • Opens Proof Modal
  • If UPI: 1-Tap Confirm.                              • Prompts Native Camera (`capture="environment"`)
  • If COD: Displays Cash Due.                          • Snaps Gate / House entrance photo
    Driver MUST check mandatory                         • Browser acquires doorstep GPS coordinates
    box: "[✓] I confirm I have received                 • Submits multipart upload to verify-delivery.php
    this cash from customer."                           • Server compresses image via GD, updates
  • Delivers immediately.                                 customer as verified, sets order to 'delivered'.
```

#### Progressive Proof-of-Delivery Strategy:
1. **Returning Verified Customers (`is_location_verified = 1`):**
   * Eliminates repetitive friction. Driver does not need to photograph the gate a second time.
   * Prompts single-tap Quick-Deliver modal.
   * If COD, requires the driver to verify cash receipt by toggling the acknowledgment checkbox.
2. **First-Time or Unverified Customers (`is_location_verified = 0`):**
   * Driver must tap "First Delivery: Verify & Deliver".
   * Launches native device camera (`input type="file" accept="image/*" capture="environment"`).
   * Simultaneously latches exact doorstep GPS latitude and longitude via browser Geolocation API.
   * Submits gate photo and coordinates to `driver/api/verify-delivery.php`.
   * Future deliveries to this address are automatically upgraded to the 1-tap fast-path.
