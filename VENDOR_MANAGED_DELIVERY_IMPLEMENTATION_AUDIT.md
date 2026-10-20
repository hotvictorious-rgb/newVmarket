# Victorious MARKET — Vendor-Managed Delivery Rules & Implementation Audit

> **Owner-confirmed payment and handover rule (20 October 2026):** Only pay-before-delivery through Victorious MARKET or reserve in-shop pickup and pay at the vendor shop through Victorious MARKET's authorised payment workflow are permitted. No off-platform payment. Doorstep handover requires customer OTP verification plus a doorstep photo; in-shop pickup requires the RES-XXXXXX reservation pass plus one-time customer pickup OTP verification plus an in-shop photo. GPS, signature, separate rider-pickup photo and hub inspection photo are not mandatory. Payouts use server-calculated strict FIFO batches of whole earning records, with ₦5,000 minimum and ₦500,000 maximum; never split or skip an over-cap record.


**Review date:** 20 October 2026  
**Branch:** `audit/paystack-refund-webhook-safety`  
**Status:** Business rules documented; implementation gaps remain open. This report is an audit and implementation plan, not proof that the code has been fixed.  
**Controlling policy:** `.ai/BUSINESS_RULES.md`  
**No-merge rule:** Do not merge to `main` without explicit owner approval.

## 1. Approved operating model

The business owner’s 15 original delivery answers, additional privacy/pickup requirements, and two later financial-policy decisions are binding:

- Victorious MARKET is a marketplace, payments/financial-control, safety, and dispute platform. It does not run delivery dispatch, a central fleet, logistics-company fulfilment, route management, rider payroll, or rider payouts for new orders.
- Each active rider has exactly one active owning vendor. The vendor creates/manages its riders and manually assigns that vendor’s own doorstep-delivery child orders to them.
- Riders are operational-only. Vendor-to-rider compensation is arranged and paid directly by the vendor outside platform financial systems.
- A rider can work only on an explicitly assigned order belonging to that rider’s active vendor. Reassignment is allowed before physical custody; after custody, use a recorded return-to-vendor or explicit custody-transfer process.
- A multi-vendor cart remains one parent checkout with vendor-specific child orders. Each child has its own vendor, fulfilment choice, delivery-fee calculation, financial allocation, and at most one active rider assignment. A vendor’s rider never serves another vendor’s child order.
- Vendors configure the destinations they serve and the fees for those destinations. Unsupported destinations fail closed. LGA-specific pricing takes precedence over state-level fallback pricing where fallback is configured.
- For new vendor-managed delivery, the delivery fee allocation is 90% vendor / 10% platform. Eligible merchandise uses the separate 90% vendor / 5% customer cashback / 5% platform allocation. Cashback never applies to delivery fees. Neither allocation creates rider compensation payable by the platform.
- Doorstep COD is disabled. The two permitted payment paths are pay before delivery through Victorious MARKET, or reserve in-shop pickup through Victorious MARKET and pay at the approved vendor shop through the platform's authorised payment workflow. No off-platform payment is permitted. Launch rails are Paystack card and standard bank transfer unless separately approved. Riders must never see payment status or amount due.
- Delivery attempts, custody, proof, and handover are operational evidence. They must not automatically issue spendable customer credit, approve a refund, create a rider/logistics payable, or release vendor funds.
- In-shop pickup (`in_shop_pickup`) remains fully supported and costs zero delivery fee. At the vendor counter, the customer must present the `RES-XXXXXX` reservation pass AND complete the separate one-time customer pickup OTP; the vendor records the in-shop pickup photo. No rider assignment or delivery payout is created.
- Rider customer information is limited to the customer’s name, delivery address, and a protected **Call Customer** action. Raw phone number, payment/card/bank data, amount due, payment status, refunds, wallet balances, delivery-fee financial fields, cashback, commission, vendor settlement, and payout information are prohibited from rider responses and interfaces.
- Legacy logistics-company/fleet records and financial history are retained for authorized audit/reconciliation. They are not eligible for new assignments or new-order financial activity. Do not perform destructive cleanup until dependencies are mapped.
- Victorious MARKET is responsible for executing approved customer refunds through the authorized financial workflow; vendor recovery is separate and applies where the approved reason and actual allocation make the vendor responsible.
- Vendor settlement cannot become eligible before verified successful doorstep delivery or confirmed customer in-shop pickup. Applicable refund-window, dispute, vendor-status, bank-security, authorization, and proof controls still apply; fulfilment confirmation is necessary, not a bypass for those controls.
- **Vendor payout rule:** merchandise earnings are recorded per order-item line; delivery earnings are separate per vendor child order. Vendors may request only the full server-computed withdrawable balance, never a partial amount. Admin approval must be recorded before an authorized admin initiates payment. Only verified Paystack transfer success/reconciliation marks earnings paid.


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


## 2. Documentation consistency review

### Resolved on this branch

- `.ai/BUSINESS_RULES.md` records the owner’s 15 decisions, customer privacy rule, pickup rule, acceptance criteria, and open source-code findings.
- Root `VICTORIOUS_MARKET.md` and `README.md` already describe vendor-managed delivery, rider financial separation, the 90/5/5 merchandise allocation, the 90/10 delivery-fee allocation, customer privacy, pickup, and legacy-data preservation.
- `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md` already defers to `.ai/BUSINESS_RULES.md` and contains an implementation audit list.
- `backend/VICTORIOUS_MARKET.md` was updated on this branch to remove its contradictory claims that the platform coordinates logistics companies/routes and pays logistics providers for new delivery orders. It now describes vendor-owned riders, vendor assignment, pickup, privacy, fee allocation, COD boundaries, and financial separation.
- Cashback clock alignment is partially implemented on this branch: pre-fulfilment `matures_at`/`expires_at` are nullable; checkout/payment leave them unset; the migration backfills legacy pending/quarantined lots from the order's persisted fulfilment-window expiry (or clears dates for unfulfilled orders); verified delivery/pickup sets maturity to `refund_window_expires_at + 24 hours` and expiry to `matures_at + 365 days`. The cashback recovery ledger, persisted-setting audit, and reopening logic after a resolved dispute/return remain open.

### Remaining consistency checks before release

1. Search all docs, frontend/mobile text, onboarding screens, route names, notification templates, tests, and API contracts for active “fleet dispatch”, logistics-provider share/payable, rider earnings, rider payouts, or automatic wallet credit language. Historical descriptions must be explicitly labelled historical rather than presented as current behaviour.
2. **Canonical pickup credential decision:** customer in_shop_pickup requires both the RES-XXXXXX reservation pass and the separate six-digit customer pickup OTP (`pickup_verification_code`). The OTP supplements and never replaces the reservation pass. Preserve legacy values and records, securely verify both at the owning vendor counter, and keep the customer OTP distinct from any rider-to-vendor parcel-custody OTP.
3. Distinguish the rider collecting a parcel from the vendor (doorstep-delivery custody handover) from a customer collecting an order at the vendor counter. They are separate actors, flows, permissions, secrets, and status transitions. A rider must not be assigned to, or asked to verify, a customer in-shop pickup.

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

## 4. Pickup and multi-vendor invariants

- Server-side fee is exactly `0.00` for `in_shop_pickup`, regardless of client-supplied fee or delivery-zone settings.
- The vendor verifies the customer’s `RES-XXXXXX` reservation pass AND one-time pickup OTP at the vendor’s counter and records an in-shop pickup photo. Pickup is not a rider job.
- A vendor’s rider can only be assigned to that vendor’s doorstep-delivery child orders.
- Every parent checkout preserves separate vendor child orders and separate delivery fees/allocations.
- Unsupported vendor destination must fail closed; selected destination and fee are snapshotted at checkout.
- No rider/logistics payable or rider payout is created for new vendor-managed delivery.
- Legacy logistics financial entries remain auditable and are never automatically reclassified as vendor/rider money.

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

The business rules are now explicit; this section distinguishes policy from code state so a documentation edit is never mistaken for a shipped feature.

| Area | Binding rule | Source-code work still required |
|---|---|---|
| Rider ownership | Vendor-created/managed rider, exactly one active vendor owner, no new fleet assignments | Harden registration, identity uniqueness, active vendor/user checks, reject fleet-only/dual-linked profiles |
| Assignment | Vendor manually assigns only its own doorstep child order; reassignment before custody only | Atomic row-locked assignment, same-vendor checks, allowed-state checks, audit trail, exact rider assignment on every rider mutation |
| Customer privacy | Name, delivery address, protected Call Customer action only; no raw phone or financial data | Server-mediated/relayed call action, privacy-minimal serializers, inspect JSON/HTML/logs/exports/notifications and remove all prohibited fields |
| Rider financial boundary | Operational-only; no rider wallet/earnings/payable/payout; no rider-triggered financial posting | Split handover/failed-attempt evidence from financial decisions; remove direct wallet credits and logistics payable journals from rider paths |
| Failed delivery | Count attempts safely, apply configured threshold, return parcel to vendor, vendor confirms custody; remedy reviewed separately | Locked transaction, idempotent attempt identity, no duplicate return cases, no automatic refund/credit/journal |
| Pickup | in_shop_pickup is zero-fee, no rider; require RES-XXXXXX reservation pass AND separate customer pickup OTP AND in-shop pickup photo | Reconcile distinct customer and rider-custody credentials, enforce zero fee server-side, dedicated counter transition, test no rider assignment and no delivery financial allocation |
| Multi-vendor | One parent checkout; child orders isolate vendor, fulfilment, fee, and rider | Verify checkout snapshots and prevent cross-vendor reads/writes/assignment |
| Fees and allocation | Vendor destination/fee configuration; merchandise 90/5/5 and delivery 90/10; no rider share | Validate destination/fee on server, snapshot values, reconcile refunds to actual child-order allocations |
| Vendor earning ledger | Merchandise earnings per order-item line; delivery earning per vendor child order | Add immutable earning source records and lifecycle; stop using order-level aggregates as sole source of truth; item-level refunds/recovery reverse only affected lines/components |
| Vendor withdrawal | Strict FIFO batch of whole eligible earning records; no vendor-selected amount; no split/skip; active earnings atomically reserved | Compute strict FIFO whole-record batch server-side; reject custom amounts and overlapping requests; snapshot exact earning IDs and amounts |
| Admin payout control | Admin approval is separate from payment initiation; payout is not paid until verified gateway success | Split approval from Paystack transfer; pending-review cannot call gateway; audit actor/time/reason; reconcile timeout/failure/reversal against exact earning allocations |
| COD | Disabled across the entire marketplace at launch; no COD payment path is enabled | Audit UI, API, vendor settings, legacy payment methods and direct requests; any future enablement needs a separate owner decision and passing vendor-owned collection/reconciliation controls |
| Legacy logistics | Preserve history for authorized audit; no new fulfilment or new-order payouts through legacy fleet | Retire from new paths only after route/job/API/report dependency map; historical records remain read-only/auditable |

### Cross-document contradictions checked

- The controlling .ai/BUSINESS_RULES.md, root README.md, root VICTORIOUS_MARKET.md, backend VICTORIOUS_MARKET.md, and implementation plan must all describe vendor-managed delivery as the only active doorstep model for new orders.
- Generic future-roadmap mentions of logistics providers must not imply a planned central fleet or override vendor-managed delivery. Future wording should refer to vendor-managed delivery teams unless the owner approves a new business model.
- Pickup pass, rider parcel pickup, and customer counter pickup must not be used interchangeably. The RES pass belongs to customer counter pickup; rider parcel custody uses its separate audited operational handshake.
- A protected Call Customer action must not be implemented by returning a hidden or masked raw phone number to the rider.
- Rider operational events may be inputs to a separately authorized financial service only where the approved financial policy calls for it; the rider endpoint itself must not create financial postings or return financial results.
- Any legacy logistics route, wallet, earnings, or payout feature shown to a rider or used for new vendor-managed work is a policy violation even if the old database records are retained.

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

- Add a single authoritative fulfilment gate for settlement eligibility: `doorstep_delivery` requires verified customer handover; `in_shop_pickup` requires owning-vendor counter confirmation using the approved `RES-XXXXXX` pass. Both must record actor, timestamp, order/child vendor, and evidence; neither can be simulated by a rider pickup/custody event.
- Rework new-order settlement journals to credit the correct vendor payable for the 90% delivery-fee allocation and platform revenue for 10%, once per vendor child order; do not create logistics-company or rider beneficiaries.
- Keep vendor funds held until fulfilment is confirmed, the configured refund/return window has expired, no blocking dispute/return remains, the vendor is eligible, and payout security controls pass. Test single-vendor, multi-vendor, doorstep, and counter-pickup orders independently.
- Extend the refund financial model to distinguish customer refund execution from vendor recovery. On refund confirmation, reverse only the actual approved components and actual original allocations. If proceeds remain held, offset the appropriate vendor payable. If already paid, record a vendor recovery receivable/liability and collect via an authorized future settlement offset or approved recovery process. Do not mark customer refund success based on recovery success/failure.
- Make recovery idempotent and auditable: store responsible vendor, approved reason, amount, order/child reference, whether payable was held or already disbursed, recovery journal/receivable reference, offsets, and outstanding balance. Do not create rider compensation or expose any of this to riders.
- Add tests for no settlement before delivery/pickup; delivery/pickup gates each child order independently; open return/dispute blocks settlement; zero-fee pickup creates no delivery allocation; new order creates no logistics payable/wallet credit; refund before vendor payout offsets held payable; refund after vendor payout creates a recovery receivable; repeated refund/recovery events do not duplicate entries; multi-vendor refunds recover only the responsible vendor's actual share.

**Important qualification:** `vendor_recovery_ledgers` and `VendorRecoveryLedger` already exist, and the manual return/refund service uses them for post-settlement recovery. The gap is not absence of all recovery infrastructure; it is inconsistent integration/idempotency across the manual and Paystack webhook/reconciliation refund paths, plus fixed allocation assumptions that must be reconciled with actual order values.

### Verification status

Documentation review is not implementation verification. The audit findings above remain open until code changes and targeted regression tests pass. Record the actual CI run and full-suite result after implementation; do not infer success from the policy text.


## 10. Confirmed payment, refund, Paystack and chargeback policy (20 October 2026)

The owner has resolved the business decisions listed below. This section is binding policy; it does not claim the implementation is complete. The detailed state and webhook matrix is in `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md`.

### Binding decisions

1. **Payment channels:** launch with Paystack card and standard bank-transfer checkout; COD is disabled. Dedicated Virtual Accounts (DVA), USSD, mobile money and other channels are deferred unless separately approved. Handle `charge.success` for both enabled channels and `bank.transfer.rejected` as a reconciliation exception.
2. **One parent charge:** one Paystack charge for the parent checkout; immutable internal allocations per vendor child order. Each child order keeps its own fulfilment, fee, allocation, refund ceiling and settlement gate. Enforce both each-child allocation ceilings and the aggregate refund ceiling of the parent Paystack capture, atomically across concurrent partial-refund requests.
3. **Refund destination:** original Paystack channel only at launch. Wallet-credit refunds are deferred and must remain disabled in UI, API and service-level authorization. Any later release requires a separate owner-approved policy, explicit customer consent, timestamped consent, admin authorization and audit-log provenance.
4. **Automatic refund exceptions:** only (a) a verified duplicate successful charge for the exact extra capture against an already-paid checkout, and (b) a verified payment for an order cancelled before any vendor action may use a narrow automatic-approval path. Every other refund requires persisted human authorization before dispatch. A webhook is evidence, not approval. If the state is ambiguous, fail closed to review.
5. **Return/refund window and cashback:** the default customer return/refund/protection window is 24 hours, beginning at verified delivery or confirmed in-shop pickup. Cashback can mature only after this window expires and a separate further 24-hour buffer elapses, with no blocking return/dispute. The extra buffer applies to cashback maturity only; it does not extend the customer return/refund window. The 24-hour window is the standard customer return/claim window after fulfilment, not a promise of refund completion within 24 hours. Verified duplicate-charge corrections, pre-vendor-action cancellation refunds, payment exceptions, chargebacks, and applicable non-waivable legal obligations remain governed by separate authorization and evidence rules. If cashback has already been spent and is recoverable under policy, offset future cashback at maturity before the remainder becomes spendable; never silently debit cash wallet funds or create a negative spendable balance. Terms & Conditions and legal/consumer-policy review are required before release.
6. **Delivery-fee refunds:** vendor fault, including wrong/defective goods, and pre-dispatch cancellation may qualify. Customer change of mind and unjustified refusal normally do not. Partial returns require case-specific review and a recorded decision.
7. **Commercial allocations:** calculate merchandise 90/5/5 from actual post-discount merchandise collected, excluding delivery fee. Delivery fee is allocated 90% vendor / 10% platform. The platform owns the rounding remainder. No rider payable is created.
8. **Gateway fees and taxes:** Victorious MARKET bears Paystack processing fees from its own platform share; do not reduce vendor 90% or cashback 5%. Do not hard-code tax treatment until a qualified Nigerian tax adviser confirms it.
9. **Chargeback losses:** vendor recovery is evidence- and fault-based. Recover from the vendor only when documented vendor fault or an approved allocation justifies it. If the platform submitted required evidence but still loses, or fault is ambiguous, do not automatically debit the vendor; the platform bears the loss unless an authorized decision establishes a justified recovery.
10. **Bank-transfer rejection message:** “Your bank transfer could not be processed. We are checking the status with our payment provider and will update you within 24 hours. If funds left your account, they will be returned to you once confirmed. Please do not attempt another payment until you receive an update.” Do not promise a refund until provider evidence confirms the applicable outcome.

### Prioritized implementation checklist

| Priority | Exact work area | Required change / acceptance condition |
|---|---|---|
| P0 | `backend/app/Http/Controllers/Web/PaystackPaymentController.php` | Add explicit `bank.transfer.rejected`, `refund.pending`, `refund.processing`, `refund.needs-attention`, and `charge.dispute.create/remind/resolve` handling. Match exact event/payment/refund identity and payload shape; preserve unmatched cases for review. Do not let an event authorize a refund or duplicate-refund an invalid bank transfer. |
| P0 | `backend/app/Services/FinancialOperationService.php`, refund dispatch routes/services | Enforce persisted human approval for all refunds except the two narrow, auditable automatic exceptions. Wallet-credit refunds must be blocked at UI, route and service level at launch. A `refund_pending` status must not authorize dispatch. |
| P0 | `backend/app/Services/OrderSettlementService.php`, checkout/payment configuration | Verify only Paystack card + standard bank transfer are enabled; reject COD across checkout UI, APIs, vendor settings and legacy payment methods; enforce one parent charge and immutable child-order allocations. Store verified duplicate captures separately; never settle or accrue cashback twice. |
| P0 | Refund reservation/finalization and `ReturnAndRefundService` | Use captured post-discount child-order allocations, exact delivery-fee decision, previous refunds and active reservations. Enforce per-child and aggregate parent-capture refund ceilings under locks across concurrent requests. Keep customer refund obligation open after `refund.failed`; reconcile provider credit before retry. |
| P1 | `ReturnAndRefundService`, `SystemSetting`, cashback creation/maturation jobs | Preserve the approved `refund_window_hours` default of 24; audit persisted settings/seeds and start the window from verified fulfilment. Mature cashback no earlier than `refund_window_expires_at + 24 hours` (`+ 1 day`) only after valid fulfilment and no blocking case. The buffer is cashback-only, not a longer customer claim window or gateway SLA. Implement customer cashback recovery ledger and offset only at maturity; add customer-facing Terms & Conditions before enabling. |
| P1 | `backend/app/Services/BcMathService.php` and checkout allocation caller | Prove 90/5/5 uses actual post-discount merchandise amount and excludes delivery fee; keep 90/10 delivery allocation; assign rounding remainder to platform; preserve immutable snapshot. |
| P1 | Dispute case/reconciliation and vendor recovery ledger | Tie chargebacks to the exact parent charge and affected child allocation; preserve evidence/deadlines; hold only affected unsettled proceeds; vendor recovery requires documented responsibility, authorization, idempotency and actual allocation. |
| P1 | Customer messaging/notifications | Use the approved bank-transfer rejection message; do not promise refund processing or receipt until authoritative provider state supports the wording. Tell the customer not to retry payment while the first attempt is being reconciled. |
| P2 | Finance configuration and release readiness | Obtain Nigerian tax-adviser review before implementing tax calculations. Confirm gateway-fee accounting so fees are borne by platform share and do not reduce vendor/cashback allocations. |
| P2 | Tests/CI | Add regression coverage for both automatic-refund exceptions and nearby ineligible cases; card and standard bank transfer; bank-transfer rejected with/without provider refund; all refund lifecycle states; duplicate/out-of-order webhooks; chargeback outcomes; cashback recovery at maturity; discounts + delivery fees; and no rider financial exposure. Run targeted tests and full backend/frontend suites before release. |

### Paystack launch webhook subscription set

Subscribe to and handle the following events for the enabled launch products:

- `charge.success`
- `bank.transfer.rejected`
- `refund.pending`
- `refund.processing`
- `refund.needs-attention`
- `refund.processed`
- `refund.failed`
- `charge.dispute.create`
- `charge.dispute.remind`
- `charge.dispute.resolve`
- `transfer.success`
- `transfer.failed`
- `transfer.reversed`

Do not subscribe to DVA assignment events at launch because DVA is deferred. Subscribe to invoice, subscription, payment-request or customer-identification events only if those separate Paystack products are deliberately enabled. All events require signature validation, immutable event identity, exact reference/amount/currency matching, idempotent state transitions, audit logging and reconciliation.



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
