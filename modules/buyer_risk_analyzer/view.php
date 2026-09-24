<?php
/** ThreadFlow - Buyer Commitment Risk Analysis detail and read-only simulator. */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/helpers.php';

require_role(['admin', 'merchandiser', 'management']);

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId <= 0) {
    flash_set('error', 'Invalid order ID specified.');
    redirect('modules/buyer_risk_analyzer/index.php');
}

$order = null;
try {
    $stmt = $conn->prepare(buyer_risk_order_sql('bo.order_id = ?') . ' LIMIT 1');
    if (!$stmt) throw new mysqli_sql_exception('Risk analysis detail query could not be prepared.');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        flash_set('error', 'Buyer order was not found.');
        redirect('modules/buyer_risk_analyzer/index.php');
    }
} catch (mysqli_sql_exception $e) {
    error_log('Buyer risk analyzer detail error: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while loading the order analysis.');
    redirect('modules/buyer_risk_analyzer/index.php');
}

$maximumQuantity = max(0, (int) $order['total_quantity']);
$simulation = [
    'extra_completed_quantity' => buyer_risk_validate_nonnegative_int($_GET['extra_completed_quantity'] ?? 0, $maximumQuantity),
    'delivery_extension_days' => buyer_risk_validate_nonnegative_int($_GET['delivery_extension_days'] ?? 0, 365),
    'extra_packed_quantity' => buyer_risk_validate_nonnegative_int($_GET['extra_packed_quantity'] ?? 0, $maximumQuantity),
    'resolve_on_hold' => isset($_GET['resolve_on_hold']) && $_GET['resolve_on_hold'] === '1',
    'resolve_qc_failures' => isset($_GET['resolve_qc_failures']) && $_GET['resolve_qc_failures'] === '1',
    'resolve_priority_changes' => isset($_GET['resolve_priority_changes']) && $_GET['resolve_priority_changes'] === '1',
];
$baseRisk = buyer_calculate_risk($order);
$simulatedRisk = buyer_calculate_risk($order, $simulation);
$shippedPercent = buyer_risk_percent((float) $order['shipped_delivered_quantity'], (float) $order['total_quantity']);
$packedPercent = buyer_risk_percent((float) $order['packed_quantity'], (float) $order['total_quantity']);

$pageTitle = 'Risk Analysis: ' . $order['order_number'];
$currentNav = 'buyer_risk_analyzer';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.risk-detail { --detail-ink:#2c2022; --detail-muted:#75676a; --detail-line:#eed9dd; }
.risk-detail .detail-top { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
.detail-back { display:inline-flex; align-items:center; gap:.35rem; font-size:.82rem; font-weight:700; }
.detail-back svg { width:16px; height:16px; }
.detail-summary { display:grid; grid-template-columns:minmax(0, 1fr) 180px; gap:1.5rem; align-items:center; padding:1.35rem 1.5rem; margin-bottom:1rem; border:1px solid var(--detail-line); border-radius:var(--radius-lg); background:linear-gradient(120deg, #fff 0%, #fff5f6 100%); box-shadow:var(--shadow-sm); }
.detail-summary h1 { margin:.15rem 0 .25rem; font-size:clamp(1.45rem, 3vw, 2rem); }
.detail-eyebrow { color:var(--color-primary); font-size:.72rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
.detail-style { color:var(--detail-muted); font-size:.84rem; }
.detail-summary-meta { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.75rem; margin-top:1.15rem; }
.detail-meta-item { padding-top:.65rem; border-top:1px solid var(--detail-line); }
.detail-meta-item span, .detail-tile-label { display:block; color:var(--detail-muted); font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.detail-meta-item strong { display:block; margin-top:.2rem; color:var(--detail-ink); font-size:.88rem; }
.detail-score-wrap { display:flex; flex-direction:column; align-items:center; gap:.5rem; }
.detail-score-ring { position:relative; display:grid; place-items:center; width:132px; height:132px; border-radius:50%; background:conic-gradient(var(--color-primary) <?= (int) $baseRisk['score'] ?>%, #f0dfe2 0); }
.detail-score-ring::before { width:100px; height:100px; border-radius:50%; background:#fff; content:""; }
.detail-score-content { position:absolute; display:flex; flex-direction:column; align-items:center; color:var(--detail-ink); }
.detail-score-content strong { font-size:2rem; line-height:1; }
.detail-score-content small { color:var(--detail-muted); font-size:.7rem; }
.detail-score-wrap .badge { position:relative; }
.detail-card { margin-bottom:1rem; border:1px solid var(--detail-line); border-radius:var(--radius-md); background:#fff; box-shadow:var(--shadow-xs); }
.detail-card-heading { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.15rem; border-bottom:1px solid var(--detail-line); }
.detail-card-heading h2 { margin:0; font-size:1.05rem; }
.detail-card-body { padding:1rem 1.15rem; }
.factor-list { display:grid; gap:.55rem; }
.factor-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.7rem .8rem; border:1px solid var(--detail-line); border-radius:var(--radius-sm); background:var(--risk-soft, #fff8f9); }
.factor-row span:first-child { color:var(--detail-ink); font-size:.84rem; }
.detail-groups { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.8rem; }
.detail-group { padding:.9rem; border:1px solid var(--detail-line); border-radius:var(--radius-sm); background:#fffafb; }
.detail-group h3 { display:flex; align-items:center; gap:.45rem; margin:0 0 .75rem; font-size:.9rem; }
.detail-group h3 svg { width:17px; height:17px; color:var(--color-primary); }
.detail-tiles { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.55rem; }
.detail-tile { min-width:0; padding:.6rem .65rem; border-radius:var(--radius-xs); background:#fff; }
.detail-tile-value { display:block; margin-top:.22rem; color:var(--detail-ink); font-size:.88rem; font-weight:750; overflow-wrap:anywhere; }
.detail-progress { height:6px; margin-top:.45rem; overflow:hidden; border-radius:var(--radius-full); background:#f1e3e5; }
.detail-progress span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); }
.detail-progress--success span { background:var(--color-success); }
.action-panel { border-color:#e9c5cb; background:#fff7f8; }
.action-list { display:grid; gap:.6rem; margin:0; padding:0; list-style:none; counter-reset:actions; }
.action-list li { display:flex; gap:.7rem; align-items:flex-start; color:var(--detail-ink); font-size:.86rem; counter-increment:actions; }
.action-list li::before { display:grid; flex:0 0 24px; place-items:center; width:24px; height:24px; border-radius:50%; color:#fff; background:var(--color-primary); content:counter(actions); font-size:.72rem; font-weight:800; }
.simulator-grid { display:grid; grid-template-columns:minmax(0, 1fr) minmax(280px, .9fr); gap:1.25rem; }
.simulator-inputs { padding-right:1.25rem; border-right:1px solid var(--detail-line); }
.simulator-inputs .details-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.75rem; }
.simulator-checks { display:grid; gap:.5rem; margin:.85rem 0 1rem; }
.simulator-check { display:flex; align-items:flex-start; gap:.5rem; color:var(--detail-ink); font-size:.82rem; }
.simulator-check input { margin-top:.25rem; accent-color:var(--color-primary); }
.simulator-results { display:grid; gap:.75rem; align-content:start; }
.sim-result-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:.55rem; }
.sim-result { padding:.75rem; border:1px solid var(--detail-line); border-radius:var(--radius-sm); background:#fff; }
.sim-result strong { display:block; margin-top:.25rem; color:var(--detail-ink); font-size:1.3rem; }
.sim-difference--down { border-color:var(--color-success-border); background:var(--color-success-bg); }
.sim-difference--down strong { color:var(--color-success); }
.sim-difference--up { border-color:var(--color-danger-border); background:var(--color-danger-bg); }
.sim-difference--up strong { color:var(--color-danger); }
.sim-difference--same { background:#faf7f7; }
.remaining-risks { padding-top:.8rem; border-top:1px solid var(--detail-line); }
.remaining-risks h3 { margin-bottom:.55rem; font-size:.9rem; }
.remaining-risks ul { display:grid; gap:.45rem; margin:0; padding-left:1.1rem; color:var(--detail-ink); font-size:.82rem; }
@media (max-width: 820px) { .detail-summary { grid-template-columns:1fr; } .detail-score-wrap { align-items:flex-start; flex-direction:row; } .detail-summary-meta { grid-template-columns:repeat(2, minmax(0, 1fr)); } .simulator-grid { grid-template-columns:1fr; } .simulator-inputs { padding:0 0 1rem; border-right:0; border-bottom:1px solid var(--detail-line); } }
@media (max-width: 560px) { .risk-detail .detail-top, .detail-card-heading { align-items:flex-start; flex-direction:column; } .detail-summary { padding:1rem; } .detail-summary-meta, .detail-groups, .simulator-inputs .details-grid { grid-template-columns:1fr 1fr; } .detail-tiles { grid-template-columns:1fr; } .sim-result-grid { grid-template-columns:1fr; } }
</style>

<div class="risk-detail">
    <div class="detail-top"><div><a class="detail-back" href="<?= e(url('modules/buyer_risk_analyzer/index.php')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>Back to Analyzer</a></div><span class="badge badge-info">Read-only analysis</span></div>

    <section class="detail-summary"><div><div class="detail-eyebrow">Buyer commitment dossier</div><h1><?= e($order['order_number']) ?></h1><div class="detail-style"><?= e($order['buyer_name']) ?> &bull; <?= e($order['style_number']) ?></div><div class="detail-summary-meta"><div class="detail-meta-item"><span>Order quantity</span><strong><?= number_format((int) $order['total_quantity']) ?> pieces</strong></div><div class="detail-meta-item"><span>Status</span><strong><?= e($order['order_status']) ?></strong></div><div class="detail-meta-item"><span>Delivery date</span><strong><?= e($order['delivery_date'] ?: 'Not scheduled') ?></strong></div><div class="detail-meta-item"><span>Countdown</span><strong><?php if ($baseRisk['days_remaining'] === null): ?>No date<?php elseif ($baseRisk['days_remaining'] < 0): ?><span class="badge badge-danger"><?= abs((int) $baseRisk['days_remaining']) ?> day(s) overdue</span><?php else: ?><?= (int) $baseRisk['days_remaining'] ?> day(s) remaining<?php endif; ?></strong></div></div></div><div class="detail-score-wrap"><div class="detail-score-ring"><div class="detail-score-content"><strong><?= (int) $baseRisk['score'] ?></strong><small>of 100</small></div></div><span class="badge <?= e(buyer_risk_badge_class($baseRisk['level'])) ?>"><?= e($baseRisk['level']) ?> risk</span></div></section>

    <section class="detail-card"><div class="detail-card-heading"><h2>Risk-factor breakdown</h2><span class="badge badge-muted">Base score <?= (int) $baseRisk['score'] ?>/100</span></div><div class="detail-card-body"><div class="factor-list"><?php if (!$baseRisk['reasons']): ?><p class="text-muted">No rule-based risk factors are currently triggered.</p><?php else: ?><?php foreach ($baseRisk['reasons'] as $reason): ?><div class="factor-row"><span><?= e($reason['reason']) ?></span><span class="badge badge-warning">+<?= (int) $reason['points'] ?> points</span></div><?php endforeach; ?><?php endif; ?></div></div></section>

    <section class="detail-card"><div class="detail-card-heading"><h2>Lifecycle coverage</h2><span class="text-muted text-xs">Current recorded position</span></div><div class="detail-card-body"><div class="detail-groups">
        <div class="detail-group"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"></path><path d="M8 6V3h8v3M8 11h8M8 15h5"></path></svg>Production</h3><div class="detail-tiles"><div class="detail-tile"><span class="detail-tile-label">Batches</span><strong class="detail-tile-value"><?php if ((int) $order['total_batch_count'] === 0): ?><span class="text-muted">No data recorded</span><?php else: ?><?= (int) $order['total_batch_count'] ?> &middot; <?= number_format((int) $order['allocated_quantity']) ?> allocated<?php endif; ?></strong></div><div class="detail-tile"><span class="detail-tile-label">Completion</span><strong class="detail-tile-value"><?= number_format($baseRisk['production_percent'], 1) ?>%</strong><div class="detail-progress"><span style="width: <?= min(100, max(0, (float) $baseRisk['production_percent'])) ?>%;"></span></div></div><div class="detail-tile"><span class="detail-tile-label">In progress</span><strong class="detail-tile-value"><?= (int) $order['in_progress_quantity'] > 0 ? number_format((int) $order['in_progress_quantity']) . ' pcs' : '<span class="text-muted">No data recorded</span>' ?></strong></div><div class="detail-tile"><span class="detail-tile-label">On hold</span><strong class="detail-tile-value"><?= (int) $order['on_hold_batch_count'] > 0 ? (int) $order['on_hold_batch_count'] : '<span class="text-muted">No data recorded</span>' ?></strong></div></div></div>
        <div class="detail-group"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11 3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>Quality Control</h3><div class="detail-tiles"><div class="detail-tile"><span class="detail-tile-label">Inspections</span><strong class="detail-tile-value"><?= (int) $order['inspection_count'] > 0 ? (int) $order['inspection_count'] . ' recorded' : '<span class="text-muted">No data recorded</span>' ?></strong></div><div class="detail-tile"><span class="detail-tile-label">Inspected / passed</span><strong class="detail-tile-value"><?= (int) $order['inspection_count'] > 0 ? number_format((int) $order['inspected_quantity']) . ' / ' . number_format((int) $order['passed_quantity']) : '<span class="text-muted">No data recorded</span>' ?></strong></div><div class="detail-tile"><span class="detail-tile-label">Defect rate</span><strong class="detail-tile-value"><?= (int) $order['inspection_count'] > 0 ? number_format($baseRisk['defect_percent'], 2) . '%' : '<span class="text-muted">No data recorded</span>' ?></strong><div class="detail-progress"><span style="width: <?= min(100, max(0, (float) $baseRisk['defect_percent'] * 10)) ?>%;"></span></div></div><div class="detail-tile"><span class="detail-tile-label">Failed / defective</span><strong class="detail-tile-value"><?= (int) $order['failed_inspection_count'] > 0 ? (int) $order['failed_inspection_count'] . ' failed' : '<span class="text-muted">No data recorded</span>' ?><?php if ((int) $order['inspection_count'] > 0): ?><small class="text-muted"> <?= number_format((int) $order['defective_quantity']) ?> pcs</small><?php endif; ?></strong></div></div></div>
        <div class="detail-group"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h18v13H3z"></path><path d="M1 3h22v5H1zM10 12h4"></path></svg>Packing &amp; Shipment</h3><div class="detail-tiles"><div class="detail-tile"><span class="detail-tile-label">Packed coverage</span><strong class="detail-tile-value"><?= (int) $order['packed_quantity'] > 0 ? number_format($packedPercent, 1) . '%' : '<span class="text-muted">No data recorded</span>' ?></strong><div class="detail-progress"><span style="width: <?= min(100, max(0, (float) $packedPercent)) ?>%;"></span></div></div><div class="detail-tile"><span class="detail-tile-label">Shipped / delivered</span><strong class="detail-tile-value"><?= (int) $order['shipped_delivered_quantity'] > 0 ? number_format($shippedPercent, 1) . '%' : '<span class="text-muted">No data recorded</span>' ?></strong><div class="detail-progress detail-progress--success"><span style="width: <?= min(100, max(0, (float) $shippedPercent)) ?>%;"></span></div></div><div class="detail-tile"><span class="detail-tile-label">Packed quantity</span><strong class="detail-tile-value"><?= (int) $order['packed_quantity'] > 0 ? number_format((int) $order['packed_quantity']) . ' pcs' : '<span class="text-muted">No data recorded</span>' ?></strong></div><div class="detail-tile"><span class="detail-tile-label">Active shipments</span><strong class="detail-tile-value"><?= (int) $order['active_shipment_count'] > 0 ? (int) $order['active_shipment_count'] : '<span class="text-muted">No data recorded</span>' ?></strong></div></div></div>
        <div class="detail-group"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><path d="M8 9h8M8 13h5"></path></svg>Buyer Changes</h3><div class="detail-tiles"><div class="detail-tile"><span class="detail-tile-label">Pending total</span><strong class="detail-tile-value"><?= (int) $order['pending_change_count'] > 0 ? (int) $order['pending_change_count'] : '<span class="text-muted">No data recorded</span>' ?></strong></div><div class="detail-tile"><span class="detail-tile-label">High / Critical</span><strong class="detail-tile-value"><?= (int) $order['pending_high_critical_change_count'] > 0 ? (int) $order['pending_high_critical_change_count'] : '<span class="text-muted">No data recorded</span>' ?></strong></div></div></div>
    </div></div></section>

    <section class="detail-card action-panel"><div class="detail-card-heading"><h2>Recommended actions</h2><span class="badge badge-warning">Decision support</span></div><div class="detail-card-body"><?php if (!$baseRisk['actions']): ?><p class="text-muted">No immediate rule-based action is recommended.</p><?php else: ?><ol class="action-list"><?php foreach ($baseRisk['actions'] as $action): ?><li><?= e($action) ?></li><?php endforeach; ?></ol><?php endif; ?></div></section>

    <section class="detail-card"><div class="detail-card-heading"><h2>What-If Simulator</h2><span class="badge badge-info">Read-only</span></div><div class="detail-card-body"><div class="alert alert-warning"><strong>Simulation only - no database records were changed.</strong> Values below are temporary GET parameters and are recalculated in memory.</div><div class="simulator-grid"><div class="simulator-inputs"><form method="get" action="<?= e(url('modules/buyer_risk_analyzer/view.php')) ?>"><input type="hidden" name="id" value="<?= (int) $order['order_id'] ?>"><div class="details-grid"><div><label for="extra_completed_quantity" class="form-label">Extra completed production quantity</label><input type="number" id="extra_completed_quantity" name="extra_completed_quantity" class="form-control" min="0" max="<?= $maximumQuantity ?>" value="<?= (int) $simulation['extra_completed_quantity'] ?>"></div><div><label for="delivery_extension_days" class="form-label">Delivery extension (days)</label><input type="number" id="delivery_extension_days" name="delivery_extension_days" class="form-control" min="0" max="365" value="<?= (int) $simulation['delivery_extension_days'] ?>"></div><div><label for="extra_packed_quantity" class="form-label">Extra packed / shipped quantity</label><input type="number" id="extra_packed_quantity" name="extra_packed_quantity" class="form-control" min="0" max="<?= $maximumQuantity ?>" value="<?= (int) $simulation['extra_packed_quantity'] ?>"></div></div><div class="simulator-checks"><span class="form-label">Temporary resolutions</span><label class="simulator-check"><input type="checkbox" name="resolve_on_hold" value="1" <?= $simulation['resolve_on_hold'] ? 'checked' : '' ?>> <span>Resolve On-Hold batches</span></label><label class="simulator-check"><input type="checkbox" name="resolve_qc_failures" value="1" <?= $simulation['resolve_qc_failures'] ? 'checked' : '' ?>> <span>Resolve QC failures</span></label><label class="simulator-check"><input type="checkbox" name="resolve_priority_changes" value="1" <?= $simulation['resolve_priority_changes'] ? 'checked' : '' ?>> <span>Resolve pending High/Critical changes</span></label></div><button type="submit" class="btn btn-primary">Recalculate Simulation</button> <a href="<?= e(url('modules/buyer_risk_analyzer/view.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-secondary">Reset</a></form></div><div class="simulator-results"><div class="sim-result-grid"><div class="sim-result"><span class="detail-tile-label">Base risk</span><strong><?= (int) $baseRisk['score'] ?>/100</strong><span class="badge <?= e(buyer_risk_badge_class($baseRisk['level'])) ?>"><?= e($baseRisk['level']) ?></span></div><div class="sim-result"><span class="detail-tile-label">Simulated risk</span><strong><?= (int) $simulatedRisk['score'] ?>/100</strong><span class="badge <?= e(buyer_risk_badge_class($simulatedRisk['level'])) ?>"><?= e($simulatedRisk['level']) ?></span></div><?php $simulationDifference = $simulatedRisk['score'] - $baseRisk['score']; $differenceClass = $simulationDifference < 0 ? 'sim-difference--down' : ($simulationDifference > 0 ? 'sim-difference--up' : 'sim-difference--same'); ?><div class="sim-result <?= e($differenceClass) ?>"><span class="detail-tile-label">Score difference</span><strong><?= sprintf('%+d', $simulationDifference) ?></strong><span class="text-muted text-xs"><?= $simulationDifference < 0 ? 'Risk reduced' : ($simulationDifference > 0 ? 'Risk increased' : 'No change') ?></span></div></div><div class="remaining-risks"><h3>Risks remaining in simulation</h3><?php if (!$simulatedRisk['reasons']): ?><p class="text-muted">No rule-based risks remain under these assumptions.</p><?php else: ?><ul><?php foreach ($simulatedRisk['reasons'] as $reason): ?><li><?= e($reason['reason']) ?> <span class="badge badge-warning">+<?= (int) $reason['points'] ?></span></li><?php endforeach; ?></ul><?php endif; ?></div></div></div></div></section>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
