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
                &#9776; Menu
            </button>
            <a href="<?= e(url('dashboard.php')) ?>" class="brand-title">THREADFLOW</a>
            <span class="brand-tagline">Garment Production, Quality Control &amp; Traceability</span>
        </div>

        <div class="header-nav">
            <?php if ($currentUser): ?>
                <div class="header-user">
                    <span class="user-name"><?= e($currentUser['full_name'] ?: $currentUser['username']) ?></span>
                    <span class="badge badge-role"><?= e(ucwords(str_replace('_', ' ', $currentUser['role']))) ?></span>
                </div>
                <form action="<?= e(url('auth/logout.php')) ?>" method="post" class="logout-form" style="display: inline-block; margin: 0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-secondary" style="color: #FFFFFF; border-color: rgba(255,255,255,0.5);">
                        Log Out
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-sm btn-secondary" style="color: #FFFFFF; border-color: rgba(255,255,255,0.5);">
                    Log In
                </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="app-body">
