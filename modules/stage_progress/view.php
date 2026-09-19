<?php
/**
 * ThreadFlow - Stage Progress Dossier View
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Validate positive integer ID
$progressId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$progressId || $progressId <= 0) {
    flash_set('error', 'Invalid stage progress record ID specified.');
    redirect('modules/stage_progress/index.php');
}

// Fetch stage progress record with all associated relations
$record = null;
try {
    $sql = "SELECT 
                sp.progress_id,
                sp.batch_id,
                sp.stage_id,
                sp.input_quantity,
                sp.output_quantity,
                sp.start_time,
                sp.end_time,
                sp.status,
                sp.remarks,
                sp.created_at,
                sp.updated_at,
                pb.batch_number,
                pb.planned_quantity,
                pb.status AS batch_status,
                pb.start_date AS batch_start_date,
                pb.target_end_date AS batch_target_end_date,
                bo.order_id,
                bo.order_number,
                bo.style_number,
                bo.total_quantity AS order_total_quantity,
                b.buyer_id,
                b.buyer_name,
                b.contact_person AS buyer_contact,
                b.email AS buyer_email,
                b.country AS buyer_country,
                sl.line_name,
                sl.line_code,
                sl.daily_capacity,
                ps.stage_name,
                ps.stage_code,
                ps.sequence_number
            FROM stage_progress sp
            INNER JOIN production_batches pb ON sp.batch_id = pb.batch_id
            INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
            INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
            LEFT JOIN sewing_lines sl ON pb.line_id = sl.line_id
            INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id
            WHERE sp.progress_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare stage_progress view query: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving stage progress details.');
        redirect('modules/stage_progress/index.php');
    }

    $stmt->bind_param("i", $progressId);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();

    if (!$record) {
        flash_set('error', 'Stage progress record not found.');
        redirect('modules/stage_progress/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching stage progress details: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading record details.');
    redirect('modules/stage_progress/index.php');
}

// Fetch all stages for this batch to render the batch stage progression overview
$batchStages = [];
try {
    $chainSql = "SELECT 
                    ps.stage_id,
                    ps.stage_name,
                    ps.stage_code,
                    ps.sequence_number,
                    sp.progress_id,
                    sp.status AS progress_status,
                    sp.input_quantity,
                    sp.output_quantity,
                    sp.start_time,
                    sp.end_time
                 FROM production_stages ps
                 LEFT JOIN stage_progress sp 
                    ON ps.stage_id = sp.stage_id AND sp.batch_id = ?
                 WHERE ps.is_active = 1
                 ORDER BY ps.sequence_number ASC";
    $cStmt = $conn->prepare($chainSql);
    if ($cStmt) {
        $cStmt->bind_param("i", $record['batch_id']);
        $cStmt->execute();
        $batchStages = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $cStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching batch stage chain: " . $e->getMessage());
}

$pageTitle = 'Stage Progress: ' . $record['stage_name'] . ' (' . $record['batch_number'] . ')';
$currentNav = 'stage_progress';

// Status badge styling
$badgeClass = 'badge-muted';
if ($record['status'] === 'In Progress') $badgeClass = 'badge-primary';
elseif ($record['status'] === 'On Hold') $badgeClass = 'badge-warning';
elseif ($record['status'] === 'Completed') $badgeClass = 'badge-success';

// Calculation metrics
$inputQty   = (int) $record['input_quantity'];
$outputQty  = (int) $record['output_quantity'];
$plannedQty = (int) $record['planned_quantity'];
$lossQty    = max(0, $inputQty - $outputQty);
$yieldPct   = ($inputQty > 0) ? round(($outputQty / $inputQty) * 100, 1) : 0;
$lossPct    = ($inputQty > 0) ? round(($lossQty / $inputQty) * 100, 1) : 0;
$planPct    = ($plannedQty > 0) ? round(($inputQty / $plannedQty) * 100, 1) : 0;

// Duration calculation
$durationText = 'Ongoing';
if (!empty($record['end_time'])) {
    $startTs = strtotime($record['start_time']);
    $endTs   = strtotime($record['end_time']);
    if ($startTs && $endTs && $endTs >= $startTs) {
        $diffSec = $endTs - $startTs;
        $days    = floor($diffSec / 86400);
        $hours   = floor(($diffSec % 86400) / 3600);
        $minutes = floor(($diffSec % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = $days . 'd';
        if ($hours > 0) $parts[] = $hours . 'h';
        if ($minutes > 0 || empty($parts)) $parts[] = $minutes . 'm';
        $durationText = implode(' ', $parts);
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Stage Progress Dossier</h1>
        <div class="page-subtitle">
            Progress #<?= (int)$record['progress_id'] ?> &bull; 
            Stage <?= (int)$record['sequence_number'] ?>: <?= e($record['stage_name']) ?> &bull; 
            Batch <?= e($record['batch_number']) ?>
        </div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/stage_progress/index.php')) ?>" class="btn btn-secondary">Back to List</a>
        <a href="<?= e(url('modules/stage_progress/edit.php?id=' . (int)$record['progress_id'])) ?>" class="btn btn-secondary">Edit Record</a>
        <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$record['batch_id'])) ?>" class="btn btn-secondary">View Batch</a>
    </div>
</div>

<!-- Batch Stage Workflow Tracker -->
<?php if (!empty($batchStages)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3>Batch Workflow Progression: <?= e($record['batch_number']) ?></h3>
            <span class="badge badge-muted">Batch Planned: <?= number_format($plannedQty) ?> pcs</span>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <?php foreach ($batchStages as $bs): ?>
                    <?php
                    $isCurrent = ((int)$bs['stage_id'] === (int)$record['stage_id']);
                    $stBadgeClass = 'badge-muted';
                    $stLabel = 'Not Started';

                    if (!empty($bs['progress_id'])) {
                        $stLabel = $bs['progress_status'];
                        if ($bs['progress_status'] === 'Completed') $stBadgeClass = 'badge-success';
                        elseif ($bs['progress_status'] === 'In Progress') $stBadgeClass = 'badge-primary';
                        elseif ($bs['progress_status'] === 'On Hold') $stBadgeClass = 'badge-warning';
                    }
                    ?>
                    <div style="border: 2px solid <?= $isCurrent ? '#78000F' : '#E5C3C7' ?>; border-radius: 8px; padding: 0.85rem; background: <?= $isCurrent ? '#FFF8F8' : '#FFFFFF' ?>;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-xs text-muted font-bold">Stage <?= (int)$bs['sequence_number'] ?></span>
                            <span class="badge <?= e($stBadgeClass) ?> text-xs"><?= e($stLabel) ?></span>
                        </div>
                        <div class="font-bold mb-1" style="color: <?= $isCurrent ? '#78000F' : 'inherit' ?>;">
                            <?= e($bs['stage_name']) ?>
                            <?php if ($isCurrent): ?>
                                <span class="badge badge-primary text-xs ml-1">Current</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($bs['progress_id'])): ?>
                            <div class="text-xs text-muted">
                                In: <?= number_format((int)$bs['input_quantity']) ?> &bull; Out: <?= number_format((int)$bs['output_quantity']) ?>
                            </div>
                            <?php if (!$isCurrent): ?>
                                <div class="mt-2">
                                    <a href="<?= e(url('modules/stage_progress/view.php?id=' . (int)$bs['progress_id'])) ?>" class="btn btn-sm btn-secondary" style="font-size: 0.72rem; padding: 2px 6px;">
                                        View Details
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-xs text-muted">Pending execution</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Detailed Dossier Table -->
<div class="card card-medium">
    <div class="card-header">
        <h2>Stage Details: <?= e($record['stage_name']) ?></h2>
        <div>
            <span class="badge <?= e($badgeClass) ?>"><?= e($record['status']) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 220px;">Progress Record ID</th>
                        <td>#<?= (int) $record['progress_id'] ?></td>
                    </tr>
                    <tr>
                        <th>Production Stage</th>
                        <td>
                            <strong><?= e($record['stage_name']) ?></strong> 
                            (Code: <code><?= e($record['stage_code']) ?></code> &bull; Sequence #<?= (int)$record['sequence_number'] ?>)
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge <?= e($badgeClass) ?>"><?= e($record['status']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Input Quantity</th>
                        <td>
                            <strong><?= number_format($inputQty) ?> pieces</strong>
                            <?php if ($plannedQty > 0): ?>
                                <span class="text-muted text-sm ml-2">
                                    (<?= e((string)$planPct) ?>% of batch planned volume)
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Output Quantity</th>
                        <td>
                            <strong><?= number_format($outputQty) ?> pieces</strong>
                            <?php if ($inputQty > 0): ?>
                                <span class="text-muted text-sm ml-2">
                                    &bull; Yield: <strong><?= e((string)$yieldPct) ?>%</strong>
                                    <?php if ($lossQty > 0): ?>
                                        &bull; Process Loss: <strong style="color: #A30014;"><?= number_format($lossQty) ?> pcs (<?= e((string)$lossPct) ?>%)</strong>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Execution Timeline</th>
                        <td>
                            <div>
                                <strong>Start Time:</strong> <?= e(date('F j, Y g:i A', strtotime($record['start_time']))) ?>
                            </div>
                            <div>
                                <strong>End Time:</strong>
                                <?php if (!empty($record['end_time'])): ?>
                                    <?= e(date('F j, Y g:i A', strtotime($record['end_time']))) ?>
                                <?php else: ?>
                                    <span class="text-muted">In Progress / Ongoing</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm text-muted mt-1">
                                <strong>Operational Duration:</strong> <?= e($durationText) ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Associated Batch</th>
                        <td>
                            <strong>
                                <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$record['batch_id'])) ?>">
                                    <?= e($record['batch_number']) ?>
                                </a>
                            </strong>
                            (Batch ID #<?= (int)$record['batch_id'] ?> &bull; Status: <?= e($record['batch_status']) ?>)
                        </td>
                    </tr>
                    <tr>
                        <th>Batch Planned Volume</th>
                        <td><?= number_format($plannedQty) ?> pieces</td>
                    </tr>
                    <tr>
                        <th>Buyer Order Reference</th>
                        <td>
                            <div><strong>Order Number:</strong> <?= e($record['order_number']) ?></div>
                            <div><strong>Garment Style:</strong> <?= e($record['style_number']) ?></div>
                            <div><strong>Total Order Volume:</strong> <?= number_format((int)$record['order_total_quantity']) ?> pcs</div>
                        </td>
                    </tr>
                    <tr>
                        <th>Related Buyer</th>
                        <td>
                            <strong><?= e($record['buyer_name']) ?></strong>
                            <?php if (!empty($record['buyer_country'])): ?>
                                <span class="text-muted text-sm">(<?= e($record['buyer_country']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($record['buyer_contact'])): ?>
                                <div class="text-sm text-muted">Contact: <?= e($record['buyer_contact']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($record['buyer_email'])): ?>
                                <div class="text-sm"><a href="mailto:<?= e($record['buyer_email']) ?>"><?= e($record['buyer_email']) ?></a></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Floor Line Allocation</th>
                        <td>
                            <?php if (!empty($record['line_name'])): ?>
                                <strong><?= e($record['line_name']) ?></strong> (<code><?= e($record['line_code']) ?></code>)
                                <?php if ((int)$record['daily_capacity'] > 0): ?>
                                    <div class="text-sm text-muted">Rated Capacity: <?= number_format((int)$record['daily_capacity']) ?> pcs/day</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-muted">Unassigned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Remarks / Process Notes</th>
                        <td>
                            <?php if (!empty($record['remarks'])): ?>
                                <?= nl2br(e($record['remarks'])) ?>
                            <?php else: ?>
                                <span class="text-muted">No process remarks recorded.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Record Created</th>
                        <td><?= e($record['created_at']) ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= e($record['updated_at']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
