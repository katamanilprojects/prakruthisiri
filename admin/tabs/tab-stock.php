<?php
declare(strict_types=1);

/**
 * Admin Tab 1: Stock & Pricing Input
 * Variables in scope: $products, $activeRun, $activeRunId, $targetDate, $targetRegion
 */

$products     = $products ?? [];
$activeRun    = $activeRun ?? [];
$activeRunId  = $activeRunId ?? 0;
$targetDate   = $targetDate ?? date('Y-m-d');
$targetRegion = $targetRegion ?? 'Hanamkonda';
?>
<div class="space-y-4">
  <div class="bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 rounded-2xl text-xs flex items-center justify-between">
    <div>
      <strong>Isolated Run Stocking:</strong> You are setting vegetables stock for 
      <strong><?= htmlspecialchars(($activeRun['delivery_day'] ?? '') . ', ' . date('d M Y', strtotime($targetDate)) . ' (' . $targetRegion . ')', ENT_QUOTES) ?></strong>.
      Units calculate automatically based on crop package weight (e.g. 500g packets, 200g bunches, or individual pieces).
    </div>
    <span class="text-[11px] font-mono bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-md font-bold">
      Run #<?= $activeRunId ?>
    </span>
  </div>

  <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="enterprise-table">
        <thead>
          <tr>
            <th>Vegetable Name</th>
            <th>Category</th>
            <th class="text-right w-44">Harvest Stock (Kg)</th>
            <th class="text-right w-36">Available Packets</th>
            <th class="text-right w-36">Price / 0.5kg</th>
            <th class="text-center w-28">Live in Store</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <?php
              $packets = (int) $p['available_half_kg_stock'];
              $unitWeight = (float) ($p['unit_weight_kg'] ?? 0.5);
              if ($unitWeight <= 0) $unitWeight = 0.5;
              $kg = isset($p['harvest_kg']) ? (float)$p['harvest_kg'] : ($packets * $unitWeight);
            ?>
            <tr class="crop-row" data-id="<?= (int)($p['product_id'] ?? $p['id']) ?>" data-unit-weight="<?= $unitWeight ?>">
              <td>
                <div class="font-bold text-slate-900"><?= htmlspecialchars($p['name'], ENT_QUOTES) ?></div>
                <div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($p['telugu_name'], ENT_QUOTES) ?></div>
              </td>
              <td>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $p['category'] === 'premium' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700' ?>">
                  <?= ucfirst($p['category']) ?>
                </span>
              </td>
              <td class="text-right">
                <div class="inline-flex items-center gap-1.5">
                  <input 
                    type="number" 
                    step="0.5" 
                    min="0" 
                    value="<?= number_format($kg, 1, '.', '') ?>"
                    class="input-kg compact-input w-24 text-right font-mono font-bold text-sm py-1"
                  >
                  <span class="text-xs font-semibold text-slate-500">kg</span>
                </div>
              </td>
              <td class="text-right font-mono font-bold text-emerald-700">
                <span class="packets-display text-sm"><?= $packets ?></span> <span class="text-[11px] font-normal text-slate-500">pkts</span>
              </td>
              <td class="text-right">
                <div class="inline-flex items-center gap-1">
                  <span class="text-xs font-bold text-slate-400">₹</span>
                  <input 
                    type="number" 
                    step="1.00" 
                    min="1" 
                    value="<?= number_format((float)$p['price_per_half_kg'], 2, '.', '') ?>"
                    class="input-price compact-input w-20 text-right font-mono font-bold text-sm py-1"
                  >
                </div>
              </td>
              <td class="text-center">
                <input 
                  type="checkbox" 
                  class="input-active rounded border-slate-300 text-emerald-600 w-4 h-4 cursor-pointer focus:ring-emerald-500"
                  <?= !empty($p['is_active']) ? 'checked' : '' ?>
                >
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
