-- Perfect LPG: configurable stock validation + wastage tracking
USE perfect_lpg;

CREATE TABLE IF NOT EXISTS inventory_policies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 location_id BIGINT UNSIGNED NOT NULL,
 cylinder_type_id BIGINT UNSIGNED NULL,
 stock_validation_enabled BOOLEAN NOT NULL DEFAULT 1,
 wastage_mode ENUM('percent','fixed_kg') NOT NULL DEFAULT 'percent',
 wastage_percent DECIMAL(8,3) NOT NULL DEFAULT 0,
 wastage_fixed_kg DECIMAL(8,3) NOT NULL DEFAULT 0,
 is_active BOOLEAN NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_inventory_policy(location_id,cylinder_type_id),
 FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE,
 FOREIGN KEY(cylinder_type_id) REFERENCES cylinder_types(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CHECK(wastage_percent>=0 AND wastage_percent<=100), CHECK(wastage_fixed_kg>=0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_wastage_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 location_id BIGINT UNSIGNED NOT NULL,
 cylinder_type_id BIGINT UNSIGNED NULL,
 cylinder_unit_id BIGINT UNSIGNED NULL,
 gas_weight_kg DECIMAL(14,3) NOT NULL,
 reason VARCHAR(255) NOT NULL,
 source_type VARCHAR(40) NOT NULL DEFAULT 'adjustment',
 source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE RESTRICT,
 FOREIGN KEY(cylinder_type_id) REFERENCES cylinder_types(id) ON DELETE RESTRICT,
 FOREIGN KEY(cylinder_unit_id) REFERENCES cylinder_units(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 KEY idx_wastage_date(location_id,created_at), KEY idx_wastage_type(location_id,cylinder_type_id)
) ENGINE=InnoDB;

INSERT INTO inventory_policies(location_id,cylinder_type_id)
SELECT l.id,NULL FROM locations l
LEFT JOIN inventory_policies p ON p.location_id=l.id AND p.cylinder_type_id IS NULL
WHERE p.id IS NULL;
