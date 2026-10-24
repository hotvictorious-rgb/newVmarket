# Victorious MARKET — Manual Bank Transfer and Payment-Provider Policy Addendum

**Decision date:** 24 October 2026  
**Status:** Proposed policy alignment for owner review; this document does not certify implementation.  
**Binding authority:** Once owner-approved, this addendum supplements [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md) and the [Payment, Refund, Settlement & Paystack Webhook Policy](../../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). If there is a conflict, this addendum governs manual bank transfer only; all unrelated active business rules remain in force.

## 1. Purpose, strict isolation and permitted payment methods

Victorious MARKET has two distinct customer payment paths:

1. **Paystack checkout:** Paystack-supported card and Paystack bank-transfer channels, verified through trusted Paystack verification/webhook mechanisms.
2. **Manual Bank Transfer:** The customer transfers directly to the Victorious MARKET receiving bank account configured in the admin dashboard. This is a direct bank credit, is not a Paystack transaction, and must never be represented or processed as one.

### 1.1 Mutual-exclusion activation rule

Only **one customer payment method may be active globally at any time**. An authorized admin may activate Paystack, activate Manual Bank Transfer, or deactivate both. The system must reject any attempt to enable the second method while the other is active. This rule must be enforced server-side and in persistent configuration, not merely by hiding a checkout option in the user interface. Configuration updates must be atomic and concurrency-safe so two admins or requests cannot activate both methods simultaneously.

If neither method is active, checkout must not accept payment or create a payable order. Existing orders and pending transactions must remain auditable and must be handled according to their recorded payment method; deactivation must not delete them or pretend they were paid, cancelled or refunded.

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

An appropriately authorized admin can activate Manual Bank Transfer only when Paystack is inactive, or deactivate Manual Bank Transfer; activating it must atomically ensure Paystack is inactive. Activating Paystack must likewise ensure Manual Bank Transfer is inactive. The dashboard must display the current active method and reject conflicting concurrent changes. When Manual Bank Transfer is inactive, customers cannot start new manual-transfer payments. The admin can configure the receiving bank name, account name, account number, customer-facing instructions and effective status. Account-detail changes must be permission-checked and leave an immutable audit record identifying the actor, timestamp and changed fields. Never expose private credentials or internal bank integration secrets in customer-facing pages.

Customers must see the active configured account details and the amount payable for their order. Historical orders must retain a snapshot of the instructions/account destination shown when the payment method was selected, while new orders use the currently active configuration. Changing or disabling account details must not silently rewrite an existing order's payment instructions or payment status.

## 3. Customer instructions and order reference

For each order, the system generates a unique, non-editable order code. The payment instructions must tell the customer to:
- transfer the exact total displayed for that order to the configured Victorious MARKET account;
- copy the order code exactly into the bank narration, description or remarks field where the sending bank supports it;
- retain the bank transaction reference/receipt for their records; and
- wait for Victorious MARKET to verify the actual credit before treating the order as paid.

The order code is a matching aid, not proof of payment. **After making a manual bank transfer, the customer must upload a screenshot or image of the transfer receipt against that specific order before submitting it for admin review.** The order remains awaiting verification while the evidence is reviewed. The upload must be securely stored and linked to the order/payment attempt; it must not be exposed through a public, guessable URL. Preserve upload metadata and review history. If the customer replaces or resubmits evidence, retain the previous version and audit trail rather than silently overwriting it. Accept only configured, validated file types and size limits, and apply available file-safety checks.

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

## 6. Reconciliation, refunds and financial safety

Manual bank transfers must be represented as their own tender/provider type and must not be given fabricated Paystack references, Paystack webhook events or Paystack verification status. Keep customer receipts, payment attempts, verified bank credits, refunds, supplier payables, logistics-company payables, cashback and reconciliation adjustments as distinct auditable records. The current cashback rule specifies eligible merchandise funded by verified Paystack money; therefore, manual-transfer orders do not qualify for cashback unless the owner explicitly amends that rule and its funding/reversal controls.

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
- server-side and concurrency-safe proof that Paystack and Manual Bank Transfer cannot both be active globally;
- checkout rejects payment when both methods are disabled and rejects any invalid dual-enabled configuration;
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
