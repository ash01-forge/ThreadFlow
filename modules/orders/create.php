<?php
/** ThreadFlow - Add Buyer Order Form. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);
$pageTitle = 'Add Buyer Order';
$currentNav = 'orders';
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
$buyers = [];
try {
    $stmt = $conn->prepare('SELECT buyer_id, buyer_name FROM buyers WHERE is_active = 1 ORDER BY buyer_name ASC');
    if ($stmt) {
        $stmt->execute();
        $buyers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log('Failed to prepare active buyers query: ' . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database exception loading active buyers: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while loading active buyers.');
}
$statuses = ['Pending', 'Confirmed', 'In Production', 'Completed', 'Cancelled'];
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.orders-form { --orders-ink: #3c1b25; --orders-muted: #806b72; --orders-line: #efd9df; --orders-soft: #fff7f8; }
.orders-form .orders-form-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 24px; }
.orders-form .orders-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.orders-form .orders-form-card { max-width: 980px; overflow: hidden; border: 1px solid var(--orders-line); border-radius: 14px; background: #fff; }
.orders-form .orders-section { padding: 24px; border-bottom: 1px solid var(--orders-line); }
.orders-form .orders-section:last-of-type { border-bottom: 0; }
.orders-form .orders-section-head { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 20px; }
.orders-form .orders-step { display: grid; place-items: center; flex: 0 0 30px; width: 30px; height: 30px; border-radius: 50%; color: #fff; background: #78000f; font-weight: 700; }
.orders-form .orders-section h2 { margin: 0 0 4px; color: var(--orders-ink); font-size: 1.1rem; }
.orders-form .orders-section-head p { margin: 0; color: var(--orders-muted); font-size: .84rem; }
.orders-form .orders-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.orders-form .orders-fields .form-group { margin: 0; }
.orders-form .orders-item-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.orders-form #order-items { display: grid !important; gap: 12px !important; margin-top: 16px !important; }
.orders-form .order-item-row { display: grid !important; grid-template-columns: 1.35fr 1fr .8fr .8fr auto !important; gap: 12px !important; align-items: end !important; padding: 16px; border: 1px solid var(--orders-line); border-radius: 11px; background: #fffafd; }
.orders-form .order-item-row > div { min-width: 0; }
.orders-form .orders-total-preview { display: flex; justify-content: flex-end; gap: 8px; align-items: baseline; margin-top: 14px; color: var(--orders-muted); font-size: .85rem; }
.orders-form .orders-total-preview strong { color: #78000f; font-size: 1.15rem; }
.orders-form .orders-date-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.orders-form .orders-status-options { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 9px; }
.orders-form .orders-status-option { position: relative; display: block; cursor: pointer; }
.orders-form .orders-status-option input { position: absolute; opacity: 0; }
.orders-form .orders-status-card { display: block; min-height: 66px; padding: 12px 10px; border: 2px solid var(--orders-line); border-radius: 10px; text-align: center; color: var(--orders-muted); font-size: .8rem; }
.orders-form .orders-status-option input:checked + .orders-status-card { border-color: #78000f; color: #78000f; background: #fff1f4; box-shadow: 0 0 0 3px rgba(120, 0, 15, .1); font-weight: 700; }
.orders-form .orders-status-option input:focus-visible + .orders-status-card { outline: 3px solid #d58a99; outline-offset: 2px; }
.orders-form .orders-status-option[data-status="Completed"] input:checked + .orders-status-card { border-color: #25805c; color: #1c704e; background: #f2fbf6; }
.orders-form .orders-status-option[data-status="Cancelled"] input:checked + .orders-status-card { border-color: #b43b4c; color: #a52e42; background: #fff4f5; }
.orders-form .orders-submit { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 24px; background: var(--orders-soft); }
.orders-form .orders-submit p { margin: 0; color: var(--orders-muted); font-size: .82rem; }
.orders-form .orders-submit .form-actions { margin: 0; }
@media (max-width: 800px) { .orders-form .orders-status-options { grid-template-columns: repeat(3, minmax(0, 1fr)); } .orders-form .order-item-row { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; } .orders-form .order-item-row .remove-order-item { grid-column: 1 / -1; } }
@media (max-width: 600px) { .orders-form .orders-form-head { display: block; } .orders-form .orders-form-head .page-actions { margin-top: 14px; } .orders-form .orders-fields, .orders-form .orders-date-grid { grid-template-columns: 1fr; } .orders-form .orders-status-options { grid-template-columns: repeat(2, minmax(0, 1fr)); } .orders-form .orders-submit { display: block; } .orders-form .orders-submit .form-actions { margin-top: 14px; } .orders-form .orders-submit .btn { width: 100%; margin-bottom: 8px; } }
</style>
<div class="orders-form"><div class="orders-form-head"><div><div class="orders-kicker">New order workflow</div><div class="page-title-group"><h1>Add Buyer Order</h1><div class="page-subtitle">Capture buyer identity, item quantities, schedule, and operational status.</div></div></div><div class="page-actions"><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Back to Order List</a></div></div>
<div class="orders-form-card"><form action="<?= e(url('modules/orders/store.php')) ?>" method="post" novalidate><?= csrf_field() ?><section class="orders-section"><div class="orders-section-head"><span class="orders-step">1</span><div><h2>Buyer and Order Identity</h2><p>Set the reference number and active buyer for this order.</p></div></div><div class="orders-fields"><div class="form-group"><label for="order_number" class="form-label">Order Number <span class="required">*</span></label><input type="text" id="order_number" name="order_number" maxlength="50" required class="form-control <?= isset($errors['order_number']) ? 'has-error' : '' ?>" value="<?= e($old['order_number'] ?? '') ?>" placeholder="e.g., ORD-2026-004"><?php if (isset($errors['order_number'])): ?><span class="form-error"><?= e($errors['order_number']) ?></span><?php endif; ?></div><div class="form-group"><label for="buyer_id" class="form-label">Active Buyer <span class="required">*</span></label><select id="buyer_id" name="buyer_id" required class="form-control <?= isset($errors['buyer_id']) ? 'has-error' : '' ?>"><option value="">-- Select Active Buyer --</option><?php foreach ($buyers as $buyer): ?><option value="<?= (int) $buyer['buyer_id'] ?>" <?= (string)($old['buyer_id'] ?? '') === (string)$buyer['buyer_id'] ? 'selected' : '' ?>><?= e($buyer['buyer_name']) ?></option><?php endforeach; ?></select><?php if (isset($errors['buyer_id'])): ?><span class="form-error"><?= e($errors['buyer_id']) ?></span><?php endif; ?></div></div></section>
<?php $items = isset($old['items']) && is_array($old['items']) && $old['items'] ? array_values($old['items']) : [['style_number' => '', 'color' => '', 'size' => '', 'quantity' => '']]; ?><section class="orders-section"><div class="orders-section-head"><span class="orders-step">2</span><div><h2>Order Items</h2><p>Add each style, variant, and quantity included in this buyer order.</p></div></div><div class="orders-item-heading"><span class="form-label">Item lines <span class="required">*</span></span><button type="button" class="btn btn-secondary" id="add-order-item">+ Add Item</button></div><?php if (isset($errors['items'])): ?><span class="form-error"><?= e($errors['items']) ?></span><?php endif; ?><div id="order-items"><?php foreach ($items as $index => $item): ?><div class="order-item-row" data-item-row style="display:grid; grid-template-columns: 1.4fr 1fr 0.8fr 0.8fr auto; gap:0.75rem; align-items:end;"><div><label class="form-label" for="item_style_<?= (int) $index ?>">Style Number <span class="required">*</span></label><input type="text" id="item_style_<?= (int) $index ?>" name="items[<?= (int) $index ?>][style_number]" maxlength="50" required class="form-control" value="<?= e($item['style_number'] ?? '') ?>"></div><div><label class="form-label" for="item_color_<?= (int) $index ?>">Color</label><input type="text" id="item_color_<?= (int) $index ?>" name="items[<?= (int) $index ?>][color]" maxlength="100" class="form-control" value="<?= e($item['color'] ?? '') ?>"></div><div><label class="form-label" for="item_size_<?= (int) $index ?>">Size</label><input type="text" id="item_size_<?= (int) $index ?>" name="items[<?= (int) $index ?>][size]" maxlength="50" class="form-control" value="<?= e($item['size'] ?? '') ?>"></div><div><label class="form-label" for="item_quantity_<?= (int) $index ?>">Quantity <span class="required">*</span></label><input type="number" id="item_quantity_<?= (int) $index ?>" name="items[<?= (int) $index ?>][quantity]" min="1" step="1" required class="form-control item-quantity" value="<?= e($item['quantity'] ?? '') ?>"></div><button type="button" class="btn btn-secondary remove-order-item" aria-label="Remove item" <?= count($items) === 1 ? 'disabled' : '' ?>>Remove</button></div><?php endforeach; ?></div><div class="orders-total-preview" aria-live="polite">Total Item Quantity: <strong id="total-item-quantity">0 pcs</strong></div></section>
<section class="orders-section"><div class="orders-section-head"><span class="orders-step">3</span><div><h2>Schedule</h2><p>Set the planned order and delivery dates.</p></div></div><div class="orders-date-grid"><div class="form-group"><label for="order_date" class="form-label">Order Date</label><input type="date" id="order_date" name="order_date" class="form-control <?= isset($errors['order_date']) ? 'has-error' : '' ?>" value="<?= e($old['order_date'] ?? '') ?>"><?php if (isset($errors['order_date'])): ?><span class="form-error"><?= e($errors['order_date']) ?></span><?php endif; ?></div><div class="form-group"><label for="delivery_date" class="form-label">Delivery Date</label><input type="date" id="delivery_date" name="delivery_date" class="form-control <?= isset($errors['delivery_date']) ? 'has-error' : '' ?>" value="<?= e($old['delivery_date'] ?? '') ?>"><?php if (isset($errors['delivery_date'])): ?><span class="form-error"><?= e($errors['delivery_date']) ?></span><?php endif; ?></div></div></section>
<section class="orders-section"><div class="orders-section-head"><span class="orders-step">4</span><div><h2>Order Status</h2><p>Choose the current operational state for this order.</p></div></div><fieldset class="form-group" style="border:0; padding:0; margin:0;"><legend class="form-label">Status <span class="required">*</span></legend><div class="orders-status-options <?= isset($errors['status']) ? 'has-error' : '' ?>"><?php foreach ($statuses as $status): ?><label class="orders-status-option" data-status="<?= e($status) ?>"><input type="radio" id="status_<?= e(strtolower(str_replace(' ', '_', $status))) ?>" name="status" value="<?= e($status) ?>" required <?= ($old['status'] ?? 'Pending') === $status ? 'checked' : '' ?>><span class="orders-status-card"><?= e($status) ?></span></label><?php endforeach; ?></div></fieldset><?php if (isset($errors['status'])): ?><span class="form-error"><?= e($errors['status']) ?></span><?php endif; ?></section><div class="orders-submit"><div><strong>Ready to save?</strong><p>Order items and status will be submitted with this form.</p></div><div class="form-actions"><button type="submit" class="btn btn-primary">Save Order</button><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Cancel</a></div></div></form></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('order-items');
    var addButton = document.getElementById('add-order-item');
    var totalQuantity = document.getElementById('total-item-quantity');
    var nextIndex = container.querySelectorAll('[data-item-row]').length;
    function updateTotalQuantity() {
        var total = 0;
        container.querySelectorAll('.item-quantity').forEach(function (input) { total += parseInt(input.value, 10) || 0; });
        totalQuantity.textContent = total.toLocaleString() + ' pcs';
    }
    function updateRemoveButtons() {
        var rows = container.querySelectorAll('[data-item-row]');
        rows.forEach(function (row) { row.querySelector('.remove-order-item').disabled = rows.length === 1; });
    }
    addButton.addEventListener('click', function () {
        var index = nextIndex++;
        var row = document.createElement('div');
        row.className = 'order-item-row';
        row.setAttribute('data-item-row', '');
        row.style.cssText = 'display:grid; grid-template-columns:1.4fr 1fr 0.8fr 0.8fr auto; gap:0.75rem; align-items:end;';
        row.innerHTML = '<div><label class="form-label" for="item_style_' + index + '">Style Number <span class="required">*</span></label><input type="text" id="item_style_' + index + '" name="items[' + index + '][style_number]" maxlength="50" required class="form-control"></div><div><label class="form-label" for="item_color_' + index + '">Color</label><input type="text" id="item_color_' + index + '" name="items[' + index + '][color]" maxlength="100" class="form-control"></div><div><label class="form-label" for="item_size_' + index + '">Size</label><input type="text" id="item_size_' + index + '" name="items[' + index + '][size]" maxlength="50" class="form-control"></div><div><label class="form-label" for="item_quantity_' + index + '">Quantity <span class="required">*</span></label><input type="number" id="item_quantity_' + index + '" name="items[' + index + '][quantity]" min="1" step="1" required class="form-control item-quantity"></div><button type="button" class="btn btn-secondary remove-order-item" aria-label="Remove item">Remove</button>';
        container.appendChild(row);
        updateRemoveButtons();
        updateTotalQuantity();
    });
    container.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-order-item')) { event.target.closest('[data-item-row]').remove(); updateRemoveButtons(); updateTotalQuantity(); }
    });
    container.addEventListener('input', function (event) { if (event.target.classList.contains('item-quantity')) { updateTotalQuantity(); } });
    updateTotalQuantity();
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>