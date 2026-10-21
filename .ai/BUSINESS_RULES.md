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

- Existing database tables and code may retain names such as vendor, shop, vendor child order, vendor earnings, fleet, logistics company or rider. These names are implementation artifacts until inspected; do not globally rename them or assume their semantics fit the retailer model.
- Preserve historical vendor earnings, payout attempts, cashback, recovery, logistics-company and journal records. Map foreign keys, provider references, source order/items, reconciliation evidence and legal/audit dependencies before migration.
- Prevent old vendor commission, payout, debt-recovery and vendor-managed rider paths from creating new retailer-model financial activity. Do not delete or reclassify completed historical entries without an approved accounting migration.
- The canonical current model is [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](../RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md). Detailed payment/refund and webhook rules are in [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md).

## 13. Acceptance tests required before release

1. Supplier submits a new product; VM approval creates one canonical public listing and does not auto-publish before approval.
2. Supplier submits an offer for an existing matching product; the offer links to the existing listing and does not create a duplicate.
3. Order item snapshots the exact selected supplier offer, product/variant, purchase cost, quantity and terms; later offer changes do not alter the order.
4. VM coverage from Uyo allows only supported destinations with current product sourcing, approved logistics availability and a known fee; missing/contradictory coverage fails closed.
5. Assignment rejects inactive/unapproved logistics companies, riders without a company, riders linked to multiple companies, inactive riders and supplier/vendor-linked riders.
6. A rider can access only work assigned to that rider and linked logistics company, with minimum customer data; no supplier wholesale price or customer payment/refund details leak.
7. Doorstep handover cannot complete without server-verified customer OTP and doorstep photo.
8. Supplier-shop pickup cannot complete without the reservation pass, separate one-time customer pickup verification and in-shop photo; supplier readiness is not fulfilment.
9. Supplier payable is not eligible after order placement, customer payment, supplier readiness, VM collection, rider assignment, carrier collection or delivery attempt; it becomes eligible only after verified customer handover and reconciliation.
10. Duplicate callbacks, repeated requests, payment timeouts and uncertain supplier-transfer attempts do not create duplicate charges, payables, transfers, refunds or journal entries.
11. Customer refund remains a VM responsibility after supplier payment; supplier recovery requires evidence-backed responsibility and contractual basis and never delays the refund.
12. Failed delivery, customer refusal, supplier fault, customer refund and supplier debt remain separate states; complaint alone does not create debt.
13. Legacy vendor payout/commission and old 90/5/5 or 90/10 splits cannot be applied to new retailer transactions.
14. Historical vendor, cashback, payout, recovery and journal records remain auditable after migration.
15. Legacy web checkout and every API checkout route are covered by idempotency, payment verification and state-transition tests; specifically close the known checkout bypass of CheckoutExecutionService.
16. Refund operations enforce component, order and aggregate-capture ceilings under concurrency; approvals, gateway execution and confirmed outcomes are distinct.
17. Run targeted tests and full suites; document exact commands, commit, results and CI evidence. Documentation-only changes must not be reported as code fixes.

## 14. Required implementation work and source-audit findings

The previous audit contains findings about the legacy marketplace implementation. Re-evaluate each finding under the retailer model rather than implementing the old vendor rules literally.

### P0 — Payment and financial boundaries

- Close the legacy web checkout bypass of CheckoutExecutionService and require stable idempotency/payment initialization state on every relevant checkout path.
- Preserve server-side Paystack verification, webhook signature validation, exact reference/amount/currency binding, duplicate event protection and reconciliation.
- Ensure no new retailer order creates marketplace vendor commissions, vendor payout earnings or vendor-debt movements by default.
- Introduce or validate supplier payable records and payment attempts with immutable source offer/order-item linkage and idempotent reconciliation.
- Keep approved customer refund execution separate from supplier recovery and logistics-company charges.

### P1 — Supplier, logistics and fulfilment invariants

- Implement supplier offer submission, VM review/approval, canonical product matching and duplicate-listing prevention.
- Enforce immutable supplier offer and purchase-cost snapshots on order items.
- Implement VM-controlled service coverage and customer delivery fees from Uyo; fail closed for unsupported destinations.
- Require every assigned rider to be active and linked to exactly one active logistics company. Reject supplier/vendor-linked riders for new VM delivery.
- Keep supplier-shop pickup and doorstep delivery proof distinct; customer handover requires the approved OTP/photo evidence.
- Distinguish supplier-to-VM collection, logistics custody, delivery attempt, customer handover and return-to-origin.
- Remove/block legacy fleet/vendor dispatch and financial paths for new retailer work after dependency mapping; preserve historical records.

### P1 — Financial policy and migration

- Explicitly approve retailer chart of accounts, supplier payment authorization/terms, logistics-company rate cards and settlement terms, delivery-fee margin, tax/discount treatment and whether new cashback accrual continues.
- Do not infer the old 90/5/5 or 90/10 split as retailer policy.
- Preserve existing customer cashback obligations and historical vendor financial records during migration.
- Enforce supplier-payment eligibility only after verified successful customer delivery/pickup and reconciliation. Eligibility is not automatic transfer.
- Use evidence-backed, contractually supportable supplier recovery; never treat a complaint alone as debt.

### P2 — Policy, audit and release proof

- Align customer-facing Terms, pickup, delivery, refund and warranty wording with the retailer model and applicable Nigerian law.
- Ensure rider responses and logs do not expose supplier wholesale prices, customer financial details or pickup credentials.
- Run supplier/catalog, checkout, payment, pickup, logistics, refund, supplier payment, idempotency, concurrency and migration tests.
- Keep this branch unmerged and undeployed until explicit owner approval and verification.

## 15. Source-of-truth and release control

Current business-model authority: [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](../RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md). Detailed payment/refund authority: [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). Legacy source findings are evidence to investigate, not permission to reintroduce marketplace assumptions. Do not merge or deploy based on documentation alignment alone.
