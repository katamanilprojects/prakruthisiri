<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Daily Operations Hub
 * REQ-ADM-02: 1-Click WhatsApp Menu Broadcast Generator
 * REQ-ADM-03: Consolidated 4-Step Daily Workflow for Farm Admin & Farmers
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RunInventoryService.php';
require_once __DIR__ . '/../src/ConfigService.php';
require_once __DIR__ . '/../src/FarmService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\RunInventoryService;
use PrakruthiSiri\ConfigService;
use PrakruthiSiri\FarmService;

$pdo = Database::getInstance()->getConnection();
$activePage = 'daily-hub';

$runInvService = new RunInventoryService($pdo);
$configService = new ConfigService($pdo);
$farmService   = new FarmService($pdo);

$storeWhatsApp = $configService->getStoreWhatsAppNumber();

// 1. Resolve Active Delivery Schedule
$schedules = $farmService->getUpcomingSchedules();
$selectedSchedId = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : ($schedules[0]['id'] ?? 0);

$currentSchedule = null;
if ($selectedSchedId > 0) {
    $currentSchedule = $runInvService->getScheduleById($selectedSchedId);
}

// 2. Step 1: Inventory & Pricing Data
$inventoryItems = [];
if ($selectedSchedId > 0) {
    $inventoryItems = $runInvService->getInventoryForSchedule($selectedSchedId, false);
}

// 3. Step 4: Harvest Aggregation & Packing Tags
$harvestSummary = [];
$packingOrders  = [];
$totalHarvestKg = 0.0;
$totalPackets   = 0;

if ($currentSchedule) {
    $targetDate = (string)$currentSchedule['delivery_date'];

    $hStmt = $pdo->prepare("
        SELECT 
            p.`id` AS `product_id`,
            p.`name` AS `product_name`,
            p.`telugu_name`,
            p.`category`,
            COALESCE(oi.`pricing_unit`, p.`pricing_unit`, 'half_kg') AS `pricing_unit`,
            SUM(oi.`half_kg_quantity`) AS `total_packets`,
            SUM(CASE WHEN COALESCE(oi.`pricing_unit`, p.`pricing_unit`, 'half_kg') = 'half_kg' THEN oi.`half_kg_quantity` * 0.500 ELSE 0 END) AS `total_kg`,
            COUNT(DISTINCT oi.`order_id`) AS `orders_count`
        FROM `order_items` oi
        JOIN `orders` o ON oi.`order_id` = o.`id`
        JOIN `products` p ON oi.`product_id` = p.`id`
        WHERE (o.`schedule_id` = :sid OR (o.`schedule_id` IS NULL AND o.`target_delivery_date` = :tdate))
          AND o.`order_status` != 'cancelled'
        GROUP BY p.`id`, p.`name`, p.`telugu_name`, p.`category`, `pricing_unit`
        ORDER BY p.`category` DESC, `total_kg` DESC
    ");
    $hStmt->execute([':sid' => $selectedSchedId, ':tdate' => $targetDate]);
    $harvestSummary = $hStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($harvestSummary as $h) {
        $totalHarvestKg += (float)$h['total_kg'];
        $totalPackets   += (int)$h['total_packets'];
    }

    $ordStmt = $pdo->prepare("
        SELECT 
            o.`id` AS `order_id`, o.`order_code`, o.`route_sequence_number`, o.`total_amount`,
            o.`payment_method`, o.`payment_status`, o.`delivery_notes`,
            c.`full_name` AS `customer_name`, c.`phone_number` AS `customer_phone`,
            c.`delivery_address`, c.`landmark`, c.`region`
        FROM `orders` o
        JOIN `customers` c ON o.`customer_id` = c.`id`
        WHERE (o.`schedule_id` = :sid OR (o.`schedule_id` IS NULL AND o.`target_delivery_date` = :tdate))
          AND o.`order_status` != 'cancelled'
        ORDER BY COALESCE(o.`route_sequence_number`, 9999) ASC, o.`id` ASC
    ");
    $ordStmt->execute([':sid' => $selectedSchedId, ':tdate' => $targetDate]);
    $packingOrders = $ordStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch line items for packing tags
    $orderIds = array_column($packingOrders, 'order_id');
    $itemsByOrder = [];
    if (!empty($orderIds)) {
        $inClause = implode(',', array_fill(0, count($orderIds), '?'));
        $itStmt = $pdo->prepare("
            SELECT oi.`order_id`, oi.`half_kg_quantity`, oi.`pricing_unit`, p.`name`, p.`telugu_name`
            FROM `order_items` oi
            JOIN `products` p ON oi.`product_id` = p.`id`
            WHERE oi.`order_id` IN ({$inClause})
            ORDER BY p.`name` ASC
        ");
        $itStmt->execute($orderIds);
        foreach ($itStmt->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $itemsByOrder[$it['order_id']][] = $it;
        }
    }
}

// 4. Generate WhatsApp Broadcast Copies (Telugu & English)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$hostUrl  = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'], 2) . '/public';

$region = $currentSchedule['target_region'] ?? 'Hanamkonda';
$storeUrl = $hostUrl . '/index.php?region=' . urlencode($region);
$delivDateStr = $currentSchedule ? date('d M Y (l)', strtotime((string)$currentSchedule['delivery_date'])) : date('d M Y');
$cutoffStr = $currentSchedule && !empty($currentSchedule['cutoff_datetime']) ? date('h:i A, d M', strtotime((string)$currentSchedule['cutoff_datetime'])) : '08:00 PM';

// Format vegetable items
$teVeggies = [];
$enVeggies = [];
$i = 1;
foreach ($inventoryItems as $item) {
    if (empty($item['is_active'])) continue;
    $unitLbl = $item['unit_label'] ?? '0.5 kg';
    $price = number_format((float)$item['price_per_half_kg'], 0);
    $teName = $item['telugu_name'] ?: $item['name'];
    $enName = $item['name'];

    $teVeggies[] = "{$i}. {$teName} ({$enName}) - ₹{$price} ({$unitLbl})";
    $enVeggies[] = "{$i}. {$enName} ({$teName}) - ₹{$price} ({$unitLbl})";
    $i++;
}

$teVeggiesList = implode("\n", $teVeggies);
$enVeggiesList = implode("\n", $enVeggies);

$teBroadcast = "🌱 *ప్రకృతి సిరి (Prakruthi Siri) - తాజా సేంద్రీయ కూరగాయలు*\n"
             . "డెలివరీ ప్రాంతం: *{$region}*\n"
             . "డెలివరీ తేదీ: *{$delivDateStr}*\n"
             . "ఆర్డర్ల ముగింపు సమయం: *{$cutoffStr}*\n\n"
             . "🌾 *ఈ రోజు తాజా పంట జాబితా:*\n"
             . "{$teVeggiesList}\n\n"
             . "📲 *ఆన్‌లైన్‌లో సులభంగా ఆర్డర్ చేయడానికి లింక్ నొక్కండి:*\n"
             . "{$storeUrl}\n\n"
             . "📞 ఫోన్ / వాట్సాప్ ద్వారా ఆర్డర్ ఇవ్వడానికి: {$storeWhatsApp}\n"
             . "_(రసాయనాలు లేని స్వచ్ఛమైన కూరగాయలు - డెలివరీ రోజే వేకువజామున కోత)_";

$enBroadcast = "🌱 *Prakruthi Siri - Chemical-Free Farm Harvest Menu*\n"
             . "Delivery Area: *{$region}*\n"
             . "Delivery Date: *{$delivDateStr}*\n"
             . "Orders Close: *{$cutoffStr}*\n\n"
             . "🌾 *Today's Fresh Harvest Catalog:*\n"
             . "{$enVeggiesList}\n\n"
             . "📲 *Tap link to order online in 1 minute:*\n"
             . "{$storeUrl}\n\n"
             . "📞 Phone / WhatsApp Orders: {$storeWhatsApp}\n"
             . "_(100% Organically grown vegetables, cut fresh at dawn on delivery day)_";
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Daily Operations Hub | Prakruthi Siri Admin</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../public/assets/css/theme.css">

  <style>
    @media print {
      body { background: white !important; color: black !important; }
      header, .no-print { display: none !important; }
      .print-only { display: block !important; }
      .print-break { page-break-after: always; }
      .app-card { border: 1px solid #ccc !important; box-shadow: none !important; }
    }
  </style>
</head>
<body class="min-h-full flex flex-col antialiased text-slate-900 bg-slate-50 pb-20">

  <?php require __DIR__ . '/includes/masthead.php'; ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-3 sm:px-6 py-5 space-y-6">

    <!-- Hub Header & Schedule Selector -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4 no-print">
      <div>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
          <span>⚡</span> <span>Daily Operations Hub (4-Step Workflow)</span>
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">
          Consolidated operations: Pricing Check &rarr; Proxy Orders &rarr; WhatsApp Blast &rarr; Picking Manifest
        </p>
      </div>

      <!-- Schedule Selector -->
      <form method="GET" action="daily-hub.php" class="flex items-center gap-2">
        <label class="text-xs font-bold text-slate-700 whitespace-nowrap">Delivery Batch:</label>
        <select name="schedule_id" onchange="this.form.submit()" class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-900 shadow-xs outline-none">
          <?php foreach ($schedules as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $s['id'] == $selectedSchedId ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['target_region'], ENT_QUOTES) ?> &bull; <?= htmlspecialchars($s['delivery_day'], ENT_QUOTES) ?>, <?= date('d M Y', strtotime($s['delivery_date'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <!-- 4-Step Linear Tabs / Overview -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 no-print">
      <a href="#step-1" class="p-3.5 bg-white border border-slate-200 hover:border-emerald-500 rounded-2xl shadow-xs transition block space-y-1">
        <div class="text-[11px] font-extrabold text-emerald-700 uppercase">Step 1</div>
        <div class="text-xs font-extrabold text-slate-900">🥦 Harvest &amp; Pricing</div>
        <div class="text-[11px] text-slate-500"><?= count($inventoryItems) ?> produce items</div>
      </a>

      <a href="#step-2" class="p-3.5 bg-white border border-slate-200 hover:border-emerald-500 rounded-2xl shadow-xs transition block space-y-1">
        <div class="text-[11px] font-extrabold text-emerald-700 uppercase">Step 2</div>
        <div class="text-xs font-extrabold text-slate-900">📝 Assisted Ordering</div>
        <div class="text-[11px] text-slate-500">Fast phone/WhatsApp proxy</div>
      </a>

      <a href="#step-3" class="p-3.5 bg-white border border-slate-200 hover:border-emerald-500 rounded-2xl shadow-xs transition block space-y-1">
        <div class="text-[11px] font-extrabold text-emerald-700 uppercase">Step 3</div>
        <div class="text-xs font-extrabold text-slate-900">💬 WhatsApp Broadcast</div>
        <div class="text-[11px] text-slate-500">1-Click Menu Generator</div>
      </a>

      <a href="#step-4" class="p-3.5 bg-white border border-slate-200 hover:border-emerald-500 rounded-2xl shadow-xs transition block space-y-1">
        <div class="text-[11px] font-extrabold text-emerald-700 uppercase">Step 4</div>
        <div class="text-xs font-extrabold text-slate-900">📦 Harvest &amp; Packing</div>
        <div class="text-[11px] text-slate-500"><?= number_format($totalHarvestKg, 1) ?> kg &bull; <?= count($packingOrders) ?> tags</div>
      </a>
    </div>

    <!-- ==================================================================== -->
    <!-- STEP 1: HARVEST & PRICING REVIEW -->
    <!-- ==================================================================== -->
    <section id="step-1" class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4 no-print">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Step 1</span>
          <h2 class="text-base font-extrabold text-slate-900">Harvest &amp; Pricing Review</h2>
          <p class="text-xs text-slate-500 mt-0.5">Check farmer's yield entries and adjust customer unit pricing before opening orders.</p>
        </div>
        <div class="flex items-center gap-2">
          <a href="../farmer/harvest.php?schedule_id=<?= (int)$selectedSchedId ?>" target="_blank" class="text-xs font-bold text-emerald-700 hover:underline">
            Farmer Portal &rarr;
          </a>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700">
          <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase border-b border-slate-100">
            <tr>
              <th class="px-3 py-2.5">Produce</th>
              <th class="px-3 py-2.5">Unit</th>
              <th class="px-3 py-2.5 text-right">Farmer Yield (Kg)</th>
              <th class="px-3 py-2.5 text-right">Stock (Packs)</th>
              <th class="px-3 py-2.5 text-right">Customer Price (₹)</th>
              <th class="px-3 py-2.5 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <?php foreach ($inventoryItems as $it): ?>
              <tr>
                <td class="px-3 py-2.5 font-bold text-slate-900">
                  <?= htmlspecialchars($it['telugu_name'] ?: $it['name'], ENT_QUOTES) ?>
                  <span class="text-slate-400 font-normal block text-[11px]"><?= htmlspecialchars($it['name'], ENT_QUOTES) ?></span>
                </td>
                <td class="px-3 py-2.5 text-slate-500 font-mono"><?= htmlspecialchars($it['unit_label'], ENT_QUOTES) ?></td>
                <td class="px-3 py-2.5 text-right font-mono font-bold text-emerald-700"><?= (float)$it['harvest_kg'] ?> kg</td>
                <td class="px-3 py-2.5 text-right font-mono font-bold text-slate-900"><?= (int)$it['available_half_kg_stock'] ?> pkts</td>
                <td class="px-3 py-2.5 text-right font-mono font-bold text-slate-900">₹<?= number_format((float)$it['price_per_half_kg'], 0) ?></td>
                <td class="px-3 py-2.5 text-center">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= !empty($it['is_active']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400' ?>">
                    <?= !empty($it['is_active']) ? 'Active' : 'Disabled' ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ==================================================================== -->
    <!-- STEP 2: ASSISTED PROXY ORDERING (PHONE / WHATSAPP) -->
    <!-- ==================================================================== -->
    <section id="step-2" class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4 no-print">
      <div class="border-b border-slate-100 pb-3">
        <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Step 2</span>
        <h2 class="text-base font-extrabold text-slate-900">Assisted Proxy Ordering (Take WhatsApp/Phone Order)</h2>
        <p class="text-xs text-slate-500 mt-0.5">Quickly book an order on behalf of an offline customer calling on WhatsApp.</p>
      </div>

      <div id="proxy-order-msg" class="hidden p-3 rounded-xl text-xs font-bold"></div>

      <form id="proxy-order-form" onsubmit="submitProxyOrder(event)" class="space-y-4 text-xs">
        <input type="hidden" name="schedule_id" value="<?= (int)$selectedSchedId ?>">

        <!-- Customer Phone & Details -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Customer Mobile (10 digits) *</label>
            <input 
              type="tel" 
              name="phone" 
              id="proxy-phone" 
              required 
              maxlength="10" 
              pattern="[0-9]{10}"
              placeholder="9876543210" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-900 outline-none"
            >
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Customer Full Name *</label>
            <input 
              type="text" 
              name="name" 
              id="proxy-name" 
              required 
              placeholder="e.g. Anjali Devi" 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-900 outline-none"
            >
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Region *</label>
            <select name="region" id="proxy-region" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-900 outline-none">
              <option value="Hanamkonda" <?= $region === 'Hanamkonda' ? 'selected' : '' ?>>Hanamkonda</option>
              <option value="Warangal" <?= $region === 'Warangal' ? 'selected' : '' ?>>Warangal</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Delivery Address *</label>
            <input 
              type="text" 
              name="address" 
              id="proxy-address" 
              required 
              placeholder="House #, Street, Colony..." 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 outline-none"
            >
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Landmark</label>
            <input 
              type="text" 
              name="landmark" 
              id="proxy-landmark" 
              placeholder="Near Water Tank, Beside Temple..." 
              class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 outline-none"
            >
          </div>
        </div>

        <!-- Produce Picker with Quantity Increments -->
        <div class="space-y-2 pt-2 border-t border-slate-100">
          <label class="block font-bold text-slate-700">Select Produce &amp; Quantities:</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 max-h-56 overflow-y-auto p-1">
            <?php foreach ($inventoryItems as $idx => $it): ?>
              <?php if (empty($it['is_active'])) continue; ?>
              <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-2">
                <div class="truncate">
                  <div class="font-bold text-slate-900 truncate"><?= htmlspecialchars($it['telugu_name'] ?: $it['name'], ENT_QUOTES) ?></div>
                  <div class="text-[10px] text-slate-500">₹<?= number_format((float)$it['price_per_half_kg'], 0) ?> / <?= htmlspecialchars($it['unit_label'], ENT_QUOTES) ?></div>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                  <input 
                    type="number" 
                    min="0" 
                    max="20" 
                    name="items[<?= $it['product_id'] ?>]" 
                    value="0" 
                    data-price="<?= (float)$it['price_per_half_kg'] ?>"
                    class="w-14 text-center px-1.5 py-1 bg-white border border-slate-300 rounded-lg font-bold font-mono text-slate-900"
                  >
                  <span class="text-[10px] text-slate-400 font-bold">pkts</span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Payment Mode & Submit -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100">
          <div class="flex items-center gap-4">
            <span class="font-bold text-slate-700">Payment:</span>
            <label class="inline-flex items-center gap-1.5 cursor-pointer font-bold">
              <input type="radio" name="payment_method" value="COD" checked class="text-emerald-600">
              <span>Cash on Delivery (COD)</span>
            </label>
            <label class="inline-flex items-center gap-1.5 cursor-pointer font-bold">
              <input type="radio" name="payment_method" value="UPI" class="text-emerald-600">
              <span>UPI / Online</span>
            </label>
          </div>

          <button 
            type="submit" 
            id="btn-proxy-submit"
            class="w-full sm:w-auto px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs transition flex items-center justify-center gap-1.5"
          >
            <span>🛒</span> <span>Book Proxy Order</span>
          </button>
        </div>
      </form>
    </section>

    <!-- ==================================================================== -->
    <!-- STEP 3: 1-CLICK WHATSAPP MENU GENERATOR (REQ-ADM-02) -->
    <!-- ==================================================================== -->
    <section id="step-3" class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4 no-print">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Step 3</span>
          <h2 class="text-base font-extrabold text-slate-900">1-Click WhatsApp Menu Generator (REQ-ADM-02)</h2>
          <p class="text-xs text-slate-500 mt-0.5">Pre-formatted single-language broadcast messages ready for your customer WhatsApp groups.</p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" onclick="openWhatsAppWeb()" class="px-3 py-1.5 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-bold hover:bg-emerald-100 transition flex items-center gap-1">
            <span>📲</span> <span>Open WhatsApp Web</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Telugu Broadcast Card -->
        <div class="p-4 bg-emerald-50/50 border border-emerald-200 rounded-2xl space-y-3">
          <div class="flex items-center justify-between border-b border-emerald-200 pb-2">
            <span class="font-extrabold text-xs text-emerald-900 flex items-center gap-1.5">
              <span>🌾</span> <span>తెలుగు మెసేజ్ (Telugu Broadcast)</span>
            </span>
            <button 
              type="button" 
              onclick="copyToClipboard('te-broadcast-text', this)"
              class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition flex items-center gap-1"
            >
              <span>📋</span> <span>Copy Message</span>
            </button>
          </div>
          <textarea 
            id="te-broadcast-text" 
            rows="10" 
            readonly 
            class="w-full p-2.5 bg-white border border-emerald-200 rounded-xl text-xs text-slate-800 font-mono leading-relaxed outline-none select-all"
          ><?= htmlspecialchars($teBroadcast, ENT_QUOTES) ?></textarea>
        </div>

        <!-- English Broadcast Card -->
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
          <div class="flex items-center justify-between border-b border-slate-200 pb-2">
            <span class="font-extrabold text-xs text-slate-900 flex items-center gap-1.5">
              <span>🇬🇧</span> <span>English Message (English Broadcast)</span>
            </span>
            <button 
              type="button" 
              onclick="copyToClipboard('en-broadcast-text', this)"
              class="px-3 py-1 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-bold shadow-xs transition flex items-center gap-1"
            >
              <span>📋</span> <span>Copy Message</span>
            </button>
          </div>
          <textarea 
            id="en-broadcast-text" 
            rows="10" 
            readonly 
            class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 font-mono leading-relaxed outline-none select-all"
          ><?= htmlspecialchars($enBroadcast, ENT_QUOTES) ?></textarea>
        </div>
      </div>
    </section>

    <!-- ==================================================================== -->
    <!-- STEP 4: HARVEST & PACKING MANIFEST (PRINTABLE) -->
    <!-- ==================================================================== -->
    <section id="step-4" class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-5">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3 no-print">
        <div>
          <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Step 4</span>
          <h2 class="text-base font-extrabold text-slate-900">Harvest &amp; Packing Manifest</h2>
          <p class="text-xs text-slate-500 mt-0.5">Consolidated field picking kilograms and customer crate packing tags.</p>
        </div>
        <button 
          type="button" 
          onclick="window.print()" 
          class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5"
        >
          <span>🖨️</span> <span>Print Manifest &amp; Tags</span>
        </button>
      </div>

      <!-- Print Header (Visible Only on Print) -->
      <div class="hidden print-only mb-4 border-b border-black pb-2 text-black">
        <h1 class="text-xl font-bold">PRAKRUTHI SIRI - HARVEST &amp; PACKING MANIFEST</h1>
        <p class="text-xs">
          Delivery Date: <strong><?= $delivDateStr ?></strong> | Region: <strong><?= $region ?></strong> | Printed: <?= date('d M Y, h:i A') ?>
        </p>
      </div>

      <!-- Field Picking Summary Table -->
      <div class="space-y-2">
        <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">1. Field Picking Requirements (Total <?= number_format($totalHarvestKg, 1) ?> Kg)</h3>
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
              <tr>
                <th class="p-2">#</th>
                <th class="p-2">Vegetable (Telugu / English)</th>
                <th class="p-2 text-right">Total Packets</th>
                <th class="p-2 text-right">Harvest Weight</th>
                <th class="p-2 text-right">Orders</th>
                <th class="p-2 text-center no-print">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($harvestSummary as $idx => $row): ?>
                <tr>
                  <td class="p-2 font-mono text-slate-400"><?= $idx + 1 ?></td>
                  <td class="p-2 font-bold text-slate-900">
                    <?= htmlspecialchars($row['telugu_name'] ?: $row['product_name'], ENT_QUOTES) ?>
                    <span class="text-slate-400 font-normal text-[11px]">(<?= htmlspecialchars($row['product_name'], ENT_QUOTES) ?>)</span>
                  </td>
                  <td class="p-2 text-right font-mono font-bold"><?= $row['total_packets'] ?> pkts</td>
                  <td class="p-2 text-right font-mono font-extrabold text-emerald-800">
                    <?= $row['pricing_unit'] === 'half_kg' ? number_format((float)$row['total_kg'], 1) . ' kg' : '-' ?>
                  </td>
                  <td class="p-2 text-right font-mono text-slate-600"><?= $row['orders_count'] ?> baskets</td>
                  <td class="p-2 text-center no-print">
                    <input type="checkbox" class="rounded text-emerald-600">
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Individual Customer Packing Tags -->
      <div class="space-y-2 pt-4">
        <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">2. Customer Packing Tags (<?= count($packingOrders) ?> Orders)</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <?php foreach ($packingOrders as $idx => $ord): ?>
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-xs">
              <div class="flex items-start justify-between border-b border-slate-200 pb-1.5">
                <div>
                  <span class="font-mono font-extrabold text-emerald-800">Stop #<?= $ord['route_sequence_number'] ?? ($idx + 1) ?></span>
                  <div class="font-extrabold text-slate-900 text-sm mt-0.5"><?= htmlspecialchars($ord['customer_name'], ENT_QUOTES) ?></div>
                  <div class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($ord['customer_phone'], ENT_QUOTES) ?></div>
                </div>
                <div class="text-right">
                  <span class="px-2 py-0.5 bg-slate-200 rounded text-[10px] font-mono font-bold">#<?= htmlspecialchars($ord['order_code'], ENT_QUOTES) ?></span>
                  <div class="font-mono font-bold text-slate-900 text-xs mt-1">₹<?= number_format((float)$ord['total_amount'], 0) ?></div>
                  <div class="text-[10px] text-slate-500"><?= htmlspecialchars($ord['payment_method'], ENT_QUOTES) ?></div>
                </div>
              </div>

              <!-- Itemized list -->
              <div class="space-y-1 text-[11px] divide-y divide-slate-100">
                <?php $custItems = $itemsByOrder[$ord['order_id']] ?? []; ?>
                <?php foreach ($custItems as $ci): ?>
                  <div class="pt-1 first:pt-0 flex justify-between">
                    <span><?= htmlspecialchars($ci['telugu_name'] ?: $ci['name'], ENT_QUOTES) ?></span>
                    <span class="font-mono font-bold"><?= (int)$ci['half_kg_quantity'] ?> pkts</span>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="pt-1.5 border-t border-slate-200 text-[10px] text-slate-500 truncate">
                📍 <?= htmlspecialchars($ord['delivery_address'], ENT_QUOTES) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

  </main>

  <script>
    function copyToClipboard(elementId, btn) {
      const textarea = document.getElementById(elementId);
      if (!textarea) return;
      navigator.clipboard.writeText(textarea.value).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span>✓</span> <span>Copied!</span>';
        btn.classList.add('bg-emerald-800');
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.classList.remove('bg-emerald-800');
        }, 2000);
      }).catch(err => {
        alert('Failed to copy: ' + err);
      });
    }

    function openWhatsAppWeb() {
      window.open('https://web.whatsapp.com', '_blank');
    }

    async function submitProxyOrder(e) {
      e.preventDefault();
      const form = document.getElementById('proxy-order-form');
      const formData = new FormData(form);
      const btn = document.getElementById('btn-proxy-submit');
      const msg = document.getElementById('proxy-order-msg');

      const phone = formData.get('phone');
      const name = formData.get('name');
      const address = formData.get('address');
      const landmark = formData.get('landmark');
      const region = formData.get('region');
      const scheduleId = parseInt(formData.get('schedule_id'), 10);
      const paymentMethod = formData.get('payment_method');

      const items = [];
      for (const [k, v] of formData.entries()) {
        const match = k.match(/^items\[(\d+)\]$/);
        if (match) {
          const pid = parseInt(match[1], 10);
          const qty = parseInt(v, 10);
          if (qty > 0) {
            items.push({
              product_id: pid,
              half_kg_quantity: qty,
              quantity: qty
            });
          }
        }
      }

      if (items.length === 0) {
        alert('Please select at least 1 vegetable item.');
        return;
      }

      btn.disabled = true;
      btn.classList.add('opacity-50');

      try {
        const resp = await fetch('api/orders-api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create_manual_order',
            schedule_id: scheduleId,
            override_capacity: true,
            customer: {
              phone_number: phone,
              full_name: name,
              delivery_address: address,
              landmark: landmark,
              region: region
            },
            items: items,
            payment_method: paymentMethod,
            delivery_notes: 'Proxy Phone Order (Daily Hub)'
          })
        });

        const res = await resp.json();
        if (res.success) {
          msg.className = 'p-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 block';
          msg.textContent = res.message || 'Order placed successfully!';
          form.reset();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          msg.className = 'p-3 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 block';
          msg.textContent = res.error || 'Failed to place order';
          btn.disabled = false;
          btn.classList.remove('opacity-50');
        }
      } catch (err) {
        msg.className = 'p-3 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 block';
        msg.textContent = 'Network error: ' + err.message;
        btn.disabled = false;
        btn.classList.remove('opacity-50');
      }
    }
  </script>
</body>
</html>
