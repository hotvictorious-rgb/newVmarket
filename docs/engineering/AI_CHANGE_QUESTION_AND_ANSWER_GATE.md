# Victorious MARKET — Mandatory AI Change Question-and-Answer Gate

**Status:** Required engineering policy for every code change.  
**Purpose:** Make every AI-assisted change explainable, policy-aligned, tested, and auditable.  
**Policy authority:** Follow `docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md`, the current approved payment-provider addendum, and other current owner-approved policies. Historical audit documents and old tests are evidence/history, not authority where superseded.

## 1. Non-negotiable rule

Before an AI-assisted change is accepted, the authoring AI must answer every required question in the pull request description, identify the exact code and tests touched, and provide real test output. The answers must be specific to that change. "Done", "looks good", "tests pass", or a policy paraphrase without evidence is not sufficient.

A passing workflow proves only that required answers were supplied. It does **not** prove the answers are truthful, the code is correct, or the entire application is safe. Reviewers must compare answers to the diff and test evidence.

No AI may:
- invent test results, claim commands ran when they did not, or claim production/live-provider proof from mocks;
- weaken, delete, skip, or rewrite a test merely to make a change pass without explaining the policy reason and getting review;
- treat documentation, a test name, a green CI badge, or an AI explanation as proof that runtime behaviour is correct;
- silently change a business policy, payment rail, ledger meaning, permissions, schema, migration, deployment configuration, or security boundary;
- introduce a new dependency, background job, webhook side effect, automatic refund, automatic bank transfer, wallet tender, or other financial side effect without explicit policy authorization;
- merge or deploy a change that fails required checks or has unresolved P0/P1 financial/security issues.

## 2. Required pull-request Q&A

Every pull request must answer all questions below. If a question is not applicable, say why and provide evidence that supports the non-applicability; do not leave it blank.

### Q1 — What exactly changed?
List the files, classes/functions, migrations, routes, jobs, configuration, database effects and user-visible behaviour changed. Link the relevant diff or line ranges.

**Acceptable answer:** Concrete file paths and behaviour.  
**Reject:** "Fixed the system" without details.

### Q2 — Which approved policy permits this?
Name the current policy document and section, state the exact rule, and explain how the implementation follows it. If policy documents conflict, identify the conflict and stop for owner clarification rather than guessing.

**Acceptable answer:** Current policy path + section + implementation mapping.  
**Reject:** Reliance on a superseded branch, historical document, or legacy test alone.

### Q3 — What are the preconditions, state transitions, and authorization checks?
Describe initial state, allowed transition, forbidden transitions, actor permissions, evidence requirements, and resulting state. Include failure/unknown states and recovery behaviour.

**Acceptable answer:** Explicit state-transition table or equivalent test-linked explanation.  
**Reject:** Happy-path-only description.

### Q4 — What can go wrong, and how does the code fail safely?
Cover invalid input, duplicate requests, concurrent requests, retries, timeout, crash/restart, partial database writes, provider/bank uncertainty, stale data, authorization failure, and external service outage as relevant.

**Acceptable answer:** Each applicable failure mode has a safe outcome and regression test or a documented follow-up blocker.  
**Reject:** "Laravel handles it" or "the webhook is secure" without evidence.

### Q5 — How are duplicate and concurrent effects prevented?
Identify database constraints, transaction boundaries, locks, idempotency keys, uniqueness rules, replay checks, and exactly-once *business effect* guarantees. Explain what happens if two workers/admins act at the same time.

**Acceptable answer:** Concrete code/schema safeguards plus concurrency tests where relevant.  
**Reject:** An application-level pre-check alone when concurrent requests could race.

### Q6 — What are the financial and audit consequences?
For money-affecting changes, state the debit/credit or balance effect, amount/currency rounding strategy, immutable snapshots, ledger entries, reconciliation path, audit actor/reason, and reversal/retry semantics. For non-financial changes, explain why no financial state can be affected.

**Acceptable answer:** Reconciles before/after states and proves no duplicate money effect.  
**Reject:** Treating a journal entry as proof cash moved, or treating screenshots as proof a bank credit occurred.

### Q7 — Which tests were added or changed, and what did they actually prove?
List exact test names and commands, actual pass/fail/skip counts, and environment. Include regression, negative/authorization, boundary, idempotency, and concurrency/crash-recovery tests as applicable. Clearly label mocks/fakes versus real integration tests.

**Acceptable answer:** Actual command output and test names, with limitations.  
**Reject:** Invented output, "all tests passed" after running only a targeted subset, or mock tests described as live provider proof.

### Q8 — What did you inspect beyond the changed lines?
Explain callers, routes, models, migrations, jobs, webhooks, policy docs, dependent services, related tests, and backward compatibility reviewed. State what was not inspected.

**Acceptable answer:** Relevant cross-file review and explicit limitations.  
**Reject:** Claiming a deep audit from a single-file edit.

### Q9 — Did you remove, weaken, skip, or alter any existing test or control?
Answer yes/no. If yes, list each item, the policy reason, replacement coverage, and approval. Removing legacy-conflicting tests requires showing which current policy they conflict with and preserving tests that still protect valid invariants.

### Q10 — Does this preserve the current Victorious MARKET operating model?
Explicitly check applicable invariants:
- one customer-facing retailer; supplier identities/private wholesale costs are not exposed to customers;
- Uyo-only suppliers and delivery; unknown/out-of-boundary destinations fail closed;
- authenticated customer checkout; pay before binding supplier allocation;
- Paystack and manual bank transfer may coexist globally, but each order uses exactly one immutable payment rail;
- manual-transfer screenshots are evidence only; actual receiving-account credit is independently verified; one bank transaction applies to at most one order;
- no COD, no supplier collection/off-platform payment, and no public pickup reservation;
- supplier confirms ability to fulfil the full required quantity; exhausted sourcing goes to admin-managed exception handling;
- one rider/logistics company per order; bulky items isolate the order to a dedicated suitable trip;
- required pickup/delivery OTPs and photo evidence remain enforced;
- customer delivery fee and logistics-company payable are independently configured and snapshotted;
- supplier/logistics settlements are manually authorized/dispatched, within ₦1–₦1,000,000 per transfer/request, without FIFO or silent splitting; disbursements Mon–Fri after cleared/reconciled funds;
- no script, webhook, queue, cron, or timeout autonomously dispatches refunds or bank transfers;
- Paystack refunds apply only to Paystack-paid orders through the approved route; manual-transfer refunds must never call the Paystack refund API;
- balanced, auditable accounting; no fabricated cash, no duplicate financial effect, no silent wallet-credit fallback.

Mark each invariant applicable/not applicable and explain why. Do not claim every invariant was tested by every PR.

### Q11 — What are the risks, deployment requirements, and rollback plan?
State severity, migration/backfill/config/secret implications, monitoring/alerts, compatibility risks, rollback/recovery steps, and whether rollback itself could duplicate or lose financial effects.

### Q12 — What remains unproven?
List all skipped tests, environment limitations, known gaps, unresolved policy ambiguities, manual steps, and production-only checks. State clearly whether this change is safe to merge, safe to deploy, both, or neither—and why.

## 3. Minimum test design checklist

Add tests that match the risk of the change. Use the applicable rows below as a test-design catalogue; not every row must be duplicated in every PR, but every touched domain must retain adequate coverage.

| Domain | Required question/scenario | Expected safe result |
|---|---|---|
| Payment rail isolation | Can Paystack evidence pay a manual-transfer order, or vice versa? | Reject; no state/ledger/sourcing side effect |
| Payment uniqueness | Can one provider/bank transaction pay two orders? | Database-backed uniqueness prevents reuse |
| Payment idempotency | Can repeated callback/review apply payment twice? | One paid transition and one set of financial effects |
| Payment concurrency | Can two workers approve the same payment simultaneously? | One wins; second is safely idempotent/rejected |
| Manual transfer evidence | Is a screenshot or customer-supplied reference enough? | No; actual credit must be independently verified |
| Payment-before-allocation | Can supplier binding/notification occur before verified payment? | No; pre-payment checks remain non-binding |
| Supplier quantity | Can allocation occur without confirmation of full order-line quantity? | No; fail over or admin exception |
| Supplier failover | What if all eligible suppliers reject/time out/become ineligible? | Bounded attempts, audit trail, admin exception; no silent refund |
| Uyo boundary | What if supplier or destination is outside/unknown Uyo boundary? | Fail closed |
| Listing availability | What if all offers become ineligible or admin delisted the item? | Unavailable; history retained |
| Delivery assignment | Can one order have two riders/companies? | Reject or preserve one assignment invariant |
| Bulky contagion | Does one bulky item permit another customer's order in same trip? | No; dedicated trip |
| Delivery proof | Can order complete without required OTP/photo evidence? | No, except explicitly authorized, audited exception policy |
| Refund authorization | Can queue/webhook/cron send a refund automatically? | No; human Super Admin authorization and manual dispatch |
| Refund rail | Can a manual-transfer refund call Paystack refund API? | Never |
| Refund state | Is request/approval/submission/provider completion conflated? | Distinct states; completion only on verified provider/bank outcome |
| Ledger balance | Can any event create unbalanced journal legs? | Reject/rollback; debits equal credits for each balanced event |
| Cash reality | Can journal or screenshot alone imply cash settled? | No; actual bank/provider outcome must reconcile |
| Settlement limits | Are ₦1 minimum and ₦1,000,000 maximum enforced per request/transfer? | Boundary accepted; below/above rejected |
| Settlement policy | Is FIFO or silent splitting introduced? | No |
| Settlement retry | Can uncertain transfer be resent before original outcome reconciled? | No; hold as uncertain and reconcile first |
| Settlement concurrency | Can simultaneous requests over-reserve payable or treasury funds? | Locks/constraints allow only valid reservations |
| Auditability | Can privileged action occur without actor, reason, timestamp, before/after context? | Reject or require auditable exception |
| Crash recovery | Can crash after external success but before local persistence duplicate a transfer/refund? | Reconcile by stable provider/bank reference; never blindly resend |
| Access control | Can unauthorized customer/supplier/admin reach another party's records/actions? | Deny and test ownership/role boundaries |
| Data privacy | Are supplier identities, wholesale costs, private account details, or evidence files leaked? | No; authorization and safe storage enforced |
| Regression | Did old tests encode superseded policy? | Document conflict; remove only conflicting assertion and preserve valid safeguards |
| Full suite | Did the targeted fix break unrelated workflows? | Run the broad suite or disclose why not run and block release if required |

## 4. Merge and release decision

- A pull request must not merge with missing answers, fabricated evidence, failed required tests, unresolved policy conflict, or an unaddressed P0/P1 financial/security defect.
- A green Q&A workflow validates answer presence/format only. A human reviewer (owner/authorized maintainer) must compare answers with the diff and evidence.
- Enable the workflow as a required status check in GitHub branch protection/rulesets for `main`. Without that repository setting, a workflow can report failure but may not technically block merging.
- Deployment is a separate gate. CI does not prove live bank reconciliation, real Paystack behaviour, production permissions, backup recovery, or operational readiness.
- Do not weaken branch protection to bypass a failed gate. If an exception is necessary, document the reason, risk, approver, expiry and compensating control.

## 5. AI instruction (copy into coding-agent instructions)

> Before editing, read this gate and the current policy authority. Inspect relevant implementation, callers, schema, migrations, workflows, and tests. Make the smallest policy-compliant change. Add or update regression tests. Run the relevant tests and the broadest feasible suite. Answer Q1–Q12 in the PR description using exact code paths and real evidence. Disclose all failures, skips, limitations, and unproven claims. Never invent results. Stop and ask for owner clarification if policy conflicts or the implementation would require a policy change. Do not merge or deploy unless the required checks and authorization are satisfied.
