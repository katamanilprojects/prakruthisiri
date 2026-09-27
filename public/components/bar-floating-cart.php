<?php
declare(strict_types=1);
?>
<!-- STEP 3: FLOATING STICKY CART BOTTOM BAR -->
<aside id="sticky-cart-bar" class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-200 p-3.5 z-40 shadow-2xl hidden">
  <div class="max-w-xl mx-auto flex items-center justify-between gap-3">
    <div>
      <div class="font-mono font-extrabold text-base sm:text-lg text-slate-900 leading-tight" id="bar-items-summary">
        0 Items | ₹0
      </div>
      <div class="text-[11px] text-emerald-700 font-medium" id="bar-items-subtext">
        Free Delivery Available
      </div>
    </div>
    <button 
      type="button" 
      id="btn-open-drawer" 
      onclick="openCheckoutDrawer()"
      class="btn btn-primary h-11 px-5 text-xs sm:text-sm font-extrabold rounded-xl shadow-md flex items-center gap-1.5 cursor-pointer"
    >
      <span id="btn-open-drawer-text">View Cart / చిరునామా & చెల్లింపు →</span>
    </button>
  </div>
</aside>
