# Module 03 — Customer Records, Mobile Accounts and Shop Assignment

## Business rule

Each customer belongs to exactly one shop. A shop may have many customers. This is a many-to-one relationship from customers to a shop, not a one-to-one shop/customer restriction.

The customer's assigned shop is the authoritative shop for mobile ordering. The customer cannot choose a different shop from the app.

## Current gap

The current `customers` table and `CustomerModel::$allowedFields` do not include `location_id`. The existing ledger derives a location filter from the current session in some paths, but this is not a durable customer ownership relationship. Add explicit customer-to-shop ownership through a backward-compatible migration.

## Identity design

- Keep the existing business customer row as the single customer master record.
- Add a separate customer mobile-account mapping or identity table; do not duplicate the financial customer record.
- Enforce one active mobile identity mapping to one customer, and one customer-to-shop assignment.
- Allow multiple authenticated sessions/devices for one account without creating duplicate customer records.
- Phone number verification is required before linking a mobile identity.
- Never auto-link solely because an unverified phone string matches a customer record.
- Define safe recovery/relinking for changed phone numbers, duplicate phone numbers, shared family phones, inactive customers and blocked accounts.
- Store password hashes or use a trusted OTP identity provider; never store plaintext passwords or OTP values.

## Registration and account linking

1. Customer enters phone number and completes verification.
2. Backend checks whether the phone/account is already linked.
3. If a known customer exists, use a safe verification-and-link process; do not expose another customer's private details.
4. If the customer is new, create the customer under an explicitly selected/assigned shop only after the shop-selection policy is satisfied. For the first release, a business-approved registration link/code or shop-created invitation is safer than arbitrary public shop/customer creation.
5. Persist the customer's shop assignment and account mapping atomically.
6. Return only the authenticated customer's allowed profile and shop context.

The product owner must decide if public self-registration is allowed and how the first shop assignment is made. The default requirement is not to let customers freely switch shops.

## Ledger and legacy-data rules

- Customer ledger, outstanding balance, security deposit, receipts, sales and custody history must remain correct.
- Adding `customers.location_id` requires a mapping plan for existing records. Do not assume the current browser session identifies the correct historical owner.
- Where legacy customers have transactions in multiple locations, flag for manual reconciliation or document an explicit migration rule; do not silently assign or move ledger history.
- Define whether customer ledgers are shop-local or show historic transactions across locations after a customer transfer. Default: no automatic transfer; preserve historic transaction locations and require an audited transfer policy.
- A customer transfer between shops is an explicit administrative action with audit history. It must not rewrite old sales, receipts, deposits or cylinder custody.

## Acceptance criteria

- Every active customer is assigned to one valid shop before mobile ordering is enabled.
- A mobile account cannot access another customer's ledger or orders.
- Customer's order shop is derived from the trusted account/customer mapping, not accepted from request JSON.
- Customer account linking does not create duplicate customer records.
- Inactive/blocked customer accounts cannot submit orders.
- Legacy customer mapping is reviewed and reversible before production rollout.
