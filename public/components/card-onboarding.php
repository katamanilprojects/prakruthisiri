<?php
declare(strict_types=1);
?>
<!-- PHONE-FIRST ONBOARDING CARD -->
<section id="onboarding-section" class="space-y-3">
  
  <!-- Step 1: Mobile Input Card -->
  <div id="onboarding-step-1" class="app-card space-y-2.5">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="card-badge badge-emerald" id="lbl-onboarding-badge"></span>
      </div>
      <span class="text-[11px] text-slate-400 font-mono">+91</span>
    </div>

    <div class="relative">
      <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center font-mono font-bold text-slate-400 text-sm">+91</span>
      <input 
        type="tel" 
        id="onboarding-phone" 
        maxlength="10" 
        pattern="[0-9]{10}" 
        placeholder="9876543210" 
        oninput="handlePhoneInput(this.value)" 
        class="app-input pl-12 font-mono font-bold text-base h-12"
      >
      <button 
        type="button" 
        id="btn-phone-continue" 
        onclick="checkPhoneManual()"
        class="absolute right-1.5 top-1.5 bottom-1.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs hidden"
      >
        <span id="btn-phone-continue-label"></span>
      </button>
    </div>
    <p class="text-[11px] text-slate-500 leading-normal" id="txt-onboarding-hint"></p>
  </div>

  <!-- Step 2A: Returning Customer Multi-Address Selector Card -->
  <div id="onboarding-returning-card" class="hidden app-card space-y-3.5 bg-emerald-50/60 border border-emerald-200">
    <div class="flex items-start justify-between gap-2">
      <div>
        <div class="text-[11px] uppercase font-extrabold tracking-wider text-emerald-800" id="returning-badge"></div>
        <h3 class="font-extrabold text-base text-slate-900 mt-0.5" id="returning-welcome-title"></h3>
        <span class="text-xs text-slate-500 font-mono font-bold" id="returning-phone-display">+91 ----------</span>
      </div>
      <button type="button" onclick="resetOnboardingPhone()" class="text-xs font-bold text-slate-500 hover:text-slate-800 underline" id="btn-change-mobile"></button>
    </div>

    <!-- Saved Addresses Radios -->
    <div class="space-y-2">
      <label class="block text-xs font-extrabold text-slate-700" id="lbl-select-addr"></label>
      <div id="saved-addresses-container" class="space-y-2">
        <!-- Dynamically injected address radio cards -->
      </div>
    </div>

    <!-- Inline Form to Add a New Address -->
    <div id="inline-new-addr-form" class="hidden p-3 bg-white rounded-xl border border-slate-200 space-y-2.5 text-xs">
      <div class="flex items-center justify-between">
        <span class="font-bold text-slate-800" id="inline-addr-form-title"></span>
        <button type="button" onclick="toggleNewAddressForm(false)" class="text-slate-400 hover:text-slate-700 text-base font-bold">&times;</button>
      </div>
      <div>
        <label class="block text-slate-600 font-semibold mb-1" id="lbl-inline-addr-label"></label>
        <input type="text" id="inline-addr-label" class="app-input h-9 text-xs">
      </div>
      <div>
        <label class="block text-slate-600 font-semibold mb-1" id="lbl-inline-addr-street"></label>
        <input type="text" id="inline-addr-street" class="app-input h-9 text-xs">
      </div>
      <div>
        <label class="block text-slate-600 font-semibold mb-1" id="lbl-inline-addr-landmark"></label>
        <input type="text" id="inline-addr-landmark" class="app-input h-9 text-xs">
      </div>

      <!-- Doorstep Map Location Controls (Inline) -->
      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
        <div class="flex items-center justify-between">
          <span class="font-bold text-slate-800 text-[11px] block" id="lbl-gps-section-inline"></span>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button 
            type="button" 
            onclick="detectUserGps('inline')" 
            id="btn-gps-inline" 
            class="btn py-1.5 px-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg font-bold text-xs flex items-center justify-center gap-1 transition"
          >
            <span id="txt-gps-inline"></span>
          </button>
          <button 
            type="button" 
            onclick="toggleLeafletMap('inline')" 
            id="btn-map-toggle-inline" 
            class="btn py-1.5 px-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg font-bold text-xs flex items-center justify-center gap-1 transition"
          >
            <span id="txt-pin-map-inline"></span>
          </button>
        </div>
        <div>
          <input 
            type="text" 
            id="inline-addr-map-link" 
            oninput="handlePasteMapsLink('inline', this.value)" 
            class="app-input h-8 text-[11px] font-mono"
          >
        </div>
        <input type="hidden" id="inline-addr-lat" value="">
        <input type="hidden" id="inline-addr-lng" value="">
        <div id="inline-addr-map-feedback" class="text-[10px] font-medium text-slate-500 flex items-center gap-1">
          <span>💡</span>
          <span id="txt-map-hint-inline"></span>
        </div>
        <div id="inline-addr-map-wrap" class="hidden">
          <div id="inline-addr-map" class="w-full h-40 rounded-xl border border-slate-200 overflow-hidden shadow-inner z-0"></div>
          <p class="text-[9px] text-slate-400 mt-1 text-center" id="txt-map-drag-inline"></p>
        </div>
      </div>

      <div>
        <label class="block text-slate-600 font-semibold mb-1" id="lbl-inline-addr-city"></label>
        <div class="grid grid-cols-2 gap-2">
          <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-white cursor-pointer font-bold text-slate-800 hover:border-emerald-500 text-xs">
            <input type="radio" name="inline_addr_city" value="Hanamkonda" checked class="accent-emerald-600">
            <span id="inline-hanamkonda-label"></span>
          </label>
          <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-white cursor-pointer font-bold text-slate-800 hover:border-emerald-500 text-xs">
            <input type="radio" name="inline_addr_city" value="Warangal" class="accent-emerald-600">
            <span id="inline-warangal-label"></span>
          </label>
        </div>
      </div>
      <button type="button" onclick="saveInlineAddress()" class="btn btn-primary w-full h-9 text-xs font-bold shadow-xs" id="btn-save-inline-address"></button>
    </div>

    <!-- Address Action Buttons -->
    <div class="flex flex-col sm:flex-row gap-2 pt-1">
      <button type="button" onclick="confirmAddressAndProceed()" class="btn btn-primary h-11 min-h-[44px] flex-1 font-bold text-xs shadow-xs">
        <span id="btn-proceed-veg"></span>
      </button>
      <button type="button" id="btn-add-addr-toggle" onclick="toggleNewAddressForm(true)" class="btn btn-secondary h-11 min-h-[44px] font-semibold text-xs text-slate-700">
        <span id="btn-add-new-addr-text"></span>
      </button>
    </div>
  </div>

  <!-- Step 2B: New Customer Onboarding Card -->
  <div id="onboarding-new-card" class="hidden app-card space-y-3 bg-slate-50 border border-slate-200">
    <div class="flex items-center justify-between">
      <h3 class="font-bold text-sm text-slate-900" id="new-cust-header"></h3>
      <span class="text-[10px] font-bold text-emerald-800 uppercase bg-emerald-100/70 px-2.5 py-0.5 rounded-full border border-emerald-200" id="new-cust-badge"></span>
    </div>
    <div class="space-y-2.5 text-xs">
      <div>
        <label class="block font-bold text-slate-700 mb-1" id="lbl-new-name"></label>
        <input type="text" id="new-cust-name" class="app-input">
      </div>
      <div>
        <label class="block font-bold text-slate-700 mb-1" id="lbl-new-address"></label>
        <input type="text" id="new-cust-address" class="app-input">
      </div>
      <div>
        <label class="block font-bold text-slate-700 mb-1" id="lbl-new-landmark"></label>
        <input type="text" id="new-cust-landmark" class="app-input">
      </div>

      <!-- Doorstep Map Location Controls (New Customer) -->
      <div class="p-3 bg-white rounded-xl border border-slate-200 space-y-2.5">
        <div>
          <span class="font-bold text-slate-800 text-xs block" id="lbl-gps-section-new"></span>
          <span class="text-[11px] text-slate-500 block" id="lbl-gps-section-new-desc"></span>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button 
            type="button" 
            onclick="detectUserGps('new')" 
            id="btn-gps-new" 
            class="btn py-2 px-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition"
          >
            <span id="txt-gps-new"></span>
          </button>
          <button 
            type="button" 
            onclick="toggleLeafletMap('new')" 
            id="btn-map-toggle-new" 
            class="btn py-2 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition"
          >
            <span id="txt-pin-map-new"></span>
          </button>
        </div>
        <div>
          <input 
            type="text" 
            id="new-cust-map-link" 
            oninput="handlePasteMapsLink('new', this.value)" 
            class="app-input h-9 text-xs font-mono"
          >
        </div>
        <input type="hidden" id="new-cust-lat" value="">
        <input type="hidden" id="new-cust-lng" value="">
        <div id="new-cust-map-feedback" class="text-[11px] font-medium text-slate-500 flex items-center gap-1.5">
          <span>💡</span>
          <span id="txt-map-hint-new"></span>
        </div>
        <div id="new-cust-map-wrap" class="hidden">
          <div id="new-cust-map" class="w-full h-44 rounded-xl border border-slate-200 overflow-hidden shadow-inner z-0"></div>
          <p class="text-[10px] text-slate-400 mt-1 text-center" id="txt-map-drag-new"></p>
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1" id="lbl-new-locality"></label>
        <div class="grid grid-cols-2 gap-2">
          <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer font-bold text-slate-800 hover:border-emerald-500">
            <input type="radio" name="new_locality" value="Hanamkonda" checked onchange="switchRegion('Hanamkonda')" class="accent-emerald-600">
            <span id="new-hanamkonda-label"></span>
          </label>
          <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer font-bold text-slate-800 hover:border-emerald-500">
            <input type="radio" name="new_locality" value="Warangal" onchange="switchRegion('Warangal')" class="accent-emerald-600">
            <span id="new-warangal-label"></span>
          </label>
        </div>
        <p class="text-[11px] text-slate-400 mt-1" id="txt-kazipet-note"></p>
      </div>
      <button type="button" onclick="saveNewCustomerAndProceed()" class="btn btn-primary w-full h-11 min-h-[44px] font-bold text-xs mt-1 shadow-xs">
        <span id="btn-save-proceed"></span>
      </button>
    </div>
  </div>

  <!-- Step 3: Confirmed Delivery Address Strip (Visible after confirmation) -->
  <div id="onboarding-confirmed-strip" class="hidden app-card flex items-center justify-between gap-3 p-3 bg-emerald-50/80 border border-emerald-200">
    <div class="flex items-center gap-2.5 min-w-0">
      <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shrink-0">📍</span>
      <div class="min-w-0">
        <span class="text-[10px] text-emerald-800 font-bold uppercase tracking-wider block" id="lbl-confirmed-dest"></span>
        <div class="font-extrabold text-xs text-slate-900 truncate" id="confirmed-strip-name">Customer Name</div>
        <div class="text-[11px] text-slate-600 truncate" id="confirmed-strip-address">Street Address, Locality</div>
      </div>
    </div>
    <button type="button" onclick="changeActiveAddress()" class="btn btn-secondary h-8 px-3 text-xs font-bold text-emerald-800 border-emerald-200 shrink-0" id="btn-change-active-address"></button>
  </div>

</section>
