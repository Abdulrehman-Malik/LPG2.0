<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-009: every branch-owned table carries its tenant (`business_id`; business = tenant).
 * - backfilled from the row's branch (locations.business_id)
 * - composite FK (location_id, business_id) -> locations(id, business_id): a row can never
 *   name a branch of another business, and moving a row to another business's branch fails
 * - new rows are stamped by the application's Query Builder (App\Database\MySQLi\Builder);
 *   database triggers are deliberately not used (they need SUPER on hosts with binary logging)
 */
class BusinessIdOnBranchTables extends Migration
{
    /** Fixed at the time of this migration (later changes go in new migrations). table => location_id nullable */
    private const TABLES = [
        'customers' => false, 'sales' => false, 'purchases' => false, 'customer_receipts' => false,
        'supplier_payments' => false, 'expenses' => false, 'cash_registers' => false, 'cylinder_units' => false,
        'inventory_movements' => false, 'inventory_opening_balances' => false, 'cylinder_custody' => false,
        'customer_security_deposits' => false, 'document_sequences' => false, 'shop_settings' => false,
        'inventory_policies' => false, 'inventory_wastage_logs' => false, 'shop_purchase_void_users' => false,
        'supplier_opening_balances' => false, 'shop_setting_overrides' => false,
        'rate_cards' => true, 'rate_change_log' => true, 'audit_logs' => true,
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $nullable) {
            if (!$this->db->tableExists($table, false) || $this->db->fieldExists('business_id', $table)) {
                continue;
            }
            $this->db->query("ALTER TABLE `$table` ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER location_id");
            $this->db->query("UPDATE `$table` t JOIN locations l ON l.id=t.location_id SET t.business_id=l.business_id");
            if (!$nullable) {
                $this->db->query("ALTER TABLE `$table` MODIFY business_id BIGINT UNSIGNED NOT NULL");
            }
            $this->db->query("ALTER TABLE `$table` ADD KEY idx_{$table}_business (business_id)");
            $this->db->query("ALTER TABLE `$table` ADD CONSTRAINT fk_{$table}_branch_business FOREIGN KEY (location_id, business_id) REFERENCES locations(id, business_id)");
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES, true) as $table => $nullable) {
            if (!$this->db->tableExists($table, false) || !$this->db->fieldExists('business_id', $table)) {
                continue;
            }
            $composite = "fk_{$table}_branch_business";
            $this->db->query("ALTER TABLE `$table` DROP FOREIGN KEY $composite");
            if ($this->indexExists($table, $composite)) {
                // Adding the composite key let MySQL drop the implicit index of the table's own
                // location_id foreign key; recreate it (same name) before removing ours.
                if (!$this->otherIndexStartsWith($table, 'location_id', $composite)) {
                    $locationFk = $this->db->query(
                        "SELECT CONSTRAINT_NAME n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='location_id' AND REFERENCED_TABLE_NAME='locations' AND CONSTRAINT_NAME<>? LIMIT 1",
                        [$table, $composite]
                    )->getRow('n');
                    if ($locationFk) {
                        $this->db->query("ALTER TABLE `$table` ADD INDEX `$locationFk` (location_id)");
                    }
                }
                $this->db->query("ALTER TABLE `$table` DROP INDEX $composite");
            }
            $this->db->query("ALTER TABLE `$table` DROP KEY idx_{$table}_business");
            $this->db->query("ALTER TABLE `$table` DROP COLUMN business_id");
        }
    }

    private function otherIndexStartsWith(string $table, string $column, string $except): bool
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND SEQ_IN_INDEX=1 AND INDEX_NAME<>?',
            [$table, $column, $except]
        )->getRow('n') > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',
            [$table, $index]
        )->getRow('n') > 0;
    }
}
