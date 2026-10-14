-- Migration 038: unique recoverable link from a POS sale to its customer order.
-- The nullable unique key preserves all existing POS sales and makes fulfillment retries idempotent.
SET @lpg038_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='customer_order_id')=0,
    'ALTER TABLE sales ADD COLUMN customer_order_id BIGINT UNSIGNED NULL AFTER location_id',
    'SELECT 1'
);
PREPARE lpg038_stmt FROM @lpg038_sql; EXECUTE lpg038_stmt; DEALLOCATE PREPARE lpg038_stmt;

SET @lpg038_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND INDEX_NAME='uq_sales_customer_order')=0,
    'ALTER TABLE sales ADD UNIQUE KEY uq_sales_customer_order(customer_order_id)',
    'SELECT 1'
);
PREPARE lpg038_stmt FROM @lpg038_sql; EXECUTE lpg038_stmt; DEALLOCATE PREPARE lpg038_stmt;
