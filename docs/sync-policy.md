# Sync policy (BL-030)

How each table behaves when an offline client (the mobile apps, MOB-01…MOB-05) syncs with the
server. `tests/qa/checks/ledger_check.mjs` enforces two things against this file: every table in
the schema is classified below, and ledger tables are only changed at the sites listed under
"Allowed ledger mutations".

## Classes

- **master** — reference data a client caches and may edit. Columns `updated_at`, `deleted_at`
  (soft delete: the row stays, lists and finds skip it) and `version` (the application DB builder
  adds `version = version + 1` to every UPDATE). A client sends the `version` it edited; the
  server applies last-writer-wins and returns the new version. Pull: rows with
  `updated_at > last sync`, including soft-deleted rows so the client removes them.
- **ledger** — business events. Append-only: a correction is a new row (a void, a reversal
  movement, an adjustment), never an edit. Every row carries `created_at`/its event time, and the
  client-created ones a `uuid` (BL-028) so a retried upload is a replay (BL-029), not a duplicate.
- **state** — server-owned current state derived from ledgers (stock units, open sessions,
  order and transfer status, document counters). Clients read it; only services change it.
- **system** — configuration, identity, access and bookkeeping tables. Not synced to devices
  (settings are read through the configuration API).

## Table classification

| Table | Class | Notes |
|---|---|---|
| `customers` | master | soft delete via `CustomerService::delete` / `DELETE /staff/customers/{id}` |
| `suppliers` | master | soft delete via the model |
| `cylinder_types` | master | delete (only when unused) is a soft delete |
| `expense_categories` | master | deactivated, not deleted |
| `rate_cards` | master | rate history in `rate_change_log` |
| `customer_rates` | master | ended by `ended_at`, not deleted |
| `business_catalog_products` | master | |
| `sales` | ledger | |
| `sale_items` | ledger | |
| `sale_payments` | ledger | |
| `customer_receipts` | ledger | |
| `customer_security_deposits` | ledger | |
| `expenses` | ledger | |
| `purchases` | ledger | |
| `purchase_items` | ledger | |
| `purchase_payments` | ledger | |
| `supplier_payments` | ledger | |
| `inventory_movements` | ledger | |
| `inventory_adjustments` | ledger | |
| `inventory_wastage_logs` | ledger | |
| `cash_transactions` | ledger | |
| `stock_transfer_items` | ledger | |
| `customer_order_items` | state | lines of a not yet fulfilled order (re-quoted on price change) |
| `customer_order_status_history` | ledger | |
| `rate_change_log` | ledger | |
| `audit_logs` | ledger | never updated or deleted (BL-016) |
| `cylinder_units` | state | gas weight, status and branch follow the inventory ledger |
| `cylinder_custody` | state | issued → returned |
| `cash_sessions` | state | open → closed |
| `cash_registers` | state | |
| `customer_orders` | state | order lifecycle (status history is the ledger) |
| `stock_transfers` | state | in transit → received / cancelled |
| `document_sequences` | state | number counters |
| `inventory_opening_balances` | state | opening stock, editable until transactions exist |
| `supplier_opening_balances` | state | per-branch opening payable (D9-4) |
| `inventory_policies` | system | |
| `shop_product_availability` | system | |
| `application_settings` | system | |
| `business_settings` | system | |
| `shop_settings` | system | |
| `shop_setting_overrides` | system | |
| `shop_purchase_void_users` | system | |
| `businesses` | system | |
| `locations` | system | |
| `users` | system | |
| `roles` | system | |
| `permissions` | system | |
| `role_permissions` | system | |
| `user_business_access` | system | |
| `user_shop_access` | system | |
| `customer_app_accounts` | system | |
| `customer_signup_requests` | system | |
| `api_sessions` | system | |
| `api_access_tokens` | system | |
| `api_refresh_tokens` | system | |
| `api_idempotency_keys` | system | |
| `migrations` | system | |
| `lpg_migration_history` | system | |
| `lpg_installation_state` | system | installer bookkeeping |
| `lpg_tenant_backfill` | system | BL-013 rollback data |
| `lpg_rate_backfill` | system | BL-012 rollback data |

## Allowed ledger mutations

Each is either part of writing the event itself (filled in within the same transaction) or a
status change that marks the event void. `TransactionalDataRefreshService` (wipes transactional
data on a non-production test install, BL-016) is the only other exception.

| Table | Site | What changes |
|---|---|---|
| `inventory_adjustments` | `app/Services/InventoryService.php` | `adjustment_no` and `after_state` of the row just inserted |
| `purchases` | `app/Services/PurchaseService.php` | void: `status`, `voided_*` |
| `supplier_payments` | `app/Services/PurchaseService.php` | void of the settlement paid with a voided purchase |
| `sales` | `app/Services/SalesService.php` | void: `status`, `voided_by`, `voided_at`, `void_reason` |
| `customer_receipts` | `app/Services/SalesService.php` | void of the OS settlement collected with a voided sale |

## Stock movement states (BL-033)

`inventory_movements` is never updated or deleted: a void, a purchase void or an edited or deleted
opening entry adds reversal rows instead. Every row records the cylinder's state before and after
the movement in `from_state` / `to_state`:

| State | Meaning |
|---|---|
| `none` | not in the business's stock (before a purchase, opening or adjustment created the unit) |
| `filled`, `empty` | in the branch's stock |
| `custody` | with a customer on security deposit |
| `sold` | sold, written off or removed by a void (out of stock) |
| `in_transit` | between branches on a two-step transfer |
| `retired` | an opening cylinder withdrawn by editing or deleting its opening entry |

Gas rows carry the state of the cylinder that holds the gas (a partial gas sale is `filled` →
`filled`; the sale that empties it is `filled` → `empty`). Invariant, checked after every QA suite
(`run_all.sh`, `QA_UNIT_MISMATCH_SQL` in `tests/qa/env.sh`): each unit's status equals the
`to_state` of its latest movement. `ledger_check.mjs` fails when an insert omits the states.
Rows written before BL-033 were back-filled by the migration on a best-effort basis.
