<?php
/**
 * ThreadFlow - Update Quality Inspection Handler
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * POST-only handler for updating existing quality inspection audits with transactional integrity.
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

$inspectionId = filter_input(INPUT_POST, 'inspection_id', FILTER_VALIDATE_INT);
if (!$inspectionId || $inspectionId <= 0) {
    flash_set('error', 'Invalid inspection ID for update.');
    redirect('modules/quality_inspections/index.php');
}

// Verify existence of record
try {
    $cStmt = $conn->prepare("SELECT inspection_id, inspection_number, batch_id, inspector_id, inspection_date, inspection_stage, inspected_quantity, passed_quantity, defective_quantity, overall_result, remarks FROM quality_inspections WHERE inspection_id = ? LIMIT 1");
    if (!$cStmt) {
        throw new Exception($conn->error);
    }
    $cStmt->bind_param("i", $inspectionId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    $existing = $cRes->fetch_assoc();
    $cStmt->close();

    if (!$existing) {
        flash_set('error', 'Quality inspection record not found.');
        redirect('modules/quality_inspections/index.php');
    }

    // Retrieve existing defects breakdown for audit diff
    $existingDefects = [];
    $dOldStmt = $conn->prepare("SELECT defect_id, defect_count, notes FROM inspection_defects WHERE inspection_id = ?");
    if ($dOldStmt) {
        $dOldStmt->bind_param("i", $inspectionId);
        $dOldStmt->execute();
        $existingDefects = $dOldStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $dOldStmt->close();
    }
} catch (Exception $e) {
    error_log("Error verifying inspection existence: " . $e->getMessage());
    flash_set('error', 'Database error verifying inspection record.');
    redirect('modules/quality_inspections/index.php');
}

// Allowed domain lists
$allowedStages  = ['Inline', 'End of Line', 'Final Pre-Shipment'];
$allowedResults = ['Passed', 'Failed', 'Pending Review'];

// Extract inputs
$inspectionNumber = trim($_POST['inspection_number'] ?? '');
$batchId          = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
$inspectionDate   = trim($_POST['inspection_date'] ?? '');
$inspectionStage  = trim($_POST['inspection_stage'] ?? '');
$overallResult    = trim($_POST['overall_result'] ?? '');
$remarks          = trim($_POST['remarks'] ?? '');

$inspectedRaw = trim((string)($_POST['inspected_quantity'] ?? ''));
$defectiveRaw = trim((string)($_POST['defective_quantity'] ?? ''));
$passedRaw    = trim((string)($_POST['passed_quantity'] ?? ''));

$errors = [];

// 1. Validate Inspection Number (unique excluding current record)
if ($inspectionNumber === '') {
    $errors['inspection_number'] = 'Inspection Number is required.';
} elseif (mb_strlen($inspectionNumber) > 50) {
    $errors['inspection_number'] = 'Inspection Number cannot exceed 50 characters.';
} else {
    try {
        $uStmt = $conn->prepare("SELECT inspection_id FROM quality_inspections WHERE inspection_number = ? AND inspection_id != ? LIMIT 1");
        if ($uStmt) {
            $uStmt->bind_param("si", $inspectionNumber, $inspectionId);
            $uStmt->execute();
            $uRes = $uStmt->get_result();
            if ($uRes->fetch_assoc()) {
                $errors['inspection_number'] = "Inspection number '{$inspectionNumber}' is already in use by another record.";
            }
            $uStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error checking inspection_number uniqueness: " . $e->getMessage());
    }
}

// 2. Validate Production Batch
$batchPlannedQty = 0;
if (!$batchId || $batchId <= 0) {
    $errors['batch_id'] = 'Please select a valid production batch.';
} else {
    try {
        $bStmt = $conn->prepare("SELECT batch_id, planned_quantity FROM production_batches WHERE batch_id = ? LIMIT 1");
        if ($bStmt) {
            $bStmt->bind_param("i", $batchId);
            $bStmt->execute();
            $bRes = $bStmt->get_result();
            $batchRow = $bRes->fetch_assoc();
            $bStmt->close();

            if (!$batchRow) {
                $errors['batch_id'] = 'The selected production batch does not exist.';
            } else {
                $batchPlannedQty = (int) $batchRow['planned_quantity'];
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error checking batch existence: " . $e->getMessage());
        $errors['batch_id'] = 'Database error verifying production batch.';
    }
}

// 3. Validate Inspection Date
if ($inspectionDate === '') {
    $errors['inspection_date'] = 'Inspection Date is required.';
} else {
    $d = DateTime::createFromFormat('Y-m-d', $inspectionDate);
    if (!$d || $d->format('Y-m-d') !== $inspectionDate) {
        $errors['inspection_date'] = 'Please provide a valid date in YYYY-MM-DD format.';
    }
}

// 4. Validate Inspection Stage
if ($inspectionStage === '' || !in_array($inspectionStage, $allowedStages, true)) {
    $errors['inspection_stage'] = 'Please select a valid inspection stage.';
}

// 5. Validate Overall Result
if ($overallResult === '' || !in_array($overallResult, $allowedResults, true)) {
    $errors['overall_result'] = 'Please select a valid overall result.';
}

// 6. Validate Quantities
if ($inspectedRaw === '' || !ctype_digit($inspectedRaw) || (int)$inspectedRaw <= 0) {
    $errors['inspected_quantity'] = 'Inspected Quantity must be a whole number greater than 0.';
}
if ($defectiveRaw === '' || !ctype_digit($defectiveRaw) || (int)$defectiveRaw < 0) {
    $errors['defective_quantity'] = 'Defective Quantity must be a whole number (0 or greater).';
}
if ($passedRaw === '' || !ctype_digit($passedRaw) || (int)$passedRaw < 0) {
    $errors['passed_quantity'] = 'Passed Quantity must be a whole number (0 or greater).';
}

$inspectedQty = (int) $inspectedRaw;
$defectiveQty = (int) $defectiveRaw;
$passedQty    = (int) $passedRaw;

// Formula validation: passed + defective = inspected
if (!isset($errors['inspected_quantity']) && !isset($errors['defective_quantity']) && !isset($errors['passed_quantity'])) {
    if (($passedQty + $defectiveQty) !== $inspectedQty) {
        $errors['quantity_rule'] = "Quantity rule violation: Passed quantity ({$passedQty}) + Defective quantity ({$defectiveQty}) must equal Inspected quantity ({$inspectedQty}).";
    }

    if ($batchPlannedQty > 0 && $inspectedQty > $batchPlannedQty) {
        $errors['inspected_quantity'] = "Inspected quantity ({$inspectedQty}) cannot exceed batch planned quantity ({$batchPlannedQty} pcs).";
    }
}

// 7. Validate Defect Rows
$defectIds    = $_POST['defect_id'] ?? [];
$defectCounts = $_POST['defect_count'] ?? [];
$defectNotes  = $_POST['defect_notes'] ?? [];

$cleanedDefects = [];

if (is_array($defectIds)) {
    foreach ($defectIds as $idx => $rawDefectId) {
        $dId = filter_var($rawDefectId, FILTER_VALIDATE_INT);
        $dCount = filter_var($defectCounts[$idx] ?? null, FILTER_VALIDATE_INT);
        $dNote = trim((string)($defectNotes[$idx] ?? ''));

        if ($dId && $dId > 0) {
            $cleanedDefects[] = [
                'defect_id' => $dId,
                'count'     => $dCount,
                'notes'     => mb_substr($dNote, 0, 255),
            ];
        }
    }
}

if ($defectiveQty === 0) {
    if (!empty($cleanedDefects)) {
        $errors['defects'] = 'Defect rows should not be submitted when defective quantity is 0.';
    }
} else {
    if (empty($cleanedDefects)) {
        $errors['defects'] = "At least one defect type must be added when defective quantity is {$defectiveQty}.";
    } else {
        $seenDefectIds = [];
        foreach ($cleanedDefects as $cd) {
            if (in_array($cd['defect_id'], $seenDefectIds, true)) {
                $errors['defects'] = 'Each selected defect type must be unique. Duplicate defect type entries are not permitted.';
                break;
            }
            $seenDefectIds[] = $cd['defect_id'];

            if ($cd['count'] === false || $cd['count'] <= 0) {
                $errors['defects'] = 'Each defect count must be a positive integer of at least 1.';
                break;
            }

            if ($cd['count'] > $defectiveQty) {
                $errors['defects'] = "Defect count ({$cd['count']}) cannot exceed the total defective quantity ({$defectiveQty}).";
                break;
            }
        }

        if (empty($errors['defects'])) {
            try {
                $placeholders = implode(',', array_fill(0, count($seenDefectIds), '?'));
                $typeStr = str_repeat('i', count($seenDefectIds));
                $dtStmt = $conn->prepare("SELECT defect_id FROM defect_types WHERE defect_id IN ($placeholders)");
                if ($dtStmt) {
                    $dtStmt->bind_param($typeStr, ...$seenDefectIds);
                    $dtStmt->execute();
                    $dtRes = $dtStmt->get_result();
                    $foundCount = $dtRes->num_rows;
                    $dtStmt->close();

                    if ($foundCount !== count($seenDefectIds)) {
                        $errors['defects'] = 'One or more selected defect types are invalid.';
                    }
                }
            } catch (mysqli_sql_exception $e) {
                error_log("Error verifying defect types on update: " . $e->getMessage());
                $errors['defects'] = 'Database error verifying defect types.';
            }
        }
    }
}

// Redirect back if validation failed
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/quality_inspections/edit.php?id=' . $inspectionId);
}

// Begin Database Transaction
$conn->begin_transaction();

try {
    // 1. Update parent quality inspection
    $upSql = "UPDATE quality_inspections SET 
                inspection_number = ?,
                batch_id = ?,
                inspection_date = ?,
                inspection_stage = ?,
                inspected_quantity = ?,
                passed_quantity = ?,
                defective_quantity = ?,
                overall_result = ?,
                remarks = ?
              WHERE inspection_id = ?";

    $stmt = $conn->prepare($upSql);
    if (!$stmt) {
        throw new Exception("Failed to prepare UPDATE for quality_inspections: " . $conn->error);
    }

    $remarksParam = $remarks !== '' ? $remarks : null;
    $stmt->bind_param(
        "sissiiissi",
        $inspectionNumber,
        $batchId,
        $inspectionDate,
        $inspectionStage,
        $inspectedQty,
        $passedQty,
        $defectiveQty,
        $overallResult,
        $remarksParam,
        $inspectionId
    );

    $stmt->execute();
    $stmt->close();

    // 2. Clear old child inspection defect records
    $delStmt = $conn->prepare("DELETE FROM inspection_defects WHERE inspection_id = ?");
    if (!$delStmt) {
        throw new Exception("Failed to prepare DELETE for inspection_defects: " . $conn->error);
    }
    $delStmt->bind_param("i", $inspectionId);
    $delStmt->execute();
    $delStmt->close();

    // 3. Re-insert updated child inspection defect rows (if defective_quantity > 0)
    if ($defectiveQty > 0 && !empty($cleanedDefects)) {
        $insChild = $conn->prepare("INSERT INTO inspection_defects (inspection_id, defect_id, defect_count, notes) VALUES (?, ?, ?, ?)");
        if (!$insChild) {
            throw new Exception("Failed to prepare INSERT for inspection_defects: " . $conn->error);
        }

        foreach ($cleanedDefects as $cd) {
            $notesParam = $cd['notes'] !== '' ? $cd['notes'] : null;
            $insChild->bind_param("iiis", $inspectionId, $cd['defect_id'], $cd['count'], $notesParam);
            $insChild->execute();
        }
        $insChild->close();
    }

    // Record audit event inside the active transaction
    audit_log([
        'action'            => 'UPDATE',
        'module'            => 'quality_inspections',
        'table_name'        => 'quality_inspections',
        'record_id'         => $inspectionId,
        'record_identifier' => $inspectionNumber,
        'old_values'        => [
            'inspection_number'  => $existing['inspection_number'],
            'batch_id'           => (int)$existing['batch_id'],
            'inspection_date'    => $existing['inspection_date'],
            'inspection_stage'   => $existing['inspection_stage'],
            'inspected_quantity' => (int)$existing['inspected_quantity'],
            'passed_quantity'    => (int)$existing['passed_quantity'],
            'defective_quantity' => (int)$existing['defective_quantity'],
            'overall_result'     => $existing['overall_result'],
            'remarks'            => $existing['remarks'],
            'defects'            => $existingDefects,
        ],
        'new_values'        => [
            'inspection_number'  => $inspectionNumber,
            'batch_id'           => $batchId,
            'inspection_date'    => $inspectionDate,
            'inspection_stage'   => $inspectionStage,
            'inspected_quantity' => $inspectedQty,
            'passed_quantity'    => $passedQty,
            'defective_quantity' => $defectiveQty,
            'overall_result'     => $overallResult,
            'remarks'            => $remarksParam,
            'defects'            => $cleanedDefects,
        ],
    ]);

    // Commit transaction
    $conn->commit();

    flash_set('success', "Quality inspection '{$inspectionNumber}' updated successfully.");
    redirect('modules/quality_inspections/view.php?id=' . $inspectionId);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Transaction error updating quality inspection: " . $e->getMessage());

    if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
        flash_set('error', "Inspection number '{$inspectionNumber}' is already registered. Inspection numbers must be unique.");
    } else {
        flash_set('error', 'A database error occurred while updating the quality inspection. Please try again.');
    }

    $_SESSION['_form_old'] = $_POST;
    redirect('modules/quality_inspections/edit.php?id=' . $inspectionId);
}
