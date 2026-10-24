# Victorious MARKET — Supplier Catalog and Order-Specific Availability Confirmation Policy

**Decision date:** 24 October 2026  
**Status:** Owner-approved business policy; runtime implementation remains subject to separate audit and tests  
**Scope:** Private supplier catalogs, canonical public listings, binary stock signals, paid-order quantity confirmation, bounded failover, and admin exceptions  
**Authority:** Binding addendum to `ACTIVE_BUSINESS_RULES_2026-10-22.md`. Read with the approved Exception and Reverse-Logistics and Order State, Custody and Recovery policies. This policy does not authorize automated refunds or transfers.

## 1. The retailer remains the public seller

- Victorious MARKET is the only customer-facing retailer. Supplier identities, shop details, wholesale costs, sourcing comparisons, supplier responses, and internal assignment decisions are private.
- All participating suppliers and their single approved shop/pickup point must be within the approved Uyo operating boundary. Supplier accounts are created, approved, activated, and suspended by authorized admins only.
- Admins own and control canonical public product records: public title, description, photos, category, variant-defining attributes, retail price, listing approval and listing state.
- A supplier's private catalog is a separate set of supplier-owned product records. Supplier records are not public storefront listings and are not automatically merged into the canonical catalog.

## 2. Private supplier catalog: no permanent quantity field

A supplier product record may contain:
- supplier's product name and photos;
- supplier/source price;
- brand, model, variant, condition, package contents, compatibility and other relevant specifications;
- a binary availability signal: **In stock** or **Out of stock**; and
- record status and audit history.

**Do not require or maintain a permanent supplier quantity field.** The binary catalog signal is a non-binding indication that the supplier currently carries the item. It is not a reservation, guaranteed quantity, or a promise to fulfill a future order. Do not infer an available quantity from the binary signal.

The supplier may update the signal. Changes must be audited and must trigger recomputation of public purchasability. A stock signal alone must not create an order assignment, reservation, supplier payable, or financial movement.

## 3. Admin matching and canonical listing workflow

- Admin searches the existing canonical public catalog before creating a new public product.
- Admin may attach a supplier's private product record to one canonical public product only after reviewing whether the sellable item is genuinely the same: brand/model, generation, capacity/size, condition, variant, package/accessories, compatibility, and material specifications must match where relevant.
- If no matching canonical product exists, an authorized admin may create a new canonical public product using reviewed supplier information, then decide its public retail price, approval and listing status. Creating a supplier record alone never publishes a listing.
- One supplier product record may be linked to **at most one** canonical product at a time. A record for a different model, variant, condition, package, compatibility, or materially different item must remain separate.
- Do not destructively merge supplier records or supplier accounts. Preserve source records, audit history, existing order references, and immutable order snapshots. Ambiguous matches require human review; do not auto-merge by name or photo alone.
- Wholesale/source cost must be strictly below the public retail price for an offer to be eligible for sale. Enforce this on the server side at creation, update, matching, selection and post-payment revalidation.

## 4. Public listing eligibility

A canonical product is purchasable only if it is approved and listed by Victorious MARKET and at least one linked supplier offer passes all hard eligibility checks, including:
- supplier is active, approved and verified;
- supplier has one valid approved shop inside Uyo;
- linked supplier product is marked **In stock**;
- availability confirmation is sufficiently fresh under the active rules;
- the SKU-specific cooldown has expired;
- supplier is below the active pending-handover limit;
- wholesale cost is strictly below retail price; and
- all other service-area, product and policy constraints pass.

If no linked offer is eligible, hide the product from the normal purchasable catalog or mark it unavailable without deleting the canonical product, supplier records, historical orders, or audit trail. When an offer becomes eligible again, restore public purchasability only if the admin has not manually delisted the canonical product and every other listing rule passes. Supplier catalog changes never override an admin's manual delisting.

Before payment, catalogue and feasibility checks are informational and non-binding. They must not lock a supplier, reserve stock, send a binding order request, assign an order, or create supplier payables.

## 5. Payment-first, exact-order quantity confirmation

- Customer checkout requires an authenticated customer account. No cash on delivery.
- The system must independently verify successful payment on the trusted server side before starting binding supplier confirmation or allocation. A redirect, screenshot, client claim, payment initialization, or unverified event is not proof of payment.
- After verified payment, the system evaluates eligible sourcing candidates and starts a bounded, auditable confirmation attempt with the highest-priority eligible supplier candidate.
- The request must identify the actual paid order and exact line item(s), including requested quantity and relevant variant/specification. The supplier must explicitly confirm whether it can fulfill the **entire requested quantity for that line** by the applicable deadline.
- The system must not rely on the binary catalog status to infer quantity. This post-payment response is an order-specific commitment/confirmation, not a permanent inventory quantity field.
- A supplier must not be asked to provide a permanent quantity to make the catalog work. The order-specific response should record the order/line, supplier offer, requested quantity, response (accept/decline), actor/source, timestamps, deadline, and relevant evidence/audit metadata.
- Unless a separately approved policy explicitly permits it, do not combine partial quantities from multiple suppliers to fill one product line. One supplier must confirm the full requested quantity for that line. Different lines in the same customer order may be sourced from different approved suppliers.
- The complete customer order still uses one assigned rider/logistics company. Apply the active whole-order plan priority: first minimize distinct supplier pickup locations; then minimize total wholesale cost; then prefer documented reliability and stable deterministic tie-breaks. Confirmation attempts must follow that ordered candidate/plan policy without bypassing hard eligibility.

## 6. Bounded failover and supplier response SLA

Use the existing active supplier confirmation SLA unless a later owner-approved policy changes it:
- Confirmation window: 8:00 AM–6:00 PM, Africa/Lagos local time.
- Each supplier has 60 operating minutes to confirm. After 6:00 PM, the timer pauses; the pending countdown restarts fresh at 8:00 AM the next morning under the active rule.
- A clear decline, inability to fulfill the requested quantity, qualifying timeout, or applicable shop-floor breach makes that supplier candidate unavailable for the current sourcing attempt and triggers the existing SKU-specific 24-hour cooldown and Super Admin alert as defined by the Active Business Rules.
- The system then tries the next eligible candidate/feasible plan. It must re-check supplier, offer, shop, stock signal, freshness, cooldown, capacity, price margin, and order state before every new attempt.
- Each supplier/offer/order-line attempt must have a durable idempotency identity and terminal result. Duplicate webhooks, queue deliveries, admin refreshes, process restarts, or concurrent workers must not create duplicate active attempts, duplicate assignment, duplicate supplier payable, or duplicate financial effects.
- Retry work must be bounded. Do not repeatedly contact the same supplier for the same terminal decline/timeout in the same sourcing cycle. A later new sourcing cycle may consider that supplier only when all active eligibility and cooldown rules permit it.
- Concurrent workers must atomically claim the next attempt and enforce a single active confirmation/assignment path for the relevant order line. Preserve immutable order, payment and supplier-cost snapshots.
- A supplier's acceptance applies only to the identified paid order and exact requested quantity. If order contents or quantity change, obtain the required customer consent and create a new valid confirmation decision; do not silently reuse a mismatched confirmation.

## 7. Exhaustion and admin-managed exception

If all eligible supplier candidates have declined, timed out, become ineligible, or cannot fulfill the required quantity:
- stop automated supplier attempts for that sourcing cycle;
- place the already-paid order/line into the existing visible admin-managed exception state;
- preserve paid status, order details, attempt history and immutable financial snapshots;
- alert the authorized admin and expose aging/reason information;
- permit authorized human review, eligible reassignment, customer-approved alternative where policy permits, hold, or cancellation/refund decision under the existing policies.

Do not silently substitute products, split a line across suppliers, cancel a paid order, or trigger an automatic refund. Every refund and bank transfer remains subject to the Zero-Automated-Outflow Law and explicit human Super Admin authorization.

## 8. Required data and audit invariants

At minimum, implementation must be able to distinguish:
- supplier-owned private catalog record and its binary stock signal;
- canonical public product and manual listing state;
- link/match relationship and matching actor/time/reason;
- order-specific supplier confirmation attempt and exact requested quantity;
- confirmation deadline, response, timeout/decline reason and attempt result;
- candidate ordering and reason for exclusion;
- cooldown and eligibility history;
- current assignment versus immutable historical supplier-cost snapshot; and
- admin exception decisions and all related audit events.

Do not make supplier quantity a catalog source of truth. The requested quantity belongs to the customer order; the supplier's answer is scoped to that order/line. Do not use catalog toggles or confirmation responses to trigger automated refunds, transfers, payouts, or ledger postings.

## 9. Acceptance tests required before runtime compliance may be claimed

1. Supplier catalog stores binary in-stock/out-of-stock state without requiring a permanent quantity field.
2. Supplier records remain private and unlinked until reviewed by an admin.
3. Admin can link an exact match or create a new canonical public product; ambiguous and materially different variants are not auto-merged.
4. One supplier record cannot be linked to two canonical products concurrently.
5. Out-of-stock, stale, suspended, unverified, cooldown-blocked, over-capacity, out-of-Uyo, or non-positive-margin offers cannot make a public product purchasable.
6. No supplier is bound, reserved, assigned, or paid before independently verified payment.
7. A paid line with quantity greater than one triggers an explicit order-specific confirmation for the full requested quantity.
8. Supplier decline, inability, timeout, suspension, or stale eligibility moves to the next eligible candidate without duplicate attempts.
9. If no supplier can fulfill the full line quantity, the line enters the admin exception workflow; it is not silently split across suppliers.
10. Duplicate payment events, webhook replay, queue retries, concurrent workers and process restart cannot duplicate attempts, assignments, payables or financial effects.
11. Confirmation windows obey the 8 AM–6 PM operating SLA and restart rule; cooldowns are enforced.
12. A successful supplier confirmation is scoped to the exact order/line/quantity and cannot be reused for a changed order.
13. Public listing hides when all offers are ineligible and restores only if not manually delisted.
14. Supplier identities, source costs, candidate order, and confirmation details never leak to customers or riders.
15. Paid-order failure remains admin-managed; refunds and transfers are never autonomously initiated.
16. All attempts and overrides are auditable; historical snapshots remain immutable.
17. Full regression coverage includes checkout, payment verification, settlement, stockout, order expiry, cancellation/refund, restoration, concurrency and supplier payout/ledger paths.

## 10. Implementation status boundary

This policy records the approved business workflow; it is not evidence that the current database, supplier catalog UI, checkout, payment settlement, queue workers, sourcing service or tests fully implement it. Existing legacy product inventory gates must not be removed merely to fit this policy. Audit all stock decrement, restoration, payment, cancellation, refund, expiry and concurrency paths together, then implement and test the transition safely. Do not merge or deploy based on documentation alone.
