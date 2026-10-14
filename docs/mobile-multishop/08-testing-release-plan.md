# Module 08 — Testing, Pilot and Release Gates

## Required documentation and workflows

Follow `agent.md`, `TESTING.md`, and `SQA.md`. The existing agent rules require runtime QA after screen-level changes and regression testing for impacted workflows. Do not mark user-executed tests as passed without the user's actual evidence.

## Automated backend tests

### Identity and shop ownership
- Customer belongs to exactly one active shop.
- New customer/account linking is atomic and prevents duplicate links.
- Invalid, inactive or blocked customer cannot order.
- Customer A cannot read Customer B's order, even when IDs are changed.
- Customer cannot submit an order for another shop.
- Shop A staff cannot access Shop B's orders.
- Business A owner cannot access Business B data.
- Business owner can access all authorized shops within their business.
- Existing roles and custom permissions remain intact after migration.

### Order lifecycle
- Successful order creation creates exactly one pending order.
- Retried request with same idempotency key does not duplicate order.
- Invalid item, quantity, price or inactive product is rejected.
- Shop acceptance revalidates current price and stock/availability.
- Invalid status transitions are rejected.
- Rejection/cancellation stores actor/reason and does not post sales, payments, cash, inventory, deposits or custody.
- Duplicate acceptance/fulfilment cannot post a sale twice.
- Failure midway leaves no partial order/financial state.

### Security
- Unauthenticated API request is rejected with JSON, not redirected to HTML login.
- Expired/revoked token cannot access data.
- Object-level authorization and shop scope are tested for every endpoint.
- Mass assignment, SQL error leakage, OTP abuse, request replay and brute force are tested.
- Rate limits and audit events are verified.

### Regression
- Existing Gas Sale, Cylinder Sale, Security Deposit/Issue Cylinder, Cylinder Return/Refund Deposit.
- Customer ledger, receipts, security-deposit tab, cash sessions and reports.
- Inventory adjustments, purchases, cylinder custody and existing role/permission UI.
- Shop Settings and location-specific rates.
- Installation and migration flows on fresh and existing databases.

## Manual end-to-end pilot

1. Use one test business with two shops and separate staff assignments.
2. Create customers assigned to Shop A and Shop B.
3. Sign in as Customer A; confirm only Shop A catalogue/orders are accessible.
4. Submit an order; verify it appears once in Shop A's mobile/web order queue.
5. Sign in as Shop A staff; accept and update status.
6. Confirm Customer A sees the same status.
7. Verify Shop B staff cannot view or act on Shop A's order.
8. Sign in as business owner; confirm cross-shop overview only within the owner's business.
9. Fulfil one accepted order through the approved POS integration and reconcile sale, inventory and payment records.
10. Repeat with network interruption/retry, cancellation, stock change and price change.
11. Run Android and iOS functional tests against the same API.
12. Verify production configuration, TLS, token expiry/revocation, logs, backups and rollback plan.

## Release gates

- [ ] Business/location/customer ownership migration reviewed and approved.
- [ ] Existing database upgrade test passes.
- [ ] Fresh-install schema includes the final supported schema.
- [ ] Role/permission and data-scope tests pass.
- [ ] API security tests pass.
- [ ] Order-to-POS duplicate protection and reconciliation pass.
- [ ] Existing POS regression suite passes.
- [ ] Runtime QA passes on impacted web screens for current commit.
- [ ] Android and iOS test builds pass.
- [ ] Pilot owner signs off on order statuses and operational flow.
- [ ] Backup, monitoring, support and rollback plans are ready.
- [ ] Explicit approval to merge/deploy is obtained.

Do not publish the mobile app or enable production ordering before all applicable gates pass.
