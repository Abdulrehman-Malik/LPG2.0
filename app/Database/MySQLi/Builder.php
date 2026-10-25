<?php

namespace App\Database\MySQLi;

use App\Database\TenantTables;
use App\Libraries\Uuid;
use CodeIgniter\Database\MySQLi\Builder as BaseBuilder;

/**
 * Query Builder of the application's MySQLi driver (BL-009). On INSERT into a tenant-owned
 * table it fills `business_id` from the row's branch (or parent row) when the caller did not
 * set it, so every insert path in the app stamps the tenant without being changed. A wrong or
 * missing tenant is then rejected by the composite foreign keys.
 */
class Builder extends BaseBuilder
{
    /** @var array<string,int|null> "table:id" => business_id */
    private static array $businessCache = [];

    public function insert($set = null, ?bool $escape = null)
    {
        if ($set !== null) {
            $this->set($set, '', $escape);
        }
        $this->stampTenant();
        // BL-028: the client's UUID waiting for this table, else a new v4 UUID.
        $table = $this->baseTable();
        if ($this->uuidColumnReady($table) && !array_key_exists($this->db->protectIdentifiers('uuid', false), $this->QBSet)) {
            $this->set('uuid', Uuid::take($table) ?? Uuid::v4());
        }

        return parent::insert(null, $escape);
    }

    public function insertBatch($set = null, ?bool $escape = null, int $batchSize = 100)
    {
        if (is_array($set) && $set !== []) {
            $table = $this->baseTable();
            $source = TenantTables::sourceFor($table);
            if ($source !== null && $this->tenantColumnReady($table, $source[1])) {
                [$column, $parent] = $source;
                foreach ($set as &$row) {
                    if (is_array($row) && !array_key_exists('business_id', $row) && isset($row[$column])) {
                        $businessId = $this->businessOf($parent, (int) $row[$column]);
                        if ($businessId !== null) {
                            $row['business_id'] = $businessId;
                        }
                    }
                }
                unset($row);
            }
            if ($this->uuidColumnReady($table)) {
                foreach ($set as &$row) {
                    if (is_array($row) && !array_key_exists('uuid', $row)) {
                        $row['uuid'] = Uuid::v4();
                    }
                }
                unset($row);
            }
        }

        return parent::insertBatch($set, $escape, $batchSize);
    }

    /** @var array<string,bool> table => has a business_id column */
    private static array $hasTenantColumn = [];

    /** Only tables (and parents) whose migration has added business_id are stamped. */
    private function tenantColumnReady(string $table, string $parent): bool
    {
        foreach ([$table, $parent] as $t) {
            if (!array_key_exists($t, self::$hasTenantColumn)) {
                self::$hasTenantColumn[$t] = (int) $this->db->query(
                    "SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'business_id'",
                    [$this->db->DBPrefix . $t]
                )->getRow('n') > 0;
            }
            if (!self::$hasTenantColumn[$t]) {
                return false;
            }
        }
        return true;
    }

    /** @var array<string,bool> table => has the BL-028 uuid column */
    private static array $hasUuidColumn = [];

    private function uuidColumnReady(string $table): bool
    {
        if (!in_array($table, TenantTables::UUID_TABLES, true)) {
            return false;
        }
        if (!array_key_exists($table, self::$hasUuidColumn)) {
            self::$hasUuidColumn[$table] = (int) $this->db->query(
                "SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'uuid'",
                [$this->db->DBPrefix . $table]
            )->getRow('n') > 0;
        }
        return self::$hasUuidColumn[$table];
    }

    /** Forget cached schema facts (after migrations change the tenant columns). */
    public static function resetTenantCache(): void
    {
        self::$hasUuidColumn = [];
        self::$hasTenantColumn = [];
        self::$businessCache = [];
    }

    private function stampTenant(): void
    {
        $table = $this->baseTable();
        $source = TenantTables::sourceFor($table);
        if ($source === null || array_key_exists($this->db->protectIdentifiers('business_id', false), $this->QBSet)) {
            return;
        }
        [$column, $parent] = $source;
        if (!$this->tenantColumnReady($table, $parent)) {
            return;
        }
        $value = $this->setValue($column);
        if ($value === null || (int) $value <= 0) {
            return;
        }
        $businessId = $this->businessOf($parent, (int) $value);
        if ($businessId !== null) {
            $this->set('business_id', $businessId);
        }
    }

    /** The value given to set() for a column (bound or raw), or null when it was not set. */
    private function setValue(string $column)
    {
        $key = $this->db->protectIdentifiers($column, false);
        if (!array_key_exists($key, $this->QBSet)) {
            return null;
        }
        $value = $this->QBSet[$key];
        if (is_string($value) && preg_match('/^:(.+):$/', $value, $m) && array_key_exists($m[1], $this->binds)) {
            $bound = $this->binds[$m[1]];
            return is_array($bound) ? $bound[0] : $bound;
        }
        return is_string($value) ? trim($value, "'\" ") : $value;
    }

    private function baseTable(): string
    {
        $from = (string) ($this->QBFrom[0] ?? '');
        $name = strtok(trim(str_replace(['`', '"'], '', $from)), ' ');
        $prefix = (string) $this->db->DBPrefix;
        return $prefix !== '' && str_starts_with((string) $name, $prefix) ? substr((string) $name, strlen($prefix)) : (string) $name;
    }

    private function businessOf(string $table, int $id): ?int
    {
        $key = $table . ':' . $id;
        if (!array_key_exists($key, self::$businessCache)) {
            $row = $this->db->query('SELECT business_id FROM ' . $this->db->protectIdentifiers($table, true) . ' WHERE id = ?', [$id])->getRowArray();
            self::$businessCache[$key] = isset($row['business_id']) ? (int) $row['business_id'] : null;
        }
        return self::$businessCache[$key];
    }
}
