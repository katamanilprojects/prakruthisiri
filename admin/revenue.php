<?php
declare(strict_types=1);

/**
 * Prakruthi Siri - Financial & Revenue Hub
 * CDCApp Standard: 4 Segmented Date Tabs, 3 Prominent KPI Stat Cards
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

use PrakruthiSiri\Config\Database;

$activePage = 'revenue';
$crops = [];

try {
    $pdo = Database::getInstance()->getConnection();
    $cropStmt = $pdo->query("SELECT `id`, `name`, `telugu_name`, `category` FROM `products` ORDER BY `name` ASC");
    $crops = $cropStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
    error_log('Revenue Hub bootstrap error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Amounts &amp; Revenue | Prakruthi Siri</title>
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
<body class="min-h-full bg-slate-50 text-slate-900 flex flex-col font-sans pb-16">
  <?php require __DIR__ . '/includes/masthead.php'; ?>

  <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 flex-1 space-y-5">
    <!-- Header & Segmented Time Filter Strip -->
    <div class="bg-white border border-slate-200 p-4 sm:p-5 rounded-2xl shadow-xs space-y-4">
      <form id="report-filter-form" class="space-y-4">
        <!-- Hidden Inputs for Form Submission -->
        <input type="hidden" id="filter-dimension" name="date_dimension" value="this_month">
        <input type="hidden" name="order_status" value="delivered">
        <input type="hidden" name="payment_mode" value="ALL">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <!-- 4 Fast Segmented Date Tabs -->
          <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold select-none overflow-x-auto no-scrollbar">
            <button type="button" class="date-tab-btn px-3.5 py-1.5 rounded-lg transition text-slate-600 hover:text-slate-900" data-dim="today">
              Today
            </button>
            <button type="button" class="date-tab-btn px-3.5 py-1.5 rounded-lg transition text-slate-600 hover:text-slate-900" data-dim="this_week">
              This Week
            </button>
            <button type="button" class="date-tab-btn px-3.5 py-1.5 rounded-lg transition bg-emerald-600 text-white shadow-xs" data-dim="this_month">
              This Month
            </button>
            <button type="button" class="date-tab-btn px-3.5 py-1.5 rounded-lg transition text-slate-600 hover:text-slate-900" data-dim="custom_range">
              Custom Range
            </button>
          </div>

          <!-- Controls: View Mode, Crop Filter & Export -->
          <div class="flex flex-wrap items-center gap-2.5 text-xs">
            <div class="flex items-center gap-1.5">
              <label for="filter-view-mode" class="font-bold text-slate-700 whitespace-nowrap">View:</label>
              <select id="filter-view-mode" name="view_mode" class="compact-select font-semibold text-xs text-slate-800">
                <option value="daily" selected>Daily Summary</option>
                <option value="crop">Crop-Wise Summary</option>
                <option value="itemized">Itemized Orders</option>
              </select>
            </div>

            <div class="flex items-center gap-1.5">
              <label for="filter-crop" class="font-bold text-slate-700 whitespace-nowrap">Crop:</label>
              <select id="filter-crop" name="crop_id" class="compact-select text-xs">
                <option value="0">All Crops</option>
                <?php foreach ($crops as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="button" id="btn-export-csv" class="btn btn-secondary text-xs h-8 min-h-[32px] px-3 font-bold">
              📥 Export CSV
            </button>
          </div>
        </div>

        <!-- Custom Date Range Picker Drawer (conditionally visible) -->
        <div id="wrap-custom-range" class="hidden pt-3 border-t border-slate-100 flex flex-wrap items-center gap-3 text-xs">
          <div class="flex items-center gap-2">
            <label for="filter-start-date" class="font-bold text-slate-700">From:</label>
            <input type="date" id="filter-start-date" name="start_date" value="<?= date('Y-m-d', strtotime('-7 days')) ?>" class="compact-input font-mono text-xs">
          </div>
          <div class="flex items-center gap-2">
            <label for="filter-end-date" class="font-bold text-slate-700">To:</label>
            <input type="date" id="filter-end-date" name="end_date" value="<?= date('Y-m-d') ?>" class="compact-input font-mono text-xs">
          </div>
          <button type="submit" class="btn btn-primary text-xs h-8 min-h-[32px] px-3 font-bold">
            Apply Date Range
          </button>
        </div>
      </form>
    </div>

    <!-- 3 Prominent KPI Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Card 1: Total Revenue -->
      <div class="stat-card">
        <div class="stat-label">Total Revenue</div>
        <div class="stat-number text-emerald-600" id="metric-collected">₹0.00</div>
        <div class="text-xs text-slate-500 mt-1 font-medium">
          Delivered &amp; settled customer sales
        </div>
      </div>

      <!-- Card 2: Total Orders & Weight -->
      <div class="stat-card">
        <div class="stat-label">Total Completed Orders</div>
        <div class="stat-number text-slate-900" id="metric-orders">0</div>
        <div class="text-xs text-slate-500 mt-1 font-medium">
          <span id="metric-weight" class="font-bold text-emerald-700">0.0 kg</span> vegetables (<span id="metric-packets">0</span> packets)
        </div>
      </div>

      <!-- Card 3: COD vs UPI Breakdown -->
      <div class="stat-card">
        <div class="stat-label">Payment Channels (COD vs UPI)</div>
        <div class="stat-number text-slate-800 flex items-baseline gap-2">
          <span class="text-amber-600 font-mono text-xl" id="metric-cod">₹0</span>
          <span class="text-xs font-normal text-slate-400">/</span>
          <span class="text-sky-600 font-mono text-xl" id="metric-upi">₹0</span>
        </div>
        <div class="text-xs text-slate-500 mt-1 font-medium flex items-center gap-3">
          <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> COD Cash</span>
          <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-sky-500"></span> Online UPI</span>
        </div>
      </div>
    </div>

    <!-- Main Dynamic Data Grid -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden" id="report-grid-container">
      <div class="overflow-x-auto">
        <table class="enterprise-table" id="report-main-table">
          <thead id="report-thead">
            <!-- Populated by revenue-report.js -->
          </thead>
          <tbody id="report-tbody">
            <tr>
              <td colspan="9" class="text-center py-10 text-slate-400 font-medium">Loading revenue report...</td>
            </tr>
          </tbody>
          <tfoot id="report-tfoot">
            <!-- Populated by revenue-report.js -->
          </tfoot>
        </table>
      </div>
    </div>
  </main>

  <script src="assets/js/revenue-report.js"></script>
</body>
</html>
