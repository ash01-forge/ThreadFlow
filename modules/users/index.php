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
<?php
$totalUsers = count($users);
$activeUsers = count(array_filter($users, static fn ($user) => (int) $user['is_active'] === 1));
$inactiveUsers = $totalUsers - $activeUsers;
$administratorUsers = count(array_filter($users, static fn ($user) => $user['role'] === 'admin'));
?>
<style>
.users-index { --users-ink: #3c1b25; --users-muted: #806b72; --users-line: #efd9df; --users-soft: #fff7f8; }
.users-index .users-hero { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; margin-bottom: 22px; }
.users-index .users-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.users-index .users-hero h1 { margin-bottom: 6px; }
.users-index .users-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 20px; }
.users-index .users-stat { padding: 15px 16px; border: 1px solid var(--users-line); border-radius: 11px; background: #fff; }
.users-index .users-stat strong { display: block; color: var(--users-ink); font-size: 1.45rem; }
.users-index .users-stat span { color: var(--users-muted); font-size: .78rem; }
.users-index .users-toolbar { display: flex; align-items: flex-end; gap: 14px; padding: 16px; margin-bottom: 20px; border: 1px solid var(--users-line); border-radius: 12px; background: var(--users-soft); }
.users-index .users-toolbar .filter-field-grow, .users-index .users-toolbar .filter-field { flex: 1; margin: 0; }
.users-index .users-toolbar .filter-actions { display: flex; gap: 8px; }
.users-index .users-directory { overflow: hidden; border: 1px solid var(--users-line); border-radius: 14px; background: #fff; }
.users-index .users-directory-head { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--users-line); }
.users-index .users-directory-head h2 { margin: 0; color: var(--users-ink); font-size: 1.1rem; }
.users-index .users-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(315px, 1fr)); gap: 12px; padding: 16px; }
.users-index .user-card { padding: 18px; border: 1px solid var(--users-line); border-left: 4px solid #25805c; border-radius: 10px; background: #fff; transition: box-shadow .18s ease, transform .18s ease; }
.users-index .user-card[data-active="0"] { border-left-color: #b43b4c; }
.users-index .user-card:hover { box-shadow: 0 8px 22px rgba(120, 0, 15, .1); transform: translateY(-2px); }
.users-index .user-card-top, .users-index .user-card-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.users-index .user-card-id { color: var(--users-muted); font-size: .75rem; font-weight: 700; }
.users-index .user-name { display: block; margin-top: 12px; color: #78000f; font-size: 1.1rem; font-weight: 700; }
.users-index .user-username { margin: 3px 0 17px; color: var(--users-muted); font-size: .86rem; }
.users-index .user-card-meta { display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; padding: 14px 0; border-top: 1px solid #f4e7e9; border-bottom: 1px solid #f4e7e9; }
.users-index .user-card-meta small { display: block; color: var(--users-muted); font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; }
.users-index .user-card-meta strong { display: block; margin-top: 4px; color: var(--users-ink); font-size: .84rem; overflow-wrap: anywhere; }
.users-index .user-card-actions { justify-content: flex-start; padding-top: 14px; }
.users-index .user-card-actions .btn { flex: 1; text-align: center; }
@media (max-width: 760px) { .users-index .users-hero { display: block; } .users-index .users-hero .page-actions { margin-top: 14px; } .users-index .users-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .users-index .users-toolbar { display: block; } .users-index .users-toolbar .filter-field { margin-top: 12px; } .users-index .users-toolbar .filter-actions { margin-top: 14px; } .users-index .users-toolbar .btn { flex: 1; text-align: center; } }
</style>
<div class="users-index"><div class="users-hero"><div><div class="users-kicker">Administration</div><div class="page-title-group"><h1>User Management</h1><div class="page-subtitle">Manage ThreadFlow identities, access roles, and account availability.</div></div></div><div class="page-actions"><a href="<?= e(url('modules/users/create.php')) ?>" class="btn btn-primary">+ Add New User</a></div></div>
<div class="users-summary"><div class="users-stat"><strong><?= $totalUsers ?></strong><span>Total Users</span></div><div class="users-stat"><strong><?= $activeUsers ?></strong><span>Active Users</span></div><div class="users-stat"><strong><?= $inactiveUsers ?></strong><span>Inactive Users</span></div><div class="users-stat"><strong><?= $administratorUsers ?></strong><span>Administrators</span></div></div>
<div class="users-toolbar"><form method="get" action="<?= e(url('modules/users/index.php')) ?>" class="filter-bar" style="display: contents;"><div class="filter-field-grow"><label for="q" class="form-label">Search Users</label><input id="q" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Name, username, or email"></div><div class="filter-field"><label for="role" class="form-label">Role</label><select id="role" name="role" class="form-control"><option value="all">All Roles</option><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $role))) ?></option><?php endforeach; ?></select></div><div class="filter-field"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-control"><option value="all">All Statuses</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option></select></div><div class="filter-actions"><button type="submit" class="btn btn-primary">Apply Filters</button><?php if ($search !== '' || $roleFilter !== 'all' || $statusFilter !== 'all'): ?><a href="<?= e(url('modules/users/index.php')) ?>" class="btn btn-secondary">Clear</a><?php endif; ?></div></form></div>
<div class="users-directory"><div class="users-directory-head"><h2>User Directory</h2><span class="badge badge-muted"><?= count($users) ?> Result(s)</span></div><?php if (!$users): ?><div class="empty-state"><p>No users found matching your criteria.</p></div><?php else: ?><div class="users-grid"><?php foreach ($users as $user): ?><article class="user-card" data-active="<?= (int) $user['is_active'] ?>"><div class="user-card-top"><span class="user-card-id">User ID #<?= (int) $user['user_id'] ?></span><?php if ((int) $user['is_active'] === 1): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></div><a href="<?= e(url('modules/users/view.php?id=' . (int) $user['user_id'])) ?>" class="user-name"><?= e($user['full_name']) ?></a><div class="user-username"><?= e('@' . ltrim($user['username'], '@')) ?></div><div class="user-card-meta"><div><small>Email</small><strong><?= e($user['email']) ?></strong></div><div><small>Role</small><strong><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></strong></div><div><small>Last Login</small><strong><?= e($user['last_login_at'] ?: 'Never') ?></strong></div><div><small>Created</small><strong><?= e($user['created_at']) ?></strong></div></div><div class="user-card-actions"><a href="<?= e(url('modules/users/view.php?id=' . (int) $user['user_id'])) ?>" class="btn btn-sm btn-secondary">View</a><a href="<?= e(url('modules/users/edit.php?id=' . (int) $user['user_id'])) ?>" class="btn btn-sm btn-primary">Edit</a></div></article><?php endforeach; ?></div><?php endif; ?></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>