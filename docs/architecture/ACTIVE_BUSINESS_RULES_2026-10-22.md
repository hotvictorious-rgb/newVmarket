# Victorious MARKET — Active Business Rules and Documentation Source of Truth
**Decision date:** 22 October 2026  
**Scope:** Business policy and documentation only. This document does not certify code implementation.

This document records the owner's active decisions. If older documents conflict with these rules, these rules take precedence until those documents are corrected. It does not authorize code changes by itself.

## 1. Retailer and service area
- Victorious MARKET is the single customer-facing retailer, operating within Uyo LGA, Akwa Ibom State.
- Suppliers remain behind the scenes. Customers buy from Victorious MARKET, not from individual supplier storefronts.
- Only an authorized Victorious MARKET admin may register/create a supplier account; suppliers cannot self-register or independently activate their own accounts. Only an authorized admin may approve, activate or suspend a supplier. Each supplier may operate from one admin-approved shop or pickup location anywhere within the approved Uyo operating boundary. Plaza is one possible location, not a mandatory hub.
- A suspended supplier is immediately ineligible for new sourcing/allocation. Suspension must not silently erase records or automatically cancel/refund already-paid orders; an authorized admin must review and explicitly resolve affected in-flight assignments. Reactivation requires an authorized admin action and a recorded audit trail.
- The configured eight delivery corridors remain Oron Road, Nwaniba Road, Aka Road, Abak Road, Ikot Ekpene Road, Itu Road, Ikpa Road and Plaza. The admin must maintain unambiguous landmark-to-corridor mappings. Unknown or out-of-boundary destinations fail closed.

## 2. Product listing lifecycle is separate from order allocation
- A product listing is a customer-facing catalogue record; it is not a supplier assignment or reservation for a particular order.
- A listing can be visible and purchasable only while at least one eligible, approved supplier offer for that product is explicitly in stock and otherwise eligible under the sourcing rules.
- When every eligible supplier offer for the product is out of stock or otherwise ineligible, automatically hide the listing from the customer-facing storefront / make it unavailable for purchase.
- An authorized admin may manually delist a product at any time, even if suppliers report stock.
- When a supplier changes stock, the system should recompute listing availability. Restoring an eligible in-stock offer may make the listing visible again only if the admin has not manually delisted it and other listing rules pass.
- Keep the product record, offer history, stock events and delisting reason for audit; hiding a listing should not erase historical orders or financial records.
- Listing-time availability checks must not bind a supplier to a customer's future order.

## 3. Payment before supplier allocation
- The customer must complete upfront Paystack payment. A browser redirect, screenshot, payment initialization or unverified event is not proof of payment.
- The backend independently verifies payment (including the trusted webhook/signature, amount, currency, reference and idempotency) before any order-specific supplier allocation, assignment or reservation begins.
- Before payment, the system may display catalogue availability, quote a price, validate a cart or perform a non-binding feasibility check. It must not lock suppliers, reserve their stock, notify them of a binding order, or create supplier payables.
- After verified payment, revalidate current stock and eligibility, then allocate the paid order. Preserve the payment and allocation events for audit.

## 4. Supplier allocation
- Apply hard eligibility checks first: supplier active and approved, approved location within Uyo, product offer explicitly in stock with sufficient quantity, applicable 24-hour product-specific out-of-stock cooldown expired, and supplier below the limit of five active pending handovers.
- Compare only feasible plans that can fulfil the whole customer order. First minimize the number of distinct supplier pickup locations; among plans using the same minimum number of locations, minimize total wholesale cost; then prefer documented fulfilment reliability and apply a stable deterministic tie-break.
- A customer order may use multiple supplier locations, but the whole customer order remains assigned to one rider from one approved logistics company.
- Admin may override a supplier assignment with required authorization, reason and immutable audit record. Overrides cannot bypass verified payment or other non-overridable safety rules.

## 5. Delivery and proof
- Small orders may be batched on a bike only when they meet the same-road-corridor and all other capacity/route rules.
- If any item is bulky, the entire order requires a dedicated Keke/tricycle trip exclusive to that customer order. Do not add another customer's order to that trip.
- Require a unique pickup OTP and mandatory photo proof at each supplier pickup location, then customer delivery OTP and mandatory geo-tagged doorstep photo for final delivery.
- Support-managed pickup is an exceptional admin/support workflow only when the entire order is ready at one approved collection point, payment handling is authorized, the customer agrees and verification/photo evidence is recorded. It is not a public reservation or pre-payment hold feature.

## 6. Two separate delivery money paths
- Customer delivery charge: one configured flat customer base fee per customer order, independent of the logistics corridor, plus the configured customer bulky surcharge per applicable physical bulky unit. Do not use the logistics company's corridor tariff to calculate the customer's fee.
- Logistics-company payable: admin-configured base payable for the applicable corridor and assigned logistics company, plus that company's configured bulky-unit surcharge per applicable bulky unit.
- These are independent tariff schedules. The logistics-company payable is not a percentage of the customer delivery fee. The former 90% delivery-fee rule is retired and must never be used as a fallback.
- Snapshot the applicable corridor, company, tariff/version, base payable, bulky quantity/rate and total payable at assignment. Later tariff changes must not silently alter an existing payable.
- If company payable exceeds customer delivery revenue, record the difference explicitly as a Victorious MARKET-funded subsidy/expense. Pay the logistics company, not automatically the individual rider. Overrides require authorization, reason and immutable audit.

## 7. Paid-order fulfilment exceptions are admin-managed
- If verified payment succeeds but supplier allocation or fulfilment fails, do not automatically refund solely because no supplier is immediately available.
- Move the order into a visible admin-managed exception state, preserve its paid status and financial trace, alert the admin and show aging/escalation information.
- Authorized admin may reassign the order to another eligible supplier, source an acceptable alternative, hold fulfilment, cancel or initiate a refund according to policy.
- Do not silently substitute a different product or impose an additional charge. Where product, price or agreed terms change, obtain customer consent as required by policy.
- A refund is not marked complete until the payment provider confirms it. Record actor, reason, timestamps, order/payment references, amount and refund status. Keep all actions auditable and idempotent.

## 8. Financial integrity
- Supplier payables use immutable agreed wholesale-cost snapshots and become eligible only after verified delivery or an explicitly authorized completion exception followed by reconciliation. Supplier stock acceptance alone does not make a payable due.
- **Supplier payable disbursement limits:** each supplier-payment request/disbursement must be at least ₦5,000 and no more than ₦1,000,000 at a time. Do not silently split an over-cap request into multiple transfers; it must be reduced to an allowed amount or handled through a separately authorized decision.
- **No FIFO:** do not use FIFO, oldest-earning-first, oldest-order-first, or automatic FIFO allocation to decide which eligible supplier payables are included in a payment. Selection must use an authorized, auditable non-FIFO process. Do not invent an automatic alternative ranking/selection algorithm without owner approval. Admin approval, reconciliation, idempotency and provider-confirmed payment remain required; eligibility is not automatic payment.
- Keep customer receipts, refunds, supplier payables, logistics-company payables, retailer margin, delivery revenue/costs, bulky surcharges, cashback and recovery/reconciliation as distinct money paths.
- Cashback remains a separate 5% loyalty benefit on eligible post-discount merchandise actually funded by verified Paystack money, subject to existing caps, maturity and reversal rules. Former 90/5/5 merchandise splits and vendor-managed delivery allocations are historical only.
- All sensitive assignments, overrides, refunds, tariff changes and financial adjustments require role-based authorization, a reason where applicable, and immutable audit records.

## 9. Documentation versus implementation
These are the active business decisions to align across documentation. Code, schema, admin UI, workflows and tests must be audited separately and must not be claimed aligned merely because the documents are updated. Until a separate implementation audit and required tests pass, implementation status remains unverified.
