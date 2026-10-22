> **Historical audit record:** Findings and proposed workflows below describe what was observed or planned at the time. They are not proof of current policy or implementation. Current policy is governed by [Active Business Rules](https://github.com/hotvictorious-rgb/newVmarket/blob/main/docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md). Do not implement legacy Plaza-only sourcing, pre-payment binding allocation, public pickup stock holds, automatic refund solely due to allocation failure, or the retired 90% logistics-payable formula.

# Victorious MARKET — Real-World Scenario Hardening Plan


## Active retailer-policy acceptance scenarios (aligned 22 October 2026)

**Verification status:** These are required acceptance criteria, not evidence that tests have run or passed. The active policy source is `docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md`.

1. **Listing lifecycle:** A product is purchasable only while at least one eligible approved supplier offer is explicitly in stock with sufficient quantity. When none remain, hide it. A manually delisted listing must not auto-relist until an authorized admin permits it. Preserve offer and order history.
2. **Payment gate:** Pre-payment checks may quote and assess feasibility but must not bind a supplier, hold stock, notify a supplier of a binding order or create a supplier payable. Duplicate, invalid, wrong-amount, wrong-currency or unverified payment events must not trigger allocation.
3. **Whole-order supplier optimization:** After verified payment, revalidate offers and compare feasible plans by fewest distinct pickup locations first, then lowest total wholesale cost, reliability and deterministic tie-break. A cheaper plan using more pickup locations must not defeat a more consolidated feasible plan.
4. **Flat customer delivery charge:** The customer delivery fee is one configured flat base fee per order, independent of corridor, plus configured bulky-unit surcharges. Changing the destination corridor must not silently change the customer base fee.
5. **Configured logistics-company payable:** Resolve the assigned approved logistics company and destination corridor, snapshot that company's configured base payable and configured bulky-unit surcharge, and calculate the company payable independently of the customer delivery charge. Do not assume a fixed percentage or default 10% margin.
6. **Bulky isolation and accountability:** If any item is bulky, the entire order uses an exclusive Keke/tricycle trip and cannot be batched with another customer. The complete order remains assigned to one rider from one approved logistics company even if multiple supplier pickup locations are involved.
7. **Payable exceeds delivery revenue:** If configured company payable exceeds customer delivery revenue collected, record the difference as an explicit VM-funded subsidy/expense. Preserve balanced journals and the immutable tariff snapshot.
8. **Tariff changes after assignment:** Changing the admin-configured corridor/company tariff must not rewrite the payable snapshot for an already-assigned order. New assignments use the new active tariff/version.
9. **Authorized override:** Only an authorized admin may override a payable or supplier assignment, with required reason and immutable audit record. Reject unauthorized, malformed, duplicate, stale-state or post-finalization changes without duplicate financial effects; never bypass payment verification or hard eligibility rules.
10. **Allocation failure:** After verified payment, no eligible supplier must place the order in an admin-managed exception state, not trigger an automatic refund solely for allocation failure. Reassignment, changed terms, cancellation and refund must be authorized and audited; obtain customer consent for changed product/price/terms. Refund completion requires provider confirmation.
11. **Supplier payable eligibility:** Supplier acceptance, allocation and pickup alone do not make a payable due. Eligibility requires verified delivery, or an explicitly authorized completion exception, followed by reconciliation and required evidence.
12. **Supplier governance and disbursement:** Only an authorized admin can register, activate or suspend suppliers; suspension blocks new allocations and requires explicit review of existing paid orders. Each supplier-payment request must be ₦5,000–₦1,000,000 at a time; no automatic splitting above the cap. FIFO/oldest-first payable selection is prohibited, and the selected payable records and approving admin must be auditable.
13. **Cashback basis and non-compounding:** Given eligible post-discount merchandise of ₦10,000, cashback redeemed of ₦2,000, and delivery of ₦1,000, cashback earned is 5% × ₦8,000 = ₦400 after verified successful fulfilment. Delivery and cashback-funded amounts do not earn cashback; failed/unfulfilled/cancelled orders do not accrue a cashback lot.
14. **Maturity, validity and refund:** Cashback is unavailable until verified fulfilment + 24-hour claim window + separate 24-hour buffer, with no blocking case; its 365-day validity begins at maturity. Refund reversals are idempotent; spent cashback recovery is separate and must not silently debit a cash wallet.
15. **Idempotency/concurrency:** Duplicate webhook delivery, retries, concurrent admin overrides or repeated payout attempts must not duplicate orders, stock movements, cashback lots, payable adjustments, journals, refunds or company transfers.

Historical audit metadata: the original audit work referenced branch `audit/paystack-refund-webhook-safety` on 21 October 2026. That is historical context, not proof that the branch currently exists or that its code is present on `main`. Automated tests and current CI/runtime verification remain required.

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
- **VM-AUD-064 partial implementation:** `createIntent()` now supports `Idempotency-Key`: same key + same normalized request replays the original intent; same key + changed request returns 409; a DB unique constraint arbitrates concurrent insert races. Regression added for replay and payload mismatch. The financial/payment hardening proof tests passed for order-execution idempotency on commit `b2548cdf8916176354bde09e7192bf4ad2f82430` ([run 39516844005](https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39516844005)). The `/api/v1/customer/orders/checkout` idempotency contract is now also documented in OpenAPI on commit `d72ce63e86c2c2510da49a8b97d862b4837e62d4`; its documentation CI is running. Because the key remains optional and no active client caller was found in this repository search, S01 remains open until client key generation/integration and universal enforcement are confirmed.

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

### S13 — Legacy vendor payout timeout, duplicate click and late bank confirmation (historical compatibility)
- Scope: this scenario covers only pre-existing legacy vendor-earning/payout records. It must not be used as the acceptance rule for new retailer-model supplier payables.
- Arrange: for a historical vendor payout, the vendor clicks twice; the provider times out; later reconciliation confirms transfer; also test a true failure.
- Expected (legacy records only): one active reservation of legacy earning records; timeout does not release the reservation; apply the historical whole-record/FIFO rules only to that legacy record set; completed transfer is not reversed without a verified reversal event.
- New supplier-payable acceptance rule: request/disbursement is ₦5,000–₦1,000,000; FIFO/oldest-first selection is prohibited; selection is authorized and auditable but has no owner-approved automatic ranking; do not silently split an over-cap request.
- Verify separately: legacy payout state/allocations and, for new supplier payables, selected payable IDs, approving admin, amount limits, idempotency, provider confirmation and balanced ledger.

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
\n\n### Newly discovered VM-AUD-065 — duplicate order execution\n- Arrange: send the same authenticated checkout request twice (and concurrently) with the same logical attempt.\n- Expected: one order and one Paystack initialization; subsequent calls replay that order result. Uncertain provider initialization must be reconciled, not blindly reinitialized.\n- Verify: order count, cashback reservations, inventory reservations, payment references, Paystack initialization count and journal entries.\n