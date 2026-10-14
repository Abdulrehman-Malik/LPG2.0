-- Independent Security Deposit payment classification.
-- Existing sale payments remain compatible: payment_type defaults to 'sale'.

DROP PROCEDURE IF EXISTS lpg_migration_025_apply;
DELIMITER $$

CREATE PROCEDURE lpg_migration_025_apply()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sale_payments' AND COLUMN_NAME='payment_type'
    ) THEN
        ALTER TABLE sale_payments
            ADD COLUMN payment_type ENUM('sale','security_deposit','security_deposit_refund') NOT NULL DEFAULT 'sale'
            AFTER sale_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sale_payments' AND INDEX_NAME='idx_sale_payment_type'
    ) THEN
        ALTER TABLE sale_payments
            ADD KEY idx_sale_payment_type(sale_id,payment_type,payment_mode);
    END IF;
END$$

DELIMITER ;

CALL lpg_migration_025_apply();

DROP PROCEDURE IF EXISTS lpg_migration_025_apply;
