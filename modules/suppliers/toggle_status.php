<?php
/**
 * ThreadFlow - Toggle Supplier Status Handler
 * 
 * Module 2: Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 * POST-only handler to deactivate or reactivate a supplier.
 * Note: ThreadFlow prohibits permanent deletion of supplier records.
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
$supplierId = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
if (!$supplierId || $supplierId <= 0) {
    flash_set('error', 'Invalid supplier ID specified.');
    redirect('modules/suppliers/index.php');
}

// Check if redirected from view page
$fromView = !empty($_POST['from_view']);

try {
    // Check if supplier exists and fetch current status
    $stmt = $conn->prepare("SELECT supplier_id, supplier_name, is_active FROM suppliers WHERE supplier_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT in toggle_status: " . $conn->error);
        flash_set('error', 'A database error occurred while updating status.');
        redirect($fromView ? 'modules/suppliers/view.php?id=' . $supplierId : 'modules/suppliers/index.php');
    }

    $stmt->bind_param("i", $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    $stmt->close();

    // Safe handling of missing records
    if (!$supplier) {
        flash_set('error', 'Supplier record not found.');
        redirect('modules/suppliers/index.php');
    }

    // Toggle active state
    $newStatus = ((int)$supplier['is_active'] === 1) ? 0 : 1;
    $statusText = ($newStatus === 1) ? 'reactivated' : 'deactivated';

    $updateStmt = $conn->prepare("UPDATE suppliers SET is_active = ? WHERE supplier_id = ?");
    if (!$updateStmt) {
        error_log("Failed to prepare UPDATE in toggle_status: " . $conn->error);
        flash_set('error', 'A database error occurred while updating status.');
        redirect($fromView ? 'modules/suppliers/view.php?id=' . $supplierId : 'modules/suppliers/index.php');
    }

    $updateStmt->bind_param("ii", $newStatus, $supplierId);
    $updateStmt->execute();
    $updateStmt->close();

    flash_set('success', "Supplier '{$supplier['supplier_name']}' has been {$statusText} successfully.");

    if ($fromView) {
        redirect('modules/suppliers/view.php?id=' . $supplierId);
    } else {
        redirect('modules/suppliers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception toggling supplier status: " . $e->getMessage());
    flash_set('error', 'A database error occurred while updating status.');
    redirect('modules/suppliers/index.php');
}
