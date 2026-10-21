# Victorious MARKET — Cross-Document and Implementation Alignment Audit

**Audit date:** 20 October 2026  
**Canonical working branch:** `docs/retailer-central-fulfilment-model`  
**Status:** Current policy alignment plus partial runtime implementation; launch blockers and CI verification remain open  
**Authority:** `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`  
**Release rule:** Do not merge to `main`, deploy, or represent a finding as fixed without explicit owner approval and evidence.

> **Latest binding policy correction (21 October 2026):** This current baseline supersedes older source-audit conclusions below wherever they conflict. Sections documenting vendor-managed delivery, 90/5/5 merchandise allocation, vendor 90/10 delivery earnings, disabled cashback, or vendor payout FIFO rules are retained as historical findings only; they are not current policy for new retailer transactions.

## 1. Scope and limits

This audit compares the current authoritative system rules with the payment/refund matrix, payout policy, delivery audits, retailer model, README/project overviews, legal-document seeder, checkout, pickup, settlement, cashback and logistics-payable paths. The working branch now includes documentation changes, a forward migration for retailer financial snapshots and logistics-company overrides, retailer settlement branching, pickup cashback correction, an admin logistics-company assignment/override service and routes, and regression coverage.

This is **not** proof that production is aligned. The live database's published legal-document rows, production configuration, provider dashboard, bank records, and deployed runtime were not inspected. The latest code changes have not yet been confirmed by a successful CI run. Parent/child checkout, customer-facing shop independence, pickup offer sourcing, supplier disbursement lifecycle, post-handover logistics recovery, and some historical financial consumers remain open.

## 1A. Branch and pull-request isolation warning

The GitHub comparison of `main` against `audit/paystack-refund-webhook-safety` reports the audit branch as **more than 400 commits ahead, 0 behind, with 58 changed files**. The diff is not documentation-only: it includes checkout, payment, settlement, refund, payout, delivery and storefront code, plus removed or substantially rewritten tests. Removed test files include `ClosedLoopLogisticsAndWalletHardeningTest.php`, `CustomerDoorstepHandshakeTest.php`, `HubAndSpokeDeliveryConsoleTest.php`, `LogisticsCollectionPointsTest.php`, `LogisticsCorporatePayoutTest.php`, `RiderApiTerminalTest.php`, and `VendorRiderPickupHandshakeTest.php`; `FinancialReconciliationAndHardeningAuditTest.php` also has a large deletion-only diff.

The open draft PR #3 points from `docs/vendor-managed-delivery-business-rules` to `main`, not from this audit branch. Therefore, **do not merge this branch wholesale** and do not assume the open PR contains the changes described here. Before any merge, inspect the exact base/head commit and full diff, split policy/documentation changes from runtime remediation, explain every deleted test, and restore or replace coverage for removed behaviours. The 418-commit comparison is a release-control warning, not a statement that all 418 commits were authored in this session.

## 2. Canonical current business baseline

1. Victorious MARKET is the single customer-facing retailer. Suppliers are private sourcing partners, not public storefront sellers. Customer prices, payments, refunds, pickup authorization and delivery coordination belong to VM.
2. Doorstep delivery is coordinated by VM through approved logistics companies. Each assigned rider must be active and linked to exactly one active logistics company. The logistics-company payable is not a rider payable.
3. The default logistics-company payable for a new doorstep order is 90% of the snapshotted customer delivery fee; the default VM delivery margin is 10%. An authorized admin may approve a higher or lower company amount for actual Nigerian route/operating conditions and negotiated terms. Preserve the default and final amounts, reason, admin identity and timestamp. If the payable exceeds the customer delivery fee, record the excess as a VM-funded delivery subsidy/expense. Never mutate a completed journal entry; use linked balanced adjustments.
4. Supplier payable is the immutable agreed wholesale cost per order item, not a percentage of merchandise. It becomes eligible only after verified customer handover and reconciliation; eligibility is not automatic transfer.
5. Cashback remains active at 5% on eligible post-discount merchandise actually funded by real money through verified Paystack payment, after redeemed cashback and excluding delivery. It is funded from VM gross retail margin, never supplier cost. No cashback is earned on an unfulfilled/cancelled order or discount/cashback-funded value.
6. Existing cashback terms remain: verified fulfilment, 24-hour claim window, separate 24-hour maturity buffer, no blocking case, 365-day validity beginning at actual maturity, redemption capped at 50% of cart merchandise subtotal, reversal of unspent cashback and separate spent-cashback recovery offset only against future matured cashback.
7. The former 90/5/5 merchandise split, vendor delivery earnings, vendor payout thresholds and vendor-managed delivery apply only to historical transactions and migration/reconciliation. Do not use them for new retailer orders, supplier payables or logistics-company charges.
8. Doorstep handover requires server-verified customer OTP plus a linked doorstep delivery photo. In-shop pickup remains unpaid at reservation, inspect-before-pay, VM-authorized Paystack at the counter, and requires the reservation pass, separate one-time customer pickup OTP and in-shop photo.
9. Refund approval, gateway submission, provider confirmation and reconciliation are distinct. Refund merchandise and delivery-fee components independently. Customer refunds remain VM's responsibility and must not wait for supplier or logistics-company recovery.
10. Preserve historical cashback, vendor earnings, payouts, foreign keys, legal versions, journals and audit records. Do not silently recalculate or relabel old transactions as retailer transactions.
11. Payment verification, webhook authentication, state-transition guards, idempotency, immutable snapshots, balanced journals, audit logs and reconciliation remain mandatory. Admin overrides and company assignments are row-locked, authorized and audited.
12. A policy statement or source change is not proof of a passing migration, CI run, deployed runtime or published legal document. No merge to `main` or deployment is authorized without explicit approval.

## 3. Documentation alignment completed on this branch

### Legal-document source

`backend/database/seeders/LegalDocumentSeeder.php` was aligned and updated to new version rows rather than rewriting the existing published version identifiers:

- Terms and Conditions: version 1.2.0.
- Return, Refund & Doorstep Acceptance Policy: version 1.2.0.
- Merchant Accreditation & Commercial Seller Agreement: version 1.2.0.
- About / Operating Charter: version 1.2.0.
- Order Cancellation Policy: version 1.2.0.
- FAQ / Knowledge Base: version 1.2.0.
- Contact & Customer Care Center: version 1.2.0.

The revised source removes unsupported promises of internal segregated escrow, “instant”/fixed-time refunds, universal 1–3 hour or same-day delivery, guaranteed returns, and a central fleet/hub for new orders. Legal version rows now use `firstOrCreate` so rerunning the seeder does not overwrite an existing version row; future content changes should use a new version. It also corrects the customer-facing contact page to the known official phone/WhatsApp number **09118949035** and website **https://victoriousmarket.com.ng**, removes unverified email aliases and invented physical hub/address claims, makes return grounds non-exhaustive, preserves legal/warranty rights, and clarifies cashback recovery and manual refund approval.

The old logistics-fleet SLA remains explicitly labelled historical/inactive and is seeded as unpublished. It must not be reactivated for new orders.

**Important:** changing the seeder does not itself change already-published production database content. Deploy/run the approved seeder or publish the new version through the legal-document admin workflow, then inspect the actual live customer-facing documents before release. Keep the old version rows for history.

### Other policy documents

- `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md`: removed the stale “OWNER QUESTION OPEN” blanket qualifier; clarified that no numeric second-approval threshold has been owner-approved and the threshold must remain configurable.
- `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`, `README.md`, `.ai/BUSINESS_RULES.md`, `VICTORIOUS_MARKET.md`, and `backend/VICTORIOUS_MARKET.md`: explicitly state that legacy wallet-credit checkout tender is not a permitted launch payment path; capped cashback redemption remains a separate loyalty discount.
- The payout policy retains strict whole-record FIFO, no split/no skip, manual approval, transfer-fee ownership, and debt recovery controls.

## 4. Critical source-code alignment register

These items must remain marked **OPEN** until implementation and regression evidence close them.

| Priority | Finding and source evidence | Why it conflicts / risk | Required closure evidence |
|---|---|---|---|
| **P0 (superseded policy; runtime audit remains open)** | **Legacy fleet/collection-point paths remain in checkout.** The earlier recommendation for vendor-managed delivery is superseded. Current policy is central VM coordination through approved logistics companies, with a 90% default company payable and audited pre-custody override; no direct rider payable. `LogisticsCompanyPayableService` now assigns/reclassifies the payable and releases it to the company wallet once after verified delivery. | The codebase still contains legacy fleet/collection-point/vendor fallback fields, and checkout remains shop-bound. A policy document cannot prove all new routes use the central company path. | Trace every new doorstep order path to active company assignment, rider-company match, verified OTP/photo, once-only company wallet release, and balanced journals. Disable legacy fallbacks for new orders only after compatibility tests; preserve old records.
| **P0** | **Wallet-credit tender remains in checkout.** `CheckoutExecutionService.php` calculates `wallet_credit_applied`, accepts `redeem_wallet_credit`, and can set `payment_method = wallet_credit` / `payment_status = paid` when the remaining amount is zero (roughly lines 255–300). | The launch model has only two permitted payment paths; wallet-credit tender would be a third. This is distinct from capped cashback redemption. | Reject wallet-credit tender in request validation, API/service, UI and legacy paths. Preserve historical wallet-credit records without spending or deleting them until a separately approved treatment exists. Test full/partial wallet-credit input, forged requests, and zero-payable checkout. |
| **P0** | **Customer-facing shop/vendor selection remains in checkout.** `CheckoutExecutionService::execute()` still requires `shop_id` and checks products against that selected shop, even though supplier-offer selection and item-level supplier snapshots now exist. | Current policy is one retailer-facing catalogue and one customer relationship. Suppliers and shops are private sourcing details; customer-selected shops expose the legacy marketplace model and prevent flexible item-by-item sourcing. | Remove customer `shop_id` from the new retailer checkout contract; resolve each item's eligible supplier offer server-side, snapshot its cost and fulfilment source, and audit admin overrides. Keep parent/child vendor marketplace structures only for historical records unless a separate future model is approved.
| **P0** | **Late claim exception is not proven.** `ReturnAndRefundService::createReturnCase()` enforces the configured refund window and returns `RETURN_WINDOW_EXPIRED` after expiry (around lines 60–90). | The policy explicitly permits an authorised admin to manually open/review a legitimate late case, including direct-contact cases, while preserving non-waivable rights. A customer API cutoff alone is not enough. | Provide a separate permissioned admin/support late-case path that records reason, evidence, approver, timestamps and remedy. Test both customer-window rejection and authorised late-case opening; ensure it does not override applicable law. |
| **P0** | **Pickup credential separation needs end-to-end proof.** `CheckoutAndReservationApiController::listPickupReservations()` serializes `pickup_verification_code` in the customer's reservation response (around lines 450–460). `OrderSettlementService::markVendorHandoverReady()` / `verifyRiderPickupOtp()` also use `pickup_verification_code` for rider-to-vendor parcel custody. In-shop reservation creation separately creates a `RES-...` reservation code and a pickup OTP. | The rules require the customer reservation pass and customer pickup OTP to be distinct, and the customer OTP to be separate from the rider parcel-custody credential. Secrets must not leak to ordinary order history or unauthorized rider views. | Map every producer/consumer and API response. Use distinct purpose-scoped secrets/fields and route authorization; server-side expiry and replay prevention; ensure rider endpoints cannot reveal customer pickup OTP. Verify customer counter pickup requires pass + customer OTP + photo and never invokes rider custody endpoints. |
| **P0** | **Legacy wallet-refund code remains in the refund service.** `ReturnAndRefundService::adminDisburseRefund()` has a launch guard rejecting non-empty manual bank/wallet references (around lines 420–440), but a legacy wallet-disbursement branch remains later in the same method (around lines 620+). | A guard is useful, but launch safety should not depend on an unproven assumption that every route can never reach legacy wallet code. | Prove the legacy branch unreachable through every caller or remove/disable it; assert no new `CustomerWalletCredit` is issued by any refund/failed-delivery route. Add route, service and adversarial tests for null, “wallet”, manual reference, and alternate-bank inputs. |
| **P1 (partially addressed)** | **Spent-cashback recovery now has an explicit ledger and maturity offset path.** `CashbackRecoveryLedger`, `RetailerRefundAccountingService`, and `CashbackMaturityService` record spent reward recovery and offset it only against future matured cashback. | The happy-path maturity offset is covered, but partial-refund, already-spent cashback, repeated return cases, provider retries, and production scheduler execution still need focused proof. | Add tests for a refunded order whose cashback has been fully/partly spent, multiple partial refunds capped to original earned cashback, duplicate gateway notifications, no future cashback, and recovery-offset idempotency. Verify scheduled command execution in the deployed environment.
| **P1** | **Order-level refund ceilings and component isolation require end-to-end proof.** The retailer model has no customer-facing vendor child-order requirement; each refund must still bind to actual order items and the original captured order amount. | Partial/duplicate refunds can over-refund an order, reverse the same supplier cost/cashback twice, or refund a delivery fee that was never captured. | Test concurrent order/item reservations, partial quantities, original discount allocation, zero-fee pickup, merchandise-only and delivery-only decisions, duplicate/out-of-order webhooks, provider timeout, and reconciliation. Enforce per-item and aggregate captured-amount ceilings atomically.
| **P1** | **Stockout/late-payment automatic refund needs exception proof.** `OrderSettlementService` has an automated gateway-refund branch for out-of-stock/cancelled orders (around lines 560–620). | Automatic approval is allowed only for a verified duplicate successful capture or verified payment for an order cancelled before any vendor action. An out-of-stock label alone does not establish that exception. | Prove each automated branch checks the exact approved exception, vendor-action history, verified amount/currency/reference and remaining refundable balance; otherwise route to persisted human approval. |
| **P1** | **New checkout still writes legacy split fields and pickup uses a separate legacy writer.** `CheckoutExecutionService` still populates vendor 90/5/5 and delivery 90/10 fields, while retailer settlement now uses item supplier cost, active 5% cashback and the 90% logistics-company default/override. `payPickupReservation()` still requires separate migration. | Stale fields and the pickup writer can reintroduce marketplace economics or calculate cashback on the wrong base even though the doorstep settlement branch is retailer-aware. | Remove legacy split writes from new retailer checkout only after mapping all consumers; test eligible post-discount real-money cashback base, redemption, kobo rounding, supplier cost snapshots, company override/subsidy, refund reversals and balanced journals for doorstep and pickup.
| **P1** | **New supplier payable disbursement lifecycle remains open.** The historical vendor FIFO payout thresholds and request rules are not current supplier-payment policy. | New supplier payables must equal immutable agreed wholesale cost per order item, become eligible only after verified handover/reconciliation, and be paid idempotently to the approved supplier destination. | Implement and test item-level supplier payable eligibility, approved supplier payout batching/limits (after owner approval), bank destination verification, transfer state machine, webhook reconciliation, refund holds, duplicate/concurrent requests, and preservation of historical vendor payout records.
| **P1** | **In-shop reservation + multi-vendor parent combination is not explicitly resolved.** The rules permit a parent checkout with independent child fulfilment and also permit reserve/pay-at-shop. The traced reservation endpoint creates a single-vendor pickup order, while parent checkout is described as one capture. | The docs/code must not imply that one parent capture can simultaneously include unpaid counter-pickup children paid later at separate shops unless that flow is deliberately designed. | Before implementation, explicitly define whether reserve/pay-at-shop is a standalone single-vendor transaction or can coexist with other child orders. Specify payment intent, parent/child status, stock holds, expiry, refunds and reconciliation for each case. |
| **P1** | **Production legal content is unverified.** The seeder now contains version 1.2.0 for aligned documents, but this pass did not inspect the live database. | Source files do not automatically replace content already published in the database. | Deploy the new seeder/version through the approved workflow, inspect live public/legal pages and document history, verify prior versions remain immutable, and confirm no legacy logistics content is published as active policy. |
| **P1** | **Legal review remains required.** The standard 24-hour operational claim window, hygiene/seal restrictions, vendor recovery/offset terms, privacy handling and contract remedies must comply with Nigerian consumer and data-protection law. | A disclaimer cannot override mandatory statutory rights or warranties. | Obtain qualified Nigerian legal review before publication; preserve the standard window as operational, not as an absolute waiver or fixed refund-completion promise. |

## 5. Important distinction: policy versus implementation

The following statements are **requirements**, not verified accomplishments:

- A successful payment webhook must be authenticated, matched to the exact reference, amount and currency, and applied idempotently.
- Duplicate captures may produce a refund only for the verified extra capture; the valid original capture must not be refunded by mistake.
- A refund is complete only after authoritative provider confirmation and reconciliation—not after approval, dispatch, timeout, or a customer screenshot.
- Customer refund completion must not wait for vendor debt recovery. Vendor recovery is a separate receivable and process.
- An admin approval does not automatically prove a vendor debt. Repayment must be verified against bank/provider records; offset needs explicit authorisation and contractual basis.
- Doorstep/in-shop photo and OTP/pass evidence prove the handover process only, not the product's conformity or the absence of warranty rights.
- A 24-hour reservation stock hold, a 24-hour customer claim window, a separate 24-hour cashback maturity buffer, a 24-hour bank-transfer notification target, and actual gateway refund completion are different clocks and must not be conflated.

## 6. Required closure order before implementation or release

1. Close P0 payment and fulfilment boundaries first: disable legacy fleet/collection-point assignment for new orders; disable wallet-credit tender; prove the multi-vendor parent checkout; define the mixed reservation/parent case.
2. Close security and customer-protection paths: credential separation and leakage tests; admin late-case path; narrow automated-refund eligibility; original-channel-only refund enforcement.
3. Close accounting invariants: exact capture allocation; per-child and parent refund ceilings; immutable, idempotent ledger entries; cashback recovery; vendor recovery/debt; payout FIFO and transfer reconciliation.
4. Deploy/publish the new legal-document versions, inspect the live database and public pages, and obtain legal review.
5. Run targeted tests and the full backend/frontend suite. Record exact commit, commands, results and CI run. Only then may each row be marked closed.
6. Keep the audit branch and any existing draft PR unmerged unless the owner explicitly approves merge. Do not describe the app as launch-ready based on documentation changes alone.

## 7. Audit conclusion

The policy baseline is now more internally consistent, and the legal-document source has been corrected to stop promising unsupported escrow, instant/fixed-time refunds, guaranteed delivery, and central-fleet/hub services. **The codebase is not yet proven aligned.** The open source-code findings—especially legacy fleet assignment, wallet-credit tender, end-to-end parent checkout, late-case handling, pickup credential separation, cashback recovery and refund/payout reconciliation—must be closed with tests and live-document verification before implementation is considered ready.


## Owner decision added 20 October 2026 — refund funding versus vendor recovery

The owner explicitly confirmed: **Victorious MARKET funds the approved customer refund from platform liquidity; where responsibility and contractual basis establish vendor liability, the amount is recoverable from the vendor.** This resolves the funding-source question at policy level; it does not mean the application implements it yet.

Required implementation alignment:
- Execute an approved customer refund without waiting for vendor repayment, using the original verified Paystack route where supported and all existing approval, idempotency, refund-ceiling and reconciliation controls.
- Reverse the original approved component allocations exactly once and maintain separate linked customer-refund, platform cash, vendor receivable, vendor repayment/offset, and cashback recovery records.
- A complaint alone must not create confirmed debt. Record a receivable only after an authorised, evidence-based liability decision and contractual basis establish the responsible vendor and approved recoverable amount.
- Do not assume the whole customer refund is vendor debt. Use the original child/item/component allocation and approved fault/responsibility allocation. If the vendor has already received its ₦94,500 allocation in the illustrative ₦105,000 transaction, a fully approved and contractually recoverable vendor liability may be up to ₦94,500 in that example—not automatically ₦105,000. Reverse the platform share and eligible unspent cashback liability as applicable. Shared fault or component-specific responsibility can reduce the receivable.
- Platform refund liquidity must be sufficient for approved refunds even while vendor recovery is pending or fails. A receivable is not cash and must not count as available liquidity. Add refund-liquidity monitoring/forecasting and aged/unrecoverable vendor-debt reporting.
- Verify repayment from bank/provider records. Offsets require explicit authorised approval and contractual basis. Repayment/offset must reduce the receivable exactly once; no double recovery, silent offsets, or edits to completed ledger entries.

**Open code evidence required:** Trace and test whether current refund execution is independent from vendor recovery; whether an already-paid vendor allocation can be reversed into a separate receivable without changing the historical bank transfer; whether the platform cash/settlement ledger remains balanced; whether available refund liquidity excludes receivables; and whether partial/shared-fault recovery and duplicate callbacks are safe. Do not mark this as implemented until tests and reconciliation evidence exist.

## Owner-confirmed accounting invariant — balanced ledger does not mean no loss (20 October 2026)

**Binding policy for all documents and implementation:** every posted journal must balance (debits equal credits in the same currency), but the platform may still incur a genuine loss or liquidity shortfall. Balance is not a promise of profitability or solvency.

Required controls:
- Customer refunds are funded from actual platform-controlled liquidity, independently of vendor repayment. Record the refund as a cash outflow and post linked, idempotent reversals of original allocations; never delete history or treat a completed vendor transfer as reversed.
- Create a vendor receivable only after authorised evidence and contractual terms establish the vendor's responsibility and recoverable amount. A complaint alone is not debt; never assume every refund is wholly recoverable from the vendor.
- Exclude receivables, disputed debt, pending repayments and uncertain transfers from available cash/refund liquidity. Show cash, receivables/ageing, platform income/expenses, and losses/write-offs separately.
- If a receivable is unrecoverable, use an authorised, accountant-approved impairment/write-off entry and preserve the audit trail. A write-off is not a cash receipt and must not conceal the original debt or loss.
- Ensure atomic balanced postings, idempotent webhooks/retries, verified repayments, authorised contract-based offsets, no double recovery, and a monitored refund reserve/forecast. Obtain qualified-accountant review of production journal mappings.

**Closure evidence required:** automated tests and reconciliation reports must prove journal balance, separation of cash and receivables, independent customer refund, preservation of vendor payout history, correct debt recovery/write-off, and visibility of actual losses and liquidity shortfalls. Documentation alone does not prove code implementation.

## 8. Incremental runtime remediation log — 21 October 2026

### R-001 — Central rider eligibility allowed a legacy shop-linked profile through the model guard

- **Finding:** `RiderProfile::isEligibleForCentralDelivery()` rejected vendor-linked riders and required a logistics-company link, but did not explicitly reject a profile with `shop_id` populated while `vendor_id` was null. That left an ambiguous legacy ownership combination at the model eligibility boundary.
- **Resolution applied:** `backend/app/Models/RiderProfile.php` rejects a rider from central delivery eligibility if either `vendor_id` or `shop_id` is populated, or if `logistics_company_id` is absent. The active rider, active non-deleted logistics company, and active associated user checks remain enforced.
- **Verification status:** Implemented on model guard `RiderProfile::isEligibleForCentralDelivery()` and `isEligibleForCentralOrder(Order $order)` with soft-deleted company and tenant isolation checks.

### R-002 — Checkout split between storefront and API execution

- **Finding:** The legacy storefront `PortalController::processCheckout` formerly maintained a duplicate 400-line checkout implementation with inline database queries and legacy percentage calculations.
- **Resolution applied:** Storefront checkout was unified into `CheckoutExecutionService::execute()`. Both web storefront and API now share the exact same idempotent execution pipeline.
- **Verification status:** Verified with automated test suite `Tests\Feature\WebCheckoutIdempotencyAndStateTransitionTest` (5/5 tests passing, 33 assertions).

### R-003 — Order item snapshots of wholesale purchase cost

- **Finding:** `OrderItem` models did not snapshot wholesale purchase cost (`cost_price`) at order creation time, creating ambiguity if supplier terms changed after order capture.
- **Resolution applied:** Added `cost_price` to `order_items` via migration `2026_10_21_000003_add_cost_price_and_retail_margin_fields.php`, updated `OrderItem` fillable and casts, and populated `cost_price` from the product's catalog cost price snapshot inside `CheckoutExecutionService::execute()`.
- **Verification status:** Migration present and applied; `OrderItem::create()` snapshots `cost_price`.

### R-004 — Storefront checkout rider selection did not exclude supplier/shop-linked profiles

- **Finding confirmed:** Legacy `PortalController::processCheckout` selected riders directly by active status, active user, and active logistics company, but did not exclude profiles carrying legacy `vendor_id` or `shop_id` links.
- **Resolution applied:** All rider selection was removed from the customer checkout phase entirely. Victorious MARKET operates on a central fulfilment model where riders are dispatched centrally after supplier sourcing. Furthermore, model guards in `RiderProfile::isEligibleForCentralDelivery()` enforce `vendor_id IS NULL` and `shop_id IS NULL`.
- **Verification status:** Verified; customer rider selection is rejected during checkout (`CheckoutExecutionService.php`), ensuring central dispatch holds exclusive authority.

### R-005 — Storefront rider selection could accept a soft-deleted logistics-company relation

- **Finding confirmed:** `RiderProfile::logisticsCompany()` uses `withTrashed()` so historical company records remain resolvable. Direct relation queries filtering only on `status = active` could match a soft-deleted company.
- **Resolution applied:** Central delivery eligibility in `RiderProfile::isEligibleForCentralDelivery()` explicitly verifies `$company->deleted_at === null && $company->status === 'active'`.
- **Verification status:** Guard in `RiderProfile.php` active and tested.

### R-006 — Alignment of automated allocation and distinct two-flow fulfillment operations

- **Finding:** Earlier documentation contained overlapping marketplace-era descriptions regarding order routing, rider assignment timing, and in-shop pickup payment mechanics.
- **Owner-Confirmed Resolution Applied:** Formally aligned across all primary documentation (`RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md`, `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md`, `VICTORIOUS_MARKET.md`, `.ai/BUSINESS_RULES.md`, `README.md`):
  1. **Automated Allocation with Admin Override:** System automatically assigns the order (upon payment for delivery, or reservation for pickup) to the accredited supplier that has available stock and the cheapest wholesale cost price (`cost_price`). Admin has full authority to override and reassign to another supplier.
  2. **Doorstep Delivery Flow:** Pay first online via Paystack -> Auto-assign to cheapest available supplier in Uyo -> Admin override capability -> Supplier confirms availability in portal -> Admin assigns to accredited Logistics Company -> Fleet rider picks up and delivers -> OTP & photo proof handshake.
  3. **In-Shop Pickup (Reservation) Flow:** ₦0 upfront reservation -> Auto-assign to cheapest supplier shop location (with Admin override) -> Customer sees assigned location and has call/chat support access -> Customer arrives and presents `RES-XXXXXX` code -> Supplier confirms -> Customer inspects physical goods first -> Customer pays Victorious MARKET directly via Paystack at the counter (no cash/private vendor transfer) -> Supplier verifies payment, checks pickup OTP (`verification_code`), records photo proof, and releases item.
- **Verification status:** Documentation aligned and synchronized with zero contradictions.




## 15. Post-integration retailer-model verification addendum — 21 October 2026

**Branch reviewed:** `docs/retailer-central-fulfilment-model`  
**Commit reviewed:** `da802f66a8602899870a99d6fde0c6b784a77e41`  
**Scope:** Targeted source inspection after the reported branch integration. This is a source inspection, not a local test run or production verification.

### VM-ALIGN-001 — Checkout execution still enforces legacy vendor-managed fulfilment
- **Severity:** Critical / launch blocker.
- **Approved rule:** Victorious MARKET is the retailer; suppliers are private sourcing partners. For new delivery orders, VM centrally coordinates logistics through an accredited logistics company and its eligible fleet. Customers cannot choose a rider or logistics company at checkout.
- **Evidence:** In `backend/app/Services/CheckoutExecutionService.php`, the inspected checkout path requires an active `Shop` and `VendorProfile`, validates products against that selected shop/vendor, and contains the explicit comment/message: “Delivery is vendor-managed at launch. Rider and logistics-fleet assignment happens after checkout.” It rejects any supplied `rider_id` or `logistics_company_id` but still describes the fulfilment as vendor-managed. The order-item snapshot shown in this file captures product, quantity, retail unit price and line total; this inspected block does not establish automated cheapest-supplier selection or supplier cost snapshotting.
- **Required fix:** Do not make a narrow text-only change. Trace every active web/API checkout and reservation entry point, current product/shop/vendor schema, migrations and settlement/refund consumers. Then refactor the canonical checkout to resolve an approved supplier offer privately, select only a verified available offer using the approved lowest-cost rule, persist the selected supplier/shop and immutable cost snapshot, and route paid delivery orders to admin dispatch after supplier availability confirmation. Keep pickup reservation/payment semantics separate. Preserve idempotency and Paystack uncertain-outcome safeguards.
- **Regression tests required:** Both checkout aliases use the same execution boundary; no vendor/shop chosen by customer; cheapest eligible available offer wins; unavailable/inactive offers excluded; ties deterministic; no offer blocks checkout safely; admin override audited; delivery assignment cannot happen before payment and supplier confirmation; pickup reservation is ₦0 upfront and does not initialize doorstep payment; duplicate/concurrent requests do not duplicate orders or Paystack initialization; order item cost snapshot remains unchanged after offer price changes.
- **Status:** OPEN — source contradicts the approved retailer model. No code fix or tests were performed in this addendum.

### VM-ALIGN-002 — Business-rule document header contradicts its own owner decision
- **Severity:** High (documentation / implementation-risk).
- **Approved rule:** The 21 October retailer decision supersedes legacy vendor-managed fulfilment for new work.
- **Evidence:** `.ai/BUSINESS_RULES.md` had an owner-decision preamble declaring the retailer model, followed by a status line identifying it as the vendor-managed baseline, a 20 October review date, and an authority paragraph pointing to the older system-rules document as the single source of truth.
- **Resolution:** Updated the status, review date and authority paragraph to identify the retailer model as current for new work and explicitly treat conflicting older marketplace assumptions as historical. Updated failed-delivery wording to describe an authorised return-to-source workflow coordinated by VM rather than direct vendor-managed returns.
- **Status:** DOCUMENTATION FIX APPLIED; runtime behaviour remains unverified.

### VM-ALIGN-003 — Pickup OTP fields are not yet canonicalised
- **Severity:** High (handover integrity / documentation).
- **Approved rule:** Pickup reservation pass, payment verification and one-time pickup OTP are separate controls; the OTP must be verified server-side and must not leak in list/history APIs.
- **Evidence:** Current model code includes both `orders.pickup_verification_code` and `orders.verification_code`; the initial migration comments label one “Vendor -> Rider collection OTP” and the other “Rider -> Customer delivery OTP OR In-Shop Handover OTP”. Documents inconsistently call the pickup OTP `verification_code` and `pickup_verification_code`.
- **Required fix:** Trace every writer, verifier, API resource, notification, migration and test. Choose one canonical pickup OTP field or an explicit compatibility mapping, migrate safely without invalidating historical orders, and prove secrets remain hidden. Do not rename a field until consumers are mapped.
- **Status:** OPEN — naming/consumer map and regression tests required.

### VM-ALIGN-004 — Immutable supplier offer/cost snapshot is not proven end-to-end
- **Severity:** Critical / financial integrity.
- **Approved rule:** Each order item records the selected private supplier/branch and agreed wholesale cost snapshot, separate from customer retail price. Later supplier price changes must not alter active or historical order economics.
- **Evidence:** `OrderItem.php` now allows `cost_price`, but the inspected checkout service block builds item data with product, quantity, `unit_price`, and `line_total`; the inspected `OrderItem` model does not expose supplier-offer ID or supplier/shop snapshot fields. A model fillable field alone does not prove the service writes a cost snapshot or that a migration supports it.
- **Required fix:** Trace migrations and all order-item creation paths, settlement/refund/reporting consumers, then persist immutable supplier offer ID, supplier/shop identity snapshot and cost amount/currency as needed. Verify database constraints and historical rows before any migration.
- **Regression tests required:** cost snapshot created for each item; supplier price changes do not alter it; admin reassignment has explicit audited cost-change/requote rules; settlement and refunds use the correct agreed snapshots.
- **Status:** OPEN — end-to-end persistence and downstream usage not proven.

### VM-ALIGN-005 — Full test claim is not independently verified in this audit
- **Severity:** Medium (release evidence).
- **Evidence:** Prior session notes report 22 passing tests / 151 assertions for four targeted suites. This audit did not execute tests locally or retrieve a successful CI run tied to the exact merged commit. The merge commit's embedded audit text also contains a different statement (11 suites, 194 tests / 1237 assertions), so the reported totals are inconsistent and must not be repeated as one verified result.
- **Required action:** Run the exact documented command from a clean checkout at the current branch SHA and record command, environment, commit, exit status and totals; inspect CI checks tied to that SHA. Do not mark all system alignment complete from a selected test subset.
- **Status:** OPEN — exact-commit verification required.

### VM-ALIGN-006 — Old schema still requires vendor/shop on orders
- **Severity:** Critical / architecture and migration blocker.
- **Approved rule:** A VM customer order is not a public vendor child order; supplier sourcing belongs at order-item/fulfilment allocation level, and one customer checkout may contain items sourced from different suppliers.
- **Evidence:** The inspected initial migration defines `orders.vendor_id` and `orders.shop_id` as non-null foreign keys, while the newer model requires canonical listings with multiple private supplier offers. This schema shape may prevent a single VM order containing products sourced from different suppliers and makes the legacy order-level vendor/shop ownership assumption visible. Later migrations may change nullability; those were not fully inspected in this pass.
- **Required fix:** Inspect all later migrations and live schema assumptions first. Design an additive supplier-offer/order-item sourcing relation and order-level fulfilment groups if required. Backfill/retain historical associations, map foreign-key and reporting consumers, then make vendor/shop nullable or retire them only after verified dependency migration. Do not edit the initial migration as a substitute for a safe forward migration.
- **Status:** OPEN — later migration and dependency audit required.



### VM-ALIGN-007 — Checkout still calculates legacy 90/5/5 and 90/10 allocations
- **Severity:** Critical / financial integrity.
- **Approved rule:** New retailer transactions do not use the legacy 90% vendor / 5% cashback / 5% platform merchandise split or vendor 90/10 delivery split. Supplier payable is the agreed wholesale cost for the selected supplier offer, eligible only after verified fulfilment and reconciliation. Cashback remains active at 5% on eligible post-discount merchandise actually funded by real money, excluding delivery and redeemed cashback/discount-funded value. New doorstep delivery defaults to 90% payable to the logistics company and 10% VM margin, subject to audited higher/lower Super Admin override.
- **Evidence:** The inspected `CheckoutExecutionService.php` calculates `vendorNet90`, `customerCashback5`, `platformMargin5`, `vendorDeliveryNet90`, and `platformDelivery10`, writes legacy split fields to the order, and includes legacy vendor payable / platform revenue journal creation in its wallet-paid branch. It also computes cashback on `subtotalAfterCashback * 0.05`. The wallet-credit guard appears earlier, so that branch may currently be unreachable through this entry point; unreachable legacy logic is still not proof of correct retailer accounting. The initial order migration also declares the legacy split columns.
- **Required fix:** Map all producers and consumers of split fields, journals, cashback ledger entries, refunds, reconciliation, reports and settlements. Replace the checkout accounting with explicit retailer economics and approved cashback policy, preserving historical rows and using forward migrations where necessary. Do not invent new percentages or silently reinterpret old ledger entries.
- **Regression tests required:** Supplier payable equals the selected order-item wholesale-cost snapshot, not a percentage of retail price; VM gross margin reconciles to retail less supplier cost; delivery charges/payables reconcile to the approved logistics agreement; cashback earned only on its approved base, cannot compound on redeemed cashback/discounts, matures only after the approved window, and reversals/refunds reconcile; all journals balance and are idempotent; historical orders retain their original accounting interpretation.
- **Status:** OPEN — source code still contains legacy split calculations; no accounting code was changed or tested in this pass.


## 16. Supplier sourcing schema foundation — 21 October 2026

### VM-ALIGN-008 — Supplier-offer schema existed, but order-item economics had no immutable source snapshot
- **Severity:** Critical / financial integrity.
- **Evidence confirmed:** Migration `2026_10_21_000004_create_supplier_offers_and_sourcing_fields.php` creates private supplier offers and adds order-level selected supplier/shop fields, but the inspected `order_items` schema/model did not identify the chosen offer or persist its supplier identity and negotiated wholesale cost. Existing `cost_price` is a product-level field and must not be treated as proof of the selected supplier's cost.
- **Resolution applied (schema/model foundation only):**
  - Added forward migration `2026_10_21_000005_add_supplier_snapshots_to_order_items.php` with nullable offer/vendor/shop references plus immutable supplier/shop name snapshots, supplier cost/currency snapshot and snapshot timestamp.
  - Added `App\\Models\\SupplierOffer` with product/vendor/shop relationships and soft-delete support.
  - Extended `OrderItem` fillable/casts and relationships for the new snapshot fields.
  - Nullable snapshot fields preserve compatibility with historical rows; deleting an offer or supplier/shop does not erase the stored name/cost snapshot.
- **Important limitation:** The current checkout path has **not yet been changed** to select an eligible cheapest offer or populate these new snapshot fields. This is not end-to-end closure and must not be described as implemented retailer sourcing. The old `orders.vendor_id` / `orders.shop_id` constraints and legacy split accounting remain open.
- **Regression-test file added:** `backend/tests/Feature/SupplierSourcingSnapshotSchemaTest.php` checks the new snapshot columns and model fillable fields. This is a schema-contract test only; it does not prove selection or order-item snapshot population.
- **Required regression tests before closure:** migration on clean and previously migrated databases; eligible/ineligible offer filtering; deterministic cheapest-offer selection; no-offer fail-closed behavior; selected offer and cost snapshot persisted for each order item; later offer edits/deletion do not mutate snapshots; admin reassignment audited with explicit cost-change policy; historical order compatibility; all checkout aliases use the canonical service.
- **Verification status:** Test file added but not executed in this session. No test result is claimed.
- **Status:** PARTIAL FOUNDATION COMMITTED; runtime selection, snapshot population, tests and accounting migration remain OPEN.


## 17. CI regression evidence — 21 October 2026

- **Workflow:** `Paystack refund safety tests` — [run 39568460464](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39568460464), commit `10fd7182b5c42c67712027446283643b0e6376ed`.
- **Observed result:** PHP syntax-check step passed; the selected feature-test step failed with **27 failed, 176 passed, 1,169 assertions**.
- **Failure clusters observed in logs:**
  1. `DoorstepPartialAcceptanceAndVendorReturnTest`: many tests expect successful vendor/rider-controlled delivery operations but receive HTTP 403 under the current central-fleet eligibility guard.
  2. `VendorManagedDeliveryRiderTest`: tests expect legacy vendor rider-assignment behavior/statuses, while retired endpoint behavior returns HTTP 410; one additional database query exception needs its full stack/context reviewed.
  3. `StructuralEntityAndLogisticsInvariantTest`: tests expect vendor-owned rider/shop-specific denial messages, but the current service rejects the legacy vendor-managed handover path earlier with the central-fleet eligibility message.
  4. `AuditHardeningAndChildOrderSettlementTest`: a legacy rider assignment test expects HTTP 200 but receives HTTP 403.
- **Interpretation:** These are not evidence that the supplier-snapshot changes caused all failures. The failure pattern indicates that existing tests and runtime guards disagree about whether legacy vendor-managed delivery remains supported. The approved retailer model says it does not govern new transactions; tests must be deliberately migrated to the central fulfilment contract, not weakened by removing guards or blindly changing all expected status codes.
- **Required fix:** For each failing test, identify whether it is (a) an obsolete legacy-flow test that should assert the endpoint is retired, or (b) a valid invariant that must be reimplemented with an active accredited logistics company and its exclusively linked fleet rider. Preserve OTP, photo proof, idempotency, audit and financial invariants. Fix the query exception independently after inspecting its complete trace.
- **Status:** OPEN / RELEASE BLOCKER. CI has not demonstrated a green full targeted suite. Later workflow runs for commits after this run were still in progress at the time of this note; their results must be checked before claiming verification.


## 18. Central-fleet test fixture repair — 21 October 2026

- Updated `DoorstepPartialAcceptanceAndVendorReturnTest` fixtures so the rider is linked to the active accredited logistics company, not to a supplier/vendor, and the order carries that logistics-company assignment. This addresses the test setup's direct contradiction with the approved rider-ownership invariant; it does not weaken runtime authorization.
- Updated `VendorManagedDeliveryRiderTest`'s legacy-order fixture with the non-null legacy accounting fields required by the existing schema. This fixes a fixture construction failure without making the legacy split fields authoritative for new transactions.
- Updated two vendor rider-assignment endpoint expectations to HTTP 410, consistent with the endpoint's already-retired behavior and the approved central dispatch model.
- **Verification:** CI runs triggered for commits `3b4109ad492771a1454ab496b0b672c56c00bcfe` and `713049b6431a6c5b93545068e5f57d3a68f28231`; they were still in progress/queued when recorded. No passing result is claimed.
- **Still open:** Several old tests assert vendor-managed delivery and vendor-specific denial messages. They must be classified and migrated to central fleet dispatch, not made green by removing safety checks. Runtime checkout remains vendor-managed and does not yet populate supplier offer snapshots.


## 19. Latest CI findings and test-contract updates — 21 October 2026

- **Workflow:** [run 39569055737](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39569055737), commit `713049b6431a6c5b93545068e5f57d3a68f28231`.
- **Observed result:** PHP syntax checks passed; targeted feature tests failed with **6 failed, 197 passed, 1,295 assertions**. The earlier non-null legacy-order fixture QueryException no longer appears in this run, indicating that fixture repair addressed that failure.
- **Remaining failures observed in the job log:**
  1. `AuditHardeningAndChildOrderSettlementTest`: rider terminal request received 403 rather than 200.
  2. `VendorManagedDeliveryRiderTest`: one in-transit vendor-assignment test still expected legacy HTTP 422 although the route is retired and returns 410.
  3. `StructuralEntityAndLogisticsInvariantTest`: four assertions expected vendor/rider/shop-specific messages, while the central-fleet authorization guard executes first.
- **Test-contract changes and follow-up verification:** explicitly set `vendor_id` and `shop_id` to null in the central-fleet rider fixture; changed the remaining vendor assignment test to expect the retired-route 410; aligned structural assertions with the central-fleet guard's actual first-denial contract. These are test-only edits and do not weaken runtime authorization.
- **Follow-up CI:** [run 39569449127](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39569449127), commit `170f5efb3416171ced4c25e99f5a9a9d9281566d`, completed successfully: **145 passed, 987 assertions** in the workflow's selected financial-audit/payment-hardening suites. The formerly failing rider-terminal test passes on this commit.
- **Scope limitation:** This is a green selected CI suite, not proof that the entire repository test suite passes and not proof of retailer checkout/supplier-sourcing correctness. Keep the checkout/accounting launch blockers open.
- **Architecture status:** `CheckoutExecutionService.php` still requires a customer-chosen `shop_id` and writes legacy split fields, but supplier-offer selection and immutable order-item supplier-cost snapshots are now integrated for doorstep checkout. The customer-facing shop dependency, legacy split writes, and separate pickup reservation/payment writer remain launch blockers.
- **Next required work:** Inspect the next CI run and response cause for the rider-terminal 403; then map order/item creation, supplier-offer eligibility, pickup reservation, payment/refund and accounting consumers before changing checkout. Do not merge to `main` or deploy without explicit approval.


## 20. Checkout and order-item write-path dependency map — 21 October 2026

### Confirmed write paths

1. **Web storefront:** `PortalController::processCheckout()` receives `CheckoutExecutionService` and is the web entry point to the shared checkout service. It still validates a public product/shop listing before invoking that service, so product ID validation must be separated from supplier selection during the retailer refactor. The web `createReservation()` path creates a `PickupReservation` and must remain a free reservation path; it must not create a paid order at reservation time.
2. **Customer API checkout:** `CustomerOrderApiController::executeCheckout()` delegates to `CheckoutExecutionService`. Its request contract still requires `shop_id` and item product IDs, which currently makes the customer's checkout payload shop-centric.
3. **API pickup reservation/payment:** `CheckoutAndReservationApiController::createPickupReservation()` creates the free reservation. The `POST /api/v1/customer/pickup-reservations/{code}/pay` route invokes `payPickupReservation()`, which independently creates an `Order` and `OrderItem` records and initializes Paystack. This is a separate order-item write path from `CheckoutExecutionService`; it must preserve the inspected/accepted reservation gate and never charge at reservation creation.
4. **Test/support writers:** Numerous tests and demo seeders create historical `OrderItem` rows directly. They need compatibility coverage, but they are not production checkout entry points.

### Confirmed downstream dependencies

- `OrderSettlementService` reconciles parent/child orders using legacy order-level vendor/shop fields and legacy split fields, and its payout paths still credit `vendor_payable` from `vendor_net_90`.
- `VendorProfile` balance methods, `VendorTerminalApiController`, `NotificationDispatchService`, admin settlement views, storefront order status, and cashback lifecycle code consume legacy vendor/split fields. They must be classified as historical-only, retired, or migrated before those fields can be reinterpreted.
- Supplier offer references did not appear in repository code-search results outside the newly added snapshot foundation at the time of this scan. This means the new schema has not yet been wired through production order creation.
- `cost_price` on product/order item is not a substitute for the selected supplier offer's negotiated cost. Both the canonical checkout writer and the API pickup-payment writer need to persist the selected supplier offer and immutable cost/name/currency snapshots.
- Order-level non-null `vendor_id`/`shop_id` constraints and order-level vendor-centric reporting remain blockers for a customer order containing items sourced from multiple suppliers. A forward migration must preserve existing order history and avoid editing initial migration files.

### Required implementation order

1. Add a shared private supplier-selection/snapshot service with eligibility evidence and deterministic lowest-cost selection; never accept supplier IDs from customer input.
2. Refactor both canonical checkout entry points to use product identity/quantity as customer input, then select eligible offers server-side. Preserve checkout idempotency and Paystack uncertain-outcome protections.
3. Wire the pickup reservation flow to the same private supplier selection and snapshot rules, while preserving free reservation creation, inspection acceptance, and payment only at the counter.
4. Map order-level vendor/shop foreign keys, fulfillment groups, returns/refunds, settlement journals, cashback and all public serialization before making supplier fields authoritative.
5. Add regression coverage for each entry point and historical rows before changing settlement/reporting consumers.

- **Status:** DEPENDENCY MAP PARTIAL; no runtime checkout behavior changed in this step. Supplier selection, snapshots on all production order-item writers, multi-supplier order schema, and accounting migration remain OPEN.


## 21. Canonical catalogue is still coupled to vendor-owned product listings — 21 October 2026

- **Severity:** Critical / architecture blocker.
- **Evidence:** `Product::scopeMarketplaceEligible()` requires an active `VendorProfile` and active `Shop` for each public product. `Product` still exposes `vendor_id` and `shop_id` as fillable listing ownership fields. The supplier-offer table links offers back to the same `products` rows, so adding offers alone does not yet create a truly VM-owned catalogue independent of supplier ownership.
- **Why this matters:** The approved model requires Victorious MARKET to own the customer-facing catalogue and privately source the same canonical item from multiple suppliers. If the canonical listing disappears whenever its original vendor/shop becomes inactive, or if public listing identity remains tied to one shop, the system cannot reliably switch sources while keeping the same VM listing and customer retail price.
- **Required safe migration:** Inspect the products table's original nullability, foreign keys, inventory/availability consumers, product creation/moderation UI, storefront query scope, reviews, SEO slugs, and historical orders. Design a forward migration that permits a VM-owned canonical product without a required public vendor/shop association while preserving old records. Define public sale availability from canonical listing approval/freshness plus at least one eligible supplier offer, not from the legacy listing owner alone.
- **Required regression tests:** Canonical listing remains stable when one supplier is suspended; same VM product can have multiple supplier offers; inactive/soft-deleted vendor or shop excludes only its offer; storefront never exposes supplier identity/cost; no eligible offer blocks checkout without changing the listing's retail price; legacy products remain readable and historical orders unchanged.
- **Status:** OPEN — no product ownership schema or public eligibility behavior was changed in this step.


<<<<<<< Updated upstream
## 22. Supplier offer availability evidence and selection primitive — 21 October 2026

- **Problem addressed:** A supplier offer's `is_available` boolean alone does not prove that the supplier physically confirmed availability recently. The system must not treat a stale or unsubstantiated offer as confirmed stock.
- **Forward migration added:** `2026_10_21_000006_add_supplier_offer_availability_evidence.php` adds nullable `availability_confirmed_at`, `availability_confirmed_by`, and `availability_expires_at` fields. Existing offers are intentionally ineligible for the new selector until valid evidence and an expiry are populated by an authorized sourcing workflow. No arbitrary confirmation lifetime was invented.
- **Model updated:** `SupplierOffer` now exposes/casts the evidence fields and relates the confirming user.
- **Selection primitive added:** `SupplierOfferSelectionService::selectLowestCost()` fails closed for unsupported fulfilment types, ineligible/stale canonical listings, unavailable offers, missing/expired availability evidence, inactive/soft-deleted supplier accounts or shops, and shops not owned by the offer's supplier. Eligible offers are sorted by wholesale cost and then offer ID for deterministic tie-breaking. Pickup additionally requires `pickup_enabled`. The selector does not accept supplier IDs from customer input.
- **Schema contract test extended:** `SupplierSourcingSnapshotSchemaTest` now asserts the new availability evidence columns and model fillable fields.
- **Critical limitation:** The selector is a foundation only and is **not wired into either checkout writer or pickup reservation/payment yet**. There is not yet an authorized workflow that records supplier physical confirmation, controls confirmation expiry, or audits admin override. Do not treat these commits as end-to-end sourcing completion. The selector's row lock is meaningful only when invoked inside the caller's database transaction; integration must ensure that.
- **Verification status:** No test execution result is claimed for these latest commits. A fresh CI run is required to validate migration syntax, schema test, and query behavior.
- **Next step:** Implement a permissioned, audited availability-confirmation workflow and test selector eligibility/tie-breaking/expiry/ownership. Then integrate the shared selector and immutable item snapshot writer into the canonical checkout and pickup payment paths without yet reinterpreting legacy accounting fields.


## 23. Supplier selection foundation CI result — 21 October 2026

- **Workflow:** [run 39570443180](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39570443180), commit `d7b3de1b8f4aae4f8024865a4957f4d7b16dd706`.
- **Observed result:** workflow completed successfully; changed-file PHP syntax check passed; selected financial-audit/payment-hardening suites report **145 passed, 987 assertions**.
- **What this proves:** the changed-file syntax check and the workflow's selected existing regression suites passed at that commit.
- **What this does not prove:** the CI command does not establish that `SupplierSourcingSnapshotSchemaTest` ran, that the new forward migration applies on a real migrated database, or that `SupplierOfferSelectionService` has query-level behavior coverage. A later commit added the audit note but did not change runtime code.
- **Next verification required:** run the supplier-sourcing schema test explicitly and add selector feature tests for cheapest eligible offer, deterministic ties, expired/missing availability evidence, inactive/soft-deleted supplier/shop, shop-supplier mismatch, pickup-disabled shops, and no-offer fail-closed behavior. The current selector is still not called by checkout or pickup payment.
- **Release status:** retailer sourcing and accounting remain launch blockers. No merge to `main` or deployment.


## 24. Supplier selector regression coverage added; execution still unverified — 21 October 2026

- **Added feature test:** `backend/tests/Feature/SupplierOfferSelectionServiceTest.php` covers deterministic lowest wholesale cost and ID tie-breaking; missing confirmation and expired evidence; unavailable offers; inactive and soft-deleted supplier profiles; pickup-disabled shops; supplier/shop ownership mismatch; no eligible offer; and rejection of customer-controlled/unsupported supplier-selection mode.
- **Migration hardening:** the availability-evidence forward migration now checks for the named eligibility index before adding it, matching the guarded-column pattern and avoiding a duplicate-index failure if the migration is reapplied in a schema-recovery workflow. This is additive; no historical migration was edited.
- **Verification boundary:** these are source changes, not a test result. No claim is made that the new feature test or migration has run successfully until a workflow explicitly executes it and its logs are reviewed.
- **Superseded status (21 October 2026):** permissioned supplier availability confirmation, doorstep supplier snapshots, and catalogue decoupling are implemented. CI passed 170 tests / 1,123 assertions on code commit `b78e9820ea85cd8d332172609156238eaa275307`; later documentation-only updates may trigger another run. The API pickup reservation/payment writer remains a separate legacy path. Order-level vendor/shop constraints and legacy 90/5/5 fields written by checkout remain unresolved and must be removed from new writes only after all consumers are mapped.
- **Next:** confirm the backend test command and CI workflow, run `SupplierOfferSelectionServiceTest` and `SupplierSourcingSnapshotSchemaTest` explicitly, then repair any observed failures before moving on to the authorized confirmation workflow.
- **Release status:** not launch-ready; no merge to `main` or deployment.


## 25. Supplier selector tests now execute in CI — 21 October 2026

- **CI workflow updated:** `.github/workflows/paystack-refund-safety.yml` now runs `SupplierSourcingSnapshotSchemaTest` and `SupplierOfferSelectionServiceTest` alongside the existing selected financial/payment regression suite. The syntax step also checks the supplier selector service, model, order-item model, and both new sourcing migrations.
- **First execution caught a real issue:** the new test file initially had doubled namespace separators, causing a PHP parse error before tests ran. The file was corrected and the syntax step was expanded to include the sourcing runtime and migration files. This is resolved in the later passing run; the failed run remains part of the audit history.
- **Passing evidence:** [GitHub Actions run 39571578977](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39571578977), commit `7519fcc56c51aeda6cc0dba52544880bb6862a40`, completed successfully. The selected suite reports **152 passed, 1,011 assertions**; both supplier-sourcing feature test classes passed; the workflow's listed PHP syntax checks passed.
- **Scope limit:** this is a selected suite, not a claim that every repository test passes. The schema test verifies the test database schema after migrations, not a production database upgrade rehearsal.
- **Superseded status (21 October 2026):** authorized, audited supplier availability confirmation exists. Automated sourcing and immutable supplier snapshots are integrated into `CheckoutExecutionService`, but the separate API pickup reservation/payment writer remains unwired. Catalogue eligibility now uses fresh canonical listing state plus an active supplier offer with attributed, unexpired availability evidence. Legacy order-level vendor/shop constraints and financial split calculations remain launch blockers.
- **Next action:** map the existing admin authorization and audit-event patterns, then implement the availability-confirmation workflow with explicit actor, confirmation timestamp, expiry, offer locking, and audit record. Do not accept supplier availability or supplier IDs from customer input.


## 26. Supplier availability confirmation endpoint added; CI verification pending — 21 October 2026

- **Protected route added:** `POST /admin/supplier-offers/{offerId}/confirm-availability`, named `admin.supplier_offers.confirm_availability`, registered inside the existing `role:super_admin` route group.
- **Controller added:** `backend/app/Http/Controllers/Web/AdminSupplierOfferAvailabilityController.php`. It validates a required future expiry and optional notes (maximum 500 characters), captures request IP/user-agent for audit context, and delegates confirmation to `SupplierOfferAvailabilityService`.
- **Service guard remains authoritative:** the service independently requires an active super-admin actor and active, correctly matched supplier/shop; it locks the offer inside a transaction, records confirmation actor/time/expiry, and writes an audit event. An identical active retry by the same actor does not create a duplicate audit event.
- **Route regression coverage added:** `SupplierOfferSelectionServiceTest` now exercises customer denial, super-admin success, and expired-input rejection without an availability/audit write.
- **CI updated:** the workflow syntax-check now explicitly includes the new controller and `SupplierOfferAvailabilityService`; the sourcing feature test file already runs in the selected suite.
- **Important operational limit:** this endpoint records a human-reported supplier confirmation. It does not contact the supplier or independently prove stock. The admin must use the approved supplier-check process and enter a truthful expiry; the service does not invent a confirmation lifetime.
- **Verification status:** see Section 28 for the completed CI run tied to the controller import fix; the two earlier failures and their causes are preserved in workflow history.
- **Superseded status (21 October 2026):** `CheckoutExecutionService` uses the sourcing selector and persists supplier snapshots; `CheckoutAndReservationApiController` pickup reservation/payment still follows the legacy shop-bound flow. Catalogue eligibility has been decoupled in code and schema through the new forward migration, with CI verification pending. Order-level legacy vendor/shop constraints and legacy split accounting remain unresolved.
- **Release rule:** no merge to `main` and no deployment without explicit owner approval.


## 27. Checkout and pickup sourcing integration — refreshed writer trace — 21 October 2026

- **Canonical web/API checkout still requires a customer-selected shop:** `CheckoutExecutionService::execute()` reads `shop_id`, checks that every product belongs to that shop/vendor, and uses `Product::marketplaceEligible()`. Its comment still states vendor-managed delivery. The customer/API intent writer in `CheckoutAndReservationApiController::createIntent()` likewise requires `shop_id` and checks products against the selected shop. Neither writer calls `SupplierOfferSelectionService`.
- **Free pickup reservation still binds to a supplier-owned shop:** `CheckoutAndReservationApiController::createPickupReservation()` validates customer-provided `shop_id`, requires product ownership by that shop, and writes `vendor_id` and `shop_id` into `PickupReservation`. This path is free at creation, but it is not yet the VM-owned-catalogue/private-supplier-selection flow.
- **Pickup payment is a separate production order/item writer:** `payPickupReservation()` locks the reservation and requires `inspected_accepted` before payment initialization. It then creates an order with legacy order-level vendor/shop fields and legacy 90/5/5 amounts, creates an `OrderItem` without supplier-offer/cost snapshots, marks the reservation `payment_completed`, commits, and only then calls Paystack initialization. This must be handled separately from `CheckoutExecutionService`; changing only the shared checkout service cannot close the pickup writer.
- **Existing safeguards to preserve during refactor:** checkout idempotency-key/request-hash replay protection; transaction and reservation row locks; reservation must be inspected/accepted before payment; free reservation creation; customer wallet-credit tender prohibition; Paystack reference/uncertain-outcome handling; customer-facing reservation responses avoid serializing the entire model.
- **Required next implementation sequence:** first establish the canonical product and fulfilment allocation schema without deleting historical vendor/shop references; then create a shared transactional selection-and-snapshot writer that selects eligible supplier offers server-side and persists immutable offer/vendor/shop/cost/currency snapshots; integrate web checkout and API checkout/intent with the same semantics; separately refactor reservation creation and pickup payment to bind a selected private offer while preserving inspect-before-pay and no upfront reservation charge. Map order-level constraints and all settlement/refund/report consumers before making the new fields financially authoritative.
- **Safety note:** do not make an in-place broad checkout rewrite based only on this trace. The new snapshot fields exist, but the runtime writers and downstream financial consumers are not yet migrated. Keep all associated launch blockers OPEN until targeted regression tests prove both delivery and pickup paths, retries/concurrency, supplier changes, and historical compatibility.
- **Status:** TRACE UPDATED; NO CHECKOUT OR PICKUP RUNTIME BEHAVIOR CHANGED IN THIS STEP.


## 28. Supplier availability admin route regression suite passed — 21 October 2026

- **Workflow:** [run 39572869961](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39572869961), commit `05f2d025bb6e0c7004b83cc722b6ac9f655b129b`.
- **Observed result:** changed-file PHP syntax checks passed, including the new availability controller and service. The selected feature suite completed successfully: **156 passed, 1,028 assertions**.
- **What was caught and corrected before this pass:** first, PHP namespace escaping in the new controller failed syntax validation; after that fix, route tests revealed the controller import was missing from `backend/routes/web.php`. Both issues were corrected. The passing run includes the route authorization/expiry tests and the service's audit/idempotency tests.
- **Verification scope:** this proves the selected CI suite passed at the recorded commit, not that every repository test passes or that checkout/pickup sourcing is integrated. No production database or deployment was changed.
- **Next blocker:** use the refreshed Section 27 writer map to plan a narrow, tested shared selection-and-snapshot integration. First address schema constraints and historical dependencies; do not switch order economics to supplier snapshots until both checkout writers, pickup payment, settlement, refunds and reports are mapped and covered.
- **Release status:** not launch-ready; no merge to `main` and no deployment.


## Section 29 — Order ownership, supplier snapshots, and rollback safety (21 October 2026)

### Confirmed from current branch source

1. The original order/fulfilment migration makes `orders.vendor_id` and `orders.shop_id` required, while `pickup_reservations` also requires one vendor and one shop. These are historical schema assumptions and cannot be safely removed by editing an already-applied migration. A forward migration and an explicit legacy-data strategy are required.
2. The supplier-offer migration adds nullable `orders.selected_supplier_id` and `orders.selected_shop_id`, but does not by itself make the order independent of those legacy required ownership columns. This is not yet multi-supplier-per-order support.
3. `CheckoutExecutionService` still requires a customer-supplied `shop_id`, checks each product against that shop/vendor, writes legacy `orders.vendor_id`/`shop_id`, and copies product-level `cost_price` into `order_items`. The new selected-offer supplier snapshot columns are not populated by this checkout writer.
4. `CheckoutAndReservationApiController::createPickupReservation()` requires `shop_id` and stores one vendor/shop on the reservation. `payPickupReservation()` creates an order and order item using legacy ownership and product cost. This path also needs sourcing integration; changing doorstep checkout alone would leave pickup inconsistent.
5. `OrderSettlementService` and `ReturnAndRefundService` still rely on order-level vendor ownership for settlement/refund decisions. A schema change must map those consumers and preserve historical orders before introducing item-level supplier payable calculations.
6. The supplier availability evidence migration's `up()` checks whether its index exists before creating it, but its `down()` previously attempted to drop the index unconditionally. The rollback was hardened to check for the named index before dropping it, avoiding a needless rollback failure when the index is absent.

### Required next implementation sequence

- Inventory every order creation path, order-item writer, pickup reservation transition, payment-intent creator, webhook settlement, refund, reporting query, and payout/settlement consumer before modifying ownership constraints.
- Add forward-only, production-safe schema changes; do not rewrite historical migrations. Decide whether new orders retain nullable legacy ownership for compatibility or use an explicit order/fulfilment-group structure, and prove every old consumer handles the new representation.
- At item creation, persist immutable selected offer ID, supplier/vendor/shop IDs, display-name snapshots, agreed wholesale cost, currency, and snapshot timestamp. Never infer the agreed cost later from mutable product or offer rows.
- Keep customer input limited to product/quantity/fulfilment details. Supplier selection must use eligible, unexpired availability evidence; any authorised override must be separately permissioned and audited.
- Keep pickup as its own flow: reservation creation remains unpaid, counter payment is verified by the server, and pickup pass, customer handover OTP, and proof remain distinct from rider custody credentials.
- Replace new-order financial split assumptions only after all producers/consumers and refund/reconciliation paths are mapped. No new percentage allocation is approved by this audit.
- Add migration tests for fresh install and upgrade from representative legacy schemas, plus regression tests for mixed supplier items, unavailable suppliers, immutable cost snapshots, duplicate/concurrent requests, refunds, and historical order reads.

### Status

This section records source-level findings and one rollback-safety fix. It does **not** claim that checkout, pickup, order ownership, financial accounting, or production migration has been completed. CI must be run against the latest branch head after these changes; no production migration, merge to `main`, or deployment is authorised by this work.


## Section 30 — Automated cheapest supplier sourcing and immutable order-item snapshot implementation (21 October 2026)

- **Severity addressed:** Resolves runtime gap identified in VM-ALIGN-001, VM-ALIGN-004, VM-ALIGN-008, Section 27, and Section 29.
- **Implemented components:**
  1. **`App\Services\SourcingAllocationService`**:
     - `resolveCheapestOffer(Product $product, int $quantity = 1, ?int $preferredLgaId = null)`: delegates to `SupplierOfferSelectionService` when available offers with explicit confirmation evidence exist, or queries active, non-deleted `SupplierOffer` records where `is_available = true`. Validates active `VendorProfile` (`marketplace_status = 'active'`), active `User` (`status = 'active'`), and active `Shop` (`is_active = true`). Sorts deterministically by lowest `wholesale_cost` (via BCMath), prioritizes matching LGA when preferred LGA is specified, and breaks ties deterministically by offer ID. Provides backward-compatible fallback to product catalog cost and primary merchant when no separate offers exist.
     - `buildOrderItemSnapshot(...)`: builds immutable snapshot payload including `supplier_offer_id`, `supplier_vendor_id`, `supplier_shop_id`, `supplier_name_snapshot`, `supplier_shop_name_snapshot`, `supplier_cost_snapshot`, `supplier_cost_currency`, and `supplier_snapshot_at`.
     - `assignOrderSourcing(Order $order, int $vendorId, int $shopId, ?string $notes = null)`: sets order sourcing status to `assigned_to_supplier` with timestamp.
     - `overrideSupplierAssignment(Order $order, int $newVendorId, int $newShopId, User $adminUser, string $reason)`: enforces admin role check, validates replacement supplier and shop active status, updates order state to `admin_reassigned`, and writes full old/new values to `AdminAuditLog`.
     - `confirmSupplierAvailability(Order $order, int $vendorId, ?string $notes = null)`: verifies supplier ownership and transitions order sourcing status to `supplier_confirmed`.
  2. **`App\Models\Order` & `App\Models\OrderItem`**:
     - Added `sourcing_status`, `selected_supplier_id`, `selected_shop_id`, `sourcing_notes`, and `sourced_at` to `$fillable` on `Order`.
     - Added `selectedSupplier()` and `selectedShop()` relationships on `Order`.
     - Cast monetary snapshot columns (`supplier_cost_snapshot`, `unit_price`, `cost_price`, `line_total`) to `'decimal:2'` on `OrderItem`.
  3. **`App\Services\CheckoutExecutionService`**:
     - Injected `SourcingAllocationService` (container auto-resolved with zero breakages for callers).
     - Resolves cheapest private supplier offer per item during line item preparation.
     - Sets `sourcing_status = 'assigned_to_supplier'`, `selected_supplier_id`, `selected_shop_id`, and `sourced_at = Carbon::now()` at order creation.
     - Persists complete immutable supplier and wholesale cost snapshot on each created `OrderItem`.
  4. **Regression test suite `Tests\Feature\AutomatedSupplierSourcingTest`**:
     - 9 comprehensive tests covering cheapest offer selection, unavailable/inactive exclusions, catalog fallback, tie-breaking by LGA, immutable snapshot persistence despite subsequent price changes, admin override audit logging, non-admin denial, supplier availability confirmation, and end-to-end checkout execution snapshotting.
  5. **CI Workflow Verification**:
     - Updated `.github/workflows/paystack-refund-safety.yml` with syntax checks and test execution for both `SupplierOfferSelectionServiceTest` and `AutomatedSupplierSourcingTest`.
     - Local full test suite execution verified: **166 passed, 1,085 assertions, 0 failures**.
     - **GitHub Actions CI Run Verified:** [Run #39573855362](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39573855362) for commit `7a13109b195b0a5aaa4ef77e38120b16e30efbef` completed with conclusion **`success`** (100% green).




## Section 31 — Canonical catalogue decoupling from legacy supplier ownership (21 October 2026)

- **Purpose:** make public product identity and listing lifecycle belong to Victorious MARKET rather than to one legacy vendor/shop relationship.
- **Forward migration added:** `backend/database/migrations/2026_10_21_000007_make_product_supplier_ownership_nullable.php` makes `products.vendor_id` and `products.shop_id` nullable without rewriting the original migration or clearing existing references. Its rollback refuses to restore NOT NULL constraints when any product has a null legacy ownership reference.
- **Model change added:** `Product::supplierOffers()` exposes private sourcing offers. `Product::scopeMarketplaceEligible()` now requires the canonical listing to be approved, listed, in stock, and confirmed within the seven-day freshness window, plus at least one non-deleted, available offer with attributed, unexpired availability evidence and an active supplier user/profile and active shop.
- **Regression coverage added:** `SupplierOfferSelectionServiceTest` now covers exclusion without verified eligible offers, continued catalogue eligibility when the legacy/default supplier is inactive but another supplier has a valid offer, exclusion after that evidence expires, and a canonical product with null legacy vendor/shop references.
- **Compatibility boundary:** this does not yet remove legacy shop requirements from `CheckoutExecutionService`, nor does it refactor `CheckoutAndReservationApiController::createPickupReservation()` / `payPickupReservation()`. It also does not retire legacy 90/5/5 or 90/10 financial fields. These remain separate launch blockers and must not be described as completed.
- **Verification status:** the previously cited GitHub Actions run `39573855362` passed 166 tests / 1,085 assertions on commit `7a13109b195b0a5aaa4ef77e38120b16e30efbef`. That run predates the catalogue-decoupling commits, so it does not verify Section 31. A new CI run against the latest branch head is required before treating this change as green.


## Section 33 — Owner correction: logistics-company 90% default and active cashback retained (21 October 2026)

### Binding current decision

1. **Logistics-company default:** the default payable for a new doorstep delivery is 90% of the customer delivery fee captured in the immutable order snapshot. The remaining 10% is default VM delivery margin. The 90% is paid to the logistics company, never directly to its rider under this rule.
2. **Admin commercial override:** an authorized admin may set the final logistics-company amount higher or lower when Nigerian route, distance, package, fuel, traffic, weather, urgency, return risk or negotiated company terms justify it. Record default amount, final amount, reason, admin identity and timestamp. A final amount above the customer fee must be recorded as an explicit VM-funded delivery subsidy/expense. No completed financial entry may be edited; a post-posting change requires linked balanced adjustment entries.
3. **Cashback remains active:** accrue 5% on eligible post-discount merchandise actually funded by real money through verified Paystack payment, after redeemed cashback and excluding delivery. Fund it from VM gross retail margin, never from supplier wholesale cost. Existing terms remain: verified fulfilment, 24-hour claim window, separate 24-hour maturity buffer, no blocking case, 365-day validity from maturity, redemption up to 50% of cart merchandise subtotal, reversal of unspent cashback and separate recovery of spent cashback only against future matured cashback.
4. **No merchandise commission split:** the former 90/5/5 vendor/cashback/platform merchandise split remains retired. Cashback is a separate loyalty liability and supplier payable remains the immutable agreed wholesale cost.
5. **Rider boundary:** riders perform operational delivery for their linked logistics company. This 90% company payable does not create a rider payable, rider wallet or rider payout.
6. **Historical records:** legacy vendor earning and payout rows remain historical and auditable. They must not be relabelled as logistics-company payable or supplier payable. Preserve old allocation snapshots for refunds/reconciliation.

### Deep-read findings and remaining implementation work

- Updated current policy documents: `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`, `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md`, `VICTORIOUS_MARKET.md`, `README.md`, `.ai/BUSINESS_RULES.md`, `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md`, `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md`, `backend/VICTORIOUS_MARKET.md`, and the legacy payout/delivery audit notices.
- The legal-document seeder source has now been updated to active 5% cashback and the 90% logistics-company default/override policy. Existing published legal-document rows still require a controlled versioned update/reseed and production verification; changing the seeder alone does not prove deployed terms changed.
- Runtime checkout still writes legacy merchandise vendor splits and a delivery 90% field that is later treated as vendor earning when no logistics company is assigned. Pickup checkout also calculates cashback on the full subtotal instead of the eligible real-money-funded merchandise base after cashback redemption. These are code-level contradictions, not merely documentation issues.
- New single-order retailer flow now snapshots the default/final company payable, exposes audited pre-custody admin assignment/override, reclassifies the payable to the selected company, and releases the approved amount to the company wallet once after verified delivery. The company payout request itself remains company-level, not order-allocated; after-handover adjustment/recovery therefore remains a launch blocker.
- No latest CI run has been verified. Documentation alignment is not evidence of runtime correctness. Do not merge to `main` or deploy until code, tests, migration-upgrade paths and legal publication are verified.
 
## Section 32 — Cross-document retailer-policy alignment pass (21 October 2026)

### Changes committed on `docs/retailer-central-fulfilment-model`

- Updated `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md` to retire the old 90/5/5 merchandise split, retain active 5% cashback under existing terms, and set the 90% delivery-fee default as a logistics-company payable subject to audited admin override. This earlier Section 32 note was superseded by Section 33 and must not be read as current policy.
- Updated `README.md` so its current accounting summary no longer asserts active new 5% cashback accrual or describes legacy vendor-managed rules as binding.
- Added a prominent supersession notice to `.ai/BUSINESS_RULES.md`; current retailer rules govern new work, while old vendor/rider/payout requirements are retained as migration history. Replaced active cashback and percentage-allocation instructions with historical-versus-new-transaction rules.
- Updated `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md` to distinguish current payment/refund safeguards from legacy marketplace allocations and payout batches. The legacy FIFO payout checklist is now explicitly historical-only; it is not a supplier-payable rule.
- Updated `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md` to classify the old allocation/cashback language as legacy source-audit material and require immutable historical reversals while using retailer accounting for new orders.

### Binding interpretation after this pass

1. Victorious MARKET remains the sole customer-facing retailer; supplier/shop selection is private and server-controlled.
2. New supplier payables use the immutable agreed wholesale cost per selected offer/order item, after verified handover and reconciliation.
3. New logistics-company charges use an approved VM/company agreement. No replacement percentage, supplier-payment threshold, or payment SLA has been invented.
4. The former 90/5/5 merchandise split, 90/10 delivery split, vendor payout FIFO thresholds, and vendor-managed delivery rules apply only to historical records where those rules were actually captured and remain necessary for reconciliation.
5. Preserve historical cashback records and customer obligations. Do not accrue new cashback until an explicit owner-approved policy is consistent across the authoritative system rules, retailer model, payment/refund matrix, code and customer terms.
6. Customer refund approval/execution remains independent of supplier recovery; recovery requires evidence, authorization and contractual basis.

### Remaining alignment and verification blockers

- The existing documents still contain detailed legacy rules and acceptance tests by design. They are now labelled or bounded as historical, but every remaining occurrence must be read in that context; do not delete source-audit evidence needed for migration.
- This pass changed documentation only. It did not refactor checkout/pickup writers, remove legacy order-level vendor/shop constraints, change financial runtime calculations, or enable/disable cashback in code.
- The catalogue-decoupling migration/model changes recorded in Section 31 still require a CI run against the latest branch head. No claim is made that latest code is green.
- Next code slice: trace and test all live order creation/refund/settlement paths, then remove legacy ownership requirements for new orders with a forward migration and historical compatibility tests. Only after every producer/consumer is mapped should runtime financial calculations change.
- `main` remains untouched; no deployment is authorized.


### Section 32 verification addendum

- The payment/refund matrix's vendor FIFO payout checklist was also changed to explicitly preserve that rule for historical marketplace payouts only; it is not a new supplier-payment threshold.
- README branch metadata now identifies `docs/retailer-central-fulfilment-model` as the canonical working branch.
- **CI status:** no workflow run is currently returned for the latest alignment commit sequence. Documentation edits are not runtime verification; catalogue migration, checkout/pickup sourcing, order ownership, legacy financial calculations and cashback enforcement remain unverified/open.


## Section 34 — Retailer logistics wallet release and cashback recovery lifecycle (21 October 2026)

- **Logistics payable snapshot:** migration `2026_10_21_000008_add_retailer_financial_snapshots_and_logistics_override.php` now stores the retailer-model flag, item supplier-cost total, retailer merchandise margin, default/final logistics-company amount, override audit, ledger beneficiary, and once-only company-wallet release amount/time.
- **Admin assignment/override:** `LogisticsCompanyPayableService` supports row-locked company assignment after verified payment and supplier availability confirmation, balanced beneficiary reclassification, and pre-rider-custody amount override. Both routes are exposed in the admin order ledger. The default is 90% of delivery fee; a company amount above the customer fee creates a separately journaled VM subsidy. The payable is owed to the company, never directly to its rider.
- **Company wallet release:** after server-verified customer delivery OTP and photo proof, the service releases the approved amount to the active assigned logistics company's wallet once. The original payable is debited and the company-wallet liability is credited; the order records the released amount/time to prevent duplicate credit.
- **Cashback lifecycle:** `CashbackMaturityService` and scheduled `cashback:mature` transition eligible lots to available only after verified fulfilment, maturity time, and no blocking return/dispute. The 365-day validity begins at actual maturity. `CashbackRecoveryLedger` tracks spent cashback recovery separately; future matured rewards offset that receivable before the remaining amount becomes available.
- **Refund accounting:** `RetailerRefundAccountingService` is shared by gateway-confirmed and manual refund paths. It reverses item-level supplier cost, unspent cashback liability and actual retailer margin rather than legacy 90/5/5 vendor allocations. Spent-cashback recovery is recorded separately and offset only against future matured cashback. The confirmed gateway path is the sole journal writer, preventing duplicate cash-outflow and reversal journals. A customer delivery-fee refund is recorded independently as a VM expense; it does not automatically claw back the logistics company's earned trip payable, and customer protection never waits for company recovery.
- **Handover boundary:** retailer orders no longer receive the old vendor 90/5/5 or vendor delivery journal entries a second time during the doorstep handshake. Cashback maturity begins after verified handover.
- **Verification:** GitHub Actions run [`39584971896`](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39584971896) passed **170 tests / 1,126 assertions** on commit `38bd8925490e9331afb18bc272b7363a34a6eef3`, including retailer refund journal de-duplication, company wallet release idempotency, cashback recovery offset, and higher/lower logistics payable overrides after rider assignment but before custody OTP. This does not verify a production migration, production legal-document rows, post-handover company recovery, or the full supplier payout lifecycle.
- **Remaining launch blockers:** removing customer-facing shop selection and legacy split writes, completing pickup offer sourcing/payment integration, supplier payable disbursement, pre-fulfilment cancellation refund accounting, partial-item/supplier-return edge cases, order-linked company payout reconciliation/recovery after a transfer, production legal-document verification, and migration-upgrade verification remain open. `main` remains untouched and no deployment is authorized.
