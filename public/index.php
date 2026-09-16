<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/TimeWindow.php';
require_once __DIR__ . '/../src/ConfigService.php';
require_once __DIR__ . '/../src/RunInventoryService.php';
require_once __DIR__ . '/../src/GeoFenceService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\TimeWindow;
use PrakruthiSiri\ConfigService;
use PrakruthiSiri\RunInventoryService;

$pdo = Database::getInstance()->getConnection();

$configService = new ConfigService($pdo);
$runInventoryService = new RunInventoryService($pdo);
$now = TimeWindow::now();

// Pre-fetch active/upcoming batches for Hanamkonda and Warangal
$regions = ['Hanamkonda', 'Warangal'];
$regionSchedules = [];
$defaultRegion = 'Hanamkonda';

foreach ($regions as $reg) {
    $sched = $runInventoryService->getActiveScheduleForRegion($reg, $now);
    $status = 'OPEN';
    if (!$sched) {
        $sched = $runInventoryService->getNextUpcomingScheduleForRegion($reg, $now);
        $status = 'UPCOMING';
    }
    if ($sched) {
        $bookedCount = $runInventoryService->getBookedOrdersCount((int)$sched['id']);
        $sched['status_mode'] = $status;
        $sched['booked_orders_count'] = $bookedCount;
        $sched['max_orders_limit'] = 30;
        $sched['is_batch_full'] = ($bookedCount >= 30);
        $sched['cutoff_fmt'] = !empty($sched['cutoff_datetime']) ? date('D, d M - h:i A', strtotime($sched['cutoff_datetime'])) : '';
        $sched['delivery_fmt'] = !empty($sched['delivery_date']) ? date('l, d M Y', strtotime($sched['delivery_date'])) : '';
    }
    $regionSchedules[$reg] = $sched;
}

$activeSchedule = $regionSchedules[$defaultRegion] ?? null;
$initialCatalog = [];
if ($activeSchedule) {
    $initialCatalog = $runInventoryService->getRunCatalog((int)$activeSchedule['id'], ($activeSchedule['status_mode'] ?? '') === 'OPEN');
}

$movThreshold  = $configService->getMovThreshold();
$deliveryFee   = $configService->getStandardDeliveryFee();
$storeWhatsApp = $configService->getStoreWhatsAppNumber();
?>
<!DOCTYPE html>
<html lang="te" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  
  <title>Prakruthi Siri | Farm-Fresh Organic Vegetables in Hanamkonda &amp; Warangal (ప్రకృతి సిరి)</title>
  <meta name="description" content="Chemical-free, farm-fresh organic vegetables harvested daily and delivered across Hanamkonda and Warangal. తాజా సేంద్రీయ కూరగాయలు మీ ఇంటి వద్దకే. Order online before 7 PM.">
  <link rel="canonical" href="https://prakruthisiri.com/">

  <!-- Open Graph / WhatsApp Link Previews -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Prakruthi Siri">
  <meta property="og:title" content="Prakruthi Siri | Farm-Fresh Organic Vegetables (Hanamkonda &amp; Warangal)">
  <meta property="og:description" content="Chemical-free organic vegetables delivered directly from farm to doorstep. Order before 7 PM for fresh harvest delivery.">
  <meta property="og:image" content="https://prakruthisiri.com/assets/icons/icon-512.png">
  <meta property="og:url" content="https://prakruthisiri.com/">

  <!-- Structured Data (Schema.org JSON-LD) -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "GroceryStore",
    "name": "Prakruthi Siri Organic Vegetables",
    "image": "https://prakruthisiri.com/assets/icons/icon-512.png",
    "telephone": "+919393767927",
    "url": "https://prakruthisiri.com",
    "priceRange": "₹",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "KU Cross Road, Naimnagar",
      "addressLocality": "Hanamkonda",
      "addressRegion": "Telangana",
      "postalCode": "506009",
      "addressCountry": "IN"
    },
    "geo": {
      "@type": "GeoCoordinates",
      "latitude": 18.028439,
      "longitude": 79.635941
    },
    "areaServed": [
      { "@type": "City", "name": "Hanamkonda" },
      { "@type": "City", "name": "Warangal" }
    ],
    "openingHoursSpecification": {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
      "opens": "05:00",
      "closes": "19:00"
    }
  }
  </script>

  <meta name="theme-color" content="#059669">
  <link rel="icon" type="image/png" href="assets/icons/icon.png">
  <link rel="manifest" href="manifest.json">

  <!-- Inter Typography & CDCApp Master Stylesheet -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/theme.css">
  
  <!-- Leaflet Maps CSS & JS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body class="min-h-full flex flex-col antialiased text-slate-900 bg-slate-50 pb-32">

  <!-- ====================================================================== -->
  <!-- HEADER & BILINGUAL TOGGLE                                             -->
  <!-- ====================================================================== -->
  <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200 shadow-xs">
    <div class="max-w-xl mx-auto px-4 h-16 flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold text-xl shadow-xs">
          🌱
        </div>
        <div>
          <span class="font-extrabold text-base text-slate-900 tracking-tight leading-none block" id="header-brand">Prakruthi Siri</span>
          <span class="text-xs text-slate-500 font-medium block mt-0.5" id="header-tagline"></span>
        </div>
      </a>

      <!-- Language Toggle Switcher -->
      <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 select-none text-xs font-bold">
        <button type="button" id="btn-lang-te" class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs">తెలుగు</button>
        <button type="button" id="btn-lang-en" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition">English</button>
      </div>
    </div>
  </header>

  <main class="flex-1 max-w-xl w-full mx-auto px-4 py-4 space-y-4">

    <!-- PWA Install Banner (Dismissible on Mobile) -->
    <div id="pwa-banner" class="bg-emerald-50 border border-emerald-200 text-emerald-950 p-3 rounded-2xl flex items-center justify-between text-xs shadow-xs gap-2">
      <div class="flex items-center gap-2 min-w-0">
        <span class="text-base shrink-0">📱</span>
        <span class="font-medium truncate" id="pwa-text"></span>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <button type="button" id="btn-pwa-install-public" onclick="triggerPublicPwaInstall()" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow-xs flex items-center gap-1 cursor-pointer">
          <span>📲</span>
          <span id="btn-pwa-install-text">Install</span>
        </button>
        <button type="button" onclick="dismissPwaBanner()" class="text-slate-400 hover:text-slate-700 font-bold text-lg px-1.5 leading-none cursor-pointer" aria-label="Dismiss">&times;</button>
      </div>
    </div>

    <!-- Pre-Rendered Semantic Catalog Fallback for Search Crawlers & No-JS -->
    <noscript>
      <div class="seo-catalog-fallback p-4 bg-white rounded-2xl border border-slate-200 shadow-xs space-y-3">
        <h2 class="text-base font-bold text-slate-900">Fresh Organic Vegetables Available for Delivery in Hanamkonda &amp; Warangal (తాజా సేంద్రీయ కూరగాయలు)</h2>
        <ul class="space-y-1.5 text-xs text-slate-700">
          <?php
          try {
              $seoStmt = $pdo->query("SELECT `name`, `telugu_name`, `price_per_half_kg` FROM `products` WHERE `is_active` = 1");
              while ($p = $seoStmt->fetch(PDO::FETCH_ASSOC)):
          ?>
            <li class="flex justify-between border-b border-slate-100 pb-1">
              <strong><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['telugu_name']) ?>)</strong>
              <span class="font-mono font-semibold text-emerald-700">₹<?= number_format((float)$p['price_per_half_kg'], 2) ?> per 0.5 kg packet</span>
            </li>
          <?php
              endwhile;
          } catch (\Throwable $e) {}
          ?>
        </ul>
      </div>
    </noscript>

    <!-- ==================================================================== -->
    <!-- PHONE-FIRST ONBOARDING CARD                                          -->
    <!-- Step 1: 10-digit mobile number -> Step 2: Auto-Profile or New Setup  -->
    <!-- ==================================================================== -->
    <section id="onboarding-section" class="space-y-3">
      
      <!-- Step 1: Mobile Input Card -->
      <div id="onboarding-step-1" class="app-card space-y-2.5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="card-badge badge-emerald" id="lbl-onboarding-badge"></span>
          </div>
          <span class="text-[11px] text-slate-400 font-mono">+91</span>
        </div>

        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center font-mono font-bold text-slate-400 text-sm">+91</span>
          <input 
            type="tel" 
            id="onboarding-phone" 
            maxlength="10" 
            pattern="[0-9]{10}" 
            placeholder="9876543210" 
            oninput="handlePhoneInput(this.value)" 
            class="app-input pl-12 font-mono font-bold text-base h-12"
          >
          <button 
            type="button" 
            id="btn-phone-continue" 
            onclick="checkPhoneManual()"
            class="absolute right-1.5 top-1.5 bottom-1.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs hidden"
          >
            <span id="btn-phone-continue-label"></span>
          </button>
        </div>
        <p class="text-[11px] text-slate-500 leading-normal" id="txt-onboarding-hint"></p>
      </div>

      <!-- Step 2A: Returning Customer Multi-Address Selector Card -->
      <div id="onboarding-returning-card" class="hidden app-card space-y-3.5 bg-emerald-50/60 border border-emerald-200">
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase font-extrabold tracking-wider text-emerald-800" id="returning-badge"></div>
            <h3 class="font-extrabold text-base text-slate-900 mt-0.5" id="returning-welcome-title"></h3>
            <span class="text-xs text-slate-500 font-mono font-bold" id="returning-phone-display">+91 ----------</span>
          </div>
          <button type="button" onclick="resetOnboardingPhone()" class="text-xs font-bold text-slate-500 hover:text-slate-800 underline" id="btn-change-mobile"></button>
        </div>

        <!-- Saved Addresses Radios -->
        <div class="space-y-2">
          <label class="block text-xs font-extrabold text-slate-700" id="lbl-select-addr"></label>
          <div id="saved-addresses-container" class="space-y-2">
            <!-- Dynamically injected address radio cards -->
          </div>
        </div>

        <!-- Inline Form to Add a New Address -->
        <div id="inline-new-addr-form" class="hidden p-3 bg-white rounded-xl border border-slate-200 space-y-2.5 text-xs">
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-800" id="inline-addr-form-title"></span>
            <button type="button" onclick="toggleNewAddressForm(false)" class="text-slate-400 hover:text-slate-700 text-base font-bold">&times;</button>
          </div>
          <div>
            <label class="block text-slate-600 font-semibold mb-1" id="lbl-inline-addr-label"></label>
            <input type="text" id="inline-addr-label" class="app-input h-9 text-xs" id="inline-addr-label-input">
          </div>
          <div>
            <label class="block text-slate-600 font-semibold mb-1" id="lbl-inline-addr-street"></label>
            <input type="text" id="inline-addr-street" class="app-input h-9 text-xs">
          </div>
          <div>
            <label class="block text-slate-600 font-semibold mb-1" id="lbl-inline-addr-landmark"></label>
            <input type="text" id="inline-addr-landmark" class="app-input h-9 text-xs">
          </div>

          <!-- Doorstep Map Location Controls (Inline) -->
          <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-800 text-[11px] block" id="lbl-gps-section-inline"></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <button 
                type="button" 
                onclick="detectUserGps('inline')" 
                id="btn-gps-inline" 
                class="btn py-1.5 px-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg font-bold text-xs flex items-center justify-center gap-1 transition"
              >
                <span id="txt-gps-inline"></span>
              </button>
              <button 
                type="button" 
                onclick="toggleLeafletMap('inline')" 
                id="btn-map-toggle-inline" 
                class="btn py-1.5 px-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg font-bold text-xs flex items-center justify-center gap-1 transition"
              >
                <span id="txt-pin-map-inline"></span>
              </button>
            </div>
            <div>
              <input 
                type="text" 
                id="inline-addr-map-link" 
                oninput="handlePasteMapsLink('inline', this.value)" 
                class="app-input h-8 text-[11px] font-mono"
              >
            </div>
            <input type="hidden" id="inline-addr-lat" value="">
            <input type="hidden" id="inline-addr-lng" value="">
            <div id="inline-addr-map-feedback" class="text-[10px] font-medium text-slate-500 flex items-center gap-1">
              <span>💡</span>
              <span id="txt-map-hint-inline"></span>
            </div>
            <div id="inline-addr-map-wrap" class="hidden">
              <div id="inline-addr-map" class="w-full h-40 rounded-xl border border-slate-200 overflow-hidden shadow-inner z-0"></div>
              <p class="text-[9px] text-slate-400 mt-1 text-center" id="txt-map-drag-inline"></p>
            </div>
          </div>

          <div>
            <label class="block text-slate-600 font-semibold mb-1" id="lbl-inline-addr-city"></label>
            <div class="grid grid-cols-2 gap-2">
              <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-white cursor-pointer font-bold text-slate-800 hover:border-emerald-500 text-xs">
                <input type="radio" name="inline_addr_city" value="Hanamkonda" checked class="accent-emerald-600">
                <span id="inline-hanamkonda-label"></span>
              </label>
              <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-white cursor-pointer font-bold text-slate-800 hover:border-emerald-500 text-xs">
                <input type="radio" name="inline_addr_city" value="Warangal" class="accent-emerald-600">
                <span id="inline-warangal-label"></span>
              </label>
            </div>
          </div>
          <button type="button" onclick="saveInlineAddress()" class="btn btn-primary w-full h-9 text-xs font-bold shadow-xs" id="btn-save-inline-address"></button>
        </div>

        <!-- Address Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-2 pt-1">
          <button type="button" onclick="confirmAddressAndProceed()" class="btn btn-primary h-11 min-h-[44px] flex-1 font-bold text-xs shadow-xs">
            <span id="btn-proceed-veg"></span>
          </button>
          <button type="button" id="btn-add-addr-toggle" onclick="toggleNewAddressForm(true)" class="btn btn-secondary h-11 min-h-[44px] font-semibold text-xs text-slate-700">
            <span id="btn-add-new-addr-text"></span>
          </button>
        </div>
      </div>

      <!-- Step 2B: New Customer Onboarding Card -->
      <div id="onboarding-new-card" class="hidden app-card space-y-3 bg-slate-50 border border-slate-200">
        <div class="flex items-center justify-between">
          <h3 class="font-bold text-sm text-slate-900" id="new-cust-header"></h3>
          <span class="text-[10px] font-bold text-emerald-800 uppercase bg-emerald-100/70 px-2.5 py-0.5 rounded-full border border-emerald-200" id="new-cust-badge"></span>
        </div>
        <div class="space-y-2.5 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1" id="lbl-new-name"></label>
            <input type="text" id="new-cust-name" class="app-input">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1" id="lbl-new-address"></label>
            <input type="text" id="new-cust-address" class="app-input">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1" id="lbl-new-landmark"></label>
            <input type="text" id="new-cust-landmark" class="app-input">
          </div>

          <!-- Doorstep Map Location Controls (New Customer) -->
          <div class="p-3 bg-white rounded-xl border border-slate-200 space-y-2.5">
            <div>
              <span class="font-bold text-slate-800 text-xs block" id="lbl-gps-section-new"></span>
              <span class="text-[11px] text-slate-500 block" id="lbl-gps-section-new-desc"></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <button 
                type="button" 
                onclick="detectUserGps('new')" 
                id="btn-gps-new" 
                class="btn py-2 px-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition"
              >
                <span id="txt-gps-new"></span>
              </button>
              <button 
                type="button" 
                onclick="toggleLeafletMap('new')" 
                id="btn-map-toggle-new" 
                class="btn py-2 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition"
              >
                <span id="txt-pin-map-new"></span>
              </button>
            </div>
            <div>
              <input 
                type="text" 
                id="new-cust-map-link" 
                oninput="handlePasteMapsLink('new', this.value)" 
                class="app-input h-9 text-xs font-mono"
              >
            </div>
            <input type="hidden" id="new-cust-lat" value="">
            <input type="hidden" id="new-cust-lng" value="">
            <div id="new-cust-map-feedback" class="text-[11px] font-medium text-slate-500 flex items-center gap-1.5">
              <span>💡</span>
              <span id="txt-map-hint-new"></span>
            </div>
            <div id="new-cust-map-wrap" class="hidden">
              <div id="new-cust-map" class="w-full h-44 rounded-xl border border-slate-200 overflow-hidden shadow-inner z-0"></div>
              <p class="text-[10px] text-slate-400 mt-1 text-center" id="txt-map-drag-new"></p>
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1" id="lbl-new-locality"></label>
            <div class="grid grid-cols-2 gap-2">
              <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer font-bold text-slate-800 hover:border-emerald-500">
                <input type="radio" name="new_locality" value="Hanamkonda" checked onchange="switchRegion('Hanamkonda')" class="accent-emerald-600">
                <span id="new-hanamkonda-label"></span>
              </label>
              <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer font-bold text-slate-800 hover:border-emerald-500">
                <input type="radio" name="new_locality" value="Warangal" onchange="switchRegion('Warangal')" class="accent-emerald-600">
                <span id="new-warangal-label"></span>
              </label>
            </div>
            <p class="text-[11px] text-slate-400 mt-1" id="txt-kazipet-note"></p>
          </div>
          <button type="button" onclick="saveNewCustomerAndProceed()" class="btn btn-primary w-full h-11 min-h-[44px] font-bold text-xs mt-1 shadow-xs">
            <span id="btn-save-proceed"></span>
          </button>
        </div>
      </div>

      <!-- Step 3: Confirmed Delivery Address Strip (Visible after confirmation) -->
      <div id="onboarding-confirmed-strip" class="hidden app-card flex items-center justify-between gap-3 p-3 bg-emerald-50/80 border border-emerald-200">
        <div class="flex items-center gap-2.5 min-w-0">
          <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shrink-0">📍</span>
          <div class="min-w-0">
            <span class="text-[10px] text-emerald-800 font-bold uppercase tracking-wider block" id="lbl-confirmed-dest"></span>
            <div class="font-extrabold text-xs text-slate-900 truncate" id="confirmed-strip-name">Customer Name</div>
            <div class="text-[11px] text-slate-600 truncate" id="confirmed-strip-address">Street Address, Locality</div>
          </div>
        </div>
        <button type="button" onclick="changeActiveAddress()" class="btn btn-secondary h-8 px-3 text-xs font-bold text-emerald-800 border-emerald-200 shrink-0" id="btn-change-active-address"></button>
      </div>

    </section>

    <!-- Locked Catalog Placeholder (Shown until phone & address are confirmed) -->
    <div id="catalog-locked-card" class="app-card text-center py-10 space-y-3 bg-white border border-slate-200">
      <span class="text-4xl block">🥬</span>
      <h3 class="font-extrabold text-slate-900 text-base" id="locked-card-title"></h3>
      <p class="text-xs text-slate-600 max-w-sm mx-auto leading-relaxed" id="locked-card-desc"></p>
      <div class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold shadow-2xs">
        <span id="locked-card-badge"></span>
      </div>
    </div>

    <!-- Storefront Catalog Section (Revealed once phone and address are verified) -->
    <div id="storefront-catalog-section" class="space-y-4 hidden">

      <!-- ==================================================================== -->
      <!-- STEP 1: DELIVERY BATCH SELECTOR & 30-SLOT STATUS                     -->
      <!-- Auto-locked when address is known, manual preview for guests         -->
      <!-- ==================================================================== -->
      <section class="app-card space-y-3" id="batch-selection-card">
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2 min-w-0">
            <span class="card-badge badge-emerald whitespace-nowrap shrink-0" id="badge-step1"></span>
            <h2 class="text-sm font-bold text-slate-900 truncate" id="title-step1"></h2>
          </div>
          <span id="run-status-badge" class="card-badge whitespace-nowrap shrink-0 <?= ($activeSchedule && ($activeSchedule['status_mode'] ?? '') === 'OPEN') ? 'badge-emerald' : 'badge-amber' ?>"></span>
        </div>

        <!-- Locked Region Indicator for Confirmed Address Customers -->
        <div id="locked-region-indicator" class="hidden p-3 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-between text-xs">
          <div class="flex items-center gap-2">
            <span class="text-lg">📍</span>
            <div>
              <span class="text-[11px] text-emerald-700 font-semibold block" id="lbl-delivering-to-locked">Delivering to your area</span>
              <strong class="text-sm font-extrabold text-emerald-950" id="val-locked-region-name">Hanamkonda</strong>
            </div>
          </div>
          <span class="text-[11px] font-bold text-emerald-700 bg-white px-2 py-0.5 rounded-md border border-emerald-300 shadow-2xs whitespace-nowrap" id="lbl-auto-locked-badge">✓ Auto-Locked</span>
        </div>

        <!-- Locality Toggle Pills (Shown only for guests / before address confirmation) -->
        <div class="grid grid-cols-2 gap-2" id="locality-pills">
          <button 
            type="button" 
            onclick="switchRegion('Hanamkonda')" 
            id="pill-hanamkonda"
            class="btn py-2.5 px-3 rounded-xl font-extrabold text-xs sm:text-sm transition border-2 flex items-center justify-center gap-1.5 <?= $defaultRegion === 'Hanamkonda' ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"
          >
            <span>🏡</span>
            <span class="pill-name whitespace-nowrap" id="pill-hanamkonda-label"></span>
          </button>

          <button 
            type="button" 
            onclick="switchRegion('Warangal')" 
            id="pill-warangal"
            class="btn py-2.5 px-3 rounded-xl font-extrabold text-xs sm:text-sm transition border-2 flex items-center justify-center gap-1.5 <?= $defaultRegion === 'Warangal' ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"
          >
            <span>🏢</span>
            <span class="pill-name whitespace-nowrap" id="pill-warangal-label"></span>
          </button>
        </div>

        <!-- Batch Schedule Timing & 30-Order Capacity Bar -->
        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-2.5 text-xs">
          <div class="flex items-center justify-between gap-2">
            <span class="text-slate-500 font-medium whitespace-nowrap" id="lbl-next-delivery"></span>
            <strong class="text-slate-900 font-bold text-right" id="val-delivery-date">
              <?= htmlspecialchars($activeSchedule['delivery_fmt'] ?? 'Scheduled Day') ?>
            </strong>
          </div>

          <div class="flex items-center justify-between gap-2">
            <span class="text-slate-500 font-medium whitespace-nowrap" id="lbl-cutoff-time"></span>
            <strong class="text-emerald-700 font-bold font-mono text-right" id="val-cutoff-time">
              <?= htmlspecialchars($activeSchedule['cutoff_fmt'] ?? 'Daily 7:00 PM') ?>
            </strong>
          </div>

          <!-- 30-Order Capacity Badge -->
          <?php
            $curBooked = (int)($activeSchedule['booked_orders_count'] ?? 0);
            $isFull = (bool)($activeSchedule['is_batch_full'] ?? false);
          ?>
          <div class="pt-2 border-t border-slate-200/80 flex items-center justify-between gap-2 hidden">
            <span class="text-slate-600 font-medium whitespace-nowrap" id="lbl-batch-capacity"></span>
            <span id="batch-capacity-badge" class="font-mono text-xs font-extrabold px-2.5 py-0.5 rounded-full whitespace-nowrap <?= $isFull ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' ?>"></span>
          </div>

          <!-- Hard Cap Alert if Full -->
          <div id="batch-full-banner" class="<?= $isFull ? '' : 'hidden' ?> p-2.5 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs font-bold flex items-center gap-2">
            <span>⚠️</span>
            <span id="txt-batch-full-msg"></span>
          </div>
        </div>
      </section>

      <!-- ==================================================================== -->
      <!-- STEP 2: SCANNABLE 0.5 KG VEGETABLE CATALOG                           -->
      <!-- ==================================================================== -->
      <section class="space-y-3" id="vegetable-catalog">
        <div class="flex items-center justify-between px-1">
          <div class="flex items-center gap-2">
            <span class="card-badge badge-emerald whitespace-nowrap" id="badge-step2"></span>
            <h2 class="text-sm font-bold text-slate-900" id="title-step2"></h2>
          </div>
          <span class="text-xs font-semibold text-slate-500" id="catalog-count-label"></span>
        </div>

        <div class="space-y-2.5" id="vegetables-container">
          <?php if (!empty($initialCatalog)): ?>
            <?php foreach ($initialCatalog as $p): ?>
              <?php
                $pid = (int)($p['product_id'] ?? $p['id']);
                $stock = (int)($p['available_half_kg_stock'] ?? 0);
                $price = (float)($p['price_per_half_kg'] ?? 0);
                $isSoldOut = ($stock <= 0);
                $imgPath = str_replace('.webp', '.svg', (string)($p['image_path'] ?? ''));
              ?>
              <article class="app-card flex items-center justify-between gap-3 <?= $isSoldOut ? 'opacity-60 bg-slate-50/70' : '' ?>" data-id="<?= $pid ?>" data-telugu-name="<?= htmlspecialchars($p['telugu_name'] ?? '', ENT_QUOTES) ?>" data-english-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                  <div class="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                    <?php if (!empty($imgPath)): ?>
                      <img src="<?= htmlspecialchars($imgPath, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" class="w-full h-full object-contain" onerror="this.outerHTML='🥬'">
                    <?php else: ?>
                      <span class="text-2xl">🥬</span>
                    <?php endif; ?>
                  </div>
                  <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-sm sm:text-base text-slate-900 leading-snug break-words prod-name">
                      <?= htmlspecialchars($p['telugu_name'] ?: $p['name'], ENT_QUOTES) ?>
                    </h3>
                    <div class="text-sm font-extrabold text-emerald-700 mt-0.5 font-mono flex items-baseline gap-1">
                      <span>₹<?= number_format($price, 2) ?></span>
                      <span class="text-xs text-slate-500 font-normal whitespace-nowrap unit-label"></span>
                    </div>
                  </div>
                </div>

                <div class="shrink-0 pl-1">
                  <?php if ($isSoldOut): ?>
                    <span class="card-badge badge-slate font-bold text-xs py-1.5 px-2.5 whitespace-nowrap sold-out-badge"></span>
                  <?php else: ?>
                    <div class="flex items-center bg-slate-100 rounded-xl p-0.5 border border-slate-200">
                      <button 
                        type="button" 
                        class="stepper-btn btn-minus bg-white hover:bg-slate-50 text-slate-800 rounded-lg shadow-xs disabled:opacity-40" 
                        data-id="<?= $pid ?>" 
                        onclick="updateItemQty(<?= $pid ?>, -1)"
                        aria-label="Decrease quantity"
                      >−</button>
                      <span class="w-7 text-center font-mono font-bold text-sm text-slate-900 qty-val-<?= $pid ?>">0</span>
                      <button 
                        type="button" 
                        class="stepper-btn btn-plus bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-xs" 
                        data-id="<?= $pid ?>" 
                        data-stock="<?= $stock ?>"
                        onclick="updateItemQty(<?= $pid ?>, 1)"
                        aria-label="Increase quantity"
                      >+</button>
                    </div>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="app-card text-center py-10 text-slate-400 text-sm" id="catalog-loading-msg"></div>
          <?php endif; ?>
        </div>
      </section>
  </div>

  </main>

  <!-- ====================================================================== -->
  <!-- STEP 3: STICKY CART BOTTOM BAR                                         -->
  <!-- Displays total price, packets count, and triggers checkout drawer      -->
  <!-- ====================================================================== -->
  <aside id="sticky-cart-bar" class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-200 p-4 z-40 shadow-xl hidden">
    <div class="max-w-xl mx-auto flex items-center justify-between gap-3">
      <div>
        <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block" id="bar-cart-label"></span>
        <div class="font-mono font-extrabold text-lg text-slate-900">
          <span id="bar-total-amount">₹0.00</span>
          <span class="text-xs text-slate-500 font-normal font-sans" id="bar-total-count"></span>
        </div>
      </div>
      <button 
        type="button" 
        id="btn-open-drawer" 
        onclick="openCheckoutDrawer()"
        class="btn btn-primary btn-large px-6 shadow-md"
      >
        <span id="btn-open-drawer-text"></span>
      </button>
    </div>
  </aside>

  <!-- ====================================================================== -->
  <!-- 1-PAGE CHECKOUT BOTTOM DRAWER                                          -->
  <!-- Clean Inter typography, customer info, address, quiet validation       -->
  <!-- ====================================================================== -->
  <div id="checkout-backdrop" class="app-drawer-backdrop hidden" onclick="closeCheckoutDrawer()"></div>
  
  <div id="checkout-drawer" class="app-drawer-bottom hidden">
    <div class="max-w-lg mx-auto space-y-4">
      
      <!-- Drawer Header -->
      <div class="flex items-center justify-between border-b border-slate-200 pb-3">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-lg">🛒</div>
          <div>
            <h3 class="font-extrabold text-base text-slate-900" id="drawer-title"></h3>
            <span class="text-xs text-slate-500 font-medium" id="drawer-run-desc"></span>
          </div>
        </div>
        <button type="button" onclick="closeCheckoutDrawer()" class="w-9 h-9 rounded-xl hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-700 text-2xl font-bold transition">&times;</button>
      </div>

      <!-- Selected Items Summary -->
      <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200 space-y-2">
        <div class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between">
          <span id="drawer-basket-summary"></span>
          <span id="drawer-items-weight" class="font-mono text-slate-500">0 kg</span>
        </div>
        <div id="drawer-items-list" class="text-xs space-y-1 divide-y divide-slate-200/60 max-h-36 overflow-y-auto">
          <!-- Populated dynamically -->
        </div>
      </div>

      <!-- Customer Details Form -->
      <form id="checkout-form" onsubmit="submitOrder(event)" class="space-y-3.5">
        
        <!-- Verified Delivery Address Summary in Drawer -->
        <div id="drawer-address-card" class="bg-emerald-50/80 border border-emerald-200 rounded-2xl p-3.5 text-xs space-y-1.5">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-extrabold uppercase text-emerald-800 tracking-wider" id="lbl-drawer-address"></span>
            <button type="button" onclick="editAddressFromDrawer()" class="text-xs font-bold text-emerald-700 hover:underline" id="btn-drawer-change"></button>
          </div>
          <div class="font-extrabold text-slate-900 text-sm" id="drawer-summary-name">Customer Name</div>
          <div class="text-slate-600 font-medium" id="drawer-summary-address">123 Street, Locality</div>
          <div class="text-slate-500 font-mono text-[11px]" id="drawer-summary-phone">+91 ----------</div>
        </div>

        <!-- Manual Input Fields (Hidden by default when address is confirmed, kept in sync for submission) -->
        <div id="drawer-inputs-container" class="space-y-3 hidden">
          <div>
            <label for="cust-name" class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-name"></label>
            <input 
              type="text" 
              id="cust-name" 
              name="full_name" 
              required 
              class="app-input"
            >
          </div>

          <div>
            <label for="cust-phone" class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-phone"></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center font-mono font-bold text-slate-400 text-sm">+91</span>
              <input 
                type="tel" 
                id="cust-phone" 
                name="phone_number" 
                maxlength="10" 
                pattern="[0-9]{10}" 
                required 
                placeholder="9876543210" 
                class="app-input pl-12 font-mono font-bold"
              >
            </div>
          </div>

          <div>
            <label for="cust-address" class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-address"></label>
            <input 
              type="text" 
              id="cust-address" 
              name="delivery_address" 
              required 
              class="app-input"
            >
          </div>

          <div>
            <label for="cust-landmark" class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-landmark"></label>
            <input 
              type="text" 
              id="cust-landmark" 
              name="landmark" 
              class="app-input"
            >
          </div>

          <!-- Locality Selector -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-locality"></label>
            <select id="cust-region" name="region" onchange="syncRegionFromDrawer(this.value)" class="app-select">
              <option value="Hanamkonda" id="drawer-opt-hanamkonda"></option>
              <option value="Warangal" id="drawer-opt-warangal"></option>
            </select>
          </div>
          <input type="hidden" id="cust-lat" name="latitude" value="">
          <input type="hidden" id="cust-lng" name="longitude" value="">
        </div>

        <!-- Payment Method Selection -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-payment-mode"></label>
          <div class="grid grid-cols-1 gap-2">
            <label class="flex items-center gap-2 p-3 rounded-xl border border-emerald-600 bg-emerald-50/50 cursor-pointer transition">
              <input type="radio" name="payment_method" value="COD" checked class="accent-emerald-600">
              <div>
                <span class="block text-xs font-bold text-slate-900" id="txt-mode-cod"></span>
                <span class="block text-[10px] text-slate-500" id="txt-mode-cod-sub"></span>
              </div>
            </label>
          </div>
        </div>

        <!-- Bill Breakdown Card -->
        <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200 space-y-1.5 text-xs">
          <div class="flex justify-between text-slate-600">
            <span id="lbl-bill-subtotal"></span>
            <span class="font-mono font-bold" id="bill-subtotal">₹0.00</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span id="lbl-bill-delivery"></span>
            <span class="font-mono font-bold" id="bill-delivery">₹0.00</span>
          </div>
          <div class="border-t border-slate-200 pt-1.5 flex justify-between text-sm font-extrabold text-slate-900">
            <span id="lbl-bill-total"></span>
            <span class="font-mono text-emerald-700" id="bill-total">₹0.00</span>
          </div>
        </div>

        <!-- Submit Button -->
        <button 
          type="submit" 
          id="btn-confirm-order" 
          class="btn btn-primary btn-large btn-full text-base font-extrabold shadow-lg"
        >
          <span id="btn-confirm-order-text"></span>
        </button>
      </form>

    </div>
  </div>

  <script src="assets/js/i18n-customer.js"></script>
  <script>
    // Global State
    let currentLang = localStorage.getItem('ps_customer_lang') || 'te';
    let currentRegion = '<?= $defaultRegion ?>';
    let activeSchedule = <?= json_encode($activeSchedule) ?>;
    let activeCatalog = <?= json_encode($initialCatalog) ?>;
    const regionSchedules = <?= json_encode($regionSchedules) ?>;
    const movThreshold = <?= (float)$movThreshold ?>;
    const standardDeliveryFee = <?= (float)$deliveryFee ?>;
    const storeWhatsApp = '<?= $storeWhatsApp ?>';
    
    // Customer profile state
    let customerProfile = null;

    // Cart representation: { productId: quantityInPackets }
    let cart = {};

    // --------------------------------------------------------------------------
    // Language Switcher
    // --------------------------------------------------------------------------
    function setCustomerLanguage(lang) {
      currentLang = lang;
      localStorage.setItem('ps_customer_lang', lang);
      const isTe = (lang === 'te');
      const t = CUSTOMER_I18N[lang] || CUSTOMER_I18N.te;

      // --- Language toggle button styles ---
      const btnTe = document.getElementById('btn-lang-te');
      const btnEn = document.getElementById('btn-lang-en');
      if (btnTe && btnEn) {
        if (isTe) {
          btnTe.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
          btnEn.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
        } else {
          btnEn.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
          btnTe.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
        }
      }

      // --- Header ---
      const el = (id) => document.getElementById(id);
      const set = (id, val) => { const e = el(id); if (e) e.textContent = val; };
      const setAttr = (id, attr, val) => { const e = el(id); if (e) e.setAttribute(attr, val); };

      set('header-brand', t.brand_title);
      set('header-tagline', t.brand_subtitle);
      set('pwa-text', t.pwa_banner_text);

      // --- Onboarding Step 1 ---
      set('lbl-onboarding-badge', t.onboarding_badge);
      set('btn-phone-continue-label', t.btn_continue);
      set('txt-onboarding-hint', t.onboarding_hint);

      // --- Returning customer card ---
      set('returning-badge', t.returning_badge);
      set('btn-change-mobile', t.btn_change_mobile);
      set('lbl-select-addr', t.lbl_select_address);
      set('btn-proceed-veg', t.btn_confirm_address);
      set('btn-add-new-addr-text', t.btn_add_new_address);

      // Inline new address form (in returning customer card)
      set('inline-addr-form-title', t.inline_addr_title);
      set('lbl-inline-addr-label', t.lbl_addr_label);
      setAttr('inline-addr-label', 'placeholder', t.placeholder_addr_label);
      set('lbl-inline-addr-street', t.lbl_addr_street);
      setAttr('inline-addr-street', 'placeholder', t.placeholder_addr_street);
      set('lbl-inline-addr-landmark', t.lbl_addr_landmark);
      setAttr('inline-addr-landmark', 'placeholder', t.placeholder_addr_landmark);
      set('lbl-gps-section-inline', t.gps_section_title);
      set('txt-gps-inline', t.btn_use_gps);
      set('txt-pin-map-inline', t.btn_pin_map);
      setAttr('inline-addr-map-link', 'placeholder', t.placeholder_maps_link);
      set('txt-map-hint-inline', t.map_hint);
      set('txt-map-drag-inline', t.map_drag_hint);
      set('lbl-inline-addr-city', t.lbl_addr_city);
      set('inline-hanamkonda-label', t.hanamkonda_label);
      set('inline-warangal-label', t.warangal_label);
      set('btn-save-inline-address', t.btn_save_address);

      // --- New customer card ---
      set('new-cust-header', t.new_cust_header);
      set('new-cust-badge', t.new_cust_badge);
      set('lbl-new-name', t.lbl_new_name);
      setAttr('new-cust-name', 'placeholder', t.placeholder_new_name);
      set('lbl-new-address', t.lbl_new_address);
      setAttr('new-cust-address', 'placeholder', t.placeholder_new_address);
      set('lbl-new-landmark', t.lbl_new_landmark);
      setAttr('new-cust-landmark', 'placeholder', t.placeholder_new_landmark);
      set('lbl-gps-section-new', '📍 ' + (isTe ? 'GPS డోర్‌స్టెప్ లొకేషన్' : 'Doorstep Map Location'));
      set('lbl-gps-section-new-desc', t.gps_section_desc);
      set('txt-gps-new', t.btn_use_gps);
      set('txt-pin-map-new', t.btn_pin_map);
      setAttr('new-cust-map-link', 'placeholder', t.placeholder_maps_link);
      set('txt-map-hint-new', t.map_hint);
      set('txt-map-drag-new', t.map_drag_hint);
      set('lbl-new-locality', t.lbl_new_locality);
      set('new-hanamkonda-label', t.hanamkonda_label);
      set('new-warangal-label', t.warangal_label);
      set('txt-kazipet-note', t.kazipet_note);
      set('btn-save-proceed', t.btn_save_proceed);

      // --- Confirmed address strip ---
      set('lbl-confirmed-dest', t.lbl_delivering_to);
      set('btn-change-active-address', t.btn_change_address);

      // --- Locked catalog card ---
      set('locked-card-title', t.locked_title);
      set('locked-card-desc', t.locked_desc);
      set('locked-card-badge', t.locked_badge);

      // --- Batch selector card ---
      set('badge-step1', t.badge_delivery_batch);
      const isAddressConfirmed = !document.getElementById('onboarding-confirmed-strip')?.classList.contains('hidden');
      if (isAddressConfirmed) {
        set('title-step1', t.title_schedule_locked || (isTe ? 'మీ డెలివరీ షెడ్యూల్' : 'Your Delivery Schedule'));
      } else {
        set('title-step1', t.title_delivery_batch);
      }
      set('lbl-delivering-to-locked', t.lbl_delivering_to_locked || (isTe ? 'మీ ప్రాంతానికి డెలివరీ షెడ్యూల్:' : 'Delivering to your area:'));
      set('lbl-auto-locked-badge', t.lbl_auto_locked_badge || (isTe ? '✓ ఆటో-లాక్ అయింది' : '✓ Auto-Locked'));
      set('pill-hanamkonda-label', t.hanamkonda_label);
      set('pill-warangal-label', t.warangal_label);
      set('lbl-next-delivery', t.lbl_next_delivery);
      set('lbl-cutoff-time', t.lbl_cutoff_time);
      set('lbl-batch-capacity', t.batch_capacity_label);
      // Batch capacity count badge
      const capBadge = el('batch-capacity-badge');
      if (capBadge) {
        const n = parseInt(capBadge.getAttribute('data-booked') || '<?= $curBooked ?>', 10);
        capBadge.textContent = t.batch_slots_booked(n);
      }
      set('txt-batch-full-msg', t.batch_full_msg);
      // Run status badge
      const statusMode = activeSchedule?.status_mode || 'UPCOMING';
      const statusBadge = el('run-status-badge');
      if (statusBadge) {
        if (statusMode === 'OPEN') statusBadge.textContent = t.bookings_open;
        else if (statusMode === 'CLOSED') statusBadge.textContent = t.bookings_closed;
        else statusBadge.textContent = t.bookings_upcoming;
      }

      // --- Step 2: Catalog ---
      set('badge-step2', t.badge_step2);
      set('title-step2', t.title_step2);
      const countLabel = el('catalog-count-label');
      if (countLabel) countLabel.textContent = t.varieties_label(activeCatalog.length);
      set('catalog-loading-msg', t.catalog_empty);

      // Update unit-labels
      document.querySelectorAll('.unit-label').forEach(el => el.textContent = t.unit_per_half_kg);
      // Update sold-out badges
      document.querySelectorAll('.sold-out-badge').forEach(el => el.textContent = t.sold_out);

      // Update product card names based on language
      document.querySelectorAll('#vegetables-container article').forEach(card => {
        const teluguName = card.getAttribute('data-telugu-name') || '';
        const englishName = card.getAttribute('data-english-name') || '';
        const nameEl = card.querySelector('.prod-name');
        if (nameEl) nameEl.textContent = isTe ? (teluguName || englishName) : englishName;
      });

      // --- Sticky cart bar ---
      set('bar-cart-label', t.basket_label);
      set('btn-open-drawer-text', t.btn_review_order);

      // --- Checkout Drawer ---
      set('drawer-title', t.drawer_title);
      // Update drawer-run-desc if there's an active schedule/region
      const drawerDesc = el('drawer-run-desc');
      if (drawerDesc && activeSchedule) {
        const regionLabel = currentRegion === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
        drawerDesc.textContent = (activeSchedule.delivery_day || '') + (isTe ? ' బ్యాచ్ · ' : ' Batch · ') + regionLabel;
      }

      // Re-translate customer profile elements if loaded
      if (customerProfile) {
        const welEl = el('returning-welcome-title');
        if (welEl && customerProfile.full_name) {
          welEl.textContent = `${t.returning_welcome} ${customerProfile.full_name}!`;
        }
        if (customerProfile.addresses && customerProfile.addresses.length) {
          renderSavedAddresses();
        }
        if (customerProfile.active_address) {
          const activeAddr = customerProfile.active_address;
          const displayRegion = activeAddr.region === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
          const gpsBadge = (activeAddr.latitude && activeAddr.longitude) ? ` <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-sky-100 text-sky-800 border border-sky-200">${t.gps_pinned_badge}</span>` : '';
          const stripAddr = el('confirmed-strip-address');
          if (stripAddr) stripAddr.innerHTML = `${activeAddr.delivery_address}${activeAddr.landmark ? ' (' + activeAddr.landmark + ')' : ''} &bull; ${displayRegion}${gpsBadge}`;
          const drawerAddr = el('drawer-summary-address');
          if (drawerAddr) drawerAddr.innerHTML = `${activeAddr.delivery_address}${activeAddr.landmark ? ' (' + activeAddr.landmark + ')' : ''} &bull; ${displayRegion}${gpsBadge}`;
        }
      }
      set('drawer-basket-summary', t.drawer_basket_heading);
      set('lbl-drawer-address', t.lbl_drawer_address);
      set('btn-drawer-change', t.btn_drawer_change);
      set('lbl-cust-name', t.name_label);
      setAttr('cust-name', 'placeholder', t.name_placeholder);
      set('lbl-cust-phone', t.phone_label);
      set('lbl-cust-address', t.address_label);
      setAttr('cust-address', 'placeholder', t.address_placeholder);
      set('lbl-cust-landmark', t.landmark_label);
      setAttr('cust-landmark', 'placeholder', t.landmark_placeholder);
      set('lbl-cust-locality', t.locality_label);

      // Dropdown options
      set('drawer-opt-hanamkonda', t.hanamkonda_label);
      set('drawer-opt-warangal', t.warangal_label);

      // Payment
      set('lbl-payment-mode', t.payment_mode_label);
      set('txt-mode-cod', t.pay_cod_label);
      set('txt-mode-cod-sub', t.pay_cod_sub);
      set('txt-mode-upi', t.pay_upi_label);
      set('txt-mode-upi-sub', t.pay_upi_sub);

      // Bill
      set('lbl-bill-subtotal', t.items_subtotal);
      set('lbl-bill-delivery', t.delivery_fee);
      set('lbl-bill-total', t.total_payable);

      // Confirm button
      const isFull = !!(activeSchedule?.is_batch_full);
      set('btn-confirm-order-text', isFull ? t.batch_full_btn : t.btn_confirm_order);

      renderCartUI();
    }

    // --------------------------------------------------------------------------
    // PWA Banner & Installation Prompt Logic
    // --------------------------------------------------------------------------
    let deferredPublicPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredPublicPrompt = e;
    });

    function dismissPwaBanner() {
      document.getElementById('pwa-banner')?.classList.add('hidden');
      localStorage.setItem('ps_pwa_dismissed', '1');
    }

    if (localStorage.getItem('ps_pwa_dismissed') === '1' || window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
      document.getElementById('pwa-banner')?.classList.add('hidden');
    }

    function triggerPublicPwaInstall() {
      if (deferredPublicPrompt) {
        deferredPublicPrompt.prompt();
        deferredPublicPrompt.userChoice.then((choice) => {
          if (choice.outcome === 'accepted') {
            dismissPwaBanner();
          }
          deferredPublicPrompt = null;
        });
      } else {
        const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIos) {
          alert('To install Prakruthi Siri on your iPhone/iPad:\n\n1. Tap the Share button 📤 in Safari.\n2. Scroll down & tap "Add to Home Screen" ➕.');
        } else {
          alert('To install Prakruthi Siri:\n\nIn your browser menu (⋮), select "Install App" or "Add to Home Screen".');
        }
      }
    }

    // --------------------------------------------------------------------------
    // Phone-First Onboarding sequence & Multi-Address Management
    // --------------------------------------------------------------------------
    let phoneLookupTimeout = null;
    let selectedAddressId = null;

    function handlePhoneInput(val) {
      const clean = val.replace(/[^0-9]/g, '');
      const btn = document.getElementById('btn-phone-continue');
      if (clean.length === 10) {
        btn?.classList.remove('hidden');
        clearTimeout(phoneLookupTimeout);
        phoneLookupTimeout = setTimeout(() => performPhoneLookup(clean), 300);
      } else {
        btn?.classList.add('hidden');
      }
    }

    function checkPhoneManual() {
      const phone = document.getElementById('onboarding-phone').value.replace(/[^0-9]/g, '');
      if (phone.length === 10) {
        performPhoneLookup(phone);
      }
    }

    async function performPhoneLookup(phone) {
      try {
        const resp = await fetch(`api/customer-lookup.php?phone=${phone}`);
        const data = await resp.json();

        if (data.success && data.found && data.customer) {
          customerProfile = data.customer;
          if (!customerProfile.addresses || !customerProfile.addresses.length) {
            customerProfile.addresses = [{
              id: 1,
              label: 'Home',
              delivery_address: customerProfile.delivery_address || '',
              landmark: customerProfile.landmark || '',
              region: customerProfile.region || 'Hanamkonda',
              is_default: true
            }];
          }
          selectedAddressId = customerProfile.active_address?.id || customerProfile.addresses[0]?.id;
          localStorage.setItem('ps_customer_phone', phone);
          localStorage.setItem('ps_cust_profile', JSON.stringify(customerProfile));
          localStorage.setItem('ps_saved_profile', JSON.stringify(customerProfile));
          showReturningCustomerUI(customerProfile);
        } else {
          customerProfile = { phone_number: phone, addresses: [] };
          showNewCustomerUI(phone);
        }
      } catch (err) {
        customerProfile = { phone_number: phone, addresses: [] };
        showNewCustomerUI(phone);
      }
    }

    function showReturningCustomerUI(cust) {
      document.getElementById('onboarding-step-1').classList.add('hidden');
      document.getElementById('onboarding-new-card').classList.add('hidden');
      document.getElementById('onboarding-confirmed-strip').classList.add('hidden');
      const retCard = document.getElementById('onboarding-returning-card');
      retCard.classList.remove('hidden');

      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      document.getElementById('returning-welcome-title').textContent = `${t.returning_welcome} ${cust.full_name || ''}!`;
      document.getElementById('returning-phone-display').textContent = `+91 ${cust.phone_number || cust.phone || ''}`;

      renderSavedAddresses();
    }

    function renderSavedAddresses() {
      const container = document.getElementById('saved-addresses-container');
      if (!container) return;
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

      const addrs = customerProfile?.addresses || [];
      if (!addrs.length && customerProfile?.delivery_address) {
        addrs.push({
          id: 1,
          label: 'Home',
          delivery_address: customerProfile.delivery_address,
          landmark: customerProfile.landmark || '',
          region: customerProfile.region || 'Hanamkonda',
          is_default: true
        });
        customerProfile.addresses = addrs;
      }

      if (!selectedAddressId && addrs.length > 0) {
        selectedAddressId = addrs[0].id;
      }

      container.innerHTML = addrs.map((addr) => {
        const isSelected = (addr.id === selectedAddressId);
        const icon = addr.label && addr.label.toLowerCase().includes('work') ? '💼' : '🏠';
        const displayLabel = (!addr.label || addr.label.toLowerCase() === 'home') ? t.placeholder_addr_label : addr.label;
        const displayRegion = addr.region === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
        return `
          <label class="flex items-start gap-3 p-3 rounded-xl border-2 transition cursor-pointer ${isSelected ? 'border-emerald-600 bg-white shadow-xs' : 'border-slate-200 bg-white/70 hover:border-slate-300'}">
            <input 
              type="radio" 
              name="selected_addr_id" 
              value="${addr.id}" 
              ${isSelected ? 'checked' : ''} 
              onchange="selectAddress(${addr.id})"
              class="mt-1 accent-emerald-600 shrink-0"
            >
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-extrabold text-slate-900 text-xs">${icon} ${displayLabel}</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${addr.region === 'Warangal' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'}">${displayRegion}</span>
                ${(addr.latitude && addr.longitude) ? `<span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-sky-50 text-sky-800 border border-sky-200">${t.gps_pinned_badge}</span>` : ''}
              </div>
              <p class="text-xs text-slate-600 mt-0.5 leading-snug">${addr.delivery_address}${addr.landmark ? ' (' + addr.landmark + ')' : ''}</p>
            </div>
          </label>
        `;
      }).join('');
    }

    function selectAddress(addrId) {
      selectedAddressId = addrId;
      const addr = (customerProfile?.addresses || []).find(a => a.id === addrId);
      if (addr) {
        customerProfile.active_address = addr;
        customerProfile.delivery_address = addr.delivery_address;
        customerProfile.landmark = addr.landmark;
        customerProfile.region = addr.region;

        if (addr.region && addr.region !== currentRegion) {
          switchRegion(addr.region);
        }
      }
      renderSavedAddresses();
    }

    function saveProfilePhone() {
      const ph = (customerProfile?.phone_number || customerProfile?.phone || '').replace(/\D/g,'').slice(-10);
      if (ph) localStorage.setItem('ps_customer_phone', ph);
    }

    function toggleNewAddressForm(show) {
      const form = document.getElementById('inline-new-addr-form');
      const btnToggle = document.getElementById('btn-add-addr-toggle');
      if (show) {
        form?.classList.remove('hidden');
        btnToggle?.classList.add('hidden');
        // Clear fields for a fresh new address
        ['inline-addr-label','inline-addr-street','inline-addr-landmark','inline-addr-map-link'].forEach(id => {
          const el = document.getElementById(id); if (el) el.value = '';
        });
        document.getElementById('inline-addr-lat').value = '';
        document.getElementById('inline-addr-lng').value = '';
        _syncInlineGpsUI(null, null);
      } else {
        form?.classList.add('hidden');
        btnToggle?.classList.remove('hidden');
      }
    }

    function _syncInlineGpsUI(lat, lng) {
      const hasCoords = !!(lat && lng);
      const gpsBtn  = document.getElementById('btn-gps-inline');
      const mapBtn  = document.getElementById('btn-map-toggle-inline');
      const feedback = document.getElementById('inline-addr-map-feedback');
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      if (hasCoords) {
        if (gpsBtn)  { gpsBtn.classList.add('hidden'); }
        if (mapBtn)  { mapBtn.classList.add('hidden'); }
        if (feedback) feedback.innerHTML = `<span class="inline-flex items-center gap-1 text-sky-700 font-bold">📍 ${t.gps_pinned_badge} &mdash; <button type="button" onclick="_clearInlineGps()" class="underline text-slate-500 font-normal">${t.btn_change_mobile || 'Change'}</button></span>`;
      } else {
        if (gpsBtn)  { gpsBtn.classList.remove('hidden'); }
        if (mapBtn)  { mapBtn.classList.remove('hidden'); }
        if (feedback) feedback.innerHTML = `<span>💡</span><span id="txt-map-hint-inline"></span>`;
        const t2 = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
        const hint = document.getElementById('txt-map-hint-inline');
        if (hint) hint.textContent = t2.map_hint || '';
      }
    }

    function _clearInlineGps() {
      document.getElementById('inline-addr-lat').value = '';
      document.getElementById('inline-addr-lng').value = '';
      _syncInlineGpsUI(null, null);
    }

    // --------------------------------------------------------------------------
    // Map Location, GPS Auto-Detection & Geofencing System
    // --------------------------------------------------------------------------
    const FARM_HUB = { lat: 18.028439, lng: 79.635941, maxRadiusKm: 11.5 };
    const CENTROIDS = {
      Hanamkonda: { lat: 17.9856, lng: 79.5892 },
      Warangal: { lat: 17.9689, lng: 79.5941 }
    };

    function calculateDistanceKm(lat1, lon1, lat2, lon2) {
      const R = 6371; // km
      const dLat = (lat2 - lat1) * Math.PI / 180;
      const dLon = (lon2 - lon1) * Math.PI / 180;
      const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      return R * c;
    }

    function checkGeofence(lat, lng) {
      const dist = calculateDistanceKm(FARM_HUB.lat, FARM_HUB.lng, lat, lng);
      const isTe = (currentLang === 'te');
      
      // Kazipet western cutoff: west of 79.540°E within urban corridor
      if (lng < 79.540 && lat >= 17.92 && lat <= 18.06 && dist <= 18.0) {
        return { 
          valid: false, 
          error: (isTe ? 'క్షమించండి! మేము కాజీపేట ప్రాంతానికి డెలివరీ చేయట్లేదు.' : 'Kazipet is outside our delivery area. Currently serving Hanamkonda & Warangal only.'), 
          isKazipet: true, 
          dist 
        };
      }
      if (dist > FARM_HUB.maxRadiusKm) {
        return { 
          valid: false, 
          error: (isTe ? `మీ లొకేషన్ మా ఫామ్ హబ్ నుండి ${dist.toFixed(1)} కిమీ దూరంలో ఉంది (గరిష్ట పరిధి 11.5 కిమీ).` : `Location is ${dist.toFixed(1)} km away, exceeding our 11.5 km delivery radius.`), 
          dist 
        };
      }

      // Proximity test for auto-region selection
      const dH = calculateDistanceKm(CENTROIDS.Hanamkonda.lat, CENTROIDS.Hanamkonda.lng, lat, lng);
      const dW = calculateDistanceKm(CENTROIDS.Warangal.lat, CENTROIDS.Warangal.lng, lat, lng);
      const closestRegion = dH <= dW ? 'Hanamkonda' : 'Warangal';

      return { valid: true, closestRegion, dist };
    }

    let leafletMaps = { new: null, inline: null };
    let leafletMarkers = { new: null, inline: null };

    function initLeafletMap(type, initialLat, initialLng) {
      const wrapId = `${type === 'new' ? 'new-cust' : 'inline-addr'}-map-wrap`;
      const mapElId = `${type === 'new' ? 'new-cust' : 'inline-addr'}-map`;
      document.getElementById(wrapId)?.classList.remove('hidden');

      const lat = initialLat || 17.9856;
      const lng = initialLng || 79.5892;

      if (!leafletMaps[type]) {
        if (typeof L === 'undefined') {
          console.warn('Leaflet not loaded yet');
          return;
        }
        leafletMaps[type] = L.map(mapElId).setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap'
        }).addTo(leafletMaps[type]);

        const marker = L.marker([lat, lng], { draggable: true }).addTo(leafletMaps[type]);
        leafletMarkers[type] = marker;

        marker.on('dragend', () => {
          const pos = marker.getLatLng();
          setPointCoordinates(type, pos.lat, pos.lng, true);
        });

        leafletMaps[type].on('click', (e) => {
          marker.setLatLng(e.latlng);
          setPointCoordinates(type, e.latlng.lat, e.latlng.lng, true);
        });
      } else {
        leafletMaps[type].setView([lat, lng], 14);
        leafletMarkers[type].setLatLng([lat, lng]);
        setTimeout(() => { leafletMaps[type].invalidateSize(); }, 150);
      }
    }

    function toggleLeafletMap(type) {
      const wrap = document.getElementById(`${type === 'new' ? 'new-cust' : 'inline-addr'}-map-wrap`);
      if (wrap?.classList.contains('hidden')) {
        const curLat = parseFloat(document.getElementById(`${type === 'new' ? 'new-cust' : 'inline-addr'}-lat`).value) || null;
        const curLng = parseFloat(document.getElementById(`${type === 'new' ? 'new-cust' : 'inline-addr'}-lng`).value) || null;
        initLeafletMap(type, curLat, curLng);
      } else {
        wrap?.classList.add('hidden');
      }
    }

    function setPointCoordinates(type, lat, lng, fromMap = false) {
      const prefix = (type === 'new') ? 'new-cust' : 'inline-addr';
      const latEl = document.getElementById(`${prefix}-lat`);
      const lngEl = document.getElementById(`${prefix}-lng`);
      const feedbackEl = document.getElementById(`${prefix}-map-feedback`);
      const isTe = (currentLang === 'te');
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

      latEl.value = lat.toFixed(7);
      lngEl.value = lng.toFixed(7);

      const check = checkGeofence(lat, lng);
      if (check.valid) {
        feedbackEl.className = 'text-[11px] font-bold text-emerald-700 flex items-center gap-1.5';
        const regionName = check.closestRegion === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
        const infoMsg = isTe
          ? `లొకేషన్ పిన్ నమోదైంది (${lat.toFixed(4)}, ${lng.toFixed(4)}) • ఫామ్ నుండి ${check.dist.toFixed(1)} కిమీ • ${regionName}`
          : `Location Pinned (${lat.toFixed(4)}, ${lng.toFixed(4)}) • ${check.dist.toFixed(1)} km from Hub • ${check.closestRegion}`;
        feedbackEl.innerHTML = `<span>✓</span> <span>${infoMsg}</span>`;

        // Auto-select region
        if (type === 'new') {
          const radio = document.querySelector(`input[name="new_locality"][value="${check.closestRegion}"]`);
          if (radio && !radio.checked) {
            radio.checked = true;
            switchRegion(check.closestRegion);
          }
        } else {
          const radio = document.querySelector(`input[name="inline_addr_city"][value="${check.closestRegion}"]`);
          if (radio) radio.checked = true;
        }
      } else {
        feedbackEl.className = 'text-[11px] font-bold text-rose-600 flex items-center gap-1.5';
        feedbackEl.innerHTML = `<span>⚠️</span> <span>${check.error}</span>`;
      }

      if (!fromMap && leafletMaps[type]) {
        leafletMaps[type].setView([lat, lng], 15);
        leafletMarkers[type].setLatLng([lat, lng]);
      }
    }

    function detectUserGps(type) {
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      const btnTxt = document.getElementById(`txt-gps-${type}`);
      const oldText = btnTxt.textContent;
      btnTxt.textContent = t.gps_locating;

      if (!navigator.geolocation) {
        alert(t.gps_unsupported);
        btnTxt.textContent = oldText;
        return;
      }

      navigator.geolocation.getCurrentPosition(
        pos => {
          btnTxt.textContent = t.gps_latched;
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;
          initLeafletMap(type, lat, lng);
          setPointCoordinates(type, lat, lng, false);
          if (type === 'inline') setTimeout(() => _syncInlineGpsUI(lat, lng), 3100);
          else setTimeout(() => { btnTxt.textContent = oldText; }, 3000);
        },
        err => {
          console.warn('High-accuracy GPS timeout, retrying with low-accuracy:', err.message);
          navigator.geolocation.getCurrentPosition(
            pos => {
              btnTxt.textContent = t.gps_latched;
              const lat = pos.coords.latitude;
              const lng = pos.coords.longitude;
              initLeafletMap(type, lat, lng);
              setPointCoordinates(type, lat, lng, false);
              if (type === 'inline') setTimeout(() => _syncInlineGpsUI(lat, lng), 3100);
              else setTimeout(() => { btnTxt.textContent = oldText; }, 3000);
            },
            err2 => {
              alert(t.gps_failed);
              btnTxt.textContent = oldText;
              toggleLeafletMap(type);
            },
            { enableHighAccuracy: false, timeout: 8000, maximumAge: 120000 }
          );
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
      );
    }

    function handlePasteMapsLink(type, val) {
      if (!val) return;
      let lat = null, lng = null;
      const atMatch = val.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
      const qMatch = val.match(/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/);
      const coordMatch = val.match(/(-?\d+\.\d+)[,\s]+(-?\d+\.\d+)/);
      const dMatch = val.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);

      if (dMatch) {
        lat = parseFloat(dMatch[1]);
        lng = parseFloat(dMatch[2]);
      } else if (atMatch) {
        lat = parseFloat(atMatch[1]);
        lng = parseFloat(atMatch[2]);
      } else if (qMatch) {
        lat = parseFloat(qMatch[1]);
        lng = parseFloat(qMatch[2]);
      } else if (coordMatch) {
        lat = parseFloat(coordMatch[1]);
        lng = parseFloat(coordMatch[2]);
      }

      if (lat !== null && lng !== null) {
        initLeafletMap(type, lat, lng);
        setPointCoordinates(type, lat, lng, false);
      } else if (val.includes('maps') || val.includes('goo.gl')) {
        const feedbackEl = document.getElementById(`${type === 'new' ? 'new-cust' : 'inline-addr'}-map-feedback`);
        feedbackEl.className = 'text-[11px] font-bold text-amber-700 flex items-center gap-1.5';
        const isTe = (currentLang === 'te');
        const mapsRecordedMsg = isTe
          ? 'మ్యాప్స్ లింక్ నమోదైంది. డ్రైవర్ ఈ లొకేషన్ ఆధారంగా వస్తారు.'
          : 'Maps link recorded. Driver will navigate via share pin.';
        feedbackEl.innerHTML = `<span>📍</span> <span>${mapsRecordedMsg}</span>`;
      }
    }

    async function saveInlineAddress() {
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      const label = document.getElementById('inline-addr-label').value.trim() || 'Home';
      const street = document.getElementById('inline-addr-street').value.trim();
      const landmark = document.getElementById('inline-addr-landmark').value.trim();
      const city = document.querySelector('input[name="inline_addr_city"]:checked')?.value || 'Hanamkonda';
      const lat = parseFloat(document.getElementById('inline-addr-lat').value) || null;
      const lng = parseFloat(document.getElementById('inline-addr-lng').value) || null;

      if (!street) {
        alert(t.street_required_alert);
        return;
      }

      if (lat !== null && lng !== null) {
        const check = checkGeofence(lat, lng);
        if (!check.valid) {
          alert(check.error);
          return;
        }
      }

      const phone = customerProfile.phone_number || customerProfile.phone || '';
      const fullName = customerProfile.full_name || '';

      try {
        const resp = await fetch('api/customer-lookup.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'register',
            phone_number: phone,
            full_name: fullName,
            label: label,
            delivery_address: street,
            landmark: landmark,
            region: city,
            latitude: lat,
            longitude: lng,
            is_default: 1
          })
        });
        const data = await resp.json();
        if (data.success && data.customer) {
          customerProfile = data.customer;
          selectedAddressId = data.customer.active_address?.id || (data.customer.addresses?.[0]?.id);
        } else {
          // Fallback local creation
          const newId = Date.now();
          const newAddr = {
            id: newId,
            label: label,
            delivery_address: street,
            landmark: landmark,
            region: city,
            latitude: lat,
            longitude: lng,
            is_default: true
          };
          if (!customerProfile.addresses) customerProfile.addresses = [];
          customerProfile.addresses.push(newAddr);
          customerProfile.active_address = newAddr;
          selectedAddressId = newId;
        }
      } catch (e) {
        const newId = Date.now();
        const newAddr = {
          id: newId,
          label: label,
          delivery_address: street,
          landmark: landmark,
          region: city,
          latitude: lat,
          longitude: lng,
          is_default: true
        };
        if (!customerProfile.addresses) customerProfile.addresses = [];
        customerProfile.addresses.push(newAddr);
        customerProfile.active_address = newAddr;
        selectedAddressId = newId;
      }

      saveProfilePhone();
      localStorage.setItem('ps_cust_profile', JSON.stringify(customerProfile));
      localStorage.setItem('ps_saved_profile', JSON.stringify(customerProfile));

      document.getElementById('inline-addr-label').value = '';
      document.getElementById('inline-addr-street').value = '';
      document.getElementById('inline-addr-landmark').value = '';
      document.getElementById('inline-addr-lat').value = '';
      document.getElementById('inline-addr-lng').value = '';
      toggleNewAddressForm(false);

      if (city !== currentRegion) {
        switchRegion(city);
      }

      renderSavedAddresses();
    }

    function showNewCustomerUI(phone) {
      document.getElementById('onboarding-step-1').classList.add('hidden');
      document.getElementById('onboarding-returning-card').classList.add('hidden');
      document.getElementById('onboarding-confirmed-strip').classList.add('hidden');
      const newCard = document.getElementById('onboarding-new-card');
      newCard.classList.remove('hidden');

      document.getElementById('cust-phone').value = phone;
    }

    async function saveNewCustomerAndProceed() {
      const name = document.getElementById('new-cust-name').value.trim();
      const addr = document.getElementById('new-cust-address').value.trim();
      const landmark = document.getElementById('new-cust-landmark').value.trim();
      const locality = document.querySelector('input[name="new_locality"]:checked')?.value || 'Hanamkonda';
      const phone = document.getElementById('onboarding-phone').value.trim() || document.getElementById('cust-phone').value.trim();
      const lat = parseFloat(document.getElementById('new-cust-lat').value) || null;
      const lng = parseFloat(document.getElementById('new-cust-lng').value) || null;

      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

      if (!name) {
        alert(t.name_required_alert);
        return;
      }
      if (!addr) {
        alert(t.address_required_alert);
        return;
      }

      if (lat !== null && lng !== null) {
        const check = checkGeofence(lat, lng);
        if (!check.valid) {
          alert(check.error);
          return;
        }
      }

      try {
        const resp = await fetch('api/customer-lookup.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'register',
            phone_number: phone,
            full_name: name,
            delivery_address: addr,
            landmark: landmark,
            region: locality,
            latitude: lat,
            longitude: lng,
            label: 'Home',
            is_default: 1
          })
        });
        const data = await resp.json();
        if (data.success && data.customer) {
          customerProfile = data.customer;
          selectedAddressId = data.customer.active_address?.id || (data.customer.addresses?.[0]?.id);
        } else {
          const addressObj = {
            id: Date.now(),
            label: 'Home',
            delivery_address: addr,
            landmark: landmark,
            region: locality,
            latitude: lat,
            longitude: lng,
            is_default: true
          };
          customerProfile = {
            full_name: name,
            phone_number: phone,
            delivery_address: addr,
            landmark: landmark,
            region: locality,
            latitude: lat,
            longitude: lng,
            active_address: addressObj,
            addresses: [addressObj]
          };
          selectedAddressId = addressObj.id;
        }
      } catch (err) {
        const addressObj = {
          id: Date.now(),
          label: 'Home',
          delivery_address: addr,
          landmark: landmark,
          region: locality,
          latitude: lat,
          longitude: lng,
          is_default: true
        };
        customerProfile = {
          full_name: name,
          phone_number: phone,
          delivery_address: addr,
          landmark: landmark,
          region: locality,
          latitude: lat,
          longitude: lng,
          active_address: addressObj,
          addresses: [addressObj]
        };
        selectedAddressId = addressObj.id;
      }

      saveProfilePhone();
      localStorage.setItem('ps_cust_profile', JSON.stringify(customerProfile));
      localStorage.setItem('ps_saved_profile', JSON.stringify(customerProfile));

      if (locality !== currentRegion) {
        switchRegion(locality);
      }

      confirmAddressAndProceed(true);
    }

    function confirmAddressAndProceed(shouldScroll = true) {
      if (!customerProfile) return;
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

      const activeAddr = (customerProfile.addresses || []).find(a => a.id === selectedAddressId) || customerProfile.active_address || customerProfile.addresses?.[0];
      if (!activeAddr) {
        alert(t.select_address_alert);
        return;
      }

      customerProfile.active_address = activeAddr;
      customerProfile.delivery_address = activeAddr.delivery_address;
      customerProfile.landmark = activeAddr.landmark;
      customerProfile.region = activeAddr.region;
      customerProfile.latitude = activeAddr.latitude;
      customerProfile.longitude = activeAddr.longitude;
      saveProfilePhone();
      localStorage.setItem('ps_cust_profile', JSON.stringify(customerProfile));
      localStorage.setItem('ps_saved_profile', JSON.stringify(customerProfile));

      // Sync form & drawer fields
      document.getElementById('cust-name').value = customerProfile.full_name || '';
      document.getElementById('cust-phone').value = customerProfile.phone_number || customerProfile.phone || '';
      document.getElementById('cust-address').value = activeAddr.delivery_address || '';
      document.getElementById('cust-landmark').value = activeAddr.landmark || '';
      document.getElementById('cust-region').value = activeAddr.region || currentRegion;
      document.getElementById('cust-lat').value = activeAddr.latitude || '';
      document.getElementById('cust-lng').value = activeAddr.longitude || '';

      const displayRegion = activeAddr.region === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
      const gpsBadge = (activeAddr.latitude && activeAddr.longitude) ? ` <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-sky-100 text-sky-800 border border-sky-200">${t.gps_pinned_badge}</span>` : '';

      // Update confirmed address strip
      document.getElementById('confirmed-strip-name').textContent = customerProfile.full_name || '';
      document.getElementById('confirmed-strip-address').innerHTML = `${activeAddr.delivery_address}${activeAddr.landmark ? ' (' + activeAddr.landmark + ')' : ''} &bull; ${displayRegion}${gpsBadge}`;

      // Update drawer address display
      document.getElementById('drawer-summary-name').textContent = customerProfile.full_name || '';
      document.getElementById('drawer-summary-address').innerHTML = `${activeAddr.delivery_address}${activeAddr.landmark ? ' (' + activeAddr.landmark + ')' : ''} &bull; ${displayRegion}${gpsBadge}`;
      document.getElementById('drawer-summary-phone').textContent = `+91 ${customerProfile.phone_number || customerProfile.phone || ''}`;

      // Switch view: hide cards, show strip
      document.getElementById('onboarding-step-1').classList.add('hidden');
      document.getElementById('onboarding-returning-card').classList.add('hidden');
      document.getElementById('onboarding-new-card').classList.add('hidden');
      document.getElementById('onboarding-confirmed-strip').classList.remove('hidden');

      // Auto-lock delivery region UI to customer's confirmed address
      document.getElementById('locality-pills')?.classList.add('hidden');
      document.getElementById('locked-region-indicator')?.classList.remove('hidden');
      const lockedRegionEl = document.getElementById('val-locked-region-name');
      if (lockedRegionEl) lockedRegionEl.textContent = displayRegion;
      const titleStep1El = document.getElementById('title-step1');
      if (titleStep1El) titleStep1El.textContent = t.title_schedule_locked || (currentLang === 'te' ? 'మీ డెలివరీ షెడ్యూల్' : 'Your Delivery Schedule');

      // Reveal the vegetable catalog & batch selection section
      document.getElementById('catalog-locked-card')?.classList.add('hidden');
      document.getElementById('storefront-catalog-section')?.classList.remove('hidden');

      if (activeAddr.region && activeAddr.region !== currentRegion) {
        switchRegion(activeAddr.region);
      }

      if (shouldScroll) {
        const catalogEl = document.getElementById('vegetable-catalog');
        catalogEl?.scrollIntoView({ behavior: 'smooth' });
      }
    }

    function changeActiveAddress() {
      document.getElementById('onboarding-confirmed-strip').classList.add('hidden');

      // Restore region selector toggle pills for address change/guest view
      document.getElementById('locality-pills')?.classList.remove('hidden');
      document.getElementById('locked-region-indicator')?.classList.add('hidden');
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      const titleStep1El = document.getElementById('title-step1');
      if (titleStep1El) titleStep1El.textContent = t.title_delivery_batch;

      if (customerProfile && (customerProfile.phone_number || customerProfile.phone)) {
        showReturningCustomerUI(customerProfile);
      } else {
        document.getElementById('onboarding-step-1').classList.remove('hidden');
      }
      document.getElementById('onboarding-section')?.scrollIntoView({ behavior: 'smooth' });
    }

    function resetOnboardingPhone() {
      localStorage.removeItem('ps_cust_profile');
      localStorage.removeItem('ps_saved_profile');
      localStorage.removeItem('ps_customer_phone');
      customerProfile = null;
      selectedAddressId = null;
      document.getElementById('onboarding-phone').value = '';
      document.getElementById('onboarding-returning-card').classList.add('hidden');
      document.getElementById('onboarding-new-card').classList.add('hidden');
      document.getElementById('onboarding-confirmed-strip').classList.add('hidden');
      document.getElementById('onboarding-step-1').classList.remove('hidden');
      document.getElementById('btn-phone-continue')?.classList.add('hidden');

      // Restore region selector toggle pills
      document.getElementById('locality-pills')?.classList.remove('hidden');
      document.getElementById('locked-region-indicator')?.classList.add('hidden');
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      const titleStep1El = document.getElementById('title-step1');
      if (titleStep1El) titleStep1El.textContent = t.title_delivery_batch;

      // Lock catalog until new phone & address check-in
      document.getElementById('storefront-catalog-section')?.classList.add('hidden');
      document.getElementById('catalog-locked-card')?.classList.remove('hidden');
    }

    function editAddressFromDrawer() {
      closeCheckoutDrawer();
      changeActiveAddress();
    }

    // --------------------------------------------------------------------------
    // Locality Switcher & Batch Synchronization
    // --------------------------------------------------------------------------
    async function switchRegion(region) {
      currentRegion = region;
      document.getElementById('cust-region').value = region;

      const pillH = document.getElementById('pill-hanamkonda');
      const pillW = document.getElementById('pill-warangal');

      if (region === 'Hanamkonda') {
        pillH.className = 'btn py-3 px-3 rounded-xl font-extrabold text-sm transition border-2 flex flex-col items-center justify-center gap-0.5 border-emerald-600 bg-emerald-50/70 text-emerald-900';
        pillW.className = 'btn py-3 px-3 rounded-xl font-extrabold text-sm transition border-2 flex flex-col items-center justify-center gap-0.5 border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
      } else {
        pillW.className = 'btn py-3 px-3 rounded-xl font-extrabold text-sm transition border-2 flex flex-col items-center justify-center gap-0.5 border-emerald-600 bg-emerald-50/70 text-emerald-900';
        pillH.className = 'btn py-3 px-3 rounded-xl font-extrabold text-sm transition border-2 flex flex-col items-center justify-center gap-0.5 border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
      }

      // Fetch dynamic catalog and batch capacity for this region
      try {
        const resp = await fetch(`api/checkout.php?action=get_run_catalog&region=${region}`);
        const data = await resp.json();
        if (data.success && data.schedule) {
          activeSchedule = data.schedule;
          activeCatalog = data.catalog || [];
          updateScheduleHeaderUI();
          renderCatalogCards();
        }
      } catch (e) {
        console.error('Failed to load batch catalog for', region, e);
      }
    }

    function syncRegionFromDrawer(region) {
      if (region !== currentRegion) {
        switchRegion(region);
      }
    }

    function updateScheduleHeaderUI() {
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      const isTe = (currentLang === 'te');
      const badge = document.getElementById('run-status-badge');
      const isOpen = (activeSchedule && (activeSchedule.status_mode === 'OPEN' || activeSchedule.is_ordering_open == 1));

      if (isOpen) {
        badge.className = 'card-badge badge-emerald';
        badge.textContent = t.bookings_open;
      } else {
        badge.className = 'card-badge badge-amber';
        badge.textContent = t.bookings_closed;
      }
      const regionLabel = currentRegion === 'Warangal' ? t.warangal_label : t.hanamkonda_label;

      // Clean, readable date formatting (e.g. Saturday, 19 Sep 2026)
      let dateText = activeSchedule.delivery_fmt;
      if (!dateText && activeSchedule.delivery_date) {
        try {
          const d = new Date(activeSchedule.delivery_date + 'T00:00:00');
          dateText = d.toLocaleDateString(isTe ? 'te-IN' : 'en-IN', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
        } catch(e) {
          dateText = (activeSchedule.delivery_day ? activeSchedule.delivery_day + ', ' : '') + activeSchedule.delivery_date;
        }
      }
      document.getElementById('val-delivery-date').textContent = dateText || (activeSchedule.delivery_day || 'Scheduled Day');
      document.getElementById('val-cutoff-time').textContent = activeSchedule.cutoff_fmt || 'Daily 7:00 PM';
      document.getElementById('drawer-run-desc').textContent = (activeSchedule.delivery_day || '') + (isTe ? ' బ్యాచ్ · ' : ' Batch · ') + regionLabel;

      // Keep locked region indicator text in sync if active
      const lockedRegionEl = document.getElementById('val-locked-region-name');
      if (lockedRegionEl) lockedRegionEl.textContent = regionLabel;

      // Update 30-order capacity badge
      const booked = parseInt(activeSchedule.booked_orders_count || 0, 10);
      const isFull = (activeSchedule.is_batch_full || booked >= 30);
      const capBadge = document.getElementById('batch-capacity-badge');
      const fullBanner = document.getElementById('batch-full-banner');

      if (capBadge) {
        capBadge.textContent = t.batch_slots_booked(booked);
        capBadge.className = isFull 
          ? 'font-mono text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200' 
          : 'font-mono text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200';
      }

      if (fullBanner) {
        fullBanner.classList.toggle('hidden', !isFull);
      }
    }

    function renderCatalogCards() {
      const container = document.getElementById('vegetables-container');
      const isTe = (currentLang === 'te');
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      document.getElementById('catalog-count-label').textContent = t.varieties_label(activeCatalog.length);

      if (!activeCatalog.length) {
        container.innerHTML = `<div class="app-card text-center py-10 text-slate-400 text-sm">${t.catalog_empty}</div>`;
        return;
      }

      container.innerHTML = activeCatalog.map(prod => {
        const pid = parseInt(prod.product_id || prod.id, 10);
        const stock = parseInt(prod.available_half_kg_stock || 0, 10);
        const price = parseFloat(prod.price_per_half_kg || 0);
        const isSoldOut = (stock <= 0);
        const currQty = cart[pid] || 0;
        const mainName = isTe ? (prod.telugu_name || prod.name) : prod.name;
        const imgUrl = (prod.image_path || '').replace(/\.webp$/i, '.svg');

        return `
          <article class="app-card flex items-center justify-between gap-3 ${isSoldOut ? 'opacity-60 bg-slate-50/70' : ''}" data-id="${pid}" data-telugu-name="${prod.telugu_name || ''}" data-english-name="${prod.name}">
            <div class="flex items-center gap-3 min-w-0 flex-1">
              <div class="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                ${imgUrl ? `<img src="${imgUrl}" alt="${prod.name}" class="w-full h-full object-contain" onerror="this.outerHTML='🥬'">` : `<span class="text-2xl">🥬</span>`}
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="font-bold text-sm sm:text-base text-slate-900 leading-snug break-words line-clamp-2 prod-name">${mainName}</h3>
                <div class="text-sm font-extrabold text-emerald-700 mt-0.5 font-mono flex items-baseline gap-1">
                  <span>₹${price.toFixed(2)}</span>
                  <span class="text-xs text-slate-500 font-normal whitespace-nowrap unit-label">${t.unit_per_half_kg}</span>
                </div>
              </div>
            </div>

            <div class="shrink-0 pl-1">
              ${isSoldOut ? `
                <span class="card-badge badge-slate font-bold text-xs py-1.5 px-2.5 whitespace-nowrap sold-out-badge">${t.sold_out}</span>
              ` : `
                <div class="flex items-center bg-slate-100 rounded-xl p-0.5 border border-slate-200">
                  <button 
                    type="button" 
                    class="stepper-btn btn-minus bg-white hover:bg-slate-50 text-slate-800 rounded-lg shadow-xs disabled:opacity-40" 
                    data-id="${pid}" 
                    onclick="updateItemQty(${pid}, -1)"
                    aria-label="Decrease quantity"
                  >−</button>
                  <span class="w-7 text-center font-mono font-bold text-sm text-slate-900 qty-val-${pid}">${currQty}</span>
                  <button 
                    type="button" 
                    class="stepper-btn btn-plus bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-xs" 
                    data-id="${pid}" 
                    data-stock="${stock}"
                    onclick="updateItemQty(${pid}, 1)"
                    aria-label="Increase quantity"
                  >+</button>
                </div>
              `}
            </div>
          </article>
        `;
      }).join('');
    }

    // --------------------------------------------------------------------------
    // Cart Mechanics
    // --------------------------------------------------------------------------
    function updateItemQty(productId, change) {
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      const isTe = (currentLang === 'te');

      if (activeSchedule && activeSchedule.is_batch_full) {
        alert(t.batch_full_alert);
        return;
      }

      const prod = activeCatalog.find(p => parseInt(p.product_id || p.id, 10) === productId);
      if (!prod) return;

      const current = cart[productId] || 0;
      const stock = parseInt(prod.available_half_kg_stock || 0, 10);
      const next = current + change;

      if (next < 0) return;
      if (next > stock) {
        const prodDisplayName = isTe ? (prod.telugu_name || prod.name) : prod.name;
        alert(t.stock_limit_alert(prodDisplayName, stock));
        return;
      }

      if (next === 0) {
        delete cart[productId];
      } else {
        cart[productId] = next;
      }

      // Update UI Stepper Number
      document.querySelectorAll(`.qty-val-${productId}`).forEach(el => {
        el.textContent = next;
      });

      renderCartUI();
    }

    function renderCartUI() {
      let subtotal = 0.0;
      let totalPackets = 0;
      const drawerList = document.getElementById('drawer-items-list');
      const isTe = (currentLang === 'te');
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

      const itemsHtml = [];

      for (const [pidStr, qty] of Object.entries(cart)) {
        const pid = parseInt(pidStr, 10);
        const prod = activeCatalog.find(p => parseInt(p.product_id || p.id, 10) === pid);
        if (!prod || qty <= 0) continue;

        const price = parseFloat(prod.price_per_half_kg || 0);
        const lineTotal = price * qty;
        subtotal += lineTotal;
        totalPackets += qty;

        const prodDisplayName = isTe ? (prod.telugu_name || prod.name) : prod.name;
        const weightKg = (qty * 0.5).toFixed(1);

        itemsHtml.push(`
          <div class="py-1.5 flex items-center justify-between">
            <div>
              <strong class="text-slate-900">${prodDisplayName}</strong>
              <span class="text-slate-500 font-mono text-[11px] block">${t.cart_item_qty(qty, weightKg)}</span>
            </div>
            <span class="font-mono font-bold text-slate-800">₹${lineTotal.toFixed(2)}</span>
          </div>
        `);
      }

      if (drawerList) {
        drawerList.innerHTML = itemsHtml.length ? itemsHtml.join('') : `<div class="text-slate-400 py-3 text-center italic">${t.cart_empty}</div>`;
      }

      // Calculate delivery fee
      const delivery = (subtotal >= movThreshold || subtotal === 0) ? 0.0 : standardDeliveryFee;
      const grandTotal = subtotal > 0 ? (subtotal + delivery) : 0.0;
      const totalWeightKg = (totalPackets * 0.5).toFixed(1);

      // Update sticky bottom bar
      const bar = document.getElementById('sticky-cart-bar');
      if (totalPackets > 0) {
        bar.classList.remove('hidden');
        document.getElementById('bar-total-amount').textContent = `₹${grandTotal.toFixed(2)}`;
        document.getElementById('bar-total-count').textContent = t.cart_count(totalPackets, totalWeightKg);
      } else {
        bar.classList.add('hidden');
      }

      // Update drawer billing
      document.getElementById('drawer-items-weight').textContent = `${totalWeightKg} kg`;
      document.getElementById('bill-subtotal').textContent = `₹${subtotal.toFixed(2)}`;
      document.getElementById('bill-delivery').textContent = delivery === 0 ? t.delivery_free : `₹${delivery.toFixed(2)}`;
      document.getElementById('bill-total').textContent = `₹${grandTotal.toFixed(2)}`;

      // Disable drawer confirm button if batch full or cart empty
      const btnConfirm = document.getElementById('btn-confirm-order');
      const btnText = document.getElementById('btn-confirm-order-text');
      if (btnConfirm) {
        if (activeSchedule && activeSchedule.is_batch_full) {
          btnConfirm.disabled = true;
          if (btnText) btnText.textContent = t.batch_full_btn;
        } else {
          btnConfirm.disabled = (totalPackets === 0);
          if (btnText) btnText.textContent = t.btn_confirm_order;
        }
      }
    }

    // --------------------------------------------------------------------------
    // Drawer Open / Close
    // --------------------------------------------------------------------------
    function openCheckoutDrawer() {
      // Ensure profile values are populated
      if (customerProfile) {
        if (customerProfile.full_name) document.getElementById('cust-name').value = customerProfile.full_name;
        if (customerProfile.phone_number) document.getElementById('cust-phone').value = customerProfile.phone_number;
        if (customerProfile.delivery_address) document.getElementById('cust-address').value = customerProfile.delivery_address;
        if (customerProfile.landmark) document.getElementById('cust-landmark').value = customerProfile.landmark;
        if (customerProfile.region) document.getElementById('cust-region').value = customerProfile.region;
      }

      document.getElementById('checkout-backdrop').classList.remove('hidden');
      document.getElementById('checkout-drawer').classList.remove('hidden');
    }

    function closeCheckoutDrawer() {
      document.getElementById('checkout-backdrop').classList.add('hidden');
      document.getElementById('checkout-drawer').classList.add('hidden');
    }

    // --------------------------------------------------------------------------
    // Order Submission & WhatsApp Sync
    // --------------------------------------------------------------------------
    async function submitOrder(e) {
      e.preventDefault();
      const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
      const isTe = (currentLang === 'te');

      if (!activeSchedule || !activeSchedule.id) {
        alert(isTe ? 'దయచేసి ఒక డెలివరీ బ్యాచ్ ఎంచుకోండి.' : 'Please select an active delivery batch.');
        return;
      }

      if (activeSchedule.is_batch_full) {
        alert(t.batch_full_alert);
        return;
      }

      const itemsPayload = [];
      const itemLines = [];
      let subtotal = 0;

      for (const [pidStr, qty] of Object.entries(cart)) {
        if (qty > 0) {
          const pid = parseInt(pidStr, 10);
          const prod = activeCatalog.find(p => parseInt(p.product_id || p.id, 10) === pid);
          itemsPayload.push({ product_id: pid, quantity: qty });
          if (prod) {
            const price = parseFloat(prod.price_per_half_kg || 0);
            const lineTot = price * qty;
            subtotal += lineTot;
            const weight = (qty * 0.5).toFixed(1);
            const prodName = isTe ? (prod.telugu_name || prod.name) : prod.name;
            const pktWord = isTe ? 'ప్యాకెట్లు' : 'pkts';
            itemLines.push(`- ${prodName}: ${qty} ${pktWord} (${weight} kg) - ₹${lineTot.toFixed(0)}`);
          }
        }
      }

      if (!itemsPayload.length) {
        alert(t.empty_basket_alert);
        return;
      }

      const form = document.getElementById('checkout-form');
      const formData = new FormData(form);

      const customerData = {
        full_name: formData.get('full_name').toString().trim(),
        phone_number: formData.get('phone_number').toString().trim(),
        delivery_address: formData.get('delivery_address').toString().trim(),
        landmark: formData.get('landmark')?.toString().trim() || null,
        region: formData.get('region').toString().trim(),
        latitude: formData.get('latitude') ? parseFloat(formData.get('latitude').toString()) : null,
        longitude: formData.get('longitude') ? parseFloat(formData.get('longitude').toString()) : null
      };

      // Save profile to localStorage
      saveProfilePhone();
      localStorage.setItem('ps_cust_profile', JSON.stringify(customerData));
      localStorage.setItem('ps_saved_profile', JSON.stringify(customerData));

      const paymentMethod = formData.get('payment_method')?.toString() || 'COD';
      const btn = document.getElementById('btn-confirm-order');
      btn.disabled = true;
      btn.textContent = t.saving_order;

      try {
        const resp = await fetch('api/checkout.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            schedule_id: parseInt(activeSchedule.id, 10),
            customer: customerData,
            items: itemsPayload,
            payment_method: paymentMethod,
            lang: currentLang
          })
        });

        const data = await resp.json();

        if (data.success && data.order_code) {
          const orderCode = data.order_code;
          const deliveryFee = (subtotal >= movThreshold || subtotal === 0) ? 0 : standardDeliveryFee;
          const totalAmount = subtotal + deliveryFee;
          const host = window.location.origin + window.location.pathname.replace('index.php', '');
          const trackUrl = `${host}track.php?code=${orderCode}`;

          // Single-language WhatsApp Order Template
          const payMethodLabel = paymentMethod === 'COD' 
            ? (isTe ? 'క్యాష్ ఆన్ డెలివరీ' : 'Cash on Delivery') 
            : (isTe ? 'ఆన్‌లైన్ UPI' : 'Online UPI');

          const waMessage = isTe 
            ? `🌱 *ప్రకృతి సిరి - ఆర్డర్ నిర్ధారణ*
ఆర్డర్ కోడ్: #${orderCode}
కస్టమర్: ${customerData.full_name}
ఫోన్: ${customerData.phone_number}
డెలివరీ బ్యాచ్: ${activeSchedule.delivery_day || ''}, ${activeSchedule.delivery_date || ''} (${activeSchedule.target_region || currentRegion})
చిరునామా: ${customerData.delivery_address}${customerData.landmark ? ', ' + customerData.landmark : ''}

కూరగాయలు:
${itemLines.join('\n')}

మొత్తం: ₹${totalAmount.toFixed(2)} (${payMethodLabel})
📍 ఆర్డర్ లైవ్ ట్రాకింగ్: ${trackUrl}`
            : `🌱 *Prakruthi Siri - Order Confirmation*
Order Code: #${orderCode}
Customer: ${customerData.full_name}
Phone: ${customerData.phone_number}
Batch: ${activeSchedule.delivery_day || ''}, ${activeSchedule.delivery_date || ''} (${activeSchedule.target_region || currentRegion})
Address: ${customerData.delivery_address}${customerData.landmark ? ', ' + customerData.landmark : ''}

Items:
${itemLines.join('\n')}

Total Amount: ₹${totalAmount.toFixed(2)} (${payMethodLabel})
📍 Track Order: ${trackUrl}`;

          const waUrl = `https://wa.me/${storeWhatsApp.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(waMessage)}`;

          // Clear cart
          cart = {};
          localStorage.removeItem('ps_cart');

          // Automated WhatsApp Dispatch:
          // Navigate to order-success with auto_wa=1 to render receipt and immediately trigger WhatsApp launch
          window.location.href = `order-success.php?code=${encodeURIComponent(orderCode)}&auto_wa=1`;

        } else {
          alert(data.error || data.message || (isTe ? 'ఆర్డర్ చేయడం విఫలమైంది.' : 'Failed to place order.'));
          btn.disabled = false;
          btn.textContent = t.btn_confirm_order;
        }
      } catch (err) {
        alert((isTe ? 'నెట్‌వర్క్ సమస్య: ' : 'Network Error: ') + err.message);
        btn.disabled = false;
        btn.textContent = t.btn_confirm_order;
      }
    }

    // Init Language
    document.getElementById('btn-lang-te').addEventListener('click', () => setCustomerLanguage('te'));
    document.getElementById('btn-lang-en').addEventListener('click', () => setCustomerLanguage('en'));
    setCustomerLanguage(currentLang);

    // Restore returning customer AFTER i18n is applied so labels render correctly
    // If phone is stored, re-fetch fresh profile from API (picks up new addresses, updated default)
    (async () => {
      try {
        const savedPhone = localStorage.getItem('ps_customer_phone');
        if (savedPhone) {
          await performPhoneLookup(savedPhone);
          // performPhoneLookup calls showReturningCustomerUI which shows the card —
          // if the customer has a confirmed active address, skip straight to catalog
          if (customerProfile && customerProfile.active_address) {
            selectedAddressId = customerProfile.active_address.id || customerProfile.addresses?.[0]?.id;
            confirmAddressAndProceed(false);
          }
          return;
        }
        // Fallback: use cached profile if no phone key (e.g. older session)
        const saved = localStorage.getItem('ps_cust_profile') || localStorage.getItem('ps_saved_profile');
        if (saved) {
          customerProfile = JSON.parse(saved);
          if (customerProfile && (customerProfile.phone_number || customerProfile.phone)) {
            const phone = customerProfile.phone_number || customerProfile.phone;
            localStorage.setItem('ps_customer_phone', phone.replace(/\D/g, '').slice(-10));
            selectedAddressId = customerProfile.active_address?.id || customerProfile.addresses?.[0]?.id;
            confirmAddressAndProceed(false);
          }
        }
      } catch(e) {}
    })();
  </script>
</body>
</html>
