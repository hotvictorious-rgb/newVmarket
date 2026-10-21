# Victorious MARKET

> **Owner-confirmed business model & payment rules (21 October 2026):** Victorious MARKET operates as a Direct-to-Consumer (1P) Retailer with Just-in-Time Private Supplier Sourcing based in Uyo, Akwa Ibom State. Customers buy from Victorious MARKET. Suppliers provide wholesale cost prices, and VM adds its profit margin (`Retail Selling Price = Wholesale Cost + VM Margin`). Sourcing selects the cheapest available local supplier offer. Only two fulfilment & payment paths are permitted:
> 1. **Doorstep Delivery:** Customer **must pay first online** via Paystack (card or bank transfer) + delivery fee. Strictly NO Cash on Delivery (COD). Handover verified via customer Handshake OTP (`delivery_otp`) plus doorstep photo.
> 2. **In-Shop Pickup:** Customer reserves online for free (₦0 upfront, `RES-XXXXXX` pass), visits the designated supplier shop in Uyo, **inspects the physical item first**, and pays at the shop counter through VM's authorized workflow upon satisfaction before goods release. Handover verified via customer pickup OTP (`pickup_verification_code`) plus in-shop photo.
> Suppliers are paid their agreed wholesale cost strictly after verified customer delivery or verified counter pickup.

**Your Trusted Online Market for Quality Products**  
**Operating principle:** Trust first. Quality always. Accountability at every stage.

Victorious MARKET is a centrally managed online retailer operating from Uyo, Akwa Ibom State. Customers buy from Victorious MARKET; suppliers remain private behind-the-scenes product sources linked internally to approved wholesale offers and order items. Victorious MARKET controls public listings, retail prices, customer payments, delivery coordination, pickup verification, customer support, refunds, and financial records. Delivery is fulfilled through approved logistics companies whose riders must each be linked to an active logistics company.

> **Current policy status:** The owner-approved business rules are documented on the audit branch. Some items in the implementation audit remain open and must not be treated as completed merely because they appear in this README. Do not merge this branch into main without explicit owner approval.

## At a glance

- **Repository:** [hotvictorious-rgb/newVmarket](https://github.com/hotvictorious-rgb/newVmarket)
- **Current audit branch:** audit/paystack-refund-webhook-safety
- **Application:** Laravel 12 / PHP 8.2+
- **Currency:** NGN; payment amounts are validated in integer kobo.
- **Two Permitted Customer Flows:**
  - **Doorstep Delivery:** Pay first online via Paystack. Sourced from cheapest/available supplier offer in Uyo. Dispatched via accredited logistics rider. Zero COD.
  - **In-Shop Pickup:** Reserve online for ₦0 upfront. Inspect physical item first at supplier shop counter. Pay at the shop counter upon satisfaction before goods release. Zero delivery fee.
- **Retail Accounting:** `Customer Retail Price - Supplier Wholesale Cost = VM Gross Margin`. Suppliers receive 100% of agreed wholesale cost upon verified completion. Legacy 90/5/5 splits are retired.
- **No off-platform payment:** Suppliers may not collect cash or private-account transfers for orders. Launch payment rails are Paystack card and standard bank transfer only. Doorstep COD is completely disabled.
- **Reservation:** In-shop reservation features a 24-hour stock hold at the supplier shop.
- **Operating base and coverage:** Uyo, Akwa Ibom State; serves destinations where VM has confirmed supplier sourcing and logistics coverage.
- **Primary policy documents:** see [Business and implementation documents](#business-and-implementation-documents).

## Contents

- [Retailer model and binding rules](#retailer-model-and-binding-rules)
- [Checkout and payment lifecycle](#checkout-and-payment-lifecycle)
- [Returns, refunds, and cashback](#returns-refunds-and-cashback)
- [Retail accounting and supplier payment](#retail-accounting-and-supplier-payment)
- [Central delivery and pickup](#central-delivery-and-pickup)
- [Security and financial integrity](#security-and-financial-integrity)
- [Paystack webhook coverage](#paystack-webhook-coverage)
- [Technology and repository layout](#technology-and-repository-layout)
- [Local development](#local-development)
- [Tests and release verification](#tests-and-release-verification)
- [Known implementation gaps](#known-implementation-gaps)
- [Business and implementation documents](#business-and-implementation-documents)

## Retailer model and binding rules

### One customer-facing retailer

Victorious MARKET is the retailer. Customers buy from Victorious MARKET, not from public supplier storefronts. The system may retain legacy vendor tables and identifiers during migration, but those legacy names do not define the new business relationship.

### Supplier catalogue workflow

1. A supplier may submit product photos, brand/model, specifications, condition, warranty information, wholesale price, availability, and fulfilment location.
2. VM reviews the supplier and offer before publication.
3. If the product is new to the catalogue, VM creates and approves one canonical public product listing.
4. If the product already exists, VM maps the supplier offer to that existing listing; do not create duplicate public listings merely because another supplier offers the same product.
5. VM controls the public title, description, category, retail price, publication status, and customer-facing terms. Supplier identity, wholesale price, and supplier-specific terms remain private except where disclosure is needed for fulfilment, consumer rights, or legal compliance.
6. Every order item must retain the exact selected supplier offer and a snapshot of the agreed purchase cost and relevant terms. A later supplier price change must not rewrite an existing order.

### Central operating base and service coverage

VM operates from Uyo, Akwa Ibom State, and serves any destination it can actually cover through available product sourcing and approved logistics arrangements. Uyo is the central operating base, not a promise of nationwide service. Checkout must offer delivery only where current coverage, product availability, fee and delivery arrangements are confirmed. No coverage rule means no delivery promise.

### Payment and order-state separation

Customer payment, order status, supplier payable, logistics-company charges, pickup/delivery status, refund status, and customer cashback are separate state machines. A supplier's confirmation that an item is ready, a rider's collection scan, or a dispatch assignment does not mean the customer received the item. Supplier payment eligibility requires verified successful customer delivery or verified customer pickup, with the relevant evidence and financial reconciliation recorded.

Existing approved customer payment paths and Paystack verification safeguards remain in force unless explicitly changed by the owner. Supplier shops must not collect off-platform payment or bypass VM's authorized payment workflow.

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

## Retail accounting and supplier payment

The former marketplace allocations (90% vendor / 5% cashback / 5% platform on merchandise and 90% vendor / 10% platform on delivery) are **retired for the retailer model**.

Record separately, using immutable linked financial entries:
- customer merchandise revenue and verified customer payment;
- the agreed supplier purchase cost for each order item (`cost_price * quantity`);
- **customer 5% cashback liability** (confirmed active by owner decision: 5% earned strictly on real money paid through Paystack for merchandise, excluding amounts covered by redeemed cashback or discounts; funded from VM gross margin, maturing 24h after return window closes, capped at 50% redemption on future checkouts);
- delivery charges collected from the customer;
- logistics-company charges and amounts payable under the applicable logistics agreement;
- payment-processing fees, discounts, approved refunds, and other authorized expenses;
- supplier amounts owed, payments made, reversals, disputes, and evidence-backed recovery claims.

**Supplier-payment timing:** do not make a supplier payment eligible merely because an order is placed, paid, accepted, or marked ready. Eligibility begins only after verified successful customer delivery or confirmed customer pickup and the required order/amount reconciliation. Eligibility is not an automatic transfer; the payable must still pass authorization and reconciliation controls. If a customer refund is later owed after the supplier has been paid, VM remains responsible for the customer remedy. Any supplier receivable/recovery is a separate evidence-backed process under an agreed contract; it must not delay a valid customer refund.

Suppliers receive 100% of their agreed wholesale cost upon verified completion. No percentage commission or cashback deductions are taken from suppliers; VM funds customer cashback and earns its profit directly from the retail gross margin (`Retail Price - Supplier Wholesale Cost`).

## Central delivery and pickup

### Delivery controlled by Victorious MARKET (Pay First Online)

- **Pay First Online:** Customer must pay the retail selling price plus delivery fee upfront online via Paystack. Strictly NO Cash on Delivery (COD).
- VM coordinates delivery from its Uyo operating base, sourcing items from the cheapest available local supplier offer.
- Delivery is fulfilled through an approved logistics company. Every rider assigned to VM work must be active and linked to exactly one active logistics company; an unlinked rider, supplier-owned rider, inactive company, or ambiguous company association must be rejected.
- A logistics-company assignment must be authorized and recorded against the relevant order or fulfilment unit. A supplier cannot independently dispatch a rider or mark delivery complete.
- The logistics company and rider perform operational custody and handover tasks. They cannot approve refunds, alter prices, mark customer payments successful, or create supplier-payment entries.
- Do not expose supplier wholesale costs, customer payment/refund information, or unnecessary customer data to riders.
- Customer delivery completion requires server-verified customer OTP (`delivery_otp`) and doorstep photo. Rider collection or a logistics-company scan alone is not fulfilment.

### Supplier-shop pickup (Inspect First, Pay at the Shop)

- **Online Reservation Pass:** Customer reserves the product online with ₦0 upfront, receiving a reservation pass (`RES-XXXXXX`) and 24-hour stock hold at the designated supplier shop in Uyo.
- **Inspect First:** The customer visits the supplier shop counter and **physically inspects the item first** to verify condition, authenticity, and satisfaction before making any payment.
- **Pay at Counter Upon Satisfaction:** If satisfied, the customer completes payment through VM's authorized workflow at the shop counter and provides the one-time customer pickup OTP (`pickup_verification_code`).
- The supplier records the required in-shop photo, verifies the payment and pickup OTP, and hands over the item.
- If the customer is not satisfied upon inspection, they simply walk away without paying anything.
- Only verified counter handover completes fulfilment and makes the supplier's wholesale payable eligible.

### Coverage and fees

VM owns the customer-facing coverage decision and delivery-fee quote. Fees and logistics-company charges must be based on VM-approved coverage and commercial agreements, not supplier-configured marketplace delivery rates. Exact pricing, corridor rules, logistics-company settlement terms, and fee-margin accounting require explicit approval; this document does not invent amounts or percentages.

### Legacy marketplace delivery records

Preserve historical vendor, vendor-rider, fleet, payout, and settlement records needed for audit and migration. They must not authorize new retailer-model work unless explicitly migrated and validated. Do not destructively rename or delete legacy tables/records before mapping foreign keys, payment journals, order history, and reconciliation dependencies.

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

The repository's audit documents identify implementation work that remains to be verified or completed. The existence of a policy statement or regression test does not prove the production application is fully aligned. The repository still contains legacy marketplace code; the retailer model is a business decision, not proof that the implementation migration is complete. In particular, the current audit tracks items such as:

- Full rider API/page/log/notification privacy enforcement and protected customer calling.
- Removing financial side effects from rider-triggered failed-delivery and handover flows.
- Complete bank-transfer rejection handling and provider-status reconciliation.
- Explicit refund pending/processing/needs-attention handling.
- Consistent cashback maturity based on actual fulfilment-window expiry and a separate customer cashback-recovery ledger.
- Persisted human refund approval provenance and reason-specific delivery-fee refund decisions.
- Retailer-model migration: supplier offer/order-item linkage, supplier payables after verified fulfilment, and retirement of marketplace vendor earnings and commission rules.
- Central logistics-company assignment, mandatory rider-to-logistics-company linkage, VM-owned coverage and delivery pricing, and retirement of supplier/vendor-managed dispatch paths.
- Legacy web checkout bypass of CheckoutExecutionService remains an open hardening issue and must be fixed and tested before release.

Use the implementation audit as the current gap register. Re-check source code and tests before marking an item complete.

## Business and implementation documents

These documents serve different purposes and should remain consistent:

1. [RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md](RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md) — current owner-confirmed retailer, supplier, coverage, logistics and payment-eligibility model.
2. [VICTORIOUS_MARKET.md](VICTORIOUS_MARKET.md) — broader product vision and operating model.
3. [.ai/BUSINESS_RULES.md](.ai/BUSINESS_RULES.md) — binding vendor-managed delivery, rider, privacy, pickup and COD rules.
4. [PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md](PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md) — payment channels, refund/cashback policy, settlement and Paystack event matrix.
5. [VENDOR_MANAGED_DELIVERY_IMPLEMENTATION_AUDIT.md](VENDOR_MANAGED_DELIVERY_IMPLEMENTATION_AUDIT.md) — code observations, implementation gaps and verification checklist.
6. [DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md](DELIVERY_SIMPLIFICATION_AND_FINANCIAL_INTEGRITY.md) — delivery and accounting implementation plan.
7. [SHARED_HOSTING_DEPLOYMENT_GUIDE.md](SHARED_HOSTING_DEPLOYMENT_GUIDE.md) — deployment instructions.

If documents conflict, follow the current owner-confirmed retailer model in `RETAILER_SUPPLIER_AND_CENTRAL_FULFILMENT_MODEL.md`, then reconcile the code and every downstream policy before release. This branch is documentation and audit work; it does not claim that the application has already been migrated. The old multi-vendor checkout, vendor commission/payout splits, vendor-managed delivery and vendor-linked riders are legacy assumptions until a separate implementation migration is completed.

---

**Victorious MARKET — Your Trusted Online Market for Quality Products.**  
*Trust first. Quality always. Accountability at every stage.*
