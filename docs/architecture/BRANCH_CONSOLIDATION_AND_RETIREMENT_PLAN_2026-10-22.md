# Victorious MARKET — Branch Consolidation and Retirement Plan
**Review date:** 22 October 2026  
**Repository:** `hotvictorious-rgb/newVmarket`  
**Active integration candidate:** `align/vmarket-single-source-2026-10-22`  
**Release branch:** `main`  
**Status:** In progress; no branches deleted; release verification not green.

## Objective

Maintain one active integration branch for the Uyo-only retailer model while keeping `main` protected as the release branch. Do not keep multiple branches as competing sources of business rules. Do not merge or delete branches solely because their names look old or because a commit-count comparison suggests they are behind.

## Current integration state

- Draft PR #12: `align/vmarket-single-source-2026-10-22` → `docs/retailer-central-fulfilment-model`.
- PR #12 is open, unmerged and not release-approved.
- Latest observed focused workflow: run 39749874290. Composer install and configured PHP syntax checks passed; the financial/audit test step failed with 268 passed, 12 failed (1,772 assertions).
- A schema migration has now been added to make legacy `orders.vendor_id` nullable for centrally owned retailer orders. It is not closed until CI confirms migration/foreign-key compatibility, order tests pass and rollback is safely guarded.
- Checkout currently resolves/snapshots a whole-order source plan before Paystack payment verification. The policy requires binding supplier assignment only after verified payment, or a clearly non-binding quote followed by atomic revalidation/rebinding after payment.
- Therefore, do not merge or retire source branches yet.

## Branch disposition

| Branch | Current comparison to integration candidate | Decision |
|---|---|---|
| `main` | Ancestor/behind candidate; no unique commits shown by the comparison | **Keep permanently.** Protect it; do not delete or force-move. Release to it only through an approved, reviewed PR after all release gates pass. |
| `align/vmarket-single-source-2026-10-22` | Current consolidated documents and implementation candidate | **Only active integration/write branch.** Continue fixes and verification here. |
| `docs/retailer-central-fulfilment-model` | Behind candidate by 116 commits; no unique commits shown in the comparison | **Keep temporarily.** It is the base of PR #12. Do not delete or retarget while that PR is open. Reassess after the PR is resolved and the release path is agreed. |
| `work/victorious-market-single-branch-2026-10-22` | Behind candidate by 116 commits; no unique commits shown | **Retirement candidate after verification.** Confirm no open PR, branch protection, external automation or unrepresented file changes before deleting. |
| `feature/financial-core-engine` | Behind candidate; no unique commits shown | **Retirement candidate after verification.** First confirm all useful financial code, tests and migration history are present in the candidate. |
| `audit/paystack-refund-webhook-safety` | Behind candidate; no unique commits shown | **Keep until finance/security gates pass.** Preserve its audit trail and test findings; archive or delete only after the fixes and tests are demonstrably carried forward. |
| `docs/vendor-managed-delivery-business-rules` | Diverged by 8 commits; comparison reports 3 changed documents | **Historical reference only.** Do not merge its vendor-managed dispatch or marketplace policy into new orders. Preserve any unique security/payment history before archiving. |
| `align/uyo-boundary-reconcile-latest-2026-10-21` | Diverged by 19 commits; comparison reports 15 files | **Review unique storefront, address-boundary, API, seeder and regression-test changes.** Some candidate files already contain newer equivalents; compare file contents, not just commit ancestry. |
| `align/uyo-full-merge-2026-10-21` | Diverged by 146 commits; comparison reports 47 files | **Keep as a source branch until file-level review completes.** It contains integration history across checkout, settlement, logistics, storefront, tests and policy docs. |
| `align/uyo-only-retailer-latest-pass2-2026-10-21` | Diverged by 142 commits; comparison reports 45 files | **Keep as a source branch until file-level review completes.** Candidate already has later changes in some key files, including supplier-stop proof/GPS and bulky snapshots, but unique changes still require review. |
| `align/uyo-only-retailer-latest-pass-2026-10-21` | Diverged by 49 commits; comparison reports 24 files | **Review unique changes before retirement.** |
| `align/uyo-only-retailer-model-2026-10-21` | Diverged by 13 commits; comparison reports 12 files | **Review unique changes before retirement.** |
| `align/uyo-only-retailer-model-current2-2026-10-21` | Diverged by 29 commits; comparison reports 22 files | **Review unique changes before retirement.** |
| `align/uyo-only-retailer-model-current-2026-10-21` | Diverged by 42 commits; comparison reports 22 files | **Review unique changes before retirement.** |
| `align/uyo-only-retailer-model-final-2026-10-21` | Diverged by 22 commits; comparison reports 20 files | **Review unique changes before retirement.** |
| `align/uyo-only-retailer-model-latest-2026-10-21` | Diverged by 22 commits; comparison reports 15 files | **Review unique changes before retirement.** |

*Comparison figures describe GitHub's commit/file comparison, not proof that all file contents are missing from or present in the candidate. Identical file contents and divergent ancestry can coexist.*

## Safe retirement gates

A branch may be retired only after all applicable gates are complete:

1. Compare every file changed uniquely on that branch with the candidate, especially migrations, controllers, services, security guards, payment/refund code, tests and customer-facing copy.
2. Cherry-pick or reimplement useful changes on the integration branch with a clear commit message; do not merge whole divergent histories blindly.
3. Preserve historical marketplace behavior only where needed for historical reads, refunds, reconciliation and audit. It must not authorize new retailer orders.
4. Run the relevant focused tests, migration checks and the full required suite. A skipped test step is not a pass.
5. Confirm there is no open PR, branch protection, automation dependency or unpreserved release evidence.
6. Record the disposition in this file, then archive/delete only the redundant branch. Never delete `main`.

## Required next work

1. Resolve the `orders.vendor_id` schema mismatch with a safe forward migration/backfill.
2. Resolve checkout source-binding timing relative to verified Paystack payment.
3. Close or correctly reclassify the 12 failing tests; preserve security gates rather than weakening them to satisfy stale fixtures.
4. Verify Uyo-only corridor admin UI, company tariff setup, one-rider-per-order assignment, bulky contagion, supplier-stop proof and company/supplier settlement.
5. Re-run CI until required checks pass, then complete branch-by-branch unique-change review.
6. Only then decide which source branches can be retired.

**No branch has been deleted as part of this review.**
