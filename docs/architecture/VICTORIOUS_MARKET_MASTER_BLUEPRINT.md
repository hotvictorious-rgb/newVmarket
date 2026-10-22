> **Active-policy precedence (22 October 2026):** [Active Business Rules](../architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md) governs current business behaviour. This document retains detailed architecture/history, but any Plaza-only supplier assumption, binding pre-payment supplier assignment/reservation, public pickup reservation/stock hold, automatic refund on allocation failure, or 90%-of-customer-delivery-fee logistics rule is superseded. Do not treat this document as evidence that runtime code conforms.

# Victorious MARKET: Comprehensive Master Operational & Architectural Blueprint

This document provides an exhaustive, end-to-end engineering and operational breakdown of **Victorious MARKET**. Built specifically for the economic, geographic, and physical realities of **Uyo, Akwa Ibom State**, this system replaces open-market chaos with a deterministic, high-margin retail and logistics engine. Every component—from database schema decisions to physical Keke loading—is designed to protect working capital, eliminate inventory shrinkage, and maximize speed.

---

## 1. System Philosophy & Architectural Foundation

Most e-commerce ventures in Nigeria fail because they import foreign software assumptions: they rely on unmapped street coordinates, allow Cash-on-Delivery (COD) theft, permit riders to zig-zag aimlessly across cities, and assume open-market traders manage digital inventory like corporate warehouses.

Victorious MARKET enforces a **fail-closed, hyper-localized architecture** built on five foundational laws:

1. **The Plaza Centralization Law (Zero Distance Complexity):** Every supplier is physically situated within the **Plaza** commercial hub in central Uyo. Because all pickups originate from this single unified cluster, the platform requires zero Google Maps API distance calculations, zero dynamic per-kilometer routing fees, and zero complex geo-fencing. Distance from Plaza to any neighborhood is governed strictly by arterial road corridors.
2. **SKU-Level Deterministic Sourcing:** Suppliers in Plaza sell dozens of distinct categories. All stock availability, pricing, and penalty rules operate strictly at the individual product (`product_id`) level, never as a blanket shop-wide guess.
3. **One Order, One Rider Accountability:** A customer order—even if it contains five items sourced from three different approved supplier locations within Uyo—is owned from pickup to doorstep by **one single delivery person**. Splitting a single customer's order across multiple riders is prohibited.
4. **The "Bulky Contagion" & Keke Isolation Law:** If a customer order contains even one bulky item (`is_bulky = true`), the *entire order* becomes classified as bulky, is forced onto a **Keke (Tricycle)**, and locks that Keke into a **100% exclusive trip** for that customer alone.
5. **Cryptographic Proof Over Trust:** No item moves, no status changes, and no naira is paid out without dual verification (**OTP + Photo Proof**) at every single handoff point.

---

## 2. Phase 1: Customer Discovery, Cart Mechanics, & Checkout Gateway

### 2.1 Catalog Structure & Product Attributes

When customers browse `victoriousmarket.com.ng`, they do not see individual vendor shops; they see a unified, authoritative Victorious MARKET retail catalog. Behind the scenes, each product record contains critical operational metadata:

* **Retail Selling Price (`unit_price`):** The customer-facing price set by Victorious MARKET.
* **Payload Classification (`is_bulky`):** A strict boolean flag (`true` or `false`). Items like smartphones, chargers, blenders, and clippers are `false` (Standard/Small). Items like 4x6 virgin foam mattresses, standing gas cookers, large generators, and deep freezers are `true` (Bulky).
* **Active Supplier Backing:** A product only displays as purchasable on the frontend if at least one KYC-verified approved Uyo supplier currently has an active, unpenalized `in_stock` offer linked to it.

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

$$\text{Delivery Fee} = \text{Corridor Base Fee} + (\text{Bulky Surcharge} \times \text{Total Bulky Units})$$

$$\text{Grand Total} = \sum(\text{Item Retail Price} \times \text{Quantity}) + \text{Delivery Fee}$$

* **No Cash-on-Delivery (COD):** The customer must complete payment via **Paystack** (Card, Bank Transfer, USSD).
* **Webhook Verification Gate:** Completing payment on the browser does *not* trigger fulfillment. The order remains in `payment_pending` state until the backend server receives, validates, and verifies the cryptographic HMAC signature of the **Paystack `charge.success` webhook**. Only then does the order transition to `paid_processing` and wake up the Automated Sourcing Engine.

---

## 3. Phase 2: The Automated Product Sourcing Engine (Supply Side)

The exact millisecond Paystack confirms payment, the backend executes the **Multi-Product Consolidated Sourcing Pipeline**. The goal of this engine is threefold: **guarantee stock availability, minimize the number of Plaza shop stops for the rider, and maximize Victorious MARKET's retail profit margin.**

### 3.1 Step 1: Hard Pass/Fail Filters (SKU-Level Elimination)

For every distinct `product_id` in the customer's order, the engine queries the `supplier_offers` table and immediately disqualifies any supplier offer that fails any of these four mandatory checks:

1. **KYC & Geographic Boundary Check:** The supplier account must have `kyc_verified = true`, `status = 'active'`, and `approved supplier location within the Uyo LGA operating boundary`.
2. **Explicit Product Stock Flag:** The supplier's specific offer for *that exact product* must be marked `stock_status = 'in_stock'` and have sufficient quantity.
3. **The 24-Hour Unavailability Penalty (Self-Healing Shield):** The engine queries the `supplier_unavailability_logs` table. Did this supplier mark *this specific product* as "Out of Stock / Unavailable" within the last 24 hours? If **Yes**, the offer is **discarded**. This stops the system from repeatedly sending orders to a merchant who ran out of stock earlier that day.
4. **Pending Handover Overload Cap (Bottleneck Prevention):** The engine counts how many active order items are currently sitting with this supplier in `assigned` or `confirmed` status waiting for rider pickup. If the supplier has **5 or more pending handovers** ($\text{Pending Handovers} \ge 5$), they are **temporarily discarded**. This ensures orders never pile up in a slow or crowded shop.

### 3.2 Step 2: Multi-Product Consolidation & Smart Sorting

Once ineligible offers are eliminated, the engine evaluates the surviving suppliers to find the optimal assignment:

* **Rule A — Multi-Product Shop Consolidation (Reducing Rider Stops):**
If a customer ordered 3 different products (e.g., a phone, a power bank, and a memory card), the engine checks which surviving suppliers have **all 3** (or **2 out of 3**) items in stock. It assigns a high **Consolidation Priority Weight** to suppliers who can fulfill multiple items from the same order. Having a rider stop at 1 shop instead of 3 cuts pickup time by 70% and drastically reduces operational friction.
* **Rule B — Primary Economic Sort (Lowest Wholesale Price):**
Among suppliers who meet the consolidation threshold (or for single-product orders), the engine sorts strictly by `cost_price ASC` (Lowest Wholesale Price first). If Supplier A sells the item for ₦14,000 wholesale and Supplier B sells it for ₦14,500, Supplier A wins—automatically locking in an extra ₦500 margin for Victorious MARKET.
* **Rule C — Secondary Reliability Tie-Breaker (Availability Rate):**
If two suppliers offer the exact same wholesale price and consolidation coverage, the engine breaks the tie using `availability_rate DESC` (calculated from their historical successful handovers divided by total assigned orders). The most reliable trader gets the sale.

### 3.3 Step 3: Supplier Notification, Rejection Loop, & Admin Override

1. **Automated Assignment:** The winning supplier(s) receive an instant dashboard alert and SMS/WhatsApp ping showing the item(s) ordered and the locked wholesale payout price.
2. **If Chosen Supplier Marks "Unavailable":** If a supplier realizes their physical shelf is actually empty and clicks *"Mark Unavailable"*:
* The system immediately logs a **24-hour penalty** against that supplier for that specific SKU.
* The system decreases their historical **Availability Rate** score.
* The system triggers a high-priority alert on the **Admin Operations Dashboard** (and can either auto-reallocate to the second-best surviving supplier or hold for Admin intervention).
3. **The Manual Admin Override:** At any point before rider pickup, an authorized Victorious MARKET Admin can click **"Override Supplier"**, select any verified Plaza shop from a dropdown, and input a **Mandatory Audit Reason Code** (e.g., *"Original supplier shop shutter closed temporarily"*). The database updates the cost price snapshot, logs the Admin's User ID, and preserves ledger integrity.

---

## 4. Phase 3: Major-Road Logistics & Dispatch Architecture (Delivery Side)

Once every item in the customer order is confirmed ready by the approved Uyo supplier(s), the order moves to the **Logistics Dispatch Queue**. Victorious MARKET does not manage random freelance riders; it integrates accredited **Third-Party Logistics (3PL) Companies** operating in Uyo.

### 4.1 The 3PL Partner Portal & The 8 Road Tabs

When a 3PL Fleet Manager logs into their dedicated dashboard, they do not see a chaotic list of addresses. They see **8 Distinct Operational Tabs** corresponding to the 8 Uyo Road Corridors:
`[ Oron Rd ]` `[ Nwaniba Rd ]` `[ Aka Rd ]` `[ Abak Rd ]` `[ Ikot Ekpene Rd ]` `[ Itu Rd ]` `[ Ikpa Rd ]` `[ Plaza ]`

Inside each tab, ready orders are grouped clearly with two vital badges:

* **Number of Plaza Pickup Stops** (e.g., *1 Stop* or *2 Stops*)
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

### 5.1 Stage 1: Plaza Shop Pickup Protocol (Per Stop)

Suppose Rider Emmanuel (driving a Keke) is assigned Order #800, which has 2 items sourced from two different approved supplier locations within Uyo (Shop Alpha and Shop Beta):

1. **Arrival at Shop Alpha:** Emmanuel arrives at Shop Alpha's table. The merchant hands over Item 1.
2. **Shop Pickup Photo #1:** Emmanuel uses his app to snap a clear, live camera photo of Item 1 at the shop counter (gallery uploads are disabled; live camera capture is forced).
3. **Shop Pickup OTP #1:** Shop Alpha reads out the unique 4-digit **Shop Pickup OTP** displayed on their supplier dashboard for that specific order item. Emmanuel enters it into his app.
* *System Action:* The database timestamps the handoff, shifts liability for Item 1 from Shop Alpha to the 3PL company, and marks Shop Alpha's portion as `picked_up`.
4. **Arrival at Shop Beta:** Emmanuel moves 50 meters down the plaza to Shop Beta, loads the bulky item into his Keke, snaps **Shop Pickup Photo #2**, and enters **Shop Pickup OTP #2**.
5. **Transit Unlock:** Only after *every single shop stop* for Order #800 has a verified Pickup OTP and Photo does the order status transition to `in_transit_on_corridor`, unlocking the customer's doorstep details.

### 5.2 Stage 2: Doorstep Handover Protocol

1. **Arrival on Corridor:** Emmanuel drives straight down the designated road corridor (e.g., *Oron Road*) to the customer's address.
2. **Doorstep Photo Proof:** Before handing over the final package, Emmanuel snaps a mandatory, timestamped **Doorstep Delivery Photo** showing the intact items at the customer's premises.
3. **Customer Delivery OTP:** The customer recites their secret **Delivery OTP** (sent to their phone via SMS/email when the order went into transit). Emmanuel inputs the OTP into his app.
4. **Instant Cryptographic Closure:** The backend verifies the OTP match, validates the uploaded photo proof, and transitions the order status to `completed_verified`.

---

## 6. Phase 5: Fail-Closed Financial Ledger & Settlement Engine

Victorious MARKET never relies on manual spreadsheet calculations to figure out who is owed what. The platform runs an automated double-entry internal ledger that executes the moment an order reaches `completed_verified`.

### 6.1 How the Money Splits (Per Completed Order)

When a customer pays via Paystack at checkout, 100% of the funds sit in the Victorious MARKET settlement account. The moment the final Doorstep Delivery OTP is entered, the ledger splits the revenue into three distinct buckets:

1. **Supplier Payable Ledger (Paid Per Item Fulfilled):**
Each approved Uyo supplier who contributed an item to the order has their wallet/payout ledger credited with the exact locked `cost_price` snapshot recorded at the moment of allocation.
2. **3PL Logistics Payable Ledger (Paid Per Customer Order Trip):**
The 3PL company is **never** paid per item or per shop stop; they are paid **one pre-negotiated flat corridor rate per completed customer order trip**, based on the road corridor and vehicle type:
* *Standard Bike Order Trip:* Credited at the flat Bike rate for that specific road corridor.
* *Isolated Bulky Keke Order Trip:* Credited at the higher flat Keke rate for that road corridor (funded by the customer's bulky surcharge at checkout).
3. **Victorious MARKET Net Revenue Ledger:**
The platform automatically retains:

$$\text{Net Profit} = \sum(\text{Retail Price} - \text{Supplier Wholesale Cost}) + (\text{Customer Delivery Fee Collected} - \text{3PL Corridor Payout})$$

### 6.2 Settlement Cycles

* **approved Uyo suppliers:** Settled instantly or via daily automated bank transfers for all verified picked-up and delivered orders, keeping their cash turnover ultra-fast so they prioritize Victorious MARKET above walk-in credit buyers.
* **3PL Logistics Companies:** Settled on a clean, automated weekly batch cycle (e.g., every Monday morning), accompanied by an itemized digital manifest showing every completed Order ID, Road Corridor, Vehicle Type, and OTP timestamp.

---

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
* **approved Uyo supplier Portal:** Ultra-simple mobile-first screen to toggle stock, view assigned orders, read out Shop Pickup OTPs, or click "Unavailable" (triggering the 24h penalty).
* **3PL Logistics Portal & Rider View:** The 8 Road Corridor tabs, vehicle assignment validation, and camera/OTP capture screens.
* **Victorious Admin Command Center:** Live order monitor, manual supplier override modal, stuck-order alerts, and weekly payout reconciliation dashboard.
