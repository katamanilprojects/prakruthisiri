<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Financial & Revenue Reports API
 * 
 * Scalable SQL aggregation engine computing:
 *  - Top KPI Metrics (Gross Revenue, Net Veg Sales, Sold Kg, Packets, COD vs UPI, AOV)
 *  - Tab A: Periodic Aggregations (Daily / Monthly / Yearly)
 *  - Tab B: Crop-Wise Breakdown (Sales, Packets, Kg, Avg Unit Price, % Share)
 *  - Tab C: Itemized Transaction Ledger
 *  - Direct CSV/Excel Export with UTF-8 BOM for Telugu characters
 */

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

use PrakruthiSiri\Config\Database;

$isExport = isset($_GET['action']) && $_GET['action'] === 'export_csv';

if (!$isExport) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $pdo = Database::getInstance()->getConnection();

    // 1. Resolve Filter Parameters
    $input = $_GET;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '', true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }

    $dateDimension = (string) ($input['date_dimension'] ?? 'this_month');
    $specificDate  = trim((string) ($input['specific_date'] ?? ''));
    $startDate     = trim((string) ($input['start_date'] ?? ''));
    $endDate       = trim((string) ($input['end_date'] ?? ''));
    $specificMonth = trim((string) ($input['specific_month'] ?? ''));
    $fiscalYear    = trim((string) ($input['fiscal_year'] ?? ''));
    $calendarYear  = trim((string) ($input['calendar_year'] ?? ''));
    $cropId        = isset($input['crop_id']) && is_numeric($input['crop_id']) ? (int) $input['crop_id'] : 0;
    $paymentMode   = strtoupper(trim((string) ($input['payment_mode'] ?? 'ALL')));
    $orderStatus   = strtolower(trim((string) ($input['order_status'] ?? 'delivered')));
    $exportTab     = (string) ($input['export_tab'] ?? 'tab_periodic');

    // 2. Resolve Exact Date Range [from_datetime, to_datetime]
    $fromTime = '';
    $toTime   = '';
    $groupByFormat = '%Y-%m-%d';
    $periodLabel   = 'Day';

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $todayStr = $now->format('Y-m-d');

    switch ($dateDimension) {
        case 'today':
            $fromTime = $todayStr . ' 00:00:00';
            $toTime   = $todayStr . ' 23:59:59';
            $groupByFormat = '%Y-%m-%d';
            $periodLabel = 'Date';
            break;

        case 'yesterday':
            $yest = $now->modify('-1 day')->format('Y-m-d');
            $fromTime = $yest . ' 00:00:00';
            $toTime   = $yest . ' 23:59:59';
            $groupByFormat = '%Y-%m-%d';
            $periodLabel = 'Date';
            break;

        case 'specific_date':
            if ($specificDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $specificDate)) {
                $specificDate = $todayStr;
            }
            $fromTime = $specificDate . ' 00:00:00';
            $toTime   = $specificDate . ' 23:59:59';
            $groupByFormat = '%Y-%m-%d';
            $periodLabel = 'Date';
            break;

        case 'custom_range':
            if ($startDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                $startDate = $now->modify('-7 days')->format('Y-m-d');
            }
            if ($endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                $endDate = $todayStr;
            }
            $fromTime = $startDate . ' 00:00:00';
            $toTime   = $endDate . ' 23:59:59';
            $groupByFormat = '%Y-%m-%d';
            $periodLabel = 'Date';
            break;

        case 'this_week':
            $mon = $now->modify('monday this week')->format('Y-m-d');
            $sun = $now->modify('sunday this week')->format('Y-m-d');
            $fromTime = $mon . ' 00:00:00';
            $toTime   = $sun . ' 23:59:59';
            $groupByFormat = '%Y-%m-%d';
            $periodLabel = 'Date';
            break;

        case 'this_month':
            $fromTime = $now->format('Y-m-01 00:00:00');
            $toTime   = $now->format('Y-m-t 23:59:59');
            $groupByFormat = '%Y-%m-%d';
            $periodLabel = 'Day';
            break;

        case 'specific_month':
            if ($specificMonth === '' || !preg_match('/^\d{4}-\d{2}$/', $specificMonth)) {
                $specificMonth = $now->format('Y-m');
            }
            $mStart = new DateTimeImmutable($specificMonth . '-01 00:00:00', new DateTimeZone('Asia/Kolkata'));
            $fromTime = $mStart->format('Y-m-01 00:00:00');
            $toTime   = $mStart->format('Y-m-t 23:59:59');
            $groupByFormat = '%Y-%m-%d';
            $periodLabel = 'Day';
            break;

        case 'financial_year':
            // Indian FY: April 1 to March 31
            // e.g. "2025-2026" or "2025"
            $fyYear = 2025;
            if (preg_match('/^(\d{4})/', $fiscalYear, $m)) {
                $fyYear = (int) $m[1];
            } else {
                $currentYear = (int) $now->format('Y');
                $currentMonth = (int) $now->format('m');
                $fyYear = ($currentMonth >= 4) ? $currentYear : ($currentYear - 1);
            }
            $fromTime = sprintf('%04d-04-01 00:00:00', $fyYear);
            $toTime   = sprintf('%04d-03-31 23:59:59', $fyYear + 1);
            $groupByFormat = '%Y-%m';
            $periodLabel = 'Month';
            break;

        case 'calendar_year':
            $calYear = (int) ($calendarYear ?: $now->format('Y'));
            if ($calYear < 2020 || $calYear > 2035) {
                $calYear = (int) $now->format('Y');
            }
            $fromTime = sprintf('%04d-01-01 00:00:00', $calYear);
            $toTime   = sprintf('%04d-12-31 23:59:59', $calYear);
            $groupByFormat = '%Y-%m';
            $periodLabel = 'Month';
            break;

        default:
            $fromTime = $now->format('Y-m-01 00:00:00');
            $toTime   = $now->format('Y-m-t 23:59:59');
            $groupByFormat = '%Y-%m-%d';
            $periodLabel = 'Day';
            break;
    }

    // 3. Build WHERE Clauses
    $whereOrders = ['o.`target_delivery_date` >= :from_date', 'o.`target_delivery_date` <= :to_date'];
    $paramsOrders = [
        ':from_date' => substr($fromTime, 0, 10),
        ':to_date'   => substr($toTime, 0, 10),
    ];

    // Status filter
    if ($orderStatus === 'delivered') {
        $whereOrders[] = "o.`order_status` = 'delivered'";
    } elseif ($orderStatus === 'completed') {
        $whereOrders[] = "o.`order_status` IN ('packed', 'out_for_delivery', 'delivered')";
    } elseif ($orderStatus === 'cancelled') {
        $whereOrders[] = "o.`order_status` = 'cancelled'";
    }

    // Payment mode filter
    if ($paymentMode === 'COD' || $paymentMode === 'UPI') {
        $whereOrders[] = "o.`payment_method` = :pmode";
        $paramsOrders[':pmode'] = $paymentMode;
    }

    // If a specific crop is selected, filter orders having that crop
    if ($cropId > 0) {
        $whereOrders[] = "EXISTS (SELECT 1 FROM `order_items` oi_sub WHERE oi_sub.`order_id` = o.`id` AND oi_sub.`product_id` = :fcrop)";
        $paramsOrders[':fcrop'] = $cropId;
    }

    $whereSqlOrders = implode(' AND ', $whereOrders);

    // ------------------------------------------------------------------------
    // Build Item-Level WHERE Clauses (for Weight/Packets and Crop-Filtered Revenue)
    // ------------------------------------------------------------------------
    $itemsWhere = ["o.`target_delivery_date` >= :from_date", "o.`target_delivery_date` <= :to_date"];
    $itemsParams = [
        ':from_date' => substr($fromTime, 0, 10),
        ':to_date'   => substr($toTime, 0, 10),
    ];
    if ($orderStatus === 'delivered') {
        $itemsWhere[] = "o.`order_status` = 'delivered'";
    } elseif ($orderStatus === 'completed') {
        $itemsWhere[] = "o.`order_status` IN ('packed', 'out_for_delivery', 'delivered')";
    } elseif ($orderStatus === 'cancelled') {
        $itemsWhere[] = "o.`order_status` = 'cancelled'";
    }
    if ($paymentMode === 'COD' || $paymentMode === 'UPI') {
        $itemsWhere[] = "o.`payment_method` = :pmode";
        $itemsParams[':pmode'] = $paymentMode;
    }
    if ($cropId > 0) {
        $itemsWhere[] = "oi.`product_id` = :fcrop";
        $itemsParams[':fcrop'] = $cropId;
    }
    $itemsWhereSql = implode(' AND ', $itemsWhere);

    // ------------------------------------------------------------------------
    // A. Top Metric Cards (KPI Summary)
    // ------------------------------------------------------------------------
    if ($cropId > 0) {
        // When filtering by a specific crop, aggregate line items directly:
        // delivery fee is an order-level attribute (0.00 for crop), revenue = crop sales
        $kpiSql = "
            SELECT 
                COUNT(DISTINCT o.`id`) AS `total_orders`,
                COALESCE(SUM(oi.`line_total`), 0.00) AS `net_vegetable_sales`,
                0.00 AS `total_delivery_fees`,
                COALESCE(SUM(oi.`line_total`), 0.00) AS `gross_revenue`,
                COALESCE(SUM(CASE WHEN o.`payment_method` = 'COD' THEN oi.`line_total` ELSE 0 END), 0.00) AS `cod_total`,
                COALESCE(SUM(CASE WHEN o.`payment_method` = 'UPI' THEN oi.`line_total` ELSE 0 END), 0.00) AS `upi_total`
            FROM `order_items` oi
            JOIN `orders` o ON oi.`order_id` = o.`id`
            WHERE {$itemsWhereSql}
        ";
        $kpiStmt = $pdo->prepare($kpiSql);
        $kpiStmt->execute($itemsParams);
    } else {
        $kpiSql = "
            SELECT 
                COUNT(DISTINCT o.`id`) AS `total_orders`,
                COALESCE(SUM(o.`subtotal`), 0.00) AS `net_vegetable_sales`,
                COALESCE(SUM(o.`delivery_fee`), 0.00) AS `total_delivery_fees`,
                COALESCE(SUM(o.`total_amount`), 0.00) AS `gross_revenue`,
                COALESCE(SUM(CASE WHEN o.`payment_method` = 'COD' THEN o.`total_amount` ELSE 0 END), 0.00) AS `cod_total`,
                COALESCE(SUM(CASE WHEN o.`payment_method` = 'UPI' THEN o.`total_amount` ELSE 0 END), 0.00) AS `upi_total`
            FROM `orders` o
            WHERE {$whereSqlOrders}
        ";
        $kpiStmt = $pdo->prepare($kpiSql);
        $kpiStmt->execute($paramsOrders);
    }
    $kpiData = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Weight and packet totals from order_items (taking crop filter into account)
    $itemsSumSql = "
        SELECT 
            COALESCE(SUM(oi.`half_kg_quantity`), 0) AS `total_packets`,
            COALESCE(SUM(oi.`half_kg_quantity` * 0.5), 0.0) AS `total_kg_sold`
        FROM `order_items` oi
        JOIN `orders` o ON oi.`order_id` = o.`id`
        WHERE {$itemsWhereSql}
    ";
    $itemsSumStmt = $pdo->prepare($itemsSumSql);
    $itemsSumStmt->execute($itemsParams);
    $itemsSum = $itemsSumStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalOrders  = (int) ($kpiData['total_orders'] ?? 0);
    $grossRevenue = (float) ($kpiData['gross_revenue'] ?? 0.0);
    $netVegSales  = (float) ($kpiData['net_vegetable_sales'] ?? 0.0);
    $deliveryFees = (float) ($kpiData['total_delivery_fees'] ?? 0.0);
    $codTotal     = (float) ($kpiData['cod_total'] ?? 0.0);
    $upiTotal     = (float) ($kpiData['upi_total'] ?? 0.0);
    $totalPackets = (int) ($itemsSum['total_packets'] ?? 0);
    $totalKgSold  = (float) ($itemsSum['total_kg_sold'] ?? 0.0);
    $aov          = ($totalOrders > 0) ? ($grossRevenue / $totalOrders) : 0.0;

    $kpiSummary = [
        'gross_revenue'       => $grossRevenue,
        'gross_revenue_fmt'   => '₹' . number_format($grossRevenue, 2),
        'net_vegetable_sales' => $netVegSales,
        'net_veg_sales_fmt'   => '₹' . number_format($netVegSales, 2),
        'total_delivery_fees' => $deliveryFees,
        'delivery_fees_fmt'   => '₹' . number_format($deliveryFees, 2),
        'total_orders'        => $totalOrders,
        'total_kg_sold'       => $totalKgSold,
        'total_kg_sold_fmt'   => number_format($totalKgSold, 1) . ' kg',
        'total_packets'       => $totalPackets,
        'cod_total'           => $codTotal,
        'cod_total_fmt'       => '₹' . number_format($codTotal, 2),
        'upi_total'           => $upiTotal,
        'upi_total_fmt'       => '₹' . number_format($upiTotal, 2),
        'aov'                 => $aov,
        'aov_fmt'             => '₹' . number_format($aov, 2),
        'period_label'        => $periodLabel,
        'date_from'           => substr($fromTime, 0, 10),
        'date_to'             => substr($toTime, 0, 10),
    ];

    // ------------------------------------------------------------------------
    // B. Tab A: Periodic Aggregations (Grouped by Day or Month)
    // ------------------------------------------------------------------------
    if ($cropId > 0) {
        $periodicSql = "
            SELECT 
                DATE_FORMAT(o.`target_delivery_date`, '{$groupByFormat}') AS `period_key`,
                COUNT(DISTINCT o.`id`) AS `period_orders`,
                COALESCE(SUM(oi.`line_total`), 0.00) AS `period_subtotal`,
                0.00 AS `period_delivery_fee`,
                COALESCE(SUM(oi.`line_total`), 0.00) AS `period_revenue`,
                COALESCE(SUM(CASE WHEN o.`payment_method` = 'COD' THEN oi.`line_total` ELSE 0 END), 0.00) AS `period_cod`,
                COALESCE(SUM(CASE WHEN o.`payment_method` = 'UPI' THEN oi.`line_total` ELSE 0 END), 0.00) AS `period_upi`
            FROM `order_items` oi
            JOIN `orders` o ON oi.`order_id` = o.`id`
            WHERE {$itemsWhereSql}
            GROUP BY `period_key`
            ORDER BY `period_key` DESC
        ";
        $pStmt = $pdo->prepare($periodicSql);
        $pStmt->execute($itemsParams);
    } else {
        $periodicSql = "
            SELECT 
                DATE_FORMAT(o.`target_delivery_date`, '{$groupByFormat}') AS `period_key`,
                COUNT(DISTINCT o.`id`) AS `period_orders`,
                COALESCE(SUM(o.`subtotal`), 0.00) AS `period_subtotal`,
                COALESCE(SUM(o.`delivery_fee`), 0.00) AS `period_delivery_fee`,
                COALESCE(SUM(o.`total_amount`), 0.00) AS `period_revenue`,
                COALESCE(SUM(CASE WHEN o.`payment_method` = 'COD' THEN o.`total_amount` ELSE 0 END), 0.00) AS `period_cod`,
                COALESCE(SUM(CASE WHEN o.`payment_method` = 'UPI' THEN o.`total_amount` ELSE 0 END), 0.00) AS `period_upi`
            FROM `orders` o
            WHERE {$whereSqlOrders}
            GROUP BY `period_key`
            ORDER BY `period_key` DESC
        ";
        $pStmt = $pdo->prepare($periodicSql);
        $pStmt->execute($paramsOrders);
    }
    $periodicRows = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Aggregate weight & packets per period
    $periodicWeightsSql = "
        SELECT 
            DATE_FORMAT(o.`target_delivery_date`, '{$groupByFormat}') AS `period_key`,
            COALESCE(SUM(oi.`half_kg_quantity`), 0) AS `period_packets`,
            COALESCE(SUM(oi.`half_kg_quantity` * 0.5), 0.0) AS `period_kg`
        FROM `order_items` oi
        JOIN `orders` o ON oi.`order_id` = o.`id`
        WHERE {$itemsWhereSql}
        GROUP BY `period_key`
    ";
    $pwStmt = $pdo->prepare($periodicWeightsSql);
    $pwStmt->execute($itemsParams);
    $pwRows = $pwStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $pwMap = [];
    foreach ($pwRows as $r) {
        $pwMap[$r['period_key']] = $r;
    }

    $tabPeriodic = [];
    foreach ($periodicRows as $row) {
        $pkey = $row['period_key'];
        $kg   = (float) ($pwMap[$pkey]['period_kg'] ?? 0.0);
        $pkts = (int) ($pwMap[$pkey]['period_packets'] ?? 0);
        
        $tabPeriodic[] = [
            'period'          => $pkey,
            'orders_count'    => (int) $row['period_orders'],
            'weight_kg'       => $kg,
            'weight_kg_fmt'   => number_format($kg, 1) . ' kg',
            'packets'         => $pkts,
            'subtotal'        => (float) $row['period_subtotal'],
            'subtotal_fmt'    => '₹' . number_format((float) $row['period_subtotal'], 2),
            'delivery_fee'    => (float) $row['period_delivery_fee'],
            'delivery_fee_fmt'=> '₹' . number_format((float) $row['period_delivery_fee'], 2),
            'revenue'         => (float) $row['period_revenue'],
            'revenue_fmt'     => '₹' . number_format((float) $row['period_revenue'], 2),
            'cod_amount'      => (float) $row['period_cod'],
            'cod_amount_fmt'  => '₹' . number_format((float) $row['period_cod'], 2),
            'upi_amount'      => (float) $row['period_upi'],
            'upi_amount_fmt'  => '₹' . number_format((float) $row['period_upi'], 2),
        ];
    }

    // ------------------------------------------------------------------------
    // C. Tab B: Crop-Wise Breakdown
    // ------------------------------------------------------------------------
    if ($cropId > 0) {
        $cropBreakdownSql = "
            SELECT 
                p.`id` AS `product_id`,
                p.`name` AS `crop_name`,
                p.`telugu_name`,
                p.`category`,
                COALESCE(SUM(oi.`half_kg_quantity`), 0) AS `packets_sold`,
                COALESCE(SUM(oi.`half_kg_quantity` * 0.5), 0.0) AS `kg_sold`,
                COALESCE(AVG(oi.`unit_price_applied`), p.`price_per_half_kg`) AS `avg_price_per_unit`,
                COALESCE(SUM(oi.`line_total`), 0.00) AS `crop_gross_revenue`
            FROM `products` p
            JOIN `order_items` oi ON p.`id` = oi.`product_id`
            JOIN `orders` o ON oi.`order_id` = o.`id`
            WHERE p.`id` = :filter_crop_id AND {$whereSqlOrders}
            GROUP BY p.`id`, p.`name`, p.`telugu_name`, p.`category`, p.`price_per_half_kg`
            ORDER BY `crop_gross_revenue` DESC, `kg_sold` DESC
        ";
        $cropParams = array_merge($paramsOrders, [':filter_crop_id' => $cropId]);
    } else {
        $cropBreakdownSql = "
            SELECT 
                p.`id` AS `product_id`,
                p.`name` AS `crop_name`,
                p.`telugu_name`,
                p.`category`,
                COALESCE(SUM(oi.`half_kg_quantity`), 0) AS `packets_sold`,
                COALESCE(SUM(oi.`half_kg_quantity` * 0.5), 0.0) AS `kg_sold`,
                COALESCE(AVG(oi.`unit_price_applied`), p.`price_per_half_kg`) AS `avg_price_per_unit`,
                COALESCE(SUM(oi.`line_total`), 0.00) AS `crop_gross_revenue`
            FROM `products` p
            JOIN `order_items` oi ON p.`id` = oi.`product_id`
            JOIN `orders` o ON oi.`order_id` = o.`id`
            WHERE {$whereSqlOrders}
            GROUP BY p.`id`, p.`name`, p.`telugu_name`, p.`category`, p.`price_per_half_kg`
            HAVING `packets_sold` > 0
            ORDER BY `crop_gross_revenue` DESC, `kg_sold` DESC
        ";
        $cropParams = $paramsOrders;
    }
    $cbStmt = $pdo->prepare($cropBreakdownSql);
    $cbStmt->execute($cropParams);
    $cropRows = $cbStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $tabCrops = [];
    $totalCropRevenue = array_sum(array_column($cropRows, 'crop_gross_revenue')) ?: 1.0;

    foreach ($cropRows as $cr) {
        $cRev = (float) $cr['crop_gross_revenue'];
        $sharePercent = ($cRev / $totalCropRevenue) * 100.0;
        $kg = (float) $cr['kg_sold'];
        $pkts = (int) $cr['packets_sold'];

        $tabCrops[] = [
            'product_id'      => (int) $cr['product_id'],
            'crop_name'       => $cr['crop_name'],
            'telugu_name'     => $cr['telugu_name'],
            'category'        => ucfirst($cr['category']),
            'kg_sold'         => $kg,
            'kg_sold_fmt'     => number_format($kg, 1) . ' kg',
            'packets_sold'    => $pkts,
            'avg_price'       => (float) $cr['avg_price_per_unit'],
            'avg_price_fmt'   => '₹' . number_format((float) $cr['avg_price_per_unit'], 2),
            'gross_revenue'   => $cRev,
            'gross_revenue_fmt'=> '₹' . number_format($cRev, 2),
            'revenue_share'   => round($sharePercent, 1),
            'revenue_share_fmt'=> number_format($sharePercent, 1) . '%',
        ];
    }

    // ------------------------------------------------------------------------
    // D. Tab C: Itemized Transaction Log
    // ------------------------------------------------------------------------
    $limit = $isExport ? 10000 : 250;
    if ($cropId > 0) {
        // When filtering by a single crop, output line-item revenue for this crop
        $ledgerSql = "
            SELECT 
                o.`id` AS `order_id`,
                o.`order_code`,
                o.`created_at`,
                o.`target_delivery_date`,
                o.`order_status`,
                o.`payment_method`,
                o.`payment_status`,
                COALESCE(SUM(oi.`line_total`), 0.00) AS `subtotal`,
                0.00 AS `delivery_fee`,
                COALESCE(SUM(oi.`line_total`), 0.00) AS `total_amount`,
                c.`full_name` AS `customer_name`,
                c.`phone_number` AS `customer_phone`,
                c.`region`,
                c.`landmark`
            FROM `orders` o
            JOIN `customers` c ON o.`customer_id` = c.`id`
            JOIN `order_items` oi ON oi.`order_id` = o.`id` AND oi.`product_id` = :fcrop_ledger
            WHERE {$whereSqlOrders}
            GROUP BY o.`id`, o.`order_code`, o.`created_at`, o.`target_delivery_date`, o.`order_status`, o.`payment_method`, o.`payment_status`, c.`full_name`, c.`phone_number`, c.`region`, c.`landmark`
            ORDER BY o.`target_delivery_date` DESC, o.`id` DESC
            LIMIT {$limit}
        ";
        $ledgerParams = array_merge($paramsOrders, [':fcrop_ledger' => $cropId]);
    } else {
        $ledgerSql = "
            SELECT 
                o.`id` AS `order_id`,
                o.`order_code`,
                o.`created_at`,
                o.`target_delivery_date`,
                o.`order_status`,
                o.`payment_method`,
                o.`payment_status`,
                o.`subtotal`,
                o.`delivery_fee`,
                o.`total_amount`,
                c.`full_name` AS `customer_name`,
                c.`phone_number` AS `customer_phone`,
                c.`region`,
                c.`landmark`
            FROM `orders` o
            JOIN `customers` c ON o.`customer_id` = c.`id`
            WHERE {$whereSqlOrders}
            ORDER BY o.`target_delivery_date` DESC, o.`id` DESC
            LIMIT {$limit}
        ";
        $ledgerParams = $paramsOrders;
    }
    $lStmt = $pdo->prepare($ledgerSql);
    $lStmt->execute($ledgerParams);
    $ledgerRows = $lStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch items summary for ledger
    $orderIds = array_column($ledgerRows, 'order_id');
    $itemsByOrder = [];
    if (!empty($orderIds)) {
        $inClause = implode(',', array_fill(0, count($orderIds), '?'));
        $cropCondition = ($cropId > 0) ? " AND oi.`product_id` = " . (int) $cropId : "";
        $itemQ = $pdo->prepare("
            SELECT 
                oi.`order_id`,
                oi.`half_kg_quantity`,
                p.`name`,
                p.`telugu_name`
            FROM `order_items` oi
            JOIN `products` p ON oi.`product_id` = p.`id`
            WHERE oi.`order_id` IN ($inClause){$cropCondition}
            ORDER BY p.`name` ASC
        ");
        $itemQ->execute($orderIds);
        $allOrderItems = $itemQ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($allOrderItems as $it) {
            $itemsByOrder[$it['order_id']][] = $it;
        }
    }

    $tabLedger = [];
    foreach ($ledgerRows as $lr) {
        $oId = (int) $lr['order_id'];
        $rawItems = $itemsByOrder[$oId] ?? [];
        $summaryParts = [];
        foreach ($rawItems as $it) {
            $summaryParts[] = $it['name'] . ' (' . ($it['half_kg_quantity'] * 0.5) . 'kg)';
        }
        $itemsText = !empty($summaryParts) ? implode(', ', $summaryParts) : 'None';

        $tabLedger[] = [
            'order_id'       => $oId,
            'order_code'     => $lr['order_code'],
            'created_at'     => $lr['created_at'],
            'created_fmt'    => date('d M Y, h:i A', strtotime($lr['created_at'])),
            'target_run'     => $lr['target_delivery_date'],
            'target_run_fmt' => date('d M Y', strtotime($lr['target_delivery_date'])),
            'order_status'   => $lr['order_status'],
            'customer_name'  => $lr['customer_name'],
            'customer_phone' => $lr['customer_phone'],
            'region'         => $lr['region'],
            'landmark'       => $lr['landmark'] ?? '',
            'items_summary'  => $itemsText,
            'payment_method' => $lr['payment_method'],
            'payment_status' => $lr['payment_status'],
            'subtotal'       => (float) $lr['subtotal'],
            'subtotal_fmt'   => '₹' . number_format((float) $lr['subtotal'], 2),
            'delivery_fee'   => (float) $lr['delivery_fee'],
            'delivery_fee_fmt'=> '₹' . number_format((float) $lr['delivery_fee'], 2),
            'total_amount'   => (float) $lr['total_amount'],
            'total_amount_fmt'=> '₹' . number_format((float) $lr['total_amount'], 2),
        ];
    }

    // ------------------------------------------------------------------------
    // E. Export Handling (CSV / Excel with UTF-8 BOM)
    // ------------------------------------------------------------------------
    if ($isExport) {
        $filename = sprintf('Prakruthi_Siri_Financial_Report_%s_to_%s.csv', substr($fromTime, 0, 10), substr($toTime, 0, 10));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output UTF-8 BOM for Excel Telugu rendering
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        // Title & Filter Summary
        fputcsv($out, ['PRAKRUTHI SIRI - FINANCIAL & REVENUE OPERATIONS REPORT']);
        fputcsv($out, ['Reporting Period', substr($fromTime, 0, 10) . ' to ' . substr($toTime, 0, 10)]);
        fputcsv($out, ['Payment Mode Filter', $paymentMode]);
        fputcsv($out, ['Order Status Filter', strtoupper($orderStatus)]);
        fputcsv($out, ['Gross Revenue', '₹' . number_format($grossRevenue, 2)]);
        fputcsv($out, ['Net Vegetable Sales', '₹' . number_format($netVegSales, 2)]);
        fputcsv($out, ['Total Weight Sold (kg)', number_format($totalKgSold, 1) . ' kg']);
        fputcsv($out, ['Total 0.5kg Packets', $totalPackets]);
        fputcsv($out, ['COD Amount Settled', '₹' . number_format($codTotal, 2)]);
        fputcsv($out, ['UPI Amount Settled', '₹' . number_format($upiTotal, 2)]);
        fputcsv($out, ['Average Order Value (AOV)', '₹' . number_format($aov, 2)]);
        fputcsv($out, []); // Blank line

        if ($exportTab === 'tab_crops') {
            // Tab B: Crop-Wise Breakdown
            fputcsv($out, ['TAB B: CROP-WISE REVENUE BREAKDOWN']);
            fputcsv($out, ['Product ID', 'Crop Name (English)', 'Crop Name (Telugu)', 'Category', 'Total Kg Sold', '0.5kg Packets', 'Avg Unit Price (₹)', 'Gross Revenue (₹)', 'Revenue Share (%)']);
            foreach ($tabCrops as $c) {
                fputcsv($out, [
                    $c['product_id'],
                    $c['crop_name'],
                    $c['telugu_name'],
                    $c['category'],
                    $c['kg_sold'],
                    $c['packets_sold'],
                    number_format($c['avg_price'], 2),
                    number_format($c['gross_revenue'], 2),
                    $c['revenue_share'] . '%',
                ]);
            }
        } elseif ($exportTab === 'tab_ledger') {
            // Tab C: Itemized Transaction Log
            fputcsv($out, ['TAB C: ITEMIZED TRANSACTION LOG']);
            fputcsv($out, ['Order Code', 'Created At', 'Target Delivery Run', 'Customer Name', 'Phone Number', 'Region', 'Landmark', 'Items Purchased', 'Payment Method', 'Payment Status', 'Subtotal (₹)', 'Delivery Fee (₹)', 'Total Amount (₹)', 'Order Status']);
            foreach ($tabLedger as $l) {
                fputcsv($out, [
                    $l['order_code'],
                    $l['created_at'],
                    $l['target_run'],
                    $l['customer_name'],
                    $l['customer_phone'],
                    $l['region'],
                    $l['landmark'],
                    $l['items_summary'],
                    $l['payment_method'],
                    $l['payment_status'],
                    number_format($l['subtotal'], 2),
                    number_format($l['delivery_fee'], 2),
                    number_format($l['total_amount'], 2),
                    strtoupper($l['order_status']),
                ]);
            }
        } else {
            // Tab A: Periodic Aggregations (Default)
            fputcsv($out, ['TAB A: PERIODIC AGGREGATIONS']);
            fputcsv($out, ['Date / Period', 'Total Orders', 'Weight Sold (kg)', '0.5kg Packets', 'Vegetable Subtotal (₹)', 'Delivery Fees (₹)', 'Total Revenue (₹)', 'COD Amount (₹)', 'UPI Amount (₹)']);
            foreach ($tabPeriodic as $p) {
                fputcsv($out, [
                    $p['period'],
                    $p['orders_count'],
                    $p['weight_kg'],
                    $p['packets'],
                    number_format($p['subtotal'], 2),
                    number_format($p['delivery_fee'], 2),
                    number_format($p['revenue'], 2),
                    number_format($p['cod_amount'], 2),
                    number_format($p['upi_amount'], 2),
                ]);
            }
        }

        fclose($out);
        exit;
    }

    // Return JSON response for AJAX dashboard updates
    echo json_encode([
        'success'      => true,
        'kpi'          => $kpiSummary,
        'tab_periodic' => $tabPeriodic,
        'tab_crops'    => $tabCrops,
        'tab_ledger'   => $tabLedger,
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    if ($isExport) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Error generating financial export: " . $e->getMessage();
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Financial reporting error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
