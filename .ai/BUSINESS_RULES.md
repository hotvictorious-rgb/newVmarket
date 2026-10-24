# Victorious MARKET — Business Rules

> **Binding authority:** [Active Business Rules](../docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md). If any legacy detail in this file conflicts, the active rules prevail. This file is a policy reference, not a claim that runtime implementation has passed audit.

## Retailer and service boundary
- Victorious MARKET is the sole customer-facing retailer; suppliers remain private behind-the-scenes sources.
- Sourcing and delivery are restricted to the approved Uyo LGA boundary.
- Only an authorized admin may register a supplier or activate/suspend its account; supplier self-registration is not permitted. Each supplier has one admin-approved shop/pickup point anywhere inside that boundary. Plaza is optional. Suspension blocks new allocations; existing paid/in-flight orders require explicit admin review, not silent cancellation/refund. Reactivation must be authorized and audited.
- Corridors: Oron Road, Nwaniba Road, Aka Road, Abak Road, Ikot Ekpene Road, Itu Road, Ikpa Road and Plaza. Unknown/out-of-boundary destination mapping fails closed.

## Listing lifecycle
- Listing visibility/purchasability is separate from order allocation.
- Keep a listing available only while at least one eligible approved offer is explicitly in stock with sufficient quantity.
- Hide/unavailable automatically when all eligible offers are out of stock or otherwise ineligible.
- Admin may manually delist. Stock changes cannot automatically relist a manually delisted item until an authorized admin permits it.
- Preserve stock events, offer history, delisting reason, prior orders and financial records.

## Customer registration, Paystack gate and allocation
- **Mandatory Customer Account Registration:** Guests can freely browse the catalog and add items to cart, but guest checkout is strictly disabled. Customers must register or log in before initiating checkout or Paystack payment.
- Customer payment is upfront through either Paystack or direct Manual Bank Transfer; both may be enabled simultaneously, with strict per-order method binding and separate verification paths.
- Before verified payment, stock/catalogue/cart checks are non-binding. No supplier lock, order-specific reservation, binding notification or supplier payable may be created.
- After verified payment, revalidate current stock and supplier eligibility before allocation.
- Sourcing confirmation runs strictly from **8:00 AM to 6:00 PM** (60-minute supplier SLA; pauses after 6:00 PM and restarts fresh at 8:00 AM).
- Supplier eligibility: active/approved; one approved Uyo location; explicit in-stock offer with sufficient quantity; SKU-specific 24-hour unavailable cooldown expired; fewer than five active pending handovers.
- Compare only feasible plans that can fulfil the whole order: minimize distinct pickup locations first; among equally consolidated plans, minimize total wholesale cost; then prefer documented reliability and a deterministic tie-break. An order may use multiple suppliers but one rider from one approved logistics company handles the complete customer order.
- Sourcing failover / shop counter stockout breach: immediate 24-hour SKU penalty, automated failover to next eligible supplier, and alert to Super Admin. Admin maintains permanent authority to override auto-match and reassign to any eligible supplier with audit reason.

## Logistics and evidence
- Road delivery transit operates strictly from **8:00 AM to 5:00 PM** (deliveries stop by 5:00 PM for rider security and safety).
- Mandatory pre-departure phone call from rider to customer before traveling on road corridor.
- Any bulky unit makes the entire order a dedicated Keke/tricycle trip exclusive to that customer order.
- Bike batching for small orders is permitted only on the same road corridor and within all capacity/route/safety constraints.
- Supplier pickup: one-time pickup OTP plus mandatory pickup photo. Doorstep delivery: customer OTP plus mandatory geo-tagged doorstep photo.
- Up to 3 batched reattempts on corridor for customer absence with zero extra platform fee. If all 3 fail, order cancelled, items returned to suppliers; **Fair Protection Refund** refunds merchandise amount only and retains delivery fee.
- Support-managed pickup is exceptional, not public pre-payment reservation. Confirm only after complete-order readiness at one approved point, authorized payment arrangement, customer agreement and required evidence are verified.

## Money rules

## Owner-confirmed policy alignment — 24 October 2026

This section supersedes conflicting older supplier-settlement wording. It records policy decisions and does not certify implementation.

- Supplier settlement minimum: ₦1. Maximum: ₦1,000,000 per individual settlement transaction/request. No FIFO/oldest-first selection and no silent splitting.
- The initiating authorized admin may complete supplier-settlement reconciliation; a second administrator is not mandatory for that outgoing-settlement reconciliation. Audit, transaction uniqueness, idempotency, cleared funds, destination validation, ledger balancing and actual bank-outcome evidence remain mandatory.
- Admin-uploaded outgoing-transfer evidence applies to supplier settlements. Customer-uploaded incoming-transfer screenshots are separate supporting evidence for manual customer-payment review; they are never proof of actual incoming credit by themselves.
- Supplier settlements may proceed only against eligible reconciled supplier payables and cleared/reconciled platform funds, regardless of whether customer money originated through Paystack or the separately isolated manual-transfer method. Do not fabricate Paystack identities for manual transfers.
- Preserve Monday–Friday operating days, but do not make Paystack settlement a prerequisite when cleared funds came through the separately approved manual-transfer collection path.
- Legacy vendor-wallet/vendor-payout caps and FIFO rules do not govern new retailer-model supplier payables. No automated refunds or transfers are permitted.

- **The Zero-Automated-Outflow Law:** Money NEVER leaves the platform or payment gateway automatically. Under no circumstances may any server script, webhook, queue worker, cron job, or timeout autonomously trigger a Paystack refund or bank transfer.
- **100% Manual Human Super Admin Authorization:** Every customer refund (including verified duplicate charges, pre-action cancellations, out-of-stock items, or customer returns) and every bank transfer (supplier wholesale disbursements and logistics company settlements) must generate an immutable, auditable pending request in the Admin Command Tower and requires explicit review, authorization, and manual dispatch initiation by an authorized human Super Administrator.
- Customer delivery charge = one configured flat customer base fee per order, independent of corridor, + configured customer bulky surcharge per applicable physical bulky unit. The logistics company's corridor tariff is separate.
- Logistics company payable = admin-configured company/corridor base payable + that company’s configured bulky-unit surcharge per applicable physical bulky unit. These are separate calculations.
- The former “90% of customer delivery fee” payable is retired; never use it as a default/fallback. Snapshot company, corridor, tariff/version, base payable, bulky units/rate and total. If payable exceeds delivery revenue, record VM subsidy/expense. Logistics company is payee, not automatically rider.
- Supplier payable uses immutable wholesale-cost snapshots and is eligible only after verified delivery or authorized completion exception plus reconciliation. Each supplier-payable disbursement/request must be at least ₦1 and at most ₦1,000,000 per individual settlement transaction/request. Disbursements occur Monday through Friday only after the relevant platform funds are cleared and reconciled in the corporate receiving account, regardless of whether customer funds originated through Paystack or the separately isolated manual bank-transfer method. No FIFO or oldest-first allocation may be used; use an authorized, auditable non-FIFO selection process, and do not invent an automatic replacement ranking. No silent splitting of a request above the cap. Approval, reconciliation, idempotency and provider-confirmed payment are required.
- Cashback is a separate 5% eligible post-discount merchandise benefit funded by verified Paystack money, subject to existing caps/maturity/validity/reversal rules; delivery is excluded. Old 90/5/5 and vendor 90/10 splits are historical only.
- Separate customer receipts/refunds, supplier payables, logistics-company payables, VM margin, delivery revenue/cost, bulky surcharges, cashback and recovery/reconciliation.

## Paid-order exceptions and audit
- Allocation failure is not an automatic refund. Admin may reassign, hold, source an acceptable alternative, cancel or initiate refund; customer consent is required for changed product/terms. Refund is complete only after payment-provider confirmation.
- Money/status transitions must be authorized, idempotent and auditable. Retain immutable price, stock, offer, tariff and decision snapshots.
- No code was changed as part of this policy update; runtime conformity remains to be verified.


## Manual bank transfer and provider replaceability
- Admin-configured manual bank transfer is a separate tender path. Customers receive the configured Victorious MARKET bank details, exact amount and unique order code, which they should copy into the bank narration/remarks.
- The customer must upload a transfer screenshot/receipt against the order for review. Securely link it to the order and preserve evidence and review history. A rejected submission is terminal; no second payment may be attached to the same order. An authorized admin must independently verify actual credited funds and approve the payment through an auditable, concurrency-safe operation; the screenshot alone never proves payment. A transaction cannot be applied to multiple orders. Discrepancies and late payments remain on hold for review.
- No supplier confirmation/allocation, fulfilment or supplier payable may begin before the payment is verified and approved. Paystack and manual bank transfer must share payment/order safety invariants while retaining distinct provider evidence.
- Refunds for manual-transfer orders require authorized human Super Admin review and manual dispatch; never dispatch automatically. The refund destination procedure must be explicitly approved before production release. See [Manual Bank Transfer and Payment-Provider Policy](../docs/architecture/MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md).


## Exclusive payment methods and isolation
- Paystack and Manual Bank Transfer may both be active simultaneously, or neither may be active. If neither is active, checkout cannot accept payment.
- Every order is immutably bound to exactly one method. No mixed payment, top-up, cross-method verification, settlement, refund, or silent switching is allowed. Paystack references/webhooks apply only to Paystack orders; bank-credit evidence/admin approval apply only to manual-transfer orders.
- Deactivation never deletes or rewrites existing orders. Pending, unknown, disputed or potentially late payment must be reconciled before any audited reset. See [Manual Bank Transfer and Payment-Provider Policy](../docs/architecture/MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md).


## Owner-confirmed payment and settlement alignment — 24 October 2026

This section supersedes any conflicting earlier wording in this document. It records approved business policy; it does not certify runtime implementation.

### Customer payment methods: coexistence with strict per-order isolation
- Paystack and direct Manual Bank Transfer may both be enabled and offered as customer payment choices at the same time. There is no global mutual-exclusion rule. If neither is enabled, checkout must not accept payment.
- Each order/payment attempt is bound to exactly one selected method and its own evidence path. The customer may choose either method for a new order; an existing order must not silently switch methods after a payment attempt starts. A pending, unknown, disputed or potentially late payment must be reconciled before any explicitly audited resolution or reset.
- Paystack orders require trusted Paystack-side verification/webhook authentication, unique provider reference, exact order binding, amount/currency match and replay/idempotency controls. Direct manual transfers require customer receipt/screenshot submission plus independent verification of actual incoming bank credit by an authorized admin. Screenshots, emails, SMS and customer-supplied references are supporting evidence, never proof of credit by themselves.
- A single order may have only one accepted payment; a single incoming bank transaction or Paystack transaction may be applied to at most one order. Database-level uniqueness, atomic state transitions and concurrency-safe idempotency must prevent duplicate payment, duplicate paid transitions and duplicate sourcing/payable creation. Never cross-verify or represent a manual bank credit as a Paystack transaction, or vice versa.
- Manual-transfer evidence may be replaced only while the review is still open and an authorized reviewer explicitly requests replacement. Preserve all evidence versions and the review audit. Final rejection is terminal for ordinary receipt resubmission and must not accept a second payment on that order. If the customer alleges that funds were actually credited despite rejection, create a separate support investigation; do not silently reopen the order or accept a second payment. Reconcile the actual bank credit and resolve through an explicit audited exception.

### Manual outgoing bank settlements
- Supplier settlements and logistics-company settlements are both made by an authorized human through the business bank's normal transfer process; the website records and reconciles the outcome and must not claim to send money automatically.
- For each individual supplier or logistics settlement request/transfer, minimum is ₦1 and maximum is ₦1,000,000. No FIFO/oldest-first requirement, no silent splitting, and no automatic replacement ranking. Requests above the cap require an explicit separately authorized business decision; the system must not silently divide them into transfers.
- Supplier payable is eligible only after verified fulfilment or an authorized completion exception and reconciliation. Logistics payable follows the approved company/corridor tariff, bulky surcharge and completed-delivery/authorized exception rules. Both require sufficient eligible payable and cleared/reconciled platform funds. Preserve the Monday–Friday operating schedule where specified by the active operating policy.
- The initiating authorized admin may reconcile their own settlement; a second admin is not mandatory. The system must still preserve separate initiation, dispatch-recording and reconciliation events, actor identity, timestamps, amount, payee, destination, bank reference, evidence version and outcome. Admin-uploaded outgoing-transfer evidence applies to both supplier and logistics-company settlements. Evidence upload alone is not proof of completion; verify the actual bank outcome.
- Prevent duplicate bank references and duplicate settlement application. Pending, uncertain, failed or reversed transfers must not be marked settled. Reconcile the original attempt before retry; never blindly retry an uncertain transfer. No webhook, queue, cron, timeout or retry may automatically dispatch money. Ledger entries must balance and be idempotent; legacy vendor-wallet payout rules must not govern retailer-model supplier/logistics payables.

### Financial separation
- Keep customer receipts and tender type, Paystack transactions, verified manual bank credits, refunds, supplier payables, logistics-company payables, outgoing bank transfers, delivery revenue/cost, cashback and reconciliation adjustments as distinct auditable records. Only verified and reconciled funds count as cleared funds. Do not award Paystack-funded cashback to manual-transfer orders unless a separate owner-approved policy changes that rule.
- Policy documentation is not proof of implementation. Code, schema constraints, permissions, bank-verification operations and acceptance tests must be reviewed independently before release.
