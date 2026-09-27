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

    $plotId = (int)($data['plot_id'] ?? 0);
    if ($plotId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid Plot ID']);
        exit;
    }

    $validStatuses = ['land_preparation', 'sown', 'vegetative', 'flowering', 'active_harvesting', 'fallow'];
    $status = (string)($data['status'] ?? 'land_preparation');
    if (!in_array($status, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid plot status value']);
        exit;
    }

    $updateData = [
        'crop_type' => trim((string)($data['crop_type'] ?? '')),
        'status'    => $status,
        'sown_date' => !empty($data['sown_date']) ? (string)$data['sown_date'] : null,
        'notes'     => trim((string)($data['notes'] ?? '')),
    ];

    $ok = $farmService->updatePlot($plotId, $updateData);

    if ($ok) {
        echo json_encode([
            'success' => true,
            'message' => 'Plot details updated successfully.',
            'plot_id' => $plotId,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update plot.']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
