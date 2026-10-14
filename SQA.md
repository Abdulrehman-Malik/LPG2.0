# Perfect LPG — SQA Functional Test Plan

## Purpose

This document is the execution checklist for SQA. Execute the application end-to-end in the order below, record the actual observed result for every use case, and update **Status** and **Comments**.

**Important:** Do not mark a case PASS unless it was actually executed and observed in the configured test environment.

### Status values

- **NOT EXECUTED** — not yet tested
- **PASS** — actual result matches expected result
- **FAIL** — actual result differs from expected result
- **BLOCKED** — cannot execute because a prerequisite/environment issue prevents testing
- **N/A** — formally not applicable

### Test environment

- Application: Perfect LPG POS & ERP
- URL: `http://localhost:180/LPG2/LPG/public/`
- Login: `admin / admin123` on a fresh development database
- Database: MySQL 8+
- PHP: 8.2+
- Browser: Chrome/Edge current stable
- Test database: use a disposable SQA database or a reset copy of `perfect_lpg`
- Use test names/codes prefixed with `SQA-` so test transactions are identifiable.

### Evidence rule

For financial/inventory cases, capture enough evidence to reconcile:
1. Before transaction stock/balance.
2. Input values.
3. Document/transaction number.
4. After transaction stock/balance.
5. Relevant receipt/report/audit entry.

---

## A. Installation, Security and Authentication

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| A01 | Fresh database installation | Run `database/schema.sql` on fresh MySQL | Database and all required tables are created without SQL errors | | | NOT EXECUTED |
| A02 | Seed verification | Inspect users, roles, permissions, register, cylinder types | Admin, roles/permissions, REG-01 and six standard cylinder types exist | | | NOT EXECUTED |
| A03 | Environment configuration | Copy `.env.example` to local `.env`; configure DB | Application connects to configured DB; local `.env` is not required in Git | | | NOT EXECUTED |
| A04 | Login success | admin / admin123 | User reaches Dashboard as System Administrator / ADMIN | | | NOT EXECUTED |
| A05 | Invalid login | admin / wrong password | Login rejected; no session created | | | NOT EXECUTED |
| A06 | Authentication protection | Open `/dashboard` while logged out | Redirect to login | | | NOT EXECUTED |
| A07 | Logout | Login then Logout | Session is destroyed and login page is shown | | | NOT EXECUTED |
| A08 | Permission enforcement | Use a restricted role/user and open an unauthorized module | HTTP 403 / access denied; no data mutation | | | NOT EXECUTED |
| A09 | CSRF protection | Submit a POST form without valid CSRF token | Request is rejected | | | NOT EXECUTED |

## B. Dashboard and Master Data

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| B01 | Dashboard fresh state | Open Dashboard on fresh DB | Sales, cylinder stock and gas stock show zero values without errors | | | NOT EXECUTED |
| B02 | Customer create | Create SQA customer with valid fields | Customer is created and appears in directory | | | NOT EXECUTED |
| B03 | Customer duplicate code | Reuse an existing customer code | Save is rejected with user-friendly error | | | NOT EXECUTED |
| B04 | Customer edit | Edit name/phone/credit limit | Correct record is loaded and changes persist | | | NOT EXECUTED |
| B05 | Customer inactive | Mark customer inactive | Customer cannot be selected for a new active transaction | | | NOT EXECUTED |
| B06 | Supplier create/edit | Create then edit SQA supplier | Record saves and edited values persist | | | NOT EXECUTED |
| B07 | Supplier duplicate code | Reuse supplier code | Save rejected without corrupting existing supplier | | | NOT EXECUTED |
| B08 | Cylinder type create/edit | Create 20 kg SQA type; edit capacity/tare | Valid values save; duplicate code/capacity and invalid capacity are rejected | | | NOT EXECUTED |
| B09 | Cylinder capacity validation | Try capacity 0 or negative | Save rejected | | | NOT EXECUTED |
| B10 | LPG gas rate | Create/update gas/kg rate with effective date | Current/effective rate is used by POS and history retains old/new values | | | NOT EXECUTED |
| B11 | Cylinder price rate | Set cylinder price for SQA cylinder | POS resolves correct cylinder price for types 3, 4 and 5 | | | NOT EXECUTED |
| B12 | User/role create/edit | Create SQA user, assign role, edit without password | Role changes persist and blank password does not overwrite existing password | | | NOT EXECUTED |

## D. Shop Settings and Branch Configuration

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| D01 | Open Shop Settings | Open Shop Settings as Admin/Manager | Professional tabbed configuration page loads for the current branch | | | NOT EXECUTED |
| D02 | General branch settings | Change branch name/address/city/phone and save | Location details persist and are used by branch UI/receipts | | | NOT EXECUTED |
| D03 | POS default transaction | Set default to one of the five transaction types; open POS | First POS line uses the configured branch default; no default selector appears on POS | | | NOT EXECUTED |
| D04 | Default payment mode | Set default payment to Cash/Cheque/Online/Credit; open POS and add payment | New payment defaults to configured branch payment mode, subject to walk-in restrictions | | | NOT EXECUTED |
| D05 | Stock validation ON | Enable Stock Validation at Sale; attempt gas overstock sale | Sale is blocked when applicable | | | NOT EXECUTED |
| D06 | Stock validation OFF | Disable Stock Validation; attempt over-stock gas sale | Sale requires explicit override confirmation | | | NOT EXECUTED |
| D07 | Stock override disabled | Disable both stock validation and stock override | Over-stock gas sale is rejected even if user confirms | | | NOT EXECUTED |
| D08 | Receipt configuration | Change receipt title/footer/address display | New values appear on printed POS receipt | | | NOT EXECUTED |
| D09 | Backup configuration | Save backup URL/endpoint and maintenance notes | Values persist; POS does not execute the URL automatically | | | NOT EXECUTED |
| D10 | Access control | Login as cashier and open Shop Settings | Access denied; branch configuration remains protected | | | NOT EXECUTED |
| D11 | Branch isolation | In multi-location test data, change current branch settings | Only the active branch's settings change | | | NOT EXECUTED |

## D. Opening Inventory and Physical Cylinders

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| D01 | Gas opening balance | Gas KG = 100.00 | Inventory gas stock becomes 100.00 kg | | | NOT EXECUTED |
| D02 | Filled-cylinder opening | 2 × 20 kg, actual gas 20.000 kg | 2 physical filled units created; gas stock increases 40.000 kg | | | NOT EXECUTED |
| D03 | Partial filled-cylinder opening | 2 × 20 kg, actual gas 18.000 kg | 2 physical units each contain 18.000 kg; gas stock contribution = 36.000 kg | | | NOT EXECUTED |
| D04 | Over-capacity cylinder | Actual gas 20.001 kg for 20 kg cylinder | Save rejected; no units/movement created | | | NOT EXECUTED |
| D05 | Zero-gas filled cylinder | Actual gas 0 for filled cylinder | Save rejected | | | NOT EXECUTED |
| D06 | Empty-cylinder opening | 3 empty SQA cylinders | 3 physical empty units and empty stock +3 | | | NOT EXECUTED |
| D07 | Opening update | Change actual gas on an existing opening | Active opening units are reconciled; historical posted transactions are not deleted | | | NOT EXECUTED |
| D08 | Opening quantity reduction below active units | Existing active units > new quantity | Save rejected and existing stock remains unchanged | | | NOT EXECUTED |

## E. Counter Cash

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| E01 | Open cash session | Opening cash Rs. 10,000 | REG-01 opens; expected cash is Rs. 10,000 | | | NOT EXECUTED |
| E02 | Prevent second open session | Attempt opening another session on REG-01 | Second session rejected | | | NOT EXECUTED |
| E03 | Cash IN | Rs. 100 manual cash IN | Cash In and Expected increase by Rs. 100 | | | NOT EXECUTED |
| E04 | Cash OUT | Rs. 50 manual cash OUT | Cash Out increases by Rs. 50; Expected decreases by Rs. 50 | | | NOT EXECUTED |
| E05 | Cash history filter | From/to date + REG-01 | Matching transactions shown with date, counter, type, direction, amount, reference and notes | | | NOT EXECUTED |
| E06 | Opening float not double-counted | Open with Rs. 10,000 and inspect summary | Expected cash is Rs. 10,000, not Rs. 20,000 | | | NOT EXECUTED |
| E07 | Cash close | Counted cash = displayed expected | Session closes; difference = 0.00 | | | NOT EXECUTED |
| E08 | Cash close variance | Counted cash different from expected | Session closes and difference = counted - expected | | | NOT EXECUTED |
| E09 | Closed session protection | Try cash movement after close | Transaction rejected | | | NOT EXECUTED |

## F. POS — Five Business Transaction Types

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| F01 | POS load | Open POS with cash session | Five transaction types load; cash session is visible; no JS/PHP errors | | | NOT EXECUTED |
| F02 | Transaction type list | Open Transaction Type dropdown | Exactly five business options are shown in the requested order | | | NOT EXECUTED |
| F03 | Default transaction type | Select a type, tick Save as my default, refresh POS | Selected type is restored for the same logged-in user/browser | | | NOT EXECUTED |
| F04 | Sell Gas Only — partial source consumption | Select 20 kg source type; enter 5.000 kg | Gas stock -5.000 kg; source physical cylinder remains filled with its remaining gas; no customer cylinder is added to stock | | | NOT EXECUTED |
| F05 | Sell Gas Only — full source consumption | Have a filled 20 kg unit at 20.000 kg; sell 20.000 kg | Gas stock -20.000 kg; that physical filled unit becomes empty; empty stock +1 for that same cylinder type | | | NOT EXECUTED |
| F06 | Sell Gas Only — source type tracking | Use multiple filled types; select C20 as source for 10 kg | Movement/line identifies the selected source cylinder type; a different cylinder type is not consumed | | | NOT EXECUTED |
| F07 | Sell Gas by Same-Capacity Replacement | Customer returns empty C20 and receives filled C20 | Actual gas from selected filled unit(s) is charged; filled stock decreases; empty C20 stock increases; named customer required | | | NOT EXECUTED |
| F08 | Same-capacity replacement price | Select C20 with actual gas 18 kg | Charge is 18 × Gas/KG rate only; no cylinder price is added | | | NOT EXECUTED |
| F09 | Sell Filled Cylinder + Gas | Sell one filled C20 without receiving a cylinder | Actual gas × Gas/KG + cylinder price is charged; gas and filled stock both decrease | | | NOT EXECUTED |
| F10 | Filled sale actual-weight pricing | Selected filled unit contains 18 kg in a 20 kg cylinder | Customer is charged 18 kg of gas, not automatically 20 kg, plus cylinder price | | | NOT EXECUTED |
| F11 | Different-capacity replacement | Customer returns empty C11.8 and receives filled C20 | Gas and sold filled C20 decrease; empty C11.8 increases; cylinder price for sold C20 is charged | | | NOT EXECUTED |
| F12 | Different-capacity validation | Select same received/sold type for type 4 | Posting rejected; type 4 must use a different received cylinder type | | | NOT EXECUTED |
| F13 | Empty-cylinder-only sale | Sell one empty C20 | Empty stock -1; gas stock unchanged; no gas quantity is charged | | | NOT EXECUTED |
| F14 | Filled stock visibility | Select a filled cylinder type | Dropdown contains available filled count and total actual gas for that type; no long list of physical unit codes is displayed below the dropdown | | | NOT EXECUTED |
| F15 | Gas stock after-sale indicator | Select/enter gas transaction | Total Gas Stock remains current physical filled-cylinder gas; After This Sale updates immediately from the selected transaction lines | | | NOT EXECUTED |
| F16 | Cash payment | Exact cash payment for any payable POS type | Sale posts and Counter Cash increases by cash amount | | | NOT EXECUTED |
| F17 | Cheque payment | Exact cheque total + reference | Sale posts; Counter Cash does not increase | | | NOT EXECUTED |
| F18 | Online payment | Exact online total + reference | Sale posts; Counter Cash does not increase | | | NOT EXECUTED |
| F19 | Credit payment | Named customer + credit within limit | Sale posts; customer outstanding increases by credit portion | | | NOT EXECUTED |
| F20 | Payment mismatch | Payment total differs from sale total | Posting rejected | | | NOT EXECUTED |
| F21 | Walk-in restrictions | Walk-in with types 2 or 4 | Rejected; a named customer is required for cylinder returns | | | NOT EXECUTED |
| F22 | Walk-in valid modes | Walk-in with type 1, 3 or 5 and cash | Sale allowed | | | NOT EXECUTED |
| F23 | Custom rates | Override Gas/KG and/or cylinder price | Custom-rate flag is recorded and applied values match the transaction | | | NOT EXECUTED |
| F24 | Receipt/ledger | Open posted sale and customer ledger | Sale scenario, items, actual gas, prices, payments and customer balance reconcile | | | NOT EXECUTED |

## G. Inventory and Legacy POS Regression

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| G01 | Physical filled-cylinder purchase | Purchase 2 × 20 kg at 18.000 kg actual each | Two physical filled units created; gas stock +36.000 kg | | | NOT EXECUTED |
| G02 | Empty-cylinder purchase/intake | Receive empty physical cylinder through Inventory/Purchase flow | Empty stock and physical empty units increase | | | NOT EXECUTED |
| G03 | Insufficient filled stock | Attempt type 2/3/4 with more filled cylinders than available | Posting rejected without partial movement | | | NOT EXECUTED |
| G04 | Insufficient empty stock | Attempt type 5 with more empty cylinders than available | Posting rejected without partial movement | | | NOT EXECUTED |
| G05 | Gas stock validation ON | Request gas above current physical gas stock | Posting blocked with clear stock error | | | NOT EXECUTED |
| G06 | Gas stock validation OFF | Request gas above stock and confirm override | Confirmation is required; after confirmation the sale posts and override is traceable | | | NOT EXECUTED |
| G07 | Cylinder-type stock-policy override | Configure a cylinder-type override | POS gas validation uses the applicable type-specific policy | | | NOT EXECUTED |
| G08 | Multi-line POS | Add multiple supported transaction types in one sale | Totals, gas deduction and inventory movements match each line; no duplicate physical-unit consumption | | | NOT EXECUTED |
| G09 | Legacy receipt/payment reconciliation | Post cash/cheque/online/credit combinations | Sale payments and customer ledger reconcile exactly | | | NOT EXECUTED |
| G10 | Void gas-only partial sale | Sell 5 kg from a 20 kg physical unit, then void | Gas and the same physical unit return to their pre-sale state | | | NOT EXECUTED |
| G11 | Void gas-only full consumption | Fully consume a physical filled cylinder, then void | The same physical cylinder is restored to filled with its original gas weight; empty stock reversal is correct | | | NOT EXECUTED |
| G12 | Void replacement | Void type 2 or 4 | Sold filled units are restored; received empty units are reversed; gas and ledgers return to pre-sale state | | | NOT EXECUTED |

## H. Inventory Controls and Wastage

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| H01 | Default stock validation ON | Open Inventory Controls | Default policy shows ON | | | NOT EXECUTED |
| H02 | Cylinder-type validation override | Set SQA cylinder override OFF | POS uses the override for that cylinder type | | | NOT EXECUTED |
| H03 | Percentage wastage policy | Set 10% on 20 kg cylinder | Maximum permitted incident wastage is calculated from current gas | | | NOT EXECUTED |
| H04 | Fixed KG wastage policy | Set 1.000 kg fixed | Wastage above 1.000 kg is rejected | | | NOT EXECUTED |
| H05 | Partial wastage | 18 kg cylinder; record 1 kg wastage | Gas stock -1 kg; cylinder remains filled at 17 kg; wastage log records 1 kg | | | NOT EXECUTED |
| H06 | Make cylinder empty | 1 kg cylinder; record 1 kg wastage | Gas stock -1 kg; filled unit OUT; empty unit IN; cylinder status empty | | | NOT EXECUTED |
| H07 | Wastage greater than current gas | Current 5 kg; record 5.001 kg | Rejected; no stock change | | | NOT EXECUTED |
| H08 | Wastage above configured allowance | Configure 1 kg max; record 1.001 kg | Rejected; no partial movement | | | NOT EXECUTED |
| H09 | Wastage history | Filter by date/type | Correct physical unit, cylinder type, KG, reason, user and timestamp are shown | | | NOT EXECUTED |
| H10 | Atomic wastage failure | Force an invalid posting | No partial inventory movement or cylinder status change remains | | | NOT EXECUTED |
| H11 | Physical inventory adjustment IN | Filled cylinder IN with actual gas | Physical unit created and gas movement recorded | | | NOT EXECUTED |
| H12 | Physical inventory adjustment OUT | Filled cylinder OUT | Physical unit sold/removed and its actual gas deducted | | | NOT EXECUTED |
| H13 | Adjustment over capacity | Filled-cylinder IN above capacity | Rejected | | | NOT EXECUTED |
| H14 | Adjustment insufficient stock | Cylinder OUT above available | Rejected without partial movement | | | NOT EXECUTED |

## I. Purchases and Supplier Ledger

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| I01 | Gas purchase | Buy 20 kg gas at valid rate | Gas stock +20 kg; purchase and payment recorded | | | NOT EXECUTED |
| I02 | Filled-cylinder purchase | Buy 2 × 20 kg at 18 kg actual each | Two physical filled units created; gas stock +36 kg | | | NOT EXECUTED |
| I03 | Purchase over capacity | Actual gas above capacity | Purchase rejected | | | NOT EXECUTED |
| I04 | Empty-cylinder purchase | Buy empty cylinder | Empty physical unit and stock increase | | | NOT EXECUTED |
| I05 | Cash purchase | Exact cash payment | Purchase posts; Counter Cash decreases by cash amount | | | NOT EXECUTED |
| I06 | Credit purchase | Credit payment within supplier limit | Supplier outstanding increases by credit amount | | | NOT EXECUTED |
| I07 | Supplier credit limit | Existing outstanding + new credit > limit | Purchase rejected | | | NOT EXECUTED |
| I08 | Supplier payment | Pay amount <= outstanding | Supplier balance decreases; cash OUT for cash payment | | | NOT EXECUTED |
| I09 | Supplier overpayment | Payment > outstanding | Payment rejected | | | NOT EXECUTED |
| I10 | Supplier ledger | Open supplier ledger after transactions | Opening + credit purchases - posted payments reconciles | | | NOT EXECUTED |

## J. Customer Credit and Receipts

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| J01 | Credit sale within limit | Customer with sufficient available credit | Sale posts; outstanding increases by credit portion | | | NOT EXECUTED |
| J02 | Credit limit exceeded | Credit causing new balance above limit | Sale rejected | | | NOT EXECUTED |
| J03 | Customer receipt | Receipt <= outstanding | Customer balance decreases | | | NOT EXECUTED |
| J04 | Receipt over outstanding | Receipt > outstanding | Receipt rejected | | | NOT EXECUTED |
| J05 | Cash customer receipt | Valid cash receipt with open session | Customer balance decreases and Counter Cash increases | | | NOT EXECUTED |
| J06 | Cheque/online receipt | Valid non-cash receipt | Customer balance decreases; Counter Cash unchanged | | | NOT EXECUTED |
| J07 | Customer ledger | Open ledger after sale and receipt | Opening + credit sales - receipts reconciles | | | NOT EXECUTED |

## K. Expenses

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| K01 | Cash expense | Rs. 100 cash expense | Expense posts and Counter Cash decreases Rs. 100 | | | NOT EXECUTED |
| K02 | Non-cash expense | Cheque/online expense | Expense posts; Counter Cash unchanged | | | NOT EXECUTED |
| K03 | Invalid expense amount | Zero/negative | Rejected | | | NOT EXECUTED |
| K04 | Expense category | Inactive/invalid category | Save rejected | | | NOT EXECUTED |

## L. Voids, Reversals and Integrity

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| L01 | Sale void | Void a posted cash filled-cylinder sale with reason | Sale becomes voided; inventory/cash movements are reversed | | | NOT EXECUTED |
| L02 | Void reason required | Submit blank reason | Void rejected | | | NOT EXECUTED |
| L03 | Double void | Void same sale twice | Second void rejected; no duplicate reversal | | | NOT EXECUTED |
| L04 | Void credit sale | Void posted credit sale | Inventory reverses and customer outstanding returns to prior balance | | | NOT EXECUTED |
| L05 | Void exchange | Void exchange | Filled/empty physical states and gas inventory are restored correctly | | | NOT EXECUTED |
| L06 | Failed transaction atomicity | Cause a validation/cash failure during posting | No sale/purchase, partial inventory movement or partial cash movement remains | | | NOT EXECUTED |

## M. Reports and Audit

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| M01 | Daily sales report | Post known sales; open Reports | Sales count/total/credit match posted sales | | | NOT EXECUTED |
| M02 | Payment-mode report | Post cash/cheque/online/credit | Mode totals reconcile with sale payments | | | NOT EXECUTED |
| M03 | Inventory report | Reconcile known movements | Gas, filled and empty stock match movement ledger and physical units | | | NOT EXECUTED |
| M04 | Wastage report | Record known wastage | Date/type/unit/KG/reason/user filters return correct rows | | | NOT EXECUTED |
| M05 | Customer ledger report | Known credit sale + receipt | Ledger balances reconcile | | | NOT EXECUTED |
| M06 | Supplier ledger report | Known credit purchase + payment | Ledger balances reconcile | | | NOT EXECUTED |
| M07 | Audit log | Create/edit/post/void operational transactions | Audit contains user, location, action, entity and timestamp | | | NOT EXECUTED |
| M08 | Cross-module reconciliation | Compare POS, inventory, cash, customer/supplier ledgers | No unexplained difference exists | | | NOT EXECUTED |

## N. UI, Validation and Regression

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| N01 | POS numeric precision | Enter rates with decimal values | Rate fields accept/display 2 decimal places | | | NOT EXECUTED |
| N02 | Quantity precision | Enter gas/cylinder quantities | Gas quantities support 3 decimals; cylinder quantities remain whole where required | | | NOT EXECUTED |
| N03 | Browser refresh after validation error | Submit invalid transaction then refresh/back | No duplicate posting occurs | | | NOT EXECUTED |
| N04 | Empty line removal | Add/remove POS lines | Totals and hidden JSON remain correct | | | NOT EXECUTED |
| N05 | Multiple payments | Split one transaction across allowed modes | Payment total must equal transaction total and each payment is retained | | | NOT EXECUTED |
| N06 | Responsive POS | Test desktop and narrow/mobile viewport | Controls remain usable; no critical overlap or inaccessible action | | | NOT EXECUTED |
| N07 | JavaScript regression | Load POS, add/remove lines, change transaction type/cylinder/rates/qty | No console syntax/runtime errors; totals, rates, availability and selectors update correctly | | | NOT EXECUTED |
| N08 | Error handling | Trigger common validation failures | User sees readable error and no partial transaction | | | NOT EXECUTED |

## O. End-to-End Business Flow

| ID | Use case | Input / Steps | Expected output / result | Actual result | Comments | Status |
|---|---|---|---|---|---|---|
| O01 | Complete retail day | Open cash → opening stock → purchase → POS sale → receipt → expense → supplier payment → reports → close cash | All modules reconcile from start to end of day | | | NOT EXECUTED |
| O02 | Partial-fill lifecycle | Create 18 kg physical cylinder → sell → purchase/refill scenario → partial wastage → empty conversion | Physical unit status and gas inventory remain consistent at every stage | | | NOT EXECUTED |
| O03 | Credit lifecycle | Opening customer → credit sale → receipt → ledger reconciliation | Customer balance remains mathematically consistent | | | NOT EXECUTED |
| O04 | Supplier lifecycle | Opening supplier → credit purchase → supplier payment → ledger reconciliation | Supplier balance remains mathematically consistent | | | NOT EXECUTED |
| O05 | Reversal lifecycle | Post sale → verify stock/cash → void → verify all reversals | Final balances return to pre-sale state, except audit history | | | NOT EXECUTED |

---

## SQA Sign-off

**Tester:** ____________________  
**Test date:** ____________________  
**Build / commit:** ____________________  
**Browser:** ____________________  
**PHP version:** ____________________  
**MySQL version:** ____________________

### Defect summary

| Severity | Count | Notes |
|---|---:|---|
| Critical |  | |
| High |  | |
| Medium |  | |
| Low |  | |

### Final SQA result

- [ ] PASS — all required cases passed
- [ ] FAIL — one or more required cases failed
- [ ] BLOCKED — environment/prerequisite prevented completion

**SQA Comments:**  
> Record final observations, known limitations, and recommended follow-up here.


---

## Current Build / SQA Baseline — October 2026

### Automated pre-SQA gate

The latest GitHub Actions build has passed:

- **LPG CI #20**
- Commit: `f19b68e730d49ba5b40be9ed98d4c0b9b407f7ae`
- PHP syntax: PASS
- Composer installation: PASS
- MySQL 8 schema bootstrap: PASS
- Current migrations: PASS
- CodeIgniter `php spark`: PASS
- PHPUnit: **5/5 tests, 6 assertions**
- CI PHPUnit coverage is disabled because no coverage driver is installed.

This is a prerequisite quality gate only. It does not replace execution of this SQA matrix in a browser against a disposable test database.

### Current POS model — use this for new SQA cases

The current POS model is **header-level, one transaction type per invoice**:

| Code | Current transaction |
|---|---|
| `gas_sale` | Gas Sale / Refill |
| `cylinder_sale` | Cylinder Sale |
| `security_deposit` | Security Deposit / Issue Cylinder |
| `cylinder_return` | Cylinder Return / Refund Deposit |

Older five-mode test cases already present in this document are retained as historical/regression coverage. **For new testing and defect analysis, use the four current transaction types above.**

### Current gas-sale rules

- Gas quantity is measured in KG.
- **Gas KG is a derived value, not an independent inventory entity.** Available gas is always the sum of `gas_weight_kg` from company-owned physical cylinders whose status is `filled`.
- Selling 1 KG consumes only 1 KG of gas; it does not consume a complete cylinder.
- With **Individual Cylinder Tracking OFF** (default), normal POS shows aggregate available gas for the selected cylinder type rather than asking the cashier to choose a physical source cylinder.
- Backend automatically allocates the requested gas across available filled physical cylinders of that cylinder type.
- The backend continues to maintain physical cylinder records and actual gas weight.
- When a physical cylinder reaches 0 KG, it becomes empty and corresponding filled-out/empty-in inventory movements are recorded.
- Customer custody cylinders remain physical-unit based for security-deposit, return and custody-refill workflows.

### Current inventory rules

- Physical `cylinder_units` are the authoritative current stock records for filled and empty cylinders.
- Filled cylinder records contain cylinder type, physical unit code, status and actual gas weight.
- Available Gas KG is derived from the actual gas weight of currently filled physical cylinders; no purchase, opening, adjustment or sale may create an independent gas stock balance.
- Actual gas weight cannot exceed cylinder capacity.
- Opening inventory supports filled and empty physical cylinders.
- Manual stock +/- movements are handled through the dedicated Stock Adjustment & History screen.
- Inventory Detail Report identifies partially used company cylinders based on remaining gas between 0 and capacity.

### Current customer/payment rules

- Previous customer OS is included in the current receivable.
- Payments are applied to previous OS first, then the current sale.
- Remaining balance carries forward as customer OS.
- Credit validation supports branch modes: none, customer, or shop.
- Security deposit amounts are tracked separately from ordinary revenue/customer OS.

### Current permissions/SQA expectation

The sidebar is permission-aware. A user should only see menu items for permissions they actually have, and direct unauthorized route access must still be denied by the controller/service guard.

### Browser testing environment

For remote browser/E2E testing, do not use a production environment.

Recommended:
`Windows PC + XAMPP + MySQL -> Cloudflare Tunnel -> HTTPS staging URL`

Use:
- separate disposable SQA database;
- dedicated SQA users;
- test customers/suppliers/cylinders prefixed `SQA-`;
- no production credentials;
- no production data.

A localhost URL is suitable for local SQA execution but is not itself a remote staging URL.

### New current-model SQA focus

#### Combined Security Deposit + Gas/Cylinder payment allocation
1. Shop Settings → verify allocation rule options: Gas / Cylinder First, Security Deposit First, Manual Allocation.
2. Security Deposit / Issue Cylinder with a gas charge and deposit → enter one Amount Received; verify the POS shows Total Due, allocations, and Remaining clearly.
3. Gas-first rule → verify payment records are split into Sale and Security Deposit classifications without netting the deposit into the sale.
4. Deposit-first rule → verify deposit is classified first and gas/cylinder payment remains separate.
5. Manual rule → enter separate gas/deposit allocations; verify their sum equals Amount Received and each allocation cannot exceed its due amount.
6. Verify Gas Sale, Cylinder Sale, and Cylinder Return continue using their existing payment workflows.

Before considering the application release-ready, explicitly execute and record:
1. Default POS transaction type from Shop Settings.
2. Gas Sale / Refill using KG quantities.
3. Aggregate gas availability by cylinder type with individual tracking OFF.
4. Automatic allocation across multiple physical filled cylinders.
5. Exact transition from filled to empty at 0 KG.
6. Cylinder Sale quantity against physical cylinder availability.
7. Security Deposit / Issue Cylinder custody and deposit ledger.
8. Cylinder Return / Refund Deposit against the customer's custody cylinder.
9. Customer OS + partial payment allocation.
10. Credit-limit validation.
11. Stock Adjustment +/− and history, including the rule that gas changes must target a physical cylinder rather than a bulk gas bucket.
12. Inventory Detail Report and partially used-cylinder visibility.
13. Permission-aware sidebar and unauthorized route protection.
14. Thermal receipt and browser/JavaScript regression.
15. End-to-end reconciliation of POS, inventory, cash, customer ledger, security deposits and audit logs.


## Environment Configuration Rule — Important

- **Never add Cloudflare/tunnel-specific URL logic to application code.**
- The application must use the normal CodeIgniter `app.baseURL` configuration from the local `.env` file.
- For staging, set `app.baseURL` in the staging machine's local `.env` to the staging HTTPS URL.
- For production, set `app.baseURL` in the production machine's local `.env` to the production URL.
- Do not commit tunnel URLs, environment-specific hostnames, production URLs, or environment secrets to the repository.
- Testing infrastructure may use external URLs, but it must not require application-code changes to accommodate a temporary tunnel.


## Current POS Credit Business Rule Addendum — October 2026

| ID | Use case | Input / Steps | Expected result | Status |
|---|---|---|---|---|
| J08 | Walk-in credit blocked | Select Walk-in / Cash and enter a payment less than the sale total | POS rejects posting; walk-in must be fully paid and received amount must equal sale total | NOT EXECUTED |
| J09 | Walk-in non-cash blocked | Select Walk-in / Cash and choose cheque, online or credit | POS rejects posting; walk-in is cash-only | NOT EXECUTED |
| J10 | Customer credit not configured | Select an actual customer whose Allow Credit Sale is OFF and attempt a partial/credit sale | POS and server reject the sale with a clear credit-not-allowed message | NOT EXECUTED |
| J11 | Customer credit within limit | Enable Allow Credit Sale; configure a positive credit limit; make a sale whose resulting outstanding is within the limit | Sale posts and customer outstanding increases only by the credit amount | NOT EXECUTED |
| J12 | Customer credit limit exceeded | Enable Allow Credit Sale; attempt credit that makes resulting outstanding exceed Credit Limit | POS and server reject the sale; no partial inventory/cash/ledger posting remains | NOT EXECUTED |
| J13 | Zero customer credit limit | Enable Allow Credit Sale but set Credit Limit to zero; attempt unpaid balance | Credit sale is rejected because resulting outstanding cannot exceed zero | NOT EXECUTED |
| J14 | Direct POST bypass attempt | Bypass browser controls and submit a credit sale for a customer with Allow Credit Sale OFF or over limit | SalesService rejects the request; UI-only restrictions are not the security boundary | NOT EXECUTED |

### Mandatory business rule

- Walk-in customer = cash-only and **sale total must equal received amount**.
- Actual customer = credit only when **Allow Credit Sale** is enabled on the customer record.
- Customer credit = resulting outstanding must not exceed the customer's configured **Credit Limit**.
- These rules are enforced server-side and must not be disabled by the branch-level credit-limit validation mode setting.


## Source Filled Cylinder Selection Tests

- **J15** — Shop Settings ON: Source Filled Cylinder dropdown is visible on Gas Sale.
- **J16** — Shop Settings ON: same physical source cylinder on two lines is rejected.
- **J17** — Shop Settings ON: source-cylinder quantity above available KG is rejected.
- **J18** — Shop Settings ON: sale deducts gas only from the selected physical cylinder; when its remaining gas reaches zero it becomes empty.
- **J19** — Shop Settings OFF: source dropdown is hidden and gas is allocated automatically by ascending physical-cylinder sequence.
- **J20** — Shop Settings OFF: allocation consumes one cylinder down to zero before moving to the next cylinder.
- **J21** — Shop Settings OFF: gas quantity above the selected cylinder type's total available KG is rejected.
- **J22** — Same cylinder type on multiple gas-sale lines is rejected in both ON and OFF modes.
- **J23** — Cylinder Type dropdown displays available gas grouped by cylinder type.
- **J24** — Concurrent gas sales cannot oversell the same physical-cylinder quantity because source rows are locked inside the posting transaction.


## Recent Business Rule Regression Matrix — October 2026

| ID | Business rule | Expected result | Status |
|---|---|---|---|
| R01 | Walk-in credit | Credit payment rejected; walk-in is cash-only | NOT EXECUTED |
| R02 | Walk-in payment equality | Received amount must equal sale total | NOT EXECUTED |
| R03 | Customer credit permission | Credit rejected when Allow Credit Sale is OFF | NOT EXECUTED |
| R04 | Customer credit limit | Resulting outstanding cannot exceed Credit Limit | NOT EXECUTED |
| R05 | Zero credit limit | No credit outstanding is permitted | NOT EXECUTED |
| R06 | Server bypass | Invalid credit rejected server-side | NOT EXECUTED |
| R07 | Source selection ON | Source cylinder selector is visible | NOT EXECUTED |
| R08 | Source uniqueness | Same physical source cannot be selected twice | NOT EXECUTED |
| R09 | Source quantity | KG cannot exceed selected source actual stock | NOT EXECUTED |
| R10 | Manual deduction | Only selected source cylinder is consumed | NOT EXECUTED |
| R11 | Automatic allocation | Source selector hidden; allocation follows ascending physical ID | NOT EXECUTED |
| R12 | Sequential consumption | One physical cylinder is consumed before the next | NOT EXECUTED |
| R13 | Type stock limit | KG above total type stock is rejected | NOT EXECUTED |
| R14 | Duplicate type | Same cylinder type cannot be repeated across gas lines | NOT EXECUTED |
| R15 | Concurrent sales | Locks prevent physical-cylinder overselling | NOT EXECUTED |
| R16 | Physical code | Code follows type code + global sequence | NOT EXECUTED |
| R17 | Cylinder capacity | Actual gas cannot exceed capacity | NOT EXECUTED |
| R18 | Filled-to-empty | Unit becomes empty at zero KG | NOT EXECUTED |
| R19 | Gas source of truth | Available gas equals actual KG on filled physical cylinders | NOT EXECUTED |
| R20 | Filled-cylinder pricing | Uses actual gas KG plus cylinder price where applicable | NOT EXECUTED |
| R21 | Replacement customer | Named customer required for cylinder-return/replacement flows | NOT EXECUTED |
| R22 | Different replacement | Received type must differ from sold type | NOT EXECUTED |
| R23 | Empty-cylinder sale | Only empty physical inventory is consumed; gas unchanged | NOT EXECUTED |
| R24 | Security deposit customer | Named customer required | NOT EXECUTED |
| R25 | Custody uniqueness | Already-custody cylinder cannot be issued again | NOT EXECUTED |
| R26 | Custody return | Cylinder must be empty before return/refund | NOT EXECUTED |
| R27 | Custody ownership | Only custody customer can return the cylinder | NOT EXECUTED |
| R28 | Custody refill capacity | Refill cannot exceed cylinder capacity | NOT EXECUTED |
| R29 | Purchase actual gas | Filled purchase retains actual gas KG per line | NOT EXECUTED |
| R30 | Purchase atomicity | Failed purchase leaves no partial posting | NOT EXECUTED |
| R31 | Transaction atomicity | Failed POS posting rolls back inventory/financial changes | NOT EXECUTED |
| R32 | Security deposit UI | Deposit field shown only for Security Deposit transaction | NOT EXECUTED |
| R33 | Compact POS summary | Current Sale, Previous Balance and Discount share one row on desktop | NOT EXECUTED |
| R34 | Screen-level gas entry mode | Gas Sale has one screen-level KG/Rs selector applied to all gas lines; no per-line selector exists | NOT EXECUTED |
| R35 | Rs. mode stock validation | Entered amount is converted to KG and remains subject to source/type stock validation and physical-cylinder locking | NOT EXECUTED |
| R36 | Gas entry server authority | Server recalculates KG from Rs. amount and current effective rate; KG mode remains quantity-based and client cannot bypass stock validation | NOT EXECUTED |



## Security Deposit Payment UI Regression
- Confirm the right payment panel is compact and does not show a redundant Total Due field.
- Confirm only one Amount Received summary is shown.
- Confirm multiple payment tenders can be entered for one Security Deposit transaction.
- Confirm each payment has a single Reference / Note input.
- Confirm Gas First, Deposit First, and Manual allocation rules still produce separate gas/cylinder and deposit accounting records.
- Confirm credit-sale eligibility and credit-limit rules remain enforced server-side for the gas/cylinder portion.
- Confirm successful posting opens the receipt as a preview and does not auto-print.

### Negative Customer OS / Advance Credit Regression
- Customer has OS Rs. 1,000; post receipt Rs. 1,500. Receipts, POS, customer ledger, and customer balance endpoint show -Rs. 500.
- With OS -Rs. 500 and a sale of Rs. 200 with no collection, resulting OS remains -Rs. 300; net receivable is Rs. 0 and POS does not reject the transaction because expected collection is negative.
- With OS -Rs. 100 and a sale of Rs. 200, the net receivable is Rs. 100. Collecting Rs. 100 results in OS Rs. 0; collecting Rs. 50 results in OS Rs. 50.
- With a negative OS, credit-limit availability is calculated from positive amount due only; a customer advance does not incorrectly appear as an exceeded credit limit.
- Customer ledger balance includes `return_gas_ledger_amount`, matching the POS/customer balance calculation.
- Shop-level receivables and credit-limit totals include return-gas ledger adjustments but do not subtract negative customer credits from total receivables.
- **Status:** NOT EXECUTED (requires application/database runtime).

## Security Deposit zero-receipt / compact payment regression
- With Security Deposit / Issue Cylinder selected, a blank or zero payment amount is permitted when the gas/cylinder charge is being posted to customer credit.
- Credit eligibility and credit-limit validation remain the same as Gas Sale: projected customer OS must remain within the applicable limit.
- A positive Security Deposit Amount still requires an actual non-credit deposit payment; deposit liability is never converted into gas-sale revenue.
- The payment collection row remains compact, the delete button is reduced in size, and Reference / Note remains after Add Payment.
