# Victorious MARKET — Backend Service

> **Authoritative Master Blueprint:** This application is strictly governed by [VICTORIOUS_MARKET_MASTER_BLUEPRINT.md](../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md).

Built specifically for **Uyo, Akwa Ibom State**, this platform operates as a centrally managed retailer engine enforcing:
1. **The Plaza Centralization Law:** Sourcing from the central Plaza commercial cluster.
2. **SKU-Level Deterministic Sourcing:** 4 hard filters + multi-item shop consolidation + `cost_price ASC` sorting.
3. **One Order, One Rider:** A single rider owns the entire customer order from all pickup stops to the doorstep.
4. **Bulky Contagion & Keke Isolation:** Any bulky item (`is_bulky = true`) forces the entire order onto a dedicated, isolated Keke trip.
5. **Cryptographic Proof Over Trust:** Live camera photo + Shop Pickup OTP per stop; live camera photo + Delivery OTP at customer doorstep.
6. **Corridor Logistics:** 8 Master Uyo Road Corridors (`COR-01` to `COR-08`) with pre-negotiated flat 3PL trip rates.

For full architectural laws, pricing formulas, and settlement workflows, see [VICTORIOUS_MARKET_MASTER_BLUEPRINT.md](../VICTORIOUS_MARKET_MASTER_BLUEPRINT.md).
