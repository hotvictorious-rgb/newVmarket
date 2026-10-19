# VICTORIOUS MARKET — Authoritative Business Rules

**Status:** Business-rule baseline for vendor-managed fulfilment  
**Scope:** Delivery, vendor-linked riders, in-shop pickup, customer privacy, failed delivery, delivery fees, COD boundaries, and legacy logistics.  
**Authority:** These rules supersede older descriptions of platform-managed logistics, logistics-company dispatch, rider earnings, and rider payouts. Historical records must be preserved.

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
- Sets its own supported delivery areas and delivery prices.
- Coordinates delivery directly with its rider and handles compensation outside the platform.
- Handles its fulfilment work, including preparing goods, handing over parcels, receiving returned parcels, and communicating with customers.

### Rider
- Must have an active user account, an active rider profile, and exactly one active vendor relationship to be eligible for new assignments.
- Can access only orders explicitly assigned to that rider and belonging to the rider's linked vendor.
- Can see only the minimum customer information needed to complete the assigned delivery (see Customer Privacy).
- Can update allowed operational states, record failed attempts, submit required proof, and complete authorised handover verification.
- Cannot assign orders to themselves, browse unassigned orders, transfer orders to another vendor, change product or delivery prices, see payment status/amounts/commissions, issue wallet credit, initiate refunds, access payouts, or perform financial actions.
- Cannot fulfil in-shop pickup as a delivery task. The customer pickup pass is verified at the vendor shop.

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
- Delivery confirmation is protected by the approved customer verification mechanism (OTP/pass as configured) and required proof evidence.
- A rider's delivery confirmation is an operational event. It does not grant the rider financial permissions or let the rider directly edit a ledger, approve a refund, alter prices, or request payouts. An independent, authorized financial service may consume a verified delivery event as an input to the published vendor-settlement policy; that financial action must be separately validated, idempotent, and auditable.

### In-shop pickup
- In-shop pickup remains fully supported as a first-class fulfilment type.
- The customer collects the order at the owning vendor's physical shop/counter.
- Delivery fee is exactly zero. Checkout and backend enforce zero regardless of client-supplied amount or vendor delivery-zone setting.
- The customer receives a pickup pass in the format RES-XXXXXX. Store only a secure representation where feasible; do not expose the pass in rider lists, logs, or unrelated API responses.
- The vendor verifies the pickup pass and records handover. A delivery rider is not required and no delivery assignment, delivery fee, rider compensation, or logistics payout is created.
- Pickup status and doorstep delivery status remain distinct. Pickup must not be forced through out-for-delivery or doorstep-only OTP/proof requirements.
- Order/payment expiry must not silently invalidate a paid order or create a financial refund. Expiry, stock release, payment verification, and refund are separate auditable operations.

## 6. Vendor-defined delivery areas and fees

1. Vendors configure destinations they serve and the delivery fee for each supported destination. State-level fallback may be supported; an explicit LGA fee takes precedence over a state-level fee.
2. A doorstep-delivery destination without a valid vendor-configured fee is unavailable. Fees must be server-validated and cannot be trusted from the client.
3. If the existing commercial rule requires a positive fee for doorstep delivery, enforce a fee greater than zero for that enabled destination. This rule does not apply to in-shop pickup, whose fee is always zero.
4. The chosen fulfilment type, destination, quoted fee, and relevant fee-rule version are snapshotted to the vendor child order at checkout. Later vendor settings changes do not rewrite existing orders.
5. Each child vendor order calculates its own products, discounts, delivery fee, commission, cashback eligibility, and vendor allocation. The parent checkout must not collapse multiple vendors' delivery fees into an untraceable shared amount.
6. The delivery fee is a vendor-order charge, not a logistics-company fare. No rider share/payable is created by the platform. The vendor is responsible for rider compensation outside the platform.
7. The existing merchandise allocation is 90% vendor, 5% customer cashback, and 5% platform share on eligible merchandise value. The delivery-fee allocation is 90% vendor and 10% platform share. Cashback applies to eligible product value only and never to delivery fees. For example, with ₦90,000 eligible products and ₦10,000 delivery, the merchandise allocation is ₦81,000 vendor / ₦4,500 cashback / ₦4,500 platform, and the delivery allocation is ₦9,000 vendor / ₦1,000 platform. The owning vendor separately pays its rider outside the platform. Refunds must reverse the correct merchandise split and/or delivery-fee split according to the actual approved refundable components. Any future change must be an explicit business decision updated consistently across documents and code.

## 7. Customer privacy and rider data minimisation

The rider interface and rider API must reveal only information necessary for the rider to perform the assigned delivery.

### Permitted customer-facing delivery information
- Customer name.
- Delivery address and only the location instructions necessary to reach that address.
- A **Call Customer** action.

### Prohibited rider access
- Raw customer phone number in API payloads, page markup, logs, analytics, or exports. The Call Customer action should use a protected call flow or privacy-preserving relay; do not render the number merely to make the button work.
- Customer financial data: payment status, total paid, outstanding balance, cash amount due, refunds, wallet balance, card details, bank details, or payment references.
- Platform commission, vendor margin, cashback, vendor payable, settlement state, rider earnings, payout balances, ledger/journal entries, or financial reports.
- Card PAN, CVV, payment tokens, or other payment credentials under all circumstances.
- Pickup/delivery OTPs or reusable secrets in order-list APIs, history APIs, logs, analytics, or screenshots. A verification secret is entered only into the specific action that validates it and is never returned to the rider.
- Other customers' orders, another vendor's orders, unassigned orders, or a customer's unrelated profile data.

Operational order information such as order identifier, parcel/product description and quantity, shop pickup location, destination, allowed fulfilment state, and proof requirements may be exposed only where required to complete the assigned job. Financial fields are not operational fields and must be excluded by default.

## 8. Delivery evidence and operational status

1. Record actor, timestamp, order, action, and evidence reference for assignment, reassignment, vendor handover, failed attempt, return-to-vendor, and delivery completion.
2. Use the configured customer handover pass/OTP and proof-photo requirements. Never return verification secrets in dashboard/list responses.
3. Failed-attempt reasons should be enumerated and auditable (for example, customer unreachable, recipient unavailable, wrong address, access blocked, or customer rescheduled).
4. A failed attempt is an operational event, not a financial decision. It must not automatically create an available customer wallet credit, mark a refund paid, credit a logistics company, create a rider payable, or release vendor funds.
5. Follow the configured failed-delivery policy: record the attempt and evidence, allow vendor-directed retry where permitted, and return the parcel to its owning vendor after the policy threshold. Record custody and return confirmation. The vendor/platform dispute workflow decides any refund or compensation separately.
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

COD must not reintroduce rider financial accounts or expose customer financial data to riders. The rider is operational-only and must not see the amount due or payment status.

If COD is enabled by the business, the workflow must define a vendor-owned collection and reconciliation method that does not give the rider a financial dashboard, wallet, payout entitlement, or access to customer financial details. Until that workflow is explicitly verified, do not enable COD for rider-assigned orders by relying on the rider to calculate, collect, reconcile, or remit a displayed amount. Online prepaid fulfilment and in-shop pickup must continue to work independently.

## 11. Financial separation

- Rider assignment, availability, location, pickup, delivery, failed attempts, and proof are operational records.
- Riders are not financial beneficiaries in the marketplace ledger.
- Do not create rider payout requests, rider wallets, rider earnings, logistics-company payable entries, or delivery-fee split journals for new vendor-managed deliveries.
- Rider endpoints must not directly mutate financial ledgers, approve refunds, or issue credits. The authorized financial engine may use a verified operational event as an input only where the published settlement/refund policy explicitly requires it.
- Existing vendor settlement, platform commission, cashback, payment verification, refund, and payout controls remain in their established financial services.
- If historical orders contain logistics-company financial entries, preserve and reconcile them under legacy rules. Do not reclassify historical amounts as vendor income or delete journal entries.
- All new financial movements must remain idempotent, balanced, authorized, and reconcilable.

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
- In-shop pickup costs zero, uses RES-XXXXXX, is verified at the vendor shop, and does not create a rider assignment or delivery payout.
- Doorstep delivery requires correct proof/OTP but does not expose the OTP in list/history payloads.
- Failed attempts create operational records only; no automatic wallet credit, rider/logistics payable, refund, or vendor settlement release.
- Repeated delivery callbacks, failed attempts, assignment requests, and pickup confirmation cannot duplicate state transitions or financial entries.
- Historical logistics records remain accessible for authorized audit and are not used to route new deliveries.
- COD remains disabled unless its vendor-owned collection and reconciliation design passes explicit privacy and financial-control tests.

## 14. Required implementation work (not yet a claim of completion)

The current repository must be audited and updated to match this specification. In particular:
- Enforce vendor-only rider ownership and assignment in backend policies/controllers/services, not just UI.
- Remove logistics-company affiliation as an eligible new-dispatch path while preserving historical rows.
- Remove financial fields and raw phone data from rider API projections; add a privacy-preserving Call Customer action.
- Remove automatic wallet credits and logistics-company payout journal entries from failed-delivery code paths.
- Keep pickup and doorstep states independent and enforce zero pickup fee server-side.
- Disable rider earnings/wallet/payout screens and their routes for the new model; retain legacy financial history read-only for authorized roles.
- Update vendor rider management/assignment screens, customer checkout, admin controls, API contracts, tests, and documentation.
- Add regression, authorization, privacy, COD, failed-delivery, multi-vendor, migration, and historical-data tests before any release.

## 15. Source-of-truth and release control

These rules are the authoritative business requirements for vendor-managed fulfilment. Older documents that describe platform-managed logistics, logistics-provider revenue shares, rider earnings, rider payouts, or an unresolved choice to retire/keep the rider app must be updated to this model. Business-rule changes require explicit owner approval and consistent updates to API contracts, database rules, implementation, tests, and customer/vendor policies.

Do not merge to main without the user's explicit approval.
