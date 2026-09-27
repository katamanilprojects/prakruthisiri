<?php
declare(strict_types=1);

$driverName        = $driverName ?? 'Driver';
$orders            = $orders ?? [];
$totalStops        = $totalStops ?? 0;
$deliveredStops    = $deliveredStops ?? 0;
$selectedFormatted = $selectedFormatted ?? date('d M Y');
$availableRuns     = $availableRuns ?? [];
$scheduleInfo      = $scheduleInfo ?? null;
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Driver Manifest | <?= htmlspecialchars($driverName, ENT_QUOTES) ?> - Prakruthi Siri</title>
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#059669">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="PS Driver">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="../assets/icons/icon-192.png">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(err => console.error('SW reg error:', err));
      });
    }
  </script>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../public/assets/css/theme.css">
</head>
<body class="min-h-full bg-slate-50 text-slate-900 flex flex-col font-sans pb-24 antialiased">

  <!-- ====================================================================== -->
  <!-- TOP APP BAR                                                            -->
  <!-- ====================================================================== -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
    <div class="max-w-xl mx-auto px-4 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-xl shadow-xs">
          🛵
        </div>
        <div>
          <div class="text-sm font-extrabold text-slate-900 leading-tight truncate max-w-[160px]">
            <?= htmlspecialchars($driverName, ENT_QUOTES) ?>
          </div>
          <div id="driver-app-sub" class="text-xs text-slate-500 font-medium">
            Rider Delivery Manifest
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button type="button" id="btn-pwa-install-driver" onclick="triggerDriverPwaInstall()" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs flex items-center gap-1 cursor-pointer">
          <span>📲</span>
          <span id="driver-install-label" data-i18n="install_app">Install App</span>
        </button>

        <!-- Language Switcher -->
        <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 select-none text-xs font-bold">
          <button type="button" id="driver-lang-te" class="px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition">తెలుగు</button>
          <button type="button" id="driver-lang-en" class="px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 transition">English</button>
        </div>
        
        <a href="logout.php" id="link-driver-logout" class="px-3 py-1.5 text-xs font-bold text-slate-700 hover:text-rose-700 bg-slate-100 hover:bg-rose-50 border border-slate-200 rounded-lg transition">
          Sign Out
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-xl mx-auto w-full px-4 py-4 space-y-4 flex-1">
    
    <!-- ==================================================================== -->
    <!-- RUN SELECTOR & PROGRESS CARD                                         -->
    <!-- ==================================================================== -->
    <div class="app-card space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <span id="label-scheduled-run" class="text-xs font-bold uppercase tracking-wider text-slate-400">Delivery Batch</span>
          <h2 class="text-base font-extrabold text-slate-900 leading-tight mt-0.5">
            <?php if (!empty($scheduleInfo)): ?>
              <?= htmlspecialchars($scheduleInfo['delivery_day'] . ', ' . date('d M Y', strtotime($scheduleInfo['delivery_date'])) . ' — ' . $scheduleInfo['target_region'], ENT_QUOTES) ?>
            <?php else: ?>
              <?= htmlspecialchars($selectedFormatted, ENT_QUOTES) ?>
            <?php endif; ?>
          </h2>
        </div>

        <?php if (!empty($availableRuns)): ?>
          <form method="GET" action="route.php" class="shrink-0">
            <select name="run_id" onchange="this.form.submit()" class="app-select text-xs font-bold py-1 px-2.5 h-9 bg-slate-50">
              <?php foreach ($availableRuns as $r): ?>
                <option value="<?= $r['id'] ?>" <?= (!empty($scheduleInfo) && (int)$scheduleInfo['id'] === (int)$r['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($r['delivery_day'] . ' (' . $r['target_region'] . ')', ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        <?php endif; ?>
      </div>

      <!-- Financials & Progress Strip -->
      <?php
        $codPending = 0.0;
        $codCollected = 0.0;
        foreach ($orders as $o) {
          if (strtoupper($o['payment_method']) === 'COD') {
            if ($o['order_status'] === 'delivered') $codCollected += (float)$o['total_amount'];
            else $codPending += (float)$o['total_amount'];
          }
        }
        $percent = $totalStops > 0 ? round(($deliveredStops / $totalStops) * 100) : 0;
      ?>
      <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
        <div>
          <span id="label-stops" class="text-slate-500 font-medium">Stops Completed:</span> 
          <strong class="text-slate-900 font-bold font-mono text-sm"><?= $deliveredStops ?> / <?= $totalStops ?></strong>
        </div>
        <div>
          <span id="label-cod" class="text-slate-500 font-medium">COD to Collect:</span> 
          <strong class="text-amber-700 font-bold font-mono text-sm">₹<?= number_format($codPending, 2) ?></strong>
        </div>
      </div>

      <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
        <div class="bg-emerald-600 h-2.5 rounded-full transition-all duration-300" style="width: <?= $percent ?>%"></div>
      </div>
    </div>

    <!-- ==================================================================== -->
    <!-- STREAMLINED STOP LIST (#1 TO #N)                                     -->
    <!-- 4 Essential Data Points + 3 Finger-Friendly Buttons                  -->
    <!-- ==================================================================== -->
    <?php if (empty($orders)): ?>
      <?php if ((isset($isDispatched) && !$isDispatched) || (isset($isBatchReleased) && !$isBatchReleased)): ?>
        <div class="app-card text-center py-12 space-y-2.5">
          <span class="text-4xl">⏳</span>
          <h3 class="font-extrabold text-slate-900 text-base">Delivery Batch Not Yet Dispatched</h3>
          <p class="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
            The farm admin is preparing vegetables and packing crates. Your delivery route will appear here once the batch is approved and released to driver.
          </p>
        </div>
      <?php else: ?>
        <div class="app-card text-center py-12 space-y-2">
          <span class="text-4xl">📦</span>
          <h3 class="font-bold text-slate-900 text-base">No Stops Assigned</h3>
          <p class="text-xs text-slate-500">There are no active delivery stops assigned to your route for this batch.</p>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="space-y-3.5">
        <?php foreach ($orders as $idx => $o): ?>
          <?php
            $isDelivered = ($o['order_status'] === 'delivered');
            $isCod = (strtoupper($o['payment_method']) === 'COD');
            $stopNum = $o['route_sequence_number'] ?? ($idx + 1);
            $lat = (float)($o['latitude'] ?? 0);
            $lng = (float)($o['longitude'] ?? 0);
            
            // Direct single-stop navigation pin
            $mapsUrl = ($lat > 0 && $lng > 0 && empty($o['is_fallback_coord']))
              ? "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}"
              : "https://www.google.com/maps/search/?api=1&query=" . urlencode($o['delivery_address'] . ', ' . ($o['region'] ?? 'Hanamkonda'));
          ?>
          <div class="app-card space-y-3.5 <?= $isDelivered ? 'opacity-75 bg-slate-50' : '' ?>" id="stop-order-<?= $o['order_id'] ?>">
            
            <!-- Stop Header: 1. Stop # & 4. Amount to Collect (COD / Paid) -->
            <div class="flex items-center justify-between gap-2">
              <span class="card-badge <?= $isDelivered ? 'badge-emerald' : 'badge-slate' ?> font-extrabold text-sm py-1.5 px-3">
                Stop #<?= $stopNum ?>
              </span>

              <div class="text-right">
                <?php if ($isDelivered): ?>
                  <span class="card-badge badge-emerald">✓ Delivered</span>
                <?php else: ?>
                  <span class="font-mono font-extrabold text-sm <?= $isCod ? 'text-amber-800 bg-amber-50 border border-amber-200' : 'text-emerald-800 bg-emerald-50 border border-emerald-200' ?> px-2.5 py-1 rounded-lg">
                    <?= $isCod ? 'Collect: ₹' . number_format((float)$o['total_amount'], 2) : '✓ UPI Paid' ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- 2. Customer Name & Phone -->
            <div>
              <h3 class="text-base font-extrabold text-slate-900 leading-tight">
                <?= htmlspecialchars($o['customer_name'], ENT_QUOTES) ?>
              </h3>
              <div class="text-xs font-mono font-semibold text-slate-500 mt-0.5">
                📞 <?= htmlspecialchars($o['customer_phone'], ENT_QUOTES) ?> &bull; <span class="text-slate-400 font-sans">Order #<?= htmlspecialchars($o['order_code'], ENT_QUOTES) ?></span>
              </div>
            </div>

            <!-- 3. Address + Landmark -->
            <div class="text-xs text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1">
              <div class="font-medium text-slate-900"><?= htmlspecialchars($o['delivery_address'], ENT_QUOTES) ?></div>
              <?php if (!empty($o['landmark'])): ?>
                <div class="text-slate-500">Landmark: <span class="font-medium text-slate-700"><?= htmlspecialchars($o['landmark'], ENT_QUOTES) ?></span></div>
              <?php endif; ?>
              <?php if (!empty($o['delivery_notes'])): ?>
                <div class="text-amber-800 font-semibold">Note: <?= htmlspecialchars($o['delivery_notes'], ENT_QUOTES) ?></div>
              <?php endif; ?>
            </div>

            <!-- Vegetables Items Quick Pill -->
            <?php if (!empty($o['items'])): ?>
              <div class="text-xs text-slate-600 font-medium">
                <strong>Items:</strong> <?= htmlspecialchars(implode(', ', array_map(fn($it) => ($it['telugu_name'] ?: $it['product_name']) . ' × ' . $it['half_kg_quantity'] . ' ' . ($it['unit_label'] ?? 'pkts'), $o['items'])), ENT_QUOTES) ?>
              </div>
            <?php endif; ?>

            <?php
              $driverPhoneClean = preg_replace('/\D/', '', (string)$o['customer_phone']);
              if (strlen($driverPhoneClean) === 10) $driverPhoneClean = '91' . $driverPhoneClean;
              $driverWaMsg = "Namaste! Your Prakruthi Siri delivery driver is arriving at your doorstep in 5 minutes with order #" . $o['order_code'] . ". " . ($isCod ? "Total payable: ₹" . number_format((float)$o['total_amount'], 2) . " (COD)." : "Total: ₹" . number_format((float)$o['total_amount'], 2) . " (Paid via UPI).");
              $driverWaUrl = "https://wa.me/" . $driverPhoneClean . "?text=" . rawurlencode($driverWaMsg);
            ?>
            <!-- 3 Finger-Friendly Action Buttons -->
            <div class="grid grid-cols-3 gap-2 pt-1">
              <!-- 1. Call Customer Button -->
              <a 
                href="tel:<?= preg_replace('/[^0-9+]/', '', (string)$o['customer_phone']) ?>" 
                class="btn btn-secondary full-width text-xs px-2 flex items-center justify-center gap-1 font-bold"
              >
                <span>📞</span>
                <span class="btn-call-text">Call</span>
              </a>

              <!-- 2. WhatsApp Customer Button -->
              <a 
                href="<?= htmlspecialchars($driverWaUrl, ENT_QUOTES) ?>" 
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-secondary full-width text-xs px-2 flex items-center justify-center gap-1 font-bold text-emerald-700 hover:bg-emerald-50 border-emerald-200"
              >
                <span>💬</span>
                <span class="btn-wa-text">WhatsApp</span>
              </a>

              <!-- 3. Direct Single-Stop Google Maps Pin -->
              <a 
                href="<?= htmlspecialchars($mapsUrl, ENT_QUOTES) ?>" 
                target="_blank" 
                class="btn btn-secondary full-width text-xs px-2 flex items-center justify-center gap-1 font-bold text-sky-800 border-sky-200 hover:bg-sky-50"
              >
                <span>🧭</span>
                <span class="btn-map-text">Navigate</span>
              </a>
            </div>

            <!-- 3. Progressive Delivery Verification Button -->
            <?php if (!$isDelivered): ?>
              <?php if (!empty($o['is_location_verified'])): ?>
                <!-- Returning Customer with Verified Location: 1-Tap Mark Delivered -->
                <button 
                  type="button" 
                  onclick="openQuickDeliverModal(<?= (int)$o['order_id'] ?>, '<?= htmlspecialchars($o['order_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($o['customer_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($o['delivery_address'], ENT_QUOTES) ?>', <?= $isCod ? 'true' : 'false' ?>, <?= (float)$o['total_amount'] ?>)"
                  class="btn btn-primary btn-large btn-full mt-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold shadow-sm"
                >
                  <span>✓</span>
                  <span class="btn-quick-deliver-text">Mark Delivered</span>
                </button>
                <div class="text-center pt-1">
                  <button 
                    type="button" 
                    onclick="openProofModal(<?= (int)$o['order_id'] ?>, <?= (int)$o['customer_id'] ?>, '<?= htmlspecialchars($o['order_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($o['customer_name'], ENT_QUOTES) ?>', <?= $isCod ? 'true' : 'false' ?>, <?= (float)$o['total_amount'] ?>)"
                    class="text-[11px] text-slate-400 hover:text-slate-600 underline"
                  >
                    📷 Update Gate Photo / GPS
                  </button>
                </div>
              <?php else: ?>
                <!-- First-Time or Unverified Location Customer: Photo & GPS Verification Required -->
                <button 
                  type="button" 
                  onclick="openProofModal(<?= (int)$o['order_id'] ?>, <?= (int)$o['customer_id'] ?>, '<?= htmlspecialchars($o['order_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($o['customer_name'], ENT_QUOTES) ?>', <?= $isCod ? 'true' : 'false' ?>, <?= (float)$o['total_amount'] ?>)"
                  class="btn btn-primary btn-large btn-full mt-2 bg-sky-700 hover:bg-sky-800 text-white font-extrabold shadow-sm"
                >
                  <span><?= !empty($o['gate_photo_path']) ? '📍' : '📷' ?></span>
                  <span class="btn-first-deliver-text" data-has-photo="<?= !empty($o['gate_photo_path']) ? '1' : '0' ?>"><?= !empty($o['gate_photo_path']) ? '📍 Capture GPS &amp; Deliver' : 'First Delivery: Verify &amp; Deliver' ?></span>
                </button>
              <?php endif; ?>
            <?php else: ?>
              <?php if (!empty($o['gate_photo_path'])): ?>
                <div class="pt-1 flex items-center justify-between text-xs text-slate-500">
                  <span>✓ Photo Verified</span>
                  <a href="../public/<?= ltrim($o['gate_photo_path'], '/') ?>" target="_blank" class="text-emerald-700 font-bold hover:underline">View Photo ↗</a>
                </div>
              <?php endif; ?>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>

  <!-- ====================================================================== -->
  <!-- DOORSTEP PROOF-OF-DELIVERY MODAL                                      -->
  <!-- Native Camera Capture + Server-Side GD Compression                    -->
  <!-- ====================================================================== -->
  <div id="proof-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-2xl border border-slate-200">
      
      <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <div>
          <h3 class="font-extrabold text-base text-slate-900" id="modal-title">Doorstep Verification</h3>
          <span class="text-xs font-mono text-slate-500" id="modal-order-code">--</span>
        </div>
        <button type="button" onclick="closeProofModal()" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-700 text-xl font-bold leading-none">&times;</button>
      </div>

      <!-- Cash Alert in Modal if COD -->
      <div id="modal-cod-box" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs space-y-1 hidden">
        <div class="font-bold text-amber-900 flex items-center gap-1.5 text-sm">
          <span>💵</span>
          <span id="modal-cod-alert-title">Cash Collection Required</span>
        </div>
        <div class="text-amber-800">
          <span id="modal-cod-prefix">Please collect</span> 
          <strong id="modal-cod-amount" class="text-sm font-mono text-amber-900 font-extrabold">₹0.00</strong> 
          <span id="modal-cod-suffix">in cash from the customer.</span>
        </div>
      </div>

      <!-- Native Camera Capture Input (No Canvas Loops) -->
      <div class="space-y-2">
        <label id="modal-photo-label" class="block text-xs font-bold text-slate-700">
          Doorstep / Gate Photo: <span class="text-rose-500">*</span>
        </label>
        
        <input 
          type="file" 
          id="gate-photo-input" 
          accept="image/*" 
          capture="environment" 
          class="block w-full text-xs text-slate-500 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-800 hover:file:bg-emerald-100 cursor-pointer"
        >

        <div id="photo-preview-wrap" class="hidden mt-2 rounded-xl border border-slate-200 overflow-hidden bg-slate-100 flex items-center justify-center max-h-48">
          <img id="photo-preview" src="" alt="Gate Photo Preview" class="w-full h-auto object-cover">
        </div>
      </div>

      <!-- Modal Action Buttons -->
      <div class="space-y-2 pt-2">
        <button 
          type="button" 
          id="btn-confirm-delivery" 
          onclick="submitDeliveryProof()"
          class="btn btn-primary btn-large btn-full shadow-md text-sm"
        >
          <span>✓</span>
          <span id="btn-confirm-delivery-text">Complete &amp; Deliver Stop</span>
        </button>

        <button 
          type="button" 
          onclick="closeProofModal()" 
          class="btn btn-secondary btn-full text-xs"
        >
          Cancel
        </button>
      </div>
    </div>
  </div>

  <!-- ====================================================================== -->
  <!-- QUICK DELIVER CONFIRMATION MODAL (CDCApp Bottom Sheet/Modal)           -->
  <!-- ====================================================================== -->
  <div id="quick-deliver-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-end sm:items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <div>
          <h3 class="font-extrabold text-base text-slate-900" id="qd-title">Confirm Delivery</h3>
          <span class="text-xs font-mono text-slate-500" id="qd-order-code">--</span>
        </div>
        <button type="button" onclick="closeQuickDeliverModal()" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-700 text-xl font-bold leading-none">&times;</button>
      </div>

      <!-- Customer Summary -->
      <div class="text-xs text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1">
        <div class="font-bold text-slate-900" id="qd-customer-name">--</div>
        <div class="text-slate-500 text-[11px] leading-relaxed" id="qd-address">--</div>
      </div>

      <!-- COD Cash Confirmation Box -->
      <div id="qd-cod-box" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs space-y-2.5 hidden">
        <div class="flex items-center justify-between">
          <span class="font-bold text-amber-900 text-xs">💵 Cash to Collect:</span>
          <span class="text-base font-extrabold font-mono text-amber-900" id="qd-cod-amount">₹0.00</span>
        </div>
        <label class="flex items-start gap-2.5 p-2 bg-white rounded-lg border border-amber-300 cursor-pointer">
          <input type="checkbox" id="qd-cod-checkbox" onchange="toggleQuickDeliverSubmit()" class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500 w-4 h-4">
          <span class="text-xs font-bold text-amber-950">I confirm I have received this cash from the customer.</span>
        </label>
      </div>

      <!-- UPI Online Confirmation Box -->
      <div id="qd-upi-box" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs flex items-center gap-2 text-emerald-900 hidden">
        <span class="text-base">✓</span>
        <span class="font-bold text-xs">Pre-paid Online via UPI. No cash to collect.</span>
      </div>

      <div class="space-y-2 pt-1">
        <button 
          type="button" 
          id="btn-qd-confirm" 
          onclick="executeQuickDeliver()" 
          class="btn btn-primary btn-large btn-full shadow-md text-sm font-extrabold"
        >
          <span>✓</span>
          <span id="btn-qd-confirm-text">Confirm Delivery</span>
        </button>
        <button 
          type="button" 
          onclick="closeQuickDeliverModal()" 
          class="btn btn-secondary btn-full text-xs font-bold"
        >
          Cancel
        </button>
      </div>
    </div>
  </div>

  <script src="assets/js/i18n-driver.js"></script>
  <script src="assets/js/camera-gps.js"></script>
</body>
</html>
