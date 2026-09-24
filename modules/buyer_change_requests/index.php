<?php
/** ThreadFlow - Buyer Change Request Workflow List. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);

$pageTitle = 'Buyer Change Requests';
$currentNav = 'buyer_change_requests';
$statusFilter = trim($_GET['status'] ?? 'all');
$impactFilter = trim($_GET['impact'] ?? 'all');
$validStatuses = ['Submitted', 'Approved', 'Rejected'];
$validImpacts = ['Low', 'Medium', 'High', 'Critical'];
$where = [];
$params = [];
$types = '';
if ($statusFilter !== 'all' && in_array($statusFilter, $validStatuses, true)) {
    $where[] = 'bcr.status = ?'; $params[] = $statusFilter; $types .= 's';
}
if ($impactFilter !== 'all' && in_array($impactFilter, $validImpacts, true)) {
    $where[] = 'bcr.impact_level = ?'; $params[] = $impactFilter; $types .= 's';
}
$sql = 'SELECT bcr.request_id, bcr.change_type, bcr.impact_level, bcr.status, bcr.affected_quantity, bcr.requested_at,
               bo.order_id, bo.order_number, bo.style_number, u.full_name AS requester_name
        FROM buyer_change_requests bcr
        INNER JOIN buyer_orders bo ON bo.order_id = bcr.order_id
        INNER JOIN users u ON u.user_id = bcr.requester_id';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY bcr.requested_at DESC, bcr.request_id DESC';
$requests = [];
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log('Buyer change request list prepare failed: ' . $conn->error); flash_set('error', 'A database error occurred while loading requests.'); }
    else {
        if ($types !== '') { $stmt->bind_param($types, ...$params); }
        $stmt->execute(); $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    }
} catch (mysqli_sql_exception $e) { error_log('Buyer change request list error: ' . $e->getMessage()); flash_set('error', 'A database error occurred while loading requests.'); }
function bcr_badge_class(string $value): string {
    return $value === 'Critical' || $value === 'Rejected' ? 'badge-danger' : ($value === 'High' ? 'badge-warning' : ($value === 'Approved' || $value === 'Low' ? 'badge-success' : 'badge-info'));
}
$submittedCount = count(array_filter($requests, static fn ($request) => $request['status'] === 'Submitted'));
$approvedCount = count(array_filter($requests, static fn ($request) => $request['status'] === 'Approved'));
$rejectedCount = count(array_filter($requests, static fn ($request) => $request['status'] === 'Rejected'));
include __DIR__ . '/../../includes/header.php'; include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.bcr-list { --bcr-ink: #3c1b25; --bcr-muted: #806b72; --bcr-line: #efd9df; --bcr-soft: #fff7f8; }
.bcr-list .bcr-hero { display: flex; justify-content: space-between; gap: 24px; align-items: flex-end; margin-bottom: 24px; }
.bcr-list .bcr-hero h1 { margin-bottom: 6px; }
.bcr-list .bcr-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.bcr-list .bcr-summary { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 20px; }
.bcr-list .bcr-stat { min-width: 112px; padding: 12px 14px; border: 1px solid var(--bcr-line); border-radius: 10px; background: #fff; }
.bcr-list .bcr-stat strong { display: block; color: var(--bcr-ink); font-size: 1.25rem; }
.bcr-list .bcr-stat span { color: var(--bcr-muted); font-size: .75rem; }
.bcr-list .bcr-toolbar { display: flex; align-items: flex-end; gap: 14px; padding: 16px; border: 1px solid var(--bcr-line); border-radius: 12px; background: var(--bcr-soft); margin-bottom: 20px; }
.bcr-list .bcr-toolbar .filter-field { flex: 1; margin: 0; }
.bcr-list .bcr-toolbar .filter-actions { display: flex; gap: 8px; }
.bcr-list .bcr-register { overflow: hidden; border: 1px solid var(--bcr-line); border-radius: 14px; background: #fff; }
.bcr-list .bcr-register-head { display: flex; justify-content: space-between; gap: 16px; align-items: center; padding: 18px 20px; border-bottom: 1px solid var(--bcr-line); }
.bcr-list .bcr-register-head h2 { margin: 0; color: var(--bcr-ink); font-size: 1.1rem; }
.bcr-list .bcr-request-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; padding: 16px; }
.bcr-list .bcr-request { padding: 18px; border: 1px solid var(--bcr-line); border-left: 4px solid #cf9eaa; border-radius: 10px; background: #fff; }
.bcr-list .bcr-request[data-status="Submitted"] { border-left-color: #b77800; }
.bcr-list .bcr-request[data-status="Approved"] { border-left-color: #25805c; }
.bcr-list .bcr-request[data-status="Rejected"] { border-left-color: #b43b4c; }
.bcr-list .bcr-request-top, .bcr-list .bcr-request-meta, .bcr-list .bcr-request-actions { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.bcr-list .bcr-request-top { margin-bottom: 15px; }
.bcr-list .bcr-request-id { color: var(--bcr-muted); font-size: .76rem; font-weight: 700; }
.bcr-list .bcr-order { color: #78000f; font-size: 1.05rem; font-weight: 700; }
.bcr-list .bcr-style { color: var(--bcr-muted); font-size: .8rem; }
.bcr-list .bcr-request-meta { justify-content: flex-start; flex-wrap: wrap; margin: 16px 0; }
.bcr-list .bcr-meta-item { min-width: 100px; }
.bcr-list .bcr-meta-item small { display: block; color: var(--bcr-muted); font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; }
.bcr-list .bcr-meta-item strong { color: var(--bcr-ink); font-size: .86rem; }
.bcr-list .bcr-request-actions { padding-top: 14px; border-top: 1px solid #f4e7e9; }
.bcr-list .bcr-request-actions .btn { flex: 1; text-align: center; }
@media (max-width: 700px) { .bcr-list .bcr-hero { display: block; } .bcr-list .bcr-hero .page-actions { margin-top: 16px; } .bcr-list .bcr-toolbar { display: block; } .bcr-list .bcr-toolbar .filter-field + .filter-field { margin-top: 12px; } .bcr-list .bcr-toolbar .filter-actions { margin-top: 14px; } .bcr-list .bcr-toolbar .btn { flex: 1; text-align: center; } }
</style>
<div class="bcr-list"><div class="bcr-hero"><div><div class="bcr-kicker">Buyer order workflow</div><div class="page-title-group"><h1>Buyer Change Requests</h1><div class="page-subtitle">Review requested order changes, understand production impact, and keep decisions traceable.</div></div></div><div class="page-actions"><a href="<?= e(url('modules/buyer_change_requests/create.php')) ?>" class="btn btn-primary">+ Submit Change Request</a></div></div>
<div class="bcr-summary"><div class="bcr-stat"><strong><?= count($requests) ?></strong><span>Total results</span></div><div class="bcr-stat"><strong><?= $submittedCount ?></strong><span>Awaiting review</span></div><div class="bcr-stat"><strong><?= $approvedCount ?></strong><span>Approved</span></div><div class="bcr-stat"><strong><?= $rejectedCount ?></strong><span>Rejected</span></div></div>
<div class="bcr-toolbar"><form method="get" action="<?= e(url('modules/buyer_change_requests/index.php')) ?>" class="filter-bar" style="display: contents;"><div class="filter-field"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-control"><option value="all">All Statuses</option><?php foreach ($validStatuses as $value): ?><option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div><div class="filter-field"><label for="impact" class="form-label">Impact</label><select id="impact" name="impact" class="form-control"><option value="all">All Impact Levels</option><?php foreach ($validImpacts as $value): ?><option value="<?= e($value) ?>" <?= $impactFilter === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div><div class="filter-actions"><button type="submit" class="btn btn-primary">Apply Filters</button><?php if ($statusFilter !== 'all' || $impactFilter !== 'all'): ?><a href="<?= e(url('modules/buyer_change_requests/index.php')) ?>" class="btn btn-secondary">Clear</a><?php endif; ?></div></form></div>
<div class="bcr-register"><div class="bcr-register-head"><h2>Request Register</h2><span class="badge badge-muted"><?= count($requests) ?> Result(s)</span></div><?php if (!$requests): ?><div class="empty-state"><p>No buyer change requests match the selected filters.</p><a href="<?= e(url('modules/buyer_change_requests/create.php')) ?>" class="btn btn-primary btn-sm">Submit First Request</a></div><?php else: ?><div class="bcr-request-grid"><?php foreach ($requests as $request): ?><article class="bcr-request" data-status="<?= e($request['status']) ?>"><div class="bcr-request-top"><span class="bcr-request-id">#<?= (int) $request['request_id'] ?></span><span class="badge <?= e(bcr_badge_class($request['status'])) ?>"><?= e($request['status']) ?></span></div><a class="bcr-order" href="<?= e(url('modules/buyer_change_requests/view.php?id=' . (int) $request['request_id'])) ?>"><?= e($request['order_number']) ?></a><div class="bcr-style">Style <?= e($request['style_number']) ?></div><div class="bcr-request-meta"><div class="bcr-meta-item"><small>Change</small><strong><?= e($request['change_type']) ?></strong></div><div class="bcr-meta-item"><small>Quantity</small><strong><?= $request['affected_quantity'] === null ? 'Not specified' : number_format((int) $request['affected_quantity']) . ' pcs' ?></strong></div><div class="bcr-meta-item"><small>Impact</small><span class="badge <?= e(bcr_badge_class($request['impact_level'])) ?>"><?= e($request['impact_level']) ?></span></div></div><div class="bcr-request-meta"><div class="bcr-meta-item"><small>Requester</small><strong><?= e($request['requester_name']) ?></strong></div><div class="bcr-meta-item"><small>Requested</small><strong><?= e($request['requested_at']) ?></strong></div></div><div class="bcr-request-actions"><a href="<?= e(url('modules/buyer_change_requests/view.php?id=' . (int) $request['request_id'])) ?>" class="btn btn-sm btn-secondary"><?= $request['status'] === 'Submitted' && $userRole === 'admin' ? 'View Details' : 'View Outcome' ?></a><?php if ($userRole === 'admin' && $request['status'] === 'Submitted'): ?><a href="<?= e(url('modules/buyer_change_requests/review.php?id=' . (int) $request['request_id'])) ?>" class="btn btn-sm btn-primary">Review Decision</a><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
