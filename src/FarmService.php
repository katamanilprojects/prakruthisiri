<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use PDO;
use Throwable;

/**
 * Prakruthi Siri - Farm & Agronomic Traceability Service
 * Manages 4 quarter-plots, crop lifecycle milestones, harvest estimation,
 * and batch traceability for radical organic transparency.
 */
class FarmService
{
    private PDO $pdo;

    public const CROP_STAGES = [
        'land_preparation' => ['en' => 'Land Preparation',   'te' => 'భూమి తయారీ'],
        'sown'             => ['en' => 'Sown',               'te' => 'విత్తనం నాటబడింది'],
        'vegetative'       => ['en' => 'Vegetative Growth',  'te' => 'శాకీయ పెరుగుదల'],
        'flowering'        => ['en' => 'Flowering & Fruiting','te' => 'పూత & కాత దశ'],
        'active_harvesting'=> ['en' => 'Active Harvesting',  'te' => 'కోత దశ (హార్వెస్టింగ్)'],
        'fallow'           => ['en' => 'Fallow / Rest',      'te' => 'విశ్రాంతి దశ'],
    ];

    public const MILESTONE_STAGES = [
        'sowing'                 => ['en' => 'Sowing',                 'te' => 'విత్తనాలు నాటడం'],
        'fertilizer_application' => ['en' => 'Organic Input (Jeevamrutham/Neem)','te' => 'సేంద్రీయ పోషకాలు (జీవామృతం/వేపనూనె)'],
        'flowering'              => ['en' => 'Flowering & Setting',    'te' => 'పూత & కాత'],
        'harvesting'             => ['en' => 'Harvesting',             'te' => 'తాజా కూరగాయల కోత'],
        'other'                  => ['en' => 'Plot Inspection',       'te' => 'పొలం పరిశీలన'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve all 4 quarter-plots with their current status and latest milestone.
     */
    public function getPlots(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                p.`id`, p.`plot_number`, p.`quarter_name`, p.`crop_type`,
                p.`status`, p.`sown_date`, p.`notes`, p.`updated_at`,
                (SELECT COUNT(*) FROM `crop_milestones` m WHERE m.`plot_id` = p.`id`) AS milestone_count,
                (SELECT m.`photo_path` FROM `crop_milestones` m WHERE m.`plot_id` = p.`id` AND m.`photo_path` IS NOT NULL ORDER BY m.`logged_at` DESC LIMIT 1) AS latest_photo,
                (SELECT m.`stage` FROM `crop_milestones` m WHERE m.`plot_id` = p.`id` ORDER BY m.`logged_at` DESC LIMIT 1) AS latest_stage,
                (SELECT m.`logged_at` FROM `crop_milestones` m WHERE m.`plot_id` = p.`id` ORDER BY m.`logged_at` DESC LIMIT 1) AS latest_milestone_at
            FROM `farm_plots` p
            ORDER BY p.`plot_number` ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retrieve a specific plot by ID.
     */
    public function getPlot(int $plotId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `farm_plots` WHERE `id` = :id LIMIT 1
        ");
        $stmt->execute([':id' => $plotId]);
        $plot = $stmt->fetch(PDO::FETCH_ASSOC);
        return $plot ?: null;
    }

    /**
     * Update plot status and crop details.
     */
    public function updatePlot(int $plotId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `farm_plots`
            SET 
                `quarter_name` = COALESCE(:quarter_name, `quarter_name`),
                `crop_type`    = :crop_type,
                `status`       = :status,
                `sown_date`    = :sown_date,
                `notes`        = :notes,
                `updated_at`   = CURRENT_TIMESTAMP
            WHERE `id` = :id
        ");

        return $stmt->execute([
            ':quarter_name' => !empty($data['quarter_name']) ? trim((string)$data['quarter_name']) : null,
            ':crop_type'    => !empty($data['crop_type']) ? trim((string)$data['crop_type']) : null,
            ':status'       => $data['status'] ?? 'land_preparation',
            ':sown_date'    => !empty($data['sown_date']) ? $data['sown_date'] : null,
            ':notes'        => !empty($data['notes']) ? trim((string)$data['notes']) : null,
            ':id'           => $plotId,
        ]);
    }

    /**
     * Record a crop milestone with optional photo.
     */
    public function addMilestone(int $plotId, string $stage, ?string $photoPath, ?string $notes): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `crop_milestones` (`plot_id`, `stage`, `photo_path`, `notes`, `logged_at`)
            VALUES (:plot_id, :stage, :photo_path, :notes, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':plot_id'    => $plotId,
            ':stage'      => $stage,
            ':photo_path' => $photoPath,
            ':notes'      => $notes,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Retrieve all milestones logged for a plot.
     */
    public function getMilestones(int $plotId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT `id`, `plot_id`, `stage`, `photo_path`, `notes`, `logged_at`
            FROM `crop_milestones`
            WHERE `plot_id` = :plot_id
            ORDER BY `logged_at` DESC
        ");
        $stmt->execute([':plot_id' => $plotId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retrieve upcoming delivery schedules for harvest forecasting.
     */
    public function getUpcomingSchedules(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                s.`id`, s.`delivery_date`, s.`delivery_day`, s.`target_region`,
                s.`order_open_datetime`, s.`cutoff_datetime`, s.`is_ordering_open`, s.`status`
            FROM `delivery_schedules` s
            WHERE s.`delivery_date` >= CURRENT_DATE() - INTERVAL 1 DAY
            ORDER BY s.`delivery_date` ASC
            LIMIT 5
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetch all active master products joined with any existing run_inventory for schedule & plot.
     */
    public function getHarvestProducts(int $scheduleId, ?int $plotId = null): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.`id` AS product_id,
                p.`name` AS product_name,
                p.`telugu_name`,
                p.`price_per_half_kg`,
                p.`unit_label`,
                p.`image_path`,
                p.`pricing_unit`,
                COALESCE(ri.`harvest_kg`, 0.00) AS harvest_kg,
                COALESCE(ri.`available_half_kg_stock`, 0) AS available_half_kg_stock,
                ri.`plot_id`,
                ri.`id` AS run_inventory_id
            FROM `products` p
            LEFT JOIN `run_inventory` ri ON p.`id` = ri.`product_id` AND ri.`schedule_id` = :schedule_id
            WHERE p.`is_active` = 1
            ORDER BY p.`name` ASC
        ");
        $stmt->execute([':schedule_id' => $scheduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Submit harvest forecast quantities (in kg) and push directly into run_inventory.
     * Items array format: [ ['product_id' => 1, 'harvest_kg' => 30.0], ... ]
     */
    public function submitHarvestEstimate(int $scheduleId, int $plotId, array $items): int
    {
        $this->pdo->beginTransaction();
        try {
            // Get master product default prices and pricing units
            $prodStmt = $this->pdo->query("SELECT `id`, `price_per_half_kg`, `pricing_unit`, `unit_label` FROM `products`");
            $products = [];
            foreach ($prodStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $products[(int)$p['id']] = $p;
            }

            $upsertStmt = $this->pdo->prepare("
                INSERT INTO `run_inventory` 
                    (`schedule_id`, `product_id`, `harvest_kg`, `available_half_kg_stock`, `price_per_half_kg`, `plot_id`, `pricing_unit`, `unit_label`, `is_active`)
                VALUES 
                    (:schedule_id, :product_id, :harvest_kg, :stock, :price, :plot_id, :pricing_unit, :unit_label, 1)
                ON DUPLICATE KEY UPDATE
                    `harvest_kg`              = VALUES(`harvest_kg`),
                    `available_half_kg_stock` = VALUES(`available_half_kg_stock`),
                    `plot_id`                 = VALUES(`plot_id`),
                    `pricing_unit`            = VALUES(`pricing_unit`),
                    `is_active`               = 1
            ");

            $updatedCount = 0;
            foreach ($items as $item) {
                $prodId = (int)($item['product_id'] ?? 0);
                $harvestKg = (float)($item['harvest_kg'] ?? 0.0);

                if ($prodId <= 0 || !isset($products[$prodId])) {
                    continue;
                }

                $prod = $products[$prodId];
                $pricingUnit = $prod['pricing_unit'] ?? 'half_kg';
                $unitPrice = (float)$prod['price_per_half_kg'];
                $unitLabel = $prod['unit_label'] ?? '0.5 kg (500g)';

                // Calculate available stock packs:
                // For 'half_kg': each pack is 0.5kg -> stock = floor(harvest_kg / 0.5) = harvest_kg * 2
                // For 'piece' or 'bunch': count is direct discrete quantity
                if ($pricingUnit === 'half_kg') {
                    $stock = (int)floor($harvestKg / 0.5);
                } else {
                    $stock = (int)round($harvestKg);
                }

                $upsertStmt->execute([
                    ':schedule_id'  => $scheduleId,
                    ':product_id'   => $prodId,
                    ':harvest_kg'   => $harvestKg,
                    ':stock'        => $stock,
                    ':price'        => $unitPrice,
                    ':plot_id'      => $plotId,
                    ':pricing_unit' => $pricingUnit,
                    ':unit_label'   => $unitLabel,
                ]);
                $updatedCount++;
            }

            // Also log milestone for the harvest if harvest_kg > 0 entered
            $totalKg = array_sum(array_column($items, 'harvest_kg'));
            if ($totalKg > 0) {
                $this->addMilestone(
                    $plotId,
                    'harvesting',
                    null,
                    sprintf('Harvest yield forecast submitted: %.1f kg across %d produce items.', $totalKg, $updatedCount)
                );
            }

            $this->pdo->commit();
            return $updatedCount;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Batch traceability lookup by schedule ID or order code.
     */
    public function getBatchTraceability(int|string $scheduleIdOrCode): ?array
    {
        $schedule = null;
        $order = null;

        // If numeric, look up schedule directly
        if (is_numeric($scheduleIdOrCode)) {
            $sStmt = $this->pdo->prepare("SELECT * FROM `delivery_schedules` WHERE `id` = :id LIMIT 1");
            $sStmt->execute([':id' => (int)$scheduleIdOrCode]);
            $schedule = $sStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Treat as order code
            $oStmt = $this->pdo->prepare("
                SELECT o.`id`, o.`order_code`, o.`schedule_id`, o.`target_delivery_date`,
                       s.`id` AS `s_id`, s.`delivery_date`, s.`delivery_day`, s.`target_region`
                FROM `orders` o
                LEFT JOIN `delivery_schedules` s ON o.`schedule_id` = s.`id`
                WHERE UPPER(TRIM(REPLACE(o.`order_code`, '#', ''))) = :code
                LIMIT 1
            ");
            $oStmt->execute([':code' => strtoupper(trim((string)$scheduleIdOrCode))]);
            $order = $oStmt->fetch(PDO::FETCH_ASSOC);
            if ($order && !empty($order['s_id'])) {
                $schedule = [
                    'id'            => $order['s_id'],
                    'delivery_date' => $order['delivery_date'] ?: $order['target_delivery_date'],
                    'delivery_day'  => $order['delivery_day'] ?? date('l', strtotime($order['target_delivery_date'])),
                    'target_region' => $order['target_region'] ?? 'Hanamkonda',
                ];
            }
        }

        if (!$schedule) {
            return null;
        }

        $scheduleId = (int)$schedule['id'];

        // Get plots linked to this schedule's run_inventory
        $pStmt = $this->pdo->prepare("
            SELECT DISTINCT p.*
            FROM `run_inventory` ri
            JOIN `farm_plots` p ON ri.`plot_id` = p.`id`
            WHERE ri.`schedule_id` = :sid
        ");
        $pStmt->execute([':sid' => $scheduleId]);
        $plots = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // If no plot explicitly linked, default to active_harvesting plot or Quarter 1
        if (empty($plots)) {
            $defPlot = $this->pdo->query("SELECT * FROM `farm_plots` WHERE `status` = 'active_harvesting' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$defPlot) {
                $defPlot = $this->pdo->query("SELECT * FROM `farm_plots` ORDER BY `plot_number` ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            }
            if ($defPlot) {
                $plots = [$defPlot];
            }
        }

        // Get milestones for each plot
        foreach ($plots as &$plot) {
            $plot['milestones'] = $this->getMilestones((int)$plot['id']);
        }
        unset($plot);

        // Get harvested vegetables in this batch
        $vStmt = $this->pdo->prepare("
            SELECT ri.`harvest_kg`, ri.`available_half_kg_stock`, ri.`pricing_unit`,
                   p.`name` AS product_name, p.`telugu_name`, p.`image_path`
            FROM `run_inventory` ri
            JOIN `products` p ON ri.`product_id` = p.`id`
            WHERE ri.`schedule_id` = :sid AND (ri.`harvest_kg` > 0 OR ri.`available_half_kg_stock` > 0)
            ORDER BY p.`name` ASC
        ");
        $vStmt->execute([':sid' => $scheduleId]);
        $harvestedItems = $vStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'schedule'        => $schedule,
            'order'           => $order,
            'plots'           => $plots,
            'harvested_items' => $harvestedItems,
        ];
    }
}
