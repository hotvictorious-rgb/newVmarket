# Victorious MARKET — The Complete Business, Operating System and Technology Explained

Victorious MARKET is much more than an online shop. The system you are designing is intended to combine an online retail business, a controlled network of private suppliers, a payment-verification system, a delivery coordination operation, a returns process and an auditable financial management system.

The central idea is simple:
**A customer buys from Victorious MARKET, Victorious MARKET controls the entire customer experience, and private suppliers and approved logistics companies work behind the scenes to fulfil the order.**

The important part is not just selling products online. It is coordinating the money, products, suppliers, people, delivery evidence and financial records so that every order can be traced from the customer's first click to final delivery and settlement.

I have reviewed the current repository's main business rules, payment-policy documents, supplier availability policy, exception policies, operational playbook and engineering Q&A policy. The explanation below describes the intended Victorious MARKET model in detail. I will also distinguish the approved business rules from things that still require proof in the actual running software.

---

## 1. What exactly is Victorious MARKET?

- **One public retailer:** Customers see one brand, one storefront, one retail price, one customer-support operation and one accountable seller: Victorious MARKET.
- **Private supplier network:** Approved suppliers provide stock information and wholesale prices privately. Victorious MARKET chooses which suppliers can fulfil each order.
- **Centralized fulfilment:** Victorious MARKET coordinates sourcing, pickup, dispatch, doorstep delivery, exceptions and customer communication.
- **Controlled financial operations:** The business verifies customer payments, records its obligations, reconciles incoming and outgoing bank transactions, and requires human authorization for refunds and payouts.

The official positioning is:
> **VICTORIOUS MARKET — Your Trusted Online Market in Uyo, Akwa Ibom State.**

Its operating philosophy is:
> **Trust First. Quality Always. Accountability at Every Stage.**

### What kind of marketplace is it?

It is a controlled retailer with just-in-time supplier sourcing, rather than a conventional open marketplace where independent merchants create their own public shops and collect money from customers.

Consider the difference:

| Feature / Property | Conventional open marketplace | Victorious MARKET |
| :--- | :--- | :--- |
| **Merchant Choice** | Customers may choose between individual sellers. | Customers buy from Victorious MARKET. |
| **Product Listings** | Sellers may control their public product listings. | Victorious MARKET controls its canonical public catalogue. |
| **Pricing Control** | Sellers may set their own customer-facing prices. | Victorious MARKET controls retail prices. |
| **Payment Flow** | Sellers may receive customer payments under marketplace-specific arrangements. | Customer payments go through the approved Victorious MARKET payment routes. |
| **Delivery Management** | Sellers may arrange their own delivery. | Victorious MARKET coordinates the delivery operation. |
| **Customer Experience** | Customer experience can differ by seller. | Victorious MARKET owns the customer experience and exception process. |

This model gives the business more control over pricing, quality presentation, customer trust, fulfilment and financial accountability.

The trade-off is that Victorious MARKET itself must handle the difficult operational work. It cannot simply blame an independent seller and leave the customer to resolve the problem.

The design is documented in the [repository README](README.md) and [active business rules](docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md).

---

## 2. Where does Victorious MARKET operate?

Victorious MARKET is designed to operate within the approved Uyo LGA operating boundary in Akwa Ibom State.

This is a deliberate operating restriction, not merely a marketing preference. The sourcing network and delivery network must both remain within the approved area.

### Supplier geography

Every supplier must:
1. Be registered by an authorized Victorious MARKET administrator.
2. Be approved, active and verified before becoming eligible for sourcing.
3. Have one approved shop or pickup location within the permitted Uyo boundary.
4. Provide products under the private supplier arrangement.
5. Remain subject to activation, suspension and reactivation controls.

Suppliers cannot register themselves and immediately begin selling through the platform. A supplier must not be allowed to activate their own account without administrative approval.

Plaza is a possible supplier location, not a compulsory central hub. An approved supplier may operate anywhere within the permitted Uyo boundary.

If an existing supplier is suspended, the system must exclude that supplier from new sourcing. It must not silently erase historical records or automatically cancel every paid order associated with that supplier. Any in-flight affected orders require explicit review.

### Delivery geography and corridors

The eight configured delivery corridors are:

| Code | Corridor |
| :--- | :--- |
| **COR-01** | Oron Road |
| **COR-02** | Nwaniba Road |
| **COR-03** | Aka Road |
| **COR-04** | Abak Road |
| **COR-05** | Ikot Ekpene Road |
| **COR-06** | Itu Road |
| **COR-07** | Ikpa Road |
| **COR-08** | Plaza / central core |

Customers provide their Uyo address, landmark and relevant delivery information. The system uses the approved landmark-to-corridor configuration to support delivery assignment, batching and logistics pricing.

Unknown or out-of-boundary destinations must fail closed. That means the platform should refuse to proceed when it cannot safely establish that the destination is supported, rather than guessing a corridor or accepting an unsupported delivery address.

The business is intentionally starting with a smaller, manageable geography. It can establish reliable supplier relationships and delivery routines in Uyo before considering any future geographical expansion.

---

## 3. The people and roles inside Victorious MARKET

The system has several different participants. They should not all have the same permissions or see the same information:

1. **Customer:** Browses products, creates an account, places orders, pays, receives delivery updates, supplies required delivery information, receives goods and can submit complaints or return requests under the applicable rules.
2. **Super Administrator:** Oversees the business, authorizes financial actions, resolves exceptions, controls supplier access, approves changes and has access to sensitive operational and financial information according to role permissions.
3. **Supplier:** Manages permitted private product and availability information, receives order-specific sourcing requests and confirms whether the full requested quantity can be supplied.
4. **Logistics company and rider:** The approved company is responsible for the assigned logistics work. A linked rider handles physical collection and delivery, following the required calls, OTPs, photographs and custody procedures.
5. **Customer support / operations staff:** Assist customers, communicate approved options, document complaints and coordinate permitted operational actions. They should not automatically inherit Super Admin financial permissions.

The permissions system is a major part of the design. A rider does not need to see supplier wholesale costs. A supplier does not need access to the business's complete financial ledger. A support agent should not be able to send a refund simply because a customer has complained.

These restrictions protect the business and its customers.

---

## 4. The public product catalogue

The catalogue is the product collection customers see on the website.

It may cover categories such as:
- Electronics and gadgets.
- Kitchen and home appliances.
- Musical instruments.
- Fashion.
- Furniture and beddings.
- Bags and luggage.

The public catalogue might include phones, power banks, generators, mattresses, gas cylinders, MiFi devices, clippers and smartwatches, depending on the products Victorious MARKET chooses to stock through its suppliers.

### The important distinction: a public product is not a supplier assignment

Suppose Victorious MARKET lists a mattress for ₦120,000.
Behind the scenes, several approved suppliers might have a compatible mattress available at different wholesale prices. The public listing remains one Victorious MARKET product; it does not become three different customer-facing shops.

The system must distinguish:
- **Canonical product:** The public product Victorious MARKET sells.
- **Supplier offer:** A private supplier's matching product, availability signal and wholesale price.
- **Order allocation:** The supplier or suppliers selected to fulfil a particular customer's paid order.

Those three things are related, but they are not interchangeable.

### How a product becomes visible

An authorized administrator manages the catalogue and the supplier-product relationships.

A listing can ordinarily be purchasable only when at least one linked supplier offer is eligible. The written rules include checks such as:
- The supplier is approved, active and verified.
- The supplier's approved location is within Uyo.
- The supplier's product is explicitly marked in stock.
- The availability confirmation is sufficiently fresh.
- The offer passes other eligibility rules.
- The wholesale cost is strictly lower than the public retail selling price.
- The administrator has not manually delisted the public product.

If all eligible supplier offers become unavailable, the public listing should be hidden or made unavailable for purchase.
However, the product record and history should remain. Hiding a listing should not erase old orders, financial records, supplier offers or audit events.
An administrator can also manually delist a product even when a supplier says it has stock.

These rules prevent a customer's visible catalogue from becoming disconnected from the real sourcing network.
The detailed policy is in [Supplier Catalogue and Order-Specific Availability](docs/architecture/SUPPLIER_ORDER_SPECIFIC_AVAILABILITY_CONFIRMATION_POLICY_2026-10-24.md).

---

## 5. How Victorious MARKET earns money

The basic merchandise economics are straightforward.
Suppose the public retail price of an item is ₦100,000 and the selected supplier's agreed wholesale cost is ₦82,000.

| Illustrative merchandise economics | Amount |
| :--- | :--- |
| **Retail selling price** | ₦100,000 |
| **Supplier wholesale cost** | ₦82,000 |
| **Initial merchandise spread** | **₦18,000** |

This is the spread before payment-processing costs, operating expenses, delivery subsidies, refunds, losses, marketing and other business costs. It is not necessarily net profit.

The same principle applies across the catalogue: Victorious MARKET controls the public retail price and evaluates whether a supplier offer has an acceptable wholesale cost.

The business should retain an immutable record of the price and supplier cost agreed for each order. Later catalogue or wholesale-price changes must not silently rewrite an existing customer's order or the business's financial obligations.

### Delivery is a separate money path

The company must also distinguish merchandise economics from delivery economics.
A customer's delivery fee is not the same thing as the amount Victorious MARKET owes a logistics company.

The business can make a merchandise spread on an item while losing money on delivery, or collect a delivery fee that is insufficient to cover the actual logistics cost.

That is why the system must record merchandise revenue, supplier costs, delivery revenue, logistics payable, bulky surcharges, refunds and other adjustments separately.

---

## 6. The complete customer journey

Here is the intended end-to-end experience, from browsing to delivery:

1. **Browse:** The customer views the public catalogue, product details and displayed retail prices.
2. **Register or sign in:** The customer must have an account and verified phone number before checkout. Guest checkout is disabled.
3. **Prepare the order:** The customer selects products and quantities, supplies the Uyo delivery address and landmark, and reviews the order total.
4. **Choose a payment method:** The customer selects one enabled option: Paystack or direct Manual Bank Transfer.
5. **Verify payment:** The backend verifies Paystack through its trusted provider flow, or an authorized administrator independently verifies actual direct bank credit.
6. **Source the products:** Only after verified payment does binding supplier confirmation and allocation begin.
7. **Confirm stock and prepare:** Each required supplier confirms it can fulfil the exact quantity for its assigned order line.
8. **Assign and dispatch:** The system coordinates readiness, a suitable approved logistics company and one rider for the customer order.
9. **Collect goods securely:** The rider completes required supplier pickup OTP and photo evidence at every pickup location.
10. **Deliver to the customer:** The rider calls before departure and completes the delivery OTP and required doorstep photo.
11. **Reconcile the order:** The system records delivery, exceptions, relevant claim windows, supplier obligations and logistics payable eligibility.
12. **Complete financial settlement:** After the applicable checks and reconciliation, an authorized human handles supplier and logistics-company bank transfers.

This sequence includes an important principle: **paying for an order does not mean every other stage has already succeeded.**
Payment confirms one thing: the required customer payment has been verified. It does not prove that suppliers can fulfil the quantity, that the rider collected the products, that delivery succeeded or that the supplier has already been paid.
Those are distinct stages with their own evidence and controls.

---

## 7. Customer accounts, checkout and order creation

Customers can browse the catalogue and add products to their cart without an account. However, the active business rules require registration or login before checkout.

The account is important for more than collecting a name and email address. It binds the order to an authenticated customer and a verified phone number needed for the delivery process.

At checkout, the system must establish the order's details, including as applicable:
- Customer identity.
- Product IDs and variants.
- Quantities.
- Agreed retail prices.
- Merchandise total.
- Delivery address and landmark.
- Selected delivery information and corridor.
- Customer delivery fee and applicable bulky surcharge.
- Chosen payment method.
- Unique order reference.
- Payment status and applicable payment-attempt identity.

The system must calculate the total using trusted server-side values. It must not simply believe a browser request that says an item costs ₦20,000 or that the total to be paid is ₦75,000.

The website can perform availability checks before payment, but those checks are non-binding. They must not reserve suppliers, create payable obligations or send supplier notifications that commit the business to a paid order before payment has been verified.

Why? Because products may sell, supplier availability may change, and concurrent orders may compete for the same stock. The system needs to revalidate the sourcing options once the order has actually been paid.

---

## 8. Payments: Paystack and direct bank transfer

Victorious MARKET's payment design has two distinct customer-payment paths that may coexist:

- **Path A — Paystack:** A customer pays through an enabled Paystack-supported payment method. The backend verifies the transaction through trusted server-side provider mechanisms, including the reference, order binding, amount, currency and applicable signature/idempotency protections.
- **Path B — Direct Manual Bank Transfer:** The customer transfers directly to the Victorious MARKET bank account configured by an authorized administrator. The customer submits the transfer receipt against the order, but the order remains unpaid until an authorized reviewer independently verifies the actual incoming credit and approves it.

One important distinction: a bank-transfer option processed through Paystack belongs to the Paystack payment path. A customer transferring directly to the business bank account is using the manual bank-transfer path. These are not the same type of transaction.

### How the two methods can coexist safely

Both methods may be enabled at the same time for different customers and orders. But each individual order must be permanently associated with exactly one selected payment method once its payment attempt begins.

For example:
- Order VM-1001 is created with Paystack as its payment method.
- Order VM-1002 is created with direct manual bank transfer as its payment method.

The first order cannot be verified by a manually uploaded bank receipt. The second cannot be declared paid by a Paystack webhook.
The system must also prevent a single payment transaction from paying two different orders.

If the payment result is uncertain, the system must reconcile the original attempt rather than silently switching the order to another payment method. Otherwise, a delayed bank credit and a second payment could create a double-payment situation.

### How direct manual bank transfer should work

The customer receives the configured Victorious MARKET account details, exact amount payable and a unique order reference.

The customer then:
1. Transfers the exact amount to the official receiving account.
2. Uses the order reference in the bank narration where supported.
3. Uploads the transfer receipt or screenshot to that order.
4. Waits while an authorized reviewer checks the transaction.

The receipt helps the reviewer find the transaction. It is not proof that the receiving account has been credited.
The administrator must independently inspect the actual receiving-account credit, verify the amount and currency, check the bank reference and relevant transaction status, and confirm that funds are available rather than merely pending, reversed or initiated.

The reviewer records the decision, reference, amount, reason, time and relevant evidence version. Missing or rejected evidence does not mark an order as paid.

If the customer claims to have paid but the incoming credit cannot be found, the order goes into a review or support-investigation process. The software must not make the customer's screenshot equivalent to money in the bank.

The full proposal is described in the [Manual Bank Transfer and Payment-Provider Policy](docs/architecture/MANUAL_BANK_TRANSFER_AND_PAYMENT_PROVIDER_POLICY_2026-10-24.md).

> **Documentation note:** The Active Business Rules state the intended coexistence of Paystack and direct bank transfer, but the standalone manual-transfer addendum still labels itself as proposed for owner review. Its approval-status wording should be reconciled before production release.

---

## 9. Supplier sourcing: how the system chooses where to buy

This is one of the most important parts of Victorious MARKET.
The customer is buying from Victorious MARKET, but the physical product may come from one of several private suppliers. The system must choose suppliers without exposing the internal sourcing process to the customer.

### Supplier eligibility comes first

A supplier must pass the applicable hard eligibility checks before being considered for an order.

These include the supplier being approved, active and verified; having an approved location in Uyo; having an eligible product offer marked in stock; having sufficiently fresh availability confirmation; being outside any applicable SKU-specific cooldown; having fewer than five active pending handovers; and having a wholesale cost below the public retail price.

A supplier that fails a hard eligibility check must not become eligible just because its price is attractive.

### The sourcing system does not rely on a permanent supplier quantity field

The current policy deliberately distinguishes a general stock signal from an order-specific quantity commitment.
A supplier catalogue record uses a simple stock signal such as *In stock* or *Out of stock*. That signal helps determine whether the supplier's product is eligible for consideration.

But suppose a customer buys five power banks. A supplier's catalogue saying “In stock” does not prove that the supplier can provide five units.
After payment has been verified, the system asks the supplier to confirm that it can fulfil the full requested quantity for that particular order line.

The supplier's response applies to that order and quantity. It is not a permanent quantity statement that automatically guarantees stock for future orders.

### How supplier selection is prioritized

The system first eliminates ineligible suppliers, then evaluates feasible plans capable of fulfilling the complete order.

The approved priority is:
1. **Minimize the number of distinct supplier pickup locations.**
2. **Among plans using the same minimum number of locations, minimize total wholesale cost.**
3. **Prefer better documented fulfilment reliability.**
4. **Apply a stable, deterministic tie-break when the earlier criteria do not decide the result.**

This is not simply “always buy from the cheapest supplier.”

Imagine that an order has three product lines. One supplier can supply all three at a total wholesale cost of ₦70,000. Two other suppliers could supply the products separately for a combined ₦65,000.
The rules prefer the feasible one-location plan, because reducing the number of pickup locations is the first priority. The difference in wholesale cost is considered after pickup-location count.

Why? Every extra supplier location can introduce more pickup coordination, more chances of a shop being unavailable, longer handling time and greater operational complexity.

### Multiple products versus multiple suppliers

A single customer order can involve more than one supplier location.
For instance, the order could contain a mattress from Supplier A and a generator from Supplier B. That is permitted where the sourcing rules allow it.

But if the customer orders five identical units on one product line, the current policy requires one supplier to confirm the full five-unit quantity. The platform must not silently divide that single line across several suppliers to complete the order.

This is important because the system must know who committed to fulfil each line, where the goods are being collected and which source-cost snapshot applies.

### Supplier confirmation timing and failover

Supplier confirmation operates within the configured sourcing window of 8:00 AM to 6:00 PM, Africa/Lagos time.

An eligible supplier has 60 operating minutes to confirm the requested quantity. After 6:00 PM, the timer pauses and restarts fresh at 8:00 AM the next morning, according to the current policy.

If a supplier declines, cannot supply the exact requested quantity, times out or experiences a qualifying pickup stockout, the system can move to the next eligible candidate in the approved sequence. The relevant SKU cooldown and Super Admin alert rules also apply.

If no eligible supplier can fulfil the line, the paid order goes into an administrator-managed exception process. The system must not treat the customer's payment as permission to send a refund automatically or to substitute another product without consent.

The [supplier confirmation policy](docs/architecture/SUPPLIER_ORDER_SPECIFIC_AVAILABILITY_CONFIRMATION_POLICY_2026-10-24.md) documents these rules.

---

## 10. Delivery: one order, one rider and one logistics company

Supplier sourcing and delivery assignment are separate decisions.

An order may involve several supplier pickup locations, but the current model keeps the entire customer order assigned to one rider working through one approved logistics company.

That rule does not mean there is only one pickup stop. It means the customer order must have one coordinated delivery assignment rather than being split among independent riders.

### Small orders and bulky orders

The delivery model distinguishes ordinary small orders from orders containing bulky items:

- **Small-item orders:** Eligible small-only orders may be batched with other customers' orders on the same corridor, subject to route, capacity and safety rules. This can reduce logistics costs without charging a separate delivery fee for every supplier stop.
- **Bulky orders:** If any item in an order is classified as bulky, the entire order becomes bulky for dispatch purposes. It must use a suitable dedicated Keke/tricycle-style trip exclusively for that customer order. Another customer's order must not be added to that trip.

This is the **bulky contagion rule**. A bulky item changes the transport requirement for the entire order, even if the remaining items are small.

The product's bulky flag must be reliable because an incorrect flag can cause the system to assign an unsuitable vehicle, calculate the wrong surcharge or create unsafe loading conditions.

### Delivery operating hours

Physical road transit operates from 8:00 AM to 5:00 PM under the current rules. Supplier confirmation and road transit have separate schedules; the later sourcing deadline does not authorize new road delivery after the delivery window.

Before travelling to the customer, the rider must call the customer using the required contact details. This helps reduce failed journeys caused by a switched-off phone, inaccessible compound or customer absence.

### Pickup and final delivery proof

Victorious MARKET's delivery design includes a chain of custody. It is not enough for a rider to tap a button saying “collected” or “delivered.”

At each supplier pickup location, the process requires:
- Appropriate product and condition checks.
- The correct one-time supplier pickup OTP.
- Mandatory pickup photo evidence.
- A recorded custody event.

At final delivery, the process requires:
- The correct customer delivery OTP.
- Mandatory doorstep photo evidence, with geolocation where required by the active rules.
- A recorded delivery event.

The policy also calls for controlled OTP attempts and protections against brute-force guessing. A failed OTP should not silently complete a handover.

The purpose is to create evidence of who had the goods, when custody changed and whether the final delivery handshake was completed.

---

## 11. How delivery charges work

There are two separate delivery-money calculations.

### What the customer pays

The customer's delivery charge is:
$$\text{Customer Delivery Charge} = \text{One configured base delivery fee per customer order} + \sum (\text{configured bulky surcharge per physical bulky unit})$$

The customer is not supposed to pay a new ordinary delivery fee for every supplier stop.
If an order requires collection from two supplier locations, Victorious MARKET absorbs any extra cost created by that multi-stop logistics plan, unless a future explicitly approved pricing policy changes the arrangement.
The customer charge is calculated independently of the logistics company's corridor tariff.

### What the logistics company receives

The logistics-company payable is:
$$\text{Logistics Payable} = \text{Assigned company's configured corridor base payable} + \sum (\text{company's configured bulky-unit surcharge})$$

The system records which company was assigned, which corridor tariff applied, the bulky quantity and rate, and the resulting amount. These details are snapshotted so that a later change in the tariff schedule does not silently change an existing order's financial obligations.
The company—not automatically the individual rider—is the payee under the current model.

Consider a purely illustrative order:

| Component | Amount |
| :--- | :--- |
| Customer's base delivery fee | ₦2,000 |
| Customer's bulky surcharge | ₦1,000 |
| **Total delivery revenue** | **₦3,000** |
| Logistics company's corridor payable | ₦3,500 |
| Logistics bulky surcharge | ₦500 |
| **Total logistics payable** | **₦4,000** |
| **Victorious MARKET's delivery shortfall** | **₦1,000** |

In this example, the business receives ₦3,000 in delivery revenue but owes the logistics company ₦4,000.
The system must record the ₦1,000 difference as a Victorious MARKET expense or subsidy. It must not pretend that the business received ₦4,000, reduce a supplier's payable to conceal the shortfall or use the retired rule that made the logistics payable a fixed percentage of the customer delivery fee.

This separation is vital for understanding the real profit of the business.

---

## 12. Exceptions: what happens when the normal workflow fails?

This is where an ordinary online store and a properly designed operational system differ.

Real-life orders can fail for many reasons:
- A supplier confirms stock but sells it to a walk-in customer before pickup.
- No eligible supplier can fulfil the exact quantity.
- A supplier shop is closed.
- A product arrives damaged or incorrect.
- A rider cannot complete a pickup.
- A customer rejects an item.
- The customer is absent at the delivery address.
- A payment succeeds, but the application crashes while processing it.
- A refund request is approved, but the payment provider's outcome is uncertain.
- A supplier or logistics company disputes a payable.

Victorious MARKET needs a structured response for each of these cases rather than assuming all orders move directly from payment to delivery.

### A. Paid order, but no supplier can provide the product

The system should try the eligible sourcing candidates according to the approved selection rules. If those candidates are exhausted, the order goes into the Admin Command Tower's exception process.

The administrator may review the available options, including eligible reassignment, an acceptable alternative where policy allows, putting fulfilment on hold, partial fulfilment where the customer is offered the permitted choice, or cancellation and a refund request.

The system must not automatically refund simply because a supplier was unavailable. Nor should it silently substitute a different product, change the price or cancel the paid order without the appropriate decision.

### B. Partial fulfilment

Suppose a customer orders a phone, a power bank and a mattress, but the mattress cannot be sourced after the allowed supplier attempts.
The order moves into the defined partial-fulfilment exception process.

The customer is offered the approved choice. The current exception policy gives the customer 24 hours from the successfully recorded offer to respond.

- **If the customer accepts:** The available items continue through the existing delivery workflow. The unavailable item is cancelled, and its applicable merchandise refund is recorded as a pending refund request. A bulky surcharge attributable to a cancelled bulky item is handled according to the applicable calculation rules.
- **If the customer rejects or the response window expires:** Silence is treated as rejection of the partial offer, and the whole-order cancellation/refund process applies to undelivered merchandise. The original delivery fee is included in the applicable whole-order refund request, subject to the governing policy and law.

This is not authorization for a timer to send money automatically. Expiration may trigger a workflow or create a refund request, but a human Super Admin must still review and manually initiate the permitted refund.

The policy also establishes a one-delivery resolution for accepted partial orders; it does not authorize a separate second delivery merely to bring the cancelled or unavailable item later.

### C. Customer absent at the doorstep

Suppose the rider reaches the customer's compound, but the customer is unreachable.
The order must not be marked delivered just because the rider reached the address. The rider records the failed attempt and the package enters secure logistics custody under the applicable procedure.

The rules permit up to three documented delivery attempts, with future attempts batched along the same corridor and no additional platform delivery fee for those attempts.

After three failed absence attempts, the goods are returned to the originating suppliers and the order is cancelled under the prescribed process. The Fair Protection Refund Rule specifies a merchandise-only refund while the original delivery fee is retained to cover the logistics costs of the three attempts.

That outcome applies to actual customer absence cases. A customer who explicitly rejects the goods at the doorstep must not automatically be recorded as absent; rejection has its own exception and reverse-logistics process.

### D. Defective or incorrect products

A customer may complain about a defective, damaged or materially incorrect item.
The system should capture the complaint, preserve the order and delivery evidence, identify the relevant item and supplier, and allow authorized review of photos or video where applicable. If the goods need to be returned, the return must be tracked through the approved process, including the supplier's receipt of the returned goods and reconciliation of their condition.

A complaint does not automatically approve a refund. But a routine deadline must not be used to automatically deny a customer's remedy where applicable Nigerian consumer-protection law or another valid warranty provides a stronger right.

The active rules currently identify specific non-returnable categories after verified delivery—including perishable fresh food and groceries, personal hygiene products/underwear, unsealed beauty products and opened digital vouchers/scratch cards. The customer-facing terms need to explain the applicable rules clearly and be reviewed for legal compliance.

### E. Payment succeeds but processing crashes

Suppose Paystack confirms that a customer paid, but the application crashes before the order is properly marked as paid.
The safe behaviour is to preserve the provider transaction evidence and reconcile the payment against the original order. The system must not invent a new order, fulfil a guessed purchase or issue an automatic refund.

If the original order cannot be recovered or the payment cannot safely be matched, it should be quarantined for authorized reconciliation.

This is a critical reliability requirement: a system crash must not turn a successfully charged customer into an invisible transaction, and recovery must not create a second fulfilment or financial effect.

The [Order State, Custody and Recovery Policy](docs/architecture/ORDER_STATE_CUSTODY_AND_RECOVERY_POLICY_2026-10-23.md) describes how lifecycle states, recovery and reconciliation are intended to work.

---

## 13. Refunds: requesting, authorizing and completing them

Refunds need their own workflow, separate from both payment verification and delivery status.

Victorious MARKET's current policy includes the **Zero-Automated-Outflow Law**:
> **No webhook, queue worker, scheduled task, timeout or ordinary application script may autonomously send a customer refund or bank transfer.**

The software can identify a situation that may require a refund, calculate a proposed amount and create a pending request. It cannot turn that request into an automatic money outflow.

### The intended refund sequence

1. A qualifying event or complaint is recorded.
2. The system identifies the original order, payment, item and reason.
3. The applicable refund amount is calculated from the original captured order and fee snapshots.
4. An immutable pending refund request is created.
5. An authorized human Super Admin reviews the request and evidence.
6. The Super Admin explicitly approves and initiates the permitted manual dispatch.
7. The relevant payment/bank outcome is checked.
8. The refund is marked complete only after authoritative confirmation and reconciliation.

A request, an approval, a submitted refund and a completed refund are four different things. A timeout must not cause the system to mark a refund complete.

### The payment method determines the permitted refund route

For a Paystack-paid order, the documented policy calls for using the verified original Paystack transaction/channel through the approved refund route where supported.

A manual bank-transfer order cannot be refunded by pretending its bank credit was a Paystack transaction. The standalone manual-transfer policy says that the actual manual-transfer refund destination and matching procedure needs explicit approval before production release.

Until that procedure is approved and implemented, such a refund must remain under an authorized manual resolution process. It must not silently be converted into wallet credit.

This matters because a financial record saying “refund complete” when no money was actually returned would be misleading and potentially harmful to both the customer and the business.

### Cashback is different from a refund

The business's cashback or loyalty benefit is separate from a refund. It must not be used as an informal substitute for returning money to a customer.

The current policy preserves a 5% cashback benefit on eligible post-discount merchandise funded by verified Paystack money, subject to the existing caps, validity, maturity and reversal rules. It excludes delivery charges, and manual-transfer orders do not qualify under the current funding rule.

The policy also defines a separate 24-hour cashback-maturity buffer after qualifying fulfilment. That buffer is not an extension of the customer's ordinary claim window.

---

## 14. The financial system: supplier payables, logistics payables and bank reconciliation

This is one of the most important aspects of your design.

The system must distinguish money received, money owed, money approved for payment, money actually transferred and money reconciled.
These are not interchangeable.

A database entry showing that Victorious MARKET owes a supplier ₦82,000 does not mean ₦82,000 has left the business's bank account. Likewise, a bank transfer receipt uploaded by an administrator does not, on its own, prove that the payment succeeded.

### Supplier payable lifecycle

Suppose a customer orders a mattress. The selected supplier confirms stock and the rider picks it up.
Supplier stock confirmation does not itself make the supplier payable. The payable becomes eligible only after verified fulfilment—or an explicitly authorized completion exception—followed by the required reconciliation.

A sound lifecycle is:
$$\text{Not eligible} \longrightarrow \text{Eligible pending reconciliation} \longrightarrow \text{Reconciled/held as appropriate} \longrightarrow \text{Approved for manual dispatch} \longrightarrow \text{Transfer outcome verified} \longrightarrow \text{Reconciled/closed}$$

A dispute, failed handover, return or uncertain completion may place an obligation on hold. The system must not pay it merely because the order exists or the supplier accepted a sourcing request.

### Logistics-company payable lifecycle

The logistics-company payable follows its own calculation and lifecycle.
It is based on the assigned logistics company's configured corridor rate and applicable bulky surcharge, together with the completion and exception rules. It is not calculated as a percentage of the customer's delivery fee.

The logistics company is the payee under the current model. The system must not automatically treat every rider as a separate person to be paid by Victorious MARKET.

### The confirmed settlement rules

| Rule | Current policy |
| :--- | :--- |
| **Who receives the money?** | The supplier or approved logistics company, as applicable. |
| **How does it leave the business?** | An authorized human makes the bank transfer through the normal business-bank process. |
| **Minimum individual settlement** | ₦1. |
| **Maximum individual settlement** | ₦1,000,000. |
| **FIFO / oldest-first** | Not required and must not be silently introduced. |
| **Automatic splitting of a payment above the cap** | Prohibited. |
| **Settlement operating days** | Monday through Friday under the active operating policy. |
| **Funds prerequisite** | Funds must have actually cleared and been reconciled in the receiving account. |
| **Evidence** | Preserve the outgoing transfer evidence and independently reconcile the bank outcome. |
| **Who can reconcile?** | The initiating authorized administrator may reconcile their own settlement; a second administrator is not mandatory under the current rule. |
| **Automatic money outflow** | Prohibited. |

A request above ₦1,000,000 cannot simply be split behind the scenes into several transfers. It requires an explicit separately authorized business decision under the current rules.

The administrator must also distinguish the initiation, dispatch recording and reconciliation events, and preserve the actor, time, amount, payee, destination, bank reference, evidence version and outcome.

A pending, uncertain, failed or reversed transfer must not be marked as successfully settled. Before attempting another transfer, the original attempt must be reconciled to avoid paying the same obligation twice.

### Why both customer payment methods can share financial infrastructure safely

Paystack and manual bank transfer can share neutral financial principles—such as exact amounts, audit trails, order references, idempotency and reconciliation—without sharing transaction identities.

For example, the ledger can record that an order was paid, but the source and evidence for that receipt must still say whether it was paid through Paystack or verified direct bank transfer.

That separation is what allows the business to run both methods without creating fake provider references or mixing transaction types.

The [Active Business Rules](docs/architecture/ACTIVE_BUSINESS_RULES_2026-10-22.md) and [payment policy](PAYMENT_REFUND_POLICY_AND_WEBHOOK_MATRIX.md) describe the relevant constraints.

---

## 15. What the admin dashboard should actually control

Your administrator dashboard—described in the operating documents as the Super Admin Command Tower—is not just an area for editing product names. It is the control centre for the business.

A mature implementation should bring together the following capabilities:
- **Catalogue management:** Create canonical products, review supplier offers, manage retail prices, control listing visibility and apply manual delisting.
- **Supplier management:** Register suppliers, approve and suspend accounts, inspect eligibility and manage approved locations.
- **Sourcing and allocation:** Monitor exact-quantity confirmations, supplier response deadlines, failover attempts, stockouts and administrative overrides.
- **Payment operations:** Configure which customer payment methods are available, review Paystack events and reconcile direct bank transfers.
- **Delivery operations:** Monitor pickup readiness, corridor assignments, logistics-company selection, OTPs, photographs, failed attempts and custody.
- **Exception management:** Resolve sourcing exhaustion, partial fulfilment, customer absence, rejected goods, returns, disputes and cancellation decisions.
- **Refund management:** Review refund cases, calculate allowed amounts, approve permitted actions and reconcile actual refund outcomes.
- **Settlement management:** Review supplier and company payables, check eligibility, authorize manual transfers, record transfer evidence and reconcile outcomes.
- **Tariff and pricing controls:** Configure customer delivery fees and logistics-company corridor rates separately, including bulky surcharges.
- **Audit and security:** Record actors, reasons, timestamps, before/after states and sensitive administrative actions.

A major design requirement is that the dashboard must make exceptional situations visible. A paid order with no available supplier should not simply disappear from the administrator's attention. An uncertain outgoing transfer must not look like a completed payout. A rejected manual-transfer receipt must not be reopened casually.

Equally, an administrator's ability to intervene should not permit bypassing payment verification, delivery evidence or other non-negotiable financial controls.

---

## 16. How the software should protect against fraud, bugs and duplicate effects

The software needs to be dependable under conditions less convenient than a successful demonstration.

A customer may press the payment button twice. A payment webhook can be delivered more than once. A queue job can retry. Two workers may try to allocate the same stock. An administrator can refresh a page while another action is still processing. The server might crash after the bank transaction succeeds but before the database is updated.

Victorious MARKET must be designed so that repeated or interrupted processing does not duplicate business effects.

### The core engineering safeguards

| Safeguard | Why it matters |
| :--- | :--- |
| **Server-side price calculations** | Prevent customers from manipulating amounts in the browser. |
| **Payment verification** | Prevent an initialization request, screenshot or client redirect from being mistaken for payment. |
| **Transaction uniqueness** | Prevent one payment transaction from paying two orders. |
| **Idempotency** | Prevent a replayed event from creating another assignment, payable or financial effect. |
| **Database constraints and transaction boundaries** | Protect against race conditions between concurrent requests and workers. |
| **Immutable financial snapshots** | Preserve the amount agreed for an order even when current prices or tariffs later change. |
| **Role-based authorization** | Restrict sensitive actions to the correct roles. |
| **Private evidence storage** | Protect customer receipts, pickup photographs, delivery photographs and payout evidence. |
| **Append-only audit records** | Allow the business to investigate who did what and when. |
| **Reconciliation** | Resolve uncertain outcomes from real payment and bank evidence instead of guessing. |
| **Recovery procedures** | Handle crashes, late events, retries and incomplete operations safely. |

For Paystack, the active rules specifically require trusted webhook verification, including HMAC-SHA512 verification, together with idempotency and amount/currency/reference checks.

For physical delivery, the design includes OTPs, photographic evidence and controls against OTP guessing. The policy also specifies authentication protections, role separation and browser/network security measures.

Those are required properties of the intended system, not proof that every relevant code path has been implemented correctly. The implementation needs to be inspected and tested against them.

---

## 17. What the data and accounting records must preserve

The software should not try to represent the entire business with a single order status.

An order could be paid but still awaiting supplier sourcing. It could be delivered while a separate return complaint remains open. A refund may be approved but not yet dispatched. A supplier payable may be eligible but still awaiting reconciliation.

These are separate dimensions.

For every financially significant event, the system should preserve enough information to reconstruct what happened:
- The original order and its payment-method identity.
- The original captured product prices, quantities and fee snapshots.
- The verified Paystack transaction or verified incoming bank credit.
- The supplier confirmation attempt and the exact quantity requested.
- The selected supplier and agreed wholesale-cost snapshot.
- The assigned logistics company and tariff snapshot.
- The pickup and delivery OTP/photo evidence.
- Refund and dispute requests, decisions and outcomes.
- Supplier and logistics-company payables, settlement requests and reconciliation evidence.
- The actor, reason, timestamp and state changes for sensitive actions.

This is how the software can answer questions such as:
- “Which order did this bank payment cover?”
- “Was this supplier actually responsible for the product that was delivered?”
- “Why was this supplier payable approved?”
- “Was the refund only requested, or did money actually leave the business?”
- “Did the rider collect the product and obtain the required evidence?”

The [Order State, Custody and Recovery Policy](docs/architecture/ORDER_STATE_CUSTODY_AND_RECOVERY_POLICY_2026-10-23.md) gives the fuller lifecycle and audit requirements.

---

## 18. What the customer sees—and what must remain private

A good customer experience should be simple even though the backstage operation is complex.

The customer should see Victorious MARKET's product names, descriptions, images, specifications, retail prices, delivery information, payment choices, order status, support contacts and relevant return/refund terms.

The customer should not need to understand internal supplier selection or see competing supplier offers.

The following information is intended to remain private:
- Supplier identities and private supplier shop details.
- Supplier wholesale prices.
- Internal sourcing comparisons and supplier ranking.
- Internal assignment decisions and reliability information.
- The platform's merchandise spread.
- Sensitive payment-provider credentials and internal banking secrets.
- Internal financial ledgers and other customers' order information.

Riders also need appropriately restricted interfaces. They require the delivery information needed to collect and deliver goods; they do not need to see the retail margin or wholesale-cost details.

The public site and private administrative portals therefore have different responsibilities. The public site should support customer trust and discoverability. The operational tools should protect private information and expose only what each authorized role needs.

---

## 19. How WhatsApp fits into Victorious MARKET

Your preferred business arrangement is website-first with WhatsApp support.

That means the website is the principal place for customers to discover products, view the official selling prices and place orders. WhatsApp can provide a familiar support channel for people who need assistance or clarification.

It can help with questions about products, support requests, delivery issues and communication about approved exception options.

However, WhatsApp communication should not replace critical backend controls.
A message saying “I have paid” does not verify a payment. A support agent promising a replacement should not silently modify a paid order. An informal message saying “refund sent” should not mark a refund complete.

Any important financial or operational decision made through a support conversation must be recorded in the official system and handled under the relevant permission and evidence rules.

That way, WhatsApp makes the business easier to reach without becoming a second, uncontrolled order or accounting system.

---

## 20. The technology: how the pieces fit together

At the software level, Victorious MARKET is being designed as several cooperating components rather than a simple catalogue page.

A useful way to visualize the intended architecture is:
- **Customer-facing website:** Catalogue · Account · Cart · Checkout · Order information · Support
- **Application backend:** Authentication · Validation · Pricing · Order lifecycle · Permissions
- **Payment subsystem:** Paystack and direct bank verification
- **Sourcing subsystem:** Supplier eligibility and exact-quantity confirmation
- **Fulfilment subsystem:** Dispatch, OTPs, custody and delivery
- **Financial subsystem:** Ledger, refunds, payables and reconciliation
- **Persistent database and audit history:** Orders · Payments · Supplier offers · Evidence · Status transitions · Financial records

That diagram is a conceptual view of the target architecture, not a declaration that each component has already been completed and integrated.

The application backend enforces the rules, while the persistent database preserves orders, transactions, supplier assignments and historical decisions.

Background jobs may be useful for notifying suppliers, processing non-financial work and retrying safe operations. However, retries must not create duplicate assignments or duplicate money effects. The Zero-Automated-Outflow Law still applies: a background worker or timeout cannot autonomously send a refund or bank transfer.

### A note about the existing website and repository

You have also worked on the WordPress/WooCommerce website, while the GitHub repository contains a separate application-code and policy effort.

I have not verified that the public website, the repository's backend, the database and the live payment setup are already integrated into one fully operational deployment. They should not be assumed to be the same system merely because they belong to the Victorious MARKET project.

A final technical review needs to verify which application currently powers the public site, which code is deployed, which database it uses, which payment credentials and endpoints are configured, and whether its runtime behaviour matches the current policy.

---

## 21. Testing: how we can know the system is safe enough

Tests are not merely a final formality. For Victorious MARKET, they are the evidence that the software preserves the rules under normal, abnormal and adversarial conditions.

The system needs multiple kinds of testing:
- **Business-rule tests:** Verify intended decisions (out-of-Uyo destinations fail closed, suspended suppliers cannot be assigned to new sourcing attempts, listings become unavailable when no eligible in-stock supplier remains, a supplier must confirm the full quantity of the relevant order line, a bulky item makes the whole order a dedicated bulky trip, customer delivery fee and logistics-company payable use separate calculations, manual transfer and Paystack cannot verify one another's transactions, settlement limits, operating days and non-FIFO selection rules are enforced).
- **Security and authorization tests:** Confirm role separation, tamper-resistance on checkout parameters, and negative authorization tests (a customer cannot change a price by editing a request, a supplier cannot access private financial information, and a support account cannot perform a Super Admin-only action).
- **Duplicate and concurrency tests:** Simulate duplicate webhooks, multiple workers claiming an order simultaneously, an administrator double-clicking a control, repeated settlement submissions and concurrent attempts to allocate limited stock.
- **Crash-recovery tests:** Verify atomic state resumption, inbox fence guarantees, and zero duplicate financial side-effects (e.g., after receiving a successful payment event but before completing the corresponding database transition).
- **Financial reconciliation tests:** Validate double-entry ledger balance, reservation committed/released lifecycle, and exact payout reconciliation.
- **Integration and operational tests:** Transition from mocks to live provider credentials, cloud storage, and real-world acceptance testing.

---

## 22. The new mandatory AI Engineering Q&A Gate

This is a significant part of your engineering approach.
You asked for a process in which an AI must answer questions about the work it implements rather than merely produce code and say that it is finished.

The repository now contains:
- [AI Change Question-and-Answer Gate](docs/engineering/AI_CHANGE_QUESTION_AND_ANSWER_GATE.md)
- [Pull-request template](.github/pull_request_template.md)
- [AI Engineering Q&A GitHub Actions workflow](.github/workflows/ai-engineering-qa-gate.yml)

For every proposed code change, the AI must address twelve questions covering:
1. What changed?
2. Which approved policy permits it?
3. What are the preconditions, state transitions and authorization checks?
4. What can go wrong, and how does the system recover safely?
5. How are duplicate and concurrent effects prevented?
6. What are the financial and audit consequences?
7. Which tests were run, and what did they actually prove?
8. What was inspected beyond the changed lines?
9. Were any tests or controls removed, weakened or skipped?
10. Does the change preserve the current Victorious MARKET operating model?
11. What are the risks, deployment requirements and rollback plan?
12. What remains unproven?

This is important because an AI-generated implementation could look plausible while breaking a rule elsewhere. For example, adding a refund button might appear to work but accidentally bypass payment-method isolation, fail to preserve a financial snapshot or allow an unauthorised role to initiate a refund.

The Q&A forces the developer to examine those connections.

But there is a distinction between having the gate and making it mandatory. On the last GitHub inspection, the workflow existed, but main was reported as unprotected, and no repository rulesets were found. That means I cannot claim GitHub is already blocking every merge that fails the Q&A check.

The required-status-check and branch-protection configuration still needs to be activated and verified. Moreover, the workflow only checks that the questions have non-empty answers. Human/code review and executable tests are still required to determine whether those answers are correct.

This is how the engineering process should work:
$$\text{Policy} \longrightarrow \text{Change explanation} \longrightarrow \text{Implementation} \longrightarrow \text{Automated tests} \longrightarrow \text{Review of evidence} \longrightarrow \text{Acceptance or rejection}$$

---

## 23. What is already defined, and what is not yet proven?

This is the most important distinction when evaluating the overall progress of Victorious MARKET.

The repository contains extensive rules for the intended business operation. That is valuable because the software can be built against explicit decisions instead of relying on guesswork.
However, the documents themselves repeatedly state that policy alignment does not certify implementation.

| Area | Intended design | What still has to be demonstrated |
| :--- | :--- | :--- |
| **Public retailer model** | One Victorious MARKET brand and private suppliers | Public/private data isolation in the actual application |
| **Product catalogue** | Canonical products linked to private supplier offers | Listing availability and delisting work reliably in the runtime |
| **Customer payment** | Paystack and direct transfer, with one method per order | Both flows coexist safely, including under duplicate events and uncertainty |
| **Manual transfer verification** | Screenshot as supporting evidence, actual bank credit independently checked | Secure evidence handling, reviewer controls and real reconciliation |
| **Supplier allocation** | Verified payment first, then exact-quantity confirmation and failover | Concurrency-safe sourcing, no duplicate assignments and correct exhaustion handling |
| **Delivery** | One rider/company, bulky isolation, OTPs and photos | Real workflow enforces every required step and preserves proof |
| **Refunds** | Manual Super Admin authorization; no automatic outflow | All entry points block unauthorized or automatic dispatch, including retries |
| **Supplier and company settlements** | Manual bank transfer with limits, evidence and reconciliation | Amount limits, uniqueness, permissions and uncertain-outcome recovery |
| **Crash recovery** | Durable event identity and safe reconciliation | Actual tests show no duplicate or lost financial effects across crash points |
| **AI Q&A gate** | Policy, template and workflow are present | Required check is enforced by GitHub rules and the check is tested in a real PR |

The next significant engineering goal should not simply be adding more documentation. It should be closing the gap between the defined policy and verified runtime behaviour.

For the money-related flows in particular, passing ordinary happy-path tests is not sufficient. We need evidence about replay safety, concurrency, crashes, uncertain bank outcomes and duplicate prevention.

---

## 24. What happens in a complete real-world example?

To bring the whole system together, imagine this order:
A customer in Uyo orders a mattress and a power generator. Both items are listed on the Victorious MARKET website.

The customer registers, provides the delivery landmark and reaches checkout. The platform creates the order and displays the correct merchandise total, delivery fee and applicable bulky surcharge.

The customer chooses direct Manual Bank Transfer. The order is bound to that payment method, and the configured receiving-account instructions are shown.

The customer transfers the money, uploads a screenshot, and submits it for review. The screenshot alone does not activate fulfilment. An authorized administrator independently verifies the actual bank credit, matches the transaction to the order and approves the payment.

Only then does sourcing begin.

The system evaluates eligible supplier plans. It considers pickup-location consolidation first, then wholesale cost among plans with the same minimum number of pickup locations, followed by reliability and the deterministic tie-break.

The chosen suppliers receive order-specific requests. Each must confirm the full quantity required for the relevant product line. If one supplier cannot fulfil its line, the platform attempts permitted failover. If no eligible supplier can fulfil the order, the order enters the admin exception process.

Once the required products are confirmed ready, Victorious MARKET assigns one rider through one approved logistics company. Because the order contains bulky items, the entire order receives an appropriate dedicated bulky trip. The customer is not charged a separate ordinary delivery fee for each supplier stop.

At pickup, the rider verifies the goods and completes the required OTP/photo evidence. Before departure to the customer, the rider makes the mandatory call. At the doorstep, the delivery OTP and required photo are recorded.

After delivery, the system preserves the order and evidence, handles any applicable claim or loyalty-maturity windows, and reconciles the relevant financial obligations.

The supplier payable is calculated using the agreed wholesale-cost snapshot. The logistics-company payable uses its assigned corridor tariff and bulky surcharge. Neither is automatically sent out of the business bank account.

On an eligible business day, an authorized Super Admin reviews the relevant payable, confirms the funds have cleared and been reconciled, and makes the appropriate manual bank transfer. The outgoing transfer evidence is attached, and the actual bank outcome is independently reconciled before the record is considered successfully settled.

The order now has a traceable history from browsing through payment, sourcing, custody, delivery and settlement.
That is the complete business process Victorious MARKET is being designed to support.

---

## 25. The overall goal

Victorious MARKET should eventually function as a dependable Uyo retailer whose customers experience a simple, trustworthy online shop while the business controls the more complex operation behind it.

Its success depends on six things working together:
1. **Trustworthy customer experience:** Accurate listings, transparent charges, clear communication, reliable delivery and accountable support.
2. **Reliable sourcing:** Eligible suppliers, exact-quantity confirmation, private wholesale pricing and controlled failover.
3. **Disciplined fulfilment:** One rider/company per customer order, suitable bulky dispatch, proof of pickup and proof of delivery.
4. **Secure financial handling:** Verified payments, immutable snapshots, manual authorization for money outflows and balanced, reconciled records.
5. **Safe recovery from failures:** Explicit exception workflows, idempotency, concurrency safety, crash recovery and evidence-backed decisions.
6. **Evidence-driven engineering:** Every change explains what it does, why it aligns with policy, how it can fail and which tests demonstrate its behaviour.

The defining principle of the project is that the business must not confuse an action being requested with that action having succeeded.

A payment attempt is not a verified payment. A supplier confirmation is not a pickup. A rider arriving is not a delivery. A refund request is not a completed refund. A transfer receipt is not reconciled bank cash. A green workflow is not proof that the whole system is safe.

Each important transition needs the appropriate evidence.

That is what makes Victorious MARKET a controlled retail operation rather than merely a website that accepts orders. The next stage is to prove, through the actual code and testing, that the implemented system behaves according to these rules—even when transactions fail, people make mistakes, concurrent actions collide or the application crashes.
