# Current-State Gap Report — Mobile Multi-Shop Ordering

**Reviewed:** 2026-10-14
**Source branch:** `main` at `0ef0f3ff41eaa4aae1d66c4549892ab77f3898e6`
**Status:** This document describes the historical source gap on `main` at the commit above. The gaps listed in sections 1–4 are baseline findings, not a statement that the current feature branch is unchanged. Implementation has since proceeded on `feature/mobile-multishop-ordering`; see `wp0-source-inventory.md` and `DEVELOPMENT_PLAN.md` for current implementation status. Runtime QA remains pending.

## 1. Role and permission implementation

### What exists today

- `roles` defines named roles.
- `permissions` defines capability codes.
- `role_permissions` associates capabilities with roles.
- `users.role_id` associates a staff user with one role.
- `users.location_id` is a nullable location field.
- `PermissionService::all()` loads the signed-in user's permission codes through `users → role_permissions → permissions`.
- `PermissionService::allows($code)` provides capability checks.
- `PermissionService::syncStandardRolePermissions()` idempotently adds known standard permissions and default assignments for `ADMIN`, `MANAGER` and `CASHIER`; the implementation explicitly says custom roles are not changed.
- `Users::index()` loads role/permission assignments for the existing role UI.
- `Users::rolePermissions()` replaces a role's selected permission mappings transactionally.
- Browser routes are grouped behind the `auth` filter, and individual controllers use `PermissionService::allows()` guards.

### Gaps for the target product

1. Capability permissions alone do not prove that a user may access a particular business, shop, customer or order.
2. A single `users.location_id` does not model a business owner who has authorized access to multiple locations.
3. No business/company parent currently connects locations.
4. Customer rows do not have a shop assignment field.
5. Customer mobile identities and native API tokens are not represented in the reviewed current structure.
6. Current routes are session/browser-oriented; mobile APIs need JSON authentication and authorization behavior.
7. New mobile/order permissions need careful seeding so existing custom role assignments are not silently altered.
8. Permission cache invalidation and scope evaluation must be designed together; do not use the current capability cache as a shop-scope decision.

### Required design

Retain the existing role/permission tables and service as the capability layer. Add a separately testable business/shop scope resolver that uses trusted authenticated identity and explicit assignments. Each protected operation must pass both capability and scope checks. Prefer adding permission codes through the project's existing permission pattern, with reviewed defaults for standard roles; do not grant new order permissions to every custom role automatically.

## 2. Shop and customer schema

### Current source findings

- `locations` exists and represents shops/branches.
- `locations` has no `business_id`.
- `users` has nullable `location_id`.
- `customers` has no `location_id`, and `CustomerModel::$allowedFields` does not allow it.
- `rate_cards.location_id` allows location-specific rates and a nullable/global rate.
- Sales, receipts, deposit records and other transactions have their own location attribution.

### Required target

- Introduce business/company parent entity.
- Every location belongs to exactly one business.
- Every customer belongs to exactly one location.
- A customer mobile account links to an existing customer record, not a duplicate financial profile.
- Every customer order references the customer, assigned location and business.
- Shop ownership and order ownership must be validated on the server and reinforced with database constraints where possible.

### Data setup decision: fresh deployment, no legacy mapping/backfill

The owner confirmed that there is no existing business/customer transactional data to migrate for this initiative. Therefore, a legacy customer-to-shop mapping report and historical backfill are not requirements or release blockers. The fresh-install schema and repeatable seed/setup path must create the business, shop and customer ownership relationships correctly from the start. Every new customer is assigned to exactly one shop during customer creation or approved account setup.

This does not mean a mobile signup can create a duplicate financial customer. The customer app identity must link to one existing customer master in the deployed application; if no suitable master record exists, an authorized Admin/Manager must explicitly select or create the correct customer master as a separate reviewed step before approving the app account. Any future plan to upgrade a populated deployment requires a separate approved data-handling and recovery plan.

## 2A. Configuration management target

The owner requires important settings to be managed through a centralized, permission-aware Configuration area, building on the existing Shop Settings experience. The target has three scopes: Application (System Administrator only), Head Office/Business (limited to that business), and Shop (limited to assigned shop(s)). A Login & Session group belongs at Application scope by default.

This is a target requirement, not a complete source inventory. During WP-0, inspect the existing Shop Settings UI, routes, controllers, models/tables, consumers and tests. Classify settings before moving them. Resolve permitted overrides as Shop → Business → Application; settings that are not explicitly overridable remain at their owning scope. Keep security guardrails at Application scope; lower scopes cannot extend session limits or weaken authentication controls. Audit changes and never return deployment secrets to a client.

See [Module 10 — Centralized Configuration Management](10-configuration-management.md) for the full target design.

## 3. POS and financial integrity

The existing architecture assigns posting logic to application services and uses location-aware transaction records. Mobile ordering must not bypass this.

- Creating or accepting a pending order must not post a sale/payment/cash/ledger/deposit/custody/inventory movement.
- Actual fulfilment must use existing validated SalesService and related services.
- A unique order-to-sale link and idempotency rules must prevent duplicate posting.
- Revalidate stock and price at acceptance/fulfilment; do not trust client totals.
- Keep the four existing POS transaction types isolated and regression-test them.

## 4. First implementation work package

The first implementation step is a code/schema inventory and fresh-install design review; do not make ad hoc controller changes or start mobile UI first.

1. Confirm source schema, migration conventions, permission seeding behavior, current POS posting service and automated/runtime test setup.
2. Finalize business/shop/customer relationships and repeatable fresh-install schema/seeds; do not build a legacy mapping/backfill workflow.
3. Define the business owner vs system administrator boundary and explicit staff multi-shop assignments.
4. Implement and test a server-side business/shop scope resolver without changing existing role semantics.
5. Define customer account creation by Admin/Manager, customer self-registration with pending approval, username/password API login, account recovery and session revocation.
6. Define Head Office business catalog settings and shop-level product activation/deactivation, effective pricing snapshots, price-change review, and delivery/collection options.
7. Implement order persistence and web Order Management before mobile UI. Keep orders separate from POS financial/inventory posting until validated fulfilment.
8. Add API authentication, authorization, idempotency and security tests; then build the separate Customer App and Business/Staff App against the stable API.
9. Test multilingual login selection, offline customer order queueing, customer signup approval, product toggles, price changes, delivery/collection, multi-business isolation and all four existing POS types.

## 5. Confirmed product decisions represented in the plan

- Two separate mobile apps: Customer App and Business/Staff App; both use the authenticated API and never connect directly to MySQL.
- Username/password login through the API over TLS. SMS/OTP is out of scope for the first release.
- Admin/Manager can create customer app accounts; customers may self-register, but an authorized Admin/Manager must approve or reject the request before the customer can log in or order.
- Head Office manages the business product catalog; individual shops can activate/deactivate products according to what they currently sell.
- The server applies existing effective-rate rules and snapshots prices for cloud orders. A changed price is never silently applied to an existing order; offline queued orders require review if price/availability changed before synchronization.
- Customer chooses delivery or collection at checkout; delivery requires an address and any fee/condition must be shown before submission.
- English is the only supported UI language in the initial release; keep strings localization-ready. Add a language selector when a second language is introduced.
- Important settings are managed through one Configuration area at Application, Head Office/Business and Shop scopes, with application-level Login & Session policy and audited, validated override rules.
- The customer app can persist offline queued orders and retry when connectivity returns; a queued local-only order is not visible to a shop until the cloud confirms it.

## 6. Verification limits

This report is based on GitHub source inspection. No production database was queried and no runtime QA or automated test suite was run as part of this report. Do not interpret this document as test evidence.
