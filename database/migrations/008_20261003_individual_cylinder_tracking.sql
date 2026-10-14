SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    "ALTER TABLE shop_settings ADD COLUMN individual_cylinder_tracking BOOLEAN NOT NULL DEFAULT 0 AFTER default_transaction_type",
    "SELECT 1"
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shop_settings'
    AND COLUMN_NAME = 'individual_cylinder_tracking'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
