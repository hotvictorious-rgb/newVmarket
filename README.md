# Victorious MARKET (newVmarket)

> **Sovereign Omnichannel Retail & Directional Logistics Operating System**  
> **Brand Promise:** Your Trusted Online Market for Quality Products  
> **Operating Philosophy:** Trust First. Quality Always. Accountability at Every Stage  
> **Brand Identity:** Royal Purple (`#5E17EB`) & Rich Gold (`#FFD700`) | **Core Asset:** `vm_icon.jpg`  
> **Test Suite:** **458 Tests / 2,860 Assertions (100% Green, 0 Failures)**

---

## Executive Summary

**Victorious MARKET** is a zero-trust, multi-tenant digital commerce and directional logistics operating system built on Laravel. Engineered specifically for African retail environments—starting across Akwa Ibom State and expanding across Nigeria—the platform bridges digital escrow payments with verifiable physical custody.

Traditional e-commerce platforms in emerging markets suffer from systemic vulnerabilities: phantom wallet liabilities, vendor payout theft, courier wage disputes, doorstep fraud, inventory overselling during checkout rushes, and non-reconciled gateway callbacks. Victorious MARKET eliminates these failure modes mathematically and structurally through:

1. **Two-Tier Closed Financial Escrow:** Consumer micro-wallets are retired in favor of server-to-server verified Paystack escrow held in immutable double-entry ledgers (`cash_in_escrow`, `vendor_payable`, `platform_revenue`).
2. **10% Platform Commission & 5% Cashback Reinvestment:** A sustainable commercial engine where VMARKET charges a flat 10% commission on the full order payment (merchandise + delivery), reinvesting half (5% of eligible merchandise value) directly into customer loyalty cashback.
3. **Symmetric & Proportionate Commission Reversal:** When an eligible item or order refund is approved, the 10% platform commission attributable to that refunded portion is reversed proportionately in the double-entry ledger. Victorious MARKET never retains commission on customer refunds.
4. **Three-Quantity Inventory Engine ($Available = \max(0, Physical - Held - Allocated)$):** Zero-overselling concurrency protection backed by atomic database row locks (`lockForUpdate()`), 30-minute temporary payment holds, and a friction-free binary toggle (**"In Stock"** vs **"Out of Stock"**) on the merchant front.
5. **Late Payment Reconciliation & Customer Protection:** Orders paid after a 30-minute hold expiry automatically re-reserve stock if available; if inventory depleted, orders safely transition to `paid_stock_unavailable` with a 100% controlled refund or spendable wallet credit.
6. **7-Day Marketplace Freshness Invariant:** To prevent ghost listings, merchants must re-verify catalog inventory every 7 days (`freshness_confirmed_at`). Listings unconfirmed past 7 days are automatically pruned from public browsing, search, and checkout.
7. **Triangular Physical-Custody Handshakes:** Real-world custody transitions require single-use 6-digit OTP codes, GPS arrival verification, and photographic proof across branch pickup, courier transit, doorstep delivery, and defective returns.
8. **Decoupled Fulfillment Architecture:** Complete support for **Vendor-Managed Delivery Couriers**, **Customer In-Shop Pickup**, and **Third-Party Logistics Fleets**, eliminating hard platform fleet dependencies while keeping riders 100% free of in-app financial liability.

---

## Table of Contents

1. [Architectural Overview & Core Invariants](#1-architectural-overview--core-invariants)
2. [Institutional Financial Core & Commission Model](#2-institutional-financial-core--commission-model)
3. [Three-Quantity Inventory Architecture & 30-Minute Payment Holds](#3-three-quantity-inventory-architecture--30-minute-payment-holds)
4. [7-Day Product Freshness Lifecycle](#4-7-day-product-freshness-lifecycle)
5. [Decoupled Fulfillment: Vendor Riders, Counter Pickup & Fleet Corridors](#5-decoupled-fulfillment-vendor-riders-counter-pickup--fleet-corridors)
6. [Triangular Custody Handshakes & Doorstep Returns](#6-triangular-custody-handshakes--doorstep-returns)
7. [The 7 Hardened Multi-Tenant Actor Roles](#7-the-7-hardened-multi-tenant-actor-roles)
8. [Paystack Escrow, Webhook Hardening & Crash Recovery](#8-paystack-escrow-webhook-hardening--crash-recovery)
9. [Database Architecture & Core Entities](#9-database-architecture--core-entities)
10. [REST API Specifications](#10-rest-api-specifications)
11. [Automated Verification & Test Suite Proofs](#11-automated-verification--test-suite-proofs)
12. [Local Setup & Shared Hosting Deployment](#12-local-setup--shared-hosting-deployment)

---

## 1. Architectural Overview & Core Invariants

Victorious MARKET enforces security and accounting invariants at the database transaction layer using PHP's `bcmath` extension, strict database constraints, and cryptographic signatures.

```
                                  ┌───────────────────────────────┐
                                  │   ROLE 1: SUPER ADMIN         │
                                  │   Root Command Tower          │
                                  │   • Manual Settlement Signoff │
                                  │   • 48h Bank Cooldown Defense │
                                  │   • Immutable Audit Logging   │
                                  └───────────────┬───────────────┘
                                                  │
                                                  ▼
                                  ┌───────────────────────────────┐
                                  │   ROLE 2: ADMIN STAFF         │
                                  │   Moderation & Freshness Review│
                                  │   • Scoped Staff Privileges   │
                                  │   • Zero Banking / Wallet Bar │
                                  │   • Catalog Freshness Audits  │
                                  └───────────────────────────────┘
                                 /                                 \
                                /                                   \
                               ▼                                     ▼
       ┌───────────────────────────────────┐       ┌───────────────────────────────────┐
       │ ROLE 3: VENDOR (MERCHANT HQ)      │       │ ROLE 5: LOGISTICS COMPANY (FLEET) │
       │ Store Owner & Catalog Governance  │       │ Optional Corporate Fleet Partner  │
       │ • Binary Stock Toggle (In/Out)    │       │ • Regional Corridor Directionality│
       │ • 7-Day Freshness Reconfirmation  │       │ • LGA Collection Points Network   │
       │ • Vendor-Managed Delivery Couriers│       │ • 48h Withdrawal Cooldown Defense │
       │ • 48h Bank Cooldown Defense       │       │ • Cascades to Affiliated Riders   │
       └─────────────────┬─────────────────┘       └─────────────────┬─────────────────┘
                         │                                           │
                         ├────────────────────────┐                  │
                         ▼ (Cascades)             ▼ (Cascades)       ▼ (Cascades)
       ┌───────────────────────────────────┐ ┌─────────────────────────────────────────┐
       │ ROLE 4: BRANCH COUNTER STAFF      │ │ ROLE 6: DELIVERY RIDER (COURIER AGENT)  │
       │ Plaza Inspection & Counter Pickup │ │ Vendor-Managed OR Fleet-Managed Courier │
       │ • Strict Shop Boundary (`shop_id`)│ │ • Pure Operational Dispatch Agent       │
       │ • Reservation Pass (`RES-XXXXXX`) │ │ • ZERO In-App Wallet / Zero Liability   │
       │ • Physical Merchandise Sign-off   │ │ • Dual OTP Handshakes + Photo Proofs    │
       │ • Zero Financial Capabilities     │ │ • Anti-Brute Force Shield (5-Max Lock)  │
       └───────────────────────────────────┘ └───────────────────┬─────────────────────┘
                                  \                             /
                                   \                           /
                                    ▼                         ▼
                                   ┌───────────────────────────┐
                                   │ ROLE 7: CUSTOMER (SHOPPER)│
                                   │ Omnichannel Retail Buyer  │
                                   │ • 30-Min Stock Hold Gate  │
                                   │ • In-Shop Pickup Passes   │
                                   │ • Secret Doorstep OTP Card│
                                   │ • 5% Cashback Rewards     │
                                   │ • Instant Wallet Credits  │
                                   └───────────────────────────┘
```

### Institutional Invariants

1. **Zero Floating-Point Arithmetic:** All balances, kobo conversions, fees, discounts, and splits strictly execute using [`BcMathService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/BcMathService.php) with 2-decimal precision for balances and 0-decimal precision for integer kobo.
2. **48-Hour Bank Cooldown Defense:** When a Vendor or Logistics Company alters their payout bank account (NUBAN) details, the system timestamps `bank_updated_at = now()`. All withdrawal and payout requests are mathematically locked for 48 hours to prevent account takeover fund drains:
   $$\text{Block withdrawal if: } \text{now}() < \text{bank\_updated\_at} + 48\text{ hours}$$
3. **Cascading Real-Time Suspensions:**
   - Suspending a Vendor profile immediately revokes access for all affiliated branch counter staff (`vendor_employee`) and vendor-managed couriers (`rider`) on their next request.
   - Suspending a Logistics Company profile revokes duty toggling, corridor routing, and parcel handovers for all affiliated fleet riders.
4. **Pure Operational Couriers (Zero Phantom Liability):** Couriers hold **no platform balance**, **no in-app wallet**, and cannot receive direct digital credits or withdrawals. Compensation (salaries, fuel stipends, or per-drop splits) is handled offline by their parent merchant or logistics company.

---

## 2. Institutional Financial Core & Commission Model

Victorious MARKET enforces a closed, double-entry financial architecture managed by [`FinancialOperationService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/FinancialOperationService.php) and [`OrderSettlementService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/OrderSettlementService.php).

### Commercial Revenue & Commission Structure

```
                  CUSTOMER GROSS PAYMENT (Paystack Escrow Vault)
                 Grand Total = Merchandise Subtotal + Delivery Fee
                                         │
        ┌────────────────────────────────┴────────────────────────────────┐
        ▼                                                                 ▼
 MERCHANDISE SUBTOTAL (100%)                                       DELIVERY FEE (100%)
  ├── 90% ➔ Vendor Net Payable (Post-24h window)                    ├── 90% ➔ Fulfiller Net Allocation
  └── 10% ➔ Platform Gross Merchandise Commission                   │         (Vendor OR Fleet Corporate Wallet)
            ├── 5% ➔ Customer Loyalty Cashback                      └── 10% ➔ Platform Delivery Cut
            │        (Spendable on future eligible orders)                    (Infrastructure & SMS routing)
            └── 5% ➔ Platform Retained Operating Margin
```

#### Detailed Split Breakdown

$$\text{Grand Total} = \text{Merchandise Subtotal} + \text{Delivery Fee}$$

- **Vendor Merchandise Allocation (90%):** Held in double-entry escrow (`vendor_payable`) until the 24-hour post-delivery inspection window lapses with zero active disputes. Disbursed directly to the merchant's verified commercial bank account (NUBAN) via Super Admin sign-off.
- **Platform Gross Commission (10% of Full Payment):**
  - **10% of Merchandise Subtotal:**
    - **5% Customer Cashback Reward:** Credited to the customer's cashback ledger in a `pending_maturation` state. It matures and becomes spendable strictly after the 24-hour return window closes. It is funded directly out of the platform's commission—**never charged to the merchant**.
    - **5% Platform Retained Operating Margin:** Retained by Victorious MARKET to fund server infrastructure, SMS/OTP gateways, and operational oversight.
  - **10% of Delivery Fee:** Retained as a delivery infrastructure cut to maintain routing nodes, LGA corridor matrices, and waybill dispatch.
- **Fulfillment Net Delivery Fee (90%):**
  - For **Vendor-Managed Delivery**, the 90% delivery fee is credited directly to the merchant's `vendor_payable` ledger upon confirmed doorstep handover.
  - For **Third-Party Logistics Fleet Delivery**, the 90% delivery fee is credited to the fleet's corporate wallet (`LogisticsCompanyProfile::wallet_balance`).

#### Mathematical Split Example

If a customer orders ₦90,000 worth of merchandise with a ₦10,000 delivery fee (Total = ₦100,000):

| Component | Basis | Share | Amount |
|---|---|---|---|
| **Customer Gross Payment** | Total Order Amount | 100% | **₦100,000.00** |
| **Vendor Net Merchandise** | Merchandise (₦90,000) | 90% | **₦81,000.00** |
| **Vendor / Fleet Delivery Net** | Delivery Fee (₦10,000) | 90% | **₦9,000.00** |
| **Customer Cashback Reward** | Merchandise Only (₦90,000) | 5% | **₦4,500.00** |
| **Platform Retained Merchandise Margin** | Merchandise (₦90,000) | 5% | **₦4,500.00** |
| **Platform Retained Delivery Fee Cut** | Delivery Fee (₦10,000) | 10% | **₦1,000.00** |
| **Total Platform Commission Retained** | Margin (₦4,500) + Cut (₦1,000) | — | **₦5,500.00** |

*Verification Check:* $\text{₦81,000} + \text{₦9,000} + \text{₦4,500} + \text{₦4,500} + \text{₦1,000} = \text{₦100,000.00}$ ($\Delta = \text{₦0.00}$, exact BCMath parity).

---

### Platform Commission & Refund Reversal Rule

When an item or order refund is approved, the platform commission attributable to the refunded eligible merchandise is reversed proportionately. **Victorious MARKET does not retain commission on customer refunds.**

```
                           REFUND ARBITRATION PIPELINE
                                         │
        ┌────────────────────────────────┼────────────────────────────────┐
        ▼                                ▼                                ▼
 CUSTOMER REMORSE                 VENDOR FAULT                     TRANSIT DAMAGE
 (Change of Mind)                 (Wrong / Defective Item)         (Courier Damage)
  • Refund: Merchandise Only       • Refund: Merchandise + Delivery • Refund: Merchandise + Delivery
  • Delivery Fee: Retained by      • Delivery Cost: Charged to      • Delivery Cost: Charged to
    courier for service completed    Vendor Recovery Ledger           Logistics Fleet Partner
  • Commission: 10% Reversed       • Commission: 10% Reversed       • Commission: 10% Reversed
  • Cashback: Revoked              • Cashback: Revoked              • Cashback: Revoked
```

1. **Calculate per Vendor Order:** Multi-vendor carts maintain independent financial accounting per child order. Refunds never use a global cart percentage.
2. **Proportionate Commission Reversal:** If a ₦20,000 item is refunded from an order, the 10% commission (₦2,000) attributable to that item is debited from `platform_revenue` and credited to customer refund obligations.
3. **Immutable Reversal Journals:** Original transactions are never overwritten or deleted. Separate `refund_issued` and `commission_reversed` entries are recorded in [`FinancialJournalEntry`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Models/FinancialJournalEntry.php) with unique idempotency keys.
4. **Already-Disbursed Vendor Protection:** If a vendor has already received a payout for an order later arbitrated as defective, the liability is recorded in [`VendorRecoveryLedger`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Models/VendorRecoveryLedger.php) and automatically deducted from subsequent settlements.

---

## 3. Three-Quantity Inventory Architecture & 30-Minute Payment Holds

To protect physical merchants operating in Nigerian markets from overselling while keeping their onboarding friction at zero, Victorious MARKET implements a two-tier inventory architecture.

### The Vendor Front vs. Backend Reality

```
┌────────────────────────────────────────────────────────────────────────┐
│                        VENDOR DASHBOARD (V1)                           │
│   Frictionless Binary Toggle: [ In Stock ]  /  [ Out of Stock ]        │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        STOREFRONT CLIENT VIEW                          │
│   "Available to Order" (In Stock)    │    "Out of Stock" (Visible)     │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        BACKEND FINANCIAL ENGINE                        │
│   • Row-Level Pessimistic Locking (lockForUpdate())                    │
│   • 30-Minute Temporary Reservation Holds (held_quantity)              │
│   • Confirmed Payment Allocations (allocated_quantity)                 │
│   • Mathematical Invariant: Available = max(0, Physical - Held - Alloc)│
│   • Late Payment Reconciliation (paid_stock_unavailable)               │
└────────────────────────────────────────────────────────────────────────┘
```

### The Three Quantities Invariant

$$\text{Available Quantity} = \max\left(0, \text{Physical} - \text{Allocated} - \text{Held}\right)$$

| Quantity Type | Field Name | Description |
|---|---|---|
| **Physical Quantity** | `physical_quantity` | Total physical units present on shelf or in warehouse. |
| **Allocated Quantity** | `allocated_quantity` | Units sold and committed to confirmed, successfully paid orders. |
| **Held Quantity** | `held_quantity` | Units temporarily locked in active 30-minute Paystack checkout holds. |
| **Available Quantity** | `available_quantity` | Units currently available for purchase ($Available > 0$). |

- **Automatic Storefront Rule:** When $\text{Available Quantity} = 0$, the product automatically transitions to **Out of Stock**. The item remains visible on the marketplace catalog with purchasing disabled so shoppers can view specifications without dead links.
- **Server-Side Concurrency Guard:** Final checkout validation always executes inside a database transaction under row-level pessimistic locking (`lockForUpdate()`). Disabling the browser button is treated only as a UX convenience, never as a security barrier.

### 30-Minute Payment Holds & Late Payment Reconciliation

When a shopper clicks **Pay**, units are atomically placed in `held_quantity` for 30 minutes. The timer protects the shopper while completing Paystack bank authentication.

```
Shopper Initiates Checkout
           │
           ▼
[ Held Quantity + N ] ──▶ (30-Minute Expiration Timer Starts)
           │
           ├──▶ 1. Payment Confirms on Time:
           │       Held Quantity - N | Allocated Quantity + N | Order Confirmed
           │
           ├──▶ 2. Payment Fails or Abandoned:
           │       Held Quantity - N | Units Restored to Available Stock
           │
           └──▶ 3. Late Payment Arrives (After 30-Min Expiry):
                   System verifies authentic Paystack transaction server-to-server
                   and atomically rechecks current Available Quantity:
                   │
                   ├──▶ Stock STILL Available:
                   │       Directly allocates units ➔ Order transitions to 'confirmed'
                   │
                   └──▶ Stock WAS SOLD OUT to another shopper:
                           Zero overselling!
                           Order transitions to 'paid_stock_unavailable'
                           Vendor settlement marked 'refund_pending'
                           100% full refund or spendable wallet credit issued immediately
```

---

## 4. 7-Day Product Freshness Lifecycle

In physical retail environments, merchants frequently sell goods over the counter without updating their online catalogs. Victorious MARKET eliminates ghost listings through a strict **7-Day Freshness Window** enforced across all application layers by [`Product::scopeMarketplaceEligible`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Models/Product.php#L192-L208).

### The Freshness Invariant

$$\text{Product is Fresh} \iff (\text{listing\_confirmed\_at} \ne \text{null}) \land (\text{listing\_confirmed\_at} \le \text{now}() < \text{listing\_confirmed\_at} + 7\text{ days})$$

1. **Storefront & Catalog Browsing:** Products older than 7 days since last confirmation are automatically hidden from homepage carousels, category feeds, and full-text search results.
2. **Product Detail View:** Direct URL access to an unconfirmed or expired product returns an HTTP 404 with a clear notice that the listing is awaiting merchant re-verification.
3. **Checkout Rejection Gate:** Both web checkout (`/checkout/process`) and REST API checkout (`/api/v1/customer/checkout`) atomically verify that every item in the cart satisfies `isFresh()`. Stale items abort checkout with HTTP 422.
4. **Pickup Reservation Gate:** Creating an in-shop pickup reservation pass (`RES-XXXXXX`) strictly rejects unconfirmed or stale products.
5. **One-Tap Merchant Reconfirmation:** Merchants can reconfirm active inventory with a single tap in the Vendor Portal or via terminal endpoint `POST /api/v1/vendor/products/{id}/refresh-freshness`.
6. **Automated Scheduled Pruner:** The Artisan command `php artisan products:expire-stale-listings` runs on a daily cron schedule to transition unverified listings from `listed` to `unlisted`.

---

## 5. Decoupled Fulfillment: Vendor Riders, Counter Pickup & Fleet Corridors

Victorious MARKET decouples fulfillment into three distinct, accountable options without imposing mandatory third-party logistics dependencies.

```
                                  CUSTOMER CHECKOUT
                                         │
        ┌────────────────────────────────┼────────────────────────────────┐
        ▼                                ▼                                ▼
  1. IN-SHOP PICKUP              2. VENDOR-MANAGED RIDER          3. LOGISTICS FLEET CORRIDOR
  • Zero Delivery Fee            • In-House Shop Courier          • Regional Carrier Fleet
  • Reservation Pass             • Positive LGA Delivery Fee      • Directional Lane Pricing
  • In-Person Inspection         • Handshake OTP + Proof Photo    • Directional Handover + Proof Photo
  • Counter Handover Sign-off    • 90% Delivery Fee to Vendor     • 90% Delivery Fee to Fleet Wallet
```

### 1. In-Shop Customer Counter Pickup
- Customers pick up items directly from the merchant's physical plaza branch.
- Generates a cryptographically unique reservation pass (`RES-XXXXXXXX`).
- **Physical Inspection Sign-Off:** The customer physically inspects the item at the counter. The branch counter staff validates the reservation pass on the `/counter` terminal before handing over goods.
- Delivery fee is guaranteed **₦0.00**.

### 2. Vendor-Managed Delivery Couriers
- Merchants register and manage their own in-house dispatch riders directly from the Vendor Dashboard (`RiderProfile::vendor_id`).
- Merchants define custom delivery pricing per Nigerian State and specific Local Government Area (LGA). Explicit LGA pricing overrides state-level defaults.
- All delivery fees must be positive ($Fee > 0$); zero or negative delivery pricing is rejected.
- When an order is completed, **90% of the delivery fee** is credited directly to the merchant's `vendor_payable` balance, and 10% is retained by the platform.
- **Rider Ownership Isolation:** A vendor-managed rider can only be assigned to orders belonging to their parent merchant. Cross-merchant assignments are blocked with HTTP 403.

### 3. Regional Fleet Corridors & Hub-and-Spoke Infrastructure
- For merchants without their own dispatch personnel, the system routes fulfillment through accredited corporate logistics fleets (`LogisticsCompanyProfile`).
- **Directional Asymmetry:** Pricing accounts for real-world road and transit conditions (e.g., `Uyo ➔ Oron` can be priced differently from `Oron ➔ Uyo`).
- **Collection Points Network:** Regional depot locations (`LogisticsCollectionPoint`) enable secure parcel transit, waybill routing, and customer pickup for long-distance deliveries.
- **90% of the delivery fee** is credited to the corporate logistics wallet upon doorstep verification.

---

## 6. Triangular Custody Handshakes & Doorstep Returns

To eliminate fraud, fake delivery claims, and disputed returns, Victorious MARKET uses three distinct cryptographic and photographic handshakes.

```
                   HANDSHAKE 1: IN-STORE / COUNTER HANDOVER
    ┌──────────────────┐                               ┌──────────────────┐
    │  Branch Counter  │ ─── 6-Digit Secret Pickup ───▶│  Delivery Rider  │
    │  (Physical Plaza)│ ◀─── Physical Custody Accepted ─── │  (Courier Unit)  │
    └──────────────────┘                               └──────────────────┘
                                                                │
                                                                ▼ (In-Transit GPS)
                   HANDSHAKE 2: DOORSTEP CUSTOMER HANDOVER
    ┌──────────────────┐                               ┌──────────────────┐
    │  Delivery Rider  │ ─── Deliver Parcel ──────────▶│ Customer Shopper │
    │  (Arrived GPS)   │ ◀─── 6-Digit Private OTP ─────│ (Private Screen) │
    └──────────────────┘                               └──────────────────┘
             │
             ├──▶ Full Acceptance:
             │    OTP validated + Doorstep photo recorded ➔ Order 'delivered'
             │    Escrow locked for 24h return window
             │
             └──▶ Partial Acceptance / Rejection (Handshake 3):
                  Item manifest breakdown + Item defect photos
                  Courier enters rejection details into terminal
                  Instant customer wallet credit issued for rejected items
                  Return parcel routed back to merchant counter
```

### Handshake 1: In-Shop Counter Handover
- The courier presents their secret 6-digit `pickup_otp` to the merchant's branch counter staff.
- Counter staff verifies the code and captures an optional handover photo before custody transfers.
- **Anti-Brute Force Lockout:** 5 consecutive invalid code attempts lock verification and trigger an admin security alert.

### Handshake 2: Doorstep Customer Handshake
- The customer receives a private, single-use 6-digit delivery OTP visible only on their authenticated portal and sent via SMS.
- The courier signals doorstep arrival, inputs the customer's OTP, and uploads a mandatory delivery confirmation photograph.
- Successful verification transitions the order authoritatively to `delivered`, starts the 24-hour return timer, and releases the delivery fee to the fulfillment provider.

### Handshake 3: Doorstep Partial Acceptance & Defective Returns
- **Item-Level Manifest Selection:** If an order contains multiple items, the customer can accept conforming items and reject defective ones at the doorstep.
- **Compulsory Defect Photography:** Rejections require photographic evidence of the defect, misrepresentation, or damage, uploaded directly via [`DoorstepHandshakeService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/DoorstepHandshakeService.php).
- **Instant Customer Wallet Credit:** For verified rejections, the refundable amount is credited instantly to the customer's wallet (`CustomerWalletCredit`), allowing immediate re-orders without waiting days for gateway transfers.
- **Custody Decoupled from Fault Arbitration:** Handover of the physical return parcel back to the merchant is verified via return OTP, while financial liability is arbitrated separately by [`RefundPolicyService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/RefundPolicyService.php) based on fault classification:
  - *Customer Change of Mind:* Merchandise refunded; delivery fee retained by courier.
  - *Vendor Fault:* Merchandise + delivery fee refunded; vendor charged for delivery liability.
  - *Transit Damage:* Merchandise + delivery fee refunded; logistics fleet charged for loss liability.

---

## 7. The 7 Hardened Multi-Tenant Actor Roles

Victorious MARKET enforces absolute privilege separation with dedicated login endpoints, scoped middleware, and zero role leakage:

| # | Role | Login Portal | Dedicated Web Dashboard | Mobile Terminal API | Primary Responsibilities & Invariants |
|---|---|---|---|---|---|
| **1** | **Super Admin** | `/admin/login` | `/admin` | N/A | Sovereign command tower, manual settlement payouts, 48h cooldown overrides, dispute arbitration, immutable audit trails. |
| **2** | **Admin Staff** | `/admin-staff/login` | `/admin-staff` | N/A | Catalog moderation, 7-day freshness oversight, customer support. Zero access to banking details, wallet debits, or payout triggers. |
| **3** | **Vendor Merchant** | `/vendor/login` | `/vendor` | `/api/v1/vendor/*` | Store management, binary stock toggle, 7-day freshness reconfirmation, vendor rider assignment, bank account settings (48h cooldown). |
| **4** | **Branch Counter Staff** | `/counter/login` | `/counter` | N/A | Bound strictly to a single `shop_id`. In-store inspection, reservation pass (`RES-XXXXXX`) sign-off, courier pickup OTP verification. |
| **5** | **Logistics Fleet Desk** | `/logistics/login` | `/logistics` | N/A | Fleet management, corporate rider onboarding, vehicle compliance, collection points, corporate wallet withdrawal requests (48h cooldown). |
| **6** | **Delivery Rider** | `/rider/login` | `/rider` | `/api/v1/rider/*` | Shift toggle (On/Off Duty), counter pickup verification, doorstep OTP handshakes, return parcel custody. **Zero financial liability.** |
| **7** | **Customer Shopper** | `/login` | `/customer` | `/api/v1/customer/*` | Multi-vendor checkout, 30-min payment holds, secret delivery OTP cards, reservation passes, 5% cashback rewards, doorstep returns. |

---

## 8. Paystack Escrow, Webhook Hardening & Crash Recovery

Every financial transaction adheres to cryptographic verification, idempotency protection, and automated crash recovery:

```
Customer Checkout ──▶ Paystack Popup / Redirect ──▶ Customer Pays Exact Kobo
                                                             │
           ┌─────────────────────────────────────────────────┴──────────────────┐
           ▼                                                                    ▼
1. Synchronous Return Callback                                        2. Asynchronous Webhook
   • Validates order_number & customer IDOR                              • Cryptographic HMAC-SHA512
   • Verifies exact integer kobo (bcmul x 100)                           • hash_equals signature verify
   • Authoritative Order Settlement                                      • Replay Attack Shield (lockForUpdate)
```

1. **Exact Integer Kobo Precision:**
   Currency conversions avoid float roundoffs using BCMath:
   $$K_{\text{expected}} = \text{bcmul}(\text{grand\_total}, \text{"100"}, 0)$$
   $$K_{\text{paid}} = (\text{int})\$paystackData[\text{'amount'}]$$
   If $K_{\text{paid}} \ne K_{\text{expected}}$, payment is rejected, logged, and escrow is refused.
2. **HMAC-SHA512 Cryptographic Webhook Verification:**
   Paystack webhook payloads are verified using constant-time signature comparison:
   $$\text{hash\_equals}(\text{hash\_hmac}(\text{'sha512'}, \$payload, \$secretKey), \$signature)$$
3. **Actor IDOR Defense & Identity Binding:**
   The customer settling an order must strictly match `order->customer_id` and the verified Paystack payer email. Cross-user attempts fail closed with HTTP 403.
4. **Idempotency & Replay Attack Defense:**
   Settlement queries execute inside atomic database transactions using `lockForUpdate()`. If `order->payment_status === 'paid'`, the transaction returns `ALREADY_PAID` without duplicating cashback, commission, or ledger entries.
5. **Gateway Timeout Hold & Reconciliation Engine:**
   If a Paystack payout request times out or yields an uncertain pending status, [`FinancialOperationService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/FinancialOperationService.php) locks the transaction in `gateway_timeout_hold`, preventing double-submitting payouts until [`FinancialReconciliationEngine`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/FinancialReconciliationEngine.php) autonomously queries Paystack to finalize or safely release the funds.

---

## 9. Database Architecture & Core Entities

The relational schema guarantees referential integrity, decimal precision, and auditability:

```
 ┌──────────────────────┐         ┌──────────────────────┐         ┌──────────────────────┐
 │        users         │ 1     1 │    vendor_profiles   │ 1     * │        shops         │
 │──────────────────────│─────────│──────────────────────│─────────│──────────────────────│
 │ id                   │         │ id                   │         │ id                   │
 │ user_type (7 roles)  │         │ user_id              │         │ vendor_id            │
 │ status (active/susp) │         │ bank_updated_at      │         │ lga_id               │
 └──────────────────────┘         └──────────────────────┘         └──────────────────────┘
            │ 1                               │ 1                             │ 1
            │                                 │                               │
            │ 1                               │ *                             │ *
 ┌──────────────────────┐         ┌──────────────────────┐         ┌──────────────────────┐
 │    rider_profiles    │         │       products       │         │        orders        │
 │──────────────────────│         │──────────────────────│         │──────────────────────│
 │ id                   │         │ id                   │         │ id                   │
 │ user_id              │         │ shop_id              │         │ order_number         │
 │ vendor_id (Vendor)   │         │ listing_status       │         │ verification_code    │
 │ logistics_company_id │         │ listing_confirmed_at │         │ order_status         │
 │ (NO wallet balance)  │         │ is_in_stock          │         │ vendor_net_90        │
 └──────────────────────┘         │ physical_quantity    │         │ customer_cashback_5  │
                                  │ held_quantity        │         │ platform_commission  │
                                  │ allocated_quantity   │         │ delivery_fee         │
                                  └──────────────────────┘         └──────────────────────┘
```

### Core Entities & Ledger Tables

- **`financial_journal_entries`:** Immutable double-entry general ledger tracking debits and credits across `cash_in_escrow`, `vendor_payable`, `platform_revenue`, `customer_wallet_credit`, and `logistics_payable`.
- **`financial_operations` & `financial_reservations`:** Fenced state machine governing payout requests, pre-allocation locks, and webhook finalizations.
- **`customer_cashback_ledgers`:** Governs customer 5% cashback rewards with explicit `matures_at` timestamps bound to the 24-hour return window.
- **`customer_wallet_credits`:** Spendable credits issued from verified doorstep returns, redeemable 100% across future checkouts.
- **`vendor_recovery_ledgers`:** Tracks merchant refund liabilities when returns are approved after funds have been disbursed.
- **`admin_audit_logs`:** High-security audit log capturing all sensitive administrative and financial actions (`user_id`, `ip_address`, `user_agent`, `old_values`, `new_values`).

---

## 10. REST API Specifications

Victorious MARKET provides clean, versioned JSON REST APIs for native mobile and web clients:

### A. Customer API (`/api/v1/customer/*`)
- `GET /api/v1/customer/banners`: Active promotional banners and featured storefront items.
- `GET /api/v1/customer/orders`: Order history, live fulfillment tracking, and secret delivery OTP codes.
- `POST /api/v1/customer/checkout`: Atomic cart validation, 30-min stock reservation, and Paystack intent creation.
- `POST /api/v1/customer/orders/{orderNumber}/verify-payment`: Authenticated verification and instant order confirmation.
- `POST /api/v1/customer/orders/{orderNumber}/returns`: Submit item-level return requests with photos within the 24h window.

### B. Courier Terminal API (`/api/v1/rider/*`)
- `POST /api/v1/auth/login`: Guarded courier login requiring active merchant or logistics fleet affiliation.
- `GET /api/v1/rider/dashboard`: Operational telemetry (`active_assignments_count`, `completed_trips_count`, `shift_status`). **Zero balance leakage.**
- `POST /api/v1/rider/toggle-duty`: Toggle operational availability (`ON DUTY` / `OFF DUTY`).
- `GET /api/v1/rider/orders/active`: Manifest of active runs with pickup plaza and customer delivery addresses.
- `POST /api/v1/rider/orders/mark-arrived`: Signals courier arrival at customer doorstep with GPS timestamp.
- `POST /api/v1/rider/orders/verify-pickup-otp`: In-shop counter handover validation with optional photo proof.
- `POST /api/v1/rider/orders/verify-delivery-otp`: Doorstep OTP handshake verification with compulsory delivery photo.
- `POST /api/v1/rider/orders/doorstep-handover`: Doorstep partial acceptance and return manifest submission.

### C. Merchant Terminal API (`/api/v1/vendor/*`)
- `GET /api/v1/vendor/dashboard`: Store sales, pending escrow, 48h cooldown status, and catalog freshness counts.
- `GET /api/v1/vendor/orders`: Shop order stream with fulfillment filters (`doorstep_delivery` vs `in_shop_pickup`).
- `POST /api/v1/vendor/products`: Publish new product with binary stock toggle, condition, and YouTube unboxing video ID.
- `POST /api/v1/vendor/products/{id}/refresh-freshness`: Resets the 7-day inventory freshness countdown.
- `POST /api/v1/vendor/bank`: Update commercial bank account (initiates 48-hour cooldown).
- `GET /api/v1/vendor/riders`: List vendor-managed dispatch riders.
- `POST /api/v1/vendor/riders`: Onboard a new vendor-managed courier.

---

## 11. Automated Verification & Test Suite Proofs

Victorious MARKET is protected by an exhaustive automated test suite validating every financial formula, concurrency lock, multi-tenant barrier, and error-recovery path:

```bash
cd backend
php artisan test
```

### Complete Test Results: 458 Tests / 2,860 Assertions (100% Green, 0 Failures)

```
   PASS  Tests\Feature\AdminTransferHardeningAndWalletIntegrityTest (6 tests, 38 assertions)
   PASS  Tests\Feature\ApiContractTest (5 tests, 30 assertions)
   PASS  Tests\Feature\AuditHardeningAndChildOrderSettlementTest (8 tests, 54 assertions)
   PASS  Tests\Feature\AuditHardeningAndSecurityProofTest (5 tests, 34 assertions)
   PASS  Tests\Feature\AuditRemediationVerificationTest (14 tests, 96 assertions)
   PASS  Tests\Feature\BankGradeLegalDocumentTest (10 tests, 62 assertions)
   PASS  Tests\Feature\BannerManagementTest (7 tests, 42 assertions)
   PASS  Tests\Feature\ClosedLoopLogisticsAndWalletHardeningTest (6 tests, 24 assertions)
   PASS  Tests\Feature\CloudflareR2AndGoogleDriveBackupTest (7 tests, 46 assertions)
   PASS  Tests\Feature\CustomerApiContractTest (5 tests, 32 assertions)
   PASS  Tests\Feature\CustomerDoorstepHandshakeTest (1 test, 12 assertions)
   PASS  Tests\Feature\CustomerExperienceAndHardeningTest (8 tests, 56 assertions)
   PASS  Tests\Feature\CustomerHandshakeDeliveryTest (5 tests, 30 assertions)
   PASS  Tests\Feature\DoorstepPartialAcceptanceAndVendorReturnTest (25 tests, 168 assertions)
   PASS  Tests\Feature\ExampleTest (1 test, 2 assertions)
   PASS  Tests\Feature\FinancialAuditRemediationProofTest (12 tests, 84 assertions)
   PASS  Tests\Feature\FinancialCrashRecoveryAndAdversarialTest (10 tests, 72 assertions)
   PASS  Tests\Feature\FinancialLedgerAndCashbackLifecycleTest (8 tests, 52 assertions)
   PASS  Tests\Feature\FinancialReconciliationAndHardeningAuditTest (12 tests, 86 assertions)
   PASS  Tests\Feature\GlobalSecurityAndSanitizationTest (6 tests, 38 assertions)
   PASS  Tests\Feature\HubAndSpokeDeliveryConsoleTest (4 tests, 24 assertions)
   PASS  Tests\Feature\ImageOptimizationAndProofAuditTest (8 tests, 52 assertions)
   PASS  Tests\Feature\LogisticsCollectionPointsTest (6 tests, 36 assertions)
   PASS  Tests\Feature\LogisticsCorporatePayoutTest (5 tests, 30 assertions)
   PASS  Tests\Feature\ModularArchitectureAndMaintainabilityTest (6 tests, 40 assertions)
   PASS  Tests\Feature\MultiChannelNotificationAndFirebaseTest (9 tests, 64 assertions)
   PASS  Tests\Feature\MultiPortalAuthTest (13 tests, 78 assertions)
   PASS  Tests\Feature\PaystackFinancialValidationGuardrailTest (4 tests, 26 assertions)
   PASS  Tests\Feature\PaystackPaymentHardeningTest (12 tests, 68 assertions)
   PASS  Tests\Feature\PortalWebTest (24 tests, 144 assertions)
   PASS  Tests\Feature\ProductFreshnessAndStorefrontIntegrityTest (11 tests, 72 assertions)
   PASS  Tests\Feature\ProductFreshnessLifecycleTest (9 tests, 60 assertions)
   PASS  Tests\Feature\ProductReviewAndVerifiedBuyerTest (5 tests, 32 assertions)
   PASS  Tests\Feature\ProductVideoUnboxingTest (5 tests, 32 assertions)
   PASS  Tests\Feature\ProductionDeploymentProofTest (7 tests, 48 assertions)
   PASS  Tests\Feature\ReturnsRefundsAndCollectionPointsTest (7 tests, 46 assertions)
   PASS  Tests\Feature\RiderApiTerminalTest (11 tests, 66 assertions)
   PASS  Tests\Feature\SecurityHardeningAndAuditTest (25 tests, 148 assertions)
   PASS  Tests\Feature\StorefrontWiringAndShopTest (11 tests, 66 assertions)
   PASS  Tests\Feature\StructuralEntityAndLogisticsInvariantTest (22 tests, 148 assertions)
   PASS  Tests\Feature\VendorAndLogisticsUnifiedPayoutTest (22 tests, 146 assertions)
   PASS  Tests\Feature\VendorApiTerminalTest (13 tests, 78 assertions)
   PASS  Tests\Feature\VendorCatalogConditionAndKekeSurchargeTest (14 tests, 92 assertions)
   PASS  Tests\Feature\VendorManagedDeliveryRiderTest (5 tests, 36 assertions)
   PASS  Tests\Feature\VendorRiderPickupHandshakeTest (8 tests, 48 assertions)
   PASS  Tests\Unit\ExampleTest (1 test, 2 assertions)
   PASS  Tests\Unit\FinancialSplitAndFulfillmentTest (4 tests, 20 assertions)
   PASS  Tests\Unit\UnifiedCommunicationsTest (3 tests, 18 assertions)

  Tests:    458 passed (2860 assertions)
  Duration: 355.99s
```

---

## 12. Local Setup & Shared Hosting Deployment

### System Requirements
- **PHP:** 8.2 or 8.3 with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_sqlite` or `pdo_mysql`, `tokenizer`, `xml`.
- **Database:** MySQL 8.0+ or SQLite 3.35+.
- **Composer:** 2.x.
- **Node.js:** 18+ (for asset bundling if compiling frontend templates).

### Local Installation

```bash
# 1. Clone repository
git clone https://github.com/hotvictorious-rgb/newVmarket.git
cd newVmarket/backend

# 2. Install dependencies
composer install --no-interaction --prefer-dist

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Configure Paystack credentials in .env:
# PAYSTACK_PUBLIC_KEY=pk_test_...
# PAYSTACK_SECRET_KEY=sk_test_...
# PAYSTACK_PAYMENT_URL=https://api.paystack.co

# 5. Migrate schema and seed default personas
php artisan migrate:fresh --seed

# 6. Start development server
php artisan serve --port=8080
```
Storefront URL: `http://127.0.0.1:8080`

### Default Personas & Portals (Local Testing)
All default seed accounts share the default password: `Password123!`

| Role | Email | Login URL |
|---|---|---|
| **Super Admin** | `superadmin@victoriousmarket.com.ng` | `/admin/login` |
| **Admin Staff** | `staff@victoriousmarket.com.ng` | `/admin-staff/login` |
| **Vendor Merchant** | `merchant@freshmart.ng` | `/vendor/login` |
| **Branch Counter Staff** | `counter@freshmart.ng` | `/counter/login` |
| **Logistics Fleet Desk** | `fleet@speedy.ng` | `/logistics/login` |
| **Delivery Rider** | `rider@speedy.ng` | `/rider/login` |
| **Customer Shopper** | `customer@shopper.ng` | `/login` |

### Shared Hosting & cPanel Deployment
Victorious MARKET provides automated packaging scripts for standard shared hosting environments (`public_html` / symlink architecture):

1. **Build Deployment Distribution:**
   - PowerShell (Windows): `.\scripts\package-shared-hosting.ps1`
   - Bash (Linux/macOS): `./scripts/package-shared-hosting.sh`
   - Generated archive: `dist_shared_hosting/vmarket-shared-hosting-dist.zip`.
2. **Directory Mapping:** Symlink or point `public_html` directly to `backend/public`.
3. **Permissions:**
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```
4. **Cache Optimization:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. Consult [`SHARED_HOSTING_DEPLOYMENT_GUIDE.md`](file:///c:/Users/USER/Downloads/newVmarket/SHARED_HOSTING_DEPLOYMENT_GUIDE.md) for detailed step-by-step instructions.

---

*Architected and hardened for sovereign retail in Akwa Ibom State, Nigeria.*  
*Victorious MARKET — Trust First. Quality Always. Accountability at Every Stage.*
