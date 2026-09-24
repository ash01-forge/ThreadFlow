<?php
/**
 * ThreadFlow - Toggle Defect Type Status Handler
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * POST-only handler to deactivate or reactivate a defect type.
 * Note: ThreadFlow prohibits permanent deletion of defect type records.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'qc_officer']);

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Enforce CSRF token
require_csrf();

// Validate positive integer ID
$defectId = filter_input(INPUT_POST, 'defect_id', FILTER_VALIDATE_INT);
if (!$defectId || $defectId <= 0) {
    flash_set('error', 'Invalid defect type ID specified.');
    redirect('modules/defect_types/index.php');
}

// Check if redirected from view page
$fromView = !empty($_POST['from_view']);

try {
    // Check if defect type exists and fetch current status
    $stmt = $conn->prepare("SELECT defect_id, defect_code, defect_name, is_active FROM defect_types WHERE defect_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT in toggle_status: " . $conn->error);
        flash_set('error', 'A database error occurred while updating status.');
        redirect($fromView ? 'modules/defect_types/view.php?id=' . $defectId : 'modules/defect_types/index.php');
    }

    $stmt->bind_param("i", $defectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $defect = $result->fetch_assoc();
    $stmt->close();

    // Safe handling of missing records
    if (!$defect) {
        flash_set('error', 'Defect type record not found.');
        redirect('modules/defect_types/index.php');
    }

    // Toggle active state
    $newStatus = ((int)$defect['is_active'] === 1) ? 0 : 1;
    $statusText = ($newStatus === 1) ? 'reactivated' : 'deactivated';

    $updateStmt = $conn->prepare("UPDATE defect_types SET is_active = ? WHERE defect_id = ?");
    if (!$updateStmt) {
        error_log("Failed to prepare UPDATE in toggle_status: " . $conn->error);
        flash_set('error', 'A database error occurred while updating status.');
        redirect($fromView ? 'modules/defect_types/view.php?id=' . $defectId : 'modules/defect_types/index.php');
    }

    $updateStmt->bind_param("ii", $newStatus, $defectId);
    $updateStmt->execute();
    $updateStmt->close();

    // Record audit event
    audit_log([
        'action'            => 'STATUS_CHANGE',
        'module'            => 'defect_types',
        'table_name'        => 'defect_types',
        'record_id'         => $defectId,
        'record_identifier' => $defect['defect_code'],
        'old_values'        => [
            'is_active'   => (int)$defect['is_active'],
            'status_text' => ((int)$defect['is_active'] === 1) ? 'Active' : 'Inactive',
        ],
        'new_values'        => [
            'is_active'   => $newStatus,
            'status_text' => ($newStatus === 1) ? 'Active' : 'Inactive',
        ],
    ]);

    flash_set('success', "Defect type '{$defect['defect_code']}' has been {$statusText} successfully.");

    if ($fromView) {
        redirect('modules/defect_types/view.php?id=' . $defectId);
    } else {
        redirect('modules/defect_types/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception toggling defect type status: " . $e->getMessage());
    flash_set('error', 'A database error occurred while updating status.');
    redirect('modules/defect_types/index.php');
}
