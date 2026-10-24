# Victorious MARKET — Order State, Custody and Recovery Policy

**Decision date:** 23 October 2026  
**Status:** OWNER-APPROVED BINDING POLICY — approved 23 October 2026; documentation only until implementation is audited and tested  
**Scope:** Order lifecycle, item lifecycle, physical custody, event recovery, exception ownership and reconciliation. This document does not certify or implement runtime behaviour.

## 1. Authority and non-conflict rule

This policy supplements, and does not replace, the Active Business Rules, the owner-approved Exception and Reverse-Logistics Policies dated 23 October 2026, the Payment/Refund/Webhook Policy, or the Operational Workflow and Exception Playbook.

Apply this precedence:
1. The latest owner-approved Active Business Rules and specific owner-approved addenda govern business decisions.
2. This policy defines cross-cutting lifecycle, custody, recovery and reconciliation controls where the existing rules do not already prescribe the outcome.
3. Dependent technical and operational documents must be aligned to the above; older conflicting wording must not silently override them.

This policy must not change customer fees, refund entitlements, sourcing priorities, delivery windows, supplier eligibility, payout limits, return eligibility, or the Zero-Automated-Outflow Law. If a conflict is discovered, fail closed, preserve evidence, flag it for authorized admin review, and update the conflicting document before implementation. Do not invent a new business outcome to resolve a conflict.

## 2. Core model: keep lifecycle dimensions separate

The system must represent and reconcile these related but distinct dimensions:
- **Payment:** awaiting verification, verified, mismatched/under review, uncertain, duplicate charge, or failed as authoritatively confirmed.
- **Order orchestration:** awaiting payment, paid and awaiting allocation, sourcing, admin-managed exception, ready for dispatch, in fulfilment, partially fulfilled, delivered, cancellation in progress, cancelled, or closed after required reconciliation.
- **Order line:** awaiting sourcing, allocated, supplier-confirmed, ready, picked up, in transit, delivered/accepted, rejected, cancelled, returned, disputed, or awaiting reconciliation, as applicable.
- **Custody:** supplier-held, handed to assigned logistics company, logistics secure holding, customer accepted, return in transit, supplier return received, or unresolved/disputed.
- **Refund obligation:** none identified, review required, pending approval, approved for manual dispatch, provider processing/uncertain, provider-confirmed, failed/needs attention, or reconciled.
- **Supplier and logistics payable:** not eligible, eligible pending reconciliation, held/disputed, approved for manual dispatch, provider processing/uncertain, provider-confirmed, reversed/failed, or reconciled.
- **Exception case:** open, assigned, awaiting customer, awaiting supplier/logistics evidence, awaiting admin decision, awaiting provider reconciliation, resolved, or escalated.

These are business concepts, not mandatory literal database enum values. Map them deliberately onto the existing schema and statuses. Do not create duplicate or contradictory status aliases. An order can be delivered while a separate refund/dispute case remains open; resolving one dimension must not falsely resolve another.

## 3. Invariants that every transition must preserve

1. **Verified payment first:** no binding supplier allocation, supplier reservation, supplier notification, or supplier payable creation before authoritative Paystack verification of reference, order/customer binding, exact amount in integer kobo and NGN currency, with idempotency protection.
2. **A payment is not delivery:** payment verification does not prove stock readiness, custody transfer, delivery, refund completion or payable eligibility.
3. **One source of truth per event:** every payment event, fulfilment action, custody handover, refund operation and payout operation must have a stable identifier/idempotency key and be applied at most once for its intended effect.
4. **One accountable custodian per item/quantity at a time:** record the responsible supplier, assigned logistics company, secure holding custodian, customer, return carrier or receiving supplier. If custody is uncertain, mark it unresolved and block normal closure and affected payable eligibility.
5. **One logistics company and one assigned rider per VM order:** preserve the existing whole-order accountability rule, including multi-supplier orders. A partial fulfilment decision does not create a second normal delivery assignment. An authorised defective-item return is a separate reverse-logistics record, not a redelivery.
6. **No unsafe financial automation:** no webhook, timeout, scheduled job, queue worker or retry may autonomously issue a refund or bank transfer. Every refund and supplier/logistics disbursement requires recorded human Super Admin review, authorization and manual dispatch initiation under the Zero-Automated-Outflow Law.
7. **No payment by implication:** supplier readiness, assignment, pickup, a delivery attempt, a customer complaint, a refund request, or a timeout alone must not be treated as proof of delivery, refund completion or provider-confirmed payout.
8. **Preserve snapshots:** never recalculate an existing order’s customer price, item price, supplier cost, delivery charge, bulky surcharge, corridor, logistics tariff, discount or other captured amount from current catalogue/configuration values. Corrections are explicit, authorized and audited.
9. **No silent business changes:** no silent product substitution, added customer charge, altered destination, changed supplier cost, or changed return/refund outcome.
10. **Audit every privileged action:** record actor, role, timestamp, reason, affected order/item/payment/return/payable, before/after state and relevant evidence/provider references. Keep financial and custody history append-only or otherwise tamper-evident.

## 4. Payment confirmation and crash recovery

### 4.1 Verified payment, then allocation
The checkout may create a pending order/payment intent before payment. Before verified payment, any availability check is non-binding and must not reserve supplier stock, bind a supplier, notify a supplier of a binding assignment, or create a supplier payable. Once payment is authoritatively verified, revalidate stock and supplier eligibility, then start allocation under the existing supplier-selection rules.

### 4.2 Payment succeeds but order processing fails
If Paystack confirms a successful charge but the application crashes, times out, or fails while saving/updating the order:
- Preserve the provider event/transaction reference in an idempotent payment inbox or equivalent durable reconciliation record before acknowledging successful processing, where the architecture supports this.
- Retry/reconcile the same payment reference against the pending checkout/order identity; use an atomic transaction/unique constraints to prevent a second order, second payment application, duplicate stock reservation or duplicate payable.
- If the original order identity cannot be safely recovered, quarantine the payment as an unmatched exception for authorized reconciliation. Do not create a guessed replacement order, fulfil it, or refund it automatically.
- If the payment is verified but the order is cancelled, unavailable, mismatched or otherwise not safely fulfilable, keep fulfilment and payout blocked and create the appropriate pending refund-review case. The human Super Admin must decide and manually dispatch any approved refund.
- Never infer failure from a browser redirect, timeout, missing webhook, or delayed database write.

### 4.3 Duplicate, late, malformed and uncertain payment events
- Verify signature/authority and exact reference, amount, currency and order/customer binding.
- Duplicate webhook delivery for the same charge must be a no-op after the first valid application, apart from safe audit/monitoring updates.
- A second actual successful charge is a separate provider transaction and must create a duplicate-charge reconciliation/refund-review case; it must not create a second fulfilment or supplier payable for the same purchase.
- Underpayment, overpayment, currency/reference mismatch, unmatched events and uncertain provider outcomes remain quarantined for reconciliation. Do not fulfil, create cashback, release a stock hold or issue a refund until the relevant authoritative facts are resolved under the existing policy.
- Reconcile uncertain operations before retrying the same financial action; never blindly repeat a charge, refund or transfer.

## 5. Supplier allocation and concurrency recovery

The existing supplier rules remain unchanged: active/admin-approved supplier with one approved Uyo pickup location; explicit sufficient in-stock SKU offer; product-specific 24-hour unavailability cooldown; fewer than five active pending handovers; and whole-order plan priority of fewest distinct pickup locations, then lowest total wholesale cost, then documented reliability, then stable deterministic tie-break. Sourcing confirmation runs 8:00 AM–6:00 PM with the existing 60-minute timer behaviour. Road transit remains 8:00 AM–5:00 PM.

After verified payment, allocation must revalidate stock and eligibility atomically enough to prevent two concurrent orders from consuming the same available quantity. Use row/quantity locks, conditional updates, database constraints or an equivalent proven mechanism. If allocation loses a race, retry the sourcing decision safely or move the order to the existing admin-managed exception state; do not oversell, create duplicate assignments or silently cancel/refund.

Supplier rejection, timeout, shop-floor breach or pickup stockout must use the existing 24-hour SKU cooldown, next-best eligible supplier failover and Super Admin alert/override policy. Any reassignment must preserve original history, record the new offer/cost snapshot, and post only balanced, idempotent compensating payable adjustments. Do not create a payable to the failed supplier merely because it was previously assigned. Block collection/payout for disputed or unreconciled items.

If all eligible suppliers are exhausted, use the existing admin-managed exception and approved partial-fulfilment process. Do not automatically refund merely because allocation failed.

## 6. Item custody and handover proof

For each item and quantity, record custody and handover events separately from the commercial order status.

- **Supplier custody:** the supplier remains responsible until the required pickup handover is verified.
- **Pickup handover:** record the correct order/stop/items, assigned rider and logistics company, pickup OTP, required photo evidence, timestamp and resulting custody transfer. The existing policy requires a unique pickup OTP and mandatory photo proof at each supplier pickup location.
- **Multiple supplier locations:** do not unlock transit or disclose the doorstep details until every required supplier stop for that order has the required verified handover evidence, consistent with the existing operational workflow.
- **Logistics custody:** after verified handover, the assigned logistics company is accountable for the collected goods. If a delivery attempt fails because the customer is absent, preserve the existing rule that the parcel returns to secure logistics holding custody for the permitted same-corridor future attempts.
- **Doorstep delivery:** record item-level acceptance and rejection. The existing delivery OTP and mandatory geo-tagged doorstep photo remain required for successful delivery verification. A single successful OTP must not mark unaccepted/rejected items delivered.
- **Return custody:** create a unique return record linked to the original order and line. Record collection, carrier, route/compatibility, item condition, quantity, timestamps and receiving supplier confirmation. A return is not complete merely because a rider says it was dropped off.
- **Disputed/missing evidence:** preserve the last verified custodian and mark custody unresolved. Alert the assigned case owner; do not fabricate a handover, mark delivered/returned, or release affected payables until reconciliation or an explicitly authorized completion exception under the existing policy.

Evidence should be securely stored with access control and retention rules. Do not expose supplier wholesale costs or retailer margins to riders/customers.

## 7. Existing exception policies remain specific and binding

This policy does not merge distinct exception types into one generic cancellation/refund rule.

### 7.1 Supplier stockout and partial fulfilment
Follow the approved 23 October Exception and Reverse-Logistics Policy: try eligible alternative suppliers first; then admin controls the partial offer. Customer has 24 hours from successful offer issuance to respond; repeated notifications do not reset the deadline; explicit rejection or silence after the deadline means whole-order cancellation. A timeout must not move money. Accepted partial fulfilment cancels only unavailable lines; available lines continue under the existing order and assignment. No later redelivery of cancelled lines.

Refund amounts use immutable snapshots. For accepted partial fulfilment, request refund of the cancelled merchandise and the attributable bulky surcharge for cancelled bulky units; retain the base delivery fee if the remaining order is delivered. For whole-order cancellation after partial-offer rejection/timeout, include undelivered merchandise and the original delivery fee in the refund request, subject to applicable law and the governing refund policy. All refund requests remain human-authorized and manually dispatched.

### 7.2 Customer absent or unreachable
Do not treat absence as doorstep rejection, stockout, defect or customer refusal. Preserve the existing failed-handover rule: up to three delivery attempts, no extra platform delivery fee for those attempts, then return goods to originating suppliers and cancel the order. Under the existing Fair Protection Refund Rule, the human Super Admin authorizes and executes a merchandise-only refund after the prescribed process; the original customer delivery fee is retained to cover the three physical attempts. Do not override this rule with the separate doorstep-rejection refund treatment.

### 7.3 Doorstep rejection
Follow the approved Exception and Reverse-Logistics Policy. Record full-order rejection separately from item-level rejection. Return rejected goods and reconcile supplier receipt. For full-order doorstep rejection, the approved addendum recommends including the original delivery charge in the refund request after return and reconciliation, subject to applicable law. For partial doorstep rejection, the base fee remains when accepted items are delivered; review the attributable bulky surcharge for rejected bulky units. Do not classify rejection as an absence attempt.

### 7.4 Defective, damaged, unsafe or materially incorrect items
Follow the existing 24-hour defective/damaged/non-conforming item return window, the established non-returnable categories after verified delivery OTP, case-by-case Super Admin adjudication and applicable Nigerian consumer-protection law. This policy does not extend the window, remove statutory rights, automatically approve a complaint, or promise collection when no compatible rider is available. No extra customer return fee is introduced. Remedy and any supplier recovery remain evidence-based and authorized.

### 7.5 Pickup, delivery and logistics scope
Support-managed customer pickup remains an exception only after the entire order is ready at one approved collection point, the customer agrees, payment handling is authorized and evidence is recorded. No public reservation or pre-payment stock hold is created. Keep one approved logistics company and one assigned rider accountable for the whole normal VM order. Do not reintroduce independent logistics-company orders or a logistics marketplace.

## 8. Cancellation, refund and settlement are not the same event

- **Cancellation** records the decision to stop all or part of fulfilment. It does not erase the original order, payment, custody, sourcing or ledger history.
- **Return received** means the correct goods and quantity have been acknowledged at the approved destination with condition/evidence recorded; it does not itself approve a refund.
- **Refund requested/approved/dispatched/confirmed** are separate states. A refund is not complete until the exact operation, amount, currency and original transaction are matched to authoritative provider confirmation. A timeout or unmatched webhook remains uncertain.
- **Payable eligible/approved/dispatched/confirmed/reconciled** are separate states. Eligibility does not authorize automatic outflow.
- **Delivered** applies only to items with required proof and customer acceptance under the existing delivery protocol. Cancelled or rejected lines cannot be counted as delivered.
- **Order operationally complete** does not mean every related refund, dispute, return or payable case is closed. A case/order may be closed only according to its own state and when required physical and financial reconciliation is complete. Preserve links to any separately open obligations.

### Supplier payable
Use immutable agreed wholesale cost per sourced item. Eligibility requires verified successful delivery or an explicitly authorized completion exception followed by reconciliation, as already permitted by the Active Business Rules and payment policy. Assignment, readiness, pickup or delivery attempt alone is insufficient. If a supplier has already been paid and a valid customer refund is later approved, handle customer refund and evidence-backed supplier recovery as separate operations; do not delay a valid refund solely while seeking supplier recovery.

### Logistics payable
Use the assigned company's configured corridor base payable plus that company's bulky-unit surcharge multiplied by the applicable bulky-unit count, snapshotted at assignment. Never derive logistics payable from customer delivery fee or percentage, and never fall back to the customer delivery fee when the configured company tariff is missing. Missing/invalid tariff must fail closed into an admin configuration/reconciliation case; it must not produce an invented payable. Any platform subsidy is recorded separately.

### Money-out controls
Supplier-payment request limits remain ₦5,000 minimum and ₦1,000,000 maximum per request/disbursement; no silent split of an over-cap request; no FIFO/oldest-first automatic selection. Disbursements remain Monday–Friday only after Paystack collections fully settle into the corporate settlement account. Human Super Admin review, approval, manual dispatch, idempotency, exact provider matching and provider-confirmed completion remain mandatory.

## 9. Exception queue, ownership and escalation

Every unresolved exception must have:
- unique case ID and related order, item, payment, supplier, logistics, return or payout references;
- specific exception type and severity;
- current owner/role and next required action;
- created/last-updated timestamps, applicable deadline and escalation state;
- evidence and external/provider references;
- customer communication status where relevant;
- physical custody status and financial impact;
- resolution decision, authorizing actor, reason and reconciliation outcome.

Do not silently close a case because a notification was sent, a timer expired, a provider request timed out, or an actor clicked a button. Escalate overdue cases to an authorized admin. Automated reminders/escalations may notify or create review tasks, but may not autonomously cancel a paid order, approve/dispatch a refund, transfer money or erase an obligation except where a specific existing policy explicitly authorizes the operational state transition (such as the partial-offer timeout leading to whole-order cancellation handling, without moving money).

## 10. Required recovery and concurrency scenarios

Implementation and test plans must explicitly cover:
1. Paystack success arrives before/after a crash during order persistence.
2. Duplicate webhook for one payment and two genuinely successful charges for one checkout.
3. Payment timeout or refund/transfer timeout with unknown provider outcome.
4. Two concurrent orders competing for the last supplier quantity.
5. Supplier stockout or suspension after payment and before pickup.
6. Reassignment after the first supplier payable snapshot exists.
7. Multi-supplier order where one pickup is complete and another fails.
8. Rider has collected goods but pickup evidence is incomplete or disputed.
9. Customer accepts some items and rejects others at the doorstep.
10. Three actual absence attempts, distinguished from refusal and defect.
11. Return collected but supplier disputes receipt, quantity or condition.
12. Duplicate admin action, retry, webhook or scheduled job for cancellation/refund/payable.
13. Missing company/corridor logistics tariff.
14. Delivery verified but a separate refund/dispute remains open.
15. Provider confirms a refund/payout after the application has timed out or restarted.

For each scenario, tests must verify final order/item/payment/custody/refund/payable states, audit history, ownership of unresolved cases and the absence of duplicate financial effects. A test passing means only that tested scenario passed; it does not certify every production path.

## 11. Documentation and implementation requirements

Align this policy with:
- Active Business Rules (the primary owner-decision source of truth).
- Approved Exception and Reverse-Logistics Policies dated 23 October 2026.
- Operational Workflow and Exception Playbook.
- Payment, Refund and Webhook Matrix.
- Master Blueprint.
- Documentation Alignment Report.

The implementation audit must separately inspect database schema/constraints, webhook inbox and signature verification, payment/order transaction boundaries, supplier stock locking, status transition guards, custody proof, refund/payout authorization, ledger idempotency, logistics tariff fail-closed behaviour, admin queues and all relevant tests. Documentation alignment is not implementation completion.

**Historical risk and remaining verification:** earlier code paths used customer delivery fee/percentage fallbacks when the logistics-company corridor tariff was missing. The working branch now contains independent company/corridor tariff fields, fail-closed checkout validation, suspense-based legacy recovery and regression coverage. The current CI run must pass against the exact working-branch commit, and live migration/configuration must still be verified before production. Never fall back to the customer delivery fee.

**Approval and implementation boundary:** The owner approved this policy on 23 October 2026. It is binding for future design and implementation on the working branch. Approval does not mean the application code has been changed, audited, tested, merged to the main branch, or deployed. Runtime compliance remains unverified until a separate implementation audit and required tests pass.
