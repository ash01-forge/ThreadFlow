<?php
/**
 * ThreadFlow - Operational Dashboard
 * 
 * Module 3: Assigned to Member 3.
 * Feature 4: Operational Dashboard.
 * 
 * Executive cross-functional command center integrating:
 * - Order progress tracking (Member 1 & 3)
 * - Bottleneck & delay monitoring (Member 3 Feature 3)
 * - Quality inspection defect rates (Member 4)
 * - Rework job status (Member 4)
 * - Shipment readiness derivation (Member 4)
 * 
 * Access restricted to Admin, Production Manager, and Management roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager', 'management']);

$pageTitle = 'Operational Dashboard';
$currentNav = 'operational_dashboard';

// Extract and validate the order filter parameter
$orderFilter = trim($_GET['order_id'] ?? 'all');
$selectedOrderId = null;
if ($orderFilter !== 'all') {
    $val = filter_var($orderFilter, FILTER_VALIDATE_INT);
    if ($val && $val > 0) {
        $selectedOrderId = (int) $val;
    } else {
        $orderFilter = 'all';
    }
}

// Fetch all orders for the top filter dropdown
$allOrders = [];
try {
    $ordSql = "SELECT bo.order_id, bo.order_number, bo.style_number, bo.total_quantity, bo.status, b.buyer_name 
               FROM buyer_orders bo 
               INNER JOIN buyers b ON bo.buyer_id = b.buyer_id 
               ORDER BY bo.order_id DESC";
    $ordRes = $conn->query($ordSql);
    if ($ordRes) {
        $allOrders = $ordRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database error fetching buyer orders for dashboard filter: " . $e->getMessage());
}

// If an order is selected, fetch its specific header details
$selectedOrder = null;
if ($selectedOrderId !== null) {
    try {
        $stmtSel = $conn->prepare("SELECT bo.order_id, bo.order_number, bo.style_number, bo.total_quantity, 
                                          bo.order_date, bo.delivery_date, bo.status AS order_status, 
                                          b.buyer_name, b.country 
                                   FROM buyer_orders bo 
                                   INNER JOIN buyers b ON bo.buyer_id = b.buyer_id 
                                   WHERE bo.order_id = ? LIMIT 1");
        if ($stmtSel) {
            $stmtSel->bind_param("i", $selectedOrderId);
            $stmtSel->execute();
            $selRes = $stmtSel->get_result();
            $selectedOrder = $selRes->fetch_assoc();
            $stmtSel->close();
            if (!$selectedOrder) {
                // If ID is not in DB, fall back to all
                $selectedOrderId = null;
                $orderFilter = 'all';
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database error fetching selected order details: " . $e->getMessage());
    }
}

// Count active production stages (needed for expected stage slots calculation)
$activeStagesCount = 0;
try {
    $stgRes = $conn->query("SELECT COUNT(*) AS active_count FROM production_stages WHERE is_active = 1");
    if ($stgRes) {
        $row = $stgRes->fetch_assoc();
        $activeStagesCount = (int) ($row['active_count'] ?? 0);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database error counting active production stages: " . $e->getMessage());
}

// =============================================================================
// Operational Summary Metrics Calculation
// =============================================================================

$viewMissing = false;

// 1. Order Progress Metric
// Expected stage slots = number of production batches * active production stages
// Completed stage slots = distinct batch/stage progress records with status Completed
// Progress % = completed stage slots / expected stage slots * 100
$batchCount = 0;
$completedStageSlots = 0;
try {
    if ($selectedOrderId !== null) {
        $bStmt = $conn->prepare("SELECT COUNT(*) AS b_count FROM production_batches WHERE order_id = ?");
        $bStmt->bind_param("i", $selectedOrderId);
        $bStmt->execute();
        $bRes = $bStmt->get_result()->fetch_assoc();
        $batchCount = (int) ($bRes['b_count'] ?? 0);
        $bStmt->close();

        $cStmt = $conn->prepare("SELECT COUNT(DISTINCT sp.batch_id, sp.stage_id) AS c_slots 
                                FROM stage_progress sp 
                                INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id
                                INNER JOIN production_batches pb ON sp.batch_id = pb.batch_id 
                                WHERE sp.status = 'Completed' AND ps.is_active = 1 AND pb.order_id = ?");
        $cStmt->bind_param("i", $selectedOrderId);
        $cStmt->execute();
        $cRes = $cStmt->get_result()->fetch_assoc();
        $completedStageSlots = (int) ($cRes['c_slots'] ?? 0);
        $cStmt->close();
    } else {
        $bRes = $conn->query("SELECT COUNT(*) AS b_count FROM production_batches");
        if ($bRes) {
            $row = $bRes->fetch_assoc();
            $batchCount = (int) ($row['b_count'] ?? 0);
        }
        $cRes = $conn->query("SELECT COUNT(DISTINCT sp.batch_id, sp.stage_id) AS c_slots 
                              FROM stage_progress sp 
                              INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id 
                              WHERE sp.status = 'Completed' AND ps.is_active = 1");
        if ($cRes) {
            $row = $cRes->fetch_assoc();
            $completedStageSlots = (int) ($row['c_slots'] ?? 0);
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database error calculating order progress metric: " . $e->getMessage());
}

$expectedStageSlots = $batchCount * $activeStagesCount;
$orderProgressPct = $expectedStageSlots > 0 ? round(($completedStageSlots / $expectedStageSlots) * 100, 1) : 0.0;

// 2. Delayed Batches Metric
// Count distinct batches from view_bottleneck_monitoring where is_bottleneck = 1
$delayedBatchesCount = 0;
try {
    if ($selectedOrderId !== null) {
        $dStmt = $conn->prepare("SELECT COUNT(DISTINCT batch_id) AS delayed_count FROM view_bottleneck_monitoring WHERE is_bottleneck = 1 AND order_id = ?");
        if ($dStmt) {
            $dStmt->bind_param("i", $selectedOrderId);
            $dStmt->execute();
            $dRes = $dStmt->get_result()->fetch_assoc();
            $delayedBatchesCount = (int) ($dRes['delayed_count'] ?? 0);
            $dStmt->close();
        }
    } else {
        $dRes = $conn->query("SELECT COUNT(DISTINCT batch_id) AS delayed_count FROM view_bottleneck_monitoring WHERE is_bottleneck = 1");
        if ($dRes) {
            $row = $dRes->fetch_assoc();
            $delayedBatchesCount = (int) ($row['delayed_count'] ?? 0);
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception querying view_bottleneck_monitoring: " . $e->getMessage());
    if (strpos($e->getMessage(), 'view_bottleneck_monitoring') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        $viewMissing = true;
    }
}

// 3. Defect Rate Metric
// SUM(quality_inspections.defective_quantity) / NULLIF(SUM(quality_inspections.inspected_quantity), 0) * 100
$totalInspectedQty = 0;
$totalDefectiveQty = 0;
try {
    if ($selectedOrderId !== null) {
        $qiStmt = $conn->prepare("SELECT SUM(qi.inspected_quantity) AS total_inspected, 
                                         SUM(qi.defective_quantity) AS total_defective 
                                  FROM quality_inspections qi 
                                  INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id 
                                  WHERE pb.order_id = ?");
        if ($qiStmt) {
            $qiStmt->bind_param("i", $selectedOrderId);
            $qiStmt->execute();
            $qiRow = $qiStmt->get_result()->fetch_assoc();
            $totalInspectedQty = (int) ($qiRow['total_inspected'] ?? 0);
            $totalDefectiveQty = (int) ($qiRow['total_defective'] ?? 0);
            $qiStmt->close();
        }
    } else {
        $qiRes = $conn->query("SELECT SUM(inspected_quantity) AS total_inspected, SUM(defective_quantity) AS total_defective FROM quality_inspections");
        if ($qiRes) {
            $qiRow = $qiRes->fetch_assoc();
            $totalInspectedQty = (int) ($qiRow['total_inspected'] ?? 0);
            $totalDefectiveQty = (int) ($qiRow['total_defective'] ?? 0);
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database error calculating quality defect rate: " . $e->getMessage());
}

$defectRatePct = $totalInspectedQty > 0 ? round(($totalDefectiveQty / $totalInspectedQty) * 100, 2) : 0.0;

// 4. Rework Status Metric
// Conditional aggregation for Pending, In Progress, Completed, Rejected jobs + quantities
$reworkStats = [
    'total_jobs'       => 0,
    'pending_jobs'     => 0,
    'in_progress_jobs' => 0,
    'completed_jobs'   => 0,
    'rejected_jobs'    => 0,
    'total_allocated'  => 0,
    'total_completed'  => 0,
    'total_rejected'   => 0
];
try {
    if ($selectedOrderId !== null) {
        $rwkStmt = $conn->prepare("SELECT 
                                    COUNT(*) AS total_jobs,
                                    SUM(CASE WHEN rj.status = 'Pending' THEN 1 ELSE 0 END) AS pending_jobs,
                                    SUM(CASE WHEN rj.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_jobs,
                                    SUM(CASE WHEN rj.status = 'Completed' THEN 1 ELSE 0 END) AS completed_jobs,
                                    SUM(CASE WHEN rj.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_jobs,
                                    SUM(rj.rework_quantity) AS total_allocated,
                                    SUM(rj.completed_quantity) AS total_completed,
                                    SUM(rj.rejected_quantity) AS total_rejected
                                   FROM rework_jobs rj
                                   INNER JOIN inspection_defects id ON rj.inspection_defect_id = id.inspection_defect_id
                                   INNER JOIN quality_inspections qi ON id.inspection_id = qi.inspection_id
                                   INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id
                                   WHERE pb.order_id = ?");
        if ($rwkStmt) {
            $rwkStmt->bind_param("i", $selectedOrderId);
            $rwkStmt->execute();
            $rwkRow = $rwkStmt->get_result()->fetch_assoc();
            if ($rwkRow) {
                $reworkStats = [
                    'total_jobs'       => (int) ($rwkRow['total_jobs'] ?? 0),
                    'pending_jobs'     => (int) ($rwkRow['pending_jobs'] ?? 0),
                    'in_progress_jobs' => (int) ($rwkRow['in_progress_jobs'] ?? 0),
                    'completed_jobs'   => (int) ($rwkRow['completed_jobs'] ?? 0),
                    'rejected_jobs'    => (int) ($rwkRow['rejected_jobs'] ?? 0),
                    'total_allocated'  => (int) ($rwkRow['total_allocated'] ?? 0),
                    'total_completed'  => (int) ($rwkRow['total_completed'] ?? 0),
                    'total_rejected'   => (int) ($rwkRow['total_rejected'] ?? 0),
                ];
            }
            $rwkStmt->close();
        }
    } else {
        $rwkSql = "SELECT 
                    COUNT(*) AS total_jobs,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_jobs,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_jobs,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_jobs,
                    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_jobs,
                    SUM(rework_quantity) AS total_allocated,
                    SUM(completed_quantity) AS total_completed,
                    SUM(rejected_quantity) AS total_rejected
                   FROM rework_jobs";
        $rwkRes = $conn->query($rwkSql);
        if ($rwkRes) {
            $rwkRow = $rwkRes->fetch_assoc();
            if ($rwkRow) {
                $reworkStats = [
                    'total_jobs'       => (int) ($rwkRow['total_jobs'] ?? 0),
                    'pending_jobs'     => (int) ($rwkRow['pending_jobs'] ?? 0),
                    'in_progress_jobs' => (int) ($rwkRow['in_progress_jobs'] ?? 0),
                    'completed_jobs'   => (int) ($rwkRow['completed_jobs'] ?? 0),
                    'rejected_jobs'    => (int) ($rwkRow['rejected_jobs'] ?? 0),
                    'total_allocated'  => (int) ($rwkRow['total_allocated'] ?? 0),
                    'total_completed'  => (int) ($rwkRow['total_completed'] ?? 0),
                    'total_rejected'   => (int) ($rwkRow['total_rejected'] ?? 0),
                ];
            }
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database error calculating rework status metrics: " . $e->getMessage());
}

// 5. Shipment Readiness Derivation Helper
// Rules:
// - Dispatched: dispatched_count > 0 (shipment status In Transit, Shipped, or Delivered)
// - Ready to Ship: ready_batch_count > 0 (batch has Passed Final Pre-Shipment, no open rework, Packed status)
// - Blocked: blocked_batch_count > 0 (batch has Failed/Pending Review Final Pre-Shipment OR open rework)
// - Awaiting QC/Packing: otherwise
function derive_shipment_readiness(array $signals): array {
    $dispatched = (int) ($signals['dispatched_count'] ?? 0);
    $ready = (int) ($signals['ready_batch_count'] ?? 0);
    $blocked = (int) ($signals['blocked_batch_count'] ?? 0);

    if ($dispatched > 0) {
        return [
            'status' => 'Dispatched',
            'badge'  => 'badge-primary',
            'reason' => 'Outbound shipment released and currently in transit, shipped, or delivered.'
        ];
    }
    if ($ready > 0) {
        return [
            'status' => 'Ready to Ship',
            'badge'  => 'badge-success',
            'reason' => 'Passed Final Pre-Shipment inspection with no open rework and packed export cartons.'
        ];
    }
    if ($blocked > 0) {
        return [
            'status' => 'Blocked',
            'badge'  => 'badge-danger',
            'reason' => 'Failed/Pending Review Final Pre-Shipment inspection or open rework in progress.'
        ];
    }
    return [
        'status' => 'Awaiting QC/Packing',
        'badge'  => 'badge-warning',
        'reason' => 'Production ongoing; awaiting final pre-shipment quality audit and packaging clearance.'
    ];
}

// Fetch order-level workflow rows (complete workflow comparison)
$orderReportRows = [];
$reportQuery = "SELECT 
                    bo.order_id,
                    bo.order_number,
                    bo.style_number,
                    bo.total_quantity,
                    bo.order_date,
                    bo.delivery_date,
                    bo.status AS order_status,
                    b.buyer_name,
                    COUNT(DISTINCT pb.batch_id) AS batch_count,
                    COALESCE(SUM(pb.planned_quantity), 0) AS total_planned_qty,
                    -- Completed stage slots for this order (active stages only)
                    (
                        SELECT COUNT(DISTINCT sp.batch_id, sp.stage_id)
                        FROM stage_progress sp
                        INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id
                        INNER JOIN production_batches pb2 ON sp.batch_id = pb2.batch_id
                        WHERE pb2.order_id = bo.order_id AND sp.status = 'Completed' AND ps.is_active = 1
                    ) AS completed_slots,
                    -- Delayed batches count from view_bottleneck_monitoring
                    (
                        SELECT COUNT(DISTINCT vbm.batch_id)
                        FROM view_bottleneck_monitoring vbm
                        WHERE vbm.order_id = bo.order_id AND vbm.is_bottleneck = 1
                    ) AS delayed_batch_count,
                    -- Quality inspections volume
                    (
                        SELECT COALESCE(SUM(qi.inspected_quantity), 0)
                        FROM quality_inspections qi
                        INNER JOIN production_batches pb3 ON qi.batch_id = pb3.batch_id
                        WHERE pb3.order_id = bo.order_id
                    ) AS inspected_qty,
                    (
                        SELECT COALESCE(SUM(qi.defective_quantity), 0)
                        FROM quality_inspections qi
                        INNER JOIN production_batches pb3 ON qi.batch_id = pb3.batch_id
                        WHERE pb3.order_id = bo.order_id
                    ) AS defective_qty,
                    -- Completed rework count for this order (table usage)
                    (
                        SELECT COUNT(*)
                        FROM rework_jobs rj
                        INNER JOIN inspection_defects id ON rj.inspection_defect_id = id.inspection_defect_id
                        INNER JOIN quality_inspections qi ON id.inspection_id = qi.inspection_id
                        INNER JOIN production_batches pb5 ON qi.batch_id = pb5.batch_id
                        WHERE pb5.order_id = bo.order_id
                          AND rj.status = 'Completed'
                    ) AS completed_rework_count,
                    -- Ready batch count: batch has Passed Final Pre-Shipment, NO open rework, and Packed shipment
                    (
                        SELECT COUNT(DISTINCT pb_ready.batch_id)
                        FROM production_batches pb_ready
                        WHERE pb_ready.order_id = bo.order_id
                          AND EXISTS (
                              SELECT 1 FROM quality_inspections qi_r
                              WHERE qi_r.batch_id = pb_ready.batch_id
                                AND qi_r.inspection_stage = 'Final Pre-Shipment'
                                AND qi_r.overall_result = 'Passed'
                          )
                          AND NOT EXISTS (
                              SELECT 1 FROM rework_jobs rj_r
                              INNER JOIN inspection_defects id_r ON rj_r.inspection_defect_id = id_r.inspection_defect_id
                              INNER JOIN quality_inspections qi_rwk ON id_r.inspection_id = qi_rwk.inspection_id
                              WHERE qi_rwk.batch_id = pb_ready.batch_id
                                AND rj_r.status IN ('Pending', 'In Progress')
                          )
                          AND EXISTS (
                              SELECT 1 FROM packing_shipments ps_r
                              WHERE ps_r.batch_id = pb_ready.batch_id
                                AND ps_r.status = 'Packed'
                          )
                    ) AS ready_batch_count,
                    -- Blocked batch count: batch has Failed/Pending Review Final Pre-Shipment OR open rework
                    (
                        SELECT COUNT(DISTINCT pb_blk.batch_id)
                        FROM production_batches pb_blk
                        WHERE pb_blk.order_id = bo.order_id
                          AND (
                              EXISTS (
                                  SELECT 1 FROM quality_inspections qi_b
                                  WHERE qi_b.batch_id = pb_blk.batch_id
                                    AND qi_b.inspection_stage = 'Final Pre-Shipment'
                                    AND qi_b.overall_result IN ('Failed', 'Pending Review')
                              )
                              OR EXISTS (
                                  SELECT 1 FROM rework_jobs rj_b
                                  INNER JOIN inspection_defects id_b ON rj_b.inspection_defect_id = id_b.inspection_defect_id
                                  INNER JOIN quality_inspections qi_rwk2 ON id_b.inspection_id = qi_rwk2.inspection_id
                                  WHERE qi_rwk2.batch_id = pb_blk.batch_id
                                    AND rj_b.status IN ('Pending', 'In Progress')
                              )
                          )
                    ) AS blocked_batch_count,
                    -- Dispatched batches count (distinct shipped batch IDs)
                    (
                        SELECT COUNT(DISTINCT ps_d.batch_id)
                        FROM packing_shipments ps_d
                        INNER JOIN production_batches pb_d ON ps_d.batch_id = pb_d.batch_id
                        WHERE pb_d.order_id = bo.order_id
                          AND ps_d.status IN ('In Transit', 'Shipped', 'Delivered')
                    ) AS dispatched_count
                FROM buyer_orders bo
                INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
                LEFT JOIN production_batches pb ON bo.order_id = pb.order_id";

if ($selectedOrderId !== null) {
    $reportQuery .= " WHERE bo.order_id = ? GROUP BY bo.order_id ORDER BY bo.order_id DESC";
} else {
    $reportQuery .= " GROUP BY bo.order_id ORDER BY bo.order_id DESC";
}

try {
    if ($selectedOrderId !== null) {
        $rStmt = $conn->prepare($reportQuery);
        if ($rStmt) {
            $rStmt->bind_param("i", $selectedOrderId);
            $rStmt->execute();
            $orderReportRows = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $rStmt->close();
        }
    } else {
        $rRes = $conn->query($reportQuery);
        if ($rRes) {
            $orderReportRows = $rRes->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database error fetching operational dashboard order report: " . $e->getMessage());
    if (strpos($e->getMessage(), 'view_bottleneck_monitoring') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        $viewMissing = true;
    }
}

// Calculate scope-level shipment readiness summary
$overallReadinessCounts = [
    'Dispatched'          => 0,
    'Ready to Ship'       => 0,
    'Blocked'             => 0,
    'Awaiting QC/Packing' => 0
];
foreach ($orderReportRows as $row) {
    $rInfo = derive_shipment_readiness($row);
    $st = $rInfo['status'];
    if (isset($overallReadinessCounts[$st])) {
        $overallReadinessCounts[$st]++;
    }
}

// If single order is selected, fetch batch-level breakdown for that order
$batchBreakdownRows = [];
if ($selectedOrderId !== null) {
    try {
        $bbSql = "SELECT 
                    pb.batch_id,
                    pb.batch_number,
                    pb.planned_quantity,
                    pb.status AS batch_status,
                    pb.start_date,
                    pb.target_end_date,
                    sl.line_code,
                    sl.line_name,
                    -- Stage progress count (active stages only)
                    (
                        SELECT COUNT(DISTINCT sp.batch_id, sp.stage_id)
                        FROM stage_progress sp
                        INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id
                        WHERE sp.batch_id = pb.batch_id AND sp.status = 'Completed' AND ps.is_active = 1
                    ) AS completed_stages,
                    -- Bottleneck status
                    (
                        SELECT MAX(vbm.is_bottleneck)
                        FROM view_bottleneck_monitoring vbm
                        WHERE vbm.batch_id = pb.batch_id
                    ) AS is_bottleneck,
                    (
                        SELECT vbm.classification
                        FROM view_bottleneck_monitoring vbm
                        WHERE vbm.batch_id = pb.batch_id
                        ORDER BY vbm.is_bottleneck DESC, vbm.delay_days DESC
                        LIMIT 1
                    ) AS bottleneck_classification,
                    -- Inspection count & result
                    (
                        SELECT qi.overall_result
                        FROM quality_inspections qi
                        WHERE qi.batch_id = pb.batch_id
                        ORDER BY qi.inspection_id DESC
                        LIMIT 1
                    ) AS latest_qc_result,
                    -- Packing status
                    (
                        SELECT ps.status
                        FROM packing_shipments ps
                        WHERE ps.batch_id = pb.batch_id
                        ORDER BY ps.packing_id DESC
                        LIMIT 1
                    ) AS latest_packing_status
                  FROM production_batches pb
                  LEFT JOIN sewing_lines sl ON pb.line_id = sl.line_id
                  WHERE pb.order_id = ?
                  ORDER BY pb.batch_id ASC";
        $bbStmt = $conn->prepare($bbSql);
        if ($bbStmt) {
            $bbStmt->bind_param("i", $selectedOrderId);
            $bbStmt->execute();
            $batchBreakdownRows = $bbStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $bbStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database error fetching batch breakdown for dashboard: " . $e->getMessage());
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Operational Dashboard</h1>
        <div class="page-subtitle">
            Executive Production &amp; Quality Command Center &bull; Access: Admin, Production Manager, Management
        </div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/bottlenecks/index.php')) ?>" class="btn btn-secondary">Bottleneck Monitoring</a>
        <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-secondary">Production Batches</a>
    </div>
</div>

<?php if ($viewMissing): ?>
    <div class="alert alert-warning mb-4">
        <div class="alert-content">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div class="alert-message">
                <strong>Notice:</strong> <code>view_bottleneck_monitoring</code> is not initialized in the database yet. Delay metrics may be incomplete.
                Please ensure <code>sql/bottleneck_monitoring.sql</code> has been imported.
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Order Filter Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/operational_dashboard/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="order_id" class="form-label font-semibold">Select Buyer Order Focus</label>
                <select id="order_id" name="order_id" class="form-control" onchange="this.form.submit()">
                    <option value="all" <?= $orderFilter === 'all' ? 'selected' : '' ?>>
                        All Buyer Orders (Cross-Plant Enterprise Scope)
                    </option>
                    <?php foreach ($allOrders as $ord): ?>
                        <option value="<?= (int) $ord['order_id'] ?>" <?= ((string) $ord['order_id'] === (string) $selectedOrderId) ? 'selected' : '' ?>>
                            <?= e($ord['order_number']) ?> &bull; <?= e($ord['buyer_name']) ?> &bull; <?= e($ord['style_number']) ?> (<?= number_format((int) $ord['total_quantity']) ?> pcs)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Scope</button>
                <?php if ($selectedOrderId !== null): ?>
                    <a href="<?= e(url('modules/operational_dashboard/index.php')) ?>" class="btn btn-secondary">Reset to All Orders</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($selectedOrder): ?>
            <div class="mt-3 pt-3" style="border-top: 1px dashed var(--color-border); font-size: 0.875rem;">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <strong>Order Focus:</strong>
                        <span class="badge badge-primary"><?= e($selectedOrder['order_number']) ?></span>
                        <span class="text-muted ml-2">Style: <strong><?= e($selectedOrder['style_number']) ?></strong></span>
                        <span class="text-muted ml-2">&bull; Buyer: <strong><?= e($selectedOrder['buyer_name']) ?></strong> (<?= e($selectedOrder['country']) ?>)</span>
                    </div>
                    <div>
                        <span class="text-muted">Target Delivery: <strong><?= !empty($selectedOrder['delivery_date']) ? e(date('M j, Y', strtotime($selectedOrder['delivery_date']))) : 'Not Set' ?></strong></span>
                        <span class="badge badge-muted ml-2"><?= e($selectedOrder['order_status']) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Operational Summary Cards (5 Metrics) -->
<div class="module-grid mb-4">

    <!-- 1. Order Progress -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">Order Progress</span>
                <span class="badge badge-info"><?= $orderProgressPct ?>%</span>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--color-primary); line-height: 1.2;">
                <?= $orderProgressPct ?>%
            </div>
            <div class="text-xs text-muted mt-1">
                <?= number_format($completedStageSlots) ?> of <?= number_format($expectedStageSlots) ?> stage slots completed
            </div>
            <div class="text-xs text-muted" style="opacity: 0.85;">
                (<?= number_format($batchCount) ?> batches &times; <?= number_format($activeStagesCount) ?> active stages)
            </div>
        </div>
    </div>

    <!-- 2. Delayed Batches -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">Delayed Batches</span>
                <?php if ($delayedBatchesCount > 0): ?>
                    <span class="badge badge-danger"><?= $delayedBatchesCount ?> Delayed</span>
                <?php else: ?>
                    <span class="badge badge-success">On Schedule</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; color: <?= $delayedBatchesCount > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>; line-height: 1.2;">
                <?= number_format($delayedBatchesCount) ?>
            </div>
            <div class="text-xs text-muted mt-1">
                <?= $delayedBatchesCount > 0 ? e($delayedBatchesCount . ' of ' . $batchCount . ' batches past target end date') : 'Zero active or historical batch delays' ?>
            </div>
            <div class="text-xs text-muted" style="opacity: 0.85;">
                Derived via Bottleneck Monitoring view
            </div>
        </div>
    </div>

    <!-- 3. Defect Rate -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">QC Defect Rate</span>
                <?php if ($defectRatePct > 5.0): ?>
                    <span class="badge badge-danger"><?= $defectRatePct ?>%</span>
                <?php elseif ($defectRatePct > 0.0): ?>
                    <span class="badge badge-warning"><?= $defectRatePct ?>%</span>
                <?php else: ?>
                    <span class="badge badge-success"><?= $defectRatePct ?>%</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; color: <?= $defectRatePct > 5.0 ? 'var(--color-danger)' : ($defectRatePct > 0 ? 'var(--color-warning)' : 'var(--color-success)') ?>; line-height: 1.2;">
                <?= $defectRatePct ?>%
            </div>
            <div class="text-xs text-muted mt-1">
                <?= number_format($totalDefectiveQty) ?> defective / <?= number_format($totalInspectedQty) ?> inspected pcs
            </div>
            <div class="text-xs text-muted" style="opacity: 0.85;">
                From Quality Inspection audits
            </div>
        </div>
    </div>

    <!-- 4. Rework Status -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">Rework Status</span>
                <?php $openRework = $reworkStats['pending_jobs'] + $reworkStats['in_progress_jobs']; ?>
                <?php if ($openRework > 0): ?>
                    <span class="badge badge-warning"><?= $openRework ?> Open</span>
                <?php else: ?>
                    <span class="badge badge-muted"><?= $reworkStats['total_jobs'] ?> Jobs</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; color: <?= $openRework > 0 ? 'var(--color-warning)' : 'var(--color-primary)' ?>; line-height: 1.2;">
                <?= number_format($reworkStats['total_jobs']) ?> <span class="text-xs font-semibold text-muted">Jobs</span>
            </div>
            <div class="text-xs text-muted mt-1">
                <span title="Pending">P: <?= $reworkStats['pending_jobs'] ?></span> &bull; 
                <span title="In Progress">IP: <?= $reworkStats['in_progress_jobs'] ?></span> &bull; 
                <span title="Completed">C: <?= $reworkStats['completed_jobs'] ?></span> &bull; 
                <span title="Rejected">R: <?= $reworkStats['rejected_jobs'] ?></span>
            </div>
            <div class="text-xs text-muted" style="opacity: 0.85;">
                <?= number_format($reworkStats['total_completed']) ?> of <?= number_format($reworkStats['total_allocated']) ?> pcs resolved
            </div>
        </div>
    </div>

    <!-- 5. Shipment Readiness -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">Shipment Readiness</span>
                <?php 
                $scopeSignals = [
                    'dispatched_count'    => array_sum(array_column($orderReportRows, 'dispatched_count')),
                    'ready_batch_count'   => array_sum(array_column($orderReportRows, 'ready_batch_count')),
                    'blocked_batch_count' => array_sum(array_column($orderReportRows, 'blocked_batch_count')),
                ];
                $primaryReadiness = derive_shipment_readiness($scopeSignals);
                ?>
                <span class="badge <?= e($primaryReadiness['badge']) ?>"><?= e($primaryReadiness['status']) ?></span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--color-primary); line-height: 1.3; margin-top: 0.25rem;">
                <?= e($primaryReadiness['status']) ?>
            </div>
            <div class="text-xs text-muted mt-1">
                <?= $overallReadinessCounts['Ready to Ship'] ?> Ready &bull; <?= $overallReadinessCounts['Dispatched'] ?> Dispatched &bull; <?= $overallReadinessCounts['Blocked'] ?> Blocked
            </div>
            <div class="text-xs text-muted" style="opacity: 0.85;">
                Based on QC, Rework &amp; Shipment rules
            </div>
        </div>
    </div>

</div>

<!-- Shipment Readiness Explicit Rule Definition Guide -->
<div class="card mb-4" style="background-color: var(--color-surface-subtle); border-left: 4px solid var(--color-primary);">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <h3 style="font-size: 0.95rem; margin-bottom: 0; color: var(--color-primary);">
                Shipment Readiness Evaluation Logic (Standard Operating Procedure)
            </h3>
            <span class="badge badge-muted">Deterministic Priority Rules</span>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; font-size: 0.8125rem;">
            <div>
                <strong style="color: var(--color-primary);"><span class="badge badge-primary">1. Dispatched</span></strong>
                <div class="text-muted mt-1">Non-cancelled shipment record has status <code>In Transit</code>, <code>Shipped</code>, or <code>Delivered</code>.</div>
            </div>
            <div>
                <strong style="color: var(--color-success);"><span class="badge badge-success">2. Ready to Ship</span></strong>
                <div class="text-muted mt-1">Batch has Passed <code>Final Pre-Shipment</code> audit, zero open rework (0 Pending / In Progress), and <code>Packed</code> record.</div>
            </div>
            <div>
                <strong style="color: var(--color-danger);"><span class="badge badge-danger">3. Blocked</span></strong>
                <div class="text-muted mt-1">Batch has <code>Failed</code> or <code>Pending Review</code> Final Pre-Shipment audit, OR open rework jobs remain in progress.</div>
            </div>
            <div>
                <strong style="color: var(--color-warning);"><span class="badge badge-warning">4. Awaiting QC/Packing</span></strong>
                <div class="text-muted mt-1">Default state when prior conditions are not yet satisfied; manufacturing ongoing.</div>
            </div>
        </div>
    </div>
</div>

<!-- Order-Level Workflow Comparison Table -->
<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex align-items-center gap-2">
            <h2>Order-Level Workflow Progress &amp; Comparison</h2>
            <span class="badge badge-muted"><?= count($orderReportRows) ?> Order(s) in Scope</span>
        </div>
        <?php if ($selectedOrderId !== null): ?>
            <a href="<?= e(url('modules/operational_dashboard/index.php')) ?>" class="btn btn-sm btn-secondary">
                View All Orders
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($orderReportRows)): ?>
            <div class="empty-state">
                <p>No buyer orders found for the selected filter criteria.</p>
                <a href="<?= e(url('modules/operational_dashboard/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Filter</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Buyer &amp; Order</th>
                            <th>Target Delivery</th>
                            <th>Batches</th>
                            <th style="min-width: 140px;">Order Progress</th>
                            <th>Bottlenecks</th>
                            <th>QC &amp; Defects</th>
                            <th>Rework Status</th>
                            <th class="col-status">Shipment Readiness</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderReportRows as $row): ?>
                            <?php
                            $rowExpectedSlots = (int) $row['batch_count'] * $activeStagesCount;
                            $rowCompletedSlots = (int) $row['completed_slots'];
                            $rowProgressPct = $rowExpectedSlots > 0 ? round(($rowCompletedSlots / $rowExpectedSlots) * 100, 1) : 0.0;

                            $rowInspected = (int) $row['inspected_qty'];
                            $rowDefective = (int) $row['defective_qty'];
                            $rowDefectRate = $rowInspected > 0 ? round(($rowDefective / $rowInspected) * 100, 1) : 0.0;

                            $rowDelayed = (int) $row['delayed_batch_count'];
                            $readiness = derive_shipment_readiness($row);
                            ?>
                            <tr>
                                <!-- Buyer & Order -->
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/operational_dashboard/index.php?order_id=' . (int)$row['order_id'])) ?>" title="Filter to this order">
                                            <?= e($row['order_number']) ?>
                                        </a>
                                    </strong>
                                    <div class="text-xs text-muted">
                                        <?= e($row['buyer_name']) ?> &bull; <?= e($row['style_number']) ?>
                                    </div>
                                    <div class="text-xs text-muted">
                                        Total: <strong><?= number_format((int)$row['total_quantity']) ?></strong> pcs
                                    </div>
                                </td>

                                <!-- Target Delivery -->
                                <td class="text-nowrap">
                                    <?php if (!empty($row['delivery_date'])): ?>
                                        <div><strong><?= e(date('M j, Y', strtotime($row['delivery_date']))) ?></strong></div>
                                        <span class="badge badge-muted text-xs"><?= e($row['order_status']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">Unspecified</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Batches -->
                                <td>
                                    <strong><?= (int)$row['batch_count'] ?></strong> batch(es)
                                    <div class="text-xs text-muted">
                                        <?= number_format((int)$row['total_planned_qty']) ?> pcs planned
                                    </div>
                                </td>

                                <!-- Order Progress -->
                                <td>
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="font-semibold text-sm"><?= $rowProgressPct ?>%</span>
                                        <span class="text-xs text-muted"><?= $rowCompletedSlots ?>/<?= $rowExpectedSlots ?> slots</span>
                                    </div>
                                    <div style="background-color: var(--color-border); border-radius: var(--radius-full); height: 6px; overflow: hidden;">
                                        <div style="background-color: var(--color-primary); width: <?= min(100.0, $rowProgressPct) ?>%; height: 100%;"></div>
                                    </div>
                                </td>

                                <!-- Bottlenecks -->
                                <td>
                                    <?php if ($rowDelayed > 0): ?>
                                        <span class="badge badge-danger"><?= $rowDelayed ?> Delayed</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">On Schedule</span>
                                    <?php endif; ?>
                                </td>

                                <!-- QC & Defects -->
                                <td>
                                    <?php if ($rowInspected > 0): ?>
                                        <strong><?= $rowDefectRate ?>%</strong>
                                        <div class="text-xs text-muted">
                                            <?= number_format($rowDefective) ?> / <?= number_format($rowInspected) ?> pcs
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">No QC yet</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Rework Status -->
                                <td>
                                    <?php 
                                    $compRw = (int)($row['completed_rework_count'] ?? 0);
                                    ?>
                                    <?php if ($compRw > 0): ?>
                                        <span class="badge badge-success"><?= $compRw ?> Resolved</span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Shipment Readiness -->
                                <td class="col-status text-nowrap">
                                    <span class="badge <?= e($readiness['badge']) ?>" title="<?= e($readiness['reason']) ?>">
                                        <?= e($readiness['status']) ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="col-actions text-nowrap">
                                    <div class="row-actions">
                                        <?php if ((string)$row['order_id'] !== (string)$selectedOrderId): ?>
                                            <a href="<?= e(url('modules/operational_dashboard/index.php?order_id=' . (int)$row['order_id'])) ?>" class="btn btn-sm btn-primary">
                                                Select Order
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= e(url('modules/operational_dashboard/index.php')) ?>" class="btn btn-sm btn-secondary">
                                                Reset Scope
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Batch-Level Breakdown for Selected Order -->
<?php if ($selectedOrderId !== null && !empty($batchBreakdownRows)): ?>
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <h2>Batch Execution Breakdown &bull; Order <?= e($selectedOrder['order_number']) ?></h2>
                <span class="badge badge-muted"><?= count($batchBreakdownRows) ?> Batch(es)</span>
            </div>
            <a href="<?= e(url('modules/production_batches/create.php')) ?>" class="btn btn-sm btn-secondary">Add Batch</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Batch Number</th>
                            <th>Sewing Line</th>
                            <th>Planned Qty</th>
                            <th>Batch Status</th>
                            <th>Production Timeline</th>
                            <th>Stage Slots Completed</th>
                            <th>Bottleneck Status</th>
                            <th>Latest QC</th>
                            <th>Packing Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batchBreakdownRows as $bb): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$bb['batch_id'])) ?>">
                                            <?= e($bb['batch_number']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <?php if (!empty($bb['line_name'])): ?>
                                        <?= e($bb['line_code'] ?: $bb['line_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format((int)$bb['planned_quantity']) ?> pcs</td>
                                <td>
                                    <?php
                                    $bstClass = 'badge-muted';
                                    if ($bb['batch_status'] === 'In Progress') $bstClass = 'badge-primary';
                                    elseif ($bb['batch_status'] === 'On Hold') $bstClass = 'badge-warning';
                                    elseif ($bb['batch_status'] === 'Completed') $bstClass = 'badge-success';
                                    elseif ($bb['batch_status'] === 'Planned') $bstClass = 'badge-info';
                                    ?>
                                    <span class="badge <?= e($bstClass) ?>"><?= e($bb['batch_status']) ?></span>
                                </td>
                                <td class="text-nowrap text-sm">
                                    <?= e(date('M j, Y', strtotime($bb['start_date']))) ?> &rarr; 
                                    <strong><?= e(date('M j, Y', strtotime($bb['target_end_date']))) ?></strong>
                                </td>
                                <td>
                                    <strong><?= (int)$bb['completed_stages'] ?></strong> / <?= $activeStagesCount ?> stages
                                </td>
                                <td>
                                    <?php if ((int)($bb['is_bottleneck'] ?? 0) === 1): ?>
                                        <span class="badge badge-danger"><?= e($bb['bottleneck_classification'] ?: 'Delayed') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success">On Schedule</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($bb['latest_qc_result'])): ?>
                                        <?php 
                                        $qcClass = 'badge-muted';
                                        if ($bb['latest_qc_result'] === 'Passed') $qcClass = 'badge-success';
                                        elseif ($bb['latest_qc_result'] === 'Failed') $qcClass = 'badge-danger';
                                        elseif ($bb['latest_qc_result'] === 'Pending Review') $qcClass = 'badge-warning';
                                        ?>
                                        <span class="badge <?= e($qcClass) ?>"><?= e($bb['latest_qc_result']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($bb['latest_packing_status'])): ?>
                                        <span class="badge badge-info"><?= e($bb['latest_packing_status']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">Unpacked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-actions text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$bb['batch_id'])) ?>" class="btn btn-sm btn-secondary">
                                            View Batch
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
