<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Driver Doorstep Verification API Endpoint
 * 
 * Validates driver authorization, gate photo MIME types, performs secure upload,
 * and updates customer GPS & order delivery status atomically.
 */

@ini_set('upload_max_filesize', '25M');
@ini_set('post_max_size', '30M');
@ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');

session_name('driver_session');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

// 1. Validate Driver Authorization
if (empty($_SESSION['driver_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized. Valid driver session required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Method Not Allowed. Only POST requests accepted.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
use PrakruthiSiri\Config\Database;

try {
    $rawInput = file_get_contents('php://input');
    $payload  = json_decode($rawInput ?: '', true) ?: [];
    $data     = array_merge($_POST, $payload);
    $action   = (string)($data['action'] ?? '');

    // 2.1 Fast-Path 1-Tap Delivery for Returning Verified Customers
    if ($action === 'quick_deliver') {
        $orderId = (int) ($data['order_id'] ?? 0);
        if ($orderId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid order ID.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        $driverId = (int)$_SESSION['driver_id'];
        $orderStmt = $pdo->prepare("
            UPDATE `orders`
            SET 
                `order_status`   = 'delivered',
                `delivered_at`   = CURRENT_TIMESTAMP,
                `payment_status` = CASE 
                                     WHEN `payment_method` = 'COD' THEN 'verified' 
                                     ELSE `payment_status` 
                                   END
            WHERE `id` = :order_id
              AND (`assigned_driver_id` = :driver_id OR `assigned_driver_id` IS NULL)
        ");
        $orderStmt->execute([':order_id' => $orderId, ':driver_id' => $driverId]);
        $pdo->commit();

        http_response_code(200);
        echo json_encode([
            'success'      => true,
            'message'      => 'Delivery completed successfully.',
            'order_id'     => $orderId,
            'delivered_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orderId    = (int) ($data['order_id'] ?? 0);
    $customerId = (int) ($data['customer_id'] ?? 0);
    $latitude   = !empty($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
    $longitude  = !empty($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null;

    if ($orderId <= 0 || $customerId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid order ID or customer ID.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Validate Uploaded Gate Photo (Supports high-res mobile photos up to 20MB)
    if (isset($_FILES['gate_photo']) && in_array($_FILES['gate_photo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        http_response_code(413);
        echo json_encode([
            'success' => false,
            'error'   => 'Gate photo exceeds maximum upload size. Please retake photo with standard resolution.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($_FILES['gate_photo']) || $_FILES['gate_photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Gate photo upload is required for first-time doorstep verification.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_FILES['gate_photo']['size'] > 20 * 1024 * 1024) {
        http_response_code(413); // Payload Too Large
        echo json_encode([
            'success' => false,
            'error'   => 'Gate photo must not exceed 20 MB.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tempPath = $_FILES['gate_photo']['tmp_name'];

    // Strict MIME Type Validation via finfo_file()
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tempPath);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        http_response_code(415); // Unsupported Media Type
        echo json_encode([
            'success' => false,
            'error'   => "Invalid image format ($mimeType). Only JPEG or PNG images are permitted.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. Save Sanitized Gate Photo to public/uploads/gates/
    // Re-encode via GD to strip all EXIF metadata, injected scripts, and polyglot payloads
    $uploadDir = __DIR__ . '/../../public/uploads/gates/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $uploadDir));
    }

    $fileName = hash('sha256', (string) $customerId . bin2hex(random_bytes(16))) . '.jpg';
    $targetFile = $uploadDir . $fileName;

    if (function_exists('imagecreatefromstring')) {
        $rawBytes = @file_get_contents($tempPath);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            throw new \RuntimeException('Failed to read uploaded photo.');
        }

        $img = @imagecreatefromstring($rawBytes);
        if ($img === false) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Uploaded file could not be decoded as a valid image.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $origW = imagesx($img);
        $origH = imagesy($img);
        $maxDim = 1280;

        if ($origW > $maxDim || $origH > $maxDim) {
            if ($origW >= $origH) {
                $newW = $maxDim;
                $newH = (int) round(($origH / $origW) * $maxDim);
            } else {
                $newH = $maxDim;
                $newW = (int) round(($origW / $origH) * $maxDim);
            }
            $scaledImg = imagescale($img, $newW, $newH);
            if ($scaledImg !== false) {
                imagedestroy($img);
                $img = $scaledImg;
            }
        }

        $saved = imagejpeg($img, $targetFile, 75);
        imagedestroy($img);

        if (!$saved) {
            throw new \RuntimeException('Failed to re-encode and store sanitized gate photo.');
        }
    } else {
        if (!move_uploaded_file($tempPath, $targetFile)) {
            throw new \RuntimeException('Failed to store uploaded gate photo on server.');
        }
    }

    @chmod($targetFile, 0644);

    // Relative web asset path for client viewing
    $relativePhotoPath = 'uploads/gates/' . $fileName;

    // 5. Execute Atomic Database Updates
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    try {
        $hasValidCoords = ($latitude !== null && $longitude !== null && abs($latitude) > 0.0001 && abs($longitude) > 0.0001);

        // Update Customer Record with verified gate photo and doorstep coordinates
        $custStmt = $pdo->prepare('
            UPDATE `customers`
            SET 
                `latitude`             = COALESCE(:lat, `latitude`),
                `longitude`            = COALESCE(:lng, `longitude`),
                `gate_photo_path`      = :gate_photo,
                `is_location_verified` = CASE 
                                           WHEN :has_valid_coords = 1 THEN 1 
                                           WHEN `latitude` IS NOT NULL AND `longitude` IS NOT NULL AND `is_location_verified` = 1 THEN 1
                                           ELSE 0 
                                         END,
                `updated_at`           = CURRENT_TIMESTAMP
            WHERE `id` = :customer_id
        ');

        $custStmt->execute([
            ':lat'              => $latitude,
            ':lng'              => $longitude,
            ':gate_photo'       => $relativePhotoPath,
            ':has_valid_coords' => $hasValidCoords ? 1 : 0,
            ':customer_id'      => $customerId,
        ]);

        // Synchronize customer_addresses table (default or most recent address)
        try {
            $addrStmt = $pdo->prepare('
                UPDATE `customer_addresses`
                SET 
                    `latitude`             = COALESCE(:lat, `latitude`),
                    `longitude`            = COALESCE(:lng, `longitude`),
                    `gate_photo_path`      = :gate_photo,
                    `is_location_verified` = CASE 
                                               WHEN :has_valid_coords = 1 THEN 1 
                                               WHEN `latitude` IS NOT NULL AND `longitude` IS NOT NULL AND `is_location_verified` = 1 THEN 1
                                               ELSE 0 
                                             END,
                    `updated_at`           = CURRENT_TIMESTAMP
                WHERE `customer_id` = :customer_id AND `is_default` = 1
            ');
            $addrStmt->execute([
                ':lat'              => $latitude,
                ':lng'              => $longitude,
                ':gate_photo'       => $relativePhotoPath,
                ':has_valid_coords' => $hasValidCoords ? 1 : 0,
                ':customer_id'      => $customerId,
            ]);

            if ($addrStmt->rowCount() === 0) {
                $recentAddrStmt = $pdo->prepare('
                    UPDATE `customer_addresses`
                    SET 
                        `latitude`             = COALESCE(:lat, `latitude`),
                        `longitude`            = COALESCE(:lng, `longitude`),
                        `gate_photo_path`      = :gate_photo,
                        `is_location_verified` = CASE 
                                                   WHEN :has_valid_coords = 1 THEN 1 
                                                   WHEN `latitude` IS NOT NULL AND `longitude` IS NOT NULL AND `is_location_verified` = 1 THEN 1
                                                   ELSE 0 
                                                 END,
                        `updated_at`           = CURRENT_TIMESTAMP
                    WHERE `customer_id` = :customer_id
                    ORDER BY `id` DESC
                    LIMIT 1
                ');
                $recentAddrStmt->execute([
                    ':lat'              => $latitude,
                    ':lng'              => $longitude,
                    ':gate_photo'       => $relativePhotoPath,
                    ':has_valid_coords' => $hasValidCoords ? 1 : 0,
                    ':customer_id'      => $customerId,
                ]);
            }
        } catch (\Throwable $addrEx) {
            // Gracefully ignore if customer_addresses table is absent
        }

        $driverId = (int)$_SESSION['driver_id'];
        $orderStmt = $pdo->prepare("
            UPDATE `orders`
            SET 
                `order_status`   = 'delivered',
                `delivered_at`   = CURRENT_TIMESTAMP,
                `payment_status` = CASE 
                                     WHEN `payment_method` = 'COD' THEN 'verified' 
                                     ELSE `payment_status` 
                                   END
            WHERE `id` = :order_id
              AND (`assigned_driver_id` = :driver_id OR `assigned_driver_id` IS NULL)
        ");

        $orderStmt->execute([':order_id' => $orderId, ':driver_id' => $driverId]);

        $pdo->commit();

        http_response_code(200);
        echo json_encode([
            'success'         => true,
            'message'         => 'Doorstep delivery verified successfully.',
            'order_id'        => $orderId,
            'customer_id'     => $customerId,
            'delivered_at'    => date('Y-m-d H:i:s'),
            'gate_photo_path' => $relativePhotoPath,
            'latitude'        => $latitude,
            'longitude'       => $longitude,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Remove uploaded file if DB transaction failed
        if (file_exists($targetFile)) {
            @unlink($targetFile);
        }
        throw $e;
    }

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Delivery verification error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
