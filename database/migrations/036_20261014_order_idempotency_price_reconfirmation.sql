-- Migration 036: idempotency payload consistency and explicit price reconfirmation state.
-- These columns apply only to the new customer_orders table; no legacy LPG sale/ledger data is changed.
SET @lpg036_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_orders' AND COLUMN_NAME='request_fingerprint')=0,
    'ALTER TABLE customer_orders ADD COLUMN request_fingerprint CHAR(64) NOT NULL DEFAULT ''0000000000000000000000000000000000000000000000000000000000000000'' AFTER idempotency_key',
    'SELECT 1'
);
PREPARE lpg036_stmt FROM @lpg036_sql; EXECUTE lpg036_stmt; DEALLOCATE PREPARE lpg036_stmt;

SET @lpg036_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_orders' AND COLUMN_NAME='price_revision')=0,
    'ALTER TABLE customer_orders ADD COLUMN price_revision INT UNSIGNED NOT NULL DEFAULT 1 AFTER request_fingerprint',
    'SELECT 1'
);
PREPARE lpg036_stmt FROM @lpg036_sql; EXECUTE lpg036_stmt; DEALLOCATE PREPARE lpg036_stmt;

SET @lpg036_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_orders' AND COLUMN_NAME='price_reconfirmation_required')=0,
    'ALTER TABLE customer_orders ADD COLUMN price_reconfirmation_required BOOLEAN NOT NULL DEFAULT 0 AFTER price_revision',
    'SELECT 1'
);
PREPARE lpg036_stmt FROM @lpg036_sql; EXECUTE lpg036_stmt; DEALLOCATE PREPARE lpg036_stmt;

SET @lpg036_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_orders' AND COLUMN_NAME='price_reconfirmed_at')=0,
    'ALTER TABLE customer_orders ADD COLUMN price_reconfirmed_at DATETIME NULL AFTER price_reconfirmation_required',
    'SELECT 1'
);
PREPARE lpg036_stmt FROM @lpg036_sql; EXECUTE lpg036_stmt; DEALLOCATE PREPARE lpg036_stmt;
