# Perfect LPG POS & ERP

A branch-aware LPG retail POS/ERP built with CodeIgniter 4, PHP 8.2+ and MySQL 8+.

## Core Modules

- Authentication and role-based access
- Customers and supplier ledgers
- Cylinder types and LPG pricing
- Physical cylinder inventory with actual gas weight
- Five-mode LPG POS
- Purchases and supplier payments
- Counter Cash
- Expenses
- Inventory controls and gas wastage
- Reports and audit logging
- Branch-level Shop Settings

## POS Transaction Modes

1. **Sell Gas Only** — customer brings their own cylinder; the cashier selects exactly one filled physical source cylinder, gas stock is reduced only from that unit, and the same unit becomes empty when its gas reaches zero.
2. **Sell Gas by Replacing Same-Capacity Cylinder** — customer returns an empty cylinder and receives the same capacity filled cylinder.
3. **Sell Filled Cylinder with Gas + Cylinder Price** — gas is charged by actual gas weight plus the cylinder price.
4. **Sell Filled Cylinder with Gas + Replace Different-Capacity Cylinder** — same as mode 3, with a different empty cylinder type received.
5. **Sell Empty Cylinder Only** — only empty-cylinder inventory is affected.

## Shop Settings

Open **Shop Settings** from the left navigation.

The branch-level settings are organized into tabs:

- General — branch name, address, city, phone
- POS & Sales — default POS transaction mode
- Inventory Control — sale stock validation and stock override policy
- Cash & Payments — default payment mode
- Receipt & Printing — receipt title, footer and branch address
- Backup & Maintenance — database backup URL/endpoint and maintenance notes

The POS no longer contains a separate default-transaction preference control. It reads the branch default from Shop Settings.

## Installation

### Local / manual installation

1. Install PHP 8.2+ with the required extensions (`intl`, `mbstring`, `mysqli`, and `zip`), Composer, and MySQL 8+.
2. Copy the repository's `env` example file to `.env` and configure the APP and DATABASE values. Never commit `.env` or real credentials.
3. Run `composer install`.
4. For a manual schema installation, select the intended empty database and run `database/schema.sql` against it. Then apply the required numbered, non-destructive migrations that are not already applied, in ascending order.
5. Point Apache/Nginx to the project's `public` directory and open the application URL.

### Environment variables and flags

The repository's `env` file documents each configuration block. Important values:

| Variable | Purpose / instructions |
| --- | --- |
| `CI_ENVIRONMENT` | Use `development` locally for detailed diagnostics; set to `production` on public deployments. |
| `app.baseURL` | The full public application URL, including a trailing slash. Use your Railway domain in production. |
| `database.default.hostname` | MySQL server hostname for a standard CodeIgniter environment configuration. |
| `database.default.database` | Name of the database the application should use. |
| `database.default.username` / `database.default.password` | Database login credentials. Use a restricted account and never commit production credentials. |
| `database.default.DBDriver` / `database.default.port` | Keep `MySQLi` for MySQL; use the port supplied by your database host (usually 3306). |
| `LPG_DB_HOST`, `LPG_DB_NAME`, `LPG_DB_USER`, `LPG_DB_PASS`, `LPG_DB_PORT` | Alternative explicit database variables supported by the app and convenient for Railway's Variables panel. Set the values supplied by your database provider. |
| `LPG_DB_DRIVER` | Optional driver override; normally `MySQLi`. |
| `encryption.key` | Recommended in production: a unique, random secret. Keep it stable and out of source control. |
| `app.companyName` | Optional company name shown on the login page and browser title. |
| `logger.threshold` | Controls log verbosity. Use a production-appropriate level; do not expose debug details to visitors. |
| `backup.directory` | Optional location for local SQL backup files. Local files on ephemeral hosting may not survive redeployments. |
| `FRESH_DATABASE_SETUP` | Installer feature flag; details below. |

### First-time database setup flag

- **Default: `FRESH_DATABASE_SETUP = false`.** Normal login and normal application use; the installer is not enabled.
- **Temporarily set `FRESH_DATABASE_SETUP = true` only for a controlled first deployment.** If setup is incomplete, login redirects to Installation Setup. The installer creates the configured database if missing, applies `database/schema.sql`, then runs pending numbered migrations in order. The separate destructive transactional-data refresh migration is not run automatically.
- **Existing populated database warning:** replacement deletes the configured database's tables and records. It requires verification of an active ADMIN user's password, typed confirmations, and a successfully created/verified safety backup. The database account also needs the necessary create/drop permissions.
- After installation, verify that the application and administrator login work, then **immediately set `FRESH_DATABASE_SETUP = false`**.
- Do not enable this flag as a routine way to refresh transactional data. Use the separately permission-controlled Transactional Data Refresh feature for that purpose.

See [First-Time Database Installation](docs/first-install-setup.md) for the full procedure and [Railway Deployment](docs/railway-deployment.md) for Railway-specific setup.

## Important Environment Rule

Do not commit a local `.env` file (or any variant such as `..env`; `.gitignore` blocks them). Copy the committed `env` template to `.env` for local configuration.

## Shop Settings Migration

The branch settings table is intentionally delivered as a non-destructive migration so existing installations are not rebuilt.

Migration: `database/migrations/001_20260930_shop_settings.sql`

Permissions migration: `database/migrations/009_20261003_permissions_sync.sql`

It adds missing standard permissions and backfills the default role mappings so Reports (`REPORT_VIEW`) and Shop Settings access are available to the standard roles according to the application's permission model. Custom role permissions are not removed.

It creates one settings record per branch and synchronizes the branch-wide stock-validation flag with the existing inventory policy.

## Database Deployment Migrations

All manual SQL deployment scripts use a numeric sequence prefix. Run only migrations that are not already applied to the target database, in this order:

1. `001_20260930_shop_settings.sql`
2. `002_20260930_inventory_controls.sql`
3. `003_20260930_per_cylinder_inventory.sql`
4. `004_20261002_credit_limit_validation.sql`
5. `005_20261002_opening_inventory_comments.sql`
6. `006_20261002_security_deposits.sql`
7. `007_20261003_pos_transaction_type.sql`
8. `008_20261003_individual_cylinder_tracking.sql`
9. `009_20261003_permissions_sync.sql`
10. `010_20261004_purchase_inventory_integrity.sql`
11. `011_20261003_refresh_transactional_data.sql` — **manual staging/test-data refresh only; never run automatically in production**
12. `012_20261004_physical_cylinder_code.sql` — physical cylinder code migration
13. `013_20261004_customer_credit_sale_control.sql` — per-customer credit-sale permission
14. `014_20261004_pos_source_filled_cylinder_selection.sql` — POS source-cylinder selection setting
15. `015_20261004_pos_os_receipt_snapshot.sql` — POS OS/receipt snapshot
16. `016_20261004_empty_cylinder_sale_price.sql` — configurable empty-cylinder sale price
17. `017_20261004_cylinder_sale_rate_components.sql` — cylinder-sale rate components
18. `018_20261005_purchase_void_authorization.sql` — purchase void authorization
19. `019_20261005_stock_adjustment_history.sql` — stock adjustment transaction history

See `database/migrations/README.md` for the deployment procedure and safety rules.

## QA / Testing

- `TESTING.md` — development status and release gate.
- `SQA.md` — detailed execution matrix with expected/actual/status fields.
- No browser execution is falsely marked PASS unless it has been executed in the user's local test environment.

## POS Customer Credit Business Rules

These rules are mandatory and are enforced in both the POS UI and SalesService server-side posting logic:

1. **Walk-in / Cash customer:** Credit sale is never allowed. A walk-in sale must be fully paid at posting time; **received amount must equal the sale total**. Walk-in payments are cash only.
2. **Actual customer:** Credit sale is allowed only when **Allow Credit Sale** is enabled on that customer's record.
3. **Credit limit:** For an actual customer allowed to buy on credit, the resulting customer outstanding balance must never exceed that customer's configured **Credit Limit**. A zero credit limit therefore permits no credit.
4. **Server-side enforcement:** UI restrictions are not trusted by themselves; direct POST requests are validated by the sales service as well.
5. **Customer configuration:** The Customers / Parties screen shows the credit-sale status and provides the **Allow Credit Sale** option alongside the customer's Credit Limit.


## POS Source Filled Cylinder Selection

Shop Settings → POS & Sales includes **Allow user to select the source filled cylinder on POS**.

- **ON:** Gas Sale / Refill shows the Source Filled Cylinder dropdown. One source physical cylinder can be used on only one POS line, the entered KG cannot exceed that cylinder's available gas, and stock is deducted only from the selected cylinder.
- **OFF:** The source dropdown is hidden. The system automatically allocates gas from filled physical cylinders in ascending global sequence (cylinder unit ID), consuming one cylinder's available quantity before moving to the next.
- The same cylinder type cannot appear on multiple gas-sale lines. Available gas stock is shown in the Cylinder Type dropdown as the sum of gas across its filled physical cylinders.
- Server-side validation and transaction locking enforce the same rules and prevent concurrent overselling.

Migration: `database/migrations/014_20261004_pos_source_filled_cylinder_selection.sql`.


## Recently Applied Business Rules — October 2026

This is the consolidated business-rule reference for the recent LPG/POS changes. These rules are part of application behavior and must be enforced by both UI and server-side transaction services where applicable.

### 1. Customer and Credit-Sale Rules

- A **credit sale** is a sale where the customer does not pay the complete sale amount. The unpaid portion increases the customer's **Outstanding (OS)** balance.
- **New Credit = Sale Total - Total Amount Received**. A fully paid sale creates no new credit, even if the customer already has a previous OS balance.
- **Projected Customer OS = Existing Customer OS + New Credit - any amount of the current payment allocated to settle the previous OS**.
- Walk-in / Cash customers cannot create OS. Their sale must be fully paid and walk-in payments are cash-only.
- Each actual customer has an **Allow Credit Sale** flag. It is OFF by default. When OFF, any sale that would create additional OS is rejected with: **"Credit sale is not allowed for this customer. Enable Allow Credit Sale on the customer record."**
- Shop Settings > **Credit Limit Validation** controls the limit policy:
  - **None — no credit limit validation:** credit sales are allowed without a limit when Allow Credit Sale is ON.
  - **Customer Level:** use the selected customer's Credit Limit and validate the resulting customer OS.
  - **Shop Level:** use one branch/shop-wide Credit Limit and validate the resulting overall positive customer OS; individual customer Credit Limit values are ignored.
- Existing OS is always considered when validating a new credit sale. The system validates the **projected OS**, not only the new unpaid amount.
- Under Customer Level, the customer cannot exceed their configured Credit Limit.
- Under Shop Level, the shop cannot exceed its configured Shop Credit Limit. Positive OS balances are aggregated across customers; one customer's negative balance does not offset another customer's positive OS.
- When a customer is selected on POS, the screen shows whether credit sale is allowed and displays the applicable limit, current OS and available credit where a limit applies.
- Server-side validation is authoritative and is performed inside the posting transaction using the latest database values.
- Shop-level credit-limit sales use a shop-wide database lock so concurrent credit sales cannot collectively exceed the configured shop limit.
- Customer-level validation locks the selected customer row before calculating the latest OS.
- Credit-limit values of zero mean no additional credit is available in the corresponding limited mode.
- Customer credit permission and credit-limit rules apply only when the sale actually creates additional OS; a fully paid sale is not treated as a new credit sale.

### 2. POS Source Filled-Cylinder Rules

**Setting ON:**
- Gas Sale / Refill shows a Source Filled Cylinder selector per gas line.
- Selected source must be a filled physical cylinder at the current location and match the selected cylinder type.
- Same physical source cylinder cannot be selected on more than one line.
- Gas quantity cannot exceed actual gas weight in the selected source cylinder.
- Only the selected source cylinder is consumed; when it reaches zero KG it becomes empty.

**Setting OFF:**
- Source selector is hidden.
- System automatically allocates from filled physical cylinders of the selected type.
- Allocation follows ascending global physical-cylinder sequence (`cylinder_units.id`).
- One cylinder is consumed before moving to the next.
- Requested KG cannot exceed total actual gas available for that cylinder type.

**Both modes:**
- Same cylinder type cannot appear on multiple gas-sale lines.
- Available gas for a type is the sum of actual gas on its filled physical cylinders.
- Transaction locking prevents concurrent overselling.

### 3. Physical Cylinder Rules

- Every physical cylinder has an individual `cylinder_units` record.
- Code format is **`<CYLINDER_TYPE_CODE>-<GLOBAL_SEQUENCE>`**, for example `C11_8-000123`.
- Global sequence is the `cylinder_units.id` AUTO_INCREMENT value and is not reset per type/location.
- Existing IDs and inventory relationships are preserved during code migration.
- Filled cylinder actual gas must be greater than zero and cannot exceed capacity.
- Empty cylinder gas weight is always zero.
- Reaching zero KG changes a filled unit to empty transactionally.

### 4. Gas Inventory Rules

- **Available Gas KG = sum of actual gas weight on filled physical cylinders** at the current location.
- Gas consumption changes physical-cylinder gas weight; it does not use an unrelated bulk gas bucket.
- Partial sales consume only the requested KG.
- Stock validation uses the physical-cylinder-derived balance.
- If stock validation is disabled, over-stock posting requires explicit override confirmation and branch **Allow Stock Override** permission.
- If stock validation is enabled, insufficient gas stock blocks posting.
- Inventory locking/transaction controls prevent concurrent overselling.

### 5. Cylinder Sale and Replacement Rules

- Physical-cylinder quantities must be whole numbers.
- Filled-cylinder sale charges **actual gas KG × gas/kg rate + cylinder quantity × cylinder rate**.
- Actual gas weight, not nominal capacity, is used for gas pricing.
- Same-capacity replacement requires a named customer when an empty cylinder is returned.
- Different-capacity replacement requires a named customer and a received type different from the sold type.
- Empty-cylinder-only sale consumes empty physical-cylinder inventory and does not consume gas.
- Filled/empty availability is revalidated during posting.

### 6. Customer Custody and Security Deposit Rules

- Security Deposit / Issue Cylinder requires a named customer.
- Selected company physical cylinders move to customer custody while remaining individually tracked.
- A cylinder already on active custody cannot be issued again.
- Security deposit is tracked separately from ordinary sales revenue/customer OS.
- Only the customer holding a custody record can return that cylinder.
- A custody cylinder must be empty before return/refund.
- Returned cylinder becomes an empty company cylinder and its recorded deposit is refundable according to the custody ledger rules.
- Gas added to a custody cylinder cannot make it exceed capacity and must target a cylinder belonging to the selected customer.

### 7. Purchase Inventory Integrity Rules

- Filled-cylinder purchase lines retain **actual gas weight per line**.
- Actual gas weight is validated against cylinder capacity.
- Filled cylinders cannot be created with zero/negative actual gas.
- Empty cylinders have zero gas weight.
- Filled-cylinder purchases create individual physical-cylinder records.
- Historical rows are not silently rewritten when the actual-gas field is introduced; unavailable historical values remain at migration default.
- Purchase inventory and supplier/payment posting must be atomic.

### 8. Transaction and Integrity Rules

- POS uses one header transaction type per invoice.
- Financial totals are recalculated server-side.
- Payment total must equal the recalculated payable amount unless the transaction type has a specific settlement rule.
- Invalid/inactive customers and cylinder types cannot be used.
- Inventory availability is revalidated inside posting.
- Financial and inventory changes commit atomically; failures roll back.
- Sale voids reverse related inventory/cash/customer-ledger effects without duplicate reversal.
- Audit history remains after voids.

### 9. Recently Applied POS UI Rules

- POS left panel uses reduced vertical padding for a more compact working area.
- **Current Sale**, **Previous Balance**, and **Discount** are displayed on one row on desktop.
- Unnecessary explanatory/help text was removed from the main workflow.
- **Security Deposit Amount** is hidden unless the selected transaction type is **Security Deposit / Issue Cylinder**.
- These UI changes do not replace server-side validation.

### 10. Gas Entry Mode — Screen-Level KG / Rs. Rule

- On **Gas Sale / Refill**, the cashier selects one screen-level **Gas Entry: KG / Rs.** mode. The selection applies to all gas-sale lines on that invoice; there is no KG/Rs selector per line.
- **KG mode:** the cashier enters gas quantity in KG and the system calculates the line amount using the applicable gas/kg rate.
- **Rs. mode:** the cashier enters the sale amount and the system automatically calculates **Gas KG = entered amount ÷ current effective gas/kg rate**.
- The server uses the current effective gas/kg rate for amount-entry mode; the submitted quantity/rate cannot override this calculation.
- Stock validation remains fully active: the calculated KG must still pass the selected source-cylinder or total cylinder-type stock validation.
- Manual source mode still limits the calculated KG to the selected source filled cylinder's actual gas.
- Automatic source mode still validates the calculated KG against total available gas and consumes physical cylinders in the existing sequence.
- In Rs. mode, gas rate is controlled by the current effective server-side rate and is not manually overridden by the cashier.
- Changing between KG and Rs. converts the existing gas-line values using the current displayed rate so the transaction remains consistent.
- Rs. mode does not disable, reduce, or bypass inventory locking, stock validation, customer credit rules, payment rules, or transaction atomicity.
- The calculated KG and final line total are stored/posting values; client-side calculations are only convenience behavior.

### 11. Recent Migration Rules

- `010_20261004_purchase_inventory_integrity.sql` — actual gas weight for purchase lines.
- `012_20261004_physical_cylinder_code.sql` — standardized physical-cylinder codes.
- `013_20261004_customer_credit_sale_control.sql` — per-customer credit-sale permission.
- `014_20261004_pos_source_filled_cylinder_selection.sql` — POS source-cylinder selection setting.
- Run only migrations not already applied, in sequence.

**Rule precedence:** Server-side transactional validation is authoritative. UI restrictions, displayed availability and client-side checks must never be the only enforcement mechanism.

## Cylinder Sale — October 2026

- **Cylinder Sale** supports empty, filled/partially-filled, or mixed cylinder sales.
- Empty-cylinder sales reduce only empty physical-cylinder stock; gas stock is unchanged.
- Empty-cylinder price is configured per cylinder type and can be overridden at sale time.
- Filled-cylinder sales require explicit physical-cylinder checkbox selection.
- Gas KG and cylinder quantity for filled-cylinder sales are **read-only** and derived from the selected physical cylinders.
- Gas rate and cylinder price remain editable for filled-cylinder sales.
- Partially-filled cylinders use their actual current gas quantity.
- Empty + filled cylinder lines can be posted together.
- Server-side posting locks and revalidates the selected physical cylinders.
- Existing **Gas Sale / Refill** behavior is unchanged.
- Migration `016_20261004_empty_cylinder_sale_price.sql` adds the configurable empty-cylinder sale price.


## Stock Adjustment History

Stock adjustments are recorded as dedicated transactions with references such as `ADJ-000123`. The adjustment header stores the branch, item, scope, direction, quantity, reason, user, and before/after stock state. All related `inventory_movements` rows point to the adjustment reference, so a multi-row physical-cylinder change can be reviewed as one transaction.

The Stock Adjustment screen provides guided item/scope selection, current-stock preview, required adjustment reason, date/type/direction filters, search, and an adjustment detail page showing before/after state and all linked inventory movements. Historical movements remain append-only; corrections should be posted as new adjustments rather than editing old movement history.
