<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$statusBadge = static fn(string $s): string => match ($s) {
    'in_transit' => '<span class="badge text-bg-warning">In transit</span>',
    'received' => '<span class="badge text-bg-success">Received</span>',
    'cancelled' => '<span class="badge text-bg-secondary">Cancelled</span>',
    default => '<span class="badge text-bg-light border">' . esc($s) . '</span>',
};
$businessId = \App\Services\BusinessContext::businessIdForLocation($locationId);
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">Branch Stock Transfers</h4>
        <p class="text-muted mb-0">Move physical cylinders to another branch of this business. Each cylinder keeps its code and actual gas weight.</p>
    </div>
    <?php if ($enabled): ?>
        <span class="badge text-bg-light border align-self-center">
            <?= $flow === 'one_step' ? 'One-step flow: stock moves immediately' : 'Two-step flow: dispatch, then the receiving branch confirms' ?>
        </span>
    <?php endif; ?>
</div>

<?php if (!$enabled): ?>
    <div class="card mb-3">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <i class="bi bi-info-circle fs-4 text-primary"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold">Stock transfer is turned off for this business.</div>
                <div class="text-muted small">Turn on <em>Inter-branch stock transfer</em> and choose the transfer flow in the business configuration. Incoming and past transfers are still listed below.</div>
            </div>
            <?php if ($canConfigure): ?>
                <a class="btn btn-outline-primary" href="<?= site_url('configuration/business/' . $businessId) ?>"><i class="bi bi-gear me-1"></i>Business configuration</a>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (!$destinations): ?>
    <div class="alert alert-info">This business has no other active branch to transfer to.</div>
<?php else: ?>
    <form method="post" action="<?= site_url('inventory/transfers/create') ?>" class="card mb-4" id="transferForm">
        <?= csrf_field() ?>
        <div class="card-header fw-semibold"><i class="bi bi-arrow-left-right me-1"></i>New transfer</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="to_location_id">Send to branch <span class="text-danger">*</span></label>
                    <select class="form-select" id="to_location_id" name="to_location_id" required>
                        <option value="">Choose branch…</option>
                        <?php foreach ($destinations as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (string) old('to_location_id') === (string) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?><?= $d['city'] ? ' — ' . esc($d['city']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <?= view('partials/transaction_date', ['dateFieldClass' => '']) ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="notes">Notes</label>
                    <input class="form-control" id="notes" name="notes" maxlength="500" value="<?= esc(old('notes') ?? '') ?>" placeholder="Vehicle, driver, reason…">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mt-4 mb-2">
                <div class="fw-semibold">Cylinders in this branch's stock</div>
                <div class="d-flex gap-2 flex-wrap">
                    <select class="form-select form-select-sm" id="filterType" aria-label="Filter by cylinder type" style="width:auto">
                        <option value="">All types</option>
                        <?php foreach (array_unique(array_map(static fn($u) => $u['cylinder_code'] . '|' . $u['cylinder_name'], $units)) as $t): [$code, $name] = explode('|', $t, 2); ?>
                            <option value="<?= esc($code) ?>"><?= esc($code . ' — ' . $name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" id="filterStatus" aria-label="Filter by filled or empty" style="width:auto">
                        <option value="">Filled and empty</option>
                        <option value="filled">Filled only</option>
                        <option value="empty">Empty only</option>
                    </select>
                </div>
            </div>
            <?php if (!$units): ?>
                <div class="text-muted border rounded p-3 text-center">No filled or empty cylinders are in this branch's stock.</div>
            <?php else: ?>
                <div class="table-responsive border rounded" style="max-height:360px">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="sticky-top"><tr>
                            <th style="width:2.5rem"><input class="form-check-input" type="checkbox" id="selectAll" aria-label="Select all visible cylinders"></th>
                            <th>Cylinder</th><th>Type</th><th>Status</th><th class="text-end">Gas (KG)</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($units as $u): ?>
                            <tr class="unit-row" data-type="<?= esc($u['cylinder_code']) ?>" data-status="<?= esc($u['status']) ?>" data-gas="<?= esc((string) $u['gas_weight_kg']) ?>">
                                <td><input class="form-check-input unit-check" type="checkbox" name="unit_ids[]" value="<?= (int) $u['id'] ?>" aria-label="Select <?= esc($u['unit_code']) ?>"></td>
                                <td class="fw-semibold"><?= esc($u['unit_code']) ?></td>
                                <td><?= esc($u['cylinder_code'] . ' — ' . $u['cylinder_name']) ?></td>
                                <td><?= $u['status'] === 'filled' ? '<span class="badge text-bg-primary">Filled</span>' : '<span class="badge text-bg-light border">Empty</span>' ?></td>
                                <td class="text-end"><?= number_format((float) $u['gas_weight_kg'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted" id="selectionSummary">No cylinders selected.</div>
            <button class="btn btn-primary" type="submit" id="submitTransfer" disabled>
                <i class="bi bi-send me-1"></i><?= $flow === 'one_step' ? 'Post transfer' : 'Dispatch transfer' ?>
            </button>
        </div>
    </form>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Transfers for this branch</span>
        <form method="get" class="d-flex gap-2 flex-wrap">
            <select name="direction" class="form-select form-select-sm" style="width:auto" aria-label="Direction">
                <option value="">Sent and received</option>
                <option value="outgoing" <?= $filters['direction'] === 'outgoing' ? 'selected' : '' ?>>Sent from this branch</option>
                <option value="incoming" <?= $filters['direction'] === 'incoming' ? 'selected' : '' ?>>Coming to this branch</option>
            </select>
            <select name="status" class="form-select form-select-sm" style="width:auto" aria-label="Status">
                <option value="">Any status</option>
                <option value="in_transit" <?= $filters['status'] === 'in_transit' ? 'selected' : '' ?>>In transit</option>
                <option value="received" <?= $filters['status'] === 'received' ? 'selected' : '' ?>>Received</option>
                <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
        </form>
    </div>
    <div class="card-body table-responsive">
        <?php if (!$transfers): ?>
            <div class="text-muted text-center py-4">No transfers yet.</div>
        <?php else: ?>
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Transfer</th><th>Date</th><th>From</th><th>To</th><th class="text-end">Cylinders</th><th class="text-end">Gas (KG)</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($transfers as $t): $incoming = (int) $t['to_location_id'] === $locationId; ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($t['transfer_no']) ?></td>
                        <td><?= esc(date('d-M-Y h:i A', strtotime($t['transfer_at']))) ?></td>
                        <td><?= esc($t['from_name']) ?></td>
                        <td><?= esc($t['to_name']) ?></td>
                        <td class="text-end"><?= (int) $t['unit_count'] ?></td>
                        <td class="text-end"><?= number_format((float) $t['gas_kg'], 2) ?></td>
                        <td><?= $statusBadge($t['status']) ?><?= $incoming && $t['status'] === 'in_transit' ? ' <span class="badge text-bg-danger">Awaiting your receipt</span>' : '' ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('inventory/transfers/' . $t['id']) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
    const rows = [...document.querySelectorAll('.unit-row')];
    const type = document.getElementById('filterType');
    const status = document.getElementById('filterStatus');
    const all = document.getElementById('selectAll');
    const summary = document.getElementById('selectionSummary');
    const submit = document.getElementById('submitTransfer');
    if (!rows.length || !summary) return;

    const refresh = () => {
        const checked = rows.filter(r => r.querySelector('.unit-check').checked);
        const filled = checked.filter(r => r.dataset.status === 'filled').length;
        const gas = checked.reduce((sum, r) => sum + Number(r.dataset.gas || 0), 0);
        summary.textContent = checked.length
            ? `${checked.length} cylinder(s) selected — ${filled} filled, ${checked.length - filled} empty, ${gas.toFixed(2)} KG gas.`
            : 'No cylinders selected.';
        submit.disabled = checked.length === 0;
    };
    const applyFilter = () => {
        rows.forEach(r => {
            const show = (!type.value || r.dataset.type === type.value) && (!status.value || r.dataset.status === status.value);
            r.classList.toggle('d-none', !show);
        });
        all.checked = false;
    };
    rows.forEach(r => r.querySelector('.unit-check').addEventListener('change', refresh));
    type.addEventListener('change', applyFilter);
    status.addEventListener('change', applyFilter);
    all.addEventListener('change', () => {
        rows.filter(r => !r.classList.contains('d-none')).forEach(r => { r.querySelector('.unit-check').checked = all.checked; });
        refresh();
    });
    document.getElementById('transferForm').addEventListener('submit', e => {
        const to = document.getElementById('to_location_id');
        const label = to.options[to.selectedIndex]?.text || '';
        if (!confirm(`Transfer the selected cylinders to ${label}?`)) e.preventDefault();
    });
})();
</script>
<?= $this->endSection() ?>
