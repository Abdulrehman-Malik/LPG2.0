<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h4 class="mb-1"><?=esc($supplier['name'])?></h4><div class="text-muted small"><?=esc($supplier['phone']??'')?><?=!empty($supplier['city'])?' · '.esc($supplier['city']):''?></div></div>
  <a class="btn btn-outline-secondary" href="<?=site_url('suppliers')?>"><i class="bi bi-arrow-left me-1"></i>Back to Suppliers</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Opening Balance</div><h5 class="mb-0">Rs. <?=number_format((float)$supplier['opening_balance'],2)?></h5></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Credit Limit</div><h5 class="mb-0">Rs. <?=number_format((float)$supplier['credit_limit'],2)?></h5></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Current Balance Due</div><h5 class="mb-0">Rs. <?=number_format((float)$supplier['credit_due'],2)?></h5></div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white">
    <ul class="nav nav-tabs card-header-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#supplierOverview" type="button"><i class="bi bi-person-vcard me-1"></i>Overview</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplierPurchases" type="button"><i class="bi bi-bag-check me-1"></i>Purchases <span class="badge text-bg-secondary"><?=count($purchases)?></span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplierPayments" type="button"><i class="bi bi-cash-coin me-1"></i>Payments <span class="badge text-bg-secondary"><?=count($payments)?></span></button></li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <div class="tab-pane fade show active" id="supplierOverview">
        <div class="row g-4">
          <div class="col-lg-6"><div class="border rounded p-4 h-100"><h6 class="fw-semibold">Account Summary</h6><div class="d-flex justify-content-between py-2 border-bottom"><span>Opening Balance</span><strong>Rs. <?=number_format((float)$supplier['opening_balance'],2)?></strong></div><div class="d-flex justify-content-between py-2 border-bottom"><span>Credit Limit</span><strong>Rs. <?=number_format((float)$supplier['credit_limit'],2)?></strong></div><div class="d-flex justify-content-between py-2"><span>Current Balance Due</span><strong>Rs. <?=number_format((float)$supplier['credit_due'],2)?></strong></div></div></div>
          <div class="col-lg-6"><div class="border rounded p-4 h-100"><h6 class="fw-semibold">Supplier Details</h6><div class="small text-muted mb-1">Code</div><div class="mb-2"><?=esc($supplier['code']??'—')?></div><div class="small text-muted mb-1">Phone</div><div class="mb-2"><?=esc($supplier['phone']??'—')?></div><div class="small text-muted mb-1">City</div><div class="mb-2"><?=esc($supplier['city']??'—')?></div><div class="small text-muted mb-1">Address</div><div><?=esc($supplier['address']??'—')?></div></div></div>
        </div>
      </div>
      <div class="tab-pane fade" id="supplierPurchases">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>Purchase</th><th>Total</th><th>Paid</th><th>Added to Payable</th><th>Status</th></tr></thead><tbody>
        <?php foreach($purchases as $r): ?><tr><td><?=esc($r['transaction_at'])?></td><td><?=esc($r['purchase_no'])?></td><td>Rs. <?=number_format((float)$r['total_amount'],2)?></td><td>Rs. <?=number_format(max(0,(float)$r['paid_amount']),2)?></td><td>Rs. <?=number_format(max(0,(float)$r['credit_amount']),2)?></td><td><?=esc(ucfirst($r['status']))?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>
      <div class="tab-pane fade" id="supplierPayments">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>Payment</th><th>Amount</th><th>Method</th><th>Applied To</th><th>Status</th></tr></thead><tbody>
        <?php foreach($payments as $r): ?><tr><td><?=esc($r['payment_at'])?></td><td><?=esc($r['payment_no'])?></td><td>Rs. <?=number_format((float)$r['amount'],2)?></td><td><?=esc(ucfirst($r['payment_mode']))?></td><td><?=esc($r['source'])?><?=!empty($r['purchase_no'])?' · '.esc($r['purchase_no']):''?></td><td><?=esc(ucfirst($r['status']))?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>