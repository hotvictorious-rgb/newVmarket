# Victorious MARKET — Simplified Delivery-First Fulfilment Policy

**Policy date:** 21 October 2026  
**Status:** Owner-directed current policy  
**Applies to:** New retailer-model transactions and all current product, checkout, fulfilment, support, finance, API and customer-facing documentation  
**Authority:** This policy supplements `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`. If older documents describe public multi-vendor selling, customer-facing shop selection, automatic in-shop pickup reservations, or supplier-address disclosure, those descriptions are historical/legacy requirements and do not define the current customer journey.

## 1. The simplest operating model

Victorious MARKET (VM) is one customer-facing retailer. Private suppliers are sourcing partners behind the scenes, not independent marketplace sellers. VM owns the retail listing, retail price, customer relationship, checkout, support, refund decision and fulfilment coordination.

All approved supplier shops, logistics companies, customer destinations, active delivery corridors and dispatches for the launch must be within **Uyo LGA, Akwa Ibom State**. The eight configured corridors are Oron Road, Nwaniba Road, Aka Road, Abak Road, Ikot Ekpene Road, Itu Road, Ikpa Road and Plaza. Do not enable Eket, Oron (town), Ikot Ekpene, Abak, other LGAs, interstate destinations or park-to-park routes for new orders. Every checkout address/landmark must resolve unambiguously to one active Uyo corridor; unknown or outside-boundary destinations fail closed.

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
- All new delivery orders are Uyo-only and use the approved Uyo doorstep-delivery workflow. No outside-Uyo or park/terminal delivery promise may be offered at launch. A support-managed pickup exception is not standard checkout and must not be used to bypass Uyo, payment, sourcing or evidence controls.
- Supplier shops and logistics-company headquarters must be physically located in Uyo, Akwa Ibom State. Other origins are rejected for new retailer fulfilment.
- Outside-Uyo destinations must have an explicitly active directional delivery lane. The old automatic nationwide fallback is not authority to accept a destination. Each route is independently enabled/disabled by operations.
- For new orders, the logistics-company payable is the assigned company's admin-configured corridor base payable plus its configured per-bulky-unit surcharge, independent of the customer's delivery fee. Snapshot the company, corridor, tariff/version, base payable, bulky quantity, bulky rate and total. The former 90% rule is historical-only for orders that actually used it.
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

1. Standard checkout accepts Uyo LGA destinations only and uses the configured Uyo doorstep-delivery workflow; outside-Uyo destinations are rejected.
2. Customer cannot select a supplier/shop or retrieve a private supplier address through checkout, product pages, public APIs or reservation lookup.
3. Pickup reservation creation and supplier-address disclosure are disabled for new customer-facing requests; support-managed exceptions use an authorized, audited process.
4. One order can contain products from multiple private suppliers without requiring multiple customer destinations.
5. The whole order is checked for availability and a feasible fulfilment plan before promising readiness or dispatch.
6. One customer base delivery fee is charged per order, plus the configured customer surcharge for each physical bulky unit. The logistics company's payable is calculated separately from that company's corridor base payable plus its configured per-bulky-unit surcharge; the former 90% allocation is not used for new orders.
7. Support cannot bypass payment verification, sourcing eligibility, delivery coverage, OTP/photo evidence, refund controls or financial idempotency.
8. Existing reservations/orders remain readable and auditable; no historical rows are mass-deleted or relabelled.
9. Tests cover attempts to create public pickup reservations, disclose addresses early, mix multiple sources, use unavailable sources, duplicate transitions, and preserve legacy reads.
10. Documentation, API/OpenAPI schemas, customer UI, legal terms, admin workflows, tests and production database state are aligned before claiming the change is fully implemented.

## 8. Verification status

This file establishes the current business policy and is subordinate to `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md` and `docs/architecture/VM_UYO_ONLY_RETAILER_POLICY.md`. Runtime alignment and end-to-end verification remain open on consolidation candidate `align/vmarket-single-source-2026-10-22`; this document does not prove implementation or test completion.


## 9. Owner-confirmed geography and delivery modes (21 October 2026)

- Supplier origin: Uyo LGA, Akwa Ibom State only.
- Logistics-company headquarters: Uyo LGA, Akwa Ibom State only.
- Local mode: Uyo destination — door-to-door.
- Geographic launch boundary: Uyo LGA only for suppliers, customer destinations, active corridors and dispatch. Other LGAs and interstate routes are future scope and must remain disabled for new orders.
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

## 14. Prevent rejection/refund of an in-flight payout (21 October 2026)

A payout-state audit found a high-severity asymmetry: vendor payout rejection already attempted to guard uncertain transfers, but logistics payout rejection had no gateway-state guard at all. It could mark a payout rejected, restore the logistics company's wallet and post a reversal while a Paystack transfer was still processing, creating a double-payment risk if the original transfer later succeeded. The vendor guard also failed to explicitly reject any non-empty active processing claim when older data lacked the usual gateway markers.

- Added a fail-closed logistics rejection gate. It refuses rejection while gateway status is pending/processing/OTP/received/unknown, a transfer code/reference exists, any processing token exists, or timeout evidence is present. It also refuses to perform a wallet refund if the company profile is missing/archived.
- Tightened vendor rejection to block any non-empty processing token, in addition to the existing gateway/reference/timeout checks.
- Added regression tests asserting that uncertain logistics payout rejection leaves the payout pending, does not restore the wallet, and creates no rejection journal; vendor rejection with a processing claim likewise leaves status and journal unchanged.
- Commits: controller a8bb953f5856d8fa1b9d2c0d24964c3e978bfd5b; regression tests 026d7eeeb747b3a3a116d3d0bbdbe20c70125e1d.
- Verification status: code and test changes are committed on the working branch; the test suite has not been executed. These tests must run before merge/deployment.

## 15. Stale payout claims and transfer-reference fencing (21 October 2026)

Further review found that both payout initiation paths could automatically reclaim a processing token after five minutes if gateway status and transfer code had not yet been persisted. A process can crash after submitting a transfer but before persisting Paystack's response; elapsed time alone does not prove that no transfer occurred. The payout reference is created before recipient/transfer calls, so a reference without a definitive failure is also not proof of a safe retry.

- Vendor and logistics payout initiation now fail closed whenever any processing token remains, regardless of its age. Manual gateway reconciliation is required before retrying an abandoned claim.
- A persisted transfer reference blocks another initiation unless the recorded gateway state is explicitly failed. Recipient-resolution failures are now recorded as failed before-transfer outcomes, allowing a safe retry/rejection without pretending a transfer was sent.
- Payout rejection now permits a retained reference only when the gateway outcome is explicitly failed, while any processing token, transfer code, uncertain gateway state or timeout hold continues to block reversal.
- Added a regression test for stale vendor and logistics processing claims: both requests must remain pending and preserve their claim instead of submitting another gateway transfer.
- Commits: controller 5adcea40ab11b359a1c10e57378051a9b5288361; regression tests 465d730dd74c125e7c5c8de6fb2c359ebd2b2b76.
- Verification status: source and test changes are committed on the working branch. PHP tests and CI have not been run. This must be verified before merge/deployment.

## 16. Logistics payout rejection must prove the original reservation (21 October 2026)

The logistics rejection path also lacked the vendor path's request-journal existence check. It credited the company's wallet and wrote a reversal even when no request-time journal proved that the wallet had been debited and a payout liability reserved. For legacy/corrupt rows this could create money from an unproven reservation.

- Wallet restoration and the balanced rejection journal now occur only if event EVT-FLEET-REQ-{payout_id} exists. If absent, the payout is marked rejected for operational closure, but no wallet credit or fabricated journal movement is posted; the row is marked REJECTION_JOURNAL_MISSING and a critical finance log is emitted for manual reconciliation.
- The normal rejection regression test now seeds the two original request-time journal legs. A second test asserts a missing request journal does not credit the wallet or create rejection journal entries.
- Commits: controller d872ba5ae19f46e9676bd01f8287b4b6503861b1; test fixture ebdb09fa246d14b2e1c469933b849e9962b3f424; missing-journal regression 26be484113394b86130ccacc5a6f7c8d23e20e56.
- Verification status: committed source and tests have not been executed in PHP or CI. Verify normal and legacy payout rejection cases before release.

## 17. Logistics payout reversal request-journal evidence (21 October 2026)

The authoritative logistics payout reversal service restored the company's wallet before checking whether the original request-time reservation journal existed. It could also write request-side reversal entries even when no request journal proved the wallet debit and payout liability. This bypassed the request-journal guard added to the manual rejection path.

- The reversal service now checks for EVT-FLEET-REQ-{payout_id} before crediting the wallet. If the request journal is absent, it leaves the wallet unchanged, records REQUEST_JOURNAL_MISSING, emits a critical finance log, and does not create request-side journal entries.
- If a confirmed payout journal exists, its actual gateway reversal accounting is still recorded. Request-side journal reversal entries are appended only when the corresponding request journal exists.
- Added a regression test invoking the reversal service on a failed payout with no request journal and asserting no wallet credit and no journal mutation.
- Commits: service 186fc9c2fc241a05c0bef8a06b75ceb5a7f2ee11; regression test 8ed3cfc6f868b9eebc3ac23c7eaf0d969edf5799.
- Verification status: source and test changes are committed; PHP tests and CI have not been run. Existing reversal tests must be verified for both request-journal-present and missing-journal paths before release.

## 18. Hold logistics payout reversal when beneficiary record is unavailable (21 October 2026)

The logistics payout reversal service could continue accounting mutations when the related company profile was missing/archived. That could produce a wallet ledger credit without a company wallet row to receive it.

- The reversal service now returns a protected finance-reconciliation result before changing payout status, wallet balance, or journals when the company profile cannot be loaded or is archived.
- Added a regression test that soft-deletes the company and verifies reversal leaves the payout pending and creates no journal entries.
- Commits: service cc4bbb997a341eebc75035ec13fa0a13d0b26d62; regression test 2ba59c483e3e78d9b7233fb6ec229e972b51a61f.
- Verification status: committed source and test changes are not yet run in PHP/CI.

## 19. Prevent duplicate payout rejection after a terminal journal event (21 October 2026)

Manual rejection previously trusted the payout request's status column as the sole terminal-state signal. If the status remained stale at pending while a confirmed payout or prior rejection journal already existed, the rejection path could apply a second liability/wallet reversal.

- Both vendor and logistics rejection endpoints now check for an existing payout-finalization or rejection accounting event before any state, wallet or journal mutation. A terminal accounting event with a stale pending status is quarantined for finance reconciliation.
- Added a regression test for both payout types confirming that an existing terminal payout journal prevents a second rejection and prevents wallet/journal changes.
- Commits: controller a16a4e8d73c84412e571b77898142cf45a3a72fc; test 87bba3870f255243fe621e82ab5c80af1cd5f313.
- Verification status: changes committed; tests have not been executed in PHP or CI.

## 20. Verify both legs of payout request journals before treating them as proof (21 October 2026)

A further audit found that payout paths treated any journal row with a request event ID as proof that the original payout reservation had been posted. A partial/corrupt event containing only one leg, a wrong beneficiary, or a mismatched amount could therefore authorize a wallet credit or liability reversal that did not match the actual reservation.

- Added shared evidence checks in PayoutFinalizationService for vendor and logistics request journals. Each requires exactly two rows for the request event, both expected account/entry legs, the correct beneficiary type and ID, and the exact payout amount; extra or mismatched rows invalidate the evidence.
- Vendor and logistics finalization/reversal now use these stronger checks; manual rejection endpoints call the same evidence methods instead of a loose event-ID existence check.
- Added regression coverage for single-leg, mismatched-amount and complete two-leg request journal evidence for both payout types.
- Commits: initial service helper c8622583c5c2dcbd8c876d2aa3bfd6ecf94e20b8; exact-leg hardening 5fa24b5311def21084545cbb64479a41fe79225f and transaction/currency checks 800b7c991b46de42949b869c53ee957a98588cf2; controller call sites 11fd47cb2234df5917a3f2dce6f756985699aae2; regression test 6f6b0f1610121d3bf75177aa8c796c9c7be02c76; fixture hardening 7de78a5d7bb0f026794ac9108f3f519295e6c530 and 6e8c8f1c3516fd8edcb13d877c2a7637e9edf456; vendor rejection test alignment 92351466bc3f3db2ce2571497d0106e8ace0b176 and request-journal seed 127e8795eacced83dbccdc66d4dda7ff7bdaa40f.
- Verification status: changes committed but tests/CI have not run. Ensure the test fixtures satisfy all schema constraints and execute the relevant payout suite before release.

## 21. Serialize logistics wallet credits during payout reversal (21 October 2026)

The logistics company wallet model's credit method performs a read/modify/save. The manual rejection and gateway reversal paths previously loaded the company relation without locking its row, while verified delivery release already uses a row lock. Concurrent delivery credits and payout reversals could therefore overwrite one another's wallet balance update.

- Both logistics payout rejection and payout reversal now reload the active company row with a database row lock before applying wallet changes. The payout row is locked first, and the company wallet mutation remains inside the same transaction as payout state and journal changes.
- Commits: controller bea588c68c5c1b3f5f8434ce00d273a103276bbb; service 54f6ef469cf6fdff3f48f05db4797bc8cf2d6f47.
- Verification status: source change committed; PHP syntax and payout regression workflows are still pending.

## 22. Include payout hardening files in CI syntax and regression checks (21 October 2026)

The existing Paystack safety workflow did not syntax-check PayoutFinalizationService or AdminTransferHardeningAndWalletIntegrityTest, and it did not execute that payout-focused test file. As a result, payout regressions could land while the existing workflow appeared unrelated or skipped the relevant coverage.

- Extended the workflow syntax check to include PayoutFinalizationService and the payout hardening/idempotency schema test files.
- Extended the feature-test command to run AdminTransferHardeningAndWalletIntegrityTest and FinancialJournalIdempotencySchemaTest with the existing financial hardening suite.
- Commit: df5f8fd4d74266375e19c5776bb9b476b5c470bd.
- Verification status: workflow update committed; the resulting run is pending. Do not treat CI as green until the latest branch-head run completes successfully.

## 23. Add read-only retailer supplier-payable reconciliation to the finance scan (21 October 2026)

The recurring financial reconciliation engine previously handled vendor/logistics gateway payouts and customer refunds but did not audit retailer-model supplier liabilities. Recovery had a local reconciliation guard, yet ordinary paid retailer orders could have an operational supplier/cost projection that diverged from their net supplier_payable ledger without the scheduled finance scan reporting it.

- Added a read-only reconciliation pass over paid retailer-model orders. It groups operational fulfillment costs by supplier and compares them with net supplier_payable journal credits minus debits per order/supplier. Missing item evidence, missing supplier IDs, or any ledger mismatch is logged at critical level and included in the reconciliation result.
- The scan never repairs journals, changes supplier assignments, authorizes payout, or releases money. Any discrepancy remains held for finance review.
- Added regression coverage that verifies a recovered multi-supplier order reconciles cleanly, a deliberately altered operational cost is reported as a mismatch, and restoring the snapshot clears the mismatch.
- Commits: reconciliation engine 6abb5bbef0f1f2c8514923270f0720eda3816780; regression test 77d797791d9f036c07514d14e9fdf665fcf96eb0.
- Verification status: code/test committed; the expanded workflow run is pending. This does not implement supplier-specific payouts, which remain intentionally fail-closed until reservation, payout, reversal, and lifecycle tests are complete.

### 23a. Run the full supplier liability scan off the frequent gateway loop

The supplier-payable reconciliation is intentionally not executed inside the 15-minute gateway payout loop, to avoid making every transfer-reconciliation run scan the entire retailer order history. It is exposed as `finance:reconcile-supplier-payables`, supports an optional `--order=ID` focused audit, reports critical mismatches, exits non-zero when mismatches are found, and is scheduled daily at 02:30 with overlap prevention.

- Commits: dedicated command e584f2188bfa5435829fc021c0677fd253936118; schedule a2289a3a31be77a778aaba0a96770e31dc2b6033; workflow syntax coverage 4af42102732a162971fc04b0b0e844be7b0bb13d.
- Verification status: command and schedule are committed; the latest branch-head CI run is still pending.

## 24. Make supplier-payable reconciliation return-aware (21 October 2026)

The first supplier liability audit compared the full operational item cost with the net supplier_payable ledger. That would incorrectly report a mismatch after a completed refund correctly debited supplier payable for returned items, and it could overlook malformed beneficiary types in the ledger query.

- The audit now includes all supplier_payable entries for the order, validates beneficiary type/ID, and accounts for completed return cases only after finding the exact supplier-payable debit event for each refunded return item. A missing or wrong-amount reversal remains a reported mismatch; a settled order needing supplier recovery is explicitly escalated rather than silently netted.
- No journal, return case, supplier assignment, or payout state is modified by the audit.
- Commit: 9af7c1209e5de49e0e19146534741a628b98d354.
- Verification status: committed source; latest syntax/test workflow is pending after the previously discovered namespace errors were corrected.

## 25. Restore PSR-4 autoloading for supplier recovery (21 October 2026)

The GitHub Actions Composer install emitted PSR-4 warnings and skipped both SupplierReassignmentRecoveryService and AdminSupplierRecoveryController because their namespace/import separators were doubled. This meant the supplier reassignment recovery route could fail at runtime before its financial safeguards executed, so the earlier source-level fixes were not operationally reachable.

- Normalized namespace/import separators throughout both PHP files so the class namespaces match their PSR-4 paths.
- Added both files to the workflow's PHP syntax checks.
- Commits: service f671a579ee6f9c230dc56effce70753aefca00a0; controller 6d9917a7f754e88c7704f93873539b6cf3449adb; CI syntax coverage 1343894516cd929ffd72c5fc9087db37948b85ab.
- Verification status: changes committed; next branch-head workflow must confirm syntax and Composer no longer emits PSR-4 skip warnings.

## 26. CI-discovered migration syntax and test-contract corrections (21 October 2026)

The expanded workflow exposed additional pre-existing inconsistencies that were blocking the financial suite: two October 21 migrations had doubled namespace separators and could not be loaded by Laravel; a supplier terminal test used an invalid marketplace-status enum value; a checkout branch test used a stale product without a fresh confirmed supplier offer; a retailer return test expected central ownership that the model did not enforce; and a high-value payout test contradicted the enforced ₦500,000 launch cap.

- Corrected namespace separators in migrations 000009 and 000010 and added both migrations to PHP syntax checks.
- Enforced central ownership in ReturnCase: retailer-model return cases clear the private vendor ID even if a caller supplies one; logistics collection-point selection remains intact.
- Updated fixtures to use the valid non-active supplier status (probation), a fresh eligible product with a confirmed offer, and the actual vendor payout hard cap.
- CI result for branch head aff8acbe65d4652f3e322799ca8fbd395b170b5e: **success**. The financial audit/payment hardening workflow completed with 210 tests passing and 1,433 assertions; earlier failures were resolved.
- Commits: migration 000009 7402842c1ba3332b6926e4781da4e1f8ad387382; migration 000010 65c0d52dab0e59dc6a8a743b31c2050425d1e3b4; workflow migration checks a9c97265d088e8424fd0fb4cb77b2572f6e6203a and c88dfc563fb569be5029382b120789939f534cff; ReturnCase model 32385951ce0a4df5dcbc18ef802d213634579c51; supplier test state cac4f87bd949746286ccf36f666c331264cdb793; payout cap test 36fa9894ce95e85ee13583192e1556313bb569b2; checkout fixture 764ed89304c22fd31455bcb8c068929a508b2eea; CI syntax coverage aff8acbe65d4652f3e322799ca8fbd395b170b5e.

## 27. Preserve order-item identity in supplier payable journals (21 October 2026)

Supplier-specific payout eligibility cannot safely allocate money from order-level net balances alone when several suppliers share one order or a single item is reassigned/refunded. The journal schema previously lacked a structured link from a supplier payable entry to the exact order item, forcing recovery and refund logic to rely on event IDs/descriptions for attribution.

- Added nullable `financial_journal_entries.order_item_id` with a foreign key/index and a best-effort migration backfill from existing capture, recovery, and refund journal evidence. Ambiguous historical rows remain null rather than being guessed.
- Capture-time supplier payable entries, supplier reassignment compensation/variance entries, and supplier refund/recovery entries now persist their exact order-item link.
- Supplier payable reconciliation now rejects supplier-payable entries without a valid item link and verifies refund reversals belong to the returned item.
- Added schema assertion and updated recovery regression fixtures to persist item-level identity. The focused recovery/reconciliation test passed in CI at branch commit 8c42cbfd1e82bd1aac5c21fe7632fd04420606f0.
- Commits: migration 75a24291c7c9f8036800b9aa40b7f8924675f49a; model a82ffba90106c8a405ac43fd505582dd99a97562; capture 081bee4aa063f8932c7e60056facf3972364769c; recovery 6a06fdb8105771395df8e630a9863d119a986c04; refund accounting b5178f0b1a9965df8598078f30c1c5148540ecc3; reconciliation 5ce19457fd3824a93d1b22820dfa2677ff562105; schema test 61d7d0e2e41abb194f0bb910450a6f79373fc1c4; recovery fixture 8c42cbfd1e82bd1aac5c21fe7632fd04420606f0; syntax coverage d9252cce6655a8458b28be67b6d43cc7eb4f5322.
- Verification status: focused CI run passed. Full branch-head CI is still required after the latest documentation/test-message commits. Supplier-specific payout execution remains fail-closed until its request/reservation, gateway finalization, and reversal workflow is implemented and tested.

## 28. Reconcile supplier payable per item, not just per supplier total (21 October 2026)

Once journal rows carried order-item IDs, the audit was strengthened to compare net supplier_payable per (order item, supplier) as well as per supplier/order. This catches offsetting errors where one item is over-credited and another item for the same supplier is under-credited while the supplier's order-level total happens to match.

- Operational line cost is compared to item-linked supplier payable credits minus debits; completed refund quantities reduce the expected liability only when the exact item-linked refund debit exists. Missing item links and invalid beneficiary types are critical mismatches.
- Commit: 44bc2323f434a8a768fcfaa04def41bf719152cc.
- CI verification at that code commit: **214 tests passed, 1,459 assertions**; PHP syntax checks passed, including the new item-link migration and supplier recovery files.

## 29. Introduce the item-level supplier payout lifecycle (21 October 2026)

The earlier fail-closed guard prevented retailer-model supplier payables from entering the legacy order-level vendor payout flow, but did not yet provide an operational supplier-specific payout lifecycle. A dedicated lifecycle is now present on the working branch.

- Supplier payout requests are built only from item-linked net `supplier_payable` balances for active Uyo suppliers, with a single active shop, complete bank details, a 48-hour bank-detail cooldown, delivered paid orders beyond the refund window, and no open dispute/return hold.
- Requesting a payout reserves each eligible item liability using balanced request journals. Per-item allocation rows prevent the same item payable being reserved twice. The request is capped at ₦500,000, with a ₦5,000 minimum; those are launch guardrails, not supplier contract terms.
- A super-admin-only treasury controller handles Paystack recipient resolution, transfer initiation, reference/amount/currency verification, reconciliation, definitive failure and reversal. Uncertain outcomes remain held; the controller never retries an ambiguous transfer automatically.
- Finalization and reversal preflight all affected item journal events before mutating any allocation. Journal evidence is checked against the payout reference, item, order, supplier beneficiary, amount and currency. Active payout allocations block a new return request for that item; completed supplier payouts are accounted for by the refund/recovery path.
- Supplier payout requests/history are exposed to the supplier account owner; employee accounts cannot initiate or view these supplier payout records. Admin disburse/reconcile/reject attempts are written to the admin audit log.
- Regression coverage exercises item-payable reservation, successful payout finalization, repeated finalization, reservation release/rejection, repeated reversal, and supplier-payable reconciliation.
- Relevant commits include payout workflow/controller/UI/routes, regression coverage, bank-detail cooldown and treasury audit logging on this branch. See repository history for exact per-file commits.
- Verification: GitHub Actions reported **225 financial-hardening tests passed (1,536 assertions)** on the latest supplier payout owner-account guard commit. The three targeted bank-update tests also passed (2 API tests / 8 assertions and 1 web audit test / 9 assertions), and PHP syntax checks passed.
- Remaining release gates: run the migration against a representative existing database, confirm real Paystack transfer verification response shape in a safe test environment, add/execute explicit concurrency tests for simultaneous supplier payout requests and return-versus-payout races, and complete the end-to-end manual treasury review. No deployment or production transfer has been performed by this work.

## 30. Revalidate payout eligibility and protect supplier recovery debts (21 October 2026)

Additional lifecycle review found that eligibility could change between a supplier requesting a payout and treasury submitting the transfer. The payout request also had no gate for unresolved recovery debts, and bank-account details could be changed through the web/API paths without a consistent bank-code contract or safe audit record.

- Before transfer submission, the service now rechecks supplier account status, marketplace status, verified KYC, the 48-hour bank cooldown, unchanged bank snapshot, exactly one active shop, each allocated item's original order/supplier identity, delivered/paid/refund-window eligibility, open disputes/returns, and order-level supplier-payable reconciliation.
- Paystack recipient details must match the payout snapshot for account holder name, account number and bank code. A missing or conflicting verification result blocks transfer before submission; the reservation is released only when the service can prove no transfer was submitted.
- Bank updates now require a bank code, are blocked while either legacy or retailer supplier payouts are active, trigger the 48-hour cooldown, and write audit records containing masked account numbers rather than full account details.
- The vendor treasury page now separates legacy marketplace balances from Victorious MARKET supplier payables and provides a distinct supplier-payable request/history panel. The supplier-facing API masks account numbers and internal transfer controls. The admin treasury page and its navigation are restricted to Super Admins.
- Supplier payout finalization accepts authoritative verified success after an uncertain timeout; it checks allocation identity/status before journal posting. Reversal preflights allocation state and every journal event before changing any ledger rows.
- Unresolved supplier recovery debts now block both retailer supplier-payable requests and legacy vendor payout requests/disbursements. No automatic debt offset or write-off was invented; finance must resolve the recovery ledger before payouts resume.
- Regression coverage now includes KYC and owner-account changes after request, changed bank snapshots, uncertain gateway holds, successful finalization after timeout, partial payout journals, unresolved recovery debts, and legacy payout blocking.
- Latest verified CI: **225 financial-hardening tests passed (1,536 assertions)** plus **3 targeted bank-update tests passed (17 assertions)**; PHP syntax checks passed. This is the configured financial-hardening workflow, not a claim that every test in the repository passes.
- Release gates still outstanding: run the new migrations against a representative existing database; perform a safe Paystack sandbox/controlled verification of recipient detail fields and real transfer statuses; add a dedicated concurrency test for payout-request versus return/dispute creation; and provide a finance-approved workflow to resolve/write off pending supplier recovery debts. No merge or production deployment has occurred.
## 31. Scope supplier-recovery journal idempotency keys across orders (21 October 2026)

A final schema-level review found that the recovery service's request idempotency contract was order-scoped, while the financial_journal_entries.idempotency_key column is unique across the whole table. Hashing only the caller key and item ID could therefore collide if the same otherwise-valid caller key were reused on a different order, aborting recovery despite the two instructions being independent.

- Recovery journal idempotency keys now hash the order ID together with the caller/item suffix. Keys remain deterministic and below the database column limit, while distinct orders receive distinct keys for the same caller-supplied key.
- Added a regression test for deterministic retries, cross-order separation, and the 100-character schema limit.
- Commits: service 63d12fc78b841fb269fff59dca7e362fa0dc28e4; test d725847725b046598c9c88f5f8c528f4fb9bdfcd.
- Verification: branch-head CI passed after both journal-writing call sites were updated; the configured financial-hardening suite reported 227 tests passed (1,551 assertions), plus the targeted vendor API test (2 tests / 8 assertions) and web audit test (1 test / 9 assertions). PHP syntax checks passed for the recovery service and regression test. CI did not validate migrations against a representative production-like existing database or exercise live Paystack transfers.
- Remaining release gates: representative-database migration validation, safe Paystack sandbox verification, and dedicated concurrency coverage for supplier payout versus return/dispute creation.
