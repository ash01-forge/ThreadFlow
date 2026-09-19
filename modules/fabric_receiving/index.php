<?php
/**
 * ThreadFlow - Fabric Receiving Management Directory
 * 
 * Module 2 (Feature 2): Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Fabric Receiving Management';
$currentNav = 'fabric_receiving';

// Documented allowed units and inspection statuses
$allowedUnits = ['Yards', 'Meters', 'Kilograms', 'Rolls'];
$allowedInspectionStatuses = ['Pending', 'Passed', 'Failed', 'Conditionally Approved'];

// Extract and sanitize search and filter query parameters
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$supplierFilter = filter_input(INPUT_GET, 'supplier_id', FILTER_VALIDATE_INT) ?: 0;

// Fetch suppliers list for filter dropdown
$suppliersList = [];
try {
    $supStmt = $conn->prepare("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");
    if ($supStmt) {
        $supStmt->execute();
        $supRes = $supStmt->get_result();
        $suppliersList = $supRes->fetch_all(MYSQLI_ASSOC);
        $supStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching suppliers for filter: " . $e->getMessage());
}

// Build dynamic WHERE clause with prepared statements
$whereClauses = [];
$params = [];
$types = '';

// Search across lot_number, fabric_type, color, or supplier_name
if ($search !== '') {
    $whereClauses[] = "(fl.lot_number LIKE ? OR fl.fabric_type LIKE ? OR fl.color LIKE ? OR s.supplier_name LIKE ?)";
    $wildcard = '%' . $search . '%';
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $types .= 'ssss';
}

// Filter by inspection status
if ($statusFilter !== 'all' && in_array($statusFilter, $allowedInspectionStatuses, true)) {
    $whereClauses[] = "fl.inspection_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

// Filter by supplier
if ($supplierFilter > 0) {
    $whereClauses[] = "fl.supplier_id = ?";
    $params[] = $supplierFilter;
    $types .= 'i';
}

$sql = "SELECT 
            fl.fabric_lot_id,
            fl.supplier_id,
            fl.lot_number,
            fl.fabric_type,
            fl.color,
            fl.received_quantity,
            fl.unit,
            fl.received_date,
            fl.inspection_status,
            fl.notes,
            fl.created_at,
            fl.updated_at,
            s.supplier_name,
            s.supplier_type,
            s.contact_person,
            s.is_active AS supplier_is_active
        FROM fabric_lots fl
        INNER JOIN suppliers s ON fl.supplier_id = s.supplier_id";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY fl.fabric_lot_id DESC";

$fabricLots = [];
$tableMissing = false;

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare fabric_lots index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching fabric lots.');
    } else {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $fabricLots = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching fabric lots: " . $e->getMessage());
    if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        $tableMissing = true;
    } else {
        flash_set('error', 'A database error occurred while loading fabric receiving records.');
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Fabric Receiving Management</h1>
        <div class="page-subtitle">Module 2 (Feature 2) &bull; Ownership: Member 2 &bull; Access: Admin, Production Manager</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/fabric_receiving/create.php')) ?>" class="btn btn-primary">Receive Fabric Lot</a>
        <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="btn btn-secondary">Supplier Directory</a>
    </div>
</div>

<?php if ($tableMissing): ?>
    <div class="alert alert-warning mb-4">
        <strong>Database Schema Notice:</strong> The <code>fabric_lots</code> table does not exist in the active database yet.
        Please import <code>sql/fabric_lots.sql</code> via phpMyAdmin or the MySQL CLI to initialize the fabric receiving schema and sample records.
    </div>
<?php endif; ?>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/fabric_receiving/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Fabric Lots</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by lot #, fabric type, color, or supplier...">
            </div>

            <div class="filter-field">
                <label for="supplier_id" class="form-label">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="form-control">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliersList as $sup): ?>
                        <option value="<?= (int) $sup['supplier_id'] ?>" <?= $supplierFilter === (int) $sup['supplier_id'] ? 'selected' : '' ?>>
                            <?= e($sup['supplier_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="status" class="form-label">Inspection Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <?php foreach ($allowedInspectionStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                            <?= e($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $statusFilter !== 'all' || $supplierFilter > 0): ?>
                    <a href="<?= e(url('modules/fabric_receiving/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Fabric Lots Directory Table -->
<div class="card">
    <div class="card-header">
        <h2>Received Fabric Lots</h2>
        <span class="badge badge-muted"><?= count($fabricLots) ?> Result(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($fabricLots)): ?>
            <div class="empty-state">
                <p>No fabric receiving records found matching your criteria.</p>
                <?php if ($search !== '' || $statusFilter !== 'all' || $supplierFilter > 0): ?>
                    <a href="<?= e(url('modules/fabric_receiving/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/fabric_receiving/create.php')) ?>" class="btn btn-primary btn-sm">Receive First Fabric Lot</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th>Lot Number</th>
                            <th>Fabric Type &amp; Details</th>
                            <th>Supplier</th>
                            <th>Quantity Received</th>
                            <th>Received Date</th>
                            <th class="col-status">Inspection</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fabricLots as $lot): ?>
                            <?php
                            $badgeClass = 'badge-muted';
                            if ($lot['inspection_status'] === 'Pending') $badgeClass = 'badge-warning';
                            elseif ($lot['inspection_status'] === 'Passed') $badgeClass = 'badge-success';
                            elseif ($lot['inspection_status'] === 'Failed') $badgeClass = 'badge-danger';
                            elseif ($lot['inspection_status'] === 'Conditionally Approved') $badgeClass = 'badge-info';
                            ?>
                            <tr>
                                <td>#<?= (int) $lot['fabric_lot_id'] ?></td>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/fabric_receiving/view.php?id=' . (int) $lot['fabric_lot_id'])) ?>">
                                            <?= e($lot['lot_number']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <div><strong><?= e($lot['fabric_type']) ?></strong></div>
                                    <?php if (!empty($lot['color'])): ?>
                                        <span class="text-muted text-xs">Color: <?= e($lot['color']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">Color: Unspecified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/suppliers/view.php?id=' . (int) $lot['supplier_id'])) ?>">
                                            <?= e($lot['supplier_name']) ?>
                                        </a>
                                    </strong>
                                    <?php if ((int) $lot['supplier_is_active'] !== 1): ?>
                                        <span class="badge badge-danger text-xs ml-1">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= number_format((float) $lot['received_quantity'], 2) ?></strong>
                                    <span class="text-muted text-sm"><?= e($lot['unit']) ?></span>
                                </td>
                                <td>
                                    <span class="text-sm">
                                        <?= e(date('M j, Y', strtotime($lot['received_date']))) ?>
                                    </span>
                                </td>
                                <td class="col-status">
                                    <span class="badge <?= e($badgeClass) ?>"><?= e($lot['inspection_status']) ?></span>
                                </td>
                                <td class="text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/fabric_receiving/view.php?id=' . (int) $lot['fabric_lot_id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                                        <a href="<?= e(url('modules/fabric_receiving/edit.php?id=' . (int) $lot['fabric_lot_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
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
