<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use PDO;
use PrakruthiSiri\Config\Database;
use PrakruthiSiri\Exceptions\ProductNotFoundException;

/**
 * Inventory management service enforcing 0.5 kg (half-kg) base packet units,
 * conversion utilities, harvest stock updates, and storefront catalog reads.
 */
class InventoryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo === null) {
            require_once __DIR__ . '/../config/database.php';
            $this->pdo = Database::getInstance()->getConnection();
        } else {
            $this->pdo = $pdo;
        }
    }

    /**
     * Converts a kilogram weight into half-kg packet units.
     * Exactly $kg * 2 (e.g., 2.5 kg -> 5 packets).
     */
    public function convertKgToPackets(float $kg): int
    {
        return (int) round($kg * 2.0);
    }

    /**
     * Converts half-kg packet units into equivalent kilograms.
     * Exactly $packets * 0.5 (e.g., 7 packets -> 3.5 kg).
     */
    public function convertPacketsToKg(int $packets): float
    {
        return $packets * 0.5;
    }

    /**
     * Updates daily available stock for a product in 0.5 kg packets (e.g., after evening harvest).
     *
     * @param int $productId Target product ID
     * @param int $packets   Quantity in 0.5 kg packets
     * @return bool True if updated successfully
     * @throws ProductNotFoundException
     */
    public function updateHarvestStock(int $productId, int $packets): bool
    {
        if ($packets < 0) {
            $packets = 0;
        }

        $stmt = $this->pdo->prepare('
            UPDATE `products`
            SET `available_half_kg_stock` = :stock
            WHERE `id` = :id
        ');

        $stmt->execute([
            ':stock' => $packets,
            ':id'    => $productId,
        ]);

        if ($stmt->rowCount() === 0) {
            // Verify if product exists
            $check = $this->pdo->prepare('SELECT `id` FROM `products` WHERE `id` = :id');
            $check->execute([':id' => $productId]);
            if (!$check->fetch()) {
                throw new ProductNotFoundException($productId);
            }
        }

        return true;
    }

    /**
     * Updates daily available stock given weight in kilograms (converts to half-kg packets).
     */
    public function updateHarvestStockByKg(int $productId, float $kg): bool
    {
        $packets = $this->convertKgToPackets($kg);
        return $this->updateHarvestStock($productId, $packets);
    }

    /**
     * Bulk updates harvest inventory for multiple products atomically.
     *
     * @param array<int, int> $stockMap Associative array where key = product_id, value = packets (or array with 'product_id' and 'packets')
     */
    public function bulkUpdateHarvestStock(array $stockMap): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE `products`
            SET `available_half_kg_stock` = :stock
            WHERE `id` = :id
        ');

        $this->pdo->beginTransaction();

        try {
            foreach ($stockMap as $key => $value) {
                if (is_array($value)) {
                    $productId = (int) $value['product_id'];
                    $packets   = (int) ($value['packets'] ?? $this->convertKgToPackets((float) ($value['kg'] ?? 0)));
                } else {
                    $productId = (int) $key;
                    $packets   = (int) $value;
                }

                if ($packets < 0) {
                    $packets = 0;
                }

                $stmt->execute([
                    ':stock' => $packets,
                    ':id'    => $productId,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Fetches active stock catalog for the customer storefront.
     * Computes real-time kilogram equivalents and stock availability flags.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveStockCatalog(): array
    {
        $stmt = $this->pdo->query('
            SELECT 
                `id`,
                `name`,
                `telugu_name`,
                `category`,
                `price_per_half_kg`,
                `available_half_kg_stock`,
                `image_path`,
                `is_active`,
                `updated_at`
            FROM `products`
            WHERE `is_active` = 1
            ORDER BY `category` DESC, `name` ASC
        ');

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $p): array {
            $packets = (int) $p['available_half_kg_stock'];
            $pricePerHalfKg = (float) $p['price_per_half_kg'];

            return [
                'id'                      => (int) $p['id'],
                'name'                    => $p['name'],
                'telugu_name'             => $p['telugu_name'],
                'category'                => $p['category'],
                'price_per_half_kg'       => $pricePerHalfKg,
                'price_per_kg_equivalent' => $pricePerHalfKg * 2.0,
                'available_half_kg_stock' => $packets,
                'available_kg_equivalent' => $this->convertPacketsToKg($packets),
                'is_in_stock'             => $packets > 0,
                'image_path'              => $p['image_path'],
            ];
        }, $products);
    }

    /**
     * Retrieve single product current stock and availability info.
     *
     * @return array<string, mixed>|null
     */
    public function getProductStock(int $productId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                `id`,
                `name`,
                `telugu_name`,
                `category`,
                `price_per_half_kg`,
                `available_half_kg_stock`,
                `is_active`
            FROM `products`
            WHERE `id` = :id
            LIMIT 1
        ');

        $stmt->execute([':id' => $productId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            return null;
        }

        $packets = (int) $p['available_half_kg_stock'];
        $price = (float) $p['price_per_half_kg'];

        return [
            'id'                      => (int) $p['id'],
            'name'                    => $p['name'],
            'telugu_name'             => $p['telugu_name'],
            'category'                => $p['category'],
            'price_per_half_kg'       => $price,
            'price_per_kg_equivalent' => $price * 2.0,
            'available_half_kg_stock' => $packets,
            'available_kg_equivalent' => $this->convertPacketsToKg($packets),
            'is_in_stock'             => $packets > 0,
            'is_active'               => (bool) $p['is_active'],
        ];
    }

    /**
     * Restores stock for a product in half-kg packet increments (e.g. on order cancellation).
     *
     * @param int $productId Target product ID
     * @param int $packets   Quantity of 0.5 kg packets to restore
     * @return bool True if restored successfully
     * @throws ProductNotFoundException
     */
    public function restoreStock(int $productId, int $packets): bool
    {
        if ($packets <= 0) {
            return true;
        }

        $stmt = $this->pdo->prepare('
            UPDATE `products`
            SET `available_half_kg_stock` = `available_half_kg_stock` + :packets
            WHERE `id` = :id
        ');

        $stmt->execute([
            ':packets' => $packets,
            ':id'      => $productId,
        ]);

        if ($stmt->rowCount() === 0) {
            $check = $this->pdo->prepare('SELECT `id` FROM `products` WHERE `id` = :id');
            $check->execute([':id' => $productId]);
            if (!$check->fetch()) {
                throw new ProductNotFoundException($productId);
            }
        }

        return true;
    }
}

