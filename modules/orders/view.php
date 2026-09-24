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
    $itemStmt = $conn->prepare('SELECT style_number, color, size, quantity FROM order_items WHERE order_id = ? ORDER BY item_id ASC');
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();
    if (!$items) {
        $items = [['style_number' => $order['style_number'], 'color' => '', 'size' => '', 'quantity' => $order['total_quantity']]];
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
<div class="card"><div class="card-header"><h2>Order Items</h2><span class="badge badge-muted">Total: <?= number_format((int) $order['total_quantity']) ?> pcs</span></div><div class="card-body"><div class="table-responsive"><table class="table" style="width:100%; min-width:620px;"><thead><tr><th scope="col" style="width:40%;">Style</th><th scope="col" style="width:25%;">Color</th><th scope="col" style="width:20%;">Size</th><th scope="col" style="width:15%; text-align:right;">Quantity</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= e($item['style_number']) ?></td><td><?= e($item['color'] ?: 'Not specified') ?></td><td><?= e($item['size'] ?: 'Not specified') ?></td><td style="text-align:right;"> <?= number_format((int) $item['quantity']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
