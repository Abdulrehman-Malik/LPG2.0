# Module 07 — Database Model, Migrations and Legacy Data

## Existing schema observations

- `locations` exists as the shop/location entity but currently has no `business_id`.
- `users.location_id` exists and is nullable.
- `customers` currently has no `location_id`; `CustomerModel::$allowedFields` likewise has no location field.
- `roles`, `permissions`, `role_permissions` are the existing authorization tables.
- `rate_cards` can be location-specific or global (nullable location).
- Sales and related ledgers retain location attribution.
- The canonical fresh-install schema is `database/schema.sql`; existing installs use the repository's migration mechanism. Do not treat a fresh schema edit as a production upgrade by itself.

## Target data model (logical, names to finalize after code review)

- `businesses`: business identity, name, status and timestamps.
- `locations.business_id`: required parent business after a safe backfill; one business has many shops.
- `customers.location_id`: required assigned shop after all existing customers have been mapped; one shop has many customers.
- Staff-shop assignment table (for example `user_location_assignments`) if users need access to multiple shops; preserve existing `users.location_id` for compatibility/default shop as appropriate.
- Customer mobile-account identity/session mapping table with unique constraints preventing duplicate links.
- `customer_orders`: business, location, customer, public order number, status, fulfilment details, snapshots and timestamps.
- `customer_order_items`: order, product/cylinder type, quantity, server-validated price snapshots and line totals.
- `customer_order_status_history`: prior/new state, actor, reason, timestamp.
- Idempotency support for order creation and order-to-sale posting.
- Optional delivery addresses and device notification tokens only when required by agreed MVP scope.

## Referential and integrity rules

- Every location belongs to one business.
- Every customer belongs to one location.
- Every order references one business, one location and one customer; validate that the customer location equals the order location and the location belongs to the business.
- Enforce order item ownership through its parent order.
- Enforce unique public order number within the intended business/location scope.
- Enforce one-to-one order-to-sale linkage if the MVP permits only one sale per order.
- Add indexes for shop/status/created date, customer/order history, and relevant foreign keys.
- Use InnoDB, transactions, appropriate FK constraints and unique keys. Application checks supplement but do not replace database integrity.

## Migration stages — non-destructive

1. Inspect all existing locations, customers, users and historical customer transactions by location. Produce a report before schema changes.
2. Create businesses and assign current locations to an explicitly approved legacy business (or map multiple known businesses accurately).
3. Add nullable `customers.location_id` first. Backfill only from a verified customer ownership source; do not infer from whichever shop the admin is logged into.
4. Identify customers whose historic transactions span locations or whose shop cannot be established. Put them in a review report and block mobile ordering for unresolved records until an owner resolves them.
5. Add constraints only after data-quality checks pass. Keep migrations restart-safe and record migration completion using the project's migration runner.
6. Add customer-account, staff-shop assignment and order tables.
7. Add API and application code after schema migration tests pass.
8. Verify upgrade path on a copy of an existing database and fresh installation from `database/schema.sql`.
9. Document rollback and backup requirements. If a migration has begun creating order data, prefer forward repair over destructive rollback.

## Important cautions

- Do not move historical sales, receipts, deposits, custody, cash or inventory records just to match the new ownership model.
- Do not assign all old customers to one default shop without explicit approval.
- Do not make `customers.location_id` NOT NULL until every legacy record is mapped and verified.
- Keep `database/schema.sql` and incremental migration scripts consistent.
- No destructive data refresh/reset is part of this feature.

