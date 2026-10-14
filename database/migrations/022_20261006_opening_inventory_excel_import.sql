-- Opening inventory Excel import support.
-- Migration sequence: 022
USE perfect_lpg;

SET @has_batch_key := (
 SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_opening_balances' AND COLUMN_NAME='opening_batch_key'
);
SET @sql := IF(@has_batch_key=0,
 'ALTER TABLE inventory_opening_balances ADD COLUMN opening_batch_key VARCHAR(64) NOT NULL DEFAULT '''' AFTER comments',
 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_unique := (
 SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_opening_balances' AND INDEX_NAME='uq_inventory_opening'
);
SET @has_new_unique := (
 SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_opening_balances' AND INDEX_NAME='uq_inventory_opening_batch'
);

-- Replace the old unique key atomically.  The replacement index starts with
-- the same referenced columns, so it can continue to satisfy any existing
-- foreign-key dependency on the old key while allowing the batch key to
-- distinguish multiple same-day import rows.
SET @sql := IF(@has_old_unique>0 AND @has_new_unique=0,
 'ALTER TABLE inventory_opening_balances DROP INDEX uq_inventory_opening, ADD UNIQUE KEY uq_inventory_opening_batch(location_id,inventory_date,inventory_type,cylinder_type_id,opening_batch_key)',
 IF(@has_old_unique>0 AND @has_new_unique>0,
    'ALTER TABLE inventory_opening_balances DROP INDEX uq_inventory_opening',
    IF(@has_old_unique=0 AND @has_new_unique=0,
       'ALTER TABLE inventory_opening_balances ADD UNIQUE KEY uq_inventory_opening_batch(location_id,inventory_date,inventory_type,cylinder_type_id,opening_batch_key)',
       'SELECT 1')));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
