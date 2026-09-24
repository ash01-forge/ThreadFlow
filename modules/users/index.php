<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'User Management';
$currentNav = 'users';
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$roleFilter = trim($_GET['role'] ?? 'all');
$roles = ROLES_LIST;
$users = [];
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR username LIKE ? OR email LIKE ?)';
    $wildcard = '%' . $search . '%';
    $params = [$wildcard, $wildcard, $wildcard];
    $types = 'sss';
}
if ($statusFilter === 'active' || $statusFilter === 'inactive') {
    $where[] = 'is_active = ?';
    $params[] = $statusFilter === 'active' ? 1 : 0;
    $types .= 'i';
}
if (in_array($roleFilter, $roles, true)) {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
    $types .= 's';
}

$sql = 'SELECT user_id, username, email, full_name, role, is_active, last_login_at, created_at FROM users';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY user_id DESC';

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    error_log('Database exception loading users: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while loading users.');
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="page-header">
    <div class="page-title-group"><h1>User Management</h1><div class="page-subtitle">Administration &bull; Admin access only</div></div>
    <div class="page-actions"><a href="<?= e(url('modules/users/create.php')) ?>" class="btn btn-primary">Add New User</a></div>
</div>

<div class="card mb-4"><div class="card-body">
    <form method="get" action="<?= e(url('modules/users/index.php')) ?>" class="filter-bar">
        <div class="filter-field-grow"><label for="q" class="form-label">Search Users</label><input id="q" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Name, username, or email"></div>
        <div class="filter-field"><label for="role" class="form-label">Role</label><select id="role" name="role" class="form-control"><option value="all">All Roles</option><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $role))) ?></option><?php endforeach; ?></select></div>
        <div class="filter-field"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-control"><option value="all">All Statuses</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option></select></div>
        <div class="filter-actions"><button type="submit" class="btn btn-primary">Filter</button><?php if ($search !== '' || $roleFilter !== 'all' || $statusFilter !== 'all'): ?><a href="<?= e(url('modules/users/index.php')) ?>" class="btn btn-secondary">Clear</a><?php endif; ?></div>
    </form>
</div></div>

<div class="card"><div class="card-header"><h2>User Directory</h2><span class="badge badge-muted"><?= count($users) ?> Result(s)</span></div><div class="card-body" style="padding: 0;">
<?php if (!$users): ?><div class="empty-state"><p>No users found matching your criteria.</p></div><?php else: ?><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Full Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td>#<?= (int) $user['user_id'] ?></td><td><strong><a href="<?= e(url('modules/users/view.php?id=' . (int) $user['user_id'])) ?>"><?= e($user['full_name']) ?></a></strong></td><td><?= e($user['username']) ?></td><td><?= e($user['email']) ?></td><td><span class="badge badge-info"><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></span></td><td><?php if ((int) $user['is_active'] === 1): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></td><td class="text-nowrap"><a href="<?= e(url('modules/users/view.php?id=' . (int) $user['user_id'])) ?>" class="btn btn-sm btn-secondary">View</a> <a href="<?= e(url('modules/users/edit.php?id=' . (int) $user['user_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>