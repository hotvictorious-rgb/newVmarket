# Victorious MARKET

> **Owner-confirmed payment and handover rule (20 October 2026):** Only pay-before-delivery through Victorious MARKET or reserve in-shop pickup and pay at the vendor shop through Victorious MARKET's authorised payment workflow are permitted. No off-platform payment. Doorstep handover requires customer OTP verification plus a doorstep photo; in-shop pickup requires the RES-XXXXXX reservation pass plus one-time customer pickup OTP verification plus an in-shop photo. GPS, signature, separate rider-pickup photo and hub inspection photo are not mandatory. Payouts use server-calculated strict FIFO batches of whole earning records, with ₦5,000 minimum and ₦500,000 maximum; never split or skip an over-cap record.


**Your Trusted Online Market for Quality Products**  
**Operating principle:** Trust first. Quality always. Accountability at every stage.

Victorious MARKET is a Nigerian multi-vendor marketplace built with Laravel. Customers can buy from more than one vendor in a single checkout while each vendor retains responsibility for its own products and fulfilment. The platform governs marketplace access, payment verification, customer protection, refunds, vendor earnings and settlements, security, and financial audit trails.

> **Current policy status:** The owner-approved business rules are documented on the audit branch. Some items in the implementation audit remain open and must not be treated as completed merely because they appear in this README. Do not merge this branch into main without explicit owner approval.

## At a glance

- **Repository:** [hotvictorious-rgb/newVmarket](https://github.com/hotvictorious-rgb/newVmarket)
- **Current audit branch:** audit/paystack-refund-webhook-safety
- **Application:** Laravel 12 / PHP 8.2+
- **Currency:** NGN; payment amounts are validated in integer kobo.
- **Only permitted payment paths:** pay before delivery through Victorious MARKET checkout, or reserve an in-shop pickup through Victorious MARKET and pay at the approved vendor shop through Victorious MARKET's authorised payment workflow.
- **No off-platform payment:** vendors may not collect cash or private-account transfers for marketplace orders. Launch payment rails are Paystack card and standard bank transfer only unless separately approved. Doorstep COD and wallet-credit refunds are disabled; DVA, USSD, mobile money and other unapproved rails are deferred.
- **Reservation:** the existing in-shop reservation flow remains supported with its 24-hour stock hold unless separately changed by the owner; do not release stock while payment status is successful or uncertain/in flight.
- **Fulfilment:** vendor-managed doorstep delivery or zero-delivery-fee in-shop pickup.
- **Primary policy documents:** see [Business and implementation documents](#business-and-implementation-documents).

## Contents

- [Business model and binding rules](#business-model-and-binding-rules)
- [Checkout and payment lifecycle](#checkout-and-payment-lifecycle)
- [Returns, refunds, and cashback](#returns-refunds-and-cashback)
- [Commercial allocation](#commercial-allocation)
- [Vendor-managed delivery and pickup](#vendor-managed-delivery-and-pickup)
- [Security and financial integrity](#security-and-financial-integrity)
- [Paystack webhook coverage](#paystack-webhook-coverage)
- [Technology and repository layout](#technology-and-repository-layout)
- [Local development](#local-development)
- [Tests and release verification](#tests-and-release-verification)
- [Known implementation gaps](#known-implementation-gaps)
- [Business and implementation documents](#business-and-implementation-documents)

## Business model and binding rules

### Multi-vendor checkout

1. A customer may put products from several vendors into one parent checkout.
2. The parent checkout uses one Paystack payment reference/capture. The backend allocates the verified captured amount to vendor-specific child orders using immutable checkout snapshots.
3. Each child order has one owning vendor and independently tracks its items, discounts, fulfilment choice, delivery fee, refund ceiling, operational status, and settlement eligibility.
4. The sum of child allocations must equal the verified parent capture exactly. A child order must never claim more money than was captured for the parent checkout.
5. One vendor's rider can only fulfil that vendor's child order; a multi-vendor cart does not create a shared delivery assignment.

### Payment methods

| Method | Launch decision | Rule |
|---|---|---|
| Paystack card | Enabled | Verify the transaction server-side before treating an order as paid. |
| Standard Paystack bank transfer | Enabled | Handle payment confirmation and rejection as distinct, auditable outcomes. Verify provider status before refunding or telling a customer funds have been returned. |
| Doorstep cash on delivery | Disabled | The only alternative is a platform-created in-shop reservation paid at the vendor shop through Victorious MARKET's authorised payment workflow; no off-platform collection. |
| Wallet-credit refund | Disabled | Customer refunds use the verified original Paystack transaction/channel at launch. |
| Dedicated Virtual Accounts, USSD, mobile money, other channels | Deferred | Require a separate owner-approved policy and tested implementation before enabling. |

A browser redirect, customer screenshot, rider report, or webhook name by itself is not proof of successful payment. The backend must verify the authoritative transaction and bind it to the expected reference, amount in kobo, NGN currency, and correct checkout/customer.

### Order and payment states are not interchangeable

The system must distinguish at least these concepts:

- **Payment state:** whether the gateway charge has been verified and reconciled.
- **Order state:** whether the child order is awaiting action, being prepared, in delivery, picked up, delivered, cancelled, or under review.
- **Refund state:** requested, approved, submitted, processing, needs attention, confirmed, failed, or awaiting manual reconciliation.
- **Vendor earning state:** held, eligible/withdrawable, reserved for payout, paid, reversed, or under recovery.
- **Cashback state:** pending maturity, spendable, reversed, or subject to recovery.
- **Delivery state:** assigned, in custody, attempted, delivered, or returned to the vendor.

Changing one state must not silently or automatically assert that another state has completed. In particular, refund approval is not proof of gateway refund completion, and delivery proof is not refund approval.

## Returns, refunds, and cashback

### Customer return/refund window: 24 hours

The standard customer return/claim window is **24 hours from verified doorstep delivery or confirmed in-shop pickup**. This is a customer-protection window; it is **not** a promise that Paystack will process a refund within 24 hours.

- The backend must use the approved 24-hour setting consistently across customer interfaces, APIs, services, jobs, and vendor settlement eligibility, evaluated independently per vendor child order.
- The 24-hour period is the standard operational claim/reporting window, not a blanket waiver of rights that cannot lawfully be excluded. It must not be presented as eliminating remedies available under the Federal Competition and Consumer Protection Act or other applicable Nigerian law, including where goods are defective, unsafe, misdescribed, or non-conforming. Applicable law and valid warranty obligations prevail where they provide a stronger remedy; obtain qualified Nigerian legal review of customer-facing Terms before launch.
- A return request, dispute, failed delivery, unconfirmed pickup, or other blocking case must be evaluated before affected vendor earnings or cashback are released.
- Verified duplicate successful charges, verified paid orders cancelled before any vendor action, payment exceptions, chargebacks, and applicable non-waivable legal obligations follow their own evidence and authorization rules. The fulfilment-based 24-hour clock does not erase those obligations.
- Only verified duplicate charges and verified paid orders cancelled before any vendor action qualify for the narrow automatic-refund-approval exception. Other refunds require persisted authorized human approval before dispatch.
- The platform remains responsible for executing an approved customer refund. Vendor recovery, where justified by the actual allocation and approved reason, is a separate auditable process; it must not be confused with whether the customer refund succeeded.
- Refunds are limited by the affected child order's captured allocation and the aggregate parent capture. Previously refunded amounts and approved merchandise/delivery components must be accounted for. Concurrent requests must not exceed either ceiling.
- At launch, the refund goes through Paystack against the verified original transaction. Do not silently substitute wallet credit, an alternate bank destination, or an off-platform transfer.

### Cashback maturity is a separate clock

Cashback maturity is **not the same as the return/refund window**.

1. The 24-hour customer return/claim window starts at verified fulfilment.
2. After that window expires, cashback has a **separate additional 24-hour maturity buffer**.
3. The earliest possible cashback maturity is therefore the return-window expiry plus another 24 hours, and only if fulfilment is valid and no blocking return, dispute, failed-delivery case, or unconfirmed pickup remains.
4. The additional buffer delays cashback becoming spendable; it does not extend the customer's return window and does not delay or define Paystack's refund-processing SLA.
5. Cashback cannot mature before verified fulfilment establishes the claim-window expiry. Its 365-day validity period starts at the actual `matures_at` timestamp, not checkout or payment; pre-fulfilment lots may have null `matures_at` and `expires_at`.
6. If a refund reverses cashback that the customer has already spent, reverse any unspent cashback first and track the remaining recoverable amount separately. At maturity, future cashback offsets the recovery balance before any remainder becomes spendable. Do not silently debit a cash wallet or issue a negative spendable balance.
7. Cashback recovery must be disclosed in the customer Terms & Conditions and reviewed for applicable Nigerian consumer-policy/legal requirements before release.

### Refund lifecycle safeguards

- A refund request is not an approved refund.
- An approved refund is not a successful gateway refund.
- A webhook is evidence, not authorization.
- A failed or reversed refund attempt does not automatically erase the customer's refund obligation.
- Unmatched, conflicting, incomplete, or out-of-order provider events must be preserved for reconciliation rather than applied to a guessed order or operation.
- Each refund operation, reservation, gateway attempt, journal entry, and vendor recovery must be idempotent and auditable.

## Commercial allocation

The marketplace uses separate allocation bases. Do not apply one percentage to the entire cart.

### Eligible merchandise

The basis is the actual post-discount merchandise amount collected, excluding delivery fees.

| Allocation | Share |
|---|---:|
| Vendor merchandise allocation | 90% |
| Customer cashback liability | 5% |
| Platform merchandise share | 5% |

### Vendor-configured delivery fee

| Allocation | Share |
|---|---:|
| Owning vendor delivery allocation | 90% |
| Platform delivery-fee share | 10% |
| Rider payable from the platform | 0% |

The vendor compensates its own rider directly outside Victorious MARKET and its financial systems. The platform must not create rider wallets, rider earnings, rider payout requests, or new logistics-company payables for vendor-managed deliveries.

- Cashback applies to eligible merchandise only, never to delivery fees.
- The platform bears Paystack processing fees from its own share; they must not be deducted from the vendor's 90% merchandise allocation, the customer's 5% cashback allocation, or the vendor's 90% delivery-fee allocation.
- Decimal-safe arithmetic and integer-kobo amounts are required. Any rounding remainder is assigned to the platform share.
- Refunds reverse only the relevant approved merchandise and/or delivery-fee allocations from the immutable checkout snapshot.
- Vendor payout eligibility is per vendor earning/child order and requires verified payment, qualifying fulfilment, expiry of the 24-hour customer protection window, no blocking return/dispute/payment exception, vendor/KYC eligibility, bank-security checks, and reconciliation. Fulfilment is necessary but not sufficient.
- Vendor payout requests have a ₦5,000 minimum and ₦500,000 maximum. Vendors may request whenever unreserved eligible balance reaches ₦5,000; no automatic transfer occurs just because the threshold is reached.
- The detailed canonical policy, review workflow, fee decision status, and acceptance criteria are documented in [VENDOR_PAYOUT_POLICY.md](VENDOR_PAYOUT_POLICY.md).
- Every payout request requires authorized admin approval before transfer. Earnings are tracked per source but normally aggregated into one payout; excess eligible balance carries forward. Exact reservation, duplicate/concurrent-request protection, provider reconciliation and exact-once ledger posting are required.
- Victorious MARKET bears outbound vendor payout transfer fees/stamp duty as platform expense; the vendor receives the exact approved amount. Only verified Paystack transfer success/reconciliation can mark a payout paid.

## Vendor-managed delivery and pickup

Victorious MARKET does not operate a central delivery fleet, dispatch desk, route-management service, rider payroll, or rider-payout programme for new orders.

### Vendor and rider responsibilities

- A vendor creates and manages its own rider profiles.
- Every rider eligible for new work must be active and linked to exactly one active vendor.
- Vendors manually assign only their own doorstep-delivery child orders to their own riders.
- Reassignment is allowed before the rider takes custody. After custody begins, the vendor must use an audited return-to-vendor or explicit custody-transfer process.
- A rider may only act on explicitly assigned work belonging to the rider's linked vendor.
- Riders are operational actors only. They cannot approve refunds, access payment or payout tools, change prices, or create financial entries.
- Failed delivery is an operational event: record the attempt, reason, time, evidence and custody state. Apply the configured retry threshold and return the parcel to its owning vendor as required. Customer refunds, delivery-fee decisions, and vendor recovery are handled separately through the authorized review/financial workflow.

### Rider privacy

The rider-facing order projection may expose only the customer information needed to complete delivery:

- Customer name
- Delivery address
- A protected **Call Customer** action

Do not expose the raw phone number, payment status, amount paid/due, card or bank details, payment reference, customer wallet/refund data, delivery-fee financial fields, cashback, commission, vendor settlement, or payout information in rider API responses, pages, notifications, logs, exports, or analytics. Hiding a number visually is insufficient if the raw number is still sent to the device; use a protected contact mechanism.

### In-shop pickup

Customer pickup at the vendor's physical shop remains fully supported as **in_shop_pickup**:

- Delivery fee is exactly zero, enforced server-side.
- The customer receives a pickup pass in the format **RES-XXXXXX**.
- The owning vendor verifies the pass through the authorized counter flow.
- Pickup has its own operational status flow; it does not require a rider assignment or create delivery compensation.
- The pickup pass must not be exposed in rider lists, rider history, logs, analytics, or unrelated API responses.
- Do not confuse the customer's RES-XXXXXX counter pickup pass with any separate rider parcel-handover code. Their purpose and authorization must be explicit.

### Legacy logistics records

Existing logistics-company, fleet, and payout history may be retained for audit, reconciliation, and migration compatibility. Do not use those legacy records for new assignments or new-order payouts. Do not destructively delete historical financial records until their dependencies and audit needs have been mapped.

## Security and financial integrity

The platform must enforce these controls server-side, not only in the UI:

1. **Payment verification:** verify the Paystack transaction or webhook signature using the configured Paystack API secret and compare exact reference, amount, currency and checkout identity.
2. **Webhook authentication:** validate Paystack's HMAC-SHA512 signature from the raw request body using constant-time comparison. Never put gateway secrets in source control, client code, logs, or support messages.
3. **Idempotency:** repeated callbacks or webhooks must not duplicate settlements, cashback, refunds, reservations, or journal entries. A reused idempotency key must refer to the same financial instruction.
4. **Concurrency control:** use database transactions/row locks and unique constraints where required. A stale worker must not overwrite a newer webhook/reconciliation result.
5. **Refund ceilings:** confirmed refunds plus active reservations must remain within the child-order and parent-capture limits. Validate currency, amount, original payment identity, and operation status before finalizing.
6. **Balanced accounting:** financial journal events must balance. Reversals must reverse real prior postings; never fabricate a cash outflow for a transfer that never disbursed.
7. **Evidence and audit:** preserve provider payload identity, attempt history, approval provenance, operational proof, state transitions, and reconciliation decisions.
8. **Least privilege:** enforce vendor ownership, rider-to-vendor linkage, and minimum data projections on every route and response.
9. **Fail closed:** ambiguous payment, refund, transfer, custody, or ownership evidence goes to a visible review/reconciliation state, not an assumed success.
10. **No false escrow claim:** internal holds, reservations and ledgers are accounting controls; do not describe them as a legally regulated escrow service unless that status has been independently established.

## Paystack webhook coverage

Webhook subscription must match the enabled Paystack products and channels. The detailed event matrix, state transitions, retry expectations and known gaps are maintained in [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md). The table below is a concise launch checklist, not a claim that every handler is already implemented.

| Event | Why it matters |
|---|---|
| charge.success | Candidate successful payment signal for card and bank transfer; verify transaction and bind it to the correct parent checkout. |
| bank.transfer.rejected | Required because standard bank transfer is enabled; record a payment exception and verify provider refund/status before taking action. Avoid issuing a duplicate refund. |
| refund.pending | Track a refund that has not reached a final outcome. |
| refund.processing | Track gateway processing without marking the refund complete. |
| refund.processed | Confirm only when event identity, operation, amount, currency and original transaction all match. |
| refund.failed | Preserve the obligation and reconcile whether a safe retry or manual review is required. |
| refund.needs-attention | Alert authorized operators; keep the customer refund obligation open. Do not switch to wallet or alternate-bank payment without a separate approved policy. |
| transfer.success | Verify transfer identity, amount and currency before marking an authorized vendor payout paid. |
| transfer.failed | Reconcile failure and returned funds; do not let a delayed failure overwrite a confirmed success. |
| transfer.reversed | Record a genuine reversal and reverse only previously posted disbursement entries. |

Webhooks are not a substitute for authoritative API verification or scheduled reconciliation. Events must be durably recorded, deduplicated, matched to the correct provider operation, and processed safely when duplicated, delayed, conflicting, or out of order. Unmatched events must remain visible for authorized review.

## Technology and repository layout

- **Laravel application:** backend/
- **Backend dependencies and scripts:** backend/composer.json
- **Environment template:** backend/.env.example
- **Payment/refund policy and Paystack event matrix:** PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md
- **Vendor delivery implementation audit:** VENDOR_MANAGED_DELIVERY_IMPLEMENTATION_AUDIT.md
- **Delivery and financial-integrity plan:** DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md
- **Product and business overview:** VICTORIOUS_MARKET.md
- **Deployment guide:** SHARED_HOSTING_DEPLOYMENT_GUIDE.md
- **Business-rule source for vendor-managed delivery:** .ai/BUSINESS_RULES.md
- **Focused financial-safety CI workflow:** .github/workflows/paystack-refund-safety.yml

The codebase uses Laravel 12, PHP 8.2+, Composer, a relational database (SQLite for local testing or a supported production database), and BCMath for money arithmetic. Node.js is needed if the frontend assets must be built.

## Local development

### Requirements

- PHP 8.2 or later, with BCMath and the extensions required by Laravel
- Composer 2.x
- SQLite for a simple local test setup, or a configured supported database
- Node.js/npm only when building frontend assets

### Setup

From the repository root:

~~~bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
~~~

Configure a local database and the Paystack test keys in the local .env file. Keep all real keys and passwords private; never commit .env or credentials. Paystack webhook signature verification uses the Paystack API secret key as documented in the environment template.

For a local SQLite setup, ensure the database file exists and the DB connection is configured for SQLite, then run:

~~~bash
php artisan migrate
php artisan serve
~~~

The development server normally starts at http://127.0.0.1:8000. Follow the current environment template and Laravel setup requirements if this repository's configuration differs. Do not run destructive commands such as migrate:fresh against a database containing data you need to preserve.

### Environment and production safety

- Use Paystack test credentials for local/staging testing and production credentials only in the secured production environment.
- Set APP_DEBUG=false in production and configure APP_URL, database credentials, secure session/cookie settings, mail, queues, backups, and monitoring.
- Use HTTPS for all customer, vendor, rider, counter and admin portals.
- Configure the Paystack dashboard webhook URL to the deployed endpoint and verify delivery/retry monitoring.
- Do not expose secret keys in frontend code, client apps, logs, or screenshots.
- Follow [SHARED_HOSTING_DEPLOYMENT_GUIDE.md](SHARED_HOSTING_DEPLOYMENT_GUIDE.md) for cPanel/shared-hosting packaging and deployment.
- Back up the database before migrations or releases and test restore procedures, not just backup creation.

## Tests and release verification

Run the backend suite from the backend directory:

~~~bash
php artisan test
~~~

The audit branch also has a focused GitHub Actions workflow that checks PHP syntax and runs the financial remediation, Paystack payment hardening, and returns/refunds/collection-points tests. See [the workflow file](.github/workflows/paystack-refund-safety.yml) and the branch's [Actions runs](https://github.com/hotvictorious-rgb/newVmarket/actions).

Do not copy a historical test count into release notes as proof of current status. Verify the latest run against the exact commit intended for release. A focused green workflow does not necessarily mean the entire backend suite, production deployment, Paystack dashboard configuration, or every documented business rule has been verified.

Before release, verify at minimum:

- Card and bank-transfer payment confirmation, mismatch handling and duplicate events.
- bank.transfer.rejected handling without duplicate refunding.
- Parent/child allocation equality and multi-vendor fulfilment isolation.
- COD rejection through every checkout/API/configuration path.
- 24-hour return-window start/end and the separate 24-hour cashback-maturity buffer.
- Original-channel-only refunds, approval provenance, refund ceilings and refund reconciliation.
- Cashback reversal/recovery without silent wallet debit.
- Vendor-only rider assignments, protected Call Customer action and rider response privacy.
- Zero-fee in-shop pickup with RES-XXXXXX, no rider assignment, and no ambiguity with rider parcel codes.
- Vendor settlement eligibility, strict FIFO whole-record payout batches, admin approval, transfer verification and balanced journals.
- Legacy logistics paths blocked for new work while historical records remain auditable.

## Known implementation gaps

The repository's audit documents identify implementation work that remains to be verified or completed. The existence of a policy statement or regression test does not prove the production application is fully aligned. In particular, the current audit tracks items such as:

- Full rider API/page/log/notification privacy enforcement and protected customer calling.
- Removing financial side effects from rider-triggered failed-delivery and handover flows.
- Complete bank-transfer rejection handling and provider-status reconciliation.
- Explicit refund pending/processing/needs-attention handling.
- Consistent cashback maturity based on actual fulfilment-window expiry and a separate customer cashback-recovery ledger.
- Persisted human refund approval provenance and reason-specific delivery-fee refund decisions.
- Item-level vendor earning records and payout eligibility aligned with child-order allocations.
- Ensuring legacy logistics routes and payout paths cannot create new-order financial activity.

Use the implementation audit as the current gap register. Re-check source code and tests before marking an item complete.

## Business and implementation documents

These documents serve different purposes and should remain consistent:

1. [VICTORIOUS_MARKET.md](VICTORIOUS_MARKET.md) — broader product vision and operating model.
2. [.ai/BUSINESS_RULES.md](.ai/BUSINESS_RULES.md) — binding vendor-managed delivery, rider, privacy, pickup and COD rules.
3. [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md) — payment channels, refund/cashback policy, settlement and Paystack event matrix.
4. [VENDOR_MANAGED_DELIVERY_IMPLEMENTATION_AUDIT.md](VENDOR_MANAGED_DELIVERY_IMPLEMENTATION_AUDIT.md) — code observations, implementation gaps and verification checklist.
5. [DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md](DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md) — delivery and accounting implementation plan.
6. [SHARED_HOSTING_DEPLOYMENT_GUIDE.md](SHARED_HOSTING_DEPLOYMENT_GUIDE.md) — deployment instructions.

If documents conflict, follow the latest owner-confirmed decision recorded in the policy documents and reconcile the conflicting document/code before release. The final owner-approved launch contract is: multi-vendor parent checkout; two payment paths (pay before delivery through Victorious MARKET, or reserve in-shop pickup and pay at the vendor shop through Victorious MARKET's authorised workflow); Paystack card and standard bank transfer rails; no doorstep COD or off-platform payments; original Paystack-channel refunds only; 24-hour customer return/refund window; separate additional 24-hour cashback maturity buffer; vendor-managed riders with no platform rider payouts; and zero-fee in-shop pickup using RES-XXXXXX plus required secure pickup verification and an in-shop photo.

---

**Victorious MARKET — Your Trusted Online Market for Quality Products.**  
*Trust first. Quality always. Accountability at every stage.*
