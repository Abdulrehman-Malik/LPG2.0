<?= $this->extend('layouts/app') ?><?= $this->section('content') ?>
<?php
$default = array_merge(['stock_validation_enabled' => 1, 'wastage_mode' => 'percent', 'wastage_percent' => 0, 'wastage_fixed_kg' => 0], (array) ($policies['default'] ?? []));
$overrides = $overrides ?? [];
$describe = static function (array $p): string {
    $allowance = $p['wastage_mode'] === 'fixed_kg'
        ? rtrim(rtrim(number_format((float) $p['wastage_fixed_kg'], 3, '.', ''), '0'), '.') . ' kg per cylinder'
        : rtrim(rtrim(number_format((float) $p['wastage_percent'], 3, '.', ''), '0'), '.') . '% of the cylinder gas';
    return 'Wastage allowance ' . $allowance;
};
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Inventory Controls</h1>
        <p class="page-subtitle">Decide whether POS may sell more gas than is in stock, and how much gas may be written off as wastage per cylinder.</p>
    </div>
    <?php if (\App\Services\PermissionService::allows('GAS_WASTAGE_MANAGE')): ?>
        <div class="page-actions"><a class="btn btn-outline-secondary" href="<?= site_url('inventory/wastage') ?>"><i class="bi bi-droplet-half me-1"></i>Record gas wastage</a></div>
    <?php endif; ?>
</div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h6 mb-1">Shop default policy</h2>
    <p class="small text-muted mb-3">Applies to every cylinder type unless the type has its own setting below.</p>
    <form method="post" action="<?= site_url('inventory/controls/save') ?>" class="row g-3 align-items-end"><?= csrf_field() ?>
        <input type="hidden" name="cylinder_type_id" value="">
        <div class="col-md-4"><label class="form-label" for="defValidation">Stock validation</label>
            <select id="defValidation" name="stock_validation_enabled" class="form-select">
                <option value="1" <?= !empty($default['stock_validation_enabled']) ? 'selected' : '' ?>>On — block selling more than stock</option>
                <option value="0" <?= empty($default['stock_validation_enabled']) ? 'selected' : '' ?>>Off — allow with confirmation</option>
            </select></div>
        <div class="col-md-3"><label class="form-label" for="defMode">Wastage allowance basis</label>
            <select id="defMode" name="wastage_mode" class="form-select">
                <option value="percent" <?= $default['wastage_mode'] === 'percent' ? 'selected' : '' ?>>Percentage of cylinder gas</option>
                <option value="fixed_kg" <?= $default['wastage_mode'] === 'fixed_kg' ? 'selected' : '' ?>>Fixed KG per cylinder</option>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="defPercent">Allowance %</label><input id="defPercent" name="wastage_percent" class="form-control" type="number" step=".001" min="0" max="100" value="<?= esc($default['wastage_percent']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="defKg">Allowance KG</label><input id="defKg" name="wastage_fixed_kg" class="form-control" type="number" step=".001" min="0" value="<?= esc($default['wastage_fixed_kg']) ?>"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100">Save</button></div>
    </form>
    <p class="small text-muted mt-2 mb-0">Only the field matching the basis is used: a percentage basis ignores the KG value and vice versa.</p>
</div></div>

<div class="card"><div class="card-body">
    <h2 class="h6 mb-1">Per cylinder type</h2>
    <p class="small text-muted mb-3">Give a cylinder type its own setting only when it differs from the shop default.</p>
    <?php if (!$types): ?>
        <div class="empty-state"><i class="bi bi-box"></i><p class="mb-0">No active cylinder types.</p></div>
    <?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Cylinder type</th><th>Current setting</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($types as $t): $id = (int) $t['id']; $custom = in_array($id, $overrides, true);
            $p = array_merge($default, (array) ($custom ? ($policies[$id] ?? []) : [])); ?>
            <tr>
                <td class="fw-semibold"><?= esc($t['code'] . ' — ' . $t['name']) ?></td>
                <td>
                    <?php if ($custom): ?><span class="status status-info">Custom</span><?php else: ?><span class="status">Shop default</span><?php endif; ?>
                    <div class="small text-muted mt-1">Validation <?= !empty($p['stock_validation_enabled']) ? 'on' : 'off' ?> · <?= esc($describe($p)) ?></div>
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#ovr<?= $id ?>" aria-expanded="false" aria-controls="ovr<?= $id ?>"><?= $custom ? 'Edit' : 'Customise' ?></button>
                    <?php if ($custom): ?>
                        <form method="post" action="<?= site_url('inventory/controls/reset') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="cylinder_type_id" value="<?= $id ?>">
                            <button class="btn btn-sm btn-outline-secondary" data-confirm="Use the shop default policy for <?= esc($t['code']) ?>?" data-confirm-danger="false" data-confirm-ok="Use shop default">Use shop default</button></form>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="collapse" id="ovr<?= $id ?>"><td colspan="3" class="bg-body-tertiary">
                <form method="post" action="<?= site_url('inventory/controls/save') ?>" class="row g-2 align-items-end"><?= csrf_field() ?>
                    <input type="hidden" name="cylinder_type_id" value="<?= $id ?>">
                    <div class="col-md-4"><label class="form-label small" for="val<?= $id ?>">Stock validation</label>
                        <select id="val<?= $id ?>" name="stock_validation_enabled" class="form-select form-select-sm">
                            <option value="1" <?= !empty($p['stock_validation_enabled']) ? 'selected' : '' ?>>On — block selling more than stock</option>
                            <option value="0" <?= empty($p['stock_validation_enabled']) ? 'selected' : '' ?>>Off — allow with confirmation</option>
                        </select></div>
                    <div class="col-md-3"><label class="form-label small" for="mode<?= $id ?>">Allowance basis</label>
                        <select id="mode<?= $id ?>" name="wastage_mode" class="form-select form-select-sm">
                            <option value="percent" <?= $p['wastage_mode'] === 'percent' ? 'selected' : '' ?>>Percentage</option>
                            <option value="fixed_kg" <?= $p['wastage_mode'] === 'fixed_kg' ? 'selected' : '' ?>>Fixed KG</option>
                        </select></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="pct<?= $id ?>">Allowance %</label><input id="pct<?= $id ?>" name="wastage_percent" class="form-control form-control-sm" type="number" step=".001" min="0" max="100" value="<?= esc($p['wastage_percent']) ?>"></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="kg<?= $id ?>">Allowance KG</label><input id="kg<?= $id ?>" name="wastage_fixed_kg" class="form-control form-control-sm" type="number" step=".001" min="0" value="<?= esc($p['wastage_fixed_kg']) ?>"></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                </form>
            </td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>
<?= $this->endSection() ?>
