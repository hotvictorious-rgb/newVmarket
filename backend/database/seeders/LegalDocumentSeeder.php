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
        $effectiveDate = Carbon::create(2026, 10, 17);

        // 1. TERMS OF SERVICE
        $termsContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — TERMS AND CONDITIONS OF SERVICE
**Effective Date:** October 17, 2026  
**Version:** 1.0.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. Introduction & Operating Philosophy
Welcome to **VICTORIOUS MARKET** ("Victorious Market", "the Platform", "we", "us", or "our"). Victorious Market is an institutional digital commerce and fulfillment platform designed and managed on the fundamental philosophy:  
**Trust First. Quality Always. Accountability at Every Stage.**

By creating an account, browsing listings, placing orders, fulfilling deliveries, or registering as a merchant or logistics fleet provider, you agree to be bound by these Terms and Conditions. If you do not agree to these terms, you must discontinue use of the platform immediately.

---

### 2. The Governed Marketplace Model
Victorious Market is an intentionally **controlled, centrally governed marketplace**, not an open unmoderated classifieds board:
* **Central Transaction Authority:** All transactions, product listings, escrow funds, dispatch waybills, doorstep handshakes, customer returns, and vendor settlements are coordinated and enforced directly by Victorious Market.
* **Prohibition of Off-Platform Dealing:** Buyers, merchants, and riders are strictly prohibited from soliciting or transacting outside the platform (including via WhatsApp, personal phone calls, cash-on-delivery, or direct bank transfer). Any transaction negotiated or settled off-platform is void of all platform guarantees, escrow protections, return rights, and insurance coverage.

---

### 3. Merchant Accreditation & Catalog Integrity
* **Verification Prerequisite:** No merchant is authorized to list products or receive orders without completing identity verification, physical shop verification, and Know-Your-Business (KYB) checks.
* **Mandatory Condition Transparency:** All listings must explicitly state true product condition:
  * **Brand New:** Factory sealed, genuine OEM units with applicable manufacturer warranties.
  * **UK Used / Pre-Owned:** Thoroughly tested, functional pre-owned items with all cosmetic blemishes, battery health indicators, and missing accessories explicitly disclosed.
* **Zero Counterfeit Tolerance:** Presenting used goods as new or listing replica/counterfeit items results in immediate merchant suspension, listing deletion, forfeiture of platform privileges, and referral to commercial law enforcement.

---

### 4. Payments, Escrow & Revenue Allocation
* **Cryptographic Gateway Verification:** Payments are processed via Paystack. An order is marked as paid only after server-to-server cryptographic verification (HMAC-SHA512).
* **Controlled Escrow Custody:** 100% of customer funds are placed into segregated internal escrow accounts (`cash_in_escrow`). Merchant earnings become eligible for withdrawal only after physical handover, inspection, and expiration of the return window.
* **Merchandise Revenue Allocation:**
  * **90%** Vendor Net Payout (disbursable post-fulfillment).
  * **5%** Victorious Customer Cashback / Loyalty Allocation.
  * **5%** Victorious Market Platform Margin.
* **Logistics Allocation:** 90% retained by the accredited Logistics Provider, 10% platform fulfillment technology fee.

---

### 5. Delivery, Handover & Cryptographic Handshakes
* **Geofenced Local Delivery:** Operations commence within verified coverage zones in Akwa Ibom State (centered in Uyo LGA) and expand only across verified logistics corridors.
* **OTP Physical Verification:** Delivery riders must exchange a unique One-Time Password (OTP) with the customer at doorstep handover. Possession and risk of loss transfer to the customer only upon verified OTP confirmation.
* **Photographic Evidence:** Handover condition, packaging integrity, and waybill numbers are photographed and cryptographically linked to the order history in Cloudflare R2 storage.

---

### 6. Doorstep Acceptance & Partial Rejection Rights
* **Item-Level Decisions:** In multi-item deliveries, customers maintain the right at the moment of doorstep delivery to inspect items and reject non-conforming, damaged, or incorrect items individually while accepting the valid remainder.
* **Pro-Rata Settlement:** Only accepted items proceed to merchant settlement. Rejected items are sealed, documented by the rider, and returned to the hub for inspection.

---

### 7. Returns & 24-Hour Inspection Policy
* **24-Hour Window:** Eligible issues (defects, wrong items, undisclosed damage) must be reported within twenty-four (24) hours of confirmed delivery.
* **Collection Point Drop-Off:** Returned items must be surrendered at an accredited Victorious Market Collection Point / Hub for physical inspection before refund release.
* **Ineligible Returns:** Items damaged through user misuse, missing original serial numbers, or returned after the 24-hour window are ineligible for refund.

---

### 8. Refunds & Customer Wallet Credits
* **Administrative Authorization:** All cash refunds require Super Admin verification and inspection sign-off.
* **Disbursement Integrity:** Approved cash refunds are credited back to the original Paystack funding source. Instant doorstep credits applied to the customer wallet are reversed if full cash gateway refund is subsequently requested.

---

### 9. 5% Victorious Customer Cashback Program
* **Maturation Hold:** 5% merchandise cashback earned on purchases remains in a maturation hold for 365 days, activating only after delivery confirmation.
* **Non-Transferable:** Cashback is non-transferable, cannot be withdrawn to external bank accounts, and is automatically cancelled if the associated order is refunded or returned.

---

### 10. Platform Suspension & Financial Recovery
* **Suspension Powers:** Victorious Market reserves the right to suspend or permanently ban any merchant, rider, or customer engaging in abusive returns, chargeback fraud, off-platform solicitation, or harassment.
* **Debt Recovery:** If a refund or chargeback occurs after a vendor payout has disbursed, the platform reserves the legal right of offset against the merchant's future receivables or registered settlement account.

---

### 11. Governing Law & Arbitration
These Terms are governed by and construed in accordance with the laws of Akwa Ibom State and the Federal Republic of Nigeria. Any dispute arising out of or in connection with these Terms shall first be submitted to the Victorious Market Administrative Arbitration Desk before recourse to the High Court of Akwa Ibom State.
MARKDOWN;

        $doc1 = LegalDocument::updateOrCreate(
            ['slug' => 'terms-of-service'],
            [
                'title' => 'Terms and Conditions of Service',
                'category' => 'terms',
                'current_version' => '1.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Foundational operating contract governing marketplace transactions, escrow protections, merchant accreditation, delivery handshakes, and dispute resolution.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc1->id,
                'version' => '1.0.0',
            ],
            [
                'title' => 'Terms and Conditions of Service',
                'content' => $termsContent,
                'summary_of_changes' => 'Initial bank-grade charter adoption reflecting October 2026 operating blueprint.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 2. RETURN AND REFUND POLICY
        $refundContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — RETURN, REFUND & DOORSTEP ACCEPTANCE POLICY
**Effective Date:** October 17, 2026  
**Version:** 1.0.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. The 24-Hour Return Guarantee
At Victorious Market, customer protection is backed by real operational mechanisms. We operate an exact **twenty-four (24) hour return window** calculated from the timestamp of confirmed delivery OTP verification.

---

### 2. Doorstep Inspection & Partial Rejection
* **Inspect Before Releasing Rider:** When a delivery package arrives, the customer is invited to verify external package seals and contents in the presence of the delivery rider.
* **Item-Level Decisions:** If your order contains multiple items (e.g., a phone and a charger), you may accept the phone and reject the incorrect charger right at the doorstep.
* **Immediate Documentation:** The rider documents the partial rejection in the rider terminal app with photographic evidence and marks the item as returned.

---

### 3. Eligible Grounds for Return
Returns are accepted strictly under the following verifiable conditions:
1. **Wrong Item Delivered:** Product delivered differs in model, specifications, color, or variant from what was ordered.
2. **Defective or Inoperable:** Electronic or mechanical product fails to operate upon initial testing.
3. **Condition Misrepresentation:** A pre-owned / UK-used item exhibits undisclosed damage or defects not stated in the listing.
4. **Transit Damage:** Goods arrived visibly damaged during transit.

---

### 4. Non-Returnable Categories
The following products cannot be returned once unsealed, due to health, hygiene, and copyright protections:
* Personal grooming, health, and cosmetic goods with broken factory seals.
* Underwear, swimwear, and intimate apparel.
* Consumable goods, groceries, and perishables.
* Digital software licenses or activation codes once revealed.

---

### 5. Return Process & Collection Point Handover
1. **Submit Request:** File a return request through the Customer Portal within 24 hours of delivery, attaching clear photo/video evidence.
2. **Select Collection Point:** Choose your nearest Victorious Market Collection Point / Hub in Uyo or designated LGA hub.
3. **Physical Inspection:** Hand over the package to the hub inspection officer. The officer conducts a physical check against the order serial numbers.
4. **Arbitration & Approval:** Once inspection passes, the Super Admin approves the case for refund disbursement.

---

### 6. Refund Disbursement & Timelines
* **Gateway Reversals:** Approved card/bank payments made through Paystack are credited back to the original funding account within 3 to 7 business banking days.
* **Instant Wallet Credit Option:** Customers may opt for instant credit to their verified Victorious Market Wallet for zero-wait checkout on replacement items.
MARKDOWN;

        $doc2 = LegalDocument::updateOrCreate(
            ['slug' => 'return-and-refund-policy'],
            [
                'title' => 'Return, Refund & Doorstep Acceptance Policy',
                'category' => 'returns',
                'current_version' => '1.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Comprehensive guidelines covering item-level partial rejection, 24-hour return eligibility, collection point inspections, and refund disbursement.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc2->id,
                'version' => '1.0.0',
            ],
            [
                'title' => 'Return, Refund & Doorstep Acceptance Policy',
                'content' => $refundContent,
                'summary_of_changes' => 'Initial operational release with doorstep partial rejection and hub inspection clauses.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 3. PRIVACY & DATA PROTECTION POLICY
        $privacyContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — PRIVACY & DATA PROTECTION POLICY
**Effective Date:** October 17, 2026  
**Version:** 1.0.0  
**Regulatory Compliance:** Nigeria Data Protection Act (NDPA) & NDPR  

---

### 1. Overview
Victorious Market is committed to protecting the privacy, identity, and personal records of our customers, merchants, and logistics partners. This policy outlines how your data is collected, processed, and safeguarded.

---

### 2. Information We Collect
* **Account Identity:** Full name, verified mobile telephone number, email address, and delivery coordinates.
* **Merchant KYB Data:** CAC registration certificates, director identity proofs, physical shop addresses, and verified bank settlement account numbers.
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
                'current_version' => '1.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'NDPA/NDPR compliant privacy policy covering KYC handling, Cloudflare R2 media encryption, and zero payment credential retention.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc3->id,
                'version' => '1.0.0',
            ],
            [
                'title' => 'Privacy and Data Protection Policy',
                'content' => $privacyContent,
                'summary_of_changes' => 'Initial release in compliance with Nigeria Data Protection Act.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 4. MERCHANT ACCREDITATION & COMMERCIAL SELLER AGREEMENT
        $merchantContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — MERCHANT ACCREDITATION & COMMERCIAL SELLER AGREEMENT
**Effective Date:** October 17, 2026  
**Version:** 1.0.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  
**Contract Type:** Commercial Vendor Agreement (B2B)  

---

### 1. Preamble & Scope
This Merchant Accreditation & Commercial Seller Agreement ("Agreement") constitutes a legally binding commercial contract between **Victorious Market** ("the Platform", "we", "us") and the verified merchant entity ("Merchant", "Vendor", "you"). This Agreement governs your participation as an approved seller within our managed, centrally coordinated digital commerce ecosystem.

By completing vendor onboarding, submitting KYB documents, or listing products on Victorious Market, you agree to be bound by the covenants, warranties, and obligations set forth herein.

---

### 2. Merchant Vetting, KYB & Physical Shop Accreditation
* **Prerequisite to Listing:** Registration alone does not authorize commercial selling. Every vendor must submit verified identity documentation, Corporate Affairs Commission (CAC) registration proof where applicable, and evidence of a physical shop or warehousing location within an approved Local Government Area (commencing in Uyo LGA).
* **On-Site Inspection Right:** Victorious Market compliance inspectors reserve the irrevocable right to conduct physical inspections of the merchant's declared premises, inventory handling procedures, and storage conditions prior to granting or renewing accreditation.
* **Ongoing Accreditation:** Accreditation is not a permanent badge. The platform conducts continuous monitoring of fulfillment rates, cancellation frequencies, customer ratings, and return metrics.

---

### 3. Catalog Integrity & Mandatory Condition Disclosure
* **Absolute Warranty of Authenticity:** The Merchant explicitly warrants that every product listed as **Brand New** is a genuine, factory-sealed OEM unit possessing valid manufacturer warranties where applicable.
* **Pre-Owned / UK-Used Transparency:** For products listed as **UK Used / Pre-Owned**, the Merchant is legally obligated to disclose:
  * Cosmetic condition (Grade A, B, or C).
  * Battery health indicators and operational wear.
  * Any missing original accessories, manuals, or packaging.
* **Zero Counterfeit Tolerance:** Offering or selling replica, counterfeit, cloned, or illegally refurbished items represented as new constitutes a material breach of this Agreement. Violations trigger immediate shop delisting, forfeiture of marketplace privileges, freezing of withdrawable balances, and criminal referral to the Federal Competition and Consumer Protection Commission (FCCPC) and the Standards Organisation of Nigeria (SON).

---

### 4. Order Fulfillment & 2-Hour Readiness SLA
* **Inventory Synchronization:** The Merchant must maintain real-time inventory records. Confirming an order that cannot be fulfilled due to preventable stockouts attracts administrative penalties.
* **2-Hour Handover SLA:** Upon receiving automated order notification, the Merchant must prepare, package, and mark the order as "Ready for Handover" within two (2) operating hours.
* **Handover Verification:** The Merchant must demand and verify the authorized courier's OTP before surrendering custody of packages to any logistics rider or counter representative.

---

### 5. Financial Settlement, Escrow Custody & 90% Allocation
* **90% Net Merchandise Allocation:** For every fulfilled, non-returned transaction, the Merchant earns exactly **ninety percent (90%)** of the product merchandise subtotal.
* **Escrow Holding Window:** Customer payments are held under internal escrow custody (`cash_in_escrow`). Merchant earnings become eligible for withdrawal exactly twenty-four (24) hours following confirmed doorstep delivery OTP or counter inspection, provided no customer return or dispute has been lodged.
* **48-Hour Bank Cooldown Security:** Any change to the Merchant's registered settlement bank account invokes a mandatory **forty-eight (48) hour withdrawal freeze** to protect against unauthorized account takeover.

---

### 6. Doorstep Partial Rejection & Customer Return Obligations
* **Item-Level Rejection:** In multi-item deliveries, the Merchant agrees to accept item-level partial rejections where a customer accepts conforming goods and rejects defective or non-conforming items at doorstep delivery.
* **Collection Point Drop-Off:** Rejected items are returned to a designated Collection Point Hub. The Merchant may inspect the returned items at the hub within forty-eight (48) hours of notification.
* **Replacement / Restock:** Conforming returned items may be retrieved by the merchant; non-conforming items must be removed from the catalog.

---

### 7. Indemnity & Commercial Product Liability
* **Merchant Indemnity:** The Merchant agrees to defend, indemnify, and hold harmless Victorious Market, its directors, officers, employees, and agents against any claims, losses, liabilities, regulatory fines, legal costs, or damages arising out of:
  * Product defects, personal injury, or property damage caused by products sold by the Merchant.
  * Intellectual property infringement, counterfeit goods, or unauthorized brand use.
  * Deceptive, false, or misleading listing descriptions.

---

### 8. Debt Recovery, Deficit Balances & Right of Offset
* **Post-Payout Liabilities:** If an order is refunded, cancelled, or chargebacked *after* settlement funds have already been withdrawn by the Merchant, the Merchant incurs an immediate, legally enforceable debt liability to Victorious Market.
* **Contractual Set-Off:** Victorious Market reserves the express contractual right to offset and deduct such deficit balances against any current or future receivables, held payouts, or subsequent sales generated across any of the Merchant's shops or branches.

---

### 9. Prohibition of Off-Platform Dealing & WhatsApp Bypass
* **Non-Circumvention:** Merchants are strictly forbidden from contacting platform customers privately (via personal WhatsApp, phone calls, or flyers inserted in packages) to solicit direct off-platform sales, cash-on-delivery, or private bank transfers.
* **Enforcement:** Any attempt to bypass the platform's escrow and governance mechanisms results in immediate shop termination, permanent merchant blacklisting, and liquidated damages equal to the value of the diverted transactions.

---

### 10. Term, Suspension & Dispute Arbitration
* **Termination for Convenience:** Either party may terminate with 30 days written notice, subject to full settlement of existing orders and outstanding liabilities.
* **Immediate Termination for Cause:** Victorious Market may suspend or terminate this Agreement instantly upon fraud, counterfeit sales, abusive conduct, or failure of KYB standards.
* **Governing Law:** This Agreement is governed by the laws of Akwa Ibom State and the Federal Republic of Nigeria, with exclusive arbitration jurisdiction situated in Uyo, Akwa Ibom State.
MARKDOWN;

        $doc4 = LegalDocument::updateOrCreate(
            ['slug' => 'merchant-agreement'],
            [
                'title' => 'Merchant Accreditation & Commercial Seller Agreement',
                'category' => 'merchant',
                'current_version' => '1.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Legally binding commercial covenants governing vendor accreditation, catalog condition honesty, 90% escrow payouts, anti-counterfeit indemnity, and debt recovery set-off.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc4->id,
                'version' => '1.0.0',
            ],
            [
                'title' => 'Merchant Accreditation & Commercial Seller Agreement',
                'content' => $merchantContent,
                'summary_of_changes' => 'Initial commercial accreditation agreement for onboarded vendors.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 5. LOGISTICS FLEET OPERATIONS & CHAIN-OF-CUSTODY SLA
        $logisticsContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — LOGISTICS FLEET OPERATIONS & CHAIN-OF-CUSTODY SLA
**Effective Date:** October 17, 2026  
**Version:** 1.0.0  
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
                'title' => 'Logistics Fleet Operations & Chain-of-Custody SLA',
                'category' => 'logistics',
                'current_version' => '1.0.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Operational Service Level Agreement binding logistics fleet operators and riders to strict OTP custody transfer, photographic proof, 90/10 payout reconciliation, and package loss liability.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc5->id,
                'version' => '1.0.0',
            ],
            [
                'title' => 'Logistics Fleet Operations & Chain-of-Custody SLA',
                'content' => $logisticsContent,
                'summary_of_changes' => 'Initial operational SLA for logistics fleet companies, riders, and collection point hubs.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );
    }
}
