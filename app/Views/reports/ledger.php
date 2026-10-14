<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">Customer Ledger — <?=esc($customer['name'])?></h4><div class="text-muted small">Credit / OS ledger</div></div><a class="btn btn-outline-secondary" href="<?=site_url('reports')?>">Back</a></div>
<div class="row g-3 mb-3">
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Opening Balance</div><h5>Rs. <?=number_format((float)$openingBalance,2)?></h5></div></div>
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Current OS</div><h5>Rs. <?=number_format((float)$currentBalance,2)?></h5></div></div>
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Credit Validation</div><h5><?=esc(ucfirst($creditLimitMode))?></h5></div></div>
<div class="col-md-3"><div class="card card-body"><div class="text-muted">Effective Credit Limit</div><h5><?= $creditLimitMode==='none'?'OFF':'Rs. '.number_format((float)$effectiveCreditLimit,2) ?></h5></div></div>
</div>
<div class="card"><div class="card-body table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th class="text-end">OS Debit</th><th class="text-end">Receipt / Settlement</th><th class="text-end">Running OS</th></tr></thead><tbody>
<?php foreach($events as $e): ?><tr><td><?=esc($e['date'])?></td><td><?=esc($e['type'])?></td><td><?=esc($e['reference'])?></td><td class="text-end">Rs. <?=number_format((float)$e['debit'],2)?></td><td class="text-end">Rs. <?=number_format((float)$e['credit'],2)?></td><td class="text-end fw-semibold">Rs. <?=number_format((float)$e['running_balance'],2)?></td></tr><?php endforeach; ?>
<?php if(!$events): ?><tr><td colspan="6" class="text-muted">No credit transactions or receipts found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?= $this->endSection() ?>