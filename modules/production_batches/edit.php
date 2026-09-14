<?php
/**
 * ThreadFlow - Edit Production Batch Form
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Validate positive integer ID
$batchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$batchId || $batchId <= 0) {
    flash_set('error', 'Invalid production batch ID specified.');
    redirect('modules/production_batches/index.php');
}

// Fetch existing batch
$batch = null;
try {
    $stmt = $conn->prepare("SELECT batch_id, batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes FROM production_batches WHERE batch_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for edit batch: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving the batch record.');
        redirect('modules/production_batches/index.php');
    }

    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $batch = $result->fetch_assoc();
    $stmt->close();

    if (!$batch) {
        flash_set('error', 'Production batch record not found.');
        redirect('modules/production_batches/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching batch for edit: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving the batch record.');
    redirect('modules/production_batches/index.php');
}

$pageTitle = 'Edit Batch: ' . $batch['batch_number'];
$currentNav = 'batches';

// Retrieve and clear flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Helper to determine form field values (old submitted input overrides DB record)
$getValue = function (string $field) use ($old, $batch): string {
    if (isset($old[$field])) {
        return (string) $old[$field];
    }
    return (string) ($batch[$field] ?? '');
};

// Fetch orders (include the currently assigned order even if inactive)
$orders = [];
$lines = [];

try {
    $orderStmt = $conn->prepare("SELECT bo.order_id, bo.order_number, bo.style_number, bo.total_quantity, b.buyer_name 
                                 FROM buyer_orders bo 
                                 INNER JOIN buyers b ON bo.buyer_id = b.buyer_id 
                                 WHERE (bo.status != 'Cancelled' AND b.is_active = 1) OR bo.order_id = ?
                                 ORDER BY bo.order_id DESC");
    if ($orderStmt) {
        $orderStmt->bind_param("i", $batch['order_id']);
        $orderStmt->execute();
        $orders = $orderStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $orderStmt->close();
    }

    $currentLineId = (int) ($batch['line_id'] ?? 0);
    $lineStmt = $conn->prepare("SELECT line_id, line_name, line_code, daily_capacity 
                                FROM sewing_lines 
                                WHERE is_active = 1 OR line_id = ?
                                ORDER BY line_name ASC");
    if ($lineStmt) {
        $lineStmt->bind_param("i", $currentLineId);
        $lineStmt->execute();
        $lines = $lineStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $lineStmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading orders/lines for edit batch: " . $e->getMessage());
}

$statuses = ['Planned', 'In Progress', 'On Hold', 'Completed'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Production Batch</h1>
        <div class="page-subtitle">Batch #<?= (int) $batch['batch_id'] ?> &bull; <?= e($batch['batch_number']) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$batch['batch_id'])) ?>" class="btn btn-secondary">View Batch</a>
        <a href="<?= e(url('modules/production_batches/index.php')) ?>" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>Edit Batch Details</h2>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/production_batches/update.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="batch_id" value="<?= (int) $batch['batch_id'] ?>">

            <div class="form-group">
                <label for="batch_number" class="form-label">Batch Number <span class="required">*</span></label>
                <input type="text"
                       id="batch_number"
                       name="batch_number"
                       class="form-control <?= isset($errors['batch_number']) ? 'has-error' : '' ?>"
                       maxlength="50"
                       required
                       value="<?= e($getValue('batch_number')) ?>"
                       placeholder="e.g., BATCH-2026-004">
                <?php if (isset($errors['batch_number'])): ?>
                    <span class="form-error"><?= e($errors['batch_number']) ?></span>
                <?php endif; ?>
                <span class="form-text">Unique tracking identifier for this production lot.</span>
            </div>

            <div class="form-group">
                <label for="order_id" class="form-label">Buyer Order <span class="required">*</span></label>
                <select id="order_id"
                        name="order_id"
                        class="form-control <?= isset($errors['order_id']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Buyer Order --</option>
                    <?php foreach ($orders as $ord): ?>
                        <?php 
                        $selected = ((string)$ord['order_id'] === $getValue('order_id')) ? 'selected' : ''; 
                        ?>
                        <option value="<?= (int) $ord['order_id'] ?>" <?= $selected ?>>
                            <?= e($ord['order_number']) ?> &bull; <?= e($ord['buyer_name']) ?> (Style: <?= e($ord['style_number']) ?>, Total: <?= number_format((int)$ord['total_quantity']) ?> pcs)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['order_id'])): ?>
                    <span class="form-error"><?= e($errors['order_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Mandatory buyer purchase order associated with this batch.</span>
            </div>

            <div class="form-group">
                <label for="line_id" class="form-label">Sewing Line Allocation <span class="text-muted">(Optional)</span></label>
                <select id="line_id"
                        name="line_id"
                        class="form-control <?= isset($errors['line_id']) ? 'has-error' : '' ?>">
                    <option value="">-- None / Unassigned --</option>
                    <?php foreach ($lines as $ln): ?>
                        <?php 
                        $selected = ((string)$ln['line_id'] === $getValue('line_id')) ? 'selected' : ''; 
                        ?>
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
                       value="<?= e($getValue('planned_quantity')) ?>"
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
                        $selected = ($getValue('status') === $st) ? 'selected' : ''; 
                        ?>
                        <option value="<?= e($st) ?>" <?= $selected ?>>
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
                           value="<?= e($getValue('start_date')) ?>">
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
                           value="<?= e($getValue('target_end_date')) ?>">
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
                          placeholder="Special instructions, trims, bundling notes, or cutting instructions..."><?= e($getValue('notes')) ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <span class="form-error"><?= e($errors['notes']) ?></span>
                <?php endif; ?>
                <span class="form-text">Optional technical or operational instructions (maximum 1000 characters).</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Batch</button>
                <a href="<?= e(url('modules/production_batches/view.php?id=' . (int)$batch['batch_id'])) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
