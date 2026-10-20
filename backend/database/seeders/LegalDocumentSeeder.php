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
        $effectiveDate = Carbon::create(2026, 10, 20);

        // 1. TERMS OF SERVICE
        $termsContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — TERMS AND CONDITIONS OF SERVICE
**Effective Date:** October 20, 2026  
**Version:** 1.2.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. Introduction & Operating Philosophy
Welcome to **VICTORIOUS MARKET** ("Victorious Market", "the Platform", "we", "us", or "our"). Victorious Market is an institutional digital commerce and fulfillment platform designed and managed on the fundamental philosophy:  
**Trust First. Quality Always. Accountability at Every Stage.**

By creating an account, browsing listings, placing orders, fulfilling deliveries, or applying to become an approved vendor, you agree to the applicable Terms and Conditions. Legacy logistics-fleet terms are inactive for new orders and do not authorize a fleet, hub, rider payout, or logistics-company payable for the current vendor-managed model.

---

### 2. The Governed Marketplace Model
Victorious Market is an intentionally **controlled, centrally governed marketplace**, not an open unmoderated classifieds board:
* **Central Transaction Authority:** Victorious MARKET governs marketplace listings, order/payment records, payment verification, customer support, approved refunds, vendor settlement controls, disputes, and audit records. Vendors fulfil their own orders and manage their own approved riders. Do not describe funds as regulated or segregated escrow unless that arrangement is independently verified.
* **Prohibition of Off-Platform Dealing:** Buyers, merchants, and riders must not bypass Victorious MARKET for marketplace transactions. The only permitted customer payment paths are (1) pay before delivery through Victorious MARKET checkout, or (2) reserve an in-shop pickup through Victorious MARKET and pay at the approved vendor shop through Victorious MARKET's authorised payment workflow. Vendors must not collect cash or private-account transfers outside the platform. In-shop reserve-and-pay is counter pickup only, not doorstep COD.

---

### 3. Merchant Accreditation & Catalog Integrity
* **Verification Prerequisite:** No merchant is authorized to list products or receive orders without completing identity verification, physical shop verification, and Know-Your-Business (KYB) checks.
* **Mandatory condition transparency:** Listings must accurately state product condition and any warranty/packaging/accessory limitations:
  * **Brand New:** Accurately described new products. Factory-sealed/OEM status and any manufacturer warranty must be stated only where true and verified; do not imply a warranty that the supplier/manufacturer has not confirmed.
  * **UK Used / Pre-Owned:** Thoroughly tested, functional pre-owned items with all cosmetic blemishes, battery health indicators, and missing accessories explicitly disclosed.
* **Zero Counterfeit Tolerance:** Presenting used goods as new or listing replica/counterfeit items results in immediate merchant suspension, listing deletion, forfeiture of platform privileges, and referral to commercial law enforcement.

---

### 4. Payments, Refunds & Revenue Allocation
* **Cryptographic Gateway Verification:** Payments are processed via Paystack. An order is marked as paid only after server-to-server cryptographic verification (HMAC-SHA512).
* **Payment and settlement controls:** Customer payments are processed through the authorised Paystack workflow and reconciled to the parent checkout and vendor child orders. Do not promise that funds are held in a regulated or segregated escrow account unless that arrangement is independently verified. Vendor earnings become eligible only after confirmed fulfilment, expiry of the child's standard 24-hour claim window, and clearance of applicable holds and checks; eligibility is not an automatic transfer.
* **Merchandise Revenue Allocation:**
  * **90%** vendor merchandise allocation, recorded as earnings subject to fulfilment, the claim window, applicable holds, reconciliation, and manual payout approval.
  * **5%** Victorious Customer Cashback / Loyalty Allocation.
  * **5%** Victorious Market Platform Margin.
* **Delivery-fee allocation:** 90% of the configured delivery fee is allocated to the owning vendor and 10% to Victorious MARKET. Vendors arrange and pay their own riders outside platform financial systems; new orders create no platform rider or logistics-company payable.

---

### 5. Delivery, Handover & Cryptographic Handshakes
* **Geofenced Local Delivery:** Operations commence within verified coverage zones in Akwa Ibom State (centered in Uyo LGA) and expand only across verified logistics corridors.
* **Doorstep handover:** The customer's delivery OTP must be securely verified and a doorstep delivery photo recorded before completion.
* **In-shop pickup:** The customer must present the `RES-XXXXXX` reservation pass AND complete one-time customer pickup OTP verification. The vendor must securely verify both and record an in-shop pickup photo before completion. Neither credential replaces the photo; the customer pickup OTP is distinct from any rider-to-vendor parcel-custody OTP.
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
* **Disbursement and tender integrity:** At launch, approved refunds are initiated through the verified original Paystack payment route where supported. Wallet-credit refunds, legacy wallet-credit checkout tender, and off-platform refunds/payments are disabled. Cashback redemption is a separate capped loyalty discount. A submitted refund is not complete until provider confirmation/reconciliation.

---

### 9. 5% Victorious Customer Cashback Program
* **Maturity and validity:** Eligible 5% merchandise cashback matures after confirmed fulfilment, the standard 24-hour customer claim window, and a separate additional 24-hour maturity buffer, provided no blocking case remains. Its 365-day validity period starts at actual `matures_at`, not at checkout or delivery.
* **Non-Transferable:** Cashback is non-transferable and cannot be withdrawn to an external bank account. If the associated order is cancelled or refunded, reverse unspent cashback. If already-spent cashback is recoverable, track it separately and offset only against future cashback as it matures; never silently debit a cash balance.

---

### 10. Platform Suspension & Financial Recovery
* **Suspension Powers:** Victorious Market reserves the right to suspend or permanently ban any merchant, rider, or customer engaging in abusive returns, chargeback fraud, off-platform solicitation, or harassment.
* **Debt recovery:** Vendor liability must be supported by a documented, authorised decision; a complaint alone is not a debt. A confirmed debt may be repaid by verified bank transfer or offset against held/future vendor earnings only with authorised admin approval and contractual basis. Record recovery as a separate receivable; do not pretend a completed transfer has been reversed.

---

### 11. Governing Law & Arbitration
These Terms are governed by applicable laws of the Federal Republic of Nigeria and, where applicable, Akwa Ibom State. Customers may contact Victorious MARKET support to seek resolution. Nothing in this clause removes any mandatory right to approach a regulator, court, or other forum available under applicable law.
MARKDOWN;

        $doc1 = LegalDocument::updateOrCreate(
            ['slug' => 'terms-of-service'],
            [
                'title' => 'Terms and Conditions of Service',
                'category' => 'terms',
                'current_version' => '1.2.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Foundational operating charter covering approved-vendor marketplace transactions, payment verification, vendor-managed fulfilment, handover evidence, customer remedies, and financial controls.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc1->id,
                'version' => '1.2.0',
            ],
            [
                'title' => 'Terms and Conditions of Service',
                'content' => $termsContent,
                'summary_of_changes' => 'Alignment release: corrected payment/refund claims, vendor-managed fulfilment, cashback rules, and consumer-rights safeguards; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 2. RETURN AND REFUND POLICY
        $refundContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — RETURN, REFUND & DOORSTEP ACCEPTANCE POLICY
**Effective Date:** October 20, 2026  
**Version:** 1.2.0  
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
                'current_version' => '1.2.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Guidelines covering item-level partial rejection, the standard 24-hour claim window, admin-reviewed exceptions, and original-payment-route refunds.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc2->id,
                'version' => '1.2.0',
            ],
            [
                'title' => 'Return, Refund & Doorstep Acceptance Policy',
                'content' => $refundContent,
                'summary_of_changes' => 'Alignment release: clarified claim-window exceptions, evidence limits, original-channel refunds, and provider reconciliation; previous version retained.',
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

        LegalDocumentVersion::firstOrCreate(
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
**Version:** 1.2.0  
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

### 4. Order Acceptance, Stock Accuracy & Readiness
* **Inventory Synchronization:** The Merchant must maintain real-time inventory records. Confirming an order that cannot be fulfilled due to preventable stockouts attracts administrative penalties.
* **Order readiness:** Upon receiving an order notification, the vendor must promptly accept or reject the order in the platform, keep stock accurate, and provide a realistic readiness estimate. No fixed two-hour readiness guarantee applies unless a separately approved vendor SLA is configured and disclosed for the order.
* **Handover verification:** For doorstep delivery, the vendor must hand the parcel only to the active rider assigned to that order and linked to the same vendor, after the approved rider-to-vendor custody verification. This custody credential is distinct from the customer's doorstep delivery OTP and the customer's in-shop pickup OTP. In-shop pickup is a separate customer-counter process and requires no rider.

---

### 5. Vendor Earnings, Payout & 90/5/5 Allocation
* **Merchandise allocation:** Eligible post-discount merchandise collected is allocated 90% to vendor earnings, 5% to customer cashback liability, and 5% to Victorious MARKET. Cashback is not calculated on delivery fees. The vendor allocation is subject to item-level refunds/reversals and applicable earning holds; it is not an unconditional promise of net payout.
* **Vendor earnings eligibility:** Vendor earnings become eligible 24 hours after confirmed doorstep delivery or confirmed in-shop pickup, provided no applicable return/dispute/payment hold or security/reconciliation check blocks them. Eligibility is not automatic transfer.
* **Bank-change security:** A 48-hour payout cooldown applies after a change to the vendor's registered payout bank details, subject to the platform's verified security controls.
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
* **Post-payout recovery:** If an approved refund, chargeback, or adjustment creates a potential vendor recovery after payout, record the case and evidence. Vendor liability becomes a confirmed debt only after an authorised, documented decision under the vendor agreement and applicable law. A complaint or pending investigation alone is not a debt.
* **Authorised recovery:** A confirmed vendor debt may be repaid by verified bank transfer or offset against held/future vendor earnings only with authorised admin approval and contractual basis. A complaint alone is not a confirmed debt; record the debt/recovery separately and allow a vendor challenge.

---

### 9. Prohibition of Off-Platform Dealing & WhatsApp Bypass
* **Non-Circumvention:** Merchants must not divert marketplace orders into private cash collection, personal-account transfers, or other off-platform payment. Ordinary customer support or fulfilment communications are not, by themselves, prohibited; the restriction is on bypassing the marketplace transaction and payment controls. Doorstep cash-on-delivery is disabled at launch.
* **Enforcement:** Suspected bypass is investigated and may lead to proportionate suspension or termination under the vendor agreement, with evidence, notice and any required review. Any contractual damages or recovery must have a valid contractual and legal basis; it is not imposed automatically by this statement.

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
                'current_version' => '1.2.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Commercial covenants governing approved-vendor accreditation, accurate product condition, the 90/5/5 merchandise allocation, controlled payouts, anti-counterfeit obligations, and authorised debt recovery.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc4->id,
                'version' => '1.2.0',
            ],
            [
                'title' => 'Merchant Accreditation & Commercial Seller Agreement',
                'content' => $merchantContent,
                'summary_of_changes' => 'Alignment release: corrected payout eligibility, vendor debt confirmation, and vendor-managed fulfilment rules; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 5. LOGISTICS FLEET OPERATIONS & CHAIN-OF-CUSTODY SLA
        $logisticsContent = <<<'MARKDOWN'
# LEGACY — LOGISTICS FLEET OPERATIONS & CHAIN-OF-CUSTODY SLA (INACTIVE FOR NEW ORDERS)

> **Historical record only:** Victorious MARKET does not assign new orders to a central fleet, operate collection hubs for new fulfilment, or pay rider/logistics compensation for new vendor-managed orders. This document is retained for audit and historical obligations only; it is not the active policy for new orders.
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
**Primary launch market:** Uyo, Akwa Ibom State, Federal Republic of Nigeria  
**Version:** 1.2.0  

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
* **Delivery-fee allocation:** For new vendor-managed orders, 90% of the configured delivery fee is allocated to the owning vendor and 10% to Victorious MARKET. Vendors arrange and pay their own riders outside platform financial systems. The legacy fleet split is inactive for new orders.

---

### 5. Geographic Discipline & Expansion
Our launch focus is **Uyo, Akwa Ibom State**, with service to other destinations only where an approved vendor confirms coverage and a delivery fee. Vendors manage their own riders and fulfilment. Delivery estimates are not guarantees unless explicitly confirmed for the particular order; no central fleet or collection hub is required for new orders.
MARKDOWN;

        $doc6 = LegalDocument::updateOrCreate(
            ['slug' => 'about-us'],
            [
                'title' => 'About Victorious Market — Operating Charter',
                'category' => 'about',
                'current_version' => '1.2.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Foundational operating philosophy, managed marketplace vision, geographic discipline in Akwa Ibom State, and core brand promise.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc6->id,
                'version' => '1.2.0',
            ],
            [
                'title' => 'About Victorious Market — Operating Charter',
                'content' => $aboutContent,
                'summary_of_changes' => 'Alignment release: removed central-fleet and unsupported delivery-guarantee claims; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 7. ORDER CANCELLATION POLICY
        $cancellationContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — ORDER CANCELLATION & PRE-DISPATCH POLICY
**Effective Date:** October 20, 2026  
**Version:** 1.2.0  
**Governing Territory:** Akwa Ibom State, Federal Republic of Nigeria  

---

### 1. Overview
Victorious MARKET uses a controlled cancellation and refund workflow designed to balance customer flexibility with vendor preparation and fulfilment costs. Payments and refunds must be verified and reconciled; do not represent the service as regulated or segregated escrow unless that arrangement is independently verified.

---

### 2. Pre-Fulfilment Cancellation and Refund Review
* **Permitted window:** Ordinary cancellation is generally available before the vendor accepts or begins preparing the order, subject to the persisted order state. Once the vendor accepts or prepares it, ordinary cancellation may be restricted, but this does not remove remedies for failed fulfilment, duplicate payment, wrong/defective goods, or rights that cannot lawfully be excluded.
* **Refund:** A verified refund request is processed under the applicable approval rule. Automatic approval is limited to a verified duplicate successful capture and a verified payment for an order cancelled before any vendor action. Other refunds require persisted authorised human approval before gateway dispatch. Refunds use the original verified Paystack route where supported; wallet-credit refunds are disabled at launch. Delivery-fee refundability is assessed separately. A submitted request is not a completed refund.
* **No invented fee:** Do not invent a cancellation penalty. The refundable amount and any delivery-fee reversal depend on what was actually captured, the reason, the applicable policy, and law.

---

### 3. In-Shop Pickup Reservations (24-Hour Expiry)
* **Reservation Window:** In-shop pickup reservations hold the selected stock for **twenty-four (24) hours**.
* **Payment and collection:** The customer reserves through Victorious MARKET and pays at the approved vendor shop through Victorious MARKET's authorised payment workflow. The vendor must not accept an off-platform cash/private-account payment and must not release the item until the platform verifies payment.
* **Automatic Expiration:** If the customer does not complete the platform-recorded payment and pickup within 24 hours, the reservation may expire and stock may return to active inventory only after confirming there is no successful or uncertain/in-flight payment. Reconcile uncertain payments before releasing stock.

---

### 4. Post-Dispatch In-Transit Policy
* **Ordinary cancellation after dispatch:** Once an order is out for delivery, an ordinary change-of-mind cancellation may be restricted because fulfilment costs may be committed. This does not bar a customer from rejecting wrong, damaged, defective, or non-conforming goods, reporting failed fulfilment, or seeking any remedy required by applicable law.
* **Doorstep Decision:** The customer must await the rider's arrival and exercise their **Doorstep Acceptance & Partial Rejection** rights at physical handover.
* **Repeated unjustified refusals:** Repeated refusals of conforming goods without a valid reason may be reviewed under a fair customer-account policy. Cash-on-delivery is disabled at launch, so no COD privilege is offered or suspended.

---

### 5. Merchant Stockout Cancellations
* **Customer remedy:** If a vendor cannot fulfil a paid child order because of a stockout or inventory error, stop fulfilment, record the reason, notify the customer, and open a controlled refund/cancellation case. Automatic refund approval is permitted only where the transaction meets the narrow approved exception (including cancellation before any vendor action); otherwise an authorised admin must approve before gateway dispatch. Refund only the amount due under the captured transaction and applicable policy, assess delivery separately, and reconcile provider completion. Other vendors' child orders remain independent.
* **Merchant Accountability:** Preventable merchant stockouts are logged against the vendor's fulfillment rating. Repeated stockouts trigger temporary catalog delisting.

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
                'current_version' => '1.2.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Guidelines covering ordinary cancellation stages, the 24-hour in-shop reservation hold, controlled refund approval, and customer remedies during fulfilment.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc7->id,
                'version' => '1.2.0',
            ],
            [
                'title' => 'Order Cancellation & Pre-Dispatch Policy',
                'content' => $cancellationContent,
                'summary_of_changes' => 'Alignment release: corrected cancellation/refund approval, stockout remedies, and cashback reversal; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 8. FREQUENTLY ASKED QUESTIONS (FAQ) & KNOWLEDGE BASE
        $faqContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — FREQUENTLY ASKED QUESTIONS (FAQ)
**Knowledge Base & Operational Answers**  
**Effective Date:** October 20, 2026 &bull; Version: 1.2.0  

---

### General & Platform Trust

#### What is Victorious Market?
Victorious MARKET is a managed online marketplace in Nigeria. We connect customers with approved vendors, process marketplace payments through the authorised workflow, coordinate customer support and refund decisions, and require recorded handover evidence. The standard operational claim window is 24 hours from confirmed delivery or pickup, subject to applicable consumer rights and valid warranty obligations; it is not a guarantee that every return will be approved.

#### How is Victorious Market different from open classifieds or social media sellers?
On open social media platforms (like Instagram or WhatsApp), buyers may have limited transaction records. On Victorious MARKET, **we govern the marketplace transaction** through approved vendors, payment verification, documented handover, refund review and reconciliation:
* Merchants are vetted and physically inspected.
* Payments are processed through the authorised provider workflow and reconciled to order and financial records. We do not represent the service as a regulated or segregated escrow account unless that arrangement is independently verified.
* Riders must exchange an OTP before delivery is valid.
* You can partially reject defective items at your doorstep.

#### Where are your services currently available?
We are headquartered in **Uyo, Akwa Ibom State**. Our delivery coverage spans Uyo Local Government Area and primary transit corridors across Akwa Ibom State, with planned expansion into neighboring commercial centers.

---

### Shopping, Payments & Cashback

#### How are payment and vendor settlement controlled?
Payments are processed through Victorious MARKET's authorised Paystack workflow and reconciled to the parent checkout and vendor-specific child orders. Vendor earnings do not become payout-eligible until confirmed delivery or pickup, expiry of the applicable 24-hour claim window, and clearance of holds and reconciliation checks. Eligible earnings still require a vendor request and authorised admin approval. This is not a promise of regulated or segregated escrow custody.

#### Can I reserve an item and pay at the vendor shop?
Yes. Create an in-shop pickup reservation through Victorious MARKET. The reservation holds the item for 24 hours. At the approved vendor shop, inspect the item and complete payment through Victorious MARKET's authorised payment workflow before the item is released. Vendors may not collect cash or private-account transfers outside the platform.

#### What payment methods do you accept?
Launch payment rails are Paystack card and standard bank transfer through Victorious MARKET. Customers may pay before delivery or reserve in-shop pickup and pay at the vendor shop through the platform's authorised workflow. Legacy wallet-credit checkout tender is disabled; cashback redemption is a separate capped loyalty discount. USSD, DVA, mobile money and other rails are not enabled unless separately approved.

#### What is the 5% Customer Cashback Program?
Eligible post-discount merchandise may accrue 5% cashback; delivery fees are excluded. Cashback remains pending until verified fulfilment, expiry of the 24-hour claim window, and a separate 24-hour maturity buffer, provided no blocking case remains. Its 365-day validity starts at actual maturity. It is non-transferable and not withdrawable to a bank. Redemption is capped at 50% of merchandise subtotal per checkout. If the order is refunded, unspent cashback is reversed; any recoverable spent cashback is tracked separately and offset only against future cashback as it matures.

---

### Delivery, Logistics & Doorstep Handover

#### How long does delivery take?
Delivery availability and estimates depend on the vendor, destination, stock, rider availability, and the specific order confirmation. The platform does not guarantee a universal 1–3 hour or same-day delivery time.

#### What is the delivery handover check?
Doorstep delivery requires server-side verification of the one-time customer delivery OTP and a linked doorstep delivery photo. Keep the OTP private and share it only for the intended handover. OTP/photo evidence records the handover process; it does not prove that the item is correct, defect-free, or free from a later warranty issue.

#### What is "Doorstep Partial Rejection"?
If you order 3 items (e.g. a phone, a case, and a charger) and the charger is wrong or damaged, you do **not** have to reject the whole package! You can accept the phone and case, and reject only the charger right at your doorstep. You will be refunded for the rejected item while keeping the rest.

#### How are fragile goods transported?
The owning vendor is responsible for selecting an appropriate and safe delivery method for the product. Any vehicle or handling promise must be confirmed for the specific order; the platform does not guarantee a universal vehicle type.

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
                'current_version' => '1.2.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Customer answers covering payment verification, in-shop pickup, doorstep handover, the standard 24-hour claim window, cashback and approved-vendor policies.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc8->id,
                'version' => '1.2.0',
            ],
            [
                'title' => 'Frequently Asked Questions (FAQ) & Knowledge Base',
                'content' => $faqContent,
                'summary_of_changes' => 'Alignment release: corrected payment, cashback, handover, delivery-estimate, and return-window descriptions; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );

        // 9. CONTACT & CUSTOMER CARE CENTER
        $contactContent = <<<'MARKDOWN'
# VICTORIOUS MARKET — CONTACT & CUSTOMER CARE CENTER
**Customer Support & Official Contact**  
**Effective Date:** October 20, 2026 &bull; Version: 1.2.0  

---

### 1. Official Contact

For assistance with orders, payments, delivery, returns, or disputes, use the official Victorious MARKET website or contact the customer-care number below:

* **Phone / WhatsApp:** `09118949035`
* **Website:** https://victoriousmarket.com.ng

Include your order number and the affected vendor child order/item where relevant. Never send passwords, card security codes, or one-time passwords to customer support.

---

### 2. Vendor-Managed Fulfilment and Pickup

New orders are fulfilled by the owning approved vendor, which manages its own riders. Victorious MARKET does not require a central fleet, central collection hub, or hub drop-off for new orders. In-shop pickup takes place only at the vendor shop identified in the order or reservation. Contact support for authorised return/inspection instructions; do not send goods to an unverified address.

---

### 3. Complaints and Disputes

Open a support request through the website/order record or use the official contact number above. Explain the issue, identify the affected item and vendor child order, and attach available supporting evidence. An authorised administrator reviews the case and records the decision. No fixed 24-hour dispute-resolution or refund-completion time is promised. Applicable consumer rights and valid warranty obligations remain in force.

---

### 4. Fraud and Off-Platform Payment Reports

Use the official website or phone number above to report suspected counterfeit goods, private payment requests, impersonation, or other marketplace abuse. Do not rely on unverified email addresses or unofficial physical collection-point listings.

MARKDOWN;

        $doc9 = LegalDocument::updateOrCreate(
            ['slug' => 'contact-and-help-center'],
            [
                'title' => 'Contact & Customer Care Center',
                'category' => 'support',
                'current_version' => '1.2.0',
                'effective_date' => $effectiveDate,
                'is_published' => true,
                'summary' => 'Official customer contact channel, vendor-managed fulfilment explanation, and order-support escalation steps.',
            ]
        );

        LegalDocumentVersion::firstOrCreate(
            [
                'legal_document_id' => $doc9->id,
                'version' => '1.2.0',
            ],
            [
                'title' => 'Contact & Customer Care Center',
                'content' => $contactContent,
                'summary_of_changes' => 'Alignment release: replaced unverified contact/address/hub claims with official contact details and vendor-managed support instructions; previous version retained.',
                'effective_date' => $effectiveDate,
                'ip_address' => '127.0.0.1',
            ]
        );
    }
}
