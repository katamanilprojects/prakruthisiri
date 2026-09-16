<?php
declare(strict_types=1);

/**
 * Prakruthi Siri - Run Calendar & Cutoff Controls
 * CDCApp Standard: Inter Font, Slate-50 Background, Emerald Actions
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
use PrakruthiSiri\Config\Database;

$activePage = 'schedules';

$pdo = Database::getInstance()->getConnection();

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
$defaultDeliveryDate = $now->modify('+1 day')->format('Y-m-d');
$defaultDeliveryDay  = $now->modify('+1 day')->format('l');
$defaultHarvestDate  = $now->format('Y-m-d');
$defaultOpenTime     = $now->format('Y-m-d\T05:00');
$defaultCutoffTime   = $now->format('Y-m-d\T19:00');
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Batch Calendar &amp; Cutoff Controls | Prakruthi Siri</title>
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0f172a">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="PS Admin">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="../assets/icons/icon-192.png">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(err => console.error('SW reg error:', err));
      });
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/admin-theme.css">
</head>
<body class="min-h-full font-sans antialiased text-slate-900 bg-slate-50 flex flex-col pb-16">
  <?php require __DIR__ . '/includes/masthead.php'; ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 space-y-5">
    <!-- Toast Banner -->
    <div id="sched-toast" class="hidden p-3.5 rounded-xl text-xs font-semibold flex items-center justify-between border shadow-xs">
      <span id="sched-toast-msg"></span>
      <button type="button" onclick="this.parentElement.classList.add('hidden')" class="font-bold text-base leading-none">&times;</button>
    </div>

    <!-- Header Action Ribbon -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
          <span>Scheduled Delivery Batches (Calendar &amp; Cutoffs)</span>
        </h2>
        <p class="text-xs text-slate-500 mt-0.5">
          Plan upcoming deliveries 1, 2, or 3 days in advance. Standard ordering window: 5:00 AM – 7:00 PM (19:00 IST) on harvest day.
        </p>
      </div>

      <button 
        type="button" 
        id="btn-open-schedule-modal"
        class="btn btn-primary text-xs h-9 min-h-[36px] px-4 font-bold self-start sm:self-auto"
      >
        <span>+</span>
        <span>Schedule New Delivery Batch</span>
      </button>
    </div>

    <!-- Schedules Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="enterprise-table">
          <thead>
            <tr>
              <th>Delivery Batch (Day &amp; Date)</th>
              <th>Target Locality</th>
              <th>Ordering Window (Open – Cutoff)</th>
              <th>Harvest Date</th>
              <th class="text-right">Orders Booked</th>
              <th class="text-right">Revenue (₹)</th>
              <th class="text-center">Status</th>
              <th class="text-center">Quick Controls</th>
            </tr>
          </thead>
          <tbody id="tbody-schedules">
            <tr>
              <td colspan="8" class="text-center py-10 text-slate-400 font-medium">Loading delivery batch schedules...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Modal: Schedule New Delivery Batch -->
  <div id="schedule-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 hidden items-center justify-center p-4">
    <div class="max-w-lg w-full bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
      <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-sm text-slate-900">Schedule Delivery Batch</h3>
        <button type="button" id="btn-close-modal" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:text-slate-800 hover:bg-slate-200 flex items-center justify-center text-lg font-bold transition">&times;</button>
      </div>

      <form id="form-new-schedule" class="p-5 space-y-4 text-xs">
        <div class="grid grid-cols-2 gap-3">
          <!-- Delivery Date -->
          <div>
            <label for="modal-delivery-date" class="block font-bold text-slate-700 mb-1">Delivery Date <span class="text-rose-500">*</span></label>
            <input 
              type="date" 
              id="modal-delivery-date" 
              name="delivery_date" 
              value="<?= $defaultDeliveryDate ?>" 
              min="<?= date('Y-m-d') ?>" 
              required 
              class="compact-input w-full font-mono font-semibold"
            >
          </div>

          <!-- Day Name -->
          <div>
            <label for="modal-delivery-day" class="block font-bold text-slate-700 mb-1">Day of Week</label>
            <input 
              type="text" 
              id="modal-delivery-day" 
              name="delivery_day" 
              value="<?= $defaultDeliveryDay ?>" 
              readonly 
              class="compact-input w-full bg-slate-100 text-slate-600 font-semibold cursor-not-allowed"
            >
          </div>
        </div>

        <!-- Target Locality -->
        <div>
          <label for="modal-target-region" class="block font-bold text-slate-700 mb-1">Target Locality <span class="text-rose-500">*</span></label>
          <select id="modal-target-region" name="target_region" class="compact-select w-full font-semibold">
            <option value="Hanamkonda" selected>Hanamkonda (Tuesday Batch Default)</option>
            <option value="Warangal">Warangal (Saturday Batch Default)</option>
          </select>
          <p class="text-[11px] text-slate-400 mt-1">Operational routes strictly restricted to Hanamkonda and Warangal (Kazipet excluded).</p>
        </div>

        <!-- Ordering Window -->
        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5">
          <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wider">Automated Ordering Window (IST)</div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <div>
              <label for="modal-open-datetime" class="block font-semibold text-slate-600 mb-0.5 text-[11px]">Opens At</label>
              <input 
                type="datetime-local" 
                id="modal-open-datetime" 
                name="order_open_datetime" 
                value="<?= $defaultOpenTime ?>" 
                required 
                class="compact-input w-full font-mono text-[11px]"
              >
            </div>
            <div>
              <label for="modal-cutoff-datetime" class="block font-semibold text-slate-600 mb-0.5 text-[11px]">Cutoff Time (Closes)</label>
              <input 
                type="datetime-local" 
                id="modal-cutoff-datetime" 
                name="cutoff_datetime" 
                value="<?= $defaultCutoffTime ?>" 
                required 
                class="compact-input w-full font-mono text-[11px]"
              >
            </div>
          </div>
          <p class="text-[10px] text-slate-500">Defaults automatically to the day before harvest: 05:00 AM opens &rarr; 19:00 (7:00 PM) hard cutoff.</p>
        </div>

        <!-- Harvest Date -->
        <div>
          <label for="modal-harvest-date" class="block font-bold text-slate-700 mb-1">Harvest Date <span class="text-rose-500">*</span></label>
          <input 
            type="date" 
            id="modal-harvest-date" 
            name="harvest_date" 
            value="<?= $defaultHarvestDate ?>" 
            required 
            class="compact-input w-full font-mono font-semibold"
          >
        </div>

        <!-- Initial Ordering Status -->
        <div>
          <label for="modal-ordering-open" class="block font-bold text-slate-700 mb-1">Initial Ordering State</label>
          <select id="modal-ordering-open" name="is_ordering_open" class="compact-select w-full font-semibold">
            <option value="1" selected>Open (Storefront accepts orders within window)</option>
            <option value="0">Closed / Paused</option>
          </select>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
          <button type="button" id="btn-cancel-modal" class="btn btn-secondary text-xs h-8 min-h-[32px] px-3 font-semibold">Cancel</button>
          <button type="submit" id="btn-submit-schedule" class="btn btn-primary text-xs h-8 min-h-[32px] px-4 font-bold">Save Delivery Batch</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function () {
      'use strict';

      const tbody = document.getElementById('tbody-schedules');
      const modal = document.getElementById('schedule-modal');
      const form = document.getElementById('form-new-schedule');
      const dateInput = document.getElementById('modal-delivery-date');
      const dayInput = document.getElementById('modal-delivery-day');
      const openInput = document.getElementById('modal-open-datetime');
      const cutoffInput = document.getElementById('modal-cutoff-datetime');
      const harvestInput = document.getElementById('modal-harvest-date');

      // Auto update day and default window when delivery date changes
      const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
      dateInput.addEventListener('change', () => {
        const val = dateInput.value;
        if (!val) return;
        const d = new Date(val + 'T00:00:00');
        dayInput.value = dayNames[d.getDay()];

        const prev = new Date(d);
        prev.setDate(prev.getDate() - 1);
        const y = prev.getFullYear();
        const m = String(prev.getMonth() + 1).padStart(2, '0');
        const dt = String(prev.getDate()).padStart(2, '0');

        harvestInput.value = `${y}-${m}-${dt}`;
        openInput.value = `${y}-${m}-${dt}T05:00`;
        cutoffInput.value = `${y}-${m}-${dt}T19:00`;
      });

      async function loadSchedules() {
        try {
          const resp = await fetch('api/schedule-api.php?action=get_schedules');
          const data = await resp.json();
          if (!data.success) throw new Error(data.error || 'Failed to fetch schedules');
          renderTable(data.schedules);
        } catch (err) {
          tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-red-600 font-semibold">Error: ${escapeHtml(err.message)}</td></tr>`;
        }
      }

      function renderTable(schedules) {
        tbody.innerHTML = '';
        if (!schedules || schedules.length === 0) {
          tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-slate-400">No delivery runs scheduled yet.</td></tr>`;
          return;
        }

        schedules.forEach((s) => {
          const tr = document.createElement('tr');
          let stateBadge = '';
          if (s.is_effectively_open) {
            stateBadge = '<span class="badge-status badge-status-delivered">Open &amp; Active</span>';
          } else if (s.is_cutoff_passed) {
            stateBadge = '<span class="badge-status badge-status-placed">Cutoff Passed</span>';
          } else {
            stateBadge = '<span class="badge-status badge-status-cancelled">Paused</span>';
          }

          tr.innerHTML = `
            <td>
              <div class="font-bold text-slate-900">${escapeHtml(s.delivery_day || '')}, ${escapeHtml(s.delivery_date)}</div>
              <div class="text-[11px] text-slate-500 font-mono">${escapeHtml(s.delivery_date_fmt)}</div>
            </td>
            <td>
              <span class="font-semibold text-xs px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                ${escapeHtml(s.target_region || 'Hanamkonda')}
              </span>
            </td>
            <td class="font-mono text-xs">
              <div>Open: <strong class="text-slate-800">${escapeHtml(s.order_open_fmt || '-')}</strong></div>
              <div>Cutoff: <strong class="${s.is_cutoff_passed ? 'text-red-600' : 'text-emerald-700'}">${escapeHtml(s.cutoff_datetime_fmt)}</strong></div>
            </td>
            <td class="font-mono text-xs text-slate-600">
              ${escapeHtml(s.harvest_date_fmt || s.harvest_date)}
            </td>
            <td class="text-right font-mono font-bold text-slate-900">
              ${s.orders_count}
            </td>
            <td class="text-right font-mono font-bold text-emerald-700">
              ${s.orders_revenue_fmt}
            </td>
            <td class="text-center">
              ${stateBadge}
            </td>
            <td class="text-center whitespace-nowrap space-x-1">
              <button 
                type="button" 
                class="btn-toggle btn btn-secondary text-xs h-7 min-h-[28px] px-2 font-bold ${s.is_ordering_open == 1 ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50'}"
                data-id="${s.id}"
              >
                ${s.is_ordering_open == 1 ? 'Pause' : 'Resume'}
              </button>
              <button 
                type="button" 
                class="btn-extend-1h btn btn-secondary text-xs h-7 min-h-[28px] px-2 font-semibold"
                data-id="${s.id}"
                title="Extend cutoff by 1 hour"
              >
                +1h
              </button>
              <button 
                type="button" 
                class="btn-extend-2h btn btn-secondary text-xs h-7 min-h-[28px] px-2 font-semibold"
                data-id="${s.id}"
                title="Extend cutoff by 2 hours"
              >
                +2h
              </button>
            </td>
          `;

          tr.querySelector('.btn-toggle').addEventListener('click', () => toggleOrdering(s.id));
          tr.querySelector('.btn-extend-1h').addEventListener('click', () => extendCutoff(s.id, 1));
          tr.querySelector('.btn-extend-2h').addEventListener('click', () => extendCutoff(s.id, 2));

          tbody.appendChild(tr);
        });
      }

      async function toggleOrdering(id) {
        try {
          const resp = await fetch('api/schedule-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_ordering', schedule_id: id })
          });
          const data = await resp.json();
          if (!data.success) throw new Error(data.error);
          showToast(data.message, true);
          loadSchedules();
        } catch (err) {
          showToast(err.message, false);
        }
      }

      async function extendCutoff(id, hours) {
        try {
          const resp = await fetch('api/schedule-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'extend_cutoff', schedule_id: id, hours: hours })
          });
          const data = await resp.json();
          if (!data.success) throw new Error(data.error);
          showToast(data.message, true);
          loadSchedules();
        } catch (err) {
          showToast(err.message, false);
        }
      }

      function showToast(msg, ok) {
        const toast = document.getElementById('sched-toast');
        const text = document.getElementById('sched-toast-msg');
        text.textContent = msg;
        toast.className = ok 
          ? 'p-3.5 rounded-xl text-xs font-semibold flex items-center justify-between border bg-emerald-50 text-emerald-900 border-emerald-300 shadow-xs'
          : 'p-3.5 rounded-xl text-xs font-semibold flex items-center justify-between border bg-red-50 text-red-900 border-red-300 shadow-xs';
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 4000);
      }

      function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
      }

      // Modal Controls
      document.getElementById('btn-open-schedule-modal').addEventListener('click', () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
      });
      document.getElementById('btn-close-modal').addEventListener('click', () => {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
      });
      document.getElementById('btn-cancel-modal').addEventListener('click', () => {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
      });

      // Submit new schedule
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
          action: 'save_schedule',
          delivery_date: document.getElementById('modal-delivery-date').value,
          target_region: document.getElementById('modal-target-region').value,
          delivery_day: document.getElementById('modal-delivery-day').value,
          order_open_datetime: document.getElementById('modal-open-datetime').value,
          cutoff_datetime: document.getElementById('modal-cutoff-datetime').value,
          harvest_date: document.getElementById('modal-harvest-date').value,
          is_ordering_open: parseInt(document.getElementById('modal-ordering-open').value, 10),
          status: 'scheduled'
        };

        try {
          const resp = await fetch('api/schedule-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const data = await resp.json();
          if (!data.success) throw new Error(data.error);

          showToast(data.message, true);
          modal.classList.remove('flex');
          modal.classList.add('hidden');
          loadSchedules();
        } catch (err) {
          alert('Error saving schedule: ' + err.message);
        }
      });

      // Initial load
      loadSchedules();
    })();
  </script>
</body>
</html>
