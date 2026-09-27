<?php
/**
 * ThreadFlow - Create / Assign Rework Job
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Create Rework Job';
$currentNav = 'rework';

$currentUser = auth_user();

// Retrieve and clear flashed validation errors and old input
$errors = $_SESSION['_form_errors'] ?? [];
$old    = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Query available inspection defects with remaining unallocated quantities
$availableDefects = [];
try {
    $defectSql = "SELECT 
        idf.inspection_defect_id,
        idf.defect_count,
        idf.notes AS defect_notes,
        qi.inspection_id,
        qi.inspection_number,
        qi.inspection_date,
        qi.inspection_stage,
        qi.overall_result,
        pb.batch_id,
        pb.batch_number,
        bo.order_number,
        bo.style_number,
        b.buyer_name,
        dt.defect_id,
        dt.defect_code,
        dt.defect_name,
        dt.category AS defect_category,
        dt.severity AS defect_severity,
        COALESCE(SUM(rj.rework_quantity), 0) AS allocated_quantity,
        (idf.defect_count - COALESCE(SUM(rj.rework_quantity), 0)) AS available_quantity
    FROM inspection_defects idf
    INNER JOIN quality_inspections qi ON idf.inspection_id = qi.inspection_id
    INNER JOIN production_batches pb ON qi.batch_id = pb.batch_id
    INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
    INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
    INNER JOIN defect_types dt ON idf.defect_id = dt.defect_id
    LEFT JOIN rework_jobs rj ON idf.inspection_defect_id = rj.inspection_defect_id
    GROUP BY idf.inspection_defect_id
    HAVING available_quantity > 0
    ORDER BY qi.inspection_id DESC, idf.inspection_defect_id ASC";

    $dRes = $conn->query($defectSql);
    if ($dRes) {
        $availableDefects = $dRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load available inspection defects: " . $e->getMessage());
}

// Fetch active sewing lines for optional line assignment
$sewingLines = [];
try {
    $slRes = $conn->query("SELECT line_id, line_name, line_code FROM sewing_lines WHERE is_active = 1 ORDER BY line_name ASC");
    if ($slRes) {
        $sewingLines = $slRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load sewing lines for rework creation: " . $e->getMessage());
}

// Pre-selected inspection defect ID
$preselectedDefectId = (int) ($old['inspection_defect_id'] ?? ($_GET['inspection_defect_id'] ?? 0));

// Default suggested rework number
$defaultReworkNumber = $old['rework_number'] ?? ('RWK-' . date('Ymd') . '-' . str_pad((string) mt_rand(100, 999), 3, '0', STR_PAD_LEFT));
$defaultAssignedDate = $old['assigned_date'] ?? date('Y-m-d');
$defaultTargetDate   = $old['target_completion_date'] ?? date('Y-m-d', strtotime('+2 days'));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Create Rework Job</h1>
        <div class="page-subtitle">Module 4 &bull; Ownership: Member 4 &bull; Access: Admin, QC Officer</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/rework/index.php')) ?>" class="btn btn-secondary">Back to Rework List</a>
    </div>
</div>

<!-- Scoped Create Rework Styles -->
<style>
.rwk-form-card {
    max-width: 900px;
    margin: 0 auto 1.5rem;
}
.form-section-header {
    border-bottom: 1px solid var(--color-border-subtle);
    padding: 0.85rem 1.25rem;
    background: var(--color-surface-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.form-section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-primary-dark);
    margin: 0;
}
.form-section-body {
    padding: 1.25rem;
}
.rwk-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.15rem;
}
.rwk-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}
.rwk-preview-box {
    background: #FFFDFD;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 1.15rem;
    margin-top: 0.85rem;
}
</style>

<div class="card rwk-form-card">
    <div class="card-header">
        <div>
            <h2>Rework Job Assignment</h2>
            <div class="text-xs text-muted">Route inspection defects to repair stations and assign salvage quantities</div>
        </div>
        <span class="badge badge-primary">New Job</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($availableDefects)): ?>
            <div class="alert alert-warning m-3 mb-0">
                <strong>No Unallocated Defects Available:</strong> There are currently no Quality Inspection defects with unallocated quantities available for rework.
                All recorded inspection defects have either been fully allocated or no defective garments have been logged yet.
                <div class="mt-2">
                    <a href="<?= e(url('modules/quality_inspections/index.php')) ?>" class="btn btn-sm btn-secondary">View Quality Inspections</a>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= e(url('modules/rework/store.php')) ?>" method="post" id="reworkForm" novalidate>
            <?= csrf_field() ?>

            <!-- Section 1: Identification & Author -->
            <div class="form-section-header">
                <h3 class="form-section-title">1. Job Identification &amp; QC Author</h3>
                <span class="badge badge-muted text-xs">Tracking Key</span>
            </div>
            <div class="form-section-body">
                <div class="rwk-grid-2">
                    <div class="form-group mb-0">
                        <label for="rework_number" class="form-label">Rework Number <span class="required">*</span></label>
                        <input type="text"
                               id="rework_number"
                               name="rework_number"
                               class="form-control <?= isset($errors['rework_number']) ? 'has-error' : '' ?>"
                               maxlength="50"
                               required
                               value="<?= e($defaultReworkNumber) ?>"
                               placeholder="e.g., RWK-20260921-001">
                        <?php if (isset($errors['rework_number'])): ?>
                            <span class="form-error"><?= e($errors['rework_number']) ?></span>
                        <?php endif; ?>
                        <span class="form-text">Unique tracking identifier for this rework job.</span>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label">Logged By <span class="text-muted">(Authenticated Account)</span></label>
                        <input type="text"
                               class="form-control"
                               readonly
                               disabled
                               style="background-color: var(--color-bg, #FCEBED); cursor: not-allowed;"
                               value="<?= e($currentUser['full_name'] ?: $currentUser['username']) ?> (<?= e(ucwords(str_replace('_', ' ', $currentUser['role']))) ?>)">
                        <span class="form-text">Author identity is securely locked to your session.</span>
                    </div>
                </div>
            </div>

            <!-- Section 2: Source Defect Selection & Preview -->
            <div class="form-section-header">
                <h3 class="form-section-title">2. Source Defect &amp; Available Headroom</h3>
                <span class="badge badge-muted text-xs">Inspection Context</span>
            </div>
            <div class="form-section-body">
                <div class="form-group mb-0">
                    <label for="inspection_defect_id" class="form-label">Source Quality Inspection Defect <span class="required">*</span></label>
                    <select id="inspection_defect_id"
                            name="inspection_defect_id"
                            class="form-control <?= isset($errors['inspection_defect_id']) ? 'has-error' : '' ?>"
                            required>
                        <option value="">-- Select an inspection defect with available quantity --</option>
                        <?php foreach ($availableDefects as $def): ?>
                            <?php
                            $isSelected = ($preselectedDefectId === (int)$def['inspection_defect_id']);
                            $avail = (int)$def['available_quantity'];
                            $total = (int)$def['defect_count'];
                            $alloc = (int)$def['allocated_quantity'];
                            ?>
                            <option value="<?= (int)$def['inspection_defect_id'] ?>"
                                    data-available="<?= $avail ?>"
                                    data-total="<?= $total ?>"
                                    data-allocated="<?= $alloc ?>"
                                    data-inspection-num="<?= e($def['inspection_number']) ?>"
                                    data-inspection-id="<?= (int)$def['inspection_id'] ?>"
                                    data-inspection-stage="<?= e($def['inspection_stage']) ?>"
                                    data-batch-num="<?= e($def['batch_number']) ?>"
                                    data-style-num="<?= e($def['style_number']) ?>"
                                    data-buyer-name="<?= e($def['buyer_name']) ?>"
                                    data-defect-code="<?= e($def['defect_code']) ?>"
                                    data-defect-name="<?= e($def['defect_name']) ?>"
                                    data-defect-category="<?= e($def['defect_category']) ?>"
                                    data-defect-severity="<?= e($def['defect_severity']) ?>"
                                    data-notes="<?= e($def['defect_notes'] ?? '') ?>"
                                    <?= $isSelected ? 'selected' : '' ?>>
                                [<?= e($def['inspection_number']) ?>] Batch: <?= e($def['batch_number']) ?> | <?= e($def['defect_name']) ?> (<?= ucfirst(e($def['defect_severity'])) ?>) &mdash; Available: <?= $avail ?> pc(s) (Total: <?= $total ?>, Allocated: <?= $alloc ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['inspection_defect_id'])): ?>
                        <span class="form-error"><?= e($errors['inspection_defect_id']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Choose the specific defective garment count logged in a Quality Inspection.</span>
                </div>

                <!-- Dynamic Source Defect Preview Dossier -->
                <div id="defectPreviewBox" class="rwk-preview-box" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                        <strong style="color: var(--color-primary); font-size: 0.95rem;">Source Defect Details &amp; Quantity Breakdown</strong>
                        <span id="previewSeverityBadge" class="badge"></span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.85rem;">
                        <div>
                            <span class="text-muted">Inspection:</span>
                            <strong id="previewInspection"></strong> (<span id="previewStage"></span>)
                        </div>
                        <div>
                            <span class="text-muted">Batch &amp; Style:</span>
                            <strong id="previewBatch"></strong> &bull; <span id="previewStyle"></span>
                        </div>
                        <div>
                            <span class="text-muted">Buyer:</span>
                            <span id="previewBuyer"></span>
                        </div>
                        <div>
                            <span class="text-muted">Defect Type:</span>
                            <strong id="previewDefect"></strong> (<span id="previewCode"></span>)
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-top: 0.85rem; text-align: center;">
                        <div style="background: #fff; padding: 0.65rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                            <div class="text-xs text-muted font-semibold">DEFECTIVE RECORDED</div>
                            <div id="previewTotal" style="font-size: 1.25rem; font-weight: 800; margin-top: 0.2rem;">0</div>
                        </div>
                        <div style="background: #fff; padding: 0.65rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                            <div class="text-xs text-muted font-semibold">ALREADY ALLOCATED</div>
                            <div id="previewAllocated" style="font-size: 1.25rem; font-weight: 800; color: #B45309; margin-top: 0.2rem;">0</div>
                        </div>
                        <div style="background: #F0FDF4; padding: 0.65rem; border-radius: var(--radius-sm); border: 1px solid #BBF7D0;">
                            <div class="text-xs font-semibold" style="color: #15803D;">AVAILABLE FOR REWORK</div>
                            <div id="previewAvailable" style="font-size: 1.25rem; font-weight: 800; color: #15803D; margin-top: 0.2rem;">0</div>
                        </div>
                    </div>
                    <div id="previewNotesRow" style="margin-top: 0.5rem; font-size: 0.8rem;" class="text-muted">
                        <em>Floor Notes: <span id="previewNotes"></span></em>
                    </div>
                </div>
            </div>

            <!-- Section 3: Assignment & Target Dates -->
            <div class="form-section-header">
                <h3 class="form-section-title">3. Assignment, Quantity &amp; Schedule</h3>
                <span class="badge badge-muted text-xs">Floor Routing</span>
            </div>
            <div class="form-section-body">
                <div class="rwk-grid-2">
                    <div class="form-group">
                        <label for="assigned_to" class="form-label">Assigned Person / Team / Station <span class="required">*</span></label>
                        <input type="text"
                               id="assigned_to"
                               name="assigned_to"
                               class="form-control <?= isset($errors['assigned_to']) ? 'has-error' : '' ?>"
                               maxlength="150"
                               required
                               value="<?= e($old['assigned_to'] ?? '') ?>"
                               placeholder="e.g., Line A Repair Unit, Tailor Rahim, Finishing Section">
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
                                <option value="<?= (int)$sl['line_id'] ?>" <?= ((int)($old['line_id'] ?? 0) === (int)$sl['line_id']) ? 'selected' : '' ?>>
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

                <div class="rwk-grid-3">
                    <div class="form-group">
                        <label for="rework_quantity" class="form-label">Rework Quantity (pcs) <span class="required">*</span></label>
                        <input type="number"
                               id="rework_quantity"
                               name="rework_quantity"
                               class="form-control <?= isset($errors['rework_quantity']) ? 'has-error' : '' ?>"
                               min="1"
                               step="1"
                               required
                               value="<?= e($old['rework_quantity'] ?? '') ?>"
                               placeholder="e.g., 4">
                        <?php if (isset($errors['rework_quantity'])): ?>
                            <span class="form-error"><?= e($errors['rework_quantity']) ?></span>
                        <?php endif; ?>
                        <span id="maxAvailableNote" class="form-text">Positive integer up to available quantity.</span>
                    </div>

                    <div class="form-group">
                        <label for="assigned_date" class="form-label">Assigned Date <span class="required">*</span></label>
                        <input type="date"
                               id="assigned_date"
                               name="assigned_date"
                               class="form-control <?= isset($errors['assigned_date']) ? 'has-error' : '' ?>"
                               required
                               value="<?= e($defaultAssignedDate) ?>">
                        <?php if (isset($errors['assigned_date'])): ?>
                            <span class="form-error"><?= e($errors['assigned_date']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="target_completion_date" class="form-label">Target Completion Date <span class="required">*</span></label>
                        <input type="date"
                               id="target_completion_date"
                               name="target_completion_date"
                               class="form-control <?= isset($errors['target_completion_date']) ? 'has-error' : '' ?>"
                               required
                               value="<?= e($defaultTargetDate) ?>">
                        <?php if (isset($errors['target_completion_date'])): ?>
                            <span class="form-error"><?= e($errors['target_completion_date']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label for="status" class="form-label">Initial Job Status <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control <?= isset($errors['status']) ? 'has-error' : '' ?>" required>
                        <option value="Pending" <?= ($old['status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending (Created, awaiting repair start)</option>
                        <option value="In Progress" <?= ($old['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress (Repair actively underway)</option>
                    </select>
                    <?php if (isset($errors['status'])): ?>
                        <span class="form-error"><?= e($errors['status']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">New jobs start as Pending or In Progress. Completion and rejection are recorded during repair execution.</span>
                </div>
            </div>

            <!-- Section 4: Corrective Procedure & Remarks -->
            <div class="form-section-header">
                <h3 class="form-section-title">4. Corrective Procedure &amp; Repair Notes</h3>
                <span class="badge badge-muted text-xs">Floor Guidance</span>
            </div>
            <div class="form-section-body">
                <div class="form-group">
                    <label for="corrective_action" class="form-label">Planned Corrective Action</label>
                    <textarea id="corrective_action"
                              name="corrective_action"
                              class="form-control <?= isset($errors['corrective_action']) ? 'has-error' : '' ?>"
                              rows="2"
                              placeholder="Describe the required corrective procedure (e.g., Unpick side seam and restitch with tension adjustment)..."><?= e($old['corrective_action'] ?? '') ?></textarea>
                    <?php if (isset($errors['corrective_action'])): ?>
                        <span class="form-error"><?= e($errors['corrective_action']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group mb-0">
                    <label for="remarks" class="form-label">Remarks / Floor Notes</label>
                    <textarea id="remarks"
                              name="remarks"
                              class="form-control <?= isset($errors['remarks']) ? 'has-error' : '' ?>"
                              rows="2"
                              placeholder="Optional notes or instructions for the repair team..."><?= e($old['remarks'] ?? '') ?></textarea>
                    <?php if (isset($errors['remarks'])): ?>
                        <span class="form-error"><?= e($errors['remarks']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Footer -->
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-xs text-muted">Rework allocation is deducted from source inspection availability.</span>
                <div class="form-actions mt-0">
                    <a href="<?= e(url('modules/rework/index.php')) ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" <?= empty($availableDefects) ? 'disabled' : '' ?>>
                        Save &amp; Assign Rework Job
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const defectSelect = document.getElementById('inspection_defect_id');
    const previewBox   = document.getElementById('defectPreviewBox');
    const qtyInput     = document.getElementById('rework_quantity');
    const maxNote      = document.getElementById('maxAvailableNote');

    const previewInspection = document.getElementById('previewInspection');
    const previewStage      = document.getElementById('previewStage');
    const previewBatch      = document.getElementById('previewBatch');
    const previewStyle      = document.getElementById('previewStyle');
    const previewBuyer      = document.getElementById('previewBuyer');
    const previewDefect     = document.getElementById('previewDefect');
    const previewCode       = document.getElementById('previewCode');
    const previewTotal      = document.getElementById('previewTotal');
    const previewAllocated  = document.getElementById('previewAllocated');
    const previewAvailable  = document.getElementById('previewAvailable');
    const previewNotesRow   = document.getElementById('previewNotesRow');
    const previewNotes      = document.getElementById('previewNotes');
    const severityBadge     = document.getElementById('previewSeverityBadge');

    function updatePreview() {
        const selectedOpt = defectSelect.options[defectSelect.selectedIndex];
        if (!selectedOpt || !selectedOpt.value) {
            previewBox.style.display = 'none';
            qtyInput.removeAttribute('max');
            maxNote.textContent = 'Positive integer up to available quantity.';
            return;
        }

        const avail    = parseInt(selectedOpt.getAttribute('data-available'), 10) || 0;
        const total    = parseInt(selectedOpt.getAttribute('data-total'), 10) || 0;
        const alloc    = parseInt(selectedOpt.getAttribute('data-allocated'), 10) || 0;
        const severity = selectedOpt.getAttribute('data-defect-severity') || '';
        const notes    = selectedOpt.getAttribute('data-notes') || '';

        previewInspection.textContent = selectedOpt.getAttribute('data-inspection-num') || '';
        previewStage.textContent      = selectedOpt.getAttribute('data-inspection-stage') || '';
        previewBatch.textContent      = selectedOpt.getAttribute('data-batch-num') || '';
        previewStyle.textContent      = selectedOpt.getAttribute('data-style-num') || '';
        previewBuyer.textContent      = selectedOpt.getAttribute('data-buyer-name') || '';
        previewDefect.textContent     = selectedOpt.getAttribute('data-defect-name') || '';
        previewCode.textContent       = selectedOpt.getAttribute('data-defect-code') || '';

        previewTotal.textContent      = total;
        previewAllocated.textContent  = alloc;
        previewAvailable.textContent  = avail;

        // Severity badge
        severityBadge.textContent = severity.charAt(0).toUpperCase() + severity.slice(1);
        severityBadge.className = 'badge';
        if (severity === 'critical') {
            severityBadge.classList.add('badge-danger');
        } else if (severity === 'major') {
            severityBadge.classList.add('badge-warning');
        } else {
            severityBadge.classList.add('badge-info');
        }

        if (notes && notes.trim().length > 0) {
            previewNotes.textContent = notes;
            previewNotesRow.style.display = 'block';
        } else {
            previewNotesRow.style.display = 'none';
        }

        previewBox.style.display = 'block';

        // Set max and advise user
        qtyInput.setAttribute('max', avail);
        maxNote.textContent = 'Available for this defect: ' + avail + ' piece(s).';

        if (!qtyInput.value || parseInt(qtyInput.value, 10) > avail) {
            qtyInput.value = avail;
        }
    }

    defectSelect.addEventListener('change', updatePreview);
    // Initialize if preselected
    if (defectSelect.value) {
        updatePreview();
    }

    // Client-side date check
    const assignedDateInput = document.getElementById('assigned_date');
    const targetDateInput   = document.getElementById('target_completion_date');

    targetDateInput.addEventListener('change', function () {
        if (assignedDateInput.value && targetDateInput.value) {
            if (targetDateInput.value < assignedDateInput.value) {
                targetDateInput.setCustomValidity('Target completion date cannot be earlier than assigned date.');
            } else {
                targetDateInput.setCustomValidity('');
            }
        }
    });

    assignedDateInput.addEventListener('change', function () {
        if (assignedDateInput.value && targetDateInput.value && targetDateInput.value < assignedDateInput.value) {
            targetDateInput.value = assignedDateInput.value;
        }
    });
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
