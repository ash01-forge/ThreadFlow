<?php
/**
 * ThreadFlow - Add Production Batch Form
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Add Production Batch';
$currentNav = 'batches';

// Retrieve and clear flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Fetch active buyer orders joined with buyers
$orders = [];
$lines = [];

try {
    $orderQuery = "SELECT bo.order_id, bo.order_number, bo.style_number, bo.total_quantity, b.buyer_name 
                   FROM buyer_orders bo 
                   INNER JOIN buyers b ON bo.buyer_id = b.buyer_id 
                   WHERE bo.status != 'Cancelled' AND b.is_active = 1 
                   ORDER BY bo.order_id DESC";
    $orderRes = $conn->query($orderQuery);
    if ($orderRes) {
        $orders = $orderRes->fetch_all(MYSQLI_ASSOC);
    }

    $lineQuery = "SELECT line_id, line_name, line_code, daily_capacity 
                  FROM sewing_lines 
                  WHERE is_active = 1 
                  ORDER BY line_name ASC";
    $lineRes = $conn->query($lineQuery);
    if ($lineRes) {
        $lines = $lineRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading orders/lines for create batch: " . $e->getMessage());
}

$statuses = ['Planned', 'In Progress', 'On Hold', 'Completed'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Add Production Batch</h1>
        <div class="page-subtitle">Module 3 &bull; Ownership: Member 3 &bull; Access: Admin, Production Manager</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-secondary">Back to Batch List</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>New Production Batch Details</h2>
    </div>
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <div class="alert alert-warning mb-4">
                <strong>Prerequisite Missing:</strong> No active buyer orders were found in the database.
                A production batch requires an existing buyer order.
                Please ensure orders are present in <code>buyer_orders</code> (or import <code>sql/production_batches.sql</code> seed records).
            </div>
        <?php endif; ?>

        <form action="<?= e(url('modules/production_batches/store.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="batch_number" class="form-label">Batch Number <span class="required">*</span></label>
                <input type="text"
                       id="batch_number"
                       name="batch_number"
                       class="form-control <?= isset($errors['batch_number']) ? 'has-error' : '' ?>"
                       maxlength="50"
                       required
                       value="<?= e($old['batch_number'] ?? '') ?>"
                       placeholder="e.g., BATCH-2026-004">
                <?php if (isset($errors['batch_number'])): ?>
                    <span class="form-error"><?= e($errors['batch_number']) ?></span>
                <?php endif; ?>
                <span class="form-text">Unique tracking identifier for this production lot (letters, numbers, hyphens).</span>
            </div>

            <div class="form-group">
                <label for="order_id" class="form-label">Buyer Order <span class="required">*</span></label>
                <select id="order_id"
                        name="order_id"
                        class="form-control <?= isset($errors['order_id']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Buyer Order --</option>
                    <?php foreach ($orders as $ord): ?>
                        <?php $selected = (isset($old['order_id']) && (int)$old['order_id'] === (int)$ord['order_id']) ? 'selected' : ''; ?>
                        <option value="<?= (int) $ord['order_id'] ?>" <?= $selected ?>>
                            <?= e($ord['order_number']) ?> &bull; <?= e($ord['buyer_name']) ?> (Style: <?= e($ord['style_number']) ?>, Total: <?= number_format((int)$ord['total_quantity']) ?> pcs)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['order_id'])): ?>
                    <span class="form-error"><?= e($errors['order_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Mandatory purchase order from which this batch is spawned.</span>
            </div>

            <div class="form-group">
                <label for="line_id" class="form-label">Sewing Line Allocation <span class="text-muted">(Optional)</span></label>
                <select id="line_id"
                        name="line_id"
                        class="form-control <?= isset($errors['line_id']) ? 'has-error' : '' ?>">
                    <option value="">-- None / Unassigned --</option>
                    <?php foreach ($lines as $ln): ?>
                        <?php $selected = (isset($old['line_id']) && (string)$old['line_id'] === (string)$ln['line_id']) ? 'selected' : ''; ?>
                        <option value="<?= (int) $ln['line_id'] ?>" <?= $selected ?>>
                            <?= e($ln['line_name']) ?> (<?= e($ln['line_code']) ?> &bull; <?= number_format((int)$ln['daily_capacity']) ?> pcs/day)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['line_id'])): ?>
                    <span class="form-error"><?= e($errors['line_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Assigned factory floor sewing line if scheduled, or leave unassigned.</span>
            </div>

            <div class="form-group">
                <label for="planned_quantity" class="form-label">Planned Quantity (Pieces) <span class="required">*</span></label>
                <input type="number"
                       id="planned_quantity"
                       name="planned_quantity"
                       class="form-control <?= isset($errors['planned_quantity']) ? 'has-error' : '' ?>"
                       min="1"
                       step="1"
                       required
                       value="<?= e($old['planned_quantity'] ?? '') ?>"
                       placeholder="e.g., 1500">
                <?php if (isset($errors['planned_quantity'])): ?>
                    <span class="form-error"><?= e($errors['planned_quantity']) ?></span>
                <?php endif; ?>
                <span class="form-text">Must be a whole number greater than zero.</span>
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Batch Status <span class="required">*</span></label>
                <select id="status"
                        name="status"
                        class="form-control <?= isset($errors['status']) ? 'has-error' : '' ?>"
                        required>
                    <?php foreach ($statuses as $st): ?>
                        <?php 
                        $currentSelected = ($old['status'] ?? 'Planned') === $st ? 'selected' : ''; 
                        ?>
                        <option value="<?= e($st) ?>" <?= $currentSelected ?>>
                            <?= e($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?>
                    <span class="form-error"><?= e($errors['status']) ?></span>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="start_date" class="form-label">Start Date <span class="required">*</span></label>
                    <input type="date"
                           id="start_date"
                           name="start_date"
                           class="form-control <?= isset($errors['start_date']) ? 'has-error' : '' ?>"
                           required
                           value="<?= e($old['start_date'] ?? date('Y-m-d')) ?>">
                    <?php if (isset($errors['start_date'])): ?>
                        <span class="form-error"><?= e($errors['start_date']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="target_end_date" class="form-label">Target End Date <span class="required">*</span></label>
                    <input type="date"
                           id="target_end_date"
                           name="target_end_date"
                           class="form-control <?= isset($errors['target_end_date']) ? 'has-error' : '' ?>"
                           required
                           value="<?= e($old['target_end_date'] ?? date('Y-m-d', strtotime('+14 days'))) ?>">
                    <?php if (isset($errors['target_end_date'])): ?>
                        <span class="form-error"><?= e($errors['target_end_date']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Production Notes / Floor Routing</label>
                <textarea id="notes"
                          name="notes"
                          class="form-control <?= isset($errors['notes']) ? 'has-error' : '' ?>"
                          rows="3"
                          maxlength="1000"
                          placeholder="Special instructions, trims, bundling notes, or cutting instructions..."><?= e($old['notes'] ?? '') ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <span class="form-error"><?= e($errors['notes']) ?></span>
                <?php endif; ?>
                <span class="form-text">Optional technical or operational instructions (maximum 1000 characters).</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= empty($orders) ? 'disabled' : '' ?>>Save Batch</button>
                <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
