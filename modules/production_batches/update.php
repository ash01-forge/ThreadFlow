<?php
/**
 * ThreadFlow - Update Production Batch Handler
 * 
 * Module 3: Assigned to Member 3.
 * Access restricted to Admin and Production Manager roles.
 * POST-only handler for updating existing production batch records.
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
$batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
if (!$batchId || $batchId <= 0) {
    flash_set('error', 'Invalid production batch ID specified.');
    redirect('modules/production_batches/index.php');
}

// Verify batch exists in database
try {
    $checkStmt = $conn->prepare("SELECT batch_id FROM production_batches WHERE batch_id = ? LIMIT 1");
    if (!$checkStmt) {
        error_log("Failed to prepare existence check in update batch: " . $conn->error);
        flash_set('error', 'A database error occurred. Please try again.');
        redirect('modules/production_batches/index.php');
    }
    $checkStmt->bind_param("i", $batchId);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();
    $existing = $checkRes->fetch_assoc();
    $checkStmt->close();

    if (!$existing) {
        flash_set('error', 'Production batch record not found.');
        redirect('modules/production_batches/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception verifying batch existence: " . $e->getMessage());
    flash_set('error', 'A database error occurred. Please try again.');
    redirect('modules/production_batches/index.php');
}

// Extract and trim inputs
$batchNumber     = trim($_POST['batch_number'] ?? '');
$orderIdRaw      = trim($_POST['order_id'] ?? '');
$lineIdRaw       = trim($_POST['line_id'] ?? '');
$plannedQtyRaw   = trim($_POST['planned_quantity'] ?? '');
$status          = trim($_POST['status'] ?? 'Planned');
$startDate       = trim($_POST['start_date'] ?? '');
$targetEndDate   = trim($_POST['target_end_date'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

$errors = [];

// 1. Batch Number Validation & Uniqueness check excluding current batch
if ($batchNumber === '') {
    $errors['batch_number'] = 'Batch Number is required.';
} elseif (mb_strlen($batchNumber) > 50) {
    $errors['batch_number'] = 'Batch Number cannot exceed 50 characters.';
} elseif (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $batchNumber)) {
    $errors['batch_number'] = 'Batch Number can only contain alphanumeric characters, hyphens, dots, and underscores.';
} else {
    try {
        $uniqStmt = $conn->prepare("SELECT batch_id FROM production_batches WHERE batch_number = ? AND batch_id != ? LIMIT 1");
        if ($uniqStmt) {
            $uniqStmt->bind_param("si", $batchNumber, $batchId);
            $uniqStmt->execute();
            $uniqRes = $uniqStmt->get_result();
            if ($uniqRes && $uniqRes->num_rows > 0) {
                $errors['batch_number'] = "Batch number '{$batchNumber}' is already in use by another batch.";
            }
            $uniqStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception checking batch uniqueness in update: " . $e->getMessage());
        $errors['batch_number'] = 'Unable to verify batch number uniqueness at this time.';
    }
}

// 2. Buyer Order Validation (Mandatory relation)
$orderId = filter_var($orderIdRaw, FILTER_VALIDATE_INT);
if (!$orderId || $orderId <= 0) {
    $errors['order_id'] = 'A valid Buyer Order must be selected.';
} else {
    try {
        $ordStmt = $conn->prepare("SELECT order_id FROM buyer_orders WHERE order_id = ? LIMIT 1");
        if ($ordStmt) {
            $ordStmt->bind_param("i", $orderId);
            $ordStmt->execute();
            $ordRes = $ordStmt->get_result();
            if (!$ordRes || $ordRes->num_rows === 0) {
                $errors['order_id'] = 'The selected buyer order does not exist in the database.';
            }
            $ordStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception checking order existence in update: " . $e->getMessage());
        $errors['order_id'] = 'Unable to verify buyer order at this time.';
    }
}

// 3. Sewing Line Validation (Optional relation)
$lineId = null;
if ($lineIdRaw !== '') {
    $lineIdVal = filter_var($lineIdRaw, FILTER_VALIDATE_INT);
    if (!$lineIdVal || $lineIdVal <= 0) {
        $errors['line_id'] = 'Invalid sewing line selection.';
    } else {
        try {
            $lnStmt = $conn->prepare("SELECT line_id FROM sewing_lines WHERE line_id = ? LIMIT 1");
            if ($lnStmt) {
                $lnStmt->bind_param("i", $lineIdVal);
                $lnStmt->execute();
                $lnRes = $lnStmt->get_result();
                if (!$lnRes || $lnRes->num_rows === 0) {
                    $errors['line_id'] = 'The selected sewing line does not exist in the database.';
                } else {
                    $lineId = $lineIdVal;
                }
                $lnStmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Database exception checking sewing line existence in update: " . $e->getMessage());
            $errors['line_id'] = 'Unable to verify sewing line at this time.';
        }
    }
}

// 4. Planned Quantity Validation (> 0 required)
$plannedQty = filter_var($plannedQtyRaw, FILTER_VALIDATE_INT);
if ($plannedQty === false || $plannedQty <= 0) {
    $errors['planned_quantity'] = 'Planned Quantity must be a positive integer greater than zero.';
}

// 5. Status Validation
$validStatuses = ['Planned', 'In Progress', 'On Hold', 'Completed'];
if (!in_array($status, $validStatuses, true)) {
    $errors['status'] = 'Invalid batch status selected.';
}

// 6. Timeline Validation (Target End Date not earlier than Start Date)
$validStartDate = false;
$validEndDate = false;

if ($startDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $errors['start_date'] = 'A valid start date (YYYY-MM-DD) is required.';
} else {
    $validStartDate = true;
}

if ($targetEndDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetEndDate)) {
    $errors['target_end_date'] = 'A valid target end date (YYYY-MM-DD) is required.';
} else {
    $validEndDate = true;
}

if ($validStartDate && $validEndDate) {
    if (strtotime($targetEndDate) < strtotime($startDate)) {
        $errors['target_end_date'] = 'Target End Date cannot be earlier than Start Date.';
    }
}

// 7. Notes Validation (Optional, max 1000 chars)
if ($notes !== '' && mb_strlen($notes) > 1000) {
    $errors['notes'] = 'Notes cannot exceed 1000 characters.';
}

// If validation errors exist, preserve input and redirect back to edit form
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = [
        'batch_number'     => $batchNumber,
        'order_id'         => $orderIdRaw,
        'line_id'          => $lineIdRaw,
        'planned_quantity' => $plannedQtyRaw,
        'status'           => $status,
        'start_date'       => $startDate,
        'target_end_date'  => $targetEndDate,
        'notes'            => $notes,
    ];
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/production_batches/edit.php?id=' . $batchId);
}

// Execute UPDATE query using prepared statements
try {
    $updateSql = "UPDATE production_batches 
                  SET batch_number = ?, 
                      order_id = ?, 
                      line_id = ?, 
                      planned_quantity = ?, 
                      status = ?, 
                      start_date = ?, 
                      target_end_date = ?, 
                      notes = ? 
                  WHERE batch_id = ?";

    $stmt = $conn->prepare($updateSql);
    if (!$stmt) {
        error_log("Failed to prepare UPDATE production_batches: " . $conn->error);
        flash_set('error', 'A database error occurred while updating the batch.');
        $_SESSION['_form_old'] = $_POST;
        redirect('modules/production_batches/edit.php?id=' . $batchId);
    }

    $notesParam = $notes !== '' ? $notes : null;

    $stmt->bind_param(
        "siisssssi",
        $batchNumber,
        $orderId,
        $lineId,
        $plannedQty,
        $status,
        $startDate,
        $targetEndDate,
        $notesParam,
        $batchId
    );

    $stmt->execute();
    $stmt->close();

    flash_set('success', "Production Batch '{$batchNumber}' updated successfully.");
    redirect('modules/production_batches/view.php?id=' . $batchId);
} catch (mysqli_sql_exception $e) {
    error_log("Database exception updating production batch: " . $e->getMessage());
    flash_set('error', 'A database error occurred while updating the production batch.');
    $_SESSION['_form_old'] = $_POST;
    redirect('modules/production_batches/edit.php?id=' . $batchId);
}
