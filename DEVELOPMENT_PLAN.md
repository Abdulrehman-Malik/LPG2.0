# Perfect LPG — Development Progress

Legend:
- [ ] Pending
- [/] In Progress
- [x] Done
- [!] Blocked / user business decision required
- [~] Postponed by owner decision

## Roadmap v2 — Back Office First (re-planned 2026-10-15)

**Owner direction (2026-10-15):** the first priority is to complete the **multi-branch back-office web application**: review and correct the code logic, harden security, and modernize the UI/UX so it is attractive and easy to use. **Mobile app development (Flutter Customer App and Business/Staff App) is postponed.** The mobile-facing API and backend services already built in Phase 7 are **retained and maintained** so the apps can be built on them later without rework.

**Working branch:** `feature/backoffice-completion` (from `main` at `4b0dc5e`). No merge to `main` without explicit owner approval.

| Phase | Name | Priority | Status |
|---|---|---|---|
| 0–6 | Core POS / ERP foundation | — | Development complete; SQA execution pending |
| 7 | Mobile & multi-shop backend (API, scope, orders) | — | Backend complete; **mobile apps postponed** (see Phase 13) |
| 8 | Stabilization & security hardening | P0 | [x] Done except owner credential rotation (8.1.2) and committed test suite (8.3.6) |
| 9 | Multi-branch back-office completion | P1 | [x] Done — SQA evidence P9-01…P9-32 |
| 10 | Code-logic review & refactoring | P1 | [ ] Pending |
| 11 | UI/UX modernization | P1 | [~] In progress — 11.1 design system, 11.2 app shell and 11.3 Dashboard, 11.4 POS and Sale History done; Cash next |
| 12 | Back-office SQA, UAT & release | P1 | [ ] Pending |
| 13 | Mobile apps (Customer / Staff) | Future | [~] Postponed |

Phases 8–11 can overlap: Phase 8 must finish first because it fixes data-integrity and security defects. Phase 9 and 10 changes are done service-first so that the retained mobile API benefits from the same fixes. Phase 11 is done page by page after the page's logic has been reviewed.

## Phase 0 — Architecture
- [x] Repository reviewed
- [x] Target normalized database architecture defined
- [x] Core services/business rules/API contract documented
- [x] Complete one-shot MySQL schema prepared
- [x] Defined functional scope implemented; deferred business decisions are documented separately

## Phase 1 — Foundation / Local Test Gate
- [x] Complete database schema in database/schema.sql
- [x] Seed location, roles, permissions, admin, register, expense categories and cylinder types
- [x] Authentication updated for role table
- [x] Dashboard updated for target inventory/sales ledgers
- [x] TESTING.md created as mandatory test gate
- [x] User executes schema and tests foundation
- [x] User reports PASS/FAIL in TESTING.md

## Phase 2 — Master Data
- [x] Customers / Parties
- [x] Suppliers
- [x] Cylinder Types management
- [x] LPG rate management + rate history
- [x] Users / Roles / Permissions
- [x] Opening inventory
- [x] Customer/supplier ledgers

## Phase 3 — POS Sales
- [x] POS screen
- [x] Filled cylinder sale
- [x] KG refill
- [x] Cylinder exchange
- [x] Empty cylinder intake
- [x] Empty cylinder sale
- [x] Payment modes
- [x] Credit enforcement
- [x] OS balance block
- [x] Custom-rate highlighting
- [x] Atomic inventory/cash posting
- [x] Counter Cash session foundation (open/summary/close + POS cash linkage)
- [x] Receipt / print
- [x] Sale void/reversal
- [x] Phase 3 functional scope implemented and released for user testing

### Phase 3 — Transaction Integrity Hardening
- [x] Cash-session open concurrency protection
- [x] Cash-session close locking and committed-summary calculation
- [x] Cash-sale vs cash-session-close concurrency protection
- [x] Sale-row locking for void/reversal idempotency
- [x] Inventory-key concurrency locking for sales and reversals
- [x] Customer-row locking and in-transaction credit-limit recheck
- [x] Concurrency/reversal safeguards implemented; execution evidence is tracked in SQA.md
- [x] Configurable gas stock validation with explicit POS override confirmation
- [x] Gas wastage/leakage recording with physical-cylinder conversion to empty stock and filtered wastage report

## Phase 4 — Purchases & Inventory
- [x] Purchase entry
- [x] Inventory service
- [x] Physical cylinder unit inventory with actual gas weight per cylinder
- [x] Stock adjustments
- [x] Negative-stock protection
- [x] Inventory reports / stock view
- [x] Phase 4 functional scope implemented and released for user testing
- [x] Per-cylinder gas-weight inventory enhancement implemented and released for user retest

## Phase 5 — Counter Cash & Expenses
- [x] Cash register/session
- [x] Cash IN/OUT
- [x] Expenses
- [x] Customer receipts
- [x] Supplier payments
- [x] Handover / cash movement foundation
- [x] Close/reconcile
- [x] Phase 5 functional scope implemented and released for user testing

## Phase 6 — Reports & Audit
- [x] Customer ledger
- [x] Supplier ledger
- [x] Daily transactions / daily summary
- [x] Custom-rate reporting foundation
- [x] Stock report / stock view
- [x] Counter cash reconciliation
- [x] Expenses
- [x] Outstanding balance foundations
- [x] Audit log
- [x] Branch-level Shop Settings with POS defaults, sale stock validation, payment/receipt, and backup endpoint configuration
- [x] Phase 6 functional scope implemented and released for SQA

## Phase 7 — Mobile App and Multi-Shop Customer Ordering

**Implementation branch:** `feature/mobile-multishop-ordering`  
**Planning branch:** `docs/mobile-multishop-final-plan`  
**Main baseline:** `0ef0f3ff41eaa4aae1d66c4549892ab77f3898e6`  
**Current status:** Web administration and the customer/staff API foundation are implemented on this feature branch. CI passes PHP/schema/PHPUnit checks plus selected Playwright browser, API session, customer-order and shop-isolation scenarios against a disposable MySQL database. Full route-by-route SQA, financial/inventory concurrency regression and the owner two-shop pilot remain pending. `main` remains untouched.

### Confirmed product decisions

- Support multiple independent businesses; each has a Head Office and its own shops.
- Keep two separate Flutter apps: Customer App and Business/Staff App.
- Every customer belongs to exactly one shop and links to one financial customer master.
- No legacy customer-to-shop mapping/backfill is required for this initiative. If a populated deployment is later selected, its upgrade/mapping plan must be separately reviewed.
- Keep the existing role/permission implementation and add server-side business/shop scope checks.
- Keep centralized Configuration for Application, Head Office/Business and Shop scopes; preserve existing Shop Settings. Login & Session controls are application-owned.
- English only for the initial release, with localization-ready strings.
- Customer orders are not POS sales; existing validated services own financial/inventory posting.
- Customer offline queue persists locally, but a shop receives an order only after cloud synchronization.

### Work package status

- [x] Requirements modules 01–10 and decision register documented.
- [x] WP-0 — source inventory, framework/test review, Shop Settings inventory and technical decisions. Source inventory complete; runtime verification remains a separate gate.
- [x] WP-1 — fresh-install business/shop/customer ownership schema and seed setup. Migrations 032–039 are included and final mobile-order schema is present in `database/schema.sql`.
- [x] WP-2 — business/shop scope resolver, explicit user/business/shop assignments, standard-role permission integration and scope checks in browser/API paths. Cross-shop runtime cases remain pending in `SQA.md`.
- [x] WP-3 — customer app identity, pending self-registration, approval/rejection, account creation/disablement and revocable customer/staff API sessions. Browser administration screens are now present.
- [x] WP-4 — order tables, state machine, status history, request idempotency, price reconfirmation, selected physical-cylinder snapshots and unique order-to-sale linkage.
- [x] WP-5 — backend order/catalog/configuration services plus browser Order Management, Business/Shop administration, Customer App Account review and Catalog/Shop Availability pages.
- [x] WP-6 — versioned Customer/Staff API, authentication/session lifecycle, permission/scope checks and CI schema assertions. Dedicated security/isolation runtime cases remain pending.
- [~] WP-7 — separate Customer App (Flutter). **Postponed 2026-10-15** — moved to Phase 13.
- [~] WP-8 — separate Business/Staff App (Flutter). **Postponed 2026-10-15** — moved to Phase 13.
- [x] WP-9 — POS fulfilment service integration uses the existing validated sale/cash/inventory/ledger posting path with idempotent order linkage. Financial/inventory regression execution remains pending in `SQA.md`.
- [ ] WP-10 — two-shop pilot, release validation, owner acceptance and rollback plan. The **web back-office** part of the two-shop pilot moves to Phase 12; the mobile part moves to Phase 13.


### Web/backend delivery completed on this branch

- Browser **Businesses & Shops** administration: create businesses (System Administrator only), provision shops with their default shop settings/cash register/inventory-policy records, and manage staff shop/Head Office scope without granting global ADMIN permissions.
- Browser **Customer Orders** queue/detail: filter orders, validate legal state transitions, display price-reconfirmation state and status history, select physical cylinders and post fulfilment through the existing POS service.
- Browser **Customer App Accounts**: review pending signups, link an existing financial customer or create a new one with credit disabled by default, reject with a reason, create app credentials for existing customers, and disable/revoke sessions.
- Browser **Customer Ordering Catalog**: maintain the business-level product catalog and activate/deactivate products per authorized shop.
- Versioned customer/staff API and services include account/session lifecycle, catalogue, centralized settings, order creation/cancellation/reconfirmation, shop order queue/status, physical-cylinder fulfilment and idempotent POS sale linkage.
- These are implementation statuses, not runtime test results. Follow the page-by-page workflow and SQA execution register before release.

### Development safety gates

- All work stays on `feature/mobile-multishop-ordering`; no direct commits to `main`.
- No production database is queried or modified by this development workflow.
- Preserve current POS, ledgers, receipts, deposits, cylinder custody, cash, inventory and custom-role behavior.
- Do not report tests as PASS without execution evidence. CI covers PHP syntax, Composer, MySQL 8 schema/migration smoke checks, PHPUnit, and selected Playwright browser/API runtime scenarios against disposable test data. Full route-by-route SQA, mobile-device validation, concurrency/reversal tests and owner acceptance still require separate evidence; CI is not proof that every page/device scenario passed.
- Do not merge or deploy until all security, isolation, POS-regression and owner-acceptance gates pass.

See [WP-0 source inventory](docs/mobile-multishop/wp0-source-inventory.md), [the detailed implementation plan](docs/mobile-multishop/09-implementation-plan-and-decision-register.md), and [centralized configuration requirements](docs/mobile-multishop/10-configuration-management.md).

## Phase 8 — Stabilization & Security Hardening (P0)

**Goal:** fix the defects found in the 2026-10-15 architecture review before any new feature or UI work, so later phases build on correct posting logic. Every item needs a regression case in `SQA.md`.

### 8.1 Security
- [x] 8.1.1 Stop tracking the misnamed `..env` (it contained live hosted-database credentials); ignore all `.env` variants; the committed `env` file is the only configuration template (its example `app.baseURL` was corrected).
- [!] 8.1.2 **Owner action:** rotate the exposed database password and any other secret that was in `..env`. Removing the file from the current tree does not remove it from Git history; purge history if the repository is or becomes public.
- [x] 8.1.3 `POST migrations/run` no longer runs for anonymous visitors: it requires a signed-in System Administrator, or the documented installer flag (`FRESH_DATABASE_SETUP=true`) during a controlled first deployment.
- [x] 8.1.4 Disabled users are signed out on their next request (the auth filter re-checks `users.is_active`).
- [x] 8.1.5 Login throttling: repeated failed logins for the same login name / IP are temporarily blocked.
- [x] 8.1.6 The web/CLI migration runner ignores `USE <database>;` statements found in older migration files, so a migration can never switch to and alter a different database on the same server (installs whose database is not named `perfect_lpg`).
- [x] 8.1.7 The migration runner's SQL splitter ignores whole-line comments. Previously a `;` inside a comment (migration 039 header) split the statement, so 039 failed when applied by the web/CLI runner. CI did not catch it because CI pre-applies 039 with the `mysql` client. Verified locally: fresh schema + `php spark migrations:run` now completes.
- [x] 8.1.8 Migration checksums ignore line endings. A checkout that converts LF/CRLF (Git autocrlf on Windows) previously made an unchanged migration look edited and the migration gate blocked every sign-in. Checksums are now stored LF-normalized; LF, CRLF and raw-byte checksums recorded by existing installs are all accepted.

### 8.2 Permissions
- [x] 8.2.1 `PermissionService::syncStandardRolePermissions()` no longer re-grants permissions that an administrator removed from a standard role. Default role grants are applied only when a permission code is first created.

### 8.3 Posting integrity (SalesService)
- [x] 8.3.1 Sales posting uses the shop passed by the caller, not the browser session. Fixes customer-balance and credit-limit checks for API order fulfilment, where there is no browser session.
- [x] 8.3.2 Sale void refuses to restore a physical cylinder whose state changed after the sale (later sold, issued to custody, refilled, consumed). The void fails atomically and asks for a controlled stock adjustment instead of corrupting stock (SQA REV02).
- [x] 8.3.3 Sale void no longer posts cash reversals into a closed cash session; the reversal goes to the shop's current open session (and requires one).
- [x] 8.3.4 Security Deposit / Issue Cylinder: payment plan and credit-limit checks run inside the posting transaction after the customer row is locked.
- [x] 8.3.5 Cylinder Return / Refund Deposit: refundable deposit balance is re-checked inside the transaction under the customer lock (prevents double refunds), and inventory locks are taken.
- [ ] 8.3.6 Business-logic PHPUnit suite against a disposable MySQL database for the four POS types, void, credit modes and deposit refund (CI job).

**Verification (2026-10-15):** items 8.1.1–8.3.5 were verified at runtime on a disposable local database. Each defect reproduced on the original code and passed after the fix (SQA.md, "Phase 8 stabilization & security regression", P8-01…P8-11). The 26-test Playwright regression and PHPUnit also pass. Remaining for Phase 8: owner credential rotation (8.1.2), the committed business-logic test suite (8.3.6), concurrency cases, and a CI run on MySQL 8.

**Exit criteria:** all 8.x items done, regression cases recorded in `SQA.md`, CI green.

## Phase 9 — Multi-Branch Back-Office Completion (P1)

**Goal:** the web back office fully supports the approved hierarchy (Business → Head Office → Shops/branches; staff assigned to one or more shops; each customer belongs to one shop) with no cross-branch or cross-business data leakage, plus Head Office oversight. Requirements: `docs/mobile-multishop/02-business-shops-users-permissions.md`, `03-customer-accounts.md`, `10-configuration-management.md`.

### 9.1 Branch-scope audit and fixes
- [x] 9.1.1 Audit every controller/service query for business/shop scope; record results in a scope register (`docs/branch-scope-register.md`).
- [x] 9.1.2 Fix known gaps: `Reports::ledger` loads the customer without a shop check; suppliers, cylinder types and expense categories are global tables.
- [x] 9.1.3 Suppliers become business-owned (migration 040, backward compatible). Supplier balances stay per shop.
- [x] 9.1.4 Cylinder types and expense categories: decide platform-wide vs business-owned (decision D9-1), then implement.

### 9.2 Head Office oversight
- [x] 9.2.1 "All branches" view for users with business scope: consolidated dashboard with per-branch breakdown.
- [x] 9.2.2 Consolidated reports with a branch filter: sales, payment modes, purchases, expenses, cash, stock by branch, customer OS by branch, deposits/custody.
- [x] 9.2.3 Branch comparison report (sales, gas KG sold, margin indicators, cash variance).

### 9.3 Branch administration UX
- [x] 9.3.1 Branch switcher in the top bar with a clear active-branch badge on every page and on printed receipts.
- [x] 9.3.2 Users screen: assign shops / Head Office scope and default branch when creating or editing a user (today this is split across screens).
- [x] 9.3.3 Branch onboarding checklist (settings, cash register, rates, opening stock, users).

### 9.6 Delivered (2026-10-16)
- **Branch Overview** (Reporting → Branch Overview): one business at a time, only the user's accessible branches, date range, branch filter, CSV export. Trading (sales, gas KG, payment modes, receipts, discounts, voids), purchases/expenses/deposits, and current stock/balances (gas, filled/empty/custody/in-transit cylinders, customer OS, open cash). Built on `ReportService` with one grouped query per metric (also starts 10.1).
- **Users screen**: branch access checkboxes and default branch per user (`StaffAccessService`), limited to branches the administrator manages; Head Office access stays in Businesses & Shops.
- **Branch setup checklist** on Businesses & Shops: shop settings, cash register, effective gas rate, cylinder stock, staff assigned (`BranchReadinessService`), with a one-click switch to the branch.
- **Expense Categories** screen (business-owned; deactivate instead of delete).
- **Scope register** `docs/branch-scope-register.md`; fixes: report ledger customer check, cash-session close limited to the branch, Dashboard receivables limited to the branch (was a cross-branch data leak and an HTTP 500 in any non-default branch).
- Sidebar highlights only the most specific menu entry.
- Printed receipts already show the branch name and address; the top-bar branch switcher lists only authorized branches.

### 9.4 Owner decisions (confirmed 2026-10-15)
- [x] **D9-1** Cylinder types and expense categories are **owned by a business**. Each business has its own list; branches of that business share it. A new business is provisioned with the standard cylinder types and expense categories.
- [x] **D9-2** **Inter-branch stock transfer is required** and its flow is **configurable per business**:
  - `stock_transfer_enabled` (default off).
  - `stock_transfer_mode`: `one_step` (stock moves immediately when posted) or `two_step` (sending branch dispatches → cylinders are *in transit* → receiving branch confirms receipt). Default `two_step`.
  - Transfers move **physical cylinder units** (filled with their actual gas weight, or empty) between branches of the **same business only**, with paired out/in inventory movements, a transfer document number, audit trail and cancellation of a dispatched (not yet received) transfer.
- [x] **D9-3** Supplier sharing is **configurable per business** (`supplier_scope_mode`):
  - `shared` — every supplier belongs to the business and is visible to all its branches.
  - `branch` — every supplier belongs to exactly one branch.
  - `both` — each supplier is either business-wide or branch-only, chosen when it is created.
  Supplier balances (purchases/payments) are always tracked per branch.
- [x] **D10-1** Back-dated posting is **configurable per business** (`backdate_mode`):
  - `none` — today only (default).
  - `current_month` — any date from the first day of the current month.
  - `days` — up to `backdate_max_days` days back (1–365, default 7).
  Future-dated postings are never allowed. Applies to POS sales, purchases, expenses, customer receipts and supplier payments. Opening inventory is exempt because it records historical stock.

### 9.5 Implementation order
- [x] 9.5.1 Configuration: add `choice` setting type and the business-level keys above (Configuration → Business screen).
- [x] 9.5.2 `TransactionDatePolicy` service applied to every financial posting path (closes Phase 10.4).
- [x] 9.5.3 Migration 040: business ownership for cylinder types, expense categories and suppliers (+ optional supplier branch); scoped screens, lookups and validation; provisioning of defaults for new businesses.
- [x] 9.5.4 Migration 041 + `StockTransferService`: transfer documents, one-step/two-step flow, screens, permission `STOCK_TRANSFER_MANAGE`, reports.
- [x] 9.5.5 Head Office consolidated dashboard/reports (9.2) and branch administration UX (9.3).
- [x] 9.5.6 Branch-scope audit register and fixes (9.1).

## Phase 10 — Code-Logic Review & Refactoring (P1)

**Goal:** controllers stay thin, posting and reporting logic lives in services that can be reused by the API later, and pages are fast.

- [/] 10.1 Move query/report logic out of controllers into services: `ReportService`, `DashboardService`, `LedgerService` (Reports, Dashboard, Customers/Suppliers ledgers are currently one-line controller queries).
- [ ] 10.2 Performance: POS page loads balances customer by customer (several queries per customer); replace with set-based balance queries. Same review for Dashboard and Reports.
- [ ] 10.3 One customer-balance calculation used everywhere (POS, ledger, reports, receipts, credit checks, API).
- [x] 10.4 Validate and normalize `transaction_at` — delivered by Phase 9.5.2 (`TransactionDatePolicy`, decision D10-1).
- [ ] 10.5 Concurrency-safe document numbering for sales and receipts (sequence table) instead of `MAX()+1` / timestamp+random.
- [ ] 10.6 Retire or isolate the legacy five-mode POS path in `SalesService::post()`; it is still used by customer-order fulfilment. Move fulfilment onto the four header transaction types, then remove dead code.
- [ ] 10.7 Split `SalesService` (1,700+ lines) into per-transaction-type posting classes sharing payment-plan, credit and inventory-lock components (aligned with the agent.md transaction-type isolation rule).
- [ ] 10.8 Consistent money rounding (2 decimals at line and document level) and DECIMAL-safe comparisons.
- [x] D10-1 Back-dating is configurable per business — see Phase 9.4.

## Phase 11 — UI/UX Modernization (P1)

**Goal:** a consistent, attractive and fast back office that is easy for counter staff and managers, on desktop and tablet/mobile browsers.

- [x] 11.1 Design system: extract inline layout CSS into `public/assets/css/app.css`; define color, spacing, typography and radius tokens; standard components (page header with actions, KPI tile, card, data table, form section, empty state, status badge, confirm dialog, toast).
- [x] 11.2 App shell: grouped sidebar with icons and search, top bar with branch switcher, user menu and breadcrumbs, working dark mode, mobile off-canvas navigation.
  - Delivered 2026-10-16: `app/Libraries/Navigation.php` (menu + active entry + breadcrumb), `app/Libraries/AppTheme.php` (branch appearance settings to CSS variables with readable contrast), `public/assets/css/app.css`, `public/assets/js/app.js`, libraries self-hosted in `public/assets/vendor/` (no CDN), Inter as the default font, per-user Light/Dark/Auto switch, `data-confirm` confirm dialog, toasts, double-submit guard on POST forms, skip link, DataTables scroll inside their container on phones. Conventions: `docs/ui-design-system.md`. Evidence: SQA.md P11-01…P11-06.
- [x] 11.3 Dashboard redesign: KPI tiles, sales trend and payment-mix charts, stock alerts (low filled stock, partial cylinders), open cash session status, Head Office branch comparison.
  - Delivered 2026-10-16: query logic moved to `app/Services/DashboardService.php` (same figures; duplicate stock queries removed); controller only parses the range and permissions. Date presets, alerts (no open cash session, transfers awaiting receipt, cylinder type out of stock, partly used cylinders), daily sales bar chart (Chart.js, validated single hue `--lpg-viz-1`, table view), payment-mode bars, cash-session card, stock by cylinder type, latest sales, branch comparison (REPORT_VIEW and 2+ accessible branches of the business). The 7 drill-down tiles, date filter and Current Stock tab are kept (E2E-010). Evidence: SQA.md P11-07…P11-10.
  - Not changed: receivables still call `SalesService::customerBalance()` once per customer (10.2/10.3).
- [x] 11.4 POS UX: keyboard-first entry, customer quick search, clear sticky totals/payment panel, large touch targets, clear errors before submit, print-friendly receipt (A4 and 80 mm).
  - Delivered 2026-10-16 (view-only; `sales/save` and posting rules untouched): customer search by code/name/phone (Enter selects, never submits); shortcuts F2 customer, F4 add line / cylinder picker, F7 payment, F9 or Ctrl+Enter post (ignored while a dialog is open); live overpayment warning using the same rule as the submit check; Net Receivable shown as the panel headline; summary panel sticks below the top bar; 44 px targets on touch screens; status bar with cash-session state; POS CSS rebuilt on design tokens (dark mode works; fixed font-size rules that referenced the removed `--app-font-size`); receipt `?format=a4` alongside the 80 mm default. Evidence: SQA.md P11-11…P11-14.
- [ ] 11.5 Lists and forms: consistent DataTables (search, export, paging), inline validation messages, required markers, confirmation for destructive actions, loading states.
- [ ] 11.6 Accessibility: contrast, visible focus, labels, keyboard navigation.
- [ ] 11.7 Page-by-page rollout order: layout → Dashboard → POS → Sale History/Receipt → Cash → Customers/Suppliers/Ledgers → Inventory → Purchases/Expenses → Reports → Settings/Admin.
  - Progress: [x] Layout/shell · [x] Dashboard · [x] POS · [x] Sale History/Receipt · [ ] Cash · [ ] Customers/Suppliers/Ledgers · [ ] Inventory · [ ] Purchases/Expenses (Expenses page: bare table overflows at 390 px; page repeats the flash messages the layout already shows) · [ ] Reports · [ ] Settings/Admin (incl. login page restyle).
  - Sale History (2026-10-16): page header, date presets, Enter-to-search, posted/received totals, status badges, sticky table header, loading/empty states; void uses a dialog with a required reason (was browser confirm/prompt) and the button now says "Void" (was "Delete", contradicting the void-not-delete rule); details modal has 80 mm / A4 receipt links; void now returns to Sale History (was POS); "All History" uses the local date (was UTC, which dropped today before 05:00 PKT). Evidence: SQA.md P11-15…P11-18.
  - Per screen: use the components in `docs/ui-design-system.md`; replace `onsubmit="return confirm(...)"` with `data-confirm`; run Playwright `tests/e2e` and check light/dark at 1440 px and 390 px.

## Phase 12 — Back-Office SQA, UAT & Release (P1)

- [ ] 12.1 Execute `PAGE_TEST_WORKFLOW.md` page by page (PQA01–PQA16 web rows) on a disposable staging database.
- [ ] 12.2 Execute financial/concurrency/reversal cases (FIP01, INV01, CRL01/02, REV01/02) and the Phase 8 regression cases.
- [ ] 12.3 Two-branch web pilot with the owner (WP-10, web part).
- [ ] 12.4 Deployment runbook, backup/restore drill, rollback plan.
- [ ] 12.5 Owner sign-off; merge to `main` only after approval.

## Phase 13 — Mobile Apps (Postponed)

**Status:** [~] Postponed by owner decision on 2026-10-15. Resume after Phase 12 sign-off.

What stays in place now so mobile work can resume without rework:
- `/api/v1` customer and staff API, token sessions, business/shop scope checks, order state machine, idempotent order-to-POS fulfilment (`CustomerOrderService`, `ApiSessionService`, `ShopScopeService`, `ConfigurationService`).
- Web screens that the apps depend on: Customer App Accounts, Customer Ordering Catalog, Customer Orders.
- CI API scenarios keep running so back-office changes do not break the API.
- Rule: back-office changes in Phases 8–11 must be made in services (not only controllers/views) so the API inherits them.

Postponed items:
- [~] 13.1 Customer App (WP-7): runner projects, signup/approval screens, offline order queue (decision register §8), device QA.
- [~] 13.2 Business/Staff App (WP-8): runner projects, order handling, fulfilment, device QA.
- [~] 13.3 Store packaging/signing, push notifications, mobile pilot (WP-10 mobile part).

## Workflow Gate

Functional development can be marked [x] when the requested module has been implemented and is packaged for user/SQA testing. User testing is recorded separately in TESTING.md and must not be represented as PASS until the user executes it.

Phase 3 transaction-integrity hardening tests are explicitly postponed by user request and remain pending.

## Final Development Review

- [x] Full application code review completed after functional implementation.
- [x] Physical-cylinder inventory adjustments corrected to maintain unit-level state.
- [x] Partial wastage and configured wastage allowance implemented.
- [x] Cylinder-type stock-validation overrides enforced by POS posting.
- [x] Customer and supplier receipt/payment balance limits enforced.
- [x] Local environment configuration removed from source control.
- [x] Branch Shop Settings moved POS defaults/configuration out of the POS screen.
- [x] SQA.md created as the complete execution matrix.
- [x] TESTING.md updated to the final functional SQA gate.

SQA execution remains a verification activity, not an unfinished development feature.
