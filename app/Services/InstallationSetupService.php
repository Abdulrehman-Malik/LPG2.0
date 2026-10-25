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
            $adminNote = null;
            $complete = false;
            $interrupted = false;
            $missing = [];
            $foreign = [];
            if ($exists) {
                $stmt = $db->prepare('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?');
                $stmt->bind_param('s', $s['name']);
                $stmt->execute();
                $names = array_map(static fn (array $r): string => strtolower((string) $r[0]), $stmt->get_result()->fetch_all());
                $tables = count($names);
                if ($tables > 0) {
                    $q = chr(96);
                    $dbName = $q . str_replace($q, $q . $q, $s['name']) . $q;
                    $known = $this->knownTables();
                    $missing = array_values(array_diff($this->schemaTables(), $names));
                    $foreign = array_values(array_diff($names, $known));
                    if (!in_array('users', $names, true) || !in_array('roles', $names, true)) {
                        $adminNote = 'The users or roles table does not exist.';
                    } else {
                        try {
                            $query = "SELECT COUNT(*) FROM " . $dbName . ".users u JOIN " . $dbName . ".roles r ON r.id=u.role_id WHERE UPPER(r.code)='ADMIN' AND u.is_active=1";
                            $adminExists = (int) $db->query($query)->fetch_row()[0] > 0;
                            if (!$adminExists) {
                                $adminNote = 'No active user has the ADMIN role.';
                            }
                        } catch (\Throwable $e) {
                            $adminNote = 'The administrator check failed: ' . $e->getMessage();
                        }
                    }
                    $state = null;
                    if (in_array('lpg_installation_state', $names, true)) {
                        $row = $db->query("SELECT state FROM " . $dbName . ".lpg_installation_state WHERE id=1")->fetch_assoc();
                        $state = $row['state'] ?? null;
                        $complete = $state === 'complete';
                    }
                    // An unfinished install of this app can be rebuilt: either the installer marked it
                    // 'installing', or setup never got as far as creating an administrator. Both only when
                    // no business document exists and every table is one this app creates, so another
                    // application's database under the same name is never treated as disposable.
                    $interrupted = !$complete && !$this->hasTransactions($db, $dbName)
                        && ($state === 'installing' || (!$adminExists && $foreign === []));
                }
            }
            return [
                'database' => $s['name'], 'exists' => $exists, 'tables' => $tables, 'has_data' => $tables > 0,
                'admin_exists' => $adminExists, 'admin_note' => $adminNote, 'complete' => $complete, 'interrupted' => $interrupted,
                'missing_tables' => $missing, 'foreign_tables' => $foreign,
            ];
        } finally {
            $db->close();
        }
    }

    /** Tables database/schema.sql creates. */
    private function schemaTables(): array
    {
        $sql = (string) @file_get_contents(ROOTPATH . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $sql, $m);
        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /** Every table this application creates: the schema, the SQL and CI4 migrations, and its bookkeeping tables. */
    private function knownTables(): array
    {
        $tables = $this->schemaTables();
        $files = array_merge(glob(ROOTPATH . 'database/migrations/*.sql') ?: [], glob(APPPATH . 'Database/Migrations/*.php') ?: []);
        foreach ($files as $file) {
            preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?|createTable\(\s*\'([A-Za-z0-9_]+)\'/i', (string) file_get_contents($file), $m);
            foreach (array_merge($m[1], $m[2]) as $t) {
                if ($t !== '') {
                    $tables[] = strtolower($t);
                }
            }
        }
        return array_values(array_unique(array_merge($tables, ['migrations', 'lpg_installation_state', 'lpg_migration_history'])));
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
        // Statement list only to name the one that fails (multi_query runs the file as written).
        $statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []), static fn (string $st): bool => $st !== '' && !str_starts_with($st, '--')));
        $index = 0;
        $fail = static function (string $error) use (&$index, $statements): RuntimeException {
            $statement = preg_replace('/\s+/', ' ', (string) ($statements[$index] ?? ''));
            return new RuntimeException('Schema execution failed at statement ' . ($index + 1) . ' (' . substr((string) $statement, 0, 80) . '…): ' . $error);
        };
        try {
            try {
                if (!$db->multi_query($sql)) throw $fail($db->error);
                do {
                    if ($result = $db->store_result()) $result->free();
                    if (!$db->more_results()) break;
                    $index++;
                    if (!$db->next_result()) throw $fail($db->error);
                } while (true);
            } catch (\mysqli_sql_exception $e) {
                throw $fail($e->getMessage());
            }
        } finally {
            $db->close();
        }
    }

    /** True when any posted business document exists (then a database is never treated as a disposable partial install). */
    private function hasTransactions(\mysqli $db, string $dbName): bool
    {
        foreach (['sales', 'purchases', 'customer_receipts', 'supplier_payments', 'expenses', 'cash_sessions'] as $table) {
            try {
                if ((int) $db->query('SELECT COUNT(*) FROM ' . $dbName . '.' . $table)->fetch_row()[0] > 0) return true;
            } catch (\Throwable $ignored) {
                // table not created yet
            }
        }
        return false;
    }

    private function recordState(string $state): void
    {
        $s = $this->settings();
        $db = new \mysqli($s['host'], $s['user'], $s['pass'], $s['name'], $s['port']);
        $db->set_charset('utf8mb4');
        try {
            $db->query("CREATE TABLE IF NOT EXISTS lpg_installation_state (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, state VARCHAR(20) NOT NULL, completed_at DATETIME NULL) ENGINE=InnoDB");
            $stmt = $db->prepare("INSERT INTO lpg_installation_state (id,state,completed_at) VALUES (1,?,IF(?='complete',NOW(),NULL)) ON DUPLICATE KEY UPDATE state=VALUES(state), completed_at=VALUES(completed_at)");
            $stmt->bind_param('ss', $state, $state);
            $stmt->execute();
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

        if ($state['has_data'] && !empty($state['interrupted'])) {
            // A previous run of this installer did not finish and no business documents exist:
            // rebuild it without the replacement safeguards meant for live data.
            $backup = (new DatabaseBackupService())->createBackup(false);
            $backupPath = (string) ($backup['path'] ?? '');
            if ($backupPath === '' || !is_file($backupPath)) {
                throw new RuntimeException('The unfinished installation was not rebuilt because a safety backup could not be made.');
            }
            log_message('warning', 'Rebuilding unfinished installation of database {database}. Safety backup: {backup}', ['database' => $state['database'], 'backup' => basename($backupPath)]);
            $this->replaceDatabase();
        } elseif ($state['has_data']) {
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

        $this->recordState('installing');
        $this->executeSchema();
        $result = (new MigrationRunnerService())->runPending();
        if (empty($result['success'])) throw new RuntimeException('Schema was created, but one or more migrations failed. Login remains blocked. ' . ($result['message'] ?? ''));
        $this->recordState('complete');
        log_message('info', 'Fresh database installation completed for database {database}.', ['database' => $state['database']]);
        return ['database' => $state['database'], 'backup' => $backupPath, 'migration_result' => $result];
    }
}
