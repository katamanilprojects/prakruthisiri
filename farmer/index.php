<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Farmer Portal: 4 Quarter-Plot Dashboard
 * REQ-FARM-01 & REQ-FARM-02: Isolated Plot Management for 3-Acre Organic Farmland
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/FarmService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\FarmService;

$pdo = Database::getInstance()->getConnection();
$farmService = new FarmService($pdo);
$plots = $farmService->getPlots();

$farmerName = $_SESSION['farmer_name'] ?? 'Farmer';
?>
<!DOCTYPE html>
<html lang="te" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Plot Quarters | Prakruthi Siri Farmer Portal</title>
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
      <a href="index.php" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs shrink-0">
        <span>🌱</span>
        <span id="nav-item-plots">ప్లాట్ల వివరాలు</span>
      </a>
      <a href="milestones.php" class="px-3.5 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 text-xs font-bold flex items-center gap-1.5 transition shrink-0">
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
        <h1 class="text-lg font-extrabold text-slate-900 tracking-tight" id="hdr-plots-title">3 ఎకరాల ప్లాట్ క్వార్టర్లు</h1>
        <p class="text-xs text-slate-500 mt-0.5" id="hdr-plots-sub">4 క్వార్టర్ల పంట స్థితి మరియు నిర్వహణ</p>
      </div>
      <div class="text-right">
        <span class="inline-block px-2.5 py-1 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold rounded-lg">
          👨‍🌾 <?= htmlspecialchars($farmerName, ENT_QUOTES) ?>
        </span>
      </div>
    </div>

    <!-- 4 Quarter-Plots Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php foreach ($plots as $plot): ?>
        <?php
          $status = (string)$plot['status'];
          $statusBadgeClass = match($status) {
              'active_harvesting' => 'bg-emerald-600 text-white',
              'flowering'         => 'bg-amber-500 text-white',
              'vegetative'        => 'bg-teal-600 text-white',
              'sown'              => 'bg-blue-600 text-white',
              'fallow'            => 'bg-purple-600 text-white',
              default             => 'bg-slate-500 text-white',
          };
        ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs space-y-3.5 flex flex-col justify-between">
          <div class="space-y-2.5">
            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2.5">
              <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Plot Quarter #<?= (int)$plot['plot_number'] ?></span>
                <h3 class="text-base font-extrabold text-slate-900">
                  <?= htmlspecialchars($plot['quarter_name'] ?: 'Quarter ' . $plot['plot_number'], ENT_QUOTES) ?>
                </h3>
              </div>
              <span class="px-2.5 py-1 rounded-lg text-xs font-bold shadow-xs <?= $statusBadgeClass ?> status-badge" data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>">
                <?= strtoupper(str_replace('_', ' ', $status)) ?>
              </span>
            </div>

            <div class="space-y-1.5 text-xs text-slate-700">
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-semibold" data-i18n="lbl_current_crop">ప్రస్తుత పంట:</span>
                <span class="font-bold text-slate-900"><?= !empty($plot['crop_type']) ? htmlspecialchars($plot['crop_type'], ENT_QUOTES) : '<em class="text-slate-400">నాటలేదు (None)</em>' ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-semibold" data-i18n="lbl_sown_date">విత్తిన తేదీ:</span>
                <span class="font-bold text-slate-900"><?= !empty($plot['sown_date']) ? date('d M Y', strtotime($plot['sown_date'])) : '-' ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-semibold" data-i18n="lbl_milestones_count">నమోదైన దశలు:</span>
                <span class="font-bold text-emerald-700 font-mono"><?= (int)$plot['milestone_count'] ?></span>
              </div>
              <?php if (!empty($plot['notes'])): ?>
                <div class="p-2 bg-slate-50 rounded-lg text-slate-600 text-[11px] border border-slate-100 italic">
                  <?= htmlspecialchars($plot['notes'], ENT_QUOTES) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Latest Photo Preview -->
            <?php if (!empty($plot['latest_photo'])): ?>
              <div class="mt-2 rounded-xl overflow-hidden border border-slate-200 relative h-28 bg-slate-100">
                <img src="../<?= htmlspecialchars($plot['latest_photo'], ENT_QUOTES) ?>" alt="Latest milestone photo" class="w-full h-full object-cover">
                <span class="absolute bottom-1 right-1 px-2 py-0.5 rounded bg-black/60 text-white text-[10px] font-bold backdrop-blur-xs">
                  <?= htmlspecialchars($plot['latest_stage'] ?? 'Stage', ENT_QUOTES) ?>
                </span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Action Buttons -->
          <div class="pt-2 border-t border-slate-100 grid grid-cols-3 gap-1.5 text-center">
            <button 
              type="button" 
              onclick="openEditPlotModal(<?= htmlspecialchars(json_encode($plot), ENT_QUOTES) ?>)"
              class="px-2 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition flex items-center justify-center gap-1"
            >
              <span>✏️</span>
              <span class="btn-edit-text">మార్చండి</span>
            </button>
            <a 
              href="milestones.php?plot_id=<?= (int)$plot['id'] ?>"
              class="px-2 py-2 rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 text-xs font-bold transition flex items-center justify-center gap-1"
            >
              <span>📸</span>
              <span class="btn-photo-text">ఫొటో</span>
            </a>
            <a 
              href="harvest.php?plot_id=<?= (int)$plot['id'] ?>"
              class="px-2 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center justify-center gap-1 shadow-xs"
            >
              <span>🥕</span>
              <span class="btn-yield-text">దిగుబడి</span>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- Edit Plot Modal -->
  <div id="edit-plot-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-5 space-y-4 shadow-xl">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <h3 class="text-base font-extrabold text-slate-900" id="modal-edit-title">ప్లాట్ వివరాలు మార్చండి</h3>
        <button type="button" onclick="closeEditPlotModal()" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center">✕</button>
      </div>

      <form id="edit-plot-form" onsubmit="submitEditPlot(event)" class="space-y-3">
        <input type="hidden" name="plot_id" id="edit-plot-id">

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-modal-quarter">క్వార్టర్ పేరు</label>
          <input type="text" name="quarter_name" id="edit-quarter-name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-modal-crop">ప్రస్తుత పంట (Crop Name)</label>
          <input type="text" name="crop_type" id="edit-crop-type" placeholder="e.g. Country Tomato, Ridge Gourd" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-modal-status">పంట దశ / స్థితి (Status)</label>
          <select name="status" id="edit-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900">
            <option value="land_preparation">భూమి తయారీ (Land Preparation)</option>
            <option value="sown">విత్తనం నాటబడింది (Sown)</option>
            <option value="vegetative">శాకీయ పెరుగుదల (Vegetative Growth)</option>
            <option value="flowering">పూత &amp; కాత (Flowering &amp; Setting)</option>
            <option value="active_harvesting">కోత దశ (Active Harvesting)</option>
            <option value="fallow">విశ్రాంతి దశ (Fallow)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-modal-sown">విత్తిన తేదీ (Sown Date)</label>
          <input type="date" name="sown_date" id="edit-sown-date" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-modal-notes">గమనికలు (Notes)</label>
          <textarea name="notes" id="edit-notes" rows="2" placeholder="e.g. Jeevamrutham applied, drip irrigation operational" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900"></textarea>
        </div>

        <div class="pt-2 flex items-center justify-end gap-2">
          <button type="button" onclick="closeEditPlotModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold">రద్దు (Cancel)</button>
          <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs">సేవ్ చేయండి (Save)</button>
        </div>
      </form>
    </div>
  </div>

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
      set('hdr-plots-title', t.plots_heading);
      set('hdr-plots-sub', t.plots_subtitle);

      // Translate labels inside plot cards
      document.querySelectorAll('[data-i18n]').forEach(el => {
        const k = el.getAttribute('data-i18n');
        if (t[k]) el.textContent = t[k];
      });

      // Translate status badges
      document.querySelectorAll('.status-badge').forEach(el => {
        const st = el.getAttribute('data-status');
        const stKey = 'status_' + st;
        if (t[stKey]) el.textContent = t[stKey];
      });

      // Translate buttons
      document.querySelectorAll('.btn-edit-text').forEach(el => { el.textContent = t.btn_edit_plot || 'Edit'; });
      document.querySelectorAll('.btn-photo-text').forEach(el => { el.textContent = isTe ? 'ఫొటో' : 'Photo'; });
      document.querySelectorAll('.btn-yield-text').forEach(el => { el.textContent = isTe ? 'దిగుబడి' : 'Yield'; });
    }

    document.getElementById('lang-btn-te')?.addEventListener('click', () => setLanguage('te'));
    document.getElementById('lang-btn-en')?.addEventListener('click', () => setLanguage('en'));

    // Modal controls
    function openEditPlotModal(plot) {
      document.getElementById('edit-plot-id').value = plot.id;
      document.getElementById('edit-quarter-name').value = plot.quarter_name || '';
      document.getElementById('edit-crop-type').value = plot.crop_type || '';
      document.getElementById('edit-status').value = plot.status || 'land_preparation';
      document.getElementById('edit-sown-date').value = plot.sown_date || '';
      document.getElementById('edit-notes').value = plot.notes || '';
      document.getElementById('edit-plot-modal').classList.remove('hidden');
    }

    function closeEditPlotModal() {
      document.getElementById('edit-plot-modal').classList.add('hidden');
    }

    async function submitEditPlot(e) {
      e.preventDefault();
      const form = document.getElementById('edit-plot-form');
      const formData = new FormData(form);
      const data = Object.fromEntries(formData.entries());

      try {
        const resp = await fetch('api/plot-api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        const res = await resp.json();
        if (res.success) {
          window.location.reload();
        } else {
          alert('Error: ' + (res.error || 'Failed to update plot'));
        }
      } catch (err) {
        alert('Request failed: ' + err.message);
      }
    }

    setLanguage(currentLang);
  </script>
</body>
</html>
