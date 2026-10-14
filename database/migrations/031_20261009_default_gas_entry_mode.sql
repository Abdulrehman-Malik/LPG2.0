ALTER TABLE shop_settings
    ADD COLUMN default_gas_entry_mode ENUM('quantity','amount','cylinders') NOT NULL DEFAULT 'quantity'
    AFTER default_transaction_type;
