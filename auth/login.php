<?php
/**
 * ThreadFlow - User Login
 * 
 * Provides secure authentication with CSRF protection, session ID regeneration,
 * and rate-friendly validation.
 */
require_once __DIR__ . '/../includes/auth.php';

// If user is already authenticated, send them to the dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '') {
        $errors['identifier'] = 'Username or email address is required.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (empty($errors)) {
        $result = auth_attempt($identifier, $password);
        if ($result['success']) {
            $intended = $_SESSION['intended_url'] ?? 'dashboard.php';
            unset($_SESSION['intended_url']);
            flash_set('success', 'Welcome back, ' . ($result['user']['full_name'] ?: $result['user']['username']) . '!');
            redirect($intended);
        } else {
            $errors['auth'] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-brand-badge" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 3v18"></path><path d="M3 12h18"></path><circle cx="12" cy="12" r="4"></circle></svg>
            </div>
            <h1>THREADFLOW</h1>
            <p>Garment Production, Quality Control &amp; Traceability</p>
        </div>

        <div class="auth-body">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <?php if (!empty($errors['auth'])): ?>
                <div class="alert alert-danger" role="alert">
                    <span class="alert-message"><?= e($errors['auth']) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('auth/login.php')) ?>" method="post" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="identifier" class="form-label">Username or Email <span class="required">*</span></label>
                    <input type="text"
                           id="identifier"
                           name="identifier"
                           class="form-control<?= !empty($errors['identifier']) ? ' has-error' : '' ?>"
                           value="<?= e($identifier) ?>"
                           autocomplete="username"
                           required
                           autofocus>
                    <?php if (!empty($errors['identifier'])): ?>
                        <span class="form-error"><?= e($errors['identifier']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control<?= !empty($errors['password']) ? ' has-error' : '' ?>"
                           autocomplete="current-password"
                           required>
                    <?php if (!empty($errors['password'])): ?>
                        <span class="form-error"><?= e($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-auth-submit">
                    Sign In
                </button>
            </form>
        </div>

        <div class="auth-footer text-center text-muted text-sm">
            <span>ThreadFlow &bull; Internal Access Only</span>
        </div>
    </div>
</body>
</html>
