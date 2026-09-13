<?php
/**
 * ThreadFlow - Add Buyer Form (Preserved Test Form)
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 * Note: Form submission handler (store.php) is disabled until implemented by Member 1.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

$pageTitle = 'Add Buyer';
$currentNav = 'buyers';

$handlerExists = file_exists(__DIR__ . '/store.php');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Add Buyer</h1>
        <div class="page-subtitle">Module 1 &bull; Ownership: Member 1 &bull; Access: Admin, Merchandiser</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Back to Buyer List</a>
    </div>
</div>

<?php if (!$handlerExists): ?>
    <div class="alert alert-warning mb-4" role="alert">
        <div>
            <strong>Notice: Submission Handler Not Implemented Yet</strong>
            <p class="mt-2 text-sm">
                The form submission handler (<code>modules/buyers/store.php</code>) has not yet been built by Member 1.
                The <strong>Save Buyer</strong> button is temporarily disabled to prevent broken submissions.
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 650px;">
    <div class="card-header">
        <h2>New Buyer Details</h2>
    </div>
    <div class="card-body">
        <form action="store.php" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="buyer_name" class="form-label">Buyer Name <span class="required">*</span></label>
                <input type="text"
                       id="buyer_name"
                       name="buyer_name"
                       class="form-control"
                       maxlength="150"
                       required
                       placeholder="e.g., Global Apparel Brands Ltd.">
            </div>

            <div class="form-group">
                <label for="contact_person" class="form-label">Contact Person</label>
                <input type="text"
                       id="contact_person"
                       name="contact_person"
                       class="form-control"
                       maxlength="100"
                       placeholder="e.g., John Smith">
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control"
                       maxlength="150"
                       placeholder="e.g., procurement@globalapparel.com">
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Phone</label>
                <input type="text"
                       id="phone"
                       name="phone"
                       class="form-control"
                       maxlength="30"
                       placeholder="e.g., +1-555-0199">
            </div>

            <div class="form-group">
                <label for="country" class="form-label">Country</label>
                <input type="text"
                       id="country"
                       name="country"
                       class="form-control"
                       maxlength="100"
                       placeholder="e.g., Germany, United Kingdom, USA">
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Address</label>
                <textarea id="address"
                          name="address"
                          class="form-control"
                          rows="3"
                          placeholder="Corporate office or billing address..."></textarea>
            </div>

            <div class="mt-4">
                <?php if ($handlerExists): ?>
                    <button type="submit" class="btn btn-primary">Save Buyer</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-disabled" disabled aria-disabled="true" title="Handler store.php does not exist yet">
                        Save Buyer (Handler Not Implemented)
                    </button>
                <?php endif; ?>
                <a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary ml-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>