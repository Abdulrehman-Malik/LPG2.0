# Perfect LPG — TESTING.md

## Test Gate

This file tracks the development state and the executable functional test gate.

**Application URL:** `http://localhost:180/LPG2/LPG/public/`

**Default test login:** `admin / admin123`

### Status convention

- `[x]` = verified PASS by the person who executed the test
- `[!]` = FAIL; include evidence/reproduction
- `[ ]` = READY / not yet executed
- `[-]` = N/A

**Important:** Code review or static inspection is not a substitute for browser/DB execution. SQA must execute the browser flows and enter the actual result in **SQA.md**.

---

## Current Development Status

The requested functional scope is implemented and the repository has undergone a second code review.

### Completed development

- Authentication, sessions and role/permission protection.
- Dashboard.
- Customers/parties and customer ledger.
- Suppliers and supplier ledger.
- Cylinder types and capacity validation.
- LPG gas/kg and cylinder-price rates with history.
- Users, roles and permissions.
- Opening inventory.
- Physical cylinder-unit inventory with per-cylinder actual gas weight.
- POS five-mode transaction model: Sell Gas Only; Same-Capacity Replacement; Filled + Gas; Different-Capacity Replacement; Empty Only.
- Cash/cheque/online/credit payments.
- Customer credit-limit enforcement.
- Customer receipts.
- Purchases and supplier credit-limit enforcement.
- Supplier payments.
- Counter cash opening, movement, history and close/reconciliation.
- Expenses.
- Inventory stock adjustments.
- Configurable stock validation with explicit override confirmation when disabled.
- Shop-default and cylinder-type inventory policy overrides.
- Configurable wastage allowance using percentage or fixed KG.
- Partial physical-cylinder wastage and full conversion to empty stock.
- Wastage history/report filtering.
- Sale void/reversal with inventory and cash reversal.
- Daily reports, ledgers and audit logging.
- POS numeric rate inputs use 2-decimal increments.
- Local environment file removed from source control; `.env.example` is provided.
- Branch-level Shop Settings page with POS defaults, stock validation, receipt configuration and backup endpoint configuration.

### Important inventory behavior

A filled physical cylinder has:
- a cylinder type/capacity;
- a physical unit code;
- an actual gas weight between 0 and capacity;
- a status: filled, empty or sold.

POS transaction behavior is explicit:
- **Sell Gas Only:** the user selects exactly one filled physical source cylinder; gas stock decreases only from that cylinder. The selected unit keeps its remaining actual gas when partially consumed and changes from filled to empty when its gas reaches zero.
- **Same-Capacity Replacement:** customer returns an empty cylinder and receives a filled cylinder of the same type; gas is charged by actual gas weight and the returned empty is added to physical stock.
- **Filled + Gas:** gas is charged by actual gas weight plus cylinder price; gas and filled-cylinder stock decrease.
- **Different-Capacity Replacement:** same as Filled + Gas, but the returned empty cylinder is a different type/capacity.
- **Empty Only:** only empty-cylinder stock is reduced; gas stock is unchanged.
- Branch administrators set the default POS transaction type in Shop Settings; the POS uses that branch default for new sales.

Selling a filled cylinder deducts its **actual gas weight**, not automatically its rated capacity.

Partial wastage:
- deducts only the gas reported as wasted;
- reduces the physical unit's gas weight;
- keeps the unit filled if gas remains.

Full wastage:
- deducts the remaining gas;
- moves the physical unit from filled to empty;
- increases empty-cylinder stock.

Wastage cannot exceed current gas or the configured percentage/fixed-KG allowance.

---

## Code Review Fixes Applied

1. Fixed duplicate POS JavaScript declaration that could prevent POS submission.
2. Fixed POS empty-cylinder stock display so it uses real controller stock.
3. Changed POS and purchase rate number inputs to 2-decimal increments.
4. Made inventory cylinder adjustments create/remove physical cylinder units instead of changing aggregate counts only.
5. Added actual-gas input for filled-cylinder inventory adjustments.
6. Changed wastage from an all-or-nothing operation to correct partial-loss behavior.
7. Enforced configured wastage allowance and cylinder-type policy override.
8. Moved wastage physical-unit locking inside the DB transaction.
9. Applied cylinder-type stock-validation overrides during POS gas validation.
10. Enforced customer receipt amount against outstanding customer balance.
11. Enforced supplier payment amount against outstanding supplier balance.
12. Corrected supplier credit-limit validation to consider existing outstanding credit, not only opening balance.
13. Removed tracked local `.env`; added `.env.example` and Git ignore rules.

---

## Phase 1 — Foundation

Previously user-tested / recorded as PASS:

- [x] Database schema installation.
- [x] Database seed data.
- [x] Application configuration.
- [x] Authentication.
- [x] Dashboard.

**Note:** Re-run these as part of the complete SQA regression if the environment/database has been rebuilt.

---

## Phase 2 — Master Data

Previously user-tested / recorded as PASS:

- [x] Customers / Parties.
- [x] Suppliers.
- [x] Cylinder Types.
- [x] LPG Rates.
- [x] Users / Roles / Permissions.
- [x] Opening Inventory.
- [x] Customer ledger.
- [x] Supplier ledger.

**Regression requirement:** SQA must repeat the master-data create/edit/inactive/duplicate/validation cases in SQA.md.

---

## Phase 3/4/5/6 — Full Functional SQA Gate

The following are now **READY FOR SQA** after development completion:

- [ ] Counter Cash open / IN / OUT / history / close.
- [ ] Physical cylinder opening stock and actual gas weight.
- [ ] POS filled-cylinder sale.
- [ ] POS KG refill.
- [ ] POS cylinder exchange.
- [ ] Empty-cylinder intake.
- [ ] Empty-cylinder sale.
- [ ] Custom rates and rate history.
- [ ] Customer credit sales and credit-limit enforcement.
- [ ] Customer receipts and outstanding-balance enforcement.
- [ ] Purchases.
- [ ] Supplier credit-limit enforcement.
- [ ] Supplier payments and outstanding-balance enforcement.
- [ ] Expenses.
- [ ] Physical inventory adjustments.
- [ ] Configurable stock validation.
- [ ] Stock-validation OFF override confirmation.
- [ ] Cylinder-type stock-policy overrides.
- [ ] Percentage wastage.
- [ ] Fixed-KG wastage.
- [ ] Partial cylinder wastage.
- [ ] Full cylinder-to-empty wastage.
- [ ] Wastage report/date/type filtering.
- [ ] Sale void/reversal.
- [ ] Inventory reconciliation.
- [ ] Customer/supplier ledger reconciliation.
- [ ] Daily reports.
- [ ] Audit logging.
- [ ] Browser regression and JavaScript error check.
- [ ] Shop Settings and branch configuration.
- [ ] End-to-end retail-day flow.

Execute the complete matrix in **SQA.md** and record Actual Result, Comments and Status for every case.

---

## Required SQA Execution Order

1. Fresh installation/configuration.
2. Login/permissions.
3. Master data.
4. Opening gas and physical-cylinder inventory.
5. Counter Cash opening.
6. Purchase/inventory receipt.
7. POS filled-cylinder sale.
8. KG refill.
9. Cylinder exchange/intake/empty sale.
10. Credit sale and customer receipt.
11. Supplier credit purchase and supplier payment.
12. Expense and manual cash movements.
13. Inventory controls and wastage.
14. Sale void/reversal.
15. Reports and audit.
16. Counter Cash close.
17. End-to-end reconciliation.

Do not manually correct database balances during testing. If a result is wrong, record the defect and reproduction steps.

---

## Environment Notes

- PHP 8.2+ with intl, mbstring and MySQLi.
- MySQL 8+ / InnoDB.
- Run `database/schema.sql` on a fresh database.
- For an existing database, preserve the current physical-cylinder tables and data; the five POS modes do not require a schema migration because they use the existing sales, sale_items and inventory movement structures.
- Local configuration should be created from `.env.example`.
- Existing databases must run `database/migrations/001_20260930_shop_settings.sql` before using the Shop Settings page.
- Existing databases must also run `database/migrations/009_20261003_permissions_sync.sql` when upgrading from an older permission dataset; this restores missing standard permission rows and default role mappings.
- The repository intentionally does not contain a local `.env`.

## Automated Tests

The repository contains CodeIgniter/PHPUnit framework example tests, but the business-specific functional test suite is primarily represented by the browser/DB SQA matrix in **SQA.md**.

The current development environment used for this review did not provide the user's local Apache/MySQL/browser runtime, so no local browser test is being falsely marked PASS here.

## Release Gate

Development is considered functionally complete for the defined scope when:
- all required implementation items above remain present;
- SQA executes SQA.md;
- every required case is PASS or has an explicitly accepted documented exception;
- all FAIL cases are fixed and re-tested;
- final SQA sign-off is completed.



## SQA — Global Application Appearance (READY FOR USER TEST)

**Latest fix:** Shared layout now loads saved Shop Settings before generating global CSS variables. This ensures theme, font size, font style, primary color and accent color are applied after saving.

1. Open **Shop Settings → Appearance**.
2. Change **Theme**, **Application Font Size**, **Font Style**, **Primary Color**, and **Accent Color** one at a time; save after each change.
3. Confirm the change is visible immediately after the redirected Shop Settings page loads.
4. Open/refresh Dashboard, POS, Inventory, Customers, Cash and Reports; confirm the selected appearance is applied globally.
5. Log out and log back in; confirm the selected appearance persists.
6. Change font size to 12 and 18 and verify multiple pages change accordingly.
7. Change font style and verify the selected family is used across the application.
8. Change primary/accent colors and verify sidebar, navigation active state and action accents reflect the saved values.
9. Change back to Light/default colors after testing.
10. Verify invalid theme/font/color/font-size values are rejected by server validation.

**SQA Comments / Improvement Notes:**

**Evidence / Test Data:**


---

## Current Automated CI Baseline — October 2026

The repository now has GitHub Actions CI at `.github/workflows/ci.yml`.

### Latest verified result

- **Workflow:** LPG CI #20
- **Commit:** `f19b68e730d49ba5b40be9ed98d4c0b9b407f7ae`
- **Overall:** PASS
- PHP syntax validation: PASS
- Composer dependencies: PASS
- MySQL 8 schema bootstrap: PASS
- Current POS/settings migrations: PASS
- CodeIgniter startup via `php spark`: PASS
- PHPUnit: **5 / 5 tests passed, 6 assertions**
- PHPUnit coverage reporting is disabled in CI with `--no-coverage`; the CI runner does not have a coverage driver.

This automated result proves that the repository can install dependencies, initialize the configured MySQL schema/migrations, boot CodeIgniter and execute the current PHPUnit suite. It does **not** constitute browser or business-process SQA sign-off.

### CI troubleshooting history already resolved

The CI setup required these fixes:
1. Synced `composer.lock` with `composer.json`.
2. Updated locked `kint-php/kint` to 6.1.x and `predis/predis` to 3.x, including `psr/http-message`.
3. Reworked the two 20261003 migrations to avoid unsupported `ADD COLUMN IF NOT EXISTS` syntax.
4. Aligned CI database bootstrapping with the self-contained `database/schema.sql` database name `perfect_lpg`.
5. Defined `SUPPORTPATH` during CodeIgniter bootstrap so `CI_ENVIRONMENT=testing` starts correctly.
6. Run PHPUnit through PHP CLI to avoid executable-bit problems.
7. Disabled coverage reporting in the CI PHPUnit invocation.

These changes are part of the current repository baseline and should not be removed casually.

---

## Current Application Architecture Test Notes

### POS transaction types

The current POS architecture is **four header-level transaction types**, one per invoice:
- `gas_sale`
- `cylinder_sale`
- `security_deposit`
- `cylinder_return`

Older documentation and test cases refer to a historical five-mode line-level POS. Treat those older sections as historical/regression references unless explicitly updated to the current four-type architecture.

### Gas stock and cylinder tracking

For normal gas sales:
- Quantity is in KG.
- 1 KG means exactly 1 KG gas consumption.
- With **Individual Cylinder Tracking OFF** (the default), POS displays aggregate available gas by cylinder type.
- The backend automatically allocates gas across available filled physical cylinders of the selected type.
- Physical cylinder units are the authoritative current stock records; gas KG is derived from them.
- When a unit reaches zero KG, it becomes empty and the related inventory movements are recorded.

### Inventory detail

The Inventory Detail Report is intended to show:
- total company gas stock;
- filled and empty cylinder counts;
- stock by cylinder type;
- partially used filled physical cylinders with remaining KG and used percentage.

### Security deposits / custody

Security deposit is a liability/hold, not normal sales revenue.
Physical company cylinders move into customer custody.
Cylinder return operates against the customer's custody cylinder and refunds the deposit.
Security-deposit and cylinder-return transactions have special restrictions and should not be treated as ordinary voidable POS sales.

### Browser/E2E staging requirement

A local URL such as `http://localhost/.../public/` is accessible only from the local machine/network. It is not directly reachable for remote browser testing.

Recommended staging path:
`Windows PC + XAMPP -> Cloudflare Tunnel -> public HTTPS test URL`.

Use a disposable test database and dedicated test credentials. Never expose production credentials or use production data for browser SQA.


## Environment Configuration Rule — Important

- **Never add Cloudflare/tunnel-specific URL logic to application code.**
- The application must use the normal CodeIgniter `app.baseURL` configuration from the local `.env` file.
- For staging, set `app.baseURL` in the staging machine's local `.env` to the staging HTTPS URL.
- For production, set `app.baseURL` in the production machine's local `.env` to the production URL.
- Do not commit tunnel URLs, environment-specific hostnames, production URLs, or environment secrets to the repository.
- Testing infrastructure may use external URLs, but it must not require application-code changes to accommodate a temporary tunnel.


## Walk-in Credit Sale Regression — Main

- [ ] Select Walk-in / Cash, create a sale for Rs. 1,000, enter cash received of Rs. 900, and post. Expected: rejected with a clear message that walk-in sales must be fully paid; no sale, payment, inventory movement, or customer OS is created.
- [ ] Select Walk-in / Cash and explicitly choose Credit as the payment mode. Expected: rejected server-side; no transaction is posted.
- [ ] Select Walk-in / Cash and pay the full sale amount in cash. Expected: sale posts normally.
- [ ] Select a named customer and use a partial payment. Expected: existing customer credit-eligibility and credit-limit rules continue to apply.

**Code fix:** SalesService::prepareSalePaymentPlan() now rejects walk-in payments that do not exactly equal the sale total (within Rs. 0.01), closing the path where a cash underpayment could silently become customer credit. Runtime browser/database verification is still pending.


## POS Gas Sale — Amount Mode Custom Gas Rate (branch: `fix/pos-gas-entry-amount-rate`)

- [ ] In POS → Gas Sale, select **Amount** entry mode and confirm the Gas Rate field remains editable.
- [ ] Enter Amount Rs. 1,000 and Gas Rate Rs. 250/kg; verify calculated quantity is 4.000 KG and line amount remains Rs. 1,000.00.
- [ ] In Amount mode, verify the Qty / KG column header shows the total gas KG to be deducted from stock (for example, `Qty / KG (4.000 KG)`) and updates when amount or rate changes.
- [ ] In Cylinders mode, verify the header shows the cylinder count and total KG gas to be deducted (for example, `Cylinder Qty (10.000 KG gas)`) and updates when cylinder quantity/type changes; in KG mode, keep the plain `Qty / KG` header.
- [ ] Change the rate to Rs. 200/kg; verify quantity updates to 5.000 KG and amount remains Rs. 1,000.00.
- [ ] Post a test sale and verify saved sale-item rate, quantity, total, receipt, stock movement, and customer OS balance are consistent.
- [ ] Test blank/zero/negative rates, amount greater than available stock, and Amount mode with no configured standard rate; invalid entries must be rejected safely and a valid manually entered positive rate must work.

Status: **Not yet runtime-verified**. Execute these checks in the application with test data before merging/deploying.


## Gas Rate Validation Regression — Main

The server must not report **“No effective gas/kg rate exists.”** when a valid positive gas rate was explicitly entered on the POS line. This applies to the legacy gas/replacement posting path and filled-cylinder sales. When a manual rate is supplied, use that same rate consistently for amount-to-KG conversion, line totals, saved sale-item rates, and custom-rate tracking.

- [ ] Positive manual gas rate + no effective configured rate: allow posting when all other validations pass.
- [ ] Blank manual rate + no effective configured rate: reject with a clear instruction to enter a positive line rate or configure an effective rate.
- [ ] Zero/negative manual rate: reject; do not silently post at zero.
- [ ] Verify sale amount, KG, receipt/payment, customer OS, saved rate, and inventory movements reconcile.

Status: **Code updated; runtime/browser/database regression not yet executed.**
