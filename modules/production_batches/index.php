<?php
/**
 * ThreadFlow - Production Batch Management Directory
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Production Batches';
$currentNav = 'batches';

// Extract and sanitize search and filter query parameters
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

$validStatuses = ['Planned', 'In Progress', 'On Hold', 'Completed'];

// Build dynamic WHERE clause with prepared statements
$whereClauses = [];
$params = [];
$types = '';

// Search across batch_number, order_number, style_number, and buyer_name
if ($search !== '') {
    $whereClauses[] = "(pb.batch_number LIKE ? OR bo.order_number LIKE ? OR bo.style_number LIKE ? OR b.buyer_name LIKE ?)";
    $wildcard = '%' . $search . '%';
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $types .= 'ssss';
}

// Filter by batch status
if ($statusFilter !== 'all' && in_array($statusFilter, $validStatuses, true)) {
    $whereClauses[] = "pb.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql = "SELECT 
            pb.batch_id,
            pb.batch_number,
            pb.order_id,
            pb.line_id,
            pb.planned_quantity,
            pb.status,
            pb.start_date,
            pb.target_end_date,
            pb.created_at,
            bo.order_number,
            bo.style_number,
            b.buyer_id,
            b.buyer_name,
            sl.line_name,
            sl.line_code
        FROM production_batches pb
        INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
        INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
        LEFT JOIN sewing_lines sl ON pb.line_id = sl.line_id";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY pb.batch_id DESC";

$batches = [];
$tableMissing = false;

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare production_batches index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching the production batches.');
    } else {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $batches = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching production batches: " . $e->getMessage());
    if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        $tableMissing = true;
    } else {
        flash_set('error', 'A database error occurred while loading production batches.');
    }
}

// Presentation-only summaries derived from the records already loaded above.
$batchStatusCounts = array_fill_keys($validStatuses, 0);
$plannedPieces = 0;
foreach ($batches as $batchRow) {
    if (isset($batchStatusCounts[$batchRow['status']])) {
        $batchStatusCounts[$batchRow['status']]++;
    }
    $plannedPieces += (int) $batchRow['planned_quantity'];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.m3-batches { --m3-line: var(--color-border-subtle); --m3-soft: var(--color-primary-subtle); }
.m3-batches .m3-hero { display:flex; justify-content:space-between; align-items:flex-end; gap:1.25rem; margin-bottom:1rem; padding:1.35rem 1.5rem; border:1px solid var(--m3-line); border-radius:var(--radius-lg); background:var(--color-surface); box-shadow:var(--shadow-sm); }
.m3-batches .m3-kicker { margin-bottom:.35rem; color:var(--color-primary-light); font-size:.68rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
.m3-batches .m3-hero h1 { margin:0 0 .25rem; }
.m3-batches .m3-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
.m3-batches .m3-metric { padding:1rem 1.1rem; border:1px solid var(--m3-line); border-radius:var(--radius-md); background:var(--color-surface); box-shadow:var(--shadow-xs); }
.m3-batches .m3-metric strong { display:block; color:var(--color-primary); font-size:1.35rem; }
.m3-batches .m3-metric span { color:var(--color-text-muted); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
.m3-batches .m3-filter { border-left:4px solid var(--color-primary); }
.m3-batches .m3-register .card-header { background:var(--color-surface); }
.m3-batches .table td { vertical-align:top; }
@media (max-width:800px) { .m3-batches .m3-hero { display:block; } .m3-batches .m3-hero .page-actions { margin-top:1rem; } .m3-batches .m3-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:520px) { .m3-batches .m3-hero .btn { width:100%; } .m3-batches .m3-metrics { grid-template-columns:1fr; } }
</style>
<div class="m3-batches">
<div class="page-header m3-hero">
    <div class="page-title-group">
        <div class="m3-kicker">Production control</div>
        <h1>Production Batches</h1>
        <div class="page-subtitle">Plan line allocation, production quantity, and delivery windows from one operational register.</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/production_batches/create.php')) ?>" class="btn btn-primary">Add New Batch</a>
    </div>
</div>

<div class="m3-metrics" aria-label="Batch summary">
    <div class="m3-metric"><strong><?= count($batches) ?></strong><span>Visible batches</span></div>
    <div class="m3-metric"><strong><?= number_format($plannedPieces) ?></strong><span>Planned pieces</span></div>
    <div class="m3-metric"><strong><?= (int) $batchStatusCounts['In Progress'] ?></strong><span>In progress</span></div>
    <div class="m3-metric"><strong><?= (int) $batchStatusCounts['On Hold'] ?></strong><span>On hold</span></div>
</div>

<?php if ($tableMissing): ?>
    <div class="alert alert-warning mb-4">
        <strong>Database Schema Notice:</strong> The <code>production_batches</code> table does not exist in the active database yet.
        Please import <code>sql/production_batches.sql</code> via phpMyAdmin or the MySQL CLI to initialize Module 3 tables and sample seed records.
    </div>
<?php endif; ?>

<!-- Search & Filter Controls -->
<div class="card mb-4 m3-filter">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/production_batches/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Batches</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by batch #, order #, style, or buyer name...">
            </div>

            <div class="filter-field">
                <label for="status" class="form-label">Status Filter</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <?php foreach ($validStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                            <?= e($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                    <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Production Batches Directory Table -->
<div class="card m3-register">
    <div class="card-header">
        <h2>Batch Directory</h2>
        <span class="badge badge-muted"><?= count($batches) ?> Result(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($batches)): ?>
            <div class="empty-state">
                <p>No production batches found matching your criteria.</p>
                <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                    <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/production_batches/create.php')) ?>" class="btn btn-primary btn-sm">Create Your First Batch</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th>Batch Number</th>
                            <th>Buyer</th>
                            <th>Order &amp; Style</th>
                            <th>Planned Qty</th>
                            <th>Sewing Line</th>
                            <th>Timeline</th>
                            <th class="col-status">Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $b): ?>
                            <?php
                            $badgeClass = 'badge-muted';
                            if ($b['status'] === 'Planned') $badgeClass = 'badge-info';
                            elseif ($b['status'] === 'In Progress') $badgeClass = 'badge-primary';
                            elseif ($b['status'] === 'On Hold') $badgeClass = 'badge-warning';
                            elseif ($b['status'] === 'Completed') $badgeClass = 'badge-success';
                            ?>
                            <tr>
                                <td>#<?= (int) $b['batch_id'] ?></td>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$b['batch_id'])) ?>">
                                            <?= e($b['batch_number']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?= e($b['buyer_name']) ?></td>
                                <td>
                                    <div><strong><?= e($b['order_number']) ?></strong></div>
                                    <span class="text-muted text-xs"><?= e($b['style_number']) ?></span>
                                </td>
                                <td><?= number_format((int) $b['planned_quantity']) ?> pcs</td>
                                <td>
                                    <?php if (!empty($b['line_name'])): ?>
                                        <span title="<?= e($b['line_name']) ?>">
                                            <?= e($b['line_code'] ?: $b['line_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm">
                                        <?= e(date('M j, Y', strtotime($b['start_date']))) ?> &rarr; <?= e(date('M j, Y', strtotime($b['target_end_date']))) ?>
                                    </span>
                                </td>
                                <td class="col-status">
                                    <span class="badge <?= e($badgeClass) ?>"><?= e($b['status']) ?></span>
                                </td>
                                <td class="text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$b['batch_id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                                        <a href="<?= e(url('modules/production_batches/edit.php?id=' . (int)$b['batch_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
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
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
