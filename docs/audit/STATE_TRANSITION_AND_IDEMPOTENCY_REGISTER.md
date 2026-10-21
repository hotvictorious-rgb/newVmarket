> **Current retailer policy (21 October 2026):** new doorstep delivery uses a 90% default logistics-company payable with audited Super Admin override before rider custody; the amount is paid to the company, not its rider. Active cashback remains 5% of eligible merchandise actually funded by real money used to complete the order, under the existing claim/maturity/expiry/redemption/recovery terms. Legacy vendor 90/5/5 and vendor-managed delivery state transitions below are historical-only for old records and must not drive new retailer orders.

> **Current owner policy (21 October 2026):** This register contains historical marketplace/vendor transitions for audit and migration. For new retailer transactions, the old 90/5/5 merchandise split and vendor delivery earning do not apply. Cashback remains active at 5% of eligible post-discount merchandise funded by verified real Paystack money after redeemed cashback, excluding delivery; existing 24h claim + 24h maturity buffer, 365-day validity from maturity, 50% cart merchandise redemption cap, refund reversal and spent-cashback recovery terms remain. The logistics-company payable defaults to 90% of the snapshotted customer delivery fee, paid to the company rather than its rider, and can be overridden higher/lower by an authorized admin with immutable reason/approver/time audit. Vendor FIFO payout and rider payable transitions below are legacy-only. New supplier payable uses item-level agreed wholesale-cost snapshots. Do not treat this notice as proof that runtime code has been migrated.

# Victorious MARKET — State Transition and Idempotency Register

**Status:** Initial system-wide inventory; implementation verification is intentionally incomplete.
**Branch:** `audit/paystack-refund-webhook-safety`
**Rule:** No event may apply a state transition or financial effect unless its source state, authorization, evidence, and idempotency identity are valid. Duplicate delivery must return the recorded outcome without repeating the effect. Unknown provider outcomes remain pending/uncertain until reconciled; never guess success or failure.
**Important:** The lists below distinguish states found in current enums/policy from required lifecycle states that still need code-level mapping. They are not a claim that every transition is currently enforced.

## 1. Global transition contract

Every state-changing command/event must:
1. Identify the aggregate and unique business operation (order/child, payment capture, refund operation, earning, payout, recovery, reservation, handover, case).
2. Lock/reload the current aggregate and verify the expected source state inside the same database transaction as the transition and local ledger writes.
3. Enforce an explicit allowed-transition table; reject illegal skips, backward transitions, cross-fulfilment transitions, and terminal-state mutation except through an explicit reversal/compensation workflow.
4. Enforce authorization and evidence at the final service boundary, not only in controllers/UI.
5. Use a stable unique idempotency key for inbound provider events and outbound financial operations; database uniqueness is required. A check-then-insert without a unique constraint is not sufficient.
6. Atomically record the event outcome and all local effects, or use a transactional outbox/inbox pattern where external gateways cannot participate in the DB transaction.
7. Verify provider signature, environment, reference, amount, currency, transaction ownership and current operation before accepting a webhook.
8. Treat duplicate event ID/reference as replay: return/no-op against the already-recorded result. Same key with conflicting payload must be quarantined for investigation.
9. Treat network timeout as `uncertain`, not failed or successful; retain reservations until verified reconciliation.
10. Write immutable ledger entries and compensating entries, never edit/delete a completed financial fact to hide a duplicate.
11. Make scheduled jobs, queues, admin actions, APIs, webhooks and callbacks invoke the same domain transition service.
12. Log actor, source state, target state, operation key, evidence/reason, timestamps and correlation IDs without logging secrets or customer OTPs.

## 2. Order lifecycle states found in `OrderStatus`

### Doorstep delivery values
`pending`, `confirmed`, `processing`, `ready_for_dispatch`, `ready_for_pickup` (legacy/staged alias), `shipped`, `out_for_delivery`, `delivered`, `delivery_attempt_failed`, `doorstep_rejected`, `returned_to_vendor`, `disputed`, `returned`, `cancelled`, `paid_stock_unavailable`.

### In-shop pickup values
`pickup_reserved`, `ready_for_counter_pickup`, `counter_inspecting`, `picked_up`, `counter_rejected`, `pickup_expired`, `pickup_cancelled`.

### Required transition constraints
- Checkout creates a pending payment/order state; it must not mark an order paid/confirmed merely because the requested payable amount is zero or a legacy wallet balance exists.
- Verified payment can advance only the matching unpaid/pending order/parent capture; repeated success callbacks cannot re-consume inventory, duplicate cashback, duplicate journals, or duplicate notifications.
- Vendor acceptance/preparation is a real transition and forms the cancellation boundary; post-boundary cancellation must use a remedy/return/refund flow, not silently rewrite history.
- Delivery states are only valid for `doorstep_delivery`; pickup states only for `in_shop_pickup`. `delivered` and `picked_up` require their distinct server-verified handover evidence.
- Delivery completion requires valid one-time customer OTP plus doorstep photo. Pickup completion requires reservation pass, separate one-time customer OTP plus in-shop photo. OTPs cannot be reused or exposed through unrelated views.
- Failed attempt, rejection, dispute, return, cancellation and stock-unavailable paths must not bypass payment/refund authorization or terminal-state guards.
- The enum's `isTerminal()` includes `delivered` and `picked_up`; those mean fulfilment completed, not that all later claims/refunds are impossible. Claims are separate aggregates.
- **OPEN verification:** enumerate every writer of `orders.order_status`; prove all use one transition guard and verify no direct arbitrary status assignment remains. Explicit transition edges are not yet certified by this inventory.

### Late payment after expiry — additional audited edge
- A verified success for a previously cancelled order may reinstate it only if the locked availability check passes; otherwise it enters the refund-liability-only path.
- Successful reinstatement for `in_shop_pickup` must also transition the linked eligible reservation to `payment_completed` in the same settlement transaction. A paid/confirmed order paired with a cancelled/expired reservation is an invalid cross-aggregate state.
- VM-AUD-059 regression covers the linked reservation restoration; CI must pass before this edge is considered verified.

## 3. Payment lifecycle

### Current `PaymentStatus` enum
`unpaid`, `pending`, `pending_payment`, `paid`, `partially_refunded`, `refunded`, `failed`.

### Required event/state handling
- Checkout initiated: `unpaid/pending_payment -> pending` only with a persisted unique payment attempt/reference.
- Provider initialization failure before any charge: record failure for that attempt; order remains unpaid and retry uses a new attempt/reference.
- Verified capture/success: `pending/unpaid -> paid` once; amount/currency/order/reference must match. A duplicate successful capture is not a second order payment; record a duplicate-capture exception and refund only under the documented automatic exception.
- Provider failure: may mark that attempt failed only if verified and no successful capture exists for that reference. Late failure must not downgrade a paid transaction.
- Timeout, missing webhook, or conflicting status: `uncertain/pending` operational state until query/reconciliation; do not mark failed or release inventory/cashback reservation on timeout alone.
- Refund success updates payment aggregate to `partially_refunded` or `refunded` based on confirmed cumulative refunds; request/approval/dispatch alone must not set `refunded`.
- **OPEN verification:** payment-attempt entity/statuses, uniqueness constraints, late-event ordering, bank-transfer confirmation, and all callback/webhook/controller/job writers require exhaustive code audit.

## 4. Return and refund case lifecycle

### Current `ReturnCaseStatus` enum
`requested`, `pending`, `in_transit`, `in_transit_back_to_vendor`, `inspected`, `approved_for_return`, `refund_pending`, `refunded`, `rejected`, `closed`.

### Required event/state handling
- Customer request or authorised late/direct-contact intake: create `requested` once per valid case identity; intake is not approval.
- Review starts: `requested -> pending` only by authorised workflow.
- Return logistics/evidence: transit and inspection states only where the return method requires them; never require a hub step where policy does not.
- Decision: `pending/inspected -> approved_for_return` or `rejected`, with persisted actor, reason, ruling and authorized merchandise/delivery component scope.
- Refund preparation: `approved_for_return -> refund_pending` only after persisted approval and exact amount/child/item/component ceiling validation.
- Gateway accepted/processing/timeout remains a pending/uncertain refund operation; do not set case `refunded` until provider confirms success.
- Provider-confirmed full case refund may set `refunded`; partial component refund must preserve remaining allowed balance and cannot close/refund unrelated components.
- `closed` requires a defined terminal outcome; cannot erase a still-owed customer remedy or unresolved gateway outcome.
- Late case intake is allowed for authorised admins with a recorded reason; the normal 24-hour rule is not a blanket waiver of statutory rights.
- **OPEN verification:** one explicit transition table across service, controllers, admin UI, webhook and reconciliation is not yet certified.

## 5. Refund-operation lifecycle (required canonical model; map existing fields before altering schema)

Required states: `requested -> authorization_pending -> approved -> dispatching -> provider_pending -> succeeded`; alternate paths `rejected`, `failed_confirmed`, `uncertain`, `needs_attention`, and `cancelled_before_dispatch` only when provider confirms no dispatch occurred.

- Persist the human approver and component scope before dispatch except the two approved auto-approval exceptions: verified duplicate successful capture and verified payment for an order cancelled before vendor action.
- One immutable operation identity per refund attempt; retries after a confirmed provider failure may create a linked attempt only after reconciliation proves no successful refund exists.
- Never issue wallet-credit/off-platform refunds at launch.
- Enforce child allocation, aggregate parent capture, item quantity/value, merchandise component and delivery-fee component ceilings under row locks.
- Repeated webhook/callback/admin-click/queue retry must not create a second gateway refund or second journal.
- `refund.failed` or `refund.needs-attention` is not proof the customer is owed nothing; retain the case for reconciliation.
- **OPEN:** confirm whether current schema already represents all states; do not introduce parallel status columns without first mapping existing refund operation data.

## 6. Vendor fulfilment and handover events

- Vendor order acceptance/preparation: only valid from the appropriate paid/reserved state; duplicate accept is idempotent; rejected/expired/cancelled order cannot be resurrected by late acceptance.
- Vendor rider assignment: rider must belong to the same vendor; assignment is operational only, not a platform logistics payable. Assignment change must not reset handover evidence or financial state.
- Doorstep: `ready_for_dispatch -> shipped -> out_for_delivery -> delivered` only through allowed edges; failed attempts/rejection use explicit exception transitions. The customer OTP and photo are one-time evidence; concurrent submissions must have one winner.
- In-shop pickup: `pickup_reserved -> ready_for_counter_pickup -> counter_inspecting -> picked_up`, with explicit counter rejection/expiry/cancellation branches. Require reservation pass + customer OTP + photo and verified payment before handover.
- Vendor-to-rider custody OTP, where still present in legacy paths, is not customer handover proof and must never be returned as the customer's pickup PIN.
- **OPEN:** audit every vendor/rider API and admin endpoint for direct status writes, ownership checks, replay races, OTP clearing, and photo storage.

## 7. Cashback lifecycle

Required business states: `reserved`, `pending_maturation`, `matured/spendable`, `redeemed`, `expired`, `reversed_unspent`, plus separate `recovery_due`/recovery ledger for already-spent amounts (do not make the customer's cash wallet negative).

- Create cashback liability only once from verified eligible merchandise allocation; no cashback on delivery.
- Reserve redemption atomically and enforce the 50% merchandise-subtotal cap; payment retry/failure releases only the matching reservation once.
- Start the 24-hour claim clock from each child order's verified fulfilment time; mature no earlier than claim-window expiry plus separate 24-hour buffer and only if no blocking case remains.
- 365-day expiry starts at actual `matures_at`; null maturity means not spendable.
- Refund reverses unspent cashback once. Spent cashback recovery is separate and can offset only future cashback when it matures; never silently debit cash wallet.
- Duplicate fulfilment/refund/maturity jobs must not double-credit, double-reverse or mature early.
- **OPEN:** enumerate cashback ledger status values, uniqueness keys, concurrent reservation behavior and every scheduled job/service writer.

## 8. Vendor earning, settlement and payout lifecycle

### Earning/settlement
Required concepts: `held -> eligible -> reserved_for_payout -> paid_out`; exceptions/holds remain separately represented (dispute, return, security, reconciliation). Never turn eligibility directly into transfer success.
- Vendor merchandise earning must be tied to child-order item allocation; delivery earning is a separate record tied to the child order.
- Eligibility requires verified fulfilment, that child's 24-hour claim window expired, and no blocking case/security/reconciliation issue.
- Every earning can be allocated to at most one active payout; strict FIFO uses stable `created_at, id`; only whole records; never split or skip; maximum request ₦500,000, minimum ₦5,000.
- Platform pays transfer fee; no rider payable for vendor-managed orders.
- **OPEN:** map these conceptual states to current earning records and ensure database constraints prevent double allocation/settlement.

### Payout request/transfer
Required operational states: `pending -> approved -> processing/in_flight -> succeeded`; branches `rejected`, `failed_confirmed`, `uncertain` and explicit reconciled retry. Existing persisted vocabulary may differ and must be mapped before migrations.
- One active vendor request across pending, approved-unsent, processing, in-flight and uncertain.
- Admin approval precedes gateway transfer. Amount and FIFO allocation plan are recalculated/locked at approval and revalidated at dispatch.
- Timeout/network exception retains reservation as uncertain. Only verified provider status releases or finalizes it.
- Success finalization is idempotent; failed confirmed transfer restores the exact original whole earning reservations once; never reverse a completed transfer without a real reversal event.
- **Payout liability journal contract (remediation in CI):** request reclassifies Dr `vendor_payable` / Cr `vendor_payout_payable`; confirmed transfer clears Dr `vendor_payout_payable` / Cr `cash_in_escrow`; rejection or failed-before-success reverses Dr `vendor_payout_payable` / Cr `vendor_payable`; a verified successful-transfer reversal restores Dr `cash_in_escrow` / Cr `vendor_payable`. If the original request journal is missing, do not invent a reversal; log and route to finance reconciliation. Legacy allocation-discrepancy journal paths remain separately flagged for finance review.
- **OPEN:** confirm all web/API/admin/reconciliation paths share one state machine and all unique operation references are database-enforced.

## 9. Vendor recovery, repayment, offset and write-off

Required states: `proposed` (not a receivable), `authorized_receivable`, `partially_repaid`, `repaid`, `disputed`, `impaired/writeoff_pending`, `written_off`. Use the actual schema only after mapping it.
- Customer refund funding and vendor recovery are separate processes. Refund can be owed and funded even if vendor cannot repay.
- Create receivable only after platform refund funding and an authorized, evidence-based fault ruling plus contractual basis.
- Repayment counts only after bank/provider verification; screenshot is not proof.
- Offset requires explicit authorized approval and contractual basis; no automatic debt deduction from withdrawable balance/cashback.
- Write-off/impairment requires authorized decision and accountant-approved mapping; balanced journals do not by themselves prove liquidity or absence of loss.
- Idempotency key must prevent duplicate receivable creation and duplicate repayment application; never recover the same amount twice.
- **OPEN:** repayment, approved offset and write-off workflows need implementation and tests.

## 10. Parent checkout and multi-vendor allocation

Required aggregate states: parent checkout/payment attempt and per-vendor child-order lifecycle must remain separate. Parent payment success is not proof that every child has been fulfilled; one child's fulfilment/refund must not mutate another child's state.
- One parent capture, immutable vendor-child allocations, independent vendor fulfilment, delivery fees, return windows, refunds, cashback maturity and settlement.
- Parent capture ceiling is shared across child refund operations; each child and each item/component also has its own remaining refundable ceiling.
- Parent status is derived from child states under a documented aggregation rule, not blindly copied from one child.
- Duplicate checkout submission/payment initialization must be idempotent or return the existing checkout result; unique checkout idempotency key and unique provider reference are required.
- **OPEN / CRITICAL (verified 2026-10-20):** `CheckoutExecutionService::execute()` and `CheckoutAndReservationApiController::createIntent()` require one `shop_id`, reject items outside that shop, and persist one shop-scoped order/intent. `OrderSettlementService` has a branch that allocates a paid parent to existing children, but the active checkout path does not create those parent/child rows. The orders migration also makes `vendor_id` and `shop_id` non-null for every order, including any proposed parent, while `payment_reference` is indexed but not declared unique in that migration. Before implementation, decide parent identity/reference uniqueness, child reference mapping, single Paystack capture ceiling, allocation idempotency, per-child delivery pricing/fulfilment, partial refunds and reconciliation. No parent/child implementation should be considered complete until DB migrations and end-to-end concurrent acceptance tests prove these invariants.

- **Implementation plan — parent/child checkout (design gate, not yet implemented):**
  1. Add a forward-only migration for an explicit parent aggregate shape. Decide whether parent rows may have nullable `vendor_id`/`shop_id` or whether a separate checkout aggregate table owns the parent; do not insert fake vendor/shop IDs. Preserve legacy single-vendor orders.
  2. Make checkout intents represent the full cart snapshot grouped by vendor/shop, including server-derived item prices, quantities, each child’s fulfilment type/address/delivery quote, cashback allocation, and one aggregate payable. Client-supplied totals are never authoritative. Lock products in deterministic ID order and revalidate stock, shop/vendor status, address and delivery availability inside execution.
  3. Establish one unique parent payment reference for one Paystack capture. Children must link to the parent and must not independently settle the same capture. Webhook lookup must resolve exactly one payment owner, verify amount/currency/reference against the parent total, and invoke an idempotent atomic allocator. Do not reuse one reference as if each child were a separate capture; add a unique database constraint after checking/migrating existing duplicates.
  4. Allocate the captured amount to child merchandise and delivery components using integer kobo/BCMath and deterministic remainder handling; ensure child totals sum exactly to the parent capture. Apply the approved 90/5/5 merchandise allocation to eligible actual post-discount merchandise collected, and the 90/10 delivery split to delivery fees only. Cashback redemption remains a capped merchandise discount; delivery earns no cashback.
  - **Additional settlement guard found during audit (VM-AUD-031):** the current dormant parent settlement branch does not verify the child aggregate sums against the parent before marking children paid and posting journals. Before enabling parent/child checkout, lock child rows in deterministic ID order and reject if child ownership/reference is inconsistent or if sums of merchandise, delivery, cashback discount, grand total, vendor net, cashback liability, platform merchandise margin, vendor delivery earning, or platform delivery fee differ from the parent. A mismatch must return a fail-closed result before any financial side effect; test both under-allocation and over-allocation, duplicate/concurrent webhook replay, and exact journal balance. No rider/fleet payable is created.
  5. Keep parent payment state separate from each child’s fulfilment, settlement hold, delivery proof, 24-hour claim window, and payout eligibility. A child can fail/cancel/fulfil independently without falsely changing siblings. Parent display aggregates child states but does not replace the child audit trail.
  6. Extend refund operations with parent/child/item/merchandise/delivery component ceilings, reserve each component atomically, and reconcile partial refunds against the original capture. Never allow the sum of child/component refunds to exceed the parent’s verified captured amount; a customer refund is not delayed by vendor recovery.
  7. Acceptance tests must cover mixed-vendor carts, duplicate product IDs across vendors, inactive vendor/shop, stock races, quote changes, payment timeout then webhook, duplicate/concurrent webhooks, partial child fulfilment, one child cancellation, merchandise-only and delivery-only refunds, concurrent refund ceiling races, payout eligibility isolation, and exact balanced journals. Add migration-upgrade tests and verify historical single-vendor orders remain readable.
- **Do not start implementation until** the order/intent schema, Paystack reference ownership, child delivery quote semantics, and cashback-on-discounted-merchandise accounting are covered by tests. These are explicit safety gates, not permission to invent defaults.

## 11. Webhook inbox and idempotency coverage

For each provider event, persist provider + provider event ID (or documented stable fallback identity), reference, event type, payload hash, received time, processing status, attempt count and outcome. Unique index must prevent concurrent duplicate processing. If provider lacks stable event IDs, derive a stable identity from immutable provider fields and treat same identity/different payload as a conflict.
- Verify signature against raw request body and correct secret/environment before trusting event content.
- Handle out-of-order events by querying provider state and applying only a valid transition; older failure events cannot downgrade success.
- Record unsupported/unmatched events as ignored/quarantined with reason; do not mutate money.
- **Verified subcase (VM-AUD-063):** valid unsupported event types now transition inbox `processing -> completed` with `processed_at`; a duplicate of the same event returns the recorded outcome without increasing attempts. This does not close the broader webhook coverage item below.
- Use transactional outbox for external dispatch and unique operation keys for gateway refund/transfer creation.
- **OPEN:** `ProcessedWebhookEvent` exists, but full coverage, unique index, raw signature verification, transaction boundaries, and all gateway event types must be inspected and tested. Do not infer coverage from the model existing.

## 12. System event inventory — required audit coverage

Every occurrence below must be traced to every writer, including controllers, services, observers, listeners, scheduled commands, queue jobs, webhook handlers, callbacks, admin tools and mobile API routes.

| Domain | Events to inventory | State/effect that must be idempotent |
|---|---|---|
| Checkout | create/retry, reserve stock, reserve cashback, initialize payment, abandon/expire | parent/child creation, stock reservation, cashback reservation, payment reference |
| Payment | provider initialized, success/capture, failed, transfer/bank confirmation, duplicate capture, timeout, reconciliation | payment attempt/order paid state, allocation journals, stock confirmation, notifications |
| Fulfilment | vendor accept/reject, prepare, ready, assign/change rider, dispatch, out-for-delivery, failed attempt, reject, delivered | order state, inventory, evidence, claim-window timestamp |
| Pickup | reserve, pay, ready, inspect, pass accepted, OTP success/failure, photo upload, handover, reject, expiry/cancel | reservation state, one-time OTP, inventory and fulfilment timestamp |
| Returns/disputes | open, late intake, review, approve/reject, arbitration, evidence, escalation, resolve/close | case state, ruling, authorized components, holds |
| Refund | authorize, approve/reject, dispatch, accepted, processing, success, failed, needs-attention, timeout, retry, reconciliation | refund operation, remaining ceilings, payment status, journal, vendor recovery |
| Cashback | accrue, reserve, consume, release, mature, expire, reverse, recovery-offset | liability and spendable lots, each once |
| Settlement | hold, eligibility, reserve earning, release hold, reversal, paid out | earning state, available balance and vendor payable |
| Payout | request, approve, reject, recipient validation, transfer create, provider success/failure, timeout, reconcile, retry | request, whole earning allocation, transfer reference, journal |
| Recovery | propose, authorize debt, repay, verify repayment, offset approve/apply, impair/write off | receivable balance, repayments, offset, expense |
| Security/admin | bank change/cooldown, role/permission change, manual override, admin correction, webhook replay | authorization, audit trail, cooldown, one-time tokens |
| Notifications | order/payment/refund/payout/return messages, queue retry | delivery dedupe where practical; must never trigger domain money mutation |
| Reconciliation/jobs | expire unpaid, mature cashback, settle earnings, retry provider query, repair stuck operations | repeat-safe state change and immutable reconciliation evidence |

## 13. Required acceptance tests before declaring this complete

- For every event, test valid source-to-target transition and each invalid source state.
- Deliver the same event twice sequentially and concurrently; assert exactly one state change, ledger/journal effect, stock effect, gateway operation and cashback mutation.
- Deliver success then late failure, failure then success, duplicate IDs with conflicting payload, and events out of order.
- Simulate timeout after provider accepted operation; assert reservation remains held and retry cannot create a second gateway operation.
- Simulate DB rollback after event receipt; assert retry processes safely, or outbox/inbox resumes without duplicate external effect.
- Race two checkouts against stock, two OTP submissions, two refund approvals, two payout requests, and two payout finalizations.
- Assert journal balance AND correct account classification; balanced entries alone are not sufficient.
- Assert parent/child/item/component ceilings under concurrent partial refunds.
- Assert all legacy endpoints, scheduled commands, admin actions and mobile APIs enforce the same transitions.
- Verify old safety tests retain equivalent coverage after logistics tests are removed/renamed.

## 14. Current priority order

1. **Critical:** map and enforce payment/refund/webhook idempotency and legal transitions at service/database boundaries.
2. **Critical:** implement parent checkout/child allocations before claiming multi-vendor checkout is supported.
3. **Critical:** prove all order status writers and handover paths use explicit transitions.
4. **High:** unify payout/earning transitions and transfer idempotency across web/API/admin/reconciliation.
5. **High:** formalize cashback and return case transitions; test scheduled jobs and concurrent retries.
6. **High:** implement verified vendor repayment, authorized offset and accountant-approved write-off.
7. **High:** restore equivalent coverage for deleted safety tests and add concurrency/replay tests.

No production merge or deployment is authorized by this register.

## Webhook inbox verification follow-up — 20 October 2026

- Confirmed the initial migration creates a database unique constraint on `processed_webhook_events.event_id`; the handler also stores a SHA-256 payload hash and rejects a reused event ID with a different payload.
- Fixed the stale-worker fencing warning so it correctly interpolates the event ID and logs the claimed attempt generation (commit recorded in Git history).
- **Still unverified:** existing tests cover HMAC rejection and payment settlement replay, but do not establish the inbox's own duplicate event delivery, concurrent claim, conflicting payload, failed/unmatched retry, stale worker fencing, or database-specific duplicate-key recovery behavior.
- The duplicate-key recovery currently catches a database `QueryException` inside the transaction callback. Verify on the production database engine whether a uniqueness exception leaves the transaction usable; add a supported-engine-safe strategy if not. Do not assume SQLite test success proves PostgreSQL/MySQL behavior.
- Required acceptance tests: identical completed event is a no-op; same ID/different signed payload is quarantined; concurrent same-ID deliveries cause exactly one financial effect; failed and unmatched retries retain the original identity/hash; stale workers cannot overwrite a newer attempt; all error paths leave an observable inbox status.


### Payout reversal evidence gate — VM-AUD-032 (20 October 2026)

`transfer.reversed` is a financial state-changing event, not merely a notification. It must resolve exactly one payout, match the persisted transfer reference/code, include an explicit amount represented as integer kobo and an explicit `NGN` currency, and match the payout amount exactly before any payout or journal mutation. Reject malformed amounts before integer conversion; never allow fractional values to be truncated into a match. The handler now applies the strict amount/currency requirement to reversal events and validates integer-kobo representation. A regression test now covers fractional-kobo amount rejection with no payout or journal mutation. **OPEN for verification:** tests must still cover missing amount/currency, wrong amount/currency, valid reversal, and late/duplicate reversal after terminal failure; confirm the provider's real reversal payload in sandbox before deployment.


### Shop creation → initial delivery coverage — VM-AUD-033 (20 October 2026)

On `Shop` creation, if a valid physical `lga_id` exists, create the default active coverage row for that LGA using the LGA's canonical `state_id`. Use `firstOrCreate` so the model hook and the vendor-controller initialization may safely coexist without duplicate coverage. `DatabaseSeeder` intentionally uses `WithoutModelEvents`, so `EcosystemDemoSeeder` must initialize coverage explicitly; model events alone are not a seeder guarantee. Subsequent vendor coverage replacement is an explicit authorized transition and is not implicitly overwritten by unrelated shop updates. Checkout remains fail-closed for destinations not covered. **Verification pending:** CI must demonstrate a newly seeded/model-created shop can check out for its own LGA and that explicitly removed coverage is still rejected.


### `transfer.reversed` requires confirmed disbursement — VM-AUD-034 (20 October 2026)

A signed and correctly identified reversal event must not be treated as a generic pre-dispatch transfer failure. Before mutating payout status, vendor allocations, logistics wallet balance or journals, the finalizer must find the original confirmed payout-disbursement journal (`EVT-VND-PAYOUT-{id}` or `EVT-FLEET-PAYOUT-{id}`). If absent, return a protected/manual-reconciliation result and leave all financial state unchanged. A regression now covers vendor payout quarantine with no disbursement journal; equivalent logistics coverage and CI verification remain open.


### Refund webhook identity must resolve exactly one operation — VM-AUD-035 (20 October 2026)

A `refund.processed` event must resolve to exactly one `customer_refund` financial operation across operation reference, persisted refund-reference metadata, and gateway-attempt references. Do not use `first()` to choose among multiple matches. Include confirmed operations in matching so duplicate success can be handled idempotently by the finalizer. Zero or multiple matches are quarantined as `unmatched` and must not mutate an operation, reservation, order summary, or journal. A regression with two operations sharing one gateway refund reference has been added; CI verification is pending.

### Verification update — payout reversal and refund webhook identity (20 October 2026)

Code commit `b7122f99fec327ca5f4ec5344d1c633d8ca69b08` passed the focused refund-safety workflow: **164 tests / 1,049 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39499688786). Verified in CI: missing/malformed/mismatched reversal amount or currency is rejected without financial mutation; vendor and logistics reversals lacking their original disbursement journal are quarantined without state/wallet/journal mutation; a refund reference matching multiple customer refund operations is quarantined rather than selecting an arbitrary operation. This does not replace staging verification against actual Paystack payloads or resolve the documented stale-worker race.

### Refund success/failure ordering — verified regression (20 October 2026)

A new signed `refund.failed` webhook test delivers a failure signal after its financial operation is already confirmed. CI proves the operation remains `confirmed`, journal count is unchanged, and the inbox event is acknowledged without downgrading the operation. Focused workflow passed on commit `658cd1096f0e9c4861bf8dd9e9e237e60189ab76`: **165 tests / 1,053 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39499839994). The inverse ordering (failed terminal operation followed by a genuine provider success) remains fail-closed for manual reconciliation, and end-to-end stale-worker fencing remains open.

### Positive payout reversal transition — verified in CI (20 October 2026)

The positive-path `transfer.reversed` regression now verifies the full transition when the payout identity, amount/currency evidence, confirmed disbursement journal and original request journal all exist: payout transitions to rejected/failed, only its allocated order returns to eligible, exactly two reversal journal entries are added, and the inbox event completes. CI passed on commit `70afe769b46fda8a57aab29cda3bf8600e3afbf3`: **166 tests / 1,059 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39500051174). This is not a substitute for a real Paystack sandbox event or production database concurrency tests.

### Refund terminal-event ordering — both directions tested (20 October 2026)

The focused suite now verifies both contradictory orderings: a late `refund.failed` after `confirmed` cannot downgrade the operation; a late `refund.processed` after terminal `failed` cannot reopen it and is quarantined for manual reconciliation. No extra journal entries are created in either case. CI passed on commit `afbfd0be526cc14a1050f196f72df89c0d6500e6`: **167 tests / 1,063 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39500196677). End-to-end stale-worker fencing remains open because a stale inbox worker can still reach domain handlers before the final compare-and-set update.

### VM-AUD-036 — refund confirmation must hold current inbox generation

For webhook-driven `refund.processed`, the inbox row is locked and its `processing` status plus exact `attempts` generation are checked inside the same database transaction that confirms the financial operation, commits its reservation, and posts its journal. A stale generation must not mutate financial state or overwrite the newer inbox status. The regression simulates generation 1 arriving after generation 2 has reclaimed the event and verifies zero financial mutation. CI passed on `94cb14368f142b66199b7868422b840d325f5802`: **168 tests / 1,069 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39500860286). This invariant is not yet implemented for `refund.failed`, `charge.success`, or payout transfer webhooks.

### VM-AUD-037 — refund failure reservation release fence

The `refund.failed` handler must lock the inbox event and verify `status=processing` and the claimed attempt generation inside the same transaction as changing the financial operation to failed, releasing reservations, updating gateway-attempt outcomes, and recalculating order summary. A stale generation returns without these mutations and must not overwrite the current inbox state. CI passed on `389ebac6f643dfce0ef4317eba91b3fef90ef8ef`: **168 tests / 1,069 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39501098385). Dedicated stale-generation regression for this failure path remains open, as do fencing for `charge.success` and payout transfer webhooks.

### VM-AUD-038 — charge.success order settlement generation fence

The webhook path into `OrderSettlementService::settlePaystackOrder` now locks the `ProcessedWebhookEvent` row and verifies that it is still `processing` at the exact attempt generation before payment/order settlement and journal mutations. A stale worker returns `STALE_WEBHOOK_CLAIM`; the controller responds with retryable 429 and does not overwrite the current inbox row. Regression verifies a reclaimed event cannot settle the order or add journal entries. CI passed on `d2a4657b790917556addc2acb6216330849286e8`: **169 tests / 1,076 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39501350769). Payout transfer webhooks and a dedicated stale `refund.failed` regression remain open.

### VM-AUD-039 — transfer webhook payout mutation generation fence

Vendor/logistics payout finalization and reversal transactions now lock the processed-webhook inbox row and validate its `processing` status and exact attempt generation before payout, wallet/allocation, or journal mutation. Stale attempts return a protected result and the controller responds with 429 without overwriting the current inbox status. A stale vendor `transfer.success` regression verifies payout state and journal count are unchanged. CI passed on `0cc2f34b9b977ca50eecc97e65ccdabb3f292c5e`: **170 tests / 1,083 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39501738865). Remaining validation includes stale `refund.failed`, logistics-specific transfer cases, and true concurrent reclaim tests against the production database engine.

### VM-AUD-040 — logistics reversal stale-generation transition

Regression verifies that a stale `transfer.reversed` attempt cannot change a logistics payout from approved/success, cannot credit the company wallet, cannot post journal entries, and cannot overwrite the inbox generation owned by a newer worker. CI passed on `90284b14489de17430dbe144a2373a9ddb143128`: **171 tests / 1,091 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39501881418). Logistics success and refund-failure stale-generation regressions remain outstanding.

### VM-AUD-041 — logistics success stale-generation transition

A stale `transfer.success` generation is now tested against logistics payout finalization. It cannot change payout status/gateway status, credit/debit the company wallet, post journal entries, or overwrite the newer inbox attempt. CI passed on `43c4f3cf5f43edc8c7fb770d8953de710da57593`: **172 tests / 1,099 assertions** (run https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39502030887). Dedicated stale `refund.failed` and production-engine concurrent reclaim verification remain open.
### VM-AUD-042 — payout eligibility and return-case terminal states

Vendor balance calculation and vendor payout request validation treat `resolved`, `refunded`, `replacement_completed`, `rejected`, and `closed` return cases as terminal/non-blocking for an otherwise eligible earning. The admin payout allocation query now uses that exact same set. A closed return case must not make an otherwise eligible whole earning record disappear between request and disbursement. Regression passed in CI: `f20026303486320b35c4f09cd78d73dc0ff6ab24`, **179 tests / 1,125 assertions** (https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39503186205).

### VM-AUD-043 — refund-failure inbox generation fence proof

A `refund.failed` handler may mark an operation failed, release its active reservation, update gateway attempts, and recompute the order summary only while holding the current inbox generation inside the same transaction. The regression simulates a generation change after operation lookup; generation 1 returns retryable HTTP 429 and must not mutate operation/reservation/journals or overwrite generation 2. CI passed on `f20026303486320b35c4f09cd78d73dc0ff6ab24`: **179 tests / 1,125 assertions**. Concurrent execution on PostgreSQL/production DB is still unverified.
### VM-AUD-044 — confirmed payout fallback allocation and journal rules

For a confirmed vendor transfer where pre-transfer allocation records are missing or incomplete, fallback allocation follows the same canonical FIFO (`created_at`, then `id`) and only allocates whole earning records that fit. It may allocate earlier whole records and leave a residual; it must never split the next record. Any residual is a treasury reconciliation discrepancy, not vendor debt and not a reason to pretend the confirmed transfer failed. If existing allocated rows exceed the transfer amount, do not mark those orders settled from the suspect rows; quarantine the confirmed cash amount in treasury suspense pending finance reconciliation. Legacy disbursement journals without a request-time payout liability journal must balance for exact, shortfall, and over-allocation cases; never debit vendor payable above the confirmed transfer amount. Regressions passed on `53b7081485f2cb60e4061c7fb41d577a363e38a3`: **181 tests / 1,139 assertions** (https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39504779791).
### VM-AUD-045 — payout reservation terminal-state rule

A payout reserves vendor earnings only while its gateway outcome remains unresolved. Because the existing successful terminal state is `status=approved, gateway_status=success`, active-reservation queries must not use `status=approved` alone. Shared `VendorPayoutRequest::activeReservation()` includes pending/approved/processing/in-flight/uncertain rows only when the gateway outcome is not terminal (`success`, `failed`, `reversed`). Use it for pending payout totals, withdrawable balance calculation, and the one-active-request guard. Regression proves a completed payout no longer reduces later eligible earnings or blocks a new request. CI passed on `56ea88c5ec69e04482fa6b342c98b13a6fd59d98`: **182 tests / 1,147 assertions** (https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39505063860).
### VM-AUD-046 — allocation ownership validation and quarantine

Every vendor payout allocation must reference an existing order owned by the same vendor as the payout. Remaining payable calculations ignore cross-vendor allocation rows. If finalization encounters missing/cross-vendor allocation ownership, it must not infer which vendor/order earned the transfer, allocate more orders, or mark an unrelated order settled. Record `ALLOCATION_INVARIANT_FAILED`, keep the payout reservation active despite gateway success, and journal legacy confirmed cash to treasury suspense pending reconciliation. A reversal with an ownership mismatch must be quarantined before mutating payout/allocation/order/wallet/journal state. Regression suite passed on `9202bdae2f071098b201cc710cae1a2e64a3ef63`: **184 tests / 1,166 assertions** (https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39505479844).
### VM-AUD-047 — payout allocation preflight before transfer dispatch

Before any Paystack transfer request, every pre-existing allocated row must reference an existing order owned by the payout vendor and carry a positive amount; the sum of active allocation rows must not exceed the payout amount. Any violation stops before gateway dispatch and preserves the pending payout, allocations, and journals for finance reconciliation. Finalization-time quarantine is not a substitute for pre-dispatch validation. Regression passed on `0bd7a224d8078dd40998b88d9bb4dbe4498440ae`: **185 tests / 1,174 assertions** (https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39505710379). Still audit per-order allocation caps against actual vendor payable, including other active payout allocations.
### VM-AUD-048 — existing allocation must equal one eligible whole earning

Before dispatching a payout, every existing allocation must represent exactly one complete earning record for that payout: existing row references an eligible delivered/paid order for the payout vendor; the row is positive; there is one allocation row for that order in this payout; the amount equals the order's full vendor payable (including vendor-retained delivery where applicable); no blocking return/dispute exists; and no other unresolved payout reserves the same order. Partial, duplicate, cross-vendor, over-cap, or no-longer-eligible allocation rows block transfer dispatch for finance reconciliation. CI passed on `726d72c57d53efe7bc5d1ae0d97226588a11df5d`: **186 tests / 1,181 assertions** (https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39505931861).

### VM-AUD-049 — handover readiness transition guard

**Transition rule:** The vendor API may move an order to \`ready_for_pickup\` only when the order is paid, has \`fulfillment_type=doorstep_delivery\`, and its current status is in the explicit pre-dispatch allowlist. Unpaid, in-shop pickup, delivered, cancelled, returned, and other unlisted states must be rejected with no order-state or handover-timestamp mutation. The API delegates to this same row-locked service as the web endpoints; this also prevents API-only bypass of shared OTP and notification behavior. Regression tests cover unpaid doorstep and paid in-shop pickup rejection. The test-only run passed; CI for the final shared-service/API changes remains pending.


### VM-AUD-050 — rider pickup payment precondition

verifyRiderPickupOtp may transition an order to out_for_delivery only if payment_status=paid, the fulfilment type is doorstep delivery, the source status is allowed, the active vendor-owned rider is assigned to the order, and the one-time vendor pickup OTP matches. The payment precondition is checked inside the row-locked service before any pickup state/timestamp mutation. An unpaid order with a legacy ready_for_pickup status must remain unchanged. Implementation and regression test are committed; CI verification pending. Do not treat this finding as proof that all order state writers have been inventoried or unified.


### VM-AUD-051 — delivery completion payment precondition

Doorstep delivery completion must require payment_status=paid inside the row-locked OrderSettlementService before validating the customer OTP or changing delivery/cashback state. A correct OTP is evidence of receipt, not evidence of payment. If payment is pending/unpaid, reject without changing order status, receipt timestamp, customer confirmation, cashback ledger or settlement state. Implementation and regression test are committed; CI verification pending. Mandatory photo evidence and all other direct order-status writers remain under audit.


### VM-AUD-052 — partial doorstep handover preconditions

DoorstepHandshakeService::processDoorstepHandover is a separate writer of delivered/returned state and item-level financial outcomes. It may proceed only for doorstep fulfilment, payment_status=paid, and order_status=out_for_delivery, after rider/vendor assignment and OTP/manifest checks. Reject unpaid or wrong-source-state orders before changing order items, creating return cases/refund reservations, changing order status, or enqueuing cashback. Regression test committed; CI verification pending. Mandatory photo/custody preconditions and all other writers still require full inventory.


### VM-AUD-053 — mandatory doorstep evidence at service boundary

DoorstepHandshakeService::processDoorstepHandover must reject an absent/blank doorstep proof path, even when invoked without the HTTP controller. API validation alone is not authoritative for direct callers. This guard runs before OTP and any handover side effects; regression coverage asserts no order completion or ReturnCase mutation. CI verification pending.


### VM-AUD-054 — failed delivery attempt transition contract

The delivery-attempt-failed API is an authoritative order writer and must use a row lock/transaction. Allowed only when the assigned rider owns the order, payment_status=paid, fulfillment_type=doorstep_delivery, and order_status=out_for_delivery. Attempt counter, evidence, audit log, optional ReturnCase and returned transition are committed atomically; repeated concurrent requests cannot race the attempt threshold. Regression test covers unpaid order rejection with no state/ReturnCase mutation. CI verification pending.


### VM-AUD-055 — customer dispute idempotency and serialization

A customer dispute may be filed only against an owned delivered order within the active 24-hour claim window, unless admin/legal workflow explicitly opens a late case. Serialize createDispute using a row lock on the order; re-check source status/window and existing active dispute while locked; create one dispute and freeze vendor settlement in the same transaction. Duplicate submissions must not create duplicate dispute records or race settlement state. Regression asserts exactly one dispute after duplicate submission. CI verification pending.


### VM-AUD-056 — all-rejected doorstep handover must not settle delivery fee

Transition: `out_for_delivery` -> `returned` only after verified customer handover OTP, complete unique manifest, mandatory photo proof and authorized return case. If accepted merchandise total is zero, no vendor/fleet delivery payable, no platform delivery revenue and no escrow-release debit may be posted by the handover event. Delivery-fee outcome remains held for separate persisted admin remedy/refund authorization; customer refund rights are not decided by this transition. If at least one item is accepted, delivery fee follows the normal allocation path. Regression checks assert the all-rejected event creates none of the three delivery-fee/escrow journal events. CI verification pending.


### VM-AUD-057 — doorstep delivery confirmation evidence gate

No event may transition `out_for_delivery` -> `delivered`, set customer-confirmed receipt, start the 24-hour claim window, or enqueue cashback solely from a valid customer OTP. The shared settlement service requires a non-empty server-stored proof image path after OTP validation and before any terminal transition. Every exposed API/web confirmation route must validate an uploaded image (image, max 10 MB), store it server-side, and pass the resulting path into the shared service. Missing proof returns failure with no delivery status, received timestamp, cashback, or settlement side effects. Rider/customer entry paths must be tested independently. CI verification pending.


VM-AUD-057 verification update (20 October 2026): the full financial/payment hardening suite passed on implementation plus regression commit `7fbde23bde35ae405f51d5ada5f88fda5e55053b` ([CI run](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39511157789)). The first CI attempt correctly exposed two existing direct-service tests that still called successful handover without evidence; both fixtures were updated to supply the proof path, and the subsequent full suite passed. A follow-up wording-only API response correction is in commit `6523ed4bc18863b6deb678d0958794295d8ba063`; its CI run is pending, so the latest branch head is not yet fully verified.


### VM-AUD-058 — abandoned-order expiry transition must revalidate under lock

Allowed transition: `pending` + `payment_status=pending` + non-null `expires_at <= now` -> `cancelled`, and only if all conditions remain true after locking the order row. The scheduler's initial candidate query is not authoritative after time passes or concurrent updates occur. Under lock, re-check payment state, order state, non-null expiry, and `expires_at <= current time` before cancellation, reservation cancellation, or stock restoration. A renewed/unexpired order must have no transition or inventory side effects. Regression simulates expiry extension after candidate selection. CI verification pending.


VM-AUD-058 verification update (20 October 2026): the service implementation CI passed ([run](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39511432954)), the stale-expiry regression CI passed ([run](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39511441860)), and the full financial/payment hardening suite passed ([run](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39511448338)). The latest documentation-only validation runs are still in progress at the time of this update.


### VM-AUD-060 — administrative return rejection transition

Allowed: `requested | under_review | received_at_hub | inspected | returned_to_vendor -> rejected`, only by authorized admin staff, with a reason, under a row lock and transaction. Forbidden: reject from `refund_pending`, any state with a dispatched/confirmed refund, `rejected`, or other terminal/advanced state. The case state, item state, and audit event must commit atomically; no controller may mutate ReturnCase status directly. Regression: VM-AUD-060.


### VM-AUD-061 — pickup reservation cancellation

Allowed: eligible unplaced reservation -> `canceled`, only after locking and rechecking the reservation row in a transaction shared with the payment path. Forbidden from `order_placed`, `payment_completed`, `canceled`, `cancelled`, or `expired`. The same locked row must serialize cancellation against order creation/payment; no read-then-write cancellation route is permitted. Regression: VM-AUD-061.


### VM-AUD-060/061 verification update (21 October 2026)

CI for the guarded admin return-rejection transition and serialized pickup-reservation cancellation has passed, including regression coverage and latest branch-head validation ([run 39512965854](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39512965854)). This does not certify the entire state-transition inventory; PostgreSQL concurrent execution, Paystack sandbox payload compatibility, and remaining direct-writer inventory are still open.


### VM-AUD-062 — pickup inspection transition serialization

Allowed: `reserved` and not expired -> `inspected_accepted`, or `reserved` and expired -> `expired`, after reloading the reservation under a row lock and rechecking staff/vendor/branch authorization. Forbidden: inspection from `order_placed`, `payment_completed`, `canceled`, `cancelled`, `expired`, `handed_over`, or any other non-`reserved` state. Concurrent cancellation/order creation must serialize against this same reservation row. Regression: VM-AUD-062.
