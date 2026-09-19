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
<div class="page-header"><div class="page-title-group"><h1>Review Buyer Change Request</h1><div class="page-subtitle">Admin decision required for <?= e($request['order_number']) ?></div></div><div class="page-actions"><a href="<?= e(url('modules/buyer_change_requests/view.php?id=' . $requestId)) ?>" class="btn btn-secondary">View Details</a></div></div>
<div class="card card-form"><div class="card-header"><h2>Decision</h2><span class="badge badge-warning"><?= e($request['impact_level']) ?> Impact</span></div><div class="card-body"><div class="details-grid"><div><span class="form-label">Order</span><p><?= e($request['order_number']) ?></p></div><div><span class="form-label">Requester</span><p><?= e($request['requester_name']) ?></p></div><div><span class="form-label">Change Type</span><p><?= e($request['change_type']) ?></p></div><div><span class="form-label">Requested Value</span><p><?= e($request['requested_value']) ?></p></div></div><form action="<?= e(url('modules/buyer_change_requests/process_review.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int) $requestId ?>"><div class="form-group"><label for="decision" class="form-label">Decision <span class="required">*</span></label><select id="decision" name="decision" class="form-control" required><option value="">Select decision</option><option value="Approved">Approve</option><option value="Rejected">Reject</option></select></div><div class="form-group"><label for="review_notes" class="form-label">Review Notes <span class="required">*</span></label><textarea id="review_notes" name="review_notes" class="form-control" rows="5" maxlength="5000" required placeholder="Explain the approval or rejection decision..."></textarea></div><div class="form-actions"><button type="submit" class="btn btn-primary">Save Decision</button><a href="<?= e(url('modules/buyer_change_requests/view.php?id=' . $requestId)) ?>" class="btn btn-secondary">Cancel</a></div></form></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
