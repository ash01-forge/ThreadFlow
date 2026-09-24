<?php
/**
 * ThreadFlow - Buyer Commitment Risk Analyzer helpers.
 *
 * This module is read-only. These helpers only assemble analytical SQL and
 * calculate scores in memory; no helper performs a database write.
 */

/**
 * Returns the shared analytical query. The optional WHERE clause belongs to
 * the caller so detail pages can bind an order ID safely.
 */
function buyer_risk_order_sql(string $whereClause = ''): string
{
    $sql = <<<'SQL'
SELECT
    bo.order_id,
    bo.order_number,
    bo.style_number,
    bo.total_quantity,
    bo.order_date,
    bo.delivery_date,
    bo.status AS order_status,
    b.buyer_id,
    b.buyer_name,
    DATEDIFF(bo.delivery_date, CURRENT_DATE()) AS days_remaining,

    /* LEFT JOIN derived production totals so orders without batches remain visible. */
    COALESCE(prod.total_batch_count, 0) AS total_batch_count,
    COALESCE(prod.allocated_quantity, 0) AS allocated_quantity,
    COALESCE(prod.completed_production_quantity, 0) AS completed_production_quantity,
    COALESCE(prod.in_progress_quantity, 0) AS in_progress_quantity,
    COALESCE(prod.on_hold_batch_count, 0) AS on_hold_batch_count,
    prod.latest_target_end_date,

    /* LEFT JOIN derived QC totals across every inspection for the order. */
    COALESCE(qc.inspection_count, 0) AS inspection_count,
    COALESCE(qc.inspected_quantity, 0) AS inspected_quantity,
    COALESCE(qc.passed_quantity, 0) AS passed_quantity,
    COALESCE(qc.defective_quantity, 0) AS defective_quantity,
    COALESCE(qc.failed_inspection_count, 0) AS failed_inspection_count,
    COALESCE(qc.defect_percentage, 0) AS defect_percentage,

    /* LEFT JOIN derived packing totals; cancelled shipments are excluded. */
    COALESCE(ship.packed_quantity, 0) AS packed_quantity,
    COALESCE(ship.shipped_delivered_quantity, 0) AS shipped_delivered_quantity,
    COALESCE(ship.active_shipment_count, 0) AS active_shipment_count,

    /* Correlated subqueries keep each order's buyer-change counts local. */
    (
        SELECT COUNT(*)
        FROM buyer_change_requests bcr_pending
        WHERE bcr_pending.order_id = bo.order_id
          AND bcr_pending.status = 'Submitted'
    ) AS pending_change_count,
    (
        SELECT COUNT(*)
        FROM buyer_change_requests bcr_priority
        WHERE bcr_priority.order_id = bo.order_id
          AND bcr_priority.status = 'Submitted'
          AND bcr_priority.impact_level IN ('High', 'Critical')
    ) AS pending_high_critical_change_count
FROM buyer_orders bo
/* Required INNER JOIN: every analyzed order must identify its buyer. */
INNER JOIN buyers b ON b.buyer_id = bo.buyer_id
/* Derived production subquery grouped at order level. */
LEFT JOIN (
    SELECT
        pb.order_id,
        COUNT(*) AS total_batch_count,
        COALESCE(SUM(pb.planned_quantity), 0) AS allocated_quantity,
        COALESCE(SUM(CASE WHEN pb.status = 'Completed' THEN pb.planned_quantity ELSE 0 END), 0) AS completed_production_quantity,
        COALESCE(SUM(CASE WHEN pb.status = 'In Progress' THEN pb.planned_quantity ELSE 0 END), 0) AS in_progress_quantity,
        COALESCE(SUM(CASE WHEN pb.status = 'On Hold' THEN 1 ELSE 0 END), 0) AS on_hold_batch_count,
        MAX(pb.target_end_date) AS latest_target_end_date
    FROM production_batches pb
    GROUP BY pb.order_id
) prod ON prod.order_id = bo.order_id
/* Derived quality subquery joins batches to inspections, then groups by order. */
LEFT JOIN (
    SELECT
        pb.order_id,
        COUNT(qi.inspection_id) AS inspection_count,
        COALESCE(SUM(qi.inspected_quantity), 0) AS inspected_quantity,
        COALESCE(SUM(qi.passed_quantity), 0) AS passed_quantity,
        COALESCE(SUM(qi.defective_quantity), 0) AS defective_quantity,
        COALESCE(SUM(CASE WHEN qi.overall_result = 'Failed' THEN 1 ELSE 0 END), 0) AS failed_inspection_count,
        COALESCE((SUM(qi.defective_quantity) / NULLIF(SUM(qi.inspected_quantity), 0)) * 100, 0) AS defect_percentage
    FROM production_batches pb
    INNER JOIN quality_inspections qi ON qi.batch_id = pb.batch_id
    GROUP BY pb.order_id
) qc ON qc.order_id = bo.order_id
/* Derived shipment subquery joins batches to packing records, then groups by order. */
LEFT JOIN (
    SELECT
        pb.order_id,
        COALESCE(SUM(CASE WHEN ps.status <> 'Cancelled' THEN ps.packed_quantity ELSE 0 END), 0) AS packed_quantity,
        COALESCE(SUM(CASE WHEN ps.status IN ('Shipped', 'Delivered') THEN ps.packed_quantity ELSE 0 END), 0) AS shipped_delivered_quantity,
        COALESCE(SUM(CASE WHEN ps.status IN ('Packed', 'In Transit', 'Shipped') THEN 1 ELSE 0 END), 0) AS active_shipment_count
    FROM production_batches pb
    INNER JOIN packing_shipments ps ON ps.batch_id = pb.batch_id
    GROUP BY pb.order_id
) ship ON ship.order_id = bo.order_id
SQL;

    if ($whereClause !== '') {
        $sql .= "\nWHERE " . $whereClause;
    }

    return $sql;
}

function buyer_risk_number($value): float
{
    return is_numeric($value) ? (float) $value : 0.0;
}

function buyer_risk_percent(float $part, float $whole): float
{
    return $whole > 0 ? min(100.0, max(0.0, ($part / $whole) * 100)) : 0.0;
}

function buyer_risk_level(int $score): string
{
    if ($score >= 70) {
        return 'Critical';
    }
    if ($score >= 40) {
        return 'High';
    }
    if ($score >= 20) {
        return 'Medium';
    }
    return 'Low';
}

function buyer_risk_badge_class(string $level): string
{
    return $level === 'Critical' ? 'badge-danger' : ($level === 'High' ? 'badge-warning' : ($level === 'Medium' ? 'badge-info' : 'badge-success'));
}

/**
 * Calculates an explainable score. Simulation overrides are temporary only.
 */
function buyer_calculate_risk(array $order, array $simulation = []): array
{
    $totalQuantity = max(0.0, buyer_risk_number($order['total_quantity'] ?? 0));
    $completedQuantity = max(0.0, buyer_risk_number($order['completed_production_quantity'] ?? 0) + buyer_risk_number($simulation['extra_completed_quantity'] ?? 0));
    $packedQuantity = max(0.0, buyer_risk_number($order['packed_quantity'] ?? 0) + buyer_risk_number($simulation['extra_packed_quantity'] ?? 0));
    $daysRemaining = $order['days_remaining'] === null || $order['days_remaining'] === '' ? null : (int) $order['days_remaining'];
    if (!empty($simulation['delivery_extension_days']) && $daysRemaining !== null) {
        $daysRemaining += (int) $simulation['delivery_extension_days'];
    }

    $onHoldCount = (int) buyer_risk_number($order['on_hold_batch_count'] ?? 0);
    $failedInspectionCount = (int) buyer_risk_number($order['failed_inspection_count'] ?? 0);
    $pendingPriorityCount = (int) buyer_risk_number($order['pending_high_critical_change_count'] ?? 0);
    $pendingChangeCount = (int) buyer_risk_number($order['pending_change_count'] ?? 0);
    if (!empty($simulation['resolve_on_hold'])) $onHoldCount = 0;
    if (!empty($simulation['resolve_qc_failures'])) $failedInspectionCount = 0;
    if (!empty($simulation['resolve_priority_changes'])) $pendingPriorityCount = 0;
    if (!empty($simulation['resolve_priority_changes'])) $pendingChangeCount = max(0, $pendingChangeCount - (int) buyer_risk_number($order['pending_high_critical_change_count'] ?? 0));

    $productionPercent = buyer_risk_percent($completedQuantity, $totalQuantity);
    $packedPercent = buyer_risk_percent($packedQuantity, $totalQuantity);
    $defectPercent = buyer_risk_number($order['defect_percentage'] ?? 0);
    $isCompleted = ($order['order_status'] ?? '') === 'Completed' || ($totalQuantity > 0 && $packedQuantity >= $totalQuantity);
    $score = 0;
    $reasons = [];
    $actions = [];
    $add = static function (string $reason, int $points, string $action) use (&$score, &$reasons, &$actions): void {
        $score += $points;
        $reasons[] = ['reason' => $reason, 'points' => $points];
        $actions[] = $action;
    };

    if (!$isCompleted && $daysRemaining !== null && $daysRemaining < 0) $add('Delivery is overdue while the order is incomplete.', 40, 'Escalate the order owner and confirm a recovery date with the buyer.');
    elseif (!$isCompleted && $daysRemaining !== null && $daysRemaining <= 7) $add('Delivery is due within 7 days.', 25, 'Confirm daily production and outbound capacity immediately.');
    elseif (!$isCompleted && $daysRemaining !== null && $daysRemaining <= 14) $add('Delivery is due within 14 days.', 15, 'Review the remaining production and packing schedule.');

    if (in_array($order['order_status'] ?? '', ['Confirmed', 'In Production'], true) && buyer_risk_number($order['allocated_quantity'] ?? 0) <= 0) $add('The confirmed or in-production order has no production allocation.', 20, 'Assign production capacity and a target completion date.');
    if ($totalQuantity > 0 && $productionPercent < 50) $add('Production completion is below 50%.', 20, 'Prioritize unfinished batches and verify line capacity.');
    elseif ($totalQuantity > 0 && $productionPercent < 100) $add('Production completion is below 100%.', 10, 'Track the remaining production quantity to completion.');
    if ($onHoldCount > 0) $add('One or more production batches are On Hold.', 15, 'Resolve the hold reason and record the next production checkpoint.');

    if ($failedInspectionCount > 0) $add('One or more quality inspections failed.', 20, 'Open corrective action and re-inspect affected production before packing.');
    if ($defectPercent > 5) $add('The defect percentage is above 5%.', 15, 'Escalate quality containment and review defect root causes.');
    elseif ($defectPercent > 2) $add('The defect percentage is above 2%.', 8, 'Increase QC monitoring on the remaining quantity.');

    if ($pendingPriorityCount > 0) $add('Pending High/Critical buyer change requests exist.', 15, 'Review priority buyer changes before committing the remaining schedule.');
    elseif ($pendingChangeCount > 0) $add('Pending buyer change requests exist.', 5, 'Review and resolve outstanding buyer requests.');

    if (!$isCompleted && $daysRemaining !== null && $daysRemaining <= 14 && $packedPercent < 100) $add('Delivery is due within 14 days and packed coverage is below 100%.', 10, 'Reserve packing and shipment capacity for the uncovered quantity.');

    return [
        'score' => min(100, max(0, $score)),
        'level' => buyer_risk_level(min(100, max(0, $score))),
        'reasons' => $reasons,
        'actions' => array_values(array_unique($actions)),
        'days_remaining' => $daysRemaining,
        'production_percent' => $productionPercent,
        'packed_percent' => $packedPercent,
        'defect_percent' => $defectPercent,
        'completed_quantity' => $completedQuantity,
        'packed_quantity' => $packedQuantity,
        'on_hold_count' => $onHoldCount,
        'failed_inspection_count' => $failedInspectionCount,
        'pending_priority_count' => $pendingPriorityCount,
        'pending_change_count' => $pendingChangeCount,
    ];
}

function buyer_risk_validate_nonnegative_int($value, int $maximum): int
{
    $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $maximum]]);
    return $validated === false ? 0 : (int) $validated;
}
