# Module 03 — Customer Records, Mobile Accounts and Shop Assignment

## Business rule

Each customer belongs to exactly one shop. A shop may have many customers. This is a many-to-one relationship from customers to a shop, not a one-to-one shop/customer restriction.

The customer's assigned shop is the authoritative shop for mobile ordering. The customer cannot choose a different shop from the app.

## Feature-branch implementation status

The customer/staff API already supports approved customer login, refresh/logout/password change, profile, shop-specific catalog, idempotent order create/list/detail/cancel, price reconfirmation, and staff-side signup approval/rejection/account creation/disablement. Browser administration is available at `/customer-app-accounts`; staff API-mediated password reset is implemented at `POST /api/v1/staff/customer-app-accounts/{accountId}/reset-password` and revokes active customer sessions. These paths use the existing financial customer master; they do not create a second ledger identity.

The business/customer constraints and flows are implemented in source, but account lifecycle, race conditions, and cross-shop isolation remain **NOT EXECUTED** runtime cases in `SQA.md` (ACCWEB01 and APISEC01).


## Historical baseline gap (resolved on the feature branch)

The main-branch baseline lacked a durable customer-to-shop relationship. On `feature/mobile-multishop-ordering`, `customers.location_id`, customer mobile-account mapping and shop-scoped signup/order services are present in the fresh-install schema and migration set. Do not treat this historical paragraph as the current feature-branch schema.

## Identity design

- Keep the existing business customer row as the single customer master record.
- Add a separate customer mobile-account mapping or identity table; do not duplicate the financial customer record.
- Enforce one active mobile identity mapping to one customer, and one customer-to-shop assignment.
- Allow multiple authenticated sessions/devices for one account without creating duplicate customer records.
- Customer and staff identities are separate; customer login uses username/password through the versioned API over TLS.
- Store strong password hashes server-side; never store plaintext passwords or log passwords.
- A customer mobile identity links to one financial customer master and one assigned shop; never auto-link based only on a matching contact number.
- Customer usernames must be unique within the customer-account namespace. Phone number is contact information, not an SMS/OTP login credential in MVP.
- For MVP, Admin/Manager-mediated password recovery is the default. Self-service reset remains off until a verified recovery channel is approved.
- Disable active customer sessions when the account is disabled or signup approval is revoked; password reset revokes existing sessions.

## Registration and account linking

Two onboarding paths are supported:

1. **Admin/Manager-created account:** An authorized Admin/Manager creates an app account for a customer within their permitted shop and links it to the correct financial customer master. Shop scope is enforced server-side.
2. **Customer self-registration:** The customer submits a username/password signup request and the minimum details needed for the shop to identify the customer. The account is created in `pending_approval` state. An authorized Admin/Manager reviews it, links it to an existing financial customer master (or explicitly creates the master as a separate reviewed step), then approves or rejects the request.

A pending/rejected account cannot log in to the ordering experience or submit orders. The customer cannot choose or change the shop by editing the client payload. Approval, customer-master linking and the shop assignment must be atomic and auditable. Return no private customer/ledger details before approval. Successful login returns only that customer's allowed profile and assigned-shop context.

## Ledger and data-setup rules

- Customer ledger, outstanding balance, security deposit, receipts, sales and custody history must remain correct.
- The owner confirmed that there is no existing business/customer transactional data to migrate for this initiative. Do not build legacy customer-to-shop mapping or historic backfill tooling as a prerequisite.
- Fresh-install schema and seed/setup logic must establish shop ownership correctly from the start. Every customer belongs to exactly one shop.
- A future upgrade of a populated installation needs a separately approved upgrade/data-handling plan; this is not a requirement to map legacy customer data for the planned deployment.
- A customer transfer between shops is an explicit administrative action with audit history. It must not rewrite old sales, receipts, deposits or cylinder custody.

## Acceptance criteria

- Every active customer is assigned to one valid shop before mobile ordering is enabled.
- A mobile account cannot access another customer's ledger or orders.
- Customer's order shop is derived from the trusted account/customer mapping, not accepted from request JSON.
- Customer account linking does not create duplicate customer records.
- Inactive/blocked customer accounts cannot submit orders.
- Fresh-install customer ownership and account-linking constraints are tested; there is no legacy mapping/backfill release gate.
