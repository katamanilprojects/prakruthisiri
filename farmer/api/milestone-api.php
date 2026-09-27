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

    $plotId = (int)($_POST['plot_id'] ?? 0);
    $stage  = trim((string)($_POST['stage'] ?? 'other'));
    $notes  = trim((string)($_POST['notes'] ?? ''));

    if ($plotId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid Plot ID']);
        exit;
    }

    $validStages = ['sowing', 'fertilizer_application', 'flowering', 'harvesting', 'other'];
    if (!in_array($stage, $validStages, true)) {
        $stage = 'other';
    }

    $photoPath = null;

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['photo']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid photo format. Please upload JPG, PNG, or WebP.']);
            exit;
        }

        // Validate image mime
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!str_starts_with((string)$mime, 'image/')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Uploaded file is not a valid image.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../../uploads/milestones/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = sprintf('milestone_p%d_%s_%s.%s', $plotId, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($tmpName, $targetFile)) {
            $photoPath = 'uploads/milestones/' . $filename;
            // Also mirror to public/uploads/milestones/ if that exists
            $publicDir = __DIR__ . '/../../public/uploads/milestones/';
            if (is_dir($publicDir)) {
                copy($targetFile, $publicDir . $filename);
            }
        }
    }

    $milestoneId = $farmService->addMilestone($plotId, $stage, $photoPath, $notes ?: null);

    echo json_encode([
        'success'      => true,
        'message'      => 'Crop milestone logged successfully.',
        'milestone_id' => $milestoneId,
        'photo_path'   => $photoPath,
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
