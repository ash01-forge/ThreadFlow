<?php
/**
 * ThreadFlow - Toggle Buyer Status Handler
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 * POST-only handler to deactivate or reactivate a buyer.
 * Note: ThreadFlow prohibits permanent deletion of buyer records.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Enforce CSRF token
require_csrf();

// Validate positive integer ID
$buyerId = filter_input(INPUT_POST, 'buyer_id', FILTER_VALIDATE_INT);
if (!$buyerId || $buyerId <= 0) {
    flash_set('error', 'Invalid buyer ID specified.');
    redirect('modules/buyers/index.php');
}

// Check if redirected from view page
$fromView = !empty($_POST['from_view']);

try {
    // Check if buyer exists and fetch current status
    $stmt = $conn->prepare("SELECT buyer_id, buyer_name, is_active FROM buyers WHERE buyer_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT in toggle_status: " . $conn->error);
        flash_set('error', 'A database error occurred while updating status.');
        redirect($fromView ? 'modules/buyers/view.php?id=' . $buyerId : 'modules/buyers/index.php');
    }

    $stmt->bind_param("i", $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $buyer = $result->fetch_assoc();
    $stmt->close();

    // Safe handling of missing records
    if (!$buyer) {
        flash_set('error', 'Buyer record not found.');
        redirect('modules/buyers/index.php');
    }

    // Toggle active state
    $newStatus = ((int)$buyer['is_active'] === 1) ? 0 : 1;
    $statusText = ($newStatus === 1) ? 'reactivated' : 'deactivated';

    $updateStmt = $conn->prepare("UPDATE buyers SET is_active = ? WHERE buyer_id = ?");
    if (!$updateStmt) {
        error_log("Failed to prepare UPDATE in toggle_status: " . $conn->error);
        flash_set('error', 'A database error occurred while updating status.');
        redirect($fromView ? 'modules/buyers/view.php?id=' . $buyerId : 'modules/buyers/index.php');
    }

    $updateStmt->bind_param("ii", $newStatus, $buyerId);
    $updateStmt->execute();
    $updateStmt->close();

    flash_set('success', "Buyer '{$buyer['buyer_name']}' has been {$statusText} successfully.");

    if ($fromView) {
        redirect('modules/buyers/view.php?id=' . $buyerId);
    } else {
        redirect('modules/buyers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception toggling buyer status: " . $e->getMessage());
    flash_set('error', 'A database error occurred while updating status.');
    redirect('modules/buyers/index.php');
}
