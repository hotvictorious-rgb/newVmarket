# Documentation Alignment Report — 22 October 2026

## Scope and safety boundary

This pass aligns documentation to [Active Business Rules](ACTIVE_BUSINESS_RULES_2026-10-22.md). It is documentation-only: no application code, database migrations, test source, runtime configuration, deployment scripts or infrastructure settings were changed. Deployment and audit guides were edited as documentation. A policy statement is not proof of runtime conformity.

## Canonical decisions propagated

- Victorious MARKET is the sole customer-facing retailer; suppliers are private.
- Only an authorized admin may register, activate or suspend suppliers; no supplier self-registration. Suspension blocks new allocations and requires explicit admin review of already-paid/in-flight orders, without silent cancellation or refund.
- Uyo LGA is the sourcing and delivery boundary. Every supplier has one admin-approved location within that boundary; Plaza is optional.
- Eight corridors remain: Oron Road, Nwaniba Road, Aka Road, Abak Road, Ikot Ekpene Road, Itu Road, Ikpa Road and Plaza.
- Listing availability is separate from order allocation. Hide listings when no eligible in-stock offer remains; manual delisting blocks automatic relisting until admin authorization.
- Paystack verification precedes all binding order-specific supplier allocation, reservation and supplier notification. Pre-payment checks are non-binding.
- Supplier eligibility, SKU-specific 24-hour cooldown, five-active-pending-handover cap and consolidation → lowest wholesale cost → reliability → deterministic tie-break order are aligned.
- One customer order may source from multiple suppliers but remains assigned to one rider from one approved logistics company.
- Any bulky item requires a dedicated exclusive Keke/tricycle trip. Bike batching is limited to qualifying small orders in the same corridor.
- Supplier pickup OTP/photo and customer doorstep OTP/geo-tagged photo are mandatory.
- Customer delivery charge is per order plus configured bulky-unit surcharges. Logistics-company payable is separately configured by company/corridor plus company-specific bulky-unit surcharges. The former 90%-of-customer-delivery-fee rule is retired.
- Supplier payables use immutable wholesale-cost snapshots and are eligible only after verified completion/authorized exception plus reconciliation. Supplier-payable disbursements are ₦5,000 minimum and ₦1,000,000 maximum per request/time; FIFO is prohibited, and selection must be authorized, auditable and non-FIFO.
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
- **Revalidated 22 October 2026:** the current payment/refund policy now explicitly states there is no default percentage, no 10% VM margin, and no percentage-based fallback for logistics-company payable. It requires the independently configured company/corridor base payable plus the company-specific bulky surcharge; historical vendor splits remain historical only. The real-world scenario plan's FIFO timeout case is explicitly scoped to historical vendor payouts, while the new supplier-payable acceptance rule separately prohibits FIFO and uses the ₦5,000–₦1,000,000 limits.
- The root blueprint previously used a corridor base fee in the customer delivery-fee formula. Corrected it to one configured flat customer base fee per order, independent of corridor, plus customer bulky-unit surcharge. The corridor tariff remains for logistics-company payable only.
- The root blueprint and payment policy previously described cheapest-per-item supplier selection or weighted consolidation. Replaced with the strict whole-order priority: fewest pickup locations, lowest total wholesale cost among equally consolidated plans, reliability, deterministic tie-break.
- The scenario plan's supposedly current examples still expected a ₦900 payable on a ₦1,000 customer fee. Replaced them with configuration-driven acceptance scenarios, including flat customer fee, payment gate, listing lifecycle, bulky isolation, allocation failure and supplier payable eligibility.
- A payout-timeout scenario still stated FIFO whole-earning allocation as an active expectation. Scoped it explicitly to historical vendor payout records and added the separate active supplier-payable acceptance rule: ₦5,000–₦1,000,000, no FIFO, no invented automatic ranking, and no silent over-cap splitting. The payment policy's remaining active reference to a default 10% VM delivery margin was also removed.
- The production deployment guide described a multi-sided marketplace, vendor app and merchant/rider wallet payouts as the current architecture. Reframed these as legacy assumptions requiring revalidation. Fixed the shared-hosting guide's link to a model document absent from the repository.
- Historical branch references in audit documents are now explicitly historical; they must not be read as proof that branch-specific code is present on `main`.
- The root blueprint now accepts either a trusted verified Paystack webhook or authoritative server-side transaction verification, rather than making one webhook event the only possible proof. Pickup OTP/photo evidence is described per supplier pickup stop, doorstep photo proof is explicitly geo-tagged, and supplier rejection opens an admin-managed exception rather than silent auto-reassignment.
- The root blueprint's broad claim that no status can change without OTP/photo proof was narrowed to physical custody handoffs; unrelated state transitions must satisfy their own authorization/evidence rules. A separate KYC flag is not added as an undocumented supplier eligibility gate; any KYC requirement must be defined in the supplier approval policy.

## Additional contradiction checks — documentation versus source artifacts (22 October 2026)

The main policy documents now agree on the owner-confirmed supplier payout limits and no-FIFO rule. Historical tracker/register entries that describe FIFO and the former ₦500,000 cap are valid only as evidence of the old vendor-earning model; the state-transition register now labels that section accordingly.

The following are **not resolved by documentation edits** and must remain visible as implementation/source-artifact risks:

- **Supplier payout implementation mismatch:** repository search results for `backend/app/Services/RetailerSupplierPayoutService.php`, vendor payout controllers/views and tests still show a ₦500,000 cap and FIFO/oldest-first allocation. This conflicts with the active retailer-model rule of a ₦1,000,000 maximum and prohibited FIFO. Code was not changed in this documentation-only pass. Treat runtime enforcement as non-conforming/unverified until the service, every request/approval/dispatch path, UI and tests are separately corrected and verified.
- **KYC policy mismatch:** active business rules do not yet decide whether KYC is a mandatory component of supplier admin approval, but historical remediation/source artifacts describe a KYC hard gate for listing, sourcing and supplier payout. Do not treat that gate as owner-approved until the owner resolves the KYC standard; preserve this as a decision and implementation audit item.
- **Published legal text embedded in source:** `backend/database/seeders/LegalDocumentSeeder.php` contains customer-facing content that still describes an active 24-hour in-shop reservation, and the About text still describes logistics-company payable as 90% of customer delivery fee / 10% VM margin. These conflict with the current support-managed pickup exception and independent company/corridor tariff policy. The seeder is source code, so it was not edited under the documentation-only constraint. It needs a separately authorized source change and a safe published-legal-document versioning/update plan; changing the seeder alone may not update already-published database records.
- **Legal/document scope:** source-embedded Privacy, Terms, FAQ, cancellation and About content must be audited as a family, including what is actually published in the database. A correct README does not make stale customer-facing legal text safe.

## Remaining work before implementation or release

1. **Owner decision still needed — supplier approval/KYC:** the active rule requires suppliers to be active and admin-approved, but it does not separately define a `kyc_verified` flag or KYC acceptance criteria. Decide whether KYC is a required component of admin approval and document the exact evidence/verification standard before implementing a separate hard gate.
2. Audit code/schema/tests against active rules, especially admin-only supplier registration/activation/suspension, non-FIFO supplier-payable selection and ₦5,000/₦1,000,000 limits, listing/manual-delist lifecycle, verified-payment allocation gate, whole-order multi-supplier consolidation, flat customer delivery fee independent of corridor, configurable company/corridor logistics payable, bulky isolation, stop-level pickup evidence, support-managed pickup, supplier payable eligibility and admin-managed paid-order exceptions.
3. Reconcile all historical audit claims with current `main`, CI and runtime before treating any fix as implemented or release-ready. This documentation alignment does not make the repository release-ready.
