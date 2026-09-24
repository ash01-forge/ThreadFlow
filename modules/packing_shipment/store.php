<?php
/**
 * ThreadFlow - Store Packing Shipment Handler
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * POST-only handler for creating new packing shipments with transactional integrity.
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
$createdBy   = (int) $currentUser['user_id'];

// Extract inputs
$packingNumber   = trim($_POST['packing_number'] ?? '');
$inspectionId    = filter_input(INPUT_POST, 'inspection_id', FILTER_VALIDATE_INT);
$packedQtyRaw    = trim((string)($_POST['packed_quantity'] ?? ''));
$cartonCountRaw  = trim((string)($_POST['carton_count'] ?? '1'));
$packingDate     = trim($_POST['packing_date'] ?? '');
$shippingDate    = trim($_POST['shipping_date'] ?? '');
$shippingMethod  = trim($_POST['shipping_method'] ?? 'Sea');
$carrierName     = trim($_POST['carrier_name'] ?? '');
$trackingNumber  = trim($_POST['tracking_number'] ?? '');
$destination     = trim($_POST['destination'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

// New shipments must ALWAYS start with status = 'Packed'
$status = 'Packed';

$errors = [];

// 1. Validate Packing Number
if ($packingNumber === '') {
    $errors['packing_number'] = 'Packing Number is required.';
} elseif (mb_strlen($packingNumber) > 50) {
    $errors['packing_number'] = 'Packing Number cannot exceed 50 characters.';
} elseif (!preg_match('/^[A-Za-z0-9\-_#\/ ]+$/', $packingNumber)) {
    $errors['packing_number'] = 'Packing Number contains invalid characters.';
} else {
    try {
        $uStmt = $conn->prepare("SELECT packing_id FROM packing_shipments WHERE packing_number = ? LIMIT 1");
        if ($uStmt) {
            $uStmt->bind_param("s", $packingNumber);
            $uStmt->execute();
            if ($uStmt->get_result()->fetch_assoc()) {
                $errors['packing_number'] = "Packing number '{$packingNumber}' is already in use.";
            }
            $uStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error verifying packing_number uniqueness: " . $e->getMessage());
        $errors['packing_number'] = 'Database error verifying packing number uniqueness.';
    }
}

// 2. Validate Inspection Selection
if (!$inspectionId || $inspectionId <= 0) {
    $errors['inspection_id'] = 'Please select a valid passed quality inspection.';
}

// 3. Validate Packed Quantity
if ($packedQtyRaw === '' || !ctype_digit($packedQtyRaw) || (int)$packedQtyRaw <= 0) {
    $errors['packed_quantity'] = 'Packed Quantity must be a whole positive integer.';
}
$packedQuantity = (int) $packedQtyRaw;

// 4. Validate Carton Count
if ($cartonCountRaw === '' || !ctype_digit($cartonCountRaw) || (int)$cartonCountRaw <= 0) {
    $errors['carton_count'] = 'Carton Count must be at least 1.';
}
$cartonCount = (int) $cartonCountRaw;

// 5. Validate Packing Date
if ($packingDate === '') {
    $errors['packing_date'] = 'Packing Date is required.';
} else {
    $pDate = DateTime::createFromFormat('Y-m-d', $packingDate);
    if (!$pDate || $pDate->format('Y-m-d') !== $packingDate) {
        $errors['packing_date'] = 'Please provide a valid packing date (YYYY-MM-DD).';
    }
}

// 6. Validate Shipping Date (optional for Packed, but if present must be >= packing_date)
$shippingDateVal = null;
if ($shippingDate !== '') {
    $sDate = DateTime::createFromFormat('Y-m-d', $shippingDate);
    if (!$sDate || $sDate->format('Y-m-d') !== $shippingDate) {
        $errors['shipping_date'] = 'Shipping Date must be a valid date in YYYY-MM-DD format.';
    } elseif (empty($errors['packing_date']) && $shippingDate < $packingDate) {
        $errors['shipping_date'] = 'Shipping Date cannot be earlier than Packing Date.';
    } else {
        $shippingDateVal = $shippingDate;
    }
}

// 7. Validate Shipping Method
$allowedMethods = ['Sea', 'Air', 'Road', 'Courier'];
if (!in_array($shippingMethod, $allowedMethods, true)) {
    $errors['shipping_method'] = 'Invalid shipping method selected.';
}

// 8. Validate Carrier Name & Tracking Number
if (mb_strlen($carrierName) > 100) {
    $errors['carrier_name'] = 'Carrier Name cannot exceed 100 characters.';
}
if (mb_strlen($trackingNumber) > 100) {
    $errors['tracking_number'] = 'Tracking / B/L Number cannot exceed 100 characters.';
}

// 9. Validate Destination
if ($destination === '') {
    $errors['destination'] = 'Destination Port or Warehouse is required.';
} elseif (mb_strlen($destination) > 150) {
    $errors['destination'] = 'Destination cannot exceed 150 characters.';
}

// Redirect back if early validation errors exist
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old']    = $_POST;
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/packing_shipment/create.php');
}

// =============================================================================
// Transactional Database Persistence with Row-Level Locking
// =============================================================================
$conn->begin_transaction();

try {
    // 1. Lock the selected quality_inspections row with SELECT ... FOR UPDATE
    // Calculate all non-cancelled packed quantities (do not use packing_id != ?)
    $lockSql = "SELECT 
        qi.inspection_id,
        qi.batch_id,
        qi.passed_quantity,
        qi.overall_result,
        qi.inspection_date,
        (
            SELECT COALESCE(SUM(ps.packed_quantity), 0)
            FROM packing_shipments ps
            WHERE ps.inspection_id = qi.inspection_id
              AND ps.status != 'Cancelled'
        ) AS total_packed_quantity
    FROM quality_inspections qi
    WHERE qi.inspection_id = ?
    FOR UPDATE";

    $lockStmt = $conn->prepare($lockSql);
    if (!$lockStmt) {
        throw new Exception("Failed to prepare inspection lock statement: " . $conn->error);
    }

    $lockStmt->bind_param("i", $inspectionId);
    $lockStmt->execute();
    $inspectionRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    // Verify inspection existence and Passed status
    if (!$inspectionRow) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['inspection_id' => 'The selected quality inspection does not exist.'];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', 'Selected quality inspection record was not found.');
        redirect('modules/packing_shipment/create.php');
    }

    if ($inspectionRow['overall_result'] !== 'Passed') {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['inspection_id' => 'Only inspections with overall result "Passed" are eligible for packing.'];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', 'The selected inspection has not passed quality assurance.');
        redirect('modules/packing_shipment/create.php');
    }

    // 2. Derive batch_id directly from the locked inspection record (never trust browser batch_id)
    $derivedBatchId = (int) $inspectionRow['batch_id'];

    // Ensure packing date is on or after inspection date
    if ($packingDate < $inspectionRow['inspection_date']) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['packing_date' => "Packing date cannot be earlier than the inspection date ({$inspectionRow['inspection_date']})."];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', 'Packing date must be on or after the quality inspection date.');
        redirect('modules/packing_shipment/create.php');
    }

    // 3. Recalculate available capacity
    $passedQty      = (int) $inspectionRow['passed_quantity'];
    $alreadyPacked  = (int) $inspectionRow['total_packed_quantity'];
    $availableUnits = $passedQty - $alreadyPacked;

    // 4. Validate capacity
    if ($packedQuantity > $availableUnits) {
        $conn->rollback();
        $_SESSION['_form_errors'] = [
            'packed_quantity' => "Requested quantity ({$packedQuantity} pcs) exceeds remaining available passed capacity ({$availableUnits} pcs)."
        ];
        $_SESSION['_form_old'] = $_POST;
        flash_set('error', 'Capacity conflict detected. The remaining passed quantity has changed or was exceeded.');
        redirect('modules/packing_shipment/create.php');
    }

    // 5. Only after all validations pass, perform INSERT
    $insSql = "INSERT INTO packing_shipments (
        packing_number,
        batch_id,
        inspection_id,
        packed_quantity,
        carton_count,
        packing_date,
        shipping_date,
        shipping_method,
        carrier_name,
        tracking_number,
        destination,
        status,
        notes,
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Packed', ?, ?)";

    $stmt = $conn->prepare($insSql);
    if (!$stmt) {
        throw new Exception("Failed to prepare INSERT into packing_shipments: " . $conn->error);
    }

    $carrierParam  = $carrierName !== '' ? $carrierName : null;
    $trackingParam = $trackingNumber !== '' ? $trackingNumber : null;
    $notesParam    = $notes !== '' ? $notes : null;

    $stmt->bind_param(
        "siiiisssssssi",
        $packingNumber,
        $derivedBatchId,
        $inspectionId,
        $packedQuantity,
        $cartonCount,
        $packingDate,
        $shippingDateVal,
        $shippingMethod,
        $carrierParam,
        $trackingParam,
        $destination,
        $notesParam,
        $createdBy
    );

    $stmt->execute();
    $newPackingId = $conn->insert_id;
    $stmt->close();

    // Commit the active transaction
    $conn->commit();

    flash_set('success', "Packing shipment '{$packingNumber}' created successfully ({$packedQuantity} pcs, {$cartonCount} cartons). Initial status: Packed.");
    redirect('modules/packing_shipment/view.php?id=' . $newPackingId);

} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("Transaction error storing packing shipment: " . $e->getMessage());
    $_SESSION['_form_errors'] = ['general' => 'A database error occurred while saving the packing shipment. Please try again.'];
    $_SESSION['_form_old']    = $_POST;
    flash_set('error', 'Failed to save packing shipment due to an unexpected system error.');
    redirect('modules/packing_shipment/create.php');
}
