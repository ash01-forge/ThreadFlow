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

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.rwk-view-container {
    max-width: 1080px;
    margin: 0 auto;
}
.rwk-hero {
    background: linear-gradient(135deg, #fff 0%, #FFFDFD 100%);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
}
.rwk-hero-code {
    font-family: var(--font-mono);
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--color-primary);
}
.rwk-scorecard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.rwk-score-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1.15rem;
    box-shadow: var(--shadow-sm);
    text-align: center;
}
.rwk-score-card.salvaged {
    background: #F0FDF4;
    border-color: #BBF7D0;
}
.rwk-score-card.scrapped {
    background: #FEF2F2;
    border-color: #FECACA;
}
.rwk-score-card.in-repair {
    background: #EFF6FF;
    border-color: #BFDBFE;
}
.rwk-score-val {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.2;
    margin: 0.35rem 0;
}
.rwk-progress-bar-container {
    background: #E5E7EB;
    border-radius: 999px;
    height: 10px;
    overflow: hidden;
    display: flex;
    margin-top: 0.5rem;
    width: 100%;
}
.rwk-progress-segment {
    height: 100%;
    transition: width 0.3s ease;
}
.rwk-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.rwk-dossier-table th {
    width: 38%;
    color: var(--color-text-muted);
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid #f1f1f1;
    background: #fafafa;
}
.rwk-dossier-table td {
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid #f1f1f1;
    font-size: 0.9rem;
}
.rwk-callout-box {
    background: #FFFDFD;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1.15rem;
    margin-bottom: 1rem;
}
.rwk-callout-title {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-primary-dark);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
</style>

<div class="rwk-view-container">
    <div class="page-header">
        <div class="page-title-group">
            <h1>Rework Job Dossier</h1>
            <div class="page-subtitle">Repair allocation and floor disposition record &bull; Record #<?= (int)$rework['rework_id'] ?></div>
        </div>
        <div class="page-actions">
            <a href="<?= e(url('modules/rework/index.php')) ?>" class="btn btn-secondary">
                Back to Rework List
            </a>
            <a href="<?= e(url('modules/rework/edit.php?id=' . (int)$rework['rework_id'])) ?>" class="btn btn-primary">
                Edit / Update Progress
            </a>
        </div>
    </div>

    <!-- Hero Header Banner -->
    <div class="rwk-hero">
        <div>
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.35rem;">
                <span class="rwk-hero-code"><?= e($rework['rework_number']) ?></span>
                <span class="badge <?= $statusBadgeClass ?>" style="font-size:0.85rem;padding:0.35rem 0.75rem;"><?= e($rework['status']) ?></span>
                <span class="badge <?= $sevBadgeClass ?>"><?= ucfirst(e($rework['defect_severity'])) ?> Defect</span>
            </div>
            <div class="text-sm text-muted">
                Originating from Batch <strong><a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$rework['batch_id'])) ?>"><?= e($rework['batch_number']) ?></a></strong>
                &bull; Style <strong><?= e($rework['style_number']) ?></strong>
                &bull; Buyer <strong><?= e($rework['buyer_name']) ?></strong>
            </div>
        </div>

        <div style="text-align:right;">
            <div class="text-xs text-muted" style="font-weight:600;text-transform:uppercase;">Source Quality Audit</div>
            <div>
                <a href="<?= e(url('modules/quality_inspections/view.php?id=' . (int)$rework['inspection_id'])) ?>" style="font-weight:700;color:var(--color-primary);font-size:1.05rem;">
                    <?= e($rework['inspection_number']) ?>
                </a>
                <span class="badge <?= $qiResultClass ?> text-xs ml-1"><?= e($rework['overall_result']) ?></span>
            </div>
            <div class="text-xs text-muted">Stage: <?= e($rework['inspection_stage']) ?> &bull; <?= e(date('M d, Y', strtotime($rework['inspection_date']))) ?></div>
        </div>
    </div>

    <!-- Progress Scorecard -->
    <div class="rwk-scorecard">
        <div class="rwk-score-card">
            <div class="text-xs text-muted" style="font-weight:700;letter-spacing:0.04em;">ALLOCATED REWORK</div>
            <div class="rwk-score-val" style="color:var(--color-text);">
                <?= number_format($reworkQty) ?>
            </div>
            <div class="text-xs text-muted">Total garment pieces routed</div>
        </div>

        <div class="rwk-score-card salvaged">
            <div class="text-xs" style="color:#166534;font-weight:700;letter-spacing:0.04em;">COMPLETED / SALVAGED</div>
            <div class="rwk-score-val" style="color:#166534;">
                <?= number_format($completedQty) ?>
            </div>
            <div class="text-xs" style="color:#166534;font-weight:600;"><?= $completionRate ?>% salvage recovery</div>
        </div>

        <div class="rwk-score-card <?= $rejectedQty > 0 ? 'scrapped' : '' ?>">
            <div class="text-xs" style="color:<?= $rejectedQty > 0 ? '#991B1B' : 'var(--color-text-muted)' ?>;font-weight:700;letter-spacing:0.04em;">REJECTED / SCRAPPED</div>
            <div class="rwk-score-val" style="color:<?= $rejectedQty > 0 ? '#991B1B' : 'var(--color-text)' ?>;">
                <?= number_format($rejectedQty) ?>
            </div>
            <div class="text-xs" style="color:<?= $rejectedQty > 0 ? '#991B1B' : 'var(--color-text-muted)' ?>;"><?= $scrapRate ?>% unrecoverable scrap</div>
        </div>

        <div class="rwk-score-card <?= $inProgressQty > 0 ? 'in-repair' : '' ?>">
            <div class="text-xs" style="color:<?= $inProgressQty > 0 ? '#1E40AF' : 'var(--color-text-muted)' ?>;font-weight:700;letter-spacing:0.04em;">REMAINING IN REPAIR</div>
            <div class="rwk-score-val" style="color:<?= $inProgressQty > 0 ? '#1E40AF' : 'var(--color-text)' ?>;">
                <?= number_format($inProgressQty) ?>
            </div>
            <div class="text-xs text-muted"><?= $reworkQty > 0 ? round(($inProgressQty / $reworkQty) * 100, 1) : 0 ?>% pending fix</div>
        </div>
    </div>

    <!-- Visual Stacked Recovery Bar -->
    <?php if ($reworkQty > 0): ?>
    <div class="card p-3 mb-4" style="background:#fff;">
        <div style="display:flex;justify-content:space-between;font-size:0.8rem;font-weight:600;color:var(--color-text-muted);margin-bottom:0.25rem;">
            <span>Salvage Distribution</span>
            <span><?= $completedQty ?> Salvaged &bull; <?= $rejectedQty ?> Scrapped &bull; <?= $inProgressQty ?> In Repair</span>
        </div>
        <div class="rwk-progress-bar-container">
            <div class="rwk-progress-segment" style="width:<?= $completionRate ?>%;background:#22C55E;" title="Salvaged: <?= $completionRate ?>%"></div>
            <div class="rwk-progress-segment" style="width:<?= $scrapRate ?>%;background:#EF4444;" title="Scrapped: <?= $scrapRate ?>%"></div>
            <div class="rwk-progress-segment" style="width:<?= $reworkQty > 0 ? round(($inProgressQty / $reworkQty) * 100, 1) : 0 ?>%;background:#3B82F6;" title="In Repair"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Two-Column Operational Details Grid -->
    <div class="rwk-grid-2">
        <!-- Card 1: Floor Assignment & Schedule -->
        <div class="card card-medium mb-0">
            <div class="card-header">
                <h2>Floor Assignment &amp; Timetable</h2>
                <span class="badge badge-muted">Operational</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table rwk-dossier-table mb-0">
                        <tbody>
                            <tr>
                                <th>Assigned Worker / Team</th>
                                <td>
                                    <strong><?= e($rework['assigned_to']) ?></strong>
                                    <?php if (!empty($rework['line_name'])): ?>
                                        <div class="text-xs text-muted mt-1">
                                            Sewing Line: <strong><?= e($rework['line_name']) ?></strong> (<?= e($rework['line_code']) ?>)
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Lifecycle Status</th>
                                <td><span class="badge <?= $statusBadgeClass ?>"><?= e($rework['status']) ?></span></td>
                            </tr>
                            <tr>
                                <th>Assigned Date</th>
                                <td><?= e(date('F j, Y', strtotime($rework['assigned_date']))) ?></td>
                            </tr>
                            <tr>
                                <th>Target Completion Date</th>
                                <td>
                                    <strong><?= e(date('F j, Y', strtotime($rework['target_completion_date']))) ?></strong>
                                    <?php
                                    $today = date('Y-m-d');
                                    if ($rework['status'] !== 'Completed' && $rework['status'] !== 'Rejected' && $rework['target_completion_date'] < $today):
                                    ?>
                                        <span class="badge badge-danger text-xs ml-1">Overdue</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Actual Completion Date</th>
                                <td>
                                    <?php if (!empty($rework['actual_completion_date'])): ?>
                                        <strong><?= e(date('F j, Y', strtotime($rework['actual_completion_date']))) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">In Progress / Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Card 2: Defect Origin & QC Audit Context -->
        <div class="card card-medium mb-0">
            <div class="card-header">
                <h2>Defect Origin &amp; QC Audit Context</h2>
                <span class="badge badge-primary">Traceability</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table rwk-dossier-table mb-0">
                        <tbody>
                            <tr>
                                <th>Defect Type</th>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/defect_types/view.php?id=' . (int)$rework['defect_id'])) ?>">
                                            <?= e($rework['defect_name']) ?>
                                        </a>
                                    </strong>
                                    <div class="text-xs text-muted mt-1">
                                        Code: <span class="font-mono"><?= e($rework['defect_code']) ?></span> &bull;
                                        Category: <?= e($rework['defect_category']) ?> &bull;
                                        Severity: <span class="badge <?= $sevBadgeClass ?> text-xs"><?= ucfirst(e($rework['defect_severity'])) ?></span>
                                    </div>
                                    <?php if (!empty($rework['defect_description'])): ?>
                                        <div class="text-xs text-muted mt-1"><em><?= e($rework['defect_description']) ?></em></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Audit Defect Volume</th>
                                <td>
                                    <strong><?= number_format((int)$rework['defect_count']) ?> piece(s)</strong> total logged in inspection
                                    <?php if (!empty($rework['defect_notes'])): ?>
                                        <div class="text-xs text-muted mt-1">Inspector Notes: <em><?= e($rework['defect_notes']) ?></em></div>
                                    <?php endif; ?>
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
                                    <div class="text-xs text-muted mt-1">
                                        Style: <strong><?= e($rework['style_number']) ?></strong> &bull; Order: <?= e($rework['order_number']) ?>
                                    </div>
                                    <div class="text-xs text-muted">Planned Qty: <?= number_format((int)$rework['batch_planned_qty']) ?> pcs (<?= e($rework['batch_status']) ?>)</div>
                                </td>
                            </tr>
                            <tr>
                                <th>Buyer / Brand</th>
                                <td><strong><?= e($rework['buyer_name']) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Corrective Action & Remarks -->
    <div class="card card-medium mb-4">
        <div class="card-header">
            <h2>Corrective Action Procedure &amp; Floor Remarks</h2>
            <span class="badge badge-muted">Quality Instructions</span>
        </div>
        <div class="card-body">
            <div class="rwk-callout-box">
                <div class="rwk-callout-title">
                    Planned Corrective Procedure
                </div>
                <div style="font-size:0.95rem;line-height:1.5;white-space:pre-line;">
                    <?= !empty($rework['corrective_action']) ? e($rework['corrective_action']) : '<span class="text-muted">No specific corrective procedure documented for this repair order.</span>' ?>
                </div>
            </div>

            <div class="rwk-callout-box mb-0">
                <div class="rwk-callout-title">
                    QC &amp; Floor Supervisor Remarks
                </div>
                <div style="font-size:0.95rem;line-height:1.5;white-space:pre-line;">
                    <?= !empty($rework['remarks']) ? e($rework['remarks']) : '<span class="text-muted">No floor remarks logged.</span>' ?>
                </div>
            </div>
        </div>
        <div class="card-footer" style="background:#fafafa;padding:0.85rem 1.25rem;border-top:1px solid var(--color-border);font-size:0.8rem;color:var(--color-text-muted);display:flex;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <div>
                Created by <strong><?= e($rework['creator_name'] ?: $rework['creator_username']) ?></strong>
                (<?= e(ucwords(str_replace('_', ' ', $rework['creator_role']))) ?>) on <?= e(date('M d, Y H:i', strtotime($rework['created_at']))) ?>
            </div>
            <?php if (!empty($rework['updated_by']) && !empty($rework['updater_name'])): ?>
                <div>
                    Last updated by <strong><?= e($rework['updater_name'] ?: $rework['updater_username']) ?></strong>
                    (<?= e(ucwords(str_replace('_', ' ', $rework['updater_role']))) ?>) on <?= e(date('M d, Y H:i', strtotime($rework['updated_at']))) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
