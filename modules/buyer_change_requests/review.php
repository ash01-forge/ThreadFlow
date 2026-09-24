<?php
/** ThreadFlow - Admin Review Form for Buyer Change Request. */
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId || $requestId <= 0) { flash_set('error', 'Invalid change request ID specified.'); redirect('modules/buyer_change_requests/index.php'); }
try {
    $stmt = $conn->prepare('SELECT bcr.request_id, bcr.status, bcr.change_type, bcr.impact_level, bcr.requested_value, bo.order_number, req.full_name AS requester_name FROM buyer_change_requests bcr INNER JOIN buyer_orders bo ON bo.order_id = bcr.order_id INNER JOIN users req ON req.user_id = bcr.requester_id WHERE bcr.request_id = ? LIMIT 1');
    if (!$stmt) { throw new mysqli_sql_exception('Review query could not be prepared.'); }
    $stmt->bind_param('i', $requestId); $stmt->execute(); $request = $stmt->get_result()->fetch_assoc(); $stmt->close();
} catch (mysqli_sql_exception $e) { error_log('Database exception loading review form: ' . $e->getMessage()); flash_set('error', 'A database error occurred while loading the review.'); redirect('modules/buyer_change_requests/index.php'); }
if (!$request) { flash_set('error', 'Buyer change request not found.'); redirect('modules/buyer_change_requests/index.php'); }
if ($request['status'] !== 'Submitted') { flash_set('info', 'This request has already been reviewed.'); redirect('modules/buyer_change_requests/view.php?id=' . $requestId); }
$pageTitle = 'Review Change Request'; $currentNav = 'buyer_change_requests';
include __DIR__ . '/../../includes/header.php'; include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.bcr-review { --bcr-ink: #3c1b25; --bcr-muted: #806b72; --bcr-line: #efd9df; }
.bcr-review .bcr-review-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 22px; }
.bcr-review .bcr-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.bcr-review .bcr-review-card { max-width: 900px; overflow: hidden; border: 1px solid var(--bcr-line); border-radius: 14px; background: #fff; }
.bcr-review .bcr-context { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; padding: 20px; background: #fff7f8; border-bottom: 1px solid var(--bcr-line); }
.bcr-review .bcr-context p { margin: 5px 0 0; color: var(--bcr-ink); }
.bcr-review .bcr-form-body { padding: 24px; }
.bcr-review .bcr-decision-intro { margin: 0 0 14px; color: var(--bcr-muted); font-size: .88rem; }
.bcr-review .bcr-decision-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 24px; }
.bcr-review .bcr-decision-option { position: relative; display: block; cursor: pointer; }
.bcr-review .bcr-decision-option input { position: absolute; opacity: 0; }
.bcr-review .bcr-decision-card { display: block; height: 100%; padding: 18px; border: 2px solid var(--bcr-line); border-radius: 11px; }
.bcr-review .bcr-decision-card strong { display: block; margin-bottom: 6px; color: var(--bcr-ink); font-size: 1rem; }
.bcr-review .bcr-decision-card span { color: var(--bcr-muted); font-size: .82rem; }
.bcr-review .bcr-decision-option input:checked + .bcr-decision-card { box-shadow: 0 0 0 3px rgba(120, 0, 15, .12); }
.bcr-review .bcr-decision-option input:focus-visible + .bcr-decision-card { outline: 3px solid #d58a99; outline-offset: 2px; }
.bcr-review .bcr-decision-option input[value="Approved"]:checked + .bcr-decision-card { border-color: #25805c; background: #f2fbf6; }
.bcr-review .bcr-decision-option input[value="Rejected"]:checked + .bcr-decision-card { border-color: #b43b4c; background: #fff4f5; }
.bcr-review .bcr-decision-option input[value="Approved"] + .bcr-decision-card strong { color: #1c704e; }
.bcr-review .bcr-decision-option input[value="Rejected"] + .bcr-decision-card strong { color: #a52e42; }
.bcr-review .bcr-final-actions { display: flex; align-items: center; gap: 10px; margin-top: 20px; }
@media (max-width: 650px) { .bcr-review .bcr-review-head { display: block; } .bcr-review .bcr-review-head .page-actions { margin-top: 14px; } .bcr-review .bcr-context { grid-template-columns: repeat(2, minmax(0, 1fr)); } .bcr-review .bcr-decision-options { grid-template-columns: 1fr; } .bcr-review .bcr-final-actions { display: block; } .bcr-review .bcr-final-actions .btn { width: 100%; margin-bottom: 8px; } }
</style>
<div class="bcr-review"><div class="bcr-review-head"><div><div class="bcr-kicker">Admin review</div><div class="page-title-group"><h1>Review Buyer Change Request</h1><div class="page-subtitle">Admin decision required for <?= e($request['order_number']) ?></div></div></div><div class="page-actions"><a href="<?= e(url('modules/buyer_change_requests/view.php?id=' . $requestId)) ?>" class="btn btn-secondary">View Details</a></div></div>
<div class="bcr-review-card"><div class="bcr-context"><div><span class="form-label">Order</span><p><?= e($request['order_number']) ?></p></div><div><span class="form-label">Requester</span><p><?= e($request['requester_name']) ?></p></div><div><span class="form-label">Change Type</span><p><?= e($request['change_type']) ?></p></div><div><span class="form-label">Requested Value</span><p><?= e($request['requested_value']) ?></p></div></div><div class="bcr-form-body"><form id="bcr-review-form" action="<?= e(url('modules/buyer_change_requests/process_review.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int) $requestId ?>"><fieldset class="form-group" style="border: 0; padding: 0; margin: 0;"><legend class="form-label">Choose a decision <span class="required">*</span></legend><p class="bcr-decision-intro">Your decision will be recorded against this request and cannot be changed here later.</p><div class="bcr-decision-options"><label class="bcr-decision-option"><input type="radio" name="decision" value="Approved" required><span class="bcr-decision-card"><strong>Approve Request</strong><span>Accept the requested change and allow the workflow to proceed.</span></span></label><label class="bcr-decision-option"><input type="radio" name="decision" value="Rejected" required><span class="bcr-decision-card"><strong>Reject Request</strong><span>Decline the requested change and keep the order as currently recorded.</span></span></label></div></fieldset><div class="form-group"><label for="review_notes" class="form-label">Review Notes <span class="required">*</span></label><textarea id="review_notes" name="review_notes" class="form-control" rows="5" maxlength="5000" required placeholder="Explain the approval or rejection decision..."></textarea></div><div class="bcr-final-actions"><button type="submit" class="btn btn-primary">Confirm Decision</button><a href="<?= e(url('modules/buyer_change_requests/view.php?id=' . $requestId)) ?>" class="btn btn-secondary">Cancel</a></div></form></div></div></div>
<script>
document.getElementById('bcr-review-form').addEventListener('submit', function (event) {
    var selected = this.querySelector('input[name="decision"]:checked');
    if (selected && !window.confirm('Confirm this request as ' + (selected.value === 'Approved' ? 'Approved' : 'Rejected') + '?')) {
        event.preventDefault();
    }
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
