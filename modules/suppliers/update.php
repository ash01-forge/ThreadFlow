<?php
/**
 * ThreadFlow - Update Supplier Handler
 * 
 * Module 2: Assigned to Member 2.
 * Access restricted to Admin and Production Manager roles.
 * POST-only handler for updating existing supplier records.
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

// Check if supplier exists
try {
    $checkStmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ? LIMIT 1");
    if (!$checkStmt) {
        error_log("Failed to prepare check statement in update: " . $conn->error);
        flash_set('error', 'A database error occurred. Please try again.');
        redirect('modules/suppliers/index.php');
    }
    $checkStmt->bind_param("i", $supplierId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $existing = $checkResult->fetch_assoc();
    $checkStmt->close();

    if (!$existing) {
        flash_set('error', 'Supplier record not found.');
        redirect('modules/suppliers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception verifying supplier existence: " . $e->getMessage());
    flash_set('error', 'A database error occurred. Please try again.');
    redirect('modules/suppliers/index.php');
}

// Documented allowed supplier types
$allowedSupplierTypes = ['Fabric', 'Yarn', 'Trims & Accessories', 'Dyeing & Chemicals', 'Packaging', 'Other'];

// Extract and trim inputs
$supplierName   = trim($_POST['supplier_name'] ?? '');
$supplierType   = trim($_POST['supplier_type'] ?? '');
$contactPerson  = trim($_POST['contact_person'] ?? '');
$email          = trim($_POST['email'] ?? '');
$phone          = trim($_POST['phone'] ?? '');
$country        = trim($_POST['country'] ?? '');
$leadTimeString = trim($_POST['lead_time_days'] ?? '');
$address        = trim($_POST['address'] ?? '');
$notes          = trim($_POST['notes'] ?? '');

$errors = [];

// 1. Supplier Name: Required, max 150 chars
if ($supplierName === '') {
    $errors['supplier_name'] = 'Supplier Name is required.';
} elseif (mb_strlen($supplierName) > 150) {
    $errors['supplier_name'] = 'Supplier Name cannot exceed 150 characters.';
}

// 2. Supplier Type: Required, must be in allowed options
if ($supplierType === '') {
    $errors['supplier_type'] = 'Please select a valid supplier type.';
} elseif (!in_array($supplierType, $allowedSupplierTypes, true)) {
    $errors['supplier_type'] = 'Invalid supplier type selected. Allowed options: ' . implode(', ', $allowedSupplierTypes) . '.';
}

// 3. Contact Person: Optional, max 100 chars
if ($contactPerson !== '' && mb_strlen($contactPerson) > 100) {
    $errors['contact_person'] = 'Contact Person cannot exceed 100 characters.';
}

// 4. Email: Optional, max 150 chars, valid email format
if ($email !== '') {
    if (mb_strlen($email) > 150) {
        $errors['email'] = 'Email cannot exceed 150 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
}

// 5. Phone: Optional, max 30 chars
if ($phone !== '' && mb_strlen($phone) > 30) {
    $errors['phone'] = 'Phone number cannot exceed 30 characters.';
}

// 6. Country: Optional, max 100 chars
if ($country !== '' && mb_strlen($country) > 100) {
    $errors['country'] = 'Country cannot exceed 100 characters.';
}

// 7. Lead Time Days: Optional, reasonable non-negative integer (0 to 365)
$leadTimeDays = null;
if ($leadTimeString !== '') {
    if (filter_var($leadTimeString, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 365]]) === false) {
        $errors['lead_time_days'] = 'Lead time must be a non-negative whole number (0 to 365 days).';
    } else {
        $leadTimeDays = (int) $leadTimeString;
    }
}

// If validation fails, preserve inputs and return with errors
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = [
        'supplier_name'  => $supplierName,
        'supplier_type'  => $supplierType,
        'contact_person' => $contactPerson,
        'email'          => $email,
        'phone'          => $phone,
        'country'        => $country,
        'lead_time_days' => $leadTimeString,
        'address'        => $address,
        'notes'          => $notes,
    ];
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/suppliers/edit.php?id=' . $supplierId);
}

// Update database using prepared statements
try {
    $stmt = $conn->prepare("UPDATE suppliers SET supplier_name = ?, supplier_type = ?, contact_person = ?, email = ?, phone = ?, country = ?, address = ?, lead_time_days = ?, notes = ? WHERE supplier_id = ?");
    if (!$stmt) {
        error_log("Failed to prepare UPDATE on suppliers: " . $conn->error);
        flash_set('error', 'A database error occurred while updating the supplier.');
        $_SESSION['_form_old'] = $_POST;
        redirect('modules/suppliers/edit.php?id=' . $supplierId);
    }

    $contactPersonParam = $contactPerson !== '' ? $contactPerson : null;
    $emailParam         = $email !== '' ? $email : null;
    $phoneParam         = $phone !== '' ? $phone : null;
    $countryParam       = $country !== '' ? $country : null;
    $addressParam       = $address !== '' ? $address : null;
    $notesParam         = $notes !== '' ? $notes : null;

    $stmt->bind_param(
        "sssssssisi",
        $supplierName,
        $supplierType,
        $contactPersonParam,
        $emailParam,
        $phoneParam,
        $countryParam,
        $addressParam,
        $leadTimeDays,
        $notesParam,
        $supplierId
    );

    $stmt->execute();
    $stmt->close();

    flash_set('success', "Supplier '{$supplierName}' updated successfully.");
    redirect('modules/suppliers/view.php?id=' . $supplierId);
} catch (mysqli_sql_exception $e) {
    error_log("Database exception updating supplier: " . $e->getMessage());
    flash_set('error', 'A database error occurred while updating the supplier.');
    $_SESSION['_form_old'] = $_POST;
    redirect('modules/suppliers/edit.php?id=' . $supplierId);
}
