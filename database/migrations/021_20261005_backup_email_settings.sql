-- Database Backup Email / SMTP settings per shop.
-- Safe to run once through the project migration runner.

ALTER TABLE shop_settings
    ADD COLUMN smtp_host VARCHAR(255) NULL AFTER backup_notes,
    ADD COLUMN smtp_port INT NOT NULL DEFAULT 587 AFTER smtp_host,
    ADD COLUMN smtp_username VARCHAR(255) NULL AFTER smtp_port,
    ADD COLUMN smtp_password TEXT NULL AFTER smtp_username,
    ADD COLUMN smtp_encryption VARCHAR(10) NOT NULL DEFAULT 'tls' AFTER smtp_password,
    ADD COLUMN smtp_from_email VARCHAR(255) NULL AFTER smtp_encryption,
    ADD COLUMN smtp_from_name VARCHAR(150) NULL AFTER smtp_from_email,
    ADD COLUMN smtp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER smtp_from_name;
