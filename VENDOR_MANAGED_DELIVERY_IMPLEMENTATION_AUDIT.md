# Victorious MARKET — Vendor-Managed Delivery Rules & Implementation Audit

**Review date:** 19 October 2026  
**Branch:** `audit/paystack-refund-webhook-safety`  
**Status:** Business rules documented; implementation gaps remain open. This report is an audit and implementation plan, not proof that the code has been fixed.  
**Controlling policy:** `.ai/BUSINESS_RULES.md`  
**No-merge rule:** Do not merge to `main` without explicit owner approval.

## 1. Approved operating model

The business owner’s 15 answers and additional requirements are binding:

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

## 2. Documentation consistency review

### Resolved on this branch

- `.ai/BUSINESS_RULES.md` records the owner’s 15 decisions, customer privacy rule, pickup rule, acceptance criteria, and open source-code findings.
- Root `VICTORIOUS_MARKET.md` and `README.md` already describe vendor-managed delivery, rider financial separation, the 90/5/5 merchandise allocation, the 90/10 delivery-fee allocation, customer privacy, pickup, and legacy-data preservation.
- `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md` already defers to `.ai/BUSINESS_RULES.md` and contains an implementation audit list.
- `backend/VICTORIOUS_MARKET.md` was updated on this branch to remove its contradictory claims that the platform coordinates logistics companies/routes and pays logistics providers for new delivery orders. It now describes vendor-owned riders, vendor assignment, pickup, privacy, fee allocation, COD boundaries, and financial separation.

### Remaining consistency checks before release

1. Search all docs, frontend/mobile text, onboarding screens, route names, notification templates, tests, and API contracts for active “fleet dispatch”, logistics-provider share/payable, rider earnings, rider payouts, or automatic wallet credit language. Historical descriptions must be explicitly labelled historical rather than presented as current behaviour.
2. Check the customer-facing pickup flow: code currently distinguishes the `RES-...` reservation code from an order’s six-digit `pickup_verification_code`. The approved rule says the customer pickup pass is `RES-XXXXXX` and the vendor verifies it at the counter. The implementation must deliberately reconcile these semantics and not accidentally require a rider to verify customer counter pickup. If the reservation code is only a pre-purchase inspection reference and a separate code is intended for final order handover, that exception must be explicitly approved and documented; do not silently treat the two codes as interchangeable.
3. Distinguish the rider collecting a parcel from the vendor (custody handover) from a customer collecting an order at the vendor counter. They are separate actors, flows, permissions, and status transitions.

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
- Do not merge to `main` without explicit owner approval.
- Business-rule changes require owner approval and matching updates to documentation, APIs, implementation, tests, and customer/vendor-facing policies.
