<?php
/**
 * ThreadFlow - Receive Fabric Lot Form
 * 
 * Module 2 (Feature 2): Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Receive Fabric Lot';
$currentNav = 'fabric_receiving';

// Documented allowed units
$allowedUnits = ['Yards', 'Meters', 'Kilograms', 'Rolls'];

// Fetch active suppliers for dropdown
$activeSuppliers = [];
try {
    $stmt = $conn->prepare("SELECT supplier_id, supplier_name, supplier_type, country FROM suppliers WHERE is_active = 1 ORDER BY supplier_name ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $activeSuppliers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching active suppliers for create form: " . $e->getMessage());
}

// Retrieve and clear flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Receive Fabric Lot</h1>
        <div class="page-subtitle">Module 2 (Feature 2) &bull; Ownership: Member 2 &bull; Access: Admin, Production Manager</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/fabric_receiving/index.php')) ?>" class="btn btn-secondary">Back to Receiving List</a>
    </div>
</div>

<?php if (empty($activeSuppliers)): ?>
    <div class="alert alert-warning mb-4">
        <strong>No Active Suppliers Found:</strong> Fabric lots can only be received from active suppliers.
        Please ensure an active supplier exists in <a href="<?= e(url('modules/suppliers/create.php')) ?>">Supplier Management</a> before receiving raw material lots.
    </div>
<?php endif; ?>

<div class="card card-form">
    <div class="card-header">
        <h2>Fabric Lot Receiving Details</h2>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/fabric_receiving/store.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <!-- Supplier Selection -->
            <div class="form-group">
                <label for="supplier_id" class="form-label">Supplier <span class="required">*</span></label>
                <select id="supplier_id"
                        name="supplier_id"
                        class="form-control <?= isset($errors['supplier_id']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Active Fabric Supplier --</option>
                    <?php
                    $selectedSupplierId = (int) ($old['supplier_id'] ?? 0);
                    foreach ($activeSuppliers as $sup):
                    ?>
                        <option value="<?= (int) $sup['supplier_id'] ?>" <?= $selectedSupplierId === (int) $sup['supplier_id'] ? 'selected' : '' ?>>
                            <?= e($sup['supplier_name']) ?> (<?= e($sup['supplier_type'] ?? 'Fabric') ?><?= !empty($sup['country']) ? ' - ' . e($sup['country']) : '' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['supplier_id'])): ?>
                    <span class="form-error"><?= e($errors['supplier_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Must be an active registered supplier. Link established via supplier_id.</span>
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
                       value="<?= e($old['lot_number'] ?? '') ?>"
                       placeholder="e.g., LOT-2026-004 or ML-88912">
                <?php if (isset($errors['lot_number'])): ?>
                    <span class="form-error"><?= e($errors['lot_number']) ?></span>
                <?php endif; ?>
                <span class="form-text">Unique lot identification code provided by mill/vendor for this shipment (max 100 characters).</span>
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
                       value="<?= e($old['fabric_type'] ?? '') ?>"
                       placeholder="e.g., 100% Cotton Single Jersey 180 GSM, Cotton Twill, Indigo Denim 12oz">
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
                       value="<?= e($old['color'] ?? '') ?>"
                       placeholder="e.g., Navy Blue #19-4010, Charcoal Heather, Optical White">
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
                           value="<?= e($old['received_quantity'] ?? '') ?>"
                           placeholder="e.g., 2500.00">
                    <?php if (isset($errors['received_quantity'])): ?>
                        <span class="form-error"><?= e($errors['received_quantity']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Must be greater than 0.00.</span>
                </div>

                <div class="form-group">
                    <label for="unit" class="form-label">Unit of Measure <span class="required">*</span></label>
                    <select id="unit"
                            name="unit"
                            class="form-control <?= isset($errors['unit']) ? 'has-error' : '' ?>"
                            required>
                        <?php
                        $selectedUnit = $old['unit'] ?? 'Yards';
                        foreach ($allowedUnits as $unit):
                        ?>
                            <option value="<?= e($unit) ?>" <?= $selectedUnit === $unit ? 'selected' : '' ?>>
                                <?= e($unit) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['unit'])): ?>
                        <span class="form-error"><?= e($errors['unit']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Select approved measurement unit.</span>
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
                           value="<?= e($old['received_date'] ?? date('Y-m-d')) ?>">
                    <?php if (isset($errors['received_date'])): ?>
                        <span class="form-error"><?= e($errors['received_date']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Arrival date at warehouse / receiving dock.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Initial Inspection Status</label>
                    <div style="padding: 0.5rem 0;">
                        <span class="badge badge-warning">Pending</span>
                        <span class="text-muted text-sm ml-2">All newly received lots initialize as Pending.</span>
                    </div>
                    <span class="form-text">Fabric inspection is conducted separately.</span>
                </div>
            </div>

            <!-- Notes -->
            <div class="form-group">
                <label for="notes" class="form-label">Receiving Notes &amp; Observations</label>
                <textarea id="notes"
                          name="notes"
                          class="form-control <?= isset($errors['notes']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="e.g., Mill roll tickets #101-140; packing list checked; awaiting fabric relaxation and shade band approval..."><?= e($old['notes'] ?? '') ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <span class="form-error"><?= e($errors['notes']) ?></span>
                <?php endif; ?>
                <span class="form-text">Roll counts, container numbers, delivery docket references, or physical condition.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= empty($activeSuppliers) ? 'disabled' : '' ?>>Save Received Fabric Lot</button>
                <a href="<?= e(url('modules/fabric_receiving/index.php')) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
echo '<link rel="stylesheet" href="modern-ui.css">';
include __DIR__ . '/../../includes/footer.php';
