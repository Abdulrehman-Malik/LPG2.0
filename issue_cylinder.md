# Issue Cylinder / Security Deposit & Cylinder Return Requirements

## Purpose
Authoritative implementation source for POS Security Deposit / Issue Cylinder and Cylinder Return / Refund Deposit.

## Core Accounting Rule — MUST BE INDEPENDENT
Security Deposit is a **separate financial transaction/component** from the gas/cylinder issue or return.

- Security Deposit Receive is always recorded as a separate refundable-deposit liability and is never netted into the gas/cylinder sale ledger. When a customer pays gas/cylinder charges and the deposit together, Shop Settings controls whether the single received amount is allocated Gas/Cylinder First, Security Deposit First, or manually; the resulting payment records remain separate.
- Security Deposit Refund must NEVER be netted, offset, or used as payment against return-gas OS.
- Gas/cylinder OS and Security Deposit OS must be calculated independently and then reflected in the customer's overall OS.
- Deposit receipt/refund must have its own payment/refund records and deposit ledger/history.
- A transaction may contain both gas/cylinder activity and a deposit activity, but the accounting for each must remain independently identifiable and reversible.
- Do not reuse sale-payment allocation logic to settle or reduce the security deposit amount.
- Do not use the security deposit balance to settle gas sale OS.
- Do not use gas return OS to settle or reduce the security deposit balance.

## Security Deposit / Issue Cylinder

### Cylinder Issue
- Select an existing customer and load normal customer context.
- Select cylinder type and Filled/Empty condition; Filled includes filled and partially-filled cylinders.
- Popup shows individual available physical cylinder IDs with Cylinder Type and Filled/Empty filters.
- Multiple line items are allowed, but the same Cylinder Type + Filled/Empty combination cannot be duplicated in one issue transaction.
- Filled and empty cylinders may be issued together. At least one cylinder is required.
- Filled cylinder issue gas = its full current gas quantity/capacity; partially-filled = exact current gas quantity; empty = 0 kg.
- Gas rate follows existing POS rules and total follows current cylinder-sale pricing logic.
- **The user may manually change the Gas Rate at the time of issuing the cylinder.** The edited rate is used for that issue transaction and any applicable party-OS calculation; the configured/default rate remains the initial value.
- At issue time, filled/partially-filled gas stock is reduced by the exact issued gas quantity; the physical cylinder moves to customer custody/pending status and its ID remains tracked.
- Empty cylinders move to customer custody with 0 kg gas.
- Custody is temporary; the shop remains owner.

### Gas/Cylinder OS
- The gas/cylinder issue amount follows the normal POS gas/cylinder OS rules.
- If the gas/cylinder issue creates customer OS, that OS must be calculated and posted independently of the security deposit.
- Security Deposit Receive must not reduce the gas/cylinder OS created by the issue.
- If the gas/cylinder issue is paid immediately, those payments are applied only to the gas/cylinder amount.

### Security Deposit Receive
- One transaction-level Security Deposit Amount field on the right panel, not per cylinder. It may be 0 or positive.
- Deposit is received independently from the gas/cylinder amount.
- Deposit uses existing POS payment methods, but deposit payment records must be identifiable as Security Deposit Receive and must not be inserted as payment against the gas/cylinder sale amount.
- Deposit has its own customer security-deposit ledger/balance.
- Only the actual sum of deposit receipt payments increases the customer's refundable security-deposit balance; the Security Deposit Amount field may be any non-negative amount and does not force a matching receipt.
- Deposit receipt is not required to equal the amount entered or attached to cylinder custody. No equality/minimum/maximum receipt validation applies (ordinary valid payment methods and non-negative amounts still apply).
- If Shop Settings enables **Include Security Deposit in Party OS**, the deposit receipt independently increases Party OS by the deposit amount.
- If disabled, the deposit receipt does not affect Party OS.
- The deposit OS impact must be recorded separately so that a later refund can reverse only the deposit OS impact.
- The deposit balance must never be calculated from or mixed with gas/cylinder sale OS.

## Cylinder Return

### Cylinder Return / Gas Return
- Select customer first; show only that customer's currently pending/issued cylinders.
- Popup shows individual cylinder cards with Cylinder ID, type/capacity, current status, and gas quantity; filters are Cylinder Type and Filled/Empty.
- User selects specific IDs; each selected cylinder becomes its own return line.
- Filled/partially-filled and empty cylinders can be returned together. At least one cylinder is required.
- Return gas defaults to 0 and is entered separately for each cylinder; no Apply-to-All for quantity.
- Return gas 0 => cylinder becomes Empty Stock.
- Return gas >0 => cylinder becomes Partially Filled Stock with that exact gas quantity and same physical ID; returned gas is added to shop gas stock.
- Issued gas was already deducted at issue, so return never deducts issued gas again.
- Consumed gas = Issued Gas - Returned Gas and must be retained in history. Show per line: Issued X kg | Return Y kg | Consumed Z kg.
- Original issue gas rate is shown by default. User can change it.
- Support one overall return-gas rate plus individual per-cylinder rates. Provide Apply to All for the overall rate; individual changes override the overall rate.

### Return Gas OS
- If **Return Gas Affects Party OS** is enabled, return-gas ledger impact is calculated independently from the Security Deposit Refund.
- Return gas OS must never be reduced by the deposit refund.
- If return-gas OS impact is disabled, returned gas still increases stock but does not change Party OS.
- The return-gas OS entry must be separately identifiable in transaction/ledger history.

## Return Gas Settings
Two independent options, both disabled by default:
1. Allow/Show Return Gas Quantity.
2. Return Gas Affects Party OS.

If quantity is disabled, users cannot enter it. If quantity is enabled but OS impact is disabled, stock is still increased but party OS is unchanged.

## Invalid Return Gas Conditions
- Empty-issued cylinder returned with gas >0: allowed only if a Shop Settings option permits it; when allowed, show confirmation/warning. When disabled, reject and prevent the entire transaction from saving.
- Return gas greater than originally issued: allowed only if a Shop Settings option permits it; when allowed, show confirmation/warning. When disabled, reject and prevent the entire transaction from saving.
- Never silently change a disallowed value to 0.

## Security Deposit Refund

### Refund Rules
- One transaction-level Security Deposit Refund Amount field on the right panel; it may be 0.
- Refund cannot exceed customer's currently refundable security-deposit balance.
- If no refund is made, remaining deposit stays refundable for future transactions.
- Refund is independent from return-gas/cylinder OS.
- Refund must never be treated as a payment/settlement against the return-gas amount.
- Refund uses existing POS payment methods, but refund payment records must be identifiable as Security Deposit Refund and must not be inserted as payment against the return-gas/cylinder return amount.
- A refund decreases the customer's refundable security-deposit balance.
- If **Include Security Deposit in Party OS** is enabled, the refund independently decreases Party OS by the refund amount, reversing the deposit's OS impact.
- If disabled, the refund does not affect Party OS.
- The refund OS impact must reverse only the deposit OS component; it must not change the return-gas OS calculation.
- Return transaction requires at least one cylinder.

## Example — Issue

Example:
- Gas/cylinder issue amount = 5,000
- Security deposit received = 3,000
- Gas/cylinder payment = 5,000
- Deposit payment = 3,000

These are two independent financial components.

If deposit is configured to affect OS:
- Gas/cylinder OS = based only on the 5,000 gas/cylinder transaction and its payments.
- Deposit OS = +3,000.
- Customer overall OS = gas/cylinder OS + deposit OS.

If deposit is configured not to affect OS:
- Deposit balance still increases by 3,000.
- Deposit OS impact = 0.

The 3,000 deposit must never be used to make the 5,000 gas/cylinder sale appear paid.

## Example — Return

Example:
- Customer returns cylinder.
- Returned gas value = 1,000.
- Security deposit refund = 3,000.

If both OS settings are enabled:
- Return-gas OS impact = based only on the 1,000 returned gas.
- Deposit refund OS impact = -3,000.
- Overall OS = previous OS + return-gas OS impact - deposit refund amount.

The 3,000 refund must never be used to settle the 1,000 return-gas amount, and the 1,000 return-gas OS must never reduce the refundable deposit balance.

## Balances / Audit
Maintain and reconcile separately:
- Gas/cylinder Party OS
- Security Deposit OS impact
- Overall Party OS
- Security deposit refundable balance
- Security deposit received history
- Security deposit refunded history
- Pending cylinder balance/status
- Issued gas
- Returned gas
- Consumed gas
- Physical cylinder custody history
- Gas/cylinder issue payments
- Security deposit receipt payments
- Return-gas/cylinder return payments, where applicable
- Security deposit refund payments

Every deposit receipt/refund and every gas/cylinder OS movement must remain independently identifiable in audit/history.

## Atomicity / Validation
- Financial, stock, cylinder-status, custody, deposit and ledger effects must post atomically.
- Any validation failure prevents the entire transaction from saving.
- Server-side validation is mandatory.
- Lock/revalidate physical cylinder IDs during posting to prevent double issue/return.
- Reject duplicate selections and returns for cylinders not pending for the selected customer.
- Validate gas/cylinder payments independently from Security Deposit Receive.
- Never require the Security Deposit receipt total to equal the Security Deposit Amount. Customer deposit balance is based only on actual deposit receipts minus actual refunds, not the amount entered for cylinder custody.
- Validate return-gas/cylinder return financial effects independently from Security Deposit Refund.
- Do not allow one component's payment/refund amount to silently satisfy another component's validation.

## Shop Settings
Add/configure:
- POS visibility of Security Deposit / Issue Cylinder and Cylinder Return / Refund Deposit.
- Include Security Deposit in Party OS (default disabled).
- Allow/Show Return Gas Quantity (default disabled).
- Return Gas Affects Party OS (default disabled).
- Allow Empty-Issued Cylinder to be Returned With Gas (default disabled).
- Allow Return Gas Greater Than Originally Issued (default disabled).

## Implementation Rules
- Reuse existing POS payment/cash and physical-cylinder infrastructure where possible.
- Security Deposit must use a dedicated deposit ledger and dedicated deposit accounting flow.
- Do not route Security Deposit Receive through normal gas/cylinder sale payment allocation.
- Do not route Security Deposit Refund through normal return-gas payment/settlement allocation.
- Gas/cylinder issue OS and return-gas OS must remain independent from deposit accounting.
- Deposit OS impact, when enabled, must be a separate ledger component that can be reversed independently.
- Deposit balance must be based only on deposit receive/refund history.
- Custody is not a cylinder sale. Never deduct issued gas again on return.
- Preserve issue gas, return gas, consumed gas, original/return rates, deposit receive/refund amounts, and full physical-cylinder custody history.
- Update implementation tests and documentation whenever this requirement changes.
