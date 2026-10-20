# VICTORIOUS MARKET — Vendor Earnings and Payout Policy

**Version:** 1.0  
**Policy date:** 20 October 2026  
**Status:** Owner-confirmed launch policy; implementation and verification remain open  
**Authority:** Read with [VICTORIOUS_MARKET_SYSTEM_RULES_V1.md](VICTORIOUS_MARKET_SYSTEM_RULES_V1.md). This policy does not create a vendor wallet, rider payout, or automatic bank transfer.

## 1. Vendor earnings eligibility

1. Vendor earnings become eligible for payout 24 hours after confirmed doorstep delivery or confirmed in-shop pickup, provided the affected earning has no applicable return/dispute/payment hold and all vendor, security and reconciliation checks pass.
2. The 24-hour eligibility rule does not prevent an authorised admin from opening or approving a legitimate late refund case. If a vendor has already been paid and is responsible for an approved refund, record a separate recovery receivable.
3. Eligible earnings are not an automatic transfer. Vendors request a payout through the platform, and an authorised administrator manually approves every payout request at launch.
4. Track merchandise earnings per order-item earning record and delivery-fee earnings as separate records linked to the vendor child order. Use item-level records where practical so an unrelated item is not unnecessarily held.
5. A held, reserved, paid, reversed, disputed, return-pending, unreconciled, or otherwise blocked earning cannot be selected for a new payout.

## 2. Request thresholds and server-calculated FIFO batch

- **Minimum payout:** ₦5,000.
- **Maximum payout:** ₦500,000 per request.
- The vendor does not enter the payout amount. The server calculates the request from the oldest eligible, unreserved earning records.
- Select whole earning records in strict FIFO order. Add the oldest record in full while the cumulative total remains at or below ₦500,000.
- If adding the next oldest whole record would make the total exceed ₦500,000, stop immediately. Do not split that record and do not skip it to select newer records. That record and all newer records remain available for a later batch.
- If the calculated batch total is below ₦5,000, no request is created until enough eligible records can be included without breaking the cap.
- If the oldest eligible record alone exceeds ₦500,000, do not split or skip it. Flag the case for authorised finance review and an explicit accounting-safe resolution; do not silently bypass FIFO.
- Excess eligible earnings remain eligible for later requests, subject to holds and normal checks. Earnings are never forfeited just because they exceed the cap.

### Example

Eligible whole earning records, oldest first: ₦200,000, ₦180,000, ₦170,000.

The first request is **₦380,000**. Adding the next full record would make ₦550,000, so the system stops. It does not split the ₦170,000 record into ₦120,000 and does not skip it. The ₦170,000 record remains for the next payout batch.

## 3. One active request and reservations

- A vendor may have only one active payout request while it is pending review, approved but not sent, processing/in-flight, or uncertain/awaiting reconciliation.
- A new request is allowed only after the previous request is definitively rejected, paid, failed/reversed and reconciled, or otherwise closed with a final auditable outcome.
- Create the request and reserve its exact earning-record allocations atomically under a database transaction and appropriate row locks/unique constraints.
- No earning record may support two active requests or be paid twice.
- Rejection releases the reservation exactly once and preserves the original earnings history.
- Before approval, admin reviews vendor/KYC status, bank details and the 48-hour bank-change cooldown, open disputes/returns, confirmed recoveries/debts, prior payout status, and available platform/provider funding.
- Admin approval is separate from transfer initiation and is never proof of payment.

## 4. Vendor debt, disputes and recoveries

- Confirmed vendor debt is tracked in a separate disputes/recoveries ledger, not as a spendable vendor wallet balance.
- A vendor may repay a confirmed debt by direct bank transfer to the designated Victorious MARKET account. The platform must verify receipt against bank/provider records and attach it to a unique recovery reference, vendor, case, original order and amount. A screenshot or promise alone is not proof of repayment.
- Alternatively, an authorised admin may approve an offset against the vendor's held or future earnings where the vendor agreement permits it. Do not automatically offset a debt without recorded admin approval.
- The vendor must be notified and may challenge a proposed recovery. An allegation or unresolved complaint is not a confirmed debt.
- If the vendor was already paid, record a recovery receivable; do not pretend that a completed bank transfer has been reversed. Direct repayment and approved future-earnings offset are the available recovery paths.
- Track disputed, confirmed, outstanding, partially repaid, fully recovered and written-off states. Correct errors with linked reversing/adjustment entries, never by deleting the original record.

## 5. Transfer and fee policy

- Victorious MARKET bears outbound vendor payout transfer charges, including applicable transfer fees/stamp duty charged for the payout, as a platform expense. The vendor receives the exact approved payout amount; never deduct payout charges from vendor earnings.
- Check actual platform/provider funding and account-specific transfer limits before initiation. The ₦500,000 cap is a platform policy limit, not a statement of a statutory bank limit or guarantee of provider approval.
- Persist a unique transfer reference and processing identity before contacting Paystack.
- Distinguish pending review, approved, processing/in-flight, successful, failed, reversed and uncertain/needs-reconciliation states.
- A timeout or unknown result must be reconciled against the same transfer reference before any retry. Never blindly repeat a transfer, treat pending as success, or let a late webhook overwrite a confirmed outcome.
- Finalise payout and balanced ledger entries exactly once after authoritative confirmation of success. Failed/reversed transfers restore or adjust the same reserved source earnings through an audited, idempotent flow.

## 6. Required implementation and verification

Documentation is not proof of implementation. Before launch, align the web and API request endpoints, vendor dashboard, admin approval/disbursement flow, earnings ledger/reservations, vendor-debt recovery workflow, Paystack transfer initiation/webhooks, reconciliation, notifications, legal wording and tests with this policy.

Required tests include:
- ₦4,999 minimum boundary and ₦5,000 threshold.
- ₦500,000 maximum boundary.
- Whole-record FIFO, stop-on-over-cap, no splitting, no skipping and carry-forward.
- An oldest record greater than the cap.
- One active request, duplicate/concurrent submissions and atomic reservations.
- Admin approval enforcement and rejection release.
- Admin-approved debt offsets, direct repayments, disputed debt and reversal.
- Insufficient provider balance, timeout, duplicate/late webhooks, failed/reversed transfer and exact-once ledger posting.

Do not merge broad audit-branch changes into `main` without explicit owner approval.
