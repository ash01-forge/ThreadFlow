<?php
/**
 * ThreadFlow - Fabric Lot Dossier View
 * 
 * Module 2 (Feature 2): Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Positive integer ID validation
$lotId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$lotId || $lotId <= 0) {
    flash_set('error', 'Invalid fabric lot ID specified.');
    redirect('modules/fabric_receiving/index.php');
}

// Fetch fabric lot details and joined supplier information safely using prepared statements
try {
    $sql = "SELECT 
                fl.fabric_lot_id,
                fl.supplier_id,
                fl.lot_number,
                fl.fabric_type,
                fl.color,
                fl.received_quantity,
                fl.unit,
                fl.received_date,
                fl.inspection_status,
                fl.notes,
                fl.created_at,
                fl.updated_at,
                s.supplier_name,
                s.supplier_type,
                s.contact_person,
                s.email,
                s.phone,
                s.country,
                s.lead_time_days,
                s.is_active AS supplier_is_active
            FROM fabric_lots fl
            INNER JOIN suppliers s ON fl.supplier_id = s.supplier_id
            WHERE fl.fabric_lot_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare SELECT for fabric lot details: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving fabric lot details.');
        redirect('modules/fabric_receiving/index.php');
    }

    $stmt->bind_param("i", $lotId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lot = $result->fetch_assoc();
    $stmt->close();

    // Safe handling of missing records
    if (!$lot) {
        flash_set('error', 'Fabric lot record not found.');
        redirect('modules/fabric_receiving/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching fabric lot details: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving fabric lot details.');
    redirect('modules/fabric_receiving/index.php');
}

$pageTitle = 'Fabric Lot: ' . $lot['lot_number'];
$currentNav = 'fabric_receiving';

// Badge styling
$badgeClass = 'badge-muted';
if ($lot['inspection_status'] === 'Pending') $badgeClass = 'badge-warning';
elseif ($lot['inspection_status'] === 'Passed') $badgeClass = 'badge-success';
elseif ($lot['inspection_status'] === 'Failed') $badgeClass = 'badge-danger';
elseif ($lot['inspection_status'] === 'Conditionally Approved') $badgeClass = 'badge-info';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Fabric Receiving Dossier</h1>
        <div class="page-subtitle">Lot #<?= e($lot['lot_number']) ?> &bull; Record ID #<?= (int) $lot['fabric_lot_id'] ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/fabric_receiving/index.php')) ?>" class="btn btn-secondary">Back to Receiving List</a>
        <a href="<?= e(url('modules/fabric_receiving/edit.php?id=' . (int) $lot['fabric_lot_id'])) ?>" class="btn btn-secondary">Edit Lot Details</a>
        <a href="<?= e(url('modules/fabric_inspections/create.php?lot_id=' . (int) $lot['fabric_lot_id'])) ?>" class="btn btn-secondary">Inspect Lot</a>
        <a href="<?= e(url('modules/fabric_allocations/traceability.php?lot_id=' . (int) $lot['fabric_lot_id'])) ?>" class="btn btn-secondary">Trace Lot</a>
        <?php if (in_array($lot['inspection_status'], ['Passed', 'Conditionally Approved'], true)): ?>
            <a href="<?= e(url('modules/fabric_allocations/create.php?lot_id=' . (int) $lot['fabric_lot_id'])) ?>" class="btn btn-secondary">Allocate Fabric</a>
        <?php endif; ?>
        <a href="<?= e(url('modules/suppliers/view.php?id=' . (int) $lot['supplier_id'])) ?>" class="btn btn-primary">View Supplier Profile</a>
    </div>
</div>

<div class="card card-medium mb-4">
    <div class="card-header">
        <h2>Lot #<?= e($lot['lot_number']) ?></h2>
        <div class="d-flex align-items-center gap-2">
            <span class="badge <?= e($badgeClass) ?>"><?= e($lot['inspection_status']) ?></span>
            <span class="badge badge-muted"><?= number_format((float) $lot['received_quantity'], 2) ?> <?= e($lot['unit']) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 220px;">Fabric Lot ID</th>
                        <td>#<?= (int) $lot['fabric_lot_id'] ?></td>
                    </tr>
                    <tr>
                        <th>Lot / Batch Number</th>
                        <td><strong><?= e($lot['lot_number']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Fabric Type / Weave</th>
                        <td><strong><?= e($lot['fabric_type']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Color / Shade</th>
                        <td><?= e($lot['color'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th>Received Quantity</th>
                        <td>
                            <strong><?= number_format((float) $lot['received_quantity'], 2) ?></strong>
                            <span class="text-muted ml-1"><?= e($lot['unit']) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Received Date</th>
                        <td><?= e(date('F j, Y', strtotime($lot['received_date']))) ?></td>
                    </tr>
                    <tr>
                        <th>Inspection Status</th>
                        <td>
                            <span class="badge <?= e($badgeClass) ?>"><?= e($lot['inspection_status']) ?></span>
                            <?php if ($lot['inspection_status'] === 'Pending'): ?>
                                <span class="text-muted text-sm ml-2">Awaiting 4-point visual and lab inspection</span>
                            <?php elseif ($lot['inspection_status'] === 'Passed'): ?>
                                <span class="text-muted text-sm ml-2">Approved for cutting and production allocation</span>
                            <?php elseif ($lot['inspection_status'] === 'Conditionally Approved'): ?>
                                <span class="text-muted text-sm ml-2">Approved for secondary garments with variances noted</span>
                            <?php elseif ($lot['inspection_status'] === 'Failed'): ?>
                                <span class="text-muted text-sm ml-2">Rejected during QC; quarantined from production</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Receiving Notes</th>
                        <td>
                            <?php if (!empty($lot['notes'])): ?>
                                <?= nl2br(e($lot['notes'])) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Audit Traceability</th>
                        <td>
                            <span class="text-sm text-muted">
                                <em>Receiving records are retained permanently to ensure complete backward traceability for garments, cutting lines, and quality inspections.</em>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Date Received (Recorded)</th>
                        <td><?= e($lot['created_at']) ?></td>
                    </tr>
                    <tr>
                        <th>Last Modified</th>
                        <td><?= e($lot['updated_at']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Joined Supplier Profile Card -->
<div class="card card-medium">
    <div class="card-header">
        <h3>Supplier Information</h3>
        <div>
            <?php if ((int) $lot['supplier_is_active'] === 1): ?>
                <span class="badge badge-success">Active Supplier</span>
            <?php else: ?>
                <span class="badge badge-danger">Inactive Supplier</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 220px;">Supplier / Mill Name</th>
                        <td>
                            <strong>
                                <a href="<?= e(url('modules/suppliers/view.php?id=' . (int) $lot['supplier_id'])) ?>">
                                    <?= e($lot['supplier_name']) ?>
                                </a>
                            </strong>
                            <span class="badge badge-info ml-2"><?= e($lot['supplier_type'] ?? 'Fabric') ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Contact Person</th>
                        <td><?= e($lot['contact_person'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td>
                            <?php if (!empty($lot['email'])): ?>
                                <a href="mailto:<?= e($lot['email']) ?>"><?= e($lot['email']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Phone Number</th>
                        <td>
                            <?php if (!empty($lot['phone'])): ?>
                                <a href="tel:<?= e($lot['phone']) ?>"><?= e($lot['phone']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Country</th>
                        <td><?= e($lot['country'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th>Procurement Lead Time</th>
                        <td>
                            <?php if ($lot['lead_time_days'] !== null): ?>
                                <?= (int) $lot['lead_time_days'] ?> days
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
echo '<link rel="stylesheet" href="modern-ui.css">';
include __DIR__ . '/../../includes/footer.php';
