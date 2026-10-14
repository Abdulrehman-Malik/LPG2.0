<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h4 class="mb-1">Cylinder Details</h4><div class="text-muted small"><?=esc($unit['unit_code'])?></div></div>
  <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?=site_url('inventory/cylinders/'.$unit['cylinder_type_id'])?>"><i class="bi bi-arrow-left me-1"></i>Cylinders</a><a class="btn btn-outline-secondary" href="<?=site_url('inventory')?>">Inventory</a></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Cylinder Code</div><div class="fw-bold fs-5"><?=esc($unit['unit_code'])?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Type</div><div class="fw-bold"><?=esc($unit['cylinder_code'])?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Capacity</div><div class="fw-bold"><?=number_format((float)$unit['capacity_kg'], 2)?> KG</div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Current Gas</div><div class="fw-bold"><?=number_format((float)$unit['gas_weight_kg'], 2)?> KG</div></div></div></div>
  <?php $gas=(float)$unit['gas_weight_kg'];$cap=(float)$unit['capacity_kg'];$status=$gas<=0?'Empty':($gas<$cap?'Partially Filled':'Filled'); ?>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Status</div><div class="fw-bold fs-5"><?=esc($status)?></div></div></div></div>
</div>
<div class="card shadow-sm mb-4"><div class="card-body">
<div class="row g-3">
<div class="col-md-3"><span class="text-muted small d-block">Location</span><strong>Current Branch</strong></div>
<div class="col-md-3"><span class="text-muted small d-block">Ownership / Custody</span><strong><?=$unit['custody_customer_name']?'Customer Custody — '.esc($unit['custody_customer_name']):'Company Stock'?></strong></div>
<div class="col-md-3"><span class="text-muted small d-block">Created</span><strong><?=esc($unit['created_at']??'—')?></strong></div>
<div class="col-md-3"><span class="text-muted small d-block">Source</span><strong><?=esc(ucwords(str_replace('_',' ',(string)($unit['source_type']??''))))?><?=!empty($unit['source_id'])?' #'.esc($unit['source_id']):''?></strong></div>
</div></div></div>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-4"><label class="form-label small">From Date</label><input type="date" name="from_date" class="form-control" value="<?=esc($fromDate)?>" <?=$allHistory?'disabled':''?>></div>
  <div class="col-md-4"><label class="form-label small">To Date</label><input type="date" name="to_date" class="form-control" value="<?=esc($toDate)?>" <?=$allHistory?'disabled':''?>></div>
  <div class="col-md-2 align-self-end"><button class="btn btn-primary w-100" <?=$allHistory?'disabled':''?>>View</button></div>
  <div class="col-md-2 align-self-end">
    <a class="btn <?=$allHistory?'btn-primary':'btn-outline-secondary'?> w-100" href="<?=site_url('inventory/cylinder/'.$unit['id'])?>?all_history=1">All History</a>
  </div>
</form>
<div class="card shadow-sm"><div class="card-header bg-white"><h5 class="mb-0">Cylinder History & Running Gas Ledger</h5><div class="small text-muted"><?= $allHistory ? 'Complete lifecycle history.' : 'Current date history. Use All History for the complete lifecycle.' ?> Gas balance is calculated as previous balance + gas in − gas out.</div></div>
<div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0">
<thead><tr><th>Date/Time</th><th>Activity</th><th>Reference</th><th>Gas In</th><th>Gas Out</th><th>Running Gas</th><th>Status</th><th>User</th><th>Notes</th></tr></thead>
<tbody>
<?php foreach($movements as $m):
 $in=$m['inventory_type']==='gas_kg'&&$m['direction']==='in'?(float)$m['quantity']:0;
 $out=$m['inventory_type']==='gas_kg'&&$m['direction']==='out'?(float)$m['quantity']:0;
 $badge=$m['status_after']==='Filled'?'text-bg-success':($m['status_after']==='Partially Filled'?'text-bg-warning':'text-bg-secondary');
?>
<tr><td><?=esc($m['movement_at'])?></td><td><?=esc(ucwords(str_replace('_',' ',(string)$m['source_type'])))?><?= $m['inventory_type']==='gas_kg'?' — Gas':' — '.esc(ucwords(str_replace('_',' ',$m['inventory_type']))) ?></td><td><?=esc($m['source_reference'])?></td><td><?=number_format($in, 2)?></td><td><?=number_format($out, 2)?></td><td class="fw-semibold"><?=number_format((float)$m['running_gas'], 2)?> KG</td><td><span class="badge <?=$badge?>"><?=esc($m['status_after'])?></span></td><td><?=esc($m['full_name']??'')?></td><td><?=esc($m['notes']??'')?></td></tr>
<?php endforeach;?>
<?php if(!$movements):?><tr><td colspan="9" class="text-center text-muted py-4">No inventory history found for this cylinder.</td></tr><?php endif;?>
</tbody></table></div></div>
<?= $this->endSection() ?>