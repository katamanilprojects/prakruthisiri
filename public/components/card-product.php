<?php
declare(strict_types=1);

/**
 * Product Item Card Component
 * Layout: Top 50% Image, Bottom 50%: Name -> Price / unit_label -> Count Stepper
 * Expects $p array with product data
 */
$pid = (int)($p['product_id'] ?? $p['id']);
$stock = (int)($p['available_half_kg_stock'] ?? 0);
$price = (float)($p['price_per_half_kg'] ?? 0);
$unitLabel = (string)($p['unit_label'] ?? '0.5 kg');
$isSoldOut = ($stock <= 0);

$rawImg = (string)($p['image_path'] ?? '');
if (file_exists(__DIR__ . '/../../' . $rawImg)) {
    $imgPath = $rawImg;
} else {
    $imgPath = str_replace(['.webp', '.svg'], '.jpeg', $rawImg);
}
?>
<article class="app-card !p-3 rounded-2xl flex flex-col justify-between bg-white border border-slate-200 shadow-xs hover:shadow-md transition-all relative overflow-hidden <?= $isSoldOut ? 'opacity-60 bg-slate-50/80' : '' ?>" data-id="<?= $pid ?>" data-telugu-name="<?= htmlspecialchars($p['telugu_name'] ?? '', ENT_QUOTES) ?>" data-english-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
  
  <!-- 1. TOP HALF: Prominent Vegetable Image Container (Takes ~50% Card Height) -->
  <div class="w-full aspect-[4/3] rounded-xl bg-slate-50 border border-slate-100 p-2 flex items-center justify-center relative shrink-0 overflow-hidden shadow-2xs">
    <?php if ($isSoldOut): ?>
      <span class="absolute top-1.5 right-1.5 z-10 card-badge badge-slate font-bold text-[10px] py-0.5 px-2 whitespace-nowrap shadow-xs">Sold Out</span>
    <?php endif; ?>
    <?php if (!empty($imgPath)): ?>
      <img src="<?= htmlspecialchars($imgPath, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" class="w-full h-full object-contain transition-transform duration-300 hover:scale-105" onerror="this.outerHTML='<span class=\'text-4xl\'>🥬</span>'">
    <?php else: ?>
      <span class="text-4xl">🥬</span>
    <?php endif; ?>
  </div>

  <!-- 2. OTHER HALF: Stacked Name, Price/unit_label, and Count Stepper -->
  <div class="mt-2.5 flex-1 flex flex-col justify-between space-y-2">
    <!-- Top: Name (English + Telugu) -->
    <div>
      <h3 class="font-extrabold text-xs sm:text-sm text-slate-900 leading-snug prod-title prod-name" style="overflow-wrap: normal; word-break: keep-all;">
        <?= htmlspecialchars($p['name'], ENT_QUOTES) ?>
      </h3>
      <div class="text-[11px] font-bold text-emerald-700 mt-0.5 truncate prod-subtitle">
        <?= htmlspecialchars($p['telugu_name'] ?? '', ENT_QUOTES) ?>
      </div>
    </div>

    <!-- Middle: Price / per unit -->
    <div class="flex items-baseline gap-1 pt-1 border-t border-slate-100">
      <span class="font-extrabold text-sm sm:text-base text-slate-900 font-mono">
        ₹<?= number_format($price, 0) ?>
      </span>
      <span class="text-[11px] text-slate-500 font-medium whitespace-nowrap">
        / <?= htmlspecialchars($unitLabel, ENT_QUOTES) ?>
      </span>
    </div>

    <!-- Bottom: Count Stepper -->
    <div class="pt-1 stepper-container-<?= $pid ?>">
      <?php if ($isSoldOut): ?>
        <button type="button" disabled class="w-full py-2 text-[11px] font-bold text-slate-400 bg-slate-100 rounded-xl cursor-not-allowed text-center">
          Sold Out
        </button>
      <?php else: ?>
        <button type="button" onclick="updateItemQty(<?= $pid ?>, 1)" class="btn-add-modern w-full" aria-label="Add item">
          + ADD
        </button>
      <?php endif; ?>
    </div>
  </div>

</article>
