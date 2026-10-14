-- Perfect LPG: branch-level shop settings
-- Run once on an existing database after deploying this feature.
USE perfect_lpg;

CREATE TABLE IF NOT EXISTS shop_settings(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id BIGINT UNSIGNED NOT NULL UNIQUE,
    default_sale_mode ENUM('sell_gas_only','replace_same','sell_filled','replace_different','sell_empty') NOT NULL DEFAULT 'sell_gas_only',
    default_payment_mode ENUM('cash','cheque','online','credit') NOT NULL DEFAULT 'cash',
    pos_font_size_px DECIMAL(4,1) NOT NULL DEFAULT 14.0,
    theme_mode ENUM('light','dark') NOT NULL DEFAULT 'light',
    font_family VARCHAR(30) NOT NULL DEFAULT 'system',
    primary_color CHAR(7) NOT NULL DEFAULT '#1b2a3a',
    accent_color CHAR(7) NOT NULL DEFAULT '#ff7a1a',
    stock_validation_enabled BOOLEAN NOT NULL DEFAULT 1,
    allow_stock_override BOOLEAN NOT NULL DEFAULT 1,
    credit_limit_validation_mode ENUM('none','customer','shop') NOT NULL DEFAULT 'none',
    shop_credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0,
    backup_enabled BOOLEAN NOT NULL DEFAULT 0,
    db_backup_url VARCHAR(500),
    backup_notes TEXT,
    receipt_title VARCHAR(120) NOT NULL DEFAULT 'SALE RECEIPT',
    receipt_footer VARCHAR(500) NOT NULL DEFAULT 'Thank you',
    show_address_on_receipt BOOLEAN NOT NULL DEFAULT 1,
    settings_note VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE shop_settings ADD COLUMN IF NOT EXISTS pos_font_size_px DECIMAL(4,1) NOT NULL DEFAULT 14.0 AFTER default_payment_mode;
ALTER TABLE shop_settings ADD COLUMN IF NOT EXISTS theme_mode ENUM('light','dark') NOT NULL DEFAULT 'light' AFTER pos_font_size_px;
ALTER TABLE shop_settings ADD COLUMN IF NOT EXISTS font_family VARCHAR(30) NOT NULL DEFAULT 'system' AFTER theme_mode;
ALTER TABLE shop_settings ADD COLUMN IF NOT EXISTS primary_color CHAR(7) NOT NULL DEFAULT '#1b2a3a' AFTER font_family;
ALTER TABLE shop_settings ADD COLUMN IF NOT EXISTS accent_color CHAR(7) NOT NULL DEFAULT '#ff7a1a' AFTER primary_color;
ALTER TABLE shop_settings ADD COLUMN IF NOT EXISTS credit_limit_validation_mode ENUM('none','customer','shop') NOT NULL DEFAULT 'none' AFTER allow_stock_override;
ALTER TABLE shop_settings ADD COLUMN IF NOT EXISTS shop_credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER credit_limit_validation_mode;

INSERT INTO shop_settings(location_id)
SELECT l.id FROM locations l
WHERE NOT EXISTS (SELECT 1 FROM shop_settings s WHERE s.location_id=l.id);

UPDATE inventory_policies p
JOIN shop_settings s ON s.location_id=p.location_id AND p.cylinder_type_id IS NULL
SET p.stock_validation_enabled=s.stock_validation_enabled;
