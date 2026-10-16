<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$hasOld = old('name') !== null;
$val = static fn (string $field) => old($field) ?? ($edit[$field] ?? '');
$activeChecked = $hasOld ? (old('is_active') ? 'checked' : '') : ((!$edit || $edit['is_active']) ? 'checked' : '');
$mode = $scopeMode ?? 'shared';
$branchOnly = $hasOld ? old('supplier_scope') === 'branch' : (isset($edit['location_id']) && $edit['location_id'] !== null);
$active = array_filter($suppliers, static fn ($s) => (int) $s['is_active'] === 1);
$payable = array_sum(array_filter($balances, static fn ($b) => $b > 0.005));
$sharedCount = count(array_filter($suppliers, static fn ($s) => $s['location_id'] === null));
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Suppliers</h1>
        <p class="page-subtitle">Gas and cylinder suppliers available to this branch and what is owed to each. <?= ($balanceMode ?? 'branch') === 'business' ? 'Shared suppliers have one balance for the whole business.' : 'Each branch has its own balance with a supplier.' ?><?php if (\App\Services\PermissionService::allows('BUSINESS_SETTINGS_MANAGE')): ?> <a href="<?= site_url('configuration') ?>">Change in business settings</a><?php endif; ?></p>
    </div>
    <div class="page-actions">
        <?php if ($edit): ?>
            <a class="btn btn-primary" href="<?= site_url('suppliers?new=1') ?>"><i class="bi bi-plus-lg me-1"></i>Add supplier</a>
        <?php else: ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#form"><i class="bi bi-plus-lg me-1"></i>Add supplier</button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="kpi"><div class="kpi-label">Active suppliers <i class="bi bi-truck"></i></div><div class="kpi-value"><?= count($active) ?></div><div class="kpi-hint"><?= $sharedCount ?> shared with all branches</div></div></div>
    <div class="col-sm-4"><div class="kpi is-danger"><div class="kpi-label">Payable by this branch <i class="bi bi-cash-coin"></i></div><div class="kpi-value">Rs. <?= number_format($payable, 2) ?></div></div></div>
    <div class="col-sm-4"><div class="kpi is-info"><div class="kpi-label">Supplier mode <i class="bi bi-diagram-3"></i></div><div class="kpi-value fs-5"><?= esc(['shared' => 'Shared', 'branch' => 'Branch only', 'both' => 'Shared or branch'][$mode] ?? ucfirst($mode)) ?></div><div class="kpi-hint">Set in Central Configuration</div></div></div>
</div>

<div class="card">
    <?php if (!$suppliers): ?>
        <div class="empty-state"><i class="bi bi-truck"></i><div class="empty-state-title">No suppliers yet</div>Add the companies you buy gas and cylinders from.</div>
    <?php else: ?>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 datatable">
            <thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>City</th><th>Availability</th><th class="text-end">Credit Limit</th><th class="text-end">Opening</th><th class="text-end">Payable</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $s): $bal = (float) ($balances[(int) $s['id']] ?? 0); ?>
                <tr>
                    <td class="fw-semibold"><?= esc($s['code'] ?? '') ?></td>
                    <td><?= esc($s['name']) ?></td>
                    <td class="text-nowrap"><?= esc($s['phone'] ?? '') ?></td>
                    <td><?= esc($s['city'] ?? '') ?></td>
                    <td><?= $s['location_id'] === null ? '<span class="status">All branches</span>' : '<span class="status status-info">This branch only</span>' ?></td>
                    <td class="text-end"><?= number_format((float) $s['credit_limit'], 2) ?></td>
                    <td class="text-end"><?= number_format((float) ($openings[(int) $s['id']] ?? 0), 2) ?></td>
                    <td class="text-end fw-semibold <?= $bal > 0.005 ? 'text-danger' : '' ?>" data-order="<?= $bal ?>"><?= number_format($bal, 2) ?></td>
                    <td><?= $s['is_active'] ? '<span class="status status-success">Active</span>' : '<span class="status">Inactive</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('suppliers?edit=' . $s['id']) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('suppliers/ledger/' . $s['id']) ?>"><i class="bi bi-journal-text me-1"></i>Ledger</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="form" tabindex="-1" aria-labelledby="supplierFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg"><form method="post" action="<?= site_url('suppliers/save') ?>" class="modal-content">
        <?= csrf_field() ?>
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="modal-header"><h2 class="modal-title fs-5" id="supplierFormTitle"><?= $edit ? 'Edit supplier — ' . esc($edit['name']) : 'Add supplier' ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="form-section">
                <h3 class="form-section-title">Contact</h3>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label" for="sCode">Code</label><input id="sCode" name="code" class="form-control" maxlength="40" value="<?= esc($val('code')) ?>" placeholder="Optional"></div>
                    <div class="col-md-5"><label class="form-label required" for="sName">Name</label><input id="sName" name="name" class="form-control" maxlength="150" required value="<?= esc($val('name')) ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="sPhone">Phone</label><input id="sPhone" name="phone" class="form-control" maxlength="30" inputmode="tel" value="<?= esc($val('phone')) ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="sCity">City</label><input id="sCity" name="city" class="form-control" maxlength="80" value="<?= esc($val('city')) ?>"></div>
                    <div class="col-md-8"><label class="form-label" for="sAddress">Address</label><input id="sAddress" name="address" class="form-control" maxlength="255" value="<?= esc($val('address')) ?>"></div>
                </div>
            </div>
            <div class="form-section mb-0">
                <h3 class="form-section-title">Terms and availability</h3>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="sLimit">Credit limit (Rs.)</label><input id="sLimit" name="credit_limit" type="number" min="0" step="0.01" class="form-control" inputmode="decimal" value="<?= esc($val('credit_limit') !== '' ? $val('credit_limit') : '0') ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="sOpening">Opening balance at <?= esc($branchName ?? 'this branch') ?> (Rs.)</label><input id="sOpening" name="opening_balance" type="number" min="0" step="0.01" class="form-control" inputmode="decimal" value="<?= esc(old('opening_balance') ?? (string) ($edit['branch_opening'] ?? '0')) ?>"><div class="form-text">Amount this branch owed before using this system.<?= ($balanceMode ?? 'branch') === 'business' ? ' Shared suppliers have one balance for the business: openings of all branches are added together.' : ' Each branch records its own opening.' ?></div></div>
                    <div class="col-md-4 d-flex align-items-center"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="sActive" name="is_active" value="1" <?= $activeChecked ?>><label class="form-check-label" for="sActive">Active</label></div></div>
                    <div class="col-12">
                        <?php if ($mode === 'both'): ?>
                            <label class="form-label" for="sScope">Available to</label>
                            <select id="sScope" name="supplier_scope" class="form-select"><option value="business" <?= $branchOnly ? '' : 'selected' ?>>All branches of this business</option><option value="branch" <?= $branchOnly ? 'selected' : '' ?>>This branch only</option></select>
                        <?php else: ?>
                            <div class="form-text"><i class="bi bi-info-circle me-1"></i><?= $mode === 'branch' ? 'Suppliers are branch-only for this business: this supplier will be available to the current branch only.' : 'Suppliers are shared for this business: this supplier will be available to all branches.' ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><?= $edit ? 'Update supplier' : 'Save supplier' ?></button></div>
    </form></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($edit || $hasOld || service('request')->getGet('new')): ?>
<script>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('form')).show());</script>
<?php endif; ?>
<?= $this->endSection() ?>
