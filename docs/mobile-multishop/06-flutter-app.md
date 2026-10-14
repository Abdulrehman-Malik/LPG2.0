# Module 06 — Flutter Android/iOS App

## Technology

Use Flutter for two separate mobile apps targeting Android and iOS: Customer App and Business/Staff App. Shared components may be reused, but packages, navigation, release identities and authorization boundaries remain separate. Both are clients only; PHP/CodeIgniter owns business rules, authorization and persistent state.

## App and authentication boundaries

Both apps use English as the only supported UI language in the initial release. Structure UI strings for localization so additional languages can be added later without redesigning screens or business logic. Use username/password through the API over TLS; SMS/OTP is out of scope for the initial release. After sign-in, fetch account status, capabilities and scope from `GET /api/v1/me`; local preferences never grant permissions.

- **Customer App:** customer home, catalog, cart, checkout, own orders, support and signup/approval states. Pending signups receive no ordering access and must not create a duplicate financial customer.
- **Business/Staff App:** shop selector where permitted, pending customer signups, pending orders, order actions and permitted product/shop settings.
- Customer and internal staff identities remain separate. Do not expose staff workflows in the Customer App or customer ordering workflows in the Business/Staff App.

## Customer screens

1. English UI; username/password sign-in and recovery/help. Keep UI text localization-ready for future languages.
2. Customer self-registration with a clear pending-approval state; no ordering access until approved.
3. Shop identity shown read-only after account assignment.
4. Head Office-defined catalog filtered by shop-level product activation/deactivation.
5. Cart with quantities and delivery/collection choice; delivery requires an address and shows any configured fee/condition.
6. Server-calculated quote and order review with price snapshot.
7. Offline queue with a clear notice that a local-only order has not reached the shop; require review if price/availability changed before synchronization.
8. Confirmation with public order number after cloud acknowledgement.
9. Own order history/status details, profile and support.

## Staff/owner screens

1. English UI and username/password sign-in; keep UI text localization-ready for future languages.
2. Shop/business context.
3. Pending customer signup approval queue and permissioned account create/reset actions.
4. Business catalog administration and per-shop product activation/deactivation.
5. Pending order queue with filters and refresh.
6. Order details including customer contact, item/quantity, address or collection, notes, price snapshot and timeline.
7. Accept/reject with reason as required.
8. Update allowed fulfilment status.
9. Owner cross-shop view, only where authorized.
10. Error/retry states and network connectivity feedback.

The central Configuration editor remains in the authenticated web application for the MVP, preserving one source of truth. The Business/Staff App may display a permission-filtered entry or read-only effective-setting summary where useful, but must not duplicate configuration storage or expose Application Login & Session editing to ordinary business/shop users.
## UX requirements

- Clear distinction between order status and sale/payment status.
- Loading, empty, offline, retry, validation and permission-denied states.
- Prevent double taps and show submission state; rely on server idempotency too.
- Do not calculate authoritative totals locally; display server quotes.
- Do not display other shops' stock, customer details or order queues.
- Accessible touch targets, concise text, responsive layouts and localization-ready UI strings. English is the only initial language; future language additions should not require business-logic changes.
- Do not store sensitive order data longer than needed in local caches.

## Build and release

- Two separately packaged Flutter apps with separate application IDs, navigation, release packages and store listings; each targets Android and iOS. Shared widgets/API clients may live in common code.
- Separate development/test/production API base URLs via build configuration, not hard-coded source URLs.
- Secrets must remain server-side.
- Android signing and Play Store release require release credentials.
- iOS build/signing requires macOS/Xcode and Apple developer release setup.
- Push notifications can follow after in-app order refresh/status flow is reliable.

## Acceptance criteria

- Customer App exposes customer capabilities only; Business/Staff App exposes authorized staff/Head Office capabilities only.
- Changing local role, language, shop state or local configuration does not grant server permissions or change effective server settings.
- Configuration views display effective value and source scope where applicable; unauthorized settings are neither editable nor returned by the API.
- Orders/status remain consistent across mobile and web.
- Android and iOS builds use the same API contract and pass the same functional scenarios.
