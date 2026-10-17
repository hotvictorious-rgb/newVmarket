<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\NotificationDeliveryLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Models\VendorProfile;
use App\Services\FirebaseCloudMessagingService;
use App\Services\NotificationDispatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiChannelNotificationAndFirebaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $riderUser;
    protected User $customer;
    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Product $product;
    protected RiderProfile $rider;
    protected LogisticsCompanyProfile $company;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name' => 'Nigeria', 'iso_code' => 'NG', 'phone_code' => '234']);
        $state = State::create(['country_id' => $country->id, 'name' => 'Akwa Ibom']);
        $lga = Lga::create(['state_id' => $state->id, 'name' => 'Uyo']);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@victoriousmarket.com',
            'phone' => '08000000099',
            'password' => bcrypt('password'),
            'user_type' => 'super_admin',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->riderUser = User::create([
            'name' => 'Speedy Rider',
            'email' => 'rider@speedy.com',
            'phone' => '08012345678',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'is_active' => true,
        ]);

        $this->customer = User::create([
            'name' => 'Victor Customer',
            'email' => 'customer@victoriousmarket.com',
            'phone' => '08098765432',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->vendorUser = User::create([
            'name' => 'Merchant Owner',
            'email' => 'vendor@shop.com',
            'phone' => '08055555555',
            'password' => bcrypt('password'),
            'user_type' => 'vendor',
            'is_active' => true,
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Alpha Plaza Store',
            'business_phone' => '08055555555',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Alpha Shop 1',
            'slug' => 'alpha-shop-1',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $lga->id,
            'address' => 'Plaza Complex, Uyo',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Victorious Power Bank 20000mAh',
            'slug' => 'power-bank-20k',
            'unit_price' => '15000.00',
            'stock_quantity' => 20,
            'status' => 'published',
            'is_active' => true,
            'condition' => 'brand_new',
            'package_tier' => 'bike_standard',
        ]);

        $fleetUser = User::create([
            'name' => 'Logistics HQ',
            'email' => 'fleet@speedy.com',
            'phone' => '08077777777',
            'password' => bcrypt('password'),
            'user_type' => 'logistics_company',
            'is_active' => true,
        ]);

        $this->company = LogisticsCompanyProfile::create([
            'user_id' => $fleetUser->id,
            'company_name' => 'Speedy Logistics',
            'registration_number' => 'RC-998877',
            'contact_email' => 'fleet@speedy.com',
            'contact_phone' => '08077777777',
            'hq_address' => '10 Logistics Way, Uyo',
            'lga_id' => $lga->id,
            'status' => 'active',
            'wallet_balance' => '50000.00',
        ]);

        $this->rider = RiderProfile::create([
            'user_id' => $this->riderUser->id,
            'logistics_company_id' => $this->company->id,
            'rider_id_number' => 'RD-001',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_number' => 'UYO-123-XY',
            'status' => 'active',
            'is_online' => true,
        ]);
    }

    /**
     * Test 1: User can register FCM device token via API
     */
    public function test_user_can_register_fcm_device_token(): void
    {
        Sanctum::actingAs($this->riderUser);

        $response = $this->postJson('/api/v1/notifications/device-token', [
            'device_token' => 'fcm_sample_token_rider_terminal_xyz123',
            'device_type' => 'android',
            'app_type' => 'rider',
            'device_name' => 'Samsung Galaxy A54',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'device_type' => 'android',
                'app_type' => 'rider',
                'is_active' => true,
            ],
        ]);

        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->riderUser->id,
            'device_token' => 'fcm_sample_token_rider_terminal_xyz123',
            'device_type' => 'android',
            'app_type' => 'rider',
            'is_active' => true,
        ]);
    }

    /**
     * Test 2: User can revoke device token on logout
     */
    public function test_user_can_revoke_device_token_on_logout(): void
    {
        UserDeviceToken::create([
            'user_id' => $this->riderUser->id,
            'device_token' => 'token_to_revoke_999',
            'device_type' => 'android',
            'app_type' => 'rider',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->riderUser);

        $response = $this->deleteJson('/api/v1/notifications/device-token', [
            'device_token' => 'token_to_revoke_999',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->riderUser->id,
            'device_token' => 'token_to_revoke_999',
            'is_active' => false,
        ]);
    }

    /**
     * Test 3: Rider can register device token via rider prefix alias
     */
    public function test_rider_can_register_token_via_rider_alias(): void
    {
        Sanctum::actingAs($this->riderUser);

        $response = $this->postJson('/api/v1/rider/device-token', [
            'device_token' => 'token_via_rider_alias_777',
            'device_type' => 'android',
            'app_type' => 'rider',
            'device_name' => 'Tecno Spark 10',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->riderUser->id,
            'device_token' => 'token_via_rider_alias_777',
            'app_type' => 'rider',
        ]);
    }

    /**
     * Test 4: In-app notifications listing and mark as read
     */
    public function test_in_app_notifications_list_and_mark_read(): void
    {
        $notif1 = SystemNotification::create([
            'user_id' => $this->customer->id,
            'title' => 'Order Dispatched',
            'body' => 'Your package is on the way.',
            'type' => 'order_dispatched',
        ]);

        $notif2 = SystemNotification::create([
            'user_id' => $this->customer->id,
            'title' => 'Cashback Received',
            'body' => 'You earned ₦500 cashback.',
            'type' => 'cashback_earned',
        ]);

        Sanctum::actingAs($this->customer);

        $listRes = $this->getJson('/api/v1/notifications/in-app');
        $listRes->assertStatus(200);
        $listRes->assertJson([
            'status' => true,
            'unread_count' => 2,
        ]);

        // Mark single notification as read
        $readRes = $this->postJson("/api/v1/notifications/mark-read/{$notif1->id}");
        $readRes->assertStatus(200);

        $this->assertNotNull($notif1->fresh()->read_at);
        $this->assertNull($notif2->fresh()->read_at);

        // Mark all as read
        $readAllRes = $this->postJson('/api/v1/notifications/mark-read');
        $readAllRes->assertStatus(200);
        $this->assertNotNull($notif2->fresh()->read_at);
    }

    /**
     * Test 5: Super admin notification settings page renders with metrics
     */
    public function test_admin_notification_settings_page_renders(): void
    {
        UserDeviceToken::create([
            'user_id' => $this->riderUser->id,
            'device_token' => 'sample_token_for_metrics',
            'device_type' => 'android',
            'app_type' => 'rider',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->get('/admin/notifications');

        $response->assertStatus(200);
        $response->assertSee('Multi-Channel Notification &amp; Firebase Control Tower', false);
        $response->assertSee('Master Channels &amp; Gateway Credentials', false);
        $response->assertSee('Event-by-Event Multi-Channel Routing Matrix', false);
        $response->assertSee('FCM Push Diagnostic &amp; Live Terminal Tester', false);
    }

    /**
     * Test 6: Super admin can update notification settings and event routing matrix
     */
    public function test_admin_can_update_notification_settings(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/admin/notifications/update', [
            'fcm_enabled' => '1',
            'inapp_enabled' => '1',
            // SMS intentionally unchecked
            'firebase_project_id' => 'victorious-prod-99',
            'firebase_client_email' => 'admin@victorious-prod-99.iam.gserviceaccount.com',
            'matrix' => [
                'order_placed' => ['fcm' => '1', 'in_app' => '1'], // sms disabled
                'doorstep_arrived' => ['fcm' => '1', 'sms' => '1', 'in_app' => '1'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertTrue((bool)SystemSetting::get('notification_fcm_enabled'));
        $this->assertFalse((bool)SystemSetting::get('notification_sms_enabled'));
        $this->assertEquals('victorious-prod-99', SystemSetting::get('firebase_project_id'));

        $matrix = SystemSetting::get('notification_event_matrix');
        $this->assertTrue($matrix['order_placed']['fcm']);
        $this->assertFalse($matrix['order_placed']['sms']);
    }

    /**
     * Test 7: Super admin can fire live test push notification
     */
    public function test_admin_can_fire_test_push_notification(): void
    {
        SystemSetting::set('firebase_project_id', '');
        SystemSetting::set('notification_fcm_enabled', true);
        UserDeviceToken::create([
            'user_id' => $this->superAdmin->id,
            'device_token' => 'admin_phone_token_888',
            'device_type' => 'android',
            'app_type' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->post('/admin/notifications/test-push', [
            'target_type' => 'user',
            'target_id' => $this->superAdmin->id,
            'title' => 'Test Sound Ping',
            'body' => 'Checking audio and heads-up delivery.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('notification_delivery_logs', [
            'channel' => 'fcm',
            'recipient' => 'admin_phone_token_888',
            'event_type' => 'admin_test',
            'title' => 'Test Sound Ping',
            'status' => 'delivered',
        ]);
    }

    /**
     * Test 8: Notification dispatch respects admin event routing matrix
     */
    public function test_notification_dispatch_respects_admin_channel_matrix(): void
    {
        // Configure matrix: order_placed has in_app enabled, but sms disabled
        SystemSetting::set('notification_sms_enabled', true);
        SystemSetting::set('notification_inapp_enabled', true);
        SystemSetting::set('notification_event_matrix', [
            'order_placed' => ['fcm' => true, 'sms' => false, 'in_app' => true],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-NOTIF-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'grand_total' => '15000.00',
            'total_amount' => '15000.00',
            'merchandise_subtotal' => '15000.00',
            'delivery_fee' => '1000.00',
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->shop->lga_id,
            'vendor_net_90' => '13500.00',
            'customer_cashback_5' => '750.00',
            'platform_margin_5' => '750.00',
        ]);

        $dispatches = NotificationDispatchService::dispatchOrderPlaced($order);

        // SMS was disabled in matrix for order_placed: should have zero SMS recorded
        $this->assertEmpty($dispatches);

        // In-App was enabled: SystemNotification must exist for customer
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $this->customer->id,
            'type' => 'order_placed',
        ]);
    }

    public function test_admin_can_configure_termii_sms_settings(): void
    {
        $payload = [
            'fcm_enabled' => '1',
            'sms_enabled' => '1',
            'in_app_enabled' => '1',
            'termii_api_key' => 'live_termii_key_abc123',
            'termii_sender_id' => 'VMARKET_TEST',
            'termii_channel' => 'generic',
            'firebase_project_id' => 'victorious-live',
        ];

        $response = $this->from('/admin/notifications')
            ->actingAs($this->superAdmin)
            ->post('/admin/notifications/update', $payload);

        $response->assertRedirect('/admin/notifications');
        $response->assertSessionHas('status');

        $this->assertEquals('live_termii_key_abc123', \App\Models\SystemSetting::get('termii_api_key'));
        $this->assertEquals('VMARKET_TEST', \App\Models\SystemSetting::get('termii_sender_id'));
        $this->assertEquals('generic', \App\Models\SystemSetting::get('termii_channel'));
    }

    public function test_admin_can_upload_service_account_json_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $serviceAccountData = [
            'type' => 'service_account',
            'project_id' => 'victorious-market-prod',
            'client_email' => 'firebase-adminsdk@victorious-market-prod.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDTestKey\n-----END PRIVATE KEY-----\n",
        ];

        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'service-account.json',
            json_encode($serviceAccountData)
        );

        $response = $this->from('/admin/notifications')
            ->actingAs($this->superAdmin)
            ->post('/admin/notifications/update', [
                'fcm_enabled' => '1',
                'firebase_service_account_json' => $uploadedFile,
            ]);

        $response->assertRedirect('/admin/notifications');
        $this->assertEquals('victorious-market-prod', \App\Models\SystemSetting::get('firebase_project_id'));
        $this->assertEquals('firebase-adminsdk@victorious-market-prod.iam.gserviceaccount.com', \App\Models\SystemSetting::get('firebase_client_email'));
        $this->assertStringContainsString('MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDTestKey', \App\Models\SystemSetting::get('firebase_private_key'));
    }

    public function test_admin_can_configure_custom_templates_and_render_dynamic_tags(): void
    {
        $payload = [
            'templates' => [
                'doorstep_return_initiated' => [
                    'customer' => [
                        'title' => 'Return #{order_number} Initiated',
                        'body' => 'Your return for order #{order_number} worth NGN {amount} is being processed.',
                    ],
                ],
            ],
        ];

        $response = $this->from('/admin/notifications')
            ->actingAs($this->superAdmin)
            ->post('/admin/notifications/update', $payload);
        $response->assertRedirect('/admin/notifications');

        $title = NotificationDispatchService::renderTemplate(
            'doorstep_return_initiated',
            'customer',
            'title',
            ['order_number' => 'ORD-TEST-777', 'amount' => '25,000.00'],
            'Default Title'
        );

        $body = NotificationDispatchService::renderTemplate(
            'doorstep_return_initiated',
            'customer',
            'body',
            ['order_number' => 'ORD-TEST-777', 'amount' => '25,000.00'],
            'Default Body'
        );

        $this->assertEquals('Return #ORD-TEST-777 Initiated', $title);
        $this->assertEquals('Your return for order #ORD-TEST-777 worth NGN 25,000.00 is being processed.', $body);
    }

    public function test_admin_can_revoke_and_prune_device_tokens(): void
    {
        $tokenToRevoke = \App\Models\UserDeviceToken::create([
            'user_id' => $this->customer->id,
            'device_token' => 'fcm_token_to_revoke_123',
            'device_type' => 'android',
            'app_type' => 'customer',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $staleInactiveToken = \App\Models\UserDeviceToken::create([
            'user_id' => $this->customer->id,
            'device_token' => 'fcm_token_stale_old',
            'device_type' => 'ios',
            'app_type' => 'customer',
            'is_active' => false,
            'last_seen_at' => now()->subDays(60),
        ]);

        $activeToken = \App\Models\UserDeviceToken::create([
            'user_id' => $this->customer->id,
            'device_token' => 'fcm_token_healthy_active_999',
            'device_type' => 'android',
            'app_type' => 'customer',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        // 1. Admin revokes tokenToRevoke
        $revokeRes = $this->from('/admin/notifications')
            ->actingAs($this->superAdmin)
            ->post("/admin/notifications/revoke-token/{$tokenToRevoke->id}");
        $revokeRes->assertRedirect('/admin/notifications');
        $this->assertFalse((bool)$tokenToRevoke->fresh()->is_active);

        // 2. Admin prunes inactive/decommissioned tokens
        $pruneRes = $this->from('/admin/notifications')
            ->actingAs($this->superAdmin)
            ->post('/admin/notifications/prune-tokens');
        $pruneRes->assertRedirect('/admin/notifications');

        // Decommissioned/inactive tokens are pruned
        $this->assertDatabaseMissing('user_device_tokens', ['id' => $staleInactiveToken->id]);
        $this->assertDatabaseMissing('user_device_tokens', ['id' => $tokenToRevoke->id]);

        // Healthy active token remains in registry
        $this->assertDatabaseHas('user_device_tokens', ['id' => $activeToken->id]);
    }

    public function test_admin_can_send_test_sms(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.ng.termii.com/*' => \Illuminate\Support\Facades\Http::response([
                'message_id' => 'MSG-TERMII-12345',
                'message' => 'Successfully Sent',
                'balance' => 450.50,
            ], 200),
        ]);

        \App\Models\SystemSetting::set('termii_api_key', 'live_termii_key_123');
        \App\Models\SystemSetting::set('termii_sender_id', 'VMARKET');

        $response = $this->from('/admin/notifications')
            ->actingAs($this->superAdmin)
            ->post('/admin/notifications/test-sms', [
                'recipient' => '+2348012345678',
                'message' => 'Test SMS dispatch from Super Admin Portal',
            ]);

        $response->assertRedirect('/admin/notifications');
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('notification_delivery_logs', [
            'channel' => 'sms',
            'recipient' => '+2348012345678',
            'status' => 'delivered',
        ]);
    }

    public function test_payout_and_return_events_dispatch_notifications(): void
    {
        // 1. Customer Wallet Credited
        NotificationDispatchService::dispatchCustomerWalletCredited($this->customer, '5000.00', 'Doorstep return refund');
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $this->customer->id,
            'type' => 'wallet_credit',
        ]);

        // 2. Vendor Payout Requested
        $vendorPayout = \App\Models\VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '45000.00',
            'status' => 'pending',
            'bank_name' => 'Access Bank',
            'account_number' => '0123456789',
            'account_name' => 'Victorious Vendor Store',
        ]);

        NotificationDispatchService::dispatchPayoutRequested($vendorPayout);
        // Alert sent to merchant owner user
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $this->vendorUser->id,
            'type' => 'payout_requested',
        ]);

        // 3. Vendor Payout Disbursed
        NotificationDispatchService::dispatchPayoutDisbursed($vendorPayout);
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $this->vendorUser->id,
            'type' => 'payout_disbursed',
        ]);

        // 4. Vendor Payout Rejected
        NotificationDispatchService::dispatchPayoutRejected($vendorPayout, 'Invalid account number');
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $this->vendorUser->id,
            'type' => 'payout_rejected',
        ]);
    }

    /**
     * Security Test: Active branch counter staff receives notifications strictly for their assigned shop
     * Counter staff of Shop B must NEVER receive orders or OTPs for Shop A.
     */
    public function test_branch_counter_employee_receives_order_notification_scoped_strictly_to_assigned_shop(): void
    {
        // Create Shop B
        $shopB = Shop::create([
            'vendor_id' => $this->vendor->id,
            'country_id' => $this->shop->country_id,
            'state_id' => $this->shop->state_id,
            'name' => 'Shop Branch B (Eket)',
            'slug' => 'shop-branch-b-eket',
            'address' => '45 Eket Road',
            'lga_id' => $this->shop->lga_id,
            'status' => 'approved',
            'is_active' => true,
        ]);

        // Create Counter Employee A assigned to Shop A
        $staffUserA = User::create([
            'name' => 'Counter Staff A',
            'email' => 'counterA@vmarket.ng',
            'phone' => '08011223344',
            'password' => bcrypt('password'),
            'user_type' => 'vendor_employee',
            'status' => 'active',
        ]);
        \App\Models\VendorEmployeeProfile::create([
            'user_id' => $staffUserA->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'role' => 'inspection_staff',
            'is_active' => true,
        ]);

        // Create Counter Employee B assigned to Shop B
        $staffUserB = User::create([
            'name' => 'Counter Staff B',
            'email' => 'counterB@vmarket.ng',
            'phone' => '08022334455',
            'password' => bcrypt('password'),
            'user_type' => 'vendor_employee',
            'status' => 'active',
        ]);
        \App\Models\VendorEmployeeProfile::create([
            'user_id' => $staffUserB->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $shopB->id,
            'role' => 'inspection_staff',
            'is_active' => true,
        ]);

        // Create Order for Shop A
        $orderA = Order::create([
            'order_number' => 'VM-SEC-SHOPA-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_status' => 'processing',
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_type' => 'doorstep',
            'pickup_verification_code' => '998877',
            'verification_code' => '112233',
            'subtotal' => '15000.00',
            'total_amount' => '16000.00',
            'merchandise_subtotal' => '15000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '16000.00',
            'vendor_net_90' => '13500.00',
            'customer_cashback_5' => '750.00',
            'platform_margin_5' => '750.00',
        ]);

        NotificationDispatchService::dispatchOrderPlaced($orderA);
        NotificationDispatchService::dispatchVendorHandoverReady($orderA);

        // Assert Staff A received in-app notification
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $staffUserA->id,
            'type' => 'order_placed',
        ]);
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $staffUserA->id,
            'type' => 'vendor_handover_ready',
        ]);

        // Strict Boundary Assertion: Staff B of Shop B must NEVER receive notifications for Shop A
        $this->assertDatabaseMissing('system_notifications', [
            'user_id' => $staffUserB->id,
            'type' => 'order_placed',
        ]);
        $this->assertDatabaseMissing('system_notifications', [
            'user_id' => $staffUserB->id,
            'type' => 'vendor_handover_ready',
        ]);
    }

    /**
     * Security Test: Admin employees receive dispute and return alerts alongside Super Admin
     */
    public function test_admin_employee_receives_dispute_and_return_notifications(): void
    {
        $adminStaff = User::create([
            'name' => 'Moderation Desk Staff',
            'email' => 'moderator@vmarket.ng',
            'phone' => '08099881122',
            'password' => bcrypt('password'),
            'user_type' => 'admin_employee',
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'VM-SEC-DISP-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_status' => 'delivered',
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_type' => 'doorstep',
            'subtotal' => '20000.00',
            'total_amount' => '21200.00',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1200.00',
            'grand_total' => '21200.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
        ]);

        $case = \App\Models\ReturnCase::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'case_number' => 'RET-SEC-001',
            'return_reason' => 'defective_product',
            'status' => 'vendor_disputed',
            'rejection_category' => 'defective_seal',
            'vendor_return_otp' => '445566',
            'total_refundable_amount' => '20000.00',
        ]);

        NotificationDispatchService::dispatchVendorCounterDispute($case, 'Item was altered by buyer');

        // Both Super Admin and Admin Staff must receive alert
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $this->superAdmin->id,
            'type' => 'dispute_escalated',
        ]);
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $adminStaff->id,
            'type' => 'dispute_escalated',
        ]);
    }

    /**
     * Security Test: Role affinity protection prevents customers from registering as admin terminal
     */
    public function test_customer_cannot_register_device_token_as_admin(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/notifications/device-token', [
            'device_token' => 'fcm_malicious_admin_spoof',
            'device_type' => 'android',
            'app_type' => 'admin',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => false,
            'message' => 'Unauthorized device role binding for admin terminal.',
        ]);
    }

    /**
     * API Test: In-app notifications endpoint returns both 'notifications' and 'data' keys for multi-app parity
     */
    public function test_in_app_notifications_returns_both_keys(): void
    {
        SystemNotification::create([
            'user_id' => $this->customer->id,
            'title' => 'Test Notification',
            'body' => 'Testing mobile parity',
            'type' => 'general',
        ]);

        Sanctum::actingAs($this->customer);

        $response = $this->getJson('/api/v1/notifications/in-app');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'unread_count',
            'notifications',
            'data',
        ]);
    }
}