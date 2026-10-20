# VICTORIOUS MARKET — Payment, Refund, Settlement & Paystack Webhook Policy

**Status:** Owner-confirmed policy baseline for payment/refund decisions recorded on 20 October 2026. The business questions in this decision set are resolved; technical implementation and verification gaps remain open. Implementation gaps are not completed fixes.
**Prepared:** 20 October 2026
**Branch:** audit/paystack-refund-webhook-safety
**Canonical delivery rules:** .ai/BUSINESS_RULES.md
**No-merge rule:** Do not merge to main without explicit owner approval.

## 1. Scope and authority

This draft extends the approved marketplace model: one parent checkout can contain vendor-specific child orders; Victorious MARKET controls payment verification, customer refunds, vendor settlement, disputes and financial audit; vendors manage their own riders; riders have no financial role. It preserves the approved merchandise allocation of 90% vendor / 5% customer cashback / 5% platform and delivery-fee allocation of 90% vendor / 10% platform, with no rider payable.

The owner directed that the supplied recommendations be used, with Paystack bank transfers included alongside cards. The rules below are binding for the audit plan unless a specific item is marked **OWNER QUESTION OPEN**. These are business decisions; they do not mean implementation is complete. Do not merge to main without explicit approval.


### Final owner-confirmed launch contract

The owner has confirmed the following final choices. These are binding and supersede any older or conflicting proposal elsewhere in the repository:

- **Checkout shape:** one parent checkout and one Paystack capture for a multi-vendor cart, with separate vendor child orders, immutable child allocations, independent fulfilment, delivery fees, refund limits, and settlement eligibility.
- **Enabled payment methods:** Paystack card and standard Paystack bank transfer only. **COD is disabled** marketplace-wide. Dedicated Virtual Accounts (DVA), USSD, mobile money, and other channels are deferred.
- **Fulfilment:** vendors own delivery operations and add/manage their own riders; every active rider is linked to exactly one vendor and is operational-only. Victorious MARKET does not dispatch riders or pay rider compensation. Customer in-shop pickup remains supported as `in_shop_pickup`, has a zero delivery fee, and uses the owning vendor's `RES-XXXXXX` counter pickup pass.
- **Refund destination:** original Paystack channel only at launch. Wallet-credit refunds are deferred and must be denied at UI, API, and service layers.
- **Refund approval:** automatic approval is limited to a verified duplicate successful capture and a verified payment for an order cancelled before any vendor action. All other refunds require persisted authorized human approval before gateway dispatch.
- **Protection/cashback:** 24-hour return/refund/protection window plus a separate further 24-hour cashback-maturity buffer after qualifying fulfilment; no maturity while a blocking return/dispute remains. The cashback buffer does not extend the customer return/refund window. The 24-hour window is the standard customer return/claim window after fulfilment, not a gateway refund-processing SLA. Verified duplicate-charge corrections, pre-vendor-action cancellation refunds, payment exceptions, chargebacks, and applicable non-waivable legal obligations remain governed by their separate authorization and evidence rules. Recoverable spent cashback is offset against future cashback only at maturity, not by silently debiting spendable wallet funds.
- **Commercial split:** eligible post-discount merchandise collected, excluding delivery, is allocated 90% vendor / 5% cashback liability / 5% platform; delivery fee is allocated 90% vendor / 10% platform. Rounding remainder and Paystack processing fees belong to the platform share. No rider payable is created.
- **Vendor earnings and withdrawals:** every merchandise earning is independently recorded against its vendor order-item line; the 90% delivery-fee earning is a separate record tied to that vendor child order. Vendor payout requests have a ₦5,000 minimum and ₦500,000 maximum. Vendors may request whenever their unreserved eligible balance is at least ₦5,000; requests do not trigger automatic transfers. Every request requires authorized admin approval before transfer. Eligible excess above the requested/approved amount remains eligible for a later request. Earnings are tracked per order/earning record but normally aggregated into one payout request/transfer rather than paid per order. Reserve exact allocations atomically and prevent duplicate/concurrent claims. No rider or legacy logistics payout is created for new vendor-managed orders. Transfer-fee/stamp-duty allocation remains an open owner decision; never silently deduct it from vendor earnings.
- **Refund/settlement integrity:** partial refunds use immutable captured allocations and are limited by both each child order's refundable allocation and the aggregate parent capture. Refund success is distinct from vendor recovery success; vendor recovery is evidence-based and separately authorized.
- **Release control:** the policy documents are not proof of implementation. Keep all changes on `audit/paystack-refund-webhook-safety`; do not merge `main` without explicit owner approval.


## 1.1 Binding policy clocks and state distinctions

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


## 2. Checkout and payment decisions (Q1–Q8)

| Q | Proposed rule | Implementation / control |
|---:|---|---|
| 1 | One Paystack charge for a multi-vendor parent checkout; allocate the verified payment to child orders internally. | Parent amount/reference is authoritative for collection. Child allocations must sum exactly to parent total. Keep per-child fulfilment, fee, merchandise/cashback allocation and settlement eligibility separate. |
| 2 | A valid webhook or server-side transaction verification may establish payment. | Both entry points call the same authoritative settlement service and enforce exact reference, amount in kobo, currency, customer/order binding and idempotency. Browser redirect data alone never establishes payment. |
| 3 | Keep one valid payment for an order; a verified duplicate successful charge is eligible for the narrow automatic-refund exception. | Store every Paystack transaction/reference separately. A duplicate must never settle the order or credit cashback twice. Verify exact extra charge identity, amount and currency; confirm the parent checkout is already paid; create an auditable duplicate-charge case and idempotent refund operation; refund only the verified extra capture. |
| 4 | If payment succeeds after cancellation or stock is unavailable, do not reactivate or fulfil automatically; create a controlled refund case. Automatic refund is permitted only if the order was cancelled before any vendor action and every narrow exception guard passes. | Re-verify payment, lock/recheck inventory and order/vendor state, mark the payment as late/exceptional, prevent vendor settlement, and verify that no vendor accepted/prepared/dispatched/handed over goods and no prior refund covers the same capture. If any condition is uncertain, require authorized human approval. A webhook event alone never grants approval. |
| 5 | Underpayment: keep the order unpaid/on hold; do not fulfil. | Compare exact integer kobo to the expected parent checkout amount. Record mismatch and verified transaction for manual resolution. Never round, silently mark paid, or credit cashback/vendor proceeds. |
| 6 | Overpayment/wrong amount: do not fulfil automatically or convert excess to wallet credit. | Flag for reconciliation and refund/resolve only after payment verification. Distinguish a fully mismatched charge from a true excess where possible; do not issue an assumed difference refund without reconciling captured amount and order liability. |
| 7 | Launch channels: Paystack card + standard bank-transfer checkout only; COD is disabled. Dedicated Virtual Accounts (DVA), USSD, mobile money and other channels are deferred unless separately approved. | Configure checkout to expose card and standard bank transfer only and reject COD across all checkout/API/legacy paths. Both enabled channels use `charge.success` as the authoritative successful-payment event after exact server-side verification. Handle `bank.transfer.rejected` as a reconciliation exception; verify provider/refund evidence and never initiate a duplicate refund. Do not subscribe to DVA assignment events for launch. |
| 8 | Expired stock holds must not release stock while payment outcome is uncertain. | Verify/reconcile payment before releasing stock where payment may have succeeded or be in-flight. A late verified payment may be fulfilled only after an atomic stock recheck/reservation; otherwise open a controlled refund case. |

### Merchandise and delivery allocation arithmetic (binding)

- Calculate the 90/5/5 merchandise allocation from the actual **post-discount merchandise amount collected**, before adding delivery fee. Do not calculate from list price or an amount the customer did not pay.
- Allocate eligible merchandise value as 90% vendor, 5% cashback liability, and 5% platform share. Cashback is never calculated on delivery fees.
- Allocate the separately charged delivery fee 90% to the vendor and 10% to the platform. No part of this delivery allocation becomes a rider payable; vendor-to-rider compensation is paid outside platform systems.
- Calculate in integer kobo/decimal-safe arithmetic. Preserve the exact checkout allocation snapshot for refunds and settlement. Assign any rounding remainder to the platform share so the vendor share and cashback amount remain deterministic.
- Refunds must reverse the actual captured child-order allocation for the approved refundable component(s), considering prior refunds, discount allocation, cashback earned/redeemed, delivery-fee decision and whether vendor proceeds have already been paid. Never recompute an old refund from current product prices or current vendor settings.

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

## 4. Vendor settlement, disputes and failed orders (Q17–Q24)

| Q | Proposed rule | Implementation / control |
|---:|---|---|
| 17 | Vendor proceeds remain held while fulfilment is unconfirmed, the configured return/protection window remains open, or a return/dispute blocks release. | Successful customer handover for doorstep_delivery or confirmed vendor-counter handover for in_shop_pickup is necessary but not sufficient. Evaluate each child order independently, with bank-security, vendor eligibility, payout authorization and reconciliation controls. |
| 18 | A chargeback/dispute immediately flags the related payment/order, blocks unsettled related vendor proceeds, preserves evidence, records the response deadline and opens an admin case. | Resolve by authoritative dispute status and a recorded decision. Never release the hold solely because a reminder is absent. Keep customer/rider/vendor evidence access scoped by role. |
| 19 | An unmatched or mismatched transfer.success is recorded as unmatched for treasury reconciliation; never create a payout record solely from a webhook. | Match exact transfer/payout reference, expected recipient, amount, currency and operation. Alert authorized finance/admin staff; no automatic ledger repair from unmatched payloads. |
| 20 | A failed/reversed vendor transfer may be retried only after exact reference/status/amount/currency and returned-funds reconciliation. | Distinguish failed from reversed. Preserve the old attempt and use a new payout attempt/reference only after the financial service establishes retry safety. Do not let a delayed failure overwrite a confirmed success. |
| 21 | Failed delivery is operational first; financial outcome depends on reason and customer policy. | Record reason, attempt evidence and parcel custody/return-to-vendor. Admin/customer-support workflow decides redelivery, cancellation, delivery-fee refund, customer refund and vendor recovery. Rider endpoints must not create wallet credits or financial journals. |
| 22 | Never blindly retry an uncertain refund. Reconcile the existing refund first. | Query authoritative provider status using the existing reference where supported; use webhooks and scheduled reconciliation together. An uncertain timeout keeps the reservation/obligation open until status is resolved. |
| 23 | Vendors may request cancellation/refund and submit evidence; only the authorized platform workflow executes a customer refund. | Enforce authorization in backend services/routes, not only UI. A vendor or rider action cannot call a gateway refund endpoint directly or bypass approval. |
| 24 | Cancelled orders cannot settle, and cancellation must reconcile payment, refund obligation, cashback and reservations. | **Correction to supplied recommendation:** a refund attempt in failed status is not equivalent to a completed refund. Do not close the customer refund obligation or treat the order as financially resolved merely because the attempt is terminal-failed. Keep an explicit failed/retry-or-manual-review state until the obligation is satisfied or an authorized documented alternative is decided. |

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

### Vendor earning ledger and payout authorization lifecycle

Each earning is a separate traceable financial record, not merely a total stored on an order or vendor profile.

| Earning type | Required source link | Calculation and lifecycle |
|---|---|---|
| Merchandise vendor earning | Parent payment, vendor child order, exact order-item/line ID and immutable line allocation snapshot | 90% of that line's actual post-discount merchandise amount collected, in integer kobo. Hold until that earning passes payment, fulfilment, 24-hour protection-window, return/dispute, vendor/KYC, bank-security, and reconciliation checks. Item refunds/rejections reverse only the affected line's actual vendor allocation. |
| Delivery vendor earning | Exact vendor child order and immutable captured delivery-fee snapshot | 90% of that child order's delivery fee. Track separately from merchandise lines. Zero for in_shop_pickup. A parent-level aggregate without child attribution is not sufficient. |
| Platform merchandise share / cashback | Exact item allocation | The remaining 5% platform share and 5% cashback liability are not vendor earnings. Cashback has its own maturity and recovery rules. |
| Platform delivery share | Exact vendor child order delivery-fee allocation | 10% platform share; never a rider/logistics payable. |

Every earning record must retain amount in kobo, currency, vendor, source IDs, allocation version/snapshot, status, event/idempotency key, and any refund/recovery links. Use independent lifecycle states equivalent to held, withdrawable, reserved, paid, reversed/recovered, or blocked for review. Names may map to existing enums, but the semantics must remain distinct and auditable. The vendor's dashboard totals are derived from these records; they must not replace the source ledger.

#### Capped, reviewed vendor payout flow

1. **Eligibility calculation:** derive unreserved withdrawable funds on the server from earning records that passed all checks. Exclude held, reserved, paid, reversed, disputed, return-pending, unreconciled, or otherwise blocked earnings. Validate vendor/KYC status, verified bank details, 48-hour bank-change cooldown, recovery offsets, and other payout-security controls.
2. **Request amount:** enforce a ₦5,000 minimum and ₦500,000 maximum per request on every server endpoint. The amount must not exceed the vendor's unreserved eligible balance. A vendor may request any amount in that range; it is not required to equal the full balance. Reaching ₦5,000 does not trigger an automatic transfer.
3. **Aggregation and carry-forward:** earning records remain itemized by vendor order/earning source, but the normal disbursement aggregates the requested amount into one payout. Eligible balance not included in the approved request remains eligible for a future request. Do not pay each order separately by default.
4. **Atomic reservation:** calculate and reserve the exact requested amount and source earning allocations under transaction/row locks. Reject duplicate or concurrent requests that would reserve the same funds. Pending reservations reduce the unreserved balance. Do not trust client-supplied balance values.
5. **Admin review:** every request enters pending review. An authorized admin approves or rejects it, recording actor, timestamp, decision, and reason/notes. Approval must be distinct from transfer execution. Rejection releases the exact reservation once without erasing earning history.
6. **Admin payment:** only an approved request may be submitted by an authorized admin for Paystack transfer. Persist a unique transfer reference and processing identity before the gateway call. Preserve in-flight/uncertain state on timeout and reconcile before retry.
7. **Provider confirmation:** only authoritative successful confirmation matching payout reference, recipient, amount, and currency finalizes the payout, writes balanced journals, and marks exact source allocations paid once. Failed/reversed transfers use an audited recovery flow; late events must not overwrite confirmed success.
8. **Liquidity/provider limits:** ₦500,000 is the marketplace's own request cap, not a statutory Nigerian bank limit or guarantee of provider approval. Check actual Paystack balance, account-specific limits, and charges before transfer. An insufficient or uncertain provider result is not success.
9. **Transfer fees and stamp duty:** owner decision remains open on whether the platform absorbs or vendors bear outbound transfer charges. Do not silently deduct fees from vendor earnings; record the final owner-approved rule before launch and reconcile charges separately.
10. **No rider payout:** the vendor pays its own riders outside platform accounting. Do not use this payout flow to pay riders, logistics companies, or another vendor.

| Evidence | Internal result |
|---|---|
| No eligible earning records / zero withdrawable balance | Reject request; no payout record or transfer call |
| Vendor submits ₦5,000–₦500,000 request not exceeding unreserved eligible balance | Reserve exact earning allocations and create pending-review request |
| Client requests below ₦5,000, above ₦500,000, or above unreserved eligible balance | Reject; do not reserve any earning |
| Existing active request or concurrent request | Reject or return the existing request; never reserve the same earning twice |
| Pending-review request | No Paystack transfer call allowed |
| Admin rejects request | Record actor/reason; release exact reservations once; preserve earning history |
| Admin approves request | Approved only; no ledger payout-finalization yet |
| Admin initiates approved request | Persist transfer attempt/reference and move to processing; do not mark paid |
| transfer.success matching exact approved payout | Confirm transfer once; balanced journal; mark only snapshotted earnings paid |
| transfer.failed | Preserve obligation, reconcile returned funds; controlled release/retry only after safe reconciliation |
| transfer.reversed | Reversal state; post balanced reversal only if original disbursement journal exists |
| Unmatched/conflicting transfer event | Unmatched/manual treasury review; never invent a payout |
| Late failure after confirmed success | Protect success; unmatched/manual review, no blind reversal |
| Retry | New audited attempt/reference after reconciliation and re-authorization as required; never reuse a completed attempt as though it were new |

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

1. **Payment channels:** card + standard Paystack bank-transfer checkout only at launch. DVA, USSD, mobile money and other channels are deferred unless separately approved.
2. **Refund failure:** a failed Paystack refund attempt does not satisfy the customer refund obligation. Keep the obligation open, reconcile the provider's merchant-account credit and permit only a controlled retry.
3. **Automatic refund exception:** verified duplicate charge and verified pre-vendor-action cancellation are the only automatic-approval cases. A late payment caused by stock loss is not automatically eligible unless it independently satisfies every pre-vendor-action condition. The webhook event itself never approves or dispatches a refund.
4. **Cashback recovery:** offset future cashback only at maturity after fulfilment, the 24-hour return/refund window and separate 24-hour cashback-maturity buffer, provided no blocking case remains. Keep recovery separate from spendable wallet funds, disclose it in Terms & Conditions and complete legal/consumer-policy review before release.
5. **Wallet refunds:** disabled at launch; original Paystack channel only. Any later wallet-credit release requires explicit customer consent, timestamped consent, admin authorization and an audit log.
6. **Return/refund/protection window:** 24 hours, then a separate 24-hour buffer before eligible cashback matures, subject to blocking cases.
7. **Delivery-fee refunds:** vendor fault (including wrong/defective goods) and pre-dispatch cancellation may qualify for refund; customer change of mind and unjustified refusal normally do not; partial returns require reason-specific review and recorded decision.
8. **Commercial allocation:** merchandise split is 90/5/5 from actual post-discount merchandise collected, excluding delivery. Delivery fee is 90% vendor / 10% platform. Rounding remainder belongs to platform share. Preserve immutable allocation snapshots.
9. **Paystack fees and taxes:** platform bears Paystack processing fees from its own share; do not reduce vendor 90% or cashback 5%. Do not hard-code tax treatment until reviewed by a qualified Nigerian tax adviser.
10. **Chargeback losses:** vendor recovery is evidence- and fault-based. Recover only when documented vendor fault or approved allocation justifies it. If the platform provided required evidence but still loses, or responsibility remains ambiguous, the platform bears the loss unless an authorized case decision establishes a justified recovery.
11. **Multi-vendor checkout:** one Paystack charge for the parent checkout, with immutable child-order allocations and independent fulfilment/settlement gates.
12. **Bank-transfer rejection message:** “Your bank transfer could not be processed. We are checking the status with our payment provider and will update you within 24 hours. If funds left your account, they will be returned to you once confirmed. Please do not attempt another payment until you receive an update.” Never promise a refund until authoritative provider evidence confirms the applicable outcome.
13. **Vendor earnings/payout:** merchandise earnings are tracked per order-item line; delivery earnings are separate per vendor child order. Vendors can withdraw only the entire server-computed withdrawable balance; partial withdrawals are disabled. Admin approval and payment initiation are separate steps, and payment is complete only on verified Paystack success/reconciliation. Existing payout cadence/minimums are superseded where they conflict with this full-balance-only rule; existing bank-detail cooldown and other security controls remain. No rider or logistics-company payout is created for new orders.

These business questions are resolved. Technical implementation questions and verification gaps remain open in the checklist below.

14. **COD at launch:** cash on delivery is disabled across the marketplace at launch. Checkout, APIs, vendor settings and legacy payment-method paths must reject it. Enabled launch payment channels are Paystack card and standard bank transfer only. In-shop pickup is a fulfilment method with zero delivery fee, not a COD/payment exception. Any later COD release requires a separate explicit owner decision and a vendor-owned collection/reconciliation design that passes privacy, accounting, authorization and regression tests.

## 9. Required implementation changes / audit checklist

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
- [ ] Reject partial withdrawal requests; compute the full withdrawable amount server-side and atomically reserve all included earnings. Reject zero/mismatched amounts, duplicate active requests and concurrent double-reservation.
- [ ] Split admin payout approval from payment initiation. Pending review cannot call Paystack; only an approved request can enter processing; verified transfer success finalizes the exact earning snapshot.
- [ ] Test rejection/failed/reversed/timeout payout states, exact reservation restoration, webhook races, and no rider/logistics beneficiary for new vendor-managed orders.

### Refunds and cashback
- [ ] Persist authorization provenance before dispatch. Permit automatic approval only for verified duplicate charges and pre-vendor-action cancellation under the narrow policy in Q10; require authorized human approval for all other refunds.
- [ ] Ensure partial-refund calculations use captured immutable item allocations and include prior confirmed refunds plus active reservations.
- [ ] Ensure reservations are concurrency-safe across child orders, partial refunds, provisional holds and repeated idempotency keys.
- [ ] Enforce original-channel refunds only at launch; wallet-credit refund UI/API stays disabled. For a later release, require explicit customer consent, timestamp, admin authorization and audit log.
- [ ] Add secure `refund.needs-attention` handling, notifications and follow-up tracking. Preserve original-transaction Paystack-only refund policy; do not use alternate bank details/retry flow unless a separate owner-approved exception exists.
- [ ] On refund.failed, preserve the customer obligation and reservation policy; alert and reconcile provider credit before retry.
- [ ] Verify the default customer return/refund/protection window is 24 hours from verified fulfilment and cashback's earliest maturity is `refund_window_expires_at + 24 hours` (`+ 1 day`), only if no blocking case remains. The cashback buffer does not extend the customer claim window and is not a gateway refund SLA.
- [ ] Implement a cashback recovery ledger and offset future cashback before making new cashback spendable; document the policy in Terms & Conditions and complete legal/consumer-policy review.
- [ ] Make refund completion depend on matched refund.processed evidence and idempotent journal posting.
- [ ] Preserve partial-refund sibling states; one confirmed partial refund must not falsely mark the entire child order fully refunded/completed.
- [ ] Keep customer refund status separate from vendor recovery status.

### Vendor settlements and disputes
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
15. **Wallet refund launch boundary — partially remediated on the audit branch:** `ReturnAndRefundService::adminDisburseRefund()` now rejects non-empty manual bank/wallet references at the service boundary; rider doorstep-rejection and failed-delivery paths no longer create new `CustomerWalletCredit` refund records. Original Paystack is the only intended customer refund path. Still open: audit and disable any alternate UI/API/direct-credit path, ensure existing wallet credits cannot be mistaken for a newly approved refund, and complete end-to-end tests. Any later wallet-refund release requires explicit customer consent, timestamped consent, admin authorization and audit-log provenance.
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

- Victorious MARKET bears Paystack processing fees from its own platform share. The vendor's 90% merchandise allocation, customer's 5% cashback allocation, and vendor's 90% delivery-fee allocation must not be reduced to pay gateway fees.
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

## 14. Binding rule: merchandise and delivery refunds are approved independently

The owner has confirmed that **delivery is never mixed with merchandise, and merchandise refunds and delivery-fee refunds must be approved independently**. This rule applies to a single-vendor order and to every child order inside a multi-vendor parent checkout.

### Component decision matrix

| Component | Approval unit | Included in approved amount | Accounting reversal | Must not include |
|---|---|---|---|---|
| Merchandise/item | Specific child order + item/order-line(s) + quantity | Approved merchandise value after the original discount allocation; prior item refunds and cashback adjustments are accounted for | Reverse only the actual 90% vendor / 5% cashback / 5% platform allocation attributable to the approved merchandise amount | Delivery fee or delivery-fee commission |
| Delivery fee | Specific child order's captured delivery-fee component | Only the independently approved refundable delivery-fee amount | Reverse only the actual 90% vendor / 10% platform delivery-fee allocation | Merchandise value, merchandise commission, or cashback |
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
- Test the full cross-product of merchandise/delivery independent decisions, mixed success/failure/pending states, partial-item refunds, multi-vendor children, zero-fee pickup, concurrent reservations, duplicate webhooks and correct 90/5/5 versus 90/10 journal reversals.
