# Module 05 — API Contracts, Authentication and Security

## API conventions

- Prefix all mobile endpoints with `/api/v1`.
- Use HTTPS only in deployed environments.
- Use JSON request/response bodies and consistent error format, for example `{ "error": { "code": "...", "message": "...", "request_id": "..." } }`.
- Validate types, required fields, allowed values, lengths and numeric ranges server-side.
- Never expose SQL errors, stack traces, password hashes, authentication secrets, internal filesystem paths or unnecessary customer data.
- Use pagination for lists and stable public order identifiers where appropriate.

## Suggested initial endpoints

### Authentication
- `POST /api/v1/auth/customer/login` — username/password for Customer App accounts; only approved, active customer accounts may receive a session.
- `POST /api/v1/auth/staff/login` — username/password for Business/Staff App accounts.
- `POST /api/v1/auth/logout` — revoke the current app/device refresh session.
- `POST /api/v1/auth/refresh` — rotate the refresh token and issue a new short-lived access token for the same app audience.
- `POST /api/v1/auth/change-password` — authenticated password change and session invalidation policy.
- `GET /api/v1/me` — authenticated account status, account type, capabilities and allowed scope.
- `POST /api/v1/customer/signup` — public signup request only; creates a pending approval request and does not grant ordering access or an authenticated session.

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
- `GET /api/v1/staff/customer-signups?status=pending&shop_id=...` — permissioned, shop-scoped approval queue.
- `POST /api/v1/staff/customer-signups/{signupId}/approve`
- `POST /api/v1/staff/customer-signups/{signupId}/reject`
- `POST /api/v1/staff/customer-accounts` — Admin/Manager-created app account linked to a customer master, subject to shop scope.
- `GET /api/v1/staff/orders?status=pending&shop_id=...`
- `GET /api/v1/staff/orders/{orderId}`
- `POST /api/v1/staff/orders/{orderId}/accept`
- `POST /api/v1/staff/orders/{orderId}/reject`
- `POST /api/v1/staff/orders/{orderId}/status`
- `POST /api/v1/staff/orders/{orderId}/fulfil` (only after the POS integration design is approved)

Endpoint names are a proposed contract and must be reconciled with existing API conventions before implementation. Do not expose endpoints until their authentication, scope and tests are ready.

## Authentication and app/device sessions

- Customer and staff accounts use username/password through the API over TLS; SMS/OTP is out of scope for the first release.
- Store strong password hashes server-side and never log submitted passwords.
- Proposed lifecycle: access token lifetime target of 15 minutes; rotating, revocable refresh session with a 30-day maximum lifetime target; maintain independent revocable sessions per app/device. Verify library/framework support before implementation and record any change to these targets.
- Logout revokes the current device session. Password reset, account disablement or removal of customer signup approval revokes all sessions for that account.
- Pending/rejected/inactive accounts cannot obtain an ordering session. Never create a usable session as a side effect of public signup before Admin/Manager approval.
- Customer and staff auth endpoints issue app-audience-bound sessions. Customer sessions are rejected by staff endpoints and staff sessions are rejected by customer endpoints, even when a person uses both apps.
- Rate-limit login, signup and recovery flows and avoid revealing whether a username/customer exists.
- Do not log access/refresh tokens or sensitive identity values.
- Store mobile credentials/session material in platform-secure storage; never embed backend secrets in the Flutter bundle.

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
- Ensure automated tests cover direct-object reference attacks, unauthorized shop IDs, role escalation, session expiry/revocation, refresh-token rotation/replay and duplicate submissions.

