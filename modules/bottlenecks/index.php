<?php
/**
 * ThreadFlow - Bottleneck Monitoring Report
 * 
 * Module 3: Assigned to Member 3.
 * Feature 3: Bottleneck Monitoring.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Bottleneck Monitoring';
$currentNav = 'bottlenecks';

// Extract and sanitize GET search and filter parameters
$search               = trim($_GET['q'] ?? '');
$stageFilter          = trim($_GET['stage_id'] ?? 'all');
$lineFilter           = trim($_GET['line_id'] ?? 'all');
$classificationFilter = trim($_GET['classification'] ?? 'all');

$validClassifications = [
    'bottlenecks_only'    => 'All Bottlenecks (Active & Historical)',
    'On Hold Overdue'     => 'On Hold Overdue',
    'In Progress Overdue' => 'In Progress Overdue',
    'Historical Delay'    => 'Historical Delay',
    'Due Today'           => 'Due Today (Target Date Boundary)',
    'On Schedule'         => 'On Schedule'
];

// Fetch all stages for filter dropdown
$allStages = [];
try {
    $stgRes = $conn->query("SELECT stage_id, stage_name, stage_code, sequence_number FROM production_stages ORDER BY sequence_number ASC");
    if ($stgRes) {
        $allStages = $stgRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching production stages for bottleneck filter: " . $e->getMessage());
}

// Fetch all sewing lines for filter dropdown
$allLines = [];
try {
    $lnRes = $conn->query("SELECT line_id, line_name, line_code FROM sewing_lines ORDER BY line_name ASC");
    if ($lnRes) {
        $allLines = $lnRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching sewing lines for bottleneck filter: " . $e->getMessage());
}

$viewMissing = false;
$metrics = [
    'total_monitored'     => 0,
    'total_bottlenecks'   => 0,
    'in_progress_overdue' => 0,
    'on_hold_overdue'     => 0,
    'historical_delays'   => 0,
    'due_today'           => 0,
    'on_schedule'         => 0,
];

// Fetch high-level bottleneck metrics from the repeat-safe view
try {
    $metricSql = "SELECT 
                    COUNT(*) AS total_monitored,
                    SUM(CASE WHEN is_bottleneck = 1 THEN 1 ELSE 0 END) AS total_bottlenecks,
                    SUM(CASE WHEN classification = 'In Progress Overdue' THEN 1 ELSE 0 END) AS in_progress_overdue,
                    SUM(CASE WHEN classification = 'On Hold Overdue' THEN 1 ELSE 0 END) AS on_hold_overdue,
                    SUM(CASE WHEN classification = 'Historical Delay' THEN 1 ELSE 0 END) AS historical_delays,
                    SUM(CASE WHEN classification = 'Due Today' THEN 1 ELSE 0 END) AS due_today,
                    SUM(CASE WHEN classification = 'On Schedule' THEN 1 ELSE 0 END) AS on_schedule
                  FROM view_bottleneck_monitoring";
    $mRes = $conn->query($metricSql);
    if ($mRes) {
        $row = $mRes->fetch_assoc();
        if ($row) {
            $metrics = [
                'total_monitored'     => (int) ($row['total_monitored'] ?? 0),
                'total_bottlenecks'   => (int) ($row['total_bottlenecks'] ?? 0),
                'in_progress_overdue' => (int) ($row['in_progress_overdue'] ?? 0),
                'on_hold_overdue'     => (int) ($row['on_hold_overdue'] ?? 0),
                'historical_delays'   => (int) ($row['historical_delays'] ?? 0),
                'due_today'           => (int) ($row['due_today'] ?? 0),
                'on_schedule'         => (int) ($row['on_schedule'] ?? 0),
            ];
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching bottleneck summary metrics: " . $e->getMessage());
    if (strpos($e->getMessage(), 'view_bottleneck_monitoring') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        $viewMissing = true;
    }
}

// Build dynamic WHERE clause with prepared statements
$whereClauses = [];
$params = [];
$types = '';

// Filter by production stage
if ($stageFilter !== 'all') {
    $stageIdVal = filter_var($stageFilter, FILTER_VALIDATE_INT);
    if ($stageIdVal && $stageIdVal > 0) {
        $whereClauses[] = "stage_id = ?";
        $params[] = $stageIdVal;
        $types .= 'i';
    }
}

// Filter by sewing line (including unassigned)
if ($lineFilter === 'unassigned') {
    $whereClauses[] = "line_id IS NULL";
} elseif ($lineFilter !== 'all') {
    $lineIdVal = filter_var($lineFilter, FILTER_VALIDATE_INT);
    if ($lineIdVal && $lineIdVal > 0) {
        $whereClauses[] = "line_id = ?";
        $params[] = $lineIdVal;
        $types .= 'i';
    }
}

// Filter by classification / bottleneck state
if ($classificationFilter === 'bottlenecks_only') {
    $whereClauses[] = "is_bottleneck = 1";
} elseif ($classificationFilter !== 'all' && array_key_exists($classificationFilter, $validClassifications)) {
    $whereClauses[] = "classification = ?";
    $params[] = $classificationFilter;
    $types .= 's';
}

// Search across batch_number, order_number, style_number, and buyer_name
if ($search !== '') {
    $whereClauses[] = "(batch_number LIKE ? OR order_number LIKE ? OR style_number LIKE ? OR buyer_name LIKE ?)";
    $wildcard = '%' . $search . '%';
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
    $types .= 'ssss';
}

$sql = "SELECT 
            progress_id,
            batch_id,
            stage_id,
            order_id,
            line_id,
            buyer_id,
            batch_number,
            planned_quantity,
            batch_status,
            batch_start_date,
            target_end_date,
            order_number,
            style_number,
            buyer_name,
            line_name,
            line_code,
            stage_name,
            stage_code,
            sequence_number,
            input_quantity,
            output_quantity,
            stage_status,
            start_time,
            end_time,
            remarks,
            elapsed_minutes,
            classification,
            delay_days,
            is_bottleneck,
            bottleneck_reason
        FROM view_bottleneck_monitoring";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

// Order: Prioritize bottlenecks, then largest delay days, then target date and stage sequence
$sql .= " ORDER BY is_bottleneck DESC, delay_days DESC, target_end_date ASC, sequence_number ASC";

$records = [];

if (!$viewMissing) {
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare bottleneck monitoring query: " . $conn->error);
            flash_set('error', 'A database error occurred while querying bottleneck monitoring records.');
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
        error_log("Database exception executing bottleneck monitoring query: " . $e->getMessage());
        if (strpos($e->getMessage(), 'view_bottleneck_monitoring') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
            $viewMissing = true;
        } else {
            flash_set('error', 'A database error occurred while loading bottleneck records.');
        }
    }
}

$hasActiveFilters = ($search !== '' || $stageFilter !== 'all' || $lineFilter !== 'all' || $classificationFilter !== 'all');

/**
 * Format minutes elapsed into human-readable duration (Xd Yh / Xh Ym).
 *
 * @param int $minutes
 * @return string
 */
function format_elapsed_duration(int $minutes): string {
    if ($minutes < 0) {
        return '0m';
    }
    $days = (int) floor($minutes / 1440);
    $hours = (int) floor(($minutes % 1440) / 60);
    $mins = (int) ($minutes % 60);

    if ($days > 0) {
        return "{$days}d {$hours}h";
    }
    if ($hours > 0) {
        return "{$hours}h {$mins}m";
    }
    return "{$mins}m";
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.m3-bottlenecks .m3-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;margin-bottom:1rem;padding:1.35rem 1.5rem;border:1px solid var(--color-border-subtle);border-left:4px solid var(--color-danger);border-radius:var(--radius-lg);background:var(--color-surface);box-shadow:var(--shadow-sm)}.m3-bottlenecks .m3-kicker{margin-bottom:.35rem;color:var(--color-danger);font-size:.68rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.m3-bottlenecks .m3-hero h1{margin:0 0 .25rem}.m3-bottlenecks .module-grid .card{border-top:3px solid var(--color-border);box-shadow:var(--shadow-xs)}.m3-bottlenecks .module-grid .card:first-child{border-top-color:var(--color-danger)}.m3-bottlenecks .m3-filter{border-left:4px solid var(--color-primary)}.m3-bottlenecks .card-header{background:var(--color-surface)}.m3-bottlenecks .table td{vertical-align:top}@media(max-width:760px){.m3-bottlenecks .m3-hero{display:block}.m3-bottlenecks .m3-hero .page-actions{margin-top:1rem}.m3-bottlenecks .m3-hero .btn{width:100%;margin-bottom:.5rem}}
</style>
<div class="m3-bottlenecks">
<div class="page-header m3-hero">
    <div class="page-title-group">
        <div class="m3-kicker">Exception control</div>
        <h1>Bottleneck Monitoring</h1>
        <div class="page-subtitle">Prioritize overdue production stages, understand delay exposure, and open the affected lifecycle record.</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/stage_progress/index.php')) ?>" class="btn btn-secondary">Stage Progress History</a>
        <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-secondary">Production Batches</a>
    </div>
</div>

<?php if ($viewMissing): ?>
    <div class="alert alert-warning mb-4">
        <div class="alert-content">
            <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div class="alert-message">
                <strong>Database View Notice:</strong> The repeat-safe database view <code>view_bottleneck_monitoring</code> does not exist in the active database yet.<br>
                Please import <code>sql/bottleneck_monitoring.sql</code> via phpMyAdmin or the MySQL CLI to initialize Member 3 Feature 3.
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Summary Metric Cards -->
<div class="module-grid mb-4">
    <!-- Active Overdue -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">Active Overdue</span>
                <span class="badge badge-danger"><?= (int)$metrics['in_progress_overdue'] + (int)$metrics['on_hold_overdue'] ?></span>
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-danger); line-height: 1.2;">
                <?= (int)$metrics['in_progress_overdue'] + (int)$metrics['on_hold_overdue'] ?>
            </div>
            <div class="text-xs text-muted mt-1">Ongoing stages past batch target date</div>
        </div>
    </div>

    <!-- On Hold Overdue -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">On Hold Overdue</span>
                <span class="badge badge-warning"><?= (int)$metrics['on_hold_overdue'] ?></span>
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-warning); line-height: 1.2;">
                <?= (int)$metrics['on_hold_overdue'] ?>
            </div>
            <div class="text-xs text-muted mt-1">Paused stages past batch target date</div>
        </div>
    </div>

    <!-- Historical Delays -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">Historical Delays</span>
                <span class="badge badge-warning"><?= (int)$metrics['historical_delays'] ?></span>
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-primary); line-height: 1.2;">
                <?= (int)$metrics['historical_delays'] ?>
            </div>
            <div class="text-xs text-muted mt-1">Completed after batch target date</div>
        </div>
    </div>

    <!-- Due Today -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-xs font-bold text-muted" style="text-transform: uppercase; letter-spacing: 0.5px;">Due Today</span>
                <span class="badge badge-info"><?= (int)$metrics['due_today'] ?></span>
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-info); line-height: 1.2;">
                <?= (int)$metrics['due_today'] ?>
            </div>
            <div class="text-xs text-muted mt-1">Active on target date boundary (on-time)</div>
        </div>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card mb-4 m3-filter">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/bottlenecks/index.php')) ?>" class="filter-bar">
            <!-- Text Search -->
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Records</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by batch #, order #, style, or buyer...">
            </div>

            <!-- Production Stage Filter -->
            <div class="filter-field">
                <label for="stage_id" class="form-label">Production Stage</label>
                <select id="stage_id" name="stage_id" class="form-control">
                    <option value="all" <?= $stageFilter === 'all' ? 'selected' : '' ?>>All Production Stages</option>
                    <?php foreach ($allStages as $stg): ?>
                        <option value="<?= (int)$stg['stage_id'] ?>" <?= ((string)$stg['stage_id'] === $stageFilter) ? 'selected' : '' ?>>
                            Seq <?= (int)$stg['sequence_number'] ?>: <?= e($stg['stage_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sewing Line Filter -->
            <div class="filter-field">
                <label for="line_id" class="form-label">Sewing Line</label>
                <select id="line_id" name="line_id" class="form-control">
                    <option value="all" <?= $lineFilter === 'all' ? 'selected' : '' ?>>All Sewing Lines</option>
                    <option value="unassigned" <?= $lineFilter === 'unassigned' ? 'selected' : '' ?>>Unassigned Line</option>
                    <?php foreach ($allLines as $ln): ?>
                        <option value="<?= (int)$ln['line_id'] ?>" <?= ((string)$ln['line_id'] === $lineFilter) ? 'selected' : '' ?>>
                            <?= e($ln['line_code']) ?> &bull; <?= e($ln['line_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Classification Filter -->
            <div class="filter-field">
                <label for="classification" class="form-label">Classification</label>
                <select id="classification" name="classification" class="form-control">
                    <option value="all" <?= $classificationFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <?php foreach ($validClassifications as $clsKey => $clsLabel): ?>
                        <option value="<?= e($clsKey) ?>" <?= $classificationFilter === $clsKey ? 'selected' : '' ?>>
                            <?= e($clsLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/bottlenecks/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bottleneck Monitoring Report Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center gap-2">
            <h2>Bottleneck Monitoring Directory</h2>
            <span class="badge badge-muted"><?= count($records) ?> Result(s)</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-danger"><?= (int)$metrics['in_progress_overdue'] + (int)$metrics['on_hold_overdue'] ?> Active Overdue</span>
            <span class="badge badge-warning"><?= (int)$metrics['historical_delays'] ?> Historical Delay</span>
            <span class="badge badge-info"><?= (int)$metrics['due_today'] ?> Due Today</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($records)): ?>
            <div class="empty-state">
                <p>No stage progress or bottleneck records found matching your criteria.</p>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/bottlenecks/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Batch &amp; Order</th>
                            <th>Buyer</th>
                            <th>Sewing Line</th>
                            <th>Stage</th>
                            <th class="col-status">Stage Status</th>
                            <th>Timeline</th>
                            <th>Target Date</th>
                            <th>Elapsed</th>
                            <th class="text-center">Delay</th>
                            <th class="col-status">Classification</th>
                            <th>Bottleneck Reason</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <?php
                            // Stage Status Badge Class
                            $stageBadgeClass = 'badge-muted';
                            if ($r['stage_status'] === 'In Progress') {
                                $stageBadgeClass = 'badge-primary';
                            } elseif ($r['stage_status'] === 'On Hold') {
                                $stageBadgeClass = 'badge-warning';
                            } elseif ($r['stage_status'] === 'Completed') {
                                $stageBadgeClass = 'badge-success';
                            }

                            // Classification Badge Class
                            $classBadgeClass = 'badge-muted';
                            if ($r['classification'] === 'On Hold Overdue' || $r['classification'] === 'In Progress Overdue') {
                                $classBadgeClass = 'badge-danger';
                            } elseif ($r['classification'] === 'Historical Delay') {
                                $classBadgeClass = 'badge-warning';
                            } elseif ($r['classification'] === 'Due Today') {
                                $classBadgeClass = 'badge-info';
                            } elseif ($r['classification'] === 'On Schedule') {
                                $classBadgeClass = 'badge-success';
                            }

                            $delayDays = (int) $r['delay_days'];
                            $elapsedMinutes = (int) $r['elapsed_minutes'];
                            ?>
                            <tr>
                                <!-- Batch & Order -->
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

                                <!-- Buyer -->
                                <td>
                                    <?= e($r['buyer_name']) ?>
                                </td>

                                <!-- Sewing Line -->
                                <td>
                                    <?php if (!empty($r['line_name'])): ?>
                                        <span title="<?= e($r['line_name']) ?>">
                                            <?= e($r['line_code'] ?: $r['line_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">Unassigned</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Stage -->
                                <td>
                                    <span class="badge badge-muted text-xs">Seq <?= (int)$r['sequence_number'] ?></span>
                                    <strong><?= e($r['stage_name']) ?></strong>
                                </td>

                                <!-- Stage Status -->
                                <td class="col-status">
                                    <span class="badge <?= e($stageBadgeClass) ?>"><?= e($r['stage_status']) ?></span>
                                </td>

                                <!-- Timeline -->
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

                                <!-- Target Date -->
                                <td class="text-nowrap">
                                    <strong><?= e(date('M j, Y', strtotime($r['target_end_date']))) ?></strong>
                                    <?php if ($r['classification'] === 'Due Today'): ?>
                                        <div class="text-xs" style="color: var(--color-info); font-weight: 600;">Due Today</div>
                                    <?php endif; ?>
                                </td>

                                <!-- Elapsed Duration -->
                                <td class="text-nowrap text-sm">
                                    <?= e(format_elapsed_duration($elapsedMinutes)) ?>
                                </td>

                                <!-- Delay Days -->
                                <td class="text-center text-nowrap">
                                    <?php if ($delayDays > 0): ?>
                                        <span class="badge badge-danger">+<?= $delayDays ?> d</span>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Classification -->
                                <td class="col-status">
                                    <span class="badge <?= e($classBadgeClass) ?>"><?= e($r['classification']) ?></span>
                                </td>

                                <!-- Reason -->
                                <td>
                                    <span class="text-sm">
                                        <?= e($r['bottleneck_reason']) ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="col-actions text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/stage_progress/view.php?id=' . (int)$r['progress_id'])) ?>" class="btn btn-sm btn-secondary">
                                            View Stage
                                        </a>
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
