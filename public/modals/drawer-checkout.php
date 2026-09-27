<?php
declare(strict_types=1);
?>
<!-- 1-PAGE CHECKOUT BOTTOM DRAWER MODAL -->
<div id="checkout-backdrop" class="app-drawer-backdrop hidden" onclick="closeCheckoutDrawer()"></div>

<div id="checkout-drawer" class="app-drawer-bottom hidden">
  <div class="max-w-lg mx-auto space-y-4">
    
    <!-- Drawer Header -->
    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-lg">🛒</div>
        <div>
          <h3 class="font-extrabold text-base text-slate-900" id="drawer-title"></h3>
          <span class="text-xs text-slate-500 font-medium" id="drawer-run-desc"></span>
        </div>
      </div>
      <button type="button" onclick="closeCheckoutDrawer()" class="w-9 h-9 rounded-xl hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-700 text-2xl font-bold transition">&times;</button>
    </div>

    <!-- Selected Items Summary -->
    <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200 space-y-2">
      <div class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between">
        <span id="drawer-basket-summary"></span>
        <span id="drawer-items-weight" class="font-mono text-slate-500">0 kg</span>
      </div>
      <div id="drawer-items-list" class="text-xs space-y-1 divide-y divide-slate-200/60 max-h-36 overflow-y-auto">
        <!-- Populated dynamically -->
      </div>
    </div>

    <!-- Customer Details Form -->
    <form id="checkout-form" onsubmit="submitOrder(event)" class="space-y-3.5">
      
      <!-- Verified Delivery Address Summary in Drawer -->
      <div id="drawer-address-card" class="bg-emerald-50/80 border border-emerald-200 rounded-2xl p-3.5 text-xs space-y-1.5">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-extrabold uppercase text-emerald-800 tracking-wider" id="lbl-drawer-address"></span>
          <button type="button" onclick="editAddressFromDrawer()" class="text-xs font-bold text-emerald-700 hover:underline" id="btn-drawer-change"></button>
        </div>
        <div class="font-extrabold text-slate-900 text-sm" id="drawer-summary-name">Customer Name</div>
        <div class="text-slate-600 font-medium" id="drawer-summary-address">123 Street, Locality</div>
        <div class="text-slate-500 font-mono text-[11px]" id="drawer-summary-phone">+91 ----------</div>
      </div>

      <!-- Manual Input Fields (Hidden by default when address is confirmed, kept in sync for submission) -->
      <div id="drawer-inputs-container" class="space-y-3 hidden">
        <div>
          <label for="cust-name" class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-name"></label>
          <input 
            type="text" 
            id="cust-name" 
            name="full_name" 
            required 
            class="app-input"
          >
        </div>

        <div>
          <label for="cust-phone" class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-phone"></label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center font-mono font-bold text-slate-400 text-sm">+91</span>
            <input 
              type="tel" 
              id="cust-phone" 
              name="phone_number" 
              maxlength="10" 
              pattern="[0-9]{10}" 
              required 
              placeholder="9876543210" 
              class="app-input pl-12 font-mono font-bold"
            >
          </div>
        </div>

        <div>
          <label for="cust-address" class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-address"></label>
          <input 
            type="text" 
            id="cust-address" 
            name="delivery_address" 
            required 
            class="app-input"
          >
        </div>

        <div>
          <label for="cust-landmark" class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-landmark"></label>
          <input 
            type="text" 
            id="cust-landmark" 
            name="landmark" 
            class="app-input"
          >
        </div>

        <!-- Locality Selector -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-cust-locality"></label>
          <select id="cust-region" name="region" onchange="syncRegionFromDrawer(this.value)" class="app-select">
            <option value="Hanamkonda" id="drawer-opt-hanamkonda"></option>
            <option value="Warangal" id="drawer-opt-warangal"></option>
          </select>
        </div>
        <input type="hidden" id="cust-lat" name="latitude" value="">
        <input type="hidden" id="cust-lng" name="longitude" value="">
      </div>

      <!-- Payment Method Selection -->
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-payment-mode"></label>
        <div class="grid grid-cols-1 gap-2">
          <label class="flex items-center gap-2 p-3 rounded-xl border border-emerald-600 bg-emerald-50/50 cursor-pointer transition">
            <input type="radio" name="payment_method" value="COD" checked class="accent-emerald-600">
            <div>
              <span class="block text-xs font-bold text-slate-900" id="txt-mode-cod"></span>
              <span class="block text-[10px] text-slate-500" id="txt-mode-cod-sub"></span>
            </div>
          </label>
        </div>
      </div>

      <!-- Bill Breakdown Card -->
      <div class="bg-slate-50 rounded-2xl p-3.5 border border-slate-200 space-y-1.5 text-xs">
        <div class="flex justify-between text-slate-600">
          <span id="lbl-bill-subtotal"></span>
          <span class="font-mono font-bold" id="bill-subtotal">₹0.00</span>
        </div>
        <div class="flex justify-between text-slate-600">
          <span id="lbl-bill-delivery"></span>
          <span class="font-mono font-bold" id="bill-delivery">₹0.00</span>
        </div>
        <div class="border-t border-slate-200 pt-1.5 flex justify-between text-sm font-extrabold text-slate-900">
          <span id="lbl-bill-total"></span>
          <span class="font-mono text-emerald-700" id="bill-total">₹0.00</span>
        </div>
      </div>

      <!-- Submit Button -->
      <button 
        type="submit" 
        id="btn-confirm-order" 
        class="btn btn-primary btn-large btn-full text-base font-extrabold shadow-lg"
      >
        <span id="btn-confirm-order-text"></span>
      </button>
    </form>

  </div>
</div>
