<?php
/**
 * ThreadFlow - Packing & Shipment Directory
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Packing & Shipment Management';
$currentNav = 'packing_shipment';

// Allowed domain lists
$allowedStatuses = ['Packed', 'In Transit', 'Shipped', 'Delivered', 'Cancelled'];
$allowedMethods  = ['Sea', 'Air', 'Road', 'Courier'];

// Extract and sanitize search and filter query parameters
$search       = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$methodFilter = trim($_GET['method'] ?? 'all');
$buyerFilter  = filter_input(INPUT_GET, 'buyer_id', FILTER_VALIDATE_INT);
$batchFilter  = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);

// Build query with prepared statements
$whereClauses = [];
$types = '';
$params = [];

// Search across packing number, batch number, order number, style number, buyer name, carrier, tracking, destination
if ($search !== '') {
    $whereClauses[] = "(ps.packing_number LIKE ? OR pb.batch_number LIKE ? OR bo.order_number LIKE ? OR bo.style_number LIKE ? OR b.buyer_name LIKE ? OR ps.carrier_name LIKE ? OR ps.tracking_number LIKE ? OR ps.destination LIKE ?)";
    $wildcard = '%' . $search . '%';
    $types .= 'ssssssss';
    for ($i = 0; $i < 8; $i++) {
        $params[] = $wildcard;
    }
}

// Filter by status
if ($statusFilter !== 'all' && in_array($statusFilter, $allowedStatuses, true)) {
    $whereClauses[] = "ps.status = ?";
    $types .= 's';
    $params[] = $statusFilter;
}

// Filter by shipping method
if ($methodFilter !== 'all' && in_array($methodFilter, $allowedMethods, true)) {
    $whereClauses[] = "ps.shipping_method = ?";
    $types .= 's';
    $params[] = $methodFilter;
}

// Filter by buyer ID
if ($buyerFilter && $buyerFilter > 0) {
    $whereClauses[] = "bo.buyer_id = ?";
    $types .= 'i';
    $params[] = $buyerFilter;
}

// Filter by batch ID
if ($batchFilter && $batchFilter > 0) {
    $whereClauses[] = "ps.batch_id = ?";
    $types .= 'i';
    $params[] = $batchFilter;
}

$hasActiveFilters = ($search !== '' || $statusFilter !== 'all' || $methodFilter !== 'all' || ($buyerFilter && $buyerFilter > 0) || ($batchFilter && $batchFilter > 0));

// Load dropdown options for filters
$buyerOptions = [];
$batchOptions = [];
$metrics = [
    'total_shipments' => 0,
    'total_packed_qty' => 0,
    'active_shipments' => 0,
    'total_cartons'   => 0
];
$tableMissing = false;

try {
    // Check if table exists
    $tblCheck = $conn->query("SHOW TABLES LIKE 'packing_shipments'");
    if (!$tblCheck || $tblCheck->num_rows === 0) {
        $tableMissing = true;
    } else {
        // Buyers for filter dropdown
        $byRes = $conn->query("SELECT buyer_id, buyer_name FROM buyers ORDER BY buyer_name ASC");
        if ($byRes) {
            $buyerOptions = $byRes->fetch_all(MYSQLI_ASSOC);
        }

        // Batches for filter dropdown
        $btRes = $conn->query("SELECT batch_id, batch_number FROM production_batches ORDER BY batch_number ASC");
        if ($btRes) {
            $batchOptions = $btRes->fetch_all(MYSQLI_ASSOC);
        }

        // KPI metrics
        $kpiSql = "SELECT 
            COUNT(*) AS total_shipments,
            COALESCE(SUM(CASE WHEN status != 'Cancelled' THEN packed_quantity ELSE 0 END), 0) AS total_packed_qty,
            COALESCE(SUM(CASE WHEN status IN ('In Transit', 'Shipped') THEN 1 ELSE 0 END), 0) AS active_shipments,
            COALESCE(SUM(CASE WHEN status != 'Cancelled' THEN carton_count ELSE 0 END), 0) AS total_cartons
        FROM packing_shipments";
        $kpiRes = $conn->query($kpiSql);
        if ($kpiRes) {
            $metrics = $kpiRes->fetch_assoc() ?: $metrics;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load packing & shipment filter data or metrics: " . $e->getMessage());
    $tableMissing = true;
}

// Main listing query with mandatory JOINs and correlated subqueries
$records = [];
if (!$tableMissing) {
    $sql = "SELECT 
                ps.packing_id,
                ps.packing_number,
                ps.batch_id,
                ps.inspection_id,
                ps.packed_quantity,
                ps.carton_count,
                ps.packing_date,
                ps.shipping_date,
                ps.shipping_method,
                ps.carrier_name,
                ps.tracking_number,
                ps.destination,
                ps.status,
                ps.notes,
                ps.created_at,
                -- Production Batch JOIN
                pb.batch_number,
                pb.planned_quantity AS batch_planned_qty,
                -- Buyer Order JOIN (retrieves production order and style)
                bo.order_id,
                bo.order_number,
                bo.style_number,
                -- Buyer JOIN
                b.buyer_id,
                b.buyer_name,
                -- Quality Inspection JOIN
                qi.inspection_number,
                qi.passed_quantity,
                qi.inspection_stage,
                -- Creator User JOIN
                u_creator.full_name AS creator_name,
                u_creator.username AS creator_username,
                -- Updater User JOIN
                u_updater.full_name AS updater_name,
                u_updater.username AS updater_username,
                -- Correlated Subquery 1: Already packed non-cancelled quantity for this inspection
                (
                    SELECT COALESCE(SUM(ps2.packed_quantity), 0)
                    FROM packing_shipments ps2
                    WHERE ps2.inspection_id = ps.inspection_id
                      AND ps2.status != 'Cancelled'
                ) AS inspection_total_packed,
                -- Correlated Subquery 2: Available remaining quantity for this inspection
                (
                    qi.passed_quantity - (
                        SELECT COALESCE(SUM(ps2.packed_quantity), 0)
                        FROM packing_shipments ps2
                        WHERE ps2.inspection_id = ps.inspection_id
                          AND ps2.status != 'Cancelled'
                    )
                ) AS inspection_available_quantity
            FROM packing_shipments ps
            INNER JOIN production_batches pb ON ps.batch_id = pb.batch_id
            INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
            INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
            INNER JOIN quality_inspections qi ON ps.inspection_id = qi.inspection_id
            INNER JOIN users u_creator ON ps.created_by = u_creator.user_id
            LEFT JOIN users u_updater ON ps.updated_by = u_updater.user_id";

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }
    $sql .= " ORDER BY ps.packing_id DESC";

    try {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $records = $result->fetch_all(MYSQLI_ASSOC);
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare packing_shipment index query: " . $conn->error);
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error executing packing_shipment index query: " . $e->getMessage());
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Packing &amp; Shipment Management</h1>
        <div class="page-subtitle">Module 4 &bull; Finished Goods Packaging, Dispatch &amp; Logistics Tracking</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/packing_shipment/create.php')) ?>" class="btn btn-primary">
            Create Packing Shipment
        </a>
    </div>
</div>

<?php if ($tableMissing): ?>
    <div class="alert alert-warning">
        <strong>Database Schema Notice:</strong> The <code>packing_shipments</code> table is not yet installed in the database.
        Please import <code>sql/packing_shipment.sql</code> using phpMyAdmin or MySQL CLI to activate this module.
    </div>
<?php endif; ?>

<!-- Summary KPI Metric Cards matching ThreadFlow Standard Layout -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0.5px;">Total Shipments Logged</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;">
            <?= e(number_format((int)$metrics['total_shipments'])) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">Total packages created</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0.5px;">Active Packed Units</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #15803D; margin-top: 0.25rem;">
            <?= e(number_format((int)$metrics['total_packed_qty'])) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">pcs finished goods</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0.5px;">In Transit / Shipped</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #1E40AF; margin-top: 0.25rem;">
            <?= e(number_format((int)$metrics['active_shipments'])) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">Active outbound shipments</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0.5px;">Total Outbound Cartons</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #854D0E; margin-top: 0.25rem;">
            <?= e(number_format((int)$metrics['total_cartons'])) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">Cartons / boxes prepared</div>
    </div>
</div>

<!-- Search & Filter Controls matching ThreadFlow Standard filter-bar Grid -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/packing_shipment/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Shipments</label>
                <input type="text" 
                       id="q" 
                       name="q" 
                       class="form-control" 
                       placeholder="Search by packing #, batch #, order #, style #, buyer, carrier..." 
                       value="<?= e($search) ?>">
            </div>

            <div class="filter-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                            <?= e($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="method" class="form-label">Shipping Method</label>
                <select id="method" name="method" class="form-control">
                    <option value="all" <?= $methodFilter === 'all' ? 'selected' : '' ?>>All Methods</option>
                    <?php foreach ($allowedMethods as $m): ?>
                        <option value="<?= e($m) ?>" <?= $methodFilter === $m ? 'selected' : '' ?>>
                            <?= e($m) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($buyerOptions)): ?>
                <div class="filter-field">
                    <label for="buyer_id" class="form-label">Buyer</label>
                    <select id="buyer_id" name="buyer_id" class="form-control">
                        <option value="">All Buyers</option>
                        <?php foreach ($buyerOptions as $bo): ?>
                            <option value="<?= (int)$bo['buyer_id'] ?>" <?= ((int)$buyerFilter === (int)$bo['buyer_id']) ? 'selected' : '' ?>>
                                <?= e($bo['buyer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($batchOptions)): ?>
                <div class="filter-field">
                    <label for="batch_id" class="form-label">Production Batch</label>
                    <select id="batch_id" name="batch_id" class="form-control">
                        <option value="">All Batches</option>
                        <?php foreach ($batchOptions as $bto): ?>
                            <option value="<?= (int)$bto['batch_id'] ?>" <?= ((int)$batchFilter === (int)$bto['batch_id']) ? 'selected' : '' ?>>
                                <?= e($bto['batch_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/packing_shipment/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Main Table Card -->
<div class="card">
    <div class="card-header">
        <h2>Packing &amp; Shipment Records</h2>
        <span class="badge badge-muted"><?= count($records) ?> Result(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($records)): ?>
            <div style="padding: 2.5rem; text-align: center; color: var(--color-text-muted);">
                <p style="margin-bottom: 1rem;"><?= $tableMissing ? 'Table not installed yet.' : 'No packing and shipment records found matching criteria.' ?></p>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/packing_shipment/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/packing_shipment/create.php')) ?>" class="btn btn-primary btn-sm">Create Your First Packing Shipment</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Packing #</th>
                            <th>Batch &amp; Style</th>
                            <th>Buyer &amp; Order</th>
                            <th>QC Inspection</th>
                            <th style="text-align: right;">Packed Qty</th>
                            <th style="text-align: right;">Cartons</th>
                            <th>Packing Date</th>
                            <th>Method &amp; Destination</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <?php
                            $st = $row['status'];
                            $badgeClass = 'badge-muted';
                            if ($st === 'Packed') $badgeClass = 'badge-info';
                            elseif ($st === 'In Transit') $badgeClass = 'badge-warning';
                            elseif ($st === 'Shipped') $badgeClass = 'badge-primary';
                            elseif ($st === 'Delivered') $badgeClass = 'badge-success';
                            elseif ($st === 'Cancelled') $badgeClass = 'badge-danger';
                            ?>
                            <tr>
                                <td>
                                    <strong><a href="<?= e(url('modules/packing_shipment/view.php?id=' . $row['packing_id'])) ?>"><?= e($row['packing_number']) ?></a></strong>
                                    <div class="text-muted" style="font-size: 0.75rem;">ID: #<?= e((string)$row['packing_id']) ?></div>
                                </td>
                                <td>
                                    <strong><?= e($row['batch_number']) ?></strong>
                                    <div class="text-muted" style="font-size: 0.75rem;">Style: <?= e($row['style_number']) ?></div>
                                </td>
                                <td>
                                    <div><?= e($row['buyer_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Order: <?= e($row['order_number']) ?></div>
                                </td>
                                <td>
                                    <div><strong><?= e($row['inspection_number']) ?></strong></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Passed: <?= e(number_format((int)$row['passed_quantity'])) ?> pcs</div>
                                </td>
                                <td style="text-align: right;">
                                    <strong class="<?= $st === 'Cancelled' ? 'text-muted' : '' ?>" style="<?= $st === 'Cancelled' ? 'text-decoration: line-through;' : '' ?>">
                                        <?= e(number_format((int)$row['packed_quantity'])) ?>
                                    </strong>
                                    <div class="text-muted" style="font-size: 0.75rem;">pcs</div>
                                </td>
                                <td style="text-align: right;">
                                    <?= e(number_format((int)$row['carton_count'])) ?>
                                    <div class="text-muted" style="font-size: 0.75rem;">ctns</div>
                                </td>
                                <td>
                                    <div><?= e($row['packing_date']) ?></div>
                                    <?php if (!empty($row['shipping_date'])): ?>
                                        <div class="text-muted" style="font-size: 0.75rem;">Ship: <?= e($row['shipping_date']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><span class="badge badge-muted"><?= e($row['shipping_method']) ?></span></div>
                                    <div class="text-muted" style="font-size: 0.75rem; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($row['destination']) ?>">
                                        <?= e($row['destination']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>"><?= e($st) ?></span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?= e(url('modules/packing_shipment/view.php?id=' . $row['packing_id'])) ?>" class="btn btn-sm btn-secondary" title="View Dossier">
                                        View
                                    </a>
                                    <?php if (!in_array($st, ['Delivered', 'Cancelled'], true)): ?>
                                        <a href="<?= e(url('modules/packing_shipment/edit.php?id=' . $row['packing_id'])) ?>" class="btn btn-sm btn-primary" title="Edit Shipment">
                                            Edit
                                        </a>
                                    <?php endif; ?>
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
