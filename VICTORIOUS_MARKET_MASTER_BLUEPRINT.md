# Victorious MARKET: Comprehensive Master Operational & Architectural Blueprint

> **Binding active business rules:** [Active Business Rules](docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md). This blueprint must be read consistently with that policy; any older Plaza-only, pre-payment assignment, flat logistics-rate, or automatic-refund wording is superseded by the active rules. Documentation changes do not mean code is implemented or verified.

This document provides an exhaustive, end-to-end engineering and operational breakdown of **Victorious MARKET**. Built specifically for the economic, geographic, and physical realities of **Uyo, Akwa Ibom State**, this system replaces open-market chaos with a deterministic, high-margin retail and logistics engine. Every component—from database schema decisions to physical Keke loading—is designed to protect working capital, eliminate inventory shrinkage, and maximize speed.

---

## 1. System Philosophy & Architectural Foundation

Most e-commerce ventures in Nigeria fail because they import foreign software assumptions: they rely on unmapped street coordinates, allow Cash-on-Delivery (COD) theft, permit riders to zig-zag aimlessly across cities, and assume open-market traders manage digital inventory like corporate warehouses.

Victorious MARKET enforces a **fail-closed, hyper-localized architecture** built on five foundational laws:

1. **The Approved Uyo Supplier Location Rule:** Every supplier must have one admin-approved shop or pickup location anywhere within the approved Uyo operating boundary. Plaza is one possible location, not a mandatory hub. The eight delivery corridors classify customer destinations and company tariffs; they do not require suppliers to be located in Plaza.
2. **SKU-Level Deterministic Sourcing:** Approved suppliers at locations throughout Uyo may offer products. Stock availability, pricing and penalty rules operate at the individual product (`product_id` / supplier offer) level, never as a blanket shop-wide guess.
3. **One Order, One Rider Accountability:** A customer order—even if it contains items sourced from multiple approved supplier locations—is owned from pickup to doorstep by **one single delivery person**. Splitting a single customer's order across multiple riders is prohibited.
4. **The "Bulky Contagion" & Keke Isolation Law:** If a customer order contains even one bulky item (`is_bulky = true`), the *entire order* becomes classified as bulky, is forced onto a **Keke (Tricycle)**, and locks that Keke into a **100% exclusive trip** for that customer alone.
5. **Cryptographic Proof Over Trust:** No item moves, no status changes, and no naira is paid out without dual verification (**OTP + Photo Proof**) at every single handoff point.

---

## 2. Phase 1: Customer Discovery, Cart Mechanics, & Checkout Gateway

### 2.1 Catalog Structure & Product Attributes

When customers browse `victoriousmarket.com.ng`, they do not see individual vendor shops; they see a unified, authoritative Victorious MARKET retail catalog. Behind the scenes, each product record contains critical operational metadata:

* **Retail Selling Price (`unit_price`):** The customer-facing price set by Victorious MARKET.
* **Payload Classification (`is_bulky`):** A strict boolean flag (`true` or `false`). Items like smartphones, chargers, blenders, and clippers are `false` (Standard/Small). Items like 4x6 virgin foam mattresses, standing gas cookers, large generators, and deep freezers are `true` (Bulky).
* **Active Supplier Backing and Listing Lifecycle:** A product is purchasable only while at least one active, verified supplier at an admin-approved Uyo location has an eligible, unpenalized `in_stock` offer. If all eligible offers are out of stock or otherwise ineligible, automatically hide the listing from the customer-facing storefront. An authorized admin may manually delist it at any time. Recompute visibility when stock changes; a listing may return only if an eligible offer exists and admin has not manually delisted it. Hiding a listing must not delete product, offer or historical order records. Listing visibility is separate from allocation to a particular paid order.

### 2.2 Address Capture & The 8 Major Uyo Road Corridors

At checkout, the customer is never asked to drop an inaccurate GPS pin. Instead, they type their street/house description and select their nearest area from a curated dropdown of **Uyo Landmarks**.

The backend database permanently links every landmark to one of **8 Master Uyo Road Corridors**:

| Road Corridor ID | Primary Arterial Road | Mapped Landmarks & Feeder Axes Included |
| --- | --- | --- |
| **COR-01** | **Oron Road** | Ewet Housing Estate, Shelter Afrique, Osongama, Ring Road 3 axis, Airport Road junction |
| **COR-02** | **Nwaniba Road** | Use Offot, Akpan Andem Market axis, Brooks Street, Marina / Ibom Icon axis |
| **COR-03** | **Aka Road** | Four Lanes, Aka Etinan axis, State Secretariat annex, Ibom Plaza South edge |
| **COR-04** | **Abak Road** | Federal Housing Estate, Mechanic Village axis, Ukana Offot, Ibom Tropicana axis |
| **COR-05** | **Ikot Ekpene Road** | Itam Market / Flyover axis, Kalabari street, Ikot Oku Ikono junction |
| **COR-06** | **Itu Road** | Uyo Village Road, Obio Etoi, Northern Ring Road bypass axis |
| **COR-07** | **Ikpa Road** | Uniuyo Town Campus, Science Park axis, Ravine area, Barracks Road |
| **COR-08** | **Plaza (Central Core)** | Immediate Ibom Plaza surroundings, Wellington Bassey Way, Ikot Abasi Street |

### 2.3 Immutable Fee Calculation & Paystack Lock

Once the landmark is selected, the checkout engine inspects the cart and calculates the exact payable total using an immutable formula:

$\text{Customer Delivery Fee} = \text{Configured Flat Customer Base Fee Per Order} + (\text{Customer Bulky Surcharge} \times \text{Applicable Bulky Units})$

$$\text{Grand Total} = \sum(\text{Item Retail Price} \times \text{Quantity}) + \text{Delivery Fee}$$

* **No Cash-on-Delivery (COD):** The customer must complete payment via **Paystack** (Card or standard bank transfer, the approved launch rails).
* **Webhook Verification Gate:** Completing payment on the browser does *not* trigger fulfillment. The order remains in `payment_pending` state until the backend server receives, validates, and verifies the cryptographic HMAC signature of the **Paystack `charge.success` webhook**. Only then does the order transition to `paid_processing` and wake up the Automated Sourcing Engine.

---

## 3. Phase 2: The Automated Product Sourcing Engine (Supply Side)

Only after the backend independently verifies successful Paystack payment does it execute the **Multi-Product Consolidated Sourcing Pipeline**. Before payment, any cart or feasibility check is non-binding: it must not assign or reserve suppliers, create supplier payables, or send binding order notifications. After payment, revalidate current stock and eligibility before allocating. The goal is to maximize fulfilment success, minimize the number of approved supplier pickup locations, and control wholesale cost.

### 3.1 Step 1: Hard Pass/Fail Filters (SKU-Level Elimination)

For every distinct `product_id` in the customer's order, the engine queries the `supplier_offers` table and immediately disqualifies any supplier offer that fails any of these four mandatory checks:

1. **KYC & Geographic Boundary Check:** The supplier account must have `kyc_verified = true`, `status = 'active'`, and an admin-approved operating/pickup location within Uyo.
2. **Explicit Product Stock Flag:** The supplier's specific offer for *that exact product* must be marked `stock_status = 'in_stock'` and have sufficient quantity.
3. **The 24-Hour Unavailability Penalty (Self-Healing Shield):** The engine queries the `supplier_unavailability_logs` table. Did this supplier mark *this specific product* as "Out of Stock / Unavailable" within the last 24 hours? If **Yes**, the offer is **discarded**. This stops the system from repeatedly sending orders to a merchant who ran out of stock earlier that day.
4. **Pending Handover Overload Cap (Bottleneck Prevention):** The engine counts how many active order items are currently sitting with this supplier in `assigned` or `confirmed` status waiting for rider pickup. If the supplier has **5 or more pending handovers** ($\text{Pending Handovers} \ge 5$), they are **temporarily discarded**. This ensures orders never pile up in a slow or crowded shop.

### 3.2 Step 2: Multi-Product Consolidation & Smart Sorting

Once ineligible offers are eliminated, the engine evaluates the surviving suppliers to find the optimal assignment:

* **Rule A — Multi-Product Shop Consolidation (Reducing Rider Stops):**
For the complete order, evaluate feasible supplier plans by the binding priority order: (1) minimize the number of distinct approved pickup locations needed to fulfil the whole order; (2) among equally consolidated plans, minimize total wholesale cost; (3) prefer better documented fulfilment reliability; and (4) use a stable deterministic tie-break. Do not use an arbitrary consolidation weight or a fixed 2-of-3 threshold that can override this priority order.
* **Rule B — Primary Economic Sort (Lowest Wholesale Price):**
Wholesale cost is the second priority only after comparing the number of pickup locations required for the feasible whole-order plan. A cheaper individual offer must not defeat a plan that uses fewer pickup locations.
* **Rule C — Secondary Reliability Tie-Breaker (Availability Rate):**
If two suppliers offer the exact same wholesale price and consolidation coverage, the engine breaks the tie using `availability_rate DESC` (calculated from their historical successful handovers divided by total assigned orders). The most reliable trader gets the sale.

### 3.3 Step 3: Supplier Notification, Rejection Loop, & Admin Override

1. **Automated Assignment:** The winning supplier(s) receive an instant dashboard alert and SMS/WhatsApp ping showing the item(s) ordered and the locked wholesale payout price.
2. **If Chosen Supplier Marks "Unavailable":** If a supplier realizes their physical shelf is actually empty and clicks *"Mark Unavailable"*:
* The system immediately logs a **24-hour penalty** against that supplier for that specific SKU.
* The system decreases their historical **Availability Rate** score.
* The system triggers a high-priority alert on the **Admin Operations Dashboard** (and can either auto-reallocate to the second-best surviving supplier or hold for Admin intervention).
3. **The Manual Admin Override:** At any point before rider pickup, an authorized Victorious MARKET Admin can click **"Override Supplier"**, select any eligible verified supplier from an approved Uyo shop/pickup location, and input a **Mandatory Audit Reason Code** (e.g., *"Original supplier shop shutter closed temporarily"*). The system preserves the original assignment and financial snapshots, records the admin's actor/reason/time, and appends balanced compensating entries for any changed supplier payable or cost delta. Do not overwrite the original financial fact or silently mutate a posted journal.

---

## 4. Phase 3: Major-Road Logistics & Dispatch Architecture (Delivery Side)

Once every item in the customer order is confirmed ready by the approved supplier(s), the order moves to the **Logistics Dispatch Queue**. Victorious MARKET does not manage random freelance riders; it integrates accredited **Third-Party Logistics (3PL) Companies** operating in Uyo.

### 4.1 The 3PL Partner Portal & The 8 Road Tabs

When a 3PL Fleet Manager logs into their dedicated dashboard, they do not see a chaotic list of addresses. They see **8 Distinct Operational Tabs** corresponding to the 8 Uyo Road Corridors:
`[ Oron Rd ]` `[ Nwaniba Rd ]` `[ Aka Rd ]` `[ Abak Rd ]` `[ Ikot Ekpene Rd ]` `[ Itu Rd ]` `[ Ikpa Rd ]` `[ Plaza ]`

Inside each tab, ready orders are grouped clearly with two vital badges:

* **Number of Supplier Pickup Stops** (e.g., *1 Stop* or *2 Stops*)
* **Required Vehicle Type** (`BIKE - Standard` or `KEKE - Bulky Isolated`)

### 4.2 The Three Unbreakable Logistics Validation Laws

When the 3PL Manager assigns an order (or batch of orders) to a specific rider in their fleet, the backend runs `validateLogisticsAssignment()` and programmatically blocks any violation of these three rules:

1. **Law 1: The Single-Road Corridor Lock (Zero Cross-Road Mixing):**
A rider assigned to an active manifest on **Aka Road** can *only* be assigned additional orders that also belong to **Aka Road**. If the manager tries to attach an **Ikpa Road** order to that same rider's trip, the system throws a hard error. Riders travel straight down a single artery, dropping parcels linearly without ever zig-zagging across Uyo.
2. **Law 2: One Order, One Rider Consolidation:**
Even if Customer Order #502 requires picking up a phone charger from Shop A and a mattress from Shop B, the system locks the **entire Order #502** to a single rider ID. Two riders can never split a single customer's order.
3. **Law 3: The Bulky Contagion & Keke Isolation Matrix:**
The system inspects every item inside the customer's order:
* **Scenario A — Pure Small-Item Order (`has_bulky = false`):** The order is assigned to a **Motorcycle (Bike)**. Furthermore, that same Bike rider *is permitted* to carry small-item orders for **multiple different customers** (e.g., Order #501, Order #503, Order #504) on the exact same trip, provided all of them are heading down the **exact same road corridor**.
* **Scenario B — Order Contains a Bulky Item (`has_bulky = true`):** Even if the customer ordered 4 tiny items and just **1 bulky item** (e.g., three phone cases and one 4x6 foam mattress), the **Bulky Contagion Rule** fires. The entire order becomes classified as **Bulky**. Motorcycles are strictly banned; the system forces assignment to a **Keke (Tricycle)**. Furthermore, the **Isolation Rule** locks that Keke trip: **no other customer's order can be added to that Keke until that trip is completed.** The Keke acts as that customer's private delivery vehicle.

---

## 5. Phase 4: The Multi-Shop Pickup & Doorstep Verification Chain

To eliminate inventory theft, "rider said / supplier said" arguments, and fake delivery claims, the mobile interface enforces a multi-stage verification chain.

### 5.1 Stage 1: Approved Supplier Location Pickup Protocol (Per Stop)

Suppose Rider Emmanuel (driving a Keke) is assigned Order #800, which has 2 items sourced from two different approved supplier locations (Shop Alpha and Shop Beta):

1. **Arrival at Shop Alpha:** Emmanuel arrives at Shop Alpha's table. The merchant hands over Item 1.
2. **Shop Pickup Photo #1:** Emmanuel uses his app to snap a clear, live camera photo of Item 1 at the shop counter (gallery uploads are disabled; live camera capture is forced).
3. **Shop Pickup OTP #1:** Shop Alpha reads out the unique 4-digit **Shop Pickup OTP** displayed on their supplier dashboard for that specific order item. Emmanuel enters it into his app.
* *System Action:* The database timestamps the handoff, shifts liability for Item 1 from Shop Alpha to the 3PL company, and marks Shop Alpha's portion as `picked_up`.
4. **Arrival at Shop Beta:** Emmanuel travels to the second approved supplier pickup location, loads the bulky item into his Keke, snaps **Shop Pickup Photo #2**, and enters **Shop Pickup OTP #2**.
5. **Transit Unlock:** Only after *every single shop stop* for Order #800 has a verified Pickup OTP and Photo does the order status transition to `in_transit_on_corridor`, unlocking the customer's doorstep details.

### 5.2 Stage 2: Doorstep Handover Protocol

1. **Arrival on Corridor:** Emmanuel drives straight down the designated road corridor (e.g., *Oron Road*) to the customer's address.
2. **Doorstep Photo Proof:** Before handing over the final package, Emmanuel snaps a mandatory, timestamped **Doorstep Delivery Photo** showing the intact items at the customer's premises.
3. **Customer Delivery OTP:** The customer recites their secret **Delivery OTP** (sent to their phone via SMS/email when the order went into transit). Emmanuel inputs the OTP into his app.
4. **Instant Cryptographic Closure:** The backend verifies the OTP match, validates the uploaded photo proof, and transitions the order status to `completed_verified`.

---

### 5.3 Paid-Order Fulfilment Exceptions — Admin-Controlled

If payment has been verified but no supplier can fulfil the order, do not automatically refund solely because allocation failed. Place the order in a visible admin-managed exception state, alert the admin and track aging/escalation. Authorized admin may reassign to another eligible supplier, source an acceptable alternative, hold fulfilment, cancel or initiate a refund according to policy. Do not silently substitute products or impose extra charges; obtain customer consent where terms change. A refund is not complete until confirmed by the payment provider. Preserve actor, reason, timestamp, amount, payment/refund reference and status in the audit trail. All retry/reassignment/refund actions must be idempotent.

---

## 6. Phase 5: Financial ledger, payables and reconciliation

Victorious MARKET must preserve an auditable, idempotent financial ledger. Ledger automation must not be confused with automatic approval or immediate bank disbursement. This section follows [Active Business Rules](docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md); if this older blueprint's wording conflicts, the active rules prevail.

### 6.1 Separate money paths for each completed order

Customer payment is recorded only after trusted Paystack verification. A successful payment does not itself prove delivery, supplier payable eligibility, or logistics payable eligibility.

1. **Supplier payable:** Link the immutable agreed wholesale-cost snapshot to each sourced order item. Supplier stock acceptance, availability confirmation, allocation or pickup alone does not make the payable due. Eligibility requires verified delivery, or an explicitly authorized completion exception, followed by reconciliation. Do not treat supplier payables as vendor commissions or automatically credit a supplier wallet at allocation/capture.
2. **Logistics-company payable:** Calculate the payable from the assigned approved logistics company's admin-configured base payable for the applicable corridor, plus that company's configured bulky-unit surcharge multiplied by the applicable bulky-unit count. This is a separate tariff schedule from the customer delivery charge. Never derive it from a percentage of customer delivery revenue. Snapshot company, corridor, tariff/version, base, bulky quantity/rate and total at assignment; later tariff changes must not silently rewrite it. If payable exceeds delivery revenue, record the difference as a Victorious MARKET-funded subsidy/expense. The logistics company—not automatically the rider—is the payee.
3. **Victorious MARKET margin and expenses:** Keep merchandise revenue, supplier purchase cost, customer delivery revenue, logistics cost, gateway fees, discounts, refunds, cashback liability, subsidy and operating expenses distinct. Merchandise spread is not net profit until attributable costs and liabilities are reconciled.

### 6.2 Supplier and logistics payment controls

A payable becoming eligible is not the same as a payment being approved, initiated, or confirmed. Every payout/payment operation must follow the applicable owner-approved authorization, limits, idempotency, reconciliation and audit requirements. Do not assume instant supplier transfers, daily automatic supplier settlement, weekly logistics-company transfer, or automatic wallet credit unless a separate current policy explicitly authorizes it. Provider timeout remains uncertain until reconciled; payout completion requires authoritative provider confirmation. Preserve legacy vendor earning/payout records as historical records and do not relabel them as supplier costs without an approved accounting migration.

## 7. Master Engineering Checklist (What You & The AI Will Build/Test)

To ensure your AI coding partner builds this system without cutting corners, here are the exact software modules that make up the full application:

1. **Database Schema & Migrations:**
* `products` (with `is_bulky` boolean and retail price)
* `landmarks` & `road_corridors` (the 8 Uyo roads mapped tightly)
* `supplier_offers` (linking `supplier_id`, `product_id`, `cost_price`, `stock_status`)
* `supplier_unavailability_logs` (tracking the 24-hour SKU penalty timestamp)
* `orders` & `order_items` (storing cost snapshots, corridor ID, vehicle requirement, `is_isolated_trip` flag)
* `fulfillment_proofs` (storing shop pickup OTPs/photos and doorstep delivery OTPs/photos)
* `ledger_entries` (fail-closed financial tracking for Suppliers, 3PLs, and Platform Margin)

2. **Core Backend Services:**
* `PaystackWebhookService` (HMAC verification and idempotency lock)
* `ProductSourcingEngine` (4 hard filters + multi-item shop consolidation + price/reliability sorting)
* `AdminSupplierOverrideService` (manual reassignment with mandatory audit logging)
* `LogisticsValidationService` (enforcing single-road lock, one-rider-per-order, and Bulky Contagion Keke isolation)
* `HandoverVerificationService` (validating shop pickup and doorstep OTPs + image uploads before unlocking ledger payouts)

3. **Role-Based Portals (UI/Frontend Views):**
* **Customer Storefront:** Fast catalog, landmark-driven checkout, Paystack integration, and OTP tracking page.
* **Approved Supplier Portal:** Ultra-simple mobile-first screen for each approved supplier to manage its offers and stock, view assigned orders, read out Shop Pickup OTPs, or click "Unavailable" (triggering the product-specific 24-hour cooldown).
* **3PL Logistics Portal & Rider View:** The 8 Road Corridor tabs, vehicle assignment validation, and camera/OTP capture screens.
* **Victorious Admin Command Center:** Live order monitor, manual supplier override modal, stuck-order alerts, and weekly payout reconciliation dashboard.
