<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Database Installation Setup') ?></title>
    <link href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <style>
        body { min-height:100vh; background:#172534; display:flex; align-items:center; padding:24px 0; }
        .setup-card { border:0; border-radius:14px; box-shadow:0 1rem 3rem rgba(0,0,0,.25); }
        .warning { background:#fff3cd; border:1px solid #ffecb5; border-radius:8px; padding:14px; }
    </style>
</head>
<body>
<div class="container" style="max-width:820px">
    <div class="card setup-card">
        <div class="card-header bg-white p-4">
            <h3 class="mb-1">First-Time Database Installation</h3>
            <div class="text-muted">Prepare the database, install the schema, seed master data, and execute pending migrations.</div>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($error)) : ?><div class="alert alert-danger"><?= esc($error) ?></div><?php endif; ?>
            <?php if (!empty($success)) : ?><div class="alert alert-success"><?= esc($success) ?></div><?php endif; ?>
            <dl class="row">
                <dt class="col-sm-4">Configured database</dt><dd class="col-sm-8"><code><?= esc($state['database'] ?? '') ?></code></dd>
                <dt class="col-sm-4">Database exists</dt><dd class="col-sm-8"><?= !empty($state['exists']) ? 'Yes' : 'No — it will be created' ?></dd>
                <dt class="col-sm-4">Tables detected</dt><dd class="col-sm-8"><?= (int)($state['tables'] ?? 0) ?></dd>
            </dl>

            <?php if (!empty($state['has_data'])) : ?>
                <div class="warning mb-4">
                    <h5 class="text-danger"><i class="bi bi-exclamation-triangle"></i> Destructive database replacement</h5>
                    <p class="mb-2"><strong>All existing records and tables in this database will be permanently deleted.</strong> A verified SQL backup is required before replacement. This operation is separate from Transactional Data Refresh.</p>
                    <?php if (empty($state['admin_exists'])) : ?>
                        <div class="alert alert-danger mb-0">No active administrator account could be verified. Replacement is blocked for safety.</div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-info">No existing table data was detected. The installer will create or initialize the configured database, apply <code>database/schema.sql</code>, seed initial data, and run pending migrations.</div>
            <?php endif; ?>

            <form method="post" action="<?= site_url('installation/run') ?>" onsubmit="return confirm('Continue with database installation? Existing database data may be permanently deleted if replacement is selected.');">
                <?= csrf_field() ?>
                <?php if (!empty($state['has_data'])) : ?>
                    <div class="mb-3">
                        <label for="admin_password" class="form-label">Existing administrator password</label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" autocomplete="current-password" required <?= empty($state['admin_exists']) ? 'disabled' : '' ?>>
                        <div class="form-text">Verified on the server against an active ADMIN account in the existing database.</div>
                    </div>
                    <div class="mb-3">
                        <label for="replace_confirm" class="form-label">Type <code>REPLACE DATABASE</code> to authorize deletion</label>
                        <input type="text" class="form-control" id="replace_confirm" name="replace_confirm" autocomplete="off" required <?= empty($state['admin_exists']) ? 'disabled' : '' ?>>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label for="confirm" class="form-label">Type <code>INSTALL DATABASE</code> to continue</label>
                    <input type="text" class="form-control" id="confirm" name="confirm" autocomplete="off" required>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="understand" required>
                    <label class="form-check-label" for="understand">I understand this runs schema and migration scripts and may replace all existing data.</label>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2" <?= (!empty($state['has_data']) && empty($state['admin_exists'])) ? 'disabled' : '' ?>>Run Installation Setup</button>
            </form>
            <div class="text-center mt-3"><a href="<?= site_url('login') ?>">Return to login</a></div>
        </div>
    </div>
</div>
</body>
</html>
