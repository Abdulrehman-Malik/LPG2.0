<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$in = $out = 0.0;
foreach ($rows as $r) {
    if (($r['direction'] ?? '') === 'in') {
        $in += (float) $r['amount'];
    } else {
        $out += (float) $r['amount'];
    }
}
$today = date('Y-m-d');
$presets = [
    'Today' => [$today, $today],
    'Last 7 days' => [date('Y-m-d', strtotime('-6 days')), $today],
    'This month' => [date('Y-m-01'), $today],
    'Last 30 days' => [date('Y-m-d', strtotime('-30 days')), $today],
];
$presetUrl = static fn (string $f, string $t): string => site_url('cash/history') . '?' . http_build_query(array_filter(['from' => $f, 'to' => $t, 'register_id' => $registerId ?: null]));
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Counter Cash History</h1>
        <p class="page-subtitle">Every cash movement across the branch's counters: floats, sales, receipts, expenses, refunds and manual entries.</p>
    </div>
    <div class="page-actions"><a class="btn btn-outline-secondary" href="<?= site_url('cash') ?>"><i class="bi bi-arrow-left me-1"></i>Counter Cash</a></div>
</div>

<form class="card mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label" for="cashFrom">From</label><input type="date" id="cashFrom" name="from" class="form-control" value="<?= esc($from) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="cashTo">To</label><input type="date" id="cashTo" name="to" class="form-control" value="<?= esc($to) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="cashRegister">Counter</label><select id="cashRegister" name="register_id" class="form-select"><option value="">All counters</option><?php foreach ($registers as $reg): ?><option value="<?= esc($reg['id']) ?>" <?= (string) $registerId === (string) $reg['id'] ? 'selected' : '' ?>><?= esc($reg['code'] . ' — ' . $reg['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Apply</button></div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <?php foreach ($presets as $label => [$pf, $pt]): ?>
                <a class="btn btn-sm btn-outline-secondary <?= $from === $pf && $to === $pt ? 'active' : '' ?>" href="<?= esc($presetUrl($pf, $pt)) ?>"><?= esc($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="kpi is-success"><div class="kpi-label">Cash in <i class="bi bi-arrow-down-left"></i></div><div class="kpi-value">Rs. <?= number_format($in, 2) ?></div></div></div>
    <div class="col-sm-4"><div class="kpi is-danger"><div class="kpi-label">Cash out <i class="bi bi-arrow-up-right"></i></div><div class="kpi-value">Rs. <?= number_format($out, 2) ?></div></div></div>
    <div class="col-sm-4"><div class="kpi"><div class="kpi-label">Net movement <i class="bi bi-arrow-left-right"></i></div><div class="kpi-value">Rs. <?= number_format($in - $out, 2) ?></div><div class="kpi-hint"><?= count($rows) ?> movement(s)</div></div></div>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="bi bi-cash"></i><div class="empty-state-title">No cash movements</div>Try a wider date range.</div>
    <?php else: ?>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 datatable">
            <thead><tr><th>Date / Time</th><th>Counter</th><th>Type</th><th>Direction</th><th class="text-end">Amount</th><th>Reference</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $isIn = ($r['direction'] ?? '') === 'in'; ?>
                <tr>
                    <td class="text-nowrap" data-order="<?= esc($r['transaction_at'] ?? '') ?>"><?= esc($r['transaction_at'] ?? '') ?></td>
                    <td><?= esc($r['register_code'] ?? '') ?></td>
                    <td><?= esc(ucwords(str_replace('_', ' ', $r['transaction_type'] ?? ''))) ?></td>
                    <td><span class="status <?= $isIn ? 'status-success' : 'status-danger' ?>"><?= $isIn ? 'IN' : 'OUT' ?></span></td>
                    <td class="text-end fw-semibold">Rs. <?= number_format((float) $r['amount'], 2) ?></td>
                    <td><?= (int) ($r['reference_id'] ?? 0) > 0 ? esc(ucwords(str_replace('_', ' ', (string) $r['reference_type'])) . ' #' . $r['reference_id']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= esc($r['notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
