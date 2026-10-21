# Victorious MARKET — Retailer and Central Fulfilment Compatibility Audit

> **Superseding owner decision (21 October 2026):** VM is a retailer operating from Uyo. Suppliers are private product sources linked to approved offers and order items, not public marketplace sellers. VM owns delivery coordination through approved logistics companies; every assigned rider must be linked to exactly one active logistics company. Authorized supplier-shop pickup remains available with the existing reservation pass, separate one-time customer pickup verification and in-shop photo. Supplier payment eligibility begins only after verified successful customer delivery or pickup and reconciliation. Marketplace commission/vendor payout rules and vendor-managed delivery are legacy assumptions for new work. This audit records code compatibility gaps; it does not claim migration is complete. See [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md).


**Review date:** 20 October 2026  
**Branch:** `audit/paystack-refund-webhook-safety`  
**Status:** Business rules documented; implementation gaps remain open. This report is an audit and implementation plan, not proof that the code has been fixed.  
**Controlling policy:** `.ai/BUSINESS_RULES.md`  
**No-merge rule:** Do not merge to `main` without explicit owner approval.

## 1. Approved operating model — retailer and central fulfilment

The owner-confirmed business model changed on 21 October 2026. The earlier vendor-managed delivery decisions below are retained only as historical implementation context and must not be treated as current policy for new retailer-model work.

- VM is the customer-facing retailer. Suppliers submit product offers privately; VM approves new canonical listings or links offers to existing listings.
- Each accepted order item must snapshot the selected supplier offer, purchase cost, product/variant and relevant supply terms. Supplier offer edits must not rewrite an existing order.
- VM operates from Uyo, Akwa Ibom State and offers delivery only to destinations supported by current product sourcing and approved logistics coverage.
- VM coordinates delivery through approved logistics companies. Every rider assigned to new VM work must be active and linked to exactly one active logistics company; supplier/vendor-linked riders are not eligible.
- Authorized supplier-shop pickup remains available through the existing reservation pass, separate one-time customer pickup verification and in-shop photo.
- Supplier payment eligibility begins only after verified successful customer delivery or confirmed customer pickup and required reconciliation. Eligibility does not authorize an automatic transfer.
- Former 90/5/5 merchandise allocation, 90/10 vendor delivery allocation, vendor earnings, vendor payout FIFO, vendor debt recovery and vendor-managed rider rules are not default retailer-model policies.
- Preserve payment verification, webhook authentication, idempotency, refund ceilings, customer refund rights, immutable journals, evidence and reconciliation. Supplier recovery is separate from customer refunds and requires evidence plus contractual recoverability.
- The implementation is not yet proven compatible. The legacy checkout bypass and any existing vendor/logistics financial paths must be audited before release.

## 1.1 Binding policy clocks and implementation acceptance

### Binding distinction: claim window, refund processing, settlement, and cashback maturity

These are separate clocks and state machines. Never use one as a substitute for another.

1. **Customer claim/return window — 24 hours.** For ordinary post-fulfilment claims, start the standard 24-hour window from the verified delivery or confirmed vendor-counter pickup timestamp for each vendor child order. Use that child's persisted `refund_window_expires_at`; do not calculate from checkout creation, payment time, a parent order's timestamp, or the time a job happens to run. An open return, dispute, delivery exception, or unverified pickup must be handled under the relevant hold/review rule.
2. **Consumer-rights safeguard.** The 24-hour standard is an operational claim/reporting window, not a blanket waiver of rights that cannot lawfully be excluded. It must not be represented as eliminating remedies available under the Federal Competition and Consumer Protection Act or other applicable Nigerian law, including remedies that may apply to defective, unsafe, misdescribed, or non-conforming goods. Applicable law and any valid warranty/guarantee obligations prevail where they provide a stronger remedy. Have the customer-facing Terms and Conditions reviewed by qualified Nigerian counsel before launch.
3. **Gateway refund lifecycle — independent of the claim window.** A request, approval, Paystack submission, provider processing, and provider-confirmed refund are distinct states. The 24-hour claim window is not a promise that Paystack will complete a refund in 24 hours. A timeout, `refund.failed`, or `refund.needs-attention` does not satisfy the customer refund obligation; keep it open for reconciliation/review. At launch, refund through the verified original Paystack transaction only; wallet-credit refunds are disabled. Automatic approval is limited to a verified duplicate successful capture and a verified payment for an order cancelled before any vendor action. All other refunds require recorded authorized human approval before dispatch.
4. **Vendor settlement eligibility.** The owning vendor child order may become eligible only after verified fulfilment and expiry of that child's 24-hour standard window, with no blocking return/dispute/payment exception and after vendor, bank-security, authorization, and reconciliation checks. This is eligibility, not a guarantee of immediate disbursement. The additional cashback buffer below does not automatically extend the vendor's settlement hold.
5. **Cashback maturity and expiry.** Cashback stays `pending_maturation` until verified fulfilment establishes `refund_window_expires_at`. Its earliest maturity is `refund_window_expires_at + 24 hours` (the separate cashback buffer), and it can become spendable only if no blocking case remains. Thus, with no exceptions, cashback is normally available no earlier than about 48 hours after fulfilment. Its 365-day validity period starts at `matures_at`, not at checkout or payment. A pre-fulfilment cashback record may have null `matures_at` and `expires_at`; null means the clock has not started, never “mature now”.
6. **Spent cashback recovery.** Reverse unspent cashback first. Any recoverable amount already spent must be recorded in a separate customer cashback-recovery ledger and offset against future cashback only as that future cashback matures. Never silently debit the customer's cash wallet or create a negative spendable balance. Disclose this rule in customer Terms and Conditions and complete consumer-policy/legal review before enabling recovery.
7. **Exceptions and multi-vendor isolation.** Verified duplicate captures, qualifying pre-vendor-action cancellations, payment exceptions, chargebacks, and non-waivable legal rights use their own evidence and authorization paths and do not depend on a normal fulfilment clock. For a multi-vendor parent checkout, evaluate fulfilment, claims, refunds, settlement, and cashback independently per child order while enforcing the aggregate parent-capture refund ceiling across all child orders.

Implementation of this policy must be proved by code review and tests; this text is not evidence that every path already complies.


## 2. Documentation consistency review — retailer-model rebaseline

### Documentation updated on this branch

- Root README now states the retailer model, private supplier offers, Uyo operating base, central logistics-company delivery, supplier-shop pickup and supplier payment eligibility.
- `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md` is the canonical business model and migration checklist.
- `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md` and `.ai/BUSINESS_RULES.md` define the current policy and acceptance gates.
- `VICTORIOUS_MARKET.md` and `backend/VICTORIOUS_MARKET.md` describe the retailer and central fulfilment model.
- `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md` retains payment/refund safeguards while superseding marketplace allocation and vendor payout assumptions for new retailer transactions.
- `VENDOR_PAYOUT_POLICY.md` is explicitly legacy-only for historical vendor earnings.
- `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md` and this audit are rebaselined for central delivery and supplier payables.
- `SHARED_HOSTING_DEPLOYMENT_GUIDE.md` no longer describes legacy marketplace percentages as current financial policy.

### Remaining consistency checks before release

1. Source code, API contracts, customer/vendor/supplier/logistics UI, notifications, legal-document seeder and published legal pages still require a code-level and runtime audit. Documentation alignment does not prove they have migrated.
2. Supplier-shop pickup requires the RES-XXXXXX reservation pass, separate one-time customer pickup OTP and in-shop photo. Keep these distinct from any rider-to-logistics-company custody credential; do not leak customer secrets to rider views, logs or history.
3. Distinguish supplier-to-VM collection, logistics-company/rider custody, customer doorstep handover, supplier-shop customer pickup, failed attempts and return-to-origin. They are separate actors, permissions, credentials and state transitions.
4. Review the historical cashback maturity/recovery implementation. Preserve historical balances, but do not automatically enable new retailer-model cashback accrual from the old 5% marketplace allocation.
5. Confirm the production legal-document database contains current retailer, supplier, delivery, pickup, payment and refund terms. Source seeder edits alone do not update already-published records.
6. Audit every route for the known legacy web checkout bypass of CheckoutExecutionService, and test supplier payable idempotency, central delivery assignment and mandatory rider-to-company linkage.

## 3. Confirmed source-code gaps

These findings were confirmed by source inspection of the audit branch. They remain **unfixed** unless a later implementation commit explicitly addresses them.

### P0 — Block release until corrected

#### A. Rider authorization still accepts legacy fleet riders
**File:** `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `guardRiderWithLogistics()`

The guard permits either a vendor relationship or a logistics-company relationship. It must instead require an active user, active rider profile, exactly one active owning vendor, and no ambiguous simultaneous fleet ownership for new work. Preserve legacy fields/history, but deny fleet-only/ambiguous profiles new delivery actions.

#### B. Rider APIs expose customer and financial information
**File:** `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `formatOrderForRider()`, `dashboard()`, `history()`, `activeReturns()`

Current rider projections include `payment_status`, `delivery_fee`, raw customer phone, and in active returns `customer_phone` and `total_refundable_amount`. The doorstep-handover response also returns `accepted_amount`, `rejected_amount`, and `wallet_credited`. Remove all prohibited financial fields and raw phone data from rider JSON, rendered HTML, embedded state, notifications, logs, analytics, exports, and errors. Keep only minimum operational data and customer name/address plus a protected Call Customer action.

Implement the protected contact action without placing the phone number in rider API payloads or page markup. Prefer a server-mediated call relay or equivalent privacy-preserving mechanism, with authorization to an assigned job and audit logging that does not log the raw number.

#### C. Rider-triggered failed delivery creates financial outcomes
**File:** `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `recordDeliveryAttemptFailed()`

**Partial remediation (audit branch):** the handler now records the attempt and return case without creating a `CustomerWalletCredit` or `logistics_payable` entry; regression coverage is being added to CI. Still open: the threshold and return-case transition are not yet proven atomic under concurrent requests, and rider ownership/privacy guards remain open. Refunds and vendor recovery must go through the independently authorized, idempotent financial workflow after policy review.

#### D. Rider-triggered handover mixes operational evidence with money movement
**File:** `backend/app/Services/DoorstepHandshakeService.php`; response in `RiderTerminalApiController::doorstepHandover()`

**Partial remediation (audit branch):** the handover service no longer creates a `CustomerWalletCredit`; it creates a return case and provisional refund reservation for rejected merchandise, and the rider response labels the amount as a refund request rather than a wallet credit. Still open: the rider-triggered service writes merchandise/cashback/platform/delivery-fee journal entries and returns accepted/rejected monetary amounts to the rider. Separate operational proof/item acceptance from financial approval/posting and remove financial amounts from rider payloads. A separately authorized financial engine may consume verified events after policy checks, idempotency, refund-ceiling checks, and audit recording.

#### E. Rider actions can act on unassigned orders
**File:** `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`

Several guards reject an order only if it is assigned to a *different non-null* rider; an order with `rider_id = null` can therefore pass that particular check. Every rider mutation must require exact assignment to the authenticated rider and same vendor. Use `rider_id === authenticated rider profile id`, not “null or me”.

### P1 — Required for policy-compliant operation

#### F. Assignment lacks atomic custody/state enforcement
**File:** `backend/app/Http/Controllers/Api/VendorTerminalApiController.php`, `assignRiderToOrder()`

The current method checks order/vendor and rider/vendor match and rider active status, then assigns without a transaction/row lock, order-state validation, fulfilment-type validation, custody check, vendor/user status check for the rider, or assignment audit event. Add an atomic state-checked assignment with audit metadata (actor, previous rider, new rider, timestamp, reason/state). Reject `in_shop_pickup`, non-doorstep orders, ineligible riders, and states outside approved ready-for-assignment states. Prevent reassignment after custody unless the return/custody-transfer process completes.

#### G. Rider deactivation can ignore parcel custody
**File:** `backend/app/Http/Controllers/Api/VendorTerminalApiController.php`, `updateRiderStatus()`

The current method toggles `is_active` without checking whether the rider has a parcel in custody. Block deactivation while the rider owns active custody; preserve history and require controlled return/transfer before deactivation.

#### H. Checkout accepts legacy rider/fleet identifiers
**File:** `backend/app/Http/Controllers/Api/CheckoutAndReservationApiController.php`, `createIntent()`

The customer checkout request still validates `rider_id` and `logistics_company_id`, and includes legacy logistics/rider eligibility checks. Rider assignment belongs to the vendor after checkout, not the customer. Remove these inputs from the new checkout API contract and ensure no customer-supplied rider/fleet ID can influence order assignment. Preserve old order fields for historical reads and migrations.

#### I. Pickup pass and rider parcel custody are conflated
**Files:** `backend/app/Models/PickupReservation.php`, `backend/app/Models/Order.php`, `backend/app/Http/Controllers/Api/CheckoutAndReservationApiController.php`, rider terminal routes/services.

`PickupReservation::generateCode()` generates an `RES-` reference, while pickup-reservation payment creates an order with a six-digit `pickup_verification_code` and returns both values. Rider terminal routes also use “pickup OTP” for a rider collecting a parcel from the vendor. Use both the canonical `RES-XXXXXX` reservation pass and the one-time customer pickup OTP at the vendor counter. The OTP supplements rather than replaces the pass. Keep rider-to-vendor parcel custody as a separate operational handshake with its own status and evidence. Never expose either customer verification secret in rider list/history payloads.

#### J. Rider onboarding needs safer credential handling and unique identity
**File:** `backend/app/Http/Controllers/Api/VendorTerminalApiController.php`, `storeRider()`

The vendor creates a rider user and sets the user status active immediately, accepting a password in the request. Keep vendor-led onboarding, but review whether credentials should be established through a one-time invitation/password setup rather than a vendor-selected rider password. Enforce one rider profile per user and ensure vendor identity always comes from the authenticated vendor context. Do not add central dispatch approval; platform account/safety suspension remains allowed.

#### K. Legacy logistics and rider financial surfaces remain active
**Areas:** web routes/controllers/views, logistics-company models, rider UI, payout services, API routes, jobs, and reports.

Disable legacy fleet roles from new order assignment and new financial activity. Remove rider earnings/wallet/payout views and any new logistics-company payout path for vendor-managed orders. Preserve historical records and authorized read-only historical reporting. Do not drop legacy tables/columns until dependency mapping confirms safe retirement.

#### L. Failed-delivery attempts are not concurrency-safe
**File:** `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `recordDeliveryAttemptFailed()`

Attempt count, threshold evaluation, order state, return case, and custody are updated without one encompassing locked transaction. Concurrent attempts can exceed or race around the threshold or duplicate return cases. Use a locked transaction, idempotency key/event identity, and one authoritative transition to return-to-vendor after the configured threshold.

#### M. COD is disabled at launch
**Areas:** checkout/payment methods, rider views/API, vendor collection and reconciliation workflows.

Doorstep COD is disabled. Customers may pay before delivery through Victorious MARKET, or reserve in-shop pickup through Victorious MARKET and pay at the approved vendor shop through Victorious MARKET's authorised payment workflow. No off-platform payment is allowed. Launch rails are Paystack card and standard bank transfer unless separately approved. Riders must never see payment status/amount due or collect/reconcile customer money through platform rider accounts.

## 4. Pickup, supplier linkage and central-delivery invariants

- Supplier-shop pickup remains supported; it does not create a public vendor storefront.
- The order item retains the exact supplier offer and purchase-cost snapshot chosen at confirmation.
- Customer pickup requires the existing reservation pass/reference, separate one-time customer pickup verification, and in-shop photo evidence.
- Doorstep delivery requires a VM-authorized logistics-company assignment and a rider linked to exactly one active logistics company.
- Customer doorstep handover requires server-verified customer OTP and doorstep photo; carrier custody evidence is not customer handover proof.
- No supplier payable eligibility before verified customer handover and reconciliation.
- No duplicate supplier payable/payment from repeated callbacks, retries or status events.
- No use of old vendor earning/payout splits as supplier purchase cost or logistics-company payment rules.
- Coverage and delivery fee are VM-controlled and must fail closed when no valid current rule exists.
- Failed delivery, customer refusal, supplier fault, refund approval and supplier debt are separate states/decisions.

## 5. Required regression and security tests

1. Vendor A cannot list, create, activate, assign, or reassign Vendor B’s riders/orders.
2. Fleet-only, ownerless, inactive-vendor, inactive-user, and dual-linked rider profiles cannot act on new work.
3. Rider API list/detail/history/returns and rendered rider UI contain no raw customer phone, payment status, total/amount due, delivery fee financial field, card/bank details, payment reference, refund amount, cashback, commission, vendor settlement, payout or ledger data.
4. Protected Call Customer works for the authenticated rider’s assigned order without disclosing the number in JSON, HTML, logs, or analytics.
5. Rider cannot mutate an order unless `order.rider_id` exactly matches their rider profile and order vendor matches their active vendor.
6. Two concurrent assignment requests cannot leave two active riders; assignment after custody is rejected; deactivation is blocked during custody.
7. Failed-delivery event records evidence and custody only; concurrent duplicate attempts do not create duplicate return cases or financial entries.
8. Rider handover records operational evidence only. No rider-triggered wallet credit, financial journal, rider/logistics payable, vendor-fund release, or financial amount is returned to the rider.
9. Pickup `in_shop_pickup` is zero-fee, requires the `RES-XXXXXX` reservation pass AND one-time customer pickup OTP plus an in-shop pickup photo at the vendor counter, requires no rider, and cannot be fulfilled through rider doorstep endpoints.
10. Multi-vendor checkout keeps fees, assignments, and allocations isolated by child vendor order.
11. COD is rejected across all launch checkout modes and legacy paths; a future release requires separate explicit owner approval and passing vendor-owned collection/reconciliation controls.
12. Historical logistics orders and financial records remain readable to authorized auditors, but legacy fleet roles cannot receive new work.
13. Merchandise 90/5/5 and delivery-fee 90/10 allocations balance and refund correctly without any rider beneficiary.
14. Merchandise earnings are traceable to each vendor order-item line and delivery earnings separately to each vendor child order; partial item/fee refunds reverse only the correct source earning.
15. Vendor cannot choose a payout amount; server calculates strict FIFO batch of whole earning records, stops before exceeding ₦500,000, never splits/skips, and rejects overlapping requests.
16. Admin approval precedes payment initiation; pending-review cannot call Paystack; only verified transfer success marks the snapshotted earnings paid.

## 6. Implementation order

1. **P0 privacy/financial containment:** remove rider financial fields/raw phone; exact-assignment authorization; block rider-triggered financial posting; enforce vendor-only rider guard.
2. **P1 lifecycle integrity:** atomic assignment/custody, rider deactivation guard, failed-attempt transaction/idempotency, pickup code/status reconciliation.
3. **P1 checkout and legacy isolation:** remove customer-supplied rider/fleet IDs; disable new fleet assignment/payout routes; remove rider earnings and financial screens; keep legacy read-only access.
4. **P2 policy and verification:** confirm credential onboarding; prove COD is disabled; update API contracts, UI, notifications, and remaining docs.
5. Run targeted tests, then the complete backend suite and relevant frontend/mobile tests. Record actual results; do not describe the feature as implemented until checks pass.

## 7. Release controls

- This report does not claim the implementation gaps have been fixed.
- No destructive removal of legacy logistics data before dependency mapping and historical reconciliation.
- Do not merge to main without explicit owner approval.
- Business-rule changes require owner approval and matching updates to documentation, APIs, implementation, tests, and customer/vendor-facing policies.

## 8. Detailed contradiction register and implementation acceptance

This table is a compatibility gate for the new retailer model. Historical source findings may mention vendor models; new work must follow the current owner-confirmed policy.

| Area | Current binding rule | Source-code work still required |
|---|---|---|
| Supplier/catalogue | Supplier offers remain private; VM approves new canonical listings or maps offers to existing listings. | Add/validate supplier offer records, approval lifecycle, canonical product matching and duplicate prevention. |
| Supplier/order linkage | Each order item snapshots the selected supplier offer, purchase cost, product/variant, quantity and terms. | Map existing product/shop/vendor IDs to supplier identities without losing foreign keys, historical order data or journals. |
| Rider ownership | Every rider assigned to new VM work must be active and linked to exactly one active logistics company. | Enforce company/rider active status and exclusive linkage at registration, assignment and custody transitions; reject supplier/vendor-linked riders. |
| Assignment | VM or an authorized operations actor assigns a covered order to an approved logistics company and eligible rider. | Atomic row-locked assignment, allowed-state and destination-coverage checks, audit trail and exact assignment enforcement on every rider mutation. |
| Customer privacy | Riders see only minimum information needed for their assigned delivery, using a protected contact action. | Inspect serializers, HTML, logs, exports and notifications; remove raw phone, payment/refund data, supplier wholesale cost and pickup secrets. |
| Financial boundary | Riders and logistics companies cannot approve refunds, change prices, mark customer payment successful or create supplier payables. | Separate operational handover events from authorized financial decisions; remove direct wallet/refund/journal side effects from rider routes. |
| Failed delivery | Record attempts, reason, custody and return-to-origin separately. | Locked/idempotent attempt tracking; no duplicate return case, automatic refund, supplier debt or wallet credit. |
| Pickup | Customer pickup requires reservation pass, separate one-time customer pickup OTP and in-shop photo; no rider assignment for counter pickup. | Keep customer pickup secrets separate from rider-to-logistics-company custody; enforce authorized location, proof and replay prevention. |
| Central coverage/fees | VM controls coverage and customer delivery-fee quotes from Uyo; no coverage/fee rule means no delivery promise. | Retire supplier/vendor-configured delivery rates for new orders; version/snapshot destination, quote and approved logistics rate/terms. |
| Supplier payment | Eligibility only after verified successful customer delivery or confirmed pickup and reconciliation. | Add immutable supplier payable/payment states and idempotent attempts; stop marketplace vendor earning/payout code from generating new supplier payables. |
| Refunds | VM remains responsible for approved customer refunds; component and capture ceilings, original-route rules and authorization remain. | Test concurrency, partial quantities, discounts, zero-fee pickup, duplicate/out-of-order webhooks, timeout and reconciliation. |
| Historical records | Preserve vendor payouts, cashback, recovery, logistics and journals as historical records. | Map all dependencies and accounting treatment before migration; no global rename/delete or silent reclassification. |

### Cross-document contradictions checked

- The canonical retailer/supplier document, root README, root and backend product overview, system rules, business rules, payment policy and delivery plan now describe the central retailer model.
- Older implementation notes and source observations are historical evidence, not current business policy. Do not implement vendor-managed delivery, supplier-configured marketplace fees, vendor earnings or old 90/5/5 and 90/10 splits for new retailer transactions.
- Pickup pass, customer pickup OTP and rider-to-logistics-company custody credentials must remain separate.
- A protected Call Customer action must not be implemented by returning a hidden or masked raw phone number to the rider.
- Operational events may be inputs to a separately authorized financial service only where the current policy requires them; a rider endpoint cannot create financial postings or return financial results.
- Existing logistics-company models may be reusable for approved delivery partners, but all assignments must enforce active company status and exactly one active company per rider; historical fleet records alone do not authorize new work.

## 9. Additional financial-policy decisions and code audit (20 October 2026)

The owner has now explicitly decided:

- **Refund responsibility: A.** Victorious MARKET owns the customer refund workflow and executes the approved refund. It must not make the customer chase the vendor for a marketplace refund. Where vendor responsibility is established, the platform separately recovers the vendor-funded portion using the actual child-order allocation and a recorded reason/decision.
- **Vendor settlement timing: B.** Vendor proceeds are held until verified successful customer delivery or confirmed customer in-shop pickup. This is the earliest eligibility trigger; the configured refund window, open disputes/returns, vendor/account restrictions, bank-account security cooldown, authorization, and transfer-proof controls still apply. Settlement must be evaluated per vendor child order.

### Confirmed implementation gaps for these decisions

1. **New payment capture still creates legacy logistics-company payables.** In `backend/app/Services/OrderSettlementService.php::settlePaystackOrder`, the child-order and single-order paths create `logistics_payable` credits from the delivery-fee share and use logistics-company beneficiaries. This contradicts vendor-managed delivery and the approved 90% vendor / 10% platform delivery-fee allocation. Replace new-order delivery allocation with the owning vendor's delivery-fee payable and the platform's 10% revenue, scoped to each child order. Keep old fleet entries historical and auditable.
2. **Delivery confirmation still pays a logistics-company wallet.** `verifyCustomerDeliveryOtp()` credits a logistics-company corporate wallet and writes `logistics_payable` / `logistics_company_wallet` journal entries. This is an active fleet payout path triggered by delivery confirmation. Remove it from new vendor-managed orders; a rider delivery event must not generate rider/fleet compensation. Vendor delivery-fee proceeds remain vendor proceeds and vendor-to-rider compensation is outside platform systems.
3. **Vendor settlement has a partial timing gate but needs fulfilment-type and child-order proof.** `verifyCustomerDeliveryOtp()` sets the order to `delivered`, sets `vendor_settlement_status = held`, and starts the configured refund window. `evaluateMaturation()` only advances fulfilled orders after the return window expires and blocks active disputes/returns; `disburseVendorSettlement()` then requires eligible status, active vendor, bank cooldown clearance, and proof. Preserve these controls, but verify the same policy for the owning vendor's `in_shop_pickup` confirmation and every child order. Parent payment capture or a rider's operational status update must never release vendor funds.
4. **Vendor recovery exists in one refund path but is not unified across refund paths.** `ReturnAndRefundService::adminDisburseRefund()` creates `VendorRecoveryLedger` and a `vendor_recovery_receivable` journal entry when a return-case order is already `settled`; otherwise it debits `vendor_payable`. However, `FinancialOperationService::finalizeRefundConfirmation()`—the Paystack webhook/reconciliation confirmation path—does not create or reconcile that recovery ledger. Its return-case branch debits `vendor_payable` using a fixed 90/5/5 split without first determining whether proceeds remain held, have already been paid, or differ due to discounts/line-item allocations; its non-return/cancellation branch posts the cash outflow without explicit vendor recovery. The result is two inconsistent financial paths: a post-settlement refund may be handled differently depending on which service confirms it. Unify both paths behind one idempotent financial finalizer that uses actual allocation, offsets held vendor proceeds where authorized, and creates/reconciles a recovery receivable when proceeds were already disbursed.
5. **No verified customer-refund ownership contract is enforced end-to-end by the delivery flows.** Rider handover and failed-delivery paths currently mix operational evidence with wallet-credit/refund-reservation/journal work (see the P0 findings above). Keep the platform as refund operator, but make the rider only an evidence source; refund decision and execution belong to the authorized return/refund workflow.
6. **Parent and child financial allocations must not be double-counted.** Existing payment settlement creates some delivery-fee entries at both child and parent levels. During the change, map exactly which order owns each captured fee and journal event, and assert that each delivery fee is allocated once to vendor (90%) and platform (10%). Do not fix the issue by deleting legacy entries or silently reclassifying posted historical entries.

7. **Vendor payout request currently permits partial withdrawal and combines approval with payment.** The vendor request action accepts a client-provided amount and checks only that it does not exceed withdrawable balance; an existing test explicitly allows a request for ₦12,000 from a ₦20,000 withdrawable balance. The admin disbursement route combines approval with transfer initiation. This conflicts with the owner's rule: no partial withdrawal, admin approval before payout, and admin pays after approval. Compute the entire current withdrawable balance server-side under locks, reject partial/mismatched/zero requests, reserve every included earning record atomically, and reject overlapping requests. Add distinct admin approve/reject and approved-payout initiation transitions; never call Paystack from pending-review state.

8. **Payout source of truth is not item-level.** Current balance and payout allocation models are order-based (including vendor_net_90 and VendorPayoutAllocation keyed by order_id), so they cannot independently identify each merchandise line's vendor earning or distinguish it from the delivery-fee earning. Add an immutable vendor earning ledger with one merchandise earning per vendor order-item line and a separate delivery earning linked to the vendor child order. Persist exact post-discount line allocation in kobo, product/order-line snapshot, vendor/parent/child/payment references, status, event/idempotency identity, payout reservation, refund/recovery links, and journal references. Derive totals from these records. Do not guess historical line allocations; preserve legacy aggregate records when reliable line snapshots are unavailable.

9. **Payout lifecycle must reconcile earning records.** A payout request snapshots exact earning IDs and amounts. Success marks only those records paid once; rejection or provider-confirmed non-execution restores the same records once; timeout holds them for reconciliation; reversal reverses the original journal only if it exists and adjusts the same earning records after provider-state verification. Protect confirmed success from late failure. No rider/logistics beneficiary is allowed for new orders.

### Required implementation work

- Add a single authoritative fulfilment gate for settlement eligibility: `doorstep_delivery` requires verified customer handover; `in_shop_pickup` requires owning-vendor counter verification of the `RES-XXXXXX` reservation pass AND separate customer pickup OTP plus in-shop pickup photo. Both must record actor, timestamp, order/child vendor, and evidence; neither can be simulated by a rider pickup/custody event.
- Rework new-order settlement journals to credit the correct vendor payable for the 90% delivery-fee allocation and platform revenue for 10%, once per vendor child order; do not create logistics-company or rider beneficiaries.
- Keep vendor funds held until fulfilment is confirmed, the configured refund/return window has expired, no blocking dispute/return remains, the vendor is eligible, and payout security controls pass. Test single-vendor, multi-vendor, doorstep, and counter-pickup orders independently.
- Extend the refund financial model to distinguish customer refund execution from vendor recovery. On refund confirmation, reverse only the actual approved components and actual original allocations. If proceeds remain held, offset the appropriate vendor payable. If already paid, record a vendor recovery receivable/liability and collect via an authorized future settlement offset or approved recovery process. Do not mark customer refund success based on recovery success/failure.
- Make recovery idempotent and auditable: store responsible vendor, approved reason, amount, order/child reference, whether payable was held or already disbursed, recovery journal/receivable reference, offsets, and outstanding balance. Do not create rider compensation or expose any of this to riders.
- Add tests for no settlement before delivery/pickup; delivery/pickup gates each child order independently; open return/dispute blocks settlement; zero-fee pickup creates no delivery allocation; new order creates no logistics payable/wallet credit; refund before vendor payout offsets held payable; refund after vendor payout creates a recovery receivable; repeated refund/recovery events do not duplicate entries; multi-vendor refunds recover only the responsible vendor's actual share.

**Important qualification:** `vendor_recovery_ledgers` and `VendorRecoveryLedger` already exist, and the manual return/refund service uses them for post-settlement recovery. The gap is not absence of all recovery infrastructure; it is inconsistent integration/idempotency across the manual and Paystack webhook/reconciliation refund paths, plus fixed allocation assumptions that must be reconciled with actual order values.

### Verification status

Documentation review is not implementation verification. The audit findings above remain open until code changes and targeted regression tests pass. Record the actual CI run and full-suite result after implementation; do not infer success from the policy text.


## 10. Current payment, refund, supplier payment and logistics policy (21 October 2026)

The current business contract is the retailer model, not the former marketplace/vendor-managed delivery policy. Detailed gateway state transitions remain in PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md.

### Binding decisions

1. **Payment channels:** Paystack card and standard bank transfer at launch unless separately approved. COD and off-platform supplier payments are disabled.
2. **Supplier linkage:** Each order item must preserve the selected supplier offer and purchase-cost snapshot. A supplier cannot directly publish a public listing or alter VM's retail price.
3. **Central delivery:** VM operates from Uyo and serves destinations supported by current sourcing and approved logistics arrangements. Every assigned rider must be active and linked to exactly one active logistics company.
4. **Pickup:** Authorized supplier-shop pickup uses the existing reservation pass, separate one-time customer pickup verification and in-shop photo. Supplier readiness is not pickup completion.
5. **Supplier payment:** Eligibility begins only after verified successful customer delivery or confirmed pickup and reconciliation. It is not automatic transfer. No vendor payout threshold/FIFO rule is carried over as supplier payment policy.
6. **Refunds:** VM remains responsible for valid approved customer refunds through the verified original Paystack route where supported. Refund request, approval, dispatch and provider confirmation are distinct. A customer refund must not wait for supplier recovery.
7. **Supplier recovery:** Evidence-backed supplier responsibility and contractual recoverability are required. A complaint alone is not debt. Never pretend a completed supplier transfer was reversed.
8. **Retail accounting:** The former 90/5/5 merchandise and 90/10 vendor delivery splits are not default retailer rules. Keep customer revenue, supplier purchase cost, delivery charges, logistics-company charges, fees, refunds, any approved cashback and expenses separate. Obtain owner/accountant approval for whether new cashback continues and for logistics/company payment terms.
9. **Cashback:** Preserve historical lots and obligations. Do not accrue new 5% cashback automatically under the retailer model until funding/accounting rules are explicitly approved.
10. **Implementation status:** Policy is not proof of code compliance. Legacy web checkout, wallet tender/refund branches, supplier offer linkage, rider-company assignment, delivery coverage and supplier-payment idempotency remain audit targets.

### Prioritized implementation checklist

| Priority | Work area | Required change / acceptance condition |
|---|---|---|
| P0 | Checkout routes and CheckoutExecutionService | Close the known legacy web checkout bypass; enforce consistent idempotency, payment verification and supplier offer/purchase-cost snapshots on every relevant checkout path. |
| P0 | Supplier offer/catalogue | Implement review/approval and match offers to existing canonical products without duplicate listings. |
| P0 | Logistics assignment | Require approved active logistics company and rider linked to exactly one active company; block supplier/vendor-linked riders from new VM work. |
| P0 | Pickup/handover | Keep reservation pass, customer pickup OTP and rider custody credentials separate; require correct photo and server-side verification. |
| P0 | Supplier payable/payment | Eligibility only after verified customer handover and reconciliation; payment attempts idempotent and provider-reconciled; no old vendor payout splits. |
| P0 | Customer refunds | Preserve original-route, authorization, component isolation, aggregate capture ceilings, failure/needs-attention handling and reconciliation. |
| P1 | Coverage/fees | Define Uyo-origin coverage, VM customer fee table, approved logistics-company rate cards and unsupported-route behavior. |
| P1 | Cashback | Preserve historical obligations; approve new accrual/funding policy before enabling retailer-model cashback. |
| P1 | Legal and accounting | Review published Terms, supplier agreements, logistics agreements, taxes, delivery margin and refund/warranty wording. |
| P2 | Tests/CI | Cover supplier matching, order snapshots, checkout replay, logistics assignment, customer OTP/photo, pickup, supplier payment, refunds, duplicate events, timeouts, and historical ledger migration. |

Subscribe to and handle the required Paystack events for enabled products, including charge.success, bank.transfer.rejected, refund lifecycle events, dispute events and transfer outcomes where applicable. Each event requires signature validation, exact identity/amount/currency matching, idempotent state transitions, audit logging and reconciliation. Do not treat an event as refund approval or as proof of supplier payment without matching the authorized operation.

## 11. Final cross-document reconciliation and launch gate (20 October 2026)

The following are the single aligned owner-approved decisions in all three policy documents. If older copy, UI text, an API contract, or implementation behavior conflicts, follow these decisions and update that artifact; do not silently change the business model.

| Topic | Binding rule | Acceptance evidence required |
|---|---|---|
| Multi-vendor checkout | One parent checkout/Paystack capture; separate child order per vendor, with immutable allocation and independent fulfilment, delivery fee, refund ceiling and settlement gate | Parent captured amount equals sum of child allocations exactly; child states/fees/refunds do not leak across vendors |
| Payment channels | Paystack card + standard bank transfer only; COD disabled everywhere; DVA, USSD and mobile money deferred | UI, API, server configuration and legacy paths reject disabled methods; both enabled channels pass authoritative verification |
| Refund destination/approval | Initiate customer refunds through Paystack against the verified original transaction; wallet refund disabled. If `refund.needs-attention` occurs, keep the obligation open and require authorized review—no wallet/off-platform refund or alternate bank destination unless separately owner-approved. Automatic approval only for verified duplicate capture or verified pre-vendor-action cancellation; all other refunds require persisted human approval | Service-level authorization, approval actor/time/reason/amount/allocation snapshot, safe needs-attention handling, and tests proving a webhook alone cannot approve or dispatch |
| Refund ceilings | Enforce both child-order allocation ceiling and aggregate parent capture ceiling under locks, counting confirmed refunds plus active reservations; idempotency key must bind to the exact instruction | Concurrency tests across multiple child orders and partial refunds; same-key exact retry succeeds; changed amount/order/reason is rejected |
| Protection/cashback | 24-hour return/refund/protection window from verified fulfilment; cashback earliest maturity is `refund_window_expires_at + 24 hours` (`+ 1 day`) and only when no blocking case remains. The extra buffer does not extend the customer claim window or promise gateway refund timing; recoverable spent cashback offsets future cashback only at maturity | Settings fallback/seed audited; maturity job uses actual expiry timestamp; recovery ledger prevents negative spendable wallet balance; T&C/legal review completed before release |
| Commercial split | Merchandise 90/5/5 on actual post-discount merchandise collected, excluding delivery; delivery 90/10; platform absorbs rounding remainder and Paystack fees; no rider payable | Immutable checkout snapshot, exact integer-kobo tests with discounts and fees, balanced journals and no logistics/rider beneficiary for new orders |
| Vendor-managed fulfilment | Vendor owns its riders and manual assignments. Rider sees only customer name, delivery address and protected Call Customer action; no customer financial data. In-shop pickup is zero-fee and uses `RES-XXXXXX` | Role/IDOR/privacy tests, no raw phone or financial data in rider payloads, no rider financial posting, counter pickup tests, per-child settlement proof |
| Independent refund components and recovery | Merchandise and delivery-fee refunds require separate approvals, amounts, operations, reservations, gateway states and ledger reversals. Platform executes the authorized customer refund; vendor recovery is separate, evidence-based and authorized. A failed refund attempt leaves that component's customer obligation open | Component, child-order and aggregate parent-capture ceilings under locks; 90/5/5 merchandise versus 90/10 delivery reversals; sibling component state isolation; held vendor proceeds offset only where justified; paid-out vendor share creates a recovery receivable |
| Webhook subscriptions | `charge.success`, `bank.transfer.rejected`, `refund.pending`, `refund.processing`, `refund.needs-attention`, `refund.processed`, `refund.failed`, `charge.dispute.create`, `charge.dispute.remind`, `charge.dispute.resolve`, `transfer.success`, `transfer.failed`, `transfer.reversed` | Raw-body HMAC-SHA512 verification, immutable event payload hash, exact reference/amount/currency matching, idempotent locked transitions, unmatched/manual-review path and scheduled reconciliation |
| Release control | Documentation describes policy; it does not prove code compliance. Work stays on `audit/paystack-refund-webhook-safety` | Targeted regression suites and full backend/frontend checks pass; review records exact commit and CI URL. Do not merge `main` without explicit owner approval |

### Implementation order after policy reconciliation

1. **P0 — Money safety:** refund approval gate and launch wallet-refund block; exact parent/child allocation and cumulative refund ceilings; bank-transfer rejection handling; explicit refund lifecycle and charge-dispute handlers; no blind retry or duplicate refund.
2. **P0 — Delivery/privacy boundary:** remove rider-triggered wallet/journal/refund behavior and logistics-company payables from new flows; enforce exact vendor/rider ownership and assignment; remove raw phone and financial fields from rider API/UI; disable COD.
3. **P1 — Settlement and recovery:** gate settlement independently per child after verified delivery or vendor-counter pickup and expiry of the 24-hour return/refund window, with no blocking case; the extra 24-hour buffer applies to cashback maturity only. Unify refund finalization and vendor recovery using actual immutable allocations; remove duplicate parent/child delivery-fee accounting.
4. **P1 — Cashback and fee proof:** maturity-based recovery offset, settings/seed audit, customer terms and legal review, and integer-kobo split tests.
5. **Release evidence:** run focused tests for each acceptance row above, then full backend/frontend suites. Report actual outcomes. No merge to `main` without explicit approval.

### Status clarification

The latest documentation edits align policy; they do **not** close the previously recorded source-code findings. Until implementation and tests prove each acceptance condition, the corresponding item remains open and the marketplace is not release-ready for that behavior.

## 12. Owner-confirmed rule: independent merchandise and delivery-fee refunds

**Do not combine merchandise and delivery into one refund approval.** A single customer case can contain both, but each component requires its own decision, amount, authorization record, financial operation, reservation, gateway reconciliation, and ledger reversal.

- Merchandise refund: scope to the vendor child order and item/order-line quantities. Calculate from immutable post-discount allocations and reverse only the related 90/5/5 merchandise/cashback/platform amounts.
- Delivery-fee refund: scope to the delivery fee actually captured for that child order. Decide independently using reason/evidence/policy, and reverse only the actual 90/10 vendor/platform delivery allocation. It must not contain merchandise value or cashback.
- A merchandise approval must not implicitly approve delivery. A delivery approval must not implicitly approve merchandise. Either component may be denied or remain pending while the other succeeds.
- Use separate component-specific idempotent operations tied to the same original parent Paystack capture and exact child order. Separate refund decisions do not create a new payment or change the original-channel-only launch rule.
- Enforce component remaining balance, child-order refund ceiling and aggregate parent-capture ceiling under locks, including active reservations and confirmed refunds from all components and vendors.
- A zero-fee in_shop_pickup order cannot have a delivery-fee refund. Do not infer a refund from failed delivery alone.
- Webhooks and reconciliation must match the exact provider refund identity and operation. A component's refund.processed/refund.failed event cannot change a sibling component. Uncertain/failed gateway outcome leaves that component open for reconciliation.
- Vendor recovery and cashback recovery remain separate linked accounting operations; neither can silently alter the approval or gateway status of the other refund component.
- Keep shared provenance without collapsing states: both component decisions reference the original parent capture and child-order allocation history, but retain independent approvals, reservations, gateway outcomes and audit records. Only confirmed Paystack cash refunds plus active cash-refund reservations count against the shared parent-capture cash-refund ceiling.
- Cashback reversal/recovery must link to the originating merchandise item/refund and use the cashback ledger. It is not a second Paystack refund and must not be counted again against the parent-capture cash-refund ceiling. Cashback maturity and recovery have separate lifecycle states and timing, but must reconcile back to the same source order/item/refund.

### Audit findings and required implementation work

1. Inspect ReturnAndRefundService, FinancialOperationService::createRefundOperation(), refund approval endpoints, return-case/item acceptance flows, Paystack refund dispatch, webhook matching, reservations, journal posting and customer/vendor status screens for any shared refund total that combines merchandise and delivery.
2. Add an explicit server-validated component/scope to refund request and operation metadata or normalized fields; preserve immutable item IDs, quantities, captured delivery fee, post-discount allocation and approval evidence.
3. Replace any one-click approve-total-refund behavior with separately authorized merchandise and delivery decisions. If the UI offers approve-both, it must record two explicit component decisions, two amounts, and two operations—not one implicit blanket approval.
4. Keep component reservations isolated while also enforcing a shared parent-capture cap. Locks must serialize concurrent requests across child orders; each operation must reserve only its component amount.
5. Update customer/admin/vendor views and notifications so each component's request, approval, denial, gateway state and confirmed amount are shown independently. Riders must not see any of these financial fields.
6. Test merchandise-only approval, delivery-only approval, one approved/one rejected, one pending while the other confirms, partial item quantities, discounts, pickup zero fee, multi-vendor carts, same-key conflicts, duplicate/out-of-order webhooks, concurrent reservations and correct journal reversals.
7. Treat this as a code audit requirement, not a completed fix. Record files changed, tests executed, CI run and remaining gaps; keep all work on audit/paystack-refund-webhook-safety.
