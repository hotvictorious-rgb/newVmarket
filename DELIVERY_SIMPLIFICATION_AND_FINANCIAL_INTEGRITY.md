# VMARKET DELIVERY SIMPLIFICATION AND FINANCIAL-INTEGRITY IMPLEMENTATION

**Repository:** `hotvictorious-rgb/newVmarket`

## Mission

Modify the existing VICTORIOUS MARKET application to support vendor-managed delivery and customer pickup while retaining secure multi-vendor checkout, Paystack payments, item-level returns/refunds, vendor settlements, cashback/discount accounting, and auditability.

Do not rebuild the application from scratch. Do not implement only frontend changes. Do not delete old logistics code until its dependencies, data, API consumers, jobs, and tests have been mapped.

## Operating model

- Vendors configure their own delivery destinations and positive delivery fees.
- Vendors arrange and manage their own delivery personnel or couriers.
- Customers can choose customer pickup where the vendor supports it.
- Vendors may configure delivery prices for individual LGAs and state-level destinations within Nigeria.
- Explicit LGA pricing overrides state-level pricing. No valid destination rule means delivery is unavailable.
- Each vendor's products, fulfilment, delivery fee, and financial outcome are tracked independently within a coherent customer checkout.
- VMARKET retains payment verification, vendor verification, fulfilment oversight, dispute review, refund authorization, ledger integrity, payout controls, and audit logs.
- Retire mandatory platform-managed fleet, logistics-company, hub, dispatch, and rider-payout dependencies where no longer needed.
- Preserve delivery addresses, destination validation, fulfilment events, evidence, and dispute workflows.
- Do not let rider/delivery confirmation approve a refund, change prices, or release funds.

## Platform Commission and Refund Accounting Rule

VICTORIOUS MARKET earns 10% of the full eligible vendor-order payment, including the vendor's delivery fee. If an eligible refund is approved, the 10% commission attributable to the refunded amount is also reversed.

VMARKET should not retain commission on money that has been refunded to the customer.

### 1. How the 10% commission works

Example: a customer buys products worth ₦50,000, and the vendor charges ₦5,000 for delivery.

| Breakdown | Amount |
| :--- | :--- |
| Products | ₦50,000 |
| Delivery fee | ₦5,000 |
| Customer payment | ₦55,000 |
| VMARKET commission (10%) | ₦5,500 |
| Vendor allocation before other adjustments | ₦49,500 |

The ₦5,500 is gross platform commission, not net profit. Payment-processing fees, applicable taxes, cashback, and other legitimate adjustments must be accounted for separately.

### 2. What happens when a refund is approved?

Suppose the customer returns an item worth ₦20,000 from that order, and the approved refund covers that item only. Assuming the delivery fee is not refunded:

- **Original product subtotal:** ₦50,000
- **Product value refunded:** ₦20,000
- **Commission reversed (10% × ₦20,000):** ₦2,000
- **Original commission:** ₦5,500
- **Remaining commission:** ₦3,500

The delivery fee remains subject to the refund decision and published policy. If the delivery fee is also refunded, the commission attributable to that refunded delivery fee must be reversed too.

### 3. The rules the financial engine must enforce

1. **Calculate commission per vendor order.** A multi-vendor checkout must not use one shared commission calculation that obscures each vendor's allocation.
2. **Reverse commission proportionately.** Refunds must use the actual approved refundable allocation, not automatically reverse 10% of the entire cart.
3. **Never reverse the same commission twice.** Every refund and commission adjustment needs a unique idempotency key.
4. **Keep an immutable ledger.** Record the original commission and a separate reversal entry; never rewrite the original transaction.
5. **Handle vendor payouts correctly.** If the vendor has already received the money, the system must record and manage the resulting recovery or liability rather than pretending the funds are still available.
6. **Separate refund approval from refund completion.** An approved refund is not proof that Paystack has returned the money to the customer.
7. **Reconcile all amounts.** Product discounts, delivery refunds, cashback, payment-processing fees and previous partial refunds must be accounted for without double-counting.

One important distinction: reversing VMARKET's commission does not automatically mean the customer receives that exact amount as an additional refund. The customer receives the approved refund amount; the commission reversal adjusts how that refund is allocated between VMARKET and the vendor.

### 4. The policy adopted

> **Platform Commission and Refund Rule:** Victorious MARKET charges a 10% commission on the full eligible vendor-order amount, including the vendor's delivery fee. When a refund is approved, the platform commission attributable to the refunded eligible amount is reversed proportionately. The refund amount, commission reversal, vendor allocation, delivery-fee treatment and any other adjustments must be recorded separately in the financial ledger. All calculations must be server-side, auditable, idempotent and reconciled against the payment provider.

Before implementing it, ensure the return policy clearly defines when delivery fees are refundable, especially for defective, incorrect or misrepresented products. The policy should not override applicable consumer-protection requirements.

## Required workflow

### Phase 1: Audit before coding

1. Verify the current branch, commit, worktree state, and remote status.
2. Map checkout, parent payments, child vendor orders, product lines, discounts, delivery fees, Paystack references, ledger journals, cashback, payout allocations, returns, refunds, rider/logistics services, APIs, jobs, webhooks, and Flutter/web/admin interfaces.
3. Identify existing equivalent models and tables. Reuse sound existing infrastructure rather than creating duplicates.
4. Run baseline tests and record actual results.
5. Produce a file-by-file plan listing changes, migrations, dependencies, risks, and acceptance tests.
6. Do not push directly to `main`.

### Phase 2: Delivery coverage and fee settings

1. Use canonical Nigerian state/LGA records and server-side destination validation.
2. Allow vendors to enable only the destinations they serve.
3. Support LGA-specific pricing and state-level fallback pricing.
4. Require every enabled delivery destination to have a valid fee greater than zero.
5. Do not permit missing, zero, negative, malformed, or client-controlled fees.
6. Customer pickup must be modelled separately.
7. Snapshot the selected destination, fulfilment method, and agreed fee at checkout. Subsequent settings changes must not alter existing orders.
8. Fail closed for unsupported destinations.

### Phase 3: Checkout and payment

1. Reconcile each vendor's product subtotal, discount allocation, fulfilment method, delivery fee, cashback eligibility, payable total, commission, and vendor payable.
2. Show customers a clear breakdown for each vendor order before payment.
3. Calculate authoritative totals on the server using safe monetary arithmetic.
4. Preserve the parent checkout and child vendor-order relationship.
5. Verify Paystack transactions server-side against amount, currency, reference, status, and order linkage.
6. Make payment confirmation and webhook processing idempotent.
7. Handle late callbacks, duplicate events, timeout uncertainty, and reconciliation without duplicating financial entries.
8. Do not mark an order paid based only on frontend success or customer-provided evidence.

### Phase 4: Vendor-managed fulfilment

1. Implement or adapt secure order acceptance, preparation, readiness, dispatch, delivery/pickup confirmation, failure, and complaint transitions.
2. Enforce vendor ownership and authorised delivery-person access on the server.
3. Keep dispatch separate from delivery confirmation.
4. Record timestamps, actor identity, relevant evidence, and transition history.
5. Remove obsolete platform dispatch dependencies from critical paths.
6. Decide whether the rider app should become an optional authorised confirmation tool or be retired. Do not leave mandatory RiderProfile or LogisticsCompany dependencies in checkout and order completion.
7. Preserve existing order data and avoid destructive migrations.

### Phase 5: Returns and refunds

1. Assess complaints and return decisions at the affected product/vendor-order level.
2. Support mixed outcomes within a multi-vendor cart.
3. Implement the reviewed customer-change-of-mind delivery-fee retention policy only where disclosed, justified, and legally permitted.
4. Do not automatically penalise customers for vendor fault.
5. Keep original delivery-fee treatment distinct from any return-transport cost.
6. Calculate refund amounts from immutable captured payment and line-item allocations, including discounts, commission, cashback, delivery charges, prior refunds, and amounts already paid to vendors.
7. Never calculate a refund as a percentage of the overall cart total.
8. Fix idempotency ordering, concurrent refund reservations, refund ceilings, and duplicate gateway attempts after uncertain outcomes.
9. Reconcile the actual Paystack result before reporting a refund as completed.
10. Keep approval, financial reservation, provider execution, ledger posting, and reconciliation as distinct auditable steps.
11. Do not grant spendable wallet credit merely because a complaint or return has been opened or approved.
12. Do not use hardcoded refund splits that conflict with the order's actual financial allocations.

### Phase 6: Financial integrity

1. Maintain one authoritative payment/financial-operation and ledger architecture.
2. Ensure journal entries balance and vendor available balances account for outstanding reservations.
3. Handle vendor proceeds already paid through a defined recovery/liability process.
4. Prevent duplicate payment confirmation, refund execution, cashback credit, and payout execution.
5. Verify webhook signatures and exact references; make event processing crash-safe and replay-safe.
6. Use database transactions, appropriate locking, uniqueness constraints, and safe money arithmetic.
7. Preserve uncertain provider states until reconciled.
8. Record the actor, reason, amount, decision, provider reference, and state transitions for every sensitive financial operation.

### Phase 7: Security and regression testing

Test vendor ownership, customer ownership, role permissions, invalid destinations, altered client totals, mixed fulfilment, concurrent refunds, duplicate webhooks, uncertain gateway timeouts, partial refunds, paid vendor payouts, cashback/discount allocations, late payment confirmation, delivery evidence, and historical order compatibility.

Run baseline and post-change tests. Add regression tests for every bug fixed. Do not claim tests passed unless they were actually executed. Do not claim the system is 100% secure.

### Deliverables

Produce:

1. An initial architecture/dependency audit.
2. A file-by-file implementation plan.
3. Safe database migrations and backfill plan where necessary.
4. Backend and interface changes.
5. Automated tests and their actual results.
6. A list of obsolete logistics paths removed or retired.
7. A financial reconciliation report showing the expected ledger and refund behaviour.
8. A list of unresolved issues, deployment risks, and rollback steps.

Work in small, reviewable commits on a dedicated feature branch. Never silently change the business rules, delete historical financial data, or bypass the financial engine to make a test pass.
