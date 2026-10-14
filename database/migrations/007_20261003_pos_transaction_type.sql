SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    "ALTER TABLE shop_settings ADD COLUMN default_transaction_type ENUM('gas_sale','cylinder_sale','security_deposit','cylinder_return') NOT NULL DEFAULT 'gas_sale' AFTER default_sale_mode",
    "SELECT 1"
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shop_settings'
    AND COLUMN_NAME = 'default_transaction_type'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE shop_settings
SET default_transaction_type = CASE
  WHEN default_sale_mode IN ('sell_filled','replace_different','sell_empty') THEN 'cylinder_sale'
  ELSE 'gas_sale'
END
WHERE default_transaction_type = 'gas_sale';
