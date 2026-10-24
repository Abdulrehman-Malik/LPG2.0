# OPEN_QUESTIONS

Questions the code cannot answer. **Blocking** questions must be answered before the backlog (Gate B) can be sized and ordered.
Answers from the code to LPGSASS §8 are at the end.

## Decisions delegated to the agent (2026-10-24)

The owner asked the agent to choose what fits the LPG business and the current implementation best, and to make workflow choices **configurable** so they can be changed later. Every choice below is a setting in `ConfigurationService` (application → business → shop) whose **default keeps today's behaviour** where behaviour already exists.

| Q | Decision | Setting (default) |
|---|---|---|
| Q-04 Tax | Tax engine only: rate, inclusive/exclusive, registration no., all entered by the tenant. No rates or rules are shipped (Rule 2). | `tax_enabled` (off), `tax_rate` (0), `tax_mode` (exclusive), `tax_registration_no` |
| Q-05 Numbering | Keep the gap-free numbering and make the reset period configurable. | `document_number_period` = day \| month \| year \| never (**day**, as today) |
| Q-06 Costing | Weighted-average cost per KG per branch, recalculated on purchase (P1, BL-090). | `costing_method` (weighted_average) |
| Q-08 Mobile | Flutter (skeletons already in `mobile/`). | — |
| Q-09 Printers | Support 58 mm, 80 mm and A4 as HTML print layouts. Urdu is shaped by the browser and prints as an image on thermal drivers. | `receipt_default_format` (80mm) |
| Q-10 Payment methods | Add bank_transfer, jazzcash, easypaisa; keep cash, cheque, credit and legacy `online`. The enabled list is configurable. | `payment_methods_enabled` (cash, cheque, bank_transfer, jazzcash, easypaisa, credit) |
| Q-11 Roles | Fixed platform role set now (OWNER, MANAGER, CASHIER, DRIVER, ACCOUNTANT + super admin). Per-tenant permission editing comes later as a P1 item. `pak` / PAK_OPS is treated as customer-specific: disabled for new tenants and kept only where it already exists. | — |
| Q-12 Hosting | Not an agent decision. Backups are env-configurable (directory, encryption key, retention), so any host works. | `backup.*` env |
| Q-13 Refresh / restore | Keep both, restricted to the system admin. Refresh is disabled in production and never truncates the audit log. | `CI_ENVIRONMENT`, `backup.allowRestoreInProduction` (false) |
| Q-14 Sizes | New tenants get 6, 11.8, 15, 45.4 kg (editable). Existing 45 / 45.2 rows untouched. | — |
| Q-15 Swap shortage | Configurable. Default **deposit**: the shortfall is charged as a security deposit and creates custody, which matches the existing Security Deposit flow and Pakistan practice. Walk-in customers can never carry "owed" (cash rule), so for them the shortage is always deposit or cylinder sale. | `swap_shortage_mode` = deposit \| owed \| cylinder_sale \| cashier_choice (**deposit**) |
| Q-16 OGRA price | Platform-wide table maintained by the operator (OGRA notifies one national price each month), per KG plus per cylinder size (11.8 / 45.4). A business may enter its own if allowed. Cap check defaults to **warn**. | `regulated_price_source` = platform \| business (platform); `price_cap_mode` = off \| warn \| block (warn) |
| Q-17 Price lists | Tenant-defined lists; Retail and Wholesale seeded. | `default_price_list`, `walkin_price_list` (retail) |
| Q-18 Tenant key | Keep `business_id` (business = tenant). | — |
| Q-19 Port from fork | Urdu layer; price lists / RateResolver ideas; refill purchases with plants; supplier advances; supplier credit-limit hint; performance indexes; CI gates (env guard, dependency audit). **Not** ported: editing or deleting rate history rows. Untracking `vendor/` is done in BL-076 as a separate commit. | — |
| BL-047 Margin | Distributor margin = Rs per KG below or above the notified price, used only to *suggest* list prices. | `price_suggestion_margin_per_kg` (0) |
| POS rate change | From the fork. | `pos_rate_change_mode` = not_allowed \| allowed \| reason_required (reason_required) |

Still for the owner (cannot be delegated):
- **mPDF licence (BL-066):** GPL is fine for hosted SaaS but has obligations if the app is distributed on-premise. Asked when BL-066 is reached. HTML print and the share link cover receipts until then.
- **Q-12 hosting budget.**

## Answers received (2026-10-24, owner)

| ID | Answer | Consequence |
|---|---|---|
| Q-01 | The `C:\PerfectLPG` code is a different repository, `C:\data\lpg-management-system` (`github.com/AchiveXsl/lpg-management-system`), and it may be reused. | It shares early history with this repo but diverged; its migrations 033–036 clash with ours by number. Reuse means **porting** features (Urdu `UiLanguage` + ~2,000 lines of `ur` catalogs, price lists / `RateResolver`), not merging histories. |
| Q-02 | Nothing is live. | PLT-03 shrinks: no production data to migrate. The default-tenant step only has to be correct for fresh installs and test databases. Schema changes still go through reversible migrations. |
| Q-03 | All three sale kinds happen: swap (full for empty), refill by KG, and outright cylinder sale (existing Cylinder Sale type). | CORE-06: add a **swap** transaction. Refill-by-KG and Cylinder Sale stay. Shortage rule still open (Q-15). |
| Q-07 | Yes: store the OGRA maximum price separately from selling rates. Rates must be flexible: retail, wholesale and customer-wise, as in `lpg-management-system`, or better. | BIL-01/03 become a regulated-price master plus a cap check. Selling prices move to a price-list model (backlog BL-040…BL-045). |

## New questions (raised by the answers)

| ID | Blocking | Question | Why |
|---|---|---|---|
| Q-15 | Yes, for BL-035 only | Swap with fewer empties returned than fulls taken: what happens to the difference? (a) record "cylinders owed" on the customer, settled later, (b) charge a security deposit, (c) charge the cylinder price (it becomes a Cylinder Sale line), or (d) cashier chooses per sale? Should walk-in customers be allowed a shortage at all? (Walk-ins cannot carry balances today.) | Decides the swap shortage rule. |
| Q-16 | Yes, for BL-040 | OGRA maximum price: does the SaaS operator enter it once for **all** tenants each month, or does each tenant enter its own? Is it per KG only, or also per 11.8 kg and 45.4 kg cylinder? Do regional variations need supporting? | Data ownership of the regulated price table. My proposal: operator-maintained platform table, per KG plus per cylinder size, with an optional tenant override flagged in reports. |
| Q-17 | No | Price lists: fixed **Retail + Wholesale** as in the other repo, or **tenant-defined lists** (Retail and Wholesale seeded, plus e.g. "Commercial" or "Dealer")? | My proposal is tenant-defined lists: same effort, more flexible. |
| Q-18 | No | Naming: keep `business_id` as the tenant key (it already exists on many tables), or rename to `tenant_id` everywhere? | My proposal: keep `business_id`, treat "business = tenant" in docs, and avoid a mass rename. |
| Q-19 | No | Besides Urdu and pricing, which `lpg-management-system` features should be ported? Candidates: refill purchases with plants and supplier advances (relevant to CORE-08/CORE-11), supplier credit-limit hint, performance indexes and asset caching, CI lint / env-guard / dependency-audit gates, untracking `vendor/`. Note that its "edit/delete rate history rows" conflicts with the audit/history rules and I would not port it. | Scope of reuse. |

## Original questions

| ID | Blocking | Question | Why it matters / what the code shows |
|---|---|---|---|
| Q-01 | ~~Yes~~ answered | The installed copy at `C:\PerfectLPG` ("LPG TEST") runs code that is **not in this repo**: it has an Urdu/RTL login page and a different migration list (e.g. `036_20261021_customer_special_rates.sql`). Where does that code live, and should its Urdu/i18n work be brought into this repo instead of rebuilt? | Avoids building LOC-01 twice and diverging codebases. This audit covers this repo only. |
| Q-02 | ~~Yes~~ answered | Is anything live in production (Railway per `docs/railway-deployment.md`, the Windows installer, or both)? How many customers, branches and rows must be migrated into tenants? | Sizes PLT-03 and decides how careful the default-tenant migration must be. Migration 032 left legacy branches without a business. |
| Q-03 | ~~Yes~~ answered | What does a typical pilot distributor's sale look like? (a) customer hands in an empty and takes a full (swap), (b) gas is refilled by KG into the customer's own cylinder (what the POS does now), (c) cylinder sold outright, or a mix? Should customers carry a "cylinders owed" balance when they return fewer empties than they take? | CORE-06 is DIVERGENT: no swap transaction exists, and `empty_cylinder_received` is always 0. This decides whether a new POS type is needed. |
| Q-04 | No (for P0 sizing) | Which sales-tax rules apply to LPG distributors in your pilot segment (rate, inclusive/exclusive, registration no. on receipt)? Is FBR POS/e-invoicing integration legally required for them? | BIL-06, BIL-12. Nothing tax-related exists. We will not guess. |
| Q-05 | No | Invoice numbering: the app restarts the sequence every day per branch (`S20261024-00001`). Is that acceptable, or do you need one continuous series per branch (or per tenant)? | BIL-04 "sequential, gap-free". Today's scheme is gap-free per day. |
| Q-06 | No | Profit/margin (RPT-07): what cost basis? Last purchase rate, weighted average per KG, or per physical cylinder's purchase cost? | No cost data is tracked on sales today. |
| Q-07 | ~~Yes~~ answered | OGRA price: should the system store the notified **maximum** consumer price separately from the shop's **selling** rate, and compare every sale line against it (warn or block per tenant)? Is the cap per KG only, or also per 11.8 / 45.4 kg cylinder? | BIL-01 is DIVERGENT (rates are selling prices). Drives BIL-03 and RPT-05. |
| Q-08 | No | Mobile stack: the repo already contains Flutter skeletons (`mobile/`). Confirm Flutter for MOB-07, and confirm the team skills (PHP only, or also Dart/JS)? | MOB-06/07 sizing. |
| Q-09 | No | Which thermal printers and widths do pilots use (58 mm, 80 mm, both)? Must receipts print Urdu on the thermal printer itself (needs image or Unicode-capable rendering)? | BIL-05, LOC-03. Today: 80 mm and A4 HTML print only. |
| Q-10 | No | Payment methods: split `online` into bank transfer, JazzCash and Easypaisa? How should existing `online` records be migrated (keep as "online/other")? | BIL-08. |
| Q-11 | No | Roles: should roles become **per tenant** (each distributor edits its own), or stay a fixed platform-wide set (owner, manager, cashier, driver, accountant)? Is `PAK_OPS` / user `pak` (migration 030) specific to one customer, and should it be removed from the generic product? | PLT-06, S-03. |
| Q-12 | No | Hosting for the first year: single VPS or cloud (region), backup destination, and whether backups must be encrypted? | PLT-09, NFR-05. |
| Q-13 | No | Keep or drop the extra features listed in AUDIT.md §5, in particular the destructive transactional-data refresh tool and the in-app database restore, for multi-tenant SaaS? In a shared database these act on **all** tenants. | Safety of SaaS operations. |
| Q-14 | No | Cylinder sizes: the seed has 45 kg and 45.2 kg but not 45.4 kg. Which commercial size(s) should be default? | CORE-02. |

## Answers found in the code (LPGSASS §8)

1. **Versions:** CodeIgniter 4.7.4, PHP ≥ 8.2 (tested here on 8.5), MySQL 8 (MariaDB also supported by scripts).
2. **Single-customer or shared DB:** one shared database already modelling many businesses and branches (`businesses` → `locations`). The real data volume is unknown (Q-02).
3. **Live in production:** unknown from code (Q-02).
4. **Tax:** not implemented (Q-04).
5. **Urdu:** not in this repo. Present in the out-of-repo install (Q-01). Receipts support 80 mm and A4 (Q-09).
6. **Mobile skills:** Flutter skeletons exist (Q-08).
7. **Extra functionality:** listed in AUDIT.md §5 (Q-13).
8. **Hosting:** docs mention Railway and a Windows installer (Q-12).
