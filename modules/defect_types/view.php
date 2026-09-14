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

<div class="page-header">
    <div class="page-title-group">
        <h1>Defect Type Profile</h1>
        <div class="page-subtitle">Defect ID #<?= (int) $defect['defect_id'] ?> &bull; <?= e($defect['defect_code']) ?> &mdash; <?= e($defect['defect_name']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-secondary">Back to Defect List</a>
        <a href="<?= e(url('modules/defect_types/edit.php?id=' . (int)$defect['defect_id'])) ?>" class="btn btn-secondary">Edit Defect Type</a>
        <form action="<?= e(url('modules/defect_types/toggle_status.php')) ?>" method="post" style="display: contents;">
            <?= csrf_field() ?>
            <input type="hidden" name="defect_id" value="<?= (int) $defect['defect_id'] ?>">
            <input type="hidden" name="from_view" value="1">
            <?php if ((int)$defect['is_active'] === 1): ?>
                <button type="submit" class="btn btn-danger" data-confirm="Are you sure you want to deactivate this defect type?">
                    Deactivate Defect
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary" data-confirm="Are you sure you want to reactivate this defect type?">
                    Reactivate Defect
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card card-medium">
    <div class="card-header">
        <h2><?= e($defect['defect_name']) ?></h2>
        <div class="d-flex align-items-center gap-2">
            <span class="badge <?= $sevClass ?>"><?= ucfirst(e($defect['severity'])) ?></span>
            <?php if ((int)$defect['is_active'] === 1): ?>
                <span class="badge badge-success">Active</span>
            <?php else: ?>
                <span class="badge badge-danger">Inactive</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 220px;">Defect ID</th>
                        <td>#<?= (int) $defect['defect_id'] ?></td>
                    </tr>
                    <tr>
                        <th>Defect Code</th>
                        <td><strong><?= e($defect['defect_code']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Defect Name</th>
                        <td><strong><?= e($defect['defect_name']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?= e($defect['category']) ?></td>
                    </tr>
                    <tr>
                        <th>Severity Tier</th>
                        <td>
                            <span class="badge <?= $sevClass ?>"><?= ucfirst(e($defect['severity'])) ?></span>
                            <?php if ($defect['severity'] === 'minor'): ?>
                                <span class="text-muted text-sm ml-2">Cosmetic or minor variance; does not affect garment usability</span>
                            <?php elseif ($defect['severity'] === 'major'): ?>
                                <span class="text-muted text-sm ml-2">Noticeable quality defect likely to result in customer dissatisfaction</span>
                            <?php elseif ($defect['severity'] === 'critical'): ?>
                                <span class="text-muted text-sm ml-2">Severe failure causing non-conformance, safety risk, or batch rejection</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Description / Inspection Criteria</th>
                        <td>
                            <?php if (!empty($defect['description'])): ?>
                                <?= nl2br(e($defect['description'])) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Operational Status</th>
                        <td>
                            <?php if ((int)$defect['is_active'] === 1): ?>
                                <span class="badge badge-success">Active</span>
                                <span class="text-muted text-sm ml-2">Available for inline QC inspection and end-of-line auditing</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                                <span class="text-muted text-sm ml-2">Archived; disabled from new QC defect logging</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Record Created</th>
                        <td><?= e($defect['created_at']) ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= e($defect['updated_at']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
