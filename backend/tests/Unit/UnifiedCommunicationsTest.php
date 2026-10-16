<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\UnifiedCommunicationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnifiedCommunicationsTest extends TestCase
{
    use RefreshDatabase;

    protected UnifiedCommunicationsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnifiedCommunicationsService();
        putenv('TERMII_API_KEY=test_termii_key');
    }

    public function test_nigerian_phone_number_formatting(): void
    {
        $this->assertEquals('2348031234567', UnifiedCommunicationsService::formatNigerianPhone('08031234567'));
        $this->assertEquals('2348031234567', UnifiedCommunicationsService::formatNigerianPhone('+2348031234567'));
        $this->assertEquals('2348031234567', UnifiedCommunicationsService::formatNigerianPhone('2348031234567'));
        $this->assertEquals('2348031234567', UnifiedCommunicationsService::formatNigerianPhone('8031234567'));
    }

    public function test_sms_sequential_failover_stops_at_first_success(): void
    {
        // Configure SmartSMS in Admin SystemSettings
        SystemSetting::set('sms_active_gateway', 'smart_sms', 'sms_config');
        SystemSetting::set('sms_gateways', [
            'smart_sms' => [
                'token' => 'smart_test_token',
                'sender_id' => 'VMARKET',
                'is_active' => true,
            ],
            'ebulksms' => [
                'username' => 'ebulk_user',
                'api_key' => 'ebulk_key',
                'is_active' => true,
            ],
        ], 'sms_config');

        // Mock Termii failing with 500, SmartSMS succeeding with 200
        Http::fake([
            'https://api.ng.termii.com/*' => Http::response(['message' => 'Internal Server Error'], 500),
            'https://app.smartsmssolutions.com/*' => Http::response(['code' => '1000', 'message_id' => 'smart_12345'], 200),
            'https://api.ebulksms.com/*' => Http::response(['status' => 'SUCCESS'], 200),
        ]);

        $result = $this->service->sendSmsWithFailover('08031234567', 'Your OTP is 123456');

        $this->assertTrue($result['success']);
        $this->assertEquals('smart_sms', $result['provider']);

        // Verify failover history recorded Termii's failure
        $this->assertCount(1, $result['failover_history']);
        $this->assertEquals('termii', $result['failover_history'][0]['provider']);

        // Mathematical proof: EbulkSMS was NEVER contacted because SmartSMS succeeded
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'smartsmssolutions.com');
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'ebulksms.com');
        });
    }
}