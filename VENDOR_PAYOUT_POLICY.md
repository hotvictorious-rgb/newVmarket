# Legacy Marketplace Vendor Payout Policy — Superseded for New Retailer Transactions

> **Status (21 October 2026):** This policy is retained for historical records and compatibility review only. Victorious MARKET is now operating under a centralized retailer model. Suppliers are product sources, not marketplace vendors, and supplier purchase costs must not be calculated using vendor earnings, commission shares or this policy's payout thresholds.

> **Additional current retailer rule (21 October 2026):** New doorstep delivery fees default to a logistics-company payable equal to 90% of the snapshotted customer delivery fee, with the remaining 10% as default VM delivery margin. An authorized admin may override the company amount higher or lower for actual route and operating conditions; the override must preserve before/final amount, reason, admin identity and timestamp. The company is the payee, not its rider. This logistics-company payable is separate from supplier wholesale payables and from every legacy vendor earning/payout record.

## Current authority

Read [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md), [.ai/BUSINESS_RULES.md](.ai/BUSINESS_RULES.md), and [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md) for current policy.

## Current supplier-payment rule

- Supplier payment eligibility begins only after verified successful customer delivery or confirmed customer pickup and required reconciliation.
- Order placement, customer payment, supplier acceptance/readiness, VM collection, rider assignment, rider collection or a delivery attempt is not enough.
- Eligibility does not mean an automatic transfer. Payables, authorization, payment attempt, provider confirmation, reversal and reconciliation are separate, idempotent and auditable states.
- Supplier payable amounts come from the agreed purchase cost snapshot on the selected supplier offer/order item. They are not vendor commission or a percentage of customer sales unless a separate explicit supplier agreement says otherwise.
- A supplier payment timeout is uncertain, not proof of success or failure. Reconcile before retrying.
- VM remains responsible for a valid customer refund even if a supplier has already been paid. Supplier recovery requires evidence-backed responsibility and contractual recoverability; it must not delay a customer refund.
- No replacement payment rail, monetary threshold, payout SLA, approval threshold or accounting percentage is approved by this document. Do not infer one.

## Historical marketplace policy retained for migration only

The former marketplace rules included a ₦5,000 minimum / ₦500,000 maximum, strict FIFO batches of whole vendor earning records, one active vendor payout request, admin approval and platform-paid vendor transfer fees. Those rules may describe existing historical vendor earnings and transfers, but they **must not be applied to supplier payables** or used to calculate new retailer-model costs.

Do not delete or relabel historical vendor earning/payout/recovery records. Before retiring their code paths, map the source order, payment capture, journal entries, provider transfer references, disputes, recoveries, foreign keys and audit history. Any migration/reclassification requires an approved accounting plan and regression tests.

## Required implementation audit

1. Identify every route/service/job that creates vendor earnings or payout requests.
2. Prevent legacy vendor payout code from treating new supplier purchase costs as marketplace earnings.
3. Introduce or validate supplier-offer and order-item linkage with immutable purchase-cost snapshots.
4. Enforce verified customer delivery/pickup before supplier payment eligibility.
5. Enforce idempotent supplier payment attempts and provider reconciliation.
6. Keep customer refund funding and supplier recovery separate.
7. Preserve all historical ledger entries and prove migration safety before deployment.
