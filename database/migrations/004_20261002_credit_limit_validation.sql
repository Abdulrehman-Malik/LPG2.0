-- Credit limit validation configuration for existing LPG databases.
-- Run once after deploying this feature.

ALTER TABLE shop_settings
  ADD COLUMN IF NOT EXISTS credit_limit_validation_mode ENUM('none','customer','shop') NOT NULL DEFAULT 'none' AFTER allow_stock_override,
  ADD COLUMN IF NOT EXISTS shop_credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER credit_limit_validation_mode;
