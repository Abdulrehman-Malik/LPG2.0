<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$money = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
$due = (float) $customer['credit_due'];
$label = static fn (string $t): string => ucwords(str_replace('_', ' ', $t));

// Statement: the events that make up the balance (opening + credit + return-gas ledger − receipts),
// oldest first with a running balance. POS sale payments are not listed here: they already reduced
// the sale's credit amount.
$events = [];
foreach ($sales as $s) {
    if (($s['status'] ?? '') !== 'posted') {
        continue;
    }
    if ((float) $s['credit_amount'] != 0.0) {
        $events[] = ['at' => $s['transaction_at'], 'ref' => $s['sale_no'], 'what' => $label($s['transaction_type']) . ' — unpaid amount', 'debit' => (float) $s['credit_amount'], 'credit' => 0.0];
    }
    if ((float) ($s['return_gas_ledger_amount'] ?? 0) != 0.0) {
        $v = (float) $s['return_gas_ledger_amount'];
        $events[] = ['at' => $s['transaction_at'], 'ref' => $s['sale_no'], 'what' => 'Return gas adjustment', 'debit' => max(0, $v), 'credit' => max(0, -$v)];
    }
}
foreach ($receipts as $r) {
    if (($r['status'] ?? '') === 'posted' && in_array($r['receipt_type'] ?? '', ['Customer Receipt', 'POS OS Settlement'], true)) {
        $events[] = ['at' => $r['receipt_at'], 'ref' => $r['receipt_no'], 'what' => ($r['receipt_type'] === 'POS OS Settlement' ? 'OS settled at POS' : 'Payment received') . ' (' . strtoupper((string) $r['payment_mode']) . ')', 'debit' => 0.0, 'credit' => (float) $r['amount']];
    }
}
usort($events, static fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));
$running = (float) $customer['opening_balance'];
foreach ($events as &$e) {
    $running += $e['debit'] - $e['credit'];
    $e['balance'] = $running;
}
unset($e);
$statementMatches = abs($running - $due) < 0.01;
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= esc($customer['name']) ?></h1>
        <p class="page-subtitle">Customer ledger<?= !empty($customer['code']) ? ' · ' . esc($customer['code']) : '' ?><?= !empty($customer['phone']) ? ' · ' . esc($customer['phone']) : '' ?><?= !empty($customer['city']) ? ' · ' . esc($customer['city']) : '' ?></p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        <a class="btn btn-outline-secondary" href="<?= site_url('customers') ?>"><i class="bi bi-arrow-left me-1"></i>Customers</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="kpi <?= $due > 0.005 ? 'is-danger' : 'is-success' ?>"><div class="kpi-label">Current balance <i class="bi bi-wallet2"></i></div><div class="kpi-value"><?= $money(abs($due)) ?></div><div class="kpi-hint"><?= $due > 0.005 ? 'Customer owes this amount' : ($due < -0.005 ? 'Advance held for the customer' : 'Settled') ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi"><div class="kpi-label">Opening balance <i class="bi bi-flag"></i></div><div class="kpi-value"><?= $money($customer['opening_balance']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi is-info"><div class="kpi-label">Total purchased <i class="bi bi-cart-check"></i></div><div class="kpi-value"><?= $money($customer['total_purchased']) ?></div><div class="kpi-hint"><?= number_format((float) ($customer['total_gas_kg'] ?? 0), 2) ?> KG gas</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi is-warning"><div class="kpi-label">Security deposit held <i class="bi bi-shield-check"></i></div><div class="kpi-value"><?= $money($customer['security_deposit_held'] ?? 0) ?></div><div class="kpi-hint">Refundable; not part of sales</div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#customerStatement" type="button" role="tab"><i class="bi bi-journal-text me-1"></i>Statement</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerSales" type="button" role="tab"><i class="bi bi-receipt me-1"></i>Sales <span class="badge text-bg-light"><?= count($sales) ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerReceipts" type="button" role="tab"><i class="bi bi-cash-coin me-1"></i>Receipts <span class="badge text-bg-light"><?= count($receipts) ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerDeposits" type="button" role="tab"><i class="bi bi-shield-check me-1"></i>Deposits <span class="badge text-bg-light"><?= count($deposits) ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerCustody" type="button" role="tab"><i class="bi bi-box-seam me-1"></i>Cylinders held <span class="badge text-bg-light"><?= count($custody) ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerOverview" type="button" role="tab"><i class="bi bi-person-vcard me-1"></i>Details</button></li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="customerStatement" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="text-end">Debit (owes)</th><th class="text-end">Credit (paid)</th><th class="text-end">Balance</th></tr></thead>
                        <tbody>
                            <tr class="table-light"><td colspan="5"><strong>Opening balance</strong></td><td class="text-end fw-semibold"><?= number_format((float) $customer['opening_balance'], 2) ?></td></tr>
                            <?php foreach ($events as $e): ?>
                            <tr>
                                <td class="text-nowrap"><?= esc(date('d M Y, h:i A', strtotime($e['at']))) ?></td>
                                <td class="text-nowrap"><?= esc($e['ref']) ?></td>
                                <td><?= esc($e['what']) ?></td>
                                <td class="text-end"><?= $e['debit'] ? number_format($e['debit'], 2) : '' ?></td>
                                <td class="text-end"><?= $e['credit'] ? number_format($e['credit'], 2) : '' ?></td>
                                <td class="text-end fw-semibold"><?= number_format($e['balance'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td colspan="5"><strong>Closing balance</strong></td><td class="text-end fw-bold"><?= number_format($running, 2) ?></td></tr></tfoot>
                    </table>
                </div>
                <?php if (!$events): ?><p class="small text-muted mt-2 mb-0">No credit sales or payments yet.</p><?php endif; ?>
                <?php if (!$statementMatches): ?><div class="alert alert-warning small mt-3 mb-0">The statement total (<?= number_format($running, 2) ?>) differs from the current balance (<?= number_format($due, 2) ?>). Please report this to support.</div><?php endif; ?>
            </div>

            <div class="tab-pane fade" id="customerSales" role="tabpanel">
                <div class="table-responsive"><table class="table table-sm table-hover align-middle datatable"><thead><tr><th>Date</th><th>No.</th><th>Type</th><th class="text-end">Total</th><th class="text-end">Unpaid (credit)</th><th class="text-end">Gas return</th><th class="text-end">Deposit</th><th class="text-end">Refund</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($sales as $r): ?><tr><td class="text-nowrap"><?= esc($r['transaction_at']) ?></td><td class="fw-semibold"><?= esc($r['sale_no']) ?></td><td><?= esc($label($r['transaction_type'])) ?></td><td class="text-end"><?= number_format((float) $r['total_amount'], 2) ?></td><td class="text-end"><?= number_format((float) $r['credit_amount'], 2) ?></td><td class="text-end"><?= number_format((float) ($r['return_gas_ledger_amount'] ?? 0), 2) ?></td><td class="text-end"><?= number_format((float) $r['security_deposit_amount'], 2) ?></td><td class="text-end"><?= number_format((float) $r['security_deposit_refund_amount'], 2) ?></td><td><?= $r['status'] === 'posted' ? '<span class="status status-success">Posted</span>' : '<span class="status status-danger">' . esc(ucfirst($r['status'])) . '</span>' ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>

            <div class="tab-pane fade" id="customerReceipts" role="tabpanel">
                <div class="table-responsive"><table class="table table-sm table-hover align-middle datatable"><thead><tr><th>Date</th><th>Receipt / Transaction</th><th>Type</th><th class="text-end">Amount</th><th>Mode</th><th>Source</th><th>Status</th><th>Reference</th><th>Details</th></tr></thead><tbody>
                <?php foreach ($receipts as $r): ?><tr><td class="text-nowrap"><?= esc($r['receipt_at']) ?></td><td class="fw-semibold"><?= esc($r['receipt_no']) ?></td><td><?= esc($r['receipt_type'] ?? 'Customer Receipt') ?></td><td class="text-end"><?= number_format((float) $r['amount'], 2) ?></td><td><?= esc(strtoupper($r['payment_mode'])) ?></td><td><?= esc($r['source'] ?? 'Unknown') ?></td><td><span class="status <?= ($r['status'] ?? '') === 'posted' ? 'status-success' : '' ?>"><?= esc(strtoupper($r['status'] ?? 'UNKNOWN')) ?></span></td><td><?= esc($r['reference_no'] ?? '') ?></td><td><?= esc($r['details'] ?? '') ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>

            <div class="tab-pane fade" id="customerDeposits" role="tabpanel">
                <div class="table-responsive"><table class="table table-sm table-hover align-middle datatable"><thead><tr><th>Date</th><th>Entry</th><th class="text-end">Amount</th><th>Sale</th><th>Custody</th><th>Note</th></tr></thead><tbody>
                <?php foreach ($deposits as $r): ?><tr><td class="text-nowrap"><?= esc($r['transaction_at']) ?></td><td><?= $r['entry_type'] === 'hold' ? '<span class="status status-success">Deposit held</span>' : '<span class="status status-danger">Deposit refunded</span>' ?></td><td class="text-end"><?= number_format((float) $r['amount'], 2) ?></td><td><?= esc((string) ($r['sale_id'] ?? '')) ?></td><td><?= esc((string) ($r['custody_id'] ?? '')) ?></td><td><?= esc($r['notes'] ?? '') ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>

            <div class="tab-pane fade" id="customerCustody" role="tabpanel">
                <?php if (!$custody): ?><div class="empty-state"><i class="bi bi-box-seam"></i>The customer holds no company cylinders.</div><?php else: ?>
                <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Cylinder</th><th>Type</th><th class="text-end">Gas KG</th><th class="text-end">Deposit held</th><th>Issued</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($custody as $r): ?><tr><td class="fw-semibold"><?= esc($r['unit_code']) ?></td><td><?= esc($r['cylinder_code'] . ' — ' . $r['cylinder_name']) ?></td><td class="text-end"><?= number_format((float) $r['gas_weight_kg'], 2) ?></td><td class="text-end"><?= number_format((float) $r['deposit_amount'], 2) ?></td><td class="text-nowrap"><?= esc($r['issued_at']) ?></td><td><span class="status status-warning">With customer</span></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="customerOverview" role="tabpanel">
                <div class="row g-3">
                    <?php foreach (['Code' => $customer['code'] ?? '', 'Phone' => $customer['phone'] ?? '', 'City' => $customer['city'] ?? '', 'Vehicle' => $customer['vehicle_no'] ?? '', 'Address' => $customer['address'] ?? '', 'Credit sale' => !empty($customer['allow_credit_sale']) ? 'Allowed' : 'Cash only', 'Credit limit' => (float) $customer['credit_limit'] > 0 ? $money($customer['credit_limit']) : 'No limit', 'Status' => (int) $customer['is_active'] ? 'Active' : 'Inactive'] as $k => $v): ?>
                        <div class="col-sm-6 col-lg-3"><div class="small text-muted"><?= esc($k) ?></div><div class="fw-semibold"><?= esc($v !== '' ? $v : '—') ?></div></div>
                    <?php endforeach; ?>
                </div>
                <a class="btn btn-outline-secondary btn-sm mt-3" href="<?= site_url('customers?edit=' . (int) $customer['id']) ?>"><i class="bi bi-pencil me-1"></i>Edit customer</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
