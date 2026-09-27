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

<style>
.ps-form-container {
    max-width: 960px;
    margin: 0 auto;
}
.form-section-header {
    background: #FFFDFD;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--color-border);
    border-top: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.form-section-header:first-of-type {
    border-top: none;
}
.form-section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-primary-dark);
    margin: 0;
}
.form-section-body {
    padding: 1.25rem;
}
.ps-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.15rem;
}
.ps-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.15rem;
}
.ps-preview-box {
    background: #FFFDFD;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1.15rem;
    margin-top: 0.85rem;
}
.ps-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
    margin-top: 0.85rem;
}
.ps-mini-stat {
    background: #fff;
    padding: 0.75rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    text-align: center;
}
.ps-mini-stat.highlight {
    background: #F0FDF4;
    border-color: #86EFAC;
}
</style>

<div class="ps-form-container">
    <div class="page-header">
        <div class="page-title-group">
            <h1>Create Packing Shipment</h1>
            <div class="page-subtitle">Finished Goods Packaging, Carton Allocation &amp; Dispatch Authorization</div>
        </div>
        <div class="page-actions">
            <a href="<?= e(url('modules/packing_shipment/index.php')) ?>" class="btn btn-secondary">
                Back to Shipments
            </a>
        </div>
    </div>

    <?php if ($tableMissing): ?>
        <div class="alert alert-warning mb-4">
            <strong>Database Schema Notice:</strong> Table <code>packing_shipments</code> is missing. Please import <code>sql/packing_shipment.sql</code> before submitting.
        </div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger mb-4">
            <?= e($errors['general']) ?>
        </div>
    <?php endif; ?>

    <div class="card card-form">
        <div class="card-header">
            <div>
                <h2>Outbound Shipment Specifications</h2>
                <div class="text-xs text-muted">Initialize finished packaging lot linked to verified QC inspection audit</div>
            </div>
            <span class="badge badge-primary">New Shipment</span>
        </div>
        <div class="card-body p-0">
            <form method="POST" action="<?= e(url('modules/packing_shipment/store.php')) ?>" novalidate id="packingForm">
                <?= csrf_field() ?>

                <!-- Section 1: Lot Identification & Status -->
                <div class="form-section-header">
                    <h3 class="form-section-title">1. Shipment Identification &amp; Initial Lifecycle</h3>
                    <span class="badge badge-muted text-xs">Tracking Key</span>
                </div>
                <div class="form-section-body">
                    <div class="ps-grid-2">
                        <div class="form-group mb-0">
                            <label for="packing_number" class="form-label">Packing Number <span class="required">*</span></label>
                            <input 
                                type="text" 
                                name="packing_number" 
                                id="packing_number" 
                                class="form-control font-mono <?= isset($errors['packing_number']) ? 'has-error' : '' ?>" 
                                value="<?= e($defaultPackingNumber) ?>" 
                                required
                                maxlength="50"
                            >
                            <?php if (isset($errors['packing_number'])): ?>
                                <span class="form-error"><?= e($errors['packing_number']) ?></span>
                            <?php else: ?>
                                <span class="form-text">Unique identification reference for this packaging lot.</span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label">Initial Status <span class="badge badge-info text-xs ml-1">System Enforced</span></label>
                            <input type="text" class="form-control font-mono" value="Packed" readonly disabled style="background-color: var(--color-bg); font-weight: 600; color: var(--color-primary);">
                            <span class="form-text">All new shipments strictly initialize in <strong>Packed</strong> status. Progression occurs via dispatch actions.</span>
                        </div>
                    </div>
                </div>

                <!-- Section 2: QC Audit Authorization & Headroom -->
                <div class="form-section-header">
                    <h3 class="form-section-title">2. Authorized Quality Inspection &amp; Headroom Preview</h3>
                    <span class="badge badge-info text-xs">Capacity Check</span>
                </div>
                <div class="form-section-body">
                    <div class="form-group mb-0">
                        <label for="inspection_id" class="form-label">Authorized Quality Inspection <span class="required">*</span></label>
                        <select 
                            name="inspection_id" 
                            id="inspection_id" 
                            class="form-control <?= isset($errors['inspection_id']) ? 'has-error' : '' ?>" 
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
                            <span class="form-error"><?= e($errors['inspection_id']) ?></span>
                        <?php else: ?>
                            <span class="form-text">Only inspections with overall result <strong>Passed</strong> and remaining available capacity are eligible.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Dynamic Live Preview Box for Selected Inspection -->
                    <div id="inspection_preview" class="ps-preview-box" style="display: none;">
                        <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:0.5rem;border-bottom:1px solid var(--color-border);margin-bottom:0.75rem;">
                            <strong style="font-size:0.92rem;color:var(--color-primary-dark);">Inspection &amp; Batch Authorization Profile</strong>
                            <span class="badge badge-success text-xs">QC Passed Authorization</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;font-size:0.85rem;">
                            <div>
                                <span class="text-muted">Batch:</span> <strong id="prev_batch">-</strong> &bull;
                                <span class="text-muted">Order:</span> <span id="prev_order">-</span>
                            </div>
                            <div>
                                <span class="text-muted">Style:</span> <strong id="prev_style">-</strong> &bull;
                                <span class="text-muted">Buyer:</span> <span id="prev_buyer">-</span>
                            </div>
                            <div>
                                <span class="text-muted">Stage:</span> <span id="prev_stage">-</span> &bull;
                                <span class="text-muted">QC Date:</span> <span id="prev_date">-</span>
                            </div>
                        </div>
                        <div class="ps-mini-grid">
                            <div class="ps-mini-stat">
                                <div class="text-xs text-muted" style="font-weight:600;">PASSED QUANTITY</div>
                                <div id="prev_passed" style="font-size:1.25rem;font-weight:700;color:var(--color-text);margin-top:0.25rem;">0</div>
                                <div class="text-xs text-muted">pcs certified</div>
                            </div>
                            <div class="ps-mini-stat">
                                <div class="text-xs text-muted" style="font-weight:600;">ALREADY PACKED</div>
                                <div id="prev_packed" style="font-size:1.25rem;font-weight:700;color:#b7791f;margin-top:0.25rem;">0</div>
                                <div class="text-xs text-muted">pcs allocated</div>
                            </div>
                            <div class="ps-mini-stat highlight">
                                <div class="text-xs" style="color:#166534;font-weight:700;">AVAILABLE TO PACK</div>
                                <div id="prev_available" style="font-size:1.25rem;font-weight:700;color:#166534;margin-top:0.25rem;">0 pcs</div>
                                <div class="text-xs text-muted">remaining headroom</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Volumes & Logistics Routing -->
                <div class="form-section-header">
                    <h3 class="form-section-title">3. Packaging Volume &amp; Dispatch Logistics</h3>
                    <span class="badge badge-muted text-xs">Freight Specifications</span>
                </div>
                <div class="form-section-body">
                    <div class="ps-grid-3">
                        <div class="form-group mb-0">
                            <label for="packed_quantity" class="form-label">Packed Quantity (pcs) <span class="required">*</span></label>
                            <input 
                                type="number" 
                                name="packed_quantity" 
                                id="packed_quantity" 
                                class="form-control <?= isset($errors['packed_quantity']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['packed_quantity'] ?? '') ?>" 
                                min="1" 
                                required
                            >
                            <?php if (isset($errors['packed_quantity'])): ?>
                                <span class="form-error"><?= e($errors['packed_quantity']) ?></span>
                            <?php else: ?>
                                <span class="form-text">Cannot exceed available passed quantity.</span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="carton_count" class="form-label">Carton Count <span class="required">*</span></label>
                            <input 
                                type="number" 
                                name="carton_count" 
                                id="carton_count" 
                                class="form-control <?= isset($errors['carton_count']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['carton_count'] ?? '1') ?>" 
                                min="1" 
                                required
                            >
                            <?php if (isset($errors['carton_count'])): ?>
                                <span class="form-error"><?= e($errors['carton_count']) ?></span>
                            <?php else: ?>
                                <span class="form-text">Total packed outer cartons/boxes.</span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="packing_date" class="form-label">Packing Date <span class="required">*</span></label>
                            <input 
                                type="date" 
                                name="packing_date" 
                                id="packing_date" 
                                class="form-control <?= isset($errors['packing_date']) ? 'has-error' : '' ?>" 
                                value="<?= e($defaultPackingDate) ?>" 
                                required
                            >
                            <?php if (isset($errors['packing_date'])): ?>
                                <span class="form-error"><?= e($errors['packing_date']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ps-grid-3" style="margin-top:1.15rem;">
                        <div class="form-group mb-0">
                            <label for="shipping_method" class="form-label">Shipping Method <span class="required">*</span></label>
                            <select name="shipping_method" id="shipping_method" class="form-control <?= isset($errors['shipping_method']) ? 'has-error' : '' ?>" required>
                                <?php 
                                $selectedMethod = $old['shipping_method'] ?? 'Sea';
                                foreach (['Sea', 'Air', 'Road', 'Courier'] as $m): 
                                ?>
                                    <option value="<?= e($m) ?>" <?= $selectedMethod === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['shipping_method'])): ?>
                                <span class="form-error"><?= e($errors['shipping_method']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="carrier_name" class="form-label">Carrier / Logistics Provider</label>
                            <input 
                                type="text" 
                                name="carrier_name" 
                                id="carrier_name" 
                                class="form-control <?= isset($errors['carrier_name']) ? 'has-error' : '' ?>" 
                                placeholder="e.g., Maersk, DHL, Evergreen" 
                                value="<?= e($old['carrier_name'] ?? '') ?>" 
                                maxlength="100"
                            >
                            <?php if (isset($errors['carrier_name'])): ?>
                                <span class="form-error"><?= e($errors['carrier_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="tracking_number" class="form-label">Tracking / Bill of Lading #</label>
                            <input 
                                type="text" 
                                name="tracking_number" 
                                id="tracking_number" 
                                class="form-control font-mono <?= isset($errors['tracking_number']) ? 'has-error' : '' ?>" 
                                placeholder="e.g., B/L-984210, AWB-5512" 
                                value="<?= e($old['tracking_number'] ?? '') ?>" 
                                maxlength="100"
                            >
                            <?php if (isset($errors['tracking_number'])): ?>
                                <span class="form-error"><?= e($errors['tracking_number']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Destination & Notes -->
                <div class="form-section-header">
                    <h3 class="form-section-title">4. Destination &amp; Floor Handling Instructions</h3>
                    <span class="badge badge-muted text-xs">Dispatch Endpoint</span>
                </div>
                <div class="form-section-body">
                    <div class="ps-grid-2">
                        <div class="form-group">
                            <label for="destination" class="form-label">Destination Port / Warehouse <span class="required">*</span></label>
                            <input 
                                type="text" 
                                name="destination" 
                                id="destination" 
                                class="form-control <?= isset($errors['destination']) ? 'has-error' : '' ?>" 
                                placeholder="e.g., Port of Hamburg / Logistics Hub Frankfurt, Germany" 
                                value="<?= e($old['destination'] ?? '') ?>" 
                                required
                                maxlength="150"
                            >
                            <?php if (isset($errors['destination'])): ?>
                                <span class="form-error"><?= e($errors['destination']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="shipping_date" class="form-label">Anticipated / Actual Shipping Date</label>
                            <input 
                                type="date" 
                                name="shipping_date" 
                                id="shipping_date" 
                                class="form-control <?= isset($errors['shipping_date']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['shipping_date'] ?? '') ?>"
                            >
                            <?php if (isset($errors['shipping_date'])): ?>
                                <span class="form-error"><?= e($errors['shipping_date']) ?></span>
                            <?php else: ?>
                                <span class="form-text">Optional on creation; mandatory when dispatched.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="notes" class="form-label">Notes / Special Handling Instructions</label>
                        <textarea 
                            name="notes" 
                            id="notes" 
                            rows="3" 
                            class="form-control <?= isset($errors['notes']) ? 'has-error' : '' ?>" 
                            placeholder="Enter carton marking details, seal numbers, container allocations, or inspection release notes..."
                        ><?= e($old['notes'] ?? '') ?></textarea>
                        <?php if (isset($errors['notes'])): ?>
                            <span class="form-error"><?= e($errors['notes']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;background:#fafafa;padding:1.25rem;border-top:1px solid var(--color-border);">
                    <a href="<?= e(url('modules/packing_shipment/index.php')) ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Packing Shipment</button>
                </div>
            </form>
        </div>
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
