<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
// Re-open the form with the submitted values when a save failed (redirect()->withInput()).
$hasOld = old('name') !== null;
$val = static fn (string $field) => old($field) ?? ($edit[$field] ?? '');
$checked = static fn (string $field, bool $default) => $hasOld ? (old($field) ? 'checked' : '') : (($edit ? !empty($edit[$field]) : $default) ? 'checked' : '');
$active = array_filter($customers, static fn ($c) => (int) $c['is_active'] === 1);
$credit = array_filter($active, static fn ($c) => !empty($c['allow_credit_sale']));
$owing = array_filter($balances, static fn ($b) => $b > 0.005);
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Customers / Parties</h1>
        <p class="page-subtitle">Customers of this branch, their credit terms and what they currently owe. Open a ledger to see every sale, receipt and deposit.</p>
    </div>
    <div class="page-actions">
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= site_url('customers/template') ?>"><i class="bi bi-download me-2"></i>Download template</a></li>
                <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#excelImport"><i class="bi bi-upload me-2"></i>Upload customers</button></li>
            </ul>
        </div>
        <?php if ($edit): ?>
        <a class="btn btn-primary" href="<?= site_url('customers?new=1') ?>"><i class="bi bi-person-plus me-1"></i>Add customer</a>
        <?php else: ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#form"><i class="bi bi-person-plus me-1"></i>Add customer</button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="kpi"><div class="kpi-label">Active customers <i class="bi bi-people"></i></div><div class="kpi-value"><?= count($active) ?></div><div class="kpi-hint"><?= count($customers) - count($active) ?> inactive</div></div></div>
    <div class="col-sm-4"><div class="kpi is-info"><div class="kpi-label">Credit allowed <i class="bi bi-credit-card"></i></div><div class="kpi-value"><?= count($credit) ?></div><div class="kpi-hint">Others are cash only</div></div></div>
    <div class="col-sm-4"><div class="kpi is-danger"><div class="kpi-label">Outstanding (OS) <i class="bi bi-hourglass-split"></i></div><div class="kpi-value">Rs. <?= number_format(array_sum($owing), 2) ?></div><div class="kpi-hint"><?= count($owing) ?> customer<?= count($owing) === 1 ? '' : 's' ?> owe money</div></div></div>
</div>

<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-2" aria-label="Filter customers">
    <label class="form-label mb-0" for="typeFilter">Customer type</label>
    <select id="typeFilter" name="type" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All types</option>
        <?php foreach (\App\Services\CustomerFields::TYPES as $code => $label): ?><option value="<?= esc($code) ?>" <?= ($typeFilter ?? '') === $code ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-sm btn-outline-secondary">Filter</button></noscript>
</form>

<div class="card">
    <?php if (!$customers): ?>
        <div class="empty-state"><i class="bi bi-people"></i><div class="empty-state-title">No customers yet</div>Add a customer or upload them from the Excel template. Walk-in sales need no customer.</div>
    <?php else: ?>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 datatable">
            <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Phone</th><th>City</th><th>Credit</th><th class="text-end">Credit Limit</th><th class="text-end">Opening</th><th class="text-end">OS Balance</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): $os = (float) ($balances[(int) $c['id']] ?? 0); ?>
                <tr>
                    <td class="fw-semibold"><?= esc($c['code'] ?? '') ?></td>
                    <td><?= esc($c['name']) ?><?php if (!empty($c['vehicle_no'])): ?><div class="small text-muted"><?= esc($c['vehicle_no']) ?></div><?php endif; ?></td>
                    <td><?= esc(\App\Services\CustomerFields::typeLabel($c['customer_type'] ?? null)) ?></td>
                    <td class="text-nowrap"><?= esc($c['phone'] ?? '') ?><?php if (!empty($c['whatsapp_enabled'])): ?> <i class="bi bi-whatsapp text-success" title="WhatsApp enabled"></i><?php endif; ?></td>
                    <td><?= esc($c['city'] ?? '') ?><?php if (!empty($c['area'])): ?><div class="small text-muted"><?= esc($c['area']) ?></div><?php endif; ?></td>
                    <td><?= !empty($c['allow_credit_sale']) ? '<span class="status status-info">Allowed</span>' : '<span class="status">Cash only</span>' ?></td>
                    <td class="text-end"><?= (float) $c['credit_limit'] > 0 ? number_format((float) $c['credit_limit'], 2) : (!empty($c['allow_credit_sale']) ? '<span class="text-muted">No limit</span>' : '—') ?></td>
                    <td class="text-end"><?= number_format((float) $c['opening_balance'], 2) ?></td>
                    <td class="text-end fw-semibold <?= $os > 0.005 ? 'text-danger' : '' ?>" data-order="<?= $os ?>"><?= number_format($os, 2) ?></td>
                    <td><?= $c['is_active'] ? '<span class="status status-success">Active</span>' : '<span class="status">Inactive</span>' ?><?php if (!empty($c['is_blocked'])): ?> <span class="status status-danger" title="No new sales">Blocked</span><?php endif; ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('customers?edit=' . $c['id']) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('customers/ledger/' . $c['id']) ?>"><i class="bi bi-journal-text me-1"></i>Ledger</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="excelImport" tabindex="-1" aria-labelledby="excelImportTitle" aria-hidden="true">
    <div class="modal-dialog"><form method="post" action="<?= site_url('customers/import') ?>" enctype="multipart/form-data" class="modal-content">
        <?= csrf_field() ?>
        <div class="modal-header"><h2 class="modal-title fs-5" id="excelImportTitle">Upload customers from Excel</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="small text-muted">Use the <a href="<?= site_url('customers/template') ?>">Excel template</a>. Each row creates one customer. Existing customer codes are rejected; existing customers are never overwritten.</p>
            <label class="form-label required" for="customerExcel">Excel file (.xlsx)</label>
            <input type="file" id="customerExcel" name="customer_excel" class="form-control" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            <div class="form-text">Maximum 5 MB and 5,000 rows.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload &amp; import</button></div>
    </form></div>
</div>

<div class="modal fade" id="form" tabindex="-1" aria-labelledby="customerFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg"><form method="post" action="<?= site_url('customers/save') ?>" class="modal-content">
        <?= csrf_field() ?>
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="modal-header"><h2 class="modal-title fs-5" id="customerFormTitle"><?= $edit ? 'Edit customer — ' . esc($edit['name']) : 'Add customer' ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="form-section">
                <h3 class="form-section-title">Contact</h3>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label" for="cCode">Code</label><input id="cCode" name="code" class="form-control" maxlength="40" value="<?= esc($val('code')) ?>" placeholder="Optional"></div>
                    <div class="col-md-6"><label class="form-label required" for="cName">Name</label><input id="cName" name="name" class="form-control" maxlength="150" required value="<?= esc($val('name')) ?>"></div>
                    <div class="col-md-3"><label class="form-label" for="cType">Customer type</label><select id="cType" name="customer_type" class="form-select"><?php $curType = (string) ($val('customer_type') ?: 'domestic'); foreach (\App\Services\CustomerFields::TYPES as $code => $label): ?><option value="<?= esc($code) ?>" <?= $curType === $code ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label" for="cPhone">Phone</label><input id="cPhone" name="phone" class="form-control" maxlength="30" inputmode="tel" value="<?= esc($val('phone')) ?>"></div>
                    <div class="col-md-3"><label class="form-label" for="cCity">City</label><input id="cCity" name="city" class="form-control" maxlength="80" value="<?= esc($val('city')) ?>"></div>
                    <div class="col-md-3"><label class="form-label" for="cArea">Area</label><input id="cArea" name="area" class="form-control" maxlength="80" value="<?= esc($val('area')) ?>" placeholder="Mohalla / sector"></div>
                    <div class="col-md-3"><label class="form-label" for="cVehicle">Vehicle no.</label><input id="cVehicle" name="vehicle_no" class="form-control" maxlength="40" value="<?= esc($val('vehicle_no')) ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="cAddress">Address</label><input id="cAddress" name="address" class="form-control" maxlength="255" value="<?= esc($val('address')) ?>"></div>
                </div>
            </div>
            <div class="form-section">
                <h3 class="form-section-title">Status and notes</h3>
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="is_blocked" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="cBlocked" name="is_blocked" value="1" <?= $checked('is_blocked', false) ?>>
                    <label class="form-check-label" for="cBlocked"><strong>Blocked</strong> — no new sales; the customer stays listed and can still pay</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="whatsapp_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="cWhatsapp" name="whatsapp_enabled" value="1" <?= $checked('whatsapp_enabled', false) ?>>
                    <label class="form-check-label" for="cWhatsapp">WhatsApp messages (receipts, reminders) to the phone number</label>
                </div>
                <label class="form-label" for="cNotes">Notes</label><textarea id="cNotes" name="notes" class="form-control" rows="2" maxlength="500"><?= esc($val('notes')) ?></textarea>
            </div>
            <div class="form-section mb-0">
                <h3 class="form-section-title">Credit and balances</h3>
                <p class="form-section-hint">Credit sales are also limited by the branch's credit-limit mode in Shop Settings.</p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="cCredit" name="allow_credit_sale" value="1" <?= $checked('allow_credit_sale', false) ?>>
                    <label class="form-check-label" for="cCredit"><strong>Allow credit sale</strong> — the customer may leave part of a sale unpaid (OS)</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="cLimit">Credit limit (Rs.)</label><input id="cLimit" name="credit_limit" type="number" min="0" step="0.01" class="form-control" value="<?= esc($val('credit_limit') !== '' ? $val('credit_limit') : '0') ?>" inputmode="decimal"><div class="form-text">0 = no limit when credit is allowed</div></div>
                    <div class="col-md-4"><label class="form-label" for="cOpening">Opening balance (Rs.)</label><input id="cOpening" name="opening_balance" type="number" min="0" step="0.01" class="form-control" value="<?= esc($val('opening_balance') !== '' ? $val('opening_balance') : '0') ?>" inputmode="decimal"><div class="form-text">Amount owed before using this system</div></div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="cActive" name="is_active" value="1" <?= $checked('is_active', true) ?>>
                            <label class="form-check-label" for="cActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><?= $edit ? 'Update customer' : 'Save customer' ?></button></div>
    </form></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($edit || $hasOld || service('request')->getGet('new')): ?>
<script>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('form')).show());</script>
<?php endif; ?>
<?= $this->endSection() ?>
