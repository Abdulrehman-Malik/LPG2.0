# POS Workflow Review — Business Flow, Tables, and Impact

**Repository reviewed:** `Abdulrehman-Malik/LPG`  
**Branch reviewed:** `main`  
**Review type:** Source-code and schema review. No business logic or application code was changed for this review. Runtime transactions and live database reconciliation were not executed.

This document describes the current four POS header transaction types in `app/Services/SalesService.php` and the related schema. The POS controller validates that a transaction type is enabled for the shop, then passes the transaction to `SalesService`, which performs server-side validation and transactional posting.

## 1. Overview: four POS transaction types

| POS type | Main purpose | Sales record type | Inventory impact | Money / balance impact |
|---|---|---|---|---|
| Gas Sale / Refill | Sell gas by KG, amount, or cylinder-count entry | `gas_sale` | Reduces gas from filled physical cylinder(s); a cylinder becomes empty when its gas reaches zero | Normal sale payment, cash counter and customer credit rules |
| Cylinder Sale | Sell a physical filled or empty cylinder | `cylinder_sale` | Filled: removes cylinder and its gas from shop stock. Empty: removes only empty-cylinder stock | Normal sale payment, cash counter and customer credit rules |
| Security Deposit / Issue Cylinder | Place selected company cylinders in a named customer's custody and collect a refundable deposit, optionally with a gas/cylinder charge | `security_deposit` | Selected physical cylinder moves to customer custody; remains tracked by unit identity | Deposit and gas/cylinder payments are classified separately; deposit is not ordinary sales revenue |
| Cylinder Return / Refund Deposit | Take back a cylinder currently held by that customer and refund some or all of the refundable deposit | `cylinder_return` | Custody ends; physical cylinder returns to shop as empty, or filled if permitted return gas is entered | Deposit refund is recorded separately from sales revenue; optional return-gas value may affect customer OS |

## 2. Gas Sale / Refill

### Business flow

1. Cashier selects **Gas Sale / Refill**, a customer if applicable, and one or more gas lines.
2. The line identifies the cylinder type and gas quantity. The UI supports quantity/KG, entered-amount, and cylinder-count modes.
3. In amount mode, the service calculates KG from the entered amount and effective gas/KG rate. In cylinder-count mode, KG is calculated from the cylinder type's capacity and the requested cylinder count.
4. The service validates the effective gas rate, quantity, source selection policy, available stock, and payment plan. If source-cylinder selection is enabled, the selected source must be a valid filled cylinder of the chosen type and cannot be reused on another line. Otherwise gas is allocated from available filled cylinders in stock order.
5. Posting occurs in a database transaction. Gas weight is deducted from physical cylinder unit(s). If a source cylinder reaches zero gas, its status changes from `filled` to `empty`.
6. The service creates the sale header, sale item lines, inventory movement history, payment rows, customer settlement entries (where applicable), and cash movement for cash received.

### Tables and what each one means

| Table | Effect |
|---|---|
| `sales` | One header row with `transaction_type='gas_sale'`, timestamp, customer/location, total KG, subtotal/discount/total, credit and balance-related fields, and posted status |
| `sale_items` | One or more `refill_kg` lines with cylinder type, KG, applied rate, line total, and optional target customer-custody cylinder reference |
| `cylinder_units` | Reduces `gas_weight_kg` on the physical source cylinder. Status remains `filled` if gas remains; becomes `empty` at zero |
| `inventory_movements` | Records gas KG out. If a cylinder becomes empty, also records filled-cylinder out and empty-cylinder in transitions, tied to the sale and physical unit |
| `sale_payments` | Stores normal sale payment allocations (cash, cheque, online and/or credit) as `payment_type='sale'` |
| `cash_transactions` and `cash_sessions` | Cash portion increases the open counter session using the sale-cash movement. A cash session must be open when cash is posted |
| Customer settlement/receipt ledger tables | Credit/receipt allocation is posted by the customer-settlement logic where applicable; affects customer outstanding balance |
| `audit_logs` | Do not assume a POS row is written here solely from this service path; check the relevant audit hooks before relying on it for this transaction |

### Where it impacts

- **Available Gas KG** decreases by the KG sold.
- **Filled cylinder count** can decrease and **empty cylinder count** can increase when a source cylinder is fully emptied.
- **Sales summary / Sales History / receipt** uses the posted sale header and its detail/payment rows.
- **Cash Counter** increases only by the cash portion, not cheque/online/credit.
- **Customer OS** increases by the credit portion and is subject to the configured credit rules.
- **Inventory detail and movement history** should reconcile to the physical cylinder changes.

### Important validation behavior

- A positive effective gas/KG rate is required unless a valid positive line rate is provided.
- Source-cylinder choice is constrained by shop settings; selected stock is revalidated/locked during posting.
- Duplicate cylinder types across gas lines and reusing the same selected source cylinder are rejected by server-side validation.
- Posting uses a database transaction; an error should roll back the posting rather than leave partial sale/inventory/payment rows.

## 3. Cylinder Sale

### Business flow

1. Cashier selects **Cylinder Sale**, chooses cylinder type, status (**filled** or **empty**), quantity, and payment allocation.
2. The service validates quantity as a positive whole number and validates the cylinder price. For a filled cylinder, an effective gas/KG rate is also required.
3. For a filled-cylinder sale, the cashier must select the exact physical cylinder unit(s). The service checks that each unit belongs to the chosen type, is still filled, has gas, and is not selected more than once.
4. For an empty-cylinder sale, the service selects available empty physical units of the selected type; the price is the cylinder price and there is no gas charge.
5. The sale, detail lines, inventory movements, payments, customer settlement and cash movements are posted in a transaction.

### Tables and what each one means

| Table | Effect |
|---|---|
| `sales` | Header row with `transaction_type='cylinder_sale'`; total, KG, payment/credit and customer fields |
| `sale_items` | Filled or empty cylinder detail. Filled lines include actual gas KG and gas/cylinder pricing; empty lines have zero gas and cylinder pricing |
| `cylinder_units` | Sold physical unit(s) are marked `sold`; they no longer count as available filled/empty shop stock |
| `inventory_movements` | Filled sale records gas KG out and filled-cylinder out per physical unit. Empty sale records empty-cylinder out |
| `sale_payments` | Normal sale allocations with `payment_type='sale'` |
| `cash_transactions` / `cash_sessions` | Cash portion is posted to the open counter session |
| Customer settlement/receipt ledger tables | Record the credit/settlement impact for a named customer when applicable |

### Where it impacts

- **Available filled cylinders** decrease for filled-cylinder sales.
- **Available gas KG** decreases by the actual gas weight contained in the sold filled cylinder(s).
- **Available empty cylinders** decrease for empty-cylinder sales.
- Sold cylinders are not converted into empty stock: the physical unit becomes `sold`.
- **Sales summary, Sales History, receipt, cash counter and customer OS** are affected as for a normal sale.

### Important distinction

A **filled cylinder sale** charges for the gas contained in the physical cylinder plus the cylinder price. An **empty cylinder sale** charges only the cylinder price. It must not deduct gas KG or add the sold empty cylinder back into stock.

## 4. Security Deposit / Issue Cylinder

### Business flow

1. Cashier selects **Security Deposit / Issue Cylinder** and must select a named, active customer.
2. Cashier selects the actual physical company cylinder unit(s), grouped by cylinder type and filled/empty status. The same physical unit cannot be issued twice in one transaction or while already in active custody.
3. For filled cylinders, the transaction may include a gas charge and cylinder charge. Empty cylinders have no gas charge. The refundable deposit amount is handled separately from the gas/cylinder charge.
4. The service calculates the total gas/cylinder charge, validates payment allocations, and classifies received deposit payments separately from ordinary sale payments. Combined payments are allocated according to `shop_settings.deposit_payment_allocation_rule` (`deposit_first`, `gas_first`, or `manual`).
5. During transactional posting, the physical unit(s) are moved to customer custody, custody records are created, inventory movements are recorded, and the actual deposit received is written to the security-deposit ledger.
6. Deposit cash and gas/cylinder cash are posted as different cash movement types. Any permitted gas/cylinder credit is handled through the normal customer-credit validation/settlement path.

### Tables and what each one means

| Table | Effect |
|---|---|
| `sales` | Header row with `transaction_type='security_deposit'`; records gas/cylinder charge and the deposit amount fields separately |
| `sale_items` | Detail for each physical cylinder issued, including type, unit reference, issued condition, gas weight and charge |
| `cylinder_units` | Selected unit status becomes `custody` and its custody customer is set; the unit remains individually tracked |
| `cylinder_custody` | Creates the active custody record with customer, physical unit, issuing sale, deposit share, issued gas/condition and rate snapshots |
| `inventory_movements` | Records the physical cylinder moving out of shop stock into customer custody; gas movement is recorded for a filled issued cylinder as implemented by the service |
| `customer_security_deposits` | Adds an `entry_type='hold'` row for the **amount actually received** as deposit. This is the independent deposit balance ledger |
| `sale_payments` | Separates payment rows using `payment_type='security_deposit'` from `payment_type='sale'`. Deposit cannot be received as customer credit |
| `cash_transactions` / `cash_sessions` | Deposit cash uses `security_deposit` (in); gas/cylinder cash uses `sale_cash` (in) |
| Customer settlement/receipt ledger tables | Apply only to the gas/cylinder part and its permitted credit/receipt allocations; deposit is not silently treated as ordinary sale credit |

### Where it impacts

- **Available filled/empty stock** decreases because the unit is no longer in shop stock.
- **Issued Cylinders dashboard and customer custody list** increase/show the selected customer and exact physical cylinder(s).
- **Deposit balance** increases by the amount actually received, not simply by the deposit amount requested or assigned to custody.
- **Cash Counter** increases by the cash actually received, separated by deposit vs sale cash movement type.
- **Sales revenue and customer OS** must not treat the security deposit as ordinary gas/cylinder sales. Shop setting `include_security_deposit_in_os` controls whether the deposit balance is included in the overall OS display/calculation.
- **Return eligibility** depends on the active custody record for that customer and physical unit.

### Important validation behavior

- A named active customer is mandatory.
- Only available physical cylinders in the current location can be issued.
- Duplicate physical-unit selection and duplicate type/status line combinations are rejected.
- Deposit receipts cannot be posted as credit.
- Posting is transactional; custody, stock movement, sale detail and payment/deposit ledger entries must succeed together.

## 5. Cylinder Return / Refund Deposit

### Business flow

1. Cashier selects **Cylinder Return / Refund Deposit** and the named customer.
2. The cashier selects physical cylinder(s) that are currently issued to that same customer. The service verifies that each selected unit has an active custody record.
3. The normal return path expects the issued cylinder to be returned empty. If return-gas quantity is enabled in Shop Settings, gas may be entered subject to configuration:
   - `allow_return_gas_qty`: enables gas quantity on return.
   - `allow_empty_issued_return_gas`: controls whether a cylinder issued empty may be returned with gas.
   - `allow_return_gas_over_issued`: controls whether returned gas may exceed the recorded issued gas.
   - `return_gas_affects_os`: controls whether the value of returned gas adjusts customer gas OS.
4. The cashier enters the security-deposit refund amount and refund payment allocation. The refund cannot exceed the customer's available security-deposit balance; refund payments must be classified as `security_deposit_refund`.
5. During transactional posting, a `cylinder_return` header is created, the unit is returned from custody, custody is marked returned, the physical unit becomes `empty` (or `filled` if permitted gas is recorded), and the refund is written to the deposit ledger and payment history.
6. If the shop setting includes deposit in OS, the remaining deposit balance is included in the overall OS calculation. If return-gas affects OS, the returned gas value adjusts the gas/customer balance according to the service logic.

### Tables and what each one means

| Table | Effect |
|---|---|
| `sales` | Header row with `transaction_type='cylinder_return'`, normally `total_amount=0`, plus `security_deposit_refund_amount`, returned gas KG and optional return-gas ledger amount |
| `sale_items` | One return detail per physical unit, linking back to the returned customer-custody cylinder and recording returned gas quantity/rate |
| `cylinder_custody` | Active record changes to `returned`; stores return sale, returned timestamp, refund amount allocation, returned gas, consumed gas and return rate |
| `cylinder_units` | Clears the custody customer; unit status becomes `empty` with zero gas or `filled` with the permitted returned gas quantity |
| `inventory_movements` | Records physical cylinder intake back to shop stock, plus gas movement where applicable, linked to the return transaction |
| `customer_security_deposits` | Adds an `entry_type='refund'` row for the refund, reducing the refundable deposit balance |
| `sale_payments` | Stores refund allocations with `payment_type='security_deposit_refund'`; kept separate from normal sale payments |
| `cash_transactions` / `cash_sessions` | A cash refund is posted as `security_deposit_refund` with direction `out`; it reduces counter cash |
| Customer balance/OS fields and settlement logic | Return-gas value adjusts OS only when the relevant setting enables it; deposit balance inclusion is separately configurable |

### Where it impacts

- **Available empty cylinders** increase when a cylinder returns empty.
- If returned gas is accepted, **available filled cylinders and gas KG** increase according to the returned physical unit's gas quantity.
- **Issued Cylinders dashboard/customer custody list** decreases because the custody record is no longer active.
- **Deposit balance** decreases by the refund amount; the refund cannot exceed the available refundable balance.
- **Cash Counter** decreases only for the cash-refunded portion; cheque/online refunds remain separately classified.
- **Sales revenue** should not increase from a cylinder return because the return header has zero normal sale total.
- **Customer OS** may be affected by return gas only when configured; security deposit inclusion in OS is separately controlled.

## 6. Cross-table relationship map

The shared transaction header is `sales`. Detail and movement tables are linked to it so users can inspect a transaction and reconcile its impacts.

```text
sales (transaction header)
 ├── sale_items (what was sold / issued / returned)
 ├── sale_payments (sale receipt, deposit receipt, or deposit refund)
 ├── inventory_movements (stock and gas movement audit trail)
 ├── cylinder_custody (issue/return lifecycle for physical cylinders)
 ├── customer_security_deposits (deposit hold/refund ledger)
 └── cash_transactions (cash-in/out; references the sale)
```

Other important master/configuration tables:
- `cylinder_units`: physical cylinder identity, current status and current gas KG.
- `cylinder_types`: cylinder type and capacity.
- `customers`: customer identity and credit eligibility/limit.
- `rate_cards` / gas-rate model: effective gas and cylinder rates.
- `shop_settings`: visible/default POS transaction types, source selection, payment allocation, deposit-in-OS and return-gas behavior.
- `cash_sessions` / `cash_registers`: where cash movement is posted.

## 7. Downstream screens and reports affected

| Screen / report | Gas sale | Cylinder sale | Issue / deposit | Return / refund |
|---|---:|---:|---:|---:|
| POS receipt / Sale History | Yes | Yes | Yes | Yes |
| Dashboard Sales summary | Yes | Yes | Excluded from normal sales total | Excluded from normal sales total |
| Available Gas KG | Decreases | Decreases for filled sale | Decreases for filled custody issue | Increases if gas is accepted on return |
| Filled / empty cylinder stock | Can convert filled to empty | Selected units become sold | Selected units leave shop stock into custody | Returned unit comes back empty or filled |
| Issued cylinder / customer custody | No, unless refilling a customer custody cylinder | No | Increases | Decreases |
| Cash Counter | Cash received increases | Cash received increases | Deposit cash and sale cash increase as separate movements | Cash refund decreases |
| Customer OS | Normal credit/receipt logic | Normal credit/receipt logic | Gas/cylinder part follows credit logic; deposit inclusion is configurable | Return-gas adjustment and deposit-in-OS setting can affect displayed/calculated OS |
| Deposit balance | No | No | Increases by deposit actually received | Decreases by refund |
| Inventory detail / movement history | Yes | Yes | Yes | Yes |

## 8. Business rules to preserve

1. **Keep security deposits independent from normal sales accounting.** Deposit receipt/refund uses its own payment classifications and ledger entries.
2. **Track physical cylinder identity.** Do not treat custody issue/return as a generic quantity adjustment when a physical unit is selected.
3. **Do not double-allocate stock.** A selected cylinder must be available, of the expected type/status, and not reused in the same transaction.
4. **Keep payment and cash meanings distinct.** A sale's total, the deposit amount, the amount actually received, credit amount, and cash movement are different values.
5. **Use transaction boundaries.** Header, details, payments, stock updates, movement rows, custody and deposit-ledger changes should succeed or roll back together.
6. **Keep configuration-driven behavior configuration-driven.** Do not hardcode deposit-in-OS, payment allocation, source selection or return-gas rules.
7. **Do not infer runtime correctness from source inspection alone.** After code changes, run browser/database tests for all four transaction types and reconcile the before/after stock, deposit balance, customer OS, payment rows and cash movement.

## 9. Review scope and caveats

- Reviewed the POS controller/service flow, relevant table definitions, and repository workflow documentation on `main`.
- This is a documentation/review deliverable, not a change to posting logic.
- The review did not execute transactions against a live database and does not certify existing data as reconciled.
- When changing issue/return/deposit behavior, also consult `issue_cylinder.md`, update `SQA.md` / `TESTING.md`, and test the affected dashboard, POS receipt/history, inventory, custody, customer balance and cash-counter views.
