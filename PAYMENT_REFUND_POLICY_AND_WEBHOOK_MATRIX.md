# VICTORIOUS MARKET — Payment, Refund, Settlement & Paystack Webhook Policy


> **Current delivery-first fulfilment policy (21 October 2026):** Victorious MARKET is one customer-facing retailer using private suppliers behind the scenes. Doorstep delivery is the only standard online fulfilment method. Do not offer automated in-shop pickup reservations, supplier/shop selection, or supplier-address disclosure in customer checkout. Customers who prefer pickup must contact VM support; support may confirm an exception only after checking the complete order, availability, physical readiness, approved collection point, payment arrangement and customer agreement. Never disclose a collection address or say an order is ready before confirmation. For multi-product orders, VM coordinates the complete order; customers must not be sent to multiple suppliers by default. Legacy pickup/multi-vendor code and historical records remain compatibility/audit material until safely retired; documentation alignment alone does not mean runtime routes or production legal terms have been disabled. See `docs/architecture/RETAILER_FULFILMENT_SIMPLIFICATION_2026-10-21.md` for the binding workflow and implementation acceptance criteria.

> **Superseding owner decision (22 October 2026):** Victorious MARKET is the sole customer-facing retailer operating only within Uyo LGA, Akwa Ibom State for new sourcing, destinations and dispatch. Suppliers are private sources; approved logistics companies control their own active riders, with one rider assigned to the complete customer order. One bulky item makes the whole order keke-only and exclusive to that customer; only all-small-item orders may be batched on a bike along the same corridor. Each supplier stop requires a shop-specific one-time pickup OTP and photo; final delivery requires customer OTP and delivery photo. Support-managed pickup is an exception, not an automated reservation/checkout flow. Supplier payables require verified completion and reconciliation. The old 90/5/5 merchandise allocation and 90% logistics-fee allocation are retired for new orders; company payables use the assigned company's Uyo corridor base tariff plus its configured per-bulky-unit surcharge. Cashback remains a separate 5% benefit under its existing eligible-post-discount merchandise terms. These are requirements, not proof that implementation is complete.

**Status:** Owner-confirmed policy baseline for payment/refund decisions recorded on 20 October 2026. The business questions in this decision set are resolved; technical implementation and verification gaps remain open. Implementation gaps are not completed fixes.
**Prepared:** 20 October 2026
**Current consolidation branch:** `align/vmarket-single-source-2026-10-22` (the former audit branch remains a source of changes to compare, not the active policy branch).
**Canonical delivery rules:** .ai/BUSINESS_RULES.md
**No-merge rule:** Do not merge to main without explicit owner approval.

## 1. Scope and authority

This policy governs payment-provider verification, customer refunds, webhook handling, reconciliation and financial safety. For the current business model, read it with [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md) and [.ai/BUSINESS_RULES.md](.ai/BUSINESS_RULES.md). Legacy vendor child-order, commission and payout language below is retained only where needed to describe existing code/history; it is not authority to apply marketplace allocations to new retailer-model transactions.

The owner-confirmed decisions below are binding for the audit plan. Paystack card and standard bank transfer are the approved launch rails. No numeric second-approval threshold has been approved; keep any high-value second-approval threshold configurable and do not hard-code an unapproved amount. These are business decisions; they do not mean implementation is complete. Do not merge to main without explicit owner approval.


### Final owner-confirmed launch contract

The owner has confirmed the following final choices. These are binding and supersede any older or conflicting proposal elsewhere in the repository:

- **Retailer Operating Model:** Victorious MARKET is the customer-facing retailer. Customers buy directly from Victorious MARKET. Products are sourced just-in-time from private suppliers in Uyo who submit wholesale cost prices. VM sets public retail selling prices (`Retail Price = Wholesale Cost + VM Margin`) and sources each order item from the cheapest available local supplier offer.
- **Two Permitted Payment Paths:**
  1. **Doorstep Delivery (Pay First Online):** Customer must pay the full retail selling price plus delivery fee upfront online via Paystack (card or bank transfer). Cash on delivery is completely disabled.
  2. **Pickup exception:** Pickup is not an automated reservation or checkout path. Customers must contact support; support confirms only after checking the complete order, physical readiness at one approved point, payment arrangements and customer agreement. Do not disclose addresses or direct customers to travel before confirmation. All payments must be processed/recorded through Victorious MARKET; suppliers may not accept cash or off-platform private transfers. Launch rails are Paystack card and standard bank transfer.
- **Fulfilment:** VM coordinates delivery only within Uyo LGA through approved logistics companies. Every assigned rider must be active and linked to exactly one active approved company. One rider handles the complete customer order across all supplier stops. If any item is bulky, the order requires an exclusive keke trip; only all-small orders can be batched on a bike along the same corridor. Each shop pickup requires its own one-time shop OTP and mandatory photo. Doorstep completion requires the customer's delivery OTP and mandatory geo-tagged delivery photo. Pickup, if exceptionally approved by support, is not a public reservation/checkout flow and requires recorded authorization, customer verification and photo evidence.
- **Retail accounting:** `Retail Price - Supplier Wholesale Cost = VM Gross Margin` before other costs. Supplier payables use immutable agreed wholesale-cost snapshots and become eligible only after verified delivery (or an explicitly authorized pickup exception) plus reconciliation. Cashback remains a separate 5% liability on eligible post-discount merchandise actually funded by verified Paystack money, excluding delivery, under existing maturity, validity, cap and reversal rules. The former 90/5/5 merchandise split is historical-only. For new orders, the logistics-company payable is its admin-configured Uyo corridor base payable plus its per-bulky-unit surcharge; it is not a percentage of the customer's delivery fee. Admin overrides require reason and immutable audit.
- **Refund and tender boundary:** customer refunds use the verified original Paystack transaction/channel only at launch. Do not issue wallet-credit refunds and do not accept legacy wallet credits as checkout tender at launch; preserve historical credit records for separately approved treatment. Cashback redemption is a distinct loyalty discount and remains subject to the 50% merchandise-subtotal cap.
- **Refund approval:** automatic approval is limited to a verified duplicate successful capture and a verified payment for an order cancelled before any supplier action. All other refunds require persisted authorized human approval before gateway dispatch.
- **Protection/cashback:** 24-hour return/refund/protection window plus a separate further 24-hour cashback-maturity buffer after qualifying fulfilment; no maturity while a blocking return/dispute remains. The cashback buffer does not extend the customer return/refund window.
- **Refund/settlement integrity:** partial refunds use immutable captured allocations. VM executes approved customer refunds; supplier recovery is evidence-based, contractually agreed, and tracked separately.
- **Release control:** policy documents are not proof of implementation. This file is being aligned on `align/vmarket-single-source-2026-10-22`, the current consolidation candidate. Keep `main` protected; do not merge until unique branch work is reconciled and required CI, security, payment and fulfilment tests pass.


## 1.1 Binding payment paths, reservation and policy clocks

**Binding payment rule:** Standard online orders are paid upfront through Victorious MARKET's authorized Paystack workflow (card or standard bank transfer); COD and off-platform supplier payment are disabled. New online checkout does not create an in-shop reservation or inspect-before-pay flow. A customer may request pickup through VM support only; support must verify the complete order, physical readiness of every item at one approved collection point, the payment arrangement and customer agreement, then record the exception and required one-time pickup OTP/photo evidence. Preserve old reservation passes and stock holds only for historical compatibility; do not use them to authorize new orders.



### Binding distinction: claim window, refund processing, settlement, and cashback maturity

These are separate clocks and state machines. Never use one as a substitute for another.

1. **Customer claim/return window — 24 hours.** For new retailer orders, start the standard 24-hour window from the verified delivery or approved pickup-completion timestamp on the customer order. Persist the resulting `refund_window_expires_at`; do not calculate it from checkout creation, payment time, or the time a job happens to run. For historical marketplace orders, preserve each child order's original window and snapshot. Open returns, disputes, delivery exceptions or unverified pickup must follow the applicable hold/review rule.
2. **Consumer-rights safeguard.** The 24-hour standard is an operational claim/reporting window, not a blanket waiver of rights that cannot lawfully be excluded. It must not be represented as eliminating remedies available under the Federal Competition and Consumer Protection Act or other applicable Nigerian law, including remedies that may apply to defective, unsafe, misdescribed, or non-conforming goods. Applicable law and any valid warranty/guarantee obligations prevail where they provide a stronger remedy. Have the customer-facing Terms and Conditions reviewed by qualified Nigerian counsel before launch.
3. **Gateway refund lifecycle — independent of the claim window.** A request, approval, Paystack submission, provider processing, and provider-confirmed refund are distinct states. The 24-hour claim window is not a promise that Paystack will complete a refund in 24 hours. A timeout, `refund.failed`, or `refund.needs-attention` does not satisfy the customer refund obligation; keep it open for reconciliation/review. At launch, refund through the verified original Paystack transaction only; wallet-credit refunds are disabled. Automatic approval is limited to a verified duplicate successful capture and a verified payment for an order cancelled before any vendor action. All other refunds require recorded authorized human approval before dispatch.
4. **Supplier payable eligibility.** A new retailer supplier payable is based on the immutable agreed wholesale-cost snapshot for each order item and is eligible only after verified customer delivery (or an explicitly authorized and verified pickup exception) plus reconciliation, with no blocking return/dispute/payment exception and after required beneficiary/security checks. Eligibility is not an automatic transfer. Preserve historical vendor-child settlement rules only for legacy transactions where they actually applied.
5. **Cashback maturity and expiry.** Cashback stays `pending_maturation` until verified fulfilment establishes `refund_window_expires_at`. Its earliest maturity is `refund_window_expires_at + 24 hours` (the separate cashback buffer), and it can become spendable only if no blocking case remains. Thus, with no exceptions, cashback is normally available no earlier than about 48 hours after fulfilment. Its 365-day validity period starts at `matures_at`, not at checkout or payment. A pre-fulfilment cashback record may have null `matures_at` and `expires_at`; null means the clock has not started, never “mature now”.
6. **Spent cashback recovery.** Reverse unspent cashback first. Any recoverable amount already spent must be recorded in a separate customer cashback-recovery ledger and offset against future cashback only as that future cashback matures. Never silently debit the customer's cash wallet or create a negative spendable balance. Disclose this rule in customer Terms and Conditions and complete consumer-policy/legal review before enabling recovery.
7. **Exceptions and historical multi-vendor isolation.** Verified duplicate captures, qualifying pre-supplier-action cancellations, payment exceptions, chargebacks and non-waivable legal rights use their own evidence and authorization paths. New retailer orders are one customer order with internal supplier assignments; do not split customer fulfilment, fees or refund decisions into public vendor child orders. Preserve historical parent/child marketplace allocation, claim clocks and per-child refund limits only for transactions created under that legacy model, while enforcing the aggregate original-capture refund ceiling.

Implementation of this policy must be proved by code review and tests; this text is not evidence that every path already complies.


## 2. Checkout and payment decisions (Q1–Q8)

| Q | Binding rule | Implementation / control |
|---:|---|---|
| 1 | Customer checkout is with Victorious MARKET as retailer. Do not expose suppliers as public marketplace sellers or apply multi-vendor commission allocation to new retailer-model orders. | Preserve customer payment/order identity. Link each order item internally to the exact selected supplier offer and immutable agreed purchase-cost snapshot. Existing parent/child structures may remain as legacy implementation artifacts until migrated safely. |
| 2 | A valid authenticated webhook or server-side transaction verification may establish payment. | Enforce exact reference, amount in integer kobo, NGN currency, correct order/customer binding and idempotency. Browser redirect data alone never establishes payment. |
| 3 | A verified duplicate successful charge is eligible for the narrow automatic-refund exception. | Store every provider transaction/reference separately. A duplicate must never settle the order or create a second supplier payable. Verify the exact extra charge and create an auditable idempotent refund operation. |
| 4 | If payment succeeds after cancellation or product unavailability, do not reactivate or fulfil automatically; create a controlled exception/refund case. | Re-verify payment and order/stock state, block fulfilment and supplier payment, and use the authorized refund workflow. Automatic approval is allowed only when all documented pre-action exception guards pass. |
| 5 | Underpayment: keep the order unpaid/on hold; do not fulfil or pay a supplier. | Compare exact integer kobo to expected amount and preserve mismatch evidence for reconciliation. |
| 6 | Overpayment/wrong amount: do not fulfil automatically or convert excess to wallet credit. | Flag for reconciliation and resolve only after authoritative payment verification. |
| 7 | Customer payment is through VM's authorized payment workflow. Supported launch rails remain Paystack card and standard bank transfer. A support-managed pickup exception is not a public reservation/payment-at-location flow and must be explicitly authorized, agreed by the customer and recorded in VM's payment workflow. | Link payment, order, customer, amount and pickup authorization. Supplier shops cannot independently collect payment or bypass VM. Handle `bank.transfer.rejected` as a reconciliation exception. |
| 8 | An expired stock hold must not release stock while payment outcome is uncertain. | Reconcile payment before releasing stock; a late verified payment requires atomic availability recheck or a controlled refund case. |

### Retail accounting and supplier payable boundary

The former marketplace 90/5/5 merchandise split and 90/10 vendor delivery-fee split are not binding for new retailer-model transactions. Do not use vendor commission or payout ledgers as supplier payable calculations.

For new retailer-model transactions, keep separate records for verified customer receipts, merchandise revenue, supplier purchase cost/payable, customer delivery charges, logistics-company charges/payables, gateway fees, discounts, approved refunds, the active 5% cashback liability and operating expenses. Supplier purchase cost is not a commission. The customer pays one configured base delivery fee per order plus the configured customer surcharge per bulky physical unit. The logistics company is paid its separately configured corridor base payable plus its per-bulky-unit surcharge, independent of customer delivery revenue. Any company payable above customer delivery revenue is recorded as a VM-funded subsidy; the company, not its rider, is the payee. The former 90% allocation is historical-only.

A supplier payable is linked to the accepted order item/sourcing unit. Its purchase-cost snapshot must not change when the supplier's current offer changes. If an order contains items from different suppliers, preserve the supplier cost and fulfilment outcome per item or sourcing unit.

## 3. Refund and cashback decisions (Q9–Q16)

| Q | Proposed rule | Implementation / control |
|---:|---|---|
| 9 | Refund to the original payment channel by default. Wallet-credit refunds are documented for a later release and disabled at launch. | The production launch path must return funds through the original Paystack payment channel. Do not expose an active wallet-refund option or allow API parameters to select wallet credit. Before a later release, implement a distinct wallet-credit operation requiring explicit customer consent, a consent timestamp, admin authorization and an audit log. |
| 10 | Hybrid refund authorization: only two narrowly defined cases may be automatically approved: (a) a verified duplicate successful charge against an already-paid checkout, for the exact extra captured charge; (b) a verified payment for an order cancelled before any vendor action, where no vendor accepted, prepared, dispatched or handed over goods. All other customer refunds require persisted authorized human approval before gateway dispatch. | The automatic exception must be implemented as a separate, auditable policy gate—not a general bypass. Re-verify the original transaction and exact amount/currency/reference; lock and recheck order/vendor state; ensure no fulfilment/vendor action or existing refund covers the same funds; enforce idempotency and cumulative refund ceilings; record the rule that authorized the automatic decision. Webhooks may report payment/refund state but cannot themselves create an approval or dispatch a refund. Wrong/defective goods, failed delivery, partial returns, customer disputes and `refund.needs-attention` require human review. |
| 11 | Partial refunds are allowed for eligible items/quantities in multi-item or multi-vendor orders. | Calculate from immutable captured child-order item prices, quantities, discounts and allocations, delivery-fee policy, cashback, previous refunds, and any vendor proceeds already disbursed. Lock the parent payment, affected child order/return/operation, and enforce both ceilings atomically: refunds allocated to any child cannot exceed that child's captured allocation, and the sum of all child refunds/other refunds against the same Paystack capture cannot exceed the verified parent capture. Match each Paystack refund event to its exact refund operation/reference; never match only by order ID. |
| 12 | Apply the approved reason-specific delivery-fee refund baseline. | Refund the delivery fee when vendor fault (including wrong/defective goods) justifies it or when cancellation occurs before dispatch. Normally do not refund it for customer change of mind or an unjustified refusal. Partial returns require case-specific review of what was wrong and whether the delivery fee should be wholly or partly refunded. Persist the reason, amount and approver; do not infer the decision from a webhook. |
| 13 | Cashback becomes spendable only after successful delivery or confirmed in-shop pickup, expiry of the 24-hour return/refund/protection window, and a separate 24-hour cashback-maturity buffer, with no blocking case. | Default `refund_window_hours` to `24`. Cashback maturity is scheduled for `refund_window_expires_at + 1 day` (the separate 24-hour buffer), subject to no blocking return/dispute and valid fulfilment. Cashback never applies to delivery fees. Do not use a hard-coded arbitrary future date. The extra buffer does not extend the customer's return/refund window. |
| 14 | If cashback has already been spent when the related item is refunded, reverse unspent cashback first and track any remaining recoverable amount for offset against future cashback accruals. | Add a customer-level cashback recovery ledger with amount outstanding, reason, originating order/refund, timestamps and adjustments. Future cashback first reduces this recovery balance; only the remainder becomes spendable. Never silently debit the customer's wallet or create a negative spendable balance. Disclose this rule in customer Terms & Conditions and retain an auditable adjustment trail. Legal/consumer-policy review is still required before release. |
| 15 | `refund.needs-attention`: leave the refund unresolved, alert an authorized admin, and track follow-up. | Keep the customer refund obligation open; do not mark it refunded or release related controls as if complete. At launch, do not route to wallet credit, make an off-platform payment, or submit an alternate bank destination through Paystack's retry endpoint unless a separate owner-approved policy explicitly permits that exception. Record the provider state, exact refund/transaction references, customer communications and next review action. |
| 16 | `refund.failed`: mark the attempt failed, preserve the outstanding customer refund obligation, alert admin and permit only a controlled retry after reconciliation. | Verify the exact transaction/refund and the provider's merchant-account credit before retry. Never retry blindly or mark the customer obligation resolved because one attempt failed. Customer refund status remains separate from vendor recovery. |

## 4. Supplier payment eligibility, disputes and failed fulfilment

| Topic | Binding rule | Implementation / control |
|---|---|---|
| Supplier payment eligibility | Eligibility begins only after verified successful customer delivery or confirmed customer pickup and required reconciliation. | Order placement, customer payment, supplier acceptance/readiness, VM collection, rider assignment, rider collection or delivery attempt is insufficient. A supplier cannot mark its own payable eligible. |
| Payment execution | Eligibility is not an automatic transfer. | Require the approved payment authorization and idempotent supplier-payment workflow. Record payable, approval, payment attempt, provider confirmation, reversal and reconciliation separately. Timeout is uncertain and must be reconciled before retry. No supplier payment may be duplicated by repeated events. |
| Refund after supplier payment | VM remains responsible for an approved customer refund even if the supplier has already been paid. | Refund through the verified original payment route where supported. Supplier recovery is separate and requires evidence-backed responsibility plus contractual recoverability; a complaint alone is not debt. Do not delay a valid customer refund while seeking supplier recovery. |
| Chargeback/dispute | Flag the related order/payment, preserve evidence and open an authorized case. | Supplier recovery or accounting loss follows evidence and contract; do not automatically turn a dispute into supplier debt. |
| Failed delivery or pickup | Failed fulfilment is operational first, not automatic refund approval or supplier fault. | Record attempt reason, parcel custody/return, evidence and customer communication. VM determines remedy under the customer policy. No supplier payment eligibility until successful verified handover. |
| Unmatched transfer/provider event | Reconcile before creating or finalizing a payable/payment state. | Match exact reference, recipient, amount, currency and operation; never repair ledgers from unmatched webhooks alone. |
| Cancellation | A cancelled order cannot create a new supplier payment. | Reconcile payment, sourcing, refund obligation and stock reservation. Do not close a refund obligation because an attempt failed. |

Supplier payment timing here means the earliest eligibility trigger, not an unapproved payout SLA, payout rail or automatic-transfer instruction. Additional supplier-payment terms must be agreed and approved separately.

## 5. Payment and refund state matrix

These are business states, not necessarily literal database enum values. Map existing application statuses deliberately; do not add aliases that disagree about whether money moved.

### Payment / order collection

| State/evidence | Allowed result | Prohibited result / next action |
|---|---|---|
| Checkout created, no authoritative payment | Awaiting payment; stock hold active until expiry | No vendor settlement, spendable cashback or fulfilled status |
| Browser callback says success only | Show verification in progress | Do not mark paid from browser fields alone |
| Verified charge.success / verified transaction | Settle once if reference, exact kobo amount, NGN currency and order binding match and order is fulfillable | No duplicate settlement or duplicate cashback |
| Verified success but order cancelled/stock unavailable | Record late/exception payment; block fulfilment and settlement; create refund case | Do not reopen or oversell; do not bypass refund approval |
| Underpayment / overpayment / reference or currency mismatch | Quarantine for reconciliation; preserve evidence | No fulfilment, vendor allocation, or cashback until resolved |
| Duplicate successful charge | Keep one charge as the order payment; create separate duplicate-payment case | Do not link both charges to the same settlement or silently keep excess funds |
| Payment status unknown/timeout | Pending reconciliation; retain financial/stock controls as policy requires | Do not assume failed, release stock blindly, or dispatch a second charge/refund |
| Confirmed payment failure/abandonment | Release stock hold according to expiry policy | Do not create a refund for a payment that never captured funds |

### Refund lifecycle

| Paystack event/state | Internal business state | Required action |
|---|---|---|
| Admin approval, before gateway call | Approved/ready to execute | Persist approver, amount, currency, reason, payment reference, allocation snapshot and idempotency key |
| refund.pending | Refund pending | Keep reservation/obligation open; store event and provider identifiers |
| refund.processing | Refund processing | Keep obligation open; no final refund journal/closed status yet |
| refund.needs-attention | Needs attention/manual action | Alert authorized admin; preserve the customer obligation and follow-up deadline. Do not use wallet credit, off-platform payment, or an alternate bank destination unless a separate owner-approved exception explicitly permits it. |
| refund.processed with matching operation, amount, currency and original transaction | Confirmed refund | Commit reservation and write idempotent balanced journals; update order/return state only after matching evidence |
| refund.failed with matching operation | Failed attempt / obligation still open | Verify provider account credit and refund identity; alert admin; controlled retry/manual handling only |
| Unmatched, conflicting or malformed event | Unmatched/manual reconciliation | Do not mutate financial operation/reservation/order; preserve event and alert authorized staff |
| Gateway timeout / uncertain result | Uncertain; reservation remains active | Reconcile the same attempt before any retry |
| Refund requested but not approved | Awaiting approval | No Paystack call and no final journal |

**Important:** Paystack says a refund reported as processed may still take up to 10 business days for the customer to receive funds. Customer messaging should say “processed” rather than promise instant bank receipt.

### Supplier payable and payment authorization lifecycle

For new retailer-model transactions, do not create marketplace vendor earning records or use the old vendor payout batch as the supplier-payment mechanism.

- Link each supplier payable to the accepted order item/sourcing unit, canonical product, selected supplier offer, agreed purchase cost and currency.
- Supplier payable eligibility requires verified customer delivery or confirmed pickup, the correct order/offer/quantity, required proof and amount reconciliation.
- Keep payable state, approval, payment attempt, provider confirmation, reversal and reconciliation separate and idempotent. Do not infer payment success from a timeout or supplier screenshot.
- Do not use the former vendor payout minimum/maximum, FIFO earning-record selection, vendor debt balance or 90% earning share as supplier payment rules.
- Existing vendor earning and payout records remain historical and must be preserved/reconciled during migration. Do not relabel old journal entries as supplier costs without an approved accounting migration.

### Dispute lifecycle

| Event | Required response |
|---|---|
| charge.dispute.create | Open dispute case, record deadline/amount/reference, preserve payment and fulfilment evidence, hold affected unsettled vendor proceeds |
| charge.dispute.remind | Update reminder/deadline and notify authorized case owner; keep hold |
| charge.dispute.resolve | Verify final dispute outcome, record evidence and decision, then apply approved financial recovery/release; never infer outcome from an unverified payload |

## 6. Paystack webhook subscriptions

Paystack's official documentation lists supported event names. Subscribe to events required by the Paystack products/channels Victorious MARKET actually enables. For launch, enable card + standard bank transfer only; DVA is deferred. Webhook subscriptions do not replace server-side transaction/refund/transfer verification, database idempotency, or scheduled reconciliation.

### Required for the approved card + bank-transfer launch

| Exact event name | Why |
|---|---|
| charge.success | Required for both card and bank-transfer payments; settlement signal and detector for late, duplicate, mismatched and unexpected successful charges |
| refund.pending | Track a newly initiated refund |
| refund.processing | Track processor acceptance/in-progress |
| refund.needs-attention | Handle missing information/action needed for refund |
| refund.processed | Confirm a matched refund after strict identity/amount/currency checks |
| refund.failed | Preserve refund obligation, reconcile merchant credit, alert for controlled follow-up |
| transfer.success | Confirm a matched vendor payout |
| transfer.failed | Reconcile failed payout and returned funds |
| transfer.reversed | Reconcile reversed payout and post a valid reversal |
| bank.transfer.rejected | Required because Paystack bank-transfer payments are enabled; record wrong-amount/fraud rejection as a payment exception, notify/reconcile, and verify provider refund status and avoid issuing a duplicate refund for an invalid-amount transfer |
| charge.dispute.create | Open/flag chargeback case and hold affected unsettled proceeds |
| charge.dispute.remind | Track outstanding dispute reminders/deadlines |
| charge.dispute.resolve | Record dispute outcome and trigger authorized follow-up |

### Conditional: subscribe only if the corresponding Paystack product is enabled

| Exact event name | When required |
|---|---|
| dedicatedaccount.assign.success / dedicatedaccount.assign.failed | If dedicated virtual accounts are used |
| customeridentification.success / customeridentification.failed | If customer identification/verification features are used |
| paymentrequest.pending / paymentrequest.success | If Paystack Payment Requests are used |
| invoice.create / invoice.update / invoice.payment_failed | If Paystack invoices/subscriptions are used |
| subscription.create / subscription.disable / subscription.not_renew / subscription.expiring_cards | If Paystack subscriptions are used |

Do not subscribe to subscription, invoice, payment-request, dedicated-account or customer-identification events just because they exist; they are not necessary for one-time marketplace checkout unless those Paystack products are used. Dispute event names must include the charge. prefix: charge.dispute.create, charge.dispute.remind and charge.dispute.resolve.

## 7. Webhook security and processing contract

1. Verify Paystack HMAC-SHA512 signature using the raw request body and merchant secret key before trusting payload. Never echo or log secrets.
2. Persist an inbox record before business effects. Store event name, payload hash, provider identifiers, received time, attempt count, processing state and safe error summary.
3. Treat event identity as immutable: same event ID with a different payload hash is a conflict, not a retry. Identical failed/unmatched payloads may be retried under explicit state rules.
4. Return a successful HTTP response promptly once the event is durably accepted; do slow processing asynchronously where practical. Failed processing must be visible to reconciliation/admin.
5. Match exact operation/payment/refund/transfer identity, reference, amount in integer kobo and currency. Never find a refund by order ID alone if multiple partial refunds exist.
6. Apply transitions under database transactions/locks and idempotency keys. Duplicate or out-of-order events must not duplicate journal entries, cashback, reservations, refunds or payouts.
7. A webhook is a notification, not standalone proof that an internal order or operation should be created. Unmatched events remain unmatched for review.
8. Reconcile missing webhooks using authoritative Paystack verification/API and scheduled jobs. Never infer failure merely from a missing webhook.
9. Monitor delivery failures, unmatched events, stale processing claims, old uncertain operations, and Paystack webhook delivery history. Alert authorized operators without exposing customer financial details to riders.

## 8. Confirmed decisions and contradiction resolution

1. **Launch payment rails:** Paystack card and standard bank transfer only unless separately approved. DVA, USSD, mobile money and other channels remain deferred.
2. **Refund failure:** a failed Paystack refund attempt does not satisfy the customer refund obligation. Keep the obligation open, reconcile provider status and use only a controlled authorized retry.
3. **Automatic refund exception:** verified duplicate charge and verified paid order cancelled before any supplier/fulfilment action are the only narrow automatic-approval cases, subject to all documented guards. A webhook event itself never approves or dispatches a refund.
4. **Refund route:** original verified Paystack transaction/channel only at launch where supported. Wallet-credit and off-platform refunds remain disabled unless a separate owner-approved policy permits them.
5. **Customer claim window:** the standard operational claim window remains 24 hours from verified fulfilment, subject to applicable Nigerian consumer rights and valid warranty obligations. This is not a guarantee that a refund completes within 24 hours.
6. **Cashback (current retailer policy):** cashback remains active at 5% on eligible post-discount merchandise actually funded by real money used to complete the order, after redeemed cashback and excluding delivery. It is funded from VM gross margin, not supplier cost. Preserve the 24-hour claim window plus separate 24-hour maturity buffer, no-blocking-case requirement, 365-day validity from actual maturity, 50% cart-merchandise redemption cap, refund reversal and spent-cashback recovery offset only against future matured cashback. The old 5% marketplace allocation is historical only.
7. **Delivery-fee refunds:** decide the refundable delivery component under the approved reason-specific customer policy, evidence, contract and applicable law. VM records and funds an approved customer delivery-fee refund independently; do not automatically claw back the logistics company's earned trip payable. Any company recovery is a separate contract-authorized adjustment.
8. **Retail accounting:** supplier purchase cost is a retailer cost/payable, not vendor commission. The old 90/5/5 merchandise and vendor 90/10 delivery splits are historical-only. New retailer cashback remains a separate active 5% loyalty liability under the approved eligibility and maturity rules.
9. **Logistics-company charges:** VM coordinates delivery through approved logistics companies within Uyo, and every assigned rider must be linked to exactly one active logistics company. For new orders, the company payable is the assigned company's snapshotted Uyo corridor base payable plus its per-bulky-unit surcharge, independent of customer delivery pricing. The company—not the rider—is the payee. Any override requires authorization, reason and immutable audit; an amount above customer delivery revenue must be recorded as a VM-funded subsidy/expense.
10. **Supplier payment:** eligibility begins only after verified successful customer delivery or confirmed customer pickup and reconciliation. Eligibility is not an automatic transfer; payment authorization, provider confirmation and reconciliation remain distinct.
11. **Refund funding and supplier recovery:** VM remains responsible for the customer refund. Any supplier receivable requires evidence-backed supplier responsibility and contractual recoverability. Never delay customer refund for supplier recovery; a complaint alone is not debt.
12. **Customer payment for a support-managed pickup exception:** no public reservation or inspect-before-pay checkout flow is permitted for new orders. Support must record the approved one-point collection exception, confirm the authorized payment arrangement and customer agreement, and require one-time pickup verification plus photo evidence. Preserve legacy reservation/payment-at-location records only for historical obligations; no supplier may collect off-platform cash or private-account transfers.
13. **Bank-transfer rejection message:** “Your bank transfer could not be processed. We are checking the status with our payment provider and will update you within 24 hours. If funds left your account, they will be returned to you once confirmed. Please do not attempt another payment until you receive an update.” Never promise a refund until authoritative provider evidence confirms the applicable outcome.
14. **Implementation status:** these are policy requirements, not a claim that every legacy checkout, supplier-payment, logistics or refund code path has passed verification. The known legacy web checkout bypass of `CheckoutExecutionService` remains open until fixed and tested.

## 9. Required implementation changes / audit checklist

**Legacy boundary:** checklist items below that describe vendor earning records, vendor payout requests, FIFO payout batches, vendor balances, or 90/5/5 and 90/10 splits apply only to preserving and safely reconciling historical marketplace transactions. They are not requirements for new retailer-model supplier payables or logistics-company charges. New transactions follow the supersession rules near the top of this document.

These are requirements to verify against code; listing them does not mean they are already implemented.

### Payment collection
- [ ] Confirm one parent payment and immutable per-child allocation snapshots.
- [ ] Reject COD across checkout, APIs, settings and legacy payment paths at launch; prove in-shop pickup is not treated as COD.
- [ ] Ensure webhook and transaction verification use the same strict settlement service.
- [ ] Record every verified charge/reference, including duplicate/late/mismatched charges.
- [ ] Add a controlled duplicate-payment case/operation and tests for double charge.
- [ ] Cover cancellation and stock loss between checkout and late payment; atomically recheck stock.
- [ ] Distinguish underpayment, overpayment, currency mismatch, reference mismatch and invalid payer binding; keep each from fulfilling.
- [ ] Add/verify reconciliation state for uncertain payment and safe stock-hold release.
- [ ] Restrict enabled payment channels to Paystack card + standard bank transfer; implement and test `bank.transfer.rejected` without duplicate refunding.
- [ ] Keep COD disabled across every launch path; no rider/customer cash collection workflow is enabled.
- [ ] Keep DVA disabled at launch; reject or ignore DVA-only flows safely and do not subscribe to `dedicatedaccount.assign.success` / `dedicatedaccount.assign.failed` until DVA is separately approved.
- [ ] Add an immutable earning ledger: one merchandise earning per vendor order-item line and one separate delivery earning per vendor child order; store source IDs, integer-kobo amount, allocation snapshot, lifecycle, and refund/recovery links.
- [ ] Make vendor withdrawable balance a derived total of eligible earning records, not an order-level aggregate alone; apply payment, fulfilment, 24-hour window, returns/disputes, KYC/vendor status, bank cooldown and recovery checks per earning.
- [ ] **Legacy marketplace only:** preserve and reconcile historical vendor payout batch records with their original rules. Do not implement the ₦5,000/₦500,000 FIFO vendor-payout policy for new supplier payables; no replacement supplier-payment threshold or SLA is approved. For new supplier payments, require verified handover, immutable item-level cost, authorization, reconciliation and idempotency.
- [ ] Split admin payout approval from payment initiation. Pending review cannot call Paystack; only an approved request can enter processing; verified transfer success finalizes the exact earning snapshot.
- [ ] Test rejection/failed/reversed/timeout payout states, exact reservation restoration, webhook races, and no rider/logistics beneficiary for new vendor-managed orders.

### Refunds and cashback
- [ ] Persist authorization provenance before dispatch. Permit automatic approval only for verified duplicate charges and pre-vendor-action cancellation under the narrow policy in Q10; require authorized human approval for all other refunds.
- [ ] Ensure partial-refund calculations use captured immutable item allocations and include prior confirmed refunds plus active reservations.
- [ ] Ensure reservations are concurrency-safe across child orders, partial refunds, provisional holds and repeated idempotency keys.
- [ ] Enforce original-channel refunds only at launch; block wallet-credit refunds and legacy wallet-credit checkout tender at UI/API/service boundaries. Preserve historical wallet-credit records without allowing them as a third launch payment path. Cashback redemption remains a separate capped discount.
- [ ] Add secure `refund.needs-attention` handling, notifications and follow-up tracking. Preserve original-transaction Paystack-only refund policy; do not use alternate bank details/retry flow unless a separate owner-approved exception exists.
- [ ] On refund.failed, preserve the customer obligation and reservation policy; alert and reconcile provider credit before retry.
- [ ] Verify the default customer return/refund/protection window is 24 hours from verified fulfilment and cashback's earliest maturity is `refund_window_expires_at + 24 hours` (`+ 1 day`), only if no blocking case remains. The cashback buffer does not extend the customer claim window and is not a gateway refund SLA.
- [ ] Implement a cashback recovery ledger and offset future cashback before making new cashback spendable; document the policy in Terms & Conditions and complete legal/consumer-policy review.
- [ ] Make refund completion depend on matched refund.processed evidence and idempotent journal posting.
- [ ] Preserve partial-refund sibling states; one confirmed partial refund must not falsely mark the entire child order fully refunded/completed.
- [ ] Keep customer refund status separate from vendor recovery status.

### Supplier payables, logistics charges and disputes
- [ ] Verify payout eligibility per child order after fulfilment, protection window and no blocking return/dispute.
- [ ] Implement charge.dispute.* handlers with exact names, case/deadline tracking, evidence preservation and affected-settlement hold.
- [ ] Never create a payout record from unmatched transfer.success.
- [ ] Make transfer failure/reversal handling race-safe; stale failure must not overwrite success.
- [ ] Verify provider transfer status/returned funds before retry; use a new attempt/reference.
- [ ] Keep rider and legacy logistics payouts excluded from new vendor-managed delivery flows.

### Webhook reliability
- [ ] HMAC-SHA512 signature validation on raw body before business processing.
- [ ] Immutable payload hash per provider event identity; conflict returns a non-success response and creates a security/audit record.
- [ ] Safe duplicate delivery, out-of-order delivery, unmatched retry, stale-claim recovery and dead-letter/manual-review paths.
- [ ] Store enough provider identifiers to distinguish multiple partial refunds against one original payment.
- [ ] Scheduled reconciliation for lost webhook, uncertain timeout and stale pending refund/transfer.
- [ ] Alerts/monitoring for unmatched/conflicting events and prolonged financial states.
- [ ] Test exact Paystack payload shapes for every subscribed event, including amount units and reference fields.

## 10. Source-code observations from this review

These are observed gaps in the current audit branch, not assumptions that documentation equals implementation.

13. **Return/refund-window default:** `ReturnAndRefundService` currently reads `SystemSetting::get('refund_window_hours', 24)`, which matches the owner's approved 24-hour default. Verify database seeds, persisted settings and admin controls do not silently override this policy. Treat any non-24-hour value as an explicit policy/configuration change requiring authorization and audit. The remaining implementation check is to confirm the expiry timestamp is calculated from verified fulfilment and used consistently.
14. **Cashback clock alignment — partial code remediation on this branch.** Pre-fulfilment cashback records now allow null `matures_at`/`expires_at`; checkout and payment settlement leave those clocks unset. The additive migration also recalculates legacy pending/quarantined lots from each order's persisted `refund_window_expires_at`, or clears their dates when fulfilment has not happened. Verified doorstep delivery and confirmed in-shop pickup set `matures_at = refund_window_expires_at + 24 hours` and `expires_at = matures_at + 365 days`. Null-safe model checks prevent a missing date from being treated as matured. Still verify all fulfilment paths, persisted settings/seeds, dispute/return reopening behaviour, and scheduler operation.
15. **Wallet/refund-tender launch boundary — partially remediated on the audit branch:** `ReturnAndRefundService::adminDisburseRefund()` rejects non-empty manual bank/wallet references at its service boundary, and rider doorstep-rejection/failed-delivery paths no longer create new `CustomerWalletCredit` refund records. Original Paystack is the intended customer refund path. Still open: disable legacy wallet-credit checkout tender across UI/API/service paths, prove no alternate direct-credit refund path is reachable, preserve historical wallet-credit records without spending them, and complete end-to-end tests. Any later wallet-refund or wallet-tender release requires a separate owner-approved policy, explicit customer consent where relevant, authorization and audit-log provenance.
16. **Spent-cashback recovery is not yet a documented ledger workflow in code.** Add a customer-level recovery ledger, link entries to originating order/refund, apply recovery against future cashback at its maturity point, prevent negative spendable wallet balances, and add customer Terms & Conditions text before enabling this behavior.

17. **Vendor payout requests currently allow partial withdrawal.** The vendor request endpoint accepts a client-provided amount and only checks that it does not exceed the withdrawable balance; an existing regression test permits a request for ₦12,000 when ₦20,000 is withdrawable. Change the contract so the backend computes the entire current withdrawable balance under locks and reserves it atomically. Reject partial/mismatched/zero requests and overlapping requests. Do not trust the browser amount.

18. **Admin approval and payment initiation are currently combined.** The current admin vendor-payout disbursement action accepts a pending request, reserves order-level allocations, and initiates Paystack in one action. Add distinct approve/reject and payment-initiation transitions. Pending-review must never call Paystack; only an approved payout can be submitted, and only verified transfer success/reconciliation marks the payout paid.

19. **Vendor earning source data is order-level rather than independently item-level.** Existing vendor balance logic sums order-level vendor_net_90 and VendorPayoutAllocation is keyed by order_id. Add immutable earning records per merchandise order-item line and a separate delivery earning per vendor child order. Preserve source snapshots and exact kobo amounts; make payout allocations point to those earning records. Refunds/partial returns must reverse the exact affected item and/or child-order delivery earning, not an arbitrary portion of an aggregate. Do not infer missing historical item splits without reliable source data.
17. **Split basis and rounding need a checkout-path proof.** Verify `BcMathService::computeCommercialSplits()` receives the actual post-discount merchandise amount, excludes delivery fees from cashback, and allocates rounding remainder to platform share. Persist the snapshot and test discount + delivery-fee edge cases.
18. **Bank-transfer rejected events need a distinct handler.** Verify raw signature and event identity, bind the event to the correct Paystack payment/reference, record a reconciliation exception, and inspect provider status before messaging or refund action. For amount-mismatch rejection, Paystack may automatically refund; never double-refund based only on the webhook name.

1. **Refund lifecycle events are not all handled explicitly.** The current PaystackPaymentController handles refund.processed and refund.failed, then treats other event names as ignored. Explicit state tracking/notification is still needed for refund.pending, refund.processing and refund.needs-attention.
2. **Dispute webhooks are not handled explicitly.** The current controller has no charge.dispute.create, charge.dispute.remind or charge.dispute.resolve branch. Add a dispute case, response deadline/evidence tracking and a hold on the affected unsettled vendor child order.
3. **Late-payment stock exception can dispatch a refund from the `charge.success` webhook.** The current handler calls `createRefundOperation()` and `dispatchRefund()` when `settlePaystackOrder()` returns `trigger_gateway_refund`. Change this to a recorded decision gate: only a verified duplicate charge or a verified cancellation before any vendor action may pass the automatic exception. Stock unavailability alone is not enough if a vendor has already acted; all other cases require persisted human approval. A webhook event itself cannot approve a refund.
4. **Refund reconciliation selects return cases in `refund_pending` as well as `refund_approved`.** Ensure `refund_pending` represents an outstanding obligation only and never grants execution authority. Require persisted human approval unless a separate audit record proves the narrow duplicate-charge/pre-vendor-cancellation exception has been met.
5. **Refund state and retry obligations need a distinct customer-obligation status.** The existing reconciler skips orders whose order-level paystack_refund_status is failed, while a failed Paystack refund attempt can leave the customer still owed money. Ensure order summary state does not suppress retries/manual review for an outstanding failed operation.
6. **No duplicate-payment case is evident in the inspected webhook path.** A charge.success reference not matching an order is marked failed/unmatched, but a second verified successful reference for an already-paid parent checkout needs a dedicated duplicate-charge record and controlled refund path. Never attach it to the original payment as if it were the same charge.
7. **Payment mismatch review needs operational visibility.** The settlement service rejects invalid amount/currency/reference/payer evidence, but ensure the original transaction, received kobo amount, expected kobo amount, event and reason are retained in a reconciliation case without marking the order paid or leaking details to riders.
8. **Payment-channel implementation gap.** The policy now enables card + bank transfer. Verify checkout's allowed channel configuration and implement/test `bank.transfer.rejected`. For amount-mismatch cases, confirm the provider's actual refund status before taking action; the rejection event alone must not trigger a second refund.
9. **The draft refund policy requires approval provenance.** Verify that admin actor, approval timestamp, reason, approved amount, delivery-fee decision, destination (gateway vs wallet), and allocation snapshot are stored before dispatch. A status name alone is not sufficient evidence of authorization.
10. **Partial refund delivery-fee policy needs a first-class snapshot.** The inspected ReturnAndRefundService initializes refund_delivery_fee as false. Ensure the authorized reviewer can set and audit the decision, and that vendor/platform delivery-fee allocation is reversed only for the approved refundable amount.
11. **Cashback maturation/recovery — partial remediation, recovery still open.** The code now leaves `matures_at` and `expires_at` null before fulfilment, sets maturity to `refund_window_expires_at + 24 hours` on verified delivery/pickup, and starts the 365-day validity period from maturity. The service fallback `refund_window_hours = 24` matches the approved policy; do not change it to 168. Still audit persisted settings/seeds, all fulfilment paths, and behaviour after a blocking dispute/return is resolved. Implement the customer cashback-recovery ledger and offset recoverable spent cashback against future cashback only at maturity before any remainder becomes spendable.
12. **Dispute outcome must be linked to the actual charge.** A dispute event should be matched using the provider transaction identity/reference and validated against the affected parent/child payment. Do not freeze or release unrelated child-order proceeds just because a parent checkout includes multiple vendors.

## 11. Implementation review questions (technical, not unresolved business policy)

The business choices listed below have been answered. These are code-review checks, not questions that block the policy from being binding:

1. Does checkout truly enable only card and standard bank transfer, excluding DVA, USSD and mobile money?
2. Does the refund service enforce the narrow automatic exception server-side, with all other paths requiring persisted human approval?
3. Does the bank-transfer rejection handler verify event identity and provider refund status before messaging, and avoid duplicate refunds?
4. Are the 24-hour return/refund window, separate 24-hour cashback buffer, matured-cashback recovery offset, original-channel-only launch rule and delivery-fee refund decision enforced in services and jobs?
5. Does the chargeback handler preserve evidence, enforce deadlines, block only affected unsettled vendor proceeds, and recover vendor funds only when responsibility is established?

Until these are verified with tests, do not describe the implementation as complete.

## 12. Additional confirmed commercial rules

### Paystack processing fees and tax treatment

- For historical marketplace transactions only, preserve the captured vendor/cashback/platform and vendor/platform delivery allocations and do not reduce them to pay gateway fees. For new retailer transactions, Paystack processing fees are VM expenses; supplier payable remains agreed wholesale cost, active cashback remains a separate 5% liability on eligible real-money-funded merchandise, and the logistics company receives the approved company payable.
- The platform's net revenue may therefore be lower than its nominal 5% merchandise share plus 10% delivery-fee share. Do not silently pass gateway costs to vendors or customers.
- Do not hard-code VAT, withholding tax, or other tax calculations until a qualified Nigerian tax adviser confirms the applicable treatment, responsible party, base, timing and reporting requirements. Keep tax policy configurable and auditable after review.

### Chargeback / dispute loss allocation

- A dispute must be tied to the exact Paystack charge and affected parent/child-order allocation. Preserve delivery OTP/pass evidence, photos, communication history, item/return evidence, provider deadlines and the final decision.
- If verified vendor fault caused the loss (for example, wrong or defective goods) or an approved allocation justifies vendor responsibility, create a separately authorized, idempotent vendor recovery against the actual vendor allocation.
- If the platform submitted required evidence but still loses, or the cause remains ambiguous, do not automatically debit the vendor. An authorized admin must record the responsibility rationale; the platform bears the loss when vendor responsibility is not established.
- Rider loss/tampering falls under the vendor-managed rider relationship; any vendor recovery still requires evidence and an authorized decision. Never create a rider payable or expose dispute financial data to riders.
- A successful dispute outcome does not itself justify releasing unrelated child-order funds. Reconcile the final provider outcome against the exact charge and only the affected vendor allocations.

## 13. Official Paystack references

- Webhooks and supported event names: https://paystack.com/docs/payments/webhooks/
- Refund lifecycle and refund events: https://paystack.com/docs/payments/refunds/
- Manage disputes and dispute webhooks: https://paystack.com/docs/payments/manage-disputes/
- Payment channels, including bank-transfer rejection: https://paystack.com/docs/payments/payment-channels/
- Webhook Events API for delivery history and retries: https://paystack.com/docs/api/webhook-events/

These sources describe Paystack's event vocabulary and provider status meanings. Victorious MARKET must still apply its own authorization, identity matching, idempotency, ledger, and reconciliation rules.

## Retailer-model supersession for new transactions (binding)

This matrix contains historical marketplace payout/allocation requirements as well as current payment/refund safety controls. For new retailer-model transactions, the owner-confirmed retailer model and `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md` supersede legacy vendor allocations.

- The 90/5/5 merchandise split, vendor delivery earning, vendor FIFO payout thresholds, vendor payout requests and former 90% logistics-fee allocation are historical-only. For new orders, logistics-company payable is the assigned company's snapshotted corridor base tariff plus its configured per-bulky-unit surcharge; never derive it as a percentage of the customer delivery fee.
- A supplier payable equals the immutable agreed wholesale cost for the selected supplier offer/order item and becomes eligible only after verified customer handover and reconciliation.
- For new orders, logistics-company payable is calculated from the assigned company's snapshotted corridor base tariff plus its configured per-bulky-unit surcharge. It is independent of the customer fee; any excess over customer delivery revenue is explicitly recorded as a VM-funded delivery subsidy/expense. Historical orders retain their original captured allocation for audit and refund calculations.
- VM remains responsible for approved customer refunds. Supplier recovery is separate, evidence-backed and contract-dependent and must not delay the refund.
- Preserve legacy allocation snapshots and reverse them only according to the original historical transaction. Never rewrite old transactions using new retailer rules.
- Cashback is active at 5% of eligible post-discount merchandise actually funded by real Paystack money, after cashback redeemed and excluding delivery. Existing terms remain: fulfilment + 24-hour claim window + separate 24-hour maturity buffer, no blocking case, 365-day validity from maturity, redemption capped at 50% of cart merchandise subtotal, reversal of unspent cashback and separate recovery of spent cashback only against future matured cashback. Preserve historical lots and obligations.
- The independent merchandise-versus-delivery refund decision, original payment route, provider verification, idempotency, refund ceilings, balanced journals and reconciliation requirements remain applicable. For new retailer transactions, component amounts must be based on their actual captured snapshots, not legacy percentages.

## 14. Binding rule: merchandise and delivery refunds are approved independently

The owner has confirmed that **delivery is never mixed with merchandise, and merchandise refunds and delivery-fee refunds must be approved independently**. This rule applies to historical multi-vendor parent/child orders and to new retailer orders wherever a delivery-fee component was actually captured. For new retailer transactions, reverse the actual captured component amounts and retailer accounting entries; do not use the legacy vendor percentage allocations below as new-order policy. A customer delivery-fee refund is not an automatic clawback of a logistics company's earned trip payable. Record the customer refund independently and handle any company recovery through a separate contract-authorized, audited adjustment; never delay the customer refund.

### Component decision matrix

| Component | Approval unit | Included in approved amount | Accounting reversal | Must not include |
|---|---|---|---|---|
| Merchandise/item | Specific child order + item/order-line(s) + quantity | Approved merchandise value after the original discount allocation; prior item refunds and cashback adjustments are accounted for | Reverse the actual captured retailer accounting entries for the approved merchandise amount and the associated cashback ledger adjustment, if applicable; the old vendor 90/5/5 split is historical-only | Delivery fee or delivery-fee commission |
| Delivery fee | The order's immutable customer delivery-fee snapshot and the separately snapshotted logistics-company tariff | Only the independently approved refundable customer-fee amount | Reverse the actual captured customer-fee and company-payable/margin entries for that order; do not recalculate using current tariffs. Historical orders retain the allocation actually captured at the time |
| Both components | Two distinct decisions and financial operations | Each component's independently approved amount | Post separate component-specific reversals and reconcile separately | One combined approval or undifferentiated ledger reversal |

### Required state and execution rules

1. A customer may submit one complaint/request mentioning both components, but the system must create separate decision records. Approval of merchandise does not approve delivery; approval of delivery does not approve merchandise.
2. Each component may independently be not_requested, pending_review, approved, rejected, gateway_pending, confirmed, failed, or needs_attention (map to existing canonical enums rather than introducing duplicate enums unnecessarily). One component can be pending or rejected while the other is confirmed.
3. Only an approved component can create a dispatchable refund operation. The two narrow automatic-approval exceptions already approved (verified duplicate successful capture and verified pre-vendor-action cancellation) must still record which captured component/amount is being corrected; they do not permit an undifferentiated combined refund.
4. For each approved component, create a distinct idempotent financial operation and reservation tied to the exact parent Paystack capture, child vendor order, component type, item/line IDs when applicable, amount in kobo, decision/approver and reason. A distinct component operation is not a new charge or payment channel: the actual refund still returns to the original Paystack payment channel.
5. Enforce component-level remaining amount, child-order allocation ceiling, and aggregate parent-capture ceiling under locks. Count confirmed refunds plus active reservations across every component and every child order. A refund of one component must reduce only that component's remaining balance and also reduce the shared parent capture's remaining refundable amount.
5a. **Shared history/cap, independent states:** each component operation must reference the same original parent capture and the exact child order/allocation, but retain its own approval, reservation, gateway state and audit history. Confirmed Paystack cash refunds plus active cash-refund reservations across all components and child orders count toward the single aggregate parent-capture refund ceiling. Do not count the same refund twice.
5b. **Cashback linkage is not a second gateway refund:** a merchandise refund must calculate the related cashback reversal/recovery from the immutable item allocation and cashback ledger. This shares source history with the merchandise refund but remains a separate cashback-ledger adjustment, not another Paystack cash refund; never add cashback recovery a second time to the parent-capture cash-refund ceiling. Cashback maturity/recovery has its own state and timing, while remaining linked to the originating order, item and refund operation.
6. A delivery-fee refund is impossible for in_shop_pickup because its captured delivery fee is zero. Never invent a delivery amount to refund. Where delivery was charged, whether it is refundable depends on the separately approved reason-specific policy and applicable law.
7. Merchandise and delivery gateway operations have independent states and idempotency keys. A Paystack refund.pending, refund.processing, refund.needs-attention, refund.processed, or refund.failed event must match the exact refund operation/provider identity; it must not close, fail, or overwrite a sibling component operation.
8. Customer refund completion requires provider-confirmed outcome for that exact component. refund.failed or an uncertain timeout leaves that component's customer obligation open and subject to reconciliation. Vendor recovery remains a separate evidence-based operation and does not gate customer refund completion.
9. Customer-facing status must show each component separately: amount requested, approved/denied decision, amount actually confirmed by Paystack, and any component still under review. Do not describe a combined total as refunded while one approved component remains unresolved.
10. The 24-hour customer claim window and the separate 24-hour cashback-maturity buffer remain unchanged. This rule separates component decisions; it does not extend the window or promise gateway processing within 24 hours.

### Required implementation and regression proof

- Model a component type (merchandise or delivery_fee) and item/line scope on the refund request/operation metadata or normalized columns; enforce valid values server-side.
- Remove flows that infer delivery-fee approval from merchandise acceptance or infer merchandise approval from delivery outcome.
- Reuse immutable captured allocation snapshots; never recalculate old refunds using current prices/vendor settings.
- Ensure component-specific idempotency binds the key to parent capture, child order, component, item scope, amount, reason and approval.
- Test the full cross-product of merchandise/delivery independent decisions, mixed success/failure/pending states, partial-item refunds, multi-vendor children, zero-fee pickup, concurrent reservations, duplicate webhooks and correct historical 90/5/5 reversals versus current retailer cashback and logistics-company payable/override adjustments.


## Owner-confirmed rule: platform-funded customer refund; separate vendor recovery

**Owner decision (20 October 2026):** Victorious MARKET funds an approved customer refund from platform-controlled available cash/refund liquidity even if the vendor has already received its payout. Customer refund execution must not wait for vendor repayment. When the evidence, authorised responsibility decision, and vendor agreement establish vendor liability, record a separate recoverable vendor receivable for the approved recoverable amount and pursue it through verified repayment or explicitly authorised contractual offset.

1. **Refund the customer first under the approved process.** The refund still requires the correct approval, original verified Paystack route where supported, component/child/parent ceilings, idempotency and provider reconciliation. Platform funding does not bypass those controls.
2. **Reverse the sale allocations once.** Reverse only the approved merchandise and/or delivery components using the immutable original allocation snapshot. Cancel eligible unspent cashback liability; handle already-spent cashback only through the separate cashback-recovery ledger. Do not reverse the same allocation twice.
3. **Vendor debt is not the refund amount by default.** Determine vendor liability from evidence, approved responsibility/fault allocation, the relevant component and the vendor contract. If the vendor's allocated proceeds have already been paid and are recoverable, record the approved recoverable portion as a separate receivable. Do not automatically charge the vendor for the platform share, gateway fees, cashback, or any other amount not established as recoverable. Shared fault can mean a smaller vendor receivable.
4. **No double recovery.** Link the customer refund, original child/item/component allocations, vendor-liability decision, receivable, repayment and any approved offset. The receivable must not exceed the approved recoverable amount. Verified repayment and approved offset reduce it exactly once.
5. **Liquidity is a platform responsibility.** Maintain and forecast a refund liquidity reserve/working-cash buffer that can cover approved refunds after vendor payout. An outstanding vendor receivable is not cash, must not be counted as available refund funding, and may be delayed or become unrecoverable. Track aged, disputed and written-off receivables separately.
6. **No automatic debt from a complaint.** Until an authorised, evidence-based decision establishes liability and amount, keep the matter as a dispute/case, not a confirmed vendor debt. Any offset needs explicit authorised approval and contractual basis, plus a linked ledger entry and vendor notification. A completed bank transfer is never silently treated as reversed.
7. **Illustration only:** For a ₦105,000 full refund (₦100,000 merchandise + ₦5,000 delivery) with original allocations of ₦94,500 vendor / ₦5,500 platform / ₦5,000 cashback liability, if ₦94,500 was already paid to the vendor, the platform funds the ₦105,000 refund. It reverses the original approved allocations once and cancels the eligible unspent ₦5,000 cashback liability. If full vendor responsibility and contractual recoverability are established, up to ₦94,500 may be recorded as vendor receivable; the platform's cash gap is funded temporarily from platform liquidity until recovery. This is not a blanket rule that all refunds create full-amount vendor debt.

Required tests: refund succeeds while recovery remains pending; receivable is excluded from available cash; partial/shared-fault liability; debt cap and no double recovery; verified repayment; authorised offset; vendor debt dispute; failed/uncertain refund; and duplicate webhook/retry.

## Mandatory accounting-balance and loss-recognition controls (20 October 2026)

Binding requirements for refund, settlement, webhook, reconciliation and finance implementation:

1. Every posted journal must balance (debits = credits in the same currency). Required financial postings must be atomic; do not mark an operation complete when its journal failed.
2. Pay the approved customer refund from platform-controlled available cash/liquidity through the verified original payment route where supported. Reverse original allocations through linked, idempotent entries; never delete original history or represent a completed vendor bank transfer as reversed.
3. Create vendor receivable only after authorised evidence and contractual terms establish responsibility and the recoverable amount. A complaint alone is not debt. Never automatically classify the whole customer refund as vendor debt.
4. Exclude receivables, disputed amounts, pending repayments, uncertain transfers and unreceived funds from available cash and refund-liquidity calculations.
5. Keep refund cash movement, sale/allocation reversals, cashback liability, vendor payout history, gateway/transfer fees and vendor receivable separately traceable. Retries, duplicate webhooks and reconciliation must not double-post, double-reverse or double-recover.
6. If an established receivable becomes unrecoverable, record an authorised impairment/write-off as a platform loss/expense according to accountant-approved treatment and applicable rules. Preserve the recovery trail; do not silently erase debt or call a write-off a cash receipt.
7. Finance reporting must distinguish cash/liquidity, vendor receivables and ageing, platform income/expenses, and recognised losses/write-offs. Balanced books do not imply profitability or sufficient liquidity.
8. Maintain a monitored refund-liquidity reserve/forecast and alert authorised staff to projected shortfalls. The reserve calculation must never count vendor receivables as cash.

Acceptance tests must cover balanced journals, refunds while recovery is pending, excluded receivables in cash totals, partial/shared responsibility, debt ageing and write-off, verified repayment, approved contractual offsets, duplicate callbacks, and preservation of completed vendor-transfer history. Obtain qualified-accountant review of final journal mapping before production.
