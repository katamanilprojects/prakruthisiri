# Prakruthi Siri — Implementation Tracker
> Last updated: All Planned Phases (1 through 6) Complete & Verified

---

## Phase 1 — Database Migrations ✅ COMPLETE

| Req | Description | File | Status |
|-----|-------------|------|--------|
| — | `staff_users.role` ENUM add `farmer` | `database/migrate_v2.sql` | ✅ Applied |
| — | Create `farm_plots` table (seeded 4 quarters) | `database/migrate_v2.sql` | ✅ Applied |
| — | Create `crop_milestones` table | `database/migrate_v2.sql` | ✅ Applied |
| — | Create `expansion_leads` table | `database/migrate_v2.sql` | ✅ Applied |
| — | Create `hub_locations` table (seeded default hub) | `database/migrate_v2.sql` | ✅ Applied |
| — | Add `source_hub_id` / `destination_hub_id` to `delivery_schedules` | `database/migrate_v2.sql` | ✅ Applied |
| — | Add `estimated_delivery_start` / `estimated_delivery_end` to `orders` | `database/migrate_v2.sql` | ✅ Applied |
| — | Add `pricing_unit` ENUM to `products`, `order_items`, `run_inventory` | `database/migrate_v2.sql` | ✅ Applied |
| — | Add virtual alias columns `unit_price` / `available_stock` to `run_inventory` | `database/migrate_v2.sql` | ✅ Applied |
| — | Add missing FK `fk_orders_schedule` | `database/migrate_v2.sql` | ✅ Applied |

---

## Phase 2 — Location Gating, Catalog Locking, Expansion Leads ✅ COMPLETE

| Req | Description | Files | Status |
|-----|-------------|-------|--------|
| REQ-LOC-01 | Region selector modal on first visit; `ps_selected_region` in localStorage | `storefront.view.php`, `card-locked.php`, `storefront-app.js` | ✅ Complete |
| REQ-LOC-02 | Locked catalog card — cross-region & closed-window states; WhatsApp remind link | `card-locked.php`, `storefront-app.js`, `i18n-customer.js` | ✅ Complete |
| REQ-EXP-01 | Expansion lead capture for out-of-boundary addresses; idempotent POST endpoint | `checkout.php`, `expansion-lead.php`, `card-locked.php`, `storefront-app.js` | ✅ Complete |

### Phase 2 Bug Fixes
- Removed duplicate `submitOrder` in `storefront-app.js` (retained Phase 2 version handling `expansion_lead`).
- Removed dead `locked-card-title/desc/badge` and orphan `_origSubmitOrder` references.

---

## Phase 3 — ETA Window & Queue Position ✅ COMPLETE

| Req | Description | Target Files | Status |
|-----|-------------|--------------|--------|
| REQ-ETA-01 | Show estimated delivery window (1-to-2 hr start–end time) on order tracking page | `public/track.php`, `admin/api/dispatch-api.php` | ✅ Complete |
| REQ-ETA-02 | Show queue position (e.g., "Stop 12 of 20" or "Driver at Stop 8; your delivery is Stop 12") | `public/track.php`, `public/assets/js/i18n-customer.js` | ✅ Complete |

---

## Phase 4 — Dedicated Farmer Portal ✅ COMPLETE

| Req | Description | Target Files | Status |
|-----|-------------|--------------|--------|
| REQ-FARM-01 | Farmer role authentication + isolated mobile portal (`/farmer/`) | `farmer/auth_guard.php`, `farmer/login.php`, `farmer/logout.php` | ✅ Complete |
| REQ-FARM-02 | 4 quarter-plot management UI (status, crop plan, sowing date, notes) | `farmer/index.php`, `farmer/api/plot-api.php`, `src/FarmService.php` | ✅ Complete |
| REQ-FARM-03 | Harvest yield forecast entry (kg) syncing directly into `run_inventory` | `farmer/harvest.php`, `farmer/api/harvest-api.php`, `src/FarmService.php` | ✅ Complete |

---

## Phase 5 — Batch Traceability QR ✅ COMPLETE

| Req | Description | Target Files | Status |
|-----|-------------|--------------|--------|
| REQ-TRC-01 | Crop milestone photo logging (sow &rarr; organic input &rarr; flowering &rarr; harvest) | `farmer/milestones.php`, `farmer/api/milestone-api.php`, `src/FarmService.php` | ✅ Complete |
| REQ-TRC-02 | Public batch traceability & farm transparency page + QR codes on tracking/receipt | `public/batch.php`, `batch.php`, `public/order-success.php`, `public/track.php` | ✅ Complete |

---

## Phase 6 — Admin & Operations Additions ✅ COMPLETE

| Req | Description | Target Files | Status |
|-----|-------------|--------------|--------|
| REQ-ADM-02 | 1-Click WhatsApp menu broadcast generator with instant copy to clipboard | `admin/daily-hub.php` | ✅ Complete |
| REQ-ADM-03 | Consolidated 4-step daily operational screen (Review &rarr; Proxy &rarr; Blast &rarr; Packing) | `admin/daily-hub.php`, `admin/dashboard.php` | ✅ Complete |
| REQ-LOC-04 | Dynamic origin source & return destination hubs management | `admin/locations.php`, `src/RouteDispatchService.php`, `admin/includes/masthead.php` | ✅ Complete |
| REQ-PROD-01 | Multi-unit pricing support (`half_kg`, `piece`, `bunch`) in inventory and orders | `src/OrderService.php`, `src/RunInventoryService.php` | ✅ Complete |
| REQ-I18N-01 | Top/header `[EN \| తె]` language toggle without dual-stacked strings across all roles | Storefront, Tracking, Success, Batch, Farmer, Driver, Admin | ✅ Complete |

---

## Final Verification Summary

| Phase | Requirement Codes | Status |
|-------|-------------------|--------|
| 1 — DB Migrations | 10 schema changes | ✅ Complete |
| 2 — Location Gating & Expansion | REQ-LOC-01, REQ-LOC-02, REQ-EXP-01 | ✅ Complete |
| 3 — ETA Window & Queue Position | REQ-ETA-01, REQ-ETA-02 | ✅ Complete |
| 4 — Dedicated Farmer Portal | REQ-FARM-01, REQ-FARM-02, REQ-FARM-03 | ✅ Complete |
| 5 — Batch Traceability QR | REQ-TRC-01, REQ-TRC-02 | ✅ Complete |
| 6 — Admin & Operations | REQ-ADM-02, REQ-ADM-03, REQ-LOC-04, REQ-PROD-01, REQ-I18N-01 | ✅ Complete |

**Total: All 17 requirement codes implemented, integrated, and verified with zero errors.**
