<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$typeLabel = static fn (string $t): string => ['gas_per_kg' => 'Gas per KG', 'filled_cylinder' => 'Filled cylinder (gas)', 'cylinder_package' => 'Empty cylinder price'][$t] ?? $t;
$money = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
$cylinderName = static function (array $r) use ($rateCylinderTypes): string {
    $name = trim(($r['cylinder_code'] ?? '') . ' ' . ($r['cylinder_name'] ?? ''));
    if ($name === '' && !empty($r['cylinder_type_id'])) {
        $t = $rateCylinderTypes[(string) $r['cylinder_type_id']] ?? null;
        $name = $t ? trim($t['code'] . ' ' . $t['name']) : 'Cylinder type #' . $r['cylinder_type_id'];
    }
    return $name === '' ? 'All cylinders' : $name;
};
$activeCustomerRates = count(array_filter($customerRates, static fn ($r) => empty($r['ended_at'])));
?>
<div class="page-header">
    <div>
        <h1 class="page-title">LPG Rates</h1>
        <p class="page-subtitle">Gas per KG, gas in a full cylinder, and empty cylinder prices, plus special rates for individual customers. A new rate applies from its effective time; earlier sales keep the rate they were sold at.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#customerRateForm"><i class="bi bi-person-badge me-1"></i>Customer rate</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#form"><i class="bi bi-plus-lg me-1"></i>New rate</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-4"><div class="kpi <?= $currentGasRate ? 'is-success' : 'is-danger' ?>"><div class="kpi-label">Gas rate per KG <i class="bi bi-fuel-pump"></i></div><div class="kpi-value"><?= $currentGasRate ? $money($currentGasRate) . ' <span class="fs-6 text-muted">/ KG</span>' : 'Not set' ?></div><div class="kpi-hint"><?= $currentGasRate ? 'Gas Sale / Refill, and the fallback for cylinders' : 'Set a gas rate so the POS can price gas' ?></div></div></div>
    <div class="col-sm-6 col-xl-4"><div class="kpi is-info"><div class="kpi-label">Gas in a cylinder <i class="bi bi-box-seam"></i></div><div class="kpi-value fs-5"><?= $pricingMode === 'kg_rate' ? 'Per KG' : 'Per cylinder' ?></div><div class="kpi-hint"><?= $pricingMode === 'kg_rate' ? 'Always gas weight × rate per KG (business setting)' : 'Filled cylinder rate, pro-rata for partly filled cylinders' ?></div></div></div>
    <div class="col-sm-6 col-xl-4"><div class="kpi"><div class="kpi-label">Customers with special rates <i class="bi bi-person-badge"></i></div><div class="kpi-value"><?= count(array_unique(array_map(static fn ($r) => $r['customer_id'], array_filter($customerRates, static fn ($r) => empty($r['ended_at']))))) ?></div><div class="kpi-hint"><?= $activeCustomerRates ?> active special rate<?= $activeCustomerRates === 1 ? '' : 's' ?></div></div></div>
</div>

<div class="card mb-3"><div class="card-body">
    <h2 class="h6 mb-1">Current prices per cylinder type</h2>
    <p class="small text-muted mb-3">What the POS charges now when the customer has no special rate. Issue Cylinder and Cylinder Sale charge the gas at the filled cylinder rate; a partly filled cylinder is charged in proportion to its gas.</p>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Cylinder type</th><th class="text-end">Capacity</th><th class="text-end">Gas in a full cylinder</th><th class="text-end">Empty cylinder price</th></tr></thead>
        <tbody>
        <?php foreach ($current as $c): ?>
            <tr>
                <td class="fw-semibold"><?= esc($c['type']['code'] . ' — ' . $c['type']['name']) ?></td>
                <td class="text-end"><?= esc(rtrim(rtrim(number_format((float) $c['type']['capacity_kg'], 3), '0'), '.')) ?> KG</td>
                <td class="text-end"><?= $c['filled'] !== null ? $money($c['filled']) : '<span class="text-muted">Not priced</span>' ?><?php if ($c['filled'] !== null && $c['filled_from_kg']): ?><div class="small text-muted">from the rate per KG</div><?php endif; ?></td>
                <td class="text-end"><?= $c['empty'] > 0 ? $money($c['empty']) : '<span class="text-muted">Not set</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div></div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'branch' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#branchRates" type="button" role="tab">Rate history</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'customers' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#customerRates" type="button" role="tab">Customer rates <span class="badge text-bg-light border ms-1"><?= $activeCustomerRates ?></span></button></li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade <?= $tab === 'branch' ? 'show active' : '' ?>" id="branchRates" role="tabpanel">
        <div class="card"><div class="card-body table-responsive"><table class="table table-sm datatable"><thead><tr><th>Effective</th><th>Rate</th><th>Cylinder type</th><th class="text-end">Value</th><th>Created by</th></tr></thead><tbody>
        <?php foreach ($rates as $r): ?>
            <tr><td><?= esc($r['effective_from']) ?></td><td><?= esc($typeLabel($r['rate_type'])) ?></td><td><?= esc($cylinderName($r)) ?></td><td class="text-end"><?= number_format((float) $r['rate_value'], 2) ?></td><td><?= esc($r['created_by_name'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
    </div>
    <div class="tab-pane fade <?= $tab === 'customers' ? 'show active' : '' ?>" id="customerRates" role="tabpanel">
        <div class="card"><div class="card-body">
            <p class="small text-muted">A customer's special rate replaces the standard rate of the same kind. A special rate per KG also prices that customer's cylinders when no special filled cylinder rate is set. Ending a rate returns the customer to the standard rate.</p>
            <?php if (!$customerRates): ?>
                <div class="empty-state"><i class="bi bi-person-badge"></i><div class="empty-state-title">No customer rates yet</div><p class="mb-0">Use <strong>Customer rate</strong> to give a customer a special price.</p></div>
            <?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle datatable"><thead><tr><th>Customer</th><th>Rate</th><th>Cylinder type</th><th class="text-end">Value</th><th>From</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
            <?php foreach ($customerRates as $r): $active = empty($r['ended_at']); ?>
                <tr>
                    <td><span class="fw-semibold"><?= esc($r['customer_name']) ?></span> <span class="small text-muted"><?= esc($r['customer_code'] ?? '') ?></span><?php if (!empty($r['notes'])): ?><div class="small text-muted"><?= esc($r['notes']) ?></div><?php endif; ?></td>
                    <td><?= esc($typeLabel($r['rate_type'])) ?></td>
                    <td><?= esc($cylinderName($r)) ?></td>
                    <td class="text-end"><?= number_format((float) $r['rate_value'], 2) ?></td>
                    <td class="text-nowrap"><?= esc(date('d M Y H:i', strtotime($r['effective_from']))) ?></td>
                    <td><?= $active ? '<span class="status status-success">Active</span>' : '<span class="status">Ended ' . esc(date('d M Y', strtotime($r['ended_at']))) . '</span>' ?></td>
                    <td class="text-end"><?php if ($active): ?><form method="post" action="<?= site_url('rates/customer/end/' . (int) $r['id']) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" data-confirm="End this special rate for <?= esc($r['customer_name']) ?>? The standard rate will apply from now." data-confirm-danger="false" data-confirm-ok="End rate">End</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </div></div>
    </div>
</div>

<div class="modal fade" id="form" tabindex="-1" aria-labelledby="rateFormTitle"><div class="modal-dialog"><form method="post" action="<?= site_url('rates/save') ?>" class="modal-content"><?= csrf_field() ?>
    <div class="modal-header"><h2 class="modal-title h5" id="rateFormTitle">New rate for this branch</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label required" for="rateType">Rate</label><select name="rate_type" id="rateType" class="form-select" required data-rate-type>
            <option value="gas_per_kg">Gas per KG</option>
            <option value="filled_cylinder">Filled cylinder — price of the gas in one full cylinder</option>
            <option value="cylinder_package">Empty cylinder price</option>
        </select></div>
        <div class="mb-3" data-cylinder-wrap hidden><label class="form-label required" for="rateCylinder">Cylinder type</label><select name="cylinder_type_id" id="rateCylinder" class="form-select"><option value="">Select</option><?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['code'] . ' — ' . $t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label required" for="rateValue">Rate (Rs.)</label><input name="rate_value" id="rateValue" type="number" step="0.01" min="0" class="form-control" required></div>
        <div class="mb-3"><label class="form-label required" for="rateFrom">Effective from</label><input name="effective_from" id="rateFrom" type="datetime-local" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required></div>
        <div class="mb-0"><label class="form-label" for="rateReason">Reason</label><input name="reason" id="rateReason" class="form-control"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Save rate</button></div>
</form></div></div>

<div class="modal fade" id="customerRateForm" tabindex="-1" aria-labelledby="customerRateTitle"><div class="modal-dialog"><form method="post" action="<?= site_url('rates/customer/save') ?>" class="modal-content"><?= csrf_field() ?>
    <div class="modal-header"><h2 class="modal-title h5" id="customerRateTitle">Special rate for a customer</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <?php if (!$customers): ?><div class="alert alert-info mb-0">This branch has no active customers yet.</div><?php else: ?>
        <div class="mb-3"><label class="form-label required" for="crCustomer">Customer</label><select name="customer_id" id="crCustomer" class="form-select" required><option value="">Select customer</option><?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" <?= (string) old('customer_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name'] . ($c['code'] ? ' (' . $c['code'] . ')' : '')) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label required" for="crType">Rate</label><select name="rate_type" id="crType" class="form-select" required data-rate-type>
            <option value="gas_per_kg">Gas per KG</option>
            <option value="filled_cylinder">Filled cylinder — price of the gas in one full cylinder</option>
            <option value="cylinder_package">Empty cylinder price</option>
        </select></div>
        <div class="mb-3" data-cylinder-wrap hidden><label class="form-label required" for="crCylinder">Cylinder type</label><select name="cylinder_type_id" id="crCylinder" class="form-select"><option value="">Select</option><?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['code'] . ' — ' . $t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="row g-3">
            <div class="col-sm-6"><label class="form-label required" for="crValue">Rate (Rs.)</label><input name="rate_value" id="crValue" type="number" step="0.01" min="0" class="form-control" required value="<?= esc(old('rate_value') ?? '') ?>"></div>
            <div class="col-sm-6"><label class="form-label required" for="crFrom">Effective from</label><input name="effective_from" id="crFrom" type="datetime-local" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required></div>
        </div>
        <div class="mt-3"><label class="form-label" for="crNotes">Note</label><input name="notes" id="crNotes" class="form-control" maxlength="255" placeholder="e.g. agreed contract price" value="<?= esc(old('notes') ?? '') ?>"></div>
        <?php endif; ?>
    </div>
    <div class="modal-footer"><button class="btn btn-primary" <?= $customers ? '' : 'disabled' ?>>Save customer rate</button></div>
</form></div></div>
<script>
document.querySelectorAll('[data-rate-type]').forEach(select => {
    const wrap = select.closest('.modal-body').querySelector('[data-cylinder-wrap]');
    const sync = () => {
        const needsType = select.value !== 'gas_per_kg';
        wrap.hidden = !needsType;
        wrap.querySelector('select').required = needsType;
    };
    select.addEventListener('change', sync);
    sync();
});
</script>
<?= $this->endSection() ?>
