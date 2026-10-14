-- Configurable allocation rule for combined Security Deposit + Gas/Cylinder collection.
DROP PROCEDURE IF EXISTS lpg_migration_027_apply;
DELIMITER $$
CREATE PROCEDURE lpg_migration_027_apply()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='deposit_payment_allocation_rule'
    ) THEN
        ALTER TABLE shop_settings
            ADD COLUMN deposit_payment_allocation_rule ENUM('gas_first','deposit_first','manual') NOT NULL DEFAULT 'gas_first'
            AFTER include_security_deposit_in_os;
    END IF;
END$$
DELIMITER ;
CALL lpg_migration_027_apply();
DROP PROCEDURE IF EXISTS lpg_migration_027_apply;
