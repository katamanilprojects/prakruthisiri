<?php
declare(strict_types=1);

$adminName  = (string) ($_SESSION['admin_name'] ?? 'Admin');
$activePage = $activePage ?? 'orders';

$navItems = [
    'orders'    => ['title' => 'Orders List',       'icon' => '🛒', 'url' => 'orders.php'],
    'inventory' => ['title' => 'Harvest & Stock',   'icon' => '🥦', 'url' => 'inventory.php'],
    'routes'    => ['title' => 'Driver Route',      'icon' => '🚚', 'url' => 'routes.php'],
    'schedules' => ['title' => 'Batch Calendar',    'icon' => '📅', 'url' => 'schedules.php'],
    'revenue'   => ['title' => 'Accounts & Cash',   'icon' => '💰', 'url' => 'revenue.php'],
    'customers' => ['title' => 'Customers',         'icon' => '👥', 'url' => 'customers.php'],
];
?>
<header class="bg-white border-b border-slate-200 no-print shadow-xs sticky top-0 z-30">
  <div class="max-w-7xl mx-auto px-3 sm:px-6">
    <div class="flex items-center justify-between h-14 border-b border-slate-100">
      <div class="flex items-center gap-2.5">
        <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold text-base shadow-xs">🌿</span>
        <div>
          <span class="font-bold text-slate-900 text-sm tracking-tight block leading-none">Prakruthi Siri</span>
          <span class="text-[10px] sm:text-xs text-slate-500 hidden sm:inline" id="masthead-subtitle">Farm Dispatch &amp; Operations</span>
        </div>
      </div>
      <div class="flex items-center gap-2 sm:gap-3 text-xs whitespace-nowrap">
        <!-- Bilingual Toggle for Admin Portal -->
        <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 select-none text-xs">
          <button type="button" id="admin-lang-en" class="px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-md bg-emerald-600 text-white font-semibold text-xs transition shadow-xs">English</button>
          <button type="button" id="admin-lang-te" class="px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-md text-slate-600 hover:text-slate-900 text-xs font-medium transition">తెలుగు</button>
        </div>
        <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold">
          <span id="masthead-staff-label">Staff:</span> <strong><?= htmlspecialchars($adminName, ENT_QUOTES) ?></strong>
        </span>
        <button type="button" id="btn-pwa-install-admin" onclick="triggerAdminPwaInstall()" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs flex items-center gap-1 cursor-pointer">
          <span>📲</span>
          <span id="masthead-install-label" class="hidden xs:inline sm:inline">Install App</span>
        </button>
        <a href="../public/index.php" target="_blank" class="text-emerald-600 font-semibold hover:text-emerald-700 flex items-center gap-1 text-xs">
          <span id="masthead-storefront-label" class="hidden sm:inline">Storefront</span> &rarr;
        </a>
        <a href="logout.php" id="masthead-logout-label" class="text-red-600 font-semibold hover:text-red-700 text-xs">Sign Out</a>
      </div>
    </div>
    <nav class="flex space-x-1.5 py-2 overflow-x-auto text-xs no-scrollbar">
      <?php foreach ($navItems as $key => $item): ?>
        <a 
          href="<?= $item['url'] ?>" 
          data-nav-key="<?= $key ?>"
          class="px-3 py-1.5 rounded-lg text-xs font-semibold transition shrink-0 flex items-center gap-1.5 <?= $activePage === $key 
            ? 'bg-emerald-600 text-white shadow-xs' 
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>"
        >
          <span><?= $item['icon'] ?></span>
          <span class="nav-item-text"><?= $item['title'] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<script src="assets/js/i18n-admin.js"></script>
<script>
  (function() {
    function setAdminLanguage(lang) {
      localStorage.setItem('ps_admin_lang', lang);
      const isTe = (lang === 'te');
      const btnEn = document.getElementById('admin-lang-en');
      const btnTe = document.getElementById('admin-lang-te');
      if (btnEn && btnTe) {
        if (isTe) {
          btnTe.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-semibold text-xs transition shadow-xs';
          btnEn.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 text-xs font-medium transition';
        } else {
          btnEn.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-semibold text-xs transition shadow-xs';
          btnTe.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 text-xs font-medium transition';
        }
      }

      if (typeof ADMIN_I18N === 'undefined') return;
      const dict = ADMIN_I18N[lang] || ADMIN_I18N.en;

      // Masthead header items
      const sub = document.getElementById('masthead-subtitle');
      if (sub && dict.masthead_subtitle) sub.textContent = dict.masthead_subtitle;

      const staffLbl = document.getElementById('masthead-staff-label');
      if (staffLbl && dict.staff_label) staffLbl.textContent = dict.staff_label;

      const storeLbl = document.getElementById('masthead-storefront-label');
      if (storeLbl && dict.live_storefront) storeLbl.textContent = dict.live_storefront;

      const logoutLbl = document.getElementById('masthead-logout-label');
      if (logoutLbl && dict.exit) logoutLbl.textContent = dict.exit;

      const installLbl = document.getElementById('masthead-install-label');
      if (installLbl && dict.install_app) installLbl.textContent = dict.install_app;

      // Navigation tab links
      document.querySelectorAll('[data-nav-key]').forEach(el => {
        const key = el.getAttribute('data-nav-key');
        const navKey = 'nav_' + key;
        const textSpan = el.querySelector('.nav-item-text') || el;
        if (dict[navKey]) {
          textSpan.textContent = dict[navKey];
        }
      });

      // Any elements on the page with data-i18n
      document.querySelectorAll('[data-i18n]').forEach(el => {
        const k = el.getAttribute('data-i18n');
        if (dict[k]) {
          if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            if (el.hasAttribute('placeholder')) el.setAttribute('placeholder', dict[k]);
          } else {
            el.textContent = dict[k];
          }
        }
      });

      window.dispatchEvent(new CustomEvent('adminLanguageChanged', { detail: { lang, dict } }));
    }

    window.setAdminLanguage = setAdminLanguage;

    function initLang() {
      const savedLang = localStorage.getItem('ps_admin_lang') || 'en';
      document.getElementById('admin-lang-en')?.addEventListener('click', () => setAdminLanguage('en'));
      document.getElementById('admin-lang-te')?.addEventListener('click', () => setAdminLanguage('te'));
      setAdminLanguage(savedLang);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initLang);
    } else {
      initLang();
    }
  })();

  // --------------------------------------------------------------------------
  // Admin PWA Installation Logic
  // --------------------------------------------------------------------------
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js').catch(err => console.error('Admin SW reg error:', err));
    });
  }

  let deferredAdminPrompt = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredAdminPrompt = e;
  });

  function isStandaloneAdmin() {
    return (window.matchMedia('(display-mode: standalone)').matches) || (window.navigator.standalone === true);
  }

  if (isStandaloneAdmin()) {
    document.getElementById('btn-pwa-install-admin')?.classList.add('hidden');
  }

  function triggerAdminPwaInstall() {
    if (deferredAdminPrompt) {
      deferredAdminPrompt.prompt();
      deferredAdminPrompt.userChoice.then((choice) => {
        if (choice.outcome === 'accepted') {
          document.getElementById('btn-pwa-install-admin')?.classList.add('hidden');
        }
        deferredAdminPrompt = null;
      });
    } else {
      const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;
      if (isIos) {
        alert('To install PS Admin on your iPhone/iPad:\n\n1. Tap the Share button 📤 in Safari.\n2. Scroll down & tap "Add to Home Screen" ➕.');
      } else {
        alert('To install PS Admin:\n\nIn Chrome/Edge menu (⋮), select "Install App" or "Add to Home Screen".');
      }
    }
  }
</script>
