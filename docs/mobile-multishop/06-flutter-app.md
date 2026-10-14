# Module 06 — Flutter Android/iOS App

## Technology

Use Flutter for a single shared mobile codebase targeting Android and iOS. The Flutter app is a client only; PHP/CodeIgniter owns business rules, authorization and persistent state.

## Authentication-driven navigation

After authentication, fetch the trusted account type, allowed capabilities and permitted shop context from `GET /api/v1/me`. Do not let a local preference grant a role.

- Customer account: Customer Home, Catalog, Cart, Checkout, My Orders, Order Details, Profile/Support.
- Shop staff account: Assigned Shop selector where permitted, Pending Orders, Order Details, accept/reject, preparation/fulfilment status, history.
- Business owner: business-level overview, filter by authorized shop, cross-shop pending orders and permitted reports/settings.
- A person with both customer and staff accounts must use explicit account switching/re-authentication or a carefully reviewed dual-role flow; never combine privileges implicitly.

## Customer screens

1. Phone verification/sign-in.
2. Shop identity shown read-only after account assignment.
3. Product/cylinder-type catalogue with server-supplied prices and availability.
4. Cart with quantities and delivery/collection choice.
5. Address/instructions if delivery is enabled.
6. Server-validated order review and submission.
7. Confirmation with order number.
8. Order history and status details.
9. Profile and account support.

## Staff/owner screens

1. Secure sign-in.
2. Shop/business context.
3. Pending order queue with filters and refresh.
4. Order details including customer contact, item/quantity, address or collection, notes, price snapshot and timeline.
5. Accept/reject with reason as required.
6. Update allowed fulfilment status.
7. Owner cross-shop view, only where authorized.
8. Error/retry states and network connectivity feedback.

## UX requirements

- Clear distinction between order status and sale/payment status.
- Loading, empty, offline, retry, validation and permission-denied states.
- Prevent double taps and show submission state; rely on server idempotency too.
- Do not calculate authoritative totals locally; display server quotes.
- Do not display other shops' stock, customer details or order queues.
- Accessible touch targets, concise text, responsive layouts, and localization readiness (English/Urdu decision can be made in implementation planning).
- Do not store sensitive order data longer than needed in local caches.

## Build and release

- One Flutter project with Android and iOS targets.
- Separate development/test/production API base URLs via build configuration, not hard-coded source URLs.
- Secrets must remain server-side.
- Android signing and Play Store release require release credentials.
- iOS build/signing requires macOS/Xcode and Apple developer release setup.
- Push notifications can follow after in-app order refresh/status flow is reliable.

## Acceptance criteria

- Same app build presents correct authorized experience for customer, shop staff and owner accounts.
- Changing local role state does not grant server privileges.
- Orders/status remain consistent across mobile and web.
- Android and iOS builds use the same API contract and pass the same functional scenarios.
