# POS Test Cases

## Purpose
Use this file as the manual POS regression checklist. Execute the steps and mark TRUE or FALSE in the Result column.

**Test date:** __________  
**Tester:** __________  
**Build / Commit:** __________  
**Environment:** __________  

### Result convention
- TRUE = Expected result occurred.
- FALSE = Expected result did not occur.
- Record the actual message/behavior in Actual Result / Notes.

## A. POS Basic Validation

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-001 | Open POS | Open POS screen. | POS loads without JavaScript/PHP errors. | | |
| POS-002 | Default transaction | Open POS with default transaction. | Correct default transaction and required sections are shown. | | |
| POS-003 | No line | Remove all sale lines and click Post Sale. | Sale is rejected with a clear line-required message. | | |
| POS-004 | Missing cylinder type | Add gas line, leave Cylinder Type blank, post. | Sale is rejected; cylinder type is required. | | |
| POS-005 | Zero KG | Select KG mode and enter 0 KG. | Sale is rejected; valid quantity is required. | | |
| POS-006 | Negative KG | Try to enter negative KG. | Negative quantity is rejected. | | |
| POS-007 | KG mode valid | Select KG, cylinder type and valid KG. | Amount is calculated from KG × applicable rate. | | |
| POS-008 | Rs. mode valid | Select Rs., cylinder type and valid amount. | KG is calculated from amount ÷ current gas rate. | | |
| POS-009 | Screen-level mode | Add two gas lines and change KG/Rs once. | Same mode applies to all gas lines; no per-line selector exists. | | |
| POS-010 | KG to Rs | Enter KG then switch to Rs. | Existing amount is converted using current rate. | | |
| POS-011 | Rs to KG | Enter amount then switch to KG. | Existing KG is converted using current rate. | | |

## B. Walk-in / Cash Customer

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-012 | Walk-in cash sale | Select Walk-in / Cash, valid sale, full cash payment, post. | Sale posts successfully if all validations pass. | | |
| POS-013 | Walk-in credit sale | Select Walk-in / Cash, select Credit payment, enter valid sale, post. | Sale is NOT posted. Error says credit sale is not allowed for Walk-in / Cash customer. | | |
| POS-014 | Walk-in cheque | Select Walk-in / Cash and Cheque payment. | Sale is rejected; walk-in is cash only. | | |
| POS-015 | Walk-in online | Select Walk-in / Cash and Online payment. | Sale is rejected; walk-in is cash only. | | |
| POS-016 | Walk-in underpayment | Sale total 1000, receive 900. | Sale is rejected; received amount must equal sale total. | | |
| POS-017 | Walk-in overpayment | Sale total 1000, receive 1100. | Sale is rejected; payment cannot exceed receivable. | | |
| POS-018 | Walk-in credit bypass | Submit Walk-in + Credit directly if possible. | Server rejects credit sale; nothing is posted. | | |

## C. Named Customer Credit

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-019 | Customer cash sale | Active customer, valid sale, full cash. | Sale posts. | | |
| POS-020 | Credit disabled | Customer Allow Credit Sale OFF, use Credit. | Sale rejected; credit is not allowed. | | |
| POS-021 | Credit enabled | Customer Allow Credit Sale ON, within limit, use Credit. | Sale posts. | | |
| POS-022 | Credit limit exceeded | Attempt credit above available limit. | Sale rejected; credit limit exceeded. | | |
| POS-023 | Zero credit limit | Allow Credit ON but limit 0, attempt credit. | Sale rejected. | | |
| POS-024 | Previous balance | Customer has previous OS and makes payment. | Settlement follows configured previous-balance rules. | | |
| POS-025 | Credit permission bypass | Directly submit credit for customer with Allow Credit OFF. | Server rejects; no financial/inventory changes. | | |

## D. Credit Sale / OS / Credit Limit Rules

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-094 | Customer credit permission OFF | Select actual customer with Allow Credit Sale OFF. Make sale with received amount less than sale total. | Sale is rejected with: "Credit sale is not allowed for this customer. Enable Allow Credit Sale on the customer record." | | |
| POS-095 | Customer credit permission ON | Select customer with Allow Credit Sale ON and make a credit sale. | Sale is allowed when the applicable credit-limit rule is satisfied. | | |
| POS-096 | No credit-limit mode | Set Shop Settings Credit Limit Validation = None. Customer Allow Credit Sale ON. Make credit sale. | Credit sale is allowed regardless of customer Credit Limit. Existing OS is still recorded and increased by the new unpaid amount. | | |
| POS-097 | Customer-level limit with previous OS | Customer limit Rs.100,000; existing OS Rs.70,000; new credit Rs.20,000. | Projected OS Rs.90,000; sale allowed. | | |
| POS-098 | Customer-level limit exceeded by previous OS + new credit | Customer limit Rs.100,000; existing OS Rs.90,000; new credit Rs.20,000. | Projected OS Rs.110,000; sale rejected. | | |
| POS-099 | Customer-level zero limit | Customer Allow Credit Sale ON; Credit Limit Rs.0; make any new credit. | Sale rejected because no additional credit is available. | | |
| POS-100 | Shop-level limit | Shop limit Rs.1,000,000; current positive shop OS Rs.900,000; new credit Rs.50,000. | Projected shop OS Rs.950,000; sale allowed. | | |
| POS-101 | Shop-level limit exceeded | Shop limit Rs.1,000,000; current positive shop OS Rs.980,000; new credit Rs.30,000. | Projected shop OS Rs.1,010,000; sale rejected. Individual customer limit is ignored. | | |
| POS-102 | Shop-level ignores customer limit | Shop mode; customer limit Rs.10,000; shop has available credit; new credit Rs.20,000. | Sale is allowed if shop limit is not exceeded and Allow Credit Sale is ON. | | |
| POS-103 | Existing OS considered | Customer existing OS Rs.50,000; sale Rs.30,000; receive Rs.10,000. | New credit Rs.20,000; projected OS Rs.70,000; validation uses Rs.70,000. | | |
| POS-104 | Payment settles previous OS | Customer existing OS Rs.50,000; sale Rs.30,000; receive Rs.40,000. | Rs.30,000 settles current sale and Rs.10,000 reduces previous OS; resulting OS is Rs.40,000 and no new credit sale is created. | | |
| POS-105 | POS customer status | Select customer with Allow Credit ON. | POS immediately shows Credit Sale Allowed and the applicable limit/current OS/available credit according to Shop Settings mode. | | |
| POS-106 | POS credit disabled status | Select customer with Allow Credit OFF. | POS shows Credit Sale Not Allowed and instructs user to enable Allow Credit Sale. | | |
| POS-107 | Walk-in creates OS | Walk-in sale total Rs.10,000; receive Rs.9,000. | Sale rejected; Walk-in cannot create OS. | | |
| POS-108 | Fully paid customer sale | Customer has existing OS; current sale is fully paid. | Sale posts without creating additional credit; existing OS is not treated as new credit. | | |
| POS-109 | Shop-level concurrent credit | Two sessions attempt credit sales simultaneously near shop limit. | Shop lock prevents combined projected OS from exceeding the shop limit. | | |

## D. Gas Source Cylinder — Manual Selection ON

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-026 | Setting ON | Enable source-cylinder selection setting. | Source Filled Cylinder selector is visible. | | |
| POS-027 | Valid source | Select type/source with sufficient gas and valid KG. | Only selected source cylinder is reduced. | | |
| POS-028 | Missing source | Leave source blank and post. | Sale rejected; source is required. | | |
| POS-029 | Source over quantity | Source has 5 KG; enter 6 KG. | Sale rejected. | | |
| POS-030 | Same source twice | Use same source on two lines. | Duplicate source is rejected. | | |
| POS-031 | Source depleted | Sell exactly all gas from source. | Gas becomes zero and cylinder becomes Empty. | | |
| POS-032 | Partial source sale | Source has 10 KG; sell 4 KG. | Source remains Filled with about 6 KG. | | |

## E. Gas Source Cylinder — Automatic Selection OFF

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-033 | Setting OFF | Disable source-cylinder selection. | Source selector is hidden. | | |
| POS-034 | Automatic sequence | Multiple filled cylinders; sell gas. | Cylinders are consumed in ascending global sequence/ID. | | |
| POS-035 | One cylinder first | First cylinder has enough gas. | Only first required cylinder is consumed. | | |
| POS-036 | Cross-cylinder allocation | Request more than first cylinder but less than total. | First cylinder is consumed, then next cylinder. | | |
| POS-037 | Insufficient stock | Request more than total available gas. | Sale rejected; no inventory changes. | | |
| POS-038 | Duplicate type lines | Add same cylinder type on two gas lines. | Sale rejected; same type cannot appear on multiple gas lines. | | |

## F. Gas Amount / Rs. Mode

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-039 | Amount calculation | Current rate 200/KG; enter Rs.1000. | Calculated KG = 5.000. | | |
| POS-040 | Current rate authority | Select Rs. mode and attempt custom rate. | Server uses current effective gas rate. | | |
| POS-041 | Amount exceeds stock | Stock 5 KG, rate 200/KG, enter Rs.1200. | Sale rejected because calculated KG is 6. | | |
| POS-042 | Valid amount sale | Enter valid amount within stock and pay fully. | Sale posts with calculated KG. | | |
| POS-043 | Return to KG | Switch Rs. to KG and change quantity. | KG input becomes authoritative. | | |
| POS-044 | Amount server validation | Submit manipulated amount/quantity directly. | Server recalculates KG and validates stock. | | |

## G. Payments and Totals

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-045 | Exact payment | Sale 1000, pay 1000. | Accepted. | | |
| POS-046 | Underpayment | Sale 1000, pay 900. | Rejected. | | |
| POS-047 | Overpayment | Sale 1000, pay 1100. | Rejected. | | |
| POS-048 | Split payment | Pay 600 + 400. | Total equals sale total and can post. | | |
| POS-049 | Zero payment | Add blank/zero payment. | Payment rejected. | | |
| POS-050 | Cash + credit | Named customer with credit enabled; valid cash + credit within limit. | Sale posts and credit becomes outstanding. | | |
| POS-051 | Valid discount | Discount less than subtotal. | Net total recalculates correctly. | | |
| POS-052 | Excess discount | Discount greater than subtotal. | Sale rejected. | | |

## H. Customer Cylinder / Replacement

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-053 | Customer cylinder | Select named customer cylinder and valid gas. | Gas is added subject to capacity. | | |
| POS-054 | Capacity exceeded | Sell more gas than remaining customer-cylinder capacity. | Sale rejected. | | |
| POS-055 | Replacement without customer | Attempt replacement with Walk-in. | Sale rejected; named customer required. | | |
| POS-056 | Replace same | Valid customer and same type replacement. | Replacement posts. | | |
| POS-057 | Replace different | Choose a different received cylinder type. | Replacement posts. | | |
| POS-058 | Same type for different replacement | Replace Different but choose same type. | Sale rejected. | | |

## I. Cylinder Sale / Empty Cylinder

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-059 | Filled cylinder sale | Valid whole quantity. | Sale posts using actual gas weight and cylinder rate. | | |
| POS-060 | Filled insufficient | Request more filled cylinders than available. | Sale rejected. | | |
| POS-061 | Fractional cylinder qty | Enter 1.5 cylinders. | Rejected; whole number required. | | |
| POS-062 | Empty cylinder sale | Valid empty-cylinder quantity. | Sale posts against available empty cylinders. | | |
| POS-063 | Empty insufficient | Request more empty cylinders than available. | Sale rejected. | | |

## J. Security Deposit / Custody

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-064 | Deposit UI | Select Security Deposit. | Security Deposit field is visible. | | |
| POS-065 | Normal sale UI | Select Gas Sale. | Security Deposit field is hidden. | | |
| POS-066 | Deposit Walk-in | Attempt Security Deposit with Walk-in. | Rejected; named customer required. | | |
| POS-067 | Valid deposit | Customer, cylinder, deposit amount and payment. | Deposit posts and cylinder enters custody. | | |
| POS-068 | Duplicate custody | Place same cylinder into custody again. | Rejected. | | |
| POS-069 | Return filled cylinder | Return custody cylinder containing gas. | Rejected; cylinder must be empty. | | |
| POS-070 | Return empty cylinder | Return eligible empty custody cylinder. | Cylinder returns to company inventory and deposit refund is recorded. | | |
| POS-071 | Wrong customer return | Return another customer's custody cylinder. | Rejected. | | |
| POS-072 | Custody capacity | Add gas beyond cylinder capacity. | Rejected. | | |

## K. Physical Cylinder / Inventory Integrity

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-073 | Physical cylinder code | Create a physical cylinder. | Code follows cylinder-type-code plus global sequence. | | |
| POS-074 | Global sequence | Create cylinders under different types. | Sequence remains globally unique. | | |
| POS-075 | Gas stock source | Compare POS stock with sum of physical filled-cylinder gas. | Values agree. | | |
| POS-076 | Transaction rollback | Force posting failure after validation. | No partial sale/payment/inventory/customer update remains. | | |
| POS-077 | Concurrent sale | Two sessions sell the same limited stock simultaneously. | Locks prevent overselling. | | |
| POS-078 | Sale void | Post then void a sale. | Inventory and financial effects reverse exactly once. | | |
| POS-079 | Double void | Void the same sale twice. | Second void is rejected; no duplicate reversal. | | |

## L. UI Regression

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-080 | Compact summary | Open normal POS. | Current Sale, Previous Balance and Discount are one row. | | |
| POS-081 | Help text | Review POS line area. | Unnecessary descriptions are removed. | | |
| POS-082 | Screen-level KG/Rs | Review gas line table. | One KG/Rs selector at screen level; none inside rows. | | |
| POS-083 | Source OFF UI | Disable source selection. | Source column is hidden. | | |
| POS-084 | Source ON UI | Enable source selection. | Source column is visible. | | |
| POS-085 | Error clarity | Trigger validation failures. | Error identifies the actual business rule that failed, not an unrelated lower-level error. | | |

## M. Server-Side Bypass

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-086 | Walk-in credit API bypass | Submit Walk-in + Credit directly. | Server rejects credit sale. | | |
| POS-087 | Credit permission bypass | Submit credit for customer with Allow Credit OFF. | Server rejects. | | |
| POS-088 | Credit limit bypass | Submit credit above limit. | Server rejects. | | |
| POS-089 | Stock bypass | Submit quantity above physical stock. | Server rejects. | | |
| POS-090 | Source bypass | Submit invalid source cylinder. | Server rejects. | | |
| POS-091 | Duplicate source bypass | Submit same source on multiple lines. | Server rejects. | | |
| POS-092 | Duplicate type bypass | Submit same cylinder type on multiple gas lines. | Server rejects. | | |
| POS-093 | Amount bypass | Submit Rs. amount converting to more KG than stock. | Server rejects. | | |

## N. Sale History / Void

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-094 | Sale History tab | Open POS and select Sale History. | History tab opens without changing New Sale behavior. | | |
| POS-095 | Default history date | Open Sale History. | From Date and To Date default to today's business date. | | |
| POS-096 | Today's sales | Search with default dates. | Only today's branch sales are shown. | | |
| POS-097 | All history date range | Select an earlier From Date and today's To Date. | Sales for the complete selected range are shown. | | |
| POS-098 | History filters | Filter by sale no, customer, type and status. | Each filter returns only matching records. | | |
| POS-099 | Posted and voided visibility | Search without status filter after voiding a sale. | Original sale remains visible with VOID status. | | |
| POS-100 | Sale details | Click a history row. | Details show header, lines, physical/source cylinder, quantities, rates, amounts and payments. | | |
| POS-101 | Gas source detail | Open a gas sale that consumed a physical source cylinder. | Actual physical source cylinder code is shown in sale details. | | |
| POS-102 | Void permission visibility | Login as user without POS_VOID permission. | Delete/Void action is not visible. | | |
| POS-103 | Void authorized user | Login as user with POS_VOID permission and select a posted sale. | Delete button is visible only for posted sale. | | |
| POS-104 | Void complete reversal | Void a posted sale with reason. | Sale becomes VOID; gas/cylinder inventory, cash and customer ledger effects are reversed atomically. | | |
| POS-105 | Void reason required | Attempt void with blank reason. | Void is rejected and original sale remains posted. | | |
| POS-106 | Double void | Attempt to void an already voided sale. | Second void is rejected and no duplicate reversal is created. | | |
| POS-107 | Void audit trail | Void a sale and reopen its details. | Void date/time, voiding user and reason are retained. | | |
| POS-108 | Unauthorized server bypass | POST directly to sales/void without POS_VOID permission. | Server returns Forbidden; sale and ledgers remain unchanged. | | |
| POS-109 | Cross-branch protection | Request another branch's sale id from history/details/void. | Sale is not exposed or modified. | | |

## O. POS OS / Discount / Receipt Snapshot

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-110 | Previous OS shown | Select a customer with existing OS. | Previous OS Balance shows the outstanding amount before the new sale. | | |
| POS-111 | Discount included in current sale | Enter sale lines and a discount. | Current Sale equals subtotal minus discount; Discount is shown separately. | | |
| POS-112 | Dynamic receipt and OS | Change payment/receipt amount. | Receipt Amount updates immediately; OS Balance = Previous OS + Current Sale - Receipt Amount. | | |
| POS-113 | Fully received | Enter receipt equal to Net Receivable Amount. | OS Balance becomes Rs. 0.00 without refresh. | | |
| POS-114 | Historical snapshot | Post a sale with Previous OS, Discount, Receipt and resulting OS. Reopen Sale History and receipt. | The same recorded Previous OS, Current Sale, Discount, Net Receivable, Receipt Amount and OS Balance are displayed. | | |

## Final POS Acceptance Summary

| Area | Passed | Failed | Not Executed | Notes |
|---|---:|---:|---:|---|
| Basic validation | | | | |
| Walk-in / Cash | | | | |
| Customer credit | | | | |
| Manual source selection | | | | |
| Automatic source allocation | | | | |
| KG / Rs. mode | | | | |
| Payments / totals | | | | |
| Customer cylinder / replacement | | | | |
| Cylinder sale | | | | |
| Security deposit / custody | | | | |
| Inventory integrity | | | | |
| UI regression | | | | |
| Server-side bypass | | | | |
| Overall | | | | |

### Release decision

- [ ] PASS — all critical tests are TRUE.
- [ ] FAIL — one or more critical tests are FALSE.
- [ ] BLOCKED — test could not be executed because of environment/data/setup issue.

**Tester comments:**  
__________________________________________________________________  
__________________________________________________________________  
__________________________________________________________________


## Cylinder Sale — Physical Cylinder Selection Regression

| ID | Test Case | Expected Result |
|---|---|---|
| POS-115 | Cylinder Sale → Empty cylinder, select type and enter quantity | Empty Cylinder Price defaults from Cylinder Type Setup; Gas Rate/Gas KG are not used; amount = quantity × empty-cylinder price. |
| POS-116 | Empty Cylinder Sale posting | Only selected/allocated empty physical-cylinder stock decreases; gas stock remains unchanged. |
| POS-117 | Cylinder Sale → Filled cylinder, select type | Available filled and partially-filled physical cylinders appear in a checkbox grid showing physical code, type/capacity, gas KG and status. |
| POS-118 | Select one filled physical cylinder | Cylinder Qty becomes 1 read-only; Gas KG becomes the selected cylinder's current gas quantity read-only. |
| POS-119 | Select multiple filled physical cylinders | Cylinder Qty and Gas KG are calculated automatically from the selected physical cylinders; neither can be manually edited. |
| POS-120 | Change Gas Rate on filled-cylinder sale | Gas KG remains unchanged/read-only; line amount recalculates using the changed gas rate. |
| POS-121 | Change Cylinder Price on filled-cylinder sale | Gas KG remains unchanged; line amount recalculates using the changed cylinder price. |
| POS-122 | Try to manually edit Gas KG on filled-cylinder sale | Field cannot be edited; server ignores any client-supplied gas quantity and derives gas from locked physical cylinders. |
| POS-123 | Try to manually change filled-cylinder quantity | Quantity is read-only and remains equal to selected physical-cylinder count. |
| POS-124 | Same physical cylinder selected in two lines | UI/server rejects duplicate physical-cylinder selection; transaction is not posted. |
| POS-125 | Physical cylinder becomes unavailable before posting | Server revalidates/locks the selected physical cylinders and rejects the sale with a clear retry message; no partial posting occurs. |
| POS-126 | Partially-filled cylinder selected | Gas KG equals the cylinder's actual current gas quantity; cylinder is sold and gas stock is reduced by that exact quantity. |
| POS-127 | Empty + Filled cylinders in one Cylinder Sale | Both lines can coexist; empty line affects cylinder stock only, filled line affects physical cylinder and gas stock. |
| POS-128 | Discount on mixed Cylinder Sale | Existing discount/Net Receivable/Receipt/OS calculations remain correct. |
| POS-129 | Existing customer credit rules on Cylinder Sale | Existing customer Allow Credit Sale, OS and credit-limit rules remain unchanged. |
| POS-130 | Walk-in Cylinder Sale with unpaid balance | Existing Walk-in cash/full-payment rule remains enforced. |
| POS-131 | Void posted Cylinder Sale | Gas and physical-cylinder inventory movements are fully reversed and the original sale remains visible as VOID. |
| POS-132 | Gas Sale / Refill regression after Cylinder Sale changes | Existing Gas Sale / Refill UI, KG/Rs mode, source-cylinder logic and posting behavior remain unchanged. |


## P. Independent Security Deposit / Gas OS Accounting Regression

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-133 | Issue gas/cylinder + deposit | Customer takes filled cylinder with gas/cylinder charge Rs.3,000 and Security Deposit Rs.5,000; pay both fully. | Gas/cylinder payment is recorded as sale payment; Rs.5,000 is recorded separately as Security Deposit Receive; deposit does not settle gas charge. | | |
| POS-134 | Deposit affects OS | Enable Include Security Deposit in Party OS; issue with gas/cylinder charge fully paid and deposit Rs.5,000. | Gas OS remains 0; Deposit Balance = Rs.5,000; Deposit OS = +Rs.5,000; Overall OS = Rs.5,000. | | |
| POS-135 | Deposit does not affect OS | Disable Include Security Deposit in Party OS; issue with fully paid gas/cylinder charge and deposit Rs.5,000. | Deposit Balance = Rs.5,000; Deposit OS = 0; Overall OS contains no deposit amount. | | |
| POS-136 | Deposit cannot settle gas OS | Gas/cylinder charge Rs.3,000; deposit Rs.5,000; do not pay gas amount. | Gas OS = +Rs.3,000 and Deposit Balance = Rs.5,000 independently. Deposit is not used to clear the Rs.3,000 gas OS. | | |
| POS-137 | Deposit refund + return gas | Return gas value Rs.1,000 and refund deposit Rs.5,000 with both OS settings enabled. | Return Gas OS = -Rs.1,000; Deposit OS = -Rs.5,000; refund payment is classified as Security Deposit Refund; neither component settles the other. | | |
| POS-138 | Partial deposit refund | Deposit balance Rs.5,000; refund Rs.2,000. | Deposit balance becomes Rs.3,000; only Rs.2,000 deposit OS is reversed; return-gas OS remains independent. | | |
| POS-139 | Refund cannot exceed deposit | Deposit balance Rs.5,000; attempt refund Rs.6,000. | Entire transaction rejected; no cylinder, stock, deposit or OS changes. | | |
| POS-140 | Payment classification | Open issue/return transaction details after posting. | Gas/cylinder payments show payment type Sale; deposit receipt shows Security Deposit; refund shows Security Deposit Refund. | | |
| POS-141 | Deposit balance source | Create multiple deposit receives/refunds across cylinders. | Refundable deposit balance equals deposits received minus deposits refunded; gas sales/return gas do not change it. | | |

## Q. Legacy Gas Sale / Refill Regression After Deposit Changes

| ID | Test Case | Steps | Expected Result | Result TRUE/FALSE | Actual Result / Notes |
|---|---|---|---|---|---|
| POS-142 | Existing Gas Sale / Refill | Perform normal Gas Sale / Refill without security deposit. | Existing gas-sale/refill posting, stock deduction, payment and OS behavior remains unchanged. | | |
| POS-143 | Gas Sale with existing deposit | Customer already has refundable Security Deposit and deposit OS setting is ON; make a normal gas refill and pay the refill amount. | Refill payment settles only the gas/refill component; existing deposit is not consumed or reduced. | | |
| POS-144 | Gas Sale credit with existing deposit | Customer has Security Deposit balance and gas refill is posted on credit. | New gas OS increases only by unpaid refill amount; deposit balance remains unchanged. | | |
| POS-145 | Existing refill source-cylinder flow | Run existing source-cylinder ON and OFF refill scenarios. | Source selection/automatic allocation, gas quantities, physical-cylinder balances and refill history remain workable. | | |

| POS-142 | Combined deposit + gas payment | Security Deposit / Issue Cylinder with gas charge and deposit; one Amount Received | POS displays Total Due and allocates payment according to Shop Settings while keeping sale/deposit payment classifications separate. | | |
| POS-143 | Gas-first allocation | Allocation rule = Gas / Cylinder First | Gas/cylinder charge (including previous gas OS) is allocated first; remaining payment is classified as Security Deposit. | | |
| POS-144 | Deposit-first allocation | Allocation rule = Security Deposit First | Deposit is allocated first; remaining payment is classified as Gas / Cylinder Sale. Security Deposit never becomes sale revenue. | | |
| POS-145 | Manual allocation | Allocation rule = Manual Allocation | Cashier-entered gas and deposit allocations equal Amount Received and each stays within its due amount. | | |
| POS-146

### POS-147 — Security Deposit compact multi-tender payment
- Security Deposit / Issue Cylinder payment panel shows Gas / Cylinder + OS, Security Deposit Amount, and one Amount Received total.
- Multiple payment rows remain available (e.g. Rs. 500 Online + Rs. 500 Cash).
- Reference / Note is a single input per payment row.
- Automatic allocation rules continue to split each tender between gas/cylinder and deposit without merging accounting classifications.

### POS-148 — Security Deposit manual allocation
- When Shop Settings allocation rule is Manual, each payment row exposes Gas / Cylinder or Security Deposit classification.
- Gas allocation and deposit allocation must exactly match their respective due amounts.

### POS-149 — Receipt preview
- After posting, the receipt opens in a separate preview window/tab.
- Receipt does not auto-print; Print must be explicitly selected from the preview.
- Existing print layout remains available.
 | Other transaction types unaffected | Run Gas Sale, Cylinder Sale, and Cylinder Return | Existing payment workflows continue to validate/post correctly. | | |


### POS-150 — Security Deposit paid immediately with remaining gas on credit
- Select customer and cylinder(s) for Security Deposit / Issue Cylinder.
- Enter the Security Deposit amount the customer pays at the counter.
- If the receipt/payment amount covers the deposit only, allocate the deposit first; the remaining gas/cylinder amount becomes customer OS/credit subject to the same Allow Credit Sale and credit-limit rules as Gas Sale.
- Verify the server validates projected OS as current transaction balance plus previous OS and rejects the transaction when the applicable credit limit is exceeded.
- Verify Security Deposit itself is never posted as credit.

### POS-151 — Zero/blank payment amount and reference note
- Payment amount accepts blank/null, 0, 1, 1.23, and values up to two decimal places.
- Reference / Note is displayed as a larger field below Add Payment and is persisted with payment records.

| POS-152 | Security Deposit / Issue Cylinder with zero receipt | Customer allows credit and credit limit covers previous OS + current gas/cylinder amount; payment row is blank/0 | Transaction posts with no cash/online receipt; gas/cylinder amount becomes customer credit under the normal Gas Sale credit rules. Security Deposit, when entered, must still be paid separately and cannot be posted on credit. | | |
| POS-153 | Compact Security Deposit payment controls | Security Deposit / Issue Cylinder screen | Payment collection area remains compact, delete payment control is small, and Reference / Note remains below Add Payment. | | |
| POS-154 | Manual gas rate without configured rate — gas-only / replacement | Remove or future-date the effective Gas/KG rate; enter a positive Gas Rate on a valid gas line and post | Sale is not rejected with “No effective gas/kg rate exists”; the manually entered rate is applied consistently to line total and quantity, and custom-rate flag is set. | | |
| POS-155 | Manual gas rate without configured rate — filled cylinder sale | Remove or future-date the effective Gas/KG rate; sell a selected filled physical cylinder and enter a positive Gas Rate | Sale is allowed if all other validations pass; no false missing-rate validation; saved applied rate/total and stock movements reconcile. | | |
| POS-156 | No configured rate and no manual rate | Remove or future-date the effective Gas/KG rate; leave the line Gas Rate blank for a gas-priced transaction | Sale is rejected with a clear message asking for a positive line rate or effective configured rate; no sale, payment, or inventory changes are committed. | | |
