# Victorious MARKET — Retailer and Central Fulfilment Compatibility Audit

> **Superseding owner decision (21 October 2026):** VM is a retailer operating from Uyo. Suppliers are private product sources linked to approved offers and order items, not public marketplace sellers. VM owns delivery coordination through approved logistics companies; every assigned rider must be linked to exactly one active logistics company. Authorized supplier-shop pickup remains available with the existing reservation pass, separate one-time customer pickup verification and in-shop photo. Supplier payment eligibility begins only after verified successful customer delivery or pickup and reconciliation. Marketplace commission/vendor payout rules and vendor-managed delivery are legacy assumptions for new work. This audit records code compatibility gaps; it does not claim migration is complete. See [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md).


**Review date:** 20 October 2026  
**Current documentation branch:** `docs/retailer-central-fulfilment-model` (based on `audit/paystack-refund-webhook-safety`)  
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

### Claim window, refund processing, supplier payment and cashback are separate

These are separate states and clocks. Never substitute one for another.

1. **Customer claim/return window — 24 hours.** For ordinary post-fulfilment claims, start the standard 24-hour window from verified customer delivery or confirmed pickup. Use the persisted fulfilment timestamp/expiry; do not calculate from checkout creation, payment time, or when a background job happens to run. An open return, dispute, delivery exception or unverified pickup remains under the relevant review rule.
2. **Consumer-rights safeguard.** The 24-hour standard is an operational claim/reporting window, not a blanket waiver of rights that cannot lawfully be excluded. Applicable Nigerian law and valid warranty obligations prevail where they provide a stronger remedy. Have customer-facing Terms reviewed by qualified Nigerian counsel before launch.
3. **Gateway refund lifecycle — independent of the claim window.** Request, approval, Paystack submission, provider processing and provider-confirmed refund are distinct states. The 24-hour claim window is not a promise that Paystack completes a refund within 24 hours. A timeout, refund.failed or refund.needs-attention does not satisfy the customer refund obligation; keep it open for reconciliation/review. Refund through the verified original Paystack transaction where supported; wallet-credit refunds remain disabled at launch. Automatic approval remains limited to the documented narrow exceptions.
4. **Supplier payment eligibility.** Eligibility begins only after verified successful customer delivery or confirmed customer pickup and required order/amount reconciliation. Do not impose the former vendor settlement 24-hour hold as a supplier payment rule unless the owner separately approves it. Eligibility is not a guarantee of immediate transfer: supplier terms, authorization, provider confirmation and reconciliation remain separate. A timeout is uncertain and must be reconciled before retry.
5. **Supplier recovery.** A customer refund after supplier payment does not automatically establish supplier debt. VM remains responsible for a valid customer refund. Record a supplier receivable only where evidence establishes supplier responsibility and the supplier agreement permits recovery. A complaint alone is not debt, and supplier recovery must not delay the customer refund.
6. **Cashback and historical obligations.** Preserve historical cashback lots and customer obligations. The former 5% marketplace allocation is not authority to accrue new cashback on retailer-model sales. New accrual/funding/maturity rules require explicit owner/accountant approval. Any historical lot's existing maturity/expiry rules must be applied consistently until an approved migration policy changes them.
7. **Exceptions and aggregate refund ceiling.** Verified duplicate captures, qualifying pre-supplier-action cancellations, payment exceptions, chargebacks and non-waivable legal rights use their own evidence and authorization paths. Independent merchandise/delivery refund components must remain separately approved and reconciled while enforcing the aggregate original-capture ceiling.

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

The earlier numbered vendor-managed-delivery cases below are historical regression cases only where they exercise legacy records or code. They are not acceptance criteria for new retailer orders. New retailer acceptance requires VM-controlled assignment through an approved logistics company and rejects supplier/vendor-controlled dispatch.

1. Legacy vendor A cannot access or mutate another legacy vendor's historical riders/orders; new VM delivery assignments must instead be authorized by VM operations and the approved logistics-company relationship.
2. Fleet-only, ownerless, inactive-vendor, inactive-user, and dual-linked rider profiles cannot act on new work.
3. Rider API list/detail/history/returns and rendered rider UI contain no raw customer phone, payment status, total/amount due, delivery fee financial field, card/bank details, payment reference, refund amount, cashback, commission, vendor settlement, payout or ledger data.
4. Protected Call Customer works for the authenticated rider’s assigned order without disclosing the number in JSON, HTML, logs, or analytics.
5. Rider cannot mutate an order unless the assignment links that rider to the exact active logistics company and delivery/fulfilment unit; vendor ownership is not the authorization rule for new retailer delivery.
6. Two concurrent assignment requests cannot leave two active riders; assignment after custody is rejected; deactivation is blocked during custody.
7. Failed-delivery event records evidence and custody only; concurrent duplicate attempts do not create duplicate return cases or financial entries.
8. Rider handover records operational evidence only. No rider-triggered wallet credit, financial journal, rider/logistics payable, vendor-fund release, or financial amount is returned to the rider.
9. Pickup `in_shop_pickup` is zero-fee, requires the `RES-XXXXXX` reservation pass AND one-time customer pickup OTP plus an in-shop pickup photo at the vendor counter, requires no rider, and cannot be fulfilled through rider doorstep endpoints.
10. New retailer checkout must not depend on public multi-vendor child orders or vendor commission allocation. If legacy parent/child rows exist, preserve their historical integrity and prove that new orders use the retailer workflow.
11. COD is rejected across all launch checkout modes and legacy paths; a future release requires separate explicit owner approval and passing vendor-owned collection/reconciliation controls.
12. Historical logistics orders and financial records remain readable to authorized auditors, but legacy fleet roles cannot receive new work.
13. New retailer orders must not create 90/5/5 merchandise or 90/10 delivery allocations. Any legacy financial allocation regression test must be explicitly isolated to historical records and must not authorize those rules for new orders.
14. New supplier payables must trace to the immutable supplier offer/order-item cost snapshot and verified fulfilment. Logistics-company charges must trace to approved rate-card/agreement snapshots. Refunds must reverse the actual VM customer charge without applying legacy vendor earning allocations.
15. Legacy vendor payout code, if retained for historical read-only audit, must not create supplier payments or new retailer financial activity. New supplier payment amounts derive from the agreed purchase-cost snapshot and approved supplier terms, not vendor earning FIFO or historical payout thresholds.
16. Admin approval precedes payment initiation; pending-review cannot call Paystack; only verified transfer success marks the snapshotted earnings paid.

## 6. Implementation order

1. **P0 privacy/financial containment:** remove rider financial fields/raw phone; exact assignment authorization; block rider-triggered financial posting; enforce VM/logistics-company assignment rules for new work.
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

## 9. Legacy financial-policy code audit — reassess for retailer model

The observations below concern the legacy marketplace implementation. They are not instructions to preserve the old vendor commission or vendor-managed delivery model.

### Confirmed areas requiring source review

1. **Legacy settlement may create logistics-company financial entries.** Inspect OrderSettlementService and related journals. The retailer model requires approved logistics-company charges/payables under an explicit agreement; it must not create old marketplace delivery splits or rider beneficiaries by accident. Distinguish legitimate carrier charges from obsolete vendor/fleet allocation logic.
2. **Delivery confirmation may trigger financial side effects.** Inspect customer OTP verification, DoorstepHandshakeService and rider routes. Customer handover evidence may make a supplier payable eligible only after reconciliation; it must not directly create a payment, approve a refund or create wallet credit.
3. **Pickup and delivery need one authoritative fulfilment gate.** Doorstep requires server-verified customer OTP plus doorstep photo. Supplier-shop pickup requires reservation pass, separate one-time customer pickup OTP and in-shop photo. Do not let rider custody events satisfy customer pickup.
4. **Refund finalization and supplier recovery must be consistent.** Manual admin and webhook/reconciliation paths must use one idempotent financial finalizer based on the actual approved refund amount and source records. VM remains responsible for customer refund; supplier recovery is a separate evidence-backed, contractually recoverable receivable.
5. **Historical vendor payout code must not create new supplier payables.** Existing vendor_net_90, VendorPayoutAllocation, vendor recovery ledgers and payout services may reflect marketplace commission. Preserve them for history, but new retailer supplier costs require their own source offer/order-item linkage and accounting treatment.
6. **Legacy cashback and allocation code needs migration treatment.** Preserve historical cashback lots and obligations. Do not use the old 5% marketplace allocation to accrue cashback on new retailer sales until owner/accountant approval.
7. **Checkout and parent/child logic require re-evaluation.** The previous multi-vendor parent checkout requirement is no longer the business objective. Audit how the current checkout creates orders and payment references; adapt it to one VM customer transaction with private supplier offer links without breaking payment idempotency, refund ceilings, or historical order references.
8. **Source evidence and tests are required.** Record exact service/controller paths, state transitions, database constraints, regression tests and CI results. Documentation is not proof of implementation.

### Required implementation work

- Close the known legacy web checkout bypass of CheckoutExecutionService.
- Add/validate supplier offer approval and canonical product mapping.
- Snapshot selected supplier offer and agreed purchase cost on each accepted order item.
- Enforce approved logistics company plus exactly one active company link per rider for new delivery work.
- Implement VM-controlled Uyo-origin coverage and customer delivery fee rules; unsupported routes fail closed.
- Keep operational custody, customer handover, refund approval, gateway completion, supplier payable and supplier recovery separate.
- Retire/migrate legacy marketplace commissions, vendor payouts, vendor recovery and vendor-linked rider paths for new retailer transactions only after dependency mapping.
- Add tests for successful and failed pickup/delivery, supplier payable eligibility, duplicate payment/refund events, timeout reconciliation, partial refunds, historical ledger preservation and access-control boundaries.

**Verification status:** These are open audit requirements until code changes and targeted regression tests pass. Do not infer success from this policy text.

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

## 11. Final cross-document reconciliation and launch gate (21 October 2026)

| Topic | Binding rule | Acceptance evidence required |
|---|---|---|
| Retailer and supplier | Customers buy from VM. Supplier offers are private; VM approves new listings or maps offers to existing canonical listings. | New offers require approval; existing matching offers do not create duplicate listings; order items snapshot selected offer and purchase cost. |
| Customer payment | Paystack card and standard bank transfer at launch unless separately approved. Supplier-shop payment-at-location is allowed only through VM's authorized workflow. No off-platform supplier payment or doorstep COD. | Server-side verification, exact reference/amount/currency/customer binding, idempotency and legacy wallet-tender block. |
| Central delivery | VM operates from Uyo and serves only destinations supported by current sourcing and approved logistics coverage. | VM-owned coverage/fee rule, supported-route check and snapshot; unsupported route fails closed. |
| Rider assignment | Every rider assigned to new VM work is active and linked to exactly one active logistics company. | Tests reject unlinked, multiply linked, inactive, unapproved and supplier/vendor-linked riders; authorization is server-side. |
| Pickup/handover | Doorstep requires customer OTP plus doorstep photo. Supplier-shop pickup requires reservation pass, separate customer pickup OTP and in-shop photo. | Credential separation, replay prevention, no secret leakage, correct actor and timestamp. |
| Supplier payment | Eligible only after verified successful customer delivery or pickup and reconciliation. Eligibility is not automatic transfer. | No eligibility from order/payment/readiness/collection/assignment/attempt alone; duplicate events cannot duplicate payable or payment. |
| Retail accounting | Supplier purchase costs, customer revenue, delivery charges, logistics-company charges, gateway fees, refunds, any approved cashback and expenses are separate. Former 90/5/5 and 90/10 splits are not retailer defaults. | Approved chart of accounts/rate agreements; balanced journals, actual cash reconciliation, no duplicate postings or invented percentages. |
| Customer refund and recovery | VM remains responsible for approved customer refunds. Supplier recovery requires evidence-backed responsibility and contractual recoverability; never delays customer refund. | Separate idempotent refund and recovery records; complaint alone does not create debt; timeout requires reconciliation. |
| Historical data | Preserve marketplace payout, cashback, recovery, logistics and journal history. | Foreign keys, source references and ledger balances mapped; no global rename, deletion or silent reclassification. |
| Release control | Documentation describes policy, not implementation. | Code review, targeted regression and full suites pass; current owner approves merge/deploy. |

### Implementation order after policy reconciliation

1. **P0 — Checkout/payment safety:** close the legacy web checkout bypass; enforce idempotency, payment verification and supplier offer/cost snapshots.
2. **P0 — Central delivery/privacy:** enforce Uyo coverage, logistics-company approval, rider-to-company exclusivity, minimum rider data and separate custody/handover evidence.
3. **P0 — Supplier payment/refunds:** payable eligibility only after verified customer handover and reconciliation; idempotent payment attempt and provider reconciliation; preserve customer refund authorization and ceilings.
4. **P1 — Accounting and migration:** approve supplier purchase-cost, logistics-company, delivery-fee and cashback accounting; preserve historical vendor/cashback/payout records; retire obsolete marketplace allocation paths for new transactions.
5. **P1 — Legal and operational readiness:** review published legal documents, supplier agreements, logistics agreements, delivery coverage and customer support workflow.
6. **Release evidence:** run focused tests and full backend/frontend suites; record exact commit and CI outcomes. No merge/deploy without explicit owner approval.

### Status clarification

Documentation alignment does not close the previously recorded source-code findings. The application is not proven compatible with the retailer model until implementation and tests establish each acceptance condition.

## 12. Owner-confirmed rule: independent merchandise and delivery-fee refunds

**Do not combine merchandise and delivery into one refund approval.** A single customer case can contain both, but each component requires its own decision, amount, authorization record, financial operation, reservation, gateway reconciliation, and ledger reversal.

- Merchandise refund: scope to the VM order item(s) and quantities; calculate from the immutable captured amount and original price/discount snapshot. Do not apply the former 90/5/5 marketplace split.
- Delivery-fee refund: scope to the delivery fee actually captured for the VM order. Decide independently using reason, evidence, policy and applicable law. Account for only the approved captured delivery component under the approved retailer chart of accounts; do not apply the former 90/10 vendor/platform split.
- A merchandise approval must not implicitly approve delivery. A delivery approval must not implicitly approve merchandise. Either component may be denied or remain pending while the other succeeds.
- Use separate component-specific idempotent operations tied to the same original parent Paystack capture and exact child order. Separate refund decisions do not create a new payment or change the original-channel-only launch rule.
- Enforce component remaining balance, child-order refund ceiling and aggregate parent-capture ceiling under locks, including active reservations and confirmed refunds from all components and vendors.
- A zero-fee in_shop_pickup order cannot have a delivery-fee refund. Do not infer a refund from failed delivery alone.
- Webhooks and reconciliation must match the exact provider refund identity and operation. A component's refund.processed/refund.failed event cannot change a sibling component. Uncertain/failed gateway outcome leaves that component open for reconciliation.
- Supplier recovery and any cashback adjustment remain separate linked accounting operations; neither can silently alter the approval or gateway status of the other refund component.
- Keep shared provenance without collapsing states: both component decisions reference the original parent capture and child-order allocation history, but retain independent approvals, reservations, gateway outcomes and audit records. Only confirmed Paystack cash refunds plus active cash-refund reservations count against the shared parent-capture cash-refund ceiling.
- Historical cashback reversal/recovery must link to the originating item/refund and use the historical cashback ledger. It is not a second Paystack refund. New retailer-model cashback accrual remains subject to explicit owner/accountant approval.

### Audit findings and required implementation work

1. Inspect ReturnAndRefundService, FinancialOperationService::createRefundOperation(), refund approval endpoints, return-case/item acceptance flows, Paystack refund dispatch, webhook matching, reservations, journal posting and customer/vendor status screens for any shared refund total that combines merchandise and delivery.
2. Add an explicit server-validated component/scope to refund request and operation metadata or normalized fields; preserve immutable item IDs, quantities, captured delivery fee, post-discount allocation and approval evidence.
3. Replace any one-click approve-total-refund behavior with separately authorized merchandise and delivery decisions. If the UI offers approve-both, it must record two explicit component decisions, two amounts, and two operations—not one implicit blanket approval.
4. Keep component reservations isolated while also enforcing a shared parent-capture cap. Locks must serialize concurrent requests across child orders; each operation must reserve only its component amount.
5. Update customer/admin/vendor views and notifications so each component's request, approval, denial, gateway state and confirmed amount are shown independently. Riders must not see any of these financial fields.
6. Test merchandise-only approval, delivery-only approval, one approved/one rejected, one pending while the other confirms, partial item quantities, discounts, pickup zero fee, multi-vendor carts, same-key conflicts, duplicate/out-of-order webhooks, concurrent reservations and correct journal reversals.
7. Treat this as a code audit requirement, not a completed fix. Record files changed, tests executed, CI run and remaining gaps; keep all work on audit/paystack-refund-webhook-safety.
