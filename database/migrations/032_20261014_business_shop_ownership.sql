-- Mobile multi-shop foundation (additive). No historical customer or shop ownership is inferred.
-- Fresh-install schema uses NOT NULL ownership. This compatibility migration leaves legacy rows
-- unassigned for a separately reviewed upgrade plan; it never backfills or rewrites transactions.

CREATE TABLE IF NOT EXISTS businesses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(80) NULL,
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO businesses(code,name,city)
VALUES('DEFAULT','Perfect LPG (Pvt.) LTD','Lahore')
ON DUPLICATE KEY UPDATE code=VALUES(code);

SET @lpg032_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND COLUMN_NAME='business_id')=0,
    'ALTER TABLE locations ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER id',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;

SET @lpg032_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND INDEX_NAME='idx_locations_business')=0,
    'ALTER TABLE locations ADD INDEX idx_locations_business(business_id,is_active)',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;

SET @lpg032_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND CONSTRAINT_NAME='fk_locations_business')=0,
    'ALTER TABLE locations ADD CONSTRAINT fk_locations_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;

SET @lpg032_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_system_admin')=0,
    'ALTER TABLE users ADD COLUMN is_system_admin BOOLEAN NOT NULL DEFAULT 0 AFTER location_id',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;

SET @lpg032_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='location_id')=0,
    'ALTER TABLE customers ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER id',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;

SET @lpg032_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND INDEX_NAME='idx_customers_location_active')=0,
    'ALTER TABLE customers ADD INDEX idx_customers_location_active(location_id,is_active)',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;

SET @lpg032_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND CONSTRAINT_NAME='fk_customers_location')=0,
    'ALTER TABLE customers ADD CONSTRAINT fk_customers_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;


-- Customer codes are unique within a shop, not globally across the SaaS.
SET @lpg032_customer_code_unique = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers'
      AND COLUMN_NAME='code' AND NON_UNIQUE=0 AND INDEX_NAME <> 'PRIMARY'
);
SET @lpg032_sql = IF(
    @lpg032_customer_code_unique > 0
      AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND INDEX_NAME='code') > 0,
    'ALTER TABLE customers DROP INDEX code',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;

SET @lpg032_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND INDEX_NAME='uq_customer_location_code')=0,
    'ALTER TABLE customers ADD UNIQUE KEY uq_customer_location_code(location_id,code)',
    'SELECT 1'
);
PREPARE lpg032_stmt FROM @lpg032_sql;
EXECUTE lpg032_stmt;
DEALLOCATE PREPARE lpg032_stmt;

CREATE TABLE IF NOT EXISTS user_business_access (
    user_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    access_level ENUM('head_office') NOT NULL DEFAULT 'head_office',
    is_default BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id,business_id),
    KEY idx_user_business_access_business(business_id,user_id),
    CONSTRAINT fk_user_business_access_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_business_access_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_shop_access (
    user_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id,location_id),
    KEY idx_user_shop_access_location(location_id,user_id),
    CONSTRAINT fk_user_shop_access_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_shop_access_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB;
