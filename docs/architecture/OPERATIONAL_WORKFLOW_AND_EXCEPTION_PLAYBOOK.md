# Victorious MARKET — End-to-End Operational Workflow & Exception Playbook

> **Governing Policy Authority:** [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md) and [Master Blueprint](../../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md).  
> **Status:** Final Owner-Confirmed Standard Operating Procedure (SOP).

---

## 1. System Operating Philosophy & Master Flow

Victorious MARKET operates as a centralized, deterministic retail and logistics engine in Uyo, Akwa Ibom State. This Playbook documents the exact physical and digital SOP for every touchpoint: from supplier confirmation to doorstep handover, failed delivery recovery, dispute reverse-logistics, and financial settlement.

```
[Customer Pays Upfront via Paystack]
                  │
                  ▼
[Automated Sourcing Engine Allocates Supplier(s)]
                  │
                  ▼
[Supplier 60-Min Confirmation Gate (8:00 AM – 4:00 PM)]
       ├── Confirms within 60 min ─────────────► [ALL Items Ready?]
       │                                                 │
       └── Timeout / Rejects ──► [Auto-Failover to Next Supplier]
                                       │
                         All Exhausted ──► [Admin Alert: 24h Customer Partial Option]
                                                 │
                                                 ▼
[Logistics Fleet Dispatched: One Order, One Rider]
                  │
                  ▼
[Shop Pickup: OTP + Mandatory Photo per Stop]
                  │
                  ▼
[Rider Calls Customer Prior to Departure] ◄───── MANDATORY TRANSIT NOTIFICATION
                  │
                  ▼
[Doorstep Handshake: Delivery OTP + Geo-Tagged Photo]
                  │
          ┌───────┴───────┐
          ▼               ▼
      SUCCESS          ABSENCE / NO HANDSHAKE
          │               │
  Delivered Status        Rider returns parcel to 3PL Secure Custody
          │               Reattempt batched on next corridor run (Max 3 attempts, no extra fee)
  24h Claim Window        │
          │               Exhausted 3 attempts ──► Return to Supplier & Admin Cancel
  Mon–Fri Settlement      │
  (Paystack Settled)      │
                          ▼
            [24h Defect Dispute Reported]
                          │
            Supplier Payable FROZEN in Escrow
            Rider collects on return corridor trip
            Admin executes binding arbitration
```

---

## 2. Stage 1: Sourcing & Pre-Dispatch Confirmation Gate

### 2.1 The "All-Confirmed-Before-Movement" Gate
* **Strict Invariant:** A rider from the assigned logistics company is **never dispatched** until **every single item** in the customer order has been explicitly marked as `ready_for_pickup` by each assigned supplier.
* **No Speculative Dispatch:** Riders do not travel to a shop on the assumption that an item will be available. If an order has 3 items from 2 shops, both shops must confirm readiness on their portal before the order moves to the logistics dispatch queue.

### 2.2 The 60-Minute Business-Hours Confirmation Clock
* **Operating Clock Window:** Sourcing confirmation timers run strictly during Uyo commercial hours: **8:00 AM to 4:00 PM (West Africa Time)**.
  - If an order is verified outside this window (e.g. 8:30 PM), the 60-minute countdown pauses and begins at 8:00 AM the following morning.
* **Automated Failover:** A supplier has exactly **60 minutes** of active operating hours to confirm stock readiness.
  - If the supplier marks "Unavailable" or fails to confirm within 60 minutes, the engine immediately logs a 24-hour SKU penalty and automatically reallocates the line item to the next-best eligible in-stock supplier within Uyo LGA.

### 2.3 Sourcing Exhaustion & 24-Hour Partial Fulfilment Protocol
If all eligible suppliers in Uyo LGA are exhausted without fulfilling a line item:
1. The order immediately transitions to the **Admin Operations Command Tower** (`paid_stock_unavailable` / exception hold).
2. Victorious MARKET Customer Support immediately contacts the customer and presents the **Partial Fulfilment Option**:
   - **Option A (Customer Accepts Partial):** The confirmed items are dispatched for doorstep delivery. The unfulfillable item's full retail selling price is immediately queued for a refund to the original Paystack payment channel.
   - **Option B (Customer Rejects Partial):** The customer declines partial delivery. The entire order is cancelled, and 100% of customer funds (merchandise + delivery fee) are refunded via Paystack.
3. **The 24-Hour Customer Response Window:** Support waits up to **24 hours** for the customer's decision. If the customer does not respond within 24 hours, the order automatically cancels and issues a full 100% Paystack refund.

---

## 3. Stage 2: Pickup & Mid-Route Breakdown Protocol

### 3.1 Chain of Custody at Supplier Shop
* For each supplier stop, the rider must:
  1. Inspect the items to confirm physical match and condition.
  2. Capture live camera photo proof of the item on the shop counter.
  3. Enter the supplier's 4-digit **Shop Pickup OTP**.
* Once the OTP is validated, physical liability shifts contractually from the supplier to the accredited 3PL Logistics Company.

### 3.2 Mid-Route Failure (e.g. Subsequent Shop Shutter Closed)
If a rare exception occurs where Shop 1 is picked up, but Shop 2 cannot hand over Item 2:
1. **3PL Secure Custody Hold:** The rider transports Item 1 directly to the accredited **3PL Logistics Company's secure facility/office**.
2. **Contractual Holding:** Accredited 3PL logistics companies contractually provide secure temporary holding without extra platform fees.
3. **Admin Intervention:** The order enters the Admin Exception Queue to reassign Item 2 or trigger the 24-hour customer partial delivery protocol.

---

## 4. Stage 3: Doorstep Delivery & Customer Absence Protocol

### 4.1 Mandatory Pre-Departure Call
* **The Rider Call Rule:** Before heading out on the road corridor toward the customer destination, the rider **must call the customer** to notify them of estimated arrival time.
* If the customer states they are unavailable, the trip is rescheduled before physical travel begins.

### 4.2 Customer Absence at Doorstep
If the rider arrives at the customer's verified landmark address and the customer is not present or reachable:
1. **Return to 3PL Custody:** The rider returns the package to the logistics company’s secure holding facility.
2. **Batched Re-Attempt Rule:**
   - The platform allows up to **3 delivery attempts**.
   - **The platform does NOT pay for re-delivery trips.**
   - All re-attempts **must be batched** with future scheduled delivery runs traveling down that exact same road corridor.
3. **Exhausted Attempts (3 Failed Attempts):** If the customer cannot be reached after 3 documented attempts:
   - The order enters `delivery_failed_abandoned`.
   - The logistics company returns the items to the originating suppliers.
   - Admin investigates and processes the case under the cancellation and refund policy.

---

## 5. Stage 4: Post-Delivery Disputes & Reverse Logistics

### 5.1 24-Hour Return / Defect Window
* Customers have exactly 24 hours from verified delivery OTP handshake to report defective, non-functional, or misdescribed items.

### 5.2 Zero-Waste Reverse Logistics via Corridors
* If an item return is authorized:
  - An accredited rider operating on that specific road corridor collects the item from the customer **on their return journey from an active delivery run**.
  - The rider returns the item directly to the originating supplier's shop.
  - This eliminates standalone courier costs for return pickups.

### 5.3 Escrow Freeze & Dispute Arbitration
* **Instant Payout Freeze:** The moment a return or defect case is filed, the associated supplier payable is **immediately frozen in escrow**—no money leaves the platform.
* **Admin Arbitration:** If the supplier disputes the defect (e.g., alleging customer negligence or misuse):
  - The item is brought to the **Victorious MARKET Admin Desk** for physical inspection.
  - Super Admin inspects the pickup photo, doorstep photo, and returned item to issue a final, binding determination.

---

## 6. Stage 5: Supplier Onboarding & Lean KYC Standards

To balance robust fraud prevention with low friction for local Uyo merchants, the platform enforces clear tiering:

| Requirement | Status | Standard / Validation Rule |
|---|---|---|
| **Business / Shop Name** | **MANDATORY** | Registered trade name of the shop or merchant. |
| **Physical Shop Address** | **MANDATORY** | Specific location/landmark strictly within approved Uyo LGA boundary. |
| **Nigerian Bank Account (NUBAN)** | **MANDATORY** | 10-digit account number, bank code, and resolved account name. |
| **Shop Signboard / Storefront Photo** | *Optional* | Recommended for initial verification. |
| **NIN (National Identity Number)** | *Optional* | Not required for initial catalogue listing. |
| **CAC Registration Certificate** | *Optional* | Small informal traders in Uyo commercial axes are permitted. |

* **Zero Public Sign-Ups:** Suppliers and logistics fleets cannot self-register public accounts. Every supplier account, shop, and fleet partner must be provisioned and activated by an authorized Victorious MARKET Super Administrator.

---

## 7. Stage 6: Working Capital & Settlement Schedule

### 7.1 Paystack Settlement Dependency
* Victorious MARKET operates a protected treasury model. No supplier payout is disbursed using uncollected speculative money.
* Payouts to suppliers occur **only after customer payments have settled from Paystack into Victorious MARKET's corporate settlement account**.

### 7.2 Monday-to-Friday Payout Schedule
* Paystack operates on standard Nigerian banking business days (**T+1 settlement** for card and bank transfers, with weekend transactions settling on Monday).
* Consequently, **Supplier Disbursements occur strictly Monday through Friday**:
  - Orders verified over the weekend mature into eligibility once Paystack funds settle on Monday morning.
  - No automated supplier bank transfers are dispatched on Saturdays or Sundays.
* **Payout Guardrails:**
  - Minimum payout request/batch: **₦5,000**.
  - Maximum single transaction: **₦1,000,000**.
  - Non-FIFO, auditable selection of verified order line-item wholesale payables.
