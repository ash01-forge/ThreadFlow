<?php
/**
 * ThreadFlow - Dashboard Shell
 * 
 * Central hub for authenticated users across all roles.
 * Displays accessible modules based on server-side role authorization.
 */
require_once __DIR__ . '/includes/auth.php';

require_login();

$user = auth_user();
$currentNav = 'dashboard';
$pageTitle = 'Dashboard';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Dashboard</h1>
        <div class="page-subtitle">Welcome back, <?= e($user['full_name'] ?: $user['username']) ?> &bull; Role: <strong><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></strong></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h2>Shared Project Foundation</h2>
    </div>
    <div class="card-body">
        <p>The ThreadFlow common foundation is active. This foundation provides centralized session security, role-based authorization, prepared MySQLi database access, CSRF token handling, and shared UI styling.</p>
        <p class="text-muted text-sm">
            <em>Note: The current visual layout is provisional. The approved color palette (#78000F primary maroon and #FCEBED background) has been applied. Individual module features will be implemented by their respective team members.</em>
        </p>
    </div>
</div>

<h2>Project Modules</h2>
<p class="text-muted mb-3">Module access is controlled by server-side role authorization. Cards reflect your current permissions.</p>

<div class="module-grid mb-4">
    <!-- Module 1: Buyer Management -->
    <?php $canBuyers = has_role(['admin', 'merchandiser']); ?>
    <div class="module-card <?= $canBuyers ? '' : 'restricted' ?>">
        <div>
            <div class="module-card-header">
                <span class="badge badge-muted">Member 1</span>
                <?php if ($canBuyers): ?>
                    <span class="badge badge-success">Authorized</span>
                <?php else: ?>
                    <span class="badge badge-danger">Restricted</span>
                <?php endif; ?>
            </div>
            <h3 class="module-card-title">Buyer Management</h3>
            <div class="module-card-owner">Assigned: Member 1 &bull; Allowed: Admin, Merchandiser</div>
            <p class="module-card-desc">Customer profiles, contact details, buyer agreements, and order tracking origins.</p>
        </div>
        <div class="module-card-footer">
            <?php if ($canBuyers): ?>
                <a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-sm btn-primary">Open Module</a>
            <?php else: ?>
                <span class="text-muted text-sm">Requires Merchandiser or Admin role</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Module 2: Supplier Management -->
    <?php $canSuppliers = has_role(['admin', 'production_manager']); ?>
    <div class="module-card <?= $canSuppliers ? '' : 'restricted' ?>">
        <div>
            <div class="module-card-header">
                <span class="badge badge-muted">Member 2</span>
                <?php if ($canSuppliers): ?>
                    <span class="badge badge-success">Authorized</span>
                <?php else: ?>
                    <span class="badge badge-danger">Restricted</span>
                <?php endif; ?>
            </div>
            <h3 class="module-card-title">Supplier Management</h3>
            <div class="module-card-owner">Assigned: Member 2 &bull; Allowed: Admin, Production Manager</div>
            <p class="module-card-desc">Raw material vendors, fabric mills, accessories suppliers, and delivery records.</p>
        </div>
        <div class="module-card-footer">
            <?php if ($canSuppliers): ?>
                <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="btn btn-sm btn-primary">Open Module</a>
            <?php else: ?>
                <span class="text-muted text-sm">Requires Production Manager or Admin role</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Module 3: Production Batches -->
    <?php $canBatches = has_role(['admin', 'production_manager']); ?>
    <div class="module-card <?= $canBatches ? '' : 'restricted' ?>">
        <div>
            <div class="module-card-header">
                <span class="badge badge-muted">Member 3</span>
                <?php if ($canBatches): ?>
                    <span class="badge badge-success">Authorized</span>
                <?php else: ?>
                    <span class="badge badge-danger">Restricted</span>
                <?php endif; ?>
            </div>
            <h3 class="module-card-title">Production Batches</h3>
            <div class="module-card-owner">Assigned: Member 3 &bull; Allowed: Admin, Production Manager</div>
            <p class="module-card-desc">Work order tracking, cutting and sewing line allocation, and production batch batches.</p>
        </div>
        <div class="module-card-footer">
            <?php if ($canBatches): ?>
                <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-sm btn-primary">Open Module</a>
            <?php else: ?>
                <span class="text-muted text-sm">Requires Production Manager or Admin role</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Module 4: Defect Types -->
    <?php $canDefects = has_role(['admin', 'qc_officer']); ?>
    <div class="module-card <?= $canDefects ? '' : 'restricted' ?>">
        <div>
            <div class="module-card-header">
                <span class="badge badge-muted">Member 4</span>
                <?php if ($canDefects): ?>
                    <span class="badge badge-success">Authorized</span>
                <?php else: ?>
                    <span class="badge badge-danger">Restricted</span>
                <?php endif; ?>
            </div>
            <h3 class="module-card-title">Defect Types</h3>
            <div class="module-card-owner">Assigned: Member 4 &bull; Allowed: Admin, QC Officer</div>
            <p class="module-card-desc">Quality control defect taxonomy, severity levels, sewing errors, and inspection logs.</p>
        </div>
        <div class="module-card-footer">
            <?php if ($canDefects): ?>
                <a href="<?= e(url('modules/defect_types/index.php')) ?>" class="btn btn-sm btn-primary">Open Module</a>
            <?php else: ?>
                <span class="text-muted text-sm">Requires QC Officer or Admin role</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($user['role'] === 'admin'): ?>
    <div class="card">
        <div class="card-header">
            <h3>System Diagnostics</h3>
        </div>
        <div class="card-body">
            <p>As an administrator, you can run diagnostic checks to verify local database connectivity and table status.</p>
            <a href="<?= e(url('tests/database_test.php')) ?>" class="btn btn-secondary">Run Database Diagnostic Check</a>
        </div>
    </div>
<?php endif; ?>

<?php
include __DIR__ . '/includes/footer.php';
?>
