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
include __DIR__ . '/../../includes/header.php'; include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="page-header"><div class="page-title-group"><h1>Buyer Change Requests</h1><div class="page-subtitle">Order-linked change workflow &bull; Access: Admin, Merchandiser</div></div><div class="page-actions"><a href="<?= e(url('modules/buyer_change_requests/create.php')) ?>" class="btn btn-primary">Submit Change Request</a></div></div>
<div class="card mb-4"><div class="card-body"><form method="get" action="<?= e(url('modules/buyer_change_requests/index.php')) ?>" class="filter-bar"><div class="filter-field"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-control"><option value="all">All Statuses</option><?php foreach ($validStatuses as $value): ?><option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div><div class="filter-field"><label for="impact" class="form-label">Impact</label><select id="impact" name="impact" class="form-control"><option value="all">All Impact Levels</option><?php foreach ($validImpacts as $value): ?><option value="<?= e($value) ?>" <?= $impactFilter === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select></div><div class="filter-actions"><button type="submit" class="btn btn-primary">Filter</button><?php if ($statusFilter !== 'all' || $impactFilter !== 'all'): ?><a href="<?= e(url('modules/buyer_change_requests/index.php')) ?>" class="btn btn-secondary">Clear</a><?php endif; ?></div></form></div></div>
<div class="card"><div class="card-header"><h2>Request Register</h2><span class="badge badge-muted"><?= count($requests) ?> Result(s)</span></div><div class="card-body" style="padding: 0;"><?php if (!$requests): ?><div class="empty-state"><p>No buyer change requests match the selected filters.</p><a href="<?= e(url('modules/buyer_change_requests/create.php')) ?>" class="btn btn-primary btn-sm">Submit First Request</a></div><?php else: ?><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Order</th><th>Change</th><th>Impact</th><th>Status</th><th>Requester</th><th>Requested</th><th>Actions</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td>#<?= (int) $request['request_id'] ?></td><td><strong><a href="<?= e(url('modules/buyer_change_requests/view.php?id=' . (int) $request['request_id'])) ?>"><?= e($request['order_number']) ?></a></strong><div class="text-muted text-xs"><?= e($request['style_number']) ?></div></td><td><?= e($request['change_type']) ?><?php if ($request['affected_quantity'] !== null): ?><div class="text-muted text-xs"><?= number_format((int) $request['affected_quantity']) ?> pcs affected</div><?php endif; ?></td><td><span class="badge <?= e(bcr_badge_class($request['impact_level'])) ?>"><?= e($request['impact_level']) ?></span></td><td><span class="badge <?= e(bcr_badge_class($request['status'])) ?>"><?= e($request['status']) ?></span></td><td><?= e($request['requester_name']) ?></td><td><?= e($request['requested_at']) ?></td><td class="text-nowrap"><a href="<?= e(url('modules/buyer_change_requests/view.php?id=' . (int) $request['request_id'])) ?>" class="btn btn-sm btn-secondary">View</a><?php if ($userRole === 'admin' && $request['status'] === 'Submitted'): ?><a href="<?= e(url('modules/buyer_change_requests/review.php?id=' . (int) $request['request_id'])) ?>" class="btn btn-sm btn-primary">Review</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
