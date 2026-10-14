<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Shop Settings</h4>
        <div class="text-muted small">Branch-level configuration for <?= esc($location['name'] ?? 'Current Branch') ?></div>
    </div>
    <span class="badge text-bg-secondary">Branch configuration</span>
</div>

<?php if(empty($settings['id'])): ?><div class="alert alert-warning"><strong>Shop Settings storage is not initialized.</strong> Run <code>database/migrations/20260930_shop_settings.sql</code> once on this existing database, then refresh this page.</div><?php endif; ?>
<form method="post" action="<?= site_url('shop-settings/save') ?>">
<?= csrf_field() ?>
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general" type="button">General</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#appearance" type="button">Appearance</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pos" type="button">POS & Sales</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#inventory" type="button">Inventory Control</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cash" type="button">Cash & Payments</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#custody" type="button">Cylinder Custody</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#security" type="button">Security & Void</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#receipt" type="button">Receipt & Printing</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#backup" type="button">Backup & Maintenance</button></li>
        </ul>
    </div>

    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="general">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Branch Code</label><input class="form-control" value="<?= esc($location['code'] ?? '') ?>" readonly></div>
                    <div class="col-md-8"><label class="form-label">Shop / Branch Name</label><input name="shop_name" class="form-control" required value="<?= esc($location['name'] ?? '') ?>"></div>
                    <div class="col-md-8"><label class="form-label">Address</label><input name="shop_address" class="form-control" value="<?= esc($location['address'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">City</label><input name="shop_city" class="form-control" value="<?= esc($location['city'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Phone</label><input name="shop_phone" class="form-control" value="<?= esc($location['phone'] ?? '') ?>"></div>
                    <div class="col-md-8"><label class="form-label">Configuration Notes</label><input name="settings_note" class="form-control" value="<?= esc($settings['settings_note'] ?? '') ?>" placeholder="Internal branch-level notes"></div>
                </div>
            </div>

            <div class="tab-pane fade" id="appearance"><div class="row g-3"><div class="col-md-4"><label class="form-label">Theme</label><select name="theme_mode" class="form-select"><option value="light" <?= ($settings['theme_mode'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option><option value="dark" <?= ($settings['theme_mode'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Dark</option></select></div><div class="col-md-4"><label class="form-label">Application Font Size (px)</label><input name="pos_font_size_px" type="number" class="form-control" min="10" max="24" step="0.5" value="<?= esc($settings['pos_font_size_px'] ?? 14) ?>"></div><div class="col-md-4"><label class="form-label">Font Style</label><select name="font_family" class="form-select"><?php foreach(['system'=>'System Default','arial'=>'Arial','verdana'=>'Verdana','tahoma'=>'Tahoma','trebuchet'=>'Trebuchet MS','georgia'=>'Georgia','times'=>'Times New Roman'] as $value=>$label): ?><option value="<?= esc($value) ?>" <?= ($settings['font_family'] ?? 'system') === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Primary Color</label><input name="primary_color" type="color" class="form-control form-control-color" value="<?= esc($settings['primary_color'] ?? '#1b2a3a') ?>"></div><div class="col-md-4"><label class="form-label">Accent Color</label><input name="accent_color" type="color" class="form-control form-control-color" value="<?= esc($settings['accent_color'] ?? '#ff7a1a') ?>"></div><div class="col-12"><div class="alert alert-light border mb-0">These appearance settings apply to the whole application for this branch. Font size is also used by POS; there is no separate POS-only font setting.</div></div></div></div>
            <div class="tab-pane fade" id="pos">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Default POS Transaction Type</label>
                        <select name="default_transaction_type" class="form-select">
                            <?php foreach(\App\Models\ShopSettingsModel::TRANSACTION_TYPE_LABELS as $value=>$label): ?>
                                <option value="<?= esc($value) ?>" <?= ($settings['default_transaction_type'] ?? 'gas_sale') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">This value is selected automatically when the POS opens. It must be one of the visible transaction types.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Default Gas Entry</label>
                        <select name="default_gas_entry_mode" class="form-select">
                            <option value="quantity" <?= ($settings['default_gas_entry_mode'] ?? 'quantity') === 'quantity' ? 'selected' : '' ?>>KG</option>
                            <option value="amount" <?= ($settings['default_gas_entry_mode'] ?? 'quantity') === 'amount' ? 'selected' : '' ?>>Amount</option>
                            <option value="cylinders" <?= ($settings['default_gas_entry_mode'] ?? 'quantity') === 'cylinders' ? 'selected' : '' ?>>Cylinders</option>
                        </select>
                        <div class="form-text">The selected entry method will be preselected whenever Gas Sale / Refill opens on the POS. Cashiers can still change it for an individual sale.</div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">POS Transaction Type Visibility</label>
                        <?php $visibleTypes = (new \App\Models\ShopSettingsModel())->visibleTransactionTypes($settings); ?>
                        <div class="border rounded p-3">
                            <div class="row g-2">
                                <?php foreach(\App\Models\ShopSettingsModel::TRANSACTION_TYPE_LABELS as $value=>$label): ?>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                name="pos_visible_transaction_types[]"
                                                id="posVisible<?= esc($value) ?>"
                                                value="<?= esc($value) ?>"
                                                <?= in_array($value, $visibleTypes, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="posVisible<?= esc($value) ?>"><?= esc($label) ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-text">Only transaction types enabled here will appear in the POS Transaction Type dropdown for this shop. At least one type must remain visible.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Source Filled Cylinder Selection on POS</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="allow_pos_source_cylinder_selection" id="allowPosSourceCylinderSelection" <?= !empty($settings['allow_pos_source_cylinder_selection']) ? 'checked' : '' ?> <?= empty($settings['individual_cylinder_tracking']) ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="allowPosSourceCylinderSelection">Allow user to select the source filled cylinder on POS</label>
                        </div>
                        <div class="form-text">Requires Individual Cylinder Tracking. ON: cashier selects a physical source cylinder. OFF: system automatically allocates available physical cylinders.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Individual Cylinder Tracking</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="individual_cylinder_tracking" id="individualCylinderTracking" <?= !empty($settings['individual_cylinder_tracking']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="individualCylinderTracking">Track individual physical cylinders in normal POS sales</label>
                        </div>
                        <div class="form-text">ON: the system maintains stock at physical-cylinder level, including each cylinder's gas quantity/status. OFF: stock remains managed at cylinder-type level. Enable this before enabling Source Filled Cylinder Selection on POS.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Security Deposit + Sale Payment Allocation</label>
                        <select name="deposit_payment_allocation_rule" id="depositPaymentAllocationRule" class="form-select">
                            <option value="gas_first" <?= ($settings['deposit_payment_allocation_rule'] ?? 'deposit_first') === 'gas_first' ? 'selected' : '' ?>>Gas / Cylinder Sale First</option>
                            <option value="deposit_first" <?= ($settings['deposit_payment_allocation_rule'] ?? 'gas_first') === 'deposit_first' ? 'selected' : '' ?>>Security Deposit First</option>
                            <option value="manual" <?= ($settings['deposit_payment_allocation_rule'] ?? 'gas_first') === 'manual' ? 'selected' : '' ?>>Manual Allocation</option>
                        </select>
                        <div class="form-text">When one payment is received for both amounts, Security Deposit is allocated first by default, then the remaining amount is applied to Gas / Cylinder and OS. Manual Allocation lets the cashier enter each allocation.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Credit Limit Validation</label>
                        <select name="credit_limit_validation_mode" id="creditLimitMode" class="form-select">
                            <option value="none" <?= ($settings['credit_limit_validation_mode'] ?? 'none') === 'none' ? 'selected' : '' ?>>None — no credit limit validation</option>
                            <option value="customer" <?= ($settings['credit_limit_validation_mode'] ?? 'none') === 'customer' ? 'selected' : '' ?>>Customer Level — use each customer's Credit Limit</option>
                            <option value="shop" <?= ($settings['credit_limit_validation_mode'] ?? 'none') === 'shop' ? 'selected' : '' ?>>Shop Level — use one branch-wide Credit Limit</option>
                        </select>
                        <div class="form-text">A limit of Rs. 0.00 means the customer must fully settle the current sale and any previous OS.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Shop Credit Limit</label>
                        <input name="shop_credit_limit" id="shopCreditLimit" type="number" min="0" step="any" class="form-control" value="<?= esc($settings['shop_credit_limit'] ?? 0) ?>">
                        <div class="form-text">Used only when Credit Limit Validation is set to Shop Level.</div>
                    </div>
                    <div class="col-12"><div class="alert alert-light border mb-0">Partial payment is allowed. Any unpaid amount becomes Customer OS and is carried into the next sale. Credit-limit validation applies to the resulting outstanding balance.</div></div>
                    <div class="col-12"><div class="alert alert-info border mb-0"><strong>POS transaction types:</strong> Visibility is configurable above per shop.  Gas Sale / Refill, Cylinder Sale, Security Deposit / Issue Cylinder, and Cylinder Return / Refund Deposit. The selected default is used when opening a new POS invoice.</div></div>
                </div>
            </div>

            <div class="tab-pane fade" id="inventory">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Stock Validation at Sale</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="stock_validation_enabled" id="stockValidation" <?= !empty($settings['stock_validation_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="stockValidation">Prevent sales above available gas stock</label>
                        </div>
                        <div class="form-text">The existing cylinder-type policy override remains available under Inventory Controls.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Allow Stock Override Confirmation</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="allow_stock_override" id="stockOverride" <?= !empty($settings['allow_stock_override']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="stockOverride">Allow authorized users to confirm an over-stock gas sale when validation is OFF</label>
                        </div>
                        <div class="form-text">The confirmation is recorded with the sale request. Keep this disabled for strict stock control.</div>
                    </div>
                    <div class="col-12"><div class="alert alert-info mb-0"><strong>Important:</strong> Cylinder-type overrides are managed separately in Inventory Controls so branch defaults and per-cylinder exceptions remain clear.</div></div>
                </div>
            </div>

            <div class="tab-pane fade" id="custody">
                <div class="row g-3">
                    <div class="col-12"><div class="alert alert-info mb-0"><strong>Cylinder Custody:</strong> Configure how temporary cylinder issues, returns, gas recovery and security deposits affect stock and party OS.</div></div>
                    <div class="col-md-6">
                        <label class="form-label">Include Security Deposit in Party OS</label>
                        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="include_security_deposit_in_os" <?= !empty($settings['include_security_deposit_in_os']) ? 'checked' : '' ?>><label class="form-check-label">Include deposit hold/refund in customer OS</label></div>
                        <div class="form-text">OFF by default. Deposit remains separately tracked either way.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Allow / Show Return Gas Quantity</label>
                        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="allow_return_gas_qty" <?= !empty($settings['allow_return_gas_qty']) ? 'checked' : '' ?>><label class="form-check-label">Allow cashier to enter gas returned with each cylinder</label></div>
                        <div class="form-text">OFF by default. Return gas is then forced to 0.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Return Gas Affects Party OS</label>
                        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="return_gas_affects_os" <?= !empty($settings['return_gas_affects_os']) ? 'checked' : '' ?>><label class="form-check-label">Post returned-gas value as a party OS adjustment</label></div>
                        <div class="form-text">Independent of whether the return-gas quantity field is visible.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Allow Empty-Issued Cylinder Returned With Gas</label>
                        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="allow_empty_issued_return_gas" <?= !empty($settings['allow_empty_issued_return_gas']) ? 'checked' : '' ?>><label class="form-check-label">Permit gas on return when it was issued empty</label></div>
                        <div class="form-text">When enabled, the POS asks for confirmation before saving.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Allow Return Gas Greater Than Issued</label>
                        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="allow_return_gas_over_issued" <?= !empty($settings['allow_return_gas_over_issued']) ? 'checked' : '' ?>><label class="form-check-label">Permit returned gas to exceed historical issued gas</label></div>
                        <div class="form-text">When enabled, the POS asks for confirmation before saving.</div>
                    </div>
                    <div class="col-12"><div class="alert alert-warning mb-0">All five options are disabled by default. A disabled rule rejects the complete transaction; the system never silently changes a disallowed return quantity.</div></div>
                </div>
            </div>

            <div class="tab-pane fade" id="security">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Purchase Void</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="purchase_void_enabled" id="purchaseVoidEnabled" <?= !empty($settings['purchase_void_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="purchaseVoidEnabled">Enable Purchase Void for this branch</label>
                        </div>
                        <div class="form-text">When OFF, nobody can void a posted purchase. When ON, only the users selected below can use Purchase Void.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Users Allowed to Void Purchases</label>
                        <select name="purchase_void_user_ids[]" id="purchaseVoidUsers" class="form-select" multiple size="6">
                            <?php foreach (($assignableUsers ?? []) as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= in_array((int) $u['id'], ($assignedPurchaseVoidUsers ?? []), true) ? 'selected' : '' ?>>
                                    <?= esc($u['full_name'] . ' (' . $u['username'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Explicit user assignment. Having a role such as ADMIN or MANAGER does not automatically grant Purchase Void access.</div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">
                            <strong>Important:</strong> Purchase Void reverses stock, cash and supplier-ledger effects. Assign this function only to trusted users.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="cash">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Default Payment Mode</label><select name="default_payment_mode" class="form-select"><?php foreach(['cash'=>'Cash','cheque'=>'Cheque','online'=>'Online','credit'=>'Credit'] as $value=>$label): ?><option value="<?= esc($value) ?>" <?= ($settings['default_payment_mode'] ?? 'cash') === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select><div class="form-text">New POS payment lines use this mode by default.</div></div>
                    <div class="col-md-6"><label class="form-label">Counter Cash</label><div class="form-control bg-light">Open cash session is required for cash sales and cash movements.</div></div>
                    <div class="col-12"><div class="alert alert-light border mb-0">Cash, cheque, online and credit remain supported. Non-cash payments do not increase Counter Cash.</div></div>
                </div>
            </div>

            <div class="tab-pane fade" id="receipt">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Receipt Title</label><input name="receipt_title" class="form-control" value="<?= esc($settings['receipt_title'] ?? 'SALE RECEIPT') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Show Shop Address</label><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="show_address_on_receipt" <?= !empty($settings['show_address_on_receipt']) ? 'checked' : '' ?>><label class="form-check-label">Print branch address on receipts</label></div></div>
                    <div class="col-12"><label class="form-label">Receipt Footer</label><textarea name="receipt_footer" class="form-control" rows="3"><?= esc($settings['receipt_footer'] ?? 'Thank you') ?></textarea></div>
                </div>
            </div>

            <div class="tab-pane fade" id="backup">
                <div class="alert alert-warning"><strong>Security:</strong> Store a backup endpoint or URL only. Do not put database usernames, passwords or API secrets into this field.</div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Backup Enabled</label><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="backup_enabled" <?= !empty($settings['backup_enabled']) ? 'checked' : '' ?>><label class="form-check-label">Enable backup integration setting</label></div></div>
                    <div class="col-md-8"><label class="form-label">Database Backup URL / Endpoint</label><input name="db_backup_url" type="url" class="form-control" value="<?= esc($settings['db_backup_url'] ?? '') ?>" placeholder="https://backup.example.com/..."></div>
                    <div class="col-12"><label class="form-label">Backup Notes</label><textarea name="backup_notes" class="form-control" rows="4" placeholder="Backup schedule, provider notes, retention policy, etc."><?= esc($settings['backup_notes'] ?? '') ?></textarea></div>
                    <div class="col-12"><div class="alert alert-light border mb-0">This setting stores the backup endpoint for future backup integration. The POS does not execute the URL automatically.</div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end">
        <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Shop Settings</button>
    </div>
</div>
</form>
<script>
document.getElementById('individualCylinderTracking')?.addEventListener('change', function () {
    const source = document.getElementById('allowPosSourceCylinderSelection');
    if (!source) return;
    source.disabled = !this.checked;
    if (!this.checked) source.checked = false;
});
</script>
<?= $this->endSection() ?>