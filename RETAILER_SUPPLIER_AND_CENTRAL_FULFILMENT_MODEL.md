# Victorious MARKET — Retailer, Supplier and Central Fulfilment Model


> **Current delivery-first fulfilment policy (22 October 2026):** Victorious MARKET is the sole customer-facing retailer operating only within Uyo LGA, Akwa Ibom State for new sourcing, customer destinations and dispatch. Suppliers are private sources. Doorstep delivery is the only standard online fulfilment method. Public pickup reservations, supplier/shop selection and supplier-address disclosure are disabled for new orders. Support may approve pickup only as an exception after checking complete-order availability/readiness at one approved collection point, payment arrangement and customer agreement. One rider handles the complete order; one bulky item makes the order keke-only and exclusive, while only all-small orders may be batched by bike along the same Uyo corridor. New logistics-company payables use the company's configured corridor base payable plus its per-bulky-unit surcharge; the former 90% rule is historical-only.

**Status:** Owner-confirmed business model baseline, 22 October 2026. This document supersedes earlier marketplace/vendor-managed-delivery assumptions for new retailer-model work. It does not assert that application code has already been migrated.

## 1. Business identity

Victorious MARKET (VM) is a centrally managed online retailer operating from Uyo, Akwa Ibom State. Customers buy from VM. Suppliers are private behind-the-scenes sources of products; they are not public marketplace sellers and do not own the customer relationship.

VM controls the public catalogue, retail prices, order acceptance, customer payment workflow, fulfilment coordination, delivery coverage, customer support, refund decisions, and financial records.

## 2. Supplier offer, pricing and catalogue workflow

Victorious MARKET operates as a Direct-to-Consumer (1P) Retailer with Just-in-Time Private Supplier Sourcing:
1. **Admin-Only Onboarding (Zero Public Self-Registration):** Suppliers must only be created and onboarded by Victorious MARKET Admin staff. Public self-registration for suppliers or vendors is strictly prohibited across all web and mobile endpoints.
2. **Wholesale Sourcing:** Suppliers provide their physical products, inventory availability, physical shop locations in Uyo, and wholesale cost prices (e.g., ₦10,000).
3. **Retail Markup:** Victorious MARKET sets the customer-facing retail selling price by adding its own profit margin (e.g., wholesale ₦10,000 + VM markup ₦4,000 = retail selling price ₦14,000).
4. **Public Product Listing:** VM creates and controls one canonical public listing. Customers only see Victorious MARKET as the seller at the retail price. Supplier identity and wholesale cost remain strictly private to VM.
5. **Whole-order source planning:** Evaluate eligible offers at SKU level, then choose a feasible whole-order allocation. Prefer the feasible plan that sources the greatest number of order lines from the fewest supplier shops; among comparably consolidated feasible plans, minimize total wholesale cost, then use measured fulfillment success and a stable deterministic tie-breaker. Do not blindly select the cheapest offer per SKU if that makes the whole order infeasible or creates avoidable extra stops.

A supplier submission is a wholesale proposal, never an auto-published marketplace listing. VM reviews, approves, and owns all customer-facing titles, descriptions, categories, images, and prices.

## 3. Customer order sourcing and supplier selection

For every new online order:
1. The customer orders the canonical product from Victorious MARKET at the published retail price.
2. Doorstep delivery is the only standard online fulfilment method. The customer pays the merchandise total and the published destination/service delivery fee through the authorized VM checkout.
3. Pickup is not a checkout or reservation type. A customer who prefers pickup must contact support for a confirmed exception; no automated reservation or supplier/shop selection is exposed.
4. After verified Paystack payment, sourcing binds a feasible whole-order plan using confirmed current availability, active/approved private-source status and valid Uyo delivery coverage. Any pre-payment availability result is a non-binding quote; the selected source and wholesale-cost snapshots must be revalidated and committed after verified payment. The system assesses the complete order, not merely the cheapest source for each line independently.
5. If several sources are required, VM coordinates internal collection/consolidation. Do not expose supplier identity/address or direct the customer to multiple suppliers by default.
6. Admin may override a source assignment only with appropriate authorization, a recorded reason and an audit trail.
7. Each order item stores an immutable snapshot of the selected offer/source, agreed wholesale cost, retail price and selection timestamp. Later supplier-price edits must not rewrite existing order economics.
8. If no feasible source and fulfilment plan exists, do not promise fulfilment or silently add fees. Contact the customer with an approved alternative or process the applicable cancellation/refund path.

## 4. Central operating base and service coverage

Victorious MARKET's launch operating boundary is **Uyo LGA, Akwa Ibom State only** for suppliers, customer destinations, active corridors and logistics dispatch. Other LGAs, interstate routes and park/terminal handoffs are future scope and must be rejected for new orders.

Before offering delivery at checkout, VM verifies:
1. The product can be sourced from an active supplier shop in Uyo;
2. The destination is within an active logistics coverage rule;
3. An approved logistics company can serve the route;
4. The customer-facing delivery fee is confirmed and quoted.

If coverage is unconfirmed, checkout blocks delivery. VM controls delivery coverage and quotes; suppliers do not configure delivery prices.

## 5. Doorstep delivery: customer pays first, automated sourcing, admin logistics dispatch

**Binding rule: Doorstep delivery requires pre-payment online via Paystack. Strictly NO Cash on Delivery (COD).**

1. **Customer Checkout (Pay First):** The customer pays the full retail price plus delivery fee online via Paystack (card or bank transfer).
2. **Automated Sourcing Assignment:** After verified Paystack payment, run the SKU-level eligibility checks and select the best feasible whole-order sourcing plan using consolidation priority, total wholesale cost and supplier fulfillment reliability. Snapshot the selected source and wholesale cost per item. Do not allocate new online orders merely on order creation or an unverified payment event.
3. **Admin Sourcing Override:** Admin can review the auto-assigned supplier and override the assignment by selecting a different accredited supplier if necessary.
4. **Supplier Availability Confirmation:** The assigned supplier receives the sourcing notification in their portal and physically inspects their inventory to **confirm product availability**.
5. **Admin Logistics Company Assignment:** Once the supplier confirms availability, the order transitions to Admin dispatch. Admin assigns the order to an accredited **Logistics Company** (onboarded strictly by Admin; zero public self-registration).
6. **Fleet Pickup & Handover:** One customer order is assigned to one active rider from one approved logistics company, even when several Plaza shops supply its items. Every supplier stop requires its own single-use shop-specific pickup OTP and mandatory pickup photo. If any order item is bulky, the entire order requires a keke and that trip is exclusive to that customer; only all-small-item orders may be batched on a bike with compatible orders for the same Uyo road corridor.
7. **Customer Delivery Handshake:** Doorstep delivery completion strictly requires the server-verified customer OTP (`delivery_otp`) plus a mandatory doorstep delivery photo proof.
8. **Supplier Settlement:** A supplier's immutable wholesale-cost payable becomes eligible only after verified customer delivery—or an explicitly authorized, verified support-managed pickup exception—and successful reconciliation. Eligibility does not trigger an automatic transfer.

### Order-level delivery fee calculation and bulky surcharges

Victorious MARKET charges delivery **per order**, while calculating the fee using the items inside that order:
1. **Base Delivery Fee per Order:** Determined by the resolved, active Uyo road corridor. Other LGAs, interstate routes and park/terminal handoffs are disabled for new orders at launch.
2. **Bulky Surcharge per Qualifying Unit:** Added for each physical unit that does not fit on a bike (e.g. standing fans, cookers, furniture).
3. **Multiple Supplier Pickups:** Sourcing across multiple suppliers is coordinated as one fulfilment job without charging multiple base delivery fees to the customer.
4. **Order-Level Total Snapshotted:** The customer sees one clear delivery charge at checkout (Base Fee + Bulky Surcharge), snapshotted immutably upon payment.
5. **Company-specific corridor payable (current rule):** Super Admin sets each approved logistics company's base payable and per-bulky-unit surcharge for each Uyo corridor. This is independent of the flat customer delivery fee. The former 90% delivery allocation is historical-only for orders that actually used it.

## 6. Pickup requests: support-managed exception only

**Binding current rule:** doorstep delivery is the only standard online fulfilment method. Customers cannot select in-shop pickup or create an automated pickup reservation at checkout. Customers who prefer pickup must contact Victorious MARKET support by phone or WhatsApp.

Support may confirm pickup only after verifying the complete order, confirmed availability of every line, physical readiness of all items at one approved collection point, the payment arrangement, and the customer's agreement. Support must record the responsible staff member, timestamp, decision, approved location, readiness evidence and any exception reason. Do not disclose a supplier address or instruct the customer to travel before confirmation.

A multi-product order may require pickups from multiple private supplier shops. That is normal internal fulfilment: the same assigned rider collects every supplier assignment in sequence and delivers the complete order. Do not direct the customer to suppliers, split one customer order across riders, or add another customer base delivery fee because multiple suppliers are involved.

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

The legacy multi-vendor marketplace commission rules (90% vendor / 5% cashback / 5% platform merchandise split and vendor-retained 90% / platform 10% delivery split) are **retired for new retailer-model transactions**. New doorstep delivery uses the assigned company's corridor-specific base payable plus per-bulky-unit surcharge, subject to audited admin override; the former 90%/10% rule is historical-only.

In the retailer model:
- **Merchandise accounting:** customer receipts/revenue, discounts, supplier wholesale cost/COGS, gateway fees, refunds, taxes and operating expenses must be separately recorded. VM gross margin before other costs is `Retail Price - Supplier Wholesale Cost`.
- **Supplier payable:** the agreed wholesale cost (`cost_price * quantity`) is snapshotted against the selected offer and becomes eligible only after verified customer handover and reconciliation. It is not a commission percentage.
- **Delivery accounting:** the customer delivery fee is VM-collected revenue. The company payable is the assigned company's corridor-specific base payable plus per-bulky-unit surcharge; it is not a percentage of the snapshotted customer delivery fee. The approved corridor tariff is payable to the logistics company, not directly to its rider. An authorized admin may override the tariff-derived amount higher or lower to reflect real Nigerian operating conditions and negotiated terms. Record the tariff-derived amount, final amount, reason, approver and timestamp. Overrides are allowed before rider custody/handover; after handover, changes require a separately authorized recovery/adjustment linked to payout state. If the approved amount exceeds the customer delivery fee, record the excess as a VM-funded delivery subsidy/expense. A customer delivery-fee refund does not automatically claw back the company's earned trip payable: VM records the customer refund expense and pursues any contractually authorized company recovery separately. Never apply vendor/rider commission logic to this payable.
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


## Authoritative launch boundary — Uyo-only central retailer

The current owner-approved launch policy is **Uyo LGA, Akwa Ibom State only** for new sourcing, customer destinations, active corridors and logistics dispatch. Historical routes do not authorize new out-of-Uyo orders. Support-managed pickup is an exception only, not a public checkout/reservation feature. Suppliers remain private sourcing partners; customers transact with Victorious MARKET only. Delivery is priced once per order plus customer bulky-unit surcharges, while the logistics-company payable comes from that company's corridor tariff plus its configured bulky-unit surcharge. Supplier payables require verified completion and reconciliation. Preserve the active 5% eligible merchandise cashback and its existing caps, maturity, reversals and refund rules. See [the authoritative Uyo-only policy](docs/architecture/VM_UYO_ONLY_RETAILER_POLICY.md) and [end-to-end operating blueprint](docs/architecture/VM_END_TO_END_OPERATING_BLUEPRINT.md).

 
---
 
## Superseding owner decision: independent customer delivery fees and company corridor tariffs
 
This owner-confirmed rule supersedes earlier 90%-of-customer-fee logistics payable language for new retailer orders. Customer delivery pricing is a separately configured flat base fee per order, plus the customer bulky surcharge for each physical unit that cannot safely fit on a bike. Each approved logistics company has its own admin-configured payable for each delivery corridor: a corridor base payable plus that company's bulky-unit surcharge. Calculate company payable from the assigned company's tariff and actual bulky quantity, not as a percentage of customer fees. Snapshot the corridor, company tariff/version, base payable, company bulky surcharge, bulky quantity and total at assignment; future rate edits must not change existing orders. Release company wallet credit only after verified delivery and reconciliation; do not pay riders directly. Record any company payable above customer delivery revenue as an explicit VM-funded subsidy. Admin overrides require authorization, reason and immutable audit.
 
**Verification status:** corridor/rate tables, an admin management page and endpoints, customer checkout corridor-fee resolution, and company-specific payable calculation/snapshots at assignment are present on this branch. They have not been run through the PHP suite or full financial regression; do not mark complete until payment, refund, retry, ledger and payout tests pass. Customer fees and company payables are separate tariffs.


**Launch tender note:** Paystack prepayment is required for standard checkout. COD and instant refund-wallet-credit checkout tender are disabled at launch; cashback redemption remains separately allowed under its existing caps. The 5% earned-cashback base excludes redeemed cashback, wallet credit, and delivery fees.


## Current authoritative operating blueprint

For the complete customer-to-settlement flow, including Uyo's eight road corridors, per-SKU supplier sourcing, multi-shop collection, bulky-order routing, mandatory proof, and finance gates, follow [`docs/architecture/VM_END_TO_END_OPERATING_BLUEPRINT.md`](docs/architecture/VM_END_TO_END_OPERATING_BLUEPRINT.md). For new Uyo launch orders, that blueprint supersedes conflicting legacy marketplace, public supplier storefront, multi-LGA delivery, pickup-reservation, and vendor-commission assumptions. Historical records remain for audit only and do not authorize new out-of-Uyo fulfillment. Items listed under its known implementation gaps must remain open until code and tests verify them.
