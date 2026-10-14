-- Customer credit-sale control.
-- Credit is disabled by default. Enable it per actual customer from Customers / Parties.
ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS allow_credit_sale BOOLEAN NOT NULL DEFAULT 0 AFTER credit_limit;
