# Perfect LPG — Database Migration Deployment Order

All manual SQL migrations in this directory use the format:

```
NNN_YYYYMMDD_feature_name.sql
```

The **NNN** prefix is the deployment sequence. The date remains in the filename so the feature/change date is still visible.

## Ordered migration list

| Seq | Migration | Purpose |
|---:|---|---|
| 001 | `001_20260930_shop_settings.sql` | Branch-level shop settings |
| 002 | `002_20260930_inventory_controls.sql` | Inventory validation and wastage controls |
| 003 | `003_20260930_per_cylinder_inventory.sql` | Physical cylinder units and per-cylinder inventory support |
| 004 | `004_20261002_credit_limit_validation.sql` | Customer/shop credit-limit configuration |
| 005 | `005_20261002_opening_inventory_comments.sql` | Opening-inventory comments |
| 006 | `006_20261002_security_deposits.sql` | Security deposits, cylinder custody and transaction fields |
| 007 | `007_20261003_pos_transaction_type.sql` | Header-level POS transaction type |
| 008 | `008_20261003_individual_cylinder_tracking.sql` | Individual-cylinder tracking setting |
| 009 | `009_20261003_permissions_sync.sql` | Synchronize standard permissions and role mappings |
| 010 | `010_20261004_purchase_inventory_integrity.sql` | Persist actual gas weight on purchase lines |
| 011 | `011_20261003_refresh_transactional_data.sql` | **Manual staging/test-data reset only** |
| 012 | `012_20261004_physical_cylinder_code.sql` | Global physical-cylinder code format |
| 013 | `013_20261004_customer_credit_sale_control.sql` | Per-customer credit-sale permission; disabled by default |
| 014 | `014_20261004_pos_source_filled_cylinder_selection.sql` | Branch-level POS source-cylinder selection setting |
| 015 | `015_20261004_pos_os_receipt_snapshot.sql` | POS OS / receipt snapshot fields |
| 016 | `016_20261004_empty_cylinder_sale_price.sql` | Cylinder Type empty-cylinder sale price |
| 017 | `017_20261004_cylinder_sale_rate_components.sql` | Cylinder sale gas-rate and cylinder-price components |
| 018 | `018_20261005_purchase_void_authorization.sql` | Purchase void authorization |
| 019 | `019_20261005_stock_adjustment_history.sql` | Stock adjustment history |
| 020 | `020_20261005_pos_transaction_type_visibility.sql` | POS transaction-type visibility |
| 021 | `021_20261005_backup_email_settings.sql` | Database backup email settings |
| 022 | `022_20261006_opening_inventory_excel_import.sql` | Opening inventory Excel import and separate import batches |
| 023 | `023_20261006_rate_card_global_gas_rate.sql` | Allow global gas rate card without a cylinder type |
| 024 | `024_20261006_issue_cylinder_workflow.sql` | Issue cylinder workflow |
| 025 | `025_20261007_independent_deposit_payments.sql` | Classify Security Deposit Receive/Refund payments independently from gas/cylinder sale payments |
| 026 | `026_20261007_backup_email_settings_idempotent.sql` | Safely reconcile backup email SMTP columns for databases where migration 021 was already applied |
| 027 | `027_20261007_payment_allocation_rule.sql` | Configurable allocation rule for combined Security Deposit + Gas/Cylinder payment collection |
| 028 | `028_20261007_sale_history_permission.sql` | Dedicated Sale History permission and default role mappings |
| 029 | `029_20261007_security_deposit_first.sql` | Makes Security Deposit the default allocation target before Gas/Cylinder |
| 030 | `030_20261009_pak_operations_user.sql` | Creates the `pak` account and limited `PAK_OPS` role for dashboard, sales/receipts, inventory, customer ledger, cash, expenses and rates |
| 031 | `031_20261009_default_gas_entry_mode.sql` | Adds the default Gas Entry mode for Gas Sale / Refill |
| 032 | `032_20261014_business_shop_ownership.sql` | Additive business/shop ownership foundations and assignment tables; leaves existing customer/shop ownership unassigned for a separately reviewed upgrade plan |
| 033 | `033_20261014_customer_accounts_orders_and_catalog.sql` | Adds business catalog/shop availability, customer signup and account tables, API token/session storage, order lines/status history and composite ownership constraints |
| 034 | `034_20261014_pending_signup_username_guard.sql` | Enforces uniqueness for pending signup usernames while freeing the username after approval/rejection |
| 035 | `035_20261014_centralized_configuration_storage.sql` | Adds validated application defaults, per-business settings and per-shop overrides with one server-side resolver |
| 036 | `036_20261014_order_idempotency_price_reconfirmation.sql` | Tracks order request fingerprints and whether the customer must reconfirm a revised quote |
| 037 | `037_20261014_sale_delivery_fee_component.sql` | Adds a separate delivery-fee amount to POS sales so accepted mobile orders can be fulfilled without hiding the delivery charge from financial records |
| 038 | `038_20261014_customer_order_sale_idempotency.sql` | Adds a unique nullable customer-order reference to POS sales so fulfillment retries can recover the same sale rather than duplicate it |
| 039 | `039_20261014_order_physical_cylinder_selection.sql` | Persists selected physical cylinders on order lines so actual gas weight can be quoted and reconfirmed before posting |

## Deployment rules

The application now checks this directory on the login page. A pending migration is shown there with an **Execute Pending Migrations** button. Migrations are executed strictly by numeric sequence, one at a time. If one migration fails, execution stops immediately and later migrations remain pending.

Execution results are recorded in `lpg_migration_history`, including status, checksum, start/end time, duration, executed statement count, and the error message for failures. Login is blocked until every migration is successfully applied. An already-applied migration file may not be changed; create a new sequence instead.


1. Run `database/schema.sql` only for a fresh database.
2. For an existing database, run only the migration scripts that have not already been applied.
3. Always execute pending migrations in ascending sequence order: **001 → 002 → ... → 030 → 031 → 032 → 033 → 034 → 035 → 036 → 037 → 038 → 039**.
4. Migration **011** is not a normal upgrade migration. It is a controlled, destructive refresh intended for disposable staging/test databases. It clears transactional records plus customers, suppliers, cylinder types, rate cards, and rate-change history. Users, roles, permissions, locations, shop settings, cash registers, inventory policies, and other application configuration remain. Whenever a later migration introduces a new transactional table, update the controlled refresh service/script so that table is reset too.
5. Do not automatically run migration 011 during production deployment.
6. Do not rename or reorder an already released migration. Add a new sequence number for every future migration.
7. Keep migration files idempotent where practical so a partially completed deployment can be safely retried.

## Fresh database

The current `database/schema.sql` already contains the current application schema. For a fresh database, use the schema first and apply only migrations needed for compatibility/testing of an existing-installation path.

## Existing database

Before deployment, identify the latest sequence already applied and continue from the next pending sequence. The numeric prefix is the authoritative execution order; the date is informational.

## Staging refresh

To intentionally reset transactional/test data, parties (customers and suppliers), cylinder types, and LPG rates/rate history:

```sql
USE perfect_lpg;
SET @lpg_refresh_transactional_data = 1;
SOURCE database/migrations/011_20261003_refresh_transactional_data.sql;
```

After the refresh, re-create customers/suppliers, cylinder types, and rates before resuming normal POS and inventory operations. Never execute the staging refresh against production unless a deliberate full reset of these records is explicitly intended.

## Future migration naming standard

Use:

```
012_YYYYMMDD_feature_name.sql
014_YYYYMMDD_feature_name.sql
015_YYYYMMDD_feature_name.sql
```

Never reuse an existing sequence number.

- `014_20261004_pos_source_filled_cylinder_selection.sql` — adds the branch-level POS setting controlling manual source filled-cylinder selection versus automatic sequential allocation.
- `019_20261005_stock_adjustment_history.sql` — adds dedicated stock-adjustment transaction headers, references, reasons, and before/after snapshots.


## Credit Sale / OS Rules

- A credit sale exists when a sale is not fully paid and therefore increases customer OS.
- Existing customer OS is included when validating a new credit sale.
- Customer Allow Credit Sale is stored in customers.allow_credit_sale and defaults to OFF.
- credit_limit_validation_mode values are none, customer, and shop.
- none means no credit-limit restriction; customer validates the selected customer's projected OS; shop validates projected positive OS across the branch and ignores individual customer credit limits.
- Shop-level validation uses a branch-wide database lock to prevent concurrent credit sales from collectively exceeding the shop limit.
- These rules are enforced server-side; POS display/JavaScript is only an early user-feedback layer.

- `031_20261009_default_gas_entry_mode.sql` — adds the branch-level default Gas Entry option (KG, Amount, or Cylinders) for Gas Sale / Refill.
- `032_20261014_business_shop_ownership.sql` — creates the business parent and explicit business/shop assignment tables. It does not infer ownership for pre-existing locations or customers; a populated-install upgrade requires a separate reviewed mapping/recovery plan.
- `033_20261014_customer_accounts_orders_and_catalog.sql` — adds customer signup/account, API session/token storage, business catalog, shop product availability, and customer order/state-history tables with matching business/shop/customer/account constraints.
- `034_20261014_pending_signup_username_guard.sql` — enforces unique pending signup names through a generated key that automatically clears when the request is approved or rejected.
- `035_20261014_centralized_configuration_storage.sql` — stores application defaults, per-business values and per-shop overrides. `ConfigurationService` is the only supported effective-value resolver; existing `shop_settings` remains intact.
- `036_20261014_order_idempotency_price_reconfirmation.sql` — persists canonical request fingerprints so reused idempotency keys cannot create/confuse orders, and records quote revisions that need customer confirmation.
- `037_20261014_sale_delivery_fee_component.sql` — stores delivery charges separately from item subtotal/discount while still including them in the posted sale total and payment plan.
