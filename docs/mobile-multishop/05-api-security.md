# Module 05 — API Contracts, Authentication and Security

## API conventions

- Prefix all mobile endpoints with `/api/v1`.
- Use HTTPS only in deployed environments.
- Use JSON request/response bodies and consistent error format, for example `{ "error": { "code": "...", "message": "...", "request_id": "..." } }`.
- Validate types, required fields, allowed values, lengths and numeric ranges server-side.
- Never expose SQL errors, stack traces, password hashes, OTP secrets, internal filesystem paths or unnecessary customer data.
- Use pagination for lists and stable public order identifiers where appropriate.

## Suggested initial endpoints

### Authentication
- `POST /api/v1/auth/otp/request`
- `POST /api/v1/auth/otp/verify`
- `POST /api/v1/auth/logout`
- `POST /api/v1/auth/refresh` (only if refresh-token design is selected)
- `GET /api/v1/me`

### Customer
- `GET /api/v1/customer/shop`
- `GET /api/v1/customer/catalog`
- `GET /api/v1/customer/availability`
- `GET /api/v1/customer/orders`
- `POST /api/v1/customer/orders`
- `GET /api/v1/customer/orders/{orderId}`
- `POST /api/v1/customer/orders/{orderId}/cancel`

### Shop/owner staff
- `GET /api/v1/staff/shops`
- `GET /api/v1/staff/orders?status=pending&shop_id=...`
- `GET /api/v1/staff/orders/{orderId}`
- `POST /api/v1/staff/orders/{orderId}/accept`
- `POST /api/v1/staff/orders/{orderId}/reject`
- `POST /api/v1/staff/orders/{orderId}/status`
- `POST /api/v1/staff/orders/{orderId}/fulfil` (only after the POS integration design is approved)

Endpoint names are a proposed contract and must be reconciled with existing API conventions before implementation. Do not expose endpoints until their authentication, scope and tests are ready.

## Authentication

- Browser session authentication is not sufficient for native mobile API calls.
- Select a token/session design with expiration, revocation, secure device storage and logout semantics.
- OTP delivery provider and rate limits require a product decision.
- Rate-limit OTP request/verify and login endpoints; avoid revealing whether a phone number belongs to a customer.
- Do not log access tokens, OTPs or sensitive identity values.
- Use secure storage on-device; never embed backend secrets in the Flutter bundle.

## Authorization and data isolation

Every endpoint must validate:
1. authenticated identity and active status;
2. required capability permission;
3. business/shop scope for the requested resource;
4. customer ownership for customer endpoints;
5. allowed order status transition and actor role.

Do not trust IDs sent by the app. Scope database queries by the authenticated principal before retrieving data. Return a non-enumerating not-found/forbidden response policy that does not disclose another customer's orders.

## Other controls

- CSRF remains relevant to browser forms; mobile token APIs require their own appropriate protections.
- CORS is mainly a browser control and is not mobile API authentication.
- Validate request size and content type.
- Add audit events for staff order decisions, assignment changes and account linking.
- Prevent mass assignment by allowlisting fields.
- Use idempotency keys for order creation and fulfilment.
- Set security headers and safe error handling.
- Ensure automated tests cover direct-object reference attacks, unauthorized shop IDs, role escalation, token expiry/revocation, replay and duplicate submissions.

