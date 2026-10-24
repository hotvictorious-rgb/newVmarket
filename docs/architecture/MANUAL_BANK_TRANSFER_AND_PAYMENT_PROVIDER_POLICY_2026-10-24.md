# Victorious MARKET — Manual Bank Transfer and Payment-Provider Policy Addendum

**Decision date:** 24 October 2026  
**Status:** Approved policy; canonical implementation enforced across dual payment rails and double-entry ledger.  
**Binding authority:** This addendum supplements [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md) and the [Payment, Refund, Settlement & Paystack Webhook Policy](../../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). If there is a conflict, this addendum governs manual bank transfer only; all unrelated active business rules remain in force.

## 1. Purpose, strict isolation and permitted payment methods

Victorious MARKET has two distinct customer payment paths:

1. **Paystack checkout:** Paystack-supported card and Paystack bank-transfer channels, verified through trusted Paystack verification/webhook mechanisms.
2. **Manual Bank Transfer:** The customer transfers directly to the Victorious MARKET receiving bank account configured in the admin dashboard. This is a direct bank credit, is not a Paystack transaction, and must never be represented or processed as one.

### 1.1 Coexisting customer payment methods

Paystack and direct Manual Bank Transfer may both be active and offered to customers simultaneously. An authorized admin may enable either or both, or disable both. Configuration updates must be atomic and concurrency-safe. If neither method is active, checkout must not accept payment or create a payable order. Each order is permanently bound to exactly one chosen method, with method-specific verification and no cross-method payment, evidence, refund or settlement. Existing orders and pending transactions must remain auditable and must be handled according to their recorded payment method; deactivation must not delete them or pretend they were paid, cancelled or refunded.

### 1.2 Per-order payment-method lock

At order/payment-intent creation, the system records exactly one immutable payment-method identity: paystack or manual_bank_transfer. That identity governs all attempts, evidence, verification, approval, settlement, refund routing and reconciliation for the order.

- An order must never be paid partly through Paystack and partly through Manual Bank Transfer.
- A Paystack reference, webhook, capture or refund must never verify, approve, settle or refund a manual-transfer payment.
- A manual bank credit, narration, bank reference or admin approval must never verify, approve, settle or refund a Paystack payment.
- Neither method may be used as a fallback to complete, top up, replace or settle the other method's order.
- Provider identifiers and evidence must remain namespaced by method. A matching amount or order code alone cannot make evidence from one path valid for the other.
- The system must reject cross-method callbacks, transaction reuse, duplicate application and attempts to attach a transaction from another method to an existing order.

The order's payment method cannot be silently switched after a payment attempt has been created or a transfer may have been sent. If an attempt definitively failed or was cancelled and there is authoritative evidence that no payment was captured/credited, an authorized admin may start a separately audited payment-method reset under a defined, tested procedure. The original attempt and method must remain in the audit trail. If a result is pending, unknown, disputed, or a bank transfer may still arrive, switching is prohibited until the original path is reconciled. A late or duplicate payment must be held for investigation and must not automatically fulfil or settle the order.

### 1.3 Shared financial core without shared transaction identity

The methods share only provider-neutral order lifecycle and financial safety rules: exact amount and currency, one verified payment per order, idempotency, transaction uniqueness, auditability, reconciliation, payment-before-sourcing, and manual human authorization for outflows. They do not share provider transactions, verification evidence, transaction references, settlement mechanisms or refund rails. The architecture may use common interfaces or common ledger infrastructure only if method-specific identity and permissions remain enforced end-to-end.

Cash on delivery remains disabled. Suppliers may not collect customer payments or direct customers to private/off-platform accounts. All customer payments must be made to an account officially configured for Victorious MARKET.

## 2. Admin-configured receiving account

An appropriately authorized admin can activate or deactivate Manual Bank Transfer and Paystack independently. Both methods may be active and offered to customers simultaneously, or either method may be enabled individually, or both disabled. The dashboard displays the status of both payment rails and rejects conflicting concurrent changes. When Manual Bank Transfer is inactive, customers cannot start new manual-transfer payments. The admin can configure the receiving bank name, account name, account number, customer-facing instructions and effective status. Account-detail changes must be permission-checked and leave an immutable audit record identifying the actor, timestamp and changed fields. Never expose private credentials or internal bank integration secrets in customer-facing pages.

Customers must see the active configured account details and the amount payable for their order. Historical orders must retain a snapshot of the instructions/account destination shown when the payment method was selected, while new orders use the currently active configuration. Changing or disabling account details must not silently rewrite an existing order's payment instructions or payment status.

## 3. Customer instructions and order reference

For each order, the system generates a unique, non-editable order code. The payment instructions must tell the customer to:
- transfer the exact total displayed for that order to the configured Victorious MARKET account;
- copy the order code exactly into the bank narration, description or remarks field where the sending bank supports it;
- retain the bank transaction reference/receipt for their records; and
- wait for Victorious MARKET to verify the actual credit before treating the order as paid.

The order code is a matching aid, not proof of payment. **After making a manual bank transfer, the customer must upload a screenshot or image of the transfer receipt against that specific order before submitting it for admin review.** The order remains awaiting verification while the evidence is reviewed. The upload must be securely stored and linked to the order/payment attempt; it must not be exposed through a public, guessable URL. Preserve upload metadata and review history. While review remains open, an authorized admin may request replacement evidence; preserve prior versions and audit history. Final rejection ends ordinary receipt resubmission and prevents a second payment on that order. If the customer claims that funds were actually credited, open a separate support investigation and reconcile the bank credit through an explicit audited exception; do not silently reopen the order. Accept only configured, validated file types and size limits, and apply available file-safety checks.

The screenshot is supporting evidence only. It may help the admin locate and compare the transaction, but a customer-uploaded screenshot, email, SMS, transfer initiation screen or customer-supplied transaction ID alone must never establish payment. The authorized admin must independently verify the actual receiving-account credit and then explicitly approve or reject/request replacement evidence. Record reviewer identity, decision, timestamp, reason, bank transaction reference, amount and the evidence version reviewed. Rejection or missing evidence leaves the order unpaid/on hold. Only a successful, audited payment approval may start sourcing.

## 4. Payment states and admin verification

Manual transfer must have explicit, auditable states distinct from Paystack provider states, at minimum: awaiting transfer, pending verification, approved/verified, discrepancy/review required, rejected/not received, and expired/cancelled where applicable. Exact state names may follow the implementation's established state model, but transitions must be explicit and tested.

An authorized reviewer must inspect the receiving account's actual transaction/credit record, then match the incoming transaction to the order using the credited amount, currency, bank transaction reference, order code/narration where present, transaction status and available timing/sender evidence. The reviewer must confirm that the credit is final/available and not merely initiated, pending, reversed or failed.

On approval, the system must atomically record the verified payment against the order, including the verified amount, currency, receiving account/configuration version, bank transaction reference, reviewer identity, approval timestamp and evidence/audit reference. A single bank transaction can be applied to no more than one order/payment. A payment can be approved only once. Concurrent reviews, duplicate submissions and retries must not create duplicate receipts, paid transitions, sourcing attempts or supplier payables.

If the narration is missing/wrong, the amount differs, the transaction cannot be found, the credit is uncertain, or the transaction appears already used, the payment remains on hold for investigation. Underpayment and overpayment must not silently count as fully paid; an authorized admin must resolve the discrepancy through an explicit, audited decision. Never turn excess money into wallet credit automatically.

Manual verification is initially human-operated. Do not imply that a screenshot, email parser, or untrusted notification is a bank-confirmation integration. A trusted bank feed may be added later only through a separately reviewed design with equivalent transaction authenticity, replay protection and reconciliation controls.

## 5. Order allocation and fulfilment gate

Before verified payment, the order may be created and its payment instructions displayed, but catalogue checks remain non-binding. Do not reserve stock, bind a supplier, notify a supplier of a binding order, start order-specific supplier confirmation, create a supplier payable, dispatch a rider or fulfil the order.

Only after the manual transfer is verified and approved may the order enter the existing post-payment workflow: revalidate current eligibility, request supplier confirmation for the exact quantities, perform bounded eligible-supplier failover, and route exhausted sourcing attempts to the admin-managed exception process. Payment approval does not guarantee stock availability or delivery; it only satisfies the payment gate.

A late transfer for an expired/cancelled order must not automatically reactivate the order or start fulfilment. Hold it for authorized review and reconcile the real bank credit with the order's current state before deciding the next action.

## Supplier settlement controls (owner-confirmed 24 October 2026)

These rules govern outgoing supplier settlements, not customer incoming-payment verification.

- Settlement minimum is ₦1; maximum is ₦1,000,000 per individual settlement transaction/request. No FIFO/oldest-first selection or silent splitting.
- The initiating authorized admin may complete reconciliation for an outgoing supplier settlement; a second administrator is not mandatory for that settlement reconciliation.
- The admin must attach outgoing-transfer evidence linked to the supplier, settlement, amount, destination and bank reference. The upload alone is not proof of completed payment; reconcile the actual bank outcome.
- Pending, uncertain, failed or reversed outcomes must not be marked paid. Reconcile the original attempt before retrying to prevent duplicate outflow.
- Supplier settlement requires an eligible reconciled payable and cleared/reconciled platform funds, regardless of customer tender. Keep Paystack and manual customer-payment identities separate; never fabricate Paystack references for direct manual transfers.
- Supplier and logistics-company disbursements occur Monday through Saturday and only after the relevant funds have cleared and been reconciled in the corporate receiving account.

## 6. Reconciliation, refunds and financial safety

Manual bank transfers must be represented as their own tender/provider type and must not be given fabricated Paystack references, Paystack webhook events or Paystack verification status. Keep customer receipts, payment attempts, verified bank credits, refunds, supplier payables, logistics-company payables, cashback and reconciliation adjustments as distinct auditable records. Cashback is awarded as an eligible merchandise benefit for both Paystack-funded and verified manual-bank-transfer orders, with the funding source explicitly recorded (`source`: `paystack` or `manual_bank_transfer`). The system must always remain reconcilable, traceable, auditable, and balanced (`debits == credits`).

The Zero-Automated-Outflow Law remains binding. No script, webhook, queue, cron job or timeout may autonomously send a refund or bank transfer. Any refund for an order paid by manual bank transfer must remain a pending, auditable case requiring explicit human Super Admin review, authorization and manual dispatch initiation. The actual refund destination/matching procedure for manual-transfer orders must be explicitly approved before production release; do not assume that Paystack's original-channel refund API applies to a direct bank transfer. Do not mark a refund complete until the outgoing payment is independently evidenced and reconciled.

The current policy's original-Paystack-channel refund rule applies to Paystack-paid orders. It must not be misapplied to a direct bank transfer. Until the manual-transfer refund procedure is approved and implemented, cases requiring such a refund must be held for authorized manual resolution and must not be silently converted to wallet credit.

## 7. Payment-provider replaceability

Checkout and order lifecycle logic must depend on a provider-neutral payment contract/state model, not on Paystack-specific controller/service assumptions. Provider adapters may initialize or verify their own transactions and normalize provider evidence, but the shared financial core must enforce:
- exact amount in integer minor units and currency;
- order/customer/reference binding;
- authenticated and replay-safe provider events where applicable;
- idempotent payment application and transaction uniqueness;
- safe handling of late success, duplicate captures, reversals and uncertain outcomes;
- durable audit evidence and reconciliation; and
- the Zero-Automated-Outflow Law for all refunds and disbursements.

Manual bank transfer is a separately verified tender path, not a fake gateway adapter. Any future provider must pass the same financial safety acceptance tests before being enabled.

## 8. Required acceptance tests before release

At minimum, tests must prove:
- admin permissions, validation and audit history for payment-method activation and account configuration;
- server-side and concurrency-safe proof that Paystack and Manual Bank Transfer can coexist simultaneously, operate individually, or both be disabled;
- checkout rejects payment when both methods are disabled;
- each order is bound to exactly one payment method and cannot mix, top up, switch or settle through the other method;
- cross-method references, webhooks, bank credits, approvals, refunds and transaction reuse are rejected;
- deactivation preserves existing orders and evidence; pending/unknown attempts cannot be switched, and late payments are held for review;
- unique order codes and correct customer instructions/account snapshot;
- manual-transfer customers must upload a valid payment screenshot/receipt against the correct order before admin review; upload failure does not mark the order paid;
- secure evidence storage, order-scoped access, file validation, evidence version history and immutable admin review/audit decisions;
- admin can approve only after independent actual-credit verification; reject/request-more-evidence leaves order unpaid;
- screenshots and customer claims cannot mark an order paid;
- exact match, missing/wrong narration, underpayment, overpayment, pending/reversed credit and unknown transaction handling;
- duplicate bank transaction cannot be used for two orders;
- concurrent approvals and request retries produce one payment and one sourcing start;
- no supplier confirmation/allocation/payable or fulfilment before approval;
- late payment on expired/cancelled order enters review rather than auto-reactivating;
- approval survives retry/crash without duplicate financial effects;
- manual-transfer refunds remain blocked pending explicit authorized review and are never dispatched automatically; and
- existing Paystack, refund, webhook recovery, settlement and ledger safety tests remain passing.

Policy alignment is not evidence of implementation. Code, database constraints, admin permissions, operational bank-verification procedure and tests must be reviewed separately before release.


## Owner-confirmed superseding clarification — 24 October 2026

This clarification supersedes conflicting earlier statements in this addendum, including the earlier global mutual-exclusion rule and any terminal-on-first-submission wording that prevents replacement evidence during an open review.

- **Paystack and direct Manual Bank Transfer may coexist.** Both may be enabled and offered to customers simultaneously. If neither is enabled, checkout is disabled. Each order is bound to exactly one method and uses that method's own verification path. No cross-method verification, switching after an attempt starts, mixed payment or fabricated provider reference is allowed. Pending/unknown/disputed attempts must be reconciled before an explicitly audited reset.
- **One order, one accepted payment.** Each Paystack reference or incoming bank transaction is unique and may be applied to at most one order. Exact order/amount/currency binding, persistent uniqueness, atomic transitions, idempotency and concurrency safety are mandatory.
- **Manual receipt evidence:** while review remains open, an authorized admin may request replacement evidence; retain the old version and audit trail. A final rejection ends ordinary receipt resubmission and does not allow a second payment on that order. A claim that money was actually credited despite rejection opens a separate support investigation; reconcile the actual bank credit and resolve only through an explicit audited exception, without silent order reactivation.
- **Outgoing settlements:** both supplier and logistics-company settlements are manual bank transfers made outside the website and recorded/reconciled in it. Each individual request/transfer has a ₦1 minimum and ₦1,000,000 maximum, no FIFO/oldest-first requirement and no silent splitting. The initiating authorized admin may reconcile without a second admin, but separate audit events, unique bank reference, idempotency and actual bank-outcome verification remain mandatory. Admin-uploaded transfer evidence applies to both payee types and is not proof of completion by itself.
- Both settlement types require an eligible payable and cleared/reconciled platform funds. Settlements occur Monday through Saturday for manual bank transfers executed through the business bank. Pending, uncertain, failed or reversed transfers are not settled; reconcile before retry. No automated bank outflow. Keep supplier and logistics payables separate and balanced in the ledger; old vendor-wallet payout rules do not govern retailer-model payables.
- Manual-transfer refunds remain a separately authorized manual process; do not use Paystack's refund API for a direct bank transfer or mark a refund complete before its actual outgoing bank outcome is reconciled. Cashback applies to both Paystack-funded and verified manual-bank-transfer orders, with the funding source explicitly recorded (`source`: `paystack` or `manual_bank_transfer`). The system must always remain reconcilable, traceable, auditable, and balanced (`debits == credits`).
- This policy is not implementation proof; code, database constraints, permissions and acceptance tests require separate verification.
