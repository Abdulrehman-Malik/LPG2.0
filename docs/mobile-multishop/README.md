# Mobile App + Multi-Shop Ordering — Requirements Pack

**Status:** The feature branch contains the customer/staff API foundation, scoped business/shop services, browser Business & Shops administration, Customer Ordering Catalog, Customer App Accounts and Customer Orders/fulfilment screens, plus initial Flutter client source flows. CI currently verifies PHP/schema/PHPUnit checks; live browser, mobile/device, and two-shop financial/isolation testing remain required. Android/iOS runner projects/signing and store builds are not complete.
**Working branch:** `feature/mobile-multishop-ordering`
**Baseline:** `main` at `0ef0f3ff41eaa4aae1d66c4549892ab77f3898e6` (verified 2026-10-14). Do not modify `main` as part of this work.

## Product goal

Deliver two separate Flutter apps for Android and iOS: a Customer App and a Business/Staff App, with separate packages, navigation and authorization boundaries. The system supports multiple independent businesses, each with its own Head Office and shops. Each customer is assigned to exactly one shop; one shop can have many customers. Customers can order only from their assigned shop. A business owner can oversee all shops within their business; shop managers and order staff are limited to explicitly assigned shops.

The existing CodeIgniter/PHP application and MySQL database remain the business system of record. The mobile app must use a versioned, authenticated API and must not connect directly to MySQL.

## Non-negotiable rules

1. Preserve existing POS, sales, cash, inventory, customer ledger, security-deposit, cylinder-custody, audit and role/permission behavior unless a specific change is approved and tested.
2. An order is not a posted sale. Submitting or accepting an order must not accidentally create revenue, payment, cash movement, customer OS, security-deposit activity, cylinder custody, or inventory movement.
3. Use existing validated application services for any eventual POS posting. Do not duplicate financial/business rules in Flutter.
4. Every order belongs to exactly one business, one shop/location and one customer. The customer's assigned shop must equal the order's shop.
5. Enforce permissions and business/shop data scope on the server for every request. Hiding UI controls is not authorization.
6. Keep existing role/permission tables and PermissionService. Extend them; do not replace them with a second authorization system.
7. Design repeatable fresh-install schema/seeds and a reviewed upgrade path. The owner confirmed no existing business/customer transactional data needs migration for this initiative, so no legacy customer-to-shop mapping/backfill is required. Never drop or silently reassign existing data.
8. Keep work on the feature branch; do not merge to `main` without explicit approval.
9. Follow `agent.md`, `TESTING.md`, and `SQA.md`. Execute impacted runtime QA after screen changes; do not claim QA passed without evidence.

## Current architecture findings from source review

- `locations` already represents shops/branches.
- `users.location_id` currently associates a staff user with one location (nullable).
- `shop_settings.location_id`, `rate_cards.location_id`, sales and other operational records are location-aware.
- `roles`, `permissions`, and `role_permissions` implement database-backed role permissions.
- `PermissionService::allows()` checks capability permissions, and `syncStandardRolePermissions()` maintains standard permissions for ADMIN, MANAGER and CASHIER while leaving custom roles alone.
- The current permission model is capability-oriented; business-wide versus shop-specific data scope is implemented in the feature API layer but still requires end-to-end authorization review.
- Current `customers` schema/model has no `location_id`; the fresh-install schema must add required customer-to-shop ownership. No legacy customer mapping/backfill is required for this initiative.
- Current `locations` schema has no business/company parent; business ownership requires a planned schema extension.
- The mobile API route contract is now implemented under `/api/v1`; see [the actual route contract](11-mobile-api-contract.md). Token/session, cross-shop and concurrent fulfillment behavior still requires runtime verification.

These are source-code observations, not runtime test results.

## First read

- [Current-state gap report](00-current-state-gap-report.md) — reviewed existing roles/permissions, customer/location schema gaps, POS boundaries and safe first implementation work package.
- [Centralized Configuration Management](10-configuration-management.md) — scope inheritance, Application Login & Session settings, security guardrails and audit requirements.
- [Mobile API Contract](11-mobile-api-contract.md) — implemented route inventory and the remaining POS fulfillment safety gate.

## Module index and delivery sequence

| Module | Scope | Status |
|---|---|---|
| 01 | Product scope and target architecture | Documented |
| 02 | Business, shops, staff roles and permission/scope model | Documented |
| 03 | Customer records, mobile identity and single-shop assignment | Documented |
| 04 | Order lifecycle and safe POS integration | Backend + web queue/fulfillment implemented; CI checks pass on recent commits; live integration tests pending |
| 05 | API contracts, authentication and security | Versioned API/session implementation and actual route contract documented; security runtime tests pending |
| 06 | Flutter app experience and role-based navigation | Initial customer/staff flows implemented in Dart; runner projects and device QA pending |
| 07 | Database migration and legacy-data plan | Documented |
| 08 | QA, rollout and release gates | Documented |
| 09 | Dependency-ordered implementation plan and decision register | Documented |
| 10 | Centralized Configuration across Application, Head Office/Business and Shop scopes | Central resolver, web UI and staff API routes implemented; inheritance/permissions require runtime QA |
| 11 | Mobile API contract and implementation status | Documented |

## Implementation sequence for the coding agent

Read [the detailed implementation plan and decision register](09-implementation-plan-and-decision-register.md) before starting any coding. It defines work packages WP-0 through WP-10, dependencies, exit criteria, acceptance requirements and release gates.

Review [Module 10 — Centralized Configuration Management](10-configuration-management.md) alongside role/scope and API-security requirements.

Implementation is underway only on `feature/mobile-multishop-ordering`. Keep all changes off `main`; do not merge without explicit approval. Do not build legacy customer mapping/backfill tooling for this deployment.

The delivered web/backend scope is: source inventory and fresh-install schema → business/shop authorization → customer identity/signup approval + account password reset → order data/state machine → versioned customer/staff API → browser catalogue, accounts, businesses/shops and order fulfillment → validated POS fulfilment integration. Remaining release gates are live security/financial regression, two-shop pilot, Flutter runner/signing/device verification, and owner acceptance.

## Decisions already fixed

- Two separate Flutter apps for Android and iOS: Customer App and Business/Staff App; shared components do not merge their packages or authorization boundaries.
- Multiple independent businesses are supported; each has its own Head Office and shops.
- A customer belongs to exactly one shop and can order only from that shop.
- One order belongs to one shop; no split order across shops in the MVP.
- Head Office can view/manage permitted functions for its own business; Shop Admin has full shop-level access only within their shop.
- Shop manager/order staff can access only assigned shop(s), as permitted.
- Existing LPG web application remains the system of record.
- Username/password login is via the API over TLS; SMS/OTP is out of scope for the first release.
- Admin/Manager may create a customer account; customers may self-register, but an Admin/Manager must approve the signup before login/order access.
- Head Office defines the business product catalog; each shop activates/deactivates products based on what it currently sells.
- Customer chooses delivery or collection; delivery requires an address and any configured fee/condition is shown before submission.
- The server calculates prices and saves a cloud-order price snapshot; changes require customer review/reconfirmation, never silent repricing.
- English is the only initial UI language in both apps; keep the UI localization-ready so more languages can be added later.
- Important settings are managed in one Configuration area with Application, Head Office/Business and Shop scopes; settings inherit only where explicitly allowed, and Login & Session policy is Application-owned by default.
- MVP order is pending until accepted by authorized staff.
- No online payment, automated deposit, or automatic cylinder custody in the first release unless separately approved.

## Decisions to validate before production implementation

- Future language additions and translations. A language selector should be introduced when a second language is added.
- Whether one staff user may be assigned to multiple shops.
- Exact cancellation window and status transitions.
- Final per-shop delivery settings, including optional fee/service-area/slot rules; the customer choice between delivery and collection is confirmed.
- During WP-0, verify and document the application's existing effective-rate precedence and shop/global fallback behavior. The API must reuse it; price snapshot/reconfirmation behavior is already fixed.
- Whether owner role should be a new role code or an existing configurable role with business-wide scope.

