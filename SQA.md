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
- Negative Security Deposit Amount must be rejected by the server (not silently changed to zero); verify no sale, payment, deposit-ledger, custody, cash, or inventory records are created.
- The payment collection row remains compact, the delete button is reduced in size, and Reference / Note remains after Add Payment.

## Customer-order duplicate fulfillment / idempotency regression

**Execution status: PARTIALLY EXECUTED — selected integration scenarios passed in disposable MySQL 8 CI on 2026-10-15. Full DPF04 fault injection and detailed payment/cash/customer-ledger cardinality checks remain open.**

Evidence: [LPG CI run #356](https://github.com/Abdulrehman-Malik/LPG2.0/actions/runs/38724252049), commit `71781c722fef8d26941abd707831efeecb490495`. Playwright passed 26/26 tests. E2E-028 covers a valid POS fulfillment, payment-mismatch rejection, and replay returning the same completed sale. E2E-029 covers mixed gas/filled/empty lines, physical-cylinder overlap rejection and customer price reconfirmation. E2E-030 sends two concurrent fulfillment requests, verifies both return the same sale ID, and checks that the selected source cylinder gas is reduced once. CI SQL assertions reconcile order/sale links and item quantity, gas-weight and line-total snapshots.

### DPF01 — Sequential retry after a successful fulfillment
1. Create and accept a customer order with known lines, payment, and available physical cylinders.
2. Fulfill it once through the staff order API/POS and record the returned sale ID/number.
3. Submit the exact same fulfillment request again, including the same order ID and payload.
4. Verify the retry returns the existing completed order/sale or a safe “already fulfilled” response; it must not post a second sale.

**Expected:** exactly one `sales` row for the `customer_order_id`; the order is `completed` and its `pos_sale_id` matches that sale. There is only one set of sale items, sale payments/settlements, cash movements, and inventory movements for the order. Cylinder statuses and gas weight change only once.

### DPF02 — Two concurrent fulfillment requests for the same order
1. Prepare a fresh accepted order and record the baseline row counts and balances for `sales`, `sale_items`, `sale_payments` (or the payment table used by the schema), `inventory_movements`, cash movements, customer balance, and selected cylinder units.
2. From two independent authenticated requests/connections, submit fulfillment for the same order as nearly simultaneously as possible.
3. Wait for both responses, then query the database and reconcile the order, POS sale, payment, cash, customer ledger, and inventory records.

**Expected:** at most one request creates the POS sale; the other safely returns the already-posted result or a controlled retry/error. There is exactly one sale linked to the order and no duplicate financial or inventory effects. No selected cylinder is consumed twice, and the final order points to the one posted sale.

### DPF03 — Idempotency after a response interruption
1. Fulfill an order successfully, but simulate a client/network interruption after the server commits and before the client receives the response.
2. Retry the same fulfillment request.
3. Verify the retry discovers the committed sale and does not post any new records.

**Expected:** the existing sale is returned/recovered; no second sale or duplicate side effects are created.

### DPF04 — Inconsistent link / duplicate protection
1. In a disposable test database only, test a completed order whose `pos_sale_id` conflicts with the sale linked by `sales.customer_order_id`, and separately test an order link pointing to a missing sale.
2. Retry fulfillment and inspect the response and all records.

**Expected:** reject with a controlled manual-review/integrity error; do not create another sale, change inventory, or post payments/cash to “repair” the mismatch automatically.

### Implementation review notes
- `SalesService::postForCustomerOrder()` checks for an existing sale and validates the order/shop/customer association.
- The posting transaction locks the `customer_orders` row with `SELECT ... FOR UPDATE`; if a competing request has already completed the order and linked a sale, the second request follows the idempotent replay path.
- `database/schema.sql` defines a unique key on `sales.customer_order_id`, preventing more than one non-NULL sale link per order at the database level.
- The CI evidence above validates selected sequential retry, concurrent retry and sale-line reconciliation paths; it is not exhaustive financial audit evidence.
- **Still open:** DPF04 corruption/fault-injection for conflicting or missing links; explicit before/after row-count checks for sale payments and cash transactions; full customer-ledger reconciliation; and a true network interruption between server commit and response delivery.


## Financial posting, inventory concurrency, credit-limit and reversal regression

**Execution status: NOT EXECUTED — requires a disposable MySQL 8+ database and two independent application requests/connections where concurrency is specified.** Code inspection below is not a substitute for runtime evidence.

### FIP01 — Atomic rollback after a late posting failure
1. Record baseline counts and values for the sale, sale items, payments, inventory movements, cash transactions, customer ledger/balance, cylinder unit(s), and (for an order) order status/link.
2. Prepare a valid transaction that reaches posting but fails late, for example a cash sale with no open counter session or an intentionally induced failure on a dependent insert in a disposable test database.
3. Verify the operation returns an error and compare every baseline value.

**Expected:** no partial sale/payment/ledger/cash/inventory records remain; cylinder status and gas weight are unchanged; customer balance is unchanged; customer order remains fulfillable and unlinked if fulfillment did not commit. All posting side effects must commit together or roll back together.

### INV01 — Competing requests for the same physical cylinder
1. Prepare one filled cylinder and submit two near-simultaneous transactions that select the same unit (e.g. two gas-only sales, or a sale competing with an issue-to-custody request).
2. Repeat for one empty cylinder selected by two empty-cylinder sale / custody operations.
3. Reconcile the physical unit row and its inventory movements after both requests finish.

**Expected:** the same physical unit cannot be consumed, sold, or issued twice. One operation may succeed and the other must fail safely/retry; final cylinder status and gas weight must match the single successful operation, with no duplicate or orphan movements.

### CRL01 — Concurrent credit sales at the customer credit limit
1. Configure an active customer with credit enabled and a known limit. Set current outstanding balance so either one proposed credit sale can fit but both together cannot.
2. Submit two credit sales concurrently using separate requests/connections.
3. Reconcile the customer ledger, posted sales, payment rows, and outstanding balance.

**Expected:** the customer row lock serializes credit validation and posting. At most the permitted amount is posted; the final outstanding balance must not exceed the configured limit. A rejected request must leave no partial sale, payment, cash, or inventory effects. Repeat for the configured shop-level credit-limit mode.

### PRICE01 — Order price / physical gas weight changes before fulfillment
1. Create and accept an order against selected physical cylinders and record the quoted price revision, actual gas weights, rates, and delivery fee.
2. In a disposable test database, change the selected unit's gas weight or the effective price after the quote but before fulfillment.
3. Attempt fulfillment without customer price reconfirmation, then perform the supported reconfirmation flow and retry.

**Expected:** stale quote fulfillment is blocked; no sale/payment/cash/inventory effects are posted while confirmation is required. The customer is shown the revised quote and must reconfirm it before fulfillment can commit. The final sale total and inventory consumption must agree with the confirmed order.

### REV01 — Void/reversal restores the original financial and physical inventory state
Run separate cases for: partial gas-only sale; gas-only sale that empties a cylinder; filled-cylinder sale; empty-cylinder sale; same-capacity exchange; and different-capacity exchange. For each case:
1. Capture the pre-sale cylinder units, gas weights, customer balance, cash session balance, and inventory movement totals.
2. Post the sale and then void it with a reason.
3. Reconcile original sale movements against sale_void movements, physical units, customer ledger, sale status, and cash transactions.

**Expected:** the sale is voided once; every inventory movement has exactly one effective reversal; the original physical unit(s) and gas weights are restored without double-restoration or capacity violations; generated empty units are handled correctly; sale cash is reversed; customer outstanding balance returns to the expected pre-sale amount. Repeated void attempts must be rejected without creating additional reversals.

### REV02 — Void after later use of the same cylinder
1. Post a partial gas-only sale against a filled unit.
2. Post a second valid gas-only sale against that same unit, including a case that reduces it to empty.
3. Void the first sale and compare the resulting unit state and movement ledger with the mathematically expected stock after reversing only the first transaction.

**Expected:** reversal must not overwrite later valid activity or create gas beyond cylinder capacity. If the original unit state can no longer be safely reconstructed, void must fail atomically and require controlled manual review rather than corrupting stock.

### Static review observations (not runtime proof)
- Standard POS posting begins a database transaction and catches exceptions to roll back dependent writes; cash-session availability is checked before the posting can commit.
- Standard POS and the gas/cylinder header posting paths use customer row locks in their credit-sensitive posting paths; inventory paths acquire MySQL advisory locks keyed by shop and inventory type, then lock/revalidate physical units.
- Sales have a unique (location_id, sale_no) key. Concurrent requests can still contend while calculating the next sequence; verify the losing request rolls back cleanly and receives a controlled retry/error rather than partial effects.
- SalesService::void() writes reversal movements and restores physical cylinder state within a transaction. REV01/REV02 are required to validate real-world sequences, especially when a unit has had subsequent valid activity.
- FIP01, INV01, CRL01, PRICE01, REV01 and REV02 remain **NOT EXECUTED** until test evidence is recorded. Do not report financial/inventory safety as passed based on source review alone.


### CRL02 — Credit-limit mode is honored by the standard five-mode POS
1. Run a standard five-mode POS credit sale with credit-limit mode set to `none`, first with customer credit limit 0 and then with a positive customer limit.
2. Repeat with mode `customer` and a customer whose existing balance plus proposed credit exceeds the configured customer limit.
3. Repeat with mode `shop` and a shop outstanding balance near the shop limit; test both one request and two concurrent requests for different customers.
4. Check the posted sale, payment, customer ledger, and shop/customer outstanding totals after every allowed and rejected request.

**Expected:** `none` with customer limit 0 does not accidentally reject an otherwise eligible credit sale; a positive customer limit remains enforced in `none` mode; `customer` mode enforces a positive customer limit; `shop` mode enforces the configured shop limit and serializes concurrent requests. Every rejected request leaves no partial posting.

### Confirmed source-review finding and fix
- The standard five-mode POS posting path previously enforced the customer's numeric credit limit unconditionally. This contradicted the existing configuration semantics: a zero customer limit is treated as unlimited, and the shop-level mode should validate total shop exposure instead.
- Updated `SalesService::post()` to honor `credit_limit_validation_mode`, preserve the positive customer-limit behavior in `none` mode, and use the shop-scoped advisory lock plus shop outstanding calculation in `shop` mode.
- This is a source-level fix only. CRL01/CRL02 remain **NOT EXECUTED** until the customer and shop-limit scenarios (including concurrent requests) pass against a disposable database.

## Web and mobile-backend release cases

These cases were added for the current web/backend implementation. **Every case below is NOT EXECUTED until a result and evidence are recorded.** Passing CI does not prove the running application works.

### BIZWEB01 — Business/shop provisioning and staff scope

1. As System Administrator, create a unique second business and verify an authorized user can see it.
2. Create two shops under the second business; verify each receives a default shop_settings row, main cash register and base inventory policy.
3. Verify a Business/Head Office user can access the business shops, but is not silently granted global System Administrator/ADMIN permissions.
4. Assign one staff member to one shop and another staff member to both shops; verify defaults are consistent, access changes revoke old staff API sessions, and the next login exposes only permitted shops.
5. Try to access a different business/shop by editing URLs, form IDs and API path IDs. Deny out-of-scope read and write attempts.
6. Set Head Office as the default assignment and verify the default shop belongs to that business; remove access and verify no stale users.location_id grants access.

**Expected:** provisioning is atomic; no duplicate shop defaults; access follows role capability AND business/shop membership; scope violations are denied.

Status: **NOT EXECUTED**.

### CATWEB01 — Business catalog and per-shop product availability

1. Create one product per supported sale mode (sell_gas_only, sell_filled, sell_empty) for an active cylinder type.
2. Verify gas quantity rules permit only valid positive KG minimum/step values, cylinder products require whole-number quantity rules, and duplicate business/type/mode products are rejected safely.
3. Edit and deactivate a product; verify it is not orderable at any shop when the business catalog item is inactive.
4. Activate the product at Shop A, keep it inactive at Shop B, then verify customer catalog responses reflect the separate shop availability.
5. Try to activate a product belonging to another business or write Shop B availability while scoped only to Shop A.
6. Verify audit records contain the business/shop actor context for create/update/availability changes.

**Expected:** one shared business catalog with separate explicit per-shop availability; no cross-business product reference or permission bypass.

Status: **NOT EXECUTED**.

### ACCWEB01 — Customer App signup approval and account lifecycle

1. Submit self-registration for Shop A and verify no login/order access exists while signup is pending.
2. Approve the request by linking an existing active financial customer in Shop A; verify exactly one account-to-customer link and no duplicate customer master.
3. Approve another request by creating a new financial customer master; confirm the new master starts with credit sales disabled, zero credit limit, and no opening balance.
4. Attempt to link a customer from Shop B, a customer that already has an app account, an occupied username, and a duplicate pending username; each must be rejected safely.
5. Reject a pending request with a reason; verify the password hash is cleared and the pending username becomes available.
6. Create a Customer App account for an existing unlinked party; verify the minimum password policy, username normalization and uniqueness.
7. Reset the password for an approved account; verify the old refresh/access sessions are revoked and cannot refresh or call protected routes. A new login with the new password should work.
8. Disable an account, then verify its existing access and refresh tokens stop working. Re-enable only while the financial customer is active, then verify old sessions remain invalid and only a fresh login works. Verify unauthorized roles cannot approve, reject, reset, enable or disable accounts.

**Expected:** one mobile account per financial customer; every customer is owned by exactly one shop; pending/rejected/disabled accounts cannot access private profiles or orders; no credit permission is silently enabled.

Status: **NOT EXECUTED**.

### OWEB01 — Customer order list and status workflow in browser

1. Open /customer-orders as an authorized order viewer; verify status filters and empty/populated views.
2. Open an order from Shop A, verify customer ownership and the entire status history.
3. Try every permitted status transition, including mandatory reason checks for rejection/cancellation/unable-to-fulfil; test invalid/backward transitions.
4. Verify staff cannot mark an order completed directly; completion must come from POS fulfillment.
5. Open the same order under Shop B and as a role without ORDER_VIEW/ORDER_MANAGE; access must be denied.

**Expected:** the same server-side order state machine is used by the browser and staff API; the route or form cannot bypass a permission or shop-scope check.

Status: **NOT EXECUTED**.

### OWEB02 — Browser fulfillment posts once through validated POS

1. Use an accepted order with one gas-only line and one cylinder line. Ensure the shop has sufficient real cylinder units and an open cash session.
2. Select physical units and submit a payment split whose total equals the current server-calculated order total (including delivery fee if applicable).
3. Verify the linked POS sale, separate delivery-fee component, payments, cash session, customer balance, physical-cylinder states/weights and immutable inventory movement history.
4. Change a cylinder's gas weight or the effective price before fulfillment. Verify the new quote forces customer reconfirmation, resets acceptance, and does not create a sale.
5. Submit the same fulfillment request twice or concurrently. Verify one POS sale maximum and a safe idempotent replay/recovery.
6. Try insufficient stock, a closed cash session, invalid payment mode, payment total mismatch, wrong cylinder type/unit, and a request selecting a unit from another shop.

**Expected:** failure leaves no partial financial/inventory posting; one order can create at most one sale; only the validated POS posting path can complete an order.

Status: **NOT EXECUTED**.

### APISEC01 — Customer/staff API authentication and isolation

1. Test malformed, missing, expired and wrong-audience access tokens for each customer/staff route.
2. Rotate refresh tokens, replay an already-used refresh token, logout, change password, run staff-mediated customer password reset, disable and re-enable an account and remove shop access.
3. Verify customer tokens can only read their own linked customer, products and orders; staff can only access authorized business/shop scopes.
4. Run two customers and two staff members concurrently across two businesses/two shops. Verify order idempotency, state history and sale linkage; test cross-shop order IDs and physical-cylinder IDs.
5. Verify public error responses don't reveal password hashes, refresh/access token values, session IDs, SQL errors or internal tracebacks.

**Expected:** audience separation, scope enforcement and token revocation are proven by HTTP responses/database evidence. Static source review alone is not a PASS.

Status: **NOT EXECUTED**.

## Page-by-page runtime execution register

Follow `PAGE_TEST_WORKFLOW.md` for the full route/page inventory and the required checks per page. The workflow must be executed in a running browser against the current commit and disposable SQA database; static inspection and CI are not substitutes.

**Current status: NOT EXECUTED as a complete page-by-page runtime suite.**

| ID | Scope | Required execution | Actual result | Evidence / issue | Status |
|---|---|---|---|---|---|
| PQA01 | Login, installation, session and access control | Test successful/failed login, logout, direct URL protection and restricted-role denial | | | NOT EXECUTED |
| PQA02 | Dashboard and configuration | Verify displayed totals, filters, shop context, save/reload and role visibility | | | NOT EXECUTED |
| PQA03 | POS and sale history/details/receipt | Exercise all supported transaction types, validation, payment, duplicate submit and linked history | | | NOT EXECUTED |
| PQA04 | Cash and payment pages | Open/close, IN/OUT, receipts, supplier payments, cash totals and ledger links | | | NOT EXECUTED |
| PQA05 | Customers, suppliers and ledgers | CRUD/validation, balances, receipts/payments, credit/deposit and history | | | NOT EXECUTED |
| PQA06 | Inventory, physical cylinders, adjustments and wastage | Validate stock/gas KG, unit state, permissions, history, limits and movements | | | NOT EXECUTED |
| PQA07 | Purchases, expenses and reversals | Posting, validation, void/reversal, cash and stock/payable reconciliation | | | NOT EXECUTED |
| PQA08 | Rates, cylinder types, users and permissions | CRUD, effective rates, capacity validation and unauthorized access attempts | | | NOT EXECUTED |
| PQA09 | Reports, audit and backup/admin pages | Filter accuracy, source reconciliation, audit trail and admin-only operations | | | NOT EXECUTED |
| PQA10 | Customer/staff mobile API and multi-shop scope | Auth/token lifecycle, order flows, price reconfirmation, duplicate protection and cross-shop denial | | | NOT EXECUTED |
| PQA11 | Responsive/browser quality | Desktop/mobile viewport, navigation, modals, tables, browser console, network and server logs | | | NOT EXECUTED |
| PQA12 | Full end-to-end regression | Execute complete retail-day scenario and reconcile inventory, ledgers, cash, reports and audit | | | NOT EXECUTED |
| PQA13 | Businesses, shops and staff scope web pages | Create/provision businesses/shops, review shop defaults, staff assignments/default shop and authorization denials | | | NOT EXECUTED |
| PQA14 | Customer App account administration | Review pending signup, approve/reject, link or create one customer master, reset password, disable/re-enable login and revoke sessions | | | NOT EXECUTED |
| PQA15 | Customer Ordering Catalog | Create/edit products, validate quantity rules, activate shop-specific availability and verify catalog API output | | | NOT EXECUTED |
| PQA16 | Browser customer order management | Queue/detail/history, valid status transitions, physical-cylinder selection and fulfillment through POS with reconciliation | | | NOT EXECUTED |

For each PQA row, link the detailed individual page/case IDs and evidence in the result comments. Split a row into multiple entries when individual pages have different outcomes. A page must not be marked PASS merely because its controller or route exists.


## Automated web/API runtime evidence — 2026-10-15

**Result:** PASS for the selected automated scenarios below.  
**Commit tested:** `8f632643a691f061a3a79db73c1c1703c2d23cf7`  
**Evidence:** [GitHub Actions runtime QA run](https://github.com/Abdulrehman-Malik/LPG2.0/actions/runs/38673310726)  
**Environment:** Disposable MySQL 8 CI database, PHP 8.2, CodeIgniter development server and Playwright Chromium. CI creates its own QA catalog/rate and second-business fixtures; no production database is used.

| Case | Coverage | Actual result | Status |
| E2E-011 | Smoke-load 24 existing web routes from dashboard through inventory opening | Every listed route returned HTTP 200 with no detected PHP/browser errors | PASS |
|---|---|---|---|
| E2E-020 | Business/shop administration page renders for System Administrator | Page loaded in browser | PASS |
| E2E-021 | Customer ordering catalog page and separate business/shop controls | Page loaded in browser | PASS |
| E2E-022 | Customer App account review page | Page loaded in browser | PASS |
| E2E-023 | Customer order queue authentication and permissions | Page loaded in browser | PASS |
| E2E-024 | API health, public registration-shop discovery, missing-token guards and invalid staff credentials | Expected HTTP/status behavior observed | PASS |
| E2E-025 | Staff API login, access-token use, refresh rotation, refresh-token replay revocation, logout and revoked-token denial | Session lifecycle checks passed | PASS |
| E2E-026 | Customer signup and staff approval, customer login/profile/catalog, application/business/shop configuration reads, catalog API reads, second-shop provisioning, order create, idempotent retry, reused-key conflict, staff queue/acceptance and customer-safe order details | End-to-end API flow passed; order remained separate from POS sale | PASS |
| E2E-027 | Scoped staff cannot override shop scope for a sibling shop or another business; non-system administrator cannot create a business | Cross-scope requests denied as expected | PASS |

**Fix found by these tests:** Access-token creation time is now written explicitly in the application timezone so the token's creation and expiry boundaries are evaluated consistently. Logout now returns a JSON `200` response after revoking the session, which is reliable for mobile API clients.

This is evidence for the listed CI scenarios only. It does **not** mark the complete PQA01–PQA12 route-by-route suite or concurrency/reversal cases as passed. Those still require their own execution evidence, and WP-10 still needs full two-shop pilot/owner acceptance before release.


## Mobile-ordering security regression evidence — 2026-10-15

**Result:** PASS for the selected automated runtime suite.  
**Commit tested:** `e80e12f9700848e7f5df746f387ef5b64466951f`  
**GitHub Actions:** [Run 38699695263](https://github.com/Abdulrehman-Malik/LPG2.0/actions/runs/38699695263)  
**Environment:** Disposable MySQL 8 CI database, PHP 8.2, CodeIgniter server and Playwright Chromium. No production database was used.

The run passed PHP syntax validation, schema bootstrap, application migration gate, PHPUnit, and all **23 Playwright tests**.

Additional regression coverage verified:

| Case | Coverage | Actual result | Status |
|---|---|---|---|
| E2E-007 | Zero-quantity gas sale is rejected after valid source-cylinder setup | Validation message shown; no sale posted | PASS |
| E2E-026 | Customer signup/approval, shop-scoped catalog and order creation, idempotent retry and reused-key conflict, disabled shop ordering, unavailable catalog product, malformed idempotency key, and acceptance without POS posting | Expected API responses observed, including 403 for disabled ordering, 409 for stale catalog product, and 422 for malformed idempotency key | PASS |
| E2E-026 ownership checks | A second approved customer in the same shop attempts to read and cancel another customer’s order | Both requests returned 404; order ownership was enforced server-side | PASS |
| E2E-027 | Staff attempts to override shop scope for a sibling shop or a different business | Cross-scope requests denied as expected | PASS |

The order lifecycle checks also confirmed that creating or accepting a customer order does not itself link a POS sale. The existing validated POS fulfilment path remains the posting boundary.

This evidence covers the listed automated cases only. It does **not** mark the complete page-by-page PQA01–PQA16 suite, concurrency/reversal tests, Flutter device QA, or the two-shop owner pilot as complete.


## Phase 8 stabilization & security regression — 2026-10-15

**Branch:** `feature/backoffice-completion` (uncommitted working tree on top of `main` `4b0dc5e`).
**Environment:** local Windows/XAMPP, PHP 8.2.12, **MariaDB 10.4.32** (not the MySQL 8 production target), CodeIgniter dev server, disposable database `lpg_qa_phase8` built from `database/schema.sql` + `php spark migrations:run` + the CI Playwright seed fixtures. The schema's `CAST(... AS JSON)` literals were stripped in a scratch copy only, because MariaDB does not support them. No production or business database was used.
**Method:** each case was executed through the real HTTP endpoints (or, for P8-09, `SalesService` called from the CLI with no browser session), then verified by querying the resulting rows. Every case was also run against the original code: each defect reproduced before the fix and passed after it.

| ID | Plan item | Scenario | Before fix | After fix | Status |
|---|---|---|---|---|---|
| P8-01 | 8.3.3 | Cash gas sale → close cash session → open new session → void sale | Reversal posted into the **closed** session (its balance changed 100.00 → 0.00) | Closed session unchanged; reversal posted to the open session; cylinder restored to 6 kg | PASS |
| P8-02 | 8.3.2 / REV02 | Sale B (1 kg) and later sale C (1 kg) from the same cylinder → void B | B voided; stock reconstructed incorrectly (gas re-created after voiding C) | Void of B refused with a clear message; no stock or movement change; void of latest sale C succeeds and restores 5 kg | PASS |
| P8-03 | 8.2 | System Administrator reduces CASHIER to DASHBOARD_VIEW, then later requests run | Permissions re-granted (count back to 5) | Count stays 1 | PASS |
| P8-04 | 8.3.4 | Security deposit issue (empty C6, deposit 500, combined cash payment) | Posted | Posted | PASS |
| P8-05 | 8.3.5 | Cylinder return with refund 9,999 vs balance 500, then refund 500 | Rejected / posted | Rejected / posted; cylinder back in empty stock; deposit balance 0 | PASS |
| P8-06 | 8.1.3 | Anonymous POST `migrations/run` | **Runner executed** (history rows 39 → 72) | Refused; migration history unchanged | PASS |
| P8-07 | 8.1.3 | `migrations/run` with wrong / correct System Administrator password | Accepted (no check) | Wrong refused; correct accepted | PASS |
| P8-08 | 8.1.4 | Manager signed in → account disabled → next request | Redirected to login without a reason | Redirected to login with "This account has been disabled" | PASS |
| P8-09 | 8.3.1 | Credit gas sale for a customer (limit 50, customer mode) posted with **no browser session** (API fulfilment path), then a second sale exceeding the limit | First sale failed ("Customer not found in current shop") | First sale posted; second rejected with "Customer credit limit exceeded" using the real balance | PASS |
| P8-10 | 8.1.5 | 5 failed logins for `admin`, then the correct password; another login name | Not throttled | 6th attempt blocked for 15 minutes; other login names unaffected | PASS |
| P8-11 | 8.1.7 | Fresh schema + `php spark migrations:run` | Migration 039 failed (`;` inside a comment) | All migrations up to date | PASS |

**Regression:** Playwright `lpg-runtime.spec.mjs` (the CI selection E2E-001…E2E-030, 26 tests) — **26 passed** on the final code against `lpg_qa_phase8` (run with `--timeout=120000` because CDN assets load slowly on this machine; pages render server-side in ~0.3 s). PHPUnit — **15 tests, 36 assertions, OK** (`sqlite3` extension enabled for the test run).
**Test fix:** E2E-004 now captures the `/sales/save` JSON through a route handler. Previously Chromium could discard the response body when the POS page redirected after a successful save, which made the test fail although the sale posted correctly.

**Not covered here (remain NOT EXECUTED):** concurrent double-refund / concurrent deposit-credit requests (8.3.4/8.3.5 are concurrency fixes; sequential tests only show no regression), MySQL 8 execution of these cases (CI), browser-level page-by-page QA (PQA01–PQA16), and a committed automated business-logic suite (plan item 8.3.6).


## Phase 9.5.1–9.5.3 — business settings, back-dating and business-owned master data — 2026-10-15

**Environment:** same as the Phase 8 section (local XAMPP, PHP 8.2.12, MariaDB 10.4, disposable `lpg_qa_phase8`; upgrade test on a separate disposable `lpg_qa_upgrade`).
**Method:** real HTTP requests to the running app, then the resulting rows were checked in the database.

| ID | Item | Scenario | Actual result | Status |
|---|---|---|---|---|
| P9-01 | 9.5.1 | Save an invalid value for the `backdate_mode` choice setting | Rejected with the list of allowed values; nothing stored | PASS |
| P9-02 | 9.5.1 | Business configuration screen | New settings shown; choice settings render as dropdowns | PASS |
| P9-03 | 9.5.2 | Default policy `none`: POS sale dated yesterday / 2 days ahead / now | Yesterday rejected ("not allowed"), future rejected, now accepted | PASS |
| P9-04 | 9.5.2 | Policy `days` = 3: yesterday / 5 days back | Yesterday accepted and stored with yesterday's date; 5 days back rejected | PASS |
| P9-05 | 9.5.2 | Policy `current_month`: 1st of month / last day of previous month | Accepted / rejected | PASS |
| P9-06 | 9.5.2 | Expense screen when back-dating is allowed; back-dated cash expense | Date field shown; expense stored with the chosen date | PASS |
| P9-07 | 9.5.3 | Create a business from Businesses & Shops | Provisioned with 6 cylinder types and 5 expense categories of its own | PASS |
| P9-08 | 9.5.3 | Business A posts a POS sale / rate card / type toggle / expense using business B's cylinder type or category IDs | All rejected; no rows created or changed; B's types not listed for A | PASS |
| P9-09 | 9.5.3 | Supplier mode `shared` / `branch` / `both` | Business-wide / current branch only / chosen per supplier | PASS |
| P9-10 | 9.5.3 | Second branch of the same business: supplier list, ledger and payment for the first branch's branch-only supplier | Shared suppliers listed; branch-only supplier hidden, ledger 404, payment rejected | PASS |
| P9-11 | 9.5.3 | Migration 040 as an **upgrade** (old schema + a second business already using the global C6 type, a rate card and a supplier) | Each business owns its own C6; the second business's cylinder unit, rate card and supplier payment were re-pointed to its own copies; re-running the file changed nothing | PASS |
| P9-12 | 9.5.3 | Fresh schema + migration runner | 040 recognised as already applied (skipped) | PASS |

**Regression on the same code:** Playwright 26/26 passed; PHPUnit 15 tests OK; Phase 8 checks P8-01…P8-11 passed. One Phase 8 run showed two transient check failures caused by the test harness reading an empty value; the database showed the correct outcomes, and an immediate rerun passed.

**Not covered yet:** MySQL 8 run in CI, page-by-page browser QA of the changed screens (Configuration, Suppliers, Cylinder Types, Expenses, Receipts, Supplier Payments, Purchases, POS date field), and Head Office reporting (9.5.5).

### Phase 9.5.4 — inter-branch stock transfer — 2026-10-15

| ID | Scenario | Actual result | Status |
|---|---|---|---|
| P9-13 | Transfers screen and posting while `stock_transfer_enabled` is off | Page explains the feature is off and links to business configuration; posting rejected | PASS |
| P9-14 | Two-step: MAIN dispatches one filled (6 kg) and one empty cylinder to QA-B2 | `TR-000001` in transit; cylinders keep code and gas weight; MAIN available gas −6 kg; POS cannot sell the in-transit cylinder | PASS |
| P9-15 | Who may receive / cancel | Sender cannot confirm receipt; receiver sees "Awaiting your receipt" and confirms; second receipt refused (exactly 3 IN movements); receiver cannot cancel; cancel requires a reason; sender cancel returns cylinders to stock | PASS |
| P9-16 | One-step flow | Cylinder moved to the receiving branch immediately with status Received | PASS |
| P9-17 | Isolation | Transfer to another business's shop rejected; cylinder at another branch cannot be sent; a branch that is neither sender nor receiver gets 404 | PASS |
| P9-18 | Permission | Cashier (no `STOCK_TRANSFER_MANAGE`) gets 403 and does not see the menu entry | PASS |
| P9-19 | Reconciliation | At both branches, gas movement history (in − out) equals gas in filled cylinders | PASS |
| P9-20 | Browser walkthrough (Playwright, Chromium): enable settings in Business Configuration, select cylinders, dispatch, switch branch, confirm receipt; open Suppliers, Cylinder Types, Expenses, POS | Completed with no browser console/page errors; screenshots reviewed | PASS |

Regression after 9.5.4: Playwright 26/26, Phase 9 checks 28/28, Phase 8 checks passed (the two no-session credit checks need a temporary CLI command and were verified in the earlier run).

### Phase 9.1–9.3 — Head Office report, branch administration and scope review — 2026-10-16

| ID | Scenario | Actual result | Status |
|---|---|---|---|
| P9-21 | Branch Overview figures vs independent SQL, two branches with cash sale, credit sale, voided sale, expense, customer receipt, one-step transfer and a sale at the second branch | Sales count/total, gas KG, credit, voids, expenses, receipts, gas stock, filled cylinders, open-cash expected and customer OS match SQL for both branches and the total (29 checks) | PASS |
| P9-22 | Branch Overview scope: MAIN-only manager requests QA-B2 via `branch_ids[]`; admin filters to QA-B2 | Manager sees only MAIN; filter limits the report to QA-B2 | PASS |
| P9-23 | Users screen: create a cashier with access to MAIN + QA-B2, default QA-B2; sign in | Access rows and default saved; user lands in QA-B2 | PASS |
| P9-24 | Users screen: remove QA-B2 access while the user is signed in there; save with no branch; default outside the selection | Access removed and user moved to MAIN on the next request; empty selection and invalid default refused | PASS |
| P9-25 | Branch setup checklist | MAIN "Ready"; new branch lists missing gas rate, cylinder stock and staff with a switch-branch link | PASS |
| P9-26 | Expense categories: create (code upper-cased), duplicate code, deactivate then post an expense with it | Created for the business; duplicate refused; deactivated category rejected | PASS |
| P9-27 | Sidebar on Expense Categories | Exactly one active entry (Expense Categories) | PASS |
| P9-28 | QA-B2 posts `cash/close` with MAIN's open session ID | Refused ("Cash session not found for this branch"); session stays open | PASS (fixed defect) |
| P9-29 | QA-B2 opens `reports/ledger/{MAIN customer}` | 404; MAIN itself gets 200 | PASS (fixed defect) |
| P9-30 | Dashboard opened in a non-default branch | Previously HTTP 500 and listed every branch's customers; now 200 and limited to the branch | PASS (fixed defect) |
| P9-31 | Browser walkthrough (Chromium): Businesses & Shops checklist, Branch Overview, user branch-access form, expense category create, Dashboard in second branch | No console/page errors; screenshots reviewed | PASS |
| P9-32 | Regression on final code | Playwright 26/26; P9-01…P9-20 suites 28/28 and 23/23; Phase 8 suite passed (two no-session credit checks need a temporary CLI command; verified earlier); migration gate accepts CRLF/LF checkouts (8.1.8) | PASS |

### Phase 11.1–11.2 — Design system and application shell — 2026-10-16

Environment: disposable `lpg_qa_phase8` database (MariaDB 10.4), `php spark serve`, Chromium (Playwright). Presentation-only change. No service, controller logic, schema or permission changes, except that the Shop Settings font list now comes from `AppTheme`, which adds a "Device system font" option.

| ID | Scenario | Actual result | Status |
|---|---|---|---|
| P11-01 | Every menu page (31 links for ADMIN) at 1440 px and 390 px | HTTP 200, no console/page errors, exactly one active menu entry matching the page, no horizontal page scroll (62 page loads) | PASS |
| P11-02 | No third-party requests | No request leaves the app origin; Bootstrap, Icons, jQuery, DataTables, Inter and Chart.js are served from `public/assets/vendor` | PASS |
| P11-03 | Shell behaviour | Breadcrumb shows group / page; menu search filters to the matching item and Enter opens it; sidebar collapses to icons (72 px) and the state is remembered; on phones the menu slides in and closes with Escape or the backdrop | PASS |
| P11-04 | Theme | Dark theme applied from the account menu and kept across pages; light restored; branch accent/primary colours produce readable text (unit test: contrast ≥ 4.5:1 for links); invalid stored colours/fonts fall back to defaults | PASS |
| P11-05 | Wide DataTables on phones (Inventory Controls, Gas Wastage, Audit Log overflowed by 38–229 px) | Table scrolls inside its container; the page no longer scrolls sideways | PASS (fixed) |
| P11-06 | Regression | Playwright 26/26 (E2E-011 timeout raised to 150 s: it loads 24 screens and the single-threaded dev server now serves the local assets too); PHPUnit 19/19 (4 new shell tests); Phase 9 suites 28/28, 23/23, 19/19, 29/29 | PASS |

Not verified locally: the Apache cache headers added in `public/.htaccess`, because Apache was not running on this machine. Check them on staging with `curl -I <app>/assets/css/app.css`.

### Phase 11.3 — Dashboard redesign — 2026-10-16

| ID | Scenario | Actual result | Status |
|---|---|---|---|
| P11-07 | Dashboard figures after the move to `DashboardService`, using the verify_hq fixture (cash sale 100, credit sale 30, voided sale, expense 25, receipt 10, sale at QA-B2) | Sales Rs. 130 (cash 100, credit 30, void excluded); cash counter in 160 / out 75 / net 85; receivables Rs. 20; gas 10.20 KG; expenses Rs. 25; branch comparison MAIN 130 / QA-B2 200, matching the SQL-verified Branch Overview | PASS |
| P11-08 | Drill-down tiles, date filter, Current Stock tab | E2E-010 passes unchanged (7 tiles open the right modal titles, From/To + Apply Filter, stock tab) | PASS |
| P11-09 | Light, dark and 390 px | No console errors; no horizontal scroll; chart redraws in the dark palette (series colour validated ≥ 3:1 on both surfaces) | PASS |
| P11-10 | Regression | Playwright 26/26; PHPUnit 19/19; verify_branch_admin 19/19 (non-default-branch dashboard and customer scope), verify_phase9 28/28, verify_transfers 23/23, verify_hq 29/29; shell check 75/76. The one failure is an existing Expenses-page overflow, logged for the Expenses step. | PASS |
