<?php
declare(strict_types=1);

/**
 * Admin Tab 3: Crate Packing Checklist
 * Variables in scope: $packingOrders, $itemsByOrder
 */

$packingOrders = $packingOrders ?? [];
$itemsByOrder  = $itemsByOrder ?? [];
?>
<div class="space-y-4">
  <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-xs flex items-center justify-between text-xs">
    <div>
      <h3 class="font-bold text-slate-900 text-sm">Crate Packing Checklist</h3>
      <p class="text-slate-500 mt-0.5">Ordered sequentially by Stop #1..N for crate assembly before driver departure.</p>
    </div>
    <span class="px-3 py-1 bg-emerald-50 text-emerald-800 rounded-full font-bold border border-emerald-200">
      <?= count($packingOrders) ?> Total Crates
    </span>
  </div>

  <div class="space-y-3">
    <?php if (empty($packingOrders)): ?>
      <div class="app-card text-center py-10 text-slate-400">
        No orders to pack for this delivery run yet.
      </div>
    <?php else: ?>
      <?php foreach ($packingOrders as $idx => $o): ?>
        <?php 
          $oid = (int)$o['id'];
          $items = $itemsByOrder[$oid] ?? [];
        ?>
        <div class="app-card flex flex-col md:flex-row md:items-start justify-between gap-4 p-4">
          <!-- Left: Stop # and Order / Customer info -->
          <div class="space-y-1.5 md:max-w-xs w-full">
            <div class="flex items-center gap-2">
              <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-mono font-extrabold text-sm shadow-xs">
                #<?= $o['route_sequence_number'] ?? ($idx + 1) ?>
              </span>
              <div>
                <div class="font-mono font-bold text-slate-900"><?= htmlspecialchars($o['order_code'], ENT_QUOTES) ?></div>
                <div class="text-xs font-bold text-slate-800"><?= htmlspecialchars($o['full_name'], ENT_QUOTES) ?></div>
              </div>
            </div>

            <div class="text-xs text-slate-600 pl-10">
              <a href="tel:<?= htmlspecialchars($o['phone_number'], ENT_QUOTES) ?>" class="font-mono text-emerald-600 font-semibold hover:underline">
                📞 <?= htmlspecialchars($o['phone_number'], ENT_QUOTES) ?>
              </a>
              <div class="text-slate-500 mt-0.5"><?= htmlspecialchars($o['delivery_address'], ENT_QUOTES) ?></div>
              <?php if (!empty($o['landmark'])): ?>
                <div class="text-slate-400 text-[11px]">Landmark: <?= htmlspecialchars($o['landmark'], ENT_QUOTES) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Center: Packing Item Checklist -->
          <div class="flex-1 bg-slate-50 rounded-xl p-3 border border-slate-100 space-y-2">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Vegetable Packets to Pack</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
              <?php foreach ($items as $it): ?>
                <label class="flex items-center gap-2 p-1.5 rounded-lg bg-white border border-slate-200 cursor-pointer select-none hover:border-emerald-300">
                  <input type="checkbox" class="rounded border-slate-300 text-emerald-600 w-4 h-4 cursor-pointer focus:ring-emerald-500">
                  <span class="font-semibold text-slate-800"><?= htmlspecialchars($it['name'], ENT_QUOTES) ?> (<?= htmlspecialchars($it['telugu_name'], ENT_QUOTES) ?>)</span>
                  <span class="ml-auto font-mono font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded text-[11px]">
                    × <?= (int)$it['half_kg_quantity'] ?> <?= htmlspecialchars($it['unit_label'] ?? 'pkts', ENT_QUOTES) ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Right: Financial Summary & Verification -->
          <div class="text-right md:w-36 space-y-1">
            <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Order Amount</div>
            <div class="font-mono font-extrabold text-slate-900 text-base">₹<?= number_format((float)$o['total_amount'], 2) ?></div>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $o['payment_method'] === 'COD' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-sky-50 text-sky-800 border border-sky-200' ?>">
              <?= htmlspecialchars($o['payment_method'], ENT_QUOTES) ?>
            </span>
            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($o['payment_status'], ENT_QUOTES) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
