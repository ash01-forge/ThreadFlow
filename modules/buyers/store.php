<?php
/**
 * ThreadFlow - Store New Buyer Handler
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 * POST-only handler for creating new buyer records.
 */
require_once __DIR__ . '/../../includes/auth.php';

// Enforce role authorization
require_role(['admin', 'merchandiser']);

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Enforce CSRF token
require_csrf();

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
    redirect('modules/buyers/create.php');
}

// Prepare database insert
try {
    $stmt = $conn->prepare("INSERT INTO buyers (buyer_name, contact_person, email, phone, country, address, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    if (!$stmt) {
        error_log("Failed to prepare INSERT into buyers: " . $conn->error);
        flash_set('error', 'A database error occurred while saving the buyer. Please try again.');
        $_SESSION['_form_old'] = $_POST;
        redirect('modules/buyers/create.php');
    }

    $contactPersonParam = $contactPerson !== '' ? $contactPerson : null;
    $emailParam         = $email !== '' ? $email : null;
    $phoneParam         = $phone !== '' ? $phone : null;
    $countryParam       = $country !== '' ? $country : null;
    $addressParam       = $address !== '' ? $address : null;

    $stmt->bind_param("ssssss", $buyerName, $contactPersonParam, $emailParam, $phoneParam, $countryParam, $addressParam);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    flash_set('success', "Buyer '{$buyerName}' created successfully.");
    redirect('modules/buyers/index.php');
} catch (mysqli_sql_exception $e) {
    error_log("Database exception creating buyer: " . $e->getMessage());
    flash_set('error', 'A database error occurred while saving the buyer. Please try again.');
    $_SESSION['_form_old'] = $_POST;
    redirect('modules/buyers/create.php');
}
