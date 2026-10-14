-- Add comments to opening inventory for existing installations.
ALTER TABLE inventory_opening_balances ADD COLUMN comments VARCHAR(500) NULL AFTER quantity;
