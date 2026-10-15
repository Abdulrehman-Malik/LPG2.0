# Perfect LPG — Development Progress

Legend:
- [ ] Pending
- [/] In Progress
- [x] Done
- [!] Blocked / user business decision required

## Phase 0 — Architecture
- [x] Repository reviewed
- [x] Target normalized database architecture defined
- [x] Core services/business rules/API contract documented
- [x] Complete one-shot MySQL schema prepared
- [x] Defined functional scope implemented; deferred business decisions are documented separately

## Phase 1 — Foundation / Local Test Gate
- [x] Complete database schema in database/schema.sql
- [x] Seed location, roles, permissions, admin, register, expense categories and cylinder types
- [x] Authentication updated for role table
- [x] Dashboard updated for target inventory/sales ledgers
- [x] TESTING.md created as mandatory test gate
- [x] User executes schema and tests foundation
- [x] User reports PASS/FAIL in TESTING.md

## Phase 2 — Master Data
- [x] Customers / Parties
- [x] Suppliers
- [x] Cylinder Types management
- [x] LPG rate management + rate history
- [x] Users / Roles / Permissions
- [x] Opening inventory
- [x] Customer/supplier ledgers

## Phase 3 — POS Sales
- [x] POS screen
- [x] Filled cylinder sale
- [x] KG refill
- [x] Cylinder exchange
- [x] Empty cylinder intake
- [x] Empty cylinder sale
- [x] Payment modes
- [x] Credit enforcement
- [x] OS balance block
- [x] Custom-rate highlighting
- [x] Atomic inventory/cash posting
- [x] Counter Cash session foundation (open/summary/close + POS cash linkage)
- [x] Receipt / print
- [x] Sale void/reversal
- [x] Phase 3 functional scope implemented and released for user testing

### Phase 3 — Transaction Integrity Hardening
- [x] Cash-session open concurrency protection
- [x] Cash-session close locking and committed-summary calculation
- [x] Cash-sale vs cash-session-close concurrency protection
- [x] Sale-row locking for void/reversal idempotency
- [x] Inventory-key concurrency locking for sales and reversals
- [x] Customer-row locking and in-transaction credit-limit recheck
- [x] Concurrency/reversal safeguards implemented; execution evidence is tracked in SQA.md
- [x] Configurable gas stock validation with explicit POS override confirmation
- [x] Gas wastage/leakage recording with physical-cylinder conversion to empty stock and filtered wastage report

## Phase 4 — Purchases & Inventory
- [x] Purchase entry
- [x] Inventory service
- [x] Physical cylinder unit inventory with actual gas weight per cylinder
- [x] Stock adjustments
- [x] Negative-stock protection
- [x] Inventory reports / stock view
- [x] Phase 4 functional scope implemented and released for user testing
- [x] Per-cylinder gas-weight inventory enhancement implemented and released for user retest

## Phase 5 — Counter Cash & Expenses
- [x] Cash register/session
- [x] Cash IN/OUT
- [x] Expenses
- [x] Customer receipts
- [x] Supplier payments
- [x] Handover / cash movement foundation
- [x] Close/reconcile
- [x] Phase 5 functional scope implemented and released for user testing

## Phase 6 — Reports & Audit
- [x] Customer ledger
- [x] Supplier ledger
- [x] Daily transactions / daily summary
- [x] Custom-rate reporting foundation
- [x] Stock report / stock view
- [x] Counter cash reconciliation
- [x] Expenses
- [x] Outstanding balance foundations
- [x] Audit log
- [x] Branch-level Shop Settings with POS defaults, sale stock validation, payment/receipt, and backup endpoint configuration
- [x] Phase 6 functional scope implemented and released for SQA

## Phase 7 — Mobile App and Multi-Shop Customer Ordering

**Implementation branch:** `feature/mobile-multishop-ordering`  
**Planning branch:** `docs/mobile-multishop-final-plan`  
**Main baseline:** `0ef0f3ff41eaa4aae1d66c4549892ab77f3898e6`  
**Current status:** Web administration and the customer/staff API foundation are implemented on this feature branch. CI passes PHP/schema/PHPUnit checks plus selected Playwright browser, API session, customer-order and shop-isolation scenarios against a disposable MySQL database. Full route-by-route SQA, financial/inventory concurrency regression and the owner two-shop pilot remain pending. `main` remains untouched.

### Confirmed product decisions

- Support multiple independent businesses; each has a Head Office and its own shops.
- Keep two separate Flutter apps: Customer App and Business/Staff App.
- Every customer belongs to exactly one shop and links to one financial customer master.
- No legacy customer-to-shop mapping/backfill is required for this initiative. If a populated deployment is later selected, its upgrade/mapping plan must be separately reviewed.
- Keep the existing role/permission implementation and add server-side business/shop scope checks.
- Keep centralized Configuration for Application, Head Office/Business and Shop scopes; preserve existing Shop Settings. Login & Session controls are application-owned.
- English only for the initial release, with localization-ready strings.
- Customer orders are not POS sales; existing validated services own financial/inventory posting.
- Customer offline queue persists locally, but a shop receives an order only after cloud synchronization.

### Work package status

- [x] Requirements modules 01–10 and decision register documented.
- [x] WP-0 — source inventory, framework/test review, Shop Settings inventory and technical decisions. Source inventory complete; runtime verification remains a separate gate.
- [x] WP-1 — fresh-install business/shop/customer ownership schema and seed setup. Migrations 032–039 are included and final mobile-order schema is present in `database/schema.sql`.
- [x] WP-2 — business/shop scope resolver, explicit user/business/shop assignments, standard-role permission integration and scope checks in browser/API paths. Cross-shop runtime cases remain pending in `SQA.md`.
- [x] WP-3 — customer app identity, pending self-registration, approval/rejection, account creation/disablement and revocable customer/staff API sessions. Browser administration screens are now present.
- [x] WP-4 — order tables, state machine, status history, request idempotency, price reconfirmation, selected physical-cylinder snapshots and unique order-to-sale linkage.
- [x] WP-5 — backend order/catalog/configuration services plus browser Order Management, Business/Shop administration, Customer App Account review and Catalog/Shop Availability pages.
- [x] WP-6 — versioned Customer/Staff API, authentication/session lifecycle, permission/scope checks and CI schema assertions. Dedicated security/isolation runtime cases remain pending.
- [ ] WP-7 — separate Customer App (Flutter). Mobile screens/app packaging were explicitly excluded from this web/backend task.
- [ ] WP-8 — separate Business/Staff App (Flutter). Mobile screens/app packaging were explicitly excluded from this web/backend task.
- [x] WP-9 — POS fulfilment service integration uses the existing validated sale/cash/inventory/ledger posting path with idempotent order linkage. Financial/inventory regression execution remains pending in `SQA.md`.
- [ ] WP-10 — two-shop pilot, release validation, owner acceptance and rollback plan.


### Web/backend delivery completed on this branch

- Browser **Businesses & Shops** administration: create businesses (System Administrator only), provision shops with their default shop settings/cash register/inventory-policy records, and manage staff shop/Head Office scope without granting global ADMIN permissions.
- Browser **Customer Orders** queue/detail: filter orders, validate legal state transitions, display price-reconfirmation state and status history, select physical cylinders and post fulfilment through the existing POS service.
- Browser **Customer App Accounts**: review pending signups, link an existing financial customer or create a new one with credit disabled by default, reject with a reason, create app credentials for existing customers, and disable/revoke sessions.
- Browser **Customer Ordering Catalog**: maintain the business-level product catalog and activate/deactivate products per authorized shop.
- Versioned customer/staff API and services include account/session lifecycle, catalogue, centralized settings, order creation/cancellation/reconfirmation, shop order queue/status, physical-cylinder fulfilment and idempotent POS sale linkage.
- These are implementation statuses, not runtime test results. Follow the page-by-page workflow and SQA execution register before release.

### Development safety gates

- All work stays on `feature/mobile-multishop-ordering`; no direct commits to `main`.
- No production database is queried or modified by this development workflow.
- Preserve current POS, ledgers, receipts, deposits, cylinder custody, cash, inventory and custom-role behavior.
- Do not report tests as PASS without execution evidence. CI covers PHP syntax, Composer, MySQL 8 schema/migration smoke checks, PHPUnit, and selected Playwright browser/API runtime scenarios against disposable test data. Full route-by-route SQA, mobile-device validation, concurrency/reversal tests and owner acceptance still require separate evidence; CI is not proof that every page/device scenario passed.
- Do not merge or deploy until all security, isolation, POS-regression and owner-acceptance gates pass.

See [WP-0 source inventory](docs/mobile-multishop/wp0-source-inventory.md), [the detailed implementation plan](docs/mobile-multishop/09-implementation-plan-and-decision-register.md), and [centralized configuration requirements](docs/mobile-multishop/10-configuration-management.md).

## Workflow Gate

Functional development can be marked [x] when the requested module has been implemented and is packaged for user/SQA testing. User testing is recorded separately in TESTING.md and must not be represented as PASS until the user executes it.

Phase 3 transaction-integrity hardening tests are explicitly postponed by user request and remain pending.

## Final Development Review

- [x] Full application code review completed after functional implementation.
- [x] Physical-cylinder inventory adjustments corrected to maintain unit-level state.
- [x] Partial wastage and configured wastage allowance implemented.
- [x] Cylinder-type stock-validation overrides enforced by POS posting.
- [x] Customer and supplier receipt/payment balance limits enforced.
- [x] Local environment configuration removed from source control.
- [x] Branch Shop Settings moved POS defaults/configuration out of the POS screen.
- [x] SQA.md created as the complete execution matrix.
- [x] TESTING.md updated to the final functional SQA gate.

SQA execution remains a verification activity, not an unfinished development feature.
