# Victorious MARKET — Real-World Scenario Hardening Plan

Date started: 21 October 2026
Branch: `audit/paystack-refund-webhook-safety`
Scope: test the actual application services, controllers, database constraints, queues, webhook handlers, and financial ledgers. Scenarios must run in automated tests wherever feasible; manual tabletop review is supplemental, not a substitute.

## Non-negotiable pass conditions

Every scenario must verify:
1. **State legality:** only an explicitly allowed source-state -> destination-state transition occurs.
2. **Retry safety:** repeated requests, duplicate webhooks, queue retries, and client timeouts do not duplicate orders, refunds, ledger entries, cashback, stock movements, or payouts.
3. **Atomicity:** related order, reservation, payment, fulfillment, refund and journal changes commit together or roll back together.
4. **Money conservation:** captured money is accounted for; journal debits equal credits; component/parent refund caps are never exceeded; no liability is silently discarded.
5. **Authorization:** customer, vendor, vendor employee, rider and admin cannot act outside their ownership, role, branch or assigned job.
6. **Evidence:** required OTPs, uploaded proof, provider verification and human approvals are verified server-side—not trusted from client claims.
7. **Auditability:** rejected transitions and accepted financial actions leave accurate, non-duplicated audit/idempotency records without leaking OTPs or secrets.
8. **Race safety:** concurrent operations serialize or fail safely. Run database race tests against PostgreSQL before launch; SQLite/unit tests alone do not prove row-lock correctness.
9. **Recovery:** timeout or ambiguous provider response stays in an uncertain/reconciliation state; it is never assumed to mean success or failure.
10. **Customer rights:** operational timers never silently remove statutory rights or delay an approved customer refund while vendor recovery is pending.

## Scenario catalogue and expected outcomes

### S01 — Customer taps Pay twice on a weak network
- Arrange: one checkout; client sends the same request twice, then retries after a timeout.
- Expected: one intended order/payment attempt or a clearly linked idempotent result; no duplicate stock reservation, cashback reservation, or journal entries.
- Verify: order count, provider reference uniqueness, stock state, cashback and journal totals.
- **Implemented partial regression:** `test_duplicate_charge_success_with_distinct_event_ids_does_not_duplicate_financial_effects` simulates duplicate successful capture webhooks with different event IDs and checks paid/confirmed state, journal count, cashback ledger count and inbox completion. CI passed: [run 39515053888](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39515053888). This is not a full client-side double-checkout/idempotency test.
- **Open finding VM-AUD-064:** `createIntent()` always inserts a new intent and fingerprints include the current timestamp. Implement client idempotency-key persistence/uniqueness and regression for same-key retries plus conflicting payloads before treating S01 as resolved.

### S02 — Paystack succeeds but the browser closes
- Arrange: provider reports a successful verified payment; browser never receives the redirect.
- Expected: server webhook/reconciliation settles the order once; customer refresh does not create a second order or payment.
- Verify: provider transaction ID, payment status, order status, ledger count and repeat-webhook result.

### S03 — Duplicate, delayed and out-of-order Paystack events
- Arrange: deliver the same success event repeatedly; deliver a stale failure after success; deliver an old refund event after a newer refund attempt.
- Expected: duplicate events are idempotent; stale events cannot regress a newer verified state; conflicting events trigger reconciliation rather than guessing.
- Verify: event identity/generation, payment/refund state, audit log and journal uniqueness.

### S04 — Payment arrives after the order expires
- Arrange: expiry worker selects a pending order; payment succeeds just before/after cancellation; vary stock availability.
- Expected: locks and rechecks determine one consistent outcome. Reinstate only when allowed and stock can be safely reserved; otherwise initiate the approved refund/reconciliation path. Never both reinstate and refund the same capture.
- Verify: order, inventory, reservation, capture, refund and journal state.

### S05 — Customer cancels while payment/order creation is running
- Arrange: issue cancellation concurrently with order placement/payment settlement.
- Expected: row-lock/state-machine winner is authoritative; no paid order with a canceled reservation, and no canceled order with unaccounted successful payment.
- Verify: both final states and all side effects.

### S06 — Vendor inspection races cancellation or expiry
- Arrange: vendor employee approves inspection while customer cancels or expiry job runs.
- Expected: only one valid transition wins under a shared lock; non-reserved/expired reservations cannot be resurrected by stale models.
- Verify: reservation state, inspector identity, timestamps and audit trail.

### S07 — Rider marks delivered without customer proof
- Arrange: rider attempts confirmation with missing, invalid, reused or mismatched OTP/photo; attempt alternate web/API routes.
- Expected: no delivered state, claim-window start, cashback maturity, vendor settlement eligibility or delivery-fee release without the required verified evidence.
- Verify: all endpoints and all financial side effects, not just HTTP response.

### S08 — Partial doorstep acceptance and delivery-fee allocation
- Arrange: customer accepts one item and rejects another; then reject all items; repeat confirmation.
- Expected: item-level decisions are preserved; delivery fee follows the approved rule and is not released when no merchandise is accepted; no duplicate disputes or allocations.
- Verify: item states, dispute count, delivery fee journals, escrow and refund ceilings.

### S09 — Return request at the 24-hour boundary
- Arrange: submit immediately before, exactly at and after the operational window; include a manually reviewed late complaint and a statutory-rights case.
- Expected: boundary uses a documented timezone and comparison rule; manual review remains possible; operational deadline does not override applicable consumer law.
- Verify: timestamps, decision audit and remedy path.

### S10 — Refund approved while vendor has already been paid
- Arrange: valid refund is approved after vendor settlement; vendor has insufficient funds.
- Expected: platform funds the approved customer refund through supported original route; recoverable vendor debt is recorded only with authorized evidence/contract basis; customer refund does not wait for recovery.
- Verify: cash movement, vendor receivable, refund journal, debt evidence and balanced debits/credits.

### S11 — Duplicate or competing refund attempts
- Arrange: two admins or worker retries attempt refund for the same item/component; provider times out after accepting one request.
- Expected: persisted human approval precedes dispatch where required; one active operation/idempotency key; timeout remains uncertain until provider reconciliation; parent/component refund caps cannot be exceeded.
- Verify: gateway calls, refund records, approval identity, amount conservation and reconciliation.

### S12 — Cashback races with a claim or refund
- Arrange: maturity worker runs concurrently with a claim, refund, reversal or redemption; replay each job.
- Expected: blocked cashback does not mature/spend; spent cashback recovery is separately recorded; no silent cash-wallet debit or duplicate ledger entry.
- Verify: maturity time, liability ledger, redemption cap and reversal links.

### S13 — Payout timeout, duplicate click and late bank confirmation
- Arrange: vendor clicks twice; provider times out; later reconciliation confirms transfer; also test a true failure.
- Expected: one active reservation of earning records; timeout does not release the reservation; FIFO whole-earning allocation and caps remain intact; completed transfer is not reversed.
- Verify: payout state, earning allocation, bank/provider reference, fees and ledger balance.

### S14 — Vendor repayment and refund recovery
- Arrange: vendor disputes debt; sends screenshot only; later verified bank repayment arrives twice; admin proposes offset/write-off.
- Expected: screenshot alone is not repayment proof; verified repayment is idempotent; offsets require authorized basis; write-off is explicit and accounted as expense, never cash received.
- Verify: receivable, repayment ledger, approval, write-off and no double recovery.

### S15 — Wrong customer, vendor, branch or rider attempts an action
- Arrange: reuse valid-looking order/reservation IDs with another account; cross vendor/branch; inactive staff; unassigned rider.
- Expected: deny access without leaking OTP, address, payment data or evidence; no state/financial side effects.
- Verify: API and web route coverage plus audit/security logs.

### S16 — Concurrent inventory and multi-vendor settlement
- Arrange: two customers buy the last item simultaneously; then simulate parent/child totals, fees and refunds that differ by one kobo.
- Expected: inventory cannot oversell under the actual production database; child totals reconcile exactly to parent; each vendor's fulfillment, fee and refund remains isolated.
- Verify: PostgreSQL race run, uniqueness/constraints, decimal/kobo conservation and parent/child refund ceilings. Multi-vendor checkout must remain disabled until these invariants pass.

### S17 — Queue outage, retry storm and partial database failure
- Arrange: fail a job before commit, after provider response, and during retry; kill worker and replay.
- Expected: transaction boundaries and idempotency prevent duplicate side effects; outbox/reconciliation repairs ambiguous outcomes; no event silently disappears.
- Verify: queue attempts, operation IDs, database rollback, provider state and audit trail.

## Execution protocol

For each scenario:
1. Link the scenario to a specific event and state-transition table.
2. Create deterministic feature tests with explicit setup, action, and assertions on both success and forbidden transitions.
3. Include retry/duplicate and authorization variants.
4. Where concurrency matters, run independent transactions/processes against PostgreSQL and assert final invariants.
5. Run focused tests, then the full financial/payment suite.
6. Record finding ID, test name, commit, CI run, result, unresolved limitations and owner decision in the audit tracker.
7. Do not call a scenario resolved just because a test passes: confirm the test reaches the real service/controller path and checks financial side effects.
8. No merge/deployment or live-provider money movement as part of this audit.

## Initial priority

P0: S01–S07, S10–S13, S16 (money, state integrity, proof and race conditions).
P1: S08–S09, S14–S15, S17.
Provider sandbox tests and PostgreSQL concurrency are separate required gates; mocks and SQLite are not substitutes.
