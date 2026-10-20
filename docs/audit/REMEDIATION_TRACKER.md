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
| VM-AUD-001 | High | Vendor payout request validation allowed ₦1,000–₦50,000,000. | Minimum ₦5,000; maximum ₦500,000. | Updated request validation and added a second min/max guard at admin transfer dispatch to block legacy/out-of-policy payout rows. | FIXED — CI passed |
| VM-AUD-002 | Critical | Payout allocator could split a single order earning record to satisfy a requested amount. | Allocate whole earning records only; strict FIFO; never split or skip; stop before the next record that exceeds the remaining payout cap; oldest over-cap record goes to finance resolution. | Added deterministic `created_at, id` FIFO ordering. Both request creation and transfer dispatch now verify whole-record FIFO totals; malformed requests are rejected before creating a payout request/reservation. Allocation plans are checked before rows are created. Replaced the old partial-payout test with a whole-record regression test that also checks the active-request guard. | FIXED — CI passed |
| VM-AUD-003 | High | Vendor could create another payout request while an earlier request may still reserve earnings. | One active request across pending, approved-unsent, in-flight, or uncertain states; timeout does not release reservation without reconciliation. | Added a vendor-locked active-request guard for pending/approved/processing/in-flight/uncertain statuses. Confirm actual persisted status vocabulary and test timeout/reconciliation lifecycle. | FIXED — CI passed; lifecycle vocabulary/reconciliation tests remain open |
| VM-AUD-004 | Critical | Checkout supported legacy wallet-credit as a payment tender through both the checkout API and checkout-intent flow. | Legacy wallet credit is not launch checkout tender; cashback is a separately capped merchandise discount. | Restricted payment method to Paystack, rejected positive wallet-credit redemption in both API validation paths, added a service-level defense, and replaced the three wallet-tender success tests with rejection/no-spend tests. Search remaining routes and UI; CI passed for current regression suite. | FIXED — CI passed; residual path audit open |
| VM-AUD-005 | Critical | Checkout service still creates one shop-scoped order and contains central logistics/fleet lane and rider-payable assumptions. | One parent checkout can create vendor child orders; vendor-managed fulfilment; no central fleet/hub for new orders. | Map data model, payment capture and fulfilment assumptions before implementing parent/child order creation. This is a cross-cutting migration and must not be approximated with a superficial controller patch. | OPEN |
| VM-AUD-006 | High | Return flow has a 24-hour customer window but no confirmed admin route to open late/direct-contact cases for review. | 24-hour standard window; authorised admin can open/review late claims; applicable statutory rights prevail. | Trace admin routes, permissions, audit history and case creation; add controlled late-case opening with reason and audit event. | OPEN |
| VM-AUD-007 | Critical | Return/refund service contains legacy wallet-credit branch code. | Launch refunds use verified original Paystack route where supported; no wallet-credit/off-platform refund. | Service-boundary guard already rejects every non-empty manual reference, including `wallet`, before the legacy branch; existing regression test asserts wallet refund is disabled and no credit is created. Keep branch unreachable and preserve the guard. | FIXED — existing guard and regression test; CI passed |
| VM-AUD-008 | Critical | Refund dispatch lacked a persisted approval/exception gate at the final gateway boundary. | Only verified duplicate successful capture or verified payment for order cancelled before vendor action may be auto-approved; all other refunds need persisted human approval before gateway dispatch. | Added `refund_authorization` metadata to financial operations and a final `FinancialOperationService` gate. Return-case dispatch requires a matching persisted admin reviewer; automated dispatch is restricted to the verified paid-stock-unavailable/refund-pending state. Reconciliation skips requests without either authorization. Added negative and approved-exception regression tests. CI found and helped correct a missing closure capture; current full run pending. Duplicate-capture exception still needs separate path audit. | FIXED — CI pending; duplicate-capture exception audit open |
| VM-AUD-009 | High | Vendor recovery ledger can be created in more than one service path; duplicate debt and authorised fault ruling must be proven. | Customer refund is funded by platform when owed; vendor receivable only after authorised evidence-based fault ruling and contractual recoverability; no double recovery. | Reconcile `DoorstepHandshakeService` and `ReturnAndRefundService` recovery creation, idempotency, ruling provenance, repayments, offsets and write-offs. | INVESTIGATING |
| VM-AUD-010 | High | Return case creation rejected expired requests and admin approval only handled existing cases. | Admin must be able to open/review late or direct-contact cases with recorded reason; statutory rights/warranties prevail. | Added a Super Admin-only late/direct-contact intake route and desk form. It requires an order, item/quantity, customer report and override reason; service retains ownership/item quantity checks, marks the case `requested` (not approved), and records an immutable admin audit event. Customer API's original 24-hour rejection remains unchanged. Regression test added; CI pending. | FIXED — CI pending |
| VM-AUD-011 | High | Payout executive gate hardcodes Super Admin above ₦10,000,000. | No owner-approved monetary second-approval threshold; any threshold must be configurable and explicitly approved. | Replace hardcoded amount gate with a configurable policy only after reviewing current role/approval model; avoid silently choosing a new threshold. | OPEN |
| VM-AUD-012 | High | Deleted/renamed logistics and financial audit tests may have removed useful coverage. | Architecture changes must retain relevant safety guarantees even when old logistics implementation is retired. | Map each deleted test invariant to replacement tests and restore coverage for any invariant that remains applicable. | OPEN |
| VM-AUD-013 | High | Refund ceilings and separate merchandise/delivery-fee refund operations need end-to-end verification. | Enforce parent/child/item/component ceilings; merchandise 90/5/5 and delivery fee 90/10 are separate; one component outcome does not imply the other. | Trace admin UI, service, reservations, webhook and ledger reversals; add concurrency and partial-component tests. | INVESTIGATING |
| VM-AUD-014 | High | Required doorstep/in-shop proof and customer OTP confidentiality need end-to-end verification. | Doorstep: server-verified customer OTP + doorstep photo. In-shop pickup: reservation pass + separate one-time customer pickup OTP + pickup photo. OTPs must not leak into history or unauthorised rider views. | Audit API resources, logs, history serialization, pickup and delivery routes; add positive/negative authorization tests. | INVESTIGATING |
| VM-AUD-015 | High | Return approval and refund disbursement actions referenced an undefined `$validated` variable and incorrectly attempted dispute-arbitration notification dispatch. | Return approval/disbursement must complete using persisted case state and the correct notification workflow; no runtime undefined-variable errors. | Removed the two invalid dispute-notification calls from approve/disburse handlers; retained the validated ruling notification in the actual arbitration handler. CI pending. | FIXED — CI pending |

## First remediation batch — payout controls, launch payment guard, and refund authorization

Files changed so far:
- `backend/app/Http/Controllers/Web/PortalController.php`
- `backend/tests/Feature/VendorPayoutLifecycleTest.php`
- `backend/app/Http/Controllers/Api/CustomerOrderApiController.php`
- `backend/app/Http/Controllers/Api/CheckoutAndReservationApiController.php`
- `backend/app/Http/Requests/CreateCheckoutIntentRequest.php`
- `backend/app/Services/CheckoutExecutionService.php`
- `backend/tests/Feature/DoorstepPartialAcceptanceAndVendorReturnTest.php`
- `backend/app/Services/FinancialOperationService.php`
- `backend/app/Services/ReturnAndRefundService.php`
- `backend/app/Services/OrderSettlementService.php`
- `backend/app/Services/FinancialReconciliationEngine.php`
- `backend/app/Http/Controllers/Web/PaystackPaymentController.php`
- `backend/tests/Feature/FinancialAuditRemediationProofTest.php`
- `backend/resources/views/admin/returns.blade.php`
- `backend/routes/web.php`

Changes are on the audit branch only. The workflow caught and helped correct payout-test expectations, optional-field validation behavior, and malformed test namespace syntax. Commit `421068f3b2c042891446ea6084790ff955bac7e4` passed PHP syntax checks and **147 tests / 917 assertions** (https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39476905725). The whole-record FIFO pre-reservation change and its updated lifecycle tests passed CI on commit `32d17bb23672c25dcb92ab65dc85a90d463e9f18` (https://github.com/hotvictorious-rgb/newVmarket/actions/runs/39477261585). Refund authorization gating is now added, but a first run exposed a reconciliation fixture that described a generic cancelled order rather than the approved late-payment exception; the fixture has been corrected to match the actual policy. Current refund-gate tests are awaiting CI. This targeted suite does not prove full application or production gateway readiness.

## Financial safety principles that remain mandatory

1. Every posted journal entry group balances, but a balanced journal does not prove liquidity or profitability.
2. A vendor receivable is not refund cash. Customer refunds must not wait for vendor recovery where a refund is owed.
3. A complaint alone is not vendor debt. Recovery requires an authorised, evidence-based ruling and a contractual basis.
4. Verify gateway outcomes by provider references and reconciliation; screenshots are not proof of repayment.
5. Timeouts are uncertain outcomes, not proof of failure. Do not release reservations or retry transfers until reconciled.
6. Never mark a change verified based only on code inspection. Require tests, and use a staging/gateway sandbox for provider workflows.
