<?php
/** ThreadFlow - Submit Buyer Change Request. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);
$pageTitle = 'Submit Buyer Change Request'; $currentNav = 'buyer_change_requests';
$errors = $_SESSION['_form_errors'] ?? []; $old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
$orders = [];
try {
    $stmt = $conn->prepare("SELECT order_id, order_number, style_number, total_quantity, delivery_date, status FROM buyer_orders ORDER BY order_id DESC");
    if ($stmt) { $stmt->execute(); $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
    else { error_log('Buyer change request order list prepare failed: ' . $conn->error); flash_set('error', 'A database error occurred while loading orders.'); }
} catch (mysqli_sql_exception $e) { error_log('Buyer change request order list error: ' . $e->getMessage()); flash_set('error', 'A database error occurred while loading orders.'); }
$changeTypes = ['Quantity', 'Style', 'Delivery Date', 'Color', 'Other'];
include __DIR__ . '/../../includes/header.php'; include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="page-header"><div class="page-title-group"><h1>Submit Buyer Change Request</h1><div class="page-subtitle">Impact is calculated from the selected order and its production batches.</div></div><div class="page-actions"><a href="<?= e(url('modules/buyer_change_requests/index.php')) ?>" class="btn btn-secondary">Back to Requests</a></div></div>
<div class="card card-form"><div class="card-header"><h2>Request Details</h2></div><div class="card-body"><form action="<?= e(url('modules/buyer_change_requests/store.php')) ?>" method="post" novalidate><?= csrf_field() ?>
<div class="form-group"><label for="order_id" class="form-label">Buyer Order <span class="required">*</span></label><select id="order_id" name="order_id" class="form-control <?= isset($errors['order_id']) ? 'has-error' : '' ?>" required><option value="">Select an existing order</option><?php foreach ($orders as $order): ?><option value="<?= (int) $order['order_id'] ?>" <?= (string) ($old['order_id'] ?? '') === (string) $order['order_id'] ? 'selected' : '' ?>><?= e($order['order_number'] . ' — ' . $order['style_number'] . ' — ' . number_format((int) $order['total_quantity']) . ' pcs — ' . $order['status']) ?></option><?php endforeach; ?></select><?php if (isset($errors['order_id'])): ?><span class="form-error"><?= e($errors['order_id']) ?></span><?php endif; ?></div>
<div class="form-group"><label for="change_type" class="form-label">Change Type <span class="required">*</span></label><select id="change_type" name="change_type" class="form-control <?= isset($errors['change_type']) ? 'has-error' : '' ?>" required><option value="">Select change type</option><?php foreach ($changeTypes as $type): ?><option value="<?= e($type) ?>" <?= ($old['change_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select><?php if (isset($errors['change_type'])): ?><span class="form-error"><?= e($errors['change_type']) ?></span><?php endif; ?></div>
<div class="form-group"><label for="current_value" class="form-label">Current Value <span class="required">*</span></label><input type="text" id="current_value" name="current_value" maxlength="500" class="form-control <?= isset($errors['current_value']) ? 'has-error' : '' ?>" value="<?= e($old['current_value'] ?? '') ?>" required placeholder="Value currently recorded on the order"></div>
<div class="form-group"><label for="requested_value" class="form-label">Requested New Value <span class="required">*</span></label><input type="text" id="requested_value" name="requested_value" maxlength="500" class="form-control <?= isset($errors['requested_value']) ? 'has-error' : '' ?>" value="<?= e($old['requested_value'] ?? '') ?>" required placeholder="Requested replacement value"></div>
<div class="form-group"><label for="reason_details" class="form-label">Reason / Details <span class="required">*</span></label><textarea id="reason_details" name="reason_details" rows="5" maxlength="5000" class="form-control <?= isset($errors['reason_details']) ? 'has-error' : '' ?>" required><?= e($old['reason_details'] ?? '') ?></textarea></div>
<div class="form-group"><label for="affected_quantity" class="form-label">Affected Quantity <span class="text-muted">(optional)</span></label><input type="number" id="affected_quantity" name="affected_quantity" min="1" step="1" class="form-control <?= isset($errors['affected_quantity']) ? 'has-error' : '' ?>" value="<?= e($old['affected_quantity'] ?? '') ?>" placeholder="Cannot exceed the selected order quantity"><span class="form-text">Leave blank when the request does not affect a specific quantity.</span></div>
<?php foreach ($errors as $error): ?><span class="form-error"><?= e($error) ?></span><?php endforeach; ?><div class="form-actions"><button type="submit" class="btn btn-primary">Submit Request</button><a href="<?= e(url('modules/buyer_change_requests/index.php')) ?>" class="btn btn-secondary">Cancel</a></div></form></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
