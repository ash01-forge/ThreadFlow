<?php
/**
 * ThreadFlow - Edit Packing Shipment
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * Enforces field immutability for shipments beyond 'Packed' status.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$packingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$packingId || $packingId <= 0) {
    flash_set('error', 'Invalid packing shipment ID.');
    redirect('modules/packing_shipment/index.php');
}

// Retrieve and clear flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old    = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Fetch shipment details with joined metadata and capacity headroom (excluding current record)
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
                -- Joined batch and order info
                pb.batch_number,
                bo.order_number,
                bo.style_number,
                b.buyer_name,
                -- Quality inspection info
                qi.inspection_number,
                qi.inspection_stage,
                qi.inspection_date,
                qi.passed_quantity,
                -- Correlated Subquery: Other allocated non-cancelled units (excluding current record)
                (
                    SELECT COALESCE(SUM(ps2.packed_quantity), 0)
                    FROM packing_shipments ps2
                    WHERE ps2.inspection_id = ps.inspection_id
                      AND ps2.status != 'Cancelled'
                      AND ps2.packing_id != ps.packing_id
                ) AS other_packed_quantity
            FROM packing_shipments ps
            INNER JOIN production_batches pb ON ps.batch_id = pb.batch_id
            INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
            INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
            INNER JOIN quality_inspections qi ON ps.inspection_id = qi.inspection_id
            WHERE ps.packing_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare packing shipment edit query: " . $conn->error);
        flash_set('error', 'Database error loading shipment.');
        redirect('modules/packing_shipment/index.php');
    }

    $stmt->bind_param("i", $packingId);
    $stmt->execute();
    $shipment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$shipment) {
        flash_set('error', 'Shipment record not found.');
        redirect('modules/packing_shipment/index.php');
    }

} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading packing shipment for edit: " . $e->getMessage());
    flash_set('error', 'Error loading shipment record.');
    redirect('modules/packing_shipment/index.php');
}

$currentStatus = $shipment['status'];

// Check terminal states
if (in_array($currentStatus, ['Delivered', 'Cancelled'], true)) {
    flash_set('error', "Shipment is in terminal status '{$currentStatus}' and cannot be edited.");
    redirect('modules/packing_shipment/view.php?id=' . $packingId);
}

// Calculate max available headroom for this shipment
$passedQty      = (int) $shipment['passed_quantity'];
$otherAllocated = (int) $shipment['other_packed_quantity'];
$maxAvailable   = $passedQty - $otherAllocated;

// Determine field immutability: core QC fields are locked once status is no longer 'Packed'
$isCoreLocked = ($currentStatus !== 'Packed');

// Valid next status options according to strict state transition map
$statusOptions = [];
if ($currentStatus === 'Packed') {
    $statusOptions = ['Packed', 'In Transit', 'Cancelled'];
} elseif ($currentStatus === 'In Transit') {
    $statusOptions = ['In Transit', 'Shipped', 'Cancelled'];
} elseif ($currentStatus === 'Shipped') {
    $statusOptions = ['Shipped', 'Delivered'];
}

$pageTitle = 'Edit Shipment ' . $shipment['packing_number'];
$currentNav = 'packing_shipment';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Shipment <?= e($shipment['packing_number']) ?></h1>
        <div class="page-subtitle">Current Status: <strong><?= e($currentStatus) ?></strong> &bull; Batch: <?= e($shipment['batch_number']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/packing_shipment/view.php?id=' . $shipment['packing_id'])) ?>" class="btn btn-secondary">
            &larr; Back to Dossier
        </a>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger">
        <?= e($errors['general']) ?>
    </div>
<?php endif; ?>

<?php if ($isCoreLocked): ?>
    <div class="alert alert-warning mb-4">
        <strong>Field Immutability Enforced:</strong> This shipment has advanced beyond <em>Packed</em> (current status: <strong><?= e($currentStatus) ?></strong>).
        Source inspection, production batch, packing quantity, and packing date are locked and cannot be altered. Only logistics attributes and authorized status progression may be updated.
    </div>
<?php endif; ?>

<div class="card card-form" style="max-width: 900px;">
    <div class="card-header">
        <h2>Modify Shipment Parameters</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= e(url('modules/packing_shipment/update.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="packing_id" value="<?= e((string)$shipment['packing_id']) ?>">

            <!-- Row 1: Identification & Status Progression -->
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Packing Number</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        value="<?= e($shipment['packing_number']) ?>" 
                        readonly 
                        disabled 
                        style="background-color: var(--color-bg); font-weight: 600;"
                    >
                    <span class="form-text">System-wide unique packing reference (immutable).</span>
                </div>

                <div class="form-group col-md-6">
                    <label for="status">Shipment Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-control <?= isset($errors['status']) ? 'is-invalid' : '' ?>" required>
                        <?php 
                        $selectedStatus = $old['status'] ?? $currentStatus;
                        foreach ($statusOptions as $opt): 
                        ?>
                            <option value="<?= e($opt) ?>" <?= $selectedStatus === $opt ? 'selected' : '' ?>>
                                <?= e($opt) ?> <?= $opt === $currentStatus ? '(Current)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['status'])): ?>
                        <div class="invalid-feedback"><?= e($errors['status']) ?></div>
                    <?php else: ?>
                        <span class="form-text">Only strict progression allowed: Packed &rarr; In Transit &rarr; Shipped &rarr; Delivered (or Cancelled).</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 2: Source QC Inspection & Batch Context -->
            <div class="card mb-4" style="background: #fdf5f6; border-color: #ebd1d4;">
                <div class="card-body py-3">
                    <h3 style="font-size: 0.95rem; margin-bottom: 0.5rem; color: var(--color-primary);">QC &amp; Batch Authorization Context</h3>
                    <div class="row small">
                        <div class="col-md-3">
                            <div><strong>Inspection:</strong> <?= e($shipment['inspection_number']) ?></div>
                            <div><strong>QC Stage:</strong> <?= e($shipment['inspection_stage']) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div><strong>Batch:</strong> <?= e($shipment['batch_number']) ?></div>
                            <div><strong>Order:</strong> <?= e($shipment['order_number']) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div><strong>Style:</strong> <?= e($shipment['style_number']) ?></div>
                            <div><strong>Buyer:</strong> <?= e($shipment['buyer_name']) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div><strong>Passed Qty:</strong> <?= e(number_format($passedQty)) ?> pcs</div>
                            <div><strong>Other Packed:</strong> <?= e(number_format($otherAllocated)) ?> pcs</div>
                            <div><strong>Available Headroom:</strong> <span class="badge badge-success"><?= e(number_format($maxAvailable)) ?> pcs</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Quantities and Packing Date -->
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="packed_quantity">Packed Quantity (Pcs) <span class="text-danger">*</span></label>
                    <input 
                        type="number" 
                        name="packed_quantity" 
                        id="packed_quantity" 
                        class="form-control <?= isset($errors['packed_quantity']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['packed_quantity'] ?? (string)$shipment['packed_quantity']) ?>" 
                        min="1" 
                        max="<?= e((string)$maxAvailable) ?>"
                        <?= $isCoreLocked ? 'readonly disabled style="background-color: var(--color-bg);"' : 'required' ?>
                    >
                    <?php if ($isCoreLocked): ?>
                        <span class="form-text">Locked because status is beyond Packed.</span>
                    <?php elseif (isset($errors['packed_quantity'])): ?>
                        <div class="invalid-feedback"><?= e($errors['packed_quantity']) ?></div>
                    <?php else: ?>
                        <span class="form-text">Max available for this batch: <?= e(number_format($maxAvailable)) ?> pcs.</span>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="carton_count">Carton Count <span class="text-danger">*</span></label>
                    <input 
                        type="number" 
                        name="carton_count" 
                        id="carton_count" 
                        class="form-control <?= isset($errors['carton_count']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['carton_count'] ?? (string)$shipment['carton_count']) ?>" 
                        min="1" 
                        required
                    >
                    <?php if (isset($errors['carton_count'])): ?>
                        <div class="invalid-feedback"><?= e($errors['carton_count']) ?></div>
                    <?php else: ?>
                        <span class="form-text">Outer packaging cartons/boxes.</span>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="packing_date">Packing Date <span class="text-danger">*</span></label>
                    <input 
                        type="date" 
                        name="packing_date" 
                        id="packing_date" 
                        class="form-control <?= isset($errors['packing_date']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['packing_date'] ?? $shipment['packing_date']) ?>" 
                        <?= $isCoreLocked ? 'readonly disabled style="background-color: var(--color-bg);"' : 'required' ?>
                    >
                    <?php if ($isCoreLocked): ?>
                        <span class="form-text">Locked because status is beyond Packed.</span>
                    <?php elseif (isset($errors['packing_date'])): ?>
                        <div class="invalid-feedback"><?= e($errors['packing_date']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 4: Shipping Logistics -->
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="shipping_method">Shipping Method <span class="text-danger">*</span></label>
                    <select name="shipping_method" id="shipping_method" class="form-control <?= isset($errors['shipping_method']) ? 'is-invalid' : '' ?>" required>
                        <?php 
                        $curMethod = $old['shipping_method'] ?? $shipment['shipping_method'];
                        foreach (['Sea', 'Air', 'Road', 'Courier'] as $m): 
                        ?>
                            <option value="<?= e($m) ?>" <?= $curMethod === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['shipping_method'])): ?>
                        <div class="invalid-feedback"><?= e($errors['shipping_method']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="carrier_name">Carrier / Freight Operator</label>
                    <input 
                        type="text" 
                        name="carrier_name" 
                        id="carrier_name" 
                        class="form-control <?= isset($errors['carrier_name']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['carrier_name'] ?? $shipment['carrier_name'] ?? '') ?>" 
                        maxlength="100"
                    >
                    <?php if (isset($errors['carrier_name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['carrier_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="tracking_number">Tracking / Bill of Lading #</label>
                    <input 
                        type="text" 
                        name="tracking_number" 
                        id="tracking_number" 
                        class="form-control <?= isset($errors['tracking_number']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['tracking_number'] ?? $shipment['tracking_number'] ?? '') ?>" 
                        maxlength="100"
                    >
                    <?php if (isset($errors['tracking_number'])): ?>
                        <div class="invalid-feedback"><?= e($errors['tracking_number']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 5: Destination & Shipping Date -->
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="destination">Destination Port / Hub <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        name="destination" 
                        id="destination" 
                        class="form-control <?= isset($errors['destination']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['destination'] ?? $shipment['destination']) ?>" 
                        required
                        maxlength="150"
                    >
                    <?php if (isset($errors['destination'])): ?>
                        <div class="invalid-feedback"><?= e($errors['destination']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="shipping_date">Shipping Date <span id="shipping_req_star" class="text-danger" style="display: none;">*</span></label>
                    <input 
                        type="date" 
                        name="shipping_date" 
                        id="shipping_date" 
                        class="form-control <?= isset($errors['shipping_date']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['shipping_date'] ?? $shipment['shipping_date'] ?? '') ?>"
                    >
                    <?php if (isset($errors['shipping_date'])): ?>
                        <div class="invalid-feedback"><?= e($errors['shipping_date']) ?></div>
                    <?php else: ?>
                        <span class="form-text" id="shipping_date_hint">Mandatory for Shipped and Delivered statuses.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 6: Notes -->
            <div class="form-group">
                <label for="notes">Notes / Handling Remarks</label>
                <textarea 
                    name="notes" 
                    id="notes" 
                    rows="3" 
                    class="form-control <?= isset($errors['notes']) ? 'is-invalid' : '' ?>"
                ><?= e($old['notes'] ?? $shipment['notes'] ?? '') ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <div class="invalid-feedback"><?= e($errors['notes']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-actions mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                <a href="<?= e(url('modules/packing_shipment/view.php?id=' . $shipment['packing_id'])) ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Packing Shipment</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var statusSelect = document.getElementById('status');
    var shipDateStar = document.getElementById('shipping_req_star');
    var shipDateInput = document.getElementById('shipping_date');

    function toggleShippingRequirement() {
        var val = statusSelect.value;
        if (val === 'Shipped' || val === 'Delivered') {
            shipDateStar.style.display = 'inline';
            if (!shipDateInput.value) {
                var today = new Date().toISOString().split('T')[0];
                shipDateInput.value = today;
            }
        } else {
            shipDateStar.style.display = 'none';
        }
    }

    statusSelect.addEventListener('change', toggleShippingRequirement);
    toggleShippingRequirement();
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
