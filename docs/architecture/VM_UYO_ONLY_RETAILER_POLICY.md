# Binding Owner Policy — Uyo-Only Victorious MARKET

**Owner-confirmed:** 21 October 2026. This policy governs all new code paths and supersedes conflicting legacy examples until those paths are safely retired.

## Geographic boundary
Victorious MARKET sources exclusively from approved supplier shops physically located in Uyo LGA, Akwa Ibom State, and delivers exclusively to customer destinations within Uyo LGA. No Eket, Oron, Ikot Ekpene, Abak, any other LGA, or interstate delivery. An Akwa Ibom destination is not sufficient unless its LGA is Uyo. Existing out-of-area database rows and historical orders remain for audit but must not authorize new fulfilment.

## One retailer, private suppliers
Customers buy from Victorious MARKET, see one public catalog and VM retail prices, and contact VM support at 09118949035. Supplier identities, shop names/slugs/addresses/contact details and wholesale costs must not be exposed on public product pages, APIs, checkout, receipts, or shop directories. Each approved supplier has one source shop in Uyo. Select the cheapest eligible verified offer subject to availability and feasibility; snapshot offer/source/wholesale cost per order item. Admin overrides require authorization, reason and immutable audit history.

## Checkout and fulfilment
- Standard online checkout is prepaid via verified Paystack and doorstep delivery within Uyo only. Cash-on-delivery is disabled.
- Validate the customer address/destination and every selected supplier source server-side in checkout intent, order creation, payment retry/callback, admin override, background jobs and delivery assignment.
- Charge one delivery base fee per customer order, not per product or supplier; add the configured bulky surcharge for each eligible bulky unit.
- Baseline logistics-company payable is 90% of collected delivery fee; it is payable to the logistics company, not directly to riders. Admin overrides require permissions, reason and audit trail.
- Only approved logistics companies and authorized riders may fulfil orders. Completion requires the order-specific customer OTP and mandatory photo proof.
- Public pickup reservations, collection-point fulfilment and vendor-managed delivery are disabled. Any support-managed pickup exception must be rare, approved, documented, consented to, and must never bypass the Uyo boundary or payment controls.

## Financial controls
- Supplier payable is the agreed wholesale cost snapshot for fulfilled goods, not a legacy vendor commission percentage. Payout eligibility requires verified delivery and reconciliation.
- Customer refunds are handled by VM and must not wait for supplier recovery; supplier recovery is a separate evidence-backed process.
- Cashback remains 5% of eligible real customer money paid toward merchandise, funded from actual VM margin. Exclude delivery fees and redeemed cashback/wallet credit from the eligible base, and retain existing caps, maturation, reversal and refund controls.
- Keep merchandise revenue, supplier payable, logistics payable, cashback liability, refund liability and VM margin separate. Enforce database idempotency, balanced journals, replay-safe Paystack callbacks, concurrency-safe reservations and reconciliation.

## Acceptance tests
1. Uyo source + Uyo destination can proceed if all checks pass.
2. Any non-Uyo destination or source is rejected even if a legacy lane is active.
3. Customer-facing APIs never reveal private supplier details and do not offer pickup.
4. Multi-item order charges one base delivery fee plus applicable bulky-unit surcharge.
5. Duplicate callbacks cannot duplicate orders, payables, cashback or journal entries.
6. Supplier payout remains held until verified fulfilment and reconciliation.
7. Legacy out-of-area rows remain auditable but cannot authorize new orders.

**Customer promise:** *VICTORIOUS MARKET — Your Trusted Online Market in Uyo, Akwa Ibom State. Sourced in Uyo. Delivered within Uyo.*
