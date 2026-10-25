<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-031: customer profile — type (domestic / commercial / retailer_dealer), area, notes, a
 * blocked flag (no new sales; still listed and in history) and WhatsApp opt-in.
 */
class CustomerProfileFields extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('customer_type', 'customers')) {
            return;
        }
        $this->db->query("ALTER TABLE `customers`
            ADD COLUMN `customer_type` VARCHAR(20) NOT NULL DEFAULT 'domestic' AFTER `name`,
            ADD COLUMN `area` VARCHAR(80) NULL AFTER `city`,
            ADD COLUMN `notes` VARCHAR(500) NULL,
            ADD COLUMN `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN `whatsapp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
            ADD KEY `idx_customers_type` (`location_id`, `customer_type`)");
    }

    public function down(): void
    {
        if ($this->db->fieldExists('customer_type', 'customers')) {
            $this->db->query('ALTER TABLE `customers` DROP KEY `idx_customers_type`, DROP COLUMN `customer_type`, DROP COLUMN `area`,
                DROP COLUMN `notes`, DROP COLUMN `is_blocked`, DROP COLUMN `whatsapp_enabled`');
        }
    }
}
