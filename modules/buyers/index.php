<?php
/**
 * ThreadFlow - Buyer Management Directory
 * 
 * Module 1: Assigned to Member 1.
 * Access restricted to Admin and Merchandiser roles.
 */
require_once __DIR__ . '/../../includes/auth.php';

require_role(['admin', 'merchandiser']);

$pageTitle = 'Buyer Management';
$currentNav = 'buyers';

// Extract and sanitize search and filter query parameters
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

// Build query with prepared statements
$whereClauses = [];
$wildcard = '%' . $search . '%';
$statusVal = ($statusFilter === 'active') ? 1 : (($statusFilter === 'inactive') ? 0 : null);

// Search across buyer_name, contact_person, and email
if ($search !== '') {
    $whereClauses[] = "(buyer_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
}

// Filter by active status
if ($statusVal !== null) {
    $whereClauses[] = "is_active = ?";
}

$sql = "SELECT buyer_id, buyer_name, contact_person, email, phone, country, is_active, created_at, updated_at FROM buyers";
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY buyer_id DESC";

$buyers = [];
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare buyers index query: " . $conn->error);
        flash_set('error', 'A database error occurred while fetching the buyer list.');
    } else {
        if ($search !== '' && $statusVal !== null) {
            $stmt->bind_param("sssi", $wildcard, $wildcard, $wildcard, $statusVal);
        } elseif ($search !== '') {
            $stmt->bind_param("sss", $wildcard, $wildcard, $wildcard);
        } elseif ($statusVal !== null) {
            $stmt->bind_param("i", $statusVal);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $buyers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database exception fetching buyers: " . $e->getMessage());
    flash_set('error', 'A database error occurred while loading buyers.');
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<?php
$totalBuyers = count($buyers);
$activeBuyers = count(array_filter($buyers, static fn ($buyer) => (int) $buyer['is_active'] === 1));
$inactiveBuyers = $totalBuyers - $activeBuyers;
?>
<style>
.buyers-index { --buyers-ink: #3c1b25; --buyers-muted: #806b72; --buyers-line: #efd9df; --buyers-soft: #fff7f8; }
.buyers-index .buyers-hero { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; margin-bottom: 22px; }
.buyers-index .buyers-kicker { color: #9b5363; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.buyers-index .buyers-hero h1 { margin-bottom: 6px; }
.buyers-index .buyers-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 20px; }
.buyers-index .buyers-stat { padding: 15px 16px; border: 1px solid var(--buyers-line); border-radius: 11px; background: #fff; }
.buyers-index .buyers-stat strong { display: block; color: var(--buyers-ink); font-size: 1.45rem; }
.buyers-index .buyers-stat span { color: var(--buyers-muted); font-size: .78rem; }
.buyers-index .buyers-toolbar { display: flex; align-items: flex-end; gap: 14px; padding: 16px; margin-bottom: 20px; border: 1px solid var(--buyers-line); border-radius: 12px; background: var(--buyers-soft); }
.buyers-index .buyers-toolbar .filter-field-grow, .buyers-index .buyers-toolbar .filter-field { flex: 1; margin: 0; }
.buyers-index .buyers-toolbar .filter-actions { display: flex; gap: 8px; }
.buyers-index .buyers-directory { overflow: hidden; border: 1px solid var(--buyers-line); border-radius: 14px; background: #fff; }
.buyers-index .buyers-directory-head { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--buyers-line); }
.buyers-index .buyers-directory-head h2 { margin: 0; color: var(--buyers-ink); font-size: 1.1rem; }
.buyers-index .buyers-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(315px, 1fr)); gap: 12px; padding: 16px; }
.buyers-index .buyer-card { padding: 18px; border: 1px solid var(--buyers-line); border-left: 4px solid #25805c; border-radius: 10px; background: #fff; transition: box-shadow .18s ease, transform .18s ease; }
.buyers-index .buyer-card[data-active="0"] { border-left-color: #b43b4c; }
.buyers-index .buyer-card:hover { box-shadow: 0 8px 22px rgba(120, 0, 15, .1); transform: translateY(-2px); }
.buyers-index .buyer-card-top, .buyers-index .buyer-card-actions { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.buyers-index .buyer-card-id { color: var(--buyers-muted); font-size: .75rem; font-weight: 700; }
.buyers-index .buyer-name { display: block; margin-top: 12px; color: #78000f; font-size: 1.1rem; font-weight: 700; }
.buyers-index .buyer-contact { margin: 3px 0 17px; color: var(--buyers-muted); font-size: .86rem; }
.buyers-index .buyer-meta { display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; padding: 14px 0; border-top: 1px solid #f4e7e9; border-bottom: 1px solid #f4e7e9; }
.buyers-index .buyer-meta small { display: block; color: var(--buyers-muted); font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; }
.buyers-index .buyer-meta strong { display: block; margin-top: 4px; color: var(--buyers-ink); font-size: .84rem; overflow-wrap: anywhere; }
.buyers-index .buyer-card-actions { justify-content: flex-start; flex-wrap: wrap; padding-top: 14px; }
.buyers-index .buyer-card-actions .btn { flex: 1; min-width: 92px; text-align: center; }
.buyers-index .buyer-card-actions form { display: contents; }
.buyers-index .buyer-card-actions .buyer-activate { color: #fff; border-color: #25805c; background: #25805c; }
.buyers-index .buyer-card-actions .buyer-activate:hover { color: #fff; border-color: #1c704e; background: #1c704e; }
.buyers-index .buyer-card-actions .buyer-deactivate { color: #a52e42; border-color: #efc6cd; background: #fff4f5; }
.buyers-index .buyer-card-actions .buyer-deactivate:hover { color: #922d3d; border-color: #e7aab4; background: #ffe9ed; }
@media (max-width: 760px) { .buyers-index .buyers-hero { display: block; } .buyers-index .buyers-hero .page-actions { margin-top: 14px; } .buyers-index .buyers-summary { grid-template-columns: 1fr; } .buyers-index .buyers-toolbar { display: block; } .buyers-index .buyers-toolbar .filter-field { margin-top: 12px; } .buyers-index .buyers-toolbar .filter-actions { margin-top: 14px; } .buyers-index .buyers-toolbar .btn { flex: 1; text-align: center; } }
</style>
<div class="buyers-index"><div class="buyers-hero"><div><div class="buyers-kicker">Buyer operations</div><div class="page-title-group"><h1>Buyer Management</h1><div class="page-subtitle">Manage buyer identities, contacts, sourcing locations, and account availability.</div></div></div><div class="page-actions"><a href="<?= e(url('modules/buyers/create.php')) ?>" class="btn btn-primary">+ Add New Buyer</a></div></div>
<div class="buyers-summary"><div class="buyers-stat"><strong><?= $totalBuyers ?></strong><span>Total Buyers</span></div><div class="buyers-stat"><strong><?= $activeBuyers ?></strong><span>Active Buyers</span></div><div class="buyers-stat"><strong><?= $inactiveBuyers ?></strong><span>Inactive Buyers</span></div></div>
<div class="buyers-toolbar"><form method="get" action="<?= e(url('modules/buyers/index.php')) ?>" class="filter-bar" style="display: contents;"><div class="filter-field-grow"><label for="q" class="form-label">Search Buyers</label><input type="text" id="q" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search by name, contact person, or email..."></div><div class="filter-field"><label for="status" class="form-label">Status Filter</label><select id="status" name="status" class="form-control"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option></select></div><div class="filter-actions"><button type="submit" class="btn btn-primary">Apply Filters</button><?php if ($search !== '' || $statusFilter !== 'all'): ?><a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary">Clear</a><?php endif; ?></div></form></div>
<div class="buyers-directory"><div class="buyers-directory-head"><h2>Buyer Directory</h2><span class="badge badge-muted"><?= count($buyers) ?> Total Result(s)</span></div><?php if (empty($buyers)): ?><div class="empty-state"><p>No buyers found matching your criteria.</p><?php if ($search !== '' || $statusFilter !== 'all'): ?><a href="<?= e(url('modules/buyers/index.php')) ?>" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a><?php else: ?><a href="<?= e(url('modules/buyers/create.php')) ?>" class="btn btn-primary btn-sm">Add Your First Buyer</a><?php endif; ?></div><?php else: ?><div class="buyers-grid"><?php foreach ($buyers as $b): ?><article class="buyer-card" data-active="<?= (int) $b['is_active'] ?>"><div class="buyer-card-top"><span class="buyer-card-id">Buyer ID #<?= (int) $b['buyer_id'] ?></span><?php if ((int)$b['is_active'] === 1): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></div><a class="buyer-name" href="<?= e(url('modules/buyers/view.php?id=' . (int)$b['buyer_id'])) ?>"><?= e($b['buyer_name']) ?></a><div class="buyer-contact"><?= e($b['contact_person'] ?: 'No contact person listed') ?></div><div class="buyer-meta"><div><small>Email</small><strong><?php if (!empty($b['email'])): ?><a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a><?php else: ?>—<?php endif; ?></strong></div><div><small>Phone</small><strong><?= e($b['phone'] ?: '—') ?></strong></div><div><small>Country</small><strong><?= e($b['country'] ?: '—') ?></strong></div><div><small>Updated</small><strong><?= e($b['updated_at']) ?></strong></div></div><div class="buyer-card-actions"><a href="<?= e(url('modules/buyers/view.php?id=' . (int)$b['buyer_id'])) ?>" class="btn btn-sm btn-secondary">View</a><a href="<?= e(url('modules/buyers/edit.php?id=' . (int)$b['buyer_id'])) ?>" class="btn btn-sm btn-primary">Edit</a><form action="<?= e(url('modules/buyers/toggle_status.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="buyer_id" value="<?= (int) $b['buyer_id'] ?>"><?php if ((int)$b['is_active'] === 1): ?><button type="submit" class="btn btn-sm buyer-deactivate" data-confirm="Are you sure you want to deactivate this buyer?">Deactivate Buyer</button><?php else: ?><button type="submit" class="btn btn-sm buyer-activate" data-confirm="Are you sure you want to activate this buyer?">Activate Buyer</button><?php endif; ?></form></div></article><?php endforeach; ?></div><?php endif; ?></div></div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
