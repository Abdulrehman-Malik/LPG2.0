# BACKLOG — LPG SaaS (Gate B)

Built from [AUDIT.md](AUDIT.md) and the owner's answers in [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md) (2026-10-24).
Every PARTIAL / MISSING / DIVERGENT requirement maps to at least one item (see §Coverage at the end). P2 requirements are parked until the owner asks.

**Sizes:** S ≤ 1 day, M = 1–2 days. Nothing is L: anything bigger has been split.
**Order:** execute in number order unless a dependency says otherwise. Tracks A–E are the foundation (LPGSASS §6 ordering rule 1), F–H are money and stock (rule 2), I–L are delivery, receipts, localization, reports and SaaS ops (rule 3), and M is P1 (rule 4).
**Every item:** branch `bl-NNN-short-title`, reversible migration if schema changes (BL-001 provides the mechanism), tests per the item, full suite green (PHPUnit + Playwright + `run_all.sh` on `lpg_qa`), docs updated, then **Gate C**.
**Decisions:** the owner delegated the open questions on 2026-10-24. The choices and their settings are in [OPEN_QUESTIONS.md § Decisions](OPEN_QUESTIONS.md), and those choices replace any "*Depends on Q-…*" below except the mPDF licence (BL-066).
**Configurability rule:** every workflow choice an item introduces is a `ConfigurationService` setting (application → business → shop where it makes sense). Its default keeps today's behaviour; for new behaviour, the default is the safest option. Settings are listed in the item and appear on the Configuration screen.

**Gate B:** approved 2026-10-24 ("keep the implementation what you find better … make things configurable").

## Summary

| Track | Items | Priority | Theme |
|---|---|---|---|
| A | BL-001…006 | P0 | Safety: reversible migrations, security fixes |
| B | BL-007…013 | P0 | Tenant isolation |
| C | BL-014…016 | P0 | Roles, audit log |
| D | BL-017…027 | P0 | Logic into services; API coverage |
| E | BL-028…030 | P0 | Offline readiness (UUID, idempotency, sync columns) |
| F | BL-031…039 | P0 | Stock and sale model (swap, states, ledger) |
| G | BL-040…047 | P0 | Pricing: OGRA cap, price lists, customer rates |
| H | BL-048…051 | P0 | Billing and credit |
| I | BL-052…058 | P0 | Orders and delivery |
| J | BL-059…066 | P0 | Receipts and Urdu |
| K | BL-067…068 | P0 | Reports |
| L | BL-069…075 | P0 | SaaS operations and platform |
| M | BL-076…099 | P1 | After pilots start |
| — | parked | P2 | PLT-11, MOB-07, MOB-08, CORE-09, BIL-11, BIL-12, DEL-08, DEL-09, SAS-07, EXT-01…03 |

---

## Track A — Safety and foundations (P0)

### BL-001 — Reversible migrations alongside the SQL runner
Requirement(s): PLT-03, LPGSASS Rule 6
Priority: P0 · Size: M · Depends on: —
Problem: All 44 migrations are forward-only SQL (`database/migrations/*.sql`, `MigrationRunnerService`). No `down()`, and `app/Database/Migrations` is empty.
Scope: New schema changes become CI4 migration classes (`up()` / `down()`) in `app/Database/Migrations`. `MigrationRunnerService::status()/runPending()` and the login gate cover both the legacy SQL files and CI4 migrations. Add `tests/qa/verify_migrations.sh`: up → down → up on `lpg_qa`, comparing the schema dump before and after.
Out of scope: Converting the 44 existing SQL files (they stay; nothing is live).
Acceptance criteria:
  - [ ] A sample CI4 migration appears as pending on the login gate and runs from it.
  - [ ] `php spark migrate:rollback` reverts it, and the schema dump equals the pre-migration dump.
  - [ ] `verify_migrations.sh` passes and runs in CI.
  - [ ] `database/migrations/README.md` documents the rule: new changes are CI4 migrations with `down()`.
Tests to add: `verify_migrations.sh`; PHPUnit for gate status with mixed sources.
Migration: yes (sample, reversible).
Risks: The gate must not unblock while a CI4 migration is pending.

### BL-002 — Users can only grant roles within their own permissions
Requirement(s): PLT-06, NFR-02 (S-01)
Priority: P0 · Size: S · Depends on: —
Problem: `Users::save` takes `role_id` from POST unchecked, so a USER_MANAGE holder can make anyone, including themselves, ADMIN.
Scope: Add `PermissionService::canGrantRole(actorId, roleId)`: allowed only if the role's permissions are a subset of the actor's, or the actor is a system admin. Block self role change. Apply to web `Users::save` and any API equivalent.
Out of scope: The role redesign (BL-014).
Acceptance criteria:
  - [ ] A MANAGER with USER_MANAGE cannot grant ADMIN (403 + message); the role is unchanged in the DB.
  - [ ] No user can change their own role.
  - [ ] A system admin can grant any role.
Tests to add: shell suite case (HTTP POST) + PHPUnit for `canGrantRole`.
Migration: no. · Risks: low.

### BL-003 — No usable default credentials
Requirement(s): NFR-02 (S-03), PLT-08
Priority: P0 · Size: S · Depends on: BL-001
Problem: `schema.sql` seeds `admin/admin123`. Migration 030 seeds `pak` and resets its password on every re-run.
Scope: Add a `users.must_change_password` flag (reversible migration), set for seeded accounts. Login forces a password change before anything else. The installer already asks for an admin password and clears the flag. Disable `pak` unless Q-11 says keep. Add a check that warns a system admin if any active account still has a default hash.
Out of scope: Removing migration 030 (applied files cannot change).
Acceptance criteria:
  - [ ] Logging in as `admin/admin123` on a fresh schema redirects to a change-password page; no other page is reachable until it is changed.
  - [ ] Re-running migration 030 no longer re-enables a known password (account stays disabled or flagged).
  - [ ] Playwright and QA fixtures updated so suites still pass.
Tests to add: Playwright login case; shell check.
Migration: yes, reversible. · Risks: QA fixtures use `admin123`; adjust them.

### BL-004 — Throttle API auth; minimum password length on web
Requirement(s): NFR-02 (S-04, S-05), PLT-05
Priority: P0 · Size: S · Depends on: —
Problem: `/api/v1/*/auth/login|refresh|signup` have no throttling. Web user passwords have no minimum length.
Scope: Reuse the web throttle pattern (`Auth.php`) in a shared `LoginThrottle` service for staff/customer API login, refresh and signup (per IP + login), returning 429 with `Retry-After`. Enforce `password_min_length` in `Users::save`.
Acceptance criteria:
  - [ ] The 6th failed API login for the same login/IP within 15 min returns 429; the correct password is also refused until the window ends.
  - [ ] Web user save with a password shorter than the configured minimum is rejected.
Tests to add: E2E API case; shell case.
Migration: no. · Risks: QA suites that log in many times must clear the throttle (`qa_clear_throttle` exists).

### BL-005 — Secure transport and headers by configuration
Requirement(s): NFR-02 (S-06, S-11), PLT-08
Priority: P0 · Size: S · Depends on: —
Problem: `Cookie::$secure=false`, secure headers and CSP are off, and backup/migration/installation routes skip `AuthFilter` checks.
Scope: Env-driven `cookie.secure`, `app.forceGlobalSecureRequests` (documented in `env`). Enable the `secureheaders` filter globally. Add an auth-lite filter on `database-backup/*` that applies the disabled-user check. Keep the login-page migration flow working.
Acceptance criteria:
  - [ ] With `cookie.secure=true` in env, the session cookie has the `Secure` flag; the default dev setup still works on http.
  - [ ] Responses carry `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
  - [ ] A disabled system admin's existing session cannot reach `database-backup`.
Tests to add: curl header checks in a shell suite.
Migration: no. · Risks: none on http dev.

### BL-006 — Safe error messages and structured logs
Requirement(s): PLT-10, NFR-02 (S-07)
Priority: P0 · Size: S · Depends on: —
Problem: 39 `catch` blocks flash `$e->getMessage()`, so DB errors can reach users. Logs are plain text with no request or tenant context.
Scope: Add a `UserFacingException` type (business errors are already `RuntimeException`; mark them explicitly). Add a helper that shows the message for user-facing errors, and otherwise shows "Something went wrong (ref XXXX)" and logs the detail. Add a request-ID filter. The log line format becomes JSON with request_id, user_id, business_id and branch_id.
Acceptance criteria:
  - [ ] A forced DB exception on a POST shows the generic message with a reference; the log contains the SQL error under that reference.
  - [ ] Business validation messages (e.g. credit limit) still show unchanged; existing suites pass.
Tests to add: PHPUnit for the helper; shell case with a forced error command (test-only).
Migration: no. · Risks: Hiding a message users rely on; mitigated by the explicit exception type.

## Track B — Tenant isolation (P0)

### BL-007 — Tenant isolation test harness (baseline)
Requirement(s): NFR-01, PLT-02
Priority: P0 · Size: M · Depends on: —
Problem: Isolation is tested per feature (SQA P9-08/17/28…30, E2E-027), not systematically.
Scope: A fixture with two businesses (A, B), each with branches, customers, sales, stock, users and API tokens. Add `tests/qa/verify_isolation.sh` plus a Node runner that (1) enumerates every GET web route from `php spark routes` and calls each with B's IDs while logged in as A, (2) calls every staff/customer API endpoint with B's IDs using A's token, (3) POSTs to mutating routes with B's IDs. The pass rule is 403/404/redirect and none of B's marker strings in the body.
Out of scope: Fixing findings (each becomes its own item).
Acceptance criteria:
  - [ ] Harness covers 100% of routes listed by `spark routes` (report shows the count).
  - [ ] Runs in CI `business-logic-suites`.
  - [ ] Any current leak is reported as a new backlog item (expected: rate-card fallback, BL-012).
Tests to add: this harness.
Migration: no. · Risks: Mutating routes need careful fixtures; run on `lpg_qa` only.

### BL-008 — One TenantContext for web and API
Requirement(s): PLT-02
Priority: P0 · Size: M · Depends on: BL-007
Problem: Scope is read from `session('location_id')` in controllers, models (`CustomerModel::activeDirectory`, `SupplierModel::activeDirectory`) and views. The API resolves scope separately.
Scope: Add a `TenantContext` service (business_id, branch_id, user_id, source web|api), set by `AuthFilter` and `ApiAuthFilter`. It fails closed when unset. Replace direct session reads in models and services with it.
Acceptance criteria:
  - [ ] `grep "session()->get('location_id')" app/Models app/Services` returns 0 hits.
  - [ ] Calling a scoped model method with no context throws.
  - [ ] Isolation harness and all suites pass.
Tests to add: PHPUnit for TenantContext; isolation harness re-run.
Migration: no. · Risks: Wide but mechanical change; covered by suites.

### BL-009 — `business_id` on branch-owned header tables
Requirement(s): PLT-02
Priority: P0 · Size: M · Depends on: BL-001, BL-008
Problem: Transaction tables carry only `location_id`, so the tenant is implicit.
Scope: Reversible migration adds `business_id` (NOT NULL after backfill) to customers, sales, purchases, customer_receipts, supplier_payments, expenses, cash_registers, cylinder_units, inventory_movements, inventory_opening_balances, cylinder_custody, customer_security_deposits, rate_cards, audit_logs, document_sequences, shop_settings. Each gets a composite FK `(location_id, business_id) → locations(id, business_id)` (unique key already exists) and a `BEFORE INSERT` trigger that fills `business_id` from `locations`, so no service has to change.
Acceptance criteria:
  - [ ] An insert whose business_id disagrees with its location is rejected by the FK.
  - [ ] Existing posting paths work unchanged (all suites green).
  - [ ] `down()` drops the columns, FKs and triggers cleanly (verify_migrations).
Tests to add: SQL assertion script; verify_migrations.
Migration: yes, reversible. · Risks: Trigger behaviour on MariaDB vs MySQL 8; test both if MariaDB stays supported.

### BL-010 — `business_id` on child/line tables
Requirement(s): PLT-02
Priority: P0 · Size: M · Depends on: BL-009
Problem: sale_items, sale_payments, purchase_items, purchase_payments, cash_sessions, cash_transactions, stock_transfer_items, customer_rates, customer_app_accounts and api_sessions are scoped only through their parent.
Scope: Same pattern as BL-009, with the trigger copying the parent's business_id and a composite FK to the parent where it has `(id, business_id)` unique.
Acceptance criteria: as BL-009, for these tables.
Tests to add: same. · Migration: yes, reversible. · Risks: as BL-009.

### BL-011 — Tenant-scoped base model
Requirement(s): PLT-02, NFR-01
Priority: P0 · Size: M · Depends on: BL-008, BL-009
Problem: A forgotten `where` leaks data.
Scope: `TenantModel extends Model` adds `business_id = TenantContext::businessId()` (and branch where the table is branch-owned) to every find/findAll/update/delete through a CI4 model event or builder hook, with an explicit `withoutTenantScope()` escape for system-admin code. Move CustomerModel, SupplierModel, CylinderTypeModel, RateCardModel and InventoryOpeningBalanceModel onto it. Raw `->table()` queries in services are not covered; document that and flag them in the code-health list.
Acceptance criteria:
  - [ ] `CustomerModel::find(<B's id>)` in A's context returns null.
  - [ ] Unscoped use requires `withoutTenantScope()` and is grep-able.
Tests to add: PHPUnit (MySQL test DB) per model; isolation harness.
Migration: no. · Risks: Hidden reliance on cross-branch reads in Head Office screens; use an explicit business-level scope there.

### BL-012 — Remove cross-tenant rate fallback; tenant-unique codes
Requirement(s): PLT-02, NFR-02 (S-02)
Priority: P0 · Size: S · Depends on: BL-009
Problem: `GasRateModel::effectiveRow` falls back to `rate_cards.location_id IS NULL` for every business. `locations.code` is unique platform-wide.
Scope: The fallback becomes business-level (`business_id` set, `location_id` NULL), and existing NULL rows go to the owning (DEFAULT) business. Change the `locations.code` unique key to `(business_id, code)`.
Acceptance criteria:
  - [ ] A global rate created in business A is never used by business B's POS (isolation harness case).
  - [ ] Two businesses can both have a branch coded `MAIN`.
Tests to add: harness case; verify_rates still green.
Migration: yes, reversible. · Risks: Superseded in part by BL-041; keep the change small.

### BL-013 — Default tenant for unassigned branches
Requirement(s): PLT-03
Priority: P0 · Size: S · Depends on: BL-001
Problem: Migration 032 leaves legacy `locations.business_id` NULL. Nothing is live (Q-02), but test DBs and old dumps can hit this.
Scope: Reversible migration assigns NULL-business branches (and their users' business access) to `DEFAULT`, recording the assigned IDs so `down()` restores NULL. Then makes `locations.business_id` NOT NULL.
Acceptance criteria:
  - [ ] On a DB built from the pre-032 fixture, every branch belongs to DEFAULT after migrating, and rollback restores the prior state.
Tests to add: verify_migrations case with a legacy fixture.
Migration: yes, reversible. · Risks: low.

## Track C — Roles and audit (P0)

### BL-014 — Standard roles for the SaaS
Requirement(s): PLT-06
Priority: P0 · Size: M · Depends on: BL-002; **Q-11** (per-tenant vs fixed roles)
Problem: Roles are ADMIN, MANAGER, CASHIER and PAK_OPS. There is no owner, driver or accountant, and roles are global.
Scope (assuming fixed platform roles until Q-11 says otherwise): seed OWNER (rename of ADMIN within a business), MANAGER, CASHIER, DRIVER and ACCOUNTANT with a documented permission matrix (`docs/roles.md`). Super admin stays `is_system_admin`. Add permissions DELIVERY_VIEW_OWN, DELIVERY_RECORD, SETTLEMENT_MANAGE for track I. Web and API enforce the same codes.
Acceptance criteria:
  - [ ] Each role signs in and sees exactly the menu entries in the matrix (shell_check per role).
  - [ ] DRIVER gets 403 on POS, purchases and reports; ACCOUNTANT is read-only on stock but can view ledgers and reports.
Tests to add: per-role page crawl in shell_check.
Migration: yes, reversible (seed rows). · Risks: Renaming ADMIN must not break existing fixtures; keep the code `ADMIN` and change only the label if simpler.

### BL-015 — Audit every sensitive action
Requirement(s): PLT-07, BIL-09
Priority: P0 · Size: M · Depends on: —
Problem: Not audit-logged: sale void, user create/update/disable, role-permission edits, customer edits (incl. credit limit), customer receipts, supplier payments and rate changes. `ShopSettings` inserts into `audit_logs` directly.
Scope: Call `AuditService::log` with before/after for each of these, and route ShopSettings through AuditService. Add filters on the Audit screen for action and entity.
Acceptance criteria:
  - [ ] Each listed action produces one `audit_logs` row with user, time, IP and before/after JSON (shell suite checks each).
  - [ ] The Audit screen filters by action.
Tests to add: shell suite `verify_audit.sh`.
Migration: no. · Risks: low.

### BL-016 — Audit log integrity
Requirement(s): PLT-07, NFR-02 (S-09, S-10); **Q-13**
Priority: P0 · Size: S · Depends on: BL-015
Problem: `TransactionalDataRefreshService` truncates `audit_logs`. Audit write failures are swallowed. Refresh and restore act on all tenants.
Scope: Refresh never touches `audit_logs` and is disabled when `CI_ENVIRONMENT=production`. Inside posting transactions, an audit failure rolls back the posting. Database restore is limited to the system admin and blocked in production unless an env flag is set (single-tenant restore is not supported; documented).
Acceptance criteria:
  - [ ] The refresh endpoint returns 403 in production mode, and audit rows survive a refresh in development.
  - [ ] A forced audit insert failure during sale void leaves the sale posted (not voided).
Tests to add: shell cases.
Migration: no. · Risks: low.

## Track D — Logic into services; API coverage (P0)

### BL-017 — Extract receipt, supplier-payment and expense posting into services
Requirement(s): PLT-04
Priority: P0 · Size: M · Depends on: —
Problem: `Receipts::save`, `SupplierPayments::save` and `Expenses::save` hold transactions, balance checks and cash postings inline.
Scope: Add `CustomerReceiptService`, `SupplierPaymentService` and `ExpenseService`, moving the code unchanged. Controllers become thin.
Acceptance criteria:
  - [ ] Controllers contain no `transBegin` or `->table(` calls.
  - [ ] parties_check, cash_check and verify_business_flow pass unchanged.
Tests to add: none new (behaviour unchanged); PHPUnit smoke per service.
Migration: no. · Risks: low.

### BL-018 — Extract report queries; no DB access in views
Requirement(s): PLT-04
Priority: P0 · Size: M · Depends on: —
Problem: `Reports::index/custody/inventoryDetail/ledger` build SQL in the controller. `layouts/app.php:8-55` queries the DB on every page. Several other views instantiate services.
Scope: Move the queries into `ReportService` / `LedgerService`. Add a `LayoutData` provider called from a base controller and filter to supply the shell data.
Acceptance criteria:
  - [ ] `grep -E "Database::connect|->table\(|new \\\\App\\\\(Services|Models)" app/Views` returns 0 hits.
  - [ ] verify_pages and verify_hq pass; report numbers are unchanged vs SQL.
Tests to add: none new.
Migration: no. · Risks: low.

### BL-019 — API conventions: pagination, errors, idempotency
Requirement(s): PLT-04, MOB-02, NFR-04
Priority: P0 · Size: M · Depends on: BL-008
Problem: API lists are unpaginated and there is no shared idempotency mechanism (orders have their own).
Scope: A shared `ApiController` base with a `{data, meta:{page,per_page,total,next_cursor}}` envelope, a standard error body, `X-Request-Id`, and an `Idempotency-Key` header handled by the `api_idempotency_keys` table (key, principal, route, fingerprint, response) for all POST creates. Retrofit existing staff/customer list endpoints with pagination.
Acceptance criteria:
  - [ ] Every list endpoint accepts `page`/`per_page` (max 100) and returns `meta`.
  - [ ] A repeated POST with the same key and body returns the stored response; a different body returns 409.
Tests to add: E2E API cases.
Migration: yes, reversible (table). · Risks: The mobile skeleton client must follow the new envelope (update `lpg_mobile_core`).

### BL-020 — OpenAPI spec for /api/v1
Requirement(s): NFR-06, PLT-04
Priority: P0 · Size: S · Depends on: BL-019
Scope: `docs/api/openapi.yaml` covering existing endpoints, validated in CI (spectral or a schema check). Every later API item updates it.
Acceptance criteria:
  - [ ] The spec validates in CI, and each route in `spark routes` under `api/v1` is present.
Tests to add: CI check comparing the routes list with spec paths.
Migration: no.

### BL-021 — API: customers, balances, ledger
Requirement(s): PLT-04
Priority: P0 · Size: M · Depends on: BL-011, BL-019
Scope: `GET/POST/PUT /staff/customers`, `GET /staff/customers/{id}/balance`, `GET /staff/customers/{id}/ledger`, using `CustomerModel`, `CustomerBalanceService` and `LedgerService`, with permission CUSTOMER_MANAGE.
Acceptance criteria:
  - [ ] Results equal the web screens for the same fixture (parties fixture).
  - [ ] Isolation harness covers the new routes.
Tests to add: E2E API + harness.
Migration: no.

### BL-022 — API: POS sales post, list, detail, void
Requirement(s): PLT-04
Priority: P0 · Size: M · Depends on: BL-019
Scope: `POST /staff/sales` (all transaction types via `SalesService::post`, Idempotency-Key required), `GET /staff/sales` (filters as Sale History), `GET /staff/sales/{id}` (lines, payments, movements, receipt data), `POST /staff/sales/{id}/void` (POS_VOID).
Acceptance criteria:
  - [ ] A gas sale posted through the API creates the same rows as the POS for identical input (SQL comparison).
  - [ ] A replay with the same key returns the same sale_no.
  - [ ] Credit-limit and walk-in rules are enforced identically.
Tests to add: E2E API cases mirroring E2E-004/007/014.
Migration: no.

### BL-023 — API: cash sessions
Requirement(s): PLT-04
Priority: P0 · Size: S · Depends on: BL-019
Scope: open, current summary, manual move, close and history, via `CashService`.
Acceptance criteria:
  - [ ] Figures equal the Counter Cash screen (cash_check fixture).
Tests to add: E2E API. · Migration: no.

### BL-024 — API: inventory and transfers
Requirement(s): PLT-04
Priority: P0 · Size: M · Depends on: BL-019
Scope: Stock by type and state, physical units, movement history (paginated), adjustments, and transfers (create, receive, cancel), via `InventoryService`, `CylinderUnitService` and `StockTransferService`.
Acceptance criteria:
  - [ ] Stock totals equal the Inventory Detail report.
  - [ ] Transfer flows match verify_transfers outcomes.
Tests to add: E2E API. · Migration: no.

### BL-025 — API: purchases, suppliers, supplier payments
Requirement(s): PLT-04, CORE-11
Priority: P0 · Size: M · Depends on: BL-017, BL-019
Scope: Supplier CRUD and ledger; purchase post, list and void; supplier payment post.
Acceptance criteria:
  - [ ] Payables equal `SupplierBalanceService` results for the business-flow fixture.
Tests to add: E2E API. · Migration: no.

### BL-026 — API: receipts and expenses
Requirement(s): PLT-04, BIL-10
Priority: P0 · Size: S · Depends on: BL-017, BL-019
Scope: Customer receipt post and list; expense post and list; expense categories list.
Acceptance criteria:
  - [ ] Posting through the API changes the customer OS and cash session exactly like the web.
Tests to add: E2E API. · Migration: no.

### BL-027 — API: dashboard and reports
Requirement(s): PLT-04, RPT-08
Priority: P0 · Size: S · Depends on: BL-018, BL-019
Scope: Dashboard figures, period report, custody report and branch overview, read-only with REPORT_VIEW.
Acceptance criteria:
  - [ ] Values equal the web pages for the verify_hq fixture.
Tests to add: E2E API. · Migration: no.

## Track E — Offline readiness (P0)

### BL-028 — Client-generated UUIDs
Requirement(s): MOB-01
Priority: P0 · Size: M · Depends on: BL-001, BL-019
Scope: Add a `uuid CHAR(36) UNIQUE` column (backfilled) to sales, sale_payments, customer_receipts, customers, expenses, inventory_movements, customer_orders, cash_transactions, stock_transfers and cylinder_custody. API creates accept a client UUID (v4/v7 validated); the server generates one when absent. The API exposes `uuid` alongside `id`.
Acceptance criteria:
  - [ ] Creating a sale with a client UUID stores it; GET by UUID works.
  - [ ] An invalid UUID returns 422.
Tests to add: E2E API; verify_migrations.
Migration: yes, reversible. · Risks: Backfill time on large tables (nothing live).

### BL-029 — Idempotent creates by UUID
Requirement(s): MOB-02
Priority: P0 · Size: S · Depends on: BL-028
Scope: Creates for sales, receipts, payments, expenses and stock movements treat a duplicate UUID from the same principal as a replay and return the original. A different payload with the same UUID returns 409.
Acceptance criteria:
  - [ ] 10 concurrent identical sale POSTs (same UUID) create exactly one sale and one stock deduction (pattern of E2E-030).
Tests to add: E2E concurrency case.
Migration: no.

### BL-030 — Sync columns and soft delete on masters
Requirement(s): MOB-03, MOB-05
Priority: P0 · Size: M · Depends on: BL-001
Scope: customers, suppliers, cylinder_types, expense_categories, price-list and rate tables, and business_catalog_products get `updated_at`, `deleted_at` and `version INT` (incremented on update, CI4 `useSoftDeletes`). Ledger tables are declared insert-only (documented; `created_at` + `uuid`), and a DB check script asserts no UPDATE/DELETE paths exist except the listed status fields.
Acceptance criteria:
  - [ ] Deleting a customer sets `deleted_at` and keeps the row; lists hide it.
  - [ ] Every update increments `version`.
  - [ ] `docs/sync-policy.md` lists every table as master (LWW + version) or ledger (append-only).
Tests to add: PHPUnit (MySQL) + verify_migrations.
Migration: yes, reversible. · Risks: Existing hard deletes (e.g. cylinder type delete) change meaning; review each.

## Track F — Stock and sale model (P0)

### BL-031 — Customer type, area, notes, blocked
Requirement(s): CORE-01
Priority: P0 · Size: S · Depends on: BL-001
Scope: Add to `customers`: `customer_type` (domestic / commercial / retailer_dealer), `area`, `notes`, `is_blocked` (blocks new sales, still visible) and `whatsapp_enabled`. Add them to the form, list filters and Excel import template.
Acceptance criteria:
  - [ ] A blocked customer cannot be selected for a POS sale (server-side rejection).
  - [ ] Customer type is filterable on the list.
Tests to add: shell + Playwright POS case.
Migration: yes, reversible.

### BL-032 — Cylinder type: deposit and gross weight; 45.4 kg
Requirement(s): CORE-02; **Q-14**
Priority: P0 · Size: S · Depends on: BL-001
Scope: Add `deposit_amount` to `cylinder_types` (default deposit used by Security Deposit and swap shortage); show gross = net + tare. Seed a 45.4 kg type for new businesses (per Q-14).
Acceptance criteria:
  - [ ] The Security Deposit screen pre-fills the type's deposit amount.
  - [ ] New businesses get the 11.8 and 45.4 kg types.
Tests to add: verify_phase9 provisioning case.
Migration: yes, reversible.

### BL-033 — Movement ledger with from/to state; no deletes
Requirement(s): CORE-04, MOB-05
Priority: P0 · Size: M · Depends on: BL-001
Problem: Movements record direction only. Unit status is changed in place. Opening-inventory edit/delete deletes movements (`InventoryOpening.php:250,718`).
Scope: Add `from_state` / `to_state` to `inventory_movements`, populated by every posting path (sale, purchase, deposit, return, transfer, adjustment, wastage, opening). Opening edit and delete become reversing movements plus new ones. Add a reconciliation check: each unit's status equals the `to_state` of its latest movement.
Acceptance criteria:
  - [ ] After the full `run_all.sh`, the reconciliation query returns 0 mismatches.
  - [ ] Deleting an opening record leaves its movements and adds reversals.
Tests to add: reconciliation assertion in verify_business_flow; opening case.
Migration: yes, reversible.

### BL-034 — Swap transaction (full out, empty in)
Requirement(s): CORE-06, LOC-04
Priority: P0 · Size: M · Depends on: BL-033
Scope: New POS transaction type `cylinder_swap`: per line, a cylinder type and the number of filled cylinders out and empties in (equal counts here). Company filled units move out (to `sold_to_customer` / exchange), the customer's empties come in as company empty stock, and the customer pays the gas price (resolver from BL-042, or the current filled-cylinder rate until then). Add it to shop-visible types, Sale History, receipt and void (with reversal).
Out of scope: Shortage handling (BL-035).
Acceptance criteria:
  - [ ] Swap 2 × 11.8 kg: filled −2, empty +2, sale total = 2 × filled-cylinder rate; the void restores both.
  - [ ] A swap with empties ≠ fulls is rejected until BL-035.
Tests to add: Playwright POS case; verify_business_flow swap section.
Migration: yes, reversible (ENUM value).

### BL-035 — Swap shortage handling
Requirement(s): CORE-06, CORE-07
Priority: P0 · Size: M · Depends on: BL-034; **Q-15**
Scope: Implement the rule chosen in Q-15 (cylinders owed, deposit charged, cylinder sold, or cashier choice). Cylinders owed go into a `customer_cylinder_balances` ledger (append-only) and are settled by later empties returned. Walk-in customers are handled per Q-15.
Acceptance criteria: defined once Q-15 is answered (testable per option).
Tests to add: verify_business_flow cases.
Migration: likely yes, reversible.

### BL-036 — Cylinder states: damaged, lost, at plant
Requirement(s): CORE-03
Priority: P0 · Size: M · Depends on: BL-033
Scope: Extend `cylinder_units.status` with `damaged`, `lost` and `at_plant` (and `on_vehicle`, used by BL-056). Stock adjustment gets reasons that move units into or out of these states with movements. The Inventory Detail report shows counts per state.
Acceptance criteria:
  - [ ] Marking an empty unit damaged moves it from empty to damaged; it is not sellable and is shown in the damaged column.
Tests to add: shell case.
Migration: yes, reversible.

### BL-037 — Per-customer deposit and cylinder statement
Requirement(s): CORE-07, RPT-04
Priority: P0 · Size: S · Depends on: BL-035
Scope: A customer statement tab: deposits held and refunded, cylinders in custody, and cylinders owed (from BL-035), with running balances. Printable.
Acceptance criteria:
  - [ ] The statement closing balances equal `CustomerBalanceService::depositBalances` and the custody count.
Tests to add: parties_check case.
Migration: no.

### BL-038 — Purchase supplier document number and empties returned
Requirement(s): CORE-05
Priority: P0 · Size: M · Depends on: BL-033
Scope: Add a `supplier_doc_no` to `purchases` (required, unique per supplier). Add an optional "empties returned to supplier" section on the purchase that moves empty units to `at_plant` / returned. Port the relevant part of `lpg-management-system` "refill purchases with plants" if Q-19 agrees.
Acceptance criteria:
  - [ ] Purchase with doc no. X and 3 empties returned: filled +N, empty −3, doc no. shown in history and the supplier ledger; a duplicate doc no. for the same supplier is rejected.
Tests to add: verify_business_flow.
Migration: yes, reversible.

### BL-039 — Partial sale return by reversing entry
Requirement(s): BIL-09
Priority: P0 · Size: M · Depends on: BL-033, BL-015
Problem: Only whole-invoice void exists.
Scope: A "Return" on a posted sale creates a return document (`sales` row type `return`, linked `original_sale_id`) reversing the selected lines and quantities: stock back, money refunded or credited to OS, audit logged. The original stays posted.
Acceptance criteria:
  - [ ] Returning 1 of 2 cylinders restores 1 to stock, reduces customer OS or refunds cash, and links both documents; the original is unchanged.
Tests to add: verify_business_flow.
Migration: yes, reversible.

## Track G — Pricing (P0)

### BL-040 — Regulated (OGRA) price master
Requirement(s): BIL-01; **Q-16**
Priority: P0 · Size: M · Depends on: BL-001
Scope: Table `regulated_prices` (scope platform or business per Q-16, price_per_kg, optional per cylinder size (11.8 / 45.4), effective_from, notification reference, entered_by). Maintenance screen for the operator (or tenant), history list (never edited, superseded by a new row), audit logged. Read API.
Acceptance criteria:
  - [ ] Entering October's notified price makes it effective from its date; history shows previous months.
  - [ ] A tenant user cannot edit platform prices (if Q-16 = operator).
Tests to add: shell + PHPUnit for effective lookup.
Migration: yes, reversible.

### BL-041 — Price lists (Retail, Wholesale, tenant-defined)
Requirement(s): BIL-02, CORE-01; Q-17
Priority: P0 · Size: M · Depends on: BL-009, BL-031
Scope: Table `price_lists` (business_id, code, name, is_default), with Retail and Wholesale seeded. `rate_cards` gets a `price_list_id` (existing rows → Retail). Rates are set per list at business level with an optional branch override. `customers.price_list_id` is nullable (= business default); walk-in uses a configured list. Rate types follow the other repo: gas_per_kg, gas_per_cylinder, empty_cylinder.
Acceptance criteria:
  - [ ] Existing rates appear under Retail after migration and POS totals are unchanged (verify_rates green).
  - [ ] A business can add a third list.
Tests to add: verify_rates migration case.
Migration: yes, reversible.

### BL-042 — Rate resolver (port of `RateResolver`)
Requirement(s): BIL-02
Priority: P0 · Size: M · Depends on: BL-041
Scope: Extend `RateService` with this precedence: customer special rate (existing `customer_rates`) → customer's price list → business default list → Retail fallback (for Wholesale) → none. Per-KG and per-cylinder convert into each other as in `lpg-management-system/app/Services/RateResolver.php`. Store `price_list_id` and `rate_source` on each sale line. All POS types use it.
Acceptance criteria:
  - [ ] A PHPUnit table test covers each precedence step and conversion.
  - [ ] A wholesale customer gets the wholesale price in POS; with no wholesale price set, Retail applies with `rate_source=retail_fallback`.
Tests to add: PHPUnit (pure, no DB) + rates_check.
Migration: yes (line columns), reversible.

### BL-043 — Rates screen: price-list grid and customer rates
Requirement(s): BIL-02
Priority: P0 · Size: M · Depends on: BL-041
Scope: A grid of cylinder types × lists (per KG, per full cylinder, empty price) with effective-from; a customer special rates tab (exists, adapted). No editing or deleting of history rows: a change creates a new effective row.
Acceptance criteria:
  - [ ] Saving a grid cell creates a new effective row and a `rate_change_log` entry; old rows are read-only.
Tests to add: rates_check update.
Migration: no.

### BL-044 — POS rate-change policy
Requirement(s): BIL-03
Priority: P0 · Size: S · Depends on: BL-042, BL-015
Scope: Shop setting `pos_rate_change_mode` = not_allowed / allowed / reason_required (default reason_required). The reason is stored on the sale and the change audited (pattern from the other repo's `assertRateChange`).
Acceptance criteria:
  - [ ] In `not_allowed` mode a changed rate is rejected server-side.
  - [ ] In `reason_required` mode, posting without a reason fails and posting with one stores the reason and an audit row.
Tests to add: pos_check cases.
Migration: yes (settings and sale column), reversible.

### BL-045 — Price-cap compliance check
Requirement(s): BIL-03, RPT-05 (data)
Priority: P0 · Size: M · Depends on: BL-040, BL-044
Scope: At posting, compare each gas line's effective per-KG price (and per-cylinder price for 11.8 / 45.4 when defined) with the regulated price in force. A tenant setting `price_cap_mode` = off / warn / block applies. Over-cap lines in warn mode need a reason (audited); block mode rejects them. Store `regulated_price_snapshot` and `over_cap` on the line. The POS shows the warning before submit.
Acceptance criteria:
  - [ ] Block mode: a line above the cap is rejected with a message naming the cap.
  - [ ] Warn mode: it posts only with a reason, and `over_cap=1` plus an audit row are recorded.
Tests to add: PHPUnit for the comparison; pos_check case.
Migration: yes, reversible.

### BL-046 — API: rates, price lists, regulated prices
Requirement(s): PLT-04
Priority: P0 · Size: S · Depends on: BL-019, BL-043
Scope: Read and write endpoints, plus a "resolve price" endpoint for a customer, type and time (used by mobile).
Acceptance criteria:
  - [ ] The resolve endpoint returns the same rate and source as the POS for each verify_rates case.
Tests to add: E2E API. · Migration: no.

### BL-047 — Rounding steps and suggested price from the notified price
Requirement(s): BIL-02
Priority: P0 · Size: S · Depends on: BL-040, BL-042
Scope: `money_rounding_mode` gains rounding to the nearest 1 / 5 / 10 rupees on the document total. The price grid shows a "suggested" price = notified per KG × net kg ± a configurable distributor margin (assumption: margin is a tenant setting in Rs per kg; confirm at Gate B). The delivery charge already exists (`delivery_fee`).
Acceptance criteria:
  - [ ] With rounding to 5, a total of 2,997.40 becomes 2,995.00 (rule documented: nearest).
  - [ ] Suggested prices update when a new notified price is entered.
Tests to add: PHPUnit for MoneyRounding.
Migration: no (settings via ConfigurationService).

## Track H — Billing and credit (P0)

### BL-048 — Invoice numbering choice and payment status
Requirement(s): BIL-04; **Q-05**
Priority: P0 · Size: S · Depends on: —
Scope: If Q-05 = continuous, a business setting `document_number_period` = day | month | never (period key in `document_sequences`). Add a derived `payment_status` (paid / partial / credit) stored on `sales` and shown on history and receipt.
Acceptance criteria:
  - [ ] With `never`, consecutive sales across days get consecutive numbers; concurrent posting stays gap-free (E2E-030 pattern).
Tests to add: shell + concurrency case.
Migration: yes, reversible.

### BL-049 — Payment methods: bank, JazzCash, Easypaisa
Requirement(s): BIL-08; **Q-10**
Priority: P0 · Size: S · Depends on: BL-001
Scope: Payment modes become cash, cheque, bank_transfer, jazzcash, easypaisa, credit (`online` kept as legacy, label "Online (other)"). A reference number is required for non-cash and non-credit modes. Reports group by mode.
Acceptance criteria:
  - [ ] A JazzCash payment without a reference is rejected; with one it posts and appears under JazzCash in Branch Overview.
Tests to add: pos_check, verify_hq.
Migration: yes (ENUMs on 5 tables), reversible.

### BL-050 — Receivables ageing
Requirement(s): BIL-07, RPT-03
Priority: P0 · Size: S · Depends on: —
Scope: Ageing report per branch and business: buckets 0–30 / 31–60 / 61–90 / 90+ days by unpaid credit sale date (oldest-first allocation, matching the existing OS allocation rule).
Acceptance criteria:
  - [ ] Bucket totals sum to the customer OS total from `CustomerBalanceService`.
Tests to add: parties_check case.
Migration: no.

### BL-051 — Configurable sales tax
Requirement(s): BIL-06; **Q-04 (blocked)**
Priority: P0 · Size: M · Depends on: Q-04
Scope: Business settings for tax enabled, rate, inclusive/exclusive and registration number. A tax amount per line and document, shown on the receipt and reports. No hard-coded rules.
Acceptance criteria: per Q-04 answer.
Tests to add: PHPUnit for calculation; receipt check.
Migration: yes, reversible.

## Track I — Orders and delivery (P0)

### BL-052 — Vehicles and drivers
Requirement(s): DEL-06, DEL-03
Priority: P0 · Size: S · Depends on: BL-014
Scope: Table `vehicles` (business, branch, number, capacity per type, default driver user, maintenance notes, active), CRUD screen, API list.
Acceptance criteria:
  - [ ] A vehicle can be created and assigned a DRIVER-role user; a non-driver user is rejected as driver.
Tests to add: shell. · Migration: yes, reversible.

### BL-053 — Staff order entry
Requirement(s): DEL-01
Priority: P0 · Size: M · Depends on: BL-031
Scope: Staff create an order for a customer (or a new walk-in name and phone) with channel (phone / WhatsApp / walk-in), items, address and requested time, reusing `customer_orders` and `CustomerOrderService` (`source=staff`). Web form plus API.
Acceptance criteria:
  - [ ] A staff-created order appears in the order queue with channel and requested time, and fulfils to POS exactly like an app order (E2E-028 path).
Tests to add: Playwright + E2E API. · Migration: yes (columns), reversible.

### BL-054 — Order status flow aligned to delivery
Requirement(s): DEL-02
Priority: P0 · Size: S · Depends on: BL-053
Scope: Status set new(=pending) → assigned → out_for_delivery → delivered (=completed) / failed (=unable_to_fulfil) / cancelled. Existing accepted / preparing / ready are kept as optional internal steps (documented mapping). Transitions are validated and history recorded.
Acceptance criteria:
  - [ ] An invalid transition (delivered → assigned) is rejected; every change writes history.
Tests to add: E2E. · Migration: yes (ENUM), reversible.

### BL-055 — Assign orders to driver and vehicle; driver's own list
Requirement(s): DEL-03
Priority: P0 · Size: M · Depends on: BL-052, BL-054
Scope: Assign / reassign on the order queue. A driver's web view and API `GET /staff/deliveries` show only orders assigned to them.
Acceptance criteria:
  - [ ] Driver A cannot see or open driver B's orders (web 404, API 404); the isolation harness is extended.
Tests to add: E2E + harness. · Migration: yes (columns), reversible.

### BL-056 — Vehicle load and unload (on_vehicle stock)
Requirement(s): DEL-04, CORE-03
Priority: P0 · Size: M · Depends on: BL-036, BL-052
Scope: Loading filled units onto a vehicle moves them to `on_vehicle` with movements; unloading moves them back. Stock reports show the on-vehicle column.
Acceptance criteria:
  - [ ] Loading 5 filled: branch filled −5, on vehicle +5; reconciliation (BL-033) holds.
Tests to add: shell. · Migration: no.

### BL-057 — Driver records the delivery
Requirement(s): DEL-04
Priority: P0 · Size: M · Depends on: BL-056, BL-034, BL-055
Scope: The driver marks an order delivered with cylinders delivered (from vehicle stock), empties collected (swap), payment collected (cash / credit / method + ref) and notes. It posts through `SalesService` (swap or gas type) with the driver as user, idempotent by UUID. Failed delivery requires a reason.
Acceptance criteria:
  - [ ] Delivering 2 swaps with 1 400 cash posts one sale, vehicle filled −2, vehicle empties +2, and cash attributed to the driver; a replay creates nothing.
Tests to add: E2E API (driver token).
Migration: maybe (driver cash attribution), reversible.

### BL-058 — Driver end-of-day settlement
Requirement(s): DEL-05
Priority: P0 · Size: M · Depends on: BL-057
Scope: A settlement document per driver per day: expected cash (sum collected), expected cylinders (loaded − delivered + empties collected) vs counted returns entered by the manager. Differences are flagged as shortages, cash goes to the branch cash session, and stock is unloaded. Shortages are audit logged.
Acceptance criteria:
  - [ ] Counted cash 100 below expected shows "Short Rs 100", is stored and audit logged; settled cash appears in the counter session.
Tests to add: shell. · Migration: yes, reversible.

## Track J — Receipts and Urdu (P0)

### BL-059 — Port the English/Urdu language layer
Requirement(s): LOC-01
Priority: P0 · Size: M · Depends on: — (Q-01 answered: reuse)
Scope: Port `app/Libraries/UiLanguage.php`, `app/Language/{en,ur}/Login.php` and the browser dictionary mechanism plus RTL CSS from `lpg-management-system`. Language switch per user (cookie, then user preference) with a tenant default. Port `tests/unit/UiLanguageTest.php`.
Acceptance criteria:
  - [ ] The login page and app shell switch to Urdu with `dir="rtl"`; the switch persists.
  - [ ] Ported unit tests pass; shell_check passes in both languages (no sideways scroll at 390 px in RTL).
Tests to add: ported PHPUnit; shell_check RTL pass.
Migration: maybe (user language), reversible.

### BL-060 — Urdu catalogs: POS, receipts, customers, cash
Requirement(s): LOC-01, LOC-03
Priority: P0 · Size: M · Depends on: BL-059
Scope: Re-key the fork's `ur/app/{sales,receipts,customers,cash,common,messages}.php` to this repo's (redesigned) screen text, and translate the rest.
Acceptance criteria:
  - [ ] A test lists untranslated visible strings on these screens; the list is empty.
Tests to add: a Playwright "untranslated text" scan for these pages.
Migration: no.

### BL-061 — Urdu catalogs: inventory, purchases, suppliers, rates, expenses
Requirement(s): LOC-01 · Priority: P0 · Size: M · Depends on: BL-059
Acceptance criteria: as BL-060 for these screens. Tests: same scan. Migration: no.

### BL-062 — Urdu catalogs: dashboard, reports, settings, admin, orders
Requirement(s): LOC-01 · Priority: P0 · Size: M · Depends on: BL-059
Acceptance criteria: as BL-060 for these screens, plus server messages (`messages.php`). Tests: same scan. Migration: no.

### BL-063 — Currency and number/date formatting
Requirement(s): LOC-02
Priority: P0 · Size: M · Depends on: BL-059
Scope: Business settings for currency code and symbol (PKR / Rs default) and decimals. A `Money::format()` and date helper (locale-aware, Urdu digits optional) replace the 115 hard-coded "Rs." in views and the JS `fmtMoney`.
Acceptance criteria:
  - [ ] `grep "Rs\." app/Views` returns 0 hits; changing the symbol changes POS, receipts and reports.
Tests to add: PHPUnit formatter; shell_check.
Migration: no.

### BL-064 — Receipts: 58 mm, logo, Urdu
Requirement(s): BIL-05, LOC-03, SAS-06
Priority: P0 · Size: M · Depends on: BL-059, BL-063
Scope: A 58 mm layout next to 80 mm and A4. Business logo upload (validated image, size limit, stored per business). Receipt language follows the setting or user choice; Urdu uses an embedded Urdu font (Noto Nastaliq / Naskh, self-hosted).
Acceptance criteria:
  - [ ] `?format=58mm` renders under 220 px width with no overflow.
  - [ ] The Urdu receipt renders RTL with correct shaping in Chromium print preview (Playwright screenshot reviewed).
  - [ ] The logo appears on all formats.
Tests to add: pos_check receipt cases.
Migration: yes (logo path), reversible.

### BL-065 — Receipt share link (WhatsApp)
Requirement(s): BIL-05, MOB-08
Priority: P0 · Size: S · Depends on: BL-064
Scope: Signed, expiring public receipt URL (HMAC token, no login, read-only, no other data). A "Share on WhatsApp" button opens `wa.me/<customer phone>?text=<link>`.
Acceptance criteria:
  - [ ] A valid link shows the receipt without login; a tampered or expired token gives 404; the link never exposes other sales.
Tests to add: E2E + harness case.
Migration: no.

### BL-066 — Receipt PDF (with Urdu)
Requirement(s): BIL-05, LOC-03
Priority: P0 · Size: M · Depends on: BL-064; **owner approval for a new dependency (mPDF, which shapes Urdu; dompdf does not)**
Scope: A PDF download of A4 and 80 mm receipts, Urdu and English.
Acceptance criteria:
  - [ ] The PDF opens and Urdu text is shaped correctly (visual check on an attached sample); English is unchanged.
Tests to add: Playwright downloads the PDF and checks the content type and size.
Migration: no. · Risks: Dependency size; licence (GPL for mPDF) must be acceptable for a SaaS product. Owner decides.

## Track K — Reports (P0)

### BL-067 — Daily closing report
Requirement(s): RPT-01
Priority: P0 · Size: S · Depends on: BL-033
Scope: A printable day-close per branch: sales by type and payment mode, cash received, credit given, receipts, expenses, cylinders out and in by type (from movements), and closing stock by state.
Acceptance criteria:
  - [ ] Figures equal independent SQL for the verify_hq fixture; cylinders out minus in equals the stock change for the day.
Tests to add: verify_hq extension. · Migration: no.

### BL-068 — Stock by product × state; movement history filters
Requirement(s): RPT-02
Priority: P0 · Size: S · Depends on: BL-033, BL-036
Scope: Stock matrix (type × state) per branch, and movement history with date, type, source document, state and user filters (paginated).
Acceptance criteria:
  - [ ] The matrix equals `cylinder_units` counts; each filter narrows results as expected against SQL.
Tests to add: shell. · Migration: no.

## Track L — SaaS operations and platform (P0)

### BL-069 — Tenant onboarding wizard
Requirement(s): SAS-01
Priority: P0 · Size: M · Depends on: BL-014, BL-041
Scope: A system-admin wizard in one transaction: business → first branch → owner user (OWNER role, must change password) → default cylinder types, expense categories, price lists and settings → optional sample data (a few customers and an opening stock). API equivalent.
Acceptance criteria:
  - [ ] The wizard creates a usable tenant whose owner can post a POS sale immediately after setting the rates (branch readiness shows Ready).
  - [ ] A failure midway leaves nothing behind.
Tests to add: Playwright wizard; harness includes the new tenant.
Migration: no.

### BL-070 — Super-admin tenant list
Requirement(s): SAS-02
Priority: P0 · Size: S · Depends on: BL-009
Scope: A list of businesses with status, branches, users, documents this month, last activity (latest audit or sale) and a plan placeholder.
Acceptance criteria:
  - [ ] Counts equal SQL; only system admins can open it (403 otherwise).
Tests to add: shell. · Migration: no.

### BL-071 — Support login (impersonation)
Requirement(s): SAS-02
Priority: P0 · Size: M · Depends on: BL-015, BL-070
Scope: A system admin starts a time-limited support session as a tenant's user (reason required). A visible banner is shown, actions are audit logged with both identities, password and role changes are blocked during impersonation, and there is an explicit end.
Acceptance criteria:
  - [ ] Every action during impersonation is logged with `impersonator_id`; the session expires after the configured minutes.
Tests to add: shell + Playwright banner. · Migration: yes (audit column), reversible.

### BL-072 — Remove single-customer constants
Requirement(s): PLT-08
Priority: P0 · Size: S · Depends on: BL-069
Scope: Replace `'Perfect LPG (Pvt.) LTD'` fallbacks with a neutral product name from env, remove the `MAIN` code assumptions from application code, and make backup binary paths env-only (drop the XAMPP path guessing, or keep it only as documented dev behaviour).
Acceptance criteria:
  - [ ] `grep -rn "Perfect LPG" app` hits only env-driven defaults; a fresh tenant shows its own name everywhere.
Tests to add: shell_check. · Migration: no.

### BL-073 — Scheduled, encrypted backups with retention
Requirement(s): PLT-09, NFR-05; Q-12
Priority: P0 · Size: M · Depends on: —
Scope: A `php spark backup:run` command (uses `DatabaseBackupService`), encryption with a key from env (libsodium), and a retention setting (keep N daily / M weekly). Documented cron and Windows Task Scheduler setup in `docs/release-runbook.md`. The restore drill script handles encrypted files.
Acceptance criteria:
  - [ ] The command creates an encrypted file that cannot be read without the key; the drill restores it with 8/8 checks; retention deletes only files beyond the policy.
Tests to add: extend `drill_backup_restore.sh`. · Migration: no.

### BL-074 — Encrypt stored secrets
Requirement(s): NFR-02 (S-08), NFR-05
Priority: P0 · Size: S · Depends on: BL-001
Scope: `smtp_password` (and any future secrets) encrypted with CI4 Encryption (key from env), with a migration encrypting existing values.
Acceptance criteria:
  - [ ] The DB column holds ciphertext; the test email still sends using the decrypted value.
Tests to add: PHPUnit. · Migration: yes, reversible (decrypt in down()).

### BL-075 — PHPUnit on a MySQL test DB for critical logic
Requirement(s): NFR-03
Priority: P0 · Size: S · Depends on: —
Scope: Configure the PHPUnit `tests` DB group to use a MySQL test database (env-driven) when `ext-sqlite3` is absent. Add first tests for `DocumentNumberService`, `MoneyRounding`, `CustomerBalanceService` and `RateService`. Run in CI. Later items add their own tests here.
Acceptance criteria:
  - [ ] `phpunit` runs locally against `lpg_qa_unit` with 0 errors (today 12 error for lack of SQLite).
Tests to add: as listed. · Migration: no.

## Track M — P1 (after pilots start)

Each item follows the same protocol. Acceptance criteria are kept short here and are expanded when the item is picked up.

| ID | Title | Req | Size | Depends | Acceptance criteria (summary) |
|---|---|---|---|---|---|
| BL-076 | Static analysis + CI gates (PHPStan baseline, php-cs-fixer dry-run, env-file guard, dependency audit; port from `lpg-management-system` CI) | PLT-12 | S | — | CI fails on new PHPStan errors above the baseline and on a committed `.env` |
| BL-077 | CSV/Excel export for customers, suppliers, sales, receipts, purchases, stock, expenses | PLT-13, RPT-09 | M | BL-011 | Each list has Export; the file contains only the tenant's rows (harness check) |
| BL-078 | Server-side pagination for heavy web lists (sales history, customers, movements, audit, POS customer search) | MOB-09, NFR-04 | M | BL-019 | Pages return ≤ 100 rows; POS customer search is remote |
| BL-079 | Delta sync: masters changed since cursor (incl. soft deletes) | MOB-04 | M | BL-030 | Cursor returns exactly the rows changed after it, in order |
| BL-080 | Delta sync: ledger/transaction feed since cursor | MOB-04 | M | BL-028, BL-079 | As above for append-only tables |
| BL-081 | Conflict policy enforced: API 409 on stale `version` for masters | MOB-05 | S | BL-030 | An update with an old version returns 409 and the current row |
| BL-082 | PWA shell: manifest, service worker, offline asset cache | MOB-06 | M | BL-059 | Installable on Android Chrome; shell opens offline |
| BL-083 | PWA offline queue infrastructure (IndexedDB queue, UUID, replay with idempotency) | MOB-06 | M | BL-029, BL-082 | 10 queued sales sync once with no duplicates after reconnect |
| BL-084 | Offline forms: delivery, cash collection, cylinder return | MOB-06 | M | BL-083, BL-057 | Each form works offline and syncs |
| BL-085 | Empties to plant and refilled back (at_plant cycle, shortage, damage, lost) | CORE-08 | M | BL-036, BL-038; Q-19 | Dispatch 10 empties, receive 9 filled + 1 damaged; states and movements reconcile |
| BL-086 | Refill-cycle reconciliation report per supplier/plant | CORE-08 | S | BL-085 | Outstanding at plant equals dispatched − received − written off |
| BL-087 | Area/route grouping of orders + map link | DEL-07 | S | BL-031, BL-055 | Orders filter by area; address opens Google Maps |
| BL-088 | Price-compliance report (over-cap lines with reasons) | RPT-05 | S | BL-045 | Lists exactly the `over_cap=1` lines for the period |
| BL-089 | Driver performance and shortage report | RPT-06 | S | BL-058 | Deliveries, failures and shortages per driver equal settlement data |
| BL-090 | Profit/margin report by type and period | RPT-07 | M | Q-06 | Margin per type equals the agreed costing method on a fixture |
| BL-091 | Excel/PDF export for all reports | RPT-09 | M | BL-077, BL-066 | Each report downloads in both formats |
| BL-092 | Plans and limits (users, branches, documents/month) enforced | SAS-03 | M | BL-070 | Creating a user over the plan limit is refused with a clear message |
| BL-093 | Trial / subscription lifecycle: warning → read-only → suspended; never delete | SAS-04 | M | BL-092 | A read-only tenant can view and export but not post |
| BL-094 | Operator subscription invoices (manual, JazzCash/Easypaisa/bank refs) | SAS-05 | M | BL-092 | Invoice numbering gap-free; marking paid lifts read-only |
| BL-095 | Per-tenant branding defaults (name, logo, currency, language) at business level | SAS-06 | S | BL-063, BL-064 | Branch inherits business branding unless overridden |
| BL-096 | One-key swap-and-sell flow on POS | LOC-04 | S | BL-034 | A swap for a known customer posts in ≤ 3 key presses after customer select |
| BL-097 | Personal-data policy: retention, customer anonymisation on request | NFR-05 | S | BL-030 | Anonymise replaces name/phone/address, keeps ledgers intact |
| BL-098 | Docs: SaaS README, `.env.example`, setup guide, operator runbook | NFR-06 | S | — | A new developer reaches a running app following the docs only |
| BL-099 | Performance check on realistic data (50k customers, 500k movements) + port the fork's perf indexes | NFR-04 | M | BL-078 | Key pages < 2 s on the dataset; findings recorded |

## Parked (P2: only on request)

PLT-11 Docker packaging · MOB-07 driver mobile app (Flutter) · MOB-08 customer app beyond the WhatsApp link · CORE-09 QR scan · BIL-11 payment gateways · BIL-12 e-invoicing (needs Q-04) · DEL-08 proof of delivery · DEL-09 recurring orders · SAS-07 usage analytics · EXT-01 marketing-company module · EXT-02 India · EXT-03 hardware.

## Coverage: requirement → backlog items

| Req | Status | Items |
|---|---|---|
| PLT-02 | PARTIAL | BL-007…012 |
| PLT-03 | PARTIAL | BL-001, BL-013 |
| PLT-04 | PARTIAL | BL-017…027, BL-046 |
| PLT-06 | PARTIAL | BL-002, BL-014 |
| PLT-07 | PARTIAL | BL-015, BL-016 |
| PLT-08 | PARTIAL | BL-003, BL-005, BL-072 |
| PLT-09 | PARTIAL | BL-073 |
| PLT-10 | PARTIAL | BL-006 |
| PLT-12 | PARTIAL | BL-076 |
| PLT-13 | PARTIAL | BL-077 |
| MOB-01 | MISSING | BL-028 |
| MOB-02 | PARTIAL | BL-019, BL-029 |
| MOB-03 | MISSING | BL-030 |
| MOB-04 | MISSING | BL-079, BL-080 |
| MOB-05 | PARTIAL | BL-030, BL-033, BL-081 |
| MOB-06 | MISSING | BL-082…084 |
| MOB-09 | PARTIAL | BL-078 |
| CORE-01 | PARTIAL | BL-031 |
| CORE-02 | PARTIAL | BL-032 |
| CORE-03 | PARTIAL | BL-036, BL-056 |
| CORE-04 | PARTIAL | BL-033 |
| CORE-05 | PARTIAL | BL-038 |
| CORE-06 | DIVERGENT | BL-034, BL-035 |
| CORE-07 | PARTIAL | BL-035, BL-037 |
| CORE-08 | MISSING | BL-085, BL-086 |
| BIL-01 | DIVERGENT | BL-040 |
| BIL-02 | PARTIAL | BL-041…043, BL-047 |
| BIL-03 | MISSING | BL-044, BL-045 |
| BIL-04 | PARTIAL | BL-048 |
| BIL-05 | PARTIAL | BL-064…066 |
| BIL-06 | MISSING | BL-051 |
| BIL-07 | PARTIAL | BL-050 |
| BIL-08 | PARTIAL | BL-049 |
| BIL-09 | PARTIAL | BL-015, BL-039 |
| DEL-01 | PARTIAL | BL-053 |
| DEL-02 | PARTIAL | BL-054 |
| DEL-03 | MISSING | BL-052, BL-055 |
| DEL-04 | MISSING | BL-056, BL-057 |
| DEL-05 | MISSING | BL-058 |
| DEL-06 | MISSING | BL-052 |
| DEL-07 | MISSING | BL-087 |
| RPT-01 | PARTIAL | BL-067 |
| RPT-02 | PARTIAL | BL-068 |
| RPT-03 | PARTIAL | BL-050 |
| RPT-05 | MISSING | BL-045, BL-088 |
| RPT-06 | MISSING | BL-089 |
| RPT-07 | MISSING | BL-090 |
| RPT-09 | PARTIAL | BL-077, BL-091 |
| SAS-01 | PARTIAL | BL-069 |
| SAS-02 | PARTIAL | BL-070, BL-071 |
| SAS-03 | MISSING | BL-092 |
| SAS-04 | MISSING | BL-093 |
| SAS-05 | MISSING | BL-094 |
| SAS-06 | PARTIAL | BL-064, BL-095 |
| LOC-01 | MISSING | BL-059…062 |
| LOC-02 | PARTIAL | BL-063 |
| LOC-03 | MISSING | BL-060, BL-064, BL-066 |
| LOC-04 | PARTIAL | BL-034, BL-096 |
| NFR-01 | PARTIAL | BL-007, BL-011 (+ harness extended by later items) |
| NFR-02 | PARTIAL | BL-002…006, BL-012, BL-016, BL-074 |
| NFR-03 | PARTIAL | BL-075 (+ tests in every item) |
| NFR-04 | PARTIAL | BL-019, BL-078, BL-099 |
| NFR-05 | MISSING | BL-073, BL-074, BL-097 |
| NFR-06 | PARTIAL | BL-020, BL-098 |
| P2 PARTIAL/MISSING (PLT-11, MOB-07, MOB-08, CORE-09, BIL-11, BIL-12, DEL-08, DEL-09, SAS-07, EXT-01…03) | — | parked |

DONE and not itemised: PLT-01, PLT-05 (throttling in BL-004), CORE-10, CORE-11, BIL-10, RPT-04, RPT-08, LOC-05.
