<?php

namespace App\Commands;

use App\Libraries\TenantContext;
use App\Models\CustomerModel;
use App\Models\CylinderTypeModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * QA-only command (tests/qa). verify_isolation.sh copies it into app/Commands for one check and
 * removes it again; it is never deployed. Proves the tenant-scoped models (BL-011) cannot read
 * or change another tenant's rows and refuse to run without a tenant context.
 */
class QaTenantModel extends BaseCommand
{
    protected $group = 'QA';
    protected $name = 'qa:tenant-model';
    protected $usage = 'qa:tenant-model <tenant A branch id> <tenant B customer id> <tenant B cylinder type id>';

    public function run(array $params)
    {
        [$branchA, $customerB, $typeB] = array_map('intval', $params + [0, 0, 0]);

        TenantContext::clear();
        try {
            (new CustomerModel())->find($customerB);
            CLI::write('no-context:allowed');
        } catch (\RuntimeException $e) {
            CLI::write('no-context:refused');
        }

        TenantContext::setWeb(1, $branchA);
        $customers = new CustomerModel();
        CLI::write('find-other:' . ($customers->find($customerB) === null ? 'null' : 'FOUND'));
        CLI::write('where-other:' . count($customers->where('id', $customerB)->findAll()));
        $customers->update($customerB, ['name' => 'QA Tenant Model Hijack']);
        $customers->delete($customerB);
        CLI::write('type-other:' . ((new CylinderTypeModel())->find($typeB) === null ? 'null' : 'FOUND'));
        try {
            (new CylinderTypeModel())->insert(['business_id' => 999999, 'code' => 'QA-TM', 'name' => 'QA', 'capacity_kg' => 1]);
            CLI::write('foreign-insert:allowed');
        } catch (\RuntimeException $e) {
            CLI::write('foreign-insert:refused');
        }
        CLI::write('platform-scope:' . ((new CustomerModel())->withoutTenantScope()->find($customerB) === null ? 'null' : 'FOUND'));
    }
}
