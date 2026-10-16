<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<style>
.purchase-page .card-header { background:var(--lpg-surface); }
.purchase-page .stock-table th,
.purchase-page .stock-table td { vertical-align:middle; }
.purchase-page .stock-table th { white-space:nowrap; font-size:.86rem; font-weight:600; color:var(--lpg-muted); }
.purchase-page .stock-table .form-control,
.purchase-page .stock-table .form-select { min-width:0; }
.purchase-page .stock-table .lineTotal {
    display:flex;
    align-items:center;
    justify-content:flex-end;
    min-height:38px;
    font-weight:600;
    white-space:nowrap;
}
.purchase-page .summary-card { border:1px solid var(--lpg-border); border-radius:.5rem; }
.purchase-page .summary-row { display:flex; justify-content:space-between; gap:1rem; padding:.6rem 0; }
.purchase-page .summary-row + .summary-row { border-top:1px solid var(--lpg-border); }
.purchase-page .summary-total { font-size:1.05rem; }
.purchase-page .summary-balance {
    margin-top:.35rem;
    padding-top:.75rem;
    border-top:2px solid var(--lpg-border-strong);
    font-size:1.15rem;
}
.purchase-page .purchase-tabs { margin-bottom: 1rem; }
.purchase-page .history-table th,
.purchase-page .history-table td { vertical-align: middle; }
.purchase-page .history-table th { white-space: nowrap; font-size: .86rem; font-weight: 600; color: var(--lpg-muted); }
.purchase-page .payment-row {
    border:1px solid var(--lpg-border);
    border-radius:.5rem;
    padding:.75rem;
    background:var(--lpg-surface-2);
}
.purchase-page .history-details-table th {
    white-space:nowrap;
    font-size:.82rem;
    color:var(--lpg-muted);
}
.purchase-page .history-details-table td { vertical-align:middle; }
.purchase-page .purchase-detail-summary {
    background:var(--lpg-surface-2);
    border:1px solid var(--lpg-border);
    border-radius:.5rem;
}
@media (max-width: 991.98px) {
    .purchase-page .stock-table { min-width:920px; }
}
</style>

<div class="purchase-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Purchases</h1>
            <p class="page-subtitle">Receive stock from a supplier. Anything not paid now is added to the supplier's payable.</p>
        </div>
        <div class="page-actions"><a class="btn btn-outline-secondary" href="<?= site_url('supplier-payments') ?>"><i class="bi bi-cash-coin me-1"></i>Pay a supplier</a></div>
    </div>
    <ul class="nav nav-tabs purchase-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($activeTab ?? 'new') === 'new' ? 'active' : '' ?>" href="#new-purchase" data-bs-toggle="tab" role="tab" aria-controls="new-purchase" aria-selected="<?= ($activeTab ?? 'new') === 'new' ? 'true' : 'false' ?>">
                <i class="bi bi-plus-circle me-1"></i>New Purchase
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($activeTab ?? 'new') === 'history' ? 'active' : '' ?>" href="#purchase-history" data-bs-toggle="tab" role="tab" aria-controls="purchase-history" aria-selected="<?= ($activeTab ?? 'new') === 'history' ? 'true' : 'false' ?>">
                <i class="bi bi-clock-history me-1"></i>Purchase History
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade <?= ($activeTab ?? 'new') === 'new' ? 'show active' : '' ?>" id="new-purchase" role="tabpanel">
    <form method="post" action="<?= site_url('purchases/save') ?>" id="purchaseForm">
        <?= csrf_field() ?>
        <input type="hidden" name="lines_json" id="lines_json">
        <input type="hidden" name="payments_json" id="payments_json">

        <div class="card shadow-sm mb-3">
            <div class="card-header py-3">
                <div class="fw-semibold">Supplier &amp; Purchase Details</div>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-6">
                        <label class="form-label fw-semibold">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="form-select" required>
                            <option value="">Select supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= esc($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Previous Payable</label>
                        <div class="form-control bg-light fw-semibold text-end" id="previousPayable">Rs. 0.00</div>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Discount</label>
                        <input name="discount_amount" id="discount" class="form-control text-end" type="number" step=".01" min="0" value="0">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <div>
                    <div class="fw-semibold">Stock Received</div>
                    <div class="small text-muted">Add each stock item received from the supplier.</div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="add">
                    <i class="bi bi-plus-lg me-1"></i>Add Stock Line
                </button>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless mb-0 stock-table">
                        <thead class="border-bottom">
                            <tr>
                                <th class="ps-3" style="width:18%">Stock Type</th>
                                <th style="width:18%">Cylinder Type</th>
                                <th class="text-end" style="width:10%">Quantity</th>
                                <th class="text-end" style="width:16%">Actual Gas / Cylinder (KG)</th>
                                <th class="text-end" style="width:15%">Unit Rate</th>
                                <th class="text-end" style="width:18%">Line Amount</th>
                                <th class="text-center pe-3" style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="lines"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header py-3">
                <div class="fw-semibold">Supplier Settlement</div>
                <div class="small text-muted mt-1">
                    Payment is optional. Leave it as “No Payment” for a full credit purchase, or enter the amount paid now.
                </div>
            </div>

            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <?= view('partials/transaction_date') ?>
                        <label class="form-label fw-semibold">Purchase Notes</label>
                        <textarea name="notes" class="form-control" rows="5" placeholder="Optional purchase or supplier notes"></textarea>

                        <div class="alert alert-info mt-3 mb-0">
                            <div class="fw-semibold mb-1">Accounting treatment</div>
                            <div class="small">
                                Stock is received immediately. The unpaid portion becomes supplier account payable.
                                Any payment entered here reduces the supplier balance.
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="summary-card p-3" style="background:var(--lpg-surface-2)">
                            <div class="small text-muted mb-2">Supplier Account Summary</div>

                            <div class="summary-row">
                                <span>Previous Payable</span>
                                <strong>Rs. <span id="summaryPrevious">0.00</span></strong>
                            </div>

                            <div class="summary-row">
                                <span>Current Purchase</span>
                                <strong>Rs. <span id="summaryCurrent">0.00</span></strong>
                            </div>

                            <div class="summary-row summary-total">
                                <span class="fw-semibold">Total Payable</span>
                                <strong>Rs. <span id="summaryTotalPayable">0.00</span></strong>
                            </div>

                            <div class="summary-row align-items-center">
                                <span class="fw-semibold">Payment Type</span>
                                <select id="paymentType" class="form-select form-select-sm" style="max-width:180px" aria-label="Payment type">
                                    <option value="">No Payment</option>
                                    <option value="cash">Cash</option>
                                    <option value="online">Online</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>

                            <div class="summary-row align-items-center">
                                <span class="fw-semibold">Amount Paid</span>
                                <div class="input-group input-group-sm" style="max-width:180px">
                                    <span class="input-group-text">Rs.</span>
                                    <input id="amountPaid" class="form-control text-end" type="number" min="0" step=".01" value="0" placeholder="0.00">
                                </div>
                            </div>

                            <div class="summary-balance d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Balance Payable</span>
                                <strong>Rs. <span id="summaryBalance">0.00</span></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary w-100 mt-4" id="postPurchase" type="submit">
                    <i class="bi bi-check2-circle me-1"></i>Post Purchase
                </button>
            </div>
        </div>
    </form>
        </div>

        <div class="tab-pane fade <?= ($activeTab ?? 'new') === 'history' ? 'show active' : '' ?>" id="purchase-history" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header py-3">
                    <div class="fw-semibold">Purchase History</div>
                    <div class="small text-muted">View purchases posted for the selected date range.</div>
                </div>
                <div class="card-body">
                    <form method="get" action="<?= site_url('purchases') ?>" class="row g-3 align-items-end mb-4">
                        <input type="hidden" name="tab" value="history">
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label fw-semibold">From Date</label>
                            <input type="date" name="from_date" class="form-control" value="<?= esc($fromDate ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label fw-semibold">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="<?= esc($toDate ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>Search History
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($purchaseHistory)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover history-table mb-0">
                                <thead class="border-bottom">
                                    <tr>
                                        <th>Date</th>
                                        <th>Purchase No.</th>
                                        <th>Supplier</th>
                                        <th class="text-end">Subtotal</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Current Credit</th>
                                        <th>Status</th>
                                        <th class="text-center">Details</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchaseHistory as $purchase): ?>
                                        <?php
                                        $status = (string) ($purchase['status'] ?? 'posted');
                                        $statusClass = $status === 'posted' ? 'bg-success' : 'bg-danger';
                                        ?>
                                        <tr>
                                            <td><?= esc(date('d-M-Y h:i A', strtotime($purchase['transaction_at']))) ?></td>
                                            <td class="fw-semibold"><?= esc($purchase['purchase_no']) ?></td>
                                            <td><?= esc($purchase['supplier_name']) ?></td>
                                            <td class="text-end">Rs. <?= number_format((float) $purchase['subtotal'], 2) ?></td>
                                            <td class="text-end">Rs. <?= number_format((float) $purchase['discount_amount'], 2) ?></td>
                                            <td class="text-end fw-semibold">Rs. <?= number_format((float) $purchase['total_amount'], 2) ?></td>
                                            <td class="text-end">Rs. <?= number_format((float) $purchase['amount_paid'], 2) ?></td>
                                            <td class="text-end">Rs. <?= number_format((float) $purchase['credit_amount'], 2) ?></td>
                                            <td><span class="badge <?= $statusClass ?>"><?= esc(ucfirst($status)) ?></span></td>
                                            <td class="text-center">
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#purchaseDetailModal<?= (int) $purchase['id'] ?>">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </button>
                                            </td>
                                            <td class="text-center">
                                                <?php if (($canVoidPurchases ?? false) && $status === 'posted'): ?>
                                                    <form method="post"
                                                        action="<?= site_url('purchases/void/' . (int) $purchase['id']) ?>"
                                                        class="d-inline"
                                                        data-confirm="Void purchase <?= esc($purchase['purchase_no']) ?>? Stock, cash and supplier ledger effects will be reversed.">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="void_reason" value="Voided from Purchase History">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-x-circle me-1"></i>Void
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php foreach ($purchaseHistory as $purchase): ?>
                            <?php
                            $detail = $purchaseDetails[(int) $purchase['id']] ?? ['items' => [], 'payments' => []];
                            $detailStatus = (string) ($purchase['status'] ?? 'posted');
                            $detailStatusClass = $detailStatus === 'posted' ? 'bg-success' : 'bg-danger';
                            ?>
                            <div class="modal fade" id="purchaseDetailModal<?= (int) $purchase['id'] ?>" tabindex="-1" aria-labelledby="purchaseDetailLabel<?= (int) $purchase['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <div>
                                                <h5 class="modal-title" id="purchaseDetailLabel<?= (int) $purchase['id'] ?>">
                                                    Purchase <?= esc($purchase['purchase_no']) ?>
                                                </h5>
                                                <div class="small text-muted">
                                                    <?= esc(date('d-M-Y h:i A', strtotime($purchase['transaction_at']))) ?>
                                                    &nbsp;•&nbsp; <?= esc($purchase['supplier_name']) ?>
                                                </div>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <?php if ($detailStatus === 'voided'): ?>
                                                <div class="alert alert-danger py-2">
                                                    <strong>VOID</strong>
                                                    — <?= esc($purchase['voided_at'] ?? '') ?>
                                                    by <?= esc($purchase['voided_by_name'] ?? '-') ?>
                                                    <br>
                                                    Reason: <?= esc($purchase['void_reason'] ?? '-') ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <div class="purchase-detail-summary p-3 h-100">
                                                        <div class="small text-muted">Supplier</div>
                                                        <div class="fw-semibold"><?= esc($purchase['supplier_name']) ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="purchase-detail-summary p-3 h-100">
                                                        <div class="small text-muted">Purchase Date</div>
                                                        <div class="fw-semibold"><?= esc(date('d-M-Y h:i A', strtotime($purchase['transaction_at']))) ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="purchase-detail-summary p-3 h-100">
                                                        <div class="small text-muted">Status</div>
                                                        <div><span class="badge <?= $detailStatusClass ?>"><?= esc(ucfirst($detailStatus)) ?></span></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <h6 class="fw-semibold mb-2">Stock Received</h6>
                                            <?php if (!empty($detail['items'])): ?>
                                                <div class="table-responsive mb-4">
                                                    <table class="table table-sm table-bordered history-details-table mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Stock Type</th>
                                                                <th>Cylinder Type</th>
                                                                <th class="text-end">Quantity</th>
                                                                <th class="text-end">Actual Gas / Cylinder (KG)</th>
                                                                <th class="text-end">Unit Rate</th>
                                                                <th class="text-end">Line Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($detail['items'] as $item): ?>
                                                                <tr>
                                                                    <td><?= (int) $item['line_no'] ?></td>
                                                                    <td><?= esc(ucwords(str_replace('_', ' ', $item['line_type']))) ?></td>
                                                                    <td><?= esc(trim(($item['cylinder_code'] ?? '') . ' ' . ($item['cylinder_name'] ?? '')) ?: '—') ?></td>
                                                                    <td class="text-end"><?= number_format((float) $item['quantity'], 2) ?></td>
                                                                    <td class="text-end">
                                                                        <?= $item['line_type'] === 'filled_cylinder' ? number_format((float) $item['actual_gas_weight_kg'], 2) : '—' ?>
                                                                    </td>
                                                                    <td class="text-end">Rs. <?= number_format((float) $item['unit_rate'], 2) ?></td>
                                                                    <td class="text-end fw-semibold">Rs. <?= number_format((float) $item['line_total'], 2) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-light border">No purchase line details found.</div>
                                            <?php endif; ?>

                                            <div class="row g-4">
                                                <div class="col-lg-7">
                                                    <h6 class="fw-semibold mb-2">Payments</h6>
                                                    <?php if (!empty($detail['payments'])): ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-bordered history-details-table mb-0">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Date</th>
                                                                        <th>Payment Mode</th>
                                                                        <th>Reference</th>
                                                                        <th class="text-end">Amount</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($detail['payments'] as $payment): ?>
                                                                        <tr>
                                                                            <td><?= esc(date('d-M-Y h:i A', strtotime($payment['payment_at']))) ?></td>
                                                                            <td><?= esc(ucfirst($payment['payment_mode'])) ?></td>
                                                                            <td><?= esc($payment['reference_no'] ?? '—') ?></td>
                                                                            <td class="text-end">Rs. <?= number_format((float) $payment['amount'], 2) ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-light border mb-0">No payment was recorded for this purchase.</div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="col-lg-5">
                                                    <h6 class="fw-semibold mb-2">Purchase Summary</h6>
                                                    <div class="purchase-detail-summary p-3">
                                                        <div class="d-flex justify-content-between py-1">
                                                            <span>Subtotal</span>
                                                            <strong>Rs. <?= number_format((float) $purchase['subtotal'], 2) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between py-1">
                                                            <span>Discount</span>
                                                            <strong>Rs. <?= number_format((float) $purchase['discount_amount'], 2) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between py-1 border-top mt-2 pt-2">
                                                            <span>Total</span>
                                                            <strong>Rs. <?= number_format((float) $purchase['total_amount'], 2) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between py-1">
                                                            <span>Paid</span>
                                                            <strong>Rs. <?= number_format((float) $purchase['amount_paid'], 2) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between py-1">
                                                            <span>Current Credit</span>
                                                            <strong>Rs. <?= number_format((float) $purchase['credit_amount'], 2) ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if (trim((string) ($purchase['notes'] ?? '')) !== ''): ?>
                                                <div class="mt-4">
                                                    <h6 class="fw-semibold mb-2">Notes</h6>
                                                    <div class="purchase-detail-summary p-3"><?= nl2br(esc($purchase['notes'])) ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <?php if (($canVoidPurchases ?? false) && $detailStatus === 'posted'): ?>
                                                <form method="post"
                                                    action="<?= site_url('purchases/void/' . (int) $purchase['id']) ?>"
                                                    class="d-flex align-items-center gap-2"
                                                    data-confirm="Void purchase <?= esc($purchase['purchase_no']) ?>? Stock, cash and supplier ledger effects will be reversed.">
                                                    <?= csrf_field() ?>
                                                    <input type="text" name="void_reason" class="form-control form-control-sm" style="width:240px" value="Voided from Purchase Details" aria-label="Void reason">
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bi bi-x-circle me-1"></i>Void Purchase
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-light border mb-0 text-center">
                            No purchases found between <?= esc(date('d-M-Y', strtotime($fromDate ?? date('Y-m-d')))) ?> and <?= esc(date('d-M-Y', strtotime($toDate ?? date('Y-m-d')))) ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<script>
const canVoidPurchases = <?= json_encode($canVoidPurchases ?? false) ?>;
const types = <?= json_encode($types) ?>;
const supplierBalances = <?= json_encode($supplierBalances ?? []) ?>;

const money = value => Number(value || 0).toFixed(2);

function getPreviousPayable() {
    const supplierId = document.getElementById('supplier_id').value;
    return Math.max(0, Number(supplierBalances[supplierId] || 0));
}

function addLine() {
    const row = document.createElement('tr');
    row.className = 'line border-bottom';
    row.innerHTML = `
        <td class="ps-3">
            <select class="form-select type">
                <option value="filled_cylinder">Filled Cylinder</option>
                <option value="empty_cylinder">Empty Cylinder</option>
            </select>
        </td>
        <td>
            <select class="form-select cyl">
                <option value="">—</option>
                ${types.map(t => `<option value="${t.id}">${t.code} — ${t.name}</option>`).join('')}
            </select>
        </td>
        <td>
            <input class="form-control qty text-end" type="number" min=".001" step=".001" value="1">
        </td>
        <td>
            <input class="form-control actual text-end" type="number" min="0" step=".001" placeholder="Filled cylinders only">
        </td>
        <td>
            <input class="form-control rate text-end" type="number" min="0" step=".01" value="0">
        </td>
        <td>
            <div class="lineTotal">Rs. 0.00</div>
        </td>
        <td class="text-center pe-3">
            <button type="button" class="btn btn-outline-danger btn-sm rm" title="Remove line">
                <i class="bi bi-trash"></i>
            </button>
        </td>`;

    document.getElementById('lines').append(row);
    row.querySelector('.rm').onclick = () => { row.remove(); calculate(); };
    row.querySelectorAll('input,select').forEach(element => element.addEventListener('input', calculate));
    row.querySelectorAll('select').forEach(element => element.addEventListener('change', calculate));
}

function calculate() {
    let current = 0;

    document.querySelectorAll('.line').forEach(row => {
        const amount =
            Number(row.querySelector('.qty').value || 0) *
            Number(row.querySelector('.rate').value || 0);

        row.querySelector('.lineTotal').textContent = 'Rs. ' + money(amount);
        current += amount;
    });

    current = Math.max(0, current - Number(document.getElementById('discount').value || 0));

    const previous = getPreviousPayable();
    const totalPayable = previous + current;

    const paid = Math.max(0, Number(document.getElementById('amountPaid').value || 0));

    const balance = Math.max(0, totalPayable - paid);

    document.getElementById('previousPayable').textContent = 'Rs. ' + money(previous);
    document.getElementById('summaryPrevious').textContent = money(previous);
    document.getElementById('summaryCurrent').textContent = money(current);
    document.getElementById('summaryTotalPayable').textContent = money(totalPayable);
    document.getElementById('summaryBalance').textContent = money(balance);
    document.getElementById('amountPaid').max = money(totalPayable);

    const over = paid > totalPayable + 0.01;
    document.getElementById('amountPaid').classList.toggle('is-invalid', over);
    document.getElementById('postPurchase').disabled = over;
}

document.getElementById('add').onclick = addLine;
document.getElementById('discount').addEventListener('input', calculate);
document.getElementById('supplier_id').addEventListener('change', calculate);
document.getElementById('paymentType').addEventListener('change', function () {
    if (!this.value) document.getElementById('amountPaid').value = '0.00';
    calculate();
});
document.getElementById('amountPaid').addEventListener('input', calculate);

document.getElementById('purchaseForm').onsubmit = function () {
    const lines = [...document.querySelectorAll('.line')].map(row => ({
        line_type: row.querySelector('.type').value,
        cylinder_type_id: row.querySelector('.cyl').value,
        quantity: row.querySelector('.qty').value,
        unit_rate: row.querySelector('.rate').value,
        actual_gas_weight_kg: row.querySelector('.actual').value
    }));

    const paymentType = document.getElementById('paymentType').value;
    const paidAmount = Number(document.getElementById('amountPaid').value || 0);

    if (paidAmount > 0 && !paymentType) {
        alert('Select a payment type for the amount paid.');
        return false;
    }

    const payments = paidAmount > 0 ? [{
        payment_mode: paymentType,
        amount: paidAmount,
        reference_no: ''
    }] : [];

    if (!lines.length) {
        alert('Add at least one stock line.');
        return false;
    }

    const totalPayable = getPreviousPayable() +
        Number(document.getElementById('summaryCurrent').textContent || 0);

    const paid = payments.reduce((sum, payment) => sum + Number(payment.amount || 0), 0);

    if (paid > totalPayable + 0.01) {
        alert('Amount paid cannot exceed the total supplier payable.');
        return false;
    }

    document.getElementById('lines_json').value = JSON.stringify(lines);
    document.getElementById('payments_json').value = JSON.stringify(payments);

    return true;
};

addLine();
calculate();
</script>

<?= $this->endSection() ?>
