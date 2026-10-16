<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header">
    <div><h1 class="page-title">Expense Categories</h1><p class="page-subtitle">Shared by all branches of this business. Categories are deactivated rather than deleted so past expenses keep their category.</p></div>
    <a class="btn btn-outline-secondary" href="<?= site_url('expenses') ?>"><i class="bi bi-arrow-left me-1"></i>Expenses</a>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <form method="post" action="<?= site_url('expenses/categories/save') ?>" class="card">
            <?= csrf_field() ?>
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
            <div class="card-header fw-semibold"><?= $edit ? 'Edit category' : 'New category' ?></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                    <input class="form-control text-uppercase" id="code" name="code" maxlength="30" required value="<?= esc(old('code') ?? ($edit['code'] ?? '')) ?>" placeholder="e.g. FUEL">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                    <input class="form-control" id="name" name="name" maxlength="80" required value="<?= esc(old('name') ?? ($edit['name'] ?? '')) ?>" placeholder="e.g. Vehicle fuel">
                </div>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-end">
                <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= site_url('expenses/categories') ?>">Cancel</a><?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Add category' ?></button>
            </div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Code</th><th>Name</th><th class="text-end">Expenses</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr class="<?= (int) $c['is_active'] ? '' : 'text-muted' ?>">
                            <td class="fw-semibold"><?= esc($c['code']) ?></td>
                            <td><?= esc($c['name']) ?></td>
                            <td class="text-end"><?= (int) $c['expense_count'] ?></td>
                            <td class="text-end"><?= number_format((float) $c['expense_total'], 2) ?></td>
                            <td><?= (int) $c['is_active'] ? '<span class="status status-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('expenses/categories?edit=' . (int) $c['id']) ?>">Edit</a>
                                <form method="post" action="<?= site_url('expenses/categories/' . (int) $c['id'] . '/toggle') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm <?= (int) $c['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" type="submit"><?= (int) $c['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
