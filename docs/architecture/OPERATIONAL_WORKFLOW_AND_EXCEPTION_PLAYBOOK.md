# Victorious MARKET — End-to-End Operational Workflow & Exception Playbook

> **Governing Policy Authority:** [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md) and [Master Blueprint](../../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md).  
> **Status:** Final Owner-Confirmed Standard Operating Procedure (SOP).

---

## 1. System Operating Philosophy & Master Flow

Victorious MARKET operates as a centralized, deterministic retail and logistics engine in Uyo, Akwa Ibom State. This Playbook documents the exact physical and digital SOP for every touchpoint: from supplier confirmation to doorstep handover, failed delivery recovery, dispute reverse-logistics, and financial settlement.

### The Zero-Automated-Outflow Law
Victorious MARKET enforces an unbreakable financial rule: **Money NEVER leaves the platform automatically.**
* **Zero Automated Paystack Refunds:** No webhook, cron job, queue worker, timeout, or automated script may ever trigger a Paystack refund directly. Every single refund (whether due to out-of-stock items, customer cancellation, 24-hour partial fulfilment expiration, or duplicate charges) is queued as an immutable **Pending Refund Request** on the **Super Admin Command Tower**. It requires explicit review, authorization, and manual dispatch initiation by a human Super Administrator.
* **Zero Automated Bank Transfers:** Supplier wholesale disbursements and logistics company settlements are never automated. Each payout is reviewed and manually authorized and dispatched by an authorized Super Admin strictly after customer funds have cleared and settled from Paystack into the platform's corporate account.

### Mandatory Customer Registration Gate (No Guest Checkout)
* **Open Guest Browsing:** Visitors may browse the catalog, view real-time Uyo product prices, and populate their shopping cart freely without signing in.
* **Registration Enforced at Checkout:** Guest checkout is strictly disabled. Before initiating checkout, selecting a landmark corridor, or submitting Paystack payment, the customer must register or log in. This binds the order to an authenticated customer identity, verified phone number for rider calls, and a secure delivery OTP channel.

```
[Customer Browses Freely (Guest) ──► Must Register/Login to Checkout]
                  │
                  ▼
[Customer Pays Upfront via Paystack]
                  │
                  ▼
[Automated Sourcing Engine Allocates Supplier(s)]
                  │
                  ▼
[Supplier 60-Min Confirmation Gate (8:00 AM – 6:00 PM)]
       ├── Confirms within 60 min ─────────────► [ALL Items Ready?]
       │                                                 │
       └── Timeout / Rejects ──► [Auto-Failover to Next Supplier (Admin Override)]
                                       │
                         All Exhausted ──► [Admin Alert: 24h Customer Partial Option]
                                                 │
                                                 ▼
[Logistics Fleet Dispatched (8:00 AM – 5:00 PM): One Order, One Rider]
                  │
                  ▼
[Shop Pickup: OTP + Mandatory Photo per Stop]
       │
       └── Shop Stockout Breach ──► 24h SKU Penalty + Auto-Route + Admin Alert
                  │
                  ▼
[Rider Calls Customer Prior to Departure] ◄───── MANDATORY TRANSIT NOTIFICATION
                  │
                  ▼
[Doorstep Handshake (by 5:00 PM): Delivery OTP + Geo-Tagged Photo]
                  │
          ┌───────┴───────┐
          ▼               ▼
      SUCCESS          ABSENCE / NO HANDSHAKE
          │               │
  Delivered Status        Rider returns parcel to 3PL Secure Custody
          │               Reattempt batched on next corridor run (Max 3 attempts, no extra fee)
  24h Claim Window        │
  (Strict Non-Returnable  Exhausted 3 attempts ──► Return to Supplier & Admin Cancel
   Exclusions Apply)      │                        (Merchandise Refunded, Delivery Fee Retained)
          │               │
  Mon–Fri Settlement      │
  (Paystack Settled,      ▼
  Manual Admin Payout)  [24h Defect Dispute Reported]
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

### 2.2 Sourcing Confirmation Clock (8:00 AM – 6:00 PM) vs. Delivery Window (8:00 AM – 5:00 PM)
* **Sourcing Operating Window (8:00 AM – 6:00 PM):** Supplier confirmation timers run strictly during Uyo commercial hours from **8:00 AM to 6:00 PM (West Africa Time)**.
  - A supplier has exactly **60 minutes** of active operating hours to confirm stock readiness.
  - **The Evening Protocol (After 6:00 PM):** Sourcing countdown timers pause. Merchants can view incoming customer orders on their portal at any time, but the 60-minute countdown clock **starts afresh at 8:00 AM** the following morning.
* **Delivery Transit Window (8:00 AM – 5:00 PM):** Physical road transit operates strictly between **8:00 AM and 5:00 PM**. No deliveries are dispatched after 5:00 PM to ensure rider safety, optimal visibility, and eliminate evening delivery security risks along Uyo road corridors.
* **Automated Failover & Admin Override Authority:**
  - If a supplier marks "Unavailable" or fails to confirm within 60 minutes, the engine immediately logs a 24-hour SKU penalty and automatically reallocates the line item to the next-best eligible in-stock supplier within Uyo LGA.
  - **Super Admin Override:** The Super Admin Command Tower is alerted immediately. An authorized Super Admin maintains permanent authority to override the automated match and reassign the order line item to any other eligible supplier in Uyo.

### 2.3 Sourcing Exhaustion & 24-Hour Partial Fulfilment Protocol
If all eligible suppliers in Uyo LGA are exhausted without fulfilling a line item:
1. The order immediately transitions to the **Admin Operations Command Tower** (`paid_stock_unavailable` / exception hold).
2. Victorious MARKET Customer Support immediately contacts the customer and presents the **Partial Fulfilment Option**:
   - **Option A (Customer Accepts Partial):** The confirmed items are dispatched for doorstep delivery. The unfulfillable item's full retail selling price is immediately recorded as a **Pending Partial Refund Request** in the Admin Command Tower. An authorized Super Admin must review and manually trigger the Paystack refund.
   - **Option B (Customer Rejects Partial):** The customer declines partial delivery. The entire order is cancelled, and a **Pending Full Refund Request** (merchandise + delivery fee) is queued for manual Super Admin review and manual gateway dispatch.
3. **The 24-Hour Customer Response Window:** Support waits up to **24 hours** for the customer's decision. If the customer does not respond within 24 hours, the order automatically cancels, and the full refund is queued on the Admin Command Tower for manual Super Admin authorization. Funds remain locked in Paystack escrow until a human Super Admin approves and clicks to initiate the refund.

---

## 3. Stage 2: Pickup, Counter Breaches & Mid-Route Protocols

### 3.1 Chain of Custody at Supplier Shop
* For each supplier stop, the rider must:
  1. Inspect the items to confirm physical match and condition.
  2. Capture live camera photo proof of the item on the shop counter.
  3. Enter the supplier's 4-digit **Shop Pickup OTP**.
* Once the OTP is validated, physical liability shifts contractually from the supplier to the accredited 3PL Logistics Company.

### 3.2 Counter Breach: "Walk-In Cash Customer" Stockout at Handover
If a merchant previously confirmed stock readiness on their portal, but when the rider arrives with the Pickup OTP, the merchant states the item was sold to a walk-in cash customer:
1. **Rider Counter-Flag:** The rider selects **"Item Unavailable at Shop Counter"** in the mobile interface.
2. **24-Hour SKU Penalty:** The platform instantly slaps that supplier with a 24-hour unavailability cooldown for that product.
3. **Automated Re-Route:** The sourcing engine instantly re-allocates the item to the next-best eligible supplier in Uyo LGA.
4. **Command Tower Alert & Admin Override:** The incident triggers an urgent alert on the Super Admin Command Tower. The Super Admin can monitor the auto-route or immediately intervene to manually assign any alternative verified supplier.

### 3.3 Mid-Route Failure (e.g. Subsequent Shop Shutter Closed)
If a rare exception occurs where Shop 1 is picked up, but Shop 2 cannot hand over Item 2:
1. **3PL Secure Custody Hold:** The rider transports Item 1 directly to the accredited **3PL Logistics Company's secure facility/office**.
2. **Contractual Holding:** Accredited 3PL logistics companies contractually provide secure temporary holding without extra platform fees.
3. **Admin Intervention:** The order enters the Admin Exception Queue to reassign Item 2 or trigger the 24-hour customer partial delivery protocol.

---

## 4. Stage 3: Doorstep Delivery & Customer Absence Protocol

### 4.1 Mandatory Pre-Departure Call
* **The Rider Call Rule:** Before heading out on the road corridor toward the customer destination, the rider **must call the customer** to notify them of estimated arrival time.
* If the customer states they are unavailable, the trip is rescheduled before physical travel begins.

### 4.2 Customer Absence at Doorstep & Fair Protection Refund
If the rider arrives at the customer's verified landmark address during delivery hours (8:00 AM – 5:00 PM) and the customer is not present or reachable:
1. **Return to 3PL Custody:** The rider returns the package to the logistics company’s secure holding facility.
2. **Batched Re-Attempt Rule:**
   - The platform allows up to **3 delivery attempts**.
   - **The platform does NOT pay for re-delivery trips.**
   - All re-attempts **must be batched** with future scheduled delivery runs traveling down that exact same road corridor.
3. **Exhausted Attempts (3 Failed Attempts) — The Fair Protection Refund:**
   - If the customer fails to receive the order after 3 documented attempts, the order transitions to `delivery_failed_abandoned`.
   - The logistics company returns the items to the originating suppliers.
   - **Fair Protection Financial Rule:** Under Super Admin manual review, the customer receives a **refund of the merchandise selling price only**. The **customer delivery fee is permanently retained** by Victorious MARKET to cover the logistics fleet costs incurred across 3 physical transit attempts. The refund is queued for manual Super Admin dispatch under the Zero-Automated-Outflow Law.

### 4.3 Flat Customer Base Fee Integrity (Zero Multi-Stop Customer Surcharges)
* The customer is charged strictly **one flat customer base delivery fee** (plus bulky surcharge if bulky), regardless of whether items are sourced from 1, 2, or 3 separate shops across Uyo.
* Victorious MARKET absorbs any multi-stop logistics company corridor tariff differential as a platform expense/subsidy, ensuring checkout conversion remains friction-free.

---

## 5. Stage 4: Post-Delivery Disputes, Non-Returnable Items & Reverse Logistics

### 5.1 24-Hour Return Window & Strict Admin Adjudication
* Customers have exactly 24 hours from verified delivery OTP handshake to report defective, non-functional, or misdescribed items.
* **Not All Returns/Refunds are Approved:** A customer report does not automatically grant a return or refund. Every claim requires photographic/video evidence and is individually adjudicated by a Super Administrator.
* **Strictly Non-Returnable Categories:**
  Once the Delivery OTP handshake is verified, the following categories are strictly non-returnable and non-refundable:
  1. **Perishable Goods:** Fresh food, fruits, vegetables, baked goods, and chilled groceries.
  2. **Personal Hygiene & Intimate Items:** Underwear, socks, cosmetics, beauty items with opened seals, and personal grooming appliances.
  3. **Digital & Vouchers:** Software licenses, scratch cards, and airtime/data pins.

### 5.2 Zero-Waste Reverse Logistics via Corridors
* If an item return is authorized by Super Admin:
  - An accredited rider operating on that specific road corridor collects the item from the customer **on their return journey from an active delivery run**.
  - The rider returns the item directly to the originating supplier's shop.
  - This eliminates standalone courier costs for return pickups.

### 5.3 Escrow Freeze & Dispute Arbitration
* **Instant Payout Freeze:** The moment a return or defect case is filed, the associated supplier payable is **immediately frozen in escrow**—no money leaves the platform.
* **Admin Arbitration:** If the supplier disputes the defect (e.g., alleging customer negligence or misuse):
  - The item is brought to the **Victorious MARKET Admin Desk** for physical inspection.
  - Super Admin inspects the pickup photo, doorstep photo, and returned item to issue a final, binding determination. If approved, the refund is manually dispatched to the customer; if denied, frozen funds are released to the supplier.

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

### 7.2 Monday-to-Friday Payout Schedule & Manual Initiation Mandate
* Paystack operates on standard Nigerian banking business days (**T+1 settlement** for card and bank transfers, with weekend transactions settling on Monday).
* Consequently, **Supplier and Fleet Disbursements occur strictly Monday through Friday**:
  - Orders verified over the weekend mature into eligibility once Paystack funds settle on Monday morning.
  - **Zero Automated Outflow Mandate:** Under no circumstances are automated bank transfers dispatched by cron or background queue. Every single NUBAN disbursement to a supplier or logistics company must be manually authorized, reviewed, and initiated by an authorized Super Administrator.
* **Payout Guardrails:**
  - Minimum payout request/batch: **₦5,000**.
  - Maximum single transaction: **₦1,000,000**.
  - Non-FIFO, auditable selection of verified order line-item wholesale payables.
  - Full cryptographic and audit trail linking each transfer to settled Paystack receipts.
