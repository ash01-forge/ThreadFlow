<?php
/**
 * ThreadFlow - Dedicated Status Transition Handler for Packing & Shipment
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * POST-only endpoint enforcing transactional locking and strict forward transitions.
 */
require_once __DIR__ . '/../../includes/auth.php';

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
$packingId     = filter_input(INPUT_POST, 'packing_id', FILTER_VALIDATE_INT);
$requestedNext = trim($_POST['next_status'] ?? '');
$shippingDate  = trim($_POST['shipping_date'] ?? '');

if (!$packingId || $packingId <= 0) {
    flash_set('error', 'Invalid shipment ID specified.');
    redirect('modules/packing_shipment/index.php');
}

// Strict status progression mapping
$transitionMap = [
    'Packed'     => ['In Transit', 'Cancelled'],
    'In Transit' => ['Shipped', 'Cancelled'],
    'Shipped'    => ['Delivered']
];

// =============================================================================
// Transactional Execution & Concurrency Locking
// =============================================================================
$conn->begin_transaction();

try {
    // 1. Lock the shipment row inside the transaction
    $lockSql = "SELECT 
        packing_id,
        packing_number,
        inspection_id,
        batch_id,
        packed_quantity,
        packing_date,
        shipping_date,
        status
    FROM packing_shipments
    WHERE packing_id = ?
    FOR UPDATE";

    $lockStmt = $conn->prepare($lockSql);
    if (!$lockStmt) {
        throw new Exception("Failed to prepare shipment status lock query: " . $conn->error);
    }

    $lockStmt->bind_param("i", $packingId);
    $lockStmt->execute();
    $currentRecord = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$currentRecord) {
        $conn->rollback();
        flash_set('error', 'Shipment record was not found.');
        redirect('modules/packing_shipment/index.php');
    }

    $currentStatus = $currentRecord['status'];
    $packingNumber = $currentRecord['packing_number'];
    $packingDate   = $currentRecord['packing_date'];

    // 2. Validate current status: block Delivered and Cancelled (terminal states)
    if (in_array($currentStatus, ['Delivered', 'Cancelled'], true)) {
        $conn->rollback();
        flash_set('error', "Shipment '{$packingNumber}' is in terminal status '{$currentStatus}' and cannot be altered.");
        redirect('modules/packing_shipment/view.php?id=' . $packingId);
    }

    // 3. Validate requested status against strict transition map
    $allowedTransitions = $transitionMap[$currentStatus] ?? [];
    if (!in_array($requestedNext, $allowedTransitions, true)) {
        $conn->rollback();
        flash_set('error', "Invalid status transition from '{$currentStatus}' to '{$requestedNext}'.");
        redirect('modules/packing_shipment/view.php?id=' . $packingId);
    }

    // 4. Require shipping_date for Shipped or Delivered status
    $resolvedShippingDate = !empty($currentRecord['shipping_date']) ? $currentRecord['shipping_date'] : null;

    if (in_array($requestedNext, ['Shipped', 'Delivered'], true)) {
        if ($shippingDate !== '') {
            $sDate = DateTime::createFromFormat('Y-m-d', $shippingDate);
            if (!$sDate || $sDate->format('Y-m-d') !== $shippingDate) {
                $conn->rollback();
                flash_set('error', 'Invalid shipping date format. Must be YYYY-MM-DD.');
                redirect('modules/packing_shipment/view.php?id=' . $packingId);
            }
            if ($shippingDate < $packingDate) {
                $conn->rollback();
                flash_set('error', 'Shipping date cannot be earlier than packing date.');
                redirect('modules/packing_shipment/view.php?id=' . $packingId);
            }
            $resolvedShippingDate = $shippingDate;
        } elseif (empty($resolvedShippingDate)) {
            // Default to current date if not set previously
            $resolvedShippingDate = date('Y-m-d');
            if ($resolvedShippingDate < $packingDate) {
                $resolvedShippingDate = $packingDate;
            }
        }
    }

    // 5. Update only after validation
    $updSql = "UPDATE packing_shipments SET 
        status = ?, 
        shipping_date = ?, 
        updated_by = ?, 
        updated_at = CURRENT_TIMESTAMP 
    WHERE packing_id = ?";

    $updStmt = $conn->prepare($updSql);
    if (!$updStmt) {
        throw new Exception("Failed to prepare status UPDATE statement: " . $conn->error);
    }

    $updStmt->bind_param("ssii", $requestedNext, $resolvedShippingDate, $updatedBy, $packingId);
    $updStmt->execute();
    $updStmt->close();

    // Record audit event inside the active transaction
    audit_log([
        'action'            => 'STATUS_CHANGE',
        'module'            => 'packing_shipment',
        'table_name'        => 'packing_shipments',
        'record_id'         => $packingId,
        'record_identifier' => $packingNumber,
        'old_values'        => [
            'status'        => $currentStatus,
            'shipping_date' => $currentRecord['shipping_date'],
        ],
        'new_values'        => [
            'status'        => $requestedNext,
            'shipping_date' => $resolvedShippingDate,
        ],
    ]);

    // 6. Commit transaction
    $conn->commit();

    $extraNotice = ($requestedNext === 'Cancelled') ? ' Any reserved capacity has been returned to the available inventory.' : '';
    flash_set('success', "Shipment '{$packingNumber}' status successfully updated to {$requestedNext}.{$extraNotice}");
    redirect('modules/packing_shipment/view.php?id=' . $packingId);

} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("Database error updating status for shipment ID {$packingId}: " . $e->getMessage());
    flash_set('error', 'An error occurred while updating shipment status. Please try again.');
    redirect('modules/packing_shipment/view.php?id=' . $packingId);
}
