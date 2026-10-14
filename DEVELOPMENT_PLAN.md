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
**Requirements branch:** `feature/mobile-multishop-ordering-requirements`
**Current status:** planning pack complete; implementation not started. Business decisions and legacy data mapping remain approval gates. Keep all work on the feature branch, never directly on `main`.

- [x] Module 01 — Product scope and target architecture documented
- [x] Module 02 — Business, shops, staff roles, permissions and scope requirements documented
- [x] Module 03 — Customer mobile accounts and one-customer-to-one-shop assignment documented
- [x] Module 04 — Order lifecycle and safe POS integration documented
- [x] Module 05 — API contract and security requirements documented
- [x] Module 06 — Flutter Android/iOS app requirements documented
- [x] Module 07 — Database model and non-destructive legacy migration plan documented
- [x] Module 08 — QA and release gates documented
- [x] Module 09 — Dependency-ordered implementation plan and decision register documented
- [x] Complete documentation-only development planning on the feature branch
- [ ] Review/approve the mobile multi-shop decision register with the owner
- [ ] Inspect current data in a safe non-production copy and produce customer-to-shop legacy mapping report
- [ ] Finalize the remaining owner decisions listed in docs/mobile-multishop/09-implementation-plan-and-decision-register.md
- [ ] Implement business-to-location model and shop-scoped authorization
- [ ] Add verified customer-to-shop assignment and customer mobile-account linking
- [ ] Add order tables, idempotency and status history
- [ ] Add web Order Management screen
- [ ] Add versioned mobile API and security tests
- [ ] Build Flutter app for Android and iOS
- [ ] Run two-shop isolation pilot and existing POS regression suite
- [ ] Obtain explicit approval before merge/deployment

## Mobile / Multi-Shop Planning Gate

- [x] Requirements and architecture modules 01–08 documented on `feature/mobile-multishop-ordering-requirements`.
- [x] Module 09 implementation plan, decision register, dependency order, acceptance criteria and release gates documented.
- [x] Documentation-only planning completed; no mobile/order application code or database schema was changed.
- [ ] Owner reviews/approves the business decisions in `docs/mobile-multishop/09-implementation-plan-and-decision-register.md`.
- [ ] Inspect current data in a safe non-production copy and prepare/review customer-to-shop mapping.
- [ ] Explicit approval to begin implementation.
- [ ] Until approval, do not modify application code, schema/migrations, production data, or `main`.

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
