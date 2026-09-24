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
<style>
.orders-view { --orders-ink: #3c1b25; --orders-muted: #806b72; --orders-line: #efd9df; }
.orders-view .orders-hero { padding: 24px; margin-bottom: 20px; border-radius: 14px; color: #fff; background: linear-gradient(120deg, #78000f, #a83c51); }
.orders-view .orders-hero .page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 22px; margin: 0; }
.orders-view .orders-kicker { margin-bottom: 7px; color: #f9dfe4; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.orders-view .orders-hero h1 { margin-bottom: 6px; color: #fff; }
.orders-view .orders-hero .page-subtitle { color: #f9dfe4; }
.orders-view .orders-hero .page-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.orders-view .orders-hero .btn-primary { color: #78000f; background: #fff; border-color: #fff; box-shadow: 0 3px 10px rgba(48, 0, 10, .2); }
.orders-view .orders-hero .btn-primary:hover { color: #5c000c; background: #fff4f6; border-color: #fff4f6; }
.orders-view .orders-hero .btn-secondary { color: #fff; background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .75); }
.orders-view .orders-hero .btn-secondary:hover { color: #fff; background: rgba(255, 255, 255, .2); border-color: #fff; }
.orders-view .orders-hero-summary { display: grid; grid-template-columns: 1.4fr repeat(5, 1fr); gap: 8px; margin-top: 22px; }
.orders-view .orders-hero-stat { padding: 12px; border: 1px solid rgba(255, 255, 255, .25); border-radius: 9px; background: rgba(255, 255, 255, .1); }
.orders-view .orders-hero-stat small { display: block; color: #f9dfe4; font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; }
.orders-view .orders-hero-stat strong { display: block; margin-top: 4px; color: #fff; font-size: .9rem; overflow-wrap: anywhere; }
.orders-view .orders-panel { overflow: hidden; margin-bottom: 18px; border: 1px solid var(--orders-line); border-radius: 13px; background: #fff; }
.orders-view .orders-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 17px 20px; border-bottom: 1px solid var(--orders-line); }
.orders-view .orders-panel-head h2 { margin: 0; color: var(--orders-ink); font-size: 1.08rem; }
.orders-view .orders-panel-body { padding: 20px; }
.orders-view .orders-meta-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.orders-view .orders-meta-card { padding: 15px; border: 1px solid var(--orders-line); border-radius: 10px; background: #fffafd; }
.orders-view .orders-meta-card small { display: block; color: var(--orders-muted); font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.orders-view .orders-meta-card p { margin: 7px 0 0; color: var(--orders-ink); font-size: .9rem; overflow-wrap: anywhere; }
.orders-view .orders-items { display: grid; gap: 10px; }
.orders-view .orders-item { display: grid; grid-template-columns: 1.4fr 1fr 1fr .7fr; gap: 14px; align-items: center; padding: 15px 16px; border: 1px solid var(--orders-line); border-radius: 10px; }
.orders-view .orders-item div { min-width: 0; }
.orders-view .orders-item small { display: block; color: var(--orders-muted); font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; }
.orders-view .orders-item strong { display: block; margin-top: 4px; color: var(--orders-ink); overflow-wrap: anywhere; }
.orders-view .orders-item-quantity { text-align: right; }
@media (max-width: 900px) { .orders-view .orders-hero-summary { grid-template-columns: repeat(3, minmax(0, 1fr)); } .orders-view .orders-meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 600px) { .orders-view .orders-hero .page-header { display: block; } .orders-view .orders-hero .page-actions { margin-top: 16px; } .orders-view .orders-hero .page-actions .btn { width: 100%; margin-bottom: 7px; text-align: center; } .orders-view .orders-hero-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .orders-view .orders-meta-grid { grid-template-columns: 1fr; } .orders-view .orders-item { grid-template-columns: repeat(2, minmax(0, 1fr)); } .orders-view .orders-item-quantity { text-align: left; } }
</style>
<div class="orders-view"><div class="orders-hero"><div class="page-header"><div class="page-title-group"><div class="orders-kicker">Order dossier</div><h1><?= e($order['order_number']) ?></h1><div class="page-subtitle">Order ID #<?= (int) $order['order_id'] ?> &bull; Buyer order record</div></div><div class="page-actions"><a href="<?= e(url('modules/orders/edit.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-primary">Edit Order</a><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Back to List</a></div></div><div class="orders-hero-summary"><div class="orders-hero-stat"><small>Buyer</small><strong><?= e($order['buyer_name']) ?></strong></div><div class="orders-hero-stat"><small>Status</small><strong><?= e($order['status']) ?></strong></div><div class="orders-hero-stat"><small>Total Quantity</small><strong><?= number_format((int) $order['total_quantity']) ?> pcs</strong></div><div class="orders-hero-stat"><small>Order Date</small><strong><?= e($order['order_date'] ?: 'Not specified') ?></strong></div><div class="orders-hero-stat"><small>Delivery Date</small><strong><?= e($order['delivery_date'] ?: 'Not specified') ?></strong></div></div></div>
<div class="orders-panel"><div class="orders-panel-head"><h2>Order Summary</h2><span class="badge <?= $order['status'] === 'Completed' ? 'badge-success' : ($order['status'] === 'Cancelled' ? 'badge-danger' : 'badge-warning') ?>"><?= e($order['status']) ?></span></div><div class="orders-panel-body"><div class="orders-meta-grid"><div class="orders-meta-card"><small>Buyer</small><p><a href="<?= e(url('modules/buyers/view.php?id=' . (int) $order['buyer_id'])) ?>"><?= e($order['buyer_name']) ?></a></p></div><div class="orders-meta-card"><small>Style Number</small><p><?= e($order['style_number']) ?></p></div><div class="orders-meta-card"><small>Total Quantity</small><p><?= number_format((int) $order['total_quantity']) ?> pcs</p></div><div class="orders-meta-card"><small>Order Status</small><p><?= e($order['status']) ?></p></div><div class="orders-meta-card"><small>Order Date</small><p><?= e($order['order_date'] ?: 'Not specified') ?></p></div><div class="orders-meta-card"><small>Delivery Date</small><p><?= e($order['delivery_date'] ?: 'Not specified') ?></p></div><div class="orders-meta-card"><small>Created</small><p><?= e($order['created_at']) ?></p></div><div class="orders-meta-card"><small>Last Updated</small><p><?= e($order['updated_at']) ?></p></div></div></div></div>
<div class="orders-panel"><div class="orders-panel-head"><h2>Order Items</h2><span class="badge badge-muted">Total: <?= number_format((int) $order['total_quantity']) ?> pcs</span></div><div class="orders-panel-body"><div class="orders-items"><?php foreach ($items as $item): ?><div class="orders-item"><div><small>Style</small><strong><?= e($item['style_number']) ?></strong></div><div><small>Color</small><strong><?= e($item['color'] ?: 'Not specified') ?></strong></div><div><small>Size</small><strong><?= e($item['size'] ?: 'Not specified') ?></strong></div><div class="orders-item-quantity"><small>Quantity</small><strong><?= number_format((int) $item['quantity']) ?> pcs</strong></div></div><?php endforeach; ?></div></div></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
