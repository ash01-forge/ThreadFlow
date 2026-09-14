<?php
/**
 * ThreadFlow - Update Buyer Handler
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 * POST-only handler for updating existing buyer records.
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

// Check if buyer exists
try {
    $checkStmt = $conn->prepare("SELECT buyer_id FROM buyers WHERE buyer_id = ? LIMIT 1");
    if (!$checkStmt) {
        error_log("Failed to prepare check statement in update: " . $conn->error);
        flash_set('error', 'A database error occurred. Please try again.');
        redirect('modules/buyers/index.php');
    }
    $checkStmt->bind_param("i", $buyerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $existing = $checkResult->fetch_assoc();
    $checkStmt->close();

    if (!$existing) {
        flash_set('error', 'Buyer record not found.');
        redirect('modules/buyers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception verifying buyer existence: " . $e->getMessage());
    flash_set('error', 'A database error occurred. Please try again.');
    redirect('modules/buyers/index.php');
}

// Extract and trim inputs
$buyerName     = trim($_POST['buyer_name'] ?? '');
$contactPerson = trim($_POST['contact_person'] ?? '');
$email         = trim($_POST['email'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$country       = trim($_POST['country'] ?? '');
$address       = trim($_POST['address'] ?? '');

$errors = [];

// 1. Buyer Name: Required, max 150 chars
if ($buyerName === '') {
    $errors['buyer_name'] = 'Buyer Name is required.';
} elseif (mb_strlen($buyerName) > 150) {
    $errors['buyer_name'] = 'Buyer Name cannot exceed 150 characters.';
}

// 2. Contact Person: Optional, max 100 chars
if ($contactPerson !== '' && mb_strlen($contactPerson) > 100) {
    $errors['contact_person'] = 'Contact Person cannot exceed 100 characters.';
}

// 3. Email: Optional, max 150 chars, valid email format
if ($email !== '') {
    if (mb_strlen($email) > 150) {
        $errors['email'] = 'Email cannot exceed 150 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
}

// 4. Phone: Optional, max 30 chars
if ($phone !== '' && mb_strlen($phone) > 30) {
    $errors['phone'] = 'Phone number cannot exceed 30 characters.';
}

// 5. Country: Optional, max 100 chars
if ($country !== '' && mb_strlen($country) > 100) {
    $errors['country'] = 'Country cannot exceed 100 characters.';
}

// If validation fails, preserve inputs and return with errors
if (!empty($errors)) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = [
        'buyer_name'     => $buyerName,
        'contact_person' => $contactPerson,
        'email'          => $email,
        'phone'          => $phone,
        'country'        => $country,
        'address'        => $address,
    ];
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/buyers/edit.php?id=' . $buyerId);
}

// Update database using prepared statements
try {
    $stmt = $conn->prepare("UPDATE buyers SET buyer_name = ?, contact_person = ?, email = ?, phone = ?, country = ?, address = ? WHERE buyer_id = ?");
    if (!$stmt) {
        error_log("Failed to prepare UPDATE on buyers: " . $conn->error);
        flash_set('error', 'A database error occurred while updating the buyer.');
        $_SESSION['_form_old'] = $_POST;
        redirect('modules/buyers/edit.php?id=' . $buyerId);
    }

    $contactPersonParam = $contactPerson !== '' ? $contactPerson : null;
    $emailParam         = $email !== '' ? $email : null;
    $phoneParam         = $phone !== '' ? $phone : null;
    $countryParam       = $country !== '' ? $country : null;
    $addressParam       = $address !== '' ? $address : null;

    $stmt->bind_param("ssssssi", $buyerName, $contactPersonParam, $emailParam, $phoneParam, $countryParam, $addressParam, $buyerId);
    $stmt->execute();
    $stmt->close();

    flash_set('success', "Buyer '{$buyerName}' updated successfully.");
    redirect('modules/buyers/view.php?id=' . $buyerId);
} catch (mysqli_sql_exception $e) {
    error_log("Database exception updating buyer: " . $e->getMessage());
    flash_set('error', 'A database error occurred while updating the buyer.');
    $_SESSION['_form_old'] = $_POST;
    redirect('modules/buyers/edit.php?id=' . $buyerId);
}
