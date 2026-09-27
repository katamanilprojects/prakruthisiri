<?php
declare(strict_types=1);

/**
 * Admin Tab 2: Field Harvest Requirements Plan
 * Variables in scope: $harvestSummary, $totalHarvestKg, $totalHarvestPackets, $packingOrders
 */

$harvestSummary      = $harvestSummary ?? [];
$totalHarvestKg      = $totalHarvestKg ?? 0.0;
$totalHarvestPackets = $totalHarvestPackets ?? 0;
$packingOrders       = $packingOrders ?? [];
?>
<div class="space-y-4">
  <!-- 3 KPI Cards for Field Harvest -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="stat-card">
      <div class="stat-label">Total Harvest Required</div>
      <div class="stat-number text-emerald-600"><?= number_format($totalHarvestKg, 1) ?> <span class="text-sm font-normal text-slate-500">Kg</span></div>
      <div class="text-xs text-slate-500 mt-1 font-medium">Exact yield needed from farm plots</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total 0.5kg Packets</div>
      <div class="stat-number text-slate-900"><?= number_format($totalHarvestPackets) ?> <span class="text-sm font-normal text-slate-500">packets</span></div>
      <div class="text-xs text-slate-500 mt-1 font-medium">Standard customer units</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Active Customer Orders</div>
      <div class="stat-number text-sky-600"><?= count($packingOrders) ?> <span class="text-sm font-normal text-slate-500">stops</span></div>
      <div class="text-xs text-slate-500 mt-1 font-medium">Booked for this delivery run</div>
    </div>
  </div>

  <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="enterprise-table">
        <thead>
          <tr>
            <th class="w-12 text-center">#</th>
            <th>Vegetable Name</th>
            <th>Telugu Name</th>
            <th>Category</th>
            <th class="text-right">Packets (0.5kg)</th>
            <th class="text-right">Harvest Weight</th>
            <th class="text-right">Customer Orders</th>
            <th class="text-center no-print w-28">Harvest Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($harvestSummary)): ?>
            <tr><td colspan="8" class="text-center py-10 text-slate-400">No active orders placed for this delivery run.</td></tr>
          <?php else: ?>
            <?php foreach ($harvestSummary as $idx => $row): ?>
              <tr>
                <td class="text-center font-mono font-bold text-slate-400"><?= $idx + 1 ?></td>
                <td class="font-bold text-slate-900"><?= htmlspecialchars($row['product_name'], ENT_QUOTES) ?></td>
                <td class="font-medium text-slate-600"><?= htmlspecialchars($row['telugu_name'], ENT_QUOTES) ?></td>
                <td>
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $row['category'] === 'premium' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700' ?>">
                    <?= ucfirst($row['category']) ?>
                  </span>
                </td>
                <td class="text-right font-mono font-bold text-slate-800"><?= $row['total_packets'] ?> pkts</td>
                <td class="text-right font-mono font-extrabold text-emerald-700 text-sm"><?= number_format((float)$row['total_kg'], 1) ?> kg</td>
                <td class="text-right font-mono text-slate-600"><?= $row['orders_count'] ?> baskets</td>
                <td class="text-center no-print">
                  <label class="inline-flex items-center gap-1.5 cursor-pointer select-none text-xs font-semibold text-slate-600">
                    <input type="checkbox" class="rounded border-slate-300 text-emerald-600 w-4 h-4 cursor-pointer">
                    <span>Harvested</span>
                  </label>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($harvestSummary)): ?>
          <tfoot>
            <tr>
              <td colspan="4" class="font-bold uppercase text-xs">Total Field Harvest</td>
              <td class="text-right font-mono font-bold"><?= $totalHarvestPackets ?> pkts</td>
              <td class="text-right font-mono font-extrabold text-emerald-700 text-sm"><?= number_format($totalHarvestKg, 1) ?> kg</td>
              <td class="text-right font-mono font-bold"><?= count($packingOrders) ?> orders</td>
              <td class="no-print"></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
