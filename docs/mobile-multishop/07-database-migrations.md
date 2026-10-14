# Module 07 — Database Model, Fresh Install and Schema Upgrade

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
- `locations.business_id`: required business parent for every shop in the fresh-install schema; one business has many shops. The model must support multiple businesses from the start.
- `customers.location_id`: required assigned shop for newly created customer masters in the fresh-install schema; one shop has many customers.
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

## Fresh-install schema and optional existing-install upgrade

The owner confirmed that there is no existing business/customer transactional data to migrate for this initiative. Therefore, no historic customer-location mapping report or customer transaction backfill is required.

1. Update `database/schema.sql` so a clean install creates the business entity, business-owned shops, required customer-to-shop relationship, customer identity/session tables, permissions and order tables consistently.
2. Ensure installation/setup seeds create or select the intended business and its shops explicitly; the schema must support multiple businesses and must not assume every shop belongs to one global business.
3. Create repeatable seed/setup rules for products and permissions. Seed logic must not silently grant new capabilities to custom roles.
4. Test fresh installation, foreign keys, unique constraints, indexes and repeat execution of approved seed operations.
5. If an existing installation is later selected for upgrade, define and review a separate schema-upgrade/data-handling plan first. Any existing shop row must be assigned to an explicitly identified business before enforcing a required `business_id`; do not infer ownership from the active staff session.
6. Add customer identity, staff-shop assignment and order tables according to the approved logical model. Existing financial customer masters remain distinct from customer app identities.
7. Keep `database/schema.sql` and incremental migrations consistent, restart-safe and recorded using the repository's migration runner.
8. Test the selected upgrade path on a safe database copy, separately from the fresh-install test.
9. Document backup, recovery and forward-repair procedures. Do not perform production data changes as part of planning.

## Important cautions

- Do not move historical sales, receipts, deposits, custody, cash or inventory records just to match the new ownership model.
- Do not invent historic customer ownership or silently reassign existing transaction history.
- Do not require a legacy mapping/backfill process for the planned clean deployment. A populated upgrade is a separate, explicitly approved scope.
- Keep `database/schema.sql` and incremental migration scripts consistent.
- No destructive data refresh/reset is part of this feature.

