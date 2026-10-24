<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-010: tables owned through a parent row carry the tenant too. business_id is copied from the
 * parent (backfill here; new rows by the application's Query Builder) and a composite FK
 * (parent_id, business_id) -> parent(id, business_id) keeps child and parent in one business.
 */
class BusinessIdOnChildTables extends Migration
{
    /** Parents that need UNIQUE(id, business_id) as a composite FK target. */
    private const PARENTS = ['sales', 'purchases', 'customers', 'cash_registers', 'stock_transfers'];

    /** child => [parent column, parent table]; a parent that is itself a child comes first. */
    private const CHILDREN = [
        'cash_sessions' => ['register_id', 'cash_registers'],
        'sale_items' => ['sale_id', 'sales'],
        'sale_payments' => ['sale_id', 'sales'],
        'purchase_items' => ['purchase_id', 'purchases'],
        'purchase_payments' => ['purchase_id', 'purchases'],
        'stock_transfer_items' => ['transfer_id', 'stock_transfers'],
        'customer_rates' => ['customer_id', 'customers'],
        'customer_app_accounts' => ['customer_id', 'customers'],
        'cash_transactions' => ['cash_session_id', 'cash_sessions'],
    ];

    public function up(): void
    {
        foreach (self::PARENTS as $parent) {
            $this->db->query("ALTER TABLE `$parent` ADD UNIQUE KEY uq_{$parent}_id_business (id, business_id)");
        }
        foreach (self::CHILDREN as $child => [$column, $parent]) {
            $this->db->query("ALTER TABLE `$child` ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER `$column`");
            $this->db->query("UPDATE `$child` c JOIN `$parent` p ON p.id=c.`$column` SET c.business_id=p.business_id");
            $this->db->query("ALTER TABLE `$child` MODIFY business_id BIGINT UNSIGNED NOT NULL");
            $this->db->query("ALTER TABLE `$child` ADD KEY idx_{$child}_business (business_id)");
            if ($child === 'cash_sessions') {
                // cash_sessions is the parent of cash_transactions.
                $this->db->query('ALTER TABLE cash_sessions ADD UNIQUE KEY uq_cash_sessions_id_business (id, business_id)');
            }
            $this->db->query("ALTER TABLE `$child` ADD CONSTRAINT fk_{$child}_parent_business FOREIGN KEY (`$column`, business_id) REFERENCES `$parent`(id, business_id)");
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::CHILDREN, true) as $child => [$column, $parent]) {
            $composite = "fk_{$child}_parent_business";
            $this->db->query("ALTER TABLE `$child` DROP FOREIGN KEY $composite");
            $this->restoreImplicitIndex($child, $column, $composite);
            if ($child === 'cash_sessions') {
                $this->db->query('ALTER TABLE cash_sessions DROP KEY uq_cash_sessions_id_business');
            }
            $this->db->query("ALTER TABLE `$child` DROP KEY idx_{$child}_business");
            $this->db->query("ALTER TABLE `$child` DROP COLUMN business_id");
        }
        foreach (self::PARENTS as $parent) {
            $this->db->query("ALTER TABLE `$parent` DROP KEY uq_{$parent}_id_business");
        }
    }

    /**
     * Adding the composite key let MySQL drop the implicit index of the table's own single-column
     * foreign key on $column; recreate it (same name as that key) before removing the composite index.
     */
    private function restoreImplicitIndex(string $table, string $column, string $composite): void
    {
        if (!$this->count('SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?', [$table, $composite])) {
            return;
        }
        $otherIndex = $this->count('SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND SEQ_IN_INDEX=1 AND INDEX_NAME<>?', [$table, $column, $composite]);
        if (!$otherIndex) {
            $fk = $this->db->query(
                'SELECT CONSTRAINT_NAME n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL AND CONSTRAINT_NAME<>? LIMIT 1',
                [$table, $column, $composite]
            )->getRow('n');
            if ($fk) {
                $this->db->query("ALTER TABLE `$table` ADD INDEX `$fk` (`$column`)");
            }
        }
        $this->db->query("ALTER TABLE `$table` DROP INDEX $composite");
    }

    private function count(string $sql, array $binds): int
    {
        return (int) $this->db->query($sql, $binds)->getRow('n');
    }
}
