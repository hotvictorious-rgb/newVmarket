# Victorious MARKET: Comprehensive Master Operational & Architectural Blueprint

> **Binding active business rules:** [Active Business Rules](docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md). This blueprint must be read consistently with that policy; any older Plaza-only, pre-payment assignment, flat logistics-rate, or automatic-refund wording is superseded by the active rules. Documentation changes do not mean code is implemented or verified.

This document provides an exhaustive, end-to-end engineering and operational breakdown of **Victorious MARKET**. Built specifically for the economic, geographic, and physical realities of **Uyo, Akwa Ibom State**, this system replaces open-market chaos with a deterministic, high-margin retail and logistics engine. Every component—from database schema decisions to physical Keke loading—is designed to protect working capital, eliminate inventory shrinkage, and maximize speed.

---

## 1. System Philosophy & Architectural Foundation

Most e-commerce ventures in Nigeria fail because they import foreign software assumptions: they rely on unmapped street coordinates, allow Cash-on-Delivery (COD) theft, permit riders to zig-zag aimlessly across cities, and assume open-market traders manage digital inventory like corporate warehouses.

Victorious MARKET enforces a **fail-closed, hyper-localized architecture** built on six foundational laws:

1. **The Approved Uyo Supplier Location Rule:** Every supplier must have one admin-approved shop or pickup location anywhere within the approved Uyo operating boundary. Plaza is one possible location, not a mandatory hub. The eight delivery corridors classify customer destinations and company tariffs; they do not require suppliers to be located in Plaza.
2. **SKU-Level Deterministic Sourcing:** Approved suppliers at locations throughout Uyo may offer products. Stock availability, pricing and penalty rules operate at the individual product (`product_id` / supplier offer) level, never as a blanket shop-wide guess.
3. **One Order, One Rider Accountability:** A customer order—even if it contains items sourced from multiple approved supplier locations—is owned from pickup to doorstep by **one single delivery person**. Splitting a single customer's order across multiple riders is prohibited.
4. **The "Bulky Contagion" & Keke Isolation Law:** If a customer order contains even one bulky item (`is_bulky = true`), the *entire order* becomes classified as bulky, is forced onto a **Keke (Tricycle)**, and locks that Keke into a **100% exclusive trip** for that customer alone.
5. **Evidence at physical handoffs:** Supplier pickup requires the prescribed one-time pickup OTP and mandatory pickup photo at each supplier location. Customer doorstep completion requires the customer OTP and mandatory geo-tagged doorstep photo. Other status and financial transitions must satisfy their own authorization, evidence, payment and idempotency rules; OTP/photo proof is not a universal prerequisite for unrelated status changes.
6. **The Zero-Automated-Outflow Law:** Money NEVER leaves the platform or payment gateway automatically. Zero automated Paystack refunds; zero automated bank transfers. 100% of outflows require manual, persisted human Super Admin review, authorization, and manual dispatch initiation via the Admin Command Tower.

---

## 2. Phase 1: Customer Discovery, Cart Mechanics, & Checkout Gateway

### 2.1 Catalog Structure, High-Converting Media & Product Attributes

When customers browse `victoriousmarket.com.ng`, they do not see chaotic multi-vendor listings; they experience a single, ultra-fast, authoritative Victorious MARKET retail storefront. Behind the scenes, each canonical product record contains rich operational, media, and SEO metadata:

* **Retail Selling Price (`unit_price`):** The customer-facing price set by Victorious MARKET.
* **Payload Classification (`is_bulky`):** A strict boolean flag (`true` or `false`). Items like smartphones, chargers, blenders, and clippers are `false` (Standard/Small). Items like 4x6 virgin foam mattresses, standing gas cookers, large generators, and deep freezers are `true` (Bulky).
* **Multimedia & Embedded YouTube Unboxing Video (`youtube_video_id`):** 
  - Admin can attach an official YouTube video/Shorts link to any product.
  - On the customer product page, an ultra-fast, lightweight facade video player is embedded. Customers can watch real-world unboxings, physical quality reviews, and product demonstrations right on the page without sacrificing page load speeds.
* **Mobile-First High-Converting Product Page Architecture:**
  - **Blazing Fast Performance:** Zero heavy third-party tracker bloat; optimized lazy-loaded WebP images; lightweight vanilla CSS.
  - **Sticky Bottom Action Bar:** On mobile screens, an ergonomic sticky bar displays the price, quantity selector, and high-contrast "Buy Now" / "Add to Cart" buttons for frictionless thumb access.
  - **Conversion Boosters:** Verified Retailer Trust Badge (*"Sold & Guaranteed by Victorious MARKET Uyo"*), Live In-Stock Indicator (*"In Stock in Uyo — Order before 5 PM for same-day corridor delivery"*), 5% Cashback Highlight (*"Earn ₦X Cashback on this item"*), and an Instant Landmark Delivery Fee Estimator directly on the page.
* **100% SEO Optimization Standards (Storefront Exclusive):**
  - **Public Storefront Only:** 100% SEO indexing, Google ranking, Schema.org JSON-LD structured data, and OpenGraph WhatsApp/Facebook previews apply **strictly and exclusively** to the customer-facing storefront (`/`, `/products/*`, `/categories/*`, `/legal/*`).
  - **Zero Backstage Indexing (Triple-Shield Isolation):** Under no circumstances may search engines crawl, index, or cache the Super Admin Command Tower, Supplier Terminal, or 3PL Logistics Portal.
    1. `robots.txt` strictly disallows `/admin/`, `/vendor/`, `/supplier/`, `/logistics/`, `/rider/`, and `/api/`.
    2. All authenticated portal layouts inject `<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">`.
    3. Server middleware emits `X-Robots-Tag: noindex, nofollow` HTTP security headers across all operational portals.
  - **Automated Schema.org JSON-LD:** Implements rich structured data (`Product`, `Offer`, `AggregateRating`, `Brand`, and `InStock` availability) for rich Google search snippets.
  - **OpenGraph & Twitter Cards:** Dynamic social sharing meta tags optimized for crystal-clear WhatsApp, Facebook, and Twitter image/price previews when customers share products.
  - **Single `<h1>` Hierarchy:** Strict semantic HTML5 tags with descriptive alt attributes on all product photos.
* **Supplier Offer Management & Negative-Margin Shield:**
  - Suppliers never create public listings; they submit confidential wholesale offers against canonical products: **Wholesale Cost (`cost_price`)**, **Stock Quantity (`stock_quantity`)**, and **Stock Status (`in_stock` / `out_of_stock`)**.
  - **Negative-Margin Guard:** The system programmatically blocks or flags any scenario where a supplier's wholesale cost exceeds the public retail selling price (`cost_price >= unit_price`), alerting the Super Admin immediately.
  - **Supplier Product Request Feature:** If a merchant stocks an item not yet in the master catalog, they click *"Request Product Addition"*; Admin reviews, adds the canonical listing with studio photos/YouTube video, sets the retail price, and opens it for supplier offers.
* **Active Supplier Backing and Listing Lifecycle:** A product is purchasable only while at least one active, verified supplier at an admin-approved Uyo location has an eligible, unpenalized `in_stock` offer. If all eligible offers are out of stock or otherwise ineligible, the system automatically hides the listing from the storefront to prevent ghost sales. An authorized admin may manually delist at any time.

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

$\text{Pre-discount Checkout Subtotal} = \sum(\text{Item Retail Price} \times \text{Quantity}) + \text{Customer Delivery Fee}$

Apply only the discounts and cashback redemption permitted by the active checkout policy when deriving the final amount due.

* **Mandatory Customer Account Registration:** Guests are permitted to browse the storefront catalog, view products, calculate delivery estimates, and add items to cart freely. However, **guest checkout is strictly prohibited**. Before proceeding to checkout or initializing a payment, the customer must register or log in. This ensures that every order is bound to an authenticated customer record with a verified phone number for the rider's mandatory pre-departure call and secure Delivery OTP dispatch.
* **No Cash-on-Delivery (COD):** The customer must complete payment upfront through an enabled approved method: Paystack (card or Paystack's standard bank-transfer channel) or the separate Manual Bank Transfer path to the receiving account configured by an authorized admin. Manual Bank Transfer is not a Paystack transaction.
* **Payment Verification Gate:** A browser redirect, screenshot or payment initialization does not trigger fulfilment. Paystack payment requires trusted provider verification with reference, order binding, exact amount, NGN currency and idempotency. Manual Bank Transfer requires the customer to upload a transfer screenshot/receipt against the order for admin review. The upload is supporting evidence only: an authorized admin must independently verify actual receiving-account credit, match amount/order/transaction evidence, and explicitly approve it with an audit record. Securely store the evidence against the order, restrict access, and preserve resubmission/review history. Only verified and approved payment may enter the paid processing state and proceed to supplier sourcing. See [Manual Bank Transfer and Payment-Provider Policy](docs/architecture/MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md).

---

### Supplier account governance

Supplier account creation, approval, activation and suspension are controlled exclusively by an authorized Victorious MARKET admin. Supplier self-registration is not permitted. A suspended supplier is immediately ineligible for new sourcing/allocation. Existing paid or in-flight orders must be reviewed and explicitly resolved by an authorized admin; suspension must not silently delete history or automatically cancel/refund orders. Reactivation requires an authorized admin action and an audit record. Follow the [Active Business Rules](docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md) for the binding policy.

## 3. Phase 2: The Automated Product Sourcing Engine (Supply Side)

Only after the backend independently verifies successful Paystack payment does it execute the **Multi-Product Consolidated Sourcing Pipeline**. Before payment, any cart or feasibility check is non-binding: it must not assign or reserve suppliers, create supplier payables, or send binding order notifications. After payment, revalidate current stock and eligibility before allocating. The goal is to maximize fulfilment success, minimize the number of approved supplier pickup locations, and control wholesale cost.

### 3.1 Step 1: Hard Pass/Fail Filters (SKU-Level Elimination)

For every distinct `product_id` in the customer's order, the engine queries the `supplier_offers` table and immediately disqualifies any supplier offer that fails any of these four mandatory checks:

1. **KYC & Geographic Boundary Check:** The supplier account must be active and admin-approved, with an admin-approved operating/pickup location within Uyo. Any separate KYC check must be part of the documented supplier-approval policy; do not invent an additional undocumented eligibility gate.
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

### 3.3 Step 3: Confirmation Clock, Counter Breach, Re-Route & Admin Override

1. **Operating Confirmation Window (8:00 AM – 6:00 PM):** Winning suppliers receive an instant portal alert with a **60-minute confirmation timer**. Timers run strictly between 8:00 AM and 6:00 PM. After 6:00 PM, timers pause; merchants can view orders, and countdowns restart fresh at 8:00 AM the following morning.
2. **If Supplier Rejects or Commits Shop-Floor Counter Breach:**
* If a supplier marks "Unavailable" or fails to hand over an item at pickup because it was sold to a walk-in cash customer:
* The system immediately applies a **24-hour SKU penalty** against that supplier.
* The sourcing engine immediately **auto-routes** to the next-best eligible in-stock supplier in Uyo LGA.
* The system instantly alerts the **Super Admin Command Tower**.
3. **The Permanent Admin Override Authority:** At any point, an authorized Victorious MARKET Super Admin can override the automated sourcing assignment or failover match, manually reassigning the order line item to any verified supplier in Uyo with a mandatory audit reason. The system preserves the original audit trail and records balanced compensating entries for any changed wholesale payable.

---

## 4. Phase 3: Major-Road Logistics & Dispatch Architecture (Delivery Side)

Once every item in the customer order is confirmed ready by the approved supplier(s), the order moves to the **Logistics Dispatch Queue**. Road delivery transit operates strictly from **8:00 AM to 5:00 PM** to ensure rider safety, optimal visibility, and eliminate evening delivery security risks along Uyo road corridors. Victorious MARKET integrates accredited **Third-Party Logistics (3PL) Companies** operating in Uyo.

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
2. **Supplier Pickup Photo:** Emmanuel captures the mandatory pickup photo for the handover at Shop Alpha, showing the items/manifest being collected at that location. The photo is linked to the pickup stop; gallery uploads are disabled if the approved implementation supports enforced live capture.
3. **Supplier Pickup OTP:** Shop Alpha reads out the unique one-time **Supplier Pickup OTP** for this order's pickup stop. Emmanuel enters it into his app. The OTP verifies the handover at this supplier location, not each item as a separate pickup event.
* *System Action:* The database timestamps the handoff, shifts liability for Item 1 from Shop Alpha to the 3PL company, and marks Shop Alpha's portion as `picked_up`.
4. **Arrival at Shop Beta:** Emmanuel travels to the second approved supplier pickup location, loads the items for that stop, captures the mandatory pickup photo for the handover and enters that stop's one-time **Supplier Pickup OTP**.
5. **Transit Unlock:** Only after *every single shop stop* for Order #800 has a verified Pickup OTP and Photo does the order status transition to `in_transit_on_corridor`, unlocking the customer's doorstep details.

### 5.2 Stage 2: Doorstep Handover Protocol

1. **Arrival on Corridor:** Emmanuel drives straight down the designated road corridor (e.g., *Oron Road*) to the customer's address.
2. **Doorstep Photo Proof:** Before final handover, Emmanuel captures a mandatory, timestamped, geo-tagged **Doorstep Delivery Photo** showing the delivered items at the customer's premises.
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
   - Each supplier-payable payment request/disbursement must be at least ₦5,000 and no more than ₦1,000,000 at a time. FIFO/oldest-first selection is prohibited. Use an authorized, auditable non-FIFO selection process; do not invent an automatic alternative ranking or silently split an over-cap request. Admin approval, reconciliation, idempotency and provider-confirmed completion remain required.
2. **Logistics-company payable:** Calculate the payable from the assigned approved logistics company's admin-configured base payable for the applicable corridor, plus that company's configured bulky-unit surcharge multiplied by the applicable bulky-unit count. This is a separate tariff schedule from the customer delivery charge. Never derive it from a percentage of customer delivery revenue. Snapshot company, corridor, tariff/version, base, bulky quantity/rate and total at assignment; later tariff changes must not silently rewrite it. If payable exceeds delivery revenue, record the difference as a Victorious MARKET-funded subsidy/expense. The logistics company—not automatically the rider—is the payee.
3. **Victorious MARKET margin and expenses:** Keep merchandise revenue, supplier purchase cost, customer delivery revenue, logistics cost, gateway fees, discounts, refunds, cashback liability, subsidy and operating expenses distinct. Merchandise spread is not net profit until attributable costs and liabilities are reconciled.

### 6.2 Supplier and logistics payment controls & The Zero-Automated-Outflow Law

A payable becoming eligible is not the same as a payment being approved, initiated, or confirmed. Every payout/payment and refund operation must strictly enforce the **Zero-Automated-Outflow Law**:
- **Zero Automated Outflow Mandate:** No cron, queue worker, or webhook may ever execute an automated Paystack refund or automated bank transfer. All money outflows require recorded human Super Admin review and manual dispatch.
- **Settlement Dependency:** Supplier and fleet disbursements take place strictly Monday through Friday and only after Paystack collections have fully settled into the platform's corporate settlement account.
- **Auditable Non-FIFO Controls:** Every payout request must adhere to the ₦5,000 minimum and ₦1,000,000 maximum per batch, selected through an authorized non-FIFO audit process.
Provider timeout remains uncertain until reconciled; payout completion requires authoritative provider confirmation. Preserve legacy vendor earning/payout records as historical records and do not relabel them as supplier costs without an approved accounting migration.

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

---

## 8. Comprehensive Security, Anti-Fraud & Data Protection Architecture

To ensure institutional stability and eliminate all attack vectors, the platform enforces 5 security pillars:

1. **Financial Gateway Security:**
   - Strict HMAC-SHA512 verification on 100% of incoming Paystack webhooks.
   - Atomic database idempotency locks preventing double-spend or double-allocation.
   - 100% backend price calculation—zero client-side price trust.
2. **Delivery Cryptography & Anti-Theft:**
   - 4-digit Shop Pickup OTP shifting custody to 3PL.
   - 6-digit Customer Doorstep Handshake OTP.
   - 3-attempt brute-force OTP lockout with 15-minute freeze and Super Admin alert.
   - Live hardware camera enforcement (`capture="environment"`) blocking gallery uploads.
3. **Access Control & Financial Redaction:**
   - Strict RBAC separating Customer, Merchant, Rider, and Super Admin privileges.
   - Complete redaction of retail selling prices and platform profit margins from rider views.
   - Brute-force login rate limiting (max 5 attempts per minute per IP).
4. **Browser & Network Hardening:**
   - Content-Security-Policy (CSP) restricting scripts to self, Paystack, and YouTube video embeds.
   - `X-Frame-Options: SAMEORIGIN` clickjacking prevention.
   - `X-Content-Type-Options: nosniff` MIME sniffing protection.
   - HSTS HTTPS enforcement and mandatory CSRF token validation on all state-changing forms.
5. **Immutable Administrative Audit Trail:**
   - Every sensitive administrative event (supplier overrides, refund approvals, payout dispatches, tariff changes, and merchant suspensions) is permanently logged with `admin_id`, `ip_address`, `timestamp`, `action`, `reason_code`, and `before/after snapshots`.
   - Audit records are append-only and cannot be altered or purged.


## Owner-approved policy addendum — 23 October 2026

The [Approved Exception and Reverse-Logistics Policies](docs/architecture/APPROVED_EXCEPTION_AND_REVERSE_LOGISTICS_POLICIES_2026-10-23.md) are binding architectural constraints. Implement supplier-stockout failover before admin-managed cancellation; partial-fulfilment acceptance/rejection with a 24-hour response window and silence treated as rejection; distinct full-order versus item-level doorstep rejection; post-purchase defective-item return collection on compatible return journeys without a customer return fee; no redelivery; no independent logistics-company order marketplace; and an admin-maintained major-road → child-landmark → written-address hierarchy. Preserve paid-order destination/tariff snapshots and existing one-approved-logistics-company/one-rider accountability for each VM order. Refunds and supplier/logistics financial adjustments must remain auditable and non-automatic. Documentation does not prove that these constraints are implemented in code.


## Owner-approved Order State, Custody and Recovery Policy — 23 October 2026

The [Order State, Custody and Recovery Policy](docs/architecture/ORDER_STATE_CUSTODY_AND_RECOVERY_POLICY_2026-10-23.md) is a binding cross-cutting design constraint for lifecycle separation, verified-payment recovery, concurrency safety, custody evidence, exception ownership and reconciliation. It supplements but does not override the Active Business Rules or specific approved exception/refund outcomes. Preserve one logistics company/rider per normal order, independent logistics tariffs, item-level partial fulfilment, distinct absence/rejection/defect paths and the Zero-Automated-Outflow Law. Policy approval is not implementation proof; schema, code, admin flows and recovery tests must be audited separately.


### Payment-method exclusivity invariant
Only one of Paystack and Manual Bank Transfer may be active globally at a time; an authorized admin may activate either one or deactivate both. The server must enforce this atomically and reject dual activation. Each order is permanently bound to one method and cannot mix, top up, verify, settle or refund through the other. Disabling a method does not alter existing payment attempts. Pending or uncertain attempts must be reconciled before an audited reset. See [Manual Bank Transfer and Payment-Provider Policy](docs/architecture/MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md).
