# Victorious MARKET — Audit and Remediation Tracker

**Working branch:** `audit/paystack-refund-webhook-safety`  
**Policy source:** Owner-approved business rules captured in the project audit context.  
**Deployment status:** No deployment or merge performed.  
**Evidence rule:** A code change is not marked verified until the relevant automated tests run successfully.

## Status key

- **FIXED — CI pending:** code and regression test updated; CI result not yet confirmed.
- **OPEN:** confirmed gap remains in code.
- **INVESTIGATING:** path requires further tracing before a safe change.
- **BLOCKED:** cannot safely implement without an owner decision or missing external evidence.

## Remediation register

| ID | Severity | Finding | Approved rule | Change / next action | Status |
|---|---|---|---|---|---|
| VM-AUD-001 | High | Vendor payout request validation allowed ₦1,000–₦50,000,000. | Minimum ₦5,000; maximum ₦500,000. | Updated request validation in `PortalController::vendorRequestPayout`; regression coverage still needs boundary assertions and CI. | FIXED — CI pending |
| VM-AUD-002 | Critical | Payout allocator could split a single order earning record to satisfy a requested amount. | Allocate whole earning records only; strict FIFO; never split or skip; stop before the next record that exceeds the remaining payout cap; oldest over-cap record goes to finance resolution. | Added deterministic `created_at, id` FIFO ordering. Allocation now plans whole records first and rejects a request that cannot be matched exactly, before creating allocations. Replaced the old partial-payout test with a whole-record regression test. | FIXED — CI pending |
| VM-AUD-003 | High | Vendor could create another payout request while an earlier request may still reserve earnings. | One active request across pending, approved-unsent, in-flight, or uncertain states; timeout does not release reservation without reconciliation. | Added a vendor-locked active-request guard for pending/approved/processing/in-flight/uncertain statuses. Confirm actual persisted status vocabulary and test timeout/reconciliation lifecycle. | FIXED — CI pending; lifecycle verification open |
| VM-AUD-004 | Critical | Checkout still appears to support wallet-credit as a payment tender. | Legacy wallet credit is not launch checkout tender; cashback is a separately capped merchandise discount. | Trace all API/controller/service/UI paths, disable tender without breaking separately approved cashback redemption, add negative tests. | OPEN |
| VM-AUD-005 | Critical | Checkout service still creates one shop-scoped order and contains central logistics/fleet lane and rider-payable assumptions. | One parent checkout can create vendor child orders; vendor-managed fulfilment; no central fleet/hub for new orders. | Map data model, payment capture and fulfilment assumptions before implementing parent/child order creation. This is a cross-cutting migration and must not be approximated with a superficial controller patch. | OPEN |
| VM-AUD-006 | High | Return flow has a 24-hour customer window but no confirmed admin route to open late/direct-contact cases for review. | 24-hour standard window; authorised admin can open/review late claims; applicable statutory rights prevail. | Trace admin routes, permissions, audit history and case creation; add controlled late-case opening with reason and audit event. | OPEN |
| VM-AUD-007 | Critical | Return/refund service contains a wallet-credit refund branch. | Launch refunds use verified original Paystack route where supported; no wallet-credit/off-platform refund. | Remove or feature-gate wallet disbursement at launch; preserve reversal of prior wallet-credit liabilities where required; test that admin cannot select wallet as refund destination. | OPEN |
| VM-AUD-008 | Critical | Refund operation approval gate and gateway dispatch paths need end-to-end proof. | Only verified duplicate successful capture or verified payment for order cancelled before vendor action may be auto-approved; all other refunds need persisted human approval before gateway dispatch. | Trace every caller of `createRefundOperation` and `dispatchRefund`; enforce approval evidence in the operation state machine, not just the UI. | INVESTIGATING |
| VM-AUD-009 | High | Vendor recovery ledger can be created in more than one service path; duplicate debt and authorised fault ruling must be proven. | Customer refund is funded by platform when owed; vendor receivable only after authorised evidence-based fault ruling and contractual recoverability; no double recovery. | Reconcile `DoorstepHandshakeService` and `ReturnAndRefundService` recovery creation, idempotency, ruling provenance, repayments, offsets and write-offs. | INVESTIGATING |
| VM-AUD-010 | High | Return case creation rejects expired requests, and admin approval only handles existing cases. | Admin must be able to open/review late or direct-contact cases with recorded reason; statutory rights/warranties prevail. | Add admin-only case opening flow after reviewing schema, policy checks, notification and audit patterns. | OPEN |
| VM-AUD-011 | High | Payout executive gate hardcodes Super Admin above ₦10,000,000. | No owner-approved monetary second-approval threshold; any threshold must be configurable and explicitly approved. | Replace hardcoded amount gate with a configurable policy only after reviewing current role/approval model; avoid silently choosing a new threshold. | OPEN |
| VM-AUD-012 | High | Deleted/renamed logistics and financial audit tests may have removed useful coverage. | Architecture changes must retain relevant safety guarantees even when old logistics implementation is retired. | Map each deleted test invariant to replacement tests and restore coverage for any invariant that remains applicable. | OPEN |
| VM-AUD-013 | High | Refund ceilings and separate merchandise/delivery-fee refund operations need end-to-end verification. | Enforce parent/child/item/component ceilings; merchandise 90/5/5 and delivery fee 90/10 are separate; one component outcome does not imply the other. | Trace admin UI, service, reservations, webhook and ledger reversals; add concurrency and partial-component tests. | INVESTIGATING |
| VM-AUD-014 | High | Required doorstep/in-shop proof and customer OTP confidentiality need end-to-end verification. | Doorstep: server-verified customer OTP + doorstep photo. In-shop pickup: reservation pass + separate one-time customer pickup OTP + pickup photo. OTPs must not leak into history or unauthorised rider views. | Audit API resources, logs, history serialization, pickup and delivery routes; add positive/negative authorization tests. | INVESTIGATING |

## First remediation batch — payout controls

Files changed so far:
- `backend/app/Http/Controllers/Web/PortalController.php`
- `backend/tests/Feature/VendorPayoutLifecycleTest.php`

Changes are on the audit branch only. The automated test suite has **not yet been observed passing after these edits**. Run the targeted payout lifecycle test and the full relevant CI workflow before treating these controls as verified.

## Financial safety principles that remain mandatory

1. Every posted journal entry group balances, but a balanced journal does not prove liquidity or profitability.
2. A vendor receivable is not refund cash. Customer refunds must not wait for vendor recovery where a refund is owed.
3. A complaint alone is not vendor debt. Recovery requires an authorised, evidence-based ruling and a contractual basis.
4. Verify gateway outcomes by provider references and reconciliation; screenshots are not proof of repayment.
5. Timeouts are uncertain outcomes, not proof of failure. Do not release reservations or retry transfers until reconciled.
6. Never mark a change verified based only on code inspection. Require tests, and use a staging/gateway sandbox for provider workflows.
