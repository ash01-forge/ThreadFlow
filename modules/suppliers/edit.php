<?php
/**
 * ThreadFlow - Edit Supplier Form
 * 
 * Module 2: Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Validate positive integer ID
$supplierId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$supplierId || $supplierId <= 0) {
    flash_set('error', 'Invalid supplier ID specified.');
    redirect('modules/suppliers/index.php');
}

// Fetch existing supplier record safely using prepared statements
try {
    $stmt = $conn->prepare("SELECT supplier_id, supplier_name, supplier_type, contact_person, email, phone, country, address, lead_time_days, notes, is_active FROM suppliers WHERE supplier_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for edit supplier: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving supplier record.');
        redirect('modules/suppliers/index.php');
    }

    $stmt->bind_param("i", $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    $stmt->close();

    if (!$supplier) {
        flash_set('error', 'Supplier record not found.');
        redirect('modules/suppliers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching supplier for edit: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving supplier record.');
    redirect('modules/suppliers/index.php');
}

$pageTitle = 'Edit Supplier: ' . $supplier['supplier_name'];
$currentNav = 'suppliers';

// Documented allowed supplier types
$allowedSupplierTypes = ['Fabric', 'Yarn', 'Trims & Accessories', 'Dyeing & Chemicals', 'Packaging', 'Other'];

// Retrieve and clear any flashed form validation errors and previous input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Helper to determine field values (prefer old submitted input over database record)
$getValue = function (string $field) use ($old, $supplier): string {
    if (isset($old[$field])) {
        return (string) $old[$field];
    }
    return (string) ($supplier[$field] ?? '');
};

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Supplier</h1>
        <div class="page-subtitle">Update vendor profile for Supplier #<?= (int) $supplier['supplier_id'] ?> &bull; <?= e($supplier['supplier_name']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/suppliers/view.php?id=' . (int)$supplier['supplier_id'])) ?>" class="btn btn-secondary">View Profile</a>
        <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>Edit Supplier Details</h2>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/suppliers/update.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="supplier_id" value="<?= (int) $supplier['supplier_id'] ?>">

            <div class="form-group">
                <label for="supplier_name" class="form-label">Supplier / Company Name <span class="required">*</span></label>
                <input type="text"
                       id="supplier_name"
                       name="supplier_name"
                       class="form-control <?= isset($errors['supplier_name']) ? 'has-error' : '' ?>"
                       maxlength="150"
                       required
                       value="<?= e($getValue('supplier_name')) ?>"
                       placeholder="e.g., Apex Textile Mills Ltd.">
                <?php if (isset($errors['supplier_name'])): ?>
                    <span class="form-error"><?= e($errors['supplier_name']) ?></span>
                <?php endif; ?>
                <span class="form-text">Primary legal or corporate vendor name (maximum 150 characters).</span>
            </div>

            <div class="form-group">
                <label for="supplier_type" class="form-label">Supplier Type <span class="required">*</span></label>
                <select id="supplier_type"
                        name="supplier_type"
                        class="form-control <?= isset($errors['supplier_type']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Material / Service Type --</option>
                    <?php
                    $currentType = $getValue('supplier_type');
                    foreach ($allowedSupplierTypes as $type):
                    ?>
                        <option value="<?= e($type) ?>" <?= $currentType === $type ? 'selected' : '' ?>>
                            <?= e($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['supplier_type'])): ?>
                    <span class="form-error"><?= e($errors['supplier_type']) ?></span>
                <?php endif; ?>
                <span class="form-text">Category of supplied goods or services.</span>
            </div>

            <div class="form-group">
                <label for="contact_person" class="form-label">Contact Person</label>
                <input type="text"
                       id="contact_person"
                       name="contact_person"
                       class="form-control <?= isset($errors['contact_person']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       value="<?= e($getValue('contact_person')) ?>"
                       placeholder="e.g., Rafiqul Islam">
                <?php if (isset($errors['contact_person'])): ?>
                    <span class="form-error"><?= e($errors['contact_person']) ?></span>
                <?php endif; ?>
                <span class="form-text">Primary representative or sales contact (maximum 100 characters).</span>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control <?= isset($errors['email']) ? 'has-error' : '' ?>"
                       maxlength="150"
                       value="<?= e($getValue('email')) ?>"
                       placeholder="e.g., sales@apextextiles.com">
                <?php if (isset($errors['email'])): ?>
                    <span class="form-error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
                <span class="form-text">Official contact email for orders and purchasing queries.</span>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Phone</label>
                <input type="text"
                       id="phone"
                       name="phone"
                       class="form-control <?= isset($errors['phone']) ? 'has-error' : '' ?>"
                       maxlength="30"
                       value="<?= e($getValue('phone')) ?>"
                       placeholder="e.g., +880-1711-000000">
                <?php if (isset($errors['phone'])): ?>
                    <span class="form-error"><?= e($errors['phone']) ?></span>
                <?php endif; ?>
                <span class="form-text">Contact telephone or direct mobile line (maximum 30 characters).</span>
            </div>

            <div class="form-group">
                <label for="country" class="form-label">Country</label>
                <input type="text"
                       id="country"
                       name="country"
                       class="form-control <?= isset($errors['country']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       value="<?= e($getValue('country')) ?>"
                       placeholder="e.g., Bangladesh, India, China">
                <?php if (isset($errors['country'])): ?>
                    <span class="form-error"><?= e($errors['country']) ?></span>
                <?php endif; ?>
                <span class="form-text">Country of vendor operations or mill (maximum 100 characters).</span>
            </div>

            <div class="form-group">
                <label for="lead_time_days" class="form-label">Estimated Lead Time (Days)</label>
                <input type="number"
                       id="lead_time_days"
                       name="lead_time_days"
                       class="form-control <?= isset($errors['lead_time_days']) ? 'has-error' : '' ?>"
                       min="0"
                       max="365"
                       step="1"
                       value="<?= e($getValue('lead_time_days')) ?>"
                       placeholder="e.g., 14">
                <?php if (isset($errors['lead_time_days'])): ?>
                    <span class="form-error"><?= e($errors['lead_time_days']) ?></span>
                <?php endif; ?>
                <span class="form-text">Standard raw material procurement lead time in days (0 to 365 days).</span>
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Address</label>
                <textarea id="address"
                          name="address"
                          class="form-control <?= isset($errors['address']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="Mill, factory, or corporate postal address..."><?= e($getValue('address')) ?></textarea>
                <?php if (isset($errors['address'])): ?>
                    <span class="form-error"><?= e($errors['address']) ?></span>
                <?php endif; ?>
                <span class="form-text">Full office or manufacturing plant address.</span>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Notes &amp; Certifications</label>
                <textarea id="notes"
                          name="notes"
                          class="form-control <?= isset($errors['notes']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="Certifications, minimum order sizes, or terms..."><?= e($getValue('notes')) ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <span class="form-error"><?= e($errors['notes']) ?></span>
                <?php endif; ?>
                <span class="form-text">Material specifications, compliance certifications, or remarks.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Supplier</button>
                <a href="<?= e(url('modules/suppliers/view.php?id=' . (int)$supplier['supplier_id'])) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
echo '<link rel="stylesheet" href="modern-ui.css">';
include __DIR__ . '/../../includes/footer.php';
?>
