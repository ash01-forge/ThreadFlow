<?php
/**
 * ThreadFlow - Edit Stage Progress Form
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Validate positive integer ID
$progressId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$progressId || $progressId <= 0) {
    flash_set('error', 'Invalid stage progress record ID specified.');
    redirect('modules/stage_progress/index.php');
}

// Fetch existing stage progress record
$record = null;
try {
    $stmt = $conn->prepare("SELECT 
                                sp.progress_id,
                                sp.batch_id,
                                sp.stage_id,
                                sp.input_quantity,
                                sp.output_quantity,
                                sp.start_time,
                                sp.end_time,
                                sp.status,
                                sp.remarks,
                                pb.batch_number,
                                pb.planned_quantity,
                                ps.stage_name,
                                ps.stage_code,
                                ps.sequence_number
                            FROM stage_progress sp
                            INNER JOIN production_batches pb ON sp.batch_id = pb.batch_id
                            INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id
                            WHERE sp.progress_id = ?
                            LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for edit stage progress: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving the progress record.');
        redirect('modules/stage_progress/index.php');
    }

    $stmt->bind_param("i", $progressId);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();

    if (!$record) {
        flash_set('error', 'Stage progress record not found.');
        redirect('modules/stage_progress/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching stage progress for edit: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving the progress record.');
    redirect('modules/stage_progress/index.php');
}

$pageTitle = 'Edit Stage Progress: ' . $record['stage_name'] . ' (' . $record['batch_number'] . ')';
$currentNav = 'stage_progress';

// Retrieve and clear flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old    = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Helper to determine field values (old submitted input overrides DB record)
$getValue = function (string $field) use ($old, $record): string {
    if (isset($old[$field])) {
        return (string) $old[$field];
    }
    return (string) ($record[$field] ?? '');
};

$getDateTimeValue = function (string $field) use ($old, $record): string {
    if (isset($old[$field])) {
        return (string) $old[$field];
    }
    if (!empty($record[$field])) {
        return date('Y-m-d\TH:i', strtotime($record[$field]));
    }
    return '';
};

// Fetch available batches and stages
$batches = [];
$stages  = [];

try {
    $batchSql = "SELECT pb.batch_id, pb.batch_number, pb.planned_quantity,
                        bo.order_number, bo.style_number, b.buyer_name
                 FROM production_batches pb
                 INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
                 INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
                 ORDER BY pb.batch_id DESC";
    $bRes = $conn->query($batchSql);
    if ($bRes) {
        $batches = $bRes->fetch_all(MYSQLI_ASSOC);
    }

    $stageSql = "SELECT stage_id, stage_name, stage_code, sequence_number 
                 FROM production_stages 
                 WHERE is_active = 1 OR stage_id = " . (int)$record['stage_id'] . "
                 ORDER BY sequence_number ASC";
    $sRes = $conn->query($stageSql);
    if ($sRes) {
        $stages = $sRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception loading batches/stages for edit stage progress: " . $e->getMessage());
}

$statuses = ['In Progress', 'On Hold', 'Completed'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Stage Progress</h1>
        <div class="page-subtitle">
            Progress #<?= (int)$record['progress_id'] ?> &bull; 
            Stage <?= (int)$record['sequence_number'] ?>: <?= e($record['stage_name']) ?> &bull; 
            Batch <?= e($record['batch_number']) ?>
        </div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/stage_progress/view.php?id=' . (int)$record['progress_id'])) ?>" class="btn btn-secondary">View Record</a>
        <a href="<?= e(url('modules/stage_progress/index.php')) ?>" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div class="card card-form">
    <div class="card-header">
        <h2>Edit Stage Progress Details</h2>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/stage_progress/update.php')) ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="progress_id" value="<?= (int) $record['progress_id'] ?>">

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
                        $selected = ((string)$b['batch_id'] === $getValue('batch_id')) ? 'selected' : '';
                        ?>
                        <option value="<?= (int) $b['batch_id'] ?>" <?= $selected ?>>
                            <?= e($b['batch_number']) ?> &bull; <?= e($b['buyer_name']) ?> &bull; Order <?= e($b['order_number']) ?> (Planned: <?= number_format((int)$b['planned_quantity']) ?> pcs)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['batch_id'])): ?>
                    <span class="form-error"><?= e($errors['batch_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Mandatory batch for which this stage progress is tracked.</span>
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
                        $selected = ((string)$s['stage_id'] === $getValue('stage_id')) ? 'selected' : '';
                        ?>
                        <option value="<?= (int) $s['stage_id'] ?>" <?= $selected ?>>
                            Stage <?= (int) $s['sequence_number'] ?>: <?= e($s['stage_name']) ?> (<?= e($s['stage_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['stage_id'])): ?>
                    <span class="form-error"><?= e($errors['stage_id']) ?></span>
                <?php endif; ?>
                <span class="form-text">Chronological factory stage.</span>
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
                           value="<?= e($getValue('input_quantity')) ?>"
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
                           value="<?= e($getValue('output_quantity')) ?>"
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
                <span class="form-text">'Completed' status requires an End Date &amp; Time.</span>
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
                           value="<?= e($getDateTimeValue('start_time')) ?>">
                    <?php if (isset($errors['start_time'])): ?>
                        <span class="form-error"><?= e($errors['start_time']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Exact timestamp when this stage began operation.</span>
                </div>

                <div class="form-group">
                    <label for="end_time" class="form-label">End Date &amp; Time <span class="text-muted">(Required if Completed)</span></label>
                    <input type="datetime-local"
                           id="end_time"
                           name="end_time"
                           class="form-control <?= isset($errors['end_time']) ? 'has-error' : '' ?>"
                           value="<?= e($getDateTimeValue('end_time')) ?>">
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
                          placeholder="Operational notes, yield observations, or process adjustments..."><?= e($getValue('remarks')) ?></textarea>
                <?php if (isset($errors['remarks'])): ?>
                    <span class="form-error"><?= e($errors['remarks']) ?></span>
                <?php endif; ?>
                <span class="form-text">Optional operational notes (maximum 1000 characters).</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Stage Progress</button>
                <a href="<?= e(url('modules/stage_progress/view.php?id=' . (int)$record['progress_id'])) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
