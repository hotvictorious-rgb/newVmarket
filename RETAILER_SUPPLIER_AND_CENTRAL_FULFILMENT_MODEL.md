# Victorious MARKET — Retailer, Supplier and Central Fulfilment Model

**Status:** Owner-confirmed business model baseline, 21 October 2026. This document supersedes earlier marketplace/vendor-managed-delivery assumptions for new retailer-model work. It does not assert that application code has already been migrated.

## 1. Business identity

Victorious MARKET (VM) is a centrally managed online retailer operating from Uyo, Akwa Ibom State. Customers buy from VM. Suppliers are private behind-the-scenes sources of products; they are not public marketplace sellers and do not own the customer relationship.

VM controls the public catalogue, retail prices, order acceptance, customer payment workflow, fulfilment coordination, delivery coverage, customer support, refund decisions, and financial records.

## 2. Supplier offer and catalogue workflow

A supplier may submit:
- product photographs and product identity;
- brand, model, specifications, condition and included accessories;
- warranty/return information and known limitations;
- wholesale/purchase price and its validity period;
- current availability and expected replenishment time;
- source/pickup location and any product handling requirements.

Supplier submission is a proposal, not automatic publication or a guarantee that VM will purchase.

### New product
VM reviews the supplier and offer, creates one canonical public product record, writes/approves the customer-facing title, description, category, attributes, retail price, images and terms, then publishes it.

### Existing product
If the product identity matches an existing canonical listing, VM links the supplier offer to that listing instead of creating a duplicate. Offers must not be merged merely because two products look similar; identity, model, variant, condition and specifications must match. Different variants/conditions may require distinct listings.

### Privacy and ownership
The public listing is owned/controlled by VM. Supplier identity, wholesale price and supplier-specific commercial terms remain private, except where disclosure is needed for fulfilment, consumer rights or legal compliance. Suppliers cannot directly publish, alter VM's retail price, or contact customers outside authorized fulfilment/support procedures.

## 3. Supplier-to-order linkage

Every accepted order item must preserve an immutable snapshot of:
- canonical VM product/listing and variant;
- selected supplier and supplier-offer identifier;
- agreed supplier purchase price and currency;
- relevant availability, condition/warranty and supply terms;
- source/collection location and selection time;
- quantity and the identity of the offer selected at order confirmation.

Later edits to the supplier offer must not silently change an existing order's cost, product identity or fulfilment terms. If a supplier cannot fulfill, VM must use an authorized exception/cancellation/refund workflow. Substitution requires an explicit authorized process and any required customer consent; never silently substitute or raise the customer's price.

Where an order contains items sourced from multiple suppliers, track supplier payables and sourcing/fulfilment outcomes per order item or supplier-specific fulfilment unit. Customer checkout remains a VM transaction; supplier identity does not create a public vendor sub-order or seller storefront.

## 4. Central operating base and service coverage

VM's central operating base is Uyo, Akwa Ibom State. VM may serve any destination it can actually cover through current product sourcing and approved logistics arrangements. This is not a claim of nationwide delivery.

Before offering delivery at checkout, VM must verify:
1. the product can be sourced and made ready;
2. the destination is within a currently supported delivery coverage rule;
3. an approved logistics company can serve the route/time window;
4. the applicable customer delivery charge and fulfilment commitment are known;
5. any required capacity, handling or delivery restrictions are satisfied.

If a coverage or fee rule is absent, stale, contradictory or unconfirmed, do not promise delivery or invent a fee. VM controls coverage and the customer-facing fee; suppliers do not configure marketplace delivery rates. The exact coverage zones, price table, logistics rate cards, service-level promises and delivery-fee margin require owner approval and must be versioned/snapshotted on accepted orders.

## 5. Central delivery and rider affiliation

VM owns customer-facing delivery coordination. Delivery work is carried by a logistics company approved/authorized by VM.

**Mandatory rider invariant:** every rider assigned to new VM work must be active and linked to exactly one active logistics company. Reject riders who have no company link, are linked to multiple companies, are linked only to a supplier/legacy vendor, or belong to an inactive/unapproved company. Company and rider status must be rechecked at assignment and at relevant custody transitions.

Every assignment must identify the order/fulfilment unit, logistics company, rider, assigning actor, timestamp, current state and required custody evidence. A supplier cannot dispatch its own rider as the VM delivery carrier or mark customer delivery complete.

Separate these events:
- supplier makes the product ready;
- VM or its authorized collector receives the product from the supplier;
- logistics company/rider accepts custody;
- rider attempts delivery;
- customer successfully receives the product;
- parcel is returned or custody is transferred.

Rider-to-company/supplier custody proof is not customer handover proof. Customer doorstep completion requires server-verified customer OTP and doorstep photo under the existing fulfilment policy. Rider collection, GPS, dispatch assignment, a supplier's assertion, or a delivery status supplied by an untrusted client is not enough.

Riders are operational actors, not financial approvers. They must not approve refunds, alter retail or supplier prices, mark payment successful, create supplier payables, or access supplier wholesale prices or unnecessary customer financial data. Use minimum necessary customer information and protected contact mechanisms.

## 6. Supplier-shop pickup

Pickup at an authorized supplier shop remains an allowed exception/option within VM's existing reservation and pickup flow:
1. VM confirms the order and authorizes the specific pickup location.
2. The customer receives the approved pickup location and instructions.
3. The customer presents the existing reservation pass/reference and completes the separate one-time customer pickup verification.
4. The required in-shop pickup photo and actor/timestamp evidence are recorded.
5. Only verified customer handover completes fulfilment.

Supplier readiness or a reservation alone is not fulfilment. The pickup pass and customer OTP are customer credentials, not rider credentials. Supplier-shop pickup does not make the supplier a public seller. Supplier shops may not bypass VM's authorized customer payment process or collect off-platform payments.

## 7. Supplier payment eligibility

**Binding owner rule:** pay suppliers only after successful, verified customer delivery or confirmed customer pickup.

The following do not make a supplier payable eligible by themselves: order placement, customer payment, supplier acceptance, supplier readiness, product collection by VM, rider assignment, rider collection, delivery attempt, customer refusal, or an unverified completion event.

After verified fulfilment, reconcile the order item, quantity, selected supplier offer, agreed purchase cost, actual fulfilment outcome and any applicable authorized adjustments. Supplier payment eligibility is not an automatic transfer; payment must follow an authorized, idempotent and auditable payment process. A timeout is an uncertain outcome, not proof of failure or success; reconcile before retrying.

A supplier payable is the purchase cost owed under the supplier agreement, not a marketplace commission, vendor earning or wallet balance. Record the payable, approval, payment attempt, provider confirmation, reversal and reconciliation as separate linked states. Do not duplicate supplier payments after repeated events.

If a customer refund is owed after a supplier has already been paid, VM remains responsible for the customer remedy. Supplier recovery is separate: create a receivable only where evidence supports supplier responsibility and the contract permits recovery. A complaint alone does not create a debt, and customer refunds must not wait for supplier recovery.

Supplier payment terms must be agreed with suppliers. This baseline specifies the eligibility trigger, not an invented payment SLA, payout rail, approval threshold or settlement date.

## 8. Retail accounting: rules to retire or reassess

The prior marketplace rules below must not be carried over as retailer defaults:
- 90% vendor / 5% cashback / 5% platform merchandise split;
- 90% vendor / 10% platform delivery-fee split;
- vendor earning balances, vendor payout thresholds and marketplace commission;
- automatic or contractual vendor-debt recovery logic treated as supplier debt;
- supplier/vendor-managed rider payables and delivery fee settings.

A supplier purchase cost is a retailer cost/payable. VM must separately record customer receipts/revenue, supplier purchase costs and payables, delivery revenue/charges, logistics-company charges/payables, gateway fees, discounts, taxes, refunds, cashback liabilities only if separately confirmed, and operating expenses. Balanced journals must not conceal a cash shortfall or operating loss.

The owner/accountant must explicitly approve the retailer chart of accounts, whether customer cashback remains active and how it is funded, logistics-company charges and payment terms, supplier payment authorization, delivery-fee margin, taxes/discount treatment and any recovery policy. No replacement percentage or unapproved monetary threshold is inferred here.

## 9. Customer payments, refunds and safety controls retained

The retailer-model decision does not waive existing payment and security requirements:
- verify provider payments server-side against reference, exact amount in integer kobo, NGN currency and correct order/customer;
- authenticated webhooks and reconciliation; idempotency keys and unique constraints where required;
- immutable order and financial snapshots; balanced linked ledger entries and explicit reversals;
- refund approval distinct from refund dispatch and gateway confirmation;
- refunds through the verified original payment route where supported under the approved policy;
- customer refunds remain VM's responsibility; supplier recovery is separate;
- parent/order/component refund ceilings, duplicate-charge protections, and independently tracked merchandise/delivery refund outcomes where applicable;
- no treating timeout or ambiguous provider/custody evidence as success or failure without reconciliation;
- statutory Nigerian consumer rights and valid warranty obligations prevail where stronger than operational rules.

The previously identified legacy web checkout bypass of `CheckoutExecutionService` remains an implementation gap until fixed and regression-tested. A written policy or passing documentation check is not proof that every production code path follows it.

## 10. Legacy migration and release gate

Existing database tables and code may still use names such as `vendor`, `shop`, vendor child order, vendor earnings, logistics company, rider or fleet. These are implementation artifacts until inspected; do not globally rename them or assume their semantics are correct for the new model.

Before enabling the retailer model in production, audit and test:
- supplier offer to canonical product matching and approval;
- immutable supplier offer and purchase-cost snapshots on order items;
- customer checkout and legacy checkout paths, including idempotency;
- central delivery coverage, fee calculation and logistics-company assignment;
- rider-to-logistics-company exclusivity and active-status checks;
- customer OTP/photo proof for doorstep delivery and supplier-shop pickup;
- supplier payment eligibility only after verified successful customer handover;
- no marketplace commission/vendor payout logic creating new supplier payables;
- refund, duplicate-event, timeout and reconciliation behavior;
- historical data migration, foreign keys, ledger references and audit history.

Do not merge/deploy this policy change as if it were an implemented code migration. Complete the compatibility audit, approve unresolved financial terms, implement in controlled steps, and pass targeted regression/security tests first.
