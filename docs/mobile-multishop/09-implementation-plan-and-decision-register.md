# Implementation Plan and Decision Register — Mobile Multi-Shop Ordering

**Plan version:** 1.0  
**Prepared:** 2026-10-13  
**Status:** Planning completed; implementation deliberately not started.  
**Target branch:** `feature/mobile-multishop-ordering-requirements`  
**Baseline:** `main` at `9abc7d9a417d0508ebc8afd04da3e3d7a15d9038`

This document is the execution plan for the mobile app and multi-shop ordering feature. It consolidates the module requirements into dependency-ordered work packages, defines their entry/exit criteria, and records the business decisions that need owner approval. It authorizes planning only; it does not authorize code, schema, data, or configuration changes.

## 1. Project outcome

Deliver one Flutter app for Android and iOS, backed by the existing CodeIgniter/PHP and MySQL system, supporting:
- Customers linked to existing LPG customer master records and assigned to exactly one shop.
- Shop staff who can receive and process orders only for assigned shop(s).
- Business owners who can view permitted records across shops owned by their business without gaining global system-admin privileges.
- A browser-based Order Management workflow in the existing LPG web app.
- An authenticated, versioned API shared by web/mobile domain services where appropriate.
- Safe creation, acceptance, rejection, cancellation, status tracking and eventual sale fulfilment without bypassing existing POS rules.

The backend remains authoritative for identity, permissions, shop scope, item eligibility, pricing, availability, order states and financial/inventory posting. Flutter is a client, not a second business-rule implementation.

## 2. Non-negotiable boundaries

1. No direct MySQL access from the mobile app.
2. No application or database changes on `main`; work only on the designated feature branch until explicit merge approval.
3. No production data updates, customer reassignment or migration until a reviewed mapping report and backup/recovery plan are approved.
4. A submitted or accepted order is not a sale. It must not, by itself, create revenue, customer OS, receipts, cash movement, payment, deposit, cylinder custody or inventory movement.
5. Actual sale fulfilment must call the existing validated backend POS/sales services and must not reproduce their financial rules in Flutter or a new order controller.
6. Preserve current ADMIN/MANAGER/CASHIER and custom role behavior. Extend the existing permission tables and service; add explicit business/shop scope checks rather than a parallel role system.
7. Keep the existing four POS transaction types isolated and regression-test Gas Sale/Refill, Cylinder Sale, Security Deposit/Issue Cylinder and Cylinder Return/Refund Deposit.
8. Do not silently reprice an order, switch its shop, move historic ledger entries, or create a second customer master record.
9. Do not enable production ordering or publish a mobile build until all applicable release gates pass.
10. All requirements, test outcomes, migrations and decisions must be traceable in repository documentation.

## 3. Work breakdown and dependency order

### WP-0 — Baseline, source inventory and decisions
**Goal:** Confirm the actual source baseline and remove ambiguity before implementation.

Tasks:
- Reconfirm feature branch and main baseline SHAs before starting implementation.
- Inspect schema, migration runner, Models, Services, route filters, order-related naming conventions, current sale primary key, receipt/ledger relationships, rate-card precedence, inventory availability services and current runtime test setup.
- Confirm current capabilities and standard-role permission seeding behavior; identify custom-role preservation requirements.
- Maintain the decision register in Section 4 and record each approved choice before dependent work starts.
- Confirm which existing web screens are impacted and how current-commit runtime QA can be executed.

Exit criteria:
- Source inventory references exact files/classes/tables.
- No unresolved architecture decision is disguised as a technical fact.
- Test environments and a safe copy of a representative database are available.
- No code or schema changed during this work package.

### WP-1 — Legacy data discovery and customer-to-shop mapping
**Goal:** Know which shop owns each existing customer before adding enforced ownership.

Tasks:
- On a safe, non-production database copy, inventory businesses/locations, customers, staff, sales, receipts, ledger entries, security deposits, cylinder custody and transactions by location.
- Identify customers with no posted transactions, customers with activity in one location, customers with activity in multiple locations, customers whose activity has null/missing location, duplicate/shared phone values and inactive records.
- Produce a mapping report with proposed shop, evidence, confidence, unresolved reason and owner decision.
- Do not infer customer ownership from the currently logged-in browser session or assign all customers to a default shop.
- Decide how to represent customers with multi-shop historic activity while preserving each historical transaction's original location.

Deliverables:
- Data inventory summary.
- Customer-to-shop mapping CSV/report with sensitive details minimized.
- Unresolved mapping register and reviewed disposition.
- Backup/restore and rehearsal evidence.

Exit criteria:
- Shop-to-business ownership and customer-to-shop mapping approved by the business owner.
- Unresolved customers are identified and explicitly prevented from mobile ordering until resolved.
- Historical sales, receipts, deposits, custody, cash and inventory records remain unchanged.

### WP-2 — Business/shop ownership and authorization foundations
**Goal:** Enforce capability permission and data scope as separate server-side checks.

Design tasks:
- Add a business/company parent for existing `locations`.
- Define the system administrator boundary versus a business owner boundary.
- Define staff-to-shop assignments; retain `users.location_id` for compatibility/default context where needed.
- Design a scope resolver/service that answers allowed businesses, allowed shops and record ownership from authenticated identity.
- Reuse `roles`, `permissions`, `role_permissions` and `PermissionService`; do not authorize by role name alone.
- Define permission codes for order view, accept, reject, status update, cancellation, shop view/manage, cross-shop reporting and customer account linking. Confirm standard-role defaults and do not auto-grant to existing custom roles.
- Apply scope checks in service/query paths as well as controllers; prevent IDOR by scoping queries before retrieving records.
- Audit business ownership changes, shop assignment changes and privileged owner actions.

Exit criteria:
- Unit/integration tests prove cross-business and cross-shop isolation.
- Existing permission UI and standard/custom role assignments remain compatible.
- A business owner does not gain global admin privileges merely by being an owner.

### WP-3 — Customer ownership and mobile identity
**Goal:** Keep one financial customer master record while adding secure mobile sign-in.

Design tasks:
- Add `customers.location_id` initially nullable; backfill only from the approved mapping report.
- Add a separate customer mobile identity/account relation with unique constraints and account status.
- Keep customer app identities separate from staff `users` identities unless a security-reviewed design explicitly approves shared identity.
- Define normalized phone-number format, account linking, duplicate/shared phone handling, phone change, account recovery, blocked/inactive customer behavior and audit requirements.
- Define a safe shop transfer workflow. Transfer must be explicit and audited, apply to future orders, and never rewrite historic transactions.
- Choose OTP provider and secrets/configuration approach. Never store plaintext OTP values or log them.
- Ensure a customer cannot choose a shop by changing client payloads.

Exit criteria:
- Existing customers are not duplicated.
- Identity linking is verified, atomic and resistant to phone-number guessing or unauthorized linking.
- No customer can order until their shop mapping and account state are valid.

### WP-4 — Order data model and state machine
**Goal:** Persist order intent without side effects in the financial system.

Logical records to finalize against the repository's schema conventions:
- Order header: business, location, customer, public order number, channel/source, fulfilment method, status, contact/address snapshots when required, server-calculated totals/fees/discount snapshots if approved, timestamps and actor references.
- Order lines: supported item/type reference, quantity/unit, description and price snapshots, calculated line totals and notes.
- Status history: previous/new status, actor, reason, timestamp and request/correlation reference.
- Customer mobile identity/session records as approved.
- Staff-to-location assignment records if multi-shop staff access is supported.
- Idempotency storage/keys and a unique order-to-sale association using the actual existing sale key discovered in WP-0.
- Optional delivery and notification records only if included in the approved MVP.

Order state machine:
- Initial state: `pending`.
- Staff decisions: `accepted` or `rejected`.
- Operational progress: approved intermediate preparation/ready/out-for-delivery states only when the chosen fulfilment flow uses them.
- Terminal outcomes: `completed`, `cancelled`, or `unable_to_fulfil` with defined reasons and actors.
- A central OrderService owns allowed transitions; controllers and Flutter must not invent transitions.
- Every change writes the order status and its history atomically.
- Duplicate/replayed requests return or reference the original outcome rather than creating duplicate orders or sales.
- MVP does not support split fulfilment across multiple shops. Partial fulfilment is disabled unless explicitly designed and approved.

Exit criteria:
- State transition matrix and data dictionary are approved.
- Duplicate order/order-to-sale protection is specified at application and database levels.
- Tests prove pending/rejected/cancelled orders create no financial, cash, deposit, custody or stock postings.

### WP-5 — Backend services and browser Order Management
**Goal:** The business can process orders reliably without relying on the Flutter app.

Tasks:
- Implement order services for validation, creation, shop/customer scope, transitions, audit and idempotency.
- Add a web Order Management screen under existing authentication and permission conventions.
- Provide pending queue, filters, order detail/timeline, accept/reject reasons, allowed status changes and safe links to existing POS fulfilment.
- Keep customer and order reads scoped to authorized shops/businesses.
- Define operational handling for changed rates/availability after an order is submitted.
- Ensure order completion requires the explicitly agreed fulfilment/POS reconciliation condition; never mark a sale completed before the authoritative posting succeeds.
- Keep notifications outside the critical transaction where practical; persist/commit state first and retry notification delivery safely.

Exit criteria:
- Staff can receive and process an order in the web app.
- Direct URL manipulation cannot bypass permission/scope checks.
- Current POS flows behave as before; orders do not become sales before the approved fulfilment action.

### WP-6 — Versioned JSON API and API authentication
**Goal:** Expose the approved order workflow safely to native clients.

Proposed API groups (final routes must follow repository conventions):
- Auth: OTP request/verify, logout, optional refresh, `GET /api/v1/me`.
- Customer: assigned shop, catalog, availability/quote, create/list/read/cancel own orders.
- Staff/owner: authorized shops, scoped order list/detail, accept/reject/status and approved fulfilment handoff.
- Platform responses: stable JSON error schema, pagination, request/correlation ID and no HTML login redirects for API requests.

Tasks:
- Select token/session lifecycle, expiry, revocation, refresh and device storage behavior.
- Add request validation, payload-size limits, rate limits, safe logging, error handling and audit.
- Enforce authentication, capability, business/shop scope, record ownership and state transition on every protected endpoint.
- Validate every identifier against authenticated server-side scope.
- Use idempotency for order creation and fulfilment; do not assume the client can guarantee exactly-once delivery.
- Test expired/revoked credentials, brute-force controls, replay, mass assignment, object reference attacks and cross-shop requests.

Exit criteria:
- API security tests pass independently of Flutter.
- Unauthenticated calls return JSON errors.
- No endpoint exposes another customer's profile/order or another shop's operational data.

### WP-7 — Flutter app: customer experience
**Goal:** Allow a linked customer to place and track a shop-specific order.

Screens:
- Phone verification/sign-in and account recovery.
- Read-only assigned-shop context.
- Approved catalog and server-provided prices/availability.
- Cart and order review.
- Delivery/collection details only according to approved shop policy.
- Submit/confirmation with public order number.
- Own order history, status timeline and cancellation where allowed.
- Profile/help/error and retry states.

Rules:
- Never trust client-side totals, role, shop ID, price or order status.
- Prevent accidental double submission in UI and retain server idempotency.
- Handle network interruption by safely retrying with the same idempotency key.
- Do not show cross-shop stock, customer data or queues.
- Store only minimal sensitive data locally using platform secure storage for credentials.

Exit criteria:
- Android/iOS customer flows pass against the same API.
- Customer can access only the account and orders linked to their authenticated identity.

### WP-8 — Flutter app: staff and business-owner experiences
**Goal:** Support authorized order handling in one app package without privilege mixing.

Screens:
- Staff sign-in and server-derived permissions.
- Authorized shop selection only for accounts with multiple shop assignments.
- Pending orders, filters, order details, status timeline and reason capture.
- Owner cross-shop overview restricted to the owner's business and allowed capabilities.
- Empty/loading/offline/retry/permission-denied states.

Rules:
- Server-issued capabilities and scope drive navigation, but every operation is independently authorized server-side.
- Do not automatically combine customer and staff privileges for a person with both identities; define a safe switching/re-authentication flow if needed.
- Do not expose unrelated finance, ledger, POS or admin screens just because order access exists.

Exit criteria:
- A shop user cannot view or act on another shop's orders.
- Business owner sees only authorized shops inside their own business.
- Customer/staff identity modes cannot be combined to escalate privileges.

### WP-9 — POS fulfilment, financial reconciliation and regression
**Goal:** Link order fulfilment to the existing authoritative POS posting path.

Tasks:
- Before coding, identify the canonical current sale posting service, primary key, transaction boundary, reversal behavior and cash-session requirements.
- Define whether fulfilment creates a new POS sale, opens the existing POS with order details prefilled, or uses another approved workflow; it must reuse existing posting validation.
- Store one immutable order-to-sale link for MVP and prevent duplicate posting.
- Revalidate order status, shop/customer ownership, quantities, current pricing and availability before fulfilment.
- Ensure failed POS posting does not mark the order completed.
- Define reconciliation for a sale later voided/reversed and the resulting order status/audit trail.
- Preserve existing customer credit, payment mode, walk-in, cash session, inventory locking, deposits and cylinder-custody rules.

Exit criteria:
- One order cannot create more than one posted sale.
- The order and POS records can be reconciled by stable identifiers.
- All four POS types plus ledger, receipt, deposit, custody, inventory and cash-session regressions pass.

### WP-10 — Pilot, release and operations
**Goal:** Validate the end-to-end system in a controlled environment.

Tasks:
- Run fresh-install schema validation and migration upgrade against a copy of a representative existing database.
- Run mapping review before enforcing ownership constraints.
- Test one business with two shops, customers in both shops, shop-specific staff and a business owner.
- Test network timeout/retry, stock/rate changes, cancellation, rejection, unauthorized IDs, token expiry and duplicate fulfilment.
- Build Android test package and iOS test build; production iOS signing requires macOS/Xcode and Apple release credentials.
- Confirm environment-specific API URLs, TLS, secrets, backups, monitoring, support procedure and rollback/forward-repair procedure.
- Execute runtime QA on all impacted web screens and existing POS regression suite for the current tested commit.
- Obtain explicit owner acceptance and separate approval before merge/deployment.

Exit criteria:
- Every release gate in Module 08 is checked with evidence.
- Pilot reconciles orders against POS sales and inventory.
- No open critical/high security or data-integrity issue.
- Merge/deployment approval is explicit, not inferred from test completion.

## 4. Decision register

The technical plan is organized; the items below are business/product choices that must be approved before the dependent work package starts. The proposed default is a recommendation, not authorization to implement without approval.

| ID | Decision required | Proposed MVP default | Blocks |
|---|---|---|---|
| D-01 | Business ownership for existing shops | One approved legacy business owns the current shop(s) until the owner confirms any separate businesses; do not assume unrelated shops belong together | WP-1, WP-2 |
| D-02 | Existing customer-to-shop mapping | Use audited transaction evidence and owner review; unresolved customers cannot order | WP-1, WP-3 |
| D-03 | Staff can serve multiple shops? | Support explicit many-to-many staff/shop assignment through an assignment table; keep `users.location_id` as a compatibility/default field | WP-2, WP-8 |
| D-04 | Owner identity/role | Reuse configurable capabilities plus explicit business scope; do not grant global ADMIN access | WP-2 |
| D-05 | New permission defaults | Add order capabilities; assign only to specifically reviewed standard roles; never silently modify custom roles | WP-2 |
| D-06 | Customer login method and OTP vendor | SMS OTP through a provider abstraction, with production provider/credentials selected before release; non-production OTP bypass must not be available in production | WP-3, WP-6 |
| D-07 | Customer onboarding | Invite/QR/code issued or approved by the shop, not unrestricted public customer creation or free shop selection | WP-3, WP-7 |
| D-08 | Customer phone uniqueness/shared phones | Verify uniqueness constraints against real data before selecting a global unique rule; support manual recovery for shared/duplicate numbers | WP-3 |
| D-09 | Catalog/orderable item types | MVP supports only explicitly approved sellable items that map cleanly to existing POS services; security deposit, issue-cylinder custody and refund/return workflows excluded from mobile orders unless separately approved | WP-4, WP-7, WP-9 |
| D-10 | Price source/precedence | Use the existing shop-specific rate when configured, otherwise the existing global fallback only if current business rules confirm that fallback; snapshot the displayed quote | WP-4, WP-5 |
| D-11 | Rate changes after submission | Never silently change a customer-visible price; require customer reconfirmation or staff rejection/re-quote when the authoritative price changes | WP-5, WP-7 |
| D-12 | Stock reservation | No inventory reservation/deduction when submitted or accepted; revalidate at fulfilment unless an explicit reservation design is approved | WP-4, WP-5 |
| D-13 | Delivery/collection | Support collection as the simplest path; enable delivery per shop only after delivery zones, fee, address, minimum order and time-window rules are agreed | WP-4, WP-7 |
| D-14 | Payment | No online payment in MVP; payment is handled through existing POS fulfilment and its normal payment rules | WP-5, WP-9 |
| D-15 | Customer cancellation | Customer may cancel while pending; after acceptance only staff may cancel/mark unable-to-fulfil under the approved status matrix | WP-4, WP-5 |
| D-16 | Partial fulfilment | Out of scope; one order links to no more than one sale in MVP | WP-4, WP-9 |
| D-17 | Completed status meaning | Completed only after the agreed fulfilment step and successful authoritative POS posting when a sale is required | WP-4, WP-9 |
| D-18 | Staff/customer using one phone | Keep customer identities separate from staff identities; require explicit account switch and re-authentication if both modes are later supported | WP-3, WP-8 |
| D-19 | Notifications | In-app/order-list refresh first; choose SMS/push provider and templates after reliable core order lifecycle | WP-5, WP-7 |
| D-20 | Languages | Build localization-ready UI; confirm whether English, Urdu or both are required for first release | WP-7, WP-8 |
| D-21 | First rollout | One business, two shops in test/pilot; production rollout only after owner sign-off and all gates pass | WP-10 |

## 5. Cross-cutting acceptance criteria

The feature is not ready unless all of these are true:
- Business, shop, staff and customer ownership are enforced server-side for web and API.
- Customer master records and historic transactions are preserved.
- Custom roles remain intact; permission and scope checks are independently tested.
- Order state and status history are atomic, auditable and transition-controlled.
- Retries cannot duplicate an order or a posted sale.
- Pending/accepted/rejected/cancelled orders have no unintended financial or stock side effects.
- Actual fulfilment reuses existing POS rules and is reconcilable.
- Existing web POS, customer/supplier ledgers, receipts, deposits, custody, cash, inventory, rates and reporting pass regression.
- API auth, scope, request validation, rate limiting, logging and failure responses pass automated security tests.
- Android and iOS use the same documented API and pass the agreed test cases.
- Upgrade and fresh-install database paths are tested.
- Runtime QA is current to the commit under test, with no claim of success without evidence.
- Backup, support, monitoring and recovery plans are documented.

## 6. Definition of planning complete

Planning is complete when:
- The requirement modules and this work breakdown are reviewed together.
- The logical data model, API domains, authorization rules, state machine, POS boundary and migration approach are documented.
- Every implementation work package has dependencies and exit criteria.
- Business choices are visible in the decision register with recommended defaults.
- A safe data-mapping report can be produced without changing application code or production data.
- The owner approves decisions that affect customer behavior, data ownership, security or financial workflows.

**This document does not mark business decisions as approved.** The next permitted step is decision review plus data discovery on a safe database copy. Implementation remains paused until that gate is explicitly cleared.
