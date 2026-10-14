<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h4 class="mb-1"><?=esc($customer['name'])?></h4><div class="text-muted small"><?=esc($customer['phone']??'')?><?=!empty($customer['city'])?' · '.esc($customer['city']):''?></div></div>
  <a class="btn btn-outline-secondary" href="<?=site_url('customers')?>"><i class="bi bi-arrow-left me-1"></i>Back to Customers</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Opening Balance</div><h5 class="mb-0">Rs. <?=number_format((float)$customer['opening_balance'],2)?></h5></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Total Purchased</div><h5 class="mb-0">Rs. <?=number_format((float)$customer['total_purchased'],2)?></h5></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Current Balance</div><h5 class="mb-0 <?=((float)$customer['credit_due'])<0?'text-danger':'text-success'?>">Rs. <?=number_format((float)$customer['credit_due'],2)?></h5><div class="small text-muted"><?=((float)$customer['credit_due'])<0?'Customer credit / advance':'Amount due'?></div></div></div></div>
  <div class="col-md-3"><div class="card border-warning h-100"><div class="card-body"><div class="small text-muted">Security Deposits Held</div><h5 class="mb-0">Rs. <?=number_format((float)($customer['security_deposit_held']??0),2)?></h5><div class="small text-muted">Refundable liability</div></div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white">
    <ul class="nav nav-tabs card-header-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#customerOverview" type="button"><i class="bi bi-person-vcard me-1"></i>Overview</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerSales" type="button"><i class="bi bi-receipt me-1"></i>Sales / Transactions <span class="badge text-bg-secondary"><?=count($sales)?></span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerReceipts" type="button"><i class="bi bi-cash-coin me-1"></i>Receipts <span class="badge text-bg-secondary"><?=count($receipts)?></span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerDeposits" type="button"><i class="bi bi-shield-check me-1"></i>Security Deposits <span class="badge text-bg-secondary"><?=count($deposits)?></span></button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerCustody" type="button"><i class="bi bi-box-seam me-1"></i>Cylinder Custody <span class="badge text-bg-secondary"><?=count($custody)?></span></button></li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <div class="tab-pane fade show active" id="customerOverview">
        <div class="row g-4">
          <div class="col-lg-6"><div class="border rounded p-4 h-100"><h6 class="fw-semibold">Account Summary</h6><div class="d-flex justify-content-between py-2 border-bottom"><span>Opening Balance</span><strong>Rs. <?=number_format((float)$customer['opening_balance'],2)?></strong></div><div class="d-flex justify-content-between py-2 border-bottom"><span>Current Balance</span><strong>Rs. <?=number_format((float)$customer['credit_due'],2)?></strong></div><div class="d-flex justify-content-between py-2"><span>Security Deposit Held</span><strong>Rs. <?=number_format((float)($customer['security_deposit_held']??0),2)?></strong></div></div></div>
          <div class="col-lg-6"><div class="border rounded p-4 h-100"><h6 class="fw-semibold">Customer Details</h6><div class="small text-muted mb-1">Code</div><div class="mb-2"><?=esc($customer['code']??'—')?></div><div class="small text-muted mb-1">Phone</div><div class="mb-2"><?=esc($customer['phone']??'—')?></div><div class="small text-muted mb-1">Vehicle</div><div class="mb-2"><?=esc($customer['vehicle_no']??'—')?></div><div class="small text-muted mb-1">Address</div><div><?=esc($customer['address']??'—')?></div></div></div>
        </div>
      </div>

      <div class="tab-pane fade" id="customerSales">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>No.</th><th>Type</th><th>Total</th><th>Credit</th><th>Gas Return Credit</th><th>Deposit</th><th>Refund</th><th>Status</th></tr></thead><tbody>
        <?php foreach($sales as $r): ?><tr><td><?=esc($r['transaction_at'])?></td><td><?=esc($r['sale_no'])?></td><td><?=esc(ucwords(str_replace('_',' ',$r['transaction_type'])))?></td><td>Rs. <?=number_format((float)$r['total_amount'],2)?></td><td>Rs. <?=number_format((float)$r['credit_amount'],2)?></td><td>Rs. <?=number_format((float)($r['return_gas_ledger_amount']??0),2)?></td><td>Rs. <?=number_format((float)$r['security_deposit_amount'],2)?></td><td>Rs. <?=number_format((float)$r['security_deposit_refund_amount'],2)?></td><td><?=esc(ucfirst($r['status']))?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="customerReceipts">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>Receipt / Transaction</th><th>Type</th><th>Amount</th><th>Mode</th><th>Source</th><th>Status</th><th>Reference</th><th>Details</th></tr></thead><tbody>
        <?php foreach($receipts as $r): ?><tr><td><?=esc($r['receipt_at'])?></td><td><?=esc($r['receipt_no'])?></td><td><?=esc($r['receipt_type']??'Customer Receipt')?></td><td>Rs. <?=number_format((float)$r['amount'],2)?></td><td><?=esc(strtoupper($r['payment_mode']))?></td><td><?=esc($r['source']??'Unknown')?></td><td><span class="badge text-bg-<?=($r['status']??'')==='posted'?'success':'secondary'?>"><?=esc(strtoupper($r['status']??'UNKNOWN'))?></span></td><td><?=esc($r['reference_no']??'')?></td><td><?=esc($r['details']??'')?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="customerDeposits">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Date</th><th>Entry</th><th>Amount</th><th>Sale</th><th>Custody</th><th>Note</th></tr></thead><tbody>
        <?php foreach($deposits as $r): ?><tr><td><?=esc($r['transaction_at'])?></td><td><?= $r['entry_type']==='hold'?'<span class="badge text-bg-success">Deposit Held</span>':'<span class="badge text-bg-danger">Deposit Refunded</span>' ?></td><td>Rs. <?=number_format((float)$r['amount'],2)?></td><td><?=esc((string)($r['sale_id']??''))?></td><td><?=esc((string)($r['custody_id']??''))?></td><td><?=esc($r['notes']??'')?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="customerCustody">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle datatable"><thead><tr><th>Cylinder</th><th>Type</th><th>Gas KG</th><th>Deposit Held</th><th>Issued</th><th>Status</th></tr></thead><tbody>
        <?php foreach($custody as $r): ?><tr><td><?=esc($r['unit_code'])?></td><td><?=esc($r['cylinder_code'].' — '.$r['cylinder_name'])?></td><td><?=number_format((float)$r['gas_weight_kg'],2)?></td><td>Rs. <?=number_format((float)$r['deposit_amount'],2)?></td><td><?=esc($r['issued_at'])?></td><td><span class="badge text-bg-warning">Issued / Held</span></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>