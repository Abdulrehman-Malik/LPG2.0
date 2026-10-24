# AUDIT — LPGSASS requirements vs codebase (Gate A)

Audited: 2026-10-24. Branch `feature/backoffice-completion` @ `3354fe5`, this repo only. The installed copy at `C:\PerfectLPG` runs different code and was not audited (see Q-01).
Companion files: [CODEBASE_MAP.md](CODEBASE_MAP.md) and [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md).

## 1. Summary

**Runtime evidence gathered for this audit.** This repo's code was served with `php spark serve` (PHP 8.5.12) against a new disposable MySQL 8.4 database `lpg_qa`, created with a QA-only user that can reach only that database:

| Run | Result |
|---|---|
| `tests/qa/reset_db.sh`: schema + all migrations + fixtures | PASS |
| Playwright `tests/e2e/lpg-runtime.spec.mjs` (E2E-001…030) | **26 / 26 passed** (5.1 min) |
| `tests/qa/run_all.sh --no-e2e` (shell business-logic suites + browser checks) | see §1.1 |
| PHPUnit (`--no-coverage`) | 19 tests: 7 pass, 12 error. All errors are `Class "SQLite3" not found`, a local PHP build limitation, not code |
| `php -l` on 213 app/test PHP files | 213 / 213 pass |

### 1.1 QA suite results

`bash tests/qa/run_all.sh --no-e2e` resets `lpg_qa` before each suite. It ran 2026-10-24 and exited with code 0.

| Suite | Result | Suite | Result |
|---|---|---|---|
| verify_phase8 | 25 / 25 | pos_check | 19 / 19 |
| verify_phase9 | 28 / 28 | history_check | 16 / 16 |
| verify_transfers | 23 / 23 | cash_check | 17 / 17 |
| verify_hq | 29 / 29 | hier_check | 30 / 30 |
| verify_branch_admin | 19 / 19 | parties_check | 29 / 29 |
| verify_business_flow | 44 / 44 | shell_check | 78 / 78 |
| verify_pages | 52 / 52 | rates_check | 11 / 11 |
| verify_rates | 21 / 21 | | |

**Total: 441 checks passed, 0 failed**, plus Playwright 26/26. What exists works as its own suites describe. The gaps in §2 are features or properties that are absent, not broken behaviour.

### 1.2 Status counts (84 requirements)

| Status | All | P0 (46) | P1 (26) | P2 (12) |
|---|---|---|---|---|
| DONE | 8 | 3 | 5 | 0 |
| PARTIAL | 44 | 32 | 9 | 3 |
| DIVERGENT | 2 | 2 | 0 | 0 |
| MISSING | 30 | 9 | 12 | 9 |
| UNKNOWN | 0 | 0 | 0 | 0 |

(Counted by script from the matrix below against the priorities in LPGSASS.md.)

**P0 requirements met: 3 of 46 (7%)**, counting DONE only. Weighting PARTIAL as half gives about **41%**.

The low DONE count is honest, but it hides how much is already built. The back-office core of stock, money and branches is mature and well tested. Most P0 gaps fall into three groups:
1. **SaaS foundations:** central tenant enforcement, API breadth, roles, audit completeness, reversible migrations.
2. **Pakistan-market specifics:** OGRA price cap, Urdu, tax, JazzCash/Easypaisa, PDF/58 mm/share receipts.
3. **The delivery module:** drivers, vehicles, settlement. It does not exist yet.

## 2. Coverage matrix

Legend: Effort S ≈ ≤1 day, M ≈ 2–4 days, L ≈ >1 week (must be split in the backlog).

### 2.1 Platform and architecture (PLT)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| PLT-01 | PHP 8.1+ / CI4 | DONE | `system/CodeIgniter.php:58` CI 4.7.4; `composer.json` `php ^8.2`; CI on PHP 8.2; lint clean on 8.5 | none | — |
| PLT-02 | Multi-tenant, `tenant_id`, central enforcement | PARTIAL | Tenant root `businesses` → `locations.business_id`; scope via `ShopScopeService`, `BusinessContext`; every query hand-filters `location_id`/`business_id` (e.g. `CustomerModel::findForLocation`, `Reports::ledger`) | **No central enforcement**: no base model, query scope or middleware. A missed WHERE leaks data. Branch-owned tables carry `location_id`, not `tenant_id`; child tables (`sale_items`, `cash_transactions`, …) have no tenant column. `rate_cards.location_id IS NULL` is a cross-tenant fallback (`GasRateModel::effectiveRow`). `locations.code`, usernames and customer-app usernames are unique platform-wide. Several defects of this kind were already found and fixed (SQA P9-28/29/30, P11-23), which shows the risk is real | L |
| PLT-03 | Migrate single-customer data into default tenant, reversible + tested | PARTIAL | Migration 032 creates business `DEFAULT` but explicitly "leaves legacy rows unassigned" (`032_…sql:1-3`); 040 moves master data to the earliest business (tested as upgrade, SQA P9-11) | No backfill of `locations.business_id` on upgraded DBs (`BusinessContext::businessIdForLocation` then throws). Migrations are forward-only SQL with **no down/rollback** | M |
| PLT-04 | API-first; versioned REST API exposes everything the web does | PARTIAL | `/api/v1` 47 routes (`Config/Routes.php`); posting logic mostly in services (`SalesService`, `PurchaseService`, `CashService`, …) | API lacks POS sale/void, cash, purchases, inventory/transfers, customers/suppliers/ledgers, rates, expenses, reports, users. Logic still in controllers (`Expenses::save`, `Receipts::save`, `SupplierPayments::save`, `Reports::*`, `Rates::save`) and views (`layouts/app.php`). API controllers duplicate web ones (`Staff/Businesses` vs `BusinessManagement`) | L |
| PLT-05 | Token auth per user/tenant with revocation | DONE | `ApiSessionService` (random 32-byte tokens, SHA-256 at rest, access + refresh rotation, reuse detection, idle/absolute TTL, revoke on logout, `revokeAllForStaff` on password change/disable, `Users.php`); E2E-025 passed | Not Shield, but equivalent. Throttling is missing; see NFR-02 | — |
| PLT-06 | RBAC: super admin, owner, manager, cashier, driver, accountant; web + API | PARTIAL | `PermissionService` (31 codes), roles ADMIN/MANAGER/CASHIER/PAK_OPS, `users.is_system_admin`; staff API uses `allowsForUser` (`Api/V1/Staff/*`); E2E-027 passed | No owner/driver/accountant roles. Roles and role permissions are **global**, not per tenant. One role per user across all businesses. **`Users::save` does not limit which `role_id` an actor may grant** (a USER_MANAGE holder can grant ADMIN, including to themselves) | M |
| PLT-07 | Audit log of sensitive actions with before/after | PARTIAL | `AuditService::log` (user, branch, action, old/new JSON, IP, UA); used for purchases create/void, stock adjustments, opening inventory, transfers, staff access, business/shop, config, catalog, customer app accounts | **Not audited**: sale void (`SalesService::void`), user/role changes (`Users`), role-permission edits, customer edits, receipt/payment edits. Rate changes go to `rate_change_log` only. `ShopSettings.php:210` inserts directly. `TransactionalDataRefreshService` truncates `audit_logs`. Audit write failures are swallowed (`AuditService.php`) | M |
| PLT-08 | Config via env; no hard-coded hosts/creds/single-customer constants | PARTIAL | DB via `.env`/`LPG_DB_*` (`Config/Database.php:200`); backup paths/Drive creds via env (`Config/Backup.php`) | Single-customer constants: `'Perfect LPG (Pvt.) LTD'` (`Auth.php`, migration 032), `'Perfect LPG'` SMTP name (`ShopSettingsModel`), `MAIN` location code in seeds/030, XAMPP path guessing in `Backup.php` | S |
| PLT-09 | Automated backups + documented restore | PARTIAL | Manual backup/restore UI (`DatabaseBackupService`: mysqldump or portable PHP, email, Google Drive); runbook §2/§6 (`docs/release-runbook.md`); drill `tests/qa/drill_backup_restore.sh` (SQA P12-01) | **No scheduling** in repo (no cron, command or task). Backups are unencrypted. No retention policy | M |
| PLT-10 | Consistent error handling, structured logging, no stack traces in prod | PARTIAL | `Boot/production.php` `display_errors=0`; CI4 error views; `log_message` used | 39 web `catch` blocks flash `$e->getMessage()` to users (DB errors would leak text). Logging is plain-text CI file logs, not structured; no request/tenant context | M |
| PLT-11 | Docker packaging (on-prem) | MISSING | No Dockerfile/compose in repo (`git ls-files`) | A Windows packaged installer exists outside the repo (`C:\PerfectLPG`) | M |
| PLT-12 | CI with tests + static analysis | PARTIAL | `.github/workflows/ci.yml`: lint, schema/migrations on MySQL 8, PHPUnit, Playwright, `business-logic-suites` job (`run_all.sh --suites-only`) | **No static analysis** (PHPStan/Psalm, php-cs-fixer check) | S |
| PLT-13 | Tenant data export CSV/Excel for every major list | PARTIAL | CSV only for Branch Overview (`Reports::branchesCsv`); Excel *templates* for import (`InventoryOpening`, `Customers`) | No export on customers, suppliers, sales, receipts, purchases, stock, ledgers, expenses | M |

### 2.2 Offline and mobile readiness (MOB)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| MOB-01 | Client-generated UUIDs for offline records | MISSING | All PKs `BIGINT AUTO_INCREMENT`; no `uuid` column anywhere (grep) | — | M |
| MOB-02 | Idempotent creates | PARTIAL | Customer orders: `idempotency_key` + `request_fingerprint` (`CustomerOrderService::create`); order fulfilment → one POS sale (`uq_sales_customer_order`, `SalesService::postForCustomerOrder`); E2E-026/028/030 passed | POS sales, receipts, payments, expenses, purchases, stock movements have no idempotency key | M |
| MOB-03 | `created_at`, `updated_at`, `deleted_at`, `version` on syncable tables | MISSING | No `deleted_at` or `version` columns (grep). Many ledger tables have `created_at` only | — | M |
| MOB-04 | Delta sync endpoints | MISSING | none | — | L |
| MOB-05 | Conflict policy: ledgers append-only, masters last-write-wins | PARTIAL | Movements, payments, deposits, cash transactions are inserted, and voids add reversals (`SalesService::void`) | Not documented. `cylinder_units` state is mutated in place. Opening-inventory edit/delete hard-deletes movements (`InventoryOpening.php:250,718`). No `updated_at`-based LWW | M |
| MOB-06 | Installable PWA with offline queue | MISSING | No manifest or service worker (`public/`) | — | L |
| MOB-07 | Driver/staff mobile app | PARTIAL | Flutter skeleton: `mobile/apps/business_staff_app`, `lpg_mobile_core` (login, API client) | Postponed (Phase 13); framework already chosen as Flutter in repo docs; confirm (Q-08) | L |
| MOB-08 | Customer ordering: WhatsApp link first, app later | PARTIAL | Customer ordering API + Flutter `customer_app` skeleton | No WhatsApp/ordering link | S |
| MOB-09 | Low-end Android / slow connections | PARTIAL | Assets self-hosted, no third-party requests (SQA P11-02); 390 px layouts checked (P11-01) | Lists load all rows and page in the browser (DataTables, `public/assets/js/app.js:250`); POS page embeds all customers (`Sales::index`); API order lists unpaginated | M |

### 2.3 Core domain (CORE)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| CORE-01 | Customer: type, WhatsApp phone, address/area, credit limit, notes, active/blocked | PARTIAL | `customers`: code, name, phone, city, address, vehicle_no, credit_limit, allow_credit_sale, opening_balance, is_active | No customer type, notes, area or blocked status (only `is_active`). WhatsApp flag not modelled | S |
| CORE-02 | Configurable cylinder types: name, net, tare, gross, deposit, price basis; 11.8/45.4 defaults | PARTIAL | `cylinder_types`: capacity_kg (net), tare_weight_kg, empty_cylinder_price, per business (`CylinderTypes`, `BusinessProvisioningService`); pricing basis is a business setting (`filled_cylinder_pricing`) | No deposit amount on the type, no gross field. Seeded sizes are 6, 11.8, 15, 35, **45, 45.2**: there is no 45.4 kg (`schema.sql` cylinder_types insert) | S |
| CORE-03 | Stock by state per branch: full, empty, with customer, on vehicle/in transit, at plant, damaged, lost | PARTIAL | `cylinder_units.status` ENUM filled/empty/custody/sold/in_transit, per `location_id`; Inventory Detail report | Missing states: on vehicle, at plant, damaged, lost | M |
| CORE-04 | Append-only movement ledger; movement has type, product, qty, from/to state, branch, user, ref doc, time | PARTIAL | `inventory_movements` (location, inventory_type, cylinder_type, qty, direction in/out/adjustment, source_type/id/line_id, unit, user, time); stock adjustments via movements with history (`InventoryService::adjust`) | No from-state/to-state columns. Unit balance (`cylinder_units.status`, `gas_weight_kg`) is edited directly alongside the ledger. Opening edits delete movements. One post-insert update (`SalesService.php:595`) | M |
| CORE-05 | Purchase/receive filled from marketer/plant: supplier doc no., rate, qty, empties returned | PARTIAL | `PurchaseService::post`: supplier, lines with actual gas weight, unit_rate, payments, void (`verify_business_flow`, SQA P12-02) | No supplier document/invoice no. column (`purchases` has notes only). Empties returned to supplier not recorded | S |
| CORE-06 | Sale with cylinder swap (full out, empty in) incl. fewer empties → customer cylinder balance | DIVERGENT | Four POS types (`ShopSettingsModel::TRANSACTION_TYPES`). Gas Sale sells **KG** drawn from company cylinders (`PostsGasSales`); Cylinder Sale sells the cylinder outright; Security Deposit issues a cylinder into custody | No swap transaction. `sale_items.empty_cylinder_received` is always written as 0 (`PostsCylinderSales.php:169`, `PostsGasSales.php:176`). Customer cylinder balance exists only as deposit custody. Business model needs confirming (Q-03) | L |
| CORE-07 | New connection with deposit, refund on return, per-customer deposit and cylinder statements | PARTIAL | `PostsSecurityDeposits`, `PostsCylinderReturns`, `cylinder_custody`, `customer_security_deposits`; custody report (`Reports::custody`); SQA P8-04/05 | No per-customer deposit/cylinder **statement** (custody report is branch-wide; customer ledger focuses on OS) | S |
| CORE-08 | Empties to plant, refill cycle, shortage/damage/lost, reconciliation | MISSING | none (gas wastage exists: `InventoryControlService::recordWastage`) | — | L |
| CORE-09 | Optional per-cylinder serial/QR | PARTIAL | Every physical cylinder has `unit_code`; `individual_cylinder_tracking` setting | No QR/scan | M |
| CORE-10 | Multi-branch with inter-branch transfers | DONE | `locations`; `StockTransferService` one-step/two-step; `verify_transfers.sh` (SQA P9-13…20) | — | — |
| CORE-11 | Suppliers master + ledger | DONE | `suppliers`, `supplier_payments`, `supplier_opening_balances`, `SupplierBalanceService`, `LedgerService::supplierLedger`; SQA P9-09/10, P11-24/25 | — | — |

### 2.4 Pricing, billing, receipts, credit (BIL)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| BIL-01 | Monthly OGRA notified max price per kg, effective-from, history | DIVERGENT | `rate_cards` (gas_per_kg / filled_cylinder / cylinder_package, `effective_from`, per branch) + `rate_change_log`; `Rates` screen | Rates are the **selling** price set per branch, not the OGRA **maximum** notified price. No OGRA price master | M |
| BIL-02 | Price = per-kg × net kg, rounding, margin, delivery charge | PARTIAL | `RateService::filledCylinderRate` (kg rate × capacity, pro-rata); `MoneyRounding` (document/line modes); `delivery_fee` setting and `sales.delivery_fee_amount`; `verify_rates.sh` | No distributor margin concept. Rounding to the rupee/5/10 not offered | S |
| BIL-03 | Price-cap compliance: warn/block, reason, audit | MISSING | Overrides flagged only (`sale_items.custom_rate_flag`, `standard_rate`) | No cap, no reason, no audit of overrides | M |
| BIL-04 | Sequential gap-free invoice numbers per tenant/branch; lines, deposits, discounts, payment status | PARTIAL | `DocumentNumberService` (per branch, doc type, **day**; atomic in posting transaction); `uq_sale_no(location_id,sale_no)`; deposit fields, discounts, OS on `sales` | Sequence restarts daily (`S20261024-00001`). Is that acceptable vs one continuous series (Q-05)? No explicit payment-status field (derived from credit/OS) | S |
| BIL-05 | Receipt: PDF + 58/80 mm thermal, Urdu + English, logo, share link | PARTIAL | `app/Views/sales/receipt.php`: 80 mm and A4 HTML print layouts; receipt title/footer settings | No PDF, no 58 mm, no Urdu, no logo, no share/WhatsApp link | M |
| BIL-06 | Configurable sales tax per tenant | MISSING | No tax fields (grep `tax`) | Rules unknown (Q-04) | M |
| BIL-07 | Credit ledger: balances, limit enforcement, statements, receipts, ageing | PARTIAL | `CustomerBalanceService`, credit-limit modes none/customer/shop (`SalesService::post`, E2E-014, SQA P8-09); walk-in cash-only; `LedgerService::customerLedger`; `Receipts` | **No ageing report** | S |
| BIL-08 | Methods cash, bank, JazzCash, Easypaisa, cheque with refs | PARTIAL | `payment_mode` ENUM cash/cheque/online/credit + `reference_no` on all payment tables | Bank / JazzCash / Easypaisa collapsed into `online` | S |
| BIL-09 | Void/return via reversing entry, never delete, audit-logged | PARTIAL | `SalesService::void`: status `voided`, reversing inventory movements, cash reversal, receipts voided, guards (SQA P8-01/02, P11-17); `PurchaseService::void` audited | Sale void **not in `audit_logs`**. No partial return (only whole-invoice void). Opening-inventory delete is a hard delete (audited) | S |
| BIL-10 | Expenses + daily cash book | DONE | `expenses` + `expense_categories` per business (`Expenses`, `ExpenseCategories`); counter cash sessions/transactions and Cash History (`CashService`, SQA P11-19…21) | Daily cash book is per register session. Confirm format with owner | — |
| BIL-11 | Payment gateway integration | MISSING | — | P2, out of scope until confirmed | L |
| BIL-12 | Government e-invoicing/POS | MISSING | — | P2, do not build until confirmed (Q-04) | L |

### 2.5 Orders and delivery (DEL)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| DEL-01 | Staff order entry for phone/WhatsApp/walk-in | PARTIAL | Order model exists (`customer_orders`, `CustomerOrderService`) | Orders can only be created by customers through the app API. Staff routes are list/show/status/fulfil only (`OrderManagement`); no requested time | M |
| DEL-02 | Status new → assigned → out for delivery → delivered/failed/cancelled | PARTIAL | `customer_orders.status`: pending, accepted, rejected, preparing, ready, out_for_delivery, completed, cancelled, unable_to_fulfil; history table | No "assigned" (no driver). Mapping to required names needed | S |
| DEL-03 | Assign to driver + vehicle; driver sees own list | MISSING | — | — | M |
| DEL-04 | Driver records delivery: cylinders, empties, payment, notes | MISSING | Fulfilment is a staff POS posting (`Orders::fulfill`) | — | M |
| DEL-05 | Driver end-of-day settlement | MISSING | Cash sessions are per counter register | — | M |
| DEL-06 | Vehicle master | MISSING | `customers.vehicle_no` is the customer's vehicle | — | S |
| DEL-07 | Route/area grouping, map link | MISSING | — | — | M |
| DEL-08 | Proof of delivery | MISSING | — | P2 | M |
| DEL-09 | Recurring orders | MISSING | — | P2 | M |

### 2.6 Reports and dashboards (RPT)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| RPT-01 | Daily closing: sales, cash, credit, cylinders out/in, stock | PARTIAL | `ReportService::periodReport` (sales, payments, credit, receipts, purchases, expenses, deposits, gas stock, filled/empty/custody counts); `Reports::index` | No cylinders-out/in movement counts for the day. Not a printable closing document | S |
| RPT-02 | Stock by product/state per branch; movement history with filters | PARTIAL | `Reports::inventoryDetail`, `Inventory::typeHistory`, `Inventory::adjustments` | Movement history is per type with limited filters (no date/source filter UI verified) | S |
| RPT-03 | Receivables/ageing + statements | PARTIAL | Customer ledger statements (SQA P11-25), OS on customer list | No ageing buckets | S |
| RPT-04 | Customer cylinder balance + deposits held | DONE | `Reports::custody` (`cylinder_custody` + deposits); `CustomerBalanceService::depositBalances` | — | — |
| RPT-05 | Price-compliance report | MISSING | — | Depends on BIL-01/03 | S |
| RPT-06 | Driver performance/shortage | MISSING | — | Depends on DEL-03…05 | S |
| RPT-07 | Profit/margin by product and period | MISSING | No cost/margin data (grep) | Needs costing method (Q-06) | M |
| RPT-08 | Owner dashboard | DONE | `DashboardService::forBranch`, date ranges, drill-downs (E2E-002/010, SQA P11-07…09); Branch Overview (`Reports::branches`) | — | — |
| RPT-09 | All reports export to Excel/PDF | PARTIAL | CSV for Branch Overview only | — | M |

### 2.7 SaaS operations (SAS)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| SAS-01 | Onboarding: tenant, owner user, default products/settings, sample data | PARTIAL | `BusinessManagement::saveBusiness` / API `Businesses::createBusiness` + `BusinessProvisioningService::seedMasterData` (6 types, 5 expense categories; SQA P9-07); shop provisioning (P9-35) | No one-step owner-user creation, no sample data, no default rates | M |
| SAS-02 | Super-admin panel: tenants, status, plan, usage, last activity, impersonation | PARTIAL | Business Management / Business Hierarchy screens for system admin | No plan, usage, last activity, impersonation | M |
| SAS-03 | Plans and limits | MISSING | — | — | M |
| SAS-04 | Trial, subscription status, warning → read-only → suspend, never delete | MISSING | `businesses.is_active` only | — | M |
| SAS-05 | Subscription invoicing for operator | MISSING | — | — | M |
| SAS-06 | Per-tenant branding: name, logo, receipt header/footer, currency, language | PARTIAL | Per-shop `receipt_title`, `receipt_footer`, colours, font (`ShopSettings`); business name in shell | No logo, currency or language. App name from env (`app.companyName`) is global | S |
| SAS-07 | Usage analytics | MISSING | — | P2 | M |

### 2.8 Localization and UX (LOC)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| LOC-01 | English + Urdu, RTL, all strings in language files | MISSING | `app/Language/en/Validation.php` only; `App::$supportedLocales=['en']`; strings hard-coded in views | The installed copy at `C:\PerfectLPG` has an Urdu/RTL login page that is **not in this repo** (Q-01) | L |
| LOC-02 | PKR default, number/date format, currency configurable | PARTIAL | `Rs.` hard-coded (115 occurrences in views); `number_format`; TZ `Asia/Karachi` | Currency not configurable; no locale-aware formatting | M |
| LOC-03 | Urdu in PDF and thermal | MISSING | — | Depends on LOC-01, BIL-05 | M |
| LOC-04 | Fast keyboard cashier screens, minimal clicks for swap-and-sell | PARTIAL | POS shortcuts F2/F4/F7/F9, quick customer search (SQA P11-11/12, `checks/pos_check.mjs`) | The swap-and-sell transaction itself does not exist (CORE-06) | S |
| LOC-05 | Accessibility basics, small screens | DONE | axe-core WCAG 2.1 A/AA 0 violations (SQA P11-32); 390 px with no sideways scroll (P11-01, `checks/shell_check.mjs`) | Re-verify after Urdu/RTL | — |

### 2.9 Marketing-company module and India (EXT)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| EXT-01 | Marketing company oversees distributor tenants | MISSING | The business → branches hierarchy is *inside* one tenant | P2 | L |
| EXT-02 | India variant | MISSING | — | Out of scope | — |
| EXT-03 | Hardware integrations | MISSING | — | P2 | L |

### 2.10 Non-functional (NFR)

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort |
|---|---|---|---|---|---|
| NFR-01 | Tenant isolation proven by automated tests, web + API | PARTIAL | `tests/unit/ShopScopeServiceTest.php`; E2E-027 (cross-shop/business API); SQA P9-08 (business B IDs rejected), P9-17, P9-22, P9-28/29/30, P9-36 | No systematic suite that runs every web route and API endpoint as tenant A against tenant B's IDs. Coverage is per feature | M |
| NFR-02 | OWASP Top 10 review | PARTIAL | Review done for this audit (see §4) | Findings open | M |
| NFR-03 | Critical logic covered by automated tests | PARTIAL | Shell suites cover posting, stock, pricing, credit, voids, rounding (`verify_business_flow`, `verify_rates`, `verify_phase8`), run in CI `business-logic-suites` | PHPUnit has none of this. All coverage is black-box against a live DB | M |
| NFR-04 | Acceptable on slow mobile; list endpoints paginated | PARTIAL | Local assets | No server-side pagination on web lists or API lists (see MOB-09). Not measured on a realistic dataset | M |
| NFR-05 | Personal data protected; encrypted backups; retention/deletion policy | MISSING | — | Backups are plain SQL in `writable/backups`. `shop_settings.smtp_password` stored in plaintext. No retention or deletion policy | M |
| NFR-06 | README, setup guide, `.env.example`, OpenAPI, runbook | PARTIAL | `README.md`, `docs/first-install-setup.md`, `env` template, `docs/release-runbook.md`, prose API contract (`docs/mobile-multishop/11-mobile-api-contract.md`) | No OpenAPI spec. Template is named `env`, not `.env.example`. README is POS-centric, not SaaS | M |

## 3. Top risks for the pilots

1. **Tenant isolation depends on discipline, not structure (PLT-02, NFR-01).** Every query hand-adds a branch or business filter, scope comes from the session in many places, and "global" rate cards fall back across tenants. Past fixes (SQA P9-28/29/30, P11-23) show such leaks do happen. With many paying tenants in one database, one missed filter is a data breach.
2. **The core sale model may not match the target market (CORE-06, BIL-01, BIL-03).** The app sells gas by KG and cylinders outright. LPGSASS describes distributors doing full-for-empty swaps with customer cylinder balances, priced against the OGRA notified maximum. If pilots work the LPGSASS way, the main POS flow needs a new transaction type. This must be settled before backlog sizing (Q-03, Q-07).
3. **Privilege escalation and weak defaults (PLT-06, NFR-02).** `Users::save` lets any USER_MANAGE holder grant any role. Roles are global across tenants. The seeded `admin/admin123` and migration-030 `pak` accounts have known passwords, and re-running 030 resets `pak`'s password. API login has no throttling.
4. **No reversible migrations, no scheduled or encrypted backups (Rule 6, PLT-03, PLT-09, NFR-05).** Every schema change is forward-only SQL. Moving existing customer data into tenants (PLT-03) cannot be undone except by a full restore. Backups depend on someone clicking a button.
5. **Pakistan-market P0 features missing (LOC-01/03, BIL-05/06/08, DEL-01…05).** There is no Urdu, PDF/58 mm/share receipt, tax handling, distinct JazzCash/Easypaisa methods, or delivery and driver workflow. These are visible to pilot customers from day one.

## 4. Security findings (NFR-02)

| # | OWASP area | Finding | Evidence | Severity |
|---|---|---|---|---|
| S-01 | A01 Access control | No restriction on which role a user manager may assign, so self-escalation to ADMIN is possible | `app/Controllers/Users.php` `save()`: `role_id` taken from POST | High |
| S-02 | A01 Access control | Tenant scoping is per query, not central; global `rate_cards` fallback across tenants | `GasRateModel::effectiveRow`; see PLT-02 | High (SaaS) |
| S-03 | A07 Auth | Known default credentials seeded: `admin`/`admin123` (schema) and `pak` (migration 030, password reset on every re-run via `ON DUPLICATE KEY UPDATE password_hash`) | `database/schema.sql` users insert; `030_…sql:30-41` | High |
| S-04 | A07 Auth | No rate limiting on API login, refresh or signup (web login is throttled) | `Api/V1/Auth.php` (no throttle); `Auth.php` throttle only | Medium |
| S-05 | A07 Auth | Web user passwords have no minimum length (API uses `password_min_length`) | `Users::save`; comment in `Auth.php` about initial password `'1'` | Medium |
| S-06 | A05 Misconfiguration | `Cookie::$secure=false`, secure headers and CSP disabled, `forceGlobalSecureRequests=false` | `app/Config/Cookie.php:57`, `Filters.php` globals, `App.php:160,201` | Medium (env-dependent) |
| S-07 | A04/A09 | Raw exception messages flashed to users (39 places); audit-log write failures silently swallowed | `grep "getMessage()" app/Controllers`; `AuditService.php` | Low–Medium |
| S-08 | A02 Crypto / data | SMTP password stored in plaintext in `shop_settings.smtp_password`; backups unencrypted | `ShopSettingsModel`, `DatabaseBackupService` | Medium |
| S-09 | A08 Integrity | Audit log can be truncated by the transactional refresh tool, and sale voids and user changes are not audited | `TransactionalDataRefreshService.php:19`; PLT-07 | Medium |
| S-10 | A05 / file upload | Backup restore accepts a ≤512 MB SQL upload and executes it (system admin only); Excel imports parse uploaded zips | `DatabaseBackup::restore`, `ExcelInventoryService` | Low (admin only), but needs review in SaaS |
| S-11 | A01 | `database-backup/*`, `migrations/run`, `installation/*` routes are outside the `auth` filter. Each self-guards (system admin / env flag), but they skip the disabled-user and migration-gate checks | `Config/Routes.php` line 4 | Low |

Positive controls verified: query builder or bound parameters throughout (no string-concatenated SQL found in reviewed paths), `esc()` in views, global CSRF on web, bcrypt passwords, session regeneration on login, web login throttling (SQA P8-10), protected migration endpoint (P8-06/07), API tokens hashed at rest with rotation and reuse detection (E2E-025).

**Committed secrets:** none found. No `.env` is tracked or in history. The `env` template has only local `root` / empty-password placeholders. Committed bcrypt hashes for `admin` and `pak` are known-password defaults, not secrets, but are listed as S-03.

## 5. Extra functionality not in LPGSASS (human decides whether to keep)

- Four POS transaction types with per-branch visibility and default (`gas_sale`, `cylinder_sale`, `security_deposit`, `cylinder_return`).
- Per-physical-cylinder gas weights, automatic gas allocation across cylinders, partly-used cylinders, gas wastage policies and logging (`InventoryControlService`).
- Opening inventory with Excel import; customer Excel import.
- Customer-specific rates (`customer_rates`) and pro-rata pricing of partly filled cylinders.
- Configurable back-dating policy (`TransactionDatePolicy`), money-rounding modes, shared vs branch supplier balances (D9-4).
- Three-level configuration system (application → business → shop) with an allow-list (`ConfigurationService`).
- Customer self-signup with staff approval, customer app accounts, mobile catalog and per-shop availability, price-reconfirmation on orders.
- Head Office (business-level) users and Branch Overview / Business Hierarchy screens.
- In-app database backup with email and Google Drive upload, restore, installation wizard, migration runner on the login page, destructive transactional-data refresh.
- Branch setup readiness checklist, theming (light/dark, colours, fonts).
- Dedicated `PAK_OPS` role and `pak` user (migration 030), apparently customer-specific.

## 6. Open questions

See [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md). The owner answered Q-01, Q-02, Q-03 and Q-07 on 2026-10-24. Effect on this audit:

- **LOC-01 / LOC-03** stay MISSING *in this repo*, but `C:\data\lpg-management-system` already has a tested English/Urdu/RTL layer (`app/Libraries/UiLanguage.php`, `app/Language/ur/app/*.php`, `tests/unit/UiLanguageTest.php`) that can be ported.
- **BIL-01 / BIL-02** stay DIVERGENT / PARTIAL. The owner wants the OGRA maximum stored separately and flexible retail / wholesale / customer-wise selling rates. The other repo's `RateResolver` (price lists + customer special rates + POS rate-change policy) is the reference design.
- **CORE-06** stays DIVERGENT. Swaps happen in practice alongside refill-by-KG and outright Cylinder Sale, so a swap transaction must be added.
- **PLT-03** effort drops to S: nothing is live, so no real data needs migrating.
