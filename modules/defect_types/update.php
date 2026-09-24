<?php
/**
 * ThreadFlow - Update Defect Type Handler
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * POST-only handler for updating existing defect type records.
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

// Check if defect type exists
try {
    $checkStmt = $conn->prepare("SELECT defect_id, defect_code, defect_name, category, severity, description, is_active FROM defect_types WHERE defect_id = ? LIMIT 1");
    if (!$checkStmt) {
        error_log("Failed to prepare check statement in update: " . $conn->error);
        flash_set('error', 'A database error occurred. Please try again.');
        redirect('modules/defect_types/index.php');
    }
    $checkStmt->bind_param("i", $defectId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $existing = $checkResult->fetch_assoc();
    $checkStmt->close();

    if (!$existing) {
        flash_set('error', 'Defect type record not found.');
        redirect('modules/defect_types/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception verifying defect type existence: " . $e->getMessage());
    flash_set('error', 'A database error occurred. Please try again.');
    redirect('modules/defect_types/index.php');
}

// Allowed domain lists
$allowedCategories = ['Sewing', 'Fabric', 'Measurement', 'Finishing', 'Packaging', 'Other'];
$allowedSeverities = ['minor', 'major', 'critical'];

// Extract and trim inputs
$defectCode  = trim($_POST['defect_code'] ?? '');
$defectName  = trim($_POST['defect_name'] ?? '');
$category    = trim($_POST['category'] ?? '');
$severity    = trim($_POST['severity'] ?? '');
$description = trim($_POST['description'] ?? '');

$errors = [];

// 1. Defect Code: Required, max 50 chars, unique excluding this defect_id
if ($defectCode === '') {
    $errors['defect_code'] = 'Defect Code is required.';
} elseif (mb_strlen($defectCode) > 50) {
    $errors['defect_code'] = 'Defect Code cannot exceed 50 characters.';
} else {
    try {
        $uniqueStmt = $conn->prepare("SELECT defect_id FROM defect_types WHERE defect_code = ? AND defect_id != ? LIMIT 1");
        if ($uniqueStmt) {
            $uniqueStmt->bind_param("si", $defectCode, $defectId);
            $uniqueStmt->execute();
            $uniqueResult = $uniqueStmt->get_result();
            if ($uniqueResult->fetch_assoc()) {
                $errors['defect_code'] = "Defect code '{$defectCode}' is already registered to another record.";
            }
            $uniqueStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception checking defect_code uniqueness on update: " . $e->getMessage());
    }
}

// 2. Defect Name: Required, max 150 chars
if ($defectName === '') {
    $errors['defect_name'] = 'Defect Name is required.';
} elseif (mb_strlen($defectName) > 150) {
    $errors['defect_name'] = 'Defect Name cannot exceed 150 characters.';
}

// 3. Category: Required, restricted to allowed options
if ($category === '') {
    $errors['category'] = 'Category is required.';
} elseif (!in_array($category, $allowedCategories, true)) {
    $errors['category'] = 'Please select a valid defect category.';
}

// 4. Severity: Required, restricted to minor/major/critical
if ($severity === '') {
    $errors['severity'] = 'Severity Tier is required.';
} elseif (!in_array($severity, $allowedSeverities, true)) {
    $errors['severity'] = 'Please select a valid severity tier (Minor, Major, or Critical).';
}

// If validation fails, preserve inputs and return with errors
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = [
        'defect_code' => $defectCode,
        'defect_name' => $defectName,
        'category'    => $category,
        'severity'    => $severity,
        'description' => $description,
    ];
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/defect_types/edit.php?id=' . $defectId);
}

// Update database using prepared statements
try {
    $stmt = $conn->prepare("UPDATE defect_types SET defect_code = ?, defect_name = ?, category = ?, severity = ?, description = ? WHERE defect_id = ?");
    if (!$stmt) {
        error_log("Failed to prepare UPDATE on defect_types: " . $conn->error);
        flash_set('error', 'A database error occurred while updating the defect type.');
        $_SESSION['_form_old'] = $_POST;
        redirect('modules/defect_types/edit.php?id=' . $defectId);
    }

    $descriptionParam = $description !== '' ? $description : null;

    $stmt->bind_param("sssssi", $defectCode, $defectName, $category, $severity, $descriptionParam, $defectId);
    $stmt->execute();
    $stmt->close();

    // Record audit event
    audit_log([
        'action'            => 'UPDATE',
        'module'            => 'defect_types',
        'table_name'        => 'defect_types',
        'record_id'         => $defectId,
        'record_identifier' => $defectCode,
        'old_values'        => [
            'defect_code' => $existing['defect_code'],
            'defect_name' => $existing['defect_name'],
            'category'    => $existing['category'],
            'severity'    => $existing['severity'],
            'description' => $existing['description'],
        ],
        'new_values'        => [
            'defect_code' => $defectCode,
            'defect_name' => $defectName,
            'category'    => $category,
            'severity'    => $severity,
            'description' => $descriptionParam,
        ],
    ]);

    flash_set('success', "Defect type '{$defectCode}' updated successfully.");
    redirect('modules/defect_types/view.php?id=' . $defectId);
} catch (mysqli_sql_exception $e) {
    error_log("Database exception updating defect type: " . $e->getMessage());
    if ($e->getCode() === 1062) {
        flash_set('error', "Defect code '{$defectCode}' is already registered to another record.");
    } else {
        flash_set('error', 'A database error occurred while updating the defect type.');
    }
    $_SESSION['_form_old'] = $_POST;
    redirect('modules/defect_types/edit.php?id=' . $defectId);
}
