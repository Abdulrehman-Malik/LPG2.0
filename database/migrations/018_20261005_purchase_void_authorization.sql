-- Perfect LPG - configurable purchase void authorization
-- Deployment sequence: 018
-- Purchase Void is controlled per branch and assigned to explicit users.

USE perfect_lpg;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    "ALTER TABLE shop_settings ADD COLUMN purchase_void_enabled BOOLEAN NOT NULL DEFAULT 0 AFTER default_payment_mode",
    "SELECT 1"
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shop_settings'
    AND COLUMN_NAME = 'purchase_void_enabled'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS shop_purchase_void_users(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shop_purchase_void_user(location_id,user_id),
  FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
)ENGINE=InnoDB;
