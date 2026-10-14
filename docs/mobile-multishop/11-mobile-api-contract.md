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
| GET | `/staff/orders/{orderId}/fulfillment-options` | List eligible physical cylinders per order line within the authorized shop |
| POST | `/staff/orders/{orderId}/fulfill` | Select physical cylinders, confirm the current quote, collect payment details and post the order through the existing POS service |
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
- Staff fulfillment is exposed at `POST /staff/orders/{orderId}/fulfill`. The request contains `fulfillment_lines` (each `line_no` plus physical `selected_cylinder_unit_ids` for cylinder products or `source_cylinder_unit_id` for gas refill) and a `payments` array using the existing POS payment modes.
- Request body shape:
  ```json
  {
    "fulfillment_lines": [
      { "line_no": 1, "source_cylinder_unit_id": 101 },
      { "line_no": 2, "selected_cylinder_unit_ids": [201, 202] }
    ],
    "payments": [
      { "payment_mode": "cash", "amount": 2500.00 }
    ]
  }
  ```
  Use `source_cylinder_unit_id` for a gas-only refill line; use `selected_cylinder_unit_ids` for filled/empty cylinder lines. Payment amounts must equal the server-calculated final total. The delivery fee is taken from the saved order, never trusted from the mobile request.
- Physical filled-cylinder IDs and actual gas weights are persisted in the order quote. If selection or weight changes the total, the service saves a revised quote, returns `reconfirmation_required`, and does not post a sale until the customer reconfirms and the shop accepts again.
- POS posting records `sales.customer_order_id` with a unique constraint and sets `customer_orders.pos_sale_id`, `status=completed`, and completion history in the same POS database transaction. Delivery fee is a separate `sales.delivery_fee_amount` component. This prevents a retry from posting a duplicate sale or leaving a committed sale without its order completion link.
- Order status changes alone still do not post financial entries. Do not bypass the fulfillment endpoint or mark an order completed manually.

## Implementation status and next gates

1. **Backend API foundation:** auth, customer catalog/order APIs, staff order/catalog APIs, business/shop provisioning and scoped configuration APIs are present on this feature branch.
2. **Verification:** run the repository's PHP syntax, database/schema smoke and PHPUnit workflow after the latest commit; successful earlier runs do not replace checking the current head.
3. **POS fulfillment backend:** the staff route and service now connect customer orders to existing mixed-cart POS posting with explicit physical cylinder selection, delivery-fee accounting, price reconfirmation, payment validation and a unique recoverable order-to-sale link. Run the current PHP/schema smoke and PHPUnit workflow and review edge cases before treating this as release-ready.
4. **Then:** build the two separate Flutter applications (Customer and Business/Staff) against this contract, using secure platform token storage and role-aware navigation.
5. **Release gate:** no merge to `main` until permissions, migrations, tests, fulfillment atomicity and both apps have been reviewed.

Initial UI language is English only. Keep user-facing strings localization-ready; language support must only be enabled once translations are implemented and tested.
