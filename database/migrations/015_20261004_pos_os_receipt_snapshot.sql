-- POS OS / receipt snapshot fields
-- Migration 015: persist the values shown at posting time so historical receipts and Sale History remain exact.
ALTER TABLE sales
  ADD COLUMN previous_os_balance DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER credit_amount,
  ADD COLUMN receipt_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER previous_os_balance,
  ADD COLUMN net_receivable_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER receipt_amount,
  ADD COLUMN os_balance DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER net_receivable_amount;
