<?php

namespace App\Database\Migrations;

use App\Database\TenantTables;
use CodeIgniter\Database\Migration;

/**
 * BL-028: a globally unique `uuid` on the records an offline client creates, so a device can name
 * a record before it reaches the server. Existing rows are backfilled. New rows get the client's
 * UUID or a server v4 UUID from the application DB builder (App\Database\MySQLi\Builder): MySQL
 * rejects UUID() as a column default (and triggers) while binary logging is on.
 */
class ClientUuids extends Migration
{
    public function up(): void
    {
        foreach (TenantTables::UUID_TABLES as $table) {
            if ($this->db->fieldExists('uuid', $table)) {
                continue;
            }
            $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`");
            $this->db->query("UPDATE `{$table}` SET `uuid` = UUID() WHERE `uuid` IS NULL");
            $this->db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `uq_{$table}_uuid` (`uuid`)");
        }
    }

    public function down(): void
    {
        foreach (array_reverse(TenantTables::UUID_TABLES) as $table) {
            if ($this->db->fieldExists('uuid', $table)) {
                $this->db->query("ALTER TABLE `{$table}` DROP INDEX `uq_{$table}_uuid`, DROP COLUMN `uuid`");
            }
        }
    }
}
