<?php

namespace App\Services;

use RuntimeException;

class InstallationSetupService
{
    public function enabled(): bool
    {
        return filter_var(env('FRESH_DATABASE_SETUP', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function settings(): array
    {
        $db = (array) config('Database')->default;
        $name = trim((string) ($db['database'] ?? ''));
        if ($name === '' || !preg_match('/^[A-Za-z0-9_$-]+$/', $name)) {
            throw new RuntimeException('The configured database name is invalid. Check database.default.database / LPG_DB_NAME in .env.');
        }
        return [
            'host' => (string) ($db['hostname'] ?? 'localhost'),
            'user' => (string) ($db['username'] ?? ''),
            'pass' => (string) ($db['password'] ?? ''),
            'name' => $name,
            'port' => (int) ($db['port'] ?? 3306),
        ];
    }

    private function connectServer(): \mysqli
    {
        if (!extension_loaded('mysqli')) {
            throw new RuntimeException('The PHP mysqli extension is required for installation.');
        }
        $s = $this->settings();
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new \mysqli($s['host'], $s['user'], $s['pass'], '', $s['port']);
        $db->set_charset('utf8mb4');
        return $db;
    }

    public function inspect(): array
    {
        $s = $this->settings();
        $db = $this->connectServer();
        try {
            $stmt = $db->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->bind_param('s', $s['name']);
            $stmt->execute();
            $exists = (bool) $stmt->get_result()->fetch_row();
            $tables = 0;
            $adminExists = false;
            $complete = false;
            if ($exists) {
                $stmt = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?');
                $stmt->bind_param('s', $s['name']);
                $stmt->execute();
                $tables = (int) $stmt->get_result()->fetch_row()[0];
                if ($tables > 0) {
                    $q = chr(96);
                    $dbName = $q . str_replace($q, $q . $q, $s['name']) . $q;
                    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='users'");
                    $stmt->bind_param('s', $s['name']);
                    $stmt->execute();
                    if ((int) $stmt->get_result()->fetch_row()[0] > 0) {
                        try {
                            $query = "SELECT COUNT(*) FROM " . $dbName . ".users u JOIN " . $dbName . ".roles r ON r.id=u.role_id WHERE UPPER(r.code)='ADMIN' AND u.is_active=1";
                            $adminExists = (int) $db->query($query)->fetch_row()[0] > 0;
                        } catch (\Throwable $ignored) {
                            $adminExists = false;
                        }
                    }
                    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='lpg_installation_state'");
                    $stmt->bind_param('s', $s['name']);
                    $stmt->execute();
                    if ((int) $stmt->get_result()->fetch_row()[0] > 0) {
                        $result = $db->query("SELECT state FROM " . $dbName . ".lpg_installation_state WHERE id=1");
                        $row = $result->fetch_assoc();
                        $complete = ($row['state'] ?? '') === 'complete';
                    }
                }
            }
            return ['database' => $s['name'], 'exists' => $exists, 'tables' => $tables, 'has_data' => $tables > 0, 'admin_exists' => $adminExists, 'complete' => $complete];
        } finally {
            $db->close();
        }
    }

    public function shouldRedirectToSetup(): bool
    {
        if (!$this->enabled()) return false;
        try {
            return !$this->inspect()['complete'];
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function verifyAdminPassword(string $password): bool
    {
        $s = $this->settings();
        $db = $this->connectServer();
        try {
            $q = chr(96);
            $dbName = $q . str_replace($q, $q . $q, $s['name']) . $q;
            $query = "SELECT u.password_hash FROM " . $dbName . ".users u JOIN " . $dbName . ".roles r ON r.id=u.role_id WHERE UPPER(r.code)='ADMIN' AND u.is_active=1 ORDER BY u.id LIMIT 100";
            $result = $db->query($query);
            while ($row = $result->fetch_assoc()) {
                if (password_verify($password, (string) $row['password_hash'])) return true;
            }
            return false;
        } finally {
            $db->close();
        }
    }

    private function createDatabase(): void
    {
        $s = $this->settings();
        $db = $this->connectServer();
        try {
            $q = chr(96);
            $name = $q . str_replace($q, $q . $q, $s['name']) . $q;
            $db->query("CREATE DATABASE " . $name . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } finally {
            $db->close();
        }
    }

    private function replaceDatabase(): void
    {
        $s = $this->settings();
        $db = $this->connectServer();
        try {
            $q = chr(96);
            $name = $q . str_replace($q, $q . $q, $s['name']) . $q;
            $db->query("DROP DATABASE " . $name);
            $db->query("CREATE DATABASE " . $name . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } finally {
            $db->close();
        }
    }

    private function executeSchema(): void
    {
        $path = ROOTPATH . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        $sql = @file_get_contents($path);
        if ($sql === false || trim($sql) === '') throw new RuntimeException('database/schema.sql is missing or empty.');
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+|DROP\s+TABLE)\b/im', $sql)) {
            throw new RuntimeException('The install schema must not select a hard-coded database or drop tables. Update database/schema.sql before running setup.');
        }
        $s = $this->settings();
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new \mysqli($s['host'], $s['user'], $s['pass'], $s['name'], $s['port']);
        $db->set_charset('utf8mb4');
        try {
            if (!$db->multi_query($sql)) throw new RuntimeException('Schema execution failed: ' . $db->error);
            do {
                if ($result = $db->store_result()) $result->free();
                if (!$db->more_results()) break;
                if (!$db->next_result()) throw new RuntimeException('Schema execution failed: ' . $db->error);
            } while (true);
        } finally {
            $db->close();
        }
    }

    private function recordComplete(): void
    {
        $s = $this->settings();
        $db = new \mysqli($s['host'], $s['user'], $s['pass'], $s['name'], $s['port']);
        $db->set_charset('utf8mb4');
        try {
            $db->query("CREATE TABLE IF NOT EXISTS lpg_installation_state (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, state VARCHAR(20) NOT NULL, completed_at DATETIME NULL) ENGINE=InnoDB");
            $db->query("INSERT INTO lpg_installation_state (id,state,completed_at) VALUES (1,'complete',NOW()) ON DUPLICATE KEY UPDATE state='complete', completed_at=NOW()");
        } finally {
            $db->close();
        }
    }

    public function install(array $post): array
    {
        if (!$this->enabled()) throw new RuntimeException('Fresh database setup is disabled. Set FRESH_DATABASE_SETUP=true in .env to enable it.');
        $state = $this->inspect();
        if (($post['confirm'] ?? '') !== 'INSTALL DATABASE') throw new RuntimeException('Please enter INSTALL DATABASE in the confirmation field.');
        $backupPath = null;

        if ($state['has_data']) {
            if (!$state['admin_exists']) throw new RuntimeException('This database contains tables but no verifiable active administrator was found. Replacement is blocked for safety.');
            $password = (string) ($post['admin_password'] ?? '');
            if ($password === '' || !$this->verifyAdminPassword($password)) {
                usleep(500000);
                throw new RuntimeException('Administrator password verification failed. The database was not changed.');
            }
            if (($post['replace_confirm'] ?? '') !== 'REPLACE DATABASE') throw new RuntimeException('To replace this existing database, enter REPLACE DATABASE in the destructive confirmation field.');
            $backup = (new DatabaseBackupService())->createBackup(false);
            $backupPath = (string) ($backup['path'] ?? '');
            if ($backupPath === '' || !is_file($backupPath) || (filesize($backupPath) ?: 0) < 50) {
                throw new RuntimeException('The existing database was not changed because a valid safety backup could not be verified.');
            }
            log_message('warning', 'Fresh setup authorized by active administrator; database {database} will be replaced. Safety backup: {backup}', ['database' => $state['database'], 'backup' => basename($backupPath)]);
            $this->replaceDatabase();
        } elseif (!$state['exists']) {
            $this->createDatabase();
        }

        $this->executeSchema();
        $result = (new MigrationRunnerService())->runPending();
        if (empty($result['success'])) throw new RuntimeException('Schema was created, but one or more migrations failed. Login remains blocked. ' . ($result['message'] ?? ''));
        $this->recordComplete();
        log_message('info', 'Fresh database installation completed for database {database}.', ['database' => $state['database']]);
        return ['database' => $state['database'], 'backup' => $backupPath, 'migration_result' => $result];
    }
}
