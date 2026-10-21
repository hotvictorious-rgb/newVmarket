# VICTORIOUS MARKET — Authoritative Business Rules

> **BINDING GEOGRAPHIC POLICY (21 October 2026):** Victorious MARKET sources only from approved supplier shops physically located in Uyo LGA, Akwa Ibom State, and delivers only to customer destinations within Uyo LGA. No outside-Uyo sourcing or delivery. Victorious MARKET is the sole public retailer; supplier identities, wholesale prices and addresses are private. Standard checkout is prepaid Paystack doorstep delivery within Uyo; no COD, public pickup reservations, collection-point delivery, or inter-LGA/inter-state routes. One delivery base fee per order plus applicable bulky surcharge. See [VM Uyo-Only Retailer Policy](docs/architecture/VM_UYO_ONLY_RETAILER_POLICY.md) for the authoritative rules. This overrides conflicting legacy assumptions in this document.



> **Current delivery-first fulfilment policy (21 October 2026):** Victorious MARKET is one customer-facing retailer using private suppliers behind the scenes. Doorstep delivery is the only standard online fulfilment method. Do not offer automated in-shop pickup reservations, supplier/shop selection, or supplier-address disclosure in customer checkout. Customers who prefer pickup must contact VM support; support may confirm an exception only after checking the complete order, availability, physical readiness, approved collection point, payment arrangement and customer agreement. Never disclose a collection address or say an order is ready before confirmation. For multi-product orders, VM coordinates the complete order; customers must not be sent to multiple suppliers by default. Legacy pickup/multi-vendor code and historical records remain compatibility/audit material until safely retired; documentation alignment alone does not mean runtime routes or production legal terms have been disabled. See `docs/architecture/RETAILER_FULFILMENT_SIMPLIFICATION_2026-10-21.md` for the binding workflow and implementation acceptance criteria.

> **Superseding owner decision (21 October 2026):** Victorious MARKET is a retailer operating centrally from Uyo, Akwa Ibom State. Suppliers are private product sources, not public marketplace sellers. They submit offers for VM approval or map offers to existing canonical listings. VM controls customer-facing prices and delivery, uses approved logistics companies, and requires every rider to be linked to exactly one active logistics company. Authorized supplier-shop pickup remains supported using the existing customer verification and photo flow. Supplier payment eligibility begins only after verified successful customer delivery or confirmed pickup and reconciliation. Earlier multi-vendor, vendor-managed delivery, vendor commission/payout and vendor-linked rider rules in this document are legacy assumptions and are superseded for new retailer-model work. Do not infer that code has been migrated. See [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](../RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md).

**Status:** Authoritative owner-approved retailer and central-fulfilment requirements; legacy vendor-managed sections are historical only
**Last reviewed:** 21 October 2026
**Review note:** The rules are approved requirements; Section 14 lists implementation gaps found in the audit branch, not completed fixes.  
**Scope:** Delivery, vendor-linked riders, in-shop pickup, customer privacy, failed delivery, delivery fees, COD boundaries, and legacy logistics.  
**Authority:** `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md` is the single source of truth for current launch rules. `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md` describes the operating model and `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md` describes payment/refund controls; neither may contradict the system rules. Older marketplace sections in this file are historical-only where they conflict. Preserve historical records. This document records requirements, not proof of implementation; runtime alignment remains unverified until each finding is tested.

## Decision record — retailer and central fulfilment model

This decision record supersedes older vendor-delivery Q&A decisions for new work.

## 1. Non-negotiable operating model

1. **Retailer of Record:** VM is the retailer and the only customer-facing seller in the product/order experience. Customers buy directly from Victorious MARKET.
2. **Admin-Only Supplier Onboarding:** Suppliers must only be created and onboarded by Victorious MARKET Admin staff. Public self-registration for suppliers or vendors is strictly prohibited across all web and mobile endpoints.
3. **Private Wholesale Sourcing:** Suppliers provide physical products, inventory availability in Uyo, and wholesale cost prices. VM adds its profit margin and publishes the canonical public retail selling price (`Retail Price = Wholesale Cost + VM Profit Margin`). Supplier identity and wholesale costs are strictly private.
4. **Automated Sourcing Assignment & Admin Override:** When an order is placed (upon verified payment for delivery, or reservation created for in-shop pickup), the system **automatically assigns the order/reservation to the accredited supplier that has the product in stock and offers the cheapest wholesale cost price (`cost_price`)**. The Admin console maintains complete override authority: Admin can review the auto-assignment and reassign to any other accredited supplier or branch at any time.
5. **Doorstep Delivery Flow (Pay First, Sourced & Dispatched):** Customer pays the full retail selling price plus delivery fee upfront online via Paystack. Strictly NO Cash on Delivery (COD). The system automatically assigns the order to the cheapest in-stock supplier in Uyo (with Admin override capability). The assigned supplier inspects inventory and confirms physical availability in their portal. Once confirmed, Admin assigns the order to an accredited Logistics Company. The Logistics Company dispatches its active fleet rider to pick up the package from the supplier's shop and deliver it to the customer. Delivery completion strictly requires customer verification OTP (`delivery_otp`) plus doorstep photo proof.
6. **Pickup request (support-managed exception only):** Customers cannot reserve online or receive an automatically assigned supplier branch. They must contact Victorious MARKET support. Support verifies every item in the complete order, physical readiness at one approved collection point, payment arrangements and customer agreement before confirming pickup or disclosing collection instructions. Never disclose supplier addresses early or direct customers to multiple suppliers by default.
7. **Operating Base:** VM operates centrally from Uyo, Akwa Ibom State and serves destinations supported by local supplier sourcing and approved logistics coverage.
8. **Admin-Only Logistics Companies & Fleet Rider Management:** Logistics companies can strictly only be created and onboarded by Victorious MARKET Super Admin. Zero public self-registration exists. Once accredited, logistics companies manage their own fleet of dispatch riders (adding couriers, toggling active duty status, and assigning delivery corridors). Every delivery rider must be active and linked to exactly one accredited logistics company. Supplier-owned or vendor-linked riders cannot receive new VM delivery assignments.
9. **Supplier Payment Eligibility:** Suppliers are paid their agreed wholesale cost strictly after verified successful customer delivery or confirmed in-shop pickup. Order placement, customer payment, or rider collection alone never unlocks supplier payment.
10. **Retail accounting (current retailer model):** `Retail Price - Supplier Wholesale Cost = VM Gross Margin` before other costs. The old 90/5/5 merchandise split is retired; supplier payables use the immutable agreed wholesale cost. Cashback remains active at 5% on eligible post-discount merchandise actually funded by real money through verified Paystack payment, after redeemed cashback and excluding delivery. Existing terms remain: fulfilment + 24-hour claim window + separate 24-hour maturity buffer with no blocking case; 365-day validity starts at maturity; redemption capped at 50% of cart merchandise subtotal; unspent cashback reverses through ledger entries and spent-cashback recovery may offset only future matured cashback. Cashback is funded from VM gross margin, never supplier cost. For new doorstep orders, logistics-company payable defaults to 90% of the snapshotted customer delivery fee, with 10% default VM delivery margin. Authorized admin may override the company amount higher or lower with reason, approver, timestamp and original/final amounts recorded. This payable belongs to the logistics company, not directly to its rider; amounts above the fee must be recorded as VM delivery subsidy/expense.
11. **Financial Integrity:** Payment verification, webhook authentication, idempotency, refund ceilings, immutable double-entry journals, and audit trails remain mandatory. Existing database schema is adapted additively without breaking historical data.



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

- Can strictly only be created and onboarded by Super Admin via the Logistics Companies Desk (zero public registration).
- Manages their own fleet of dispatch couriers via their fleet desk (adding riders, toggling duty status, assigning corridors).
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

### Doorstep delivery (Pay First Online)

- **Mandatory Pre-payment:** Customer must pay the full retail selling price plus delivery fee online via Paystack. Strictly NO Cash on Delivery (COD).
- VM confirms destination coverage from Uyo, sources the product from the cheapest available supplier offer, and assigns an accredited logistics partner rider.
- Supplier readiness and rider collection are internal custody events, not customer completion.
- Customer handover requires server-verified one-time customer OTP (`delivery_otp`) plus a mandatory doorstep delivery photo.
- Failed attempts and returns to origin are tracked separately; failed delivery does not grant automatic refund or release supplier payable.

### Pickup requests (support-managed exception only)

Doorstep delivery is the only standard online fulfilment method. The website and public APIs must not offer automated in-shop pickup reservations, supplier/shop selection or early supplier-address disclosure. Customers who prefer pickup must contact Victorious MARKET support.

Support can confirm an exception only after checking the complete order, confirmed availability of every item, physical readiness at one approved collection point, payment arrangements and customer agreement. Record the staff actor, timestamp, approved location, decision and reason. Do not direct customers to multiple supplier shops by default; consolidate internally or offer a feasible delivery plan. Do not silently add fees or alter the checkout delivery-fee snapshot.

Existing pickup reservation tables, states, endpoints and historical records are legacy compatibility only until safely retired. They may be used to resolve existing obligations but must not create new public reservations. Disable new public writes and early address disclosure before removing dependent code; preserve historical reads and financial audit trails.
## 6. VM-defined delivery coverage and fees

1. VM controls customer-facing service coverage and delivery-fee quotes; suppliers do not configure marketplace delivery prices.
2. Uyo, Akwa Ibom State is the central operating base. VM serves any destination it can currently cover through sourcing and approved logistics arrangements, not every location by default.
3. Checkout must validate a current coverage rule, carrier availability, applicable customer fee and order snapshot. Missing, expired or contradictory coverage data means delivery is unavailable until resolved.
4. Snapshot destination, fulfilment choice, quoted customer fee and applicable fee-rule version on the accepted order.
5. Default logistics-company payable is 90% of the customer's snapshotted delivery fee; VM retains the remaining 10% by default. Admin may override the amount higher or lower to reflect real route/distance/package/fuel/traffic/urgency and negotiated Nigerian logistics terms. Record original amount, final amount, reason, admin and timestamp; no direct rider payable is created by this rule. An override above the customer fee must record an explicit VM-funded delivery subsidy.

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
5. After doorstep rejection or failed delivery, record the outcome and use an authorised, auditable return-to-source workflow to return the parcel to the selected supplier/shop. Victorious MARKET operations coordinate the return through the assigned logistics company or another approved custody process; supplier receipt must be recorded. The supplier may offer a corrected item or replacement redelivery only with customer agreement. Record every custody transfer and any agreed redelivery. Refund/compensation is separately reviewed.
6. Delivery proof supports an investigation but is not, by itself, proof that the item was genuine, defect-free, or that a refund is owed.

## 9. Failed delivery and customer remedies

1. Failed delivery does not automatically mean the customer receives an immediate wallet credit or cash refund.
2. The system records each attempt and reason, preserves parcel custody history, and applies the configured attempt limit consistently.
3. When the limit is reached, the parcel enters an authorised return-to-source workflow coordinated by Victorious MARKET. An approved logistics actor returns it to the selected supplier/shop and the supplier confirms receipt. The order enters an explicit failed/returned/review state; it must not be marked successfully delivered.
4. Any rescheduling, redelivery fee, cancellation, refund, or vendor recovery is decided under the applicable published customer policy and platform review. It must be linked to the order and use the existing financial-operation/refund engine.
5. A customer refund is based on the verified original payment and approved refund amount, with exact allocation of merchandise, delivery fee, discounts, commission, cashback, previous refunds, and vendor amounts already paid.
6. No failed-delivery handler may directly create spendable wallet credit or a logistics/rider journal entry. Financial actions require the authorized financial service and an auditable decision.
7. Delivery fees and return-transport costs are separate concepts. Do not assume the original delivery fee is always retained or refunded; apply the published reason-specific policy and applicable law.

## 10. Cash on delivery (COD) boundary

**COD is disabled at launch for the entire platform**, not merely for rider-assigned orders. Checkout, APIs, payment-method flags, legacy payment methods, vendor settings, and direct requests must all reject COD. Only two customer payment flows are permitted:
1. **Doorstep Delivery — Pay First Online:** Customer must pay the full retail selling price plus delivery fee upfront online via Paystack checkout. Cash on delivery is completely rejected.
2. **Pickup exception:** Pickup is not a standard checkout path and is not automatically reserved. Customers must contact support; support confirms only after checking the complete order, physical readiness at one approved point, payment arrangements and customer agreement. Do not disclose addresses or direct customers to travel before confirmation.

All payments must be processed and recorded through Victorious MARKET's authorised payment workflow. Suppliers may not accept cash, private bank transfers or other off-platform payment for orders, and must not release goods until the platform verifies successful payment and counter OTP. Launch rails are Paystack card and standard bank transfer only unless separately approved. Riders remain operational-only and must never see payment status/amount due or collect/reconcile customer money through platform rider accounts.

## 11. Financial separation

- Customer payment, supplier payable, logistics-company charge/payable, delivery/pickup status, refund status and any customer cashback liability are distinct state machines.
- A supplier payable represents the agreed purchase cost for goods supplied to VM, not a marketplace commission or vendor earning.
- Supplier payment eligibility begins only after verified successful customer delivery or confirmed customer pickup and the required order/amount reconciliation. Eligibility is not an automatic transfer.
- Order placement, customer payment, supplier acceptance/readiness, VM collection, rider assignment, rider collection, delivery attempt or unverified completion does not independently make the supplier payable eligible.
- Supplier payable, authorization, payment attempt, provider confirmation, reversal and reconciliation must be separately linked and idempotent. Timeout means uncertain and requires reconciliation before retry.
- VM remains responsible for a valid customer refund even if a supplier was already paid. Supplier recovery requires evidence-backed responsibility and contractual recoverability; a complaint alone is not debt, and recovery must not delay a customer refund.
- Do not create rider wallets, rider earnings or rider payout requests. For new doorstep orders, logistics-company payable defaults to 90% of the snapshotted delivery fee, with 10% default VM delivery margin. Authorized admins may override the company amount higher or lower with before/final amount, reason, admin identity and timestamp audited. Amounts above the customer fee are recorded as a VM-funded delivery subsidy/expense.
- The old 90% vendor / 5% cashback / 5% platform merchandise allocation and vendor delivery earnings are retired for new retailer transactions. Cashback remains active at 5% under the approved formula and existing terms in Rule 10. Vendor withdrawal thresholds and vendor debt-recovery rules apply only to historical records.
- Preserve old vendor settlements, cashback, logistics payouts and financial history as legacy records. Do not reclassify or delete historical journal entries without a documented migration and accounting approval.
- All new financial movements remain idempotent, balanced, authorized, immutable and reconcilable. Balanced books do not prove cash availability; recognize loss/liquidity shortfall honestly.
- Customer payment/refund security rules remain: verify Paystack status, exact amount, currency, reference and order identity; refunds require applicable authorization and original-route handling; refund approval is distinct from gateway completion; enforce component and aggregate ceilings.

## 12. Legacy migration and compatibility

1. Keep historical logistics company profiles, payout requests, fleet rider links, delivery lanes, collection points, and ledger entries for audit/history unless a separately approved data-retention plan says otherwise.
2. Prevent legacy logistics roles from receiving new vendor-managed assignments. Legacy data may be shown in restricted read-only historical views.
3. Do not drop logistics-company columns, legacy tables, old routes, or columns until dependency mapping confirms no production, API, job, webhook, report, or historical-order dependency remains.
4. Use additive migrations and backfills. Identify vendor ownership for eligible active riders; unresolved/ambiguous riders are suspended from new assignments until manually resolved, not guessed.
5. Never convert a legacy logistics-company payout into a rider payout or vendor payout without verified financial reconciliation and explicit approval.

## Retailer-model supersession notice (binding for all new transactions)

The numbered marketplace/vendor-managed-delivery rules and the acceptance tests below originated in a legacy operating model. They are retained as migration/audit history and must **not** be treated as current requirements where they conflict with the owner-confirmed retailer model or `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`.

For new retailer-model work, apply these controlling rules:
- Victorious MARKET is the single customer-facing retailer; suppliers are private sourcing partners. Do not expose supplier identity/cost or require customers to select a supplier/shop.
- VM centrally coordinates doorstep delivery through approved logistics companies. Every assigned rider must be active and linked to exactly one active logistics company. Vendor-owned rider assignment and vendor-managed delivery are not valid for new work.
- Supplier purchase cost is an item-level payable, not a commission. The former 90/5/5 merchandise and 90/10 delivery splits, vendor FIFO payout thresholds and vendor payout-request rules do not govern new supplier or logistics-company payments.
- Default logistics-company payable is 90% of the snapshotted customer delivery fee; the remaining 10% is default VM delivery margin. Admin may approve a higher or lower company amount with reason, admin identity and timestamp audited. Do not pay this share directly to the rider.
- Preserve historical marketplace allocations, cashback, vendor earnings, payouts, foreign keys and audit records. Do not silently recalculate or relabel them as retailer transactions.
- Cashback remains active at 5% of eligible post-discount merchandise actually funded by real Paystack money after redeemed cashback, excluding delivery. Existing maturity, 365-day validity-from-maturity, 50% cart merchandise redemption cap, refund reversal and spent-cashback recovery terms remain in force.
- VM executes approved customer refunds through the authorized workflow; supplier recovery is separate, evidence-backed and contractual, and never delays customer protection.
- The pickup flow remains unpaid at reservation, inspect-before-pay, VM-authorized Paystack at the counter, and requires distinct reservation pass, pickup OTP and photo proof.

Where the legacy text below conflicts with these rules, it is a historical finding to migrate—not permission to preserve the old behaviour for new orders.

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
12. **Doorstep COD is disabled.** Permit only pay-before-delivery through Victorious MARKET for standard online checkout. Pickup is support-managed by exception only and is not created through a public reservation flow. Reject all off-platform payment. Do not expose payment state or amount due to riders.

### P1 — Retire active fleet/rider financial surfaces safely

13. **Disable new logistics-company fulfilment and payouts.** The current web routes include logistics fleet dashboards, riders, waybills, collection points, bank updates, wallet/payout requests, and admin logistics-payout actions. Remove them from new-order assignment and new financial workflows. Keep historical data accessible only to authorized roles for audit/reconciliation; do not delete old financial records or reclassify them as vendor/rider money.
14. **Remove rider earnings and financial screens.** The web routes currently include `/rider/earnings`; rider views and navigation must not present earnings, wallet, payout, payment, commission, or financial summaries. Rider dashboards may show only operational telemetry such as assigned jobs and completed trips.
15. **Audit all API and web response paths.** Inspect route middleware, controller responses, Blade templates, JavaScript hydration, notification templates, logs, exports, background jobs, and admin previews for raw customer phone, customer IDs, payment status, amounts, delivery fees, cashback, commissions, vendor settlements, or financial ledger data reaching rider contexts. `activeReturns()` currently returns `total_refundable_amount`, and the doorstep-handover response returns `accepted_amount`, `rejected_amount`, and `wallet_credited`; these are financial data and must be removed from rider-facing payloads.
16. **Keep vendor-to-rider compensation off-platform.** No rider wallet, rider earning ledger, rider payable, rider payout request, platform-funded rider compensation, or rider financial reconciliation should be created. Vendor compensation is a private vendor-to-rider arrangement outside Victorious MARKET's rider financial systems.

17. **Vendor earnings and payouts do not yet meet the approved granularity and full-withdrawal contract.** The current balance and payout flow aggregates vendor proceeds at order level (vendor_net_90 / remaining_payable_to_vendor), accepts a vendor-supplied partial amount, and the admin disbursement action combines payout approval with transfer initiation. Add an immutable earning ledger with one merchandise earning per order-item line and a separate delivery earning per vendor child order. The request must snapshot/reserve all eligible withdrawable earning records, be exactly equal to the server-computed full balance, and reject partial/overlapping requests. Split admin approval from admin payment initiation, require approved state before calling Paystack, and reconcile transfer success/failure/reversal against the exact earning allocations. Keep any order-level aggregate as a derived/reporting total, not the source of truth.

### P2 — Policy, audit, and release proof

17. **Delivery proof remains operational evidence.** Keep the configured customer handover pass/OTP and required proof. Do not include OTPs or reusable secrets in order-list/history APIs, logs, analytics, screenshots, or unrelated responses. A proof event does not independently decide product quality, refund entitlement, or financial settlement.
18. **Failed-delivery policy must be deterministic and concurrency-safe.** Define and enforce the attempt threshold, enumerated reasons, customer rescheduling rules, return-to-vendor custody confirmation, and the resulting order state. The current failed-attempt handler reads/updates the order without an encompassing locked transaction; concurrent attempts can race around the threshold or overwrite counters. Lock the order and make attempt creation, threshold evaluation, return-case creation, and custody state transition atomic and idempotent. The threshold must not silently create a wallet credit, refund, payout, or vendor-fund release.
19. **Reconcile actual financial allocation.** For historical marketplace orders, preserve and reverse the immutable 90/5/5 merchandise and 90/10 delivery allocations that were actually captured. For new retailer orders, do not use those percentages; reconcile retail receipts, discounts, supplier cost/payable, approved logistics-company charges, gateway fees, refunds and any separately approved cashback using the actual immutable transaction snapshots.
20. **Preserve historical compatibility.** Map old routes, API consumers, jobs, webhooks, views, tests, payout records, and order histories before removing legacy dependencies. Keep historical logistics views read-only where possible; historical financial records must remain auditable.
21. **Add adversarial regression tests.** At minimum test: vendor A cannot assign vendor B's order/rider; fleet-only and ambiguous riders are denied new work; inactive vendor/rider cannot act; two concurrent assignments cannot both win; reassignment after custody is blocked; rider APIs and HTML contain no prohibited fields or raw phone; Call Customer works without exposing the number; failed delivery and rider handover create no direct financial postings; pickup is zero-fee and rider-free; COD remains disabled; duplicate callbacks do not duplicate operations; historical logistics data remains intact.
22. **Keep all documents and user-facing policies aligned.** A prior README version described third-party fleet delivery, fleet delivery-fee payouts, active logistics-company operations, and immediate wallet credits from rider handover. The README's main overview, allocation, fulfilment, custody, and actor-role sections have now been aligned on this audit branch. Before release, scan the remaining documentation, API contracts, onboarding text, customer/vendor policies, notifications, and tests for any stale statement or UI that reintroduces those retired behaviours. Keep `VICTORIOUS_MARKET.md` and `DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md` aligned with this document.
23. **Verify before release.** Run targeted rider API/privacy, delivery handover, failed-delivery, pickup, vendor assignment, and historical compatibility tests, then the full backend test suite. Report actual results. Do not claim the rules are implemented merely because the documentation or a test fixture describes them.

## 15. Source-of-truth and release control

The authoritative consolidated specification is [VICTORIOUS_MARKET_SYSTEM_RULES_V1.md](../VICTORIOUS_MARKET_SYSTEM_RULES_V1.md), followed by [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](../RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md) and [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). These current retailer rules control where legacy wording in this audit report conflicts. The current required evidence is server-verified customer delivery OTP plus doorstep photo, or the separate pickup reservation pass, customer pickup OTP and in-shop photo. New delivery riders must be linked to an active logistics company; vendor-linked rider rules are legacy only.

This file retains historical vendor-managed requirements for audit and migration traceability. They are not authoritative for new retailer transactions. Business-rule changes require explicit owner approval and consistent updates to API contracts, database rules, implementation, tests, and customer-facing policies.

Do not merge to main without the user's explicit approval.

## Payment/refund policy extension

The detailed payment, refund, settlement, vendor earning/payout state-transition, Paystack webhook, confirmed policy decisions, contradictions, and implementation matrix is maintained in [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). The documented payment/refund decisions include card + standard Paystack bank-transfer checkout (DVA deferred), a 24-hour return/refund/protection window, original-channel-only refunds at launch, wallet refunds deferred, narrow automatic refund exceptions for verified duplicate charges and verified pre-vendor-action cancellations, and evidence-based chargeback recovery. The former 90/5/5 merchandise allocation is historical only; active retailer cashback and logistics-company 90% default/authorized override rules are stated above. Failed Paystack refund attempts do not satisfy the customer refund obligation. The matrix records implementation gaps; it is not proof that the code already complies. Do not merge to main without explicit owner approval.

### Final launch decision checksum (20 October 2026)

These final choices are binding across checkout, fulfilment, payment, refund, and finance code:

- One parent checkout/Paystack capture may cover multiple vendors, but every vendor child order retains separate fulfilment, fee, allocation, refund ceiling, and settlement gate.
- Launch payment methods are Paystack card and standard bank transfer only. COD is disabled everywhere. DVA, USSD, and mobile money are not launch channels.
- Customer refunds return through the original Paystack channel only. Wallet refunds are deferred. Only a verified duplicate capture or a verified payment for an order cancelled before any vendor action can pass the narrow automatic-approval policy; all other refunds require recorded human approval before dispatch.
- The standard return/refund request window is 24 hours, with admin-authorised late cases and applicable legal/warranty exceptions. Cashback remains unchanged: it matures after qualifying fulfilment, expiry of the 24-hour window and a separate further 24-hour buffer, with no blocking claim; its 365-day validity starts at `matures_at`. Recoverable spent cashback is offset against future cashback at maturity; never silently debit spendable wallet funds.
- For new retailer orders, supplier payable is the immutable agreed wholesale cost per order item, not 90% of merchandise. Cashback is a separate 5% liability on eligible real-money-funded merchandise, excluding delivery. Delivery defaults to 90% logistics-company payable / 10% VM margin, with audited admin override. No direct rider payable exists. The older percentages remain only in historical marketplace records.
- Parent-level captured funds and child-order allocations must reconcile exactly. Refund reservations enforce component-level, per-child and aggregate parent-capture ceilings under concurrency. For historical marketplace orders only, reverse their captured 90/5/5 merchandise and vendor 90/10 delivery allocations. For new retailer orders, reverse the immutable supplier-cost, active cashback and retailer-margin entries, with the logistics-company payable handled under its separate policy. Merchandise and delivery-fee refund decisions still require independent approvals and idempotent operations; neither implies the other. Customer refund completion and supplier/company recovery are separate states.
- Required launch webhook contract: `charge.success`, `bank.transfer.rejected`, `refund.pending`, `refund.processing`, `refund.needs-attention`, `refund.processed`, `refund.failed`, `charge.dispute.create`, `charge.dispute.remind`, `charge.dispute.resolve`, `transfer.success`, `transfer.failed`, and `transfer.reversed`. Subscribe to DVA or other product-specific events only after that product is explicitly enabled.
- These rules do not certify implementation. Use the payment/refund matrix for event/state details and the implementation audit for code findings and acceptance tests. Keep work on `audit/paystack-refund-webhook-safety`; do not merge `main` without explicit approval.

## 16. Independent merchandise-refund and delivery-fee-refund decisions (binding owner rule)

**Merchandise and delivery are separate financial components. They must never be merged into one refund-approval decision or one undifferentiated refund amount.** A parent checkout may contain multiple vendor child orders, but each child order's merchandise and delivery-fee refund eligibility must be decided and recorded independently.

1. **Separate decisions:** every refund request must identify its component: (a) merchandise/item refund, (b) delivery-fee refund, or (c) a set of separately itemized component requests. A single customer complaint may mention both, but approval for one component never implies approval for the other. This component separation applies to both legacy marketplace orders and new retailer orders.
2. **Merchandise refund approval:** record the exact child order, affected item/order-line IDs and quantities, reason, evidence, approved merchandise amount, applicable discount allocation, vendor/platform allocation reversal, cashback reversal/recovery, prior refunds and approving actor/time. Do not include any delivery fee in this amount.
3. **Delivery-fee refund approval:** record a separate decision for the delivery-fee component, including the child order, delivery-fee amount originally captured, reason, delivery/failed-attempt evidence, amount approved, vendor/platform delivery allocation reversal, and approving actor/time. Do not include merchandise value or merchandise commission/cashback in this amount. For in_shop_pickup, delivery fee is zero, so there is no delivery-fee amount to refund.
4. **Independent outcomes:** merchandise may be approved while delivery is denied, or delivery may be approved while merchandise is denied, or both may be approved as two distinct component decisions. Either component may remain pending review while the other proceeds. Customer messaging must state the outcome of each component separately.
5. **Separate financial operations:** create a distinct idempotent refund operation/reservation for each approved component (and for each independently approved item/component group where required). Use the original Paystack payment channel and the same original captured parent transaction as the gateway source; a separate component decision does not mean a new payment channel or a new charge. Record parent payment reference, child order, component type, component amount, approval record, and component-specific idempotency identity.
6. **Ceilings:** enforce component-specific remaining refundable balance, child-order remaining allocation, and aggregate parent-capture refund ceiling under database locks. Sum confirmed refunds plus active reservations across all components and all child orders so separate approvals cannot collectively refund more than the captured payment. Exact retries are idempotent; reuse of a key for a changed amount, component, child order, item, reason or approval instruction must be rejected.
7. **Accounting:** for new retailer orders, merchandise refunds reverse the approved item-level supplier-cost payable, related active cashback liability/recovery, and actual retailer margin/loss entries. Delivery-fee refunds reverse the customer-facing fee through the authorized refund workflow and are recorded as VM refund expense; the logistics company's earned trip payable is not automatically clawed back, and any company recovery is a separate authorized adjustment. For historical marketplace orders only, reverse their original 90/5/5 merchandise and 90/10 vendor delivery allocations. Platform gateway fees remain borne by the platform and are not silently added to the component refund.
8. **Gateway result and partial success:** approval is not execution. Track each component operation through requested/pending/processing/confirmed/failed/needs-attention and reconcile it to the exact Paystack refund identity and amount. A failed or uncertain component refund remains an open customer obligation and must not be treated as paid. One component's success or failure must not overwrite the other component's state.
9. **Vendor recovery is separate:** customer refund execution and vendor recovery are linked but independent operations. Determine recovery from the approved component and actual vendor allocation; do not hold the customer refund open merely because vendor recovery is unresolved, and do not mark vendor recovery settled merely because Paystack refunded the customer.
10. **Return window:** the normal customer claim window remains 24 hours from verified doorstep delivery or confirmed vendor-counter pickup. The additional 24-hour buffer applies only to cashback maturity, not to whether merchandise and delivery-fee components need separate decisions. Exceptional duplicate charges, pre-vendor-action cancellations, chargebacks and non-waivable legal rights follow their separate evidence/authorization rules.

**Required acceptance tests:** merchandise approved/delivery denied; delivery approved/merchandise denied; both approved as separate operations; one component pending while the other succeeds; pickup with zero delivery fee; discount and partial-item refund; multiple vendor child orders; duplicate/out-of-order webhooks; component-specific idempotency conflicts; concurrent reservations that approach both child and parent ceilings; and correct historical 90/5/5 versus vendor 90/10 reversals alongside current retailer supplier-cost/cashback/company-payable accounting.


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
