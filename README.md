# Victorious MARKET

> **Authoritative Master Blueprint:** Victorious MARKET operates strictly under the [VICTORIOUS_MARKET_MASTER_BLUEPRINT.md](VICTORIOUS_MARKET_MASTER_BLUEPRINT.md). Built specifically for the economic, geographic, and physical realities of **Uyo, Akwa Ibom State**, this system replaces open-market chaos with a deterministic, high-margin retail and logistics engine.

---

## Official Positioning
**VICTORIOUS MARKET — YOUR TRUSTED ONLINE MARKET IN UYO, AKWA IBOM STATE.**  
*Trust first. Quality always. Accountability at every stage.*

Victorious MARKET is a centrally managed direct retailer. Customers buy from Victorious MARKET; suppliers remain private behind-the-scenes product sources linked internally to approved wholesale offers and order items. Victorious MARKET controls public listings, retail prices, customer payments, delivery coordination, customer support, refunds, and financial records. Delivery is fulfilled through accredited third-party logistics (3PL) companies whose riders must each be linked to an active logistics company.

---

## The 5 Foundational Laws

1. **The Plaza Centralization Law (Zero Distance Complexity):** Every supplier is physically situated within the **Plaza** commercial hub in central Uyo. Pickups originate from this unified cluster; customer delivery distances are governed strictly by the 8 arterial road corridors.
2. **SKU-Level Deterministic Sourcing:** All stock availability, pricing, and penalty rules operate strictly at the individual product (`product_id`) level.
3. **One Order, One Rider Accountability:** A customer order—even with items from multiple Plaza shops—is owned from pickup to doorstep by **one single delivery person**. Multi-rider order splitting is prohibited.
4. **The "Bulky Contagion" & Keke Isolation Law:** If an order contains even one bulky item (`is_bulky = true`), the *entire order* becomes classified as bulky, is forced onto a **Keke (Tricycle)**, and locks that Keke into a **100% exclusive trip** for that customer alone.
5. **Cryptographic Proof Over Trust:** Dual verification (**OTP + Live Photo Proof**) is enforced at every handoff point (per-shop pickup and customer doorstep).

---

## The 8 Master Uyo Road Corridors

All customer destinations and logistics dispatch operations are permanently mapped to one of the 8 Corridors:

| Corridor ID | Corridor Name | Key Landmarks & Feeder Axes |
| :--- | :--- | :--- |
| **COR-01** | **Oron Road** | Ewet Housing Estate, Shelter Afrique, Osongama, Ring Road 3 axis, Airport Rd junction |
| **COR-02** | **Nwaniba Road** | Use Offot, Akpan Andem Market axis, Brooks Street, Marina / Ibom Icon axis |
| **COR-03** | **Aka Road** | Four Lanes, Aka Etinan axis, State Secretariat annex, Ibom Plaza South edge |
| **COR-04** | **Abak Road** | Federal Housing Estate, Mechanic Village axis, Ukana Offot, Ibom Tropicana axis |
| **COR-05** | **Ikot Ekpene Road** | Itam Market / Flyover axis, Kalabari street, Ikot Oku Ikono junction |
| **COR-06** | **Itu Road** | Uyo Village Road, Obio Etoi, Northern Ring Road bypass axis |
| **COR-07** | **Ikpa Road** | Uniuyo Town Campus, Science Park axis, Ravine area, Barracks Road |
| **COR-08** | **Plaza (Central Core)** | Immediate Ibom Plaza surroundings, Wellington Bassey Way, Ikot Abasi Street |

---

## Core Operational Mechanics

### 1. Customer Discovery & Checkout
- **Unified Retailer Catalog:** Customers do not browse individual vendor shops.
- **Payload Classification:** Every product has an immutable `is_bulky` boolean flag.
- **Formula:** $\text{Delivery Fee} = \text{Corridor Base Fee} + (\text{Bulky Surcharge} \times \text{Total Bulky Units})$.
- **No Cash on Delivery (COD):** 100% upfront payment via Paystack card/transfer. Orders remain in `payment_pending` until the Paystack `charge.success` HMAC webhook is cryptographically verified.

### 2. Automated Supply Sourcing
Upon payment, the system evaluates all Plaza suppliers:
1. **Hard Filters:** KYC verified, active, located at `Plaza_Uyo`, stock in stock, not in 24h unavailability penalty, fewer than 5 active pending handovers.
2. **Consolidation Priority:** Prioritizes suppliers who can fulfill multiple items in the order to reduce rider stops.
3. **Wholesale Cost Sort:** Sorts by `cost_price ASC`.
4. **Reliability Tie-Breaker:** Breaks ties using historical `availability_rate DESC`.
5. **Rejection Penalty & Override:** Supplier unavailability logs a 24h penalty for that SKU; Admin can manually override supplier with an audited reason code.

### 3. Major-Road Logistics & Dispatch
- Accredited 3PL Fleet Managers manage dispatches across the 8 Road Corridor tabs.
- Programmatic validations enforce:
  - Single-Road Corridor Lock (riders only take orders on their designated corridor).
  - One Order, One Rider.
  - Bulky Contagion & Keke Isolation (standard bike trips can batch corridor small orders; bulky orders are forced to dedicated Keke trips).

### 4. Dual Verification Chain
- **Plaza Shop Pickup:** Live camera photo of item at counter + unique 4-digit Shop Pickup OTP from merchant.
- **Doorstep Handover:** Live camera photo at customer premises + secret Customer Delivery OTP.

### 5. Financial Ledger & Settlement Engine
- Automated double-entry ledger triggers upon `completed_verified`.
- **Supplier Payable:** Credited with locked `cost_price` snapshot per item. Settled daily/instantly.
- **3PL Logistics Payable:** Credited with pre-negotiated flat corridor rate per completed order trip (Bike vs Keke rate). Settled weekly (Mondays).
- **Victorious MARKET Net Profit:** $\sum(\text{Retail Price} - \text{Wholesale Cost}) + (\text{Delivery Fee Captured} - \text{3PL Corridor Payout})$.

---

## Authority & Documentation Map

1. [VICTORIOUS_MARKET_MASTER_BLUEPRINT.md](VICTORIOUS_MARKET_MASTER_BLUEPRINT.md) — The authoritative master architectural and operational blueprint.
2. [.ai/BUSINESS_RULES.md](.ai/BUSINESS_RULES.md) — System rules and engineering specifications aligned directly with the master blueprint.
3. [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md) — Paystack webhook handling, refund ceilings, and idempotent financial journals.
4. [SHARED_HOSTING_DEPLOYMENT_GUIDE.md](SHARED_HOSTING_DEPLOYMENT_GUIDE.md) — Server deployment procedures.
