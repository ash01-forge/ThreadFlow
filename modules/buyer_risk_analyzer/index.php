<?php
/** ThreadFlow - Buyer Commitment Risk & What-If Analyzer dashboard. */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/helpers.php';

require_role(['admin', 'merchandiser', 'management']);

$pageTitle = 'Buyer Risk Analyzer';
$currentNav = 'buyer_risk_analyzer';
$levelFilter = trim((string) ($_GET['risk'] ?? 'all'));
$validLevels = ['all', 'Low', 'Medium', 'High', 'Critical'];
if (!in_array($levelFilter, $validLevels, true)) $levelFilter = 'all';

$orders = [];
$analyzedOrders = [];
try {
    $stmt = $conn->prepare(buyer_risk_order_sql("bo.status IN ('Pending', 'Confirmed', 'In Production')"));
    if (!$stmt) throw new mysqli_sql_exception('Risk analyzer query could not be prepared.');
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as $row) {
        $row['risk'] = buyer_calculate_risk($row);
        $analyzedOrders[] = $row;
        if ($levelFilter === 'all' || $row['risk']['level'] === $levelFilter) $orders[] = $row;
    }
    usort($orders, static function (array $left, array $right): int {
        return $right['risk']['score'] <=> $left['risk']['score'];
    });
} catch (mysqli_sql_exception $e) {
    error_log('Buyer risk analyzer dashboard error: ' . $e->getMessage());
    flash_set('error', 'A database error occurred while loading buyer commitment analysis.');
}

$criticalCount = 0;
$dueSoonCount = 0;
$priorityChangeCount = 0;
$atRiskQuantity = 0;
foreach ($analyzedOrders as $order) {
    if ($order['risk']['level'] === 'Critical') $criticalCount++;
    if ($order['risk']['days_remaining'] !== null && $order['risk']['days_remaining'] >= 0 && $order['risk']['days_remaining'] <= 7) $dueSoonCount++;
    if ((int) $order['pending_high_critical_change_count'] > 0) $priorityChangeCount++;
    if ($order['risk']['score'] >= 40) $atRiskQuantity += (int) $order['total_quantity'];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.risk-dashboard { --risk-ink: #2c2022; --risk-muted: #75676a; --risk-line: #eed9dd; --risk-soft: #fff8f9; }
.risk-hero { display:flex; align-items:flex-end; justify-content:space-between; gap:1.5rem; padding:1.55rem 1.7rem; margin-bottom:1.15rem; border:1px solid var(--risk-line); border-radius:var(--radius-lg); background:linear-gradient(120deg, #fff 0%, #fff5f6 100%); box-shadow:var(--shadow-sm); }
.risk-hero h1 { margin-bottom:.35rem; font-size:clamp(1.5rem, 3vw, 2.15rem); }
.risk-hero .page-subtitle { max-width:650px; }
.risk-readonly { display:inline-flex; align-items:center; gap:.45rem; padding:.42rem .7rem; border:1px solid var(--color-success-border); border-radius:var(--radius-full); color:var(--color-success); background:var(--color-success-bg); font-size:.75rem; font-weight:700; white-space:nowrap; }
.risk-readonly::before { width:7px; height:7px; border-radius:50%; background:currentColor; content:""; }
.risk-kpis { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.85rem; margin-bottom:1rem; }
.risk-kpi { position:relative; min-height:128px; overflow:hidden; padding:1rem 1.1rem; border:1px solid var(--risk-line); border-radius:var(--radius-md); background:#fff; box-shadow:var(--shadow-xs); }
.risk-kpi::after { position:absolute; right:-28px; bottom:-35px; width:110px; height:110px; border-radius:50%; background:var(--kpi-wash); content:""; }
.risk-kpi--danger { --kpi-accent:var(--color-danger); --kpi-wash:var(--color-danger-bg); }
.risk-kpi--warning { --kpi-accent:var(--color-warning); --kpi-wash:var(--color-warning-bg); }
.risk-kpi--info { --kpi-accent:var(--color-info); --kpi-wash:var(--color-info-bg); }
.risk-kpi--neutral { --kpi-accent:var(--color-primary); --kpi-wash:var(--color-primary-subtle); }
.risk-kpi-top { display:flex; align-items:center; justify-content:space-between; color:var(--kpi-accent); font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.risk-kpi-icon { display:grid; place-items:center; width:30px; height:30px; border-radius:8px; background:var(--kpi-wash); }
.risk-kpi-icon svg { width:17px; height:17px; stroke:currentColor; }
.risk-kpi-value { position:relative; z-index:1; display:block; margin-top:.6rem; color:var(--risk-ink); font-size:1.85rem; line-height:1; }
.risk-kpi-copy { position:relative; z-index:1; margin:.35rem 0 0; color:var(--risk-muted); font-size:.78rem; }
.risk-filter { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.8rem 1rem; margin-bottom:1rem; border:1px solid var(--risk-line); border-radius:var(--radius-md); background:#fff; }
.risk-filter-label { display:flex; align-items:center; gap:.65rem; color:var(--risk-ink); font-size:.86rem; font-weight:700; }
.risk-filter-label svg { width:18px; height:18px; color:var(--color-primary); }
.risk-filter-form { display:flex; align-items:center; gap:.55rem; }
.risk-filter-form .form-control { width:180px; min-height:36px; }
.risk-ranking-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.15rem; border-bottom:1px solid var(--risk-line); }
.risk-ranking-header h2 { margin:0; font-size:1.1rem; }
.risk-order-list { display:grid; gap:.75rem; padding:1rem; }
.risk-order { position:relative; display:grid; grid-template-columns:minmax(180px, 1.25fr) minmax(210px, 1fr) auto; gap:1.25rem; align-items:center; padding:1rem 1.1rem 1rem 1.25rem; border:1px solid var(--risk-line); border-radius:var(--radius-md); background:#fff; transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
.risk-order:hover { transform:translateY(-2px); border-color:#d8aeb5; box-shadow:var(--shadow-md); }
.risk-order::before { position:absolute; inset:0 auto 0 0; width:4px; border-radius:var(--radius-md) 0 0 var(--radius-md); background:var(--risk-accent); content:""; }
.risk-order--critical { --risk-accent:var(--color-danger); }
.risk-order--high { --risk-accent:var(--color-warning); }
.risk-order--medium { --risk-accent:var(--color-info); }
.risk-order--low { --risk-accent:var(--color-success); }
.risk-order-title { display:flex; align-items:center; gap:.55rem; margin-bottom:.15rem; color:var(--risk-ink); font-size:1rem; }
.risk-order-title a { color:inherit; }
.risk-order-style, .risk-order-buyer { color:var(--risk-muted); font-size:.8rem; }
.risk-order-buyer { margin-top:.55rem; font-weight:600; }
.risk-order-meta { display:flex; flex-wrap:wrap; gap:.45rem 1rem; margin-top:.8rem; color:var(--risk-muted); font-size:.77rem; }
.risk-order-meta strong { color:var(--risk-ink); }
.risk-metric { min-width:0; }
.risk-metric-head { display:flex; justify-content:space-between; gap:.6rem; margin-bottom:.3rem; color:var(--risk-muted); font-size:.72rem; }
.risk-metric-head strong { color:var(--risk-ink); }
.risk-progress { height:7px; overflow:hidden; border-radius:var(--radius-full); background:#f3e6e8; }
.risk-progress span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); }
.risk-progress--ship span { background:var(--color-success); }
.risk-order-metrics { display:grid; gap:.7rem; }
.risk-order-score { display:flex; align-items:center; gap:.8rem; justify-content:flex-end; }
.risk-score-number { color:var(--risk-accent); font-size:1.65rem; font-weight:800; line-height:1; text-align:right; }
.risk-score-number small { display:block; color:var(--risk-muted); font-size:.65rem; font-weight:600; }
.risk-order-action { display:flex; justify-content:flex-end; margin-top:.75rem; }
.risk-priority { margin-left:.3rem; }
@media (max-width: 980px) { .risk-kpis { grid-template-columns:repeat(2, minmax(0, 1fr)); } .risk-order { grid-template-columns:1fr 1fr; } .risk-order-score { justify-content:flex-start; } .risk-order-action { justify-content:flex-start; } }
@media (max-width: 640px) { .risk-hero, .risk-filter { align-items:flex-start; flex-direction:column; } .risk-hero { padding:1.2rem; } .risk-kpis { grid-template-columns:1fr 1fr; gap:.6rem; } .risk-kpi { min-height:116px; padding:.85rem; } .risk-kpi-value { font-size:1.55rem; } .risk-filter { gap:.7rem; } .risk-filter-form { width:100%; } .risk-filter-form .form-control { flex:1; width:auto; } .risk-order { grid-template-columns:1fr; gap:.9rem; padding:.95rem 1rem .95rem 1.1rem; } }
</style>

<div class="risk-dashboard">
    <section class="risk-hero" aria-labelledby="risk-dashboard-title">
        <div><div class="risk-kicker">ThreadFlow decision desk</div><h1 id="risk-dashboard-title">Buyer Commitment Risk Analyzer</h1><div class="page-subtitle">A focused view of open-order delivery exposure across production, quality, packing, shipment, and buyer changes.</div></div>
        <span class="risk-readonly">Read-only analytics</span>
    </section>

    <div class="risk-kpis">
        <div class="risk-kpi risk-kpi--danger"><div class="risk-kpi-top"><span>Critical risk</span><span class="risk-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.8 20h18.4L12 3Z"></path><path d="M12 9v4M12 17h.01"></path></svg></span></div><strong class="risk-kpi-value"><?= (int) $criticalCount ?></strong><p class="risk-kpi-copy">Open orders scoring 70+</p></div>
        <div class="risk-kpi risk-kpi--warning"><div class="risk-kpi-top"><span>Due soon</span><span class="risk-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span></div><strong class="risk-kpi-value"><?= (int) $dueSoonCount ?></strong><p class="risk-kpi-copy">Orders due within 7 days</p></div>
        <div class="risk-kpi risk-kpi--info"><div class="risk-kpi-top"><span>Buyer changes</span><span class="risk-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 1-13.5 5.8L4 19"></path><path d="M4 13V7h6"></path><path d="M4 7a8 8 0 0 1 13.5-1.8L20 3"></path><path d="M20 9V3h-6"></path></svg></span></div><strong class="risk-kpi-value"><?= (int) $priorityChangeCount ?></strong><p class="risk-kpi-copy">Orders with priority changes</p></div>
        <div class="risk-kpi risk-kpi--neutral"><div class="risk-kpi-top"><span>At-risk quantity</span><span class="risk-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z"></path><path d="m4 7.5 8 4.5 8-4.5M12 12v9"></path></svg></span></div><strong class="risk-kpi-value"><?= number_format($atRiskQuantity) ?></strong><p class="risk-kpi-copy">Pieces in High or Critical orders</p></div>
    </div>

    <div class="risk-filter"><div class="risk-filter-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"></path></svg><span>Focus the risk queue</span></div><form method="get" action="<?= e(url('modules/buyer_risk_analyzer/index.php')) ?>" class="risk-filter-form"><label for="risk" class="visually-hidden">Risk level</label><select id="risk" name="risk" class="form-control"><?php foreach ($validLevels as $level): ?><option value="<?= e($level) ?>" <?= $levelFilter === $level ? 'selected' : '' ?>><?= e($level === 'all' ? 'All risk levels' : $level) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-primary btn-sm">Apply</button><?php if ($levelFilter !== 'all'): ?><a href="<?= e(url('modules/buyer_risk_analyzer/index.php')) ?>" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?></form></div>

    <section class="card" aria-labelledby="risk-ranking-title"><div class="risk-ranking-header"><h2 id="risk-ranking-title">Open Order Risk Ranking</h2><span class="badge badge-muted"><?= count($orders) ?> order(s)</span></div><div class="risk-order-list">
        <?php if (!$orders): ?><div class="empty-state"><p>No open orders match this risk filter.</p></div><?php else: ?>
        <?php foreach ($orders as $order): $risk = $order['risk']; $packedPercent = buyer_risk_percent((float) $order['packed_quantity'], (float) $order['total_quantity']); $shippedPercent = buyer_risk_percent((float) $order['shipped_delivered_quantity'], (float) $order['total_quantity']); ?>
        <article class="risk-order risk-order--<?= e(strtolower($risk['level'])) ?>">
            <div><div class="risk-order-title"><span class="text-muted text-xs">#<?= (int) $order['order_id'] ?></span><strong><a href="<?= e(url('modules/buyer_risk_analyzer/view.php?id=' . (int) $order['order_id'])) ?>"><?= e($order['order_number']) ?></a></strong></div><div class="risk-order-style"><?= e($order['style_number']) ?></div><div class="risk-order-buyer"><?= e($order['buyer_name']) ?></div><div class="risk-order-meta"><span>Due <strong><?= e($order['delivery_date'] ?: 'Not scheduled') ?></strong></span><span><?php if ($risk['days_remaining'] === null): ?><span class="text-muted">No countdown</span><?php elseif ($risk['days_remaining'] < 0): ?><span class="badge badge-danger"><?= abs((int) $risk['days_remaining']) ?> day(s) overdue</span><?php else: ?><strong><?= (int) $risk['days_remaining'] ?></strong> day(s) remaining<?php endif; ?></span></div></div>
            <div class="risk-order-metrics"><div class="risk-metric"><div class="risk-metric-head"><span>Production completion</span><strong><?= number_format($risk['production_percent'], 1) ?>%</strong></div><div class="risk-progress"><span style="width: <?= min(100, max(0, (float) $risk['production_percent'])) ?>%;"></span></div></div><div class="risk-metric"><div class="risk-metric-head"><span>Packed / shipped</span><strong><?= number_format($packedPercent, 1) ?>% / <?= number_format($shippedPercent, 1) ?>%</strong></div><div class="risk-progress risk-progress--ship"><span style="width: <?= min(100, max(0, (float) $shippedPercent)) ?>%;"></span></div></div><div class="risk-order-meta"><span>QC defect <strong><?= number_format($risk['defect_percent'], 1) ?>%</strong></span><span><?= (int) $order['pending_change_count'] ?> pending<?php if ((int) $order['pending_high_critical_change_count'] > 0): ?> <span class="badge badge-danger risk-priority">Priority</span><?php endif; ?></span></div></div>
            <div><div class="risk-order-score"><div class="risk-score-number"><?= (int) $risk['score'] ?><small>/ 100</small></div><span class="badge <?= e(buyer_risk_badge_class($risk['level'])) ?>"><?= e($risk['level']) ?></span></div><div class="risk-order-action"><a href="<?= e(url('modules/buyer_risk_analyzer/view.php?id=' . (int) $order['order_id'])) ?>" class="btn btn-sm btn-secondary">View Analysis</a></div></div>
        </article>
        <?php endforeach; ?><?php endif; ?>
    </div></section>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
