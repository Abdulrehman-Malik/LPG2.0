-- Cylinder Sale: configurable empty-cylinder sale price per cylinder type
-- Deployment sequence: 016
ALTER TABLE cylinder_types
  ADD COLUMN empty_cylinder_price DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER tare_weight_kg;
