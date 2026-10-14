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
