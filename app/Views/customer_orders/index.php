<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$statusLabels = [
    'pending' => 'Pending review',
    'accepted' => 'Accepted',
    'rejected' => 'Rejected',
    'preparing' => 'Preparing',
    'ready' => 'Ready',
    'out_for_delivery' => 'Out for delivery',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'unable_to_fulfil' => 'Unable to fulfil',
];
$statusClasses = [
    'pending' => 'text-bg-warning',
    'accepted' => 'text-bg-primary',
    'rejected' => 'text-bg-danger',
    'preparing' => 'text-bg-info',
    'ready' => 'text-bg-info',
    'out_for_delivery' => 'text-bg-primary',
    'completed' => 'text-bg-success',
    'cancelled' => 'text-bg-secondary',
    'unable_to_fulfil' => 'text-bg-danger',
];
?>
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">Customer Orders</h4>
        <p class="text-muted mb-0">Review mobile orders for the currently selected shop. Orders do not affect POS, cash or stock until a validated fulfillment is posted.</p>
    </div>
    <span class="badge text-bg-light border"><?= count($orders) ?> order(s)</span>
</div>

<?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $flashKey => $class): ?>
    <?php if (session()->getFlashdata($flashKey)): ?>
        <div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata($flashKey)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= site_url('customer-orders') ?>" class="row g-2 align-items-end">
            <div class="col-md-4 col-lg-3">
                <label class="form-label" for="order-status">Status</label>
                <select id="order-status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= esc($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button></div>
            <div class="col-auto"><a class="btn btn-outline-secondary" href="<?= site_url('customer-orders') ?>">Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Fulfilment</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$orders): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No customer orders match this filter.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php $orderStatus = (string) ($order['status'] ?? ''); ?>
                    <tr>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="<?= site_url('customer-orders/' . (int)$order['id']) ?>"><?= esc($order['order_no']) ?></a>
                            <?php if ((int)($order['price_reconfirmation_required'] ?? 0) === 1): ?>
                                <div class="small text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Price reconfirmation required</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= esc($order['customer_name_snapshot'] ?? 'Customer') ?></div>
                            <div class="small text-muted"><?= esc($order['customer_phone_snapshot'] ?? '') ?></div>
                        </td>
                        <td><?= esc(ucfirst((string)($order['fulfilment_method'] ?? ''))) ?></td>
                        <td class="text-end"><?= number_format((float)($order['total_amount'] ?? 0), 2) ?></td>
                        <td><span class="badge <?= esc($statusClasses[$orderStatus] ?? 'text-bg-secondary') ?>"><?= esc($statusLabels[$orderStatus] ?? ucfirst(str_replace('_', ' ', $orderStatus))) ?></span></td>
                        <td>
                            <div><?= esc(date('d M Y', strtotime((string)$order['created_at']))) ?></div>
                            <div class="small text-muted"><?= esc(date('H:i', strtotime((string)$order['created_at']))) ?></div>
                        </td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('customer-orders/' . (int)$order['id']) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
