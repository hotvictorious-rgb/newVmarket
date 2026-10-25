# Victorious MARKET — Return, Refund and Reverse-Logistics Chain-of-Custody Policy

**Status:** Proposed policy amendment for owner review; documentation does not certify code implementation.  
**Scope:** Uyo-only retailer operations; customer return requests, collection, inspection, internal custody transfers, supplier receipt, replacement and refund evidence.  
**Authority:** To be read with [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md) and [Manual Bank Transfer and Proactive Supplier Payment Policy](MANUAL_BANK_TRANSFER_AND_PROACTIVE_SUPPLIER_PAYMENT_POLICY_2026-10-24.md). Existing rules remain in force unless this policy explicitly adds a control.

## 1. Purpose and governing principles

Victorious MARKET is the single customer-facing retailer. Suppliers remain private sourcing partners; customers raise return requests with Victorious MARKET and must not be required to negotiate directly with an undisclosed supplier.

Every physical transfer of a returned item must create an auditable custody event. Each event must identify the order and return case, the item(s), the releasing and receiving parties, event time, event-specific verification result, and mandatory photo evidence. OTP acknowledgement, photographs, inspection and financial decisions serve different purposes and must not be treated as interchangeable proof.

- OTP confirms acknowledgement of a particular handover; it does not by itself prove item identity, condition, fault, refund eligibility or bank settlement.
- Photos document observed condition and custody; they do not by themselves prove who caused damage or prove that money moved.
- Admin adjudication remains required. No return request, collection OTP, supplier receipt or uploaded image may autonomously issue a refund, create a supplier recovery debt or settle a financial ledger.
- Missing, conflicting or failed evidence must remain visible as an exception. The system must never silently advance an unverified handover to success.

## 2. Return eligibility and request intake

- The normal return-request window remains 24 hours after confirmed customer delivery for eligible defective, damaged or non-conforming items, subject to the active non-returnable category rules. An authorized admin may review an exception outside the normal window; the exception, reason, actor and timestamp must be recorded.
- The customer opens the request through Victorious MARKET and selects a reason. Require a description and clear photos of the item, alleged defect/damage, product identifiers where available, accessories and packaging when relevant. Video may be accepted where supported by the system.
- Link every upload to the authenticated customer, order, order line and return case. Store files privately with access controls and integrity metadata; do not expose private evidence through public URLs.
- Preserve original delivery photos, OTP event, timestamp and item identifiers so investigators can compare the original condition with return evidence.
- Submission opens a case; it does not mean the return is approved. An authorized admin reviews evidence and records approval, rejection, request for more information or investigation.

## 3. Mandatory physical custody events

### 3.1 Collection from the customer

Before marking collection successful, the authorized collector must:
1. Confirm the correct order and approved return case server-side.
2. Match the item to the order/return line using product/model and serial/IMEI or other unique identifier where available.
3. Capture mandatory photos of the actual item and its visible condition, relevant accessories and packaging, where applicable.
4. Obtain the customer's event-specific return-release OTP.
5. Record the authenticated collector, timestamp, outcome and any discrepancy.

The collector must not accept an unrelated or mismatched item merely because an OTP was provided. If the item does not match, the customer refuses release, the OTP is invalid/expired, or required evidence cannot be captured, do not record successful collection. Record the failed attempt and escalate or reschedule.

### 3.2 Transit and custody responsibility

After verified collection, assign the returned item to a named/identified authorized logistics actor and record the custody state. Every later handover must close the prior custody responsibility and open the next one atomically. The same return item must not be simultaneously marked as held by two parties. Failed, delayed, lost or disputed transit must remain in an exception state and alert authorized operations staff.

Apply existing Uyo-only logistics rules. If any return shipment contains an item classified as bulky, apply the active bulky-contagion dispatch rule: the whole return trip requires the approved dedicated bulky-capable vehicle and must not be combined with another customer's order where the rule prohibits batching.

### 3.3 Receipt at an inspection/receiving point

At the designated approved receiving or inspection point, the authorized receiver must:
1. Confirm the order and return case.
2. Match the item and identifiers against collection records.
3. Capture mandatory arrival photos and record visible condition, packaging, seals and accessories as applicable.
4. Complete a separate, event-specific receipt OTP/verification with the authorized releasing/receiving participants under the configured workflow.
5. Record the receiving actor, timestamp, discrepancies and outcome.

Do not mark the return as received if the item is missing, mismatched, damaged in a disputed way, or mandatory evidence/verification is absent. Use a documented exception path instead.

### 3.4 Handover back to a supplier

Supplier receipt is required only when the item is actually being returned to that supplier. The supplier remains an internal business partner, not the customer's counterparty.

- Verify the approved supplier and its approved Uyo location, and verify that the person accepting the item is authorized for that supplier.
- Match product identifiers and capture mandatory photos of the item and condition at the point of handover.
- Record a supplier-specific, event-specific receipt OTP/verification and timestamp.
- If the supplier refuses receipt, is unavailable, disputes identity/condition or cannot complete verification, record an attempted/failed handover—not a successful supplier receipt—and notify admin.
- A rider's statement, GPS point, photo of a shopfront or outbound dispatch scan alone must not be treated as supplier acceptance.

### 3.5 Replacement dispatch and redelivery

A replacement is a separate fulfilment event linked to the original order and return case. Admin must authorize the replacement outcome. Record the replacement product identity and pre-dispatch photos, then apply the existing supplier pickup proof and customer final-delivery OTP/photo requirements. Supplier dispatch alone is not customer delivery. Do not charge the customer an unapproved extra amount or silently substitute a product.

## 4. OTP security and exception controls

- Each OTP must be bound server-side to a specific order/return case, handover event, authorized participant/role and intended transition.
- OTPs must be random, time-limited, single-use, rate-limited, and invalidated after successful use or cancellation of the event. A code from original delivery must never authorize a return handover. Do not log or display raw OTPs in ordinary audit logs.
- Verify OTPs and transition preconditions on the server inside an atomic transaction with locking/uniqueness safeguards. Concurrent requests, retries and replay must not create duplicate custody events or advance the state twice.
- Do not expose a successful handover API that accepts client-supplied status alone. The backend must verify authorization, correct order/item, expected current custody state, OTP validity, mandatory evidence and idempotency key/event uniqueness.
- If OTP delivery or verification is unavailable, only an authorized admin may approve a documented exception with reason, participant identity, timestamp and independent supporting evidence. The exception must be visibly distinct from OTP-verified completion and must not be used to bypass item identity, custody or financial controls.
- Never accept an OTP as proof that the product is in good condition, that a supplier is at fault, or that a refund has been paid.

## 5. Evidence, audit trail and privacy

Each custody event should retain, at minimum:
- Unique event ID; order ID, order line ID and return case ID.
- Event type, previous and resulting custody states, success/failure/exception outcome.
- Releasing and receiving actor IDs/roles and authorized supplier/logistics identity where applicable.
- Server timestamp; client-reported time only as supplementary metadata.
- OTP verification outcome and safe verification metadata (never the raw OTP).
- Mandatory private photo evidence references, upload time, uploader identity and integrity hash where supported.
- Product identifier checks, observed condition, accessories/packaging notes and discrepancy reason.
- Admin overrides, decisions and immutable audit entries.

Restrict evidence access by role and business need. Do not collect unrelated faces, identity documents or location data. Define retention and deletion controls consistent with legal/accounting needs. Do not allow an uploader to overwrite the original evidence silently; corrections must be appended and audited.

## 6. Admin adjudication and financial separation

- Admin reviews the complete evidence chain and decides whether to approve a refund, replacement, further inspection, rejection or another policy-authorized outcome.
- The return case, order fulfilment state, supplier recovery/chargeback decision, refund instruction and financial reconciliation are distinct records/states.
- Do not automatically create a supplier debt solely because a customer alleges damage or a return is collected. Record a recovery only after an authorized, evidenced decision and correct accounting treatment.
- Do not mark a customer refund fully completed merely because an outgoing transfer receipt was uploaded or a bank transfer was initiated. For Paystack, use the order's pinned Paystack refund path and trusted provider confirmation. For manual-bank-transfer orders, use the separately authorized manual bank-refund process and reconcile the actual bank outcome. Never cross payment rails.
- An external bank transfer may be shown as dispatched/awaiting reconciliation until the actual outcome is verified. Keep dispatch and reconciliation statuses distinct. A receipt image alone does not prove the customer received the funds.
- Supplier payables remain subject to the active rules: verified fulfilment, the 24-hour return/dispute window, no unresolved hold, and financial reconciliation. A supplier's receipt of a returned item must trigger the appropriate hold/review; it must not automatically finalize a payable or refund.
- Preserve double-entry equality, immutable journals, idempotency and evidence links. No automated outflow is permitted.

## 7. Required user-visible states

Use clear states that reflect verified facts, including as applicable:
- Return requested / awaiting admin review
- More evidence requested
- Return approved / collection to be scheduled
- Collection attempted / failed
- Collected from customer / in transit
- Inspection point received / inspection pending
- Supplier handover attempted / failed
- Received by supplier
- Admin decision pending / return rejected / replacement approved / refund approved
- Refund initiated / dispatched awaiting reconciliation / reconciled complete / failed or under investigation

Exact enum names must be aligned with the existing database and API before implementation. Do not introduce states in UI only without backend persistence, transition rules and tests.

## 8. Required acceptance tests before launch

Run against the production-target MySQL 8.4/InnoDB configuration, in addition to appropriate unit tests. SQLite-only success is not sufficient for concurrency or migration proof.

1. Valid OTP + correct order/item + mandatory photos records one successful handover.
2. Wrong, expired, reused, cross-order, cross-event and unauthorized-role OTPs are rejected.
3. Two concurrent requests using the same OTP produce at most one successful custody transition/event.
4. Retrying after a timeout does not duplicate events, notifications, recovery entries or refund instructions.
5. Missing photos, failed upload, mismatched item/serial, missing receiver or failed OTP cannot mark a handover successful.
6. Customer collection, inspection-point receipt and supplier receipt each require separate event evidence and distinct state transitions.
7. Supplier unavailable/refusal and customer absent/refusal create failed-attempt records and do not falsely advance custody.
8. A lost item or conflicting condition evidence creates an admin exception and preserves the last verified custodian.
9. Bulky return transport obeys the exclusive bulky dispatch rule; Uyo-only and approved-location restrictions remain enforced.
10. Replacement dispatch cannot mark the replacement delivered; customer delivery proof is required.
11. Return approval alone does not dispatch a refund; uploaded transfer evidence alone does not mark a manual refund reconciled.
12. Paystack and manual-bank-transfer refund paths remain isolated by the original order's pinned rail.
13. A return/dispute hold prevents supplier payable eligibility until the authorized resolution and required reconciliation.
14. Admin exception/override is role-protected, reasoned, timestamped and distinguishable from verified OTP completion.
15. Crash/restart, transaction rollback, duplicate uploads and concurrent admin decisions preserve custody uniqueness and financial ledger balance.
16. Photo access controls, upload validation, evidence integrity, retention and audit history are verified.

## 9. Rollout rule

This document is a policy specification, not proof that the application implements it. Before production enablement, map every requirement to concrete Laravel routes/controllers/services, database constraints, authorization policies, storage configuration, UI and automated tests. Run migrations and tests on MySQL 8.4/InnoDB, inspect the actual CI run for the exact commit, and document any remaining gaps. Do not merge or deploy on the strength of documentation alone.
