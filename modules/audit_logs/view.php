<?php
/**
 * ThreadFlow - Audit Log Event Details View
 * 
 * Module 4: Assigned to Member 4 (QC Officer / Admin).
 * Strictly read-only event dossier.
 * Demonstrates LEFT JOIN with users table and chronological timeline subqueries.
 */

require_once __DIR__ . '/../../includes/auth.php';

// Enforce role authorization (Admin and QC Officer)
require_role(['admin', 'qc_officer']);

// Enforce strictly read-only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$logId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$logId || $logId <= 0) {
    flash_set('error', 'Invalid audit log ID specified.');
    redirect('modules/audit_logs/index.php');
}

$allowedModules = [
    'defect_types'        => 'Defect Types',
    'quality_inspections' => 'Quality Inspections',
    'rework'              => 'Rework Management',
    'packing_shipment'    => 'Packing & Shipment',
];

$event = null;

try {
    // JOIN and Subquery implementation:
    // 1. LEFT JOIN with users: Retrieves live user record while preserving log if user is deleted.
    // 2. Subqueries for previous_log_id, next_log_id, and total_record_events for entity timeline navigation.
    $sql = "SELECT 
                al.log_id,
                al.user_id,
                al.user_identity,
                al.user_role,
                al.action,
                al.module,
                al.table_name,
                al.record_id,
                al.record_identifier,
                al.old_values,
                al.new_values,
                al.ip_address,
                al.user_agent,
                al.created_at,
                -- Live user record via LEFT JOIN
                u.username AS live_username,
                u.full_name AS live_full_name,
                u.email AS live_email,
                u.role AS live_role,
                u.is_active AS live_is_active,
                -- Timeline Subquery 1: Previous audit event for the exact same business record
                (
                    SELECT al_prev.log_id 
                    FROM audit_logs al_prev 
                    WHERE al_prev.table_name = al.table_name 
                      AND al_prev.record_id = al.record_id 
                      AND al_prev.log_id < al.log_id 
                    ORDER BY al_prev.log_id DESC 
                    LIMIT 1
                ) AS previous_log_id,
                -- Timeline Subquery 2: Subsequent audit event for the exact same business record
                (
                    SELECT al_next.log_id 
                    FROM audit_logs al_next 
                    WHERE al_next.table_name = al.table_name 
                      AND al_next.record_id = al.record_id 
                      AND al_next.log_id > al.log_id 
                    ORDER BY al_next.log_id ASC 
                    LIMIT 1
                ) AS next_log_id,
                -- Historical Depth Subquery 3: Total count of audit events registered for this record
                (
                    SELECT COUNT(*) 
                    FROM audit_logs al_total 
                    WHERE al_total.table_name = al.table_name 
                      AND al_total.record_id = al.record_id
                ) AS total_record_events
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.user_id
            WHERE al.log_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare audit log detail query: " . $conn->error);
    }

    $stmt->bind_param("i", $logId);
    $stmt->execute();
    $res = $stmt->get_result();
    $event = $res->fetch_assoc();
    $stmt->close();

    if (!$event) {
        flash_set('error', 'Audit event record not found.');
        redirect('modules/audit_logs/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading audit event: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading audit event details.');
    redirect('modules/audit_logs/index.php');
}

$pageTitle = 'Audit Event #' . (int)$event['log_id'];
$currentNav = 'audit_logs';

// Determine Actor Identity (with snapshot fallback)
$actorDisplay = !empty($event['live_full_name'])
    ? $event['live_full_name'] . ' (' . $event['live_username'] . ')'
    : (!empty($event['live_username']) ? $event['live_username'] : $event['user_identity'] . ' (Snapshot)');

$roleDisplay = !empty($event['live_role']) ? $event['live_role'] : ($event['user_role'] ?? 'user');

// Action badge styling
$actionBadge = 'badge-secondary';
if ($event['action'] === 'CREATE') {
    $actionBadge = 'badge-success';
} elseif ($event['action'] === 'UPDATE') {
    $actionBadge = 'badge-info';
} elseif ($event['action'] === 'STATUS_CHANGE') {
    $actionBadge = 'badge-warning';
}

// Decode JSON payloads safely
$oldArray = !empty($event['old_values']) ? json_decode($event['old_values'], true) : null;
$newArray = !empty($event['new_values']) ? json_decode($event['new_values'], true) : null;

// Collect unique keys for side-by-side comparison
$allKeys = [];
if (is_array($oldArray)) {
    $allKeys = array_merge($allKeys, array_keys($oldArray));
}
if (is_array($newArray)) {
    $allKeys = array_merge($allKeys, array_keys($newArray));
}
$allKeys = array_unique($allKeys);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Audit Event Dossier</h1>
        <div class="page-subtitle">
            Log #<?= (int)$event['log_id'] ?> &bull; 
            Recorded <?= e(date('F j, Y \a\t H:i:s', strtotime($event['created_at']))) ?>
        </div>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= e(url('modules/audit_logs/index.php')) ?>" class="btn btn-secondary">
            &larr; Back to Audit History
        </a>

        <?php if (!empty($event['previous_log_id'])): ?>
            <a href="<?= e(url('modules/audit_logs/view.php?id=' . (int)$event['previous_log_id'])) ?>" class="btn btn-secondary" title="View earlier change to this record">
                &laquo; Earlier Change (#<?= (int)$event['previous_log_id'] ?>)
            </a>
        <?php endif; ?>

        <?php if (!empty($event['next_log_id'])): ?>
            <a href="<?= e(url('modules/audit_logs/view.php?id=' . (int)$event['next_log_id'])) ?>" class="btn btn-secondary" title="View subsequent change to this record">
                Subsequent Change (#<?= (int)$event['next_log_id'] ?>) &raquo;
            </a>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">

    <!-- Card 1: Event Summary Metadata -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h2>Event Metadata</h2>
            <span class="badge <?= $actionBadge ?>"><?= e($event['action']) ?></span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th style="width: 170px;">Event Action</th>
                            <td><span class="badge <?= $actionBadge ?>"><?= e($event['action']) ?></span></td>
                        </tr>
                        <tr>
                            <th>Module</th>
                            <td><strong><?= e($allowedModules[$event['module']] ?? ucfirst($event['module'])) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Database Table</th>
                            <td><code><?= e($event['table_name']) ?></code></td>
                        </tr>
                        <tr>
                            <th>Record Reference</th>
                            <td>
                                <strong><?= !empty($event['record_identifier']) ? e($event['record_identifier']) : 'Record #' . (int)$event['record_id'] ?></strong>
                                <?php if ($event['record_id']): ?>
                                    <span class="text-xs text-muted">(ID #<?= (int)$event['record_id'] ?>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Record Event Depth</th>
                            <td>
                                <span class="badge badge-muted">
                                    <?= (int)$event['total_record_events'] ?> <?= (int)$event['total_record_events'] === 1 ? 'total event' : 'total events' ?> for this record
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Timestamp</th>
                            <td><?= e(date('Y-m-d H:i:s', strtotime($event['created_at']))) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Card 2: Actor Identity & Origin Metadata -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h2>Actor &amp; Origin Details</h2>
            <span class="badge badge-muted">Audit Verification</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th style="width: 170px;">Actor Identity</th>
                            <td>
                                <strong><?= e($actorDisplay) ?></strong>
                                <?php if ($event['user_id'] === null || empty($event['live_username'])): ?>
                                    <span class="badge badge-warning text-xs ml-1" title="User account no longer exists in live database">Historical Snapshot</span>
                                <?php else: ?>
                                    <span class="badge badge-success text-xs ml-1">Verified User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Live Account Email</th>
                            <td>
                                <?= !empty($event['live_email']) ? e($event['live_email']) : '<span class="text-muted">Not Available / Deleted User</span>' ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Actor Role</th>
                            <td>
                                <span class="badge badge-secondary"><?= e(ucfirst($roleDisplay)) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th>Origin IP Address</th>
                            <td>
                                <code><?= !empty($event['ip_address']) ? e($event['ip_address']) : 'Local/Unavailable' ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th>Client User Agent</th>
                            <td style="word-break: break-all; font-size: 0.8rem; color: var(--color-text-muted);">
                                <?= !empty($event['user_agent']) ? e($event['user_agent']) : 'Not reported' ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- State Comparison / Change Diff Card -->
<div class="card mb-4">
    <div class="card-header">
        <h2>
            <?php if ($event['action'] === 'UPDATE'): ?>
                Field Comparison (Old vs New Values)
            <?php elseif ($event['action'] === 'STATUS_CHANGE'): ?>
                Status Transition Summary
            <?php else: ?>
                Initial Record Attributes
            <?php endif; ?>
        </h2>
        <span class="badge badge-muted"><?= count($allKeys) ?> <?= count($allKeys) === 1 ? 'field' : 'fields' ?> tracked</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($allKeys)): ?>
            <div class="empty-state" style="padding: 2.5rem; text-align: center;">
                <p class="text-muted">No state attribute data was recorded for this event.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Attribute / Field</th>
                            <?php if ($event['action'] === 'UPDATE' || $event['action'] === 'STATUS_CHANGE'): ?>
                                <th>Previous State (Old)</th>
                                <th>Updated State (New)</th>
                                <th style="width: 120px; text-align: center;">Change Status</th>
                            <?php else: ?>
                                <th>Value</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allKeys as $key): ?>
                            <?php
                            $oldVal = $oldArray[$key] ?? null;
                            $newVal = $newArray[$key] ?? null;

                            // Format values for human-readable display
                            $formatValue = function ($val) {
                                if ($val === null) {
                                    return '<span class="text-muted">&mdash; (NULL)</span>';
                                }
                                if (is_bool($val)) {
                                    return $val ? 'true' : 'false';
                                }
                                if (is_array($val)) {
                                    return '<pre style="margin: 0; font-size: 0.75rem; background: var(--color-surface-subtle); padding: 4px 8px; border-radius: 4px;">' . e(json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
                                }
                                return e((string)$val);
                            };

                            $isChanged = ($oldVal !== $newVal);
                            ?>
                            <tr <?= $isChanged && ($event['action'] === 'UPDATE' || $event['action'] === 'STATUS_CHANGE') ? 'style="background-color: rgba(254, 249, 195, 0.35);"' : '' ?>>
                                <td>
                                    <strong><code><?= e($key) ?></code></strong>
                                </td>

                                <?php if ($event['action'] === 'UPDATE' || $event['action'] === 'STATUS_CHANGE'): ?>
                                    <td style="color: <?= $isChanged ? '#991B1B' : 'inherit' ?>;">
                                        <?= $formatValue($oldVal) ?>
                                    </td>
                                    <td style="color: <?= $isChanged ? '#15803D' : 'inherit' ?>; font-weight: <?= $isChanged ? '600' : 'normal' ?>;">
                                        <?= $formatValue($newVal) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($isChanged): ?>
                                            <span class="badge badge-warning">Modified</span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Unchanged</span>
                                        <?php endif; ?>
                                    </td>
                                <?php else: ?>
                                    <td>
                                        <?= $formatValue($newVal) ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Technical Raw Payloads (Collapsible) -->
<div class="card mb-4">
    <div class="card-header" style="cursor: pointer;">
        <h2>Technical Raw Data (Sanitized JSON)</h2>
        <span class="badge badge-info">Developer Inspection</span>
    </div>
    <div class="card-body">
        <details>
            <summary style="cursor: pointer; font-weight: 600; color: var(--color-primary); margin-bottom: 0.75rem;">
                Click to expand raw JSON state payloads
            </summary>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div>
                    <h3 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;">Old Values JSON</h3>
                    <pre style="background: var(--color-surface-subtle); padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.75rem; overflow-x: auto; border: 1px solid var(--color-border);"><?= e(!empty($event['old_values']) ? $event['old_values'] : 'null') ?></pre>
                </div>
                <div>
                    <h3 style="font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem;">New Values JSON</h3>
                    <pre style="background: var(--color-surface-subtle); padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.75rem; overflow-x: auto; border: 1px solid var(--color-border);"><?= e(!empty($event['new_values']) ? $event['new_values'] : 'null') ?></pre>
                </div>
            </div>
        </details>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
