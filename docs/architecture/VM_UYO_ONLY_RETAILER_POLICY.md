# Victorious MARKET — Uyo-only central retailer policy

**Authority:** This policy reflects the owner's current launch decision and supersedes any older planning text that describes inter-LGA delivery, nationwide dispatch, public supplier storefronts, or customer-facing pickup as available launch features.

## Customer promise and geography
- Victorious MARKET is one centrally managed retailer; customers buy from and contact Victorious MARKET, not a supplier.
- New sourcing and doorstep delivery are limited to **Uyo LGA, Akwa Ibom State**. “Akwa Ibom” does not mean all LGAs in the state. Eket, Oron, Ikot Ekpene, Abak, other LGAs, interstate and park-to-park fulfilment are out of scope.
- Customer address validation, cart checks, checkout, fee resolution, admin lane controls and background fulfillment must fail closed for any destination or sourcing point outside Uyo.
- Historical out-of-Uyo lanes and orders may remain for audit/reconciliation, but they must not authorize new fulfillment. Admin tools cannot reactivate non-Uyo lanes.

## Supplier privacy and allocation
- Suppliers are private sourcing partners, not public marketplace sellers. Public product pages, catalogues, APIs and customer messages must not expose supplier shop names, addresses, contacts, wholesale prices or direct supplier links.
- Each supplier may register one approved shop/pickup point internally. Supplier onboarding and source location verification are admin-controlled.
- After successful verified payment, the system may assign eligible items to approved Uyo suppliers based on confirmed availability, total cost and other deterministic operational factors. Admin may override with authorization, reason and audit history.
- The entire customer order must be feasible before checkout is offered. Do not promise partial fulfillment if any line cannot be sourced locally.

## Delivery and money controls
- Delivery fee is one base fee per customer order, not per product or supplier, plus the configured surcharge for each eligible bulky unit. Show the total before payment.
- For new retailer orders, the logistics-company payable is the assigned company's active Uyo corridor base payable plus its per-bulky-unit surcharge, payable to the company—not directly to its rider. This is independent of the customer's flat per-order fee. The former 90% share is historical-only. Authorized admin overrides must record amount, actor, reason and audit event.
- Supplier payable is based on immutable wholesale-cost snapshots and stays held until delivery is verified and financial reconciliation passes.
- Delivery completion requires the customer's valid OTP and mandatory doorstep photo proof, with auditable timestamps/actor records.
- Cashback remains 5% of eligible real customer money applied to merchandise, funded from Victorious MARKET gross margin. Exclude delivery fees and redeemed wallet/cashback credit from the eligible base; preserve configured caps, maturation, reversal and refund rules.
- Payment/webhook retries and repeated jobs must be idempotent. A replay must not duplicate orders, supplier payables, logistics payables, refunds, cashback or journal entries. Financial paths fail closed when amounts, ownership, status or reconciliation cannot be verified.
- Paystack prepayment is required for normal checkout; COD and instant refund-wallet-credit checkout tender are disabled at launch. Cashback redemption remains separately allowed under its existing caps. Public pickup reservation is disabled; any exceptional pickup must be arranged by central support, explicitly agreed by the customer and separately authorized/audited without bypassing payment or Uyo-boundary checks.

## Acceptance gates before production
1. Non-Uyo destination is rejected by location selection, cart, checkout, fee API, lane administration and asynchronous fulfillment even if a legacy lane is active.
2. Public catalog/API/HTML responses contain no supplier identity, shop address, supplier contact or wholesale price.
3. Multi-item orders use one base delivery fee plus the applicable bulky-unit surcharge(s), independent of supplier count.
4. Supplier and logistics payables are created once, with correct ledger ownership, only at their permitted state transitions.
5. Delivery OTP and photo proof are mandatory; refunds and cashback reversals are idempotent.
6. Automated tests and CI pass against the current integration branch before merge/deployment.

**Customer tagline:** *VICTORIOUS MARKET — Your Trusted Online Market in Uyo, Akwa Ibom State. Sourced in Uyo. Delivered within Uyo.*
