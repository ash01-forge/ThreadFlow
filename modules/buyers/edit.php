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

<style>
.buyers-edit { --buyers-ink: #3c1b25; --buyers-muted: #806b72; --buyers-line: #efd9df; --buyers-soft: #fff7f8; }
.buyers-edit .buyers-edit-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 20px; }
.buyers-edit .buyers-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.buyers-edit .buyers-summary { display: grid; grid-template-columns: 1.5fr repeat(3, 1fr); gap: 10px; max-width: 920px; margin-bottom: 20px; }
.buyers-edit .buyers-summary-card { padding: 14px; border: 1px solid var(--buyers-line); border-radius: 10px; background: #fffafd; }
.buyers-edit .buyers-summary-card small { display: block; color: var(--buyers-muted); font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; }
.buyers-edit .buyers-summary-card strong { display: block; margin-top: 5px; color: var(--buyers-ink); overflow-wrap: anywhere; }
.buyers-edit .buyers-form-card { max-width: 920px; overflow: hidden; border: 1px solid var(--buyers-line); border-radius: 14px; background: #fff; }
.buyers-edit .buyers-section { padding: 24px; border-bottom: 1px solid var(--buyers-line); }
.buyers-edit .buyers-section-head { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 20px; }
.buyers-edit .buyers-step { display: grid; place-items: center; flex: 0 0 30px; width: 30px; height: 30px; border-radius: 50%; color: #fff; background: #78000f; font-weight: 700; }
.buyers-edit .buyers-section h2 { margin: 0 0 4px; color: var(--buyers-ink); font-size: 1.1rem; }
.buyers-edit .buyers-section-head p { margin: 0; color: var(--buyers-muted); font-size: .84rem; }
.buyers-edit .buyers-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.buyers-edit .buyers-fields .form-group { margin: 0; }
.buyers-edit .buyers-wide { grid-column: 1 / -1; }
.buyers-edit .buyers-submit { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 24px; background: var(--buyers-soft); }
.buyers-edit .buyers-submit .form-actions { margin: 0; }
@media (max-width: 700px) { .buyers-edit .buyers-edit-head { display: block; } .buyers-edit .buyers-edit-head .page-actions { margin-top: 14px; } .buyers-edit .buyers-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .buyers-edit .buyers-fields { grid-template-columns: 1fr; } .buyers-edit .buyers-wide { grid-column: auto; } .buyers-edit .buyers-submit { display: block; } .buyers-edit .buyers-submit .form-actions { margin-top: 14px; } .buyers-edit .buyers-submit .btn { width: 100%; margin-bottom: 8px; } }
</style>
<div class="buyers-edit"><div class="buyers-edit-head"><div><div class="buyers-kicker">Buyer maintenance</div><div class="page-title-group"><h1>Edit Buyer</h1><div class="page-subtitle">Update profile for Buyer #<?= (int) $buyer['buyer_id'] ?> &bull; <?= e($buyer['buyer_name']) ?></div></div></div><div class="page-actions"><a href="<?= e(url('modules/buyers/view.php?id=' . (int)$buyer['buyer_id'])) ?>" class="btn btn-secondary">View Profile</a><a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Back to List</a></div></div><div class="buyers-summary"><div class="buyers-summary-card"><small>Buyer</small><strong><?= e($buyer['buyer_name']) ?></strong></div><div class="buyers-summary-card"><small>Status</small><strong><?= (int) $buyer['is_active'] === 1 ? 'Active' : 'Inactive' ?></strong></div><div class="buyers-summary-card"><small>Country</small><strong><?= e($buyer['country'] ?: 'Not specified') ?></strong></div><div class="buyers-summary-card"><small>Buyer ID</small><strong>#<?= (int) $buyer['buyer_id'] ?></strong></div></div>
<div class="buyers-form-card"><form action="<?= e(url('modules/buyers/update.php')) ?>" method="post" novalidate><?= csrf_field() ?><input type="hidden" name="buyer_id" value="<?= (int) $buyer['buyer_id'] ?>"><section class="buyers-section"><div class="buyers-section-head"><span class="buyers-step">1</span><div><h2>Company Identity</h2><p>Update the buyer organization name used across ThreadFlow.</p></div></div><div class="buyers-fields"><div class="form-group buyers-wide">
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
            </div></div></section><section class="buyers-section"><div class="buyers-section-head"><span class="buyers-step">2</span><div><h2>Contact Information</h2><p>Maintain the representative and communication channels.</p></div></div><div class="buyers-fields"><div class="form-group">
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
            </div><div class="form-group">
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
            </div><div class="form-group">
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
            </div></div></section><section class="buyers-section"><div class="buyers-section-head"><span class="buyers-step">3</span><div><h2>Address / Location</h2><p>Keep the buyer’s headquarters, sourcing, or billing location current.</p></div></div><div class="buyers-fields"><div class="form-group">
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
            </div></div></section><div class="buyers-submit"><div><strong>Ready to save?</strong><p>Changes will use the existing buyer update workflow.</p></div><div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Buyer</button>
                <a href="<?= e(url('modules/buyers/view.php?id=' . (int)$buyer['buyer_id'])) ?>" class="btn btn-secondary">Cancel</a>
            </div></div></form></div></div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
