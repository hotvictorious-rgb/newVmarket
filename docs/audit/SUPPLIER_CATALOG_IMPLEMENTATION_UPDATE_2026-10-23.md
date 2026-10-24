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

Remaining work: edit existing product details and media, offer retirement/history, explicit per-offer eligibility explanations, and a careful reconciliation of legacy product-level inventory gates with supplier-offer availability. The branch has not been merged or deployed.

## Follow-up hardening — 24 October 2026

A deeper trace of verified-payment settlement found a concrete compatibility bug: canonical retailer products use `inventory_quantity = 0` as a sentinel because supplier-offer availability is their sourcing signal, but the legacy settlement loop could interpret that value as depleted tracked inventory and place a paid order into a stockout hold. Settlement now skips legacy inventory deduction for the canonical sentinel only while the product remains marked in stock. This preserves the existing last-unit concurrency guard: a legacy product whose final unit was consumed is marked out of stock and still routes the competing paid order to the stockout revalidation hold.

Added a regression test for canonical supplier-backed products with zero legacy inventory. CI passed at [run 40047974508](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/40047974508), including the financial/payment hardening suite and supplier bank-update regression.

A second admin-history gap was fixed: the supplier catalog eager-load now includes soft-deleted offers, so the existing “retired” state in the admin view is reachable. A feature assertion verifies a retired offer remains visible to administrators while it remains excluded from public eligibility. The combined code and regression suite passed at [run 40048032372](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/40048032372): **237 tests, 1,579 assertions**, plus the supplier bank-update regression (**1 test, 9 assertions**); PHP syntax checks and the full backend syntax scan passed.

This resolves the zero-sentinel false stockout and missing retired-offer history, but it is not the end of stock-path reconciliation. Checkout stock guards, payment settlement, expiry/restoration, refund/stockout paths, and concurrency still require a full cross-path audit before removing any legacy stock guards. No payment, refund, payout, ledger, historical-order, merge, or deployment behavior was intentionally changed by this slice.
