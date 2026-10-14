# Module 04 — Order Lifecycle and Existing POS Integration

## Principle

A customer order is a request for fulfilment. It is not a completed sale, payment, deposit, cash transaction or inventory movement.

## Proposed order states

- `pending`: submitted by customer; awaits staff review.
- `accepted`: authorized staff confirmed the order.
- `preparing`: shop is preparing fulfilment.
- `ready_for_collection`: prepared for customer collection.
- `out_for_delivery`: handed to delivery workflow.
- `completed`: fulfilment is confirmed and the business's sale-posting policy is satisfied.
- `rejected`: shop cannot accept the order; reason required.
- `cancelled`: cancelled according to allowed transitions; actor and reason recorded.
- `unable_to_fulfil`: accepted order could not be completed; reason and resolution required.

Allowed transitions must be defined centrally in an OrderService, not scattered across controllers and Flutter UI. The exact cancellation window, whether staff can reopen orders, and the meaning of `completed` must be finalized before coding.

## Order data

Order header should capture:
- unique public order number and internal ID;
- business, shop/location and customer;
- current status and status timestamps;
- delivery or collection method;
- delivery address snapshot and contact snapshot where applicable;
- customer instructions;
- subtotal, fees, discount and total snapshots if those concepts are enabled;
- rate/product descriptions and unit prices at submission time;
- accepted/rejected/cancelled actor and reason;
- timestamps and idempotency key/reference.

Order items should capture product/cylinder type, requested quantity, agreed or quoted price snapshot, line total and notes. Validate all product IDs, quantity, rate, fees and totals on the server.

## Shop acceptance

On acceptance:
1. Confirm customer belongs to the selected shop.
2. Revalidate product is active/orderable, current rate policy, shop stock/availability, and delivery/collection constraints.
3. Recalculate authoritative amounts server-side.
4. Apply any explicit reservation policy only if designed with expiry, cancellation/rejection release and concurrency control. MVP default: do not reserve/deduct inventory merely because an order is pending.
5. Save status transition and audit record atomically.
6. Notify customer only after the transaction commits.

## Existing POS integration

- Keep the four current POS transaction types and their rules: gas sale, cylinder sale, security deposit/issue cylinder, cylinder return/refund deposit.
- Do not route ordinary customer orders through security-deposit or cylinder-custody logic unless the customer explicitly performs that separate supported business process.
- Do not create a posted sale when the order is merely submitted or accepted.
- When staff fulfil the order, use existing validated SalesService/business services for the actual sale. Avoid duplicating credit limits, payment allocation, rates, inventory locks, cash-session checks, deposit balance, custody and audit behavior.
- Define an explicit order-to-sale link with uniqueness so the same order cannot be posted twice. If partial fulfilment is not in the MVP, reject/disable it rather than implement implicitly.
- The customer order should not bypass existing payment modes, customer eligibility or walk-in restrictions.
- A mobile order does not itself create customer credit or change customer OS.

## Idempotency and concurrency

- Client must send an idempotency key on order creation; same key + same user should return the original result, not create a duplicate.
- Use database uniqueness and transactions to prevent duplicate order creation or duplicate order-to-sale posting.
- Status updates must use allowed-transition checks and handle concurrent updates.
- Stock and rates can change between submission and acceptance; acceptance must revalidate.
- Rejected/cancelled orders must not leave inventory, payment, cash or ledger side effects.

## Acceptance criteria

- Pending order creates no sales/payment/cash/deposit/custody/inventory posting.
- An order cannot be accepted by unauthorized shop staff.
- An order cannot be fulfilled twice.
- Customer sees the same authoritative status saved by the backend.
- Existing POS regression tests still pass.
