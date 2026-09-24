<?php
/**
 * ThreadFlow - Add Buyer Form
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

$pageTitle = 'Add Buyer';
$currentNav = 'buyers';

// Retrieve and clear any flashed form validation errors and previous input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.buyers-form { --buyers-ink: #3c1b25; --buyers-muted: #806b72; --buyers-line: #efd9df; --buyers-soft: #fff7f8; }
.buyers-form .buyers-form-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 24px; }
.buyers-form .buyers-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.buyers-form .buyers-form-card { max-width: 920px; overflow: hidden; border: 1px solid var(--buyers-line); border-radius: 14px; background: #fff; }
.buyers-form .buyers-section { padding: 24px; border-bottom: 1px solid var(--buyers-line); }
.buyers-form .buyers-section-head { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 20px; }
.buyers-form .buyers-step { display: grid; place-items: center; flex: 0 0 30px; width: 30px; height: 30px; border-radius: 50%; color: #fff; background: #78000f; font-weight: 700; }
.buyers-form .buyers-section h2 { margin: 0 0 4px; color: var(--buyers-ink); font-size: 1.1rem; }
.buyers-form .buyers-section-head p { margin: 0; color: var(--buyers-muted); font-size: .84rem; }
.buyers-form .buyers-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.buyers-form .buyers-fields .form-group { margin: 0; }
.buyers-form .buyers-wide { grid-column: 1 / -1; }
.buyers-form .buyers-submit { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 24px; background: var(--buyers-soft); }
.buyers-form .buyers-submit p { margin: 0; color: var(--buyers-muted); font-size: .82rem; }
.buyers-form .buyers-submit .form-actions { margin: 0; }
@media (max-width: 650px) { .buyers-form .buyers-form-head { display: block; } .buyers-form .buyers-form-head .page-actions { margin-top: 14px; } .buyers-form .buyers-fields { grid-template-columns: 1fr; } .buyers-form .buyers-wide { grid-column: auto; } .buyers-form .buyers-submit { display: block; } .buyers-form .buyers-submit .form-actions { margin-top: 14px; } .buyers-form .buyers-submit .btn { width: 100%; margin-bottom: 8px; } }
</style>
<div class="buyers-form"><div class="buyers-form-head"><div><div class="buyers-kicker">Buyer onboarding</div><div class="page-title-group"><h1>Add Buyer</h1><div class="page-subtitle">Create a buyer profile for merchandising and production workflows.</div></div></div><div class="page-actions"><a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Back to Buyer List</a></div></div>
<div class="buyers-form-card"><form action="<?= e(url('modules/buyers/store.php')) ?>" method="post" novalidate><?= csrf_field() ?><section class="buyers-section"><div class="buyers-section-head"><span class="buyers-step">1</span><div><h2>Company Identity</h2><p>Start with the organization name used across ThreadFlow.</p></div></div><div class="buyers-fields"><div class="form-group buyers-wide">
                <label for="buyer_name" class="form-label">Buyer Name <span class="required">*</span></label>
                <input type="text"
                       id="buyer_name"
                       name="buyer_name"
                       class="form-control <?= isset($errors['buyer_name']) ? 'has-error' : '' ?>"
                       maxlength="150"
                       required
                       value="<?= e($old['buyer_name'] ?? '') ?>"
                       placeholder="e.g., Global Apparel Brands Ltd.">
                <?php if (isset($errors['buyer_name'])): ?>
                    <span class="form-error"><?= e($errors['buyer_name']) ?></span>
                <?php endif; ?>
                <span class="form-text">Primary organizational or corporate buyer name (maximum 150 characters).</span>
            </div></div></section><section class="buyers-section"><div class="buyers-section-head"><span class="buyers-step">2</span><div><h2>Contact Information</h2><p>Provide the primary representative and direct communication channels.</p></div></div><div class="buyers-fields"><div class="form-group">
                <label for="contact_person" class="form-label">Contact Person</label>
                <input type="text"
                       id="contact_person"
                       name="contact_person"
                       class="form-control <?= isset($errors['contact_person']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       value="<?= e($old['contact_person'] ?? '') ?>"
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
                       value="<?= e($old['email'] ?? '') ?>"
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
                       value="<?= e($old['phone'] ?? '') ?>"
                       placeholder="e.g., +1-555-0199">
                <?php if (isset($errors['phone'])): ?>
                    <span class="form-error"><?= e($errors['phone']) ?></span>
                <?php endif; ?>
                <span class="form-text">Contact telephone or direct mobile line (maximum 30 characters).</span>
            </div></div></section><section class="buyers-section"><div class="buyers-section-head"><span class="buyers-step">3</span><div><h2>Address / Location</h2><p>Record the buyer’s headquarters, sourcing, or billing location.</p></div></div><div class="buyers-fields"><div class="form-group">
                <label for="country" class="form-label">Country</label>
                <input type="text"
                       id="country"
                       name="country"
                       class="form-control <?= isset($errors['country']) ? 'has-error' : '' ?>"
                       maxlength="100"
                       value="<?= e($old['country'] ?? '') ?>"
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
                          placeholder="Corporate office or billing address..."><?= e($old['address'] ?? '') ?></textarea>
                <?php if (isset($errors['address'])): ?>
                    <span class="form-error"><?= e($errors['address']) ?></span>
                <?php endif; ?>
                <span class="form-text">Full office, warehouse, or billing postal address.</span>
            </div></div></section><div class="buyers-submit"><div><strong>Ready to save?</strong><p>The buyer will be available for the existing workflow.</p></div><div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Buyer</button>
                <a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Cancel</a>
            </div></div></form></div></div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>