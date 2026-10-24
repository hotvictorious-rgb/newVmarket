# Supplier Catalog and Admin Listing — Deep Audit

**Date:** 2026-10-23  
**Repository:** `hotvictorious-rgb/newVmarket`  
**Working branch:** `docs/retailer-central-fulfilment-model`  
**Scope:** Supplier offer management, canonical product listing, public supplier privacy, availability gates, and checkout dependencies.  
**Status:** Audit findings recorded; first privacy remediation implemented and CI-tested. No merge or deployment.

## Confirmed architecture

- `Product` is the canonical catalog entity and supports multiple `SupplierOffer` rows.
- Migration `2026_10_21_000007_make_product_supplier_ownership_nullable.php` permits canonical products without legacy `vendor_id` / `shop_id` references. Existing product-to-supplier references are retained for compatibility.
- `SupplierOffer` stores product, supplier, shop, wholesale cost, binary availability, confirmation actor/time, expiry and penalty cooldown. The owner-approved 24 October policy clarifies that no permanent supplier quantity is maintained; the exact requested quantity is confirmed against a verified paid order line.
- `SupplierOfferAvailabilityService` records admin-confirmed availability in a transaction and writes an `AdminAuditLog`; it does not independently contact suppliers.
- `SupplierOfferSelectionService` filters active, verified suppliers and Uyo shops, valid confirmation evidence, expiry, cooldown and workload, then orders by wholesale cost and ID.
- `SourcingAllocationService` resolves eligible offers and whole-order plans.
- Checkout keeps supplier selection server-side; payment settlement and sourcing snapshot paths have existing regression tests.

## Confirmed findings

### P0 — Public supplier identity was still shown in the web storefront

The product detail view displayed `$product->shop->name` and a public shop link under “Sourcing Partner”, despite the public shop directory being retired. The homepage also queried and rendered active shop names and logos.

**Remediation committed:**
- The homepage no longer queries supplier shops for public display; its existing empty state presents Victorious MARKET as the single retailer.
- Public product cards no longer eager-load shop identity.
- Product detail no longer eager-loads supplier/vendor relationships or renders a supplier name/link.
- Added regression coverage asserting that the homepage and product detail do not show the supplier shop name.

### P1 — Admin catalog UI is still legacy-oriented

The existing `admin.products.index` route calls `PortalController::adminProductsPage()` and renders `resources/views/admin/products.blade.php`. It focuses on legacy merchant inventory and flagship badges. The inspected routes expose availability confirmation for an existing offer, but do not provide a complete canonical-product/offer management workflow.

Missing admin capabilities to implement:
- Search the canonical catalog before creating a product.
- Create a retailer-owned canonical product without assigning a supplier as its owner.
- Attach an approved Uyo supplier offer to an existing canonical product.
- Edit wholesale cost with audit history.
- Disable an offer and block it from future allocations; require fresh confirmation before reactivation.
- Retire an offer without deleting canonical product history.
- Approve/list and manually delist products separately from offer availability.
- Show admins why a product is currently unavailable and which offers are eligible, without exposing supplier data publicly.

### P1 — Legacy product inventory still overlaps with offer availability

`SupplierOfferSelectionService::selectLowestCost()` and `getEligibleOffers()` reject products unless legacy `Product.is_in_stock` is true and `Product.isFresh()`. Checkout also checks `is_in_stock`, `isFresh()` and product-level `inventory_quantity`. Meanwhile, offers store a boolean availability state. This is now an intentional business rule rather than a missing quantity field: supplier catalog availability is binary, and ability to fulfill a particular quantity must be confirmed after verified payment against the exact order line.

The product eligibility scope and selector therefore do not apply identical stock rules. This must be reconciled before changing inventory semantics or declaring the supplier catalog fully operational. Do not simply remove stock guards: settlement and inventory restoration paths must be audited together, and concurrency/paid-order exception behavior must remain safe.

### P1 — Canonical product identity and duplicate matching need explicit admin safeguards

The schema supports attaching multiple offers to one product, but the current admin screen does not guide staff to search and match before creating a product. Product matching must distinguish critical variants such as model, size/capacity, condition, pack contents and warranty. Ambiguous matches should require human review; historical orders and foreign keys must not be rewritten by a casual merge.

### P1 — Existing CI has separate financial and delivery test failures on an earlier commit

The earlier run `40026761590` failed two financial assertions and one bulky-surcharge assertion:
- `FinancialLedgerAndCashbackLifecycleTest`: expected a supplier payable credit of 6000.00 but found 5000.00 in the corresponding journal entry.
- `VendorCatalogConditionAndPackageTierTest`: supplier payable ledger did not reconcile to operational item snapshots during recovery.
- A multi-item bulky-surcharge assertion also failed.

These failures were not reproduced by the subsequent full run `40028387802`, which passed 234 tests (1,538 assertions), including the affected test files. Treat the earlier results as transient or environment/order-sensitive until reproduced; do not alter expected ledger values merely to make tests green. Continue monitoring them in later full runs.

## Privacy remediation verification

The latest run triggered by the privacy regression test is:

- Workflow: Paystack refund safety tests
- Run: https://github.com/hotvictorious-rgb/newVmarket/actions/runs/40028387802
- Result: **success**
- Test suite: **234 passed (1,538 assertions)**
- Supplier bank-update regression: **1 passed (9 assertions)**
- New test: `test_public_storefront_and_product_detail_do_not_reveal_supplier_identity` passed.

## Implementation sequence

1. Keep the public supplier privacy regression test and fix in place.
2. Implement a dedicated super-admin supplier catalog screen using the existing `Product`, `SupplierOffer`, `SupplierOfferAvailabilityService` and `AdminAuditLog` rather than creating parallel catalog tables.
3. Add product/offer actions with transactions, validation, audit events and authorization. A newly attached offer must start unavailable until an admin records real availability confirmation.
4. Separate manual product listing state from derived offer availability.
5. Before changing inventory gates, map and test every stock decrement, restore, settlement, refund and post-payment sourcing-hold path. Keep paid-order supplier snapshots immutable.
6. Add regression tests for duplicate matching, disabled-offer exclusion, multiple offers per product, private wholesale data, manual delisting, supplier suspension, post-payment stockout, concurrency and no catalog-triggered financial movements.
7. Re-run the full workflow and diagnose any ledger/surcharge failures as their own issues. Do not merge or deploy until the complete branch status is reviewed.

## Explicit non-goals for this audit commit

- No payment, refund, payout, ledger or supplier-assignment behavior was changed.
- No database migration was added.
- No branch merge or deployment was performed.


## Owner-approved workflow update — 24 October 2026

The owner has approved the [Supplier Catalog and Order-Specific Availability Confirmation Policy](../architecture/SUPPLIER_ORDER_SPECIFIC_AVAILABILITY_CONFIRMATION_POLICY_2026-10-24.md). This resolves the policy ambiguity around supplier quantity: **there is no permanent supplier quantity field**. Supplier-owned private catalog records hold product details, source price and binary in-stock/out-of-stock state; admins match each supplier record to at most one exact canonical public product or create a new canonical listing when no exact match exists. After independent payment verification, the system requests confirmation for the exact quantity of each paid order line, attempts eligible suppliers in priority order, and performs bounded/idempotent failover on decline, inability, timeout or lost eligibility. A single supplier must fulfill the full line unless a separate policy is approved; exhausted sourcing goes to admin-managed exception handling.

This decision clarifies the target behavior but does **not** establish runtime compliance. The code audit remains open for supplier-owned unlinked catalog records, quantity-free data/UI, order-specific confirmation attempts, candidate failover and cooldown interactions, checkout/settlement stock guards, expiry/restoration, concurrency, immutable snapshots, and supplier/payable/ledger effects. Existing legacy stock guards must not be removed until these connected paths are reconciled and regression-tested. No merge or deployment is authorized by this documentation update.
