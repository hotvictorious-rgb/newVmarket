# Victorious MARKET — Cross-Document and Implementation Alignment Audit

**Original audit date:** 20 October 2026; **retailer-model rebaseline:** 21 October 2026  
**Branch reviewed:** `docs/retailer-central-fulfilment-model` (based on `audit/paystack-refund-webhook-safety`)  
**Status:** Historical implementation findings retained; current business model re-baselined; launch blockers remain open  
**Authority:** `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md` and `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`  
**Release rule:** Do not merge to `main`, deploy, or represent a finding as fixed without explicit owner approval and evidence.

## 1. Scope and limits

This audit compared the authoritative business rules with the payment/refund matrix, payout policy, vendor-delivery business rules, implementation audit, delivery/financial-integrity plan, root and backend project overviews, README files, and `backend/database/seeders/LegalDocumentSeeder.php`. It also traced selected checkout, reservation, settlement, and refund service paths.

This is **not** proof that production is aligned. The legal-document seeder contains legacy marketplace/vendor terms that must be corrected and then deployed/versioned; changing source text alone does not update already-published database rows. The live database's published legal-document rows, production configuration, provider dashboard, bank records, and deployed runtime were not inspected in this pass. The legal-document seeder source and documentation were changed, but no runtime checkout, payment, fulfilment, refund, cashback, or payout service/controller code was changed in this pass, and no test suite was run here. Source-code findings below are therefore open remediation/verification items, not claims of completed fixes.

## 1A. Branch and pull-request isolation warning

The GitHub comparison of `main` against `audit/paystack-refund-webhook-safety` reports the audit branch as **more than 400 commits ahead, 0 behind, with 58 changed files**. The diff is not documentation-only: it includes checkout, payment, settlement, refund, payout, delivery and storefront code, plus removed or substantially rewritten tests. Removed test files include `ClosedLoopLogisticsAndWalletHardeningTest.php`, `CustomerDoorstepHandshakeTest.php`, `HubAndSpokeDeliveryConsoleTest.php`, `LogisticsCollectionPointsTest.php`, `LogisticsCorporatePayoutTest.php`, `RiderApiTerminalTest.php`, and `VendorRiderPickupHandshakeTest.php`; `FinancialReconciliationAndHardeningAuditTest.php` also has a large deletion-only diff.

The open draft PR #3 points from `docs/vendor-managed-delivery-business-rules` to `main`, not from this audit branch. Therefore, **do not merge this branch wholesale** and do not assume the open PR contains the changes described here. Before any merge, inspect the exact base/head commit and full diff, split policy/documentation changes from runtime remediation, explain every deleted test, and restore or replace coverage for removed behaviours. The 418-commit comparison is a release-control warning, not a statement that all 418 commits were authored in this session.

## 2. Canonical business baseline — owner decision 21 October 2026

The current model supersedes the earlier marketplace/vendor-managed-delivery baseline in this historical audit.

1. **Retailer identity:** Victorious MARKET is the customer-facing retailer operating from Uyo, Akwa Ibom State. Customers buy from VM; suppliers are private sources, not public marketplace sellers.
2. **Supplier catalogue:** suppliers propose product information and wholesale offers. VM approves new canonical listings or links an offer to an existing listing. VM controls public descriptions, retail prices, publication and customer terms.
3. **Order linkage:** each accepted order item snapshots the exact selected supplier offer, agreed purchase cost, product/variant, quantity and relevant terms. Offer changes do not rewrite existing orders.
4. **Central coverage:** VM offers delivery only to destinations it can cover through current sourcing and approved logistics arrangements. Uyo is the central operating base, not a nationwide-service guarantee. VM owns customer-facing delivery coverage and fees.
5. **Logistics:** VM coordinates delivery through approved logistics companies. Every rider assigned to new VM work must be active and linked to exactly one active logistics company. Supplier/vendor-linked riders are not eligible.
6. **Pickup:** authorized supplier-shop pickup remains supported using the existing reservation pass, separate one-time customer pickup verification and in-shop photo.
7. **Supplier payment:** eligibility begins only after verified successful customer delivery or confirmed pickup and reconciliation. It is not an automatic transfer. Supplier readiness, payment, VM collection, rider assignment or carrier collection alone is insufficient.
8. **Retail accounting:** supplier purchase costs/payables, customer receipts/revenue, delivery charges, logistics-company charges, fees, refunds, any approved cashback and operating expenses are separate. Former 90/5/5 and 90/10 marketplace splits, vendor earnings, vendor payout thresholds and vendor debt recovery are not default retailer rules.
9. **Customer refunds:** VM remains responsible for valid approved customer refunds even if the supplier has already been paid. Supplier recovery requires evidence-backed responsibility and contractual recoverability and must not delay a customer refund.
10. **Security retained:** server-side payment verification, authenticated webhooks, idempotency, exact amount/currency/reference binding, immutable ledgers, refund ceilings, independent refund states, evidence and reconciliation remain mandatory.
11. **Migration caution:** legacy vendor/order/logistics schemas and historic financial records must not be globally renamed or deleted. Map code, foreign keys, journals and audit dependencies before migration. Documentation changes are not proof of runtime compatibility.

## 3. Documentation alignment and migration status

The documents listed below have been edited on the retailer-model documentation branch to establish the current business direction. They do not prove that application code, published legal documents, production configuration, payment-provider settings or the live database have been migrated.

- `README.md`: retailer model, supplier offers, central delivery/pickup, supplier payment eligibility and known migration gaps.
- `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md`: canonical model, order linkage, Uyo coverage, logistics-company rider rule, pickup, supplier payment and accounting decisions.
- `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md` and `.ai/BUSINESS_RULES.md`: updated authoritative policy baseline and operational rules.
- `VICTORIOUS_MARKET.md`: updated product vision, customer journey, fulfilment and retailer accounting.
- `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md`: retained payment/refund safety controls and superseded marketplace allocation/payout assumptions for new retailer transactions.
- `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md`: implementation plan re-baselined for central logistics and supplier payables.
- `VENDOR_PAYOUT_POLICY.md`: explicitly marked legacy-only for historic marketplace earnings; supplier payments use the new retailer model.
- `VENDOR_MANAGED_DELIVERY_IMPLEMENTATION_AUDIT.md`: reframed as retailer/central fulfilment compatibility audit.
- `SHARED_HOSTING_DEPLOYMENT_GUIDE.md`: removed obsolete financial-split claim.

The existing legal-document seeder and published legal-document versions were not changed by this documentation-only pass. They must be reviewed for obsolete marketplace/vendor wording and updated through a new versioned legal publication workflow before launch. Any legacy logistics/fleet policy marked historical/inactive does not replace the current need for VM-approved logistics companies and riders linked to exactly one active company.

## 4. Critical source-code alignment register — re-evaluate under retailer model

The source observations below were collected while the code still reflected marketplace behavior. Treat them as open compatibility findings, not as instructions to preserve the old business model.

| Priority | Finding / legacy source area | Required retailer-model closure |
|---|---|---|
| **P0** | **Legacy web checkout bypasses CheckoutExecutionService.** The storefront POST route uses PortalController::processCheckout and does not yet share the hardened checkout execution/idempotency/payment-initialization path. | Audit every web/API checkout route; prevent duplicate orders/payment initialization; enforce the same request validation, supplier-offer snapshot, payment-state and idempotency guarantees. |
| **P0** | **Legacy vendor and supplier linkage are not yet the same thing.** Existing orders/products use vendor/shop identifiers and may not snapshot a selected supplier offer or purchase cost. | Design/validate private supplier-offer linkage to canonical product and order item; snapshot supplier, offer, purchase cost, quantity, variant and terms. Do not globally rename tables before foreign-key and ledger mapping. |
| **P0** | **Legacy vendor-managed rider/fleet assignment conflicts with central delivery.** Existing checkout/order fields and rider controllers support vendor-linked riders, fleet/collection-point routing and fallback assignment. | VM-authorized delivery must use an approved active logistics company. Every assigned rider must be active and linked to exactly one active logistics company. Reject supplier/vendor-linked riders for new VM work; preserve historical assignments. |
| **P0** | **Pickup credential separation needs end-to-end proof.** Existing reservation flow uses a RES reservation reference and customer pickup OTP; rider custody code paths may reuse pickup-related fields. | Keep reservation pass, customer pickup OTP and rider-to-logistics-company custody credential distinct. Customer pickup requires pass + one-time customer OTP + in-shop photo. Protect secrets from history, logs, analytics and unauthorized rider responses. |
| **P0** | **Legacy wallet-credit tender/refund branches remain in code.** Existing checkout/refund services contain historical wallet-credit paths. | Prove legacy tender/refund paths are unreachable or disable them server-side; preserve historical records. Customer refunds follow the approved original-payment-route process; no silent wallet or off-platform substitute. |
| **P1** | **Supplier payment eligibility and payment attempts are not proven.** Existing vendor earnings and payout services may assume marketplace commission and vendor settlement timing. | Implement supplier payable from the immutable selected offer/order-item purchase cost. Eligibility begins only after verified successful customer delivery or confirmed pickup and reconciliation. Use idempotent authorized payment attempts and provider reconciliation; no duplicate payable/transfer. |
| **P1** | **Customer refund component isolation and ceilings need end-to-end proof.** The existing policy requires independently approved merchandise and delivery components, per-order limits and aggregate capture limits. | Test concurrent requests, partial quantities, discounts, zero-fee pickup, mixed supplier sourcing, duplicate/late webhooks, timeouts and reconciliation. Do not use former 90/5/5 or 90/10 allocation rules for new retailer accounting. |
| **P1** | **Cashback policy needs a retailer decision.** Legacy code allocates 5% cashback under the marketplace split. | Preserve historical cashback obligations and balances; do not accrue new cashback from the old split until owner/accountant approves whether and how retailer-model cashback is funded and accounted for. |
| **P1** | **Logistics-company commercial and coverage rules are not yet approved/verified.** Existing vendor-configured destination/fee rules conflict with VM-controlled coverage. | Define Uyo-origin coverage, VM-owned customer fee rules, logistics-company rate/settlement agreements and unsupported-route behavior. No coverage or fee rule means no delivery promise. |
| **P1** | **Refund/failed-delivery side effects and authorization remain audit targets.** Operational events may interact with refunds, wallet credits or journals. | Keep supplier readiness, carrier custody, customer handover, refund approval, gateway completion and supplier recovery as distinct states. Failed delivery or complaint alone creates neither refund completion nor supplier debt. |
| **P1** | **Published legal content is unverified.** Source documents do not prove the production database has current terms. | Review the legal-document seeder and actual published Terms, supplier agreements, logistics terms, refund/pickup/delivery wording and consumer rights; publish new versioned content only through the approved workflow. |
| **P2** | **Historical marketplace records need safe migration.** Old vendor earnings, payouts, cashback, recovery and logistics entries may be linked to orders and journals. | Map all foreign keys, provider references, audit history and accounting treatment. Preserve history; do not relabel old journal entries as supplier costs or delete them. |

## 5. Important distinction: policy versus implementation

The following statements are **requirements**, not verified accomplishments:

- A successful payment webhook must be authenticated, matched to the exact reference, amount and currency, and applied idempotently.
- Duplicate captures may produce a refund only for the verified extra capture; the valid original capture must not be refunded by mistake.
- A refund is complete only after authoritative provider confirmation and reconciliation—not after approval, dispatch, timeout, or a customer screenshot.
- Customer refund completion must not wait for vendor debt recovery. Vendor recovery is a separate receivable and process.
- An admin approval does not automatically prove a vendor debt. Repayment must be verified against bank/provider records; offset needs explicit authorisation and contractual basis.
- Doorstep/in-shop photo and OTP/pass evidence prove the handover process only, not the product's conformity or the absence of warranty rights.
- The 24-hour in-shop reservation hold, the 24-hour customer claim window, any separately approved cashback maturity buffer, bank-transfer notification target, and actual gateway refund completion are different clocks and must not be conflated. The 30-minute stock hold described in older delivery-plan text has been superseded; do not implement it as the reservation expiry.

## 6A. Runtime findings discovered and resolved during code alignment — 21 October 2026

| ID | Severity | Finding | Resolution / verification state |
|---|---|---|---|
| VM-CODE-001 | P0 | `RiderTerminalApiController::guardRiderWithLogistics` authorized new delivery terminal access through an active vendor and rejected logistics-company riders, contradicting the centrally managed retailer model. | **Code changed on this branch:** guard now delegates to `RiderProfile::isEligibleForCentralDelivery()`, requiring an active rider with no vendor link and one linked active logistics company whose user account is active when present. **Tests/lint not yet run; do not mark release-verified.** |
| VM-CODE-002 | P0 | `DoorstepHandshakeService::processDoorstepHandover` repeated vendor ownership checks and rejected logistics-company assignments, so direct service calls could not enforce the new central-delivery rule. | **Code changed on this branch:** handover now checks rider eligibility against the exact order rider and logistics-company IDs, and verifies the assigned company is active. Vendor/shop metadata no longer grants dispatch authority. **Tests/lint not yet run; transition coverage still requires tests.** |
| VM-CODE-003 | P0 | Rider eligibility was expressed separately across the controller and handover service, making contradictory authorization rules easy to reintroduce. | **Code changed on this branch:** shared eligibility predicates added to `RiderProfile`. **Remaining verification:** audit rider assignment, pickup confirmation, arrival, failed-attempt and delivery-OTP transitions; add/update tests and run backend suite. |

## 6B. Additional runtime findings — 21 October 2026

| ID | Severity | Finding | Resolution / verification state |
|---|---|---|---|
| VM-CODE-004 | P0 | Rider dashboard, active-order, history and failed-delivery-attempt paths could trust a rider ID without confirming the order's logistics company matched the rider's active company. | **Code changed on this branch:** rider order lists are scoped to `logistics_company_id`; failed-attempt mutation checks the same rider/company assignment while the order is locked. **Tests not yet run.** |
| VM-CODE-005 | P0 | Checkout currently rejects customer-supplied logistics assignment and creates new orders with `logistics_company_id = null`, while the aligned handover flow now correctly fails closed without an approved company assignment. Existing vendor assignment paths are not a substitute for central dispatch. | **Open — not resolved yet:** trace and implement a VM-authorized central dispatch assignment workflow that assigns an active approved logistics company and one of its active riders atomically before custody begins. Do not re-enable customer-selected riders. |
| VM-CODE-006 | P0 | `CheckoutExecutionService` still creates legacy vendor-owned order linkage and 90/5/5 plus 90/10 allocation fields/journals; `DoorstepHandshakeService` still posts the old commission splits and vendor delivery payable. | **Open — not resolved yet:** new retailer transactions need immutable supplier offer/purchase-cost snapshots and separate supplier payable, logistics-company charge/payable, retail revenue, fee, refund and approved cashback treatment. Do not guess percentages or repurpose old journal entries. Block release until accounting implementation and reconciliation tests prove the new path. |

| VM-CODE-007 | P0 | API and web vendor assignment endpoints could attach vendor-managed riders to new orders, despite the central-retailer rule. | **Code changed on this branch:** API action now returns HTTP 410 and the web action refuses the legacy assignment; regression test updated to assert no rider assignment occurs. **Tests/lint not yet run.** |

## 6. Required closure order before implementation or release

1. Audit supplier-offer/catalogue matching, new-listing approval, canonical listing deduplication, and immutable supplier offer/purchase-cost snapshots on order items.
2. Audit every checkout route, including the legacy web checkout bypass of CheckoutExecutionService. Enforce idempotency and correct payment/order binding.
3. Implement and test VM-controlled coverage/fee rules from Uyo and logistics-company availability; reject unsupported destinations.
4. Enforce active rider linkage to exactly one active logistics company on assignment and custody transitions; block supplier/vendor-linked riders from new VM delivery work.
5. Preserve the existing supplier-shop pickup reservation pass, one-time customer pickup verification and in-shop photo, and doorstep customer OTP/photo.
6. Enforce supplier payment eligibility only after verified successful customer handover and reconciliation; make payable/payment events idempotent.
7. Retire or migrate marketplace vendor commission, vendor payout, vendor debt, vendor-managed rider and 90/5/5 or 90/10 split paths for new retailer transactions without destroying historical financial records.
8. Explicitly approve retailer accounting, supplier payment terms, logistics-company rates/settlement, delivery-fee margin and whether cashback continues. Do not invent a percentage or monetary threshold.
9. Review and version customer-facing legal documents and Terms; inspect actual published database content and obtain qualified Nigerian legal review.
10. Run targeted tests and the full backend/frontend suite. Record exact commit, commands, results and CI run. Only then mark implementation gaps closed.
11. Keep the branch unmerged and undeployed unless the owner explicitly approves the release. Documentation alignment alone does not establish launch readiness.

## 7. Audit conclusion

The documentation now defines Victorious MARKET as a retailer based in Uyo with private supplier offers and centralized delivery through approved logistics companies. It does **not** establish that the application has been migrated.

The highest-risk remaining implementation areas are the legacy web checkout bypass, supplier offer/order-item linkage, mandatory rider-to-logistics-company assignment, VM-owned coverage and fee rules, supplier payment after verified customer handover, refund authorization/ceilings, cashback migration, and published legal content.

Do not merge or deploy this documentation branch as though it implements those behaviors. Complete the compatibility audit, approve unresolved accounting/logistics terms, implement in controlled steps, run targeted and full regression/security tests, and obtain explicit owner approval first.

## Owner decision added 20 October 2026 — refund funding versus supplier recovery

The owner explicitly confirmed: **Victorious MARKET funds the approved customer refund from platform liquidity; where responsibility and contractual basis establish vendor liability, the amount is recoverable from the vendor.** This resolves the funding-source question at policy level; it does not mean the application implements it yet.

Required implementation alignment:
- Execute an approved customer refund without waiting for vendor repayment, using the original verified Paystack route where supported and all existing approval, idempotency, refund-ceiling and reconciliation controls.
- Reverse the original approved component allocations exactly once and maintain separate linked customer-refund, platform cash, supplier receivable, vendor repayment/offset, and cashback recovery records.
- A complaint alone must not create confirmed debt. Record a receivable only after an authorised, evidence-based liability decision and contractual basis establish the responsible vendor and approved recoverable amount.
- Do not assume the whole customer refund is vendor debt. Use the original child/item/component allocation and approved fault/responsibility allocation. If the vendor has already received its ₦94,500 allocation in the illustrative ₦105,000 transaction, a fully approved and contractually recoverable vendor liability may be up to ₦94,500 in that example—not automatically ₦105,000. Reverse the platform share and eligible unspent cashback liability as applicable. Shared fault or component-specific responsibility can reduce the receivable.
- Platform refund liquidity must be sufficient for approved refunds even while supplier recovery is pending or fails. A receivable is not cash and must not count as available liquidity. Add refund-liquidity monitoring/forecasting and aged/unrecoverable vendor-debt reporting.
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

## 12. Retailer-model documentation alignment pass — 21 October 2026

The current owner-confirmed model is **one centrally managed retailer**. Suppliers are private sourcing partners, not public sellers. VM owns the customer relationship, public catalogue, retail pricing, authorized customer payments, refund decisions and fulfilment coordination. Delivery is coordinated through approved logistics companies; each rider assigned to new VM work must be active and linked to exactly one active logistics company. Authorized supplier-shop pickup requires the reservation pass, separate one-time customer pickup verification and in-shop photo. Supplier payment eligibility begins only after verified successful customer delivery or pickup and reconciliation.

The following core sources were aligned to that model on branch `docs/retailer-central-fulfilment-model`:

- `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md` — single source of truth and release authority.
- `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md`, `README.md`, `VICTORIOUS_MARKET.md`, and `backend/VICTORIOUS_MARKET.md` — retailer model, supplier offers, accounting boundaries and migration status.
- `.ai/BUSINESS_RULES.md`, `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md`, `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md`, and `VENDOR_MANAGED_DELIVERY_IMPLEMENTATION_AUDIT.md` — operational, payment, refund, rider and logistics acceptance criteria.
- `VENDOR_PAYOUT_POLICY.md` remains explicitly legacy-only; its thresholds/FIFO vendor earnings do not govern supplier payments.
- `backend/database/seeders/LegalDocumentSeeder.php` — customer terms, returns, privacy, supplier agreement, About, cancellation, FAQ and contact source text were aligned and the current customer-facing versions were bumped. The old logistics-fleet SLA remains unpublished/legacy-only; it does not define the new approved logistics-company agreement.

### Conflicts corrected in this pass

1. Removed active multi-vendor/public-supplier assumptions from the current business model and customer-facing source text.
2. Replaced new-order 90/5/5 merchandise and 90/10 delivery allocation requirements with separate retailer revenue, supplier purchase cost/payable, customer delivery charge, approved logistics-company charge, fee, refund and separately approved cashback records.
3. Replaced vendor-owned dispatch/rider authorization requirements with VM-controlled assignment through an approved logistics company and exactly one active company link per assigned rider.
4. Replaced the obsolete 30-minute reservation hold in the delivery plan with the documented 24-hour supplier-shop reservation. The 24-hour customer claim window, reservation expiry, cashback maturity (if approved), and gateway refund completion remain separate clocks.
5. Reframed legacy vendor payout, child-order, rider and allocation tests as legacy-only evidence; new retailer acceptance must not rely on public vendor child orders or commission accounting.
6. Updated the seeded customer policy versions to 2.0.0 where their contents changed. A source change does **not** update an already-published production database until the seeder/version deployment is deliberately run and the resulting published documents are verified.

### Verification and remaining boundaries

- This pass changed documentation and the legal-document seeder source. It did **not** migrate runtime application code, run the PHP test suite, run PHP syntax lint, execute the seeder against a database, verify published production legal rows, or deploy anything.
- The latest checked GitHub combined commit status returned no status checks; this is not a passing test result.
- The previously documented runtime gaps remain launch blockers until code changes and targeted tests prove them fixed, especially legacy checkout bypass, public multi-vendor checkout paths, rider/company authorization, refund/webhook state handling, cashback recovery and supplier/logistics financial reconciliation.
- If the fresh deployment has no legacy transactions, obsolete marketplace routes, screens, tables and jobs should be retired through a reviewed migration rather than left active. If legacy records exist, preserve them as history until dependencies and accounting treatment are established.
- Do not merge or deploy this branch without explicit owner approval and verification against the exact intended commit.

