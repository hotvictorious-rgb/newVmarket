# VICTORIOUS MARKET — Authoritative System Rules Specification

**Version:** 1.1  
**Policy date:** 21 October 2026  
**Status:** Owner-confirmed retailer and central-fulfilment business baseline  
**Applies to:** Customer orders, supplier offers, central delivery, logistics companies/riders, pickup, refunds, supplier payables, disputes and recoveries  
**Implementation status:** These are requirements, not proof that code or published database terms already comply. The legacy marketplace implementation must be audited and migrated before launch. See [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md).

## 1. Authority and change control

This document is the single source of truth for launch business rules. The README, business overview, payment/refund policy, delivery rules, payout policy, implementation audit and legal-document source must agree with it. If an older document or code path conflicts, record the conflict and align it before implementation. Do not merge broad audit-branch changes into `main` without explicit owner approval.

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
- Legacy wallet-credit checkout tender remains disabled. Preserve historical wallet-credit and cashback records; do not treat them as new retailer tender or new cashback funding without explicit approval.
- A reservation is created in VM and tied to the customer, order, item/quantity, price snapshot and expiry. Preserve the existing 24-hour reservation expiry unless the owner changes it. Expiry releases stock only after payment is confirmed not successful/uncertain/in flight; uncertain payment must be reconciled first.
- Payment success must be established by trusted server-side provider verification or an authenticated, verified webhook. Browser redirects, screenshots, supplier claims and rider status are not proof.
- Validate transaction reference, order association, exact amount in integer kobo, NGN currency and provider status. A customer capture must not be duplicated by repeated requests or webhooks.
- Timeouts and missing/out-of-order webhooks trigger verification/reconciliation of the original transaction, not a blind retry or duplicate charge.
- Refunds use the verified original payment route where supported. Customer wallet-credit refunds and off-platform refunds remain disabled at launch unless a separately approved lawful exception is documented.

## 4. Cancellation, doorstep acceptance, rejection and redelivery

- Before a vendor accepts an order for fulfilment, a customer cancellation can normally be processed under the pre-fulfilment cancellation policy, subject to payment verification and stock/order state.
- Once a vendor accepts an order for fulfilment or begins preparation, ordinary customer cancellation is restricted because vendor preparation and delivery costs may already be committed. It is not an absolute bar where the vendor cannot fulfil, payment is duplicated, an order is cancelled before any vendor action, applicable law requires a remedy, or an authorised admin approves a documented exception.
- Customers should inspect the order at the door where practical. For a multi-item order, accept conforming items and reject only the wrong, visibly damaged, defective or materially non-conforming item where item-level handling is supported.
- When an item is rejected at the door, the vendor's rider records the operational outcome and takes the rejected item back to the owning vendor. The vendor inspects it and may offer a corrected item or replacement for redelivery. Redelivery requires customer agreement; it must not be forced on the customer or used to delay a refund/right that is legally due.
- If the customer declines a replacement, the vendor cannot supply a suitable replacement, or the remedy is otherwise inappropriate, an authorised admin reviews the refund or other remedy under this policy and applicable law.
- Repeated, unsupported refusal of conforming goods may trigger a fair account review; it does not permit automatic forfeiture of statutory rights or money.
- Failed delivery, refusal at the door, return-to-vendor, redelivery, refund and vendor recovery are distinct states and events. None may silently create a wallet credit, mark a refund complete, release money, or charge a vendor without an authorised and auditable financial decision.

## 5. Required fulfilment evidence and pickup reservation

The only mandatory **photo evidence** types at launch are:
1. **Doorstep delivery photo** linked to the relevant vendor child order.
2. **In-shop pickup photo** linked to the relevant vendor child order.

OTP verification remains mandatory alongside the photos:
- **Doorstep delivery:** the customer delivery OTP must be securely verified and a doorstep delivery photo recorded before the handover is marked complete.
- **In-shop pickup:** the customer must present the reservation pickup pass (`RES-XXXXXX`) AND complete one-time customer pickup OTP verification; the vendor must securely verify both and record an in-shop pickup photo before completing the handover. The reservation pass and OTP serve distinct security checks; neither replaces the photo.
- Keep customer counter pickup separate from rider-to-vendor parcel custody. Do not expose OTPs or pickup secrets in ordinary order lists/history, logs, analytics or unauthorised rider views. Verify on the backend, expire credentials where applicable, and prevent reuse.
- GPS, customer signature, separate rider-pickup photo and collection-point/hub inspection photo are not mandatory at launch.

A reservation is created through Victorious MARKET and holds the selected stock for the existing 24-hour window unless an owner-approved rule changes it. The customer pays at the approved vendor shop through Victorious MARKET's authorised payment workflow; the vendor must not release the item on an unpaid or payment-uncertain reservation, accept private off-platform payment, or mark payment successful based on a screenshot alone. On expiry, release the hold only after payment state is safely resolved.

OTP/pass verification confirms the handover process, while a photo provides supporting evidence. Neither proves product quality, customer satisfaction or vendor liability. Admin may request relevant additional claim evidence when reasonable and must record evidence exceptions. Protect photo and credential access and customer privacy.

## 6. Standard return/refund window and admin exceptions

- The standard operational return/refund request window for eligible ordinary claims is **24 hours from confirmed doorstep delivery or confirmed in-shop pickup**.
- The 24-hour window is not a blanket waiver of consumer rights, a universal product warranty cutoff, or a promise that a payment provider will complete a refund within 24 hours. Applicable law and valid warranty obligations prevail where they require a remedy.
- Ordinary post-delivery refunds should be low-probability through clear eligible grounds, pre-handover inspection where practical, photo evidence of fulfilment, vendor correction/replacement options, and authorised review—not by unfairly refusing valid claims.
- Eligible reasons include wrong item, missing item, visible transit damage, defect/non-operation, material listing misdescription, failed fulfilment, verified duplicate charge and other legally required grounds. Product-specific exclusions may apply only where clearly disclosed and lawful.
- Support may open a case and collect details. At launch, an authorised admin reviews and approves ordinary refunds. Admin may manually open, review and approve a legitimate late case after 24 hours, including when a customer contacts admin directly. Record the reason, evidence, decision maker, approved amount and timestamps.
- A complaint alone is not proof of vendor fault. Determine whether the vendor, platform, customer or shared cause bears each cost. A vendor's acceptance of an order for fulfilment restricts ordinary cancellation; it does not eliminate remedies for non-conforming goods or non-waivable legal rights.
- A case filed within the standard window remains open until resolved even if inspection, redelivery, repayment or payment-provider processing takes longer.
- Distinguish request, review, approval, submission, provider processing, confirmed completion, failure and reconciliation. Never mark a refund complete solely because an admin approved it or the gateway request was sent.
- Refunds are calculated per affected child order/item and must never exceed the verified parent capture or the remaining refundable amount after prior refunds. Merchandise and delivery-fee refund decisions are made independently according to the reason, actual allocation, published terms and applicable law.
- At launch, refund through the original verified payment method where supported. A complaint or approval does not automatically establish that the vendor owes money.

## 7. Supplier payment eligibility and execution

- Supplier payment eligibility begins only after verified successful doorstep delivery or confirmed customer pickup and required order/amount reconciliation.
- Order placement, customer payment, supplier acceptance/readiness, VM collection, rider assignment, rider collection, delivery attempt or unverified completion is not enough to make a supplier payable eligible.
- A supplier payable represents the agreed purchase cost from the selected offer/order-item snapshot, not a marketplace vendor earning or commission.
- Eligibility is not an automatic bank transfer. The approved payment process must separately record payable creation, authorization, payment attempt, provider confirmation, reversal and reconciliation.
- Payment instructions and callbacks must be idempotent. A timeout is uncertain; reconcile the existing provider attempt before retrying. Repeated events must not create a second payable or transfer.
- The former vendor payout minimum/maximum, FIFO batches, one-active-vendor-request rule and platform-paid vendor transfer fees do not govern supplier payables. No replacement payout threshold, payment rail, payment SLA or approval threshold is approved by this rule set.
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
- The former 5% marketplace cashback allocation was funded as part of the 90/5/5 merchandise split. That split is not a default retailer rule. The owner/accountant must explicitly decide whether new retailer-model sales continue to accrue cashback and how it is funded before new accrual logic is enabled.
- Until an approved migration decision is made, do not infer new cashback accrual from the old marketplace percentages. Any continuing redemption of existing lots must follow the documented existing customer rules and legal review.
- If a refund reverses cashback already spent, track any recoverable amount separately and do not silently debit a cash wallet or create a negative spendable balance. Any new maturity/recovery policy must be explicitly approved and disclosed.

## 10. Retail accounting and financial integrity

- Use immutable order/payment snapshots and integer-kobo/decimal-safe arithmetic.
- Record customer receipts/revenue, supplier purchase cost/payable, delivery charges, logistics-company charges/payables, gateway fees, discounts, approved refunds, any explicitly approved cashback liability, and operating expenses separately.
- The former 90% vendor / 5% cashback / 5% platform merchandise split and 90% vendor / 10% platform delivery split are not default rules for retailer-model transactions.
- Supplier purchase cost is a cost/payable, not marketplace commission. Logistics-company charges and customer delivery-fee margin must follow an approved agreement/rate card; do not invent a split or percentage.
- Customer refunds reverse the approved captured amount under the independent merchandise/delivery component policy and original payment-route controls. Refund execution and supplier recovery are separate processes.
- Every payment, refund, supplier payment, repayment, offset, cashback adjustment and journal correction must be idempotent, authorized, balanced and reconcilable. Never edit/delete a completed financial entry; post a linked reversal or adjustment.
- Balanced journals do not prove cash is available. Record real liquidity shortfalls, losses, receivables and approved write-offs honestly. Do not claim internal holds/ledgers are regulated escrow without establishing that legal status.

## 11. Roles, notifications and audit

- Support staff may open cases and gather information. Authorised finance/admin may approve refunds, payouts and offsets within assigned permissions. High-value or exceptional refunds should use a configurable second-approval threshold.
- Every manual override requires a reason and audit record. Completed financial actions cannot be silently edited or deleted.
- Notify customers of material refund status changes and vendors of holds, payout decisions, confirmed debts, repayments and offsets.
- Keep customer, rider, payment and photo data restricted to staff who need it. Retention and access rules must be documented and legally reviewed.
- Reconcile gateway transactions, refunds, payouts, bank receipts, internal journals, vendor balances and cashback balances regularly.

## 12. Pre-implementation gate

Before implementation begins:
1. Update all business and customer/vendor legal documents to this specification.
2. Identify and remove conflicting statements, including legacy logistics fleet/hub terms, conflicting OTP/photo requirements, wallet-refund promises, old payout calculation rules, off-platform/in-shop payment ambiguity and incorrect cashback maturity descriptions.
3. Keep legacy records for audit but clearly mark retired legacy fleet documents/flows as inactive for new orders.
4. Audit current code and database-seeded legal documents against every rule and mark each item implemented, partial, missing or conflicting.
5. Prepare a prioritised implementation plan and regression tests. Do not claim code alignment until changes are implemented, tested and reviewed.


## 13. Owner-confirmed refund funding and vendor recovery rule (20 October 2026)

**Binding owner decision:** When Victorious MARKET approves a valid customer refund after the vendor has already been paid, Victorious MARKET funds and executes the customer refund from platform-controlled available cash/refund liquidity. The customer refund must not be delayed solely because the vendor has not repaid the platform. Where evidence, the approved decision, and the vendor agreement establish that the vendor is responsible, Victorious MARKET records a separate recoverable vendor receivable and pursues recovery through verified repayment or an expressly authorised contractual offset. This rule does not permit automatic debt creation from a complaint.

Required accounting treatment:
- Customer refund obligation, refund execution, platform cash outflow, reversal of original allocations, and vendor recovery are separate linked records/events.
- Reverse the original eligible vendor/platform/cashback and delivery allocations exactly once for the approved refund components. A refunded unspent cashback liability is reversed; spent-cashback recovery remains a separate cashback-ledger matter.
- If the vendor's allocation has already been transferred, do not pretend that transfer was reversed. If the approved liability decision makes the paid vendor allocation recoverable, post a separate vendor receivable for the **approved recoverable amount**, normally limited to the vendor-funded allocation attributable to the approved component(s), subject to the vendor agreement, evidence, applicable law, and any approved shared-fault allocation.
- Do not automatically book the entire customer refund as vendor debt. The platform's own share, gateway fees, delivery allocation, cashback, customer/platform contribution, and fault allocation must be treated according to the actual approved component-level calculation. No double recovery is permitted.
- If vendor responsibility or the recoverable amount is disputed, keep it as a case under review rather than confirmed debt until an authorised decision is recorded. Vendor recovery can remain outstanding after the customer refund is complete.
- The platform must maintain adequate working/refund liquidity to pay approved refunds even when vendor proceeds were already paid and recovery is delayed or impossible. Vendor debt is a receivable, not cash and not available refund liquidity. Forecast the cash gap and report overdue/unrecoverable debt separately.
- Repayment counts only after the platform verifies receipt against its bank/provider records. An authorised offset requires contractual basis, explicit approval, a linked ledger entry, and vendor notification. Write-off requires authorised approval and an audit trail.

Illustrative full-refund case: the customer paid ₦105,000 (₦100,000 merchandise + ₦5,000 delivery). The original allocation was ₦94,500 vendor, ₦5,500 platform, and ₦5,000 cashback liability. If the vendor already received ₦94,500 and the customer is later approved a full ₦105,000 refund, the platform pays the customer ₦105,000 from available platform cash/refund liquidity. It reverses the applicable original allocations; the ₦5,000 unspent cashback liability is cancelled if eligible; and the platform reverses its ₦5,500 share. Only if the documented responsibility decision and vendor contract make the paid vendor allocation recoverable does the platform separately record up to ₦94,500 as vendor receivable in this example. The platform temporarily funds the cash gap; the receivable is not treated as cash until recovered. This example assumes full vendor responsibility and a fully recoverable vendor allocation; actual cases must use the approved component-level liability calculation.

Acceptance tests must prove: customer refund completes while vendor recovery is pending; refund liquidity is not overstated by a receivable; a complaint alone creates no confirmed debt; the debt cannot exceed the approved recoverable amount; verified repayments reduce the receivable once; offsets require permission and contractual basis; and a full refund cannot reverse allocations or create debt twice.

## Mandatory accounting-balance and loss-recognition controls (20 October 2026)

This is a binding accounting invariant, not an optional implementation note.

- Every posted journal must balance: total debits equal total credits in the same currency. Required financial postings must be atomic; do not mark a financial operation complete when its required journal fails.
- Record the customer refund as an actual platform cash outflow when paid through the verified refund route. Reverse original allocations using linked, idempotent entries; never delete or rewrite original entries or pretend a completed vendor bank transfer was reversed.
- Record a vendor receivable only after an authorised, evidence-backed responsibility decision and contractual basis establish the recoverable amount. The receivable is separate from cash, vendor earnings, customer balances, and platform income.
- Available cash/refund liquidity must exclude vendor receivables, disputed claims, pending repayments, uncertain transfers, and other funds not actually received and available.
- Reconcile refund cash movement, original allocation reversals, cashback liability/reversal, vendor payout history, gateway fees, and receivables as distinct components. Retries/webhooks must not duplicate reversals, debt, or recovery.
- If a vendor receivable becomes unrecoverable, record an authorised impairment/write-off using accountant-approved accounting treatment and preserve the audit trail. A write-off is not a cash receipt and must not conceal the loss.
- Reports must separately show (1) actual cash and available liquidity, (2) vendor receivables with ageing/status, and (3) platform income, expenses, recognised losses and write-offs. Balanced books do not guarantee profit or sufficient liquidity.
- Maintain a monitored refund-liquidity reserve/forecast and alert authorised operators when projected obligations exceed available funds. Never count receivables as cash in a reserve calculation.
- Obtain qualified-accountant review of production journal mapping. Preserve immutable audit events for approvals, postings, repayments, offsets, impairments and write-offs.

**Acceptance criteria:** prove balanced debit/credit journals; customer refund independent of vendor recovery; cash reports exclude receivables; completed vendor transfers remain historical facts; recovery/write-off is authorised and idempotent; and finance reports expose cash shortfalls and losses.
