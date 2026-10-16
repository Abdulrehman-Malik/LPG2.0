<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">Businesses & Shops</h4>
        <p class="text-muted mb-0">Manage the business hierarchy and staff access. Every user action is checked against both permissions and authorized business/shop scope.</p>
    </div>
</div>

<?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $flashKey => $class): ?>
    <?php if (session()->getFlashdata($flashKey)): ?>
        <div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata($flashKey)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($canCreateBusiness): ?>
<div class="card mb-4">
    <div class="card-header"><strong><i class="bi bi-building-add me-2"></i>Create business / Head Office</strong></div>
    <div class="card-body">
        <form method="post" action="<?= site_url('business-management/business/save') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-3"><label class="form-label" for="business-code">Business code *</label><input id="business-code" class="form-control" name="code" maxlength="40" required pattern="[A-Za-z0-9_-]{2,40}" value="<?= esc(old('code')) ?>"></div>
            <div class="col-md-5"><label class="form-label" for="business-name">Business / Head Office name *</label><input id="business-name" class="form-control" name="name" maxlength="150" required value="<?= esc(old('name')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="business-email">Email</label><input id="business-email" type="email" class="form-control" name="email" maxlength="150" value="<?= esc(old('email')) ?>"></div>
            <div class="col-md-3"><label class="form-label" for="business-phone">Phone</label><input id="business-phone" class="form-control" name="phone" maxlength="30" value="<?= esc(old('phone')) ?>"></div>
            <div class="col-md-5"><label class="form-label" for="business-address">Address</label><input id="business-address" class="form-control" name="address" maxlength="255" value="<?= esc(old('address')) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="business-city">City</label><input id="business-city" class="form-control" name="city" maxlength="80" value="<?= esc(old('city')) ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Create business</button></div>
        </form>
        <div class="form-text mt-2">Only a System Administrator can create a new business. Business owners do not receive platform-wide administrator privileges.</div>
    </div>
</div>
<?php endif; ?>

<?php if (!$businesses): ?>
<div class="alert alert-info">No active businesses are assigned to this account. Ask a System Administrator to assign a business or shop.</div>
<?php endif; ?>

<?php foreach ($businesses as $business): ?>
<section class="card mb-4">
    <div class="card-header d-flex align-items-start justify-content-between gap-2 flex-wrap">
        <div><h5 class="mb-1"><?= esc($business['name']) ?></h5><div class="small text-muted"><?= esc($business['code']) ?> · <?= esc($business['city'] ?? '') ?></div></div>
        <span class="badge text-bg-light border"><?= count($business['shops']) ?> accessible shop(s)</span>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h6 class="mb-0">Shop locations</h6>
            <?php if (!$business['can_manage_business']): ?><span class="small text-muted">View access limited to assigned shops</span><?php endif; ?>
        </div>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Code</th><th>Shop</th><th>City</th><th>Phone</th><th>Setup</th><th class="text-end">Settings</th></tr></thead>
                <tbody>
                <?php if (empty($business['shops'])): ?>
                    <tr><td colspan="6" class="text-muted text-center py-3">No accessible active shops.</td></tr>
                <?php else: ?>
                    <?php $readiness = (new \App\Services\BranchReadinessService())->forLocations(array_map(static fn($s) => (int) $s['id'], $business['shops'])); ?>
                    <?php foreach ($business['shops'] as $shop): $checks = $readiness[(int) $shop['id']] ?? []; $missing = array_values(array_filter($checks, static fn($c) => !$c['ok'])); ?>
                    <tr>
                        <td><?= esc($shop['code'] ?? '') ?></td><td class="fw-semibold"><?= esc($shop['name']) ?></td>
                        <td><?= esc($shop['city'] ?? '') ?></td><td><?= esc($shop['phone'] ?? '') ?></td>
                        <td>
                            <?php if (!$missing): ?>
                                <span class="status status-success"><i class="bi bi-check2-circle me-1"></i>Ready</span>
                            <?php else: ?>
                                <?php foreach ($missing as $item): ?><span class="status status-warning me-1" title="<?= esc($item['hint']) ?>"><i class="bi bi-exclamation-triangle me-1"></i><?= esc($item['label']) ?></span><?php endforeach; ?>
                                <?php if ((int) $shop['id'] !== (int) session()->get('location_id')): ?>
                                    <form method="post" action="<?= site_url('shop-context/select') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="location_id" value="<?= (int) $shop['id'] ?>"><button class="btn btn-link btn-sm p-0 ms-1" type="submit">Open branch to finish setup</button></form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('configuration/shop/' . (int)$shop['id']) ?>">Shop settings</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($canCreateShop && $business['can_manage_business']): ?>
        <details class="border rounded p-3 mb-3">
            <summary class="fw-semibold">Add shop to this business</summary>
            <form method="post" action="<?= site_url('business-management/shop/save') ?>" class="row g-3 mt-1">
                <?= csrf_field() ?><input type="hidden" name="business_id" value="<?= (int)$business['id'] ?>">
                <div class="col-md-3"><label class="form-label">Shop code *</label><input class="form-control" name="code" maxlength="30" required pattern="[A-Za-z0-9_-]{2,30}"></div>
                <div class="col-md-4"><label class="form-label">Shop name *</label><input class="form-control" name="name" maxlength="120" required></div>
                <div class="col-md-3"><label class="form-label">Address</label><input class="form-control" name="address" maxlength="255"></div>
                <div class="col-md-2"><label class="form-label">City</label><input class="form-control" name="city" maxlength="80"></div>
                <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="30"></div>
                <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary" type="submit">Create shop with defaults</button></div>
            </form>
        </details>
        <?php endif; ?>

        <?php if ($canManageAccess && $business['can_manage_business']): ?>
        <div class="border-top pt-3 mt-3">
            <h6 class="mb-2">Staff access assignments</h6>
            <p class="small text-muted">Access scope is separate from role permissions. A staff assignment change revokes existing mobile API sessions so new access takes effect on the next sign-in.</p>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead class="table-light"><tr><th>Staff</th><th>Access scope</th><th>Default</th><th>Status</th><th class="text-end">Remove</th></tr></thead>
                    <tbody>
                    <?php if (empty($business['assignments'])): ?>
                        <tr><td colspan="5" class="text-muted text-center py-3">No explicit assignments are recorded for this business.</td></tr>
                    <?php else: ?>
                        <?php foreach ($business['assignments'] as $assignment): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= esc($assignment['full_name']) ?></div><div class="small text-muted"><?= esc($assignment['username']) ?></div></td>
                            <td><?= $assignment['access_type'] === 'head_office' ? 'Head Office / all shops in business' : esc($assignment['shop_name'] ?? 'Shop') ?></td>
                            <td><?= (int)$assignment['is_default'] === 1 ? '<span class="badge text-bg-primary">Default</span>' : '—' ?></td>
                            <td><?= (int)$assignment['is_active'] === 1 ? 'Active' : 'Disabled' ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= site_url('business-management/staff-access/save') ?>" data-confirm="Remove this access assignment?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="business_id" value="<?= (int)$business['id'] ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$assignment['user_id'] ?>">
                                    <input type="hidden" name="access_type" value="<?= esc($assignment['access_type']) ?>">
                                    <input type="hidden" name="shop_id" value="<?= (int)($assignment['shop_id'] ?? 0) ?>">
                                    <input type="hidden" name="enabled" value="0"><input type="hidden" name="is_default" value="0">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($business['staff_options']) && !empty($business['shops'])): ?>
            <details class="border rounded p-3">
                <summary class="fw-semibold">Assign staff to a shop</summary>
                <form method="post" action="<?= site_url('business-management/staff-access/save') ?>" class="row g-3 mt-1">
                    <?= csrf_field() ?><input type="hidden" name="business_id" value="<?= (int)$business['id'] ?>">
                    <div class="col-lg-4"><label class="form-label">Staff account *</label><select class="form-select" name="user_id" required><option value="">Select staff</option><?php foreach ($business['staff_options'] as $staff): ?><option value="<?= (int)$staff['id'] ?>"><?= esc($staff['full_name'] . ' (' . $staff['username'] . ')') ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-3"><label class="form-label">Access type</label><select class="form-select" name="access_type"><option value="shop">Shop access</option><?php if ($isSystemAdmin): ?><option value="head_office">Head Office business access</option><?php endif; ?></select></div>
                    <div class="col-lg-3"><label class="form-label">Shop *</label><select class="form-select" name="shop_id"><option value="0">Choose a shop</option><?php foreach ($business['shops'] as $shop): ?><option value="<?= (int)$shop['id'] ?>"><?= esc($shop['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-2 d-flex align-items-end"><input type="hidden" name="enabled" value="1"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_default" value="1"> Default</label></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Save assignment</button></div>
                </form>
                <div class="form-text mt-2">Staff accounts are created from Users / Roles. Existing custom role permissions are not changed by assigning a shop.</div>
            </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
<?= $this->endSection() ?>
