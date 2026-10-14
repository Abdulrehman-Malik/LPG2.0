-- Perfect LPG - Standardize physical cylinder codes
--
-- New format:
--   <CYLINDER_TYPE_CODE>-<GLOBAL_SEQUENCE>
-- Example:
--   C11_8-000123
--
-- The cylinder_units AUTO_INCREMENT id is the global application sequence.
-- This keeps the sequence unique across all cylinder types and locations.
--
-- Existing physical cylinders are converted from the legacy CYL-... format
-- without changing their cylinder_units.id or inventory relationships.

USE perfect_lpg;

UPDATE cylinder_units cu
JOIN cylinder_types ct ON ct.id = cu.cylinder_type_id
SET cu.unit_code = CONCAT(ct.code, '-', LPAD(cu.id, 6, '0'));

SELECT
    'Physical cylinder codes standardized' AS status,
    COUNT(*) AS cylinder_count
FROM cylinder_units
WHERE unit_code REGEXP '^[A-Za-z0-9_]+-[0-9]{6}$';