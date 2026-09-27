<?php
declare(strict_types=1);
?>
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
