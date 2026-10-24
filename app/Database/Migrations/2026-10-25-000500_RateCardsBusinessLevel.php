<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-012: no platform-wide data inside a tenant.
 * - A "global" rate card (location_id NULL) used to apply to every branch of every business.
 *   It now belongs to one business (business-level rate, location_id NULL + business_id set):
 *   the original row goes to its cylinder type's business (or DEFAULT for a per-KG rate) and
 *   every other business gets its own copy, so prices do not change for anyone.
 * - rate_cards.business_id becomes NOT NULL.
 * - Branch codes are unique per business instead of across the platform.
 */
class RateCardsBusinessLevel extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'rate_card_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 10],
        ]);
        $this->forge->addKey('rate_card_id', true);
        $this->forge->createTable('lpg_rate_backfill');

        $default = (int) ($this->db->table('businesses')->select('id')->where('code', 'DEFAULT')->get()->getRow('id')
            ?? $this->db->table('businesses')->select('id')->orderBy('id')->get(1)->getRow('id') ?? 0);
        $businesses = array_map('intval', array_column($this->db->table('businesses')->select('id')->get()->getResultArray(), 'id'));

        foreach ($this->db->table('rate_cards')->where('location_id', null)->get()->getResultArray() as $card) {
            $owner = $default;
            if (!empty($card['cylinder_type_id'])) {
                $owner = (int) ($this->db->table('cylinder_types')->select('business_id')->where('id', (int) $card['cylinder_type_id'])->get()->getRow('business_id') ?? $default);
            }
            $this->db->table('rate_cards')->where('id', (int) $card['id'])->update(['business_id' => $owner]);
            $this->db->table('lpg_rate_backfill')->insert(['rate_card_id' => (int) $card['id'], 'kind' => 'assigned']);
            if (!empty($card['cylinder_type_id'])) {
                continue; // a cylinder type belongs to one business only
            }
            foreach ($businesses as $businessId) {
                if ($businessId === $owner) {
                    continue;
                }
                $copy = $card;
                unset($copy['id']);
                $copy['business_id'] = $businessId;
                $this->db->table('rate_cards')->insert($copy);
                $this->db->table('lpg_rate_backfill')->insert(['rate_card_id' => (int) $this->db->insertID(), 'kind' => 'copy']);
            }
        }
        $this->db->query('UPDATE rate_change_log g JOIN rate_cards r ON r.id=g.rate_card_id SET g.business_id=r.business_id WHERE g.location_id IS NULL AND g.business_id IS NULL');
        $this->modifyBusinessId('NOT NULL');

        $this->db->query('ALTER TABLE locations DROP INDEX code');
        $this->db->query('ALTER TABLE locations ADD UNIQUE KEY uq_locations_business_code (business_id, code)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE locations DROP INDEX uq_locations_business_code');
        $this->db->query('ALTER TABLE locations ADD UNIQUE KEY code (code)');

        $this->modifyBusinessId('NULL');
        $copies = array_column($this->db->table('lpg_rate_backfill')->where('kind', 'copy')->get()->getResultArray(), 'rate_card_id');
        if ($copies) {
            $this->db->table('rate_change_log')->whereIn('rate_card_id', $copies)->delete();
            $this->db->table('rate_cards')->whereIn('id', $copies)->delete();
        }
        $assigned = array_column($this->db->table('lpg_rate_backfill')->where('kind', 'assigned')->get()->getResultArray(), 'rate_card_id');
        if ($assigned) {
            $this->db->table('rate_cards')->whereIn('id', $assigned)->update(['business_id' => null]);
            $this->db->query('UPDATE rate_change_log SET business_id=NULL WHERE location_id IS NULL AND rate_card_id IN (' . implode(',', array_map('intval', $assigned)) . ')');
        }
        $this->forge->dropTable('lpg_rate_backfill');
    }

    /** The column is part of the composite branch/business foreign key (BL-009); MySQL only
     *  allows changing its nullability with key checks off for this one statement. */
    private function modifyBusinessId(string $nullability): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->db->query('ALTER TABLE rate_cards MODIFY business_id BIGINT UNSIGNED ' . $nullability);
        } finally {
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
