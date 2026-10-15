-- Migration 040: business ownership of cylinder types, expense categories and suppliers.
-- Owner decisions D9-1 (types and expense categories belong to a business) and D9-3
-- (suppliers are business-wide or belong to one branch, configurable per business).
--
-- Existing rows are assigned to the earliest business. Every other business receives its
-- own copy of the cylinder types and expense categories, and any of its rows that still
-- point at the earliest business's records are re-pointed to its own copy by code.
-- Suppliers are copied only into businesses whose branches actually used them.
-- The migration is safe to re-run: every step checks the current schema/data first.

SET @lpg040_primary = (SELECT MIN(id) FROM businesses);

-- ---------------------------------------------------------------------------
-- Columns
-- ---------------------------------------------------------------------------
SET @lpg040_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_types' AND COLUMN_NAME='business_id')=0,
    'ALTER TABLE cylinder_types ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER id', 'SELECT 1');
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

SET @lpg040_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expense_categories' AND COLUMN_NAME='business_id')=0,
    'ALTER TABLE expense_categories ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER id', 'SELECT 1');
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

SET @lpg040_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='business_id')=0,
    'ALTER TABLE suppliers ADD COLUMN business_id BIGINT UNSIGNED NULL AFTER id', 'SELECT 1');
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

SET @lpg040_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='location_id')=0,
    'ALTER TABLE suppliers ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER business_id', 'SELECT 1');
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

UPDATE cylinder_types SET business_id=@lpg040_primary WHERE business_id IS NULL;
UPDATE expense_categories SET business_id=@lpg040_primary WHERE business_id IS NULL;
UPDATE suppliers SET business_id=@lpg040_primary WHERE business_id IS NULL;

-- ---------------------------------------------------------------------------
-- Replace global unique keys with per-business unique keys
-- ---------------------------------------------------------------------------
SET @lpg040_idx = (SELECT s.INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS s
    WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME='cylinder_types' AND s.COLUMN_NAME='code' AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
      AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS s2 WHERE s2.TABLE_SCHEMA=s.TABLE_SCHEMA AND s2.TABLE_NAME=s.TABLE_NAME AND s2.INDEX_NAME=s.INDEX_NAME)=1 LIMIT 1);
SET @lpg040_sql = IF(@lpg040_idx IS NULL, 'SELECT 1', CONCAT('ALTER TABLE cylinder_types DROP INDEX `', @lpg040_idx, '`'));
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

SET @lpg040_idx = (SELECT s.INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS s
    WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME='cylinder_types' AND s.COLUMN_NAME='capacity_kg' AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
      AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS s2 WHERE s2.TABLE_SCHEMA=s.TABLE_SCHEMA AND s2.TABLE_NAME=s.TABLE_NAME AND s2.INDEX_NAME=s.INDEX_NAME)=1 LIMIT 1);
SET @lpg040_sql = IF(@lpg040_idx IS NULL, 'SELECT 1', CONCAT('ALTER TABLE cylinder_types DROP INDEX `', @lpg040_idx, '`'));
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

SET @lpg040_idx = (SELECT s.INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS s
    WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME='expense_categories' AND s.COLUMN_NAME='code' AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
      AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS s2 WHERE s2.TABLE_SCHEMA=s.TABLE_SCHEMA AND s2.TABLE_NAME=s.TABLE_NAME AND s2.INDEX_NAME=s.INDEX_NAME)=1 LIMIT 1);
SET @lpg040_sql = IF(@lpg040_idx IS NULL, 'SELECT 1', CONCAT('ALTER TABLE expense_categories DROP INDEX `', @lpg040_idx, '`'));
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

SET @lpg040_idx = (SELECT s.INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS s
    WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME='suppliers' AND s.COLUMN_NAME='code' AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
      AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS s2 WHERE s2.TABLE_SCHEMA=s.TABLE_SCHEMA AND s2.TABLE_NAME=s.TABLE_NAME AND s2.INDEX_NAME=s.INDEX_NAME)=1 LIMIT 1);
SET @lpg040_sql = IF(@lpg040_idx IS NULL, 'SELECT 1', CONCAT('ALTER TABLE suppliers DROP INDEX `', @lpg040_idx, '`'));
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

-- ---------------------------------------------------------------------------
-- Per-business copies of cylinder types and expense categories
-- ---------------------------------------------------------------------------
INSERT INTO cylinder_types(business_id,code,name,capacity_kg,tare_weight_kg,empty_cylinder_price,is_active,sort_order)
SELECT b.id,ct.code,ct.name,ct.capacity_kg,ct.tare_weight_kg,ct.empty_cylinder_price,ct.is_active,ct.sort_order
FROM businesses b
JOIN cylinder_types ct ON ct.business_id=@lpg040_primary
WHERE b.id<>@lpg040_primary
  AND NOT EXISTS (SELECT 1 FROM cylinder_types x WHERE x.business_id=b.id AND x.code=ct.code);

INSERT INTO expense_categories(business_id,code,name,is_active)
SELECT b.id,ec.code,ec.name,ec.is_active
FROM businesses b
JOIN expense_categories ec ON ec.business_id=@lpg040_primary
WHERE b.id<>@lpg040_primary
  AND NOT EXISTS (SELECT 1 FROM expense_categories x WHERE x.business_id=b.id AND x.code=ec.code);

-- Suppliers used by another business's branches get a business-wide copy in that business.
INSERT INTO suppliers(business_id,location_id,code,name,phone,city,address,credit_limit,opening_balance,is_active)
SELECT DISTINCT l.business_id,NULL,s.code,s.name,s.phone,s.city,s.address,s.credit_limit,0,s.is_active
FROM suppliers s
JOIN (
    SELECT supplier_id,location_id FROM purchases
    UNION
    SELECT supplier_id,location_id FROM supplier_payments
) used ON used.supplier_id=s.id
JOIN locations l ON l.id=used.location_id
WHERE l.business_id<>s.business_id
  AND NOT EXISTS (SELECT 1 FROM suppliers x WHERE x.business_id=l.business_id AND x.name=s.name AND (x.code<=>s.code));

-- ---------------------------------------------------------------------------
-- Re-point other businesses' rows to their own copies (matched by code)
-- ---------------------------------------------------------------------------
UPDATE cylinder_units r JOIN locations l ON l.id=r.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE inventory_movements r JOIN locations l ON l.id=r.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE inventory_opening_balances r JOIN locations l ON l.id=r.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE inventory_policies r JOIN locations l ON l.id=r.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE inventory_wastage_logs r JOIN locations l ON l.id=r.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE rate_cards r JOIN locations l ON l.id=r.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE rate_change_log r JOIN locations l ON l.id=r.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE sale_items r JOIN sales d ON d.id=r.sale_id JOIN locations l ON l.id=d.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE purchase_items r JOIN purchases d ON d.id=r.purchase_id JOIN locations l ON l.id=d.location_id JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=l.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>l.business_id;

UPDATE business_catalog_products r JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=r.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>r.business_id;

UPDATE customer_order_items r JOIN cylinder_types o ON o.id=r.cylinder_type_id
JOIN cylinder_types n ON n.business_id=r.business_id AND n.code=o.code
SET r.cylinder_type_id=n.id WHERE o.business_id<>r.business_id;

UPDATE expenses r JOIN locations l ON l.id=r.location_id JOIN expense_categories o ON o.id=r.category_id
JOIN expense_categories n ON n.business_id=l.business_id AND n.code=o.code
SET r.category_id=n.id WHERE o.business_id<>l.business_id;

UPDATE purchases r JOIN locations l ON l.id=r.location_id JOIN suppliers o ON o.id=r.supplier_id
JOIN suppliers n ON n.business_id=l.business_id AND n.name=o.name AND (n.code<=>o.code)
SET r.supplier_id=n.id WHERE o.business_id<>l.business_id;

UPDATE supplier_payments r JOIN locations l ON l.id=r.location_id JOIN suppliers o ON o.id=r.supplier_id
JOIN suppliers n ON n.business_id=l.business_id AND n.name=o.name AND (n.code<=>o.code)
SET r.supplier_id=n.id WHERE o.business_id<>l.business_id;

-- ---------------------------------------------------------------------------
-- Constraints
-- ---------------------------------------------------------------------------
SET @lpg040_sql = IF((SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_types' AND COLUMN_NAME='business_id')='YES',
    'ALTER TABLE cylinder_types MODIFY business_id BIGINT UNSIGNED NOT NULL, ADD UNIQUE KEY uq_cylinder_types_business_code(business_id,code), ADD UNIQUE KEY uq_cylinder_types_business_capacity(business_id,capacity_kg), ADD CONSTRAINT fk_cylinder_types_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE RESTRICT',
    'SELECT 1');
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

SET @lpg040_sql = IF((SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expense_categories' AND COLUMN_NAME='business_id')='YES',
    'ALTER TABLE expense_categories MODIFY business_id BIGINT UNSIGNED NOT NULL, ADD UNIQUE KEY uq_expense_categories_business_code(business_id,code), ADD CONSTRAINT fk_expense_categories_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE RESTRICT',
    'SELECT 1');
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;

SET @lpg040_sql = IF((SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='business_id')='YES',
    'ALTER TABLE suppliers MODIFY business_id BIGINT UNSIGNED NOT NULL, ADD UNIQUE KEY uq_suppliers_business_code(business_id,code), ADD KEY idx_suppliers_business_location(business_id,location_id,is_active), ADD CONSTRAINT fk_suppliers_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE RESTRICT, ADD CONSTRAINT fk_suppliers_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE RESTRICT',
    'SELECT 1');
PREPARE lpg040_stmt FROM @lpg040_sql; EXECUTE lpg040_stmt; DEALLOCATE PREPARE lpg040_stmt;
