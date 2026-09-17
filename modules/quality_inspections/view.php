<?php
/**
 * ThreadFlow - Quality Inspection Dossier View
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

// Positive integer ID validation
$inspectionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$inspectionId || $inspectionId <= 0) {
    flash_set('error', 'Invalid quality inspection ID specified.');
    redirect('modules/quality_inspections/index.php');
}

// Fetch inspection with joined batch, order, buyer, and inspector information
$inspection = null;
try {
    $sql = "SELECT 
                qi.inspection_id,
                qi.inspection_number,
                qi.batch_id,
                qi.inspector_id,
                qi.inspection_date,
                qi.inspection_stage,
                qi.inspected_quantity,
                qi.passed_quantity,
                qi.defective_quantity,
                qi.overall_result,
                qi.remarks,
                qi.created_at,
                qi.updated_at,
                pb.batch_number,
                pb.planned_quantity AS batch_planned_qty,
                pb.status AS batch_status,
                bo.order_number,
                bo.style_number,
                b.buyer_name,
                b.buyer_id,
                u.full_name AS inspector_name,
                u.username AS inspector_username,
                u.role AS inspector_role,
                u.email AS inspector_email
            FROM quality_inspections qi
            INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id
            INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
            INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
            INNER JOIN users u ON qi.inspector_id = u.user_id
            WHERE qi.inspection_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare SELECT for inspection view: " . $conn->error);
        flash_set('error', 'A database error occurred while loading inspection details.');
        redirect('modules/quality_inspections/index.php');
    }

    $stmt->bind_param("i", $inspectionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $inspection = $result->fetch_assoc();
    $stmt->close();

    if (!$inspection) {
        flash_set('error', 'Quality inspection record not found.');
        redirect('modules/quality_inspections/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading inspection view: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading inspection details.');
    redirect('modules/quality_inspections/index.php');
}

// Fetch associated defect breakdown items
$defects = [];
try {
    $defSql = "SELECT 
                    idf.inspection_defect_id,
                    idf.defect_id,
                    idf.defect_count,
                    idf.notes,
                    dt.defect_code,
                    dt.defect_name,
                    dt.category,
                    dt.severity
               FROM inspection_defects idf
               INNER JOIN defect_types dt ON idf.defect_id = dt.defect_id
               WHERE idf.inspection_id = ?
               ORDER BY idf.inspection_defect_id ASC";

    $dStmt = $conn->prepare($defSql);
    if ($dStmt) {
        $dStmt->bind_param("i", $inspectionId);
        $dStmt->execute();
        $dRes = $dStmt->get_result();
        $defects = $dRes->fetch_all(MYSQLI_ASSOC);
        $dStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading inspection defects: " . $e->getMessage());
}

$pageTitle = 'Inspection: ' . $inspection['inspection_number'];
$currentNav = 'quality_inspections';

$inspectedQty = (int) $inspection['inspected_quantity'];
$passedQty = (int) $inspection['passed_quantity'];
$defectiveQty = (int) $inspection['defective_quantity'];

$passRate = $inspectedQty > 0 ? round(($passedQty / $inspectedQty) * 100, 1) : 0;
$defectRate = $inspectedQty > 0 ? round(($defectiveQty / $inspectedQty) * 100, 1) : 0;

$totalOccurrences = 0;
foreach ($defects as $d) {
    $totalOccurrences += (int) $d['defect_count'];
}

$resBadgeClass = 'badge-secondary';
if ($inspection['overall_result'] === 'Passed') {
    $resBadgeClass = 'badge-success';
} elseif ($inspection['overall_result'] === 'Failed') {
    $resBadgeClass = 'badge-danger';
} elseif ($inspection['overall_result'] === 'Pending Review') {
    $resBadgeClass = 'badge-warning';
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Quality Inspection Dossier</h1>
        <div class="page-subtitle">Inspection #<?= (int) $inspection['inspection_id'] ?> &bull; <?= e($inspection['inspection_number']) ?> &bull; Batch <?= e($inspection['batch_number']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/quality_inspections/index.php')) ?>" class="btn btn-secondary">Back to Inspection List</a>
        <a href="<?= e(url('modules/quality_inspections/edit.php?id=' . (int)$inspection['inspection_id'])) ?>" class="btn btn-primary">Edit Inspection</a>
    </div>
</div>

<div class="card card-medium">
    <div class="card-header">
        <h2>Inspection <?= e($inspection['inspection_number']) ?></h2>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-info"><?= e($inspection['inspection_stage']) ?></span>
            <span class="badge <?= $resBadgeClass ?>"><?= e($inspection['overall_result']) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 220px;">Inspection Number</th>
                        <td><strong><?= e($inspection['inspection_number']) ?></strong> (Record #<?= (int) $inspection['inspection_id'] ?>)</td>
                    </tr>
                    <tr>
                        <th>Overall Result</th>
                        <td><span class="badge <?= $resBadgeClass ?>"><?= e($inspection['overall_result']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Inspection Stage</th>
                        <td><span class="badge badge-info"><?= e($inspection['inspection_stage']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Inspection Date</th>
                        <td><?= e(date('F j, Y', strtotime($inspection['inspection_date']))) ?></td>
                    </tr>
                    <tr>
                        <th>Inspector</th>
                        <td>
                            <strong><?= e($inspection['inspector_name'] ?: $inspection['inspector_username']) ?></strong>
                            <span class="badge badge-role ml-1"><?= e(ucwords(str_replace('_', ' ', $inspection['inspector_role']))) ?></span>
                            <?php if (!empty($inspection['inspector_email'])): ?>
                                <div class="text-xs text-muted"><?= e($inspection['inspector_email']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Production Batch</th>
                        <td>
                            <strong>
                                <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$inspection['batch_id'])) ?>">
                                    <?= e($inspection['batch_number']) ?>
                                </a>
                            </strong>
                            <div class="text-sm text-muted">
                                Style: <strong><?= e($inspection['style_number']) ?></strong> &bull; Order: <?= e($inspection['order_number']) ?> &bull; Buyer: <?= e($inspection['buyer_name']) ?>
                            </div>
                            <div class="text-xs text-muted">Batch Planned Volume: <?= number_format((int)$inspection['batch_planned_qty']) ?> pcs (Status: <?= e($inspection['batch_status']) ?>)</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quantity & Metric Scorecard -->
<div class="card card-medium mt-4">
    <div class="card-header">
        <h2>Quantity &amp; Defect Metric Scorecard</h2>
        <span class="badge badge-muted">Formula: Passed + Defective = Inspected</span>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; text-align: center;">
            <div style="padding: 1rem; background: #FAFAFA; border: 1px solid #eee; border-radius: 8px;">
                <div class="text-xs text-muted">INSPECTED</div>
                <div style="font-size: 1.5rem; font-weight: bold; margin-top: 0.25rem;">
                    <?= number_format($inspectedQty) ?>
                </div>
                <div class="text-xs text-muted">pieces sampled</div>
            </div>

            <div style="padding: 1rem; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 8px;">
                <div class="text-xs" style="color: #2e7d32; font-weight: bold;">PASSED PIECES</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #2e7d32; margin-top: 0.25rem;">
                    <?= number_format($passedQty) ?>
                </div>
                <div class="text-xs" style="color: #2e7d32;"><?= $passRate ?>% pass rate</div>
            </div>

            <div style="padding: 1rem; background: <?= $defectiveQty > 0 ? '#ffebee' : '#FAFAFA' ?>; border: 1px solid <?= $defectiveQty > 0 ? '#ffcdd2' : '#eee' ?>; border-radius: 8px;">
                <div class="text-xs" style="color: <?= $defectiveQty > 0 ? '#c62828' : 'inherit' ?>; font-weight: bold;">DEFECTIVE PIECES</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: <?= $defectiveQty > 0 ? '#c62828' : 'inherit' ?>; margin-top: 0.25rem;">
                    <?= number_format($defectiveQty) ?>
                </div>
                <div class="text-xs" style="color: <?= $defectiveQty > 0 ? '#c62828' : 'inherit' ?>;"><?= $defectRate ?>% defect rate</div>
            </div>

            <div style="padding: 1rem; background: #FAFAFA; border: 1px solid #eee; border-radius: 8px;">
                <div class="text-xs text-muted">DEFECT OCCURRENCES</div>
                <div style="font-size: 1.5rem; font-weight: bold; margin-top: 0.25rem;">
                    <?= number_format($totalOccurrences) ?>
                </div>
                <div class="text-xs text-muted">across <?= count($defects) ?> defect type(s)</div>
            </div>
        </div>
    </div>
</div>

<!-- Defect Breakdown Table -->
<div class="card card-medium mt-4">
    <div class="card-header">
        <h2>Defect Types Breakdown</h2>
        <span class="badge badge-muted"><?= count($defects) ?> Defect Type(s) Logged</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($defects)): ?>
            <div class="empty-state">
                <p>No defect types were recorded for this audit (Defective Quantity: <?= $defectiveQty ?>).</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Defect Code</th>
                            <th>Defect Name</th>
                            <th>Category</th>
                            <th>Severity</th>
                            <th class="text-right">Defect Count</th>
                            <th>Notes / Placement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($defects as $d): ?>
                            <?php
                            $sevClass = 'badge-secondary';
                            if ($d['severity'] === 'minor') {
                                $sevClass = 'badge-info';
                            } elseif ($d['severity'] === 'major') {
                                $sevClass = 'badge-warning';
                            } elseif ($d['severity'] === 'critical') {
                                $sevClass = 'badge-danger';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/defect_types/view.php?id=' . (int)$d['defect_id'])) ?>">
                                            <?= e($d['defect_code']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?= e($d['defect_name']) ?></td>
                                <td><?= e($d['category']) ?></td>
                                <td><span class="badge <?= $sevClass ?>"><?= ucfirst(e($d['severity'])) ?></span></td>
                                <td class="text-right font-semibold"><?= number_format((int)$d['defect_count']) ?></td>
                                <td>
                                    <?php if (!empty($d['notes'])): ?>
                                        <?= e($d['notes']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Observations & Audit Metadata -->
<div class="card card-medium mt-4">
    <div class="card-header">
        <h2>Remarks &amp; Audit Metadata</h2>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <strong>Inspector Observations / Floor Remarks:</strong>
            <p class="mt-1" style="white-space: pre-line;"><?= !empty($inspection['remarks']) ? e($inspection['remarks']) : '<span class="text-muted">No specific remarks recorded for this inspection audit.</span>' ?></p>
        </div>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 1rem 0;">
        <div class="text-xs text-muted d-flex justify-content-between">
            <span>Record Created: <?= e($inspection['created_at']) ?></span>
            <span>Last Updated: <?= e($inspection['updated_at']) ?></span>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
