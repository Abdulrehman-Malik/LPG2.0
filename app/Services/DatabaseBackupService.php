<?php

namespace App\Services;

use App\Models\ShopSettingsModel;
use Config\Backup;
use Config\Database;

class DatabaseBackupService
{
    private Backup $config;
    private array $db;

    public function __construct()
    {
        $this->config = config('Backup');
        $this->db = (array) config('Database')->default;
    }

    public function directory(): string
    {
        return $this->config->directory;
    }

    public function listBackups(): array
    {
        $dir = $this->directory();
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $files[] = [
                'name' => basename($path),
                'path' => $path,
                'size' => filesize($path) ?: 0,
                'modified' => filemtime($path) ?: 0,
            ];
        }

        usort($files, static fn(array $a, array $b): int => $b['modified'] <=> $a['modified']);
        return $files;
    }

    public function createBackup(bool $email = false, ?string $emailRecipient = null, bool $uploadToDrive = false): array
    {
        $this->ensureDirectory();

        if ($this->config->mysqldumpPath === '' || !is_file($this->config->mysqldumpPath)) {
            return $this->createNativeBackup($email, $emailRecipient, $uploadToDrive);
        }

        $database = trim((string) ($this->db['database'] ?? ''));
        if ($database === '') {
            throw new \RuntimeException('Database name is not configured.');
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $database)
            . '_' . date('Ymd_His') . '.sql';
        $path = $this->directory() . DIRECTORY_SEPARATOR . $filename;

        $exitCode = $this->runMysqlUtility($this->config->mysqldumpPath, $path, false);
        if ($exitCode !== 0 || !is_file($path) || (filesize($path) ?: 0) < 10) {
            @unlink($path);
            throw new \RuntimeException('Database backup failed. Check the MySQL client configuration and writable backup directory.');
        }

        $emailSent = false;
        $emailError = null;
        if ($email) {
            try {
                $emailSent = $this->emailBackup($path, $emailRecipient);
            } catch (\Throwable $e) {
                $emailError = $e->getMessage();
                log_message('error', 'Database backup email delivery failed: {error}', ['error' => $emailError]);
            }
        }

        $driveUploaded = false;
        $driveError = null;
        if ($uploadToDrive) {
            try {
                $this->uploadBackupToGoogleDrive($path);
                $driveUploaded = true;
            } catch (\Throwable $e) {
                $driveError = $e->getMessage();
                log_message('error', 'Google Drive backup upload failed: {error}', ['error' => $driveError]);
            }
        }

        return ['path' => $path, 'name' => $filename, 'email_sent' => $emailSent, 'email_error' => $emailError,
            'drive_uploaded' => $driveUploaded, 'drive_error' => $driveError];
    }

    public function restore(string $uploadedPath): array
    {
        if (!is_file($uploadedPath)) {
            throw new \RuntimeException('The selected backup file could not be found.');
        }

        $extension = strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION));
        if ($extension !== 'sql') {
            throw new \RuntimeException('Only .sql database backup files can be restored.');
        }

        if ($this->config->mysqlPath !== '' && is_file($this->config->mysqlPath)) {
            $exitCode = $this->runMysqlUtility($this->config->mysqlPath, $uploadedPath, true);
            if ($exitCode !== 0) {
                throw new \RuntimeException('Database restore failed. The database may be partially restored; review the MySQL error log before retrying.');
            }
        } else {
            $this->restoreNativeBackup($uploadedPath);
        }

        return ['name' => basename($uploadedPath)];
    }

    public function sendTestEmail(string $recipient): bool
    {
        $settings = $this->emailSettings();
        if (!(int) ($settings['smtp_enabled'] ?? 0)) {
            throw new \RuntimeException('SMTP email sending is disabled. Enable it in Email Configuration first.');
        }

        $email = $this->buildEmail($settings);
        $email->setTo($recipient);
        $email->setSubject('Perfect LPG - SMTP Test Email');
        $email->setMessage(
            'This is a test email from Perfect LPG. SMTP configuration is working.'
        );

        if (!$email->send()) {
            $debug = $email->printDebugger(['headers', 'subject']);
            log_message('error', 'SMTP test email failed: {debug}', ['debug' => $debug]);
            throw new \RuntimeException('Test email could not be sent. Check the SMTP host, port, username, password and encryption settings.');
        }

        return true;
    }

    /**
     * Portable table-and-data backup for hosts where mysqldump is not installed.
     * The marker lets the portable restore path recognize files generated here.
     */
    private function createNativeBackup(bool $email = false, ?string $emailRecipient = null, bool $uploadToDrive = false): array
    {
        $this->ensureDirectory();
        $database = trim((string) ($this->db['database'] ?? ''));
        if ($database === '') {
            throw new \RuntimeException('Database name is not configured.');
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $database)
            . '_' . date('Ymd_His') . '.sql';
        $path = $this->directory() . DIRECTORY_SEPARATOR . $filename;
        $db = \Config\Database::connect();
        $handle = @fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create the database backup file.');
        }

        try {
            fwrite($handle, "-- PERFECT_LPG_NATIVE_BACKUP_V1\nSET FOREIGN_KEY_CHECKS=0;\n");
            $tablesResult = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            foreach ($tablesResult->getResultArray() as $tableRow) {
                $table = (string) array_values($tableRow)[0];
                if (!preg_match('/^[A-Za-z0-9_$]+$/', $table)) {
                    throw new \RuntimeException('A database table name contains unsupported characters.');
                }
                $quotedTable = chr(96) . $table . chr(96);
                $createRow = $db->query('SHOW CREATE TABLE ' . $quotedTable)->getRowArray();
                if (!$createRow || count($createRow) < 2) {
                    throw new \RuntimeException('Could not read the schema for table ' . $table . '.');
                }
                $createSql = (string) array_values($createRow)[1];
                fwrite($handle, "\n-- Table: " . $table . "\nDROP TABLE IF EXISTS " . $quotedTable . ";\n");
                fwrite($handle, rtrim($createSql, ";\r\n") . ";\n");

                $rows = $db->query('SELECT * FROM ' . $quotedTable)->getResultArray();
                foreach ($rows as $row) {
                    $columns = [];
                    $values = [];
                    foreach ($row as $column => $value) {
                        if (!preg_match('/^[A-Za-z0-9_$]+$/', (string) $column)) {
                            throw new \RuntimeException('A database column name contains unsupported characters.');
                        }
                        $columns[] = chr(96) . $column . chr(96);
                        $values[] = $value === null ? 'NULL' : $db->escape($value);
                    }
                    if ($columns) {
                        fwrite($handle, 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns)
                            . ') VALUES (' . implode(', ', $values) . ");\n");
                    }
                }
            }
            fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($path);
            throw $e;
        }
        fclose($handle);

        if (!is_file($path) || (filesize($path) ?: 0) < 50) {
            @unlink($path);
            throw new \RuntimeException('Database backup did not contain usable data.');
        }

        $emailSent = false;
        $emailError = null;
        if ($email) {
            try {
                $emailSent = $this->emailBackup($path, $emailRecipient);
            } catch (\Throwable $e) {
                $emailError = $e->getMessage();
                log_message('error', 'Database backup email delivery failed: {error}', ['error' => $emailError]);
            }
        }

        $driveUploaded = false;
        $driveError = null;
        if ($uploadToDrive) {
            try {
                $this->uploadBackupToGoogleDrive($path);
                $driveUploaded = true;
            } catch (\Throwable $e) {
                $driveError = $e->getMessage();
                log_message('error', 'Google Drive backup upload failed: {error}', ['error' => $driveError]);
            }
        }

        return ['path' => $path, 'name' => $filename, 'email_sent' => $emailSent, 'email_error' => $emailError,
            'drive_uploaded' => $driveUploaded, 'drive_error' => $driveError];
    }

    private function restoreNativeBackup(string $path): void
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read the selected backup file.');
        }
        if (!str_starts_with($contents, "-- PERFECT_LPG_NATIVE_BACKUP_V1")) {
            throw new \RuntimeException(
                'This server has no mysql client installed and this is not a portable LPG backup. Restore a backup created by this page, or configure backup.mysqlPath.'
            );
        }

        $db = \Config\Database::connect();
        foreach ($this->splitSqlStatements($contents) as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            $db->query($statement);
        }
    }

    /**
     * Split SQL at semicolons outside quoted strings and comments.
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = '';
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= "\n";
                }
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }
            if ($quote !== '') {
                $buffer .= $char;
                if ($char === chr(92) && $quote !== chr(96) && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                } elseif ($char === $quote) {
                    if ($next === $quote && $quote !== chr(96)) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = '';
                    }
                }
                continue;
            }

            if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))
                || $char === '#') {
                $lineComment = true;
                $i += $char === '-' ? 1 : 0;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === chr(96)) {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }

    /**
     * Upload a backup using Google Drive API v3 and an OAuth refresh token.
     * Credentials are supplied only through deployment environment variables.
     */
    public function uploadBackupToGoogleDrive(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Backup file is missing; Google Drive upload was skipped.');
        }
        $clientId = trim((string) $this->config->googleDriveClientId);
        $clientSecret = trim((string) $this->config->googleDriveClientSecret);
        $refreshToken = trim((string) $this->config->googleDriveRefreshToken);
        $folderId = trim((string) $this->config->googleDriveFolderId);
        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new \RuntimeException('Google Drive is not configured. Set BACKUP_GOOGLE_DRIVE_CLIENT_ID, BACKUP_GOOGLE_DRIVE_CLIENT_SECRET and BACKUP_GOOGLE_DRIVE_REFRESH_TOKEN in the environment.');
        }

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('The PHP cURL extension is required for Google Drive uploads.');
        }

        $token = $this->requestGoogleDriveAccessToken($clientId, $clientSecret, $refreshToken);
        $metadata = ['name' => basename($path)];
        if ($folderId !== '') {
            $metadata['parents'] = [$folderId];
        }
        $fileSize = filesize($path);
        if ($fileSize === false || $fileSize <= 0) {
            throw new \RuntimeException('The backup file is empty or its size could not be read.');
        }

        // Start a resumable upload so large database files are streamed from disk
        // instead of loaded into PHP memory in one operation.
        $uploadUrl = null;
        $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,webViewLink');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; charset=UTF-8',
                'X-Upload-Content-Type: application/sql',
                'X-Upload-Content-Length: ' . $fileSize,
            ],
            CURLOPT_POSTFIELDS => json_encode($metadata, JSON_THROW_ON_ERROR),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$uploadUrl): int {
                if (stripos($header, 'Location:') === 0) {
                    $uploadUrl = trim(substr($header, strlen('Location:')));
                }
                return strlen($header);
            },
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 30,
        ]);
        $initResponse = curl_exec($ch);
        $initError = curl_error($ch);
        $initStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($initResponse === false || $initStatus < 200 || $initStatus >= 300 || !$uploadUrl) {
            log_message('error', 'Google Drive resumable upload initialization failed (HTTP {status}): {response}; cURL: {curlError}', [
                'status' => $initStatus, 'response' => substr((string) $initResponse, 0, 1000), 'curlError' => $initError,
            ]);
            throw new \RuntimeException('Google Drive could not start the backup upload. Check Drive API access and folder permissions.');
        }

        $fileHandle = @fopen($path, 'rb');
        if ($fileHandle === false) {
            throw new \RuntimeException('Unable to read the backup file for Google Drive upload.');
        }
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fileHandle,
            CURLOPT_INFILESIZE => $fileSize,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/sql',
            ],
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 600,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fileHandle);

        if ($response === false || $status < 200 || $status >= 300) {
            log_message('error', 'Google Drive API upload response (HTTP {status}): {response}; cURL: {curlError}', [
                'status' => $status, 'response' => substr((string) $response, 0, 1500), 'curlError' => $curlError,
            ]);
            throw new \RuntimeException('Backup was created, but Google Drive upload failed (HTTP ' . $status . '). Check Drive API access, folder permissions, and deployment logs.');
        }

        $result = json_decode((string) $response, true);
        if (!is_array($result) || empty($result['id'])) {
            throw new \RuntimeException('Google Drive did not confirm that the backup file was uploaded.');
        }
        return $result;
    }

    private function requestGoogleDriveAccessToken(string $clientId, string $clientSecret, string $refreshToken): string
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode((string) $response, true);
        if ($response === false || $status < 200 || $status >= 300 || empty($result['access_token'])) {
            log_message('error', 'Google OAuth token refresh failed (HTTP {status}): {response}; cURL: {curlError}', [
                'status' => $status, 'response' => substr((string) $response, 0, 1000), 'curlError' => $curlError,
            ]);
            throw new \RuntimeException('Google Drive authorization failed. Check OAuth client credentials and refresh token.');
        }
        return (string) $result['access_token'];
    }

    private function ensureDirectory(): void
    {
        $dir = $this->directory();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Backup directory could not be created: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException('Backup directory is not writable: ' . $dir);
        }
    }

    private function runMysqlUtility(string $binary, string $file, bool $restore): int
    {
        $host = (string) ($this->db['hostname'] ?? 'localhost');
        $user = (string) ($this->db['username'] ?? '');
        $password = (string) ($this->db['password'] ?? '');
        $port = (int) ($this->db['port'] ?? 3306);
        $database = (string) ($this->db['database'] ?? '');

        $optionFile = tempnam(sys_get_temp_dir(), 'lpg-db-');
        if ($optionFile === false) {
            throw new \RuntimeException('Unable to create a temporary MySQL credential file.');
        }

        $ini = "[client]\n"
            . 'host=' . $host . "\n"
            . 'port=' . $port . "\n"
            . 'user=' . $user . "\n"
            . 'password=' . $password . "\n";
        file_put_contents($optionFile, $ini);
        @chmod($optionFile, 0600);

        try {
            $command = escapeshellarg($binary)
                . ' --defaults-extra-file=' . escapeshellarg($optionFile)
                . ' ' . escapeshellarg($database);

            if ($restore) {
                $command .= ' < ' . escapeshellarg($file);
            } else {
                $command .= ' --single-transaction --routines --triggers --events --hex-blob > ' . escapeshellarg($file);
            }

            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open($command, $descriptor, $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start the MySQL utility.');
            }

            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0 && trim($stderr) !== '') {
                log_message('error', 'Database backup/restore MySQL error: {error}', ['error' => trim($stderr)]);
            }

            return $exitCode;
        } finally {
            @unlink($optionFile);
        }
    }

    private function emailSettings(): array
    {
        $locationId = (int) session()->get('location_id');
        if ($locationId <= 0) {
            throw new \RuntimeException('No shop/location is selected for email configuration.');
        }

        $settings = (new ShopSettingsModel())->forLocation($locationId);
        if (trim((string) ($settings['smtp_host'] ?? '')) === '') {
            throw new \RuntimeException('SMTP host is not configured. Open Email Configuration and save the SMTP settings.');
        }
        if (trim((string) ($settings['smtp_from_email'] ?? '')) === '') {
            throw new \RuntimeException('From Email is not configured. Open Email Configuration and save the SMTP settings.');
        }

        return $settings;
    }

    private function buildEmail(array $settings)
    {
        $encryption = trim((string) ($settings['smtp_encryption'] ?? ''));
        // Keep the hostname separate from the encryption setting. CI4's email
        // configuration supports TLS/SSL through SMTPCrypto and the port.
        $host = trim((string) $settings['smtp_host']);

        $email = service('email');
        $email->initialize([
            'protocol' => 'smtp',
            'SMTPHost' => $host,
            'SMTPUser' => (string) ($settings['smtp_username'] ?? ''),
            'SMTPPass' => (string) ($settings['smtp_password'] ?? ''),
            'SMTPPort' => (int) ($settings['smtp_port'] ?? 587),
            'SMTPTimeout' => 30,
            'SMTPKeepAlive' => false,
            'SMTPCrypto' => in_array($encryption, ['tls', 'ssl'], true) ? $encryption : '',
            'wordWrap' => true,
            'wrapChars' => 76,
            'mailType' => 'text',
            'charset' => 'UTF-8',
            'validate' => true,
            'newline' => "\r\n",
            'CRLF' => "\r\n",
        ]);
        $email->setFrom((string) $settings['smtp_from_email'], (string) ($settings['smtp_from_name'] ?: 'Perfect LPG'));

        return $email;
    }

    private function emailBackup(string $path, ?string $emailRecipient = null): bool
    {
        $settings = $this->emailSettings();
        if (!(int) ($settings['smtp_enabled'] ?? 0)) {
            throw new \RuntimeException('Email backup was requested, but SMTP email sending is disabled. Enable it in Email Configuration first.');
        }

        $recipients = trim((string) $emailRecipient);
        if ($recipients === '') {
            throw new \RuntimeException('Email backup was requested, but no recipient email address is configured.');
        }

        $email = $this->buildEmail($settings);
        $email->setTo($recipients);
        $email->setSubject('Perfect LPG Database Backup - ' . date('Y-m-d H:i:s'));
        $email->setMessage(
            'Attached is the database backup created by Perfect LPG on ' . date('Y-m-d H:i:s') . '.'
        );
        $email->attach($path);

        if (!$email->send()) {
            $debug = $email->printDebugger(['headers', 'subject']);
            log_message('error', 'Database backup email failed: {debug}', ['debug' => $debug]);
            throw new \RuntimeException('Backup was created, but the email could not be sent. Check the SMTP configuration on the Email Configuration tab.');
        }

        return true;
    }
}
