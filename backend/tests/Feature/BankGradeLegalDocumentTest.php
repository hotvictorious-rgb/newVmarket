<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use Database\Seeders\LegalDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankGradeLegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalDocumentSeeder::class);

        $this->superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->customer = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
        ]);
    }

    /**
     * Test P0: Default seeded terms of service exist and contain foundational charter clauses.
     */
    public function test_default_terms_and_conditions_seeded_and_valid(): void
    {
        $terms = LegalDocument::getBySlug('terms-of-service');

        $this->assertNotNull($terms);
        $this->assertEquals('Terms and Conditions of Service', $terms->title);
        $this->assertEquals('1.0.0', $terms->current_version);
        $this->assertEquals('2026-10-17', $terms->effective_date->format('Y-m-d'));
        $this->assertTrue($terms->is_published);

        $version = $terms->currentVersion;
        $this->assertNotNull($version);
        $this->assertStringContainsString('Trust First. Quality Always. Accountability at Every Stage.', $version->content);
        $this->assertStringContainsString('90%', $version->content);
        $this->assertStringContainsString('cash_in_escrow', $version->content);
        $this->assertStringContainsString('Akwa Ibom State', $version->content);
        $this->assertStringContainsString('Doorstep Acceptance & Partial Rejection', $version->content);
        $this->assertStringContainsString('24-Hour Inspection Policy', $version->content);
    }

    /**
     * Test P0: Public /terms route renders HTML view with sticky TOC and effective date.
     */
    public function test_public_terms_page_renders_with_table_of_contents_and_markdown(): void
    {
        $response = $this->get(route('terms'));

        $response->assertStatus(200);
        $response->assertSee('Terms and Conditions of Service');
        $response->assertSee('v1.0.0');
        $response->assertSee('Oct 17, 2026');
        $response->assertSee('CENTRALLY GOVERNED MARKETPLACE CONTRACT');
        $response->assertSee('Print / Save PDF');
    }

    /**
     * Test P0: Public /legal/{slug} route resolves return policy and privacy policy.
     */
    public function test_public_legal_slug_route_resolves_policies(): void
    {
        $returnRes = $this->get(route('legal.show', 'return-and-refund-policy'));
        $returnRes->assertStatus(200);
        $returnRes->assertSee('Return, Refund &amp; Doorstep Acceptance Policy', false);
        $returnRes->assertSee('The 24-Hour Return Guarantee');

        $privacyRes = $this->get(route('legal.show', 'privacy-policy'));
        $privacyRes->assertStatus(200);
        $privacyRes->assertSee('Privacy and Data Protection Policy');
        $privacyRes->assertSee('Nigeria Data Protection Act');
    }

    /**
     * Test P1: Access control restricts admin legal desk to super_admin.
     */
    public function test_access_control_protects_admin_legal_desk(): void
    {
        // Unauthenticated
        $this->get(route('admin.legal.index'))->assertRedirect(route('admin.login'));

        // Customer forbidden
        $this->actingAs($this->customer)
            ->get(route('admin.legal.index'))
            ->assertStatus(403);

        // Super Admin authorized
        $this->actingAs($this->superAdmin)
            ->get(route('admin.legal.index'))
            ->assertStatus(200)
            ->assertSee('Bank-Grade Legal &amp; Policy Governance Desk', false);
    }

    /**
     * Test P0: Super Admin publishes new version, creating immutable audit trail and instant public sync.
     */
    public function test_super_admin_can_publish_new_version_with_audit_trail(): void
    {
        $terms = LegalDocument::where('slug', 'terms-of-service')->firstOrFail();

        $updatePayload = [
            'title' => 'Terms and Conditions of Service (Amended)',
            'version' => '1.1.0',
            'effective_date' => '2026-10-18',
            'summary_of_changes' => 'Added explicit drone and keke delivery clause for Akwa Ibom corridors',
            'summary' => 'Updated foundational operating terms with expanded logistics lanes.',
            'content' => "# AMENDED TERMS\n\nUpdated clause on Akwa Ibom fulfillment corridors.\n\n### 1. Governed Operations",
            'is_published' => '1',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.legal.update', $terms->id), $updatePayload);

        $response->assertRedirect(route('admin.legal.index'));
        $response->assertSessionHas('status');

        $terms->refresh();
        $this->assertEquals('1.1.0', $terms->current_version);
        $this->assertEquals('Terms and Conditions of Service (Amended)', $terms->title);
        $this->assertEquals('2026-10-18', $terms->effective_date->format('Y-m-d'));

        // Verify version history has 2 distinct records
        $versions = LegalDocumentVersion::where('legal_document_id', $terms->id)->orderBy('id', 'asc')->get();
        $this->assertCount(2, $versions);
        $this->assertEquals('1.0.0', $versions[0]->version);
        $this->assertEquals('1.1.0', $versions[1]->version);
        $this->assertEquals($this->superAdmin->id, $versions[1]->published_by);

        // Verify Audit Log
        $log = AdminAuditLog::where('target_type', LegalDocument::class)
            ->where('target_id', $terms->id)
            ->where('action', 'legal_document_version_published')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('1.1.0', $log->new_values['version']);

        // Verify public storefront immediately serves the new version
        $publicRes = $this->get(route('terms'));
        $publicRes->assertStatus(200);
        $publicRes->assertSee('v1.1.0');
        $publicRes->assertSee('AMENDED TERMS');
    }

    /**
     * Test P1: Mobile public API endpoints provide JSON legal policies for mobile app integration.
     */
    public function test_public_api_legal_endpoints(): void
    {
        // 1. Index
        $indexRes = $this->getJson('/api/v1/legal');
        $indexRes->assertStatus(200);
        $indexRes->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'slug', 'title', 'category', 'current_version', 'effective_date', 'summary']
            ]
        ]);

        // 2. Specific Document
        $docRes = $this->getJson('/api/v1/legal/terms-of-service');
        $docRes->assertStatus(200);
        $docRes->assertJson([
            'success' => true,
            'data' => [
                'slug' => 'terms-of-service',
                'title' => 'Terms and Conditions of Service',
                'version' => '1.0.0',
            ]
        ]);
        $this->assertNotEmpty($docRes->json('data.content_markdown'));
        $this->assertNotEmpty($docRes->json('data.content_html'));
    }

    /**
     * Test P0: Merchant Accreditation Agreement contains B2B covenants, 2h SLA, and offset debt recovery.
     */
    public function test_merchant_agreement_seeded_with_kyb_and_indemnity_covenants(): void
    {
        $merchantDoc = LegalDocument::getBySlug('merchant-agreement');

        $this->assertNotNull($merchantDoc);
        $this->assertEquals('Merchant Accreditation & Commercial Seller Agreement', $merchantDoc->title);
        $this->assertEquals('merchant', $merchantDoc->category);
        $this->assertEquals('1.0.0', $merchantDoc->current_version);

        $version = $merchantDoc->currentVersion;
        $this->assertNotNull($version);
        $this->assertStringContainsString('Merchant Vetting, KYB & Physical Shop Accreditation', $version->content);
        $this->assertStringContainsString('2-Hour Handover SLA', $version->content);
        $this->assertStringContainsString('Debt Recovery, Deficit Balances & Right of Offset', $version->content);
        $this->assertStringContainsString('Prohibition of Off-Platform Dealing', $version->content);

        // Public Web Route
        $response = $this->get(route('legal.show', 'merchant-agreement'));
        $response->assertStatus(200);
        $response->assertSee('Merchant Accreditation &amp; Commercial Seller Agreement', false);
        $response->assertSee('Zero Counterfeit Tolerance');

        // Public API Route
        $apiRes = $this->getJson('/api/v1/legal/merchant-agreement');
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'success' => true,
            'data' => [
                'slug' => 'merchant-agreement',
                'category' => 'merchant',
            ]
        ]);
    }

    /**
     * Test P0: Logistics SLA binds fleet operators to OTP verification, photographic proof, and strict liability.
     */
    public function test_logistics_sla_seeded_with_otp_custody_and_fleet_liability(): void
    {
        $logisticsDoc = LegalDocument::getBySlug('logistics-service-agreement');

        $this->assertNotNull($logisticsDoc);
        $this->assertEquals('Logistics Fleet Operations & Chain-of-Custody SLA', $logisticsDoc->title);
        $this->assertEquals('logistics', $logisticsDoc->category);
        $this->assertEquals('1.0.0', $logisticsDoc->current_version);

        $version = $logisticsDoc->currentVersion;
        $this->assertNotNull($version);
        $this->assertStringContainsString('Cryptographic OTP Handshake & Chain of Custody Invariants', $version->content);
        $this->assertStringContainsString('Strict Liability for Bypassed OTP', $version->content);
        $this->assertStringContainsString('Photographic Evidence & R2 Storage Protocol', $version->content);
        $this->assertStringContainsString('Cargo Tiers & Vehicle Classifications', $version->content);

        // Public Web Route
        $response = $this->get(route('legal.show', 'logistics-service-agreement'));
        $response->assertStatus(200);
        $response->assertSee('Logistics Fleet Operations &amp; Chain-of-Custody SLA', false);
        $response->assertSee('Verifiable Chain of Custody');

        // Public API Route
        $apiRes = $this->getJson('/api/v1/legal/logistics-service-agreement');
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'success' => true,
            'data' => [
                'slug' => 'logistics-service-agreement',
                'category' => 'logistics',
            ]
        ]);
    }

    /**
     * Test P1: All 9 canonical legal policies are indexed and accessible in the system.
     */
    public function test_all_nine_legal_documents_available_in_public_index_and_api(): void
    {
        $allDocs = LegalDocument::where('is_published', true)->get();
        $this->assertCount(9, $allDocs);

        $expectedSlugs = [
            'terms-of-service',
            'return-and-refund-policy',
            'privacy-policy',
            'merchant-agreement',
            'logistics-service-agreement',
            'about-us',
            'cancellation-policy',
            'frequently-asked-questions',
            'contact-and-help-center',
        ];

        $this->assertEqualsCanonicalizing($expectedSlugs, $allDocs->pluck('slug')->toArray());

        $indexRes = $this->getJson('/api/v1/legal');
        $indexRes->assertStatus(200);
        $this->assertCount(9, $indexRes->json('data'));
    }

    /**
     * Test P0: About Us charter is seeded with brand promise, 90/5/5 model, and resolves via /about.
     */
    public function test_about_us_seeded_with_operating_charter_and_canonical_route(): void
    {
        $aboutDoc = LegalDocument::getBySlug('about-us');

        $this->assertNotNull($aboutDoc);
        $this->assertEquals('About Victorious Market — Operating Charter', $aboutDoc->title);
        $this->assertEquals('about', $aboutDoc->category);
        $this->assertEquals('1.0.0', $aboutDoc->current_version);

        $version = $aboutDoc->currentVersion;
        $this->assertNotNull($version);
        $this->assertStringContainsString('Your Trusted Online Market for Quality Products', $version->content);
        $this->assertStringContainsString('The Controlled Marketplace Model', $version->content);
        $this->assertStringContainsString('The 90/5/5 Allocation', $version->content);
        $this->assertStringContainsString('Uyo, Akwa Ibom State', $version->content);

        // Canonical Web Route
        $response = $this->get(route('about'));
        $response->assertStatus(200);
        $response->assertSee('About Victorious Market — Operating Charter');
        $response->assertSee('INSTITUTIONAL CHARTER &amp; VISION', false);

        // Public API Route
        $apiRes = $this->getJson('/api/v1/legal/about-us');
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'success' => true,
            'data' => [
                'slug' => 'about-us',
                'category' => 'about',
            ]
        ]);
    }

    /**
     * Test P0: Cancellation policy is seeded with pre-dispatch, transit, and escrow reversal clauses.
     */
    public function test_cancellation_policy_seeded_with_reversals_and_canonical_route(): void
    {
        $cancelDoc = LegalDocument::getBySlug('cancellation-policy');

        $this->assertNotNull($cancelDoc);
        $this->assertEquals('Order Cancellation & Pre-Dispatch Policy', $cancelDoc->title);
        $this->assertEquals('returns', $cancelDoc->category);
        $this->assertEquals('1.0.0', $cancelDoc->current_version);

        $version = $cancelDoc->currentVersion;
        $this->assertNotNull($version);
        $this->assertStringContainsString('Pre-Dispatch Customer Cancellation', $version->content);
        $this->assertStringContainsString('Post-Dispatch In-Transit Policy', $version->content);
        $this->assertStringContainsString('In-Shop Pickup Reservations (24-Hour Expiry)', $version->content);

        // Canonical Web Route
        $response = $this->get(route('cancellation'));
        $response->assertStatus(200);
        $response->assertSee('Order Cancellation &amp; Pre-Dispatch Policy', false);
        $response->assertSee('ORDER CANCELLATION &amp; REVERSAL POLICY', false);

        // Public API Route
        $apiRes = $this->getJson('/api/v1/legal/cancellation-policy');
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'success' => true,
            'data' => [
                'slug' => 'cancellation-policy',
                'category' => 'returns',
            ]
        ]);
    }

    /**
     * Test P0: FAQ is seeded with escrow, pickup inspection, 5% cashback, and delivery speeds.
     */
    public function test_frequently_asked_questions_seeded_and_canonical_route(): void
    {
        $faqDoc = LegalDocument::getBySlug('frequently-asked-questions');

        $this->assertNotNull($faqDoc);
        $this->assertEquals('Frequently Asked Questions (FAQ) & Knowledge Base', $faqDoc->title);
        $this->assertEquals('faq', $faqDoc->category);
        $this->assertEquals('1.0.0', $faqDoc->current_version);

        $version = $faqDoc->currentVersion;
        $this->assertNotNull($version);
        $this->assertStringContainsString('How does Escrow Protection work?', $version->content);
        $this->assertStringContainsString('Can I inspect an item before paying?', $version->content);
        $this->assertStringContainsString('What is the 5% Customer Cashback Program?', $version->content);

        // Canonical Web Route
        $response = $this->get(route('faq'));
        $response->assertStatus(200);
        $response->assertSee('Frequently Asked Questions (FAQ) &amp; Knowledge Base', false);
        $response->assertSee('OPERATIONS KNOWLEDGE BASE &amp; FAQ', false);

        // Public API Route
        $apiRes = $this->getJson('/api/v1/legal/frequently-asked-questions');
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'success' => true,
            'data' => [
                'slug' => 'frequently-asked-questions',
                'category' => 'faq',
            ]
        ]);
    }

    /**
     * Test P0: Contact & Help Center directory is seeded with omnichannel channels, SLAs, and hub addresses.
     */
    public function test_contact_and_help_center_seeded_and_canonical_route(): void
    {
        $contactDoc = LegalDocument::getBySlug('contact-and-help-center');

        $this->assertNotNull($contactDoc);
        $this->assertEquals('Contact & Customer Care Center', $contactDoc->title);
        $this->assertEquals('support', $contactDoc->category);
        $this->assertEquals('1.0.0', $contactDoc->current_version);

        $version = $contactDoc->currentVersion;
        $this->assertNotNull($version);
        $this->assertStringContainsString('Customer Care & Hotlines', $version->content);
        $this->assertStringContainsString('Operational Headquarters & Physical Hub', $version->content);
        $this->assertStringContainsString('Collection Points & Hub Drop-Off Network', $version->content);

        // Canonical Web Route
        $response = $this->get(route('contact'));
        $response->assertStatus(200);
        $response->assertSee('Contact &amp; Customer Care Center', false);
        $response->assertSee('OMNICHANNEL SUPPORT &amp; HELP DESK', false);

        // Public API Route
        $apiRes = $this->getJson('/api/v1/legal/contact-and-help-center');
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'success' => true,
            'data' => [
                'slug' => 'contact-and-help-center',
                'category' => 'support',
            ]
        ]);
    }

    /**
     * Test P0: Super Admin can save a working draft without altering the active public version.
     */
    public function test_super_admin_can_save_working_draft_without_affecting_live_storefront(): void
    {
        $terms = LegalDocument::where('slug', 'terms-of-service')->firstOrFail();
        $this->assertEquals('1.0.0', $terms->current_version);
        $this->assertFalse($terms->has_draft);

        $draftPayload = [
            'title' => 'Terms of Service (Draft Revision)',
            'version' => '1.1.0-draft',
            'effective_date' => '2026-11-01',
            'summary_of_changes' => 'Drafting new drone courier clause',
            'summary' => 'Draft working summary description',
            'content' => "# UNRELEASED DRAFT\n\nThis is a private draft not visible to the public.",
        ];

        // 1. Super Admin Saves Draft
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.legal.draft.save', $terms->id), $draftPayload);

        $response->assertRedirect(route('admin.legal.edit', $terms->id));
        $response->assertSessionHas('status');

        $terms->refresh();
        $this->assertTrue($terms->has_draft);
        $this->assertEquals('Terms of Service (Draft Revision)', $terms->draft_title);
        $this->assertEquals('1.1.0-draft', $terms->draft_version);
        $this->assertStringContainsString('UNRELEASED DRAFT', $terms->draft_content);

        // 2. Active Public Version MUST Remain Untouched (v1.0.0)
        $this->assertEquals('1.0.0', $terms->current_version);
        $this->assertEquals('Terms and Conditions of Service', $terms->title);

        // 3. Public Storefront MUST NOT Serve the Draft Content
        $publicRes = $this->get(route('terms'));
        $publicRes->assertStatus(200);
        $publicRes->assertSee('v1.0.0');
        $publicRes->assertDontSee('UNRELEASED DRAFT');

        // 4. Public API MUST NOT Serve the Draft Content
        $apiRes = $this->getJson('/api/v1/legal/terms-of-service');
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'success' => true,
            'data' => [
                'version' => '1.0.0',
            ]
        ]);
        $this->assertStringNotContainsString('UNRELEASED DRAFT', $apiRes->json('data.content_markdown'));
    }

    /**
     * Test P0: Super Admin can preview saved draft with draft warning ribbon, but public is forbidden.
     */
    public function test_super_admin_can_preview_saved_draft_in_storefront_context(): void
    {
        $terms = LegalDocument::where('slug', 'terms-of-service')->firstOrFail();
        $terms->update([
            'has_draft' => true,
            'draft_title' => 'Terms of Service (Under Review)',
            'draft_version' => '1.2.0',
            'draft_content' => "# DRAFT PREVIEW HEADING\n\nSecret clauses being evaluated.",
            'draft_summary' => 'Under review summary',
            'draft_effective_date' => '2026-12-01',
            'draft_updated_at' => now(),
        ]);

        // Unauthenticated access redirects to login
        $this->get(route('admin.legal.preview_draft', $terms->id))
            ->assertRedirect(route('admin.login'));

        // Customer access forbidden
        $this->actingAs($this->customer)
            ->get(route('admin.legal.preview_draft', $terms->id))
            ->assertStatus(403);

        // Super Admin gets 200 with draft warning ribbon and dynamic TOC
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.legal.preview_draft', $terms->id));

        $response->assertStatus(200);
        $response->assertSee('SUPER ADMIN DRAFT PREVIEW');
        $response->assertSee('Terms of Service (Under Review)');
        $response->assertSee('v1.2.0 (Draft)');
        $response->assertSee('DRAFT PREVIEW HEADING');
        $response->assertSee('Return to Editor');
    }

    /**
     * Test P1: Real-time raw markdown preview endpoint returns parsed HTML with tables and GFM.
     */
    public function test_live_raw_markdown_preview_endpoint_returns_rendered_html(): void
    {
        $markdown = "# Section 1\n\nThis is **bold**.\n\n| Feature | Status |\n|---|---|\n| Escrow | Active |";

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.legal.preview_raw'), [
                'content' => $markdown,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertStringContainsString('<h1>Section 1</h1>', $response->json('html'));
        $this->assertStringContainsString('<strong>bold</strong>', $response->json('html'));
        $this->assertStringContainsString('<table>', $response->json('html'));
        $this->assertStringContainsString('<td>Escrow</td>', $response->json('html'));
    }

    /**
     * Test P0: Super Admin can discard a working draft and restore editor state.
     */
    public function test_super_admin_can_discard_working_draft(): void
    {
        $terms = LegalDocument::where('slug', 'terms-of-service')->firstOrFail();
        $terms->update([
            'has_draft' => true,
            'draft_title' => 'Temporary Discardable Draft',
            'draft_version' => '9.9.9',
            'draft_content' => 'Temporary text',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.legal.draft.discard', $terms->id));

        $response->assertRedirect(route('admin.legal.edit', $terms->id));
        $response->assertSessionHas('status');

        $terms->refresh();
        $this->assertFalse($terms->has_draft);
        $this->assertNull($terms->draft_title);
        $this->assertNull($terms->draft_version);
        $this->assertNull($terms->draft_content);
    }

    /**
     * Test P0: Publishing a draft promotes it to live version and atomically clears draft fields.
     */
    public function test_publishing_promotes_version_and_clears_draft_state(): void
    {
        $terms = LegalDocument::where('slug', 'terms-of-service')->firstOrFail();
        $terms->update([
            'has_draft' => true,
            'draft_title' => 'Terms of Service (Promoted Release)',
            'draft_version' => '1.5.0',
            'draft_content' => "# OFFICIAL PROMOTED RELEASE\n\nLive for all users.",
        ]);

        $publishPayload = [
            'title' => 'Terms of Service (Promoted Release)',
            'version' => '1.5.0',
            'effective_date' => '2026-10-18',
            'summary_of_changes' => 'Promoting draft v1.5.0 to live',
            'summary' => 'Official v1.5.0 live release',
            'content' => "# OFFICIAL PROMOTED RELEASE\n\nLive for all users.",
            'is_published' => '1',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.legal.update', $terms->id), $publishPayload);

        $response->assertRedirect(route('admin.legal.index'));

        $terms->refresh();
        $this->assertEquals('1.5.0', $terms->current_version);
        $this->assertEquals('Terms of Service (Promoted Release)', $terms->title);
        $this->assertFalse($terms->has_draft);
        $this->assertNull($terms->draft_content);

        // Storefront immediately serves promoted release
        $publicRes = $this->get(route('terms'));
        $publicRes->assertStatus(200);
        $publicRes->assertSee('v1.5.0');
        $publicRes->assertSee('OFFICIAL PROMOTED RELEASE');
    }
}

