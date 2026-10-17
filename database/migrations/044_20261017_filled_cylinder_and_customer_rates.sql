-- Migration 044: filled cylinder rates and customer-specific rates (requirements.md
-- "Rates and Customer Pricing", plan 9.8).
-- 1. rate_cards / rate_change_log accept rate_type 'filled_cylinder' (price of the gas in one
--    full cylinder of a type). The old unnamed CHECK on rate_cards is replaced by a named one.
-- 2. customer_rates: effective-dated special rates for one customer.
-- Idempotent; works on MySQL 8 and MariaDB 10.4+.

-- Drop the existing rate-type CHECK on rate_cards (its generated name differs per server).
SET @lpg044_chk = (SELECT tc.CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
    JOIN INFORMATION_SCHEMA.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
    WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME='rate_cards' AND tc.CONSTRAINT_TYPE='CHECK'
      AND cc.CHECK_CLAUSE LIKE '%cylinder_package%' AND tc.CONSTRAINT_NAME<>'chk_rate_cards_type' LIMIT 1);
SET @lpg044_sql = IF(@lpg044_chk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE rate_cards DROP CONSTRAINT `', @lpg044_chk, '`'));
PREPARE lpg044_stmt FROM @lpg044_sql; EXECUTE lpg044_stmt; DEALLOCATE PREPARE lpg044_stmt;

ALTER TABLE rate_cards MODIFY rate_type ENUM('gas_per_kg','cylinder_package','filled_cylinder') NOT NULL;
ALTER TABLE rate_change_log MODIFY rate_type ENUM('gas_per_kg','cylinder_package','filled_cylinder') NOT NULL;

SET @lpg044_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_cards' AND CONSTRAINT_NAME='chk_rate_cards_type')=0,
    'ALTER TABLE rate_cards ADD CONSTRAINT chk_rate_cards_type CHECK((rate_type=''gas_per_kg'' AND cylinder_type_id IS NULL) OR (rate_type IN (''cylinder_package'',''filled_cylinder'') AND cylinder_type_id IS NOT NULL))',
    'SELECT 1');
PREPARE lpg044_stmt FROM @lpg044_sql; EXECUTE lpg044_stmt; DEALLOCATE PREPARE lpg044_stmt;

CREATE TABLE IF NOT EXISTS customer_rates(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    rate_type ENUM('gas_per_kg','cylinder_package','filled_cylinder') NOT NULL,
    cylinder_type_id BIGINT UNSIGNED NULL,
    rate_value DECIMAL(14,4) NOT NULL,
    effective_from DATETIME NOT NULL,
    ended_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    ended_by BIGINT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer_rate_lookup(customer_id, rate_type, cylinder_type_id, effective_from),
    CONSTRAINT fk_customer_rates_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_rates_type FOREIGN KEY(cylinder_type_id) REFERENCES cylinder_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_customer_rates_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_customer_rates_ended_by FOREIGN KEY(ended_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_customer_rates_value CHECK(rate_value>=0),
    CONSTRAINT chk_customer_rates_type CHECK((rate_type='gas_per_kg' AND cylinder_type_id IS NULL) OR (rate_type IN ('cylinder_package','filled_cylinder') AND cylinder_type_id IS NOT NULL))
) ENGINE=InnoDB;
