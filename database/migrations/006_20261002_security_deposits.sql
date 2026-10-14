-- Security deposit, cylinder custody and header-level transaction model
-- Safe to retry: existing columns, indexes, foreign keys and tables are preserved.

DROP PROCEDURE IF EXISTS lpg_migration_006_apply;
DELIMITER $$

CREATE PROCEDURE lpg_migration_006_apply()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'security_deposit_amount'
    ) THEN
        ALTER TABLE sales
            ADD COLUMN security_deposit_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_amount;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sales'
          AND COLUMN_NAME = 'security_deposit_refund_amount'
    ) THEN
        ALTER TABLE sales
            ADD COLUMN security_deposit_refund_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER security_deposit_amount;
    END IF;

    ALTER TABLE sales
        MODIFY transaction_type ENUM(
            'filled_cylinder','refill_service','cylinder_exchange','empty_intake','empty_sale','mixed',
            'gas_sale','cylinder_sale','security_deposit','cylinder_return'
        ) NOT NULL;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sale_items'
          AND COLUMN_NAME = 'customer_cylinder_unit_id'
    ) THEN
        ALTER TABLE sale_items
            ADD COLUMN customer_cylinder_unit_id BIGINT UNSIGNED NULL AFTER cylinder_type_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sale_items'
          AND INDEX_NAME = 'idx_sale_item_customer_cylinder'
    ) THEN
        ALTER TABLE sale_items
            ADD KEY idx_sale_item_customer_cylinder(customer_cylinder_unit_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sale_items'
          AND CONSTRAINT_NAME = 'fk_sale_item_customer_cylinder'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE sale_items
            ADD CONSTRAINT fk_sale_item_customer_cylinder
            FOREIGN KEY (customer_cylinder_unit_id)
            REFERENCES cylinder_units(id) ON DELETE RESTRICT;
    END IF;

    ALTER TABLE cylinder_units
        MODIFY status ENUM('filled','empty','custody','sold') NOT NULL DEFAULT 'empty';

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cylinder_units'
          AND COLUMN_NAME = 'custody_customer_id'
    ) THEN
        ALTER TABLE cylinder_units
            ADD COLUMN custody_customer_id BIGINT UNSIGNED NULL AFTER status;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cylinder_units'
          AND INDEX_NAME = 'idx_cylinder_custody_customer'
    ) THEN
        ALTER TABLE cylinder_units
            ADD KEY idx_cylinder_custody_customer(location_id,custody_customer_id,status);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cylinder_units'
          AND CONSTRAINT_NAME = 'fk_cylinder_custody_customer'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE cylinder_units
            ADD CONSTRAINT fk_cylinder_custody_customer
            FOREIGN KEY (custody_customer_id)
            REFERENCES customers(id) ON DELETE RESTRICT;
    END IF;

    ALTER TABLE cash_transactions
        MODIFY transaction_type ENUM(
            'opening_float','sale_cash','customer_receipt','purchase_cash','supplier_payment',
            'expense','manual_in','manual_out','cash_handover',
            'security_deposit','security_deposit_refund'
        ) NOT NULL;

    CREATE TABLE IF NOT EXISTS cylinder_custody (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        location_id BIGINT UNSIGNED NOT NULL,
        customer_id BIGINT UNSIGNED NOT NULL,
        cylinder_unit_id BIGINT UNSIGNED NOT NULL,
        status ENUM('issued','returned') NOT NULL DEFAULT 'issued',
        issue_sale_id BIGINT UNSIGNED NULL,
        return_sale_id BIGINT UNSIGNED NULL,
        deposit_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        refund_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        returned_at DATETIME NULL,
        created_by BIGINT UNSIGNED NULL,
        updated_by BIGINT UNSIGNED NULL,
        notes VARCHAR(255) NULL,
        CONSTRAINT fk_custody_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE RESTRICT,
        CONSTRAINT fk_custody_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
        CONSTRAINT fk_custody_unit FOREIGN KEY(cylinder_unit_id) REFERENCES cylinder_units(id) ON DELETE RESTRICT,
        CONSTRAINT fk_custody_issue_sale FOREIGN KEY(issue_sale_id) REFERENCES sales(id) ON DELETE RESTRICT,
        CONSTRAINT fk_custody_return_sale FOREIGN KEY(return_sale_id) REFERENCES sales(id) ON DELETE RESTRICT,
        CONSTRAINT fk_custody_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_custody_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
        KEY idx_custody_unit_status(cylinder_unit_id,status),
        KEY idx_custody_customer(location_id,customer_id,status),
        KEY idx_custody_issue_sale(issue_sale_id),
        KEY idx_custody_return_sale(return_sale_id),
        CHECK(deposit_amount>=0 AND refund_amount>=0)
    ) ENGINE=InnoDB;

    CREATE TABLE IF NOT EXISTS customer_security_deposits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        location_id BIGINT UNSIGNED NOT NULL,
        customer_id BIGINT UNSIGNED NOT NULL,
        entry_type ENUM('hold','refund') NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        sale_id BIGINT UNSIGNED NULL,
        custody_id BIGINT UNSIGNED NULL,
        transaction_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by BIGINT UNSIGNED NULL,
        notes VARCHAR(255) NULL,
        CONSTRAINT fk_deposit_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE RESTRICT,
        CONSTRAINT fk_deposit_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
        CONSTRAINT fk_deposit_sale FOREIGN KEY(sale_id) REFERENCES sales(id) ON DELETE RESTRICT,
        CONSTRAINT fk_deposit_custody FOREIGN KEY(custody_id) REFERENCES cylinder_custody(id) ON DELETE RESTRICT,
        CONSTRAINT fk_deposit_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
        KEY idx_deposit_customer(location_id,customer_id,transaction_at),
        KEY idx_deposit_sale(sale_id),
        CHECK(amount>0)
    ) ENGINE=InnoDB;
END$$

DELIMITER ;

CALL lpg_migration_006_apply();

DROP PROCEDURE IF EXISTS lpg_migration_006_apply;
