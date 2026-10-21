# Victorious MARKET — Simplified Delivery-First Fulfilment Policy

**Policy date:** 21 October 2026  
**Status:** Owner-directed current policy  
**Applies to:** New retailer-model transactions and all current product, checkout, fulfilment, support, finance, API and customer-facing documentation  
**Authority:** This policy supplements `VICTORIOUS_MARKET_SYSTEM_RULES_V1.md`. If older documents describe public multi-vendor selling, customer-facing shop selection, automatic in-shop pickup reservations, or supplier-address disclosure, those descriptions are historical/legacy requirements and do not define the current customer journey.

## 1. The simplest operating model

Victorious MARKET (VM) is one customer-facing retailer. Private suppliers are sourcing partners behind the scenes, not independent marketplace sellers. VM owns the retail listing, retail price, customer relationship, checkout, support, refund decision and fulfilment coordination.

All approved supplier shops and all approved logistics companies must be physically based in **Uyo, Akwa Ibom State**. The initial delivery network is Uyo-centred, not Uyo-destination-only. Within Uyo, the standard method is **door-to-door delivery**. Outside Uyo, an approved Uyo-based logistics company manages the delivery arrangement (for example, park/terminal collection or another agreed handoff); it is not automatically doorstep delivery. Destinations include approved Akwa Ibom LGAs and the named regional cities Aba, Calabar and Port Harcourt, but a destination is available only when its directional route is explicitly enabled. The customer places one order, pays through the authorized VM checkout, and sees one published delivery fee for the selected destination/service.

## 2. No automated pickup or reservation at checkout

The customer-facing website and APIs must not offer an automated in-shop pickup checkout, supplier selection, supplier-address disclosure, or inspect-first/pay-later reservation as a standard fulfilment option.

A customer who prefers pickup must contact VM support by phone or WhatsApp. Support-assisted pickup is an exception, not a second automated checkout workflow. Support must verify the complete order, product availability, physical readiness, approved collection point, payment arrangement and customer agreement before confirming the plan.

**Never tell a customer to travel to a supplier simply because that supplier has a listed product.** Do not disclose a supplier/collection address or claim an order is ready until the approved plan is confirmed and the required products are physically available at that point.

## 3. Whole-order coordination

Sourcing decisions must account for the complete order, not independently direct the customer to each product's nearest supplier.

For each line, VM identifies eligible private sources using confirmed current availability, active/approved source status and valid route/fulfilment coverage. VM then determines whether the complete order can be fulfilled reliably. If products come from multiple sources, VM coordinates internal collection and consolidation or chooses another feasible delivery plan. The customer must not be made to coordinate suppliers.

A single customer-facing order must not silently become multiple customer delivery fees or undisclosed supplier visits. Any exception that materially changes the agreed price, delivery promise or collection arrangement requires support communication and customer agreement before proceeding.

## 4. Delivery and commercial rules

- The customer-facing delivery fee is selected from the published destination/service schedule and snapshotted at checkout. Supplier identity must not change the published fee after checkout.
- Uyo-local orders use door-to-door delivery. Orders outside Uyo use the assigned logistics company's configured regional handoff method; the method and instructions must be communicated to the customer before dispatch. Do not mark a regional parcel delivered merely because it reached a park/terminal: the required customer receipt OTP and photo evidence still apply.
- Supplier shops and logistics-company headquarters must be physically located in Uyo, Akwa Ibom State. Other origins are rejected for new retailer fulfilment.
- Outside-Uyo destinations must have an explicitly active directional delivery lane. The old automatic nationwide fallback is not authority to accept a destination. Each route is independently enabled/disabled by operations.
- The default logistics-company payable is 90% of the captured delivery fee. An authorized Super Admin may override it higher or lower with a reason and audit trail. Any amount above the fee collected is an explicit VM-funded subsidy/expense. This payable belongs to the logistics company, not directly to its rider.
- Cashback remains active under the existing policy: 5% of eligible post-discount merchandise actually funded by real money through verified payment, excluding delivery and applying all existing redemption, maturity, reversal, validity and recovery terms.
- Supplier payable is based on the immutable agreed supplier cost snapshot and becomes eligible only after the applicable verified fulfilment and reconciliation gates. Supplier recovery is separate from customer refunds.
- Payment success alone never proves stock readiness, dispatch or delivery. Server-side payment verification, explicit state transitions, idempotency and audit records remain mandatory.

## 5. Customer information and support

Customer-facing messages must identify Victorious MARKET as the retailer and support contact. Supplier identities, wholesale costs, internal source choices and internal collection routes remain private unless disclosure is legally necessary or explicitly approved for a support-managed exception.

Suggested customer-facing pickup message:

> Prefer pickup? Please call or WhatsApp Victorious MARKET support to ask whether pickup can be arranged for your complete order. Pickup is not confirmed until our support team verifies availability and sends you the approved collection instructions. Please do not travel to a collection point before confirmation.

## 6. Legacy compatibility and retirement

Existing pickup reservation tables, routes, models, views, state values, historical reservations and old multi-vendor records may be retained temporarily for safe migration, audit, refunds, reporting and historical reads. Their existence does not authorize the active customer journey to create new pickup reservations.

Before removing legacy code/schema, trace all callers, scheduled jobs, financial journals, webhook handlers, tests, seeded/demo data and historical records. Use forward-only migrations, preserve auditability, and prevent new public writes first. Do not delete or reinterpret historical financial records. A documentation update alone does not mean runtime routes, views, APIs or production legal-document rows have already been disabled.

## 7. Required implementation acceptance criteria

1. Standard checkout uses door-to-door delivery inside Uyo and logistics-managed regional delivery outside Uyo; the regional handoff method is disclosed before dispatch.
2. Customer cannot select a supplier/shop or retrieve a private supplier address through checkout, product pages, public APIs or reservation lookup.
3. Pickup reservation creation and supplier-address disclosure are disabled for new customer-facing requests; support-managed exceptions use an authorized, audited process.
4. One order can contain products from multiple private suppliers without requiring multiple customer destinations.
5. The whole order is checked for availability and a feasible fulfilment plan before promising readiness or dispatch.
6. The customer delivery fee is snapshotted once and is not silently recalculated from the selected supplier.
7. Support cannot bypass payment verification, sourcing eligibility, delivery coverage, OTP/photo evidence, refund controls or financial idempotency.
8. Existing reservations/orders remain readable and auditable; no historical rows are mass-deleted or relabelled.
9. Tests cover attempts to create public pickup reservations, disclose addresses early, mix multiple sources, use unavailable sources, duplicate transitions, and preserve legacy reads.
10. Documentation, API/OpenAPI schemas, customer UI, legal terms, admin workflows, tests and production database state are aligned before claiming the change is fully implemented.

## 8. Verification status

This file establishes the current business policy. It does not by itself disable existing pickup endpoints or UI, refactor checkout, migrate production data, update already-published legal terms, or prove CI success. Those runtime and deployment tasks must be tracked separately and verified before release.


## 9. Owner-confirmed geography and delivery modes (21 October 2026)

- Supplier origin: Uyo LGA, Akwa Ibom State only.
- Logistics-company headquarters: Uyo LGA, Akwa Ibom State only.
- Local mode: Uyo destination — door-to-door.
- Regional mode: approved LGAs across Akwa Ibom State and explicitly enabled routes to Aba (Abia), Calabar (Cross River), and Port Harcourt (Rivers). Outside-Uyo delivery is managed by the Uyo-based logistics company and may be park/terminal collection or another agreed arrangement; do not assume doorstep delivery.
- Regional routes must be explicitly active in the directional route matrix. No generic nationwide fallback may make an unconfigured destination purchasable.
- Regional completion still requires order-specific server-verified OTP and mandatory photo proof. The handoff must represent receipt by the customer or authorized recipient, not merely arrival at a park.
