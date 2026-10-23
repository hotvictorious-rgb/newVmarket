# Victorious MARKET — Approved Exception and Reverse-Logistics Policies

**Decision date:** 23 October 2026  
**Status:** Owner-approved policy baseline  
**Scope:** Business policy and documentation. This document does not certify or implement runtime behaviour.  
**Authority:** This addendum is binding alongside the Active Business Rules. If a dependent document conflicts with this addendum, the Active Business Rules and this approved addendum take precedence until the dependent document is aligned.

These policies are approved for documentation alignment. No application code changes are authorised by this document alone.

## 1. Partial fulfilment and supplier stockout policy

### 1.1 Supplier stockout must trigger sourcing recovery first

When a supplier reports that an ordered product is unavailable, the system must:

1. Record the supplier's stockout report, timestamp, affected order line, supplier and SKU.
2. Apply the existing SKU-specific unavailability cooldown.
3. Attempt to source the product from another eligible supplier under the existing supplier-selection rules.
4. Escalate to admin when no eligible supplier can fulfil the item, or when admin determines that further sourcing is not viable.

A first supplier's stockout must not automatically cancel the product if another eligible supplier can fulfil it.

### 1.2 Admin controls the partial-fulfilment decision

When sourcing recovery fails, the order enters an admin-managed exception state. Admin must identify the unavailable line and determine which other order lines remain fulfilable.

The customer is then offered two choices:

- **Accept partial fulfilment:** receive the available items and have the unavailable item cancelled.
- **Reject partial fulfilment:** cancel the entire order.

The system must not silently substitute a different product, charge the customer an additional amount, or remove a paid item without recording the admin's decision.

### 1.3 Customer response deadline

The customer has **24 hours from the recorded time the partial-fulfilment offer is successfully issued** to respond.

- If the customer accepts within the window, admin records acceptance and cancels the unavailable line.
- If the customer explicitly rejects, the entire order enters the cancellation workflow.
- If the 24-hour window expires without a response, treat the silence as rejection of the partial order and follow the same whole-order cancellation workflow.
- A timeout must not automatically issue a refund or move money.
- The system must retain the offer timestamp, response or timeout status, and resulting admin action.

The deadline must be calculated consistently using the stored timestamp and configured timezone. Repeated notifications must not silently restart the 24-hour window.

### 1.4 No redelivery after partial fulfilment

Partial fulfilment is a one-delivery resolution. The available items continue through the existing delivery workflow; the unavailable item is cancelled and handled through the refund process.

The cancelled item must not be automatically scheduled for delivery later. If the customer wants that product in the future, it is a new purchase with a new order and applicable checkout terms.

## 2. Partial-order cancellation and refund policy

### 2.1 Accepted partial order

When the customer accepts partial fulfilment:

- Admin must cancel the unavailable order line, not the whole order.
- The cancellation must preserve the original order, payment and item-price snapshots.
- The available items continue under the original order and existing delivery assignment.
- The customer must not be charged a second base delivery fee.
- The cancelled item's merchandise amount becomes eligible for a refund request.
- If the cancelled item is bulky, refund the bulky surcharge attributable to that item.
- If other bulky items remain in the delivered order, their applicable bulky surcharges remain payable.
- If the order contains only small items, no bulky surcharge refund applies.
- The original base delivery fee remains charged because the remaining order was delivered.

### 2.2 Rejected partial order

When the customer rejects the partial offer, or the 24-hour deadline expires:

- Admin cancels the entire order.
- All undelivered merchandise lines enter the refund calculation.
- The original customer delivery fee is included in the whole-order refund request, subject to applicable law and the existing refund policy.
- Any collected goods must be returned to the relevant supplier and reconciled.
- The order cannot be marked successfully delivered.
- No supplier payout should be released for undelivered merchandise merely because the customer had already paid Victorious MARKET.

### 2.3 Refund calculation must use snapshots

Refund amounts must be calculated from the original order's immutable price and fee snapshots, not from current product prices or current delivery settings.

For a partial order, the calculation must distinguish:

1. Merchandise amount for cancelled items.
2. Bulky surcharge attributable to each cancelled bulky unit.
3. Base delivery fee for the order.
4. Any other independently refundable amounts required by the applicable policy or law.

The system must prevent duplicate refunds if an admin retries the action, a webhook is replayed, or the same order exception is processed twice.

## 3. Doorstep rejection policy

A doorstep rejection occurs when the rider reaches the customer and the customer refuses some or all of the goods before accepting delivery.

### 3.1 Full-order rejection

If the customer rejects the whole order:

1. The rider records the rejection reason and time.
2. The rider captures the required evidence, including the relevant delivery attempt details and photographs where safely and appropriately possible.
3. The order enters a rejected-at-doorstep state.
4. The rider returns the goods to the supplier or suppliers responsible for those goods.
5. Each supplier confirms receipt and condition through the approved return-handover process.
6. Admin reconciles the returned goods and queues the applicable refund request.
7. The order remains undelivered and cannot qualify for ordinary completed-order supplier payout.

**Customer fees:** no additional delivery fee is charged for the return. I recommend including the original delivery charge in the full-order refund request after the return and reconciliation are verified, rather than treating the original delivery as a completed service. Applicable consumer rights take precedence.

### 3.2 Partial doorstep rejection

If the customer accepts some items but rejects one or more items:

- Record acceptance and rejection separately for each order line.
- Deliver only the accepted items.
- Return rejected items to their respective suppliers.
- Do not treat the whole order as rejected if some items were accepted and delivered.
- Admin reviews the reason for rejection and determines the appropriate item-level cancellation, refund and supplier-reconciliation treatment.
- The base delivery fee remains when the customer receives the rest of the order.
- Refund the attributable bulky surcharge for a rejected bulky unit when the item is not delivered and the applicable refund decision requires it.
- No extra delivery or return fee is charged to the customer.

### 3.3 Rejection is not the same as customer absence

A customer refusing goods must not automatically be recorded as an absent customer or consume one of the existing failed-delivery absence attempts.

The system must distinguish at least these exception reasons:

- Customer absent or unreachable.
- Customer rejects entire order.
- Customer rejects specific item(s).
- Product damaged or defective.
- Product differs materially from its description or order.
- Supplier stockout.
- Other admin-reviewed exception.

The existing policy for repeated failed delivery attempts remains applicable to actual absence cases, not to every doorstep exception.

## 4. Defective or materially incorrect product return policy

This policy applies when the customer has completed the purchase and subsequently discovers that an item is defective, unsafe, damaged, or materially different from the agreed description.

It does **not** create a redelivery service.

### 4.1 Customer complaint

The customer must be able to report the issue through Victorious MARKET support. The complaint should capture:

- Order and order-line identifiers.
- Product and quantity.
- Date the goods were received.
- Reported defect or mismatch.
- Description of the problem.
- Available photographs, videos, serial numbers or other relevant evidence.
- Requested remedy, such as refund or another remedy permitted by the applicable policy and law.

The return period and any product-specific warranty terms should be disclosed clearly before purchase. They must not be used to remove rights provided by applicable Nigerian consumer-protection law.

### 4.2 Admin authorisation

Before a rider collects a returned product, admin must:

1. Review the complaint and available evidence.
2. Decide whether the case qualifies for a return under the applicable policy.
3. Create an authorised return record linked to the original order and item.
4. Identify the correct supplier and return destination.
5. Specify the item, quantity, condition requirements and evidence needed.
6. Approve the return collection.

The system must not automatically approve a refund merely because a customer submits a complaint. Equally, it must not automatically deny a valid complaint solely because a routine return window has elapsed if statutory rights may still apply.

### 4.3 Return transport on an existing route

An authorised return may be carried by a rider who is already travelling back along a compatible route.

- Return collection is subject to rider availability, route compatibility and safe capacity.
- The rider must not be required to make a separate redelivery trip.
- No additional return delivery fee is charged to the customer.
- The return must have a unique reference linked to the original order.
- The rider records collection evidence, custody changes and supplier handover.
- The supplier confirms receipt, condition and quantity.
- The return must not be mixed up with a new delivery or another customer's return.

If no compatible rider is available, the case remains pending for admin to arrange an approved return method. The system must not falsely mark the return as collected or promise a collection time that has not been confirmed.

### 4.4 Remedy after the supplier receives the item

After the return is received and reconciled, admin determines the appropriate remedy under the applicable policy and law.

Possible outcomes may include:

- Refund of the relevant merchandise amount.
- Repair or replacement where appropriate and legally permitted.
- Another agreed resolution permitted by law.

Any replacement must be treated as a separately authorised fulfilment arrangement. It must not silently generate a new delivery or a customer charge. This policy does not promise automatic replacement or redelivery.

### 4.5 Supplier responsibility and financial reconciliation

The system must record whether the issue appears attributable to:

- Supplier-supplied defective or incorrect goods.
- Damage during delivery.
- A customer-caused issue.
- An unresolved or disputed cause.

Admin must review the evidence before assigning financial responsibility. Supplier recovery must not be deducted arbitrarily or automatically without the applicable agreement and reconciliation controls.

The original order, supplier cost snapshot, customer payment, return record and refund record must remain linked for audit purposes.

## 5. Logistics policy amendments

### 5.1 Remove independent logistics-company orders from the platform scope

The platform must not implement an independent logistics marketplace in which logistics companies create, sell, price, collect payment for, or manage their own customer orders.

The supported workflow remains:

- Victorious MARKET receives the customer's order and payment.
- Victorious MARKET manages supplier sourcing and allocation.
- An approved logistics company is assigned to deliver the Victorious MARKET order.
- The assigned logistics company and rider remain accountable under the existing delivery policy.

Remove any proposed feature that would require Victorious MARKET to manage independent companies' customers, parcels, order payments, delivery fees, or commercial transactions.

### 5.2 Preserve one rider and one logistics company per VM order

The existing rule remains binding: one approved logistics company and its assigned rider handle the complete Victorious MARKET order.

Partial fulfilment does not create a second order or a second delivery assignment. A later defective-item return is a separate, admin-authorised reverse-logistics record and does not count as a redelivery.

### 5.3 Extra logistics costs

- Supplier stockout, partial cancellation, customer rejection, and authorised returns must not trigger an additional customer delivery charge.
- Existing logistics-company payables must remain independent of customer delivery fees.
- Any additional operational expense caused by an exception is borne by Victorious MARKET by default unless an approved supplier recovery arrangement applies.
- Supplier recovery requires documented evidence, an applicable agreement, and admin approval.
- No automatic supplier debit, rider deduction or logistics-company deduction should be introduced by this policy.

The existing logistics payable must continue to use the configured company/corridor tariff and applicable bulky surcharge—not the customer's delivery fee as a fallback.

## 6. Major-road and landmark configuration policy

### 6.1 Admin-managed road hierarchy

Major roads and delivery landmarks must be configurable by authorised admin users.

Each landmark must belong to a specific major road or delivery corridor. Admin must be able to:

- Add a major road or corridor.
- Edit its display name and operational configuration.
- Add landmarks under that road.
- Edit a landmark and its parent-road association.
- Deactivate outdated roads or landmarks without deleting historical references.
- Audit who made each change and when.

A landmark must not be selectable under an unrelated road.

### 6.2 Customer checkout sequence

The address-selection workflow must follow this sequence:

1. Customer selects the major road or corridor closest to their destination.
2. The system displays only active landmarks mapped to that road.
3. Customer selects the relevant landmark.
4. Customer enters their street, house number, compound, directions and other useful address details.

The selected landmark assists routing; it does not replace the customer's actual written address.

If the customer cannot identify the correct road or landmark, the system should allow the address to be flagged for clarification or admin review. It must not silently assign the order to an incorrect corridor or bypass the configured service boundary.

### 6.3 Preserve paid-order snapshots

Changes to road names, landmark mappings, or corridor configuration must not silently change the destination or logistics tariff of an already-paid order.

At checkout and payment confirmation, preserve the relevant destination details and corridor identifiers needed to fulfil and audit that order. Any authorised correction must be recorded as an explicit change with an audit trail.

## 7. Evidence, order states and financial controls

The new policies must reuse the existing audit and money-control architecture.

### Required records

| Event | Required record |
|---|---|
| Supplier stockout | Supplier, SKU, order line, timestamp and sourcing attempts |
| Partial offer | Offer contents, issue timestamp and deadline |
| Customer response | Acceptance, rejection or timeout |
| Item cancellation | Admin action, reason, item amount and fee adjustments |
| Doorstep rejection | Rejection reason, affected items and delivery evidence |
| Return collection | Return reference, rider, item, quantity and custody evidence |
| Supplier return handover | Receiving supplier, timestamp, condition and confirmation |
| Refund request | Original payment reference, calculated amount and reason |
| Refund completion | Authorisation, provider reference and confirmed outcome |

### Required safeguards

- No duplicate cancellation, supplier debit or refund when events are retried.
- No refund completion status before provider confirmation.
- No supplier payout for goods that were not successfully delivered or otherwise validly reconciled under the applicable policy.
- No order marked fully delivered while a paid item remains unresolved.
- No silent substitution or extra customer charge.
- Every exception must have a defined owner, current status, next action and audit history.
- Repeated webhook delivery or admin retries must not create duplicate financial effects.

## 8. Documents to align

The following documents must conform to this approved addendum:

1. [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md) — primary policy authority.
2. [Operational Workflow and Exception Playbook](OPERATIONAL_WORKFLOW_AND_EXCEPTION_PLAYBOOK.md) — operational states, handovers, deadlines and evidence.
3. [Payment, Refund, Settlement and Paystack Webhook Policy](../../PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md) — refund calculations, approval and idempotency.
4. [Master Blueprint](../../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md) — architecture scope and admin-managed road/landmark hierarchy.
5. [Documentation Alignment Report](DOCUMENT_ALIGNMENT_REPORT_2026-10-22.md) — record the alignment and outstanding runtime verification.

## 9. Implementation warning

These policies must not be treated as implemented merely because documentation has been updated. A subsequent code audit must verify:

- The 24-hour timeout is idempotent and triggers whole-order cancellation handling.
- Partial cancellations calculate the correct item-level refund and bulky surcharge.
- Doorstep rejection distinguishes full rejection from item-level rejection.
- Return custody and supplier receipt are recorded before reconciliation.
- Refunds cannot be issued twice.
- Logistics payables never fall back to the customer delivery fee.
- Checkout enforces the major-road → mapped-landmark → written-address sequence.
- No removed independent-logistics-order feature remains exposed in the UI, API, database workflows or background jobs.

The 24-hour timeout is an operational decision about an unanswered partial-fulfilment offer, not a waiver of statutory consumer rights. The return policy must comply with applicable Nigerian consumer-protection law.
