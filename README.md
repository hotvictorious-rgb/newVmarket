# Victorious MARKET (newVmarket)

> **Sovereign Omnichannel Retail & Directional Logistics Operating System**  
> Brand Colors: Royal Purple (`#5E17EB`) & Rich Gold (`#FFD700`)  
> Primary Asset: `vm_icon.jpg`

Victorious MARKET is a zero-trust, multi-actor retail marketplace and directional logistics ecosystem built for physical commerce verification, instant customer rewards, and fraud-proof digital settlement in Akwa Ibom State, Nigeria.

---

## 🏛️ The 7 Distinct Actor Portals & Dashboards

The system enforces strict domain boundaries with **zero role bleed**:

| # | Role | Login Portal | Dedicated Dashboard | Primary Domain & Permissions |
|---|---|---|---|---|
| 1 | **Super Admin** | `/admin/login` | `/admin` | Sovereign Command Tower: 90/5/5 manual settlement desk, directional LGA lanes, announcement broadcast control center, platform audit logs. |
| 2 | **Admin Staff** | `/admin-staff/login` | `/admin-staff` | Operations Desk: Catalog moderation & 7-day freshness queue, order triage, counter monitor. *(Non-treasury clearance: cannot alter bank accounts or disburse payouts).* |
| 3 | **Vendor Merchant** | `/vendor/login` | `/vendor` | Merchant Commerce Suite: Store sales analytics, 90% payout ledger, 7-day freshness countdowns, shop orders, 48-hour bank cooldown protection. |
| 4 | **Branch Counter Staff** | `/counter/login` | `/counter` | In-Shop Inspection Terminal: Physical examination sign-off (`RES-XXXXXXXX` code verification), Rider Handover OTP custody release. |
| 5 | **Logistics Fleet Desk** | `/logistics/login` | `/logistics` | Fleet Logistics Center: Rider roster (`Motorcycle UYO-123-AK`), directional LGA waybill manifests, 5% logistics fee wallet. |
| 6 | **Delivery Rider** | `/rider/login` | `/rider` | Rider Cockpit: Shift toggle (On/Off Duty), Step 1 (counter handover OTP), Step 2 (doorstep customer OTP handshake), daily drop earnings. |
| 7 | **Customer Shopper** | `/login` | `/customer` | Shopper Account Suite: 5-stage live multi-stage order tracking, secret 6-digit delivery OTP card, 5% sovereign cashback rewards wallet, saved Akwa Ibom addresses. |

---

## 🔒 Mathematical Invariants & Zero-Trust Architecture

1. **Strict 90/5/5 Split:**
   - `90%` &rarr; Verified Vendor Merchant
   - `5%` &rarr; Operating Logistics Company
   - `5%` &rarr; Customer Cashback (Funded by Platform)
   - Calculated with arbitrary-precision `bcmath` (4 decimals) preventing floating-point drift.

2. **Dual-Custody Physical Handshakes:**
   - **Handshake 1:** Branch Counter verifies courier's secret 6-digit pickup OTP to transfer custody from plaza to courier.
   - **Handshake 2:** Courier verifies customer's private 6-digit delivery OTP at the doorstep before releasing parcel.

3. **48-Hour Bank Cooldown Invariant:**
   - Changing bank account details enforces a mandatory 48-hour freeze on disbursements to prevent hijacked account takeovers.

4. **7-Day Freshness Lifecycle:**
   - Products require inventory re-verification every 7 days to preserve verified listing status on the storefront.

---

## 🚀 Quick Setup & Local Development

### 1. Requirements
- PHP 8.2+
- Composer
- SQLite3 or MySQL

### 2. Installation
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

### 3. Run Dev Server
```bash
php artisan serve --port=8080
```
Visit storefront at `http://127.0.0.1:8080`.

### 4. Run Test Suite
```bash
php artisan test
```
All 28 unit and feature tests (108 assertions) must pass.
