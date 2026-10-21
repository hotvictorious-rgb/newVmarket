# Victorious MARKET — Retailer, Supplier and Central Fulfilment Model

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
2. For doorstep delivery, the customer pays upfront online via Paystack (+ delivery fee).
3. For in-shop pickup, the customer reserves online for free (₦0 upfront) to inspect at the designated supplier shop.
4. **Supplier Offer Selection:** VM operations links the order item to the specific supplier offer chosen (the available and cheapest supplier in Uyo).
5. **Immutable Snapshot:** The order item records an immutable snapshot of:
   - Canonical product and variant;
   - Selected supplier ID and supplier shop ID;
   - Agreed supplier wholesale purchase cost (`cost_price`);
   - Customer retail selling price (`unit_price`);
   - VM gross profit margin (`unit_price - cost_price`).
6. Later edits to supplier wholesale prices never alter historical or active order snapshots.

## 4. Central operating base and service coverage

VM's central operating base is Uyo, Akwa Ibom State. VM serves destinations reliably covered by local supplier sourcing and accredited logistics partners.

Before offering delivery at checkout, VM verifies:
1. The product can be sourced from an active supplier shop in Uyo;
2. The destination is within an active logistics coverage rule;
3. An approved logistics company can serve the route;
4. The customer-facing delivery fee is confirmed and quoted.

If coverage is unconfirmed, checkout blocks delivery. VM controls delivery coverage and quotes; suppliers do not configure delivery prices.

## 5. Doorstep delivery: customer must pay first online

**Binding rule: Doorstep delivery requires pre-payment online via Paystack. Strictly NO Cash on Delivery (COD).**

1. **Customer Checkout:** The customer pays the full retail price plus delivery fee online via Paystack card or bank transfer.
2. **Sourcing:** Once payment is verified, VM selects the best supplier offer (cheapest and available), secures the item from the supplier's physical shop in Uyo.
3. **Logistics Dispatch:** VM assigns delivery to an approved logistics company and rider. Logistics companies can strictly only be added by Admin (zero public self-registration). Each accredited logistics company manages their own dispatch riders. Every rider assigned must be active and linked to exactly one approved logistics company.
4. **Custody & Delivery:** The rider collects the item from the supplier/VM hub and delivers to the customer's address.
5. **Customer Handshake:** Customer delivery completion requires server-verified customer OTP (`delivery_otp`) plus a mandatory doorstep delivery photo.
6. **Supplier Settlement:** Only after verified customer delivery is the supplier's wholesale payable unlocked and eligible for settlement.

## 6. In-shop pickup: inspect first, pay at the shop

**Binding rule: In-shop pickup allows the customer to inspect the physical item first at the supplier shop, and pay at the shop counter upon satisfaction.**

1. **Online Reservation:** The customer selects "In-Shop Pickup" at checkout. The checkout fee is ₦0 upfront.
2. **Reservation Pass Issued:** The system issues a unique reservation pass (`RES-XXXXXX`) and 6-digit pickup OTP (`pickup_verification_code`), directing the customer to the designated supplier shop in Uyo with a 24-hour stock hold.
3. **Inspect First:** The customer arrives at the supplier's physical shop counter, presents their reservation pass, and **physically inspects the item first** to verify quality, condition, and satisfaction.
4. **Payment at the Shop:** Once completely satisfied, the customer completes verification and payment through VM's authorized workflow at the shop counter before goods are released.
5. **Handover Evidence:** The supplier verifies the reservation pass and customer pickup OTP, records the mandatory in-shop pickup photo, and marks the item collected.
6. **Customer Rejection / Walk-Away:** If the customer is dissatisfied upon physical inspection, they do not pay, no funds are lost, and the reservation is cancelled cleanly without refund complications.

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

The legacy multi-vendor marketplace commission rules (90% vendor / 5% cashback / 5% platform merchandise split; 90% vendor / 10% platform delivery split) are **superseded and retired** for the retailer model.

In the retailer model:
- **Merchandise Accounting:**
  - `Customer Payment = Retail Price (VM Revenue)`.
  - `Supplier Payable = Wholesale Cost (Cost of Goods Sold - COGS)`.
  - `VM Retail Margin = Retail Price - Wholesale Cost (Gross Margin)`.
- **Customer 5% Cashback Retention (Owner Decision — 21 October 2026):**
  - Victorious MARKET **actively retains the customer 5% cashback loyalty feature** on every verified purchase to drive repeat customer retention.
  - **Funding Source:** In the retailer model, the 5% cashback liability is funded **directly out of Victorious MARKET's gross retail margin** (`Retail Selling Price - Supplier Wholesale Cost`), NEVER deducted from the supplier's wholesale cost.
  - **Mathematical Formula:** `VM Net Profit = (Retail Price - Supplier Wholesale Cost) - 5% Customer Cashback Liability`.
  - **Non-Compounding Loyalty Invariant (Real-Money Rule):** Cashback CANNOT be earned on the portion of an order covered by existing redeemed cashback or discounts. Cashback is strictly earned only on **real money paid through Paystack** for eligible merchandise:
    `Cashback Earned = (Merchandise Subtotal - Cashback Redeemed) * 5%`.
    For example, on a ₦10,000 item where the customer redeems ₦2,000 in cashback and pays ₦8,000 via Paystack, the customer earns 5% only on the ₦8,000 real cash paid (₦400 reward), NEVER on the ₦2,000 covered by existing cashback.
  - **Accrual & Maturation:** Accrues as `pending_maturation` upon checkout; matures into the customer's wallet (`status = 'available'`) 24 hours after the post-delivery/pickup inspection window closes with zero disputes.
  - **Redemption:** The customer can redeem their matured cashback balance on future checkouts, capped at up to 50% of the cart merchandise subtotal.
  - **Return / Cancellation Forfeiture:** If an order is returned, cancelled, or rejected at shop inspection, pending cashback is automatically cancelled/forfeited in the ledger.
- **Delivery Accounting:**
  - Customer delivery fee collected is VM delivery revenue.
  - Logistics company charge is VM delivery expense.
  - Delivery margin = Customer fee collected - Logistics company cost.
- **Supplier Settlement:**
  - Suppliers receive 100% of their agreed wholesale cost upon verified completion.
  - No percentage commission or cashback deductions from suppliers; suppliers are paid in full.

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
