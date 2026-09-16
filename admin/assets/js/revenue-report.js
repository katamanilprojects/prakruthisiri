/**
 * Prakruthi Siri - Financial & Revenue Report Controller
 * CDCApp Standard: 4 Segmented Date Tabs, 3 Prominent KPI Stat Cards
 */

(function () {
  'use strict';

  let reportData = null;

  const formFilter = document.getElementById('report-filter-form');
  const selViewMode = document.getElementById('filter-view-mode');
  const inputDimension = document.getElementById('filter-dimension');
  const selCrop = document.getElementById('filter-crop');
  const wrapCustomRange = document.getElementById('wrap-custom-range');
  const btnExportCsv = document.getElementById('btn-export-csv');

  const thead = document.getElementById('report-thead');
  const tbody = document.getElementById('report-tbody');
  const tfoot = document.getElementById('report-tfoot');

  // Segmented date tabs handling
  document.querySelectorAll('.date-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const dim = btn.dataset.dim;
      
      // Update tab UI
      document.querySelectorAll('.date-tab-btn').forEach(b => {
        b.className = 'date-tab-btn px-3.5 py-1.5 rounded-lg transition text-slate-600 hover:text-slate-900';
      });
      btn.className = 'date-tab-btn px-3.5 py-1.5 rounded-lg transition bg-emerald-600 text-white shadow-xs';

      inputDimension.value = dim;

      if (dim === 'custom_range') {
        wrapCustomRange.classList.remove('hidden');
      } else {
        wrapCustomRange.classList.add('hidden');
        loadReport();
      }
    });
  });

  selViewMode?.addEventListener('change', () => {
    if (reportData) renderMainGrid();
    else loadReport();
  });

  selCrop?.addEventListener('change', () => {
    loadReport();
  });

  async function loadReport() {
    const formData = new FormData(formFilter);
    const params = new URLSearchParams(formData);

    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-slate-400 font-medium">Generating revenue report...</td></tr>';
    tfoot.innerHTML = '';

    try {
      const resp = await fetch('api/reports.php?' + params.toString());
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const data = await resp.json();
      if (!data.success) throw new Error(data.error || 'Failed to load report data');

      reportData = data;
      renderKPIs(data.kpi);
      renderMainGrid();
    } catch (err) {
      console.error('Report error:', err);
      tbody.innerHTML = `<tr><td colspan="9" class="text-center py-10 text-red-600 font-semibold">Error: ${escapeHtml(err.message)}</td></tr>`;
    }
  }

  function renderKPIs(kpi) {
    if (!kpi) return;
    const elGross = document.getElementById('metric-collected');
    const elCod = document.getElementById('metric-cod');
    const elUpi = document.getElementById('metric-upi');
    const elWeight = document.getElementById('metric-weight');
    const elPackets = document.getElementById('metric-packets');
    const elOrders = document.getElementById('metric-orders');

    if (elGross) elGross.textContent = kpi.gross_revenue_fmt || '₹0.00';
    if (elCod) elCod.textContent = kpi.cod_total_fmt || '₹0.00';
    if (elUpi) elUpi.textContent = kpi.upi_total_fmt || '₹0.00';
    if (elWeight) elWeight.textContent = kpi.total_kg_sold_fmt || '0.0 kg';
    if (elPackets) elPackets.textContent = (kpi.total_packets || 0).toLocaleString('en-IN');
    if (elOrders) elOrders.textContent = (kpi.total_orders || 0).toLocaleString('en-IN');
  }

  function renderMainGrid() {
    if (!reportData) return;
    const mode = selViewMode ? selViewMode.value : 'daily';

    if (mode === 'crop') {
      renderCropSummary(reportData.tab_crops);
    } else if (mode === 'itemized') {
      renderItemizedOrders(reportData.tab_ledger);
    } else {
      renderDailySummary(reportData.tab_periodic);
    }
  }

  function renderDailySummary(rows) {
    thead.innerHTML = `
      <tr>
        <th>Date / Period</th>
        <th class="text-right">Orders</th>
        <th class="text-right">Weight</th>
        <th class="text-right">Packets</th>
        <th class="text-right">Vegetables Subtotal</th>
        <th class="text-right">Delivery Fees</th>
        <th class="text-right">Total Revenue</th>
        <th class="text-right">COD Settled</th>
        <th class="text-right">UPI Settled</th>
      </tr>
    `;
    tbody.innerHTML = '';
    tfoot.innerHTML = '';

    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-slate-400">No completed sales recorded for this period.</td></tr>';
      return;
    }

    let totOrders = 0, totKg = 0.0, totPkts = 0, totSub = 0.0, totFee = 0.0, totRev = 0.0, totCod = 0.0, totUpi = 0.0;

    rows.forEach((r) => {
      totOrders += r.orders_count;
      totKg += r.weight_kg;
      totPkts += r.packets;
      totSub += r.subtotal;
      totFee += r.delivery_fee;
      totRev += r.revenue;
      totCod += r.cod_amount;
      totUpi += r.upi_amount;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="font-bold text-slate-900">${escapeHtml(r.period)}</td>
        <td class="text-right font-mono font-semibold">${r.orders_count.toLocaleString('en-IN')}</td>
        <td class="text-right font-mono font-semibold text-emerald-700">${r.weight_kg_fmt}</td>
        <td class="text-right font-mono text-slate-600">${r.packets.toLocaleString('en-IN')}</td>
        <td class="text-right font-mono">${r.subtotal_fmt}</td>
        <td class="text-right font-mono text-slate-500">${r.delivery_fee_fmt}</td>
        <td class="text-right font-mono font-bold text-slate-900">${r.revenue_fmt}</td>
        <td class="text-right font-mono text-amber-700">${r.cod_amount_fmt}</td>
        <td class="text-right font-mono text-sky-700">${r.upi_amount_fmt}</td>
      `;
      tbody.appendChild(tr);
    });

    tfoot.innerHTML = `
      <tr>
        <td class="font-bold uppercase text-xs">Period Total</td>
        <td class="text-right font-mono font-bold">${totOrders.toLocaleString('en-IN')}</td>
        <td class="text-right font-mono font-bold text-emerald-700">${totKg.toFixed(1)} kg</td>
        <td class="text-right font-mono font-bold">${totPkts.toLocaleString('en-IN')}</td>
        <td class="text-right font-mono font-bold">₹${totSub.toFixed(2)}</td>
        <td class="text-right font-mono font-bold">₹${totFee.toFixed(2)}</td>
        <td class="text-right font-mono font-extrabold text-emerald-700">₹${totRev.toFixed(2)}</td>
        <td class="text-right font-mono font-bold text-amber-700">₹${totCod.toFixed(2)}</td>
        <td class="text-right font-mono font-bold text-sky-700">₹${totUpi.toFixed(2)}</td>
      </tr>
    `;
  }

  function renderCropSummary(rows) {
    thead.innerHTML = `
      <tr>
        <th>Vegetable Name</th>
        <th>Category</th>
        <th class="text-right">Sold Weight</th>
        <th class="text-right">0.5kg Packets</th>
        <th class="text-right">Avg Unit Price</th>
        <th class="text-right">Gross Revenue</th>
        <th class="text-right">Share (%)</th>
      </tr>
    `;
    tbody.innerHTML = '';
    tfoot.innerHTML = '';

    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-slate-400">No crop sales found for this period.</td></tr>';
      return;
    }

    let totKg = 0.0, totPkts = 0, totRev = 0.0;

    rows.forEach((r) => {
      totKg += r.kg_sold;
      totPkts += r.packets_sold;
      totRev += r.gross_revenue;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div class="font-bold text-slate-900">${escapeHtml(r.crop_name)}</div>
          <div class="text-[11px] text-slate-500">${escapeHtml(r.telugu_name)}</div>
        </td>
        <td><span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700">${escapeHtml(r.category)}</span></td>
        <td class="text-right font-mono font-bold text-emerald-700">${r.kg_sold_fmt}</td>
        <td class="text-right font-mono">${r.packets_sold.toLocaleString('en-IN')}</td>
        <td class="text-right font-mono">${r.avg_price_fmt}</td>
        <td class="text-right font-mono font-bold">${r.gross_revenue_fmt}</td>
        <td class="text-right font-mono text-slate-600">${r.revenue_share_fmt}</td>
      `;
      tbody.appendChild(tr);
    });

    tfoot.innerHTML = `
      <tr>
        <td colspan="2" class="font-bold uppercase text-xs">Total</td>
        <td class="text-right font-mono font-bold text-emerald-700">${totKg.toFixed(1)} kg</td>
        <td class="text-right font-mono font-bold">${totPkts.toLocaleString('en-IN')}</td>
        <td></td>
        <td class="text-right font-mono font-extrabold text-emerald-700">₹${totRev.toFixed(2)}</td>
        <td class="text-right font-mono font-bold">100.0%</td>
      </tr>
    `;
  }

  function renderItemizedOrders(rows) {
    thead.innerHTML = `
      <tr>
        <th>Order Code</th>
        <th>Delivery Run</th>
        <th>Customer &amp; Mobile</th>
        <th>Locality</th>
        <th>Vegetables Items</th>
        <th>Payment</th>
        <th class="text-right">Total Amount</th>
      </tr>
    `;
    tbody.innerHTML = '';
    tfoot.innerHTML = '';

    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-slate-400">No itemized orders found.</td></tr>';
      return;
    }

    rows.forEach((r) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="font-mono font-bold text-slate-900">${escapeHtml(r.order_code)}</td>
        <td class="font-mono text-xs text-slate-600">${escapeHtml(r.target_run_fmt)}</td>
        <td>
          <div class="font-bold text-slate-900">${escapeHtml(r.customer_name)}</div>
          <div class="text-slate-500 font-mono text-[11px]">${escapeHtml(r.customer_phone)}</div>
        </td>
        <td><span class="px-2 py-0.5 bg-slate-100 rounded text-xs font-semibold text-slate-700">${escapeHtml(r.region)}</span></td>
        <td class="text-xs text-slate-700">${escapeHtml(r.items_summary)}</td>
        <td>
          <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold ${r.payment_method === 'COD' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-sky-50 text-sky-800 border border-sky-200'}">
            ${escapeHtml(r.payment_method)}
          </span>
          <span class="text-[10px] text-slate-400 ml-1">(${escapeHtml(r.payment_status)})</span>
        </td>
        <td class="text-right font-mono font-bold text-slate-900">${r.total_amount_fmt}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[m]);
  }

  formFilter?.addEventListener('submit', (e) => {
    e.preventDefault();
    loadReport();
  });

  btnExportCsv?.addEventListener('click', () => {
    const formData = new FormData(formFilter);
    formData.append('action', 'export_csv');
    const mode = selViewMode ? selViewMode.value : 'daily';
    formData.append('export_tab', mode === 'crop' ? 'tab_crops' : (mode === 'itemized' ? 'tab_ledger' : 'tab_periodic'));
    window.location.href = 'api/reports.php?' + new URLSearchParams(formData).toString();
  });

  loadReport();
})();
