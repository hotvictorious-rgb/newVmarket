# Victorious MARKET — Supplier Catalog and Admin Listing Workflow

**Status:** Proposed implementation specification for owner review and code alignment  
**Date:** 23 October 2026  
**Applies to:** Uyo-only, centrally managed retailer model  
**Authority:** Must remain consistent with `docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md` and later owner-approved addenda.

## 1. Core model

Victorious MARKET is the only customer-facing retailer. A public product is a canonical catalogue record owned and controlled by Victorious MARKET. Supplier offers are private sourcing records attached to that canonical product.

- One canonical product may have many supplier offers.
- Each offer belongs to one approved supplier and that supplier's single approved shop/pickup point in Uyo.
- A supplier-owned private catalog record stores the supplier's own product name/photos, source price, relevant model/variant/condition/package/compatibility specifications, and a binary **In stock / Out of stock** signal. Do not require or maintain a permanent supplier quantity field. A linked supplier offer stores its canonical-product link, wholesale cost, eligibility/confirmation evidence and expiry, SKU cooldown, notes, and active/disabled state. Order-specific quantity confirmation is recorded against a paid order line, never as catalog quantity.
- The public product stores the customer-facing title, description, images, category, variant-defining attributes, retail price, approval/listing state, and product handling/bulky attributes.
- Supplier identities, shop details, wholesale costs, sourcing comparisons and supplier assignment decisions must never be exposed to customers or public storefront APIs.
- Historical orders and immutable supplier-cost snapshots must not change when a product or offer is edited later.

## 2. Keep three controls separate

Do not collapse these independent states into one boolean.

1. **Supplier account status:** Is this supplier approved and active as a participant in the private sourcing network?
2. **Supplier offer status:** May this specific supplier-product offer be considered for future sourcing? Is it in stock with valid, unexpired confirmation and outside any penalty cooldown?
3. **Public product listing status:** Has Victorious MARKET approved this canonical product for public display, and has an admin manually hidden it?

A supplier may be active while one offer is disabled. A product may be approved while temporarily unavailable because no offers are eligible. An offer may be available while the public listing remains manually delisted.

## 3. Product and offer lifecycle

### Canonical product lifecycle
- Draft / pending review: not visible to customers.
- Approved and listed: potentially visible only when all listing gates pass.
- Manually delisted: hidden regardless of supplier stock until an authorized admin relists it.
- Automatically unavailable: hidden or marked unavailable when no eligible offer remains in stock.
- Archived/deleted: must not destroy historical order, offer, payment, refund or ledger references.

### Supplier offer lifecycle
- Draft: created by an authorized admin, not allocatable.
- Active but unconfirmed: known sourcing option, not allocatable.
- Confirmed in stock: eligible only while the confirmation is valid and every supplier/shop/cooldown/capacity/margin rule passes. This binary signal does not prove the supplier can fulfill any particular order quantity.
- Out of stock / unavailable: excluded from allocation.
- Disabled by admin: excluded immediately from new allocations regardless of reported stock.
- Cooldown: excluded until the applicable penalty expiry; do not bypass it by toggling active.
- Soft-deleted/retired: excluded from new allocation while historical records remain available.

Availability confirmation is evidence, not a reservation. Catalogue browsing, adding to cart, pre-payment validation, and a public listing must not reserve supplier stock or create an order-specific supplier assignment.

## 4. Admin workflow

1. Search the canonical catalog by exact model/SKU, brand, size, capacity, colour, condition, pack size and other variant-defining attributes before creating a new product.
2. If the same sellable item already exists, attach a new supplier offer to that product instead of duplicating the public listing.
3. If a critical variant differs (for example, 4x6 versus 6x6 mattress, or phone storage capacity), create a distinct product/variant. Do not merge based only on similar names or category.
4. Record the supplier's private product details, approved Uyo shop, wholesale cost, binary in-stock/out-of-stock signal, notes and evidence source. Do not record a permanent stock quantity.
5. Keep a newly created/edited offer unavailable for sourcing until authorized confirmation requirements are met. Never fabricate confirmation evidence.
6. Admin sets or updates the public retail price and listing content separately from wholesale costs.
7. Preview the public listing and verify that only Victorious MARKET branding, customer-facing product details, retail price and Uyo delivery information appear.
8. The system derives public purchasability from manual listing approval plus the existence of at least one currently eligible offer. If none remains, it becomes unavailable automatically without erasing the product or its history.

## 5. Admin listing and offer controls

The admin interface should provide separate actions:

- **Create canonical product**
- **Edit public product details**
- **Approve / reject product**
- **List / manually delist / relist product**
- **Add supplier offer to existing product**
- **Edit offer wholesale cost and operational notes**
- **Confirm offer availability** with authorized confirmer and timestamp
- **Mark offer out of stock / unavailable**
- **Disable / re-enable an offer** with a reason
- **Retire an offer** without deleting historical records
- **View all offers for one product**, including eligibility reason, freshness/expiry, cooldown, supplier status and last update
- **View product availability summary**, including why a product is hidden or unavailable
- **Audit changes** with actor, timestamp, reason, and before/after values

Disabling an offer must prevent future allocation immediately. It must not silently cancel or rewrite an already-paid order assignment. Existing in-flight assignments require the prescribed explicit admin review/recovery workflow. Re-enabling an offer must not override supplier suspension, invalid/expired confirmation, cooldown, capacity limits, Uyo boundary, positive-margin requirement, or other hard eligibility rules; it does not create a quantity claim.

## 6. Availability and storefront rules

A product is publicly purchasable only when:
- the canonical product is approved and manually listed;
- its own product/listing freshness requirements pass, if retained by the active policy;
- at least one offer is currently eligible under the shared supplier eligibility rules;
- the supplier is approved, active and verified, with exactly one active approved shop in Uyo;
- the shop and destination/service-area requirements pass;
- the linked supplier product is explicitly marked **In stock** and the offer has sufficiently fresh confirmation; the binary status is not a quantity guarantee;
- the applicable 24-hour stockout penalty cooldown has expired; and
- supplier active-pending-handover capacity rules pass.

A listing availability calculation must use supplier offers as its source of sourcing truth. Legacy product-level `vendor_id`, `cost_price`, `is_in_stock` or `inventory_quantity` fields must not independently make an otherwise unsourceable product purchasable. Before removing or changing legacy fields, audit all callers, migrations, admin screens, public APIs, fixtures, order snapshots and older flows. Preserve backwards compatibility until the migration and dependent tests are safely reconciled.

If all offers become unavailable or ineligible, mark the product unavailable to customers automatically. If an eligible offer returns, make the product available again only if the admin has not manually delisted it and all other product gates pass.

## 7. Payment and allocation boundary

- Before verified Paystack payment, perform only non-binding catalogue and feasibility checks. Do not reserve stock, notify a supplier of a binding order, allocate a supplier to an order, or create supplier payables.
- After independently verified payment, re-check current offer eligibility under concurrency-safe transaction/locking rules, then request an explicit order-specific confirmation that the supplier can fulfill the exact requested quantity for the paid line. The supplier's response is not a permanent catalog quantity field. Try eligible candidates in the defined priority order after a decline, inability to fulfill, or qualifying timeout.
- If all eligible candidates decline, cannot fulfill the full line quantity, time out, or become ineligible, stop the bounded sourcing cycle and move the paid order/line to the visible admin-managed exception process. Do not split one line across suppliers unless a separately approved workflow authorizes it; do not silently refund or substitute an item.
- Snapshot the selected supplier offer, supplier identity, wholesale cost, product variant and relevant sourcing terms immutably against the order line at allocation. Later catalog/offer edits must not rewrite that snapshot.
- Supplier assignment overrides require authorized Super Admin permission, a reason and immutable audit evidence. They cannot bypass verified payment or hard safety requirements.

## 8. Product matching and merge safety

Merging means attaching a supplier offer to the same canonical product, not merging supplier accounts, shops, balances or historical order records.

- Use exact identifiers and variant-defining attributes for safe matching.
- Present likely duplicates to an admin for review; do not auto-merge ambiguous products.
- If duplicate canonical products already exist, a separate audited merge workflow is needed: select a surviving canonical product, map/reattach eligible offers, preserve redirects/slug behaviour and reviews as appropriate, and preserve all historical order-line snapshots and foreign-key integrity.
- Never combine different conditions, sizes, capacities, model generations, warranty terms or pack quantities merely to reduce catalog count.
- Record the merge actor, reason, source/target product IDs and before/after state. Do not hard-delete source records used by historical transactions.

## 9. Security and financial safeguards

- Supplier and wholesale-cost data is admin-only and must be redacted from public API payloads, customer pages, logs accessible to customers, rider interfaces and notifications.
- Use role authorization on every mutation; hiding buttons in the UI is not authorization.
- Validate supplier/shop relationship and Uyo eligibility server-side.
- Use database transactions for multi-record changes and prevent conflicting duplicate offers where the schema supports a reliable uniqueness rule.
- Keep append-only audit events for offer activation/deactivation, cost changes, stock confirmation, product listing changes and product merges.
- Never change an existing order's wholesale snapshot or create financial movements from a catalog toggle.
- A catalog change must not trigger a refund, transfer or other automated financial outflow.

## 10. Required test coverage before claiming complete

1. One canonical product with multiple eligible supplier offers appears once in the public catalog.
2. Supplier identities and wholesale costs never appear in public APIs or customer HTML.
3. Cheapest eligible offer is selectable only after hard eligibility checks; stale, suspended, out-of-stock, cooldown-blocked, over-capacity or non-positive-margin offers are excluded. Binary stock status never substitutes for order-specific quantity confirmation.
4. Disabling one offer excludes it from new sourcing while other eligible offers keep the product available.
5. Disabling the last eligible offer hides/makes the product unavailable without deleting it or its history.
6. Re-enabling an offer does not bypass confirmation expiry, cooldown, supplier suspension, location, margin or capacity rules; it does not create a quantity claim.
7. Manual delisting remains effective even when an offer is reactivated; manual relisting does not make an unsourceable product purchasable.
8. Product matching does not merge distinct variants or conditions.
9. Supplier offer changes before payment do not create reservations or assignments.
10. Post-payment sourcing requests explicit full-line-quantity confirmation, revalidates eligibility under concurrent requests, and never assigns an unconfirmed/ineligible offer.
11. Existing paid/in-flight assignments are preserved for explicit recovery review when an offer or supplier is disabled.
12. Order wholesale snapshots remain unchanged after offer cost edits, offer retirement or catalog merge.
13. Unauthorized users cannot create, activate, disable, merge or reprice products/offers.
14. All mutations record actor, reason, timestamp and before/after state.
15. Regression tests cover public storefront APIs, admin listing, product detail pages, sourcing, checkout and existing financial paths.

## 11. Implementation note

The current codebase already has `Product` and `SupplierOffer` models, a product-to-offers relationship, an admin products route, and an admin offer-availability confirmation route. That is a useful foundation, not proof that the workflow is complete. Audit the existing controllers, views, migrations, public API serializers, offer availability service and sourcing/allocation service before changing the schema or UI. Align implementation incrementally on the `docs/retailer-central-fulfilment-model` candidate branch and run focused plus regression tests. Do not merge or deploy based only on this document.


## 12. Order-specific supplier confirmation and failover policy

The binding detail is defined in [Supplier Catalog and Order-Specific Availability Confirmation Policy](SUPPLIER_ORDER_SPECIFIC_AVAILABILITY_CONFIRMATION_POLICY_2026-10-24.md). In short: the supplier catalog has a binary stock signal and no permanent quantity field; binding supplier requests begin only after independently verified payment; each request identifies the paid order line and exact quantity; one supplier must confirm the whole line; and declines, inability, timeouts or eligibility loss trigger bounded, idempotent failover to the next eligible candidate. Exhaustion routes to the admin-managed exception process. Preserve the existing 8:00 AM–6:00 PM Africa/Lagos operating window, 60 operating-minute SLA, restart-at-8-AM rule, SKU-specific cooldown, whole-order pickup-location optimization, immutable supplier-cost snapshots, and manual refund/transfer authorization. Runtime compliance remains unverified until the acceptance tests in the policy and cross-path regression suite pass.
