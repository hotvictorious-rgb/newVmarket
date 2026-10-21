# Victorious MARKET — Retailer, Supplier and Central Fulfilment Model


> **Current delivery-first fulfilment policy (21 October 2026):** Victorious MARKET is one customer-facing retailer using private suppliers behind the scenes. Doorstep delivery is the only standard online fulfilment method. Do not offer automated in-shop pickup reservations, supplier/shop selection, or supplier-address disclosure in customer checkout. Customers who prefer pickup must contact VM support; support may confirm an exception only after checking the complete order, availability, physical readiness, approved collection point, payment arrangement and customer agreement. Never disclose a collection address or say an order is ready before confirmation. For multi-product orders, VM coordinates the complete order; customers must not be sent to multiple suppliers by default. Legacy pickup/multi-vendor code and historical records remain compatibility/audit material until safely retired; documentation alignment alone does not mean runtime routes or production legal terms have been disabled. See `docs/architecture/RETAILER_FULFILMENT_SIMPLIFICATION_2026-10-21.md` for the binding workflow and implementation acceptance criteria.

**Status:** Owner-confirmed business model baseline, 21 October 2026. This document supersedes earlier marketplace/vendor-managed-delivery assumptions for new retailer-model work. It does not assert that application code has already been migrated.

## 1. Business identity

Victorious MARKET (VM) is a centrally managed online retailer operating from Uyo, Akwa Ibom State. Customers buy from VM. Suppliers are private behind-the-scenes sources of products; they are not public marketplace sellers and do not own the customer relationship.

VM controls the public catalogue, retail prices, order acceptance, customer payment workflow, fulfilment coordination, delivery coverage, customer support, refund decisions, and financial records.

## 2. Supplier offer, pricing and catalogue workflow

Victorious MARKET operates as a Direct-to-Consumer (1P) Retailer with Just-in-Time Private Supplier Sourcing:
1. **Admin-Only Onboarding (Zero Public Self-Registration):** Suppliers must only be created and onboarded by Victorious MARKET Admin staff. Public self-registration for suppliers or vendors is strictly prohibited across all web and mobile endpoints.
2. **Wholesale Sourcing:** Suppliers provide their physical products, inventory availability, physical shop locations in Uyo, and wholesale cost prices (e.g., ₦10,000).
3. **Retail Markup:** Victorious MARKET sets the customer-facing retail selling price by adding its own profit margin (e.g., wholesale ₦10,000 + VM markup ₦4,000 = retail selling price ₦14,000).
4. **Public Product Listing:** VM creates and controls one canonical public listing. Customers only see Victorious MARKET as the seller at the retail price. Supplier identity and wholesale cost remain strictly private to VM.
5. **Multiple Supplier Offers:** When multiple suppliers offer the same product, VM links each supplier's offer (wholesale price, shop location in Uyo, stock availability) to the canonical listing. VM selects the supplier offer that is **available and cheapest** when fulfilling orders.

A supplier submission is a wholesale proposal, never an auto-published marketplace listing. VM reviews, approves, and owns all customer-facing titles, descriptions, categories, images, and prices.

## 3. Customer order sourcing and supplier selection

When a customer places an order on Victorious MARKET:
1. The customer orders the canonical product from Victorious MARKET at the published retail price.
2. For **Doorstep Delivery**, the customer pays upfront online via Paystack (+ delivery fee).
3. For **In-Shop Pickup (Reservation)**, the customer reserves online for free (₦0 upfront) to inspect at the designated supplier shop.
4. **Automated Sourcing Assignment (Cheapest + Available):**
   - As soon as the customer completes checkout (payment verified for delivery, or reservation created for in-shop pickup), the system **automatically assigns the order/item to the accredited supplier that has the product in stock and offers the cheapest wholesale cost price (`cost_price`)**.
5. **Admin Override Authority:**
   - The Admin console maintains full supervisory control: Admin can review the automated assignment and **override it at any time**, manually reassigning the order or reservation to another accredited supplier/shop (e.g., for operational balancing, branch proximity, or relationship management).
6. **Immutable Snapshot:** The order item records an immutable snapshot of:
   - Canonical product and variant;
   - Selected supplier ID and supplier shop ID;
   - Agreed supplier wholesale purchase cost (`cost_price`);
   - Customer retail selling price (`unit_price`);
   - VM gross profit margin (`unit_price - cost_price`).
7. Later edits to supplier wholesale prices never alter historical or active order snapshots.

## 4. Central operating base and service coverage

VM's central operating base is Uyo, Akwa Ibom State. VM serves destinations reliably covered by local supplier sourcing and accredited logistics partners.

Before offering delivery at checkout, VM verifies:
1. The product can be sourced from an active supplier shop in Uyo;
2. The destination is within an active logistics coverage rule;
3. An approved logistics company can serve the route;
4. The customer-facing delivery fee is confirmed and quoted.

If coverage is unconfirmed, checkout blocks delivery. VM controls delivery coverage and quotes; suppliers do not configure delivery prices.

## 5. Doorstep delivery: customer pays first, automated sourcing, admin logistics dispatch

**Binding rule: Doorstep delivery requires pre-payment online via Paystack. Strictly NO Cash on Delivery (COD).**

1. **Customer Checkout (Pay First):** The customer pays the full retail price plus delivery fee online via Paystack (card or bank transfer).
2. **Automated Sourcing Assignment:** Upon verified Paystack payment, the system automatically assigns the order to the supplier that has the item available (`is_in_stock = true`) and cheapest (`cost_price` lowest).
3. **Admin Sourcing Override:** Admin can review the auto-assigned supplier and override the assignment by selecting a different accredited supplier if necessary.
4. **Supplier Availability Confirmation:** The assigned supplier receives the sourcing notification in their portal and physically inspects their inventory to **confirm product availability**.
5. **Admin Logistics Company Assignment:** Once the supplier confirms availability, the order transitions to Admin dispatch. Admin assigns the order to an accredited **Logistics Company** (onboarded strictly by Admin; zero public self-registration).
6. **Fleet Pickup & Handover:** The Logistics Company assigns one of its active fleet riders. The rider visits the supplier's shop, picks up the package, and transports it to the customer.
7. **Customer Delivery Handshake:** Doorstep delivery completion strictly requires the server-verified customer OTP (`delivery_otp`) plus a mandatory doorstep delivery photo proof.
8. **Supplier Settlement:** Only after verified customer delivery is the supplier's wholesale payable unlocked and eligible for settlement.

## 6. In-shop pickup: automated reservation, inspect first, pay at shop to Paystack

**Binding rule: In-shop pickup allows the customer to inspect the physical item first at the supplier shop, and pay directly to Victorious MARKET via Paystack upon satisfaction.**

1. **Online Reservation (₦0 Upfront):** The customer selects "In-Shop Pickup" at checkout. No money is paid online upfront.
2. **Automated Location Assignment:** The system automatically assigns the reservation to the supplier shop that has the item available and cheapest, issuing a unique reservation pass (`RES-XXXXXX`) and customer pickup OTP (`verification_code`).
3. **Admin Location Override:** The customer sees the reservation location assigned by the system, but Admin has full authority to override and reassign the reservation to another supplier branch.
4. **Customer Journey & Direct Support:** While travelling to the shop, the customer can **call or chat Victorious MARKET customer support** directly for assistance, directions, or inquiries.
5. **Arrival & Reservation Verification:** The customer arrives at the designated supplier shop and presents their `RES-XXXXXX` reservation code. The supplier verifies the reservation code in their terminal.
6. **Physical Inspection First:** The customer **physically inspects the item at the shop counter** to confirm quality, condition, and complete satisfaction before making any payment.
7. **Payment to Paystack at the Counter:** Once satisfied, the customer makes payment **directly to Victorious MARKET via Paystack** (via mobile Paystack payment link, counter QR code, or online counter checkout). Strictly NO cash or private vendor bank account transfers.
8. **Secure Handover Handshake:** Once payment is confirmed as `paid` by Paystack, the supplier verifies the customer's pickup OTP (`verification_code`), captures the mandatory in-shop pickup photo proof, and hands over the product.
9. **Customer Walk-Away (Zero Friction Cancellation):** If the customer is not satisfied upon inspection, they simply do not pay. The reservation is cancelled cleanly with zero financial refund overhead.

## 7. Supplier payment eligibility

**Binding owner rule: Pay suppliers their agreed wholesale cost strictly after verified customer delivery or verified counter pickup.**

- Order placement, customer payment, supplier readiness, rider assignment, or rider collection alone never unlocks supplier payment.
- **Supplier Payable Amount:** Equals the agreed wholesale cost (`cost_price * quantity`) snapshotted at sourcing. It is NOT a percentage commission.
- **VM Gross Margin:** `Customer Retail Selling Price - Supplier Wholesale Cost = VM Gross Profit`.
- Once delivery or pickup is verified with mandatory OTP and photo evidence:
  - The supplier payable transitions to `eligible_for_payment`.
  - Payment is disbursed to the supplier via authorized banking/Paystack transfer.
- If a customer refund is approved after delivery, VM handles the customer remedy. Supplier recovery is tracked separately based on supplier warranty/fault agreements and never delays customer protection.

## 8. Retail accounting replacing marketplace splits

The legacy multi-vendor marketplace commission rules (90% vendor / 5% cashback / 5% platform merchandise split and vendor-retained 90% / platform 10% delivery split) are **retired for new retailer-model transactions**. Separately, new doorstep delivery defaults to a 90% payable to the logistics company and 10% VM delivery margin, subject to audited admin override.

In the retailer model:
- **Merchandise accounting:** customer receipts/revenue, discounts, supplier wholesale cost/COGS, gateway fees, refunds, taxes and operating expenses must be separately recorded. VM gross margin before other costs is `Retail Price - Supplier Wholesale Cost`.
- **Supplier payable:** the agreed wholesale cost (`cost_price * quantity`) is snapshotted against the selected offer and becomes eligible only after verified customer handover and reconciliation. It is not a commission percentage.
- **Delivery accounting:** the customer delivery fee is VM-collected revenue. Default logistics-company payable is 90% of the snapshotted delivery fee; VM retains the 10% remainder as delivery margin when the default applies. The 90% is payable to the logistics company, not directly to its rider. An authorized admin may override the company amount higher or lower to reflect real Nigerian operating conditions and negotiated terms. Record the default, final amount, reason, approver and timestamp. Overrides are allowed before rider custody/handover; after handover, changes require a separately authorized recovery/adjustment linked to payout state. If the approved amount exceeds the customer delivery fee, record the excess as a VM-funded delivery subsidy/expense. A customer delivery-fee refund does not automatically claw back the company's earned trip payable: VM records the customer refund expense and pursues any contractually authorized company recovery separately. Never apply vendor/rider commission logic to this payable.
- **Cashback (active):** accrue 5% on eligible post-discount merchandise actually funded by real money through VM's verified Paystack payment, less redeemed cashback; exclude delivery and amounts covered by discounts/redeemed cashback. Fund it from VM gross retail margin, never supplier cost. Existing terms remain: fulfilment + 24-hour claim window + separate 24-hour maturity buffer with no blocking case; 365-day validity starts at maturity; redemption capped at 50% of cart merchandise subtotal; refund reversals and spent-cashback recovery use linked ledger records, with recovery offset only against future matured cashback. Preserve all historical balances and obligations.
- **Financial invariants:** each approved payment, refund, supplier payment, logistics payment, discount, fee, receivable and adjustment must be authorized, idempotent, balanced and reconcilable. Supplier recovery is separate from customer refunds; a customer refund must not wait for supplier recovery.

## 9. Customer payments, refunds and safety controls retained

The retailer-model decision does not waive existing payment and security requirements:
- verify provider payments server-side against reference, exact amount in integer kobo, NGN currency and correct order/customer;
- authenticated webhooks and reconciliation; idempotency keys and unique constraints where required;
- immutable order and financial snapshots; balanced linked ledger entries and explicit reversals;
- refund approval distinct from refund dispatch and gateway confirmation;
- refunds through the verified original payment route where supported under the approved policy;
- customer refunds remain VM's responsibility; supplier recovery is separate;
- parent/order/component refund ceilings, duplicate-charge protections, and independently tracked merchandise/delivery refund outcomes where applicable;
- no treating timeout or ambiguous provider/custody evidence as success or failure without reconciliation;
- statutory Nigerian consumer rights and valid warranty obligations prevail where stronger than operational rules.

The previously identified legacy web checkout bypass of `CheckoutExecutionService` remains an implementation gap until fixed and regression-tested. A written policy or passing documentation check is not proof that every production code path follows it.

## 10. Legacy migration and release gate

Existing database tables and code may still use names such as `vendor`, `shop`, vendor child order, vendor earnings, logistics company, rider or fleet. These are implementation artifacts until inspected; do not globally rename them or assume their semantics are correct for the new model.

Before enabling the retailer model in production, audit and test:
- supplier offer to canonical product matching and approval;
- immutable supplier offer and purchase-cost snapshots on order items;
- customer checkout and legacy checkout paths, including idempotency;
- central delivery coverage, fee calculation and logistics-company assignment;
- rider-to-logistics-company exclusivity and active-status checks;
- customer OTP/photo proof for doorstep delivery and supplier-shop pickup;
- supplier payment eligibility only after verified successful customer handover;
- no marketplace commission/vendor payout logic creating new supplier payables;
- refund, duplicate-event, timeout and reconciliation behavior;
- historical data migration, foreign keys, ledger references and audit history.

Do not merge/deploy this policy change as if it were an implemented code migration. Complete the compatibility audit, approve unresolved financial terms, implement in controlled steps, and pass targeted regression/security tests first.
