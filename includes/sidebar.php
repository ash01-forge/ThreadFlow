<?php
/**
 * ThreadFlow - Shared Sidebar Navigation
 * 
 * Filters menu items based on server-side role authorization.
 */
require_once __DIR__ . '/auth.php';

$currentUser = auth_user();
$currentNav = $currentNav ?? '';
$userRole = $currentUser['role'] ?? '';
?>
<div class="sidebar-backdrop" aria-hidden="true"></div>
<aside class="app-sidebar" aria-label="Main Navigation">
    <nav class="sidebar-nav">
        <div class="nav-section-title">Navigation</div>
        <ul class="nav-list">
            <li>
                <a href="<?= e(url('dashboard.php')) ?>" class="nav-link <?= $currentNav === 'dashboard' ? 'active' : '' ?>">
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-title">Modules</div>
        <ul class="nav-list">
            <?php if (in_array($userRole, ['admin', 'merchandiser'], true)): ?>
                <li>
                    <a href="<?= e(url('modules/buyers/index.php')) ?>" class="nav-link <?= $currentNav === 'buyers' ? 'active' : '' ?>">
                        <span>Buyer Management</span>
                        <span class="nav-badge badge-muted">M1</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (in_array($userRole, ['admin', 'production_manager'], true)): ?>
                <li>
                    <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="nav-link <?= $currentNav === 'suppliers' ? 'active' : '' ?>">
                        <span>Supplier Management</span>
                        <span class="nav-badge badge-muted">M2</span>
                    </a>
                </li>
                <li>
                    <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="nav-link <?= $currentNav === 'batches' ? 'active' : '' ?>">
                        <span>Production Batches</span>
                        <span class="nav-badge badge-muted">M3</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (in_array($userRole, ['admin', 'qc_officer'], true)): ?>
                <li>
                    <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="nav-link <?= $currentNav === 'defect_types' ? 'active' : '' ?>">
                        <span>Defect Types</span>
                        <span class="nav-badge badge-muted">M4</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <?php if ($userRole === 'admin'): ?>
            <div class="nav-section-title">Administration</div>
            <ul class="nav-list">
                <li>
                    <a href="<?= e(url('tests/database_test.php')) ?>" class="nav-link <?= $currentNav === 'diagnostics' ? 'active' : '' ?>">
                        <span>Database Diagnostics</span>
                        <span class="badge badge-warning">Admin</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>
</aside>
<main class="app-main">
    <div class="content-container">
        <?php include __DIR__ . '/alerts.php'; ?>
