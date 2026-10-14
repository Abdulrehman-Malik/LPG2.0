<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">Customer App Accounts</h4>
        <p class="text-muted mb-0">Review registrations and link each approved mobile identity to exactly one financial customer record in the current shop.</p>
    </div>
    <span class="badge text-bg-light border"><?= count($pending) ?> pending signup(s)</span>
</div>

<?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $flashKey => $class): ?>
    <?php if (session()->getFlashdata($flashKey)): ?>
        <div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata($flashKey)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="alert alert-info small">
    Approval links a mobile login to one customer master. It does not grant credit-sale permission, set a credit limit or create a sale.
    Choose an existing customer when a matching party record already exists; create a new master only after checking for duplicates.
</div>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <strong><i class="bi bi-person-check me-2"></i>Pending registration requests</strong>
        <span class="badge text-bg-warning"><?= count($pending) ?> pending</span>
    </div>
    <div class="card-body p-0">
        <?php if (!$pending): ?>
            <div class="text-center text-muted py-5">There are no pending customer registrations for this shop.</div>
        <?php else: ?>
            <div class="accordion accordion-flush" id="signupRequests">
                <?php foreach ($pending as $request): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="signup-heading-<?= (int)$request['id'] ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#signup-detail-<?= (int)$request['id'] ?>" aria-expanded="false" aria-controls="signup-detail-<?= (int)$request['id'] ?>">
                                <span class="me-3 fw-semibold"><?= esc($request['customer_name']) ?></span>
                                <span class="small text-muted me-3"><?= esc($request['username']) ?></span>
                                <span class="badge text-bg-light border">#<?= (int)$request['id'] ?></span>
                            </button>
                        </h2>
                        <div id="signup-detail-<?= (int)$request['id'] ?>" class="accordion-collapse collapse" aria-labelledby="signup-heading-<?= (int)$request['id'] ?>" data-bs-parent="#signupRequests">
                            <div class="accordion-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4"><div class="small text-muted">Phone</div><div><?= esc($request['phone'] ?: '—') ?></div></div>
                                    <div class="col-md-4"><div class="small text-muted">City</div><div><?= esc($request['city'] ?: '—') ?></div></div>
                                    <div class="col-md-4"><div class="small text-muted">Vehicle number</div><div><?= esc($request['vehicle_no'] ?: '—') ?></div></div>
                                    <div class="col-12"><div class="small text-muted">Address</div><div><?= esc($request['address'] ?: '—') ?></div></div>
                                    <div class="col-12"><div class="small text-muted">Submitted</div><div><?= esc((string)$request['created_at']) ?></div></div>
                                </div>
                                <form method="post" action="<?= site_url('customer-app-accounts/signup/' . (int)$request['id'] . '/approve') ?>" class="border rounded p-3 mb-3">
                                    <?= csrf_field() ?>
                                    <h6>Approve and link customer</h6>
                                    <label class="form-label" for="customer-link-<?= (int)$request['id'] ?>">Financial customer master</label>
                                    <select class="form-select mb-2" name="customer_id" id="customer-link-<?= (int)$request['id'] ?>">
                                        <option value="0">Create a new customer master from these details</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= (int)$customer['id'] ?>"><?= esc(trim(($customer['code'] ? $customer['code'] . ' · ' : '') . $customer['name'] . ($customer['phone'] ? ' · ' . $customer['phone'] : ''))) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text mb-3">New customer masters start without credit permission or credit limit. Verify the customer details and shop before approval.</div>
                                    <button class="btn btn-success" type="submit"><i class="bi bi-check-circle me-1"></i>Approve account</button>
                                </form>
                                <form method="post" action="<?= site_url('customer-app-accounts/signup/' . (int)$request['id'] . '/reject') ?>" class="border rounded p-3">
                                    <?= csrf_field() ?>
                                    <label class="form-label" for="reject-reason-<?= (int)$request['id'] ?>">Rejection reason (required)</label>
                                    <textarea class="form-control mb-2" name="reason" id="reject-reason-<?= (int)$request['id'] ?>" maxlength="500" rows="2" required></textarea>
                                    <button class="btn btn-outline-danger" type="submit" onclick="return confirm('Reject this registration request?')">Reject registration</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><strong><i class="bi bi-person-plus me-2"></i>Create a Customer App login for an existing party</strong></div>
    <div class="card-body p-0">
        <?php if (!$customers): ?>
            <div class="text-muted p-3">All active customer masters in this shop already have an app login, or there are no eligible customers.</div>
        <?php else: ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Financial customer</th><th style="min-width:170px">Username</th><th style="min-width:200px">Temporary password</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $customer): ?>
            <?php $formId = 'create-account-' . (int)$customer['id']; ?>
            <tr>
                <td>
                    <div class="fw-semibold"><?= esc($customer['name']) ?></div>
                    <div class="small text-muted"><?= esc($customer['code'] ?: ('Customer #' . $customer['id'])) ?><?= $customer['phone'] ? ' · ' . esc($customer['phone']) : '' ?></div>
                    <form method="post" id="<?= esc($formId) ?>" action="<?= site_url('customer-app-accounts/customer/' . (int)$customer['id'] . '/create') ?>">
                        <?= csrf_field() ?>
                    </form>
                </td>
                <td><input class="form-control form-control-sm" form="<?= esc($formId) ?>" name="username" pattern="[a-zA-Z0-9._-]{3,80}" minlength="3" maxlength="80" autocomplete="off" required placeholder="customer login"></td>
                <td><input class="form-control form-control-sm" form="<?= esc($formId) ?>" type="password" name="password" minlength="<?= (int)$passwordMinimum ?>" autocomplete="new-password" required placeholder="Min <?= (int)$passwordMinimum ?> characters"></td>
                <td class="text-end"><button class="btn btn-sm btn-primary" type="submit" form="<?= esc($formId) ?>">Create login</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
        <div class="form-text p-3">Each login links to the existing party. It does not enable credit sales or change the customer's credit limit.</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong><i class="bi bi-people me-2"></i>Customer App accounts</strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Customer</th><th>Username</th><th>Status</th><th>Last login</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php if (!$accounts): ?>
                <tr><td colspan="5" class="text-center text-muted py-5">No Customer App accounts have been created for this shop yet.</td></tr>
            <?php else: ?>
                <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><div class="fw-semibold"><?= esc($account['customer_name']) ?></div><div class="small text-muted"><?= esc($account['customer_code'] ?: ('Customer #' . $account['customer_id'])) ?><?= $account['phone'] ? ' · ' . esc($account['phone']) : '' ?></div></td>
                    <td><?= esc($account['username']) ?></td>
                    <td><?php if ($account['status'] === 'approved' && (int)$account['customer_active'] === 1): ?><span class="badge text-bg-success">Enabled</span><?php else: ?><span class="badge text-bg-secondary"><?= esc(ucfirst($account['status'])) ?><?= (int)$account['customer_active'] !== 1 ? ' · customer inactive' : '' ?></span><?php endif; ?></td>
                    <td><?= esc($account['last_login_at'] ?: 'Never') ?></td>
                    <td class="text-end">
                        <?php if ($account['status'] === 'approved'): ?>
                        <form method="post" action="<?= site_url('customer-app-accounts/' . (int)$account['account_id'] . '/disable') ?>" onsubmit="return confirm('Disable this Customer App account and revoke its existing sessions?');">
                            <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-danger">Disable</button>
                        </form>
                        <?php else: ?><span class="text-muted small">Disabled</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(() => {
    const customer = document.getElementById('account-customer');
    const username = document.getElementById('account-username');
    const password = document.getElementById('account-password');
    const submit = document.getElementById('show-account-form');
    if (!customer || !username || !password || !submit) return;
    const sync = () => { submit.disabled = !(customer.value && username.value.trim() && password.value.length >= <?= (int)$passwordMinimum ?>); };
    [customer, username, password].forEach(el => el.addEventListener('input', sync));
    submit.addEventListener('click', () => {
        if (!customer.value || !username.value.trim() || password.value.length < <?= (int)$passwordMinimum ?>) return;
        const form = document.createElement('form');
        form.method = 'post';
        form.action = <?= json_encode(site_url('customer-app-accounts/customer')) ?> + '/' + encodeURIComponent(customer.value) + '/create';
        const values = {<?= json_encode(csrf_token()) ?>: <?= json_encode(csrf_hash()) ?>, username: username.value.trim().toLowerCase(), password: password.value};
        Object.entries(values).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    });
    sync();
})();
</script>
<?= $this->endSection() ?>
