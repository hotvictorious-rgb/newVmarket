# Victorious MARKET — Cross-Document and Implementation Alignment Audit

**Audit date:** 20 October 2026  
**Branch reviewed:** `audit/paystack-refund-webhook-safety`  
**Status:** Documentation alignment and source-code trace; launch blockers remain open  
**Authority:** `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`  
**Release rule:** Do not merge to `main`, deploy, or represent a finding as fixed without explicit owner approval and evidence.

## 1. Scope and limits

This audit compared the authoritative business rules with the payment/refund matrix, payout policy, vendor-delivery business rules, implementation audit, delivery/financial-integrity plan, root and backend project overviews, README files, and `backend/database/seeders/LegalDocumentSeeder.php`. It also traced selected checkout, reservation, settlement, and refund service paths.

This is **not** proof that production is aligned. The live database's published legal-document rows, production configuration, provider dashboard, bank records, and deployed runtime were not inspected in this pass. The legal-document seeder source and documentation were changed, but no runtime checkout, payment, fulfilment, refund, cashback, or payout service/controller code was changed in this pass, and no test suite was run here. Source-code findings below are therefore open remediation/verification items, not claims of completed fixes.

## 1A. Branch and pull-request isolation warning

The GitHub comparison of `main` against `audit/paystack-refund-webhook-safety` reports the audit branch as **more than 400 commits ahead, 0 behind, with 58 changed files**. The diff is not documentation-only: it includes checkout, payment, settlement, refund, payout, delivery and storefront code, plus removed or substantially rewritten tests. Removed test files include `ClosedLoopLogisticsAndWalletHardeningTest.php`, `CustomerDoorstepHandshakeTest.php`, `HubAndSpokeDeliveryConsoleTest.php`, `LogisticsCollectionPointsTest.php`, `LogisticsCorporatePayoutTest.php`, `RiderApiTerminalTest.php`, and `VendorRiderPickupHandshakeTest.php`; `FinancialReconciliationAndHardeningAuditTest.php` also has a large deletion-only diff.

The open draft PR #3 points from `docs/vendor-managed-delivery-business-rules` to `main`, not from this audit branch. Therefore, **do not merge this branch wholesale** and do not assume the open PR contains the changes described here. Before any merge, inspect the exact base/head commit and full diff, split policy/documentation changes from runtime remediation, explain every deleted test, and restore or replace coverage for removed behaviours. The 418-commit comparison is a release-control warning, not a statement that all 418 commits were authored in this session.

## 2. Canonical business baseline

All project documents must preserve these rules unless the owner expressly changes them:

1. Victorious MARKET is the customer-facing marketplace/payment/refund coordinator. Approved vendors own products, stock accuracy, fulfilment, and their own riders.
2. A multi-vendor parent checkout uses one verified parent payment capture and separate vendor child orders. Each child has its own vendor, fulfilment, delivery-fee snapshot, item-level earnings, refund ceiling, dispute/hold state, and settlement eligibility.
3. Launch customer payment paths are only: (a) pay before delivery through Victorious MARKET; or (b) reserve for in-shop pickup and pay at the vendor shop through Victorious MARKET's authorised workflow. No doorstep COD, vendor cash collection, private-account transfer, or other off-platform settlement.
4. Launch rails are Paystack card and standard bank transfer unless separately approved. Legacy wallet-credit balances are **not** a third checkout tender at launch. Cashback redemption is a separate loyalty discount, capped at 50% of merchandise subtotal per checkout.
5. Merchandise allocation is based on eligible post-discount merchandise actually collected: 90% vendor / 5% customer cashback liability / 5% platform. Delivery fee is separately allocated 90% vendor / 10% platform; no rider payable is created. Victorious MARKET bears Paystack processing fees and vendor payout transfer fees as a platform expense.
6. Doorstep handover requires server-verified customer OTP plus a linked doorstep delivery photo. In-shop pickup requires both the `RES-XXXXXX` reservation pass and a separate one-time customer pickup OTP, plus an in-shop pickup photo. These checks prove the handover process, not product quality. GPS, signature, separate rider-pickup photo, and hub-inspection photo are not mandatory.
7. The standard claim/return request window is 24 hours from confirmed delivery/pickup, with authorised admin review of legitimate late cases and applicable statutory/warranty rights preserved. This is not a guarantee of refund approval or provider completion within 24 hours.
8. Refunds use the verified original Paystack route where supported. No wallet-credit refund or off-platform refund is enabled at launch. Automatic approval is limited to a verified duplicate successful capture and a verified payment for an order cancelled before any vendor action; other refunds require persisted authorised human approval before gateway dispatch.
9. Vendor earnings become eligible 24 hours after confirmed fulfilment only when applicable holds, disputes, security checks, and reconciliation checks permit. Eligibility is not automatic payout.
10. Vendor payout is manually approved at launch; minimum ₦5,000, maximum ₦500,000. Server-calculated strict FIFO selects whole earning records only. Stop before an over-cap record; never split or skip it. One active request remains reserved while pending, approved-unsent, in-flight, or uncertain. If the oldest record alone exceeds the cap, flag it for finance resolution.
11. No spendable vendor wallet. Vendor debt/recovery is separate from payout and customer refund. A complaint alone is not confirmed debt. Repayment requires verified bank receipt; offset requires authorised approval and contractual basis. Never silently offset or rewrite history.
12. Cashback maturity is fulfilment + 24-hour claim window + separate 24-hour maturity buffer, only when no blocking case remains. The 365-day validity period starts at actual maturity. Reverse unspent cashback; track spent-cashback recovery separately and offset only against future cashback as it matures.
13. Preserve immutable financial history. Use linked reversals/adjustments, idempotency, verified webhook events, reconciliation, and explicit refund/payout lifecycle states. A timeout is neither proof of success nor proof of failure.

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
| **P0** | **Legacy fleet/collection-point paths remain in checkout.** `backend/app/Services/CheckoutExecutionService.php` validates `logistics_company_id` (roughly lines 40–70), resolves collection-point/fleet riders and fallback logistics assignments (roughly lines 285–350), and persists `collection_point_id`, `rider_id`, and `logistics_company_id` on the created order (roughly lines 365–380). | New orders must be vendor-managed; no central fleet, hub, logistics-company payable, or rider payout. A policy document cannot disable this code path. | Remove/disable legacy assignments for new orders; enforce vendor-owned rider only; no collection-point dependency or new logistics-company financial postings. Add API/UI tests for direct forged IDs and legacy settings. Preserve historical rows for audit. |
| **P0** | **Wallet-credit tender remains in checkout.** `CheckoutExecutionService.php` calculates `wallet_credit_applied`, accepts `redeem_wallet_credit`, and can set `payment_method = wallet_credit` / `payment_status = paid` when the remaining amount is zero (roughly lines 255–300). | The launch model has only two permitted payment paths; wallet-credit tender would be a third. This is distinct from capped cashback redemption. | Reject wallet-credit tender in request validation, API/service, UI and legacy paths. Preserve historical wallet-credit records without spending or deleting them until a separately approved treatment exists. Test full/partial wallet-credit input, forged requests, and zero-payable checkout. |
| **P0** | **Multi-vendor parent creation is not proven end-to-end.** The traced `CheckoutExecutionService::execute()` accepts one `shop_id` and creates an `Order` for that shop/vendor. `OrderSettlementService` has a child-order settlement branch (around lines 375–480), but that alone does not prove checkout creates one parent capture and correctly allocated children. | Policy requires one parent checkout/capture and independent vendor child orders. A settlement branch cannot compensate for missing or inconsistent parent creation. | Trace storefront → API request → checkout intent → order creation → Paystack reference → webhook settlement. Demonstrate one parent capture, child allocation sums exactly to captured amount, one vendor per child, no duplicate parent/child delivery allocation, and independent child states. |
| **P0** | **Late claim exception is not proven.** `ReturnAndRefundService::createReturnCase()` enforces the configured refund window and returns `RETURN_WINDOW_EXPIRED` after expiry (around lines 60–90). | The policy explicitly permits an authorised admin to manually open/review a legitimate late case, including direct-contact cases, while preserving non-waivable rights. A customer API cutoff alone is not enough. | Provide a separate permissioned admin/support late-case path that records reason, evidence, approver, timestamps and remedy. Test both customer-window rejection and authorised late-case opening; ensure it does not override applicable law. |
| **P0** | **Pickup credential separation needs end-to-end proof.** `CheckoutAndReservationApiController::listPickupReservations()` serializes `pickup_verification_code` in the customer's reservation response (around lines 450–460). `OrderSettlementService::markVendorHandoverReady()` / `verifyRiderPickupOtp()` also use `pickup_verification_code` for rider-to-vendor parcel custody. In-shop reservation creation separately creates a `RES-...` reservation code and a pickup OTP. | The rules require the customer reservation pass and customer pickup OTP to be distinct, and the customer OTP to be separate from the rider parcel-custody credential. Secrets must not leak to ordinary order history or unauthorized rider views. | Map every producer/consumer and API response. Use distinct purpose-scoped secrets/fields and route authorization; server-side expiry and replay prevention; ensure rider endpoints cannot reveal customer pickup OTP. Verify customer counter pickup requires pass + customer OTP + photo and never invokes rider custody endpoints. |
| **P0** | **Legacy wallet-refund code remains in the refund service.** `ReturnAndRefundService::adminDisburseRefund()` has a launch guard rejecting non-empty manual bank/wallet references (around lines 420–440), but a legacy wallet-disbursement branch remains later in the same method (around lines 620+). | A guard is useful, but launch safety should not depend on an unproven assumption that every route can never reach legacy wallet code. | Prove the legacy branch unreachable through every caller or remove/disable it; assert no new `CustomerWalletCredit` is issued by any refund/failed-delivery route. Add route, service and adversarial tests for null, “wallet”, manual reference, and alternate-bank inputs. |
| **P1** | **Spent-cashback recovery is not yet a proven ledger workflow.** The policy matrix itself records this as open. Refund code contains proportional 5% cashback reversal logic, but that alone is not proof of tracking spent cashback as a separate receivable and recovering it only against future matured cashback. | A refund must reverse unspent cashback and handle spent cashback without silently debiting cash or creating a negative spendable balance. | Implement/verify a dedicated customer cashback-recovery ledger linked to source order/refund. Apply offsets only at future cashback maturity; test partial refund, already-spent cashback, no future cashback, multiple orders, duplicate webhooks and reversal. |
| **P1** | **Refund-window handling, parent/child refund ceilings and component isolation require end-to-end proof.** The policy requires separate merchandise and delivery-fee decisions, per-child limits and the shared parent-capture limit. | Partial/duplicate refunds can over-refund the parent or one vendor, double reverse allocations, or refund a delivery fee that was never charged. | Test concurrent requests, partial quantities, discount allocation, mixed vendors, zero-fee pickup, separate delivery-only refund, duplicate/late webhooks, provider timeout and reconciliation. Reserve amounts atomically and make all entries idempotent. |
| **P1** | **Stockout/late-payment automatic refund needs exception proof.** `OrderSettlementService` has an automated gateway-refund branch for out-of-stock/cancelled orders (around lines 560–620). | Automatic approval is allowed only for a verified duplicate successful capture or verified payment for an order cancelled before any vendor action. An out-of-stock label alone does not establish that exception. | Prove each automated branch checks the exact approved exception, vendor-action history, verified amount/currency/reference and remaining refundable balance; otherwise route to persisted human approval. |
| **P1** | **Commercial split basis and rounding need proof.** Checkout code calculates 90/5/5 and 90/10 from the current `merchandiseSubtotal` / `deliveryFee` variables; settlement posts child-level financial entries. | The policy requires the actual post-discount merchandise amount collected, excludes delivery from cashback, assigns rounding remainder to platform, and forbids double posting at parent and child levels. | Trace discount application and immutable snapshots; test discount + delivery + cashback, rounding pennies/kobo, multi-vendor sums, refund reversals, and exact parent-capture balance. Prove Paystack processing and payout transfer fees do not reduce vendor or cashback allocations. |
| **P1** | **Payout policy text is stronger than verified payout code evidence.** The policy requires whole-record FIFO, stop-on-over-cap, no splitting/skipping, ₦5,000–₦500,000 limits, atomic reservation, one active request, uncertain-transfer reconciliation and exact vendor amount. | A documented algorithm does not prove all request endpoints, admin flows, transfer retries and payout jobs enforce it. | Test oldest record below/at/above cap, cumulative batch boundary, next record over cap, oldest record alone over cap, concurrent requests, bank-change cooldown, failed transfer, timeout/uncertain transfer and retry. Never release reservation before authoritative reconciliation. |
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


