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
</head>
<body>
<div class="app-wrapper">
    <header class="app-header">
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
