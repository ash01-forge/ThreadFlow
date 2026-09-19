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
<div class="page-header"><div class="page-title-group"><h1>Add Buyer Order</h1><div class="page-subtitle">Module 1 &bull; Access: Admin, Merchandiser</div></div><div class="page-actions"><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Back to Order List</a></div></div>
<div class="card card-form"><div class="card-header"><h2>New Order Details</h2></div><div class="card-body">
<form action="<?= e(url('modules/orders/store.php')) ?>" method="post" novalidate><?= csrf_field() ?>
    <div class="form-group"><label for="order_number" class="form-label">Order Number <span class="required">*</span></label><input type="text" id="order_number" name="order_number" maxlength="50" required class="form-control <?= isset($errors['order_number']) ? 'has-error' : '' ?>" value="<?= e($old['order_number'] ?? '') ?>" placeholder="e.g., ORD-2026-004"><?php if (isset($errors['order_number'])): ?><span class="form-error"><?= e($errors['order_number']) ?></span><?php endif; ?></div>
    <div class="form-group"><label for="buyer_id" class="form-label">Active Buyer <span class="required">*</span></label><select id="buyer_id" name="buyer_id" required class="form-control <?= isset($errors['buyer_id']) ? 'has-error' : '' ?>"><option value="">-- Select Active Buyer --</option><?php foreach ($buyers as $buyer): ?><option value="<?= (int) $buyer['buyer_id'] ?>" <?= (string)($old['buyer_id'] ?? '') === (string)$buyer['buyer_id'] ? 'selected' : '' ?>><?= e($buyer['buyer_name']) ?></option><?php endforeach; ?></select><?php if (isset($errors['buyer_id'])): ?><span class="form-error"><?= e($errors['buyer_id']) ?></span><?php endif; ?></div>
    <div class="form-group"><label for="style_number" class="form-label">Style Number <span class="required">*</span></label><input type="text" id="style_number" name="style_number" maxlength="50" required class="form-control <?= isset($errors['style_number']) ? 'has-error' : '' ?>" value="<?= e($old['style_number'] ?? '') ?>" placeholder="e.g., STY-OXFORD-SHIRT"><?php if (isset($errors['style_number'])): ?><span class="form-error"><?= e($errors['style_number']) ?></span><?php endif; ?></div>
    <div class="form-group"><label for="total_quantity" class="form-label">Total Quantity <span class="required">*</span></label><input type="number" id="total_quantity" name="total_quantity" min="1" step="1" required class="form-control <?= isset($errors['total_quantity']) ? 'has-error' : '' ?>" value="<?= e($old['total_quantity'] ?? '') ?>" placeholder="e.g., 2500"><?php if (isset($errors['total_quantity'])): ?><span class="form-error"><?= e($errors['total_quantity']) ?></span><?php endif; ?></div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;"><div class="form-group"><label for="order_date" class="form-label">Order Date</label><input type="date" id="order_date" name="order_date" class="form-control <?= isset($errors['order_date']) ? 'has-error' : '' ?>" value="<?= e($old['order_date'] ?? '') ?>"><?php if (isset($errors['order_date'])): ?><span class="form-error"><?= e($errors['order_date']) ?></span><?php endif; ?></div><div class="form-group"><label for="delivery_date" class="form-label">Delivery Date</label><input type="date" id="delivery_date" name="delivery_date" class="form-control <?= isset($errors['delivery_date']) ? 'has-error' : '' ?>" value="<?= e($old['delivery_date'] ?? '') ?>"><?php if (isset($errors['delivery_date'])): ?><span class="form-error"><?= e($errors['delivery_date']) ?></span><?php endif; ?></div></div>
    <div class="form-group"><label for="status" class="form-label">Order Status <span class="required">*</span></label><select id="status" name="status" required class="form-control <?= isset($errors['status']) ? 'has-error' : '' ?>"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= ($old['status'] ?? 'Pending') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><?php if (isset($errors['status'])): ?><span class="form-error"><?= e($errors['status']) ?></span><?php endif; ?></div>
    <div class="form-actions"><button type="submit" class="btn btn-primary">Save Order</button><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Cancel</a></div>
</form></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>