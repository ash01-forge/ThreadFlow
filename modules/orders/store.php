<?php
/** ThreadFlow - Store Buyer Order Handler. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
require_csrf();

$orderNumber = trim($_POST['order_number'] ?? '');
$buyerId = filter_var($_POST['buyer_id'] ?? null, FILTER_VALIDATE_INT);
$itemsInput = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
$orderDate = trim($_POST['order_date'] ?? '');
$deliveryDate = trim($_POST['delivery_date'] ?? '');
$status = trim($_POST['status'] ?? '');
$statuses = ['Pending', 'Confirmed', 'In Production', 'Completed', 'Cancelled'];
$errors = [];

if ($orderNumber === '') {
    $errors['order_number'] = 'Order Number is required.';
} elseif (mb_strlen($orderNumber) > 50) {
    $errors['order_number'] = 'Order Number cannot exceed 50 characters.';
}
if (!$buyerId || $buyerId <= 0) {
    $errors['buyer_id'] = 'Please select an active buyer.';
}
$items = [];
$totalQuantity = 0;
foreach ($itemsInput as $itemInput) {
    $item = is_array($itemInput) ? $itemInput : [];
    $style = trim((string) ($item['style_number'] ?? ''));
    $color = trim((string) ($item['color'] ?? ''));
    $size = trim((string) ($item['size'] ?? ''));
    $quantityInput = trim((string) ($item['quantity'] ?? ''));
    if ($style === '' || mb_strlen($style) > 50) { $errors['items'] = 'Every item needs a style number of 50 characters or fewer.'; }
    if (mb_strlen($color) > 100 || mb_strlen($size) > 50) { $errors['items'] = 'Color or size is too long.'; }
    if ($quantityInput === '' || filter_var($quantityInput, FILTER_VALIDATE_INT) === false || (int) $quantityInput <= 0) { $errors['items'] = 'Every item quantity must be a positive integer.'; }
    $quantity = (int) $quantityInput;
    $totalQuantity += $quantity;
    $items[] = ['style_number' => $style, 'color' => $color, 'size' => $size, 'quantity' => $quantity];
}
if (!$items) { $errors['items'] = 'At least one order item is required.'; }
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

if ($buyerId && $buyerId > 0) {
    try {
        $buyerStmt = $conn->prepare('SELECT buyer_id FROM buyers WHERE buyer_id = ? AND is_active = 1 LIMIT 1');
        $buyerStmt->bind_param('i', $buyerId);
        $buyerStmt->execute();
        $buyerExists = (bool) $buyerStmt->get_result()->fetch_assoc();
        $buyerStmt->close();
        if (!$buyerExists) {
            $errors['buyer_id'] = 'Please select an active buyer.';
        }
    } catch (mysqli_sql_exception $e) {
        error_log('Database exception verifying order buyer: ' . $e->getMessage());
        flash_set('error', 'A database error occurred while validating the buyer.');
        redirect('modules/orders/create.php');
    }
}

if ($orderNumber !== '') {
    try {
        $numberStmt = $conn->prepare('SELECT order_id FROM buyer_orders WHERE order_number = ? LIMIT 1');
        $numberStmt->bind_param('s', $orderNumber);
        $numberStmt->execute();
        $numberExists = (bool) $numberStmt->get_result()->fetch_assoc();
        $numberStmt->close();
        if ($numberExists) {
            $errors['order_number'] = 'Order Number already exists.';
        }
    } catch (mysqli_sql_exception $e) {
        error_log('Database exception checking order number: ' . $e->getMessage());
        flash_set('error', 'A database error occurred while validating the order number.');
        redirect('modules/orders/create.php');
    }
}

if ($errors) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/orders/create.php');
}

$styleNumber = $items[0]['style_number'];
$orderDateParam = $orderDate !== '' ? $orderDate : null;
$deliveryDateParam = $deliveryDate !== '' ? $deliveryDate : null;
try {
    $conn->begin_transaction();
    $stmt = $conn->prepare('INSERT INTO buyer_orders (buyer_id, order_number, style_number, total_quantity, order_date, delivery_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ississs', $buyerId, $orderNumber, $styleNumber, $totalQuantity, $orderDateParam, $deliveryDateParam, $status);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, style_number, color, size, quantity) VALUES (?, ?, ?, ?, ?)');
    foreach ($items as $item) { $itemStmt->bind_param('isssi', $newId, $item['style_number'], $item['color'], $item['size'], $item['quantity']); $itemStmt->execute(); }
    $itemStmt->close();
    $conn->commit();
    flash_set('success', "Order '{$orderNumber}' created successfully.");
    redirect('modules/orders/view.php?id=' . (int) $newId);
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log('Database exception creating buyer order: ' . $e->getMessage());
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', $e->getCode() === 1062 ? 'Order Number already exists.' : 'A database error occurred while saving the order.');
    redirect('modules/orders/create.php');
}
