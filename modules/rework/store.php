<?php
/**
 * ThreadFlow - Store Rework Job Handler
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * POST-only handler for creating new rework jobs with transactional integrity.
 */
require_once __DIR__ . '/../../includes/auth.php';

// Enforce role authorization
require_role(['admin', 'qc_officer']);

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Enforce CSRF protection
require_csrf();

$currentUser = auth_user();
// Identity strictly derived from authenticated session
$createdBy = (int) $currentUser['user_id'];

// Extract inputs
$reworkNumber       = trim($_POST['rework_number'] ?? '');
$inspectionDefectId = filter_input(INPUT_POST, 'inspection_defect_id', FILTER_VALIDATE_INT);
$assignedTo         = trim($_POST['assigned_to'] ?? '');
$lineIdRaw          = trim($_POST['line_id'] ?? '');
$reworkQtyRaw       = trim((string)($_POST['rework_quantity'] ?? ''));
$assignedDate       = trim($_POST['assigned_date'] ?? '');
$targetDate         = trim($_POST['target_completion_date'] ?? '');
$status             = trim($_POST['status'] ?? 'Pending');
$correctiveAction   = trim($_POST['corrective_action'] ?? '');
$remarks            = trim($_POST['remarks'] ?? '');

$errors = [];

// 1. Validate Rework Number
if ($reworkNumber === '') {
    $errors['rework_number'] = 'Rework Number is required.';
} elseif (mb_strlen($reworkNumber) > 50) {
    $errors['rework_number'] = 'Rework Number cannot exceed 50 characters.';
} else {
    // Uniqueness check against rework_jobs
    try {
        $uStmt = $conn->prepare("SELECT rework_id FROM rework_jobs WHERE rework_number = ? LIMIT 1");
        if ($uStmt) {
            $uStmt->bind_param("s", $reworkNumber);
            $uStmt->execute();
            $uRes = $uStmt->get_result();
            if ($uRes->fetch_assoc()) {
                $errors['rework_number'] = "Rework number '{$reworkNumber}' is already in use.";
            }
            $uStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error checking rework_number uniqueness: " . $e->getMessage());
        $errors['rework_number'] = 'Database error verifying rework number.';
    }
}

// 2. Validate Source Inspection Defect & Available Quantity
$availableQty = 0;
if (!$inspectionDefectId || $inspectionDefectId <= 0) {
    $errors['inspection_defect_id'] = 'Please select a valid source inspection defect.';
} else {
    try {
        $defStmt = $conn->prepare("SELECT 
            idf.inspection_defect_id,
            idf.defect_count,
            COALESCE(SUM(rj.rework_quantity), 0) AS allocated_quantity
        FROM inspection_defects idf
        LEFT JOIN rework_jobs rj ON idf.inspection_defect_id = rj.inspection_defect_id
        WHERE idf.inspection_defect_id = ?
        GROUP BY idf.inspection_defect_id
        LIMIT 1");

        if ($defStmt) {
            $defStmt->bind_param("i", $inspectionDefectId);
            $defStmt->execute();
            $defRow = $defStmt->get_result()->fetch_assoc();
            $defStmt->close();

            if (!$defRow) {
                $errors['inspection_defect_id'] = 'The selected inspection defect record does not exist.';
            } else {
                $defectTotal = (int) $defRow['defect_count'];
                $defectAllocated = (int) $defRow['allocated_quantity'];
                $availableQty = $defectTotal - $defectAllocated;

                if ($availableQty <= 0) {
                    $errors['inspection_defect_id'] = "This defect's quantity ({$defectTotal} pcs) is already fully allocated to existing rework jobs.";
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error checking inspection defect available quantity: " . $e->getMessage());
        $errors['inspection_defect_id'] = 'Database error verifying source defect.';
    }
}

// 3. Validate Rework Quantity
if ($reworkQtyRaw === '' || !ctype_digit($reworkQtyRaw) || (int)$reworkQtyRaw <= 0) {
    $errors['rework_quantity'] = 'Rework Quantity must be a whole number greater than 0.';
} else {
    $reworkQty = (int) $reworkQtyRaw;
    if ($availableQty > 0 && $reworkQty > $availableQty) {
        $errors['rework_quantity'] = "Rework quantity ({$reworkQty}) cannot exceed the unallocated defective quantity ({$availableQty} pcs).";
    }
}

// 4. Validate Assigned To
if ($assignedTo === '') {
    $errors['assigned_to'] = 'Assigned Person, Team, or Station is required.';
} elseif (mb_strlen($assignedTo) > 150) {
    $errors['assigned_to'] = 'Assigned to description cannot exceed 150 characters.';
}

// 5. Validate Sewing Line (Optional)
$lineId = null;
if ($lineIdRaw !== '') {
    $lineIdVal = filter_var($lineIdRaw, FILTER_VALIDATE_INT);
    if ($lineIdVal && $lineIdVal > 0) {
        try {
            $lStmt = $conn->prepare("SELECT line_id FROM sewing_lines WHERE line_id = ? AND is_active = 1 LIMIT 1");
            if ($lStmt) {
                $lStmt->bind_param("i", $lineIdVal);
                $lStmt->execute();
                if ($lStmt->get_result()->fetch_assoc()) {
                    $lineId = $lineIdVal;
                } else {
                    $errors['line_id'] = 'The selected sewing line does not exist or is inactive.';
                }
                $lStmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Error verifying sewing line: " . $e->getMessage());
            $errors['line_id'] = 'Database error verifying sewing line.';
        }
    } else {
        $errors['line_id'] = 'Invalid sewing line selection.';
    }
}

// 6. Validate Assigned Date & Target Completion Date
if ($assignedDate === '') {
    $errors['assigned_date'] = 'Assigned Date is required.';
} else {
    $ad = DateTime::createFromFormat('Y-m-d', $assignedDate);
    if (!$ad || $ad->format('Y-m-d') !== $assignedDate) {
        $errors['assigned_date'] = 'Please provide a valid assigned date in YYYY-MM-DD format.';
    }
}

if ($targetDate === '') {
    $errors['target_completion_date'] = 'Target Completion Date is required.';
} else {
    $td = DateTime::createFromFormat('Y-m-d', $targetDate);
    if (!$td || $td->format('Y-m-d') !== $targetDate) {
        $errors['target_completion_date'] = 'Please provide a valid target date in YYYY-MM-DD format.';
    }
}

if (!isset($errors['assigned_date']) && !isset($errors['target_completion_date'])) {
    if ($targetDate < $assignedDate) {
        $errors['target_completion_date'] = 'Target completion date cannot be earlier than assigned date.';
    }
}

// 7. Validate Status
$allowedInitialStatuses = ['Pending', 'In Progress'];
if (!in_array($status, $allowedInitialStatuses, true)) {
    $errors['status'] = 'Initial status must be either Pending or In Progress.';
}

// If validation errors exist, preserve inputs and redirect back
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old']    = $_POST;
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/rework/create.php');
}

// Transactional Database Persistence
$conn->begin_transaction();

try {
    // Re-verify unallocated quantity under transaction to prevent race conditions
    $lockStmt = $conn->prepare("SELECT 
        idf.defect_count,
        COALESCE(SUM(rj.rework_quantity), 0) AS allocated_quantity
    FROM inspection_defects idf
    LEFT JOIN rework_jobs rj ON idf.inspection_defect_id = rj.inspection_defect_id
    WHERE idf.inspection_defect_id = ?
    GROUP BY idf.inspection_defect_id
    FOR UPDATE");

    if (!$lockStmt) {
        throw new Exception("Failed to prepare verification statement: " . $conn->error);
    }

    $lockStmt->bind_param("i", $inspectionDefectId);
    $lockStmt->execute();
    $lockRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$lockRow) {
        throw new Exception("Inspection defect was not found during transaction.");
    }

    $liveAvailable = (int)$lockRow['defect_count'] - (int)$lockRow['allocated_quantity'];
    $finalReworkQty = (int)$reworkQtyRaw;

    if ($finalReworkQty > $liveAvailable) {
        $conn->rollback();
        $_SESSION['_form_errors'] = [
            'rework_quantity' => "Defective quantity allocation conflict: Only {$liveAvailable} pcs are currently available."
        ];
        $_SESSION['_form_old'] = $_POST;
        flash_set('error', 'Quantity conflict detected. The remaining defective quantity was changed by another action.');
        redirect('modules/rework/create.php');
    }

    // Insert new rework job
    $insSql = "INSERT INTO rework_jobs (
        rework_number,
        inspection_defect_id,
        line_id,
        assigned_to,
        rework_quantity,
        assigned_date,
        target_completion_date,
        status,
        corrective_action,
        remarks,
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($insSql);
    if (!$stmt) {
        throw new Exception("Failed to prepare INSERT into rework_jobs: " . $conn->error);
    }

    $correctiveParam = $correctiveAction !== '' ? $correctiveAction : null;
    $remarksParam    = $remarks !== '' ? $remarks : null;

    $stmt->bind_param(
        "siisisssssi",
        $reworkNumber,
        $inspectionDefectId,
        $lineId,
        $assignedTo,
        $finalReworkQty,
        $assignedDate,
        $targetDate,
        $status,
        $correctiveParam,
        $remarksParam,
        $createdBy
    );

    $stmt->execute();
    $newReworkId = $conn->insert_id;
    $stmt->close();

    $conn->commit();

    flash_set('success', "Rework job '{$reworkNumber}' created and assigned successfully ({$finalReworkQty} pcs).");
    redirect('modules/rework/view.php?id=' . $newReworkId);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Transaction failed storing rework job: " . $e->getMessage());
    $_SESSION['_form_errors'] = ['general' => 'A database error occurred while saving the rework job.'];
    $_SESSION['_form_old']    = $_POST;
    flash_set('error', 'Failed to save rework job due to a database error. Please try again.');
    redirect('modules/rework/create.php');
}
