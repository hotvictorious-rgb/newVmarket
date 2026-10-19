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

## Customer Cashback and Loyalty Model (5% Commission Reinvestment)

VMARKET takes 10% commission and uses 5% of that commission to reward customers with cashback. That means you're using half of your commission to encourage customer loyalty while retaining the other half before expenses.

### How your 10% model works

Let's use a customer order of ₦100,000, including the vendor's delivery fee:

| Component | Basis | Amount |
| :--- | :--- | :--- |
| **Total eligible customer payment** | Original payment | **₦100,000** |
| **Vendor allocation** | Before other adjustments | **₦90,000** |
| **Customer cashback** | 5% of the original payment | **₦5,000** |
| **VMARKET's remaining commission** | Before operating expenses | **₦5,000** |

One important distinction: in this example, the customer receives cashback worth 5% of the order payment, which consumes half of VMARKET's 10% commission. It is not 5% of the commission itself.

### Why this could be powerful for VMARKET

- **Customer loyalty:** customers have an additional reason to return and shop again.
- **Repeat purchases:** cashback can encourage customers to consolidate more of their shopping on VMARKET.
- **Competitive differentiation:** the marketplace offers a tangible reward, not just product listings.
- **A controlled incentive:** if cashback is funded from your commission, the reward has a defined source rather than being an unexplained additional cost.

### Four rules I would establish

1. **Earn cashback only on eligible, successfully paid orders.** Do not reward failed, duplicated or unverified transactions.
2. **Define when cashback becomes usable.** For example, after the order reaches the required completion state, subject to your published policy.
3. **Reverse the relevant cashback when an order is refunded.** For partial refunds, calculate the adjustment against the eligible refunded amount.
4. **Keep cashback separate from vendor money and commission accounting.** Track cashback earned, pending, available, redeemed and reversed so that the same funds cannot be counted twice.

There is also an important business decision: will the 5% cashback apply to the full payment, including delivery, or only to the eligible product amount? I would generally favour 5% on eligible product value, excluding delivery fees, because it avoids rewarding delivery charges and makes the programme easier to control.

### Definitive Policy: 5% Cashback on Eligible Products Only (Excluding Delivery Fees)

My recommendation is no — calculate the 5% cashback on eligible product value only, excluding delivery fees.

Your 10% commission can still apply to the full payment, including delivery, as you decided.

For example, if a customer pays ₦100,000:

| Breakdown | Amount |
| :--- | :--- |
| Products | ₦90,000 |
| Delivery | ₦10,000 |
| VMARKET commission (10% of full payment) | ₦10,000 |
| Customer cashback (5% of products only) | ₦4,500 |
| VMARKET retains before expenses | ₦5,500 |

This gives you a clear model:

- **10% commission:** products + delivery.
- **5% cashback:** eligible products only.
- **Remaining commission:** cashback deducted from your commission, before expenses and other adjustments.

**Why I prefer this approach:** delivery is a fulfilment cost, not the product value you want customers to buy more of. Excluding it also helps protect your margins.

**One more important rule:** if a customer receives a refund for a product, the cashback associated with that refunded product should be reversed according to your published policy.

**My verdict:** this makes VMARKET's model more compelling. You're not just earning commission; you're reinvesting part of it into customer retention. The key is to make the cashback rules financially sustainable, transparent and difficult to abuse.

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

## Three-Quantity Inventory Architecture (Physical, Held, Allocated, and Available)

VMARKET can automatically mark a product **Out of Stock** the moment all its physical units have been allocated to paid orders or temporarily held for customers making payment.

### The Invariant and Calculation

Allocated quantity can be greater than available quantity. It must not exceed the physical stock remaining after active holds are accounted for.

For example, a vendor has 10 phones, 8 are allocated to paid orders, and 2 are temporarily held:

| Quantity Type | Amount | Description |
| :--- | :--- | :--- |
| **Physical Quantity** | **10** | Total units physically present on shelf or in warehouse |
| **Allocated Quantity** | **8** | Units sold and committed to confirmed, paid orders |
| **Held Quantity** | **2** | Units temporarily locked in active 30-minute checkout holds |
| **Available Quantity** | **0** | **Out of Stock** (buying disabled for new shoppers) |

The authoritative mathematical calculation is:
\text{Available} = \max\left(0, \text{Physical} - \text{Allocated} - \text{Held}\right)

### Automatic Storefront Rule
- **$\text{Available} = 0$** $\rightarrow$ **Out of Stock**: Buying is disabled immediately. The product remains visible on the storefront with purchasing disabled, rather than disappearing entirely from the marketplace.
- **$\text{Available} = 1$** $\rightarrow$ Customer can purchase at most 1 unit.
- **$\text{Available} = N$** $\rightarrow$ Customer can purchase at most $ units.

The system changes availability immediately within the database transaction that allocates or holds the final unit. The storefront updates the displayed status through a refreshed API response or real-time event.

> **Crucial Rule:** Final checkout validation must always execute on the server under row-level database locking (lockForUpdate()). Merely disabling the frontend "Buy" button is never enough.

### Lifecycle of the Three Quantities

When a vendor has 10 items and 7 are allocated:
1. **Available = 3.**
2. A customer begins checkout and starts payment for 3 units:
   - **Held** becomes 3.
   - **Available** becomes 0 immediately.
   - Product automatically shows **Out of Stock** on the storefront.
   - Other shoppers cannot create a new payment hold.
3. **If payment fails or the 30-minute hold expires**:
   - The 3 held units are released back.
   - **Held** returns to 0.
   - **Available** becomes 3 again.
4. **If payment succeeds**:
   - The 3 held units convert into **Allocated**.
   - **Allocated** becomes 7 + 3 = 10.
   - **Held** returns to 0.
   - **Available** remains 0.
5. **When the vendor dispatches the items / marks fulfilled**:
   - Both **Physical** quantity and **Allocated** quantity decrease by 3 (from 10 to 7).
   - **Available** remains 0 (not negative).

### Critical Discrepancy Rule
\text{Allocated} + \text{Held} \le \text{Physical}

If a merchant physical stock count reports fewer physical units than are already committed, the system must immediately flag an inventory discrepancy, block new sales, and alert the merchant for reconciliation—**never silently erase existing customer allocations or oversell**.


## Vendor Simplicity vs. Backend Integrity: The V1 Binary Stock Architecture ('In Stock' vs. 'Out of Stock')

For VICTORIOUS MARKET V1, simplifying the vendor interface to a clear binary choice—**"In Stock"** and **"Out of Stock"**—is the recommended operating model. Vendors operating physical retail shops in Akwa Ibom shouldn't have to manage complicated inventory numbers, track physical shelf counts manually, or face steep onboarding friction if the priority is making the marketplace easy to adopt and operate.

However, there is one critical architectural distinction: **you can simplify what vendors see without removing the inventory safeguards the system needs internally.**

```
+-------------------------------------------------------------+
|                     VENDOR INTERFACE (V1)                   |
|        Simple Binary Toggle: [ In Stock ] / [ Out of Stock ]|
+-------------------------------------------------------------+
                               |
                               v
+-------------------------------------------------------------+
|                  CUSTOMER STOREFRONT VIEW                   |
|       "Available to Order" (In Stock)  |  "Out of Stock"    |
+-------------------------------------------------------------+
                               |
                               v
+-------------------------------------------------------------+
|                   BACKEND FINANCIAL CORE                     |
|  - Row-Level Locking (lockForUpdate)                        |
|  - 30-Minute Temporary Reservation Holds (held_quantity)    |
|  - Confirmed Payment Allocations (allocated_quantity)       |
|  - Guaranteed Zero-Overselling Concurrency Protection       |
|  - Controlled Refund Pipeline for Offline Depletions        |
+-------------------------------------------------------------+
```

### What Happens Behind the Scenes

1. **Vendor marks In Stock (`is_in_stock = true`)**:
   The product becomes available for purchase across the storefront, subject to the system's order and availability checks. The vendor remains responsible for keeping this status truthful.
2. **Customer begins checkout / starts payment**:
   VMARKET temporarily reserves that product for 30 minutes (`held_quantity` incremented under `lockForUpdate()`) so concurrent shoppers cannot claim the same unit.
3. **Payment succeeds**:
   The reservation converts into a confirmed allocation (`allocated_quantity` incremented, `held_quantity` released).
4. **Vendor sells the last item in their physical shop**:
   The vendor switches the toggle to **Out of Stock** (`is_in_stock = false`). VMARKET immediately rejects all new checkout attempts on the server.
5. **Payment fails or the 30-minute hold expires**:
   The temporary reservation hold is automatically released back (`held_quantity` decremented), provided the product is still available and the vendor has not marked it Out of Stock.

### The Real-World Trade-Offs

With only a binary status, VMARKET does not inherently know whether a vendor has 1 physical phone or 100 phones on their shelf.

- **Concurrent Checkout Rush**: If a vendor has only 1 phone left and two customers attempt to buy it simultaneously, a simple status flag alone cannot prevent both orders without atomic backend holds. The backend must enforce server-side row locking (`lockForUpdate()`) and hold reservations to prevent race conditions.
- **Offline Physical Shop Sales**: If a vendor sells the last item over the counter in their brick-and-mortar shop but forgets to switch the toggle to "Out of Stock" in the VMARKET app, an online customer might place an order for an item that is physically gone.

### The VMARKET V1 Policy Matrix

| Feature | V1 Decision | Architectural Rationale |
| :--- | :--- | :--- |
| **Vendor Interface** | **In Stock / Out of Stock** | Zero cognitive burden; one-tap toggle removes operational friction for local shop owners. |
| **Storefront Presentation** | **In Stock / Out of Stock** | Clean customer messaging: "Available to Order" vs. "Out of Stock" (product remains visible). |
| **Internal Stock Quantities** | **Retained as Safeguards** | The Three-Quantity engine (`Physical`, `Allocated`, `Held`) operates under the hood to prevent race conditions. |
| **30-Minute Payment Holds** | **Mandatory** | Guarantees that a customer initiating Paystack payment has their unit secured. |
| **Duplicate-Payment Protection**| **Mandatory** | Gateway attempt fencing and idempotent ledger recording prevent double charges. |
| **Overselling Prevention** | **Mandatory** | Server-side transaction locks ensure no two shoppers can claim the same held unit. |
| **Vendor POS Sync** | **Future Phase** | Integrated when vendor point-of-sale hardware/scanners are introduced in V2. |

### The Core Purchasing Validation Rule

The core purchasing validation on the server executes inside an atomic database transaction:

```php
// Server-side atomic validation guard
$product = Product::where('id', $validated['product_id'])
    ->lockForUpdate()
    ->firstOrFail();

if (!$product->is_in_stock || !$product->is_available_for_sale) {
    throw ValidationException::withMessages([
        'product' => 'This product is currently out of stock.',
    ]);
}
```

### The Customer Protection Policy (Offline Sold-Out Safeguard)

If a vendor sells an item offline and forgets to update their VMARKET status, a customer who pays online must be protected 100%:
1. **Never erase or alter paid records silently**: An existing paid order cannot be cancelled without an auditable ledger trail.
2. **Controlled Refund Execution**: The order transitions to `paid_stock_unavailable` with `vendor_settlement_status = 'refund_pending'`.
3. **Zero Customer Loss**: The full payment (merchandise + delivery fee) is either refunded to the customer's original payment channel or credited 100% to their customer wallet according to customer preference, with full double-entry ledger journal entries.

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
