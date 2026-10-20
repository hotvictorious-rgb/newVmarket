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
        $effectiveDate = Carbon::create(2026, 10, 20);

        // 1. TERMS OF SERVICE
        $termsContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — TERMS AND CONDITIONS OF SERVICE
**Effective Date:** October 20, 2026  
**Version:** 1.1.0  
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
* **Prohibition of Off-Platform Dealing:** Buyers, merchants, and riders must not bypass Victorious MARKET for marketplace transactions. The only permitted customer payment paths are (1) pay before delivery through Victorious MARKET checkout, or (2) reserve an in-shop pickup through Victorious MARKET and pay at the approved vendor shop through Victorious MARKET's authorised payment workflow. Vendors must not collect cash or private-account transfers outside the platform. In-shop reserve-and-pay is counter pickup only, not doorstep COD.

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
* **Delivery-fee Allocation:** 90% of the delivery-fee allocation belongs to the owning vendor and 10% to Victorious MARKET. Vendors separately arrange and pay their own riders outside the platform's financial systems; new orders create no platform rider or logistics-company payable.

---

### 5. Delivery, Handover & Cryptographic Handshakes
* **Geofenced Local Delivery:** Operations commence within verified coverage zones in Akwa Ibom State (centered in Uyo LGA) and expand only across verified logistics corridors.
* **Doorstep handover:** The customer's delivery OTP must be securely verified and a doorstep delivery photo recorded before completion.
* **In-shop pickup:** The customer must present the `RES-XXXXXX` reservation pickup pass/secure pickup verification, and the vendor must record an in-shop pickup photo before completion. Reservation verification does not replace the photo.
* **Evidence limits:** GPS, customer signature, separate rider-pickup photo and hub inspection photo are not mandatory. OTP/pass verification confirms the handover process; neither OTP nor photo proves product quality or removes applicable consumer rights.

---

### 6. Doorstep Acceptance & Partial Rejection Rights
* **Item-Level Decisions:** In multi-item deliveries, customers maintain the right at the moment of doorstep delivery to inspect items and reject non-conforming, damaged, or incorrect items individually while accepting the valid remainder.
* **Vendor return handling:** A rejected item is documented and returned to its owning vendor. The vendor may offer a suitable replacement/redelivery only with customer agreement. If the customer declines, no suitable replacement is available, or a refund/remedy is otherwise required, an authorised admin reviews the case. No mandatory platform hub inspection is imposed.

---

### 7. Returns & 24-Hour Inspection Policy
* **24-Hour Standard Window:** Eligible ordinary issues should be reported within twenty-four (24) hours of confirmed doorstep delivery or in-shop pickup. This operational window does not waive statutory rights or valid warranty obligations; authorised admins may review legitimate late requests.
* **Return handling:** Support/admin may arrange return to the owning vendor for inspection. A platform collection-point/hub handover is not mandatory. Applicable law and valid warranty obligations prevail.
* **Late requests and legal rights:** The standard operational request window is 24 hours from confirmed delivery or in-shop pickup. This is not a blanket waiver of statutory rights or warranties; an authorised admin may review legitimate late requests and must record the decision.

---

### 8. Refunds & Customer Wallet Credits
* **Administrative Authorization:** An authorised admin reviews and approves ordinary refunds, with evidence, reason, amount, approver and timestamps recorded. Applicable law and valid warranty obligations prevail.
* **Disbursement Integrity:** At launch, approved refunds are initiated through the verified original Paystack payment route where supported. Wallet-credit refunds and off-platform refunds are disabled. A submitted refund is not complete until provider confirmation/reconciliation.

---

### 9. 5% Victorious Customer Cashback Program
* **Maturity and validity:** Eligible 5% merchandise cashback matures after confirmed fulfilment, the standard 24-hour customer claim window, and a separate additional 24-hour maturity buffer, provided no blocking case remains. Its 365-day validity period starts at actual `matures_at`, not at checkout or delivery.
* **Non-Transferable:** Cashback is non-transferable, cannot be withdrawn to external bank accounts, and is automatically cancelled if the associated order is refunded or returned.

---

### 10. Platform Suspension & Financial Recovery
* **Suspension Powers:** Victorious Market reserves the right to suspend or permanently ban any merchant, rider, or customer engaging in abusive returns, chargeback fraud, off-platform solicitation, or harassment.
* **Debt recovery:** Vendor liability must be supported by a documented, authorised decision; a complaint alone is not a debt. A confirmed debt may be repaid by verified bank transfer or offset against held/future vendor earnings only with authorised admin approval and contractual basis. Record recovery as a separate receivable; do not pretend a completed transfer has been reversed.

---

### 11. Governing Law & Arbitration
These Terms are governed by and construed in accordance with the laws of Akwa Ibom State and the Federal Republic of Nigeria. Any dispute arising out of or in connection with these Terms shall first be submitted to the Victorious Market Administrative Arbitration Desk before recourse to the High Court of Akwa Ibom State.
MARKDOWN;

        $doc1 = LegalDocument::updateOrCreate(
            ['slug' => 'terms-of-service'],
            [
                'title' => 'Terms and Conditions of Service',
                'category' => 'terms',
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Foundational operating contract governing marketplace transactions, escrow protections, merchant accreditation, delivery handshakes, and dispute resolution.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc1->id,
                'version' => '1.1.0',
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
**Effective Date:** October 20, 2026  
**Version:** 1.1.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. The 24-Hour Return Guarantee
The standard operational request window for eligible ordinary returns/refunds is **twenty-four (24) hours** from confirmed doorstep delivery or confirmed in-shop pickup. This is not a blanket waiver of applicable consumer rights or valid warranty obligations; authorised admins may review legitimate late requests.

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
* **Refund destination:** At launch, approved refunds are initiated through the verified original Paystack payment route where supported. Wallet-credit refunds and off-platform refunds are disabled.
MARKDOWN;

        $doc2 = LegalDocument::updateOrCreate(
            ['slug' => 'return-and-refund-policy'],
            [
                'title' => 'Return, Refund & Doorstep Acceptance Policy',
                'category' => 'returns',
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Comprehensive guidelines covering item-level partial rejection, 24-hour return eligibility, collection point inspections, and refund disbursement.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc2->id,
                'version' => '1.1.0',
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
**Effective Date:** October 20, 2026  
**Version:** 1.1.0  
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
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'NDPA/NDPR compliant privacy policy covering KYC handling, Cloudflare R2 media encryption, and zero payment credential retention.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc3->id,
                'version' => '1.1.0',
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
**Effective Date:** October 20, 2026  
**Version:** 1.1.0  
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
* **Vendor earnings eligibility:** Vendor earnings become eligible 24 hours after confirmed doorstep delivery or confirmed in-shop pickup, provided no applicable return/dispute/payment hold or security/reconciliation check blocks them. Eligibility is not automatic transfer.
* **48-Hour Bank Cooldown Security:** Any change to the Merchant's registered settlement bank account invokes a mandatory **forty-eight (48) hour withdrawal freeze** to protect against unauthorized account takeover.
* **Vendor payout batch:** Each payout batch has a ₦5,000 minimum and ₦500,000 maximum. The platform calculates strict FIFO from the oldest eligible, unreserved whole earning records, adding each full record while the total remains at or below ₦500,000. Stop before a record that would exceed the cap; never split or skip. If the oldest record alone exceeds the cap, flag it for authorised finance resolution. Every request requires admin approval before transfer.
* **Payout reservation and fees:** Reserve the exact selected whole earning records atomically. One active request per vendor is allowed while pending, approved-unsent, in-flight or uncertain. An approved request is not completed until authoritative provider success and reconciliation. Reconcile timeouts before retry. Victorious MARKET bears payout transfer fees/stamp duty as platform expense; the vendor receives the exact approved amount.

---

### 6. Doorstep Partial Rejection & Customer Return Obligations
* **Item-Level Rejection:** In multi-item deliveries, the Merchant agrees to accept item-level partial rejections where a customer accepts conforming goods and rejects defective or non-conforming items at doorstep delivery.
* **Vendor return handling:** Rejected items return to the owning vendor. The vendor may offer a suitable replacement/redelivery only with customer agreement. No platform hub handover is mandatory.
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
* **Authorised recovery:** A confirmed vendor debt may be repaid by verified bank transfer or offset against held/future vendor earnings only with authorised admin approval and contractual basis. A complaint alone is not a confirmed debt; record the debt/recovery separately and allow a vendor challenge.

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
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Legally binding commercial covenants governing vendor accreditation, catalog condition honesty, 90% escrow payouts, anti-counterfeit indemnity, and debt recovery set-off.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc4->id,
                'version' => '1.1.0',
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
**Effective Date:** October 20, 2026  
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
                'title' => 'Logistics Fleet Operations & Chain-of-Custody SLA',
                'category' => 'logistics',
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Operational Service Level Agreement binding logistics fleet operators and riders to strict OTP custody transfer, photographic proof, 90/10 payout reconciliation, and package loss liability.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
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
**Launch Headquarter:** Uyo, Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. Executive Overview
**VICTORIOUS MARKET** is an institutional digital commerce platform designed to make buying and selling safer, more structured, more transparent, and reliably accountable in Nigeria.

The vision is to establish an online marketplace where consumers discover quality products from verified merchants, complete secure payments, receive products through accountable logistics channels, and access fair resolutions when transactions do not go as expected.

Unlike an unrestricted classifieds board where buyers and sellers negotiate privately and send money through unmonitored bank accounts, VICTORIOUS MARKET is a **managed, centrally governed marketplace**. The platform coordinates merchant verification, catalog governance, escrow custody, directional logistics, delivery OTP handshakes, customer returns, and vendor settlements.

---

### 2. The Problem We Intend to Solve
Many Nigerian consumers are hesitant to purchase products online, especially from unfamiliar merchants. Common concerns include:
* Paying for products that are never delivered.
* Receiving inferior products different from advertised photos.
* Sellers who block buyers on WhatsApp after receiving bank transfers.
* Hidden or unexpected delivery charges.
* Unclear return and refund procedures.
* Total lack of verifiable evidence when disputes arise.

Merchants also experience significant challenges, including fake orders, customers refusing legitimate deliveries, delayed settlements, stock-management issues, and unreliable delivery partners.

Victorious Market solves these problems through **one coordinated system** where trust does not depend solely on a seller's verbal promise, but is enforced by verification, transparent procedures, financial controls, cryptographic evidence, and enforceable accountability.

---

### 3. The Controlled Marketplace Model
Participation in Victorious Market is subject to verification and accreditation:
* **Vetted Merchants:** Merchants register a business profile, provide CAC credentials where applicable, and pass physical shop inspections.
* **Governed Transactions:** Customers see approved business identities, but private phone numbers and off-platform dealing are protected.
* **Suspension Powers:** Any merchant or courier violating platform standards is suspended, protecting customer funds and overall market integrity.

---

### 4. Financial Integrity: The 90/5/5 Allocation
* **90% Merchant Settlement:** Legitimate merchants receive 90% net earnings upon confirmed delivery.
* **5% Customer Cashback:** Eligible merchandise cashback matures after fulfilment, the 24-hour standard customer claim window, and a separate additional 24-hour buffer when no blocking case remains. Its 365-day validity period starts at actual maturity, not checkout or delivery.
* **5% Platform Sustainability Margin:** Powering platform engineering, customer support, and dispute arbitration.
* **Logistics Split:** 90% retained by the accredited Logistics Fleet, 10% platform routing fee.

---

### 5. Geographic Discipline & Expansion
Our commercial beachhead is centered in **Uyo, Akwa Ibom State**, expanding across verified transit corridors into Ikot Ekpene, Eket, and subsequent regional centers. Rather than promising unfulfilled nationwide deliveries, Victorious Market expands through verified physical collection hubs, trusted local couriers, and guaranteed fulfillment corridors.
MARKDOWN;

        $doc6 = LegalDocument::updateOrCreate(
            ['slug' => 'about-us'],
            [
                'title' => 'About Victorious Market — Operating Charter',
                'category' => 'about',
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Foundational operating philosophy, managed marketplace vision, geographic discipline in Akwa Ibom State, and core brand promise.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc6->id,
                'version' => '1.1.0',
            ],
            [
                'title' => 'About Victorious Market — Operating Charter',
                'content' => $aboutContent,
                'summary_of_changes' => 'Initial publication of foundational operating charter.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 7. ORDER CANCELLATION POLICY
        $cancellationContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — ORDER CANCELLATION & PRE-DISPATCH POLICY
**Effective Date:** October 20, 2026  
**Version:** 1.1.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. Overview
Victorious Market operates an escrow-protected, deterministic cancellation workflow designed to balance customer flexibility with merchant preparation and logistics costs.

---

### 2. Pre-Dispatch Customer Cancellation (100% Instant Refund)
* **Permitted Window:** A customer may cancel an order for any reason **prior to merchant packaging and rider dispatch** (while order status is `pending` or `confirmed`).
* **Refund:** A verified, approved cancellation refund is initiated through the original verified Paystack payment route where supported. Wallet-credit refunds are disabled. The approved amount and delivery-fee treatment depend on the actual transaction, reason, applicable terms and law; provider completion must be reconciled.
* **Zero Cancellation Fees:** No penalty or administrative surcharge is deducted for pre-dispatch cancellations.

---

### 3. In-Shop Pickup Reservations (24-Hour Expiry)
* **Reservation Window:** In-shop pickup reservations hold the selected stock for **twenty-four (24) hours**.
* **Payment and collection:** The customer reserves through Victorious MARKET and pays at the approved vendor shop through Victorious MARKET's authorised payment workflow. The vendor must not accept an off-platform cash/private-account payment and must not release the item until the platform verifies payment.
* **Automatic Expiration:** If the customer does not complete the platform-recorded payment and pickup within 24 hours, the reservation may expire and stock may return to active inventory only after confirming there is no successful or uncertain/in-flight payment. Reconcile uncertain payments before releasing stock.

---

### 4. Post-Dispatch In-Transit Policy
* **No Mid-Transit Cancellations:** Once an order status progresses to `out_for_delivery` and is with the courier rider, the order cannot be cancelled in transit because logistics resources and transit costs are already committed.
* **Doorstep Decision:** The customer must await the rider's arrival and exercise their **Doorstep Acceptance & Partial Rejection** rights at physical handover.
* **Refusal Without Cause:** Repeated arbitrary refusals of conforming orders at doorstep delivery without valid defect grounds may lead to customer account review and suspension of cash-on-delivery or delivery privileges.

---

### 5. Merchant Stockout Cancellations
* **Immediate Customer Remedy:** If an accredited merchant cannot fulfill an order due to sudden stockout or inventory discrepancy, the platform automatically cancels the order, initiates an immediate 100% refund to the customer, and notifies the customer via SMS/Email.
* **Merchant Accountability:** Preventable merchant stockouts are logged against the vendor's fulfillment rating. Repeated stockouts trigger temporary catalog delisting.

---

### 6. Cashback Treatment on Cancelled Orders
* Any 5% cashback reward queued for maturation on an order is instantly voided and revoked if the order is cancelled.
* Any previously earned cashback applied as a discount on a cancelled order is refunded back to the customer's cashback ledger balance.
MARKDOWN;

        $doc7 = LegalDocument::updateOrCreate(
            ['slug' => 'cancellation-policy'],
            [
                'title' => 'Order Cancellation & Pre-Dispatch Policy',
                'category' => 'returns',
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Comprehensive guidelines covering pre-dispatch 100% refunds, in-shop 24-hour reservation expiries, and mid-transit doorstep rules.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc7->id,
                'version' => '1.1.0',
            ],
            [
                'title' => 'Order Cancellation & Pre-Dispatch Policy',
                'content' => $cancellationContent,
                'summary_of_changes' => 'Updated payment paths, reservation handling, refund rules, and owner-confirmed marketplace policy.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 8. FREQUENTLY ASKED QUESTIONS (FAQ) & KNOWLEDGE BASE
        $faqContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — FREQUENTLY ASKED QUESTIONS (FAQ)
**Knowledge Base & Operational Answers**  
**Effective Date:** October 20, 2026 &bull; Version: 1.0.0  

---

### General & Platform Trust

#### What is Victorious Market?
Victorious Market is a managed, centrally governed online marketplace and fulfillment network in Nigeria. We connect customers with accredited plaza merchants, provide escrow payment protection, ensure verified courier deliveries with OTP handshakes, and guarantee a 24-hour return window.

#### How is Victorious Market different from open classifieds or social media sellers?
On open social media platforms (like Instagram or WhatsApp), you send money to unknown sellers with zero delivery guarantee and no return recourse. On Victorious Market, **we govern the transaction**:
* Merchants are vetted and physically inspected.
* Your payment is held in safe internal escrow until you verify the package.
* Riders must exchange an OTP before delivery is valid.
* You can partially reject defective items at your doorstep.

#### Where are your services currently available?
We are headquartered in **Uyo, Akwa Ibom State**. Our delivery coverage spans Uyo Local Government Area and primary transit corridors across Akwa Ibom State, with planned expansion into neighboring commercial centers.

---

### Shopping, Payments & Cashback

#### How does Escrow Protection work?
When you pay for an order, 100% of your funds are placed in our secured internal escrow vault (`cash_in_escrow`). The seller **cannot** withdraw this money until twenty-four (24) hours after you have physically received and inspected the item via your delivery OTP.

#### Can I reserve an item and pay at the vendor shop?
Yes. Create an in-shop pickup reservation through Victorious MARKET. The reservation holds the item for 24 hours. At the approved vendor shop, inspect the item and complete payment through Victorious MARKET's authorised payment workflow before the item is released. Vendors may not collect cash or private-account transfers outside the platform.

#### What payment methods do you accept?
Launch payment rails are Paystack card and standard bank transfer through Victorious MARKET. Customers may pay before delivery or reserve in-shop pickup and pay at the vendor shop through the platform's authorised workflow. USSD, DVA, mobile money and other rails are not enabled unless separately approved.

#### What is the 5% Customer Cashback Program?
On every non-returned merchandise order, you automatically earn 5% cashback credited to your account. This matures into spendable customer balance and can be redeemed on future platform purchases.

---

### Delivery, Logistics & Doorstep Handover

#### How fast is delivery within Uyo?
Standard intra-Uyo deliveries arrive within 1 to 3 hours from merchant handover. Directional LGA deliveries are completed on the same business day along verified corridors.

#### What is the Delivery OTP Handshake?
When your order is dispatched, a secret 6-digit One-Time Password (OTP) is sent to your phone and email. When the rider arrives, you inspect the parcel and only give the rider the OTP once you are satisfied. Possession of the package legally transfers only when the rider enters your valid OTP.

#### What is "Doorstep Partial Rejection"?
If you order 3 items (e.g. a phone, a case, and a charger) and the charger is wrong or damaged, you do **not** have to reject the whole package! You can accept the phone and case, and reject only the charger right at your doorstep. You will be refunded for the rejected item while keeping the rest.

#### What vehicles are used for fragile goods?
Electronics such as television sets, desktop computers, and large home appliances are strictly transported using covered Keke tricycles or logistics vans with padded securing, never on motorbikes.

---

### Returns, Inspections & Refunds

#### How long do I have to report a problem?
The standard operational window for eligible ordinary claims is **twenty-four (24) hours** from confirmed delivery or in-shop pickup. Applicable consumer rights and valid warranty obligations are not waived; an authorised admin may review legitimate late requests.

#### Where do I drop off a returned product?
Support/admin will explain the authorised return or inspection process for your case. A platform collection-point or hub drop-off is not mandatory; the item may be returned to the owning vendor for review where appropriate.

#### When will I receive my refund?
After an authorised admin approves the remedy, the refund is initiated through the verified original Paystack payment route where supported. Provider/bank processing times may vary, and submission is not confirmation of completion. You cannot choose wallet credit at launch.
* **Original payment route:** At launch, approved refunds are initiated through the verified original Paystack payment route where supported. Wallet-credit refunds are not enabled. Actual provider/bank processing times may vary; submission is not confirmation of completion.

---

### Merchant & Logistics Operations

#### How can a merchant sell on Victorious Market?
Prospective merchants must register via the Vendor Portal, provide business registration documents, and pass a physical shop inspection conducted by our compliance team.

#### What are the product condition rules?
Merchants must explicitly disclose whether goods are **Brand New** (factory-sealed OEM with warranty) or **UK Used / Pre-Owned** (tested, with disclosed battery health and cosmetic grade). Counterfeits are strictly banned.
MARKDOWN;

        $doc8 = LegalDocument::updateOrCreate(
            ['slug' => 'frequently-asked-questions'],
            [
                'title' => 'Frequently Asked Questions (FAQ) & Knowledge Base',
                'category' => 'faq',
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Comprehensive answers covering escrow protection, in-shop pickup, doorstep partial rejection, 24-hour returns, and merchant accreditation.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc8->id,
                'version' => '1.1.0',
            ],
            [
                'title' => 'Frequently Asked Questions (FAQ) & Knowledge Base',
                'content' => $faqContent,
                'summary_of_changes' => 'Initial operational knowledge base release.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 9. CONTACT & CUSTOMER CARE CENTER
        $contactContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — CONTACT & CUSTOMER CARE CENTER
**Support Desk & Operational Hubs**  
**Effective Date:** October 20, 2026 &bull; Version: 1.0.0  

---

### 1. Customer Care & Hotlines
Our dedicated customer care team is available to assist shoppers, merchants, and riders with order inquiries, delivery tracking, and dispute escalations:

* **Customer Care Telephone:** `0800-VMARKET` / `+234 802 000 0000`
* **Operating Hours:** Monday – Saturday, 8:00 AM – 6:00 PM (West Africa Time)
* **General Support Email:** `support@victoriousmarket.ng`
* **Disputes & Legal Inquiries:** `disputes@victoriousmarket.ng` / `compliance@victoriousmarket.ng`

> **Urgent Handover Tip:** When calling regarding an active delivery run or rider handshake, please have your **Order Number** (e.g. `ORD-2026-XXXX`) ready for instant verification.

---

### 2. Operational Headquarters & Physical Hub
* **Physical Address:** Victorious Market Central Operations Tower, Uyo, Akwa Ibom State, Nigeria.
* **In-Person Business Desk:** Monday – Friday, 9:00 AM – 5:00 PM.

---

### 3. Collection Points & Hub Drop-Off Network
Victorious Market Collection Points function as secure holding hubs for in-shop customer pickups and return drop-offs across Akwa Ibom:
* **Uyo Central Hub:** Plaza Commercial Axis, Uyo LGA.
* **Corridor Hubs:** Expanding along designated logistics lanes in Ikot Ekpene and Eket corridors.
* **Hub Operating Hours:** Monday – Saturday, 8:30 AM – 5:30 PM.

---

### 4. Administrative Arbitration & Dispute Escalation
If you have an unresolved dispute with a merchant regarding product condition or a rider regarding delivery timing:
1. Open your **Customer Portal &rarr; Orders &rarr; Request Assistance / Dispute**.
2. Upload clear photographic or video evidence.
3. Our Administrative Arbitration Desk reviews the order history, GPS coordinates, and inspection findings to issue an explainable, binding ruling within twenty-four (24) hours.

---

### 5. Whistleblower & Anti-Fraud Reporting
Victorious Market enforces strict zero tolerance for commercial malpractice:
* To report counterfeit products, stolen goods, off-platform solicitation, or bribery attempts by any staff, merchant, or rider, email: `whistleblower@victoriousmarket.ng`.
* All whistleblower reports are treated with strict confidentiality.
MARKDOWN;

        $doc9 = LegalDocument::updateOrCreate(
            ['slug' => 'contact-and-help-center'],
            [
                'title' => 'Contact & Customer Care Center',
                'category' => 'support',
                'current_version' => '1.1.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Official hotlines, Uyo operational headquarters address, collection point hubs, administrative dispute escalation, and whistleblower reporting.',
            ]
        );

        LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $doc9->id,
                'version' => '1.1.0',
            ],
            [
                'title' => 'Contact & Customer Care Center',
                'content' => $contactContent,
                'summary_of_changes' => 'Initial publication of customer care center and escalation matrix.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );
    }
}
