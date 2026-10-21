# VICTORIOUS MARKET

> **Current owner-confirmed business model (21 October 2026):** Victorious MARKET is a centrally managed retailer operating from Uyo, Akwa Ibom State. Customers buy from VM, not public supplier storefronts. Suppliers privately submit product offers for VM approval or map offers to existing canonical listings. VM coordinates delivery through approved logistics companies; every rider must be linked to exactly one active logistics company. Authorized supplier-shop pickup remains available with the existing customer verification and photo evidence. Supplier payment becomes eligible only after verified successful customer delivery or pickup and reconciliation. Legacy marketplace commission, vendor payout and vendor-managed delivery rules are superseded for new retailer-model work and must be migrated deliberately, not renamed blindly.

> **Owner-confirmed payment and handover rule (20 October 2026):** Only pay-before-delivery through Victorious MARKET or reserve in-shop pickup and pay at the vendor shop through Victorious MARKET's authorised payment workflow are permitted. No off-platform payment. Doorstep handover requires customer OTP verification plus a doorstep photo; in-shop pickup requires the RES-XXXXXX reservation pass plus one-time customer pickup OTP verification plus an in-shop photo. GPS, signature, separate rider-pickup photo and hub inspection photo are not mandatory. The old strict FIFO vendor payout thresholds (₦5,000–₦500,000) apply only to historical marketplace earning records, not new supplier payables or logistics-company payables.

## Building Nigeria’s Trusted Online Market for Quality Products

**Brand Promise:** Your Trusted Online Market for Quality Products.

**Operating Philosophy:** Trust First. Quality Always. Accountability at Every Stage.

## 1. Executive Overview

Victorious MARKET is a centrally managed online retailer operating from Uyo, Akwa Ibom State. Its promise is to help customers buy quality products through one trusted retail brand, with controlled product approval, payment, pickup, delivery, customer support and remedies.

VM sources products from private suppliers and resells them to customers. Suppliers are operational partners, not public marketplace sellers. VM owns the customer relationship and the public retail offer.

## 2. The Problem VICTORIOUS MARKET Intends to Solve

Customers need confidence that product information is accurate, payments are handled safely, delivery commitments are realistic, and complaints have a clear owner. VM addresses these needs through approved catalogue information, verified payments, documented fulfilment, evidence-backed handover, and auditable refund decisions.

## 3. The Retailer and Supplier Model

### Supplier offers

Suppliers may submit product photos, brand/model, specifications, condition, warranty information, wholesale price, availability, and pickup/source location. Submission does not publish a listing or guarantee that VM will purchase the product.

### Catalogue approval and merging

VM reviews each offer. For a genuinely new product, VM creates and approves one canonical public product listing. When a supplier offers a product already in the catalogue, VM maps that offer to the existing listing instead of creating a duplicate. VM controls public descriptions, titles, categories, retail price, publication, customer-facing terms, and corrections.

Supplier identity, wholesale price, and private terms remain internal except where disclosure is necessary for fulfilment, consumer rights, or legal compliance. Each order item must snapshot the exact selected supplier offer, agreed purchase cost and relevant terms so later offer changes cannot rewrite an existing order.

### Supplier responsibilities

Suppliers provide accurate product information, disclose availability and material condition/warranty details, honor accepted supply commitments, and make the item available for VM collection or authorized customer pickup. Suppliers do not independently set the public retail price, collect customer payment outside VM's authorized workflow, control customer refunds, or act as public sellers within the platform.

## 4. Product Quality and Catalog Governance

VM is responsible for the public catalogue. Every listing must accurately represent product identity, brand/model, specifications, condition, photographs, warranty terms and availability. Used goods must not be described as brand new. VM may reject, suspend or correct an offer or listing that is inaccurate, unsafe, counterfeit, misleading or commercially unsuitable.

## 5. How Customers Will Shop

Customers shop on Victorious MARKET through two distinct, transparent fulfilment channels:

### Channel A: Doorstep Delivery (Pay First Online)
1. **Browse & Cart:** Customers browse canonical products listed by Victorious MARKET at the public retail price and add items to cart.
2. **Upfront Online Checkout:** At checkout, the customer selects "Doorstep Delivery", enters their delivery address in supported areas, and pays the full retail price plus delivery fee online via Paystack (card or bank transfer). **Cash on delivery (COD) is strictly disabled.**
3. **Automated Sourcing Assignment:** Upon verified Paystack payment, the system automatically assigns the order to the accredited supplier offering the cheapest wholesale cost price (`cost_price`) with active stock in Uyo.
4. **Admin Sourcing Override:** The Admin console maintains full override authority to review the auto-assignment and reassign the order to another accredited supplier whenever needed.
5. **Supplier Availability Confirmation:** The assigned supplier receives the notification in their portal, verifies their physical stock, and confirms product availability.
6. **Admin Logistics Assignment:** Once availability is confirmed, Admin assigns the order to an accredited Logistics Company (onboarded strictly by Admin).
7. **Fleet Rider Pickup & Transit:** The Logistics Company dispatches its active fleet rider to collect the packaged item from the supplier's shop and deliver it to the customer.
8. **Verified Handshake:** Handover requires customer verification of the delivery OTP (`delivery_otp`) plus a mandatory doorstep delivery photo proof.
9. **Supplier Settlement:** Only after verified customer delivery is the supplier's wholesale cost payable unlocked for payment.

### Channel B: In-Shop Pickup (Inspect First, Pay at the Shop via Paystack)
1. **Browse & Reserve (₦0 Upfront):** The customer selects "In-Shop Pickup" at checkout. The checkout fee is ₦0 upfront.
2. **Automated Location Assignment:** The system automatically assigns the reservation to the supplier shop that has the item available at the cheapest wholesale cost, issuing a unique reservation pass (`RES-XXXXXX`) and customer pickup OTP (`verification_code`).
3. **Admin Location Override:** Admin can review the assigned reservation and override/reassign the pickup location to another accredited supplier branch if required.
4. **Customer Journey & Direct Support:** While traveling to the shop, the customer can **call or chat Victorious MARKET customer support** for directions, timing, or assistance.
5. **Arrival & Reservation Presentation:** The customer arrives at the designated supplier shop counter and presents their `RES-XXXXXX` reservation code. The supplier verifies the reservation code in their terminal.
6. **Physical Inspection First:** The customer **physically inspects the item at the counter first** to verify condition, authenticity, and satisfaction before any payment is made.
7. **Pay at Shop Counter via Paystack:** Once satisfied, the customer makes payment **directly to Victorious MARKET via Paystack** (mobile payment link, counter QR code, or online counter checkout). Strictly NO cash or private vendor bank account transfers.
8. **Verified Release:** Once payment is confirmed by Paystack, the supplier verifies the customer's pickup OTP (`verification_code`), records the required in-shop photo proof, and releases the goods.
9. **Zero-Risk Customer Protection:** If the customer is not satisfied upon physical inspection, they do not pay, no funds are deducted, and the reservation is cancelled cleanly without refund delays.

## 6. Secure Payments and Retail Accounting

Customer payment must be verified against authoritative payment-provider information, the expected reference, exact amount in integer kobo, NGN currency and the correct order/customer. Browser redirects, screenshots, supplier claims or rider scans alone are not payment proof.

### Retail accounting replaces marketplace commission splits

The former 90% vendor / 5% cashback / 5% platform merchandise split is **retired** for the retailer model. The separate logistics-company delivery rule is: default 90% of the customer delivery fee to the logistics company and 10% VM delivery margin, subject to an audited admin override.

Victorious MARKET accounts for transactions as a retailer:
- **Wholesale Cost Price (`cost_price`):** Agreed price owed to the private supplier.
- **Retail Selling Price (`unit_price`):** Customer-facing price published by VM (`Wholesale Cost + VM Profit Margin`).
- **VM Gross Profit Margin:** `Retail Selling Price - Supplier Wholesale Cost = VM Gross Margin`.
- **Customer 5% Cashback (active):** Cashback is funded from VM's gross retail margin, never deducted from supplier wholesale cost. Earn 5% only on eligible post-discount merchandise actually funded by real money through VM's verified Paystack flow, after redeemed cashback; exclude delivery, discounts and cashback-funded value. Cashback matures after verified fulfilment + the 24-hour claim window + the separate 24-hour maturity buffer, provided no blocking case remains. Its 365-day validity begins at maturity. Redemption is capped at 50% of cart merchandise subtotal. Refunds reverse unspent cashback; spent-cashback recovery is tracked separately and may be offset only against future matured cashback under the existing terms.
- **Delivery Revenue & Expense:** The default logistics-company payable is 90% of the customer delivery fee; VM's default delivery margin is the remaining 10%. The company—not the rider—is the payee under this rule. Authorized admins can approve a higher or lower amount with an immutable reason/approver/time audit. Any amount above the fee is recorded as an explicit VM delivery subsidy.

Suppliers receive 100% of their agreed wholesale cost upon verified completion. No percentage commission is deducted from suppliers.

### Supplier payment eligibility

A supplier payment must not become eligible when an order is merely placed, paid, accepted, or marked ready. Eligibility begins only after verified successful customer delivery or confirmed customer pickup and the required order/amount reconciliation. Eligibility is not an automatic transfer: apply authorized payment controls and record actual payment confirmation. If a customer refund is owed after a supplier has already been paid, VM remains responsible for the customer remedy. Any supplier recovery is separate, evidence-based, contractually supportable and must not delay a valid customer refund.

### Existing payment and refund safeguards

The current payment, webhook, idempotency, refund authorization, original-payment-route, parent/child refund-ceiling and reconciliation controls remain required where applicable. Policy documentation is not proof that all code paths comply. The legacy web checkout bypass identified in the implementation audit remains open until corrected and tested. Customer statutory rights and valid warranty obligations override any narrower operational policy where applicable.

## 7. Supplier Operations and Availability

VM can source products after customer order, hold stock for selected fast-moving products, or use a hybrid approach. The system must clearly distinguish supplier-reported availability, VM-confirmed availability, stock actually held by VM, and a product reserved for an order. Supplier availability is not a guaranteed physical stock count unless verified.

Before confirming an order, VM must validate the selected supplier offer and its current availability, agreed purchase cost, fulfilment location, and ability to meet the expected delivery/pickup commitment. The order item snapshots the chosen offer and cost. If the supplier cannot fulfill, the system must stop fulfilment and follow the authorized cancellation/refund workflow rather than silently substituting a different offer or increasing the customer's price.

## 8. Central Delivery and Supplier-Shop Pickup

### Central operating base and service coverage

VM operates from Uyo, Akwa Ibom State. It may serve any destination that its current product sourcing and approved logistics arrangements can reliably cover; it must not imply universal or nationwide delivery. Checkout must reject or clearly mark unavailable any destination without a valid current coverage and fee rule. VM owns delivery coverage, customer-facing fee quotes, delivery coordination and customer communication.

### Logistics companies and riders

VM contracts or authorizes logistics companies to carry out delivery. Every rider assigned to VM work must be active and linked to exactly one active logistics company. A rider without a company link, a rider linked to multiple companies, a rider linked only to a supplier/vendor, or a rider belonging to an inactive company must not receive new work. The assignment must be recorded against the order or fulfilment unit and authorized by VM.

Logistics companies and riders manage operational custody and delivery, but cannot change product prices, mark payment successful, approve refunds or create supplier payables. Rider-to-company parcel custody evidence is distinct from proof that the customer received the product. Doorstep completion requires the existing server-verified customer OTP and doorstep photo. A dispatch assignment or rider collection scan alone is not successful fulfilment.

### Supplier-shop pickup

Customer pickup at an authorized supplier shop remains available through the existing reservation flow. VM authorizes the pickup location and order; the customer uses the reservation pass/reference and separate one-time customer pickup verification, and the required in-shop photo is recorded. Supplier readiness does not equal pickup. Only verified customer handover completes fulfilment and makes the supplier payable eligible. The supplier shop is a collection location, not a public storefront or an independent payment channel.

### Failed delivery and custody

Failed attempts, returns to origin, custody transfers, damaged/lost parcels, and customer refusal must be recorded as separate operational outcomes. They must not automatically imply refund approval, supplier fault, supplier debt, or successful fulfilment. VM decides the customer remedy under the applicable refund/consumer-rights policy. Supplier recovery requires separate evidence and a contractual basis.

## 9. Doorstep Acceptance and Partial Rejection

One important part of the VICTORIOUS MARKET model is how the platform handles a delivery containing multiple products.

Suppose a customer purchases three items:

- A smartphone
- A power bank
- A smartwatch

At delivery, the smartphone and power bank are acceptable, but the smartwatch is incorrect.

The system is intended to support item-level decisions rather than forcing the customer to accept or reject the entire order.

The accepted items can proceed through their appropriate completion process while the rejected item follows a controlled return or dispute workflow.

This requires accurate item quantities, evidence, pricing allocations, and financial adjustments.

### Preventing abuse

Customer rejection rights must be balanced with merchant and logistics protections.

A customer should not automatically receive unrestricted compensation simply by claiming dissatisfaction.

Likewise, a merchant should not be allowed to dismiss a legitimate complaint without review.

The system should distinguish between wrong items, damage, defects, inaccurate descriptions, failed delivery, and a genuine change of mind.

Each situation may require different treatment under the platform's published policies and applicable consumer-protection requirements.

---

## 10. Returns, Refunds, and Dispute Resolution

VICTORIOUS MARKET is being designed to provide customers with a structured way to report eligible problems after delivery.

Returns should be linked to actual orders and specific order items.

A return request may include the reason, affected quantities, supporting evidence, and the selected return collection arrangement.

Where applicable, a returned product will undergo inspection before the case is resolved.

### Refund control

Refund decisions and financial disbursements are intended to remain under authorized administrative control.

The platform must verify the amount due, account for discounts and previously issued credits, prevent duplicate refunds, and ensure that the payment provider's actual outcome is reconciled with the internal records.

A refund should not be presented to the customer as completed merely because a request was submitted to a payment gateway.

**Refund responsibility:** Victorious MARKET owns the approved customer refund process and executes refunds through the authorized financial workflow, ordinarily to the original payment channel. The customer must not be redirected to collect a marketplace refund from the vendor. Where the approved reason and actual order allocation make the vendor responsible, vendor recovery is tracked separately. If vendor proceeds remain held, an authorized offset may be applied; if already paid, record a recovery receivable/liability and use an approved future-settlement offset or recovery process. Customer refund completion depends on verified refund execution, not on whether vendor recovery has completed.

### Dispute resolution

Where parties disagree, VICTORIOUS MARKET should maintain an evidence-based review process.

The system should preserve the relevant order history, delivery records, customer statements, merchant responses, inspection findings, and financial transactions.

The objective is not to automatically favor the customer or the merchant.

**The objective is to reach a fair, explainable, and documented resolution.**

---

## 11. Supplier Payables and Financial Controls

The retailer model does not use marketplace vendor earnings as the default supplier payment mechanism. Supplier payables arise from the agreed purchase cost of goods sourced for VM, linked to the relevant supplier offer and order item.

No supplier payment eligibility before verified successful customer delivery or confirmed customer pickup. Keep supplier payable, payment request/authorization, payment attempt, actual transfer confirmation, customer refund, and any supplier recovery as distinct records and states. Duplicate events or retries must not duplicate the payable or payment. Timeouts are uncertain outcomes and require reconciliation before another payment is sent.

Customer refunds remain VM's responsibility and must follow the authorized original-payment-route policy where supported. If VM has already paid the supplier, a later customer refund does not automatically establish supplier debt. Record a supplier receivable only when evidence identifies supplier responsibility and the agreement permits recovery. Never delay an owed customer refund while attempting supplier recovery.

Legacy vendor earnings, payout thresholds, commissions, and vendor-recovery ledgers must be mapped before migration or retirement. Preserve historical financial records and audit references; do not destructively rename tables or reclassify old entries without an approved migration and accounting treatment.

## 12. Administration and Governance

The VICTORIOUS MARKET administrative environment handles supplier onboarding, wholesale offer reviews, canonical catalogue governance, order sourcing assignment, security oversight, financial reconciliation, customer support, authorized refunds, and operational reporting.

Delivery operations are coordinated by VM through accredited logistics partners. The platform assigns deliveries to active, approved logistics companies and their verified riders; platform administrators enforce safety, track operational custody, review incidents, and inspect audit trails.

Role-based permissions separate customers, suppliers (private source accounts), accredited logistics companies and riders, and platform administrators. Rider permissions are limited to assigned delivery orders and the minimum operational information required for delivery. Riders cannot access customer financial information, wholesale costs, supplier margins, wallet credits, payout routes, or ledger data. Sensitive administrative actions must be authorized and auditable.

## 13. Security, Privacy, and Evidence Protection

Security is a core requirement of Victorious MARKET.

VICTORIOUS MARKET is being hardened to protect customer information, supplier records, payment processes, order history, and operational evidence.

Important security objectives include:

- Preventing unauthorized access across accounts and branches.
- Protecting against SQL injection, cross-site scripting, server-side request forgery, and other application vulnerabilities.
- Verifying external payment notifications cryptographically.
- Preventing replay attacks and duplicate financial operations.
- Protecting private evidence and customer data.
- Restricting administrative permissions.
- Maintaining reliable security logs and incident-response procedures.
- Supporting secure data backups and recovery.

The system is also introducing Cloudflare R2 media storage and Google Drive database backup capabilities.

These technologies are intended to improve storage scalability and resilience, but they must be configured, tested, monitored, and periodically validated.

No system can reasonably promise that cyberattacks are mathematically impossible.

The practical goal is to reduce risks through secure design, independent testing, monitoring, incident response, and continuous improvement.

---

## 14. The Customer Loyalty Experience

VICTORIOUS MARKET intends to build long-term customer relationships rather than focus exclusively on one-time transactions.

The proposed cashback system is an important part of this approach.

Eligible purchases can generate customer loyalty value that may be used toward future purchases according to published rules.

The platform must ensure that cashback is calculated correctly, cannot be redeemed more than once, and is appropriately adjusted when orders are refunded or reversed.

The goal is to reward genuine participation without allowing the loyalty program to undermine the financial sustainability of the business.

---

## 15. The Business Expansion Vision

The initial operating focus is Uyo, Akwa Ibom State, with expansion into additional supported locations as the operational model becomes reliable.

Rather than immediately making every product available nationwide, the platform expands through verified local suppliers, confirmed local delivery coverage, accredited logistics partners, and reliable in-shop pickup arrangements.

This geographic discipline is intended to avoid promising deliveries that the platform cannot consistently fulfill.

As VICTORIOUS MARKET matures, the system may support more supplier branches, expanded product categories, accredited delivery fleets, and customers.

Expansion should follow demonstrated operational capacity, not merely technical features.

---

## 16. How VICTORIOUS MARKET Intends to Earn Trust

The brand promise is not that problems will never occur.

Products can become unavailable. Deliveries can fail. Customers and merchants can disagree. Banks and payment providers can experience delays.

The promise must therefore be supported by how the business prevents, detects, communicates, and resolves problems.

VICTORIOUS MARKET intends to earn trust through five principles:

**Verification:** Customers know that suppliers and products are subject to identity and quality checks.

**Transparency:** Product descriptions, retail prices, delivery charges, transaction statuses, and customer policies are crystal clear.

**Protection:** Payments, order fulfillment, and eligible customer remedies operate through controlled procedures.

**Accountability:** Suppliers, logistics partners, riders, administrators, and the platform itself are accountable for their responsibilities.

**Consistency:** Customers receive dependable, premium retail treatment on every purchase.

A trustworthy retailer is not one that claims never to fail. It is one that has reliable systems for preventing failures and resolving them fairly when they occur.

---

## 17. Current Development Position

VICTORIOUS MARKET is actively developing the technical foundations required to support this model.

Its current engineering direction includes managed supplier onboarding, structured canonical catalogs, checkout and inventory controls, Paystack integration, retail margin accounting, doorstep OTP handshakes, return workflows, controlled supplier settlements, evidence storage, and disaster-recovery capabilities.

These features are at different stages of implementation and verification.

Before broad public launch, the most important milestones are financial correctness testing, security validation, complete customer-journey testing, operational readiness, supplier onboarding, and real-world pilot transactions.

---

## 18. Long-Term Vision

VICTORIOUS MARKET aims to become Nigeria’s most trusted retail commerce infrastructure.

The long-term ambition is to create dependable commerce connecting verified suppliers, customers, accredited logistics carriers, and controlled financial operations.

Customers should be able to shop with complete confidence.
Suppliers should be able to sell their wholesale inventory smoothly.
Logistics partners should operate through structured, accountable workflows.
And the platform maintains immutable, balanced records of every transaction.

Approved suppliers should be able to supply VM reliably under private wholesale arrangements, while customers continue to see and transact with Victorious MARKET as the retailer.

Approved logistics companies should operate through structured and accountable processes, with each assigned rider linked to exactly one active company.

And the platform should maintain reliable records of the transactions it facilitates.

The ultimate measure of success will not be the number of features, merchants, or products available.

It will be whether customers consistently receive what they paid for, merchants receive the money they legitimately earn, delivery partners fulfill their responsibilities, and disputes are resolved fairly.

## Conclusion

**VICTORIOUS MARKET is building a trust-first online retail business designed to make quality commerce safer, more transparent, and more accountable in Nigeria.**

Its foundation is the belief that online shopping should not require customers to gamble with their money or depend entirely on a stranger's promises.

By combining private supplier verification, quality-focused product listings, secure payment processing, centrally coordinated delivery through approved logistics companies, transparent financial records, active cashback under defined terms, and fair customer protection, VICTORIOUS MARKET intends to establish a more dependable standard for online retail.

**Our Vision:** To become a trusted online retailer where Nigerians can buy quality products with confidence.

**Our Mission:** To source quality products from private suppliers and deliver them to customers through a secure, transparent, reliable, and accountable retail experience.

**Our Commitment:** To build trust into every stage of the shopping experience—and earn that trust through consistent performance.

### VICTORIOUS MARKET
**Your Trusted Online Market for Quality Products.**

*Fast. Safe. Reliable.*

*Development vision and intended operating model — October 2026. Features, policies, and operational commitments remain subject to implementation, testing, and launch readiness.*

### Independent merchandise and delivery-fee refund decisions

Victorious MARKET treats merchandise and delivery fees as separate financial components. A customer may raise one case about both, but **the merchandise refund and delivery-fee refund must be approved independently**. Approval of a product refund never automatically approves delivery-fee refundability, and approval of a delivery-fee refund never automatically approves the product refund.

- A merchandise refund identifies the affected VM order item(s) and quantity, applies the original discount allocation, and reverses the actual captured retailer accounting entries plus the related cashback ledger entry where applicable. The former vendor 90/5/5 split is historical-only.
- A delivery-fee refund identifies the captured delivery fee for the VM order, records its own reason/evidence/approval, and reverses the actual delivery-fee and logistics-company payable/margin entries for that transaction. It never includes merchandise value or cashback. Any already-paid logistics-company amount is handled through a separate authorized recovery/adjustment and never delays the customer refund.
- Each approved component has its own decision record, amount, idempotent financial operation, reservation, gateway state and customer-facing result. Both operations return funds through the original Paystack payment channel at launch. They may reference the same original parent checkout capture but must not be collapsed into one undifferentiated approval or ledger reversal.
- The customer can receive one component while the other remains pending, is denied, or fails and requires reconciliation. Customer communications must state each component's decision and confirmed Paystack result separately.
- The system must enforce the component balance, order/item refund ceiling and shared parent-capture ceiling, counting active reservations and confirmed refunds across all applicable order components. A zero-fee in_shop_pickup order has no delivery fee to refund.
- Supplier recovery, logistics-company recovery and cashback recovery are separate from the customer's gateway refund. A customer refund is complete only when Paystack confirms that specific component; vendor recovery does not delay or replace that confirmation.
- **Shared source history does not mean shared approval or one combined state.** Both component decisions link to the same original parent capture and relevant child-order allocation history, while retaining separate approval records, reservations, gateway operations, and outcomes. Confirmed Paystack refunds plus active refund reservations across all child orders/components share one parent-capture ceiling; the same amount cannot be refunded twice.
- **Cashback is linked to merchandise history but is not a second customer gateway refund.** A merchandise refund may require reversal of the associated unspent cashback and/or a separately tracked recovery of cashback already spent. That cashback adjustment uses the same originating order/item/refund evidence, but it is accounted for in the cashback ledger and must not be counted again as an additional Paystack cash refund against the parent-capture ceiling. Cashback maturity remains a separate clock: verified fulfilment, the 24-hour customer claim window, then the further 24-hour maturity buffer, with no blocking case; the 365-day validity period starts at maturity.
- The existing 24-hour customer return/claim window and separate 24-hour cashback-maturity buffer remain unchanged.

These are binding business requirements, not a statement that the current code already supports independent component approvals. See the payment/refund matrix and implementation audit for required changes and tests.
