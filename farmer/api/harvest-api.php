<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/FarmService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\FarmService;

try {
    $pdo = Database::getInstance()->getConnection();
    $farmService = new FarmService($pdo);

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $scheduleId = (int)($data['schedule_id'] ?? 0);
    $plotId     = (int)($data['plot_id'] ?? 0);
    $items      = $data['items'] ?? [];

    if ($scheduleId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please select a delivery schedule batch.']);
        exit;
    }

    if ($plotId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please select the origin plot quarter.']);
        exit;
    }

    if (!is_array($items) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No produce quantities submitted.']);
        exit;
    }

    $updated = $farmService->submitHarvestEstimate($scheduleId, $plotId, $items);

    echo json_encode([
        'success'       => true,
        'message'       => sprintf('Harvest estimate saved. %d produce items updated in store inventory.', $updated),
        'items_updated' => $updated,
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
