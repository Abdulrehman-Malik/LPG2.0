# Inventory / Stock Management – QA Test Cases

**Repository:** `Abdulrehman-Malik/LPG`  
**Module:** Inventory / Stock Management  
**Document purpose:** QA execution checklist based on the current implementation on `main`.

---

## 1. Implementation Analysis / QA Scope

The current inventory implementation is **physical-cylinder driven**.

### 1.1 Authoritative stock model

The table `cylinder_units` is the authoritative source for current physical cylinder inventory.

A cylinder has:
- Location
- Cylinder type
- Physical unit code
- Status
- Actual gas weight
- Source transaction
- Optional customer custody

Current active physical statuses include:
- `filled`
- `empty`
- `custody`

Historical/terminal states include:
- `sold`

### 1.2 Gas stock

Gas stock is **not maintained as an independent physical stock bucket** for current inventory.

Available gas is calculated as:

```
Available Gas KG = SUM(gas_weight_kg of active filled cylinders)
```

Therefore:
- A full cylinder contributes its capacity.
- A partially filled cylinder contributes its actual gas weight.
- An empty cylinder contributes zero gas.
- Gas sales reduce the gas weight of physical cylinders.
- When a filled cylinder reaches zero, it becomes an empty cylinder.

### 1.3 Inventory movement history

`inventory_movements` records inventory movement/audit history.

Expected movement sources include:
- Opening inventory
- Purchase
- Sale
- Sale void/reversal
- Stock adjustment
- Security deposit / cylinder custody workflow
- Cylinder return

QA should verify that **every stock-affecting transaction changes both the physical/current stock state and the corresponding movement history**.

### 1.4 Opening inventory

Opening inventory supports:
- Full filled cylinder
- Partially filled cylinder
- Empty cylinder

For filled cylinders:
- Quantity must be a whole number.
- Actual gas weight must be greater than zero.
- Actual gas weight cannot exceed cylinder capacity.
- Partial-cylinder opening requires actual gas weight **less than capacity**.
- Empty cylinders always have zero gas.

Opening records can be edited/deleted only while their physical cylinders have no downstream inventory history. Once downstream activity exists, the system should require stock adjustment/reversal instead.

### 1.5 Purchases

Purchases can receive:
- Gas KG
- Filled cylinders
- Empty cylinders

For cylinder purchases:
- Quantity must be a whole number.
- Filled cylinders require valid actual gas weight.
- Actual gas weight cannot exceed cylinder capacity.
- Physical cylinder units are created for cylinder purchases.

### 1.6 Sales

Inventory is affected according to transaction type.

Important expected behavior:
- Gas sale consumes gas from filled physical cylinders.
- If a filled cylinder is completely consumed, it becomes empty.
- Filled-cylinder sale consumes one physical filled cylinder per cylinder sold.
- Empty-cylinder sale reduces empty-cylinder physical stock.
- Cylinder custody/security-deposit workflows move physical cylinders into customer custody rather than simply deleting stock.
- Stock cannot become negative.
- Inventory locks are used to protect concurrent stock transactions.

### 1.7 Stock adjustment

Inventory adjustment supports:
- Gas KG
- Filled cylinder
- Empty cylinder
- Bulk adjustment
- Specific physical-cylinder adjustment

Adjustment history stores before/after state, reason, notes and movement history.

### 1.8 Physical-cylinder views

QA should verify:
- Inventory summary by cylinder type.
- Full / partial / empty classification.
- Physical cylinder list.
- Physical cylinder detail.
- Cylinder movement history.
- Cylinder-type history.
- Date filtering.
- All-history option.

### 1.9 Permissions

Inventory management actions are protected by the `INVENTORY_MANAGE` permission. QA must test both authorized and unauthorized users.

---

# 2. Test Data Preparation

Before execution, create at least:

### Cylinder types

| Code | Capacity |
|---|---:|
| C11 | 11 KG |
| C15 | 15 KG |
| C45 | 45 KG |

Use the actual cylinder types configured in the environment if these already exist.

### Suggested opening stock

For C11:
- 2 full cylinders = 11 KG each
- 2 partial cylinders = 5 KG each
- 2 empty cylinders

For C15:
- 2 full cylinders = 15 KG each
- 1 partial cylinder = 7.5 KG
- 1 empty cylinder

Also prepare:
- One active customer allowing credit.
- One customer with a configured credit limit.
- One supplier.
- Test users with and without `INVENTORY_MANAGE`.
- Open cash session where cash transactions are required.

---

# 3. Opening Inventory Test Cases

| ID | Test Case | Steps / Input | Expected Result | Status |
|---|---|---|---|---|
| INV-001 | Open opening inventory screen | Login with inventory permission and open Opening Inventory | Screen loads and active cylinder types are available | [ ] |
| INV-002 | Add full filled cylinder opening | C11, qty 2, leave actual gas blank | 2 physical filled cylinders created, each with 11 KG; gas stock increases by 22 KG | [ ] |
| INV-003 | Add full filled cylinder with explicit weight | C11, qty 2, actual gas 11 KG | 2 filled units created with 11 KG each | [ ] |
| INV-004 | Add partial filled cylinders | C11, qty 2, actual gas 5 KG | 2 filled units created with 5 KG each; inventory classifies them as partially filled; gas increases by 10 KG | [ ] |
| INV-005 | Partial opening without gas weight | Select Partially Filled Cylinder, qty 1, leave gas blank | Save is rejected; actual gas weight is required | [ ] |
| INV-006 | Partial opening equal to capacity | C11, qty 1, actual gas 11 KG | Save is rejected because partial weight must be less than capacity | [ ] |
| INV-007 | Filled opening above capacity | C11, qty 1, actual gas 12 KG | Save is rejected | [ ] |
| INV-008 | Filled opening zero gas | C11, qty 1, actual gas 0 | Save is rejected | [ ] |
| INV-009 | Empty cylinder opening | C11, qty 2, Empty Cylinder | 2 empty physical units created with zero gas | [ ] |
| INV-010 | Decimal cylinder quantity | C11, qty 1.5 | Save is rejected; cylinder quantity must be whole number | [ ] |
| INV-011 | Negative quantity | Quantity -1 | Save is rejected | [ ] |
| INV-012 | Zero quantity | Quantity 0 | No physical cylinders should be created; verify whether UI/business validation rejects zero as required | [ ] |
| INV-013 | Missing cylinder type | Leave cylinder type blank | Save is rejected | [ ] |
| INV-014 | Invalid cylinder type | Submit invalid/non-existing type ID | Save is rejected | [ ] |
| INV-015 | Opening comments | Enter valid comments | Comments are saved and visible in opening history | [ ] |
| INV-016 | Comment length > 500 | Enter >500 characters | Save is rejected | [ ] |
| INV-017 | Edit opening before downstream use | Create opening, edit quantity/weight while no downstream movement exists | Record updates and physical units remain consistent | [ ] |
| INV-018 | Edit opening after downstream use | Sell/use one of its cylinders, then edit opening | Edit is blocked with downstream-history message | [ ] |
| INV-019 | Delete opening before downstream use | Create opening and delete it before use | Opening record, related physical units and opening movements are removed; audit is created | [ ] |
| INV-020 | Delete opening after downstream use | Use one physical cylinder, then delete opening | Delete is blocked | [ ] |
| INV-021 | Multiple openings same type/date | Create same cylinder type/date again | Verify system updates/uses existing opening record according to current implementation and does not create duplicate physical stock unexpectedly | [ ] |
| INV-022 | Opening stock isolation by location | Create opening in Location A and login to Location B | Location B cannot see/use Location A stock | [ ] |

---

# 4. Inventory Summary / Classification

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-023 | Inventory summary loads | Active cylinder types show current stock | [ ] |
| INV-024 | Full cylinder classification | Cylinder with gas >= capacity appears as Filled | [ ] |
| INV-025 | Partial classification | Cylinder with gas >0 and < capacity appears as Partially Filled | [ ] |
| INV-026 | Empty classification | Empty cylinder / zero-gas cylinder appears as Empty | [ ] |
| INV-027 | Total gas calculation | Summary gas equals sum of gas weights on active filled cylinders | [ ] |
| INV-028 | Total cylinder calculation | Summary count equals active filled + empty cylinders | [ ] |
| INV-029 | Cylinder type separation | C11 stock does not appear under C15/C45 | [ ] |
| INV-030 | No sold/cancelled cylinder in active stock | Sold physical units do not contribute to active inventory | [ ] |

---

# 5. Physical Cylinder Test Cases

| ID | Test Case | Steps / Expected Result | Status |
|---|---|---|---|
| INV-031 | Open physical cylinders | Select cylinder type and View Cylinders | Physical units are listed | [ ] |
| INV-032 | Unit code uniqueness | Inspect generated cylinder codes | Every physical cylinder has a unique code | [ ] |
| INV-033 | Search by unit code | Search exact unit code | Matching physical cylinder is displayed | [ ] |
| INV-034 | Filter Filled | Apply Filled filter | Only full filled cylinders are displayed | [ ] |
| INV-035 | Filter Partial | Apply Partial filter | Only cylinders with gas >0 and < capacity are displayed | [ ] |
| INV-036 | Filter Empty | Apply Empty filter | Only empty/zero-gas cylinders are displayed | [ ] |
| INV-037 | Cylinder detail | Open a physical cylinder | Type, capacity, gas weight, status and history are displayed | [ ] |
| INV-038 | Cylinder history | Perform transactions against one cylinder | All relevant movements appear chronologically | [ ] |
| INV-039 | Running gas | Review cylinder history | Running gas is calculated correctly after each gas movement | [ ] |
| INV-040 | Status after movement | Consume gas partially/completely | Status changes to Partially Filled / Empty as applicable | [ ] |

---

# 6. Purchase / Stock-In Test Cases

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-041 | Purchase filled cylinders | Purchase 2 C11 filled cylinders at 11 KG | 2 physical filled units created; gas increases 22 KG | [ ] |
| INV-042 | Purchase partial filled cylinders | Purchase filled cylinders with actual weight below capacity | Physical units contain specified actual gas | [ ] |
| INV-043 | Purchase filled above capacity | Actual gas > capacity | Purchase rejected | [ ] |
| INV-044 | Purchase filled zero gas | Actual gas = 0 | Purchase rejected | [ ] |
| INV-045 | Purchase decimal cylinder quantity | Qty 1.5 | Purchase rejected | [ ] |
| INV-046 | Purchase empty cylinders | Purchase 2 empty C11 | 2 empty units created; gas unchanged | [ ] |
| INV-047 | Purchase gas KG | Purchase gas-only line | Gas movement is posted according to current supported purchase workflow; verify current business rule and reports | [ ] |
| INV-048 | Purchase stock movement | Complete valid purchase | Corresponding purchase inventory movement exists | [ ] |
| INV-049 | Purchase rollback | Force a purchase posting failure | No partial physical units/movements remain after rollback | [ ] |
| INV-050 | Purchase void | Post purchase, then use authorized purchase-void workflow | Stock is fully reversed and purchase status changes correctly | [ ] |
| INV-051 | Purchase void authorization | Disable purchase void setting or use unauthorized user | Void is blocked | [ ] |
| INV-052 | Purchase void twice | Void same purchase twice | Second void is rejected; stock is not reversed twice | [ ] |

---

# 7. Gas Sale / Gas Consumption

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-053 | Sell gas from full cylinder | Sell gas quantity less than cylinder gas | Gas weight on selected/consumed physical stock decreases correctly | [ ] |
| INV-054 | Partial gas consumption | Consume 5 KG from 11 KG cylinder | Cylinder becomes Partially Filled with 6 KG | [ ] |
| INV-055 | Consume exact remaining gas | Consume all remaining gas | Cylinder becomes Empty and gas stock decreases to zero for that cylinder | [ ] |
| INV-056 | Gas sale exceeding total gas | Sell more gas than available | Sale rejected; inventory remains unchanged | [ ] |
| INV-057 | Gas sale with decimal quantity | Sell valid decimal KG | Gas quantity and physical cylinder gas weight are calculated accurately | [ ] |
| INV-058 | Multiple cylinders consumed | Sell enough gas to consume multiple cylinders | Correct cylinders are depleted/updated and total gas is correct | [ ] |
| INV-059 | Gas stock after sale | Compare Inventory summary before/after | Difference equals gas sold | [ ] |
| INV-060 | Movement history after gas sale | Open affected cylinder history | Gas OUT movement exists with correct quantity/source | [ ] |
| INV-061 | Filled cylinder becomes empty | Sale consumes final gas | Filled physical unit becomes empty; no negative gas | [ ] |

---

# 8. Filled Cylinder Sale

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-062 | Sell one filled cylinder | Sell 1 filled C11 | One physical filled cylinder leaves active stock | [ ] |
| INV-063 | Filled-cylinder sale qty whole number | Enter 1.5 | Transaction rejected | [ ] |
| INV-064 | Sell more filled cylinders than available | Quantity > available | Transaction rejected | [ ] |
| INV-065 | Filled cylinder stock after sale | Compare before/after | Filled count decreases exactly by sold quantity | [ ] |
| INV-066 | Physical unit status after sale | Inspect sold unit | Unit is no longer active inventory and is marked sold/correct terminal state | [ ] |
| INV-067 | Filled-cylinder movement history | Inspect movement history | Filled-cylinder OUT movement exists | [ ] |

---

# 9. Empty Cylinder Sale

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-068 | Sell empty cylinder | Sell 1 empty C11 | Empty stock decreases by 1 | [ ] |
| INV-069 | Empty-cylinder sale gas impact | Complete empty cylinder sale | Gas stock remains unchanged | [ ] |
| INV-070 | Empty-cylinder sale over stock | Sell more than available | Transaction rejected | [ ] |
| INV-071 | Empty-cylinder sale decimal qty | Enter decimal cylinder quantity | Transaction rejected | [ ] |

---

# 10. Customer Cylinder Custody / Return

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-072 | Issue cylinder to customer custody | Complete applicable security-deposit/cylinder workflow | Physical unit moves to custody and is no longer counted as active filled/empty stock | [ ] |
| INV-073 | Custody customer ownership | Open customer custody | Correct customer and physical unit are linked | [ ] |
| INV-074 | Add gas to custody cylinder | Add valid gas quantity | Gas weight increases without exceeding capacity | [ ] |
| INV-075 | Add gas beyond capacity | Add gas that exceeds remaining capacity | Transaction rejected | [ ] |
| INV-076 | Return non-empty custody cylinder | Attempt return while gas > 0 | Return rejected; cylinder must be empty | [ ] |
| INV-077 | Return empty custody cylinder | Return after gas is zero | Unit becomes empty active stock and custody record becomes returned | [ ] |
| INV-078 | Double return | Return same custody cylinder twice | Second return rejected | [ ] |

---

# 11. Stock Adjustment

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-079 | Open adjustment screen | Screen loads current stock and adjustment history | [ ] |
| INV-080 | Gas adjustment IN | Add valid gas adjustment | Gas/physical stock reflects adjustment according to configured adjustment workflow | [ ] |
| INV-081 | Gas adjustment OUT | Remove valid gas | Stock decreases correctly | [ ] |
| INV-082 | Gas adjustment OUT beyond stock | Quantity > available | Adjustment rejected | [ ] |
| INV-083 | Filled cylinder adjustment IN | Add filled cylinder | Correct physical cylinder stock created/restored | [ ] |
| INV-084 | Filled cylinder adjustment OUT | Remove filled cylinder | Correct physical stock decreases | [ ] |
| INV-085 | Empty cylinder adjustment IN | Add empty cylinder | Empty stock increases | [ ] |
| INV-086 | Empty cylinder adjustment OUT | Remove empty cylinder | Empty stock decreases | [ ] |
| INV-087 | Specific-cylinder adjustment | Select a physical cylinder and adjust it | Only selected cylinder changes | [ ] |
| INV-088 | Invalid adjustment reason | Leave required reason blank | Adjustment rejected if reason is required | [ ] |
| INV-089 | Adjustment audit | Complete adjustment | Before state, after state, reason, user and movement are recorded | [ ] |
| INV-090 | Adjustment detail | Open adjustment detail | Related movement and before/after state are displayed | [ ] |
| INV-091 | Adjustment history filters | Filter by type/direction/scope/cylinder/search/date | Results match selected filters | [ ] |

---

# 12. Inventory History / Reporting

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-092 | Cylinder type history | Open history for C11 | Only C11-related movements are shown | [ ] |
| INV-093 | Date-from/date-to | Select date range | Only movements in selected range are shown | [ ] |
| INV-094 | Reverse date range | From date later than To date | Dates are normalized/swapped and results remain valid | [ ] |
| INV-095 | All history | Enable All History | Historical movements outside normal date range are displayed | [ ] |
| INV-096 | Opening balance in history | Review a period after opening | Opening/running gas is correct | [ ] |
| INV-097 | Purchase history | Review purchased cylinder | Purchase reference appears | [ ] |
| INV-098 | Sale history | Review sold/consumed cylinder | Sale reference appears | [ ] |
| INV-099 | Adjustment history | Review adjustment | Adjustment reference appears | [ ] |
| INV-100 | Sale void history | Void a sale and inspect history | Reversal movement is visible and stock is restored correctly | [ ] |

---

# 13. Sale Void / Inventory Reversal

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-101 | Void gas sale | Void a posted gas sale | Original inventory impact is fully reversed | [ ] |
| INV-102 | Void filled-cylinder sale | Void posted filled-cylinder sale | Physical cylinder inventory is restored correctly | [ ] |
| INV-103 | Void empty-cylinder sale | Void posted empty-cylinder sale | Empty stock is restored | [ ] |
| INV-104 | Void sale after partial downstream use | Attempt invalid reversal scenario | System prevents inconsistent physical inventory state | [ ] |
| INV-105 | Void twice | Void same sale twice | Second void rejected | [ ] |
| INV-106 | Void movement audit | Inspect movement history | Reversal movements have source `sale_void` and correct references | [ ] |
| INV-107 | Cash reversal | Void cash-paid sale | Corresponding cash impact is reversed | [ ] |
| INV-108 | Customer balance reversal | Void credit sale | Customer receivable is restored/recalculated correctly | [ ] |

---

# 14. Permission / Security

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-109 | Authorized inventory user | User has `INVENTORY_MANAGE` | Inventory pages/actions are accessible | [ ] |
| INV-110 | Unauthorized inventory user | User lacks `INVENTORY_MANAGE` | Inventory endpoints return Forbidden / access is denied | [ ] |
| INV-111 | Direct URL access | Unauthorized user manually enters inventory URL | Access remains denied | [ ] |
| INV-112 | Cross-location access | User from another location attempts to access stock ID | Other location's inventory is not exposed or modified | [ ] |
| INV-113 | Purchase void permission | User not assigned purchase void authorization | Purchase void is blocked | [ ] |

---

# 15. Validation / Negative Tests

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-114 | Invalid inventory type | Submit unsupported inventory type | Transaction rejected | [ ] |
| INV-115 | Missing cylinder type | Cylinder transaction without type | Rejected | [ ] |
| INV-116 | Negative gas quantity | Submit negative gas | Rejected | [ ] |
| INV-117 | Negative cylinder quantity | Submit negative cylinder qty | Rejected | [ ] |
| INV-118 | Gas above capacity | Any physical-cylinder operation creates gas > capacity | Rejected | [ ] |
| INV-119 | Negative resulting stock | Any transaction would make stock negative | Rejected | [ ] |
| INV-120 | Inactive cylinder type | Use inactive type | Transaction rejected | [ ] |
| INV-121 | Duplicate submission | Double-click Save / submit same transaction twice | No duplicate inventory posting | [ ] |
| INV-122 | Refresh after save | Refresh browser after successful post | Transaction is not posted a second time | [ ] |
| INV-123 | Failed transaction rollback | Trigger validation/database failure during posting | No partial stock or movement remains | [ ] |

---

# 16. Concurrency / Data Integrity

These tests are important because inventory posting uses database locking for stock-sensitive operations.

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-124 | Two users sell last filled cylinder | Submit simultaneously | Only one transaction consumes the last available cylinder; other transaction is rejected | [ ] |
| INV-125 | Two users consume same gas stock | Submit gas sales simultaneously | Combined successful quantity never exceeds available gas | [ ] |
| INV-126 | Two users sell last empty cylinder | Submit simultaneously | Stock never becomes negative | [ ] |
| INV-127 | Simultaneous stock adjustment/sale | Run adjustment and sale concurrently | Final stock is consistent; no lost update | [ ] |
| INV-128 | Concurrent physical cylinder selection | Two transactions attempt same unit | Same physical unit cannot be consumed twice | [ ] |

---

# 17. Audit Trail

| ID | Test Case | Expected Result | Status |
|---|---|---|---|
| INV-129 | Opening create audit | Create opening | CREATE audit contains user/location/values | [ ] |
| INV-130 | Opening update audit | Update opening | UPDATE audit contains old/new values | [ ] |
| INV-131 | Opening delete audit | Delete opening | DELETE audit contains old values | [ ] |
| INV-132 | Adjustment audit | Post adjustment | User, reason, before/after state and transaction are retained | [ ] |
| INV-133 | Purchase inventory audit | Post purchase | Inventory movement identifies purchase/source/user | [ ] |
| INV-134 | Sale inventory audit | Post sale | Inventory movement identifies sale/source/user | [ ] |
| INV-135 | Reversal audit | Void sale/purchase | Reversal movement identifies original transaction | [ ] |

---

# 18. End-to-End Reconciliation Tests

## INV-136 – Complete cylinder lifecycle

1. Opening: 1 C11 full at 11 KG.
2. Verify:
   - Filled = 1
   - Partial = 0
   - Empty = 0
   - Gas = 11 KG
3. Sell/consume 5 KG.
4. Verify:
   - Filled = 0
   - Partial = 1
   - Empty = 0
   - Gas = 6 KG
5. Sell/consume remaining 6 KG.
6. Verify:
   - Filled = 0
   - Partial = 0
   - Empty = 1
   - Gas = 0 KG.
7. Verify complete movement history.

**Expected:** Physical cylinder state and gas balance remain consistent through the complete lifecycle.

Status: [ ]

## INV-137 – Opening + purchase + sale reconciliation

1. Opening: 2 C11 full = 22 KG.
2. Purchase: 2 C11 full = 22 KG.
3. Total expected gas = 44 KG.
4. Sell 10 KG.
5. Expected gas = 34 KG.
6. Verify physical cylinder count/status and movement history.

Status: [ ]

## INV-138 – Partial-cylinder reconciliation

1. Opening:
   - 1 C11 full = 11 KG
   - 2 C11 partial at 5 KG = 10 KG
2. Expected total gas = 21 KG.
3. Consume 7 KG.
4. Verify total gas = 14 KG.
5. Verify no cylinder has negative gas and each cylinder's gas is within 0..capacity.

Status: [ ]

## INV-139 – Purchase void reconciliation

1. Start with known stock.
2. Purchase 2 filled C11 cylinders.
3. Record stock.
4. Void purchase using authorized user.
5. Verify stock returns exactly to pre-purchase values.
6. Verify purchase status and reversal movement.

Status: [ ]

## INV-140 – Sale void reconciliation

1. Start with known stock.
2. Post a valid sale.
3. Record stock and customer/cash impact.
4. Void sale.
5. Verify inventory, customer balance and cash impact return to the expected pre-sale state.
6. Verify reversal movement.

Status: [ ]

---

# 19. QA Reconciliation Rules

For every successful test involving stock, QA should record:

- Stock before transaction
- Transaction quantity
- Stock after transaction
- Expected stock
- Difference
- Physical cylinder count
- Gas KG
- Relevant movement record
- Transaction/source reference

The following invariant should always hold for active physical cylinders:

```
0 <= cylinder gas weight <= cylinder capacity
```

For a cylinder type:

```
Gas stock = SUM(gas_weight_kg of active filled cylinders)
```

And:

```
Active cylinder stock =
    filled cylinders
  + empty cylinders
```

Customer-custody and sold cylinders must not incorrectly appear in active shop inventory.

---

# 20. High-Priority Regression Tests

QA should execute these first after every inventory-related change:

1. **INV-004** – Partial filled opening
2. **INV-009** – Empty opening
3. **INV-027** – Total gas calculation
4. **INV-035** – Partial-cylinder filter
5. **INV-041** – Filled-cylinder purchase
6. **INV-046** – Empty-cylinder purchase
7. **INV-054** – Partial gas consumption
8. **INV-055** – Emptying a cylinder
9. **INV-056** – Insufficient gas prevention
10. **INV-062** – Filled-cylinder sale
11. **INV-068** – Empty-cylinder sale
12. **INV-077** – Cylinder return
13. **INV-083** – Filled-cylinder adjustment
14. **INV-087** – Specific-cylinder adjustment
15. **INV-101** – Gas-sale void
16. **INV-102** – Filled-cylinder-sale void
17. **INV-121** – Duplicate submission
18. **INV-123** – Transaction rollback
19. **INV-124** – Concurrent last-stock sale
20. **INV-136** – Complete cylinder lifecycle
21. **INV-139** – Purchase void reconciliation
22. **INV-140** – Sale void reconciliation

---

# 21. Defect Reporting Guidance

For every failed case, QA should record:

- Test Case ID
- Environment
- User/login role
- Location/shop
- Cylinder type
- Stock before
- Exact transaction/input
- Expected result
- Actual result
- Screenshot
- Transaction number
- Physical cylinder code, if applicable
- Relevant inventory movement ID/source
- Browser and timestamp

**Important:** A test is not considered passed merely because the UI shows a success message. QA must verify the resulting physical stock, gas quantity and inventory movement/history.

---

## QA Sign-off

| Area | QA Result | Comments |
|---|---|---|
| Opening Inventory | [ ] Pass / [ ] Fail | |
| Partial Cylinder | [ ] Pass / [ ] Fail | |
| Purchase Stock | [ ] Pass / [ ] Fail | |
| Gas Sale | [ ] Pass / [ ] Fail | |
| Filled Cylinder Sale | [ ] Pass / [ ] Fail | |
| Empty Cylinder Sale | [ ] Pass / [ ] Fail | |
| Customer Custody / Return | [ ] Pass / [ ] Fail | |
| Stock Adjustment | [ ] Pass / [ ] Fail | |
| Physical Cylinder Tracking | [ ] Pass / [ ] Fail | |
| History / Reports | [ ] Pass / [ ] Fail | |
| Sale/Purchase Void | [ ] Pass / [ ] Fail | |
| Permissions | [ ] Pass / [ ] Fail | |
| Concurrency | [ ] Pass / [ ] Fail | |
| Audit Trail | [ ] Pass / [ ] Fail | |
| End-to-End Reconciliation | [ ] Pass / [ ] Fail | |

**Overall QA Result:** [ ] PASS  [ ] FAIL  [ ] PASS WITH KNOWN ISSUES
