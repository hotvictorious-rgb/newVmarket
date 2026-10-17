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
}
