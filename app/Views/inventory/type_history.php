<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
<div><h4 class="mb-1">Cylinder Type History</h4><div class="text-muted small"><?=esc($type['code'].' — '.$type['name'])?></div></div>
<a class="btn btn-outline-secondary" href="<?=site_url('inventory')?>"><i class="bi bi-arrow-left me-1"></i>Inventory</a>
</div>
<form method="get" class="row g-2 mb-3">
<div class="col-md-4"><label class="form-label small">From Date</label><input type="date" name="from_date" class="form-control" value="<?=esc($fromDate)?>" <?=$allHistory?'disabled':''?>></div>
<div class="col-md-4"><label class="form-label small">To Date</label><input type="date" name="to_date" class="form-control" value="<?=esc($toDate)?>" <?=$allHistory?'disabled':''?>></div>
<div class="col-md-2 align-self-end"><button class="btn btn-primary w-100" <?=$allHistory?'disabled':''?>>View</button></div>
<div class="col-md-2 align-self-end"><a class="btn <?=$allHistory?'btn-primary':'btn-outline-secondary'?> w-100" href="<?=site_url('inventory/type-history/'.$type['id'])?>?all_history=1">All History</a></div>
</form>
<div class="card shadow-sm"><div class="card-header bg-white"><h5 class="mb-0">Running Inventory Movements</h5><div class="small text-muted">Gas running balance follows physical-cylinder gas movements for this cylinder type.</div></div>
<div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0">
<thead><tr><th>Date/Time</th><th>Activity</th><th>Reference</th><th>Physical Cylinder</th><th>Item</th><th>Direction</th><th>Quantity</th><th>Running Gas KG</th><th>Notes</th></tr></thead>
<tbody>
<?php foreach($movements as $m):
 $item=$m['inventory_type']==='gas_kg'?'Gas KG':($m['inventory_type']==='filled_cylinder'?'Filled Cylinder':'Empty Cylinder');
?>
<tr><td><?=esc($m['movement_at'])?></td><td><?=esc(ucwords(str_replace('_',' ',(string)$m['source_type'])))?></td><td><?=esc($m['source_reference'])?></td><td><?=esc($m['unit_code']??'—')?></td><td><?=esc($item)?></td><td><span class="badge <?=$m['direction']==='out'?'text-bg-danger':'text-bg-success'?>"><?=esc(strtoupper($m['direction']))?></span></td><td><?=number_format((float)$m['quantity'], 2)?></td><td class="fw-semibold"><?=number_format((float)$m['running_gas'], 2)?></td><td><?=esc($m['notes']??'')?></td></tr>
<?php endforeach;?>
<?php if(!$movements):?><tr><td colspan="9" class="text-center text-muted py-4">No movements found for this cylinder type in the selected date range.</td></tr><?php endif;?>
</tbody></table></div></div>
<?= $this->endSection() ?>