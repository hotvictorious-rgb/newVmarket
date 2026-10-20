# VICTORIOUS MARKET — Payment, Refund, Settlement & Paystack Webhook Policy

**Status:** Owner-decided policy baseline for payment/refund decisions recorded on 20 October 2026; the remaining questions at the end are unresolved and must not be guessed. Implementation gaps are not completed fixes.
**Prepared:** 20 October 2026
**Branch:** audit/paystack-refund-webhook-safety
**Canonical delivery rules:** .ai/BUSINESS_RULES.md
**No-merge rule:** Do not merge to main without explicit owner approval.

## 1. Scope and authority

This draft extends the approved marketplace model: one parent checkout can contain vendor-specific child orders; Victorious MARKET controls payment verification, customer refunds, vendor settlement, disputes and financial audit; vendors manage their own riders; riders have no financial role. It preserves the approved merchandise allocation of 90% vendor / 5% customer cashback / 5% platform and delivery-fee allocation of 90% vendor / 10% platform, with no rider payable.

The owner directed that the supplied recommendations be used, with Paystack bank transfers included alongside cards. The rules below are binding for the audit plan unless a specific item is marked **OWNER QUESTION OPEN**. These are business decisions; they do not mean implementation is complete. Do not merge to main without explicit approval.

## 2. Checkout and payment decisions (Q1–Q8)

| Q | Proposed rule | Implementation / control |
|---:|---|---|
| 1 | One Paystack charge for a multi-vendor parent checkout; allocate the verified payment to child orders internally. | Parent amount/reference is authoritative for collection. Child allocations must sum exactly to parent total. Keep per-child fulfilment, fee, merchandise/cashback allocation and settlement eligibility separate. |
| 2 | A valid webhook or server-side transaction verification may establish payment. | Both entry points call the same authoritative settlement service and enforce exact reference, amount in kobo, currency, customer/order binding and idempotency. Browser redirect data alone never establishes payment. |
| 3 | Keep one valid payment for an order; identify and refund any verified duplicate charge through a controlled workflow. | Store each Paystack transaction/reference separately. A duplicate must never settle the order or credit cashback twice. Add an auditable duplicate-payment case/operation; verify the duplicate charge and obtain required authorization before refunding it. |
| 4 | If payment succeeds after cancellation or stock is unavailable, do not reactivate or fulfil automatically; create a controlled refund case. | Re-verify payment, lock/recheck inventory and order state, mark the payment as late/exceptional, prevent vendor settlement, and route refund through the authorized approval/execution flow. A webhook must not bypass refund authorization. |
| 5 | Underpayment: keep the order unpaid/on hold; do not fulfil. | Compare exact integer kobo to the expected parent checkout amount. Record mismatch and verified transaction for manual resolution. Never round, silently mark paid, or credit cashback/vendor proceeds. |
| 6 | Overpayment/wrong amount: do not fulfil automatically or convert excess to wallet credit. | Flag for reconciliation and refund/resolve only after payment verification. Distinguish a fully mismatched charge from a true excess where possible; do not issue an assumed difference refund without reconciling captured amount and order liability. |
| 7 | Launch channels: card + Paystack bank transfer. Other channels (USSD, mobile money, etc.) are not enabled by this decision. | Configure checkout to expose only card and Paystack bank transfer. Both use `charge.success` as the authoritative successful-payment event. Add explicit `bank.transfer.rejected` exception handling. Do not confuse a rejected bank-transfer attempt with a separate refund request; Paystack documents automatic refund handling for invalid transfer amounts, so reconcile provider evidence and do not initiate a duplicate refund. |
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
| 9 | Refund to the original payment channel by default. Wallet refund only with explicit customer consent and recorded admin authorization. | Keep gateway refund and wallet-credit refund as distinct operation types, each with authorization, immutable evidence and separate ledger treatment. Do not assume wallet credit is equivalent to a gateway refund. |
| 10 | Authorized admin/support approval is required before a customer refund is executed; execution is a separate financial workflow. | Vendors/riders can request or provide evidence but cannot execute refunds. Webhooks report gateway state and must never create approval or initiate a refund by themselves. |
| 11 | Partial refunds are allowed for eligible items/quantities in multi-item or multi-vendor orders. | Calculate from immutable captured child-order item prices, quantities, discounts and allocations, delivery-fee policy, cashback, previous refunds, and any vendor proceeds already disbursed. Lock relevant order/return/operation and enforce a cumulative refund ceiling. |
| 12 | Delivery-fee refundability depends on reason and approved customer policy. | Suggested baseline: vendor fault/incorrect/defective goods or pre-dispatch cancellation may justify delivery-fee refund; customer change of mind or unjustified refusal may not. For partial item returns, decide whether the whole child-order delivery fee is refundable based on policy and facts. Admin records the decision; do not infer it solely from a webhook. |
| 13 | Cashback becomes spendable only after successful delivery or confirmed in-shop pickup and expiry of the 7-day (168-hour) protection/return window, with no blocking case. | Default `refund_window_hours` to `168`. Cashback maturity is scheduled for `refund_window_expires_at + 1 day` (24-hour buffer), subject to no blocking return/dispute and valid fulfilment. Cashback never applies to delivery fees. Do not use a hard-coded arbitrary future date. |
| 14 | If cashback has already been spent when the related item is refunded, reverse unspent cashback first and track any remaining recoverable amount for offset against future cashback accruals. | Add a customer-level cashback recovery ledger with amount outstanding, reason, originating order/refund, timestamps and adjustments. Future cashback first reduces this recovery balance; only the remainder becomes spendable. Never silently debit the customer's wallet or create a negative spendable balance. Disclose this rule in customer Terms & Conditions and retain an auditable adjustment trail. Legal/consumer-policy review is still required before release. |
| 15 | refund.needs-attention: leave the refund unresolved, alert admin, communicate what information is needed, and track follow-up. | Keep customer refund obligation open; do not mark refunded or release related controls as if complete. Capture required account-detail workflow securely; restrict access to sensitive bank details. |
| 16 | refund.failed: mark the attempt failed, preserve the outstanding customer refund obligation, alert admin and permit only a controlled retry after reconciliation. | Paystack documents that the merchant account is credited when a refund fails. Verify exact transaction/refund, account credit and current reservation before retry. Never retry blindly or mark the return case resolved just because one attempt failed. |

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
| refund.needs-attention | Needs attention/manual action | Alert admin; request required details securely; preserve obligation and follow-up deadline |
| refund.processed with matching operation, amount, currency and original transaction | Confirmed refund | Commit reservation and write idempotent balanced journals; update order/return state only after matching evidence |
| refund.failed with matching operation | Failed attempt / obligation still open | Verify provider account credit and refund identity; alert admin; controlled retry/manual handling only |
| Unmatched, conflicting or malformed event | Unmatched/manual reconciliation | Do not mutate financial operation/reservation/order; preserve event and alert authorized staff |
| Gateway timeout / uncertain result | Uncertain; reservation remains active | Reconcile the same attempt before any retry |
| Refund requested but not approved | Awaiting approval | No Paystack call and no final journal |

**Important:** Paystack says a refund reported as processed may still take up to 10 business days for the customer to receive funds. Customer messaging should say “processed” rather than promise instant bank receipt.

### Vendor payout/transfer lifecycle

| Evidence | Internal result |
|---|---|
| Payout requested but not authorized/eligible | Held; no transfer call |
| transfer.success matching exact payout operation | Confirm transfer once; balanced journal; close matching attempt |
| transfer.failed | Failed attempt; reconcile returned funds and preserve vendor payable/obligation |
| transfer.reversed | Reversal state; post balanced reversal only if original disbursement journal exists |
| Unmatched or conflicting transfer event | Unmatched/manual treasury review; never invent a payout |
| Late failure after confirmed success | Protect success; unmatched/manual review, no blind reversal |
| Retry | New audited attempt/reference after reconciliation; never reuse a completed attempt as though it were new |

### Dispute lifecycle

| Event | Required response |
|---|---|
| charge.dispute.create | Open dispute case, record deadline/amount/reference, preserve payment and fulfilment evidence, hold affected unsettled vendor proceeds |
| charge.dispute.remind | Update reminder/deadline and notify authorized case owner; keep hold |
| charge.dispute.resolve | Verify final dispute outcome, record evidence and decision, then apply approved financial recovery/release; never infer outcome from an unverified payload |

## 6. Paystack webhook subscriptions

Paystack's official documentation lists supported event names. Subscribe to events required by the Paystack products/channels Victorious MARKET actually enables. Webhook subscriptions do not replace server-side transaction/refund/transfer verification, database idempotency, or scheduled reconciliation.

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

## 8. Contradictions resolved and remaining owner questions

1. **Payment channels:** resolved for this audit: card + Paystack bank transfer. USSD, mobile money and other channels remain disabled unless the owner separately approves them. Open question: is the bank-transfer method the standard temporary bank-transfer channel only, or will dedicated virtual accounts (DVA) also be used?
2. **Refund failure:** resolved. A failed Paystack refund attempt does not satisfy the customer refund obligation. Keep the obligation open, reconcile the provider's merchant-account credit and authorize any retry.
3. **Automatic late-payment refund vs refund approval:** Q4 says controlled refund; Q10 requires admin approval. Late/duplicate/stock-unavailable payments therefore create a refund case, not an automatic gateway refund, unless a separate automatic-refund policy is approved.
4. **Cashback recovery:** selected policy is to offset future cashback against the outstanding recovery balance at the time cashback would otherwise mature. Keep this separate from spendable wallet funds, disclose it in Terms & Conditions, and complete legal/consumer-policy review before release.
5. **Wallet refund:** selected optional path is allowed only with explicit customer consent and recorded admin authorization; original channel remains the default.
6. **Cashback protection window:** selected value is 7 days (168 hours), with cashback maturation 24 hours after expiry when no case blocks it.
7. **Dispute resolution outcomes:** map final outcome to provider evidence and authorized accounting rules. A resolved dispute does not always imply vendor proceeds should simply be released.
8. **Allocation basis and rounding:** resolved: calculate the merchandise 90/5/5 split from actual post-discount merchandise amount before delivery fee; delivery fee is split 90/10 vendor/platform and earns no cashback. Assign rounding remainder to the platform share. Preserve the immutable allocation snapshot. Tax/gateway-fee treatment must not alter the approved basis silently and needs accounting verification before release.
9. **Vendor payout cadence:** fulfilment/return-window eligibility is specified, but payout schedule/batch timing, minimum payout, bank-detail cooldown and manual review thresholds remain existing approved controls unless separately changed.

## 9. Required implementation changes / audit checklist

These are requirements to verify against code; listing them does not mean they are already implemented.

### Payment collection
- [ ] Confirm one parent payment and immutable per-child allocation snapshots.
- [ ] Ensure webhook and transaction verification use the same strict settlement service.
- [ ] Record every verified charge/reference, including duplicate/late/mismatched charges.
- [ ] Add a controlled duplicate-payment case/operation and tests for double charge.
- [ ] Cover cancellation and stock loss between checkout and late payment; atomically recheck stock.
- [ ] Distinguish underpayment, overpayment, currency mismatch, reference mismatch and invalid payer binding; keep each from fulfilling.
- [ ] Add/verify reconciliation state for uncertain payment and safe stock-hold release.
- [ ] Restrict enabled payment channels to card + bank transfer; implement and test `bank.transfer.rejected` without duplicate refunding.
- [ ] Confirm whether dedicated virtual accounts are in scope; only then subscribe to `dedicatedaccount.assign.success` / `dedicatedaccount.assign.failed`.

### Refunds and cashback
- [ ] Ensure admin approval is persisted before dispatch, and cannot be created by a webhook.
- [ ] Ensure partial-refund calculations use captured immutable item allocations and include prior confirmed refunds plus active reservations.
- [ ] Ensure reservations are concurrency-safe across child orders, partial refunds, provisional holds and repeated idempotency keys.
- [ ] Add original-channel refund default; if wallet refund remains enabled, require explicit customer consent and separately audited admin authorization.
- [ ] Add secure refund.needs-attention handling, notifications and follow-up tracking.
- [ ] On refund.failed, preserve the customer obligation and reservation policy; alert and reconcile provider credit before retry.
- [ ] Change the default protection window to 168 hours and mature cashback at `refund_window_expires_at + 1 day`, subject to blocking cases.
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

13. **Protection-window default is inconsistent with the owner decision.** `ReturnAndRefundService` currently reads `SystemSetting::get('refund_window_hours', 24)`. Change the fallback to `168` and ensure any admin setting or seed value does not silently override the owner-approved 7-day baseline without explicit policy change.
14. **Cashback maturity must use the actual protection-window expiry.** Trace cashback creation/maturation jobs and remove any hard-coded `365-day`/far-future maturity timestamp. Mature at `refund_window_expires_at + 1 day` only after qualifying fulfilment and when no blocking return/dispute exists.
15. **Wallet refund path needs explicit consent and approval provenance.** Implement `refund_method = gateway` as default and `wallet_credit` only with customer-consent timestamp plus admin authorization/audit log. Keep wallet credit and gateway refund as distinct financial operations; webhooks cannot choose or approve the destination.
16. **Spent-cashback recovery is not yet a documented ledger workflow in code.** Add a customer-level recovery ledger, link entries to originating order/refund, apply recovery against future cashback at its maturity point, prevent negative spendable wallet balances, and add customer Terms & Conditions text before enabling this behavior.
17. **Split basis and rounding need a checkout-path proof.** Verify `BcMathService::computeCommercialSplits()` receives the actual post-discount merchandise amount, excludes delivery fees from cashback, and allocates rounding remainder to platform share. Persist the snapshot and test discount + delivery-fee edge cases.
18. **Bank-transfer rejected events need a distinct handler.** Verify raw signature and event identity, bind the event to the correct Paystack payment/reference, record a reconciliation exception, and inspect provider status before messaging or refund action. For amount-mismatch rejection, Paystack may automatically refund; never double-refund based only on the webhook name.

1. **Refund lifecycle events are not all handled explicitly.** The current PaystackPaymentController handles refund.processed and refund.failed, then treats other event names as ignored. Explicit state tracking/notification is still needed for refund.pending, refund.processing and refund.needs-attention.
2. **Dispute webhooks are not handled explicitly.** The current controller has no charge.dispute.create, charge.dispute.remind or charge.dispute.resolve branch. Add a dispute case, response deadline/evidence tracking and a hold on the affected unsettled vendor child order.
3. **Late-payment stock exception can dispatch a refund from the charge.success webhook.** The current handler calls createRefundOperation() and dispatchRefund() when settlePaystackOrder() returns trigger_gateway_refund. This appears to bypass the proposed admin-approval rule in Q10. Change the path to create a refund-review case and dispatch only after the required authorization, unless the owner explicitly approves a narrowly scoped automatic-refund exception.
4. **Refund reconciliation selects return cases in refund_pending as well as refund_approved.** Confirm the exact lifecycle meaning of refund_pending. If it can represent a customer request awaiting admin approval, it must not authorize gateway dispatch. Only a persisted approved decision should permit execution.
5. **Refund state and retry obligations need a distinct customer-obligation status.** The existing reconciler skips orders whose order-level paystack_refund_status is failed, while a failed Paystack refund attempt can leave the customer still owed money. Ensure order summary state does not suppress retries/manual review for an outstanding failed operation.
6. **No duplicate-payment case is evident in the inspected webhook path.** A charge.success reference not matching an order is marked failed/unmatched, but a second verified successful reference for an already-paid parent checkout needs a dedicated duplicate-charge record and controlled refund path. Never attach it to the original payment as if it were the same charge.
7. **Payment mismatch review needs operational visibility.** The settlement service rejects invalid amount/currency/reference/payer evidence, but ensure the original transaction, received kobo amount, expected kobo amount, event and reason are retained in a reconciliation case without marking the order paid or leaking details to riders.
8. **Payment-channel implementation gap.** The policy now enables card + bank transfer. Verify checkout's allowed channel configuration and implement/test `bank.transfer.rejected`. For amount-mismatch cases, confirm the provider's actual refund status before taking action; the rejection event alone must not trigger a second refund.
9. **The draft refund policy requires approval provenance.** Verify that admin actor, approval timestamp, reason, approved amount, delivery-fee decision, destination (gateway vs wallet), and allocation snapshot are stored before dispatch. A status name alone is not sufficient evidence of authorization.
10. **Partial refund delivery-fee policy needs a first-class snapshot.** The inspected ReturnAndRefundService initializes refund_delivery_fee as false. Ensure the authorized reviewer can set and audit the decision, and that vendor/platform delivery-fee allocation is reversed only for the approved refundable amount.
11. **Cashback maturation/recovery implementation gap.** Code currently defaults `refund_window_hours` to 24; change the default to 168. Replace any hard-coded far-future cashback date with `refund_window_expires_at + 1 day`, blocked by open cases. Implement a customer recovery ledger and offset future cashback at maturity before any remainder becomes spendable.
12. **Dispute outcome must be linked to the actual charge.** A dispute event should be matched using the provider transaction identity/reference and validated against the affected parent/child payment. Do not freeze or release unrelated child-order proceeds just because a parent checkout includes multiple vendors.

## 11. Remaining questions for the owner

1. **Bank transfer implementation type:** should launch use Paystack's standard bank-transfer checkout channel only, or standard bank transfer plus Dedicated Virtual Accounts (DVA)? DVA adds customer account-assignment lifecycle events and a different customer experience.
2. **Wallet refund launch scope:** the policy allows it only with explicit customer consent and recorded admin authorization. Should the UI/API be enabled at launch, or should the rule be documented now but the feature remain disabled until a later release?
3. **Cashback recovery timing:** if a customer has an outstanding cashback recovery balance, should every future cashback award be applied to that balance immediately when earned, or only when it would otherwise mature after the 7-day protection window? Recommended: apply the offset at maturity, so un-matured cashback is not prematurely diverted and the existing maturity rules remain clear.
4. **Refund exception approvals:** should low-risk refunds (e.g. pre-dispatch cancellation with no delivery, verified duplicate charge) ever use a narrowly scoped automatic approval policy, or must every refund require an authorized human approval? Current rule: every refund requires persisted admin approval before dispatch.
5. **Delivery-fee refund matrix:** confirm whether the proposed baseline is acceptable: vendor fault/incorrect/defective item or pre-dispatch cancellation can refund delivery fee; customer change of mind/unjustified refusal normally does not; partial item return requires reason-specific review. Until confirmed, admin must decide case-by-case and the system must not infer it.
6. **Bank-transfer rejected customer message:** choose the exact message for `bank.transfer.rejected`. Recommended: say the transfer was rejected and its status is being checked; only say an automatic refund is processing after authoritative provider evidence confirms that refund status. Never promise immediate bank receipt.

## 12. Official Paystack references

- Webhooks and supported event names: https://paystack.com/docs/payments/webhooks/
- Refund lifecycle and refund events: https://paystack.com/docs/payments/refunds/
- Manage disputes and dispute webhooks: https://paystack.com/docs/payments/manage-disputes/
- Payment channels, including bank-transfer rejection: https://paystack.com/docs/payments/payment-channels/
- Webhook Events API for delivery history and retries: https://paystack.com/docs/api/webhook-events/

These sources describe Paystack's event vocabulary and provider status meanings. Victorious MARKET must still apply its own authorization, identity matching, idempotency, ledger, and reconciliation rules.
