# Opening Inventory Screen – Comprehensive QA Test Cases

**Application:** LPG POS  
**Module:** Inventory → Opening Inventory  
**Repository:** `Abdulrehman-Malik/LPG`  
**Purpose:** Exhaustive functional, validation, security, data-integrity and regression test coverage for the Opening Inventory screen.

> QA must verify both the UI result **and the resulting physical inventory / gas stock / inventory movement / audit trail**. A success message alone is not sufficient.

## Current Business Rules Under Test

1. Inventory type options:
   - Full Filled Cylinder
   - Partially Filled Cylinder
   - Empty Cylinder
2. Cylinder quantity must be a whole number and non-negative.
3. Filled-cylinder gas weight:
   - blank = selected cylinder full capacity
   - explicit value must be greater than 0
   - cannot exceed cylinder capacity
4. Partially Filled Cylinder requires actual gas weight:
   - greater than 0
   - strictly less than cylinder capacity
5. Empty cylinders have 0 gas.
6. Cylinder type must be selected and must be an active/valid cylinder type.
7. Comments have a maximum length of 500 characters.
8. Opening records are location-specific.
9. Edit cannot change inventory type or cylinder type after creation.
10. An opening record with downstream physical-cylinder history cannot be edited or deleted.
11. Opening create/update/delete must be audited.
12. Saving must be atomic: a failure must not leave partial cylinder units or movements.
13. Opening filled cylinders create physical cylinder units and gas-in movements.
14. Partial/full classification is derived from actual gas weight versus cylinder capacity.

---

# A. Screen Access and Navigation

| ID | Scenario | Steps / Test Data | Expected Result | Priority | Status |
|---|---|---|---|---|---|
| OI-001 | Open screen with authorized user | Login with INVENTORY_MANAGE and open Opening Inventory | Screen loads successfully | High | [ ] |
| OI-002 | Open screen without permission | Login without INVENTORY_MANAGE and open menu | Menu/page is hidden or access denied | High | [ ] |
| OI-003 | Direct URL without permission | Enter Opening Inventory URL manually | HTTP 403 / access denied | High | [ ] |
| OI-004 | Direct URL after logout | Logout then open URL | User is redirected/denied | High | [ ] |
| OI-005 | Open Add Opening modal | Click Add Opening | Modal opens correctly | High | [ ] |
| OI-006 | Cancel Add modal | Open modal, click X/close | Modal closes without creating data | Medium | [ ] |
| OI-007 | Reopen modal | Open/close several times | Form is reset correctly for Add mode | High | [ ] |
| OI-008 | Page with no records | Location has no opening records | Empty-state/table renders without error | Medium | [ ] |
| OI-009 | Large opening history | Location has many records | Page remains usable; records load correctly | Medium | [ ] |
| OI-010 | Browser refresh | Refresh Opening Inventory page | No duplicate transaction is created | High | [ ] |

# B. Default Form State

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-011 | Open Add form | Form title is Add Opening Inventory | Medium | [ ] |
| OI-012 | Default date | Date defaults to current application date | High | [ ] |
| OI-013 | Default inventory type | Full Filled Cylinder is selected | Medium | [ ] |
| OI-014 | Cylinder type default | Cylinder type shows Select/no accidental value | High | [ ] |
| OI-015 | Quantity default | Quantity is blank | Medium | [ ] |
| OI-016 | Gas field default | Gas field is blank for full type | Medium | [ ] |
| OI-017 | Comments default | Comments field is blank | Low | [ ] |
| OI-018 | Full type gas help | Help says blank uses full capacity | Medium | [ ] |
| OI-019 | No stale Add data | Previously edited values do not remain in new Add form | High | [ ] |
| OI-020 | Required markers/HTML validation | Required date/type/cylinder/quantity fields prevent empty submission where applicable | High | [ ] |

# C. Inventory Type – Full Filled Cylinder

| ID | Scenario | Test Data | Expected Result | Priority | Status |
|---|---|---|---|---|---|
| OI-021 | Add one full cylinder using blank gas | C11, qty 1, gas blank | 1 physical filled unit created at 11 KG | Critical | [ ] |
| OI-022 | Add multiple full cylinders | C11, qty 5, gas blank | 5 physical filled units, 55 KG total | Critical | [ ] |
| OI-023 | Explicit full capacity | C11, qty 2, gas 11 | 2 units at 11 KG each | High | [ ] |
| OI-024 | Explicit decimal gas | C11, qty 2, gas 10.500 | 2 units at 10.500 KG each | High | [ ] |
| OI-025 | Very small positive gas | C11, qty 1, gas 0.001 | Accepted if valid precision is supported | Medium | [ ] |
| OI-026 | Gas zero | C11, qty 1, gas 0 | Rejected because filled gas must be >0 | Critical | [ ] |
| OI-027 | Gas negative | C11, qty 1, gas -1 | Rejected | Critical | [ ] |
| OI-028 | Gas above capacity | C11, qty 1, gas 11.001 | Rejected | Critical | [ ] |
| OI-029 | Gas exactly capacity | C11, qty 1, gas 11 | Accepted | High | [ ] |
| OI-030 | Gas with many decimals | C11, qty 1, gas 10.123456 | Validate supported precision; stored/displayed according to DB/UI precision | Medium | [ ] |
| OI-031 | Gas containing letters | Gas = abc | Rejected/invalid input | High | [ ] |
| OI-032 | Gas special characters | Gas = 10@ | Rejected/invalid input | High | [ ] |
| OI-033 | Gas whitespace | Gas = spaces | Treated as blank/invalid according to business rule; must not create invalid stock | High | [ ] |

# D. Inventory Type – Partially Filled Cylinder

| ID | Scenario | Test Data | Expected Result | Priority | Status |
|---|---|---|---|---|---|
| OI-034 | Add one partial cylinder | C11, qty 1, gas 5 | 1 filled physical unit at 5 KG | Critical | [ ] |
| OI-035 | Add multiple partial cylinders | C11, qty 3, gas 5 | 3 units at 5 KG each; total gas 15 KG | Critical | [ ] |
| OI-036 | Partial gas blank | C11, qty 1, gas blank | Rejected; actual gas required | Critical | [ ] |
| OI-037 | Partial gas zero | C11, qty 1, gas 0 | Rejected | Critical | [ ] |
| OI-038 | Partial gas negative | C11, qty 1, gas -1 | Rejected | Critical | [ ] |
| OI-039 | Partial gas exactly capacity | C11, qty 1, gas 11 | Rejected because partial must be less than capacity | Critical | [ ] |
| OI-040 | Partial gas above capacity | C11, qty 1, gas 12 | Rejected | Critical | [ ] |
| OI-041 | Partial gas just below capacity | C11, qty 1, gas 10.999 | Accepted and classified Partial | High | [ ] |
| OI-042 | Partial gas 0.001 | C11, qty 1, gas 0.001 | Accepted if supported; classified Partial | Medium | [ ] |
| OI-043 | Partial decimal gas | C11, qty 2, gas 5.555 | Both physical units contain 5.555 KG | High | [ ] |
| OI-044 | Partial type UI gas required | Select Partial | Gas field becomes required | High | [ ] |
| OI-045 | Switch Partial → Full | Select Partial, enter gas, switch Full | Required state/help changes correctly; no stale partial validation | High | [ ] |
| OI-046 | Switch Partial → Empty | Select Partial, enter gas, switch Empty | Gas field is hidden/disabled and gas does not post as stock | High | [ ] |
| OI-047 | Switch Full → Partial | Full with blank gas, change to Partial | Gas becomes required | High | [ ] |
| OI-048 | Partial classification in list | Add 5 KG C11 | Record displays Partially Filled Cylinder | Critical | [ ] |

# E. Inventory Type – Empty Cylinder

| ID | Scenario | Test Data | Expected Result | Priority | Status |
|---|---|---|---|---|---|
| OI-049 | Add one empty cylinder | C11, qty 1 | 1 empty physical unit created, gas 0 | Critical | [ ] |
| OI-050 | Add multiple empty cylinders | C11, qty 5 | 5 empty units created | High | [ ] |
| OI-051 | Empty with gas field hidden | Select Empty | Gas field is hidden/disabled | High | [ ] |
| OI-052 | Tamper gas through request | Empty type + actual gas 5 posted directly | Backend ignores/rejects gas; unit remains 0 KG | Critical | [ ] |
| OI-053 | Empty classification | Add empty | List shows Empty Cylinder | High | [ ] |
| OI-054 | Empty does not increase gas | Before/after gas comparison | Gas stock unchanged | Critical | [ ] |

# F. Date Validation

| ID | Scenario | Test Data | Expected Result | Priority | Status |
|---|---|---|---|---|---|
| OI-055 | Valid current date | Today | Accepted | High | [ ] |
| OI-056 | Valid past date | Previous month | Accepted if business process permits historical opening | High | [ ] |
| OI-057 | Valid future date | Tomorrow | Validate business rule; system must not silently create invalid opening | High | [ ] |
| OI-058 | Blank date | Remove date and submit | Rejected | Critical | [ ] |
| OI-059 | Invalid date format | Tamper request | Rejected | High | [ ] |
| OI-060 | Impossible date | 2026-02-30 | Rejected | High | [ ] |
| OI-061 | Date boundary | 2026-01-01 | Accepted | Medium | [ ] |
| OI-062 | Leap date valid | 2028-02-29 | Accepted | Low | [ ] |
| OI-063 | Leap date invalid | 2027-02-29 | Rejected | Low | [ ] |
| OI-064 | Date timezone boundary | Submit around midnight | Correct application date is retained | Medium | [ ] |

# G. Cylinder Type Validation

| ID | Scenario | Test Data | Expected Result | Priority | Status |
|---|---|---|---|---|---|
| OI-065 | Select active cylinder type | C11 | Accepted | Critical | [ ] |
| OI-066 | No cylinder type | Blank | Rejected | Critical | [ ] |
| OI-067 | Invalid type ID | Tamper POST with nonexistent ID | Rejected | Critical | [ ] |
| OI-068 | Inactive type | Use inactive cylinder type via tampered request | Rejected / not available | Critical | [ ] |
| OI-069 | Cylinder capacity displayed/used | C11 capacity 11 | Validation uses correct capacity | Critical | [ ] |
| OI-070 | Different capacities | C15 / C45 | Each uses its own capacity | Critical | [ ] |
| OI-071 | Same quantity, different type | Qty 2 C11 then Qty 2 C15 | Correct gas/units for each capacity | High | [ ] |
| OI-072 | Deleted/nonexistent type after form opened | Open form, deactivate/remove type, submit | Server revalidates and rejects safely | High | [ ] |

# H. Quantity Validation

| ID | Scenario | Test Data | Expected Result | Priority | Status |
|---|---|---|---|---|---|
| OI-073 | Quantity 1 | 1 | Accepted | Critical | [ ] |
| OI-074 | Quantity 2 | 2 | Accepted | High | [ ] |
| OI-075 | Large quantity | 1000 | Accepted if resources/business limits permit; correct number of units | Medium | [ ] |
| OI-076 | Quantity zero | 0 | Validate current business rule; must not create misleading zero-stock opening | Critical | [ ] |
| OI-077 | Quantity negative | -1 | Rejected | Critical | [ ] |
| OI-078 | Decimal quantity | 1.5 | Rejected | Critical | [ ] |
| OI-079 | Decimal quantity .5 | 0.5 | Rejected | High | [ ] |
| OI-080 | Quantity text | abc | Rejected | High | [ ] |
| OI-081 | Quantity scientific notation | 1e3 | Server must not bypass whole-number validation | High | [ ] |
| OI-082 | Quantity with spaces | 2 spaces | Safely validated | Medium | [ ] |
| OI-083 | Very large quantity | Maximum practical integer | No overflow; clear handling | Medium | [ ] |
| OI-084 | Excessive numeric value | Extremely large number | Rejected/handled without server error | High | [ ] |

# I. Comments Validation

| ID | Scenario | Test Data | Expected Result | Priority | Status |
|---|---|---|---|---|---|
| OI-085 | No comments | Blank | Accepted | Medium | [ ] |
| OI-086 | Normal comments | Opening stock 2026 | Saved exactly | Medium | [ ] |
| OI-087 | 499 characters | Max-1 | Accepted | Low | [ ] |
| OI-088 | 500 characters | Exactly max | Accepted | High | [ ] |
| OI-089 | 501 characters | Max+1 | Rejected | High | [ ] |
| OI-090 | Unicode comments | Urdu/Arabic/etc. | Saved/displayed correctly | Medium | [ ] |
| OI-091 | HTML in comments | <script>...</script> | Displayed safely; no script execution | Critical | [ ] |
| OI-092 | SQL-like text | ' OR 1=1 -- | Saved safely; no SQL injection | Critical | [ ] |
| OI-093 | New lines | Multi-line comment | Saved/displayed correctly | Low | [ ] |
| OI-094 | Leading/trailing spaces | '  test  ' | Stored according to trim rule | Low | [ ] |

# J. Add / Create Transaction

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-095 | Create full opening | Record + physical units + gas movements created atomically | Critical | [ ] |
| OI-096 | Create partial opening | Record + partial physical units + gas movements created | Critical | [ ] |
| OI-097 | Create empty opening | Record + empty physical units created; no gas movement | Critical | [ ] |
| OI-098 | Correct source linkage | Physical units source_type/source_id point to opening | Critical | [ ] |
| OI-099 | Unique physical cylinder IDs | Create multiple units | Every unit has unique ID/code | Critical | [ ] |
| OI-100 | Correct location | Create opening in Location A | All records belong to Location A | Critical | [ ] |
| OI-101 | Correct creator | Create opening | created_by/user audit is correct | High | [ ] |
| OI-102 | Correct opening date | Create with historical date | Movement date uses opening date | High | [ ] |
| OI-103 | Correct gas total | 3 x 5 KG partial | Gas stock = 15 KG | Critical | [ ] |
| OI-104 | No duplicate on double-click | Rapidly click Save twice | Only one opening transaction is created | Critical | [ ] |
| OI-105 | Browser back/resubmit | Submit then Back/Refresh | No duplicate opening is created | High | [ ] |

# K. Duplicate / Existing Opening Records

The implementation searches for an existing record by location + date + inventory type + cylinder type when adding.

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-106 | Same date/type/cylinder | Add same combination twice | Existing opening is updated/reused rather than creating unexpected duplicate record | Critical | [ ] |
| OI-107 | Same date, different cylinder type | Add C11 and C15 | Separate records | High | [ ] |
| OI-108 | Same date, different inventory type | Filled + empty C11 | Separate records | High | [ ] |
| OI-109 | Different date, same type | Two dates | Separate opening records | High | [ ] |
| OI-110 | Same record edit by opening ID | Edit existing record | Correct record only is changed | Critical | [ ] |
| OI-111 | Cross-location same opening key | Same date/type/cylinder in different locations | Locations remain isolated | Critical | [ ] |

# L. Edit Existing Opening

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-112 | Open edit | Edit modal loads correct values | High | [ ] |
| OI-113 | Edit date | Change date | Date updates if no downstream history | High | [ ] |
| OI-114 | Edit quantity upward | 2 → 4 | Additional physical units are created; existing units remain | Critical | [ ] |
| OI-115 | Edit quantity downward before downstream use | 5 → 3 | Current implementation should reject reduction below existing active opening units | Critical | [ ] |
| OI-116 | Edit gas upward | 5 → 7 KG | Existing filled units are updated consistently | Critical | [ ] |
| OI-117 | Edit gas downward | 7 → 5 KG | Existing filled units are updated consistently | High | [ ] |
| OI-118 | Edit full opening comments | Change comments | Comments update | Medium | [ ] |
| OI-119 | Change inventory type in UI | Try changing disabled type | Type cannot be changed | High | [ ] |
| OI-120 | Change cylinder type in UI | Try changing disabled type | Cylinder type cannot be changed | High | [ ] |
| OI-121 | Tamper inventory type on edit | Modify POST | Backend rejects type change | Critical | [ ] |
| OI-122 | Tamper cylinder type on edit | Modify POST | Backend rejects type change | Critical | [ ] |
| OI-123 | Edit missing opening ID | Invalid ID | Clear error; no unrelated record changes | High | [ ] |
| OI-124 | Edit another location's ID | Use valid ID from Location B | Access denied/not found; no change | Critical | [ ] |
| OI-125 | Edit after downstream sale | Sell/use opening cylinder then edit | Edit rejected | Critical | [ ] |
| OI-126 | Edit after downstream adjustment | Adjust opening cylinder then edit | Edit rejected | Critical | [ ] |
| OI-127 | Edit after custody movement | Move cylinder to custody then edit | Edit rejected | Critical | [ ] |
| OI-128 | Edit after cylinder sale | Sell physical cylinder then edit | Edit rejected | Critical | [ ] |
| OI-129 | Edit preserves physical identity | Increase quantity | Existing cylinder codes/IDs are not recreated unnecessarily | High | [ ] |

# M. Delete Existing Opening

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-130 | Delete unused opening | Opening, opening units and opening movements removed | Critical | [ ] |
| OI-131 | Delete confirmation Cancel | Click Delete then Cancel | Nothing is deleted | High | [ ] |
| OI-132 | Delete confirmation OK | Confirm | Record is deleted | High | [ ] |
| OI-133 | Delete after downstream movement | Use one cylinder then delete | Delete rejected | Critical | [ ] |
| OI-134 | Delete after sale | Sell cylinder then delete | Delete rejected | Critical | [ ] |
| OI-135 | Delete after adjustment | Adjust cylinder then delete | Delete rejected | Critical | [ ] |
| OI-136 | Delete another location's record | Tamper URL ID | Cannot delete | Critical | [ ] |
| OI-137 | Delete nonexistent ID | Invalid ID | Clear error; no exception | High | [ ] |
| OI-138 | Delete atomicity | Simulate DB failure during delete | Original data remains intact | Critical | [ ] |
| OI-139 | Delete audit | Delete valid opening | DELETE audit contains old values/user/location | Critical | [ ] |

# N. Inventory Movement Verification

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-140 | Full opening movement | Create full opening | One gas IN movement per filled physical unit | Critical | [ ] |
| OI-141 | Partial opening movement | Create partial opening | One gas IN movement per physical unit with actual gas | Critical | [ ] |
| OI-142 | Empty opening movement | Create empty opening | No gas IN movement | Critical | [ ] |
| OI-143 | Movement source | Inspect movement | source_type = opening_cylinder and correct source_id | Critical | [ ] |
| OI-144 | Movement cylinder linkage | Inspect movement | cylinder_unit_id is correct | Critical | [ ] |
| OI-145 | Movement date | Historical opening | movement_at uses opening date | High | [ ] |
| OI-146 | Movement direction | Opening gas | direction = in | High | [ ] |
| OI-147 | Movement quantity | 3 x 5 KG | Three movements, 5 KG each | Critical | [ ] |
| OI-148 | No stale movements after update | Edit gas | Old opening movements are replaced correctly | Critical | [ ] |
| OI-149 | No stale movements after delete | Delete opening | Opening movements are removed | Critical | [ ] |

# O. Physical Cylinder Verification

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-150 | Full cylinder status | Full opening | status = filled | Critical | [ ] |
| OI-151 | Partial cylinder status | Partial opening | status = filled with gas < capacity | Critical | [ ] |
| OI-152 | Empty cylinder status | Empty opening | status = empty | Critical | [ ] |
| OI-153 | Gas weight full | C11 full | 11 KG | Critical | [ ] |
| OI-154 | Gas weight partial | C11 partial 5 KG | 5 KG | Critical | [ ] |
| OI-155 | Gas weight empty | Empty | 0 KG | Critical | [ ] |
| OI-156 | Capacity constraint | Any opening | gas_weight <= capacity | Critical | [ ] |
| OI-157 | Negative physical gas impossible | Any opening | gas_weight >= 0 | Critical | [ ] |
| OI-158 | Physical unit location | Create opening | Unit location matches current session location | Critical | [ ] |
| OI-159 | Physical unit source | Create opening | Unit source is opening with correct source ID | Critical | [ ] |

# P. List / Display / Classification

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-160 | Record appears after Add | New row appears | High | [ ] |
| OI-161 | Date display | Date shown correctly | Medium | [ ] |
| OI-162 | Full label | Full gas >= capacity | Displays Full Filled Cylinder | Critical | [ ] |
| OI-163 | Partial label | 0 < gas < capacity | Displays Partially Filled Cylinder | Critical | [ ] |
| OI-164 | Empty label | Empty type | Displays Empty Cylinder | High | [ ] |
| OI-165 | Cylinder code/name | Valid cylinder type | Correct code/name displayed | Medium | [ ] |
| OI-166 | Quantity display | Quantity 5 | Displays 5, not decimal 5.000 | Low | [ ] |
| OI-167 | Gas display | 5.123 KG | Displays expected 3-decimal value | Medium | [ ] |
| OI-168 | Comments display | Unicode/normal comments | Correctly displayed | Medium | [ ] |
| OI-169 | Sort order | Multiple dates | Newest date first | Low | [ ] |
| OI-170 | Multiple records same date | Multiple types | Sorting remains stable/readable | Low | [ ] |

# Q. Data Integrity / Transaction Rollback

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-171 | DB failure during create | Force database error | No opening row, units or movements are partially committed | Critical | [ ] |
| OI-172 | DB failure during update | Force database error | Previous opening remains intact | Critical | [ ] |
| OI-173 | DB failure during delete | Force database error | Opening remains intact | Critical | [ ] |
| OI-174 | Cylinder creation failure | Simulate createUnits failure | Opening insert is rolled back | Critical | [ ] |
| OI-175 | Movement insert failure | Simulate movement failure | Physical units/opening record rollback | Critical | [ ] |
| OI-176 | Audit failure | Simulate audit failure | Verify transaction policy does not leave inconsistent stock | High | [ ] |
| OI-177 | Refresh after failed save | Failed submission then refresh | No partial stock appears | High | [ ] |

# R. Security / Request Tampering

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-178 | Remove CSRF token | POST without valid CSRF | Request rejected | Critical | [ ] |
| OI-179 | Change location ID in POST | Attempt another location | Server ignores/rejects; session location remains authoritative | Critical | [ ] |
| OI-180 | Change user ID in POST | Attempt another user | Server uses session user | Critical | [ ] |
| OI-181 | Change type to unsupported value | inventory_type=abc | Rejected | Critical | [ ] |
| OI-182 | Use partial type directly | POST partially_filled_cylinder | Correctly normalized to filled physical stock with partial validation | Critical | [ ] |
| OI-183 | Use empty type with gas | Tampered request | Gas forced to 0 | Critical | [ ] |
| OI-184 | SQL injection in comments | Malicious SQL string | No SQL injection | Critical | [ ] |
| OI-185 | XSS in comments | Script payload | No script execution | Critical | [ ] |
| OI-186 | IDOR edit | Change opening_id to another location | Rejected/not found | Critical | [ ] |
| OI-187 | IDOR delete | Change delete ID | Rejected/not found | Critical | [ ] |

# S. Multi-Location / Data Isolation

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-188 | Location A creates opening | A sees record | Critical | [ ] |
| OI-189 | Location B views screen | B cannot see A's opening | Critical | [ ] |
| OI-190 | Location B attempts edit A | Rejected | Critical | [ ] |
| OI-191 | Location B attempts delete A | Rejected | Critical | [ ] |
| OI-192 | Same cylinder type in A/B | Each location has independent stock | Critical | [ ] |
| OI-193 | Same opening date/type in A/B | Records remain separate | High | [ ] |

# T. Audit Trail

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-194 | Create audit | Create opening | CREATE audit contains old=null, new values, user and location | Critical | [ ] |
| OI-195 | Update audit | Edit opening | UPDATE audit contains old/new values | Critical | [ ] |
| OI-196 | Delete audit | Delete opening | DELETE audit contains old values | Critical | [ ] |
| OI-197 | Audit user | Different users create records | Correct user recorded | High | [ ] |
| OI-198 | Audit location | Different locations | Correct location recorded | High | [ ] |
| OI-199 | Audit gas stock | Full/partial opening | New values contain correct gas stock | High | [ ] |
| OI-200 | No audit for failed transaction | Force failed save | Failed transaction must not produce misleading successful CREATE/UPDATE audit | High | [ ] |

# U. Concurrency / Duplicate Posting

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-201 | Two users add same opening | Simultaneous same location/date/type/cylinder | No unintended duplicate stock | Critical | [ ] |
| OI-202 | Two browser tabs save same form | Submit from both tabs | Stock is consistent; no accidental double posting beyond defined duplicate behavior | Critical | [ ] |
| OI-203 | Edit while another user sells | User A edits while User B creates downstream movement | System prevents inconsistent edit | Critical | [ ] |
| OI-204 | Delete while another user sells | Concurrent delete/sale | No orphan/negative/inconsistent physical inventory | Critical | [ ] |
| OI-205 | Double-click Save | Rapid repeated click | One logical opening operation | Critical | [ ] |

# V. Browser / UI Robustness

| ID | Scenario | Expected Result | Priority | Status |
|---|---|---|---|---|
| OI-206 | Chrome | Complete Add/Edit/Delete | Works | High | [ ] |
| OI-207 | Edge | Complete Add/Edit/Delete | Works | Medium | [ ] |
| OI-208 | Firefox | Complete Add/Edit/Delete | Works if supported | Medium | [ ] |
| OI-209 | Mobile/responsive view | Open screen/modal | Form usable without overlap | Medium | [ ] |
| OI-210 | Keyboard navigation | Tab through form | Logical focus order | Low | [ ] |
| OI-211 | Enter key submit | Valid form | Correctly submits once | Medium | [ ] |
| OI-212 | Escape modal | Open modal, press Escape | Modal closes without save | Low | [ ] |
| OI-213 | Browser validation disabled | Remove HTML required using dev tools | Backend still validates all required values | Critical | [ ] |
| OI-214 | JavaScript disabled | Submit valid server request if possible | Server-side validation/business rules remain enforced | High | [ ] |

# W. End-to-End Opening Inventory Scenarios

## OI-215 – Full Opening Lifecycle

1. Record initial stock.
2. Add 2 C11 Full Filled Cylinders with blank gas.
3. Verify 2 physical units.
4. Verify each unit has 11 KG.
5. Verify gas stock increased by 22 KG.
6. Verify two opening gas movements.
7. Verify CREATE audit.
8. Verify list label = Full Filled Cylinder.

**Expected:** All physical, gas, movement and audit values reconcile.

Status: [ ]

## OI-216 – Partial Opening Lifecycle

1. Record initial stock.
2. Add 3 C11 Partially Filled Cylinders at 5 KG.
3. Verify 3 physical units.
4. Verify total gas = 15 KG.
5. Verify each unit is 5 KG.
6. Verify list label = Partially Filled Cylinder.
7. Verify 3 opening gas movements.
8. Verify audit.

Status: [ ]

## OI-217 – Empty Opening Lifecycle

1. Record initial empty stock.
2. Add 4 C11 Empty Cylinders.
3. Verify 4 physical units.
4. Verify gas remains unchanged.
5. Verify units are empty.
6. Verify no gas-in movements were created.
7. Verify audit.

Status: [ ]

## OI-218 – Mixed Opening Reconciliation

Create:
- 2 × C11 full = 22 KG
- 3 × C11 partial × 5 KG = 15 KG
- 4 × C11 empty = 0 KG

Expected:
- Physical cylinders = 9
- Filled physical units = 5
- Empty physical units = 4
- Gas = 37 KG

Status: [ ]

## OI-219 – Edit Reconciliation

1. Create 2 partial C11 units at 5 KG.
2. Edit gas to 7 KG.
3. Verify both units = 7 KG.
4. Verify total gas = 14 KG.
5. Verify old gas movements are replaced, not duplicated.
6. Verify UPDATE audit.

Status: [ ]

## OI-220 – Quantity Increase Reconciliation

1. Create 2 full C11.
2. Edit quantity from 2 to 5.
3. Verify 3 additional physical units.
4. Verify total active opening units = 5.
5. Verify total gas = 55 KG.
6. Verify movement count/quantities.
7. Verify UPDATE audit.

Status: [ ]

## OI-221 – Downstream Protection

1. Create 2 full C11.
2. Sell/use one cylinder.
3. Try editing the opening.
4. Try deleting the opening.

Expected:
- Both operations are blocked.
- No existing stock is changed.
- User is instructed to use stock adjustment/reversal.

Status: [ ]

## OI-222 – Location Isolation

1. Location A creates 2 C11 openings.
2. Login as Location B.
3. Open Opening Inventory.
4. Attempt to access A's record by ID.

Expected:
- A's records are not listed.
- A's record cannot be edited.
- A's record cannot be deleted.

Status: [ ]

## OI-223 – Invalid Input Security

Attempt direct POSTs for:
- negative quantity
- decimal cylinder quantity
- invalid type
- invalid cylinder type
- gas > capacity
- gas = 0 for partial
- gas = capacity for partial
- >500 character comments
- another location's opening ID
- missing CSRF

Expected: Every invalid request is rejected and no stock is changed.

Status: [ ]

# X. Required Database Reconciliation After Every Critical Test

QA should verify these invariants:

### Physical cylinder

```
0 <= gas_weight_kg <= cylinder_type.capacity_kg
```

### Full cylinder

```
gas_weight_kg = capacity_kg
```

### Partial cylinder

```
0 < gas_weight_kg < capacity_kg
```

### Empty cylinder

```
gas_weight_kg = 0
```

### Opening gas

```
Opening Gas =
SUM(gas_weight_kg)
for physical cylinders
where source_type = 'opening'
and source_id = opening.id
and status = 'filled'
```

### Movement reconciliation

For a filled opening:

```
Number of opening gas movements
=
Number of active filled opening cylinders
```

and each movement quantity should equal the linked cylinder's opening gas quantity.

### Location isolation

```
Opening location_id
=
Cylinder location_id
=
Movement location_id
=
Current user's location_id
```

# Y. Highest Priority Smoke / Regression Suite

Run these after every change to Opening Inventory:

1. OI-001 – Authorized access
2. OI-002 – Unauthorized access
3. OI-021 – Full cylinder opening
4. OI-028 – Gas above capacity
5. OI-034 – Partial cylinder opening
6. OI-036 – Partial gas required
7. OI-039 – Partial equal to capacity
8. OI-049 – Empty cylinder opening
9. OI-054 – Empty does not increase gas
10. OI-066 – Missing cylinder type
11. OI-078 – Decimal cylinder quantity
12. OI-089 – Comments >500
13. OI-095 – Create full opening
14. OI-103 – Gas total reconciliation
15. OI-104 – Double-click prevention
16. OI-114 – Quantity increase
17. OI-115 – Quantity reduction protection
18. OI-121 – Tampered inventory type
19. OI-125 – Edit after downstream movement
20. OI-130 – Delete unused opening
21. OI-133 – Delete after downstream movement
22. OI-140 – Full opening movement
23. OI-141 – Partial opening movement
24. OI-148 – No stale movement after update
25. OI-171 – Create rollback
26. OI-179 – Location tampering
27. OI-186 – IDOR edit
28. OI-187 – IDOR delete
29. OI-194 – Create audit
30. OI-195 – Update audit
31. OI-196 – Delete audit
32. OI-201 – Concurrent duplicate opening
33. OI-215 – Full lifecycle
34. OI-216 – Partial lifecycle
35. OI-218 – Mixed reconciliation
36. OI-221 – Downstream protection

# QA Execution Result

| Category | Pass | Fail | Blocked | Not Run | Comments |
|---|---:|---:|---:|---:|---|
| Access / Security | | | | | |
| Default Form | | | | | |
| Full Filled | | | | | |
| Partial Filled | | | | | |
| Empty Cylinder | | | | | |
| Date | | | | | |
| Cylinder Type | | | | | |
| Quantity | | | | | |
| Comments | | | | | |
| Create | | | | | |
| Duplicate Handling | | | | | |
| Edit | | | | | |
| Delete | | | | | |
| Movements | | | | | |
| Physical Cylinders | | | | | |
| List / Display | | | | | |
| Rollback | | | | | |
| Security / Tampering | | | | | |
| Multi-Location | | | | | |
| Audit | | | | | |
| Concurrency | | | | | |
| Browser / UI | | | | | |
| End-to-End | | | | | |

**Overall Result:** [ ] PASS  [ ] FAIL  [ ] PASS WITH KNOWN DEFECTS

**QA Name:** ____________________  
**Execution Date:** ____________________  
**Build / Commit:** ____________________  
**Environment:** ____________________
