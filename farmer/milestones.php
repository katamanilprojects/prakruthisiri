<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Farmer Portal: Milestone Photo Logging
 * REQ-TRC-01: Camera Photo Uploads Tagged by Plot Stage & Timeline History
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/FarmService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\FarmService;

$pdo = Database::getInstance()->getConnection();
$farmService = new FarmService($pdo);

$plots = $farmService->getPlots();
$selectedPlotId = isset($_GET['plot_id']) ? (int)$_GET['plot_id'] : ($plots[0]['id'] ?? 1);
$currentPlot = $farmService->getPlot($selectedPlotId) ?: ($plots[0] ?? null);
$milestones = $currentPlot ? $farmService->getMilestones((int)$currentPlot['id']) : [];
$farmerName = $_SESSION['farmer_name'] ?? 'Farmer';
?>
<!DOCTYPE html>
<html lang="te" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Crop Milestones | Prakruthi Siri Farmer Portal</title>
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
      <a href="milestones.php" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs shrink-0">
        <span>📸</span>
        <span id="nav-item-milestones">పంట దశలు (ఫొటోలు)</span>
      </a>
      <a href="harvest.php" class="px-3.5 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 text-xs font-bold flex items-center gap-1.5 transition shrink-0">
        <span>🥕</span>
        <span id="nav-item-harvest">కోత అంచనా నమోదు</span>
      </a>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-1 max-w-4xl w-full mx-auto px-4 py-5 space-y-5">
    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
      <div>
        <h1 class="text-lg font-extrabold text-slate-900 tracking-tight" id="hdr-milestones-title">పంట దశ ఫొటోల నమోదు</h1>
        <p class="text-xs text-slate-500 mt-0.5" id="hdr-milestones-sub">మొబైల్ కెమెరాతో పంట దశల ఫొటోలు తీసి రికార్డు చేయండి</p>
      </div>

      <!-- Plot Selector Dropdown -->
      <div>
        <form method="GET" action="milestones.php">
          <select name="plot_id" onchange="this.form.submit()" class="px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-900 shadow-xs outline-none">
            <?php foreach ($plots as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $p['id'] == $selectedPlotId ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['quarter_name'] ?: 'Quarter ' . $p['plot_number'], ENT_QUOTES) ?>
                <?= !empty($p['crop_type']) ? '(' . htmlspecialchars($p['crop_type'], ENT_QUOTES) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <!-- Upload New Milestone Card -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
      <div class="flex items-center gap-2 border-b border-slate-100 pb-2.5">
        <span class="text-lg">📷</span>
        <h2 class="text-sm font-extrabold text-slate-900" id="card-new-milestone-title">కొత్త పంట దశ నమోదు</h2>
      </div>

      <div id="milestone-msg" class="hidden p-3 rounded-xl text-xs font-bold"></div>

      <form id="milestone-form" onsubmit="submitMilestone(event)" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="plot_id" value="<?= (int)$selectedPlotId ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-stage-select">పంట దశ (Cultivation Stage)</label>
            <select name="stage" required class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-600 outline-none">
              <option value="sowing">విత్తనాలు నాటడం (Sowing)</option>
              <option value="fertilizer_application">సేంద్రీయ పోషకాలు / జీవామృతం (Organic Input)</option>
              <option value="flowering">పూత &amp; కాత దశ (Flowering &amp; Setting)</option>
              <option value="harvesting">కూరగాయల కోత (Harvesting)</option>
              <option value="other">పొలం పరిశీలన (Plot Inspection)</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-photo-upload">కెమెరా ఫొటో తీయండి (Camera / Gallery)</label>
            <input 
              type="file" 
              name="photo" 
              accept="image/*" 
              capture="environment"
              onchange="previewPhoto(event)"
              class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer"
            >
          </div>
        </div>

        <!-- Photo Preview Area -->
        <div id="photo-preview-wrap" class="hidden rounded-xl overflow-hidden border border-slate-200 h-36 bg-slate-100 max-w-xs relative">
          <img id="photo-preview" src="#" alt="Preview" class="w-full h-full object-cover">
          <span class="absolute top-1 right-1 bg-black/60 text-white text-[10px] px-2 py-0.5 rounded-lg">Preview</span>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-milestone-notes">గమనికలు (Notes / Inputs Applied)</label>
          <textarea 
            name="notes" 
            rows="2" 
            placeholder="e.g. Jeevamrutham 200L applied via drip, heavy tomato clusters visible, zero pests..."
            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:bg-white focus:border-emerald-600 outline-none"
          ></textarea>
        </div>

        <button 
          type="submit" 
          id="btn-submit-milestone"
          class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-xs transition flex items-center justify-center gap-1.5"
        >
          <span>📸</span>
          <span id="btn-save-text">ఫొటో &amp; దశను సేవ్ చేయండి</span>
        </button>
      </form>
    </div>

    <!-- Timeline of Logged Milestones -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <h2 class="text-sm font-extrabold text-slate-900" id="lbl-history-title">గతంలో నమోదైన దశల చరిత్ర (Timeline)</h2>
        <span class="text-xs font-bold text-slate-500 font-mono"><?= count($milestones) ?> Milestones</span>
      </div>

      <?php if (empty($milestones)): ?>
        <div class="text-center py-8 text-slate-400">
          <span class="text-3xl block mb-2">📷</span>
          <p class="text-xs font-semibold" id="empty-milestones-text">ఈ ప్లాట్ కోసం ఇంతవరకు ఎటువంటి దశలు నమోదు కాలేదు.</p>
        </div>
      <?php else: ?>
        <div class="space-y-4 relative pl-5 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
          <?php foreach ($milestones as $m): ?>
            <div class="relative space-y-1.5 bg-slate-50 rounded-xl p-3 border border-slate-200">
              <div class="absolute -left-5 top-3 w-3 h-3 rounded-full bg-emerald-600 border-2 border-white shadow-xs"></div>
              <div class="flex items-center justify-between text-xs">
                <span class="font-extrabold text-emerald-800 uppercase tracking-wide">
                  <?= htmlspecialchars(str_replace('_', ' ', (string)$m['stage']), ENT_QUOTES) ?>
                </span>
                <span class="text-slate-400 font-medium">
                  <?= date('d M Y, h:i A', strtotime($m['logged_at'])) ?>
                </span>
              </div>

              <?php if (!empty($m['notes'])): ?>
                <p class="text-xs text-slate-700 font-medium"><?= htmlspecialchars($m['notes'], ENT_QUOTES) ?></p>
              <?php endif; ?>

              <?php if (!empty($m['photo_path'])): ?>
                <div class="mt-2 rounded-lg overflow-hidden border border-slate-200 max-w-sm h-40 bg-slate-100">
                  <a href="../<?= htmlspecialchars($m['photo_path'], ENT_QUOTES) ?>" target="_blank">
                    <img src="../<?= htmlspecialchars($m['photo_path'], ENT_QUOTES) ?>" alt="Milestone photo" class="w-full h-full object-cover hover:scale-105 transition duration-200">
                  </a>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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

      set('hdr-milestones-title', t.milestone_title);
      set('hdr-milestones-sub', t.milestone_subtitle);
      set('card-new-milestone-title', isTe ? 'కొత్త పంట దశ నమోదు' : 'Log New Crop Milestone');
      set('lbl-stage-select', t.lbl_milestone_stage);
      set('lbl-photo-upload', t.lbl_capture_photo);
      set('lbl-milestone-notes', t.lbl_milestone_notes);
      set('btn-save-text', t.btn_save_milestone);
      set('lbl-history-title', t.milestones_history);
      set('empty-milestones-text', t.no_milestones);
    }

    document.getElementById('lang-btn-te')?.addEventListener('click', () => setLanguage('te'));
    document.getElementById('lang-btn-en')?.addEventListener('click', () => setLanguage('en'));

    function previewPhoto(event) {
      const input = event.target;
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const preview = document.getElementById('photo-preview');
          preview.src = e.target.result;
          document.getElementById('photo-preview-wrap').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
      }
    }

    async function submitMilestone(e) {
      e.preventDefault();
      const form = document.getElementById('milestone-form');
      const formData = new FormData(form);
      const btn = document.getElementById('btn-submit-milestone');
      const msg = document.getElementById('milestone-msg');

      btn.disabled = true;
      btn.classList.add('opacity-50');

      try {
        const resp = await fetch('api/milestone-api.php', {
          method: 'POST',
          body: formData
        });
        const res = await resp.json();
        if (res.success) {
          msg.className = 'p-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 block';
          msg.textContent = res.message || 'Milestone logged successfully!';
          setTimeout(() => window.location.reload(), 1000);
        } else {
          msg.className = 'p-3 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 block';
          msg.textContent = res.error || 'Failed to log milestone';
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
