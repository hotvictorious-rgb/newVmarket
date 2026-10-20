# Victorious MARKET (newVmarket)

> **Multi-vendor commerce platform with vendor-managed delivery**  
> **Brand Promise:** Your Trusted Online Market for Quality Products  
> **Operating Philosophy:** Trust First. Quality Always. Accountability at Every Stage  
> **Fulfilment rule:** Each vendor manages its own riders and delivery; customers may choose zero-fee in-shop pickup.  
> **Verification:** Run current CI and the full backend suite before release; historical test counts are not a current release guarantee.

---

## Executive Summary

**Victorious MARKET** is a multi-vendor commerce platform built on Laravel. It governs vendor onboarding, catalog quality, checkout, payment verification, customer protection, refunds, vendor settlements, and auditability. It does **not** operate a central logistics company, fleet, dispatch hub, route-dispatch service, or rider-payout programme for new orders.

The current operating model is vendor-managed fulfilment: each vendor registers/manages its own riders and manually assigns its own child vendor orders. Riders are operational-only and are compensated directly by their vendor outside platform financial systems. Customers can instead choose **in-shop pickup**, which costs exactly zero delivery fee and uses a `RES-XXXXXX` pickup pass verified at the vendor's shop. Legacy logistics records remain historical/audit data and must not receive new fulfilment assignments.

Core controls include:

1. **Verified payments and double-entry accounting:** Paystack outcomes are verified server-side and reconciled with internal financial operations and journals.
2. **Separate financial allocation bases:** Eligible merchandise uses a 90% vendor / 5% customer cashback / 5% platform allocation. The delivery fee uses a separate 90% vendor / 10% platform allocation. Cashback excludes delivery fees; rider compensation is never a platform payable.
3. **Idempotent refunds and settlement:** Refunds, reversals, cashback, and vendor settlements must be authorized, bounded by actual captured payment and prior operations, and reconciled against gateway outcomes.
4. **Inventory and late-payment safety:** A late payment after stock becomes unavailable is verified and routed through a controlled order-review/refund workflow. No handler may promise an immediate spendable wallet credit without the approved financial workflow.
5. **7-Day Marketplace Freshness Invariant:** Vendors reconfirm catalog availability every 7 days where this feature is enabled.
6. **Audited custody events:** Delivery attempts, handover proof, return-to-vendor, and pickup confirmation are recorded as operational evidence. A rider-triggered event must not itself grant financial permissions or automatically create spendable customer credit.
7. **Vendor-managed fulfilment:** No new order may be routed to a logistics-company fleet. Vendor riders see only assigned vendor orders and minimal delivery information.

---

## Table of Contents

1. [Architectural Overview & Core Invariants](#1-architectural-overview--core-invariants)
2. [Financial Controls and Commercial Allocation](#2-financial-controls-and-commercial-allocation)
3. [The Two-Status Inventory Architecture & Payment Safety Reservations](#3-the-two-status-inventory-architecture--payment-safety-reservations)
4. [7-Day Product Freshness Lifecycle](#4-7-day-product-freshness-lifecycle)
5. [Vendor-Managed Delivery and In-Shop Pickup](#5-vendor-managed-delivery-and-in-shop-pickup)
6. [Custody Evidence, Handover, and Returns](#6-custody-evidence-handover-and-returns)
7. [Actor Roles and Access Boundaries](#7-actor-roles-and-access-boundaries)
8. [Paystack Escrow, Webhook Hardening & Crash Recovery](#8-paystack-escrow-webhook-hardening--crash-recovery)
9. [Database Architecture & Core Entities](#9-database-architecture--core-entities)
10. [REST API Specifications](#10-rest-api-specifications)
11. [Automated Verification & Test Suite Proofs](#11-automated-verification--test-suite-proofs)
12. [Local Setup & Shared Hosting Deployment](#12-local-setup--shared-hosting-deployment)

---

## 1. Architectural Overview & Core Invariants

Victorious MARKET enforces security and accounting invariants at the database transaction layer using PHP's `bcmath` extension, database constraints, and cryptographic payment-provider verification.

### Current actor model

- **Platform administrators:** vendor verification, catalog governance, security, dispute review, reconciliation, and authorized financial controls. They do not dispatch riders or manage rider compensation.
- **Vendor:** manages its catalog, orders, delivery areas/fees, and riders. The vendor manually assigns only its own child orders to its own linked riders.
- **Vendor-linked rider:** operational-only; acts only on assigned orders for the owning vendor. The rider receives no platform wallet, earnings balance, payout, commission, or financial data.
- **Counter staff:** may perform authorized vendor-shop pickup verification within their shop boundary.
- **Customer:** shops through one parent checkout with separate vendor child orders, pays through Paystack card or standard bank transfer at launch, and chooses eligible doorstep delivery or zero-fee in-shop pickup. COD is disabled at launch.

Historical logistics-company/fleet records and routes may remain for audit and migration compatibility, but they are not an active delivery option for new orders.

### Institutional invariants

1. **Exact financial arithmetic:** money allocation, kobo conversion, fees, discounts, and splits use decimal-safe arithmetic.
2. **Vendor-only rider ownership:** every active rider eligible for new work must be linked to exactly one active vendor. Fleet-only or ambiguous legacy profiles cannot receive new assignments.
3. **Vendor-defined delivery:** supported destinations and delivery fees are configured by the vendor and snapshotted to each child order at checkout.
4. **Pickup separation:** `in_shop_pickup` always costs zero delivery fee, uses a `RES-XXXXXX` pass verified by the vendor, and does not require a rider.
5. **Rider privacy:** rider responses may include only the customer's name, delivery address, and a protected Call Customer action. They must not expose the raw phone number, payment status, amount due, delivery-fee financial fields, card/bank details, cashback, commission, vendor settlement, or payout information.
6. **Operational/financial separation:** delivery, failed attempts, proof, and custody return are operational events. They do not themselves approve refunds, create spendable wallet credit, post financial journals, or release vendor funds.
7. **Legacy compatibility:** historical logistics data is preserved for authorized audit/reconciliation but cannot drive new assignments or new logistics payouts.

---

---

## 2. Financial Controls and Commercial Allocation

Victorious MARKET uses verified payment-provider outcomes, internal financial operations, and double-entry journals. Internal ledger labels alone do not constitute a legally regulated escrow service.

### Adopted allocation rules

```text
Eligible merchandise value (100%)
  ├── 90% -> vendor merchandise allocation
  ├──  5% -> customer cashback allocation (eligible merchandise only)
  └──  5% -> platform merchandise share

Vendor-configured doorstep delivery fee (100%)
  ├── 90% -> owning vendor delivery allocation
  └── 10% -> platform delivery-fee share

Rider compensation -> paid directly by the owning vendor outside Victorious MARKET
```

- Merchandise and delivery are separate allocation bases; do not calculate a flat commission on the full cart total.
- Cashback excludes delivery fees and remains subject to the approved maturation, refund, and reversal rules.
- A delivery fee is not a rider payout or logistics-company fare. The platform creates no rider payable, rider wallet, rider earnings balance, or rider payout request for new vendor-managed delivery.
- Refunds must reverse only the applicable approved components from the actual order allocations, taking account of discounts, previous refunds, cashback, and vendor amounts already settled.
- A refund request or rider handover does not mean the gateway refund is complete. Reconcile authoritative Paystack outcomes before reporting completion.
- **Refund responsibility:** Victorious MARKET executes approved customer refunds through the authorized financial workflow. Vendor recovery is a separate, auditable obligation where the approved reason and actual allocation make the vendor responsible; it must not delay or misstate the customer refund.
- **Vendor settlement timing:** no vendor proceeds become eligible before verified successful delivery or confirmed customer in-shop pickup. Applicable refund-window, dispute/return, vendor-eligibility, bank-security, authorization, and proof controls still apply; evaluate each vendor child order independently.

---

## 3. The Two-Status Inventory Architecture & Payment Safety Reservations

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
   - **100% Customer Protection:** The verified payment enters an explicit paid-stock-unavailable review/refund workflow. Do not promise or issue an immediate spendable wallet credit automatically. At launch, refunds return only through the original Paystack payment channel; wallet-credit refunds are deferred and disabled in UI, API and service authorization. All refunds follow the authorized financial-operation process with exact amounts, idempotency, and reconciliation.

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

## 5. Vendor-Managed Delivery and In-Shop Pickup

The authoritative requirements are in [`.ai/BUSINESS_RULES.md`](.ai/BUSINESS_RULES.md). There are two supported fulfilment choices for new orders: vendor-managed doorstep delivery and customer in-shop pickup. **Third-party logistics fleets are not an active fulfilment option for new orders.**

### 1. Vendor-managed doorstep delivery
- The vendor adds/manages its riders and manually assigns its own child vendor orders to them.
- Every active rider must belong to exactly one active vendor. A rider cannot be shared across vendors, linked to a fleet for new work, or access unassigned/other-vendor orders.
- Vendors configure the destination areas and delivery fees they serve. A valid explicit LGA fee takes precedence over a state-level fallback. Unsupported destinations fail closed.
- The selected fulfilment type, destination, and agreed delivery fee are snapshotted on each child vendor order.
- Reassignment is allowed before custody. After pickup/custody, use an audited return or custody-transfer process.
- The vendor pays the rider directly outside Victorious MARKET. No rider wallet, earnings, payable, or payout is created by the platform.

### 2. Customer in-shop pickup
- Customer collects the order at the owning vendor's physical shop/counter.
- Delivery fee is exactly **₦0.00**, enforced by the backend regardless of client input.
- Pickup uses the **RES-XXXXXX** pass, verified by the vendor at the counter.
- Pickup has a distinct status flow. It does not require a rider assignment or create delivery compensation/payout.

### 3. Delivery privacy and operational evidence
- Rider access is limited to assigned orders belonging to the rider's vendor.
- The rider may see only the customer's name, delivery address, and a protected **Call Customer** action. Do not expose the raw customer phone number in JSON, page markup, logs, or exports.
- Riders must not see payment status, amount paid/due, delivery-fee financial fields, refunds, customer wallet/card/bank data, cashback, commission, vendor settlement, or payout information.
- Delivery OTP/pass secrets must not be included in list/history responses or logs. A verified handover is operational evidence; it does not itself approve a refund or create a spendable wallet credit.
- Failed attempts must record reason, time, actor, proof, and custody state. At the configured threshold, the parcel returns to its owning vendor. Refunds or customer remedies go through a separate authorized financial workflow.

### Legacy fleet records
Historical logistics-company profiles, routes, collection points, fleet assignments, payout requests, and financial entries remain preserved for authorized audit/reconciliation. They must not receive new assignments or new financial activity under vendor-managed fulfilment. Do not delete historical records as part of this transition.

---

## 6. Custody Evidence, Handover, and Returns

Custody workflows must distinguish operational evidence from financial decisions.

- For doorstep delivery, the customer uses the configured private, single-use verification code/pass and the rider submits required delivery proof. Secrets are entered only into the action that validates them and must not be returned in rider order lists or history.
- A rider can record which items were accepted or rejected and submit the required evidence. The rider action records the operational event only; it must not directly approve a refund, create a spendable wallet credit, post refund journals, or settle a delivery fee.
- Return-parcel custody is recorded separately from deciding fault or approving a financial remedy. The owning vendor confirms receipt through the approved return process.
- Return/refund review considers the affected child order/items, actual payment, discounts, applicable merchandise and delivery-fee allocation, cashback, previous refunds, and vendor settlement already made.
- Customer wallet credit is not an automatic consequence of a rider's handover or failed-delivery event. Wallet-credit refunds are disabled at launch; only original-Paystack-channel refunds are enabled. Any future wallet-refund capability requires a separate owner-approved release, explicit customer consent, timestamped consent, admin authorization, and audit logging.
- Delivery fee refundability is reason-specific and subject to the published customer policy and applicable law. Do not assume that every failure retains or refunds the fee.
- A failed delivery is not a successful delivery and does not itself trigger a refund, payout, or release of vendor funds.

---

## 7. Actor Roles and Access Boundaries

Victorious MARKET separates platform administrators, vendors, vendor-linked riders, counter staff, and customers. Legacy logistics-company accounts may remain in historical data but are not an active fulfilment role for new orders:

| # | Role | Login Portal | Dedicated Web Dashboard | Mobile Terminal API | Primary Responsibilities & Invariants |
|---|---|---|---|---|---|
| **1** | **Super Admin** | `/admin/login` | `/admin` | N/A | Sovereign command tower, manual settlement payouts, 48h cooldown overrides, dispute arbitration, immutable audit trails. |
| **2** | **Admin Staff** | `/admin-staff/login` | `/admin-staff` | N/A | Catalog moderation, 7-day freshness oversight, customer support. Zero access to banking details, wallet debits, or payout triggers. |
| **3** | **Vendor Merchant** | `/vendor/login` | `/vendor` | `/api/v1/vendor/*` | Store management, binary stock toggle, 7-day freshness reconfirmation, vendor rider assignment, bank account settings (48h cooldown). |
| **4** | **Branch Counter Staff** | `/counter/login` | `/counter` | N/A | Bound strictly to a single `shop_id`. Verify the customer’s canonical `RES-XXXXXX` in-shop pickup pass. Rider parcel pickup/custody is a separate operational flow, not customer counter pickup. |
| **5** | **Legacy logistics records (restricted historical access only)** | No new fulfilment portal | Restricted audit/reconciliation views only | N/A | Historical records only; no new dispatch, assignments, rider management, or payouts. |
| **6** | **Vendor-Linked Delivery Rider** | `/rider/login` | `/rider` | `/api/v1/rider/*` | Operational tasks for assigned orders belonging only to the owning vendor; minimal customer data; no financial access. |
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

- **`financial_journal_entries`:** Immutable double-entry ledger for current platform/vendor/customer financial movements. Historical `logistics_payable` entries may remain for audit but must not be created for new vendor-managed deliveries.
- **`financial_operations` & `financial_reservations`:** Fenced state machine governing payout requests, pre-allocation locks, and webhook finalizations.
- **`customer_cashback_ledgers`:** Governs customer 5% cashback rewards with explicit `matures_at` timestamps bound to the 24-hour return window.
- **`customer_wallet_credits`:** Credits issued only through the authorized, auditable financial workflow. Rider-triggered handover or failed delivery must not automatically create spendable credit.
- **`vendor_recovery_ledgers`:** Tracks merchant refund liabilities when returns are approved after funds have been disbursed.
- **`admin_audit_logs`:** High-security audit log capturing all sensitive administrative and financial actions (`user_id`, `ip_address`, `user_agent`, `old_values`, `new_values`).

---

## 10. REST API Specifications

Victorious MARKET provides clean, versioned JSON REST APIs for native mobile and web clients:

### A. Customer API (`/api/v1/customer/*`)
- `GET /api/v1/customer/banners`: Active promotional banners and featured storefront items.
- `GET /api/v1/customer/orders`: Customer-owned order history and live fulfilment tracking. Any customer handover pass/OTP is returned only to the authenticated owning customer when required by the flow; it must never be included in rider list/history responses or logs.
- `POST /api/v1/customer/checkout`: Atomic cart validation, 30-min stock reservation, and Paystack intent creation.
- `POST /api/v1/customer/orders/{orderNumber}/verify-payment`: Authenticated verification and instant order confirmation.
- `POST /api/v1/customer/orders/{orderNumber}/returns`: Submit item-level return requests with photos within the 24h window.

### B. Courier Terminal API (`/api/v1/rider/*`)
- `POST /api/v1/auth/login`: Rider access must require an active rider profile linked to exactly one active vendor for new fulfilment; fleet-only/ambiguous legacy profiles are ineligible.
- `GET /api/v1/rider/dashboard`: Operational telemetry (`active_assignments_count`, `completed_trips_count`, `shift_status`). **Zero balance leakage.**
- `POST /api/v1/rider/toggle-duty`: Toggle operational availability (`ON DUTY` / `OFF DUTY`).
- `GET /api/v1/rider/orders/active`: Only assigned orders for the rider's vendor, with minimum operational data; no raw customer phone or financial fields. Customer contact is via protected Call Customer action.
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

### Historical Test Snapshot — Not a Current Release Claim

The following test counts were recorded in an earlier repository snapshot (458 tests / 2,860 assertions). They are retained as historical context only. Confirm the latest CI run and execute the full backend suite on the release candidate before claiming that the current branch passes.

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
Local seed accounts are for development only. Never deploy default credentials to production. Any legacy fleet seed persona is historical/test-only and must not be enabled for new fulfilment.

| Role | Email | Login URL |
|---|---|---|
| **Super Admin** | `superadmin@victoriousmarket.com.ng` | `/admin/login` |
| **Admin Staff** | `staff@victoriousmarket.com.ng` | `/admin-staff/login` |
| **Vendor Merchant** | `merchant@freshmart.ng` | `/vendor/login` |
| **Branch Counter Staff** | `counter@freshmart.ng` | `/counter/login` |
| **Legacy fleet persona (test/history only)** | `fleet@speedy.ng` | No new fulfilment access |
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
