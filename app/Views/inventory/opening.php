<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <h4 class="mb-0">Opening Inventory</h4>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="<?= site_url('inventory/opening/template') ?>">
            Download Excel Template
        </a>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#excelImportForm">
            Upload Excel
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#form" onclick="prepareAdd()">
            Add Opening
        </button>
    </div>
</div>

<div class="alert alert-info py-2">
    Excel imports use <strong>today's date</strong>. Each Excel row stays separate and creates its own physical cylinders.
    Existing cylinder types with a capacity mismatch are flagged for confirmation; the existing database capacity is retained.
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Cylinder</th>
                    <th>Quantity</th>
                    <th>Gas Stock (KG)</th>
                    <th>Comments</th>
                    <th class="text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= esc($r['inventory_date']) ?></td>
                    <td>
                        <?php
                        $openingAvgGas = (float) ($r['quantity'] ?? 0) > 0
                            ? (float) ($r['gas_stock'] ?? 0) / (float) $r['quantity']
                            : 0;
                        $openingTypeLabel = ucwords(str_replace('_', ' ', $r['inventory_type']));
                        if (($r['inventory_type'] ?? '') === 'filled_cylinder'
                            && (float) ($r['capacity_kg'] ?? 0) > 0
                            && $openingAvgGas > 0
                            && $openingAvgGas < (float) $r['capacity_kg'] - 0.00001) {
                            $openingTypeLabel = 'Partially Filled Cylinder';
                        } elseif (($r['inventory_type'] ?? '') === 'filled_cylinder') {
                            $openingTypeLabel = 'Full Filled Cylinder';
                        }
                        ?>
                        <?= esc($openingTypeLabel) ?>
                    </td>
                    <td><?= esc(trim(($r['cylinder_code'] ?? '') . ' ' . ($r['cylinder_name'] ?? '')) ?: '—') ?></td>
                    <td><?= number_format((float) $r['quantity'], 0) ?></td>
                    <td><?= number_format((float) ($r['gas_stock'] ?? 0), 2) ?></td>
                    <td><?= esc($r['comments'] ?? '') ?></td>
                    <td class="text-nowrap">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#form"
                            onclick='prepareEdit(<?= json_encode([
                                "id" => (int) $r["id"],
                                "date" => $r["inventory_date"],
                                "type" => $r["inventory_type"],
                                "cylinderTypeId" => $r["cylinder_type_id"],
                                "quantity" => (float) $r["quantity"],
                                "gasStock" => (float) ($r["gas_stock"] ?? 0),
                                "capacityKg" => (float) ($r["capacity_kg"] ?? 0),
                                "comments" => $r["comments"] ?? "",
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>
                            Edit
                        </button>
                        <form method="post" action="<?= site_url('inventory/opening/delete/' . $r['id']) ?>" class="d-inline" onsubmit="return confirm('Delete this opening inventory record? This action will be recorded in the Audit Log.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="excelImportForm" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= site_url('inventory/opening/import') ?>" enctype="multipart/form-data" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title">Upload Opening Inventory Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Excel File (.xlsx)</label>
                    <input type="file" name="opening_inventory_file" class="form-control" accept=".xlsx" required>
                    <div class="form-text">
                        Required columns: Cylinder Type, Quantity, Max GAS Capacity, Available Gas.
                    </div>
                </div>

                <div class="alert alert-warning mb-3">
                    If an existing cylinder type has a different capacity in Excel, the import will be flagged.
                    Enable the confirmation below only after reviewing the mismatch. The existing database capacity will be used; it will not be changed by the import.
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" name="capacity_mismatch_confirmed" id="capacityMismatchConfirmed">
                    <label class="form-check-label" for="capacityMismatchConfirmed">
                        Confirm capacity mismatches and use the existing database capacity.
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?= site_url('inventory/opening/template') ?>" class="btn btn-outline-secondary">Download Template</a>
                <button type="submit" class="btn btn-primary">Validate &amp; Import</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="form" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= site_url('inventory/opening/save') ?>" class="modal-content" onsubmit="syncOpeningFields()">
            <?= csrf_field() ?>
            <input type="hidden" name="opening_id" id="openingId">
            <input type="hidden" name="cylinder_type_id" id="editCylinderTypeId">

            <div class="modal-header">
                <h5 id="formTitle">Add Opening Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Inventory Date</label>
                    <input name="inventory_date" id="inventoryDate" type="date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cylinder Status</label>
                    <input id="cylinderStatus" type="text" class="form-control" value="Empty" readonly>
                    <div class="form-text">Calculated automatically from gas quantity and cylinder capacity.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cylinder Type</label>
                    <select id="cylinderTypeId" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" data-capacity="<?= esc($t['capacity_kg']) ?>"><?= esc($t['code'] . ' — ' . $t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <input name="quantity" id="quantity" type="number" min="0" step="1" class="form-control" required>
                </div>

                <div class="mb-3" id="gasWeightWrap">
                    <label class="form-label">Gas Quantity per Cylinder (KG)</label>
                    <input name="actual_gas_weight_kg" id="actualGasWeight" type="number" min="0" step="0.001" class="form-control" required>
                    <div class="form-text" id="gasWeightHelp">0 = Empty, less than capacity = Partially Filled, equal to capacity = Full.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Comments</label>
                    <textarea name="comments" id="comments" maxlength="500" rows="3" class="form-control" placeholder="Reason, opening stock note, adjustment reference, etc."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save Opening Inventory</button>
            </div>
        </form>
    </div>
</div>

<script>
function syncOpeningFields() {
    document.getElementById('editCylinderTypeId').value =
        document.getElementById('cylinderTypeId').value || '';
}

function updateCylinderStatus() {
    const selected = document.getElementById('cylinderTypeId').selectedOptions[0];
    const capacity = selected && selected.dataset.capacity ? Number(selected.dataset.capacity) : 0;
    const gasInput = document.getElementById('actualGasWeight');
    const gas = gasInput.value === '' ? 0 : Number(gasInput.value);
    const status = document.getElementById('cylinderStatus');

    if (capacity > 0 && gas > capacity) {
        gasInput.setCustomValidity('Gas quantity cannot exceed cylinder capacity.');
        status.value = 'Invalid — exceeds capacity';
        return;
    }

    gasInput.setCustomValidity('');
    status.value = gas <= 0.00001
        ? 'Empty'
        : (capacity > 0 && gas < capacity - 0.00001 ? 'Partially Filled' : 'Full');
}

function prepareAdd() {
    document.getElementById('formTitle').textContent = 'Add Opening Inventory';
    document.getElementById('openingId').value = '';
    document.getElementById('inventoryDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('cylinderTypeId').value = '';
    document.getElementById('quantity').value = '';
    document.getElementById('actualGasWeight').value = '0';
    document.getElementById('comments').value = '';
    document.getElementById('cylinderTypeId').disabled = false;
    document.getElementById('actualGasWeight').disabled = false;
    document.getElementById('quantity').disabled = false;
    syncOpeningFields();
    updateCylinderStatus();
}

function prepareEdit(row) {
    document.getElementById('formTitle').textContent = 'Edit Opening Inventory';
    document.getElementById('openingId').value = row.id;
    document.getElementById('inventoryDate').value = row.date;
    document.getElementById('cylinderTypeId').value = row.cylinderTypeId;
    document.getElementById('quantity').value = row.quantity;

    const averageGas = row.quantity > 0 ? Number(row.gasStock || 0) / Number(row.quantity) : 0;
    document.getElementById('actualGasWeight').value = averageGas.toFixed(2);
    document.getElementById('comments').value = row.comments || '';

    document.getElementById('cylinderTypeId').disabled = true;
    document.getElementById('actualGasWeight').disabled = false;
    document.getElementById('quantity').disabled = false;
    syncOpeningFields();
    updateCylinderStatus();
}

document.getElementById('cylinderTypeId')?.addEventListener('change', function () {
    syncOpeningFields();
    updateCylinderStatus();
});
document.getElementById('actualGasWeight')?.addEventListener('input', updateCylinderStatus);
document.getElementById('actualGasWeight')?.addEventListener('change', updateCylinderStatus);
updateCylinderStatus();
</script>
<?= $this->endSection() ?>
