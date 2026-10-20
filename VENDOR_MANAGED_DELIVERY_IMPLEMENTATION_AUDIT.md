# Victorious MARKET — Vendor-Managed Delivery Rules & Implementation Audit

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
- Rider-assigned COD is disabled until a vendor-owned collection and reconciliation workflow is explicitly approved and passes privacy, authorization, and financial-control tests. Riders must never see payment status or amount due.
- Delivery attempts, custody, proof, and handover are operational evidence. They must not automatically issue spendable customer credit, approve a refund, create a rider/logistics payable, or release vendor funds.
- In-shop pickup (`in_shop_pickup`) remains fully supported, always costs zero delivery fee, uses the pickup pass format `RES-XXXXXX`, is verified by the vendor at the counter, and creates no rider assignment or delivery payout.
- Rider customer information is limited to the customer’s name, delivery address, and a protected **Call Customer** action. Raw phone number, payment/card/bank data, amount due, payment status, refunds, wallet balances, delivery-fee financial fields, cashback, commission, vendor settlement, and payout information are prohibited from rider responses and interfaces.
- Legacy logistics-company/fleet records and financial history are retained for authorized audit/reconciliation. They are not eligible for new assignments or new-order financial activity. Do not perform destructive cleanup until dependencies are mapped.
- Victorious MARKET is responsible for executing approved customer refunds through the authorized financial workflow; vendor recovery is separate and applies where the approved reason and actual allocation make the vendor responsible.
- Vendor settlement cannot become eligible before verified successful doorstep delivery or confirmed customer in-shop pickup. Applicable refund-window, dispute, vendor-status, bank-security, authorization, and proof controls still apply; fulfilment confirmation is necessary, not a bypass for those controls.

## 2. Documentation consistency review

### Resolved on this branch

- `.ai/BUSINESS_RULES.md` records the owner’s 15 decisions, customer privacy rule, pickup rule, acceptance criteria, and open source-code findings.
- Root `VICTORIOUS_MARKET.md` and `README.md` already describe vendor-managed delivery, rider financial separation, the 90/5/5 merchandise allocation, the 90/10 delivery-fee allocation, customer privacy, pickup, and legacy-data preservation.
- `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md` already defers to `.ai/BUSINESS_RULES.md` and contains an implementation audit list.
- `backend/VICTORIOUS_MARKET.md` was updated on this branch to remove its contradictory claims that the platform coordinates logistics companies/routes and pays logistics providers for new delivery orders. It now describes vendor-owned riders, vendor assignment, pickup, privacy, fee allocation, COD boundaries, and financial separation.

### Remaining consistency checks before release

1. Search all docs, frontend/mobile text, onboarding screens, route names, notification templates, tests, and API contracts for active “fleet dispatch”, logistics-provider share/payable, rider earnings, rider payouts, or automatic wallet credit language. Historical descriptions must be explicitly labelled historical rather than presented as current behaviour.
2. **Canonical pickup credential decision:** the customer-facing credential for in_shop_pickup is RES-XXXXXX, verified by the owning vendor at the counter. The separate six-digit pickup_verification_code currently generated by code must not silently replace or override the approved RES pass. Preserve legacy values and records, but make the counter flow use one unambiguous canonical RES pass. If the six-digit value has another operational purpose, isolate and document it separately; it is not an alternate customer counter-pickup credential without explicit owner approval.
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

The current handler increments attempts and, at the threshold, marks the order returned, creates a return case, creates an immediately available `CustomerWalletCredit`, and may credit a `logistics_payable` journal entry. This violates the operational-only rule. The endpoint should record the attempt, evidence, and custody/return transition only. Refunds/credits and vendor recovery must go through an independently authorized, idempotent financial workflow after policy review.

#### D. Rider-triggered handover mixes operational evidence with money movement
**File:** `backend/app/Services/DoorstepHandshakeService.php`; response in `RiderTerminalApiController::doorstepHandover()`

The handover service currently creates a return case, pending wallet credit and provisional financial reservation, writes merchandise/cashback/platform/delivery-fee journal entries, and returns financial amounts to the rider. Separate operational proof/item acceptance from financial approval/posting. The rider action must not itself create a spendable credit or make financial decisions. A separately authorized financial engine may consume the verified event after approved policy checks, idempotency, refund-ceiling checks, and audit recording.

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

`PickupReservation::generateCode()` generates an `RES-` reference, while pickup-reservation payment creates an order with a six-digit `pickup_verification_code` and returns both values. Rider terminal routes also use “pickup OTP” for a rider collecting a parcel from the vendor. Make the canonical customer in-shop pickup pass and vendor-counter verification match the approved `RES-XXXXXX` rule; keep rider-to-vendor parcel custody as a separate operational handshake with its own status and evidence. Never expose either verification secret in rider list/history payloads.

#### J. Rider onboarding needs safer credential handling and unique identity
**File:** `backend/app/Http/Controllers/Api/VendorTerminalApiController.php`, `storeRider()`

The vendor creates a rider user and sets the user status active immediately, accepting a password in the request. Keep vendor-led onboarding, but review whether credentials should be established through a one-time invitation/password setup rather than a vendor-selected rider password. Enforce one rider profile per user and ensure vendor identity always comes from the authenticated vendor context. Do not add central dispatch approval; platform account/safety suspension remains allowed.

#### K. Legacy logistics and rider financial surfaces remain active
**Areas:** web routes/controllers/views, logistics-company models, rider UI, payout services, API routes, jobs, and reports.

Disable legacy fleet roles from new order assignment and new financial activity. Remove rider earnings/wallet/payout views and any new logistics-company payout path for vendor-managed orders. Preserve historical records and authorized read-only historical reporting. Do not drop legacy tables/columns until dependency mapping confirms safe retirement.

#### L. Failed-delivery attempts are not concurrency-safe
**File:** `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `recordDeliveryAttemptFailed()`

Attempt count, threshold evaluation, order state, return case, and custody are updated without one encompassing locked transaction. Concurrent attempts can exceed or race around the threshold or duplicate return cases. Use a locked transaction, idempotency key/event identity, and one authoritative transition to return-to-vendor after the configured threshold.

#### M. COD remains disabled
**Areas:** checkout/payment methods, rider views/API, vendor collection and reconciliation workflows.

Ensure rider-assigned COD cannot be enabled through a UI flag, API parameter, legacy payment method, or direct request. Re-enable only after a vendor-owned collection/reconciliation design is approved and tests prove riders cannot see payment state/amount due or hold/reconcile money through platform rider accounts.

## 4. Pickup and multi-vendor invariants

- Server-side fee is exactly `0.00` for `in_shop_pickup`, regardless of client-supplied fee or delivery-zone settings.
- The vendor verifies the customer’s `RES-XXXXXX` pickup pass at the vendor’s counter. Pickup is not a rider job.
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
9. Pickup `in_shop_pickup` is zero-fee, uses the approved `RES-XXXXXX` customer pickup pass at the vendor counter, requires no rider, and cannot be fulfilled through rider doorstep endpoints.
10. Multi-vendor checkout keeps fees, assignments, and allocations isolated by child vendor order.
11. COD is rejected for rider-assigned orders until the approved vendor-owned collection/reconciliation workflow exists.
12. Historical logistics orders and financial records remain readable to authorized auditors, but legacy fleet roles cannot receive new work.
13. Merchandise 90/5/5 and delivery-fee 90/10 allocations balance and refund correctly without any rider beneficiary.

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
| Pickup | in_shop_pickup is zero-fee, no rider, canonical RES-XXXXXX pass verified by vendor counter | Reconcile existing six-digit field, enforce zero server-side, dedicated counter transition, test no rider assignment and no delivery financial allocation |
| Multi-vendor | One parent checkout; child orders isolate vendor, fulfilment, fee, and rider | Verify checkout snapshots and prevent cross-vendor reads/writes/assignment |
| Fees and allocation | Vendor destination/fee configuration; merchandise 90/5/5 and delivery 90/10; no rider share | Validate destination/fee on server, snapshot values, reconcile refunds to actual child-order allocations |
| COD | Rider-assigned COD remains disabled until an approved vendor-owned collection/reconciliation design passes controls | Audit UI, API, legacy payment methods, and direct requests; reject rider COD without a separately approved workflow |
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
4. **Refund completion does not yet implement a complete vendor-recovery lifecycle.** `FinancialOperationService::finalizeRefundConfirmation()` posts refund journals, but the non-return/cancellation branch only posts the cash outflow; it does not record vendor recovery. In the return-case branch, it debits `vendor_payable` using a percentage split without first determining whether that vendor payable remains unpaid, has already been disbursed, or differs due to discounts/line-item allocation. This risks a negative/fictional payable and does not satisfy the approved recovery rule.
5. **No verified customer-refund ownership contract is enforced end-to-end by the delivery flows.** Rider handover and failed-delivery paths currently mix operational evidence with wallet-credit/refund-reservation/journal work (see the P0 findings above). Keep the platform as refund operator, but make the rider only an evidence source; refund decision and execution belong to the authorized return/refund workflow.
6. **Parent and child financial allocations must not be double-counted.** Existing payment settlement creates some delivery-fee entries at both child and parent levels. During the change, map exactly which order owns each captured fee and journal event, and assert that each delivery fee is allocated once to vendor (90%) and platform (10%). Do not fix the issue by deleting legacy entries or silently reclassifying posted historical entries.

### Required implementation work

- Add a single authoritative fulfilment gate for settlement eligibility: `doorstep_delivery` requires verified customer handover; `in_shop_pickup` requires owning-vendor counter confirmation using the approved `RES-XXXXXX` pass. Both must record actor, timestamp, order/child vendor, and evidence; neither can be simulated by a rider pickup/custody event.
- Rework new-order settlement journals to credit the correct vendor payable for the 90% delivery-fee allocation and platform revenue for 10%, once per vendor child order; do not create logistics-company or rider beneficiaries.
- Keep vendor funds held until fulfilment is confirmed, the configured refund/return window has expired, no blocking dispute/return remains, the vendor is eligible, and payout security controls pass. Test single-vendor, multi-vendor, doorstep, and counter-pickup orders independently.
- Extend the refund financial model to distinguish customer refund execution from vendor recovery. On refund confirmation, reverse only the actual approved components and actual original allocations. If proceeds remain held, offset the appropriate vendor payable. If already paid, record a vendor recovery receivable/liability and collect via an authorized future settlement offset or approved recovery process. Do not mark customer refund success based on recovery success/failure.
- Make recovery idempotent and auditable: store responsible vendor, approved reason, amount, order/child reference, whether payable was held or already disbursed, recovery journal/receivable reference, offsets, and outstanding balance. Do not create rider compensation or expose any of this to riders.
- Add tests for no settlement before delivery/pickup; delivery/pickup gates each child order independently; open return/dispute blocks settlement; zero-fee pickup creates no delivery allocation; new order creates no logistics payable/wallet credit; refund before vendor payout offsets held payable; refund after vendor payout creates a recovery receivable; repeated refund/recovery events do not duplicate entries; multi-vendor refunds recover only the responsible vendor's actual share.

### Verification status

Documentation review is not implementation verification. The audit findings above remain open until code changes and targeted regression tests pass. Record the actual CI run and full-suite result after implementation; do not infer success from the policy text.
