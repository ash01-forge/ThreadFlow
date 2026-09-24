<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$pageTitle = 'Add User'; $currentNav = 'users'; $roles = ROLES_LIST;
$errors = $_SESSION['_form_errors'] ?? []; $old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);
include __DIR__ . '/../../includes/header.php'; include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.users-form { --users-ink: #3c1b25; --users-muted: #806b72; --users-line: #efd9df; --users-soft: #fff7f8; }
.users-form .users-form-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 24px; }
.users-form .users-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.users-form .users-form-card { max-width: 900px; overflow: hidden; border: 1px solid var(--users-line); border-radius: 14px; background: #fff; }
.users-form .users-section { padding: 24px; border-bottom: 1px solid var(--users-line); }
.users-form .users-section-head { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 20px; }
.users-form .users-step { display: grid; place-items: center; flex: 0 0 30px; width: 30px; height: 30px; border-radius: 50%; color: #fff; background: #78000f; font-weight: 700; }
.users-form .users-section h2 { margin: 0 0 4px; color: var(--users-ink); font-size: 1.1rem; }
.users-form .users-section-head p { margin: 0; color: var(--users-muted); font-size: .84rem; }
.users-form .users-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.users-form .users-fields .form-group { margin: 0; }
.users-form .users-fields .users-wide { grid-column: 1 / -1; }
.users-form .users-option-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.users-form .users-option { position: relative; display: block; cursor: pointer; }
.users-form .users-option input { position: absolute; opacity: 0; }
.users-form .users-option-card { display: block; min-height: 64px; padding: 14px; border: 2px solid var(--users-line); border-radius: 10px; color: var(--users-muted); text-align: center; }
.users-form .users-option-card strong { display: block; color: var(--users-ink); }
.users-form .users-option-card small { display: block; margin-top: 5px; font-size: .76rem; }
.users-form .users-option input:checked + .users-option-card { border-color: #78000f; background: #fff1f4; box-shadow: 0 0 0 3px rgba(120, 0, 15, .1); color: #78000f; }
.users-form .users-option input:checked + .users-option-card strong { color: #78000f; }
.users-form .users-option input:focus-visible + .users-option-card { outline: 3px solid #d58a99; outline-offset: 2px; }
.users-form .users-option[data-status="1"] input:checked + .users-option-card { border-color: #25805c; background: #f2fbf6; }
.users-form .users-option[data-status="1"] input:checked + .users-option-card strong { color: #1c704e; }
.users-form .users-option[data-status="0"] input:checked + .users-option-card { border-color: #b43b4c; background: #fff4f5; }
.users-form .users-option[data-status="0"] input:checked + .users-option-card strong { color: #a52e42; }
.users-form .users-submit { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 24px; background: var(--users-soft); }
.users-form .users-submit p { margin: 0; color: var(--users-muted); font-size: .82rem; }
.users-form .users-submit .form-actions { margin: 0; }
@media (max-width: 650px) { .users-form .users-form-head { display: block; } .users-form .users-form-head .page-actions { margin-top: 14px; } .users-form .users-fields { grid-template-columns: 1fr; } .users-form .users-fields .users-wide { grid-column: auto; } .users-form .users-option-grid { grid-template-columns: 1fr; } .users-form .users-submit { display: block; } .users-form .users-submit .form-actions { margin-top: 14px; } .users-form .users-submit .btn { width: 100%; margin-bottom: 8px; } }
</style>
<div class="users-form"><div class="users-form-head"><div><div class="users-kicker">Account provisioning</div><div class="page-title-group"><h1>Add User</h1><div class="page-subtitle">Create a ThreadFlow account with the right access from the start.</div></div></div><div class="page-actions"><a href="<?= e(url('modules/users/index.php')) ?>" class="btn btn-secondary">Back to User List</a></div></div>
<div class="users-form-card"><form action="<?= e(url('modules/users/store.php')) ?>" method="post" novalidate><?= csrf_field() ?><section class="users-section"><div class="users-section-head"><span class="users-step">1</span><div><h2>Identity Information</h2><p>Enter the person’s visible profile and contact details.</p></div></div><div class="users-fields"><?php $fields = [['full_name','Full Name','text','100'],['username','Username','text','50'],['email','Email','email','150']]; foreach ($fields as [$name,$label,$type,$max]): ?><div class="form-group <?= $name === 'email' ? 'users-wide' : '' ?>"><label for="<?= e($name) ?>" class="form-label"><?= e($label) ?> <span class="required">*</span></label><input type="<?= e($type) ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" maxlength="<?= e($max) ?>" required class="form-control <?= isset($errors[$name]) ? 'has-error' : '' ?>" value="<?= e($old[$name] ?? '') ?>"><?php if (isset($errors[$name])): ?><span class="form-error"><?= e($errors[$name]) ?></span><?php endif; ?></div><?php endforeach; ?></div></section>
<section class="users-section"><div class="users-section-head"><span class="users-step">2</span><div><h2>Login Credentials</h2><p>Set a secure password for the new account.</p></div></div><div class="users-fields"><div class="form-group"><label for="password" class="form-label">Password <span class="required">*</span></label><input type="password" id="password" name="password" minlength="8" required class="form-control <?= isset($errors['password']) ? 'has-error' : '' ?>"><?php if (isset($errors['password'])): ?><span class="form-error"><?= e($errors['password']) ?></span><?php endif; ?></div><div class="form-group"><label for="password_confirmation" class="form-label">Confirm Password <span class="required">*</span></label><input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required class="form-control <?= isset($errors['password_confirmation']) ? 'has-error' : '' ?>"><?php if (isset($errors['password_confirmation'])): ?><span class="form-error"><?= e($errors['password_confirmation']) ?></span><?php endif; ?></div></div></section>
<section class="users-section"><div class="users-section-head"><span class="users-step">3</span><div><h2>Role Assignment</h2><p>Choose the access role this account will use.</p></div></div><fieldset class="form-group" style="border:0; padding:0; margin:0;"><legend class="form-label">Role <span class="required">*</span></legend><div class="users-option-grid <?= isset($errors['role']) ? 'has-error' : '' ?>"><?php foreach ($roles as $role): ?><label class="users-option"><input type="radio" id="role_<?= e($role) ?>" name="role" value="<?= e($role) ?>" required <?= ($old['role'] ?? '') === $role ? 'checked' : '' ?>><span class="users-option-card"><strong><?= e(ucwords(str_replace('_', ' ', $role))) ?></strong><small>ThreadFlow access role</small></span></label><?php endforeach; ?></div></fieldset><?php if (isset($errors['role'])): ?><span class="form-error"><?= e($errors['role']) ?></span><?php endif; ?></section>
<section class="users-section"><div class="users-section-head"><span class="users-step">4</span><div><h2>Account Status</h2><p>Choose whether the account can sign in immediately.</p></div></div><fieldset class="form-group" style="border:0; padding:0; margin:0;"><legend class="form-label">Status</legend><div class="users-option-grid"><label class="users-option" data-status="1"><input type="radio" id="is_active_1" name="is_active" value="1" <?= ($old['is_active'] ?? '1') === '1' ? 'checked' : '' ?>><span class="users-option-card"><strong>Active</strong><small>Available for sign-in</small></span></label><label class="users-option" data-status="0"><input type="radio" id="is_active_0" name="is_active" value="0" <?= ($old['is_active'] ?? '') === '0' ? 'checked' : '' ?>><span class="users-option-card"><strong>Inactive</strong><small>Access temporarily disabled</small></span></label></div></fieldset></section><div class="users-submit"><div><strong>Ready to create?</strong><p>The new account will use the selected role and status.</p></div><div class="form-actions"><button type="submit" class="btn btn-primary">Create User</button><a href="<?= e(url('modules/users/index.php')) ?>" class="btn btn-secondary">Cancel</a></div></div></form></div></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>