# Victorious MARKET — End-to-End Operating Blueprint

**Status:** authoritative implementation specification for the Uyo launch.
**Business boundary:** source and deliver only within Uyo LGA, Akwa Ibom State.
**Customer promise:** VICTORIOUS MARKET — YOUR TRUSTED ONLINE MARKET IN UYO AKWA IBOM STATE.

This document turns the owner-approved operating blueprint into explicit system invariants and acceptance criteria. Authority order: `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md` governs business decisions; `VM_UYO_ONLY_RETAILER_POLICY.md` specifies the Uyo-only launch boundary; this blueprint defines operational behavior and acceptance tests. If this blueprint conflicts with either higher-level policy, update this blueprint rather than overriding the policy. Historical records remain readable for audit but must never authorize new out-of-Uyo fulfillment.

## 1. Non-negotiable launch invariants

1. Victorious MARKET is the sole customer-facing retailer. Supplier identity, shop name, wholesale cost, supplier contact details, and internal supplier addresses are private operations data.
2. New sourcing, supplier assignments, customer delivery addresses, active delivery corridors, and logistics dispatch are limited to Uyo LGA, Akwa Ibom.
3. Normal checkout is Paystack prepayment only. COD and instant refund-wallet-credit as checkout tender are disabled at launch. Cashback redemption is separate and remains subject to existing caps and eligibility.
4. One customer checkout creates one customer order. Internal supplier assignments and item-level source snapshots do not create separate customer orders.
5. Delivery pricing is customer-facing and independent from logistics company payables. The customer pays one base delivery fee per order plus the configured surcharge for each physical bulky unit. Company payables come from the assigned logistics company's admin-configured corridor tariff, including its separate bulky tariff.
6. A supplier payable uses immutable supplier cost snapshots and is not released merely because Paystack succeeds. Release must pass verified delivery, evidence, reconciliation, refund/dispute, and idempotency gates.
7. Every manual source or logistics-payable override requires an authorized administrator, a non-empty reason, and an audit record.
8. Any ambiguous, stale, inactive, out-of-area, unverified, or internally inconsistent condition fails closed. A legacy row is not permission to fulfill a new order.

## 2. Customer discovery and checkout

- Public product catalog entries must be active, approved, listed, fresh, and backed by at least one currently confirmed, unexpired, eligible Uyo supplier offer.
- Product presentation must identify whether the product/package is bulky. The order-level vehicle class is derived from all order items; one bulky item makes the whole order bulky.
- Checkout captures the customer's Uyo street address and a landmark selected from the admin-curated landmark list.
- The landmark must resolve unambiguously to exactly one active Uyo delivery corridor. Unknown, conflicting, inactive, or out-of-Uyo landmarks are rejected; do not guess a corridor.
- Customer pricing is snapshotted as one order-level base fee plus the applicable per-bulky-unit surcharge. The payable to the logistics company is calculated separately from that company's corridor tariff and may not be inferred from the customer fee.
- Paystack callbacks must be signature-verified, amount/currency/reference-checked, idempotent, and reconciled before the order can enter paid fulfillment.

## 3. Per-SKU supplier sourcing and consolidation

For every ordered product, candidate supplier offers must pass all hard gates:

- Supplier and user are active, approved, KYC-verified, and not deleted.
- Supplier has exactly one active shop/pickup point, and that shop is in Uyo LGA, Akwa Ibom.
- Offer explicitly says available, has attributable availability confirmation, has a non-expired confirmation window, and has a positive valid wholesale cost.
- Product itself is approved, listed, fresh, and in stock.
- An offer recently marked unavailable must be excluded for the 24-hour cooldown window, even if stale availability evidence otherwise appears active.
- A supplier with five or more active, unfulfilled handovers is temporarily excluded from new allocations until capacity is restored.
- Customer input may never select or override a supplier, shop, wholesale cost, or fulfillment source.

After hard filtering, evaluate the whole order rather than making isolated cheapest choices:

1. Prefer feasible allocations that source the largest number of distinct order lines from the fewest supplier shops.
2. Among equally consolidated feasible allocations, minimize the total wholesale cost for the order.
3. Break ties using the supplier's measured fulfillment success rate, then a deterministic stable ID tie-breaker.
4. Snapshot the chosen supplier offer, supplier/shop identity, wholesale unit cost, quantity, and selection timestamp per order item.
5. Group items by source into private supplier assignment records. One supplier may have a single assignment for multiple ordered SKUs from that shop.
6. A supplier rejection, stale availability, or operations exception places affected sourcing on hold. Only an authorized administrator may reassign the affected item group to a verified Uyo supplier, with a mandatory reason and audit record. Reassignment must refresh operational source snapshots without rewriting original financial/audit snapshots.

## 4. Uyo road-corridor logistics

The eight configured Uyo corridors are:

- Oron Road
- Nwaniba Road
- Aka Road
- Abak Road
- Ikot Ekpene Road
- Itu Road
- Ikpa Road
- Plaza

Corridors and landmark aliases must be admin-configured and active. The destination is resolved once and snapshotted to the order. The logistics company maintains its own negotiated corridor payable tariff; the administrator can set the base company payable and the company's bulky surcharge independently from customer delivery pricing.

- The logistics company dashboard groups work by destination corridor.
- One customer order has one assigned logistics company and one rider for the entire trip, even when the rider must visit multiple supplier shops.
- A rider must be active and linked to the assigned active logistics company; supplier-owned/vendor riders are not eligible for centrally managed retailer orders.
- A bike may batch only standard orders that share the same corridor and compatible dispatch window; an order must not cross-mix corridors on the same trip.
- If any item is bulky, the entire order requires a Keke. That Keke trip is exclusive to that one customer order and cannot carry another customer's order.
- Reassignment to another company/rider/vehicle or corridor requires authorization, validation, and audit logging. Never silently route a paid order to an incompatible vehicle or corridor.

## 5. Multi-shop pickup chain

For each private supplier assignment:

1. Supplier confirms availability and marks the package ready.
2. Rider visits the exact assigned shop.
3. Rider submits that assignment's one-time supplier pickup OTP.
4. Rider uploads a mandatory pickup photo for the items at that supplier counter.
5. The system records the assignment-level OTP consumption, timestamp, photo reference, and pickup evidence; a photo or OTP from another supplier cannot satisfy this stop.
6. The order may enter out-for-delivery only when every active supplier assignment is collected or explicitly admin-reassigned under an audited recovery workflow.

The service layer must enforce the same gates as the API/controller so direct calls, retries, or legacy routes cannot bypass pickup evidence.

## 6. Doorstep delivery and final evidence

- Only the assigned active logistics rider may complete the order.
- The rider submits the customer's one-time delivery OTP and a mandatory doorstep photo.
- The photo record must include the location evidence captured for the doorstep event (latitude/longitude and capture timestamp); missing evidence must block completion.
- The service verifies payment, order state, rider/company assignment, OTP format/value, lockout state, and evidence before setting delivered.
- Repeated callbacks/submissions must be idempotent and must not create duplicate completion, cashback, company payables, supplier payables, or journal entries.

## 7. Financial reconciliation and payout

- Each order item has an immutable supplier-cost snapshot. The order's supplier-cost total must equal the sum of item snapshot cost × quantity.
- Supplier payables are posted per supplier/item or assignment, not as legacy vendor commission. A retailer-model order must never settle through legacy parent/child vendor-net accounting.
- The assigned logistics company is credited according to its snapshotted corridor tariff (base + company bulky surcharge), never as a percentage of the customer delivery fee. The former 90% rule is retired for new orders.
- The difference between customer delivery revenue and company payable remains a separate Victorious MARKET delivery margin/surplus ledger amount.
- All journals are balanced, immutable, and idempotent. Overrides must use balanced adjustment entries and preserve the original amount, new amount, actor, reason, timestamp, and revision.
- Cashback is 5% of eligible merchandise actually paid in real money; exclude delivery, redeemed cashback, and wallet-credit amounts. Do not earn cashback on cashback. Existing caps, maturation, expiry, reversal, and refund rules remain in force.
- Supplier and company payouts require verified completion evidence and successful reconciliation. Disputes, refunds, chargebacks, missing proof, failed reconciliation, or ambiguous beneficiary account hold the payout.

## 8. Implementation acceptance checklist

| Area | Required proof of completion |
|---|---|
| Geographic boundary | New orders, supplier offers, checkout, delivery fees, admin corridors, API endpoints, and jobs all reject non-Uyo fulfillment |
| Catalog privacy | Public HTML/API never exposes supplier shop identity, private contact data, supplier cost, or supplier assignment metadata |
| Offer cooldown/capacity | Tests prove 24-hour unavailability cooldown and five-active-handover cap |
| Consolidation | Multi-SKU tests prove maximum feasible shop consolidation first, total wholesale cost second, fulfillment success rate third |
| Admin override | Non-admin rejection, reason required, Uyo-only replacement, immutable original snapshots, and audit trail |
| Corridor routing | Eight Uyo corridors resolve from curated aliases; unknown aliases fail closed; bike batching cannot cross corridors |
| Bulky contagion | One bulky item makes the whole order Keke-exclusive and adds the configured customer/company bulky charges separately |
| Pickup | Per-supplier OTP and mandatory per-stop photo; no transition to delivery until all assignments are collected |
| Delivery | Correct rider/company, customer OTP, mandatory photo and location evidence; no evidence means no completion |
| Finance | Item cost reconciliation, separate company tariff, balanced idempotent journals, cashback base, refund/reversal, and no legacy retailer settlement |
| Reliability | Automated regression suite and CI are green on the single working branch; migrations and route-level integration checks pass |

## 9. Known implementation gaps to close before calling the blueprint complete

This is a target specification, not a claim that all items are already implemented. The code audit must specifically verify and close:

The current consolidation candidate branch is being audited for supplier-owned 24-hour unavailability cooldown, a five-active-handover cap, whole-order consolidation scoring, per-item bulky snapshots, keke exclusivity, bike corridor eligibility and mandatory photo/GPS/timestamp evidence. These are implementation claims to verify against code and tests, not proof of release readiness.

The following still need to be closed before this blueprint can be called complete:

- dispatch assignment must reject incompatible rider/vehicle/corridor combinations at the moment of assignment, not only when the rider later attempts pickup or delivery;
- ensure every new rider has exactly one active approved logistics-company owner; legacy vendor-linked riders must not be assigned to new retailer orders;
- remove stale vendor-managed-dispatch comments and prevent any legacy vendor assignment route from bypassing the central logistics-company workflow;
- GPS coordinates are currently client-submitted metadata. Add location accuracy/geofence checks against the configured supplier shop or customer destination, and make sure photo capture and coordinates are bound to the same fulfillment event;
- **Payment-to-sourcing timing must be resolved and tested:** `CheckoutExecutionService` currently resolves the consolidated source plan and writes item-level supplier/cost snapshots plus supplier-assignment rows during checkout, before the Paystack payment is verified. The target rule says new supplier assignment occurs only after verified payment. Either defer binding source assignment/snapshots until the verified webhook, or explicitly treat checkout resolution as a non-binding quote and revalidate/rebind atomically after payment before any supplier notification or payable can exist. No supplier should be committed or payable created on an unverified payment.
- **Fix and rerun CI:** the observed PR #12 workflow failed in Laravel `package:discover` because `backend/routes/web.php:165` had a malformed class reference with doubled namespace backslashes. That route reference has now been corrected on the consolidation branch; the rerun must confirm Composer installation, syntax checks, migrations and tests actually execute.
- add a full end-to-end test from a verified Paystack webhook through supplier confirmation, multi-shop collection, dispatch, doorstep OTP/photo, reconciliation, and idempotent supplier/company payouts;
- run migrations and the relevant regression suite on the branch and resolve any failures; no test result should be reported as passing until CI actually returns green.
- **Latest verified workflow result (run 39749183349):** Composer dependency installation and the configured PHP syntax checks passed, but the financial/audit test step failed: **268 passed, 12 failed (1,772 assertions)**. Therefore CI is not green and the branch is not release-ready.
- **Schema migration added; verification pending:** retailer-order tests showed that legacy `orders.vendor_id` and `orders.shop_id` were NOT NULL, preventing one centrally owned customer order from spanning multiple private supplier shops without a fake single-vendor/single-shop owner. Added forward migration `2026_10_22_000004_allow_centrally_owned_retailer_orders.php` to make both legacy pointers nullable while preserving existing values and refusing unsafe rollback. CI must confirm migration compatibility, foreign-key preservation and production-database behavior before this blocker can be marked closed.
- **Supplier availability cooldown test is unresolved:** the 24-hour cooldown regression test did not throw the expected validation exception. Inspect the test's time inputs and the service's confirmation guard; do not weaken the 24-hour rule just to make the test pass.
- **Refund authorization fixture/contract needs reconciliation:** a financial proof test attempted to create a refund without the return case's persisted approval/reviewer state. Preserve the security gate; update the fixture or flow so only an explicitly approved, reviewer-bound case can create a dispatchable refund.
- **Legacy test expectations need triage:** the run includes failures expecting old regional/inter-LGA delivery lanes, vendor child-order payout semantics, legacy merchant wording, and demo-login/UI behavior. Update tests to the Uyo-only retailer contract where those old behaviors are retired; preserve compatibility tests only where historical records/routes must remain readable.
- **Logistics/admin UI verification remains open:** tests did not find the expected company corridor payable wallet label and still expect the old three-tier regional delivery-lane page. Confirm the active admin UI exposes Uyo corridors and company-specific base/bulky tariffs without offering out-of-Uyo routes for new orders.

Do not mark these items complete based on documentation alone. Record each implementation change and test result on the single working branch, and keep the pull request in draft until verification is real.