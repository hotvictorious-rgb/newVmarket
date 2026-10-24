# Victorious MARKET — Manual Bank Transfer and Proactive Supplier Payment Policy

**Decision date:** 24 October 2026  
**Status:** Owner-approved operating direction; implementation must be audited and tested before launch.  
**Scope:** Customer payment collection, admin payment verification, supplier payables and outgoing supplier bank transfers for the Uyo-only centrally managed retailer model.  
**Authority:** This policy is a binding addendum to [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md). It supersedes conflicting Paystack-only customer collection and Paystack-settlement prerequisites for orders created in manual-transfer mode. It does not authorize mixing payment rails on one order.

## 1. Operating principle

Victorious MARKET runs the money flow as a centrally managed retailer. Customers pay Victorious MARKET; an authorized admin verifies incoming payments; Victorious MARKET sources and fulfils the order; and Victorious MARKET proactively pays eligible suppliers. Supplier identities and source prices remain private. Suppliers do not operate customer-facing storefronts and do not need to request payout for earned, eligible payables to enter the admin queue.

## 2. Mutually exclusive customer collection modes

- The Super Admin may select either `manual_bank_transfer` or `paystack` as the active mode for **new** orders. Only one mode may be active for new orders at any time.
- Store the chosen mode immutably on each order/payment intent. Changing the global setting affects new orders only. It does not migrate, reinitialize or settle existing orders through a different rail.
- A single order must never be settled through both rails. The same incoming bank transaction or Paystack transaction must never settle two orders. An order can have at most one accepted successful payment.
- When manual bank transfer is active, new checkout orders must not initialize Paystack, call Paystack verification as their proof of payment, or depend on Paystack webhooks. When Paystack is active, manual-transfer screenshot approval must not settle that order.
- Supplier payments are outgoing transfers from Victorious MARKET's corporate bank account and are a separate workflow from customer collection. In the manual operating model, the admin makes supplier transfers through the bank's normal approved channel; the website records and reconciles evidence and must not call Paystack's transfer API.

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
- Each transfer must be at least ₦5,000 and no more than ₦1,000,000. Do not silently split an over-cap payout. The admin may leave it queued or select an allowed subset of eligible payable items.
- The supplier does not have to request payment. Supplier-facing statements/status can show accrued, held, eligible and paid balances, but must not expose another supplier's data or permit a supplier to bypass admin approval.
- Before transfer, recheck supplier identity/KYC, active status, verified bank details, recovery debts/offsets, selected items, previous reservations, available reconciled corporate funds and payout caps. Bank-detail changes require the existing verification and cooldown controls.
- Record the human initiating admin, beneficiary snapshot, amount, selected payable IDs, timestamp, bank transaction reference and supporting evidence. Mark paid only after checking the actual bank debit and beneficiary transfer outcome. If outcome is uncertain, hold for reconciliation; never blindly retry.
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
- proactive payable queue, no supplier request prerequisite, payout min/max, non-FIFO explicit selection, duplicate-transfer prevention, uncertain outcome hold and verified bank debit before paid status;
- no Paystack API call for manual-mode customer orders or manual supplier payouts;
- migration compatibility on SQLite and PostgreSQL, including rollback and unique-index behavior.

Documentation changes alone do not certify implementation. Do not enable manual mode until the code and tests prove these invariants on the target commit.
