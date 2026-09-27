/**
 * Prakruthi Siri Storefront Client App Engine
 */

// --------------------------------------------------------------------------
// Language Switcher
// --------------------------------------------------------------------------
function setCustomerLanguage(lang) {
  currentLang = lang;
  localStorage.setItem('ps_customer_lang', lang);
  const isTe = (lang === 'te');
  const t = CUSTOMER_I18N[lang] || CUSTOMER_I18N.te;

  // --- Language toggle button styles ---
  const btnTe = document.getElementById('btn-lang-te');
  const btnEn = document.getElementById('btn-lang-en');
  if (btnTe && btnEn) {
    if (isTe) {
      btnTe.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
      btnEn.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
    } else {
      btnEn.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
      btnTe.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
    }
  }

  // --- Header ---
  const el = (id) => document.getElementById(id);
  const set = (id, val) => { const e = el(id); if (e) e.textContent = val; };
  const setAttr = (id, attr, val) => { const e = el(id); if (e) e.setAttribute(attr, val); };

  set('header-brand', t.brand_title);
  set('header-tagline', t.brand_subtitle);
  set('pwa-text', t.pwa_banner_text);

  // --- Onboarding Step 1 ---
  set('lbl-onboarding-badge', t.onboarding_badge);
  set('btn-phone-continue-label', t.btn_continue);
  set('txt-onboarding-hint', t.onboarding_hint);

  // --- Returning customer card ---
  set('returning-badge', t.returning_badge);
  set('btn-change-mobile', t.btn_change_mobile);
  set('lbl-select-addr', t.lbl_select_address);
  set('btn-proceed-veg', t.btn_confirm_address);
  set('btn-add-new-addr-text', t.btn_add_new_address);

  // Inline new address form (in returning customer card)
  set('inline-addr-form-title', t.inline_addr_title);
  set('lbl-inline-addr-label', t.lbl_addr_label);
  setAttr('inline-addr-label', 'placeholder', t.placeholder_addr_label);
  set('lbl-inline-addr-street', t.lbl_addr_street);
  setAttr('inline-addr-street', 'placeholder', t.placeholder_addr_street);
  set('lbl-inline-addr-landmark', t.lbl_addr_landmark);
  setAttr('inline-addr-landmark', 'placeholder', t.placeholder_addr_landmark);
  set('lbl-gps-section-inline', t.gps_section_title);
  set('txt-gps-inline', t.btn_use_gps);
  set('txt-pin-map-inline', t.btn_pin_map);
  setAttr('inline-addr-map-link', 'placeholder', t.placeholder_maps_link);
  set('txt-map-hint-inline', t.map_hint);
  set('txt-map-drag-inline', t.map_drag_hint);
  set('lbl-inline-addr-city', t.lbl_addr_city);
  set('inline-hanamkonda-label', t.hanamkonda_label);
  set('inline-warangal-label', t.warangal_label);
  set('btn-save-inline-address', t.btn_save_address);

  // --- New customer card ---
  set('new-cust-header', t.new_cust_header);
  set('new-cust-badge', t.new_cust_badge);
  set('lbl-new-name', t.lbl_new_name);
  setAttr('new-cust-name', 'placeholder', t.placeholder_new_name);
  set('lbl-new-address', t.lbl_new_address);
  setAttr('new-cust-address', 'placeholder', t.placeholder_new_address);
  set('lbl-new-landmark', t.lbl_new_landmark);
  setAttr('new-cust-landmark', 'placeholder', t.placeholder_new_landmark);
  set('lbl-gps-section-new', '📍 ' + (isTe ? 'GPS డోర్‌స్టెప్ లొకేషన్' : 'Doorstep Map Location'));
  set('lbl-gps-section-new-desc', t.gps_section_desc);
  set('txt-gps-new', t.btn_use_gps);
  set('txt-pin-map-new', t.btn_pin_map);
  setAttr('new-cust-map-link', 'placeholder', t.placeholder_maps_link);
  set('txt-map-hint-new', t.map_hint);
  set('txt-map-drag-new', t.map_drag_hint);
  set('lbl-new-locality', t.lbl_new_locality);
  set('new-hanamkonda-label', t.hanamkonda_label);
  set('new-warangal-label', t.warangal_label);
  set('txt-kazipet-note', t.kazipet_note);
  set('btn-save-proceed', t.btn_save_proceed);

  // --- Confirmed address strip ---
  set('lbl-confirmed-dest', t.lbl_delivering_to);
  set('btn-change-active-address', t.btn_change_address);

  // --- Locked catalog card ---
  set('locked-card-title', t.locked_title);
  set('locked-card-desc', t.locked_desc);
  set('locked-card-badge', t.locked_badge);

  // --- Delivery Banner & Catalog ---
  set('pill-hanamkonda-label', t.hanamkonda_label);
  set('pill-warangal-label', t.warangal_label);
  set('txt-batch-full-msg', t.batch_full_msg);

  updateScheduleHeaderUI();

  // --- Step 2: Catalog ---
  set('title-step2', t.title_step2);
  const countLabel = el('catalog-count-label');
  if (countLabel) countLabel.textContent = t.varieties_label(activeCatalog.length);
  set('catalog-loading-msg', t.catalog_empty);

  // Update unit-labels & sold-out badges
  const unitText = t.unit_pack_label || (isTe ? '500గ్రా ప్యాకెట్ (1/2 కేజీ)' : 'Pack of 500g (1/2 kg)');
  document.querySelectorAll('.unit-label').forEach(el => el.textContent = unitText);
  document.querySelectorAll('.sold-out-badge').forEach(el => el.textContent = t.sold_out);

  // Update product card names and subtitles based on language
  document.querySelectorAll('#vegetables-container article').forEach(card => {
    const teluguName = card.getAttribute('data-telugu-name') || '';
    const englishName = card.getAttribute('data-english-name') || '';
    const nameEl = card.querySelector('.prod-name');
    const subEl = card.querySelector('.prod-subtitle');
    if (nameEl) nameEl.textContent = isTe ? (teluguName || englishName) : englishName;
    if (subEl) subEl.textContent = isTe ? englishName : teluguName;
  });

  // --- Sticky cart bar ---
  set('btn-open-drawer-text', t.btn_review_order);

  // --- Checkout Drawer ---
  set('drawer-title', t.drawer_title);
  const drawerDesc = el('drawer-run-desc');
  if (drawerDesc && activeSchedule) {
    const regionLabel = currentRegion === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
    drawerDesc.textContent = (activeSchedule.delivery_day || '') + (isTe ? ' బ్యాచ్ · ' : ' Batch · ') + regionLabel;
  }

  // Re-translate customer profile elements if loaded
  if (customerProfile) {
    const welEl = el('returning-welcome-title');
    if (welEl && customerProfile.full_name) {
      welEl.textContent = `${t.returning_welcome} ${customerProfile.full_name}!`;
    }
    if (customerProfile.addresses && customerProfile.addresses.length) {
      renderSavedAddresses();
    }
    if (customerProfile.active_address) {
      const activeAddr = customerProfile.active_address;
      const displayRegion = activeAddr.region === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
      const gpsBadge = (activeAddr.latitude && activeAddr.longitude) ? ` <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-sky-100 text-sky-800 border border-sky-200">${t.gps_pinned_badge}</span>` : '';
      const stripAddr = el('confirmed-strip-address');
      if (stripAddr) stripAddr.innerHTML = `${activeAddr.delivery_address}${activeAddr.landmark ? ' (' + activeAddr.landmark + ')' : ''} &bull; ${displayRegion}${gpsBadge}`;
      const drawerAddr = el('drawer-summary-address');
      if (drawerAddr) drawerAddr.innerHTML = `${activeAddr.delivery_address}${activeAddr.landmark ? ' (' + activeAddr.landmark + ')' : ''} &bull; ${displayRegion}${gpsBadge}`;
    }
  }
  set('drawer-basket-summary', t.drawer_basket_heading);
  set('lbl-drawer-address', t.lbl_drawer_address);
  set('btn-drawer-change', t.btn_drawer_change);
  set('lbl-cust-name', t.name_label);
  setAttr('cust-name', 'placeholder', t.name_placeholder);
  set('lbl-cust-phone', t.phone_label);
  set('lbl-cust-address', t.address_label);
  setAttr('cust-address', 'placeholder', t.address_placeholder);
  set('lbl-cust-landmark', t.landmark_label);
  setAttr('cust-landmark', 'placeholder', t.landmark_placeholder);
  set('lbl-cust-locality', t.locality_label);

  // Dropdown options
  set('drawer-opt-hanamkonda', t.hanamkonda_label);
  set('drawer-opt-warangal', t.warangal_label);

  // Payment
  set('lbl-payment-mode', t.payment_mode_label);
  set('txt-mode-cod', t.pay_cod_label);
  set('txt-mode-cod-sub', t.pay_cod_sub);

  // Bill
  set('lbl-bill-subtotal', t.items_subtotal);
  set('lbl-bill-delivery', t.delivery_fee);
  set('lbl-bill-total', t.total_payable);

  // Confirm button
  const isFull = !!(activeSchedule?.is_batch_full);
  set('btn-confirm-order-text', isFull ? t.batch_full_btn : t.btn_confirm_order);

  renderCartUI();
}

// --------------------------------------------------------------------------
// PWA Banner & Installation Prompt Logic
// --------------------------------------------------------------------------
let deferredPublicPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPublicPrompt = e;
});

function dismissPwaBanner() {
  document.getElementById('pwa-banner')?.classList.add('hidden');
  localStorage.setItem('ps_pwa_dismissed', '1');
}

if (localStorage.getItem('ps_pwa_dismissed') === '1' || window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
  document.getElementById('pwa-banner')?.classList.add('hidden');
}

function triggerPublicPwaInstall() {
  if (deferredPublicPrompt) {
    deferredPublicPrompt.prompt();
    deferredPublicPrompt.userChoice.then((choice) => {
      if (choice.outcome === 'accepted') {
        dismissPwaBanner();
      }
      deferredPublicPrompt = null;
    });
  } else {
    const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;
    if (isIos) {
      alert('To install Prakruthi Siri on your iPhone/iPad:\n\n1. Tap the Share button 📤 in Safari.\n2. Scroll down & tap "Add to Home Screen" ➕.');
    } else {
      alert('To install Prakruthi Siri:\n\nIn your browser menu (⋮), select "Install App" or "Add to Home Screen".');
    }
  }
}

// --------------------------------------------------------------------------
// Phone-First Onboarding sequence & Multi-Address Management
// --------------------------------------------------------------------------
let phoneLookupTimeout = null;
let selectedAddressId = null;

function handlePhoneInput(val) {
  const clean = val.replace(/[^0-9]/g, '');
  const btn = document.getElementById('btn-phone-continue');
  if (clean.length === 10) {
    btn?.classList.remove('hidden');
    clearTimeout(phoneLookupTimeout);
    phoneLookupTimeout = setTimeout(() => performPhoneLookup(clean), 300);
  } else {
    btn?.classList.add('hidden');
  }
}

function checkPhoneManual() {
  const phone = document.getElementById('onboarding-phone').value.replace(/[^0-9]/g, '');
  if (phone.length === 10) {
    performPhoneLookup(phone);
  }
}

async function performPhoneLookup(phone) {
  try {
    const resp = await fetch(`api/customer-lookup.php?phone=${phone}`);
    const data = await resp.json();

    if (data.success && data.found && data.customer) {
      customerProfile = data.customer;
      if (!customerProfile.addresses || !customerProfile.addresses.length) {
        customerProfile.addresses = [{
          id: 1,
          label: 'Home',
          delivery_address: customerProfile.delivery_address || '',
          landmark: customerProfile.landmark || '',
          region: customerProfile.region || 'Hanamkonda',
          is_default: true
        }];
      }
      selectedAddressId = customerProfile.active_address?.id || customerProfile.addresses[0]?.id;
      localStorage.setItem('ps_customer_phone', phone);
      localStorage.setItem('ps_cust_profile', JSON.stringify(customerProfile));
      localStorage.setItem('ps_saved_profile', JSON.stringify(customerProfile));
      showReturningCustomerUI(customerProfile);
    } else {
      customerProfile = { phone_number: phone, addresses: [] };
      showNewCustomerUI(phone);
    }
  } catch (err) {
    customerProfile = { phone_number: phone, addresses: [] };
    showNewCustomerUI(phone);
  }
}

function showReturningCustomerUI(cust) {
  document.getElementById('onboarding-step-1').classList.add('hidden');
  document.getElementById('onboarding-new-card').classList.add('hidden');
  document.getElementById('onboarding-confirmed-strip').classList.add('hidden');
  const retCard = document.getElementById('onboarding-returning-card');
  retCard.classList.remove('hidden');

  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
  document.getElementById('returning-welcome-title').textContent = `${t.returning_welcome} ${cust.full_name || ''}!`;
  document.getElementById('returning-phone-display').textContent = `+91 ${cust.phone_number || cust.phone || ''}`;

  renderSavedAddresses();
}

function renderSavedAddresses() {
  const container = document.getElementById('saved-addresses-container');
  if (!container) return;
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

  const addrs = customerProfile?.addresses || [];
  if (!addrs.length && customerProfile?.delivery_address) {
    addrs.push({
      id: 1,
      label: 'Home',
      delivery_address: customerProfile.delivery_address,
      landmark: customerProfile.landmark || '',
      region: customerProfile.region || 'Hanamkonda',
      is_default: true
    });
    customerProfile.addresses = addrs;
  }

  if (!selectedAddressId && addrs.length > 0) {
    selectedAddressId = addrs[0].id;
  }

  container.innerHTML = addrs.map((addr) => {
    const isSelected = (addr.id === selectedAddressId);
    const icon = addr.label && addr.label.toLowerCase().includes('work') ? '💼' : '🏠';
    const displayLabel = (!addr.label || addr.label.toLowerCase() === 'home') ? t.placeholder_addr_label : addr.label;
    const displayRegion = addr.region === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
    return `
      <label class="flex items-start gap-3 p-3 rounded-xl border-2 transition cursor-pointer ${isSelected ? 'border-emerald-600 bg-white shadow-xs' : 'border-slate-200 bg-white/70 hover:border-slate-300'}">
        <input 
          type="radio" 
          name="selected_addr_id" 
          value="${addr.id}" 
          ${isSelected ? 'checked' : ''} 
          onchange="selectAddress(${addr.id})"
          class="mt-1 accent-emerald-600 shrink-0"
        >
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-extrabold text-slate-900 text-xs">${icon} ${displayLabel}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${addr.region === 'Warangal' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'}">${displayRegion}</span>
            ${(addr.latitude && addr.longitude) ? `<span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-sky-50 text-sky-800 border border-sky-200">${t.gps_pinned_badge}</span>` : ''}
          </div>
          <p class="text-xs text-slate-600 mt-0.5 leading-snug">${addr.delivery_address}${addr.landmark ? ' (' + addr.landmark + ')' : ''}</p>
        </div>
      </label>
    `;
  }).join('');
}

function selectAddress(addrId) {
  selectedAddressId = addrId;
  const addr = (customerProfile?.addresses || []).find(a => a.id === addrId);
  if (addr) {
    customerProfile.active_address = addr;
    customerProfile.delivery_address = addr.delivery_address;
    customerProfile.landmark = addr.landmark;
    customerProfile.region = addr.region;

    if (addr.region && addr.region !== currentRegion) {
      switchRegion(addr.region);
    }
  }
  renderSavedAddresses();
}

function saveProfilePhone() {
  const ph = (customerProfile?.phone_number || customerProfile?.phone || '').replace(/\D/g,'').slice(-10);
  if (ph) localStorage.setItem('ps_customer_phone', ph);
}

function toggleNewAddressForm(show) {
  const form = document.getElementById('inline-new-addr-form');
  const btnToggle = document.getElementById('btn-add-addr-toggle');
  if (show) {
    form?.classList.remove('hidden');
    btnToggle?.classList.add('hidden');
    ['inline-addr-label','inline-addr-street','inline-addr-landmark','inline-addr-map-link'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    document.getElementById('inline-addr-lat').value = '';
    document.getElementById('inline-addr-lng').value = '';
    _syncInlineGpsUI(null, null);
  } else {
    form?.classList.add('hidden');
    btnToggle?.classList.remove('hidden');
  }
}

function _syncInlineGpsUI(lat, lng) {
  const hasCoords = !!(lat && lng);
  const gpsBtn  = document.getElementById('btn-gps-inline');
  const mapBtn  = document.getElementById('btn-map-toggle-inline');
  const feedback = document.getElementById('inline-addr-map-feedback');
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
  if (hasCoords) {
    if (gpsBtn)  { gpsBtn.classList.add('hidden'); }
    if (mapBtn)  { mapBtn.classList.add('hidden'); }
    if (feedback) feedback.innerHTML = `<span class="inline-flex items-center gap-1 text-sky-700 font-bold">📍 ${t.gps_pinned_badge} &mdash; <button type="button" onclick="_clearInlineGps()" class="underline text-slate-500 font-normal">${t.btn_change_mobile || 'Change'}</button></span>`;
  } else {
    if (gpsBtn)  { gpsBtn.classList.remove('hidden'); }
    if (mapBtn)  { mapBtn.classList.remove('hidden'); }
    if (feedback) feedback.innerHTML = `<span>💡</span><span id="txt-map-hint-inline"></span>`;
    const t2 = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
    const hint = document.getElementById('txt-map-hint-inline');
    if (hint) hint.textContent = t2.map_hint || '';
  }
}

function _clearInlineGps() {
  document.getElementById('inline-addr-lat').value = '';
  document.getElementById('inline-addr-lng').value = '';
  _syncInlineGpsUI(null, null);
}

// --------------------------------------------------------------------------
// Map Location, GPS Auto-Detection & Geofencing System
// --------------------------------------------------------------------------
const FARM_HUB = { lat: 18.028439, lng: 79.635941, maxRadiusKm: 11.5 };
const CENTROIDS = {
  Hanamkonda: { lat: 17.9856, lng: 79.5892 },
  Warangal: { lat: 17.9689, lng: 79.5941 }
};

function calculateDistanceKm(lat1, lon1, lat2, lon2) {
  const R = 6371; // km
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

function checkGeofence(lat, lng) {
  const dist = calculateDistanceKm(FARM_HUB.lat, FARM_HUB.lng, lat, lng);
  const isTe = (currentLang === 'te');
  
  if (lng < 79.540 && lat >= 17.92 && lat <= 18.06 && dist <= 18.0) {
    return { 
      valid: false, 
      error: (isTe ? 'క్షమించండి! మేము కాజీపేట ప్రాంతానికి డెలివరీ చేయట్లేదు.' : 'Kazipet is outside our delivery area. Currently serving Hanamkonda & Warangal only.'), 
      isKazipet: true, 
      dist 
    };
  }
  if (dist > FARM_HUB.maxRadiusKm) {
    return { 
      valid: false, 
      error: (isTe ? `మీ లొకేషన్ మా ఫామ్ హబ్ నుండి ${dist.toFixed(1)} కిమీ దూరంలో ఉంది (గరిష్ట పరిధి 11.5 కిమీ).` : `Location is ${dist.toFixed(1)} km away, exceeding our 11.5 km delivery radius.`), 
      dist 
    };
  }

  const dH = calculateDistanceKm(CENTROIDS.Hanamkonda.lat, CENTROIDS.Hanamkonda.lng, lat, lng);
  const dW = calculateDistanceKm(CENTROIDS.Warangal.lat, CENTROIDS.Warangal.lng, lat, lng);
  const closestRegion = dH <= dW ? 'Hanamkonda' : 'Warangal';

  return { valid: true, closestRegion, dist };
}

let leafletMaps = { new: null, inline: null };
let leafletMarkers = { new: null, inline: null };

function initLeafletMap(type, initialLat, initialLng) {
  const wrapId = `${type === 'new' ? 'new-cust' : 'inline-addr'}-map-wrap`;
  const mapElId = `${type === 'new' ? 'new-cust' : 'inline-addr'}-map`;
  document.getElementById(wrapId)?.classList.remove('hidden');

  const lat = initialLat || 17.9856;
  const lng = initialLng || 79.5892;

  if (!leafletMaps[type]) {
    if (typeof L === 'undefined') {
      console.warn('Leaflet not loaded yet');
      return;
    }
    leafletMaps[type] = L.map(mapElId).setView([lat, lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(leafletMaps[type]);

    const marker = L.marker([lat, lng], { draggable: true }).addTo(leafletMaps[type]);
    leafletMarkers[type] = marker;

    marker.on('dragend', () => {
      const pos = marker.getLatLng();
      setPointCoordinates(type, pos.lat, pos.lng, true);
    });

    leafletMaps[type].on('click', (e) => {
      marker.setLatLng(e.latlng);
      setPointCoordinates(type, e.latlng.lat, e.latlng.lng, true);
    });
  } else {
    leafletMaps[type].setView([lat, lng], 14);
    leafletMarkers[type].setLatLng([lat, lng]);
    setTimeout(() => { leafletMaps[type].invalidateSize(); }, 150);
  }
}

function toggleLeafletMap(type) {
  const wrap = document.getElementById(`${type === 'new' ? 'new-cust' : 'inline-addr'}-map-wrap`);
  if (wrap?.classList.contains('hidden')) {
    const curLat = parseFloat(document.getElementById(`${type === 'new' ? 'new-cust' : 'inline-addr'}-lat`).value) || null;
    const curLng = parseFloat(document.getElementById(`${type === 'new' ? 'new-cust' : 'inline-addr'}-lng`).value) || null;
    initLeafletMap(type, curLat, curLng);
  } else {
    wrap?.classList.add('hidden');
  }
}

function setPointCoordinates(type, lat, lng, fromMap = false) {
  const prefix = (type === 'new') ? 'new-cust' : 'inline-addr';
  const latEl = document.getElementById(`${prefix}-lat`);
  const lngEl = document.getElementById(`${prefix}-lng`);
  const feedbackEl = document.getElementById(`${prefix}-map-feedback`);
  const isTe = (currentLang === 'te');
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

  latEl.value = lat.toFixed(7);
  lngEl.value = lng.toFixed(7);

  const check = checkGeofence(lat, lng);
  if (check.valid) {
    feedbackEl.className = 'text-[11px] font-bold text-emerald-700 flex items-center gap-1.5';
    const regionName = check.closestRegion === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
    const infoMsg = isTe
      ? `లొకేషన్ పిన్ నమోదైంది (${lat.toFixed(4)}, ${lng.toFixed(4)}) • ఫామ్ నుండి ${check.dist.toFixed(1)} కిమీ • ${regionName}`
      : `Location Pinned (${lat.toFixed(4)}, ${lng.toFixed(4)}) • ${check.dist.toFixed(1)} km from Hub • ${check.closestRegion}`;
    feedbackEl.innerHTML = `<span>✓</span> <span>${infoMsg}</span>`;

    if (type === 'new') {
      const radio = document.querySelector(`input[name="new_locality"][value="${check.closestRegion}"]`);
      if (radio && !radio.checked) {
        radio.checked = true;
        switchRegion(check.closestRegion);
      }
    } else {
      const radio = document.querySelector(`input[name="inline_addr_city"][value="${check.closestRegion}"]`);
      if (radio) radio.checked = true;
    }
  } else {
    feedbackEl.className = 'text-[11px] font-bold text-rose-600 flex items-center gap-1.5';
    feedbackEl.innerHTML = `<span>⚠️</span> <span>${check.error}</span>`;
  }

  if (!fromMap && leafletMaps[type]) {
    leafletMaps[type].setView([lat, lng], 15);
    leafletMarkers[type].setLatLng([lat, lng]);
  }
}

function detectUserGps(type) {
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
  const btnTxt = document.getElementById(`txt-gps-${type}`);
  const oldText = btnTxt.textContent;
  btnTxt.textContent = t.gps_locating;

  if (!navigator.geolocation) {
    alert(t.gps_unsupported);
    btnTxt.textContent = oldText;
    return;
  }

  navigator.geolocation.getCurrentPosition(
    pos => {
      btnTxt.textContent = t.gps_latched;
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      initLeafletMap(type, lat, lng);
      setPointCoordinates(type, lat, lng, false);
      if (type === 'inline') setTimeout(() => _syncInlineGpsUI(lat, lng), 3100);
      else setTimeout(() => { btnTxt.textContent = oldText; }, 3000);
    },
    err => {
      console.warn('High-accuracy GPS timeout, retrying with low-accuracy:', err.message);
      navigator.geolocation.getCurrentPosition(
        pos => {
          btnTxt.textContent = t.gps_latched;
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;
          initLeafletMap(type, lat, lng);
          setPointCoordinates(type, lat, lng, false);
          if (type === 'inline') setTimeout(() => _syncInlineGpsUI(lat, lng), 3100);
          else setTimeout(() => { btnTxt.textContent = oldText; }, 3000);
        },
        err2 => {
          alert(t.gps_failed);
          btnTxt.textContent = oldText;
          toggleLeafletMap(type);
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 120000 }
      );
    },
    { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
  );
}

function handlePasteMapsLink(type, val) {
  if (!val) return;
  let lat = null, lng = null;
  const atMatch = val.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
  const qMatch = val.match(/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/);
  const coordMatch = val.match(/(-?\d+\.\d+)[,\s]+(-?\d+\.\d+)/);
  const dMatch = val.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);

  if (dMatch) {
    lat = parseFloat(dMatch[1]);
    lng = parseFloat(dMatch[2]);
  } else if (atMatch) {
    lat = parseFloat(atMatch[1]);
    lng = parseFloat(atMatch[2]);
  } else if (qMatch) {
    lat = parseFloat(qMatch[1]);
    lng = parseFloat(qMatch[2]);
  } else if (coordMatch) {
    lat = parseFloat(coordMatch[1]);
    lng = parseFloat(coordMatch[2]);
  }

  if (lat !== null && lng !== null) {
    initLeafletMap(type, lat, lng);
    setPointCoordinates(type, lat, lng, false);
  } else if (val.includes('maps') || val.includes('goo.gl')) {
    const feedbackEl = document.getElementById(`${type === 'new' ? 'new-cust' : 'inline-addr'}-map-feedback`);
    feedbackEl.className = 'text-[11px] font-bold text-amber-700 flex items-center gap-1.5';
    const isTe = (currentLang === 'te');
    const mapsRecordedMsg = isTe
      ? 'మ్యాప్స్ లింక్ నమోదైంది. డ్రైవర్ ఈ లొకేషన్ ఆధారంగా వస్తారు.'
      : 'Maps link recorded. Driver will navigate via share pin.';
    feedbackEl.innerHTML = `<span>📍</span> <span>${mapsRecordedMsg}</span>`;
  }
}

async function saveInlineAddress() {
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
  const label = document.getElementById('inline-addr-label').value.trim() || 'Home';
  const street = document.getElementById('inline-addr-street').value.trim();
  const landmark = document.getElementById('inline-addr-landmark').value.trim();
  const city = document.querySelector('input[name="inline_addr_city"]:checked')?.value || 'Hanamkonda';
  const lat = parseFloat(document.getElementById('inline-addr-lat').value) || null;
  const lng = parseFloat(document.getElementById('inline-addr-lng').value) || null;

  if (!street) {
    alert(t.street_required_alert);
    return;
  }

  if (lat !== null && lng !== null) {
    const check = checkGeofence(lat, lng);
    if (!check.valid) {
      alert(check.error);
      return;
    }
  }

  const phone = customerProfile.phone_number || customerProfile.phone || '';
  const fullName = customerProfile.full_name || '';

  try {
    const resp = await fetch('api/customer-lookup.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'register',
        phone_number: phone,
        full_name: fullName,
        label: label,
        delivery_address: street,
        landmark: landmark,
        region: city,
        latitude: lat,
        longitude: lng,
        is_default: 1
      })
    });
    const data = await resp.json();
    if (data.success && data.customer) {
      customerProfile = data.customer;
      selectedAddressId = data.customer.active_address?.id || (data.customer.addresses?.[0]?.id);
    } else {
      const newId = Date.now();
      const newAddr = {
        id: newId,
        label: label,
        delivery_address: street,
        landmark: landmark,
        region: city,
        latitude: lat,
        longitude: lng,
        is_default: true
      };
      if (!customerProfile.addresses) customerProfile.addresses = [];
      customerProfile.addresses.push(newAddr);
      customerProfile.active_address = newAddr;
      selectedAddressId = newId;
    }
  } catch (e) {
    const newId = Date.now();
    const newAddr = {
      id: newId,
      label: label,
      delivery_address: street,
      landmark: landmark,
      region: city,
      latitude: lat,
      longitude: lng,
      is_default: true
    };
    if (!customerProfile.addresses) customerProfile.addresses = [];
    customerProfile.addresses.push(newAddr);
    customerProfile.active_address = newAddr;
    selectedAddressId = newId;
  }

  saveProfilePhone();
  localStorage.setItem('ps_cust_profile', JSON.stringify(customerProfile));
  localStorage.setItem('ps_saved_profile', JSON.stringify(customerProfile));

  document.getElementById('inline-addr-label').value = '';
  document.getElementById('inline-addr-street').value = '';
  document.getElementById('inline-addr-landmark').value = '';
  document.getElementById('inline-addr-lat').value = '';
  document.getElementById('inline-addr-lng').value = '';
  toggleNewAddressForm(false);

  if (city !== currentRegion) {
    switchRegion(city);
  }

  renderSavedAddresses();
}

function showNewCustomerUI(phone) {
  document.getElementById('onboarding-step-1').classList.add('hidden');
  document.getElementById('onboarding-returning-card').classList.add('hidden');
  document.getElementById('onboarding-confirmed-strip').classList.add('hidden');
  const newCard = document.getElementById('onboarding-new-card');
  newCard.classList.remove('hidden');

  document.getElementById('cust-phone').value = phone;
}

async function saveNewCustomerAndProceed() {
  const name = document.getElementById('new-cust-name').value.trim();
  const addr = document.getElementById('new-cust-address').value.trim();
  const landmark = document.getElementById('new-cust-landmark').value.trim();
  const locality = document.querySelector('input[name="new_locality"]:checked')?.value || 'Hanamkonda';
  const phone = document.getElementById('onboarding-phone').value.trim() || document.getElementById('cust-phone').value.trim();
  const lat = parseFloat(document.getElementById('new-cust-lat').value) || null;
  const lng = parseFloat(document.getElementById('new-cust-lng').value) || null;

  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

  if (!name) {
    alert(t.name_required_alert);
    return;
  }
  if (!addr) {
    alert(t.address_required_alert);
    return;
  }

  if (lat !== null && lng !== null) {
    const check = checkGeofence(lat, lng);
    if (!check.valid) {
      alert(check.error);
      return;
    }
  }

  try {
    const resp = await fetch('api/customer-lookup.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'register',
        phone_number: phone,
        full_name: name,
        delivery_address: addr,
        landmark: landmark,
        region: locality,
        latitude: lat,
        longitude: lng,
        label: 'Home',
        is_default: 1
      })
    });
    const data = await resp.json();
    if (data.success && data.customer) {
      customerProfile = data.customer;
      selectedAddressId = data.customer.active_address?.id || (data.customer.addresses?.[0]?.id);
    } else {
      const addressObj = {
        id: Date.now(),
        label: 'Home',
        delivery_address: addr,
        landmark: landmark,
        region: locality,
        latitude: lat,
        longitude: lng,
        is_default: true
      };
      customerProfile = {
        full_name: name,
        phone_number: phone,
        delivery_address: addr,
        landmark: landmark,
        region: locality,
        latitude: lat,
        longitude: lng,
        active_address: addressObj,
        addresses: [addressObj]
      };
      selectedAddressId = addressObj.id;
    }
  } catch (err) {
    const addressObj = {
      id: Date.now(),
      label: 'Home',
      delivery_address: addr,
      landmark: landmark,
      region: locality,
      latitude: lat,
      longitude: lng,
      is_default: true
    };
    customerProfile = {
      full_name: name,
      phone_number: phone,
      delivery_address: addr,
      landmark: landmark,
      region: locality,
      latitude: lat,
      longitude: lng,
      active_address: addressObj,
      addresses: [addressObj]
    };
    selectedAddressId = addressObj.id;
  }

  saveProfilePhone();
  localStorage.setItem('ps_cust_profile', JSON.stringify(customerProfile));
  localStorage.setItem('ps_saved_profile', JSON.stringify(customerProfile));

  if (locality !== currentRegion) {
    switchRegion(locality);
  }

  confirmAddressAndProceed(true);
}

function confirmAddressAndProceed(shouldScroll = true) {
  if (!customerProfile) return;
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

  const activeAddr = (customerProfile.addresses || []).find(a => a.id === selectedAddressId) || customerProfile.active_address || customerProfile.addresses?.[0];
  if (!activeAddr) {
    alert(t.select_address_alert);
    return;
  }

  customerProfile.active_address = activeAddr;
  customerProfile.delivery_address = activeAddr.delivery_address;
  customerProfile.landmark = activeAddr.landmark;
  customerProfile.region = activeAddr.region;
  customerProfile.latitude = activeAddr.latitude;
  customerProfile.longitude = activeAddr.longitude;
  saveProfilePhone();
  localStorage.setItem('ps_cust_profile', JSON.stringify(customerProfile));
  localStorage.setItem('ps_saved_profile', JSON.stringify(customerProfile));

  document.getElementById('cust-name').value = customerProfile.full_name || '';
  document.getElementById('cust-phone').value = customerProfile.phone_number || customerProfile.phone || '';
  document.getElementById('cust-address').value = activeAddr.delivery_address || '';
  document.getElementById('cust-landmark').value = activeAddr.landmark || '';
  document.getElementById('cust-region').value = activeAddr.region || currentRegion;
  document.getElementById('cust-lat').value = activeAddr.latitude || '';
  document.getElementById('cust-lng').value = activeAddr.longitude || '';

  const displayRegion = activeAddr.region === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
  const gpsBadge = (activeAddr.latitude && activeAddr.longitude) ? ` <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-sky-100 text-sky-800 border border-sky-200">${t.gps_pinned_badge}</span>` : '';

  document.getElementById('confirmed-strip-name').textContent = customerProfile.full_name || '';
  document.getElementById('confirmed-strip-address').innerHTML = `${activeAddr.delivery_address}${activeAddr.landmark ? ' (' + activeAddr.landmark + ')' : ''} &bull; ${displayRegion}${gpsBadge}`;

  const nameParts = (customerProfile.full_name || '').trim().split(' ');
  const firstName = nameParts[0] || '';
  const bannerChip = document.getElementById('banner-address-chip');
  if (bannerChip) {
    bannerChip.textContent = firstName ? `${firstName} • ${displayRegion}` : displayRegion;
  }

  document.getElementById('drawer-summary-name').textContent = customerProfile.full_name || '';
  document.getElementById('drawer-summary-address').innerHTML = `${activeAddr.delivery_address}${activeAddr.landmark ? ' (' + activeAddr.landmark + ')' : ''} &bull; ${displayRegion}${gpsBadge}`;
  document.getElementById('drawer-summary-phone').textContent = `+91 ${customerProfile.phone_number || customerProfile.phone || ''}`;

  document.getElementById('onboarding-step-1').classList.add('hidden');
  document.getElementById('onboarding-returning-card').classList.add('hidden');
  document.getElementById('onboarding-new-card').classList.add('hidden');
  document.getElementById('onboarding-confirmed-strip').classList.remove('hidden');

  document.getElementById('locality-pills')?.classList.add('hidden');

  document.getElementById('catalog-locked-card')?.classList.add('hidden');
  document.getElementById('storefront-catalog-section')?.classList.remove('hidden');

  if (activeAddr.region && activeAddr.region !== currentRegion) {
    switchRegion(activeAddr.region);
  }

  if (shouldScroll) {
    const catalogEl = document.getElementById('vegetable-catalog');
    catalogEl?.scrollIntoView({ behavior: 'smooth' });
  }
}

function changeActiveAddress() {
  document.getElementById('onboarding-confirmed-strip')?.classList.add('hidden');
  document.getElementById('locality-pills')?.classList.remove('hidden');

  if (customerProfile && (customerProfile.phone_number || customerProfile.phone)) {
    showReturningCustomerUI(customerProfile);
  } else {
    document.getElementById('onboarding-step-1')?.classList.remove('hidden');
  }
  document.getElementById('onboarding-section')?.scrollIntoView({ behavior: 'smooth' });
}

function resetOnboardingPhone() {
  localStorage.removeItem('ps_cust_profile');
  localStorage.removeItem('ps_saved_profile');
  localStorage.removeItem('ps_customer_phone');
  customerProfile = null;
  selectedAddressId = null;
  document.getElementById('onboarding-phone').value = '';
  document.getElementById('onboarding-returning-card')?.classList.add('hidden');
  document.getElementById('onboarding-new-card')?.classList.add('hidden');
  document.getElementById('onboarding-confirmed-strip')?.classList.add('hidden');
  document.getElementById('onboarding-step-1')?.classList.remove('hidden');
  document.getElementById('btn-phone-continue')?.classList.add('hidden');

  const bannerChip = document.getElementById('banner-address-chip');
  if (bannerChip) {
    const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
    bannerChip.textContent = currentRegion === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
  }

  document.getElementById('locality-pills')?.classList.remove('hidden');

  document.getElementById('storefront-catalog-section')?.classList.add('hidden');
  document.getElementById('catalog-locked-card')?.classList.remove('hidden');
}

function editAddressFromDrawer() {
  closeCheckoutDrawer();
  changeActiveAddress();
}

// --------------------------------------------------------------------------
// Locality Switcher & Batch Synchronization
// --------------------------------------------------------------------------
async function switchRegion(region) {
  currentRegion = region;
  document.getElementById('cust-region').value = region;

  const pillH = document.getElementById('pill-hanamkonda');
  const pillW = document.getElementById('pill-warangal');

  if (pillH && pillW) {
    if (region === 'Hanamkonda') {
      pillH.className = 'btn py-2 px-3 rounded-xl font-extrabold text-xs sm:text-sm transition border-2 flex items-center justify-center gap-1.5 border-emerald-600 bg-emerald-50/70 text-emerald-900';
      pillW.className = 'btn py-2 px-3 rounded-xl font-extrabold text-xs sm:text-sm transition border-2 flex items-center justify-center gap-1.5 border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
    } else {
      pillW.className = 'btn py-2 px-3 rounded-xl font-extrabold text-xs sm:text-sm transition border-2 flex items-center justify-center gap-1.5 border-emerald-600 bg-emerald-50/70 text-emerald-900';
      pillH.className = 'btn py-2 px-3 rounded-xl font-extrabold text-xs sm:text-sm transition border-2 flex items-center justify-center gap-1.5 border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
    }
  }

  try {
    const resp = await fetch(`api/checkout.php?action=get_run_catalog&region=${region}`);
    const data = await resp.json();
    if (data.success && data.schedule) {
      activeSchedule = data.schedule;
      activeCatalog = data.catalog || [];
      updateScheduleHeaderUI();
      renderCatalogCards();
    }
  } catch (e) {
    console.error('Failed to load batch catalog for', region, e);
  }
}

function syncRegionFromDrawer(region) {
  if (region !== currentRegion) {
    switchRegion(region);
  }
}

function updateScheduleHeaderUI() {
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
  const isTe = (currentLang === 'te');

  const isFull = !!(activeSchedule?.is_batch_full);
  const fullBanner = document.getElementById('batch-full-banner');
  if (fullBanner) {
    fullBanner.classList.toggle('hidden', !isFull);
  }

  let dateText = activeSchedule?.delivery_fmt;
  if (!dateText && activeSchedule?.delivery_date) {
    try {
      const d = new Date(activeSchedule.delivery_date + 'T00:00:00');
      dateText = d.toLocaleDateString(isTe ? 'te-IN' : 'en-IN', { weekday: 'long', day: 'numeric', month: 'short' });
    } catch(e) {
      dateText = (activeSchedule.delivery_day ? activeSchedule.delivery_day + ', ' : '') + activeSchedule.delivery_date;
    }
  }

  const primaryEl = document.getElementById('banner-primary-line');
  if (primaryEl) {
    const prefix = t.delivering_prefix || (isTe ? 'డెలివరీ: ' : 'Delivering ');
    primaryEl.textContent = prefix + (dateText || (activeSchedule?.delivery_day || 'Scheduled Day'));
  }

  const sublineEl = document.getElementById('banner-subline');
  if (sublineEl) {
    const cutoffPrefix = t.order_before_prefix || (isTe ? 'ఆర్డర్ సమయం ' : 'Order before ');
    const freeDeliveryTag = t.free_delivery_tag || (isTe ? 'ఉచిత డెలివరీ' : 'Free Delivery');
    sublineEl.textContent = cutoffPrefix + (activeSchedule?.cutoff_fmt || '7:00 PM') + ' • ' + freeDeliveryTag;
  }

  const harvestStatusEl = document.getElementById('banner-harvest-status');
  if (harvestStatusEl) {
    harvestStatusEl.textContent = t.harvest_booking_open || (isTe ? 'హార్వెస్ట్ బుకింగ్ ఓపెన్' : 'Harvest Booking Open');
  }

  const bannerChip = document.getElementById('banner-address-chip');
  if (bannerChip) {
    const displayRegion = currentRegion === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
    const firstName = customerProfile?.full_name ? customerProfile.full_name.trim().split(' ')[0] : '';
    bannerChip.textContent = firstName ? `${firstName} • ${displayRegion}` : displayRegion;
  }

  const drawerDesc = document.getElementById('drawer-run-desc');
  if (drawerDesc && activeSchedule) {
    const regionLabel = currentRegion === 'Warangal' ? t.warangal_label : t.hanamkonda_label;
    drawerDesc.textContent = (activeSchedule.delivery_day || '') + (isTe ? ' బ్యాచ్ · ' : ' Batch · ') + regionLabel;
  }
}

function renderCatalogCards() {
  const container = document.getElementById('vegetables-container');
  const isTe = (currentLang === 'te');
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

  const countLabel = document.getElementById('catalog-count-label');
  if (countLabel) countLabel.textContent = t.varieties_label(activeCatalog.length);

  if (!activeCatalog.length) {
    container.innerHTML = `<div class="col-span-2 app-card text-center py-10 text-slate-400 text-sm">${t.catalog_empty}</div>`;
    return;
  }

  const trashSvg = '<svg class="w-4 h-4 fill-current inline-block" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';

  container.innerHTML = activeCatalog.map(prod => {
    const pid = parseInt(prod.product_id || prod.id, 10);
    const stock = parseInt(prod.available_half_kg_stock || 0, 10);
    const price = parseFloat(prod.price_per_half_kg || 0);
    const isSoldOut = (stock <= 0);
    const currQty = cart[pid] || 0;
    const mainName = isTe ? (prod.telugu_name || prod.name) : prod.name;
    const subName = isTe ? prod.name : (prod.telugu_name || '');
    const imgUrl = prod.image_path || '';
    const unitLabelText = prod.unit_label || '0.5 kg';

    let stepperHtml = '';
    if (isSoldOut) {
      stepperHtml = `<button type="button" disabled class="w-full py-2 text-[11px] font-bold text-slate-400 bg-slate-100 rounded-xl cursor-not-allowed text-center">${t.sold_out}</button>`;
    } else if (currQty === 0) {
      stepperHtml = `<button type="button" onclick="updateItemQty(${pid}, 1)" class="btn-add-modern w-full" aria-label="Add item">+ ADD</button>`;
    } else {
      const minusChar = currQty === 1 ? trashSvg : '−';
      stepperHtml = `
        <div class="stepper-active w-full">
          <button type="button" onclick="updateItemQty(${pid}, -1)" class="stepper-active-btn" aria-label="Decrease quantity">${minusChar}</button>
          <span class="stepper-active-count qty-val-${pid}">${currQty}</span>
          <button type="button" onclick="updateItemQty(${pid}, 1)" class="stepper-active-btn" aria-label="Increase quantity">+</button>
        </div>
      `;
    }

    return `
      <article class="app-card !p-3 rounded-2xl flex flex-col justify-between bg-white border border-slate-200 shadow-xs hover:shadow-md transition-all relative overflow-hidden ${isSoldOut ? 'opacity-60 bg-slate-50/80' : ''}" data-id="${pid}" data-telugu-name="${prod.telugu_name || ''}" data-english-name="${prod.name}">
        <!-- 1. TOP HALF: Prominent Vegetable Image Container (Takes ~50% Card Height) -->
        <div class="w-full aspect-[4/3] rounded-xl bg-slate-50 border border-slate-100 p-2 flex items-center justify-center relative shrink-0 overflow-hidden shadow-2xs">
          ${isSoldOut ? `<span class="absolute top-1.5 right-1.5 z-10 card-badge badge-slate font-bold text-[10px] py-0.5 px-2 whitespace-nowrap shadow-xs">${t.sold_out}</span>` : ''}
          ${imgUrl ? `<img src="${imgUrl}" alt="${prod.name}" class="w-full h-full object-contain transition-transform duration-300 hover:scale-105" onerror="this.outerHTML='<span class=\\'text-4xl\\'>🥬</span>'">` : `<span class="text-4xl">🥬</span>`}
        </div>

        <!-- 2. OTHER HALF: Stacked Name, Price/unit_label, and Count Stepper -->
        <div class="mt-2.5 flex-1 flex flex-col justify-between space-y-2">
          <!-- Top: Name (English + Telugu) -->
          <div>
            <h3 class="font-extrabold text-xs sm:text-sm text-slate-900 leading-snug prod-title prod-name" style="overflow-wrap: normal; word-break: keep-all;">${mainName}</h3>
            <div class="text-[11px] font-bold text-emerald-700 mt-0.5 truncate prod-subtitle">${subName}</div>
          </div>

          <!-- Middle: Price / per unit -->
          <div class="flex items-baseline gap-1 pt-1 border-t border-slate-100">
            <span class="font-extrabold text-sm sm:text-base text-slate-900 font-mono">
              ₹${price.toFixed(0)}
            </span>
            <span class="text-[11px] text-slate-500 font-medium whitespace-nowrap">
              / ${unitLabelText}
            </span>
          </div>

          <!-- Bottom: Count Stepper -->
          <div class="pt-1 stepper-container-${pid}">
            ${stepperHtml}
          </div>
        </div>
      </article>
    `;
  }).join('');
}

// --------------------------------------------------------------------------
// Cart Mechanics
// --------------------------------------------------------------------------
let updateQtyLock = false;

function updateItemQty(productId, change) {
  if (updateQtyLock) return;
  updateQtyLock = true;
  setTimeout(() => { updateQtyLock = false; }, 150);

  if (navigator.vibrate) {
    try { navigator.vibrate(30); } catch(e) {}
  }

  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
  const isTe = (currentLang === 'te');

  if (activeSchedule && activeSchedule.is_batch_full) {
    alert(t.batch_full_alert);
    return;
  }

  const prod = activeCatalog.find(p => parseInt(p.product_id || p.id, 10) === productId);
  if (!prod) return;

  const current = cart[productId] || 0;
  const stock = parseInt(prod.available_half_kg_stock || 0, 10);
  const next = current + change;

  if (next < 0) return;
  if (next > stock) {
    const prodDisplayName = isTe ? (prod.telugu_name || prod.name) : prod.name;
    alert(t.stock_limit_alert(prodDisplayName, stock));
    return;
  }

  if (next === 0) {
    delete cart[productId];
  } else {
    cart[productId] = next;
  }

  const stepperContainer = document.querySelector(`.stepper-container-${productId}`);
  if (stepperContainer) {
    if (next === 0) {
      stepperContainer.innerHTML = `<button type="button" onclick="updateItemQty(${productId}, 1)" class="btn-add-modern w-full" aria-label="Add item">+ ADD</button>`;
    } else {
      const trashSvg = '<svg class="w-4 h-4 fill-current inline-block" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
      const minusChar = next === 1 ? trashSvg : '−';
      stepperContainer.innerHTML = `
        <div class="stepper-active w-full">
          <button type="button" onclick="updateItemQty(${productId}, -1)" class="stepper-active-btn" aria-label="Decrease quantity">${minusChar}</button>
          <span class="stepper-active-count qty-val-${productId}">${next}</span>
          <button type="button" onclick="updateItemQty(${productId}, 1)" class="stepper-active-btn" aria-label="Increase quantity">+</button>
        </div>
      `;
    }
  }

  renderCartUI();
}

function renderCartUI() {
  let subtotal = 0.0;
  let totalPackets = 0;
  const drawerList = document.getElementById('drawer-items-list');
  const isTe = (currentLang === 'te');
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;

  const itemsHtml = [];

  for (const [pidStr, qty] of Object.entries(cart)) {
    const pid = parseInt(pidStr, 10);
    const prod = activeCatalog.find(p => parseInt(p.product_id || p.id, 10) === pid);
    if (!prod || qty <= 0) continue;

    const price = parseFloat(prod.price_per_half_kg || 0);
    const lineTotal = price * qty;
    subtotal += lineTotal;
    totalPackets += qty;

    const prodDisplayName = isTe ? (prod.telugu_name || prod.name) : prod.name;
    const weightKg = (qty * 0.5).toFixed(1);

    itemsHtml.push(`
      <div class="py-1.5 flex items-center justify-between">
        <div>
          <strong class="text-slate-900">${prodDisplayName}</strong>
          <span class="text-slate-500 font-mono text-[11px] block">${t.cart_item_qty(qty, weightKg)}</span>
        </div>
        <span class="font-mono font-bold text-slate-800">₹${lineTotal.toFixed(2)}</span>
      </div>
    `);
  }

  if (drawerList) {
    drawerList.innerHTML = itemsHtml.length ? itemsHtml.join('') : `<div class="text-slate-400 py-3 text-center italic">${t.cart_empty}</div>`;
  }

  const delivery = (subtotal >= movThreshold || subtotal === 0) ? 0.0 : standardDeliveryFee;
  const grandTotal = subtotal > 0 ? (subtotal + delivery) : 0.0;
  const totalWeightKg = (totalPackets * 0.5).toFixed(1);

  const bar = document.getElementById('sticky-cart-bar');
  if (totalPackets > 0) {
    bar.classList.remove('hidden');
    const summaryText = t.cart_summary_bar ? t.cart_summary_bar(totalPackets, grandTotal.toFixed(0)) : (totalPackets + ' Items | ₹' + grandTotal.toFixed(0));
    const subtext = totalWeightKg + ' kg • ' + (delivery === 0 ? t.delivery_free : ('+ ₹' + delivery + ' Delivery'));

    const summaryEl = document.getElementById('bar-items-summary');
    if (summaryEl) summaryEl.textContent = summaryText;
    const subtextEl = document.getElementById('bar-items-subtext');
    if (subtextEl) subtextEl.textContent = subtext;

    const btnDrawerText = document.getElementById('btn-open-drawer-text');
    if (btnDrawerText) btnDrawerText.textContent = t.btn_review_order || 'View Cart / చిరునామా & చెల్లింపు →';
  } else {
    bar.classList.add('hidden');
  }

  document.getElementById('drawer-items-weight').textContent = `${totalWeightKg} kg`;
  document.getElementById('bill-subtotal').textContent = `₹${subtotal.toFixed(2)}`;
  document.getElementById('bill-delivery').textContent = delivery === 0 ? t.delivery_free : `₹${delivery.toFixed(2)}`;
  document.getElementById('bill-total').textContent = `₹${grandTotal.toFixed(2)}`;

  const btnConfirm = document.getElementById('btn-confirm-order');
  const btnText = document.getElementById('btn-confirm-order-text');
  if (btnConfirm) {
    if (activeSchedule && activeSchedule.is_batch_full) {
      btnConfirm.disabled = true;
      if (btnText) btnText.textContent = t.batch_full_btn;
    } else {
      btnConfirm.disabled = (totalPackets === 0);
      if (btnText) btnText.textContent = t.btn_confirm_order;
    }
  }
}

// --------------------------------------------------------------------------
// Drawer Open / Close
// --------------------------------------------------------------------------
function openCheckoutDrawer() {
  if (customerProfile) {
    if (customerProfile.full_name) document.getElementById('cust-name').value = customerProfile.full_name;
    if (customerProfile.phone_number) document.getElementById('cust-phone').value = customerProfile.phone_number;
    if (customerProfile.delivery_address) document.getElementById('cust-address').value = customerProfile.delivery_address;
    if (customerProfile.landmark) document.getElementById('cust-landmark').value = customerProfile.landmark;
    if (customerProfile.region) document.getElementById('cust-region').value = customerProfile.region;
  }

  document.getElementById('checkout-backdrop').classList.remove('hidden');
  document.getElementById('checkout-drawer').classList.remove('hidden');
}

function closeCheckoutDrawer() {
  document.getElementById('checkout-backdrop').classList.add('hidden');
  document.getElementById('checkout-drawer').classList.add('hidden');
}

// --------------------------------------------------------------------------
// Order Submission & WhatsApp Sync
// --------------------------------------------------------------------------
async function submitOrder(e) {
  e.preventDefault();
  const t = CUSTOMER_I18N[currentLang] || CUSTOMER_I18N.te;
  const isTe = (currentLang === 'te');

  if (!activeSchedule || !activeSchedule.id) {
    alert(isTe ? 'దయచేసి ఒక డెలివరీ బ్యాచ్ ఎంచుకోండి.' : 'Please select an active delivery batch.');
    return;
  }

  if (activeSchedule.is_batch_full) {
    alert(t.batch_full_alert);
    return;
  }

  const itemsPayload = [];
  const itemLines = [];
  let subtotal = 0;

  for (const [pidStr, qty] of Object.entries(cart)) {
    if (qty > 0) {
      const pid = parseInt(pidStr, 10);
      const prod = activeCatalog.find(p => parseInt(p.product_id || p.id, 10) === pid);
      itemsPayload.push({ product_id: pid, quantity: qty });
      if (prod) {
        const price = parseFloat(prod.price_per_half_kg || 0);
        const lineTot = price * qty;
        subtotal += lineTot;
        const weight = (qty * 0.5).toFixed(1);
        const prodName = isTe ? (prod.telugu_name || prod.name) : prod.name;
        const pktWord = isTe ? 'ప్యాకెట్లు' : 'pkts';
        itemLines.push(`- ${prodName}: ${qty} ${pktWord} (${weight} kg) - ₹${lineTot.toFixed(0)}`);
      }
    }
  }

  if (!itemsPayload.length) {
    alert(t.empty_basket_alert);
    return;
  }

  const form = document.getElementById('checkout-form');
  const formData = new FormData(form);

  const customerData = {
    full_name: formData.get('full_name').toString().trim(),
    phone_number: formData.get('phone_number').toString().trim(),
    delivery_address: formData.get('delivery_address').toString().trim(),
    landmark: formData.get('landmark')?.toString().trim() || null,
    region: formData.get('region').toString().trim(),
    latitude: formData.get('latitude') ? parseFloat(formData.get('latitude').toString()) : null,
    longitude: formData.get('longitude') ? parseFloat(formData.get('longitude').toString()) : null
  };

  saveProfilePhone();
  localStorage.setItem('ps_cust_profile', JSON.stringify(customerData));
  localStorage.setItem('ps_saved_profile', JSON.stringify(customerData));

  const paymentMethod = formData.get('payment_method')?.toString() || 'COD';
  const btn = document.getElementById('btn-confirm-order');
  btn.disabled = true;
  btn.textContent = t.saving_order;

  try {
    const resp = await fetch('api/checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        schedule_id: parseInt(activeSchedule.id, 10),
        customer: customerData,
        items: itemsPayload,
        payment_method: paymentMethod,
        lang: currentLang
      })
    });

    const data = await resp.json();

    if (data.success && data.order_code) {
      const orderCode = data.order_code;
      const deliveryFee = (subtotal >= movThreshold || subtotal === 0) ? 0 : standardDeliveryFee;
      const totalAmount = subtotal + deliveryFee;
      const host = window.location.origin + window.location.pathname.replace('index.php', '');
      const trackUrl = `${host}track.php?code=${orderCode}`;

      const payMethodLabel = paymentMethod === 'COD' 
        ? (isTe ? 'క్యాష్ ఆన్ డెలివరీ' : 'Cash on Delivery') 
        : (isTe ? 'ఆన్‌లైన్ UPI' : 'Online UPI');

      const waMessage = isTe 
        ? `🌱 *ప్రకృతి సిరి - ఆర్డర్ నిర్ధారణ*
ఆర్డర్ కోడ్: #${orderCode}
కస్టమర్: ${customerData.full_name}
ఫోన్: ${customerData.phone_number}
డెలివరీ బ్యాచ్: ${activeSchedule.delivery_day || ''}, ${activeSchedule.delivery_date || ''} (${activeSchedule.target_region || currentRegion})
చిరునామా: ${customerData.delivery_address}${customerData.landmark ? ', ' + customerData.landmark : ''}

కూరగాయలు:
${itemLines.join('\n')}

మొత్తం: ₹${totalAmount.toFixed(2)} (${payMethodLabel})
📍 ఆర్డర్ లైవ్ ట్రాకింగ్: ${trackUrl}`
        : `🌱 *Prakruthi Siri - Order Confirmation*
Order Code: #${orderCode}
Customer: ${customerData.full_name}
Phone: ${customerData.phone_number}
Batch: ${activeSchedule.delivery_day || ''}, ${activeSchedule.delivery_date || ''} (${activeSchedule.target_region || currentRegion})
Address: ${customerData.delivery_address}${customerData.landmark ? ', ' + customerData.landmark : ''}

Items:
${itemLines.join('\n')}

Total Amount: ₹${totalAmount.toFixed(2)} (${payMethodLabel})
📍 Track Order: ${trackUrl}`;

      const waUrl = `https://wa.me/${storeWhatsApp.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(waMessage)}`;

      cart = {};
      localStorage.removeItem('ps_cart');

      window.location.href = `order-success.php?code=${encodeURIComponent(orderCode)}&auto_wa=1`;

    } else {
      alert(data.error || data.message || (isTe ? 'ఆర్డర్ చేయడం విఫలమైంది.' : 'Failed to place order.'));
      btn.disabled = false;
      btn.textContent = t.btn_confirm_order;
    }
  } catch (err) {
    alert((isTe ? 'నెట్‌వర్క్ సమస్య: ' : 'Network Error: ') + err.message);
    btn.disabled = false;
    btn.textContent = t.btn_confirm_order;
  }
}

// Init Language
document.getElementById('btn-lang-te')?.addEventListener('click', () => setCustomerLanguage('te'));
document.getElementById('btn-lang-en')?.addEventListener('click', () => setCustomerLanguage('en'));
setCustomerLanguage(currentLang);

// Restore returning customer AFTER i18n is applied
(async () => {
  try {
    const savedPhone = localStorage.getItem('ps_customer_phone');
    if (savedPhone) {
      await performPhoneLookup(savedPhone);
      if (customerProfile && customerProfile.active_address) {
        selectedAddressId = customerProfile.active_address.id || customerProfile.addresses?.[0]?.id;
        confirmAddressAndProceed(false);
      }
      return;
    }
    const saved = localStorage.getItem('ps_cust_profile') || localStorage.getItem('ps_saved_profile');
    if (saved) {
      customerProfile = JSON.parse(saved);
      if (customerProfile && (customerProfile.phone_number || customerProfile.phone)) {
        const phone = customerProfile.phone_number || customerProfile.phone;
        localStorage.setItem('ps_customer_phone', phone.replace(/\D/g, '').slice(-10));
        selectedAddressId = customerProfile.active_address?.id || customerProfile.addresses?.[0]?.id;
        confirmAddressAndProceed(false);
      }
    }
  } catch(e) {}
})();
