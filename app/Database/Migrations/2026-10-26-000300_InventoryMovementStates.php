<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-033: every inventory movement records the cylinder's state before and after it
 * (from_state / to_state), so the ledger alone explains each unit's status. States are the
 * unit statuses plus `none` (not in the business's stock yet) and the new unit status
 * `retired` (an opening cylinder withdrawn by editing or deleting its opening entry; the unit
 * and its movements are kept and reversed instead of deleted).
 *
 * Existing rows are back-filled from the movement's type, direction and source (best effort:
 * a source/unit pair holding both a filled OUT and an empty IN is a cylinder that became
 * empty, and the reverse pair one that was refilled).
 */
class InventoryMovementStates extends Migration
{
    private const STATUSES = "'filled','empty','custody','sold','in_transit'";

    public function up(): void
    {
        if ($this->db->fieldExists('to_state', 'inventory_movements')) {
            return;
        }
        $this->db->query("ALTER TABLE `cylinder_units` MODIFY `status` ENUM(" . self::STATUSES . ",'retired') NOT NULL");
        $this->db->query('ALTER TABLE `inventory_movements`
            ADD COLUMN `from_state` VARCHAR(20) NULL AFTER `direction`,
            ADD COLUMN `to_state` VARCHAR(20) NULL AFTER `from_state`');

        // Cylinder rows: IN arrives as filled/empty; OUT leaves filled/empty.
        $this->db->query("UPDATE `inventory_movements` SET
            `from_state` = CASE WHEN `direction` = 'in' THEN CASE `source_type`
                    WHEN 'cylinder_return' THEN 'custody' WHEN 'stock_transfer' THEN 'in_transit'
                    WHEN 'stock_transfer_cancel' THEN 'in_transit' WHEN 'sale_void' THEN 'sold' ELSE 'none' END
                ELSE IF(`inventory_type` = 'empty_cylinder', 'empty', 'filled') END,
            `to_state` = CASE WHEN `direction` = 'in' THEN IF(`inventory_type` = 'empty_cylinder', 'empty', 'filled')
                ELSE CASE `source_type` WHEN 'security_deposit' THEN 'custody' WHEN 'stock_transfer' THEN 'in_transit' ELSE 'sold' END END
            WHERE `to_state` IS NULL");
        // Gas taken from (or put back into) a cylinder that stays in stock: filled -> filled.
        $this->db->query("UPDATE `inventory_movements` m
            LEFT JOIN (SELECT DISTINCT `source_type`, `source_id`, `cylinder_unit_id` FROM `inventory_movements`
                        WHERE `inventory_type` <> 'gas_kg' AND `cylinder_unit_id` IS NOT NULL) c
              ON c.`source_type` = m.`source_type` AND c.`source_id` = m.`source_id` AND c.`cylinder_unit_id` = m.`cylinder_unit_id`
            SET m.`from_state` = 'filled', m.`to_state` = 'filled'
            WHERE m.`inventory_type` = 'gas_kg' AND m.`cylinder_unit_id` IS NOT NULL AND c.`cylinder_unit_id` IS NULL
              AND m.`source_type` IN ('sale', 'sale_void', 'adjustment', 'wastage')");
        // A cylinder that became empty (filled OUT + empty IN) or was refilled (empty OUT + filled IN).
        foreach ([['filled_cylinder', 'empty_cylinder', 'filled', 'empty'], ['empty_cylinder', 'filled_cylinder', 'empty', 'filled']] as [$out, $in, $from, $to]) {
            $this->db->query("UPDATE `inventory_movements` m
                JOIN (SELECT DISTINCT o.`source_type`, o.`source_id`, o.`cylinder_unit_id` FROM `inventory_movements` o
                        JOIN `inventory_movements` i ON i.`source_type` = o.`source_type` AND i.`source_id` = o.`source_id`
                         AND i.`cylinder_unit_id` = o.`cylinder_unit_id` AND i.`inventory_type` = ? AND i.`direction` = 'in'
                       WHERE o.`inventory_type` = ? AND o.`direction` = 'out' AND o.`cylinder_unit_id` IS NOT NULL) p
                  ON p.`source_type` = m.`source_type` AND p.`source_id` = m.`source_id` AND p.`cylinder_unit_id` = m.`cylinder_unit_id`
                SET m.`from_state` = ?, m.`to_state` = ?", [$in, $out, $from, $to]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('to_state', 'inventory_movements')) {
            $this->db->query('ALTER TABLE `inventory_movements` DROP COLUMN `from_state`, DROP COLUMN `to_state`');
        }
        // A retired unit is out of stock, as a sold one is.
        $this->db->query("UPDATE `cylinder_units` SET `status` = 'sold' WHERE `status` = 'retired'");
        $this->db->query("ALTER TABLE `cylinder_units` MODIFY `status` ENUM(" . self::STATUSES . ") NOT NULL");
    }
}
