# VICTORIOUS MARKET — Detailed Business Overview

> **Active business-rule source of truth:** [Active Business Rules](docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md). Where older wording conflicts, follow that policy. This is a documentation alignment update only; code implementation remains unverified.

> **Official positioning:** Victorious MARKET — Your Trusted Online Market in Uyo, Akwa Ibom State.  
> **Core principle:** The customer buys from Victorious MARKET, not from individual suppliers.

## 1. What Victorious MARKET is

Victorious MARKET is a centrally managed, online-first retail business. It helps customers buy products conveniently while the business coordinates product sourcing, verified payment, order fulfilment, delivery, customer support and financial reconciliation.

It is designed as **one retailer supported by a private network of approved suppliers and logistics companies**, not as an open marketplace where vendors independently own the customer relationship or manage separate storefronts and deliveries.

- **Victorious MARKET** owns the customer-facing brand, catalogue, retail offer, checkout, support and order coordination.
- **Suppliers** are behind-the-scenes sources of products, each with one approved shop or pickup point.
- **Approved logistics companies** provide transportation and delivery services under centrally managed dispatch rules.
- **Customers** order from Victorious MARKET and receive one coordinated order experience.
- **Administrators** supervise eligibility, assignments, tariffs, exceptions, financial controls and audit records.

The model allows Victorious MARKET to offer a broad catalogue without owning every item in advance or operating a large warehouse.

## 2. Service area and delivery corridors

The approved operating scope is **Uyo Local Government Area, Akwa Ibom State**. New sourcing, eligible customer destinations, active delivery corridors and dispatch decisions must remain within the approved Uyo operating boundary.

The eight configured road corridors are:

1. Oron Road
2. Nwaniba Road
3. Aka Road
4. Abak Road
5. Ikot Ekpene Road
6. Itu Road
7. Ikpa Road
8. Plaza

A customer destination must map unambiguously to an active corridor. If the system cannot establish that a destination is inside the approved service area or map it to a valid corridor, it should fail closed rather than silently accept it.

Expansion beyond Uyo requires an explicit business-policy decision and corresponding updates to the operational rules, application logic, configuration, documentation and tests. A logistics company's wider geographic coverage does not itself expand Victorious MARKET's approved service area.

## 3. Product categories

The intended catalogue can cover:

- **Electronics and gadgets:** phones, power banks, MiFi devices, smartwatches and accessories.
- **Home and kitchen appliances:** fans, blenders and selected household or kitchen equipment.
- **Furniture and beddings:** foam mattresses, bedding and selected furniture.
- **Power equipment:** generators and related equipment, subject to safe handling and transport requirements.
- **Musical instruments:** keyboards, guitars and related equipment.
- **Fashion and clothing:** selected clothing and accessories.
- **Bags and luggage:** bags, suitcases and travel products.

These are intended categories, not a guarantee that every item is currently listed or available. Products should be offered only when product details, pricing, sourcing options, availability and fulfilment requirements are sufficiently established.

## 4. Supplier approval and product sourcing

Victorious MARKET does not need to own all inventory before listing products. It may work with approved suppliers, each operating from one admin-approved shop or pickup location anywhere within the approved Uyo operating boundary.

The intended flow is:

1. A supplier submits product information, wholesale cost and stock availability from one admin-approved shop or pickup location anywhere within Uyo.
2. Victorious MARKET verifies the supplier offer and listing eligibility. A product is visible for purchase only while at least one eligible supplier offer is explicitly in stock; if all eligible offers are out of stock or ineligible, the listing is automatically hidden. An authorized admin may also delist it manually. Hiding a listing preserves the product, offer and order history.
3. The customer places an order and completes payment.
4. Only after payment is independently verified does the system revalidate stock and allocate suppliers to that paid order. Any pre-payment availability or feasibility check must be non-binding and must not reserve stock or assign suppliers.
5. The selected supplier or suppliers prepare the required items.
6. One assigned rider from one approved logistics company collects the complete order from every required supplier location and delivers it to the customer.

Customers should not select private suppliers or browse supplier storefronts. Suppliers should not independently control the customer's payment, delivery promise or customer-facing order policy.

### Supplier allocation rules

Allocation should apply hard eligibility checks before comparing offers. Exclude offers where the supplier is not approved, active or appropriately located; the product is not explicitly in stock; an applicable 24-hour unavailable/out-of-stock cooldown has not expired; or the supplier has reached the limit of five active pending handovers.

Among feasible whole-order allocations, the intended priorities are:

1. Compare only feasible plans that can fulfil the whole customer order; first prefer the plan using the fewest distinct supplier pickup locations.
2. Among plans using the same minimum number of locations, prefer the lowest total wholesale cost.
3. Among equally consolidated and equally priced plans, prefer better documented fulfilment reliability.
4. Apply a stable deterministic tie-break so equivalent inputs produce a predictable outcome.

An administrator may override an assignment only with the required permission, a recorded reason and an immutable audit trail. An override must not bypass payment verification or other non-overridable safety conditions.

## 5. Customer payments

The approved payment model is **upfront online payment through Paystack**. Cash-on-delivery is disabled.

- Sourcing must not begin until the payment has been independently verified.
- A customer screenshot or browser redirect is not sufficient proof of payment.
- Transaction reference, amount, currency and payment status must be checked against trusted payment records and the verified webhook flow.
- Duplicate events must be idempotent; delayed, failed, reversed or disputed payments must enter controlled reconciliation.
- An order must not be treated as paid solely because the customer says payment succeeded.

These controls help prevent unfinanced fulfilment and reduce duplicate charging or settlement risk.

## 6. One order, one rider

A customer order can be sourced from multiple supplier shops, but **the order must never be split across multiple riders**. One assigned rider from one approved logistics company is accountable for collecting every item and delivering the complete customer order.

### Small orders and bulky orders

- **Small orders:** A bike may batch orders for multiple customers only when the orders meet the same-road-corridor rule and all other capacity, route, timing and eligibility requirements.
- **Bulky orders:** If any item in an order is bulky, the whole order is classified as bulky. It must be assigned to a Keke/tricycle, and the trip must be exclusive to that customer.
- **No mixed bulky trip:** A Keke carrying a bulky order must not carry another customer's order on that trip.

Bulky status must be determined using configured product and handling rules, not left solely to rider discretion.

### Pickup and delivery proof

The intended chain of custody includes:

- A separate one-time pickup OTP for each supplier shop.
- Mandatory photo evidence for each supplier pickup.
- A customer delivery OTP at the doorstep.
- A mandatory geo-tagged doorstep delivery photo.

Missing or invalid verification must trigger the relevant exception process; it must not be silently treated as a successful handover.

## 7. Customer delivery fees versus logistics-company payables

These are separate calculations and must not be conflated.

### Customer delivery charge

The customer pays one base delivery fee per order, plus a customer bulky surcharge for each applicable physical bulky unit.

`Customer delivery charge = flat customer base fee per order (independent of corridor) + (applicable bulky units × customer bulky rate)`

### Logistics-company payable

The amount owed to the assigned logistics company is calculated from the administrator-configured corridor base payable and the company's configured bulky-unit surcharge.

`Company payable = corridor base payable + (bulky units × company bulky rate)`

The company payable is not automatically a percentage of the customer's delivery fee. The former 90% delivery-fee allocation rule is retired.

For each assigned delivery, preserve an immutable snapshot of the corridor, assigned company, tariff/version, base payable, bulky quantity, bulky rate and total payable. A future tariff change must not silently rewrite an existing delivery's financial calculation.

If the company payable exceeds the customer delivery revenue collected, the difference must be explicitly recorded as a Victorious MARKET-funded subsidy or expense. It must not disappear into an unexplained balance. The payable belongs to the approved logistics company, not automatically to the individual rider. Overrides require authorisation, a reason and an immutable audit trail.

## 8. Supplier payables and financial separation

Supplier payables are based on immutable wholesale-cost snapshots for the actual items sourced for an order.

- Stock acceptance or availability confirmation alone does not make a supplier payable due.
- A supplier payable becomes eligible only after verified delivery, or an explicitly authorised pickup-completion exception, followed by reconciliation.
- Missing items, cancellations, refunds, disputes and payment reversals must be reflected through controlled adjustments.
- Each payable must be traceable to the correct order, order line and supplier.

The system must maintain distinct money paths for customer receipts, refunds, supplier payables, logistics-company payables, Victorious MARKET margin, bulky surcharges, cashback and recovery/reconciliation. It must not count one receipt twice or treat money owed to another party as unrestricted retailer revenue.

### Retail margin example

If merchandise sells for ₦100,000 and the agreed supplier wholesale cost is ₦85,000, the initial merchandise spread is ₦15,000 before other applicable costs.

| Item | Amount |
|---|---:|
| Customer merchandise payment | ₦100,000 |
| Supplier wholesale cost | ₦85,000 |
| Initial merchandise spread | ₦15,000 |

That spread is not necessarily net profit. Payment-processing fees, discounts, refunds, eligible cashback, delivery subsidies and other attributable expenses may reduce the final result. Delivery revenue and delivery costs should be accounted for separately.

### Cashback

The intended loyalty benefit remains 5% cashback on eligible merchandise spending, subject to the existing caps, maturity, reversal and eligibility rules. It must be based on eligible post-discount merchandise actually funded by verified Paystack money. Cashback is a separate loyalty liability; it is not a supplier commission or a share of customer payments.

Former 90/5/5 merchandise splits and vendor-based delivery allocations are historical, not the active financial model.

## 9. Exceptions and support-managed pickup

### 9.1 Paid-order allocation and fulfilment failures

A verified paid order must not be automatically refunded solely because no eligible supplier can immediately fulfil it. Place it in a visible admin-managed exception state with an alert and aging/escalation information. Authorized admin may reassign it, source an acceptable alternative, hold fulfilment, cancel or initiate a refund according to policy. Do not silently substitute products or impose extra charges. A refund is complete only after payment-provider confirmation; record the actor, reason, amount, references, timestamps and status. Preserve idempotency and the financial audit trail.

The system must handle supplier unavailability, incomplete sourcing, failed pickup, failed delivery, cancellations, refunds, delayed or reversed payments, disputes and tariff changes without losing financial or audit integrity.

Each exception should use explicit states, preserve the original records and require appropriate authorisation for sensitive actions. Supplier availability cooldowns must be enforced by the application, not merely described in documentation.

A support-managed pickup may remain as a controlled exception only when the complete order is physically ready at one approved collection point, the payment arrangement is authorised, the customer agrees, and required verification and photo evidence are recorded. This is not a public reservation or order-hold flow. Legacy reservation passes and holds are historical records, not an active public checkout feature.

## 10. Administrator responsibilities

Administrators oversee:

- Supplier approval and the single admin-approved shop/pickup point for each supplier anywhere within Uyo.
- Product eligibility, wholesale offers and sourcing rules.
- Order allocation and authorised assignment overrides.
- Customer delivery rates and active Uyo corridors.
- Each logistics company's corridor base payable and bulky-unit surcharge.
- Approved logistics companies and dispatch assignments.
- Pickup and delivery evidence, incidents and exceptions.
- Paystack transactions, refunds and reconciliation.
- Supplier and logistics-company payables.
- Cashback, financial adjustments, reports and immutable audit records.

Sensitive actions must be permission-controlled, reason-coded and auditable. A policy is not fully implemented simply because it appears in a document; application logic, database constraints, interfaces and tests must enforce it too.

## 11. Website and digital infrastructure

The public website is [victoriousmarket.com.ng](https://victoriousmarket.com.ng). The digital experience may include product discovery, product details, checkout, verified payments, supplier allocation, dispatch, order tracking, customer notifications, administrative controls and financial reporting.

The public website and operational backend must enforce the same rules. Documentation, code, database schema, admin interfaces and tests should agree on the same active business model.

## 12. Development and release status

This document describes the intended business operating model; it does not certify that every rule is already implemented in production.

At the last recorded development stopping point, consolidation work was not release-approved. Pull request #12 is closed and unmerged according to the latest observed metadata. The active business rules are documented separately in `docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md`; this documentation update does not certify code or release readiness. Documentation had been updated, but the latest recorded focused test result showed 12 failures. The project was therefore not verified as release-ready.

Recorded remaining work included database compatibility for orders sourced from multiple suppliers, supplier-unavailability cooldown tests, refund-approval test flows, alignment of retired legacy tests, and verification that the admin interface exposes Uyo corridor and logistics-company payable settings. These are development findings, not proof that every rule in this overview is enforced in a live production system.

## 13. Long-term vision

Victorious MARKET aims to become a trusted local online retail destination by:

1. Building customer trust through clear product information and reliable fulfilment.
2. Sourcing from approved physical shops rather than depending on speculative inventory.
3. Keeping the supplier network private and centrally managed.
4. Making every order traceable from verified payment through final completion.
5. Maintaining transparent supplier and logistics-company financial records.
6. Improving delivery reliability while controlling transport costs.
7. Establishing a dependable Uyo operation before considering geographic expansion.

## Summary

**Victorious MARKET is a Uyo-focused online retailer that sells under one trusted brand, sources products from approved suppliers, coordinates one-rider fulfilment for each complete order, and separately manages customer payments, supplier costs, logistics-company payables, delivery charges, cashback and reconciliation.**
