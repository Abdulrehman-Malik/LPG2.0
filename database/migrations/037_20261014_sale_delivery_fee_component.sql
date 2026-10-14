-- Migration 037: persist the delivery charge as a separate sale component.
-- Legacy sales receive zero delivery fee; customer orders still need validated POS fulfilment.
SET @lpg037_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='delivery_fee_amount')=0,
    'ALTER TABLE sales ADD COLUMN delivery_fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER discount_amount',
    'SELECT 1'
);
PREPARE lpg037_stmt FROM @lpg037_sql; EXECUTE lpg037_stmt; DEALLOCATE PREPARE lpg037_stmt;
