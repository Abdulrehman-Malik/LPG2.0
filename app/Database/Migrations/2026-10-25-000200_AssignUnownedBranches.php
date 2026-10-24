<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-013: every branch belongs to a business (tenant). Databases upgraded through migration 032
 * can still have branches with business_id NULL; they are assigned to the DEFAULT business (the
 * single-customer data becomes the first tenant) and the column becomes NOT NULL. The assigned
 * branch IDs are kept so down() restores exactly the previous state.
 */
class AssignUnownedBranches extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'location_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'assigned_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('location_id', true);
        $this->forge->createTable('lpg_tenant_backfill');

        // Location 0 marks that the column was nullable before (upgraded databases); down() restores it.
        $wasNullable = $this->db->query("SELECT IS_NULLABLE n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND COLUMN_NAME='business_id'")->getRow('n') === 'YES';
        if ($wasNullable) {
            $this->db->table('lpg_tenant_backfill')->insert(['location_id' => 0, 'assigned_at' => date('Y-m-d H:i:s')]);
        }

        $unowned = $this->db->table('locations')->select('id')->where('business_id', null)->get()->getResultArray();
        if ($unowned) {
            $default = $this->db->table('businesses')->select('id')->where('code', 'DEFAULT')->get()->getRowArray();
            if (!$default) {
                $this->db->table('businesses')->insert(['code' => 'DEFAULT', 'name' => 'Default Business']);
                $default = ['id' => $this->db->insertID()];
            }
            foreach ($unowned as $row) {
                $this->db->table('lpg_tenant_backfill')->insert(['location_id' => (int) $row['id'], 'assigned_at' => date('Y-m-d H:i:s')]);
            }
            $this->db->table('locations')->whereIn('id', array_column($unowned, 'id'))->update(['business_id' => (int) $default['id']]);
        }
        if ($wasNullable) {
            $this->modify('NOT NULL');
        }
    }

    public function down(): void
    {
        $ids = array_map('intval', array_column($this->db->table('lpg_tenant_backfill')->select('location_id')->get()->getResultArray(), 'location_id'));
        if (in_array(0, $ids, true)) {
            $this->modify('NULL');
        }
        $ids = array_values(array_filter($ids));
        if ($ids) {
            $this->db->table('locations')->whereIn('id', $ids)->update(['business_id' => null]);
        }
        $this->forge->dropTable('lpg_tenant_backfill');
    }

    /** Composite foreign keys of other tables reference (id, business_id); MySQL only lets the
     *  column be tightened or relaxed with key checks off for this one statement. */
    private function modify(string $nullability): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->db->query('ALTER TABLE locations MODIFY business_id BIGINT UNSIGNED ' . $nullability);
        } finally {
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
