<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/ConfigService.php';
require_once __DIR__ . '/../src/RunInventoryService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\ConfigService;
use PrakruthiSiri\RunInventoryService;

$activePage = 'orders';
$pdo = Database::getInstance()->getConnection();
$configService = new ConfigService($pdo);
$runInventoryService = new RunInventoryService($pdo);

$movThreshold = $configService->getMovThreshold();
$stdDeliveryFee = $configService->getStandardDeliveryFee();

// 1. Fetch upcoming and active runs
$runsStmt = $pdo->query("
    SELECT `id`, `delivery_date`, `delivery_day`, `target_region`, `is_ordering_open`, `status`
    FROM `delivery_schedules`
    ORDER BY `delivery_date` ASC
");
$allRuns = $runsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selectedRunId = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;
$activeRun = null;

if ($selectedRunId > 0) {
    foreach ($allRuns as $r) {
        if ((int)$r['id'] === $selectedRunId) { $activeRun = $r; break; }
    }
}
if (!$activeRun && !empty($allRuns)) {
    foreach ($allRuns as $r) {
        if ($r['delivery_date'] >= date('Y-m-d')) { $activeRun = $r; break; }
    }
    if (!$activeRun) $activeRun = $allRuns[0];
}

$targetDate = $activeRun['delivery_date'] ?? date('Y-m-d');
$targetRegion = $activeRun['target_region'] ?? 'Hanamkonda';
$targetScheduleId = (int)($activeRun['id'] ?? 0);
$activeRunBookedCount = $targetScheduleId > 0 ? $runInventoryService->getBookedOrdersCount($targetScheduleId) : 0;
$activeRunCatalog = $targetScheduleId > 0 ? $runInventoryService->getRunCatalog($targetScheduleId, false) : [];

// 2. Fetch orders for this run
$ordersStmt = $pdo->prepare("
    SELECT 
        o.`id`, o.`order_code`, o.`total_amount`, o.`order_status`, 
        o.`payment_method`, o.`payment_status`, o.`route_sequence_number`,
        o.`created_at`, o.`delivery_fee`,
        c.`full_name`, c.`phone_number`, c.`delivery_address`, c.`landmark`, c.`gate_photo_path`
    FROM `orders` o
    JOIN `customers` c ON o.`customer_id` = c.`id`
    WHERE (o.`schedule_id` = :sid OR (o.`schedule_id` IS NULL AND o.`target_delivery_date` = :tdate))
    ORDER BY COALESCE(o.`route_sequence_number`, 9999) ASC, o.`id` ASC
");
$ordersStmt->execute([':sid' => $targetScheduleId, ':tdate' => $targetDate]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch items for all orders in this run
$orderIds = array_column($orders, 'id');
$orderItemsMap = [];
$orderWeightMap = [];
$totalVegetableWeightKg = 0.0;

if (!empty($orderIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $pdo->prepare("
        SELECT oi.`order_id`, oi.`half_kg_quantity`, p.`name` AS `product_name`, p.`telugu_name`
        FROM `order_items` oi
        JOIN `products` p ON oi.`product_id` = p.`id`
        WHERE oi.`order_id` IN ($inPlaceholders)
        ORDER BY oi.`id` ASC
    ");
    $itemsStmt->execute($orderIds);
    $rawItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rawItems as $it) {
        $oid = (int)$it['order_id'];
        $qty = (int)$it['half_kg_quantity'];
        $weight = $qty * 0.5;
        $orderItemsMap[$oid][] = [
            'name' => $it['product_name'],
            'te_name' => $it['telugu_name'],
            'qty' => $qty,
            'weight' => $weight,
        ];
        $orderWeightMap[$oid] = ($orderWeightMap[$oid] ?? 0.0) + $weight;
    }
}

// Summary values and status counts
$statusCounts = ['all' => 0, 'placed' => 0, 'packed' => 0, 'out_for_delivery' => 0, 'delivered' => 0, 'cancelled' => 0];
$totalOrders = 0;
$codCash = 0.0;
$upiPaid = 0.0;
foreach ($orders as $o) {
    $status = $o['order_status'];
    $statusCounts['all']++;
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
    if ($status !== 'cancelled') {
        $totalOrders++;
        if ($o['payment_method'] === 'COD') {
            $codCash += (float)$o['total_amount'];
        } else {
            $upiPaid += (float)$o['total_amount'];
        }
        $totalVegetableWeightKg += ($orderWeightMap[(int)$o['id']] ?? 0.0);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders Management | Prakruthi Siri</title>
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
<body class="min-h-full bg-slate-50 text-slate-900 flex flex-col font-sans pb-16">
  <?php require __DIR__ . '/includes/masthead.php'; ?>

  <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 flex-1 space-y-5">
    <!-- Delivery Batch Selector & Quick Action Bar -->
    <div class="bg-white border border-slate-200 p-3.5 sm:p-5 rounded-2xl shadow-xs space-y-3">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <form method="GET" action="orders.php" class="flex flex-wrap items-center gap-2">
          <label for="run-select" class="font-bold text-slate-800 text-xs sm:text-sm whitespace-nowrap">Delivery Batch:</label>
          <select id="run-select" name="run_id" onchange="this.form.submit()" class="compact-select font-semibold text-slate-800 text-xs sm:text-sm min-h-[40px] flex-1 sm:flex-none">
            <?php foreach ($allRuns as $run): ?>
              <option value="<?= $run['id'] ?>" <?= ($activeRun && (int)$activeRun['id'] === (int)$run['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($run['delivery_day'] . ' Batch, ' . date('d M Y', strtotime($run['delivery_date'])) . ' — ' . $run['target_region'], ENT_QUOTES) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto">
          <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
            <span><strong class="text-slate-800"><?= htmlspecialchars($targetRegion, ENT_QUOTES) ?></strong></span>
            <span>•</span>
            <span><strong class="text-slate-800"><?= date('d M Y', strtotime($targetDate)) ?></strong></span>
          </div>

          <button 
            type="button" 
            onclick="openWhatsAppModal()" 
            class="btn btn-primary text-xs h-10 min-h-[40px] px-3.5 font-bold shadow-xs inline-flex items-center gap-1.5 shrink-0"
          >
            <span>💬 +</span>
            <span>Add WhatsApp Order</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 4 KPI Stat Cards Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-4">
      <div class="stat-card p-3 sm:p-4">
        <div class="stat-label text-[11px] sm:text-xs" data-i18n="active_orders">Active Orders</div>
        <div class="stat-number text-slate-900 text-xl sm:text-2xl font-extrabold mt-0.5"><?= $totalOrders ?></div>
      </div>
      <div class="stat-card p-3 sm:p-4">
        <div class="stat-label text-[11px] sm:text-xs" data-i18n="total_vegetables">Total Vegetables</div>
        <div class="stat-number text-emerald-600 text-xl sm:text-2xl font-extrabold mt-0.5"><?= number_format($totalVegetableWeightKg, 1) ?> <span class="text-xs font-semibold text-slate-500">Kg</span></div>
      </div>
      <div class="stat-card p-3 sm:p-4">
        <div class="stat-label text-[11px] sm:text-xs" data-i18n="cod_cash">COD Cash Due</div>
        <div class="stat-number text-amber-600 text-xl sm:text-2xl font-extrabold mt-0.5">₹<?= number_format($codCash, 0) ?></div>
      </div>
      <div class="stat-card p-3 sm:p-4">
        <div class="stat-label text-[11px] sm:text-xs" data-i18n="online_paid">Online Paid (UPI)</div>
        <div class="stat-number text-sky-600 text-xl sm:text-2xl font-extrabold mt-0.5">₹<?= number_format($upiPaid, 0) ?></div>
      </div>
    </div>

    <!-- Filter Pills & Search Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-white p-3 sm:p-3.5 rounded-2xl border border-slate-200 shadow-xs">
      <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1 md:pb-0 text-xs shrink-0">
        <button type="button" onclick="filterByStatus('all')" class="status-tab-btn px-3 py-1.5 rounded-lg font-bold transition bg-emerald-600 text-white shadow-xs shrink-0" data-status="all">
          All (<?= $statusCounts['all'] ?>)
        </button>
        <button type="button" onclick="filterByStatus('placed')" class="status-tab-btn px-3 py-1.5 rounded-lg font-bold transition text-slate-600 hover:bg-slate-100 shrink-0" data-status="placed">
          Placed (<?= $statusCounts['placed'] ?>)
        </button>
        <button type="button" onclick="filterByStatus('packed')" class="status-tab-btn px-3 py-1.5 rounded-lg font-bold transition text-slate-600 hover:bg-slate-100 shrink-0" data-status="packed">
          Packed (<?= $statusCounts['packed'] ?>)
        </button>
        <button type="button" onclick="filterByStatus('out_for_delivery')" class="status-tab-btn px-3 py-1.5 rounded-lg font-bold transition text-slate-600 hover:bg-slate-100 shrink-0" data-status="out_for_delivery">
          Out for Delivery (<?= $statusCounts['out_for_delivery'] ?>)
        </button>
        <button type="button" onclick="filterByStatus('delivered')" class="status-tab-btn px-3 py-1.5 rounded-lg font-bold transition text-slate-600 hover:bg-slate-100 shrink-0" data-status="delivered">
          Delivered (<?= $statusCounts['delivered'] ?>)
        </button>
        <button type="button" onclick="filterByStatus('cancelled')" class="status-tab-btn px-3 py-1.5 rounded-lg font-bold transition text-slate-600 hover:bg-slate-100 shrink-0" data-status="cancelled">
          Cancelled (<?= $statusCounts['cancelled'] ?>)
        </button>
      </div>

      <div class="relative w-full md:w-64">
        <input 
          type="text" 
          id="order-search" 
          placeholder="Search code, customer, phone..." 
          oninput="handleSearch(this.value)"
          class="compact-input w-full text-xs pl-8 pr-3 py-2 h-10 min-h-[40px]"
        >
        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
      </div>
    </div>

    <!-- Scannable Order Cards Grid -->
    <div id="orders-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php if (empty($orders)): ?>
        <div class="col-span-full app-card text-center py-12 text-slate-400">
          <p class="text-base font-semibold text-slate-600">No orders placed for this run yet.</p>
          <p class="text-xs text-slate-400 mt-1">Orders booked on the storefront will appear here automatically.</p>
        </div>
      <?php else: ?>
        <?php foreach ($orders as $idx => $o): ?>
          <?php 
            $oid = (int)$o['id'];
            $itemsList = $orderItemsMap[$oid] ?? [];
            $orderWeight = $orderWeightMap[$oid] ?? 0.0;
            $status = $o['order_status'];
          ?>
          <div 
            class="app-card order-card flex flex-col justify-between space-y-3.5 transition-all"
            data-order-id="<?= $oid ?>"
            data-status="<?= $status ?>"
            data-search="<?= htmlspecialchars(strtolower($o['order_code'] . ' ' . $o['full_name'] . ' ' . $o['phone_number']), ENT_QUOTES) ?>"
          >
            <!-- Card Header: Stop #, Code, Status & Payment Pill -->
            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3">
              <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center font-mono font-bold text-xs">
                  #<?= $o['route_sequence_number'] ?? ($idx + 1) ?>
                </span>
                <div>
                  <div class="font-mono font-bold text-slate-900 text-sm tracking-tight flex items-center gap-1.5">
                    <?= htmlspecialchars($o['order_code'], ENT_QUOTES) ?>
                  </div>
                  <div class="text-[11px] text-slate-400 font-medium">
                    <?= date('d M, h:i A', strtotime($o['created_at'])) ?>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-1.5 flex-wrap justify-end">
                <span class="badge-status badge-status-<?= htmlspecialchars($status, ENT_QUOTES) ?>" id="status-badge-<?= $oid ?>">
                  <?= strtoupper(str_replace('_', ' ', $status)) ?>
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $o['payment_method'] === 'COD' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-sky-50 text-sky-800 border border-sky-200' ?>">
                  <?= htmlspecialchars($o['payment_method'], ENT_QUOTES) ?>
                </span>
              </div>
            </div>

            <!-- Customer & Delivery Address -->
            <div class="space-y-1 text-xs">
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($o['full_name'], ENT_QUOTES) ?></span>
                <div class="flex items-center gap-2">
                  <a href="tel:<?= htmlspecialchars($o['phone_number'], ENT_QUOTES) ?>" class="text-emerald-600 font-semibold hover:underline font-mono">
                    📞 <?= htmlspecialchars($o['phone_number'], ENT_QUOTES) ?>
                  </a>
                  <?php
                    $cPhone = preg_replace('/\D/', '', (string)$o['phone_number']);
                    if (strlen($cPhone) === 10) $cPhone = '91' . $cPhone;
                    $waAdminMsg = "Namaste! Your Prakruthi Siri vegetable basket #" . $o['order_code'] . " is packed and out for delivery today. " . ($o['payment_method'] === 'COD' ? "Cash to keep ready: ₹" . number_format((float)$o['total_amount'], 2) . "." : "Payment: Paid online via UPI.");
                    $waAdminUrl = "https://wa.me/" . $cPhone . "?text=" . rawurlencode($waAdminMsg);
                  ?>
                  <a href="<?= htmlspecialchars($waAdminUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2 py-0.5 rounded-lg transition" title="WhatsApp Customer">
                    💬 WhatsApp
                  </a>
                </div>
              </div>
              <p class="text-slate-600 leading-relaxed">
                <?= htmlspecialchars($o['delivery_address'], ENT_QUOTES) ?>
                <?php if (!empty($o['landmark'])): ?>
                  <span class="text-slate-400 font-normal"> (<?= htmlspecialchars($o['landmark'], ENT_QUOTES) ?>)</span>
                <?php endif; ?>
              </p>
              <?php if (!empty($o['gate_photo_path'])): ?>
                <div class="pt-1">
                  <a href="../public/<?= ltrim($o['gate_photo_path'], '/') ?>" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                    📷 View Gate Photo
                  </a>
                </div>
              <?php endif; ?>
            </div>

            <!-- Items Ordered (Pill tags) -->
            <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100">
              <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 mb-1.5">
                <span>Items (<?= count($itemsList) ?>)</span>
                <span class="font-mono text-emerald-700"><?= number_format($orderWeight, 1) ?> Kg total</span>
              </div>
              <div class="flex flex-wrap gap-1.5">
                <?php foreach ($itemsList as $it): ?>
                  <span class="inline-flex items-center gap-1 bg-white px-2 py-0.5 rounded-md border border-slate-200 text-xs text-slate-700">
                    <span><?= htmlspecialchars($it['name'], ENT_QUOTES) ?></span>
                    <span class="font-mono font-semibold text-slate-900 text-[11px]">× <?= $it['qty'] ?> pkt (<?= $it['weight'] ?>kg)</span>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Card Footer: Total Amount & Status Transition Dropdown -->
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
              <div>
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Total Amount</span>
                <span class="text-base font-extrabold text-slate-900 font-mono">
                  ₹<?= number_format((float)$o['total_amount'], 2) ?>
                </span>
                <?php if ($o['payment_method'] === 'COD'): ?>
                  <span class="text-[10px] text-amber-700 font-semibold block">COD cash due</span>
                <?php else: ?>
                  <span class="text-[10px] text-emerald-700 font-semibold block">Paid online</span>
                <?php endif; ?>
              </div>

              <!-- Status Transition Dropdown -->
              <div class="flex items-center gap-2">
                <select 
                  class="compact-select text-xs font-semibold py-1 px-2.5 cursor-pointer"
                  onchange="updateOrderStatus(<?= $oid ?>, this.value, this)"
                  id="status-select-<?= $oid ?>"
                  <?= $status === 'cancelled' ? 'disabled' : '' ?>
                >
                  <option value="placed" <?= $status === 'placed' ? 'selected' : '' ?>>Placed</option>
                  <option value="packed" <?= $status === 'packed' ? 'selected' : '' ?>>Packed</option>
                  <option value="out_for_delivery" <?= $status === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                  <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                  <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <?php if ($status !== 'cancelled'): ?>
                  <button 
                    type="button" 
                    onclick="cancelOrder(<?= $oid ?>)"
                    class="p-1.5 text-slate-400 hover:text-red-600 rounded hover:bg-red-50 transition" 
                    title="Cancel Order & Return Stock"
                  >
                    🗑️
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <!-- ====================================================================== -->
  <!-- ADD WHATSAPP ORDER MODAL (CDCApp Card-First Modal)                     -->
  <!-- ====================================================================== -->
  <div id="whatsapp-order-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-3 sm:p-4 hidden overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[92vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden my-auto">
      
      <!-- Modal Header -->
      <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
        <div>
          <h3 class="font-bold text-base text-slate-900 flex items-center gap-2">
            <span>💬</span> Add WhatsApp Order
          </h3>
          <p class="text-xs text-slate-500 mt-0.5">
            Batch: <strong class="text-slate-800"><?= htmlspecialchars($activeRun['delivery_day'] . ' Batch (' . $targetRegion . ')', ENT_QUOTES) ?></strong> &bull; <?= date('d M Y', strtotime($targetDate)) ?>
          </p>
        </div>
        <button type="button" onclick="closeWhatsAppModal()" class="w-8 h-8 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-700 flex items-center justify-center text-xl font-bold leading-none">
          &times;
        </button>
      </div>

      <!-- Modal Body (Scrollable) -->
      <form id="whatsapp-order-form" onsubmit="submitWhatsAppOrder(event)" class="p-5 overflow-y-auto space-y-4 flex-1 text-xs">
        
        <!-- Batch Capacity Banner -->
        <?php if ($activeRunBookedCount >= 30): ?>
          <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2.5 text-amber-900">
            <span class="text-base">⚠️</span>
            <div>
              <div class="font-bold text-xs">Delivery Batch at Full Capacity (<?= $activeRunBookedCount ?>/30 Orders)</div>
              <div class="text-[11px] text-amber-800 mt-0.5">Storefront bookings are locked. Use <strong>Capacity Override</strong> below to book this WhatsApp order.</div>
            </div>
          </div>
        <?php else: ?>
          <div class="p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-emerald-900">
            <span class="font-medium text-[11px]">Active Batch Capacity:</span>
            <span class="font-bold font-mono text-xs"><?= $activeRunBookedCount ?> / 30 slots booked</span>
          </div>
        <?php endif; ?>

        <!-- Customer Identity (Phone First) -->
        <div class="space-y-3 p-3.5 bg-slate-50 rounded-xl border border-slate-200">
          <div class="font-bold text-slate-800 text-xs uppercase tracking-wider">1. Customer Information</div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1" for="wa-phone">
                Mobile Number (10 Digits) <span class="text-rose-500">*</span>
              </label>
              <input 
                type="tel" 
                id="wa-phone" 
                maxlength="10" 
                placeholder="e.g. 9876543210" 
                required 
                oninput="handleWhatsAppPhoneInput(this.value)"
                class="compact-input w-full font-mono text-sm"
              >
              <div id="wa-phone-status" class="text-[11px] font-medium mt-1 text-slate-400">Enter 10 digits to search profile</div>
            </div>

            <div>
              <label class="block font-semibold text-slate-700 mb-1" for="wa-name">
                Customer Full Name <span class="text-rose-500">*</span>
              </label>
              <input 
                type="text" 
                id="wa-name" 
                placeholder="Full Name" 
                required 
                class="compact-input w-full text-sm"
              >
            </div>
          </div>

          <!-- Region / Locality -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1" for="wa-region">
                Delivery Region <span class="text-rose-500">*</span>
              </label>
              <select id="wa-region" class="compact-select w-full font-semibold text-xs">
                <option value="Hanamkonda" <?= $targetRegion === 'Hanamkonda' ? 'selected' : '' ?>>Hanamkonda</option>
                <option value="Warangal" <?= $targetRegion === 'Warangal' ? 'selected' : '' ?>>Warangal</option>
              </select>
            </div>

            <div>
              <label class="block font-semibold text-slate-700 mb-1" for="wa-landmark">
                Landmark (Optional)
              </label>
              <input 
                type="text" 
                id="wa-landmark" 
                placeholder="e.g. Near Water Tank, Beside Temple" 
                class="compact-input w-full text-xs"
              >
            </div>
          </div>

          <!-- Location Link Input (Regex Parsed) -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1" for="wa-map-link">
              Paste WhatsApp Location Link or Lat, Long:
            </label>
            <input 
              type="text" 
              id="wa-map-link" 
              placeholder="Paste Google Maps link (e.g. https://maps.app.goo.gl/... or 18.0284, 79.6359)" 
              oninput="handleWhatsAppMapLink(this.value)"
              class="compact-input w-full font-mono text-xs"
            >
            <input type="hidden" id="wa-lat" value="">
            <input type="hidden" id="wa-lng" value="">
            <div id="wa-map-feedback" class="text-[11px] text-slate-500 font-medium mt-1">
              📍 Paste link from WhatsApp chat; coordinates will be parsed automatically.
            </div>
            <!-- Manual Coordinates Fallback -->
            <div id="wa-manual-coords-wrap" class="hidden mt-2 p-2.5 bg-amber-50/70 border border-amber-200 rounded-xl space-y-1.5">
              <div class="text-[11px] font-bold text-amber-900 flex items-center justify-between">
                <span>📍 Manual GPS Coordinates (Optional):</span>
                <span class="text-[10px] font-normal text-amber-700">Leave blank to use locality centroid</span>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Latitude</label>
                  <input type="text" id="wa-manual-lat" placeholder="e.g. 18.028439" class="compact-input w-full font-mono text-xs" oninput="handleManualCoordInput()">
                </div>
                <div>
                  <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Longitude</label>
                  <input type="text" id="wa-manual-lng" placeholder="e.g. 79.635941" class="compact-input w-full font-mono text-xs" oninput="handleManualCoordInput()">
                </div>
              </div>
            </div>
          </div>

          <!-- Address Textarea -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1" for="wa-address">
              Delivery Address <span class="text-rose-500">*</span>
            </label>
            <textarea 
              id="wa-address" 
              rows="2" 
              placeholder="House/Flat #, Street name, Colony / Area" 
              required
              class="compact-input w-full text-xs"
            ></textarea>
          </div>
        </div>

        <!-- Vegetables Selector -->
        <div class="space-y-3 p-3.5 bg-slate-50 rounded-xl border border-slate-200">
          <div class="flex items-center justify-between">
            <div class="font-bold text-slate-800 text-xs uppercase tracking-wider">2. Select Vegetables (0.5 Kg Packets)</div>
            <span class="text-xs font-mono font-bold text-emerald-700" id="wa-items-count">0 items selected</span>
          </div>

          <div class="space-y-2 max-h-56 overflow-y-auto pr-1" id="wa-catalog-list">
            <?php if (empty($activeRunCatalog)): ?>
              <div class="text-center py-6 text-slate-400">No vegetables active in this batch inventory.</div>
            <?php else: ?>
              <?php foreach ($activeRunCatalog as $item): ?>
                <?php 
                  $pid = (int)$item['product_id'];
                  $pPrice = (float)$item['price_per_half_kg'];
                  $pStock = (int)$item['available_half_kg_stock'];
                ?>
                <div class="flex items-center justify-between p-2.5 bg-white border border-slate-200 rounded-xl gap-2 hover:border-emerald-300 transition">
                  <div class="min-w-0 flex-1">
                    <div class="font-bold text-slate-900 truncate">
                      <?= htmlspecialchars($item['name'], ENT_QUOTES) ?>
                      <?php if (!empty($item['telugu_name'])): ?>
                        <span class="text-slate-500 font-normal text-[11px]">(<?= htmlspecialchars($item['telugu_name'], ENT_QUOTES) ?>)</span>
                      <?php endif; ?>
                    </div>
                    <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                      <span class="font-mono font-bold text-emerald-700">₹<?= number_format($pPrice, 0) ?></span>
                      <span>/ 0.5 kg</span>
                      <span>&bull;</span>
                      <span class="<?= $pStock <= 5 ? 'text-amber-700 font-semibold' : 'text-slate-500' ?> font-mono">
                        Stock: <?= $pStock ?> pkts (<?= $pStock * 0.5 ?> kg)
                      </span>
                    </div>
                  </div>

                  <!-- 48px Touch-Friendly Stepper -->
                  <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden bg-slate-50 shrink-0">
                    <button 
                      type="button" 
                      onclick="adjustWaItemQty(<?= $pid ?>, -1, <?= $pStock ?>)" 
                      class="w-10 h-10 flex items-center justify-center font-bold text-slate-700 hover:bg-slate-200 text-base select-none"
                    >
                      −
                    </button>
                    <span 
                      id="wa-qty-<?= $pid ?>" 
                      class="w-9 text-center font-mono font-bold text-slate-900 text-xs"
                      data-pid="<?= $pid ?>"
                      data-price="<?= $pPrice ?>"
                      data-stock="<?= $pStock ?>"
                    >
                      0
                    </span>
                    <button 
                      type="button" 
                      onclick="adjustWaItemQty(<?= $pid ?>, 1, <?= $pStock ?>)" 
                      class="w-10 h-10 flex items-center justify-center font-bold text-slate-700 hover:bg-slate-200 text-base select-none"
                    >
                      +
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Order Financial Summary Strip -->
          <div class="p-3 bg-white border border-slate-200 rounded-xl flex flex-wrap items-center justify-between gap-2 text-xs">
            <div>
              <span class="text-slate-500 font-medium">Vegetables Weight:</span> 
              <strong class="font-mono text-slate-800" id="wa-sum-weight">0.0 kg</strong>
            </div>
            <div>
              <span class="text-slate-500 font-medium">Vegetables Subtotal:</span> 
              <strong class="font-mono text-slate-800" id="wa-sum-subtotal">₹0</strong>
            </div>
            <div>
              <span class="text-slate-500 font-medium">Delivery:</span> 
              <strong class="font-mono text-emerald-700" id="wa-sum-delivery">Free</strong>
            </div>
            <div class="border-l border-slate-200 pl-3">
              <span class="text-slate-500 font-medium">Total:</span> 
              <strong class="font-mono text-base font-extrabold text-slate-900" id="wa-sum-total">₹0</strong>
            </div>
          </div>
        </div>

        <!-- Payment Method & Capacity Override -->
        <div class="space-y-3 p-3.5 bg-slate-50 rounded-xl border border-slate-200">
          <div class="font-bold text-slate-800 text-xs uppercase tracking-wider">3. Payment &amp; Batch Settings</div>

          <div class="flex items-center gap-4">
            <span class="font-semibold text-slate-700">Payment Mode:</span>
            <label class="inline-flex items-center gap-1.5">
              <input type="radio" name="wa_payment" value="COD" checked class="text-emerald-600 focus:ring-emerald-500">
              <span class="font-bold text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">💵 Cash on Delivery (COD)</span>
            </label>
          </div>

          <!-- Capacity Override Checkbox -->
          <label class="flex items-start gap-2.5 p-2.5 bg-white border border-slate-200 rounded-xl cursor-pointer hover:border-amber-300 transition">
            <input 
              type="checkbox" 
              id="wa-override-capacity" 
              <?= $activeRunBookedCount >= 30 ? 'checked' : '' ?>
              class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500 w-4 h-4"
            >
            <div>
              <div class="font-bold text-slate-800 text-xs">Admin Capacity Override</div>
              <p class="text-[11px] text-slate-500">Allow booking even if the hard limit of 30 orders has been reached for this batch.</p>
            </div>
          </label>
        </div>

        <!-- Modal Footer Actions -->
        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0">
          <button 
            type="button" 
            onclick="closeWhatsAppModal()" 
            class="btn btn-secondary text-xs h-10 px-4 font-bold"
          >
            Cancel
          </button>

          <button 
            type="submit" 
            id="wa-submit-btn" 
            class="btn btn-primary text-xs h-10 px-6 font-bold shadow-md inline-flex items-center gap-1.5"
          >
            <span>✓</span>
            <span>Book &amp; Confirm WhatsApp Order</span>
          </button>
        </div>

      </form>
    </div>
  </div>

  <script>
    let currentStatusFilter = 'all';
    let currentSearchTerm = '';
    const activeScheduleId = <?= $targetScheduleId ?>;

    function filterByStatus(status) {
      currentStatusFilter = status;
      document.querySelectorAll('.status-tab-btn').forEach(btn => {
        if (btn.dataset.status === status) {
          btn.className = 'status-tab-btn px-3 py-1.5 rounded-lg font-semibold transition bg-emerald-600 text-white shadow-xs';
        } else {
          btn.className = 'status-tab-btn px-3 py-1.5 rounded-lg font-semibold transition text-slate-600 hover:bg-slate-100';
        }
      });
      applyFilters();
    }

    function handleSearch(val) {
      currentSearchTerm = val.toLowerCase().trim();
      applyFilters();
    }

    function applyFilters() {
      const cards = document.querySelectorAll('.order-card');
      let visibleCount = 0;
      cards.forEach(card => {
        const statusMatch = (currentStatusFilter === 'all' || card.dataset.status === currentStatusFilter);
        const searchMatch = (!currentSearchTerm || card.dataset.search.includes(currentSearchTerm));
        if (statusMatch && searchMatch) {
          card.style.display = 'flex';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });
    }

    async function updateOrderStatus(orderId, newStatus, selectElem) {
      if (newStatus === 'cancelled') {
        if (!confirm('Cancel this order and immediately return vegetable quantities to stock?')) {
          selectElem.value = selectElem.closest('.order-card').dataset.status;
          return;
        }
      }

      selectElem.disabled = true;
      try {
        const resp = await fetch('api/orders-api.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({action: 'update_status', order_id: orderId, new_status: newStatus})
        });
        const data = await resp.json();
        if (data.success) {
          const card = document.querySelector(`.order-card[data-order-id="${orderId}"]`);
          if (card) {
            card.dataset.status = newStatus;
            const badge = document.getElementById(`status-badge-${orderId}`);
            if (badge) {
              badge.className = `badge-status badge-status-${newStatus}`;
              badge.textContent = newStatus.replace(/_/g, ' ').toUpperCase();
            }
            if (newStatus === 'cancelled') {
              selectElem.disabled = true;
            }
          }
        } else {
          alert('Error: ' + (data.error || 'Failed to update status'));
          selectElem.value = selectElem.closest('.order-card').dataset.status;
        }
      } catch (err) {
        alert('Network error updating order status');
      } finally {
        if (newStatus !== 'cancelled') {
          selectElem.disabled = false;
        }
      }
    }

    async function cancelOrder(id) {
      if (!confirm('Cancel this order and immediately return vegetable quantities to stock?')) return;
      try {
        const resp = await fetch('api/orders-api.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({action: 'cancel_order', order_id: id, reason: 'Admin cancelled'})
        });
        const data = await resp.json();
        if (data.success) {
          window.location.reload();
        } else {
          alert('Error: ' + (data.error || 'Could not cancel order'));
        }
      } catch (e) {
        alert('Network error');
      }
    }

    // ========================================================================
    // WHATSAPP ORDER INGESTION LOGIC
    // ========================================================================
    function openWhatsAppModal() {
      document.getElementById('whatsapp-order-modal').classList.remove('hidden');
    }

    function closeWhatsAppModal() {
      document.getElementById('whatsapp-order-modal').classList.add('hidden');
    }

    function handleManualCoordInput() {
      const mLat = document.getElementById('wa-manual-lat')?.value.trim() || '';
      const mLng = document.getElementById('wa-manual-lng')?.value.trim() || '';
      const latInput = document.getElementById('wa-lat');
      const lngInput = document.getElementById('wa-lng');
      const feedback = document.getElementById('wa-map-feedback');

      if (mLat && mLng && !isNaN(mLat) && !isNaN(mLng)) {
        latInput.value = mLat;
        lngInput.value = mLng;
        feedback.className = 'text-[11px] text-emerald-700 font-bold mt-1 font-mono';
        feedback.textContent = `✓ Manual Coordinates Set: ${parseFloat(mLat).toFixed(6)}, ${parseFloat(mLng).toFixed(6)}`;
      } else {
        latInput.value = '';
        lngInput.value = '';
      }
    }

    // Regex GPS Parser from WhatsApp/Google Maps Links
    function parseMapUrl(input) {
      if (!input) return null;
      input = input.trim();
      // Format 1: @18.028439,79.635941 or q=18.028439,79.635941 or 18.028439, 79.635941 or ll=18.028439,79.635941
      let match = input.match(/(-?\d{1,2}\.\d+)[,\s]+(-?\d{1,3}\.\d+)/);
      if (match) {
        return { lat: parseFloat(match[1]), lng: parseFloat(match[2]) };
      }
      // Format 2: Google Maps embed format !3d18.028439!4d79.635941
      match = input.match(/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/);
      if (match) {
        return { lat: parseFloat(match[1]), lng: parseFloat(match[2]) };
      }
      return null;
    }

    function handleWhatsAppMapLink(input) {
      const feedback = document.getElementById('wa-map-feedback');
      const latInput = document.getElementById('wa-lat');
      const lngInput = document.getElementById('wa-lng');
      const manualWrap = document.getElementById('wa-manual-coords-wrap');
      const manualLat = document.getElementById('wa-manual-lat');
      const manualLng = document.getElementById('wa-manual-lng');

      const parsed = parseMapUrl(input);

      if (parsed) {
        latInput.value = parsed.lat;
        lngInput.value = parsed.lng;
        if (manualLat) manualLat.value = parsed.lat;
        if (manualLng) manualLng.value = parsed.lng;
        feedback.className = 'text-[11px] text-emerald-700 font-bold mt-1 font-mono';
        feedback.textContent = `✓ Coordinates Extracted: ${parsed.lat.toFixed(6)}, ${parsed.lng.toFixed(6)}`;
        if (manualWrap) manualWrap.classList.add('hidden');
      } else if (input.includes('maps.app.goo.gl') || input.includes('goo.gl/maps')) {
        latInput.value = (manualLat && manualLat.value) ? manualLat.value : '';
        lngInput.value = (manualLng && manualLng.value) ? manualLng.value : '';
        feedback.className = 'text-[11px] text-amber-700 font-semibold mt-1';
        feedback.textContent = 'ℹ Shortened Maps link detected. Enter exact Lat/Lng below if known, or leave blank to use locality centroid.';
        if (manualWrap) manualWrap.classList.remove('hidden');
      } else if (input.trim().length > 0) {
        latInput.value = (manualLat && manualLat.value) ? manualLat.value : '';
        lngInput.value = (manualLng && manualLng.value) ? manualLng.value : '';
        feedback.className = 'text-[11px] text-amber-700 font-medium mt-1';
        feedback.textContent = '📍 Could not auto-detect lat/lng from this link. Enter Lat/Lng manually below or leave blank for area centroid.';
        if (manualWrap) manualWrap.classList.remove('hidden');
      } else {
        latInput.value = '';
        lngInput.value = '';
        if (manualLat) manualLat.value = '';
        if (manualLng) manualLng.value = '';
        feedback.className = 'text-[11px] text-slate-500 font-medium mt-1';
        feedback.textContent = '📍 Paste link from WhatsApp chat; coordinates will be parsed automatically.';
        if (manualWrap) manualWrap.classList.add('hidden');
      }
    }

    let phoneLookupTimer = null;
    function handleWhatsAppPhoneInput(val) {
      const digits = val.replace(/\D/g, '');
      const statusEl = document.getElementById('wa-phone-status');
      
      clearTimeout(phoneLookupTimer);
      if (digits.length < 10) {
        statusEl.className = 'text-[11px] font-medium mt-1 text-slate-400';
        statusEl.textContent = 'Enter 10 digits to search profile';
        return;
      }

      statusEl.className = 'text-[11px] font-medium mt-1 text-sky-600';
      statusEl.textContent = 'Searching customer records...';

      phoneLookupTimer = setTimeout(async () => {
        try {
          const resp = await fetch(`api/orders-api.php?action=lookup_customer&phone=${digits}`);
          const data = await resp.json();
          if (data.success && data.found && data.customer) {
            const c = data.customer;
            document.getElementById('wa-name').value = c.full_name || '';
            document.getElementById('wa-address').value = c.delivery_address || '';
            document.getElementById('wa-landmark').value = c.landmark || '';
            if (c.region) {
              const regSelect = document.getElementById('wa-region');
              for (let i = 0; i < regSelect.options.length; i++) {
                if (regSelect.options[i].value.toLowerCase() === c.region.toLowerCase()) {
                  regSelect.selectedIndex = i;
                  break;
                }
              }
            }
            if (c.latitude && c.longitude) {
              document.getElementById('wa-lat').value = c.latitude;
              document.getElementById('wa-lng').value = c.longitude;
              document.getElementById('wa-map-link').value = `${c.latitude}, ${c.longitude}`;
              handleWhatsAppMapLink(`${c.latitude}, ${c.longitude}`);
            }
            statusEl.className = 'text-[11px] font-bold mt-1 text-emerald-700';
            statusEl.textContent = `✓ Returning Customer: ${c.full_name} (${c.is_location_verified ? 'Verified Pin' : 'Profile Loaded'})`;
          } else {
            statusEl.className = 'text-[11px] font-medium mt-1 text-slate-500';
            statusEl.textContent = 'New customer — please complete address details';
          }
        } catch (e) {
          statusEl.textContent = '';
        }
      }, 300);
    }

    // Vegetables Steppers & Financial Calculations
    function adjustWaItemQty(productId, change, maxStock = 999) {
      const qtyEl = document.getElementById(`wa-qty-${productId}`);
      if (!qtyEl) return;
      let cur = parseInt(qtyEl.textContent, 10) || 0;
      let next = cur + change;
      if (next < 0) next = 0;
      if (maxStock > 0 && next > maxStock) {
        alert(`Maximum available harvest stock for this batch is ${maxStock} pkts (${maxStock * 0.5} kg).`);
        next = maxStock;
      }
      qtyEl.textContent = next;
      recalculateWhatsAppOrderTotals();
    }

    const CONFIG_MOV_THRESHOLD = <?= json_encode($movThreshold) ?>;
    const CONFIG_DELIVERY_FEE = <?= json_encode($stdDeliveryFee) ?>;

    function recalculateWhatsAppOrderTotals() {
      const qtyEls = document.querySelectorAll('#wa-catalog-list [id^="wa-qty-"]');
      let totalPackets = 0;
      let totalSubtotal = 0.0;
      let distinctItems = 0;

      qtyEls.forEach(el => {
        const qty = parseInt(el.textContent, 10) || 0;
        const price = parseFloat(el.dataset.price) || 0.0;
        if (qty > 0) {
          totalPackets += qty;
          totalSubtotal += (qty * price);
          distinctItems++;
        }
      });

      const totalWeightKg = totalPackets * 0.5;
      const deliveryFee = (totalSubtotal >= CONFIG_MOV_THRESHOLD || totalSubtotal === 0) ? 0.0 : CONFIG_DELIVERY_FEE;
      const grandTotal = totalSubtotal + deliveryFee;

      document.getElementById('wa-items-count').textContent = `${distinctItems} item${distinctItems === 1 ? '' : 's'} selected`;
      document.getElementById('wa-sum-weight').textContent = `${totalWeightKg.toFixed(1)} kg`;
      document.getElementById('wa-sum-subtotal').textContent = `₹${Math.round(totalSubtotal)}`;
      document.getElementById('wa-sum-delivery').textContent = deliveryFee === 0 ? 'Free' : `₹${Math.round(deliveryFee)}`;
      document.getElementById('wa-sum-total').textContent = `₹${Math.round(grandTotal)}`;
    }
    const calculateWhatsAppTotals = recalculateWhatsAppOrderTotals;

    async function submitWhatsAppOrder(e) {
      e.preventDefault();
      
      const phone = document.getElementById('wa-phone').value.replace(/\D/g, '');
      const name = document.getElementById('wa-name').value.trim();
      const region = document.getElementById('wa-region').value;
      const address = document.getElementById('wa-address').value.trim();
      const landmark = document.getElementById('wa-landmark').value.trim();
      const lat = document.getElementById('wa-lat').value || document.getElementById('wa-manual-lat')?.value || '';
      const lng = document.getElementById('wa-lng').value || document.getElementById('wa-manual-lng')?.value || '';
      const overrideCapacity = document.getElementById('wa-override-capacity').checked;
      const paymentMethod = document.querySelector('input[name="wa_payment"]:checked')?.value || 'COD';

      if (phone.length < 10) {
        alert('Please enter a valid 10-digit mobile number.');
        return;
      }
      if (!name) {
        alert('Please enter customer full name.');
        return;
      }
      if (!address) {
        alert('Please enter delivery address.');
        return;
      }

      // Collect items
      const items = [];
      document.querySelectorAll('#wa-catalog-list [id^="wa-qty-"]').forEach(el => {
        const qty = parseInt(el.textContent, 10) || 0;
        const pid = parseInt(el.dataset.pid, 10);
        if (qty > 0 && pid > 0) {
          items.push({ product_id: pid, half_kg_quantity: qty });
        }
      });

      if (items.length === 0) {
        alert('Please select at least one vegetable item.');
        return;
      }

      const submitBtn = document.getElementById('wa-submit-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Booking WhatsApp Order...';

      try {
        const payload = {
          action: 'create_manual_order',
          schedule_id: activeScheduleId,
          override_capacity: overrideCapacity,
          payment_method: paymentMethod,
          delivery_notes: 'WhatsApp Order (Admin Manual Booking)',
          customer: {
            phone_number: phone,
            full_name: name,
            delivery_address: address,
            landmark: landmark || null,
            region: region,
            latitude: lat ? parseFloat(lat) : null,
            longitude: lng ? parseFloat(lng) : null
          },
          items: items
        };

        const resp = await fetch('api/orders-api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await resp.json();

        if (data.success) {
          alert(`Order ${data.order?.order_code || ''} booked successfully!`);
          window.location.reload();
        } else {
          alert('Error booking order: ' + (data.error || 'Unknown error'));
          submitBtn.disabled = false;
          submitBtn.textContent = '✓ Book & Confirm WhatsApp Order';
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        submitBtn.disabled = false;
        submitBtn.textContent = '✓ Book & Confirm WhatsApp Order';
      }
    }
  </script>
</body>
</html>

