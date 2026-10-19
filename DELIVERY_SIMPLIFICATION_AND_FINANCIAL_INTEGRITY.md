# VMARKET DELIVERY SIMPLIFICATION AND FINANCIAL-INTEGRITY IMPLEMENTATION

**Repository:** `hotvictorious-rgb/newVmarket`

## Mission

Modify the existing VICTORIOUS MARKET application to support vendor-managed delivery and customer pickup while retaining secure multi-vendor checkout, Paystack payments, item-level returns/refunds, vendor settlements, cashback/discount accounting, and auditability.

Do not rebuild the application from scratch. Do not implement only frontend changes. Do not delete old logistics code until its dependencies, data, API consumers, jobs, and tests have been mapped.

## Authoritative business-rule source

The detailed and controlling rules are in [.ai/BUSINESS_RULES.md](.ai/BUSINESS_RULES.md). If an older section of this implementation plan conflicts with that file, the vendor-managed rules take precedence. This document must not reintroduce platform-managed logistics, fleet allocation, rider earnings, or rider payouts.

## Operating model

- Each vendor manages its own delivery and registers/links its own riders.
- Every active rider must belong to exactly one vendor; the vendor manually assigns its own child vendor orders to its own riders.
- Riders are operational-only. The platform does not manage rider dispatch, pay riders, or maintain rider wallets/earnings/payouts.
- Vendors configure their own supported delivery destinations and fees. Explicit LGA pricing overrides a state-level fallback; unsupported destinations fail closed.
- Each vendor's products, fulfilment choice, delivery fee, commission, cashback eligibility, and financial outcome are tracked independently within the parent checkout / child vendor-order structure.
- In-shop pickup remains supported as a zero-delivery-fee option and uses the pickup pass RES-XXXXXX at the vendor's physical shop.
- VMARKET retains payment verification, vendor verification, safety oversight, dispute review, authorized refund controls, ledger integrity, vendor settlement controls, and audit logs; it does not run delivery operations.
- Preserve customer addresses, destination validation, fulfilment events, evidence, and historical logistics records. Retire legacy logistics paths from new fulfilment only after their dependencies are mapped.
- Rider/delivery confirmation is an operational event and grants no financial permissions to the rider. An independent authorized financial service may consume a verified event under the published settlement policy; the rider cannot directly edit ledgers, approve refunds, change prices, or request payouts.
- Rider interfaces may expose the customer's name, delivery address, and a protected Call Customer action only. No raw phone number, payment state, amount due, delivery fee, card details, commission, cashback, or payout data.

## Merchandise and Delivery Fee Allocation / Refund Accounting

The marketplace uses separate allocation bases for merchandise and delivery. Do not combine them into one blanket commission percentage.

### Merchandise allocation
For eligible merchandise value, the current allocation is:
- 90% vendor allocation
- 5% customer cashback
- 5% platform merchandise share

### Delivery-fee allocation
For the vendor-configured delivery fee, the current allocation is:
- 90% vendor allocation
- 10% platform delivery-fee share

The vendor separately pays its rider outside Victorious MARKET. No part of the delivery-fee allocation is a rider payout, and no rider payable is created by the platform.

### Example

For a vendor order with ₦90,000 eligible merchandise and ₦10,000 delivery:

| Component | Calculation | Amount |
| :--- | :--- | ---: |
| Vendor merchandise allocation | 90% × ₦90,000 | ₦81,000 |
| Customer cashback | 5% × ₦90,000 | ₦4,500 |
| Platform merchandise share | 5% × ₦90,000 | ₦4,500 |
| Vendor delivery-fee allocation | 90% × ₦10,000 | ₦9,000 |
| Platform delivery-fee share | 10% × ₦10,000 | ₦1,000 |
| Customer payment | Merchandise + delivery | ₦100,000 |

These are gross allocation examples before discounts, gateway fees, taxes, approved adjustments, or special product rules. Apply the actual stored order allocations and do not infer a flat 10% platform commission on the entire order.

### Refund rules

1. Calculate each allocation per vendor child order, not as one shared cart-level split.
2. A merchandise refund reverses the applicable vendor merchandise allocation, platform merchandise share, and cashback associated with the refunded eligible product amount.
3. A delivery-fee refund reverses the corresponding vendor delivery allocation and platform delivery-fee share. Delivery fee refundability depends on the published reason-specific customer policy and applicable law.
4. Never reverse the same commission, cashback, vendor payable, or refund twice. Use unique idempotency keys and immutable reversal journal entries.
5. If vendor proceeds have already been paid, record the recovery/liability through the authorized financial engine rather than pretending the funds remain available.
6. Approval is not completion: reconcile the actual Paystack result before showing a refund as completed.
7. The amount refunded to the customer is the approved refund amount; internal allocation reversals are not additional customer refunds.
8. All amounts must be calculated server-side, auditable, idempotent, and reconciled against the payment provider.

## 30-Minute Stock-Hold and Late-Payment Reconciliation Policy

For VICTORIOUS MARKET, if a customer pays after the 30-minute stock hold expires, the system should verify the payment first, check the stock again, and then either confirm the order or initiate a controlled refund. It should never assume that payment automatically means stock is still available.

The important distinction is that 30 minutes is the stock-hold duration, not a deadline that makes a customer's money disappear.

### How it should work

1. **The 30-minute hold expires**  
   The system marks the hold as expired or awaiting reconciliation. If Paystack confirms that payment failed or was abandoned, the system releases the reserved quantity so another customer can buy it.

2. **The customer actually pays late, but stock is still available**  
   The backend verifies the payment directly with Paystack, checks the amount and reference, and atomically rechecks stock. If the requested quantity is available, it deducts the stock once and confirms the order.

3. **The customer pays late, but the stock has sold out**  
   The system records the verified payment but does not confirm the order for fulfilment. It creates a refund case and initiates the appropriate refund process. The order remains in a clear state such as paid_stock_unavailable until the case is resolved.

4. **Payment status is uncertain when the hold expires**  
   Do not immediately release stock solely because the timer elapsed. Check Paystack's authoritative status. If the outcome remains uncertain, keep the order in reconciliation and prevent duplicate deductions, duplicate orders, or duplicate refunds.

### Example

A vendor has 5 phones in stock. A customer tries to buy 2.

| Event | Available stock | Held stock |
| :--- | :--- | :--- |
| **Before checkout** | 5 | 0 |
| **Customer clicks Pay** | 5 | 2 |
| **Hold expires; payment is confirmed failed** | 5 | 0 |
| **Another customer buys 4** | 1 | 0 |
| **First customer pays late** | 1 | 0 |

In the final step, the first customer's payment cannot be used to fulfil an order for 2 phones because only 1 remains. VMARKET should initiate a controlled refund rather than oversell or silently substitute another product.

### My recommendation for VMARKET

Implement these rules:

- The hold expires after 30 minutes, but payment verification and refund handling continue beyond that deadline.
- A late payment can still be fulfilled if sufficient stock remains and the backend can safely allocate it.
- Never oversell: use a database transaction and row-level locking or an equivalent atomic inventory mechanism.
- Never refund based only on the browser's message. Verify the payment with Paystack first.
- Never release stock just because the timer expired while payment status is still pending or unknown.
- Never refund twice: every payment and refund operation needs idempotency protection.

One more important detail: if Paystack returns a successful payment after the customer has already been told the hold expired, the backend must still process that verified result safely. The customer-facing message should explain whether the order was confirmed or whether a refund is being handled.

This gives VMARKET a fair policy: customers are protected when payment arrives late, vendors are protected from overselling, and the platform has a controlled path for every outcome.

## V1 Inventory Policy: Simple Vendor Toggle, Honest Stock Guarantees

The V1 vendor interface uses a simple **In Stock / Out of Stock** toggle. Vendors are not required to maintain a numeric shelf count merely to use the marketplace. However, the system must not claim that it knows the exact number of physical units when the vendor has not supplied an authoritative quantity.

### Inventory rules

1. **Binary listing mode:** The vendor's status is the source of truth for whether the listing is offered for sale. The backend must lock the product/listing during checkout, reject an out-of-stock listing, and prevent conflicting concurrent changes to the same listing. The platform cannot guarantee that the physical item remains available if the vendor sells it offline and fails to update the toggle.
2. **Quantity-tracked mode, when available:** If the product has an authoritative physical count, the backend maintains physical, allocated, and held quantities. Available quantity is physical minus allocated minus active holds, clamped at zero. The invariant is allocated + held less than or equal to physical. The vendor UI may remain simple even if the backend maintains a verified quantity.
3. **No invented quantities:** Do not infer a physical stock count from an In Stock toggle. Do not show customers a numeric quantity unless it comes from authoritative inventory.
4. **30-minute payment hold:** A checkout hold is a temporary reservation for the capacity the backend can actually enforce. The hold expires after 30 minutes, but payment verification continues beyond that deadline. Do not release a reservation while payment is pending or uncertain until the authoritative gateway outcome is reconciled.
5. **Verified successful payment:** Convert the relevant active reservation into the order's allocation atomically. If the listing uses quantity-tracked inventory, decrement/allocate exactly once under database locks. If the vendor's offline sale made the item unavailable and the vendor's binary status was stale, do not silently cancel or pretend the item was delivered; move the paid order into an explicit paid-stock-unavailable review/refund workflow.
6. **Failed payment:** Release the reservation only after the gateway result is authoritatively failed/abandoned or reconciliation establishes that no payment succeeded. Repeated callbacks must not release or allocate stock twice.
7. **Concurrency:** Use database transactions, row locks, unique/idempotency constraints, and explicit reservation state. These controls prevent conflicting updates for tracked inventory. They do not reveal unreported offline sales or magically create a physical stock count.
8. **Vendor POS sync:** Integrated point-of-sale synchronisation remains a future phase. Until then, the vendor must promptly toggle a listing Out of Stock after selling the last unit offline.

### Example when the vendor tracks quantities

A vendor has 10 units, 8 allocated to paid orders, and 2 held for checkout. Available quantity is zero. When a hold expires after a confirmed failed payment, the 2 held units return to availability. When payment succeeds, the hold becomes allocation once. Dispatch must not double-decrement stock if allocation already removed it from available inventory.

### Binary-mode trade-off

A binary toggle is deliberately easy for vendors, but it cannot tell the system whether a vendor has one unit or many. If a vendor sells the last unit offline and forgets to update the toggle, a verified late payment may arrive after the item is gone. The system must protect the customer through a documented refund/review process, and the vendor must correct the listing status. Do not describe binary-only inventory as a guaranteed numerical stock system.

### The Customer Protection Policy (Offline Sold-Out Safeguard)

If a vendor sells an item offline and forgets to update their VMARKET status, a customer who pays online must be protected 100%:
1. **Never erase or alter paid records silently**: An existing paid order cannot be cancelled without an auditable ledger trail.
2. **Controlled Refund Execution**: The order transitions to `paid_stock_unavailable` with `vendor_settlement_status = 'refund_pending'`.
3. **Customer funds remain protected**: The verified captured amount is resolved through the authorized refund workflow, normally to the original payment channel. A wallet remedy is permitted only where the customer explicitly chooses it and the approved wallet-credit workflow records balanced ledger entries. Stock-unavailable handlers must not create spendable wallet credit directly.

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
4. Require every enabled doorstep-delivery destination to have a valid vendor-configured fee greater than zero. The in_shop_pickup fulfilment type is the explicit exception and must always have a zero delivery fee.
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
6. Keep the rider app as an operational tool for vendor-linked riders, but remove platform-managed dispatch, fleet affiliation for new orders, and every rider financial/payout workflow. Enforce that each active rider belongs to one vendor and only receives that vendor's manually assigned orders. Preserve legacy logistics dependencies for historical records until dependency mapping supports safe retirement.
7. Preserve existing order data and avoid destructive migrations.
## Current-code findings that must be addressed

The initial source inspection found direct mismatches with the adopted rules. These are findings from code review, not claims that fixes have already been implemented:

1. **Rider ownership is not exclusive yet.** RiderProfile supports both vendor_id and logistics_company_id, and RiderTerminalApiController permits either vendor-linked or fleet-linked riders. New assignment guards must require one active vendor owner and reject fleet-only riders for new work.
2. **Rider API exposes prohibited data.** formatOrderForRider currently includes payment_status and delivery_fee and returns the customer's raw phone field. The customer relation is loaded with phone. Replace this with a privacy-minimal projection and protected Call Customer action; audit all list, history, detail, export, log, and error payloads.
3. **Failed-delivery handler performs financial actions.** RiderTerminalApiController::recordDeliveryAttemptFailed currently creates an immediately available CustomerWalletCredit after the attempt threshold and writes a logistics-company payable journal entry for a retained delivery fee. Both conflict with operational-only riders and the no-platform-rider-payout model. Route remedies through the authorized return/refund and financial-operation services after review.
4. **Legacy financial and fleet concepts remain active.** RiderTerminalApiController imports/uses LogisticsCompanyProfile, CustomerWalletCredit, FinancialJournalEntry, and OrderSettlementService; rider views include earnings/wallet/payout surfaces; logistics-company models, payout requests, and routes remain. Disable these for new vendor-managed delivery, preserve authorized read-only history, and map every route/job/API consumer before removing anything.
5. **Pickup needs explicit enforcement.** The repository has a migration to separate pickup and doorstep statuses, but tests must prove that in_shop_pickup always costs zero, uses RES-XXXXXX, is confirmed at the vendor counter, and never requires a rider or creates a delivery payout.
6. **Assignment lifecycle needs a custody fence.** Assign/reassign endpoints and services must enforce vendor ownership, active rider status, order state, and an atomic custody transition. Reassignment after pickup must require a recorded return/custody-transfer workflow.
7. **COD must not bypass privacy.** Rider projections cannot expose payment status or amount due. Keep rider-assigned COD disabled until a vendor-owned collection/reconciliation flow is explicitly defined and passes privacy and financial-control tests.
8. **Commission/cashback documentation had inconsistent bases.** The adopted allocation is 90/5/5 on eligible merchandise (vendor/cashback/platform) and 90/10 on the delivery fee (vendor/platform). Cashback excludes delivery. Reconcile each child order using these separate bases and reverse each component according to the approved refund.
9. **Doorstep handover service mixes operational and financial work.** DoorstepHandshakeService validates rider handover and item acceptance, but also creates return cases, pending wallet credits and refund reservations, and posts vendor/cashback/platform/delivery-fee journal entries inside the same rider-triggered flow. Preserve required item-level return evidence and the adopted 90/5/5 merchandise plus 90/10 delivery allocation, but separate the handover event from the authorized financial workflow. Financial posting may consume the verified event only through the financial engine, with idempotency, reservation checks, and audit evidence. Do not give riders direct financial permissions or allow a handover callback to create an immediately spendable refund.



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
