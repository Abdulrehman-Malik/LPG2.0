<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <title><?= esc($title ?? 'Installation') ?></title>
    <link href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <style>
        body { min-height:100vh; background:#172534; display:flex; align-items:center; padding:24px 0; }
        .setup-card { border:0; border-radius:14px; box-shadow:0 1rem 3rem rgba(0,0,0,.25); }
        .warning { background:#fff3cd; border:1px solid #ffecb5; border-radius:8px; padding:14px; }
        .stepper { display:flex; gap:4px; list-style:none; padding:0; margin:0; flex-wrap:wrap; }
        .stepper li { flex:1 1 0; min-width:90px; font-size:.8rem; text-align:center; padding:8px 4px; border-bottom:3px solid #dee2e6; color:#6c757d; }
        .stepper li.done { border-color:#198754; color:#198754; }
        .stepper li.current { border-color:#0d6efd; color:#0d6efd; font-weight:600; }
        .phase { display:flex; gap:10px; align-items:flex-start; padding:6px 0; }
        .phase .bi { font-size:1.1rem; }
    </style>
</head>
<body>
<?php
$keys = array_keys($steps);
$current = array_search($step, $keys, true);
$state = $state ?? [];
$interrupted = !empty($state['interrupted']);
$replace = !empty($state['has_data']) && !$interrupted;
$blocked = $replace && empty($state['admin_exists']);
$old = static fn (string $section, string $field) => old($field) ?? ($wizard[$section][$field] ?? '');
$nextUrl = static fn (string $s) => site_url('installation?step=' . $s);
?>
<div class="container" style="max-width:860px">
    <div class="card setup-card">
        <div class="card-header bg-white p-4">
            <h1 class="h3 mb-1">Set up Perfect LPG</h1>
            <div class="text-muted mb-3">Answer a few questions; the database, your business, its first branch and your owner account are created at the end.</div>
            <ol class="stepper" aria-label="Installation steps">
                <?php foreach ($steps as $key => $label): $i = array_search($key, $keys, true); ?>
                    <li class="<?= $i < $current ? 'done' : ($i === $current ? 'current' : '') ?>" <?= $i === $current ? 'aria-current="step"' : '' ?>>
                        <?= $i < $current ? '<i class="bi bi-check-circle-fill"></i> ' : ($i + 1) . '. ' ?><?= esc($label) ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($error)) : ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif; ?>

            <?php if ($step === 'check') : ?>
                <h2 class="h5">1. Server and database checks</h2>
                <ul class="list-group mb-3">
                    <?php $blocking = false; foreach ($checks as $c): $blocking = $blocking || (!$c['ok'] && $c['blocking']); ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <span><i class="bi <?= $c['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> me-2"></i><?= esc($c['label']) ?></span>
                            <span class="small text-muted text-end"><?= esc($c['detail']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl class="row small mb-3">
                    <dt class="col-sm-4">Database server</dt><dd class="col-sm-8"><code><?= esc(($database['hostname'] ?? '') . ':' . ($database['port'] ?? '')) ?></code> as <code><?= esc($database['username'] ?? '') ?></code></dd>
                    <dt class="col-sm-4">Database</dt><dd class="col-sm-8"><code><?= esc($state['database'] ?? ($database['database'] ?? '')) ?></code> — <?= !empty($state['exists']) ? (int) ($state['tables'] ?? 0) . ' tables' : 'will be created' ?></dd>
                </dl>
                <p class="small text-muted">The database connection comes from the server environment (<code>LPG_DB_HOST</code>, <code>LPG_DB_PORT</code>, <code>LPG_DB_NAME</code>, <code>LPG_DB_USER</code>, <code>LPG_DB_PASS</code>). The database user must be allowed to create the database and its tables; on a hosted database (e.g. Railway) use the user the host provides.</p>
                <?php if ($interrupted) : ?>
                    <div class="alert alert-warning"><strong>A previous installation did not finish.</strong> No sales or other business records exist, so setup rebuilds it from the start (a backup is taken first).
                        <?php if (!empty($state['admin_note'])) : ?><div class="small mt-1"><?= esc($state['admin_note']) ?></div><?php endif; ?>
                        <?php if (!empty($state['missing_tables'])) : ?><div class="small mt-1">Tables not yet created: <?= esc(implode(', ', array_slice($state['missing_tables'], 0, 10))) ?><?= count($state['missing_tables']) > 10 ? ', …' : '' ?>.</div><?php endif; ?>
                    </div>
                <?php elseif ($replace) : ?>
                    <div class="warning mb-3">
                        <strong class="text-danger"><i class="bi bi-exclamation-triangle"></i> This database already has data.</strong> Installing replaces it after a safety backup; you will need an active administrator's password.
                        <?php if ($blocked) : ?>
                            <div class="alert alert-danger mt-2 mb-0">No active administrator account could be verified, so replacement is blocked.
                                <?php if (!empty($state['admin_note'])) : ?><div class="small mt-1"><?= esc($state['admin_note']) ?></div><?php endif; ?>
                                <?php if (!empty($state['foreign_tables'])) : ?><div class="small mt-1">It holds tables this application does not create (<?= esc(implode(', ', array_slice($state['foreign_tables'], 0, 8))) ?><?= count($state['foreign_tables']) > 8 ? ', …' : '' ?>). Point <code>LPG_DB_NAME</code> at an empty database, or remove it manually after a backup.</div><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between">
                    <a class="btn btn-link" href="<?= site_url('login') ?>">Back to sign in</a>
                    <a class="btn btn-primary <?= ($blocking || $blocked) ? 'disabled' : '' ?>" href="<?= $nextUrl('business') ?>" <?= ($blocking || $blocked) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next <i class="bi bi-arrow-right"></i></a>
                </div>

            <?php elseif ($step === 'business') : ?>
                <h2 class="h5">2. Your business</h2>
                <p class="text-muted small">The company that owns the shops. It appears on receipts and reports.</p>
                <form method="post" action="<?= site_url('installation/step/business') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-8"><label class="form-label" for="bName">Business name <span class="text-danger">*</span></label><input id="bName" name="name" class="form-control" maxlength="150" required value="<?= esc($old('business', 'name')) ?>" autofocus></div>
                    <div class="col-md-4"><label class="form-label" for="bCode">Short code <span class="text-danger">*</span></label><input id="bCode" name="code" class="form-control text-uppercase" maxlength="20" pattern="[A-Za-z0-9_\-]{2,20}" required value="<?= esc($old('business', 'code')) ?>" placeholder="e.g. ALNOOR"></div>
                    <div class="col-md-4"><label class="form-label" for="bCity">City <span class="text-danger">*</span></label><input id="bCity" name="city" class="form-control" maxlength="80" required value="<?= esc($old('business', 'city')) ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="bPhone">Phone</label><input id="bPhone" name="phone" class="form-control" maxlength="30" inputmode="tel" value="<?= esc($old('business', 'phone')) ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="bEmail">Email</label><input id="bEmail" name="email" type="email" class="form-control" maxlength="150" value="<?= esc($old('business', 'email')) ?>"></div>
                    <div class="col-12"><label class="form-label" for="bAddress">Address</label><input id="bAddress" name="address" class="form-control" maxlength="255" value="<?= esc($old('business', 'address')) ?>"></div>
                    <div class="col-12 d-flex justify-content-between"><a class="btn btn-outline-secondary" href="<?= $nextUrl('check') ?>"><i class="bi bi-arrow-left"></i> Back</a><button class="btn btn-primary">Next <i class="bi bi-arrow-right"></i></button></div>
                </form>

            <?php elseif ($step === 'branch') : ?>
                <h2 class="h5">3. First branch (shop)</h2>
                <p class="text-muted small">The shop that sells gas. More branches can be added later in Business Management.</p>
                <form method="post" action="<?= site_url('installation/step/branch') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-8"><label class="form-label" for="lName">Branch name <span class="text-danger">*</span></label><input id="lName" name="name" class="form-control" maxlength="120" required value="<?= esc($old('branch', 'name')) ?>" autofocus></div>
                    <div class="col-md-4"><label class="form-label" for="lCode">Branch code <span class="text-danger">*</span></label><input id="lCode" name="code" class="form-control text-uppercase" maxlength="20" pattern="[A-Za-z0-9_\-]{2,20}" required value="<?= esc($old('branch', 'code') ?: 'MAIN') ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="lCity">City <span class="text-danger">*</span></label><input id="lCity" name="city" class="form-control" maxlength="80" required value="<?= esc($old('branch', 'city') ?: ($wizard['business']['city'] ?? '')) ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="lPhone">Phone</label><input id="lPhone" name="phone" class="form-control" maxlength="30" inputmode="tel" value="<?= esc($old('branch', 'phone')) ?>"></div>
                    <div class="col-12"><label class="form-label" for="lAddress">Address</label><input id="lAddress" name="address" class="form-control" maxlength="255" value="<?= esc($old('branch', 'address')) ?>"></div>
                    <div class="col-12 d-flex justify-content-between"><a class="btn btn-outline-secondary" href="<?= $nextUrl('business') ?>"><i class="bi bi-arrow-left"></i> Back</a><button class="btn btn-primary">Next <i class="bi bi-arrow-right"></i></button></div>
                </form>

            <?php elseif ($step === 'owner') : ?>
                <h2 class="h5">4. Owner account</h2>
                <p class="text-muted small">Your own sign-in. It has full access to the business and can add managers, cashiers and drivers. No default password is left behind.</p>
                <form method="post" action="<?= site_url('installation/step/owner') ?>" class="row g-3" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="col-md-6"><label class="form-label" for="oName">Full name <span class="text-danger">*</span></label><input id="oName" name="full_name" class="form-control" maxlength="120" required value="<?= esc($old('owner', 'full_name')) ?>" autofocus></div>
                    <div class="col-md-6"><label class="form-label" for="oEmail">Email</label><input id="oEmail" name="email" type="email" class="form-control" maxlength="150" value="<?= esc($old('owner', 'email')) ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="oUser">Username <span class="text-danger">*</span></label><input id="oUser" name="username" class="form-control" maxlength="60" pattern="[A-Za-z0-9._\-]{3,60}" required value="<?= esc($old('owner', 'username')) ?>" autocomplete="username"></div>
                    <div class="col-md-6"></div>
                    <div class="col-md-6"><label class="form-label" for="oPass">Password <span class="text-danger">*</span></label><input id="oPass" name="password" type="password" class="form-control" minlength="<?= \App\Services\PasswordPolicy::minLength() ?>" required autocomplete="new-password"><div class="form-text">At least <?= \App\Services\PasswordPolicy::minLength() ?> characters; not a common or default password.</div></div>
                    <div class="col-md-6"><label class="form-label" for="oPass2">Repeat password <span class="text-danger">*</span></label><input id="oPass2" name="password_confirm" type="password" class="form-control" required autocomplete="new-password"></div>
                    <?php if (!empty($wizard['owner'])) : ?><div class="col-12 small text-muted">A password was already saved for this setup; enter it again (or a new one) to continue.</div><?php endif; ?>
                    <div class="col-12 d-flex justify-content-between"><a class="btn btn-outline-secondary" href="<?= $nextUrl('branch') ?>"><i class="bi bi-arrow-left"></i> Back</a><button class="btn btn-primary">Next <i class="bi bi-arrow-right"></i></button></div>
                </form>

            <?php elseif ($step === 'review') : ?>
                <h2 class="h5">5. Review and confirm</h2>
                <div class="row g-3 mb-3 small">
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted">Business</div><strong><?= esc($wizard['business']['name']) ?></strong> <span class="badge text-bg-light"><?= esc($wizard['business']['code']) ?></span><div><?= esc($wizard['business']['city']) ?></div><a href="<?= $nextUrl('business') ?>">Change</a></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted">First branch</div><strong><?= esc($wizard['branch']['name']) ?></strong> <span class="badge text-bg-light"><?= esc($wizard['branch']['code']) ?></span><div><?= esc($wizard['branch']['city']) ?></div><a href="<?= $nextUrl('branch') ?>">Change</a></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted">Owner</div><strong><?= esc($wizard['owner']['full_name']) ?></strong><div>Username <code><?= esc($wizard['owner']['username']) ?></code></div><a href="<?= $nextUrl('owner') ?>">Change</a></div></div>
                </div>
                <p class="small">Installing will <?= $replace ? '<strong>back up and then replace</strong> the existing database' : ($interrupted ? 'back up and rebuild the unfinished database' : 'create the database') ?> <code><?= esc($state['database'] ?? '') ?></code>, create all tables and starting data (roles, cylinder types, expense categories), apply every migration, and save the details above.</p>
                <form method="post" action="<?= site_url('installation/step/review') ?>">
                    <?= csrf_field() ?>
                    <?php if ($replace) : ?>
                        <div class="warning mb-3">
                            <strong class="text-danger">All existing records in this database will be permanently deleted</strong> after a safety backup.
                            <div class="mt-2"><label for="admin_password" class="form-label">Existing administrator password</label><input type="password" class="form-control" id="admin_password" name="admin_password" autocomplete="current-password" required <?= $blocked ? 'disabled' : '' ?>></div>
                            <div class="mt-2"><label for="replace_confirm" class="form-label">Type <code>REPLACE DATABASE</code> to authorise deletion</label><input type="text" class="form-control" id="replace_confirm" name="replace_confirm" autocomplete="off" required <?= $blocked ? 'disabled' : '' ?>></div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3"><label for="confirm" class="form-label">Type <code>INSTALL DATABASE</code> to continue</label><input type="text" class="form-control" id="confirm" name="confirm" autocomplete="off" required></div>
                    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="understand" name="understand" value="1" required><label class="form-check-label" for="understand">I understand the installation creates the database and may replace existing data.</label></div>
                    <div class="d-flex justify-content-between"><a class="btn btn-outline-secondary" href="<?= $nextUrl('owner') ?>"><i class="bi bi-arrow-left"></i> Back</a><button class="btn btn-primary" <?= $blocked ? 'disabled' : '' ?>><i class="bi bi-database-gear me-1"></i>Install now</button></div>
                </form>

            <?php elseif ($step === 'install') : ?>
                <h2 class="h5">6. Installing</h2>
                <p class="text-muted small">Keep this page open. Each part runs separately; if one fails you can retry it.</p>
                <div id="phases" aria-live="polite">
                    <?php foreach (['prepare' => 'Prepare the database (backup when needed)', 'schema' => 'Create tables and starting data', 'migrate' => 'Apply database migrations', 'finalize' => 'Save business, branch and owner account'] as $key => $label): ?>
                        <div class="phase" data-phase="<?= $key ?>"><i class="bi bi-circle text-muted"></i><div><div><?= esc($label) ?></div><div class="small text-muted detail"></div></div></div>
                    <?php endforeach; ?>
                </div>
                <div id="installError" class="alert alert-danger mt-3 d-none" role="alert"></div>
                <div class="d-flex justify-content-between mt-3">
                    <a class="btn btn-link" href="<?= site_url('installation/start-over') ?>">Start over</a>
                    <button id="retry" class="btn btn-outline-primary d-none"><i class="bi bi-arrow-repeat me-1"></i>Retry</button>
                    <a id="toLogin" class="btn btn-success d-none" href="<?= site_url('login') ?>">Go to sign in <i class="bi bi-arrow-right"></i></a>
                </div>
                <script>
                (() => {
                    const token = <?= json_encode((string) ($wizard['token'] ?? '')) ?>;
                    const csrf = document.querySelector('meta[name="csrf-token"]').content;
                    const order = ['prepare', 'schema', 'migrate', 'finalize'];
                    const errorBox = document.getElementById('installError');
                    const retry = document.getElementById('retry');
                    let index = 0;
                    const row = (p) => document.querySelector('.phase[data-phase="' + p + '"]');
                    const mark = (p, cls, text) => { const r = row(p); r.querySelector('.bi').className = 'bi ' + cls; if (text !== undefined) r.querySelector('.detail').textContent = text; };
                    async function call(phase) {
                        const res = await fetch(<?= json_encode(site_url('installation/execute')) ?> + '/' + phase, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'X-Install-Token': token, 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                        });
                        let body = null;
                        try { body = await res.json(); } catch (e) { body = { ok: false, message: 'The server stopped responding (HTTP ' + res.status + '). It may have hit a time limit; press Retry.' }; }
                        if (!res.ok || !body.ok) throw new Error(body.message || ('HTTP ' + res.status));
                        return body;
                    }
                    async function run() {
                        errorBox.classList.add('d-none'); retry.classList.add('d-none');
                        while (index < order.length) {
                            const phase = order[index];
                            mark(phase, 'bi-arrow-repeat text-primary', 'Working…');
                            try {
                                let body = await call(phase);
                                if (phase === 'migrate') {
                                    let applied = 0;
                                    while (!body.done) {
                                        applied += (body.applied || []).length;
                                        mark(phase, 'bi-arrow-repeat text-primary', applied + ' applied…');
                                        if (!(body.applied || []).length) throw new Error(body.message || 'Migrations stopped without finishing.');
                                        body = await call(phase);
                                    }
                                    applied += (body.applied || []).length;
                                    body.message = applied + ' migrations applied. ' + (body.message || '');
                                }
                                mark(phase, 'bi-check-circle-fill text-success', body.message || 'Done');
                                if (phase === 'finalize') { document.getElementById('toLogin').classList.remove('d-none'); setTimeout(() => { location.href = body.redirect; }, 1500); }
                                index++;
                            } catch (e) {
                                mark(phase, 'bi-x-circle-fill text-danger', '');
                                errorBox.textContent = e.message; errorBox.classList.remove('d-none');
                                // Prepare changes nothing when it fails; a half-created schema is rebuilt by starting
                                // again from prepare; migrations and finalize resume where they stopped.
                                if (phase === 'schema') { index = 0; }
                                retry.classList.remove('d-none');
                                return;
                            }
                        }
                    }
                    retry.addEventListener('click', run);
                    run();
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
