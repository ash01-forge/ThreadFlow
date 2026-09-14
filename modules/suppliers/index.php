<?php
/**
 * ThreadFlow - Supplier Management Directory
 * 
 * Module 2: Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Supplier Management';
$currentNav = 'suppliers';

// Documented allowed supplier types
$allowedSupplierTypes = ['Fabric', 'Yarn', 'Trims & Accessories', 'Dyeing & Chemicals', 'Packaging', 'Other'];

// Extract and sanitize search and filter query parameters
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$typeFilter = trim($_GET['type'] ?? 'all');

// Build query with prepared statements
$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// Search across name, contact person, email, supplier type, and country
if ($search !== '') {
    $whereClauses[] = "(supplier_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR supplier_type LIKE ? OR country LIKE ?)";
    $wildcard = '%' . $search . '%';
    $bindTypes .= 'sssss';
    $bindParams[] = $wildcard;
    $bindParams[] = $wildcard;
    $bindParams[] = $wildcard;
    $bindParams[] = $wildcard;
    $bindParams[] = $wildcard;
}

// Filter by active status
if ($statusFilter === 'active') {
    $whereClauses[] = "is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $whereClauses[] = "is_active = 0";
}

// Filter by supplier type
if ($typeFilter !== 'all' && in_array($typeFilter, $allowedSupplierTypes, true)) {
    $whereClauses[] = "supplier_type = ?";
    $bindTypes .= 's';
    $bindParams[] = $typeFilter;
}

$sql = "SELECT supplier_id, supplier_name, supplier_type, contact_person, email, phone, country, lead_time_days, is_active, created_at, updated_at FROM suppliers";
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY supplier_id DESC";

$suppliers = [];
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare suppliers index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching the supplier list.');
    } else {
        if (!empty($bindParams)) {
            $stmt->bind_param($bindTypes, ...$bindParams);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $suppliers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching suppliers: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading suppliers.');
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Supplier Management</h1>
        <div class="page-subtitle">Module 2 &bull; Ownership: Member 2 &bull; Access: Admin, Production Manager</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/suppliers/create.php')) ?>" class="btn btn-primary">Add New Supplier</a>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/suppliers/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Suppliers</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by company name, contact, email, or country...">
            </div>

            <div class="filter-field">
                <label for="type" class="form-label">Supplier Type</label>
                <select id="type" name="type" class="form-control">
                    <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                    <?php foreach ($allowedSupplierTypes as $typeName): ?>
                        <option value="<?= e($typeName) ?>" <?= $typeFilter === $typeName ? 'selected' : '' ?>>
                            <?= e($typeName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="status" class="form-label">Status Filter</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $statusFilter !== 'all' || $typeFilter !== 'all'): ?>
                    <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Supplier Directory Table -->
<div class="card">
    <div class="card-header">
        <h2>Supplier Directory</h2>
        <span class="badge badge-muted"><?= count($suppliers) ?> Total Result(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($suppliers)): ?>
            <div class="empty-state">
                <p>No suppliers found matching your criteria.</p>
                <?php if ($search !== '' || $statusFilter !== 'all' || $typeFilter !== 'all'): ?>
                    <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/suppliers/create.php')) ?>" class="btn btn-primary btn-sm">Add Your First Supplier</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th>Supplier Name</th>
                            <th>Type</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Country</th>
                            <th>Lead Time</th>
                            <th class="col-status">Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td>#<?= (int) $s['supplier_id'] ?></td>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/suppliers/view.php?id=' . (int)$s['supplier_id'])) ?>">
                                            <?= e($s['supplier_name']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= e($s['supplier_type'] ?? 'Other') ?></span>
                                </td>
                                <td><?= e($s['contact_person'] ?: '—') ?></td>
                                <td>
                                    <?php if (!empty($s['email'])): ?>
                                        <a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($s['phone'] ?: '—') ?></td>
                                <td><?= e($s['country'] ?: '—') ?></td>
                                <td>
                                    <?php if ($s['lead_time_days'] !== null): ?>
                                        <?= (int) $s['lead_time_days'] ?> days
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-status">
                                    <?php if ((int)$s['is_active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/suppliers/view.php?id=' . (int)$s['supplier_id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                                        <a href="<?= e(url('modules/suppliers/edit.php?id=' . (int)$s['supplier_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <form action="<?= e(url('modules/suppliers/toggle_status.php')) ?>" method="post" style="display: contents;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="supplier_id" value="<?= (int) $s['supplier_id'] ?>">
                                            <?php if ((int)$s['is_active'] === 1): ?>
                                                <button type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        data-confirm="Are you sure you want to deactivate this supplier?">
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button type="submit"
                                                        class="btn btn-sm btn-primary"
                                                        data-confirm="Are you sure you want to reactivate this supplier?">
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
