<?php
/**
 * ThreadFlow - Supplier Details View
 * 
 * Module 2: Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Positive integer ID validation
$supplierId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$supplierId || $supplierId <= 0) {
    flash_set('error', 'Invalid supplier ID specified.');
    redirect('modules/suppliers/index.php');
}

// Fetch supplier details safely using prepared statements
try {
    $stmt = $conn->prepare("SELECT supplier_id, supplier_name, supplier_type, contact_person, email, phone, country, address, lead_time_days, notes, is_active, created_at, updated_at FROM suppliers WHERE supplier_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for supplier details: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving supplier details.');
        redirect('modules/suppliers/index.php');
    }

    $stmt->bind_param("i", $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    $stmt->close();

    // Safe handling of missing records
    if (!$supplier) {
        flash_set('error', 'Supplier record not found.');
        redirect('modules/suppliers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching supplier details: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving supplier details.');
    redirect('modules/suppliers/index.php');
}

$pageTitle = 'Supplier: ' . $supplier['supplier_name'];
$currentNav = 'suppliers';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Supplier Profile</h1>
        <div class="page-subtitle">Supplier ID #<?= (int) $supplier['supplier_id'] ?> &bull; <?= e($supplier['supplier_name']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/suppliers/index.php')) ?>" class="btn btn-secondary">Back to Supplier List</a>
        <a href="<?= e(url('modules/suppliers/edit.php?id=' . (int)$supplier['supplier_id'])) ?>" class="btn btn-secondary">Edit Profile</a>
        <form action="<?= e(url('modules/suppliers/toggle_status.php')) ?>" method="post" style="display: contents;">
            <?= csrf_field() ?>
            <input type="hidden" name="supplier_id" value="<?= (int) $supplier['supplier_id'] ?>">
            <input type="hidden" name="from_view" value="1">
            <?php if ((int)$supplier['is_active'] === 1): ?>
                <button type="submit" class="btn btn-danger" data-confirm="Are you sure you want to deactivate this supplier?">
                    Deactivate Supplier
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary" data-confirm="Are you sure you want to reactivate this supplier?">
                    Reactivate Supplier
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card card-medium">
    <div class="card-header">
        <h2><?= e($supplier['supplier_name']) ?></h2>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-info"><?= e($supplier['supplier_type'] ?? 'Other') ?></span>
            <?php if ((int)$supplier['is_active'] === 1): ?>
                <span class="badge badge-success">Active Supplier</span>
            <?php else: ?>
                <span class="badge badge-danger">Deactivated Supplier</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 220px;">Supplier ID</th>
                        <td>#<?= (int) $supplier['supplier_id'] ?></td>
                    </tr>
                    <tr>
                        <th>Supplier / Company</th>
                        <td><strong><?= e($supplier['supplier_name']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Material / Service Type</th>
                        <td><span class="badge badge-info"><?= e($supplier['supplier_type'] ?? 'Other') ?></span></td>
                    </tr>
                    <tr>
                        <th>Contact Person</th>
                        <td><?= e($supplier['contact_person'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td>
                            <?php if (!empty($supplier['email'])): ?>
                                <a href="mailto:<?= e($supplier['email']) ?>"><?= e($supplier['email']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Phone Number</th>
                        <td>
                            <?php if (!empty($supplier['phone'])): ?>
                                <a href="tel:<?= e($supplier['phone']) ?>"><?= e($supplier['phone']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Country</th>
                        <td><?= e($supplier['country'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th>Lead Time</th>
                        <td>
                            <?php if ($supplier['lead_time_days'] !== null): ?>
                                <strong><?= (int) $supplier['lead_time_days'] ?></strong> days
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Postal / Mill Address</th>
                        <td>
                            <?php if (!empty($supplier['address'])): ?>
                                <?= nl2br(e($supplier['address'])) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Notes &amp; Certifications</th>
                        <td>
                            <?php if (!empty($supplier['notes'])): ?>
                                <?= nl2br(e($supplier['notes'])) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php if ((int)$supplier['is_active'] === 1): ?>
                                <span class="badge badge-success">Active</span>
                                <span class="text-muted text-sm ml-2">Available for material purchase orders &amp; batch allocation</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                                <span class="text-muted text-sm ml-2">Suspended from new purchase orders</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Record Created</th>
                        <td><?= e($supplier['created_at']) ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= e($supplier['updated_at']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
