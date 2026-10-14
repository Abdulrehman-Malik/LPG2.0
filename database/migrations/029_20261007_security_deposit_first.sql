-- Make Security Deposit the first allocation target for combined collections.
DROP PROCEDURE IF EXISTS lpg_migration_029_apply;
DELIMITER $$
CREATE PROCEDURE lpg_migration_029_apply()
BEGIN
    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='deposit_payment_allocation_rule'
    ) THEN
        ALTER TABLE shop_settings
            MODIFY COLUMN deposit_payment_allocation_rule ENUM('gas_first','deposit_first','manual') NOT NULL DEFAULT 'deposit_first';
        UPDATE shop_settings
        SET deposit_payment_allocation_rule='deposit_first'
        WHERE deposit_payment_allocation_rule='gas_first';
    END IF;
END$$
DELIMITER ;
CALL lpg_migration_029_apply();
DROP PROCEDURE IF EXISTS lpg_migration_029_apply;
