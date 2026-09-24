<?php
/**
 * ThreadFlow - Update Rework Job Handler
 *
 * Module 4: Member 4 implementation.
 * Access restricted to Admin and QC Officer roles.
 * Handles POST requests to modify an existing rework job.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

// Get current user for audit fields
$currentUser = auth_user();

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// CSRF protection
require_csrf();

// Retrieve POST values
$reworkId = filter_input(INPUT_POST, 'rework_id', FILTER_VALIDATE_INT);
if (!$reworkId || $reworkId <= 0) {
    flash_set('error', 'Invalid Rework Job ID.');
    redirect('modules/rework/index.php');
}

// Extract fields (trim to remove surrounding whitespace)
$reworkNumber           = trim($_POST['rework_number'] ?? ''); // read‑only but sent for safety
$inspectionDefectId    = filter_input(INPUT_POST, 'inspection_defect_id', FILTER_VALIDATE_INT);
$assignedTo            = trim($_POST['assigned_to'] ?? '');
$lineId                = filter_input(INPUT_POST, 'line_id', FILTER_VALIDATE_INT);
$reworkQuantity        = filter_input(INPUT_POST, 'rework_quantity', FILTER_VALIDATE_INT);
$assignedDate          = trim($_POST['assigned_date'] ?? '');
$targetCompletionDate  = trim($_POST['target_completion_date'] ?? '');
$completedQuantity     = filter_input(INPUT_POST, 'completed_quantity', FILTER_VALIDATE_INT);
$rejectedQuantity      = filter_input(INPUT_POST, 'rejected_quantity', FILTER_VALIDATE_INT);
$actualCompletionDate  = trim($_POST['actual_completion_date'] ?? '');
$status                = trim($_POST['status'] ?? '');
$correctiveAction      = trim($_POST['corrective_action'] ?? '');
$remarks               = trim($_POST['remarks'] ?? '');

$errors = [];

// 1. Validate rework number (must match existing record, immutable)
if ($reworkNumber === '') {
    $errors['rework_number'] = 'Rework Number missing.';
} else {
    // Ensure the number belongs to this job (prevent tampering)
    $checkStmt = $conn->prepare('SELECT rework_id, rework_number, inspection_defect_id, line_id, assigned_to, rework_quantity, assigned_date, target_completion_date, completed_quantity, rejected_quantity, actual_completion_date, status, corrective_action, remarks, created_by FROM rework_jobs WHERE rework_number = ? LIMIT 1');
    if ($checkStmt) {
        $checkStmt->bind_param('s', $reworkNumber);
        $checkStmt->execute();
        $res = $checkStmt->get_result();
        $existingJob = $res->fetch_assoc();
        $checkStmt->close();
        if (!$existingJob || (int)$existingJob['rework_id'] !== $reworkId) {
            $errors['rework_number'] = 'Rework Number does not match the selected job.';
        }
    } else {
        $errors['rework_number'] = 'Database error verifying rework number.';
    }
}

// 2. Validate inspection_defect_id (must exist)
if (!$inspectionDefectId || $inspectionDefectId <= 0) {
    $errors['inspection_defect_id'] = 'Source inspection defect is required.';
}

// 3. Assigned To – required, max 150 chars
if ($assignedTo === '') {
    $errors['assigned_to'] = 'Assigned Person / Team is required.';
} elseif (mb_strlen($assignedTo) > 150) {
    $errors['assigned_to'] = 'Assigned Person / Team cannot exceed 150 characters.';
}

// 4. Line ID – optional but if provided must exist & be active
if ($lineId !== null && $lineId !== false && $lineId > 0) {
    $lineStmt = $conn->prepare('SELECT line_id FROM sewing_lines WHERE line_id = ? AND is_active = 1 LIMIT 1');
    if ($lineStmt) {
        $lineStmt->bind_param('i', $lineId);
        $lineStmt->execute();
        $lRes = $lineStmt->get_result();
        if (!$lRes->fetch_assoc()) {
            $errors['line_id'] = 'Selected sewing line does not exist or is inactive.';
        }
        $lineStmt->close();
    } else {
        $errors['line_id'] = 'Database error checking sewing line.';
    }
}

// 5. Rework Quantity – required, positive integer
if ($reworkQuantity === false || $reworkQuantity === null || $reworkQuantity <= 0) {
    $errors['rework_quantity'] = 'Rework quantity must be a positive integer.';
}

// 6. Dates validation (YYYY-MM-DD)
$assignedDt = DateTime::createFromFormat('Y-m-d', $assignedDate);
if (!$assignedDt || $assignedDt->format('Y-m-d') !== $assignedDate) {
    $errors['assigned_date'] = 'Assigned Date must be a valid YYYY‑MM‑DD date.';
}
$targetDt = DateTime::createFromFormat('Y-m-d', $targetCompletionDate);
if (!$targetDt || $targetDt->format('Y-m-d') !== $targetCompletionDate) {
    $errors['target_completion_date'] = 'Target Completion Date must be a valid YYYY‑MM‑DD date.';
}
if (empty($errors['assigned_date']) && empty($errors['target_completion_date'])) {
    if ($targetDt < $assignedDt) {
        $errors['target_completion_date'] = 'Target Completion Date cannot be earlier than Assigned Date.';
    }
}

// 7. Status – must be one of allowed values
$allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Rejected'];
if (!in_array($status, $allowedStatuses, true)) {
    $errors['status'] = 'Invalid job status.';
}

// 8. Completed & Rejected quantities – optional but must be non‑negative integers
if ($completedQuantity === false || $completedQuantity === null || $completedQuantity < 0) {
    $errors['completed_quantity'] = 'Completed quantity must be a non‑negative integer.';
}
if ($rejectedQuantity === false || $rejectedQuantity === null || $rejectedQuantity < 0) {
    $errors['rejected_quantity'] = 'Rejected quantity must be a non‑negative integer.';
}

// 9. Progress sum rule
if (!isset($errors['rework_quantity']) && !isset($errors['completed_quantity']) && !isset($errors['rejected_quantity'])) {
    $sum = $completedQuantity + $rejectedQuantity;
    if ($sum > $reworkQuantity) {
        $errors['progress'] = 'Sum of Completed and Rejected quantities cannot exceed the total Rework Quantity.';
    }
}

// 10. Actual Completion Date – required if status is Completed, must be >= assigned date
if ($status === 'Completed') {
    $actualDt = DateTime::createFromFormat('Y-m-d', $actualCompletionDate);
    if (!$actualDt || $actualDt->format('Y-m-d') !== $actualCompletionDate) {
        $errors['actual_completion_date'] = 'Actual Completion Date is required and must be a valid date.';
    } elseif ($actualDt < $assignedDt) {
        $errors['actual_completion_date'] = 'Actual Completion Date cannot be earlier than Assigned Date.';
    }
    // Also ensure progress totals match quantity when completing
    if (!isset($errors['progress'])) {
        if ($sum !== $reworkQuantity) {
            $errors['progress'] = 'When marking as Completed, Completed + Rejected must equal the total Rework Quantity.';
        }
    }
}

// 11. Validate that requested rework_quantity does not exceed available defect quantity.
// This must consider other allocated reworks for the same inspection defect.
if (empty($errors)) {
    try {
        // Lock the defect row to safely compute available quantity.
        $conn->begin_transaction();
        $lockStmt = $conn->prepare('SELECT defect_count, (SELECT COALESCE(SUM(rework_quantity),0) FROM rework_jobs WHERE inspection_defect_id = ? AND rework_id != ?) AS allocated FROM inspection_defects WHERE inspection_defect_id = ? FOR UPDATE');
        if (!$lockStmt) {
            throw new Exception('Failed to prepare defect lock query: ' . $conn->error);
        }
        $lockStmt->bind_param('iii', $inspectionDefectId, $reworkId, $inspectionDefectId);
        $lockStmt->execute();
        $lockRes = $lockStmt->get_result();
        $defRow = $lockRes->fetch_assoc();
        $lockStmt->close();
        if (!$defRow) {
            $errors['inspection_defect_id'] = 'Source inspection defect not found.';
        } else {
            $defectTotal = (int)$defRow['defect_count'];
            $alreadyAllocated = (int)$defRow['allocated']; // excludes current job because of rework_id != ?
            $available = $defectTotal - $alreadyAllocated;
            if ($reworkQuantity > $available) {
                $errors['rework_quantity'] = "Requested quantity ({$reworkQuantity}) exceeds remaining available quantity ({$available}) for this defect.";
            }
        }
        // Note: transaction will be committed/rolled back later based on success.
    } catch (Exception $e) {
        error_log('Error validating defect availability: ' . $e->getMessage());
        $errors['inspection_defect_id'] = 'Database error during availability check.';
    }
}

// If validation fails, store errors and old input, then redirect back to edit page.
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', 'Please correct the highlighted errors.');
    // Ensure transaction is rolled back if started.
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    redirect('modules/rework/edit.php?id=' . $reworkId);
}

// All validation passed – perform update inside transaction.
try {
    // Update the rework job record.
    $updateSql = "UPDATE rework_jobs SET
        inspection_defect_id = ?,
        assigned_to = ?,
        line_id = ?,
        rework_quantity = ?,
        assigned_date = ?,
        target_completion_date = ?,
        completed_quantity = ?,
        rejected_quantity = ?,
        actual_completion_date = ?,
        status = ?,
        corrective_action = ?,
        remarks = ?,
        updated_by = ?,
        updated_at = CURRENT_TIMESTAMP
        WHERE rework_id = ?";
    $updStmt = $conn->prepare($updateSql);
    if (!$updStmt) {
        throw new Exception('Failed to prepare update statement: ' . $conn->error);
    }
    $currentUserId = $currentUser['user_id'] ?? null;
    $updStmt->bind_param(
        'issisiiisssis',
        $inspectionDefectId,
        $assignedTo,
        $lineId,
        $reworkQuantity,
        $assignedDate,
        $targetCompletionDate,
        $completedQuantity,
        $rejectedQuantity,
        $actualCompletionDate ?: null,
        $status,
        $correctiveAction ?: null,
        $remarks ?: null,
        $currentUserId,
        $reworkId
    );
    $updStmt->execute();
    $updStmt->close();

    // Record audit event inside the active transaction
    audit_log([
        'action'            => 'UPDATE',
        'module'            => 'rework',
        'table_name'        => 'rework_jobs',
        'record_id'         => $reworkId,
        'record_identifier' => $reworkNumber,
        'old_values'        => [
            'inspection_defect_id'   => isset($existingJob['inspection_defect_id']) ? (int)$existingJob['inspection_defect_id'] : null,
            'line_id'                => isset($existingJob['line_id']) && $existingJob['line_id'] !== null ? (int)$existingJob['line_id'] : null,
            'assigned_to'            => $existingJob['assigned_to'] ?? null,
            'rework_quantity'        => isset($existingJob['rework_quantity']) ? (int)$existingJob['rework_quantity'] : null,
            'assigned_date'          => $existingJob['assigned_date'] ?? null,
            'target_completion_date' => $existingJob['target_completion_date'] ?? null,
            'completed_quantity'     => isset($existingJob['completed_quantity']) ? (int)$existingJob['completed_quantity'] : null,
            'rejected_quantity'      => isset($existingJob['rejected_quantity']) ? (int)$existingJob['rejected_quantity'] : null,
            'actual_completion_date' => $existingJob['actual_completion_date'] ?? null,
            'status'                 => $existingJob['status'] ?? null,
            'corrective_action'      => $existingJob['corrective_action'] ?? null,
            'remarks'                => $existingJob['remarks'] ?? null,
        ],
        'new_values'        => [
            'inspection_defect_id'   => $inspectionDefectId,
            'line_id'                => $lineId,
            'assigned_to'            => $assignedTo,
            'rework_quantity'        => $reworkQuantity,
            'assigned_date'          => $assignedDate,
            'target_completion_date' => $targetCompletionDate,
            'completed_quantity'     => $completedQuantity,
            'rejected_quantity'      => $rejectedQuantity,
            'actual_completion_date' => $actualCompletionDate ?: null,
            'status'                 => $status,
            'corrective_action'      => $correctiveAction ?: null,
            'remarks'                => $remarks ?: null,
        ],
    ]);

    // Commit transaction
    $conn->commit();

    flash_set('success', "Rework Job '{$reworkNumber}' updated successfully.");
    redirect('modules/rework/view.php?id=' . $reworkId);
} catch (Exception $e) {
    // Rollback on any error
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log('Transaction error updating rework job: ' . $e->getMessage());
    $_SESSION['_form_errors'] = ['general' => 'An unexpected error occurred while updating the job.'];
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', 'Failed to update the rework job. Please try again.');
    redirect('modules/rework/edit.php?id=' . $reworkId);
}
?>
