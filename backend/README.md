# Victorious MARKET — Backend Service

> **Binding business rules:** [Active Business Rules](../docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md).  
> **Detailed operating overview:** [VICTORIOUS MARKET Detailed Business Overview](../VICTORIOUS_MARKET_DETAILED_OVERVIEW.md).  
> **Architecture blueprint:** [VICTORIOUS MARKET Master Blueprint](../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md).

Built for the approved Uyo LGA, Akwa Ibom operating area, Victorious MARKET is one centrally managed retailer supported by private, approved suppliers and logistics companies. Suppliers may use one admin-approved shop or pickup location anywhere within Uyo; Plaza is not mandatory.

The active business rules require:

1. **Listing lifecycle separate from allocation:** Keep a product purchasable only while at least one eligible supplier offer is explicitly in stock. Hide it automatically when all eligible offers are out of stock/ineligible, or when an authorized admin delists it. Preserve historical records.
2. **Verified payment before allocation:** Before verified Paystack payment, stock checks and feasibility checks must remain non-binding. No supplier assignment, reservation, binding notification or supplier payable may begin until payment is verified. Revalidate stock after payment before allocation.
3. **Whole-order sourcing and one rider:** A customer order may be sourced from multiple approved supplier locations, but the complete order remains with one rider from one approved logistics company.
4. **Bulky isolation:** Any bulky item makes the whole order a dedicated Keke/tricycle trip for that customer order; do not combine it with another customer's order.
5. **Proof of custody:** Require a unique pickup OTP and mandatory photo proof at each supplier pickup location, plus customer delivery OTP and mandatory geo-tagged doorstep photo.
6. **Separate delivery tariffs:** Customer delivery fee is one base fee per order plus the customer bulky surcharge per applicable physical unit. Logistics-company payable is the admin-configured company/corridor base payable plus that company's configured bulky-unit surcharge. Never derive company payable as 90% of the customer fee.
7. **Admin-managed paid-order exceptions:** Allocation failure does not trigger an automatic refund by itself. Admin can reassign, source an acceptable alternative, hold, cancel or initiate a refund under policy. Refund completion must be confirmed by the payment provider and all actions audited.
8. **Financial separation:** Keep supplier payables, logistics-company payables, customer receipts, refunds, delivery revenue/cost, cashback and reconciliation distinct.

These are business requirements, not a statement that the backend already enforces every requirement. Verify implementation through code review, database/migration checks, admin UI checks and passing tests before release.
