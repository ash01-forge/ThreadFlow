<?php
/**
 * ThreadFlow - Defect Type Management Directory
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Defect Type Management';
$currentNav = 'defect_types';

// Allowed category and severity domain values
$allowedCategories = ['Sewing', 'Fabric', 'Measurement', 'Finishing', 'Packaging', 'Other'];
$allowedSeverities = ['minor', 'major', 'critical'];

// Extract and sanitize search and filter query parameters
$search         = trim($_GET['q'] ?? '');
$categoryFilter = trim($_GET['category'] ?? 'all');
$severityFilter = trim($_GET['severity'] ?? 'all');
$statusFilter   = trim($_GET['status'] ?? 'all');

// Build query with prepared statements
$whereClauses = [];
$types = '';
$params = [];

// Search across defect_code, defect_name, and description
if ($search !== '') {
    $whereClauses[] = "(defect_code LIKE ? OR defect_name LIKE ? OR description LIKE ?)";
    $wildcard = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
}

// Filter by category
if ($categoryFilter !== 'all' && in_array($categoryFilter, $allowedCategories, true)) {
    $whereClauses[] = "category = ?";
    $types .= 's';
    $params[] = $categoryFilter;
}

// Filter by severity
if ($severityFilter !== 'all' && in_array($severityFilter, $allowedSeverities, true)) {
    $whereClauses[] = "severity = ?";
    $types .= 's';
    $params[] = $severityFilter;
}

// Filter by active status
$statusVal = ($statusFilter === 'active') ? 1 : (($statusFilter === 'inactive') ? 0 : null);
if ($statusVal !== null) {
    $whereClauses[] = "is_active = ?";
    $types .= 'i';
    $params[] = $statusVal;
}

$sql = "SELECT defect_id, defect_code, defect_name, category, severity, description, is_active, created_at, updated_at FROM defect_types";
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY defect_id DESC";

$defects = [];
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare defect_types index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching the defect types list.');
    } else {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $defects = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching defect types: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading defect types.');
}

$hasActiveFilters = ($search !== '' || $categoryFilter !== 'all' || $severityFilter !== 'all' || $statusFilter !== 'all');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Defect Type Management</h1>
        <div class="page-subtitle">Module 4 &bull; Ownership: Member 4 &bull; Access: Admin, QC Officer</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/defect_types/create.php')) ?>" class="btn btn-primary">Add New Defect Type</a>
    </div>
</div>

<!-- Defect Types Scoped Styles -->
<style>
.dt-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.dt-kpi-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-lg);
    padding: 1.15rem 1.25rem;
    box-shadow: var(--shadow-xs);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.dt-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}
.dt-kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--color-text-muted);
}
.dt-kpi-val {
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.2;
    margin: 0.35rem 0 0.2rem;
    color: var(--color-text);
}
.dt-kpi-sub {
    font-size: 0.78rem;
    color: var(--color-text-subtle);
}
.dt-badge-category {
    background-color: var(--color-surface-subtle);
    color: var(--color-primary-dark);
    border: 1px solid var(--color-border);
    padding: 0.2rem 0.55rem;
    border-radius: var(--radius-xs);
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}
.btn-action-activate {
    background-color: var(--color-success-bg);
    border: 1px solid var(--color-success-border);
    color: var(--color-success);
}
.btn-action-activate:hover {
    background-color: #d1fae5;
    border-color: var(--color-success);
    color: #065f46;
}
</style>

<?php
// Compute in-memory KPIs without additional SQL queries
$totalCount = count($defects);
$activeCount = 0;
$criticalCount = 0;
$majorCount = 0;
foreach ($defects as $dRow) {
    if ((int)$dRow['is_active'] === 1) {
        $activeCount++;
    }
    if ($dRow['severity'] === 'critical') {
        $criticalCount++;
    } elseif ($dRow['severity'] === 'major') {
        $majorCount++;
    }
}
?>

<!-- KPI Metric Cards (Computed from loaded result set) -->
<div class="dt-kpi-grid">
    <div class="dt-kpi-card">
        <div class="dt-kpi-label">Total Defect Types</div>
        <div class="dt-kpi-val" style="color: var(--color-primary);"><?= number_format($totalCount) ?></div>
        <div class="dt-kpi-sub"><?= $hasActiveFilters ? 'Matching filtered results' : 'Active and inactive catalog' ?></div>
    </div>
    <div class="dt-kpi-card">
        <div class="dt-kpi-label">Active for QC</div>
        <div class="dt-kpi-val" style="color: var(--color-success);"><?= number_format($activeCount) ?></div>
        <div class="dt-kpi-sub">Available for inline inspections</div>
    </div>
    <div class="dt-kpi-card">
        <div class="dt-kpi-label">Critical Severity</div>
        <div class="dt-kpi-val" style="color: var(--color-danger);"><?= number_format($criticalCount) ?></div>
        <div class="dt-kpi-sub">Zero-tolerance quality gates</div>
    </div>
    <div class="dt-kpi-card">
        <div class="dt-kpi-label">Major Severity</div>
        <div class="dt-kpi-val" style="color: var(--color-warning);"><?= number_format($majorCount) ?></div>
        <div class="dt-kpi-sub">High-impact defect classifications</div>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/defect_types/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Defect Types</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by code, name, or description...">
            </div>

            <div class="filter-field">
                <label for="category" class="form-label">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="all" <?= $categoryFilter === 'all' ? 'selected' : '' ?>>All Categories</option>
                    <?php foreach ($allowedCategories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="severity" class="form-label">Severity</label>
                <select id="severity" name="severity" class="form-control">
                    <option value="all" <?= $severityFilter === 'all' ? 'selected' : '' ?>>All Severities</option>
                    <?php foreach ($allowedSeverities as $sev): ?>
                        <option value="<?= e($sev) ?>" <?= $severityFilter === $sev ? 'selected' : '' ?>><?= ucfirst(e($sev)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Defect Types Directory Table -->
<div class="card">
    <div class="card-header">
        <h2>Defect Types Directory</h2>
        <span class="badge badge-muted"><?= count($defects) ?> Total Result(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($defects)): ?>
            <div class="empty-state">
                <p>No defect types found matching your criteria.</p>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/defect_types/create.php')) ?>" class="btn btn-primary btn-sm">Add Your First Defect Type</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th>Defect Code</th>
                            <th>Defect Name</th>
                            <th>Category</th>
                            <th>Severity</th>
                            <th class="col-status">Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($defects as $d): ?>
                            <tr>
                                <td>#<?= (int) $d['defect_id'] ?></td>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/defect_types/view.php?id=' . (int)$d['defect_id'])) ?>">
                                            <code><?= e($d['defect_code']) ?></code>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <strong><?= e($d['defect_name']) ?></strong>
                                    <?php if (!empty($d['description'])): ?>
                                        <div class="text-xs text-muted" style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= e($d['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="dt-badge-category"><?= e($d['category']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $sevClass = 'badge-secondary';
                                    if ($d['severity'] === 'minor') {
                                        $sevClass = 'badge-info';
                                    } elseif ($d['severity'] === 'major') {
                                        $sevClass = 'badge-warning';
                                    } elseif ($d['severity'] === 'critical') {
                                        $sevClass = 'badge-danger';
                                    }
                                    ?>
                                    <span class="badge <?= $sevClass ?>"><?= ucfirst(e($d['severity'])) ?></span>
                                </td>
                                <td class="col-status">
                                    <?php if ((int)$d['is_active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/defect_types/view.php?id=' . (int)$d['defect_id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                                        <a href="<?= e(url('modules/defect_types/edit.php?id=' . (int)$d['defect_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <form action="<?= e(url('modules/defect_types/toggle_status.php')) ?>" method="post" style="display: contents;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="defect_id" value="<?= (int) $d['defect_id'] ?>">
                                            <?php if ((int)$d['is_active'] === 1): ?>
                                                <button type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        data-confirm="Are you sure you want to deactivate this defect type?">
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button type="submit"
                                                        class="btn btn-sm btn-action-activate"
                                                        data-confirm="Are you sure you want to reactivate this defect type?">
                                                    Reactivate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
