<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

class MigrationRunnerService
{
    protected $db;
    protected string $historyTable = 'lpg_migration_history';
    protected string $lockKey = 'lpg_migration_runner';

    public function __construct()
    {
        $this->db = Database::connect();
    }

    protected function ensureHistoryTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS lpg_migration_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration_seq INT UNSIGNED NOT NULL,
            migration_file VARCHAR(255) NOT NULL,
            checksum CHAR(64) NOT NULL,
            status ENUM('running','success','failed','skipped') NOT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            duration_ms INT UNSIGNED NULL,
            executed_statements INT UNSIGNED NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            KEY idx_lpg_migration_history_file_id (migration_file,id),
            KEY idx_lpg_migration_history_status (status),
            KEY idx_lpg_migration_history_seq (migration_seq)
        ) ENGINE=InnoDB");
    }

    protected function migrationFiles(): array
    {
        $dir = ROOTPATH . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $migrations = [];

        foreach ($files as $path) {
            $name = basename($path);
            if (!preg_match('/^(\d+)_.*\.sql$/', $name, $m)) {
                continue;
            }

            $seq = (int) $m[1];
            if (isset($migrations[$seq])) {
                throw new RuntimeException('Duplicate migration sequence '.$seq.': '.$migrations[$seq]['file'].' and '.$name);
            }

            $migrations[$seq] = [
                'seq' => $seq,
                'file' => $name,
                'path' => $path,
                'checksum' => hash_file('sha256', $path),
            ];
        }

        ksort($migrations, SORT_NUMERIC);
        return array_values($migrations);
    }

    protected function latestHistory(): array
    {
        $rows = $this->db->query(
            "SELECT h.*
             FROM lpg_migration_history h
             INNER JOIN (
                 SELECT migration_file, MAX(id) AS max_id
                 FROM lpg_migration_history
                 GROUP BY migration_file
             ) x ON x.max_id = h.id"
        )->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['migration_file']] = $row;
        }

        return $map;
    }

    protected function migrationAlreadyApplied(array $migration): ?string
    {
        if ($migration['seq'] === 11) {
            return 'Manual / opt-in staging refresh migration; automatic login runner does not perform the destructive refresh.';
        }

        $checks = [
            1 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings'",
            2 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_policies' AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_wastage_logs')",
            3 => "SELECT (EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_units') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_movements' AND COLUMN_NAME='cylinder_unit_id')) ok",
            4 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME IN ('credit_limit_validation_mode','shop_credit_limit')",
            5 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_opening_balances' AND COLUMN_NAME='comments'",
            6 => "SELECT (EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_custody') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_security_deposits') AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME IN ('security_deposit_amount','security_deposit_refund_amount'))=2) ok",
            7 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='default_transaction_type'",
            8 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='individual_cylinder_tracking'",
            9 => "SELECT COUNT(*) ok FROM permissions WHERE code='POS_SALE'",
            10 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='purchase_items' AND COLUMN_NAME='actual_gas_weight_kg'",
            12 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_units'",
            13 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='allow_credit_sale'",
            14 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='allow_pos_source_cylinder_selection'",
            15 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME IN ('previous_os_balance','receipt_amount','net_receivable_amount','os_balance')",
            16 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_types' AND COLUMN_NAME='empty_cylinder_price'",
            17 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sale_items' AND COLUMN_NAME IN ('gas_rate','cylinder_price')",
            18 => "SELECT (EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='purchase_void_enabled') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_purchase_void_users')) ok",
            19 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_adjustments'",
            20 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='pos_visible_transaction_types'",
            21 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME IN ('smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption','smtp_from_email','smtp_from_name','smtp_enabled')",
            22 => "SELECT (EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_opening_balances' AND COLUMN_NAME='opening_batch_key') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_opening_balances' AND INDEX_NAME='uq_inventory_opening_batch')) ok",
            23 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_cards' AND COLUMN_NAME='cylinder_type_id' AND IS_NULLABLE='YES'",
            24 => "SELECT (EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='include_security_deposit_in_os') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='return_gas_ledger_amount') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_custody' AND COLUMN_NAME='issued_gas_weight_kg')) ok",
            25 => "SELECT (EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sale_payments' AND COLUMN_NAME='payment_type') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sale_payments' AND INDEX_NAME='idx_sale_payment_type')) ok",
            26 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME IN ('smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption','smtp_from_email','smtp_from_name','smtp_enabled')",
            27 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='deposit_payment_allocation_rule'",
            28 => "SELECT COUNT(*) ok FROM permissions WHERE code='POS_HISTORY'",
            29 => "SELECT IF(COUNT(*)=0,1,0) ok FROM shop_settings WHERE deposit_payment_allocation_rule='gas_first'",
            31 => "SELECT COUNT(*) ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='default_gas_entry_mode'",
            32 => "SELECT (EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND COLUMN_NAME='business_id') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_system_admin') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_business_access') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_shop_access') AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='location_id')) ok",
        ];

        if (!isset($checks[$migration['seq']])) {
            return null;
        }

        $row = $this->db->query($checks[$migration['seq']])->getRowArray();
        $ok = (int)($row['ok'] ?? 0);

        if ($migration['seq'] === 4 || $migration['seq'] === 6 || $migration['seq'] === 15 || $migration['seq'] === 17 || $migration['seq'] === 21) {
            $expected = [
                4 => 2,
                6 => 2,
                15 => 4,
                17 => 2,
                21 => 8,
            26 => 8,
            ][$migration['seq']];
            $ok = $ok === $expected ? 1 : 0;
        }

        return $ok === 1
            ? 'Already present in the database; recorded as baseline without re-executing.'
            : null;
    }

    public function status(): array
    {
        $this->ensureHistoryTable();
        $migrations = $this->migrationFiles();
        $latest = $this->latestHistory();
        $results = [];
        $ready = true;
        $blockedMessage = null;

        foreach ($migrations as $migration) {
            $history = $latest[$migration['file']] ?? null;

            if ($history && in_array($history['status'], ['success','skipped'], true)) {
                if (!hash_equals((string) $history['checksum'], $migration['checksum'])) {
                    $ready = false;
                    $blockedMessage = 'Migration file '.$migration['file'].' was changed after it was applied. Create a new migration sequence instead.';
                    $results[] = [
                        'seq' => $migration['seq'],
                        'file' => $migration['file'],
                        'status' => 'changed',
                        'message' => $blockedMessage,
                        'finished_at' => $history['finished_at'],
                        'duration_ms' => $history['duration_ms'],
                        'executed_statements' => $history['executed_statements'],
                    ];
                    continue;
                }

                $results[] = [
                    'seq' => $migration['seq'],
                    'file' => $migration['file'],
                    'status' => $history['status'],
                    'message' => $history['status'] === 'skipped' ? ((string) ($history['error_message'] ?? 'Skipped.')) : 'Applied successfully.',
                    'finished_at' => $history['finished_at'],
                    'duration_ms' => $history['duration_ms'],
                    'executed_statements' => $history['executed_statements'],
                ];
                continue;
            }

            $baselineReason = $this->migrationAlreadyApplied($migration);
            if ($baselineReason !== null) {
                $results[] = [
                    'seq' => $migration['seq'],
                    'file' => $migration['file'],
                    'status' => 'skipped',
                    'message' => $baselineReason,
                    'finished_at' => null,
                    'duration_ms' => 0,
                    'executed_statements' => 0,
                ];
                continue;
            }

            $ready = false;
            $status = $history['status'] ?? 'pending';
            $message = $status === 'failed'
                ? ((string) ($history['error_message'] ?? 'Migration failed.'))
                : ($status === 'running'
                    ? 'Previous execution was interrupted; this migration will be retried.'
                    : 'Pending.');

            $results[] = [
                'seq' => $migration['seq'],
                'file' => $migration['file'],
                'status' => $status,
                'message' => $message,
                'finished_at' => $history['finished_at'] ?? null,
                'duration_ms' => $history['duration_ms'] ?? null,
                'executed_statements' => $history['executed_statements'] ?? 0,
            ];

            if ($status === 'failed' && $blockedMessage === null) {
                $blockedMessage = 'Migration '.$migration['file'].' failed. Fix the database error, then execute migrations again.';
            }
        }

        if ($blockedMessage !== null) {
            $ready = false;
        }

        return [
            'ready' => $ready,
            'blocked_message' => $blockedMessage,
            'migrations' => array_values(array_filter($results, static fn(array $r): bool => !in_array($r['status'], ['success','skipped'], true))),
            'total' => count($migrations),
            'completed' => count($migrations) - count(array_filter($results, static fn(array $r): bool => !in_array($r['status'], ['success','skipped'], true))),
        ];
    }

    public function runPending(): array
    {
        $this->ensureHistoryTable();

        $lock = $this->db->query('SELECT GET_LOCK(?, 5) AS locked', [$this->lockKey])->getRowArray();
        if ((int) ($lock['locked'] ?? 0) !== 1) {
            throw new RuntimeException('Another migration execution is already in progress. Please retry.');
        }

        try {
            $migrations = $this->migrationFiles();
            $latest = $this->latestHistory();
            $executed = [];

            foreach ($migrations as $migration) {
                $history = $latest[$migration['file']] ?? null;

                if ($history && $history['status'] === 'success') {
                    if (!hash_equals((string) $history['checksum'], $migration['checksum'])) {
                        throw new RuntimeException('Migration file '.$migration['file'].' was changed after it was applied. Create a new migration sequence instead.');
                    }
                    continue;
                }

                // Re-check the live schema on retries as well. A previous attempt may have
                // completed some DDL statements before failing; a fully-applied migration can then
                // be safely baselined instead of executing duplicate ALTER statements.
                $baselineReason = $this->migrationAlreadyApplied($migration);
                if ($baselineReason !== null) {
                    $now = date('Y-m-d H:i:s');
                    $this->db->table($this->historyTable)->insert([
                        'migration_seq' => $migration['seq'],
                        'migration_file' => $migration['file'],
                        'checksum' => $migration['checksum'],
                        'status' => 'skipped',
                        'started_at' => $now,
                        'finished_at' => $now,
                        'duration_ms' => 0,
                        'executed_statements' => 0,
                        'error_message' => $baselineReason,
                    ]);

                    $executed[] = [
                        'seq' => $migration['seq'],
                        'file' => $migration['file'],
                        'status' => 'skipped',
                        'message' => $baselineReason,
                        'executed_statements' => 0,
                        'duration_ms' => 0,
                    ];
                    continue;
                }

                $started = microtime(true);
                $this->db->table($this->historyTable)->insert([
                    'migration_seq' => $migration['seq'],
                    'migration_file' => $migration['file'],
                    'checksum' => $migration['checksum'],
                    'status' => 'running',
                    'started_at' => date('Y-m-d H:i:s'),
                ]);
                $attemptId = (int) $this->db->insertID();
                $statementCount = 0;

                try {
                    $contents = file_get_contents($migration['path']);
                    if ($contents === false) {
                        throw new RuntimeException('Unable to read migration file.');
                    }

                    foreach ($this->splitSqlStatements($contents) as $statement) {
                        if ($statement === '') {
                            continue;
                        }
                        $this->db->query($statement);
                        $statementCount++;
                        $this->db->table($this->historyTable)->where('id', $attemptId)->update([
                            'executed_statements' => $statementCount,
                        ]);
                    }

                    $duration = max(0, (int) round((microtime(true) - $started) * 1000));
                    $this->db->table($this->historyTable)->where('id', $attemptId)->update([
                        'status' => 'success',
                        'finished_at' => date('Y-m-d H:i:s'),
                        'duration_ms' => $duration,
                        'executed_statements' => $statementCount,
                    ]);

                    $executed[] = [
                        'seq' => $migration['seq'],
                        'file' => $migration['file'],
                        'status' => 'success',
                        'message' => 'Applied successfully.',
                        'executed_statements' => $statementCount,
                        'duration_ms' => $duration,
                    ];
                } catch (\Throwable $e) {
                    $duration = max(0, (int) round((microtime(true) - $started) * 1000));
                    $message = trim($e->getMessage()) ?: 'Migration failed.';
                    $this->db->table($this->historyTable)->where('id', $attemptId)->update([
                        'status' => 'failed',
                        'finished_at' => date('Y-m-d H:i:s'),
                        'duration_ms' => $duration,
                        'executed_statements' => $statementCount,
                        'error_message' => $message,
                    ]);

                    $executed[] = [
                        'seq' => $migration['seq'],
                        'file' => $migration['file'],
                        'status' => 'failed',
                        'message' => $message,
                        'executed_statements' => $statementCount,
                        'duration_ms' => $duration,
                    ];
                    break;
                }
            }

            $failed = null;
            foreach ($executed as $row) {
                if ($row['status'] === 'failed') {
                    $failed = $row;
                    break;
                }
            }

            $status = $this->status();
            // If the pending/failed list is empty, the database is fully migrated even if
            // an older history-state flag is stale. Do not fail CI/login over that stale flag.
            $migrationSetReady = $status['ready'] || empty($status['migrations']);

            return [
                'success' => $failed === null && $migrationSetReady,
                'message' => $failed
                    ? 'Migration '.$failed['file'].' failed. No later migration was executed.'
                    : ($migrationSetReady ? 'All database migrations are up to date.' : 'Pending migrations remain.'),
                'executed' => $executed,
                'status' => $status,
            ];
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$this->lockKey]);
        }
    }

    protected function splitSqlStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $delimiter = ';';
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match)) {
                $delimiter = $match[1];
                continue;
            }

            $buffer .= $line . "\n";

            while (($position = $this->findDelimiter($buffer, $delimiter)) !== null) {
                $statement = trim(substr($buffer, 0, $position));
                $buffer = substr($buffer, $position + strlen($delimiter));
                $statement = $this->stripSqlComments($statement);

                if ($statement !== '') {
                    $statements[] = $statement;
                }
            }
        }

        $tail = $this->stripSqlComments(trim($buffer));
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    protected function findDelimiter(string $sql, string $delimiter): ?int
    {
        $length = strlen($sql);
        $delimiterLength = strlen($delimiter);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];

            if ($quote !== null) {
                if ($ch === '\\') {
                    $i++;
                    continue;
                }

                if ($ch === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"' || $ch === chr(96)) {
                $quote = $ch;
                continue;
            }

            if ($delimiterLength > 0 && substr($sql, $i, $delimiterLength) === $delimiter) {
                return $i;
            }
        }

        return null;
    }

    protected function stripSqlComments(string $sql): string
    {
        $sql = preg_replace('/^\s*--[^\r\n]*(?:\r?\n|$)/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', (string) $sql);
        return trim((string) $sql);
    }
}
