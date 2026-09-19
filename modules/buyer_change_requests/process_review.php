<?php
/** ThreadFlow - Process Admin Buyer Change Request Review. */
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
require_csrf();
$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
$decision = trim($_POST['decision'] ?? '');
$reviewNotes = trim($_POST['review_notes'] ?? '');
if (!$requestId || $requestId <= 0 || !in_array($decision, ['Approved', 'Rejected'], true) || $reviewNotes === '' || mb_strlen($reviewNotes) > 5000) {
    flash_set('error', 'A valid decision and required review notes are required.');
    redirect('modules/buyer_change_requests/review.php?id=' . (int) $requestId);
}
$user = auth_user();
try {
    $stmt = $conn->prepare("UPDATE buyer_change_requests SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE request_id = ? AND status = 'Submitted'");
    if (!$stmt) { throw new mysqli_sql_exception('Review update could not be prepared.'); }
    $reviewerId = (int) $user['user_id'];
    $stmt->bind_param('ssii', $decision, $reviewNotes, $reviewerId, $requestId); $stmt->execute();
    $changed = $stmt->affected_rows; $stmt->close();
    if ($changed !== 1) { flash_set('error', 'This request was already reviewed or could not be found.'); redirect('modules/buyer_change_requests/view.php?id=' . (int) $requestId); }
    flash_set('success', 'Buyer change request ' . strtolower($decision) . ' successfully. The original order was not changed.');
    redirect('modules/buyer_change_requests/view.php?id=' . (int) $requestId);
} catch (mysqli_sql_exception $e) { error_log('Database exception processing buyer change review: ' . $e->getMessage()); flash_set('error', 'A database error occurred while saving the review.'); redirect('modules/buyer_change_requests/review.php?id=' . (int) $requestId); }
