# Inventory Module Test Cases

Mark **PASS / FAIL** in the Result column during QA.

| ID | Test Case | Expected Result | Result |
|---|---|---|---|
| INV-001 | Open Inventory page | Cylinder Type Stock summary is displayed instead of the old ambiguous item list. | |
| INV-002 | Review top summary cards | Current Gas, Total Cylinders, Filled, Partially Filled and Empty are visible. | |
| INV-003 | Verify cylinder type totals | Filled + Partially Filled + Empty equals Total Cylinders for each type. | |
| INV-004 | Verify current gas | Current Gas KG equals the sum of actual gas on company physical cylinders for the type. | |
| INV-005 | Verify total capacity | Total Capacity KG equals the sum of capacities of company physical cylinders for the type. | |
| INV-006 | Click View Cylinders | Physical cylinder list opens inside the Inventory module. | |
| INV-007 | Verify cylinder code | Each physical cylinder shows its unique cylinder code. | |
| INV-008 | Verify Filled status | A cylinder at/above capacity is shown as Filled. | |
| INV-009 | Verify Partially Filled status | A cylinder with gas > 0 and below capacity is shown as Partially Filled. | |
| INV-010 | Verify Empty status | A cylinder with zero gas is shown as Empty. | |
| INV-011 | Search cylinder code | Matching physical cylinder(s) are displayed. | |
| INV-012 | Filter Filled | Only Filled cylinders are displayed. | |
| INV-013 | Filter Partially Filled | Only Partially Filled cylinders are displayed. | |
| INV-014 | Filter Empty | Only Empty cylinders are displayed. | |
| INV-015 | Open cylinder Details | Cylinder details show code, type, capacity, current gas, status, location and ownership/custody. | |
| INV-016 | Open cylinder History | Complete/current-date cylinder movement history is displayed. | |
| INV-017 | Verify history default date | Cylinder History defaults to the current date. | |
| INV-018 | Select history date range | Only movements within the selected date range are displayed. | |
| INV-019 | Select All History | Complete lifecycle history is displayed. | |
| INV-020 | Verify gas running balance | Running Gas = Previous Balance + Gas In - Gas Out. | |
| INV-021 | Verify final gas balance | Latest running gas reconciles with the cylinder's current gas_weight_kg. | |
| INV-022 | Verify purchase history | Purchased cylinder/gas activity identifies the purchase/reference and physical cylinder where applicable. | |
| INV-023 | Verify refill history | Gas added to a cylinder shows Gas In and the resulting running balance. | |
| INV-024 | Verify gas sale history | Gas sale identifies the actual physical source cylinder. | |
| INV-025 | Verify automatic POS allocation history | Automatically allocated gas sales identify every physical source cylinder consumed. | |
| INV-026 | Verify custody history | Security-deposit/custody issue and cylinder return movements are traceable where applicable. | |
| INV-027 | Verify sale reversal history | Sale void/reversal movements are visible and linked to the physical cylinder. | |
| INV-028 | Open Cylinder Type History | Type-level movement history opens from Inventory. | |
| INV-029 | Verify type history date filter | Type history defaults to current date and supports a date range. | |
| INV-030 | Select type All History | Complete type movement history is displayed. | |
| INV-031 | Verify type running gas | Type history running gas reflects physical-cylinder gas movements for that cylinder type. | |
| INV-032 | Verify branch isolation | Only cylinders and movements for the current branch are displayed. | |
| INV-033 | Verify custody exclusion | Customer-custody cylinders are not counted as company stock in the main stock summary. | |
| INV-034 | Verify sold-cylinder exclusion | Sold physical cylinders are not counted as current company stock. | |
| INV-035 | Verify drill-down navigation | Inventory → Cylinder Type → Physical Cylinder → Details → History works correctly. | |
| INV-036 | Verify existing adjustment page | Stock Adjustment & History continues to work after the Inventory redesign. | |
| INV-037 | Verify no duplicate stock | Dashboard values are derived from physical-cylinder records and do not create a second stock bucket. | |
| INV-038 | Regression: POS gas sale | Existing POS gas sale and source-cylinder allocation continue to work. | |
| INV-039 | Regression: Purchase | Existing purchase posting and physical-cylinder creation continue to work. | |
| INV-040 | Regression: Cylinder custody | Existing custody issue/return behavior remains unchanged. | |

## QA Notes

- Use the actual physical cylinder code shown by the application.
- For gas-sale tests, confirm the source cylinder in Inventory History matches the cylinder consumed by POS.
- Verify both manual source-cylinder selection and automatic source-cylinder allocation.
- Do not modify inventory data merely to make a test pass; record the actual result.
