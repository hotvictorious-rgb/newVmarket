> **Policy authority:** [Active Business Rules](https://github.com/hotvictorious-rgb/newVmarket/blob/main/docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md) governs the backend target contract. Pickup is not a public pre-payment reservation or stock hold. No code was changed in this documentation pass.

# Victorious MARKET — Backend Service

> **Binding business rules:** [Active Business Rules](../docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md).  
> **Detailed operating overview:** [VICTORIOUS MARKET Detailed Business Overview](../VICTORIOUS_MARKET_DETAILED_OVERVIEW.md).  
> **Architecture blueprint:** [VICTORIOUS MARKET Master Blueprint](../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md).  
> **Operational playbook:** [Operational Workflow & Exception Playbook](../docs/architecture/OPERATIONAL_WORKFLOW_AND_EXCEPTION_PLAYBOOK.md).

Built for the approved Uyo LGA, Akwa Ibom operating area, Victorious MARKET is one centrally managed retailer supported by private, approved suppliers and logistics companies. Suppliers may use one admin-approved shop or pickup location anywhere within Uyo; Plaza is not mandatory.

The active business rules require:

0. **Supplier administration:** supplier registration, activation and suspension are admin-only; self-registration is not allowed. Suspension blocks new allocation and requires admin review of paid/in-flight orders.

1. **Listing lifecycle separate from allocation:** Keep a product purchasable only while at least one eligible supplier offer is explicitly in stock. Hide it automatically when all eligible offers are out of stock/ineligible, or when an authorized admin delists it. Preserve historical records.
2. **Verified payment before allocation:** Before verified Paystack payment, stock checks and feasibility checks must remain non-binding. No supplier assignment, support-managed pickup exception, binding notification or supplier payable may begin until payment is verified. Revalidate stock after payment before allocation.
3. **Whole-order sourcing and one rider:** Compare only feasible plans that fulfil the complete order; minimize supplier pickup locations first, then total wholesale cost among equally consolidated plans, then reliability and a deterministic tie-break. A customer order may use multiple approved supplier locations but remains with one rider from one approved logistics company.
4. **Bulky isolation:** Any bulky item makes the whole order a dedicated Keke/tricycle trip for that customer order; do not combine it with another customer's order.
5. **Proof of custody:** Require a unique pickup OTP and mandatory photo proof at each supplier pickup location, plus customer delivery OTP and mandatory geo-tagged doorstep photo.
6. **Separate delivery tariffs:** Customer delivery fee is one configured flat base fee per order, independent of corridor, plus the customer bulky surcharge per applicable physical unit. Logistics-company payable is the admin-configured company/corridor base payable plus that company's configured bulky-unit surcharge. Never derive company payable as 90% of the customer fee.
7. **Admin-managed paid-order exceptions:** Allocation failure does not trigger an automatic refund by itself. Admin can reassign, source an acceptable alternative, hold, cancel or initiate a refund under policy. Refund completion must be confirmed by the payment provider and all actions audited.
8. **Supplier-payable payment limits:** Each supplier payment request is at least ₦5,000 and at most ₦1,000,000 at a time. FIFO/oldest-first selection is prohibited; the selected eligible payables and approving admin must be auditable. Do not split an over-cap request automatically.
9. **Financial separation:** Keep supplier payables, logistics-company payables, customer receipts, refunds, delivery revenue/cost, cashback and reconciliation distinct.

These are business requirements, not a statement that the backend already enforces every requirement. Verify implementation through code review, database/migration checks, admin UI checks and passing tests before release.
