<?php
/**
 * ThreadFlow - Production Batch Dossier View
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Validate positive integer ID
$batchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$batchId || $batchId <= 0) {
    flash_set('error', 'Invalid production batch ID specified.');
    redirect('modules/production_batches/index.php');
}

// Fetch batch with related order, buyer, and sewing line using JOINs
$batch = null;
try {
    $sql = "SELECT 
                pb.batch_id,
                pb.batch_number,
                pb.order_id,
                pb.line_id,
                pb.planned_quantity,
                pb.status,
                pb.start_date,
                pb.target_end_date,
                pb.notes,
                pb.created_at,
                pb.updated_at,
                bo.order_number,
                bo.style_number,
                bo.total_quantity AS order_total_quantity,
                bo.order_date,
                bo.delivery_date,
                bo.status AS order_status,
                b.buyer_id,
                b.buyer_name,
                b.contact_person AS buyer_contact,
                b.email AS buyer_email,
                b.phone AS buyer_phone,
                b.country AS buyer_country,
                sl.line_name,
                sl.line_code,
                sl.daily_capacity
            FROM production_batches pb
            INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
            INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
            LEFT JOIN sewing_lines sl ON pb.line_id = sl.line_id
            WHERE pb.batch_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare SELECT for batch view: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving batch details.');
        redirect('modules/production_batches/index.php');
    }

    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $batch = $result->fetch_assoc();
    $stmt->close();

    if (!$batch) {
        flash_set('error', 'Production batch record not found.');
        redirect('modules/production_batches/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching batch details: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading batch details.');
    redirect('modules/production_batches/index.php');
}

$pageTitle = 'Batch: ' . $batch['batch_number'];
$currentNav = 'batches';

// Calculate timeline duration
$startTs = strtotime($batch['start_date']);
$endTs = strtotime($batch['target_end_date']);
$durationDays = ($startTs && $endTs) ? max(1, round(($endTs - $startTs) / 86400) + 1) : null;

// Status styling
$badgeClass = 'badge-muted';
if ($batch['status'] === 'Planned') $badgeClass = 'badge-info';
elseif ($batch['status'] === 'In Progress') $badgeClass = 'badge-primary';
elseif ($batch['status'] === 'On Hold') $badgeClass = 'badge-warning';
elseif ($batch['status'] === 'Completed') $badgeClass = 'badge-success';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Production Batch Dossier</h1>
        <div class="page-subtitle">Batch #<?= (int) $batch['batch_id'] ?> &bull; <?= e($batch['batch_number']) ?> &bull; Order <?= e($batch['order_number']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-secondary">Back to Batch List</a>
        <a href="<?= e(url('modules/production_batches/edit.php?id=' . (int)$batch['batch_id'])) ?>" class="btn btn-secondary">Edit Batch</a>
    </div>
</div>

<div class="card card-medium">
    <div class="card-header">
        <h2>Batch <?= e($batch['batch_number']) ?></h2>
        <div>
            <span class="badge <?= e($badgeClass) ?>"><?= e($batch['status']) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 220px;">Batch Number</th>
                        <td><strong><?= e($batch['batch_number']) ?></strong> (ID #<?= (int) $batch['batch_id'] ?>)</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge <?= e($badgeClass) ?>"><?= e($batch['status']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Planned Quantity</th>
                        <td>
                            <strong><?= number_format((int) $batch['planned_quantity']) ?> pieces</strong>
                            <?php if ((int)$batch['order_total_quantity'] > 0): ?>
                                <span class="text-muted text-sm ml-2">
                                    (<?= round(((int)$batch['planned_quantity'] / (int)$batch['order_total_quantity']) * 100, 1) ?>% of total order)
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Production Timeline</th>
                        <td>
                            <div>
                                <strong>Start:</strong> <?= e(date('F j, Y', strtotime($batch['start_date']))) ?>
                            </div>
                            <div>
                                <strong>Target End:</strong> <?= e(date('F j, Y', strtotime($batch['target_end_date']))) ?>
                                <?php if ($durationDays): ?>
                                    <span class="text-muted text-sm ml-1">(<?= (int)$durationDays ?> calendar days allocated)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Related Buyer</th>
                        <td>
                            <strong><?= e($batch['buyer_name']) ?></strong>
                            <?php if (!empty($batch['buyer_country'])): ?>
                                <span class="text-muted text-sm">(<?= e($batch['buyer_country']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($batch['buyer_contact'])): ?>
                                <div class="text-sm text-muted">Contact: <?= e($batch['buyer_contact']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($batch['buyer_email'])): ?>
                                <div class="text-sm"><a href="mailto:<?= e($batch['buyer_email']) ?>"><?= e($batch['buyer_email']) ?></a></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Buyer Order Reference</th>
                        <td>
                            <div>
                                <strong>Order Number:</strong> <?= e($batch['order_number']) ?>
                            </div>
                            <div>
                                <strong>Garment Style:</strong> <?= e($batch['style_number']) ?>
                            </div>
                            <div>
                                <strong>Total Order Volume:</strong> <?= number_format((int)$batch['order_total_quantity']) ?> pcs
                            </div>
                            <?php if (!empty($batch['delivery_date'])): ?>
                                <div class="text-sm text-muted">
                                    Delivery Deadline: <?= e(date('F j, Y', strtotime($batch['delivery_date']))) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Floor Line Allocation</th>
                        <td>
                            <?php if (!empty($batch['line_name'])): ?>
                                <strong><?= e($batch['line_name']) ?></strong> (Code: <code><?= e($batch['line_code']) ?></code>)
                                <?php if ((int)$batch['daily_capacity'] > 0): ?>
                                    <div class="text-sm text-muted">Rated Capacity: <?= number_format((int)$batch['daily_capacity']) ?> pcs/day</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-muted">Unassigned</span>
                                <span class="text-muted text-sm ml-2">Batch not yet allocated to a specific sewing line.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Production Notes</th>
                        <td>
                            <?php if (!empty($batch['notes'])): ?>
                                <?= nl2br(e($batch['notes'])) ?>
                            <?php else: ?>
                                <span class="text-muted">No specific floor notes recorded.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Record Created</th>
                        <td><?= e($batch['created_at']) ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= e($batch['updated_at']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
