<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-header">
    <div><h1 class="page-title">Database Backup & Restore</h1><p class="page-subtitle">Create downloadable database backups, restore SQL files, and configure backup email delivery.</p></div>
</div>

<ul class="nav nav-tabs mb-3" id="backupTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup-pane" type="button" role="tab">
            <i class="bi bi-database-down me-1"></i>Backup
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="restore-tab" data-bs-toggle="tab" data-bs-target="#restore-pane" type="button" role="tab">
            <i class="bi bi-database-up me-1"></i>Restore
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email-pane" type="button" role="tab">
            <i class="bi bi-envelope-gear me-1"></i>Email Configuration
        </button>
    </li>
    <?php if (!empty($isAdmin)): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="refresh-tab" data-bs-toggle="tab" data-bs-target="#refresh-pane" type="button" role="tab">
            <i class="bi bi-arrow-repeat me-1"></i>Transactional Data Refresh
        </button>
    </li>
    <?php endif; ?>
</ul>

<div class="tab-content" id="backupTabsContent">
    <div class="tab-pane fade show active" id="backup-pane" role="tabpanel" aria-labelledby="backup-tab">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Create Database Backup</div>
            <div class="card-body">
                <p class="text-muted">The database tables and their data will be exported to the configured server backup directory. Download each backup to keep a copy outside the server.</p>
                <div class="alert alert-warning py-2 small">Railway service storage may be temporary. Files kept only on the server may be lost after redeploys or restarts, so download backups, email them, or upload them to Google Drive.</div>
                <div class="alert alert-info py-2 small">Google Drive upload requires OAuth credentials in Railway Variables (or the local environment). Configure BACKUP_GOOGLE_DRIVE_CLIENT_ID, BACKUP_GOOGLE_DRIVE_CLIENT_SECRET, BACKUP_GOOGLE_DRIVE_REFRESH_TOKEN, and optionally BACKUP_GOOGLE_DRIVE_FOLDER_ID. Enable the Google Drive API and grant the OAuth account access to the target folder.</div>

                <div class="alert alert-light border mb-3">
                    <div class="small fw-semibold">Backup directory</div>
                    <code><?= esc($backupDirectory) ?></code>
                </div>

                <form method="post" action="<?= site_url('database-backup/create') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="send_email" id="sendEmailValue" value="0">

                    <div class="mb-3">
                        <label for="emailRecipient" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="emailRecipient" name="email_recipient"
                               value="<?= esc($emailRecipients) ?>" placeholder="Enter email address">
                        <div class="form-text">This address is remembered for your next backup until you change it.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="sendEmailCheckbox">
                        <label class="form-check-label" for="sendEmailCheckbox">Also send backup as an email attachment</label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="uploadDriveCheckbox" name="upload_drive" value="1">
                        <label class="form-check-label" for="uploadDriveCheckbox">Also upload backup to Google Drive</label>
                    </div>

                    <button type="submit" class="btn btn-primary"
                            onclick="document.getElementById('sendEmailValue').value=document.getElementById('sendEmailCheckbox').checked?'1':'0';">
                        <i class="bi bi-cloud-arrow-down me-1"></i>Create Backup
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="restore-pane" role="tabpanel" aria-labelledby="restore-tab">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Restore Database</div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong>Warning:</strong> Restore replaces database objects/data contained in the selected SQL file.
                    A safety backup is automatically created immediately before the restore.
                </div>

                <form method="post" action="<?= site_url('database-backup/restore') ?>"
                      enctype="multipart/form-data"
                      data-confirm="Restore this database backup? Current database data may be replaced. A safety backup will be created first.">
                    <?= csrf_field() ?>

                    <label class="form-label">Backup file (.sql)</label>
                    <input type="file" name="backup_file" class="form-control mb-3" accept=".sql" required>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="restoreConfirm" required>
                        <label class="form-check-label" for="restoreConfirm">
                            I understand that restoring will replace current database data.
                        </label>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restore Database
                    </button>
                </form>

                <div class="form-text mt-2">
                    Maximum upload size: <?= esc(number_format($maxUploadSize / 1048576, 0)) ?> MB, subject to PHP upload limits.
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="email-pane" role="tabpanel" aria-labelledby="email-tab">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">SMTP Email Configuration</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('database-backup/email-settings/save') ?>">
                    <?= csrf_field() ?>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="smtpEnabled" name="smtp_enabled" value="1"
                               <?= !empty($emailSettings['smtp_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="smtpEnabled">Enable SMTP email sending</label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" name="smtp_host"
                                   value="<?= esc($emailSettings['smtp_host'] ?? '') ?>"
                                   placeholder="smtp.example.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" name="smtp_port"
                                   value="<?= esc($emailSettings['smtp_port'] ?? 587) ?>" min="1" max="65535">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" name="smtp_username"
                                   value="<?= esc($emailSettings['smtp_username'] ?? '') ?>"
                                   autocomplete="username">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" name="smtp_password"
                                   placeholder="<?= !empty($emailSettings['smtp_password']) ? 'Leave blank to keep current password' : 'Enter SMTP password' ?>"
                                   autocomplete="new-password">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Encryption</label>
                            <select class="form-select" name="smtp_encryption">
                                <option value="" <?= ($emailSettings['smtp_encryption'] ?? '') === '' ? 'selected' : '' ?>>None</option>
                                <option value="tls" <?= ($emailSettings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($emailSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">From Email</label>
                            <input type="email" class="form-control" name="smtp_from_email"
                                   value="<?= esc($emailSettings['smtp_from_email'] ?? '') ?>"
                                   placeholder="backup@example.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">From Name</label>
                            <input type="text" class="form-control" name="smtp_from_name"
                                   value="<?= esc($emailSettings['smtp_from_name'] ?? 'Perfect LPG') ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Email Configuration
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <form method="post" action="<?= site_url('database-backup/email-settings/test') ?>">
                    <?= csrf_field() ?>
                    <label class="form-label fw-semibold">Test Email</label>
                    <div class="input-group">
                        <input type="email" class="form-control" name="test_email"
                               value="<?= esc($emailRecipients) ?>" placeholder="Enter test recipient" required>
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-send me-1"></i>Send Test Email
                        </button>
                    </div>
                    <div class="form-text">Save the SMTP configuration before sending the test email.</div>
                </form>
            </div>
        </div>
    </div>
    <?php if (!empty($isAdmin)): ?>
    <div class="tab-pane fade" id="refresh-pane" role="tabpanel" aria-labelledby="refresh-tab">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-white text-danger fw-semibold">Refresh Transactional Data</div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong>Destructive operation:</strong> This permanently removes transactional records such as sales, purchases, receipts, supplier payments, inventory movements, physical cylinder records, cash sessions/transactions, expenses, custody/deposit records and audit logs. It also deletes party records (customers and suppliers), cylinder types, LPG rates and rate-change history.
                    A safety database backup is created first. This does not replace a separately stored backup.
                </div>
                <p>Users, roles, permissions, locations and application configuration—including shop settings, cash registers and inventory policies—remain. Customers, suppliers, cylinder types and rates are cleared and must be configured again after refresh.</p>
                <form method="post" action="<?= site_url('database-backup/transactional-refresh') ?>"
                      data-confirm="FINAL WARNING: This will remove transactional data from the current database. A safety backup will be created first. Continue?">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="refreshConfirmation" class="form-label">Type <code>RESET TRANSACTIONAL DATA</code> to confirm</label>
                        <input type="text" class="form-control" id="refreshConfirmation" name="confirmation"
                               autocomplete="off" required pattern="RESET TRANSACTIONAL DATA"
                               title="Enter exactly: RESET TRANSACTIONAL DATA">
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Back Up and Refresh Transactional Data
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Saved Backup Files</span>
        <?php if ($backups): ?>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllBackups(true)">Select All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllBackups(false)">Clear Selection</button>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (!$backups): ?>
            <div class="p-3 text-muted">No saved SQL backups found. Create a backup above to get started.</div>
        <?php else: ?>
            <form method="post" id="backupActionsForm">
                <?= csrf_field() ?>
                <div class="d-flex flex-wrap gap-2 align-items-center p-3 border-bottom">
                    <input type="hidden" name="email_recipient" id="selectedBackupEmail">
                    <button type="submit" class="btn btn-outline-danger"
                            formaction="<?= site_url('database-backup/delete-selected') ?>"
                            onclick="return confirmSelectedBackups('delete');">
                        <i class="bi bi-trash me-1"></i>Delete Selected
                    </button>
                    <button type="submit" class="btn btn-outline-primary"
                            formaction="<?= site_url('database-backup/email-selected') ?>"
                            onclick="return emailSelectedBackups();">
                        <i class="bi bi-envelope me-1"></i>Send Selected via Email
                    </button>
                    <span class="text-muted small" id="selectedBackupCount">0 selected</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px;">
                                    <input class="form-check-input" type="checkbox" id="selectAllBackups" aria-label="Select all backups"
                                           onchange="toggleAllBackups(this.checked)">
                                </th>
                                <th>Backup File</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th class="text-end">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <input class="form-check-input backup-select" type="checkbox"
                                           name="backup_files[]" value="<?= esc($backup['name']) ?>" aria-label="Select backup <?= esc($backup['name']) ?>"
                                           onchange="updateBackupSelection()">
                                </td>
                                <td><code><?= esc($backup['name']) ?></code></td>
                                <td><?= esc(number_format($backup['size'] / 1048576, 2)) ?> MB</td>
                                <td><?= esc(date('Y-m-d H:i:s', $backup['modified'])) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="<?= site_url('database-backup/download?file=' . rawurlencode($backup['name'])) ?>">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function getBackupChecks() {
    return Array.from(document.querySelectorAll('.backup-select'));
}
function updateBackupSelection() {
    const checks = getBackupChecks();
    const selected = checks.filter(c => c.checked).length;
    const all = checks.length > 0 && selected === checks.length;
    const master = document.getElementById('selectAllBackups');
    if (master) {
        master.checked = all;
        master.indeterminate = selected > 0 && !all;
    }
    const count = document.getElementById('selectedBackupCount');
    if (count) count.textContent = selected + ' selected';
}
function toggleAllBackups(checked) {
    getBackupChecks().forEach(c => c.checked = checked);
    updateBackupSelection();
}
function confirmSelectedBackups(action) {
    const selected = getBackupChecks().filter(c => c.checked).length;
    if (!selected) {
        alert('Please select at least one backup file.');
        return false;
    }
    return action !== 'delete' || confirm('Delete ' + selected + ' selected backup file(s)? This action cannot be undone.');
}
function emailSelectedBackups() {
    const selected = getBackupChecks().filter(c => c.checked).length;
    if (!selected) {
        alert('Please select at least one backup file.');
        return false;
    }
    const recipient = document.getElementById('emailRecipient');
    if (!recipient || !recipient.value.trim()) {
        alert('Please enter an email address in the Backup tab first.');
        return false;
    }
    document.getElementById('selectedBackupEmail').value = recipient.value.trim();
    return confirm('Send ' + selected + ' selected backup file(s) to ' + recipient.value.trim() + '?');
}
updateBackupSelection();
</script>

<?= $this->endSection() ?>