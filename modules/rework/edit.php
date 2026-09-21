<?php
/**
 * ThreadFlow - Edit Rework Job Form
 *
 * Module 4: Member 4 implementation.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Edit Rework Job';
$currentNav = 'rework';

$currentUser = auth_user();

// Validate rework ID from query string
$reworkId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$reworkId || $reworkId <= 0) {
    flash_set('error', 'Invalid Rework Job ID.');
    redirect('modules/rework/index.php');
}

// Fetch existing rework job and related source defect details
$rework = null;
$sourceDefect = null;
try {
    $stmt = $conn->prepare(
        "SELECT rj.*, idf.defect_count, qi.inspection_id, qi.inspection_number, qi.inspection_date, qi.inspection_stage,\n                pb.batch_id, pb.batch_number, bo.order_number, bo.style_number, b.buyer_name,\n                dt.defect_id, dt.defect_code, dt.defect_name, dt.category AS defect_category, dt.severity AS defect_severity, idf.notes AS defect_notes\n         FROM rework_jobs rj\n         INNER JOIN inspection_defects idf ON rj.inspection_defect_id = idf.inspection_defect_id\n         INNER JOIN quality_inspections qi ON idf.inspection_id = qi.inspection_id\n         INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id\n         INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id\n         INNER JOIN buyers b ON bo.buyer_id = b.buyer_id\n         INNER JOIN defect_types dt ON idf.defect_id = dt.defect_id\n         WHERE rj.rework_id = ?\n         LIMIT 1"
    );
    if (!$stmt) {
        error_log('Failed to prepare rework edit query: ' . $conn->error);
        flash_set('error', 'A database error occurred while loading the rework job.');
        redirect('modules/rework/index.php');
        exit;
    }
    // $stmt is ready
    $stmt->bind_param('i', $reworkId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rework = $res->fetch_assoc();
    $stmt->close();
    if (!$rework) {
        flash_set('error', 'Rework job not found.');
        redirect('modules/rework/index.php');
    }
    // Build source defect array for preview UI
    $sourceDefect = [
        'inspection_defect_id' => $rework['inspection_defect_id'],
        'defect_count' => $rework['defect_count'],
        'inspection_number' => $rework['inspection_number'],
        'inspection_id' => $rework['inspection_id'],
        'inspection_stage' => $rework['inspection_stage'],
        'inspection_date' => $rework['inspection_date'],
        'batch_number' => $rework['batch_number'],
        'style_number' => $rework['style_number'],
        'buyer_name' => $rework['buyer_name'],
        'defect_code' => $rework['defect_code'],
        'defect_name' => $rework['defect_name'],
        'defect_category' => $rework['defect_category'],
        'defect_severity' => $rework['defect_severity'],
        'defect_notes' => $rework['defect_notes'],
    ];
} catch (mysqli_sql_exception $e) {
    error_log('Failed to load rework job: ' . $e->getMessage());
    flash_set('error', 'Database error while retrieving rework job.');
    redirect('modules/rework/index.php');
}

// Fetch active sewing lines for optional reassignment
$sewingLines = [];
try {
    $slRes = $conn->query("SELECT line_id, line_name, line_code FROM sewing_lines WHERE is_active = 1 ORDER BY line_name ASC");
    if ($slRes) {
        $sewingLines = $slRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log('Failed to load sewing lines for rework edit: ' . $e->getMessage());
}

// Retrieve and clear any flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old    = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Helper to prioritize old input over DB values
$getValue = function (string $key, $default = '') use ($old, $rework) {
    return $old[$key] ?? $rework[$key] ?? $default;
};

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Rework Job</h1>
        <div class="page-subtitle">Rework #<?= e($rework['rework_number']) ?> • <?= e($currentUser['full_name'] ?: $currentUser['username']) ?> (<?= e(ucwords(str_replace('_', ' ', $currentUser['role']))) ?>)</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/rework/view.php?id=' . (int)$rework['rework_id'])) ?>" class="btn btn-secondary">Cancel &amp; View</a>
    </div>
</div>
<div class="card card-form" style="max-width: 860px;">
    <div class="card-header">
        <h2>Rework Job Details</h2>
        <span class="badge badge-primary">Edit</span>
    </div>
    <div class="card-body">
        <form action="<?= e(url('modules/rework/update.php')) ?>" method="post" id="reworkForm" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="rework_number" value="<?= e($rework['rework_number']) ?>">
            <input type="hidden" name="inspection_defect_id" value="<?= e($rework['inspection_defect_id']) ?>">
            <input type="hidden" name="rework_id" value="<?= e((int)$rework['rework_id']) ?>">
            <!-- Source Defect Preview (read‑only) -->
            <div id="defectPreviewBox" style="background:#fdf6f7;border:1px solid #ebd0d4;border-radius:8px;padding:1rem;margin-bottom:1.25rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                    <strong style="color:var(--color-primary);font-size:0.95rem;">Source Defect Details &amp; Quantity Breakdown</strong>
                    <span id="previewSeverityBadge" class="badge"></span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;font-size:0.85rem;">
                    <div><span class="text-muted">Inspection:</span> <strong id="previewInspection"><?= e($sourceDefect['inspection_number']) ?></strong> (<span id="previewStage"><?= e($sourceDefect['inspection_stage']) ?></span>)</div>
                    <div><span class="text-muted">Batch &amp; Style:</span> <strong id="previewBatch"><?= e($sourceDefect['batch_number']) ?></strong> &bull; <span id="previewStyle"><?= e($sourceDefect['style_number']) ?></span></div>
                    <div><span class="text-muted">Buyer:</span> <span id="previewBuyer"><?= e($sourceDefect['buyer_name']) ?></span></div>
                    <div><span class="text-muted">Defect Type:</span> <strong id="previewDefect"><?= e($sourceDefect['defect_name']) ?></strong> (<span id="previewCode"><?= e($sourceDefect['defect_code']) ?></span>)</div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;margin-top:0.75rem;text-align:center;">
                    <div style="background:#fff;padding:0.5rem;border-radius:6px;border:1px solid #ebd0d4;">
                        <div class="text-xs text-muted">DEFECTIVE RECORDED</div>
                        <div id="previewTotal" style="font-size:1.2rem;font-weight:bold;">
                            <?= e($sourceDefect['defect_count']) ?>
                        </div>
                    </div>
                    <div style="background:#fff;padding:0.5rem;border-radius:6px;border:1px solid #ebd0d4;">
                        <div class="text-xs text-muted">ALREADY ALLOCATED</div>
                        <div id="previewAllocated" style="font-size:1.2rem;font-weight:bold;color:#b7791f;">
                            <?= e($rework['allocated_quantity'] ?? '0') ?>
                        </div>
                    </div>
                    <div style="background:#e8f5e9;padding:0.5rem;border-radius:6px;border:1px solid #c8e6c9;">
                        <div class="text-xs" style="color:#2e7d32;font-weight:bold;">AVAILABLE FOR REWORK</div>
                        <div id="previewAvailable" style="font-size:1.2rem;font-weight:bold;color:#2e7d32;">
                            <?= e(($sourceDefect['defect_count'] - ($rework['allocated_quantity'] ?? 0))) ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($sourceDefect['defect_notes'])): ?>
                <div id="previewNotesRow" style="margin-top:0.5rem;font-size:0.8rem;" class="text-muted">
                    <em>Floor Notes: <span id="previewNotes"><?= e($sourceDefect['defect_notes']) ?></span></em>
                </div>
                <?php endif; ?>
            </div>
            <!-- Job Identification & Creator (read‑only) -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label for="rework_number" class="form-label">Rework Number <span class="required">*</span></label>
                    <input type="text" id="rework_number" name="rework_number" class="form-control <?= isset($errors['rework_number']) ? 'has-error' : '' ?>" maxlength="50" required value="<?= e($rework['rework_number']) ?>" readonly disabled>
                    <?php if (isset($errors['rework_number'])): ?>
                        <span class="form-error"><?= e($errors['rework_number']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Unique identifier (cannot be changed).</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Logged By <span class="text-muted">(Authenticated Session)</span></label>
                    <input type="text" class="form-control" readonly disabled value="<?= e($currentUser['full_name'] ?: $currentUser['username']) ?> (<?= e(ucwords(str_replace('_', ' ', $currentUser['role']))) ?>)">
                </div>
            </div>
            <!-- Assignment Section -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem;">
                <div class="form-group">
                    <label for="assigned_to" class="form-label">Assigned Person / Team / Station <span class="required">*</span></label>
                    <input type="text" id="assigned_to" name="assigned_to" class="form-control <?= isset($errors['assigned_to']) ? 'has-error' : '' ?>" maxlength="150" required value="<?= e($getValue('assigned_to')) ?>" placeholder="e.g., Line A Repair Unit, Tailor Rahim, Finishing Section">
                    <?php if (isset($errors['assigned_to'])): ?>
                        <span class="form-error"><?= e($errors['assigned_to']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Responsible floor worker, team, or repair specialist.</span>
                </div>
                <div class="form-group">
                    <label for="line_id" class="form-label">Sewing Line <span class="text-muted">(Optional)</span></label>
                    <select id="line_id" name="line_id" class="form-control <?= isset($errors['line_id']) ? 'has-error' : '' ?>">
                        <option value="">-- No specific sewing line --</option>
                        <?php foreach ($sewingLines as $sl): ?>
                            <option value="<?= (int)$sl['line_id'] ?>" <?= ((int)($getValue('line_id') ?? $rework['line_id'] ?? 0) === (int)$sl['line_id']) ? 'selected' : '' ?>>
                                <?= e($sl['line_name']) ?> (<?= e($sl['line_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['line_id'])): ?>
                        <span class="form-error"><?= e($errors['line_id']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Link to factory floor sewing line if applicable.</span>
                </div>
            </div>
            <!-- Quantity & Dates -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:1rem;">
                <div class="form-group">
                    <label for="rework_quantity" class="form-label">Rework Quantity (pcs) <span class="required">*</span></label>
                    <input type="number" id="rework_quantity" name="rework_quantity" class="form-control <?= isset($errors['rework_quantity']) ? 'has-error' : '' ?>" min="1" step="1" required value="<?= e($getValue('rework_quantity')) ?>">
                    <?php if (isset($errors['rework_quantity'])): ?>
                        <span class="form-error"><?= e($errors['rework_quantity']) ?></span>
                    <?php endif; ?>
                    <span class="form-text" id="maxAvailableNoteEdit">Positive integer up to available quantity.</span>
                </div>
                <div class="form-group">
                    <label for="assigned_date" class="form-label">Assigned Date <span class="required">*</span></label>
                    <input type="date" id="assigned_date" name="assigned_date" class="form-control <?= isset($errors['assigned_date']) ? 'has-error' : '' ?>" required value="<?= e($getValue('assigned_date')) ?>">
                    <?php if (isset($errors['assigned_date'])): ?>
                        <span class="form-error"><?= e($errors['assigned_date']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="target_completion_date" class="form-label">Target Completion Date <span class="required">*</span></label>
                    <input type="date" id="target_completion_date" name="target_completion_date" class="form-control <?= isset($errors['target_completion_date']) ? 'has-error' : '' ?>" required value="<?= e($getValue('target_completion_date')) ?>">
                    <?php if (isset($errors['target_completion_date'])): ?>
                        <span class="form-error"><?= e($errors['target_completion_date']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Progress & Status -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem;">
                <div class="form-group">
                    <label for="completed_quantity" class="form-label">Completed Quantity (pcs)</label>
                    <input type="number" id="completed_quantity" name="completed_quantity" class="form-control <?= isset($errors['completed_quantity']) ? 'has-error' : '' ?>" min="0" step="1" value="<?= e($getValue('completed_quantity')) ?>">
                    <?php if (isset($errors['completed_quantity'])): ?>
                        <span class="form-error"><?= e($errors['completed_quantity']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="rejected_quantity" class="form-label">Rejected Quantity (pcs)</label>
                    <input type="number" id="rejected_quantity" name="rejected_quantity" class="form-control <?= isset($errors['rejected_quantity']) ? 'has-error' : '' ?>" min="0" step="1" value="<?= e($getValue('rejected_quantity')) ?>">
                    <?php if (isset($errors['rejected_quantity'])): ?>
                        <span class="form-error"><?= e($errors['rejected_quantity']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group" style="margin-top:1rem;">
                <label for="status" class="form-label">Job Status <span class="required">*</span></label>
                <select id="status" name="status" class="form-control <?= isset($errors['status']) ? 'has-error' : '' ?>" required>
                    <?php
                    $statuses = ['Pending', 'In Progress', 'Completed', 'Rejected'];
                    $currentStatus = $getValue('status') ?? $rework['status'];
                    foreach ($statuses as $st):
                        $selected = ($currentStatus === $st) ? 'selected' : '';
                    ?>
                        <option value="<?= e($st) ?>" <?= $selected ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?>
                    <span class="form-error"><?= e($errors['status']) ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group" style="margin-top:1rem;">
                <label for="actual_completion_date" class="form-label">Actual Completion Date</label>
                <input type="date" id="actual_completion_date" name="actual_completion_date" class="form-control <?= isset($errors['actual_completion_date']) ? 'has-error' : '' ?>" value="<?= e($getValue('actual_completion_date') ?: $rework['actual_completion_date'] ?? '') ?>">
                <?php if (isset($errors['actual_completion_date'])): ?>
                    <span class="form-error"><?= e($errors['actual_completion_date']) ?></span>
                <?php endif; ?>
            </div>
            <!-- Corrective Action & Remarks -->
            <div class="form-group" style="margin-top:1rem;">
                <label for="corrective_action" class="form-label">Planned Corrective Action</label>
                <textarea id="corrective_action" name="corrective_action" class="form-control <?= isset($errors['corrective_action']) ? 'has-error' : '' ?>" rows="2" placeholder="Describe the required corrective procedure ..."><?= e($getValue('corrective_action') ?: $rework['corrective_action']) ?></textarea>
                <?php if (isset($errors['corrective_action'])): ?>
                    <span class="form-error"><?= e($errors['corrective_action']) ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group" style="margin-top:1rem;">
                <label for="remarks" class="form-label">Remarks / Floor Notes</label>
                <textarea id="remarks" name="remarks" class="form-control <?= isset($errors['remarks']) ? 'has-error' : '' ?>" rows="2" placeholder="Optional notes or instructions for the repair team..."><?= e($getValue('remarks') ?: $rework['remarks']) ?></textarea>
                <?php if (isset($errors['remarks'])): ?>
                    <span class="form-error"><?= e($errors['remarks']) ?></span>
                <?php endif; ?>
            </div>
            <!-- Action Buttons -->
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="<?= e(url('modules/rework/view.php?id=' . (int)$rework['rework_id'])) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <script>
    // Client‑side validation for dates and quantity limits (mirrors create page)
    document.addEventListener('DOMContentLoaded', function () {
        const assignedDateInput = document.getElementById('assigned_date');
        const targetDateInput   = document.getElementById('target_completion_date');
        const actualDateInput   = document.getElementById('actual_completion_date');
        const qtyInput          = document.getElementById('rework_quantity');
        const maxNote           = document.getElementById('maxAvailableNoteEdit');
        const statusSelect      = document.getElementById('status');
        const completedInput    = document.getElementById('completed_quantity');
        const rejectedInput     = document.getElementById('rejected_quantity');
        const availableQty = parseInt(<?= json_encode(($sourceDefect['defect_count'] - ($rework['allocated_quantity'] ?? 0))) ?>);

        function enforceDateOrder() {
            if (assignedDateInput.value && targetDateInput.value && targetDateInput.value < assignedDateInput.value) {
                targetDateInput.setCustomValidity('Target completion date cannot be earlier than assigned date.');
            } else {
                targetDateInput.setCustomValidity('');
            }
            if (assignedDateInput.value && actualDateInput.value && actualDateInput.value < assignedDateInput.value) {
                actualDateInput.setCustomValidity('Actual completion date cannot be earlier than assigned date.');
            } else {
                actualDateInput.setCustomValidity('');
            }
        }
        assignedDateInput.addEventListener('change', enforceDateOrder);
        targetDateInput.addEventListener('change', enforceDateOrder);
        actualDateInput.addEventListener('change', enforceDateOrder);

        // Quantity max based on available
        qtyInput.setAttribute('max', availableQty);
        maxNote.textContent = 'Available for this defect: ' + availableQty + ' piece(s).';
        if (parseInt(qtyInput.value, 10) > availableQty) {
            qtyInput.value = availableQty;
        }

        // Ensure completed + rejected does not exceed quantity
        function validateProgress() {
            const qty = parseInt(qtyInput.value, 10) || 0;
            const completed = parseInt(completedInput.value, 10) || 0;
            const rejected = parseInt(rejectedInput.value, 10) || 0;
            if (completed + rejected > qty) {
                // simple UI hint; server will enforce
                completedInput.setCustomValidity('Sum of completed and rejected exceeds total rework quantity.');
                rejectedInput.setCustomValidity('Sum of completed and rejected exceeds total rework quantity.');
            } else {
                completedInput.setCustomValidity('');
                rejectedInput.setCustomValidity('');
            }
        }
        qtyInput.addEventListener('input', validateProgress);
        completedInput.addEventListener('input', validateProgress);
        rejectedInput.addEventListener('input', validateProgress);
    });
    </script>
        </div>
</div>
<?php
include __DIR__ . '/../../includes/footer.php';
?>
