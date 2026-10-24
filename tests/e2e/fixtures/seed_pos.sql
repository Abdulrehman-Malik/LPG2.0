-- Disposable CI fixtures for the POS browser regression (customers, physical cylinders, open cash session).
INSERT INTO customers(location_id,business_id,code,name,phone,city,address,credit_limit,allow_credit_sale,opening_balance,is_active)
SELECT l.id,l.business_id,'QA-E2E-CUST','QA E2E Customer','03000000000','Lahore','QA Test Street',0,0,0,1
FROM locations l WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE name=VALUES(name),phone=VALUES(phone),city=VALUES(city),
  address=VALUES(address),credit_limit=0,allow_credit_sale=0,opening_balance=0,is_active=1;

INSERT INTO customers(location_id,business_id,code,name,phone,city,address,credit_limit,allow_credit_sale,opening_balance,is_active)
SELECT l.id,l.business_id,'QA-E2E-CREDIT','QA E2E Credit Customer','03000000001','Lahore','QA Credit Test Street',50,1,0,1
FROM locations l WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE name=VALUES(name),phone=VALUES(phone),city=VALUES(city),
  address=VALUES(address),credit_limit=50,allow_credit_sale=1,opening_balance=0,is_active=1;

INSERT INTO shop_settings(
  location_id,business_id,individual_cylinder_tracking,allow_pos_source_cylinder_selection,
  default_gas_entry_mode,credit_limit_validation_mode
)
SELECT l.id,l.business_id,1,1,'quantity','customer'
FROM locations l
WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE
  individual_cylinder_tracking=VALUES(individual_cylinder_tracking),
  allow_pos_source_cylinder_selection=VALUES(allow_pos_source_cylinder_selection),
  default_gas_entry_mode=VALUES(default_gas_entry_mode),
  credit_limit_validation_mode=VALUES(credit_limit_validation_mode);

INSERT INTO cylinder_units(location_id,business_id,cylinder_type_id,unit_code,status,gas_weight_kg,source_type,created_by)
SELECT l.id,l.business_id,ct.id,'QA-E2E-C6-001','filled',6.000,'qa_seed',u.id
FROM locations l
JOIN cylinder_types ct ON ct.code='C6'
JOIN users u ON u.username='admin'
WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE cylinder_type_id=VALUES(cylinder_type_id),status='filled',
  gas_weight_kg=6.000,source_type='qa_seed',created_by=VALUES(created_by);

INSERT INTO inventory_movements(location_id,business_id,inventory_type,cylinder_type_id,quantity,direction,
  source_type,source_id,cylinder_unit_id,created_by,notes)
SELECT cu.location_id,cu.business_id,'filled_cylinder',cu.cylinder_type_id,1,'in','qa_seed',cu.id,cu.id,u.id,
  'Disposable Playwright QA opening cylinder'
FROM cylinder_units cu JOIN users u ON u.username='admin'
WHERE cu.unit_code='QA-E2E-C6-001'
  AND NOT EXISTS (SELECT 1 FROM inventory_movements im
    WHERE im.source_type='qa_seed' AND im.source_id=cu.id AND im.inventory_type='filled_cylinder');

INSERT INTO inventory_movements(location_id,business_id,inventory_type,cylinder_type_id,quantity,direction,
  source_type,source_id,cylinder_unit_id,created_by,notes)
SELECT cu.location_id,cu.business_id,'gas_kg',NULL,6.000,'in','qa_seed',cu.id,cu.id,u.id,
  'Disposable Playwright QA opening gas weight'
FROM cylinder_units cu JOIN users u ON u.username='admin'
WHERE cu.unit_code='QA-E2E-C6-001'
  AND NOT EXISTS (SELECT 1 FROM inventory_movements im
    WHERE im.source_type='qa_seed' AND im.source_id=cu.id AND im.inventory_type='gas_kg');

INSERT INTO cylinder_units(location_id,business_id,cylinder_type_id,unit_code,status,gas_weight_kg,source_type,created_by)
SELECT l.id,l.business_id,ct.id,'QA-ORDER-C6-001','filled',6.000,'qa_order_seed',u.id
FROM locations l
JOIN cylinder_types ct ON ct.code='C6'
JOIN users u ON u.username='admin'
WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE cylinder_type_id=VALUES(cylinder_type_id),status='filled',
  gas_weight_kg=6.000,source_type='qa_order_seed',created_by=VALUES(created_by);

INSERT INTO inventory_movements(location_id,business_id,inventory_type,cylinder_type_id,quantity,direction,
  source_type,source_id,cylinder_unit_id,created_by,notes)
SELECT cu.location_id,cu.business_id,'filled_cylinder',cu.cylinder_type_id,1,'in','qa_order_seed',cu.id,cu.id,u.id,
  'Disposable customer-order POS fulfillment cylinder'
FROM cylinder_units cu JOIN users u ON u.username='admin'
WHERE cu.unit_code='QA-ORDER-C6-001'
  AND NOT EXISTS (SELECT 1 FROM inventory_movements im
    WHERE im.source_type='qa_order_seed' AND im.source_id=cu.id AND im.inventory_type='filled_cylinder');

INSERT INTO inventory_movements(location_id,business_id,inventory_type,cylinder_type_id,quantity,direction,
  source_type,source_id,cylinder_unit_id,created_by,notes)
SELECT cu.location_id,cu.business_id,'gas_kg',NULL,6.000,'in','qa_order_seed',cu.id,cu.id,u.id,
  'Disposable customer-order POS fulfillment opening gas'
FROM cylinder_units cu JOIN users u ON u.username='admin'
WHERE cu.unit_code='QA-ORDER-C6-001'
  AND NOT EXISTS (SELECT 1 FROM inventory_movements im
    WHERE im.source_type='qa_order_seed' AND im.source_id=cu.id AND im.inventory_type='gas_kg');

INSERT INTO cylinder_units(location_id,business_id,cylinder_type_id,unit_code,status,gas_weight_kg,source_type,created_by)
SELECT l.id,l.business_id,ct.id,'QA-ORDER-FILLED-C6-001','filled',5.500,'qa_order_filled_seed',u.id
FROM locations l JOIN cylinder_types ct ON ct.code='C6' JOIN users u ON u.username='admin'
WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE cylinder_type_id=VALUES(cylinder_type_id),status='filled',
  gas_weight_kg=5.500,source_type='qa_order_filled_seed',created_by=VALUES(created_by);

INSERT INTO inventory_movements(location_id,business_id,inventory_type,cylinder_type_id,quantity,direction,
  source_type,source_id,cylinder_unit_id,created_by,notes)
SELECT cu.location_id,cu.business_id,'filled_cylinder',cu.cylinder_type_id,1,'in','qa_order_filled_seed',cu.id,cu.id,u.id,
  'Disposable filled-cylinder order QA fixture'
FROM cylinder_units cu JOIN users u ON u.username='admin'
WHERE cu.unit_code='QA-ORDER-FILLED-C6-001'
  AND NOT EXISTS (SELECT 1 FROM inventory_movements im
    WHERE im.source_type='qa_order_filled_seed' AND im.source_id=cu.id AND im.inventory_type='filled_cylinder');

INSERT INTO inventory_movements(location_id,business_id,inventory_type,cylinder_type_id,quantity,direction,
  source_type,source_id,cylinder_unit_id,created_by,notes)
SELECT cu.location_id,cu.business_id,'gas_kg',NULL,5.500,'in','qa_order_filled_seed',cu.id,cu.id,u.id,
  'Disposable filled-cylinder order QA gas fixture'
FROM cylinder_units cu JOIN users u ON u.username='admin'
WHERE cu.unit_code='QA-ORDER-FILLED-C6-001'
  AND NOT EXISTS (SELECT 1 FROM inventory_movements im
    WHERE im.source_type='qa_order_filled_seed' AND im.source_id=cu.id AND im.inventory_type='gas_kg');

INSERT INTO cylinder_units(location_id,business_id,cylinder_type_id,unit_code,status,gas_weight_kg,source_type,created_by)
SELECT l.id,l.business_id,ct.id,'QA-ORDER-EMPTY-C6-001','empty',0.000,'qa_order_empty_seed',u.id
FROM locations l JOIN cylinder_types ct ON ct.code='C6' JOIN users u ON u.username='admin'
WHERE l.code='MAIN'
ON DUPLICATE KEY UPDATE cylinder_type_id=VALUES(cylinder_type_id),status='empty',
  gas_weight_kg=0.000,source_type='qa_order_empty_seed',created_by=VALUES(created_by);

INSERT INTO inventory_movements(location_id,business_id,inventory_type,cylinder_type_id,quantity,direction,
  source_type,source_id,cylinder_unit_id,created_by,notes)
SELECT cu.location_id,cu.business_id,'empty_cylinder',cu.cylinder_type_id,1,'in','qa_order_empty_seed',cu.id,cu.id,u.id,
  'Disposable empty-cylinder order QA fixture'
FROM cylinder_units cu JOIN users u ON u.username='admin'
WHERE cu.unit_code='QA-ORDER-EMPTY-C6-001'
  AND NOT EXISTS (SELECT 1 FROM inventory_movements im
    WHERE im.source_type='qa_order_empty_seed' AND im.source_id=cu.id AND im.inventory_type='empty_cylinder');

INSERT INTO cash_sessions(register_id,business_id,opened_by,opening_cash,status,notes)
SELECT cr.id,cr.business_id,u.id,0,'open','Disposable Playwright POS regression session'
FROM cash_registers cr
JOIN locations l ON l.id=cr.location_id
JOIN users u ON u.username='admin'
WHERE l.code='MAIN' AND cr.code='REG-01'
  AND NOT EXISTS (SELECT 1 FROM cash_sessions cs
    WHERE cs.register_id=cr.id AND cs.status='open');
