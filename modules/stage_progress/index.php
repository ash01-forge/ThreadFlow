<?php
/**
 * ThreadFlow - Stage Progress History Directory
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Stage Progress History';
$currentNav = 'stage_progress';

// Extract and sanitize search and filter query parameters
$search       = trim($_GET['q'] ?? '');
$batchFilter  = trim($_GET['batch_id'] ?? 'all');
$statusFilter = trim($_GET['status'] ?? 'all');

$validStatuses = ['In Progress', 'On Hold', 'Completed'];

// Fetch all batches for the filter dropdown
$allBatches = [];
try {
    $batchListSql = "SELECT pb.batch_id, pb.batch_number, bo.order_number, b.buyer_name 
                     FROM production_batches pb
                     INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
                     INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
                     ORDER BY pb.batch_id DESC";
    $bRes = $conn->query($batchListSql);
    if ($bRes) {
        $allBatches = $bRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching batches for stage progress filter: " . $e->getMessage());
}

// Build dynamic WHERE clause with prepared statements
$whereClauses = [];
$params = [];
$types = '';

// Filter by batch_id
if ($batchFilter !== 'all') {
    $batchIdVal = filter_var($batchFilter, FILTER_VALIDATE_INT);
    if ($batchIdVal && $batchIdVal > 0) {
        $whereClauses[] = "sp.batch_id = ?";
        $params[] = $batchIdVal;
        $types .= 'i';
    }
}

// Filter by status
if ($statusFilter !== 'all' && in_array($statusFilter, $validStatuses, true)) {
    $whereClauses[] = "sp.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

// Search across batch_number, order_number, style_number, buyer_name, and stage_name
if ($search !== '') {
    $whereClauses[] = "(pb.batch_number LIKE ? OR bo.order_number LIKE ? OR bo.style_number LIKE ? OR b.buyer_name LIKE ? OR ps.stage_name LIKE ?)";
    $wildcard = '%' . $search . '%';
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $types .= 'sssss';
}

$sql = "SELECT 
            sp.progress_id,
            sp.batch_id,
            sp.stage_id,
            sp.input_quantity,
            sp.output_quantity,
            sp.start_time,
            sp.end_time,
            sp.status,
            sp.remarks,
            sp.created_at,
            sp.updated_at,
            pb.batch_number,
            pb.planned_quantity,
            pb.status AS batch_status,
            bo.order_number,
            bo.style_number,
            b.buyer_id,
            b.buyer_name,
            sl.line_name,
            sl.line_code,
            ps.stage_name,
            ps.stage_code,
            ps.sequence_number
        FROM stage_progress sp
        INNER JOIN production_batches pb ON sp.batch_id = pb.batch_id
        INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
        INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
        LEFT JOIN sewing_lines sl ON pb.line_id = sl.line_id
        INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

// Order in chronological stage order: batch grouped, stages in sequential progression
if ($batchFilter !== 'all' && filter_var($batchFilter, FILTER_VALIDATE_INT)) {
    $sql .= " ORDER BY ps.sequence_number ASC, sp.start_time ASC";
} else {
    $sql .= " ORDER BY pb.batch_number ASC, ps.sequence_number ASC, sp.start_time ASC";
}

$records = [];
$tableMissing = false;

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare stage_progress index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching stage progress records.');
    } else {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $records = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching stage progress: " . $e->getMessage());
    if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        $tableMissing = true;
    } else {
        flash_set('error', 'A database error occurred while loading stage progress records.');
    }
}

// Compute status count totals for quick metrics
$totalInProgress = 0;
$totalOnHold = 0;
$totalCompleted = 0;
foreach ($records as $rec) {
    if ($rec['status'] === 'In Progress') $totalInProgress++;
    elseif ($rec['status'] === 'On Hold') $totalOnHold++;
    elseif ($rec['status'] === 'Completed') $totalCompleted++;
}

$hasActiveFilters = ($search !== '' || $batchFilter !== 'all' || $statusFilter !== 'all');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Stage Progress History</h1>
        <div class="page-subtitle">Module 3 &bull; Ownership: Member 3 &bull; Access: Admin, Production Manager</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/stage_progress/create.php')) ?>" class="btn btn-primary">Record Stage Progress</a>
    </div>
</div>

<?php if ($tableMissing): ?>
    <div class="alert alert-warning mb-4">
        <strong>Database Schema Notice:</strong> The <code>stage_progress</code> or <code>production_stages</code> table does not exist in the active database yet.
        Please import <code>sql/stage_progress.sql</code> via phpMyAdmin or the MySQL CLI to initialize Module 3 Feature 2 tables.
    </div>
<?php endif; ?>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/stage_progress/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Records</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by batch #, order #, style, buyer, or stage...">
            </div>

            <div class="filter-field">
                <label for="batch_id" class="form-label">Production Batch</label>
                <select id="batch_id" name="batch_id" class="form-control">
                    <option value="all" <?= $batchFilter === 'all' ? 'selected' : '' ?>>All Production Batches</option>
                    <?php foreach ($allBatches as $b): ?>
                        <option value="<?= (int) $b['batch_id'] ?>" <?= ((string)$b['batch_id'] === $batchFilter) ? 'selected' : '' ?>>
                            <?= e($b['batch_number']) ?> &bull; <?= e($b['order_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/stage_progress/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Progress History Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center gap-2">
            <h2>Stage Progress Records</h2>
            <span class="badge badge-muted"><?= count($records) ?> Result(s)</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-primary" title="In Progress"><?= $totalInProgress ?> In Progress</span>
            <span class="badge badge-warning" title="On Hold"><?= $totalOnHold ?> On Hold</span>
            <span class="badge badge-success" title="Completed"><?= $totalCompleted ?> Completed</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($records)): ?>
            <div class="empty-state">
                <p>No stage progress records found matching your criteria.</p>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/stage_progress/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/stage_progress/create.php')) ?>" class="btn btn-primary btn-sm">Record Your First Stage Progress</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th>Batch &amp; Order</th>
                            <th>Buyer</th>
                            <th>Sewing Line</th>
                            <th>Planned Qty</th>
                            <th>Stage</th>
                            <th>Input Qty</th>
                            <th>Output Qty</th>
                            <th>Timeline</th>
                            <th class="col-status">Status</th>
                            <th>Remarks</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <?php
                            $badgeClass = 'badge-muted';
                            if ($r['status'] === 'In Progress') $badgeClass = 'badge-primary';
                            elseif ($r['status'] === 'On Hold') $badgeClass = 'badge-warning';
                            elseif ($r['status'] === 'Completed') $badgeClass = 'badge-success';

                            $loss = (int)$r['input_quantity'] - (int)$r['output_quantity'];
                            ?>
                            <tr>
                                <td>#<?= (int) $r['progress_id'] ?></td>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/stage_progress/view.php?id=' . (int)$r['progress_id'])) ?>">
                                            <?= e($r['batch_number']) ?>
                                        </a>
                                    </strong>
                                    <div class="text-xs text-muted">
                                        <?= e($r['order_number']) ?> (<?= e($r['style_number']) ?>)
                                    </div>
                                </td>
                                <td><?= e($r['buyer_name']) ?></td>
                                <td>
                                    <?php if (!empty($r['line_name'])): ?>
                                        <span title="<?= e($r['line_name']) ?>">
                                            <?= e($r['line_code'] ?: $r['line_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format((int) $r['planned_quantity']) ?> pcs</td>
                                <td>
                                    <span class="badge badge-muted text-xs">Seq <?= (int)$r['sequence_number'] ?></span>
                                    <strong><?= e($r['stage_name']) ?></strong>
                                </td>
                                <td><strong><?= number_format((int) $r['input_quantity']) ?></strong> pcs</td>
                                <td>
                                    <strong><?= number_format((int) $r['output_quantity']) ?></strong> pcs
                                    <?php if ($r['status'] === 'Completed' && $loss > 0): ?>
                                        <div class="text-xs text-muted" title="Process loss">
                                            -<?= number_format($loss) ?> loss
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm">
                                        <strong>Start:</strong> <?= e(date('M j, Y H:i', strtotime($r['start_time']))) ?>
                                    </div>
                                    <?php if (!empty($r['end_time'])): ?>
                                        <div class="text-sm text-muted">
                                            <strong>End:</strong> <?= e(date('M j, Y H:i', strtotime($r['end_time']))) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-muted">&bull; Ongoing</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-status">
                                    <span class="badge <?= e($badgeClass) ?>"><?= e($r['status']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($r['remarks'])): ?>
                                        <span class="text-sm" title="<?= e($r['remarks']) ?>">
                                            <?= e(mb_strimwidth($r['remarks'], 0, 30, '...')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/stage_progress/view.php?id=' . (int)$r['progress_id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                                        <a href="<?= e(url('modules/stage_progress/edit.php?id=' . (int)$r['progress_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
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
