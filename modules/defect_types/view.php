<?php
/**
 * ThreadFlow - Defect Type Details View
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

// Positive integer ID validation
$defectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$defectId || $defectId <= 0) {
    flash_set('error', 'Invalid defect type ID specified.');
    redirect('modules/defect_types/index.php');
}

// Fetch defect type details safely using prepared statements
try {
    $stmt = $conn->prepare("SELECT defect_id, defect_code, defect_name, category, severity, description, is_active, created_at, updated_at FROM defect_types WHERE defect_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for defect type details: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving defect type details.');
        redirect('modules/defect_types/index.php');
    }

    $stmt->bind_param("i", $defectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $defect = $result->fetch_assoc();
    $stmt->close();

    // Safe handling of missing records
    if (!$defect) {
        flash_set('error', 'Defect type record not found.');
        redirect('modules/defect_types/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching defect type details: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving defect type details.');
    redirect('modules/defect_types/index.php');
}

$pageTitle = 'Defect: ' . $defect['defect_code'];
$currentNav = 'defect_types';

$sevClass = 'badge-secondary';
if ($defect['severity'] === 'minor') {
    $sevClass = 'badge-info';
} elseif ($defect['severity'] === 'major') {
    $sevClass = 'badge-warning';
} elseif ($defect['severity'] === 'critical') {
    $sevClass = 'badge-danger';
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Scoped Dossier Styles -->
<style>
.dossier-wrapper {
    max-width: 900px;
    margin: 0 auto;
}
.dossier-hero {
    background: var(--color-surface);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1.25rem;
}
.dossier-code-pill {
    display: inline-block;
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--color-primary);
    background: var(--color-primary-subtle);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 0.35rem 0.85rem;
    margin-bottom: 0.5rem;
    letter-spacing: 0.5px;
}
.dossier-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: 0.35rem;
}
.dossier-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
.dossier-stat-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
}
.dossier-stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--color-text-muted);
    letter-spacing: 0.5px;
}
.dossier-stat-value {
    font-size: 1.15rem;
    font-weight: 700;
    margin-top: 0.25rem;
    color: var(--color-text);
}
.dossier-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.dossier-card-header {
    background: var(--color-surface-subtle);
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid var(--color-border-subtle);
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-primary-dark);
}
.dossier-card-body {
    padding: 1.25rem;
}
.btn-action-activate {
    background-color: var(--color-success);
    border: 1px solid var(--color-success);
    color: #FFFFFF;
}
.btn-action-activate:hover {
    background-color: #166534;
    border-color: #166534;
    color: #FFFFFF;
}
</style>

<div class="page-header">
    <div class="page-title-group">
        <h1>Defect Type Profile</h1>
        <div class="page-subtitle">Defect ID #<?= (int) $defect['defect_id'] ?> &bull; Operational Quality Dossier</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-secondary">
            &larr; Back to Defect List
        </a>
        <a href="<?= e(url('modules/defect_types/edit.php?id=' . (int)$defect['defect_id'])) ?>" class="btn btn-primary">
            Edit Defect Type
        </a>
        <form action="<?= e(url('modules/defect_types/toggle_status.php')) ?>" method="post" style="display: contents;">
            <?= csrf_field() ?>
            <input type="hidden" name="defect_id" value="<?= (int) $defect['defect_id'] ?>">
            <input type="hidden" name="from_view" value="1">
            <?php if ((int)$defect['is_active'] === 1): ?>
                <button type="submit" class="btn btn-danger" data-confirm="Are you sure you want to deactivate this defect type?">
                    Deactivate Defect
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-action-activate" data-confirm="Are you sure you want to reactivate this defect type?">
                    Reactivate Defect
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="dossier-wrapper">
    <!-- Hero Banner -->
    <div class="dossier-hero">
        <div>
            <div class="dossier-code-pill"><code><?= e($defect['defect_code']) ?></code></div>
            <div class="dossier-title"><?= e($defect['defect_name']) ?></div>
            <div class="text-xs text-muted">
                Taxonomy Category: <strong><?= e($defect['category']) ?></strong> &bull; Registered on <?= e(date('M d, Y', strtotime($defect['created_at']))) ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge <?= $sevClass ?> font-bold" style="font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                <?= ucfirst(e($defect['severity'])) ?> Severity
            </span>
            <?php if ((int)$defect['is_active'] === 1): ?>
                <span class="badge badge-success font-bold" style="font-size: 0.8rem; padding: 0.35rem 0.85rem;">Active</span>
            <?php else: ?>
                <span class="badge badge-danger font-bold" style="font-size: 0.8rem; padding: 0.35rem 0.85rem;">Inactive</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Operational Grid -->
    <div class="dossier-grid">
        <div class="dossier-stat-card">
            <div class="dossier-stat-label">Operational Department</div>
            <div class="dossier-stat-value"><?= e($defect['category']) ?></div>
            <div class="text-xs text-muted mt-1">Responsible workstation</div>
        </div>

        <div class="dossier-stat-card">
            <div class="dossier-stat-label">Severity Tier Impact</div>
            <div class="dossier-stat-value">
                <span class="badge <?= $sevClass ?>"><?= ucfirst(e($defect['severity'])) ?></span>
            </div>
            <div class="text-xs text-muted mt-1">
                <?php if ($defect['severity'] === 'minor'): ?>
                    Cosmetic or minor variance
                <?php elseif ($defect['severity'] === 'major'): ?>
                    Noticeable fault; risk of buyer claim
                <?php elseif ($defect['severity'] === 'critical'): ?>
                    Severe failure; zero tolerance
                <?php endif; ?>
            </div>
        </div>

        <div class="dossier-stat-card">
            <div class="dossier-stat-label">Floor Audit Availability</div>
            <div class="dossier-stat-value">
                <?php if ((int)$defect['is_active'] === 1): ?>
                    <span style="color: var(--color-success); font-size: 0.95rem;">Active for Logging</span>
                <?php else: ?>
                    <span style="color: var(--color-danger); font-size: 0.95rem;">Archived / Disabled</span>
                <?php endif; ?>
            </div>
            <div class="text-xs text-muted mt-1">Inline &amp; end-of-line inspections</div>
        </div>
    </div>

    <!-- Inspection Criteria Card -->
    <div class="dossier-card">
        <div class="dossier-card-header">
            Inspection Criteria &amp; Acceptance Tolerance
        </div>
        <div class="dossier-card-body">
            <?php if (!empty($defect['description'])): ?>
                <div style="font-size: 0.95rem; line-height: 1.65; white-space: pre-line; color: var(--color-text);">
                    <?= e($defect['description']) ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No specific checkpoint description or tolerance criteria entered for this defect code.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metadata & Audit Card -->
    <div class="dossier-card">
        <div class="dossier-card-header">
            Audit Traceability &amp; System Metadata
        </div>
        <div class="dossier-card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table mb-0">
                    <tbody>
                        <tr>
                            <th style="width: 220px;">System Record ID</th>
                            <td>#<?= (int) $defect['defect_id'] ?></td>
                        </tr>
                        <tr>
                            <th>Created Timestamp</th>
                            <td><?= e($defect['created_at']) ?></td>
                        </tr>
                        <tr>
                            <th>Last Modification</th>
                            <td><?= e($defect['updated_at']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
