# Module 05 — API Contracts, Authentication and Security

## API conventions

- Prefix all mobile endpoints with `/api/v1`.
- Use HTTPS only in deployed environments.
- Use JSON request/response bodies and consistent error format, for example `{ "error": { "code": "...", "message": "...", "request_id": "..." } }`.
- Validate types, required fields, allowed values, lengths and numeric ranges server-side.
- Never expose SQL errors, stack traces, password hashes, authentication secrets, internal filesystem paths or unnecessary customer data.
- Use pagination for lists and stable public order identifiers where appropriate.

## Implemented API route contract

The routes below reflect `app/Config/Routes.php` on `feature/mobile-multishop-ordering`. They are versioned under `/api/v1`; API routes are behind the `api-ready` filter, and authenticated customer/staff routes additionally require an audience-specific bearer token.

### Public / account entry points

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/health` | API readiness/status |
| GET | `/api/v1/customer/auth/shops` | Discover active shops where customer registration is enabled |
| POST | `/api/v1/customer/auth/signup` | Create pending signup request; does not grant a session |
| POST | `/api/v1/customer/auth/login` | Customer login; approved, active account only |
| POST | `/api/v1/customer/auth/refresh` | Rotate a customer refresh token |
| POST | `/api/v1/staff/auth/login` | Staff login |
| POST | `/api/v1/staff/auth/refresh` | Rotate a staff refresh token |

### Customer App API (customer-audience token required except entry points above)

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/v1/customer/auth/logout` | Revoke current session |
| POST | `/api/v1/customer/auth/change-password` | Change password and apply session invalidation policy |
| GET | `/api/v1/customer/me` | Customer profile and assigned shop context |
| GET | `/api/v1/customer/products` | Current shop's enabled catalog with server-calculated price/availability |
| GET | `/api/v1/customer/orders` | Authenticated customer's own orders |
| POST | `/api/v1/customer/orders` | Create order with idempotency key |
| GET | `/api/v1/customer/orders/{orderId}` | Own order detail/status/history |
| POST | `/api/v1/customer/orders/{orderId}/cancel` | Cancel only when the state machine permits |
| POST | `/api/v1/customer/orders/{orderId}/reconfirm-price` | Confirm the currently presented revised quote |

### Business / Staff App API (staff-audience token required)

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/v1/staff/auth/logout` | Revoke current session |
| POST | `/api/v1/staff/auth/change-password` | Change staff password |
| GET | `/api/v1/staff/me` | Staff identity, role capabilities and authorized scope |
| GET | `/api/v1/staff/shops` | List only authorized shops |
| GET / POST | `/api/v1/staff/businesses` | List accessible businesses / create business subject to platform permission |
| GET / POST | `/api/v1/staff/businesses/{businessId}/shops` | List / create shops within authorized business |
| GET / POST | `/api/v1/staff/businesses/{businessId}/staff-access` | Read / update staff scope assignments |
| GET | `/api/v1/staff/orders` | Shop-scoped order queue with filters |
| GET | `/api/v1/staff/orders/{orderId}` | Shop-scoped order detail and status history |
| POST | `/api/v1/staff/orders/{orderId}/status` | Validate and apply a legal state transition |
| GET | `/api/v1/staff/orders/{orderId}/fulfillment-options` | Return eligible physical-cylinder options |
| POST | `/api/v1/staff/orders/{orderId}/fulfill` | Revalidate and post through existing POS business services |
| GET / POST | `/api/v1/staff/businesses/{businessId}/catalog` | Read / maintain business-level catalogue |
| GET | `/api/v1/staff/shops/{shopId}/products` | Read catalogue availability at a shop |
| POST | `/api/v1/staff/shops/{shopId}/products/{productId}/availability` | Activate/deactivate an eligible product for an authorized shop |
| GET | `/api/v1/staff/customer-signups` | List pending signup requests for selected shop |
| POST | `/api/v1/staff/customer-signups/{signupId}/approve` | Link or create customer master and approve signup |
| POST | `/api/v1/staff/customer-signups/{signupId}/reject` | Reject with reason and clear pending password hash |
| POST | `/api/v1/staff/customers/{customerId}/app-account` | Create Customer App account linked to an existing customer master |
| POST | `/api/v1/staff/customer-app-accounts/{accountId}/reset-password` | Admin/Manager-mediated password reset; revoke active sessions |
| POST | `/api/v1/staff/customer-app-accounts/{accountId}/disable` | Disable account and revoke sessions |
| GET | `/api/v1/staff/configuration/application` | Read client-safe application settings permitted to this identity |
| GET | `/api/v1/staff/configuration/businesses/{businessId}` | Read permitted effective business settings |
| GET | `/api/v1/staff/configuration/shops/{shopId}` | Read permitted effective shop settings |
| POST | `/api/v1/staff/configuration/save` | Save allow-listed settings under capability + scope checks |
| POST | `/api/v1/staff/configuration/reset` | Reset eligible overrides |

The route table is implemented in source. Endpoint authorization, token lifecycle, error handling, race behavior and cross-shop denial still require the runtime APISEC01/BIZWEB01/ACCWEB01/OWEB02 checks in `SQA.md`; route presence is not a security test. Do not expose internal secrets or trust any business/shop ID in the client payload as authorization proof.

## Authentication and app/device sessions

- Customer and staff accounts use username/password through the API over TLS; SMS/OTP is out of scope for the first release.
- Store strong password hashes server-side and never log submitted passwords.
- Recommended lifecycle: access token expires after 15 minutes; refresh tokens rotate on every successful refresh and are revocable per app/device. Expire a refresh session after 30 days of inactivity, with an absolute maximum session age of 90 days from sign-in; require login again after either limit. Reject refresh-token reuse and revoke that session/token family. Verify framework/library support in WP-0 before implementation and document any change to these targets.
- Logout revokes the current app/device session. Password reset, account disablement or removal of customer signup approval revokes all sessions for that account. If a refresh token is expired, revoked, reused or invalid, require a fresh username/password login.
- Pending/rejected/inactive accounts cannot obtain an ordering session. Never create a usable session as a side effect of public signup before Admin/Manager approval.
- Customer and staff auth endpoints issue app-audience-bound sessions. Customer sessions are rejected by staff endpoints and staff sessions are rejected by customer endpoints, even when a person uses both apps.
- Rate-limit login, signup and recovery flows and avoid revealing whether a username/customer exists.
- Do not log access/refresh tokens or sensitive identity values.
- Store mobile credentials/session material in platform-secure storage; never embed backend secrets in the Flutter bundle.

## Configuration access and policy

Configuration writes should use the authenticated web Configuration area and a single backend configuration resolver/service. Add versioned API access only where a mobile client needs client-safe effective settings; it must be read-only for platform/business/shop policy and must not accept arbitrary setting keys or return secrets.

- Application Login & Session settings are managed by System Administrators only and validated against approved security guardrails.
- Head Office/Business and Shop settings require separate capability permissions plus explicit business/shop scope checks.
- Resolve permitted overrides server-side: Shop override, then Business override, then Application default. Settings marked Application-only or otherwise non-overridable must never accept lower-scope changes.
- Do not allow business/shop settings to extend token lifetime beyond Application limits, weaken password/login controls, disable refresh rotation/reuse detection, bypass approval or disable revocation.
- Log all configuration changes with actor, scope, timestamp, setting key, outcome and non-secret before/after values. Redact credentials and tokens.
- Do not expose environment variables, signing keys, raw provider credentials, passwords or session tokens to any application client.
- Authorization and session/security policy values are never trusted from the mobile request body or local app preferences.

See [Module 10 — Centralized Configuration Management](10-configuration-management.md) for setting ownership, inheritance and the audit model.

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

