<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header">
    <div><h1 class="page-title">Change Password</h1>
        <p class="page-subtitle"><?= $forced ? 'This account still uses a default password. Set your own password to continue.' : 'Set a new password for your account.' ?></p></div>
</div>
<div class="row"><div class="col-lg-5">
    <div class="card"><div class="card-body">
        <form method="post" action="<?= site_url('account/password') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label" for="current_password">Current password</label>
                <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
            <div class="mb-3"><label class="form-label" for="new_password">New password</label>
                <input class="form-control" type="password" id="new_password" name="new_password" required minlength="<?= (int) $minLength ?>" autocomplete="new-password">
                <div class="form-text">At least <?= (int) $minLength ?> characters. Default passwords are not accepted.</div></div>
            <div class="mb-3"><label class="form-label" for="confirm_password">Confirm new password</label>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="<?= (int) $minLength ?>" autocomplete="new-password"></div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-key me-1"></i>Change password</button>
            <?php if ($forced): ?><a class="btn btn-link" href="<?= site_url('logout') ?>">Sign out</a><?php endif; ?>
        </form>
    </div></div>
</div></div>
<?= $this->endSection() ?>
