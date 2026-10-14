# WP-0 Source Inventory — Mobile Multi-Shop Ordering

**Reviewed:** 2026-10-14  
**Repository:** `Abdulrehman-Malik/LPG2.0`  
**Main baseline:** `0ef0f3ff41eaa4aae1d66c4549892ab77f3898e6`  
**Implementation branch:** `feature/mobile-multishop-ordering`  
**Status:** Source-level inventory started; runtime verification is still pending.

## 1. Runtime and project shape

- Framework/runtime: CodeIgniter 4, PHP `^8.2`, MySQL 8/InnoDB.
- Dependencies/tests are declared in `composer.json` and `phpunit.dist.xml`.
- Fresh database schema: `database/schema.sql`.
- Numbered SQL upgrade scripts: `database/migrations/NNN_*.sql`; `app/Services/MigrationRunnerService.php` reads migration sequence/checksums, tracks execution in `lpg_migration_history`, and blocks login while required migrations are pending.
- CI: `.github/workflows/ci.yml` provisions MySQL 8, validates PHP syntax, installs Composer dependencies, bootstraps the schema and runs PHPUnit. Its push branches currently name `main` and `qa/workflow-cleanup`; the pull-request trigger targets `main`.
- Tests include `tests/unit/HealthTest.php`, a database example, and Playwright scenarios in `tests/e2e/lpg-runtime.spec.mjs`. The current test set is not yet sufficient for multi-business isolation, customer accounts, API security or ordering.
- This review is based on GitHub source. No application was executed, no live/non-production database was queried, and no CI run is represented as passed.

## 2. Current database shape

Relevant definitions in `database/schema.sql`:

- `locations`: shop/branch records; currently no `business_id`.
- `shop_settings`: one settings row per location, containing POS defaults, stock/credit controls, receipt, SMTP and backup fields.
- `users`: currently has one nullable `location_id`, a `role_id`, username/email and password hash. There is no many-to-many staff/shop assignment.
- `roles`, `permissions`, `role_permissions`: current capability/role mapping.
- `customers`: financial customer master, but currently no `location_id` assignment and no app identity relation.
- `sales`, `customer_receipts`, `customer_security_deposits`, `cylinder_custody`, inventory and cash tables are already location-attributed and must not be reworked casually.
- There are no current business entities, customer app account/sign-up tables, order tables, order status history or order-to-sale link.

## 3. Current authorization/authentication

- `app/Config/Routes.php` registers the current browser workflows under the `auth` filter; no versioned `/api/v1` mobile route group is currently defined.
- `app/Controllers/Auth.php` authenticates staff against `UserModel::findByLogin()` and `password_verify()`, regenerates the CodeIgniter session, stores `user_id`, `location_id`, role code/name and login status in that session.
- `app/Services/PermissionService.php` checks capability codes via `users → role_permissions → permissions`. Its cache is keyed to user ID. It does not enforce business/shop record ownership.
- `app/Controllers/Users.php` saves staff with `location_id = session location`; role permissions are editable using the current UI.
- `app/Controllers/ShopSettings.php` currently permits Shop Settings via `USER_MANAGE` or `INVENTORY_MANAGE`, uses the session's `location_id`, and loads settings for that shop. This behavior must be preserved while scope checks are added.
- `app/Models/ShopSettingsModel.php` persists settings in `shop_settings` and supplies defaults when a row is absent.
- No API token/refresh-session service for mobile clients was identified in the inspected current routes/auth implementation.

## 4. Required changes implied by the approved plan

1. Add a business parent to locations and explicit business/shop assignment relations for Head Office users and staff permitted to serve multiple shops.
2. Add one-shop ownership to customer records in the fresh-install model and a distinct customer mobile identity/signup approval lifecycle.
3. Introduce a reusable server-side scope resolver. Capability permission and business/shop ownership are separate checks; query scope must be applied before data is returned.
4. Inventory and preserve all existing Shop Settings fields, then implement centralized Application/Business/Shop configuration with server-side inheritance only where explicitly allowed.
5. Introduce order persistence and status history before mobile ordering; orders must remain separate from sales/receipts/cash/deposits/cylinder custody/inventory until validated POS fulfilment.
6. Add separate customer/staff API audiences, request validation, session lifecycle, idempotency and API security tests.
7. Expand CI/E2E coverage for two businesses, multiple shops, assigned staff, account approvals, effective setting inheritance, offline order synchronization and duplicate POS fulfilment.

## 5. WP-0 open items and execution gate

- Inspect all consumers of `session()->get('location_id')`, `customers`, `cylinder_types`, `rate_cards`, `shop_settings`, role capabilities and customer identifiers before implementing scope changes. A session-level default location is not sufficient authorization.
- Review all `database/migrations` against the complete numbered list. The migration README's list and sequence guidance have stale references around migration 031; update that documentation as part of repository cleanup.
- Runtime/test execution is not yet available from this source-only review. A disposable MySQL 8 CI run and app-level regression against a safe local/test environment are still required. Do not run destructive refresh migration 011 against production.
- Existing populated-install data mapping is not part of the approved MVP. Do not silently assign historical customers or rewrite historical transactions. If a populated installation is later chosen for an upgrade, obtain a separate reviewed mapping/recovery plan.
- Before implementing token handling, verify available platform/runtime libraries and the exact chosen persistence and revocation design.

## 6. Definition of done for WP-0

- Source inventory and current architecture gaps are documented.
- Relevant code files, tables, routes, permissions, settings and tests are identified.
- Contradictory status/migration notes are corrected.
- Test commands and execution environment are defined, with no unverified PASS claims.
