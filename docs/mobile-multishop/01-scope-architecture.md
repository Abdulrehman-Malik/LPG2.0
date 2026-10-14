# Module 01 — Product Scope and Target Architecture

## Goal

One installable Flutter app for Android and iOS serves customers and shop staff/owners. It communicates with the existing CodeIgniter application through a versioned HTTPS JSON API. The MySQL database remains private to the backend.

## Logical architecture

Flutter mobile app (Customer / Owner / Shop Manager / Order Staff)
→ HTTPS API `/api/v1`
→ CodeIgniter API controllers and authentication/authorization
→ application services (order, customer, shop, catalog; existing sales/inventory services where appropriate)
→ MySQL.

The existing web application continues to support POS and back-office workflows. Add a web Order Management screen so incoming mobile orders can be managed even if a staff member does not install the app.

## MVP scope

- Customer authentication and account-to-existing-customer linking.
- Customer sees only their assigned shop, its orderable products, applicable prices and permitted availability.
- Place one-shop order for delivery or collection.
- Shop staff receive, inspect, accept/reject, prepare and complete/cancel orders according to permissions.
- Customer sees their own order history and status.
- Owner sees orders across all shops belonging to their business and can filter by shop.
- Audit history for order changes and important administrative actions.
- Notification architecture for new orders and status updates; push provider may be phased after core order flow is stable.

## Out of scope for first release

- Split orders spanning multiple shops.
- Cross-business marketplace or customers shopping across unrelated businesses.
- Direct MySQL access from mobile.
- Mobile POS replacement.
- Automatic payment settlement, security-deposit collection/refund, or cylinder-custody changes on order submission.
- Driver GPS tracking, route optimization, loyalty, subscription and promotion engines.

## Architectural boundaries

- UI and client state are not trusted for identity, rates, totals, shop ownership, inventory availability, credit rules, payment state or authorization.
- Controllers validate input and call services; financial/inventory posting logic stays in application services.
- A submitted order is an operational request, not a financial transaction.
- Existing POS sales must continue to work independently.
- API contracts must be versioned and return stable JSON errors/status codes.
- API auth must not depend on the browser's PHP session.
- Do not add CORS exceptions as a substitute for authentication. Native mobile clients still require authenticated API access.

## Acceptance criteria

- The same app package supports customer and staff experiences based on server-authorized account capabilities.
- Customers cannot change their assigned shop by modifying a request.
- Owner views are limited to the owner's business; shop users are limited to assigned shop(s).
- One submitted order is stored once even if the client retries after a network timeout.
- Existing POS flows remain unchanged by pending-order creation.
- Order APIs can be tested independently from the Flutter app.
