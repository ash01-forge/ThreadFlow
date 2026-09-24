<?php
/**
 * ThreadFlow - Packing & Shipment Dossier View
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

// Validate ID
$packingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$packingId || $packingId <= 0) {
    flash_set('error', 'Invalid packing shipment ID specified.');
    redirect('modules/packing_shipment/index.php');
}

$shipment = null;
try {
    $sql = "SELECT 
                ps.packing_id,
                ps.packing_number,
                ps.batch_id,
                ps.inspection_id,
                ps.packed_quantity,
                ps.carton_count,
                ps.packing_date,
                ps.shipping_date,
                ps.shipping_method,
                ps.carrier_name,
                ps.tracking_number,
                ps.destination,
                ps.status,
                ps.notes,
                ps.created_by,
                ps.updated_by,
                ps.created_at,
                ps.updated_at,
                -- Batch information
                pb.batch_number,
                pb.planned_quantity AS batch_planned_qty,
                pb.status AS batch_status,
                -- Buyer order information (order and style)
                bo.order_id,
                bo.order_number,
                bo.style_number,
                bo.total_quantity AS order_total_qty,
                bo.delivery_date AS order_delivery_date,
                -- Buyer information
                b.buyer_id,
                b.buyer_name,
                b.country AS buyer_country,
                -- Quality inspection information
                qi.inspection_number,
                qi.inspection_stage,
                qi.inspection_date,
                qi.inspected_quantity,
                qi.passed_quantity,
                qi.overall_result AS inspection_result,
                -- Creator information
                uc.full_name AS creator_name,
                uc.username AS creator_username,
                uc.role AS creator_role,
                -- Updater information
                uu.full_name AS updater_name,
                uu.username AS updater_username,
                uu.role AS updater_role,
                -- Correlated Subquery 1: Already packed non-cancelled quantity for this inspection
                (
                    SELECT COALESCE(SUM(ps2.packed_quantity), 0)
                    FROM packing_shipments ps2
                    WHERE ps2.inspection_id = ps.inspection_id
                      AND ps2.status != 'Cancelled'
                ) AS inspection_total_packed,
                -- Correlated Subquery 2: Available remaining quantity for this inspection
                (
                    qi.passed_quantity - (
                        SELECT COALESCE(SUM(ps2.packed_quantity), 0)
                        FROM packing_shipments ps2
                        WHERE ps2.inspection_id = ps.inspection_id
                          AND ps2.status != 'Cancelled'
                    )
                ) AS inspection_available_quantity
            FROM packing_shipments ps
            INNER JOIN production_batches pb ON ps.batch_id = pb.batch_id
            INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
            INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
            INNER JOIN quality_inspections qi ON ps.inspection_id = qi.inspection_id
            INNER JOIN users uc ON ps.created_by = uc.user_id
            LEFT JOIN users uu ON ps.updated_by = uu.user_id
            WHERE ps.packing_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare packing shipment view query: " . $conn->error);
        flash_set('error', 'A database error occurred while loading shipment details.');
        redirect('modules/packing_shipment/index.php');
    }

    $stmt->bind_param("i", $packingId);
    $stmt->execute();
    $shipment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$shipment) {
        flash_set('error', 'Packing shipment record not found.');
        redirect('modules/packing_shipment/index.php');
    }

} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading packing shipment view: " . $e->getMessage());
    flash_set('error', 'An error occurred while loading shipment details.');
    redirect('modules/packing_shipment/index.php');
}

$pageTitle = 'Shipment ' . $shipment['packing_number'];
$currentNav = 'packing_shipment';

$status = $shipment['status'];
$isTerminal = in_array($status, ['Delivered', 'Cancelled'], true);

// Allowed status transitions for quick actions
$allowedNextStatuses = [];
if ($status === 'Packed') {
    $allowedNextStatuses = ['In Transit', 'Cancelled'];
} elseif ($status === 'In Transit') {
    $allowedNextStatuses = ['Shipped', 'Cancelled'];
} elseif ($status === 'Shipped') {
    $allowedNextStatuses = ['Delivered'];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <div class="d-flex align-items-center gap-2">
            <h1>Shipment <?= e($shipment['packing_number']) ?></h1>
            <?php
            $badgeClass = 'badge-secondary';
            if ($status === 'Packed') $badgeClass = 'badge-info';
            elseif ($status === 'In Transit') $badgeClass = 'badge-warning';
            elseif ($status === 'Shipped') $badgeClass = 'badge-primary';
            elseif ($status === 'Delivered') $badgeClass = 'badge-success';
            elseif ($status === 'Cancelled') $badgeClass = 'badge-danger';
            ?>
            <span class="badge <?= $badgeClass ?> font-weight-bold" style="font-size: 0.95rem; padding: 0.35rem 0.65rem;">
                <?= e($status) ?>
            </span>
        </div>
        <div class="page-subtitle">
            Batch: <strong><?= e($shipment['batch_number']) ?></strong> &bull;
            Style: <strong><?= e($shipment['style_number']) ?></strong> &bull;
            Buyer: <strong><?= e($shipment['buyer_name']) ?></strong>
        </div>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= e(url('modules/packing_shipment/index.php')) ?>" class="btn btn-secondary">
            &larr; Back to Directory
        </a>
        <?php if (!$isTerminal): ?>
            <a href="<?= e(url('modules/packing_shipment/edit.php?id=' . $shipment['packing_id'])) ?>" class="btn btn-primary">
                Edit Shipment
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Status Progression Toolbar -->
<?php if (!empty($allowedNextStatuses)): ?>
    <div class="card mb-4" style="border-left: 4px solid var(--color-primary); background: #fdf8f9;">
        <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <strong>Status Management:</strong> Current status is <strong><?= e($status) ?></strong>.
                Advance this shipment through the authorized delivery workflow:
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                <?php foreach ($allowedNextStatuses as $nextSt): ?>
                    <?php if ($nextSt === 'Cancelled'): ?>
                        <form method="POST" action="<?= e(url('modules/packing_shipment/update_status.php')) ?>" onsubmit="return confirm('Are you sure you want to CANCEL this shipment? Cancelled records cannot be undone and will release their quantity.');" style="display: inline-block;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="packing_id" value="<?= e((string)$shipment['packing_id']) ?>">
                            <input type="hidden" name="next_status" value="Cancelled">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Shipment</button>
                        </form>
                    <?php else: ?>
                        <!-- Trigger modal or inline form for non-cancelled status -->
                        <form method="POST" action="<?= e(url('modules/packing_shipment/update_status.php')) ?>" style="display: inline-block;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="packing_id" value="<?= e((string)$shipment['packing_id']) ?>">
                            <input type="hidden" name="next_status" value="<?= e($nextSt) ?>">
                            <?php if (in_array($nextSt, ['Shipped', 'Delivered'], true) && empty($shipment['shipping_date'])): ?>
                                <input type="hidden" name="shipping_date" value="<?= e(date('Y-m-d')) ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-success">
                                Mark as <?= e($nextSt) ?> &rarr;
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php elseif ($isTerminal): ?>
    <div class="alert <?= $status === 'Delivered' ? 'alert-success' : 'alert-danger' ?> mb-4">
        <strong>Terminal Status Notice:</strong> This shipment is marked as <strong><?= e($status) ?></strong>. 
        <?= $status === 'Delivered' ? 'All packages have been successfully received at the destination.' : 'This shipment was cancelled. It no longer consumes inventory capacity and cannot be reactivated.' ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Left Column: Packaging & Logistics Details -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <h2>Packaging &amp; Dispatch Logistics</h2>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr>
                            <th style="width: 35%;">Packing Number</th>
                            <td><strong><?= e($shipment['packing_number']) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Packed Quantity</th>
                            <td>
                                <strong class="text-primary" style="font-size: 1.15rem;"><?= e(number_format((int)$shipment['packed_quantity'])) ?></strong> pcs
                                <?php if ($status === 'Cancelled'): ?>
                                    <span class="badge badge-danger ml-2">Capacity Released</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Cartons</th>
                            <td><strong><?= e(number_format((int)$shipment['carton_count'])) ?></strong> cartons</td>
                        </tr>
                        <tr>
                            <th>Packing Date</th>
                            <td><?= e($shipment['packing_date']) ?></td>
                        </tr>
                        <tr>
                            <th>Shipping Date</th>
                            <td>
                                <?php if (!empty($shipment['shipping_date'])): ?>
                                    <span class="badge badge-light font-weight-bold"><?= e($shipment['shipping_date']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Not yet dispatched</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Shipping Method</th>
                            <td><span class="badge badge-secondary"><?= e($shipment['shipping_method']) ?></span></td>
                        </tr>
                        <tr>
                            <th>Carrier / Freight</th>
                            <td><?= e($shipment['carrier_name'] ?: 'Not assigned') ?></td>
                        </tr>
                        <tr>
                            <th>Tracking / B/L Number</th>
                            <td>
                                <?php if (!empty($shipment['tracking_number'])): ?>
                                    <code><?= e($shipment['tracking_number']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">Pending issuance</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Destination</th>
                            <td><strong><?= e($shipment['destination']) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3>Notes &amp; Handling Instructions</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($shipment['notes'])): ?>
                    <p class="mb-0" style="white-space: pre-wrap;"><?= e($shipment['notes']) ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">No special instructions or notes recorded for this shipment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Production Batch, Quality Inspection & Audit Trail -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">
                <h2>Production &amp; Buyer Context</h2>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr>
                            <th style="width: 40%;">Buyer</th>
                            <td>
                                <strong><?= e($shipment['buyer_name']) ?></strong>
                                <?php if (!empty($shipment['buyer_country'])): ?>
                                    <div class="small text-muted"><?= e($shipment['buyer_country']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Production Order</th>
                            <td>
                                <strong><?= e($shipment['order_number']) ?></strong>
                                <div class="small text-muted">Total: <?= e(number_format((int)$shipment['order_total_qty'])) ?> pcs</div>
                            </td>
                        </tr>
                        <tr>
                            <th>Garment Style</th>
                            <td><code><?= e($shipment['style_number']) ?></code></td>
                        </tr>
                        <tr>
                            <th>Production Batch</th>
                            <td>
                                <strong><?= e($shipment['batch_number']) ?></strong>
                                <div class="small text-muted">Planned: <?= e(number_format((int)$shipment['batch_planned_qty'])) ?> pcs &bull; Status: <?= e($shipment['batch_status']) ?></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2>QC Inspection Pedigree</h2>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr>
                            <th style="width: 40%;">QC Inspection #</th>
                            <td>
                                <strong><?= e($shipment['inspection_number']) ?></strong>
                                <div class="small text-muted">Stage: <?= e($shipment['inspection_stage']) ?></div>
                            </td>
                        </tr>
                        <tr>
                            <th>QC Inspection Date</th>
                            <td><?= e($shipment['inspection_date']) ?></td>
                        </tr>
                        <tr>
                            <th>Passed Quantity</th>
                            <td><strong class="text-success"><?= e(number_format((int)$shipment['passed_quantity'])) ?></strong> pcs</td>
                        </tr>
                        <tr>
                            <th>Total Packed for QC</th>
                            <td><?= e(number_format((int)$shipment['inspection_total_packed'])) ?> pcs</td>
                        </tr>
                        <tr>
                            <th>Remaining Available</th>
                            <td>
                                <span class="badge badge-success font-weight-bold">
                                    <?= e(number_format((int)$shipment['inspection_available_quantity'])) ?> pcs
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3>Audit Trail</h3>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2">
                        <strong>Created By:</strong> <?= e($shipment['creator_name']) ?> (<code><?= e($shipment['creator_username']) ?></code>)
                        <div class="text-muted"><?= e($shipment['created_at']) ?></div>
                    </li>
                    <?php if (!empty($shipment['updated_by']) && !empty($shipment['updater_name'])): ?>
                        <li>
                            <strong>Last Modified By:</strong> <?= e($shipment['updater_name']) ?> (<code><?= e($shipment['updater_username']) ?></code>)
                            <div class="text-muted"><?= e($shipment['updated_at']) ?></div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
