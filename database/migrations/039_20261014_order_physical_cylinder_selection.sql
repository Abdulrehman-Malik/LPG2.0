-- Migration 039: persist the exact physical cylinder selection used for customer order pricing.
-- A selection is only a quote snapshot; stock is revalidated at fulfillment before POS posting.
SET @lpg039_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_order_items' AND COLUMN_NAME='selected_cylinder_unit_ids')=0,
    'ALTER TABLE customer_order_items ADD COLUMN selected_cylinder_unit_ids JSON NULL AFTER line_total',
    'SELECT 1'
);
PREPARE lpg039_stmt FROM @lpg039_sql; EXECUTE lpg039_stmt; DEALLOCATE PREPARE lpg039_stmt;
