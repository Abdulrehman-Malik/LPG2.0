<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $companyName = trim((string)($companyName ?? env('app.companyName', 'Perfect LPG (Pvt.) LTD'))) ?: 'Perfect LPG (Pvt.) LTD'; ?>
    <title><?= esc(($title ?? 'Login') . ' — ' . $companyName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --lpg-dark: #1b2a3a;
            --lpg-accent: #ff7a1a;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, var(--lpg-dark) 0%, #0f1924 100%);
        }
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, .3);
            overflow: hidden;
        }
        .login-brand {
            background: var(--lpg-dark);
            color: #fff;
            padding: 2.5rem 2rem;
        }
        .login-brand .bi {
            font-size: 2.5rem;
            color: var(--lpg-accent);
        }
        .btn-brand {
            background: var(--lpg-accent);
            border-color: var(--lpg-accent);
            color: #fff;
        }
        .btn-brand:hover {
            background: #e2680f;
            border-color: #e2680f;
            color: #fff;
        }
        .form-control:focus {
            border-color: var(--lpg-accent);
            box-shadow: 0 0 0 .2rem rgba(255, 122, 26, .15);
        }
        .migration-list {
            max-height: 280px;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
        }
        .migration-list table {
            margin-bottom: 0;
        }
        .migration-list thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-11 col-sm-9 col-md-7 col-lg-5">
            <div class="card login-card">
                <div class="login-brand text-center">
                    <i class="bi bi-fire"></i>
                    <h4 class="mt-2 mb-0 fw-bold"><?= esc($companyName) ?></h4>
                    <small class="opacity-75">Distribution &amp; Cylinder Inventory System</small>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php
                    $migrationGate=$migrationGate ?? ['ready'=>true,'blocked_message'=>null,'migrations'=>[],'total'=>0,'completed'=>0];
                    $migrationReady=(bool)($migrationGate['ready'] ?? false);
                    $migrationRows=$migrationGate['migrations'] ?? [];
                    ?>

                    <?php if (session()->getFlashdata('migration_success')) : ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div><?= esc(session()->getFlashdata('migration_success')) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (session()->getFlashdata('migration_error')) : ?>
                        <div class="alert alert-danger d-flex align-items-start" role="alert">
                            <i class="bi bi-exclamation-octagon-fill me-2 mt-1"></i>
                            <div><?= esc(session()->getFlashdata('migration_error')) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$migrationReady) : ?>
                        <div class="alert alert-warning d-flex align-items-start" role="alert">
                            <i class="bi bi-database-exclamation me-2 mt-1"></i>
                            <div>
                                <strong>Database update required before login.</strong>
                                <div class="small mt-1">
                                    <?= esc($migrationGate['blocked_message'] ?? 'Pending database migrations must be executed before users can sign in.') ?>
                                </div>
                                <?php if (!empty($migrationGate['system_error'])) : ?>
                                    <div class="small mt-1 text-danger"><?= esc($migrationGate['system_error']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($migrationRows) : ?>
                            <div class="table-responsive migration-list mb-3">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="width:70px">Seq</th>
                                        <th>Migration</th>
                                        <th style="width:120px">Status</th>
                                        <th>Result</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($migrationRows as $m) : ?>
                                        <?php
                                        $status=(string)($m['status'] ?? 'pending');
                                        $badge=[
                                            'success'=>'success',
                                            'failed'=>'danger',
                                            'running'=>'warning',
                                            'changed'=>'danger',
                                            'skipped'=>'warning',
                                            'pending'=>'secondary',
                                        ][$status] ?? 'secondary';
                                        ?>
                                        <tr>
                                            <td><?= (int)$m['seq'] ?></td>
                                            <td class="small"><?= esc($m['file']) ?></td>
                                            <td><span class="badge text-bg-<?= $badge ?>"><?= esc(strtoupper($status)) ?></span></td>
                                            <td class="small"><?= esc($m['message'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <form action="<?= site_url('migrations/run') ?>" method="post" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').innerHTML='<span class=&quot;spinner-border spinner-border-sm me-1&quot;></span> Executing...';">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-brand w-100 py-2 fw-semibold">
                                <i class="bi bi-database-up me-1"></i>
                                Execute Pending Migrations
                            </button>
                        </form>

                        <div class="text-center text-muted small mt-3">
                            <?= (int)($migrationGate['completed'] ?? 0) ?> of <?= (int)($migrationGate['total'] ?? 0) ?> migrations completed.
                            The next migration is executed only after the previous one succeeds.
                        </div>
                    <?php endif; ?>

                    <?php if ($migrationReady) : ?>
                        <?php if (session()->getFlashdata('error')) : ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div><?= esc(session()->getFlashdata('error')) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (session()->getFlashdata('success')) : ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <div><?= esc(session()->getFlashdata('success')) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (session()->getFlashdata('errors')) : ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    <?php foreach (session()->getFlashdata('errors') as $error) : ?>
                                        <li><?= esc($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form action="<?= site_url('login') ?>" method="post" novalidate>
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label for="login" class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="login" name="login"
                                           value="<?= esc(old('login')) ?>" placeholder="e.g. admin" required autofocus>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password"
                                           placeholder="••••••••" required>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-brand w-100 py-2 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-center text-white-50 mt-3 small mb-0">
                &copy; <?= date('Y') ?> <?= esc($companyName) ?>. All rights reserved.
            </p>
        </div>
    </div>
</div>
</body>
</html>
