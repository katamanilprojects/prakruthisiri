<?php
declare(strict_types=1);

/**
 * REQ-EXP-01 — Expansion Lead Capture API
 * POST /api/expansion-lead.php
 * Accepts: { phone_number, full_name, locality, landmark, latitude, longitude }
 * Writes to expansion_leads table; idempotent on phone+locality.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

use PrakruthiSiri\Config\Database;

try {
    $raw     = file_get_contents('php://input');
    $payload = json_decode($raw ?: '', true) ?: [];

    $phone    = preg_replace('/\D/', '', (string)($payload['phone_number'] ?? ''));
    if (strlen($phone) > 10) {
        $phone = substr($phone, -10);
    }
    $fullName = trim((string)($payload['full_name'] ?? ''));
    $locality = trim((string)($payload['locality'] ?? ''));
    $landmark = trim((string)($payload['landmark'] ?? '')) ?: null;
    $lat      = isset($payload['latitude'])  && is_numeric($payload['latitude'])  ? (float)$payload['latitude']  : null;
    $lng      = isset($payload['longitude']) && is_numeric($payload['longitude']) ? (float)$payload['longitude'] : null;

    if (strlen($phone) < 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A valid 10-digit phone number is required.']);
        exit;
    }
    if ($locality === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Locality is required.']);
        exit;
    }

    $pdo = Database::getInstance()->getConnection();

    // Idempotent: one lead per phone+locality combination
    $check = $pdo->prepare('SELECT id FROM expansion_leads WHERE phone_number = :p AND locality = :l LIMIT 1');
    $check->execute([':p' => $phone, ':l' => $locality]);
    if ($check->fetchColumn()) {
        echo json_encode(['success' => true, 'already_registered' => true]);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO expansion_leads (phone_number, full_name, locality, landmark, latitude, longitude)
        VALUES (:phone, :name, :locality, :landmark, :lat, :lng)
    ');
    $stmt->execute([
        ':phone'    => $phone,
        ':name'     => $fullName ?: null,
        ':locality' => $locality,
        ':landmark' => $landmark,
        ':lat'      => $lat,
        ':lng'      => $lng,
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'already_registered' => false]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
