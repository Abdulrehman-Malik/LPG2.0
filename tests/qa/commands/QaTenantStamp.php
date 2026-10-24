<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * QA-only command (tests/qa). verify_tenancy_migrations.sh copies it into app/Commands for one
 * check and removes it again; it is never deployed. Inserts a customer through the Query Builder
 * without business_id and prints the business the application's driver stamped on it (BL-009).
 */
class QaTenantStamp extends BaseCommand
{
    protected $group = 'QA';
    protected $name = 'qa:tenant-stamp';
    protected $usage = 'qa:tenant-stamp <location_id>';

    public function run(array $params)
    {
        $locationId = (int) ($params[0] ?? 0);
        $db = Database::connect();
        $db->table('customers')->insert(['location_id' => $locationId, 'code' => 'QA-STAMP', 'name' => 'QA Stamp', 'is_active' => 1]);
        $row = $db->table('customers')->select('business_id')->where('id', $db->insertID())->get()->getRowArray();
        CLI::write('stamped:' . ($row['business_id'] ?? 'null'));
    }
}
