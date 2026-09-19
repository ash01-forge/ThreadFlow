<?php
/** ThreadFlow - Update Buyer Order Handler. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
require_csrf();
$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
if (!$orderId || $orderId <= 0) {
    flash_set('error', 'Invalid order ID specified.');
    redirect('modules/orders/index.php');
}

$orderNumber = trim($_POST['order_number'] ?? '');
$buyerId = filter_var($_POST['buyer_id'] ?? null, FILTER_VALIDATE_INT);
$styleNumber = trim($_POST['style_number'] ?? '');
$quantityInput = trim((string) ($_POST['total_quantity'] ?? ''));
$orderDate = trim($_POST['order_date'] ?? '');
$deliveryDate = trim($_POST['delivery_date'] ?? '');
$status = trim($_POST['status'] ?? '');
$statuses = ['Pending', 'Confirmed', 'In Production', 'Completed', 'Cancelled'];
$errors = [];

try {
    $existsStmt = $conn->prepare('SELECT order_id FROM buyer_orders WHERE order_id = ? LIMIT 1');
    $existsStmt->bind_param('i', $orderId);
    $existsStmt->execute();
    $exists = (bool) $existsStmt->get_result()->fetch_assoc();
    $existsStmt->close();
    if (!$exists) {
        flash_set('error', 'Order record not found.');
        redirect('modules/orders/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database exception verifying order: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving the order.');
    redirect('modules/orders/index.php');
}

if ($orderNumber === '') {
    $errors['order_number'] = 'Order Number is required.';
} elseif (mb_strlen($orderNumber) > 50) {
    $errors['order_number'] = 'Order Number cannot exceed 50 characters.';
}
if (!$buyerId || $buyerId <= 0) {
    $errors['buyer_id'] = 'Please select an active buyer.';
}
if ($styleNumber === '') {
    $errors['style_number'] = 'Style Number is required.';
} elseif (mb_strlen($styleNumber) > 50) {
    $errors['style_number'] = 'Style Number cannot exceed 50 characters.';
}
if ($quantityInput === '' || filter_var($quantityInput, FILTER_VALIDATE_INT) === false || (int) $quantityInput <= 0) {
    $errors['total_quantity'] = 'Quantity must be greater than zero.';
}
$validDate = static function (string $value): bool {
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if ($orderDate !== '' && !$validDate($orderDate)) {
    $errors['order_date'] = 'Order Date must be a valid date.';
}
if ($deliveryDate !== '' && !$validDate($deliveryDate)) {
    $errors['delivery_date'] = 'Delivery Date must be a valid date.';
}
if ($orderDate !== '' && $deliveryDate !== '' && $validDate($orderDate) && $validDate($deliveryDate) && $deliveryDate < $orderDate) {
    $errors['delivery_date'] = 'Delivery Date cannot be earlier than Order Date.';
}
if (!in_array($status, $statuses, true)) {
    $errors['status'] = 'Please select a valid order status.';
}

try {
    if ($buyerId && $buyerId > 0) {
        $buyerStmt = $conn->prepare('SELECT buyer_id FROM buyers WHERE buyer_id = ? AND is_active = 1 LIMIT 1');
        $buyerStmt->bind_param('i', $buyerId);
        $buyerStmt->execute();
        if (!$buyerStmt->get_result()->fetch_assoc()) {
            $errors['buyer_id'] = 'Please select an active buyer.';
        }
        $buyerStmt->close();
    }
    if ($orderNumber !== '') {
        $numberStmt = $conn->prepare('SELECT order_id FROM buyer_orders WHERE order_number = ? AND order_id <> ? LIMIT 1');
        $numberStmt->bind_param('si', $orderNumber, $orderId);
        $numberStmt->execute();
        if ($numberStmt->get_result()->fetch_assoc()) {
            $errors['order_number'] = 'Order Number already exists.';
        }
        $numberStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database exception validating updated buyer order: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while validating the order.');
    redirect('modules/orders/edit.php?id=' . (int) $orderId);
}

if ($errors) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/orders/edit.php?id=' . (int) $orderId);
}

$quantity = (int) $quantityInput;
$orderDateParam = $orderDate !== '' ? $orderDate : null;
$deliveryDateParam = $deliveryDate !== '' ? $deliveryDate : null;
try {
    $stmt = $conn->prepare('UPDATE buyer_orders SET buyer_id = ?, order_number = ?, style_number = ?, total_quantity = ?, order_date = ?, delivery_date = ?, status = ? WHERE order_id = ?');
    $stmt->bind_param('ississsi', $buyerId, $orderNumber, $styleNumber, $quantity, $orderDateParam, $deliveryDateParam, $status, $orderId);
    $stmt->execute();
    $stmt->close();
    flash_set('success', "Order '{$orderNumber}' updated successfully.");
    redirect('modules/orders/view.php?id=' . (int) $orderId);
} catch (mysqli_sql_exception $e) {
    error_log('Database exception updating buyer order: ' . $e->getMessage());
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', $e->getCode() === 1062 ? 'Order Number already exists.' : 'A database error occurred while updating the order.');
    redirect('modules/orders/edit.php?id=' . (int) $orderId);
}
