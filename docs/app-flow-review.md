# Whole-application flow review — 2026-10-16

Review of the web back office after Phases 8–11, done at the owner's request ("review the whole app flow and update where is deficiency; screens should be user friendly, not messy").

**Method**
- Walked through every menu screen at 1440 px and 390 px using the shell check's screenshots.
- Ran every committed suite against the disposable QA database: Playwright, the six business-logic suites, the six screen checks and the accessibility audit.
- Each deficiency below is either fixed (with its evidence ID in `SQA.md`) or listed as still open, with the reason.

## 1. Main flows checked

| Flow | Path through the app | Result |
|---|---|---|
| Start of day | Login → branch switcher → Counter Cash (open session) → Dashboard alerts | Works; Dashboard warns when no cash session is open |
| Counter sale | POS (all four transaction types) → receipt (80 mm / A4) → Sale History → void with reason | Works; F2/F4/F7/F9 shortcuts; overpayment warned before posting |
| Customer credit | POS credit sale → customer OS → Customer Receipts → Ledger statement | One balance rule everywhere; statement closes on the current balance |
| Stock in | Opening Inventory → Purchases (credit / part-paid) → Supplier Payments → purchase void | Payable, stock and cash reconcile (`verify_business_flow`) |
| Stock control | Inventory → Stock Adjustment → Gas Wastage (partial/full) → Branch Stock Transfers | Over-consumption refused; transfers reconcile at both branches |
| End of day | Counter Cash count and close → Cash History → Reports → Audit Log | Over/short preview; closed sessions are locked |
| Head office | Business Hierarchy → add shop → Shop Settings checklist → Users branch access → Branch Overview | Setup checklist names what a new branch is missing |
| Administration | Users & Roles, Central Configuration, Database Backup & Restore | Backup/restore drill passes on both engines |

## 2. Deficiencies found and fixed

| # | Screen / area | Deficiency | Fix | Evidence |
|---|---|---|---|---|
| 1 | Dashboard, POS, Receipts | Shop outstanding counted customers of every branch and business | One balance service (`CustomerBalanceService`) used everywhere | P11-22…P11-24 |
| 2 | 8 screens | Every success/error message appeared twice | Per-view copies removed | P11-27 |
| 3 | Reports | Broken markup; deposit tile said "today" for a date range | Rebuilt | P11-29 |
| 4 | Sale History | Void button said "Delete"; dates in UTC dropped today before 05:00; stale searches overwrote newer ones | "Void" dialog with required reason; local dates; stale responses ignored | P11-15…P11-18 |
| 5 | Counter Cash | Opening float counted twice | Shown separately | P11-19 |
| 6 | Customer Ledger | Amount owed shown in green | Colours fixed; Statement tab added | P11-25 |
| 7 | Customers / Suppliers | A failed save lost everything typed | Form reopens with the typed values | P11-24 |
| 8 | Expenses | Table overflowed on phones | Rebuilt | P11-30 |
| 9 | All lists | DataTables re-sorted server order oldest-first | Server order kept until the user sorts | P11-21 |
| 10 | 13 forms | Browser `confirm()` pop-ups | Shared confirm dialog | P11-28 |
| 11 | Whole app | 6 WCAG 2.1 AA rule failures (contrast, unlabelled fields, invalid ARIA) | 0 failures in light and dark mode | P11-32 |
| 12 | Document numbers | Sale/receipt/purchase numbers could collide under parallel posting | Atomic per-branch counters (`DocumentNumberService`) | P10-01…P10-02 |
| 13 | Inventory Controls | Repeated the whole Gas Wastage screen; override rows had no headings and did not say whether a type used the shop default | Configuration only: policy table with "Shop default / Custom", Customise and "Use shop default"; link to Gas Wastage | P11-33 |
| 14 | Gas Wastage | Button and message said every wastage empties the cylinder (partial wastage only reduces gas) | "Record Wastage"; message states the gas left or that the cylinder is now empty | P11-33 |
| 15 | Inventory Controls (save) | Posting without a cylinder type created a policy for type 0 instead of the shop default | A missing type means the shop default | P11-33 |
| 16 | Businesses & Shops | "Create business" form always open above the shops | Collapsed (opens when there is no business or after a failed save) | P11-34 |
| 17 | Users & Roles | Every role's 31-permission grid fully expanded (very long page) | Grids collapsed behind "Show permissions"; Select/Clear/Save stay in the header | P11-34 |
| 18a | Branch Overview, Business Hierarchy, document numbers | Database errors on MySQL 8 (reserved words `empty`, `last_value`); MariaDB accepted them, so local testing missed it | Renamed; CI now runs every suite and a crawl of every screen on MySQL 8 | P10-04, P12-04 |
| 18b | Suppliers, Purchases, Supplier Payments | A shared supplier's opening balance was counted at every branch; three copies of the payable formula | Owner decision D9-4: business setting for separate or single balance, openings per branch, one `SupplierBalanceService` | P12-05 |
| 18c | POS, Purchases, customer orders | POS rounded only the invoice total while customer orders rounded each line | Owner decision D10-2: one business rounding setting applied everywhere (`MoneyRounding`) | P12-05 |
| 18 | Purchases | Only screen without a page title; blank band in the settlement card | Standard page header with "Pay a supplier"; spacing fixed | P11-34 |

## 3. Still open

| Item | Why it is open | Owner |
|---|---|---|
| 8.1.2: rotate the database password that was committed earlier | The repository is public; only the owner can rotate the hosting credentials | Owner |
| 12.3 two-branch pilot, 12.5 sign-off | Needs the owner and real branch staff | Owner |
| Purchases stock-line table scrolls sideways on phones | It is a 7-column entry grid; a card layout per line would be a larger redesign. It works and stays inside the card | Optional |
| Shop Settings tabs wrap to two rows at 1440 px | 9 tabs; usable. A vertical tab list would fix it | Optional |
| Customer Ordering Catalog "Add product" form always open | Setup screen used rarely; same collapse pattern as Businesses can be applied | Optional |
| Login page keeps its own styles | Deliberate brand layout; contrast fixed | — |

## 4. How to re-run

```bash
export QA_DB=lpg_qa QA_MYSQL=mysql QA_PHP=php   # disposable database only
bash tests/qa/run_all.sh --a11y                  # everything (serve the app on :8080 against $QA_DB first)
bash tests/qa/run_all.sh --suites-only           # business-logic suites only (what CI runs)
```
