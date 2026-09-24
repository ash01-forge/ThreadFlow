<?php
/**
 * ThreadFlow - Create Packing Shipment
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * Initial status is strictly locked to 'Packed'.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Create Packing Shipment';
$currentNav = 'packing_shipment';

$currentUser = auth_user();

// Retrieve and clear flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old    = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Fetch QC-passed inspections that have remaining unallocated capacity
$availableInspections = [];
$tableMissing = false;

try {
    $tblCheck = $conn->query("SHOW TABLES LIKE 'packing_shipments'");
    if (!$tblCheck || $tblCheck->num_rows === 0) {
        $tableMissing = true;
    } else {
        $inspSql = "SELECT 
            qi.inspection_id,
            qi.inspection_number,
            qi.inspection_date,
            qi.inspection_stage,
            qi.passed_quantity,
            pb.batch_id,
            pb.batch_number,
            bo.order_number,
            bo.style_number,
            b.buyer_name,
            (
                SELECT COALESCE(SUM(ps.packed_quantity), 0)
                FROM packing_shipments ps
                WHERE ps.inspection_id = qi.inspection_id
                  AND ps.status != 'Cancelled'
            ) AS already_packed_quantity,
            (
                qi.passed_quantity - (
                    SELECT COALESCE(SUM(ps.packed_quantity), 0)
                    FROM packing_shipments ps
                    WHERE ps.inspection_id = qi.inspection_id
                      AND ps.status != 'Cancelled'
                )
            ) AS available_quantity
        FROM quality_inspections qi
        INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id
        INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
        INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
        WHERE qi.overall_result = 'Passed' AND qi.passed_quantity > 0
        HAVING available_quantity > 0
        ORDER BY qi.inspection_id DESC";

        $iRes = $conn->query($inspSql);
        if ($iRes) {
            $availableInspections = $iRes->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load available QC inspections for packing: " . $e->getMessage());
    $tableMissing = true;
}

// Generate default suggested packing number
$defaultPackingNumber = $old['packing_number'] ?? ('PKG-' . date('Ymd') . '-' . str_pad((string) mt_rand(100, 999), 3, '0', STR_PAD_LEFT));
$defaultPackingDate   = $old['packing_date'] ?? date('Y-m-d');
$preselectedInspId    = (int) ($old['inspection_id'] ?? ($_GET['inspection_id'] ?? 0));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Create Packing Shipment</h1>
        <div class="page-subtitle">Module 4 &bull; Finished Goods Packaging Authorization</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/packing_shipment/index.php')) ?>" class="btn btn-secondary">
            &larr; Back to Directory
        </a>
    </div>
</div>

<?php if ($tableMissing): ?>
    <div class="alert alert-warning">
        <strong>Database Schema Notice:</strong> Table <code>packing_shipments</code> is missing. Please import <code>sql/packing_shipment.sql</code> before submitting.
    </div>
<?php endif; ?>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger">
        <?= e($errors['general']) ?>
    </div>
<?php endif; ?>

<div class="card card-form" style="max-width: 900px;">
    <div class="card-header">
        <h2>Outbound Package &amp; Shipment Details</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= e(url('modules/packing_shipment/store.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <!-- Row 1: Identification & Fixed Initial Status -->
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="packing_number">Packing Number <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        name="packing_number" 
                        id="packing_number" 
                        class="form-control <?= isset($errors['packing_number']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($defaultPackingNumber) ?>" 
                        required
                        maxlength="50"
                    >
                    <?php if (isset($errors['packing_number'])): ?>
                        <div class="invalid-feedback"><?= e($errors['packing_number']) ?></div>
                    <?php else: ?>
                        <span class="form-text">Unique identification reference for this packaging lot.</span>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-6">
                    <label>Initial Status</label>
                    <input type="text" class="form-control" value="Packed" readonly disabled style="background-color: var(--color-bg); font-weight: 600; color: var(--color-primary);">
                    <span class="form-text">All new shipments strictly initialize in <strong>Packed</strong> status. Progression to <em>In Transit</em> occurs via status management.</span>
                </div>
            </div>

            <!-- Row 2: Source QC Inspection Selection -->
            <div class="form-group">
                <label for="inspection_id">Authorized Quality Inspection <span class="text-danger">*</span></label>
                <select 
                    name="inspection_id" 
                    id="inspection_id" 
                    class="form-control <?= isset($errors['inspection_id']) ? 'is-invalid' : '' ?>" 
                    required
                >
                    <option value="">-- Select Passed QC Inspection --</option>
                    <?php foreach ($availableInspections as $insp): ?>
                        <?php 
                        $isSelected = ($preselectedInspId === (int)$insp['inspection_id']);
                        ?>
                        <option 
                            value="<?= e((string)$insp['inspection_id']) ?>"
                            data-batch="<?= e($insp['batch_number']) ?>"
                            data-style="<?= e($insp['style_number']) ?>"
                            data-order="<?= e($insp['order_number']) ?>"
                            data-buyer="<?= e($insp['buyer_name']) ?>"
                            data-stage="<?= e($insp['inspection_stage']) ?>"
                            data-date="<?= e($insp['inspection_date']) ?>"
                            data-passed="<?= e((string)$insp['passed_quantity']) ?>"
                            data-packed="<?= e((string)$insp['already_packed_quantity']) ?>"
                            data-available="<?= e((string)$insp['available_quantity']) ?>"
                            <?= $isSelected ? 'selected' : '' ?>
                        >
                            <?= e($insp['inspection_number']) ?> &mdash; Batch: <?= e($insp['batch_number']) ?> &bull; <?= e($insp['buyer_name']) ?> (<?= e($insp['style_number']) ?>) [Available: <?= e(number_format((int)$insp['available_quantity'])) ?> pcs]
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['inspection_id'])): ?>
                    <div class="invalid-feedback"><?= e($errors['inspection_id']) ?></div>
                <?php else: ?>
                    <span class="form-text">Only inspections with overall result <strong>Passed</strong> and remaining available capacity are eligible.</span>
                <?php endif; ?>
            </div>

            <!-- Dynamic Live Preview Box for Selected Inspection -->
            <div id="inspection_preview" class="card mb-4" style="background: #fdf5f6; border-color: #ebd1d4; display: none;">
                <div class="card-body py-3">
                    <h3 style="font-size: 0.95rem; margin-bottom: 0.5rem; color: var(--color-primary);">Inspection &amp; Batch Authorization Profile</h3>
                    <div class="row small">
                        <div class="col-md-3">
                            <div><strong>Batch:</strong> <span id="prev_batch">-</span></div>
                            <div><strong>Order:</strong> <span id="prev_order">-</span></div>
                        </div>
                        <div class="col-md-3">
                            <div><strong>Style:</strong> <span id="prev_style">-</span></div>
                            <div><strong>Buyer:</strong> <span id="prev_buyer">-</span></div>
                        </div>
                        <div class="col-md-3">
                            <div><strong>Stage:</strong> <span id="prev_stage">-</span></div>
                            <div><strong>QC Date:</strong> <span id="prev_date">-</span></div>
                        </div>
                        <div class="col-md-3">
                            <div><strong>Passed Qty:</strong> <span id="prev_passed" class="text-success font-weight-bold">0</span> pcs</div>
                            <div><strong>Already Packed:</strong> <span id="prev_packed">0</span> pcs</div>
                            <div><strong>Available to Pack:</strong> <span id="prev_available" class="badge badge-success">0 pcs</span></div>
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
                        value="<?= e($old['packed_quantity'] ?? '') ?>" 
                        min="1" 
                        required
                    >
                    <?php if (isset($errors['packed_quantity'])): ?>
                        <div class="invalid-feedback"><?= e($errors['packed_quantity']) ?></div>
                    <?php else: ?>
                        <span class="form-text">Cannot exceed available passed quantity.</span>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="carton_count">Carton Count <span class="text-danger">*</span></label>
                    <input 
                        type="number" 
                        name="carton_count" 
                        id="carton_count" 
                        class="form-control <?= isset($errors['carton_count']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['carton_count'] ?? '1') ?>" 
                        min="1" 
                        required
                    >
                    <?php if (isset($errors['carton_count'])): ?>
                        <div class="invalid-feedback"><?= e($errors['carton_count']) ?></div>
                    <?php else: ?>
                        <span class="form-text">Total packed outer cartons/boxes.</span>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="packing_date">Packing Date <span class="text-danger">*</span></label>
                    <input 
                        type="date" 
                        name="packing_date" 
                        id="packing_date" 
                        class="form-control <?= isset($errors['packing_date']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($defaultPackingDate) ?>" 
                        required
                    >
                    <?php if (isset($errors['packing_date'])): ?>
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
                        $selectedMethod = $old['shipping_method'] ?? 'Sea';
                        foreach (['Sea', 'Air', 'Road', 'Courier'] as $m): 
                        ?>
                            <option value="<?= e($m) ?>" <?= $selectedMethod === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['shipping_method'])): ?>
                        <div class="invalid-feedback"><?= e($errors['shipping_method']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="carrier_name">Carrier / Logistics Provider</label>
                    <input 
                        type="text" 
                        name="carrier_name" 
                        id="carrier_name" 
                        class="form-control <?= isset($errors['carrier_name']) ? 'is-invalid' : '' ?>" 
                        placeholder="e.g., Maersk, DHL, Evergreen" 
                        value="<?= e($old['carrier_name'] ?? '') ?>" 
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
                        placeholder="e.g., B/L-984210, AWB-5512" 
                        value="<?= e($old['tracking_number'] ?? '') ?>" 
                        maxlength="100"
                    >
                    <?php if (isset($errors['tracking_number'])): ?>
                        <div class="invalid-feedback"><?= e($errors['tracking_number']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 5: Destination & Shipping Date (Optional for Packed status) -->
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="destination">Destination Port / Warehouse <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        name="destination" 
                        id="destination" 
                        class="form-control <?= isset($errors['destination']) ? 'is-invalid' : '' ?>" 
                        placeholder="e.g., Port of Hamburg / Logistics Hub Frankfurt, Germany" 
                        value="<?= e($old['destination'] ?? '') ?>" 
                        required
                        maxlength="150"
                    >
                    <?php if (isset($errors['destination'])): ?>
                        <div class="invalid-feedback"><?= e($errors['destination']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="shipping_date">Anticipated / Actual Shipping Date</label>
                    <input 
                        type="date" 
                        name="shipping_date" 
                        id="shipping_date" 
                        class="form-control <?= isset($errors['shipping_date']) ? 'is-invalid' : '' ?>" 
                        value="<?= e($old['shipping_date'] ?? '') ?>"
                    >
                    <?php if (isset($errors['shipping_date'])): ?>
                        <div class="invalid-feedback"><?= e($errors['shipping_date']) ?></div>
                    <?php else: ?>
                        <span class="form-text">Optional on creation; mandatory when dispatched.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 6: Notes -->
            <div class="form-group">
                <label for="notes">Notes / Special Handling Instructions</label>
                <textarea 
                    name="notes" 
                    id="notes" 
                    rows="3" 
                    class="form-control <?= isset($errors['notes']) ? 'is-invalid' : '' ?>" 
                    placeholder="Enter carton marking details, seal numbers, container allocations, or inspection release notes..."
                ><?= e($old['notes'] ?? '') ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <div class="invalid-feedback"><?= e($errors['notes']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-actions mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                <a href="<?= e(url('modules/packing_shipment/index.php')) ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Packing Shipment</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var selectInsp = document.getElementById('inspection_id');
    var previewBox = document.getElementById('inspection_preview');
    var packedInput = document.getElementById('packed_quantity');

    function updatePreview() {
        var opt = selectInsp.options[selectInsp.selectedIndex];
        if (!opt || !opt.value) {
            previewBox.style.display = 'none';
            packedInput.removeAttribute('max');
            return;
        }

        document.getElementById('prev_batch').textContent = opt.getAttribute('data-batch') || '-';
        document.getElementById('prev_order').textContent = opt.getAttribute('data-order') || '-';
        document.getElementById('prev_style').textContent = opt.getAttribute('data-style') || '-';
        document.getElementById('prev_buyer').textContent = opt.getAttribute('data-buyer') || '-';
        document.getElementById('prev_stage').textContent = opt.getAttribute('data-stage') || '-';
        document.getElementById('prev_date').textContent = opt.getAttribute('data-date') || '-';
        document.getElementById('prev_passed').textContent = Number(opt.getAttribute('data-passed') || 0).toLocaleString();
        document.getElementById('prev_packed').textContent = Number(opt.getAttribute('data-packed') || 0).toLocaleString();

        var available = parseInt(opt.getAttribute('data-available'), 10) || 0;
        document.getElementById('prev_available').textContent = available.toLocaleString() + ' pcs';

        packedInput.setAttribute('max', available);
        if (!packedInput.value || parseInt(packedInput.value, 10) > available) {
            packedInput.value = available;
        }

        previewBox.style.display = 'block';
    }

    selectInsp.addEventListener('change', updatePreview);
    if (selectInsp.value) {
        updatePreview();
    }
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
