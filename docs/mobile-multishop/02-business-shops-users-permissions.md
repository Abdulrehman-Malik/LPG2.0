# Module 02 — Business, Shops, Users, Roles and Permissions

## Current implementation found

- `locations` is the current shop/branch entity.
- `users.location_id` can identify a staff user's location.
- `roles`, `permissions`, and `role_permissions` are the existing permission tables.
- `PermissionService::allows($permission)` resolves a signed-in user's permissions through their role.
- `PermissionService::syncStandardRolePermissions()` ensures standard permissions and default assignments for ADMIN, MANAGER and CASHIER; comments explicitly state custom roles are not changed.
- The current model is capability/role based. It does not by itself enforce row-level business/shop scope for every record.
- `Users::save()` currently sets staff `location_id` from the current session. This is insufficient for a business owner who needs explicit access across multiple shops.
- Keep current permission management UI and role assignments compatible.

## Target business hierarchy

- One `business` owns one or more `locations` (shops).
- Every shop belongs to exactly one business.
- A business owner can access authorized records across all locations in their business.
- A shop manager/order staff member can access only the location(s) explicitly assigned to them.
- System administrator and business owner are different concepts; do not automatically grant a business owner global system-administrator access.
- Staff account-to-shop assignment is independent from the permission codes assigned to a role.

## Suggested authorization design

Use two independent checks for every protected operation:

1. **Capability check:** does the authenticated user have the required permission code?
2. **Scope check:** does the requested business/location/record belong to the business or shop scope granted to that user?

Do not rely on role names alone. Prefer permission codes and explicit scope services. A role may grant capabilities; a separate business/shop assignment or scope rule constrains which records the user may access.

Proposed capabilities to review against existing permissions before adding:
- `ORDER_VIEW`
- `ORDER_ACCEPT`
- `ORDER_REJECT`
- `ORDER_STATUS_UPDATE`
- `ORDER_CANCEL`
- `SHOP_VIEW`
- `SHOP_MANAGE`
- `BUSINESS_REPORT_VIEW`
- `CUSTOMER_ACCOUNT_LINK`

Use the existing `permissions` and `role_permissions` tables; do not create a second role-permission store. Avoid seeding new permissions into custom roles automatically. Any standard-role defaults must be explicit, idempotent, documented, and covered by migration tests.

## Role behavior to support

- Business owner: business-level order/shop overview and permitted reports; may manage shop assignments if explicitly granted.
- Shop manager: manage orders and shop functions at assigned shop(s).
- Order staff: view/update orders only for assigned shop(s), with no access to unrelated financial/admin screens unless separately permitted.
- Customer: a separate customer identity and authorization path; must not be treated as an internal `users` role unless a security review approves that approach.

These are product capabilities, not a mandate to hard-code role names into every controller. Existing custom roles should remain configurable.

## Required safeguards

- Enforce authorization in API controllers/services and relevant web controller paths.
- Scope order/customer/shop queries at the database query level, not only after loading unrestricted data.
- Never accept a client-provided business ID or location ID as authorization proof.
- Validate that a staff user is assigned to a shop before viewing or changing an order.
- Validate owner business membership before listing cross-shop orders.
- Prevent privilege escalation through role or shop assignment forms.
- Clear/partition PermissionService caches when user identity changes; do not assume a static permission cache is a complete scope cache.
- Audit role changes, staff-shop assignments, and owner-level actions.

## Acceptance tests

- Shop A staff cannot view or mutate Shop B orders by changing URL, query parameters, JSON IDs, or HTTP methods.
- Business A owner cannot view Business B records.
- Owner can access each authorized shop without becoming a system administrator.
- Existing ADMIN/MANAGER/CASHIER and custom roles retain their current effective permissions unless an approved migration explicitly changes them.
- UI visibility and direct-request authorization agree.
