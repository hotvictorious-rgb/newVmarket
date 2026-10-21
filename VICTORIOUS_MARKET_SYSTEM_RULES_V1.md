# VICTORIOUS MARKET — Authoritative System Rules Specification


> **Current delivery-first fulfilment policy (21 October 2026):** Victorious MARKET is one customer-facing retailer using private suppliers behind the scenes. Doorstep delivery is the only standard online fulfilment method. Do not offer automated in-shop pickup reservations, supplier/shop selection, or supplier-address disclosure in customer checkout. Customers who prefer pickup must contact VM support; support may confirm an exception only after checking the complete order, availability, physical readiness, approved collection point, payment arrangement and customer agreement. Never disclose a collection address or say an order is ready before confirmation. For multi-product orders, VM coordinates the complete order; customers must not be sent to multiple suppliers by default. Legacy pickup/multi-vendor code and historical records remain compatibility/audit material until safely retired; documentation alignment alone does not mean runtime routes or production legal terms have been disabled. See `docs/architecture/RETAILER_FULFILMENT_SIMPLIFICATION_2026-10-21.md` for the binding workflow and implementation acceptance criteria.

**Version:** 1.1  
**Policy date:** 21 October 2026  
**Status:** Owner-confirmed retailer and central-fulfilment business baseline  
**Applies to:** Customer orders, supplier offers, central delivery, logistics companies/riders, pickup, refunds, supplier payables, disputes and recoveries  
**Implementation status:** These are requirements, not proof that code or published database terms already comply. The legacy marketplace implementation must be audited and migrated before launch. See [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md).

## 1. Authority and change control

This document is the single source of truth for launch business rules. The README, business overview, detailed retailer model, operational business rules, payment/refund policy, delivery plan, payout policy, implementation audit and legal-document source must agree with it. Detailed documents may specify implementation acceptance criteria but cannot override this policy. Older code, tests, seeded legal text or documents that conflict are obsolete for new retailer transactions and must be corrected or explicitly isolated as legacy-only before release. Do not merge broad audit-branch changes into `main` without explicit owner approval.

This specification does not override applicable Nigerian law, consumer rights, valid warranty obligations, payment-provider terms or contractual obligations that cannot lawfully be excluded. Customer-facing legal wording requires appropriate Nigerian legal review before publication.

## 2. Retailer and supplier model

- Victorious MARKET is the customer-facing retailer operating from Uyo, Akwa Ibom State. Customers buy from VM, not public supplier storefronts.
- Suppliers are private product sources. They may submit a new product for VM approval or offer a product against an existing canonical listing. VM controls public descriptions, retail price, publication, customer terms and corrections.
- A new product receives one canonical listing. An offer matching an existing product is linked to that listing rather than creating a duplicate. Distinct variants, models, specifications or condition may require distinct listings.
- Each accepted order item must snapshot the selected supplier, supplier offer, product/variant, agreed purchase cost, quantity and relevant supply terms. Supplier offer edits must not rewrite an existing order.
- VM owns order acceptance, customer payments, delivery coordination, service coverage, pickup authorization, customer support, refund decisions and financial records.
- VM serves destinations it can currently cover through product sourcing and approved logistics arrangements. Uyo is the operating base, not a promise of nationwide coverage. Missing/uncertain coverage or fee means delivery is unavailable until resolved.
- VM coordinates delivery through approved logistics companies. Every rider assigned to new VM work must be active and linked to exactly one active logistics company. Supplier/vendor-linked riders cannot receive new VM delivery assignments.
- Authorized supplier-shop pickup remains supported through the existing reservation flow, separate one-time customer pickup verification and required in-shop photo.
- Supplier payment eligibility begins only after verified successful customer delivery or confirmed customer pickup and reconciliation. Eligibility does not trigger an automatic transfer.
- Existing schema names and vendor records may remain during migration, but historical marketplace commissions, vendor payout thresholds and vendor-managed delivery rules do not govern new retailer transactions.

## 3. Customer checkout, supplier linkage and payment

- Customer checkout is with Victorious MARKET as retailer. Do not expose suppliers as public marketplace sellers or calculate new supplier payables from marketplace vendor commission.
- Each order item must link internally to the exact selected supplier offer and immutable agreed purchase-cost snapshot. Where an order includes products from multiple suppliers, preserve item/sourcing-unit linkage and separate supplier amounts without exposing public vendor storefronts.
- Only authorized customer payment paths may be used. Launch rails remain Paystack card and standard bank transfer unless separately approved. Supplier-shop pickup may retain the reservation/payment-at-location flow only when payment is processed and recorded through VM's authorized workflow. No doorstep COD or off-platform supplier collection/private-account transfer.
- Legacy wallet-credit checkout tender remains disabled. Preserve historical wallet-credit records; cashback remains an active, separate loyalty discount under Section 9 and is not wallet-credit tender.
- A reservation is created in VM and tied to the customer, order, item/quantity, price snapshot and expiry. Preserve the existing 24-hour reservation expiry unless the owner changes it. Expiry releases stock only after payment is confirmed not successful/uncertain/in flight; uncertain payment must be reconciled first.
- Payment success must be established by trusted server-side provider verification or an authenticated, verified webhook. Browser redirects, screenshots, supplier claims and rider status are not proof.
- Validate transaction reference, order association, exact amount in integer kobo, NGN currency and provider status. A customer capture must not be duplicated by repeated requests or webhooks.
- Timeouts and missing/out-of-order webhooks trigger verification/reconciliation of the original transaction, not a blind retry or duplicate charge.
- Refunds use the verified original payment route where supported. Customer wallet-credit refunds and off-platform refunds remain disabled at launch unless a separately approved lawful exception is documented.

## 4. Cancellation, doorstep acceptance, rejection and redelivery

- Before VM authorizes sourcing/fulfilment, a customer cancellation can normally be processed under the pre-fulfilment cancellation policy, subject to payment verification and order state.
- Once sourcing or preparation has begun, ordinary cancellation may be restricted under the published policy, but this is not an absolute bar where VM/supplier cannot fulfil, payment is duplicated, no supplier action has begun, applicable law requires a remedy, or an authorized admin approves a documented exception.
- At doorstep, customers should inspect the product where practical. Where item-level handling is supported, a customer may accept conforming items and report/reject only the wrong, visibly damaged, defective or materially non-conforming item.
- The assigned logistics company/rider records the operational outcome and follows the authorized return-to-origin/custody process. VM coordinates any correction or replacement. Redelivery requires customer agreement and must not be forced or used to delay a legally owed refund.
- If replacement is unsuitable, declined, unavailable or inappropriate, VM reviews the refund or other remedy under the approved policy and applicable law.
- Failed delivery, refusal, return-to-origin, redelivery, refund, supplier fault and supplier recovery are distinct states. None may silently mark a refund complete, release money or create supplier debt without an authorized auditable decision.

## 5. Required fulfilment evidence and pickup reservation

The only mandatory photo evidence types at launch are:
1. Doorstep delivery photo linked to the VM order/fulfilment unit.
2. In-shop pickup photo linked to the VM order/fulfilment unit.

OTP verification remains mandatory alongside the photos:
- **Doorstep delivery:** server-verify the customer delivery OTP and record the doorstep photo before marking handover complete.
- **Supplier-shop pickup:** the customer presents the reservation pass (RES-XXXXXX) and completes the separate one-time customer pickup OTP; the authorized pickup actor verifies both and records an in-shop photo.
- Keep customer pickup credentials separate from rider-to-logistics-company custody credentials. Do not expose OTPs/pickup secrets in ordinary history, logs, analytics or unauthorized rider views. Verify server-side, expire credentials where applicable and prevent reuse.
- GPS, customer signature, separate rider-pickup photo and hub-inspection photo are not mandatory at launch.

A reservation is created through VM and holds the selected item for the existing 24-hour window unless the owner changes it. Any payment at a supplier-shop pickup must use VM's authorized payment workflow. Suppliers cannot accept private off-platform payment or mark an order paid from a screenshot. Release stock on expiry only after payment is safely reconciled.

OTP/pass and photo evidence support the handover process; they do not prove product quality or automatically establish supplier liability. Protect photo and credential access.

## 6. Standard return/refund window and admin exceptions

- The standard operational return/refund request window for eligible ordinary claims is 24 hours from verified doorstep delivery or confirmed customer pickup.
- This is not a blanket waiver of consumer rights, a universal warranty cutoff, or a promise that a provider completes a refund within 24 hours. Applicable law and valid warranty obligations prevail where they require a remedy.
- Eligible grounds include wrong/missing item, visible transit damage, defect/non-operation, material listing misdescription, failed fulfilment, duplicate charge and other legally required grounds. Product-specific exclusions apply only where clearly disclosed and lawful.
- Support may open a case and gather evidence. An authorized admin reviews ordinary refunds and may manually review a legitimate late case; record the reason, evidence, decision maker, amount and timestamps.
- A complaint alone is not proof of supplier fault. Determine responsibility from evidence and the supplier/logistics agreement. A supplier's acceptance of an offer does not eliminate remedies for non-conforming goods or non-waivable legal rights.
- A case opened within the standard window remains open until resolved even if inspection, replacement, recovery or payment-provider processing takes longer.
- Distinguish request, review, approval, gateway submission, provider processing, confirmed completion, failure and reconciliation. Approval or submission alone is not proof of refund completion.
- Refund the approved captured amount against the original verified payment route where supported. Merchandise and delivery-fee refund decisions are independent; enforce component and aggregate capture ceilings, count active reservations and confirmed refunds, and prevent duplicate operations.
- Customer refund obligation and supplier recovery are separate. VM must not delay a valid customer refund while trying to recover from a supplier.

## 7. Supplier payment eligibility and execution

- Supplier payment eligibility begins only after verified successful doorstep delivery or confirmed customer pickup and required order/amount reconciliation.
- Order placement, customer payment, supplier acceptance/readiness, VM collection, rider assignment, rider collection, delivery attempt or unverified completion is not enough to make a supplier payable eligible.
- A supplier payable represents the agreed purchase cost from the selected offer/order-item snapshot, not a marketplace vendor earning or commission.
- Eligibility is not an automatic bank transfer. The approved payment process must separately record payable creation, authorization, payment attempt, provider confirmation, reversal and reconciliation.
- Payment instructions and callbacks must be idempotent. A timeout is uncertain; reconcile the existing provider attempt before retrying. Repeated events must not create a second payable or transfer.
- The former vendor payout minimum/maximum, FIFO batches, one-active-vendor-request rule and platform-paid vendor transfer fees do not govern supplier payables. No replacement supplier-payment threshold, payment rail, payment SLA or approval threshold is approved by this rule set.
- Supplier agreements must define commercial terms. VM must not assume every supplier accepts payment after fulfilment without agreement.
- VM remains responsible for an approved customer refund even if the supplier has already been paid. Supplier recovery is separate, evidence-backed and contract-dependent; it must not delay a valid customer refund.

## 8. Disputes, supplier recovery and write-offs

- Keep separate linked records for customer refund, supplier payable/payment, dispute, platform receivable, supplier repayment and any authorized offset. Do not create a spendable supplier wallet.
- Only a documented authorized decision plus evidence and contractual basis can establish supplier liability. A complaint or pending investigation is not confirmed debt.
- Repayment must be verified against bank/provider records and linked to a unique recovery reference, supplier, case, order and amount. A screenshot or promise alone is not payment.
- Offset against future supplier payables only if the supplier agreement permits it and an authorized admin approves and records the amount, reason, affected payable(s), notification and audit trail. Never offset automatically.
- If the supplier has already been paid, record a separate recovery receivable; do not pretend the completed bank transfer has been reversed.
- If responsibility is ambiguous, do not automatically debit the supplier. Track disputed, partially recovered, fully recovered and written-off states. Correct errors with linked reversal/adjustment entries; do not delete ledger history.
- An authorized write-off/impairment must have a reason and audit trail and follow accountant-approved treatment. Customer refund obligations are independent and must not wait for supplier recovery.

## 9. Cashback and legacy customer obligations

- Cashback is not a refund method, supplier payable or withdrawable cash balance.
- Preserve historical cashback lots, customer balances, redemption/reversal history and obligations during the retailer-model migration. Do not delete or silently reclassify existing balances.
- **Cashback remains active at 5% for eligible completed retailer orders.** It is funded from VM's gross retail margin, never deducted from the supplier's agreed wholesale cost. Calculate it only on eligible post-discount merchandise value actually funded with real customer money through VM's verified Paystack payment flow, after subtracting cashback redeemed; exclude delivery fees and any amount covered by discounts or redeemed cashback. Use the immutable checkout/payment snapshot and decimal-safe rounding. No cashback is earned on an unfulfilled/cancelled order or on a value not paid with real money.
- Existing maturity terms remain: after verified customer handover, the 24-hour operational claim window runs, followed by a separate 24-hour maturity buffer; cashback becomes available only when no blocking case remains. The 365-day validity period starts at actual maturity. Redemption is capped at 50% of the cart's merchandise subtotal. Existing lots, balances, redemption/reversal history and customer obligations must be preserved.
- Refunds reverse the appropriate unspent cashback through a linked ledger entry. Cashback already spent is tracked separately as a potential recovery and may be offset only against future cashback as it matures, under the existing approved terms; never silently debit cash or create a negative spendable balance. Cashback adjustments are not additional gateway refunds.

## 10. Retail accounting and financial integrity

- Use immutable order/payment snapshots and integer-kobo/decimal-safe arithmetic.
- Record customer receipts/revenue, supplier purchase cost/payable, delivery charges, logistics-company charges/payables, gateway fees, discounts, approved refunds, the active 5% cashback liability, and operating expenses separately.
- The former 90% vendor / 5% cashback / 5% platform merchandise split is retired. Cashback is now a separate retailer loyalty liability calculated on eligible real-money-funded merchandise as defined in Section 9; it is not a commission split.
- **Logistics company default:** for a new retailer doorstep order, the default logistics-company payable is 90% of the customer's snapshotted delivery fee. The 10% remainder is VM's delivery margin only when the default applies. The payable belongs to the logistics company, not directly to the rider. An authorized admin may approve a higher or lower company amount to reflect actual Nigerian route, distance, package size, fuel, traffic, weather, urgency, return risk or negotiated company terms. Every override must be recorded with previous/default amount, approved amount, reason, admin identity and timestamp. If the amount exceeds the delivery fee, record the excess as an explicit VM delivery subsidy/expense rather than hiding a negative margin.
- Snapshot the default and approved final logistics-company payable on the order. Overrides are allowed before rider custody/handover; after handover, use a separately authorized recovery/adjustment workflow linked to payout state. Do not mutate a completed financial entry; post linked, balanced adjustments. A customer delivery-fee refund is independent of the logistics company's earned trip payable: VM must protect the customer immediately, record the refund expense, and pursue any contractually authorized company recovery separately. Rider operations do not create a separate rider payable under this rule.
- Customer refunds reverse the approved captured amount under the independent merchandise/delivery component policy and original payment-route controls. Refund execution and supplier recovery are separate processes.
- Every payment, refund, supplier payment, repayment, offset, cashback adjustment and journal correction must be idempotent, authorized, balanced and reconcilable. Never edit/delete a completed financial entry; post a linked reversal or adjustment.
- Balanced journals do not prove cash is available. Record real liquidity shortfalls, losses, receivables and approved write-offs honestly. Do not claim internal holds/ledgers are regulated escrow without establishing that legal status.

## 11. Roles, notifications and audit

- Support may open cases and gather evidence. Authorized finance/admin may approve customer refunds, supplier payments and recoveries within assigned permissions. No numeric second-approval threshold is approved; do not hard-code one.
- Every manual override requires a reason and audit record. Completed financial actions cannot be silently edited or deleted.
- Notify customers of material order/refund status changes; notify suppliers of accepted sourcing commitments, fulfilment issues and payable/payment outcomes as appropriate.
- Restrict customer, rider, supplier cost, payment and photo data to staff/actors who need it. Retention and access rules must be documented and legally reviewed.
- Reconcile provider transactions, refunds, supplier payments, logistics-company charges/payments, bank receipts, internal journals and historical cashback/marketplace balances regularly.

## 12. Pre-implementation gate

Do not begin a broad code migration until the following are documented and the affected source paths are audited:

1. Supplier-offer schema/linkage, canonical product matching, listing approval and immutable order-item purchase-cost snapshots.
2. Retailer accounting: supplier purchase cost/payable, customer receipts/revenue, delivery charges, logistics-company charges, fees, discounts, refunds and cashback calculation, funding, maturity, redemption, expiry and refund/recovery treatment.
3. VM-controlled coverage from Uyo, delivery-fee rules, logistics-company commercial agreements and the mandatory rider-to-company linkage.
4. Existing checkout, reservation and pickup compatibility, including the legacy web checkout bypass of CheckoutExecutionService.
5. Supplier payment eligibility only after verified successful customer delivery/pickup, idempotent payment attempts and provider reconciliation.
6. Historical marketplace/vendor payout and cashback records, foreign keys, legal text, journals and audit dependencies that must be preserved.
7. Regression/security tests for payment, refund ceilings, customer OTP/photo, supplier-shop pickup, logistics custody, duplicate events, uncertain timeouts and supplier payment.
8. Legal and accounting review of customer-facing terms, supplier agreements, logistics-company agreements, taxes, cashback and recovery terms.

A documentation change is not proof of implementation. Keep the migration branch unmerged and undeployed until the owner approves the change set and the required tests pass.

## 13. Owner-confirmed refund funding and supplier recovery rule (21 October 2026)

Victorious MARKET remains responsible for funding and executing an approved customer refund even if the supplier has already been paid. Customer remedy must not wait for supplier recovery. Record any potential supplier receivable only after evidence establishes supplier responsibility and the supplier agreement permits recovery. A complaint alone is not debt. Recovery/offset requires authorized approval and a linked audit trail; never pretend a completed transfer was reversed.

## 14. Mandatory accounting-balance and loss-recognition controls

- Supplier payables, logistics-company charges, customer refunds, gateway fees, cashback liabilities, receivables, repayments, write-offs and operating expenses must be separately traceable.
- Every financial event must have idempotent source identity, correct currency/amount, authorization, balanced journal entries and reconciliation evidence.
- A balanced ledger can still show an operating loss or liquidity shortfall. Cash and receivables are not interchangeable. A supplier receivable is not cash and must not be used to claim a customer refund is funded.
- Reverse or adjust prior postings through linked entries; do not delete history or double recover the same loss.
- Historical marketplace allocations, cashback, vendor earnings and payout records must remain traceable and must not be silently reclassified as retailer transactions.

## 15. Source-of-truth and release control

The canonical current model is [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md). Detailed payment/refund and webhook requirements remain in [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). Historical source-audit findings must be reconciled against these current business rules; do not reintroduce vendor-managed delivery, marketplace payout percentages or supplier-off-platform payment.
