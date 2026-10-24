# CODEBASE_MAP — Perfect LPG (LPG2.0)

Phase 1 (ORIENT) output for LPGSASS.md. Read-only survey; no source code was changed.
Surveyed: 2026-10-24, branch `feature/backoffice-completion` @ `3354fe5`.

## 1. Result in brief

- **Stack:** CodeIgniter **4.7.4**, PHP **^8.2**, **MySQL 8** (MariaDB also targeted). No CI3 upgrade needed.
- **Shape:** a server-rendered back office (33 web controllers, ~118 web routes) plus a **partial** JSON API under `/api/v1` (47 routes). The API covers auth, customer mobile ordering, catalog, businesses/shops, configuration and customer app accounts. It does **not** cover POS sales, inventory, purchases, cash, customers CRUD, expenses or reports.
- **Tenancy today:** there is already a **business → branch (`locations`)** hierarchy in one shared database. Isolation works by explicit per-query `location_id` / `business_id` filters plus `ShopScopeService` checks. Nothing is enforced centrally: no base model, query filter or `tenant_id` convention.
- **Domain:** a strong physical-cylinder model (`cylinder_units`) with an inventory movement ledger. It also has a POS with four transaction types, security deposits and custody, customer OS/credit ledgers, supplier ledgers, cash sessions, purchases, inter-branch transfers, rates with history, and customer mobile orders.
- **Tests:** 19 PHPUnit tests (thin), plus extensive shell/Playwright QA suites that need a running app and a disposable MySQL database.

## 2. Framework, runtime, dependencies

| Item | Value | Evidence |
|---|---|---|
| Framework | CodeIgniter 4.7.4 | `system/CodeIgniter.php:58` (`CI_VERSION`); same in `vendor/codeigniter4/framework` |
| PHP | `^8.2` required; CI uses 8.2 | `composer.json`, `.github/workflows/ci.yml` |
| DB | MySQL 8 / InnoDB / utf8mb4 (MariaDB compat claimed in migration 044) | `database/schema.sql` header, CI `mysql:8.0` service |
| Runtime deps | `laminas/laminas-escaper`, `psr/log`; ext `intl`, `mbstring`, `mysqli`, `zip` | `composer.json` |
| Dev deps | PHPUnit 10.5/11, Faker, php-cs-fixer, Kint, vfsStream, predis | `composer.json` |
| Front end | Bootstrap, Bootstrap Icons, jQuery, DataTables, Chart.js, Inter font, all vendored locally | `public/assets/vendor/*` |
| E2E | Node 22, Playwright, axe-core | `tests/e2e/package.json`, CI |
| Mobile | Flutter skeletons (postponed, Phase 13) | `mobile/apps/*`, `mobile/packages/lpg_mobile_core` |

Notes:
- `composer.json` is the **framework's own** composer file (`"name": "codeigniter4/framework"`, autoload `CodeIgniter\\ => system/`). The app runs from the repo-root `system/` (`app/Config/Paths.php:29`). `system/` (653 files) and `vendor/` (3,056 files) are both **committed to git**.
- There are no PDF, Excel, QR or payment libraries. Excel import/export is hand-written over `ZipArchive` (`app/Services/ExcelInventoryService.php`).

## 3. Folder structure

| Path | Purpose |
|---|---|
| `app/Config/` | CI4 config. `Routes.php` (all routes), `Filters.php` (auth/api filters, CSRF), `Database.php` (maps `LPG_DB_*` env vars, line ~200), `Backup.php` (backup paths, Google Drive creds from env), `App.php` (locale `en` only, TZ `Asia/Karachi`) |
| `app/Controllers/` | 33 web controllers (back office) + `Filters/` (`AuthFilter`, `ApiAuthFilter`, `ApiReadyFilter`) |
| `app/Controllers/Api/V1/` | `Auth.php`; `Customer/{Me,Products,Orders}`; `Staff/{Me,Businesses,Catalog,Configuration,CustomerAccounts,Orders}` |
| `app/Services/` | 36 services: posting logic (sales, purchases, cash, inventory, transfers), scope/permissions, configuration, reports, backup, migrations |
| `app/Models/` | 18 CI4 models, mostly thin. Several are dead (see §8) |
| `app/Libraries/` | `Navigation.php` (permission-aware sidebar), `AppTheme.php` |
| `app/Views/` | ~45 PHP views. `layouts/app.php` is the shell; `sales/index.php` (1,299 lines) is the POS |
| `app/Commands/` | `RunMigrations.php` (`php spark migrations:run`, the SQL migration runner) |
| `app/Language/en/` | only `Validation.php`; no app strings |
| `app/Database/Migrations`, `Seeds` | **empty**: CI4 migrations are not used |
| `database/schema.sql` | one-shot fresh-install schema (54 tables + seed rows) |
| `database/migrations/` | 44 numbered **SQL** upgrade scripts (`NNN_YYYYMMDD_name.sql`), run by `MigrationRunnerService` |
| `public/` | web root (`index.php`, assets) |
| `tests/unit`, `tests/database`, `tests/session` | PHPUnit (19 tests) |
| `tests/qa/` | bash + Node runtime QA suites against a disposable DB (`run_all.sh`, `verify_*.sh`, `checks/*.mjs`) |
| `tests/e2e/` | Playwright spec `lpg-runtime.spec.mjs` (26 tests) + SQL fixtures |
| `docs/` | design/QA docs: `ui-design-system.md`, `app-flow-review.md`, `release-runbook.md`, `railway-deployment.md`, `mobile-multishop/*` (API contract, security, decision register) |
| `mobile/` | Flutter app skeletons (20 files) |
| Root `*.md` | `ARCHITECTURE.md`, `DEVELOPMENT_PLAN.md` (roadmap v2), `requirements.md`, `SQA.md` (QA evidence), `TESTING.md`, test-case docs |

## 4. Entry points

### 4.1 Web routes (`app/Config/Routes.php`)

**Public, not behind the `auth` filter:**
- `/`, `login` (GET/POST), `logout`.
- `installation`, `installation/run`: guarded by `FRESH_DATABASE_SETUP` env plus admin password and `REPLACE DATABASE` confirmation (`InstallationSetupService::install`).
- `migrations/run`: needs a signed-in system admin or system-admin credentials posted (`Migrations::authorizedSystemAdministrator`).
- `database-backup/*` (index, download, create, restore, email settings, delete, email): each action checks `PermissionService::allows('BACKUP_MANAGE')`, which needs `is_system_admin=1`. These routes skip `AuthFilter`'s disabled-user and migration-gate checks.

**Behind `auth` (session login, migration gate, active-user check, shop-scope repair), by module:**

| Module | Routes (prefix) | Controller | Permission(s) |
|---|---|---|---|
| Dashboard | `dashboard` | `Dashboard` | DASHBOARD_VIEW |
| Business admin | `business-management/*`, `business-hierarchy` | `BusinessManagement`, `BusinessHierarchy` | system admin / BUSINESS_SETTINGS_*, SHOP_ACCESS_MANAGE |
| Configuration | `configuration/*` | `Configuration` | APPLICATION/BUSINESS/SHOP_SETTINGS_* |
| Shop settings / context | `shop-settings/*`, `shop-context/select` | `ShopSettings`, `ShopContext` | SHOP_SETTINGS_* |
| POS | `sales`, `sales/save`, `sales/void/:id`, `sales/history*`, `sales/receipt/:id`, `sales/custody/:id`, `sales/customer-balance/:id` | `Sales` | POS_SALE, POS_HISTORY, POS_VOID |
| Cash | `cash/*` (open/close/move/history) | `Cash` | CASH_MANAGE |
| Purchases | `purchases/*` (save, void) | `Purchases` | PURCHASE_MANAGE |
| Inventory | `inventory/*` (stock, cylinders, history, adjustments, controls, wastage, transfers, opening, Excel import) | `Inventory`, `InventoryControls`, `StockTransfers`, `InventoryOpening` | INVENTORY_MANAGE, INVENTORY_CONTROLS_MANAGE, GAS_WASTAGE_MANAGE, STOCK_TRANSFER_MANAGE |
| Expenses | `expenses/*`, `expenses/categories/*` | `Expenses`, `ExpenseCategories` | EXPENSE_MANAGE |
| Receipts / supplier payments | `receipts/*`, `supplier-payments/*` | `Receipts`, `SupplierPayments` | CUSTOMER_MANAGE / SUPPLIER_MANAGE (via guard) |
| Parties | `customers/*` (ledger, Excel import), `suppliers/*` (ledger) | `Customers`, `Suppliers` | CUSTOMER_MANAGE, SUPPLIER_MANAGE |
| Masters | `cylinder-types/*`, `rates/*` (incl. customer rates) | `CylinderTypes`, `Rates` | INVENTORY_MANAGE / RATE_MANAGE |
| Users | `users/*`, `users/role-permissions` | `Users` | USER_MANAGE, ROLE_PERMISSION_MANAGE |
| Reports | `reports`, `reports/inventory-detail`, `reports/branches` (CSV export), `reports/custody`, `reports/ledger/:id` | `Reports` | REPORT_VIEW |
| Audit | `audit` | `Audit` | AUDIT_VIEW |
| Mobile admin | `customer-app-accounts/*`, `mobile-catalog/*`, `customer-orders/*` | `CustomerAppAccounts`, `MobileCatalogManagement`, `OrderManagement` | CUSTOMER_ACCOUNT_MANAGE, BUSINESS_CATALOG_*, ORDER_* |

### 4.2 API routes (`/api/v1`, filter `api-ready`; CSRF exempted)

- **Public:** `GET health`; `GET customer/auth/shops`; `POST customer/auth/{signup,login,refresh}`; `POST staff/auth/{login,refresh}`.
- **Customer (`api-auth:customer`):** logout, change-password, `GET me`, `GET products`, orders (list/create/show/cancel/reconfirm-price).
- **Staff (`api-auth:staff`):** logout, change-password, `me`, `shops`; configuration (application/business/shop, save, reset); businesses (list/create, shops list/create, staff-access get/set); orders (list/show/status/fulfill/fulfillment-options); catalog (business catalog, shop products, availability); customer signups (list/approve/reject) and customer app accounts (create/disable/enable/reset-password).
- **Not exposed via API:** POS posting (except order fulfilment, which posts a POS sale), void, cash, purchases, inventory, transfers, customers CRUD/ledgers, suppliers, rates, expenses, reports, users/roles, audit.
- **Response style:** JSON `{"error":{"code","message"}}` on failure. No OpenAPI file; the contract is described in prose in `docs/mobile-multishop/11-mobile-api-contract.md`.

### 4.3 Services (business logic), by area

| Area | Services |
|---|---|
| POS posting | `SalesService` (1,065 lines; `post`, `postForCustomerOrder`, `void`) + traits `PostsGasSales`, `PostsCylinderSales`, `PostsSecurityDeposits`, `PostsCylinderReturns`; `DocumentNumberService`; `MoneyRounding`; `TransactionDatePolicy`; `RateService` |
| Stock | `InventoryService` (stock, receivePurchase, adjust), `CylinderUnitService` (units, custody), `InventoryControlService` (policies, wastage), `StockTransferService`, `ExcelInventoryService` |
| Money | `CashService` (sessions/transactions), `PurchaseService` (post/void), `CustomerBalanceService`, `SupplierBalanceService`, `LedgerService` |
| Orders (mobile) | `CustomerOrderService` (1,253 lines; idempotent create, status flow, fulfilment → POS) |
| Scope / security | `ShopScopeService`, `BusinessContext`, `PermissionService`, `StaffAccessService`, `ApiSessionService`, `AuditService` |
| Config / setup | `ConfigurationService` (application → business → shop settings, allow-list), `BusinessProvisioningService`, `BranchReadinessService`, `BusinessHierarchyService`, `InstallationSetupService`, `MigrationRunnerService` |
| Reports | `DashboardService`, `ReportService` |
| Ops | `DatabaseBackupService` (mysqldump or portable PHP dump, restore, email, Google Drive), `TransactionalDataRefreshService` (destructive test-data reset) |

### 4.4 Cron / scheduled jobs

**None in the repo.** No CI4 tasks or spark commands for scheduling, and no cron or Task Scheduler docs. Backups are started manually from the UI. (`C:\PerfectLPG\installer\backup.ps1` exists on this machine as part of a separate packaged install. It is not in this repo, and I did not read it.)

## 5. Database schema summary

54 tables in `database/schema.sql`, plus `lpg_migration_history` (created at runtime by `MigrationRunnerService::ensureHistoryTable`). Seed rows: one business, a `MAIN` location, roles, permissions, an `admin` user (password `admin123` per CLAUDE.md), one cash register, cylinder types, an expense category, inventory policy, catalog product.

### 5.1 Tables by domain (key columns)

| Domain | Tables |
|---|---|
| Tenancy / org | `businesses`(code, name, is_active) → `locations`(business_id, code, name) = branches/shops |
| Identity / RBAC | `users`(location_id, role_id, is_system_admin, username, email, password_hash, is_active); `roles`, `permissions`, `role_permissions` (global); `user_business_access`(access_level, is_default), `user_shop_access` |
| Settings | `application_settings`, `business_settings`, `shop_setting_overrides` (key/value, via `ConfigurationService`); legacy wide `shop_settings` (one row per location, includes SMTP password and backup URL) |
| Masters | `cylinder_types`(business_id, capacity_kg, tare_weight_kg, empty_cylinder_price); `customers`(location_id, code, name, phone, city, address, vehicle_no, credit_limit, allow_credit_sale, opening_balance, is_active); `suppliers`(business_id, location_id nullable); `supplier_opening_balances`; `expense_categories`(business_id) |
| Pricing | `rate_cards`(location_id **nullable = global**, rate_type gas_per_kg / cylinder_package / filled_cylinder, effective_from/to); `rate_change_log`(old/new rate, reason); `customer_rates`(effective-dated per customer) |
| POS | `sales`(sale_no, location_id, customer_id, transaction_type, status posted/voided, totals, deposits, credit, previous_os_balance, os_balance, custom_rate_flag, void fields, customer_order_id unique); `sale_items`(line_type, applied_rate, standard_rate, gas_rate, cylinder_price, custom_rate_flag, empty_cylinder_received); `sale_payments`(payment_type, payment_mode, reference_no) |
| Receivables / payables | `customer_receipts`, `supplier_payments` (status, payment_mode, reference_no); `customer_security_deposits`(entry_type hold/refund) |
| Purchasing | `purchases`, `purchase_items`(actual_gas_weight_kg, unit_rate), `purchase_payments`, `shop_purchase_void_users` |
| Inventory | `cylinder_units`(location_id, cylinder_type_id, unit_code, status filled/empty/custody/sold/in_transit, custody_customer_id, gas_weight_kg, source); `inventory_movements`(inventory_type, cylinder_type_id, quantity, direction, source_type/id/line_id, cylinder_unit_id); `inventory_opening_balances`; `inventory_policies`; `inventory_wastage_logs`; `cylinder_custody`(issue/return sale, deposit, gas weights); `stock_transfers` + `stock_transfer_items` |
| Cash | `cash_registers`(location_id) → `cash_sessions`(open/closed, counted_cash) → `cash_transactions`(type, direction, reference) |
| Expenses | `expenses`(expense_no, location_id, category_id, payment_mode, reference_no) |
| Mobile ordering | `business_catalog_products`, `shop_product_availability`, `customer_app_accounts`, `customer_signup_requests`, `customer_orders`(idempotency_key, request_fingerprint, price_revision, status 9-value enum, fulfilment_method, pos_sale_id), `customer_order_items`, `customer_order_status_history` |
| API auth | `api_sessions`(audience customer/staff, expiries, revoked_at), `api_access_tokens`, `api_refresh_tokens` (SHA-256 hashes) |
| Audit / numbering | `audit_logs`(user, location, action, entity, old/new JSON, IP, UA); `document_sequences`(location_id, doc_type, period, last_number) |

Columns common to all tables: `created_at`/`updated_at` on most masters, `created_at` only on many ledger tables. **No `deleted_at`, `version` or UUID columns anywhere.** IDs are `BIGINT AUTO_INCREMENT`.

### 5.2 Tenant-owned vs global

The tenant root is `businesses`. Ownership reaches a table either directly or through `locations.business_id`.

| Class | Tables |
|---|---|
| **Global (platform)** | `roles`, `permissions`, `role_permissions`, `application_settings`, `lpg_migration_history`. `users` are global identities scoped through `user_business_access` / `user_shop_access` (`users.location_id` is a legacy default) |
| **Tenant: direct `business_id`** | `locations`, `cylinder_types`, `suppliers`, `expense_categories`, `business_settings`, `business_catalog_products`, `shop_product_availability`, `customer_signup_requests`, `customer_orders`, `customer_order_items`, `customer_order_status_history`, `stock_transfers`, `user_business_access` |
| **Tenant: via `location_id`** (branch-owned) | `shop_settings`, `shop_setting_overrides`, `customers`, `sales`, `purchases`, `customer_receipts`, `supplier_payments`, `shop_purchase_void_users`, `inventory_opening_balances`, `inventory_movements`, `inventory_policies`, `inventory_wastage_logs`, `cylinder_units`, `cylinder_custody`, `customer_security_deposits`, `cash_registers`, `expenses`, `document_sequences`, `supplier_opening_balances`, `user_shop_access`, `audit_logs` (nullable) |
| **Tenant: via parent FK only** | `sale_items`, `sale_payments` (→ sales); `purchase_items`, `purchase_payments` (→ purchases); `cash_sessions` (→ register), `cash_transactions` (→ session); `stock_transfer_items`; `customer_app_accounts`, `customer_rates` (→ customers); `api_*` tables (→ user / account) |
| **Ambiguous** | `rate_cards` / `rate_change_log` with `location_id IS NULL`: a "global" rate that `GasRateModel::effectiveRow` uses as a fallback for **every** branch of **every** business |

## 6. Authentication and authorization today

- **Web login** (`app/Controllers/Auth.php`): username or email plus `password_verify`, then `session()->regenerate()`. Throttle: 5 failures per IP+login, 15-minute lockout, stored in the cache. Login is blocked while migrations are pending. The session stores `user_id`, `location_id` (current branch), `role`, `isLoggedIn`.
- **`AuthFilter`** (every back-office route): checks the session, the migration gate and that the user is still active (re-read each request). It then confirms the session branch is still accessible, or repairs it to an accessible default.
- **Permissions** (`PermissionService`): one **global role per user** (`users.role_id`), mapped to permission codes through `role_permissions`. Every controller action calls `allows('CODE')` in a local `guard()`. Four codes also need `users.is_system_admin=1`: BACKUP_MANAGE, ROLE_PERMISSION_MANAGE, APPLICATION_SETTINGS_*. Standard roles: ADMIN, MANAGER, CASHIER, plus PAK_OPS from migration 030. There is **no** owner, driver or accountant role, and no per-tenant role.
- **Data scope** (`ShopScopeService`): computes accessible shops from direct shop assignments, business (Head Office) assignments, the system-admin flag (all businesses) and a legacy fallback. Controllers then filter queries by `session('location_id')`. `BusinessContext` guards business-owned masters.
- **API auth** (`ApiSessionService`, `ApiAuthFilter`): opaque 64-hex bearer tokens stored as SHA-256 hashes. Two audiences (customer/staff), refresh-token rotation with reuse detection, idle and absolute TTLs, revocation on logout. Staff API calls use `PermissionService::allowsForUser`. **No login throttling on the API.**
- **CSRF:** global, cookie-based, `api/v1/*` exempt. Session: file driver, 2 h, cookie `secure=false`, SameSite Lax. Secure headers and CSP are off.

## 7. Running the app and tests: what I actually ran

Environment found: Windows 11. **No PHP, MySQL or Docker on PATH**; Node is available. A separate packaged install at `C:\PerfectLPG` (PHP 8.5.12, MySQL 8.4.13, Apache) is running. I used **only its `php.exe` binary**. I did **not** connect to its MySQL or read its config/secrets, because it may hold real data.

| Command | Result |
|---|---|
| `php -l` on all 213 PHP files in `app/`, `public/`, `tests/unit`, `tests/qa/commands` (PHP 8.5.12) | **213/213 pass**, 0 syntax errors |
| `CI_ENVIRONMENT=testing php vendor/bin/phpunit --no-coverage` (cache redirected to scratchpad) | **19 tests: 7 pass, 12 errors.** All 12 errors are `Class "SQLite3" not found`: the bundled PHP lacks `ext-sqlite3`, which the `tests` DB group (`:memory:` SQLite) needs. Passing: AppShellTest (4), HealthTest (2), ExampleSessionTest (1). Erroring: ConfigurationServiceTest (5), ShopScopeServiceTest (5), ExampleDatabaseTest (2) |
| App server, `migrations:run`, `tests/qa/run_all.sh`, Playwright | Run during Phase 2 (with owner approval) against a disposable `lpg_qa` database on the local MySQL 8.4 (port 3307), using a QA-only user limited to that database. App served with `php spark serve` on port 8080. Results: `reset_db.sh` PASS; Playwright **26/26**; `run_all.sh --no-e2e` **441/441**. See AUDIT.md §1 |

How it is meant to run (from `README.md`, `tests/qa/README.md`, CI):
1. Copy `env` to `.env` and set `database.default.*` or `LPG_DB_*` and `app.baseURL`.
2. On a fresh DB, `mysql < database/schema.sql`, or set `FRESH_DATABASE_SETUP=true` and use `/installation`.
3. `php spark migrations:run` (or the button on the login page).
4. `php spark serve` (or Apache with web root `public/`).
5. Tests: `php vendor/bin/phpunit --no-coverage`; `bash tests/qa/run_all.sh` against `lpg_qa`.

CI (`.github/workflows/ci.yml`): PHP lint → composer install → schema + selected migrations on MySQL 8 → `migrations:run` → seed fixtures → PHPUnit → Playwright E2E (E2E-001…030) → SQL integrity assertions. CLAUDE.md also mentions a `business-logic-suites` job. **No static analysis** (PHPStan/Psalm) step.

PHPUnit's 12 SQLite-backed tests still need a PHP build with `ext-sqlite3`. Locally they error for that reason only; CI runs them.

## 8. Code health notes

**Business logic outside services**
- Several controllers are dense one-line methods with queries and posting logic inline: `Expenses::save`, `Receipts::save`, `SupplierPayments::save` (transactions plus balance checks in the controller), `Reports::index/custody/inventoryDetail/ledger` (report SQL in the controller), `Rates::save` (rate insert + change log + transaction), `Users`, `ShopSettings` (direct `audit_logs` insert at `ShopSettings.php:210`), `BusinessManagement` (38 DB calls), `CustomerAppAccounts` (24), `InventoryOpening` (25, 746 lines).
- API controllers duplicate web controllers rather than sharing services: `Api/V1/Staff/Businesses.php` vs `BusinessManagement.php`, `Staff/CustomerAccounts.php` vs `CustomerAppAccounts.php`, `Staff/Catalog.php` vs `MobileCatalogManagement.php`. Each has its own transactions and audit calls.
- Views query the DB or instantiate services: `layouts/app.php:8-55` (locations, businesses, shop settings, migration status on every page), `business_management/index.php:60`, `sales/index.php:325,373`, `shop-settings/index.php:61`.

**Dead code**
- Models for tables that do not exist: `DailyEmptyStockModel`, `DailyFilledStockModel`, `DailySummaryModel`, `CustomerPaymentModel`.
- Unused models: `ExpenseModel` (wrong columns: `category`, `expense_date`), `SaleModel`, `SaleItemModel` (0 references).
- Legacy `shop_settings.default_sale_mode` and `ShopSettingsModel::SALE_MODES` are kept for compatibility. `app/Views/welcome_message.php` and `Home.php` are stock CI scaffolding.

**Tenancy smells**
- Scope comes from `session('location_id')` read in many places, including models (`CustomerModel::activeDirectory`, `withLedgerTotals` fallback). Some API-adjacent paths pass IDs explicitly.
- Isolation depends on every caller first loading the parent record with a scope filter (for example `CustomerModel::findForLocation`). Helpers further down, such as `SalesService::customerGasBalance(customerId)`, trust their caller. This is correct today but nothing enforces it.
- `rate_cards` with `location_id IS NULL` act as a cross-business fallback.
- `PermissionService` caches by user ID in a static property and lazily syncs permissions on each request.

**Schema / migrations**
- Migrations are **forward-only SQL** with no down scripts, so none are reversible (conflicts with LPGSASS Rule 6). CI4 `Migrations`/`Seeds` folders are unused.
- `011_…refresh_transactional_data.sql` is destructive and lives in the normal migration folder, guarded by a session variable. `TransactionalDataRefreshService` also truncates `audit_logs`.

**Tests**
- PHPUnit has no coverage of posting, stock, pricing, credit or voids. That logic is tested only by the runtime shell/Playwright suites, which need a live DB.

**Security smells** (for the NFR-02 review in the audit)
- Default seeded credentials `admin`/`admin123` in `schema.sql`. Migration 030 seeds user `pak` with a fixed bcrypt hash and **resets that password whenever the migration is re-run** (`ON DUPLICATE KEY UPDATE password_hash`).
- The API auth endpoints have no throttling.
- `Cookie::$secure=false`, CSP and secure headers off.
- Legacy `shop_settings.smtp_password` is stored in plaintext in the DB.
- Backup restore takes an SQL upload of up to 512 MB (system-admin only).
- No committed `.env` was found, and git history shows no `.env`. The committed `env` template holds only a local root / empty password example.

**Audit coverage gaps**
- Not audit-logged: sale void (`SalesService::void`, only `void_reason` on the row), users/roles changes (`Users`), customer saves, rate changes (separate `rate_change_log`, not `audit_logs`), and shop settings (direct insert).

**Other**
- `vendor/` and `system/` are committed, so the framework exists twice (repo `system/` and `vendor/codeigniter4/framework/system`).
- No language files: all UI strings are hard-coded English. No RTL.
- Hard-coded `'Perfect LPG (Pvt.) LTD'` fallbacks in `Auth.php` and `ShopSettingsModel` defaults.

## 9. Not verified (needs the human or a runnable environment)

- Behaviour outside the existing suites. The suites passed (AUDIT.md §1), but they only cover what they test.
- Whether CI is currently green on `3354fe5` (I did not query GitHub).
- What is deployed in production (`docs/railway-deployment.md` suggests Railway; `C:\PerfectLPG` suggests a Windows packaged install), and how much real data exists.
