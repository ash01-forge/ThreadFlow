<?php
/**
 * ThreadFlow - Buyer Details View
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

// Positive integer ID validation
$buyerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$buyerId || $buyerId <= 0) {
    flash_set('error', 'Invalid buyer ID specified.');
    redirect('modules/buyers/index.php');
}

// Fetch buyer details safely using prepared statements
try {
    $stmt = $conn->prepare("SELECT buyer_id, buyer_name, contact_person, email, phone, country, address, is_active, created_at, updated_at FROM buyers WHERE buyer_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for buyer details: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving buyer details.');
        redirect('modules/buyers/index.php');
    }

    $stmt->bind_param("i", $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $buyer = $result->fetch_assoc();
    $stmt->close();

    // Safe handling of missing records
    if (!$buyer) {
        flash_set('error', 'Buyer record not found.');
        redirect('modules/buyers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching buyer details: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving buyer details.');
    redirect('modules/buyers/index.php');
}

$pageTitle = 'Buyer: ' . $buyer['buyer_name'];
$currentNav = 'buyers';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Buyer Profile</h1>
        <div class="page-subtitle">Buyer ID #<?= (int) $buyer['buyer_id'] ?> &bull; <?= e($buyer['buyer_name']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Back to Buyer List</a>
        <a href="<?= e(url('modules/buyers/edit.php?id=' . (int)$buyer['buyer_id'])) ?>" class="btn btn-secondary">Edit Profile</a>
        <form action="<?= e(url('modules/buyers/toggle_status.php')) ?>" method="post" style="display: contents;">
            <?= csrf_field() ?>
            <input type="hidden" name="buyer_id" value="<?= (int) $buyer['buyer_id'] ?>">
            <input type="hidden" name="from_view" value="1">
            <?php if ((int)$buyer['is_active'] === 1): ?>
                <button type="submit" class="btn btn-danger" data-confirm="Are you sure you want to deactivate this buyer?">
                    Deactivate Buyer
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary" data-confirm="Are you sure you want to reactivate this buyer?">
                    Reactivate Buyer
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card card-medium">
    <div class="card-header">
        <h2><?= e($buyer['buyer_name']) ?></h2>
        <div>
            <?php if ((int)$buyer['is_active'] === 1): ?>
                <span class="badge badge-success">Active Account</span>
            <?php else: ?>
                <span class="badge badge-danger">Deactivated Account</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 200px;">Buyer ID</th>
                        <td>#<?= (int) $buyer['buyer_id'] ?></td>
                    </tr>
                    <tr>
                        <th>Buyer Name</th>
                        <td><strong><?= e($buyer['buyer_name']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Contact Person</th>
                        <td><?= e($buyer['contact_person'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td>
                            <?php if (!empty($buyer['email'])): ?>
                                <a href="mailto:<?= e($buyer['email']) ?>"><?= e($buyer['email']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Phone Number</th>
                        <td>
                            <?php if (!empty($buyer['phone'])): ?>
                                <a href="tel:<?= e($buyer['phone']) ?>"><?= e($buyer['phone']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Country</th>
                        <td><?= e($buyer['country'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th>Physical / Mailing Address</th>
                        <td>
                            <?php if (!empty($buyer['address'])): ?>
                                <?= nl2br(e($buyer['address'])) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php if ((int)$buyer['is_active'] === 1): ?>
                                <span class="badge badge-success">Active</span>
                                <span class="text-muted text-sm ml-2">Ready for merchandising and production assignment</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                                <span class="text-muted text-sm ml-2">Archived from new assignments</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Record Created</th>
                        <td><?= e($buyer['created_at']) ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= e($buyer['updated_at']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
