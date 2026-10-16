<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$money = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
$due = (float) $supplier['credit_due'];
$pooled = !empty($pooled);
$at = static fn (array $r): string => $pooled && !empty($r['branch_name']) ? ' · ' . $r['branch_name'] : '';
// Statement: opening + unpaid part of each posted purchase − account payments (purchase-time
// payments already reduced the purchase's unpaid amount).
$events = [];
foreach ($purchases as $p) {
    if ($p['status'] === 'posted' && (float) $p['credit_amount'] != 0.0) {
        $events[] = ['at' => $p['transaction_at'], 'ref' => $p['purchase_no'], 'what' => 'Purchase — unpaid amount' . $at($p), 'debit' => (float) $p['credit_amount'], 'credit' => 0.0];
    }
}
foreach ($payments as $p) {
    if ($p['status'] === 'posted' && $p['source'] === 'Supplier Account Payment') {
        $events[] = ['at' => $p['payment_at'], 'ref' => $p['payment_no'], 'what' => 'Payment to supplier (' . strtoupper((string) $p['payment_mode']) . ')' . $at($p), 'debit' => 0.0, 'credit' => (float) $p['amount']];
    }
}
usort($events, static fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));
$running = (float) $supplier['opening_balance'];
foreach ($events as &$e) {
    $running += $e['debit'] - $e['credit'];
    $e['balance'] = $running;
}
unset($e);
$shared = $supplier['location_id'] === null;
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= esc($supplier['name']) ?></h1>
        <p class="page-subtitle"><?= $pooled ? 'Supplier ledger for the whole business (one shared balance)' : 'Supplier ledger for this branch' ?><?= !empty($supplier['code']) ? ' · ' . esc($supplier['code']) : '' ?><?= !empty($supplier['phone']) ? ' · ' . esc($supplier['phone']) : '' ?><?= $shared ? ' · shared with all branches' : '' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= site_url('supplier-payments') ?>"><i class="bi bi-credit-card me-1"></i>Pay supplier</a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        <a class="btn btn-outline-secondary" href="<?= site_url('suppliers') ?>"><i class="bi bi-arrow-left me-1"></i>Suppliers</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="kpi <?= $due > 0.005 ? 'is-danger' : 'is-success' ?>"><div class="kpi-label">Payable now <i class="bi bi-cash-coin"></i></div><div class="kpi-value"><?= $money(abs($due)) ?></div><div class="kpi-hint"><?= $due > 0.005 ? ($pooled ? 'The business owes the supplier' : 'This branch owes the supplier') : ($due < -0.005 ? 'Advance paid to the supplier' : 'Settled') ?></div></div></div>
    <div class="col-sm-4"><div class="kpi"><div class="kpi-label">Opening balance <i class="bi bi-flag"></i></div><div class="kpi-value"><?= $money($supplier['opening_balance']) ?></div><div class="kpi-hint"><?= $pooled ? 'Openings of all branches' : ($shared ? 'Opening recorded at this branch' : 'Opening at this supplier\'s branch') ?></div></div></div>
    <div class="col-sm-4"><div class="kpi is-info"><div class="kpi-label">Credit limit <i class="bi bi-speedometer"></i></div><div class="kpi-value"><?= (float) $supplier['credit_limit'] > 0 ? $money($supplier['credit_limit']) : 'No limit' ?></div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#supplierStatement" type="button" role="tab"><i class="bi bi-journal-text me-1"></i>Statement</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplierPurchases" type="button" role="tab"><i class="bi bi-bag-check me-1"></i>Purchases <span class="badge text-bg-light"><?= count($purchases) ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplierPayments" type="button" role="tab"><i class="bi bi-cash-coin me-1"></i>Payments <span class="badge text-bg-light"><?= count($payments) ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplierOverview" type="button" role="tab"><i class="bi bi-person-vcard me-1"></i>Details</button></li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="supplierStatement" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="text-end">Owed (+)</th><th class="text-end">Paid (−)</th><th class="text-end">Balance</th></tr></thead>
                        <tbody>
                            <tr class="table-light"><td colspan="5"><strong>Opening balance</strong></td><td class="text-end fw-semibold"><?= number_format((float) $supplier['opening_balance'], 2) ?></td></tr>
                            <?php foreach ($events as $e): ?>
                            <tr><td class="text-nowrap"><?= esc(date('d M Y, h:i A', strtotime($e['at']))) ?></td><td class="text-nowrap"><?= esc($e['ref']) ?></td><td><?= esc($e['what']) ?></td><td class="text-end"><?= $e['debit'] ? number_format($e['debit'], 2) : '' ?></td><td class="text-end"><?= $e['credit'] ? number_format($e['credit'], 2) : '' ?></td><td class="text-end fw-semibold"><?= number_format($e['balance'], 2) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td colspan="5"><strong>Closing balance</strong></td><td class="text-end fw-bold"><?= number_format($running, 2) ?></td></tr></tfoot>
                    </table>
                </div>
                <?php if (abs($running - $due) >= 0.01): ?><div class="alert alert-warning small mt-3 mb-0">The statement total (<?= number_format($running, 2) ?>) differs from the payable (<?= number_format($due, 2) ?>). Please report this to support.</div><?php endif; ?>
            </div>
            <div class="tab-pane fade" id="supplierPurchases" role="tabpanel">
                <div class="table-responsive"><table class="table table-sm table-hover align-middle datatable"><thead><tr><th>Date</th><th>Purchase</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Added to payable</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($purchases as $r): ?><tr><td class="text-nowrap"><?= esc($r['transaction_at']) ?></td><td class="fw-semibold"><?= esc($r['purchase_no']) ?><?php if ($pooled): ?><div class="small text-muted fw-normal"><?= esc($r['branch_name'] ?? '') ?></div><?php endif; ?></td><td class="text-end"><?= number_format((float) $r['total_amount'], 2) ?></td><td class="text-end"><?= number_format(max(0, (float) $r['paid_amount']), 2) ?></td><td class="text-end"><?= number_format(max(0, (float) $r['credit_amount']), 2) ?></td><td><?= $r['status'] === 'posted' ? '<span class="status status-success">Posted</span>' : '<span class="status status-danger">' . esc(ucfirst($r['status'])) . '</span>' ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>
            <div class="tab-pane fade" id="supplierPayments" role="tabpanel">
                <div class="table-responsive"><table class="table table-sm table-hover align-middle datatable"><thead><tr><th>Date</th><th>Payment</th><th class="text-end">Amount</th><th>Method</th><th>Applied to</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($payments as $r): ?><tr><td class="text-nowrap"><?= esc($r['payment_at']) ?></td><td class="fw-semibold"><?= esc($r['payment_no']) ?><?php if ($pooled): ?><div class="small text-muted fw-normal"><?= esc($r['branch_name'] ?? '') ?></div><?php endif; ?></td><td class="text-end"><?= number_format((float) $r['amount'], 2) ?></td><td><?= esc(ucfirst($r['payment_mode'])) ?></td><td><?= esc($r['source']) ?><?= !empty($r['purchase_no']) ? ' · ' . esc($r['purchase_no']) : '' ?></td><td><?= $r['status'] === 'posted' ? '<span class="status status-success">Posted</span>' : '<span class="status status-danger">' . esc(ucfirst($r['status'])) . '</span>' ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>
            <div class="tab-pane fade" id="supplierOverview" role="tabpanel">
                <div class="row g-3">
                    <?php foreach (['Code' => $supplier['code'] ?? '', 'Phone' => $supplier['phone'] ?? '', 'City' => $supplier['city'] ?? '', 'Address' => $supplier['address'] ?? '', 'Availability' => $shared ? 'All branches' : 'This branch only', 'Status' => (int) $supplier['is_active'] ? 'Active' : 'Inactive'] as $k => $v): ?>
                        <div class="col-sm-6 col-lg-4"><div class="small text-muted"><?= esc($k) ?></div><div class="fw-semibold"><?= esc($v !== '' ? $v : '—') ?></div></div>
                    <?php endforeach; ?>
                </div>
                <a class="btn btn-outline-secondary btn-sm mt-3" href="<?= site_url('suppliers?edit=' . (int) $supplier['id']) ?>"><i class="bi bi-pencil me-1"></i>Edit supplier</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
