<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header">
    <div><h1 class="page-title"><?= esc($title) ?></h1><p class="page-subtitle">Effective values show their source. A shop override takes precedence over a business value, which takes precedence over the application default.</p></div>
    <a class="btn btn-outline-secondary" href="<?= esc($backUrl) ?>"><i class="bi bi-arrow-left me-1"></i>Configuration</a>
</div>

<?php foreach ($settings as $setting): ?>
<?php
    $value = $setting['value'];
    $type = $setting['type'];
    $valueLabel = $type === 'boolean'
        ? ((bool)$value ? 'Enabled / True' : 'Disabled / False')
        : ($type === 'string_list' ? implode(', ', (array)$value)
        : ($type === 'choice' ? (string)($setting['options'][(string)$value] ?? $value) : (string)$value));
    $sourceLabel = match ($setting['source']) {
        'shop' => 'Shop override',
        'business' => 'Business / Head Office',
        'application' => 'Application setting',
        'application_guardrail' => 'Application security/feature guardrail',
        default => 'Application default',
    };
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <h5 class="mb-1"><?= esc($setting['label']) ?></h5>
                <code><?= esc($setting['key']) ?></code>
            </div>
            <span class="badge text-bg-light border"><?= esc($sourceLabel) ?></span>
        </div>
        <div class="row g-3 align-items-start">
            <div class="col-lg-4">
                <div class="small text-muted mb-1">Effective value</div>
                <div class="fw-semibold"><?= esc($valueLabel) ?></div>
                <?php if ($type === 'string_list'): ?>
                    <div class="small text-muted mt-1">The initial release supports English only. Additional languages remain a planned localization extension.</div>
                <?php endif; ?>
            </div>
            <?php if ($canEdit && $type !== 'string_list'): ?>
            <div class="col-lg-8">
                <form method="post" action="<?= site_url('configuration/save') ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="scope" value="<?= esc($scope) ?>">
                    <input type="hidden" name="owner_id" value="<?= (int)$scopeOwnerId ?>">
                    <input type="hidden" name="setting_key" value="<?= esc($setting['key']) ?>">
                    <div class="col-md-8">
                        <label class="form-label small" for="setting-<?= esc($setting['key']) ?>">Update value</label>
                        <?php if ($type === 'boolean'): ?>
                            <select id="setting-<?= esc($setting['key']) ?>" name="value" class="form-select" required>
                                <option value="true" <?= (bool)$value ? 'selected' : '' ?>>True / Enabled</option>
                                <option value="false" <?= !(bool)$value ? 'selected' : '' ?>>False / Disabled</option>
                            </select>
                        <?php elseif ($type === 'integer'): ?>
                            <input id="setting-<?= esc($setting['key']) ?>" name="value" type="number" step="1" min="<?= (int)($setting['min'] ?? 0) ?>" max="<?= (int)($setting['max'] ?? 1000000) ?>" class="form-control" value="<?= esc((string)$value) ?>" required>
                            <?php if (isset($setting['min'], $setting['max'])): ?><div class="form-text">Allowed range: <?= (int)$setting['min'] ?>–<?= (int)$setting['max'] ?>.</div><?php endif; ?>
                        <?php elseif ($type === 'choice'): ?>
                            <select id="setting-<?= esc($setting['key']) ?>" name="value" class="form-select" required>
                                <?php foreach ((array)($setting['options'] ?? []) as $optionValue => $optionLabel): ?>
                                    <option value="<?= esc((string)$optionValue) ?>" <?= (string)$value === (string)$optionValue ? 'selected' : '' ?>><?= esc($optionLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($type === 'decimal'): ?>
                            <input id="setting-<?= esc($setting['key']) ?>" name="value" type="number" step="0.01" min="<?= esc((string)($setting['min'] ?? 0)) ?>" max="<?= esc((string)($setting['max'] ?? 1000000)) ?>" class="form-control" value="<?= esc(number_format((float)$value, 2, '.', '')) ?>" required>
                            <div class="form-text">Amount is stored to two decimal places.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" type="submit">Save setting</button>
                    </div>
                </form>
                <?php if (in_array($scope, ['business','shop'], true) && $setting['has_override']): ?>
                <form method="post" action="<?= site_url('configuration/reset') ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="scope" value="<?= esc($scope) ?>">
                    <input type="hidden" name="owner_id" value="<?= (int)$scopeOwnerId ?>">
                    <input type="hidden" name="setting_key" value="<?= esc($setting['key']) ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Reset override to inherited value</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($scope === 'shop' && $setting['source'] !== 'shop'): ?>
            <div class="small text-muted mt-3">No shop override is set. The value is inherited from <?= esc($sourceLabel) ?>.</div>
        <?php elseif ($scope === 'business' && $setting['source'] !== 'business'): ?>
            <div class="small text-muted mt-3">No business override is set. The value is inherited from <?= esc($sourceLabel) ?>.</div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?= $this->endSection() ?>
