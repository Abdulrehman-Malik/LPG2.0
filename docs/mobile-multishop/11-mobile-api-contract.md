# Mobile API Contract — Current Implementation

This document records the current API surface on `feature/mobile-multishop-ordering`. The PHP API is being built before the two independent Flutter applications. Do not treat a route's existence as proof that its end-to-end mobile screen or store release is complete.

## Authentication

- Base prefix: `/api/v1`
- Customer and Business/Staff App use different audiences and token sessions.
- Authenticated calls send `Authorization: Bearer <access_token>`.
- Refresh and logout are audience-specific.
- MySQL is never accessed directly by either mobile app.
- The server remains authoritative for shop scope, effective settings, catalog availability, prices and order state.

## Customer App

| Method | Route | Purpose |
|---|---|---|
| POST | `/customer/auth/signup` | Submit customer app signup for approval |
| POST | `/customer/auth/login` | Sign in approved customer |
| POST | `/customer/auth/refresh` | Rotate customer refresh token |
| POST | `/customer/auth/logout` | Revoke current customer session |
| POST | `/customer/auth/change-password` | Change customer password |
| GET | `/customer/me` | Current linked customer profile |
| GET | `/customer/products` | Effective shop catalog, availability, delivery/collection switches and fee |
| GET | `/customer/orders` | List own orders |
| POST | `/customer/orders` | Create a pending order; requires an idempotency key |
| GET | `/customer/orders/{orderId}` | View own order and status history |
| POST | `/customer/orders/{orderId}/cancel` | Cancel an eligible pending order |
| POST | `/customer/orders/{orderId}/reconfirm-price` | Reconfirm a revised quote |

Customer ownership is checked server-side. Creating, cancelling or reconfirming an order does not itself post a sale, receipt, customer outstanding balance, cash entry, deposit or inventory movement.

## Business/Staff App

| Method | Route | Purpose |
|---|---|---|
| POST | `/staff/auth/login` | Staff sign-in |
| POST | `/staff/auth/refresh` | Rotate staff refresh token |
| POST | `/staff/auth/logout` | Revoke current staff session |
| POST | `/staff/auth/change-password` | Change staff password |
| GET | `/staff/me` | Current staff identity and permitted context |
| GET | `/staff/shops` | Accessible shops |
| GET/POST | `/staff/businesses` | List accessible businesses / create a business where authorized |
| GET/POST | `/staff/businesses/{businessId}/shops` | List or provision shops within the business |
| GET/POST | `/staff/businesses/{businessId}/staff-access` | Review or change Head Office/shop access assignments |
| GET/POST | `/staff/businesses/{businessId}/catalog` | View or manage the business catalog |
| GET | `/staff/shops/{shopId}/products` | View products and shop activation state |
| POST | `/staff/shops/{shopId}/products/{productId}/availability` | Activate/deactivate a business product for one shop |
| GET | `/staff/orders` | List orders within the selected authorized shop |
| GET | `/staff/orders/{orderId}` | View order detail and status history |
| POST | `/staff/orders/{orderId}/status` | Advance/reject/cancel within the allowed order state machine |
| GET | `/staff/configuration/application` | Read application settings if authorized |
| GET | `/staff/configuration/businesses/{businessId}` | Read effective business configuration if authorized |
| GET | `/staff/configuration/shops/{shopId}` | Read effective shop configuration if authorized |
| POST | `/staff/configuration/save` | Save an allowed typed setting at an authorized scope |
| POST | `/staff/configuration/reset` | Clear a business/shop override and inherit the parent value |

The configuration endpoints reuse `ConfigurationService` and enforce capability permission plus business/shop scope on the server. Application-level security controls are not editable by ordinary business or shop accounts.

## Order lifecycle and financial boundary

- Order submission stores an order and quote snapshots; it is not a POS sale.
- The shop may accept only when the current quote is still valid and no customer price reconfirmation is pending.
- Order status changes must not create financial postings.
- The final `completed` transition remains blocked until fulfillment can call the existing `SalesService` with a safe, atomic order-to-sale link and reconcile the final price (including physical cylinder gas weight and delivery fee). Do not bypass this gate or mark an order completed manually.
- POS mixed-cart sales now persist `delivery_fee_amount` as a separate sale component, and `SalesService::postForCustomerOrder()` is the internal entry point for supplying it. This is groundwork only; the order service does not yet call it and fulfillment is not enabled.
- A unique `customer_orders.pos_sale_id` exists in the schema as the link to the eventual POS sale. Completion needs an idempotent recovery path so retries cannot create duplicate sales.

## Implementation status and next gates

1. **Backend API foundation:** auth, customer catalog/order APIs, staff order/catalog APIs, business/shop provisioning and scoped configuration APIs are present on this feature branch.
2. **Verification:** run the repository's PHP syntax, database/schema smoke and PHPUnit workflow after the latest commit; successful earlier runs do not replace checking the current head.
3. **Next implementation:** complete transaction-safe POS fulfillment for single and mixed carts. Delivery-fee persistence and a guarded SalesService entry point are now present, but still need order validation/linking. Remaining gates are explicit physical cylinder selection, exact quote reconciliation before commit, payment capture, inventory-lock/transaction boundary safety, idempotency and recovery after a partial failure.
4. **Then:** build the two separate Flutter applications (Customer and Business/Staff) against this contract, using secure platform token storage and role-aware navigation.
5. **Release gate:** no merge to `main` until permissions, migrations, tests, fulfillment atomicity and both apps have been reviewed.

Initial UI language is English only. Keep user-facing strings localization-ready; language support must only be enabled once translations are implemented and tested.
