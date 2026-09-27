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

<!-- Scoped Form Styles -->
<style>
.form-section-card {
    max-width: 820px;
    margin: 0 auto 1.5rem;
}
.form-section-header {
    border-bottom: 1px solid var(--color-border-subtle);
    padding: 0.9rem 1.35rem;
    background: var(--color-surface-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.form-section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-primary-dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.form-section-body {
    padding: 1.35rem;
}
.form-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
}
.severity-guide-box {
    background: #FFFDF9;
    border: 1px solid #FDE68A;
    border-radius: var(--radius-md);
    padding: 0.85rem 1rem;
    font-size: 0.82rem;
    color: #92400E;
    margin-top: 0.5rem;
    line-height: 1.45;
}
</style>

<div class="card form-section-card">
    <div class="card-header">
        <div>
            <h2>New Defect Type Definition</h2>
            <div class="text-xs text-muted">Register a standardized quality fault classification code</div>
        </div>
        <span class="badge badge-primary">Catalog Entry</span>
    </div>
    
    <form action="<?= e(url('modules/defect_types/store.php')) ?>" method="post" novalidate>
        <?= csrf_field() ?>

        <!-- Section 1: Identification & Taxonomy -->
        <div class="form-section-header">
            <h3 class="form-section-title">
                <span>1. Identification &amp; Nomenclature</span>
            </h3>
            <span class="badge badge-muted text-xs">Required Identifiers</span>
        </div>
        <div class="form-section-body">
            <div class="form-grid-2">
                <div class="form-group mb-0">
                    <label for="defect_code" class="form-label">Defect Code <span class="required">*</span></label>
                    <input type="text"
                           id="defect_code"
                           name="defect_code"
                           class="form-control <?= isset($errors['defect_code']) ? 'has-error' : '' ?>"
                           maxlength="50"
                           required
                           value="<?= e($old['defect_code'] ?? '') ?>"
                           placeholder="e.g., SEW-001, FAB-010">
                    <?php if (isset($errors['defect_code'])): ?>
                        <span class="form-error"><?= e($errors['defect_code']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Unique alphanumeric code used in floor logs (max 50 chars).</span>
                </div>

                <div class="form-group mb-0">
                    <label for="defect_name" class="form-label">Defect Name <span class="required">*</span></label>
                    <input type="text"
                           id="defect_name"
                           name="defect_name"
                           class="form-control <?= isset($errors['defect_name']) ? 'has-error' : '' ?>"
                           maxlength="150"
                           required
                           value="<?= e($old['defect_name'] ?? '') ?>"
                           placeholder="e.g., Broken Stitch, Oil Stain">
                    <?php if (isset($errors['defect_name'])): ?>
                        <span class="form-error"><?= e($errors['defect_name']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Descriptive name of fault classification (max 150 chars).</span>
                </div>
            </div>
        </div>

        <!-- Section 2: Classification & Quality Gate -->
        <div class="form-section-header">
            <h3 class="form-section-title">
                <span>2. Operational Domain &amp; Severity Tier</span>
            </h3>
            <span class="badge badge-muted text-xs">Routing Parameters</span>
        </div>
        <div class="form-section-body">
            <div class="form-grid-2">
                <div class="form-group mb-0">
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
                    <span class="form-text">Department or production station responsible.</span>
                </div>

                <div class="form-group mb-0">
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
                    <span class="form-text">Quality threshold impact level.</span>
                </div>
            </div>

            <div class="severity-guide-box">
                <strong>Severity Impact Rules:</strong>
                <strong>Minor:</strong> Cosmetic variance not impairing wearability &bull;
                <strong>Major:</strong> Defect liable to reduce salability or prompt buyer claim &bull;
                <strong>Critical:</strong> Immediate safety or contractual non-conformance causing batch halt.
            </div>
        </div>

        <!-- Section 3: Criteria & Guidance -->
        <div class="form-section-header">
            <h3 class="form-section-title">
                <span>3. Inspection Criteria &amp; Tolerance Guidelines</span>
            </h3>
            <span class="badge badge-muted text-xs">Floor Instructions</span>
        </div>
        <div class="form-section-body">
            <div class="form-group mb-0">
                <label for="description" class="form-label">Inspection Checkpoints &amp; Acceptance Tolerance</label>
                <textarea id="description"
                          name="description"
                          class="form-control <?= isset($errors['description']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="Specify inspection checkpoints, allowable millimeter tolerances, root causes, or corrective rework instructions..."><?= e($old['description'] ?? '') ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <span class="form-error"><?= e($errors['description']) ?></span>
                <?php endif; ?>
                <span class="form-text">Optional technical notes, remedial guidance, and QA audit benchmarks.</span>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-xs text-muted">Defect type will be activated immediately upon creation.</span>
            <div class="form-actions mt-0">
                <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Defect Type</button>
            </div>
        </div>
    </form>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
