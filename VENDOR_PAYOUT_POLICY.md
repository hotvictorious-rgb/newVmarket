# VICTORIOUS MARKET — Vendor Payout Policy

**Status:** Owner-confirmed launch policy  
**Last reviewed:** 20 October 2026  
**Applies to:** Vendor payouts only; does not create rider, fleet, or legacy logistics payout entitlements.  
**Implementation status:** Documentation alignment only. Code alignment and verification remain open.  
**Authority:** This document and `.ai/BUSINESS_RULES.md` define the launch payout policy. The payment/refund/webhook matrix defines the technical controls.

## 1. Launch rules

1. **Minimum payout request:** ₦5,000.
2. **Maximum payout request:** ₦500,000 per request.
3. **Request frequency:** A vendor may submit a request whenever its unreserved eligible/withdrawable balance is at least ₦5,000. Reaching the minimum does not trigger an automatic transfer.
4. **Approval:** Every payout request requires review and approval by an authorized administrator before a Paystack transfer may be initiated. Request creation and approval are separate actions; approval is not proof of payment.
5. **Excess balance:** A vendor may request up to ₦500,000. Eligible balance above the approved request remains eligible for a later request, subject to all eligibility, security, recovery, dispute, and liquidity checks. No eligible earnings are forfeited merely because they exceed the cap.
6. **Payout aggregation:** Earnings are calculated and recorded per vendor order-item/earning record, including delivery-fee earnings separately. The normal payout method aggregates the selected eligible earnings into one payout request and one transfer; the platform must not create a bank transfer for each order by default.
7. **No rider payouts:** Vendors pay their own riders outside Victorious MARKET. No rider wallet, rider earning, or rider payable is created by this policy.

## 2. Eligible balance and request calculation

The backend must calculate eligible, unreserved withdrawable funds from authoritative ledger/earning records. Client-submitted amounts are requests, never trusted balances. The server must reject requests below ₦5,000, above ₦500,000, above the vendor's unreserved eligible balance, or from an ineligible vendor.

Eligible earnings must have passed the applicable payment verification, fulfilment and customer-protection window, refund/return/dispute, KYC/vendor-status, bank-account security, recovery-offset, and reconciliation checks. Held, reserved, paid, reversed, disputed, return-pending, unreconciled, or otherwise blocked earnings are excluded.

## 3. Reservation, concurrency and review

- Create the request and reserve the exact amount/earning allocations atomically under database transaction and row locks.
- The same earnings must never back two active payout requests. Concurrent or duplicate submissions must not double-reserve funds.
- The request records the beneficiary and bank-account details used for that request, amount, currency, timestamps, state, decision maker, decision/rejection reason, provider reference, and audit history.
- An authorized administrator reviews the vendor status/KYC, bank details and 48-hour bank-change cooldown, open disputes/returns, recoveries/debt, payout history, and funding availability before approval.
- Rejection releases the request's reservation exactly once; it does not erase earning history or forfeit eligible funds.
- An approved request may be submitted to Paystack only through the authorized disbursement flow. Approval must not mark the payout as paid.

## 4. Transfer status and reconciliation

Track request review state separately from provider transfer state. At minimum, distinguish pending review, approved, rejected, processing/in-flight, successful, failed, reversed, and uncertain/needs reconciliation (using the application's mapped states where appropriate).

Persist a unique transfer reference and processing identity before contacting Paystack. A timeout or unknown result must be reconciled against the same reference before any retry. Never blindly repeat a transfer, treat a pending result as success, or let a late webhook overwrite an already confirmed outcome. Finalize the payout and post balanced ledger entries exactly once only after authoritative confirmation of success. Failed/reversed transfers must restore or adjust the same reservation/source earnings through an audited, idempotent recovery flow.

## 5. Platform liquidity and provider limits

The ₦500,000 limit is Victorious MARKET's own per-request control, not a statement of a Nigerian statutory bank limit or a guarantee that Paystack will approve every transfer. Before transfer initiation, check actual available provider funding and any account-specific/provider limits, plus the transfer amount and applicable charges. Never mark a request paid when funding is insufficient or the provider outcome is uncertain.

## 6. Transfer fees and stamp duty — owner decision required

**Open decision:** Whether Victorious MARKET absorbs outbound transfer fees and applicable stamp duty or deducts them from vendor payouts has not been confirmed by this policy update. Do not silently deduct charges from vendor earnings. Before launch, record the approved fee bearer, show any vendor deduction before request confirmation if one is approved, and reconcile the actual charge separately in the financial ledger.

## 7. Vendor examples

- Eligible balance ₦4,999: cannot submit a payout request yet.
- Eligible balance ₦80,000: may request ₦80,000 or a smaller amount, provided the request is at least ₦5,000.
- Eligible balance ₦720,000: may request up to ₦500,000; the remaining ₦220,000 stays eligible for a later request, subject to normal checks.
- Existing pending request ₦100,000 and total eligible earnings ₦300,000: the reserved ₦100,000 cannot be requested again; other unreserved eligible funds may be considered for a separate request only if the system's concurrency and review controls safely support it.

## 8. Required implementation and verification

Documentation is not proof of implementation. Before launch, align the API and web request endpoints, vendor dashboard, admin approval/disbursement flow, payout ledger/reservations, Paystack transfer initiation and webhooks, reconciliation, notifications, tests, legal/merchant-facing wording, and any configuration values with this policy. Tests must cover minimum/maximum boundaries, excess carry-forward, duplicate/concurrent requests, approval bypass attempts, rejection release, insufficient provider balance, timeouts, duplicate/late webhooks, failed/reversed transfers, and exact-once ledger posting.

Do not merge broad audit-branch changes into `main` without explicit owner approval.
