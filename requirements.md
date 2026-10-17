# Perfect LPG POS & ERP — Requirements

## Purpose
Production-ready POS + ERP for LPG retail operations.

## Stack
- PHP 8.x / CodeIgniter 4
- MySQL 8.0+
- Bootstrap 5.3
- HTML5/CSS3
- JavaScript/jQuery/AJAX
- DataTables
- InnoDB transactions and foreign keys

## Modules
1. Authentication and roles
2. Item/cylinder/rate setup
3. Customer and supplier directory
4. LPG POS
5. Purchases
6. Inventory
7. Customer/supplier ledgers
8. Counter Cash
9. Expenses
10. Reports
11. Audit logging

## Sales Use Cases

The POS provides exactly five business transaction types:

1. **Sell Gas Only** — customer brings their own cylinder. Gas stock is reduced by the entered KG. The user selects the filled-cylinder type used as the gas source. If the sale fully consumes a physical filled cylinder, that exact physical cylinder is moved to empty stock; if only part is consumed, the cylinder remains filled with its remaining actual gas weight.
2. **Sell Gas by Replacing Same-Capacity Cylinder** — customer returns an empty cylinder and receives one or more filled cylinders of the same type. Gas is charged by actual gas weight; filled-cylinder stock decreases and returned empty-cylinder stock increases.
3. **Sell Filled Cylinder with Gas + Cylinder Price** — customer buys a filled cylinder. Gas is charged by actual gas weight and the cylinder price is charged separately. Gas stock and filled-cylinder stock decrease.
4. **Sell Filled Cylinder with Gas + Replace Different-Capacity Cylinder** — same as type 3, except the returned empty cylinder may be a different cylinder type/capacity. Gas stock, sold filled-cylinder stock and the received empty-cylinder stock are updated.
5. **Sell Empty Cylinder Only** — only an empty physical cylinder is sold. Gas stock is not affected; empty-cylinder stock decreases.

The branch administrator sets the default POS transaction type in **Shop Settings**. The POS uses that branch default for the first new sale line.

## Shop / Branch Configuration

Shop Settings is branch-level and provides tabs for:
- General branch identity/contact details.
- POS default transaction type and default payment mode.
- Sale stock-validation control and stock-override permission.
- Cash/payment defaults.
- Receipt title, footer and address display.
- Database backup URL/endpoint and maintenance notes.

Cylinder-type inventory policy overrides remain available under Inventory Controls.

## Critical Rules
- Rate history stores effective rate, timestamp, old/new values and user.
- Every sale line stores the actual applied rate.
- Custom-rate lines are explicitly flagged.
- Walk-in customers are cash-only. Named customers are required when a cylinder is returned in transaction types 2 or 4; walk-in sales may still use type 1, 3 or 5.
- Credit limit is checked before posting a credit sale.
- Sales screen displays Previous OS + Current Credit = New OS.
- Payment modes: cash, cheque, online, credit.
- Counter Cash includes actual cash only.
- Posted transactions are reversed/voided rather than hard-deleted.
- Financial amounts use DECIMAL.
- Inventory and cash posting is atomic inside a DB transaction.

## Reports
- Customer ledger
- Supplier ledger
- Daily transactions
- Custom-rate sales
- Daily stock
- Counter Cash reconciliation
- Daily expenses
- Outstanding balances
- Payment-mode analysis

## SQA-Confirmed Inventory Control & Wastage Requirements
- Stock validation must be configurable at shop/location level, with optional cylinder-type overrides.
- When stock validation is ON, gas sales must not exceed available gas stock.
- When stock validation is OFF, a gas sale that exceeds available stock requires an explicit user confirmation and the override must be traceable.
- The system must provide a separate gas-wastage report with date-range and cylinder-type filtering. Wastage reduces the actual gas weight on the affected physical filled cylinder.
- A filled physical cylinder can be converted to empty stock through an authorized inventory wastage/leakage adjustment. The gas lost is deducted from gas inventory and recorded with physical cylinder, user, date and reason.
- Wastage policy must support percentage or fixed-KG configuration, at shop level with cylinder-type override capability.
- Residual gas/wastage from a cylinder that is made empty must never exceed the cylinder's current actual gas weight.
- Inventory control and wastage actions must be atomic and auditable.

## Deferred Business Decisions

The following items are intentionally outside the currently implemented functional scope and must be confirmed before adding them:
- GST/tax
- Sale returns/refunds
- Offline POS
- 80mm thermal printer integration
- Multiple cash registers / handover workflows beyond the current foundation
- Cylinder serial-number policy
- Manager override workflows for credit limits
- Further pricing rules beyond the current gas/kg + cylinder-price model

## Open Business Decisions
1. Can one sale contain multiple scenarios?
2. Are cylinder serial numbers required?
3. Can managers override credit limits?
4. Are cheque payments excluded from Counter Cash?
5. Single shop or multi-location?
6. Is GST/tax required?
7. Are discounts allowed?
8. Are sale returns/refunds required?
9. 80mm thermal receipt printer?
10. Offline POS required?
11. Are additional taxes/fees required on gas-only or cylinder sales?
12. Are multiple cash registers required?

## Completion Rule
A requirement is marked Done only after implementation, validation, transaction testing, UI testing, relevant reporting and tracker update.

## Master Data UI Quality Requirements
- Edit actions for master-data records must open the correct edit form and preload the selected record's current values.
- Saving an edit must persist the changed values and retain unchanged values.
- Editing a user must retain the existing password when the password field is left blank.
- Automated or manual regression testing should verify create/edit flows after UI changes to prevent modal or form JavaScript regressions.


## SQA-Confirmed POS / Inventory Requirements
- Filled-cylinder POS selection must show the currently available filled-cylinder quantity for the selected cylinder type and a clear gas-stock figure without misleading the user about whether gas is allocated to individual cylinders.
- The current inventory model does NOT maintain an independent gas KG stock bucket. Available gas is derived from the actual gas weight of company-owned filled physical cylinders. Each filled cylinder unit stores its actual gas weight and status, allowing partial fills and actual-weight deductions.
- The system must support partially filled cylinders: a cylinder type defines maximum capacity, while each filled cylinder may contain an actual gas weight from 0 up to that capacity. Inventory must prevent actual gas weight above cylinder capacity and account for actual weight when selling, refilling and exchanging cylinders.
- POS transaction types must use user-friendly names and display a short operational hint explaining what the selected transaction will do.
- The active Counter Cash session status should be prominent at the top-left of the POS screen.
- Counter Cash must provide a searchable/filterable history view by date range and counter/register, with transaction type, direction, amount, reference and notes visible.
- Counter Cash expected cash must not double-count the opening float: when an opening_float cash transaction exists, expected cash is calculated from cash-in minus cash-out for the session.


## SQA-Confirmed POS Display Requirements
- The POS transaction hint/help box is not required and should not occupy space on the Sales page.
- POS font size must be configurable from Shop Settings at branch level.
- POS font size must apply only to the POS/Sales page; other application pages must retain their normal font sizing.
- POS font size must be validated to a safe configurable range and persist across POS page reloads.


## Global Application Appearance Requirements
- Shop Settings must provide a configurable application theme: Light or Dark.
- Shop Settings must provide a configurable global application font size.
- Shop Settings must provide a configurable global font style from the supported font list.
- Shop Settings must provide configurable primary and accent colors.
- Appearance settings are branch-level and apply consistently across the whole authenticated application through the shared layout.
- Appearance settings must not be POS-only; all application pages use the same configured appearance.
- Appearance values must be validated against supported themes, fonts, safe font-size bounds and six-digit hexadecimal colors.


## Business Hierarchy Screen (owner request, 2026-10-16)
- A Business Hierarchy screen shows the whole tenant structure as a tree: business (tenant), then its Head Office users, then its shops (branches), and under each shop the staff assigned to it.
- Every node can be expanded and collapsed, with "expand all" and "collapse all" controls, and the tree can be searched by business, shop, user name, username or code.
- Selecting a node shows its details in one place without leaving the screen:
  - Business: contact details, number of shops and users, Head Office users, shop list with setup status.
  - Shop: code, contact details, active/inactive status, setup readiness (settings, cash register, gas rate, stock, staff), live position (gas KG and filled/empty cylinders in stock, cash session open or closed, today's sales), staff with role and default-branch flag, and shortcuts to switch into the shop and to its settings.
  - User: name, username, role, active status, access type (Head Office or shop staff), default branch and last sign-in.
- Users with shop administration rights can add a new shop to a business directly from the tree. It uses the same validation and default provisioning as Businesses & Shops (shop settings, main counter, inventory policy).
- The screen shows only the businesses and shops the signed-in user is allowed to access, and it is available to users who can view business/shop administration.
- The interface must be clean and professional: a two-pane layout (tree on the left, details on the right) on desktop, stacking on phones, full keyboard support and dark-mode support.

## Rates and Customer Pricing (owner request, 2026-10-17)

### Rate types
| Rate | Meaning | Used for |
|---|---|---|
| **Gas rate per KG** | Price of 1 KG of gas. | Gas Sale / Refill by KG or amount; the fallback for every gas charge. |
| **Filled cylinder rate** (new, per cylinder type) | Price of the gas in one *full* cylinder of that type (gas only, not the cylinder shell). | Issue Cylinder (Security Deposit) gas charge; gas portion of a filled Cylinder Sale; filled-cylinder products in customer orders. Gas Sale / Refill is always priced per KG. |
| **Empty cylinder price** (per cylinder type) | Price of the cylinder shell itself. | Cylinder Sale (empty, and the shell part of a filled cylinder); Issue Cylinder only when the business charges for the cylinder (see settings). |

All rates are effective-dated (a new rate applies from its effective time; earlier sales keep the rate they were sold at) and every change is logged with old/new value and user.

### Customer-specific rates
- Any of the three rates can be set for an individual customer (gas per KG, filled cylinder rate per type, empty cylinder price per type), effective-dated, at the customer's branch. A customer rate can be ended; history is kept.
- Customer rates are managed on the Rates screen (Customer rates) and shown on the customer's ledger.

### Which rate applies (most specific first)
1. The customer's own rate for that rate type (and cylinder type).
2. The branch rate.
3. The business-wide/global rate.
4. **Filled cylinder rate fallback:** if no filled cylinder rate is configured at any level, the gas is priced as *gas rate per KG × gas weight* (today's behaviour). If the customer has a special *gas rate per KG* but no special filled cylinder rate, the customer's per-KG rate × capacity is used before any generic filled cylinder rate (a customer's special price always beats a generic one).
5. Walk-in sales use rules 2–4.

### Partially filled cylinders
- A cylinder is charged in proportion to the gas it actually holds: **amount = filled cylinder rate × gas KG ÷ cylinder capacity KG.** A full cylinder costs exactly the filled cylinder rate.
- The line keeps the per-cylinder rate (shown to the user) and the equivalent rate per KG (rate ÷ capacity), so a later Cylinder Return values returned gas consistently.

### Issue Cylinder (Security Deposit) on POS
- Only the gas is sold; the cylinder stays the shop's property on deposit. The gas is priced with the **filled cylinder rate** (pro-rata for partly filled cylinders), not the per-KG rate.
- Empty cylinders issued carry no gas charge.
- The cashier may change the rate per cylinder on the issue line; the edited rate is used for that transaction only and the line is flagged as a custom rate.

### Cashier overrides
- Every POS rate field starts at the applicable rate (rules above) and remains editable; edited rates are flagged as custom rate lines (existing rule).

### Business settings (configurable per business, defaults shown)
- `filled_cylinder_pricing` — **Per cylinder** (use filled cylinder rates when configured, else per KG) / **Per KG** (always gas KG × rate per KG).
- `issue_cylinder_charge` — **Gas only** (cylinder on deposit, not charged) / **Gas and cylinder price** (also charge the empty cylinder price, the previous behaviour).
