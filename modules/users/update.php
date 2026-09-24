<?php
require_once __DIR__ . '/../../includes/auth.php'; require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); } require_csrf();
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT); $role = trim($_POST['role'] ?? ''); $activeInput = $_POST['is_active'] ?? ''; $errors = [];
if (!$userId || $userId < 1) { flash_set('error', 'Invalid user ID specified.'); redirect('modules/users/index.php'); }
if (!in_array($role, ROLES_LIST, true)) $errors['role'] = 'Please select a recognized role.';
if (!in_array((string)$activeInput, ['0','1'], true)) $errors['is_active'] = 'Invalid account status.';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($userId === $currentUserId && ($role !== 'admin' || (string)$activeInput !== '1')) { $errors['account'] = 'You cannot remove your own Admin role or deactivate your own account.'; }
if ($errors) { $_SESSION['_form_errors'] = $errors; $_SESSION['_form_old'] = ['role'=>$role,'is_active'=>(string)$activeInput]; flash_set('error', $errors['account'] ?? 'Please resolve the validation errors below.'); redirect('modules/users/edit.php?id=' . $userId); }
try { $stmt = $conn->prepare('UPDATE users SET role = ?, is_active = ? WHERE user_id = ?'); if (!$stmt) throw new RuntimeException($conn->error); $isActive = (int)$activeInput; $stmt->bind_param('sii', $role, $isActive, $userId); $stmt->execute(); $changed = $stmt->affected_rows; $stmt->close(); if ($changed < 0) throw new RuntimeException('User update failed.'); flash_set('success', 'User role and account status updated successfully.'); redirect('modules/users/view.php?id=' . $userId); } catch (Throwable $e) { error_log('Database exception updating user: ' . $e->getMessage()); flash_set('error', 'A database error occurred while updating the user.'); redirect('modules/users/edit.php?id=' . $userId); }