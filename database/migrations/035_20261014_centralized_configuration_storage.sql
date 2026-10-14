-- Migration 035: centralized application/business/shop configuration storage.
-- Effective-value precedence and per-setting scope/guardrails are enforced by ConfigurationService.
CREATE TABLE IF NOT EXISTS application_settings(
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value JSON NOT NULL,
    updated_by BIGINT UNSIGNED,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_application_settings_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS business_settings(
    business_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value JSON NOT NULL,
    updated_by BIGINT UNSIGNED,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(business_id,setting_key),
    CONSTRAINT fk_business_settings_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_business_settings_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shop_setting_overrides(
    location_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value JSON NOT NULL,
    updated_by BIGINT UNSIGNED,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(location_id,setting_key),
    CONSTRAINT fk_shop_setting_override_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE,
    CONSTRAINT fk_shop_setting_overrides_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO application_settings(setting_key,setting_value) VALUES
('customer_ordering_feature_enabled',CAST('true' AS JSON)),
('orders_enabled',CAST('true' AS JSON)),
('delivery_enabled',CAST('true' AS JSON)),
('collection_enabled',CAST('true' AS JSON)),
('delivery_fee',CAST('0' AS JSON)),
('customer_self_registration_enabled',CAST('true' AS JSON)),
('supported_languages',CAST('["en"]' AS JSON)),
('access_token_ttl_seconds',CAST('900' AS JSON)),
('refresh_idle_ttl_seconds',CAST('2592000' AS JSON)),
('refresh_absolute_ttl_seconds',CAST('7776000' AS JSON)),
('password_min_length',CAST('10' AS JSON))
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
