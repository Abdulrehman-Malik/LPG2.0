-- Cylinder Sale: persist gas rate and cylinder price components
-- Deployment sequence: 017
ALTER TABLE sale_items
  ADD COLUMN gas_rate DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER applied_rate,
  ADD COLUMN cylinder_price DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER gas_rate;
