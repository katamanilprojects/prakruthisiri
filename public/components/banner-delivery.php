<?php
declare(strict_types=1);
?>
<!-- SLIM DELIVERY BANNER (1 Compact Card <= 75px Vertical Space) -->
<section class="app-card !p-3 rounded-2xl bg-white border border-slate-200 shadow-xs flex items-center justify-between gap-3 overflow-hidden min-h-[68px] max-h-[75px]" id="delivery-banner-card">
  <div class="flex items-center gap-2.5 min-w-0 flex-1">
    <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center text-lg shrink-0">
      🛵
    </div>
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-1.5 flex-wrap">
        <span class="font-extrabold text-xs sm:text-sm text-slate-900 tracking-tight leading-tight truncate" id="banner-primary-line">
          Delivering <?= htmlspecialchars($activeSchedule['delivery_fmt'] ?? 'Scheduled Day') ?>
        </span>
        <?php if ($activeSchedule && ($activeSchedule['status_mode'] ?? '') === 'OPEN'): ?>
          <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200 shrink-0" id="banner-status-chip">
            <span class="pulse-dot"></span>
            <span id="banner-harvest-status">Harvest Booking Open</span>
          </span>
        <?php endif; ?>
      </div>
      <div class="text-[11px] sm:text-[12px] text-slate-500 font-medium truncate mt-0.5" id="banner-subline">
        Order before <?= htmlspecialchars($activeSchedule['cutoff_fmt'] ?? '7:00 PM') ?> • Free Delivery
      </div>
    </div>
  </div>

  <div class="shrink-0 text-right pl-1">
    <div class="inline-flex items-center gap-1 bg-slate-100/90 border border-slate-200 rounded-xl px-2.5 py-1 text-xs">
      <span class="text-xs">📍</span>
      <span class="font-bold text-slate-900 truncate max-w-[100px] sm:max-w-[140px]" id="banner-address-chip">
        Hanamkonda
      </span>
      <button type="button" onclick="changeActiveAddress()" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 underline ml-0.5" id="banner-change-link">
        Change
      </button>
    </div>
  </div>
</section>

<!-- Locality Toggle Pills (Hidden when address confirmed) -->
<div class="grid grid-cols-2 gap-2 mt-2 hidden" id="locality-pills">
  <button 
    type="button" 
    onclick="switchRegion('Hanamkonda')" 
    id="pill-hanamkonda"
    class="btn py-2 px-3 rounded-xl font-extrabold text-xs sm:text-sm transition border-2 flex items-center justify-center gap-1.5 <?= ($defaultRegion ?? 'Hanamkonda') === 'Hanamkonda' ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"
  >
    <span>🏡</span>
    <span class="pill-name whitespace-nowrap" id="pill-hanamkonda-label">Hanamkonda</span>
  </button>

  <button 
    type="button" 
    onclick="switchRegion('Warangal')" 
    id="pill-warangal"
    class="btn py-2 px-3 rounded-xl font-extrabold text-xs sm:text-sm transition border-2 flex items-center justify-center gap-1.5 <?= ($defaultRegion ?? 'Hanamkonda') === 'Warangal' ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"
  >
    <span>🏢</span>
    <span class="pill-name whitespace-nowrap" id="pill-warangal-label">Warangal</span>
  </button>
</div>

<!-- Batch Full Alert Banner -->
<?php
  $curBooked = (int)($activeSchedule['booked_orders_count'] ?? 0);
  $isFull = (bool)($activeSchedule['is_batch_full'] ?? false);
?>
<div id="batch-full-banner" class="<?= $isFull ? '' : 'hidden' ?> p-2.5 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs font-bold flex items-center gap-2">
  <span>⚠️</span>
  <span id="txt-batch-full-msg">This batch is full. Ordering is disabled.</span>
</div>
