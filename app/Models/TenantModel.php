<?php

namespace App\Models;

use App\Libraries\TenantContext;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;
use RuntimeException;

/**
 * Base model for tenant-owned tables (BL-011). Every query built through the model is limited to
 * the business of the current request (TenantContext), so a forgotten WHERE cannot read or
 * change another tenant's rows. Without a tenant context the model refuses to query.
 *
 * Platform code that must see every tenant (system-administrator screens, migrations, CLI) calls
 * withoutTenantScope() explicitly, which keeps such places easy to find.
 */
abstract class TenantModel extends Model
{
    /** Column holding the tenant on this table. */
    protected string $tenantColumn = 'business_id';

    private bool $tenantScopeDisabled = false;

    /** Runs the next queries of this instance across all tenants (platform code only). */
    public function withoutTenantScope(): static
    {
        $this->tenantScopeDisabled = true;
        return $this;
    }

    public function builder(?string $table = null)
    {
        $builder = parent::builder($table);
        if ($this->tenantScopeDisabled || ($table !== null && $table !== $this->table)) {
            return $builder;
        }

        $businessId = TenantContext::businessId();
        if ($businessId === null || $businessId <= 0) {
            throw new RuntimeException('No tenant context: ' . static::class . ' cannot be queried outside an authorized request.');
        }
        $this->applyTenantScope($builder, $businessId);
        // BL-030: builder-based queries (not only find/findAll) skip soft-deleted rows, unless the
        // caller asked for them with withDeleted()/onlyDeleted().
        if ($this->useSoftDeletes && $this->tempUseSoftDeletes) {
            $deleted = $this->db->protectIdentifiers($this->table . '.' . $this->deletedField);
            if (!str_contains($builder->getCompiledSelect(false), $deleted . ' IS NULL')) {
                $builder->where($this->table . '.' . $this->deletedField, null);
            }
        }

        return $builder;
    }

    private function applyTenantScope(BaseBuilder $builder, int $businessId): void
    {
        $column = $this->db->protectIdentifiers($this->table . '.' . $this->tenantColumn);
        // Builder state is reset after each query; avoid repeating the condition within one chain.
        $compiled = $builder->getCompiledSelect(false);
        if (!str_contains($compiled, $column . ' = ' . $businessId)) {
            $builder->where($this->table . '.' . $this->tenantColumn, $businessId);
        }
    }

    /** New rows always belong to the current tenant. */
    protected function doInsert(array $row)
    {
        if (!$this->tenantScopeDisabled) {
            $businessId = TenantContext::businessId();
            if ($businessId === null || $businessId <= 0) {
                throw new RuntimeException('No tenant context: ' . static::class . ' cannot insert outside an authorized request.');
            }
            if (isset($row[$this->tenantColumn]) && (int) $row[$this->tenantColumn] !== $businessId) {
                throw new RuntimeException('A row of another business cannot be created here.');
            }
            $row[$this->tenantColumn] = $businessId;
        }
        return parent::doInsert($row);
    }
}
