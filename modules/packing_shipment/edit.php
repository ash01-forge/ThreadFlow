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

$statusBadgeMap = [
    'Packed'     => 'badge-info',
    'In Transit' => 'badge-warning',
    'Shipped'    => 'badge-primary',
    'Delivered'  => 'badge-success',
    'Cancelled'  => 'badge-danger',
];
$statusBadgeClass = $statusBadgeMap[$currentStatus] ?? 'badge-secondary';
?>

<style>
.ps-edit-container { max-width: 960px; margin: 0 auto; }
.ps-summary-banner {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    align-items: center;
}
.ps-summary-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
    margin-bottom: 0.25rem;
}
.ps-summary-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--color-text);
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
.form-section-header:first-of-type { border-top: none; }
.form-section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-primary-dark);
    margin: 0;
}
.form-section-body { padding: 1.25rem; }
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
.ps-context-box {
    background: #FFFDFD;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1rem 1.15rem;
    margin-bottom: 0.5rem;
}
.ps-mini-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-top: 0.85rem;
}
.ps-mini-stat {
    background: #fff;
    padding: 0.65rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    text-align: center;
}
.ps-mini-stat.highlight { background: #F0FDF4; border-color: #86EFAC; }
.field-locked {
    background-color: #F9FAFB !important;
    cursor: not-allowed;
    color: var(--color-text-muted);
    border-color: #E5E7EB !important;
}
</style>

<div class="ps-edit-container">
    <div class="page-header">
        <div class="page-title-group">
            <h1>Edit Packing Shipment</h1>
            <div class="page-subtitle">Modify logistics, status progression and handling for shipment record</div>
        </div>
        <div class="page-actions">
            <a href="<?= e(url('modules/packing_shipment/view.php?id=' . $shipment['packing_id'])) ?>" class="btn btn-secondary">
                Cancel &amp; View Dossier
            </a>
        </div>
    </div>

    <!-- Record Summary Banner -->
    <div class="ps-summary-banner">
        <div>
            <div class="ps-summary-label">Packing Reference &amp; Status</div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span class="ps-summary-value font-mono" style="color:var(--color-primary);font-size:1.05rem;">
                    <?= e($shipment['packing_number']) ?>
                </span>
                <span class="badge <?= $statusBadgeClass ?>"><?= e($currentStatus) ?></span>
            </div>
        </div>
        <div>
            <div class="ps-summary-label">Production Batch</div>
            <div class="ps-summary-value"><?= e($shipment['batch_number']) ?></div>
            <div class="text-xs text-muted"><?= e($shipment['style_number']) ?> &bull; <?= e($shipment['order_number']) ?></div>
        </div>
        <div>
            <div class="ps-summary-label">Buyer</div>
            <div class="ps-summary-value"><?= e($shipment['buyer_name']) ?></div>
        </div>
        <div>
            <div class="ps-summary-label">Source QC Inspection</div>
            <div class="ps-summary-value"><?= e($shipment['inspection_number']) ?></div>
            <div class="text-xs text-muted"><?= e($shipment['inspection_stage']) ?> &bull; <?= e(date('M d, Y', strtotime($shipment['inspection_date']))) ?></div>
        </div>
    </div>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger mb-4"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <?php if ($isCoreLocked): ?>
        <div class="alert alert-warning mb-4">
            <strong>Field Immutability Active:</strong> Shipment is in <strong><?= e($currentStatus) ?></strong> — beyond <em>Packed</em>.
            Core packaging fields (quantity, packing date) are locked. Only logistics details and authorized status progression may be updated.
        </div>
    <?php endif; ?>

    <div class="card card-form">
        <div class="card-header">
            <div>
                <h2>Shipment Modification Form</h2>
                <div class="text-xs text-muted">Status-gated edits with field immutability enforcement</div>
            </div>
            <span class="badge badge-primary">Edit Shipment</span>
        </div>
        <div class="card-body p-0">
            <form method="POST" action="<?= e(url('modules/packing_shipment/update.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="packing_id" value="<?= e((string)$shipment['packing_id']) ?>">

                <!-- Section 1: Identification & Status Progression -->
                <div class="form-section-header">
                    <h3 class="form-section-title">1. Reference &amp; Lifecycle Status</h3>
                    <span class="badge badge-muted text-xs">Shipment Identity</span>
                </div>
                <div class="form-section-body">
                    <div class="ps-grid-2">
                        <div class="form-group mb-0">
                            <label class="form-label">Packing Number <span class="badge badge-muted text-xs ml-1">Immutable</span></label>
                            <input 
                                type="text" 
                                class="form-control font-mono field-locked" 
                                value="<?= e($shipment['packing_number']) ?>" 
                                readonly disabled
                            >
                            <span class="form-text">System-wide unique packing reference (cannot be changed).</span>
                        </div>

                        <div class="form-group mb-0">
                            <label for="status" class="form-label">Shipment Status <span class="required">*</span></label>
                            <select name="status" id="status" class="form-control <?= isset($errors['status']) ? 'has-error' : '' ?>" required>
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
                                <span class="form-error"><?= e($errors['status']) ?></span>
                            <?php else: ?>
                                <span class="form-text">Progression: Packed → In Transit → Shipped → Delivered (or Cancelled).</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section 2: QC Context (read-only) -->
                <div class="form-section-header">
                    <h3 class="form-section-title">2. QC Authorization &amp; Capacity Context</h3>
                    <span class="badge badge-info text-xs">Traceability</span>
                </div>
                <div class="form-section-body">
                    <div class="ps-context-box">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;font-size:0.85rem;">
                            <div>
                                <span class="text-muted">Inspection:</span> <strong><?= e($shipment['inspection_number']) ?></strong><br>
                                <span class="text-muted">Stage:</span> <?= e($shipment['inspection_stage']) ?>
                            </div>
                            <div>
                                <span class="text-muted">Batch:</span> <strong><?= e($shipment['batch_number']) ?></strong><br>
                                <span class="text-muted">Order:</span> <?= e($shipment['order_number']) ?>
                            </div>
                            <div>
                                <span class="text-muted">Style:</span> <strong><?= e($shipment['style_number']) ?></strong><br>
                                <span class="text-muted">Buyer:</span> <?= e($shipment['buyer_name']) ?>
                            </div>
                        </div>
                        <div class="ps-mini-grid-3">
                            <div class="ps-mini-stat">
                                <div class="text-xs text-muted" style="font-weight:600;">PASSED QTY</div>
                                <div style="font-size:1.15rem;font-weight:700;"><?= e(number_format($passedQty)) ?></div>
                                <div class="text-xs text-muted">pcs certified</div>
                            </div>
                            <div class="ps-mini-stat">
                                <div class="text-xs text-muted" style="font-weight:600;">OTHER PACKED</div>
                                <div style="font-size:1.15rem;font-weight:700;color:#b7791f;"><?= e(number_format($otherAllocated)) ?></div>
                                <div class="text-xs text-muted">pcs elsewhere</div>
                            </div>
                            <div class="ps-mini-stat highlight">
                                <div class="text-xs" style="color:#166534;font-weight:700;">AVAILABLE HEADROOM</div>
                                <div style="font-size:1.15rem;font-weight:700;color:#166534;"><?= e(number_format($maxAvailable)) ?></div>
                                <div class="text-xs text-muted">pcs max allowed</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Packaging Volumes & Logistics -->
                <div class="form-section-header">
                    <h3 class="form-section-title">3. Packaging Volume &amp; Freight Logistics</h3>
                    <span class="badge badge-muted text-xs"><?= $isCoreLocked ? 'Partially Locked' : 'Editable' ?></span>
                </div>
                <div class="form-section-body">
                    <div class="ps-grid-3">
                        <div class="form-group">
                            <label for="packed_quantity" class="form-label">Packed Quantity (pcs) <span class="required">*</span></label>
                            <input 
                                type="number" 
                                name="packed_quantity" 
                                id="packed_quantity" 
                                class="form-control <?= $isCoreLocked ? 'field-locked' : '' ?> <?= isset($errors['packed_quantity']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['packed_quantity'] ?? (string)$shipment['packed_quantity']) ?>" 
                                min="1" 
                                max="<?= e((string)$maxAvailable) ?>"
                                <?= $isCoreLocked ? 'readonly disabled' : 'required' ?>
                            >
                            <?php if ($isCoreLocked): ?>
                                <span class="form-text">🔒 Locked — beyond Packed status.</span>
                            <?php elseif (isset($errors['packed_quantity'])): ?>
                                <span class="form-error"><?= e($errors['packed_quantity']) ?></span>
                            <?php else: ?>
                                <span class="form-text">Max available: <?= e(number_format($maxAvailable)) ?> pcs.</span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="carton_count" class="form-label">Carton Count <span class="required">*</span></label>
                            <input 
                                type="number" 
                                name="carton_count" 
                                id="carton_count" 
                                class="form-control <?= isset($errors['carton_count']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['carton_count'] ?? (string)$shipment['carton_count']) ?>" 
                                min="1" 
                                required
                            >
                            <?php if (isset($errors['carton_count'])): ?>
                                <span class="form-error"><?= e($errors['carton_count']) ?></span>
                            <?php else: ?>
                                <span class="form-text">Outer cartons / boxes in this lot.</span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="packing_date" class="form-label">Packing Date <span class="required">*</span></label>
                            <input 
                                type="date" 
                                name="packing_date" 
                                id="packing_date" 
                                class="form-control <?= $isCoreLocked ? 'field-locked' : '' ?> <?= isset($errors['packing_date']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['packing_date'] ?? $shipment['packing_date']) ?>" 
                                <?= $isCoreLocked ? 'readonly disabled' : 'required' ?>
                            >
                            <?php if ($isCoreLocked): ?>
                                <span class="form-text">🔒 Locked — beyond Packed status.</span>
                            <?php elseif (isset($errors['packing_date'])): ?>
                                <span class="form-error"><?= e($errors['packing_date']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ps-grid-3" style="margin-top:1.15rem;">
                        <div class="form-group mb-0">
                            <label for="shipping_method" class="form-label">Shipping Method <span class="required">*</span></label>
                            <select name="shipping_method" id="shipping_method" class="form-control <?= isset($errors['shipping_method']) ? 'has-error' : '' ?>" required>
                                <?php 
                                $curMethod = $old['shipping_method'] ?? $shipment['shipping_method'];
                                foreach (['Sea', 'Air', 'Road', 'Courier'] as $m): 
                                ?>
                                    <option value="<?= e($m) ?>" <?= $curMethod === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['shipping_method'])): ?>
                                <span class="form-error"><?= e($errors['shipping_method']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="carrier_name" class="form-label">Carrier / Freight Operator</label>
                            <input 
                                type="text" 
                                name="carrier_name" 
                                id="carrier_name" 
                                class="form-control <?= isset($errors['carrier_name']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['carrier_name'] ?? $shipment['carrier_name'] ?? '') ?>" 
                                maxlength="100"
                                placeholder="e.g., Maersk, DHL, FedEx"
                            >
                            <?php if (isset($errors['carrier_name'])): ?>
                                <span class="form-error"><?= e($errors['carrier_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="tracking_number" class="form-label">Tracking / B/L Number</label>
                            <input 
                                type="text" 
                                name="tracking_number" 
                                id="tracking_number" 
                                class="form-control font-mono <?= isset($errors['tracking_number']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['tracking_number'] ?? $shipment['tracking_number'] ?? '') ?>" 
                                maxlength="100"
                                placeholder="e.g., B/L-984210"
                            >
                            <?php if (isset($errors['tracking_number'])): ?>
                                <span class="form-error"><?= e($errors['tracking_number']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Destination & Notes -->
                <div class="form-section-header">
                    <h3 class="form-section-title">4. Dispatch Endpoint &amp; Handling Remarks</h3>
                    <span class="badge badge-muted text-xs">Logistics</span>
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
                                value="<?= e($old['destination'] ?? $shipment['destination']) ?>" 
                                required
                                maxlength="150"
                                placeholder="e.g., Port of Hamburg, Germany"
                            >
                            <?php if (isset($errors['destination'])): ?>
                                <span class="form-error"><?= e($errors['destination']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="shipping_date" class="form-label">
                                Shipping Date <span id="shipping_req_star" class="required" style="display: none;">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="shipping_date" 
                                id="shipping_date" 
                                class="form-control <?= isset($errors['shipping_date']) ? 'has-error' : '' ?>" 
                                value="<?= e($old['shipping_date'] ?? $shipment['shipping_date'] ?? '') ?>"
                            >
                            <?php if (isset($errors['shipping_date'])): ?>
                                <span class="form-error"><?= e($errors['shipping_date']) ?></span>
                            <?php else: ?>
                                <span class="form-text" id="shipping_date_hint">Required for Shipped and Delivered statuses.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="notes" class="form-label">Notes / Handling Remarks</label>
                        <textarea 
                            name="notes" 
                            id="notes" 
                            rows="3" 
                            class="form-control <?= isset($errors['notes']) ? 'has-error' : '' ?>"
                            placeholder="Carton markings, seal numbers, container allocations, special handling..."
                        ><?= e($old['notes'] ?? $shipment['notes'] ?? '') ?></textarea>
                        <?php if (isset($errors['notes'])): ?>
                            <span class="form-error"><?= e($errors['notes']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;background:#fafafa;padding:1.25rem;border-top:1px solid var(--color-border);">
                    <a href="<?= e(url('modules/packing_shipment/view.php?id=' . $shipment['packing_id'])) ?>" class="btn btn-secondary">Cancel Changes</a>
                    <button type="submit" class="btn btn-primary">Update Packing Shipment</button>
                </div>
            </form>
        </div>
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
