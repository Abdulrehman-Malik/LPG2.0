-- Ledger data for checks/parties_check.mjs (run after verify_phase9.sh and verify_hq.sh).
-- Synthetic supplier purchases/payments and a customer with opening balance, receipts and a deposit.
UPDATE suppliers SET opening_balance=1000 WHERE code='QS1';
INSERT INTO purchases(purchase_no,location_id,supplier_id,total_amount,credit_amount,status,transaction_at) VALUES
 ('QA-P-1',1,1,5000,3000,'posted',NOW()-INTERVAL 2 DAY),('QA-P-2',1,1,800,800,'voided',NOW()-INTERVAL 1 DAY),
 ('QA-P-3',3,1,2000,2000,'posted',NOW()),('QA-P-4',1,2,1500,500,'posted',NOW());
INSERT INTO supplier_payments(payment_no,location_id,supplier_id,amount,payment_mode,payment_at,status) VALUES
 ('QA-SP-1',1,1,1200,'cash',NOW()-INTERVAL 1 DAY,'posted'),('QA-SP-2',1,1,300,'online',NOW(),'voided'),('QA-SP-3',1,2,100,'cheque',NOW(),'posted');
INSERT INTO customers(location_id,code,name,opening_balance,credit_limit,allow_credit_sale,is_active) VALUES (1,'QA-BAL-A','Balance A',150.50,0,1,1);
SET @a=(SELECT id FROM customers WHERE code='QA-BAL-A');
INSERT INTO customer_receipts(location_id,customer_id,receipt_no,amount,payment_mode,receipt_at,status) VALUES
 (1,@a,'QA-R-BAL-1',50,'cash',NOW(),'posted'),(1,@a,'QA-R-BAL-2',999,'cash',NOW(),'voided');
INSERT INTO customer_security_deposits(location_id,customer_id,entry_type,amount,transaction_at,notes) VALUES (1,@a,'hold',500,NOW(),'qa');
