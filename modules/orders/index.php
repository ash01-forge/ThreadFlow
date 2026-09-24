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
<?php
$totalOrders = count($orders);
$pendingOrders = count(array_filter($orders, static fn ($order) => $order['status'] === 'Pending'));
$productionOrders = count(array_filter($orders, static fn ($order) => $order['status'] === 'In Production'));
$completedOrders = count(array_filter($orders, static fn ($order) => $order['status'] === 'Completed'));
?>
<style>
.orders-index { --orders-ink: #3c1b25; --orders-muted: #806b72; --orders-line: #efd9df; --orders-soft: #fff7f8; }
.orders-index .orders-hero { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; margin-bottom: 22px; }
.orders-index .orders-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.orders-index .orders-hero h1 { margin-bottom: 6px; }
.orders-index .orders-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 20px; }
.orders-index .orders-stat { padding: 15px 16px; border: 1px solid var(--orders-line); border-radius: 11px; background: #fff; }
.orders-index .orders-stat strong { display: block; color: var(--orders-ink); font-size: 1.45rem; }
.orders-index .orders-stat span { color: var(--orders-muted); font-size: .78rem; }
.orders-index .orders-toolbar { display: flex; align-items: flex-end; gap: 14px; padding: 16px; margin-bottom: 20px; border: 1px solid var(--orders-line); border-radius: 12px; background: var(--orders-soft); }
.orders-index .orders-toolbar .filter-field-grow, .orders-index .orders-toolbar .filter-field { flex: 1; margin: 0; }
.orders-index .orders-toolbar .filter-actions { display: flex; gap: 8px; }
.orders-index .orders-register { overflow: hidden; border: 1px solid var(--orders-line); border-radius: 14px; background: #fff; }
.orders-index .orders-register-head { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--orders-line); }
.orders-index .orders-register-head h2 { margin: 0; color: var(--orders-ink); font-size: 1.1rem; }
.orders-index .orders-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 12px; padding: 16px; }
.orders-index .order-card { padding: 18px; border: 1px solid var(--orders-line); border-top: 4px solid #b77800; border-radius: 10px; background: #fff; transition: box-shadow .18s ease, transform .18s ease; }
.orders-index .order-card:hover { box-shadow: 0 8px 22px rgba(120, 0, 15, .1); transform: translateY(-2px); }
.orders-index .order-card[data-status="Completed"] { border-top-color: #25805c; }
.orders-index .order-card[data-status="Cancelled"] { border-top-color: #b43b4c; }
.orders-index .order-card[data-status="In Production"] { border-top-color: #78000f; }
.orders-index .order-card-top, .orders-index .order-card-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.orders-index .order-card-id { color: var(--orders-muted); font-size: .75rem; font-weight: 700; }
.orders-index .order-number { display: block; margin-top: 12px; color: #78000f; font-size: 1.12rem; font-weight: 700; }
.orders-index .order-card-buyer { margin: 3px 0 18px; color: var(--orders-ink); font-size: .9rem; }
.orders-index .order-card-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; padding: 14px 0; border-top: 1px solid #f4e7e9; border-bottom: 1px solid #f4e7e9; }
.orders-index .order-card-meta small { display: block; color: var(--orders-muted); font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; }
.orders-index .order-card-meta strong { display: block; margin-top: 4px; color: var(--orders-ink); font-size: .84rem; overflow-wrap: anywhere; }
.orders-index .order-card-actions { justify-content: flex-start; padding-top: 14px; }
.orders-index .order-card-actions .btn { flex: 1; text-align: center; }
@media (max-width: 720px) { .orders-index .orders-hero { display: block; } .orders-index .orders-hero .page-actions { margin-top: 14px; } .orders-index .orders-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .orders-index .orders-toolbar { display: block; } .orders-index .orders-toolbar .filter-field { margin-top: 12px; } .orders-index .orders-toolbar .filter-actions { margin-top: 14px; } .orders-index .orders-toolbar .btn { flex: 1; text-align: center; } }
</style>
<div class="orders-index"><div class="orders-hero"><div><div class="orders-kicker">Order operations</div><div class="page-title-group"><h1>Order Management</h1><div class="page-subtitle">Track buyer orders from intake through production completion.</div></div></div><div class="page-actions"><a href="<?= e(url('modules/orders/create.php')) ?>" class="btn btn-primary">+ Add New Order</a></div></div>
<div class="orders-summary"><div class="orders-stat"><strong><?= $totalOrders ?></strong><span>Total Orders</span></div><div class="orders-stat"><strong><?= $pendingOrders ?></strong><span>Pending</span></div><div class="orders-stat"><strong><?= $productionOrders ?></strong><span>In Production</span></div><div class="orders-stat"><strong><?= $completedOrders ?></strong><span>Completed</span></div></div>
<div class="orders-toolbar"><form method="get" action="<?= e(url('modules/orders/index.php')) ?>" class="filter-bar" style="display: contents;"><div class="filter-field-grow"><label for="q" class="form-label">Search Orders</label><input type="text" id="q" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search by order #, style, or buyer name..."></div><div class="filter-field"><label for="status" class="form-label">Order Status</label><select id="status" name="status" class="form-control"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option><?php foreach ($validStatuses as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div><div class="filter-actions"><button type="submit" class="btn btn-primary">Apply Filters</button><?php if ($search !== '' || $statusFilter !== 'all'): ?><a href="<?= e(url('modules/orders/index.php')) ?>" class="btn btn-secondary">Clear</a><?php endif; ?></div></form></div>
<div class="orders-register"><div class="orders-register-head"><h2>Buyer Orders</h2><span class="badge badge-muted"><?= count($orders) ?> Result(s)</span></div><?php if (!$orders): ?><div class="empty-state"><p>No buyer orders found matching your criteria.</p><a href="<?= e(url('modules/orders/create.php')) ?>" class="btn btn-primary btn-sm">Create Your First Order</a></div><?php else: ?><div class="orders-grid"><?php foreach ($orders as $order): ?><?php $badgeClass = $order['status'] === 'Completed' ? 'badge-success' : ($order['status'] === 'Cancelled' ? 'badge-danger' : ($order['status'] === 'In Production' ? 'badge-primary' : 'badge-warning')); ?><article class="order-card" data-status="<?= e($order['status']) ?>"><div class="order-card-top"><span class="order-card-id">Order ID #<?= (int) $order['order_id'] ?></span><span class="badge <?= e($badgeClass) ?>"><?= e($order['status']) ?></span></div><a href="<?= e(url('modules/orders/view.php?id=' . (int) $order['order_id'])) ?>" class="order-number"><?= e($order['order_number']) ?></a><p class="order-card-buyer"><?= e($order['buyer_name']) ?></p><div class="order-card-meta"><div><small>Style</small><strong><?= e($order['style_number']) ?></strong></div><div><small>Total Quantity</small><strong><?= number_format((int) $order['total_quantity']) ?> pcs</strong></div><div><small>Delivery Date</small><strong><?= e($order['delivery_date'] ?: 'Not specified') ?></strong></div></div><div class="order-card-actions"><a href="<?= e(url('modules/orders/view.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-sm btn-secondary">View</a><a href="<?= e(url('modules/orders/edit.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-sm btn-primary">Edit</a></div></article><?php endforeach; ?></div><?php endif; ?></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>