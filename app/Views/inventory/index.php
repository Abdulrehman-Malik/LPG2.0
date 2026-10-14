<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1">Inventory</h4>
    <div class="text-muted small">Current branch stock by cylinder type, physical cylinder status and actual gas quantity.</div>
  </div>
  <a class="btn btn-outline-primary" href="<?=site_url('inventory/adjustments')?>"><i class="bi bi-sliders2-vertical me-1"></i>Stock Adjustment & History</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Current Gas</div><div class="fs-4 fw-bold"><?=number_format($totalGas, 2)?> KG</div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Total Cylinders</div><div class="fs-4 fw-bold"><?=number_format($totalCylinders)?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Filled</div><div class="fs-4 fw-bold"><?=number_format($filledCount)?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Partially Filled</div><div class="fs-4 fw-bold"><?=number_format($partialCount)?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Empty</div><div class="fs-4 fw-bold"><?=number_format($emptyCount)?></div></div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <div><h5 class="mb-0">Cylinder Type Stock</h5><div class="small text-muted">Running/current total calculated from physical cylinder records.</div></div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle mb-0">
      <thead><tr>
        <th>Cylinder Type</th><th>Capacity</th><th class="text-end">Filled</th><th class="text-end">Partially Filled</th><th class="text-end">Empty</th>
        <th class="text-end">Total Cylinders</th><th class="text-end">Current Gas KG</th><th class="text-end">Total Capacity KG</th><th>Action</th>
      </tr></thead>
      <tbody>
      <?php foreach($summary as $row):
        $capacity=(float)$row['total_capacity_kg']; $gas=(float)$row['current_gas_kg'];
        $util=$capacity>0?($gas/$capacity)*100:0;
      ?>
        <tr>
          <td><strong><?=esc($row['code'])?></strong><div class="small text-muted"><?=esc($row['name'])?></div></td>
          <td><?=number_format((float)$row['capacity_kg'], 2)?> KG</td>
          <td class="text-end"><?=number_format((int)$row['filled_count'])?></td>
          <td class="text-end"><?=number_format((int)$row['partial_count'])?></td>
          <td class="text-end"><?=number_format((int)$row['empty_count'])?></td>
          <td class="text-end fw-semibold"><?=number_format((int)$row['total_cylinders'])?></td>
          <td class="text-end fw-semibold"><?=number_format($gas, 2)?></td>
          <td class="text-end"><?=number_format($capacity, 2)?><div class="small text-muted"><?=number_format($util,1)?>% utilized</div></td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-primary" href="<?=site_url('inventory/cylinders/'.$row['id'])?>"><i class="bi bi-box-seam me-1"></i>View Cylinders</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?=site_url('inventory/type-history/'.$row['id'])?>"><i class="bi bi-clock-history me-1"></i>History</a>
          </td>
        </tr>
      <?php endforeach;?>
      <?php if(!$summary):?><tr><td colspan="9" class="text-center text-muted py-4">No active cylinder types found.</td></tr><?php endif;?>
      </tbody>
    </table>
  </div>
</div>
<?= $this->endSection() ?>
