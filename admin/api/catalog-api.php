<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Vegetables Catalog & Inventory REST API
 * 
 * Decoupled endpoint for managing vegetables stock in 0.5kg increments,
 * pricing updates, and storefront active flags.
 */

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/RunInventoryService.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\RunInventoryService;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::getInstance()->getConnection();
    $runInventoryService = new RunInventoryService($pdo);

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '', true) ?: [];
    $data = array_merge($_GET, $_POST, $payload);

    $action = (string) ($data['action'] ?? 'get_catalog');
    $scheduleId = (int) ($data['schedule_id'] ?? $data['run_id'] ?? 0);

    switch ($action) {
        // --------------------------------------------------------------------
        // 1. Get Catalog List (Run-specific or Master)
        // --------------------------------------------------------------------
        case 'get_catalog':
            if ($scheduleId > 0) {
                $products = $runInventoryService->getRunCatalog($scheduleId, false);
                foreach ($products as &$p) {
                    $p['stock_kg'] = $p['harvest_kg'];
                    $p['price_fmt'] = '₹' . number_format((float) $p['price_per_half_kg'], 2);
                }
                unset($p);
            } else {
                $stmt = $pdo->query("
                    SELECT 
                        `id`,
                        `name`,
                        `telugu_name`,
                        `category`,
                        `price_per_half_kg`,
                        `available_half_kg_stock`,
                        `unit_label`,
                        `unit_weight_kg`,
                        `image_path`,
                        `is_active`,
                        `updated_at`
                    FROM `products`
                    ORDER BY `category` DESC, `name` ASC
                ");
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($products as &$p) {
                    $unitWeight = (float) ($p['unit_weight_kg'] ?? 0.5);
                    if ($unitWeight <= 0) $unitWeight = 0.5;
                    $stockPackets = (int) $p['available_half_kg_stock'];
                    $p['stock_kg'] = $stockPackets * $unitWeight;
                    $p['price_fmt'] = '₹' . number_format((float) $p['price_per_half_kg'], 2);
                }
                unset($p);
            }

            echo json_encode(['success' => true, 'schedule_id' => $scheduleId, 'products' => $products], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 2. Bulk Update Inventory & Pricing (Run-isolated)
        // --------------------------------------------------------------------
        case 'bulk_update_inventory':
            $items = $data['items'] ?? [];
            if (!is_array($items) || empty($items)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No inventory items provided for update.']);
                exit;
            }

            // Fetch unit weights map for accurate stock packet calculation
            $wStmt = $pdo->query("SELECT `id`, `unit_weight_kg` FROM `products`");
            $productWeights = $wStmt ? ($wStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []) : [];

            if ($scheduleId > 0) {
                // Update run_inventory strictly for this schedule
                $preparedItems = [];
                $zeroStockWarningCount = 0;

                foreach ($items as $item) {
                    $id = (int) ($item['id'] ?? $item['product_id'] ?? 0);
                    if ($id <= 0) continue;

                    $unitWeight = (float) ($productWeights[$id] ?? $item['unit_weight_kg'] ?? 0.5);
                    if ($unitWeight <= 0) $unitWeight = 0.5;

                    $stockKg = isset($item['stock_kg']) ? (float) $item['stock_kg'] : null;
                    $stockPackets = isset($item['stock_packets']) ? (int) $item['stock_packets'] : null;

                    if ($stockKg !== null) {
                        $stockPackets = (int) round($stockKg / $unitWeight);
                    } elseif ($stockPackets === null) {
                        $stockPackets = 0;
                    }
                    if ($stockPackets < 0) $stockPackets = 0;
                    if ($stockKg === null) $stockKg = $stockPackets * $unitWeight;

                    $price = isset($item['price']) ? (float) $item['price'] : 0.00;
                    $isActive = isset($item['is_active']) ? (int) (bool) $item['is_active'] : 1;

                    if ($isActive === 1 && $stockPackets === 0) {
                        $zeroStockWarningCount++;
                    }

                    $preparedItems[] = [
                        'product_id'              => $id,
                        'harvest_kg'              => $stockKg,
                        'available_half_kg_stock' => $stockPackets,
                        'price_per_half_kg'       => $price,
                        'is_active'               => $isActive,
                        'unit_weight_kg'          => $unitWeight,
                    ];
                }

                $res = $runInventoryService->bulkUpdateRunInventory($scheduleId, $preparedItems);

                $msg = "Run inventory updated successfully for {$res['updated_count']} varieties in schedule #{$scheduleId}.";
                if ($zeroStockWarningCount > 0) {
                    $msg .= " Note: {$zeroStockWarningCount} active varieties currently have 0 kg stock.";
                }

                echo json_encode([
                    'success'           => true,
                    'message'           => $msg,
                    'schedule_id'       => $scheduleId,
                    'updated_count'     => $res['updated_count'],
                    'zero_stock_alerts' => $zeroStockWarningCount,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Fallback: Global products update when no schedule_id specified
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE `products`
                SET 
                    `available_half_kg_stock` = :stock,
                    `price_per_half_kg`       = :price,
                    `is_active`               = :is_active,
                    `updated_at`              = CURRENT_TIMESTAMP
                WHERE `id` = :id
            ");

            $updatedCount = 0;
            $zeroStockWarningCount = 0;

            foreach ($items as $item) {
                $id = (int) ($item['id'] ?? $item['product_id'] ?? 0);
                if ($id <= 0) continue;

                $unitWeight = (float) ($productWeights[$id] ?? $item['unit_weight_kg'] ?? 0.5);
                if ($unitWeight <= 0) $unitWeight = 0.5;

                $stockKg = isset($item['stock_kg']) ? (float) $item['stock_kg'] : null;
                $stockPackets = isset($item['stock_packets']) ? (int) $item['stock_packets'] : null;

                if ($stockKg !== null) {
                    $stockPackets = (int) round($stockKg / $unitWeight);
                } elseif ($stockPackets === null) {
                    $stockPackets = 0;
                }

                if ($stockPackets < 0) {
                    $stockPackets = 0;
                }

                $price = isset($item['price']) ? (float) $item['price'] : 0.00;
                $isActive = isset($item['is_active']) ? (int) (bool) $item['is_active'] : 1;

                if ($isActive === 1 && $stockPackets === 0) {
                    $zeroStockWarningCount++;
                }

                $stmt->execute([
                    ':stock'     => $stockPackets,
                    ':price'     => $price,
                    ':is_active' => $isActive,
                    ':id'        => $id,
                ]);

                $updatedCount++;
            }

            $pdo->commit();

            $msg = "Bulk inventory updated successfully for {$updatedCount} varieties.";
            if ($zeroStockWarningCount > 0) {
                $msg .= " Note: {$zeroStockWarningCount} active varieties currently have 0 kg stock.";
            }

            echo json_encode([
                'success'           => true,
                'message'           => $msg,
                'updated_count'     => $updatedCount,
                'zero_stock_alerts' => $zeroStockWarningCount,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // --------------------------------------------------------------------
        // 3. Update Single Product
        // --------------------------------------------------------------------
        case 'update_product':
            $productId = (int) ($data['product_id'] ?? 0);
            if ($productId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid product ID.']);
                exit;
            }

            $wStmt = $pdo->prepare("SELECT `unit_weight_kg` FROM `products` WHERE `id` = :id LIMIT 1");
            $wStmt->execute([':id' => $productId]);
            $unitWeight = (float) ($wStmt->fetchColumn() ?: 0.5);
            if ($unitWeight <= 0) $unitWeight = 0.5;

            $stockKg = isset($data['stock_kg']) ? (float) $data['stock_kg'] : null;
            $stockPackets = isset($data['stock_packets']) ? (int) $data['stock_packets'] : null;

            if ($stockKg !== null) {
                $stockPackets = (int) round($stockKg / $unitWeight);
            } elseif ($stockPackets === null) {
                $stockPackets = 0;
            }

            $price = (float) ($data['price_per_half_kg'] ?? 0.00);
            $isActive = (int) (bool) ($data['is_active'] ?? 1);

            $stmt = $pdo->prepare("
                UPDATE `products`
                SET 
                    `available_half_kg_stock` = :stock,
                    `price_per_half_kg`       = :price,
                    `is_active`               = :is_active,
                    `updated_at`              = CURRENT_TIMESTAMP
                WHERE `id` = :id
            ");
            $stmt->execute([
                ':stock'     => max(0, $stockPackets),
                ':price'     => max(0.00, $price),
                ':is_active' => $isActive,
                ':id'        => $productId,
            ]);

            echo json_encode([
                'success'       => true,
                'message'       => 'Product updated successfully.',
                'product_id'    => $productId,
                'stock_packets' => $stockPackets,
                'stock_kg'      => $stockPackets * $unitWeight,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unknown action '{$action}'."]);
            exit;
    }

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Catalog API error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
