<?php
/**
 * ThreadFlow - Edit Defect Type Form
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

// Validate positive integer ID
$defectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$defectId || $defectId <= 0) {
    flash_set('error', 'Invalid defect type ID specified.');
    redirect('modules/defect_types/index.php');
}

// Fetch existing defect type record safely using prepared statements
try {
    $stmt = $conn->prepare("SELECT defect_id, defect_code, defect_name, category, severity, description, is_active FROM defect_types WHERE defect_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for edit defect type: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving defect type record.');
        redirect('modules/defect_types/index.php');
    }

    $stmt->bind_param("i", $defectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $defect = $result->fetch_assoc();
    $stmt->close();

    if (!$defect) {
        flash_set('error', 'Defect type record not found.');
        redirect('modules/defect_types/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching defect type for edit: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving defect type record.');
    redirect('modules/defect_types/index.php');
}

$pageTitle = 'Edit Defect: ' . $defect['defect_code'];
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

// Helper to determine field values (prefer old submitted input over database record)
$getValue = function (string $field) use ($old, $defect): string {
    if (isset($old[$field])) {
        return (string) $old[$field];
    }
    return (string) ($defect[$field] ?? '');
};

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Defect Type</h1>
        <div class="page-subtitle">Update record for Defect #<?= (int) $defect['defect_id'] ?> &bull; <?= e($defect['defect_code']) ?> &mdash; <?= e($defect['defect_name']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/defect_types/view.php?id=' . (int)$defect['defect_id'])) ?>" class="btn btn-secondary">View Profile</a>
        <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<!-- Scoped Edit Styles -->
<style>
.form-section-card {
    max-width: 860px;
    margin: 0 auto 1.5rem;
}
.record-summary-banner {
    max-width: 860px;
    margin: 0 auto 1.25rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-lg);
    padding: 1.15rem 1.35rem;
    box-shadow: var(--shadow-xs);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.record-summary-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.record-summary-code {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--color-primary);
    background: var(--color-primary-subtle);
    padding: 0.25rem 0.65rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
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

<!-- Record Summary Header Card -->
<div class="record-summary-banner">
    <div>
        <div class="text-xs text-muted mb-1">RECORD SUMMARY #<?= (int) $defect['defect_id'] ?></div>
        <div class="record-summary-meta">
            <span class="record-summary-code"><code><?= e($defect['defect_code']) ?></code></span>
            <strong><?= e($defect['defect_name']) ?></strong>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge badge-muted"><?= e($defect['category']) ?></span>
        <?php
        $curSevClass = 'badge-secondary';
        if ($defect['severity'] === 'minor') {
            $curSevClass = 'badge-info';
        } elseif ($defect['severity'] === 'major') {
            $curSevClass = 'badge-warning';
        } elseif ($defect['severity'] === 'critical') {
            $curSevClass = 'badge-danger';
        }
        ?>
        <span class="badge <?= $curSevClass ?>"><?= ucfirst(e($defect['severity'])) ?></span>
        <?php if ((int)$defect['is_active'] === 1): ?>
            <span class="badge badge-success">Active</span>
        <?php else: ?>
            <span class="badge badge-danger">Inactive</span>
        <?php endif; ?>
    </div>
</div>

<div class="card form-section-card">
    <div class="card-header">
        <div>
            <h2>Update Defect Parameters</h2>
            <div class="text-xs text-muted">Modify taxonomy, operational category, or inspection threshold notes</div>
        </div>
        <span class="badge badge-primary">Edit Mode</span>
    </div>

    <form action="<?= e(url('modules/defect_types/update.php')) ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="defect_id" value="<?= (int) $defect['defect_id'] ?>">

        <!-- Section 1: Identification & Taxonomy -->
        <div class="form-section-header">
            <h3 class="form-section-title">1. Identification &amp; Nomenclature</h3>
            <span class="badge badge-muted text-xs">Primary Keys</span>
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
                           value="<?= e($getValue('defect_code')) ?>"
                           placeholder="e.g., SEW-001">
                    <?php if (isset($errors['defect_code'])): ?>
                        <span class="form-error"><?= e($errors['defect_code']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Unique identification code for QC routing (max 50 chars).</span>
                </div>

                <div class="form-group mb-0">
                    <label for="defect_name" class="form-label">Defect Name <span class="required">*</span></label>
                    <input type="text"
                           id="defect_name"
                           name="defect_name"
                           class="form-control <?= isset($errors['defect_name']) ? 'has-error' : '' ?>"
                           maxlength="150"
                           required
                           value="<?= e($getValue('defect_name')) ?>"
                           placeholder="e.g., Broken Stitch">
                    <?php if (isset($errors['defect_name'])): ?>
                        <span class="form-error"><?= e($errors['defect_name']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Descriptive title of the fault classification (max 150 chars).</span>
                </div>
            </div>
        </div>

        <!-- Section 2: Classification & Quality Gate -->
        <div class="form-section-header">
            <h3 class="form-section-title">2. Operational Domain &amp; Severity Tier</h3>
            <span class="badge badge-muted text-xs">Quality Thresholds</span>
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
                            <option value="<?= e($cat) ?>" <?= $getValue('category') === $cat ? 'selected' : '' ?>>
                                <?= e($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category'])): ?>
                        <span class="form-error"><?= e($errors['category']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Operational department or inspection fault domain.</span>
                </div>

                <div class="form-group mb-0">
                    <label for="severity" class="form-label">Severity Tier <span class="required">*</span></label>
                    <select id="severity"
                            name="severity"
                            class="form-control <?= isset($errors['severity']) ? 'has-error' : '' ?>"
                            required>
                        <option value="">-- Select Severity Tier --</option>
                        <?php foreach ($allowedSeverities as $sevKey => $sevLabel): ?>
                            <option value="<?= e($sevKey) ?>" <?= $getValue('severity') === $sevKey ? 'selected' : '' ?>>
                                <?= e($sevLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['severity'])): ?>
                        <span class="form-error"><?= e($errors['severity']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Quality threshold impact level: Minor, Major, or Critical.</span>
                </div>
            </div>

            <div class="severity-guide-box">
                <strong>Threshold Reminders:</strong>
                Reclassifying severity affects past audits via reporting aggregates and future inline inspection tolerance rules.
            </div>
        </div>

        <!-- Section 3: Criteria & Guidance -->
        <div class="form-section-header">
            <h3 class="form-section-title">3. Description &amp; Inspection Criteria</h3>
            <span class="badge badge-muted text-xs">Floor Instructions</span>
        </div>
        <div class="form-section-body">
            <div class="form-group mb-0">
                <label for="description" class="form-label">Description / Inspection Criteria</label>
                <textarea id="description"
                          name="description"
                          class="form-control <?= isset($errors['description']) ? 'has-error' : '' ?>"
                          rows="3"
                          placeholder="Inspection checkpoints, tolerance guidelines, root causes, or corrective instructions..."><?= e($getValue('description')) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <span class="form-error"><?= e($errors['description']) ?></span>
                <?php endif; ?>
                <span class="form-text">Optional technical notes, acceptable tolerances, or remedial actions.</span>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-xs text-muted">All changes are recorded in the audit trace log.</span>
            <div class="form-actions mt-0">
                <a href="<?= e(url('modules/defect_types/view.php?id=' . (int)$defect['defect_id'])) ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Defect Type</button>
            </div>
        </div>
    </form>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
