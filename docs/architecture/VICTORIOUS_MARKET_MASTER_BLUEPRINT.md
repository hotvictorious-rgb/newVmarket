# Victorious MARKET — Architecture Blueprint (Aligned Summary)

> **Current authority:** [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md). The former detailed version of this duplicate blueprint contained Plaza-only assumptions and public pickup reservation workflows. Those are retired and must not be implemented. The maintained, detailed master blueprint is [the root Master Blueprint](../../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md). This file intentionally points to that maintained source to prevent duplicated specifications drifting apart.

## Current architecture contract

- **Retailer model:** Victorious MARKET is the only customer-facing retailer. Suppliers remain private sources and each has one admin-approved shop/pickup point anywhere within the approved Uyo LGA boundary; Plaza is optional.
- **Geography:** Sourcing and delivery are Uyo LGA only. The eight delivery corridors are Oron Road, Nwaniba Road, Aka Road, Abak Road, Ikot Ekpene Road, Itu Road, Ikpa Road and Plaza. Unknown/out-of-boundary destinations fail closed.
- **Listing lifecycle:** A product is visible/purchasable only while at least one eligible supplier offer is explicitly in stock with sufficient quantity. Hide it when no eligible offer remains. Manual admin delisting prevents automatic relisting until authorized; preserve history.
- **Payment gate:** Verify Paystack payment, trusted event/signature, reference, amount, currency and idempotency before any binding order-specific supplier allocation, stock reservation, assignment or supplier notification. Pre-payment checks are non-binding.
- **Allocation:** Revalidate after payment. Eligible supplier must be active/approved, inside Uyo, explicitly in stock with enough quantity, outside the SKU-specific 24-hour unavailability cooldown, and below five active pending handovers. Optimize feasible whole-order plans by fewest supplier locations, then lowest total wholesale cost, reliability and deterministic tie-break.
- **Order accountability:** An order may use multiple supplier locations but the full customer order is assigned to one rider from one approved logistics company.
- **Delivery:** Any bulky item makes the full order a dedicated Keke/tricycle trip exclusive to that order. Small-order bike batching is allowed only within the same corridor and subject to capacity/route/safety constraints. Supplier pickup requires one-time pickup OTP plus mandatory photo; doorstep delivery requires customer OTP plus mandatory geo-tagged photo.
- **Pickup exception:** No public reservation or pre-payment stock hold. Support may confirm pickup only after the complete order is ready at one approved point, authorized payment arrangements and customer agreement are confirmed, and required evidence is recorded.
- **Customer delivery charge:** One base fee per order plus configured customer bulky surcharge per applicable physical bulky unit.
- **Logistics payable:** Independently configured logistics-company/corridor base payable plus that company’s bulky-unit surcharge. The old 90%-of-customer-delivery-fee rule is retired. Snapshot tariff/version and calculation. Record any payable shortfall as VM subsidy/expense; the logistics company is the payee.
- **Supplier settlement/cashback:** Supplier payable uses immutable agreed wholesale-cost snapshots and is eligible only after verified completion or authorized exception plus reconciliation. Cashback remains a separate 5% eligible merchandise benefit subject to caps, maturity, validity and refund/recovery rules.
- **Paid-order exception:** Allocation failure is admin-managed, not an automatic refund. Admin may reassign, hold, source an acceptable alternative, cancel or initiate refund with audit; no silent substitution/extra charges. Refund is complete only after provider confirmation.
- **Financial integrity:** Keep receipts/refunds, supplier payables, logistics-company payables, VM margin, delivery revenue/cost, bulky surcharges, cashback and recovery/reconciliation separate. All money/status transitions must be authorized, idempotent and auditable.

## Implementation boundary

This aligned summary states the target contract only. It does not certify the code, schema, tests, deployment or production behaviour. Use the root Master Blueprint and audit documents for detailed implementation history, treating older conflicting audit observations as historical evidence rather than active policy.
