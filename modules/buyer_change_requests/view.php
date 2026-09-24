<?php
/** ThreadFlow - Buyer Change Request Details. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);
$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId || $requestId <= 0) { flash_set('error', 'Invalid change request ID specified.'); redirect('modules/buyer_change_requests/index.php'); }
try {
    $stmt = $conn->prepare('SELECT bcr.*, bo.order_number, bo.style_number, bo.total_quantity, bo.order_date, bo.delivery_date, bo.status AS order_status, b.buyer_id, b.buyer_name, req.full_name AS requester_name, req.username AS requester_username, rev.full_name AS reviewer_name FROM buyer_change_requests bcr INNER JOIN buyer_orders bo ON bo.order_id = bcr.order_id INNER JOIN buyers b ON b.buyer_id = bo.buyer_id INNER JOIN users req ON req.user_id = bcr.requester_id LEFT JOIN users rev ON rev.user_id = bcr.reviewed_by WHERE bcr.request_id = ? LIMIT 1');
    if (!$stmt) { throw new mysqli_sql_exception('Request detail query could not be prepared.'); }
    $stmt->bind_param('i', $requestId); $stmt->execute(); $request = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$request) { flash_set('error', 'Buyer change request not found.'); redirect('modules/buyer_change_requests/index.php'); }
    $batchStmt = $conn->prepare('SELECT batch_number, planned_quantity, status, start_date, target_end_date FROM production_batches WHERE order_id = ? ORDER BY batch_id');
    $batchStmt->bind_param('i', $request['order_id']); $batchStmt->execute(); $batches = $batchStmt->get_result()->fetch_all(MYSQLI_ASSOC); $batchStmt->close();
} catch (mysqli_sql_exception $e) { error_log('Database exception loading buyer change request: ' . $e->getMessage()); flash_set('error', 'A database error occurred while loading the request.'); redirect('modules/buyer_change_requests/index.php'); }
$pageTitle = 'Change Request #' . $requestId; $currentNav = 'buyer_change_requests';
function bcr_view_badge(string $value): string { return $value === 'Critical' || $value === 'Rejected' ? 'badge-danger' : ($value === 'High' ? 'badge-warning' : ($value === 'Approved' || $value === 'Low' ? 'badge-success' : 'badge-info')); }
include __DIR__ . '/../../includes/header.php'; include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.bcr-view { --bcr-ink: #3c1b25; --bcr-muted: #806b72; --bcr-line: #efd9df; }
.bcr-view .bcr-hero { padding: 24px; margin-bottom: 20px; border-radius: 14px; color: #fff; background: linear-gradient(120deg, #78000f, #a83c51); }
.bcr-view .bcr-hero .page-subtitle, .bcr-view .bcr-hero .bcr-kicker { color: #f9dfe4; }
.bcr-view .bcr-hero .page-header { margin: 0; }
.bcr-view .bcr-kicker { margin-bottom: 7px; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.bcr-view .bcr-hero h1 { margin-bottom: 6px; color: #fff; }
.bcr-view .bcr-hero .page-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.bcr-view .bcr-hero .btn-review-decision { display: inline-flex; align-items: center; gap: 7px; color: #78000f; background: #fff; border: 1px solid #fff; box-shadow: 0 3px 10px rgba(48, 0, 10, .22); font-weight: 700; }
.bcr-view .bcr-hero .btn-review-decision:hover { color: #5c000c; background: #fff4f6; border-color: #fff4f6; box-shadow: 0 5px 14px rgba(48, 0, 10, .3); transform: translateY(-1px); }
.bcr-view .bcr-hero .btn-review-decision:focus-visible { color: #5c000c; background: #fff; border-color: #fff; outline: 3px solid #ffd6de; outline-offset: 3px; box-shadow: 0 3px 10px rgba(48, 0, 10, .22); }
.bcr-view .bcr-hero .btn-secondary { color: #fff; background: rgba(255, 255, 255, .1); border: 1px solid rgba(255, 255, 255, .75); }
.bcr-view .bcr-hero .btn-secondary:hover { color: #fff; background: rgba(255, 255, 255, .2); border-color: #fff; }
.bcr-view .bcr-hero .btn-secondary:focus-visible { color: #fff; background: rgba(255, 255, 255, .16); border-color: #fff; outline: 3px solid #ffd6de; outline-offset: 3px; }
.bcr-view .bcr-status-strip { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
.bcr-view .bcr-panel { margin-bottom: 18px; border: 1px solid var(--bcr-line); border-radius: 13px; background: #fff; overflow: hidden; }
.bcr-view .bcr-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 17px 20px; border-bottom: 1px solid var(--bcr-line); }
.bcr-view .bcr-panel-head h2 { margin: 0; color: var(--bcr-ink); font-size: 1.05rem; }
.bcr-view .bcr-panel-body { padding: 20px; }
.bcr-view .bcr-info-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
.bcr-view .bcr-info-grid p, .bcr-view .bcr-panel-body p { margin: 5px 0 0; color: var(--bcr-ink); }
.bcr-view .bcr-value-compare { display: grid; grid-template-columns: 1fr 42px 1fr; align-items: stretch; gap: 12px; margin-bottom: 20px; }
.bcr-view .bcr-value { padding: 16px; border-radius: 10px; background: #fff7f8; }
.bcr-view .bcr-value.requested { background: #f2fbf6; border: 1px solid #c8e8d5; }
.bcr-view .bcr-value small { color: var(--bcr-muted); font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.bcr-view .bcr-value strong { display: block; margin-top: 8px; color: var(--bcr-ink); overflow-wrap: anywhere; }
.bcr-view .bcr-arrow { display: grid; place-items: center; color: #78000f; font-size: 1.4rem; }
.bcr-view .bcr-outcome-approved { border-color: #c8e8d5; }
.bcr-view .bcr-outcome-approved .bcr-panel-head { background: #f2fbf6; }
.bcr-view .bcr-outcome-rejected { border-color: #f0c8ce; }
.bcr-view .bcr-outcome-rejected .bcr-panel-head { background: #fff4f5; }
@media (max-width: 800px) { .bcr-view .bcr-info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .bcr-view .bcr-hero .page-header, .bcr-view .bcr-panel-head { display: block; } .bcr-view .bcr-hero .page-actions { margin-top: 16px; } .bcr-view .bcr-hero .page-actions .btn { width: 100%; margin-bottom: 7px; text-align: center; } .bcr-view .bcr-info-grid { grid-template-columns: 1fr; } .bcr-view .bcr-value-compare { grid-template-columns: 1fr; } .bcr-view .bcr-arrow { transform: rotate(90deg); } }
</style>
<div class="bcr-view"><div class="bcr-hero"><div class="page-header"><div class="page-title-group"><div class="bcr-kicker">Request dossier</div><h1>Buyer Change Request #<?= (int) $request['request_id'] ?></h1><div class="page-subtitle">Order <?= e($request['order_number']) ?> &bull; Submitted <?= e($request['requested_at']) ?></div></div><div class="page-actions"><?php if ($userRole === 'admin' && $request['status'] === 'Submitted'): ?><a href="<?= e(url('modules/buyer_change_requests/review.php?id=' . $requestId)) ?>" class="btn btn-review-decision">&#10003; Review &amp; Decide</a><?php endif; ?><a href="<?= e(url('modules/buyer_change_requests/index.php')) ?>" class="btn btn-secondary">Back to List</a></div></div><div class="bcr-status-strip"><span class="badge <?= e(bcr_view_badge($request['impact_level'])) ?>"><?= e($request['impact_level']) ?> Impact</span><span class="badge <?= e(bcr_view_badge($request['status'])) ?>"><?= e($request['status']) ?></span></div></div>
<div class="bcr-panel"><div class="bcr-panel-head"><h2>Request Summary</h2><span class="badge <?= e(bcr_view_badge($request['change_type'])) ?>"><?= e($request['change_type']) ?></span></div><div class="bcr-panel-body"><div class="bcr-value-compare"><div class="bcr-value"><small>Current value</small><strong><?= e($request['current_value']) ?></strong></div><div class="bcr-arrow" aria-hidden="true">&#8594;</div><div class="bcr-value requested"><small>Requested value</small><strong><?= e($request['requested_value']) ?></strong></div></div><div class="bcr-info-grid"><div><span class="form-label">Order</span><p><a href="<?= e(url('modules/orders/view.php?id=' . (int) $request['order_id'])) ?>"><?= e($request['order_number']) ?></a></p></div><div><span class="form-label">Buyer / Style</span><p><?= e($request['buyer_name']) ?> / <?= e($request['style_number']) ?></p></div><div><span class="form-label">Affected Quantity</span><p><?= $request['affected_quantity'] === null ? 'Not specified' : number_format((int) $request['affected_quantity']) . ' pcs' ?></p></div><div><span class="form-label">Requester</span><p><?= e($request['requester_name']) ?> (<?= e($request['requester_username']) ?>)</p></div><div><span class="form-label">Order Quantity</span><p><?= number_format((int) $request['total_quantity']) ?> pcs</p></div><div><span class="form-label">Order Status</span><p><?= e($request['order_status']) ?></p></div></div><div class="form-group"><span class="form-label">Reason / Details</span><p><?= nl2br(e($request['reason_details'])) ?></p></div></div></div>
<div class="bcr-panel"><div class="bcr-panel-head"><h2>Production Impact Analysis</h2><span class="badge <?= e(bcr_view_badge($request['impact_level'])) ?>"><?= e($request['impact_level']) ?></span></div><div class="bcr-panel-body"><p><strong>Recommended action:</strong> <?= e($request['recommended_action']) ?></p><?php if (!$batches): ?><p class="text-muted">No related production batches exist. Production has not started.</p><?php else: ?><div class="table-responsive"><table class="table"><thead><tr><th>Batch</th><th>Planned Quantity</th><th>Status</th><th>Timeline</th></tr></thead><tbody><?php foreach ($batches as $batch): ?><tr><td><?= e($batch['batch_number']) ?></td><td><?= number_format((int) $batch['planned_quantity']) ?> pcs</td><td><?= e($batch['status']) ?></td><td><?= e($batch['start_date']) ?> to <?= e($batch['target_end_date']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
<div class="bcr-panel <?= $request['status'] === 'Approved' ? 'bcr-outcome-approved' : ($request['status'] === 'Rejected' ? 'bcr-outcome-rejected' : '') ?>"><div class="bcr-panel-head"><h2>Review Outcome</h2><span class="badge <?= e(bcr_view_badge($request['status'])) ?>"><?= e($request['status']) ?></span></div><div class="bcr-panel-body"><?php if ($request['status'] === 'Submitted'): ?><p class="text-muted">This request is awaiting Admin review. The original buyer order remains unchanged.</p><?php else: ?><div class="bcr-info-grid"><div><span class="form-label">Decision</span><p><?= e($request['status']) ?></p></div><div><span class="form-label">Reviewed By</span><p><?= e($request['reviewer_name'] ?: 'Unknown') ?></p></div><div><span class="form-label">Reviewed At</span><p><?= e($request['reviewed_at']) ?></p></div></div><div class="form-group"><span class="form-label">Review Notes</span><p><?= nl2br(e($request['review_notes'])) ?></p></div><?php endif; ?></div></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
