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
    }
}
