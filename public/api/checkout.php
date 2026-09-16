<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/TimeWindow.php';
require_once __DIR__ . '/../../src/ConfigService.php';
require_once __DIR__ . '/../../src/GeoFenceService.php';
require_once __DIR__ . '/../../src/RunInventoryService.php';
require_once __DIR__ . '/../../src/OrderService.php';
require_once __DIR__ . '/../../src/Exceptions/InsufficientStockException.php';
require_once __DIR__ . '/../../src/Exceptions/OrderValidationException.php';
require_once __DIR__ . '/../../src/Exceptions/ProductNotFoundException.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\TimeWindow;
use PrakruthiSiri\ConfigService;
use PrakruthiSiri\GeoFenceService;
use PrakruthiSiri\RunInventoryService;
use PrakruthiSiri\OrderService;
use PrakruthiSiri\Exceptions\OrderValidationException;
use PrakruthiSiri\Exceptions\InsufficientStockException;
use PrakruthiSiri\Exceptions\ProductNotFoundException;

$pdo = Database::getInstance()->getConnection();
$runInventoryService = new RunInventoryService($pdo);

// ----------------------------------------------------------------------------
// ACTION: Get Catalog for Active Region (GET)
// ----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_run_catalog') {
    $region = trim((string)($_GET['region'] ?? 'Hanamkonda'));
    if (!in_array($region, ['Hanamkonda', 'Warangal'], true)) {
        $region = 'Hanamkonda';
    }

    $activeRun = $runInventoryService->getActiveScheduleForRegion($region);
    $status = 'OPEN';

    if (!$activeRun) {
        $activeRun = $runInventoryService->getNextUpcomingScheduleForRegion($region);
        $status = 'UPCOMING';
    }

    if (!$activeRun) {
        echo json_encode([
            'success' => false,
            'message' => "No active or upcoming delivery runs scheduled for {$region}."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $catalog = $runInventoryService->getRunCatalog((int)$activeRun['id'], $status === 'OPEN');
    $bookedCount = $runInventoryService->getBookedOrdersCount((int)$activeRun['id']);
    $activeRun['status_mode'] = $status;
    $activeRun['booked_orders_count'] = $bookedCount;
    $activeRun['max_orders_limit'] = 30;
    $activeRun['is_batch_full'] = ($bookedCount >= 30);
    $activeRun['cutoff_fmt'] = !empty($activeRun['cutoff_datetime']) ? date('D, d M - h:i A', strtotime($activeRun['cutoff_datetime'])) : '';
    $activeRun['order_open_fmt'] = !empty($activeRun['order_open_datetime']) ? date('D, d M - h:i A', strtotime($activeRun['order_open_datetime'])) : '';
    $activeRun['delivery_fmt'] = !empty($activeRun['delivery_date']) ? date('l, d M Y', strtotime($activeRun['delivery_date'])) : '';

    echo json_encode([
        'success'  => true,
        'schedule' => $activeRun,
        'catalog'  => $catalog
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------------------------
// ACTION: Place Order (POST)
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput ?: '', true) ?: [];
    $isEn = (($payload['lang'] ?? 'te') === 'en');

    $configService = new ConfigService($pdo);
    $overrideStatus = $configService->getStoreOverrideStatus();
    if ($overrideStatus === 'FORCE_CLOSED') {
        throw new OrderValidationException(
            $isEn 
                ? 'Orders are temporarily closed. Please check back shortly.' 
                : 'ఆర్డర్లు ప్రస్తుతం తాత్కాలికంగా నిలిపివేయబడ్డాయి. దయచేసి కాసేపటి తర్వాత ప్రయత్నించండి.'
        );
    }

    $customerData  = $payload['customer'] ?? [];
    $cartItems     = $payload['items'] ?? [];
    $paymentMethod = (string)($payload['payment_method'] ?? 'COD');
    $scheduleId    = (int)($payload['schedule_id'] ?? 0);

    if (empty($cartItems)) {
        throw new OrderValidationException(
            $isEn 
                ? 'Your cart is empty. Please select vegetables first.' 
                : 'మీ బుట్ట ఖాళీగా ఉంది. దయచేసి కనీసం ఒక కూరగాయను ఎంచుకోండి.'
        );
    }

    $customerRegion = trim((string)($customerData['region'] ?? 'Hanamkonda'));
    $customerLat    = !empty($customerData['latitude']) ? (float)$customerData['latitude'] : null;
    $customerLng    = !empty($customerData['longitude']) ? (float)$customerData['longitude'] : null;

    // 1. Mandatory 2-Layer Geofencing (Kazipet Longitude Cutoff & <= 11.5 km Radius)
    if ($customerLat !== null && $customerLng !== null) {
        if (GeoFenceService::isKazipet($customerLat, $customerLng)) {
            throw new OrderValidationException(
                $isEn
                    ? 'Kazipet is outside our delivery area. Currently serving Hanamkonda and Warangal only.'
                    : 'క్షమించండి! మేము కాజీపేట ప్రాంతానికి డెలివరీ చేయట్లేదు. ప్రస్తుతం హనుమకొండ మరియు వరంగల్ నగరాలకు మాత్రమే డెలివరీలు ఉన్నాయి.'
            );
        }
        if (!GeoFenceService::isWithinDeliveryRadius($customerLat, $customerLng)) {
            $distance = GeoFenceService::getDistanceKm((float)$customerLat, (float)$customerLng);
            throw new OrderValidationException(
                $isEn
                    ? "Your delivery location is " . number_format($distance, 1) . " km away. We only deliver within an 11.5 km radius from our farm hub."
                    : "మీ డెలివరీ లొకేషన్ మా ఫామ్ హబ్ నుండి " . number_format($distance, 1) . " km దూరంలో ఉంది. మేము 11.5 km పరిధి లోపల మాత్రమే డెలివరీ చేస్తాము."
            );
        }
    }

    // 2. Schedule Validation (Locality Match & Time Window)
    $stmt = $pdo->prepare("SELECT * FROM `delivery_schedules` WHERE `id` = :id LIMIT 1");
    $stmt->execute([':id' => $scheduleId]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        throw new OrderValidationException($isEn ? "Invalid delivery run selected." : "చెల్లని డెలివరీ రన్ ఎంచుకున్నారు.");
    }

    if ($schedule['target_region'] !== $customerRegion) {
        throw new OrderValidationException(
            $isEn
                ? "Your selected locality ({$customerRegion}) does not match the delivery run ({$schedule['target_region']})."
                : "మీరు ఎంచుకున్న చిరునామా ({$customerRegion}) మరియు డెలివరీ రన్ ({$schedule['target_region']}) సరిపోలడం లేదు."
        );
    }

    $now = TimeWindow::now();
    $tz = TimeWindow::getTimeZone();
    $openDt = new DateTimeImmutable($schedule['order_open_datetime'], $tz);
    $cutoffDt = new DateTimeImmutable($schedule['cutoff_datetime'], $tz);

    if ($now < $openDt) {
        throw new OrderValidationException(
            $isEn
                ? "Bookings for this batch have not opened yet. Orders open on {$openDt->format('d M \a\t h:i A')}."
                : "ఈ రన్కి ఆర్డర్లు ఇంకా ప్రారంభం కాలేదు. ఆర్డర్లు {$openDt->format('d M \a\t h:i A')} న తెరుచుకుంటాయి."
        );
    }
    if ($now > $cutoffDt || (int)$schedule['is_ordering_open'] === 0) {
        throw new OrderValidationException($isEn ? "Order cutoff for this batch has closed." : "ఈ రన్కి ఆర్డర్ల గడువు ముగిసింది.");
    }

    // 3. Enforce 30-Order Hard Cap per Batch
    $bookedCount = $runInventoryService->getBookedOrdersCount($scheduleId);
    if ($bookedCount >= 30) {
        throw new OrderValidationException(
            $isEn
                ? 'Sorry! This delivery batch is full. Please select another batch.'
                : 'క్షమించండి! ఈ డెలివరీ బ్యాచ్ పూర్తిగా నిండిపోయింది. దయచేసి తదుపరి బ్యాచ్ని ఎంచుకోండి.'
        );
    }

    // 4. Atomically Create Order & Decrement Run Stock
    $orderService = new OrderService($pdo, $configService, null, null, $runInventoryService);
    $result = $orderService->createOrder(
        $customerData,
        $cartItems,
        $paymentMethod,
        null,
        $now,
        $schedule['delivery_date'],
        $scheduleId
    );

    $result['batch_name'] = (!empty($schedule['delivery_day']) ? $schedule['delivery_day'] . ' Batch' : 'Delivery Batch') . ' — ' . date('d M Y', strtotime($schedule['delivery_date']));
    $result['delivery_address'] = (string)($customerData['delivery_address'] ?? '');
    $result['customer_name'] = (string)($customerData['full_name'] ?? '');
    $result['customer_phone'] = (string)($customerData['phone_number'] ?? '');
    $result['store_whatsapp'] = $configService->getStoreWhatsAppNumber();

    http_response_code(201);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (OrderValidationException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (InsufficientStockException $e) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (ProductNotFoundException $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
