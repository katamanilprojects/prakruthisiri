<?php
declare(strict_types=1);

/**
 * Prakruthi Siri - Unified Harvest & Stock Portal
 * Modular Tab Dispatcher (Stock Entry, Field Harvest Requirements, Crate Packing Checklist)
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
if ($currentTab === 'stock') {
    if ($activeRun) {
        $products = $runInventoryService->getRunCatalog($activeRunId, false);
    } else {
        $prodStmt = $pdo->query("SELECT * FROM `products` ORDER BY `category` DESC, `name` ASC");
        $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

// ----------------------------------------------------------------------------
// TAB 2 & 3 DATA: Field Harvest & Packing Aggregates
// ----------------------------------------------------------------------------
$harvestSummary = [];
$totalHarvestKg = 0.0;
$totalHarvestPackets = 0;
$packingOrders = [];
$itemsByOrder = [];

if ($currentTab === 'harvest' || $currentTab === 'packing' || isset($_GET['export'])) {
    $harvestStmt = $pdo->prepare("
        SELECT 
            p.`id` AS `product_id`,
            p.`name` AS `product_name`,
            p.`telugu_name`,
            p.`category`,
            SUM(oi.`half_kg_quantity`) AS `total_packets`,
            SUM(oi.`half_kg_quantity` * COALESCE(p.`unit_weight_kg`, 0.500)) AS `total_kg`,
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

    foreach ($harvestSummary as $h) {
        $totalHarvestKg += (float) $h['total_kg'];
        $totalHarvestPackets += (int) $h['total_packets'];
    }

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

    if (!empty($packingOrders)) {
        $orderIds = array_column($packingOrders, 'id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemsStmt = $pdo->prepare("
            SELECT oi.`order_id`, oi.`half_kg_quantity`, p.`name`, p.`telugu_name`, p.`unit_label`
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
}

// CSV Export Handler
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
            data-schedule-id="<?= $activeRunId ?>"
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

    <!-- Render Active Tab -->
    <?php
      if ($currentTab === 'stock') {
          require __DIR__ . '/tabs/tab-stock.php';
      } elseif ($currentTab === 'harvest') {
          require __DIR__ . '/tabs/tab-harvest.php';
      } elseif ($currentTab === 'packing') {
          require __DIR__ . '/tabs/tab-packing.php';
      }
    ?>
  </main>
  <?php if ($currentTab === 'stock'): ?>
    <script src="assets/js/inventory-stock.js"></script>
  <?php endif; ?>
</body>
</html>
