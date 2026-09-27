<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Administrative Hub Locations Management
 * REQ-LOC-04: Logistics Hubs (Source & Destination Operating Points)
 */

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

use PrakruthiSiri\Config\Database;

$pdo = Database::getInstance()->getConnection();
$activePage = 'locations';
$successMsg = null;
$errorMsg   = null;

// Handle Form Submissions (Create / Update / Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_location') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim((string)($_POST['name'] ?? ''));
        $region      = trim((string)($_POST['region'] ?? 'Hanamkonda'));
        $address     = trim((string)($_POST['address'] ?? ''));
        $latitude    = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude   = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $isSource    = !empty($_POST['is_source']) ? 1 : 0;
        $isDest      = !empty($_POST['is_destination']) ? 1 : 0;
        $isDefSource = !empty($_POST['is_default_source']) ? 1 : 0;
        $isDefDest   = !empty($_POST['is_default_destination']) ? 1 : 0;

        if ($name === '') {
            $errorMsg = 'Please enter a valid hub location name.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($isDefSource) {
                    $pdo->exec("UPDATE `hub_locations` SET `is_default_source` = 0");
                }
                if ($isDefDest) {
                    $pdo->exec("UPDATE `hub_locations` SET `is_default_destination` = 0");
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE `hub_locations`
                        SET 
                            `name`                   = :name,
                            `region`                 = :region,
                            `address`                = :address,
                            `latitude`               = :latitude,
                            `longitude`              = :longitude,
                            `is_source`              = :is_source,
                            `is_destination`         = :is_dest,
                            `is_default_source`      = :is_def_source,
                            `is_default_destination` = :is_def_dest
                        WHERE `id` = :id
                    ");
                    $stmt->execute([
                        ':name'          => $name,
                        ':region'        => $region,
                        ':address'       => $address,
                        ':latitude'      => $latitude,
                        ':longitude'     => $longitude,
                        ':is_source'     => $isSource,
                        ':is_dest'       => $isDest,
                        ':is_def_source' => $isDefSource,
                        ':is_def_dest'   => $isDefDest,
                        ':id'            => $id,
                    ]);
                    $successMsg = 'Hub location updated successfully.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO `hub_locations`
                            (`name`, `region`, `address`, `latitude`, `longitude`, `is_source`, `is_destination`, `is_default_source`, `is_default_destination`, `is_active`)
                        VALUES
                            (:name, :region, :address, :latitude, :longitude, :is_source, :is_dest, :is_def_source, :is_def_dest, 1)
                    ");
                    $stmt->execute([
                        ':name'          => $name,
                        ':region'        => $region,
                        ':address'       => $address,
                        ':latitude'      => $latitude,
                        ':longitude'     => $longitude,
                        ':is_source'     => $isSource,
                        ':is_dest'       => $isDest,
                        ':is_def_source' => $isDefSource,
                        ':is_def_dest'   => $isDefDest,
                    ]);
                    $successMsg = 'New hub location created successfully.';
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $errorMsg = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE `hub_locations` SET `is_active` = 1 - `is_active` WHERE `id` = :id");
            $stmt->execute([':id' => $id]);
            $successMsg = 'Hub status toggled.';
        }
    }
}

// Fetch all hubs
$hubs = $pdo->query("SELECT * FROM `hub_locations` ORDER BY `is_active` DESC, `is_default_source` DESC, `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Hub Locations | Prakruthi Siri Operations</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../public/assets/css/theme.css">
</head>
<body class="min-h-full flex flex-col antialiased text-slate-900 bg-slate-50 pb-16">

  <?php require __DIR__ . '/includes/masthead.php'; ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4">
      <div>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
          <span>📍</span> <span>Delivery Hub Locations (REQ-LOC-04)</span>
        </h1>
        <p class="text-xs text-slate-500 mt-1">
          Manage departure origin hubs and return destination points for driver dispatch plans.
        </p>
      </div>
      <div>
        <button 
          type="button" 
          onclick="openLocationModal()" 
          class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5"
        >
          <span>➕</span> <span>Add New Hub</span>
        </button>
      </div>
    </div>

    <?php if ($successMsg): ?>
      <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold rounded-xl flex items-center gap-2">
        <span>✓</span> <span><?= htmlspecialchars($successMsg, ENT_QUOTES) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
      <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold rounded-xl flex items-center gap-2">
        <span>⚠️</span> <span><?= htmlspecialchars($errorMsg, ENT_QUOTES) ?></span>
      </div>
    <?php endif; ?>

    <!-- Hub Locations Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-extrabold text-slate-900">Configured Logistics Points</h2>
        <span class="text-xs text-slate-500 font-semibold font-mono"><?= count($hubs) ?> Locations</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700">
          <thead class="bg-slate-50 border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
            <tr>
              <th class="px-4 py-3">Hub Name &amp; Region</th>
              <th class="px-4 py-3">Address &amp; Landmark</th>
              <th class="px-4 py-3">GPS Coordinates</th>
              <th class="px-4 py-3">Hub Roles</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <?php foreach ($hubs as $hub): ?>
              <?php
                $lat = (float)($hub['latitude'] ?? 0);
                $lng = (float)($hub['longitude'] ?? 0);
                $hasCoords = ($lat > 0 && $lng > 0);
              ?>
              <tr class="hover:bg-slate-50/80 transition <?= empty($hub['is_active']) ? 'opacity-60 bg-slate-50/40' : '' ?>">
                <td class="px-4 py-3.5">
                  <div class="font-extrabold text-slate-900 text-xs flex items-center gap-1.5">
                    <span><?= !empty($hub['is_default_source']) ? '🌟' : '📍' ?></span>
                    <span><?= htmlspecialchars($hub['name'], ENT_QUOTES) ?></span>
                  </div>
                  <span class="inline-block mt-0.5 px-2 py-0.5 bg-slate-100 rounded text-[10px] font-bold text-slate-600">
                    <?= htmlspecialchars($hub['region'] ?? 'Hanamkonda', ENT_QUOTES) ?>
                  </span>
                </td>
                <td class="px-4 py-3.5 max-w-xs truncate text-slate-600">
                  <?= htmlspecialchars($hub['address'] ?? '-', ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3.5 font-mono text-[11px]">
                  <?php if ($hasCoords): ?>
                    <a 
                      href="https://www.google.com/maps/search/?api=1&query=<?= $lat ?>,<?= $lng ?>" 
                      target="_blank" 
                      class="text-emerald-700 font-bold hover:underline flex items-center gap-1"
                    >
                      <span><?= number_format($lat, 6) ?>, <?= number_format($lng, 6) ?></span>
                      <span>🗺️</span>
                    </a>
                  <?php else: ?>
                    <span class="text-slate-400">Not set</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-3.5 space-x-1">
                  <?php if (!empty($hub['is_source'])): ?>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                      Origin Source <?= !empty($hub['is_default_source']) ? '(Default)' : '' ?>
                    </span>
                  <?php endif; ?>
                  <?php if (!empty($hub['is_destination'])): ?>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200">
                      Destination <?= !empty($hub['is_default_destination']) ? '(Default)' : '' ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-3.5">
                  <?php if (!empty($hub['is_active'])): ?>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                      Active
                    </span>
                  <?php else: ?>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500">
                      Deactivated
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-3.5 text-right space-x-2">
                  <button 
                    type="button" 
                    onclick="editLocation(<?= htmlspecialchars(json_encode($hub), ENT_QUOTES) ?>)"
                    class="font-bold text-emerald-700 hover:underline"
                  >
                    Edit
                  </button>
                  <form method="POST" action="locations.php" class="inline">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id" value="<?= (int)$hub['id'] ?>">
                    <button 
                      type="submit" 
                      class="font-bold text-slate-500 hover:text-rose-600 transition"
                      onclick="return confirm('Change status for this hub?')"
                    >
                      <?= !empty($hub['is_active']) ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Edit / Add Location Modal -->
  <div id="location-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full p-5 space-y-4 shadow-xl">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <h3 class="text-base font-extrabold text-slate-900" id="modal-loc-title">Add New Hub Location</h3>
        <button type="button" onclick="closeLocationModal()" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center">✕</button>
      </div>

      <form method="POST" action="locations.php" class="space-y-3.5 text-xs">
        <input type="hidden" name="action" value="save_location">
        <input type="hidden" name="id" id="modal-loc-id" value="0">

        <div>
          <label class="block font-bold text-slate-700 mb-1">Hub Name</label>
          <input type="text" name="name" id="modal-loc-name" required placeholder="e.g. Central Sorting Hub, Naimnagar Transit Point" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-900 outline-none">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Operating Region</label>
            <select name="region" id="modal-loc-region" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-900 outline-none">
              <option value="Hanamkonda">Hanamkonda</option>
              <option value="Warangal">Warangal</option>
              <option value="Outskirts">Outskirts / Other</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Street Landmark</label>
            <input type="text" name="address" id="modal-loc-address" placeholder="e.g. Near KU Cross Road, Naimnagar" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 outline-none">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Latitude</label>
            <input type="number" step="any" name="latitude" id="modal-loc-lat" placeholder="18.028439" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-mono text-slate-900 outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Longitude</label>
            <input type="number" step="any" name="longitude" id="modal-loc-lng" placeholder="79.635941" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-mono text-slate-900 outline-none">
          </div>
        </div>

        <!-- Capability Toggles -->
        <div class="pt-2 border-t border-slate-100 space-y-2">
          <div class="grid grid-cols-2 gap-2">
            <label class="flex items-center gap-2 p-2 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer">
              <input type="checkbox" name="is_source" id="modal-loc-source" value="1" checked class="rounded text-emerald-600">
              <span class="font-bold text-slate-800">Can be Origin (Source)</span>
            </label>
            <label class="flex items-center gap-2 p-2 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer">
              <input type="checkbox" name="is_destination" id="modal-loc-dest" value="1" checked class="rounded text-emerald-600">
              <span class="font-bold text-slate-800">Can be Return Destination</span>
            </label>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <label class="flex items-center gap-2 p-2 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer">
              <input type="checkbox" name="is_default_source" id="modal-loc-def-source" value="1" class="rounded text-emerald-600">
              <span class="font-bold text-slate-800">Set Default Origin Hub</span>
            </label>
            <label class="flex items-center gap-2 p-2 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer">
              <input type="checkbox" name="is_default_destination" id="modal-loc-def-dest" value="1" class="rounded text-emerald-600">
              <span class="font-bold text-slate-800">Set Default Return Hub</span>
            </label>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
          <button type="button" onclick="closeLocationModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 font-bold rounded-xl text-slate-700">Cancel</button>
          <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs">Save Hub</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openLocationModal() {
      document.getElementById('modal-loc-id').value = '0';
      document.getElementById('modal-loc-title').textContent = 'Add New Hub Location';
      document.getElementById('modal-loc-name').value = '';
      document.getElementById('modal-loc-region').value = 'Hanamkonda';
      document.getElementById('modal-loc-address').value = '';
      document.getElementById('modal-loc-lat').value = '';
      document.getElementById('modal-loc-lng').value = '';
      document.getElementById('modal-loc-source').checked = true;
      document.getElementById('modal-loc-dest').checked = true;
      document.getElementById('modal-loc-def-source').checked = false;
      document.getElementById('modal-loc-def-dest').checked = false;
      document.getElementById('location-modal').classList.remove('hidden');
    }

    function editLocation(hub) {
      document.getElementById('modal-loc-id').value = hub.id;
      document.getElementById('modal-loc-title').textContent = 'Edit Hub: ' + hub.name;
      document.getElementById('modal-loc-name').value = hub.name || '';
      document.getElementById('modal-loc-region').value = hub.region || 'Hanamkonda';
      document.getElementById('modal-loc-address').value = hub.address || '';
      document.getElementById('modal-loc-lat').value = hub.latitude || '';
      document.getElementById('modal-loc-lng').value = hub.longitude || '';
      document.getElementById('modal-loc-source').checked = parseInt(hub.is_source, 10) === 1;
      document.getElementById('modal-loc-dest').checked = parseInt(hub.is_destination, 10) === 1;
      document.getElementById('modal-loc-def-source').checked = parseInt(hub.is_default_source, 10) === 1;
      document.getElementById('modal-loc-def-dest').checked = parseInt(hub.is_default_destination, 10) === 1;
      document.getElementById('location-modal').classList.remove('hidden');
    }

    function closeLocationModal() {
      document.getElementById('location-modal').classList.add('hidden');
    }
  </script>
</body>
</html>
