<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Farmer Portal: Harvest Yield Declaration
 * REQ-FARM-03: Yield Estimation Input (Kg) Syncing Directly into Run Inventory
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/FarmService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\FarmService;

$pdo = Database::getInstance()->getConnection();
$farmService = new FarmService($pdo);

$schedules = $farmService->getUpcomingSchedules();
$plots     = $farmService->getPlots();

$selectedSchedId = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : ($schedules[0]['id'] ?? 0);
$selectedPlotId  = isset($_GET['plot_id']) ? (int)$_GET['plot_id'] : ($plots[0]['id'] ?? 1);

$products = $selectedSchedId > 0 ? $farmService->getHarvestProducts($selectedSchedId, $selectedPlotId) : [];
$farmerName = $_SESSION['farmer_name'] ?? 'Farmer';
?>
<!DOCTYPE html>
<html lang="te" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Harvest Yield Entry | Prakruthi Siri Farmer Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../public/assets/css/theme.css">
</head>
<body class="min-h-full flex flex-col antialiased text-slate-900 bg-slate-50 pb-16">

  <!-- Header -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
    <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="index.php" class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl shadow-xs">
          🌾
        </a>
        <div>
          <span class="font-extrabold text-base text-slate-900 leading-tight block">Prakruthi Siri</span>
          <span class="text-xs text-slate-500 font-medium block" id="masthead-portal-title">రైతు పోర్టల్</span>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <!-- Language Switcher -->
        <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 select-none text-xs font-bold">
          <button type="button" id="lang-btn-te" class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs">తెలుగు</button>
          <button type="button" id="lang-btn-en" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition">English</button>
        </div>

        <a href="logout.php" class="text-xs font-bold text-rose-600 hover:underline px-2 py-1" id="link-logout">
          లాగ్ అవుట్
        </a>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="max-w-4xl mx-auto px-4 border-t border-slate-100 flex gap-2 py-2 overflow-x-auto">
      <a href="index.php" class="px-3.5 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 text-xs font-bold flex items-center gap-1.5 transition shrink-0">
        <span>🌱</span>
        <span id="nav-item-plots">ప్లాట్ల వివరాలు</span>
      </a>
      <a href="milestones.php" class="px-3.5 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 text-xs font-bold flex items-center gap-1.5 transition shrink-0">
        <span>📸</span>
        <span id="nav-item-milestones">పంట దశలు (ఫొటోలు)</span>
      </a>
      <a href="harvest.php" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs shrink-0">
        <span>🥕</span>
        <span id="nav-item-harvest">కోత అంచనా నమోదు</span>
      </a>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-1 max-w-4xl w-full mx-auto px-4 py-5 space-y-5">
    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
      <div>
        <h1 class="text-lg font-extrabold text-slate-900 tracking-tight" id="hdr-harvest-title">కోత దిగుబడి అంచనా నమోదు</h1>
        <p class="text-xs text-slate-500 mt-0.5" id="hdr-harvest-sub">డెలివరీకి 48 గంటల ముందు కోయగల కిలోలను నమోదు చేయండి</p>
      </div>
      <div class="text-right">
        <span class="inline-block px-2.5 py-1 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold rounded-lg">
          👨‍🌾 <?= htmlspecialchars($farmerName, ENT_QUOTES) ?>
        </span>
      </div>
    </div>

    <!-- Selection Bar: Schedule & Plot -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
      <form method="GET" action="harvest.php" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-sel-batch">డెలివరీ బ్యాచ్ ఎంచుకోండి (Schedule)</label>
          <select name="schedule_id" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 shadow-xs outline-none">
            <?php foreach ($schedules as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $s['id'] == $selectedSchedId ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['target_region'], ENT_QUOTES) ?> — <?= htmlspecialchars($s['delivery_day'], ENT_QUOTES) ?>, <?= date('d M Y', strtotime($s['delivery_date'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-sel-plot">కోత జరిగే ప్లాట్ క్వార్టర్ (Origin Plot)</label>
          <select name="plot_id" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 shadow-xs outline-none">
            <?php foreach ($plots as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $p['id'] == $selectedPlotId ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['quarter_name'] ?: 'Quarter ' . $p['plot_number'], ENT_QUOTES) ?>
                (<?= htmlspecialchars($p['crop_type'] ?: $p['status'], ENT_QUOTES) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>

    <div id="harvest-msg" class="hidden p-3 rounded-xl text-xs font-bold"></div>

    <!-- Harvest Quantities Table -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <h2 class="text-sm font-extrabold text-slate-900" id="table-hdr-title">కూరగాయల కోత పరిమాణాలు</h2>
        <span class="text-xs text-slate-500 font-semibold" id="table-items-count"><?= count($products) ?> items</span>
      </div>

      <form id="harvest-form" onsubmit="submitHarvest(event)" class="space-y-4">
        <input type="hidden" name="schedule_id" value="<?= (int)$selectedSchedId ?>">
        <input type="hidden" name="plot_id" value="<?= (int)$selectedPlotId ?>">

        <div class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto">
          <?php foreach ($products as $idx => $prod): ?>
            <?php
              $pricingUnit = $prod['pricing_unit'] ?? 'half_kg';
              $isWeight = ($pricingUnit === 'half_kg');
              $unitLabel = $isWeight ? 'kg' : $pricingUnit;
            ?>
            <div class="py-3 flex items-center justify-between gap-3">
              <div class="flex items-center gap-2.5 flex-1 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold text-sm shrink-0">
                  🥦
                </div>
                <div class="truncate">
                  <div class="font-extrabold text-xs text-slate-900 truncate prod-name" 
                       data-telugu="<?= htmlspecialchars($prod['telugu_name'] ?: $prod['product_name'], ENT_QUOTES) ?>" 
                       data-english="<?= htmlspecialchars($prod['product_name'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars($prod['telugu_name'] ?: $prod['product_name'], ENT_QUOTES) ?>
                  </div>
                  <div class="text-[11px] text-slate-400 font-medium">
                    <?= htmlspecialchars($prod['unit_label'], ENT_QUOTES) ?>
                  </div>
                </div>
              </div>

              <!-- Input for Harvest Kg / Discrete Qty -->
              <div class="flex items-center gap-2 shrink-0">
                <div class="relative w-24">
                  <input 
                    type="number" 
                    step="<?= $isWeight ? '0.5' : '1' ?>" 
                    min="0" 
                    name="items[<?= $idx ?>][harvest_kg]" 
                    value="<?= (float)$prod['harvest_kg'] > 0 ? (float)$prod['harvest_kg'] : '' ?>" 
                    placeholder="0"
                    data-unit="<?= $pricingUnit ?>"
                    oninput="recalcPack(this, <?= $idx ?>)"
                    class="w-full text-right pr-7 pl-2 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-600 outline-none"
                  >
                  <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 font-bold"><?= $unitLabel ?></span>
                </div>
                <input type="hidden" name="items[<?= $idx ?>][product_id]" value="<?= (int)$prod['product_id'] ?>">

                <!-- Pack count badge -->
                <div class="w-20 text-center">
                  <span id="pack-badge-<?= $idx ?>" class="inline-block px-2 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg text-[11px] font-bold font-mono">
                    <?= (int)$prod['available_half_kg_stock'] ?> pkts
                  </span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <button 
          type="submit" 
          id="btn-submit-harvest"
          class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center justify-center gap-2"
        >
          <span>💾</span>
          <span id="btn-save-harvest-text">స్టోర్ ఇన్వెంటరీకి పంపండి (Publish to Store)</span>
        </button>
      </form>
    </div>
  </main>

  <script src="assets/js/i18n-farmer.js"></script>
  <script>
    let currentLang = localStorage.getItem('ps_farmer_lang') || 'te';

    function setLanguage(lang) {
      currentLang = lang;
      localStorage.setItem('ps_farmer_lang', lang);
      const isTe = (lang === 'te');
      const t = FARMER_I18N[lang] || FARMER_I18N.te;

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

      set('masthead-portal-title', t.portal_title);
      set('nav-item-plots', t.nav_plots);
      set('nav-item-milestones', t.nav_milestones);
      set('nav-item-harvest', t.nav_harvest);
      set('link-logout', t.logout);

      set('hdr-harvest-title', t.harvest_title);
      set('hdr-harvest-sub', t.harvest_subtitle);
      set('lbl-sel-batch', t.lbl_delivery_schedule);
      set('lbl-sel-plot', t.lbl_harvest_plot);
      set('table-hdr-title', isTe ? 'కూరగాయల కోత పరిమాణాలు' : 'Produce Harvest Quantities');
      set('btn-save-harvest-text', t.btn_publish_inventory);

      // Translate product names
      document.querySelectorAll('.prod-name').forEach(el => {
        const tel = el.dataset.telugu || '';
        const eng = el.dataset.english || '';
        el.textContent = isTe ? (tel || eng) : eng;
      });
    }

    document.getElementById('lang-btn-te')?.addEventListener('click', () => setLanguage('te'));
    document.getElementById('lang-btn-en')?.addEventListener('click', () => setLanguage('en'));

    function recalcPack(input, idx) {
      const val = parseFloat(input.value) || 0;
      const unit = input.dataset.unit || 'half_kg';
      let packs = 0;
      if (unit === 'half_kg') {
        packs = Math.floor(val / 0.5);
      } else {
        packs = Math.round(val);
      }
      const badge = document.getElementById('pack-badge-' + idx);
      if (badge) {
        badge.textContent = packs + ' pkts';
      }
    }

    async function submitHarvest(e) {
      e.preventDefault();
      const form = document.getElementById('harvest-form');
      const formData = new FormData(form);
      const btn = document.getElementById('btn-submit-harvest');
      const msg = document.getElementById('harvest-msg');

      const scheduleId = parseInt(formData.get('schedule_id'), 10);
      const plotId     = parseInt(formData.get('plot_id'), 10);

      const items = [];
      const entries = Array.from(formData.entries());
      const itemMap = {};

      for (const [key, val] of entries) {
        const match = key.match(/^items\[(\d+)\]\[(\w+)\]$/);
        if (match) {
          const idx = match[1];
          const field = match[2];
          if (!itemMap[idx]) itemMap[idx] = {};
          itemMap[idx][field] = val;
        }
      }

      for (const k in itemMap) {
        const row = itemMap[k];
        const kg = parseFloat(row.harvest_kg) || 0;
        items.push({
          product_id: parseInt(row.product_id, 10),
          harvest_kg: kg
        });
      }

      btn.disabled = true;
      btn.classList.add('opacity-50');

      try {
        const resp = await fetch('api/harvest-api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            schedule_id: scheduleId,
            plot_id: plotId,
            items: items
          })
        });
        const res = await resp.json();
        if (res.success) {
          msg.className = 'p-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 block';
          msg.textContent = res.message || 'Harvest yield saved and published to inventory!';
          setTimeout(() => {
            msg.classList.add('hidden');
            btn.disabled = false;
            btn.classList.remove('opacity-50');
          }, 2500);
        } else {
          msg.className = 'p-3 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 block';
          msg.textContent = res.error || 'Failed to save harvest yield';
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

    setLanguage(currentLang);
  </script>
</body>
</html>
