/**
 * Prakruthi Siri - Driver App Camera & GPS Delivery Engine
 * Handles geolocation, camera image compression, delivery verification modal, and PWA installation.
 */

let currentDriverLang = localStorage.getItem('ps_driver_lang') || 'te';
let activeOrderId = 0;
let activeCustomerId = 0;
let currentGeoLat = null;
let currentGeoLng = null;

function acquireGpsLocation() {
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition(
    pos => { 
      currentGeoLat = pos.coords.latitude; 
      currentGeoLng = pos.coords.longitude; 
    },
    err => { 
      console.warn('High accuracy GPS timed out (8s), falling back to standard accuracy:', err.message);
      navigator.geolocation.getCurrentPosition(
        pos => { 
          currentGeoLat = pos.coords.latitude; 
          currentGeoLng = pos.coords.longitude; 
        },
        err2 => { 
          console.warn('Geolocation completely unavailable or indoor timeout:', err2.message); 
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 120000 }
      );
    },
    { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
  );
}

function setDriverLanguage(lang) {
  currentDriverLang = lang;
  localStorage.setItem('ps_driver_lang', lang);
  if (typeof DRIVER_I18N === 'undefined') return;

  const isTe = (lang === 'te');
  const t = DRIVER_I18N[lang] || DRIVER_I18N.te;

  const btnTe = document.getElementById('driver-lang-te');
  const btnEn = document.getElementById('driver-lang-en');

  if (isTe) {
    if (btnTe) btnTe.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition';
    if (btnEn) btnEn.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 transition';
  } else {
    if (btnEn) btnEn.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition';
    if (btnTe) btnTe.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 transition';
  }

  const elSub = document.getElementById('driver-app-sub');
  if (elSub) elSub.textContent = t.app_bar_subtitle;

  const elLogout = document.getElementById('link-driver-logout');
  if (elLogout) elLogout.textContent = t.sign_out;

  const elSched = document.getElementById('label-scheduled-run');
  if (elSched) elSched.textContent = t.scheduled_run;

  const elStops = document.getElementById('label-stops');
  if (elStops) elStops.textContent = t.stops_label;

  const elCod = document.getElementById('label-cod');
  if (elCod) elCod.textContent = t.cod_to_collect_label;

  document.querySelectorAll('.btn-call-text').forEach(el => el.textContent = t.call_btn);
  document.querySelectorAll('.btn-wa-text').forEach(el => el.textContent = t.whatsapp_btn || 'WhatsApp');
  document.querySelectorAll('.btn-map-text').forEach(el => el.textContent = t.map_btn);
  document.querySelectorAll('.btn-complete-text').forEach(el => el.textContent = t.deliver_verify_btn);
  document.querySelectorAll('.btn-quick-deliver-text').forEach(el => el.textContent = t.quick_deliver_btn);
  document.querySelectorAll('.btn-first-deliver-text').forEach(el => {
    if (el.dataset.hasPhoto === '1') {
      el.textContent = t.gps_pin_needed_btn || (isTe ? '📍 జీపీఎస్ లొకేషన్ & డెలివరీ' : '📍 Capture GPS & Deliver');
    } else {
      el.textContent = t.first_delivery_btn;
    }
  });
}

function openProofModal(orderId, customerId, orderCode, customerName, isCod, totalAmount) {
  activeOrderId = orderId;
  activeCustomerId = customerId;

  const t = (typeof DRIVER_I18N !== 'undefined' && DRIVER_I18N[currentDriverLang]) ? DRIVER_I18N[currentDriverLang] : (typeof DRIVER_I18N !== 'undefined' ? DRIVER_I18N.te : {});
  const modalTitle = document.getElementById('modal-title');
  if (modalTitle) modalTitle.textContent = customerName + (currentDriverLang === 'te' ? ' గారి డెలివరీ' : ' Delivery');

  const modalCode = document.getElementById('modal-order-code');
  if (modalCode) modalCode.textContent = (currentDriverLang === 'te' ? 'ఆర్డర్ #' : 'Order #') + orderCode;

  const codBox = document.getElementById('modal-cod-box');
  if (codBox) {
    if (isCod) {
      codBox.classList.remove('hidden');
      const codAmt = document.getElementById('modal-cod-amount');
      if (codAmt) codAmt.textContent = '₹' + Number(totalAmount).toFixed(2);
    } else {
      codBox.classList.add('hidden');
    }
  }

  const gateInput = document.getElementById('gate-photo-input');
  if (gateInput) gateInput.value = '';
  const previewWrap = document.getElementById('photo-preview-wrap');
  if (previewWrap) previewWrap.classList.add('hidden');

  const proofModal = document.getElementById('proof-modal');
  if (proofModal) proofModal.classList.remove('hidden');
}

function closeProofModal() {
  const proofModal = document.getElementById('proof-modal');
  if (proofModal) proofModal.classList.add('hidden');
  activeOrderId = 0;
  activeCustomerId = 0;
}

async function compressImageFile(file, maxDimension = 1600, quality = 0.8) {
  return new Promise((resolve) => {
    if (!file || file.size < 800 * 1024) {
      resolve(file);
      return;
    }
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        let width = img.width;
        let height = img.height;
        if (width > maxDimension || height > maxDimension) {
          if (width > height) {
            height = Math.round((height * maxDimension) / width);
            width = maxDimension;
          } else {
            width = Math.round((width * maxDimension) / height);
            height = maxDimension;
          }
        }
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        canvas.toBlob((blob) => {
          if (blob) {
            const compressedFile = new File([blob], (file.name || 'gate_photo.jpg').replace(/\.[^.]+$/, '.jpg'), {
              type: 'image/jpeg',
              lastModified: Date.now()
            });
            resolve(compressedFile);
          } else {
            resolve(file);
          }
        }, 'image/jpeg', quality);
      };
      img.onerror = () => resolve(file);
      img.src = e.target.result;
    };
    reader.onerror = () => resolve(file);
    reader.readAsDataURL(file);
  });
}

async function submitDeliveryProof() {
  const t = (typeof DRIVER_I18N !== 'undefined' && DRIVER_I18N[currentDriverLang]) ? DRIVER_I18N[currentDriverLang] : (typeof DRIVER_I18N !== 'undefined' ? DRIVER_I18N.te : {});
  const fileInput = document.getElementById('gate-photo-input');
  if (!fileInput || !fileInput.files || !fileInput.files[0]) {
    alert(t.photo_required_alert || 'Please take a photo of the doorstep or gate.');
    return;
  }

  const btn = document.getElementById('btn-confirm-delivery');
  const btnText = document.getElementById('btn-confirm-delivery-text');
  if (btn) btn.disabled = true;
  if (btnText) btnText.textContent = t.saving_proof_btn || 'Saving photo...';

  try {
    const rawPhoto = fileInput.files[0];
    const compressedPhoto = await compressImageFile(rawPhoto);

    const formData = new FormData();
    formData.append('order_id', activeOrderId);
    formData.append('customer_id', activeCustomerId);
    formData.append('gate_photo', compressedPhoto);
    if (currentGeoLat && currentGeoLng) {
      formData.append('latitude', currentGeoLat);
      formData.append('longitude', currentGeoLng);
    }

    const resp = await fetch('api/verify-delivery.php', {
      method: 'POST',
      body: formData
    });
    const data = await resp.json();

    if (data.success) {
      alert(t.delivery_success_alert || 'Delivery completed successfully!');
      window.location.reload();
    } else {
      alert('Error: ' + (data.error || 'Failed to complete delivery'));
      if (btn) btn.disabled = false;
      if (btnText) btnText.textContent = t.confirm_delivery_btn || 'Complete & Deliver';
    }
  } catch (err) {
    alert('Network error: ' + err.message);
    if (btn) btn.disabled = false;
    if (btnText) btnText.textContent = t.confirm_delivery_btn || 'Complete & Deliver';
  }
}

let activeQdOrderId = 0;
let isQdCod = false;

function openQuickDeliverModal(orderId, orderCode, customerName, address, isCod, totalAmount) {
  activeQdOrderId = orderId;
  isQdCod = isCod;

  const qdCode = document.getElementById('qd-order-code');
  if (qdCode) qdCode.textContent = '#' + orderCode;

  const qdName = document.getElementById('qd-customer-name');
  if (qdName) qdName.textContent = customerName;

  const qdAddr = document.getElementById('qd-address');
  if (qdAddr) qdAddr.textContent = address;

  const codBox = document.getElementById('qd-cod-box');
  const upiBox = document.getElementById('qd-upi-box');
  const confirmBtn = document.getElementById('btn-qd-confirm');
  const codCheckbox = document.getElementById('qd-cod-checkbox');

  if (isCod) {
    if (codBox) codBox.classList.remove('hidden');
    if (upiBox) upiBox.classList.add('hidden');
    const qdAmt = document.getElementById('qd-cod-amount');
    if (qdAmt) qdAmt.textContent = '₹' + Number(totalAmount).toFixed(2);
    if (codCheckbox) codCheckbox.checked = false;
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
  } else {
    if (codBox) codBox.classList.add('hidden');
    if (upiBox) upiBox.classList.remove('hidden');
    if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
  }

  const qdModal = document.getElementById('quick-deliver-modal');
  if (qdModal) qdModal.classList.remove('hidden');
}

function toggleQuickDeliverSubmit() {
  const confirmBtn = document.getElementById('btn-qd-confirm');
  const codCheckbox = document.getElementById('qd-cod-checkbox');
  if (isQdCod && confirmBtn && codCheckbox) {
    confirmBtn.disabled = !codCheckbox.checked;
    if (codCheckbox.checked) {
      confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
      confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
  }
}

function closeQuickDeliverModal() {
  const qdModal = document.getElementById('quick-deliver-modal');
  if (qdModal) qdModal.classList.add('hidden');
  activeQdOrderId = 0;
}

async function executeQuickDeliver() {
  if (isQdCod) {
    const codCheckbox = document.getElementById('qd-cod-checkbox');
    if (codCheckbox && !codCheckbox.checked) {
      alert('Please verify cash collection by checking the confirmation box.');
      return;
    }
  }

  const btn = document.getElementById('btn-qd-confirm');
  const btnText = document.getElementById('btn-qd-confirm-text');
  if (btn) btn.disabled = true;
  if (btnText) btnText.textContent = (currentDriverLang === 'te' ? 'నమోదు చేస్తోంది...' : 'Marking Delivered...');

  try {
    const resp = await fetch('api/verify-delivery.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'quick_deliver',
        order_id: activeQdOrderId
      })
    });
    const data = await resp.json();

    if (data.success) {
      const t = (typeof DRIVER_I18N !== 'undefined' && DRIVER_I18N[currentDriverLang]) ? DRIVER_I18N[currentDriverLang] : (typeof DRIVER_I18N !== 'undefined' ? DRIVER_I18N.te : {});
      alert(t.delivery_success_alert || 'Delivery verified and stop completed successfully!');
      window.location.reload();
    } else {
      alert('Error: ' + (data.error || 'Failed to update order'));
      if (btn) btn.disabled = false;
      if (btnText) btnText.textContent = 'Confirm Delivery';
    }
  } catch (err) {
    alert('Network error: ' + err.message);
    if (btn) btn.disabled = false;
    if (btnText) btnText.textContent = 'Confirm Delivery';
  }
}

// --------------------------------------------------------------------------
// Driver PWA Installation Logic
// --------------------------------------------------------------------------
let deferredDriverPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredDriverPrompt = e;
});

function triggerDriverPwaInstall() {
  if (deferredDriverPrompt) {
    deferredDriverPrompt.prompt();
    deferredDriverPrompt.userChoice.then((choice) => {
      if (choice.outcome === 'accepted') {
        const btn = document.getElementById('btn-pwa-install-driver');
        if (btn) btn.classList.add('hidden');
      }
      deferredDriverPrompt = null;
    });
  } else {
    const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;
    if (isIos) {
      alert('To install PS Driver on your iPhone/iPad:\n\n1. Tap the Share button 📤 in Safari.\n2. Scroll down & tap "Add to Home Screen" ➕.');
    } else {
      alert('To install PS Driver:\n\nIn Chrome/Edge menu (⋮), select "Install App" or "Add to Home Screen".');
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  acquireGpsLocation();

  document.getElementById('gate-photo-input')?.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (evt) {
      const previewImg = document.getElementById('photo-preview');
      if (previewImg) previewImg.src = evt.target.result;
      const previewWrap = document.getElementById('photo-preview-wrap');
      if (previewWrap) previewWrap.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
  });

  document.getElementById('driver-lang-te')?.addEventListener('click', () => setDriverLanguage('te'));
  document.getElementById('driver-lang-en')?.addEventListener('click', () => setDriverLanguage('en'));

  setDriverLanguage(currentDriverLang);

  if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
    const installBtn = document.getElementById('btn-pwa-install-driver');
    if (installBtn) installBtn.classList.add('hidden');
  }
});
