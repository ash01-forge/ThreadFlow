<?php
/**
 * ThreadFlow - Update Stage Progress Handler
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 * POST-only handler for updating existing stage progress records.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'production_manager']);

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Enforce CSRF token
require_csrf();

// Validate positive integer ID
$progressId = filter_input(INPUT_POST, 'progress_id', FILTER_VALIDATE_INT);
if (!$progressId || $progressId <= 0) {
    flash_set('error', 'Invalid stage progress record ID specified.');
    redirect('modules/stage_progress/index.php');
}

// Verify record exists in database
try {
    $chkStmt = $conn->prepare("SELECT progress_id, batch_id, stage_id FROM stage_progress WHERE progress_id = ? LIMIT 1");
    if (!$chkStmt) {
        error_log("Failed to prepare existence check in update stage progress: " . $conn->error);
        flash_set('error', 'A database error occurred. Please try again.');
        redirect('modules/stage_progress/index.php');
    }
    $chkStmt->bind_param("i", $progressId);
    $chkStmt->execute();
    $chkRes = $chkStmt->get_result();
    $existing = $chkRes->fetch_assoc();
    $chkStmt->close();

    if (!$existing) {
        flash_set('error', 'Stage progress record not found.');
        redirect('modules/stage_progress/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception verifying stage progress existence: " . $e->getMessage());
    flash_set('error', 'A database error occurred. Please try again.');
    redirect('modules/stage_progress/index.php');
}

// Extract and trim inputs
$batchIdRaw   = trim($_POST['batch_id'] ?? '');
$stageIdRaw   = trim($_POST['stage_id'] ?? '');
$inputQtyRaw  = trim($_POST['input_quantity'] ?? '');
$outputQtyRaw = trim($_POST['output_quantity'] ?? '0');
$status       = trim($_POST['status'] ?? 'In Progress');
$startTimeRaw = trim($_POST['start_time'] ?? '');
$endTimeRaw   = trim($_POST['end_time'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');

$errors = [];

// 1. Production Batch Validation (Must exist)
$batchId = filter_var($batchIdRaw, FILTER_VALIDATE_INT);
$batch   = null;

if (!$batchId || $batchId <= 0) {
    $errors['batch_id'] = 'A valid Production Batch must be selected.';
} else {
    try {
        $bStmt = $conn->prepare("SELECT batch_id, batch_number, planned_quantity, status FROM production_batches WHERE batch_id = ? LIMIT 1");
        if ($bStmt) {
            $bStmt->bind_param("i", $batchId);
            $bStmt->execute();
            $bRes = $bStmt->get_result();
            $batch = $bRes->fetch_assoc();
            $bStmt->close();

            if (!$batch) {
                $errors['batch_id'] = 'The selected production batch does not exist in the database.';
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception verifying batch in update stage progress: " . $e->getMessage());
        $errors['batch_id'] = 'Unable to verify production batch at this time.';
    }
}

// 2. Production Stage Validation (Must exist)
$stageId      = filter_var($stageIdRaw, FILTER_VALIDATE_INT);
$currentStage = null;

if (!$stageId || $stageId <= 0) {
    $errors['stage_id'] = 'A valid Production Stage must be selected.';
} else {
    try {
        $sStmt = $conn->prepare("SELECT stage_id, stage_name, stage_code, sequence_number, is_active FROM production_stages WHERE stage_id = ? LIMIT 1");
        if ($sStmt) {
            $sStmt->bind_param("i", $stageId);
            $sStmt->execute();
            $sRes = $sStmt->get_result();
            $currentStage = $sRes->fetch_assoc();
            $sStmt->close();

            if (!$currentStage) {
                $errors['stage_id'] = 'The selected production stage does not exist in the database.';
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception verifying stage in update stage progress: " . $e->getMessage());
        $errors['stage_id'] = 'Unable to verify production stage at this time.';
    }
}

// 3. Duplicate Prevention: only one progress record per batch & stage excluding current record
if ($batch && $currentStage) {
    try {
        $dupStmt = $conn->prepare("SELECT progress_id FROM stage_progress WHERE batch_id = ? AND stage_id = ? AND progress_id != ? LIMIT 1");
        if ($dupStmt) {
            $dupStmt->bind_param("iii", $batchId, $stageId, $progressId);
            $dupStmt->execute();
            $dupRes = $dupStmt->get_result();
            if ($dupRes && $dupRes->num_rows > 0) {
                $errors['stage_id'] = "Progress for stage '{$currentStage['stage_name']}' has already been recorded for this batch.";
            }
            $dupStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception checking duplicate in update stage progress: " . $e->getMessage());
        $errors['stage_id'] = 'Unable to verify stage uniqueness at this time.';
    }
}

// 4. Input Quantity Validation (Positive integer > 0)
$inputQty = filter_var($inputQtyRaw, FILTER_VALIDATE_INT);
if ($inputQty === false || $inputQty <= 0) {
    $errors['input_quantity'] = 'Input quantity must be a positive integer greater than zero.';
}

// 5. Output Quantity Validation (Zero or positive integer >= 0)
$outputQty = filter_var($outputQtyRaw, FILTER_VALIDATE_INT);
if ($outputQty === false || $outputQty < 0) {
    $errors['output_quantity'] = 'Output quantity must be zero or a positive integer.';
}

// 6. Quantity Upper Bound: output cannot exceed input
if ($inputQty !== false && $inputQty > 0 && $outputQty !== false && $outputQty >= 0) {
    if ($outputQty > $inputQty) {
        $errors['output_quantity'] = "Output quantity ({$outputQty}) cannot exceed input quantity ({$inputQty}).";
    }
}

// 7. Input Quantity cannot exceed selected batch planned quantity
if ($batch && $inputQty !== false && $inputQty > 0) {
    $plannedQty = (int)$batch['planned_quantity'];
    if ($inputQty > $plannedQty) {
        $errors['input_quantity'] = "Input quantity (" . number_format($inputQty) . ") cannot exceed the batch planned quantity (" . number_format($plannedQty) . " pcs).";
    }
}

// 8. Preceding Stage Completion Check:
// For a later stage, input quantity cannot exceed the completed output of the immediately preceding stage.
if ($batch && $currentStage && $inputQty !== false && $inputQty > 0) {
    try {
        $currentSeq = (int)$currentStage['sequence_number'];
        $prevStageStmt = $conn->prepare("SELECT stage_id, stage_name, stage_code, sequence_number 
                                         FROM production_stages 
                                         WHERE sequence_number < ? 
                                         ORDER BY sequence_number DESC 
                                         LIMIT 1");
        if ($prevStageStmt) {
            $prevStageStmt->bind_param("i", $currentSeq);
            $prevStageStmt->execute();
            $prevStageRes = $prevStageStmt->get_result();
            $precedingStage = $prevStageRes->fetch_assoc();
            $prevStageStmt->close();

            if ($precedingStage) {
                $precProgStmt = $conn->prepare("SELECT progress_id, status, output_quantity, input_quantity 
                                                FROM stage_progress 
                                                WHERE batch_id = ? AND stage_id = ? 
                                                LIMIT 1");
                if ($precProgStmt) {
                    $precProgStmt->bind_param("ii", $batchId, $precedingStage['stage_id']);
                    $precProgStmt->execute();
                    $precProgRes = $precProgStmt->get_result();
                    $precedingProgress = $precProgRes->fetch_assoc();
                    $precProgStmt->close();

                    if (!$precedingProgress) {
                        $errors['input_quantity'] = "Cannot record stage '{$currentStage['stage_name']}': The immediately preceding stage '{$precedingStage['stage_name']}' has no recorded progress for this batch yet.";
                    } elseif ($precedingProgress['status'] !== 'Completed') {
                        $errors['input_quantity'] = "Cannot record stage '{$currentStage['stage_name']}': The immediately preceding stage '{$precedingStage['stage_name']}' is not yet completed (current status: {$precedingProgress['status']}).";
                    } else {
                        $completedOutput = (int)$precedingProgress['output_quantity'];
                        if ($inputQty > $completedOutput) {
                            $errors['input_quantity'] = "Input quantity (" . number_format($inputQty) . ") cannot exceed the completed output (" . number_format($completedOutput) . " pcs) of the immediately preceding stage '{$precedingStage['stage_name']}'.";
                        }
                    }
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception verifying preceding stage output in update: " . $e->getMessage());
        $errors['input_quantity'] = 'Unable to verify preceding stage status at this time.';
    }
}

// 9. Subsequent Stage Integrity Check:
// Ensure output is not reduced below any recorded subsequent stage's input
if ($batch && $currentStage && $outputQty !== false && $outputQty >= 0) {
    try {
        $currentSeq = (int)$currentStage['sequence_number'];
        $nextStageStmt = $conn->prepare("SELECT ps.stage_name, sp.input_quantity 
                                         FROM stage_progress sp 
                                         INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id 
                                         WHERE sp.batch_id = ? AND ps.sequence_number > ? 
                                         ORDER BY ps.sequence_number ASC 
                                         LIMIT 1");
        if ($nextStageStmt) {
            $nextStageStmt->bind_param("ii", $batchId, $currentSeq);
            $nextStageStmt->execute();
            $nextStageRes = $nextStageStmt->get_result();
            $subsequent = $nextStageRes->fetch_assoc();
            $nextStageStmt->close();

            if ($subsequent && $outputQty < (int)$subsequent['input_quantity']) {
                $errors['output_quantity'] = "Output quantity cannot be reduced below " . number_format((int)$subsequent['input_quantity']) . " pcs because subsequent stage '{$subsequent['stage_name']}' has already accepted that input volume.";
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception verifying subsequent stage in update: " . $e->getMessage());
    }
}

// 10. Status Validation
$validStatuses = ['In Progress', 'On Hold', 'Completed'];
if (!in_array($status, $validStatuses, true)) {
    $errors['status'] = 'Invalid status selected. Allowed: In Progress, On Hold, Completed.';
}

// 11. Start Time Validation
$startTimeFormatted = null;
$startTs            = false;

if ($startTimeRaw === '') {
    $errors['start_time'] = 'Start Date and Time is required.';
} else {
    $startTs = strtotime($startTimeRaw);
    if ($startTs === false) {
        $errors['start_time'] = 'A valid start date and time is required.';
    } else {
        $startTimeFormatted = date('Y-m-d H:i:s', $startTs);
    }
}

// 12. End Time & Completed Status Validation
$endTimeFormatted = null;

if ($status === 'Completed' && $endTimeRaw === '') {
    $errors['end_time'] = 'An End Date & Time is required when stage status is Completed.';
} elseif ($endTimeRaw !== '') {
    $endTs = strtotime($endTimeRaw);
    if ($endTs === false) {
        $errors['end_time'] = 'Invalid end date and time format.';
    } else {
        if ($startTs !== false && $endTs < $startTs) {
            $errors['end_time'] = 'End time cannot be earlier than start time.';
        } else {
            $endTimeFormatted = date('Y-m-d H:i:s', $endTs);
        }
    }
}

// 13. Remarks Validation (Optional, max 1000 characters)
if ($remarks !== '' && mb_strlen($remarks) > 1000) {
    $errors['remarks'] = 'Remarks cannot exceed 1000 characters.';
}

// If validation errors exist, preserve submitted inputs and redirect back to edit form
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old']    = $_POST;
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/stage_progress/edit.php?id=' . $progressId);
}

// Execute prepared UPDATE query
try {
    $updateSql = "UPDATE stage_progress 
                  SET batch_id = ?, 
                      stage_id = ?, 
                      input_quantity = ?, 
                      output_quantity = ?, 
                      start_time = ?, 
                      end_time = ?, 
                      status = ?, 
                      remarks = ? 
                  WHERE progress_id = ?";

    $stmt = $conn->prepare($updateSql);
    if (!$stmt) {
        error_log("Failed to prepare UPDATE stage_progress: " . $conn->error);
        flash_set('error', 'A database error occurred while updating the stage progress record.');
        $_SESSION['_form_old'] = $_POST;
        redirect('modules/stage_progress/edit.php?id=' . $progressId);
    }

    $remarksParam = ($remarks !== '') ? $remarks : null;

    $stmt->bind_param(
        "iiiissssi",
        $batchId,
        $stageId,
        $inputQty,
        $outputQty,
        $startTimeFormatted,
        $endTimeFormatted,
        $status,
        $remarksParam,
        $progressId
    );

    $stmt->execute();
    $stmt->close();

    $stageNameText = $currentStage['stage_name'] ?? 'Stage';
    flash_set('success', "Stage progress for {$stageNameText} updated successfully.");
    redirect('modules/stage_progress/view.php?id=' . $progressId);
} catch (mysqli_sql_exception $e) {
    error_log("Database exception updating stage progress: " . $e->getMessage());
    flash_set('error', 'A database error occurred while updating the stage progress record.');
    $_SESSION['_form_old'] = $_POST;
    redirect('modules/stage_progress/edit.php?id=' . $progressId);
}
