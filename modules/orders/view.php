<?php
/** ThreadFlow - Buyer Order Details. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId <= 0) {
    flash_set('error', 'Invalid order ID specified.');
    redirect('modules/orders/index.php');
}
try {
    $stmt = $conn->prepare('SELECT bo.order_id, bo.order_number, bo.style_number, bo.total_quantity, bo.order_date, bo.delivery_date, bo.status, bo.created_at, bo.updated_at, b.buyer_id, b.buyer_name FROM buyer_orders bo INNER JOIN buyers b ON b.buyer_id = bo.buyer_id WHERE bo.order_id = ? LIMIT 1');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        flash_set('error', 'Order record not found.');
        redirect('modules/orders/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database exception loading buyer order details: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while loading the order.');
    redirect('modules/orders/index.php');
}
$pageTitle = 'Order: ' . $order['order_number'];
$currentNav = 'orders';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="page-header"><div class="page-title-group"><h1>Order Details</h1><div class="page-subtitle">Order #<?= (int) $order['order_id'] ?> &bull; <?= e($order['order_number']) ?></div></div><div class="page-actions"><a href="<?= e(url('modules/orders/edit.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-primary">Edit Order</a><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Back to List</a></div></div>
<div class="card"><div class="card-header"><h2><?= e($order['order_number']) ?></h2><span class="badge badge-muted"><?= e($order['status']) ?></span></div><div class="card-body"><div class="details-grid">
    <div><span class="form-label">Buyer</span><p><a href="<?= e(url('modules/buyers/view.php?id=' . (int) $order['buyer_id'])) ?>"><?= e($order['buyer_name']) ?></a></p></div>
    <div><span class="form-label">Style Number</span><p><?= e($order['style_number']) ?></p></div>
    <div><span class="form-label">Total Quantity</span><p><?= number_format((int) $order['total_quantity']) ?> pcs</p></div>
    <div><span class="form-label">Order Status</span><p><?= e($order['status']) ?></p></div>
    <div><span class="form-label">Order Date</span><p><?= e($order['order_date'] ?: 'Not specified') ?></p></div>
    <div><span class="form-label">Delivery Date</span><p><?= e($order['delivery_date'] ?: 'Not specified') ?></p></div>
    <div><span class="form-label">Created</span><p><?= e($order['created_at']) ?></p></div>
    <div><span class="form-label">Last Updated</span><p><?= e($order['updated_at']) ?></p></div>
</div></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
