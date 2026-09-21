<?php
/**
 * ThreadFlow - Rework Job Dossier View
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

// Validate ID
$reworkId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$reworkId || $reworkId <= 0) {
    flash_set('error', 'Invalid rework job ID specified.');
    redirect('modules/rework/index.php');
}

// Fetch rework record with comprehensive joins
$rework = null;
try {
    $sql = "SELECT 
                rj.rework_id,
                rj.rework_number,
                rj.inspection_defect_id,
                rj.line_id,
                rj.assigned_to,
                rj.rework_quantity,
                rj.assigned_date,
                rj.target_completion_date,
                rj.actual_completion_date,
                rj.completed_quantity,
                rj.rejected_quantity,
                rj.status,
                rj.corrective_action,
                rj.remarks,
                rj.created_at,
                rj.updated_at,
                idf.defect_count,
                idf.notes AS defect_notes,
                qi.inspection_id,
                qi.inspection_number,
                qi.inspection_date,
                qi.inspection_stage,
                qi.overall_result,
                qi.inspected_quantity,
                qi.passed_quantity,
                qi.defective_quantity AS qi_defective_qty,
                pb.batch_id,
                pb.batch_number,
                pb.planned_quantity AS batch_planned_qty,
                pb.status AS batch_status,
                bo.order_id,
                bo.order_number,
                bo.style_number,
                b.buyer_id,
                b.buyer_name,
                dt.defect_id,
                dt.defect_code,
                dt.defect_name,
                dt.category AS defect_category,
                dt.severity AS defect_severity,
                dt.description AS defect_description,
                sl.line_name,
                sl.line_code,
                uc.full_name AS creator_name,
                uc.username AS creator_username,
                uc.role AS creator_role,
                uu.full_name AS updater_name,
                uu.username AS updater_username,
                uu.role AS updater_role
            FROM rework_jobs rj
            INNER JOIN inspection_defects idf ON rj.inspection_defect_id = idf.inspection_defect_id
            INNER JOIN quality_inspections qi ON idf.inspection_id = qi.inspection_id
            INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id
            INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
            INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
            INNER JOIN defect_types dt ON idf.defect_id = dt.defect_id
            LEFT JOIN sewing_lines sl ON rj.line_id = sl.line_id
            INNER JOIN users uc ON rj.created_by = uc.user_id
            LEFT JOIN users uu ON rj.updated_by = uu.user_id
            WHERE rj.rework_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare rework view query: " . $conn->error);
        flash_set('error', 'A database error occurred while loading rework job details.');
        redirect('modules/rework/index.php');
    }

    $stmt->bind_param("i", $reworkId);
    $stmt->execute();
    $rework = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rework) {
        flash_set('error', 'Rework job record not found.');
        redirect('modules/rework/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading rework view: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading rework details.');
    redirect('modules/rework/index.php');
}

$pageTitle = 'Rework Job: ' . $rework['rework_number'];
$currentNav = 'rework';

$reworkQty    = (int) $rework['rework_quantity'];
$completedQty = (int) $rework['completed_quantity'];
$rejectedQty  = (int) $rework['rejected_quantity'];
$inProgressQty = max(0, $reworkQty - ($completedQty + $rejectedQty));

$completionRate = $reworkQty > 0 ? round(($completedQty / $reworkQty) * 100, 1) : 0;
$scrapRate      = $reworkQty > 0 ? round(($rejectedQty / $reworkQty) * 100, 1) : 0;

// Badges
$statusBadgeClass = 'badge-secondary';
if ($rework['status'] === 'Pending') {
    $statusBadgeClass = 'badge-warning';
} elseif ($rework['status'] === 'In Progress') {
    $statusBadgeClass = 'badge-info';
} elseif ($rework['status'] === 'Completed') {
    $statusBadgeClass = 'badge-success';
} elseif ($rework['status'] === 'Rejected') {
    $statusBadgeClass = 'badge-danger';
}

$sevBadgeClass = 'badge-secondary';
if ($rework['defect_severity'] === 'minor') {
    $sevBadgeClass = 'badge-info';
} elseif ($rework['defect_severity'] === 'major') {
    $sevBadgeClass = 'badge-warning';
} elseif ($rework['defect_severity'] === 'critical') {
    $sevBadgeClass = 'badge-danger';
}

$qiResultClass = 'badge-secondary';
if ($rework['overall_result'] === 'Passed') {
    $qiResultClass = 'badge-success';
} elseif ($rework['overall_result'] === 'Failed') {
    $qiResultClass = 'badge-danger';
} elseif ($rework['overall_result'] === 'Pending Review') {
    $qiResultClass = 'badge-warning';
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Rework Job Dossier</h1>
        <div class="page-subtitle">Job #<?= (int)$rework['rework_id'] ?> &bull; <?= e($rework['rework_number']) ?> &bull; Batch <?= e($rework['batch_number']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/rework/index.php')) ?>" class="btn btn-secondary">Back to Rework List</a>
        <a href="<?= e(url('modules/rework/edit.php?id=' . (int)$rework['rework_id'])) ?>" class="btn btn-primary">Edit / Update Progress</a>
    </div>
</div>

<!-- Progress Scorecard -->
<div class="card card-medium">
    <div class="card-header">
        <h2>Rework Progress Scorecard</h2>
        <div class="d-flex align-items-center gap-2">
            <span class="badge <?= $statusBadgeClass ?>"><?= e($rework['status']) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; text-align: center;">
            <div style="padding: 1rem; background: #FAFAFA; border: 1px solid #eee; border-radius: 8px;">
                <div class="text-xs text-muted" style="font-weight: 600;">ALLOCATED REWORK</div>
                <div style="font-size: 1.6rem; font-weight: bold; margin-top: 0.25rem;">
                    <?= number_format($reworkQty) ?>
                </div>
                <div class="text-xs text-muted">garment pieces</div>
            </div>

            <div style="padding: 1rem; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 8px;">
                <div class="text-xs" style="color: #2e7d32; font-weight: 700;">COMPLETED / SALVAGED</div>
                <div style="font-size: 1.6rem; font-weight: bold; color: #2e7d32; margin-top: 0.25rem;">
                    <?= number_format($completedQty) ?>
                </div>
                <div class="text-xs" style="color: #2e7d32;"><?= $completionRate ?>% salvage rate</div>
            </div>

            <div style="padding: 1rem; background: <?= $rejectedQty > 0 ? '#ffebee' : '#FAFAFA' ?>; border: 1px solid <?= $rejectedQty > 0 ? '#ffcdd2' : '#eee' ?>; border-radius: 8px;">
                <div class="text-xs" style="color: <?= $rejectedQty > 0 ? '#c62828' : '#777' ?>; font-weight: 700;">REJECTED / SCRAPPED</div>
                <div style="font-size: 1.6rem; font-weight: bold; color: <?= $rejectedQty > 0 ? '#c62828' : '#777' ?>; margin-top: 0.25rem;">
                    <?= number_format($rejectedQty) ?>
                </div>
                <div class="text-xs" style="color: <?= $rejectedQty > 0 ? '#c62828' : '#777' ?>;"><?= $scrapRate ?>% scrap rate</div>
            </div>

            <div style="padding: 1rem; background: <?= $inProgressQty > 0 ? '#ebf8fa' : '#FAFAFA' ?>; border: 1px solid <?= $inProgressQty > 0 ? '#bce8f1' : '#eee' ?>; border-radius: 8px;">
                <div class="text-xs" style="color: <?= $inProgressQty > 0 ? '#31708f' : '#777' ?>; font-weight: 700;">REMAINING IN REPAIR</div>
                <div style="font-size: 1.6rem; font-weight: bold; color: <?= $inProgressQty > 0 ? '#31708f' : '#777' ?>; margin-top: 0.25rem;">
                    <?= number_format($inProgressQty) ?>
                </div>
                <div class="text-xs text-muted">pieces pending fix</div>
            </div>
        </div>
    </div>
</div>

<!-- Primary Rework Job Details -->
<div class="card card-medium mt-4">
    <div class="card-header">
        <h2>Job Specifications &amp; Floor Assignment</h2>
        <span class="badge badge-muted">Tracking ID: <?= e($rework['rework_number']) ?></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 240px;">Rework Number</th>
                        <td><strong><?= e($rework['rework_number']) ?></strong> (Record #<?= (int)$rework['rework_id'] ?>)</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge <?= $statusBadgeClass ?>"><?= e($rework['status']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Assigned To</th>
                        <td>
                            <strong><?= e($rework['assigned_to']) ?></strong>
                            <?php if (!empty($rework['line_name'])): ?>
                                <div class="text-sm text-muted">
                                    Sewing Line: <strong><?= e($rework['line_name']) ?></strong> (<?= e($rework['line_code']) ?>)
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Assigned Date</th>
                        <td><?= e(date('F j, Y', strtotime($rework['assigned_date']))) ?></td>
                    </tr>
                    <tr>
                        <th>Target Completion Date</th>
                        <td><?= e(date('F j, Y', strtotime($rework['target_completion_date']))) ?></td>
                    </tr>
                    <tr>
                        <th>Actual Completion Date</th>
                        <td>
                            <?php if (!empty($rework['actual_completion_date'])): ?>
                                <strong><?= e(date('F j, Y', strtotime($rework['actual_completion_date']))) ?></strong>
                            <?php else: ?>
                                <span class="text-muted">Not yet completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Source Traceability Dossier -->
<div class="card card-medium mt-4">
    <div class="card-header">
        <h2>Traceability: Quality Inspection &amp; Batch Origin</h2>
        <span class="badge badge-primary">Audit Linked</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 240px;">Source Quality Inspection</th>
                        <td>
                            <strong>
                                <a href="<?= e(url('modules/quality_inspections/view.php?id=' . (int)$rework['inspection_id'])) ?>">
                                    <?= e($rework['inspection_number']) ?>
                                </a>
                            </strong>
                            <div class="text-sm text-muted">
                                Inspected Date: <?= e(date('M d, Y', strtotime($rework['inspection_date']))) ?> &bull; 
                                Stage: <span class="badge badge-info"><?= e($rework['inspection_stage']) ?></span> &bull; 
                                Overall Result: <span class="badge <?= $qiResultClass ?>"><?= e($rework['overall_result']) ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Production Batch</th>
                        <td>
                            <strong>
                                <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$rework['batch_id'])) ?>">
                                    <?= e($rework['batch_number']) ?>
                                </a>
                            </strong>
                            <div class="text-sm text-muted">
                                Style: <strong><?= e($rework['style_number']) ?></strong> &bull; 
                                Order: <?= e($rework['order_number']) ?> &bull; 
                                Buyer: <?= e($rework['buyer_name']) ?>
                            </div>
                            <div class="text-xs text-muted">Batch Planned Volume: <?= number_format((int)$rework['batch_planned_qty']) ?> pcs (Status: <?= e($rework['batch_status']) ?>)</div>
                        </td>
                    </tr>
                    <tr>
                        <th>Source Defect Type</th>
                        <td>
                            <strong>
                                <a href="<?= e(url('modules/defect_types/view.php?id=' . (int)$rework['defect_id'])) ?>">
                                    <?= e($rework['defect_name']) ?> (<?= e($rework['defect_code']) ?>)
                                </a>
                            </strong>
                            <div class="text-sm text-muted">
                                Category: <strong><?= e($rework['defect_category']) ?></strong> &bull; 
                                Severity: <span class="badge <?= $sevBadgeClass ?>"><?= ucfirst(e($rework['defect_severity'])) ?></span>
                            </div>
                            <?php if (!empty($rework['defect_notes'])): ?>
                                <div class="text-xs text-muted mt-1">Inspection Placement Notes: <em><?= e($rework['defect_notes']) ?></em></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Inspection Defect Volume</th>
                        <td>
                            <strong><?= number_format((int)$rework['defect_count']) ?> piece(s)</strong> total recorded for this defect in the inspection.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Corrective Action & Remarks -->
<div class="card card-medium mt-4">
    <div class="card-header">
        <h2>Corrective Action &amp; Floor Remarks</h2>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <strong>Corrective Action Procedure:</strong>
            <p class="mt-1" style="white-space: pre-line;"><?= !empty($rework['corrective_action']) ? e($rework['corrective_action']) : '<span class="text-muted">No specific corrective procedure documented.</span>' ?></p>
        </div>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 1rem 0;">
        <div class="mb-3">
            <strong>QC &amp; Floor Supervisor Remarks:</strong>
            <p class="mt-1" style="white-space: pre-line;"><?= !empty($rework['remarks']) ? e($rework['remarks']) : '<span class="text-muted">No floor remarks logged.</span>' ?></p>
        </div>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 1rem 0;">
        <div class="text-xs text-muted d-flex justify-content-between flex-wrap gap-2">
            <div>
                Created by <strong><?= e($rework['creator_name'] ?: $rework['creator_username']) ?></strong>
                (<?= e(ucwords(str_replace('_', ' ', $rework['creator_role']))) ?>) on <?= e($rework['created_at']) ?>
            </div>
            <?php if (!empty($rework['updated_by']) && !empty($rework['updater_name'])): ?>
                <div>
                    Last updated by <strong><?= e($rework['updater_name'] ?: $rework['updater_username']) ?></strong>
                    (<?= e(ucwords(str_replace('_', ' ', $rework['updater_role']))) ?>) on <?= e($rework['updated_at']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
