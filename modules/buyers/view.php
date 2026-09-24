<?php
/**
 * ThreadFlow - Buyer Details View
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

// Positive integer ID validation
$buyerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$buyerId || $buyerId <= 0) {
    flash_set('error', 'Invalid buyer ID specified.');
    redirect('modules/buyers/index.php');
}

// Fetch buyer details safely using prepared statements
try {
    $stmt = $conn->prepare("SELECT buyer_id, buyer_name, contact_person, email, phone, country, address, is_active, created_at, updated_at FROM buyers WHERE buyer_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare SELECT for buyer details: " . $conn->error);
        flash_set('error', 'A database error occurred while retrieving buyer details.');
        redirect('modules/buyers/index.php');
    }

    $stmt->bind_param("i", $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $buyer = $result->fetch_assoc();
    $stmt->close();

    // Safe handling of missing records
    if (!$buyer) {
        flash_set('error', 'Buyer record not found.');
        redirect('modules/buyers/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching buyer details: " . $e->getMessage());
    flash_set('error', 'A database error occurred while retrieving buyer details.');
    redirect('modules/buyers/index.php');
}

$pageTitle = 'Buyer: ' . $buyer['buyer_name'];
$currentNav = 'buyers';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.buyers-view { --buyers-ink: #3c1b25; --buyers-muted: #806b72; --buyers-line: #efd9df; }
.buyers-view .buyers-hero { padding: 24px; margin-bottom: 20px; border-radius: 14px; color: #fff; background: linear-gradient(120deg, #78000f, #a83c51); }
.buyers-view .buyers-hero .page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 22px; margin: 0; }
.buyers-view .buyers-kicker { margin-bottom: 7px; color: #f9dfe4; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.buyers-view .buyers-hero h1 { margin-bottom: 6px; color: #fff; }
.buyers-view .buyers-hero .page-subtitle { color: #f9dfe4; }
.buyers-view .buyers-hero .page-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.buyers-view .buyers-hero .btn-primary { color: #78000f; background: #fff; border-color: #fff; box-shadow: 0 3px 10px rgba(48, 0, 10, .2); }
.buyers-view .buyers-hero .btn-primary:hover { color: #5c000c; background: #fff4f6; border-color: #fff4f6; }
.buyers-view .buyers-hero .btn-secondary { color: #fff; background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .75); }
.buyers-view .buyers-hero .btn-secondary:hover { color: #fff; background: rgba(255, 255, 255, .2); border-color: #fff; }
.buyers-view .buyers-hero .btn-danger { color: #fff; background: #b43b4c; border-color: #f4b1bb; }
.buyers-view .buyers-hero .btn-danger:hover { background: #922d3d; border-color: #fff; }
.buyers-view .buyers-hero .buyer-activate { color: #fff; background: #25805c; border-color: #25805c; }
.buyers-view .buyers-hero .buyer-activate:hover { color: #fff; background: #1c704e; border-color: #1c704e; }
.buyers-view .buyers-hero .buyer-deactivate { color: #fff; background: #b43b4c; border-color: #f4b1bb; }
.buyers-view .buyers-hero .buyer-deactivate:hover { color: #fff; background: #922d3d; border-color: #fff; }
.buyers-view .buyers-hero form { display: contents; }
.buyers-view .buyers-hero-profile { display: flex; align-items: center; gap: 16px; margin-top: 22px; }
.buyers-view .buyers-avatar { display: grid; place-items: center; width: 58px; height: 58px; flex: 0 0 58px; border-radius: 50%; color: #78000f; background: #fff; font-size: 1.35rem; font-weight: 800; }
.buyers-view .buyers-hero-profile strong { display: block; color: #fff; font-size: 1.15rem; }
.buyers-view .buyers-hero-profile span { display: block; margin-top: 3px; color: #f9dfe4; }
.buyers-view .buyers-hero-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.buyers-view .buyers-hero-badges .badge-success { color: #155b3a !important; background: #d9f3e4 !important; border: 1px solid #72c493 !important; }
.buyers-view .buyers-hero-badges .badge-danger { color: #8b2435 !important; background: #fde0e5 !important; border: 1px solid #df8795 !important; }
.buyers-view .buyers-panel { overflow: hidden; margin-bottom: 18px; border: 1px solid var(--buyers-line); border-radius: 13px; background: #fff; }
.buyers-view .buyers-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 17px 20px; border-bottom: 1px solid var(--buyers-line); }
.buyers-view .buyers-panel-head h2 { margin: 0; color: var(--buyers-ink); font-size: 1.08rem; }
.buyers-view .buyers-panel-body { padding: 20px; }
.buyers-view .buyers-info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.buyers-view .buyers-info-card { padding: 15px; border: 1px solid var(--buyers-line); border-radius: 10px; background: #fffafd; }
.buyers-view .buyers-info-card small { display: block; color: var(--buyers-muted); font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.buyers-view .buyers-info-card p { margin: 7px 0 0; color: var(--buyers-ink); font-size: .9rem; overflow-wrap: anywhere; }
.buyers-view .buyers-status { display: flex; align-items: center; gap: 12px; padding: 16px; border-radius: 10px; background: #f2fbf6; border: 1px solid #c8e8d5; }
.buyers-view .buyers-status.inactive { background: #fff4f5; border-color: #f0c8ce; }
.buyers-view .buyers-status strong { color: var(--buyers-ink); }
@media (max-width: 700px) { .buyers-view .buyers-hero .page-header { display: block; } .buyers-view .buyers-hero .page-actions { margin-top: 16px; } .buyers-view .buyers-hero .page-actions .btn { width: 100%; margin-bottom: 7px; text-align: center; } .buyers-view .buyers-info-grid { grid-template-columns: 1fr; } }
</style>
<div class="buyers-view"><div class="buyers-hero"><div class="page-header"><div class="page-title-group"><div class="buyers-kicker">Buyer dossier</div><h1><?= e($buyer['buyer_name']) ?></h1><div class="page-subtitle">Buyer ID #<?= (int) $buyer['buyer_id'] ?> &bull; Company profile and account status</div></div><div class="page-actions"><a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Back to Buyer List</a><a href="<?= e(url('modules/buyers/edit.php?id=' . (int)$buyer['buyer_id'])) ?>" class="btn btn-primary">Edit Buyer</a><form action="<?= e(url('modules/buyers/toggle_status.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="buyer_id" value="<?= (int) $buyer['buyer_id'] ?>"><input type="hidden" name="from_view" value="1"><?php if ((int)$buyer['is_active'] === 1): ?><button type="submit" class="btn btn-danger buyer-deactivate" data-confirm="Are you sure you want to deactivate this buyer?">Deactivate Buyer</button><?php else: ?><button type="submit" class="btn btn-primary buyer-activate" data-confirm="Are you sure you want to activate this buyer?">Activate Buyer</button><?php endif; ?></form></div></div><div class="buyers-hero-profile"><div class="buyers-avatar" aria-hidden="true"><?= e(strtoupper(substr($buyer['buyer_name'], 0, 1))) ?></div><div><strong><?= e($buyer['buyer_name']) ?></strong><span><?= e($buyer['contact_person'] ?: 'No primary contact listed') ?></span><div class="buyers-hero-badges"><?php if ((int)$buyer['is_active'] === 1): ?><span class="badge badge-success">Active Account</span><?php else: ?><span class="badge badge-danger">Inactive Account</span><?php endif; ?></div></div></div></div>
<div class="buyers-panel"><div class="buyers-panel-head"><h2>Company Profile</h2><span class="badge badge-muted">#<?= (int) $buyer['buyer_id'] ?></span></div><div class="buyers-panel-body"><div class="buyers-info-grid"><div class="buyers-info-card"><small>Buyer Name</small><p><strong><?= e($buyer['buyer_name']) ?></strong></p></div><div class="buyers-info-card"><small>Buyer ID</small><p>#<?= (int) $buyer['buyer_id'] ?></p></div><div class="buyers-info-card"><small>Country</small><p><?= e($buyer['country'] ?: '—') ?></p></div></div></div></div>
<div class="buyers-panel"><div class="buyers-panel-head"><h2>Primary Contact</h2></div><div class="buyers-panel-body"><div class="buyers-info-grid"><div class="buyers-info-card"><small>Contact Person</small><p><?= e($buyer['contact_person'] ?: '—') ?></p></div><div class="buyers-info-card"><small>Email Address</small><p><?php if (!empty($buyer['email'])): ?><a href="mailto:<?= e($buyer['email']) ?>"><?= e($buyer['email']) ?></a><?php else: ?><span class="text-muted">—</span><?php endif; ?></p></div><div class="buyers-info-card"><small>Phone Number</small><p><?php if (!empty($buyer['phone'])): ?><a href="tel:<?= e($buyer['phone']) ?>"><?= e($buyer['phone']) ?></a><?php else: ?><span class="text-muted">—</span><?php endif; ?></p></div></div></div></div>
<div class="buyers-panel"><div class="buyers-panel-head"><h2>Address / Location</h2></div><div class="buyers-panel-body"><div class="buyers-info-grid"><div class="buyers-info-card"><small>Physical / Mailing Address</small><p><?php if (!empty($buyer['address'])): ?><?= nl2br(e($buyer['address'])) ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></p></div><div class="buyers-info-card"><small>Country</small><p><?= e($buyer['country'] ?: '—') ?></p></div></div></div></div>
<div class="buyers-panel"><div class="buyers-panel-head"><h2>Account Status and Activity</h2></div><div class="buyers-panel-body"><div class="buyers-status <?= (int)$buyer['is_active'] === 1 ? '' : 'inactive' ?>"><?php if ((int)$buyer['is_active'] === 1): ?><span class="badge badge-success">Active</span><strong>Ready for merchandising and production assignment</strong><?php else: ?><span class="badge badge-danger">Inactive</span><strong>Archived from new assignments</strong><?php endif; ?></div><div class="buyers-info-grid" style="margin-top:14px;"><div class="buyers-info-card"><small>Record Created</small><p><?= e($buyer['created_at']) ?></p></div><div class="buyers-info-card"><small>Last Updated</small><p><?= e($buyer['updated_at']) ?></p></div></div></div></div></div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
