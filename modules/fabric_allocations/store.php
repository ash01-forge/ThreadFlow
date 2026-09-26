<?php
/** Member 2, Feature 4 - Allocate approved fabric with transaction-safe balance validation. */
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'production_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('modules/fabric_allocations/index.php');
require_csrf();

$errors = [];
$lotId = filter_input(INPUT_POST, 'fabric_lot_id', FILTER_VALIDATE_INT);
$batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
$quantity = filter_var($_POST['allocated_quantity'] ?? null, FILTER_VALIDATE_FLOAT);
$allocatedDate = trim($_POST['allocated_date'] ?? '');
$notes = trim($_POST['notes'] ?? '');
if (!$lotId || $lotId < 1) $errors['fabric_lot_id'] = 'Please select an approved fabric lot.';
if (!$batchId || $batchId < 1) $errors['batch_id'] = 'Please select a production batch.';
if ($quantity === false || $quantity <= 0) $errors['allocated_quantity'] = 'Allocated quantity must be greater than zero.';
$date = DateTime::createFromFormat('Y-m-d', $allocatedDate);
if (!$date || $date->format('Y-m-d') !== $allocatedDate) $errors['allocated_date'] = 'Please enter a valid allocation date.';
if (mb_strlen($notes) > 2000) $errors['notes'] = 'Notes cannot exceed 2000 characters.';
if ($errors) {
    $_SESSION['_form_errors'] = $errors; $_SESSION['_form_old'] = $_POST;
    flash_set('error', 'Please resolve the validation errors below.');
    redirect('modules/fabric_allocations/create.php' . ($lotId ? '?lot_id=' . (int)$lotId : ''));
}

$user = auth_user();
$allocatedBy = (int)$user['user_id'];
$notesParam = $notes !== '' ? $notes : null;
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT lot_number, received_quantity, unit, inspection_status FROM fabric_lots WHERE fabric_lot_id = ? FOR UPDATE");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param('i', $lotId); $stmt->execute(); $lot = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$lot) throw new DomainException('The selected fabric lot was not found.');
    if (!in_array($lot['inspection_status'], ['Passed', 'Conditionally Approved'], true)) throw new DomainException('Only Passed or Conditionally Approved fabric can be allocated.');

    $stmt = $conn->prepare("SELECT batch_number FROM production_batches WHERE batch_id = ? LIMIT 1");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param('i', $batchId); $stmt->execute(); $batch = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$batch) throw new DomainException('The selected production batch was not found.');

    $stmt = $conn->prepare("SELECT COALESCE(SUM(allocated_quantity), 0) AS allocated FROM fabric_allocations WHERE fabric_lot_id = ?");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param('i', $lotId); $stmt->execute(); $used = (float)$stmt->get_result()->fetch_assoc()['allocated']; $stmt->close();
    $available = (float)$lot['received_quantity'] - $used;
    if ($quantity > $available + 0.00001) throw new DomainException('Allocation exceeds the available lot balance of ' . number_format($available, 2) . ' ' . $lot['unit'] . '.');

    $stmt = $conn->prepare("INSERT INTO fabric_allocations (fabric_lot_id, batch_id, allocated_by, allocated_quantity, allocated_date, notes) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param('iiidss', $lotId, $batchId, $allocatedBy, $quantity, $allocatedDate, $notesParam);
    $stmt->execute(); $allocationId = $stmt->insert_id; $stmt->close();
    $conn->commit();
    flash_set('success', number_format($quantity, 2) . " {$lot['unit']} from '{$lot['lot_number']}' allocated to '{$batch['batch_number']}'.");
    redirect('modules/fabric_allocations/view.php?id=' . $allocationId);
} catch (DomainException $e) {
    $conn->rollback(); $_SESSION['_form_old'] = $_POST; flash_set('error', $e->getMessage());
    redirect('modules/fabric_allocations/create.php?lot_id=' . (int)$lotId);
} catch (Throwable $e) {
    $conn->rollback(); error_log('Fabric allocation save failed: ' . $e->getMessage());
    $_SESSION['_form_old'] = $_POST; flash_set('error', 'The allocation could not be saved. Please try again.');
    redirect('modules/fabric_allocations/create.php?lot_id=' . (int)$lotId);
}
