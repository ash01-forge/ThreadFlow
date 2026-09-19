<?php
/** ThreadFlow - Store Buyer Change Request. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
require_csrf();

$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
$changeType = trim($_POST['change_type'] ?? '');
$currentValue = trim($_POST['current_value'] ?? '');
$requestedValue = trim($_POST['requested_value'] ?? '');
$reasonDetails = trim($_POST['reason_details'] ?? '');
$affectedInput = trim((string) ($_POST['affected_quantity'] ?? ''));
$allowedTypes = ['Quantity', 'Style', 'Delivery Date', 'Color', 'Other'];
$errors = [];
if (!$orderId || $orderId <= 0) { $errors['order_id'] = 'Please select a valid buyer order.'; }
if (!in_array($changeType, $allowedTypes, true)) { $errors['change_type'] = 'Please select a valid change type.'; }
if ($currentValue === '') { $errors['current_value'] = 'Current Value is required.'; } elseif (mb_strlen($currentValue) > 500) { $errors['current_value'] = 'Current Value cannot exceed 500 characters.'; }
if ($requestedValue === '') { $errors['requested_value'] = 'Requested New Value is required.'; } elseif (mb_strlen($requestedValue) > 500) { $errors['requested_value'] = 'Requested New Value cannot exceed 500 characters.'; }
if ($reasonDetails === '') { $errors['reason_details'] = 'Reason / Details is required.'; } elseif (mb_strlen($reasonDetails) > 5000) { $errors['reason_details'] = 'Reason / Details cannot exceed 5000 characters.'; }
$affectedQuantity = null;
if ($affectedInput !== '') {
    $affectedQuantity = filter_var($affectedInput, FILTER_VALIDATE_INT);
    if ($affectedQuantity === false || $affectedQuantity <= 0) { $errors['affected_quantity'] = 'Affected Quantity must be a whole number greater than zero.'; }
}

$order = null;
if ($orderId && $orderId > 0) {
    try {
        $stmt = $conn->prepare('SELECT bo.order_id, bo.order_number, bo.total_quantity, bo.status, COUNT(pb.batch_id) AS batch_count, MAX(CASE WHEN pb.status = \'Completed\' THEN 1 ELSE 0 END) AS has_completed_batch, MAX(CASE WHEN pb.status = \'In Progress\' THEN 1 ELSE 0 END) AS has_in_progress_batch, MAX(CASE WHEN pb.status = \'Planned\' THEN 1 ELSE 0 END) AS has_planned_batch FROM buyer_orders bo LEFT JOIN production_batches pb ON pb.order_id = bo.order_id WHERE bo.order_id = ? GROUP BY bo.order_id, bo.order_number, bo.total_quantity, bo.status LIMIT 1');
        if (!$stmt) { throw new mysqli_sql_exception('Order impact query could not be prepared.'); }
        $stmt->bind_param('i', $orderId); $stmt->execute(); $order = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$order) { $errors['order_id'] = 'The selected buyer order was not found.'; }
        elseif ($affectedQuantity !== null && $affectedQuantity > (int) $order['total_quantity']) { $errors['affected_quantity'] = 'Affected Quantity cannot exceed the order quantity of ' . number_format((int) $order['total_quantity']) . '.'; }
    } catch (mysqli_sql_exception $e) { error_log('Database exception calculating buyer change impact: ' . $e->getMessage()); flash_set('error', 'A database error occurred while checking the selected order.'); $_SESSION['_form_old'] = $_POST; redirect('modules/buyer_change_requests/create.php'); }
}
if ($errors) { $_SESSION['_form_errors'] = $errors; $_SESSION['_form_old'] = $_POST; flash_set('error', 'Please resolve the validation errors below.'); redirect('modules/buyer_change_requests/create.php'); }

if ((int) $order['has_completed_batch'] === 1 || $order['status'] === 'Completed') {
    $impact = 'Critical'; $recommendation = 'Escalate to Admin and production leadership; confirm completion impact before deciding and avoid changing completed work without a controlled resolution.';
} elseif ((int) $order['has_in_progress_batch'] === 1 || $order['status'] === 'In Production') {
    $impact = 'High'; $recommendation = 'Review immediately with production leadership; assess in-progress work, materials, and rework before deciding.';
} elseif ((int) $order['has_planned_batch'] === 1) {
    $impact = 'Medium'; $recommendation = 'Review planned batch allocations and update the production plan if approved; production has not started.';
} else {
    $impact = 'Low'; $recommendation = 'Review and apply through the normal order-change process before production planning begins.';
}
$user = auth_user();
try {
    $stmt = $conn->prepare('INSERT INTO buyer_change_requests (order_id, requester_id, change_type, current_value, requested_value, reason_details, affected_quantity, impact_level, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) { throw new mysqli_sql_exception('Buyer change request insert could not be prepared.'); }
    $requesterId = (int) $user['user_id'];
    $stmt->bind_param('iissssiss', $orderId, $requesterId, $changeType, $currentValue, $requestedValue, $reasonDetails, $affectedQuantity, $impact, $recommendation);
    $stmt->execute(); $requestId = $stmt->insert_id; $stmt->close();
    flash_set('success', 'Buyer change request submitted with ' . $impact . ' impact.');
    redirect('modules/buyer_change_requests/view.php?id=' . (int) $requestId);
} catch (mysqli_sql_exception $e) { error_log('Database exception creating buyer change request: ' . $e->getMessage()); $_SESSION['_form_old'] = $_POST; flash_set('error', 'A database error occurred while saving the request.'); redirect('modules/buyer_change_requests/create.php'); }
