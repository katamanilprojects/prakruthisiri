<?php
declare(strict_types=1);
?>
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
