# Victorious MARKET — Active Business Rules and Documentation Source of Truth

## Owner-confirmed policy alignment — 24 October 2026

This section supersedes conflicting older supplier-settlement wording. It records policy decisions and does not certify implementation.

- Supplier settlement minimum: ₦1. Maximum: ₦1,000,000 per individual settlement transaction/request. No FIFO/oldest-first selection and no silent splitting.
- The initiating authorized admin may complete supplier-settlement reconciliation; a second administrator is not mandatory for that outgoing-settlement reconciliation. Audit, transaction uniqueness, idempotency, cleared funds, destination validation, ledger balancing and actual bank-outcome evidence remain mandatory.
- Admin-uploaded outgoing-transfer evidence applies to both supplier and logistics-company settlements. Customer-uploaded incoming-transfer screenshots are separate supporting evidence for manual customer-payment review; they are never proof of actual incoming credit by themselves.
- Supplier settlements may proceed only against eligible reconciled supplier payables and cleared/reconciled platform funds, regardless of whether customer money originated through Paystack or the separately isolated manual-transfer method. Do not fabricate Paystack identities for manual transfers.
- Preserve Monday–Friday operating days, but do not make Paystack settlement a prerequisite when cleared funds came through the separately approved manual-transfer collection path.
- Legacy vendor-wallet/vendor-payout caps and FIFO rules do not govern new retailer-model supplier payables. No automated refunds or transfers are permitted.

**Decision date:** 22 October 2026  
**Scope:** Business policy and documentation only. This document does not certify code implementation.

This document records the owner's active decisions. If older documents conflict with these rules, these rules take precedence until those documents are corrected. It does not authorize code changes by itself.

## 1. Retailer and service area
- Victorious MARKET is the single customer-facing retailer, operating within Uyo LGA, Akwa Ibom State.
- Suppliers remain behind the scenes. Customers buy from Victorious MARKET, not from individual supplier storefronts.
- Only an authorized Victorious MARKET admin may register/create a supplier account; suppliers cannot self-register or independently activate their own accounts. Only an authorized admin may approve, activate or suspend a supplier. Each supplier may operate from one admin-approved shop or pickup location anywhere within the approved Uyo operating boundary. Plaza is one possible location, not a mandatory hub.
- A suspended supplier is immediately ineligible for new sourcing/allocation. Suspension must not silently erase records or automatically cancel/refund already-paid orders; an authorized admin must review and explicitly resolve affected in-flight assignments. Reactivation requires an authorized admin action and a recorded audit trail.
- The configured eight delivery corridors remain Oron Road, Nwaniba Road, Aka Road, Abak Road, Ikot Ekpene Road, Itu Road, Ikpa Road and Plaza. The admin must maintain unambiguous landmark-to-corridor mappings. Unknown or out-of-boundary destinations fail closed.

## 2. Product listing lifecycle is separate from order allocation
- A product listing is a customer-facing catalogue record; it is not a supplier assignment or reservation for a particular order.
- A listing can be visible and purchasable only while at least one eligible, approved supplier offer for that product is explicitly in stock and otherwise eligible under the sourcing rules.
- When every eligible supplier offer for the product is out of stock or otherwise ineligible, automatically hide the listing from the customer-facing storefront / make it unavailable for purchase.
- An authorized admin may manually delist a product at any time, even if suppliers report stock.
- When a supplier changes stock, the system should recompute listing availability. Restoring an eligible in-stock offer may make the listing visible again only if the admin has not manually delisted it and other listing rules pass.
- Keep the product record, offer history, stock events and delisting reason for audit; hiding a listing should not erase historical orders or financial records.
- Listing-time availability checks must not bind a supplier to a customer's future order.

## 3. Customer registration, mutually exclusive payment rail, and payment before sourcing

- **Mandatory Customer Account Registration:** Guests may browse and add to cart, but must register/log in before checkout. Orders must be bound to an authenticated customer.
- **One active customer collection mode:** The Super Admin may configure either `manual_bank_transfer` or `paystack` as the active customer payment mode for new orders. They must never both be enabled for new orders at the same time. Changing the setting affects new orders only; every existing order is permanently pinned to the mode recorded when that order was created. Never settle one order through both modes, switch an order's mode after evidence/submission, or let a second payment satisfy an already-paid order.
- **Owner's selected operating direction:** Use manual bank transfer as the active customer collection mode when operating without Paystack. Paystack checkout/webhook verification must not run for those new manual-transfer orders. Manual customer collection and manual supplier bank transfers are distinct workflows and must not call Paystack's transfer API.
- **Manual bank-transfer evidence:** Give each order a unique order/payment reference and show the approved Victorious MARKET receiving-account details. Ask the customer to include the exact order reference in transfer narration and upload a payment screenshot/receipt. Keep the uploaded evidence private and access-controlled. Screenshot, email, OCR result, customer assertion, browser callback, or a typed transaction ID alone is not proof that funds arrived.
- **Human verification against actual funds:** An authorized admin must match the transfer to the actual receiving-bank transaction/statement or trusted bank evidence, including amount, currency, transaction reference, date/time, receiving account and available sender details. Only a confirmed credit for the exact expected amount may be approved as payment. An incorrect, incomplete, duplicate, untraceable or mismatched submission is rejected or held for manual investigation; it never marks the order paid.
- **One transaction ↔ one order:** Enforce database-backed uniqueness for the receiving-bank transaction identifier/reference and for the accepted payment against an order. A single bank credit may not settle two orders, and an order may have at most one accepted successful payment. Duplicate submissions/retries must be idempotent. Preserve rejected attempts and evidence for audit. Unmatched, underpaid, overpaid, reversed or ambiguous bank credits enter a manual exception queue; they must not trigger sourcing.
- **Admin review audit:** Record the reviewing admin, decision, reason, timestamp, amount, currency, order reference, bank transaction reference, evidence location/hash and relevant before/after state. Screenshots are evidence for review, not a substitute for bank reconciliation.
- **Payment-before-sourcing gate:** Supplier assignment, binding availability confirmation, supplier payable creation and fulfilment handoff are prohibited until the backend has committed a verified successful payment state for the order's pinned rail. For Paystack-pinned legacy orders, use the existing trusted provider verification/webhook/reference/idempotency rules. For manual-transfer orders, use the human bank-credit verification above. Pre-payment catalogue/feasibility checks remain non-binding.
- After verified payment, revalidate supplier/offer eligibility and request order-specific confirmation of the exact quantity for each paid line. Declines, inability and qualifying timeouts trigger bounded, idempotent failover. If candidates are exhausted, move the paid order/line to the admin-managed exception process.

## 4. Supplier allocation & Confirmation SLA
- **Operating Hours:** Sourcing confirmation timers run strictly from **8:00 AM to 6:00 PM**. Suppliers have exactly **60 minutes** to confirm stock readiness.
  - After 6:00 PM, timers pause; merchants can see incoming orders, but 60-minute confirmation countdowns **restart fresh at 8:00 AM** the next morning.
- Apply hard eligibility checks first: supplier active, approved and verified; approved location within Uyo; supplier product's binary catalog signal is explicitly **In stock**; availability confirmation is fresh; applicable 24-hour SKU-specific cooldown has expired; supplier is below the limit of five active pending handovers; and wholesale/source cost is strictly below retail price. Do **not** require or infer a permanent supplier quantity field. After verified payment, the supplier must explicitly confirm it can fulfill the exact quantity on the paid order line.
- Compare only feasible plans that can fulfil the whole customer order. First minimize the number of distinct supplier pickup locations; among plans using the same minimum number of locations, minimize total wholesale cost; then prefer documented fulfilment reliability and apply a stable deterministic tie-break.
- A customer order may use multiple supplier locations, but the whole customer order remains assigned to one rider from one approved logistics company.
- **Failover, Counter Breaches & Admin Override:**
  - If a supplier rejects, times out after 60 minutes, or commits a shop-floor breach (selling an already-confirmed item to a walk-in cash customer at pickup), the system immediately applies a **24-hour SKU penalty cooldown** to that supplier, **auto-routes** to the next best eligible supplier in Uyo, and alerts the Super Admin Command Tower.
  - **Super Admin Override Authority:** The auto-match can ALWAYS be overridden by an authorized Super Admin, who has permanent authority to manually reassign the line item to any other eligible supplier in Uyo with a recorded audit reason. Overrides cannot bypass verified payment or safety invariants.

## 5. Delivery transit, proof & Failed handovers
- **Delivery Operating Window:** Physical road transit operates strictly from **8:00 AM to 5:00 PM**. Deliveries stop by 5:00 PM to ensure rider safety, security, and visibility along Uyo road corridors.
- Mandatory pre-departure phone call: rider must call the customer before traveling down the road corridor.
- Small orders may be batched on a bike only when they meet the same-road-corridor and all other capacity/route rules.
- If any item is bulky, the entire order requires a dedicated Keke/tricycle trip exclusive to that customer order. Do not add another customer's order to that trip.
- Require a unique pickup OTP and mandatory photo proof at each supplier pickup location, then customer delivery OTP and mandatory geo-tagged doorstep photo for final delivery.
- Support-managed pickup is an exceptional admin/support workflow only when the entire order is ready at one approved collection point, payment handling is authorized, the customer agrees and verification/photo evidence is recorded. It is not a public reservation or pre-payment hold feature.
- **Failed Handover & Fair Protection Refund:**
  - If customer is absent, package returns to 3PL logistics secure holding custody.
  - Up to **3 delivery attempts** are permitted, batched along that same corridor on future runs with zero extra platform fee.
  - If all 3 attempts fail, the package is returned to originating suppliers and order cancelled.
  - **Fair Protection Refund Rule:** Super Admin authorizes and executes a refund for the **merchandise amount only**. The **customer delivery fee is retained** by Victorious MARKET to cover the logistics costs of 3 physical delivery attempts.

## 6. Two separate delivery money paths & Flat customer fee integrity
- Customer delivery charge: one configured flat customer base fee per customer order, independent of the logistics corridor, plus the configured customer bulky surcharge per applicable physical bulky unit. Do not use the logistics company's corridor tariff to calculate the customer's fee.
- **Zero Multi-Stop Customer Surcharges:** Customers are NEVER charged extra delivery fees for orders sourced from multiple shops. Victorious MARKET absorbs any multi-stop 3PL corridor tariff differential as a platform expense/subsidy.
- Logistics-company payable: admin-configured base payable for the applicable corridor and assigned logistics company, plus that company's configured bulky-unit surcharge per applicable bulky unit.
- These are independent tariff schedules. The logistics-company payable is not a percentage of the customer delivery fee. The former 90% delivery-fee rule is retired and must never be used as a fallback.
- Snapshot the applicable corridor, company, tariff/version, base payable, bulky quantity/rate and total payable at assignment. Later tariff changes must not silently alter an existing payable.
- If company payable exceeds customer delivery revenue, record the difference explicitly as a Victorious MARKET-funded subsidy/expense. Pay the logistics company, not automatically the individual rider. Overrides require authorization, reason and immutable audit.

## 7. Paid-order fulfilment exceptions, Returns & Admin adjudication
- If verified payment succeeds but supplier allocation or fulfilment fails, do not automatically refund solely because no supplier is immediately available.
- Move the order into a visible admin-managed exception state, preserve its paid status and financial trace, alert the admin and show aging/escalation information.
- Authorized admin may reassign the order to another eligible supplier, source an acceptable alternative, hold fulfilment, cancel or initiate a refund according to policy.
- Do not silently substitute a different product or impose an additional charge. Where product, price or agreed terms change, obtain customer consent as required by policy.
- **Strict Return Adjudication & Non-Returnable Goods:**
  - 24-hour return window applies for defective/damaged non-conforming items.
  - Not all returns or refunds are approved: Super Admin individually reviews photographic/video evidence and adjudicates each case.
  - **Strictly Non-Returnable Categories:** Perishable fresh food/groceries, personal hygiene/underwear, unsealed beauty products, and opened digital vouchers/scratch cards are non-returnable once verified delivery OTP is completed.
- A refund is not marked complete until the payment provider confirms it. Record actor, reason, timestamps, order/payment references, amount and refund status. Keep all actions auditable and idempotent.

## 8. Financial integrity & The Zero-Automated-Outflow Law

- **The Zero-Automated-Outflow Law:** Money NEVER leaves the platform automatically. No server script, webhook, queue worker, cron job or timeout may autonomously dispatch a customer refund, supplier bank transfer or logistics-company bank transfer.
- **Manual transfer initiation:** Every refund or outgoing bank transfer must be an immutable, auditable pending action requiring explicit review and manual initiation by an authorized human Super Administrator. For manual supplier payments, the admin initiates the transfer from Victorious MARKET's business bank account using the supplier's verified bank details; Paystack transfer APIs are not used.
- **Proactive supplier payment; no supplier request requirement:** Supplier payable eligibility is calculated by the system and presented in an admin-controlled payout queue. The supplier does not need to submit a payout request. The admin proactively selects eligible payable items and initiates payment. The system must not pay merely because a supplier asks, and a supplier request must not be the prerequisite for payment.
- Supplier payables use immutable agreed wholesale-cost snapshots and become eligible only after verified delivery, expiry of the applicable 24-hour return/dispute window, no unresolved dispute/return/recovery hold, and successful financial reconciliation—or an explicitly authorized completion exception followed by reconciliation. Supplier stock acceptance or handover alone does not make a payable due.
- **Supplier payable amount:** no artificial minimum or maximum applies. Pay the exact total of eligible payable items explicitly selected and approved by the Super Admin, bounded by verified reconciled available funds, beneficiary verification, existing holds/recovery debts, and ledger integrity. Do not silently omit eligible payable amounts or split a payment to satisfy an arbitrary threshold.
- **Manual payout evidence and idempotency:** Before initiation, recheck beneficiary bank name/account/bank details, amount, eligible selected items, existing reservations, recovery debts and available reconciled business funds. Record the initiating admin, approved amount, selected payable IDs, bank transaction reference, timestamp and proof. Mark the payout paid only after independently checking the actual outgoing debit and beneficiary transfer outcome. An ambiguous/pending bank transfer is held for reconciliation and must never be blindly retried. Prevent duplicate dispatch of the same payout and duplicate settlement of the same payable item.
- **Funding and schedule:** Supplier and logistics payments are manually initiated Monday through Friday only after the corporate bank balance/statement and the relevant receipts/payables have been reconciled. This rule does not require Paystack settlement when manual bank transfer is the active customer collection mode. Do not assume an incoming screenshot equals available funds.
- **No FIFO:** Do not use FIFO, oldest-earning-first, oldest-order-first or automatic FIFO allocation to decide which eligible supplier payables are included in a payment. Selection must be an authorized, auditable non-FIFO process. Do not invent an automatic alternative ranking/selection algorithm without owner approval.
- Keep customer receipts, refunds, supplier payables, logistics-company payables, retailer margin, delivery revenue/costs, bulky surcharges, cashback and recovery/reconciliation as distinct auditable money paths.
- Cashback remains a separate 5% loyalty benefit on eligible post-discount merchandise actually funded by verified, reconciled customer money through the order's pinned payment rail, subject to existing caps, maturity and reversal rules. Do not credit cashback on screenshots, rejected/unmatched transfers, or unverified Paystack events. Former 90/5/5 merchandise splits and vendor-managed delivery allocations are historical only.
- All sensitive assignments, overrides, refunds, tariff changes and financial adjustments require role-based authorization, a reason where applicable, and immutable audit records.

## 9. Security, Cryptography and Anti-Fraud Guardrails

- **Payment-mode isolation:** Enforce the mutually exclusive active customer payment mode server-side. Every order is pinned to one rail; routes, callbacks, webhooks and admin actions must reject attempts to settle it through the other rail. Do not treat disabling a rail as permission to abandon unresolved orders already pinned to it.
- **Paystack mode only:** When Paystack is the pinned rail, retain HMAC-SHA512 verification on Paystack webhooks, trusted server-side verification, amount/currency/reference checks and atomic idempotency controls.
- **Manual bank-transfer mode:** Keep receipt images private; validate file type/size and safely store uploads; enforce authenticated upload/review, CSRF/RBAC, transaction-reference uniqueness and audit history. Never auto-approve using image contents, screenshots, email text, OCR, client-side state or a customer-supplied reference alone. Approval must be backed by verified actual bank-credit evidence.
- **Financial database invariants:** Uniquely bind one accepted incoming bank transaction to one order and one accepted payment to one order. Enforce these rules in the database as well as application code; serialize concurrent approvals and replays; preserve rejected/duplicate evidence without creating a second payment or supplier allocation.
- **Physical Handshake Cryptography:** 4-digit Shop Pickup OTP shifting liability to 3PL; 6-digit Customer Delivery OTP; 3-attempt brute-force OTP lockout with 15-minute freeze and Super Admin alert; mandatory live camera enforcement (`capture="environment"`), blocking gallery uploads.
- **Role-Based Access Control & Redaction:** Strict RBAC separating roles; complete redaction of product retail prices, wholesale costs, and platform profit margins from rider interfaces; brute-force login throttling (max 5 attempts per minute per IP).
- **Browser & Network Hardening:** Content-Security-Policy (CSP) restricting scripts; `X-Frame-Options: SAMEORIGIN` clickjacking defense; `X-Content-Type-Options: nosniff`; HSTS HTTPS enforcement; CSRF token validation on all web forms.
- **Backstage No-Index Isolation:** `robots.txt` disallows `/admin/`, `/vendor/`, `/supplier/`, `/logistics/`, `/rider/`, and `/api/`; authenticated portal views inject `<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">`; `X-Robots-Tag: noindex, nofollow` HTTP headers. Storefront is exclusively optimized for SEO.
- **Immutable Audit Trail:** Append-only logging of all administrative actions with actor, IP, timestamp, action code, reason code, and before/after state snapshots.

## 10. Documentation versus implementation
These are the active business decisions to align across documentation. Code, schema, admin UI, workflows and tests must be audited separately and must not be claimed aligned merely because the documents are updated. Until a separate implementation audit and required tests pass, implementation status remains unverified.


## Approved policy addendum — 23 October 2026

The owner-approved [Exception and Reverse-Logistics Policies](APPROVED_EXCEPTION_AND_REVERSE_LOGISTICS_POLICIES_2026-10-23.md) are binding additions to these Active Business Rules. They govern supplier-stockout recovery, admin-managed partial fulfilment, the 24-hour response deadline (silence means rejection of the partial offer), item-level and whole-order cancellation/refund handling, doorstep rejection, post-purchase defective-item returns on compatible return routes, no redelivery, removal of independent logistics-company orders from platform scope, admin-configurable major-road/landmark hierarchy, and the related evidence and financial safeguards. Dependent documents must conform to that addendum. Documentation alignment does not certify runtime implementation.



## Owner-approved supplier catalog and order-specific availability policy — 24 October 2026

The owner-approved [Supplier Catalog and Order-Specific Availability Confirmation Policy](SUPPLIER_ORDER_SPECIFIC_AVAILABILITY_CONFIRMATION_POLICY_2026-10-24.md) is a binding addition to these Active Business Rules. Supplier catalogs use binary **In stock / Out of stock** signals and no permanent supplier quantity field. Admins review and link supplier-owned private product records to one canonical public product, or create a new canonical listing when no exact match exists. Only after independently verified payment does the system ask eligible suppliers, in priority order, to confirm the exact requested quantity for each paid line; declines, inability and qualifying timeouts trigger bounded, idempotent failover. If no eligible supplier can fulfill the full line quantity, the paid order enters the existing admin-managed exception process. No line splitting across suppliers, silent substitution, or automated refund/transfer is authorized by this policy. The existing operating-hour SLA, SKU cooldown, supplier eligibility, whole-order logistics, immutable snapshots and Zero-Automated-Outflow Law remain binding. Documentation alignment does not certify runtime implementation.


## Owner-approved Order State, Custody and Recovery Policy — 23 October 2026

The owner-approved [Order State, Custody and Recovery Policy](ORDER_STATE_CUSTODY_AND_RECOVERY_POLICY_2026-10-23.md) is a binding cross-cutting control policy. It governs lifecycle separation, idempotent payment/event recovery, concurrency-safe supplier allocation, item-level custody and evidence, exception ownership, and reconciliation without changing the specific business outcomes already defined in these Active Business Rules or the approved Exception and Reverse-Logistics Policies. Specific approved business rules take precedence for their prescribed outcomes. The policy does not authorize automated refunds or transfers; the Zero-Automated-Outflow Law, existing refund eligibility/window/category rules, the three-attempt absence rule, partial-offer deadline, supplier selection, manual payout authorization/reconciliation requirements and non-FIFO selection remain binding. Code compliance is unverified until separately audited and tested.

## Owner-approved Manual Bank Transfer and Proactive Supplier Payment Policy — 24 October 2026

The owner-approved [Manual Bank Transfer and Proactive Supplier Payment Policy](MANUAL_BANK_TRANSFER_AND_PROACTIVE_SUPPLIER_PAYMENT_POLICY_2026-10-24.md) defines the manual bank-transfer customer payment workflow, exclusive payment-mode configuration, evidence review, one-transaction/one-order protections, proactive supplier-payable queue and manual outgoing bank-transfer evidence. It supersedes conflicting Paystack-only customer-collection and Paystack-settlement prerequisites for orders created in manual-transfer mode. Existing orders remain pinned to their original rail. It does not certify implementation: all code paths, schema constraints, UI, treasury reconciliation and regression tests must be aligned before launch.


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
