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
$orderStatus = (string) ($order['status'] ?? '');
$transitions = [
    'pending' => ['accepted' => 'Accept order', 'rejected' => 'Reject order', 'cancelled' => 'Cancel order'],
    'accepted' => ['preparing' => 'Start preparing', 'ready' => 'Mark ready', 'out_for_delivery' => 'Dispatch delivery', 'cancelled' => 'Cancel order', 'unable_to_fulfil' => 'Unable to fulfil'],
    'preparing' => ['ready' => 'Mark ready', 'out_for_delivery' => 'Dispatch delivery', 'cancelled' => 'Cancel order', 'unable_to_fulfil' => 'Unable to fulfil'],
    'ready' => ['out_for_delivery' => 'Dispatch delivery', 'cancelled' => 'Cancel order', 'unable_to_fulfil' => 'Unable to fulfil'],
    'out_for_delivery' => ['unable_to_fulfil' => 'Unable to fulfil'],
];
$needsReason = ['rejected', 'cancelled', 'unable_to_fulfil'];
$unitOptionsAvailable = false;
foreach (($fulfillment['lines'] ?? []) as $fulfillmentLine) {
    if (!empty($fulfillmentLine['cylinders'])) {
        $unitOptionsAvailable = true;
        break;
    }
}
?>
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <div class="small text-muted mb-1"><a href="<?= site_url('customer-orders') ?>" class="text-decoration-none">Customer Orders</a> / Order details</div>
        <h4 class="mb-1"><?= esc($order['order_no']) ?></h4>
        <p class="text-muted mb-0"><?= esc($order['business_name'] ?? '') ?> · <?= esc($order['shop_name'] ?? '') ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= site_url('customer-orders') ?>"><i class="bi bi-arrow-left me-1"></i>Back to orders</a>
</div>

<?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $flashKey => $class): ?>
    <?php if (session()->getFlashdata($flashKey)): ?>
        <div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata($flashKey)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ((int)($order['price_reconfirmation_required'] ?? 0) === 1): ?>
<div class="alert alert-warning"><strong>Customer confirmation required.</strong> The order total or selected-cylinder quote changed. The customer must review and reconfirm the updated price in the Customer App before the shop can accept or fulfil this order.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <strong>Order items</strong>
                <span class="badge text-bg-light border"><?= esc($statusLabels[$orderStatus] ?? ucfirst(str_replace('_', ' ', $orderStatus))) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Product</th><th>Quantity</th><th class="text-end">Rate</th><th class="text-end">Line total</th></tr></thead>
                    <tbody>
                    <?php foreach (($order['items'] ?? []) as $item): ?>
                        <?php
                        $unit = (string)($item['quantity_unit'] ?? '');
                        $quantity = (float)($item['quantity'] ?? 0);
                        $mode = (string)($item['sale_mode'] ?? '');
                        $rate = $mode === 'sell_gas_only'
                            ? (float)($item['gas_rate_snapshot'] ?? 0)
                            : (float)($item['cylinder_rate_snapshot'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc($item['product_name_snapshot'] ?? '') ?></div>
                                <div class="small text-muted"><?= esc(str_replace('_', ' ', $mode)) ?></div>
                                <?php if (!empty($item['notes'])): ?><div class="small text-muted"><?= esc($item['notes']) ?></div><?php endif; ?>
                            </td>
                            <td><?= number_format($quantity, $unit === 'kg' ? 3 : 0) ?> <?= esc($unit) ?></td>
                            <td class="text-end"><?= number_format($rate, 2) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)($item['line_total'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" class="text-end">Subtotal</th><th class="text-end"><?= number_format((float)($order['subtotal'] ?? 0), 2) ?></th></tr>
                        <tr><th colspan="3" class="text-end">Delivery fee</th><th class="text-end"><?= number_format((float)($order['delivery_fee'] ?? 0), 2) ?></th></tr>
                        <tr class="table-light"><th colspan="3" class="text-end">Order total</th><th class="text-end fs-5"><?= number_format((float)($order['total_amount'] ?? 0), 2) ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Customer & fulfilment</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><div class="small text-muted">Customer</div><div class="fw-semibold"><?= esc($order['customer_name_snapshot'] ?? '') ?></div></div>
                    <div class="col-md-6"><div class="small text-muted">Phone</div><div><?= esc($order['customer_phone_snapshot'] ?? '') ?></div></div>
                    <div class="col-md-6"><div class="small text-muted">Method</div><div><?= esc(ucfirst((string)($order['fulfilment_method'] ?? ''))) ?></div></div>
                    <div class="col-md-6"><div class="small text-muted">Submitted</div><div><?= esc((string)($order['created_at'] ?? '')) ?></div></div>
                    <?php if (($order['fulfilment_method'] ?? '') === 'delivery'): ?>
                    <div class="col-md-6"><div class="small text-muted">Delivery address</div><div><?= esc($order['delivery_address_snapshot'] ?? '') ?></div></div>
                    <div class="col-md-6"><div class="small text-muted">City</div><div><?= esc($order['delivery_city_snapshot'] ?? '') ?></div></div>
                    <?php endif; ?>
                    <div class="col-12"><div class="small text-muted">Customer notes</div><div><?= esc($order['customer_notes'] ?: '—') ?></div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Status history</strong></div>
            <div class="list-group list-group-flush">
                <?php if (empty($order['status_history'])): ?>
                    <div class="list-group-item text-muted">No status history is available.</div>
                <?php else: ?>
                    <?php foreach ($order['status_history'] as $event): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div class="fw-semibold">
                                <?= esc($event['from_status'] ? ($statusLabels[$event['from_status']] ?? $event['from_status']) . ' → ' : '') ?>
                                <?= esc($statusLabels[$event['to_status']] ?? $event['to_status']) ?>
                            </div>
                            <small class="text-muted"><?= esc((string)$event['created_at']) ?></small>
                        </div>
                        <div class="small text-muted"><?= esc(ucfirst((string)$event['actor_type'])) ?><?= !empty($event['reason']) ? ' · ' . esc($event['reason']) : '' ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <?php if ($canManage && !empty($transitions[$orderStatus])): ?>
        <div class="card mb-3">
            <div class="card-header"><strong>Update status</strong></div>
            <div class="card-body">
                <form method="post" action="<?= site_url('customer-orders/' . (int)$order['id'] . '/status') ?>">
                    <?= csrf_field() ?>
                    <label class="form-label" for="next-status">Next status</label>
                    <select class="form-select mb-3" name="status" id="next-status" required>
                        <?php foreach ($transitions[$orderStatus] as $next => $label): ?>
                            <option value="<?= esc($next) ?>"><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label" for="status-reason">Reason / note</label>
                    <textarea class="form-control mb-3" name="reason" id="status-reason" rows="2" maxlength="500" placeholder="Required for rejection, cancellation or unable-to-fulfil."></textarea>
                    <div class="form-text mb-3">Every transition is validated by the server. An order cannot be marked completed here; completion is tied to POS posting.</div>
                    <button class="btn btn-primary w-100" type="submit">Save status</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canManage && $fulfillment && empty($order['pos_sale_id'])): ?>
        <div class="card mb-3">
            <div class="card-header"><strong>POS fulfilment</strong></div>
            <div class="card-body">
                <?php if ($orderStatus === 'pending'): ?>
                    <div class="alert alert-info small">Accept the order first. Fulfilment cannot skip shop acceptance.</div>
                <?php elseif ((int)($order['price_reconfirmation_required'] ?? 0) === 1): ?>
                    <div class="alert alert-warning small">Waiting for the customer to reconfirm the quote.</div>
                <?php else: ?>
                    <p class="small text-muted">Select real shop inventory units and enter payments. This action calls the existing POS posting service; it does not mark the order complete by itself.</p>
                    <form method="post" action="<?= site_url('customer-orders/' . (int)$order['id'] . '/fulfill') ?>">
                        <?= csrf_field() ?>
                        <?php foreach (($fulfillment['lines'] ?? []) as $fLine): ?>
                            <div class="border rounded p-2 mb-3">
                                <div class="fw-semibold mb-1">Line <?= (int)$fLine['line_no'] ?>: <?= esc($fLine['product_name']) ?></div>
                                <div class="small text-muted mb-2"><?= number_format((float)$fLine['quantity_required'], 3) ?> requested · <?= esc(str_replace('_', ' ', (string)$fLine['sale_mode'])) ?></div>
                                <input type="hidden" name="fulfillment_lines[<?= (int)$fLine['line_no'] ?>][line_no]" value="<?= (int)$fLine['line_no'] ?>">
                                <?php if (($fLine['selection_field'] ?? '') === 'source_cylinder_unit_id'): ?>
                                    <label class="form-label" for="source-cylinder-<?= (int)$fLine['line_no'] ?>">Filled source cylinder</label>
                                    <select class="form-select form-select-sm" id="source-cylinder-<?= (int)$fLine['line_no'] ?>" name="fulfillment_lines[<?= (int)$fLine['line_no'] ?>][source_cylinder_unit_id]" required>
                                        <option value="">Select cylinder</option>
                                        <?php foreach (($fLine['cylinders'] ?? []) as $cylinder): ?>
                                            <option value="<?= (int)$cylinder['id'] ?>"><?= esc($cylinder['unit_code']) ?> · <?= number_format((float)($cylinder['gas_weight_kg'] ?? 0), 3) ?> kg</option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <label class="form-label" for="selected-cylinders-<?= (int)$fLine['line_no'] ?>">Physical cylinders</label>
                                    <select class="form-select form-select-sm" id="selected-cylinders-<?= (int)$fLine['line_no'] ?>" name="fulfillment_lines[<?= (int)$fLine['line_no'] ?>][selected_cylinder_unit_ids][]" multiple size="4" required>
                                        <?php foreach (($fLine['cylinders'] ?? []) as $cylinder): ?>
                                            <option value="<?= (int)$cylinder['id'] ?>"><?= esc($cylinder['unit_code']) ?><?= isset($cylinder['gas_weight_kg']) ? ' · ' . number_format((float)$cylinder['gas_weight_kg'], 3) . ' kg' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select exactly <?= (int)$fLine['quantity_required'] ?> cylinder(s).</div>
                                <?php endif; ?>
                                <?php if (empty($fLine['cylinders'])): ?><div class="small text-danger mt-2">No matching physical cylinders are currently available for this line.</div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <h6 class="mt-3">Payments</h6>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="small fw-semibold mb-2">Payment <?= $i + 1 ?><?= $i === 0 ? ' (default)' : ' (optional)' ?></div>
                            <label class="form-label small" for="payment-mode-<?= $i ?>">Mode</label>
                            <select class="form-select form-select-sm mb-2" id="payment-mode-<?= $i ?>" name="payments[<?= $i ?>][payment_mode]">
                                <?php foreach (['cash' => 'Cash', 'cheque' => 'Cheque', 'online' => 'Online', 'credit' => 'Credit'] as $mode => $modeLabel): ?>
                                    <option value="<?= esc($mode) ?>" <?= $i === 0 && $mode === 'cash' ? 'selected' : '' ?>><?= esc($modeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="form-label small" for="payment-amount-<?= $i ?>">Amount</label>
                            <input class="form-control form-control-sm mb-2" id="payment-amount-<?= $i ?>" name="payments[<?= $i ?>][amount]" type="number" min="0" step="0.01" value="<?= $i === 0 ? number_format((float)($order['total_amount'] ?? 0), 2, '.', '') : '' ?>" placeholder="0.00">
                            <label class="form-label small" for="payment-reference-<?= $i ?>">Reference (optional)</label>
                            <input class="form-control form-control-sm" id="payment-reference-<?= $i ?>" name="payments[<?= $i ?>][reference_no]" maxlength="100" placeholder="Cheque / online reference">
                        </div>
                        <?php endfor; ?>
                        <div class="small text-muted mb-3">Payment total must match the validated POS amount. Credit payment is subject to the existing customer's credit-sale flag and configured credit limit.</div>
                        <button class="btn btn-success w-100" type="submit" <?= !$unitOptionsAvailable ? 'disabled' : '' ?>><i class="bi bi-check2-circle me-1"></i>Post fulfilment to POS</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($canManage && in_array($orderStatus, ['accepted', 'preparing', 'ready', 'out_for_delivery'], true) && empty($order['pos_sale_id'])): ?>
            <div class="card mb-3"><div class="card-header"><strong>POS fulfilment</strong></div><div class="card-body">
                <?php if ((int)($order['price_reconfirmation_required'] ?? 0) === 1): ?>
                    <div class="alert alert-warning mb-0">The customer must reconfirm the revised total in the Customer App before fulfilment.</div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">Physical fulfilment options could not be loaded. Check shop inventory and try again.</div>
                <?php endif; ?>
            </div></div>
        <?php endif; ?>

        <?php if (!empty($order['pos_sale_id'])): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Posted to POS sale #<?= (int)$order['pos_sale_id'] ?>.</div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
