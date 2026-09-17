<?php
/**
 * ThreadFlow - Quality Inspection Directory
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Quality Inspection Management';
$currentNav = 'quality_inspections';

// Allowed domain lists
$allowedStages = ['Inline', 'End of Line', 'Final Pre-Shipment'];
$allowedResults = ['Passed', 'Failed', 'Pending Review'];

// Extract and sanitize search and filter query parameters
$search       = trim($_GET['q'] ?? '');
$stageFilter  = trim($_GET['stage'] ?? 'all');
$resultFilter = trim($_GET['result'] ?? 'all');
$batchFilter  = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);

// Build query with prepared statements
$whereClauses = [];
$types = '';
$params = [];

// Search across inspection number, batch number, order number, style number, and inspector name
if ($search !== '') {
    $whereClauses[] = "(qi.inspection_number LIKE ? OR pb.batch_number LIKE ? OR bo.style_number LIKE ? OR bo.order_number LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)";
    $wildcard = '%' . $search . '%';
    $types .= 'ssssss';
    for ($i = 0; $i < 6; $i++) {
        $params[] = $wildcard;
    }
}

// Filter by inspection stage
if ($stageFilter !== 'all' && in_array($stageFilter, $allowedStages, true)) {
    $whereClauses[] = "qi.inspection_stage = ?";
    $types .= 's';
    $params[] = $stageFilter;
}

// Filter by overall result
if ($resultFilter !== 'all' && in_array($resultFilter, $allowedResults, true)) {
    $whereClauses[] = "qi.overall_result = ?";
    $types .= 's';
    $params[] = $resultFilter;
}

// Filter by batch ID
if ($batchFilter && $batchFilter > 0) {
    $whereClauses[] = "qi.batch_id = ?";
    $types .= 'i';
    $params[] = $batchFilter;
}

// Fetch active batches for filter dropdown
$batchOptions = [];
try {
    $bRes = $conn->query("SELECT batch_id, batch_number FROM production_batches ORDER BY batch_number ASC");
    if ($bRes) {
        $batchOptions = $bRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load batch filter options: " . $e->getMessage());
}

$sql = "SELECT 
            qi.inspection_id,
            qi.inspection_number,
            qi.batch_id,
            qi.inspection_date,
            qi.inspection_stage,
            qi.inspected_quantity,
            qi.passed_quantity,
            qi.defective_quantity,
            qi.overall_result,
            qi.created_at,
            pb.batch_number,
            bo.order_number,
            bo.style_number,
            b.buyer_name,
            u.full_name AS inspector_name,
            u.username AS inspector_username,
            (SELECT COUNT(*) FROM inspection_defects idf WHERE idf.inspection_id = qi.inspection_id) AS defect_types_count,
            (SELECT COALESCE(SUM(defect_count), 0) FROM inspection_defects idf WHERE idf.inspection_id = qi.inspection_id) AS total_defect_occurrences
        FROM quality_inspections qi
        INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id
        INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
        INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
        INNER JOIN users u ON qi.inspector_id = u.user_id";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY qi.inspection_id DESC";

$inspections = [];
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare quality_inspections index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching quality inspections.');
    } else {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $inspections = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading quality inspections: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading inspection records.');
}

$hasActiveFilters = ($search !== '' || $stageFilter !== 'all' || $resultFilter !== 'all' || ($batchFilter && $batchFilter > 0));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Quality Inspection Management</h1>
        <div class="page-subtitle">Module 4 &bull; Ownership: Member 4 &bull; Access: Admin, QC Officer</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/quality_inspections/create.php')) ?>" class="btn btn-primary">Log New Inspection</a>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/quality_inspections/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Inspections</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by inspection #, batch #, style, or inspector...">
            </div>

            <div class="filter-field">
                <label for="stage" class="form-label">Stage</label>
                <select id="stage" name="stage" class="form-control">
                    <option value="all" <?= $stageFilter === 'all' ? 'selected' : '' ?>>All Stages</option>
                    <?php foreach ($allowedStages as $stg): ?>
                        <option value="<?= e($stg) ?>" <?= $stageFilter === $stg ? 'selected' : '' ?>><?= e($stg) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="result" class="form-label">Overall Result</label>
                <select id="result" name="result" class="form-control">
                    <option value="all" <?= $resultFilter === 'all' ? 'selected' : '' ?>>All Results</option>
                    <?php foreach ($allowedResults as $res): ?>
                        <option value="<?= e($res) ?>" <?= $resultFilter === $res ? 'selected' : '' ?>><?= e($res) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($batchOptions)): ?>
                <div class="filter-field">
                    <label for="batch_id" class="form-label">Batch</label>
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
                    <a href="<?= e(url('modules/quality_inspections/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Inspections Directory Table -->
<div class="card">
    <div class="card-header">
        <h2>Quality Inspection Audits</h2>
        <span class="badge badge-muted"><?= count($inspections) ?> Total Record(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($inspections)): ?>
            <div class="empty-state">
                <p>No quality inspection records found matching your criteria.</p>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/quality_inspections/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/quality_inspections/create.php')) ?>" class="btn btn-primary btn-sm">Log Your First Inspection</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Inspection #</th>
                            <th>Batch &amp; Style</th>
                            <th>Date</th>
                            <th>Stage</th>
                            <th class="text-right">Inspected</th>
                            <th class="text-right">Passed</th>
                            <th class="text-right">Defective</th>
                            <th>Pass Rate</th>
                            <th class="col-status">Result</th>
                            <th>Inspector</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inspections as $ins): ?>
                            <?php
                            $inspectedQty = (int) $ins['inspected_quantity'];
                            $passedQty = (int) $ins['passed_quantity'];
                            $defectiveQty = (int) $ins['defective_quantity'];
                            $passRate = $inspectedQty > 0 ? round(($passedQty / $inspectedQty) * 100, 1) : 0;

                            $resBadgeClass = 'badge-secondary';
                            if ($ins['overall_result'] === 'Passed') {
                                $resBadgeClass = 'badge-success';
                            } elseif ($ins['overall_result'] === 'Failed') {
                                $resBadgeClass = 'badge-danger';
                            } elseif ($ins['overall_result'] === 'Pending Review') {
                                $resBadgeClass = 'badge-warning';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/quality_inspections/view.php?id=' . (int)$ins['inspection_id'])) ?>">
                                            <?= e($ins['inspection_number']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <strong><?= e($ins['batch_number']) ?></strong>
                                    <div class="text-xs text-muted"><?= e($ins['style_number']) ?> &bull; <?= e($ins['buyer_name']) ?></div>
                                </td>
                                <td><?= e(date('M d, Y', strtotime($ins['inspection_date']))) ?></td>
                                <td><span class="badge badge-info"><?= e($ins['inspection_stage']) ?></span></td>
                                <td class="text-right"><?= number_format($inspectedQty) ?></td>
                                <td class="text-right font-semibold" style="color: #2e7d32;"><?= number_format($passedQty) ?></td>
                                <td class="text-right font-semibold" style="color: <?= $defectiveQty > 0 ? '#c62828' : 'inherit' ?>;">
                                    <?= number_format($defectiveQty) ?>
                                    <?php if ((int)$ins['defect_types_count'] > 0): ?>
                                        <div class="text-xs text-muted"><?= (int)$ins['defect_types_count'] ?> type(s) &bull; <?= (int)$ins['total_defect_occurrences'] ?> defect(s)</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= $passRate ?>%</strong>
                                </td>
                                <td class="col-status">
                                    <span class="badge <?= $resBadgeClass ?>"><?= e($ins['overall_result']) ?></span>
                                </td>
                                <td>
                                    <?= e($ins['inspector_name'] ?: $ins['inspector_username']) ?>
                                </td>
                                <td class="text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/quality_inspections/view.php?id=' . (int)$ins['inspection_id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                                        <a href="<?= e(url('modules/quality_inspections/edit.php?id=' . (int)$ins['inspection_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
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
