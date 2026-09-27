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

<!-- Scoped Dossier Styles -->
<style>
.dossier-wrapper {
    max-width: 960px;
    margin: 0 auto;
}
.dossier-hero {
    background: var(--color-surface);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1.25rem;
}
.dossier-hero-code {
    display: inline-block;
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--color-primary);
    background: var(--color-primary-subtle);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 0.35rem 0.85rem;
    margin-bottom: 0.5rem;
}
.dossier-hero-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: 0.35rem;
}
.dossier-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.dossier-card-header {
    background: var(--color-surface-subtle);
    padding: 0.95rem 1.35rem;
    border-bottom: 1px solid var(--color-border-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.dossier-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-primary-dark);
    margin: 0;
}
.dossier-card-body {
    padding: 1.35rem;
}
.qi-scorecard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.qi-score-card {
    background: var(--color-surface-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-md);
    padding: 1rem 1.15rem;
    text-align: center;
}
.qi-score-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--color-text-muted);
}
.qi-score-val {
    font-size: 1.85rem;
    font-weight: 800;
    margin: 0.25rem 0 0.15rem;
    line-height: 1.2;
}
.qi-score-sub {
    font-size: 0.75rem;
    color: var(--color-text-subtle);
}
.qi-progress-wrap {
    background: #E5E7EB;
    border-radius: var(--radius-full);
    height: 12px;
    overflow: hidden;
    display: flex;
    margin-top: 0.5rem;
}
.qi-progress-pass {
    background: var(--color-success);
    height: 100%;
    transition: width 0.3s ease;
}
.qi-progress-defect {
    background: var(--color-danger);
    height: 100%;
    transition: width 0.3s ease;
}
</style>

<div class="page-header">
    <div class="page-title-group">
        <h1>Quality Inspection Dossier</h1>
        <div class="page-subtitle">Inspection #<?= (int) $inspection['inspection_id'] ?> &bull; Operational Quality Audit</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/quality_inspections/index.php')) ?>" class="btn btn-secondary">
            &larr; Back to Inspection List
        </a>
        <a href="<?= e(url('modules/quality_inspections/edit.php?id=' . (int)$inspection['inspection_id'])) ?>" class="btn btn-primary">
            Edit Inspection
        </a>
    </div>
</div>

<div class="dossier-wrapper">
    <!-- Hero Banner -->
    <div class="dossier-hero">
        <div>
            <div class="dossier-hero-code"><code><?= e($inspection['inspection_number']) ?></code></div>
            <div class="dossier-hero-title">
                Batch <?= e($inspection['batch_number']) ?> &mdash; <?= e($inspection['style_number']) ?>
            </div>
            <div class="text-xs text-muted">
                Buyer: <strong><?= e($inspection['buyer_name']) ?></strong> &bull;
                Order: <strong><?= e($inspection['order_number']) ?></strong> &bull;
                Audited: <?= e(date('F j, Y', strtotime($inspection['inspection_date']))) ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-info" style="font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                <?= e($inspection['inspection_stage']) ?> Stage
            </span>
            <span class="badge <?= $resBadgeClass ?> font-bold" style="font-size: 0.85rem; padding: 0.4rem 0.95rem;">
                <?= e($inspection['overall_result']) ?>
            </span>
        </div>
    </div>

    <!-- Metric Scorecard with Visual Progress Ratio -->
    <div class="dossier-card">
        <div class="dossier-card-header">
            <h2 class="dossier-card-title">Sampling Volume &amp; Quality Ratio</h2>
            <span class="badge badge-muted text-xs">Formula: Passed (<?= number_format($passedQty) ?>) + Defective (<?= number_format($defectiveQty) ?>) = Inspected (<?= number_format($inspectedQty) ?>)</span>
        </div>
        <div class="dossier-card-body">
            <div class="qi-scorecard-grid">
                <div class="qi-score-card">
                    <div class="qi-score-label">Inspected Volume</div>
                    <div class="qi-score-val" style="color: var(--color-text);"><?= number_format($inspectedQty) ?></div>
                    <div class="qi-score-sub">Total pieces sampled</div>
                </div>

                <div class="qi-score-card" style="background: #F0FDF4; border-color: #DCFCE7;">
                    <div class="qi-score-label" style="color: #166534;">Passed Garments</div>
                    <div class="qi-score-val" style="color: #15803D;"><?= number_format($passedQty) ?></div>
                    <div class="qi-score-sub" style="color: #166534;"><?= $passRate ?>% pass rate</div>
                </div>

                <div class="qi-score-card" style="background: <?= $defectiveQty > 0 ? '#FEF2F2' : 'var(--color-surface-subtle)' ?>; border-color: <?= $defectiveQty > 0 ? '#FEE2E2' : 'var(--color-border-subtle)' ?>;">
                    <div class="qi-score-label" style="color: <?= $defectiveQty > 0 ? '#991B1B' : 'inherit' ?>;">Defective Units</div>
                    <div class="qi-score-val" style="color: <?= $defectiveQty > 0 ? '#991B1B' : 'var(--color-text)' ?>;"><?= number_format($defectiveQty) ?></div>
                    <div class="qi-score-sub" style="color: <?= $defectiveQty > 0 ? '#991B1B' : 'inherit' ?>;"><?= $defectRate ?>% defect rate</div>
                </div>

                <div class="qi-score-card">
                    <div class="qi-score-label">Defect Occurrences</div>
                    <div class="qi-score-val" style="color: var(--color-primary);"><?= number_format($totalOccurrences) ?></div>
                    <div class="qi-score-sub">across <?= count($defects) ?> fault category(s)</div>
                </div>
            </div>

            <!-- Pass/Defect Proportion Bar -->
            <div>
                <div class="d-flex justify-content-between text-xs font-semibold mb-1">
                    <span style="color: #15803D;">Passed: <?= $passRate ?>%</span>
                    <span style="color: <?= $defectiveQty > 0 ? '#991B1B' : 'var(--color-text-muted)' ?>;">Defective: <?= $defectRate ?>%</span>
                </div>
                <div class="qi-progress-wrap">
                    <div class="qi-progress-pass" style="width: <?= $passRate ?>%;"></div>
                    <div class="qi-progress-defect" style="width: <?= $defectRate ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Operational Context & Inspector Card -->
    <div class="dossier-card">
        <div class="dossier-card-header">
            <h2 class="dossier-card-title">Audit Context &amp; Production Batch</h2>
            <span class="badge badge-muted">Traceability</span>
        </div>
        <div class="dossier-card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table mb-0">
                    <tbody>
                        <tr>
                            <th style="width: 220px;">Inspector Account</th>
                            <td>
                                <strong><?= e($inspection['inspector_name'] ?: $inspection['inspector_username']) ?></strong>
                                <span class="badge badge-role ml-1"><?= e(ucwords(str_replace('_', ' ', $inspection['inspector_role']))) ?></span>
                                <?php if (!empty($inspection['inspector_email'])): ?>
                                    <span class="text-xs text-muted ml-2">&bull; <?= e($inspection['inspector_email']) ?></span>
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
                                <div class="text-xs text-muted mt-1">
                                    Planned Volume: <?= number_format((int)$inspection['batch_planned_qty']) ?> pcs &bull;
                                    Batch Status: <span class="badge badge-muted"><?= e($inspection['batch_status']) ?></span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>Buyer Order &amp; Style</th>
                            <td>
                                <strong><?= e($inspection['style_number']) ?></strong> &bull; Order <?= e($inspection['order_number']) ?>
                                <span class="text-xs text-muted">(Buyer: <?= e($inspection['buyer_name']) ?>)</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Inspection Stage &amp; Date</th>
                            <td>
                                <span class="badge badge-info mr-2"><?= e($inspection['inspection_stage']) ?></span>
                                <?= e(date('F j, Y', strtotime($inspection['inspection_date']))) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Defect Breakdown Table -->
    <div class="dossier-card">
        <div class="dossier-card-header">
            <h2 class="dossier-card-title">Defect Types Breakdown</h2>
            <span class="badge badge-muted"><?= count($defects) ?> Defect Type(s) Recorded</span>
        </div>
        <div class="dossier-card-body" style="padding: 0;">
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
                                <th>Notes / Specific Area</th>
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
                                                <code><?= e($d['defect_code']) ?></code>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?= e($d['defect_name']) ?></td>
                                    <td><span class="badge badge-muted"><?= e($d['category']) ?></span></td>
                                    <td><span class="badge <?= $sevClass ?>"><?= ucfirst(e($d['severity'])) ?></span></td>
                                    <td class="text-right font-bold"><?= number_format((int)$d['defect_count']) ?></td>
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

    <!-- Observations & Remarks Card -->
    <div class="dossier-card">
        <div class="dossier-card-header">
            <h2 class="dossier-card-title">Inspector Remarks &amp; Clearance Notes</h2>
        </div>
        <div class="dossier-card-body">
            <?php if (!empty($inspection['remarks'])): ?>
                <p class="mb-0" style="white-space: pre-line; line-height: 1.6; color: var(--color-text);">
                    <?= e($inspection['remarks']) ?>
                </p>
            <?php else: ?>
                <p class="text-muted mb-0">No specific floor remarks recorded for this audit.</p>
            <?php endif; ?>
        </div>
        <div class="card-footer d-flex justify-content-between text-xs text-muted">
            <span>Audit Created: <?= e($inspection['created_at']) ?></span>
            <span>Last Updated: <?= e($inspection['updated_at']) ?></span>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
