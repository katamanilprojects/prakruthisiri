<?php
declare(strict_types=1);

/**
 * Prakruthi Siri - Unified Harvest & Stock Portal
 * CDCApp Standard: 3 Unified Tabs (Stock Entry, Field Harvest Requirements, Crate Packing Checklist)
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RunInventoryService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\RunInventoryService;

$activePage = 'inventory';
$pdo = Database::getInstance()->getConnection();
$runInventoryService = new RunInventoryService($pdo);

// 1. Fetch available delivery runs
$runsStmt = $pdo->query("
    SELECT `id`, `delivery_date`, `delivery_day`, `target_region`, `cutoff_datetime`, `is_ordering_open`, `status`, `harvest_date`
    FROM `delivery_schedules`
    ORDER BY `delivery_date` ASC
");
$allRuns = $runsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selectedRunId = isset($_GET['run_id']) ? (int) $_GET['run_id'] : 0;
$activeRun = null;
if ($selectedRunId > 0) {
    foreach ($allRuns as $r) {
        if ((int) $r['id'] === $selectedRunId) { $activeRun = $r; break; }
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
$harvestDate = $activeRun['harvest_date'] ?? date('Y-m-d', strtotime($targetDate . ' -1 day'));
$activeRunId = (int) ($activeRun['id'] ?? 0);

// Active Tab determination ('stock', 'harvest', 'packing')
$currentTab = $_GET['tab'] ?? 'stock';
if (!in_array($currentTab, ['stock', 'harvest', 'packing'], true)) {
    $currentTab = 'stock';
}

// ----------------------------------------------------------------------------
// TAB 1 DATA: Stock & Pricing Catalog
// ----------------------------------------------------------------------------
$products = [];
if ($activeRun) {
    $products = $runInventoryService->getRunCatalog($activeRunId, false);
} else {
    $prodStmt = $pdo->query("SELECT * FROM `products` ORDER BY `category` DESC, `name` ASC");
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// ----------------------------------------------------------------------------
// TAB 2 DATA: Field Harvest Requirements Aggregates
// ----------------------------------------------------------------------------
$harvestStmt = $pdo->prepare("
    SELECT 
        p.`id` AS `product_id`,
        p.`name` AS `product_name`,
        p.`telugu_name`,
        p.`category`,
        SUM(oi.`half_kg_quantity`) AS `total_packets`,
        (SUM(oi.`half_kg_quantity`) * 0.5) AS `total_kg`,
        COUNT(DISTINCT oi.`order_id`) AS `orders_count`
    FROM `order_items` oi
    JOIN `orders` o ON oi.`order_id` = o.`id`
    JOIN `products` p ON oi.`product_id` = p.`id`
    WHERE (o.`schedule_id` = :sid OR (o.`schedule_id` IS NULL AND o.`target_delivery_date` = :tdate))
      AND o.`order_status` != 'cancelled'
    GROUP BY p.`id`, p.`name`, p.`telugu_name`, p.`category`
    ORDER BY p.`category` DESC, `total_kg` DESC
");
$harvestStmt->execute([':sid' => $activeRunId, ':tdate' => $targetDate]);
$harvestSummary = $harvestStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalHarvestKg = 0.0;
$totalHarvestPackets = 0;
foreach ($harvestSummary as $h) {
    $totalHarvestKg += (float) $h['total_kg'];
    $totalHarvestPackets += (int) $h['total_packets'];
}

// ----------------------------------------------------------------------------
// TAB 3 DATA: Crate Packing Checklist
// ----------------------------------------------------------------------------
$packingOrdersStmt = $pdo->prepare("
    SELECT 
        o.`id`, o.`order_code`, o.`route_sequence_number`, o.`total_amount`,
        o.`payment_method`, o.`payment_status`, o.`delivery_notes`,
        c.`full_name`, c.`phone_number`, c.`delivery_address`, c.`landmark`
    FROM `orders` o
    JOIN `customers` c ON o.`customer_id` = c.`id`
    WHERE (o.`schedule_id` = :sid OR (o.`schedule_id` IS NULL AND o.`target_delivery_date` = :tdate))
      AND o.`order_status` != 'cancelled'
    ORDER BY COALESCE(o.`route_sequence_number`, 9999) ASC, o.`id` ASC
");
$packingOrdersStmt->execute([':sid' => $activeRunId, ':tdate' => $targetDate]);
$packingOrders = $packingOrdersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$itemsByOrder = [];
if (!empty($packingOrders)) {
    $orderIds = array_column($packingOrders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $pdo->prepare("
        SELECT oi.`order_id`, oi.`half_kg_quantity`, p.`name`, p.`telugu_name`
        FROM `order_items` oi
        JOIN `products` p ON oi.`product_id` = p.`id`
        WHERE oi.`order_id` IN ($placeholders)
        ORDER BY p.`name` ASC
    ");
    $itemsStmt->execute($orderIds);
    $rawItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rawItems as $it) {
        $itemsByOrder[$it['order_id']][] = $it;
    }
}

// CSV Export for Harvest & Packing
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Harvest_Plan_' . $targetDate . '_' . $targetRegion . '.csv"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['PRAKRUTHI SIRI - HARVEST PLAN & CRATE CHECKLIST']);
    fputcsv($out, ['Delivery Run', $targetDate, $targetRegion, date('l', strtotime($targetDate))]);
    fputcsv($out, ['Harvest Date', $harvestDate]);
    fputcsv($out, ['Total Kg Required', number_format($totalHarvestKg, 1) . ' kg']);
    fputcsv($out, ['Total Packets (0.5kg)', $totalHarvestPackets]);
    fputcsv($out, []);

    fputcsv($out, ['#', 'Vegetable Name', 'Telugu Name', 'Category', 'Total Packets (0.5kg)', 'Total Weight (Kg)', 'Orders Count']);
    foreach ($harvestSummary as $idx => $row) {
        fputcsv($out, [
            $idx + 1,
            $row['product_name'],
            $row['telugu_name'],
            ucfirst($row['category']),
            $row['total_packets'],
            number_format((float)$row['total_kg'], 1) . ' kg',
            $row['orders_count']
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['CRATE PACKING CHECKLIST (BY STOP)']);
    fputcsv($out, ['Stop #', 'Order Code', 'Customer', 'Mobile', 'Address', 'Landmark', 'Packets', 'Amount', 'Payment']);
    foreach ($packingOrders as $idx => $o) {
        $itemList = [];
        foreach ($itemsByOrder[$o['id']] ?? [] as $it) {
            $itemList[] = "{$it['name']} ({$it['telugu_name']}): {$it['half_kg_quantity']} pkts";
        }
        fputcsv($out, [
            $o['route_sequence_number'] ?? ($idx + 1),
            $o['order_code'],
            $o['full_name'],
            $o['phone_number'],
            $o['delivery_address'],
            $o['landmark'] ?? '',
            implode(' | ', $itemList),
            number_format((float)$o['total_amount'], 2),
            $o['payment_method'] . ' (' . $o['payment_status'] . ')'
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Harvest &amp; Stock Portal | Prakruthi Siri</title>
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
  <style>
    @media print {
      body { background: white !important; font-size: 11px !important; }
      .no-print { display: none !important; }
      .print-only { display: block !important; }
      .app-card { border: 1px solid #000 !important; box-shadow: none !important; break-inside: avoid; }
      .enterprise-table th { background: #f1f5f9 !important; color: #000 !important; }
    }
  </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900 flex flex-col font-sans pb-16">
  <?php require __DIR__ . '/includes/masthead.php'; ?>

  <!-- Print Header Only -->
  <div class="hidden print-only p-4 border-b-2 border-slate-900 mb-4">
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-lg font-bold uppercase">Prakruthi Siri — Field Harvest &amp; Crate Packing Sheet</h1>
        <p class="text-xs font-semibold text-slate-700">Region: <?= htmlspecialchars($targetRegion, ENT_QUOTES) ?> &bull; Harvest Date: <?= htmlspecialchars($harvestDate, ENT_QUOTES) ?></p>
      </div>
      <div class="text-right">
        <div class="text-xs font-bold">Delivery Run: <?= date('l, d M Y', strtotime($targetDate)) ?></div>
        <div class="text-xs text-slate-600">Total Demand: <?= number_format($totalHarvestKg, 1) ?> kg (<?= $totalHarvestPackets ?> pkts) across <?= count($packingOrders) ?> stops</div>
      </div>
    </div>
  </div>

  <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 flex-1 space-y-5">
    <!-- Run Selector & Context Strip -->
    <div class="bg-white border border-slate-200 p-4 sm:p-5 rounded-2xl shadow-xs flex flex-wrap items-center justify-between gap-4 no-print">
      <form method="GET" action="inventory.php" class="flex items-center gap-2.5">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($currentTab, ENT_QUOTES) ?>">
        <label for="run-select" class="font-bold text-slate-800 text-xs sm:text-sm whitespace-nowrap">Delivery Run:</label>
        <select id="run-select" name="run_id" onchange="this.form.submit()" class="compact-select font-semibold text-xs sm:text-sm text-slate-800">
          <?php foreach ($allRuns as $run): ?>
            <option value="<?= $run['id'] ?>" <?= ($activeRun && (int)$activeRun['id'] === (int)$run['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($run['delivery_day'] . ', ' . date('d M Y', strtotime($run['delivery_date'])) . ' — ' . $run['target_region'], ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <div class="flex items-center gap-3 text-xs text-slate-500 font-medium">
        <span>Region: <strong class="text-slate-800"><?= htmlspecialchars($targetRegion, ENT_QUOTES) ?></strong></span>
        <span>•</span>
        <span>Cutoff: <strong class="text-emerald-700"><?= !empty($activeRun['cutoff_datetime']) ? date('d M, h:i A', strtotime($activeRun['cutoff_datetime'])) : 'N/A' ?></strong></span>
      </div>
    </div>

    <!-- 3 Unified Sub-Navigation Tabs -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-2.5 rounded-2xl border border-slate-200 shadow-xs no-print">
      <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl text-xs font-semibold select-none overflow-x-auto no-scrollbar">
        <a 
          href="inventory.php?run_id=<?= $activeRunId ?>&tab=stock"
          class="px-3.5 py-1.5 rounded-lg transition shrink-0 <?= $currentTab === 'stock' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>"
        >
          1. Stock &amp; Pricing
        </a>
        <a 
          href="inventory.php?run_id=<?= $activeRunId ?>&tab=harvest"
          class="px-3.5 py-1.5 rounded-lg transition shrink-0 <?= $currentTab === 'harvest' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>"
        >
          2. Field Harvest Plan (<?= number_format($totalHarvestKg, 1) ?> kg)
        </a>
        <a 
          href="inventory.php?run_id=<?= $activeRunId ?>&tab=packing"
          class="px-3.5 py-1.5 rounded-lg transition shrink-0 <?= $currentTab === 'packing' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>"
        >
          3. Packing Checklist (<?= count($packingOrders) ?> Stops)
        </a>
      </div>

      <div class="flex items-center gap-2 text-xs justify-end">
        <?php if ($currentTab === 'stock'): ?>
          <button 
            type="button" 
            id="btn-save-stock" 
            class="btn btn-primary text-xs h-9 min-h-[36px] px-4 font-bold w-full sm:w-auto"
          >
            💾 Save Inventory
          </button>
        <?php elseif ($currentTab === 'harvest' || $currentTab === 'packing'): ?>
          <button 
            type="button" 
            onclick="window.print()" 
            class="btn btn-secondary text-xs h-9 min-h-[36px] px-3 font-bold"
          >
            🖨️ Print Sheet
          </button>
          <a 
            href="inventory.php?run_id=<?= $activeRunId ?>&export=csv" 
            class="btn btn-secondary text-xs h-9 min-h-[36px] px-3 font-bold"
          >
            📥 Export CSV
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ==================================================================== -->
    <!-- TAB 1: STOCK & PRICING INPUT -->
    <!-- ==================================================================== -->
    <?php if ($currentTab === 'stock'): ?>
      <div class="space-y-4">
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 rounded-2xl text-xs flex items-center justify-between">
          <div>
            <strong>Isolated Run Stocking:</strong> You are setting vegetables stock for 
            <strong><?= htmlspecialchars(($activeRun['delivery_day'] ?? '') . ', ' . date('d M Y', strtotime($targetDate)) . ' (' . $targetRegion . ')') ?></strong>.
            Packets automatically calculate as <strong>Kg &times; 2</strong> (0.5 kg customer units).
          </div>
          <span class="text-[11px] font-mono bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-md font-bold">
            Run #<?= $activeRunId ?>
          </span>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
          <div class="overflow-x-auto">
            <table class="enterprise-table">
              <thead>
                <tr>
                  <th>Vegetable Name</th>
                  <th>Category</th>
                  <th class="text-right w-44">Harvest Stock (Kg)</th>
                  <th class="text-right w-36">Available Packets</th>
                  <th class="text-right w-36">Price / 0.5kg</th>
                  <th class="text-center w-28">Live in Store</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $p): ?>
                  <?php
                    $packets = (int) $p['available_half_kg_stock'];
                    $kg = isset($p['harvest_kg']) ? (float)$p['harvest_kg'] : ($packets * 0.5);
                  ?>
                  <tr class="crop-row" data-id="<?= (int)($p['product_id'] ?? $p['id']) ?>">
                    <td>
                      <div class="font-bold text-slate-900"><?= htmlspecialchars($p['name'], ENT_QUOTES) ?></div>
                      <div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($p['telugu_name'], ENT_QUOTES) ?></div>
                    </td>
                    <td>
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $p['category'] === 'premium' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700' ?>">
                        <?= ucfirst($p['category']) ?>
                      </span>
                    </td>
                    <td class="text-right">
                      <div class="inline-flex items-center gap-1.5">
                        <input 
                          type="number" 
                          step="0.5" 
                          min="0" 
                          value="<?= number_format($kg, 1, '.', '') ?>"
                          class="input-kg compact-input w-24 text-right font-mono font-bold text-sm py-1"
                        >
                        <span class="text-xs font-semibold text-slate-500">kg</span>
                      </div>
                    </td>
                    <td class="text-right font-mono font-bold text-emerald-700">
                      <span class="packets-display text-sm"><?= $packets ?></span> <span class="text-[11px] font-normal text-slate-500">pkts</span>
                    </td>
                    <td class="text-right">
                      <div class="inline-flex items-center gap-1">
                        <span class="text-xs font-bold text-slate-400">₹</span>
                        <input 
                          type="number" 
                          step="1.00" 
                          min="1" 
                          value="<?= number_format((float)$p['price_per_half_kg'], 2, '.', '') ?>"
                          class="input-price compact-input w-20 text-right font-mono font-bold text-sm py-1"
                        >
                      </div>
                    </td>
                    <td class="text-center">
                      <input 
                        type="checkbox" 
                        class="input-active rounded border-slate-300 text-emerald-600 w-4 h-4 cursor-pointer focus:ring-emerald-500"
                        <?= !empty($p['is_active']) ? 'checked' : '' ?>
                      >
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <!-- ==================================================================== -->
    <!-- TAB 2: FIELD HARVEST REQUIREMENTS -->
    <!-- ==================================================================== -->
    <?php elseif ($currentTab === 'harvest'): ?>
      <div class="space-y-4">
        <!-- 3 KPI Cards for Field Harvest -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="stat-card">
            <div class="stat-label">Total Harvest Required</div>
            <div class="stat-number text-emerald-600"><?= number_format($totalHarvestKg, 1) ?> <span class="text-sm font-normal text-slate-500">Kg</span></div>
            <div class="text-xs text-slate-500 mt-1 font-medium">Exact yield needed from farm plots</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total 0.5kg Packets</div>
            <div class="stat-number text-slate-900"><?= number_format($totalHarvestPackets) ?> <span class="text-sm font-normal text-slate-500">packets</span></div>
            <div class="text-xs text-slate-500 mt-1 font-medium">Standard customer units</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Active Customer Orders</div>
            <div class="stat-number text-sky-600"><?= count($packingOrders) ?> <span class="text-sm font-normal text-slate-500">stops</span></div>
            <div class="text-xs text-slate-500 mt-1 font-medium">Booked for this delivery run</div>
          </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
          <div class="overflow-x-auto">
            <table class="enterprise-table">
              <thead>
                <tr>
                  <th class="w-12 text-center">#</th>
                  <th>Vegetable Name</th>
                  <th>Telugu Name</th>
                  <th>Category</th>
                  <th class="text-right">Packets (0.5kg)</th>
                  <th class="text-right">Harvest Weight</th>
                  <th class="text-right">Customer Orders</th>
                  <th class="text-center no-print w-28">Harvest Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($harvestSummary)): ?>
                  <tr><td colspan="8" class="text-center py-10 text-slate-400">No active orders placed for this delivery run.</td></tr>
                <?php else: ?>
                  <?php foreach ($harvestSummary as $idx => $row): ?>
                    <tr>
                      <td class="text-center font-mono font-bold text-slate-400"><?= $idx + 1 ?></td>
                      <td class="font-bold text-slate-900"><?= htmlspecialchars($row['product_name'], ENT_QUOTES) ?></td>
                      <td class="font-medium text-slate-600"><?= htmlspecialchars($row['telugu_name'], ENT_QUOTES) ?></td>
                      <td>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $row['category'] === 'premium' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700' ?>">
                          <?= ucfirst($row['category']) ?>
                        </span>
                      </td>
                      <td class="text-right font-mono font-bold text-slate-800"><?= $row['total_packets'] ?> pkts</td>
                      <td class="text-right font-mono font-extrabold text-emerald-700 text-sm"><?= number_format((float)$row['total_kg'], 1) ?> kg</td>
                      <td class="text-right font-mono text-slate-600"><?= $row['orders_count'] ?> baskets</td>
                      <td class="text-center no-print">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer select-none text-xs font-semibold text-slate-600">
                          <input type="checkbox" class="rounded border-slate-300 text-emerald-600 w-4 h-4 cursor-pointer">
                          <span>Harvested</span>
                        </label>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <?php if (!empty($harvestSummary)): ?>
                <tfoot>
                  <tr>
                    <td colspan="4" class="font-bold uppercase text-xs">Total Field Harvest</td>
                    <td class="text-right font-mono font-bold"><?= $totalHarvestPackets ?> pkts</td>
                    <td class="text-right font-mono font-extrabold text-emerald-700 text-sm"><?= number_format($totalHarvestKg, 1) ?> kg</td>
                    <td class="text-right font-mono font-bold"><?= count($packingOrders) ?> orders</td>
                    <td class="no-print"></td>
                  </tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>
      </div>

    <!-- ==================================================================== -->
    <!-- TAB 3: CRATE PACKING CHECKLIST -->
    <!-- ==================================================================== -->
    <?php elseif ($currentTab === 'packing'): ?>
      <div class="space-y-4">
        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-xs flex items-center justify-between text-xs">
          <div>
            <h3 class="font-bold text-slate-900 text-sm">Crate Packing Checklist</h3>
            <p class="text-slate-500 mt-0.5">Ordered sequentially by Stop #1..N for crate assembly before driver departure.</p>
          </div>
          <span class="px-3 py-1 bg-emerald-50 text-emerald-800 rounded-full font-bold border border-emerald-200">
            <?= count($packingOrders) ?> Total Crates
          </span>
        </div>

        <div class="space-y-3">
          <?php if (empty($packingOrders)): ?>
            <div class="app-card text-center py-10 text-slate-400">
              No orders to pack for this delivery run yet.
            </div>
          <?php else: ?>
            <?php foreach ($packingOrders as $idx => $o): ?>
              <?php 
                $oid = (int)$o['id'];
                $items = $itemsByOrder[$oid] ?? [];
              ?>
              <div class="app-card flex flex-col md:flex-row md:items-start justify-between gap-4 p-4">
                <!-- Left: Stop # and Order / Customer info -->
                <div class="space-y-1.5 md:max-w-xs w-full">
                  <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-mono font-extrabold text-sm shadow-xs">
                      #<?= $o['route_sequence_number'] ?? ($idx + 1) ?>
                    </span>
                    <div>
                      <div class="font-mono font-bold text-slate-900"><?= htmlspecialchars($o['order_code'], ENT_QUOTES) ?></div>
                      <div class="text-xs font-bold text-slate-800"><?= htmlspecialchars($o['full_name'], ENT_QUOTES) ?></div>
                    </div>
                  </div>

                  <div class="text-xs text-slate-600 pl-10">
                    <a href="tel:<?= htmlspecialchars($o['phone_number'], ENT_QUOTES) ?>" class="font-mono text-emerald-600 font-semibold hover:underline">
                      📞 <?= htmlspecialchars($o['phone_number'], ENT_QUOTES) ?>
                    </a>
                    <div class="text-slate-500 mt-0.5"><?= htmlspecialchars($o['delivery_address'], ENT_QUOTES) ?></div>
                    <?php if (!empty($o['landmark'])): ?>
                      <div class="text-slate-400 text-[11px]">Landmark: <?= htmlspecialchars($o['landmark'], ENT_QUOTES) ?></div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Center: Packing Item Checklist -->
                <div class="flex-1 bg-slate-50 rounded-xl p-3 border border-slate-100 space-y-2">
                  <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Vegetable Packets to Pack</div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <?php foreach ($items as $it): ?>
                      <label class="flex items-center gap-2 p-1.5 rounded-lg bg-white border border-slate-200 cursor-pointer select-none hover:border-emerald-300">
                        <input type="checkbox" class="rounded border-slate-300 text-emerald-600 w-4 h-4 cursor-pointer focus:ring-emerald-500">
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($it['name'], ENT_QUOTES) ?> (<?= htmlspecialchars($it['telugu_name'], ENT_QUOTES) ?>)</span>
                        <span class="ml-auto font-mono font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded text-[11px]">
                          × <?= (int)$it['half_kg_quantity'] ?>
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- Right: Financial Summary & Verification -->
                <div class="text-right md:w-36 space-y-1">
                  <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Order Amount</div>
                  <div class="font-mono font-extrabold text-slate-900 text-base">₹<?= number_format((float)$o['total_amount'], 2) ?></div>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $o['payment_method'] === 'COD' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-sky-50 text-sky-800 border border-sky-200' ?>">
                    <?= htmlspecialchars($o['payment_method'], ENT_QUOTES) ?>
                  </span>
                  <div class="text-[10px] text-slate-400"><?= htmlspecialchars($o['payment_status'], ENT_QUOTES) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <script>
    // Tab 1: Real-time Kg to 0.5kg packet multiplier
    document.querySelectorAll('.crop-row').forEach((row) => {
      const kgInput = row.querySelector('.input-kg');
      const pktDisplay = row.querySelector('.packets-display');
      if (kgInput && pktDisplay) {
        kgInput.addEventListener('input', () => {
          const kg = parseFloat(kgInput.value) || 0;
          pktDisplay.textContent = Math.round(kg * 2);
        });
      }
    });

    // Tab 1: Bulk Save Inventory
    document.getElementById('btn-save-stock')?.addEventListener('click', async () => {
      const btn = document.getElementById('btn-save-stock');
      btn.disabled = true;
      btn.textContent = 'Saving...';

      const items = [];
      document.querySelectorAll('.crop-row').forEach((row) => {
        items.push({
          id: parseInt(row.dataset.id, 10),
          stock_kg: parseFloat(row.querySelector('.input-kg').value) || 0,
          price: parseFloat(row.querySelector('.input-price').value) || 0,
          is_active: row.querySelector('.input-active').checked ? 1 : 0
        });
      });

      try {
        const resp = await fetch('api/catalog-api.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            action: 'bulk_update_inventory',
            schedule_id: <?= $activeRunId ?>,
            items: items
          })
        });
        const data = await resp.json();
        if (data.success) {
          alert(data.message || 'Harvest inventory updated successfully.');
          window.location.reload();
        } else {
          alert('Save failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      } finally {
        btn.disabled = false;
        btn.textContent = '💾 Save Harvest Inventory';
      }
    });
  </script>
</body>
</html>
