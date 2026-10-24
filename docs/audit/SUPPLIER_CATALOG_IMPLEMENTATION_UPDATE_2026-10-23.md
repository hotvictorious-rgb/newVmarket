# Supplier Catalog Implementation Update

Date: 23 October 2026
Branch: `docs/retailer-central-fulfilment-model`

The initial deep audit identified public supplier-name leakage and the absence of a coherent admin supplier catalog workflow. The following first implementation slice is now committed:

- Public homepage and product detail no longer reveal supplier shop names or link customers to supplier storefronts.
- Added a dedicated super-admin supplier catalog screen and navigation link.
- Admin can create a canonical product draft without assigning supplier ownership, attach a private supplier offer, update wholesale cost, disable an offer with a reason, and list or manually delist a product.
- New and restored offers start unavailable until admin availability confirmation is recorded.
- Admin offer creation/cost updates reject wholesale cost at or above retail price. Public product eligibility and offer selection also exclude non-positive-margin offers.
- Catalog changes are recorded in admin audit logs; catalog actions do not initiate payouts, refunds, or ledger movements.

Validation: [GitHub Actions run](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/40029147787) passed: 236 tests, 1,569 assertions. The supplier bank-update regression also passed (1 test, 9 assertions). PHP syntax checks and the full backend syntax scan passed.

Remaining work: edit existing product details and media, explicit per-offer eligibility explanations, implement supplier-owned unlinked private catalog records and binary-only stock state, implement post-payment exact-line quantity confirmation with bounded/idempotent candidate failover, and reconcile legacy product-level inventory gates with supplier-offer availability across checkout, settlement, expiry/restoration, cancellation/refund and concurrency. The branch has not been merged or deployed.

## Follow-up hardening — 24 October 2026

A deeper trace of verified-payment settlement found a concrete compatibility bug: canonical retailer products use `inventory_quantity = 0` as a sentinel because supplier-offer availability is their sourcing signal, but the legacy settlement loop could interpret that value as depleted tracked inventory and place a paid order into a stockout hold. Settlement now skips legacy inventory deduction for the canonical sentinel only while the product remains marked in stock. This preserves the existing last-unit concurrency guard: a legacy product whose final unit was consumed is marked out of stock and still routes the competing paid order to the stockout revalidation hold.

Added a regression test for canonical supplier-backed products with zero legacy inventory. CI passed at [run 40047974508](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/40047974508), including the financial/payment hardening suite and supplier bank-update regression.

A second admin-history gap was fixed: the supplier catalog eager-load now includes soft-deleted offers, so the existing “retired” state in the admin view is reachable. A feature assertion verifies a retired offer remains visible to administrators while it remains excluded from public eligibility. The combined code and regression suite passed at [run 40048032372](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/40048032372): **237 tests, 1,579 assertions**, plus the supplier bank-update regression (**1 test, 9 assertions**); PHP syntax checks and the full backend syntax scan passed.

This resolves the zero-sentinel false stockout and missing retired-offer history, but it is not the end of stock-path reconciliation. Checkout stock guards, payment settlement, expiry/restoration, refund/stockout paths, and concurrency still require a full cross-path audit before removing any legacy stock guards. No payment, refund, payout, ledger, historical-order, merge, or deployment behavior was intentionally changed by this slice.


## Owner-approved supplier quantity and confirmation policy — 24 October 2026

Added [Supplier Catalog and Order-Specific Availability Confirmation Policy](../architecture/SUPPLIER_ORDER_SPECIFIC_AVAILABILITY_CONFIRMATION_POLICY_2026-10-24.md) and aligned the Active Business Rules, supplier-catalog architecture, and deep-audit record.

The approved workflow is: supplier-owned private catalog record; binary **In stock / Out of stock** status with no permanent supplier quantity field; admin match to one exact canonical public product or creation of a new public listing; independently verified customer payment; then explicit supplier confirmation for the exact quantity on each paid order line. Declines, inability to fulfill, qualifying timeouts, or lost eligibility trigger bounded, idempotent failover to the next eligible supplier. If all eligible candidates are exhausted, the order/line enters admin-managed exception handling. One line is not split across suppliers without a separately approved policy. Existing Uyo restrictions, 60-operating-minute 8 AM–6 PM Africa/Lagos SLA, restart-at-8-AM rule, 24-hour SKU cooldown, whole-order sourcing priority, immutable snapshots, and Zero-Automated-Outflow Law remain in force.

**Important implementation status:** this commit records and aligns documentation only. It does not claim that the current schema, admin UI, checkout, payment settlement, queue processing, sourcing assignment or tests implement this complete workflow. In particular, supplier-owned unlinked records and exact-order confirmation/failover are still to be verified/implemented, and legacy inventory checks must not be removed until all connected money and stock paths are audited. No code tests were run for this documentation-only change. No merge or deployment was performed.
