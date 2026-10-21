# Victorious MARKET — Simplified Delivery-First Fulfilment Policy

**Policy date:** 21 October 2026  
**Status:** Owner-directed current policy  
**Applies to:** New retailer-model transactions and all current product, checkout, fulfilment, support, finance, API and customer-facing documentation  
**Authority:** This policy supplements `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`. If older documents describe public multi-vendor selling, customer-facing shop selection, automatic in-shop pickup reservations, or supplier-address disclosure, those descriptions are historical/legacy requirements and do not define the current customer journey.

## 1. The simplest operating model

Victorious MARKET (VM) is one customer-facing retailer. Private suppliers are sourcing partners behind the scenes, not independent marketplace sellers. VM owns the retail listing, retail price, customer relationship, checkout, support, refund decision and fulfilment coordination.

All approved supplier shops and all approved logistics companies must be physically based in **Uyo, Akwa Ibom State**. The initial delivery network is Uyo-centred, not Uyo-destination-only. Within Uyo, the standard method is **door-to-door delivery**. Outside Uyo, an approved Uyo-based logistics company manages the delivery arrangement (for example, park/terminal collection or another agreed handoff); it is not automatically doorstep delivery. Destinations include approved Akwa Ibom LGAs and the named regional cities Aba, Calabar and Port Harcourt, but a destination is available only when its directional route is explicitly enabled. The customer places one order and pays through authorized VM checkout. Checkout displays one order-level delivery charge: one configured base route fee plus a configured bulky surcharge for each physical product unit that does not fit on a bike. The base fee is charged once per customer order, not once per supplier or product line. The bulky surcharge is multiplied by the quantity of each line marked keke/bulky. Snapshot the base fee, bulky quantity/surcharge, and final total before payment; never invent tariffs or silently change them after checkout.

## 2. No automated pickup or reservation at checkout

The customer-facing website and APIs must not offer an automated in-shop pickup checkout, supplier selection, supplier-address disclosure, or inspect-first/pay-later reservation as a standard fulfilment option.

A customer who prefers pickup must contact VM support by phone or WhatsApp. Support-assisted pickup is an exception, not a second automated checkout workflow. Support must verify the complete order, product availability, physical readiness, approved collection point, payment arrangement and customer agreement before confirming the plan.

**Never tell a customer to travel to a supplier simply because that supplier has a listed product.** Do not disclose a supplier/collection address or claim an order is ready until the approved plan is confirmed and the required products are physically available at that point.

## 3. Whole-order coordination

Sourcing decisions must account for the complete order, not independently direct the customer to each product's nearest supplier.

For each line, VM identifies eligible private sources using confirmed current availability, active/approved source status and valid route/fulfilment coverage. VM then determines whether the complete order can be fulfilled reliably. If products come from multiple sources, VM coordinates internal collection and consolidation or chooses another feasible delivery plan. The customer must not be made to coordinate suppliers.

A single customer-facing order must not silently become multiple customer delivery fees or undisclosed supplier visits. Any exception that materially changes the agreed price, delivery promise or collection arrangement requires support communication and customer agreement before proceeding.

## 4. Delivery and commercial rules

- Delivery is charged once per order using the configured destination/service base route fee. Add the configured bulky surcharge for each physical unit that does not fit on a bike (each unit marked keke/bulky, multiplied by quantity). Do not charge another base fee for each product or supplier pickup. Display the base fee, bulky quantity and surcharge, and one order-level total before payment; snapshot the components and total so later fulfilment cannot silently reprice the order. Supplier identity must not change the customer's agreed charges after checkout.
- Uyo-local orders use door-to-door delivery. Orders outside Uyo use the assigned logistics company's configured regional handoff method; the method and instructions must be communicated to the customer before dispatch. Do not mark a regional parcel delivered merely because it reached a park/terminal: the required customer receipt OTP and photo evidence still apply.
- Supplier shops and logistics-company headquarters must be physically located in Uyo, Akwa Ibom State. Other origins are rejected for new retailer fulfilment.
- Outside-Uyo destinations must have an explicitly active directional delivery lane. The old automatic nationwide fallback is not authority to accept a destination. Each route is independently enabled/disabled by operations.
- The default logistics-company payable is 90% of the total delivery fees actually captured from the customer for the order, including applicable per-unit bulky surcharges. Calculate from the immutable captured delivery-fee snapshot, not merchandise value or an unverified quote. An authorized Super Admin may override the percentage/amount higher or lower only with a reason and audit trail. Any company payable above delivery fees collected is an explicit VM-funded subsidy/expense. This payable belongs to the logistics company, not directly to its rider; do not create a second rider payable by default.
- Cashback remains active under the existing policy: 5% of eligible post-discount merchandise actually funded by real money through verified payment, excluding delivery and applying all existing redemption, maturity, reversal, validity and recovery terms.
- Supplier payable is based on the immutable agreed supplier cost snapshot and becomes eligible only after the applicable verified fulfilment and reconciliation gates. Supplier recovery is separate from customer refunds.
- Payment success alone never proves stock readiness, dispatch or delivery. Server-side payment verification, explicit state transitions, idempotency and audit records remain mandatory.

## 5. Customer information and support

Customer-facing messages must identify Victorious MARKET as the retailer and support contact. Supplier identities, wholesale costs, internal source choices and internal collection routes remain private unless disclosure is legally necessary or explicitly approved for a support-managed exception.

Suggested customer-facing pickup message:

> Prefer pickup? Please call or WhatsApp Victorious MARKET support to ask whether pickup can be arranged for your complete order. Pickup is not confirmed until our support team verifies availability and sends you the approved collection instructions. Please do not travel to a collection point before confirmation.

## 6. Legacy compatibility and retirement

Existing pickup reservation tables, routes, models, views, state values, historical reservations and old multi-vendor records may be retained temporarily for safe migration, audit, refunds, reporting and historical reads. Their existence does not authorize the active customer journey to create new pickup reservations.

Before removing legacy code/schema, trace all callers, scheduled jobs, financial journals, webhook handlers, tests, seeded/demo data and historical records. Use forward-only migrations, preserve auditability, and prevent new public writes first. Do not delete or reinterpret historical financial records. A documentation update alone does not mean runtime routes, views, APIs or production legal-document rows have already been disabled.

## 7. Required implementation acceptance criteria

1. Standard checkout uses door-to-door delivery inside Uyo and logistics-managed regional delivery outside Uyo; the regional handoff method is disclosed before dispatch.
2. Customer cannot select a supplier/shop or retrieve a private supplier address through checkout, product pages, public APIs or reservation lookup.
3. Pickup reservation creation and supplier-address disclosure are disabled for new customer-facing requests; support-managed exceptions use an authorized, audited process.
4. One order can contain products from multiple private suppliers without requiring multiple customer destinations.
5. The whole order is checked for availability and a feasible fulfilment plan before promising readiness or dispatch.
6. One configured base route fee is charged per order, with a bulky surcharge for each physical unit that does not fit on a bike; the breakdown and order-level total are displayed before payment and snapshotted; the logistics-company default payable is 90% of captured delivery fees, not merchandise value.
7. Support cannot bypass payment verification, sourcing eligibility, delivery coverage, OTP/photo evidence, refund controls or financial idempotency.
8. Existing reservations/orders remain readable and auditable; no historical rows are mass-deleted or relabelled.
9. Tests cover attempts to create public pickup reservations, disclose addresses early, mix multiple sources, use unavailable sources, duplicate transitions, and preserve legacy reads.
10. Documentation, API/OpenAPI schemas, customer UI, legal terms, admin workflows, tests and production database state are aligned before claiming the change is fully implemented.

## 8. Verification status

This file establishes the current business policy. Runtime work is in progress on `docs/retailer-central-fulfilment-model`: checkout intent and direct checkout no longer require a customer-selected shop, resolve each product to a fresh confirmed Uyo supplier offer, snapshot item-level supplier costs, calculate one order-level base fee plus per-unit bulky surcharges, and create per-supplier assignment rows. Supplier availability confirmation is now per supplier and handover is blocked until all suppliers confirm. Supplier-specific readiness, unique collection OTPs, and mandatory per-supplier pickup proof have now been added to the runtime path. An audited admin item-level override route is available before payment. Suppliers can now reject an awaiting-confirmation assignment after payment; the order is placed on a sourcing hold and no automatic refund or second supplier payable is created. A follow-up confirmation from another supplier is now blocked while that order-wide rejection hold is active, preventing the ordinary confirmation path from overwriting `supplier_rejected` and reopening collection preparation. Post-payment reassignment and the associated supplier-payable journal reclassification remain unimplemented. The legacy `disburseVendorSettlement` path is now explicitly blocked for retailer-model orders, even if a stale or manually changed order-level settlement status says `eligible`; that legacy path pays `vendor_net_90` to `order.vendor` and cannot safely settle item-level private-supplier liabilities. This guard is a safety barrier, not a replacement for the still-missing supplier-specific payout and post-payment recovery workflows. **Financial trace finding:** the Paystack settlement path currently posts item-level `supplier_payable` credits using the captured order-item supplier/cost snapshots during payment settlement. That means a post-payment supplier change cannot safely update only `order_items` or the order's cost/margin snapshots: the original supplier payable is already represented in the immutable journal. Any recovery must append balanced compensating journal entries linked to an idempotent, audited resolution operation, preserve the original capture and its journal rows, and reconcile the old/new supplier payable plus any cost delta against retailer margin/loss before collection can resume. Do not mark supplier payout eligible merely because a payable credit exists; payout eligibility must separately enforce the fulfilment and reconciliation gates. This accounting path has been inspected but not yet covered by a new runtime test. Notification/dispatch coordination for every supplier stop, full lifecycle integration with the logistics-company assignment UI, and end-to-end refund/reconciliation verification also remain. Legacy pickup reservation endpoints/UI and historical order-level handover paths still require regression review. Tests have been edited but have not been run; no CI success, merge to `main`, or deployment is claimed. Do not treat this as release-ready until the remaining workflow and regression tests pass.


## 9. Owner-confirmed geography and delivery modes (21 October 2026)

- Supplier origin: Uyo LGA, Akwa Ibom State only.
- Logistics-company headquarters: Uyo LGA, Akwa Ibom State only.
- Local mode: Uyo destination — door-to-door.
- Regional mode: approved LGAs across Akwa Ibom State and explicitly enabled routes to Aba (Abia), Calabar (Cross River), and Port Harcourt (Rivers). Outside-Uyo delivery is managed by the Uyo-based logistics company and may be park/terminal collection or another agreed arrangement; do not assume doorstep delivery.
- Regional routes must be explicitly active in the directional route matrix. No generic nationwide fallback may make an unconfigured destination purchasable.
- Regional completion still requires order-specific server-verified OTP and mandatory photo proof. The handoff must represent receipt by the customer or authorized recipient, not merely arrival at a park.

## 10. Paid-order supplier-rejection recovery — implementation and release contract

A supplier rejection after confirmed payment is a financial and custody hold, not permission to overwrite the original supplier snapshot. The working branch now contains an admin-only recovery service/controller, a recovery route, separate operational supplier snapshots, balanced supplier-payable compensation entries, and audit/idempotency handling. This implementation is **not yet CI-verified**; do not merge or deploy until the remaining acceptance tests and the full test suite pass.

1. Lock the order first, then lock all affected supplier assignments and order items in a consistent order. Recheck payment is confirmed, the order remains on the explicit supplier-rejection hold, and the affected items still belong to the rejected assignment.
2. Require an active supplier account with exactly one active shop/pickup point in Uyo, Akwa Ibom, plus an attributable, unexpired availability confirmation for every affected product. Revalidate this evidence inside the transaction; never trust a client-supplied supplier ID, price or cost.
3. Preserve the customer-facing merchandise price, delivery fee, delivery mode and already-promised cashback calculation. The supplier cost change is an internal retailer cost variance, not a customer price change.
4. Preserve original `supplier_*` capture-time item snapshots and capture-time journal entries as historical evidence. Recovery updates only `fulfillment_supplier_*` operational snapshots. Append compensating entries: debit the old supplier's `supplier_payable`, credit the replacement supplier's `supplier_payable`, and post any difference to `retailer_sourcing_variance`. Every resolution must balance, be idempotent, and be tied to an immutable audit record.
5. Lock the order before checking the audited idempotency key and applying compensation. Retrying the same instruction returns the recorded result; reusing its key for a different supplier, item set or reason fails. Unique journal idempotency keys provide an additional duplicate-effect barrier.
6. Update the current operational sourcing assignment and cost projection only in the same database transaction as the compensating journal entries and audit record. The original capture-time snapshot and journal rows remain unchanged.
7. Keep collection, dispatch and supplier payout blocked until all assignments are confirmed, the replacement packages are ready and the payable reconciliation passes. A payable credit by itself never means payout eligibility.
8. If no valid replacement exists, keep the order on hold and route it through the existing authorized refund decision/workflow. Do not initiate an automatic gateway refund, invent a direct Paystack call, silently cancel a paid order, or release a supplier payable without a balanced accounting decision.
9. Before release, test repeated identical recovery requests, conflicting idempotency-key reuse, concurrent admin attempts, stale/expired replacement offers, replacement-cost increase/decrease, partial item reassignment, a second rejection, no replacement, failed refund authorization, and order-level debit/credit balance. Verify customer total and cashback do not change.
10. Current status: implementation exists on the working branch, but tests have not been run in this session and concurrent-admin, stale-offer, no-replacement/refund-review and second-rejection scenarios still need explicit regression coverage. The legacy vendor payout route remains fail-closed for retailer orders; supplier-specific payouts are not enabled. Do not merge/deploy until the complete supplier recovery and payout acceptance suite is implemented and CI passes.

## 11. Paystack payout initiation and reconciliation hold safety (21 October 2026)

A regression review found a mismatch between `PaystackService::initiateTransfer()` and both admin payout controllers: the service returns `UNCERTAIN_TIMEOUT` for connection exceptions and `TRANSFER_FAILED` for non-definitive gateway failures, while the controllers previously treated only `NETWORK_EXCEPTION` and `UNCERTAIN_TIMEOUT` as uncertain. In particular, the old check did not recognize the service's actual timeout code, and an HTTP 5xx failure could release a payout even though the gateway outcome was not proven.

- Vendor and logistics payout initiation now release the retry hold only for the explicitly classified `DEFINITIVE_REJECTION`, `CONFIG_MISSING`, and `AMOUNT_BELOW_MINIMUM` outcomes. Other unsuccessful transfer responses retain `HOLD_TIMEOUT_RECONCILIATION` and the processing gateway state.
- Regression coverage now includes: an unsuccessful verification response saying “reference not found” must leave a vendor payout held; and both vendor and logistics transfer-initiation tests assert that `UNCERTAIN_TIMEOUT` retains the hold, processing gateway state, and stable transfer reference. Only a successful, authoritative verification response whose transfer status is explicitly `failed` or `reversed` may use the reconciliation reversal path.
- Commits: payout-controller correction `a67af42e1fe16f866995053cb4acdc011fe7165e`; reconciliation regression `6c9458e0b9610e97b3eddfd9ea22cd3f38108b5f`; vendor initiation regression `e04d8fa1889cce9e7fcadf1c596ec1c189fce958`; logistics initiation regression `f66a5695b1b0e6952b7bcdf0f0e23076b4170ac7`.
- Verification status: source assertions confirm both controller checks were changed; the branch reports no CI status checks for the controller commit. The PHP test suite has not been run here. This is not a claim of full payout-flow correctness or release readiness. Keep the branch unmerged and undeployed until the relevant vendor/logistics initiation and reconciliation tests plus the full suite pass.

## 12. Financial journal idempotency schema gap (21 October 2026)

A source-to-schema trace found that recovery journal writers populate `financial_journal_entries.idempotency_key`, and the Eloquent model lists it as fillable, but the checked-in journal migrations defined `event_id` and `financial_operation_id` without creating the idempotency-key column or a database uniqueness constraint. Application-level replay checks alone are not enough to prevent duplicate journal effects under retries or concurrency.

- Added migration `2026_10_21_000002_add_financial_journal_idempotency_key.php` to add a nullable, unique `idempotency_key` column to the existing journal table. Nullable values preserve compatibility with historical entries; new recovery journal rows use deterministic non-null keys.
- Added `FinancialJournalIdempotencySchemaTest` to assert the column exists and the database rejects duplicate non-null keys.
- Commits: migration `aa417357e9cb3a0b7d9e3045de88857c395ed7e9`, namespace correction `3d62471befa5a64d223fc739b98b8608b40b8ec0`, schema regression test `22b1037e67a681db004df45c0a6205fd03327c61`, test namespace correction `f5e4d7ad6b5d1889a03796da9fdaccedf9db4d8c`.
- Verification status: source files and migration/test presence are confirmed on the working branch, but the migration and test have not been executed in a PHP runtime or CI. Do not treat this as release-ready until migrations run cleanly against a representative existing database and the new test plus the full suite pass.

## 13. Legacy vendor payout reservation leak on missing bank details (21 October 2026)

A source trace of the legacy vendor payout path found that it could create new `VendorPayoutAllocation` rows and only afterwards validate the supplier/vendor bank account name and account number. The controller returns a normal `valid=false` result from inside the transaction for missing bank details; because this is not an exception, the transaction commits and the allocated rows remain reserved even though no gateway transfer was attempted. This could make otherwise eligible legacy vendor earnings appear unavailable until manual repair.

- Updated `PortalController::adminDisburseVendorPayout` to reverse all still-`allocated` rows for that payout request when the bank-detail check fails. The reversal is timestamped, and no journal liability is fabricated because the gateway has not been called.
- The change is fail-safe for the no-transfer condition: the controller performs bank validation before recipient creation or transfer initiation. It does not reverse allocations for an active gateway-processing claim.
- Commit: `1f5266c6a5a94e659d2a2b5706f38c321cbcaec9`.
- Verification status: source change committed to the working branch; no PHP test run or CI result is available. Add/execute a regression test that proves missing bank details reverse allocations and leave the payout request retryable before release.

**Still-open supplier money blocker:** the legacy vendor payout allocator explicitly excludes `retailer_model_order=true`, and the legacy order settlement payout is fail-closed for retailer-model orders. That prevents paying a multi-supplier retailer order to the order-level `vendor_id`, but it also means there is no completed supplier-specific payout workflow yet. Supplier-payable journal balances must remain held until a dedicated workflow atomically reserves each supplier's reconciled, fulfilment-eligible order-item payable, excludes disputed/returned/rejected/reassigned items, prevents cross-order or cross-supplier allocation, and finalizes or reverses the gateway transfer idempotently. Do not enable supplier payouts by removing the legacy guard or by using `VendorProfile::getWithdrawableBalance()` alone.
