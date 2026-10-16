<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$t = $report['totals'];
$rows = $report['branches'];
$modes = $report['payment_modes'];
$money = static fn($v) => number_format((float) $v, 2);
$kg = static fn($v) => number_format((float) $v, 2);
$maxSales = max(1, ...array_map(static fn($b) => (float) $b['sales_total'], $rows ?: [['sales_total' => 0]]));
$collected = array_sum(array_map(static fn($m, $v) => $m === 'credit' ? 0 : $v, array_keys($t['payments']), $t['payments'])) + $t['customer_receipts'];
$query = static fn(array $extra = []) => http_build_query(array_merge([
    'business_id' => $businessId, 'from' => $from, 'to' => $to, 'branch_ids' => $selected,
], $extra));
$modeLabel = static fn(string $m) => ucfirst($m);
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">Branch Overview</h4>
        <p class="text-muted mb-0"><?= esc($businesses[$businessId] ?? '') ?> · <?= esc(date('d-M-Y', strtotime($from))) ?> to <?= esc(date('d-M-Y', strtotime($to))) ?> · <?= count($rows) ?> branch(es)</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= site_url('reports/branches?' . $query(['export' => 'csv'])) ?>"><i class="bi bi-download me-1"></i>Export CSV</a>
</div>

<form method="get" class="card mb-3">
    <div class="card-body row g-3 align-items-end">
        <?php if (count($businesses) > 1): ?>
        <div class="col-md-3">
            <label class="form-label" for="business_id">Business</label>
            <select class="form-select" id="business_id" name="business_id" onchange="this.form.submit()">
                <?php foreach ($businesses as $id => $name): ?>
                    <option value="<?= (int) $id ?>" <?= (int) $id === $businessId ? 'selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
            <input type="hidden" name="business_id" value="<?= (int) $businessId ?>">
        <?php endif; ?>
        <div class="col-sm-6 col-md-2">
            <label class="form-label" for="from">From</label>
            <input type="date" class="form-control" id="from" name="from" value="<?= esc($from) ?>">
        </div>
        <div class="col-sm-6 col-md-2">
            <label class="form-label" for="to">To</label>
            <input type="date" class="form-control" id="to" name="to" value="<?= esc($to) ?>">
        </div>
        <div class="col-md">
            <div class="form-label">Branches</div>
            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($businessShops as $shop): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="branch_ids[]" value="<?= (int) $shop['id'] ?>" id="branch-<?= (int) $shop['id'] ?>" <?= in_array((int) $shop['id'], $selected, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="branch-<?= (int) $shop['id'] ?>"><?= esc($shop['name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Sales', 'Rs. ' . $money($t['sales_total']), $t['sale_count'] . ' sale(s) · ' . $kg($t['gas_kg_sold']) . ' KG gas', 'bi-cart-check'],
        ['Collected', 'Rs. ' . $money($collected), 'Cash, cheque and online sale payments plus customer receipts', 'bi-wallet2'],
        ['Credit given', 'Rs. ' . $money($t['credit_created']), 'Unpaid part of sales in this period', 'bi-hourglass-split'],
        ['Expenses', 'Rs. ' . $money($t['expenses']), 'Purchases Rs. ' . $money($t['purchases_total']), 'bi-receipt'],
        ['Customer OS now', 'Rs. ' . $money($t['customer_os']), $t['customers_with_os'] . ' customer(s) owe money', 'bi-people'],
        ['Gas stock now', $kg($t['gas_stock_kg']) . ' KG', $t['filled_cylinders'] . ' filled · ' . $t['empty_cylinders'] . ' empty · ' . $t['outgoing_in_transit'] . ' in transit', 'bi-fuel-pump'],
    ] as [$label, $value, $hint, $icon]): ?>
        <div class="col-sm-6 col-xl-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between text-muted small"><span><?= esc($label) ?></span><i class="bi <?= $icon ?>"></i></div>
                    <div class="fs-5 fw-semibold mt-1"><?= esc($value) ?></div>
                    <div class="small text-muted"><?= esc($hint) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
    <div class="alert alert-info">No branches selected.</div>
<?php else: ?>
<div class="card mb-3">
    <div class="card-header fw-semibold">Trading by branch</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr>
                <th>Branch</th><th style="min-width:9rem">Sales</th><th class="text-end">Sales</th><th class="text-end">Gas KG</th>
                <?php foreach ($modes as $m): ?><th class="text-end"><?= esc($modeLabel($m)) ?></th><?php endforeach; ?>
                <th class="text-end">Receipts</th><th class="text-end">Discounts</th><th class="text-end">Voided</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $b): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($b['name']) ?></td>
                    <td><div class="progress" style="height:.5rem" role="img" aria-label="<?= esc($b['name']) ?> share of sales"><div class="progress-bar" style="width:<?= round(100 * (float) $b['sales_total'] / $maxSales, 1) ?>%"></div></div></td>
                    <td class="text-end"><?= $money($b['sales_total']) ?><div class="small text-muted"><?= (int) $b['sale_count'] ?> sale(s)</div></td>
                    <td class="text-end"><?= $kg($b['gas_kg_sold']) ?></td>
                    <?php foreach ($modes as $m): ?><td class="text-end"><?= $money($b['payments'][$m] ?? 0) ?></td><?php endforeach; ?>
                    <td class="text-end"><?= $money($b['customer_receipts']) ?></td>
                    <td class="text-end"><?= $money($b['discounts']) ?></td>
                    <td class="text-end"><?= (int) $b['voided_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="fw-semibold"><tr>
                <td>Total</td><td></td><td class="text-end"><?= $money($t['sales_total']) ?></td><td class="text-end"><?= $kg($t['gas_kg_sold']) ?></td>
                <?php foreach ($modes as $m): ?><td class="text-end"><?= $money($t['payments'][$m] ?? 0) ?></td><?php endforeach; ?>
                <td class="text-end"><?= $money($t['customer_receipts']) ?></td><td class="text-end"><?= $money($t['discounts']) ?></td><td class="text-end"><?= (int) $t['voided_count'] ?></td>
            </tr></tfoot>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Purchases, expenses and deposits</div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Branch</th><th class="text-end">Purchases</th><th class="text-end">Supplier paid</th><th class="text-end">Expenses</th><th class="text-end">Deposits in</th><th class="text-end">Refunded</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $b): ?>
                        <tr><td class="fw-semibold"><?= esc($b['name']) ?></td><td class="text-end"><?= $money($b['purchases_total']) ?></td><td class="text-end"><?= $money($b['supplier_payments']) ?></td><td class="text-end"><?= $money($b['expenses']) ?></td><td class="text-end"><?= $money($b['deposits_received']) ?></td><td class="text-end"><?= $money($b['deposits_refunded']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="fw-semibold"><tr><td>Total</td><td class="text-end"><?= $money($t['purchases_total']) ?></td><td class="text-end"><?= $money($t['supplier_payments']) ?></td><td class="text-end"><?= $money($t['expenses']) ?></td><td class="text-end"><?= $money($t['deposits_received']) ?></td><td class="text-end"><?= $money($t['deposits_refunded']) ?></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Stock and balances now</div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Branch</th><th class="text-end">Gas KG</th><th class="text-end">Filled</th><th class="text-end">Empty</th><th class="text-end">Custody</th><th class="text-end">In transit</th><th class="text-end">Customer OS</th><th>Cash</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $b): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($b['name']) ?></td>
                            <td class="text-end"><?= $kg($b['gas_stock_kg']) ?></td>
                            <td class="text-end"><?= (int) $b['filled_cylinders'] ?></td>
                            <td class="text-end"><?= (int) $b['empty_cylinders'] ?></td>
                            <td class="text-end"><?= (int) $b['custody_cylinders'] ?></td>
                            <td class="text-end"><?= (int) $b['outgoing_in_transit'] ?> out<?= $b['incoming_in_transit'] ? ' · <span class="text-danger fw-semibold">' . (int) $b['incoming_in_transit'] . ' to receive</span>' : '' ?></td>
                            <td class="text-end"><?= $money($b['customer_os']) ?></td>
                            <td><?= $b['cash_open'] ? '<span class="badge text-bg-success">Open</span> <span class="small">Rs. ' . $money($b['cash_expected']) . '</span>' : '<span class="badge text-bg-light border">Closed</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="fw-semibold"><tr><td>Total</td><td class="text-end"><?= $kg($t['gas_stock_kg']) ?></td><td class="text-end"><?= (int) $t['filled_cylinders'] ?></td><td class="text-end"><?= (int) $t['empty_cylinders'] ?></td><td class="text-end"><?= (int) $t['custody_cylinders'] ?></td><td class="text-end"><?= (int) $t['outgoing_in_transit'] ?></td><td class="text-end"><?= $money($t['customer_os']) ?></td><td class="small">Rs. <?= $money($t['cash_expected']) ?></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<p class="small text-muted mt-3 mb-0">Period figures come from posted transactions between the selected dates; voided sales are excluded from totals. Security deposits are shown separately and are not sales. "Now" figures show current stock and balances regardless of the dates.</p>
<?php endif; ?>
<?= $this->endSection() ?>
