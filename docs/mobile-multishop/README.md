# Mobile App + Multi-Shop Ordering — Requirements Pack

**Status:** Development planning pack complete; implementation has not started. Business decisions and legacy data mapping remain approval gates. This branch currently contains documentation-only changes.
**Working branch:** `feature/mobile-multishop-ordering-requirements`
**Baseline:** current `main` as of 2026-10-13. Do not merge or modify `main` as part of this work.

## Product goal

Deliver one Flutter app for Android and iOS with role-based Customer and Shop Staff/Owner experiences. A single business may own multiple shops/locations. Each customer is assigned to exactly one shop; one shop can have many customers. Customers can order only from their assigned shop. A business owner can oversee all shops within their business; shop managers and order staff are limited to explicitly assigned shops.

The existing CodeIgniter/PHP application and MySQL database remain the business system of record. The mobile app must use a versioned, authenticated API and must not connect directly to MySQL.

## Non-negotiable rules

1. Preserve existing POS, sales, cash, inventory, customer ledger, security-deposit, cylinder-custody, audit and role/permission behavior unless a specific change is approved and tested.
2. An order is not a posted sale. Submitting or accepting an order must not accidentally create revenue, payment, cash movement, customer OS, security-deposit activity, cylinder custody, or inventory movement.
3. Use existing validated application services for any eventual POS posting. Do not duplicate financial/business rules in Flutter.
4. Every order belongs to exactly one business, one shop/location and one customer. The customer's assigned shop must equal the order's shop.
5. Enforce permissions and business/shop data scope on the server for every request. Hiding UI controls is not authorization.
6. Keep existing role/permission tables and PermissionService. Extend them; do not replace them with a second authorization system.
7. Use backward-compatible migrations, with explicit legacy-data mapping and rollback/recovery instructions. Never drop or silently reassign existing data.
8. Keep work on the feature branch; do not merge to `main` without explicit approval.
9. Follow `agent.md`, `TESTING.md`, and `SQA.md`. Execute impacted runtime QA after screen changes; do not claim QA passed without evidence.

## Current architecture findings from source review

- `locations` already represents shops/branches.
- `users.location_id` currently associates a staff user with one location (nullable).
- `shop_settings.location_id`, `rate_cards.location_id`, sales and other operational records are location-aware.
- `roles`, `permissions`, and `role_permissions` implement database-backed role permissions.
- `PermissionService::allows()` checks capability permissions, and `syncStandardRolePermissions()` maintains standard permissions for ADMIN, MANAGER and CASHIER while leaving custom roles alone.
- The current permission model is capability-oriented; business-wide versus shop-specific data scope is not yet a complete authorization layer.
- Current `customers` schema/model has no `location_id`; assigning each customer to exactly one shop therefore requires a planned schema migration and legacy-data backfill.
- Current `locations` schema has no business/company parent; business ownership requires a planned schema extension.
- Current routes are browser/session-oriented and do not yet expose the planned customer mobile API.

These are source-code observations, not runtime test results.

## First read

- [Current-state gap report](00-current-state-gap-report.md) — reviewed existing roles/permissions, customer/location schema gaps, POS boundaries and safe first implementation work package.

## Module index and delivery sequence

| Module | Scope | Status |
|---|---|---|
| 01 | Product scope and target architecture | Documented |
| 02 | Business, shops, staff roles and permission/scope model | Documented |
| 03 | Customer records, mobile identity and single-shop assignment | Documented |
| 04 | Order lifecycle and safe POS integration | Documented |
| 05 | API contracts, authentication and security | Documented |
| 06 | Flutter app experience and role-based navigation | Documented |
| 07 | Database migration and legacy-data plan | Documented |
| 08 | QA, rollout and release gates | Documented |
| 09 | Dependency-ordered implementation plan and decision register | Documented |

## Implementation sequence for the coding agent

Read [the detailed implementation plan and decision register](09-implementation-plan-and-decision-register.md) before starting any coding. It defines work packages WP-0 through WP-10, dependencies, exit criteria, acceptance requirements and release gates.

Implementation is **not authorized yet**. First review and approve the decision register, then complete WP-1 legacy data discovery using a safe non-production database copy. Do not make application/schema changes until the required business decisions and customer-to-shop mapping are approved.

The ordered work is: source inventory and decisions → legacy customer/shop mapping → business/shop scope and customer identity → order data/state machine → backend services and web Order Management → versioned API/security → Flutter customer experience → Flutter staff/owner experience → POS fulfilment reconciliation → two-shop pilot and release.

## Decisions already fixed

- One mobile app, Flutter, Android and iOS.
- One business may own multiple shops.
- A customer belongs to exactly one shop and can order only from that shop.
- One order belongs to one shop; no split order across shops in the MVP.
- Business owner can oversee all shops in their business.
- Shop manager/order staff can access only assigned shop(s), as permitted.
- Existing LPG web application remains the system of record.
- MVP order is pending until accepted by authorized staff.
- No online payment, automated deposit, or automatic cylinder custody in the first release unless separately approved.

## Decisions to validate before production implementation

- OTP/SMS provider and account-recovery process.
- Whether one staff user may be assigned to multiple shops.
- Exact cancellation window and status transitions.
- Delivery fee, service area and delivery-slot rules.
- Whether customer-facing rates use shop-specific rate cards or a business-level default with shop override.
- Whether owner role should be a new role code or an existing configurable role with business-wide scope.
- How existing customers are mapped to shops during migration. Never guess this mapping silently.

