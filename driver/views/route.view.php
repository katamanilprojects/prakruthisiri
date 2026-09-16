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
                <strong>Items:</strong> <?= htmlspecialchars(implode(', ', array_map(fn($it) => ($it['telugu_name'] ?: $it['product_name']) . ' × ' . $it['half_kg_quantity'], $o['items'])), ENT_QUOTES) ?>
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

  <script src="../assets/js/i18n-driver.js"></script>
  <script>
    let currentDriverLang = localStorage.getItem('ps_driver_lang') || 'te';
    let activeOrderId = 0;
    let activeCustomerId = 0;
    let currentGeoLat = null;
    let currentGeoLng = null;

    function acquireGpsLocation() {
      if (!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(
        pos => { 
          currentGeoLat = pos.coords.latitude; 
          currentGeoLng = pos.coords.longitude; 
        },
        err => { 
          console.warn('High accuracy GPS timed out (8s), falling back to standard accuracy:', err.message);
          navigator.geolocation.getCurrentPosition(
            pos => { 
              currentGeoLat = pos.coords.latitude; 
              currentGeoLng = pos.coords.longitude; 
            },
            err2 => { 
              console.warn('Geolocation completely unavailable or indoor timeout:', err2.message); 
            },
            { enableHighAccuracy: false, timeout: 8000, maximumAge: 120000 }
          );
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
      );
    }
    acquireGpsLocation();

    function setDriverLanguage(lang) {
      currentDriverLang = lang;
      localStorage.setItem('ps_driver_lang', lang);
      if (typeof DRIVER_I18N === 'undefined') return;

      const isTe = (lang === 'te');
      const t = DRIVER_I18N[lang] || DRIVER_I18N.te;

      const btnTe = document.getElementById('driver-lang-te');
      const btnEn = document.getElementById('driver-lang-en');

      if (isTe) {
        btnTe.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition';
        btnEn.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 transition';
      } else {
        btnEn.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition';
        btnTe.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 transition';
      }

      document.getElementById('driver-app-sub').textContent = t.app_bar_subtitle;
      document.getElementById('link-driver-logout').textContent = t.sign_out;
      document.getElementById('label-scheduled-run').textContent = t.scheduled_run;
      document.getElementById('label-stops').textContent = t.stops_label;
      document.getElementById('label-cod').textContent = t.cod_to_collect_label;

      document.querySelectorAll('.btn-call-text').forEach(el => el.textContent = t.call_btn);
      document.querySelectorAll('.btn-wa-text').forEach(el => el.textContent = t.whatsapp_btn || 'WhatsApp');
      document.querySelectorAll('.btn-map-text').forEach(el => el.textContent = t.map_btn);
      document.querySelectorAll('.btn-complete-text').forEach(el => el.textContent = t.deliver_verify_btn);
      document.querySelectorAll('.btn-quick-deliver-text').forEach(el => el.textContent = t.quick_deliver_btn);
      document.querySelectorAll('.btn-first-deliver-text').forEach(el => {
        if (el.dataset.hasPhoto === '1') {
          el.textContent = t.gps_pin_needed_btn || (isTe ? '📍 జీపీఎస్ లొకేషన్ & డెలివరీ' : '📍 Capture GPS & Deliver');
        } else {
          el.textContent = t.first_delivery_btn;
        }
      });
    }

    function openProofModal(orderId, customerId, orderCode, customerName, isCod, totalAmount) {
      activeOrderId = orderId;
      activeCustomerId = customerId;

      const t = DRIVER_I18N[currentDriverLang] || DRIVER_I18N.te;
      document.getElementById('modal-title').textContent = customerName + (currentDriverLang === 'te' ? ' గారి డెలివరీ' : ' Delivery');
      document.getElementById('modal-order-code').textContent = (currentDriverLang === 'te' ? 'ఆర్డర్ #' : 'Order #') + orderCode;

      const codBox = document.getElementById('modal-cod-box');
      if (isCod) {
        codBox.classList.remove('hidden');
        document.getElementById('modal-cod-amount').textContent = '₹' + Number(totalAmount).toFixed(2);
      } else {
        codBox.classList.add('hidden');
      }

      document.getElementById('gate-photo-input').value = '';
      document.getElementById('photo-preview-wrap').classList.add('hidden');
      document.getElementById('proof-modal').classList.remove('hidden');
    }

    function closeProofModal() {
      document.getElementById('proof-modal').classList.add('hidden');
      activeOrderId = 0;
      activeCustomerId = 0;
    }

    document.getElementById('gate-photo-input')?.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function (evt) {
        document.getElementById('photo-preview').src = evt.target.result;
        document.getElementById('photo-preview-wrap').classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    });

    async function compressImageFile(file, maxDimension = 1600, quality = 0.8) {
      return new Promise((resolve) => {
        if (!file || file.size < 800 * 1024) {
          resolve(file);
          return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = new Image();
          img.onload = () => {
            let width = img.width;
            let height = img.height;
            if (width > maxDimension || height > maxDimension) {
              if (width > height) {
                height = Math.round((height * maxDimension) / width);
                width = maxDimension;
              } else {
                width = Math.round((width * maxDimension) / height);
                height = maxDimension;
              }
            }
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);
            canvas.toBlob((blob) => {
              if (blob) {
                const compressedFile = new File([blob], (file.name || 'gate_photo.jpg').replace(/\.[^.]+$/, '.jpg'), {
                  type: 'image/jpeg',
                  lastModified: Date.now()
                });
                resolve(compressedFile);
              } else {
                resolve(file);
              }
            }, 'image/jpeg', quality);
          };
          img.onerror = () => resolve(file);
          img.src = e.target.result;
        };
        reader.onerror = () => resolve(file);
        reader.readAsDataURL(file);
      });
    }

    async function submitDeliveryProof() {
      const t = DRIVER_I18N[currentDriverLang] || DRIVER_I18N.te;
      const fileInput = document.getElementById('gate-photo-input');
      if (!fileInput.files || !fileInput.files[0]) {
        alert(t.photo_required_alert);
        return;
      }

      const btn = document.getElementById('btn-confirm-delivery');
      const btnText = document.getElementById('btn-confirm-delivery-text');
      btn.disabled = true;
      btnText.textContent = t.saving_proof_btn;

      try {
        const rawPhoto = fileInput.files[0];
        const compressedPhoto = await compressImageFile(rawPhoto);

        const formData = new FormData();
        formData.append('order_id', activeOrderId);
        formData.append('customer_id', activeCustomerId);
        formData.append('gate_photo', compressedPhoto);
        if (currentGeoLat && currentGeoLng) {
          formData.append('latitude', currentGeoLat);
          formData.append('longitude', currentGeoLng);
        }

        const resp = await fetch('api/verify-delivery.php', {
          method: 'POST',
          body: formData
        });
        const data = await resp.json();

        if (data.success) {
          alert(t.delivery_success_alert);
          window.location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to complete delivery'));
          btn.disabled = false;
          btnText.textContent = t.confirm_delivery_btn;
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btnText.textContent = t.confirm_delivery_btn;
      }
    }

    let activeQdOrderId = 0;
    let isQdCod = false;

    function openQuickDeliverModal(orderId, orderCode, customerName, address, isCod, totalAmount) {
      activeQdOrderId = orderId;
      isQdCod = isCod;

      document.getElementById('qd-order-code').textContent = '#' + orderCode;
      document.getElementById('qd-customer-name').textContent = customerName;
      document.getElementById('qd-address').textContent = address;

      const codBox = document.getElementById('qd-cod-box');
      const upiBox = document.getElementById('qd-upi-box');
      const confirmBtn = document.getElementById('btn-qd-confirm');
      const codCheckbox = document.getElementById('qd-cod-checkbox');

      if (isCod) {
        codBox.classList.remove('hidden');
        upiBox.classList.add('hidden');
        document.getElementById('qd-cod-amount').textContent = '₹' + Number(totalAmount).toFixed(2);
        codCheckbox.checked = false;
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
      } else {
        codBox.classList.add('hidden');
        upiBox.classList.remove('hidden');
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
      }

      document.getElementById('quick-deliver-modal').classList.remove('hidden');
    }

    function toggleQuickDeliverSubmit() {
      const confirmBtn = document.getElementById('btn-qd-confirm');
      const codCheckbox = document.getElementById('qd-cod-checkbox');
      if (isQdCod) {
        confirmBtn.disabled = !codCheckbox.checked;
        if (codCheckbox.checked) {
          confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
          confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
      }
    }

    function closeQuickDeliverModal() {
      document.getElementById('quick-deliver-modal').classList.add('hidden');
      activeQdOrderId = 0;
    }

    async function executeQuickDeliver() {
      if (isQdCod) {
        const codCheckbox = document.getElementById('qd-cod-checkbox');
        if (!codCheckbox.checked) {
          alert('Please verify cash collection by checking the confirmation box.');
          return;
        }
      }

      const btn = document.getElementById('btn-qd-confirm');
      const btnText = document.getElementById('btn-qd-confirm-text');
      btn.disabled = true;
      btnText.textContent = (currentDriverLang === 'te' ? 'నమోదు చేస్తోంది...' : 'Marking Delivered...');

      try {
        const resp = await fetch('api/verify-delivery.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'quick_deliver',
            order_id: activeQdOrderId
          })
        });
        const data = await resp.json();

        if (data.success) {
          const t = DRIVER_I18N[currentDriverLang] || DRIVER_I18N.te;
          alert(t.delivery_success_alert || 'Delivery verified and stop completed successfully!');
          window.location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to update order'));
          btn.disabled = false;
          btnText.textContent = 'Confirm Delivery';
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btnText.textContent = 'Confirm Delivery';
      }
    }

    document.getElementById('driver-lang-te')?.addEventListener('click', () => setDriverLanguage('te'));
    document.getElementById('driver-lang-en')?.addEventListener('click', () => setDriverLanguage('en'));

    setDriverLanguage(currentDriverLang);

    // --------------------------------------------------------------------------
    // Driver PWA Installation Logic
    // --------------------------------------------------------------------------
    let deferredDriverPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredDriverPrompt = e;
    });

    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
      document.getElementById('btn-pwa-install-driver')?.classList.add('hidden');
    }

    function triggerDriverPwaInstall() {
      if (deferredDriverPrompt) {
        deferredDriverPrompt.prompt();
        deferredDriverPrompt.userChoice.then((choice) => {
          if (choice.outcome === 'accepted') {
            document.getElementById('btn-pwa-install-driver')?.classList.add('hidden');
          }
          deferredDriverPrompt = null;
        });
      } else {
        const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIos) {
          alert('To install PS Driver on your iPhone/iPad:\n\n1. Tap the Share button 📤 in Safari.\n2. Scroll down & tap "Add to Home Screen" ➕.');
        } else {
          alert('To install PS Driver:\n\nIn Chrome/Edge menu (⋮), select "Install App" or "Add to Home Screen".');
        }
      }
    }
  </script>
</body>
</html>
