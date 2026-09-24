<?php
/**
 * ThreadFlow - Update Packing Shipment Handler
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * Handles POST requests to modify existing shipments with strict transactional locking.
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
$updatedBy   = (int) $currentUser['user_id'];

// Extract inputs
$packingId       = filter_input(INPUT_POST, 'packing_id', FILTER_VALIDATE_INT);
$requestedStatus = trim($_POST['status'] ?? '');
$cartonCountRaw  = trim((string)($_POST['carton_count'] ?? '1'));
$shippingMethod  = trim($_POST['shipping_method'] ?? 'Sea');
$carrierName     = trim($_POST['carrier_name'] ?? '');
$trackingNumber  = trim($_POST['tracking_number'] ?? '');
$destination     = trim($_POST['destination'] ?? '');
$shippingDate    = trim($_POST['shipping_date'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

// Unverified browser inputs for core fields (only applied if current status is 'Packed')
$submittedPackedQtyRaw = trim((string)($_POST['packed_quantity'] ?? ''));
$submittedPackingDate  = trim($_POST['packing_date'] ?? '');

if (!$packingId || $packingId <= 0) {
    flash_set('error', 'Invalid packing shipment ID.');
    redirect('modules/packing_shipment/index.php');
}

// Strict status progression mapping
$transitionMap = [
    'Packed'     => ['Packed', 'In Transit', 'Cancelled'],
    'In Transit' => ['In Transit', 'Shipped', 'Cancelled'],
    'Shipped'    => ['Shipped', 'Delivered']
];

// =============================================================================
// Transactional Execution & Concurrency Locking
// =============================================================================
$conn->begin_transaction();

try {
    // A. Fetch and lock the current shipment inside the transaction
    $shipLockSql = "SELECT 
        packing_id,
        packing_number,
        inspection_id,
        batch_id,
        packed_quantity,
        packing_date,
        status
    FROM packing_shipments
    WHERE packing_id = ?
    FOR UPDATE";

    $shipStmt = $conn->prepare($shipLockSql);
    if (!$shipStmt) {
        throw new Exception("Failed to prepare shipment lock query: " . $conn->error);
    }

    $shipStmt->bind_param("i", $packingId);
    $shipStmt->execute();
    $currentRecord = $shipStmt->get_result()->fetch_assoc();
    $shipStmt->close();

    // B. Verify the record exists
    if (!$currentRecord) {
        $conn->rollback();
        flash_set('error', 'Shipment record was not found.');
        redirect('modules/packing_shipment/index.php');
    }

    $currentStatus = $currentRecord['status'];

    // C. Using the locked current status:
    // 1. Block Delivered and Cancelled records (terminal states)
    if (in_array($currentStatus, ['Delivered', 'Cancelled'], true)) {
        $conn->rollback();
        flash_set('error', "Shipments in '{$currentStatus}' status are in a terminal state and cannot be modified.");
        redirect('modules/packing_shipment/view.php?id=' . $packingId);
    }

    // 2. Validate the requested status using the strict transition map
    $allowedTargets = $transitionMap[$currentStatus] ?? [];
    if (!in_array($requestedStatus, $allowedTargets, true)) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['status' => "Invalid status transition from '{$currentStatus}' to '{$requestedStatus}'."];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', "Cannot transition shipment status from '{$currentStatus}' to '{$requestedStatus}'.");
        redirect('modules/packing_shipment/edit.php?id=' . $packingId);
    }

    // 3. Derive immutable values from database when current status is not 'Packed'
    // Never trust browser-submitted inspection_id, batch_id, packed_quantity or packing_date for non-Packed records
    $inspectionId = (int) $currentRecord['inspection_id'];
    $batchId      = (int) $currentRecord['batch_id'];

    if ($currentStatus !== 'Packed') {
        $finalPackedQuantity = (int) $currentRecord['packed_quantity'];
        $finalPackingDate     = $currentRecord['packing_date'];
    } else {
        // Current status is 'Packed' - user is permitted to adjust quantity and packing date
        if ($submittedPackedQtyRaw === '' || !ctype_digit($submittedPackedQtyRaw) || (int)$submittedPackedQtyRaw <= 0) {
            $conn->rollback();
            $_SESSION['_form_errors'] = ['packed_quantity' => 'Packed Quantity must be a whole positive integer.'];
            $_SESSION['_form_old']    = $_POST;
            flash_set('error', 'Please provide a valid packed quantity.');
            redirect('modules/packing_shipment/edit.php?id=' . $packingId);
        }
        $finalPackedQuantity = (int) $submittedPackedQtyRaw;

        if ($submittedPackingDate === '') {
            $conn->rollback();
            $_SESSION['_form_errors'] = ['packing_date' => 'Packing Date is required.'];
            $_SESSION['_form_old']    = $_POST;
            flash_set('error', 'Please provide a valid packing date.');
            redirect('modules/packing_shipment/edit.php?id=' . $packingId);
        }
        $pDate = DateTime::createFromFormat('Y-m-d', $submittedPackingDate);
        if (!$pDate || $pDate->format('Y-m-d') !== $submittedPackingDate) {
            $conn->rollback();
            $_SESSION['_form_errors'] = ['packing_date' => 'Please provide a valid date in YYYY-MM-DD format.'];
            $_SESSION['_form_old']    = $_POST;
            flash_set('error', 'Invalid packing date format.');
            redirect('modules/packing_shipment/edit.php?id=' . $packingId);
        }
        $finalPackingDate = $submittedPackingDate;
    }

    // Validate carton count
    if ($cartonCountRaw === '' || !ctype_digit($cartonCountRaw) || (int)$cartonCountRaw <= 0) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['carton_count' => 'Carton Count must be at least 1.'];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', 'Please provide a valid carton count.');
        redirect('modules/packing_shipment/edit.php?id=' . $packingId);
    }
    $cartonCount = (int) $cartonCountRaw;

    // Validate shipping method
    $allowedMethods = ['Sea', 'Air', 'Road', 'Courier'];
    if (!in_array($shippingMethod, $allowedMethods, true)) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['shipping_method' => 'Invalid shipping method selected.'];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', 'Invalid shipping method.');
        redirect('modules/packing_shipment/edit.php?id=' . $packingId);
    }

    // Validate destination
    if ($destination === '') {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['destination' => 'Destination Port or Warehouse is required.'];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', 'Destination is required.');
        redirect('modules/packing_shipment/edit.php?id=' . $packingId);
    } elseif (mb_strlen($destination) > 150) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['destination' => 'Destination cannot exceed 150 characters.'];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', 'Destination is too long.');
        redirect('modules/packing_shipment/edit.php?id=' . $packingId);
    }

    // Validate carrier and tracking length
    if (mb_strlen($carrierName) > 100) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['carrier_name' => 'Carrier name cannot exceed 100 characters.'];
        $_SESSION['_form_old']    = $_POST;
        redirect('modules/packing_shipment/edit.php?id=' . $packingId);
    }
    if (mb_strlen($trackingNumber) > 100) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['tracking_number' => 'Tracking number cannot exceed 100 characters.'];
        $_SESSION['_form_old']    = $_POST;
        redirect('modules/packing_shipment/edit.php?id=' . $packingId);
    }

    // D. Lock the related quality_inspections row using SELECT ... FOR UPDATE
    // E. Recalculate other non-cancelled packed quantities while excluding the current packing_id
    $inspLockSql = "SELECT 
        qi.inspection_id,
        qi.batch_id,
        qi.passed_quantity,
        qi.inspection_date,
        (
            SELECT COALESCE(SUM(ps.packed_quantity), 0)
            FROM packing_shipments ps
            WHERE ps.inspection_id = qi.inspection_id
              AND ps.status != 'Cancelled'
              AND ps.packing_id != ?
        ) AS other_packed_quantity
    FROM quality_inspections qi
    WHERE qi.inspection_id = ?
    FOR UPDATE";

    $inspStmt = $conn->prepare($inspLockSql);
    if (!$inspStmt) {
        throw new Exception("Failed to prepare inspection lock query: " . $conn->error);
    }

    $inspStmt->bind_param("ii", $packingId, $inspectionId);
    $inspStmt->execute();
    $inspectionRow = $inspStmt->get_result()->fetch_assoc();
    $inspStmt->close();

    if (!$inspectionRow) {
        throw new Exception("Associated quality inspection not found during update.");
    }

    // Ensure packing date is on or after inspection date
    if ($finalPackingDate < $inspectionRow['inspection_date']) {
        $conn->rollback();
        $_SESSION['_form_errors'] = ['packing_date' => "Packing date cannot be earlier than QC inspection date ({$inspectionRow['inspection_date']})."];
        $_SESSION['_form_old']    = $_POST;
        flash_set('error', 'Packing date must be on or after the QC inspection date.');
        redirect('modules/packing_shipment/edit.php?id=' . $packingId);
    }

    // F. Validate capacity, shipping date, and all other fields
    if ($requestedStatus !== 'Cancelled') {
        $passedQty       = (int) $inspectionRow['passed_quantity'];
        $otherAllocated  = (int) $inspectionRow['other_packed_quantity'];
        $availCapacity   = $passedQty - $otherAllocated;

        if ($finalPackedQuantity > $availCapacity) {
            $conn->rollback();
            $_SESSION['_form_errors'] = [
                'packed_quantity' => "Requested quantity ({$finalPackedQuantity} pcs) exceeds remaining available capacity ({$availCapacity} pcs)."
            ];
            $_SESSION['_form_old'] = $_POST;
            flash_set('error', 'Capacity conflict: not enough unallocated passed quantity available.');
            redirect('modules/packing_shipment/edit.php?id=' . $packingId);
        }
    }

    // Validate shipping_date: strictly required for Shipped and Delivered statuses
    $shippingDateVal = null;
    if (in_array($requestedStatus, ['Shipped', 'Delivered'], true)) {
        if ($shippingDate === '') {
            $conn->rollback();
            $_SESSION['_form_errors'] = ['shipping_date' => "Shipping Date is mandatory when status is '{$requestedStatus}'."];
            $_SESSION['_form_old']    = $_POST;
            flash_set('error', "Shipping Date is required when status is {$requestedStatus}.");
            redirect('modules/packing_shipment/edit.php?id=' . $packingId);
        }
    }

    if ($shippingDate !== '') {
        $sDate = DateTime::createFromFormat('Y-m-d', $shippingDate);
        if (!$sDate || $sDate->format('Y-m-d') !== $shippingDate) {
            $conn->rollback();
            $_SESSION['_form_errors'] = ['shipping_date' => 'Shipping Date must be a valid date in YYYY-MM-DD format.'];
            $_SESSION['_form_old']    = $_POST;
            flash_set('error', 'Invalid shipping date format.');
            redirect('modules/packing_shipment/edit.php?id=' . $packingId);
        } elseif ($shippingDate < $finalPackingDate) {
            $conn->rollback();
            $_SESSION['_form_errors'] = ['shipping_date' => 'Shipping Date cannot be earlier than Packing Date.'];
            $_SESSION['_form_old']    = $_POST;
            flash_set('error', 'Shipping Date cannot be earlier than Packing Date.');
            redirect('modules/packing_shipment/edit.php?id=' . $packingId);
        } else {
            $shippingDateVal = $shippingDate;
        }
    }

    // G. Run UPDATE, then COMMIT
    // packing_number is excluded to guarantee immutability
    $updateSql = "UPDATE packing_shipments SET
        packed_quantity = ?,
        carton_count = ?,
        packing_date = ?,
        shipping_date = ?,
        shipping_method = ?,
        carrier_name = ?,
        tracking_number = ?,
        destination = ?,
        status = ?,
        notes = ?,
        updated_by = ?,
        updated_at = CURRENT_TIMESTAMP
    WHERE packing_id = ?";

    $updStmt = $conn->prepare($updateSql);
    if (!$updStmt) {
        throw new Exception("Failed to prepare UPDATE packing_shipments query: " . $conn->error);
    }

    $carrierParam  = $carrierName !== '' ? $carrierName : null;
    $trackingParam = $trackingNumber !== '' ? $trackingNumber : null;
    $notesParam    = $notes !== '' ? $notes : null;

    $updStmt->bind_param(
        "iissssssssii",
        $finalPackedQuantity,
        $cartonCount,
        $finalPackingDate,
        $shippingDateVal,
        $shippingMethod,
        $carrierParam,
        $trackingParam,
        $destination,
        $requestedStatus,
        $notesParam,
        $updatedBy,
        $packingId
    );

    $updStmt->execute();
    $updStmt->close();

    $conn->commit();

    flash_set('success', "Shipment '{$currentRecord['packing_number']}' updated successfully. Current status: {$requestedStatus}.");
    redirect('modules/packing_shipment/view.php?id=' . $packingId);

} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("Transaction error updating packing shipment ID {$packingId}: " . $e->getMessage());
    $_SESSION['_form_errors'] = ['general' => 'A system error occurred while updating the shipment. Please try again.'];
    $_SESSION['_form_old']    = $_POST;
    flash_set('error', 'Failed to update shipment due to a database error.');
    redirect('modules/packing_shipment/edit.php?id=' . $packingId);
}
