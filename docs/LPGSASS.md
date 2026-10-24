# LPGSASS — LPG Distribution SaaS: Agent Brief

> Put this file in the **root of the existing codebase**. It contains (1) the prompt for the AI agent, (2) the product context, (3) the full requirements list with IDs, and (4) the workflow the agent must follow.
> Written for Claude Code (or any coding agent with repo access). Last updated: October 2026.

---

## 0. How to use this file (for the human owner)

1. Copy this file to the repo root as `LPGSASS.md`. Create an empty `docs/` folder.
2. Open Claude Code in the repo.
3. Paste the **Kickoff Prompt** from Section 1.
4. The agent works in phases and **stops at every gate** waiting for you. Reply `APPROVED`, or tell it what to change.
5. Nothing gets implemented until you approve the audit (Gate A) and the backlog (Gate B).

---

## 1. Kickoff Prompt (copy-paste this to the agent)

```
You are a senior PHP/CodeIgniter engineer and technical product analyst.

Read LPGSASS.md in this repository completely before doing anything else.

Context: this codebase is an LPG (cylinder gas) distribution management
application, about 70% built in PHP (CodeIgniter). I want to turn it into a
multi-tenant, API-first SaaS product, first for LPG distributors in Pakistan.

Your job, in this order:
1. ORIENT: read the whole codebase (read-only) and produce docs/CODEBASE_MAP.md.
2. AUDIT: compare the codebase against every requirement in LPGSASS.md
   Section 4 and produce docs/AUDIT.md with a coverage matrix and evidence.
   Then STOP and wait for my approval (Gate A).
3. BACKLOG: convert every gap into a small, testable backlog item and write
   docs/BACKLOG.md, prioritised and ordered by dependency. Then STOP and
   wait for my approval (Gate B).
4. IMPLEMENT: build the approved backlog items one at a time, following the
   protocol in Section 6. STOP for my review after each item (Gate C).

Rules you must follow: Section 3 of LPGSASS.md. In particular: do not guess,
do not rewrite what already works, never claim something is done without
running it, and ask me when a requirement is ambiguous.

Start with step 1 now. Do not modify any source code during steps 1-3.
```

---

## 2. Role and mission (for the agent)

**Role:** You are both an auditor and an implementer. First you establish the truth about what the code does today. Then you close the gaps in small, verified steps.

**Mission:** Make the existing application meet the requirements in Section 4 with the *least* change necessary. Reuse what works. Refactor only where a requirement (for example multi-tenancy or the API) demands it.

**Success looks like:**
- Every requirement has an honest status backed by evidence.
- Every gap is a small backlog item with acceptance criteria.
- Every implemented item is tested, documented, and reviewed by the human before the next starts.

---

## 3. Ground rules (non-negotiable)

1. **Evidence over assumption.** Every status in the audit must cite file paths, routes, controllers, models, tables, or tests. If you could not verify something, mark it `UNKNOWN` and say what you would need to confirm it.
2. **Do not guess business rules.** If a requirement is ambiguous or the code contradicts it, add it to `docs/OPEN_QUESTIONS.md` and ask the human. Do not invent LPG, tax, or pricing rules.
3. **Do not rewrite working code.** Prefer small diffs. No framework swap, no language change, no mass reformatting. Stack stays PHP + CodeIgniter unless the human explicitly approves a change.
4. **One item at a time.** Never start the next backlog item before the human approves the previous one.
5. **Run it before you claim it.** Run the app, migrations, and tests. If you cannot run something, say so explicitly instead of saying "done".
6. **Database safety.** Every schema change is a reversible migration. Never drop columns or tables with data without a backup step and human approval. Never edit production data directly.
7. **Secrets.** Never commit credentials, API keys, or `.env` files. If you find secrets already committed, report them in the audit under Security and do not copy them elsewhere.
8. **Tenant isolation is a security requirement**, not a feature. Any new query touching tenant data must be tenant-scoped and covered by an isolation test.
9. **Security basics always on:** parameterised queries or the query builder, output escaping, CSRF on web forms, input validation, password hashing, rate limiting on auth endpoints, least-privilege roles.
10. **Keep a paper trail.** Update `docs/PROGRESS.md` after every item. Keep `docs/AUDIT.md` statuses current.
11. **Be concise in reports.** Lead with the result, then evidence, then risks. No long narratives.
12. **Stop at gates.** Gate A (audit), Gate B (backlog), Gate C (after each implemented item). Wait for explicit approval.

---

## 4. Product context

**Product:** A web back office plus mobile-ready API for LPG cylinder distributors, sold as a monthly subscription (SaaS).

**First market:** Pakistan (home market, Lahore-based owner). India is a possible second phase and is **out of scope until the human decides** (see Section 5.9).

**How the Pakistan market works (assumptions to respect; verify against the code and with the human):**
- LPG is sold only by OGRA-licensed marketing companies. Product flows: importer/refinery → marketing company → distributor → retailer → household.
- A recent official survey counted about 313 LPG marketing companies and 6,000+ authorized distributors. Some marketers run networks of 20–100+ distributors.
- OGRA notifies a maximum LPG price every month. Typical cylinders: **11.8 kg domestic** and **45.4 kg commercial**. Other sizes may exist and must be configurable.
- A recurring complaint in the trade: missing receipts and weak traceability from supplier to retailer, and prices charged above the notified rate. The product should make receipts, stock movement, and price compliance easy to document.
- Field reality: unreliable power and internet, low-end Android phones, customers who order by phone call or WhatsApp, heavy use of cash and credit ("udhaar") ledgers, and bank transfer/JazzCash/Easypaisa payments rather than cards.

**Business model:** monthly subscription per tenant (and per branch), onboarding trial, manual invoicing first. An optional on-premise package (Docker) may be sold later to large marketing companies.

**Target users:** distributor owner, branch manager, cashier, delivery driver, accountant, SaaS operator (super admin). Later: marketing-company admin who oversees many distributors.

---

## 5. Requirements

Priority key: **P0** = needed for the first paid pilots · **P1** = after pilots start · **P2** = later / decide later.
Every requirement has an ID. The audit and backlog must use these IDs.

### 5.1 Platform and architecture (PLT)

| ID | Requirement | Pri |
|---|---|---|
| PLT-01 | Runs on a supported PHP version (8.1+) and CodeIgniter 4. If the codebase is on CI3 or old PHP, produce an upgrade plan and size it before anything else. | P0 |
| PLT-02 | **Multi-tenant**: one tenant per distributor company. Single database with `tenant_id` on every tenant-owned table, enforced centrally (base model / query filter / middleware) so a missed WHERE clause cannot leak data. | P0 |
| PLT-03 | Existing single-customer data can be migrated into a default tenant with a reversible, tested migration. | P0 |
| PLT-04 | **API-first**: all business logic lives in services/models, not in views. A versioned JSON REST API (`/api/v1/...`) exposes everything the web back office does. | P0 |
| PLT-05 | API authentication with tokens (CodeIgniter Shield or equivalent), per-user and per-tenant, with revocation. | P0 |
| PLT-06 | Role-based access control: super admin (SaaS operator), tenant owner, manager, cashier, driver, accountant. Permissions enforced in both web and API. | P0 |
| PLT-07 | Audit log of sensitive actions (stock adjustments, price changes, invoice edits/voids, payment edits, user/role changes) with user, time, before/after. | P0 |
| PLT-08 | Configuration through environment files; no hard-coded hostnames, credentials, or single-customer constants. | P0 |
| PLT-09 | Automated database backups and a documented restore procedure. | P0 |
| PLT-10 | Consistent error handling and structured logging; no stack traces shown to users in production. | P0 |
| PLT-11 | Docker packaging so the app can also be deployed on a customer's own server (on-premise option). | P2 |
| PLT-12 | CI pipeline running tests and static analysis on every push. | P1 |
| PLT-13 | Tenant data export (CSV/Excel) for every major list, so customers never feel locked in. | P1 |

### 5.2 Offline and mobile readiness (MOB)

The mobile apps themselves come later. These requirements make the **backend ready** for them.

| ID | Requirement | Pri |
|---|---|---|
| MOB-01 | Client-generated UUIDs allowed as record identifiers for records created offline (orders, deliveries, payments, stock movements). | P0 |
| MOB-02 | Idempotency: repeating the same create request (same idempotency key/UUID) never creates duplicates. | P0 |
| MOB-03 | Every syncable table has `created_at`, `updated_at`, soft-delete (`deleted_at`) and a `version` or equivalent for conflict detection. | P0 |
| MOB-04 | Delta sync endpoint(s): "give me everything changed since timestamp/cursor X" for tenant-scoped data. | P1 |
| MOB-05 | Conflict policy documented and implemented: stock movements, payments, and deliveries are **append-only ledger entries** (no conflicts). Simple profile/master fields use last-write-wins with `updated_at`. | P1 |
| MOB-06 | Staff/driver web app works as an installable **PWA** with an offline queue for sales, deliveries, cash collection, and cylinder returns, syncing when connectivity returns. | P1 |
| MOB-07 | Driver/staff mobile app (Flutter or React Native, decided by the human) consuming the same API. | P2 |
| MOB-08 | Customer-facing ordering: first a WhatsApp/ordering link; a customer mobile app only if pilots show the need. | P2 |
| MOB-09 | UI works well on low-end Android phones and slow connections (small payloads, pagination, no heavy assets). | P1 |

### 5.3 Core domain: customers, products, inventory (CORE)

| ID | Requirement | Pri |
|---|---|---|
| CORE-01 | Customer records with type (domestic, commercial, retailer/dealer), phone (WhatsApp-capable), address/area, credit limit, notes, active/blocked status. | P0 |
| CORE-02 | Configurable cylinder/product types (name, net kg, tare, gross, deposit amount, price basis). Defaults for 11.8 kg and 45.4 kg; other sizes addable. | P0 |
| CORE-03 | Cylinder stock by state per branch: full in stock, empty in stock, with customer, on vehicle/in transit, at plant (for refill), damaged, lost. | P0 |
| CORE-04 | Stock is driven by an **append-only movement ledger** (never edit a balance directly). Each movement: type, product, quantity, from-state, to-state, branch, user, reference document, timestamp. | P0 |
| CORE-05 | Purchase / receive filled cylinders from marketer or plant (supplier document no., rate, quantity, empties returned). | P0 |
| CORE-06 | Sale with **cylinder swap** (full out, empty in) including cases where the customer returns fewer empties than received (creates a customer cylinder balance). | P0 |
| CORE-07 | New connection / cylinder with deposit, deposit refund on return, and per-customer deposit and cylinder balance statements. | P0 |
| CORE-08 | Empties returned to marketer/plant and refill cycle tracking, with shortage/damage/lost handling and reconciliation. | P1 |
| CORE-09 | Optional per-cylinder serial/QR tracking (scan to identify a cylinder). Must be optional per tenant. | P2 |
| CORE-10 | Multi-branch/warehouse support within one tenant, with inter-branch transfers. | P1 |
| CORE-11 | Suppliers (marketing companies) master data and supplier ledger. | P1 |

### 5.4 Pricing, billing, receipts, credit (BIL)

| ID | Requirement | Pri |
|---|---|---|
| BIL-01 | **Price master**: monthly notified price per kg (OGRA maximum consumer price) with effective-from date and history. Manual entry screen; optional import later. | P0 |
| BIL-02 | Cylinder price calculation from price per kg × net kg, with configurable rounding, distributor margin, and delivery charge. | P0 |
| BIL-03 | Price-cap compliance: warn (or block, configurable per tenant) when a sale price exceeds the notified maximum. All overrides require a reason and are audit-logged. | P0 |
| BIL-04 | Invoices/receipts with sequential, gap-free numbering per tenant (and branch), customer, lines, deposit lines, discounts, payment status. | P0 |
| BIL-05 | Receipt output: PDF and 58 mm / 80 mm thermal print layout, in Urdu and English, with tenant name/logo, and a share link (e.g. WhatsApp). | P0 |
| BIL-06 | Sales tax fields and calculation are **configurable** per tenant (rate, inclusive/exclusive, registration number). Do not hard-code Pakistani tax rules; see Open Questions. | P0 |
| BIL-07 | Customer credit ledger ("udhaar"): balances, credit limit enforcement, statements, payment receipts, ageing report. | P0 |
| BIL-08 | Payment methods: cash, bank transfer, JazzCash, Easypaisa, cheque — recorded as methods with reference numbers (no gateway integration required yet). | P0 |
| BIL-09 | Void/return of an invoice through a reversing entry, never by deleting; fully audit-logged. | P0 |
| BIL-10 | Expenses (fuel, salaries, maintenance, rent) and daily cash book. | P1 |
| BIL-11 | Payment gateway integration (JazzCash/Easypaisa APIs) for online customer payments. | P2 |
| BIL-12 | Government e-invoicing/POS integration if legally required for the tenant segment. **Do not implement until the human confirms the requirement.** | P2 |

### 5.5 Orders and delivery (DEL)

| ID | Requirement | Pri |
|---|---|---|
| DEL-01 | Order entry for phone/WhatsApp/walk-in orders by staff (customer, items, address, requested time). | P0 |
| DEL-02 | Order status flow: new → assigned → out for delivery → delivered / failed / cancelled. | P0 |
| DEL-03 | Assign orders to a driver and vehicle; driver sees only their own list. | P0 |
| DEL-04 | Driver records delivery: cylinders delivered, empties collected, payment collected (cash/credit), notes. | P0 |
| DEL-05 | End-of-day driver settlement: expected cash and cylinders vs actually returned; shortages flagged. | P0 |
| DEL-06 | Vehicle master (number, driver, capacity, maintenance notes). | P1 |
| DEL-07 | Route/area grouping to batch deliveries; simple map link per address. | P1 |
| DEL-08 | Proof of delivery (signature or photo). | P2 |
| DEL-09 | Recurring/standing orders for commercial customers (e.g. every week). | P2 |

### 5.6 Reports and dashboards (RPT)

| ID | Requirement | Pri |
|---|---|---|
| RPT-01 | Daily closing report: sales, cash received, credit given, cylinders out/in, stock position. | P0 |
| RPT-02 | Stock report by product and state, per branch; stock movement history with filters. | P0 |
| RPT-03 | Receivables / customer ageing report and individual statements. | P0 |
| RPT-04 | Customer cylinder-balance report (who holds how many of our cylinders, deposits held). | P0 |
| RPT-05 | Price-compliance report: sales above the notified price, with reasons. | P1 |
| RPT-06 | Driver/delivery performance and shortage report. | P1 |
| RPT-07 | Profit/margin report by product and period. | P1 |
| RPT-08 | Owner dashboard (today/this month at a glance). | P1 |
| RPT-09 | All reports exportable to Excel/PDF. | P1 |

### 5.7 SaaS operations (SAS)

| ID | Requirement | Pri |
|---|---|---|
| SAS-01 | Tenant onboarding: create tenant, owner user, default products, default settings, sample data optional. | P0 |
| SAS-02 | Super-admin panel: list tenants, status, plan, usage, last activity, impersonate/support-login (audit-logged). | P0 |
| SAS-03 | Plans and limits (users, branches, records) enforced per tenant. | P1 |
| SAS-04 | Trial period and subscription status; on non-payment: warning, then read-only grace, then suspension. **Never delete tenant data** automatically. | P1 |
| SAS-05 | Subscription invoicing for the SaaS operator (manual invoices first; JazzCash/Easypaisa/bank transfer references). | P1 |
| SAS-06 | Per-tenant branding (name, logo, receipt header/footer, currency, language). | P1 |
| SAS-07 | Usage analytics for the operator: which features each tenant actually uses. | P2 |

### 5.8 Localization and UX (LOC)

| ID | Requirement | Pri |
|---|---|---|
| LOC-01 | Full **English and Urdu** support, including right-to-left layout. All user-facing strings in language files, none hard-coded. | P0 |
| LOC-02 | PKR as default currency with correct number/date formatting; currency configurable. | P0 |
| LOC-03 | Receipts and reports render Urdu text correctly in PDF and thermal print. | P0 |
| LOC-04 | Simple, fast screens for cashiers: keyboard-friendly, minimal clicks for the common "swap and sell" transaction. | P1 |
| LOC-05 | Accessibility basics: readable font sizes, contrast, works on small screens. | P1 |

### 5.9 Marketing-company (multi-distributor) module and India (EXT)

| ID | Requirement | Pri |
|---|---|---|
| EXT-01 | Tenant hierarchy: a marketing company can oversee many distributor tenants and see their stock, sales, and compliance (with the distributors' consent). | P2 |
| EXT-02 | India variant: GST invoicing, INR, UPI, distributor-company specific workflows. **Out of scope until the human decides.** Legal, payment, and cross-border feasibility must be checked by the human first. | P2 |
| EXT-03 | Hardware integrations (cylinder scales, label printers, GPS). | P2 |

### 5.10 Non-functional requirements (NFR)

| ID | Requirement | Pri |
|---|---|---|
| NFR-01 | Tenant isolation proven by automated tests (a user from tenant A can never read or write tenant B data via web or API). | P0 |
| NFR-02 | OWASP Top 10 review: injection, auth, session, XSS, CSRF, access control, file upload, misconfiguration. Findings recorded in the audit. | P0 |
| NFR-03 | Critical business logic (stock ledger, pricing, billing, credit) covered by automated tests. | P0 |
| NFR-04 | Pages load acceptably on a slow mobile connection; list endpoints are paginated. | P1 |
| NFR-05 | Personal data (customer names, phones, addresses) protected; backups encrypted at rest where possible; retention and deletion policy documented. | P1 |
| NFR-06 | Documentation: README, setup guide, `.env.example`, API docs (OpenAPI), and a short operator runbook. | P1 |

---

## 6. Workflow the agent must follow

### Phase 1 — ORIENT (read-only)

Produce `docs/CODEBASE_MAP.md` containing:
- Framework, PHP version, database engine, key dependencies and versions.
- Folder structure and the purpose of each major folder.
- Entry points: routes (web and API), controllers, models, libraries/services, views, cron jobs.
- Database schema summary: tables, key columns, relationships, and which tables are tenant-owned vs global.
- Authentication and authorization approach as it exists today.
- How to run the app and tests locally (commands you actually ran and their results).
- Code health notes: duplicated logic, business logic inside views/controllers, missing tests, dead code, security smells.

### Phase 2 — AUDIT → `docs/AUDIT.md` → **Gate A**

For **every** requirement ID in Section 5, write one row:

| ID | Requirement (short) | Status | Evidence | Gap / notes | Effort (S/M/L) |
|---|---|---|---|---|---|

Status values:
- `DONE` — works as specified; evidence shows it (and ideally a test or a run).
- `PARTIAL` — some of it exists; say precisely what is missing.
- `MISSING` — not present.
- `DIVERGENT` — exists but behaves differently from the requirement; say how.
- `UNKNOWN` — could not verify; say what is needed.

Also include in the audit:
- **Summary:** counts per status, and an honest overall "% of P0 requirements met".
- **Top risks:** the five things most likely to hurt the pilots (e.g. no tenant isolation, business logic in views, no backups).
- **Security findings** (NFR-02) and any committed secrets.
- **Extra functionality found** in the code that is **not** in this requirements list (the human decides whether to keep it).
- **Open questions** (also written to `docs/OPEN_QUESTIONS.md`).

Then **STOP**. Ask the human to review and answer open questions.

### Phase 3 — BACKLOG → `docs/BACKLOG.md` → **Gate B**

Convert every gap (`PARTIAL`, `MISSING`, `DIVERGENT`) into backlog items. Format:

```
### BL-012 — <short title>
Requirement(s): PLT-02, NFR-01
Priority: P0
Size: S | M | L   (L must be split into smaller items)
Depends on: BL-003, BL-007
Problem: <what is wrong or missing today, citing files>
Scope: <what will change>
Out of scope: <what will not change>
Acceptance criteria:
  - [ ] <observable, testable statement>
  - [ ] <...>
Tests to add: <unit / feature / tenant-isolation>
Migration: <yes/no, reversible?>
Risks: <data loss, breaking change, performance>
```

Ordering rules:
1. Foundation first: PLT-01 (upgrade, if needed), PLT-02/03 (multi-tenancy), PLT-04/05/06 (API, auth, roles), PLT-07 (audit log), NFR-01 (isolation tests).
2. Then the core money-and-stock logic: CORE-04 (movement ledger) → CORE-06/07 (swap, deposits) → BIL-01..04 (pricing, invoices) → BIL-07 (credit).
3. Then delivery, receipts/print, localization, reports.
4. Then P1, then P2 only if the human asks.

No item may be larger than roughly one to two days of focused work. Split anything bigger.

Then **STOP**. Ask the human to approve, reorder, drop, or change priorities.

### Phase 4 — IMPLEMENT (one item at a time) → **Gate C** after each

For the next approved backlog item, follow this protocol exactly:

1. **Restate** the item, its acceptance criteria, and your plan (files you will touch, migration, tests). Keep it short.
2. **Branch:** work on a branch named `bl-012-short-title`. Never work on `main`/`master` directly.
3. **Migration first** (if schema changes): reversible `up()` and `down()`; test both directions on a copy of the database.
4. **Implement** with the smallest sensible diff. Put logic in services/models, not views.
5. **Tests:** add or update tests for the acceptance criteria. For anything touching tenant data, add a tenant-isolation test.
6. **Run everything:** migrations, the full test suite, and a manual smoke test of the feature. Record the commands and results.
7. **Docs:** update `docs/AUDIT.md` statuses, `docs/PROGRESS.md`, API docs if endpoints changed.
8. **Report** to the human using this template, then **STOP**:

```
## BL-012 — <title>  [READY FOR REVIEW]
Result: <one or two sentences>
Acceptance criteria: <each one: met / not met, with proof>
Changed files: <list>
Migration: <name, reversible: yes/no>
Tests: <added/updated, pass/fail counts>
How to verify manually: <numbered steps>
Risks / follow-ups: <anything the human should know>
Questions: <if any>
```

If you hit something that changes the plan (a hidden dependency, a design flaw, a bigger problem than expected), **stop and report it** instead of pushing on. Add or reshape backlog items and ask for approval.

### Phase 5 — HARDENING (after P0 items are done)

- Full tenant-isolation test pass across web and API.
- Security review against OWASP Top 10; fix findings.
- Backup and restore drill, documented and actually performed.
- Performance check on a dataset of realistic size (e.g. 50k customers, 500k stock movements).
- Pilot readiness checklist: onboarding a new tenant end to end, receipts print correctly in Urdu, offline queue (if built) syncs without duplicates.

---

## 7. Definition of Done (per item)

- All acceptance criteria met and demonstrated.
- Migrations reversible and tested.
- Automated tests added and the full suite passes.
- No new security warnings; tenant isolation test added where relevant.
- Audit and progress docs updated.
- Human has said `APPROVED`.

---

## 8. Open questions the agent should ask early

Answer from the code where possible; ask the human only for what cannot be found.

1. Which CodeIgniter and PHP versions does the codebase use today, and which database?
2. Is the app single-customer today (one install per customer, or one shared database)? How many real users/data exist that must be migrated?
3. Is anything already live in production? If yes, what is the hosting setup and is there a staging copy?
4. Tax: what sales-tax rules apply to LPG distributors, and is any government POS/e-invoicing integration legally required for the first target customers? (Do not assume; the human will confirm.)
5. Is Urdu already supported anywhere in the app? Which printers (thermal widths) do pilot customers use?
6. Team skills for the future mobile app: PHP only, or also JavaScript/Flutter?
7. Is there existing functionality in the app that is not in this list but must be preserved?
8. Hosting target and budget for the first year (VPS, cloud region, backups).

---

## 9. Out of scope for now

- Rewriting the app in another language or framework.
- India-specific features (GST, UPI) and any cross-border selling.
- Native iOS/Android apps and customer mobile app (backend readiness only).
- Payment-gateway integrations and government e-invoicing, until confirmed.
- IoT/GPS/scale integrations.

---

## 10. Progress log template (`docs/PROGRESS.md`)

```
| Date | Item | Status | Branch | Tests | Notes |
|---|---|---|---|---|---|
```

Update it after every item and at the end of every working session.
