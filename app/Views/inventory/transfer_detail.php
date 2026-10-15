<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$t = $transfer;
$filled = array_filter($t['items'], static fn($i) => $i['unit_status'] === 'filled');
$gas = array_sum(array_map(static fn($i) => (float) $i['gas_weight_kg'], $t['items']));
$badge = match ($t['status']) {
    'in_transit' => '<span class="badge text-bg-warning fs-6">In transit</span>',
    'received' => '<span class="badge text-bg-success fs-6">Received</span>',
    default => '<span class="badge text-bg-secondary fs-6">Cancelled</span>',
};
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">Transfer <?= esc($t['transfer_no']) ?> <?= $badge ?></h4>
        <p class="text-muted mb-0"><?= esc($t['from_name']) ?> <i class="bi bi-arrow-right"></i> <?= esc($t['to_name']) ?> · <?= $t['flow'] === 'one_step' ? 'One-step transfer' : 'Two-step transfer' ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= site_url('inventory/transfers') ?>"><i class="bi bi-arrow-left me-1"></i>All transfers</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Cylinders</div><div class="fs-4 fw-semibold"><?= count($t['items']) ?></div><div class="small text-muted"><?= count($filled) ?> filled · <?= count($t['items']) - count($filled) ?> empty</div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Gas in transfer</div><div class="fs-4 fw-semibold"><?= number_format($gas, 2) ?> KG</div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Sent</div><div class="fw-semibold"><?= esc(date('d-M-Y h:i A', strtotime($t['transfer_at']))) ?></div><div class="small text-muted">by <?= esc($t['created_by_name'] ?? '—') ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <?php if ($t['status'] === 'received'): ?>
            <div class="text-muted small">Received</div><div class="fw-semibold"><?= esc(date('d-M-Y h:i A', strtotime($t['received_at']))) ?></div><div class="small text-muted">by <?= esc($t['received_by_name'] ?? '—') ?></div>
        <?php elseif ($t['status'] === 'cancelled'): ?>
            <div class="text-muted small">Cancelled</div><div class="fw-semibold"><?= esc(date('d-M-Y h:i A', strtotime($t['cancelled_at']))) ?></div><div class="small text-muted">by <?= esc($t['cancelled_by_name'] ?? '—') ?>: <?= esc($t['cancel_reason'] ?? '') ?></div>
        <?php else: ?>
            <div class="text-muted small">Waiting for</div><div class="fw-semibold"><?= esc($t['to_name']) ?> to confirm receipt</div>
        <?php endif; ?>
    </div></div></div>
</div>

<?php if ($t['notes']): ?><div class="alert alert-light border"><i class="bi bi-chat-left-text me-1"></i><?= esc($t['notes']) ?></div><?php endif; ?>

<?php if ($t['status'] === 'in_transit' && ($isReceiver || $isSender)): ?>
    <div class="card mb-3 border-warning">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <?php if ($isReceiver): ?>
                <div><div class="fw-semibold">Check the cylinders against this list before confirming.</div><div class="small text-muted">Confirming adds them to this branch's stock with the gas weights shown.</div></div>
                <form method="post" action="<?= site_url('inventory/transfers/' . (int) $t['id'] . '/receive') ?>" onsubmit="return confirm('Confirm that all <?= count($t['items']) ?> cylinder(s) were received?');">
                    <?= csrf_field() ?>
                    <button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-down me-1"></i>Confirm receipt</button>
                </form>
            <?php else: ?>
                <div><div class="fw-semibold">Cancel this transfer?</div><div class="small text-muted">Possible only before the receiving branch confirms. The cylinders return to this branch's stock.</div></div>
                <form method="post" action="<?= site_url('inventory/transfers/' . (int) $t['id'] . '/cancel') ?>" class="d-flex gap-2 flex-wrap" onsubmit="return confirm('Cancel this transfer and return the cylinders to stock?');">
                    <?= csrf_field() ?>
                    <input class="form-control" name="cancel_reason" placeholder="Reason for cancelling" required maxlength="255" style="min-width:16rem">
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-x-circle me-1"></i>Cancel transfer</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header fw-semibold">Cylinders</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Cylinder</th><th>Type</th><th>Status when sent</th><th class="text-end">Gas (KG)</th><th class="text-end">Capacity (KG)</th></tr></thead>
            <tbody>
            <?php foreach ($t['items'] as $i): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($i['unit_code']) ?></td>
                    <td><?= esc($i['cylinder_code'] . ' — ' . $i['cylinder_name']) ?></td>
                    <td><?= $i['unit_status'] === 'filled' ? '<span class="badge text-bg-primary">Filled</span>' : '<span class="badge text-bg-light border">Empty</span>' ?></td>
                    <td class="text-end"><?= number_format((float) $i['gas_weight_kg'], 2) ?></td>
                    <td class="text-end"><?= number_format((float) $i['capacity_kg'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
