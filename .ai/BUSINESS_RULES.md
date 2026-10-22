# Victorious MARKET — Authoritative Business & Operational Rules

> **Binding Authority:** This file is strictly derived from and subordinate to [VICTORIOUS_MARKET_MASTER_BLUEPRINT.md](../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md). All runtime code, database queries, checkout flows, sourcing algorithms, logistics validations, and financial ledgers must strictly align with the 5 Foundational Laws and Phase 1–5 specifications defined herein.

---

## 1. The Five Foundational Laws

1. **The Plaza Centralization Law (Zero Distance Complexity):** Every supplier is physically situated within the **Plaza** commercial hub in central Uyo. Because all pickups originate from this single unified cluster, distance from Plaza to any neighborhood is governed strictly by the 8 arterial road corridors.
2. **SKU-Level Deterministic Sourcing:** All stock availability, pricing, and penalty rules operate strictly at the individual product (`product_id`) level, never as a blanket shop-wide guess.
3. **One Order, One Rider Accountability:** A customer order—even if it contains multiple items sourced from different Plaza shops—is owned from pickup to doorstep by **one single delivery person**. Splitting a single customer's order across multiple riders is strictly prohibited.
4. **The "Bulky Contagion" & Keke Isolation Law:** If a customer order contains even one bulky item (`is_bulky = true`), the *entire order* becomes classified as bulky, is forced onto a **Keke (Tricycle)**, and locks that Keke into a **100% exclusive trip** for that customer alone.
5. **Cryptographic Proof Over Trust:** No item moves, no status changes, and no naira is paid out without dual verification (**OTP + Photo Proof**) at every single handoff point (per-shop pickup and customer doorstep).

---

## 2. Catalog & Checkout Invariants

1. **Unified Retailer Catalog:** Customers do not browse individual vendor shops. Victorious MARKET is the sole customer-facing retailer of record.
2. **Payload Classification (`is_bulky`):** Every product has an immutable boolean `is_bulky`. Small items (phones, chargers, blenders) are `false`; large items (mattresses, cookers, generators, freezers) are `true`.
3. **Address Capture via 8 Major Road Corridors:** Customers select from curated Uyo landmarks permanently mapped to one of the 8 Corridors:
   - `COR-01`: Oron Road
   - `COR-02`: Nwaniba Road
   - `COR-03`: Aka Road
   - `COR-04`: Abak Road
   - `COR-05`: Ikot Ekpene Road
   - `COR-06`: Itu Road
   - `COR-07`: Ikpa Road
   - `COR-08`: Plaza (Central Core)
4. **Immutable Delivery Fee Formula:**
   $$\text{Delivery Fee} = \text{Corridor Base Fee} + (\text{Bulky Surcharge} \times \text{Total Bulky Units})$$
5. **No Cash on Delivery (COD) & Webhook Gate:** Payment must be completed via Paystack. Orders remain in `payment_pending` until the server verifies the cryptographic HMAC signature of the `charge.success` webhook.

---

## 3. Automated Sourcing Pipeline

Triggered immediately upon verified payment:
1. **Hard Pass/Fail Filters (SKU-Level):**
   - Supplier must be KYC verified, active, and located at `Plaza_Uyo`.
   - Supplier offer must be marked `stock_status = 'in_stock'` with sufficient quantity.
   - **24-Hour Unavailability Shield:** Disqualify if the supplier marked this specific SKU unavailable within the last 24 hours.
   - **Pending Handover Cap:** Disqualify if the supplier currently has 5 or more pending handovers waiting for rider pickup.
2. **Consolidation & Smart Sorting:**
   - **Rule A (Consolidation Priority):** Prioritize suppliers who can fulfill multiple items in the same order to minimize shop stops.
   - **Rule B (Wholesale Price Sort):** Sort surviving suppliers by `cost_price ASC`.
   - **Rule C (Reliability Tie-Breaker):** Break ties using `availability_rate DESC`.
3. **Rejection & Admin Override:**
   - If a supplier marks an item unavailable: log 24h penalty for that SKU, decrement availability score, alert Admin.
   - Admin can override supplier at any time before pickup with a mandatory audit reason code.

---

## 4. Logistics Dispatch & 3PL Governance

1. **Accredited 3PL Only:** Fleet riders belong to accredited 3PL companies; freelance or supplier-owned riders are prohibited.
2. **8 Road Operational Tabs:** 3PL dashboards organize ready dispatches into the 8 Road Corridors, showing required vehicle type and pickup stops.
3. **Three Unbreakable Logistics Validation Laws:**
   - **Single-Road Corridor Lock:** A rider assigned to a corridor manifest can only take orders on that same corridor.
   - **One Order, One Rider:** The full order is locked to a single rider ID across all pickup stops.
   - **Bulky Contagion & Keke Isolation:** Small orders can be batched on a motorcycle on the same corridor; bulky orders are forced onto a Keke and strictly isolated to a single customer trip.

---

## 5. Dual Verification Handover Chain

1. **Per-Shop Pickup:** Live camera photo of item at counter + unique 4-digit Shop Pickup OTP read out by merchant. Order only transitions to `in_transit_on_corridor` once all stops are verified.
2. **Doorstep Handover:** Live camera photo of intact package at doorstep + secret Customer Delivery OTP. System validates match and transitions to `completed_verified`.

---

## 6. Financial Ledger & Settlement Engine

Double-entry ledger executes automatically upon `completed_verified`:
1. **Supplier Payable:** Credited with the locked `cost_price` snapshot per fulfilled item. Settled daily or instantly.
2. **3PL Logistics Payable:** Credited with the pre-negotiated flat corridor rate per completed order trip (Bike rate vs Keke rate). Not per item, not per stop. Historical 90% commission rules are retired for new orders. Settled on automated weekly batch (Mondays).
3. **Victorious MARKET Net Margin:**
   $$\text{Net Profit} = \sum(\text{Retail Price} - \text{Supplier Wholesale Cost}) + (\text{Customer Delivery Fee Collected} - \text{3PL Corridor Payout})$$
