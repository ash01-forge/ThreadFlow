<?php
/**
 * ThreadFlow - Add Supplier Form
 * 
 * Module 2: Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Add Supplier';
$currentNav = 'suppliers';

// Documented allowed supplier types
$allowedSupplierTypes = ['Fabric', 'Yarn', 'Trims & Accessories', 'Dyeing & Chemicals', 'Packaging', 'Other'];

// Retrieve and clear any flashed form validation errors and previous input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Add Supplier</h1>
        <div class="page-subtitle">Module 2 &bull; Ownership: Member 2 &bull; Access: Admin, Production Manager</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="btn btn-secondary">Back to Supplier List</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>New Supplier Details</h2>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/suppliers/store.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="supplier_name" class="form-label">Supplier / Company Name <span class="required">*</span></label>
                <input type="text"
                       id="supplier_name"
                       name="supplier_name"
                       class="form-control <?= isset($errors['supplier_name']) ? 'has-error' : '' ?>"
                       maxlength="150"
                       required
                       value="<?= e($old['supplier_name'] ?? '') ?>"
                       placeholder="e.g., Apex Textile Mills Ltd.">
                <?php if (isset($errors['supplier_name'])): ?>
                    <span class="form-error"><?= e($errors['supplier_name']) ?></span>
                <?php endif; ?>
                <span class="form-text">Primary legal or trading company name (maximum 150 characters).</span>
            </div>

            <div class="form-group">
                <label for="supplier_type" class="form-label">Supplier Type <span class="required">*</span></label>
                <select id="supplier_type"
                        name="supplier_type"
                        class="form-control <?= isset($errors['supplier_type']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Material / Service Type --</option>
                    <?php
                    $selectedType = $old['supplier_type'] ?? 'Fabric';
                    foreach ($allowedSupplierTypes as $type):
                    ?>
                        <option value="<?= e($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>>
                            <?= e($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['supplier_type'])): ?>
                    <span class="form-error"><?= e($errors['supplier_type']) ?></span>
                <?php endif; ?>
                <span class="form-text">Primary category of materials or services provided to garment production.</span>
            </div>

            <div class="form-group">
                <label for="contact_person" class="form-label">Contact Person</label>
                <input type="text"
                       id="contact_person"
                       name="contact_person"
                       class="form-control <?= isset($errors['contact_person']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       value="<?= e($old['contact_person'] ?? '') ?>"
                       placeholder="e.g., Rafiqul Islam">
                <?php if (isset($errors['contact_person'])): ?>
                    <span class="form-error"><?= e($errors['contact_person']) ?></span>
                <?php endif; ?>
                <span class="form-text">Primary vendor representative or sales manager (maximum 100 characters).</span>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control <?= isset($errors['email']) ? 'has-error' : '' ?>"
                       maxlength="150"
                       value="<?= e($old['email'] ?? '') ?>"
                       placeholder="e.g., sales@apextextiles.com">
                <?php if (isset($errors['email'])): ?>
                    <span class="form-error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
                <span class="form-text">Official contact email for orders and invoices.</span>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Phone</label>
                <input type="text"
                       id="phone"
                       name="phone"
                       class="form-control <?= isset($errors['phone']) ? 'has-error' : '' ?>"
                       maxlength="30"
                       value="<?= e($old['phone'] ?? '') ?>"
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
                       value="<?= e($old['country'] ?? '') ?>"
                       placeholder="e.g., Bangladesh, India, China">
                <?php if (isset($errors['country'])): ?>
                    <span class="form-error"><?= e($errors['country']) ?></span>
                <?php endif; ?>
                <span class="form-text">Country of factory, mill, or company headquarters (maximum 100 characters).</span>
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
                       value="<?= e($old['lead_time_days'] ?? '') ?>"
                       placeholder="e.g., 14">
                <?php if (isset($errors['lead_time_days'])): ?>
                    <span class="form-error"><?= e($errors['lead_time_days']) ?></span>
                <?php endif; ?>
                <span class="form-text">Typical raw material procurement lead time in days (0 to 365 days).</span>
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Address</label>
                <textarea id="address"
                          name="address"
                          class="form-control <?= isset($errors['address']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="Mill, factory, or corporate postal address..."><?= e($old['address'] ?? '') ?></textarea>
                <?php if (isset($errors['address'])): ?>
                    <span class="form-error"><?= e($errors['address']) ?></span>
                <?php endif; ?>
                <span class="form-text">Full postal or manufacturing facility address.</span>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Notes &amp; Certifications</label>
                <textarea id="notes"
                          name="notes"
                          class="form-control <?= isset($errors['notes']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="e.g., OEKO-TEX Standard 100 certified; Minimum order quantity 5,000 yards..."><?= e($old['notes'] ?? '') ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <span class="form-error"><?= e($errors['notes']) ?></span>
                <?php endif; ?>
                <span class="form-text">Material specifications, certifications, MOQ, or special supplier terms.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Supplier</button>
                <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
echo '<link rel="stylesheet" href="modern-ui.css">';
include __DIR__ . '/../../includes/footer.php';
?>
