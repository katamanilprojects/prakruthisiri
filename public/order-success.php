<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Minimal Mobile Order Confirmation Receipt
 * Chemical-Free Organic Vegetables (Warangal, Hanamkonda)
 */

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../src/ConfigService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\ConfigService;

$orderCode = trim((string) ($_GET['code'] ?? ''));
$order = null;
$items = [];
$errorMessage = null;

$db = Database::getInstance();
$pdo = $db->getConnection();
$configService = new ConfigService($pdo);
$storeWhatsApp = $configService->getStoreWhatsAppNumber();

if ($orderCode !== '') {
    try {
        // 1. Fetch Order & Customer Details
        $orderStmt = $pdo->prepare('
            SELECT 
                o.`id`,
                o.`order_code`,
                o.`customer_id`,
                o.`subtotal`,
                o.`delivery_fee`,
                o.`total_amount`,
                o.`target_delivery_date`,
                o.`order_status`,
                o.`payment_method`,
                o.`created_at`,
                c.`full_name` AS `customer_name`,
                c.`phone_number` AS `customer_phone`,
                c.`delivery_address`,
                c.`region`
            FROM `orders` o
            INNER JOIN `customers` c ON o.`customer_id` = c.`id`
            WHERE UPPER(TRIM(REPLACE(o.`order_code`, "#", ""))) = UPPER(TRIM(REPLACE(:order_code, "#", "")))
            LIMIT 1
        ');
        $orderStmt->execute([':order_code' => $orderCode]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // 2. Fetch Line Items
            $itemStmt = $pdo->prepare('
                SELECT 
                    oi.`id`,
                    oi.`half_kg_quantity`,
                    oi.`unit_price_applied`,
                    oi.`line_total`,
                    p.`name` AS `product_name`,
                    p.`telugu_name`
                FROM `order_items` oi
                INNER JOIN `products` p ON oi.`product_id` = p.`id`
                WHERE oi.`order_id` = :order_id
                ORDER BY oi.`id` ASC
            ');
            $itemStmt->execute([':order_id' => $order['id']]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errorMessage = "No order found matching: " . htmlspecialchars($orderCode, ENT_QUOTES);
        }
    } catch (\Throwable $e) {
        $errorMessage = "Unable to load order details: " . $e->getMessage();
    }
} else {
    $errorMessage = "Missing order code in request.";
}

// Format Delivery Date
$deliveryDateStr = 'Tomorrow Morning (6:30 AM – 10:30 AM)';
if ($order && !empty($order['target_delivery_date'])) {
    $ts = strtotime((string) $order['target_delivery_date']);
    if ($ts) {
        $deliveryDateStr = date('l, F j, Y', $ts) . ' (6:30 AM – 10:30 AM)';
    }
}

// WhatsApp Deep Link Message with item breakdown and tracking link
$host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']);
$trackingPhone = substr(preg_replace('/\D/', '', (string)($order['customer_phone'] ?? '')), -10);
$trackingUrl = rtrim($host, '/') . "/track.php?code=" . urlencode($orderCode) . ($trackingPhone !== '' ? "&phone=" . urlencode($trackingPhone) : '');

$itemLines = [];
foreach ($items as $it) {
    $itemLines[] = "• " . $it['product_name'] . " (" . ((int)$it['half_kg_quantity'] * 0.5) . " kg) - ₹" . number_format((float)$it['line_total'], 0);
}
$itemsSummary = !empty($itemLines) ? implode("\n", $itemLines) : "Fresh Vegetables";

$hubWhatsApp = preg_replace('/\D/', '', $storeWhatsApp ?: '919393767927');
if (strlen($hubWhatsApp) === 10) {
    $hubWhatsApp = '91' . $hubWhatsApp;
}

$messageText = "🌱 *Prakruthi Siri - New Vegetable Order*\n\n"
             . "• *Order Code:* #{$orderCode}\n"
             . "• *Customer:* " . ($order['customer_name'] ?? 'Customer') . " (" . ($order['customer_phone'] ?? '') . ")\n"
             . "• *Delivery Address:* " . ($order['delivery_address'] ?? '') . ", " . ($order['region'] ?? '') . "\n"
             . "• *Payment:* ₹" . number_format((float)($order['total_amount'] ?? 0), 2) . " (" . ($order['payment_method'] ?? 'COD') . ")\n"
             . "• *Target Delivery:* {$deliveryDateStr}\n\n"
             . "*Harvest Items:*\n{$itemsSummary}\n\n"
             . "📍 *Live Order Tracking:* {$trackingUrl}\n\n"
             . "Please confirm my fresh vegetable delivery run.";

$whatsappUrl = "https://wa.me/" . $hubWhatsApp . "?text=" . rawurlencode($messageText);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Order Placed Successfully | Prakruthi Siri</title>
  
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#059669">
  <link rel="icon" type="image/png" href="assets/icons/icon.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body class="h-full font-sans text-slate-900 bg-slate-50 flex flex-col antialiased">

  <!-- Header -->
  <header class="bg-white border-b border-slate-200 py-3.5 px-4 shadow-xs">
    <div class="max-w-md mx-auto flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold text-base">🌱</span>
        <span class="font-extrabold text-base tracking-tight text-slate-900">Prakruthi Siri</span>
      </div>
      <div class="flex items-center gap-3">
        <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 select-none text-xs font-bold">
          <button type="button" id="success-lang-te" class="px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition">తెలుగు</button>
          <button type="button" id="success-lang-en" class="px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 transition">English</button>
        </div>
        <a href="index.php" id="link-home" class="text-xs font-bold text-emerald-700 hover:underline transition"></a>
      </div>
    </div>
  </header>

  <!-- Main Receipt Container -->
  <main class="flex-1 max-w-md w-full mx-auto px-4 py-6 space-y-4">
    <?php if ($errorMessage): ?>
      <div class="bg-white rounded-3xl p-6 shadow-sm border border-rose-200 text-center space-y-3">
        <div class="w-14 h-14 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto text-2xl">
          ⚠️
        </div>
        <h2 class="text-lg font-extrabold text-stone-900">Order Not Found</h2>
        <p class="text-xs text-stone-500"><?= htmlspecialchars($errorMessage, ENT_QUOTES) ?></p>
        <a href="index.php" class="btn btn-primary text-xs px-6">
          Return to Storefront
        </a>
      </div>
    <?php else: ?>

      <!-- 1. Success Hero Banner -->
      <div class="app-card text-center space-y-2.5">
        <div class="w-16 h-16 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-2xl flex items-center justify-center mx-auto text-3xl font-bold shadow-xs">
          ✓
        </div>
        <h2 id="receipt-title" class="text-xl font-extrabold text-slate-900 tracking-tight"></h2>
        <p id="receipt-subtitle" class="text-xs text-slate-500 font-medium"></p>

        <!-- Target Delivery Time Banner -->
        <div class="mt-3 py-2.5 px-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-700">
          <span id="receipt-delivery-label" class="text-slate-500 block text-xs font-semibold"></span>
          <strong class="text-emerald-700 font-extrabold text-sm"><?= htmlspecialchars($deliveryDateStr, ENT_QUOTES) ?></strong>
        </div>
      </div>

      <!-- 2. Order Summary Card -->
      <div class="app-card space-y-3 text-xs">
        <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
          <div>
            <span id="receipt-order-ref-label" class="text-slate-400 block text-xs font-bold uppercase"></span>
            <strong class="font-mono text-sm text-emerald-700 font-extrabold"><?= htmlspecialchars($order['order_code'], ENT_QUOTES) ?></strong>
          </div>
          <div class="text-right">
            <span id="receipt-payment-mode-label" class="text-slate-400 block text-xs font-bold uppercase"></span>
            <span id="receipt-payment-val" class="card-badge <?= $order['payment_method'] === 'UPI' ? 'badge-emerald' : 'badge-amber' ?>" data-paymethod="<?= htmlspecialchars($order['payment_method'], ENT_QUOTES) ?>"></span>
          </div>
        </div>

        <div>
          <span id="receipt-delivering-to-label" class="text-slate-400 block text-xs font-bold uppercase"></span>
          <strong class="text-slate-900 font-bold text-xs"><?= htmlspecialchars($order['customer_name'], ENT_QUOTES) ?></strong>
          <p class="text-slate-600 mt-0.5"><?= htmlspecialchars($order['delivery_address'], ENT_QUOTES) ?>, <?= htmlspecialchars($order['region'], ENT_QUOTES) ?></p>
        </div>
      </div>

      <!-- 3. Itemized Harvest Receipt -->
      <div class="app-card space-y-3 text-xs">
        <div id="receipt-basket-heading" class="font-bold text-slate-700 uppercase tracking-wider text-xs"></div>

        <div class="space-y-2 divide-y divide-slate-100">
          <?php foreach ($items as $it): ?>
            <?php 
              $pkts = (int) $it['half_kg_quantity'];
              $kg = $pkts * 0.5;
              // Use telugu_name if present, fall back to product_name
              $teluguName = $it['telugu_name'] ?? '';
              $englishName = $it['product_name'];
            ?>
            <div class="pt-2 first:pt-0 flex items-center justify-between">
              <div>
                <div class="font-extrabold text-slate-900 text-sm receipt-item-name" data-telugu="<?= htmlspecialchars($teluguName, ENT_QUOTES) ?>" data-english="<?= htmlspecialchars($englishName, ENT_QUOTES) ?>"><?= htmlspecialchars($teluguName ?: $englishName, ENT_QUOTES) ?></div>
                <div class="text-xs text-slate-500 font-semibold receipt-item-qty" data-pkts="<?= $pkts ?>" data-kg="<?= number_format($kg, 1) ?>"></div>
              </div>
              <div class="font-extrabold text-slate-900 text-sm font-mono">
                ₹<?= number_format((float) $it['line_total'], 0) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Total Bill -->
        <div class="border-t border-slate-200 pt-3 space-y-1">
          <div class="flex justify-between text-slate-600">
            <span id="receipt-subtotal-label"></span>
            <span class="font-mono">₹<?= number_format((float) $order['subtotal'], 0) ?></span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span id="receipt-delivery-fee-label"></span>
            <span id="receipt-delivery-fee-val" class="font-bold text-emerald-700 font-mono" data-fee="<?= (float)$order['delivery_fee'] ?>"></span>
          </div>
          <div class="flex justify-between text-base font-extrabold text-slate-900 pt-1 border-t border-slate-100 font-mono">
            <span id="receipt-total-label" class="font-sans"></span>
            <span class="text-lg text-emerald-700">₹<?= number_format((float) $order['total_amount'], 0) ?></span>
          </div>
        </div>
      </div>

      <!-- 4. Prominent WhatsApp Share Button -->
      <a 
        href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES) ?>" 
        target="_blank" 
        rel="noopener noreferrer"
        class="btn btn-primary btn-large btn-full shadow-md text-sm"
      >
        <span id="btn-whatsapp-text"></span>
      </a>

      <!-- 5. Live Order Tracking Button -->
      <a 
        href="track.php?code=<?= urlencode($orderCode) ?><?= !empty($trackingPhone) ? '&phone=' . urlencode($trackingPhone) : '' ?>" 
        class="btn btn-secondary btn-large btn-full text-xs font-bold text-emerald-800 border-emerald-200"
      >
        <span id="btn-track-order-text"></span>
      </a>

      <!-- 6. Shop More Button -->
      <a 
        href="index.php" 
        class="btn btn-secondary btn-large btn-full text-xs"
      >
        <span id="btn-shop-more-text"></span>
      </a>

    <?php endif; ?>
  </main>

  <script src="assets/js/i18n-customer.js"></script>
  <script>
    (() => {
      let currentLang = localStorage.getItem('ps_customer_lang') || 'te';

      function setSuccessLanguage(lang) {
        currentLang = lang;
        localStorage.setItem('ps_customer_lang', lang);
        if (typeof CUSTOMER_I18N === 'undefined') return;

        const t = CUSTOMER_I18N[lang] || CUSTOMER_I18N.te;
        const isTe = (lang === 'te');
        const btnTe = document.getElementById('success-lang-te');
        const btnEn = document.getElementById('success-lang-en');

        // Fix button active states (was inverted in original code)
        if (btnTe && btnEn) {
          if (isTe) {
            btnTe.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition';
            btnEn.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 transition';
          } else {
            btnEn.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition';
            btnTe.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 transition';
          }
        }

        const set = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };

        set('link-home', t.receipt_home_link);
        set('receipt-title', t.receipt_title);
        set('receipt-subtitle', t.receipt_subtitle);
        set('receipt-delivery-label', t.receipt_delivery_label);
        set('receipt-order-ref-label', t.receipt_order_ref);
        set('receipt-payment-mode-label', t.receipt_payment_mode);

        // Payment value — uses data-paymethod attribute
        const payVal = document.getElementById('receipt-payment-val');
        if (payVal) {
          const isUpi = payVal.dataset.paymethod === 'UPI';
          payVal.textContent = isUpi ? t.receipt_paid_upi : t.receipt_paid_cod;
        }

        set('receipt-delivering-to-label', t.receipt_delivering_to);
        set('receipt-basket-heading', t.receipt_basket_heading);

        // Item names — switch between Telugu and English using data attributes
        document.querySelectorAll('.receipt-item-name').forEach(el => {
          const teluguName = el.dataset.telugu || '';
          const englishName = el.dataset.english || '';
          el.textContent = isTe ? (teluguName || englishName) : englishName;
        });

        // Item quantities — use i18n packet unit function
        document.querySelectorAll('.receipt-item-qty').forEach(el => {
          const pkts = parseInt(el.dataset.pkts, 10);
          const kg = el.dataset.kg;
          el.textContent = t.receipt_packet_unit(pkts, kg);
        });

        set('receipt-subtotal-label', t.receipt_subtotal);
        set('receipt-delivery-fee-label', t.receipt_delivery_fee);

        // Delivery fee value
        const feeVal = document.getElementById('receipt-delivery-fee-val');
        if (feeVal) {
          const fee = parseFloat(feeVal.dataset.fee || '0');
          feeVal.textContent = fee === 0 ? t.receipt_fee_free : `₹${fee.toFixed(0)}`;
        }

        set('receipt-total-label', t.receipt_grand_total);
        set('btn-whatsapp-text', t.btn_send_whatsapp);
        set('btn-track-order-text', t.btn_track_order);
        set('btn-shop-more-text', t.btn_shop_more);
      }

      document.getElementById('success-lang-te')?.addEventListener('click', () => setSuccessLanguage('te'));
      document.getElementById('success-lang-en')?.addEventListener('click', () => setSuccessLanguage('en'));

      setSuccessLanguage(currentLang);

      // Automated WhatsApp redirection if auto_wa=1 is in URL parameters
      try {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_wa') === '1') {
          const waUrl = <?= json_encode($whatsappUrl) ?>;
          if (waUrl) {
            setTimeout(() => {
              window.location.href = waUrl;
            }, 600);
          }
        }
      } catch (e) {
        console.warn('Auto WhatsApp redirect skipped:', e);
      }
    })();
  </script>
</body>
</html>
