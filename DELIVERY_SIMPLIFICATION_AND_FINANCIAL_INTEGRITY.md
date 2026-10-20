# VMARKET DELIVERY SIMPLIFICATION AND FINANCIAL-INTEGRITY IMPLEMENTATION

**Repository:** `hotvictorious-rgb/newVmarket`

## Mission

Modify the existing VICTORIOUS MARKET application to support vendor-managed delivery and customer pickup while retaining secure multi-vendor checkout, Paystack payments, item-level returns/refunds, vendor settlements, cashback/discount accounting, and auditability.

Do not rebuild the application from scratch. Do not implement only frontend changes. Do not delete old logistics code until its dependencies, data, API consumers, jobs, and tests have been mapped.

## Authoritative Business Rules — Vendor-Managed Delivery (Approved 2026-10-20)

This section records the current approved business model and supersedes any older wording in this document that describes platform-managed logistics, fleet-managed riders, logistics-company payouts, or an undecided rider-app role. These are business rules, not optional implementation suggestions.

### 1. Ownership and operating boundaries

1. **VICTORIOUS MARKET does not operate a delivery fleet or dispatch service.** It provides marketplace, payment, order, safety, evidence, dispute, and audit infrastructure. It does not recruit riders into a platform fleet, choose riders for vendors, route deliveries, supervise shifts, or operate logistics-company dispatch.
2. **Every active rider belongs to exactly one vendor.** A rider is that vendor's delivery person. The rider must have a vendor association before activation and must never be a free-floating marketplace rider.
3. **Vendors create/add and manage their own riders.** A vendor can activate/deactivate riders associated with that vendor, subject to platform-wide security suspension or legal/compliance intervention. A vendor cannot view, edit, assign, or deactivate another vendor's riders.
4. **A rider is operational, not a financial actor.** The platform must not maintain rider payable balances, rider wallets, rider payout requests, rider bank accounts, or rider financial settlement states. No financial payout from VMARKET to a rider is permitted.
5. **Vendor-to-rider compensation is a private commercial arrangement handled by the vendor outside VMARKET's payout/ledger system.** VMARKET may retain non-financial delivery status and proof records, but must not calculate or disburse rider wages, per-drop payments, tips, or bonuses.
6. **Vendor order assignment is controlled by the owning vendor.** A vendor assigns an eligible order to one of that vendor's active riders. The backend must verify ownership and eligibility on every assignment and operational action. A rider cannot self-assign an order or take another vendor's order.
7. **Reassignment remains a vendor action.** A vendor may reassign an order before completion, but the system must preserve an audit trail, revoke the previous rider's access to the active assignment, and prevent a stale rider from completing or changing the order. If the parcel is physically in the former rider's custody, the vendor must first record a custody handover/return; reassignment cannot erase custody history.
8. **Platform administrators do not dispatch orders.** They may investigate complaints, audit activity, enforce marketplace policy, suspend unsafe accounts, and intervene in exceptional disputes. Those powers do not turn VMARKET into the delivery operator.

### 2. Order and fulfilment model

1. **Multi-vendor checkout remains supported.** A parent checkout can contain separate vendor orders. Each vendor order has its own fulfilment choice, destination, delivery fee, vendor/rider assignment, operational timeline, evidence, and eligible refund outcome. Never assign a single vendor's rider to another vendor's child order.
2. **Doorstep delivery** (fulfilment type 'doorstep_delivery') requires a valid vendor delivery configuration for the customer's destination. Vendor-defined LGA pricing takes precedence over a vendor's state-level fallback where both are configured. If the destination is not served or no valid fee rule applies, doorstep delivery is unavailable; do not silently invent a fee.
3. The selected fulfilment type, destination, delivery-fee amount, fee-policy/version where available, vendor order, and relevant address snapshot must be stored server-side at checkout. Later vendor settings changes must not rewrite an existing order.
4. **In-shop customer pickup** (fulfilment type 'in_shop_pickup') remains a first-class supported fulfilment method. It has **zero delivery fee**, does not require a rider, and must not be treated as a failed doorstep delivery. The customer receives a pickup pass in the format 'RES-XXXXXX'; the authorized vendor/shop counter verifies the pass and records the handover. The pass is an access secret: do not expose it in public order lookups or to unrelated vendors.
5. Keep pickup and doorstep-delivery state transitions separate. A pickup pass is not a rider delivery OTP, and pickup completion must not create a rider assignment, delivery fee, rider compensation, or delivery payout.
6. Payment method (including cash on delivery if enabled for a particular vendor/order) does not grant a rider access to financial records. Any COD collection instructions must be narrowly operational, vendor-authorized, auditable, and must not expose card details or platform commission. Rider-facing screens must never collect or store card details.

### 3. Delivery-fee and financial boundaries

1. The existing commercial rule in this project is a **10% VMARKET commission on the eligible vendor-order amount, including the vendor delivery fee**. The **5% customer cashback applies to eligible product value only, excluding delivery**, and is funded from the platform commission rather than deducted from rider earnings.
2. For delivery-fee allocation, this document treats the platform's 10% commission on the eligible vendor-order total as the platform share; the remaining vendor allocation belongs to the vendor. The vendor is responsible for compensating its rider directly outside the VMARKET payout system.
3. Do not also apply the old standalone “90% logistics company / 10% platform” split as a second charge. It is retired for new orders because there is no platform-managed logistics company or logistics-company beneficiary. The financial engine must not double-charge commission or create a logistics-company payable for vendor-managed delivery.
4. **The zero-fee in_shop_pickup exception is mandatory.** Destination delivery-fee rules do not apply to customer pickup.
5. Financial journals and payout workflows may settle the vendor and platform according to the approved commercial rules. They must never create rider or logistics-company payouts for new vendor-managed deliveries. Refunds, reversals, commission adjustments, and delivery-fee treatment must use the actual immutable vendor-order allocation and must not pay the rider through the platform.

> **Decision interpretation note:** The original letter labels for question 9 were not stored in the repository. To avoid inventing a second fee split, this rule uses the explicit commission policy already in the project and the approved requirement that rider compensation is handled directly by the vendor. If the selected “B” meant a different numeric split, that commercial formula must be explicitly amended before any fee-allocation code is changed.

### 4. Rider information and customer privacy

1. Rider API and web responses must be purpose-limited. The customer contact card contains only the customer's **name**, **delivery address**, and a **Call Customer** action that uses the authorized phone number without revealing unrelated account data.
2. Riders must never receive customer card details, payment instrument data, wallet balances, cashback, total order financial breakdowns, vendor net earnings, platform commission, platform margin, payout balances, or internal ledger/journal details.
3. Remove payment status and delivery-fee amounts from rider-facing order payloads unless a separately approved operational requirement proves a field is essential; the current approved rule is to omit them. Do not include customer email or unrelated profile fields.
4. Return only the operational order data necessary to deliver the parcel, such as order identifier/number, vendor pickup location, item identity and quantity, destination/address, delivery instructions, allowed status actions, and evidence-upload affordances. Do not expose secret OTPs to the rider before the customer presents them.
5. Enforce these rules at the server/API serialization layer, not only by hiding fields in the UI. Test direct API calls and historical-order endpoints as well as dashboards.

### 5. Delivery evidence, failure, and reassignment

1. Delivery completion requires the approved proof workflow (customer delivery OTP/confirmation and required proof photo where configured). Evidence proves an operational event; it does not automatically approve a refund, change the order price, or release funds.
2. Each assignment, reassignment, pickup, arrival, failed attempt, custody transfer, delivery completion, and return-to-vendor event must record actor, timestamp, order, vendor, rider, reason where applicable, and evidence reference.
3. A failed attempt is an **operational event, not a financial event**. Record the reason, notes, and required evidence; notify the vendor; let the vendor decide and record retry, reassignment, customer coordination, or return-to-vendor according to policy.
4. Do not automatically issue a customer wallet credit/refund, charge a rider, create a logistics payout, or finalize financial settlement merely because a delivery attempt failed or an attempt-count threshold was reached. Refund eligibility and execution follow the separate authorized return/refund workflow and require authoritative financial reconciliation.
5. Before reassigning an order already in transit, establish parcel custody and revoke the old rider's active authorization. Old riders must be unable to view the current customer's active delivery data or perform status changes after reassignment.

### 6. Historical logistics records and migration safety

1. Existing logistics-company profiles, payout requests, wallets, collection-point records, rider affiliations, orders, and journal entries are historical records. **Do not hard-delete or rewrite historical financial data.**
2. Preserve historical rows for audit, reconciliation, dispute handling, and old-order support. Mark legacy logistics functionality as retired/read-only where appropriate.
3. For new orders, block logistics-company rider affiliation, dispatch, collection of delivery fees by logistics companies, and logistics-company payout creation. A legacy order may still display its original historical provider/financial facts to authorized staff, but those facts must not grant a legacy company access to new vendor-managed orders.
4. Before making rider_profiles.vendor_id mandatory, backfill each active rider to its rightful vendor, investigate ambiguous or conflicting affiliations, and quarantine unresolved records. Do not guess a vendor or silently reassign historical orders. Keep a rollback and reconciliation report.
5. Use additive, reversible migrations where practical. Do not drop historical columns/tables until dependencies, records, jobs, APIs, and financial reconciliation have been proven safe to retire.

### 7. Operational acceptance criteria

A release is not compliant until tests prove that:
- A rider with no vendor, a suspended vendor, a different vendor's rider, or a legacy fleet-only rider cannot access or act on new vendor-managed orders.
- A vendor can create, activate/deactivate, assign, and reassign only its own riders and orders.
- A stale/reassigned rider cannot read or mutate an assignment after access is revoked.
- Multi-vendor child orders remain isolated and each uses its own vendor's rider.
- Rider responses contain no payment status, delivery fee, card/payment details, commission, cashback, vendor financial metrics, or payout fields.
- No new rider/logistics-company financial journal, payable, wallet, or payout request is created.
- Failed delivery creates an operational record only; refund/wallet credit requires the independent authorized refund path.
- in_shop_pickup remains available at a zero delivery fee with a RES-XXXXXX pass and no rider/payout dependency.
- Legacy records remain queryable for authorized audit without enabling retired logistics workflows.

---

## Operating model

- Vendors configure their own delivery destinations and fee rules; valid LGA-specific pricing overrides state-level fallback pricing.
- Vendors add and manage their own riders; every active rider belongs to exactly one vendor and can only act on that vendor's orders.
- Vendors assign and reassign their orders. VMARKET records secure operational events but does not dispatch, schedule, or manage a fleet.
- Multi-vendor checkout remains supported as separate vendor orders with isolated fulfilment, fees, riders, evidence, and financial outcomes.
- Customer in_shop_pickup remains supported at zero delivery fee with a RES-XXXXXX pickup pass and no rider dependency.
- VMARKET retains payment verification, vendor verification, policy enforcement, evidence, dispute review, refund authorization, ledger integrity, vendor payout controls, and audit logs.
- Retire new platform-managed logistics-company dispatch, fleet affiliation, logistics-company fee payout, and rider-payout dependencies; preserve legacy records for read-only audit/reconciliation.
- Riders are operational only: no VMARKET rider wallet, payable, earnings ledger, bank account, or payout request.
- Restrict rider customer-contact data to name, delivery address, and a Call Customer action. Do not expose customer financial data, card details, platform commission, cashback, or vendor financial metrics.
- Delivery confirmation is evidence of handover only; it cannot approve a refund, change prices, or independently release funds.

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

## Customer Cashback and Loyalty Model — Authoritative Rule

VMARKET charges a **10% commission on the eligible vendor-order amount, including delivery fees**. It funds customer cashback of **5% of eligible product value only**, excluding delivery, from that commission. Cashback is not 5% of the delivery fee, not 5% of the entire cart when delivery is included, and not an additional deduction from the vendor's 90% allocation.

### Required calculations

1. Calculate eligible product value after applicable product-level discounts and approved allocation rules.
2. Calculate the 5% cashback only on eligible product value. Exclude delivery fees and any other amount the published cashback policy deems ineligible.
3. Calculate the 10% platform commission on the eligible vendor-order amount including delivery.
4. Treat cashback as a platform-funded loyalty cost within the platform commission. Never assign cashback funding to a rider or create a rider payable.
5. For multi-vendor checkout, calculate commission, eligible cashback, vendor allocation, delivery-fee treatment, and refunds per child vendor order. Do not use one cart-wide split that obscures vendor ownership.
6. On partial refund, reverse only cashback and commission attributable to the approved refunded eligible amount, using the original stored allocations and previous adjustments. Do not recalculate a historic order using current prices or rates.
7. Do not make cashback spendable until the approved maturation/return-window policy is satisfied. Refunds or rejected items must reverse the attributable pending/available cashback through an idempotent ledger operation.

Example: for ₦20,000 eligible product value plus ₦1,000 delivery, the eligible vendor-order total is ₦21,000; 10% platform commission is ₦2,100; cashback is 5% of ₦20,000 = ₦1,000; the vendor's pre-adjustment allocation is ₦18,900; platform commission remaining after cashback is ₦1,100 before other costs and adjustments. This is illustrative; checkout and ledger calculations must use exact server-side monetary arithmetic and the stored allocation.

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
   VMARKET creates a pending order with a 30-minute payment safety reservation window (`Order::expires_at = now()->addMinutes(30)`), without decrementing numeric stock quantities.
3. **Payment succeeds**:
   The pending reservation converts into a confirmed order (`order_status = 'confirmed'`, `vendor_settlement_status = 'held'`).
4. **Vendor sells the last item in their physical shop**:
   The vendor switches the toggle to **Out of Stock** (`is_in_stock = false`). VMARKET immediately rejects all new checkout attempts on the server.
5. **Payment fails or the 30-minute hold expires**:
   The order expires and transitions to `cancelled`, freeing up any pending in-store pickup reservations automatically.

### The Real-World Trade-Offs

With only a binary status, VMARKET does not inherently know whether a vendor has 1 physical phone or 100 phones on their shelf.

- **Concurrent Checkout Rush**: If a vendor has only 1 phone left and two customers attempt to buy it simultaneously, a simple status flag alone cannot prevent both orders without atomic backend holds. The backend must enforce server-side row locking (`lockForUpdate()`) and hold reservations to prevent race conditions.
- **Offline Physical Shop Sales**: If a vendor sells the last item over the counter in their brick-and-mortar shop but forgets to switch the toggle to "Out of Stock" in the VMARKET app, an online customer might place an order for an item that is physically gone.

### The VMARKET V1 Policy Matrix

| Feature | V1 Decision | Architectural Rationale |
| :--- | :--- | :--- |
| **Vendor Interface** | **In Stock / Out of Stock** | Zero cognitive burden; one-tap toggle removes operational friction for local shop owners. |
| **Storefront Presentation** | **In Stock / Out of Stock** | Clean customer messaging: "Available to Order" vs. "Out of Stock" (product remains visible). |
| **Internal Stock Quantities** | **Retained as Safeguards** | Order-level reservation timestamps and atomic row-level locks protect payment safety without requiring vendors to maintain numeric shelf counts. |
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

## Current Implementation Gap Audit — Business Rules vs Repository

**Scope:** static source inspection on the documentation branch, not a claim that runtime tests were executed. This is a file-level implementation plan; the business rules above are authoritative and the code changes remain pending.

| Priority | Current implementation evidence | Contradiction / risk | Required implementation change |
|---|---|---|---|
| P0 — financial allocation | `backend/app/Models/Order.php` retains `vendor_net_90`, `customer_cashback_5`, `platform_margin_5`, `logistics_fee_retained`, `platform_delivery_fee`, and a legacy 90/10 delivery split helper. `backend/app/Services/OrderSettlementService.php` and `backend/app/Services/DoorstepHandshakeService.php` post legacy fleet/vendor delivery-fee journal entries. | Financial formulas and beneficiaries differ by path; the older 90/5/5 merchandise split and separate 90/10 logistics split can conflict with the approved 10% commission on eligible vendor-order total and 5% product-only cashback. | Reconcile a single server-side allocation function per vendor order; define backward-compatible meaning for existing columns; add a migration/backfill/report before any column retirement; ensure one commission is applied and no new logistics-company payable is created. Do not mutate historic journals. |
| P0 — automatic fleet dispatch | `backend/app/Http/Controllers/Web/PortalController.php` around checkout/order creation (roughly lines 3720–3790) selects an online/active rider through `logistics_company_id`, collection points, and delivery lanes and persists `rider_id` / `logistics_company_id`. | Checkout assigns a rider before the vendor does so and creates a new-order logistics-company dependency. | Remove auto-assignment for new orders. New doorstep orders begin unassigned; vendor assigns an active rider belonging to that vendor after order readiness. Preserve legacy assignments only for historical orders. |
| P0 — delivery completion depends on fleet | `backend/app/Services/OrderSettlementService.php` around lines 907–930 validates rider/logistics-company status; around lines 1010–1070 it resolves a logistics company and credits its 90% wallet/journal entries. | A vendor rider can be rejected for not belonging to a logistics company, or delivery completion can create a logistics-company financial beneficiary. | Enforce active vendor affiliation and same-vendor order ownership; keep delivery confirmation operational; route vendor settlement only through the approved vendor/platform financial engine. No rider or logistics-company payout for new orders. |
| P0 — failed-delivery creates financial side effects | `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `recordDeliveryAttemptFailed()` (roughly lines 318–450) automatically changes the order to returned after two attempts, creates a return case, issues an available `CustomerWalletCredit`, and posts a logistics-company delivery-fee journal when a fleet is resolvable. | A rider's operational failure automatically triggers refund-like wallet credit and a logistics payout, bypassing vendor decision and authorized refund controls. | Change failure handling to append attempt/evidence and notify the vendor. Vendor chooses retry/reassign/return. Any return/refund is a separate authorized workflow with idempotent financial operation and reconciliation. No logistics payout. |
| P0 — rider privacy | `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `formatOrderForRider()` (roughly lines 203–255), currently returns `payment_status`, `delivery_fee`, and a raw customer phone field alongside operational data. `PortalController::riderCheckDeliveryStatus()` (roughly lines 2640–2670) also returns `delivery_fee`. | This violates the rule that riders must not see financial data; raw phone values are exposed rather than a purpose-limited Call Customer action. | Remove payment status and delivery-fee values from rider responses; provide a Call Customer action only for the active assigned order; expose only required operational order fields, customer name/address, and minimal call capability. Add response-contract tests for API and web endpoints, including history. |
| P0 — rider affiliation remains dual/optional | `backend/app/Models/RiderProfile.php` includes both `vendor_id` and `logistics_company_id`, plus `isFleetRider()`; migration `2026_10_19_000040_add_vendor_id_to_rider_profiles_table.php` adds nullable vendor/shop links. `RiderTerminalApiController::guardRiderWithLogistics()` accepts either vendor or logistics-company affiliation. | Fleet-only riders and dual-linked riders can still access the rider terminal; vendor linkage is not a database invariant. | Backfill active riders to one vendor; investigate ambiguous records; quarantine unresolved profiles; then enforce one vendor per active rider at service and schema level. Retain legacy association data for historical audit without allowing it to authorize new orders. |
| P0 — vendor-owned delivery zones and fees | `backend/app/Models/DeliveryLane.php`, `backend/database/seeders/InitialDeliveryLanesSeeder.php`, `backend/resources/views/admin/delivery_lanes.blade.php`, and checkout logic in `PortalController` still use platform/admin delivery lanes and logistics-company assignments. | The current source model is not yet the vendor-owned destination/fee configuration described by the business rules; delivery fee and rider selection remain coupled to platform lanes. | Add vendor-scoped destination/fee configuration with LGA-over-state precedence, validation, and checkout snapshots. Preserve old lane IDs for historical orders; don't allow them to auto-assign a fleet for new orders. |
| P1 — assignment/reassignment fencing | `VendorTerminalApiController::assignRiderToOrder()` (roughly lines 1206–1240) correctly scopes the order and rider to the vendor and checks rider active status, but directly sets `rider_id` without a transaction/row lock, explicit fulfilment/status guard, custody check, assignment history, or revocation token. | Concurrent assignments can race; a rider may be replaced after custody transfer without a formal handoff; stale riders may retain access based on old IDs. | Make assignment/reassignment transactional and locked; require doorstep delivery and valid status; record immutable assignment history; require custody resolution for in-transit reassignment; fence rider actions against current assignment and vendor ID at every endpoint. |
| P1 — active logistics portal and payout endpoints | `backend/routes/web.php` exposes `/logistics/*` dashboards, fleet rider management, collection points, wallet and payout request; `PortalController` includes logistics payout creation and admin disbursement/reconciliation; `backend/resources/views/logistics/wallet.blade.php` presents a 90% corporate delivery-fee wallet. | New platform-managed logistics operations and payout workflows remain active and visible. | Disable new use for new orders; keep a restricted read-only historical view for authorized audit. Block new payout requests/credits tied to vendor-managed orders. Do not delete historical payout data or journals. |
| P1 — rider earnings surface | `backend/routes/web.php` exposes `/rider/earnings`; `PortalController::riderEarningsPage()` and `backend/resources/views/rider/earnings.blade.php` describe parent fleet salary, allowances, fuel disbursements, and trip telemetry. | The UI represents riders as fleet-employed and creates an earnings concept inconsistent with an operational-only rider role. | Retire the earnings/compensation screen and route; replace it with delivery history/operational metrics only. Do not show or calculate compensation. |
| P1 — pickup pass vs order pickup OTP | `PortalController`'s separate `PickupReservation` flow creates and displays reservation codes; order fulfilment also stores `pickup_verification_code` and vendor/rider handover uses a six-digit OTP. `2026_10_19_000041_decouple_in_shop_pickup_and_delivery_statuses.php` separates some status values but does not by itself unify the pickup-pass contract. | The approved rule requires `in_shop_pickup` order fulfilment to use a `RES-XXXXXX` pass at zero fee; the repository has distinct reservation and order/OTP pathways that may not represent the same transaction. | Decide and implement one coherent order-linked pickup-pass lifecycle. Keep legacy product reservation passes working; ensure the paid order is zero-fee for delivery, no rider is assigned, only the owning shop/vendor can verify the pass, and pickup states cannot trigger delivery or rider payout logic. |
| P1 — pickup pass format | `backend/app/Models/PickupReservation.php::generateCode()` currently generates `RES-` plus eight hexadecimal characters, while the approved contract says `RES-XXXXXX` (six characters after the prefix). | The generated pass format does not exactly match the agreed format, and the reservation pass may be separate from the order fulfilment record. | Standardize the externally visible pass contract to exactly `RES-XXXXXX` for new pickup flows with collision checks and rate limiting; maintain lookup compatibility for old eight-character codes. Link the pass to the relevant order/reservation and enforce owning vendor/shop authorization. |
| P1 — COD privacy/authority | Checkout and rider projections contain payment-related fields; COD-specific vendor-controlled collection behavior is not clearly enforced by the inspected rider API contract. | A rider cannot be shown customer financial totals under the privacy rule, but a cash-collection task may require an amount. | Keep COD collection authority with the vendor. Until a separate exception is approved, do not display a payable amount or card/payment data to riders; if COD is enabled, specify a vendor-authorized collection/confirmation protocol that does not leak customer financial breakdowns. |
| P2 — vendor onboarding/approval | `VendorTerminalApiController::storeRider()`, `riders()`, and `updateRiderStatus()` already provide vendor-scoped rider creation/list/status APIs; `assignRiderToOrder()` exists. | Existing features are a foundation, but rider approval, active vendor status, unique vendor association, audit events, custody and stale-session revocation need full end-to-end verification. | Preserve the vendor-managed APIs; strengthen policy/middleware, audit logs, concurrency controls, notifications, and regression tests rather than creating a duplicate rider system. |

### Contradictions that must not be reintroduced

- Platform-managed dispatch vs vendor assignment: **vendor assignment wins** for new orders.
- Logistics-company 90% payout vs vendor-managed delivery: **no new logistics-company beneficiary or payout**.
- Rider earnings/payables vs operational-only rider: **no platform rider financial account or payout**.
- Automatic wallet credit on failed delivery vs authorized refund workflow: **failed attempt is operational only**.
- Rider-facing payment/delivery-fee fields vs customer financial privacy: **remove those fields from rider responses**.
- Zero-fee pickup vs generic positive delivery-fee validation: **zero-fee exception applies to in_shop_pickup only**.
- New policy vs legacy database records: **preserve old data; retire old new-order flows non-destructively**.

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
4. Require every enabled doorstep-delivery destination to have a valid positive fee.
5. Reject missing, negative, malformed, or client-controlled doorstep fees; do not reject the explicit zero-fee in_shop_pickup method.
6. Customer pickup must be modelled separately and use the RES-XXXXXX pickup pass, with no rider assignment.
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

1. Implement secure vendor-owned rider onboarding, activation/deactivation, order assignment, reassignment, pickup, arrival, delivery confirmation, failed-attempt, and return-to-vendor transitions.
2. Enforce that each active rider belongs to exactly one active vendor and can act only on that vendor's assigned orders.
3. Keep operational delivery state separate from financial settlement, refund approval, and vendor payout state.
4. Record actor, timestamps, evidence, custody, assignment history, and reason for every transition.
5. Keep the rider terminal as an operational tool; remove rider earnings, rider payout, and logistics-company dispatch/payout from new workflows.
6. Preserve legacy logistics records read-only for historical orders and audit. Do not hard-delete or rewrite old financial records.
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

Test vendor ownership, vendor-only rider affiliation, assignment/reassignment fencing, rider API privacy, zero rider/logistics payouts, zero-fee pickup pass, multi-vendor fulfilment isolation, invalid destinations, altered client totals, concurrent refunds, duplicate webhooks, uncertain gateway timeouts, partial refunds, vendor payouts, cashback/discount allocations, late payment confirmation, failed-delivery non-financial handling, delivery evidence, and historical logistics-order compatibility.

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
9. A business-rule contradiction matrix and file-by-file implementation gap audit for vendor-managed delivery, rider privacy, zero-fee in-shop pickup, and retirement of new logistics-company/rider financial flows.

Work in small, reviewable commits on a dedicated feature branch. Never silently change the business rules, delete historical financial data, or bypass the financial engine to make a test pass.
