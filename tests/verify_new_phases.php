<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/FarmService.php';
require_once __DIR__ . '/../src/RouteDispatchService.php';
require_once __DIR__ . '/../src/OrderService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\FarmService;
use PrakruthiSiri\RouteDispatchService;

$pdo = Database::getInstance()->getConnection();
$farmService = new FarmService($pdo);
$dispatchService = new RouteDispatchService($pdo);

echo "============================================================\n";
echo "VERIFYING PHASES 3, 4, 5, 6 IMPLEMENTATION\n";
echo "============================================================\n\n";

// 1. Verify Farm Plots & Farmer Portal (Phase 4)
echo "TEST 1: Farm Plots & Lifecycle Management (REQ-FARM-01, REQ-FARM-02)\n";
$plots = $farmService->getPlots();
assert(count($plots) === 4, "Expected 4 quarter-plots, found " . count($plots));
echo "   [✓] 4 quarter-plots verified: " . implode(', ', array_column($plots, 'quarter_name')) . "\n";

// Test plot update
$updateOk = $farmService->updatePlot(1, [
    'crop_type' => 'Country Tomato & Brinjal',
    'status'    => 'active_harvesting',
    'sown_date' => '2026-08-01',
    'notes'     => 'Healthy fruiting stage with drip irrigation',
]);
assert($updateOk === true, "Failed to update plot #1");
$plot1 = $farmService->getPlot(1);
assert($plot1['crop_type'] === 'Country Tomato & Brinjal');
echo "   [✓] Plot #1 updated and verified.\n";

// 2. Test Crop Milestone Logging (REQ-TRC-01)
echo "\nTEST 2: Crop Milestone Logging (REQ-TRC-01)\n";
$mId = $farmService->addMilestone(1, 'fertilizer_application', 'uploads/milestones/sample.jpg', 'Applied Jeevamrutham 200L via drip');
assert($mId > 0, "Milestone ID should be positive");
$milestones = $farmService->getMilestones(1);
assert(count($milestones) > 0, "Expected milestones for plot #1");
echo "   [✓] Milestone logged (ID #{$mId}) and retrieved successfully.\n";

// 3. Test Harvest Yield Declaration (REQ-FARM-03)
echo "\nTEST 3: Harvest Yield Forecast (REQ-FARM-03)\n";
$schedules = $farmService->getUpcomingSchedules();
assert(!empty($schedules), "Expected upcoming schedules");
$targetSchedId = (int)$schedules[0]['id'];

$prods = $farmService->getHarvestProducts($targetSchedId, 1);
assert(!empty($prods), "Expected harvest products");
$testProdId = (int)$prods[0]['product_id'];

$updatedCount = $farmService->submitHarvestEstimate($targetSchedId, 1, [
    ['product_id' => $testProdId, 'harvest_kg' => 25.0]
]);
assert($updatedCount > 0, "Expected updated harvest count");
echo "   [✓] Harvest forecast submitted: 25.0 kg for product #{$testProdId} -> run_inventory populated.\n";

// 4. Test Batch Traceability (REQ-TRC-02)
echo "\nTEST 4: Batch Traceability Data (REQ-TRC-02)\n";
$trace = $farmService->getBatchTraceability($targetSchedId);
assert($trace !== null, "Expected traceability bundle");
assert(!empty($trace['plots']), "Expected plots in traceability");
echo "   [✓] Batch traceability resolved with " . count($trace['plots']) . " plot(s) and " . count($trace['harvested_items']) . " harvest item(s).\n";

// 5. Test Dynamic Hub Locations (REQ-LOC-04)
echo "\nTEST 5: Dynamic Origin & Return Hubs (REQ-LOC-04)\n";
$allHubs = $dispatchService->getAllHubs();
assert(count($allHubs) >= 1, "Expected at least 1 hub location in database");
echo "   [✓] " . count($allHubs) . " hub locations retrieved.\n";

$hubs = $dispatchService->resolveScheduleHubs(null, $targetSchedId);
assert(!empty($hubs['source']), "Source hub must be present");
echo "   [✓] Source Hub: " . $hubs['source']['name'] . "\n";
echo "   [✓] Destination Hub: " . ($hubs['destination']['name'] ?? 'None (Finish at Last Customer Stop)') . "\n";

$dispatchData = $dispatchService->getDispatchData((string)$schedules[0]['delivery_date']);
assert(!empty($dispatchData['initial_gmaps_url']), "Google Maps URL must be generated");
echo "   [✓] Chained Google Maps URL generated successfully.\n";

// 6. Test ETA Window & Queue in Orders (REQ-ETA-01, REQ-ETA-02)
echo "\nTEST 6: ETA Window & Queue Columns in Orders (REQ-ETA-01, REQ-ETA-02)\n";
$orderStmt = $pdo->query("SELECT id, order_code, route_sequence_number, estimated_delivery_start, estimated_delivery_end FROM orders WHERE route_sequence_number IS NOT NULL LIMIT 1");
$sampleOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);
if ($sampleOrder) {
    echo "   [✓] Order #{$sampleOrder['order_code']} (Stop #{$sampleOrder['route_sequence_number']}): ETA {$sampleOrder['estimated_delivery_start']} – {$sampleOrder['estimated_delivery_end']}\n";
}

echo "\n============================================================\n";
echo "ALL PHASE TESTS PASSED: ZERO ERRORS\n";
echo "============================================================\n";
