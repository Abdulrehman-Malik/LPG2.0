-- Follow-up for backup email settings migration 021.
-- Makes the SMTP settings migration safe for environments where some or all
-- columns already exist, without changing the already-applied migration 021.

SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE shop_settings ADD COLUMN smtp_host VARCHAR(255) NULL AFTER backup_notes', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='smtp_host');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE shop_settings ADD COLUMN smtp_port INT NOT NULL DEFAULT 587 AFTER smtp_host', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='smtp_port');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE shop_settings ADD COLUMN smtp_username VARCHAR(255) NULL AFTER smtp_port', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='smtp_username');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE shop_settings ADD COLUMN smtp_password TEXT NULL AFTER smtp_username', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='smtp_password');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE shop_settings ADD COLUMN smtp_encryption VARCHAR(10) NOT NULL DEFAULT ''tls'' AFTER smtp_password', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='smtp_encryption');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE shop_settings ADD COLUMN smtp_from_email VARCHAR(255) NULL AFTER smtp_encryption', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='smtp_from_email');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE shop_settings ADD COLUMN smtp_from_name VARCHAR(150) NULL AFTER smtp_from_email', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='smtp_from_name');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE shop_settings ADD COLUMN smtp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER smtp_from_name', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='smtp_enabled');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
