<?php
/**
 * ThreadFlow - Log Quality Inspection Form
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

$pageTitle = 'Log Quality Inspection';
$currentNav = 'quality_inspections';

$currentUser = auth_user();

// Retrieve and clear flashed validation errors and previous input
$errors = $_SESSION['_form_errors'] ?? [];
$old = $_SESSION['_form_old'] ?? [];
unset($_SESSION['_form_errors'], $_SESSION['_form_old']);

// Fetch active production batches with order & buyer information
$batches = [];
try {
    $batchSql = "SELECT pb.batch_id, pb.batch_number, pb.planned_quantity, pb.status,
                        bo.order_number, bo.style_number, b.buyer_name
                 FROM production_batches pb
                 INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
                 INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
                 ORDER BY pb.batch_id DESC";
    $bRes = $conn->query($batchSql);
    if ($bRes) {
        $batches = $bRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load batches for create inspection: " . $e->getMessage());
}

// Fetch active defect types for dynamic defect rows
$activeDefects = [];
try {
    $defectSql = "SELECT defect_id, defect_code, defect_name, category, severity 
                  FROM defect_types 
                  WHERE is_active = 1 
                  ORDER BY category ASC, defect_code ASC";
    $dRes = $conn->query($defectSql);
    if ($dRes) {
        $activeDefects = $dRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Failed to load active defect types: " . $e->getMessage());
}

$stages = ['Inline', 'End of Line', 'Final Pre-Shipment'];
$results = ['Passed', 'Failed', 'Pending Review'];

// Generate proposed unique inspection number: INS-YYYYMMDD-XXX
$defaultInspectionNumber = $old['inspection_number'] ?? ('INS-' . date('Ymd') . '-' . str_pad((string) mt_rand(100, 999), 3, '0', STR_PAD_LEFT));

// Prepare old defect items for re-rendering if validation redirected back
$oldDefects = [];
if (!empty($old['defect_id']) && is_array($old['defect_id'])) {
    foreach ($old['defect_id'] as $idx => $dId) {
        $oldDefects[] = [
            'defect_id' => (int) $dId,
            'count'     => (int) ($old['defect_count'][$idx] ?? 1),
            'notes'     => (string) ($old['defect_notes'][$idx] ?? ''),
        ];
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1>Log Quality Inspection</h1>
        <div class="page-subtitle">Module 4 &bull; Ownership: Member 4 &bull; Access: Admin, QC Officer</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/quality_inspections/index.php')) ?>" class="btn btn-secondary">Back to Inspection List</a>
    </div>
</div>

<!-- Scoped Create Inspection Styles -->
<style>
.qi-form-card {
    max-width: 920px;
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
.qi-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.15rem;
}
.qi-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
.qi-calc-box {
    background: #FFFDFD;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.qi-calc-box-header {
    background: var(--color-primary-subtle);
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--color-border-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.qi-calc-box-body {
    padding: 1.15rem;
}
</style>

<div class="card qi-form-card">
    <div class="card-header">
        <div>
            <h2>Inspection Audit Entry</h2>
            <div class="text-xs text-muted">Log floor quality sampling results and defect taxonomy breakdowns</div>
        </div>
        <span class="badge badge-primary">New Inspection</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($batches)): ?>
            <div class="alert alert-warning m-3 mb-0">
                <strong>Prerequisite Missing:</strong> No production batches found in the database.
                An inspection must reference a valid production batch. Please create a batch in Module 3 first.
            </div>
        <?php endif; ?>

        <form action="<?= e(url('modules/quality_inspections/store.php')) ?>" method="post" id="inspectionForm" novalidate>
            <?= csrf_field() ?>

            <!-- Section 1: Audit Header & Session Context -->
            <div class="form-section-header">
                <h3 class="form-section-title">1. Audit Identification &amp; Inspector</h3>
                <span class="badge badge-muted text-xs">Session Log</span>
            </div>
            <div class="form-section-body">
                <div class="qi-grid-2">
                    <div class="form-group mb-0">
                        <label for="inspection_number" class="form-label">Inspection Number <span class="required">*</span></label>
                        <input type="text"
                               id="inspection_number"
                               name="inspection_number"
                               class="form-control <?= isset($errors['inspection_number']) ? 'has-error' : '' ?>"
                               maxlength="50"
                               required
                               value="<?= e($defaultInspectionNumber) ?>"
                               placeholder="e.g., INS-20260917-001">
                        <?php if (isset($errors['inspection_number'])): ?>
                            <span class="form-error"><?= e($errors['inspection_number']) ?></span>
                        <?php endif; ?>
                        <span class="form-text">Unique alphanumeric inspection audit identifier.</span>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label">Inspector <span class="text-muted">(Session Account)</span></label>
                        <input type="text"
                               class="form-control"
                               value="<?= e($currentUser['full_name'] ?: $currentUser['username']) ?> (<?= e(ucwords(str_replace('_', ' ', $currentUser['role']))) ?>)"
                               readonly
                               disabled
                               style="background-color: var(--color-bg, #FCEBED); cursor: not-allowed;">
                        <span class="form-text">Locked to your active authenticated account for audit traceability.</span>
                    </div>
                </div>
            </div>

            <!-- Section 2: Production Batch & Stage Context -->
            <div class="form-section-header">
                <h3 class="form-section-title">2. Batch Routing &amp; Stage Evaluation</h3>
                <span class="badge badge-muted text-xs">Floor Context</span>
            </div>
            <div class="form-section-body">
                <div class="form-group">
                    <label for="batch_id" class="form-label">Production Batch <span class="required">*</span></label>
                    <select id="batch_id"
                            name="batch_id"
                            class="form-control <?= isset($errors['batch_id']) ? 'has-error' : '' ?>"
                            required>
                        <option value="">-- Select Production Batch --</option>
                        <?php foreach ($batches as $b): ?>
                            <?php 
                            $selected = (isset($old['batch_id']) && (int)$old['batch_id'] === (int)$b['batch_id']) ? 'selected' : '';
                            ?>
                            <option value="<?= (int) $b['batch_id'] ?>"
                                    data-planned="<?= (int)$b['planned_quantity'] ?>"
                                    <?= $selected ?>>
                                <?= e($b['batch_number']) ?> &bull; <?= e($b['style_number']) ?> (<?= e($b['buyer_name']) ?> &mdash; Planned: <?= number_format((int)$b['planned_quantity']) ?> pcs)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['batch_id'])): ?>
                        <span class="form-error"><?= e($errors['batch_id']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Target production lot being inspected.</span>
                </div>

                <div class="qi-grid-3">
                    <div class="form-group mb-0">
                        <label for="inspection_stage" class="form-label">Inspection Stage <span class="required">*</span></label>
                        <select id="inspection_stage"
                                name="inspection_stage"
                                class="form-control <?= isset($errors['inspection_stage']) ? 'has-error' : '' ?>"
                                required>
                            <?php foreach ($stages as $stg): ?>
                                <?php $selected = ($old['inspection_stage'] ?? 'Inline') === $stg ? 'selected' : ''; ?>
                                <option value="<?= e($stg) ?>" <?= $selected ?>><?= e($stg) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['inspection_stage'])): ?>
                            <span class="form-error"><?= e($errors['inspection_stage']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-0">
                        <label for="inspection_date" class="form-label">Inspection Date <span class="required">*</span></label>
                        <input type="date"
                               id="inspection_date"
                               name="inspection_date"
                               class="form-control <?= isset($errors['inspection_date']) ? 'has-error' : '' ?>"
                               required
                               value="<?= e($old['inspection_date'] ?? date('Y-m-d')) ?>">
                        <?php if (isset($errors['inspection_date'])): ?>
                            <span class="form-error"><?= e($errors['inspection_date']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-0">
                        <label for="overall_result" class="form-label">Overall Result <span class="required">*</span></label>
                        <select id="overall_result"
                                name="overall_result"
                                class="form-control <?= isset($errors['overall_result']) ? 'has-error' : '' ?>"
                                required>
                            <?php foreach ($results as $res): ?>
                                <?php $selected = ($old['overall_result'] ?? 'Passed') === $res ? 'selected' : ''; ?>
                                <option value="<?= e($res) ?>" <?= $selected ?>><?= e($res) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['overall_result'])): ?>
                            <span class="form-error"><?= e($errors['overall_result']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 3: Quantity Calculation & Defect Breakdown -->
            <div class="form-section-header">
                <h3 class="form-section-title">3. Sampling Volume &amp; Defect Breakdown</h3>
                <span class="badge badge-muted text-xs">Quality Math</span>
            </div>
            <div class="form-section-body">
                <!-- Quantity Calculation Box -->
                <div class="qi-calc-box">
                    <div class="qi-calc-box-header">
                        <strong style="font-size: 0.88rem; color: var(--color-primary-dark);">Inspection Quantities Math</strong>
                        <span class="text-sm font-semibold" id="formulaIndicator" style="color: #2e7d32;">Passed + Defective = Inspected</span>
                    </div>
                    <div class="qi-calc-box-body">
                        <div class="qi-grid-3">
                            <div class="form-group mb-0">
                                <label for="inspected_quantity" class="form-label">Inspected Quantity <span class="required">*</span></label>
                                <input type="number"
                                       id="inspected_quantity"
                                       name="inspected_quantity"
                                       class="form-control <?= isset($errors['inspected_quantity']) ? 'has-error' : '' ?>"
                                       min="1"
                                       step="1"
                                       required
                                   value="<?= e($old['inspected_quantity'] ?? '') ?>"
                                   placeholder="e.g., 200">
                            <?php if (isset($errors['inspected_quantity'])): ?>
                                <span class="form-error"><?= e($errors['inspected_quantity']) ?></span>
                            <?php endif; ?>
                            <span class="form-text">Total pieces sampled.</span>
                        </div>

                        <div class="form-group mb-0">
                            <label for="defective_quantity" class="form-label">Defective Quantity <span class="required">*</span></label>
                            <input type="number"
                                   id="defective_quantity"
                                   name="defective_quantity"
                                   class="form-control <?= isset($errors['defective_quantity']) ? 'has-error' : '' ?>"
                                   min="0"
                                   step="1"
                                   required
                                   value="<?= e($old['defective_quantity'] ?? '0') ?>"
                                   placeholder="0">
                            <?php if (isset($errors['defective_quantity'])): ?>
                                <span class="form-error"><?= e($errors['defective_quantity']) ?></span>
                            <?php endif; ?>
                            <span class="form-text">Number of defective pieces.</span>
                        </div>

                        <div class="form-group mb-0">
                            <label for="passed_quantity" class="form-label">Passed Quantity <span class="required">*</span></label>
                            <input type="number"
                                   id="passed_quantity"
                                   name="passed_quantity"
                                   class="form-control <?= isset($errors['passed_quantity']) ? 'has-error' : '' ?>"
                                   min="0"
                                   step="1"
                                   required
                                   value="<?= e($old['passed_quantity'] ?? '') ?>"
                                   placeholder="Auto-calculated"
                                   readonly
                                   style="background-color: #fff; font-weight: bold; color: #2e7d32;">
                            <?php if (isset($errors['passed_quantity'])): ?>
                                <span class="form-error"><?= e($errors['passed_quantity']) ?></span>
                            <?php endif; ?>
                            <span class="form-text">Auto-computed: (Inspected &minus; Defective).</span>
                        </div>
                    </div>
                    <?php if (isset($errors['quantity_rule'])): ?>
                        <div class="form-error mt-2"><?= e($errors['quantity_rule']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dynamic Multiple Defect Breakdown -->
            <div class="card mb-4" id="defectBreakdownCard">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h3 style="font-size: 1rem; margin: 0;">Defect Types Breakdown</h3>
                        <span class="text-xs text-muted">Required when Defective Quantity &gt; 0. Individual counts cannot exceed defective quantity.</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="btnAddDefectRow">
                        + Add Defect Type
                    </button>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (isset($errors['defects'])): ?>
                        <div class="alert alert-danger m-3 mb-0"><?= e($errors['defects']) ?></div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table mb-0" id="defectsTable">
                            <thead>
                                <tr>
                                    <th style="min-width: 250px;">Defect Type <span class="required">*</span></th>
                                    <th style="width: 140px;">Defect Count <span class="required">*</span></th>
                                    <th>Notes / Specific Area</th>
                                    <th style="width: 70px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="defectsTbody">
                                <?php if (!empty($oldDefects)): ?>
                                    <?php foreach ($oldDefects as $i => $od): ?>
                                        <tr class="defect-row">
                                            <td>
                                                <select name="defect_id[]" class="form-control defect-select" required>
                                                    <option value="">-- Select Defect Type --</option>
                                                    <?php foreach ($activeDefects as $ad): ?>
                                                        <option value="<?= (int) $ad['defect_id'] ?>" <?= ((int)$od['defect_id'] === (int)$ad['defect_id']) ? 'selected' : '' ?>>
                                                            <?= e($ad['defect_code']) ?> &mdash; <?= e($ad['defect_name']) ?> (<?= e($ad['category']) ?>, <?= ucfirst(e($ad['severity'])) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number"
                                                       name="defect_count[]"
                                                       class="form-control defect-count-input"
                                                       min="1"
                                                       step="1"
                                                       required
                                                       value="<?= (int) $od['count'] ?>">
                                            </td>
                                            <td>
                                                <input type="text"
                                                       name="defect_notes[]"
                                                       class="form-control"
                                                       maxlength="255"
                                                       placeholder="Specific placement, panel, or note..."
                                                       value="<?= e($od['notes']) ?>">
                                            </td>
                                            <td style="text-align: center;">
                                                <button type="button" class="btn btn-sm btn-danger btn-remove-row" title="Remove Defect">&times;</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="noDefectsNotice" class="p-3 text-muted text-sm text-center" style="<?= !empty($oldDefects) ? 'display: none;' : '' ?>">
                        No defect rows added. Defect rows are required if Defective Quantity is greater than zero.
                    </div>
                </div>
            </div>
            <!-- End Section 3 -->

            <!-- Section 4: Remarks & Clearance Notes -->
            <div class="form-section-header">
                <h3 class="form-section-title">4. Floor Observations &amp; Clearance Notes</h3>
                <span class="badge badge-muted text-xs">Inspector Notes</span>
            </div>
            <div class="form-section-body">
                <div class="form-group mb-0">
                    <label for="remarks" class="form-label">Inspection Remarks / Observations</label>
                    <textarea id="remarks"
                              name="remarks"
                              class="form-control <?= isset($errors['remarks']) ? 'has-error' : '' ?>"
                              rows="3"
                              placeholder="Audit observations, root causes, corrective actions, or batch clearance notes..."><?= e($old['remarks'] ?? '') ?></textarea>
                    <?php if (isset($errors['remarks'])): ?>
                        <span class="form-error"><?= e($errors['remarks']) ?></span>
                    <?php endif; ?>
                    <span class="form-text">Optional technical notes, remedial guidance, or QA supervisor recommendations.</span>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-xs text-muted">Inspection results will be logged and timestamped under your account.</span>
                <div class="form-actions mt-0">
                    <a href="<?= e(url('modules/quality_inspections/index.php')) ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" <?= empty($batches) ? 'disabled' : '' ?>>Save Inspection Audit</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Defect Row Template for Vanilla JS -->
<template id="defectRowTemplate">
    <tr class="defect-row">
        <td>
            <select name="defect_id[]" class="form-control defect-select" required>
                <option value="">-- Select Defect Type --</option>
                <?php foreach ($activeDefects as $ad): ?>
                    <option value="<?= (int) $ad['defect_id'] ?>">
                        <?= e($ad['defect_code']) ?> &mdash; <?= e($ad['defect_name']) ?> (<?= e($ad['category']) ?>, <?= ucfirst(e($ad['severity'])) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number"
                   name="defect_count[]"
                   class="form-control defect-count-input"
                   min="1"
                   step="1"
                   required
                   value="1">
        </td>
        <td>
            <input type="text"
                   name="defect_notes[]"
                   class="form-control"
                   maxlength="255"
                   placeholder="Specific placement, panel, or note...">
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-sm btn-danger btn-remove-row" title="Remove Defect">&times;</button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inspectedInput = document.getElementById('inspected_quantity');
    const defectiveInput = document.getElementById('defective_quantity');
    const passedInput = document.getElementById('passed_quantity');
    const formulaIndicator = document.getElementById('formulaIndicator');
    const overallResultSelect = document.getElementById('overall_result');
    const tbody = document.getElementById('defectsTbody');
    const btnAdd = document.getElementById('btnAddDefectRow');
    const template = document.getElementById('defectRowTemplate');
    const noDefectsNotice = document.getElementById('noDefectsNotice');

    function updatePassedQuantity() {
        const inspected = parseInt(inspectedInput.value, 10) || 0;
        const defective = parseInt(defectiveInput.value, 10) || 0;

        if (inspected >= 0 && defective >= 0) {
            const passed = Math.max(0, inspected - defective);
            passedInput.value = passed;

            if (passed + defective === inspected && inspected > 0) {
                formulaIndicator.textContent = passed + ' Passed + ' + defective + ' Defective = ' + inspected + ' Inspected ✓';
                formulaIndicator.style.color = '#2e7d32';
            } else {
                formulaIndicator.textContent = 'Passed (' + passed + ') + Defective (' + defective + ') must equal Inspected (' + inspected + ')';
                formulaIndicator.style.color = '#c62828';
            }

            // Suggest overall result if user hasn't actively altered it
            if (defective === 0 && inspected > 0) {
                if (overallResultSelect.value === 'Failed') {
                    overallResultSelect.value = 'Passed';
                }
            } else if (defective > 0) {
                if (overallResultSelect.value === 'Passed') {
                    overallResultSelect.value = 'Failed';
                }
            }
        }
        checkNotice();
    }

    function checkNotice() {
        if (tbody.children.length === 0) {
            noDefectsNotice.style.display = 'block';
        } else {
            noDefectsNotice.style.display = 'none';
        }
    }

    inspectedInput.addEventListener('input', updatePassedQuantity);
    defectiveInput.addEventListener('input', updatePassedQuantity);

    // Initial calculation on load
    updatePassedQuantity();

    btnAdd.addEventListener('click', function () {
        const clone = template.content.cloneNode(true);
        tbody.appendChild(clone);
        checkNotice();
    });

    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-remove-row') || e.target.closest('.btn-remove-row')) {
            const row = e.target.closest('tr');
            if (row) {
                row.remove();
                checkNotice();
            }
        }
    });

    // Form submission client-side pre-validation
    const form = document.getElementById('inspectionForm');
    form.addEventListener('submit', function (e) {
        const inspected = parseInt(inspectedInput.value, 10) || 0;
        const defective = parseInt(defectiveInput.value, 10) || 0;
        const passed = parseInt(passedInput.value, 10) || 0;

        if (inspected <= 0) {
            alert('Inspected quantity must be greater than zero.');
            inspectedInput.focus();
            e.preventDefault();
            return;
        }

        if (passed + defective !== inspected) {
            alert('Passed quantity (' + passed + ') + defective quantity (' + defective + ') must equal inspected quantity (' + inspected + ').');
            e.preventDefault();
            return;
        }

        if (defective === 0 && tbody.children.length > 0) {
            alert('Defect rows should not be submitted when defective quantity is 0.');
            e.preventDefault();
            return;
        }

        if (defective > 0 && tbody.children.length === 0) {
            alert('Please add at least one defect type row for the ' + defective + ' defective garment(s).');
            e.preventDefault();
            return;
        }

        // Check for duplicate defect types and individual counts <= defective
        const selectedDefects = new Set();
        const rows = tbody.querySelectorAll('tr.defect-row');
        for (let i = 0; i < rows.length; i++) {
            const select = rows[i].querySelector('.defect-select');
            const countInput = rows[i].querySelector('.defect-count-input');
            const dVal = select ? select.value : '';
            const cVal = countInput ? parseInt(countInput.value, 10) || 0 : 0;

            if (!dVal) {
                alert('Please select a valid defect type on all defect rows.');
                select.focus();
                e.preventDefault();
                return;
            }

            if (selectedDefects.has(dVal)) {
                alert('Duplicate defect type selected. Each defect type in an inspection must be unique.');
                select.focus();
                e.preventDefault();
                return;
            }
            selectedDefects.add(dVal);

            if (cVal <= 0) {
                alert('Defect count must be a positive integer (at least 1).');
                countInput.focus();
                e.preventDefault();
                return;
            }

            if (cVal > defective) {
                alert('Defect count (' + cVal + ') cannot exceed the total defective quantity (' + defective + ').');
                countInput.focus();
                e.preventDefault();
                return;
            }
        }
    });
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
