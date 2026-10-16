<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header"><div><h1 class="page-title">LPG Rates &amp; History</h1><p class="page-subtitle">The gas rate per KG and empty-cylinder prices used by the POS. A new rate applies from its effective time; earlier sales keep the rate they were sold at.</p></div><div class="page-actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#form"><i class="bi bi-plus-lg me-1"></i>New rate</button></div></div>
<div class="row g-3 mb-3"><div class="col-sm-6 col-xl-4"><div class="kpi <?= $currentGasRate ? 'is-success' : 'is-danger' ?>"><div class="kpi-label">Current gas rate <i class="bi bi-fuel-pump"></i></div><div class="kpi-value"><?= $currentGasRate ? 'Rs. ' . number_format((float) $currentGasRate, 2) . ' <span class="fs-6 text-muted">/ KG</span>' : 'Not set' ?></div><div class="kpi-hint"><?= $currentGasRate ? 'Used by the POS for new gas sales' : 'Set a gas rate so the POS can price gas sales' ?></div></div></div></div>
<div class="card"><div class="card-body table-responsive"><table class="table table-sm datatable"><thead><tr><th>Effective</th><th>Type</th><th>Empty Cylinder Type</th><th>Rate</th><th>Created By</th></tr></thead><tbody><?php foreach($rates as $r): ?><tr><td><?= esc($r['effective_from']) ?></td><td><?= $r['rate_type']==='gas_per_kg'?'Gas / KG':'Empty Cylinder Price' ?></td><?php $rateCylinder=$rateCylinderTypes[(string)($r['cylinder_type_id']??'')]??null; $displayCylinder=trim(($r['cylinder_code']??'').' '.($r['cylinder_name']??'')); if($displayCylinder==='') $displayCylinder=trim(($rateCylinder['code']??'').' '.($rateCylinder['name']??'')); if($displayCylinder==='') $displayCylinder='Empty Cylinder Type #'.($r['cylinder_type_id']??''); ?><td><?= $r['rate_type']==='gas_per_kg' ? 'All Cylinders' : esc($displayCylinder) ?></td><td><?= number_format((float)$r['rate_value'],2) ?></td><td><?= esc($r['created_by_name']??'') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="modal fade" id="form"><div class="modal-dialog"><form method="post" action="<?= site_url('rates/save') ?>" class="modal-content"><?= csrf_field() ?><div class="modal-header"><h5>New Rate</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Rate Type</label><select name="rate_type" id="rateType" class="form-select" required><option value="gas_per_kg">Gas per KG</option><option value="cylinder_package">Cylinder Price</option></select></div><div class="mb-3" id="typeWrap" style="display:none"><label class="form-label">Empty Cylinder Type</label><select name="cylinder_type_id" class="form-select"><option value="">Select</option><?php foreach($types as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['code'].' — '.$t['name']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Rate / Price</label><input name="rate_value" type="number" step="0.01" min="0" class="form-control" required></div><div class="mb-3"><label class="form-label">Effective From</label><input name="effective_from" type="datetime-local" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required></div><div class="mb-3"><label class="form-label">Reason</label><input name="reason" class="form-control"></div></div><div class="modal-footer"><button class="btn btn-primary">Save Rate</button></div></form></div></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rateType = document.getElementById('rateType');
    const typeWrap = document.getElementById('typeWrap');
    if (!rateType || !typeWrap) return;

    const syncCylinderVisibility = () => {
        typeWrap.style.display = rateType.value === 'cylinder_package' ? 'block' : 'none';
    };

    rateType.addEventListener('change', syncCylinderVisibility);
    syncCylinderVisibility();
});
</script>
<?= $this->endSection() ?>