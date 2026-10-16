-- Migration 043: supplier opening balances per branch (owner decision D9-4).
-- A supplier's opening payable now belongs to a branch. With the business setting
-- supplier_balance_mode = 'branch' each branch counts only its own opening; with 'business'
-- the openings of all branches form one opening for the shared balance.
-- Existing suppliers.opening_balance moves to the supplier's own branch, or for a
-- business-wide supplier to the business's first branch, so it is no longer counted at
-- every branch. suppliers.opening_balance is kept as the total for compatibility.
-- Idempotent.

CREATE TABLE IF NOT EXISTS supplier_opening_balances(
    supplier_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(supplier_id, location_id),
    KEY idx_supplier_opening_location(location_id),
    CONSTRAINT fk_supplier_opening_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    CONSTRAINT fk_supplier_opening_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE,
    CONSTRAINT fk_supplier_opening_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CHECK(amount>=0)
) ENGINE=InnoDB;

INSERT INTO supplier_opening_balances(supplier_id, location_id, amount)
SELECT s.id,
       COALESCE(s.location_id, (SELECT MIN(l.id) FROM locations l WHERE l.business_id=s.business_id)),
       s.opening_balance
FROM suppliers s
WHERE s.opening_balance > 0
  AND COALESCE(s.location_id, (SELECT MIN(l.id) FROM locations l WHERE l.business_id=s.business_id)) IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM supplier_opening_balances o WHERE o.supplier_id=s.id);
