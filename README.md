# Victorious MARKET

> **Current business-policy authority:** [Active Business Rules](docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md). The detailed [Master Blueprint](VICTORIOUS_MARKET_MASTER_BLUEPRINT.md) and [Payment/Refund Policy](PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md) must conform to it. No conflicting legacy wording in another document overrides the active rules.

## Official positioning
**VICTORIOUS MARKET — YOUR TRUSTED ONLINE MARKET IN UYO, AKWA IBOM STATE.**

Victorious MARKET is the sole customer-facing retailer. Suppliers are private behind-the-scenes sources with one admin-approved shop or pickup location anywhere within the approved Uyo LGA boundary. Plaza is optional, not mandatory. Customers see and buy from Victorious MARKET; the company controls listings, retail prices, Paystack collections, fulfilment coordination, customer support, refunds and financial records.

## Binding operational summary
1. **Uyo-only:** source and deliver within the approved Uyo LGA boundary. Unknown or out-of-boundary destinations fail closed.
2. **Listing is not allocation:** a product is purchasable only while at least one eligible supplier offer is explicitly in stock. Hide it when no eligible offer remains. Admin delisting is respected until an authorized admin permits relisting. Preserve history.
3. **Pay first, then allocate:** verify Paystack payment (trusted signature/event, reference, amount, currency and idempotency) before any binding order-specific supplier allocation, reservation or notification. Pre-payment checks are non-binding.
4. **Supplier choice:** active/approved supplier, approved Uyo location, sufficient explicit stock, SKU-specific 24-hour unavailability cooldown expired and fewer than five active pending handovers. Optimize feasible plans by fewest pickup locations, then lowest total wholesale cost, reliability and deterministic tie-break.
5. **One order, one rider/company:** an order may source from multiple suppliers but remains assigned to one rider from one approved logistics company.
6. **Bulky isolation:** any bulky item makes the whole order a dedicated Keke/tricycle trip for that customer only. Small-item bike batching is allowed only within the same corridor and subject to capacity/safety constraints.
7. **Proof:** one-time supplier pickup OTP plus mandatory pickup photo; customer delivery OTP plus mandatory geo-tagged doorstep photo.
8. **Customer delivery fee:** one base fee per order plus configured customer bulky surcharge per applicable physical bulky unit.
9. **Logistics-company payable:** independently configured company/corridor base payable plus that company’s bulky-unit surcharge. The retired 90%-of-customer-delivery-fee rule is not a fallback. Any payable shortfall is explicitly recorded as VM subsidy/expense.
10. **Supplier settlement and cashback:** supplier payable uses immutable wholesale-cost snapshots and becomes eligible after verified completion/reconciliation. Cashback remains a separate 5% eligible merchandise loyalty benefit, subject to caps, maturity and reversals.
11. **Paid-order exceptions:** allocation failure is admin-managed, not an automatic refund. Admin may reassign, hold, source an acceptable alternative, cancel or initiate refund with audit. Refund completion requires provider confirmation. No silent substitution or unconsented extra charge.

## Eight configured corridors
- COR-01 Oron Road
- COR-02 Nwaniba Road
- COR-03 Aka Road
- COR-04 Abak Road
- COR-05 Ikot Ekpene Road
- COR-06 Itu Road
- COR-07 Ikpa Road
- COR-08 Plaza (central core)

The corridor list does not make Plaza a mandatory supplier hub. Admin must maintain clear landmark mapping; unsupported destinations fail closed.

## Customer delivery fee and logistics payable are separate
- **Customer charge:** one configured flat customer base fee per order, independent of corridor, plus configured customer bulky surcharge per applicable physical bulky unit. This is separate from the logistics company's corridor tariff.
- **Logistics payable:** assigned logistics company’s configured corridor base payable + its configured bulky-unit surcharge per applicable physical bulky unit.
- Snapshot the corridor, company, tariff version, base, bulky units/rate and total. If payable exceeds collected delivery revenue, record the difference as VM subsidy/expense. Pay the logistics company, not automatically the rider.

## Exception and pickup controls
Support-managed pickup is exceptional, not a public reservation or pre-payment stock hold. Support must verify complete-order readiness at one approved point, authorized payment arrangements, customer agreement and required evidence before confirming. Never disclose a pickup address before confirmation.

## Implementation status
This README describes policy, not verified runtime behaviour. Code changes are out of scope for this documentation alignment. See the active rules and the audit/settlement documents for implementation checks and unresolved gaps.
