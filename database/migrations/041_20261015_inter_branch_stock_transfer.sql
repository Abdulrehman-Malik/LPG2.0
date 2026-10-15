-- Migration 041: inter-branch stock transfer (owner decision D9-2).
-- Physical cylinders move between branches of the same business. In the two-step flow a
-- dispatched cylinder is 'in_transit' (in neither branch's available stock) until the
-- receiving branch confirms receipt. Idempotent.

SET @lpg041_sql = IF((SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_units' AND COLUMN_NAME='status') NOT LIKE '%in_transit%',
    'ALTER TABLE cylinder_units MODIFY status ENUM(''filled'',''empty'',''custody'',''sold'',''in_transit'') NOT NULL',
    'SELECT 1');
PREPARE lpg041_stmt FROM @lpg041_sql; EXECUTE lpg041_stmt; DEALLOCATE PREPARE lpg041_stmt;

CREATE TABLE IF NOT EXISTS stock_transfers(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    transfer_no VARCHAR(30) NOT NULL,
    from_location_id BIGINT UNSIGNED NOT NULL,
    to_location_id BIGINT UNSIGNED NOT NULL,
    flow ENUM('one_step','two_step') NOT NULL,
    status ENUM('in_transit','received','cancelled') NOT NULL,
    transfer_at DATETIME NOT NULL,
    notes VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    received_by BIGINT UNSIGNED NULL,
    received_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    UNIQUE KEY uq_stock_transfer_no(business_id,transfer_no),
    KEY idx_stock_transfer_from(from_location_id,status,transfer_at),
    KEY idx_stock_transfer_to(to_location_id,status,transfer_at),
    CONSTRAINT fk_stock_transfer_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_transfer_from FOREIGN KEY(from_location_id) REFERENCES locations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_transfer_to FOREIGN KEY(to_location_id) REFERENCES locations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_transfer_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_transfer_received_by FOREIGN KEY(received_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_transfer_cancelled_by FOREIGN KEY(cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    CHECK(from_location_id<>to_location_id)
)ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_transfer_items(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id BIGINT UNSIGNED NOT NULL,
    cylinder_unit_id BIGINT UNSIGNED NOT NULL,
    cylinder_type_id BIGINT UNSIGNED NOT NULL,
    unit_status ENUM('filled','empty') NOT NULL,
    gas_weight_kg DECIMAL(10,3) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_stock_transfer_unit(transfer_id,cylinder_unit_id),
    KEY idx_stock_transfer_items_unit(cylinder_unit_id),
    CONSTRAINT fk_stock_transfer_items_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_transfer_items_unit FOREIGN KEY(cylinder_unit_id) REFERENCES cylinder_units(id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_transfer_items_type FOREIGN KEY(cylinder_type_id) REFERENCES cylinder_types(id) ON DELETE RESTRICT
)ENGINE=InnoDB;
