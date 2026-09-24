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
    <style>
        .tf-login { --tf-maroon: #78000f; --tf-deep: #3d0914; --tf-rose: #f7dfe4; --tf-ink: #32131d; --tf-muted: #806b72; min-height: 100vh; display: grid; place-items: center; padding: 38px 24px; overflow: hidden; background: #fbf3f4; }
        .tf-login-shell { display: grid; grid-template-columns: 40% 60%; width: min(1140px, 100%); min-height: 500px; overflow: hidden; border: 1px solid #ead6db; border-radius: 16px; background: #fff; box-shadow: 0 16px 42px rgba(80, 0, 16, .1); }
        .tf-login-brand { position: relative; display: flex; align-items: center; padding: 42px; overflow: hidden; color: #fff; background: linear-gradient(145deg, #4b0715, #78000f 70%, #861e33); }
        .tf-login-brand::before { position: absolute; inset: 0; opacity: .18; background-image: linear-gradient(rgba(255,255,255,.16) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.12) 1px, transparent 1px); background-size: 22px 22px; content: ''; pointer-events: none; }
        .tf-login-brand::after { position: absolute; right: -130px; bottom: 18px; width: 390px; height: 130px; border: 1px solid rgba(255,255,255,.16); border-radius: 50%; content: ''; transform: rotate(-12deg); pointer-events: none; }
        .tf-login-brand-content { position: relative; z-index: 1; width: 100%; max-width: 360px; }
        .tf-login-mark { display: inline-grid; place-items: center; width: 38px; height: 38px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,.34); border-radius: 10px; color: #fff; background: rgba(255,255,255,.08); }
        .tf-login-brand h1 { margin: 0 0 8px; color: #fff; font-size: 1.7rem; letter-spacing: .1em; line-height: 1; }
        .tf-login-tagline { max-width: 300px; color: #f9dfe4; font-size: .76rem; line-height: 1.5; }
        .tf-login-message { max-width: 315px; margin: 28px 0 0; color: #fff; font-size: 1.05rem; font-weight: 700; line-height: 1.42; }
        .tf-login-message::after { display: block; width: 44px; height: 2px; margin-top: 14px; background: #f4b1bb; content: ''; }
        .tf-login-support { max-width: 315px; margin: 10px 0 0; color: #f9dfe4; font-size: .8rem; line-height: 1.5; }
        .tf-stitch-route { position: relative; display: grid; gap: 16px; margin-top: 30px; padding-left: 20px; }
        .tf-stitch-route::before { position: absolute; top: 7px; bottom: 7px; left: 4px; width: 1px; background: repeating-linear-gradient(to bottom, #f4b1bb 0 7px, transparent 7px 13px); content: ''; }
        .tf-stitch-stage { position: relative; color: #f9dfe4; font-size: .7rem; text-transform: uppercase; letter-spacing: .09em; }
        .tf-stitch-stage::before { position: absolute; top: 1px; left: -20px; width: 7px; height: 7px; border: 2px solid #f8cbd4; border-radius: 50%; background: #78000f; content: ''; }
        .tf-login-workspace { display: flex; flex-direction: column; justify-content: center; padding: 42px clamp(38px, 7vw, 86px); background: #fff; }
        .tf-login-eyebrow { margin-bottom: 8px; color: #a04b5c; font-size: .68rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .tf-login-workspace h2 { margin: 0 0 6px; color: var(--tf-ink); font-size: 1.8rem; }
        .tf-login-intro { margin: 0 0 22px; color: var(--tf-muted); font-size: .9rem; line-height: 1.5; }
        .tf-login-workspace .alert { margin-bottom: 14px; padding: 9px 12px; font-size: .82rem; }
        .tf-login-workspace .form-group { margin-bottom: 15px; }
        .tf-login-workspace .form-label { color: var(--tf-ink); font-size: .8rem; font-weight: 700; }
        .tf-login-workspace .form-control { min-height: 44px; border-color: #e6cfd5; border-radius: 7px; background: #fffafd; }
        .tf-login-workspace .form-control:focus { border-color: #a33b50; box-shadow: 0 0 0 3px rgba(163,59,80,.12); }
        .tf-password-wrap { position: relative; }
        .tf-password-wrap .form-control { padding-right: 74px; }
        .tf-password-toggle { position: absolute; top: 50%; right: 7px; min-height: 30px; padding: 4px 8px; transform: translateY(-50%); border: 0; border-radius: 5px; color: #78000f; background: transparent; font-size: .72rem; font-weight: 700; cursor: pointer; }
        .tf-password-toggle:hover, .tf-password-toggle:focus-visible { background: #fbecef; outline: 2px solid #c56c7d; outline-offset: 1px; }
        .tf-login-submit { width: 100%; min-height: 44px; margin-top: 5px; border-radius: 7px; box-shadow: 0 6px 14px rgba(120,0,15,.14); font-weight: 800; }
        .tf-login-security { display: flex; align-items: center; gap: 8px; margin-top: 18px; color: var(--tf-muted); font-size: .72rem; }
        .tf-login-security::before { width: 6px; height: 6px; flex: 0 0 6px; border-radius: 50%; background: #25805c; content: ''; box-shadow: 0 0 0 3px #e2f4ea; }
        .tf-login-footer { display: none; }
        @media (max-width: 820px) { .tf-login { padding: 20px 16px; } .tf-login-shell { grid-template-columns: 1fr; min-height: auto; width: min(620px, 100%); } .tf-login-brand { min-height: 260px; padding: 32px; } .tf-login-brand-content { max-width: none; } .tf-login-message { margin-top: 18px; } .tf-stitch-route { position: absolute; right: 34px; bottom: 32px; margin: 0; } .tf-login-workspace { padding: 36px 42px 40px; } }
        @media (max-width: 500px) { .tf-login { display: block; padding: 0; } .tf-login-shell { width: 100%; min-height: 100vh; border: 0; border-radius: 0; box-shadow: none; } .tf-login-brand { min-height: 220px; padding: 28px 24px; } .tf-login-brand::after, .tf-stitch-route { display: none; } .tf-login-message { max-width: 300px; font-size: .94rem; } .tf-login-workspace { padding: 32px 24px 28px; } }
        @media (prefers-reduced-motion: reduce) { .tf-login *, .tf-login *::before, .tf-login *::after { transition: none !important; animation: none !important; } }
    </style>
</head>
<body class="auth-page tf-login">
    <main class="tf-login-shell">
        <section class="tf-login-brand" aria-label="ThreadFlow overview">
            <div class="tf-login-brand-content">
                <div class="tf-login-mark" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 3v18"></path><path d="M3 12h18"></path><circle cx="12" cy="12" r="4"></circle></svg></div>
                <h1>THREADFLOW</h1>
                <div class="tf-login-tagline">Garment Production, Quality Control &amp; Traceability</div>
                <p class="tf-login-message">Production, connected.</p>
                <p class="tf-login-support">A calm control desk for garment production, quality, and traceable delivery decisions.</p>
                <div class="tf-stitch-route" aria-label="Connected garment workflow"><span class="tf-stitch-stage">Buyer request</span><span class="tf-stitch-stage">Production</span><span class="tf-stitch-stage">Quality check</span><span class="tf-stitch-stage">Delivery</span></div>
            </div>
        </section>
        <section class="tf-login-workspace" aria-labelledby="login-heading">
            <div class="tf-login-eyebrow">Operations command portal</div>
            <h2 id="login-heading">Welcome back</h2>
            <p class="tf-login-intro">Sign in to continue to your ThreadFlow workspace.</p>
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
                    <div class="tf-password-wrap"><input type="password" id="password" name="password" class="form-control<?= !empty($errors['password']) ? ' has-error' : '' ?>" autocomplete="current-password" required><button type="button" class="tf-password-toggle" id="password-toggle" aria-label="Show password" aria-controls="password" aria-pressed="false">Show</button></div>
                    <?php if (!empty($errors['password'])): ?>
                        <span class="form-error"><?= e($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-auth-submit tf-login-submit">
                    Sign In
                </button>
            </form>
            <div class="tf-login-security">Internal authorized access. Use your assigned ThreadFlow credentials.</div>
            <div class="tf-login-footer text-center"><span>ThreadFlow &bull; Internal Access Only</span></div>
        </section>
    </main>
    <script>
        (function () {
            var password = document.getElementById('password');
            var toggle = document.getElementById('password-toggle');
            if (!password || !toggle) return;
            toggle.addEventListener('click', function () {
                var visible = password.type === 'text';
                password.type = visible ? 'password' : 'text';
                toggle.textContent = visible ? 'Show' : 'Hide';
                toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
                toggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            });
        }());
    </script>
</body>
</html>
