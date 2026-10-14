# Implementation Plan and Decision Register — Mobile Multi-Shop Ordering

**Plan version:** 1.1  
**Prepared:** 2026-10-14  
**Status:** Owner decisions retained; implementation is active on `feature/mobile-multishop-ordering`. WP-0–WP-6 and the POS fulfilment integration have code present, including browser administration; runtime security/financial regression and WP-10 pilot remain open. Flutter apps WP-7/WP-8 remain out of scope for this web/backend-only task.  
**Target branch:** `docs/mobile-multishop-final-plan` for this documentation-only update; implementation branch to be created after planning approval  
**Baseline:** `main` at `0ef0f3ff41eaa4aae1d66c4549892ab77f3898e6` (verified 2026-10-14)

This document is the execution plan for the mobile app and multi-shop ordering feature. It consolidates the module requirements into dependency-ordered work packages, defines their entry/exit criteria, and records the confirmed business decisions and remaining implementation gates. The original version of this plan was planning-only. Subsequent user approval authorized feature-branch implementation; this document is now the implementation and verification plan. No production database has been queried or changed.

## 1. Project outcome

Deliver two separate Flutter mobile apps for Android and iOS, backed by the existing CodeIgniter/PHP and MySQL system:
- **Customer App:** Customers linked to existing LPG customer master records and assigned to exactly one shop can place and track their own orders.
- **Business/Staff App:** Shop staff can receive and process orders only for assigned shop(s); Head Office users can access permitted records across shops owned by their business without gaining global system-admin privileges.
- Both apps use the same secure, versioned cloud API where appropriate, but have separate app experiences, navigation, release packages and authorization-aware workflows.
- A browser-based Order Management workflow in the existing LPG web app.
- An authenticated, versioned API shared by web/mobile domain services where appropriate.
- Safe creation, acceptance, rejection, cancellation, status tracking and eventual sale fulfilment without bypassing existing POS rules.

The backend remains authoritative for identity, permissions, shop scope, item eligibility, pricing, availability, order states and financial/inventory posting. Flutter is a client, not a second business-rule implementation.

## 2. Non-negotiable boundaries

1. No direct MySQL access from the mobile app.
2. No application or database changes on `main`; work only on the designated feature branch until explicit merge approval.
3. No application, schema, configuration or production-data changes until implementation is explicitly approved. The owner confirmed that no existing business/customer transactional data must be migrated for this initiative, so do not create legacy customer-to-shop mapping or historical backfill tooling. Design repeatable fresh-install schema/seeds and a reviewed upgrade path for any existing installation that may be selected later.
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
- Inventory the existing Shop Settings/Configuration screen, fields, routes, controllers, models/tables and code paths that consume those settings. Classify each item as Application, Business/Head Office or Shop scope before proposing a move.
- Verify the current authentication/session implementation and token/session library, then map which login/session settings can safely be managed through Application Settings and what guardrails must remain fixed.
- Maintain the decision register in Section 4 and record each approved choice before dependent work starts.
- Confirm which existing web screens are impacted and how current-commit runtime QA can be executed.

Exit criteria:
- Source inventory references exact files/classes/tables.
- No unresolved architecture decision is disguised as a technical fact.
- Test environments and a safe copy of a representative database are available.
- No code or schema changed during this work package.

### WP-1 — Fresh-install schema and ownership foundations
**Goal:** Define business/shop/customer ownership for a clean install without legacy customer-data migration.

Tasks:
- Design the business/company parent entity; every shop/location belongs to exactly one business.
- Assign every customer to exactly one shop at customer creation or explicit, audited account setup.
- Ensure each customer app identity links to one financial customer master record rather than creating a duplicate during signup.
- Specify fresh-install schema, repeatable seeds, unique constraints and relationships for businesses, shops, customers, customer identities and permissions.
- Inspect current source schema and migration conventions for compatibility, but do not perform historic transaction mapping or infer legacy customer ownership.
- If an existing installation is later selected for upgrade, require a separate reviewed upgrade/data-handling plan before changing live data.

Deliverables:
- Reviewed logical data model and relationship constraints.
- Fresh-install schema/migration and seed specification.
- Upgrade and recovery considerations for any existing installation.
- Customer account-to-financial-master linking rules.

Exit criteria:
- Clean install creates the required business, shop and customer ownership structure repeatably.
- Every newly created customer belongs to one shop, and customers cannot self-select an unauthorized shop.
- Legacy customer mapping reports and historical backfills are not prerequisites.
- Signup does not duplicate or silently rewrite financial customer or historical transaction records.

### WP-2 — Business/shop ownership and authorization foundations
**Goal:** Enforce capability permission and data scope as separate server-side checks.

Design tasks:
- Add a business/company parent for existing `locations`.
- Define the system administrator boundary versus a business owner boundary.
- Define staff-to-shop assignments; retain `users.location_id` for compatibility/default context where needed.
- Design a scope resolver/service that answers allowed businesses, allowed shops and record ownership from authenticated identity.
- Reuse `roles`, `permissions`, `role_permissions` and `PermissionService`; do not authorize by role name alone.
- Define permission codes for order view, accept, reject, status update, cancellation, shop view/manage, cross-shop reporting and customer account linking. Confirm standard-role defaults and do not auto-grant to existing custom roles.
- Define separate view/edit capabilities for Application Settings, Business/Head Office Settings and Shop Settings; only System Administrators may edit Application settings.
- Apply scope checks in service/query paths as well as controllers; prevent IDOR by scoping queries before retrieving records.
- Audit business ownership changes, shop assignment changes and privileged owner actions.

Exit criteria:
- Unit/integration tests prove cross-business and cross-shop isolation.
- Existing permission UI and standard/custom role assignments remain compatible.
- A business owner does not gain global admin privileges merely by being an owner.

### WP-3 — Customer ownership and mobile identity
**Goal:** Keep one financial customer master record while adding secure mobile sign-in.

Design tasks:
- Define customer shop ownership as a required relationship for newly created customers in the fresh-install model; do not make a legacy mapping report or historical backfill part of this initiative.
- Add a separate customer mobile identity/account relation with unique constraints and account status.
- Keep customer app identities separate from staff `users` identities unless a security-reviewed design explicitly approves shared identity.
- Define username rules, account linking to an existing financial customer master, shared contact handling, password reset/recovery, pending-approval/blocked-account behavior and audit requirements. Contact phone numbers are not login credentials in MVP.
- Permit authorized Admin/Manager users to create an app account for a customer within their shop. Also permit customer self-registration, but put new signups in `pending_approval`; an authorized Admin/Manager must link the request to the correct customer master (or explicitly create the customer master as a separate reviewed step) and approve or reject it. Pending/rejected accounts cannot sign in to ordering or submit orders.
- For MVP, account recovery is handled by an authorized Admin/Manager through a secure password-reset process. Self-service password recovery remains disabled until a verified recovery channel is approved.
- Define a safe shop transfer workflow. Transfer must be explicit and audited, apply to future orders, and never rewrite historic transactions.
- Use username/password login through the versioned API over TLS. Store only strong server-side password hashes; never log passwords. SMS/OTP is out of scope for the initial release, while the authentication service should remain extensible for a future method.
- Ensure a customer cannot choose a shop by changing client payloads.

Exit criteria:
- Existing financial customer masters are not duplicated by mobile onboarding.
- Account linking and approval are atomic and auditable.
- Pending, rejected, inactive or blocked customers cannot log in to the ordering experience or place orders.

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
- Add a permission-controlled customer signup approval queue for Admin/Manager users; approvals must be limited to their own shop scope and link to one customer master record.
- Provide Head Office control of the business product catalog and shop-level product activation/deactivation, plus shop configuration for available fulfilment methods.
- Implement one scope-aware Configuration area and backend resolver/service for Application, Business/Head Office and Shop settings. Preserve existing Shop Settings behavior and storage; do not create duplicate sources of truth.
- Add Application Settings groups for Login & Session and other approved platform-wide settings. Restrict application-security controls to System Administrators and enforce guardrails server-side.
- Display effective values and their source scope, support allowed overrides/reset-to-inherited, validate values server-side, audit changes, and refresh affected caches after updates.
- Use existing validated rate logic and define handling for rate/availability changes after an order is submitted.
- Ensure order completion requires the explicitly agreed fulfilment/POS reconciliation condition; never mark a sale completed before the authoritative posting succeeds.
- Keep notifications outside the critical transaction where practical; persist/commit state first and retry notification delivery safely.

Exit criteria:
- Staff can receive and process an order in the web app.
- Direct URL manipulation cannot bypass permission/scope checks.
- Current POS flows behave as before; orders do not become sales before the approved fulfilment action.

### WP-6 — Versioned JSON API and API authentication
**Goal:** Expose the approved order workflow safely to native clients.

Proposed API groups (final routes must follow repository conventions):
- Auth: separate customer and staff username/password login endpoints, shared logout/refresh mechanics bound to the app audience, password change, approved recovery flow and `GET /api/v1/me`; no SMS/OTP endpoints in MVP. Customer-issued sessions must not be accepted by staff endpoints, or vice versa.
- Customer: assigned shop, catalog, availability/quote, create/list/read/cancel own orders.
- Staff/owner: authorized shops, scoped order list/detail, accept/reject/status and approved fulfilment handoff.
- Platform responses: stable JSON error schema, pagination, request/correlation ID and no HTML login redirects for API requests.

Tasks:
- Use TLS-only login and API access. Recommended defaults: access token expires after 15 minutes; refresh tokens rotate on every use; refresh session expires after 30 days of inactivity or 90 days absolute age, whichever comes first. Refresh sessions are separately revocable per app/device; reject refresh-token reuse and revoke the affected token family. Logout revokes the current session; password reset, account disablement or removal of signup approval revokes all sessions. Verify framework support in WP-0 before implementation and document any required adjustment.
- Add request validation, payload-size limits, rate limits, safe logging, error handling and audit.
- Enforce authentication, capability, business/shop scope, record ownership and state transition on every protected endpoint.
- Validate every identifier against authenticated server-side scope.
- Use idempotency for order creation and fulfilment; do not assume the client can guarantee exactly-once delivery.
- Test expired/revoked sessions, login throttling, refresh-session rotation/replay, mass assignment, object reference attacks and cross-shop requests.
- Expose only client-safe effective configuration needed by mobile clients; configuration/security-policy writes remain permissioned server-side operations and do not accept arbitrary keys.

Exit criteria:
- API security tests pass independently of Flutter.
- Unauthenticated calls return JSON errors.
- No endpoint exposes another customer's profile/order or another shop's operational data.

### WP-7 — Customer App (Flutter)
**Goal:** Deliver a dedicated customer-only mobile app that allows a linked customer to place and track a shop-specific order.

This is a separate app from the Business/Staff App. Customer and staff identities, navigation, app distribution and workflows must not be combined into a single app package.

Screens:
- English UI; username/password sign-in; customer self-registration with pending-approval state; account recovery/help and password change. Keep strings localization-ready; add language selection when another language is introduced.
- Read-only assigned-shop context.
- Head Office-defined catalog filtered by each shop's active product settings.
- Server-calculated prices/availability and saved price snapshots; require review if an offline queued order encounters a changed price.
- Cart and order review.
- Customer selects delivery or collection at checkout. Both choices are supported; delivery requires an address and any configured fee/condition is shown before submission.
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

### WP-8 — Business/Staff App (Flutter)
**Goal:** Deliver a separate app for shop staff and Head Office users to handle authorized operational workflows.

This is a separate app package from the Customer App. Within the Business/Staff App, available screens and actions are determined by server-granted capabilities and business/shop scope.

Screens:
- English UI; staff username/password sign-in and server-derived permissions. Keep strings localization-ready; add language selection when another language is introduced.
- Authorized shop selection only for accounts with multiple shop assignments.
- Pending customer signups needing approval, plus pending orders, filters, order details, status timeline and reason capture.
- Business catalog administration and shop-level product activation/deactivation, limited by permissions and business/shop scope.
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
- Verify clean-install schema/seeds and customer/shop ownership constraints; do not include legacy customer mapping/backfill as a release prerequisite.
- Test Admin/Manager-created accounts, customer signups awaiting approval, approval/rejection, password recovery and session revocation.
- Test multiple businesses in the data model, a one-business/two-shop pilot, shop-specific staff and Head Office access.
- Test product activation/deactivation, delivery/collection choice, price-change review, signup approval and language selection.
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

The register below contains both owner-confirmed choices and remaining choices for implementation planning. Confirmed decisions are summarized in Section 7; remaining technical details must be verified in their work packages. A proposed technical default is not permission to change code or deploy without explicit implementation approval.

| ID | Decision required | Proposed MVP default | Blocks |
|---|---|---|---|
| D-01 | Multi-business and initial setup | Support multiple independent businesses, each with its own Head Office and shops. One business/two shops is only the first pilot, not an architectural limit | WP-1, WP-2 |
| D-02 | Legacy customer-to-shop mapping | Not required: the planned deployment starts without existing business/customer transactional data to migrate. Assign each customer to one shop at creation; do not build historical mapping/backfill tooling | WP-1, WP-3 |
| D-03 | Staff can serve multiple shops? | Support explicit many-to-many staff/shop assignment through an assignment table; keep `users.location_id` as a compatibility/default field | WP-2, WP-8 |
| D-04 | Owner identity/role | Reuse configurable capabilities plus explicit business scope; do not grant global ADMIN access | WP-2 |
| D-05 | New permission defaults | Add order capabilities; assign only to specifically reviewed standard roles; never silently modify custom roles | WP-2 |
| D-06 | Customer/staff authentication and API sessions | Username/password through the versioned API over TLS; store password hashes server-side; use short-lived access credentials and revocable refresh sessions; revoke sessions on logout/reset/disablement. SMS/OTP is excluded from MVP | WP-3, WP-6 |
| D-07 | Customer onboarding and approval | A permissioned Admin/Manager may create an app account linked to a customer master; customers may also self-register, but the signup remains pending until an authorized Admin/Manager approves or rejects it for that shop. No login/order access before approval, and no duplicate financial customer master | WP-3, WP-5, WP-7 |
| D-08 | Username uniqueness and account recovery | Username is the customer login identifier and must be unique in the customer-account namespace. Admin/Manager-mediated recovery is the MVP default; self-service recovery requires a verified channel to be separately approved. Contact phone numbers are not SMS/OTP credentials in MVP | WP-3, WP-6 |
| D-09 | Business catalog and shop availability | Head Office defines the business product catalog; each shop can activate/deactivate products based on current offerings. Only products mapped to approved existing POS fulfilment services can be ordered; deposit, cylinder custody and refund/return workflows are excluded unless separately approved | WP-1, WP-4, WP-5, WP-7, WP-9 |
| D-10 | Effective price source and snapshot | Use existing validated effective-rate logic, including shop-specific/global fallback only where current business rules permit it. The server calculates and snapshots the quote on cloud order creation; catalog settings do not bypass existing rate-card rules | WP-4, WP-5, WP-6 |
| D-11 | Price changes after ordering | Never silently reprice. If a queued offline order's price/availability changes before sync, require customer review before creating the cloud order. Preserve the price snapshot for cloud orders; if fulfilment needs a different price, get customer reconfirmation or cancel/re-quote before POS posting | WP-4, WP-5, WP-7, WP-9 |
| D-12 | Stock reservation | No inventory reservation/deduction when submitted or accepted; revalidate at fulfilment unless an explicit reservation design is approved | WP-4, WP-5 |
| D-13 | Delivery/collection | Customer can select delivery or collection at checkout. Delivery requires an address; any fee or constraint must be configured and shown before submission. Do not add hidden fees | WP-4, WP-7 |
| D-14 | Payment | No online payment in MVP; payment is handled through existing POS fulfilment and its normal payment rules | WP-5, WP-9 |
| D-15 | Customer cancellation | Customer may cancel while pending; after acceptance only staff may cancel/mark unable-to-fulfil under the approved status matrix | WP-4, WP-5 |
| D-16 | Partial fulfilment | Out of scope; one order links to no more than one sale in MVP | WP-4, WP-9 |
| D-17 | Completed status meaning | Completed only after the agreed fulfilment step and successful authoritative POS posting when a sale is required | WP-4, WP-9 |
| D-18 | Staff/customer using one phone | Keep customer identities separate from staff identities; require explicit account switch and re-authentication if both modes are later supported | WP-3, WP-8 |
| D-19 | Notifications | In-app/order-list refresh first; choose SMS/push provider and templates after reliable core order lifecycle | WP-5, WP-7 |
| D-20 | Language support | English is the only supported UI language in the initial release. Keep UI strings localization-ready so additional languages can be added later without changing business logic. No language selector is needed while only English is available; add a selector when a second language is introduced | WP-7, WP-8 |
| D-21 | First rollout | One business, two shops in test/pilot; production rollout only after owner sign-off and all gates pass | WP-10 |
| D-22 | Centralized configuration scope | One permission-aware Configuration area for Application, Head Office/Business and Shop settings. Resolve only allowed overrides as Shop → Business → Application. Login & Session policy is Application-owned by default; lower scopes cannot weaken security guardrails. Inventory and preserve existing Shop Settings before consolidating | WP-0, WP-2, WP-5, WP-6, WP-8 |

## 5. Cross-cutting acceptance criteria

The feature is not ready unless all of these are true:
- Business, shop, staff and customer ownership are enforced server-side for web and API.
- Customer master records and historic transactions are preserved.
- Custom roles remain intact; permission and scope checks are independently tested.
- Application, Business/Head Office and Shop configuration are scope-isolated, validated, inherited only when allowed, and audited.
- Business/Shop configuration cannot weaken Application login/session guardrails or retrieve secret values.
- Order state and status history are atomic, auditable and transition-controlled.
- Retries cannot duplicate an order or a posted sale.
- Pending/accepted/rejected/cancelled orders have no unintended financial or stock side effects.
- Actual fulfilment reuses existing POS rules and is reconcilable.
- Existing web POS, customer/supplier ledgers, receipts, deposits, custody, cash, inventory, rates and reporting pass regression.
- API auth, scope, request validation, rate limiting, logging and failure responses pass automated security tests.
- The separate Customer App and Business/Staff App for Android and iOS use the documented API and pass their respective test cases.
- Upgrade and fresh-install database paths are tested.
- Runtime QA is current to the commit under test, with no claim of success without evidence.
- Backup, support, monitoring and recovery plans are documented.

## 6. Definition of planning complete

Planning is complete when:
- The requirement modules and this work breakdown are reviewed together.
- The logical data model, API domains, authorization rules, state machine, POS boundary and migration approach are documented.
- Every implementation work package has dependencies and exit criteria.
- Business choices are visible in the decision register with recommended defaults.
- Existing Shop Settings are inventoried and preserved, with a defined Configuration area and Application-level Login & Session group.
- Setting ownership, override rules, audit expectations and client-safe configuration API behavior are documented.
- Fresh-install schema/seeds and account-linking rules are documented without legacy customer mapping/backfill.
- Onboarding/approval, username/password API authentication, session revocation, product controls, price-change handling, delivery/collection and multilingual login are defined.
- The owner approves decisions that affect customer behavior, data ownership, security or financial workflows.

**The owner-confirmed product and architecture decisions are recorded in the approved-hierarchy section and the corresponding decision-register rows.** The language decision is fixed to English-only for the initial release. The recommended session lifetimes below must be verified against the existing framework in WP-0 before implementation. Implementation remains paused until the owner explicitly approves the consolidated plan.


## 7. Approved hierarchy and confirmed decisions (2026-10-14)

The following decisions have been confirmed by the business owner and supersede any conflicting open questions elsewhere in this plan.

- **Multi-business capable architecture:** Support multiple independent businesses in the data model. Each business has its own Head Office and one or more shops. Initial setup may contain one business, but the design must not hard-code a single-business assumption.
- **Head Office:** Head Office users can access multiple shops within their own business, subject to explicit permissions. Permitted functions may include viewing consolidated or shop-specific inventory, sales, outstanding balances (OS), and other operational data, and sending approved data/instructions to shops. Head Office access does not imply access to unrelated businesses or automatic permission to every function.
- **Shop isolation:** Each shop has its own employees and operational data. Shop employees can access only their assigned shop, unless a separately approved explicit multi-shop assignment is granted. Role capability and data scope must both be checked.
- **Shop roles:** Manager, Cashier, Operations Manager, and Shop Admin are supported shop-level roles. Their capabilities are permission-driven; role names alone are not authorization. Shop Admin has full access to data and administration within their own shop, including managing that shop's employees and settings, but cannot access other shops or businesses unless separately assigned and permitted.
- **Customer ownership:** Each customer belongs to exactly one shop. A customer mobile account links to that existing customer record; it must not create a duplicate financial customer. The project starts with no existing business/customer transactional data to migrate, so no legacy customer-to-shop mapping or historical-data backfill is required. Fresh-install schema and seed setup must create the required structure.
- **Mobile applications:** Build two separate Flutter applications for Android and iOS: (1) a Customer App for customers to place and track their own orders, and (2) a Business/Staff App for shop employees and Head Office users. They may share approved API contracts and reusable technical components, but must remain separate app experiences/packages with distinct authentication flows, navigation and permission boundaries. Staff/Head Office access is always server-authorized and scoped to the user's business and assigned shop(s). Do not combine customer and staff experiences into one mobile app.
- **Authentication:** Customer and staff accounts use username/password through the versioned API over TLS. Passwords are stored only as strong server-side hashes. Use short-lived access tokens and rotating, revocable refresh sessions; logout, password reset, account disablement and removal of signup approval revoke the applicable sessions. SMS/OTP is out of scope for MVP.
- **Customer onboarding:** A permissioned Admin/Manager may create an app account linked to the customer's financial master. A customer may self-register, but the account remains pending until an authorized Admin/Manager approves or rejects it. Pending/rejected/inactive accounts cannot sign in to order or place orders. For MVP, recovery is Admin/Manager-mediated unless a verified self-service recovery channel is separately approved.
- **Catalog and shop availability:** Head Office defines the business product catalog. Each shop may activate/deactivate products it is or is not currently selling. The server validates active shop products and existing effective-rate logic on each quote and order.
- **Price integrity:** Cloud orders save a server-calculated price snapshot. Never reprice silently. If a queued offline order encounters a changed price/availability before synchronization, require customer review before creating the cloud order; if price differs before POS fulfilment, obtain customer reconfirmation or cancel/re-quote.
- **Fulfilment:** Customers choose delivery or collection at checkout. Both choices are supported; delivery requires an address and any fee/condition must be shown before submission.
- **Languages:** English is the only UI language in the initial release. Keep UI strings localization-ready for future languages. Add a login-time language selector when a second language is introduced.
- **Centralized configuration:** Keep a single permission-aware Configuration area with Application, Head Office/Business and Shop scopes. Existing Shop Settings must be inventoried and preserved. Effective value follows Shop → Business → Application only when that setting explicitly permits override.
- **Login & Session settings:** Provide an Application-level group for authorized System Administrators. Token/session lifetime defaults and guardrails are implemented in application settings and enforced by `ApiSessionService`. Runtime expiry, rotation, reuse detection, revocation and scope-change scenarios still need test evidence. Business/Shop users cannot extend platform security limits, disable required token protections or access secrets. Changes are validated and audited.
- **Order and POS boundary:** Orders are requests, not sales. Creating, accepting, rejecting, or cancelling an order must not independently post revenue, customer OS, receipts, cash movement, deposits, cylinder custody, or inventory changes. Final sale fulfilment must use existing validated POS/services, with idempotency and a unique order-to-sale association.
- **Main branch protection:** Implementation and planning changes for this initiative stay on a dedicated feature/documentation branch until reviewed. No application code, schema, configuration, or production data changes are authorized by these planning decisions.

### Final hierarchy

```text
Platform
└── Business A
    ├── Head Office
    │   └── Head Office users (business-wide scope, permission-controlled)
    ├── Shop 1
    │   ├── Manager
    │   ├── Cashier
    │   ├── Operations Manager
    │   └── Shop Admin (full access within Shop 1)
    ├── Shop 2
    │   └── Shop-specific employees and data
    └── Shop 3
        └── Shop-specific employees and data

Future: Business B, Business C, etc., each with its own Head Office and shops.
```

### Concise dependency order (the detailed WP-0 to WP-10 sections above are authoritative)

1. **WP-0 — Baseline and technical inventory:** Confirm current source schema, migration conventions, permissions, POS posting services, test setup and deployment constraints.
2. **WP-1 — Fresh-install schema and ownership:** Define business/shop/customer relationships, customer master linkage, fresh-install schema and repeatable seeds; no legacy mapping/backfill.
3. **WP-2 — Business/shop authorization:** Finalize Head Office membership, staff shop assignments, permissions, standard-role defaults, custom-role preservation and scope enforcement.
4. **WP-3 — Customer identity and onboarding:** Define username/password identity, Admin/Manager account creation, customer self-registration and approval, recovery and session revocation.
5. **WP-4 — Order model and state machine:** Define order records, enabled catalog products, price snapshots, fulfilment method, status history, idempotency and order-to-sale linkage.
6. **WP-5 — Backend services and web Order Management:** Implement server authorization, signup approval, product/shop switches and order workflows before mobile UI.
7. **WP-6 — Versioned API:** Expose customer/staff operations with TLS, session lifecycle, JSON errors, validation, rate limits and scope checks.
8. **WP-7 — Customer App:** Separate customer app with multilingual login, signup/approval states, catalog, delivery/collection, offline queue and order history.
9. **WP-8 — Business/Staff App:** Separate staff/Head Office app with multilingual login, scoped shop selector, product controls and permitted order workflows.
10. **WP-9 — POS fulfilment:** Reuse validated sales services; test idempotent order-to-sale linkage and financial/inventory regression.
11. **WP-10 — Pilot and release:** Test multi-business boundaries, two-shop pilot, approval, product toggles, price changes, delivery/collection, languages, offline sync and rollback.

### Acceptance gates

- Automated tests prove a shop user cannot read or mutate another shop's records by changing URLs, IDs, API payloads, or filters.
- Head Office users see only shops belonging to their business and only functions granted by permissions.
- Shop Admin has full access within their shop, not global platform access.
- A customer cannot submit an order for a different shop or another customer's account.
- Existing custom roles are not silently granted new capabilities.
- Pending/rejected/cancelled orders create no POS or financial/inventory postings.
- Repeated API requests cannot create duplicate orders or duplicate sales.
- Fresh installation and repeat migration/seed execution are tested.
- Existing Gas Sale/Refill, Cylinder Sale, Security Deposit/Issue Cylinder, and Cylinder Return/Refund Deposit flows pass regression tests.
- No production release until the owner approves implementation, test evidence, and deployment/rollback plan.


## 8. Confirmed customer offline-order synchronization architecture (2026-10-14)

This section records the owner's clarified requirement for the customer mobile app. It supersedes any interpretation that customer orders must be sent immediately or that the customer mobile app must connect directly to a shop's local database.

### 8.1 Applications and responsibilities

- **Customer App (Android/iOS):** A dedicated customer-only app used to browse the assigned shop's approved orderable items, prepare and submit orders, and view order status. It keeps a durable local queue of orders that have not yet been accepted by the cloud.
- **Business/Staff App (Android/iOS):** A separate app for shop staff and Head Office users. It exposes only the operational screens and actions authorized for the signed-in user and their permitted business/shop scope.
- **Central cloud API and database:** The authoritative endpoint for customer order submission, identity/shop authorization, order IDs, status, and synchronization. The mobile app must never connect directly to MySQL.
- **Shop web app:** Receives and displays orders after they have successfully reached the central cloud database. Staff process them through the existing permission-controlled Order Management workflow.
- **Head Office:** Views orders across shops in its own business only where the signed-in user has the required permissions. It can see a customer's pending local-only order only after that order has synchronized to the cloud.
- **Shop-local database:** Any existing or future local shop installation is separate from this customer-order flow. The customer app does not need direct connectivity to the shop's local database for an order to be submitted.

### 8.2 Online and offline behavior

**When internet is available**
1. Customer reviews the order and submits it to the versioned cloud API.
2. The server authenticates the customer, derives the customer's assigned shop from trusted server-side data, validates orderable items and current rules, and stores the order.
3. The server returns the stable public order number and current status.
4. The order appears in the correct shop's web Order Management queue and authorized Head Office views.

**When internet is unavailable or the request cannot be confirmed**
1. Show a clear message such as: **“Internet connection unavailable. Your order is saved on this phone and will be sent automatically when the connection returns.”**
2. Save the order payload durably in the app's local database/queue with a client-generated unique operation ID/idempotency key, creation time, and a `pending_sync` state.
3. Show that it is **saved on this device but not yet received by the shop**. Do not show it as submitted, accepted, or confirmed by the shop.
4. Retry automatically when connectivity returns and also provide a safe manual retry action.
5. Only mark it synchronized after the server confirms successful storage and returns the authoritative order ID/status. If the outcome of a request is uncertain, retry with the same idempotency key and reconcile with the server rather than creating a new order.
6. If validation fails after reconnection (for example, an item is no longer orderable or the price/availability has changed), show the reason and require the customer to review or resubmit as appropriate. Never silently alter the order or its quoted price.

**Connectivity note:** A device's connectivity indicator alone does not prove the cloud API is reachable. The app must handle timeouts, DNS/TLS errors, server errors, app restarts, and intermittent connections without losing queued orders or creating duplicates.

### 8.3 Queue and API requirements

- Persist pending orders across app restarts and device reboots; do not rely only on in-memory state.
- Use a stable idempotency key per intended order submission. Server-side uniqueness and transactional processing must prevent duplicate orders when requests are retried.
- Distinguish at least these client sync states: `pending_sync`, `syncing`, `synced`, and `needs_customer_action` (exact names can follow code conventions).
- Store only the minimum customer/order information needed for the queue, protect local sensitive data, and clear or securely handle it on logout/account removal according to an approved retention policy.
- Use exponential/backoff-style retry limits and avoid rapid repeated requests. Keep a visible retry/error state without discarding the order.
- The server derives business, shop, and customer ownership from the authenticated account. Never trust a shop ID supplied by the mobile client as authorization.
- Provide a way to reconcile local queued submissions with server order IDs after an ambiguous network failure.
- Cloud order status updates should refresh the customer app when online; status must not be fabricated or treated as live while disconnected.
- Push notifications may be added later; they are not required for the first reliable order-sync release.

### 8.4 Business and financial boundaries

- A locally queued order is only an unsubmitted customer intention. It is not visible to the shop until the cloud accepts it.
- An order in the cloud is still an order, not a POS sale. Submission, synchronization, acceptance, rejection, and cancellation must not independently post sales, revenue, customer outstanding balance (OS), receipts, cash, deposits, cylinder custody, or inventory movement.
- Shop staff must process synchronized orders using the approved web workflow and the existing authoritative POS fulfilment services.
- An order may be fulfilled only once. Use the unique order-to-sale link and idempotent fulfilment boundary documented in WP-4/WP-9.
- The app must clearly distinguish local-only pending orders from cloud-submitted orders and shop-confirmed statuses.

### 8.5 Additional acceptance tests

1. Customer submits online: one cloud order appears in only the customer's assigned shop queue.
2. Customer submits offline: the order remains in local storage, a clear offline/pending message is shown, and no shop order is falsely reported as received.
3. App is closed/reopened while offline: the pending order remains available.
4. Connectivity returns: the order synchronizes and appears once in the correct shop queue.
5. API stores the order but the response is lost: retry with the same idempotency key yields the original order, not a duplicate.
6. Connectivity repeatedly drops during synchronization: the order is retained and eventually reconciled without duplicates.
7. Customer tampers with shop/customer identifiers: server-side scope checks reject unauthorized access and submission.
8. Price/item availability changes before synchronization: the app surfaces the conflict and requires the defined review action.
9. A pending local-only order is not shown as cloud-confirmed in customer history or Head Office/shop views.
10. Synchronizing, accepting, rejecting, or cancelling an order creates no unintended POS, ledger, cash, deposit, custody, or inventory posting.
11. Logout/account change does not expose one customer's queued orders to another signed-in customer.
12. Existing POS transaction-type and ledger/inventory regression suites continue to pass.

### 8.6 Scope clarification

The first release requires **offline queueing on the customer phone**, not full offline customer-to-shop communication. If the internet is down, the shop cannot receive the order until the phone can reach the cloud. Offline order acceptance, shop-to-phone local-network synchronization, or direct peer-to-peer sync are explicitly out of scope unless separately approved.

