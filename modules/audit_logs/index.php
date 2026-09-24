<?php
/**
 * ThreadFlow - Audit History & Traceability Directory
 * 
 * Module 4: Assigned to Member 4 (QC Officer / Admin).
 * Strictly read-only event explorer.
 * Demonstrates LEFT JOIN with users table and subqueries for KPIs and history depth.
 */

require_once __DIR__ . '/../../includes/auth.php';

// Enforce role authorization (Admin and QC Officer)
require_role(['admin', 'qc_officer']);

// Enforce strictly read-only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$pageTitle = 'Audit History & System Logs';
$currentNav = 'audit_logs';

// Allowed module and action domains
$allowedModules = [
    'defect_types'        => 'Defect Types',
    'quality_inspections' => 'Quality Inspections',
    'rework'              => 'Rework Management',
    'packing_shipment'    => 'Packing & Shipment',
];

$allowedActions = [
    'CREATE'        => 'Creation (CREATE)',
    'UPDATE'        => 'Modification (UPDATE)',
    'STATUS_CHANGE' => 'Status Transition (STATUS_CHANGE)',
];

// Check if audit_logs table exists
$tableMissing = false;
try {
    $tCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    if (!$tCheck || $tCheck->num_rows === 0) {
        $tableMissing = true;
    }
} catch (mysqli_sql_exception $e) {
    $tableMissing = true;
}

// KPI Metrics initialized to default zeroes
$metrics = [
    'total_logs'          => 0,
    'events_today'        => 0,
    'total_modifications' => 0,
    'distinct_actors'     => 0,
];

// Fetch KPI metrics using scalar subqueries if table is present
if (!$tableMissing) {
    try {
        $kpiSql = "SELECT 
            COUNT(*) AS total_logs,
            (SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURRENT_DATE()) AS events_today,
            (SELECT COUNT(*) FROM audit_logs WHERE action IN ('UPDATE', 'STATUS_CHANGE')) AS total_modifications,
            (SELECT COUNT(DISTINCT user_identity) FROM audit_logs) AS distinct_actors
        FROM audit_logs";
        $kpiRes = $conn->query($kpiSql);
        if ($kpiRes) {
            $row = $kpiRes->fetch_assoc();
            if ($row) {
                $metrics = $row;
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Failed to load audit KPI metrics: " . $e->getMessage());
    }
}

// Extract filter parameters
$search       = trim($_GET['q'] ?? '');
$moduleFilter = trim($_GET['module'] ?? 'all');
$actionFilter = trim($_GET['action'] ?? 'all');
$actorFilter  = trim($_GET['actor'] ?? 'all');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');

// Pagination settings
$perPage = 20;
$page    = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$offset  = ($page - 1) * $perPage;

$whereClauses = [];
$types        = '';
$params       = [];

// Keyword search across identifier, actor identity, module, action, table, IP
if ($search !== '') {
    $whereClauses[] = "(al.record_identifier LIKE ? OR al.user_identity LIKE ? OR al.module LIKE ? OR al.action LIKE ? OR al.table_name LIKE ? OR al.ip_address LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)";
    $wildcard = '%' . $search . '%';
    $types .= 'ssssssss';
    for ($i = 0; $i < 8; $i++) {
        $params[] = $wildcard;
    }
}

// Module filter
if ($moduleFilter !== 'all' && isset($allowedModules[$moduleFilter])) {
    $whereClauses[] = "al.module = ?";
    $types .= 's';
    $params[] = $moduleFilter;
}

// Action filter
if ($actionFilter !== 'all' && isset($allowedActions[$actionFilter])) {
    $whereClauses[] = "al.action = ?";
    $types .= 's';
    $params[] = $actionFilter;
}

// Actor filter
if ($actorFilter !== 'all' && $actorFilter !== '') {
    $whereClauses[] = "al.user_identity = ?";
    $types .= 's';
    $params[] = $actorFilter;
}

// Date Range filters
if ($dateFrom !== '') {
    $dFrom = DateTime::createFromFormat('Y-m-d', $dateFrom);
    if ($dFrom && $dFrom->format('Y-m-d') === $dateFrom) {
        $whereClauses[] = "DATE(al.created_at) >= ?";
        $types .= 's';
        $params[] = $dateFrom;
    }
}

if ($dateTo !== '') {
    $dTo = DateTime::createFromFormat('Y-m-d', $dateTo);
    if ($dTo && $dTo->format('Y-m-d') === $dateTo) {
        $whereClauses[] = "DATE(al.created_at) <= ?";
        $types .= 's';
        $params[] = $dateTo;
    }
}

// Distinct actors for filter dropdown
$actorOptions = [];
if (!$tableMissing) {
    try {
        $actRes = $conn->query("SELECT DISTINCT user_identity FROM audit_logs WHERE user_identity IS NOT NULL AND user_identity != '' ORDER BY user_identity ASC");
        if ($actRes) {
            $actorOptions = $actRes->fetch_all(MYSQLI_ASSOC);
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Failed to load audit actor options: " . $e->getMessage());
    }
}

// 1. Subquery Requirement: Filtered count calculation using a derived table subquery
$totalRecords = 0;
$records = [];

if (!$tableMissing) {
    try {
        $countSql = "SELECT COUNT(*) AS total FROM (
            SELECT al.log_id 
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.user_id";
        if (!empty($whereClauses)) {
            $countSql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $countSql .= ") AS filtered_audit_subquery";

        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            if ($types !== '') {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            if ($row = $countRes->fetch_assoc()) {
                $totalRecords = (int)$row['total'];
            }
            $countStmt->close();
        }

        // 2. JOIN Requirement: LEFT JOIN between audit_logs and users
        // Demonstrates live actor lookup with historical snapshot fallback.
        // Also includes a correlated subquery counting total historical events for that record.
        $listSql = "SELECT 
            al.log_id,
            al.user_id,
            al.user_identity,
            al.user_role,
            al.action,
            al.module,
            al.table_name,
            al.record_id,
            al.record_identifier,
            al.ip_address,
            al.created_at,
            -- LEFT JOIN attributes from users table
            u.username AS live_username,
            u.full_name AS live_full_name,
            u.role AS live_role,
            u.is_active AS live_is_active,
            -- Prepared correlated subquery calculating total audit events for this specific record
            (
                SELECT COUNT(*) 
                FROM audit_logs al_sub 
                WHERE al_sub.table_name = al.table_name 
                  AND al_sub.record_id = al.record_id
            ) AS record_total_events
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.user_id";

        if (!empty($whereClauses)) {
            $listSql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $listSql .= " ORDER BY al.log_id DESC LIMIT ? OFFSET ?";
        
        $listStmt = $conn->prepare($listSql);
        if ($listStmt) {
            $bindTypes = $types . 'ii';
            $bindParams = array_merge($params, [$perPage, $offset]);
            $listStmt->bind_param($bindTypes, ...$bindParams);
            $listStmt->execute();
            $res = $listStmt->get_result();
            if ($res) {
                $records = $res->fetch_all(MYSQLI_ASSOC);
            }
            $listStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database error loading audit history: " . $e->getMessage());
        flash_set('error', 'A database error occurred while querying audit history.');
    }
}

$totalPages = max(1, (int) ceil($totalRecords / $perPage));
$hasActiveFilters = ($search !== '' || $moduleFilter !== 'all' || $actionFilter !== 'all' || $actorFilter !== 'all' || $dateFrom !== '' || $dateTo !== '');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Audit History &amp; Traceability</h1>
        <div class="page-subtitle">Module 4 &bull; Immutable Change Log &bull; Access: Admin, QC Officer</div>
    </div>
    <div class="page-actions">
        <span class="badge badge-info" style="font-size: 0.85rem; padding: 0.4rem 0.75rem;">
            Read-Only Audit Trail
        </span>
    </div>
</div>

<?php if ($tableMissing): ?>
    <div class="alert alert-warning">
        <strong>Database Schema Notice:</strong> The <code>audit_logs</code> table is not yet installed in the database.
        Please import <code>sql/audit_logs.sql</code> using phpMyAdmin or MySQL CLI to activate full audit logging.
    </div>
<?php endif; ?>

<!-- Summary KPI Metric Cards (Calculated with Subqueries) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0.5px;">Total Events Logged</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;">
            <?= e(number_format((int)$metrics['total_logs'])) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">All recorded events</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0.5px;">Events Today</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #15803D; margin-top: 0.25rem;">
            <?= e(number_format((int)$metrics['events_today'])) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">Actions logged today</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0.5px;">Updates &amp; Transitions</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #854D0E; margin-top: 0.25rem;">
            <?= e(number_format((int)$metrics['total_modifications'])) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">State changes &amp; edits</div>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); letter-spacing: 0.5px;">Active Actors</div>
        <div style="font-size: 1.75rem; font-weight: 700; color: #1E40AF; margin-top: 0.25rem;">
            <?= e(number_format((int)$metrics['distinct_actors'])) ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">Distinct operators tracked</div>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/audit_logs/index.php')) ?>" class="filter-bar" style="flex-wrap: wrap; gap: 0.75rem;">
            <!-- Keyword Search -->
            <div class="filter-field-grow" style="min-width: 220px;">
                <label for="q" class="form-label">Keyword Search</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by ID, actor, module, action, or IP...">
            </div>

            <!-- Module Filter -->
            <div class="filter-field" style="min-width: 170px;">
                <label for="module" class="form-label">Module</label>
                <select id="module" name="module" class="form-control">
                    <option value="all">All Modules</option>
                    <?php foreach ($allowedModules as $modKey => $modLabel): ?>
                        <option value="<?= e($modKey) ?>" <?= $moduleFilter === $modKey ? 'selected' : '' ?>>
                            <?= e($modLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Action Filter -->
            <div class="filter-field" style="min-width: 160px;">
                <label for="action" class="form-label">Action</label>
                <select id="action" name="action" class="form-control">
                    <option value="all">All Actions</option>
                    <?php foreach ($allowedActions as $actKey => $actLabel): ?>
                        <option value="<?= e($actKey) ?>" <?= $actionFilter === $actKey ? 'selected' : '' ?>>
                            <?= e($actKey) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Actor / Operator Filter -->
            <div class="filter-field" style="min-width: 160px;">
                <label for="actor" class="form-label">Actor</label>
                <select id="actor" name="actor" class="form-control">
                    <option value="all">All Actors</option>
                    <?php foreach ($actorOptions as $actOpt): ?>
                        <option value="<?= e($actOpt['user_identity']) ?>" <?= $actorFilter === $actOpt['user_identity'] ? 'selected' : '' ?>>
                            <?= e($actOpt['user_identity']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range From -->
            <div class="filter-field" style="min-width: 140px;">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>

            <!-- Date Range To -->
            <div class="filter-field" style="min-width: 140px;">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>

            <!-- Filter Buttons -->
            <div class="filter-actions" style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/audit_logs/index.php')) ?>" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Audit Log Data Table -->
<div class="card">
    <div class="card-header">
        <h2>Audit Event Log</h2>
        <span class="badge badge-muted">
            <?= number_format($totalRecords) ?> total <?= $totalRecords === 1 ? 'event' : 'events' ?>
            <?php if ($totalPages > 1): ?>
                &bull; Page <?= (int)$page ?> of <?= (int)$totalPages ?>
            <?php endif; ?>
        </span>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($records)): ?>
            <div class="empty-state" style="padding: 3rem 1.5rem; text-align: center;">
                <p class="text-muted mb-3" style="font-size: 1.05rem;">
                    <?= $hasActiveFilters ? 'No audit events matched your search and filter criteria.' : 'No audit events have been recorded yet.' ?>
                </p>
                <?php if ($hasActiveFilters): ?>
                    <a href="<?= e(url('modules/audit_logs/index.php')) ?>" class="btn btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-id">#</th>
                            <th>Timestamp</th>
                            <th>Actor &amp; Role</th>
                            <th>Action</th>
                            <th>Module / Table</th>
                            <th>Record Reference</th>
                            <th>IP Address</th>
                            <th class="col-actions" style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $log): ?>
                            <?php
                            // Determine actor identity with fallback to historical user_identity
                            $actorDisplay = !empty($log['live_full_name'])
                                ? $log['live_full_name'] . ' (' . $log['live_username'] . ')'
                                : (!empty($log['live_username']) ? $log['live_username'] : $log['user_identity'] . ' (Snapshot)');
                            
                            $roleDisplay = !empty($log['live_role']) ? $log['live_role'] : ($log['user_role'] ?? 'user');

                            // Badge style according to action
                            $actionBadge = 'badge-secondary';
                            if ($log['action'] === 'CREATE') {
                                $actionBadge = 'badge-success';
                            } elseif ($log['action'] === 'UPDATE') {
                                $actionBadge = 'badge-info';
                            } elseif ($log['action'] === 'STATUS_CHANGE') {
                                $actionBadge = 'badge-warning';
                            }
                            ?>
                            <tr>
                                <td class="col-id">
                                    <span class="text-muted"><?= (int)$log['log_id'] ?></span>
                                </td>
                                <td class="text-nowrap">
                                    <span style="font-weight: 500;"><?= e(date('Y-m-d H:i:s', strtotime($log['created_at']))) ?></span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= e($actorDisplay) ?></strong>
                                        <?php if ($log['user_id'] === null || empty($log['live_username'])): ?>
                                            <span class="badge badge-muted text-xs" title="Original user record no longer exists in live database">Preserved</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-muted">Role: <?= e(ucfirst($roleDisplay)) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $actionBadge ?>">
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?= e($allowedModules[$log['module']] ?? ucfirst($log['module'])) ?></div>
                                    <div class="text-xs text-muted"><code><?= e($log['table_name']) ?></code></div>
                                </td>
                                <td>
                                    <?php if (!empty($log['record_identifier'])): ?>
                                        <strong><?= e($log['record_identifier']) ?></strong>
                                    <?php elseif ($log['record_id']): ?>
                                        <span class="text-muted">ID #<?= (int)$log['record_id'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                    <?php if ((int)$log['record_total_events'] > 1): ?>
                                        <div class="text-xs text-muted"><?= (int)$log['record_total_events'] ?> recorded events</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <span class="text-sm"><?= !empty($log['ip_address']) ? e($log['ip_address']) : '<span class="text-muted">Local/Unknown</span>' ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?= e(url('modules/audit_logs/view.php?id=' . (int)$log['log_id'])) ?>" class="btn btn-sm btn-secondary">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer d-flex justify-content-between align-items-center" style="padding: 1rem 1.25rem;">
                    <div class="text-sm text-muted">
                        Showing <?= (int)($offset + 1) ?> to <?= (int)min($offset + $perPage, $totalRecords) ?> of <?= (int)$totalRecords ?> entries
                    </div>
                    <div class="d-flex gap-2">
                        <?php
                        $queryParams = $_GET;
                        // Previous Page Link
                        if ($page > 1) {
                            $queryParams['page'] = $page - 1;
                            $prevUrl = url('modules/audit_logs/index.php?' . http_build_query($queryParams));
                            echo '<a href="' . e($prevUrl) . '" class="btn btn-sm btn-secondary">&laquo; Previous</a>';
                        }

                        // Current Page Badge
                        echo '<span class="btn btn-sm btn-secondary" style="pointer-events: none; opacity: 0.85; font-weight: 600;">Page ' . (int)$page . ' of ' . (int)$totalPages . '</span>';

                        // Next Page Link
                        if ($page < $totalPages) {
                            $queryParams['page'] = $page + 1;
                            $nextUrl = url('modules/audit_logs/index.php?' . http_build_query($queryParams));
                            echo '<a href="' . e($nextUrl) . '" class="btn btn-sm btn-secondary">Next &raquo;</a>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
