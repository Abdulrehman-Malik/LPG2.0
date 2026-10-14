-- Perfect LPG - Permission synchronization for existing installations
-- Safe for existing data: adds missing standard permissions and backfills default role mappings.
-- Does not remove permissions from custom roles.

INSERT INTO permissions(code,name) VALUES
('DASHBOARD_VIEW','View dashboard'),
('POS_SALE','Create POS sales'),
('POS_VOID','Void posted sales'),
('CUSTOMER_MANAGE','Manage customers'),
('SUPPLIER_MANAGE','Manage suppliers'),
('RATE_MANAGE','Manage LPG rates'),
('INVENTORY_MANAGE','Manage inventory'),
('PURCHASE_MANAGE','Manage purchases'),
('CASH_MANAGE','Manage counter cash'),
('EXPENSE_MANAGE','Manage expenses'),
('REPORT_VIEW','View reports'),
('USER_MANAGE','Manage users'),
('AUDIT_VIEW','View audit log')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code='ADMIN';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code='MANAGER' AND p.code<>'USER_MANAGE';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.code IN('DASHBOARD_VIEW','POS_SALE','CUSTOMER_MANAGE','REPORT_VIEW')
WHERE r.code='CASHIER';
