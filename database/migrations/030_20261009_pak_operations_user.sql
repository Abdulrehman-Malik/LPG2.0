-- Create the limited operational account pak and its configurable role.
-- Idempotent: safe to retry without duplicating role, permissions, or user.
INSERT INTO roles(code,name,description)
VALUES('PAK_OPS','Operations User','Dashboard, sales and receipts, inventory, customer ledger, cash, expenses and rates')
ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    description=VALUES(description);

-- Grant only the requested operational screens. No supplier, purchasing,
-- reporting, settings, backup, audit, or user/role administration access.
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'DASHBOARD_VIEW',
    'POS_SALE',
    'POS_HISTORY',
    'POS_VOID',
    'INVENTORY_MANAGE',
    'CUSTOMER_MANAGE',
    'CASH_MANAGE',
    'EXPENSE_MANAGE',
    'RATE_MANAGE'
)
WHERE r.code='PAK_OPS';

-- The requested initial password is stored as a password_hash (bcrypt),
-- never as plaintext. The account is reactivated and kept on the MAIN location
-- if this migration is manually retried.
INSERT INTO users(location_id,role_id,full_name,username,email,password_hash,is_active)
SELECT l.id,r.id,'Pak Operations','pak',NULL,
       '$2y$12$ck29hJ6DHISNvvHqavd5OeNFXqdErMPIYyAcI3LumNG2ejdB5oZ1W',1
FROM locations l
JOIN roles r ON r.code='PAK_OPS'
WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE
    location_id=VALUES(location_id),
    role_id=VALUES(role_id),
    full_name=VALUES(full_name),
    password_hash=VALUES(password_hash),
    is_active=1;
