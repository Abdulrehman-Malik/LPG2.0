-- Perfect LPG - Purchase inventory integrity
-- Adds actual gas weight to purchase lines for filled cylinders.
-- Safe for existing installations; existing historical rows remain 0 when
-- their original actual gas weight was not persisted.

USE perfect_lpg;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    "ALTER TABLE purchase_items ADD COLUMN actual_gas_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER quantity",
    "SELECT 1"
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purchase_items'
    AND COLUMN_NAME = 'actual_gas_weight_kg'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
