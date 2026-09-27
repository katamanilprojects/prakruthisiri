<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Public Batch Traceability & Farm Transparency
 * REQ-TRC-02: Crop journey timeline from 0.75-acre quarter-plot to kitchen.
 * Zero external paid API dependencies.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/FarmService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\FarmService;

$pdo = Database::getInstance()->getConnection();
$farmService = new FarmService($pdo);

$code       = trim((string)($_GET['code'] ?? ''));
$scheduleId = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;

$traceData = null;
if ($code !== '') {
    $traceData = $farmService->getBatchTraceability($code);
} elseif ($scheduleId > 0) {
    $traceData = $farmService->getBatchTraceability($scheduleId);
} else {
    // Default to the latest active schedule
    $schedules = $farmService->getUpcomingSchedules();
    if (!empty($schedules)) {
        $traceData = $farmService->getBatchTraceability((int)$schedules[0]['id']);
    }
}

$schedule       = $traceData['schedule'] ?? null;
$order          = $traceData['order'] ?? null;
$plots          = $traceData['plots'] ?? [];
$harvestedItems = $traceData['harvested_items'] ?? [];

$batchTitle = $schedule 
    ? (!empty($schedule['delivery_day']) ? $schedule['delivery_day'] . ' Batch' : 'Delivery Batch') . ' — ' . date('d M Y', strtotime($schedule['delivery_date'])) . ' (' . ($schedule['target_region'] ?? 'Telangana') . ')'
    : 'Fresh Farm Harvest Batch';
?>
<!DOCTYPE html>
<html lang="te" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Batch Traceability | Prakruthi Siri Organic Farm</title>
  
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
        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl shadow-xs">
          🌱
        </div>
        <div>
          <span class="font-extrabold text-base text-slate-900 tracking-tight leading-none block">Prakruthi Siri</span>
          <span class="text-xs text-slate-500 font-medium block mt-0.5" id="lbl-batch-subtitle">సేంద్రీయ పంట ప్రయాణం</span>
        </div>
      </a>
      <div class="flex items-center gap-3">
        <!-- Language Switcher -->
        <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 select-none text-xs font-bold">
          <button type="button" id="lang-btn-te" class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs">తెలుగు</button>
          <button type="button" id="lang-btn-en" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition">English</button>
        </div>
        <a href="index.php" class="text-xs font-bold text-emerald-700 hover:underline" id="link-storefront">స్టోర్ →</a>
      </div>
    </div>
  </header>

  <main class="flex-1 max-w-xl w-full mx-auto px-4 py-5 space-y-4">
    <!-- Batch Banner -->
    <div class="bg-emerald-800 text-white rounded-2xl p-5 shadow-sm space-y-2 relative overflow-hidden">
      <div class="absolute -right-4 -bottom-4 text-emerald-700 opacity-20 text-8xl font-black select-none">🌿</div>
      <div class="relative z-10">
        <span class="inline-block px-2.5 py-0.5 rounded-full bg-emerald-700/80 border border-emerald-600 text-[11px] font-bold tracking-wide uppercase" id="badge-organic">
          100% సేంద్రీయ వ్యవసాయం
        </span>
        <h1 class="text-lg font-extrabold mt-1.5 leading-snug">
          <?= htmlspecialchars($batchTitle, ENT_QUOTES) ?>
        </h1>
        <?php if ($order): ?>
          <p class="text-xs text-emerald-200 font-mono mt-1">
            Order #<?= htmlspecialchars($order['order_code'], ENT_QUOTES) ?> &bull; Traceability Verified
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Farm & Agronomic Plot Card -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-3">
      <div class="flex items-center gap-2 border-b border-slate-100 pb-2.5">
        <span class="text-lg">🏡</span>
        <h2 class="text-sm font-extrabold text-slate-900" id="hdr-farm-origin">పొలం మూలం &amp; సాగు వివరాలు</h2>
      </div>

      <div class="text-xs text-slate-700 space-y-2">
        <div class="flex justify-between border-b border-slate-50 pb-1.5">
          <span class="text-slate-500 font-semibold" id="lbl-farm-land">రైతు క్షేత్రం:</span>
          <span class="font-bold text-slate-900">3 ఎకరాల సేంద్రీయ భూమి, తెలంగాణ</span>
        </div>

        <?php foreach ($plots as $plot): ?>
          <div class="flex justify-between border-b border-slate-50 pb-1.5">
            <span class="text-slate-500 font-semibold" id="lbl-origin-quarter">ఉత్పత్తి ప్లాట్:</span>
            <span class="font-bold text-emerald-800">
              <?= htmlspecialchars($plot['quarter_name'] ?: 'Quarter ' . $plot['plot_number'], ENT_QUOTES) ?> (0.75 ఎకరం)
            </span>
          </div>
          <?php if (!empty($plot['crop_type'])): ?>
          <div class="flex justify-between border-b border-slate-50 pb-1.5">
            <span class="text-slate-500 font-semibold" id="lbl-crops-grown">సాగు చేసిన పంటలు:</span>
            <span class="font-bold text-slate-900"><?= htmlspecialchars($plot['crop_type'], ENT_QUOTES) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($plot['sown_date'])): ?>
          <div class="flex justify-between border-b border-slate-50 pb-1.5">
            <span class="text-slate-500 font-semibold" id="lbl-planting-date">విత్తిన తేదీ:</span>
            <span class="font-bold text-slate-900"><?= date('d M Y', strtotime($plot['sown_date'])) ?></span>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>

        <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl text-[11px] text-emerald-900 font-medium leading-relaxed">
          💡 <strong id="lbl-staggered-tip">నిరంతర తాజా సాగు:</strong> <span id="lbl-staggered-desc">4 క్వార్టర్లలో 3 నెలల విరామంతో సాగు చేయడం వల్ల ఏడాది పొడవునా రసాయనాలు లేని తాజా కూరగాయలు అందుతాయి.</span>
        </div>
      </div>
    </div>

    <!-- Crop Lifecycle & Milestone Photos -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
      <div class="flex items-center gap-2 border-b border-slate-100 pb-2.5">
        <span class="text-lg">📸</span>
        <h2 class="text-sm font-extrabold text-slate-900" id="hdr-milestones-timeline">పంట అభివృద్ధి దశలు &amp; ఫొటోలు</h2>
      </div>

      <?php
        $allMilestones = [];
        foreach ($plots as $p) {
            if (!empty($p['milestones'])) {
                foreach ($p['milestones'] as $m) {
                    $allMilestones[] = $m;
                }
            }
        }
      ?>

      <?php if (empty($allMilestones)): ?>
        <!-- Default baseline organic practices if no custom photos logged yet -->
        <div class="space-y-4 relative pl-5 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
          <div class="relative space-y-1 bg-slate-50 rounded-xl p-3 border border-slate-200">
            <div class="absolute -left-5 top-3 w-3 h-3 rounded-full bg-emerald-600 border-2 border-white shadow-xs"></div>
            <div class="text-xs font-bold text-slate-900">1. నాటు వేయడం (Sowing)</div>
            <p class="text-[11px] text-slate-600">దేశవాళీ సేంద్రీయ విత్తనాలను బీజామృతం చికిత్సతో నాటడం జరిగింది.</p>
          </div>
          <div class="relative space-y-1 bg-slate-50 rounded-xl p-3 border border-slate-200">
            <div class="absolute -left-5 top-3 w-3 h-3 rounded-full bg-emerald-600 border-2 border-white shadow-xs"></div>
            <div class="text-xs font-bold text-slate-900">2. సహజ పోషకాలు (Organic Nourishment)</div>
            <p class="text-[11px] text-slate-600">డ్రిప్ ద్వారా దేశీ ఆవు పేడ/మూత్రంతో తయారు చేసిన జీవామృతం, వేపనూనె పిచికారీ.</p>
          </div>
          <div class="relative space-y-1 bg-slate-50 rounded-xl p-3 border border-slate-200">
            <div class="absolute -left-5 top-3 w-3 h-3 rounded-full bg-emerald-600 border-2 border-white shadow-xs"></div>
            <div class="text-xs font-bold text-slate-900">3. వేకువజామున కోత (Dawn Harvesting)</div>
            <p class="text-[11px] text-slate-600">డెలివరీ రోజే ఉదయం 5 గంటలకు తాజాగా కోసి, బండిల్స్ గా ప్యాక్ చేయబడింది.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="space-y-4 relative pl-5 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
          <?php foreach ($allMilestones as $m): ?>
            <div class="relative space-y-2 bg-slate-50 rounded-xl p-3 border border-slate-200">
              <div class="absolute -left-5 top-3 w-3 h-3 rounded-full bg-emerald-600 border-2 border-white shadow-xs"></div>
              <div class="flex items-center justify-between text-xs">
                <span class="font-extrabold text-emerald-800 uppercase tracking-wide">
                  <?= htmlspecialchars(str_replace('_', ' ', (string)$m['stage']), ENT_QUOTES) ?>
                </span>
                <span class="text-slate-400 font-medium">
                  <?= date('d M Y', strtotime($m['logged_at'])) ?>
                </span>
              </div>

              <?php if (!empty($m['notes'])): ?>
                <p class="text-xs text-slate-700 font-medium"><?= htmlspecialchars($m['notes'], ENT_QUOTES) ?></p>
              <?php endif; ?>

              <?php if (!empty($m['photo_path'])): ?>
                <div class="rounded-xl overflow-hidden border border-slate-200 h-44 bg-slate-100">
                  <img src="../<?= htmlspecialchars($m['photo_path'], ENT_QUOTES) ?>" alt="Plot milestone photo" class="w-full h-full object-cover">
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Harvested Vegetables in this Batch -->
    <?php if (!empty($harvestedItems)): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-3">
      <div class="flex items-center gap-2 border-b border-slate-100 pb-2.5">
        <span class="text-lg">🥦</span>
        <h2 class="text-sm font-extrabold text-slate-900" id="hdr-harvested-veggies">ఈ బ్యాచ్‌లో కోసిన కూరగాయలు</h2>
      </div>

      <div class="grid grid-cols-2 gap-2 text-xs">
        <?php foreach ($harvestedItems as $item): ?>
          <div class="p-2.5 bg-slate-50 border border-slate-100 rounded-xl flex items-center gap-2">
            <span class="text-base">🌱</span>
            <div class="truncate">
              <div class="font-bold text-slate-900 truncate prod-label"
                   data-telugu="<?= htmlspecialchars($item['telugu_name'] ?: $item['product_name'], ENT_QUOTES) ?>"
                   data-english="<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>">
                <?= htmlspecialchars($item['telugu_name'] ?: $item['product_name'], ENT_QUOTES) ?>
              </div>
              <?php if ((float)$item['harvest_kg'] > 0): ?>
                <div class="text-[10px] text-emerald-700 font-mono font-bold"><?= (float)$item['harvest_kg'] ?> kg harvested</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Educational Video Section (Zero-Cost Embedded Video) -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-3">
      <div class="flex items-center gap-2 border-b border-slate-100 pb-2.5">
        <span class="text-lg">🎬</span>
        <h2 class="text-sm font-extrabold text-slate-900" id="hdr-video-title">సేంద్రీయ వ్యవసాయం &amp; స్వచ్ఛత వీడియో</h2>
      </div>
      <p class="text-xs text-slate-600" id="lbl-video-desc">
        ఎటువంటి రసాయనిక పురుగుమందులు వాడకుండా, ప్రకృతి పద్ధతుల్లో పండించే మా విధానాన్ని చూడండి:
      </p>
      <!-- Free Embedded YouTube Video -->
      <div class="rounded-xl overflow-hidden aspect-video bg-black/90 shadow-xs border border-slate-200">
        <iframe 
          class="w-full h-full" 
          src="https://www.youtube-nocookie.com/embed/nK36k_b00XQ?rel=0" 
          title="Prakruthi Siri Organic Farming" 
          frameborder="0" 
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
          allowfullscreen
        ></iframe>
      </div>
    </div>

    <!-- Return to Store / Track Buttons -->
    <div class="flex flex-col sm:flex-row gap-2.5 pt-2">
      <a href="index.php" class="btn btn-primary flex-1 btn-large text-xs font-bold text-center" id="btn-back-store">
        🛒 ఆర్డర్ చేయండి (Shop Fresh Produce)
      </a>
      <?php if ($order): ?>
        <a href="track.php?code=<?= urlencode($order['order_code']) ?>" class="btn btn-secondary flex-1 btn-large text-xs font-bold text-center text-emerald-800 border-emerald-200" id="btn-track-order">
          📦 ఆర్డర్ ట్రాక్ చేయండి
        </a>
      <?php endif; ?>
    </div>
  </main>

  <script>
    (() => {
      let currentLang = localStorage.getItem('ps_customer_lang') || 'te';

      const BATCH_I18N = {
        te: {
          subtitle: "సేంద్రీయ పంట ప్రయాణం",
          store: "స్టోర్ →",
          organic_badge: "100% సేంద్రీయ వ్యవసాయం",
          farm_origin: "పొలం మూలం & సాగు వివరాలు",
          farm_land: "రైతు క్షేత్రం:",
          origin_quarter: "ఉత్పత్తి ప్లాట్:",
          crops_grown: "సాగు చేసిన పంటలు:",
          planting_date: "విత్తిన తేదీ:",
          staggered_tip: "నిరంతర తాజా సాగు:",
          staggered_desc: "4 క్వార్టర్లలో 3 నెలల విరామంతో సాగు చేయడం వల్ల ఏడాది పొడవునా రసాయనాలు లేని తాజా కూరగాయలు అందుతాయి.",
          milestones_timeline: "పంట అభివృద్ధి దశలు & ఫొటోలు",
          harvested_veggies: "ఈ బ్యాచ్‌లో కోసిన కూరగాయలు",
          video_title: "సేంద్రీయ వ్యవసాయం & స్వచ్ఛత వీడియో",
          video_desc: "ఎటువంటి రసాయనిక పురుగుమందులు వాడకుండా, ప్రకృతి పద్ధతుల్లో పండించే మా విధానాన్ని చూడండి:",
          btn_shop: "🛒 కూరగాయలు ఆర్డర్ చేయండి",
          btn_track: "📦 ఆర్డర్ ట్రాక్ చేయండి",
        },
        en: {
          subtitle: "Organic Crop Journey",
          store: "Store →",
          organic_badge: "100% Organic Farming",
          farm_origin: "Farm Origin & Cultivation Details",
          farm_land: "Farmland Parcel:",
          origin_quarter: "Origin Plot Quarter:",
          crops_grown: "Cultivated Crops:",
          planting_date: "Planting Date:",
          staggered_tip: "Staggered Continuous Harvest:",
          staggered_desc: "Sowing across 4 staggered quarter-plots with a 3-month gap prevents cyclic dry spells and guarantees fresh zero-chemical harvests year-round.",
          milestones_timeline: "Crop Development Stages & Photos",
          harvested_veggies: "Vegetables Harvested in This Batch",
          video_title: "Organic Farming & Radical Transparency",
          video_desc: "Watch how our vegetables are grown without synthetic pesticides and harvested at dawn on delivery day:",
          btn_shop: "🛒 Shop Fresh Produce",
          btn_track: "📦 Track Order",
        }
      };

      function setLanguage(lang) {
        currentLang = lang;
        localStorage.setItem('ps_customer_lang', lang);
        const isTe = (lang === 'te');
        const t = BATCH_I18N[lang] || BATCH_I18N.te;

        const btnTe = document.getElementById('lang-btn-te');
        const btnEn = document.getElementById('lang-btn-en');
        if (btnTe && btnEn) {
          if (isTe) {
            btnTe.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
            btnEn.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
          } else {
            btnEn.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
            btnTe.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
          }
        }

        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

        set('lbl-batch-subtitle', t.subtitle);
        set('link-storefront', t.store);
        set('badge-organic', t.organic_badge);
        set('hdr-farm-origin', t.farm_origin);
        set('lbl-farm-land', t.farm_land);
        set('lbl-origin-quarter', t.origin_quarter);
        set('lbl-crops-grown', t.crops_grown);
        set('lbl-planting-date', t.planting_date);
        set('lbl-staggered-tip', t.staggered_tip);
        set('lbl-staggered-desc', t.staggered_desc);
        set('hdr-milestones-timeline', t.milestones_timeline);
        set('hdr-harvested-veggies', t.harvested_veggies);
        set('hdr-video-title', t.video_title);
        set('lbl-video-desc', t.video_desc);
        set('btn-back-store', t.btn_shop);
        set('btn-track-order', t.btn_track);

        document.querySelectorAll('.prod-label').forEach(el => {
          const tel = el.dataset.telugu || '';
          const eng = el.dataset.english || '';
          el.textContent = isTe ? (tel || eng) : eng;
        });
      }

      document.getElementById('lang-btn-te')?.addEventListener('click', () => setLanguage('te'));
      document.getElementById('lang-btn-en')?.addEventListener('click', () => setLanguage('en'));

      setLanguage(currentLang);
    })();
  </script>
</body>
</html>
