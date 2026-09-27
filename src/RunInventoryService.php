<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use DateTimeImmutable;
use PDO;
use PrakruthiSiri\Config\Database;
use PrakruthiSiri\Exceptions\InsufficientStockException;
use PrakruthiSiri\Exceptions\ProductNotFoundException;

require_once __DIR__ . '/TimeWindow.php';
require_once __DIR__ . '/GeoFenceService.php';
require_once __DIR__ . '/Exceptions/InsufficientStockException.php';
require_once __DIR__ . '/Exceptions/ProductNotFoundException.php';

/**
 * Service managing day-wise and locality-specific delivery run inventory (`run_inventory`).
 * Isolates harvest stock and pricing between runs (e.g. Thursday Hanamkonda vs Tuesday Warangal)
 * to prevent cross-run overwrites and ensure atomic concurrency during order placement.
 */
class RunInventoryService
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
     * Converts a kilogram weight into packet/unit count based on product unit weight.
     */
    public function convertKgToPackets(float $kg, float $unitWeightKg = 0.5): int
    {
        $unitWeight = $unitWeightKg > 0 ? $unitWeightKg : 0.5;
        return (int) round($kg / $unitWeight);
    }

    /**
     * Converts packet/unit counts into equivalent kilograms based on product unit weight.
     */
    public function convertPacketsToKg(int $packets, float $unitWeightKg = 0.5): float
    {
        $unitWeight = $unitWeightKg > 0 ? $unitWeightKg : 0.5;
        return $packets * $unitWeight;
    }

    /**
     * Ensures all active catalog products have a row in `run_inventory` for the given schedule.
     */
    public function ensureScheduleInventory(int $scheduleId): void
    {
        if ($scheduleId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO `run_inventory` (
                `schedule_id`, `product_id`, `harvest_kg`, `available_half_kg_stock`, `price_per_half_kg`, `pricing_unit`, `unit_label`, `unit_weight_kg`, `is_active`
            )
            SELECT 
                :sid, p.`id`, ROUND(p.`available_half_kg_stock` * 0.5, 2), p.`available_half_kg_stock`, p.`price_per_half_kg`, p.`pricing_unit`, p.`unit_label`, p.`unit_weight_kg`, p.`is_active`
            FROM `products` p
            WHERE p.`is_active` = 1
              AND NOT EXISTS (
                  SELECT 1 FROM `run_inventory` ri WHERE ri.`schedule_id` = :sid_check AND ri.`product_id` = p.`id`
              )
        ');
        $stmt->execute([':sid' => $scheduleId, ':sid_check' => $scheduleId]);
    }

    /**
     * Fetches the complete vegetables catalog for a specific delivery schedule.
     *
     * @param int  $scheduleId Target delivery schedule ID
     * @param bool $onlyActive If true, only returns active products marked active in run_inventory
     * @return array<int, array<string, mixed>>
     */
    public function getRunCatalog(int $scheduleId, bool $onlyActive = true): array
    {
        $this->ensureScheduleInventory($scheduleId);

        $sql = '
            SELECT 
                p.`id` AS `product_id`,
                p.`name`,
                p.`telugu_name`,
                p.`category`,
                p.`image_path`,
                COALESCE(ri.`pricing_unit`, p.`pricing_unit`, "half_kg") AS `pricing_unit`,
                COALESCE(ri.`unit_label`, p.`unit_label`, "0.5 kg") AS `unit_label`,
                COALESCE(ri.`unit_weight_kg`, p.`unit_weight_kg`, 0.500) AS `unit_weight_kg`,
                ri.`id` AS `run_inventory_id`,
                ri.`schedule_id`,
                ri.`harvest_kg`,
                ri.`available_half_kg_stock`,
                ri.`price_per_half_kg`,
                ri.`is_active`
            FROM `products` p
            JOIN `run_inventory` ri ON ri.`product_id` = p.`id` AND ri.`schedule_id` = :sid
            WHERE p.`is_active` = 1 ' . ($onlyActive ? 'AND ri.`is_active` = 1' : '') . '
            ORDER BY p.`category` DESC, p.`name` ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sid' => $scheduleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $r): array {
            $packets = (int) $r['available_half_kg_stock'];
            $pricePerHalfKg = (float) $r['price_per_half_kg'];
            $harvestKg = (float) $r['harvest_kg'];
            $unitWeight = (float) ($r['unit_weight_kg'] ?? 0.500);
            $pricingUnit = (string) ($r['pricing_unit'] ?? 'half_kg');

            return [
                'id'                      => (int) $r['product_id'],
                'product_id'              => (int) $r['product_id'],
                'run_inventory_id'        => (int) $r['run_inventory_id'],
                'schedule_id'             => (int) $r['schedule_id'],
                'name'                    => $r['name'],
                'telugu_name'             => $r['telugu_name'],
                'category'                => $r['category'],
                'pricing_unit'            => $pricingUnit,
                'unit_label'              => (string) ($r['unit_label'] ?? '0.5 kg'),
                'unit_weight_kg'          => $unitWeight,
                'image_path'              => $r['image_path'],
                'harvest_kg'              => $harvestKg,
                'price_per_half_kg'       => $pricePerHalfKg,
                'unit_price'              => $pricePerHalfKg,
                'price_per_kg_equivalent' => round($pricePerHalfKg * 2.0, 2),
                'available_half_kg_stock' => $packets,
                'available_stock'         => $packets,
                'available_kg_equivalent' => $pricingUnit === 'half_kg' ? $this->convertPacketsToKg($packets, $unitWeight) : null,
                'is_in_stock'             => $packets > 0,
                'is_active'               => (bool) $r['is_active'],
            ];
        }, $rows);
    }

    /**
     * Atomically updates run inventory items for a delivery schedule.
     *
     * @param int $scheduleId
     * @param array<int, array<string, mixed>> $items Array of items with keys: product_id, harvest_kg, available_half_kg_stock, price_per_half_kg, is_active
     * @return array{success: bool, updated_count: int}
     */
    public function bulkUpdateRunInventory(int $scheduleId, array $items): array
    {
        $this->ensureScheduleInventory($scheduleId);

        $stmt = $this->pdo->prepare('
            INSERT INTO `run_inventory` (
                `schedule_id`, `product_id`, `harvest_kg`, `available_half_kg_stock`, `price_per_half_kg`, `is_active`
            ) VALUES (
                :sid, :pid, :harvest, :stock, :price, :active
            )
            ON DUPLICATE KEY UPDATE
                `harvest_kg`              = VALUES(`harvest_kg`),
                `available_half_kg_stock` = VALUES(`available_half_kg_stock`),
                `price_per_half_kg`       = VALUES(`price_per_half_kg`),
                `is_active`               = VALUES(`is_active`)
        ');

        $this->pdo->beginTransaction();
        $count = 0;

        try {
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $harvestKg = isset($item['harvest_kg']) ? (float) $item['harvest_kg'] : 0.00;
                $unitWeight = isset($item['unit_weight_kg']) ? (float) $item['unit_weight_kg'] : 0.5;
                $stock = isset($item['available_half_kg_stock']) 
                    ? (int) $item['available_half_kg_stock'] 
                    : $this->convertKgToPackets($harvestKg, $unitWeight);

                $price = isset($item['price_per_half_kg']) ? (float) $item['price_per_half_kg'] : 0.00;
                $isActive = !empty($item['is_active']) ? 1 : 0;

                $stmt->execute([
                    ':sid'     => $scheduleId,
                    ':pid'     => $productId,
                    ':harvest' => $harvestKg,
                    ':stock'   => max(0, $stock),
                    ':price'   => max(0.00, $price),
                    ':active'  => $isActive,
                ]);
                $count++;
            }

            $this->pdo->commit();
            return ['success' => true, 'updated_count' => $count];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atomically decrements stock for a product in a delivery run.
     * Enforces conditional UPDATE `available_half_kg_stock >= :qty`.
     *
     * @throws InsufficientStockException
     * @throws ProductNotFoundException
     */
    public function decrementStock(int $scheduleId, int $productId, int $halfKgQty): bool
    {
        if ($halfKgQty <= 0) {
            return true;
        }

        $stmt = $this->pdo->prepare('
            UPDATE `run_inventory`
            SET `available_half_kg_stock` = `available_half_kg_stock` - :qty
            WHERE `schedule_id` = :sid 
              AND `product_id` = :pid 
              AND `available_half_kg_stock` >= :min_qty
        ');

        $stmt->execute([
            ':qty'     => $halfKgQty,
            ':sid'     => $scheduleId,
            ':pid'     => $productId,
            ':min_qty' => $halfKgQty,
        ]);

        if ($stmt->rowCount() === 0) {
            // Find current stock
            $checkStmt = $this->pdo->prepare('
                SELECT ri.`available_half_kg_stock`, p.`name`
                FROM `run_inventory` ri
                JOIN `products` p ON p.`id` = ri.`product_id`
                WHERE ri.`schedule_id` = :sid AND ri.`product_id` = :pid
                LIMIT 1
            ');
            $checkStmt->execute([':sid' => $scheduleId, ':pid' => $productId]);
            $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new ProductNotFoundException($productId);
            }

            throw new InsufficientStockException(
                productId: $productId,
                productName: (string) $row['name'],
                requestedQuantity: $halfKgQty,
                availableStock: (int) $row['available_half_kg_stock']
            );
        }

        return true;
    }

    /**
     * Atomically restores stock for a product in a delivery run (e.g. on order cancellation).
     */
    public function restoreStock(int $scheduleId, int $productId, int $halfKgQty): bool
    {
        if ($halfKgQty <= 0) {
            return true;
        }

        $stmt = $this->pdo->prepare('
            UPDATE `run_inventory`
            SET `available_half_kg_stock` = `available_half_kg_stock` + :qty
            WHERE `schedule_id` = :sid AND `product_id` = :pid
        ');

        $stmt->execute([
            ':qty' => $halfKgQty,
            ':sid' => $scheduleId,
            ':pid' => $productId,
        ]);

        return true;
    }

    /**
     * Retrieves delivery schedule details by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getScheduleById(int $scheduleId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM `delivery_schedules` WHERE `id` = :id LIMIT 1
        ');
        $stmt->execute([':id' => $scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Finds the currently open and active delivery schedule for a customer\'s target region.
     * Enforces the dual time window:
     * 1. `order_open_datetime <= NOW()` (e.g., 5:00 AM previous day)
     * 2. `cutoff_datetime > NOW()` (e.g., 7:00 PM previous day)
     * 3. `is_ordering_open = 1`
     *
     * @param string $region Locality name (\'Hanamkonda\', \'Warangal\')
     * @param DateTimeImmutable|null $now Optional current timestamp for testing/overrides
     * @return array<string, mixed>|null
     */
    public function getActiveScheduleForRegion(string $region, ?DateTimeImmutable $now = null): ?array
    {
        $now = $now ?? TimeWindow::now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('
            SELECT * 
            FROM `delivery_schedules`
            WHERE `target_region` = :region
              AND `is_ordering_open` = 1
              AND `order_open_datetime` <= :now1
              AND `cutoff_datetime` > :now2
            ORDER BY `delivery_date` ASC
            LIMIT 1
        ');
        $stmt->execute([
            ':region' => $region,
            ':now1'   => $nowStr,
            ':now2'   => $nowStr,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Finds the next scheduled run for a region even if the ordering window has not opened yet.
     * Useful for displaying "Ordering opens Wednesday at 5:00 AM" banners.
     */
    public function getNextUpcomingScheduleForRegion(string $region, ?DateTimeImmutable $now = null): ?array
    {
        $now = $now ?? TimeWindow::now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('
            SELECT * 
            FROM `delivery_schedules`
            WHERE `target_region` = :region
              AND `cutoff_datetime` > :now
            ORDER BY `delivery_date` ASC
            LIMIT 1
        ');
        $stmt->execute([
            ':region' => $region,
            ':now'    => $nowStr,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Returns all upcoming delivery schedules ordered by delivery date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllUpcomingSchedules(): array
    {
        $stmt = $this->pdo->query('
            SELECT * 
            FROM `delivery_schedules`
            ORDER BY `delivery_date` ASC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Returns the number of active booked orders for a delivery batch.
     */
    public function getBookedOrdersCount(int $scheduleId): int
    {
        if ($scheduleId <= 0) {
            return 0;
        }

        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) 
            FROM `orders`
            WHERE `schedule_id` = :sid AND `order_status` != \'cancelled\'
        ');
        $stmt->execute([':sid' => $scheduleId]);
        return (int) $stmt->fetchColumn();
    }
}
