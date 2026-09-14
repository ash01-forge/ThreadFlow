<?php
/**
 * ThreadFlow - Edit Buyer Form
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

// Validate positive integer ID
$buyerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$buyerId || $buyerId <= 0) {
    flash_set('error', 'Invalid buyer ID specified.');
    redirect('modules/buyers/index.php');
}

// Fetch existing buyer record safely using prepared statements
try {
    $stmt = $conn->prepare("SELECT buyer_id, buyer_name, contact_person, email, phone, country, address, is_active FROM buyers WHERE buyer_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for edit buyer: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving buyer record.');
        redirect('modules/buyers/index.php');
    }

    $stmt->bind_param("i", $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $buyer = $result->fetch_assoc();
    $stmt->close();

    if (!$buyer) {
        flash_set('error', 'Buyer record not found.');
        redirect('modules/buyers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching buyer for edit: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving buyer record.');
    redirect('modules/buyers/index.php');
}

$pageTitle = 'Edit Buyer: ' . $buyer['buyer_name'];
$currentNav = 'buyers';

// Retrieve and clear any flashed form validation errors and previous input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Helper to determine field values (prefer old submitted input over database record)
$getValue = function (string $field) use ($old, $buyer): string {
    if (isset($old[$field])) {
        return (string) $old[$field];
    }
    return (string) ($buyer[$field] ?? '');
};

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Buyer</h1>
        <div class="page-subtitle">Update profile for Buyer #<?= (int) $buyer['buyer_id'] ?> &bull; <?= e($buyer['buyer_name']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/buyers/view.php?id=' . (int)$buyer['buyer_id'])) ?>" class="btn btn-secondary">View Profile</a>
        <a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>Edit Buyer Details</h2>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/buyers/update.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="buyer_id" value="<?= (int) $buyer['buyer_id'] ?>">

            <div class="form-group">
                <label for="buyer_name" class="form-label">Buyer Name <span class="required">*</span></label>
                <input type="text"
                       id="buyer_name"
                       name="buyer_name"
                       class="form-control <?= isset($errors['buyer_name']) ? 'has-error' : '' ?>"
                       maxlength="150"
                       required
                       value="<?= e($getValue('buyer_name')) ?>"
                       placeholder="e.g., Global Apparel Brands Ltd.">
                <?php if (isset($errors['buyer_name'])): ?>
                    <span class="form-error"><?= e($errors['buyer_name']) ?></span>
                <?php endif; ?>
                <span class="form-text">Primary organizational or corporate buyer name (maximum 150 characters).</span>
            </div>

            <div class="form-group">
                <label for="contact_person" class="form-label">Contact Person</label>
                <input type="text"
                       id="contact_person"
                       name="contact_person"
                       class="form-control <?= isset($errors['contact_person']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       value="<?= e($getValue('contact_person')) ?>"
                       placeholder="e.g., John Smith">
                <?php if (isset($errors['contact_person'])): ?>
                    <span class="form-error"><?= e($errors['contact_person']) ?></span>
                <?php endif; ?>
                <span class="form-text">Primary representative or merchandiser contact (maximum 100 characters).</span>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control <?= isset($errors['email']) ? 'has-error' : '' ?>"
                       maxlength="150"
                       value="<?= e($getValue('email')) ?>"
                       placeholder="e.g., procurement@globalapparel.com">
                <?php if (isset($errors['email'])): ?>
                    <span class="form-error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
                <span class="form-text">Official contact email for correspondence and purchase orders.</span>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Phone</label>
                <input type="text"
                       id="phone"
                       name="phone"
                       class="form-control <?= isset($errors['phone']) ? 'has-error' : '' ?>"
                       maxlength="30"
                       value="<?= e($getValue('phone')) ?>"
                       placeholder="e.g., +1-555-0199">
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
                       placeholder="e.g., Germany, United Kingdom, USA">
                <?php if (isset($errors['country'])): ?>
                    <span class="form-error"><?= e($errors['country']) ?></span>
                <?php endif; ?>
                <span class="form-text">Country of buyer headquarters or main sourcing office (maximum 100 characters).</span>
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Address</label>
                <textarea id="address"
                          name="address"
                          class="form-control <?= isset($errors['address']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="Corporate office or billing address..."><?= e($getValue('address')) ?></textarea>
                <?php if (isset($errors['address'])): ?>
                    <span class="form-error"><?= e($errors['address']) ?></span>
                <?php endif; ?>
                <span class="form-text">Full office, warehouse, or billing postal address.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Buyer</button>
                <a href="<?= e(url('modules/buyers/view.php?id=' . (int)$buyer['buyer_id'])) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
