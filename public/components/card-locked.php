<?php declare(strict_types=1); ?>

<!-- =========================================================================
     REQ-LOC-01 / REQ-LOC-02: Region Selector Modal + Locked Catalog States
     Three states managed entirely by JS:
       1. #region-modal        — shown on first visit (no region in localStorage)
       2. #catalog-locked-card — shown when selected region has no open window
       3. Hidden               — hidden once catalog is unlocked
     ========================================================================= -->

<!-- STATE 1: 1-Tap Region Selector Modal (first visit) -->
<div id="region-modal" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 pb-6 sm:pb-0">
  <div class="w-full max-w-sm bg-white rounded-3xl shadow-2xl p-6 space-y-5 animate-slide-up">
    <div class="text-center space-y-1">
      <span class="text-4xl block">🌱</span>
      <h2 class="font-extrabold text-lg text-slate-900" id="modal-region-title"></h2>
      <p class="text-xs text-slate-500 leading-relaxed" id="modal-region-desc"></p>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <button
        type="button"
        onclick="selectRegionFromModal('Hanamkonda')"
        class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-slate-200 bg-white hover:border-emerald-500 hover:bg-emerald-50 transition font-bold text-slate-800 text-sm active:scale-95"
        id="modal-btn-hanamkonda"
      >
        <span class="text-2xl">🏙️</span>
        <span id="modal-hanamkonda-label"></span>
      </button>
      <button
        type="button"
        onclick="selectRegionFromModal('Warangal')"
        class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-slate-200 bg-white hover:border-emerald-500 hover:bg-emerald-50 transition font-bold text-slate-800 text-sm active:scale-95"
        id="modal-btn-warangal"
      >
        <span class="text-2xl">🏙️</span>
        <span id="modal-warangal-label"></span>
      </button>
    </div>
    <p class="text-[11px] text-slate-400 text-center" id="modal-region-note"></p>
  </div>
</div>

<!-- STATE 2: Locked / Cross-Region / Window-Closed Card -->
<div id="catalog-locked-card" class="app-card space-y-4 hidden">

  <!-- 2a: Cross-region locked (other region's window is open) -->
  <div id="locked-cross-region" class="hidden text-center space-y-3 py-4">
    <span class="text-4xl block">🔒</span>
    <h3 class="font-extrabold text-slate-900 text-base" id="locked-cross-title"></h3>
    <p class="text-xs text-slate-600 max-w-sm mx-auto leading-relaxed" id="locked-cross-desc"></p>
    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold">
      <span id="locked-cross-opens-label"></span>
    </div>
    <a
      id="locked-cross-wa-link"
      href="#"
      target="_blank"
      rel="noopener"
      class="flex items-center justify-center gap-2 w-full py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm transition shadow-xs"
    >
      <span>💬</span>
      <span id="locked-cross-wa-text"></span>
    </a>
    <button
      type="button"
      onclick="showRegionModal()"
      class="text-xs text-slate-500 underline"
      id="locked-cross-change-btn"
    ></button>
  </div>

  <!-- 2b: Window closed (no open schedule for selected region) -->
  <div id="locked-closed-window" class="hidden text-center space-y-3 py-4">
    <span class="text-4xl block">🥬</span>
    <h3 class="font-extrabold text-slate-900 text-base" id="locked-closed-title"></h3>
    <p class="text-xs text-slate-600 max-w-sm mx-auto leading-relaxed" id="locked-closed-desc"></p>
    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold">
      <span id="locked-closed-badge"></span>
    </div>
    <button
      type="button"
      onclick="showRegionModal()"
      class="text-xs text-slate-500 underline"
      id="locked-closed-change-btn"
    ></button>
  </div>

</div>

<!-- STATE 3: Expansion Lead Card (out-of-boundary address at checkout) -->
<div id="expansion-lead-card" class="app-card hidden space-y-4 border-amber-200 bg-amber-50/60">
  <div class="flex items-start gap-3">
    <span class="text-3xl shrink-0">📍</span>
    <div>
      <h3 class="font-extrabold text-slate-900 text-sm" id="exp-title"></h3>
      <p class="text-xs text-slate-600 mt-1 leading-relaxed" id="exp-desc"></p>
    </div>
  </div>
  <div class="p-3 bg-white rounded-xl border border-amber-200 text-xs text-slate-700 space-y-1">
    <p id="exp-threshold-msg"></p>
  </div>
  <button
    type="button"
    id="btn-join-waitlist"
    onclick="submitExpansionLead()"
    class="btn btn-primary w-full h-11 font-bold text-sm shadow-xs"
  >
    <span id="btn-join-waitlist-text"></span>
  </button>
  <p class="text-[11px] text-slate-400 text-center" id="exp-already-joined" style="display:none"></p>
</div>
