<?php
/** ThreadFlow - Edit Buyer Order Form. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId <= 0) {
    flash_set('error', 'Invalid order ID specified.');
    redirect('modules/orders/index.php');
}
try {
    $stmt = $conn->prepare('SELECT order_id, buyer_id, order_number, style_number, total_quantity, order_date, delivery_date, status FROM buyer_orders WHERE order_id = ? LIMIT 1');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        flash_set('error', 'Order record not found.');
        redirect('modules/orders/index.php');
    }
    $buyerStmt = $conn->prepare('SELECT buyer_id, buyer_name FROM buyers WHERE is_active = 1 OR buyer_id = ? ORDER BY buyer_name ASC');
    $buyerStmt->bind_param('i', $order['buyer_id']);
    $buyerStmt->execute();
    $buyers = $buyerStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $buyerStmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Database exception loading buyer order edit form: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while loading the order.');
    redirect('modules/orders/index.php');
}
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
$value = static function (string $field) use ($old, $order): string {
    return e(isset($old[$field]) ? $old[$field] : ($order[$field] ?? ''));
};
$statuses = ['Pending', 'Confirmed', 'In Production', 'Completed', 'Cancelled'];
$pageTitle = 'Edit Order: ' . $order['order_number'];
$currentNav = 'orders';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="page-header"><div class="page-title-group"><h1>Edit Buyer Order</h1><div class="page-subtitle">Update Order #<?= (int) $order['order_id'] ?> &bull; <?= e($order['order_number']) ?></div></div><div class="page-actions"><a href="<?= e(url('modules/orders/view.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-secondary">View Order</a><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Back to List</a></div></div>
<div class="card card-form"><div class="card-header"><h2>Order Information</h2></div><div class="card-body"><form action="<?= e(url('modules/orders/update.php')) ?>" method="post" novalidate><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
    <div class="form-group"><label for="order_number" class="form-label">Order Number <span class="required">*</span></label><input type="text" id="order_number" name="order_number" maxlength="50" required class="form-control <?= isset($errors['order_number']) ? 'has-error' : '' ?>" value="<?= $value('order_number') ?>"><?php if (isset($errors['order_number'])): ?><span class="form-error"><?= e($errors['order_number']) ?></span><?php endif; ?></div>
    <div class="form-group"><label for="buyer_id" class="form-label">Buyer <span class="required">*</span></label><select id="buyer_id" name="buyer_id" required class="form-control <?= isset($errors['buyer_id']) ? 'has-error' : '' ?>"><?php foreach ($buyers as $buyer): ?><option value="<?= (int) $buyer['buyer_id'] ?>" <?= (string)($old['buyer_id'] ?? $order['buyer_id']) === (string)$buyer['buyer_id'] ? 'selected' : '' ?>><?= e($buyer['buyer_name']) ?></option><?php endforeach; ?></select><?php if (isset($errors['buyer_id'])): ?><span class="form-error"><?= e($errors['buyer_id']) ?></span><?php endif; ?></div>
    <div class="form-group"><label for="style_number" class="form-label">Style Number <span class="required">*</span></label><input type="text" id="style_number" name="style_number" maxlength="50" required class="form-control <?= isset($errors['style_number']) ? 'has-error' : '' ?>" value="<?= $value('style_number') ?>"><?php if (isset($errors['style_number'])): ?><span class="form-error"><?= e($errors['style_number']) ?></span><?php endif; ?></div>
    <div class="form-group"><label for="total_quantity" class="form-label">Total Quantity <span class="required">*</span></label><input type="number" id="total_quantity" name="total_quantity" min="1" step="1" required class="form-control <?= isset($errors['total_quantity']) ? 'has-error' : '' ?>" value="<?= $value('total_quantity') ?>"><?php if (isset($errors['total_quantity'])): ?><span class="form-error"><?= e($errors['total_quantity']) ?></span><?php endif; ?></div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;"><div class="form-group"><label for="order_date" class="form-label">Order Date</label><input type="date" id="order_date" name="order_date" class="form-control <?= isset($errors['order_date']) ? 'has-error' : '' ?>" value="<?= $value('order_date') ?>"><?php if (isset($errors['order_date'])): ?><span class="form-error"><?= e($errors['order_date']) ?></span><?php endif; ?></div><div class="form-group"><label for="delivery_date" class="form-label">Delivery Date</label><input type="date" id="delivery_date" name="delivery_date" class="form-control <?= isset($errors['delivery_date']) ? 'has-error' : '' ?>" value="<?= $value('delivery_date') ?>"><?php if (isset($errors['delivery_date'])): ?><span class="form-error"><?= e($errors['delivery_date']) ?></span><?php endif; ?></div></div>
    <div class="form-group"><label for="status" class="form-label">Order Status <span class="required">*</span></label><select id="status" name="status" required class="form-control <?= isset($errors['status']) ? 'has-error' : '' ?>"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= ($old['status'] ?? $order['status']) === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><?php if (isset($errors['status'])): ?><span class="form-error"><?= e($errors['status']) ?></span><?php endif; ?></div>
    <div class="form-actions"><button type="submit" class="btn btn-primary">Update Order</button><a href="<?= e(url('modules/orders/view.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-secondary">Cancel</a></div>
</form></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
