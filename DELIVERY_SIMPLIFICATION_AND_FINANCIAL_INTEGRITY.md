# VMARKET DELIVERY SIMPLIFICATION AND FINANCIAL-INTEGRITY IMPLEMENTATION

> **Superseding owner decision (21 October 2026):** VM is a retailer based in Uyo; suppliers are private sources linked to product offers/order items, not public marketplace sellers. VM coordinates delivery through approved logistics companies, and every assigned rider must be linked to exactly one active logistics company. Authorized supplier-shop pickup remains supported. Supplier payment eligibility begins only after verified successful customer delivery or pickup and reconciliation. Legacy vendor commission, payout and vendor-managed delivery rules are not default retailer rules.


> **Binding financial safeguard:** Victorious MARKET executes approved customer refunds. Supplier recovery is separate, evidence-backed and contract-dependent. A customer refund must not wait for supplier recovery. `.ai/BUSINESS_RULES.md`, `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md`, and `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md` govern current policy.


**Repository:** `hotvictorious-rgb/newVmarket`

## Mission

Audit and migrate the existing Laravel application from marketplace/vendor-managed fulfilment to Victorious MARKET as a centralized retailer operating from Uyo. Preserve proven payment, refund, idempotency, evidence, and financial-integrity controls. Do not rebuild from scratch, make only frontend changes, or delete legacy logistics/financial data before mapping dependencies.

## Authoritative business-rule source

The current model is defined in [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md), [.ai/BUSINESS_RULES.md](.ai/BUSINESS_RULES.md), and [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). This plan is subordinate to the current owner-confirmed retailer model. Legacy vendor payout and delivery rules below must not be reused as retailer rules without an explicit decision.

## Operating model

**Current-policy supersession:** Any later section in this implementation plan that refers to vendor-managed delivery, vendor rider ownership, vendor commission, 90/5/5 merchandise allocations, 90/10 delivery allocations, vendor FIFO payouts, or immediate cashback accrual is a legacy source-audit finding unless explicitly restated as a requirement for historical records. These legacy rules must not govern new retailer transactions. New supplier payables use the selected offer's immutable wholesale cost; logistics-company charges follow approved VM/company agreements. New cashback accrual remains disabled pending a separate owner-approved policy. Preserve historical records and map their consumers before changing runtime behavior.

- **Private Wholesale Sourcing:** Suppliers provide physical products, inventory availability in Uyo, and wholesale cost prices. VM adds its profit margin and publishes the canonical public retail selling price (`Retail Price = Wholesale Cost + VM Profit Margin`).
- **Automated Sourcing Assignment (Cheapest & Available):** When multiple suppliers offer the same product, the system automatically assigns the order (upon payment for delivery, or reservation for pickup) to the supplier that is available and cheapest in Uyo (`cost_price` lowest).
- **Admin Sourcing Override:** Admin maintains complete supervisory control and can override the automated supplier or shop assignment at any time.
- **Doorstep Delivery Flow (Pay First, Sourced & Dispatched):**
  1. Customer pays the full retail selling price plus delivery fee upfront online via Paystack. Strictly NO Cash on Delivery (COD).
  2. System automatically assigns the order to the cheapest available supplier in Uyo. Admin can override this assignment.
  3. The assigned supplier verifies their physical inventory and confirms product availability in their portal.
  4. Once availability is confirmed, Admin assigns the order to an accredited Logistics Company (onboarded strictly by Admin).
  5. The Logistics Company dispatches its active fleet rider to collect the item from the supplier's shop and deliver to the customer.
  6. Delivery completion strictly requires customer verification OTP (`delivery_otp`) plus doorstep photo proof.
- **In-Shop Pickup Flow (Inspect First, Pay at Shop via Paystack):**
  1. Customer reserves online for free (₦0 upfront).
  2. System automatically assigns the reservation to the supplier shop with the cheapest wholesale cost and available stock, issuing a unique reservation pass (`RES-XXXXXX`) and customer pickup OTP (`verification_code`).
  3. Admin can override the assigned reservation pickup location if necessary.
  4. Customer journeys to the designated shop and can call or chat Victorious MARKET customer support for assistance or directions.
  5. Customer arrives at the supplier shop, presents the reservation code, and the supplier confirms the reservation.
  6. Customer **physically inspects the item at the counter first** to ensure complete satisfaction.
  7. Upon satisfaction, customer pays **directly to Victorious MARKET via Paystack** (mobile payment link, counter QR, or online counter checkout). Strictly NO cash or private vendor bank transfer.
  8. Once Paystack confirms payment, supplier verifies the customer's pickup OTP (`verification_code`), records mandatory photo proof, and hands over the item.
  9. If customer is dissatisfied upon inspection, they walk away without paying; the reservation is cancelled cleanly with zero refund overhead.
- **Operating Base:** Uyo, Akwa Ibom State is the central operating base. Delivery is offered only where local supplier sourcing and accredited logistics partner coverage are confirmed.
- **Delivery Riders:** Every rider assigned to new VM delivery must be active and linked to exactly one approved logistics company. Supplier-owned/vendor-linked riders are not eligible for new delivery assignments.
- **Supplier Payment Eligibility:** Suppliers are paid their agreed wholesale cost strictly after verified successful customer delivery or confirmed in-shop pickup. Order placement, customer payment, or rider collection alone never unlocks supplier payment.
- **Retail Accounting & Customer 5% Cashback Retention:** The legacy 90/5/5 marketplace split is superseded, but the **5% customer cashback feature is actively retained** as VM's core customer loyalty engine. Accounting follows retail margin: `Customer Retail Price - Supplier Wholesale Cost = VM Gross Margin`. The 5% customer cashback liability is funded directly out of VM's gross retail margin (never deducted from the supplier's wholesale cost). Suppliers receive 100% of agreed wholesale cost upon verified completion. **Non-Compounding Rule:** Cashback CANNOT be earned on amounts covered by redeemed cashback or discounts; it is earned strictly on real money paid through Paystack on eligible merchandise (`(Merchandise Subtotal - Cashback Redeemed) * 5%`). Cashback matures 24 hours after the return window closes and is redeemable on future checkouts up to 50% of the cart value.

## Retail accounting and refund safeguards

- Supplier purchase cost is a cost/payable (`cost_price * quantity`), not a marketplace commission.
- Reconcile the exact order-item, selected supplier offer, quantity, agreed cost and verified fulfilment before supplier payment eligibility.
- Keep payable, authorization, payment attempt, provider confirmation, reversal and reconciliation as separate idempotent states. Timeout means uncertain and must be reconciled before retry.
- VM remains responsible for an approved customer refund even if a supplier has been paid. Supplier receivable/recovery requires evidence-backed supplier responsibility and contractual recoverability; a complaint alone is not debt.
- Preserve existing payment verification, webhook authentication, refund authorization, original payment route, refund ceilings, immutable journal and reconciliation controls.
- The legacy web checkout bypass of CheckoutExecutionService remains open until fixed and tested.

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
3. **Customer funds remain protected**: The verified captured amount is resolved through the authorized refund workflow to the original Paystack payment channel at launch. Wallet-credit refunds are deferred and must remain disabled in UI, API and service authorization. Stock-unavailable handlers must not create spendable wallet credit directly. Any later wallet-refund release requires a separate owner-approved policy, explicit customer consent, timestamped consent, admin authorization and an audit trail.

## Required workflow

### Phase 1: Audit before coding

1. Verify the current branch, commit, worktree state, and remote status.
2. Map checkout, parent payments, child vendor orders, product lines, discounts, delivery fees, Paystack references, ledger journals, cashback, payout allocations, returns, refunds, rider/logistics services, APIs, jobs, webhooks, and Flutter/web/admin interfaces.
3. Identify existing equivalent models and tables. Reuse sound existing infrastructure rather than creating duplicates.
4. Run baseline tests and record actual results.
5. Produce a file-by-file plan listing changes, migrations, dependencies, risks, and acceptance tests.
6. Do not push directly to `main`.

### Phase 2: Central delivery coverage and fees

1. Define the initial service areas reachable from Uyo; do not assume all Nigerian destinations are covered.
2. Define VM-owned customer delivery fees and approved logistics-company rate/settlement agreements. Missing or stale coverage/fee rules must fail closed.
3. Snapshot the destination, quoted customer fee and applicable rule version on the order.
4. Require every rider assigned to new VM work to be active and linked to exactly one active logistics company.
5. Keep supplier source/pickup location separate from customer destination and logistics coverage.

### Phase 3: Checkout and payment

1. Link every order item to the exact selected supplier offer and immutable purchase-cost snapshot.
2. Preserve server-side payment verification, exact amount/currency/reference binding, idempotency, unique constraints and reconciliation.
3. Prevent supplier payable creation or eligibility from order placement, payment success alone, supplier readiness or carrier collection.
4. Preserve the approved customer payment paths. Supplier-shop pickup payment, where supported, must remain within VM's authorized payment workflow.
5. Audit every web/API checkout route. The legacy web checkout bypass of CheckoutExecutionService must be corrected before release.

### Phase 4: Central fulfilment and supplier pickup

1. VM coordinates product sourcing and dispatch through an approved logistics company.
2. Validate logistics-company status and the rider's exclusive active link to that company on assignment and relevant custody transitions.
3. Separate supplier-to-VM collection, logistics-company custody, delivery attempts, customer handover and return-to-origin events.
4. Doorstep completion requires server-verified customer OTP and doorstep photo. Supplier-shop pickup requires the reservation pass, separate one-time customer pickup verification and in-shop photo.
5. Only verified customer handover makes the supplier payable eligible, after order/amount reconciliation. Eligibility does not trigger automatic payment.
6. Remove or block new-order vendor-controlled dispatch, vendor-linked rider assignments, vendor commission payouts and legacy fleet payment paths after dependencies are mapped; preserve historical records.
7. A failed delivery or customer refusal is not automatic refund approval, supplier fault or debt.

## Current-code findings that must be addressed

The initial source inspection found direct mismatches with the adopted rules. These are findings from code review, not claims that fixes have already been implemented:

1. **Rider ownership is not exclusive yet.** RiderProfile supports both vendor_id and logistics_company_id, and RiderTerminalApiController permits either vendor-linked or fleet-linked riders. New assignment guards must require one active vendor owner and reject fleet-only riders for new work.
2. **Rider API exposes prohibited data.** formatOrderForRider currently includes payment_status and delivery_fee and returns the customer's raw phone field. The customer relation is loaded with phone. Replace this with a privacy-minimal projection and protected Call Customer action; audit all list, history, detail, export, log, and error payloads.
3. **Failed-delivery handler performs financial actions.** RiderTerminalApiController::recordDeliveryAttemptFailed currently creates an immediately available CustomerWalletCredit after the attempt threshold and writes a logistics-company payable journal entry for a retained delivery fee. Both conflict with operational-only riders and the no-platform-rider-payout model. Route remedies through the authorized return/refund and financial-operation services after review.
4. **Legacy financial and fleet concepts remain active.** RiderTerminalApiController imports/uses LogisticsCompanyProfile, CustomerWalletCredit, FinancialJournalEntry, and OrderSettlementService; rider views include earnings/wallet/payout surfaces; logistics-company models, payout requests, and routes remain. Disable these for new vendor-managed delivery, preserve authorized read-only history, and map every route/job/API consumer before removing anything.
5. **Pickup needs explicit enforcement.** The customer counter pickup requires BOTH the RES-XXXXXX reservation pass and the separate one-time customer pickup OTP (`pickup_verification_code`); the OTP supplements and never replaces the reservation pass. Tests must prove that in_shop_pickup always costs zero, the owning vendor verifies both credentials at the counter and records the in-shop pickup photo, pickup never requires a rider, and no delivery payout/allocation is created. Rider-to-vendor parcel custody is a separate operational flow.
6. **Assignment lifecycle needs a custody fence.** Assign/reassign endpoints and services must enforce vendor ownership, active rider status, order state, and an atomic custody transition. Reassignment after pickup must require a recorded return/custody-transfer workflow.
7. Doorstep COD is disabled. The two permitted paths are pay before delivery through Victorious MARKET checkout, or reserve in-shop pickup through Victorious MARKET and pay at the approved vendor shop through the platform's authorised payment workflow. No marketplace payment may bypass Victorious MARKET; vendors must not collect cash or private-account transfers. Launch rails are Paystack card and standard bank transfer unless separately approved. The existing 24-hour reservation hold remains supported; payment must be verified before item release.
8. **Legacy allocation finding (historical only).** The inspected legacy code/documentation used 90/5/5 merchandise and 90/10 delivery allocations. These percentages are retired for new retailer transactions and must not be reused for supplier payables or logistics charges. Preserve the original allocation snapshots for historical refunds and reconciliation; new transactions must use retailer accounting and approved logistics agreements.
9. **Doorstep handover service mixes operational and financial work.** DoorstepHandshakeService validates rider handover and item acceptance, but also creates return cases, pending wallet credits and refund reservations, and posts vendor/cashback/platform/delivery-fee journal entries inside the same rider-triggered flow. Preserve required item-level return evidence and the immutable financial allocation applicable to that transaction (historical allocation snapshots for legacy orders; retailer accounting for new orders), but separate the handover event from the authorized financial workflow. Financial posting may consume the verified event only through the financial engine, with idempotency, reservation checks, and audit evidence. Do not give riders direct financial permissions or allow a handover callback to create an immediately spendable refund.



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
13. **Refund responsibility:** Victorious MARKET executes the approved customer refund through the authorized financial-operation/Paystack workflow. The customer must not be redirected to collect the marketplace refund from the vendor.
14. **Vendor recovery:** where the approved reason and actual allocation make the vendor responsible, recover separately from that vendor. Offset held/unpaid vendor proceeds where authorized; if already disbursed, create an auditable recovery receivable/liability and use an approved future settlement offset or recovery process. Do not debit an exhausted payable as though funds were still held, and do not make customer refund completion depend on vendor recovery completion.

### Phase 6: Financial integrity

1. Maintain one authoritative payment/financial-operation and ledger architecture.
2. Ensure journal entries balance and vendor available balances account for outstanding reservations.
3. Handle vendor proceeds already paid through a defined recovery/liability process.
4. Prevent duplicate payment confirmation, refund execution, cashback credit, and payout execution.
5. Verify webhook signatures and exact references; make event processing crash-safe and replay-safe.
6. Use database transactions, appropriate locking, uniqueness constraints, and safe money arithmetic.
7. Preserve uncertain provider states until reconciled.
8. Record the actor, reason, amount, decision, provider reference, and state transitions for every sensitive financial operation.
9. Keep customer refund execution and vendor recovery as separate linked operations: the refund closes only on verified provider confirmation; the recovery tracks its own responsible vendor, amount, offset/receivable, and outstanding balance.

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

## Independent merchandise and delivery-fee refund approval (binding)

Merchandise and delivery are different captured components and must not be mixed in a single approval decision. One customer case may contain both, but the backend must create and audit two independent component decisions.

- Merchandise refund approval covers only identified item/order-line quantities and the merchandise amount after original discount allocation. It reverses only the actual immutable merchandise allocation captured for that historical transaction; new retailer transactions must not use the retired 90/5/5 split.
- Delivery-fee refund approval covers only the delivery fee captured for the specific vendor child order and must have its own reason, evidence, approved amount, actor/time and decision. It reverses only the actual immutable delivery allocation captured for that historical transaction; new retailer transactions must not use the retired 90/10 split. Delivery approval is not implied by item approval, and item approval is not implied by delivery approval.
- Each approved component creates its own idempotent refund operation and reservation, linked to the same original parent Paystack capture and the correct child vendor order. At launch, initiate refunds through Paystack against the verified original transaction; wallet refunds remain disabled. If Paystack emits `refund.needs-attention`, leave the component unresolved for authorized review; do not route to a wallet, make an off-platform payment, or use an alternate bank destination unless a separate owner-approved policy explicitly permits it.
- A component may be approved, rejected, pending, or confirmed independently of its sibling. Webhook events, failures, timeouts and reconciliation update only the exact matching component operation.
- Enforce component and child-order ceilings plus the shared parent-capture ceiling under locks, counting confirmed refunds and active reservations across all child orders/components. A in_shop_pickup order has zero delivery fee and therefore no delivery-fee refund.
- Do not calculate a refund using current product prices or current vendor delivery settings. Use immutable checkout allocation snapshots and previously refunded/reserved amounts.
- Vendor recovery and cashback recovery are separate accounting operations. They do not authorize a refund and do not make customer refund completion dependent on vendor recovery.
- The 24-hour return/claim window starts at verified delivery or confirmed in-shop pickup. Cashback's earliest maturity is `refund_window_expires_at + 24 hours` (equivalent to `+ 1 day`), provided no blocking return/dispute or fulfilment exception remains. This extra buffer affects cashback maturity only; it does not extend the customer claim window or promise Paystack refund completion within 24 hours.

Approval is not completion: each component is reported as refunded only after the exact Paystack refund operation is provider-confirmed. A failed or uncertain operation leaves that component open for reconciliation.

### Implementation acceptance tests
- Merchandise-only approval with delivery denied.
- Delivery-only approval with merchandise denied.
- Both components approved through two separate decision/operation records.
- One component remains pending/failed while the other confirms.
- Zero-fee in-shop pickup rejects a delivery-fee refund.
- Discounts, partial quantities, multiple vendors, concurrent reservations and aggregate parent-capture limits remain correct.
- Each webhook and idempotency key is bound to the exact component, amount, child order and original capture.
