<?php
/**
 * ThreadFlow - Store Received Fabric Lot Handler
 * 
 * Module 2 (Feature 2): Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 * POST-only handler for creating new fabric receiving records.
 */
require_once __DIR__ . '/../../includes/auth.php';

// Enforce role authorization
require_role(['admin', 'production_manager']);

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Enforce CSRF token
require_csrf();

// Documented allowed units
$allowedUnits = ['Yards', 'Meters', 'Kilograms', 'Rolls'];

// Extract and trim inputs (inspection_status is NOT accepted from POST; always initialized to 'Pending')
$supplierId          = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
$lotNumber           = trim($_POST['lot_number'] ?? '');
$fabricType          = trim($_POST['fabric_type'] ?? '');
$color               = trim($_POST['color'] ?? '');
$receivedQuantityStr = trim($_POST['received_quantity'] ?? '');
$unit                = trim($_POST['unit'] ?? '');
$receivedDate        = trim($_POST['received_date'] ?? '');
$notes               = trim($_POST['notes'] ?? '');
$inspectionStatus    = 'Pending';

$errors = [];
$supplier = null;

// 1. Supplier Validation: Must exist and be active
if (!$supplierId || $supplierId <= 0) {
    $errors['supplier_id'] = 'Please select a valid supplier.';
} else {
    try {
        $stmt = $conn->prepare("SELECT supplier_id, supplier_name, is_active FROM suppliers WHERE supplier_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $supplierId);
            $stmt->execute();
            $result = $stmt->get_result();
            $supplier = $result->fetch_assoc();
            $stmt->close();

            if (!$supplier) {
                $errors['supplier_id'] = 'The selected supplier does not exist.';
            } elseif ((int)$supplier['is_active'] !== 1) {
                $errors['supplier_id'] = 'The selected supplier is inactive. Fabric lots can only be received from active suppliers.';
            }
        } else {
            error_log("Failed to prepare supplier validation query: " . $conn->error);
            $errors['supplier_id'] = 'Unable to verify supplier status due to a database error.';
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception verifying supplier: " . $e->getMessage());
        $errors['supplier_id'] = 'Unable to verify supplier status.';
    }
}

// 2. Lot Number: Required, max 100 chars, composite unique per supplier
if ($lotNumber === '') {
    $errors['lot_number'] = 'Lot / batch number is required.';
} elseif (mb_strlen($lotNumber) > 100) {
    $errors['lot_number'] = 'Lot number cannot exceed 100 characters.';
} elseif ($supplierId && empty($errors['supplier_id'])) {
    try {
        $dupStmt = $conn->prepare("SELECT fabric_lot_id FROM fabric_lots WHERE supplier_id = ? AND lot_number = ? LIMIT 1");
        if ($dupStmt) {
            $dupStmt->bind_param("is", $supplierId, $lotNumber);
            $dupStmt->execute();
            $dupResult = $dupStmt->get_result();
            if ($dupResult->fetch_assoc()) {
                $errors['lot_number'] = "A fabric lot with number '{$lotNumber}' already exists for this supplier.";
            }
            $dupStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception checking duplicate lot number: " . $e->getMessage());
    }
}

// 3. Fabric Type: Required, max 100 chars
if ($fabricType === '') {
    $errors['fabric_type'] = 'Fabric type / construction is required.';
} elseif (mb_strlen($fabricType) > 100) {
    $errors['fabric_type'] = 'Fabric type cannot exceed 100 characters.';
}

// 4. Color: Optional, max 100 chars
if ($color !== '' && mb_strlen($color) > 100) {
    $errors['color'] = 'Color cannot exceed 100 characters.';
}

// 5. Received Quantity: Required, numeric, greater than zero
$receivedQuantity = null;
if ($receivedQuantityStr === '') {
    $errors['received_quantity'] = 'Received quantity is required.';
} elseif (!is_numeric($receivedQuantityStr) || (float)$receivedQuantityStr <= 0) {
    $errors['received_quantity'] = 'Received quantity must be a positive number greater than zero.';
} elseif ((float)$receivedQuantityStr > 99999999.99) {
    $errors['received_quantity'] = 'Received quantity cannot exceed 99,999,999.99.';
} else {
    $receivedQuantity = round((float)$receivedQuantityStr, 2);
}

// 6. Unit: Required, must come from documented allowlist
if ($unit === '') {
    $errors['unit'] = 'Please select a unit of measure.';
} elseif (!in_array($unit, $allowedUnits, true)) {
    $errors['unit'] = 'Invalid unit selected. Allowed options: ' . implode(', ', $allowedUnits) . '.';
}

// 7. Received Date: Required, valid date format (Y-m-d)
if ($receivedDate === '') {
    $errors['received_date'] = 'Received date is required.';
} else {
    $dateObj = DateTime::createFromFormat('Y-m-d', $receivedDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $receivedDate) {
        $errors['received_date'] = 'Please enter a valid date in YYYY-MM-DD format.';
    }
}

// If validation fails, preserve inputs and return with errors
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = [
        'supplier_id'       => $supplierId,
        'lot_number'        => $lotNumber,
        'fabric_type'       => $fabricType,
        'color'             => $color,
        'received_quantity' => $receivedQuantityStr,
        'unit'              => $unit,
        'received_date'     => $receivedDate,
        'notes'             => $notes,
    ];
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/fabric_receiving/create.php');
}

// Insert new fabric lot record using prepared statement (inspection_status always 'Pending')
try {
    $insertSql = "INSERT INTO fabric_lots (supplier_id, lot_number, fabric_type, color, received_quantity, unit, received_date, inspection_status, notes) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        error_log("Failed to prepare INSERT into fabric_lots: " . $conn->error);
        flash_set('error', 'A database error occurred while saving the fabric lot.');
        $_SESSION['_form_old'] = $_POST;
        redirect('modules/fabric_receiving/create.php');
    }

    $colorParam = ($color !== '') ? $color : null;
    $notesParam = ($notes !== '') ? $notes : null;

    $stmt->bind_param(
        "isssdssss",
        $supplierId,
        $lotNumber,
        $fabricType,
        $colorParam,
        $receivedQuantity,
        $unit,
        $receivedDate,
        $inspectionStatus,
        $notesParam
    );

    $stmt->execute();
    $stmt->close();

    $supplierDisplayName = $supplier ? $supplier['supplier_name'] : "Supplier #{$supplierId}";
    flash_set('success', "Fabric lot '{$lotNumber}' received successfully from {$supplierDisplayName} (Status: Pending Inspection).");
    redirect('modules/fabric_receiving/index.php');
} catch (mysqli_sql_exception $e) {
    error_log("Database exception creating fabric lot: " . $e->getMessage());
    if ($e->getCode() === 1062) {
        flash_set('error', "A fabric lot with lot number '{$lotNumber}' already exists for this supplier.");
    } else {
        flash_set('error', 'A database error occurred while saving the fabric lot. Please check database tables.');
    }
    $_SESSION['_form_old'] = $_POST;
    redirect('modules/fabric_receiving/create.php');
}
