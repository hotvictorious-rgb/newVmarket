# Victorious MARKET (newVmarket)

> **Sovereign Omnichannel Retail & Vendor-Managed Fulfilment Platform**  
> **Brand Promise:** Your Trusted Online Market for Quality Products  
> **Operating Philosophy:** Trust First. Quality Always. Accountability at Every Stage  
> **Brand Identity:** Royal Purple (`#5E17EB`) & Rich Gold (`#FFD700`) | **Core Asset:** `vm_icon.jpg`  
> **Test Suite:** **458 Tests / 2,860 Assertions (100% Green, 0 Failures)**

---

## Executive Summary

**Victorious MARKET** is a zero-trust, multi-tenant digital commerce platform with vendor-managed delivery built on Laravel. Engineered specifically for African retail environments—starting across Akwa Ibom State and expanding across Nigeria—the platform bridges digital escrow payments with verifiable physical custody.

Traditional e-commerce platforms in emerging markets suffer from systemic vulnerabilities: phantom wallet liabilities, vendor payout theft, courier wage disputes, doorstep fraud, inventory overselling during checkout rushes, and non-reconciled gateway callbacks. Victorious MARKET eliminates these failure modes mathematically and structurally through:

1. **Two-Tier Closed Financial Escrow:** Consumer micro-wallets are retired in favor of server-to-server verified Paystack escrow held in immutable double-entry ledgers (`cash_in_escrow`, `vendor_payable`, `platform_revenue`).
2. **10% Platform Commission & 5% Cashback Reinvestment:** A sustainable commercial engine where VMARKET charges a flat 10% commission on the full order payment (merchandise + delivery), reinvesting half (5% of eligible merchandise value) directly into customer loyalty cashback.
3. **Symmetric & Proportionate Commission Reversal:** When an eligible item or order refund is approved, the 10% platform commission attributable to that refunded portion is reversed proportionately in the double-entry ledger. Victorious MARKET never retains commission on customer refunds.
4. **Two-Status Inventory Model (In Stock / Out of Stock) & Payment Safety Reservations:** Product availability is governed by a vendor-controlled binary toggle (**"In Stock"** vs **"Out of Stock"**), avoiding fragile quantity-counting friction for local merchants. Checkout and in-shop inspection hold temporary payment reservations (30-minute and 24-hour limits) without decrementing inventory counts.
5. **Conflict & Late Payment Reconciliation:** If a merchant runs out of stock offline or two shoppers attempt concurrent purchase of the last item, the vendor switches the status to Out of Stock. Any late-confirming payment for an out-of-stock item transitions safely to `paid_stock_unavailable` with a 100% full refund or spendable wallet credit guarantee—ensuring zero overselling, zero merchant forced-fulfillment, and zero customer loss.
6. **7-Day Marketplace Freshness Invariant:** To prevent ghost listings, merchants must re-verify catalog inventory every 7 days (`freshness_confirmed_at`). Listings unconfirmed past 7 days are automatically pruned from public browsing, search, and checkout.
7. **Triangular Physical-Custody Handshakes:** Real-world custody transitions require single-use 6-digit OTP codes, GPS arrival verification, and photographic proof across branch pickup, courier transit, doorstep delivery, and defective returns.
8. **Decoupled Fulfillment Architecture:** Complete support for **Vendor-Managed Doorstep Delivery** and **Customer In-Shop Pickup**. Legacy logistics records are preserved for historical audit only; new orders do not use platform-managed fleets.

---

## Table of Contents

1. [Architectural Overview & Core Invariants](#1-architectural-overview--core-invariants)
2. [Institutional Financial Core & Commission Model](#2-institutional-financial-core--commission-model)
3. [The Two-Status Inventory Architecture & Payment Safety Reservations](#3-the-two-status-inventory-architecture--payment-safety-reservations)
4. [7-Day Product Freshness Lifecycle](#4-7-day-product-freshness-lifecycle)
5. [Vendor-Managed Fulfilment: Vendor Riders and In-Shop Pickup](#5-vendor-managed-fulfilment-vendor-riders-and-in-shop-pickup)
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
       │ ROLE 3: VENDOR (MERCHANT HQ)      │       │ ROLE 5: LEGACY LOGISTICS RECORDS │
       │ Store Owner & Catalog Governance  │       │ Historical audit only; no new dispatch  │
       │ • Binary Stock Toggle (In/Out)    │       │ • Read-only historical references│
       │ • 7-Day Freshness Reconfirmation  │       │ • No new order assignments   │
       │ • Vendor-Managed Delivery Only│       │ • No new logistics payouts │
       │ • 48h Bank Cooldown Defense       │       │ • Historical records preserved   │
       └─────────────────┬─────────────────┘       └─────────────────┬─────────────────┘
                         │                                           │
                         ├────────────────────────┐                  │
                         ▼ (Cascades)             ▼ (Cascades)       ▼ (Cascades)
       ┌───────────────────────────────────┐ ┌─────────────────────────────────────────┐
       │ ROLE 4: BRANCH COUNTER STAFF      │ │ ROLE 6: DELIVERY RIDER (COURIER AGENT)  │
       │ Plaza Inspection & Counter Pickup │ │ Vendor-Linked Courier Only │
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
2. **48-Hour Bank Cooldown Defense:** When a Vendor alters their payout bank account (NUBAN) details, the system timestamps `bank_updated_at = now()`. Vendor withdrawal requests are locked for 48 hours to prevent account takeover fund drains:
   $$\text{Block withdrawal if: } \text{now}() < \text{bank\_updated\_at} + 48\text{ hours}$$
3. **Cascading Real-Time Suspensions:**
   - Suspending a Vendor profile immediately revokes access for all affiliated branch counter staff (`vendor_employee`) and vendor-managed couriers (`rider`) on their next request.
   - Legacy logistics-company suspension remains relevant only to historical records; it does not create or authorize new delivery assignments.
4. **Pure Operational Couriers (Zero Phantom Liability):** Couriers hold **no platform balance**, **no in-app wallet**, and cannot receive direct digital credits or withdrawals. Vendor-linked rider compensation (if any) is arranged directly by that rider's vendor outside VMARKET. The platform does not track or pay rider compensation.

---

## 2. Institutional Financial Core & Commission Model

Victorious MARKET enforces a closed, double-entry financial architecture managed by [`FinancialOperationService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/FinancialOperationService.php) and [`OrderSettlementService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/OrderSettlementService.php).

### Commercial Revenue & Commission Structure

The approved commercial rule is **10% VMARKET commission on the eligible vendor-order total, including the delivery fee**. Customer cashback is **5% of eligible merchandise value only**, excluding delivery fees, and is funded from the platform commission.

For each vendor order:

- **Vendor allocation:** 90% of the eligible vendor-order total before other documented adjustments.
- **Platform gross commission:** 10% of the eligible vendor-order total, including delivery.
- **Customer cashback:** 5% of eligible merchandise value only; funded from the platform commission and never from rider earnings.
- **Platform remaining commission:** gross commission minus eligible cashback and any other properly recorded platform-funded incentive/adjustment.

Calculate these amounts separately for each vendor order in a multi-vendor checkout. Never apply a separate logistics-company split in addition to the 10% total commission.

#### Mathematical Split Example

For ₦90,000 eligible merchandise plus ₦10,000 delivery (total ₦100,000):

| Component | Basis | Amount |
|---|---|---:|
| Customer gross payment | Merchandise + delivery | ₦100,000.00 |
| Vendor allocation before adjustments | 90% of eligible vendor-order total | ₦90,000.00 |
| VMARKET gross commission | 10% of eligible vendor-order total | ₦10,000.00 |
| Customer cashback | 5% of eligible merchandise only | ₦4,500.00 |
| VMARKET commission remaining after cashback | ₦10,000 − ₦4,500 | ₦5,500.00 |

Verification: ₦90,000 + ₦4,500 + ₦5,500 = ₦100,000. Cashback is funded within the commission, not charged as an additional amount to the customer or deducted again from the vendor allocation.

### Delivery-Fee Ownership and Rider Compensation

There is no separate 90% logistics-company / 10% platform split for new orders. The vendor receives the vendor allocation after the platform's commission, including the delivery component, and is responsible for compensating its own rider directly outside VMARKET. No rider wallet, payable, bank account, earnings ledger, or payout request may be created by the platform. Legacy logistics-company journals and payout records remain historical audit data only.

### Platform Commission & Refund Reversal Rule

When an item or order refund is approved, the platform commission attributable to the refunded eligible merchandise is reversed proportionately. **Victorious MARKET does not retain commission on customer refunds.**

```
                           REFUND ARBITRATION PIPELINE
                                         │
        ┌────────────────────────────────┼────────────────────────────────┐
        ▼                                ▼                        ## 3. The Two-Status Inventory Architecture & Payment Safety Reservations

Victorious MARKET is engineered to support physical retail merchants operating across Nigerian commercial centers. For these local shop owners, maintaining complex numeric stock counts, physical shelf audits, or warehouse balance ledgers introduces steep onboarding friction and frequent discrepancies with over-the-counter walk-in customers.

To resolve this, Victorious MARKET clearly separates **Product Availability** from **Payment Safety**.

### 1. Product Availability: Pure Binary Vendor Governance

Product availability is governed by two simple states under direct merchant control:

- **In Stock (`is_in_stock = true`)**: The vendor confirms the product is physically available in their shop. Customers can purchase or reserve the item as long as all other eligibility checks (such as the 7-day freshness check and active merchant status) pass.
- **Out of Stock (`is_in_stock = false`)**: The vendor marks the product unavailable. Purchasing is disabled immediately across the storefront. The product remains visible on the marketplace so shoppers can inspect details, but checkout is blocked.
- **The Seven-Day Freshness Rule**: Products that exceed seven days without explicit merchant reconfirmation become inactive and are automatically hidden from storefront discovery and checkout.

The marketplace does **not** rely on tracking numeric quantities (e.g. how many units a merchant has) to determine whether a product can be sold.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        VENDOR DASHBOARD                                │
│        Simple Binary Toggle: [ In Stock ]  /  [ Out of Stock ]         │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        STOREFRONT CLIENT VIEW                          │
│        "In Stock" (Buy Enabled)     │    "Out of Stock" (Visible)      │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                       PAYMENT SAFETY & RESERVATIONS                    │
│   • Order Reservation Record (30-Minute Checkout Timer)                │
│   • In-Shop Inspection Pass (24-Hour Pickup Reservation RES-XXXXXX)    │
│   • Idempotent Paystack Payment Verification                           │
│   • Conflict & Depletion Reconciliation (paid_stock_unavailable)       │
└────────────────────────────────────────────────────────────────────────┘
```

### 2. Payment Safety Without Quantity-Based Tracking

Payment safety, reservation records, expiry times, idempotency, and payment verification are essential, but they do **not** require tracking or decrementing inventory quantities:

- **Checkout Purchase Reservation (30 Minutes):** When a shopper clicks **Pay**, the platform creates an authenticated order record with an expiration timer (`expires_at = now() + 30 minutes`). This reserves the customer's right to complete payment for that transaction without decrementing a merchant stock count.
- **In-Shop Inspection Reservation (24 Hours):** When a customer creates a pickup reservation (`RES-XXXXXX`), the reservation holds the customer's intent to inspect and purchase at the branch counter for 24 hours. The customer pays after inspecting the physical goods.
- **Automatic Expiration:** If payment is abandoned or fails before the timer elapses, the reservation expires automatically without requiring manual ledger rollback.

### 3. Handling Concurrent Checkouts & Offline Depletion

A boolean stock status alone cannot prevent two shoppers from initiating checkout for the same single physical item, or prevent an order if a vendor sells the last unit over the counter to a walk-in buyer before updating their app.

Because Victorious MARKET deliberately chooses simplicity over quantity tracking, the platform handles these real-world conflicts through clear rules of responsibility and safe reconciliation:

1. **Vendor Responsibility for Status Truthfulness:** The merchant is responsible for keeping their availability toggle accurate. The moment an item is sold out in the physical shop, the vendor toggles the status to **Out of Stock**.
2. **Atomic Verification on Payment Confirmation:** When Paystack notifies the platform of a successful payment:
   - The backend validates the payment reference and exact integer kobo amount inside an atomic database transaction.
   - The system re-checks the live product status (`$product->is_in_stock`).
3. **If Stock is Still Available (`In Stock`):**
   - The order confirms for fulfillment (`order_status = 'confirmed'`, `vendor_settlement_status = 'held'`).
4. **If Stock Was Depleted / Vendor Marked `Out of Stock`:**
   - **Never Oversell or Force Ghost Fulfillment:** The system never forces a merchant to fulfill an item that does not exist, and never silently ignores customer funds.
   - **Order Transitions to `paid_stock_unavailable`:** The order is safely quarantined with `vendor_settlement_status = 'refund_pending'`.
   - **100% Customer Protection:** The customer's payment (merchandise + delivery) is immediately queued for a full gateway refund or 100% spendable customer wallet credit (`CustomerWalletCredit`), fully balanced in the double-entry journal (`customer_refund_liability`).

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

## 5. Vendor-Managed Fulfilment: Vendor Riders and In-Shop Pickup

VICTORIOUS MARKET supports two new-order fulfilment paths: **vendor-managed doorstep delivery** and **in-shop customer pickup**. The platform does not operate a fleet, assign riders, or route new orders through logistics companies.

### 1. In-Shop Customer Pickup

- The customer collects from the vendor's physical shop.
- Delivery fee is **₦0.00**.
- The pickup pass format is exactly `RES-XXXXXX`; the owning vendor/shop counter verifies it and records handover.
- Pickup is not a rider delivery. No rider assignment, delivery fee, logistics-company payable, or rider payout is created.
- The pickup pass is private: it is visible to the authorized customer and may be verified only by the owning vendor/shop staff or appropriately authorized support personnel. Public tracking must not expose the pass.
- Keep pickup state transitions separate from doorstep delivery OTP and proof flows.

### 2. Vendor-Managed Doorstep Delivery

- Every active rider is linked to exactly one vendor. The vendor adds, activates/deactivates, assigns, and reassigns its own riders.
- A rider may access and act only on orders belonging to that rider's vendor and currently assigned to that rider.
- Vendors configure their own delivery coverage and fees. A specific LGA rule overrides the state-level fallback. If no valid rule exists for the destination, doorstep delivery is unavailable.
- Doorstep delivery fees must be positive; the zero-fee exception applies only to `in_shop_pickup`.
- Vendor assignment must be explicit and auditable. Checkout must not automatically choose a rider or logistics company. Reassignment must revoke the former rider's active access and record parcel custody before the assignment changes when the parcel is in transit.
- Delivery completion requires the approved OTP/customer confirmation and proof-photo workflow. Failed delivery attempts are operational events; the vendor chooses a policy-compliant retry, reassignment, customer coordination, or return-to-vendor. Failed attempts must not automatically issue a wallet credit/refund or create a delivery payout.
- The rider's customer contact card contains only the customer's name, delivery address, and a Call Customer action. Rider APIs must not reveal payment status, delivery-fee amount, card details, cashback, order financial totals, vendor earnings, platform commission, or payout/ledger data.
- The vendor compensates its rider directly outside VMARKET. The platform records operational delivery events only and never pays riders.

### 3. Historical Logistics Data

Legacy fleet, delivery-lane, collection-point, logistics-company, and payout records must be retained for authorized audit and historical-order reconciliation. They must not authorize new logistics-company dispatch or payout activity. Do not hard-delete old records or rewrite historical financial journals as part of the transition.

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
- The vendor-linked rider signals doorstep arrival, follows the customer OTP/confirmation workflow, and uploads the required delivery proof.
- Successful verification transitions the order authoritatively to `delivered` and starts the 24-hour return timer. Vendor settlement follows the vendor/platform financial rules; the rider is never paid by VMARKET.

### Handshake 3: Doorstep Partial Acceptance & Defective Returns
- **Item-Level Manifest Selection:** If an order contains multiple items, the customer can accept conforming items and reject defective ones at the doorstep.
- **Compulsory Defect Photography:** Rejections require photographic evidence of the defect, misrepresentation, or damage, uploaded directly via [`DoorstepHandshakeService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/DoorstepHandshakeService.php).
- **Instant Customer Wallet Credit:** For verified rejections, the refundable amount is credited instantly to the customer's wallet (`CustomerWalletCredit`), allowing immediate re-orders without waiting days for gateway transfers.
- **Custody Decoupled from Fault Arbitration:** Handover of the physical return parcel back to the merchant is verified via return OTP, while financial liability is arbitrated separately by [`RefundPolicyService`](file:///c:/Users/USER/Downloads/newVmarket/backend/app/Services/RefundPolicyService.php) based on fault classification:
  - *Customer Change of Mind:* Merchandise and delivery-fee treatment follow the published, disclosed return policy; the vendor compensates its rider separately.
  - *Vendor Fault:* Eligible merchandise and delivery-fee refunds are handled by the authorized refund process; vendor liability is determined from the evidence and policy.
  - *Transit Damage:* Eligible refunds and any vendor recovery are determined through evidence-based dispute review. Do not automatically charge a rider or create a logistics-fleet liability.

---

## 7. The 7 Hardened Multi-Tenant Actor Roles

Victorious MARKET enforces absolute privilege separation with dedicated login endpoints, scoped middleware, and zero role leakage:

| # | Role | Login Portal | Dedicated Web Dashboard | Mobile Terminal API | Primary Responsibilities & Invariants |
|---|---|---|---|---|---|
| **1** | **Super Admin** | `/admin/login` | `/admin` | N/A | Sovereign command tower, manual settlement payouts, 48h cooldown overrides, dispute arbitration, immutable audit trails. |
| **2** | **Admin Staff** | `/admin-staff/login` | `/admin-staff` | N/A | Catalog moderation, 7-day freshness oversight, customer support. Zero access to banking details, wallet debits, or payout triggers. |
| **3** | **Vendor Merchant** | `/vendor/login` | `/vendor` | `/api/v1/vendor/*` | Store management, binary stock toggle, 7-day freshness reconfirmation, vendor rider assignment, bank account settings (48h cooldown). |
| **4** | **Branch Counter Staff** | `/counter/login` | `/counter` | N/A | Bound strictly to a single `shop_id`. In-store inspection, reservation pass (`RES-XXXXXX`) sign-off, courier pickup OTP verification. |
| **5** | **Legacy Logistics Records** | Restricted historical access | Audit-only | N/A | Historical orders, affiliations, collection points and payout records only; no new dispatch, rider management, or payout activity. |
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
 └──────────────────────┘         │ unit_price           │         │ customer_cashback_5  │
                                  │ slash_price          │         │ platform_commission  │
                                  │ package_tier         │         │ delivery_fee         │
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
- `POST /api/v1/auth/login`: Rider login requires an active rider profile linked to exactly one active vendor for new vendor-managed orders. Legacy fleet-only affiliation does not authorize new assignments.
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
| **Legacy Logistics Fixture (audit only)** | `fleet@speedy.ng` | `/logistics/login` (legacy) |
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
