<?php
require_once __DIR__ . '/../../includes/auth.php'; require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); } require_csrf();
$fullName = trim($_POST['full_name'] ?? ''); $username = trim($_POST['username'] ?? ''); $email = trim($_POST['email'] ?? ''); $password = (string) ($_POST['password'] ?? ''); $confirmation = (string) ($_POST['password_confirmation'] ?? ''); $role = trim($_POST['role'] ?? ''); $activeInput = $_POST['is_active'] ?? '1'; $errors = [];
if ($fullName === '' || mb_strlen($fullName) > 100) $errors['full_name'] = $fullName === '' ? 'Full name is required.' : 'Full name cannot exceed 100 characters.';
if ($username === '' || mb_strlen($username) > 50) $errors['username'] = $username === '' ? 'Username is required.' : 'Username cannot exceed 50 characters.';
if ($email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'A valid email address is required.';
if (strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters.';
if ($password !== $confirmation) $errors['password_confirmation'] = 'Password confirmation does not match.';
if (!in_array($role, ROLES_LIST, true)) $errors['role'] = 'Please select a recognized role.';
if (!in_array((string) $activeInput, ['0', '1'], true)) $errors['is_active'] = 'Invalid account status.';

try {
    if (!$errors) { $stmt = $conn->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1'); if (!$stmt) throw new RuntimeException($conn->error); $stmt->bind_param('ss', $username, $email); $stmt->execute(); $existing = $stmt->get_result()->fetch_assoc(); $stmt->close(); if ($existing) { if (strcasecmp($existing['username'], $username) === 0) $errors['username'] = 'Username is already in use.'; if (strcasecmp($existing['email'], $email) === 0) $errors['email'] = 'Email is already in use.'; } }
    if ($errors) { $_SESSION['_form_errors'] = $errors; $_SESSION['_form_old'] = ['full_name'=>$fullName,'username'=>$username,'email'=>$email,'role'=>$role,'is_active'=>(string)$activeInput]; flash_set('error', 'Please resolve the validation errors below.'); redirect('modules/users/create.php'); }
    $hash = password_hash($password, PASSWORD_DEFAULT); $isActive = (int) $activeInput; $stmt = $conn->prepare('INSERT INTO users (full_name, username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?)'); if (!$stmt) throw new RuntimeException($conn->error); $stmt->bind_param('sssssi', $fullName, $username, $email, $hash, $role, $isActive); $stmt->execute(); $stmt->close(); flash_set('success', "User '{$fullName}' created successfully."); redirect('modules/users/index.php');
} catch (Throwable $e) { error_log('Database exception creating user: ' . $e->getMessage()); flash_set('error', 'A database error occurred while saving the user.'); $_SESSION['_form_old'] = ['full_name'=>$fullName,'username'=>$username,'email'=>$email,'role'=>$role,'is_active'=>(string)$activeInput]; redirect('modules/users/create.php'); }