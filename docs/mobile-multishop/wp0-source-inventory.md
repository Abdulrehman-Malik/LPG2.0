# WP-0 Source Inventory — Mobile Multi-Shop Ordering

**Reviewed:** 2026-10-14  
**Repository:** `Abdulrehman-Malik/LPG2.0`  
**Main baseline:** `0ef0f3ff41eaa4aae1d66c4549892ab77f3898e6`  
**Implementation branch:** `feature/mobile-multishop-ordering`  
**Status:** Source-level inventory complete for the feature branch; runtime verification is still pending. The notes in sections 2–3 describe the original main-branch baseline, not the current feature branch.

## 1. Runtime and project shape

- Framework/runtime: CodeIgniter 4, PHP `^8.2`, MySQL 8/InnoDB.
- Dependencies/tests are declared in `composer.json` and `phpunit.dist.xml`.
- Fresh database schema: `database/schema.sql`.
- Numbered SQL upgrade scripts: `database/migrations/NNN_*.sql`; `app/Services/MigrationRunnerService.php` reads migration sequence/checksums, tracks execution in `lpg_migration_history`, and blocks login while required migrations are pending.
- CI: `.github/workflows/ci.yml` provisions MySQL 8, validates PHP syntax, installs Composer dependencies, bootstraps the schema, applies the mobile-order migrations and runs PHPUnit. Pushes to `feature/**` run the workflow; PRs to `main` also run it.
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
- Runtime tests still require a configured QA web server, a disposable MySQL database with safe test data, and browser/API credentials for multiple businesses/shops.
- No live browser session or deployed QA API has been exercised in this review. The CI workflow covers syntax/schema setup/PHPUnit, but it is not a substitute for a running app with customer/staff sessions. Do not run destructive refresh migration 011 against production.
- Existing populated-install data mapping is not part of the approved MVP. Do not silently assign historical customers or rewrite historical transactions. If a populated installation is later chosen for an upgrade, obtain a separate reviewed mapping/recovery plan.
- Token/session persistence and revocation are implemented in `ApiSessionService`; the remaining gate is live token expiry/rotation/reuse-revocation and disabled-account testing.

## 6. Definition of done for WP-0

- [x] Source inventory and baseline architecture gaps are documented.
- [x] Relevant current code files, tables, routes, permissions, settings and tests are identified.
- [x] Stale source-status statements are reconciled in `DEVELOPMENT_PLAN.md` and the migration sequence table lists 001–039.
- [x] CI commands and runtime browser-QA gate are documented, with no unverified PASS claims.

## 7. Feature-branch implementation snapshot

The following code has been added or confirmed on `feature/mobile-multishop-ordering` after the main baseline:

- Ownership/schema layer: businesses, shops, customer-to-shop ownership, staff business/shop access, business catalog, per-shop catalog availability, customer mobile accounts, pending signup requests, API sessions/access/refresh tokens, orders, order lines, status history, selected physical-cylinder snapshots, delivery fee accounting and unique order-to-sale linkage.
- Scope/security layer: `ShopScopeService`, capability permissions, separate customer/staff API audiences, token expiry/rotation/revocation, API readiness gate and centrally resolved application/business/shop settings.
- Customer services/API: registration-shop discovery, signup and approval lifecycle, account/profile/password controls, available catalog, order create/list/detail/cancel, price reconfirmation and idempotency.
- Office/staff services/API: accessible business/shop lookup and provisioning, staff access mapping, business catalog and per-shop availability, customer account approval/rejection, shop-scoped order queue/status, physical-cylinder selection and validated POS fulfilment.
- Browser operations: Businesses & Shops, Customer Ordering Catalog, Customer App Accounts and Customer Orders pages; all routes are explicitly registered and use current session permissions and shop/business scope.
- CI coverage is being expanded to assert migrations 037–039 as well as the delivery-fee/order linkage/selected-cylinder schema. Live role-based browser and multi-shop API tests remain not executed.

The Flutter projects themselves (WP-7/WP-8) are outside the current web/backend-only task and remain pending.
