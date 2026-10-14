-- Migration 034: enforce uniqueness among pending customer signup usernames.
-- Resolved requests release the username key; approved accounts are protected by
-- customer_app_accounts.uq_customer_account_username.
SET @lpg034_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_signup_requests' AND COLUMN_NAME='pending_username')=0,
    'ALTER TABLE customer_signup_requests ADD COLUMN pending_username VARCHAR(80) GENERATED ALWAYS AS (CASE WHEN status = ''pending_approval'' THEN LOWER(username) ELSE NULL END) STORED AFTER username',
    'SELECT 1'
);
PREPARE lpg034_stmt FROM @lpg034_sql;
EXECUTE lpg034_stmt;
DEALLOCATE PREPARE lpg034_stmt;

SET @lpg034_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_signup_requests' AND INDEX_NAME='uq_customer_signup_pending_username')=0,
    'ALTER TABLE customer_signup_requests ADD UNIQUE KEY uq_customer_signup_pending_username(pending_username)',
    'SELECT 1'
);
PREPARE lpg034_stmt FROM @lpg034_sql;
EXECUTE lpg034_stmt;
DEALLOCATE PREPARE lpg034_stmt;
