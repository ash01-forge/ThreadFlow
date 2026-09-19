<?php
/** ThreadFlow - Buyer Order Management Directory. */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

$pageTitle = 'Order Management';
$currentNav = 'orders';
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$validStatuses = ['Pending', 'Confirmed', 'In Production', 'Completed', 'Cancelled'];

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(bo.order_number LIKE ? OR bo.style_number LIKE ? OR b.buyer_name LIKE ?)';
    $wildcard = '%' . $search . '%';
    $params = [$wildcard, $wildcard, $wildcard];
    $types = 'sss';
}
if ($statusFilter !== 'all' && in_array($statusFilter, $validStatuses, true)) {
    $where[] = 'bo.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$sql = 'SELECT bo.order_id, bo.order_number, bo.style_number, bo.total_quantity,
               bo.order_date, bo.delivery_date, bo.status, b.buyer_name
        FROM buyer_orders bo
        INNER JOIN buyers b ON bo.buyer_id = b.buyer_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY bo.order_id DESC';

$orders = [];
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Failed to prepare buyer orders index query: ' . $conn->error);
        flash_set('error', 'A database error occurred while fetching orders.');
    } else {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database exception fetching buyer orders: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while loading orders.');
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Order Management</h1>
        <div class="page-subtitle">Module 1 &bull; Ownership: Member 1 &bull; Access: Admin, Merchandiser</div>
    </div>
    <div class="page-actions"><a href="<?= e(url('modules/orders/create.php')) ?>" class="btn btn-primary">Add New Order</a></div>
</div>

<div class="card mb-4"><div class="card-body">
    <form method="get" action="<?= e(url('modules/orders/index.php')) ?>" class="filter-bar">
        <div class="filter-field-grow">
            <label for="q" class="form-label">Search Orders</label>
            <input type="text" id="q" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search by order #, style, or buyer name...">
        </div>
        <div class="filter-field">
            <label for="status" class="form-label">Order Status</label>
            <select id="status" name="status" class="form-control">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <?php foreach ($validStatuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($search !== '' || $statusFilter !== 'all'): ?><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Clear</a><?php endif; ?>
        </div>
    </form>
</div></div>

<div class="card"><div class="card-header"><h2>Buyer Orders</h2><span class="badge badge-muted"><?= count($orders) ?> Result(s)</span></div>
    <div class="card-body" style="padding: 0;">
        <?php if (!$orders): ?>
            <div class="empty-state"><p>No buyer orders found matching your criteria.</p><a href="<?= e(url('modules/orders/create.php')) ?>" class="btn btn-primary btn-sm">Create Your First Order</a></div>
        <?php else: ?>
            <div class="table-responsive"><table class="table"><thead><tr><th class="col-id">ID</th><th>Order Number</th><th>Buyer</th><th>Style</th><th>Quantity</th><th>Delivery Date</th><th class="col-status">Status</th><th class="col-actions">Actions</th></tr></thead><tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $badgeClass = $order['status'] === 'Completed' ? 'badge-success' : ($order['status'] === 'Cancelled' ? 'badge-danger' : ($order['status'] === 'In Production' ? 'badge-primary' : 'badge-warning')); ?>
                    <tr><td>#<?= (int) $order['order_id'] ?></td><td><strong><a href="<?= e(url('modules/orders/view.php?id=' . (int) $order['order_id'])) ?>"><?= e($order['order_number']) ?></a></strong></td><td><?= e($order['buyer_name']) ?></td><td><?= e($order['style_number']) ?></td><td><?= number_format((int) $order['total_quantity']) ?> pcs</td><td><?= e($order['delivery_date'] ?: '—') ?></td><td class="col-status"><span class="badge <?= e($badgeClass) ?>"><?= e($order['status']) ?></span></td><td class="text-nowrap"><div class="row-actions"><a href="<?= e(url('modules/orders/view.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-sm btn-secondary">View</a><a href="<?= e(url('modules/orders/edit.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a></div></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>