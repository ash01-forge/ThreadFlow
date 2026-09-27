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

$badgeClass = 'badge-secondary';
if ($status === 'Packed') $badgeClass = 'badge-info';
elseif ($status === 'In Transit') $badgeClass = 'badge-warning';
elseif ($status === 'Shipped') $badgeClass = 'badge-primary';
elseif ($status === 'Delivered') $badgeClass = 'badge-success';
elseif ($status === 'Cancelled') $badgeClass = 'badge-danger';

$methodLabelMap = [
    'sea'     => '🚢 Sea Freight',
    'air'     => '✈ Air Freight',
    'road'    => '🚚 Road Transport',
    'courier' => '📦 Courier Express',
];
$methodKey = strtolower($shipment['shipping_method'] ?? '');
$methodLabel = $methodLabelMap[$methodKey] ?? $shipment['shipping_method'];
?>

<style>
.ps-view-container { max-width: 1080px; margin: 0 auto; }
.ps-hero {
    background: linear-gradient(135deg, #fff 0%, #FFFDFD 100%);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: var(--shadow-sm);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1.25rem;
}
.ps-status-pipeline {
    display: flex;
    align-items: center;
    gap: 0;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 0.85rem 1.25rem;
    box-shadow: var(--shadow-sm);
}
.ps-pipeline-step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    border-radius: var(--radius-sm);
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--color-text-muted);
}
.ps-pipeline-step.active {
    background: var(--color-primary);
    color: #fff;
}
.ps-pipeline-step.done {
    color: #166534;
    background: #DCFCE7;
}
.ps-pipeline-step.cancelled {
    color: #991B1B;
    background: #FEE2E2;
}
.ps-pipeline-arrow {
    color: var(--color-text-muted);
    font-size: 0.9rem;
    padding: 0 0.25rem;
}
.ps-metrics-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.ps-metric-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1rem;
    box-shadow: var(--shadow-sm);
    text-align: center;
}
.ps-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 1.5rem;
}
.ps-dossier-table th {
    width: 40%;
    color: var(--color-text-muted);
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #f1f1f1;
    background: #fafafa;
}
.ps-dossier-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #f1f1f1;
    font-size: 0.9rem;
}
.ps-action-toolbar {
    background: #FFFDFD;
    border: 1px solid #FECACA;
    border-left: 4px solid var(--color-primary);
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.method-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    background: #F3F4F6;
    color: #374151;
    border: 1px solid #E5E7EB;
}
.method-badge.sea { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
.method-badge.air { background: #FAF5FF; color: #7E22CE; border-color: #E9D5FF; }
.method-badge.road { background: #FEFCE8; color: #854D0E; border-color: #FEF08A; }
.method-badge.courier { background: #FFF7ED; color: #C2410C; border-color: #FFEDD5; }
</style>

<div class="ps-view-container">
    <div class="page-header">
        <div class="page-title-group">
            <h1>Packing &amp; Shipment Dossier</h1>
            <div class="page-subtitle">Outbound logistics record &bull; Record #<?= (int)$shipment['packing_id'] ?></div>
        </div>
        <div class="page-actions">
            <a href="<?= e(url('modules/packing_shipment/index.php')) ?>" class="btn btn-secondary">
                Back to Shipments
            </a>
            <?php if (!$isTerminal): ?>
                <a href="<?= e(url('modules/packing_shipment/edit.php?id=' . $shipment['packing_id'])) ?>" class="btn btn-primary">
                    Edit Shipment
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Banner -->
    <div class="ps-hero">
        <div>
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.35rem;">
                <span class="font-mono" style="font-size:1.4rem;font-weight:800;color:var(--color-primary);"><?= e($shipment['packing_number']) ?></span>
                <span class="badge <?= $badgeClass ?>" style="font-size:0.85rem;padding:0.35rem 0.75rem;"><?= e($status) ?></span>
                <span class="method-badge <?= e($methodKey) ?>"><?= e($methodLabel) ?></span>
            </div>
            <div class="text-sm text-muted">
                Batch <strong><a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$shipment['batch_id'])) ?>"><?= e($shipment['batch_number']) ?></a></strong>
                &bull; Style <strong><?= e($shipment['style_number']) ?></strong>
                &bull; Buyer <strong><?= e($shipment['buyer_name']) ?></strong>
                <?php if (!empty($shipment['buyer_country'])): ?>
                    (<?= e($shipment['buyer_country']) ?>)
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align:right;">
            <div class="text-xs text-muted" style="font-weight:600;text-transform:uppercase;">Source QC Inspection</div>
            <div>
                <a href="<?= e(url('modules/quality_inspections/view.php?id=' . (int)$shipment['inspection_id'])) ?>" style="font-weight:700;color:var(--color-primary);font-size:1.05rem;">
                    <?= e($shipment['inspection_number']) ?>
                </a>
            </div>
            <div class="text-xs text-muted"><?= e($shipment['inspection_stage']) ?> &bull; <?= e(date('M d, Y', strtotime($shipment['inspection_date']))) ?></div>
        </div>
    </div>

    <!-- Status Pipeline Visual -->
    <?php
    $pipeline = ['Packed', 'In Transit', 'Shipped', 'Delivered'];
    $currentIdx = array_search($status, $pipeline);
    ?>
    <div class="ps-status-pipeline">
        <span style="font-size:0.78rem;font-weight:700;text-transform:uppercase;color:var(--color-text-muted);margin-right:0.75rem;">Lifecycle:</span>
        <?php foreach ($pipeline as $idx => $step): ?>
            <?php
            if ($status === 'Cancelled') {
                $stepClass = $idx < $currentIdx ? 'done' : '';
                if ($step === $status) $stepClass = 'cancelled';
            } else {
                if ($idx < (int)$currentIdx) $stepClass = 'done';
                elseif ($idx === (int)$currentIdx) $stepClass = 'active';
                else $stepClass = '';
            }
            ?>
            <?php if ($idx > 0): ?>
                <span class="ps-pipeline-arrow">&rsaquo;</span>
            <?php endif; ?>
            <span class="ps-pipeline-step <?= $stepClass ?>">
                <?= $idx < (int)$currentIdx ? '✓ ' : '' ?><?= e($step) ?>
            </span>
        <?php endforeach; ?>
        <?php if ($status === 'Cancelled'): ?>
            <span class="ps-pipeline-arrow">&rsaquo;</span>
            <span class="ps-pipeline-step cancelled">✗ Cancelled</span>
        <?php endif; ?>
    </div>

    <!-- Metrics Bar -->
    <div class="ps-metrics-bar">
        <div class="ps-metric-card">
            <div class="text-xs text-muted" style="font-weight:700;letter-spacing:0.04em;">PACKED QUANTITY</div>
            <div style="font-size:1.75rem;font-weight:800;color:var(--color-primary);margin:0.3rem 0;"><?= number_format((int)$shipment['packed_quantity']) ?></div>
            <div class="text-xs text-muted">garment pieces</div>
        </div>
        <div class="ps-metric-card">
            <div class="text-xs text-muted" style="font-weight:700;letter-spacing:0.04em;">TOTAL CARTONS</div>
            <div style="font-size:1.75rem;font-weight:800;color:#854D0E;margin:0.3rem 0;"><?= number_format((int)$shipment['carton_count']) ?></div>
            <div class="text-xs text-muted">outer boxes</div>
        </div>
        <div class="ps-metric-card">
            <div class="text-xs text-muted" style="font-weight:700;letter-spacing:0.04em;">QC PASSED TOTAL</div>
            <div style="font-size:1.75rem;font-weight:800;color:#166534;margin:0.3rem 0;"><?= number_format((int)$shipment['passed_quantity']) ?></div>
            <div class="text-xs text-muted">certified units</div>
        </div>
        <div class="ps-metric-card">
            <div class="text-xs text-muted" style="font-weight:700;letter-spacing:0.04em;">REMAINING CAPACITY</div>
            <div style="font-size:1.75rem;font-weight:800;color:#1E40AF;margin:0.3rem 0;"><?= number_format((int)$shipment['inspection_available_quantity']) ?></div>
            <div class="text-xs text-muted">pcs still packable</div>
        </div>
    </div>

    <!-- Status Action Toolbar -->
    <?php if (!empty($allowedNextStatuses)): ?>
        <div class="ps-action-toolbar">
            <div>
                <strong>Advance Shipment Status:</strong>
                Currently <strong><?= e($status) ?></strong>. Choose the next authorized transition:
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
                <?php foreach ($allowedNextStatuses as $nextSt): ?>
                    <?php if ($nextSt === 'Cancelled'): ?>
                        <form method="POST" action="<?= e(url('modules/packing_shipment/update_status.php')) ?>" onsubmit="return confirm('CANCEL this shipment? This will release its quantity allocation and cannot be undone.');" style="display:inline-block;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="packing_id" value="<?= e((string)$shipment['packing_id']) ?>">
                            <input type="hidden" name="next_status" value="Cancelled">
                            <button type="submit" class="btn btn-sm" style="background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;font-weight:600;">
                                ✗ Cancel Shipment
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= e(url('modules/packing_shipment/update_status.php')) ?>" style="display:inline-block;">
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
    <?php elseif ($isTerminal): ?>
        <div class="alert <?= $status === 'Delivered' ? 'alert-success' : 'alert-danger' ?> mb-4">
            <strong>Terminal Status:</strong> This shipment is <?= e($status) ?>.
            <?= $status === 'Delivered' ? 'All packages have been received at the destination.' : 'This shipment was cancelled and its quantity allocation has been released.' ?>
        </div>
    <?php endif; ?>

    <!-- Two-Column Details Grid -->
    <div class="ps-grid-2">
        <!-- Left: Logistics & Freight Details -->
        <div class="card mb-0">
            <div class="card-header">
                <h2>Logistics &amp; Freight Specifications</h2>
                <span class="badge badge-muted">Outbound</span>
            </div>
            <div class="card-body p-0">
                <table class="table ps-dossier-table mb-0">
                    <tbody>
                        <tr>
                            <th>Packing Number</th>
                            <td><span class="font-mono" style="font-weight:700;"><?= e($shipment['packing_number']) ?></span></td>
                        </tr>
                        <tr>
                            <th>Current Status</th>
                            <td><span class="badge <?= $badgeClass ?>"><?= e($status) ?></span></td>
                        </tr>
                        <tr>
                            <th>Packed Quantity</th>
                            <td>
                                <strong style="font-size:1.05rem;color:var(--color-primary);"><?= e(number_format((int)$shipment['packed_quantity'])) ?> pcs</strong>
                                <?php if ($status === 'Cancelled'): ?>
                                    <span class="badge badge-danger text-xs ml-1">Released</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Cartons</th>
                            <td><strong><?= e(number_format((int)$shipment['carton_count'])) ?></strong> outer boxes</td>
                        </tr>
                        <tr>
                            <th>Packing Date</th>
                            <td><?= e(date('M d, Y', strtotime($shipment['packing_date']))) ?></td>
                        </tr>
                        <tr>
                            <th>Shipping Date</th>
                            <td>
                                <?php if (!empty($shipment['shipping_date'])): ?>
                                    <strong><?= e(date('M d, Y', strtotime($shipment['shipping_date']))) ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">Not yet dispatched</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Shipping Method</th>
                            <td><span class="method-badge <?= e($methodKey) ?>"><?= e($methodLabel) ?></span></td>
                        </tr>
                        <tr>
                            <th>Carrier / Freight Op.</th>
                            <td><?= !empty($shipment['carrier_name']) ? e($shipment['carrier_name']) : '<span class="text-muted">Not assigned</span>' ?></td>
                        </tr>
                        <tr>
                            <th>Tracking / B/L Number</th>
                            <td>
                                <?php if (!empty($shipment['tracking_number'])): ?>
                                    <code class="font-mono"><?= e($shipment['tracking_number']) ?></code>
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
            <?php if (!empty($shipment['notes'])): ?>
            <div class="card-footer" style="background:#FFFDFD;padding:1rem 1.15rem;border-top:1px solid var(--color-border);">
                <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;color:var(--color-text-muted);margin-bottom:0.35rem;">Handling Notes</div>
                <div style="font-size:0.9rem;white-space:pre-wrap;"><?= e($shipment['notes']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Batch, QC Pedigree & Audit Trail -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            <!-- Production & Buyer Context -->
            <div class="card mb-0">
                <div class="card-header">
                    <h2>Production &amp; Buyer Context</h2>
                    <span class="badge badge-primary">Traceability</span>
                </div>
                <div class="card-body p-0">
                    <table class="table ps-dossier-table mb-0">
                        <tbody>
                            <tr>
                                <th>Buyer</th>
                                <td>
                                    <strong><?= e($shipment['buyer_name']) ?></strong>
                                    <?php if (!empty($shipment['buyer_country'])): ?>
                                        <div class="text-xs text-muted"><?= e($shipment['buyer_country']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Production Order</th>
                                <td>
                                    <strong><?= e($shipment['order_number']) ?></strong>
                                    <div class="text-xs text-muted">Total: <?= e(number_format((int)$shipment['order_total_qty'])) ?> pcs</div>
                                </td>
                            </tr>
                            <tr>
                                <th>Style Number</th>
                                <td><code class="font-mono"><?= e($shipment['style_number']) ?></code></td>
                            </tr>
                            <tr>
                                <th>Production Batch</th>
                                <td>
                                    <strong><a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$shipment['batch_id'])) ?>"><?= e($shipment['batch_number']) ?></a></strong>
                                    <div class="text-xs text-muted">Planned: <?= e(number_format((int)$shipment['batch_planned_qty'])) ?> pcs &bull; <?= e($shipment['batch_status']) ?></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- QC Inspection Pedigree -->
            <div class="card mb-0">
                <div class="card-header">
                    <h2>QC Inspection Pedigree</h2>
                    <span class="badge badge-success">Audit Authorized</span>
                </div>
                <div class="card-body p-0">
                    <table class="table ps-dossier-table mb-0">
                        <tbody>
                            <tr>
                                <th>Inspection #</th>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/quality_inspections/view.php?id=' . (int)$shipment['inspection_id'])) ?>">
                                            <?= e($shipment['inspection_number']) ?>
                                        </a>
                                    </strong>
                                    <div class="text-xs text-muted">Stage: <?= e($shipment['inspection_stage']) ?></div>
                                </td>
                            </tr>
                            <tr>
                                <th>Inspection Date</th>
                                <td><?= e(date('M d, Y', strtotime($shipment['inspection_date']))) ?></td>
                            </tr>
                            <tr>
                                <th>QC Passed Quantity</th>
                                <td><strong style="color:#166534;"><?= e(number_format((int)$shipment['passed_quantity'])) ?> pcs</strong></td>
                            </tr>
                            <tr>
                                <th>Total Packed (QC)</th>
                                <td><?= e(number_format((int)$shipment['inspection_total_packed'])) ?> pcs</td>
                            </tr>
                            <tr>
                                <th>Remaining Available</th>
                                <td><span class="badge badge-success"><?= e(number_format((int)$shipment['inspection_available_quantity'])) ?> pcs</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- System Audit Trail -->
            <div class="card mb-0">
                <div class="card-header">
                    <h2>System Audit Trail</h2>
                    <span class="badge badge-muted">Metadata</span>
                </div>
                <div class="card-body" style="font-size:0.85rem;">
                    <div style="margin-bottom:0.75rem;">
                        <div class="text-xs text-muted" style="font-weight:600;text-transform:uppercase;margin-bottom:0.25rem;">Created By</div>
                        <div><strong><?= e($shipment['creator_name'] ?: $shipment['creator_username']) ?></strong></div>
                        <div class="text-muted"><?= e(date('M d, Y H:i', strtotime($shipment['created_at']))) ?></div>
                    </div>
                    <?php if (!empty($shipment['updated_by']) && !empty($shipment['updater_name'])): ?>
                        <div style="border-top:1px solid var(--color-border);padding-top:0.75rem;">
                            <div class="text-xs text-muted" style="font-weight:600;text-transform:uppercase;margin-bottom:0.25rem;">Last Modified By</div>
                            <div><strong><?= e($shipment['updater_name'] ?: $shipment['updater_username']) ?></strong></div>
                            <div class="text-muted"><?= e(date('M d, Y H:i', strtotime($shipment['updated_at']))) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
