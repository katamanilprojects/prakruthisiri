/**
 * Prakruthi Siri - Unified Customer App Engine
 * Dynamic Language Toggle (Telugu/English) + Action Hub + Fast COD Checkout
 */

(() => {
  'use strict';

  // --------------------------------------------------------------------------
  // Global Multilingual Dictionary (Pure Single-Language Architecture)
  // --------------------------------------------------------------------------
  const I18N = {
    en: {
      brand_subtitle: "Chemical-Free Organic Vegetables",
      lang_btn_te: "తెలుగు",
      lang_btn_en: "English",
      btn_order_veg: "Order Fresh Vegetables",
      btn_order_sub: "Morning Doorstep Delivery",
      varieties_available: "varieties available",
      btn_whatsapp: "Order on WhatsApp",
      btn_whatsapp_sub: "Send Voice Note or List",
      btn_call: "Call Farm Direct",
      btn_call_sub: "Speak directly to dispatch",
      btn_history: "My Past Orders",
      btn_history_sub: "1-Tap Repeat Order",
      unit_label: "1/2 kg (0.5 kg)",
      packets: "packets",
      delivery_run: "📅 Delivery: Tomorrow Morning (6:30 AM – 10:30 AM)",
      view_bill: "View Bill & Confirm (Step 1 of 2)",
      confirm_cod: "Confirm & Place Order (Cash on Delivery)",
      total_due: "Total Cash Due (COD):",
      free_delivery: "FREE",
      sold_out: "Sold Out",
      change: "Change",
      back: "Back",
      final_step_notice: "⚠️ Final Step: Tap the green button below to complete your order",
      delivering_to: "Delivering To",
      items_subtotal: "Items Subtotal:",
      delivery_fee: "Delivery Fee:",
      order_placing: "Placing Order...",
      profile_title: "Delivery Address",
      phone_label: "Mobile Number",
      phone_placeholder: "10-digit mobile number",
      name_label: "Full Name",
      name_placeholder: "e.g. S. Rajendra Prasad",
      address_label: "House / Street / Colony",
      address_placeholder: "e.g. Plot 42, Green Meadows Colony",
      area_label: "Area / Region",
      gps_btn: "📍 Use My GPS Location",
      gps_latched: "✓ Location Latched Successfully!",
      save_btn: "Save & Continue →",
      repeat_order: "Repeat Order",
      past_orders_title: "My Past Orders",
      no_past_orders: "No past orders found for this phone number.",
      repeat_success: "Items added to your basket!",
      stock_limit_alert: (stock) => `Only ${stock} packets available in today's harvest.`,
      stock_conflict_alert: (name, avail) => `${name} has only ${avail} packets left. We updated your basket to the maximum available.`,
      order_fail_alert: (err) => `Could not place order: ${err || 'Please retry.'}`,
      network_error: "Network connection error. Please try again.",
      whatsapp_msg: "Namaste! I would like to order fresh chemical-free vegetables for tomorrow morning delivery.",
      empty_cart_alert: "Your basket is empty. Please select vegetables first.",
      login_prompt: "Please set up your delivery address first.",
      call_confirm: "Call Prakruthi Siri farm dispatch directly?",
      store_closed_title: "Farm Store Closed Temporarily",
      store_closed_desc: "Our daily harvest booking is closed due to farm maintenance or seasonal picking. Please check back this evening!"
    },
    te: {
      brand_subtitle: "రసాయనాలు లేని స్వచ్ఛమైన కూరగాయలు",
      lang_btn_te: "తెలుగు",
      lang_btn_en: "English",
      btn_order_veg: "తాజా కూరగాయలు కొనండి",
      btn_order_sub: "ఉదయం ఇంటి వద్దకే డెలివరీ",
      varieties_available: "రకాలు అందుబాటులో ఉన్నాయి",
      btn_whatsapp: "వాట్సాప్లో ఆర్డర్ చేయండి",
      btn_whatsapp_sub: "వాయిస్ మెసేజ్ లేదా లిస్ట్ పంపండి",
      btn_call: "ఫామ్కి నేరుగా కాల్ చేయండి",
      btn_call_sub: "డైరెక్ట్గా మాట్లాడండి",
      btn_history: "నా పాత ఆర్డర్లు",
      btn_history_sub: "ఒక్క ట్యాప్తో మళ్లీ కొనండి",
      unit_label: "1/2 కేజీ (0.5 kg)",
      packets: "ప్యాకెట్లు",
      delivery_run: "📅 రేపు ఉదయం (6:30 AM – 10:30 AM)",
      view_bill: "బిల్లు చూసి ఆర్డర్ చేయండి (స్టెప్ 1)",
      confirm_cod: "ఆర్డర్ ఖరారు చేయండి (క్యాష్ ఆన్ డెలివరీ)",
      total_due: "చెల్లించాల్సిన మొత్తం (క్యాష్):",
      free_delivery: "ఉచితం",
      sold_out: "అయిపోయాయి",
      change: "మార్చండి",
      back: "వెనుకకు",
      final_step_notice: "⚠️ చివరి దశ: ఆర్డర్ ఖరారు చేయడానికి క్రింది ఆకుపచ్చ బటన్‌ను నొక్కండి",
      delivering_to: "డెలివరీ చిరునామా",
      items_subtotal: "కూరగాయల మొత్తం:",
      delivery_fee: "డెలివరీ ఛార్జీ:",
      order_placing: "ఆర్డర్ ఖరారు అవుతోంది...",
      profile_title: "డెలివరీ చిరునామా",
      phone_label: "మొబైల్ నంబర్",
      phone_placeholder: "10 అంకెల మొబైల్ నంబర్",
      name_label: "మీ పూర్తి పేరు",
      name_placeholder: "ఉదా: ఎస్. రాజేంద్ర ప్రసాద్",
      address_label: "ఇంటి నెం / వీధి / కాలనీ",
      address_placeholder: "ఉదా: ప్లాట్ 42, గ్రీన్ మెడోస్ కాలనీ",
      area_label: "ప్రాంతం / ఏరియా",
      gps_btn: "📍 నా ప్రస్తుత లొకేషన్ (GPS)",
      gps_latched: "✓ లొకేషన్ గుర్తించబడింది!",
      save_btn: "సేవ్ చేసి ముందుకు వెళ్ళండి →",
      repeat_order: "మళ్లీ ఆర్డర్ చేయండి",
      past_orders_title: "నా పాత ఆర్డర్లు",
      no_past_orders: "ఈ నంబర్‌తో ఎలాంటి పాత ఆర్డర్లు లేవు.",
      repeat_success: "కూరగాయలు మీ బుట్టకు చేర్చబడ్డాయి!",
      stock_limit_alert: (stock) => `నేటి కోతలో కేవలం ${stock} ప్యాకెట్లు మాత్రమే అందుబాటులో ఉన్నాయి.`,
      stock_conflict_alert: (name, avail) => `${name} కేవలం ${avail} ప్యాకెట్లు మాత్రమే అందుబాటులో ఉన్నాయి. మీ బాస్కెట్‌ను గరిష్ట స్టాక్‌కు సర్దుబాటు చేసాము.`,
      order_fail_alert: (err) => `ఆర్డర్ చేయడంలో సమస్య: ${err || 'దయచేసి మళ్లీ ప్రయత్నించండి.'}`,
      network_error: "నెట్‌వర్క్ సమస్య ఏర్పడింది. దయచేసి మళ్లీ ప్రయత్నించండి.",
      whatsapp_msg: "నమస్తే! నాకు రేపటి ఉదయం డెలివరీ కోసం తాజా సేంద్రీయ కూరగాయలు కావాలి.",
      empty_cart_alert: "మీ బుట్ట ఖాళీగా ఉంది. దయచేసి కూరగాయలను ఎంచుకోండి.",
      login_prompt: "దయచేసి ముందుగా మీ డెలివరీ చిరునామా నమోదు చేయండి.",
      call_confirm: "ప్రకృతి సిరి ఫామ్ డెస్క్‌కు నేరుగా కాల్ చేయాలనుకుంటున్నారా?",
      store_closed_title: "ఫామ్ స్టోర్ తాత్కాలికంగా మూసివేయబడింది",
      store_closed_desc: "తోట నిర్వహణ లేదా కోతల కారణంగా నేటి బుకింగ్స్ మూసివేయబడ్డాయి. దయచేసి ఈ సాయంత్రం మళ్లీ చూడండి!"
    }
  };

  // --------------------------------------------------------------------------
  // Application State
  // --------------------------------------------------------------------------
  let currentLang = localStorage.getItem('ps_lang') || 'te';

  const state = {
    customer: null,
    cart: {}, // { [id]: { id, name_en, name_te, price, quantity, stock } }
    recentOrders: []
  };

  const CONFIG = window.APP_CONFIG || {
    movThreshold: 1.00,
    deliveryFee: 0.00,
    targetDeliveryDate: '',
    storeWhatsApp: '919393767927'
  };

  // DOM Elements cache
  const el = {};

  function cacheDomElements() {
    // Screens
    el.screenHub = document.getElementById('screen-hub');
    el.screenShop = document.getElementById('screen-shop');

    // Language Toggle
    el.langBtnTe = document.getElementById('lang-btn-te');
    el.langBtnEn = document.getElementById('lang-btn-en');

    // Hub Components
    el.hubUserPill = document.getElementById('hub-user-pill');
    el.hubCustomerName = document.getElementById('hub-customer-name');
    el.hubCustomerAddress = document.getElementById('hub-customer-address');
    el.btnEditUser = document.getElementById('btn-edit-user');
    el.btnGotoShop = document.getElementById('btn-goto-shop');
    el.btnShopBack = document.getElementById('btn-shop-back');
    el.btnOpenHistory = document.getElementById('btn-open-history');
    el.linkWhatsappHub = document.getElementById('link-whatsapp-hub');

    // Cart Bar
    el.cartFloatingBar = document.getElementById('cart-floating-bar');
    el.pillPacketsText = document.getElementById('pill-packets-text');
    el.pillAmountText = document.getElementById('pill-amount-text');
    el.btnOpenBill = document.getElementById('btn-open-bill');

    // Bill Confirmation Modal
    el.modalBill = document.getElementById('modal-bill');
    el.btnCloseBill = document.getElementById('btn-close-bill');
    el.billCustomerName = document.getElementById('bill-customer-name');
    el.billCustomerAddress = document.getElementById('bill-customer-address');
    el.btnEditBillAddress = document.getElementById('btn-edit-bill-address');
    el.billItemsList = document.getElementById('bill-items-list');
    el.billSubtotal = document.getElementById('bill-subtotal');
    el.billDeliveryFee = document.getElementById('bill-delivery-fee');
    el.billGrandTotal = document.getElementById('bill-grand-total');
    el.btnConfirmFinal = document.getElementById('btn-confirm-order-final');
    el.btnConfirmText = document.getElementById('btn-confirm-text');
    el.labelTotalPayable = document.getElementById('label-total-payable');

    // Customer Profile & Checkout Elements
    el.modalProfile = document.getElementById('modal-profile');
    el.btnCloseProfile = document.getElementById('btn-close-profile');
    el.formProfile = document.getElementById('form-profile');
    el.inputPhone = document.getElementById('input-profile-phone');
    el.inputName = document.getElementById('input-profile-name');
    el.inputAddress = document.getElementById('input-profile-address');
    el.inputLandmark = document.getElementById('input-profile-landmark');
    el.selectRegion = document.getElementById('select-profile-region');
    el.btnGpsLatch = document.getElementById('btn-gps-latch');
    el.gpsStatusText = document.getElementById('gps-status-text');
    el.inputLat = document.getElementById('input-profile-lat');
    el.inputLng = document.getElementById('input-profile-lng');
    el.phoneLookupBadge = document.getElementById('phone-lookup-badge');
    el.geofenceAlert = document.getElementById('geofence-alert');

    // Past Orders Modal
    el.modalHistory = document.getElementById('modal-history');
    el.btnCloseHistory = document.getElementById('btn-close-history');
    el.historyOrdersList = document.getElementById('history-orders-list');
  }

  // --------------------------------------------------------------------------
  // Initialization
  // --------------------------------------------------------------------------
  function init() {
    cacheDomElements();
    bindLanguageToggle();
    loadSavedCustomer();
    loadSavedCart();
    applyLanguage(currentLang);
    bindNavigation();
    bindCatalogSteppers();
    bindBillActions();
    bindProfileModal();
    bindHistoryModal();
  }

  // --------------------------------------------------------------------------
  // 1. Language Toggle & Dynamic Multilingual Engine
  // --------------------------------------------------------------------------
  function bindLanguageToggle() {
    el.langBtnTe?.addEventListener('click', () => applyLanguage('te'));
    el.langBtnEn?.addEventListener('click', () => applyLanguage('en'));
  }

  function applyLanguage(lang) {
    currentLang = lang;
    localStorage.setItem('ps_lang', lang);
    document.documentElement.lang = lang;

    const t = I18N[lang] || I18N.te;

    // Toggle button active styling
    if (lang === 'te') {
      el.langBtnTe?.classList.remove('text-white/80', 'hover:text-white', 'bg-transparent');
      el.langBtnTe?.classList.add('bg-harvest', 'text-stone-950', 'font-black', 'shadow-xs');
      el.langBtnEn?.classList.remove('bg-harvest', 'text-stone-950', 'font-black', 'shadow-xs');
      el.langBtnEn?.classList.add('text-white/80', 'hover:text-white', 'bg-transparent', 'font-bold');
    } else {
      el.langBtnEn?.classList.remove('text-white/80', 'hover:text-white', 'bg-transparent');
      el.langBtnEn?.classList.add('bg-harvest', 'text-stone-950', 'font-black', 'shadow-xs');
      el.langBtnTe?.classList.remove('bg-harvest', 'text-stone-950', 'font-black', 'shadow-xs');
      el.langBtnTe?.classList.add('text-white/80', 'hover:text-white', 'bg-transparent', 'font-bold');
    }

    // Translate all [data-i18n] nodes
    document.querySelectorAll('[data-i18n]').forEach((node) => {
      const key = node.dataset.i18n;
      if (key === 'delivery_run' && node.dataset.deliveryDate) {
        node.textContent = (lang === 'te') 
          ? `📅 డెలివరీ: ${node.dataset.deliveryDate} (6:30 AM – 10:30 AM)`
          : `📅 Delivery: ${node.dataset.deliveryDate} (6:30 AM – 10:30 AM)`;
      } else if (t[key] !== undefined) {
        node.textContent = t[key];
      }
    });

    // Translate placeholder attributes
    document.querySelectorAll('[data-i18n-placeholder]').forEach((node) => {
      const key = node.dataset.i18nPlaceholder;
      if (t[key] !== undefined) {
        node.placeholder = t[key];
      }
    });

    // Translate Product Catalog Cards: Pure Telugu OR Pure English
    document.querySelectorAll('.product-card').forEach((card) => {
      const titleEl = card.querySelector('.product-title');
      const badgeEl = card.querySelector('.item-unit-badge');
      if (titleEl) {
        titleEl.textContent = (lang === 'te') ? (card.dataset.nameTe || card.dataset.nameEn) : card.dataset.nameEn;
      }
      if (badgeEl) {
        badgeEl.textContent = t.unit_label;
      }
    });

    // Update dynamic WhatsApp link message
    if (el.linkWhatsappHub) {
      el.linkWhatsappHub.href = `https://wa.me/${CONFIG.storeWhatsApp}?text=${encodeURIComponent(t.whatsapp_msg)}`;
    }

    renderCartBar();
    renderHubUser();
  }

  // --------------------------------------------------------------------------
  // 2. Navigation Flow (Hub <-> Shop Tray)
  // --------------------------------------------------------------------------
  function bindNavigation() {
    el.btnGotoShop?.addEventListener('click', () => {
      el.screenHub?.classList.add('hidden');
      el.screenShop?.classList.remove('hidden');
      window.scrollTo(0, 0);
    });

    el.btnShopBack?.addEventListener('click', () => {
      el.screenShop?.classList.add('hidden');
      el.screenHub?.classList.remove('hidden');
      window.scrollTo(0, 0);
    });

    el.btnEditUser?.addEventListener('click', openProfileModal);
    el.btnEditBillAddress?.addEventListener('click', () => {
      el.modalBill?.classList.add('hidden');
      openProfileModal();
    });
  }

  // --------------------------------------------------------------------------
  // 3. Customer Session & Database Auto-Lookup
  // --------------------------------------------------------------------------
  function loadSavedCustomer() {
    const raw = localStorage.getItem('prakruthi_customer');
    if (raw) {
      try {
        state.customer = JSON.parse(raw);
        if (state.customer && state.customer.recent_orders) {
          state.recentOrders = state.customer.recent_orders;
        }
        renderHubUser();
      } catch (e) {
        localStorage.removeItem('prakruthi_customer');
        state.customer = null;
      }
    }
  }

  function renderHubUser() {
    if (!state.customer || !state.customer.full_name) {
      el.hubUserPill?.classList.add('hidden');
      return;
    }
    const t = I18N[currentLang] || I18N.te;
    if (el.hubCustomerName) el.hubCustomerName.textContent = state.customer.full_name;
    const addr = state.customer.delivery_address || '';
    const reg = state.customer.region || 'Warangal';
    if (el.hubCustomerAddress) el.hubCustomerAddress.textContent = `${addr} (${reg})`;
    if (el.btnEditUser) el.btnEditUser.textContent = t.change;
    el.hubUserPill?.classList.remove('hidden');
  }

  function bindProfileModal() {
    el.btnCloseProfile?.addEventListener('click', closeProfileModal);

    // 10-digit phone auto-lookup
    el.inputPhone?.addEventListener('input', async (e) => {
      const raw = e.target.value.replace(/\D/g, '').slice(0, 10);
      e.target.value = raw;

      if (raw.length === 10) {
        if (el.phoneLookupBadge) {
          el.phoneLookupBadge.textContent = '🔍 Checking profile...';
          el.phoneLookupBadge.classList.remove('hidden');
        }

        try {
          const res = await fetch(`api/customer-lookup.php?phone_number=${encodeURIComponent(raw)}`);
          if (res.ok) {
            const data = await res.json();
            if (data.success && data.found && data.customer) {
              if (el.inputName && !el.inputName.value) el.inputName.value = data.customer.full_name || '';
              if (el.inputAddress && !el.inputAddress.value) el.inputAddress.value = data.customer.delivery_address || '';
              if (el.selectRegion && data.customer.region) el.selectRegion.value = data.customer.region;
              if (el.inputLat && data.customer.latitude) el.inputLat.value = data.customer.latitude;
              if (el.inputLng && data.customer.longitude) el.inputLng.value = data.customer.longitude;

              if (data.customer.recent_orders) {
                state.recentOrders = data.customer.recent_orders;
              }

              if (el.phoneLookupBadge) {
                el.phoneLookupBadge.textContent = '✓ Saved Profile Found';
                el.phoneLookupBadge.className = 'text-[11px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-md';
              }
            } else {
              if (el.phoneLookupBadge) {
                el.phoneLookupBadge.textContent = '✓ New Customer Profile';
                el.phoneLookupBadge.className = 'text-[11px] font-bold text-stone-500 bg-stone-100 px-2 py-0.5 rounded-md';
              }
            }
          }
        } catch (_) {
          if (el.phoneLookupBadge) el.phoneLookupBadge.classList.add('hidden');
        }
      } else {
        if (el.phoneLookupBadge) el.phoneLookupBadge.classList.add('hidden');
      }
    });

    // Silent GPS Latch & 14 km Geofence Boundary Check
    el.btnGpsLatch?.addEventListener('click', () => {
      const t = I18N[currentLang] || I18N.te;
      if (!navigator.geolocation) {
        alert("GPS location is not supported on this browser.");
        return;
      }

      el.btnGpsLatch.disabled = true;
      if (el.gpsStatusText) el.gpsStatusText.textContent = "📍 Latching location...";

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;
          if (el.inputLat) el.inputLat.value = lat.toFixed(7);
          if (el.inputLng) el.inputLng.value = lng.toFixed(7);
          el.btnGpsLatch.disabled = false;

          const dist = calculateHaversineKm(lat, lng, 18.0165, 79.5583);
          if (dist > 14.0) {
            if (el.geofenceAlert) {
              el.geofenceAlert.classList.remove('hidden');
              el.geofenceAlert.innerHTML = `⚠️ <span>${currentLang === 'te' 
                ? `మీ లొకేషన్ మా ఫామ్ హబ్ నుండి ${dist.toFixed(1)} కి.మీ దూరంలో ఉంది (గరిష్ట పరిమితి: 14 కి.మీ). మేము హనుమకొండ & వరంగల్ పరిధిలో మాత్రమే డెలివరీ చేస్తాము.`
                : `Your location is ${dist.toFixed(1)} km from our central farm hub, exceeding our 14 km fresh delivery boundary in Hanamkonda & Warangal.`}</span>`;
            }
            el.btnGpsLatch.classList.remove('bg-emerald-50', 'border-emerald-300', 'text-emerald-900', 'bg-[#1B4D3E]', 'text-white');
            el.btnGpsLatch.classList.add('bg-amber-50', 'border-amber-400', 'text-amber-900');
            if (el.gpsStatusText) el.gpsStatusText.textContent = `⚠️ Location ${dist.toFixed(1)} km (> 14 km)`;
          } else {
            if (el.geofenceAlert) el.geofenceAlert.classList.add('hidden');
            el.btnGpsLatch.classList.remove('bg-emerald-50', 'border-emerald-300', 'text-emerald-900', 'bg-amber-50', 'border-amber-400', 'text-amber-900');
            el.btnGpsLatch.classList.add('bg-[#1B4D3E]', 'text-white', 'border-[#1B4D3E]');
            if (el.gpsStatusText) el.gpsStatusText.textContent = t.gps_latched;
          }
        },
        (err) => {
          el.btnGpsLatch.disabled = false;
          if (el.gpsStatusText) el.gpsStatusText.textContent = t.gps_btn;
          alert(currentLang === 'te' ? 'GPS గుర్తించలేకపోయాము. దయచేసి చిరునామా నమోదు చేయండి.' : 'Could not latch GPS. Please enter your address manually.');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
      );
    });

    // Form submit
    el.formProfile?.addEventListener('submit', (e) => {
      e.preventDefault();
      const phone = el.inputPhone?.value.replace(/\D/g, '') || '';
      const name = el.inputName?.value.trim() || '';
      const addr = el.inputAddress?.value.trim() || '';
      const reg = el.selectRegion?.value || 'Hanamkonda';
      const lat = el.inputLat?.value ? parseFloat(el.inputLat.value) : null;
      const lng = el.inputLng?.value ? parseFloat(el.inputLng.value) : null;

      if (phone.length !== 10) {
        alert("Please enter a valid 10-digit mobile number.");
        return;
      }
      if (!name) {
        alert("Please enter your name.");
        return;
      }
      if (!addr) {
        alert("Please enter your delivery address.");
        return;
      }

      state.customer = {
        phone_number: phone,
        phone: phone,
        full_name: name,
        delivery_address: addr,
        region: reg,
        latitude: lat,
        longitude: lng,
        recent_orders: state.recentOrders || []
      };

      localStorage.setItem('prakruthi_customer', JSON.stringify(state.customer));
      renderHubUser();
      closeProfileModal();

      // If user opened modal from bill, re-open bill
      if (Object.keys(state.cart).length > 0) {
        openBillModal();
      }
    });
  }

  function openProfileModal() {
    const t = I18N[currentLang] || I18N.te;
    if (state.customer) {
      if (el.inputPhone) el.inputPhone.value = state.customer.phone_number || state.customer.phone || '';
      if (el.inputName) el.inputName.value = state.customer.full_name || '';
      if (el.inputAddress) el.inputAddress.value = state.customer.delivery_address || '';
      if (el.selectRegion && state.customer.region) el.selectRegion.value = state.customer.region;
      if (el.inputLat && state.customer.latitude) el.inputLat.value = state.customer.latitude;
      if (el.inputLng && state.customer.longitude) el.inputLng.value = state.customer.longitude;
      if (state.customer.latitude && el.gpsStatusText) {
        el.gpsStatusText.textContent = t.gps_latched;
      }
    }
    el.modalProfile?.classList.remove('hidden');
  }

  function closeProfileModal() {
    el.modalProfile?.classList.add('hidden');
  }

  // --------------------------------------------------------------------------
  // 4. Steppers & Live Inventory Clamping
  // --------------------------------------------------------------------------
  function loadSavedCart() {
    const raw = sessionStorage.getItem('ps_cart');
    if (raw) {
      try {
        state.cart = JSON.parse(raw) || {};
        // Sync DOM steppers with saved quantities clamped to live stock
        document.querySelectorAll('.product-card').forEach((card) => {
          const id = parseInt(card.dataset.productId, 10);
          const liveStock = parseInt(card.dataset.productStock, 10) || 0;
          if (state.cart[id]) {
            if (liveStock <= 0) {
              delete state.cart[id];
            } else if (state.cart[id].quantity > liveStock) {
              state.cart[id].quantity = liveStock;
            }
            const countEl = card.querySelector('.stepper-count');
            if (countEl) countEl.textContent = state.cart[id]?.quantity || '0';
          }
        });
      } catch (e) {
        state.cart = {};
      }
    }
  }

  function bindCatalogSteppers() {
    document.querySelectorAll('.product-card').forEach((card) => {
      const id = parseInt(card.dataset.productId, 10);
      const name_en = card.dataset.nameEn || '';
      const name_te = card.dataset.nameTe || name_en;
      const price = parseFloat(card.dataset.productPrice) || 0;
      const stock = parseInt(card.dataset.productStock, 10) || 0;

      const btnInc = card.querySelector('[data-action="inc"]');
      const btnDec = card.querySelector('[data-action="dec"]');
      const countEl = card.querySelector('.stepper-count');

      btnInc?.addEventListener('click', () => {
        const currentQty = state.cart[id]?.quantity || 0;
        const t = I18N[currentLang] || I18N.te;

        if (currentQty >= stock) {
          alert(t.stock_limit_alert(stock));
          return;
        }

        state.cart[id] = { id, name_en, name_te, price, quantity: currentQty + 1, stock };
        if (countEl) countEl.textContent = state.cart[id].quantity;
        renderCartBar();
      });

      btnDec?.addEventListener('click', () => {
        const currentQty = state.cart[id]?.quantity || 0;
        if (currentQty <= 1) {
          delete state.cart[id];
          if (countEl) countEl.textContent = '0';
        } else {
          state.cart[id].quantity = currentQty - 1;
          if (countEl) countEl.textContent = state.cart[id].quantity;
        }
        renderCartBar();
      });
    });
  }

  function renderCartBar() {
    const items = Object.values(state.cart);
    let totalPackets = 0;
    let subtotal = 0;

    items.forEach((it) => {
      totalPackets += it.quantity;
      subtotal += it.price * it.quantity;
    });

    sessionStorage.setItem('ps_cart', JSON.stringify(state.cart));

    if (totalPackets > 0) {
      el.cartFloatingBar?.classList.remove('hidden');
      const t = I18N[currentLang] || I18N.te;
      const kg = (totalPackets * 0.5).toFixed(1);
      const suffix = t.packets;

      if (el.pillPacketsText) el.pillPacketsText.textContent = `${totalPackets} ${suffix} (${kg} kg)`;

      const delivery = (subtotal >= CONFIG.movThreshold) ? 0 : CONFIG.deliveryFee;
      const totalPayable = subtotal + delivery;
      const freeTag = delivery === 0 ? ` (${t.free_delivery})` : '';

      if (el.pillAmountText) el.pillAmountText.textContent = `₹${Math.round(totalPayable)}${freeTag}`;
    } else {
      el.cartFloatingBar?.classList.add('hidden');
    }
  }

  function calculateHaversineKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  function updateConfirmButtonText() {
    const selectedPayRadio = document.querySelector('input[name="checkout_payment"]:checked');
    const method = selectedPayRadio ? selectedPayRadio.value : 'COD';
    const isTe = (currentLang === 'te');
    if (el.btnConfirmText) {
      if (method === 'UPI') {
        el.btnConfirmText.textContent = isTe 
          ? 'ఆర్డర్ ఖరారు చేయండి (ఆన్‌లైన్ UPI)' 
          : 'Confirm & Place Order (Online UPI)';
      } else {
        el.btnConfirmText.textContent = isTe 
          ? 'ఆర్డర్ ఖరారు చేయండి (క్యాష్ ఆన్ డెలివరీ)' 
          : 'Confirm & Place Order (Cash on Delivery)';
      }
    }
    if (el.labelTotalPayable) {
      el.labelTotalPayable.textContent = isTe
        ? (method === 'UPI' ? 'చెల్లించాల్సిన మొత్తం (UPI):' : 'చెల్లించాల్సిన మొత్తం (క్యాష్):')
        : (method === 'UPI' ? 'Total Payable (UPI):' : 'Total Due (Cash):');
    }
  }

  // --------------------------------------------------------------------------
  // 5. Screen 3: Single-Screen Mobile Checkout
  // --------------------------------------------------------------------------
  function bindBillActions() {
    el.btnOpenBill?.addEventListener('click', openBillModal);
    el.btnCloseBill?.addEventListener('click', closeBillModal);
    el.btnConfirmFinal?.addEventListener('click', executeOrderPlacement);

    document.querySelectorAll('input[name="checkout_payment"]').forEach((radio) => {
      radio.addEventListener('change', (e) => {
        document.querySelectorAll('.payment-card-opt').forEach((card) => {
          const r = card.querySelector('input[type="radio"]');
          if (r && r.checked) {
            card.classList.add('border-brand', 'bg-emerald-50/50');
            card.classList.remove('border-[#EBE6DD]', 'bg-white');
          } else {
            card.classList.remove('border-brand', 'bg-emerald-50/50');
            card.classList.add('border-[#EBE6DD]', 'bg-white');
          }
        });
        updateConfirmButtonText();
      });
    });
  }

  function openBillModal() {
    const items = Object.values(state.cart);
    const t = I18N[currentLang] || I18N.te;

    if (items.length === 0) {
      alert(t.empty_cart_alert);
      return;
    }

    // Populate Customer Doorstep Info if available
    if (state.customer) {
      if (el.inputPhone && !el.inputPhone.value) el.inputPhone.value = state.customer.phone_number || state.customer.phone || '';
      if (el.inputName && !el.inputName.value) el.inputName.value = state.customer.full_name || '';
      if (el.inputAddress && !el.inputAddress.value) el.inputAddress.value = state.customer.delivery_address || '';
      if (el.inputLandmark && !el.inputLandmark.value) el.inputLandmark.value = state.customer.landmark || '';
      if (el.selectRegion && state.customer.region) el.selectRegion.value = state.customer.region;
      if (el.inputLat && state.customer.latitude) el.inputLat.value = state.customer.latitude;
      if (el.inputLng && state.customer.longitude) el.inputLng.value = state.customer.longitude;

      if (el.inputPhone && el.inputPhone.value.length === 10 && el.phoneLookupBadge) {
        el.phoneLookupBadge.textContent = '✓ ' + (currentLang === 'te' ? 'సేవ్ చేయబడిన చిరునామా' : 'Saved Profile');
        el.phoneLookupBadge.className = 'text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded';
        el.phoneLookupBadge.classList.remove('hidden');
      }
    }

    // Populate Itemized Basket List
    let subtotal = 0;
    if (el.billItemsList) {
      el.billItemsList.innerHTML = items.map((it) => {
        const lineTotal = it.price * it.quantity;
        subtotal += lineTotal;
        const displayName = (currentLang === 'te') ? (it.name_te || it.name_en) : it.name_en;
        const kg = (it.quantity * 0.5).toFixed(1);
        return `
          <div class="flex items-center justify-between py-2 text-xs">
            <div>
              <div class="font-extrabold text-stone-900">${escapeHtml(displayName)}</div>
              <div class="text-[11px] text-stone-500 font-semibold">${it.quantity} x 1/2 kg (${kg} kg)</div>
            </div>
            <div class="font-black text-stone-900 text-sm">₹${Math.round(lineTotal)}</div>
          </div>
        `;
      }).join('');
    }

    // Fee breakdown
    const fee = (subtotal >= CONFIG.movThreshold) ? 0 : CONFIG.deliveryFee;
    const grandTotal = subtotal + fee;

    if (el.billSubtotal) el.billSubtotal.textContent = `₹${Math.round(subtotal)}`;
    if (el.billDeliveryFee) el.billDeliveryFee.textContent = (fee === 0) ? t.free_delivery : `₹${Math.round(fee)}`;
    if (el.billGrandTotal) el.billGrandTotal.textContent = `₹${Math.round(grandTotal)}`;

    updateConfirmButtonText();
    el.modalBill?.classList.remove('hidden');
  }

  function closeBillModal() {
    el.modalBill?.classList.add('hidden');
  }

  async function executeOrderPlacement() {
    const items = Object.values(state.cart).map((i) => ({
      product_id: i.id,
      half_kg_quantity: i.quantity,
    }));

    if (items.length === 0) return;

    const t = I18N[currentLang] || I18N.te;

    const phone = (el.inputPhone?.value || '').replace(/\D/g, '');
    const name = (el.inputName?.value || '').trim();
    const addr = (el.inputAddress?.value || '').trim();
    const landmark = (el.inputLandmark?.value || '').trim();
    const region = el.selectRegion?.value || (window.APP_CONFIG && window.APP_CONFIG.selectedRegion) || 'Hanamkonda';
    const lat = el.inputLat?.value ? parseFloat(el.inputLat.value) : null;
    const lng = el.inputLng?.value ? parseFloat(el.inputLng.value) : null;

    if (phone.length !== 10) {
      alert(currentLang === 'te' ? 'దయచేసి సరైన 10 అంకెల మొబైల్ నంబర్ నమోదు చేయండి.' : 'Please enter a valid 10-digit mobile number.');
      el.inputPhone?.focus();
      return;
    }
    if (!name) {
      alert(currentLang === 'te' ? 'దయచేసి మీ పూర్తి పేరు నమోదు చేయండి.' : 'Please enter your full name.');
      el.inputName?.focus();
      return;
    }
    if (!addr) {
      alert(currentLang === 'te' ? 'దయచేసి మీ ఇంటి నెం/వీధి చిరునామా నమోదు చేయండి.' : 'Please enter your delivery address.');
      el.inputAddress?.focus();
      return;
    }

    // Geofencing verification (14 km max radius from farm hub)
    if (lat && lng) {
      const dist = calculateHaversineKm(lat, lng, 18.0165, 79.5583);
      if (dist > 14.0) {
        if (el.geofenceAlert) {
          el.geofenceAlert.classList.remove('hidden');
          el.geofenceAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        alert(currentLang === 'te'
          ? `క్షమించండి! మీ లొకేషన్ మా ఫామ్ హబ్ నుండి ${dist.toFixed(1)} కి.మీ దూరంలో ఉంది (పరిమితి: 14 కి.మీ). మేము హనుమకొండ & వరంగల్ పరిధిలో మాత్రమే డెలివరీ చేస్తాము.`
          : `We are sorry! Your address is ${dist.toFixed(1)} km from our central hub, exceeding our 14 km delivery radius.`
        );
        return;
      } else {
        if (el.geofenceAlert) el.geofenceAlert.classList.add('hidden');
      }
    }

    // Persist customer profile to state & local cache
    state.customer = {
      phone_number: phone,
      phone: phone,
      full_name: name,
      delivery_address: addr,
      landmark: landmark,
      region: region,
      latitude: lat,
      longitude: lng,
      recent_orders: state.recentOrders || []
    };
    try { localStorage.setItem('prakruthi_customer', JSON.stringify(state.customer)); } catch (_) {}
    renderHubUser();

    const selectedPayRadio = document.querySelector('input[name="checkout_payment"]:checked');
    const paymentMethod = selectedPayRadio ? selectedPayRadio.value : 'COD';

    if (window.APP_CONFIG && window.APP_CONFIG.scheduleStatus && window.APP_CONFIG.scheduleStatus !== 'OPEN') {
      alert(currentLang === 'te' 
        ? 'ఈ డెలివరీ రన్ కోసం ఆర్డరింగ్ ప్రస్తుతం మూసివేయబడింది.' 
        : 'Ordering for this delivery run is currently not open.');
      return;
    }

    el.btnConfirmFinal.disabled = true;
    el.btnConfirmFinal.innerHTML = `<span>⏳</span><span>${t.order_placing}</span>`;

    const payload = {
      schedule_id: window.APP_CONFIG ? window.APP_CONFIG.scheduleId : null,
      customer: {
        phone_number: phone,
        full_name: name,
        delivery_address: addr,
        landmark: landmark || null,
        region: region,
        latitude: lat,
        longitude: lng,
      },
      items: items,
      payment_method: paymentMethod
    };

    try {
      const res = await fetch('api/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (res.ok && data.success && data.redirect_url) {
        state.cart = {};
        try { sessionStorage.removeItem('ps_cart'); } catch (_) {}
        window.location.href = data.redirect_url;
      } else {
        if (data.error_type === 'INSUFFICIENT_STOCK') {
          const avail = parseInt(data.available_stock, 10) || 0;
          const pid = parseInt(data.product_id, 10);
          const pName = (currentLang === 'te' && data.product_name_te) ? data.product_name_te : (data.product_name || 'This vegetable');

          alert(t.stock_conflict_alert(pName, avail));

          if (state.cart[pid]) {
            if (avail > 0) {
              state.cart[pid].quantity = avail;
              state.cart[pid].stock = avail;
            } else {
              delete state.cart[pid];
            }
          }

          const card = document.querySelector(`.product-card[data-product-id="${pid}"]`);
          if (card) {
            card.dataset.productStock = avail;
            const countEl = card.querySelector('.stepper-count');
            if (countEl) countEl.textContent = (avail > 0 && state.cart[pid]) ? state.cart[pid].quantity : '0';
          }

          renderCartBar();
          if (Object.keys(state.cart).length > 0) {
            openBillModal();
          } else {
            closeBillModal();
          }
        } else {
          alert(t.order_fail_alert(data.error));
        }

        el.btnConfirmFinal.disabled = false;
        updateConfirmButtonText();
      }
    } catch (err) {
      alert(t.network_error);
      el.btnConfirmFinal.disabled = false;
      updateConfirmButtonText();
    }
  }

  // --------------------------------------------------------------------------
  // 6. Past Orders & 1-Tap Repeat Order Drawer
  // --------------------------------------------------------------------------
  function bindHistoryModal() {
    el.btnOpenHistory?.addEventListener('click', openHistoryModal);
    el.btnCloseHistory?.addEventListener('click', closeHistoryModal);
  }

  async function openHistoryModal() {
    const t = I18N[currentLang] || I18N.te;

    if (!state.customer || !state.customer.phone_number) {
      alert(t.login_prompt);
      openProfileModal();
      return;
    }

    el.modalHistory?.classList.remove('hidden');

    // If orders not loaded yet, fetch from customer lookup
    if (!state.recentOrders || state.recentOrders.length === 0) {
      if (el.historyOrdersList) {
        el.historyOrdersList.innerHTML = `<div class="py-8 text-center text-xs text-stone-500 font-bold">⏳ Loading orders...</div>`;
      }

      try {
        const phone = state.customer.phone_number || state.customer.phone;
        const res = await fetch(`api/customer-lookup.php?phone_number=${encodeURIComponent(phone)}`);
        if (res.ok) {
          const data = await res.json();
          if (data.customer && data.customer.recent_orders) {
            state.recentOrders = data.customer.recent_orders;
          }
        }
      } catch (_) {}
    }

    renderHistoryOrders();
  }

  function closeHistoryModal() {
    el.modalHistory?.classList.add('hidden');
  }

  function renderHistoryOrders() {
    const t = I18N[currentLang] || I18N.te;
    if (!el.historyOrdersList) return;

    if (!state.recentOrders || state.recentOrders.length === 0) {
      el.historyOrdersList.innerHTML = `
        <div class="py-10 text-center space-y-2">
          <div class="text-3xl">📦</div>
          <p class="text-xs text-stone-500 font-bold">${t.no_past_orders}</p>
        </div>
      `;
      return;
    }

    el.historyOrdersList.innerHTML = state.recentOrders.map((ord) => {
      const itemsSummary = (ord.items || []).map((it) => {
        const name = (currentLang === 'te') ? (it.telugu_name || it.product_name) : it.product_name;
        return `<span class="inline-block bg-stone-100 text-stone-800 text-[11px] font-semibold px-2 py-0.5 rounded-md border border-stone-200">${it.quantity}x ${escapeHtml(name)}</span>`;
      }).join(' ');

      const orderDate = ord.target_delivery_date || ord.created_at?.slice(0, 10) || '';

      return `
        <article class="bg-white border border-stone-200/90 rounded-2xl p-4 space-y-2.5 shadow-2xs">
          <div class="flex items-center justify-between border-b border-stone-100 pb-2">
            <div>
              <span class="font-black text-xs text-stone-900">${escapeHtml(ord.order_code)}</span>
              <span class="text-[11px] text-stone-500 font-semibold block">${escapeHtml(orderDate)}</span>
            </div>
            <div class="font-extrabold text-sm text-brand">
              ₹${Math.round(ord.total_amount)}
            </div>
          </div>

          <div class="flex flex-wrap gap-1.5 py-1">
            ${itemsSummary}
          </div>

          <button 
            type="button" 
            class="btn-repeat-order tap-target w-full py-2.5 bg-emerald-50 hover:bg-emerald-100 active:scale-98 border border-emerald-300 text-emerald-900 font-extrabold text-xs rounded-xl shadow-2xs transition flex items-center justify-center gap-1.5"
            data-order-id="${ord.id}"
          >
            <span>🔄</span>
            <span>${t.repeat_order}</span>
          </button>
        </article>
      `;
    }).join('');

    // Bind Repeat Order buttons
    el.historyOrdersList.querySelectorAll('.btn-repeat-order').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        const ordId = parseInt(btn.dataset.orderId, 10);
        const order = state.recentOrders.find(o => o.id === ordId);
        if (!order || !order.items) return;

        repeatOrder(order.items);
      });
    });
  }

  function repeatOrder(items) {
    const t = I18N[currentLang] || I18N.te;
    let addedCount = 0;

    items.forEach((item) => {
      const pid = item.product_id;
      const card = document.querySelector(`.product-card[data-product-id="${pid}"]`);
      if (!card) return;

      const liveStock = parseInt(card.dataset.productStock, 10) || 0;
      if (liveStock <= 0) return;

      const reqQty = item.quantity || 1;
      const finalQty = Math.min(reqQty, liveStock);

      const name_en = card.dataset.nameEn || item.product_name || '';
      const name_te = card.dataset.nameTe || item.telugu_name || name_en;
      const price = parseFloat(card.dataset.productPrice) || item.price || 0;

      state.cart[pid] = {
        id: pid,
        name_en,
        name_te,
        price,
        quantity: finalQty,
        stock: liveStock
      };

      const countEl = card.querySelector('.stepper-count');
      if (countEl) countEl.textContent = finalQty;
      addedCount++;
    });

    renderCartBar();
    closeHistoryModal();

    if (addedCount > 0) {
      alert(t.repeat_success);
      // Switch to shop tray to review
      el.screenHub?.classList.add('hidden');
      el.screenShop?.classList.remove('hidden');
      window.scrollTo(0, 0);
    } else {
      alert(currentLang === 'te' ? 'క్షమించండి, ఈ కూరగాయలు ప్రస్తుతం స్టాక్‌లో లేవు.' : 'Sorry, these vegetables are currently out of stock.');
    }
  }

  // --------------------------------------------------------------------------
  // Utility Functions
  // --------------------------------------------------------------------------
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  document.addEventListener('DOMContentLoaded', init);
})();
