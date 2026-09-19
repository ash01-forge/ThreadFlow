<?php
/**
 * ThreadFlow - Edit Fabric Lot Form
 * 
 * Module 2 (Feature 2): Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Validate positive integer ID
$lotId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$lotId || $lotId <= 0) {
    flash_set('error', 'Invalid fabric lot ID specified.');
    redirect('modules/fabric_receiving/index.php');
}

// Fetch existing fabric lot safely using prepared statements
try {
    $stmt = $conn->prepare("SELECT fabric_lot_id, supplier_id, lot_number, fabric_type, color, received_quantity, unit, received_date, inspection_status, notes FROM fabric_lots WHERE fabric_lot_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for edit fabric lot: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving fabric lot.');
        redirect('modules/fabric_receiving/index.php');
    }

    $stmt->bind_param("i", $lotId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lot = $result->fetch_assoc();
    $stmt->close();

    if (!$lot) {
        flash_set('error', 'Fabric lot record not found.');
        redirect('modules/fabric_receiving/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching fabric lot for edit: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving fabric lot.');
    redirect('modules/fabric_receiving/index.php');
}

$pageTitle = 'Edit Fabric Lot: ' . $lot['lot_number'];
$currentNav = 'fabric_receiving';

// Documented allowed units
$allowedUnits = ['Yards', 'Meters', 'Kilograms', 'Rolls'];

// Fetch active suppliers plus current supplier (even if inactive)
$suppliers = [];
try {
    $supStmt = $conn->prepare("SELECT supplier_id, supplier_name, supplier_type, country, is_active FROM suppliers WHERE is_active = 1 OR supplier_id = ? ORDER BY supplier_name ASC");
    if ($supStmt) {
        $supStmt->bind_param("i", $lot['supplier_id']);
        $supStmt->execute();
        $supRes = $supStmt->get_result();
        $suppliers = $supRes->fetch_all(MYSQLI_ASSOC);
        $supStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching suppliers for edit form: " . $e->getMessage());
}

// Retrieve and clear any flashed form validation errors and previous input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Helper to determine field values (prefer old submitted input over database record)
$getValue = function (string $field) use ($old, $lot): string {
    if (isset($old[$field])) {
        return (string) $old[$field];
    }
    return (string) ($lot[$field] ?? '');
};

// Status badge styling helper for read-only status indicator
$st = $lot['inspection_status'];
$badgeClass = 'badge-muted';
if ($st === 'Pending') $badgeClass = 'badge-warning';
elseif ($st === 'Passed') $badgeClass = 'badge-success';
elseif ($st === 'Failed') $badgeClass = 'badge-danger';
elseif ($st === 'Conditionally Approved') $badgeClass = 'badge-info';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Fabric Lot</h1>
        <div class="page-subtitle">Update receiving details for Lot #<?= e($lot['lot_number']) ?> &bull; Record ID #<?= (int) $lot['fabric_lot_id'] ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/fabric_receiving/view.php?id=' . (int) $lot['fabric_lot_id'])) ?>" class="btn btn-secondary">View Dossier</a>
        <a href="<?= e(url('modules/fabric_receiving/index.php')) ?>" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>Edit Lot Details</h2>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/fabric_receiving/update.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="fabric_lot_id" value="<?= (int) $lot['fabric_lot_id'] ?>">

            <!-- Supplier Selection -->
            <div class="form-group">
                <label for="supplier_id" class="form-label">Supplier <span class="required">*</span></label>
                <select id="supplier_id"
                        name="supplier_id"
                        class="form-control <?= isset($errors['supplier_id']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Fabric Supplier --</option>
                    <?php
                    $currentSupplierId = (int) $getValue('supplier_id');
                    foreach ($suppliers as $sup):
                    ?>
                        <option value="<?= (int) $sup['supplier_id'] ?>" <?= $currentSupplierId === (int) $sup['supplier_id'] ? 'selected' : '' ?>>
                            <?= e($sup['supplier_name']) ?> (<?= e($sup['supplier_type'] ?? 'Fabric') ?>)
                            <?= (int)$sup['is_active'] === 0 ? ' [Inactive]' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['supplier_id'])): ?>
                    <span class="form-error"><?= e($errors['supplier_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Supplier linked to this fabric lot.</span>
            </div>

            <!-- Lot Number -->
            <div class="form-group">
                <label for="lot_number" class="form-label">Lot / Batch Number <span class="required">*</span></label>
                <input type="text"
                       id="lot_number"
                       name="lot_number"
                       class="form-control <?= isset($errors['lot_number']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       required
                       value="<?= e($getValue('lot_number')) ?>"
                       placeholder="e.g., LOT-2026-004">
                <?php if (isset($errors['lot_number'])): ?>
                    <span class="form-error"><?= e($errors['lot_number']) ?></span>
                <?php endif; ?>
                <span class="form-text">Unique identification code for this shipment from the supplier (max 100 characters).</span>
            </div>

            <!-- Fabric Type -->
            <div class="form-group">
                <label for="fabric_type" class="form-label">Fabric Type / Construction <span class="required">*</span></label>
                <input type="text"
                       id="fabric_type"
                       name="fabric_type"
                       class="form-control <?= isset($errors['fabric_type']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       required
                       value="<?= e($getValue('fabric_type')) ?>"
                       placeholder="e.g., 100% Cotton Single Jersey 180 GSM">
                <?php if (isset($errors['fabric_type'])): ?>
                    <span class="form-error"><?= e($errors['fabric_type']) ?></span>
                <?php endif; ?>
                <span class="form-text">Fabric weave, composition, or construction classification (max 100 characters).</span>
            </div>

            <!-- Color -->
            <div class="form-group">
                <label for="color" class="form-label">Color / Shade</label>
                <input type="text"
                       id="color"
                       name="color"
                       class="form-control <?= isset($errors['color']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       value="<?= e($getValue('color')) ?>"
                       placeholder="e.g., Navy Blue, Charcoal Melange">
                <?php if (isset($errors['color'])): ?>
                    <span class="form-error"><?= e($errors['color']) ?></span>
                <?php endif; ?>
                <span class="form-text">Dye lot shade or Pantone color name (max 100 characters).</span>
            </div>

            <!-- Quantity and Unit Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="received_quantity" class="form-label">Received Quantity <span class="required">*</span></label>
                    <input type="number"
                           id="received_quantity"
                           name="received_quantity"
                           class="form-control <?= isset($errors['received_quantity']) ? 'has-error' : '' ?>"
                           min="0.01"
                           step="0.01"
                           max="99999999.99"
                           required
                           value="<?= e($getValue('received_quantity')) ?>"
                           placeholder="e.g., 2500.00">
                    <?php if (isset($errors['received_quantity'])): ?>
                        <span class="form-error"><?= e($errors['received_quantity']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Quantity must be greater than 0.00.</span>
                </div>

                <div class="form-group">
                    <label for="unit" class="form-label">Unit of Measure <span class="required">*</span></label>
                    <select id="unit"
                            name="unit"
                            class="form-control <?= isset($errors['unit']) ? 'has-error' : '' ?>"
                            required>
                        <?php
                        $currentUnit = $getValue('unit');
                        foreach ($allowedUnits as $unit):
                        ?>
                            <option value="<?= e($unit) ?>" <?= $currentUnit === $unit ? 'selected' : '' ?>>
                                <?= e($unit) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['unit'])): ?>
                        <span class="form-error"><?= e($errors['unit']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Documented unit of measurement.</span>
                </div>
            </div>

            <!-- Received Date and Read-Only Inspection Status Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="received_date" class="form-label">Received Date <span class="required">*</span></label>
                    <input type="date"
                           id="received_date"
                           name="received_date"
                           class="form-control <?= isset($errors['received_date']) ? 'has-error' : '' ?>"
                           required
                           value="<?= e($getValue('received_date')) ?>">
                    <?php if (isset($errors['received_date'])): ?>
                        <span class="form-error"><?= e($errors['received_date']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Arrival date at warehouse / factory.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Inspection Status (Read-Only)</label>
                    <div style="padding: 0.5rem 0;">
                        <span class="badge <?= e($badgeClass) ?>"><?= e($st) ?></span>
                        <span class="text-muted text-sm ml-2">Managed via quality control workflow.</span>
                    </div>
                    <span class="form-text">Inspection status is preserved and cannot be altered here.</span>
                </div>
            </div>

            <!-- Notes -->
            <div class="form-group">
                <label for="notes" class="form-label">Receiving Notes &amp; Observations</label>
                <textarea id="notes"
                          name="notes"
                          class="form-control <?= isset($errors['notes']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="Receiving remarks, roll tags, or shade band notes..."><?= e($getValue('notes')) ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <span class="form-error"><?= e($errors['notes']) ?></span>
                <?php endif; ?>
                <span class="form-text">Audit remarks, delivery docket numbers, or physical inspection notes.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Fabric Lot</button>
                <a href="<?= e(url('modules/fabric_receiving/view.php?id=' . (int) $lot['fabric_lot_id'])) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
