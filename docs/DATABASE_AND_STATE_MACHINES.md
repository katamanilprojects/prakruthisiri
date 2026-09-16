# Database and State Machines (Data Contract & State Lifecycle)

**Platform:** Prakruthi Siri Data Architecture  
**Database Engine:** MySQL 8.0+ (InnoDB Engine)  
**Character Set:** `utf8mb4` | **Collation:** `utf8mb4_unicode_ci`  

---

## 1. Entity Relationship Model (ERD)

The Prakruthi Siri relational schema consists of 9 core tables structured around decoupled entities: master catalog vs. day-specific run inventory, customer identity vs. multiple delivery addresses, and delivery schedules vs. routed orders.

```
┌──────────────────┐
│ system_settings  │
└──────────────────┘

┌──────────────────┐             ┌──────────────────────┐
│   staff_users    │◄────────────┤        orders        │
│(Admin & Drivers) │assigned_    │                      │
└──────────────────┘driver_id    └──────────┬───────────┘
                                            │
                                            │order_id
                                            ▼
┌──────────────────┐             ┌──────────────────────┐             ┌──────────────────┐
│    customers     │◄────────────┤     order_items      ├────────────►│     products     │
└────────┬─────────┘customer_id  │ (0.5kg packet units) │product_id   │ (Master Catalog) │
         │                       └──────────────────────┘             └─────────┬────────┘
         │                                                                      │
         ▼                                                                      │product_id
┌──────────────────┐             ┌──────────────────────┐                       │
│customer_addresses│             │  delivery_schedules  │                       │
│  (Multi-Address) │             │ (Single Source Run)  │                       │
└──────────────────┘             └──────────┬───────────┘                       │
                                            │                                   │
                                            │schedule_id                        │
                                            ▼                                   ▼
                                 ┌───────────────────────────────────────────────────────┐
                                 │                     run_inventory                     │
                                 │     (Day-Wise Isolated Harvest Stock & Pricing)       │
                                 └───────────────────────────────────────────────────────┘
```

---

## 2. Comprehensive Table Schema Reference

### 2.1 Table: `system_settings`
Global platform operational parameters, delivery fee tiers, and farm dispatch hub coordinates.

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `setting_key` | `VARCHAR(50)` | NO | PRIMARY KEY | Unique setting identifier. |
| `setting_value` | `VARCHAR(255)` | NO | — | Setting string or numeric value. |
| `updated_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last updated timestamp. |

**Standard Settings Keys:**
* `cutoff_time`: Global ordering cutoff string (e.g., `'19:00:00'`).
* `mov_threshold`: Minimum Order Value for free delivery (e.g., `'1.00'` or `'150.00'`).
* `delivery_fee_amount`: Standard delivery fee charged below MOV (e.g., `'0.00'` or `'30.00'`).
* `store_override_status`: Store status override (`'AUTO'`, `'OPEN'`, `'FORCE_CLOSED'`).
* `store_hub_name`: Name of central depot (`'Prakruthi Siri Central Hub & Organic Farm'`).
* `store_hub_address`: Depot address (`'KU Cross Road, Naimnagar, Hanamkonda, Warangal - 506009'`).
* `store_hub_latitude`: Farm hub latitude (`18.02843900`).
* `store_hub_longitude`: Farm hub longitude (`79.63594100`).
* `store_whatsapp_number`: Dispatch WhatsApp mobile (`'919393767927'`).
* `active_delivery_date`: Currently active delivery run date (e.g., `'2026-09-18'`).

---

### 2.2 Table: `staff_users`
Platform administrators and mobile delivery field drivers.

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT (PK) | Unique user ID. |
| `full_name` | `VARCHAR(100)` | NO | — | Full staff name. |
| `phone_number` | `VARCHAR(100)` | NO | — | Unique phone / email identifier. |
| `role` | `ENUM('admin', 'driver')` | NO | — | Role determining access level and session scope. |
| `auth_secret` | `VARCHAR(255)` | NO | — | Bcrypt password hash for admin or 6-digit PIN for driver. |
| `is_active` | `TINYINT(1)` | NO | `1` | Boolean flag (1 = Active, 0 = Inactive). |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | Account creation timestamp. |

**Keys & Indexes:**
* `PRIMARY KEY (id)`
* `UNIQUE KEY uk_staff_phone (phone_number)`
* `KEY idx_staff_role_active (role, is_active)`

---

### 2.3 Table: `customers`
Customer master profiles residing in Hanamkonda or Warangal.

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT (PK) | Unique customer ID. |
| `phone_number` | `VARCHAR(20)` | NO | — | Cleaned 10-digit mobile number. |
| `full_name` | `VARCHAR(100)` | NO | — | Customer full name. |
| `delivery_address` | `TEXT` | NO | — | Primary street address, flat/door number, colony. |
| `landmark` | `VARCHAR(150)` | YES | `NULL` | Prominent neighborhood landmark. |
| `region` | `ENUM('Hanamkonda', 'Warangal')` | NO | — | Strictly validated municipal sector. |
| `latitude` | `DECIMAL(10, 8)` | YES | `NULL` | Doorstep GPS latitude. |
| `longitude` | `DECIMAL(11, 8)` | YES | `NULL` | Doorstep GPS longitude. |
| `gate_photo_path` | `VARCHAR(255)` | YES | `NULL` | Relative path to driver-verified gate photo. |
| `is_location_verified`| `TINYINT(1)` | NO | `0` | `1` if gate photo & doorstep GPS latched. |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | Profile creation timestamp. |
| `updated_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last updated timestamp. |

**Keys & Indexes:**
* `PRIMARY KEY (id)`
* `UNIQUE KEY uk_customers_phone (phone_number)`
* `KEY idx_customers_region (region)`
* `KEY idx_customers_verified (is_location_verified)`

---

### 2.4 Table: `customer_addresses`
Decoupled multi-address storage per customer (e.g., Home, Office, Parents' House).

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT (PK) | Unique address ID. |
| `customer_id` | `INT UNSIGNED` | NO | — | Foreign key referencing `customers.id`. |
| `label` | `VARCHAR(50)` | NO | `'Home'` | Address label (Home, Office, Other). |
| `delivery_address` | `TEXT` | NO | — | Specific address text. |
| `landmark` | `VARCHAR(150)` | YES | `NULL` | Address-specific landmark. |
| `region` | `ENUM('Hanamkonda', 'Warangal')` | NO | `'Hanamkonda'` | Locality sector. |
| `latitude` | `DECIMAL(10, 8)` | YES | `NULL` | Pinned GPS latitude. |
| `longitude` | `DECIMAL(11, 8)` | YES | `NULL` | Pinned GPS longitude. |
| `gate_photo_path` | `VARCHAR(255)` | YES | `NULL` | Photo path for this specific premises. |
| `is_location_verified`| `TINYINT(1)` | NO | `0` | Verification flag for this address. |
| `is_default` | `TINYINT(1)` | NO | `0` | Active default address for storefront checkout. |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | Creation timestamp. |
| `updated_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last modified timestamp. |

**Foreign Key & Indexes:**
* `CONSTRAINT fk_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE CASCADE`
* `KEY idx_address_customer (customer_id)`
* `KEY idx_address_default (customer_id, is_default)`

---

### 2.5 Table: `products`
Master catalog of chemical-free vegetables.

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT (PK) | Unique vegetable ID. |
| `name` | `VARCHAR(100)` | NO | — | English crop name (e.g., `'Country Tomato'`). |
| `telugu_name` | `VARCHAR(100)` | NO | — | Native Telugu crop name (e.g., `'నాటు టమాటా'`). |
| `category` | `ENUM('standard', 'premium')`| NO | `'standard'` | Crop tier. |
| `price_per_half_kg` | `DECIMAL(8, 2)` | NO | — | Price per 0.5 kg packet. |
| `available_half_kg_stock`| `INT UNSIGNED` | NO | `0` | Default template stock in 0.5 kg packets. |
| `image_path` | `VARCHAR(255)` | YES | `NULL` | Relative web path to product SVG/WebP image. |
| `is_active` | `TINYINT(1)` | NO | `1` | Global catalog visibility flag. |
| `updated_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Catalog modification timestamp. |

**Keys & Indexes:**
* `PRIMARY KEY (id)`
* `KEY idx_products_active_category (is_active, category)`
* `KEY idx_products_stock (available_half_kg_stock)`

---

### 2.6 Table: `delivery_schedules`
Single Source of Truth for farm delivery runs and time windows.

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT (PK) | Schedule ID. |
| `delivery_date` | `DATE` | NO | — | Actual morning delivery date ($T$). |
| `delivery_day` | `ENUM(...)` | NO | — | Day of week (`'Monday'` through `'Sunday'`). |
| `target_region` | `ENUM('Hanamkonda', 'Warangal')` | NO | — | Dedicated regional sector. |
| `order_open_datetime`| `DATETIME` | NO | — | Window opening datetime ($T-1$ at 05:00 AM). |
| `cutoff_datetime` | `DATETIME` | NO | — | Hard cutoff datetime ($T-1$ at 19:00 PM). |
| `harvest_date` | `DATE` | NO | — | Evening farm harvest date ($T-1$). |
| `is_ordering_open` | `TINYINT(1)` | NO | `1` | Manual/Automated ordering lock. |
| `status` | `ENUM(...)` | NO | `'scheduled'` | Batch status: `'scheduled'`, `'open'`, `'closed'`, `'dispatched'`, `'completed'`. |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last updated timestamp. |

**Keys & Indexes:**
* `PRIMARY KEY (id)`
* `UNIQUE KEY uk_run_date_region (delivery_date, target_region)`
* `KEY idx_run_lookup (target_region, is_ordering_open, cutoff_datetime)`

---

### 2.7 Table: `run_inventory`
Day-wise isolated harvest stock and pricing per delivery schedule run.

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT (PK) | Unique run inventory ID. |
| `schedule_id` | `INT UNSIGNED` | NO | — | Foreign key referencing `delivery_schedules.id`. |
| `product_id` | `INT UNSIGNED` | NO | — | Foreign key referencing `products.id`. |
| `harvest_kg` | `DECIMAL(8, 2)` | NO | `0.00` | Estimated harvest yield in kg. |
| `available_half_kg_stock`| `INT UNSIGNED` | NO | `0` | Available stock in 0.5 kg packets ($\text{Kg} \times 2$). |
| `price_per_half_kg` | `DECIMAL(8, 2)` | NO | — | Run-specific price per 0.5 kg packet. |
| `is_active` | `TINYINT(1)` | NO | `1` | Availability flag for this specific run. |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | Creation timestamp. |
| `updated_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last inventory update timestamp. |

**Foreign Keys & Indexes:**
* `CONSTRAINT fk_run_inv_schedule FOREIGN KEY (schedule_id) REFERENCES delivery_schedules(id) ON UPDATE CASCADE ON DELETE CASCADE`
* `CONSTRAINT fk_run_inv_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT`
* `UNIQUE KEY uk_schedule_product (schedule_id, product_id)`
* `KEY idx_run_inv_schedule (schedule_id, is_active)`

---

### 2.8 Table: `orders`
Customer orders with target delivery date, schedule linkage, and driver dispatch sequences.

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT (PK) | Unique Order ID. |
| `order_code` | `VARCHAR(20)` | NO | — | Unique code format `PS-YYYYMMDD-XXXX`. |
| `customer_id` | `INT UNSIGNED` | NO | — | FK referencing `customers.id`. |
| `schedule_id` | `INT UNSIGNED` | YES | `NULL` | FK referencing `delivery_schedules.id`. |
| `subtotal` | `DECIMAL(8, 2)` | NO | — | Net vegetable sum. |
| `delivery_fee` | `DECIMAL(8, 2)` | NO | `0.00` | Calculated delivery fee. |
| `total_amount` | `DECIMAL(8, 2)` | NO | — | Subtotal + delivery fee. |
| `target_delivery_date`| `DATE` | NO | — | Date of morning delivery. |
| `order_status` | `ENUM(...)` | NO | `'placed'` | Lifecycle: `'placed'`, `'packed'`, `'out_for_delivery'`, `'delivered'`, `'cancelled'`. |
| `payment_method` | `ENUM('COD', 'UPI')`| NO | — | Payment channel. |
| `payment_status` | `ENUM(...)` | NO | `'pending'` | Lifecycle: `'pending'`, `'verified'`, `'failed'`. |
| `assigned_driver_id` | `INT UNSIGNED` | YES | `NULL` | FK referencing `staff_users.id` (Driver). |
| `route_sequence_number`| `INT` | YES | `NULL` | Stop sequence position (#1..N). |
| `route_leg_number` | `INT UNSIGNED` | YES | `NULL` | 7-to-8 stop navigation circuit leg (`ceil(seq / 8)`). |
| `delivery_notes` | `TEXT` | YES | `NULL` | Customer or admin instructions. |
| `delivered_at` | `DATETIME` | YES | `NULL` | Doorstep timestamp recorded upon driver proof verification. |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | Placement timestamp. |

**Foreign Keys & Indexes:**
* `CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT`
* `CONSTRAINT fk_orders_schedule FOREIGN KEY (schedule_id) REFERENCES delivery_schedules(id) ON UPDATE CASCADE ON DELETE SET NULL`
* `CONSTRAINT fk_orders_driver FOREIGN KEY (assigned_driver_id) REFERENCES staff_users(id) ON UPDATE CASCADE ON DELETE SET NULL`
* `UNIQUE KEY uk_orders_code (order_code)`
* `KEY idx_orders_customer (customer_id)`
* `KEY idx_orders_schedule (schedule_id)`
* `KEY idx_orders_target_date (target_delivery_date)`
* `KEY idx_orders_status (order_status)`
* `KEY idx_orders_driver (assigned_driver_id)`
* `KEY idx_orders_route_seq (target_delivery_date, assigned_driver_id, route_sequence_number)`
* `KEY idx_orders_dispatch_leg (target_delivery_date, assigned_driver_id, route_leg_number)`
* `KEY idx_orders_target_date_status (target_delivery_date, order_status)`
* `KEY idx_orders_created_at_status (created_at, order_status)`

---

### 2.9 Table: `order_items`
Purchased line items recorded strictly in 0.5 kg increments.

| Column | Data Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT (PK) | Unique line item ID. |
| `order_id` | `INT UNSIGNED` | NO | — | FK referencing `orders.id`. |
| `product_id` | `INT UNSIGNED` | NO | — | FK referencing `products.id`. |
| `half_kg_quantity` | `INT UNSIGNED` | NO | — | Discrete 0.5 kg packet count. |
| `unit_price_applied` | `DECIMAL(8, 2)` | NO | — | Price per 0.5 kg applied at checkout. |
| `line_total` | `DECIMAL(8, 2)` | NO | — | `half_kg_quantity * unit_price_applied`. |

**Foreign Keys & Indexes:**
* `CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE CASCADE`
* `CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT`
* `KEY idx_order_items_order (order_id)`
* `KEY idx_order_items_product (product_id)`
* `KEY idx_order_items_order_product (order_id, product_id)`

---

## 3. Run-Isolated Stocking Architecture

### 3.1 The Inventory Bleed Problem
In standard e-commerce architectures, a single `stock` column on the `products` table dictates availability. For a farm with fixed harvest capacities operating across distinct days (e.g., Tuesday in Hanamkonda and Saturday in Warangal), a global stock counter causes severe cross-run contamination:
* Customers ordering heavily on Monday for the Tuesday run would exhaust inventory, leaving the Saturday Warangal run with zero stock even though Saturday's field harvest had not yet been committed.
* Conversely, updating Friday's expected harvest yield would artificially inflate Tuesday's remaining stock.

### 3.2 Solution: `run_inventory` Decoupling
Prakruthi Siri decouples master product metadata (`products`) from operational runs via the junction table `run_inventory`:
1. `products` acts as a template catalog containing English names, Telugu names, category, and default template pricing.
2. `delivery_schedules` defines each distinct delivery batch (`id`, `delivery_date`, `target_region`).
3. `run_inventory` binds the specific schedule to the product with an isolated row:
   ```sql
   UNIQUE KEY uk_schedule_product (schedule_id, product_id)
   ```
4. When orders are placed, the atomic decrement is executed exclusively against the specific `schedule_id`:
   ```sql
   UPDATE run_inventory 
   SET available_half_kg_stock = available_half_kg_stock - :deduct_qty
   WHERE schedule_id = :sid 
     AND product_id = :pid 
     AND available_half_kg_stock >= :min_stock_qty
   ```
This guarantees 100% stock isolation: orders placed for Tuesday Hanamkonda have zero effect on Saturday Warangal inventory.

---

## 4. State Machines & Lifecycle Transitions

### 4.1 Order Status State Machine

```
               [Customer Places Order / Admin Ingests]
                                  │
                                  ▼
                            ┌───────────┐
                            │  placed   │
                            └─────┬─────┘
                                  │
         ┌────────────────────────┴────────────────────────┐
         │ (Admin Finalizes Dispatch / Crate Assembly)     │ (Admin / Customer
         ▼                                                 │  Cancels Order)
    ┌───────────┐                                          │
    │  packed   │                                          │
    └─────┬─────┘                                          │
         │ (Driver Begins Morning Route)                   │
         ▼                                                 │
┌──────────────────┐                                       │
│ out_for_delivery │                                       │
└─────────┬────────┘                                       │
         │ (Driver Verifies Doorstep Proof:                │
         │  Photo/GPS Latch or Quick Deliver)              │
         ▼                                                 ▼
   ┌───────────┐                                     ┌───────────┐
   │ delivered │                                     │ cancelled │
   └───────────┘                                     └───────────┘
   (TERMINAL)                                        (Restores Stock;
                                                      TERMINAL)
```

#### State Definitions & Permitted Transitions:
* **`placed`**: Initial state upon customer checkout. Stock is atomically decremented.
  * Transitions to: `packed`, `cancelled`.
* **`packed`**: Admin operations have printed pull sheets and assembled crates in sequence.
  * Transitions to: `out_for_delivery`, `cancelled`.
* **`out_for_delivery`**: Delivery driver has accepted manifest and departed central depot.
  * Transitions to: `delivered`, `cancelled`.
* **`delivered`**: Doorstep physical handover complete. Terminal state. Cannot be cancelled. Sets `delivered_at = CURRENT_TIMESTAMP`. If COD, marks `payment_status = 'verified'`.
* **`cancelled`**: Order aborted. Terminal state. Atomically executes `RunInventoryService::restoreStock`, returning 0.5 kg packet counts back to the run inventory.

---

### 4.2 Delivery Schedule Batch State Machine

```
              [Schedule Created for Future Date]
                              │
                              ▼
                        ┌───────────┐
                        │ scheduled │
                        └─────┬─────┘
                              │ (Current Time >= order_open_datetime; 05:00 AM)
                              ▼
                        ┌───────────┐
                        │   open    │ ◄───┐ (Emergency Admin Extension /
                        └─────┬─────┘     │  Re-Open via schedule-api)
                              │           │
                              │ (Current Time >= cutoff_datetime; 19:00 PM)
                              ▼           │
                        ┌───────────┐     │
                        │  closed   ├─────┘
                        └─────┬─────┘
                              │ (Admin Executes Route Optimization & Finalizes Dispatch)
                              ▼
                        ┌────────────┐
                        │ dispatched │
                        └─────┬──────┘
                              │ (All Assigned Orders Reach 'delivered' or 'cancelled')
                              ▼
                        ┌───────────┐
                        │ completed │
                        └───────────┘
                         (TERMINAL)
```

* **`scheduled`**: Future batch configured in calendar. Ordering window has not opened yet.
* **`open`**: Active batch. Customers can browse and book items (`is_ordering_open = 1`).
* **`closed`**: Hard cutoff has passed (19:00 IST). Orders are locked.
* **`dispatched`**: Admin has assigned drivers and released routes. Drivers can now view the manifest in the Driver PWA.
* **`completed`**: Morning run finished. Terminal historical record.

---

### 4.3 Payment Status State Machine

```
               [Order Placed via COD or UPI]
                             │
                             ▼
                       ┌───────────┐
                       │  pending  │
                       └─────┬─────┘
                             │
              ┌──────────────┴──────────────┐
              │                             │
              ▼                             ▼
        ┌───────────┐                 ┌───────────┐
        │ verified  │                 │  failed   │
        └───────────┘                 └───────────┘
         (TERMINAL)                    (TERMINAL)
```

* **`pending`**: Default initial state for all orders.
* **`verified`**:
  * For COD: Set automatically when driver verifies delivery or manually by admin confirming cash envelope receipt.
  * For UPI: Set when admin verifies banking transfer via `orders-api.php?action=verify_payment`.
* **`failed`**: Incomplete digital transaction or failed doorstep reconciliation.

---

## 5. Atomic Concurrency & Database Safeguards

### 5.1 Nested MySQL `SAVEPOINT` Transaction Manager
Implemented in `PrakruthiSiri\Config\Database`:
Standard PDO implementations crash when `beginTransaction()` is invoked inside an already active transaction. The singleton `Database` class tracks a `$transactionLevel` counter and injects MySQL savepoints:
* `$transactionLevel === 0`: Issues native `PDO::beginTransaction()`.
* `$transactionLevel > 0`: Issues `SAVEPOINT LEVEL{N}`.
* Commit: Issues `RELEASE SAVEPOINT LEVEL{N}` or outer `PDO::commit()`.
* Rollback: Issues `ROLLBACK TO SAVEPOINT LEVEL{N}` or outer `PDO::rollBack()`.

```php
public function beginTransaction(): bool
{
    if ($this->transactionLevel === 0) {
        $success = $this->connection->beginTransaction();
        if ($success) $this->transactionLevel = 1;
        return $success;
    }
    $this->transactionLevel++;
    $this->connection->exec('SAVEPOINT LEVEL' . $this->transactionLevel);
    return true;
}
```

### 5.2 Deadlock Prevention via Deterministic Cart Sorting
When two customers simultaneously order items $A$ and $B$, concurrent transactions executing updates in opposing order ($A \to B$ vs $B \to A$) cause relational deadlocks. `OrderService::createOrder` normalizes the incoming cart:
```php
// Deduplicate and aggregate quantities
ksort($aggregatedItems); // Sort strictly by product_id ASC
$cartItems = array_values($aggregatedItems);
```
Because all checkout transactions acquire row locks on `run_inventory` in identical ascending `product_id` order, database deadlocks are completely eliminated.

### 5.3 Atomic Conditional Stock Reservation
Stock reservation uses conditional `UPDATE` statements under row-level locks:
```sql
UPDATE run_inventory
SET available_half_kg_stock = available_half_kg_stock - :deduct_qty
WHERE schedule_id = :sid 
  AND product_id = :pid 
  AND available_half_kg_stock >= :min_stock_qty
```
If two customers attempt to purchase the last packet simultaneously, exactly one `UPDATE` affects 1 row. The second transaction affects 0 rows, prompting the service layer to immediately throw `InsufficientStockException` and roll back the transaction.
