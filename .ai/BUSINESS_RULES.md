# VICTORIOUS MARKET — Authoritative Business Rules

> **Superseding owner decision (21 October 2026):** Victorious MARKET is a retailer operating centrally from Uyo, Akwa Ibom State. Suppliers are private product sources, not public marketplace sellers. They submit offers for VM approval or map offers to existing canonical listings. VM controls customer-facing prices and delivery, uses approved logistics companies, and requires every rider to be linked to exactly one active logistics company. Authorized supplier-shop pickup remains supported using the existing customer verification and photo flow. Supplier payment eligibility begins only after verified successful customer delivery or confirmed pickup and reconciliation. Earlier multi-vendor, vendor-managed delivery, vendor commission/payout and vendor-linked rider rules in this document are legacy assumptions and are superseded for new retailer-model work. Do not infer that code has been migrated. See [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](../RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md).

**Status:** Authoritative business-rule baseline for vendor-managed fulfilment
**Last reviewed:** 20 October 2026
**Review note:** The rules are approved requirements; Section 14 lists implementation gaps found in the audit branch, not completed fixes.  
**Scope:** Delivery, vendor-linked riders, in-shop pickup, customer privacy, failed delivery, delivery fees, COD boundaries, and legacy logistics.  
**Authority:** Read with [VICTORIOUS_MARKET_SYSTEM_RULES_V1.md](../VICTORIOUS_MARKET_SYSTEM_RULES_V1.md), the single source of truth. These rules supersede older descriptions of platform-managed logistics, conflicting OTP/photo requirements, logistics-company dispatch, rider earnings, and rider payouts. Historical records must be preserved. Documentation alignment only; implementation remains unverified.

## Decision record — retailer and central fulfilment model

This decision record supersedes older vendor-delivery Q&A decisions for new work.

## 1. Non-negotiable operating model

1. VM is the retailer and the only customer-facing seller in the product/order experience.
2. Suppliers remain private behind-the-scenes sources. A supplier may propose a new product or submit an offer for an existing canonical product. VM reviews and approves; supplier submission never auto-publishes.
3. VM owns public descriptions, retail prices, publication, customer payments, fulfilment coordination, delivery coverage, customer support and refunds.
4. VM operates from Uyo and serves only destinations supported by current product sourcing and approved logistics coverage. Uyo is the central operating base, not a promise of nationwide service.
5. Delivery is coordinated by VM and carried out through approved logistics companies. Every rider assigned to new VM work must be active and linked to exactly one active logistics company. Supplier-owned/vendor-linked riders cannot be used as delivery riders for new VM orders.
6. Authorized supplier-shop pickup remains supported through the existing reservation pass, one-time customer pickup verification, and in-shop photo evidence.
7. Supplier payment eligibility requires verified successful customer delivery or confirmed customer pickup and reconciliation. Supplier readiness, order placement, customer payment, dispatch assignment or rider collection alone is insufficient.
8. The former 90/5/5 merchandise split, 90/10 delivery split, vendor earnings and vendor payout workflow are not default retailer rules. Supplier purchase costs, logistics charges, customer revenue, fees, refunds and any approved cashback must be separately accounted for. Unresolved accounting policy must not be guessed.
9. Existing payment verification, webhook authentication, idempotency, refund ceilings, immutable journals, audit trails, consumer-rights safeguards and reconciliation remain mandatory.
10. Existing schema names may be retained during migration. Do not globally rename vendor tables or delete history before dependency and ledger mapping.



## 2. Actors and permissions

### Victorious MARKET administrator/operations

- Approves suppliers and their offers; controls canonical listings, descriptions, retail prices and publication.
- Confirms order sourcing and product availability.
- Defines customer-facing delivery coverage and fees, authorizes logistics companies and assignments, and coordinates fulfilment.
- Handles customer support, claims, refund authorization, supplier payable authorization and financial reconciliation through separate audited workflows.
- May not treat a supplier or logistics-company statement as proof of customer handover or payment success.

### Supplier

- Submits product data, photos, wholesale price, availability, condition/warranty information and source location.
- May propose a new listing or offer against an existing canonical product; cannot publish directly or change VM's public retail price.
- Supplies the product under the accepted offer and makes it available for VM collection or authorized customer pickup.
- Is privately linked to selected order items and agreed purchase-cost snapshots.
- Cannot independently dispatch its own rider for VM delivery, access customer financial details beyond operational need, approve refunds, or collect off-platform customer payment.

### Logistics company

- Must be approved/active for VM work and accepts authorized delivery assignments under its commercial agreement with VM.
- Links every assigned rider to its company, maintains rider eligibility, and records custody and delivery events.
- Cannot change product prices, supplier purchase costs, customer payment state, refund approval or supplier payable.

### Rider

- Performs only assigned logistics work for the linked logistics company.
- Receives only the customer/order details needed for the assigned delivery.
- Records custody, delivery attempts and customer handover evidence through authorized server-side transitions.
- Cannot approve refunds, alter prices, mark a payment successful, create financial entries or see supplier wholesale prices.

### Customer

- Purchases from Victorious MARKET and uses VM's authorized payment, delivery, pickup and claims processes.

## 3. Rider registration, ownership, and lifecycle

1. Every rider eligible for new VM delivery work must be active and linked to exactly one active, approved logistics company.
2. Reject a rider with no company, multiple company links, only a supplier/vendor link, or a link to an inactive/unapproved company.
3. Check company/rider status at registration, assignment and relevant custody transitions; authorization cannot rely only on hidden UI controls.
4. Company changes require an audited reassignment process. Preserve historical assignments and evidence; do not rewrite prior custody records.
5. Existing vendor-linked and legacy fleet-linked rider profiles may remain for history/migration, but cannot receive new VM work until explicitly migrated and validated.
6. Do not create rider wallets, rider earnings, rider payout requests or rider financial beneficiaries for new retailer-model deliveries unless the owner approves a separate logistics compensation architecture.

## 4. Order assignment and reassignment

1. VM or its authorized operations actor assigns a deliverable order/fulfilment unit to an active approved logistics company and one eligible rider linked to that company.
2. Validate order identity, destination coverage, fulfilment state, payment/order eligibility, company status, rider status, existing assignment and custody state within a transaction/locking strategy.
3. A supplier cannot assign a supplier-owned rider or directly change VM's logistics assignment.
4. Reassignment before custody must be audited. After custody begins, require an explicit custody-transfer or return-to-origin workflow with actor, timestamp and evidence.
5. No assignment or custody scan marks the customer order fulfilled or makes supplier payment eligible. Only verified customer handover does that.
6. Orders with multiple supplier items must preserve each item's selected supplier offer and purchase cost; sourcing groups may be tracked separately, but customer checkout is with VM, not separate public vendor child orders.

## 5. Fulfilment types

### Doorstep delivery

- VM confirms the destination is within active coverage from its Uyo operating base, the product can be sourced, a logistics company can serve the route, and the customer-facing delivery fee is known.
- VM coordinates sourcing and logistics assignment. Supplier readiness and rider collection are custody events, not successful customer fulfilment.
- Customer handover requires server-verified one-time customer OTP and doorstep photo. Preserve failed attempts and return/custody outcomes separately.
- No delivery promise where coverage, fee, product availability or carrier capacity is missing or uncertain.

### Supplier-shop pickup

- Pickup at an authorized supplier shop remains supported as a pickup/collection exception in the existing flow.
- VM authorizes the order and location; customer uses the reservation pass/reference and separate one-time customer pickup verification; record the required in-shop photo and actor/timestamp.
- Supplier readiness, reservation creation or arrival at the shop alone is not fulfilment.
- The supplier shop is not a public seller storefront or independent payment channel. Customer payment remains in VM's authorized workflow.
- A verified customer handover is the earliest supplier-payment eligibility trigger, subject to reconciliation and authorized payment processing.

## 6. VM-defined delivery coverage and fees

1. VM controls customer-facing service coverage and delivery-fee quotes; suppliers do not configure marketplace delivery prices.
2. Uyo, Akwa Ibom State is the central operating base. VM serves any destination it can currently cover through sourcing and approved logistics arrangements, not every location by default.
3. Checkout must validate a current coverage rule, carrier availability, applicable customer fee and order snapshot. Missing, expired or contradictory coverage data means delivery is unavailable until resolved.
4. Snapshot destination, fulfilment choice, quoted customer fee and applicable fee-rule version on the accepted order.
5. Logistics-company charges and payment terms must follow an approved VM/company agreement. Do not infer the old 90/10 vendor delivery allocation or create rider payables.

## 7. Customer privacy and rider data minimisation

The rider interface and rider API must reveal only information necessary for the rider to perform the assigned delivery.

### Permitted customer-facing delivery information
- Customer name.
- Delivery address.
- A protected **Call Customer** action.
- No other customer profile data. The rider must not receive additional customer instructions or contact fields unless the owner explicitly approves a future policy change.

### Prohibited rider access
- Raw customer phone number in API payloads, page markup, logs, analytics, or exports. The Call Customer action should use a protected call flow or privacy-preserving relay; do not render the number merely to make the button work.
- Customer financial data: payment status, total paid, outstanding balance, cash amount due, refunds, wallet balance, card details, bank details, or payment references.
- Platform commission, vendor margin, cashback, vendor payable, settlement state, rider earnings, payout balances, ledger/journal entries, or financial reports.
- Card PAN, CVV, payment tokens, or other payment credentials under all circumstances.
- Pickup/delivery OTPs or reusable secrets in order-list APIs, history APIs, logs, analytics, or screenshots. A verification secret is entered only into the specific action that validates it and is never returned to the rider.
- Other customers' orders, another vendor's orders, unassigned orders, or a customer's unrelated profile data.

Operational order information such as order identifier, parcel/product description and quantity, shop pickup location, destination, allowed fulfilment state, and proof requirements may be exposed only where required to complete the assigned job. Financial fields are not operational fields and must be excluded by default.

## 8. Delivery evidence and operational status

1. Record actor, timestamp, order, action, and photo reference for delivery completion or in-shop pickup. The only mandatory fulfilment evidence is the doorstep delivery photo or in-shop pickup photo.
2. Doorstep delivery requires verified customer OTP plus a doorstep delivery photo. In-shop pickup requires the valid RES-XXXXXX reservation pass AND one-time customer pickup OTP verification plus an in-shop pickup photo. GPS, customer signature, separate rider-pickup photo and hub/collection-point inspection photo are not mandatory.
3. Failed-attempt reasons should be enumerated and auditable (for example, customer unreachable, recipient unavailable, wrong address, access blocked, or customer rescheduled).
4. A failed attempt or photo is operational evidence, not a financial decision. It must not automatically create wallet credit, mark a refund paid, create a rider payable, or release vendor funds.
5. After doorstep rejection or failed delivery, record the outcome and return the item directly to its owning vendor. The vendor may offer a corrected item or replacement redelivery only with customer agreement. Record return custody and any agreed redelivery. Refund/compensation is separately reviewed.
6. Delivery proof supports an investigation but is not, by itself, proof that the item was genuine, defect-free, or that a refund is owed.

## 9. Failed delivery and customer remedies

1. Failed delivery does not automatically mean the customer receives an immediate wallet credit or cash refund.
2. The system records each attempt and reason, preserves parcel custody history, and applies the configured attempt limit consistently.
3. When the limit is reached, the rider returns the parcel to the owning vendor and the vendor confirms receipt. The order enters an explicit failed/returned/review state; it must not be marked successfully delivered.
4. Any rescheduling, redelivery fee, cancellation, refund, or vendor recovery is decided under the applicable published customer policy and platform review. It must be linked to the order and use the existing financial-operation/refund engine.
5. A customer refund is based on the verified original payment and approved refund amount, with exact allocation of merchandise, delivery fee, discounts, commission, cashback, previous refunds, and vendor amounts already paid.
6. No failed-delivery handler may directly create spendable wallet credit or a logistics/rider journal entry. Financial actions require the authorized financial service and an auditable decision.
7. Delivery fees and return-transport costs are separate concepts. Do not assume the original delivery fee is always retained or refunded; apply the published reason-specific policy and applicable law.

## 10. Cash on delivery (COD) boundary

**COD is disabled at launch for the entire marketplace**, not merely for rider-assigned orders. Checkout, APIs, payment-method flags, legacy payment methods, vendor settings, and direct requests must all reject COD. Only two payment paths are allowed: pay before delivery through Victorious MARKET checkout, or reserve in-shop pickup through Victorious MARKET and pay at the approved vendor shop through the platform's authorised payment workflow. Shop pickup has zero delivery fee and is not doorstep COD. No off-platform cash/private-account transfer is permitted; launch rails are Paystack card and standard bank transfer unless separately approved.

All payments must be processed/recorded through Victorious MARKET's authorised payment workflow. Vendors may not accept cash, private bank transfers or other off-platform payment for marketplace orders, and must not release goods until the platform verifies successful payment. Launch rails are Paystack card and standard bank transfer only unless separately approved. Riders remain operational-only and must never see payment status/amount due or collect/reconcile customer money through platform rider accounts.

## 11. Financial separation

- Customer payment, supplier payable, logistics-company charge/payable, delivery/pickup status, refund status and any customer cashback liability are distinct state machines.
- A supplier payable represents the agreed purchase cost for goods supplied to VM, not a marketplace commission or vendor earning.
- Supplier payment eligibility begins only after verified successful customer delivery or confirmed customer pickup and the required order/amount reconciliation. Eligibility is not an automatic transfer.
- Order placement, customer payment, supplier acceptance/readiness, VM collection, rider assignment, rider collection, delivery attempt or unverified completion does not independently make the supplier payable eligible.
- Supplier payable, authorization, payment attempt, provider confirmation, reversal and reconciliation must be separately linked and idempotent. Timeout means uncertain and requires reconciliation before retry.
- VM remains responsible for a valid customer refund even if a supplier was already paid. Supplier recovery requires evidence-backed responsibility and contractual recoverability; a complaint alone is not debt, and recovery must not delay a customer refund.
- Do not create rider wallets, rider earnings, rider payout requests or logistics-company payables based on the old vendor delivery split. Logistics-company charges and payment terms must be separately approved and recorded under the retailer model.
- The old 90% vendor / 5% cashback / 5% platform merchandise allocation, 90% vendor / 10% platform delivery allocation, vendor earning records, vendor withdrawal thresholds, and vendor debt-recovery rules are superseded as defaults for new retailer-model transactions. Do not invent replacement percentages or assume cashback remains active without owner/accountant approval.
- Preserve old vendor settlements, cashback, logistics payouts and financial history as legacy records. Do not reclassify or delete historical journal entries without a documented migration and accounting approval.
- All new financial movements remain idempotent, balanced, authorized, immutable and reconcilable. Balanced books do not prove cash availability; recognize loss/liquidity shortfall honestly.
- Customer payment/refund security rules remain: verify Paystack status, exact amount, currency, reference and order identity; refunds require applicable authorization and original-route handling; refund approval is distinct from gateway completion; enforce component and aggregate ceilings.

## 12. Legacy migration and compatibility

1. Keep historical logistics company profiles, payout requests, fleet rider links, delivery lanes, collection points, and ledger entries for audit/history unless a separately approved data-retention plan says otherwise.
2. Prevent legacy logistics roles from receiving new vendor-managed assignments. Legacy data may be shown in restricted read-only historical views.
3. Do not drop logistics-company columns, legacy tables, old routes, or columns until dependency mapping confirms no production, API, job, webhook, report, or historical-order dependency remains.
4. Use additive migrations and backfills. Identify vendor ownership for eligible active riders; unresolved/ambiguous riders are suspended from new assignments until manually resolved, not guessed.
5. Never convert a legacy logistics-company payout into a rider payout or vendor payout without verified financial reconciliation and explicit approval.

## 13. Acceptance tests required before release

- A vendor can create/manage its own rider; cannot create a rider for another vendor.
- Every active rider has exactly one active vendor owner; no active rider is unaffiliated or fleet-linked for new fulfilment.
- Vendor A cannot view/assign Vendor B orders or riders; Rider A cannot view or mutate an order assigned to Rider B.
- Only the owning vendor assigns/reassigns its orders; reassignment is blocked after custody begins except through audited custody transfer.
- Multi-vendor checkout creates separate child orders and assignments; each rider sees only their vendor's child order.
- Rider API and UI do not expose raw customer phone, payment status, delivery fee, total/amount due, card data, commission, cashback, vendor settlement, or payouts.
- Call Customer works without revealing the raw number in API/UI/logs.
- In-shop pickup costs zero, requires an in-shop pickup photo and creates no rider assignment or delivery payout. RES-XXXXXX may remain as a separate collection-security pass.
- Doorstep delivery requires verified customer OTP plus a linked delivery photo. GPS/signature/separate rider-pickup photo are not mandatory.
- Failed attempts create operational records only; no automatic wallet credit, rider/logistics payable, refund, or vendor settlement release.
- Repeated delivery callbacks, failed attempts, assignment requests, and pickup confirmation cannot duplicate state transitions or financial entries.
- Vendor settlement cannot become eligible before verified doorstep delivery or verified customer in-shop pickup; a disputed/return-pending order remains blocked under the applicable hold policy.
- Every merchandise earning is linked to one vendor/order item; delivery earning is separate and linked to one vendor child order; line/item refunds reverse only the affected earning.
- Vendor payout requests are system-calculated capped FIFO batches of oldest eligible whole earning records (₦5,000 minimum, ₦500,000 maximum), stop on the first record that would exceed the cap, never split or skip records, and permit only one active request.
- Admin approval is recorded before payout initiation; only an approved request can be paid, and payment is not complete until Paystack success/reconciliation confirms it.
- Failed/reversed transfers do not lose earnings or permit blind retry; reconciliation restores reserved earning records exactly once.
- Victorious MARKET executes the approved customer refund; vendor recovery is a separate, auditable allocation/recovery workflow and never a rider payout.
- A refund after vendor settlement records a separate vendor recovery receivable. Vendor may repay by verified bank transfer or an authorised admin may approve a future-earnings offset under the vendor agreement.
- Historical logistics records remain accessible for authorized audit and are not used to route new deliveries.
- Doorstep COD is disabled. The only permitted alternative to pre-delivery payment is a Victorious MARKET reservation for in-shop pickup, paid at the vendor shop through the platform's authorised payment workflow. No off-platform collection is permitted.

## 14. Required implementation work and source-audit findings

**Implementation status:** The rules above are requirements, not proof that every route and service currently complies. A source review of the audit branch identified the following concrete gaps. Treat them as open until each is corrected and covered by tests.

### P0 — Privacy and financial-boundary violations

1. **Rider API guard still accepts legacy fleet affiliation.** In `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `guardRiderWithLogistics()` permits a rider with either `vendor_id` or `logistics_company_id`. New delivery work must require an active rider profile linked to exactly one active vendor; fleet-only riders must be denied for new assignments and actions. Preserve legacy links for history, but do not treat them as eligible ownership.
2. **Rider order projection leaks restricted fields.** `formatOrderForRider()` in the same controller includes `payment_status`, `delivery_fee`, and the customer's raw phone number. The dashboard, active-order, history, and return payloads must be reviewed together. Remove these fields from rider responses, HTML, embedded page data, logs, analytics, exports, and error responses. Return only customer name, delivery address, and a protected **Call Customer** action as customer information, plus minimum operational parcel/order data.
3. **Customer phone privacy is not satisfied by hiding the number visually.** The controller eager-loads the customer's phone and returns it in JSON; the active-returns response also includes `customer_phone`. Implement a privacy-preserving call relay/action so the rider can initiate a call without the raw number being present in the rider response or page markup. Test the serialized response and rendered HTML, not just visual masking.
4. **Failed-delivery financial actions — partially remediated on the audit branch.** `recordDeliveryAttemptFailed()` no longer creates a `CustomerWalletCredit` or `logistics_payable` journal entry; a regression test is being added to CI. It still needs an atomic row-locked attempt/threshold/return-case transition, and the rider authorization/privacy audit remains open. Keep customer remedies in the authorized original-Paystack refund workflow; failed delivery alone is not refund approval.
5. **Doorstep handover — partially remediated on the audit branch.** `DoorstepHandshakeService.php` no longer creates a `CustomerWalletCredit`; it creates a return case and provisional refund reservation for rejected merchandise, and its rider response now calls this a refund request rather than a wallet credit. The service still posts sale/cashback/delivery-fee journal entries during the rider-triggered handover and returns accepted/rejected monetary amounts to the rider. Separate operational evidence from financial posting and remove financial amounts from rider payloads before this finding can close.
6. **Rider return payload also leaks customer phone.** `activeReturns()` returns `customer_phone`; remove it and route customer contact through the same protected Call Customer mechanism.

### P1 — Ownership, assignment, and fulfilment invariants

7. **Enforce ownership and assigned-order authorization on every write path.** Vendor rider creation and assignment endpoints must derive vendor identity from the authenticated vendor session, not trust a client-supplied `vendor_id`. The reviewed `VendorTerminalApiController::assignRiderToOrder()` checks vendor matching and rider activity, but does not lock the order, validate doorstep fulfilment/order state/custody, block reassignment after pickup, or write an assignment audit event. The rider API actions use checks of the form `if ($order->rider_id && $order->rider_id !== $rider->id)`, which allow a rider to act on an order whose `rider_id` is null. Require exact assignment (`order.rider_id === authenticated rider.id`) for rider delivery actions, and perform the ownership, fulfilment type, order state, account status, current assignment, and custody checks on the server.
8. **Enforce a single active vendor owner.** `RiderProfile` retains both `vendor_id` and `logistics_company_id` for legacy compatibility. For new fulfilment, require exactly one active vendor owner and reject ambiguous profiles with both links, no link, inactive ownership, or fleet-only ownership. Do not delete legacy columns until dependencies and history are safely mapped.
9. **Custody is a concurrency boundary.** Assignment/reassignment must be atomic and audited. Reassignment is permitted before custody; after custody begins, require a recorded return-to-vendor or explicit custody-transfer process. Concurrent assignment requests must not leave two active riders for one child order. `VendorTerminalApiController::updateRiderStatus()` must also prevent deactivation while the rider has a parcel in custody; currently it changes `is_active` without that custody check. Rider assignment must reject `in_shop_pickup` orders and any order outside the approved doorstep-ready states.
10. **In-shop pickup is not doorstep delivery.** Enforce `in_shop_pickup` as a first-class fulfilment type: zero delivery fee server-side, the `RES-XXXXXX` reservation pass AND the separate one-time customer pickup OTP (`Order.pickup_verification_code`), mandatory in-shop pickup photo, vendor-counter verification, separate status transitions, and no rider assignment or delivery payout. The code audit must prove both customer credentials are securely verified, one-time/reuse protection is enforced, and neither credential is exposed in rider views. Do not confuse the customer pickup OTP with rider-to-vendor parcel-custody OTP.
11. **Multi-vendor isolation.** Preserve the parent checkout with separate vendor child orders. Each child has one owning vendor, one fulfilment type, its own vendor-configured fee if doorstep delivery, and at most one active rider from that vendor. Prove that a rider cannot access another vendor's child order by guessing an ID or changing request parameters.
12. **Doorstep COD is disabled.** Permit only pay-before-delivery through Victorious MARKET or reserve in-shop pickup and pay at the vendor shop through Victorious MARKET's authorised workflow. Reject all off-platform payment. Do not expose payment state or amount due to riders.

### P1 — Retire active fleet/rider financial surfaces safely

13. **Disable new logistics-company fulfilment and payouts.** The current web routes include logistics fleet dashboards, riders, waybills, collection points, bank updates, wallet/payout requests, and admin logistics-payout actions. Remove them from new-order assignment and new financial workflows. Keep historical data accessible only to authorized roles for audit/reconciliation; do not delete old financial records or reclassify them as vendor/rider money.
14. **Remove rider earnings and financial screens.** The web routes currently include `/rider/earnings`; rider views and navigation must not present earnings, wallet, payout, payment, commission, or financial summaries. Rider dashboards may show only operational telemetry such as assigned jobs and completed trips.
15. **Audit all API and web response paths.** Inspect route middleware, controller responses, Blade templates, JavaScript hydration, notification templates, logs, exports, background jobs, and admin previews for raw customer phone, customer IDs, payment status, amounts, delivery fees, cashback, commissions, vendor settlements, or financial ledger data reaching rider contexts. `activeReturns()` currently returns `total_refundable_amount`, and the doorstep-handover response returns `accepted_amount`, `rejected_amount`, and `wallet_credited`; these are financial data and must be removed from rider-facing payloads.
16. **Keep vendor-to-rider compensation off-platform.** No rider wallet, rider earning ledger, rider payable, rider payout request, platform-funded rider compensation, or rider financial reconciliation should be created. Vendor compensation is a private vendor-to-rider arrangement outside Victorious MARKET's rider financial systems.

17. **Vendor earnings and payouts do not yet meet the approved granularity and full-withdrawal contract.** The current balance and payout flow aggregates vendor proceeds at order level (vendor_net_90 / remaining_payable_to_vendor), accepts a vendor-supplied partial amount, and the admin disbursement action combines payout approval with transfer initiation. Add an immutable earning ledger with one merchandise earning per order-item line and a separate delivery earning per vendor child order. The request must snapshot/reserve all eligible withdrawable earning records, be exactly equal to the server-computed full balance, and reject partial/overlapping requests. Split admin approval from admin payment initiation, require approved state before calling Paystack, and reconcile transfer success/failure/reversal against the exact earning allocations. Keep any order-level aggregate as a derived/reporting total, not the source of truth.

### P2 — Policy, audit, and release proof

17. **Delivery proof remains operational evidence.** Keep the configured customer handover pass/OTP and required proof. Do not include OTPs or reusable secrets in order-list/history APIs, logs, analytics, screenshots, or unrelated responses. A proof event does not independently decide product quality, refund entitlement, or financial settlement.
18. **Failed-delivery policy must be deterministic and concurrency-safe.** Define and enforce the attempt threshold, enumerated reasons, customer rescheduling rules, return-to-vendor custody confirmation, and the resulting order state. The current failed-attempt handler reads/updates the order without an encompassing locked transaction; concurrent attempts can race around the threshold or overwrite counters. Lock the order and make attempt creation, threshold evaluation, return-case creation, and custody state transition atomic and idempotent. The threshold must not silently create a wallet credit, refund, payout, or vendor-fund release.
19. **Reconcile actual financial allocation.** Preserve the approved 90/5/5 allocation on eligible merchandise (vendor/cashback/platform) and 90/10 allocation on the delivery fee (vendor/platform). Cashback excludes delivery. Refunds must use the actual child-order allocations and approved refundable components, with idempotent journal reversals and prior-refund/settlement checks.
20. **Preserve historical compatibility.** Map old routes, API consumers, jobs, webhooks, views, tests, payout records, and order histories before removing legacy dependencies. Keep historical logistics views read-only where possible; historical financial records must remain auditable.
21. **Add adversarial regression tests.** At minimum test: vendor A cannot assign vendor B's order/rider; fleet-only and ambiguous riders are denied new work; inactive vendor/rider cannot act; two concurrent assignments cannot both win; reassignment after custody is blocked; rider APIs and HTML contain no prohibited fields or raw phone; Call Customer works without exposing the number; failed delivery and rider handover create no direct financial postings; pickup is zero-fee and rider-free; COD remains disabled; duplicate callbacks do not duplicate operations; historical logistics data remains intact.
22. **Keep all documents and user-facing policies aligned.** A prior README version described third-party fleet delivery, fleet delivery-fee payouts, active logistics-company operations, and immediate wallet credits from rider handover. The README's main overview, allocation, fulfilment, custody, and actor-role sections have now been aligned on this audit branch. Before release, scan the remaining documentation, API contracts, onboarding text, customer/vendor policies, notifications, and tests for any stale statement or UI that reintroduces those retired behaviours. Keep `VICTORIOUS_MARKET.md` and `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md` aligned with this document.
23. **Verify before release.** Run targeted rider API/privacy, delivery handover, failed-delivery, pickup, vendor assignment, and historical compatibility tests, then the full backend test suite. Report actual results. Do not claim the rules are implemented merely because the documentation or a test fixture describes them.

## 15. Source-of-truth and release control

The authoritative consolidated specification is [VICTORIOUS_MARKET_SYSTEM_RULES_V1.md](../VICTORIOUS_MARKET_SYSTEM_RULES_V1.md). It controls where older wording in this audit report conflicts. The current required fulfilment photos are delivery photo and in-shop pickup photo only; no mandatory OTP proof, collection hub return or platform fleet applies to new orders.

These rules are the authoritative business requirements for vendor-managed fulfilment. Older documents that describe platform-managed logistics, logistics-provider revenue shares, rider earnings, rider payouts, or an unresolved choice to retire/keep the rider app must be updated to this model. Business-rule changes require explicit owner approval and consistent updates to API contracts, database rules, implementation, tests, and customer/vendor policies.

Do not merge to main without the user's explicit approval.

## Payment/refund policy extension

The detailed payment, refund, settlement, vendor earning/payout state-transition, Paystack webhook, confirmed policy decisions, contradictions, and implementation matrix is maintained in [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). The owner has selected card + standard Paystack bank-transfer checkout (DVA deferred), a 24-hour return/refund/protection window plus a separate 24-hour cashback-maturity buffer, original-channel-only refunds at launch, wallet refunds deferred, narrow automatic refund exceptions for verified duplicate charges and verified pre-vendor-action cancellations, future-cashback offset for recoverable spent cashback at maturity, post-discount merchandise as the 90/5/5 calculation base, delivery-fee allocation 90/10, platform ownership of rounding remainders and Paystack processing fees, and evidence-based chargeback recovery. Failed Paystack refund attempts do not satisfy the customer refund obligation. The matrix records implementation gaps; it is not proof that the code already complies. Do not merge to main without explicit owner approval.

### Final launch decision checksum (20 October 2026)

These final choices are binding across checkout, fulfilment, payment, refund, and finance code:

- One parent checkout/Paystack capture may cover multiple vendors, but every vendor child order retains separate fulfilment, fee, allocation, refund ceiling, and settlement gate.
- Launch payment methods are Paystack card and standard bank transfer only. COD is disabled everywhere. DVA, USSD, and mobile money are not launch channels.
- Customer refunds return through the original Paystack channel only. Wallet refunds are deferred. Only a verified duplicate capture or a verified payment for an order cancelled before any vendor action can pass the narrow automatic-approval policy; all other refunds require recorded human approval before dispatch.
- The standard return/refund request window is 24 hours, with admin-authorised late cases and applicable legal/warranty exceptions. Cashback remains unchanged: it matures after qualifying fulfilment, expiry of the 24-hour window and a separate further 24-hour buffer, with no blocking claim; its 365-day validity starts at `matures_at`. Recoverable spent cashback is offset against future cashback at maturity; never silently debit spendable wallet funds.
- Eligible post-discount merchandise collected (excluding delivery) uses 90% vendor / 5% cashback / 5% platform. Delivery fees use 90% vendor / 10% platform. Rounding remainder and gateway processing fees are borne by the platform share. No rider payout or payable exists in the new operating model.
- Parent-level captured funds and child-order allocations must reconcile exactly. Refund reservations enforce component-level, per-child and aggregate parent-capture ceilings under concurrency. Merchandise refunds (90/5/5) and delivery-fee refunds (90/10) require independent approvals, operations, gateway states and reversals; neither implies the other. Customer refund completion and vendor recovery are separate states.
- Required launch webhook contract: `charge.success`, `bank.transfer.rejected`, `refund.pending`, `refund.processing`, `refund.needs-attention`, `refund.processed`, `refund.failed`, `charge.dispute.create`, `charge.dispute.remind`, `charge.dispute.resolve`, `transfer.success`, `transfer.failed`, and `transfer.reversed`. Subscribe to DVA or other product-specific events only after that product is explicitly enabled.
- These rules do not certify implementation. Use the payment/refund matrix for event/state details and the implementation audit for code findings and acceptance tests. Keep work on `audit/paystack-refund-webhook-safety`; do not merge `main` without explicit approval.

## 16. Independent merchandise-refund and delivery-fee-refund decisions (binding owner rule)

**Merchandise and delivery are separate financial components. They must never be merged into one refund-approval decision or one undifferentiated refund amount.** A parent checkout may contain multiple vendor child orders, but each child order's merchandise and delivery-fee refund eligibility must be decided and recorded independently.

1. **Separate decisions:** every refund request must identify its component: (a) merchandise/item refund, (b) delivery-fee refund, or (c) a set of separately itemized component requests. A single customer complaint may mention both, but approval for one component never implies approval for the other.
2. **Merchandise refund approval:** record the exact child order, affected item/order-line IDs and quantities, reason, evidence, approved merchandise amount, applicable discount allocation, vendor/platform allocation reversal, cashback reversal/recovery, prior refunds and approving actor/time. Do not include any delivery fee in this amount.
3. **Delivery-fee refund approval:** record a separate decision for the delivery-fee component, including the child order, delivery-fee amount originally captured, reason, delivery/failed-attempt evidence, amount approved, vendor/platform delivery allocation reversal, and approving actor/time. Do not include merchandise value or merchandise commission/cashback in this amount. For in_shop_pickup, delivery fee is zero, so there is no delivery-fee amount to refund.
4. **Independent outcomes:** merchandise may be approved while delivery is denied, or delivery may be approved while merchandise is denied, or both may be approved as two distinct component decisions. Either component may remain pending review while the other proceeds. Customer messaging must state the outcome of each component separately.
5. **Separate financial operations:** create a distinct idempotent refund operation/reservation for each approved component (and for each independently approved item/component group where required). Use the original Paystack payment channel and the same original captured parent transaction as the gateway source; a separate component decision does not mean a new payment channel or a new charge. Record parent payment reference, child order, component type, component amount, approval record, and component-specific idempotency identity.
6. **Ceilings:** enforce component-specific remaining refundable balance, child-order remaining allocation, and aggregate parent-capture refund ceiling under database locks. Sum confirmed refunds plus active reservations across all components and all child orders so separate approvals cannot collectively refund more than the captured payment. Exact retries are idempotent; reuse of a key for a changed amount, component, child order, item, reason or approval instruction must be rejected.
7. **Accounting:** merchandise refunds reverse only the approved merchandise allocations and related cashback liability/recovery. Delivery-fee refunds reverse only the approved 90% vendor / 10% platform delivery-fee allocations. Never use the 90/5/5 merchandise split for a delivery refund; never use the 90/10 delivery split for merchandise. Platform gateway fees remain borne by the platform and are not silently added to the component refund.
8. **Gateway result and partial success:** approval is not execution. Track each component operation through requested/pending/processing/confirmed/failed/needs-attention and reconcile it to the exact Paystack refund identity and amount. A failed or uncertain component refund remains an open customer obligation and must not be treated as paid. One component's success or failure must not overwrite the other component's state.
9. **Vendor recovery is separate:** customer refund execution and vendor recovery are linked but independent operations. Determine recovery from the approved component and actual vendor allocation; do not hold the customer refund open merely because vendor recovery is unresolved, and do not mark vendor recovery settled merely because Paystack refunded the customer.
10. **Return window:** the normal customer claim window remains 24 hours from verified doorstep delivery or confirmed vendor-counter pickup. The additional 24-hour buffer applies only to cashback maturity, not to whether merchandise and delivery-fee components need separate decisions. Exceptional duplicate charges, pre-vendor-action cancellations, chargebacks and non-waivable legal rights follow their separate evidence/authorization rules.

**Required acceptance tests:** merchandise approved/delivery denied; delivery approved/merchandise denied; both approved as separate operations; one component pending while the other succeeds; pickup with zero delivery fee; discount and partial-item refund; multiple vendor child orders; duplicate/out-of-order webhooks; component-specific idempotency conflicts; concurrent reservations that approach both child and parent ceilings; and correct 90/5/5 versus 90/10 journal reversals.


## Owner-confirmed refund funding and recoverable vendor debt

Victorious MARKET funds an approved customer refund from platform-controlled available cash/refund liquidity even if the vendor has already been paid. The customer refund must not wait for vendor repayment. When an authorised evidence-based decision and the vendor agreement establish vendor responsibility, record a separate recoverable vendor receivable for the approved amount attributable to the responsible vendor. A complaint alone is not debt.

Reverse the approved original merchandise/delivery allocations and eligible unspent cashback liability once. Never treat a completed vendor transfer as reversed. Vendor debt is not automatically the full customer refund: calculate the recoverable amount from the affected component's vendor allocation, evidence, approved fault allocation, contract and applicable law. Shared fault can reduce the recoverable amount. Do not include platform share, gateway fees or cashback unless an explicit lawful/contractual basis and approved calculation establish that they are recoverable.

The platform must maintain and forecast sufficient refund liquidity. A vendor receivable is not cash and cannot be counted as available refund funding. Verify repayments against bank/provider records; offsets require explicit authorised approval and contractual basis. Keep disputed/unconfirmed allegations separate from confirmed debt; track aged and unrecoverable balances. Never double-recover, silently offset, or rewrite ledger history.

Illustration: if the customer paid ₦105,000 and the original allocation was ₦94,500 vendor / ₦5,500 platform / ₦5,000 cashback liability, then after the vendor has already received ₦94,500, Victorious MARKET funds an approved full ₦105,000 customer refund from platform liquidity. It reverses the original allocations once and cancels eligible unspent cashback liability. If full vendor responsibility and recoverability are established, up to ₦94,500 may be recorded as vendor receivable. The platform temporarily funds the cash gap; the receivable is not cash until collected.

## Binding finance invariants — balanced books, real liquidity, and honest loss recognition

- Every posted journal balances (debits equal credits in the same currency); required entries are atomic and idempotent.
- A platform-funded customer refund is a real cash outflow. Reverse original allocations with linked entries, preserving immutable history and the fact of any completed vendor transfer.
- Vendor receivables arise only after authorised evidence and contractual responsibility establish a recoverable amount. A complaint is not debt; the refund amount is not automatically the vendor's liability.
- Receivables, disputed debt, pending repayment and uncertain transfers are never counted as cash or available refund liquidity.
- Keep cash movements, sale/allocation reversals, cashback liability, fees, vendor receivables, repayments and write-offs distinguishable and reconcilable. Duplicate webhooks/retries must not duplicate entries or recovery.
- An authorised write-off/impairment for an unrecoverable receivable must be recognised as a loss/expense using accountant-approved treatment, without erasing the audit trail or implying cash was received.
- Reports must separately show available cash/liquidity, receivables and ageing, income/expenses, and recognised losses/write-offs. Balanced accounts can still reveal a real loss or liquidity shortfall.
- Monitor a refund reserve/forecast; do not use receivables to meet liquidity requirements. Obtain qualified-accountant review of production journal mappings.

Do not mark this control as implemented until tests prove balanced journals, cash/receivable separation, independent customer refund, preserved vendor transfer history, idempotent recovery, and visible recognition of unrecoverable debt.
