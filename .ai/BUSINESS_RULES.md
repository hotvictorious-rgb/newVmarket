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
- Delivery payment is upfront through an enabled approved method: Paystack (trusted provider verification/webhook, reference, amount, currency and idempotency) or Manual Bank Transfer (customer uploads a transfer screenshot/receipt for the order; an authorized admin independently verifies actual receiving-account credit and explicitly approves it with audit evidence). The screenshot is mandatory supporting evidence but never proof of credited funds by itself. Securely link uploads to the order, preserve evidence versions/review history, and keep the order unpaid until actual credit is verified. Manual transfer is not a Paystack transaction.
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

This section supersedes any conflicting older supplier-settlement wording in this document. It records policy decisions; it does not certify implementation.

- **Supplier settlement amount:** minimum ₦1 and maximum ₦1,000,000 per individual settlement transaction/request. A valid request still requires sufficient eligible reconciled payable, authorization, destination validation and all financial safeguards. The cap does not authorize automatic splitting; no FIFO/oldest-first selection or silent splitting is allowed.
- **Single-admin reconciliation:** the initiating authorized admin may complete reconciliation; a second administrator is not mandatory. The same-admin path must still require evidence of the actual outgoing bank outcome, immutable actor/timestamp/amount/payee/destination/reference/evidence records, transaction-reference uniqueness, idempotent state transitions, ledger reconciliation and safe handling of pending, uncertain, failed or reversed transfers. Evidence upload alone is not proof of payment.
- **Evidence scope:** admin-uploaded outgoing-transfer evidence applies to supplier settlements. Customer-submitted incoming-transfer screenshots belong only to the separate manual customer-payment review workflow; they are supporting evidence and never prove incoming credit by themselves.
- **Funding and settlement rail neutrality:** supplier settlement eligibility depends on reconciled, cleared platform funds and eligible supplier payables, not on the customer payment provider being Paystack specifically. Manual bank-transfer customer receipts must not be represented as Paystack transactions. Preserve separate customer-tender records while using the same audited supplier-payable controls.
- **Legacy separation:** historical vendor-wallet/vendor-payout limits and FIFO logic must not govern new retailer-model supplier payables. Legacy records remain preserved and must be reconciled through their separately authorized migration/exception path.
- **No automated outflow:** no webhook, queue, cron, timeout or retry may autonomously send money. Human authorization and controlled manual dispatch remain required. An uncertain transfer must be reconciled before retry to prevent duplicate payment.


## Owner-confirmed policy alignment — 24 October 2026

This section supersedes any conflicting older supplier-settlement wording in this document. It records policy decisions; it does not certify implementation.

- **Supplier settlement amount:** minimum ₦1 and maximum ₦1,000,000 per individual settlement transaction/request. A valid request still requires sufficient eligible reconciled payable, authorization, destination validation and all financial safeguards. The cap does not authorize automatic splitting; no FIFO/oldest-first selection or silent splitting is allowed.
- **Single-admin reconciliation:** the initiating authorized admin may complete reconciliation; a second administrator is not mandatory. The same-admin path must still require evidence of the actual outgoing bank outcome, immutable actor/timestamp/amount/payee/destination/reference/evidence records, transaction-reference uniqueness, idempotent state transitions, ledger reconciliation and safe handling of pending, uncertain, failed or reversed transfers. Evidence upload alone is not proof of payment.
- **Evidence scope:** admin-uploaded outgoing-transfer evidence applies to supplier settlements. Customer-submitted incoming-transfer screenshots belong only to the separate manual customer-payment review workflow; they are supporting evidence and never prove incoming credit by themselves.
- **Funding and settlement rail neutrality:** supplier settlement eligibility depends on reconciled, cleared platform funds and eligible supplier payables, not on the customer payment provider being Paystack specifically. Manual bank-transfer customer receipts must not be represented as Paystack transactions. Preserve separate customer-tender records while using the same audited supplier-payable controls.
- **Legacy separation:** historical vendor-wallet/vendor-payout limits and FIFO logic must not govern new retailer-model supplier payables. Legacy records remain preserved and must be reconciled through their separately authorized migration/exception path.
- **No automated outflow:** no webhook, queue, cron, timeout or retry may autonomously send money. Human authorization and controlled manual dispatch remain required. An uncertain transfer must be reconciled before retry to prevent duplicate payment.

- **The Zero-Automated-Outflow Law:** Money NEVER leaves the platform or payment gateway automatically. Under no circumstances may any server script, webhook, queue worker, cron job, or timeout autonomously trigger a Paystack refund or bank transfer.
- **100% Manual Human Super Admin Authorization:** Every customer refund (including verified duplicate charges, pre-action cancellations, out-of-stock items, or customer returns) and every bank transfer (supplier wholesale disbursements and logistics company settlements) must generate an immutable, auditable pending request in the Admin Command Tower and requires explicit review, authorization, and manual dispatch initiation by an authorized human Super Administrator.
- Customer delivery charge = one configured flat customer base fee per order, independent of corridor, + configured customer bulky surcharge per applicable physical bulky unit. The logistics company's corridor tariff is separate.
- Logistics company payable = admin-configured company/corridor base payable + that company’s configured bulky-unit surcharge per applicable physical bulky unit. These are separate calculations.
- The former “90% of customer delivery fee” payable is retired; never use it as a default/fallback. Snapshot company, corridor, tariff/version, base payable, bulky units/rate and total. If payable exceeds delivery revenue, record VM subsidy/expense. Logistics company is payee, not automatically rider.
- Supplier payable uses immutable wholesale-cost snapshots and is eligible only after verified delivery or authorized completion exception plus reconciliation. Each supplier-payable disbursement/request must be at least ₦1 and at most ₦1,000,000 per individual settlement transaction/request. Disbursements occur only after the relevant platform funds are cleared and reconciled in the corporate receiving account, regardless of whether customer funds originated through Paystack or the separately isolated manual bank-transfer method. Any operating-day schedule must be enforced consistently by the settlement procedure, not by assuming all funds came from Paystack. No FIFO or oldest-first allocation may be used; use an authorized, auditable non-FIFO selection process, and do not invent an automatic replacement ranking. No silent splitting of a request above the cap. Approval, reconciliation, idempotency and provider-confirmed payment are required.
- Cashback is a separate 5% eligible post-discount merchandise benefit funded by verified Paystack money, subject to existing caps/maturity/validity/reversal rules; delivery is excluded. Old 90/5/5 and vendor 90/10 splits are historical only.
- Separate customer receipts/refunds, supplier payables, logistics-company payables, VM margin, delivery revenue/cost, bulky surcharges, cashback and recovery/reconciliation.

## Paid-order exceptions and audit
- Allocation failure is not an automatic refund. Admin may reassign, hold, source an acceptable alternative, cancel or initiate refund; customer consent is required for changed product/terms. Refund is complete only after payment-provider confirmation.
- Money/status transitions must be authorized, idempotent and auditable. Retain immutable price, stock, offer, tariff and decision snapshots.
- No code was changed as part of this policy update; runtime conformity remains to be verified.


## Manual bank transfer and provider replaceability
- Admin-configured manual bank transfer is a separate tender path. Customers receive the configured Victorious MARKET bank details, exact amount and unique order code, which they should copy into the bank narration/remarks.
- The customer must upload a transfer screenshot/receipt against the order for review. Securely link it to the order and preserve evidence versions and review history. An authorized admin must independently verify actual credited funds and approve the payment through an auditable, concurrency-safe operation; the screenshot alone never proves payment. A transaction cannot be applied to multiple orders. Discrepancies and late payments remain on hold for review.
- No supplier confirmation/allocation, fulfilment or supplier payable may begin before the payment is verified and approved. Paystack and manual bank transfer must share payment/order safety invariants while retaining distinct provider evidence.
- Refunds for manual-transfer orders require authorized human Super Admin review and manual dispatch; never dispatch automatically. The refund destination procedure must be explicitly approved before production release. See [Manual Bank Transfer and Payment-Provider Policy](../docs/architecture/MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md).


## Exclusive payment methods and isolation
- Only one customer payment method may be active globally: Paystack or Manual Bank Transfer, or neither. Admin activation is atomic and concurrency-safe; the server must reject dual-enabled configuration. If neither is active, checkout cannot accept payment.
- Every order is immutably bound to exactly one method. No mixed payment, top-up, cross-method verification, settlement, refund, or silent switching is allowed. Paystack references/webhooks apply only to Paystack orders; bank-credit evidence/admin approval apply only to manual-transfer orders.
- Deactivation never deletes or rewrites existing orders. Pending, unknown, disputed or potentially late payment must be reconciled before any audited reset. See [Manual Bank Transfer and Payment-Provider Policy](../docs/architecture/MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md).
