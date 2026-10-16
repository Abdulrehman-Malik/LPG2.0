<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$total = array_sum(array_map(static fn ($e) => (float) $e['amount'], $expenses));
$modes = ['cash' => 'Cash', 'cheque' => 'Cheque', 'online' => 'Online'];
$oldMode = old('payment_mode') ?? 'cash';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Expenses</h1>
        <p class="page-subtitle">Record branch running costs. Cash expenses are paid from the open cash session.</p>
    </div>
    <div class="page-actions"><a class="btn btn-outline-secondary" href="<?= site_url('expenses/categories') ?>"><i class="bi bi-tags me-1"></i>Expense categories</a></div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <form method="post" action="<?= site_url('expenses/save') ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header">New expense</div>
            <div class="card-body">
                <?php if (!$cash): ?>
                    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>No cash session is open. Cash expenses need an open session; cheque and online expenses can still be posted.</div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label required" for="expCategory">Category</label>
                    <select id="expCategory" name="category_id" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) old('category_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php if (!$categories): ?><div class="form-text text-danger">No active categories. <a href="<?= site_url('expenses/categories') ?>">Add one</a>.</div><?php endif; ?>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label required" for="expAmount">Amount</label>
                        <div class="input-group"><span class="input-group-text">Rs.</span><input id="expAmount" name="amount" type="number" min=".01" step=".01" class="form-control" required inputmode="decimal" value="<?= esc(old('amount') ?? '') ?>"></div>
                    </div>
                    <div class="col-5">
                        <label class="form-label required" for="expMode">Paid by</label>
                        <select id="expMode" name="payment_mode" class="form-select"><?php foreach ($modes as $v => $l): ?><option value="<?= $v ?>" <?= $oldMode === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="expRef">Reference</label>
                    <input id="expRef" name="reference_no" class="form-control" maxlength="80" placeholder="Bill or cheque number" value="<?= esc(old('reference_no') ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="expDesc">Description</label>
                    <input id="expDesc" name="description" class="form-control" maxlength="255" placeholder="What was this for?" value="<?= esc(old('description') ?? '') ?>">
                </div>
                <?= view('partials/transaction_date') ?>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Post expense</button></div>
        </form>
    </div>
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><span>Latest expenses</span><span class="small text-muted"><?= count($expenses) ?> shown · Rs. <?= number_format($total, 2) ?></span></div>
            <?php if (!$expenses): ?>
                <div class="empty-state"><i class="bi bi-receipt"></i><div class="empty-state-title">No expenses yet</div>Posted expenses appear here.</div>
            <?php else: ?>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 datatable">
                    <thead><tr><th>No.</th><th>Date</th><th>Category</th><th>Description</th><th>Paid by</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td class="fw-semibold text-nowrap"><?= esc($e['expense_no']) ?></td>
                            <td class="text-nowrap"><?= esc(date('d M Y, h:i A', strtotime($e['expense_at']))) ?></td>
                            <td><?= esc($e['category_name']) ?></td>
                            <td><?= esc($e['description'] ?? '') ?><?php if (!empty($e['reference_no'])): ?><div class="small text-muted">Ref: <?= esc($e['reference_no']) ?></div><?php endif; ?></td>
                            <td><span class="status"><?= esc($modes[$e['payment_mode']] ?? ucfirst((string) $e['payment_mode'])) ?></span></td>
                            <td class="text-end fw-semibold"><?= number_format((float) $e['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
