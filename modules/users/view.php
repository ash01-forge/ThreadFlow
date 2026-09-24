<?php
require_once __DIR__ . '/../../includes/auth.php'; require_role('admin');
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); if (!$userId || $userId < 1) { flash_set('error', 'Invalid user ID specified.'); redirect('modules/users/index.php'); }
try { $stmt = $conn->prepare('SELECT user_id, username, email, full_name, role, is_active, last_login_at, created_at, updated_at FROM users WHERE user_id = ? LIMIT 1'); if (!$stmt) throw new RuntimeException($conn->error); $stmt->bind_param('i', $userId); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close(); if (!$user) { flash_set('error', 'User record not found.'); redirect('modules/users/index.php'); } } catch (Throwable $e) { error_log('Database exception loading user: ' . $e->getMessage()); flash_set('error', 'A database error occurred while retrieving the user.'); redirect('modules/users/index.php'); }
$pageTitle = 'User: ' . $user['full_name']; $currentNav = 'users'; include __DIR__ . '/../../includes/header.php'; include __DIR__ . '/../../includes/sidebar.php';
$displayUsername = '@' . ltrim($user['username'], '@');
?>
<style>
.users-view { --users-ink: #3c1b25; --users-muted: #806b72; --users-line: #efd9df; }
.users-view .users-hero { padding: 24px; margin-bottom: 20px; border-radius: 14px; color: #fff; background: linear-gradient(120deg, #78000f, #a83c51); }
.users-view .users-hero .page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 22px; margin: 0; }
.users-view .users-kicker { margin-bottom: 7px; color: #f9dfe4; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.users-view .users-hero h1 { margin-bottom: 6px; color: #fff; }
.users-view .users-hero .page-subtitle { color: #f9dfe4; }
.users-view .users-hero .page-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.users-view .users-hero .btn-primary { color: #78000f; background: #fff; border-color: #fff; box-shadow: 0 3px 10px rgba(48, 0, 10, .2); }
.users-view .users-hero .btn-primary:hover { color: #5c000c; background: #fff4f6; border-color: #fff4f6; }
.users-view .users-hero .btn-secondary { color: #fff; background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .75); }
.users-view .users-hero .btn-secondary:hover { color: #fff; background: rgba(255, 255, 255, .2); border-color: #fff; }
.users-view .users-hero-profile { display: flex; align-items: center; gap: 16px; margin-top: 22px; }
.users-view .users-avatar { display: grid; place-items: center; width: 58px; height: 58px; flex: 0 0 58px; border-radius: 50%; color: #78000f; background: #fff; font-size: 1.35rem; font-weight: 800; }
.users-view .users-hero-profile strong { display: block; color: #fff; font-size: 1.15rem; }
.users-view .users-hero-profile span { display: block; margin-top: 3px; color: #f9dfe4; }
.users-view .users-hero-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.users-view .users-panel { overflow: hidden; margin-bottom: 18px; border: 1px solid var(--users-line); border-radius: 13px; background: #fff; }
.users-view .users-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 17px 20px; border-bottom: 1px solid var(--users-line); }
.users-view .users-panel-head h2 { margin: 0; color: var(--users-ink); font-size: 1.08rem; }
.users-view .users-panel-body { padding: 20px; }
.users-view .users-info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.users-view .users-info-card { padding: 15px; border: 1px solid var(--users-line); border-radius: 10px; background: #fffafd; }
.users-view .users-info-card small { display: block; color: var(--users-muted); font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.users-view .users-info-card p { margin: 7px 0 0; color: var(--users-ink); font-size: .9rem; overflow-wrap: anywhere; }
.users-view .users-status-panel { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.users-view .users-status-card { padding: 17px; border-radius: 10px; background: #f2fbf6; border: 1px solid #c8e8d5; }
.users-view .users-status-card.inactive { background: #fff4f5; border-color: #f0c8ce; }
.users-view .users-status-card small { display: block; color: var(--users-muted); font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; }
.users-view .users-status-card strong { display: block; margin-top: 6px; color: var(--users-ink); }
@media (max-width: 700px) { .users-view .users-hero .page-header { display: block; } .users-view .users-hero .page-actions { margin-top: 16px; } .users-view .users-hero .page-actions .btn { width: 100%; margin-bottom: 7px; text-align: center; } .users-view .users-info-grid, .users-view .users-status-panel { grid-template-columns: 1fr; } }
</style>
<div class="users-view"><div class="users-hero"><div class="page-header"><div class="page-title-group"><div class="users-kicker">User dossier</div><h1><?= e($user['full_name']) ?></h1><div class="page-subtitle">User ID #<?= (int) $user['user_id'] ?> &bull; <?= e($displayUsername) ?></div></div><div class="page-actions"><a href="<?= e(url('modules/users/index.php')) ?>" class="btn btn-secondary">Back to User List</a><a href="<?= e(url('modules/users/edit.php?id=' . (int) $user['user_id'])) ?>" class="btn btn-primary">Edit User</a></div></div><div class="users-hero-profile"><div class="users-avatar" aria-hidden="true"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div><div><strong><?= e($user['full_name']) ?></strong><span><?= e($user['email']) ?></span><div class="users-hero-badges"><span class="badge badge-info"><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></span><?php if ((int)$user['is_active'] === 1): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></div></div></div></div>
<div class="users-panel"><div class="users-panel-head"><h2>Account Identity</h2><span class="badge badge-muted">#<?= (int) $user['user_id'] ?></span></div><div class="users-panel-body"><div class="users-info-grid"><div class="users-info-card"><small>Full Name</small><p><?= e($user['full_name']) ?></p></div><div class="users-info-card"><small>Username</small><p><?= e($displayUsername) ?></p></div><div class="users-info-card"><small>Email</small><p><?= e($user['email']) ?></p></div></div></div></div>
<div class="users-panel"><div class="users-panel-head"><h2>Access and Role</h2><span class="badge badge-info"><?= e(ucwords(str_replace('_',' ',$user['role']))) ?></span></div><div class="users-panel-body"><div class="users-info-grid"><div class="users-info-card"><small>Role</small><p><?= e(ucwords(str_replace('_',' ',$user['role']))) ?></p></div><div class="users-info-card"><small>Account Status</small><p><?php if ((int)$user['is_active'] === 1): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></p></div></div></div></div>
<div class="users-panel"><div class="users-panel-head"><h2>Security / Activity Information</h2></div><div class="users-panel-body"><div class="users-status-panel"><div class="users-status-card <?= (int)$user['is_active'] === 1 ? '' : 'inactive' ?>"><small>Last Login</small><strong><?= e($user['last_login_at'] ?: 'Never') ?></strong></div><div class="users-status-card"><small>Created</small><strong><?= e($user['created_at']) ?></strong></div><div class="users-status-card"><small>Last Updated</small><strong><?= e($user['updated_at']) ?></strong></div></div></div></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>