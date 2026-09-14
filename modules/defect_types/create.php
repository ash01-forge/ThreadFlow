<?php
/**
 * ThreadFlow - Add Defect Type Form
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Add Defect Type';
$currentNav = 'defect_types';

$allowedCategories = ['Sewing', 'Fabric', 'Measurement', 'Finishing', 'Packaging', 'Other'];
$allowedSeverities = [
    'minor'    => 'Minor',
    'major'    => 'Major',
    'critical' => 'Critical',
];

// Retrieve and clear any flashed form validation errors and previous input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Add Defect Type</h1>
        <div class="page-subtitle">Module 4 &bull; Ownership: Member 4 &bull; Access: Admin, QC Officer</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-secondary">Back to Defect List</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>New Defect Type Details</h2>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/defect_types/store.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="defect_code" class="form-label">Defect Code <span class="required">*</span></label>
                <input type="text"
                       id="defect_code"
                       name="defect_code"
                       class="form-control <?= isset($errors['defect_code']) ? 'has-error' : '' ?>"
                       maxlength="50"
                       required
                       value="<?= e($old['defect_code'] ?? '') ?>"
                       placeholder="e.g., SEW-001, FAB-010, MEAS-002">
                <?php if (isset($errors['defect_code'])): ?>
                    <span class="form-error"><?= e($errors['defect_code']) ?></span>
                <?php endif; ?>
                <span class="form-text">Unique identification code for QC routing and reporting (maximum 50 characters).</span>
            </div>

            <div class="form-group">
                <label for="defect_name" class="form-label">Defect Name <span class="required">*</span></label>
                <input type="text"
                       id="defect_name"
                       name="defect_name"
                       class="form-control <?= isset($errors['defect_name']) ? 'has-error' : '' ?>"
                       maxlength="150"
                       required
                       value="<?= e($old['defect_name'] ?? '') ?>"
                       placeholder="e.g., Broken Stitch, Oil Stain, Shaded Fabric">
                <?php if (isset($errors['defect_name'])): ?>
                    <span class="form-error"><?= e($errors['defect_name']) ?></span>
                <?php endif; ?>
                <span class="form-text">Descriptive title of the fault or defect classification (maximum 150 characters).</span>
            </div>

            <div class="form-group">
                <label for="category" class="form-label">Category <span class="required">*</span></label>
                <select id="category"
                        name="category"
                        class="form-control <?= isset($errors['category']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Defect Category --</option>
                    <?php foreach ($allowedCategories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($old['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?>
                    <span class="form-error"><?= e($errors['category']) ?></span>
                <?php endif; ?>
                <span class="form-text">Operational department or inspection fault domain.</span>
            </div>

            <div class="form-group">
                <label for="severity" class="form-label">Severity Tier <span class="required">*</span></label>
                <select id="severity"
                        name="severity"
                        class="form-control <?= isset($errors['severity']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Severity Tier --</option>
                    <?php foreach ($allowedSeverities as $sevKey => $sevLabel): ?>
                        <option value="<?= e($sevKey) ?>" <?= ($old['severity'] ?? 'major') === $sevKey ? 'selected' : '' ?>>
                            <?= e($sevLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['severity'])): ?>
                    <span class="form-error"><?= e($errors['severity']) ?></span>
                <?php endif; ?>
                <span class="form-text">Quality threshold impact level: Minor, Major, or Critical.</span>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description / Inspection Criteria</label>
                <textarea id="description"
                          name="description"
                          class="form-control <?= isset($errors['description']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="Inspection checkpoints, tolerance guidelines, root causes, or corrective instructions..."><?= e($old['description'] ?? '') ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <span class="form-error"><?= e($errors['description']) ?></span>
                <?php endif; ?>
                <span class="form-text">Optional technical notes, acceptable tolerances, or remedial actions.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Defect Type</button>
                <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
