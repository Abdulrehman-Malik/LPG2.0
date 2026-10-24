-- Disposable CI fixtures for the mobile API Playwright scenarios (catalog, rates, isolation business, scoped manager).
INSERT INTO rate_cards(location_id,rate_type,cylinder_type_id,rate_value,effective_from,effective_to,created_by)
SELECT l.id,'gas_per_kg',NULL,100.0000,'2026-01-01 00:00:00',NULL,u.id
FROM locations l JOIN users u ON u.username='admin'
WHERE l.code='MAIN'
  AND NOT EXISTS (
    SELECT 1 FROM rate_cards rc
    WHERE rc.location_id=l.id AND rc.rate_type='gas_per_kg'
      AND rc.cylinder_type_id IS NULL
      AND rc.effective_from <= NOW()
      AND (rc.effective_to IS NULL OR rc.effective_to > NOW())
  );

INSERT INTO rate_cards(location_id,rate_type,cylinder_type_id,rate_value,effective_from,effective_to,created_by)
SELECT l.id,'cylinder_package',ct.id,250.0000,'2026-01-01 00:00:00',NULL,u.id
FROM locations l
JOIN cylinder_types ct ON ct.code='C6'
JOIN users u ON u.username='admin'
WHERE l.code='MAIN'
  AND NOT EXISTS (
    SELECT 1 FROM rate_cards rc
    WHERE rc.location_id=l.id AND rc.rate_type='cylinder_package'
      AND rc.cylinder_type_id=ct.id
      AND rc.effective_from <= NOW()
      AND (rc.effective_to IS NULL OR rc.effective_to > NOW())
  );

INSERT INTO business_catalog_products(
  business_id,cylinder_type_id,sale_mode,display_name,description,
  quantity_unit,min_quantity,quantity_step,is_active,sort_order,created_by,updated_by
)
SELECT l.business_id,ct.id,'sell_gas_only','QA Mobile Gas',
       'Disposable CI API smoke-test product','kg',1.000,1.000,1,999,u.id,u.id
FROM locations l
JOIN cylinder_types ct ON ct.code='C6'
JOIN users u ON u.username='admin'
WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE
  display_name=VALUES(display_name),description=VALUES(description),
  quantity_unit=VALUES(quantity_unit),min_quantity=1.000,
  quantity_step=1.000,is_active=1,updated_by=VALUES(updated_by);

INSERT INTO shop_product_availability(business_id,location_id,business_catalog_product_id,is_active,updated_by)
SELECT l.business_id,l.id,p.id,1,u.id
FROM locations l
JOIN business_catalog_products p ON p.business_id=l.business_id
JOIN cylinder_types ct ON ct.id=p.cylinder_type_id AND ct.code='C6'
JOIN users u ON u.username='admin'
WHERE l.code='MAIN' AND p.sale_mode='sell_gas_only'
ON DUPLICATE KEY UPDATE is_active=1,updated_by=VALUES(updated_by);
INSERT INTO shop_product_availability(business_id,location_id,business_catalog_product_id,is_active,updated_by)
SELECT l.business_id,l.id,p.id,1,u.id
FROM locations l
JOIN business_catalog_products p ON p.business_id=l.business_id
JOIN cylinder_types ct ON ct.id=p.cylinder_type_id AND ct.code='C6'
JOIN users u ON u.username='admin'
WHERE l.code='MAIN' AND p.sale_mode='sell_gas_only'
ON DUPLICATE KEY UPDATE is_active=1,updated_by=VALUES(updated_by);

INSERT INTO businesses(code,name,city,is_active)
VALUES('QA-ISOLATION','QA Isolation Business','Lahore',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),city=VALUES(city),is_active=1;

INSERT INTO locations(business_id,code,name,city,is_active)
SELECT b.id,'QA-OTHER','QA Other Shop','Lahore',1
FROM businesses b WHERE b.code='QA-ISOLATION'
ON DUPLICATE KEY UPDATE business_id=VALUES(business_id),name=VALUES(name),is_active=1;

INSERT INTO users(location_id,is_system_admin,role_id,full_name,username,email,password_hash,is_active)
SELECT l.id,0,r.id,'QA Scoped Manager','qa_scoped_manager','qa_scoped_manager@perfectlpg.local',
       admin.password_hash,1
FROM locations l
JOIN roles r ON r.code='MANAGER'
JOIN users admin ON admin.username='admin'
WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE location_id=VALUES(location_id),is_system_admin=0,
  password_hash=VALUES(password_hash),is_active=1;

INSERT INTO user_shop_access(user_id,location_id,is_default)
SELECT u.id,l.id,1 FROM users u JOIN locations l ON l.code='MAIN'
WHERE u.username='qa_scoped_manager'
ON DUPLICATE KEY UPDATE is_default=1;

-- BL-003: the QA accounts count as having changed the shipped default password already.
UPDATE users SET must_change_password=0 WHERE username IN ('admin','qa_scoped_manager');
