-- Perfect LPG - configurable POS transaction type visibility
-- Deployment sequence: 020
-- Only transaction types enabled per shop are shown/accepted on POS.
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    "ALTER TABLE shop_settings ADD COLUMN pos_visible_transaction_types TEXT NULL AFTER default_transaction_type",
    "SELECT 1"
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shop_settings'
    AND COLUMN_NAME = 'pos_visible_transaction_types'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE shop_settings
SET pos_visible_transaction_types = '["gas_sale","cylinder_sale","security_deposit","cylinder_return"]'
WHERE pos_visible_transaction_types IS NULL OR TRIM(pos_visible_transaction_types) = '';
