# Documentation Alignment Report — 22 October 2026

## Scope and safety boundary

This pass aligns documentation to [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md). It is documentation-only: no application code, database migrations, test source, runtime configuration, deployment scripts or infrastructure settings were changed. Deployment and audit guides were edited as documentation. A policy statement is not proof of runtime conformity.

## Canonical decisions propagated

- Victorious MARKET is the sole customer-facing retailer; suppliers are private.
- Uyo LGA is the sourcing and delivery boundary. Every supplier has one admin-approved location within that boundary; Plaza is optional.
- Eight corridors remain: Oron Road, Nwaniba Road, Aka Road, Abak Road, Ikot Ekpene Road, Itu Road, Ikpa Road and Plaza.
- Listing availability is separate from order allocation. Hide listings when no eligible in-stock offer remains; manual delisting blocks automatic relisting until admin authorization.
- Paystack verification precedes all binding order-specific supplier allocation, reservation and supplier notification. Pre-payment checks are non-binding.
- Supplier eligibility, SKU-specific 24-hour cooldown, five-active-pending-handover cap and consolidation → lowest wholesale cost → reliability → deterministic tie-break order are aligned.
- One customer order may source from multiple suppliers but remains assigned to one rider from one approved logistics company.
- Any bulky item requires a dedicated exclusive Keke/tricycle trip. Bike batching is limited to qualifying small orders in the same corridor.
- Supplier pickup OTP/photo and customer doorstep OTP/geo-tagged photo are mandatory.
- Customer delivery charge is per order plus configured bulky-unit surcharges. Logistics-company payable is separately configured by company/corridor plus company-specific bulky-unit surcharges. The former 90%-of-customer-delivery-fee rule is retired.
- Supplier payables use immutable wholesale-cost snapshots and are eligible only after verified completion/authorized exception plus reconciliation.
- Cashback remains a separate 5% eligible merchandise benefit under existing caps, maturity, validity and reversal rules.
- Allocation failure is admin-managed, not an automatic refund. Refund completion requires provider confirmation. Changed product/terms require customer consent; transitions are audited and idempotent.
- Support-managed pickup is exceptional, not a public reservation or pre-payment stock hold.

## Documents updated or given explicit policy precedence

- `README.md`
- `.ai/BUSINESS_RULES.md`
- `PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md`
- `VICTORIOUS_MARKET_DETAILED_OVERVIEW.md` (already points to active rules; checked for alignment)
- `VICTORIOUS_MARKET_MASTER_BLUEPRINT.md`
- `backend/README.md`
- `docs/architecture/VICTORIOUS_MARKET_MASTER_BLUEPRINT.md` (replaced duplicate conflicting spec with aligned summary and pointer to root master)
- `SHARED_HOSTING_DEPLOYMENT_GUIDE.md`
- `deploy/PRODUCTION_DEPLOYMENT_MANUAL.md`
- `docs/audit/REAL_WORLD_SCENARIO_TEST_PLAN.md`
- `docs/audit/REMEDIATION_TRACKER.md`
- `docs/audit/STATE_TRANSITION_AND_IDEMPOTENCY_REGISTER.md`

Audit/implementation records preserve historical findings for traceability and are explicitly labeled as historical rather than treated as current business-policy authority. Their old observations are not evidence that current implementation meets the updated contract.

## Branch state and limits

At the time of this pass, the GitHub branch listing for `hotvictorious-rgb/newVmarket` returned only `main`. PR #12 was previously observed closed and unmerged; the historical candidate commit `6d5d3d4e6a853e71cd9bb31fcf3a52b1c606e053` remains a readable historical snapshot, not a live branch ref. Therefore this pass updates the current `main` documentation and does not claim to have pushed changes to deleted/unavailable branch refs. No branches were deleted or merged as part of this task.

## Deep rescan findings and corrections (22 October 2026)

The post-push scan found several AI-drift risks and corrected the active wording in `main`:

- The root master blueprint previously described supplier wallet credit at allocation and instant/daily supplier settlement, conflicting with supplier-payable eligibility only after verified completion/reconciliation. Replaced with separate eligibility, approval, payment and provider-confirmation stages.
- The payment/refund policy still contained a binding 90%-of-customer-delivery-fee payable and 10% default margin despite its own supersession notice. Replaced those active statements with configured company/corridor base payable plus company-specific bulky surcharge; historical vendor rules remain labeled legacy.
- The root blueprint previously used a corridor base fee in the customer delivery-fee formula. Corrected it to one configured flat customer base fee per order, independent of corridor, plus customer bulky-unit surcharge. The corridor tariff remains for logistics-company payable only.
- The root blueprint and payment policy previously described cheapest-per-item supplier selection or weighted consolidation. Replaced with the strict whole-order priority: fewest pickup locations, lowest total wholesale cost among equally consolidated plans, reliability, deterministic tie-break.
- The scenario plan's supposedly current examples still expected a ₦900 payable on a ₦1,000 customer fee. Replaced them with configuration-driven acceptance scenarios, including flat customer fee, payment gate, listing lifecycle, bulky isolation, allocation failure and supplier payable eligibility.
- The production deployment guide described a multi-sided marketplace, vendor app and merchant/rider wallet payouts as the current architecture. Reframed these as legacy assumptions requiring revalidation. Fixed the shared-hosting guide's link to a model document absent from the repository.
- Historical branch references in audit documents are now explicitly historical; they must not be read as proof that branch-specific code is present on `main`.

## Remaining work before implementation or release

1. **Owner decision needed — payout limits scope:** the historical vendor payout policy used a ₦5,000 minimum and ₦500,000 maximum, with payout approval and excess balance remaining eligible. The active retailer model uses supplier payables rather than vendor earnings. Decide whether these same limits and request/approval semantics should govern supplier-payable disbursements, or whether supplier payments need a separate policy. Until then, do not silently apply the legacy vendor-wallet/FIFO policy to supplier payables.
2. Audit code/schema/tests against active rules, especially listing/manual-delist lifecycle, verified-payment allocation gate, whole-order multi-supplier consolidation, flat customer delivery fee independent of corridor, configurable company/corridor logistics payable, bulky isolation, support-managed pickup, supplier payable eligibility and admin-managed paid-order exceptions.
3. Reconcile all historical audit claims with current `main`, CI and runtime before treating any fix as implemented or release-ready. This documentation alignment does not make the repository release-ready.
