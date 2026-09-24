<?php
/**
 * ThreadFlow - Store New Defect Type Handler
 * 
 * Module 4: Assigned to Member 4.
 * Access restricted to Admin and QC Officer roles.
 * POST-only handler for creating new defect type records.
 */
require_once __DIR__ . '/../../includes/auth.php';

// Enforce role authorization
require_role(['admin', 'qc_officer']);

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Enforce CSRF token
require_csrf();

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

// 1. Defect Code: Required, max 50 chars
if ($defectCode === '') {
    $errors['defect_code'] = 'Defect Code is required.';
} elseif (mb_strlen($defectCode) > 50) {
    $errors['defect_code'] = 'Defect Code cannot exceed 50 characters.';
} else {
    // Check uniqueness in database
    try {
        $checkStmt = $conn->prepare("SELECT defect_id FROM defect_types WHERE defect_code = ? LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param("s", $defectCode);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkResult->fetch_assoc()) {
                $errors['defect_code'] = "Defect code '{$defectCode}' is already registered. Codes must be unique.";
            }
            $checkStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database exception checking defect_code uniqueness: " . $e->getMessage());
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
    redirect('modules/defect_types/create.php');
}

// Prepare database insert
try {
    $stmt = $conn->prepare("INSERT INTO defect_types (defect_code, defect_name, category, severity, description, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    if (!$stmt) {
        error_log("Failed to prepare INSERT into defect_types: " . $conn->error);
        flash_set('error', 'A database error occurred while saving the defect type. Please try again.');
        $_SESSION['_form_old'] = $_POST;
        redirect('modules/defect_types/create.php');
    }

    $descriptionParam = $description !== '' ? $description : null;

    $stmt->bind_param("sssss", $defectCode, $defectName, $category, $severity, $descriptionParam);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    // Record audit event
    audit_log([
        'action'            => 'CREATE',
        'module'            => 'defect_types',
        'table_name'        => 'defect_types',
        'record_id'         => $newId,
        'record_identifier' => $defectCode,
        'old_values'        => null,
        'new_values'        => [
            'defect_id'   => $newId,
            'defect_code' => $defectCode,
            'defect_name' => $defectName,
            'category'    => $category,
            'severity'    => $severity,
            'description' => $descriptionParam,
            'is_active'   => 1,
        ],
    ]);

    flash_set('success', "Defect type '{$defectCode}' created successfully.");
    redirect('modules/defect_types/index.php');
} catch (mysqli_sql_exception $e) {
    error_log("Database exception creating defect type: " . $e->getMessage());
    if ($e->getCode() === 1062) {
        flash_set('error', "Defect code '{$defectCode}' is already registered. Codes must be unique.");
    } else {
        flash_set('error', 'A database error occurred while saving the defect type. Please try again.');
    }
    $_SESSION['_form_old'] = $_POST;
    redirect('modules/defect_types/create.php');
}
