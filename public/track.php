<?php
declare(strict_types=1);

/**
 * Prakruthi Siri - Zero-Cost Live Order Tracker
 * CDCApp Standard: 4-Step Vertical Progress Timeline, Zero External API Dependencies
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/ConfigService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\ConfigService;

$pdo = Database::getInstance()->getConnection();
$configService = new ConfigService($pdo);
$storeWhatsApp = $configService->getStoreWhatsAppNumber();

$orderCode = strtoupper(trim(str_replace('#', '', (string)($_GET['code'] ?? ''))));
$phoneNumber = trim((string)($_GET['phone'] ?? ''));
$cleanPhone = substr(preg_replace('/\D/', '', $phoneNumber), -10);

$order = null;
$orderItems = [];
$error = null;

if ($orderCode !== '') {
    if (strlen($cleanPhone) < 10) {
        $error = 'Please enter your registered 10-digit mobile number along with the order code for verification.';
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                o.`id`, o.`order_code`, o.`order_status`, o.`payment_method`, o.`payment_status`,
                o.`subtotal`, o.`delivery_fee`, o.`total_amount`, o.`created_at`, o.`delivered_at`,
                o.`target_delivery_date`,
                c.`full_name`, c.`phone_number`, c.`delivery_address`, c.`landmark`, c.`region`,
                s.`delivery_day`, s.`delivery_date` AS `schedule_date`, s.`target_region`
            FROM `orders` o
            JOIN `customers` c ON o.`customer_id` = c.`id`
            LEFT JOIN `delivery_schedules` s ON o.`schedule_id` = s.`id`
            WHERE UPPER(TRIM(REPLACE(o.`order_code`, '#', ''))) = :code
              AND RIGHT(REPLACE(REPLACE(c.`phone_number`, '+91', ''), ' ', ''), 10) = :phone
            LIMIT 1
        ");
        $stmt->execute([':code' => $orderCode, ':phone' => $cleanPhone]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $itemStmt = $pdo->prepare("
                SELECT oi.`half_kg_quantity`, oi.`unit_price_applied`, oi.`line_total`,
                       p.`name` AS `product_name`, p.`telugu_name`
                FROM `order_items` oi
                JOIN `products` p ON oi.`product_id` = p.`id`
                WHERE oi.`order_id` = :oid
                ORDER BY p.`name` ASC
            ");
            $itemStmt->execute([':oid' => $order['id']]);
            $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $error = 'Order not found. Please verify both your order code (e.g. PS-1031) and registered 10-digit mobile number.';
        }
    }
}

// Determine 4-step timeline state
// 1 = placed, 2 = packed, 3 = out_for_delivery, 4 = delivered
$statusStep = 1;
if ($order) {
    switch ($order['order_status']) {
        case 'placed':
            $statusStep = 1;
            break;
        case 'packed':
            $statusStep = 2;
            break;
        case 'out_for_delivery':
            $statusStep = 3;
            break;
        case 'delivered':
            $statusStep = 4;
            break;
        case 'cancelled':
            $statusStep = 0;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="te" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Live Order Tracking | Prakruthi Siri</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body class="min-h-full flex flex-col antialiased text-slate-900 bg-slate-50 pb-20">

  <!-- Header -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
    <div class="max-w-xl mx-auto px-4 h-16 flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold text-xl shadow-xs">
          🌱
        </div>
        <div>
          <span class="font-extrabold text-base text-slate-900 tracking-tight leading-none block">Prakruthi Siri</span>
          <span class="text-xs text-slate-500 font-medium block mt-0.5" id="tracker-tagline"></span>
        </div>
      </a>
      <div class="flex items-center gap-3">
        <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 select-none text-xs font-bold">
          <button type="button" id="track-lang-te" class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs">తెలుగు</button>
          <button type="button" id="track-lang-en" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition">English</button>
        </div>
        <a href="index.php" id="tracker-store-link" class="text-xs font-bold text-emerald-700 hover:underline"></a>
      </div>
    </div>
  </header>

  <main class="flex-1 max-w-xl w-full mx-auto px-4 py-5 space-y-4">
    <!-- Order Lookup Bar (Dual Verification: Order Code + Mobile Number) -->
    <div class="app-card space-y-2.5">
      <form method="GET" action="track.php" class="space-y-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div class="relative">
            <input 
              type="text" 
              name="code" 
              value="<?= htmlspecialchars($orderCode, ENT_QUOTES) ?>" 
              id="track-search-input"
              required 
              class="compact-input w-full font-mono uppercase text-sm font-bold pl-8 py-2.5"
            >
            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">📦</span>
          </div>
          <div class="relative">
            <input 
              type="tel" 
              name="phone" 
              maxlength="10"
              pattern="[0-9]{10}"
              value="<?= htmlspecialchars($cleanPhone, ENT_QUOTES) ?>" 
              id="track-phone-input"
              required 
              class="compact-input w-full font-mono text-sm font-bold pl-8 py-2.5"
            >
            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">📞</span>
          </div>
        </div>
        <button type="submit" class="btn btn-primary text-xs h-10 w-full font-bold flex items-center justify-center gap-1.5" id="track-submit-btn">
          <span>🔍</span>
          <span id="track-submit-text">Track Order</span>
        </button>
      </form>
      <?php if (!empty($error)): ?>
        <p class="text-xs font-semibold text-rose-600 bg-rose-50 p-2.5 rounded-xl border border-rose-200"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($order): ?>
      <?php
        $isCod = (strtoupper($order['payment_method']) === 'COD');
        $batchTitle = (!empty($order['delivery_day']) ? $order['delivery_day'] . ' Batch' : 'Batch') . ' — ' . date('d M Y', strtotime($order['target_delivery_date']));
      ?>

      <!-- Order Summary Card -->
      <div class="app-card space-y-3">
        <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3">
          <div>
            <span class="card-badge badge-emerald font-mono text-xs">
              #<?= htmlspecialchars($order['order_code'], ENT_QUOTES) ?>
            </span>
            <div class="text-xs text-slate-500 font-medium mt-1">
              <span id="lbl-placed-at"></span> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
            </div>
          </div>
          <div class="text-right">
            <span class="badge-status badge-status-<?= htmlspecialchars($order['order_status'], ENT_QUOTES) ?>">
              <?= strtoupper(str_replace('_', ' ', $order['order_status'])) ?>
            </span>
            <div class="text-xs font-mono font-bold text-slate-900 mt-1">
              ₹<?= number_format((float)$order['total_amount'], 2) ?>
            </div>
          </div>
        </div>

        <div class="text-xs text-slate-700 space-y-1">
          <div><strong id="lbl-delivery-batch"></strong> <span class="text-emerald-800 font-bold"><?= htmlspecialchars($batchTitle, ENT_QUOTES) ?> (<?= htmlspecialchars($order['region'], ENT_QUOTES) ?>)</span></div>
          <?php
            $rawCustPhone = preg_replace('/\D/', '', (string)$order['phone_number']);
            $maskedCustPhone = (strlen($rawCustPhone) >= 4) ? str_repeat('•', max(0, strlen($rawCustPhone) - 4)) . substr($rawCustPhone, -4) : $order['phone_number'];
          ?>
          <div><strong id="lbl-recipient"></strong> <?= htmlspecialchars($order['full_name'], ENT_QUOTES) ?> (<?= htmlspecialchars($maskedCustPhone, ENT_QUOTES) ?>)</div>
          <div><strong id="lbl-address"></strong> <?= htmlspecialchars($order['delivery_address'], ENT_QUOTES) ?><?= !empty($order['landmark']) ? ' (' . htmlspecialchars($order['landmark'], ENT_QUOTES) . ')' : '' ?></div>
        </div>
      </div>

      <!-- 4-Step Vertical Progress Timeline -->
      <div class="app-card space-y-4">
        <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2" id="lbl-progress-title"></h3>

        <?php if ($order['order_status'] === 'cancelled'): ?>
          <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-2">
            <span>❌</span>
            <span id="lbl-cancelled-msg"></span>
          </div>
        <?php else: ?>
          <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
            
            <!-- Step 1: Order Received -->
            <div class="relative flex items-start gap-3">
              <div class="absolute -left-6 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold <?= $statusStep >= 1 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200 text-slate-500' ?>">
                <?= $statusStep >= 1 ? '✓' : '1' ?>
              </div>
              <div>
                <div class="text-xs font-bold <?= $statusStep >= 1 ? 'text-slate-900' : 'text-slate-400' ?>" id="track-step1-title"></div>
                <div class="text-[11px] text-slate-500" id="track-step1-desc"></div>
              </div>
            </div>

            <!-- Step 2: Harvested & Packed -->
            <div class="relative flex items-start gap-3">
              <div class="absolute -left-6 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold <?= $statusStep >= 2 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200 text-slate-500' ?>">
                <?= $statusStep >= 2 ? '✓' : '2' ?>
              </div>
              <div>
                <div class="text-xs font-bold <?= $statusStep >= 2 ? 'text-slate-900' : 'text-slate-400' ?>" id="track-step2-title"></div>
                <div class="text-[11px] text-slate-500" id="track-step2-desc"></div>
              </div>
            </div>

            <!-- Step 3: Out for Delivery -->
            <div class="relative flex items-start gap-3">
              <div class="absolute -left-6 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold <?= $statusStep >= 3 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200 text-slate-500' ?>">
                <?= $statusStep >= 3 ? '✓' : '3' ?>
              </div>
              <div>
                <div class="text-xs font-bold <?= $statusStep >= 3 ? 'text-slate-900' : 'text-slate-400' ?>" id="track-step3-title"></div>
                <div class="text-[11px] text-slate-500" id="track-step3-desc"></div>
              </div>
            </div>

            <!-- Step 4: Delivered -->
            <div class="relative flex items-start gap-3">
              <div class="absolute -left-6 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold <?= $statusStep >= 4 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200 text-slate-500' ?>">
                <?= $statusStep >= 4 ? '✓' : '4' ?>
              </div>
              <div>
                <div class="text-xs font-bold <?= $statusStep >= 4 ? 'text-emerald-700' : 'text-slate-400' ?>" id="track-step4-title"></div>
                <div class="text-[11px] text-slate-500" id="track-step4-desc">
                  <?= !empty($order['delivered_at']) ? date('d M, h:i A', strtotime($order['delivered_at'])) : '' ?>
                </div>
              </div>
            </div>

          </div>
        <?php endif; ?>
      </div>

      <!-- Itemized Vegetables Summary -->
      <div class="app-card space-y-3">
        <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2" id="lbl-items-heading"></h3>
        <div class="divide-y divide-slate-100 text-xs">
          <?php foreach ($orderItems as $it): ?>
            <?php 
              $weightKg = (int)$it['half_kg_quantity'] * 0.5; 
              $teluguItemName = $it['telugu_name'] ?? '';
              $englishItemName = $it['product_name'];
            ?>
            <div class="py-2 flex items-center justify-between">
              <div>
                <div class="font-bold text-slate-900 tracker-item-name" data-telugu="<?= htmlspecialchars($teluguItemName, ENT_QUOTES) ?>" data-english="<?= htmlspecialchars($englishItemName, ENT_QUOTES) ?>"><?= htmlspecialchars($teluguItemName ?: $englishItemName, ENT_QUOTES) ?></div>
                <div class="text-[11px] text-slate-500 tracker-item-qty" data-pkts="<?= (int)$it['half_kg_quantity'] ?>" data-kg="<?= number_format($weightKg, 1) ?>"></div>
              </div>
              <div class="font-mono font-bold text-slate-800">
                ₹<?= number_format((float)$it['line_total'], 2) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="pt-2 border-t border-slate-100 space-y-1 text-xs text-slate-600">
          <div class="flex justify-between">
            <span id="lbl-tracker-subtotal"></span>
            <span class="font-mono font-semibold">₹<?= number_format((float)$order['subtotal'], 2) ?></span>
          </div>
          <div class="flex justify-between">
            <span id="lbl-tracker-delivery-fee"></span>
            <span class="font-mono font-semibold">₹<?= number_format((float)$order['delivery_fee'], 2) ?></span>
          </div>
          <div class="flex justify-between text-sm font-extrabold text-slate-900 pt-1 border-t border-slate-100">
            <span id="lbl-tracker-total"></span>
            <span class="font-mono text-emerald-700">₹<?= number_format((float)$order['total_amount'], 2) ?></span>
          </div>
          <div class="text-[11px] text-slate-500 text-right">
            <span id="lbl-tracker-payment"></span> <strong><?= htmlspecialchars($order['payment_method'], ENT_QUOTES) ?></strong> (<?= htmlspecialchars($order['payment_status'], ENT_QUOTES) ?>)
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col sm:flex-row gap-2.5">
        <a href="index.php" class="btn btn-primary flex-1 btn-large text-xs font-bold text-center" id="btn-order-more"></a>
        <a 
          href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $storeWhatsApp) ?>?text=<?= urlencode('Hello Prakruthi Siri, regarding my order #' . $order['order_code'] . ': ') ?>" 
          target="_blank" 
          class="btn btn-secondary flex-1 btn-large text-xs font-bold text-center text-emerald-800 border-emerald-200"
          id="btn-wa-support"
        ></a>
      </div>

    <?php elseif (empty($error)): ?>
      <div class="app-card text-center py-12 text-slate-400">
        <div class="text-3xl mb-2">📦</div>
        <p class="text-sm font-semibold text-slate-700" id="tracker-empty-title"></p>
        <p class="text-xs text-slate-500 mt-1" id="tracker-empty-desc"></p>
      </div>
    <?php endif; ?>

  </main>

  <script src="assets/js/i18n-customer.js"></script>
  <script>
    (() => {
      let currentLang = localStorage.getItem('ps_customer_lang') || 'te';

      function setTrackLanguage(lang) {
        currentLang = lang;
        localStorage.setItem('ps_customer_lang', lang);
        if (typeof CUSTOMER_I18N === 'undefined') return;

        const t = CUSTOMER_I18N[lang] || CUSTOMER_I18N.te;
        const isTe = (lang === 'te');

        // Language toggle buttons
        const btnTe = document.getElementById('track-lang-te');
        const btnEn = document.getElementById('track-lang-en');
        if (btnTe && btnEn) {
          if (isTe) {
            btnTe.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
            btnEn.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
          } else {
            btnEn.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
            btnTe.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
          }
        }

        const set = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        const setAttr = (id, attr, val) => { const e = document.getElementById(id); if (e) e.setAttribute(attr, val); };

        // Header
        set('tracker-tagline', t.tracker_tagline);
        set('tracker-store-link', t.tracker_store_link);

        // Search form
        setAttr('track-search-input', 'placeholder', t.tracker_search_placeholder);
        setAttr('track-phone-input', 'placeholder', t.tracker_phone_placeholder || '10-digit mobile number...');
        set('track-submit-text', t.tracker_track_btn);

        // Order summary labels
        set('lbl-placed-at', t.tracker_placed_at);
        set('lbl-delivery-batch', t.tracker_delivery_batch);
        set('lbl-recipient', t.tracker_recipient);
        set('lbl-address', t.tracker_address);

        // Timeline
        set('lbl-progress-title', t.tracker_progress_title);
        set('lbl-cancelled-msg', t.tracker_cancelled_msg);
        set('track-step1-title', t.tracker_step1_title);
        set('track-step1-desc', t.tracker_step1_desc);
        set('track-step2-title', t.tracker_step2_title);
        set('track-step2-desc', t.tracker_step2_desc);
        set('track-step3-title', t.tracker_step3_title);
        set('track-step3-desc', t.tracker_step3_desc);
        set('track-step4-title', t.tracker_step4_title);
        // Step 4 desc is either delivered date (static) or default desc — only set if empty
        const step4Desc = document.getElementById('track-step4-desc');
        if (step4Desc && !step4Desc.textContent.trim()) {
          step4Desc.textContent = t.tracker_step4_desc;
        }

        // Items heading
        set('lbl-items-heading', t.tracker_items_heading);

        // Item names — switch between Telugu/English
        document.querySelectorAll('.tracker-item-name').forEach(el => {
          const teluguName = el.dataset.telugu || '';
          const englishName = el.dataset.english || '';
          el.textContent = isTe ? (teluguName || englishName) : englishName;
        });

        // Item quantities
        document.querySelectorAll('.tracker-item-qty').forEach(el => {
          const pkts = parseInt(el.dataset.pkts, 10);
          const kg = el.dataset.kg;
          el.textContent = t.tracker_item_qty(pkts, kg);
        });

        // Bill labels
        set('lbl-tracker-subtotal', t.tracker_subtotal);
        set('lbl-tracker-delivery-fee', t.tracker_delivery_fee);
        set('lbl-tracker-total', t.tracker_grand_total);
        set('lbl-tracker-payment', t.tracker_payment_label);

        // Action buttons
        set('btn-order-more', t.btn_order_more);
        set('btn-wa-support', t.btn_whatsapp_support);

        // Empty state
        set('tracker-empty-title', t.tracker_empty_state_title);
        set('tracker-empty-desc', t.tracker_empty_state_desc);
      }

      document.getElementById('track-lang-te')?.addEventListener('click', () => setTrackLanguage('te'));
      document.getElementById('track-lang-en')?.addEventListener('click', () => setTrackLanguage('en'));

      setTrackLanguage(currentLang);
    })();
  </script>
</body>
</html>
