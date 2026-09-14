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
                    <span class="nav-link-main">
                        <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        <span>Dashboard</span>
                    </span>
                </a>
            </li>
        </ul>

        <div class="nav-section-title">Modules</div>
        <ul class="nav-list">
            <?php if (in_array($userRole, ['admin', 'merchandiser'], true)): ?>
                <li>
                    <a href="<?= e(url('modules/buyers/index.php')) ?>" class="nav-link <?= $currentNav === 'buyers' ? 'active' : '' ?>">
                        <span class="nav-link-main">
                            <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span>Buyer Management</span>
                        </span>
                        <span class="nav-badge badge-muted">M1</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (in_array($userRole, ['admin', 'production_manager'], true)): ?>
                <li>
                    <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="nav-link <?= $currentNav === 'suppliers' ? 'active' : '' ?>">
                        <span class="nav-link-main">
                            <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                            <span>Supplier Management</span>
                        </span>
                        <span class="nav-badge badge-muted">M2</span>
                    </a>
                </li>
                <li>
                    <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="nav-link <?= $currentNav === 'batches' ? 'active' : '' ?>">
                        <span class="nav-link-main">
                            <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                            <span>Production Batches</span>
                        </span>
                        <span class="nav-badge badge-muted">M3</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (in_array($userRole, ['admin', 'qc_officer'], true)): ?>
                <li>
                    <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="nav-link <?= $currentNav === 'defect_types' ? 'active' : '' ?>">
                        <span class="nav-link-main">
                            <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            <span>Defect Types</span>
                        </span>
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
                        <span class="nav-link-main">
                            <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                            <span>Database Diagnostics</span>
                        </span>
                        <span class="badge badge-warning">Admin</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="system-status">
            <span class="status-indicator" aria-hidden="true"></span>
            <span class="status-text">Core System Active</span>
        </div>
    </div>
</aside>
<main class="app-main">
    <div class="content-container">
        <?php include __DIR__ . '/alerts.php'; ?>
