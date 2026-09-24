<?php
/**
 * ThreadFlow - Shared Page Header
 * 
 * Includes HTML document head, primary navigation header, logged-in user summary,
 * and POST-only CSRF-protected logout action.
 */
require_once __DIR__ . '/auth.php';

$currentUser = auth_user();
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME . ' — Garment Production System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <style>
        .tf-command-bar { --tf-header-ink: #fff; --tf-header-soft: #f9dfe4; --tf-header-line: rgba(255,255,255,.2); position: relative; z-index: 10; min-height: 84px; box-sizing: border-box; align-items: center; border-bottom: 1px solid rgba(35,0,8,.3); background: linear-gradient(105deg, #4f0716, #78000f 72%, #81192d); box-shadow: 0 3px 12px rgba(60,0,12,.1); }
        .tf-command-bar .header-brand, .tf-command-bar .header-nav { position: relative; z-index: 1; }
        .tf-command-bar .header-brand { min-width: 0; gap: 16px; align-items: center; }
        .tf-command-bar .brand-title { gap: 10px; color: var(--tf-header-ink); font-size: 1.78rem !important; font-weight: 800; letter-spacing: .1em; line-height: 1; }
        .tf-command-bar .brand-title .brand-icon { width: 38px !important; height: 38px !important; color: #fff; }
        .tf-command-bar .brand-title > span { font-size: 1.78rem !important; font-weight: 800; letter-spacing: .1em; }
        .tf-command-bar .brand-tagline { padding-left: 18px; border-left: 1px solid rgba(255,255,255,.28); color: var(--tf-header-soft); font-size: 1.03rem !important; letter-spacing: .01em; line-height: 1.25; }
        .tf-command-bar .sidebar-toggle { color: #fff; border-color: var(--tf-header-line); background: rgba(255,255,255,.08); }
        .tf-command-bar .sidebar-toggle:hover, .tf-command-bar .sidebar-toggle:focus-visible { border-color: #fff; background: rgba(255,255,255,.18); }
        .tf-command-bar .header-nav { gap: 12px; align-items: center; }
        .tf-command-bar .header-user { display: flex; align-items: center; gap: 9px; min-width: 0; padding: 4px 9px 4px 5px; border: 0; border-radius: 999px; background: rgba(255,255,255,.08); }
        .tf-command-bar .user-avatar { display: grid; place-items: center; width: 43px; height: 43px; flex: 0 0 43px; border-radius: 50%; color: #78000f; background: #fff; font-size: .86rem; font-weight: 800; }
        .tf-command-bar .user-name { max-width: 170px; overflow: hidden; color: #fff; font-size: 1.08rem !important; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .tf-command-bar .badge-role { padding: 5px 9px; border: 1px solid rgba(255,255,255,.28); border-radius: 999px; color: #fff; background: rgba(255,255,255,.12); font-size: .84rem !important; font-weight: 700; }
        .tf-command-bar .btn-header-logout { min-height: 40px; padding: 7px 14px; border: 1px solid rgba(255,255,255,.46); border-radius: 6px; color: #fff; background: rgba(255,255,255,.06); font-size: .98rem !important; font-weight: 700; }
        .tf-command-bar .btn-header-logout:hover, .tf-command-bar .btn-header-logout:focus-visible { color: #78000f; background: #fff; border-color: #fff; }
        @media (max-width: 820px) { .tf-command-bar { min-height: 78px; } .tf-command-bar .brand-tagline { display: none; } .tf-command-bar .header-brand { gap: 10px; } .tf-command-bar .header-nav { gap: 8px; } .tf-command-bar .badge-role { display: none; } .tf-command-bar .user-name { max-width: 125px; font-size: 1rem !important; } }
        @media (max-width: 480px) { .tf-command-bar { min-height: 70px; } .tf-command-bar .sidebar-toggle span { display: none; } .tf-command-bar .sidebar-toggle { padding: 7px; } .tf-command-bar .brand-title, .tf-command-bar .brand-title > span { font-size: 1.3rem !important; letter-spacing: .08em; } .tf-command-bar .brand-title .brand-icon { width: 30px !important; height: 30px !important; } .tf-command-bar .user-avatar { width: 40px; height: 40px; flex-basis: 40px; } .tf-command-bar .user-name { max-width: 84px; font-size: .9rem !important; } .tf-command-bar .btn-header-logout { min-height: 34px; padding: 6px 8px; font-size: .82rem !important; } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <header class="app-header tf-command-bar">
        <div class="header-brand">
            <button class="sidebar-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
                <svg class="sidebar-toggle-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                <span>Menu</span>
            </button>
            <a href="<?= e(url('dashboard.php')) ?>" class="brand-title">
                <svg class="brand-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 3v18"></path><path d="M3 12h18"></path><circle cx="12" cy="12" r="4"></circle></svg>
                <span>THREADFLOW</span>
            </a>
            <span class="brand-tagline">Garment Production, Quality Control &amp; Traceability</span>
        </div>

        <div class="header-nav">
            <?php if ($currentUser): ?>
                <div class="header-user">
                    <span class="user-avatar" aria-hidden="true"><?= e(strtoupper(substr($currentUser['username'], 0, 1))) ?></span>
                    <span class="user-name"><?= e($currentUser['full_name'] ?: $currentUser['username']) ?></span>
                    <span class="badge badge-role"><?= e(ucwords(str_replace('_', ' ', $currentUser['role']))) ?></span>
                </div>
                <form action="<?= e(url('auth/logout.php')) ?>" method="post" class="logout-form" style="display: inline-block; margin: 0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-header-logout" title="Sign out of ThreadFlow">
                        Log Out
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-sm btn-header-logout">
                    Log In
                </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="app-body">
