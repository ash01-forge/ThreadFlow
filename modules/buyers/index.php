<?php
/**
 * ThreadFlow - Buyer Management Directory
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

$pageTitle = 'Buyer Management';
$currentNav = 'buyers';

// Extract and sanitize search and filter query parameters
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

// Build query with prepared statements
$whereClauses = [];
$wildcard = '%' . $search . '%';
$statusVal = ($statusFilter === 'active') ? 1 : (($statusFilter === 'inactive') ? 0 : null);

// Search across buyer_name, contact_person, and email
if ($search !== '') {
    $whereClauses[] = "(buyer_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
}

// Filter by active status
if ($statusVal !== null) {
    $whereClauses[] = "is_active = ?";
}

$sql = "SELECT buyer_id, buyer_name, contact_person, email, phone, country, is_active, created_at, updated_at FROM buyers";
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY buyer_id DESC";

$buyers = [];
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare buyers index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching the buyer list.');
    } else {
        if ($search !== '' && $statusVal !== null) {
            $stmt->bind_param("sssi", $wildcard, $wildcard, $wildcard, $statusVal);
        } elseif ($search !== '') {
            $stmt->bind_param("sss", $wildcard, $wildcard, $wildcard);
        } elseif ($statusVal !== null) {
            $stmt->bind_param("i", $statusVal);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $buyers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching buyers: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading buyers.');
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Buyer Management</h1>
        <div class="page-subtitle">Module 1 &bull; Ownership: Member 1 &bull; Access: Admin, Merchandiser</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/buyers/create.php')) ?>" class="btn btn-primary">Add New Buyer</a>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/buyers/index.php')) ?>" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search Buyers</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Search by name, contact person, or email...">
            </div>

            <div class="filter-field">
                <label for="status" class="form-label">Status Filter</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                    <a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Buyer Directory Table -->
<div class="card">
    <div class="card-header">
        <h2>Buyer Directory</h2>
        <span class="badge badge-muted"><?= count($buyers) ?> Total Result(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($buyers)): ?>
            <div class="empty-state">
                <p>No buyers found matching your criteria.</p>
                <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                    <a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                <?php else: ?>
                    <a href="<?= e(url('modules/buyers/create.php')) ?>" class="btn btn-primary btn-sm">Add Your First Buyer</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th>Buyer Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Country</th>
                            <th class="col-status">Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buyers as $b): ?>
                            <tr>
                                <td>#<?= (int) $b['buyer_id'] ?></td>
                                <td>
                                    <strong>
                                        <a href="<?= e(url('modules/buyers/view.php?id=' . (int)$b['buyer_id'])) ?>">
                                            <?= e($b['buyer_name']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?= e($b['contact_person'] ?: '—') ?></td>
                                <td>
                                    <?php if (!empty($b['email'])): ?>
                                        <a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($b['phone'] ?: '—') ?></td>
                                <td><?= e($b['country'] ?: '—') ?></td>
                                <td class="col-status">
                                    <?php if ((int)$b['is_active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <div class="row-actions">
                                        <a href="<?= e(url('modules/buyers/view.php?id=' . (int)$b['buyer_id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                                        <a href="<?= e(url('modules/buyers/edit.php?id=' . (int)$b['buyer_id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <form action="<?= e(url('modules/buyers/toggle_status.php')) ?>" method="post" style="display: contents;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="buyer_id" value="<?= (int) $b['buyer_id'] ?>">
                                            <?php if ((int)$b['is_active'] === 1): ?>
                                                <button type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        data-confirm="Are you sure you want to deactivate this buyer?">
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button type="submit"
                                                        class="btn btn-sm btn-primary"
                                                        data-confirm="Are you sure you want to reactivate this buyer?">
                                                    Reactivate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
