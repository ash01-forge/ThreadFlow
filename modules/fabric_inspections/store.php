<?php
/** Member 2, Feature 3 - Save a fabric inspection and synchronize lot status. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'production_manager', 'qc_officer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/fabric_inspections/index.php');
}
require_csrf();

$errors = [];
$lotId = filter_input(INPUT_POST, 'fabric_lot_id', FILTER_VALIDATE_INT);
$inspectionDate = trim($_POST['inspection_date'] ?? '');
$inspectedQuantity = filter_var($_POST['inspected_quantity'] ?? null, FILTER_VALIDATE_FLOAT);
$penaltyPoints = filter_var($_POST['penalty_points'] ?? null, FILTER_VALIDATE_INT);
$result = trim($_POST['result'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
$allowedResults = ['Passed', 'Failed', 'Conditionally Approved'];

if (!$lotId || $lotId < 1) $errors['fabric_lot_id'] = 'Please select a valid fabric lot.';
$date = DateTime::createFromFormat('Y-m-d', $inspectionDate);
if (!$date || $date->format('Y-m-d') !== $inspectionDate) $errors['inspection_date'] = 'Please enter a valid inspection date.';
if ($inspectedQuantity === false || $inspectedQuantity <= 0) $errors['inspected_quantity'] = 'Inspected quantity must be greater than zero.';
if ($penaltyPoints === false || $penaltyPoints < 0) $errors['penalty_points'] = 'Penalty points cannot be negative.';
if (!in_array($result, $allowedResults, true)) $errors['result'] = 'Please select a valid result.';
if (mb_strlen($remarks) > 2000) $errors['remarks'] = 'Remarks cannot exceed 2000 characters.';

$lot = null;
if (empty($errors)) {
    $stmt = $conn->prepare("SELECT fabric_lot_id, lot_number, received_quantity FROM fabric_lots WHERE fabric_lot_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $lotId);
        $stmt->execute();
        $lot = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$lot) $errors['fabric_lot_id'] = 'The selected fabric lot was not found.';
    elseif ($inspectedQuantity > (float)$lot['received_quantity']) $errors['inspected_quantity'] = 'Inspected quantity cannot exceed the received quantity.';
}

if ($errors) {
    $_SESSION['_form_errors'] = $errors;
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/fabric_inspections/create.php' . ($lotId ? '?lot_id=' . (int)$lotId : ''));
}

$user = auth_user();
$inspectorId = (int)$user['user_id'];
$remarksParam = $remarks !== '' ? $remarks : null;
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO fabric_inspections (fabric_lot_id, inspector_id, inspection_date, inspected_quantity, penalty_points, result, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param('iisdiss', $lotId, $inspectorId, $inspectionDate, $inspectedQuantity, $penaltyPoints, $result, $remarksParam);
    $stmt->execute();
    $inspectionId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("UPDATE fabric_lots SET inspection_status = ? WHERE fabric_lot_id = ?");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param('si', $result, $lotId);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    flash_set('success', "Fabric lot '{$lot['lot_number']}' inspected successfully. Lot status is now {$result}.");
    redirect('modules/fabric_inspections/view.php?id=' . $inspectionId);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Fabric inspection save failed: ' . $e->getMessage());
    $_SESSION['_form_old'] = $_POST;
    flash_set('error', 'The inspection could not be saved. Please try again.');
    redirect('modules/fabric_inspections/create.php?lot_id=' . (int)$lotId);
}
