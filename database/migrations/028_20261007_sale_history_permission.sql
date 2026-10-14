-- Perfect LPG - Dedicated Sale History permission
-- Keeps POS sale creation and Sale History access independently configurable.

INSERT INTO permissions(code,name) VALUES
('POS_HISTORY','View sale history')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.code='POS_HISTORY'
WHERE r.code IN('ADMIN','MANAGER','CASHIER');
