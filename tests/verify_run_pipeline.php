<?php

declare(strict_types=1);

/**
 * End-to-End Verification Test for Prakruthi Siri Scheduled Delivery Run Pipeline.
 * 
 * Verifies:
 * 1. Run Inventory Isolation across different delivery schedules.
 * 2. Active Geofencing (> 14 km rejection).
 * 3. Locality Match & Cross-Region Mismatch Rejection.
 * 4. Dual Ordering Window Enforcement (5 AM open, 7 PM cutoff).
 * 5. Order Creation with Run-specific Stock Decrement and `schedule_id` persistence.
 * 6. Order Cancellation with atomic Run Inventory stock restoration.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Exceptions/OrderValidationException.php';
require_once __DIR__ . '/../src/Exceptions/InsufficientStockException.php';
require_once __DIR__ . '/../src/Exceptions/ProductNotFoundException.php';
require_once __DIR__ . '/../src/DatabaseMigration.php';
require_once __DIR__ . '/../src/ConfigService.php';
require_once __DIR__ . '/../src/GeoFenceService.php';
require_once __DIR__ . '/../src/InventoryService.php';
require_once __DIR__ . '/../src/RunInventoryService.php';
require_once __DIR__ . '/../src/TimeWindow.php';
require_once __DIR__ . '/../src/OrderService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\DatabaseMigration;
use PrakruthiSiri\RunInventoryService;
use PrakruthiSiri\OrderService;
use PrakruthiSiri\GeoFenceService;
use PrakruthiSiri\TimeWindow;
use PrakruthiSiri\Exceptions\OrderValidationException;

$passed = 0;
$failed = 0;

function runTest(string $name, callable $fn): void {
    global $passed, $failed;
    echo "\n------------------------------------------------------------\n";
    echo "RUNNING TEST: {$name}\n";
    echo "------------------------------------------------------------\n";
    try {
        $fn();
        echo "✅ PASS: {$name}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "❌ FAIL: {$name}\n";
        echo "   Error: " . $e->getMessage() . "\n";
        echo "   Trace: " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

$pdo = Database::getInstance()->getConnection();
DatabaseMigration::ensureMigrated($pdo);

$runInventoryService = new RunInventoryService($pdo);
$orderService = new OrderService($pdo);

// Find two distinct delivery schedules (e.g. Hanamkonda vs Warangal)
$allSchedules = $runInventoryService->getAllUpcomingSchedules();
$hanamkondaSchedule = null;
$warangalSchedule = null;

foreach ($allSchedules as $s) {
    if ($s['target_region'] === 'Hanamkonda' && (int)$s['is_ordering_open'] === 1 && $s['delivery_date'] >= date('Y-m-d') && $hanamkondaSchedule === null) {
        $hanamkondaSchedule = $s;
    }
    if ($s['target_region'] === 'Warangal' && (int)$s['is_ordering_open'] === 1 && $s['delivery_date'] >= date('Y-m-d') && $warangalSchedule === null) {
        $warangalSchedule = $s;
    }
}

if (!$hanamkondaSchedule || !$warangalSchedule) {
    throw new RuntimeException("Could not find both Hanamkonda and Warangal schedules for testing.");
}

$sidHanamkonda = (int) $hanamkondaSchedule['id'];
$sidWarangal   = (int) $warangalSchedule['id'];

echo "Target Test Schedules: Hanamkonda (#{$sidHanamkonda}) & Warangal (#{$sidWarangal})\n";

// --------------------------------------------------------------------------
// TEST 1: Run Inventory Stock Isolation
// --------------------------------------------------------------------------
runTest("Run Inventory Stock Isolation", function() use ($runInventoryService, $sidHanamkonda, $sidWarangal) {
    $runInventoryService->ensureScheduleInventory($sidHanamkonda);
    $runInventoryService->ensureScheduleInventory($sidWarangal);

    // Initial stock for Product 1 (Country Tomato) in both schedules
    $catalogH = $runInventoryService->getRunCatalog($sidHanamkonda, false);
    $catalogW = $runInventoryService->getRunCatalog($sidWarangal, false);

    $p1H = null;
    foreach ($catalogH as $p) {
        if ($p['product_id'] === 1) { $p1H = $p; break; }
    }
    $p1W = null;
    foreach ($catalogW as $p) {
        if ($p['product_id'] === 1) { $p1W = $p; break; }
    }

    if (!$p1H || !$p1W) {
        throw new RuntimeException("Product ID 1 not found in test schedules.");
    }

    $originalWarangalStock = (int) $p1W['available_half_kg_stock'];

    // Update Hanamkonda Product 1 to exactly 45 packets (22.5 kg)
    $runInventoryService->bulkUpdateRunInventory($sidHanamkonda, [
        [
            'product_id'              => 1,
            'harvest_kg'              => 22.5,
            'available_half_kg_stock' => 45,
            'price_per_half_kg'       => 25.00,
            'is_active'               => true,
        ]
    ]);

    // Re-fetch catalogs
    $updatedCatalogH = $runInventoryService->getRunCatalog($sidHanamkonda, false);
    $updatedCatalogW = $runInventoryService->getRunCatalog($sidWarangal, false);

    $updatedHStock = null;
    foreach ($updatedCatalogH as $p) {
        if ($p['product_id'] === 1) { $updatedHStock = (int) $p['available_half_kg_stock']; break; }
    }
    $updatedWStock = null;
    foreach ($updatedCatalogW as $p) {
        if ($p['product_id'] === 1) { $updatedWStock = (int) $p['available_half_kg_stock']; break; }
    }

    if ($updatedHStock !== 45) {
        throw new RuntimeException("Hanamkonda stock was expected to be 45, got {$updatedHStock}.");
    }

    if ($updatedWStock !== $originalWarangalStock) {
        throw new RuntimeException("Warangal stock was altered! Expected {$originalWarangalStock}, got {$updatedWStock}.");
    }

    echo "   [✓] Hanamkonda stock updated to 45 pkts.\n";
    echo "   [✓] Warangal stock remained completely isolated at {$originalWarangalStock} pkts.\n";
});

// --------------------------------------------------------------------------
// TEST 2: 2-Layer Geofencing Enforcement (11.5 km radius & Kazipet cutoff)
// --------------------------------------------------------------------------
runTest("Geofencing Radius & Kazipet Longitude Enforcement", function() use ($orderService, $sidHanamkonda) {
    // 2a. Beyond 11.5 km (Hyderabad)
    $outOfBoundsCustomer = [
        'full_name'        => 'Test Out of Bounds Customer',
        'phone_number'     => '9988776600',
        'delivery_address' => 'Plot 999, Mulugu Road, Atmakur Outskirts',
        'region'           => 'Warangal',
        'latitude'         => 18.0284000,
        'longitude'        => 79.8000000, // ~17.4 km East from Farm Hub (18.028444, 79.635944), well East of 79.540°E
    ];

    $cart = [
        ['product_id' => 1, 'quantity' => 2]
    ];

    $threwRadius = false;
    try {
        $orderService->createOrder($outOfBoundsCustomer, $cart, 'COD', 'Test delivery notes', null, null, $sidHanamkonda);
    } catch (OrderValidationException $e) {
        $threwRadius = true;
        if (!str_contains($e->getMessage(), 'exceeding the maximum 11.5 km delivery radius')) {
            throw new RuntimeException("Unexpected exception message: " . $e->getMessage());
        }
        echo "   [✓] Successfully caught 11.5 km radius rejection: " . $e->getMessage() . "\n";
    }

    if (!$threwRadius) {
        throw new RuntimeException("Failed to reject delivery address beyond 11.5 km!");
    }

    // 2b. Kazipet Longitude Rejection (west of 79.540°E, e.g. Kazipet Railway Station)
    $kazipetCustomer = [
        'full_name'        => 'Test Kazipet Resident',
        'phone_number'     => '9988776609',
        'delivery_address' => 'Station Road, Kazipet',
        'region'           => 'Hanamkonda',
        'latitude'         => 17.9780000,
        'longitude'        => 79.5150000, // Kazipet: lng 79.515 < 79.540
    ];

    $threwKazipet = false;
    try {
        $orderService->createOrder($kazipetCustomer, $cart, 'COD', 'Kazipet order test', null, null, $sidHanamkonda);
    } catch (OrderValidationException $e) {
        $threwKazipet = true;
        if (!str_contains($e->getMessage(), 'కాజీపేట (Kazipet)')) {
            throw new RuntimeException("Unexpected Kazipet exception message: " . $e->getMessage());
        }
        echo "   [✓] Successfully caught Kazipet western cutoff rejection: " . $e->getMessage() . "\n";
    }

    if (!$threwKazipet) {
        throw new RuntimeException("Failed to reject Kazipet address west of 79.540°E!");
    }

    // 2c. Distant Western Locations (Secunderabad / Hyderabad / Karnataka)
    // Must be rejected as out-of-radius (11.5 km), NOT falsely flagged as Kazipet
    $secunderabadCustomer = [
        'full_name'        => 'Test Secunderabad Resident',
        'phone_number'     => '9988776610',
        'delivery_address' => 'MG Road, Secunderabad',
        'region'           => 'Hanamkonda',
        'latitude'         => 17.4399000,
        'longitude'        => 78.4983000, // Secunderabad: lng 78.4983 < 79.540, ~137.1 km away
    ];

    $threwSecunderabadRadius = false;
    try {
        $orderService->createOrder($secunderabadCustomer, $cart, 'COD', 'Secunderabad test', null, null, $sidHanamkonda);
    } catch (OrderValidationException $e) {
        $threwSecunderabadRadius = true;
        if (str_contains($e->getMessage(), 'Kazipet') || str_contains($e->getMessage(), 'కాజీపేట')) {
            throw new RuntimeException("Secunderabad was wrongly flagged as Kazipet! Message: " . $e->getMessage());
        }
        if (!str_contains($e->getMessage(), 'exceeding the maximum 11.5 km delivery radius')) {
            throw new RuntimeException("Unexpected exception message for Secunderabad: " . $e->getMessage());
        }
        echo "   [✓] Successfully verified distant location (Secunderabad, 137.1 km) receives radius rejection, NOT Kazipet: " . $e->getMessage() . "\n";
    }

    if (!$threwSecunderabadRadius) {
        throw new RuntimeException("Failed to reject Secunderabad address!");
    }
});

// --------------------------------------------------------------------------
// TEST 3: Locality / Region Mismatch Rejection
// --------------------------------------------------------------------------
runTest("Locality / Region Mismatch Rejection", function() use ($orderService, $sidHanamkonda) {
    // Customer address is in Warangal, but they try to book into Hanamkonda schedule
    $warangalCustomer = [
        'full_name'        => 'Test Warangal Resident',
        'phone_number'     => '9988776601',
        'delivery_address' => 'Near Warangal Fort, Warangal',
        'region'           => 'Warangal',
        'latitude'         => 17.9620000,
        'longitude'        => 79.6050000, // Within 14km, but region is Warangal
    ];

    $cart = [
        ['product_id' => 1, 'quantity' => 2]
    ];

    $threw = false;
    try {
        $orderService->createOrder($warangalCustomer, $cart, 'COD', 'Test notes', null, null, $sidHanamkonda);
    } catch (OrderValidationException $e) {
        $threw = true;
        if (!str_contains($e->getMessage(), 'scheduled for Hanamkonda, but your delivery address is in Warangal')) {
            throw new RuntimeException("Unexpected exception message: " . $e->getMessage());
        }
        echo "   [✓] Successfully caught region mismatch: " . $e->getMessage() . "\n";
    }

    if (!$threw) {
        throw new RuntimeException("Failed to reject region mismatch between customer address and delivery run!");
    }
});

// --------------------------------------------------------------------------
// TEST 4: Dual Ordering Window Enforcement (Before 5 AM & After 7 PM)
// --------------------------------------------------------------------------
runTest("Dual Ordering Window Enforcement", function() use ($orderService, $pdo, $sidHanamkonda) {
    // Schedule details
    $stmt = $pdo->prepare("SELECT order_open_datetime, cutoff_datetime FROM delivery_schedules WHERE id = :id");
    $stmt->execute([':id' => $sidHanamkonda]);
    $sched = $stmt->fetch(PDO::FETCH_ASSOC);

    $validCustomer = [
        'full_name'        => 'Test Timed Customer',
        'phone_number'     => '9988776602',
        'delivery_address' => 'Subedari, Hanamkonda',
        'region'           => 'Hanamkonda',
        'latitude'         => 18.0165000,
        'longitude'        => 79.5583000,
    ];
    $cart = [['product_id' => 1, 'quantity' => 2]];

    // 4a. Simulate order before 5:00 AM opening
    $tooEarly = (new DateTimeImmutable($sched['order_open_datetime'], TimeWindow::getTimeZone()))->modify('-30 minutes');
    $earlyThrew = false;
    try {
        $orderService->createOrder($validCustomer, $cart, 'COD', 'Early order test', $tooEarly, null, $sidHanamkonda);
    } catch (OrderValidationException $e) {
        $earlyThrew = true;
        if (!str_contains($e->getMessage(), 'opens on')) {
            throw new RuntimeException("Unexpected early open error message: " . $e->getMessage());
        }
        echo "   [✓] Caught pre-opening rejection: " . $e->getMessage() . "\n";
    }
    if (!$earlyThrew) {
        throw new RuntimeException("Failed to reject order placed before 5:00 AM opening!");
    }

    // 4b. Simulate order after 7:00 PM cutoff
    $tooLate = (new DateTimeImmutable($sched['cutoff_datetime'], TimeWindow::getTimeZone()))->modify('+10 minutes');
    $lateThrew = false;
    try {
        $orderService->createOrder($validCustomer, $cart, 'COD', 'Late order test', $tooLate, null, $sidHanamkonda);
    } catch (OrderValidationException $e) {
        $lateThrew = true;
        if (!str_contains($e->getMessage(), 'closed on')) {
            throw new RuntimeException("Unexpected cutoff error message: " . $e->getMessage());
        }
        echo "   [✓] Caught post-cutoff rejection: " . $e->getMessage() . "\n";
    }
    if (!$lateThrew) {
        throw new RuntimeException("Failed to reject order placed after 7:00 PM cutoff!");
    }
});

// --------------------------------------------------------------------------
// TEST 5: Order Creation, schedule_id persistence, and Run Stock Decrement
// --------------------------------------------------------------------------
$createdOrderId = null;
runTest("Order Creation & Atomic Run Stock Decrement", function() use ($orderService, $pdo, $runInventoryService, $sidHanamkonda, &$createdOrderId) {
    // Ensure known stock: set Product 1 to 45 packets
    $runInventoryService->bulkUpdateRunInventory($sidHanamkonda, [
        [
            'product_id'              => 1,
            'harvest_kg'              => 22.5,
            'available_half_kg_stock' => 45,
            'price_per_half_kg'       => 25.00,
            'is_active'               => true,
        ]
    ]);

    // Customer in Hanamkonda
    $validCustomer = [
        'full_name'        => 'Pipeline Verification Customer',
        'phone_number'     => '9988776603',
        'delivery_address' => 'Plot 55, Subedari, Hanamkonda',
        'landmark'         => 'Opp Kakatiya University Gate',
        'region'           => 'Hanamkonda',
        'latitude'         => 18.0165000,
        'longitude'        => 79.5583000,
    ];

    // Order 2 packets of Product 1 (value = 50.00) + 4 packets of Product 2 (value = 140.00) to exceed MOV (150)
    $cart = [
        ['product_id' => 1, 'quantity' => 2],
        ['product_id' => 2, 'quantity' => 4],
    ];

    // Make sure ordering time is within open window
    $stmt = $pdo->prepare("SELECT order_open_datetime, cutoff_datetime FROM delivery_schedules WHERE id = :id");
    $stmt->execute([':id' => $sidHanamkonda]);
    $sched = $stmt->fetch(PDO::FETCH_ASSOC);

    $openTime = new DateTimeImmutable($sched['order_open_datetime'], TimeWindow::getTimeZone());
    $validOrderTime = $openTime->modify('+2 hours');

    $result = $orderService->createOrder($validCustomer, $cart, 'COD', 'Pipeline verified test order', $validOrderTime, null, $sidHanamkonda);

    if (empty($result['order_id']) || empty($result['order_code'])) {
        throw new RuntimeException("Order creation returned empty order_id or order_code.");
    }

    $createdOrderId = (int) $result['order_id'];
    echo "   [✓] Order created successfully: ID #{$createdOrderId}, Code: {$result['order_code']}\n";

    // Verify orders.schedule_id in database
    $chkStmt = $pdo->prepare("SELECT schedule_id, order_status, total_amount FROM orders WHERE id = :id");
    $chkStmt->execute([':id' => $createdOrderId]);
    $orderRow = $chkStmt->fetch(PDO::FETCH_ASSOC);

    if ((int) $orderRow['schedule_id'] !== $sidHanamkonda) {
        throw new RuntimeException("Order schedule_id mismatch! Expected {$sidHanamkonda}, got {$orderRow['schedule_id']}.");
    }
    echo "   [✓] Verified orders.schedule_id = {$sidHanamkonda} in database.\n";

    // Verify run_inventory for Product 1 decremented from 45 to 43 packets
    $invStmt = $pdo->prepare("SELECT available_half_kg_stock FROM run_inventory WHERE schedule_id = :sid AND product_id = 1");
    $invStmt->execute([':sid' => $sidHanamkonda]);
    $newStock = (int) $invStmt->fetchColumn();

    if ($newStock !== 43) {
        throw new RuntimeException("Expected run_inventory stock to be 43 (45 - 2), got {$newStock}.");
    }
    echo "   [✓] Verified run_inventory for schedule #{$sidHanamkonda} decremented accurately (45 -> 43 pkts).\n";
});

// --------------------------------------------------------------------------
// TEST 6: Order Cancellation & Stock Restoration
// --------------------------------------------------------------------------
runTest("Order Cancellation & Stock Restoration", function() use ($orderService, $pdo, $sidHanamkonda, &$createdOrderId) {
    if (!$createdOrderId) {
        throw new RuntimeException("No created order ID available for cancellation test.");
    }

    // Cancel the order
    $cancelResult = $orderService->cancelOrder($createdOrderId, "Verification test cancellation");

    if (empty($cancelResult['success']) || !$cancelResult['success']) {
        throw new RuntimeException("OrderService::cancelOrder failed.");
    }

    // Verify order status is cancelled
    $chkStmt = $pdo->prepare("SELECT order_status FROM orders WHERE id = :id");
    $chkStmt->execute([':id' => $createdOrderId]);
    $status = $chkStmt->fetchColumn();

    if ($status !== 'cancelled') {
        throw new RuntimeException("Expected order_status to be 'cancelled', got '{$status}'.");
    }
    echo "   [✓] Order #{$createdOrderId} status transitioned to 'cancelled'.\n";

    // Verify run_inventory stock restored from 43 to 45 packets
    $invStmt = $pdo->prepare("SELECT available_half_kg_stock FROM run_inventory WHERE schedule_id = :sid AND product_id = 1");
    $invStmt->execute([':sid' => $sidHanamkonda]);
    $restoredStock = (int) $invStmt->fetchColumn();

    if ($restoredStock !== 45) {
        throw new RuntimeException("Expected run_inventory stock restored to 45, got {$restoredStock}.");
    }
    echo "   [✓] Verified run_inventory stock restored atomically to 45 packets (43 + 2).\n";
});

// --------------------------------------------------------------------------
// TEST 7: Storefront Locality Switching & Dynamic Run Catalog Resolution
// --------------------------------------------------------------------------
runTest("Storefront Locality Switching & Dynamic Catalog Resolution", function() use ($runInventoryService) {
    $regions = ['Hanamkonda', 'Warangal'];

    foreach ($regions as $reg) {
        $activeRun = $runInventoryService->getActiveScheduleForRegion($reg);
        $status = 'CLOSED';
        $targetSchedule = null;

        if ($activeRun) {
            $status = 'OPEN';
            $targetSchedule = $activeRun;
        } else {
            $upcomingRun = $runInventoryService->getNextUpcomingScheduleForRegion($reg);
            if ($upcomingRun) {
                $status = 'UPCOMING';
                $targetSchedule = $upcomingRun;
            }
        }

        if (!$targetSchedule) {
            throw new RuntimeException("Expected active or upcoming schedule for region: {$reg}");
        }

        $sid = (int) $targetSchedule['id'];
        $catalog = $runInventoryService->getRunCatalog($sid, $status === 'OPEN');

        if (empty($catalog)) {
            throw new RuntimeException("Expected non-empty catalog for {$reg} run #{$sid}");
        }

        echo "   [✓] {$reg}: Status = {$status}, Run #{$sid} ({$targetSchedule['delivery_day']}, {$targetSchedule['delivery_date']}), Catalog Items = " . count($catalog) . "\n";
    }
});

echo "\n============================================================\n";
echo "TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
