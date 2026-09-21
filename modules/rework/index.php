<?php
/**
 * ThreadFlow - Rework Management Directory & Dashboard
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Rework Management';
$currentNav = 'rework';

// Allowed statuses
$allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Rejected'];

// Extract and sanitize query parameters
$search       = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$batchFilter  = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);

// Summary metrics across all rework records
$stats = [
    'total'       => 0,
    'pending'     => 0,
    'in_progress' => 0,
    'completed'   => 0,
    'rejected'    => 0,
    'rework_qty'  => 0,
    'salvaged'    => 0,
    'scrapped'    => 0,
];

try {
    $statRes = $conn->query("SELECT 
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending,
        COALESCE(SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END), 0) AS in_progress,
        COALESCE(SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed,
        COALESCE(SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END), 0) AS rejected,
        COALESCE(SUM(rework_quantity), 0) AS rework_qty,
        COALESCE(SUM(completed_quantity), 0) AS salvaged,
        COALESCE(SUM(rejected_quantity), 0) AS scrapped
    FROM rework_jobs");
    if ($statRes) {
        $stats = $statRes->fetch_assoc();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load rework summary metrics: " . $e->getMessage());
}

// Fetch active production batches for filter dropdown
$batchOptions = [];
try {
    $bRes = $conn->query("SELECT batch_id, batch_number FROM production_batches ORDER BY batch_number ASC");
    if ($bRes) {
        $batchOptions = $bRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load batch filter options for rework: " . $e->getMessage());
}

// Build query with prepared statements
$whereClauses = [];
$types = '';
$params = [];

if ($search !== '') {
    $whereClauses[] = "(rj.rework_number LIKE ? OR qi.inspection_number LIKE ? OR pb.batch_number LIKE ? OR bo.style_number LIKE ? OR bo.order_number LIKE ? OR dt.defect_name LIKE ? OR dt.defect_code LIKE ? OR rj.assigned_to LIKE ?)";
    $wildcard = '%' . $search . '%';
    $types .= 'ssssssss';
    for ($i = 0; $i < 8; $i++) {
        $params[] = $wildcard;
    }
}

if ($statusFilter !== 'all' && in_array($statusFilter, $allowedStatuses, true)) {
    $whereClauses[] = "rj.status = ?";
    $types .= 's';
    $params[] = $statusFilter;
}

if ($batchFilter && $batchFilter > 0) {
    $whereClauses[] = "pb.batch_id = ?";
    $types .= 'i';
    $params[] = $batchFilter;
}

$sql = "SELECT 
            rj.rework_id,
            rj.rework_number,
            rj.inspection_defect_id,
            rj.line_id,
            rj.assigned_to,
            rj.rework_quantity,
            rj.assigned_date,
            rj.target_completion_date,
            rj.actual_completion_date,
            rj.completed_quantity,
            rj.rejected_quantity,
            rj.status,
            rj.created_at,
            qi.inspection_id,
            qi.inspection_number,
            qi.inspection_stage,
            pb.batch_id,
            pb.batch_number,
            bo.order_number,
            bo.style_number,
            b.buyer_name,
            dt.defect_id,
            dt.defect_code,
            dt.defect_name,
            dt.category AS defect_category,
            dt.severity AS defect_severity,
            sl.line_name,
            sl.line_code
        FROM rework_jobs rj
        INNER JOIN inspection_defects idf ON rj.inspection_defect_id = idf.inspection_defect_id
        INNER JOIN quality_inspections qi ON idf.inspection_id = qi.inspection_id
        INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id
        INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
        INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
        INNER JOIN defect_types dt ON idf.defect_id = dt.defect_id
        LEFT JOIN sewing_lines sl ON rj.line_id = sl.line_id";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY rj.rework_id DESC";

$reworks = [];
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare rework index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching rework jobs.');
    } else {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $reworks = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading rework jobs: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading rework records.');
}

$hasActiveFilters = ($search !== '' || $statusFilter !== 'all' || ($batchFilter && $batchFilter > 0));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Rework Management</h1>
        <div class="page-subtitle">Module 4 &bull; Ownership: Member 4 &bull; Access: Admin, QC Officer</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/rework/create.php')) ?>" class="btn btn-primary">Create Rework Job</a>
    </div>
</div>

<!-- Summary Metric Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div class="text-xs text-muted" style="text-transform: uppercase; font-weight: 600;">Total Rework Jobs</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;">
            <?= number_format((int)$stats['total']) ?>
        </div>
        <div class="text-xs text-muted"><?= number_format((int)$stats['rework_qty']) ?> pcs total allocated</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div class="text-xs text-muted" style="text-transform: uppercase; font-weight: 600;">Pending</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #b7791f; margin-top: 0.25rem;">
            <?= number_format((int)$stats['pending']) ?>
        </div>
        <div class="text-xs text-muted">Awaiting assignment/start</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div class="text-xs text-muted" style="text-transform: uppercase; font-weight: 600;">In Progress</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #2b6cb0; margin-top: 0.25rem;">
            <?= number_format((int)$stats['in_progress']) ?>
        </div>
        <div class="text-xs text-muted">Currently in repair</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div class="text-xs text-muted" style="text-transform: uppercase; font-weight: 600;">Completed</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #2e7d32; margin-top: 0.25rem;">
            <?= number_format((int)$stats['completed']) ?>
        </div>
        <div class="text-xs text-muted"><?= number_format((int)$stats['salvaged']) ?> pcs salvaged</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div class="text-xs text-muted" style="text-transform: uppercase; font-weight: 600;">Rejected / Scrapped</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #c62828; margin-top: 0.25rem;">
            <?= number_format((int)$stats['rejected']) ?>
        </div>
        <div class="text-xs text-muted"><?= number_format((int)$stats['scrapped']) ?> pcs scrapped</div>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/rework/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Rework Jobs</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by rework #, inspection #, batch #, defect, or assignee...">
            </div>

            <div class="filter-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($batchOptions)): ?>
                <div class="filter-field">
                    <label for="batch_id" class="form-label">Production Batch</label>
                    <select id="batch_id" name="batch_id" class="form-control">
                        <option value="">All Batches</option>
                        <?php foreach ($batchOptions as $bo): ?>
                            <option value="<?= (int) $bo['batch_id'] ?>" <?= ((int)$batchFilter === (int)$bo['batch_id']) ? 'selected' : '' ?>>
                                <?= e($bo['batch_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/rework/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Rework Jobs Directory Table -->
<div class="card">
    <div class="card-header">
        <h2>Rework Tracking Jobs</h2>
        <span class="badge badge-muted"><?= count($reworks) ?> Result(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($reworks)): ?>
            <div class="empty-state">
                <p>No rework job records found matching your criteria.</p>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/rework/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/rework/create.php')) ?>" class="btn btn-primary btn-sm">Create Your First Rework Job</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rework #</th>
                            <th>Source Inspection &amp; Batch</th>
                            <th>Defect Type</th>
                            <th>Assigned To / Line</th>
                            <th>Assigned Date</th>
                            <th>Target Date</th>
                            <th class="text-right">Rework Qty</th>
                            <th class="text-right">Completed</th>
                            <th class="text-right">Rejected</th>
                            <th class="col-status">Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reworks as $rwk): ?>
                            <?php
                            $reworkQty = (int) $rwk['rework_quantity'];
                            $compQty   = (int) $rwk['completed_quantity'];
                            $rejQty    = (int) $rwk['rejected_quantity'];

                            // Badge styling
                            $statusBadgeClass = 'badge-secondary';
                            if ($rwk['status'] === 'Pending') {
                                $statusBadgeClass = 'badge-warning';
                            } elseif ($rwk['status'] === 'In Progress') {
                                $statusBadgeClass = 'badge-info';
                            } elseif ($rwk['status'] === 'Completed') {
                                $statusBadgeClass = 'badge-success';
                            } elseif ($rwk['status'] === 'Rejected') {
                                $statusBadgeClass = 'badge-danger';
                            }

                            $sevBadgeClass = 'badge-secondary';
                            if ($rwk['defect_severity'] === 'minor') {
                                $sevBadgeClass = 'badge-info';
                            } elseif ($rwk['defect_severity'] === 'major') {
                                $sevBadgeClass = 'badge-warning';
                            } elseif ($rwk['defect_severity'] === 'critical') {
                                $sevBadgeClass = 'badge-danger';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/rework/view.php?id=' . (int)$rwk['rework_id'])) ?>">
                                            <?= e($rwk['rework_number']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/quality_inspections/view.php?id=' . (int)$rwk['inspection_id'])) ?>">
                                            <?= e($rwk['inspection_number']) ?>
                                        </a>
                                    </strong>
                                    <div class="text-xs text-muted">
                                        Batch: <strong><?= e($rwk['batch_number']) ?></strong> &bull; <?= e($rwk['style_number']) ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= e($rwk['defect_name']) ?></strong>
                                    <div class="text-xs text-muted">
                                        <span class="badge <?= $sevBadgeClass ?>" style="padding: 2px 6px; font-size: 0.68rem;"><?= ucfirst(e($rwk['defect_severity'])) ?></span>
                                        <?= e($rwk['defect_code']) ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= e($rwk['assigned_to']) ?></strong>
                                    <?php if (!empty($rwk['line_name'])): ?>
                                        <div class="text-xs text-muted"><?= e($rwk['line_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(date('M d, Y', strtotime($rwk['assigned_date']))) ?></td>
                                <td>
                                    <?= e(date('M d, Y', strtotime($rwk['target_completion_date']))) ?>
                                </td>
                                <td class="text-right font-semibold">
                                    <?= number_format($reworkQty) ?>
                                </td>
                                <td class="text-right font-semibold" style="color: #2e7d32;">
                                    <?= number_format($compQty) ?>
                                </td>
                                <td class="text-right font-semibold" style="color: <?= $rejQty > 0 ? '#c62828' : 'inherit' ?>;">
                                    <?= number_format($rejQty) ?>
                                </td>
                                <td class="col-status">
                                    <span class="badge <?= $statusBadgeClass ?>"><?= e($rwk['status']) ?></span>
                                </td>
                                <td class="text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/rework/view.php?id=' . (int)$rwk['rework_id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                                        <a href="<?= e(url('modules/rework/edit.php?id=' . (int)$rwk['rework_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
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
