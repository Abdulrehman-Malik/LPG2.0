<?php

namespace App\Controllers;

use App\Models\ShopSettingsModel;
use App\Services\DatabaseBackupService;
use CodeIgniter\Controller;

class DatabaseBackup extends Controller
{
    private function guard()
    {
        return \App\Services\PermissionService::allows('BACKUP_MANAGE')
            ? null
            : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;

        $service = new DatabaseBackupService();
        $locationId = (int) session()->get('location_id');
        $settings = (new ShopSettingsModel())->forLocation($locationId);

        return view('database-backup/index', [
            'title' => 'Database Backup & Restore',
            'backups' => $service->listBackups(),
            'backupDirectory' => $service->directory(),
            'maxUploadSize' => config('Backup')->maxUploadSize,
            'emailRecipients' => (string) session()->get('database_backup_email'),
            'emailSettings' => $settings,
            'isAdmin' => strtoupper((string) session()->get('role')) === 'ADMIN',
        ]);
    }

    public function create()
    {
        if ($r = $this->guard()) return $r;

        try {
            $emailRecipient = trim((string) $this->request->getPost('email_recipient'));
            if ($emailRecipient !== '' && !filter_var($emailRecipient, FILTER_VALIDATE_EMAIL)) {
                return redirect()->back()->withInput()->with('error', 'Please enter a valid email address.');
            }

            if ($emailRecipient !== '') {
                session()->set('database_backup_email', $emailRecipient);
            }

            $result = (new DatabaseBackupService())->createBackup(
                $this->request->getPost('send_email') === '1',
                $emailRecipient !== '' ? $emailRecipient : (string) session()->get('database_backup_email'),
                $this->request->getPost('upload_drive') === '1'
            );

            $message = 'Database backup created successfully: ' . $result['name'];
            $deliveryWarning = false;
            if ($result['email_sent']) {
                $message .= ' Email sent successfully.';
            } elseif (!empty($result['email_error'])) {
                $message .= ' WARNING: Email delivery failed: ' . $result['email_error'];
                $deliveryWarning = true;
            }
            if (!empty($result['drive_uploaded'])) {
                $message .= ' Uploaded to Google Drive successfully.';
            } elseif (!empty($result['drive_error'])) {
                $message .= ' WARNING: Google Drive upload failed: ' . $result['drive_error'];
                $deliveryWarning = true;
            }
            return redirect()->to('/database-backup')->with($deliveryWarning ? 'error' : 'success', $message);
        } catch (\Throwable $e) {
            log_message('error', 'Database backup failed: {error}', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function saveEmailSettings()
    {
        if ($r = $this->guard()) return $r;

        $locationId = (int) session()->get('location_id');
        $host = trim((string) $this->request->getPost('smtp_host'));
        $port = (int) $this->request->getPost('smtp_port');
        $username = trim((string) $this->request->getPost('smtp_username'));
        $password = (string) $this->request->getPost('smtp_password');
        $encryption = trim((string) $this->request->getPost('smtp_encryption'));
        $fromEmail = trim((string) $this->request->getPost('smtp_from_email'));
        $fromName = trim((string) $this->request->getPost('smtp_from_name'));
        $enabled = $this->request->getPost('smtp_enabled') ? 1 : 0;

        if ($enabled) {
            if ($host === '' || $port < 1 || $port > 65535) {
                return redirect()->back()->withInput()->with('error', 'Enter a valid SMTP host and port.');
            }
            if (!in_array($encryption, ['', 'tls', 'ssl'], true)) {
                return redirect()->back()->withInput()->with('error', 'Invalid SMTP encryption type.');
            }
            if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                return redirect()->back()->withInput()->with('error', 'Enter a valid From Email address.');
            }
        }

        $model = new ShopSettingsModel();
        $settings = $model->forLocation($locationId);

        $data = [
            'location_id' => $locationId,
            'smtp_host' => $host,
            'smtp_port' => $port ?: 587,
            'smtp_username' => $username ?: null,
            'smtp_encryption' => $encryption,
            'smtp_from_email' => $fromEmail ?: null,
            'smtp_from_name' => $fromName ?: 'Perfect LPG',
            'smtp_enabled' => $enabled,
        ];

        // Keep the existing password when the password field is left blank.
        if ($password !== '') {
            $data['smtp_password'] = $password;
        } elseif (!empty($settings['smtp_password'])) {
            $data['smtp_password'] = $settings['smtp_password'];
        } else {
            $data['smtp_password'] = null;
        }

        try {
            if ((int) ($settings['id'] ?? 0) > 0) {
                $model->update((int) $settings['id'], $data);
            } else {
                $model->insert($data);
            }

            return redirect()->to('/database-backup')->with('success', 'Email/SMTP configuration saved successfully.');
        } catch (\Throwable $e) {
            log_message('error', 'SMTP settings save failed: {error}', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Email configuration could not be saved: ' . $e->getMessage());
        }
    }

    public function testEmail()
    {
        if ($r = $this->guard()) return $r;

        $recipient = trim((string) $this->request->getPost('test_email'));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid test email address.');
        }

        try {
            (new DatabaseBackupService())->sendTestEmail($recipient);
            return redirect()->to('/database-backup')->with('success', 'Test email sent successfully.');
        } catch (\Throwable $e) {
            log_message('error', 'Test email failed: {error}', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function deleteSelected()
    {
        if ($r = $this->guard()) return $r;

        $names = $this->request->getPost('backup_files');
        $names = is_array($names) ? $names : [];
        if (!$names) {
            return redirect()->back()->with('error', 'Select at least one backup file to delete.');
        }

        $service = new DatabaseBackupService();
        $allowed = [];
        foreach ($service->listBackups() as $backup) {
            $allowed[basename($backup['name'])] = $backup['path'];
        }

        $deleted = 0;
        foreach ($names as $name) {
            $name = basename((string) $name);
            if (isset($allowed[$name]) && @unlink($allowed[$name])) {
                $deleted++;
            }
        }

        return redirect()->to('/database-backup')->with(
            $deleted ? 'success' : 'error',
            $deleted ? $deleted . ' backup file(s) deleted successfully.' : 'No selected backup files could be deleted.'
        );
    }

    public function emailSelected()
    {
        if ($r = $this->guard()) return $r;

        $names = $this->request->getPost('backup_files');
        $names = is_array($names) ? $names : [];
        $recipient = trim((string) $this->request->getPost('email_recipient'));

        if (!$names) {
            return redirect()->back()->with('error', 'Select at least one backup file to email.');
        }
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Enter a valid email address.');
        }

        $service = new DatabaseBackupService();
        $allowed = [];
        foreach ($service->listBackups() as $backup) {
            $allowed[basename($backup['name'])] = $backup['path'];
        }

        $emailMethod = new \ReflectionMethod($service, 'emailBackup');
        $emailMethod->setAccessible(true);
        $sent = 0;

        try {
            foreach ($names as $name) {
                $name = basename((string) $name);
                if (!isset($allowed[$name])) {
                    continue;
                }
                $emailMethod->invoke($service, $allowed[$name], $recipient);
                $sent++;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Selected backup email failed: {error}', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($sent === 0) {
            return redirect()->back()->with('error', 'No valid selected backup files were found.');
        }

        session()->set('database_backup_email', $recipient);
        return redirect()->to('/database-backup')->with('success', $sent . ' selected backup file(s) emailed successfully.');
    }

    public function download()
    {
        if ($r = $this->guard()) return $r;

        $name = basename((string) $this->request->getGet('file'));
        if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+\.sql$/i', $name)) {
            return $this->response->setStatusCode(400)->setBody('Invalid backup file name.');
        }

        foreach ((new DatabaseBackupService())->listBackups() as $backup) {
            if ($backup['name'] === $name && is_file($backup['path'])) {
                return $this->response->download($backup['path'], null)->setFileName($backup['name']);
            }
        }

        return $this->response->setStatusCode(404)->setBody('Backup file not found.');
    }


    /**
     * Admin-only destructive refresh of transactional data.
     * A safety backup must complete before any data is removed.
     */
    public function refreshTransactionalData()
    {
        // Clears the data of every business: System Administrators only (a business owner holds
        // the ADMIN role too), and never in production (BL-016).
        if ($r = $this->guard()) return $r;
        if (ENVIRONMENT === 'production') {
            return redirect()->to('/database-backup')->with('error', 'The transactional data refresh is disabled in production.');
        }
        if ($this->request->getPost('confirmation') !== 'RESET TRANSACTIONAL DATA') {
            return redirect()->to('/database-backup')->with('error', 'Refresh cancelled. Enter the exact confirmation phrase to continue.');
        }

        $refreshAttempted = false;
        try {
            $backup = (new DatabaseBackupService())->createBackup(false);
            $backupPath = (string) ($backup['path'] ?? '');
            if ($backupPath === '' || !is_file($backupPath) || (filesize($backupPath) ?: 0) < 50) {
                throw new \RuntimeException('Refresh cancelled because a valid safety backup could not be verified.');
            }
            $refreshAttempted = true;
            $result = (new \App\Services\TransactionalDataRefreshService())->refresh();
            \App\Services\AuditService::log('TRANSACTIONAL_REFRESH', 'database', null, null, ['tables_cleared' => $result['tables_cleared'], 'safety_backup' => $backup['name'] ?? null], (int) session()->get('user_id'), (int) session()->get('location_id') ?: null);
            $message = 'Transactional data refresh completed. Safety backup: ' . ($backup['name'] ?? 'created') .
                '. Tables cleared: ' . count($result['tables_cleared']) . '. Master data and application settings were preserved.';
            return redirect()->to('/database-backup')->with('success', $message);
        } catch (\Throwable $e) {
            log_message('error', 'Transactional data refresh failed for user ID {user_id}: {error}', [
                'user_id' => (int) session()->get('user_id'),
                'error' => $e->getMessage(),
            ]);
            $message = 'Transactional data refresh failed: ' . $e->getMessage();
            if ($refreshAttempted) {
                $message .= ' The database may be partially refreshed. Restore the safety backup before resuming operations if data is inconsistent.';
            }
            return redirect()->to('/database-backup')->with('error', $message);
        }
    }

    public function restore()
    {
        if ($r = $this->guard()) return $r;
        // A restore replaces the data of every business; in production it needs an explicit
        // opt-in (backup.allowRestoreInProduction = true in .env) (BL-016).
        if (ENVIRONMENT === 'production' && !filter_var(env('backup.allowRestoreInProduction', false), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->back()->with('error', 'Database restore is disabled in production. Set backup.allowRestoreInProduction = true in .env for a planned restore.');
        }

        $file = $this->request->getFile('backup_file');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'Please select a valid .sql backup file.');
        }

        $extension = strtolower($file->getClientExtension());
        if ($extension !== 'sql') {
            return redirect()->back()->with('error', 'Only .sql backup files are allowed.');
        }

        if ($file->getSize() > config('Backup')->maxUploadSize) {
            return redirect()->back()->with('error', 'The selected backup file is larger than the configured upload limit.');
        }

        $temporary = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'restore_' . bin2hex(random_bytes(8)) . '.sql';
        if (!is_dir(dirname($temporary))) {
            mkdir(dirname($temporary), 0775, true);
        }

        try {
            if (!$file->move(dirname($temporary), basename($temporary), true)) {
                throw new \RuntimeException('The backup file could not be uploaded.');
            }

            (new DatabaseBackupService())->createBackup(false);
            $result = (new DatabaseBackupService())->restore($temporary);
            // Written after the restore, so it is part of the restored audit log.
            \App\Services\AuditService::log('DATABASE_RESTORED', 'database', null, null, ['file' => $result['name'] ?? null], (int) session()->get('user_id'), null);

            return redirect()->to('/database-backup')->with(
                'success',
                'Database restored successfully from ' . $result['name'] . '. A safety backup was created before the restore.'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Database restore failed: {error}', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', $e->getMessage());
        } finally {
            @unlink($temporary);
        }
    }
}