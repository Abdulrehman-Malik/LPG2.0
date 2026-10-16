<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$money = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
$isRange = $from !== $to;
$period = $isRange ? date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to)) : date('d M Y', strtotime($from));
$today = date('Y-m-d');
$presets = ['Today' => [$today, $today], 'Yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))], 'Last 7 days' => [date('Y-m-d', strtotime('-6 days')), $today], 'This month' => [date('Y-m-01'), $today]];
$paymentTotal = array_sum(array_map(static fn ($p) => (float) $p['amount'], $payments));
$modeLabel = static fn ($m) => ucfirst((string) $m);
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Sales, purchases and deposits for <strong><?= esc($period) ?></strong>. Security deposits are a refundable liability and are not counted as sales.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= site_url('reports/branches') ?>"><i class="bi bi-diagram-3 me-1"></i>Branch Overview</a>
        <a class="btn btn-outline-secondary" href="<?= site_url('reports/inventory-detail') ?>"><i class="bi bi-clipboard-data me-1"></i>Inventory Detail</a>
        <a class="btn btn-outline-secondary" href="<?= site_url('reports/custody') ?>"><i class="bi bi-shield-check me-1"></i>Custody &amp; Deposits</a>
    </div>
</div>

<form method="get" class="card mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label" for="repFrom">From</label><input type="date" id="repFrom" name="from" class="form-control" value="<?= esc($from) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="repTo">To</label><input type="date" id="repTo" name="to" class="form-control" value="<?= esc($to) ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Run report</button></div>
        <div class="col-md-4 d-flex flex-wrap gap-2">
            <?php foreach ($presets as $label => [$pf, $pt]): ?>
                <a class="btn btn-sm btn-outline-secondary <?= $from === $pf && $to === $pt ? 'active' : '' ?>" href="<?= esc(site_url('reports') . '?' . http_build_query(['from' => $pf, 'to' => $pt])) ?>"><?= esc($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="kpi is-success"><div class="kpi-label">Sales <i class="bi bi-cart-check"></i></div><div class="kpi-value"><?= $money($sales['total'] ?? 0) ?></div><div class="kpi-hint"><?= (int) ($sales['count'] ?? 0) ?> posted sale(s) · credit <?= $money($sales['credit'] ?? 0) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi"><div class="kpi-label">Purchases <i class="bi bi-bag-check"></i></div><div class="kpi-value"><?= $money($purchases['total'] ?? 0) ?></div><div class="kpi-hint"><?= (int) ($purchases['count'] ?? 0) ?> posted · on credit <?= $money($purchases['credit'] ?? 0) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi is-warning"><div class="kpi-label">Deposits held now <i class="bi bi-shield-check"></i></div><div class="kpi-value"><?= $money($depositBalance) ?></div><div class="kpi-hint">In period: received <?= $money($deposits['held'] ?? 0) ?> · refunded <?= $money($deposits['refunded'] ?? 0) ?></div></div></div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi"><div class="kpi-label">Sale payments by mode <i class="bi bi-wallet2"></i></div>
            <?php if (!$payments): ?><div class="kpi-hint mt-2">No payments in this period.</div><?php endif; ?>
            <?php foreach ($payments as $p): $share = $paymentTotal > 0 ? 100 * (float) $p['amount'] / $paymentTotal : 0; ?>
                <div class="d-flex justify-content-between small mt-2"><span><?= esc($modeLabel($p['payment_mode'])) ?></span><strong class="tabular"><?= $money($p['amount']) ?></strong></div>
                <div class="progress" style="height:.35rem" role="img" aria-label="<?= esc($modeLabel($p['payment_mode'])) ?> <?= round($share) ?>%"><div class="progress-bar" style="width:<?= round($share, 1) ?>%"></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span>Sales detail</span><span class="small text-muted"><?= count($saleRows) ?> sale(s)</span></div>
    <?php if (empty($saleRows)): ?>
        <div class="empty-state"><i class="bi bi-receipt"></i><div class="empty-state-title">No posted sales in this period</div>Choose another date range.</div>
    <?php else: ?>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 datatable">
            <thead><tr><th>Sale No.</th><th>Date / Time</th><th>Customer</th><th>Type</th><th class="text-end">Net amount</th><th class="text-end">Received</th><th class="text-end">Credit / OS</th><th>User</th></tr></thead>
            <tbody>
            <?php foreach ($saleRows as $s): $received = max(0, (float) $s['total_amount'] - (float) $s['credit_amount']); ?>
                <tr><td class="fw-semibold"><?= esc($s['sale_no']) ?></td><td class="text-nowrap"><?= esc($s['transaction_at']) ?></td><td><?= esc($s['customer_name'] ?? 'Walk-in / Cash') ?></td><td><?= esc(ucwords(str_replace('_', ' ', $s['transaction_type']))) ?></td><td class="text-end"><?= number_format((float) $s['total_amount'], 2) ?></td><td class="text-end"><?= number_format($received, 2) ?></td><td class="text-end"><?= number_format((float) $s['credit_amount'], 2) ?></td><td><?= esc($s['created_by_name'] ?? '-') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="4"><strong>Total</strong></td><td class="text-end fw-semibold"><?= number_format((float) ($sales['total'] ?? 0), 2) ?></td><td class="text-end fw-semibold"><?= number_format(max(0, (float) ($sales['total'] ?? 0) - (float) ($sales['credit'] ?? 0)), 2) ?></td><td class="text-end fw-semibold"><?= number_format((float) ($sales['credit'] ?? 0), 2) ?></td><td></td></tr></tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
