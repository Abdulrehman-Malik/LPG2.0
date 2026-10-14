<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h4 class="mb-1">Physical Cylinders</h4><div class="text-muted small"><?=esc($type['code'].' — '.$type['name'])?> · Capacity <?=number_format((float)$type['capacity_kg'], 2)?> KG</div></div>
  <a class="btn btn-outline-secondary" href="<?=site_url('inventory')?>"><i class="bi bi-arrow-left me-1"></i>Inventory</a>
</div>
<form method="get" class="row g-2 mb-3">
  <div class="col-md-5"><input name="q" class="form-control" value="<?=esc($search)?>" placeholder="Search cylinder code"></div>
  <div class="col-md-3">
    <select name="status" class="form-select">
      <option value="">All Statuses</option><option value="filled" <?=$status==='filled'?'selected':''?>>Filled</option><option value="partial" <?=$status==='partial'?'selected':''?>>Partially Filled</option><option value="empty" <?=$status==='empty'?'selected':''?>>Empty</option>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
  <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?=site_url('inventory/cylinders/'.$type['id'])?>">Clear</a></div>
</form>
<div class="card shadow-sm">
<div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0">
<thead><tr><th>Cylinder Code</th><th>Capacity</th><th>Current Gas</th><th>Status</th><th>Location</th><th>Last Activity</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($units as $u):
  $gas=(float)$u['gas_weight_kg'];$cap=(float)$u['capacity_kg'];
  $label=$gas<=0?'Empty':($gas<$cap?'Partially Filled':'Filled');
  $badge=$label==='Filled'?'text-bg-success':($label==='Partially Filled'?'text-bg-warning':'text-bg-secondary');
?>
<tr>
<td><strong><?=esc($u['unit_code'])?></strong></td><td><?=number_format($cap, 2)?> KG</td><td class="fw-semibold"><?=number_format($gas, 2)?> KG</td>
<td><span class="badge <?=$badge?>"><?=esc($label)?></span></td><td>Current Branch</td>
<td><?=esc($u['last_activity_at']??'—')?></td>
<td><a class="btn btn-sm btn-primary" href="<?=site_url('inventory/cylinder/'.$u['id'])?>">Details</a></td>
</tr>
<?php endforeach;?>
<?php if(!$units):?><tr><td colspan="7" class="text-center text-muted py-4">No physical cylinders match the selected filters.</td></tr><?php endif;?>
</tbody></table></div></div>
<?= $this->endSection() ?>