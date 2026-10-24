# Victorious MARKET — Manual Bank Transfer and Proactive Supplier Payment Policy

**Decision date:** 24 October 2026  
**Status:** Owner-approved operating direction; implementation must be audited and tested before launch.  
**Scope:** Customer payment collection, admin payment verification, supplier payables and outgoing supplier bank transfers for the Uyo-only centrally managed retailer model.  
**Authority:** This policy is a binding addendum to [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md). It supersedes conflicting Paystack-only customer collection and Paystack-settlement prerequisites for orders created in manual-transfer mode. It does not authorize mixing payment rails on one order.

## 1. Operating principle

Victorious MARKET runs the money flow as a centrally managed retailer. Customers pay Victorious MARKET; an authorized admin verifies incoming payments; Victorious MARKET sources and fulfils the order; and Victorious MARKET proactively pays eligible suppliers. Supplier identities and source prices remain private. Suppliers do not operate customer-facing storefronts and do not need to request payout for earned, eligible payables to enter the admin queue.

## 2. Coexisting customer collection modes with per-order isolation

- Paystack and direct Manual Bank Transfer may both be enabled and offered for new orders at the same time. If neither is enabled, checkout is disabled. There is no global mutual-exclusion requirement.
- At checkout, the order/payment intent is immutably bound to exactly one selected method. That order uses only the selected method's verification and refund path. No mixed payment, cross-method evidence, top-up or silent switching is allowed.
- A pending, unknown, disputed or potentially late payment must be reconciled before an explicitly audited reset. A single order may have only one accepted payment; a Paystack transaction or incoming bank transaction may be applied to at most one order. Enforce uniqueness, exact amount/currency/order binding, atomic transitions, idempotency and concurrency safety.
- When Manual Bank Transfer is selected, the customer must submit evidence and an authorized admin must independently verify actual bank credit. When Paystack is selected, use trusted Paystack verification/webhook controls. Neither path may impersonate the other.
- Supplier and logistics-company payments are outgoing manual bank transfers through the business bank; the website records and reconciles them and must not call Paystack's transfer API.

## 3. Customer manual-transfer workflow

1. Customer registers/logs in, checks out and receives an order-specific payment reference plus the approved Victorious MARKET receiving-account details.
2. Customer transfers the exact payable amount and includes the order reference in the narration where the bank permits it.
3. Customer uploads a screenshot/receipt through an authenticated, private upload flow. The order remains **awaiting verification**. The screenshot is an untrusted claim, not proof of credit.
4. An authorized admin reviews the submission against the actual receiving-account bank transaction, bank statement or other trusted bank evidence. The admin checks the order reference, amount, NGN currency, receiving account, bank transaction reference, date/time and available sender details.
5. Admin approves only when the actual credit is confirmed and all values match the order. Otherwise the admin rejects the submission with a reason or holds it for investigation. A rejected screenshot must not change payment status, create supplier assignments or trigger fulfilment.
6. Approval records the admin, timestamp, reason, transaction identifier/reference, amount/currency, evidence pointer/hash and order/payment IDs. Evidence and decisions are append-only auditable records.

## 4. Anti-duplication and exception handling

- Enforce a database uniqueness constraint for a bank transaction identifier/reference used to settle an order, and enforce one accepted successful payment per order. Use a transaction and row locks/unique constraints so two admins approving concurrently cannot double-settle or allocate twice.
- The same screenshot, bank reference or transaction cannot be attached to multiple accepted orders. Duplicate submissions are recorded and flagged, not silently accepted.
- Screenshot alone, customer-supplied transaction ID, sender email, OCR, browser state, callback or uploaded statement image is never sufficient. A real bank credit must be verified.
- Wrong amount, missing/ambiguous narration, underpayment, overpayment, duplicate transfer, reversed credit, unmatched credit or conflicting evidence enters an admin exception queue. Do not auto-allocate suppliers or issue refunds. Preserve the money trail and let an authorized admin decide how to resolve the exception.
- Do not automatically retry a transfer or treat a timeout as failure/success without bank reconciliation. Keep order status and money status distinct until evidence resolves the outcome.
- Daily bank reconciliation must identify unmatched credits, accepted payment records without matching credits, duplicate transaction references, reversals and amount/currency mismatches.

## 5. Supplier sourcing gate

No binding supplier availability request, order assignment, supplier payable or fulfilment handoff occurs until the manual payment has been approved against verified actual bank credit and the accepted payment has committed to the order. Once paid, the existing supplier catalog, exact-order-quantity confirmation, supplier eligibility, failover, cooldown, Uyo-only and one-rider rules continue to apply. Payment rejection or an unresolved bank-credit exception does not trigger sourcing.

## 6. Proactive supplier payable and payment workflow

- The system calculates supplier payables from immutable order-item wholesale-cost snapshots and maintains an admin-facing **Eligible Supplier Payables** queue. The queue is derived from verified fulfilment and financial records; it is not triggered by a supplier asking for money.
- A supplier payable becomes eligible only after verified delivery, expiry of the applicable 24-hour return/dispute window, no unresolved dispute/return/recovery hold, and successful order-level financial reconciliation, except where an authorized completion exception is documented and reconciled.
- The Super Admin reviews eligible items, selects the specific payable items to pay (non-FIFO; no automatic ranking), confirms the supplier's verified bank beneficiary and amount, and manually initiates the bank transfer using Victorious MARKET's normal bank channel.
- Supplier settlement minimum is ₦1 and maximum is ₦1,000,000 per individual settlement transaction/request. Pay only eligible payable items explicitly selected and authorized by the Super Admin, subject to verified available reconciled funds, beneficiary verification, existing holds/debts, and ledger integrity. Do not silently omit eligible amounts, use FIFO/oldest-first selection, or silently split a payout to satisfy the cap. If the proposed amount exceeds ₦1,000,000, the admin must explicitly revise the selected amount; the system must not split it automatically.
- The supplier does not have to request payment. Supplier-facing statements/status can show accrued, held, eligible and paid balances, but must not expose another supplier's data or permit a supplier to bypass admin approval.
- Before transfer, recheck supplier identity/KYC, active status, verified bank details, recovery debts/offsets, selected items, previous reservations, available reconciled corporate funds and exact eligible payable totals. Bank-detail changes require the existing verification and cooldown controls.
- Record the human initiating admin, beneficiary snapshot, amount, selected payable IDs, timestamp, bank transaction reference and supporting evidence. Mark paid only after checking the actual bank debit and beneficiary transfer outcome. If outcome is uncertain, hold for reconciliation; never blindly retry.
- The initiating authorized admin may complete reconciliation of the outgoing supplier settlement; a second administrator is not mandatory. This does not weaken the required audit trail, duplicate-reference safeguards, beneficiary validation, ledger balancing or actual bank-outcome verification. The admin must attach outgoing-transfer evidence to the settlement; the uploaded image alone is not proof that the transfer completed.
- Every transfer requires explicit human Super Admin authorization. The system may calculate, queue, validate, remind and reconcile; it may not autonomously dispatch funds.
- Payments are manually initiated Monday through Friday after reconciliation of the actual corporate bank balance/statement and the relevant receipts and payables. Paystack settlement is not a prerequisite when the customer collection mode is manual bank transfer.

## 7. Refunds, cashback and accounting

- The Zero-Automated-Outflow Law remains binding. All refunds require manual Super Admin authorization and dispatch. A screenshot or payment approval alone does not authorize a refund.
- Keep customer receipts, supplier payables, logistics-company payables, retailer margin, delivery fees, bulky surcharges, refunds, cashback and recovery debt in distinct auditable ledgers. Never treat an uploaded receipt as cash or a receivable as settled cash.
- The existing 5% cashback benefit applies only to eligible merchandise actually funded by verified and reconciled money through the order's pinned collection mode, subject to the existing caps, maturity and reversal rules. Do not credit cashback for an unverified or rejected transfer.
- Supplier payment records and the order's accepted incoming payment record are separate ledger events. A supplier transfer does not alter or replace the customer's accepted payment.

## 8. Required implementation and acceptance evidence

Before enabling this mode in production, verify all active checkout, payment, webhook, admin, supplier sourcing, refund, payout and reconciliation paths. Required tests include:

- mode exclusivity, immutable per-order rail pinning, and no cross-rail settlement;
- screenshot upload is private and never auto-approves;
- correct credit approval and incorrect/mismatched/rejected submission;
- one bank transaction cannot settle two orders, and one order cannot accept two payments;
- simultaneous admin approvals, duplicate upload/replay, application crash/restart and concurrent requests;
- payment approval is the only gate that allows supplier sourcing;
- unmatched/underpaid/overpaid/reversed bank credit and bank statement reconciliation;
- proactive payable queue, no supplier request prerequisite, exact eligible payable amount, non-FIFO explicit selection, duplicate-transfer prevention, uncertain outcome hold and verified bank debit before paid status;
- no Paystack API call for manual-mode customer orders or manual supplier payouts;
- migration compatibility on SQLite and PostgreSQL, including rollback and unique-index behavior.

Documentation changes alone do not certify implementation. Do not enable manual mode until the code and tests prove these invariants on the target commit.


## Owner-confirmed superseding clarification — 24 October 2026

This clarification supersedes conflicting earlier payment-provider and settlement wording in this document.

- Paystack and direct Manual Bank Transfer may both be enabled and offered to customers at once. There is no global mutual-exclusion requirement. Each order is bound to exactly one selected method; verification remains method-specific, with no cross-method references or switching after an attempt begins. If neither method is enabled, checkout is disabled.
- A single order may have only one accepted payment, and each provider/bank transaction may be applied to at most one order. Require exact amount/currency/order binding, unique transaction references, atomic transitions, idempotency and concurrency-safe verification. A customer screenshot is not proof of incoming credit.
- During an open manual-receipt review, an authorized admin may request replacement evidence while retaining all versions and audit history. Final rejection prevents ordinary resubmission or a second payment on that order. Any claim of actual credit after rejection requires a separate support investigation and audited reconciliation, not silent order reopening.
- Both supplier and logistics-company settlements are manual bank transfers performed through the business bank and recorded/reconciled in the system. Each individual request/transfer has a ₦1 minimum and ₦1,000,000 maximum; no FIFO/oldest-first requirement and no silent splitting. The initiating authorized admin may reconcile their own transfer with full audit and duplicate safeguards; a second admin is not mandatory. Admin-uploaded outgoing-transfer evidence applies to both payee types and does not alone prove completion.
- Both settlement types require an eligible payable and cleared/reconciled funds, follow the Monday–Friday operating schedule, and must not be marked settled while pending, uncertain, failed or reversed. Reconcile before retry. No automated outflow. Keep supplier and logistics payables distinct and ledger-balanced; legacy vendor-wallet payout rules do not apply to retailer-model payables.
- Manual bank-transfer refunds require a separately authorized manual process; never misapply Paystack refund APIs or mark refunds complete before the outgoing bank outcome is verified. Paystack-funded cashback remains limited to eligible Paystack-funded purchases unless the owner separately amends that rule.
- Documentation does not certify implementation; code, permissions, database constraints and acceptance tests require separate verification.
