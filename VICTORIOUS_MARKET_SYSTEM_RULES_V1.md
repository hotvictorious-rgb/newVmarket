# VICTORIOUS MARKET — Authoritative System Rules Specification

**Version:** 1.0  
**Policy date:** 20 October 2026  
**Status:** Owner-confirmed business baseline for documentation alignment before implementation  
**Applies to:** Customer orders, approved vendors, vendor-managed delivery, refunds, cashback, vendor earnings, payouts, disputes and recoveries  
**Implementation status:** These are requirements, not proof that code or published database terms already comply. Do not begin implementation until the document set is reconciled and the current code has been audited against it.

## 1. Authority and change control

This document is the single source of truth for launch business rules. The README, business overview, payment/refund policy, delivery rules, payout policy, implementation audit and legal-document source must agree with it. If an older document or code path conflicts, record the conflict and align it before implementation. Do not merge broad audit-branch changes into `main` without explicit owner approval.

This specification does not override applicable Nigerian law, consumer rights, valid warranty obligations, payment-provider terms or contractual obligations that cannot lawfully be excluded. Customer-facing legal wording requires appropriate Nigerian legal review before publication.

## 2. Marketplace and vendor model

- Victorious MARKET is a curated marketplace with vetted and approved partner vendors, not an open marketplace where anyone can register and sell immediately.
- Victorious MARKET owns the customer-facing support, order coordination, refund decision/coordination, payment verification, settlement controls and audit records.
- Vendors own/fulfil their products, keep listings and stock/prices accurate, prepare orders, manage their own approved riders and receive returned/rejected goods directly.
- Vendors manage and pay their riders outside Victorious MARKET's financial systems. New orders do not create a platform fleet, logistics-company payable, rider wallet, rider earnings or rider payout.
- Contracts, receipts, payment flow and operational conduct must consistently reflect whether the platform acts as marketplace intermediary/agent; do not describe the arrangement as buy-and-resell unless that is the actual arrangement.
- Vendor onboarding, suspension and removal must preserve order history, outstanding customer obligations, earnings and confirmed recoveries.

## 3. Parent checkout, child orders and payment

- One parent checkout may contain products from multiple vendors. It creates separate child orders per vendor, each with its own fulfilment, delivery fee, delivery status, item-level earnings and claim/settlement evaluation.
- Only two customer payment paths are permitted at launch: **(1) pay before delivery through Victorious MARKET checkout**, or **(2) reserve an in-shop pickup order through Victorious MARKET and pay at the approved vendor's shop when collecting it**.
- The reserve-and-pay-at-shop option is for customer counter pickup only; it is not cash-on-delivery and cannot be used for doorstep delivery.
- All payment collection and payment records must go through Victorious MARKET's authorised payment workflow. Vendors must not accept or solicit private cash payments, personal-account bank transfers, or other off-platform settlement. For an in-shop reservation, the vendor must complete/record the payment through the platform's approved payment flow before marking the order paid or releasing the item. Launch payment rails are Paystack card and standard bank transfer only, unless the owner separately approves another platform-integrated rail.
- A reservation is created in Victorious MARKET and tied to the customer, vendor child order, item/quantity, price snapshot and expiry. Preserve the existing 24-hour reservation expiry unless separately changed by the owner. Expiry releases the stock hold only after confirming no payment is successful or uncertain/in flight; uncertain payment must be reconciled first.
- Payment success must be established by trusted server-side provider verification or an authenticated, verified webhook. Browser redirects, screenshots and client-supplied status are not proof.
- Validate transaction reference, order association, amount, currency and provider status. A parent checkout charge is collected once and allocated to child orders once; child allocations must sum to the verified parent capture. Shop payment must also have a unique platform order/payment reference and be reconciled to the correct reservation/child order.
- Timeouts and missing/out-of-order webhooks trigger verification/reconciliation of the original transaction, not a blind retry or duplicate charge.
- Refunds use the verified original payment route where supported. Customer wallet-credit refunds and off-platform refunds are disabled at launch unless a separately approved, lawful exception is documented.

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

## 7. Vendor earnings eligibility and payout

- Vendor earnings become eligible for payout **24 hours after confirmed doorstep delivery or confirmed in-shop pickup**, provided the affected earning has no applicable return/dispute/payment hold and all vendor, security and reconciliation checks pass.
- Becoming eligible is not an automatic bank transfer. A vendor requests payout through the platform's payout process; an authorised admin manually approves every request at launch.
- Minimum payout request: **₦5,000**. Maximum payout request: **₦500,000**.
- The vendor does not enter the amount. The server calculates a batch from the oldest eligible, unreserved earning records using strict FIFO.
- **Whole earning records only:** add each oldest eligible record in full while the total remains at or below ₦500,000. If adding the next full record would exceed ₦500,000, stop. Do not split that record and do not skip it to include newer records. The record and all newer records remain for a later batch.
- If the calculated batch is below ₦5,000, no payout request is created yet. If the oldest eligible record itself exceeds ₦500,000, do not split or skip it; flag for authorised finance review and an explicit accounting-safe resolution.
- One active payout request per vendor may exist while pending review, approved but not sent, processing/in-flight, or uncertain/awaiting reconciliation. A new request is permitted only after the prior request is definitively resolved.
- Reserve the exact whole earning records atomically. Rejection releases those reservations once. Approval is not payment; mark paid only after authoritative provider success and reconciliation.
- Victorious MARKET bears outbound vendor payout transfer charges (including applicable transfer fees/stamp duty charged for that payout) as a platform expense. The vendor receives the exact approved payout amount; never deduct these charges from vendor earnings.
- Do not release a payout reservation because of a timeout alone. Reconcile the same transfer reference before retrying. Duplicate requests, callbacks, webhooks or retries must not duplicate a transfer or ledger entry.

## 8. Disputes, vendor debt and recovery

- Maintain separate, linked records for customer refund, vendor earnings, dispute, platform receivable/vendor debt, repayment and payout. This is a disputes/recoveries ledger, not a spendable vendor wallet.
- Only a documented, authorised decision can establish vendor liability and a confirmed debt. A complaint or pending investigation is not a confirmed debt.
- A vendor may repay a confirmed debt by direct bank transfer to the designated Victorious MARKET account. The platform verifies the received funds against bank/provider records and links the repayment to a unique recovery reference, vendor, case, order and amount. A screenshot or promise alone is not payment.
- Alternatively, an authorised admin may approve an offset from the vendor's held or future earnings, where the vendor agreement permits it. Every offset records the approving admin, reason, amount, affected earning records and vendor notification. Do not automatically offset without that admin approval.
- Vendors may challenge a proposed recovery. Hold the genuinely disputed amount for review; do not treat an allegation as confirmed debt.
- If the vendor was already paid, record a separate recovery receivable. The platform cannot pretend a completed bank transfer has been reversed. The vendor can repay directly or the platform can recover from future earnings with authorised approval and contractual basis.
- Track outstanding, partially repaid, fully recovered, disputed and written-off states. Correct errors through reversing/adjustment entries, never by deleting original ledger history. Only an authorised senior admin may write off a debt with a reason and audit trail.

## 9. Cashback — preserve the existing design

- Cashback remains the existing customer loyalty feature, not a refund method or vendor wallet.
- Eligible post-discount merchandise is allocated **90% vendor / 5% customer cashback / 5% platform**. Cashback is not calculated on delivery fees. The vendor delivery-fee allocation remains 90% vendor / 10% platform.
- Cashback stays pending until verified fulfilment establishes the end of the standard 24-hour claim window. It then has a **separate additional 24-hour maturity buffer**; earliest maturity is `refund_window_expires_at + 24 hours`, only if no blocking claim, dispute, failed-delivery case or unconfirmed pickup remains.
- Cashback's **365-day validity period starts at its actual `matures_at` timestamp**, not checkout, payment or delivery. This is a validity period after maturity, not a 365-day maturity hold.
- Cashback is non-transferable and not withdrawable to an external bank account. It may be redeemed on eligible future purchases under the existing redemption limit (currently no more than 50% of merchandise subtotal per checkout).
- If the associated order is cancelled or refunded, reverse unspent cashback. If cashback has already been spent, record the recoverable amount separately and offset it against future cashback as that future cashback matures; do not silently debit the customer's cash wallet or create a negative spendable balance. Preserve the current cashback implementation and disclose these rules in customer terms.

## 10. Allocation and financial accounting

- Use immutable checkout allocation snapshots and integer-kobo/decimal-safe arithmetic.
- Merchandise: 90% vendor, 5% customer cashback liability, 5% platform share on eligible post-discount merchandise.
- Vendor-configured delivery fee: 90% vendor, 10% platform share. No rider payable is created.
- Platform bears Paystack processing fees and vendor payout transfer charges from its platform share/platform expense; do not silently deduct vendor payout fees or Paystack processing fees from vendor or cashback allocations.
- Refund only the approved customer amount. Reverse the corresponding vendor, platform, delivery-fee and cashback allocations once. Refund execution and vendor recovery are separate processes.
- Every payment, refund, payout, repayment, offset, cashback accrual/reversal and adjustment must be idempotent, authorised, balanced and reconcilable. Never edit/delete a completed financial entry; post a linked reversal or adjustment.

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
