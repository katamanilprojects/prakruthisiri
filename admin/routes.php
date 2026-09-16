<?php
declare(strict_types=1);

/**
 * Prakruthi Siri - Driver Route & Dispatch
 * CDCApp Standard: Inter Font, Slate-50 Background, Emerald Actions
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
use PrakruthiSiri\Config\Database;

$activePage = 'routes';
$pdo = Database::getInstance()->getConnection();

// 1. Fetch available runs
$runsStmt = $pdo->query("
    SELECT `id`, `delivery_date`, `delivery_day`, `target_region`, `is_ordering_open`, `status`
    FROM `delivery_schedules`
    ORDER BY `delivery_date` ASC
");
$allRuns = $runsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Find active run date
$selectedRunId = isset($_GET['run_id']) ? (int) $_GET['run_id'] : 0;
$activeRun = null;

if ($selectedRunId > 0) {
    foreach ($allRuns as $r) {
        if ((int) $r['id'] === $selectedRunId) { $activeRun = $r; break; }
    }
}
if (!$activeRun && isset($_GET['date'])) {
    foreach ($allRuns as $r) {
        if ($r['delivery_date'] === $_GET['date']) { $activeRun = $r; break; }
    }
}
if (!$activeRun && !empty($allRuns)) {
    foreach ($allRuns as $r) {
        if ($r['delivery_date'] >= date('Y-m-d')) { $activeRun = $r; break; }
    }
    if (!$activeRun) $activeRun = $allRuns[0];
}

$targetDate = $activeRun['delivery_date'] ?? date('Y-m-d');
$targetRegion = $activeRun['target_region'] ?? 'Hanamkonda';

// Fetch orders for this run
$stmt = $pdo->prepare("
    SELECT o.`id`, o.`order_code`, o.`route_sequence_number`, o.`total_amount`, o.`assigned_driver_id`,
           c.`full_name`, c.`phone_number`, c.`delivery_address`, c.`latitude`, c.`longitude`
    FROM `orders` o
    JOIN `customers` c ON o.`customer_id` = c.`id`
    WHERE (o.`schedule_id` = :sid OR (o.`schedule_id` IS NULL AND o.`target_delivery_date` = :tdate))
      AND o.`order_status` != 'cancelled'
    ORDER BY COALESCE(o.`route_sequence_number`, 9999) ASC, o.`id` ASC
");
$stmt->execute([':sid' => $activeRun['id'] ?? 0, ':tdate' => $targetDate]);
$stops = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Farm Hub coordinates (KU Cross Road / Naimnagar sector)
$hubLat = 18.028439;
$hubLng = 79.635941;

// Partition stops into seamless 7-stop chained Google Maps legs
$chunkSize = 7;
$totalStopsCount = count($stops);
$legs = [];

if ($totalStopsCount > 0) {
    $numLegs = (int)ceil($totalStopsCount / $chunkSize);
    for ($legIdx = 0; $legIdx < $numLegs; $legIdx++) {
        $startIdx = $legIdx * $chunkSize;
        $endIdx = min($startIdx + $chunkSize, $totalStopsCount);
        $chunkStops = array_slice($stops, $startIdx, $endIdx - $startIdx);

        $startStopNum = $startIdx + 1;
        $endStopNum = $endIdx;

        // Determine origin for this leg
        if ($legIdx === 0) {
            $origin = "{$hubLat},{$hubLng}";
            $originLabel = "Farm Hub";
        } else {
            $prevStop = $stops[$startIdx - 1];
            $origin = ((float)$prevStop['latitude'] > 0)
                ? "{$prevStop['latitude']},{$prevStop['longitude']}"
                : urlencode($prevStop['delivery_address'] . ', ' . $targetRegion);
            $originLabel = "Stop #" . $startIdx;
        }

        // Determine waypoints
        $points = [];
        foreach ($chunkStops as $s) {
            if ((float)$s['latitude'] > 0) {
                $points[] = "{$s['latitude']},{$s['longitude']}";
            } else {
                $points[] = urlencode($s['delivery_address'] . ', ' . $targetRegion);
            }
        }

        // If this is the final leg, append return to Farm Hub
        $isLastLeg = ($legIdx === $numLegs - 1);
        if ($isLastLeg) {
            $points[] = "{$hubLat},{$hubLng}";
        }

        $url = "https://www.google.com/maps/dir/{$origin}/" . implode('/', $points);

        $legs[] = [
            'leg_number'   => $legIdx + 1,
            'label'        => "Part " . ($legIdx + 1) . " (" . ($legIdx === 0 ? "Farm &rarr; " : "Stop {$startIdx} &rarr; ") . "Stops {$startStopNum}–{$endStopNum}" . ($isLastLeg ? ' &rarr; Farm' : '') . ")",
            'start_num'    => $startStopNum,
            'end_num'      => $endStopNum,
            'url'          => $url,
            'origin_label' => $originLabel,
            'is_last'      => $isLastLeg
        ];
    }
}

// Fetch active drivers
$drivers = $pdo->query("SELECT `id`, `full_name` FROM `staff_users` WHERE `role` = 'driver' AND `is_active` = 1")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$currentAssignedDriver = !empty($stops[0]['assigned_driver_id']) ? (int)$stops[0]['assigned_driver_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Route &amp; Directions | Prakruthi Siri</title>
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
    <!-- Delivery Batch Selector Strip -->
    <?php if (!empty($allRuns)): ?>
    <?php $isDispatched = ($activeRun['status'] ?? '') === 'dispatched'; ?>
    <div class="bg-white border border-slate-200 p-3.5 sm:p-5 rounded-2xl shadow-xs space-y-3">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <form method="GET" action="routes.php" class="flex flex-wrap items-center gap-2">
          <label for="run-select" class="font-bold text-slate-800 text-xs sm:text-sm whitespace-nowrap">Delivery Batch:</label>
          <select id="run-select" name="run_id" onchange="this.form.submit()" class="compact-select font-semibold text-xs sm:text-sm text-slate-800 min-h-[40px]">
            <?php foreach ($allRuns as $run): ?>
              <option value="<?= $run['id'] ?>" <?= ($activeRun && (int)$activeRun['id'] === (int)$run['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($run['delivery_day'] . ' Batch, ' . date('d M Y', strtotime($run['delivery_date'])) . ' — ' . $run['target_region'], ENT_QUOTES) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <div class="flex items-center gap-2 text-xs font-medium">
          <span class="badge-status <?= $isDispatched ? 'badge-status-dispatched' : 'badge-status-delivered' ?>">
            <?= $isDispatched ? 'DISPATCHED TO RIDER' : 'READY FOR DISPATCH' ?>
          </span>
          <span class="text-slate-500 hidden sm:inline"><strong class="text-slate-900"><?= date('d M Y', strtotime($targetDate)) ?></strong></span>
          <span class="hidden sm:inline">&bull;</span>
          <span class="text-slate-500"><strong class="text-emerald-700"><?= htmlspecialchars($targetRegion, ENT_QUOTES) ?></strong></span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Action & Assignment Card -->
    <div class="bg-white border border-slate-200 p-3.5 sm:p-5 rounded-2xl shadow-xs space-y-3">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <h2 class="text-sm sm:text-base font-bold text-slate-900">Rider Delivery Route (<?= count($stops) ?> Stops)</h2>
          <p class="text-xs text-slate-500 mt-0.5">Sequential route manifest from Farm Hub (KU Cross Road).</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
          <button 
            type="button" 
            id="btn-sort-route" 
            class="btn btn-secondary text-xs h-10 min-h-[40px] px-3 font-bold flex-1 sm:flex-none"
          >
            🔄 Arrange Stops
          </button>

          <div class="flex items-center gap-1.5 flex-1 sm:flex-none">
            <label for="driver-select" class="font-bold text-slate-700 whitespace-nowrap">Driver:</label>
            <select id="driver-select" class="compact-select text-xs font-semibold h-10 min-h-[40px] w-full">
              <?php foreach ($drivers as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($currentAssignedDriver === (int)$d['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['full_name'], ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <button 
            id="btn-release-driver" 
            type="button"
            class="btn btn-primary text-xs h-10 min-h-[40px] px-4 font-bold inline-flex items-center gap-1.5 shadow-sm w-full sm:w-auto"
          >
            <span>🚀</span>
            <span>Approve &amp; Release to Driver</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Navigation Link Buttons (Partitioned 6-8 Stop Google Maps Legs) -->
    <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-xs space-y-2.5">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-xs font-bold text-slate-800">Partitioned Google Maps Circuit Legs:</span>
        <span class="text-[11px] font-mono text-slate-500">Farm Hub: 18.028439, 79.635941 (KU Cross Rd)</span>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <?php if (empty($legs)): ?>
          <span class="text-xs text-slate-400">No stops scheduled for this delivery batch.</span>
        <?php else: ?>
          <?php foreach ($legs as $leg): ?>
            <a 
              href="<?= htmlspecialchars($leg['url'], ENT_QUOTES) ?>" 
              target="_blank" 
              class="btn btn-secondary text-xs h-9 min-h-[36px] px-3.5 font-bold inline-flex items-center gap-1.5 hover:border-emerald-300 hover:text-emerald-700 transition"
            >
              <span>🗺️</span>
              <span><?= $leg['label'] ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stops Order Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="enterprise-table">
          <thead>
            <tr>
              <th class="w-16 text-center">Stop #</th>
              <th>Order Code</th>
              <th>Customer &amp; Mobile</th>
              <th>Delivery Address</th>
              <th>Amount</th>
              <th class="text-center">Map Direction</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($stops)): ?>
              <tr><td colspan="6" class="text-center py-10 text-slate-400 font-medium">No customer stops found for this batch date.</td></tr>
            <?php else: ?>
              <?php foreach ($stops as $idx => $s): ?>
                <tr>
                  <td class="text-center font-bold font-mono text-emerald-700">#<?= $idx + 1 ?></td>
                  <td class="font-mono font-bold text-slate-900"><?= htmlspecialchars($s['order_code'], ENT_QUOTES) ?></td>
                  <td>
                    <strong class="text-slate-900"><?= htmlspecialchars($s['full_name'], ENT_QUOTES) ?></strong>
                    <div class="text-slate-400 font-mono text-[11px] flex items-center gap-1.5 mt-0.5">
                      <a href="tel:<?= htmlspecialchars($s['phone_number'], ENT_QUOTES) ?>" class="text-emerald-600 hover:underline">
                        📞 <?= htmlspecialchars($s['phone_number'], ENT_QUOTES) ?>
                      </a>
                    </div>
                  </td>
                  <td class="text-slate-700 text-xs"><?= htmlspecialchars($s['delivery_address'], ENT_QUOTES) ?></td>
                  <td class="font-mono font-bold text-slate-900">₹<?= number_format((float)$s['total_amount'], 2) ?></td>
                  <td class="text-center whitespace-nowrap">
                    <?php
                      $dest = ((float)$s['latitude'] > 0) ? $s['latitude'] . ',' . $s['longitude'] : urlencode($s['delivery_address'] . ', ' . $targetRegion);
                      $directUrl = "https://www.google.com/maps/dir/?api=1&destination=" . $dest;
                      $sPhone = preg_replace('/\D/', '', (string)$s['phone_number']);
                      if (strlen($sPhone) === 10) $sPhone = '91' . $sPhone;
                      $waRouteMsg = "Namaste! Your Prakruthi Siri vegetable basket #" . $s['order_code'] . " is packed and out for delivery today. Cash to keep ready: ₹" . number_format((float)$s['total_amount'], 2) . ".";
                      $waRouteUrl = "https://wa.me/" . $sPhone . "?text=" . rawurlencode($waRouteMsg);
                    ?>
                    <div class="inline-flex items-center gap-1.5">
                      <a 
                        href="<?= htmlspecialchars($directUrl, ENT_QUOTES) ?>" 
                        target="_blank" 
                        class="btn btn-secondary text-xs h-7 min-h-[28px] px-2.5 font-bold inline-flex items-center gap-1 text-emerald-700"
                      >
                        <span>📍 Navigate</span>
                      </a>
                      <a 
                        href="<?= htmlspecialchars($waRouteUrl, ENT_QUOTES) ?>" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        class="btn btn-secondary text-xs h-7 min-h-[28px] px-2.5 font-bold inline-flex items-center gap-1 text-emerald-700 hover:bg-emerald-50"
                        title="WhatsApp Customer"
                      >
                        <span>💬 WhatsApp</span>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script>
    document.getElementById('btn-sort-route')?.addEventListener('click', async () => {
      const btn = document.getElementById('btn-sort-route');
      btn.disabled = true;
      btn.textContent = 'Optimizing Order...';

      try {
        const resp = await fetch('api/dispatch-api.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            action: 'optimize_route',
            target_date: '<?= $targetDate ?>',
            schedule_id: <?= (int)($activeRun['id'] ?? 0) ?>,
            region: '<?= addslashes($targetRegion) ?>'
          })
        });
        const data = await resp.json();
        if (data.success) {
          alert('Stops sequenced in optimal delivery order.');
          window.location.reload();
        } else {
          alert('Sorting failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      } finally {
        btn.disabled = false;
        btn.textContent = '🔄 Arrange Stops in Road Order';
      }
    });

    document.getElementById('btn-release-driver')?.addEventListener('click', async () => {
      const driverSelect = document.getElementById('driver-select');
      const driverId = driverSelect ? driverSelect.value : 0;
      if (!driverId) {
        alert('Please select a driver first.');
        return;
      }

      if (!confirm('Approve this batch and release it to the driver manifest? Orders will be marked as packed & out for delivery.')) {
        return;
      }

      const btn = document.getElementById('btn-release-driver');
      btn.disabled = true;
      btn.textContent = 'Releasing to Driver...';

      try {
        const resp = await fetch('api/dispatch-api.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            action: 'finalize_dispatch',
            schedule_id: <?= (int)($activeRun['id'] ?? 0) ?>,
            region: '<?= addslashes($targetRegion) ?>',
            target_date: '<?= $targetDate ?>',
            driver_id: parseInt(driverId, 10)
          })
        });
        const data = await resp.json();
        if (data.success) {
          alert('Batch successfully approved and released to driver manifest!');
          window.location.reload();
        } else {
          alert('Dispatch failed: ' + (data.error || 'Unknown error'));
          btn.disabled = false;
          btn.textContent = '🚀 Approve & Release to Driver';
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btn.textContent = '🚀 Approve & Release to Driver';
      }
    });
  </script>
</body>
</html>
