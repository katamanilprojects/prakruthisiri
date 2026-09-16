<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Customer & Verified Address Registry
 * CDCApp Standard: Clean Inline Modal, High-Contrast Architecture
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

use PrakruthiSiri\Config\Database;

$activePage = 'customers';

$searchQuery    = trim((string) ($_GET['search'] ?? ''));
$regionFilter   = trim((string) ($_GET['region'] ?? 'All'));
$verifiedFilter = trim((string) ($_GET['verified'] ?? 'All'));

$customers = [];
$totalCustomersCount = 0;
$verifiedCustomersCount = 0;

try {
    $pdo = Database::getInstance()->getConnection();

    // Handle AJAX get_addresses for the customer modal
    if (isset($_GET['action']) && $_GET['action'] === 'get_addresses') {
        header('Content-Type: application/json; charset=utf-8');
        $phone = trim((string) ($_GET['phone'] ?? ''));
        $customerId = (int) ($_GET['customer_id'] ?? 0);

        $where = [];
        $params = [];
        if ($customerId > 0) {
            $where[] = "ca.`customer_id` = :cid";
            $params[':cid'] = $customerId;
        } elseif ($phone !== '') {
            $where[] = "c.`phone_number` = :phone";
            $params[':phone'] = $phone;
        } else {
            echo json_encode(['success' => false, 'addresses' => []]);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT ca.`id`, ca.`label`, ca.`delivery_address`, ca.`landmark`, ca.`region`, ca.`latitude`, ca.`longitude`, ca.`is_default`, ca.`created_at`
            FROM `customer_addresses` ca
            JOIN `customers` c ON ca.`customer_id` = c.`id`
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ca.`is_default` DESC, ca.`id` ASC
        ");
        $stmt->execute($params);
        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode(['success' => true, 'addresses' => $addresses], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Build WHERE clauses
    $where = ['1=1'];
    $params = [];

    if ($searchQuery !== '') {
        $where[] = '(c.`phone_number` LIKE :sq1 OR c.`full_name` LIKE :sq2 OR c.`delivery_address` LIKE :sq3 OR c.`landmark` LIKE :sq4)';
        $params[':sq1'] = '%' . $searchQuery . '%';
        $params[':sq2'] = '%' . $searchQuery . '%';
        $params[':sq3'] = '%' . $searchQuery . '%';
        $params[':sq4'] = '%' . $searchQuery . '%';
    }

    if ($regionFilter !== '' && $regionFilter !== 'All') {
        $where[] = 'c.`region` = :region';
        $params[':region'] = $regionFilter;
    }

    if ($verifiedFilter === 'verified') {
        $where[] = 'c.`is_location_verified` = 1';
    } elseif ($verifiedFilter === 'unverified') {
        $where[] = 'c.`is_location_verified` = 0';
    }

    $whereSql = implode(' AND ', $where);

    // Summary counts
    $cntStmt = $pdo->query("
        SELECT 
            COUNT(*) AS `total_cust`,
            SUM(CASE WHEN `is_location_verified` = 1 THEN 1 ELSE 0 END) AS `verified_cust`
        FROM `customers`
    ");
    $cntData = $cntStmt->fetch(PDO::FETCH_ASSOC);
    $totalCustomersCount = (int) ($cntData['total_cust'] ?? 0);
    $verifiedCustomersCount = (int) ($cntData['verified_cust'] ?? 0);

    // Query customers with lifetime orders and lifetime spend
    $custSql = "
        SELECT 
            c.`id`,
            c.`phone_number`,
            c.`full_name`,
            c.`delivery_address`,
            c.`landmark`,
            c.`region`,
            c.`latitude`,
            c.`longitude`,
            c.`gate_photo_path`,
            c.`is_location_verified`,
            c.`created_at`,
            COUNT(DISTINCT o.`id`) AS `lifetime_orders`,
            COALESCE(SUM(CASE WHEN o.`order_status` != 'cancelled' THEN o.`total_amount` ELSE 0 END), 0.00) AS `lifetime_spend`
        FROM `customers` c
        LEFT JOIN `orders` o ON c.`id` = o.`customer_id`
        WHERE {$whereSql}
        GROUP BY c.`id`, c.`phone_number`, c.`full_name`, c.`delivery_address`, c.`landmark`, c.`region`, c.`latitude`, c.`longitude`, c.`gate_photo_path`, c.`is_location_verified`, c.`created_at`
        ORDER BY `lifetime_orders` DESC, c.`id` DESC
        LIMIT 200
    ";
    $stmt = $pdo->prepare($custSql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (\Throwable $e) {
    error_log('Customers registry bootstrap error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Registry | Prakruthi Siri</title>
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0f172a">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="PS Admin">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="../assets/icons/icon-192.png">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(err => console.error('SW reg error:', err));
      });
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/admin-theme.css">
</head>
<body class="min-h-full font-sans antialiased text-slate-900 bg-slate-50 flex flex-col pb-16">
  <?php require __DIR__ . '/includes/masthead.php'; ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 space-y-5">
    <!-- Header Summary Ribbon -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-base font-bold text-slate-900">Customer &amp; Household Registry</h2>
          <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
            Hanamkonda &bull; Warangal
          </span>
        </div>
        <p class="text-xs text-slate-500 mt-1">
          Directory of registered platform households with verified doorstep geolocations, multiple saved delivery addresses, and lifetime transaction metrics.
        </p>
      </div>

      <!-- Quick stats -->
      <div class="flex items-center gap-4 text-xs">
        <div class="stat-card py-2 px-3.5">
          <div class="stat-label">Total Customers</div>
          <div class="stat-number text-slate-900 text-lg"><?= number_format($totalCustomersCount) ?></div>
        </div>
        <div class="stat-card py-2 px-3.5">
          <div class="stat-label">Verified Doorsteps</div>
          <div class="stat-number text-emerald-600 text-lg"><?= number_format($verifiedCustomersCount) ?></div>
        </div>
      </div>
    </div>

    <!-- Search & Filter Ribbon -->
    <div class="bg-white border border-slate-200 rounded-2xl p-3.5 sm:p-4 shadow-xs">
      <form method="GET" action="customers.php" class="flex flex-col sm:flex-row sm:items-center flex-wrap gap-2.5 text-xs">
        <!-- Search Input -->
        <div class="flex-1 min-w-[200px] relative">
          <input 
            type="text" 
            name="search" 
            value="<?= htmlspecialchars($searchQuery, ENT_QUOTES) ?>" 
            placeholder="Search name, phone, address, landmark..." 
            class="compact-input w-full pl-8 font-medium text-xs h-10 min-h-[40px]"
          >
          <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
        </div>

        <!-- Region Filter -->
        <div class="w-full sm:w-40">
          <select name="region" class="compact-select w-full font-semibold text-xs h-10 min-h-[40px]">
            <option value="All" <?= $regionFilter === 'All' ? 'selected' : '' ?>>All Localities</option>
            <option value="Hanamkonda" <?= $regionFilter === 'Hanamkonda' ? 'selected' : '' ?>>Hanamkonda</option>
            <option value="Warangal" <?= $regionFilter === 'Warangal' ? 'selected' : '' ?>>Warangal</option>
          </select>
        </div>

        <!-- Verification Filter -->
        <div class="w-full sm:w-44">
          <select name="verified" class="compact-select w-full font-semibold text-xs h-10 min-h-[40px]">
            <option value="All" <?= $verifiedFilter === 'All' ? 'selected' : '' ?>>All Locations</option>
            <option value="verified" <?= $verifiedFilter === 'verified' ? 'selected' : '' ?>>GPS Verified Only</option>
            <option value="unverified" <?= $verifiedFilter === 'unverified' ? 'selected' : '' ?>>Unverified / Approx</option>
          </select>
        </div>

        <!-- Submit & Reset -->
        <div class="flex items-center gap-2 w-full sm:w-auto">
          <button 
            type="submit" 
            class="btn btn-primary text-xs h-10 min-h-[40px] px-4 font-bold flex-1 sm:flex-none"
          >
            Search
          </button>
          <?php if (!empty($searchQuery) || $regionFilter !== 'All' || $verifiedFilter !== 'All'): ?>
            <a 
              href="customers.php" 
              class="btn btn-secondary text-xs h-10 min-h-[40px] px-3 font-semibold text-center flex-1 sm:flex-none"
            >
              Reset
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Customer Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="enterprise-table">
          <thead>
            <tr>
              <th class="w-12">ID</th>
              <th>Customer Name</th>
              <th>Phone Number</th>
              <th>Region</th>
              <th>Primary Delivery Address</th>
              <th class="text-center">Doorstep Pin</th>
              <th class="text-right">Orders</th>
              <th class="text-right">Lifetime Spend</th>
              <th class="text-center">Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($customers)): ?>
              <tr>
                <td colspan="9" class="text-center py-10 text-slate-400 font-medium">
                  No registered customer profiles found matching this filter.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($customers as $c): ?>
                <?php
                  $isVerified = (int) $c['is_location_verified'] === 1;
                  $hasGatePhoto = !empty($c['gate_photo_path']);
                  $gatePhotoUrl = $hasGatePhoto ? '../public/' . ltrim($c['gate_photo_path'], '/') : null;
                  $lat = $c['latitude'] ? (float) $c['latitude'] : null;
                  $lng = $c['longitude'] ? (float) $c['longitude'] : null;
                ?>
                <tr class="hover:bg-slate-50 transition">
                  <td class="font-mono text-slate-400 font-semibold text-xs">#<?= (int) $c['id'] ?></td>
                  <td class="font-bold text-slate-900"><?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?></td>
                  <td class="font-mono text-xs font-semibold text-slate-700">
                    <a href="tel:<?= htmlspecialchars($c['phone_number'], ENT_QUOTES) ?>" class="hover:text-emerald-600">
                      <?= htmlspecialchars($c['phone_number'], ENT_QUOTES) ?>
                    </a>
                  </td>
                  <td>
                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700">
                      <?= htmlspecialchars($c['region'], ENT_QUOTES) ?>
                    </span>
                    <?php if (!empty($c['landmark'])): ?>
                      <div class="text-[11px] text-slate-400 mt-0.5 truncate max-w-[140px]"><?= htmlspecialchars($c['landmark'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-slate-600 text-xs max-w-xs truncate" title="<?= htmlspecialchars($c['delivery_address'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars($c['delivery_address'], ENT_QUOTES) ?>
                  </td>
                  <td class="text-center whitespace-nowrap">
                    <?php if ($isVerified): ?>
                      <span class="badge-status badge-status-delivered text-[10px]">
                        GPS Verified
                      </span>
                    <?php else: ?>
                      <span class="badge-status badge-status-placed text-[10px]">
                        Approx Area
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="text-right font-mono font-bold text-slate-900">
                    <?= (int) $c['lifetime_orders'] ?>
                  </td>
                  <td class="text-right font-mono font-bold text-emerald-700">
                    ₹<?= number_format((float) $c['lifetime_spend'], 2) ?>
                  </td>
                  <td class="text-center whitespace-nowrap">
                    <button 
                      type="button" 
                      class="btn-inspect-customer btn btn-secondary text-xs h-7 min-h-[28px] px-2.5 font-bold"
                      data-customer-id="<?= (int) $c['id'] ?>"
                      data-customer-name="<?= htmlspecialchars($c['full_name'], ENT_QUOTES) ?>"
                      data-customer-phone="<?= htmlspecialchars($c['phone_number'], ENT_QUOTES) ?>"
                      data-delivery-address="<?= htmlspecialchars($c['delivery_address'], ENT_QUOTES) ?>"
                      data-region="<?= htmlspecialchars($c['region'], ENT_QUOTES) ?>"
                      data-landmark="<?= htmlspecialchars($c['landmark'] ?? '', ENT_QUOTES) ?>"
                      data-verified="<?= $isVerified ? '1' : '0' ?>"
                      data-lat="<?= $lat ?>"
                      data-lng="<?= $lng ?>"
                      data-gate-photo="<?= htmlspecialchars($gatePhotoUrl ?? '', ENT_QUOTES) ?>"
                      data-lifetime-orders="<?= (int) $c['lifetime_orders'] ?>"
                      data-lifetime-spend="<?= number_format((float) $c['lifetime_spend'], 2) ?>"
                      data-created-at="<?= date('d M Y', strtotime($c['created_at'])) ?>"
                    >
                      View Profile
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- ======================================================================== -->
  <!-- CLEAN INLINE CUSTOMER INSPECTION MODAL (Replaces bulky slide-over drawer) -->
  <!-- ======================================================================== -->
  <div id="customer-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col animate-in fade-in zoom-in-95 duration-150">
      
      <!-- Modal Header -->
      <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-bold text-base text-slate-900" id="modal-cust-name">Customer Profile</h3>
            <span id="modal-cust-verified-badge" class="badge-status">--</span>
          </div>
          <p class="text-xs text-slate-500 font-mono mt-0.5" id="modal-cust-phone">--</p>
        </div>
        <button type="button" id="btn-close-modal" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:text-slate-800 hover:bg-slate-200 flex items-center justify-center text-lg font-bold transition">
          &times;
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-4 sm:p-5 space-y-4 overflow-y-auto text-xs">
        <!-- 3 Quick KPI Stat Badges -->
        <div class="grid grid-cols-3 gap-2 text-center">
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Lifetime Orders</div>
            <div class="text-base font-extrabold text-slate-900 font-mono mt-0.5" id="modal-cust-orders">0</div>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Lifetime Spend</div>
            <div class="text-base font-extrabold text-emerald-700 font-mono mt-0.5" id="modal-cust-spend">₹0.00</div>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Registered</div>
            <div class="text-xs font-bold text-slate-700 mt-1" id="modal-cust-reg">--</div>
          </div>
        </div>

        <!-- Primary Address & GPS Section -->
        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-2">
          <div class="font-bold text-slate-800 text-xs">Primary Delivery Address</div>
          <div class="text-slate-700 leading-relaxed font-medium" id="modal-cust-address">--</div>
          <div class="text-slate-500 text-[11px]" id="modal-cust-region-landmark">--</div>

          <div class="pt-2 flex items-center justify-between border-t border-slate-200">
            <span class="text-[11px] font-mono text-slate-500" id="modal-cust-coords">GPS: Estimated</span>
            <a 
              id="modal-cust-maps-btn" 
              href="#" 
              target="_blank" 
              rel="noopener"
              class="btn btn-secondary text-xs h-7 min-h-[28px] px-2.5 font-bold inline-flex items-center gap-1"
            >
              <span>Google Maps &rarr;</span>
            </a>
          </div>
        </div>

        <!-- Verified Doorstep Gate Photo Section -->
        <div id="modal-cust-photo-section" class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-2">
          <div class="font-bold text-slate-800 text-xs flex items-center justify-between">
            <span>Doorstep Verification Photo</span>
            <span class="text-[10px] text-slate-400">Captured by Driver</span>
          </div>
          <div id="modal-cust-photo-wrap" class="relative group cursor-pointer overflow-hidden rounded-xl border border-slate-200 bg-slate-100 flex items-center justify-center max-h-48">
            <img id="modal-cust-gate-img" src="" alt="Verified Doorstep Photo" class="w-full h-auto object-cover max-h-48">
          </div>
          <div id="modal-cust-no-photo" class="text-slate-400 italic py-2 text-center hidden">
            No doorstep gate photo registered for this customer yet.
          </div>
        </div>

        <!-- Saved Addresses Section -->
        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-2">
          <div class="font-bold text-slate-800 text-xs flex items-center justify-between">
            <span>Saved Household Addresses</span>
            <span class="text-[10px] text-slate-400">Multi-Location</span>
          </div>
          <div id="modal-saved-addresses-list" class="space-y-1.5">
            <div class="text-slate-400 italic text-center py-2">Loading saved addresses...</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function () {
      'use strict';

      const modal = document.getElementById('customer-modal');
      const btnClose = document.getElementById('btn-close-modal');

      function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }

      btnClose.addEventListener('click', closeModal);
      modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
          closeModal();
        }
      });

      document.querySelectorAll('.btn-inspect-customer').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const ds = btn.dataset;

          document.getElementById('modal-cust-name').textContent = ds.customerName;
          document.getElementById('modal-cust-phone').textContent = ds.customerPhone;
          document.getElementById('modal-cust-address').textContent = ds.deliveryAddress;
          document.getElementById('modal-cust-region-landmark').textContent = ds.region + (ds.landmark ? ' • ' + ds.landmark : '');
          document.getElementById('modal-cust-orders').textContent = ds.lifetimeOrders;
          document.getElementById('modal-cust-spend').textContent = '₹' + ds.lifetimeSpend;
          document.getElementById('modal-cust-reg').textContent = ds.createdAt;

          const isVerified = (ds.verified === '1');
          const badgeWrap = document.getElementById('modal-cust-verified-badge');
          badgeWrap.className = isVerified ? 'badge-status badge-status-delivered' : 'badge-status badge-status-placed';
          badgeWrap.textContent = isVerified ? 'GPS Verified' : 'Approx Area';

          const mapsBtn = document.getElementById('modal-cust-maps-btn');
          const coordsText = document.getElementById('modal-cust-coords');
          if (ds.lat && ds.lng && parseFloat(ds.lat) > 0) {
            coordsText.textContent = `${parseFloat(ds.lat).toFixed(5)}, ${parseFloat(ds.lng).toFixed(5)}`;
            mapsBtn.href = `https://www.google.com/maps/dir/?api=1&destination=${ds.lat},${ds.lng}`;
          } else {
            coordsText.textContent = 'GPS: Estimated Centroid';
            mapsBtn.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(ds.deliveryAddress + ' ' + ds.region)}`;
          }

          // Gate photo
          const photoWrap = document.getElementById('modal-cust-photo-wrap');
          const photoImg  = document.getElementById('modal-cust-gate-img');
          const noPhoto   = document.getElementById('modal-cust-no-photo');
          if (ds.gatePhoto && ds.gatePhoto.trim() !== '') {
            photoImg.src = ds.gatePhoto;
            photoWrap.classList.remove('hidden');
            noPhoto.classList.add('hidden');
          } else {
            photoWrap.classList.add('hidden');
            noPhoto.classList.remove('hidden');
          }

          // Fetch saved addresses from customer lookup
          const addrsList = document.getElementById('modal-saved-addresses-list');
          addrsList.innerHTML = '<div class="text-slate-400 italic text-center py-2">Loading saved addresses...</div>';

          try {
            const resp = await fetch('customers.php?action=get_addresses&customer_id=' + encodeURIComponent(ds.customerId) + '&phone=' + encodeURIComponent(ds.customerPhone));
            const data = await resp.json();
            if (data.success && data.addresses && data.addresses.length > 0) {
              addrsList.innerHTML = '';
              data.addresses.forEach((addr) => {
                const item = document.createElement('div');
                item.className = 'p-2 bg-white border border-slate-200 rounded-lg text-xs space-y-0.5';
                item.innerHTML = `
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-800">${escapeHtml(addr.label || 'Saved Address')}</span>
                    ${addr.is_default == 1 ? '<span class="px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">Default</span>' : ''}
                  </div>
                  <div class="text-slate-600 text-[11px]">${escapeHtml(addr.delivery_address)}</div>
                  <div class="text-slate-400 text-[10px]">${escapeHtml(addr.region || '')} ${addr.landmark ? '• ' + escapeHtml(addr.landmark) : ''}</div>
                `;
                addrsList.appendChild(item);
              });
            } else {
              addrsList.innerHTML = `
                <div class="p-2 bg-white border border-slate-200 rounded-lg text-xs">
                  <span class="font-semibold text-slate-700">Home (Primary Address)</span>
                  <div class="text-slate-500 text-[11px] mt-0.5">${escapeHtml(ds.deliveryAddress)}</div>
                </div>
              `;
            }
          } catch (e) {
            addrsList.innerHTML = `
              <div class="p-2 bg-white border border-slate-200 rounded-lg text-xs">
                <span class="font-semibold text-slate-700">Primary Address</span>
                <div class="text-slate-500 text-[11px] mt-0.5">${escapeHtml(ds.deliveryAddress)}</div>
              </div>
            `;
          }

          modal.classList.remove('hidden');
          modal.classList.add('flex');
        });
      });

      function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

    })();
  </script>
</body>
</html>
