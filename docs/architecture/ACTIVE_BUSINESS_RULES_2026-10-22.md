# Victorious MARKET — Active Business Rules and Documentation Source of Truth
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

## 3. Customer registration gate & Payment before supplier allocation
- **Mandatory Customer Account Registration:** Guests are permitted to browse the storefront catalog, view products, calculate delivery estimates, and add items to cart freely. However, **guest checkout is strictly disabled**. Before proceeding to checkout or initializing Paystack payment, the customer must register or log in. This ensures that every order is bound to an authenticated customer record with a verified phone number for the rider's mandatory pre-departure call and secure Delivery OTP dispatch.
- The customer must complete upfront payment through an enabled approved method: Paystack (verified through trusted provider verification/webhook mechanisms) or Manual Bank Transfer (actual receiving-account credit verified and explicitly approved by an authorized admin). A browser redirect, screenshot, payment initialization, customer-supplied transaction ID or unverified event is not proof of payment.
- The backend must persist a verified payment decision before any order-specific supplier confirmation/allocation, assignment or reservation begins. For Paystack, enforce trusted provider verification/webhook authenticity, amount, currency, reference and idempotency. For Manual Bank Transfer, the customer must upload a screenshot/image of the transfer receipt against the correct order for admin review. Store it securely with order-scoped access and retain evidence/review history. Only one receipt submission is accepted per order; a rejected submission is terminal and retry requires a new order. The screenshot is supporting evidence only: an authorized admin must independently verify the actual receiving-account credit, match amount/currency/reference/order evidence, and approve it through an auditable, concurrency-safe operation. Missing/rejected evidence leaves the order unpaid/on hold. Manual transfer is not a Paystack transaction and must not be represented as one.
- Before payment, the system may display catalogue availability, quote a price, validate a cart or perform a non-binding feasibility check. It must not lock suppliers, reserve their stock, notify them of a binding order, or create supplier payables.
- After verified payment, revalidate current supplier/offer eligibility, then request order-specific confirmation that an eligible supplier can fulfill the exact quantity on each paid line. Try eligible candidates in priority order after decline, inability, qualifying timeout or loss of eligibility; allocate only after valid confirmation. If candidates are exhausted, move the paid order/line to the admin-managed exception process. Preserve payment, confirmation-attempt, allocation and exception events for audit.

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
- **The Zero-Automated-Outflow Law:** Money NEVER leaves the platform or payment gateway automatically. Under no circumstances may any server script, webhook, queue worker, cron job, or timeout autonomously trigger a Paystack refund or bank transfer.
- **100% Manual Human Super Admin Authorization:** Every customer refund (including verified duplicate charges, pre-action cancellations, out-of-stock items, or customer returns) and every bank transfer (supplier wholesale disbursements and logistics company settlements) must generate an immutable, auditable pending request in the Admin Command Tower and requires explicit review, authorization, and manual dispatch initiation by an authorized human Super Administrator.
- Supplier payables use immutable agreed wholesale-cost snapshots and become eligible only after verified delivery or an explicitly authorized completion exception followed by reconciliation. Supplier stock acceptance alone does not make a payable due.
- **Supplier payable disbursement limits:** each supplier-payment request/disbursement must be at least ₦5,000 and no more than ₦1,000,000 at a time. Do not silently split an over-cap request into multiple transfers; it must be reduced to an allowed amount or handled through a separately authorized decision.
- **Disbursement schedule:** Supplier and logistics fleet disbursements occur strictly Monday through Friday after Paystack transactions have fully settled into Victorious MARKET's corporate settlement account.
- **No FIFO:** do not use FIFO, oldest-earning-first, oldest-order-first, or automatic FIFO allocation to decide which eligible supplier payables are included in a payment. Selection must use an authorized, auditable non-FIFO process. Do not invent an automatic alternative ranking/selection algorithm without owner approval. Admin approval, reconciliation, idempotency and provider-confirmed payment remain required; eligibility is not automatic payment.
- Keep customer receipts, refunds, supplier payables, logistics-company payables, retailer margin, delivery revenue/costs, bulky surcharges, cashback and recovery/reconciliation as distinct money paths.
- Cashback remains a separate 5% loyalty benefit on eligible post-discount merchandise actually funded by verified Paystack money, subject to existing caps, maturity and reversal rules. Former 90/5/5 merchandise splits and vendor-managed delivery allocations are historical only.
- All sensitive assignments, overrides, refunds, tariff changes and financial adjustments require role-based authorization, a reason where applicable, and immutable audit records.

## 9. Security, Cryptography and Anti-Fraud Guardrails
- **Financial Gateway Cryptography:** Mandatory HMAC-SHA512 verification on all Paystack webhooks; atomic database transaction idempotency locks; zero client-side price trust.
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

The owner-approved [Order State, Custody and Recovery Policy](ORDER_STATE_CUSTODY_AND_RECOVERY_POLICY_2026-10-23.md) is a binding cross-cutting control policy. It governs lifecycle separation, idempotent payment/event recovery, concurrency-safe supplier allocation, item-level custody and evidence, exception ownership, and reconciliation without changing the specific business outcomes already defined in these Active Business Rules or the approved Exception and Reverse-Logistics Policies. Specific approved business rules take precedence for their prescribed outcomes. The policy does not authorize automated refunds or transfers; the Zero-Automated-Outflow Law, existing refund eligibility/window/category rules, the three-attempt absence rule, partial-offer deadline, supplier selection, payout caps/schedule and non-FIFO selection remain binding. Code compliance is unverified until separately audited and tested.


## Proposed Manual Bank Transfer and Payment-Provider Policy Addendum — 24 October 2026

The [Manual Bank Transfer and Payment-Provider Policy](MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md) defines a distinct direct-to-Victorious MARKET bank transfer path. Admins may configure the receiving account and customer instructions; each order has a unique code customers should copy into narration. The customer must upload a payment screenshot/receipt against the order for admin review. An authorized admin must independently verify actual receiving-account credit and explicitly approve payment before the existing post-payment sourcing workflow begins. Screenshots and customer claims alone are never proof; uploads are supporting evidence, not confirmation of funds. Duplicate transaction use, amount discrepancies, concurrency, late payment, audit and reconciliation controls are mandatory. Manual-transfer refunds require explicit authorized human review and manual dispatch under the Zero-Automated-Outflow Law; the refund destination procedure must be approved before release. The payment-provider architecture should be replaceable without weakening shared financial controls. Documentation alignment does not certify implementation.


### Strict payment-path isolation and exclusive activation
Only one customer payment method may be active globally at a time: Paystack or Manual Bank Transfer, or neither. Admin activation must be atomic, concurrency-safe and enforced server-side; enabling both is forbidden. When neither is active, checkout cannot accept payment. Every order is bound to one immutable payment method. The order cannot combine, switch, top up, verify, settle or refund through the other method. Paystack evidence can apply only to Paystack orders; actual bank-credit evidence and admin approval can apply only to manual-transfer orders. Deactivation does not erase or rewrite existing orders or attempts. Any reset after a definitively failed attempt requires an audited procedure proving no capture/credit occurred; pending, unknown, disputed or potentially late payments must be reconciled first. See [Manual Bank Transfer and Payment-Provider Policy](MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md).
