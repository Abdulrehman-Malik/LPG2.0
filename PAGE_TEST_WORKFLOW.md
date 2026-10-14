# LPG Runtime Page QA Workflow

## Purpose

Use this workflow after every page, route, JavaScript, API, permission, layout, or business-logic change. The objective is to verify the **current commit in a running browser and database**, not merely that code compiles or a route returns HTTP 200.

**Applies to:** web pages, page actions/forms, dashboards, report and ledger views, responsive layouts, role-based navigation, and the mobile/multi-shop API workflows.

**Execution status:** this document defines the process; it is not evidence that the application has been run. Record actual results in `SQA.md`.

## 1. Safety and test environment

1. Check the current branch and commit. Work only on the task's approved feature branch; never merge to `main` without explicit approval.
2. Use a disposable SQA database and test records prefixed `SQA-`. Never run destructive tests on production or a live business database.
3. Confirm `.env` points to the intended test database and the application is using the current checked-out commit.
4. Start the app, confirm database connectivity, inspect logs, and verify the login page loads.
5. Use test accounts for each relevant role. Do not rely only on the administrator account.
6. Capture a baseline for all financial and inventory values touched by the test: physical cylinder units and gas weights, inventory movements, sale/purchase documents, customer/supplier ledgers, deposits/custody, payments, cash sessions and transactions.
7. Never use database edits to force a test to pass. If test setup needs data, create it through supported UI/API flows or a documented disposable-database fixture.

## 2. Define impacted-page scope before testing

For each change, write down:
- changed route/controller/view/JavaScript/service/API endpoint;
- every page that consumes or links to the changed component/data;
- every user role that can access the affected feature;
- every financial, inventory, ledger, cash, deposit, audit, or reporting table affected;
- related create/edit/list/detail/history/void/reversal paths;
- mobile/customer/staff API or app screens, if relevant.

Run the page checklist in section 4 for every impacted page. Run full smoke coverage in section 5 before a release or broad refactor.

## 3. Standard test sequence for every page

For each page, run all applicable checks and record the exact result:

1. **Navigation and access**
   - Open the page from its menu and directly by URL.
   - Confirm correct title, selected menu, active shop/business context and breadcrumbs.
   - Verify unauthenticated users are redirected/rejected.
   - Verify a role without permission cannot access the page or its write endpoints.
   - Verify users cannot view or mutate another business/shop's data by changing IDs or request payloads.
2. **Load and rendering**
   - Page loads without a blank screen, HTTP 5xx, PHP exception, visible debug trace or malformed layout.
   - No missing required data, broken images, malformed tables, incorrect column counts, or endless loading indicators.
   - Browser console has no uncaught JavaScript errors; network requests have no unexpected 4xx/5xx.
   - Check desktop and narrow/mobile viewport, zoom, overflow, scrolling, modal and sidebar behavior.
3. **Read/list/filter behavior**
   - Data, totals, statuses, dates and decimal precision match the database/source ledger.
   - Search, filters, sorting, pagination, date ranges, tabs, drill-downs and refresh behave correctly.
   - Empty state and populated state both render properly.
4. **Forms and controls**
   - Open create/edit/detail dialogs; check default values and dropdown options.
   - Test valid submission, required-field validation, invalid values, boundaries, duplicate values and server-side validation.
   - Test cancel/close, reset, save, double-click, browser back/refresh, and failed request behavior.
   - Confirm success/error messages are accurate and the list/detail view reflects persisted data.
5. **Persistence and authorization**
   - Re-open the record/page and confirm saved values persisted.
   - Check invalid/unauthorized requests leave data unchanged.
   - Verify action buttons are hidden or disabled as appropriate, while backend authorization still blocks direct requests.
6. **Business-flow impact**
   - Follow the record to its detail, history, ledger, report and audit trail.
   - Reconcile all expected changes and confirm unrelated balances/stock do not change.
   - For financial/inventory actions, validate atomic rollback by causing a controlled failure in the disposable test environment.
7. **Evidence**
   - Record branch/commit, page/route, role, test data IDs, steps, expected result, actual result, pass/fail/block, screenshot/log/query evidence and defect reference.
   - Mark PASS only when the observed behavior matches the expected result.

## 4. Web page inventory — minimum page-by-page smoke coverage

Test every applicable page below. If the application exposes additional menu entries/routes, add them; this list is not permission to ignore unlisted pages.

| Area | Pages/routes | Minimum page-specific checks |
|---|---|---|
| Authentication/install | `/`, `/login`, `/installation` | Login success/failure, session/logout, installation readiness, missing DB/config handling, unauthenticated protection |
| Dashboard | `/dashboard` | KPI totals against source records, date filters, cards/drill-downs, empty/populated states |
| Business/shop configuration | `/configuration`, `/configuration/application`, `/configuration/business/{id}`, `/configuration/shop/{id}`, `/shop-settings`, shop-context selector | Scope isolation, setting validation/save/reload, branch-specific values, defaults actually used by POS |
| Businesses & Shops | `/business-management`, business/shop/staff-access POST actions | System-Admin-only business creation, Head Office business scope, shop setup creates settings/register/policy defaults, default-shop rules, staff assignment/revocation and cross-business denial |
| Customer Ordering Catalog | `/mobile-catalog`, `/mobile-catalog/product/save`, `/mobile-catalog/product/{id}/availability` | Business catalog CRUD, active cylinder type, sale-mode quantity validation, duplicate product prevention, separate per-shop availability, permission and shop/business isolation |
| Customer App Accounts | `/customer-app-accounts`, signup approve/reject, account-create/disable actions | Pending signup queue, link existing vs create new financial party, username uniqueness, rejection reason/password-hash clearing, password minimum, disable revokes API sessions, one account per customer |
| Web Customer Orders | `/customer-orders`, `/customer-orders/{id}`, `/customer-orders/{id}/status`, `/customer-orders/{id}/fulfill` | Shop-scoped queue/detail/history, legal state transitions/reasons, no manual completion, quote reconfirmation, physical-unit selection, exact payment reconciliation, idempotent POS linkage, cash/stock/ledger/delivery-fee reconciliation |
| POS/sales | `/sales`, `/sales/history`, `/sales/history/data`, `/sales/details/{id}`, `/sales/receipt/{id}`, `/sales/custody/{id}`, `/sales/customer-balance/{id}` | Every transaction type; inputs, rates, quantities, payments, validation, receipt, history/details, ledger/custody impact; void only where supported |
| Cash | `/cash`, `/cash/history` | Open/close, IN/OUT, payment linkage, session totals, closed-session restrictions, history/date filters |
| Customers | `/customers`, `/customers/ledger/{id}`, `/customers/template` | Create/edit/inactive, duplicate/validation, import/template, receipts, credit limit, deposits, custody and ledger reconciliation |
| Suppliers | `/suppliers`, `/suppliers/ledger/{id}` | Create/edit/validation, supplier ledger, payable balance and purchase/payment links |
| Cylinder types | `/cylinder-types` | Create/edit/enable/disable/delete restrictions, capacity validation, references from existing stock |
| Rates | `/rates` | Gas/cylinder rates, effective dates, custom rates, rate history and POS applied-rate consistency |
| Users/roles | `/users` | Create/edit user, roles/permissions, access revocation, session behavior, direct URL/API denial |
| Inventory overview | `/inventory`, `/inventory/cylinders/{typeId}`, `/inventory/cylinder/{id}`, `/inventory/type-history/{typeId}` | Gas KG, filled/empty/sold/issued counts, physical unit status and gas weight, history and drill-down reconciliation |
| Opening inventory | `/inventory/opening`, `/inventory/opening/template` | Import/template, valid/invalid rows, physical units and weights, duplicate prevention, delete restrictions |
| Inventory adjustments | `/inventory/adjustments`, `/inventory/adjustments/{id}` | Shop-owned vs issued units, 2-decimal display, adjustment permissions, history and before/after values |
| Inventory controls/wastage | `/inventory/controls`, `/inventory/wastage` | Policy settings, shop/type overrides, fixed/percentage limits, partial/full wastage, physical unit status, filters/history |
| Purchases | `/purchases` | Purchase lines, rates, supplier payable, stock increase, payment, void/reversal, duplicate/double-submit protection |
| Customer receipts | `/receipts` | Amount bounded by outstanding, payment mode/cash effect, receipt history, customer ledger and duplicate submit |
| Supplier payments | `/supplier-payments` | Amount bounded by payable, cash effect, supplier ledger and duplicate submit |
| Expenses | `/expenses` | Required fields, amount/date/category, cash linkage, report/audit impact and validation |
| Reports | `/reports`, `/reports/inventory-detail`, `/reports/custody`, `/reports/ledger/{id}` | Date filters, totals vs ledgers, cylinder movement/custody history, shop scope, empty state and drill-down |
| Audit | `/audit` | Create/update/void/security actions logged, actor/time/shop, permission protection, no secrets exposed |
| Backup/admin tools | `/database-backup` and supported sub-actions | Admin-only visibility/access, backup create/download/restore in disposable DB only, email configuration, transactional refresh safeguards |
| Mobile API | `/api/v1/health`, customer auth/profile/products/orders, staff auth/businesses/shops/catalog/customer accounts/orders/configuration | Auth token lifecycle, schema/error responses, tenant/shop scoping, role checks, duplicate order prevention, order state transitions and price reconfirmation |
| Unlisted routes/pages | Every route found in `app/Config/Routes.php`, menu or page link | Add a row to the page register and test the same standard sequence |

**Important:** Security Deposit / Issue Cylinder and Cylinder Return / Refund Deposit are separate transaction types. Test each independently and test their customer deposit, custody, inventory and cash effects. Do not treat deposit liability as sales revenue.

## 5. End-to-end business regression

After page-level checks, run a fresh, isolated scenario in this order:

1. Configure a test business/shop and users with different permissions.
2. Create a customer and supplier; configure cylinder types and effective rates.
3. Open a cash session and create physical opening stock with realistic per-cylinder gas weights.
4. Post a purchase and reconcile supplier payable, payment and physical inventory.
5. Execute each supported POS transaction type and verify the invoice, payment, inventory movement, physical cylinder status/gas weight, cash and ledger.
6. Test customer credit under each configured limit mode: `none`, `customer`, and `shop`; include a rejected request and concurrent requests where possible.
7. Issue a cylinder/security deposit and return/refund it; reconcile deposit balance separately from gas-sale OS and revenue.
8. Record partial and full wastage; verify the same cylinder and gas KG cannot be over-consumed.
9. Test receipts, supplier payments, expenses, cash IN/OUT, purchase void and sale void/reversal where supported.
10. Verify dashboard, customer/supplier ledgers, inventory reports, custody report, daily reports, cash history and audit logs against source transactions.
11. Close cash session and reconcile opening cash + IN - OUT against closing cash and linked cash transactions.
12. Repeat key reads with a user assigned to another shop/business and confirm no cross-tenant data leaks.

## 6. Financial and inventory invariants

Do not mark the relevant test as PASS unless these invariants hold:

- Every posted document's header total equals its detail lines and recorded payment/credit breakdown according to the business rules.
- A rejected/failed posting leaves no partial sale, purchase, receipt, cash, ledger or inventory effects.
- One customer order can produce at most one POS sale; retry returns the existing result or fails safely.
- A physical cylinder cannot be sold, consumed, issued or returned twice concurrently.
- Physical cylinder gas remains between zero and its capacity; gas KG changes reconcile to inventory movements.
- Sale/purchase void or reversal occurs once and does not overwrite later valid stock activity.
- Customer credit and shop credit exposure respect the configured mode and limits under concurrency.
- Security deposit held/refunded remains separate from sales revenue and gas OS.
- Cash transaction totals reconcile to the cash session and linked payment records.
- Customer/supplier ledger balances reconcile to their posted source transactions.
- Every business/shop-scoped query and write stays within the authenticated user's authorized scope.

## 7. Failure and fix loop

When a test fails:

1. Stop the affected scenario before continuing transactions that would muddy the evidence.
2. Save the exact route, role, current commit, request inputs, document IDs, screenshots, browser console, server log and before/after balance snapshots.
3. Reproduce on the disposable database; identify root cause rather than hiding the symptom.
4. Fix on the same approved feature branch; add/adjust a regression case.
5. Re-run the failing case, all tests for the affected page, linked workflows, permissions, and financial/inventory invariants.
6. Re-run the full relevant page smoke suite against the **new commit**.
7. Do not mark complete until the affected page and its related workflows pass. If browser/runtime access is unavailable, mark BLOCKED/NOT EXECUTED; do not substitute static review or CI success.

## 8. Results register

Record each execution in `SQA.md` using:

| Commit | Page/route | Role/shop | Case IDs | Expected | Actual | Status | Evidence/defect |
|---|---|---|---|---|---|---|---|
| | | | | | | NOT EXECUTED | |

Allowed statuses: **PASS**, **FAIL**, **BLOCKED**, **NOT EXECUTED**, **N/A**. Never pre-mark a case PASS based on implementation review alone.

## Completion gate

A change is complete only when:
- every impacted page was exercised on the current commit;
- browser console and server logs were reviewed;
- related create/edit/detail/history/ledger/report paths were verified;
- permissions and business/shop isolation were tested;
- affected financial and inventory invariants reconcile;
- regressions are fixed and retested;
- results and evidence are recorded in `SQA.md`;
- CI result is recorded separately from runtime browser QA;
- no merge to `main` occurred without explicit approval.
