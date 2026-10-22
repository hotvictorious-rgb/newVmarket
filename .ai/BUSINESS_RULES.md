# Victorious MARKET — Business Rules

> **Binding authority:** [Active Business Rules](../docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md). If any legacy detail in this file conflicts, the active rules prevail. This file is a policy reference, not a claim that runtime implementation has passed audit.

## Retailer and service boundary
- Victorious MARKET is the sole customer-facing retailer; suppliers remain private behind-the-scenes sources.
- Sourcing and delivery are restricted to the approved Uyo LGA boundary.
- Each supplier has one admin-approved shop/pickup point anywhere inside that boundary. Plaza is optional.
- Corridors: Oron Road, Nwaniba Road, Aka Road, Abak Road, Ikot Ekpene Road, Itu Road, Ikpa Road and Plaza. Unknown/out-of-boundary destination mapping fails closed.

## Listing lifecycle
- Listing visibility/purchasability is separate from order allocation.
- Keep a listing available only while at least one eligible approved offer is explicitly in stock with sufficient quantity.
- Hide/unavailable automatically when all eligible offers are out of stock or otherwise ineligible.
- Admin may manually delist. Stock changes cannot automatically relist a manually delisted item until an authorized admin permits it.
- Preserve stock events, offer history, delisting reason, prior orders and financial records.

## Paystack gate and allocation
- Delivery payment is upfront through Paystack. Verify trusted webhook/signature, transaction reference, amount, currency and idempotency.
- Before verified payment, stock/catalogue/cart checks are non-binding. No supplier lock, order-specific reservation, binding notification or supplier payable may be created.
- After verified payment, revalidate current stock and supplier eligibility before allocation.
- Supplier eligibility: active/approved; one approved Uyo location; explicit in-stock offer with sufficient quantity; SKU-specific 24-hour unavailable cooldown expired; fewer than five active pending handovers.
- Choose feasible whole-order plans by fewest pickup locations, lowest total wholesale cost, reliability, then deterministic tie-break. An order may use multiple suppliers but one rider from one approved logistics company handles the complete customer order.
- Admin overrides require authorization, reason and immutable audit, and cannot bypass payment verification or safety rules.

## Logistics and evidence
- Any bulky unit makes the entire order a dedicated Keke/tricycle trip exclusive to that customer order.
- Bike batching for small orders is permitted only on the same road corridor and within all capacity/route/safety constraints.
- Supplier pickup: one-time pickup OTP plus mandatory pickup photo. Doorstep delivery: customer OTP plus mandatory geo-tagged doorstep photo.
- Support-managed pickup is exceptional, not public pre-payment reservation. Confirm only after complete-order readiness at one approved point, authorized payment arrangement, customer agreement and required evidence are verified.

## Money rules
- Customer delivery charge = one base fee per customer order + configured customer bulky surcharge per applicable physical bulky unit.
- Logistics company payable = admin-configured company/corridor base payable + that company’s configured bulky-unit surcharge per applicable physical bulky unit. These are separate calculations.
- The former “90% of customer delivery fee” payable is retired; never use it as a default/fallback. Snapshot company, corridor, tariff/version, base payable, bulky units/rate and total. If payable exceeds delivery revenue, record VM subsidy/expense. Logistics company is payee, not automatically rider.
- Supplier payable uses immutable wholesale-cost snapshots and is eligible only after verified delivery or authorized completion exception plus reconciliation.
- Cashback is a separate 5% eligible post-discount merchandise benefit funded by verified Paystack money, subject to existing caps/maturity/validity/reversal rules; delivery is excluded. Old 90/5/5 and vendor 90/10 splits are historical only.
- Separate customer receipts/refunds, supplier payables, logistics-company payables, VM margin, delivery revenue/cost, bulky surcharges, cashback and recovery/reconciliation.

## Paid-order exceptions and audit
- Allocation failure is not an automatic refund. Admin may reassign, hold, source an acceptable alternative, cancel or initiate refund; customer consent is required for changed product/terms. Refund is complete only after payment-provider confirmation.
- Money/status transitions must be authorized, idempotent and auditable. Retain immutable price, stock, offer, tariff and decision snapshots.
- No code was changed as part of this policy update; runtime conformity remains to be verified.
