# VICTORIOUS MARKET — Authoritative Business Rules

**Status:** Authoritative business-rule baseline for vendor-managed fulfilment
**Last reviewed:** 20 October 2026
**Review note:** The rules are approved requirements; Section 14 lists implementation gaps found in the audit branch, not completed fixes.  
**Scope:** Delivery, vendor-linked riders, in-shop pickup, customer privacy, failed delivery, delivery fees, COD boundaries, and legacy logistics.  
**Authority:** Read with [VICTORIOUS_MARKET_SYSTEM_RULES_V1.md](../VICTORIOUS_MARKET_SYSTEM_RULES_V1.md), the single source of truth. These rules supersede older descriptions of platform-managed logistics, conflicting OTP/photo requirements, logistics-company dispatch, rider earnings, and rider payouts. Historical records must be preserved. Documentation alignment only; implementation remains unverified.

## Decision record from the vendor-delivery Q&A

The selected answers are recorded here so implementation teams do not revert to the older fleet model:

| Q | Topic / selected answer | Adopted rule |
|---:|---|---|
| 1 | Logistics company role — A | No platform-managed logistics company, central fleet, hub, or dispatch operation for new orders. |
| 2 | Rider/vendor relationship — A | Each active rider belongs to one owning vendor. |
| 3 | Rider registration — A | The vendor adds/manages its riders through vendor-authenticated workflows. |
| 4 | Rider approval — A | Rider onboarding is vendor-led; account safety controls remain available to the platform. Do not add a central dispatch approval step unless a separately approved safety requirement calls for it. |
| 5 | Order assignment — B | The vendor manually assigns each of its orders to one of its linked riders. |
| 6 | Reassignment — A | Vendor-managed reassignment is allowed before custody; after pickup, use an audited custody-transfer/return flow. |
| 7 | Multi-vendor checkout — A | Keep one parent checkout with separate child vendor orders, fulfilment choices, fees, and rider assignments. |
| 8 | Delivery fees/zones — A | Admin sets standard delivery fees (corridors and zonal rates); vendors configure the individual LGAs and states they can cover from their physical shop. Unsupported destinations fail closed to in-shop pickup. |
| 9 | Delivery-fee split — B | Delivery fee allocation is 90% to the vendor and 10% platform delivery-fee share; it does not create a rider payable. |
| 10 | Vendor-to-rider compensation — A | The vendor compensates its rider directly outside platform financial systems. |
| 11 | Payment paths — owner-confirmed | Only (a) pay before delivery through Victorious MARKET checkout, or (b) reserve in-shop pickup through Victorious MARKET and pay at the approved vendor shop through the platform's authorised payment workflow. Shop reservation is not doorstep COD. No vendor cash collection, personal-account transfer or off-platform payment. Launch rails are Paystack card and standard bank transfer unless separately approved. Legacy wallet-credit checkout tender is disabled; capped cashback redemption is a separate discount, not a payment rail. |
| 12 | Handover evidence — owner-confirmed | Doorstep delivery requires verified customer OTP plus a linked doorstep delivery photo. In-shop pickup requires the RES-XXXXXX reservation pass AND one-time customer pickup OTP verification plus a linked in-shop pickup photo. GPS, signature, separate rider-pickup photo and hub inspection photo are not mandatory. |
| 13 | Failed delivery / doorstep rejection — owner-confirmed | Record outcome and return rejected/undelivered goods directly to the owning vendor. The vendor may offer an agreed replacement/redelivery; customer agreement is required. Refund/cancellation and financial responsibility remain separately reviewed. |
| 14 | Admin boundaries — A | Platform admin handles oversight/security/disputes, not dispatch or rider pay. |
| 15 | Legacy logistics records — A | Preserve history for audit; disable legacy fleet paths for new assignments after safe dependency mapping. |
| 16 | Refund responsibility — A | Victorious MARKET owns the customer refund process and executes approved refunds through the authorized financial workflow; recover from the vendor where policy, fault, and actual allocation justify it. | 
| 17 | Vendor settlement timing — B | Vendor proceeds are not eligible for release until successful doorstep delivery or confirmed in-shop pickup; configured refund/dispute, vendor eligibility, and payout-security controls still apply. |
| 18 | Vendor earnings and payouts — owner-confirmed | After confirmed delivery/pickup and 24 hours with no applicable hold, earnings may become available. System-calculated payout uses oldest eligible whole earning records FIFO, minimum ₦5,000, maximum ₦500,000; stop if the next full record exceeds the cap, with no splitting or skipping. One active request; admin approval required. Victorious MARKET bears payout transfer charges. No rider payout. |


## 1. Non-negotiable operating model

1. VICTORIOUS MARKET is a marketplace and financial-control platform. It does **not** operate a central logistics fleet, logistics company, dispatch hub, rider payroll, route-dispatch service, or rider-payout programme.
2. Each vendor manages delivery for that vendor's own orders.
3. Every active rider must be registered through a vendor and linked to exactly one owning vendor. A rider is that vendor's delivery worker, not a platform courier or shared marketplace resource.
4. The vendor adds/manages riders and manually assigns its orders to them.
5. Riders are operational actors only. They may view and update permitted delivery workflow data and submit delivery evidence. They have no platform wallet, earnings balance, financial ledger beneficiary account, payout request, settlement entitlement, commission view, or authority to approve refunds.
6. Vendor-to-rider pay, wages, per-delivery compensation, reimbursements, and any other compensation are arranged and paid directly by the vendor outside Victorious MARKET's rider financial systems. The platform must not calculate, reserve, collect, disburse, or reconcile rider compensation.
7. The platform may provide oversight and controls: vendor/rider account status, safety checks, abuse review, complaint/dispute review, audit logs, and suspension for safety or policy violations. Oversight is not dispatch management.
8. Legacy logistics-company, hub, fleet, route, and rider-payout records are historical data only. Preserve them for audit and existing financial reconciliation; disable them as active dependencies for new fulfilment. Do not delete history or run destructive migrations merely to simplify the workflow.

## 2. Actors and permissions

### Vendor
- Creates/invites a rider account through the vendor's authenticated account.
- Links the rider to its own vendor profile; the vendor must not attach riders to another vendor.
- Activates/deactivates its own riders subject to platform account/safety restrictions.
- Sees its own riders and vendor orders only.
- Assigns and, within the allowed custody boundary, reassigns its own delivery orders.
- Selects its own supported delivery coverage areas (individual LGAs and states); delivery fees are determined by the admin-configured platform fee schedule.
- Coordinates delivery directly with its rider and handles compensation outside the platform.
- Handles its fulfilment work, including preparing goods, handing over parcels, receiving returned parcels, and communicating with customers.

### Rider
- Must have an active user account, an active rider profile, and exactly one active vendor relationship to be eligible for new assignments.
- Can access only orders explicitly assigned to that rider and belonging to the rider's linked vendor.
- Can see only the minimum customer information needed to complete the assigned delivery (see Customer Privacy).
- Can update allowed operational states, record failed attempts, submit required proof, and complete authorised handover verification.
- Cannot assign orders to themselves, browse unassigned orders, transfer orders to another vendor, change product or delivery prices, see payment status/amounts/commissions, issue wallet credit, initiate refunds, access payouts, or perform financial actions.
- Cannot fulfil in-shop pickup as a delivery task. At the vendor counter, the customer must present the RES-XXXXXX reservation pass AND complete the separate one-time customer pickup OTP; the vendor records the required in-shop pickup photo.

### Platform administrator
- May verify or suspend vendors/riders, review safety incidents and disputes, inspect audit trails, and enforce security/policy requirements.
- Does not operate dispatch, choose which rider receives each order, manage rider shifts/routes, calculate rider pay, or pay riders.
- May intervene in a complaint or exception through an audited administrative workflow without becoming the delivery operator.
- Must not expose customer financial details to riders or grant rider financial permissions.

## 3. Rider registration, ownership, and lifecycle

1. The vendor initiates rider registration and is the rider's owning business.
2. The vendor link is mandatory for every active rider. A vendor rider must not simultaneously be linked to a logistics company or another vendor. Legacy fleet-linked profiles may remain for history but cannot receive new orders under this model.
3. A rider's account status and profile status must both be active before assignment or delivery actions are permitted.
4. A suspended/inactive vendor cannot assign orders, and its riders cannot act on that vendor's orders.
5. Vendor reassignment of a rider to a different vendor is not an ordinary edit. It requires a controlled ownership-transfer process, must not transfer active custody, and must preserve historical assignments. Until such a process exists, reject cross-vendor reassignment.
6. Deactivation must be blocked while the rider holds a parcel in custody. Historical assignments and evidence are retained; rider profiles with history are deactivated/soft-deleted rather than destructively erased.
7. Prevent duplicate rider profiles for the same user and prevent client-supplied vendor IDs from overriding the authenticated vendor's identity.

## 4. Order assignment and reassignment

1. Assignment is vendor-driven and manual. There is no platform auto-dispatch queue or logistics-company allocation.
2. A vendor can assign only an eligible rider linked to that same vendor and only an order belonging to that vendor.
3. The backend must verify ownership and order state on every assign, reassign, pickup, arrival, failed-attempt, and delivery-confirmation request. UI filtering alone is not authorization.
4. An order may be reassigned before the rider takes physical custody. Assignment/reassignment records must capture actor, old rider, new rider, timestamp, reason, and order state.
5. Once the parcel is picked up / marked in the rider's custody, normal reassignment is blocked. The vendor must use an audited return-to-vendor or custody-transfer process so two riders cannot both claim the same parcel.
6. A rider cannot assign or reassign their own order, and cannot access another rider's assignment even if the order ID is guessed.
7. Multi-vendor carts are split into vendor-specific child orders. Each child order has one owning vendor, one fulfilment choice, one applicable vendor delivery fee, and at most one active rider assignment. One vendor's rider never receives another vendor's child order.

## 5. Fulfilment types

### Doorstep delivery
- The vendor prepares the goods and assigns its rider.
- Delivery progression records vendor readiness, rider assignment, pickup/custody, delivery attempt(s), and final handover as separate states.
- Doorstep delivery completion requires secure customer OTP verification AND a delivery photo linked to the child order. GPS, signature and rider pickup photo are not mandatory.
- A rider's delivery confirmation is an operational event. It does not grant the rider financial permissions or let the rider directly edit a ledger, approve a refund, alter prices, or request payouts. An independent, authorized financial service may consume a verified delivery event as an input to the published vendor-settlement policy; that financial action must be separately validated, idempotent, and auditable.

### In-shop pickup
- In-shop pickup remains fully supported as a first-class fulfilment type.
- The customer collects the order at the owning vendor's physical shop/counter.
- Delivery fee is exactly zero. Checkout and backend enforce zero regardless of client-supplied amount or vendor delivery-zone setting.
- The `RES-XXXXXX` reservation pass AND one-time customer pickup OTP verification are required for customer counter pickup alongside the in-shop pickup photo; neither credential is a photo substitute. Never expose pickup secrets in ordinary order lists/history, logs, analytics or unauthorised rider views.
- The six-digit `pickup_verification_code` is the required one-time customer pickup OTP and is used IN ADDITION TO the `RES-XXXXXX` reservation pass; it must never replace or override the reservation pass. Keep this customer counter OTP distinct from any rider-to-vendor parcel-custody OTP. Preserve historical records and migrate additively; do not invalidate existing reservations without a safe customer-support path.
- Store only a secure representation where feasible; do not expose the pickup pass in rider lists, rider history, logs, analytics, or unrelated API responses. Return it only to the authorized customer or verify it through the owning vendor's authorized counter flow.
- The vendor verifies the pickup pass securely, confirms payment through Victorious MARKET's authorised payment workflow, and records the in-shop pickup photo/handover. A delivery rider is not required and no rider assignment, doorstep-delivery status, delivery fee, rider compensation, or logistics payout is created.
- Pickup status and doorstep delivery status remain distinct. Customer counter pickup is not the same event as a rider collecting a parcel from the vendor. Require reservation pass plus one-time customer pickup OTP verification plus the in-shop pickup photo; GPS, signature and a rider are not required.
- Order/payment expiry must not silently invalidate a paid order or create a financial refund. Expiry, stock release, payment verification, and refund are separate auditable operations.

## 6. Vendor-defined delivery areas and fees

1. Vendors configure destinations they serve and the delivery fee for each supported destination. State-level fallback may be supported; an explicit LGA fee takes precedence over a state-level fee.
2. A doorstep-delivery destination without a valid vendor-configured fee is unavailable. Fees must be server-validated and cannot be trusted from the client.
3. If the existing commercial rule requires a positive fee for doorstep delivery, enforce a fee greater than zero for that enabled destination. This rule does not apply to in-shop pickup, whose fee is always zero.
4. The chosen fulfilment type, destination, quoted fee, and relevant fee-rule version are snapshotted to the vendor child order at checkout. Later vendor settings changes do not rewrite existing orders.
5. Each child vendor order calculates its own products, discounts, delivery fee, commission, cashback eligibility, and vendor allocation. One Paystack charge collects the parent checkout amount; the verified captured amount is allocated internally using immutable per-child snapshots. The parent payment amount/reference remains authoritative for collection, and child allocations must sum exactly to the captured parent total. The parent checkout must not collapse multiple vendors' delivery fees into an untraceable shared amount.
6. The delivery fee is a vendor-order charge, not a logistics-company fare. No rider share/payable is created by the platform. The vendor is responsible for rider compensation outside the platform.
7. The existing merchandise allocation is 90% vendor, 5% customer cashback, and 5% platform share on eligible merchandise value. The delivery-fee allocation is 90% vendor and 10% platform share. Cashback applies to eligible product value only and never to delivery fees. For example, with ₦90,000 eligible products and ₦10,000 delivery, the merchandise allocation is ₦81,000 vendor / ₦4,500 cashback / ₦4,500 platform, and the delivery allocation is ₦9,000 vendor / ₦1,000 platform. The owning vendor separately pays its rider outside the platform. Refunds must reverse the correct merchandise split and/or delivery-fee split according to the actual approved refundable components. Any future change must be an explicit business decision updated consistently across documents and code.

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

- Rider assignment, availability, location, pickup, delivery, failed attempts, and proof are operational records.
- Riders are not financial beneficiaries in the marketplace ledger.
- Do not create rider payout requests, rider wallets, rider earnings, logistics-company payable entries, or delivery-fee split journals for new vendor-managed deliveries.
- Rider endpoints must not directly mutate financial ledgers, approve refunds, or issue credits. The authorized financial engine may use a verified operational event as an input only where the published settlement/refund policy explicitly requires it.
- Existing vendor settlement, platform commission, cashback, payment verification, refund, and payout controls remain in their established financial services.
- If historical orders contain logistics-company financial entries, preserve and reconcile them under legacy rules. Do not reclassify historical amounts as vendor income or delete journal entries.
- All new financial movements must remain idempotent, balanced, authorized, and reconcilable.

### Vendor settlement timing and eligibility
- Vendor proceeds must remain held before fulfilment. The qualifying fulfilment event is verified successful customer handover for `doorstep_delivery` or verified customer handover at the owning vendor's counter for `in_shop_pickup`.
- A rider's operational delivery event alone is not a payout instruction. The backend must verify the correct child order, vendor, fulfilment type, customer handover proof, and final state before the financial engine can consider settlement.
- Successful delivery or confirmed customer pickup is the earliest settlement-eligibility trigger, not permission to bypass an applicable published refund window, an open return/dispute, vendor account restrictions, bank-account security cooldown, payout authorization, or required transfer proof. Once the applicable hold has expired and no blocking case exists, the order may become eligible under the existing settlement controls. Do not invent additional delay periods or settle before fulfilment.
- For multi-vendor checkout, settlement eligibility is evaluated per vendor child order. One vendor's delivery/pickup or dispute must not release or block another child order's funds except where a documented parent-level financial risk requires it.

### Vendor earning records, withdrawal requests, and admin-paid payouts

- **Earning provenance is mandatory.** Create a separately identifiable merchandise earning record for each order item/line and owning vendor. It must point to the parent payment, vendor child order, order-item/line ID, product snapshot, quantity, post-discount merchandise amount allocated to that line, the exact 90% vendor share in integer kobo, and the source settlement/allocation event. Never store only one unexplained vendor earnings total on the whole order.
- **Delivery earnings are separate.** Record the vendor's 90% delivery-fee earning as a distinct record tied to the specific vendor child order and its captured delivery fee. It must not be attached to a merchandise line, parent total without child attribution, rider, or logistics company. For in_shop_pickup, delivery fee and delivery earning are exactly zero.
- **Independent lifecycle per earning.** Each earning record tracks its own eligibility and lifecycle (for example held, withdrawable, reserved in a payout request, paid, reversed, or under recovery) and retains its order/item or vendor-child-order source. A child order can have several item earnings and one separate delivery earning. Refunds, partial item returns, discounts, chargebacks, and recovery reverse/hold only the affected earning components using the immutable checkout allocation.
- **Earning checks precede withdrawal.** An earning becomes withdrawable only after verified payment allocation, verified qualifying fulfilment, expiry of the 24-hour return/refund window, no blocking return/dispute/payment exception, valid vendor/KYC/account status, completed bank-account security cooldown, and all applicable reconciliation/recovery checks. Cashback and platform share are never vendor earnings. A rider event alone does not release funds.
- **System-calculated capped FIFO request.** The vendor does not choose an amount. The server selects the oldest eligible, unreserved whole earning records in strict FIFO order, adding each full record while the total remains at or below ₦500,000. If the next full record would exceed the cap, stop; do not split it and do not skip it to include newer records. Minimum is ₦5,000. If the batch is below minimum, wait until more eligible whole records can be included. If the oldest record itself exceeds the cap, flag for authorised finance review rather than split/skip.
- **One active request.** Only one request per vendor may remain pending review, approved but unsent, processing/in-flight or uncertain/awaiting reconciliation. Reserve selected whole earning records atomically; never reserve them twice.
- **Admin approval and payment are distinct.** An authorized admin records approve/reject actor, timestamp, decision and reason. Only an approved request may be sent to Paystack. A timeout remains reserved for reconciliation and must not be blindly retried. A failed/reversed transfer restores the exact reserved records only after safe provider-state reconciliation.
- **Payout snapshot and fees.** Persist selected earning-record IDs and amounts, bank snapshot, request identity, approver, transfer reference/status, payment confirmation and balanced journal references. Victorious MARKET bears payout transfer charges; the vendor receives the exact approved amount.
- **No rider or legacy fleet beneficiary.** New vendor-managed orders never create rider earnings, rider payout requests, logistics-company payables, or logistics payout allocations. Vendor-to-rider compensation is a private vendor responsibility outside platform financial systems. Preserve old fleet payout history as legacy-only data.

### Customer refunds and vendor recovery
- Victorious MARKET is responsible for processing the customer's approved refund. The customer should not be redirected to negotiate or collect the marketplace refund from the vendor. At launch, refund execution must be initiated through Paystack against the verified original transaction/reference and the authorized financial-operation workflow. Do not use a wallet, off-platform payment, or alternate bank destination unless a separate owner-approved policy explicitly permits that exception.
- Refund approval, Paystack execution, confirmation, journal posting, order/return-case updates, and vendor recovery are separate auditable stages. A request, rider event, complaint, or return opening is not proof that money was refunded.
- Automatic refund approval is permitted only for a verified duplicate successful charge or a verified paid order cancelled before any vendor action, under the narrow documented controls. Support may open a case, but authorised admin approves other refunds. Admin may manually open/review/approve a legitimate late refund after the 24-hour standard window; record the reason, evidence, amount and decision. A webhook is evidence, never approval.
- Paystack processing fees are borne by the platform from its own share. Do not reduce the vendor's 90% merchandise share, customer's 5% cashback allocation, or vendor's 90% delivery-fee share to pay gateway fees. Do not hard-code tax treatment before qualified Nigerian tax advice.
- Chargeback loss and vendor recovery are evidence- and fault-based. Recover from a vendor only where documented vendor fault or an approved allocation justifies it. If responsibility is ambiguous or the platform loses despite supplying required evidence, do not automatically debit the vendor; the platform bears the loss unless an authorized case decision establishes a justified recovery.
- Recover from the vendor only where the documented responsibility decision and actual allocation make the vendor responsible. If already paid, record a separate recovery receivable. The vendor may repay by verified direct bank transfer, or an authorised admin may approve an offset against held/future earnings where the vendor agreement permits it. No offset without recorded admin approval. Notify the vendor and allow a challenge; a complaint is not confirmed debt.
- Do not charge a rider, create a rider payable, or deduct rider compensation. Rider-to-vendor compensation remains outside platform financial systems.
- Refund and recovery amounts must use the immutable child-order payment/allocation, approved refundable merchandise and delivery components, prior refunds, cashback already accrued/used, discounts, and whether vendor proceeds were already disbursed. Enforce both a per-child refund ceiling (no more than that child's captured allocation) and an aggregate parent-payment ceiling (all refunds against the same Paystack capture cannot exceed the verified parent amount), under database locks and idempotency. Delivery-fee refunds are decided under the published reason-specific customer policy and applicable law; do not automatically refund or retain delivery fees for every reason.
- At launch, customer refunds must be initiated through Paystack against the verified original transaction; wallet-credit refunds remain disabled in UI, API, and service-level authorization. If Paystack emits `refund.needs-attention`, keep the customer refund obligation open and route it to authorized review. Do not send funds to a platform wallet, make an off-platform bank transfer, or use Paystack's alternate-account retry flow unless a separate owner-approved policy explicitly permits that exception. A later wallet-credit release would require a separate owner decision, explicit customer consent, a consent timestamp, admin authorization, and an audit log.
- The standard return/refund request window is **24 hours** from confirmed doorstep delivery or confirmed in-shop pickup. This is not a blanket waiver of legal rights or warranties. Admin may approve legitimate late cases. After vendor acceptance of an order, ordinary cancellation is restricted; it is not an absolute bar to remedies for non-fulfilment, non-conforming goods or non-waivable rights. Cashback maturity is a separate clock: the earliest eligible maturity is `refund_window_expires_at + 24 hours` (equivalent to `refund_window_expires_at + 1 day`), provided fulfilment is valid and no blocking return, dispute, failed-delivery case, or unconfirmed pickup remains. This additional 24-hour buffer applies only to cashback maturity; it does not extend the customer return/refund window. The 24-hour window is the standard customer return/claim window after fulfilment, not a promise that Paystack will complete a refund within 24 hours. Verified duplicate-charge corrections, pre-vendor-action cancellations, payment exceptions, chargebacks, and any non-waivable legal obligations follow their separate authorization and evidence rules and do not depend on the fulfilment-based clock.
- If a refund reverses cashback that has already been spent, reverse any unspent cashback first and track the remaining recoverable amount in a separate customer cashback-recovery ledger. At maturity, future cashback offsets this balance before any remainder becomes spendable. Do not debit the customer's cash wallet or silently create a negative spendable balance. This policy must be disclosed in the customer Terms & Conditions and reviewed for applicable consumer-policy/legal requirements before release.
- For new checkout, enable Paystack card and standard bank-transfer checkout only. Dedicated Virtual Accounts (DVA), USSD, mobile money, and other channels are deferred unless separately approved.
- The platform must complete the customer refund even when vendor recovery is disputed or delayed, subject to verified payment and approved refund controls. Track recovery separately so vendor collection failure does not leave the customer refund falsely marked pending or completed based on the wrong event.


### Binding distinction: claim window, refund processing, settlement, and cashback maturity

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
