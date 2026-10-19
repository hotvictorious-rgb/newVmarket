# VICTORIOUS MARKET — Authoritative Business Rules

**Status:** Business-rule baseline for vendor-managed fulfilment  
**Scope:** Delivery, vendor-linked riders, in-shop pickup, customer privacy, failed delivery, delivery fees, COD boundaries, and legacy logistics.  
**Authority:** These rules supersede older descriptions of platform-managed logistics, logistics-company dispatch, rider earnings, and rider payouts. Historical records must be preserved.

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
| 8 | Delivery fees/zones — A | Each vendor configures supported delivery areas and destination fees; unsupported destinations fail closed. |
| 9 | Delivery-fee split — B | Delivery fee allocation is 90% to the vendor and 10% platform delivery-fee share; it does not create a rider payable. |
| 10 | Vendor-to-rider compensation — A | The vendor compensates its rider directly outside platform financial systems. |
| 11 | Cash on delivery — A | Rider stays operational-only and cannot see amount due/payment status. Do not enable rider-assigned COD until a vendor-owned collection/reconciliation flow passes privacy and financial controls. |
| 12 | Delivery proof — A | Use the approved customer handover pass/OTP and required proof evidence; do not expose secrets in list/history APIs. |
| 13 | Failed delivery — B | Record attempts and proof, apply the configured threshold, return the parcel to its owning vendor, and review any financial remedy separately. |
| 14 | Admin boundaries — A | Platform admin handles oversight/security/disputes, not dispatch or rider pay. |
| 15 | Legacy logistics records — A | Preserve history for audit; disable legacy fleet paths for new assignments after safe dependency mapping. |


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

## 14. Required implementation work and source-audit findings

**Implementation status:** The rules above are requirements, not proof that every route and service currently complies. A source review of the audit branch identified the following concrete gaps. Treat them as open until each is corrected and covered by tests.

### P0 — Privacy and financial-boundary violations

1. **Rider API guard still accepts legacy fleet affiliation.** In `backend/app/Http/Controllers/Api/RiderTerminalApiController.php`, `guardRiderWithLogistics()` permits a rider with either `vendor_id` or `logistics_company_id`. New delivery work must require an active rider profile linked to exactly one active vendor; fleet-only riders must be denied for new assignments and actions. Preserve legacy links for history, but do not treat them as eligible ownership.
2. **Rider order projection leaks restricted fields.** `formatOrderForRider()` in the same controller includes `payment_status`, `delivery_fee`, and the customer's raw phone number. The dashboard, active-order, history, and return payloads must be reviewed together. Remove these fields from rider responses, HTML, embedded page data, logs, analytics, exports, and error responses. Return only customer name, delivery address, and a protected **Call Customer** action as customer information, plus minimum operational parcel/order data.
3. **Customer phone privacy is not satisfied by hiding the number visually.** The controller eager-loads the customer's phone and returns it in JSON; the active-returns response also includes `customer_phone`. Implement a privacy-preserving call relay/action so the rider can initiate a call without the raw number being present in the rider response or page markup. Test the serialized response and rendered HTML, not just visual masking.
4. **Failed-delivery endpoint performs prohibited financial actions.** `recordDeliveryAttemptFailed()` in `RiderTerminalApiController.php` creates a `CustomerWalletCredit` and writes a `logistics_payable` journal entry for the legacy fleet. A rider's failed-attempt action must record the attempt, reason, timestamp, evidence, and custody/return state only. It must not create a spendable wallet credit, refund, vendor-fund release, logistics payable, or rider compensation. Route customer remedies through the authorized review and financial-operation workflow.
5. **Doorstep handover mixes operational evidence with financial posting.** `backend/app/Services/DoorstepHandshakeService.php` creates customer wallet-credit records/reservations and financial journal entries inside a rider-triggered handover, and contains a legacy fleet delivery-fee path. Split the process: the rider action records verified handover/accepted-rejected items and evidence; a separately authorized, idempotent financial service evaluates an approved remedy and posts balanced entries. Do not make a credit spendable merely because a rider submitted a handover.
6. **Rider return payload also leaks customer phone.** `activeReturns()` returns `customer_phone`; remove it and route customer contact through the same protected Call Customer mechanism.

### P1 — Ownership, assignment, and fulfilment invariants

7. **Enforce ownership on every write path.** Vendor rider creation and assignment endpoints must derive vendor identity from the authenticated vendor session, not trust a client-supplied `vendor_id`. On assignment, reassignment, pickup/custody, arrival, failed attempt, delivery confirmation, and return handover, validate the order's vendor, the rider's single vendor ownership, both account statuses, current assignment, and allowed order state on the server.
8. **Enforce a single active vendor owner.** `RiderProfile` retains both `vendor_id` and `logistics_company_id` for legacy compatibility. For new fulfilment, require exactly one active vendor owner and reject ambiguous profiles with both links, no link, inactive ownership, or fleet-only ownership. Do not delete legacy columns until dependencies and history are safely mapped.
9. **Custody is a concurrency boundary.** Assignment/reassignment must be atomic and audited. Reassignment is permitted before custody; after custody begins, require a recorded return-to-vendor or explicit custody-transfer process. Concurrent assignment requests must not leave two active riders for one child order.
10. **In-shop pickup is not doorstep delivery.** Enforce `in_shop_pickup` as a first-class fulfilment type: zero delivery fee server-side, `RES-XXXXXX` pickup pass, vendor-counter verification, separate status transitions, and no rider assignment or delivery payout. Do not confuse a rider's parcel pickup from the vendor for doorstep delivery with a customer's own in-shop pickup.
11. **Multi-vendor isolation.** Preserve the parent checkout with separate vendor child orders. Each child has one owning vendor, one fulfilment type, its own vendor-configured fee if doorstep delivery, and at most one active rider from that vendor. Prove that a rider cannot access another vendor's child order by guessing an ID or changing request parameters.
12. **COD stays disabled for rider-assigned orders.** Do not expose amount due or payment state to riders. Do not rely on riders to calculate, collect, hold, reconcile, or remit customer money. COD can be reconsidered only after an explicitly approved vendor-owned collection/reconciliation design passes privacy, accounting, and authorization tests.

### P1 — Retire active fleet/rider financial surfaces safely

13. **Disable new logistics-company fulfilment and payouts.** The current web routes include logistics fleet dashboards, riders, waybills, collection points, bank updates, wallet/payout requests, and admin logistics-payout actions. Remove them from new-order assignment and new financial workflows. Keep historical data accessible only to authorized roles for audit/reconciliation; do not delete old financial records or reclassify them as vendor/rider money.
14. **Remove rider earnings and financial screens.** The web routes currently include `/rider/earnings`; rider views and navigation must not present earnings, wallet, payout, payment, commission, or financial summaries. Rider dashboards may show only operational telemetry such as assigned jobs and completed trips.
15. **Audit all API and web response paths.** Inspect route middleware, controller responses, Blade templates, JavaScript hydration, notification templates, logs, exports, background jobs, and admin previews for raw customer phone, payment status, amounts, delivery fees, cashback, commissions, vendor settlements, or financial ledger data reaching rider contexts.
16. **Keep vendor-to-rider compensation off-platform.** No rider wallet, rider earning ledger, rider payable, rider payout request, platform-funded rider compensation, or rider financial reconciliation should be created. Vendor compensation is a private vendor-to-rider arrangement outside Victorious MARKET's rider financial systems.

### P2 — Policy, audit, and release proof

17. **Delivery proof remains operational evidence.** Keep the configured customer handover pass/OTP and required proof. Do not include OTPs or reusable secrets in order-list/history APIs, logs, analytics, screenshots, or unrelated responses. A proof event does not independently decide product quality, refund entitlement, or financial settlement.
18. **Failed-delivery policy must be deterministic.** Define and enforce the attempt threshold, enumerated reasons, customer rescheduling rules, return-to-vendor custody confirmation, and the resulting order state. The attempt threshold must not silently create a wallet credit, refund, payout, or vendor-fund release.
19. **Reconcile actual financial allocation.** Preserve the approved 90/5/5 allocation on eligible merchandise (vendor/cashback/platform) and 90/10 allocation on the delivery fee (vendor/platform). Cashback excludes delivery. Refunds must use the actual child-order allocations and approved refundable components, with idempotent journal reversals and prior-refund/settlement checks.
20. **Preserve historical compatibility.** Map old routes, API consumers, jobs, webhooks, views, tests, payout records, and order histories before removing legacy dependencies. Keep historical logistics views read-only where possible; historical financial records must remain auditable.
21. **Add adversarial regression tests.** At minimum test: vendor A cannot assign vendor B's order/rider; fleet-only and ambiguous riders are denied new work; inactive vendor/rider cannot act; two concurrent assignments cannot both win; reassignment after custody is blocked; rider APIs and HTML contain no prohibited fields or raw phone; Call Customer works without exposing the number; failed delivery and rider handover create no direct financial postings; pickup is zero-fee and rider-free; COD remains disabled; duplicate callbacks do not duplicate operations; historical logistics data remains intact.
22. **Keep all documents and user-facing policies aligned.** A prior README version described third-party fleet delivery, fleet delivery-fee payouts, active logistics-company operations, and immediate wallet credits from rider handover. The README's main overview, allocation, fulfilment, custody, and actor-role sections have now been aligned on this audit branch. Before release, scan the remaining documentation, API contracts, onboarding text, customer/vendor policies, notifications, and tests for any stale statement or UI that reintroduces those retired behaviours. Keep `VICTORIOUS_MARKET.md` and `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md` aligned with this document.
23. **Verify before release.** Run targeted rider API/privacy, delivery handover, failed-delivery, pickup, vendor assignment, and historical compatibility tests, then the full backend test suite. Report actual results. Do not claim the rules are implemented merely because the documentation or a test fixture describes them.

## 15. Source-of-truth and release control

These rules are the authoritative business requirements for vendor-managed fulfilment. Older documents that describe platform-managed logistics, logistics-provider revenue shares, rider earnings, rider payouts, or an unresolved choice to retire/keep the rider app must be updated to this model. Business-rule changes require explicit owner approval and consistent updates to API contracts, database rules, implementation, tests, and customer/vendor policies.

Do not merge to main without the user's explicit approval.
