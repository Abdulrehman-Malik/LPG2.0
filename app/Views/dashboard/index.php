<?= $this->extend('layouts/app') ?>

<?= $this->section('styles') ?>
<style>
.dash-presets .btn { --bs-btn-padding-y: .3rem; --bs-btn-font-size: .85rem; }
.dash-presets .btn.active { background: rgba(var(--lpg-accent-rgb), .14); border-color: var(--lpg-accent); color: var(--lpg-text); }
.dash-alerts { display: grid; gap: .5rem; margin-bottom: 1rem; }
.dash-alert { display: flex; align-items: center; gap: .65rem; padding: .6rem .85rem; border-radius: var(--lpg-radius); border: 1px solid var(--lpg-border); background: var(--lpg-surface); font-size: .9rem; }
.dash-alert > .bi { font-size: 1.1rem; }
.dash-alert.is-danger { background: var(--lpg-danger-soft); border-color: transparent; }
.dash-alert.is-danger > .bi { color: var(--lpg-danger); }
.dash-alert.is-warning { background: var(--lpg-warning-soft); border-color: transparent; }
.dash-alert.is-warning > .bi { color: var(--lpg-warning); }
.dash-alert.is-info { background: var(--lpg-info-soft); border-color: transparent; }
.dash-alert.is-info > .bi { color: var(--lpg-info); }
.dash-alert a { margin-left: auto; white-space: nowrap; font-weight: 600; }

.dashboard-card { display: block; width: 100%; text-align: left; vertical-align: top; cursor: pointer; transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
.dashboard-card:hover { border-color: var(--lpg-border-strong); box-shadow: var(--lpg-shadow); transform: translateY(-1px); }
.dashboard-card:focus-visible { outline: 2px solid var(--lpg-accent); outline-offset: 2px; }
.dashboard-card .kpi-more { position: absolute; right: .9rem; bottom: .8rem; color: var(--lpg-muted); font-size: .8rem; }
.dash-chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .45rem; }
.dash-chips span { font-size: .72rem; padding: .12rem .45rem; border-radius: 999px; background: var(--lpg-surface-2); border: 1px solid var(--lpg-border); color: var(--lpg-text-2); }
.dash-section-label { font-size: .75rem; font-weight: 650; letter-spacing: .05em; text-transform: uppercase; color: var(--lpg-muted); margin: 1.25rem 0 .5rem; }

.dash-chart { position: relative; height: 260px; }
.dash-bars { display: grid; gap: .7rem; }
.dash-bar-row { display: grid; grid-template-columns: 4.5rem 1fr auto; gap: .6rem; align-items: center; font-size: .88rem; }
.dash-bar-track { height: .6rem; border-radius: 4px; background: var(--lpg-neutral-soft); overflow: hidden; }
.dash-bar-fill { height: 100%; border-radius: 0 4px 4px 0; background: var(--lpg-viz-1); }
.dash-bar-value { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.dash-bar-value small { color: var(--lpg-muted); margin-left: .35rem; }
.dash-cash { display: flex; gap: .8rem; align-items: flex-start; }
.dash-cash-icon { width: 2.5rem; height: 2.5rem; border-radius: var(--lpg-radius-sm); display: grid; place-items: center; font-size: 1.2rem; flex: 0 0 auto; }
.dash-mini-bar { height: .45rem; border-radius: 4px; background: var(--lpg-neutral-soft); min-width: 5rem; }
.dash-mini-bar > span { display: block; height: 100%; border-radius: 0 4px 4px 0; background: var(--lpg-viz-1); }
.dash-list { list-style: none; margin: 0; padding: 0; }
.dash-list li { display: flex; align-items: center; gap: .75rem; padding: .6rem 1rem; border-top: 1px solid var(--lpg-border); }
.dash-list li:first-child { border-top: 0; }
.dash-list .meta { color: var(--lpg-muted); font-size: .8rem; }

/* Drill-down content (copied into the modal) */
.detail-intro { display: flex; align-items: center; gap: .9rem; padding: .85rem 1rem; border-radius: var(--lpg-radius); margin-bottom: 1rem; background: var(--lpg-surface-2); border: 1px solid var(--lpg-border); }
.detail-intro .bi { font-size: 1.5rem; color: var(--lpg-link); }
.detail-intro strong { display: block; font-size: 1.15rem; }
.detail-intro span { display: block; font-size: .8rem; color: var(--lpg-muted); }
.dashboard-drill-card { border: 1px solid var(--lpg-border); border-radius: var(--lpg-radius); margin-bottom: .6rem; overflow: hidden; background: var(--lpg-surface); }
.dashboard-drill-card summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: .55rem; padding: .75rem .9rem; font-size: .9rem; flex-wrap: wrap; }
.dashboard-drill-card summary::-webkit-details-marker { display: none; }
.dashboard-drill-card summary::after { content: "\f282"; font-family: "bootstrap-icons"; margin-left: .25rem; color: var(--lpg-muted); }
.dashboard-drill-card[open] summary { background: var(--lpg-surface-2); border-bottom: 1px solid var(--lpg-border); }
.dashboard-drill-card[open] summary::after { content: "\f286"; }
.detail-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .6rem; margin-bottom: 1rem; }
.detail-kpi-grid > div { border: 1px solid var(--lpg-border); border-radius: var(--lpg-radius); padding: .65rem .75rem; background: var(--lpg-surface-2); }
.detail-kpi-grid span { display: block; font-size: .75rem; color: var(--lpg-muted); }
.detail-kpi-grid strong { display: block; margin-top: .15rem; font-variant-numeric: tabular-nums; }
.expense-category { border: 1px solid var(--lpg-border); border-radius: var(--lpg-radius); padding: .65rem .75rem; background: var(--lpg-surface); }
.expense-category span, .expense-category small { display: block; color: var(--lpg-muted); }
.stock-type-card .kpi { transition: border-color .15s ease; }
.stock-type-card.is-active .kpi { border-color: var(--lpg-accent); box-shadow: inset 0 0 0 1px var(--lpg-accent); }
@media (max-width: 575.98px) { .dash-chart { height: 220px; } .dash-bar-row { grid-template-columns: 4rem 1fr auto; } }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$money = static fn (float $v): string => 'Rs. ' . number_format($v, 2);
$qty = static fn (float $v): string => number_format($v, 2);
$stockTab = trim((string) (service('request')->getGet('stock_tab') ?? ''));
if (!in_array($stockTab, ['filled', 'empty', 'issued'], true)) {
    $stockTab = '';
}
$displayStockTab = $stockTab !== '' ? $stockTab : 'filled';
$dashUrl = static fn (array $q): string => site_url('dashboard') . '?' . http_build_query($q);

$today = date('Y-m-d');
$presets = [
    'Today' => [$today, $today],
    'Yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
    'Last 7 days' => [date('Y-m-d', strtotime('-6 days')), $today],
    'This month' => [date('Y-m-01'), $today],
];

// Alerts: things that need attention now.
$alerts = [];
if ($canCash && !$openCash) {
    $alerts[] = ['warning', 'bi-cash-coin', 'No cash session is open. Cash sales and receipts cannot be posted until one is opened.', site_url('cash'), 'Open cash session'];
}
if ($incomingTransfers > 0) {
    $alerts[] = ['info', 'bi-truck', $incomingTransfers . ' stock transfer' . ($incomingTransfers === 1 ? ' is' : 's are') . ' on the way to this branch and waiting for you to confirm receipt.', $canTransfers ? site_url('inventory/transfers') : null, 'Review transfers'];
}
$partialTotal = 0;
foreach ($stockByType as $t) {
    $partialTotal += $t['partial'];
    if ($t['filled'] === 0 && ($t['empty'] > 0 || $t['custody'] > 0)) {
        $alerts[] = ['danger', 'bi-exclamation-octagon', 'Out of stock: no filled ' . $t['code'] . ' (' . $t['name'] . ') cylinders. ' . $t['empty'] . ' empty in the shop.', null, ''];
    }
}
if ($partialTotal > 0) {
    $alerts[] = ['info', 'bi-hourglass-split', $partialTotal . ' filled cylinder' . ($partialTotal === 1 ? ' is' : 's are') . ' partly used.', $dashUrl(['from' => $range['from'], 'to' => $range['to'], 'stock_tab' => 'filled']), 'See cylinders'];
}

$paymentMix = ['Cash' => $saleSummary['cash'], 'Online' => $saleSummary['online'], 'Cheque' => $saleSummary['cheque'], 'Credit' => $saleSummary['credit']];
$mixTotal = array_sum($paymentMix);
$mixMax = max(0.01, ...array_values($paymentMix));
$trendTotal = array_sum(array_column($trend['days'], 'total'));
$maxTypeGas = max(0.01, ...array_map(static fn ($t) => (float) $t['gas_kg'], $stockByType ?: [['gas_kg' => 0]]));
$maxBranchSales = max(0.01, ...array_map(static fn ($b) => (float) $b['sales_total'], $branches ?: [['sales_total' => 0]]));
$currentLocationId = (int) session()->get('location_id');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Branch overview</h1>
        <p class="page-subtitle">Sales, cash and expenses for <strong><?= esc($range['label']) ?></strong>. Stock, customer balances and cash session are as of now.</p>
    </div>
    <div class="page-actions">
        <div class="btn-group dash-presets" role="group" aria-label="Date range">
            <?php foreach ($presets as $label => [$pFrom, $pTo]): $on = $range['from'] === $pFrom && $range['to'] === $pTo; ?>
                <a class="btn btn-outline-secondary <?= $on ? 'active' : '' ?>" href="<?= esc($dashUrl(['from' => $pFrom, 'to' => $pTo])) ?>" <?= $on ? 'aria-current="true"' : '' ?>><?= esc($label) ?></a>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="dashboardFilterToggle"
                data-bs-toggle="collapse" data-bs-target="#dashboardDateFilter" aria-expanded="false" aria-controls="dashboardDateFilter">
            <i class="bi bi-calendar-range me-1"></i><span>Show Date Filter</span>
        </button>
    </div>
</div>

<div class="collapse mb-3" id="dashboardDateFilter">
    <div class="card">
        <div class="card-body">
            <form method="get" action="<?= site_url('dashboard') ?>" class="row g-2 align-items-end">
                <div class="col-sm-5 col-md-3">
                    <label class="form-label" for="dashFrom">From</label>
                    <input type="date" id="dashFrom" name="from" class="form-control" value="<?= esc($range['from']) ?>" required>
                </div>
                <div class="col-sm-5 col-md-3">
                    <label class="form-label" for="dashTo">To</label>
                    <input type="date" id="dashTo" name="to" class="form-control" value="<?= esc($range['to']) ?>" required>
                </div>
                <div class="col-sm-2 col-md-auto">
                    <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($alerts): ?>
<div class="dash-alerts" aria-label="Needs attention">
    <?php foreach ($alerts as [$type, $icon, $text, $url, $action]): ?>
        <div class="dash-alert is-<?= $type ?>" role="<?= $type === 'danger' ? 'alert' : 'status' ?>">
            <i class="bi <?= $icon ?>" aria-hidden="true"></i><span><?= esc($text) ?></span>
            <?php if ($url): ?><a href="<?= esc($url) ?>"><?= esc($action) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" id="dashboardTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $stockTab === '' ? 'active' : '' ?>" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-pane" type="button" role="tab" aria-controls="summary-pane" aria-selected="<?= $stockTab === '' ? 'true' : 'false' ?>"><i class="bi bi-grid-1x2 me-1"></i>Summary</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $stockTab !== '' ? 'active' : '' ?>" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock-pane" type="button" role="tab" aria-controls="stock-pane" aria-selected="<?= $stockTab !== '' ? 'true' : 'false' ?>"><i class="bi bi-boxes me-1"></i>Current Stock</button>
    </li>
</ul>

<div class="tab-content" id="dashboardTabContent">
<div class="tab-pane fade <?= $stockTab === '' ? 'show active' : '' ?>" id="summary-pane" role="tabpanel" aria-labelledby="summary-tab">

    <div class="dash-section-label mt-0">Selected period · <?= esc($range['label']) ?></div>
    <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
            <button type="button" class="kpi dashboard-card is-success" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-sales">
                <div class="kpi-label">Sales <i class="bi bi-cart-check"></i></div>
                <div class="kpi-value"><?= $money((float) $saleSummary['total']) ?></div>
                <div class="kpi-hint"><?= number_format((int) $saleSummary['count']) ?> sale<?= (int) $saleSummary['count'] === 1 ? '' : 's' ?></div>
                <div class="dash-chips"><span>Cash <?= $money((float) $saleSummary['cash']) ?></span><span>Online <?= $money((float) $saleSummary['online']) ?></span><span>Credit <?= $money((float) $saleSummary['credit']) ?></span></div>
            </button>
        </div>
        <div class="col-sm-6 col-xl-3">
            <button type="button" class="kpi dashboard-card is-warning" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-cash">
                <div class="kpi-label">Cash Counter <i class="bi bi-cash-stack"></i></div>
                <div class="kpi-value"><?= $money((float) $cashIn - (float) $cashOut) ?></div>
                <div class="kpi-hint">In <?= $money((float) $cashIn) ?> · Out <?= $money((float) $cashOut) ?></div>
                <div class="kpi-hint"><?= number_format(count($cashRegisters)) ?> register(s) with movements</div>
            </button>
        </div>
        <div class="col-sm-6 col-xl-3">
            <button type="button" class="kpi dashboard-card is-danger" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-receivable">
                <div class="kpi-label">Credit Sales / Accounts Receivable <i class="bi bi-person-lines-fill"></i></div>
                <div class="kpi-value"><?= $money((float) $receivableTotal) ?></div>
                <div class="kpi-hint"><?= number_format(count($receivables)) ?> customer<?= count($receivables) === 1 ? '' : 's' ?> owe money · as of today</div>
            </button>
        </div>
        <div class="col-sm-6 col-xl-3">
            <button type="button" class="kpi dashboard-card" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-expenses">
                <div class="kpi-label">Expenses <i class="bi bi-receipt"></i></div>
                <div class="kpi-value"><?= $money((float) $expenseTotal) ?></div>
                <div class="kpi-hint"><?= number_format(count($expenseRows)) ?> entr<?= count($expenseRows) === 1 ? 'y' : 'ies' ?><?= $expenseCategories ? ' · most on ' . esc($expenseCategories[0]['name']) : '' ?></div>
            </button>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Daily sales</span>
                    <span class="small text-muted"><?= esc(date('d M', strtotime($trend['from']))) ?> – <?= esc(date('d M Y', strtotime($trend['to']))) ?> · total <?= $money($trendTotal) ?></span>
                </div>
                <div class="card-body">
                    <div class="dash-chart"><canvas id="salesTrendChart" role="img" aria-label="Daily sales from <?= esc($trend['from']) ?> to <?= esc($trend['to']) ?>, total <?= esc($money($trendTotal)) ?>. A data table follows."></canvas></div>
                    <details class="mt-2 small">
                        <summary class="text-muted">Show as table</summary>
                        <div class="table-responsive mt-2" style="max-height:16rem">
                            <table class="table table-sm mb-0"><thead><tr><th>Date</th><th class="text-end">Sales</th></tr></thead><tbody>
                            <?php foreach (array_reverse($trend['days']) as $d): ?><tr><td><?= esc(date('D d M Y', strtotime($d['day']))) ?></td><td class="text-end"><?= $money($d['total']) ?></td></tr><?php endforeach; ?>
                            </tbody></table>
                        </div>
                    </details>
                </div>
            </div>
        </div>
        <div class="col-xl-4 d-flex flex-column gap-3">
            <div class="card">
                <div class="card-header">How sales were paid</div>
                <div class="card-body">
                    <?php if ($mixTotal <= 0): ?>
                        <div class="empty-state py-3"><i class="bi bi-pie-chart"></i>No sales in this period.</div>
                    <?php else: ?>
                    <div class="dash-bars">
                        <?php foreach ($paymentMix as $mode => $amount): ?>
                            <div class="dash-bar-row" title="<?= esc($mode . ': ' . $money((float) $amount)) ?>">
                                <span><?= esc($mode) ?></span>
                                <div class="dash-bar-track"><div class="dash-bar-fill" style="width:<?= round(100 * (float) $amount / $mixMax, 1) ?>%"></div></div>
                                <span class="dash-bar-value"><?= $money((float) $amount) ?><small><?= round(100 * (float) $amount / $mixTotal) ?>%</small></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card flex-grow-1">
                <div class="card-header">Cash session</div>
                <div class="card-body">
                    <?php if ($openCash): ?>
                        <div class="dash-cash">
                            <span class="dash-cash-icon" style="background:var(--lpg-success-soft);color:var(--lpg-success)"><i class="bi bi-unlock"></i></span>
                            <div class="flex-grow-1">
                                <span class="status status-success">Open</span> <span class="small text-muted"><?= esc($openCash['register_code'] . ' — ' . $openCash['register_name']) ?></span>
                                <div class="kpi-value mt-1"><?= $money((float) $openCash['expected']) ?></div>
                                <div class="small text-muted">Expected in the drawer · opened <?= esc(date('d M, h:i A', strtotime($openCash['opened_at']))) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="dash-cash">
                            <span class="dash-cash-icon" style="background:var(--lpg-warning-soft);color:var(--lpg-warning)"><i class="bi bi-lock"></i></span>
                            <div><span class="status status-warning">Closed</span><div class="small text-muted mt-1">No register is open in this branch.</div></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($canCash): ?><a class="btn btn-sm btn-outline-secondary mt-3" href="<?= site_url('cash') ?>"><?= $openCash ? 'Go to Counter Cash' : 'Open a cash session' ?></a><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-section-label">Stock now</div>
    <div class="row g-3">
        <div class="col-sm-4">
            <button type="button" class="kpi dashboard-card is-info" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-gas">
                <div class="kpi-label">Available Gas KG <i class="bi bi-fuel-pump"></i></div>
                <div class="kpi-value"><?= $qty((float) $availableGasKg) ?> <span class="fs-6 text-muted">KG</span></div>
                <div class="kpi-hint"><?= number_format(count($gasRows)) ?> filled cylinders · <?= number_format(count($gasTypes)) ?> type(s)</div>
            </button>
        </div>
        <div class="col-sm-4">
            <button type="button" class="kpi dashboard-card" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-empty">
                <div class="kpi-label">Empty Gas Cylinders in Shop <i class="bi bi-circle"></i></div>
                <div class="kpi-value"><?= number_format((int) $emptyCylinderCount) ?></div>
                <div class="kpi-hint"><?= number_format(count($emptyTypes)) ?> cylinder type(s)</div>
            </button>
        </div>
        <div class="col-sm-4">
            <button type="button" class="kpi dashboard-card" data-bs-toggle="modal" data-bs-target="#dashboardDetailModal" data-detail-target="detail-issued">
                <div class="kpi-label">Issued Cylinders <i class="bi bi-person-badge"></i></div>
                <div class="kpi-value"><?= number_format((int) $issuedCylinderCount) ?></div>
                <div class="kpi-hint"><?= number_format(count($issuedCustomers)) ?> customer(s) · <?= $money((float) $issuedDepositTotal) ?> deposit held</div>
            </button>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Stock by cylinder type</span>
                    <a class="small" href="<?= esc($dashUrl(['from' => $range['from'], 'to' => $range['to'], 'stock_tab' => 'filled'])) ?>">All cylinders</a>
                </div>
                <?php if (!$stockByType): ?>
                    <div class="empty-state"><i class="bi bi-box"></i><div class="empty-state-title">No cylinders at this branch</div>Add stock from Opening Inventory or Purchases.</div>
                <?php else: ?>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Type</th><th>Gas KG</th><th class="text-end">Filled</th><th class="text-end">Empty</th><th class="text-end">With customers</th></tr></thead>
                        <tbody>
                        <?php foreach ($stockByType as $t): ?>
                            <tr>
                                <td><strong><?= esc($t['code']) ?></strong> <span class="small text-muted"><?= esc($t['name']) ?></span></td>
                                <td style="min-width:10rem">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="dash-mini-bar flex-grow-1" role="img" aria-label="<?= esc($t['code']) ?> share of gas stock"><span style="width:<?= round(100 * $t['gas_kg'] / $maxTypeGas, 1) ?>%"></span></div>
                                        <span class="text-nowrap"><?= $qty($t['gas_kg']) ?></span>
                                    </div>
                                </td>
                                <td class="text-end"><?= (int) $t['filled'] ?><?php if ($t['filled'] === 0): ?> <span class="status status-danger ms-1">Out</span><?php elseif ($t['partial']): ?><div class="small text-muted"><?= (int) $t['partial'] ?> partly used</div><?php endif; ?></td>
                                <td class="text-end"><?= (int) $t['empty'] ?></td>
                                <td class="text-end"><?= (int) $t['custody'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Latest sales</span>
                    <a class="small" href="<?= site_url('sales/history') ?>">Sale History</a>
                </div>
                <?php if (!$salesRows): ?>
                    <div class="empty-state"><i class="bi bi-cart"></i><div class="empty-state-title">No sales in this period</div><a href="<?= site_url('sales') ?>">Open POS</a></div>
                <?php else: ?>
                <ul class="dash-list">
                    <?php foreach (array_slice($salesRows, 0, 6) as $s): ?>
                        <li>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold"><?= esc($s['customer_name'] ?: 'Walk-in / Cash') ?></div>
                                <div class="meta"><?= esc($s['sale_no']) ?> · <?= esc(date('d M, h:i A', strtotime($s['transaction_at']))) ?> · <?= esc(ucwords(str_replace('_', ' ', $s['transaction_type']))) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold tabular"><?= $money((float) $s['total_amount']) ?></div>
                                <?php if ((float) $s['credit_amount'] > 0): ?><span class="status status-warning">Credit</span><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($branches): ?>
    <div class="dash-section-label">Branches of this business · <?= esc($range['label']) ?></div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Branch comparison</span>
            <?php if ($canReports): ?><a class="small" href="<?= esc(site_url('reports/branches') . '?' . http_build_query(['from' => $range['from'], 'to' => $range['to']])) ?>">Branch Overview report</a><?php endif; ?>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Branch</th><th style="min-width:9rem">Sales</th><th class="text-end">Sales (Rs.)</th><th class="text-end">Gas stock KG</th><th class="text-end">Customer OS</th><th>Cash</th></tr></thead>
                <tbody>
                <?php foreach ($branches as $b): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($b['name']) ?><?= (int) $b['id'] === $currentLocationId ? ' <span class="small text-muted fw-normal">(this branch)</span>' : '' ?></td>
                        <td><div class="dash-mini-bar" role="img" aria-label="<?= esc($b['name']) ?> share of sales"><span style="width:<?= round(100 * (float) $b['sales_total'] / $maxBranchSales, 1) ?>%"></span></div></td>
                        <td class="text-end"><?= number_format((float) $b['sales_total'], 2) ?><div class="small text-muted"><?= (int) $b['sale_count'] ?> sale(s)</div></td>
                        <td class="text-end"><?= $qty((float) $b['gas_stock_kg']) ?></td>
                        <td class="text-end"><?= number_format((float) $b['customer_os'], 2) ?></td>
                        <td><?= $b['cash_open'] ? '<span class="status status-success">Open</span>' : '<span class="status">Closed</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Select any summary tile to see the transactions or cylinders behind it. Security deposits and cylinder returns are not counted as sales.</p>

    <div class="d-none">
        <div id="detail-gas">
            <div class="detail-intro"><i class="bi bi-fuel-pump"></i><div><strong><?= $qty((float) $availableGasKg) ?> KG</strong><span>available gas in filled cylinders</span></div></div>
            <?php if (!$gasTypes): ?><div class="empty-state"><i class="bi bi-fuel-pump"></i>No filled cylinders are currently available.</div><?php endif; ?>
            <?php foreach ($gasTypes as $type): ?>
                <details class="dashboard-drill-card" data-detail-type="gas">
                    <summary><strong><?= esc($type['code'] . ' — ' . $type['name']) ?></strong><span class="ms-auto"><?= $qty((float) $type['gas_kg']) ?> KG · <?= number_format((int) $type['cylinder_count']) ?> cylinders</span></summary>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th class="text-end">Capacity</th><th class="text-end">Gas KG</th><th>Status</th><th>Updated</th></tr></thead><tbody>
                    <?php foreach ($type['rows'] as $row): $gasKg = (float) $row['gas_weight_kg']; $capacityKg = (float) $row['capacity_kg']; $partial = $gasKg < $capacityKg - 0.00001; ?>
                        <tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td class="text-end"><?= $qty($capacityKg) ?></td><td class="text-end fw-semibold"><?= $qty($gasKg) ?></td><td><span class="status <?= $partial ? 'status-info' : 'status-success' ?>"><?= $partial ? 'Partially Filled' : 'Filled' ?></span></td><td><?= esc($row['updated_at']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                </details>
            <?php endforeach; ?>
        </div>

        <div id="detail-empty">
            <div class="detail-intro"><i class="bi bi-circle"></i><div><strong><?= number_format((int) $emptyCylinderCount) ?> cylinders</strong><span>empty physical cylinders in the shop</span></div></div>
            <?php if (!$emptyTypes): ?><div class="empty-state"><i class="bi bi-circle"></i>No empty cylinders are currently in the shop.</div><?php endif; ?>
            <?php foreach ($emptyTypes as $type): ?>
                <details class="dashboard-drill-card"><summary><strong><?= esc($type['code'] . ' — ' . $type['name']) ?></strong><span class="ms-auto"><?= number_format((int) $type['count']) ?> cylinders</span></summary>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th class="text-end">Capacity</th><th>Status</th><th>Updated</th></tr></thead><tbody>
                    <?php foreach ($type['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td class="text-end"><?= $qty((float) $row['capacity_kg']) ?></td><td><span class="status status-warning">Empty</span></td><td><?= esc($row['updated_at']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </details>
            <?php endforeach; ?>
        </div>

        <div id="detail-issued">
            <div class="detail-intro"><i class="bi bi-person-badge"></i><div><strong><?= number_format((int) $issuedCylinderCount) ?> cylinders</strong><span>issued to customers against <?= $money((float) $issuedDepositTotal) ?> deposit</span></div></div>
            <?php if (!$issuedCustomers): ?><div class="empty-state"><i class="bi bi-person-badge"></i>No cylinders are currently issued to customers.</div><?php endif; ?>
            <?php foreach ($issuedCustomers as $customer): ?>
                <details class="dashboard-drill-card"><summary><strong><?= esc($customer['name']) ?></strong><span class="small text-muted"><?= esc($customer['code']) ?></span><span class="ms-auto"><?= number_format((int) $customer['count']) ?> cylinders · <?= $money((float) $customer['deposit']) ?></span></summary>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cylinder</th><th>Type</th><th>Condition</th><th class="text-end">Gas KG</th><th>Issued</th><th class="text-end">Deposit</th></tr></thead><tbody>
                    <?php foreach ($customer['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= esc($row['type_code'] . ' — ' . $row['type_name']) ?></td><td><?= esc(ucfirst((string) $row['issued_condition'])) ?></td><td class="text-end"><?= $qty((float) $row['gas_weight_kg']) ?></td><td><?= esc($row['issued_at']) ?></td><td class="text-end"><?= $money((float) $row['deposit_amount']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </details>
            <?php endforeach; ?>
        </div>

        <div id="detail-sales">
            <div class="detail-kpi-grid">
                <div><span>Total</span><strong><?= $money((float) $saleSummary['total']) ?></strong></div><div><span>Cash</span><strong><?= $money((float) $saleSummary['cash']) ?></strong></div><div><span>Online</span><strong><?= $money((float) $saleSummary['online']) ?></strong></div><div><span>Cheque</span><strong><?= $money((float) $saleSummary['cheque']) ?></strong></div><div><span>Credit</span><strong><?= $money((float) $saleSummary['credit']) ?></strong></div>
            </div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Sale No.</th><th>Date / Time</th><th>Customer</th><th>Type</th><th class="text-end">Total</th><th class="text-end">Cash</th><th class="text-end">Online</th><th class="text-end">Credit</th></tr></thead><tbody>
            <?php foreach ($salesRows as $row): ?><tr><td class="fw-semibold"><?= esc($row['sale_no']) ?></td><td><?= esc($row['transaction_at']) ?></td><td><?= esc($row['customer_name'] ?: 'Walk-in / Cash') ?></td><td><?= esc(ucwords(str_replace('_', ' ', $row['transaction_type']))) ?></td><td class="text-end fw-semibold"><?= $money((float) $row['total_amount']) ?></td><td class="text-end"><?= $money((float) $row['cash_amount']) ?></td><td class="text-end"><?= $money((float) $row['online_amount']) ?></td><td class="text-end"><?= $money((float) $row['credit_amount']) ?></td></tr><?php endforeach; ?>
            <?php if (!$salesRows): ?><tr><td colspan="8" class="text-center text-muted py-4">No sales found for the selected date range.</td></tr><?php endif; ?></tbody></table></div>
        </div>

        <div id="detail-cash">
            <div class="detail-kpi-grid"><div><span>Cash In</span><strong><?= $money((float) $cashIn) ?></strong></div><div><span>Cash Out</span><strong><?= $money((float) $cashOut) ?></strong></div><div><span>Net Counter Movement</span><strong><?= $money((float) $cashIn - (float) $cashOut) ?></strong></div></div>
            <?php if (!$cashRegisters): ?><div class="empty-state"><i class="bi bi-cash-stack"></i>No cash movements found for the selected date range.</div><?php endif; ?>
            <?php foreach ($cashRegisters as $register): ?><details class="dashboard-drill-card"><summary><strong><?= esc($register['code'] . ' — ' . $register['name']) ?></strong><span class="ms-auto">In <?= $money((float) $register['in']) ?> · Out <?= $money((float) $register['out']) ?></span></summary>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date / Time</th><th>Movement</th><th>Direction</th><th class="text-end">Amount</th><th>Reference</th></tr></thead><tbody>
                <?php foreach ($register['rows'] as $row): ?><tr><td><?= esc($row['transaction_at']) ?></td><td><?= esc(ucwords(str_replace('_', ' ', $row['transaction_type']))) ?></td><td><span class="status <?= $row['direction'] === 'in' ? 'status-success' : 'status-danger' ?>"><?= $row['direction'] === 'in' ? 'In' : 'Out' ?></span></td><td class="text-end fw-semibold"><?= $money((float) $row['amount']) ?></td><td><?= esc(($row['reference_type'] ?? '') . ' ' . ($row['reference_id'] ?? '')) ?></td></tr><?php endforeach; ?></tbody></table></div>
            </details><?php endforeach; ?>
        </div>

        <div id="detail-receivable">
            <div class="detail-intro"><i class="bi bi-person-lines-fill"></i><div><strong><?= $money((float) $receivableTotal) ?></strong><span>current outstanding customer OS as of today</span></div></div>
            <?php if (!$receivables): ?><div class="empty-state"><i class="bi bi-check2-circle"></i>No customer receivables are currently outstanding.</div><?php endif; ?>
            <?php foreach ($receivables as $customer): ?><details class="dashboard-drill-card"><summary><strong><?= esc($customer['name']) ?></strong><span class="small text-muted"><?= esc($customer['code']) ?></span><span class="ms-auto"><?= $money((float) $customer['balance']) ?> due</span></summary>
                <div class="p-3"><div class="row g-2 small mb-2"><div class="col-md-4">Opening: <strong><?= $money((float) $customer['opening_balance']) ?></strong></div><div class="col-md-4">Credit sales: <strong><?= $money((float) $customer['credit_sales']) ?></strong></div><div class="col-md-4">Receipts: <strong><?= $money((float) $customer['receipts']) ?></strong></div></div>
                <?php $ledger = $receivableLedger[$customer['id']] ?? []; ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Reference</th><th class="text-end">Credit</th><th class="text-end">Receipt</th></tr></thead><tbody>
                <?php foreach ($ledger as $row): ?><tr><td><?= esc($row['transaction_at']) ?></td><td><?= esc($row['sale_no'] ?? $row['receipt_no'] ?? '') ?></td><td class="text-end"><?= $money((float) $row['credit_amount']) ?></td><td class="text-end"><?= $money((float) $row['receipt_amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
            </details><?php endforeach; ?>
        </div>

        <div id="detail-expenses">
            <div class="detail-kpi-grid"><div><span>Total Expenses</span><strong><?= $money((float) $expenseTotal) ?></strong></div><div><span>Entries</span><strong><?= number_format(count($expenseRows)) ?></strong></div></div>
            <div class="row g-2 mb-3"><?php foreach ($expenseCategories as $cat): ?><div class="col-md-4"><div class="expense-category"><span><?= esc($cat['name']) ?></span><strong><?= $money((float) $cat['amount']) ?></strong><small><?= number_format((int) $cat['count']) ?> entries</small></div></div><?php endforeach; ?></div>
            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Expense No.</th><th>Date</th><th>Category</th><th>Mode</th><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>
            <?php foreach ($expenseRows as $row): ?><tr><td class="fw-semibold"><?= esc($row['expense_no']) ?></td><td><?= esc($row['expense_at']) ?></td><td><?= esc($row['category_name'] ?? 'Other') ?></td><td><span class="status"><?= esc(ucfirst($row['payment_mode'])) ?></span></td><td><?= esc($row['description'] ?? '') ?></td><td class="text-end fw-semibold"><?= $money((float) $row['amount']) ?></td></tr><?php endforeach; ?>
            <?php if (!$expenseRows): ?><tr><td colspan="6" class="text-center text-muted py-4">No expenses found for the selected date range.</td></tr><?php endif; ?></tbody></table></div>
        </div>
    </div>

    <div class="modal fade" id="dashboardDetailModal" tabindex="-1" aria-labelledby="dashboardDetailTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="dashboardDetailTitle">Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="dashboardDetailBody"></div>
        </div></div>
    </div>
</div>

<div class="tab-pane fade <?= $stockTab !== '' ? 'show active' : '' ?>" id="stock-pane" role="tabpanel" aria-labelledby="stock-tab">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Current Stock Details</h2>
            <div class="text-muted small">Live physical cylinder position in this branch.</div>
        </div>
        <?php if ($canReports): ?><a href="<?= site_url('reports/inventory-detail') ?>" class="btn btn-sm btn-outline-secondary">Inventory Detail Report</a><?php endif; ?>
    </div>

    <div class="row g-3 mb-3">
        <?php
        $stockCards = [
            'filled' => ['title' => 'Filled Cylinders', 'icon' => 'bi-fuel-pump', 'value' => number_format($stock['filled']['count']), 'sub' => $qty((float) $stock['filled']['gas_kg']) . ' KG gas'],
            'empty' => ['title' => 'Empty Cylinders', 'icon' => 'bi-circle', 'value' => number_format($stock['empty']['count']), 'sub' => 'Empty physical cylinders'],
            'issued' => ['title' => 'Issued Temporarily Against Deposit', 'icon' => 'bi-person-badge', 'value' => number_format($stock['issued']['count']), 'sub' => $money((float) $stock['issued']['deposit']) . ' deposit held'],
        ];
        foreach ($stockCards as $key => $card): $active = $displayStockTab === $key; ?>
        <div class="col-md-4">
            <a href="<?= esc($dashUrl(['from' => $range['from'], 'to' => $range['to'], 'stock_tab' => $key])) ?>" class="text-decoration-none stock-type-card d-block h-100 <?= $active ? 'is-active' : '' ?>" <?= $active ? 'aria-current="true"' : '' ?>>
                <div class="kpi">
                    <div class="kpi-label"><?= esc($card['title']) ?> <i class="bi <?= esc($card['icon']) ?>"></i></div>
                    <div class="kpi-value"><?= esc($card['value']) ?></div>
                    <div class="kpi-hint"><?= esc($card['sub']) ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= esc($stock[$displayStockTab]['title']) ?></span>
            <span class="badge text-bg-light"><?= number_format((int) $stock[$displayStockTab]['count']) ?> record(s)</span>
        </div>
        <?php if (!$stock[$displayStockTab]['rows']): ?>
            <div class="empty-state"><i class="bi bi-inbox"></i>No <?= esc(strtolower($stock[$displayStockTab]['title'])) ?> found in the current physical stock.</div>
        <?php elseif ($displayStockTab === 'filled'): ?>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 datatable">
                    <thead><tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th class="text-end">Capacity KG</th><th class="text-end">Current Gas KG</th><th>Fill Status</th><th>Last Updated</th></tr></thead>
                    <tbody>
                    <?php foreach ($stock['filled']['rows'] as $i => $row): $isPartial = (float) $row['gas_weight_kg'] < (float) $row['capacity_kg']; ?>
                        <tr><td><?= $i + 1 ?></td><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= esc($row['type_code'] . ' — ' . $row['type_name']) ?></td><td class="text-end"><?= $qty((float) $row['capacity_kg']) ?></td><td class="text-end fw-semibold"><?= $qty((float) $row['gas_weight_kg']) ?></td><td><span class="status <?= $isPartial ? 'status-info' : 'status-success' ?>"><?= $isPartial ? 'Partially Filled' : 'Full' ?></span></td><td><?= esc($row['updated_at']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($displayStockTab === 'empty'): ?>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 datatable">
                    <thead><tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th class="text-end">Capacity KG</th><th>Last Updated</th></tr></thead>
                    <tbody>
                    <?php foreach ($stock['empty']['rows'] as $i => $row): ?>
                        <tr><td><?= $i + 1 ?></td><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= esc($row['type_code'] . ' — ' . $row['type_name']) ?></td><td class="text-end"><?= $qty((float) $row['capacity_kg']) ?></td><td><?= esc($row['updated_at']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 datatable">
                    <thead><tr><th>#</th><th>Physical Cylinder</th><th>Type</th><th class="text-end">Capacity KG</th><th>Customer</th><th>Phone</th><th class="text-end">Deposit</th><th>Issued At</th></tr></thead>
                    <tbody>
                    <?php foreach ($stock['issued']['rows'] as $i => $row): ?>
                        <tr><td><?= $i + 1 ?></td><td class="fw-semibold"><?= esc($row['unit_code']) ?></td><td><?= esc($row['type_code'] . ' — ' . $row['type_name']) ?></td><td class="text-end"><?= $qty((float) $row['capacity_kg']) ?></td><td><strong><?= esc($row['customer_name']) ?></strong><div class="small text-muted"><?= esc($row['customer_code'] ?? '') ?></div></td><td><?= esc($row['customer_phone'] ?? '') ?></td><td class="text-end fw-semibold"><?= $money((float) $row['deposit_amount']) ?></td><td><?= esc($row['issued_at']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/chartjs/chart.umd.min.js') ?>"></script>
<script>
(function () {
    // Date filter toggle label
    const filter = document.getElementById('dashboardDateFilter');
    const toggle = document.getElementById('dashboardFilterToggle');
    if (filter && toggle) {
        filter.addEventListener('show.bs.collapse', () => { toggle.querySelector('span').textContent = 'Hide Date Filter'; });
        filter.addEventListener('hide.bs.collapse', () => { toggle.querySelector('span').textContent = 'Show Date Filter'; });
    }

    // Drill-down modal: copy the matching hidden detail block into the modal.
    const modal = document.getElementById('dashboardDetailModal');
    if (modal) {
        const body = document.getElementById('dashboardDetailBody');
        const title = document.getElementById('dashboardDetailTitle');
        const titles = {
            gas: 'Available Gas KG — Cylinder Type & Individual Cylinders',
            empty: 'Empty Gas Cylinders in Shop — Type & Individual Cylinders',
            issued: 'Issued Cylinders — Customers & Cylinder Details',
            sales: 'Sales — Payment & Transaction Details',
            cash: 'Cash Counter — Register In / Out Movements',
            receivable: 'Credit Sales / Accounts Receivable — Customer OS',
            expenses: 'Expenses — Category & Transaction Details'
        };
        modal.addEventListener('show.bs.modal', (e) => {
            const target = e.relatedTarget ? e.relatedTarget.getAttribute('data-detail-target') : null;
            const source = target ? document.getElementById(target) : null;
            if (!source) return;
            title.textContent = titles[target.replace('detail-', '')] || 'Details';
            body.innerHTML = source.innerHTML;
        });
    }

    // Daily sales chart (single series: one validated hue, no legend; the card title names it).
    const canvas = document.getElementById('salesTrendChart');
    if (!canvas || !window.Chart) return;
    const days = <?= json_encode(array_map(static fn ($d) => ['day' => $d['day'], 'total' => round($d['total'], 2)], $trend['days'])) ?>;
    const money = new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const compact = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 });
    const label = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
    let chart = null;

    function draw() {
        const css = getComputedStyle(document.documentElement);
        const token = (name) => css.getPropertyValue(name).trim();
        if (chart) chart.destroy();
        chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: days.map(d => label(d.day)),
                datasets: [{
                    data: days.map(d => d.total),
                    backgroundColor: token('--lpg-viz-1'),
                    hoverBackgroundColor: token('--lpg-viz-1'),
                    borderRadius: { topLeft: 4, topRight: 4 },
                    borderSkipped: 'bottom',
                    maxBarThickness: 28,
                    categoryPercentage: 0.8,
                    barPercentage: 0.9
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 300 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: token('--lpg-surface'),
                        titleColor: token('--lpg-text'),
                        bodyColor: token('--lpg-text-2'),
                        borderColor: token('--lpg-border-strong'),
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            title: (items) => new Date(days[items[0].dataIndex].day + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }),
                            label: (item) => 'Sales: Rs. ' + money.format(item.parsed.y)
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { color: token('--lpg-border') }, ticks: { color: token('--lpg-muted'), maxRotation: 0, autoSkip: true, maxTicksLimit: 10, font: { family: token('--lpg-font') } } },
                    y: { beginAtZero: true, grid: { color: token('--lpg-viz-grid') }, border: { display: false }, ticks: { color: token('--lpg-muted'), maxTicksLimit: 5, callback: (v) => 'Rs ' + compact.format(v), font: { family: token('--lpg-font') } } }
                }
            }
        });
    }
    draw();
    // Redraw with the other theme's colours when the user switches light/dark.
    new MutationObserver(draw).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
})();
</script>
<?= $this->endSection() ?>
