<?php
/**
 * ThreadFlow - Record Stage Progress Form
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

$pageTitle = 'Record Stage Progress';
$currentNav = 'stage_progress';

// Retrieve and clear flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old    = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Check for preselected batch from query param if old input is not set
$preselectedBatchId = isset($old['batch_id']) ? (string)$old['batch_id'] : (string)(filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT) ?? '');

// Fetch available production batches
$batches = [];
$stages  = [];

try {
    $batchSql = "SELECT pb.batch_id, pb.batch_number, pb.planned_quantity, pb.status AS batch_status,
                        bo.order_number, bo.style_number, b.buyer_name,
                        sl.line_name, sl.line_code
                 FROM production_batches pb
                 INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
                 INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
                 LEFT JOIN sewing_lines sl ON pb.line_id = sl.line_id
                 ORDER BY pb.batch_id DESC";
    $bRes = $conn->query($batchSql);
    if ($bRes) {
        $batches = $bRes->fetch_all(MYSQLI_ASSOC);
    }

    $stageSql = "SELECT stage_id, stage_name, stage_code, sequence_number 
                 FROM production_stages 
                 WHERE is_active = 1 
                 ORDER BY sequence_number ASC";
    $sRes = $conn->query($stageSql);
    if ($sRes) {
        $stages = $sRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading batches/stages for create stage progress: " . $e->getMessage());
}

$statuses = ['In Progress', 'On Hold', 'Completed'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Record Stage Progress</h1>
        <div class="page-subtitle">Module 3 &bull; Ownership: Member 3 &bull; Access: Admin, Production Manager</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/stage_progress/index.php')) ?>" class="btn btn-secondary">Back to Progress History</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>New Stage Progress Entry</h2>
    </div>
    <div class="card-body">
        <?php if (empty($batches)): ?>
            <div class="alert alert-warning mb-4">
                <strong>Prerequisite Missing:</strong> No production batches were found in the database.
                A stage progress record requires an existing production batch.
                Please create a batch in <a href="<?= e(url('modules/production_batches/create.php')) ?>">Production Batches</a> first.
            </div>
        <?php endif; ?>

        <?php if (empty($stages)): ?>
            <div class="alert alert-warning mb-4">
                <strong>Prerequisite Missing:</strong> No active production stages found in <code>production_stages</code>.
                Please ensure stages are configured or import <code>sql/stage_progress.sql</code>.
            </div>
        <?php endif; ?>

        <form action="<?= e(url('modules/stage_progress/store.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <!-- Production Batch Selection -->
            <div class="form-group">
                <label for="batch_id" class="form-label">Production Batch <span class="required">*</span></label>
                <select id="batch_id"
                        name="batch_id"
                        class="form-control <?= isset($errors['batch_id']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Production Batch --</option>
                    <?php foreach ($batches as $b): ?>
                        <?php
                        $selected = ($preselectedBatchId === (string)$b['batch_id']) ? 'selected' : '';
                        ?>
                        <option value="<?= (int) $b['batch_id'] ?>" <?= $selected ?>>
                            <?= e($b['batch_number']) ?> &bull; <?= e($b['buyer_name']) ?> &bull; Order <?= e($b['order_number']) ?> (Planned: <?= number_format((int)$b['planned_quantity']) ?> pcs)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['batch_id'])): ?>
                    <span class="form-error"><?= e($errors['batch_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Mandatory batch for which this stage progress is being tracked.</span>
            </div>

            <!-- Production Stage Selection -->
            <div class="form-group">
                <label for="stage_id" class="form-label">Production Stage <span class="required">*</span></label>
                <select id="stage_id"
                        name="stage_id"
                        class="form-control <?= isset($errors['stage_id']) ? 'has-error' : '' ?>"
                        required>
                    <option value="">-- Select Production Stage --</option>
                    <?php foreach ($stages as $s): ?>
                        <?php
                        $selected = (isset($old['stage_id']) && (string)$old['stage_id'] === (string)$s['stage_id']) ? 'selected' : '';
                        ?>
                        <option value="<?= (int) $s['stage_id'] ?>" <?= $selected ?>>
                            Stage <?= (int) $s['sequence_number'] ?>: <?= e($s['stage_name']) ?> (<?= e($s['stage_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['stage_id'])): ?>
                    <span class="form-error"><?= e($errors['stage_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Chronological factory stage. Later stages require preceding stages to be completed.</span>
            </div>

            <!-- Quantities Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="input_quantity" class="form-label">Input Quantity (Pieces) <span class="required">*</span></label>
                    <input type="number"
                           id="input_quantity"
                           name="input_quantity"
                           class="form-control <?= isset($errors['input_quantity']) ? 'has-error' : '' ?>"
                           min="1"
                           step="1"
                           required
                           value="<?= e($old['input_quantity'] ?? '') ?>"
                           placeholder="e.g., 1200">
                    <?php if (isset($errors['input_quantity'])): ?>
                        <span class="form-error"><?= e($errors['input_quantity']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Must be a whole number > 0. Cannot exceed batch planned qty or preceding stage completed output.</span>
                </div>

                <div class="form-group">
                    <label for="output_quantity" class="form-label">Output Quantity (Pieces) <span class="required">*</span></label>
                    <input type="number"
                           id="output_quantity"
                           name="output_quantity"
                           class="form-control <?= isset($errors['output_quantity']) ? 'has-error' : '' ?>"
                           min="0"
                           step="1"
                           required
                           value="<?= e($old['output_quantity'] ?? '0') ?>"
                           placeholder="e.g., 1150">
                    <?php if (isset($errors['output_quantity'])): ?>
                        <span class="form-error"><?= e($errors['output_quantity']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Must be >= 0 and cannot exceed the input quantity.</span>
                </div>
            </div>

            <!-- Status Selection -->
            <div class="form-group">
                <label for="status" class="form-label">Stage Status <span class="required">*</span></label>
                <select id="status"
                        name="status"
                        class="form-control <?= isset($errors['status']) ? 'has-error' : '' ?>"
                        required>
                    <?php foreach ($statuses as $st): ?>
                        <?php
                        $selected = (($old['status'] ?? 'In Progress') === $st) ? 'selected' : '';
                        ?>
                        <option value="<?= e($st) ?>" <?= $selected ?>>
                            <?= e($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?>
                    <span class="form-error"><?= e($errors['status']) ?></span>
                <?php endif; ?>
                <span class="form-text">'Completed' status requires a valid End Date &amp; Time.</span>
            </div>

            <!-- Timestamps Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="start_time" class="form-label">Start Date &amp; Time <span class="required">*</span></label>
                    <input type="datetime-local"
                           id="start_time"
                           name="start_time"
                           class="form-control <?= isset($errors['start_time']) ? 'has-error' : '' ?>"
                           required
                           value="<?= e($old['start_time'] ?? date('Y-m-d\TH:i')) ?>">
                    <?php if (isset($errors['start_time'])): ?>
                        <span class="form-error"><?= e($errors['start_time']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Exact timestamp when this stage commenced operation.</span>
                </div>

                <div class="form-group">
                    <label for="end_time" class="form-label">End Date &amp; Time <span class="text-muted">(Required if Completed)</span></label>
                    <input type="datetime-local"
                           id="end_time"
                           name="end_time"
                           class="form-control <?= isset($errors['end_time']) ? 'has-error' : '' ?>"
                           value="<?= e($old['end_time'] ?? '') ?>">
                    <?php if (isset($errors['end_time'])): ?>
                        <span class="form-error"><?= e($errors['end_time']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Timestamp when stage concluded. Cannot be earlier than Start Time.</span>
                </div>
            </div>

            <!-- Remarks -->
            <div class="form-group">
                <label for="remarks" class="form-label">Remarks / Process Notes <span class="text-muted">(Optional)</span></label>
                <textarea id="remarks"
                          name="remarks"
                          class="form-control <?= isset($errors['remarks']) ? 'has-error' : '' ?>"
                          rows="3"
                          maxlength="1000"
                          placeholder="e.g., Cutting completed with normal 4.1% process loss, ready for sewing handover..."><?= e($old['remarks'] ?? '') ?></textarea>
                <?php if (isset($errors['remarks'])): ?>
                    <span class="form-error"><?= e($errors['remarks']) ?></span>
                <?php endif; ?>
                <span class="form-text">Optional operational notes, quality observations, or process loss justification (max 1000 characters).</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= (empty($batches) || empty($stages)) ? 'disabled' : '' ?>>
                    Save Stage Progress
                </button>
                <a href="<?= e(url('modules/stage_progress/index.php')) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
