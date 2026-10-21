<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LegalDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Legal-document source edits do not update an already-running database until this seeder is deployed/run and publication is reviewed. Keep prior LegalDocumentVersion rows immutable; aligned customer-facing documents use a new version.
        $effectiveDate = Carbon::create(2026, 10, 21);

        // 1. TERMS OF SERVICE
        $termsContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — TERMS AND CONDITIONS OF SERVICE
**Effective Date:** October 21, 2026  
**Version:** 2.0.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. Introduction & Operating Philosophy
Welcome to **VICTORIOUS MARKET** ("Victorious Market", "the Platform", "we", "us", or "our"). Victorious Market is an institutional digital commerce and fulfillment platform designed and managed on the fundamental philosophy:  
**Trust First. Quality Always. Accountability at Every Stage.**

By browsing listings or placing orders, you agree to these Terms. Victorious MARKET is the customer-facing retailer. Suppliers are private sourcing partners and are not public marketplace sellers. VM controls customer-facing listings, prices, customer payments, refunds, support and fulfilment coordination. This policy does not prove that every application workflow has already been migrated.

---

### 2. Retailer and Customer Payment Model
Victorious MARKET is a centrally managed retailer, not a public multi-vendor marketplace. Customers buy from VM. Suppliers remain private product sources whose offers must be approved and linked internally to VM product/order records.
* **Central transaction authority:** VM controls public listings, retail prices, order/payment records, payment verification, customer support, authorized refunds and fulfilment coordination. Delivery is coordinated through approved logistics companies; each assigned rider must be linked to exactly one active logistics company.
* **Authorized payment only:** Customers may pay before delivery through VM's authorized workflow or use an explicitly authorized supplier-shop reservation/payment-at-location flow where payment is processed and recorded through VM. Suppliers may not collect off-platform cash or private-account transfers. Doorstep COD is disabled at launch. Do not describe funds as regulated or segregated escrow unless independently verified.

---

### 3. Supplier Offers and Catalogue Integrity
* Suppliers submit product information, condition, warranty/packaging/accessory limitations, wholesale price, availability and sourcing location for VM review. A submission does not automatically publish a listing or guarantee a purchase.
* VM creates one canonical public listing for a new product or maps an approved supplier offer to an existing matching listing. Suppliers cannot publish listings, set VM's retail price, or contact customers outside authorized fulfilment/support procedures.
* Listings must accurately state product condition and warranty/accessory limitations. Factory-sealed/OEM status and manufacturer warranty may be claimed only where true and verified. Pre-owned products must disclose cosmetic condition, battery health where relevant, and missing accessories.
* Counterfeit or misrepresented goods are prohibited. VM may suspend an offer/supplier and pursue remedies consistent with contract and applicable law.

---

### 4. Payments, Refunds & Revenue Allocation
* **Cryptographic Gateway Verification:** Payments are processed via Paystack. An order is marked as paid only after server-to-server cryptographic verification (HMAC-SHA512).
* **Payment and reconciliation controls:** Customer payments must be verified server-side against the exact reference, amount, currency and order/customer binding. Refund approval, gateway submission, provider confirmation and reconciliation are distinct states. Do not promise regulated/segregated escrow unless independently verified.
* **Retail accounting:** Supplier purchase cost is a VM cost/payable, not a vendor commission. Record customer revenue/receipts, supplier purchase costs/payables, delivery charges, approved logistics-company charges/payables, gateway fees, discounts, refunds and any separately approved cashback as distinct entries.
* **No inherited split:** Former 90/5/5 merchandise and 90/10 delivery marketplace allocations do not apply to new retailer transactions. No new cashback accrual or logistics-company fee percentage may be inferred without explicit approval. Supplier payment eligibility begins only after verified successful customer delivery or confirmed pickup and required reconciliation; eligibility is not automatic transfer.

---

### 5. Delivery, Handover & Cryptographic Handshakes
* **Geofenced Local Delivery:** Operations commence within verified coverage zones in Akwa Ibom State (centered in Uyo LGA) and expand only across verified logistics corridors.
* **Doorstep handover:** The customer's delivery OTP must be securely verified and a doorstep delivery photo recorded before completion.
* **In-shop pickup:** The customer must present the `RES-XXXXXX` reservation pass AND complete one-time customer pickup OTP verification. The vendor must securely verify both and record an in-shop pickup photo before completion. Neither credential replaces the photo; the customer pickup OTP is distinct from any rider-to-vendor parcel-custody OTP.
* **Evidence limits:** GPS, customer signature, separate rider-pickup photo and hub inspection photo are not mandatory. OTP/pass verification confirms the handover process; neither OTP nor photo proves product quality or removes applicable consumer rights.

---

### 6. Doorstep Acceptance & Partial Rejection Rights
* **Item-level decisions:** Where an order contains multiple items, VM records each affected item and quantity. Customers may report non-conforming, damaged or incorrect goods under the published return policy and applicable law.
* **Return handling:** VM authorizes the return/inspection route and coordinates with the supplier as needed. A supplier cannot unilaterally decide the customer's remedy or make a customer refund dependent on supplier recovery. Any replacement requires customer agreement; no silent substitution is permitted.

---

### 7. Returns & 24-Hour Inspection Policy
* **24-Hour Standard Window:** Eligible ordinary issues should be reported within twenty-four (24) hours of confirmed doorstep delivery or in-shop pickup. This operational window does not waive statutory rights or valid warranty obligations; authorised admins may review legitimate late requests.
* **Return handling:** Support/admin coordinates the authorized return/inspection route and may involve the private supplier. A platform collection-point/hub handover is not mandatory. Applicable law and valid warranty obligations prevail.
* **Late requests and legal rights:** The standard operational request window is 24 hours from confirmed delivery or in-shop pickup. This is not a blanket waiver of statutory rights or warranties; an authorised admin may review legitimate late requests and must record the decision.

---

### 8. Refunds & Customer Wallet Credits
* **Administrative Authorization:** An authorised admin reviews and approves ordinary refunds, with evidence, reason, amount, approver and timestamps recorded. Applicable law and valid warranty obligations prevail.
* **Disbursement and tender integrity:** At launch, approved refunds are initiated through the verified original Paystack payment route where supported. Wallet-credit refunds, legacy wallet-credit checkout tender, and off-platform refunds/payments are disabled. Cashback redemption is a separate capped loyalty discount. A submitted refund is not complete until provider confirmation/reconciliation.

---

### 9. Cashback
Existing cashback balances and obligations must be handled according to their recorded terms and applicable policy. The former 5% marketplace allocation does not authorize new cashback accrual on retailer-model sales. New cashback accrual, funding, maturity and expiry rules require explicit owner/accountant approval before enablement. Any approved cashback adjustment is separate from a gateway cash refund and must not create a silent debit to a customer's cash balance.

---

### 10. Platform Suspension & Financial Recovery
* **Safety and suspension:** VM may restrict access or suspend a supplier, logistics partner or customer for fraud, unsafe conduct, counterfeit goods, off-platform payment requests or abuse, subject to applicable law and fair review.
* **Supplier recovery:** Any supplier recovery requires evidence of supplier responsibility, contractual recoverability and an authorized decision. A complaint alone is not a debt. Supplier recovery is separate from customer refunds and must never delay a valid customer remedy.

---

### 11. Governing Law & Arbitration
These Terms are governed by applicable laws of the Federal Republic of Nigeria and, where applicable, Akwa Ibom State. Customers may contact Victorious MARKET support to seek resolution. Nothing in this clause removes any mandatory right to approach a regulator, court, or other forum available under applicable law.
MARKDOWN;

        $doc1 = LegalDocument::updateOrCreate(
            ['slug' => 'terms-of-service'],
            [
                'title' => 'Terms and Conditions of Service',
                'category' => 'terms',
                'current_version' => '2.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Retailer terms covering private supplier sourcing, VM-controlled prices and payments, central fulfilment, customer remedies, and financial controls.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc1->id,
                'version' => '2.0.0',
            ],
            [
                'title' => 'Terms and Conditions of Service',
                'content' => $termsContent,
                'summary_of_changes' => 'Rebased customer terms to the VM retailer/private-supplier model; retired marketplace allocation claims and aligned central fulfilment and customer remedies. Previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 2. RETURN AND REFUND POLICY
        $refundContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — RETURN, REFUND & DOORSTEP ACCEPTANCE POLICY
**Effective Date:** October 21, 2026  
**Version:** 2.0.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. Standard 24-Hour Return/Claim Window
The standard operational request window for eligible ordinary returns/refunds is **twenty-four (24) hours** from confirmed doorstep delivery or confirmed in-shop pickup. This is not a blanket waiver of applicable consumer rights or valid warranty obligations; authorised admins may review legitimate late requests.

---

### 2. Doorstep Inspection & Partial Rejection
* **Inspect Before Releasing Rider:** When a delivery package arrives, the customer is invited to verify external package seals and contents in the presence of the delivery rider.
* **Item-Level Decisions:** If your order contains multiple items (e.g., a phone and a charger), you may accept the phone and reject the incorrect charger right at the doorstep.
* **Documentation:** The rider records the affected item, quantity, reason, timestamp and return-to-vendor outcome in the authorised workflow. The required doorstep delivery photo remains handover evidence; no separate rider-pickup or rejection photo is mandatory. Additional evidence may be attached where available.

---

### 3. Eligible Grounds for Return
Common grounds for review include the following examples; this list is not exhaustive and does not exclude applicable statutory rights, valid warranties, or remedies for non-conforming goods:
1. **Wrong Item Delivered:** Product delivered differs in model, specifications, color, or variant from what was ordered.
2. **Defective or Inoperable:** Electronic or mechanical product fails to operate upon initial testing.
3. **Condition Misrepresentation:** A pre-owned / UK-used item exhibits undisclosed damage or defects not stated in the listing.
4. **Transit Damage:** Goods arrived visibly damaged during transit.

---

### 4. Product categories with possible seal/hygiene restrictions
Where lawful and clearly disclosed, seal or hygiene restrictions may apply to the following categories. These restrictions do not bar remedies for wrong, defective, unsafe, misdescribed, or otherwise non-conforming goods, or any rights that cannot lawfully be excluded:
* Personal grooming, health, and cosmetic goods with broken factory seals.
* Underwear, swimwear, and intimate apparel.
* Consumable goods, groceries, and perishables.
* Digital software licenses or activation codes once revealed.

---

### 5. Return Process & Collection Point Handover
1. **Submit Request:** Report the issue through the Customer Portal or support within the standard 24-hour claim window where practicable. Describe the affected item and reason; attach available photos/video or other evidence when available. Lack of a particular photo alone must not extinguish a non-waivable legal right.
2. **Return coordination:** Contact support through the order record for the authorised return/inspection steps. A platform collection-point or hub drop-off is not mandatory.
3. **Review & decision:** An authorised admin reviews the evidence and applicable remedy, including vendor inspection where appropriate, and records the decision. Any replacement/redelivery requires customer agreement.
4. **Refund execution:** Approved refunds are initiated through the verified original Paystack payment route where supported; completion is confirmed only after provider reconciliation.

---

### 6. Refund Disbursement & Timelines
* **Gateway processing:** Approved refunds are submitted through the verified original Paystack route where supported. Actual processing and posting times depend on Paystack, the payment method, and the receiving financial institution; no fixed completion time is guaranteed. Submission is not confirmation of completion.
* **Refund destination and tender:** At launch, approved refunds are initiated through the verified original Paystack payment route where supported. Wallet-credit refunds and legacy wallet-credit checkout tender are disabled. Cashback redemption remains a separate loyalty discount; off-platform refunds/payments are prohibited.
MARKDOWN;

        $doc2 = LegalDocument::updateOrCreate(
            ['slug' => 'return-and-refund-policy'],
            [
                'title' => 'Return, Refund & Doorstep Acceptance Policy',
                'category' => 'returns',
                'current_version' => '2.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Guidelines covering item-level partial rejection, the standard 24-hour claim window, admin-reviewed exceptions, and original-payment-route refunds.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc2->id,
                'version' => '2.0.0',
            ],
            [
                'title' => 'Return, Refund & Doorstep Acceptance Policy',
                'content' => $refundContent,
                'summary_of_changes' => 'Rebased returns and refund policy on VM retailer control, private supplier sourcing, original-channel refunds and provider reconciliation; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 3. PRIVACY & DATA PROTECTION POLICY
        $privacyContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — PRIVACY & DATA PROTECTION POLICY
**Effective Date:** October 21, 2026  
**Version:** 2.0.0  
**Regulatory Compliance:** Nigeria Data Protection Act (NDPA) & NDPR  

---

### 1. Overview
Victorious Market is committed to protecting the privacy, identity, and personal records of customers, private suppliers, logistics companies/riders and VM personnel. This policy outlines how personal data is collected, processed and safeguarded.

---

### 2. Information We Collect
* **Account Identity:** Full name, verified mobile telephone number, email address, and delivery coordinates.
* **Supplier verification data:** business/identity documents, source location, contact details and verified bank account details needed for authorized supplier payments.
* **Fulfillment Data:** GPS delivery coordinates, OTP handshake timestamps, and photographic evidence of package handovers stored in Cloudflare R2 media storage.
* **Financial Transaction Records:** Paystack transaction IDs and double-entry ledger entries. We do **not** store full credit/debit card numbers or CVV codes on our servers.

---

### 3. Purpose of Processing
Your data is processed strictly for:
* Order execution, geofenced LGA delivery routing, and OTP customer verification.
* Settlement disbursement and anti-money laundering (AML) compliance.
* Dispute arbitration and photographic handover proof.
* Automated disaster recovery backups with AES-256 encryption.

---

### 4. Data Sharing & Third Parties
We do not sell, rent, or monetize your personal data. Data is shared exclusively with:
* **Accredited Logistics Partners & Riders:** Necessary delivery address and phone number for package drop-off.
* **Paystack Payment Services:** For payment authorization and settlement bank transfers.
* **Law Enforcement & Regulatory Authorities:** Only upon receipt of valid judicial summons or compliance orders under Nigerian law.
MARKDOWN;

        $doc3 = LegalDocument::updateOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy and Data Protection Policy',
                'category' => 'privacy',
                'current_version' => '2.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'NDPA/NDPR compliant privacy policy covering KYC handling, Cloudflare R2 media encryption, and zero payment credential retention.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc3->id,
                'version' => '2.0.0',
            ],
            [
                'title' => 'Privacy and Data Protection Policy',
                'content' => $privacyContent,
                'summary_of_changes' => 'Version 2.0.0: aligned data categories and processing with retailer customers, private suppliers, approved logistics companies/riders, fulfilment evidence and financial records; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 4. PRIVATE SUPPLIER SOURCING & FULFILMENT AGREEMENT
        // Kept under the legacy slug for route compatibility; this is not a public seller/vendor agreement.
        $merchantContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — PRIVATE SUPPLIER SOURCING & FULFILMENT AGREEMENT
**Effective Date:** October 21, 2026  
**Version:** 2.0.0  
**Governing Territory:** Federal Republic of Nigeria  
**Contract Type:** Private supplier sourcing agreement (B2B)

---

### 1. Relationship and scope
Victorious MARKET ("VM") operates as the customer-facing retailer. The supplier is a private product source, not a public marketplace seller, and does not own the customer relationship. This agreement governs approved product offers, supply, collection, condition/warranty disclosure, confidentiality, fulfilment cooperation and evidence-based remedies. It does not make the supplier an independent seller on VM's public storefront.

### 2. Supplier verification and offers
The supplier must provide accurate identity/business details, contact information, source location, bank details for authorized supplier payments, and any required compliance information. Each offer must identify the product/model/variant, condition, included accessories, warranty/return terms, wholesale purchase price and validity, current availability, readiness estimate and collection location. Submission is not automatic approval or a promise to purchase.

### 3. Catalogue and customer-facing price
VM approves supplier offers, creates one canonical public listing for a new product or links an offer to an existing matching listing, and controls the public title, description, retail price, publication status and customer-facing terms. The supplier must not publish/alter VM listings, change VM's retail price, misrepresent condition or warranty, or create duplicate listings to bypass VM review. Supplier identity and wholesale price remain private except where disclosure is required for fulfilment, consumer rights or law.

### 4. Order-specific supply commitment
For each accepted order item, VM records the selected supplier offer, product/variant, quantity, agreed purchase-cost snapshot, relevant condition/warranty terms and source location. Later offer edits do not change an accepted order's cost or terms. If the supplier cannot fulfil, it must notify VM promptly. VM decides whether to use another approved offer, cancel/refund, or seek customer consent for a permitted substitution. No silent substitution or customer price increase is allowed.

### 5. Collection, delivery and customer pickup
The supplier makes goods available to VM or VM's authorized collector at the agreed location and records handover evidence. Customer delivery is coordinated by VM through an approved logistics company; every assigned rider must be active and linked to exactly one active logistics company. The supplier may not dispatch its own rider as VM's delivery carrier or mark customer delivery complete. Supplier-shop customer pickup is permitted only when VM authorizes it and the reservation pass, separate one-time customer pickup verification and in-shop photo are recorded. The supplier must not release goods on an unverified credential or mark pickup complete without the required evidence.

### 6. Payment and financial separation
Supplier payment eligibility begins only after verified successful customer delivery or confirmed customer pickup and required order/amount reconciliation. Eligibility is not an automatic transfer; payment still requires authorization, supplier terms, a verified destination account and idempotent provider confirmation/reconciliation. Supplier purchase cost is not a marketplace commission. The former 90/5/5 and 90/10 marketplace allocations, vendor payout thresholds and vendor debt rules do not apply to new retailer transactions. No new cashback accrual or logistics-company percentage is inferred by this agreement.

### 7. Product quality, condition and warranty
The supplier must disclose genuine product condition, known defects, included accessories, packaging and warranty limits. Claims of factory-sealed status or manufacturer warranty must be true and verifiable. Counterfeit goods, used goods represented as new, and materially misleading specifications are prohibited. VM retains authority over customer support and customer remedies, subject to applicable law and valid warranties.

### 8. Refunds, responsibility and recovery
VM remains responsible for approved customer refunds under its customer policy, even if a supplier has already been paid. A supplier recovery is a separate receivable and requires evidence of supplier responsibility, contractual recoverability and an authorized decision. A complaint alone does not establish a debt. Recovery must not delay a customer refund. Any offset must be lawful, contractually permitted, documented and open to challenge.

### 9. Confidentiality and no off-platform dealing
The supplier must protect customer and VM commercial information, including wholesale prices, and may contact a customer only through an authorized fulfilment/support process. The supplier must not solicit private payment, collect customer cash or private-account transfers, divert a VM order, or claim to be the retailer. All customer payments must use VM's authorized payment workflow.

### 10. Termination and governing law
VM may suspend an offer or supplier relationship for fraud, counterfeit goods, repeated preventable stockouts, unsafe conduct, privacy violations or off-platform payment requests, subject to applicable law and contractual process. Either party may terminate according to the agreed written terms, without extinguishing already accrued obligations or customer remedies. This agreement is governed by applicable laws of the Federal Republic of Nigeria.
MARKDOWN;

        $doc4 = LegalDocument::updateOrCreate(
            ['slug' => 'merchant-agreement'],
            [
                'title' => 'Private Supplier Sourcing & Fulfilment Agreement',
                'category' => 'supplier',
                'current_version' => '2.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Private supplier offer, product quality, sourcing, confidentiality, fulfilment evidence, supplier payment eligibility, and evidence-based recovery terms. Not a public seller agreement.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc4->id,
                'version' => '2.0.0',
            ],
            [
                'title' => 'Private Supplier Sourcing & Fulfilment Agreement',
                'content' => $merchantContent,
                'summary_of_changes' => 'Replaced the obsolete public-vendor marketplace agreement with the owner-confirmed private supplier/retailer relationship; legacy version retained for audit.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 5. LOGISTICS FLEET OPERATIONS & CHAIN-OF-CUSTODY SLA
        $logisticsContent = <<<'MARKDOWN'
# LEGACY — LOGISTICS FLEET OPERATIONS & CHAIN-OF-CUSTODY SLA (INACTIVE FOR NEW ORDERS)

> **Historical record only:** This legacy fleet/hub agreement and its payout allocations do not govern new retailer-model deliveries. VM coordinates delivery through approved logistics companies under separately approved company terms. Every rider assigned to new VM work must be active and linked to exactly one active logistics company. This legacy document is retained for audit only; its old fleet payout percentage, hub workflow and vendor-managed dispatch rules must not be applied to new orders.
**Effective Date:** October 21, 2026  
**Version:** 1.1.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  
**Contract Type:** Transport & Delivery Service Level Agreement (SLA)  

---

### 1. Purpose & Operating Principle
This Logistics Fleet Operations & Chain-of-Custody Service Level Agreement ("SLA") establishes the operational standards, custody transfer protocols, and financial terms binding accredited **Logistics Fleet Operators**, **Collection Point Hubs**, and **Delivery Riders** operating across the Victorious Market fulfillment network.

The core fulfillment covenant is:  
**Verifiable Chain of Custody. Zero Informal Transfers. Cryptographic Handshake at Every Step.**

---

### 2. Fleet Accreditation & Rider Vetting Obligations
* **Corporate Accreditation:** Logistics companies must maintain valid corporate registration, vehicle transit roadworthiness, and active goods-in-transit (GIT) insurance coverage.
* **Rider Identity Verification:** Fleet operators are vicariously responsible for all riders assigned to the platform. Operators must verify valid state driver/rider permits, conduct background checks, and enforce mandatory use of the official `riderAPP` terminal.
* **Device Token & Location Synchronization:** Riders must keep real-time GPS telemetry and device notification tokens active while on duty.

---

### 3. Cryptographic OTP Handshake & Chain of Custody Invariants
* **Two-Phase Handshake Transfer:** Physical custody of parcels transfers **only** upon cryptographic OTP verification:
  * **Phase 1 (Merchant Pickup):** The rider must obtain and verify the merchant's 6-digit Pickup OTP before taking possession of the package.
  * **Phase 2 (Doorstep Drop-off):** The rider must obtain and verify the customer's 6-digit Delivery OTP before handing the package to the customer.
* **Strict Liability for Bypassed OTP:** If a rider surrenders any parcel to a customer, intermediary, or third party without successfully verifying the OTP in the mobile terminal, **the Logistics Operator and rider are strictly, jointly, and severally liable for one hundred percent (100%) of the merchandise retail value**, payable immediately via wallet deduction.

---

### 4. Photographic Evidence & R2 Storage Protocol
* **Pickup Photo:** At merchant pickup, the rider must photograph the package seal, waybill label, and packaging condition.
* **Delivery Photo:** At doorstep handover or counter collection, photographic proof of the intact package must be uploaded to Cloudflare R2 media storage before the delivery run can be completed in the app.
* **Tamper Documentation:** Any pre-existing damage or compromised seal noticed during pickup must be documented and rejected before leaving the merchant's shop.

---

### 5. Cargo Tiers & Vehicle Classifications
Deliveries must strictly adhere to the system's package tier rules:
* **Standard Tier (Motorbike):** Parcels under 5kg (smartphones, fashion, cosmetics, accessories).
* **Fragile / Bulky Tier (Keke Tricycle / Van):** Television sets, large audio systems, desktop computers, kitchen appliances, and fragile glasswares must strictly be dispatched via approved Keke tricycle or covered van. Transporting bulky or fragile electronics on standard motorbikes is a breach of safety protocol and voids platform transit loss coverage.

---

### 6. Doorstep Partial Rejection & Failed Delivery Workflows
* **Item-Level Partial Rejections:** When a customer rejects an item at the doorstep, the rider must:
  * Select the rejected item in the `riderAPP` terminal.
  * Record the customer's stated reason and photograph the rejected item.
  * Place the item in a tamper-evident return bag and transport it to the nearest Victorious Market Collection Point Hub within four (4) hours.
* **Failed Delivery Protocol:**
  * The rider must make a minimum of three (3) phone call attempts spaced across at least 15 minutes.
  * If the customer remains unreachable, the rider logs "Delivery Attempt Failed" in the app with GPS verification.
  * The undelivered parcel must be returned to the Collection Point Hub on the same business day. Leaving undelivered parcels overnight in private residences is strictly prohibited.

---

### 7. Logistics Revenue & Wallet Settlement Structure
* **90% Fleet Allocation:** The Logistics Operator earns **ninety percent (90%)** of the approved directional LGA lane delivery fee for every successfully confirmed delivery.
* **10% Technology Fee:** Victorious Market retains 10% as the platform fulfillment and routing technology commission.
* **Wallet Payouts:** Earnings are credited to the fleet's platform wallet and are withdrawable via Paystack bank transfer, subject to the standard 48-hour bank cooldown security policy.

---

### 8. Collection Point Hub Operations & Custody Liability
* **Hub Custody:** Accredited Collection Point Hubs function as secure intermediate custody centers for in-shop customer pickups and return drop-offs.
* **Package Storage Safety:** Hub operators must maintain secure, dry, monitored storage for all held packages and verify customer/rider identity prior to releasing parcels.
* **Return Inspection Sign-Off:** Hub inspection officers must conduct formal condition checks on returned items before logging inspection passes in the administration system.

---

### 9. Enforcement & Governing Law
Failure to uphold dispatch SLAs, repeated failed delivery rates, unverified handovers, or parcel theft results in immediate fleet suspension, rider terminal revocation, and civil debt recovery under the laws of Akwa Ibom State, Federal Republic of Nigeria.
MARKDOWN;

        $doc5 = LegalDocument::updateOrCreate(
            ['slug' => 'logistics-service-agreement'],
            [
                'title' => 'LEGACY — Logistics Fleet Operations & Chain-of-Custody SLA (Inactive for New Orders)',
                'category' => 'logistics',
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => false,
                'summary' => 'Historical logistics-fleet policy retained for authorized audit only; inactive for new vendor-managed orders and new financial activity.'
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc5->id,
                'version' => '1.1.0',
            ],
            [
                'title' => 'Logistics Fleet Operations & Chain-of-Custody SLA',
                'content' => $logisticsContent,
                'summary_of_changes' => 'Initial operational SLA for logistics fleet companies, riders, and collection point hubs.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 6. ABOUT US & FOUNDATIONAL OPERATING CHARTER
        $aboutContent = <<<'MARKDOWN'
# ABOUT VICTORIOUS MARKET
## Building Nigeria's Trusted Online Market for Quality Products

**Brand Promise:** Your Trusted Online Market for Quality Products.  
**Operating Philosophy:** Trust First. Quality Always. Accountability at Every Stage.  
**Primary operating base:** Uyo, Akwa Ibom State, Federal Republic of Nigeria  
**Version:** 2.0.0

---

### 1. Who We Are
Victorious MARKET is a centrally managed online retailer. Customers buy from Victorious MARKET; private suppliers provide products behind the scenes. Suppliers are not independent public sellers on the VM storefront.

VM reviews supplier offers, controls public product listings and retail prices, accepts and verifies customer payments, coordinates fulfilment, handles customer support and refunds, and maintains the transaction records.

### 2. How We Build Trust
- **Controlled catalogue:** VM approves supplier offers, creates one canonical listing for a new product, and maps matching offers to an existing listing instead of creating duplicates.
- **Transparent products:** Product condition, specifications, included accessories and warranty limitations must be accurately described. No unsupported warranty or quality guarantee is implied.
- **Authorized payments:** Customers pay through VM's authorized payment workflow. Suppliers must not collect off-platform cash or private-account transfers. Doorstep COD is disabled at launch.
- **Evidence-based fulfilment:** Doorstep delivery requires server-verified customer OTP and a doorstep photo. Authorized supplier-shop pickup requires the reservation pass, separate one-time customer pickup verification and an in-shop photo.
- **Central delivery coordination:** VM coordinates delivery through approved logistics companies. Every rider assigned to new VM work must be active and linked to exactly one active logistics company.
- **Accountable remedies:** Refund approval, gateway submission, provider confirmation and reconciliation are separate states. A customer refund must not wait for supplier recovery.

### 3. Retail Accounting
Supplier purchase cost is a VM cost/payable, not a vendor commission. Customer receipts/revenue, supplier costs/payables, delivery charges, approved logistics-company charges/payables, gateway fees, discounts, refunds, any separately approved cashback and operating expenses must be recorded separately.

The former 90/5/5 merchandise and 90/10 delivery marketplace allocations are retired assumptions, not active retailer rules. New cashback accrual/funding and logistics-company charges require explicit approval; no percentage is inferred.

### 4. Service Area and Delivery
VM operates from Uyo and serves only destinations where product sourcing, current coverage, an approved logistics company, capacity and the customer-facing delivery charge are confirmed. No nationwide coverage, universal delivery time or unconfirmed fee is promised.

### 5. Supplier Relationship
Supplier offers are reviewed before use. VM snapshots the selected supplier offer, product/variant, agreed purchase cost, quantity and relevant terms on each accepted order item. Supplier offer changes do not silently change an accepted order. If a supplier cannot fulfil, VM follows an authorized sourcing, cancellation or refund workflow; substitutions require any necessary customer consent.
MARKDOWN;

        $doc6 = LegalDocument::updateOrCreate(
            ['slug' => 'about-us'],
            [
                'title' => 'About Victorious Market — Operating Charter',
                'category' => 'about',
                'current_version' => '2.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Retailer operating model, private supplier sourcing, central logistics, payment safety and customer trust commitments.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc6->id,
                'version' => '2.0.0',
            ],
            [
                'title' => 'About Victorious Market — Operating Charter',
                'content' => $aboutContent,
                'summary_of_changes' => 'Replaced public marketplace and 90/5/5 allocation descriptions with the retailer/private-supplier model and central delivery policy.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 7. ORDER CANCELLATION POLICY
        $cancellationContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — ORDER CANCELLATION & PRE-DISPATCH POLICY
**Effective Date:** October 21, 2026  
**Version:** 2.0.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. Overview
Victorious MARKET uses a controlled cancellation and refund workflow designed to balance customer flexibility with supplier preparation and fulfilment costs. Payments and refunds must be verified and reconciled; do not represent the service as regulated or segregated escrow unless that arrangement is independently verified.

---

### 2. Pre-Fulfilment Cancellation and Refund Review
* **Permitted window:** Ordinary cancellation is generally available before VM has committed sourcing/fulfilment work or the order enters a restricted state, subject to the persisted order state. Once the vendor accepts or prepares it, ordinary cancellation may be restricted, but this does not remove remedies for failed fulfilment, duplicate payment, wrong/defective goods, or rights that cannot lawfully be excluded.
* **Refund:** A verified refund request is processed under the applicable approval rule. Automatic approval is limited to a verified duplicate successful capture and a verified payment for an order cancelled before any supplier action. Other refunds require persisted authorised human approval before gateway dispatch. Refunds use the original verified Paystack route where supported; wallet-credit refunds are disabled at launch. Delivery-fee refundability is assessed separately. A submitted request is not a completed refund.
* **No invented fee:** Do not invent a cancellation penalty. The refundable amount and any delivery-fee reversal depend on what was actually captured, the reason, the applicable policy, and law.

---

### 3. In-Shop Pickup Reservations (24-Hour Expiry)
* **Reservation Window:** In-shop pickup reservations hold the selected stock for **twenty-four (24) hours**.
* **Payment and collection:** The customer reserves through Victorious MARKET and, where authorized, completes payment at the approved supplier shop through VM's authorized payment workflow. The supplier must not accept an off-platform cash/private-account payment and must not release the item until the platform verifies payment.
* **Automatic Expiration:** If the customer does not complete the platform-recorded payment and pickup within 24 hours, the reservation may expire and stock may return to active inventory only after confirming there is no successful or uncertain/in-flight payment. Reconcile uncertain payments before releasing stock.

---

### 4. Post-Dispatch In-Transit Policy
* **Ordinary cancellation after dispatch:** Once an order is out for delivery, an ordinary change-of-mind cancellation may be restricted because fulfilment costs may be committed. This does not bar a customer from rejecting wrong, damaged, defective, or non-conforming goods, reporting failed fulfilment, or seeking any remedy required by applicable law.
* **Doorstep Decision:** The customer must await the rider's arrival and exercise their **Doorstep Acceptance & Partial Rejection** rights at physical handover.
* **Repeated unjustified refusals:** Repeated refusals of conforming goods without a valid reason may be reviewed under a fair customer-account policy. Cash-on-delivery is disabled at launch, so no COD privilege is offered or suspended.

---

### 5. Supplier Stockout Cancellations
* **Customer remedy:** If an approved supplier offer cannot fulfil an accepted paid order item because of a stockout or inventory error, stop fulfilment, record the reason, notify the customer, and open a controlled refund/cancellation case. Automatic refund approval is permitted only where the transaction meets the narrow approved exception (including cancellation before any vendor action); otherwise an authorised admin must approve before gateway dispatch. Refund only the amount due under the captured transaction and applicable policy, assess delivery separately, and reconcile provider completion. Other order items and supplier offers must be reconciled independently; do not expose suppliers as public child-order sellers.
* **Supplier accountability:** Preventable stockouts are recorded against the supplier offer and reviewed by VM. VM may suspend an offer or supplier relationship under the applicable agreement.

---

### 6. Cashback Treatment on Cancelled Orders
* Any pending cashback associated with a cancelled or refunded order is reversed through an auditable ledger entry.
* If cashback from the order has already been spent and is recoverable under the policy, track the amount separately and offset it only against future cashback as that cashback matures. Never silently debit a cash balance or recreate cashback merely by refunding an order.
MARKDOWN;

        $doc7 = LegalDocument::updateOrCreate(
            ['slug' => 'cancellation-policy'],
            [
                'title' => 'Order Cancellation & Pre-Dispatch Policy',
                'category' => 'returns',
                'current_version' => '2.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Guidelines covering VM-controlled cancellation stages, the 24-hour in-shop reservation hold, controlled refunds and customer remedies.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc7->id,
                'version' => '2.0.0',
            ],
            [
                'title' => 'Order Cancellation & Pre-Dispatch Policy',
                'content' => $cancellationContent,
                'summary_of_changes' => 'Aligned cancellation and stockout remedies with VM retailer control and private supplier sourcing; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 8. FREQUENTLY ASKED QUESTIONS (FAQ) & KNOWLEDGE BASE
        $faqContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — FREQUENTLY ASKED QUESTIONS
**Effective Date:** October 21, 2026 &bull; **Version:** 2.0.0

---

### General

#### What is Victorious MARKET?
Victorious MARKET is a centrally managed online retailer operating from Uyo, Akwa Ibom State. Customers buy from VM. Suppliers are private product sources, not public marketplace sellers.

#### How does Victorious MARKET build trust?
VM approves supplier offers, controls public listings and retail prices, verifies customer payments, coordinates delivery through approved logistics companies, records pickup/delivery evidence, and handles customer support and refunds. These policies do not mean every application code path has already been migrated.

#### Where do you deliver?
VM serves only destinations where the product can be sourced and current logistics coverage, capacity and customer delivery charges are confirmed. No nationwide coverage or unconfirmed delivery time is promised.

### Shopping and Payments

#### How can I pay?
Approved launch methods are Paystack card and standard bank transfer through VM's authorized workflow. An authorized supplier-shop reservation/payment-at-location option may be used only when payment is processed and recorded through VM. Suppliers cannot collect off-platform cash or private-account transfers. Doorstep COD is disabled at launch.

#### Can I reserve an item and pay at a supplier shop?
Where the option is enabled for the order, VM creates an in-shop reservation that holds the selected stock for 24 hours. At the authorized supplier shop, the customer must use VM's approved payment workflow and complete the required reservation-pass, one-time customer pickup verification and in-shop photo process before the item is released. The supplier must not accept private payment.

#### What is the delivery charge?
The delivery charge is shown/confirmed for the order based on the destination and approved logistics arrangement. If coverage or the charge cannot be confirmed, VM must not promise delivery or invent a fee.

#### Does cashback apply to every purchase?
No new cashback accrual is promised by the former 5% marketplace allocation. New cashback can be enabled only after VM approves its funding, accounting, maturity and expiry rules. Any existing recorded cashback obligation is handled under its applicable terms.

### Delivery, Pickup and Returns

#### How is doorstep delivery verified?
Doorstep completion requires server-verified customer delivery OTP and a linked doorstep photo. Keep the OTP private and share it only for the intended handover. Evidence records the handover process; it does not prove product quality or waive consumer rights.

#### How is supplier-shop pickup verified?
Authorized supplier-shop pickup requires the reservation pass, a separate one-time customer pickup verification and an in-shop photo. Supplier readiness or possession of the item alone does not prove customer pickup.

#### How do I report a problem?
Report the issue through the order record or official customer-care channel, identify the affected item and attach available evidence. The standard operational claim window is 24 hours from confirmed delivery or pickup, subject to applicable consumer rights and valid warranty obligations. It is not a guarantee that every return is approved or that a gateway refund completes within 24 hours.

#### How are refunds handled?
An authorized refund decision, Paystack submission, provider processing and provider-confirmed completion are separate states. At launch, approved refunds use the verified original Paystack payment route where supported; wallet-credit refunds are disabled. Provider/bank processing times may vary.

### Supplier and Logistics Relationships

#### Can a supplier publish directly on the website?
No. Suppliers privately submit product offers for VM approval. VM creates one canonical listing for a new product or links an offer to an existing matching listing and controls the public retail price.

#### Who delivers my order?
VM coordinates delivery through an approved logistics company. Each assigned rider must be active and linked to exactly one active logistics company. Suppliers cannot dispatch their own rider as the VM delivery carrier or mark customer delivery complete.

#### Can suppliers collect money or change the price after I order?
No. Customer payment goes through VM's authorized workflow. The accepted order snapshots the product, price and selected supplier offer. VM does not silently increase the customer's price or substitute a product; any permitted substitution requires the appropriate authorization and customer consent.
MARKDOWN;

        $doc8 = LegalDocument::updateOrCreate(
            ['slug' => 'frequently-asked-questions'],
            [
                'title' => 'Frequently Asked Questions (FAQ) & Knowledge Base',
                'category' => 'faq',
                'current_version' => '2.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Customer answers covering VM retailer operations, private suppliers, authorized payment, central logistics, pickup and refunds.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc8->id,
                'version' => '2.0.0',
            ],
            [
                'title' => 'Frequently Asked Questions (FAQ) & Knowledge Base',
                'content' => $faqContent,
                'summary_of_changes' => 'Replaced obsolete public marketplace, vendor-managed delivery and inherited cashback descriptions with the VM retailer model.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 9. CONTACT & CUSTOMER CARE CENTER
        $contactContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — CONTACT & CUSTOMER CARE CENTER
**Customer Support & Official Contact**  
**Effective Date:** October 21, 2026 &bull; Version: 2.0.0  

---

### 1. Official Contact

For assistance with orders, payments, delivery, returns, or disputes, use the official Victorious MARKET website or contact the customer-care number below:

* **Phone / WhatsApp:** `09118949035`
* **Website:** https://victoriousmarket.com.ng

Include your order number and the affected VM order/item where relevant. Never send passwords, card security codes, or one-time passwords to customer support.

---

### 2. VM-Coordinated Delivery and Authorized Pickup

Victorious MARKET coordinates customer delivery through approved logistics companies. Every rider assigned to a new VM delivery must be active and linked to exactly one active logistics company; a supplier may not dispatch its own rider as the VM carrier or mark delivery complete. Authorized supplier-shop pickup is available only when VM approves it and the reservation pass, separate one-time customer pickup verification and in-shop photo are recorded. Contact VM support for return/inspection instructions; do not send goods to an unverified address.

---

### 3. Complaints and Disputes

Open a support request through the website/order record or use the official contact number above. Explain the issue, identify the affected VM order/item, and attach available supporting evidence. An authorised administrator reviews the case and records the decision. No fixed 24-hour dispute-resolution or refund-completion time is promised. Applicable consumer rights and valid warranty obligations remain in force.

---

### 4. Fraud and Off-Platform Payment Reports

Use the official website or phone number above to report suspected counterfeit goods, private payment requests, impersonation, or other marketplace abuse. Do not rely on unverified email addresses or unofficial physical collection-point listings.

MARKDOWN;

        $doc9 = LegalDocument::updateOrCreate(
            ['slug' => 'contact-and-help-center'],
            [
                'title' => 'Contact & Customer Care Center',
                'category' => 'support',
                'current_version' => '2.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Official customer contact channel, VM-coordinated logistics, authorized supplier-shop pickup and support escalation steps.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc9->id,
                'version' => '2.0.0',
            ],
            [
                'title' => 'Contact & Customer Care Center',
                'content' => $contactContent,
                'summary_of_changes' => 'Aligned fulfilment and pickup instructions with the central retailer model; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );
    }
}
