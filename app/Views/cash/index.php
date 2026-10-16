<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$money = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
$label = static fn (?string $t): string => ucwords(str_replace('_', ' ', (string) $t));
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Counter Cash</h1>
        <p class="page-subtitle">Open the drawer with a float, record cash taken in or out, and count the drawer to close the session.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= site_url('cash/history') ?>"><i class="bi bi-clock-history me-1"></i>Cash History</a>
    </div>
</div>

<?php if (!$session): ?>
<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="<?= site_url('cash/open') ?>" class="card h-100">
            <?= csrf_field() ?>
            <div class="card-header d-flex align-items-center gap-2"><span class="status status-warning">Closed</span> Open a cash session</div>
            <div class="card-body">
                <p class="text-muted">Cash sales, receipts and cash expenses need an open session. Count the notes and coins in the drawer and enter them as the opening float.</p>
                <div class="mb-3">
                    <label class="form-label required" for="openingCash">Opening float</label>
                    <div class="input-group"><span class="input-group-text">Rs.</span><input class="form-control form-control-lg" id="openingCash" name="opening_cash" type="number" min="0" step="0.01" value="<?= esc(old('opening_cash') ?? '0') ?>" required inputmode="decimal"></div>
                </div>
                <div>
                    <label class="form-label" for="openNotes">Notes</label>
                    <input class="form-control" id="openNotes" name="notes" maxlength="255" value="<?= esc(old('notes') ?? '') ?>" placeholder="Optional">
                </div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-unlock me-1"></i>Open session</button></div>
        </form>
    </div>
</div>
<?php else: ?>
<?php
$opening = (float) $summary['session']['opening_cash'];
$otherIn = max(0, (float) $summary['cash_in'] - $opening);
$expected = (float) $summary['expected'];
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="status status-success">Open</span>
    <span class="text-muted small"><?= esc($session['register_code'] . ' — ' . $session['register_name']) ?> · opened <?= esc(date('d M Y, h:i A', strtotime($session['opened_at']))) ?></span>
</div>
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="kpi"><div class="kpi-label">Opening float <i class="bi bi-safe"></i></div><div class="kpi-value"><?= $money($opening) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi is-success"><div class="kpi-label">Cash in <i class="bi bi-arrow-down-left"></i></div><div class="kpi-value"><?= $money($otherIn) ?></div><div class="kpi-hint">Sales, receipts and manual cash in</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi is-danger"><div class="kpi-label">Cash out <i class="bi bi-arrow-up-right"></i></div><div class="kpi-value"><?= $money($summary['cash_out']) ?></div><div class="kpi-hint">Expenses, refunds, voids and manual cash out</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi is-info"><div class="kpi-label">Expected in drawer <i class="bi bi-cash-stack"></i></div><div class="kpi-value"><?= $money($expected) ?></div><div class="kpi-hint">Float + cash in − cash out</div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-5 d-flex flex-column gap-3">
        <form method="post" action="<?= site_url('cash/close') ?>" class="card" id="closeCashForm" data-confirm="Close this cash session? Cash sales cannot be posted until a new session is opened." data-confirm-ok="Close session" data-confirm-title="Close cash session">
            <?= csrf_field() ?>
            <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
            <div class="card-header">Count the drawer and close</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required" for="countedCash">Counted cash</label>
                    <div class="input-group"><span class="input-group-text">Rs.</span><input class="form-control form-control-lg" id="countedCash" name="counted_cash" type="number" min="0" step="0.01" required inputmode="decimal" placeholder="<?= number_format($expected, 2, '.', '') ?>" data-expected="<?= number_format($expected, 2, '.', '') ?>"></div>
                    <div class="form-text" id="cashDifference" aria-live="polite">Expected <?= $money($expected) ?>.</div>
                </div>
                <div>
                    <label class="form-label" for="closeNotes">Closing notes</label>
                    <input class="form-control" id="closeNotes" name="notes" maxlength="255" placeholder="Optional — e.g. reason for a shortage">
                </div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-warning"><i class="bi bi-lock me-1"></i>Close session</button></div>
        </form>

        <form method="post" action="<?= site_url('cash/move') ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header">Manual cash in / out</div>
            <div class="card-body">
                <div class="btn-group w-100 mb-3" role="radiogroup" aria-label="Direction">
                    <input type="radio" class="btn-check" name="direction" id="dirIn" value="in" checked><label class="btn btn-outline-secondary" for="dirIn"><i class="bi bi-arrow-down-left me-1"></i>Cash in</label>
                    <input type="radio" class="btn-check" name="direction" id="dirOut" value="out"><label class="btn btn-outline-secondary" for="dirOut"><i class="bi bi-arrow-up-right me-1"></i>Cash out</label>
                </div>
                <div class="row g-2">
                    <div class="col-sm-5">
                        <label class="form-label required" for="moveAmount">Amount</label>
                        <input class="form-control" id="moveAmount" name="amount" type="number" min=".01" step=".01" required inputmode="decimal">
                    </div>
                    <div class="col-sm-7">
                        <label class="form-label" for="moveNotes">Reason</label>
                        <input class="form-control" id="moveNotes" name="notes" maxlength="255" placeholder="e.g. Change from bank">
                    </div>
                </div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-outline-primary">Post movement</button></div>
        </form>
    </div>

    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><span>This session's movements</span><a class="small" href="<?= site_url('cash/history') ?>">Full history</a></div>
            <?php if (!$movements): ?>
                <div class="empty-state"><i class="bi bi-cash"></i><div class="empty-state-title">No movements yet</div>Cash sales and receipts appear here as they are posted.</div>
            <?php else: ?>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Time</th><th>Movement</th><th>By</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($movements as $m): $in = $m['direction'] === 'in'; ?>
                        <tr>
                            <td class="text-nowrap"><?= esc(date('d M, h:i A', strtotime($m['transaction_at']))) ?></td>
                            <td><?= esc($label($m['transaction_type'])) ?><?php if (!empty($m['notes'])): ?><div class="small text-muted"><?= esc($m['notes']) ?></div><?php endif; ?></td>
                            <td class="small"><?= esc($m['created_by_name'] ?? '') ?></td>
                            <td class="text-end fw-semibold text-nowrap <?= $in ? 'text-success' : 'text-danger' ?>"><?= $in ? '+' : '−' ?> <?= number_format((float) $m['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Live over/short preview while the cashier types the counted amount (the server calculates the final difference).
(() => {
    const input = document.getElementById('countedCash');
    const out = document.getElementById('cashDifference');
    if (!input || !out) return;
    const expected = Number(input.dataset.expected || 0);
    const fmt = (v) => 'Rs. ' + Math.abs(v).toFixed(2);
    input.addEventListener('input', () => {
        if (input.value === '') { out.className = 'form-text'; out.textContent = 'Expected ' + fmt(expected) + '.'; return; }
        const diff = Number(input.value) - expected;
        if (Math.abs(diff) < 0.005) { out.className = 'form-text text-success fw-semibold'; out.textContent = 'Drawer balances with the expected ' + fmt(expected) + '.'; }
        else if (diff > 0) { out.className = 'form-text text-warning fw-semibold'; out.textContent = 'Over by ' + fmt(diff) + ' (expected ' + fmt(expected) + ').'; }
        else { out.className = 'form-text text-danger fw-semibold'; out.textContent = 'Short by ' + fmt(diff) + ' (expected ' + fmt(expected) + ').'; }
    });
})();
</script>
<?= $this->endSection() ?>
