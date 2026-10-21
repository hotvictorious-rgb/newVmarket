# Victorious MARKET — Binding Uyo-Only Retailer Policy

**Owner-confirmed:** 21 October 2026  
**Applies to:** All new checkout, sourcing, delivery, payments, fulfilment, admin, API, background-job, customer-facing, and documentation behavior.

## Geographic invariant

**Victorious MARKET sources exclusively from approved supplier shops physically located in Uyo LGA, Akwa Ibom State, and delivers exclusively to customer destinations within Uyo LGA.** No sourcing from Eket, Oron, Ikot Ekpene, another LGA, or another state. No out-of-Uyo delivery, inter-LGA/inter-state shipping, or collection-point routing for new orders. A destination being in Akwa Ibom State is not enough; its LGA must be Uyo.

## One retailer, private suppliers

Customers buy from Victorious MARKET and see one public catalogue, VM retail prices, VM checkout and VM support. Suppliers are private wholesale sources, not public marketplace sellers. Do not expose supplier identities, wholesale cost, contact details, shop names, slugs, or physical addresses in customer-facing API responses, product pages, search results, checkout, receipts or public shop listings.

Each supplier has one approved source shop for this operating model, located in Uyo. A product may be sourced just in time after verified payment, but every selected offer and source must be checked server-side. Lowest valid wholesale cost is preferred subject to availability, freshness, capacity and documented fulfilment constraints. Snapshot selected supplier and wholesale cost on the order; later offer edits must not change existing order economics. Admin overrides require authorization, a mandatory reason, and an immutable audit record.

## Checkout and delivery

- Standard checkout is prepaid through verified Paystack and doorstep delivery within Uyo only. Cash-on-delivery is disabled.
- Validate the customer address and destination LGA server-side at checkout-intent creation, order creation, payment confirmation/reinstatement, admin override, queue job, and delivery assignment.
- Validate the selected source shop's LGA server-side as Uyo for every item; do not trust product listing filters or client-supplied shop IDs.
- Charge one delivery base fee per customer order, not per product or supplier. Apply the configured bulky-item surcharge transparently per eligible physical unit as the current fee policy specifies.
- Default logistics-company payable is 90% of the collected delivery fee, not 90% of merchandise revenue or supplier cost. Admin overrides require permissions, a reason, and audit history. The logistics company is the payee; do not treat the rider as the payee unless a separate approved arrangement explicitly requires it.
- Only active, approved logistics companies and their authorized riders may fulfil orders within Uyo. Delivery completion requires both the order-specific customer OTP and mandatory photo proof.
- Public customer pickup/reservation, collection-point fulfilment and vendor-managed out-of-area delivery are disabled for new orders. Support may arrange a rare exception only with explicit approval, customer consent, evidence, and reconciliation; it must not bypass the Uyo boundary, payment controls, or supplier privacy.

## Financial invariants

- Supplier payable equals the agreed wholesale cost snapshot for fulfilled goods, not a legacy vendor commission percentage. Eligibility follows verified delivery and reconciliation.
- VM owns the customer relationship, refund decision and refund execution. A valid customer refund must not wait for supplier recovery; supplier recovery is a separate evidenced process.
- Cashback remains 5% of eligible real customer money paid toward merchandise, funded from real VM margin. Exclude delivery charges and redeemed cashback/wallet credit from the eligible base, subject to stricter existing maturation, cap, refund and reversal controls.
- Keep merchandise revenue, supplier payables, logistics payables, cashback liability, refund liability and VM margin distinct. Enforce database-backed idempotency, balanced journals, replay-safe Paystack webhooks, concurrency-safe reservations and reconciliation.
- Never claim money is safe solely because documentation says so or tests previously passed. Verify the actual relevant commit and test result.

## Legacy compatibility

Historical orders, payouts, supplier records, pickup reservations, collection points, delivery lanes, and journals must be retained for audit and support. Legacy records may remain readable, but must not authorize new non-Uyo fulfilment. Disable obsolete paths safely after mapping all routes, controllers, API clients, jobs, callbacks and tests. Do not delete financial evidence to make the new model appear clean.

## Acceptance checks

1. Uyo source + Uyo destination can pass when all active local service requirements are met.
2. Uyo source + any non-Uyo destination is rejected even if a legacy active lane exists.
3. Any non-Uyo source is rejected even when destination is Uyo.
4. Direct API, stale cart, payment retry/callback, admin override and background jobs cannot bypass the same geographic rule.
5. Public APIs never reveal private supplier details and do not offer pickup/reservation.
6. Multi-item order has one delivery base fee plus applicable per-unit bulky surcharge; no per-product base fee multiplication.
7. Duplicate callbacks cannot duplicate orders, supplier/logistics payable, cashback or journal entries.
8. Supplier payout remains held until verified fulfilment and reconciliation.
9. Refunds and cashback reversals reconcile without depending on supplier recovery.
10. Existing out-of-area historical records remain auditable but cannot authorize new orders.

**Customer promise:** *VICTORIOUS MARKET — Your Trusted Online Market in Uyo, Akwa Ibom State. Sourced in Uyo. Delivered within Uyo.*
