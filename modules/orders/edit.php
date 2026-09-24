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
    $itemStmt = $conn->prepare('SELECT item_id, style_number, color, size, quantity FROM order_items WHERE order_id = ? ORDER BY item_id ASC');
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();
    if (!$items) {
        $items = [['style_number' => $order['style_number'], 'color' => '', 'size' => '', 'quantity' => $order['total_quantity']]];
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database exception loading buyer order edit form: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while loading the order.');
    redirect('modules/orders/index.php');
}
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
$items = isset($old['items']) && is_array($old['items']) && $old['items'] ? array_values($old['items']) : $items;
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
    <div class="form-group"><div style="display:flex; justify-content:space-between; align-items:center; gap:1rem;"><label class="form-label">Order Items <span class="required">*</span></label><button type="button" class="btn btn-secondary" id="add-order-item">Add Item</button></div><?php if (isset($errors['items'])): ?><span class="form-error"><?= e($errors['items']) ?></span><?php endif; ?><div id="order-items" style="display:grid; gap:1rem; margin-top:1rem;"><?php foreach ($items as $index => $item): ?><div class="order-item-row" data-item-row style="display:grid; grid-template-columns: 1.4fr 1fr 0.8fr 0.8fr auto; gap:0.75rem; align-items:end;"><div><label class="form-label" for="item_style_<?= (int) $index ?>">Style Number <span class="required">*</span></label><input type="text" id="item_style_<?= (int) $index ?>" name="items[<?= (int) $index ?>][style_number]" maxlength="50" required class="form-control" value="<?= e($item['style_number'] ?? '') ?>"></div><div><label class="form-label" for="item_color_<?= (int) $index ?>">Color</label><input type="text" id="item_color_<?= (int) $index ?>" name="items[<?= (int) $index ?>][color]" maxlength="100" class="form-control" value="<?= e($item['color'] ?? '') ?>"></div><div><label class="form-label" for="item_size_<?= (int) $index ?>">Size</label><input type="text" id="item_size_<?= (int) $index ?>" name="items[<?= (int) $index ?>][size]" maxlength="50" class="form-control" value="<?= e($item['size'] ?? '') ?>"></div><div><label class="form-label" for="item_quantity_<?= (int) $index ?>">Quantity <span class="required">*</span></label><input type="number" id="item_quantity_<?= (int) $index ?>" name="items[<?= (int) $index ?>][quantity]" min="1" step="1" required class="form-control" value="<?= e($item['quantity'] ?? '') ?>"></div><button type="button" class="btn btn-secondary remove-order-item" aria-label="Remove item" <?= count($items) === 1 ? 'disabled' : '' ?>>Remove</button></div><?php endforeach; ?></div></div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;"><div class="form-group"><label for="order_date" class="form-label">Order Date</label><input type="date" id="order_date" name="order_date" class="form-control <?= isset($errors['order_date']) ? 'has-error' : '' ?>" value="<?= $value('order_date') ?>"><?php if (isset($errors['order_date'])): ?><span class="form-error"><?= e($errors['order_date']) ?></span><?php endif; ?></div><div class="form-group"><label for="delivery_date" class="form-label">Delivery Date</label><input type="date" id="delivery_date" name="delivery_date" class="form-control <?= isset($errors['delivery_date']) ? 'has-error' : '' ?>" value="<?= $value('delivery_date') ?>"><?php if (isset($errors['delivery_date'])): ?><span class="form-error"><?= e($errors['delivery_date']) ?></span><?php endif; ?></div></div>
    <div class="form-group"><label for="status" class="form-label">Order Status <span class="required">*</span></label><select id="status" name="status" required class="form-control <?= isset($errors['status']) ? 'has-error' : '' ?>"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= ($old['status'] ?? $order['status']) === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><?php if (isset($errors['status'])): ?><span class="form-error"><?= e($errors['status']) ?></span><?php endif; ?></div>
    <div class="form-actions"><button type="submit" class="btn btn-primary">Update Order</button><a href="<?= e(url('modules/orders/view.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-secondary">Cancel</a></div>
</form></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('order-items'); var addButton = document.getElementById('add-order-item'); var nextIndex = container.querySelectorAll('[data-item-row]').length;
    function updateRemoveButtons() { var rows = container.querySelectorAll('[data-item-row]'); rows.forEach(function (row) { row.querySelector('.remove-order-item').disabled = rows.length === 1; }); }
    addButton.addEventListener('click', function () { var index = nextIndex++; var row = document.createElement('div'); row.className = 'order-item-row'; row.setAttribute('data-item-row', ''); row.style.cssText = 'display:grid; grid-template-columns:1.4fr 1fr 0.8fr 0.8fr auto; gap:0.75rem; align-items:end;'; row.innerHTML = '<div><label class="form-label" for="item_style_' + index + '">Style Number <span class="required">*</span></label><input type="text" id="item_style_' + index + '" name="items[' + index + '][style_number]" maxlength="50" required class="form-control"></div><div><label class="form-label" for="item_color_' + index + '">Color</label><input type="text" id="item_color_' + index + '" name="items[' + index + '][color]" maxlength="100" class="form-control"></div><div><label class="form-label" for="item_size_' + index + '">Size</label><input type="text" id="item_size_' + index + '" name="items[' + index + '][size]" maxlength="50" class="form-control"></div><div><label class="form-label" for="item_quantity_' + index + '">Quantity <span class="required">*</span></label><input type="number" id="item_quantity_' + index + '" name="items[' + index + '][quantity]" min="1" step="1" required class="form-control"></div><button type="button" class="btn btn-secondary remove-order-item" aria-label="Remove item">Remove</button>'; container.appendChild(row); updateRemoveButtons(); });
    container.addEventListener('click', function (event) { if (event.target.classList.contains('remove-order-item')) { event.target.closest('[data-item-row]').remove(); updateRemoveButtons(); } });
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
