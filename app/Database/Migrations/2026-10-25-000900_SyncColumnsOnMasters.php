<?php

namespace App\Database\Migrations;

use App\Database\TenantTables;
use CodeIgniter\Database\Migration;

/**
 * BL-030: master data an offline client caches carries `updated_at`, `deleted_at` (soft delete)
 * and `version` (raised by the application DB builder on every update), so a sync can fetch
 * "changed since" and resolve conflicts last-writer-wins by version (docs/sync-policy.md).
 */
class SyncColumnsOnMasters extends Migration
{
    /** Masters that had no updated_at in database/schema.sql; this migration adds (and removes) it. */
    private const ADDS_UPDATED_AT = ['expense_categories', 'rate_cards', 'customer_rates'];

    public function up(): void
    {
        foreach (TenantTables::MASTER_TABLES as $table) {
            if (in_array($table, self::ADDS_UPDATED_AT, true) && !$this->db->fieldExists('updated_at', $table)) {
                $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
            }
            if (!$this->db->fieldExists('version', $table)) {
                $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 1, ADD KEY `idx_{$table}_sync` (`updated_at`)");
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(TenantTables::MASTER_TABLES) as $table) {
            if ($this->db->fieldExists('version', $table)) {
                $this->db->query("ALTER TABLE `{$table}` DROP KEY `idx_{$table}_sync`, DROP COLUMN `version`, DROP COLUMN `deleted_at`");
            }
            if (in_array($table, self::ADDS_UPDATED_AT, true) && $this->db->fieldExists('updated_at', $table)) {
                $this->db->query("ALTER TABLE `{$table}` DROP COLUMN `updated_at`");
            }
        }
    }
}
