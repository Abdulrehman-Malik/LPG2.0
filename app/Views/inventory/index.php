<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header">
  <div><h1 class="page-title">Inventory</h1><p class="page-subtitle">Current branch stock by cylinder type, physical cylinder status and actual gas quantity.</p></div>
  <a class="btn btn-outline-primary" href="<?=site_url('inventory/adjustments')?>"><i class="bi bi-sliders2-vertical me-1"></i>Stock Adjustment & History</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl"><div class="kpi is-info"><div class="kpi-label">Gas in stock <i class="bi bi-fuel-pump"></i></div><div class="kpi-value"><?=number_format($totalGas, 2)?> <span class="fs-6 text-muted">KG</span></div><div class="kpi-hint">In filled and partly used cylinders</div></div></div>
  <div class="col-sm-6 col-xl"><div class="kpi"><div class="kpi-label">Cylinders <i class="bi bi-boxes"></i></div><div class="kpi-value"><?=number_format($totalCylinders)?></div><div class="kpi-hint">Company cylinders in this branch</div></div></div>
  <div class="col-sm-4 col-xl"><div class="kpi is-success"><div class="kpi-label">Filled <i class="bi bi-circle-fill"></i></div><div class="kpi-value"><?=number_format($filledCount)?></div></div></div>
  <div class="col-sm-4 col-xl"><div class="kpi is-warning"><div class="kpi-label">Partly used <i class="bi bi-circle-half"></i></div><div class="kpi-value"><?=number_format($partialCount)?></div></div></div>
  <div class="col-sm-4 col-xl"><div class="kpi"><div class="kpi-label">Empty <i class="bi bi-circle"></i></div><div class="kpi-value"><?=number_format($emptyCount)?></div></div></div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div><h2 class="h6 mb-0">Cylinder Type Stock</h2><div class="small text-muted">Running/current total calculated from physical cylinder records.</div></div>
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
          <td class="text-end"><?=number_format($capacity, 2)?><div class="d-flex align-items-center gap-2 justify-content-end mt-1"><div class="progress flex-grow-1" style="height:.4rem;max-width:6rem" role="img" aria-label="<?=number_format($util,1)?>% full"><div class="progress-bar" style="width:<?=min(100,round($util,1))?>%"></div></div><span class="small text-muted"><?=number_format($util,0)?>% full</span></div></td>
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
