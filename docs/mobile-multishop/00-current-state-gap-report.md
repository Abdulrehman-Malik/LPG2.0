# Current-State Gap Report — Mobile Multi-Shop Ordering

**Reviewed:** 2026-10-13
**Source branch:** `main` at `9abc7d9a417d0508ebc8afd04da3e3d7a15d9038`
**Status:** Initial source review complete. No application/schema behavior has been changed in this requirements branch.

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

### Migration blocker

A safe backfill cannot be inferred merely from the currently logged-in staff location. Before adding a non-null customer location, inspect customers and their posted sales/receipts/security deposits/custody history by location. Customers with no clear owner or transactions across shops need an explicit mapping decision. Produce a report and request owner approval before data is updated.

## 3. POS and financial integrity

The existing architecture assigns posting logic to application services and uses location-aware transaction records. Mobile ordering must not bypass this.

- Creating or accepting a pending order must not post a sale/payment/cash/ledger/deposit/custody/inventory movement.
- Actual fulfilment must use existing validated SalesService and related services.
- A unique order-to-sale link and idempotency rules must prevent duplicate posting.
- Revalidate stock and price at acceptance/fulfilment; do not trust client totals.
- Keep the four existing POS transaction types isolated and regression-test them.

## 4. First implementation work package

Do not begin by making `customers.location_id` non-null or changing authorization in controllers ad hoc.

1. Inspect current schema, migrations and representative database records in a safe non-production copy.
2. Produce a customer/location/history mapping report with unresolved cases listed separately.
3. Define the business owner vs system administrator distinction and staff multi-shop assignment requirement.
4. Implement and test a scope resolver and shop assignments without changing existing role semantics.
5. Add restart-safe migration(s) for business/location/customer relationships and test on fresh plus existing databases.
6. Only then add order persistence and staff order management.
7. Add mobile API auth, authorization, idempotency and security tests.
8. Build Flutter UI after backend contracts and web order workflow are stable.

## 5. Verification limits

This report is based on GitHub source inspection. No production database was queried, no legacy customer mapping was executed, and no runtime QA or automated test suite was run as part of this report. Do not interpret this document as test evidence.
