<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-032: a default security deposit per cylinder type. The Security Deposit screen pre-fills
 * it (per issued cylinder); the cashier can still change the amount. Existing types get 0.
 */
class CylinderTypeDeposit extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('deposit_amount', 'cylinder_types')) {
            return;
        }
        $this->db->query('ALTER TABLE `cylinder_types`
            ADD COLUMN `deposit_amount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `empty_cylinder_price`');
    }

    public function down(): void
    {
        if ($this->db->fieldExists('deposit_amount', 'cylinder_types')) {
            $this->db->query('ALTER TABLE `cylinder_types` DROP COLUMN `deposit_amount`');
        }
    }
}
