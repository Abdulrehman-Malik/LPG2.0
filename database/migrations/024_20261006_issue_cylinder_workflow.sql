-- Security Deposit / Cylinder Return workflow extensions.
-- Safe to run once through the project migration runner.
SET @sql = (SELECT IF(COUNT(*)=0,
"ALTER TABLE shop_settings ADD COLUMN include_security_deposit_in_os BOOLEAN NOT NULL DEFAULT 0 AFTER allow_pos_source_cylinder_selection, ADD COLUMN allow_return_gas_qty BOOLEAN NOT NULL DEFAULT 0 AFTER include_security_deposit_in_os, ADD COLUMN return_gas_affects_os BOOLEAN NOT NULL DEFAULT 0 AFTER allow_return_gas_qty, ADD COLUMN allow_empty_issued_return_gas BOOLEAN NOT NULL DEFAULT 0 AFTER return_gas_affects_os, ADD COLUMN allow_return_gas_over_issued BOOLEAN NOT NULL DEFAULT 0 AFTER allow_empty_issued_return_gas",
"SELECT 1") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_settings' AND COLUMN_NAME='include_security_deposit_in_os');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*)=0,
"ALTER TABLE sales ADD COLUMN return_gas_ledger_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER os_balance",
"SELECT 1") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='return_gas_ledger_amount');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*)=0,
"ALTER TABLE cylinder_custody ADD COLUMN issued_gas_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER deposit_amount, ADD COLUMN returned_gas_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER issued_gas_weight_kg, ADD COLUMN consumed_gas_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER returned_gas_weight_kg, ADD COLUMN issued_gas_rate DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER consumed_gas_weight_kg, ADD COLUMN return_gas_rate DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER issued_gas_rate, ADD COLUMN issued_condition ENUM('filled','empty') NOT NULL DEFAULT 'empty' AFTER return_gas_rate",
"SELECT 1") FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_custody' AND COLUMN_NAME='issued_gas_weight_kg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE shop_settings SET include_security_deposit_in_os=COALESCE(include_security_deposit_in_os,0), allow_return_gas_qty=COALESCE(allow_return_gas_qty,0), return_gas_affects_os=COALESCE(return_gas_affects_os,0), allow_empty_issued_return_gas=COALESCE(allow_empty_issued_return_gas,0), allow_return_gas_over_issued=COALESCE(allow_return_gas_over_issued,0);