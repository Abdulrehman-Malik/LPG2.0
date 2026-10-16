# Perfect LPG Project Tracker

This file is a concise continuation point for development.

## Current State
- Target architecture is documented in ARCHITECTURE.md.
- The complete one-shot MySQL schema is database/schema.sql.
- The old schema_v2.sql is no longer the authoritative schema.
- TESTING.md is the mandatory user/developer test gate.
- Phase 1 foundation changes are pushed and ready for local testing.

## Phase 1 Ready for User Testing
1. Execute database/schema.sql on a fresh MySQL server.
2. Configure CodeIgniter .env for perfect_lpg.
3. Test login with admin / admin123.
4. Test dashboard.
5. Record PASS/FAIL and exact errors in TESTING.md.
6. Push the updated TESTING.md.

## Important Design Rules
- Transaction tables are source of truth.
- Inventory is represented by opening balances plus immutable inventory movements.
- Counter cash is represented by cash sessions plus cash transactions.
- Posted transactions are reversed/voided; they are not hard-deleted.
- Sales must preserve the actual applied rate on every line.
- Financial posting must be atomic inside a database transaction.
- Walk-in customers are cash-only and cannot create credit or cylinder-return balances.
- Credit sales must enforce the customer's credit limit.
- Controllers should not contain financial posting logic; use application services.

## Next Development Gate (updated 2026-10-16)
Roadmap v2 in DEVELOPMENT_PLAN.md is authoritative: **back office first**.
- Status (2026-10-16): Phases 8–11 done; Phase 12 automated SQA, runbook and backup drill done. D9-4 (shared supplier balance) and D10-2 (money rounding) are business settings (`supplier_balance_mode`, `money_rounding_mode`; services `SupplierBalanceService`, `MoneyRounding`). Open: password rotation 8.1.2 (owner deferred), owner pilot 12.3 and sign-off 12.5. UI conventions: `docs/ui-design-system.md`; flow review: `docs/app-flow-review.md`; release: `docs/release-runbook.md`.
- Tests: `tests/qa/run_all.sh` (see `tests/qa/README.md`) against a disposable database only; CI job `business-logic-suites` runs the suites on MySQL 8. Never add a migration file while a QA run is in progress (the migration gate blocks login).
- Current work: Phase 8 (stabilization & security) → Phase 9 (multi-branch back office) → Phase 10 (code-logic refactor) → Phase 11 (UI/UX) → Phase 12 (SQA/UAT/release).
- Working branch: `feature/backoffice-completion`. No merge to `main` without owner approval.
- Mobile apps (Flutter) are **postponed** (Phase 13). Keep `/api/v1` and its services working; put business logic in services so the API inherits back-office fixes.


---

## October 2026 Current Implementation Baseline

This section supersedes older POS assumptions in this file. Do not revert the newer transaction architecture to the historical five-mode model unless explicitly requested.

### Repository / branch
- Primary branch: `main`.
- Known-good historical baseline: `b91321ebc38ee2daac71310653153041682f7745`.
- Current automated CI is defined in `.github/workflows/ci.yml`.

### Current POS transaction architecture
POS now uses one transaction type per invoice:
- `gas_sale` — Gas Sale / Refill.
- `cylinder_sale` — Cylinder Sale.
- `security_deposit` — Security Deposit / Issue Cylinder.
- `cylinder_return` — Cylinder Return / Refund Deposit.

The branch default transaction type is stored in `shop_settings.default_transaction_type` and is selected by Shop Settings. The old `default_sale_mode` field remains for backward compatibility and should not be treated as the current POS architecture.

### Gas sale / physical-cylinder behavior
- Normal gas sale quantity is KG, not whole-cylinder quantity.
- Selling 1 KG consumes only 1 KG of gas.
- When individual cylinder tracking is OFF, POS shows aggregate available gas by cylinder type instead of a physical source-cylinder dropdown.
- Backend automatically allocates gas across available filled physical cylinders of that type.
- Physical cylinder records are still maintained in the backend.
- If a physical source cylinder reaches 0 KG, it becomes empty and the corresponding inventory movements are recorded.
- Gas quantity cannot exceed available gas for the applicable transaction unless an explicit stock-override configuration permits it.
- A single POS gas-sale line can therefore consume gas from more than one physical cylinder internally while remaining one sales line.

### Individual cylinder tracking setting
- Shop Settings contains `individual_cylinder_tracking`.
- Default is OFF.
- OFF: aggregate stock by cylinder type is shown on normal POS gas sales and backend allocation is automatic.
- ON: normal POS can expose physical source-cylinder selection.
- Customer custody cylinder selection remains physical-unit based because custody/return/refill workflows require identifying the cylinder held by the customer.

### Security deposit / custody
- Company cylinders can move to customer custody when a security deposit is issued.
- Security deposit is a liability/hold, not sales revenue.
- Cylinder return requires the customer's custody cylinder and refunds the applicable deposit.
- Repeated custody cycles are supported.
- Security deposit and cylinder-return transactions are not treated like ordinary voidable revenue sales.

### Customer OS / payments / credit
- Previous customer OS is carried into the current receivable.
- Payments are applied to previous OS first, then the current sale.
- Remaining unpaid amount becomes customer OS.
- Credit limits support branch validation modes: none, customer, or shop.
- Credit sales require the applicable customer/branch credit validation.

### Inventory
- Opening inventory supports filled cylinders and empty cylinders; gas-only opening records remain historically supported.
- Gas stock is never stored independently. Available Gas KG is always derived from `cylinder_units.gas_weight_kg` for company-owned cylinders with `status='filled'`.
- Inventory has a separate Stock Adjustment & History screen for physical-cylinder and cylinder-count adjustments. Gas adjustments must target a specific physical cylinder and change that unit's actual gas weight; bulk independent Gas KG adjustments are not allowed.
- Filled-cylinder IN requires actual gas weight and cannot exceed cylinder capacity. Purchasing gas must therefore be represented by receiving filled physical cylinders with actual gas weights.
- Inventory Detail Report shows current gas stock, filled/empty cylinder counts, stock by cylinder type, and partially used physical cylinders.

### Permissions
Sidebar entries are permission-aware and hidden when the current user lacks the relevant permission.
Current mappings include:
- DASHBOARD_VIEW
- POS_SALE
- CASH_MANAGE
- CUSTOMER_MANAGE
- SUPPLIER_MANAGE
- INVENTORY_MANAGE
- RATE_MANAGE
- PURCHASE_MANAGE
- EXPENSE_MANAGE
- REPORT_VIEW
- USER_MANAGE
- AUDIT_VIEW

### Automated CI status
Latest verified CI result:
- Workflow: **LPG CI #20**
- Commit: `f19b68e730d49ba5b40be9ed98d4c0b9b407f7ae`
- PHP syntax validation: PASS
- Composer dependency installation: PASS
- MySQL 8 schema bootstrap: PASS
- Required migrations: PASS
- `php spark`: PASS
- PHPUnit: **5/5 tests, 6 assertions**
- PHPUnit coverage is intentionally disabled in CI with `--no-coverage` because the CI runner has no coverage driver configured.

Important: CI success is an automated code/database smoke result. It is NOT browser/SQA sign-off.

### Current real-world testing path
Browser/E2E testing still requires a reachable staging application.
Recommended setup:
`Windows PC/XAMPP -> Cloudflare Tunnel -> public HTTPS staging URL -> browser testing`.

Do not expose the production application/database. Use a dedicated test database and test credentials.


## Environment Configuration Rule — Important

- **Never add Cloudflare/tunnel-specific URL logic to application code.**
- The application must use the normal CodeIgniter `app.baseURL` configuration from the local `.env` file.
- For staging, set `app.baseURL` in the staging machine's local `.env` to the staging HTTPS URL.
- For production, set `app.baseURL` in the production machine's local `.env` to the production URL.
- Do not commit tunnel URLs, environment-specific hostnames, production URLs, or environment secrets to the repository.
- Testing infrastructure may use external URLs, but it must not require application-code changes to accommodate a temporary tunnel.
