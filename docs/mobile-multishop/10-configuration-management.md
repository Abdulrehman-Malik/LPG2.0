# Module 10 — Centralized Configuration Management

## Goal

Keep important business and application settings in a clear, permission-aware **Configuration** area. On `feature/mobile-multishop-ordering`, the central resolver and scope-aware web Configuration area are implemented, and staff mobile API read/save/reset routes use the same allow-listed service. The independent Flutter clients have initial source flows but are not store-ready. Runtime verification of inheritance, authorization and security guardrails remains pending; CI/source checks do not prove those cases.

## Configuration scopes and ownership

The Configuration area exposes only the scopes that the signed-in user is permitted to manage:

1. **Application Settings — System Administrator only.** Controls platform-wide defaults, security guardrails and application-wide features. This is not the same as a business owner and must not be available to ordinary Head Office or Shop Admin accounts.
2. **Head Office / Business Settings — authorized users of that business.** Controls permitted defaults and policies for that business and its shops. A Head Office user can only manage the business to which they belong.
3. **Shop Settings — authorized users assigned to that shop.** Retains the current shop-specific configuration and provides allowed local overrides. A Shop Admin has no authority over another shop or a different business.

The existing role/permission implementation remains authoritative. The UI may hide scopes and actions, but the backend must independently verify both capability permissions and business/shop ownership on every read and write.

## Effective value and inheritance

For a setting explicitly marked as inheritable/overridable, resolve the effective value in this order:

**Shop override → Business/Head Office override → Application default.**

A setting may also be restricted to one scope. Do not assume every setting is overridable just because an upper-level default exists. Each setting definition must record its permitted scope(s), default, type, validation, minimum/maximum or allowed values, override policy, sensitivity, and audit requirements. The UI should show the effective value and where it comes from, plus a clear option to reset an allowed override and return to the inherited value.

Changing a business default must not silently overwrite an existing explicit shop override. Changing a setting must never rewrite historic orders, sales, receipts, ledger entries, deposits, inventory or other transactions.

## Configuration screen structure

Use one grouped Configuration area with scope-aware navigation. Suggested groups:

- **Application:** login/session policy, password policy guardrails, account/session revocation controls, API security limits, supported languages and localization defaults, platform feature switches, notification-provider status and other truly global settings.
- **Head Office / Business:** business profile and defaults, customer signup/approval policy, business catalog defaults, default order/fulfilment policy, notification preferences, and the list of settings that individual shops may override.
- **Shop:** existing shop settings, shop-specific operating/contact details, enabled delivery/collection methods and approved shop-level fulfilment options, local product activation, and any other existing shop-scoped settings confirmed during WP-0.

Final settings groups must be based on an inventory of existing screens, fields, models and persistence. Do not invent a new business rule or move a field to a different scope solely to make the screen look uniform.

## Login and session settings

Add a dedicated **Login & Session** group under Application Settings. This is where platform security policy defaults and guardrails are visible and managed by an authorized System Administrator, rather than being hidden in code or copied into individual shop screens.

Implemented application-level baseline (runtime behavior still requires release verification):

- Access token lifetime: 15 minutes.
- Refresh session inactivity limit: 30 days.
- Refresh session absolute age: 90 days from sign-in; whichever expiry occurs first ends the session.
- Rotate refresh tokens on successful refresh; detect reuse and revoke the affected token family.
- Logout revokes the current app/device session.
- Password reset, account disablement or removal of customer approval revokes applicable sessions according to the security policy.
- Keep Customer App and Business/Staff App session audiences separate.

The service currently defines these defaults and bounds. They must be verified in runtime tests and may only be changed inside reviewed platform security guardrails. Business/Shop settings must not extend token/session lifetime beyond Application-level maximums, disable token rotation, weaken rate limits, bypass approval, or disable revocation. A lower scope may tighten policy only where the application explicitly supports and tests that restriction.

Changing policy must have a defined effective time. At minimum, new values must be enforced on subsequent token issuance/refresh; security-tightening changes must not grant existing sessions extra lifetime. Provide a separately permissioned, confirmed and audited emergency action for revoking active sessions rather than disguising it as an ordinary setting edit.

## Proposed configuration areas and scope rules

| Setting area | Owning scope | Lower-scope behavior |
|---|---|---|
| Access-token and refresh-session limits | Application | Business/Shop cannot extend Application limits or disable required controls |
| Password security baseline and login throttling | Application | Business/Shop cannot weaken the baseline |
| App audience separation, token rotation/reuse detection and session revocation | Application | Not overridable |
| Supported UI languages | Application | English only in the initial release; expose more languages only after they are added and tested |
| Business profile and business-level defaults | Head Office / Business | Inherited by shops where the setting definition allows it |
| Customer signup/approval policy | Application guardrails plus Business policy if approved | Shops may follow the business policy; shop-specific deviation requires explicit approval and scope definition |
| Product catalog | Head Office / Business | Shop activation/deactivation remains a shop-level choice |
| Fulfilment method availability and delivery conditions/fees | Business defaults plus Shop settings where supported | Customer must see applicable conditions/fees before submitting an order |
| Existing shop configuration | Shop | Preserve current validated behavior; do not create duplicate storage for the same setting |
| Environment secrets, signing keys, raw passwords or provider credentials | Secure deployment/secret facility | Never reveal raw secret values in the ordinary Configuration UI or mobile API |

This table is a target policy matrix. WP-0 must map it to the application's real existing fields/services, and the owner must approve any new setting that changes business behavior.

## Current implementation status

The web Configuration area currently provides application, business and shop scopes, effective-value source labels, allowed override resets, permission checks and audit entries. The central `ConfigurationService` currently defines customer-ordering feature guardrails, order/delivery/collection switches, delivery fee, customer self-registration, supported language, access/refresh session lifetime and minimum password length.

The staff API now exposes these scope-specific routes for the Business/Staff App to consume:

- `GET /api/v1/staff/configuration/application`
- `GET /api/v1/staff/configuration/businesses/{businessId}`
- `GET /api/v1/staff/configuration/shops/{shopId}`
- `POST /api/v1/staff/configuration/save` with `scope`, `owner_id`, `key`, and typed JSON `value`
- `POST /api/v1/staff/configuration/reset` for business/shop overrides

These API routes reuse the existing configuration definitions and resolver; they do not make the mobile client authoritative for permissions or configuration precedence. Application settings require the corresponding application-level capability. Business/shop requests must pass both the capability check and the assigned business/shop scope check. Values are validated server-side and changes are audited.

This is not yet a claim that all possible operational settings have been migrated into this central area. Additional settings must be added only after their current source of truth and consumers are mapped, and mobile screens must only expose settings appropriate to the signed-in role.

## Audit, validation and operational behavior

- Log actor, timestamp, setting key, scope/owner ID, old and new non-secret values, outcome and request/correlation ID. Redact sensitive values; never log passwords or tokens.
- Require explicit permission codes for viewing and editing each scope. Distinguish read from write and do not rely on role name alone.
- Validate values server-side using the setting's declared type, allowed values and bounds. Reject unknown keys and mass-assignment payloads.
- Use a configuration service/resolver as the single path for effective values. Do not make controllers or mobile apps resolve precedence independently.
- Version/cache settings deliberately; invalidate affected caches after a successful update and record the result.
- Make risky changes show their scope and impact before confirmation. Failed validation or failed persistence must not leave a partially applied setting.
- Provide reset-to-inherited/default behavior where applicable, with audit history.
- Do not return deployment secrets or unrestricted settings through mobile endpoints. Customer and staff apps may receive only the client-safe effective configuration they need; only the server may make authorization/security decisions.
- Do not store arbitrary untyped JSON as a substitute for designing and validating the setting's real source of truth.

## Implementation and acceptance gates

WP-0 must inventory the existing Shop Settings screen, related routes/controllers/models/tables, configuration fields, consumers, tests and any environment-based settings. Each existing setting must be classified before it is moved, renamed, duplicated or deprecated.

Build the configuration resolver, permission/scope checks and audit behavior before connecting screen controls. Preserve the current Shop Settings behavior and data. If the final UX consolidates routes, keep a compatibility route or safe redirect where needed; never maintain two editable sources for the same setting.

Acceptance tests must prove:
- System Administrators alone can edit Application Settings.
- Head Office users cannot cross business boundaries.
- Shop users can only view/edit allowed settings for assigned shops.
- Effective values follow the documented precedence, and non-overridable settings stay at their owning scope.
- A business default change does not erase explicit shop overrides.
- Shop and business users cannot weaken application authentication/session guardrails.
- Invalid values are rejected and failed changes are atomic.
- Setting changes are audited, caches refresh correctly, and secrets are never exposed.
- Existing Shop Settings and affected operational flows continue to work.
- Customer and Business/Staff API audiences receive only safe, relevant configuration values.

Implementation remains isolated to the feature branch. Do not merge to `main` until live browser/API, multi-shop authorization, POS/order fulfillment and migration tests have evidence and owner acceptance.
