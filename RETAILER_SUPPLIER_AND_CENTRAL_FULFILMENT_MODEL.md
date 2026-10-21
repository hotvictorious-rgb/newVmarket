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

For every new online order:
1. The customer orders the canonical product from Victorious MARKET at the published retail price.
2. Doorstep delivery is the only standard online fulfilment method. The customer pays the merchandise total and the published destination/service delivery fee through the authorized VM checkout.
3. Pickup is not a checkout or reservation type. A customer who prefers pickup must contact support for a confirmed exception; no automated reservation or supplier/shop selection is exposed.
4. Sourcing runs server-side for each line using confirmed current availability, active/approved private source status and valid delivery coverage. The system assesses the complete order and feasible fulfilment plan, not merely the cheapest source for each line independently.
5. If several sources are required, VM coordinates internal collection/consolidation. Do not expose supplier identity/address or direct the customer to multiple suppliers by default.
6. Admin may override a source assignment only with appropriate authorization, a recorded reason and an audit trail.
7. Each order item stores an immutable snapshot of the selected offer/source, agreed wholesale cost, retail price and selection timestamp. Later supplier-price edits must not rewrite existing order economics.
8. If no feasible source and fulfilment plan exists, do not promise fulfilment or silently add fees. Contact the customer with an approved alternative or process the applicable cancellation/refund path.

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

### Order-level delivery fee calculation and bulky surcharges

Victorious MARKET charges delivery **per order**, while calculating the fee using the items inside that order:
1. **Base Delivery Fee per Order:** Determined by the delivery destination route (e.g. Uyo door-to-door or enabled regional route).
2. **Bulky Surcharge per Qualifying Unit:** Added for each physical unit that does not fit on a bike (e.g. standing fans, cookers, furniture).
3. **Multiple Supplier Pickups:** Sourcing across multiple suppliers is coordinated as one fulfilment job without charging multiple base delivery fees to the customer.
4. **Order-Level Total Snapshotted:** The customer sees one clear delivery charge at checkout (Base Fee + Bulky Surcharge), snapshotted immutably upon payment.
5. **Logistics Company Receives 90%:** The default payable is 90% of the total captured delivery fee (including bulky surcharges); VM retains 10% delivery margin, subject to audited Super Admin override.

## 6. Pickup requests: support-managed exception only

**Binding current rule:** doorstep delivery is the only standard online fulfilment method. Customers cannot select in-shop pickup or create an automated pickup reservation at checkout. Customers who prefer pickup must contact Victorious MARKET support by phone or WhatsApp.

Support may confirm pickup only after verifying the complete order, confirmed availability of every line, physical readiness of all items at one approved collection point, the payment arrangement, and the customer's agreement. Support must record the responsible staff member, timestamp, decision, approved location, readiness evidence and any exception reason. Do not disclose a supplier address or instruct the customer to travel before confirmation.

A multi-product order must not default to multiple supplier visits. If all items cannot be made ready at one approved point, VM coordinates internal consolidation or offers a feasible doorstep-delivery plan and communicates any material change for customer agreement. Do not silently add another customer delivery fee or change the snapshotted fee.

Legacy reservation records, tables, states and handlers remain only for historical reads, audit, financial reconciliation and resolution of pre-existing obligations until their dependencies can be safely retired. Their existence does not authorize new public reservation writes. No existing historical record may be mass-deleted or reclassified by this policy.
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
