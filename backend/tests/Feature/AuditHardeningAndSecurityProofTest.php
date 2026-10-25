<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerCashbackLedger;
use App\Models\CustomerWalletCredit;
use App\Models\Lga;
use App\Models\LogisticsCollectionPoint;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\DoorstepHandshakeService;
use App\Services\PaystackService;
use App\Services\ReturnAndRefundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditHardeningAndSecurityProofTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;
    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Product $product1;
    protected Product $product2;
    protected User $logisticsUser;
    protected LogisticsCompanyProfile $logisticsCompany;
    protected User $riderUser;
    protected RiderProfile $rider;
    protected LogisticsCollectionPoint $collectionPoint;
    protected Lga $lga;
    protected ReturnAndRefundService $returnService;
    protected DoorstepHandshakeService $doorstepService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'currency_symbol' => '₦',
            'is_active' => true,
        ]);

        $state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);

        $this->lga = Lga::create(['state_id' => $state->id, 'name' => 'Uyo', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Finance Admin',
            'email' => 'admin@victoriousmarket.com.ng',
            'password' => bcrypt('AdminPass123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Shopper Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('JanePass123!'),
            'user_type' => 'customer',
            'phone' => '08011110000',
            'status' => 'active',
        ]);

        $this->vendorUser = User::create([
            'name' => 'Vendor Test User',
            'email' => 'vendor@test.com',
            'password' => bcrypt('VendorPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Test Vendor Electronics',
            'business_phone' => '08077770000',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1000000001',
            'account_name' => 'Test Vendor Store',
            'marketplace_status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->lga->id,
            'name' => 'Test Shop Uyo',
            'slug' => 'test-shop-uyo',
            'address' => '10 Plaza Road, Uyo',
            'phone' => '08077770000',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $this->product1 = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Bluetooth Speaker',
            'slug' => 'bluetooth-speaker',
            'sku' => 'BTS-001',
            'unit_price' => '10000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
        ]);

        $this->product2 = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'slug' => 'wireless-mouse',
            'sku' => 'WM-002',
            'unit_price' => '5000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
        ]);

        $this->logisticsUser = User::create([
            'name' => 'Fleet Logistics Manager',
            'email' => 'fleet@delivery.com',
            'password' => bcrypt('FleetPass123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->logisticsCompany = LogisticsCompanyProfile::create([
            'user_id' => $this->logisticsUser->id,
            'company_name' => 'Swift Courier Ltd',
            'company_phone' => '08099990000',
            'fleet_type' => 'motorcycle_and_van',
            'verification_status' => 'verified',
            'status' => 'active',
            'bank_name' => 'Access Bank',
            'account_number' => '0123456789',
            'account_name' => 'Swift Courier Ltd',
        ]);

        $this->riderUser = User::create([
            'name' => 'Rider John',
            'email' => 'rider@delivery.com',
            'password' => bcrypt('RiderPass123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $this->rider = RiderProfile::create([
            'user_id' => $this->riderUser->id,
            'logistics_company_id' => $this->logisticsCompany->id,
            'state_id' => $state->id,
            'lga_id' => $this->lga->id,
            'rider_code' => 'RIDER-001',
            'phone' => '08055550000',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->collectionPoint = LogisticsCollectionPoint::create([
            'logistics_company_id' => $this->logisticsCompany->id,
            'state_id' => $state->id,
            'lga_id' => $this->lga->id,
            'name' => 'Uyo Drop-off Hub',
            'code' => 'CP-UYO-TEST',
            'address' => 'Plaza Center, Uyo',
            'contact_phone' => '08055550000',
            'contact_person' => 'Hub Manager',
            'operating_hours' => '08:00 AM - 06:00 PM',
            'is_active' => true,
        ]);

        $this->returnService = app(ReturnAndRefundService::class);
        $this->doorstepService = app(DoorstepHandshakeService::class);
    }

    protected function buildBaseOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-TEST-' . bin2hex(random_bytes(4)),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'verification_code' => '654321',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '11000.00',
            'total_amount' => '11000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
        ], $attributes));
    }

    /**
     * Finding 1: Return cannot be requested before order is delivered
     */
    public function test_cannot_request_return_for_confirmed_or_undelivered_order(): void
    {
        $order = $this->buildBaseOrder([
            'order_status' => 'confirmed',
            'status' => 'confirmed',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        $result = $this->returnService->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Order cannot be returned (current status: confirmed)', $result['message']);
    }

    /**
     * Finding 2: Duplicate order_item_id in return payload cannot overstate quantity
     */
    public function test_duplicate_order_item_ids_in_return_payload_are_aggregated(): void
    {
        $deliveredAt = Carbon::now()->subHours(2);
        $order = $this->buildBaseOrder([
            'order_status' => 'delivered',
            'delivered_at' => $deliveredAt,
            'received_at' => $deliveredAt,
            'refund_window_expires_at' => $deliveredAt->copy()->addHours(24),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'quantity' => 1, // Only 1 unit purchased
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        // Attempt attack: send 2 separate lines each asking to return 1 unit (total 2 units)
        $result = $this->returnService->createReturnCase(
            $this->customer,
            $order,
            [
                ['order_item_id' => $item->id, 'quantity' => 1],
                ['order_item_id' => $item->id, 'quantity' => 1],
            ],
            'defective_product',
            null,
            null,
            ['evidence/photo.jpg']
        );


        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cannot return 2 units', $result['message']);
        $this->assertStringContainsString('Only 1 eligible units remain', $result['message']);
    }

    /**
     * Finding 4: Hub inspection cannot re-inspect or reset an already refunded case
     */
    public function test_hub_inspection_cannot_reset_already_refunded_case(): void
    {
        $deliveredAt = Carbon::now()->subHours(2);
        $order = $this->buildBaseOrder([
            'order_status' => 'delivered',
            'delivered_at' => $deliveredAt,
            'received_at' => $deliveredAt,
            'refund_window_expires_at' => $deliveredAt->copy()->addHours(24),
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => ReturnCase::generateCaseNumber(),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'collection_point_id' => $this->collectionPoint->id,
            'return_reason' => 'defective_product',
            'status' => 'refunded',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '10000.00',
            'refunded_amount' => '10000.00',
            'refunded_at' => Carbon::now(),
        ]);

        $result = $this->returnService->recordHubInspection(
            $this->superAdmin,
            $returnCase,
            $this->collectionPoint->id,
            'good_working_condition',
            'SN-12345678',
            true,
            'Attempting re-inspection on refunded case',
            'accept_for_refund'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString("cannot be inspected because it is already in 'refunded' status", $result['message']);
        $this->assertEquals('refunded', $returnCase->fresh()->status);
    }

    /**
     * Finding 5: All doorstep wallet-credit lots are reversed upon confirmed Paystack cash refund
     */
    public function test_all_doorstep_wallet_credit_lots_are_reversed_after_confirmed_cash_refund(): void
    {
        $order = $this->buildBaseOrder([
            'order_status' => 'partially_delivered',
            'status' => 'partially_delivered',
            'payment_status' => 'paid',
            'payment_reference' => 'ref_paystack_test_123',
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => ReturnCase::generateCaseNumber(),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'defective_product',
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '10000.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
        ]);

        // Customer was granted doorstep instant wallet credit
        $credit = CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'return_case_id' => $returnCase->id,
            'amount' => '6000.00',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'available',
        ]);
        $pendingCredit = CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'return_case_id' => $returnCase->id,
            'amount' => '4000.00',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'pending_approval',
        ]);

        $this->assertEquals('6000.00', CustomerWalletCredit::getAvailableBalance($this->customer->id));

        $paystack = \Mockery::mock(\App\Services\PaystackService::class);
        $paystack->shouldReceive('initiateRefund')
            ->once()
            ->andReturn([
                'success' => true,
                'status' => 'completed',
                'refund_reference' => 'rf_test_wallet_rev',
                'data' => ['status' => 'completed', 'reference' => 'rf_test_wallet_rev'],
            ]);
        $this->app->instance(\App\Services\PaystackService::class, $paystack);

        $service = app(ReturnAndRefundService::class);
        $result = $service->adminDisburseRefund(
            $this->superAdmin,
            $returnCase
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('reversed_for_cash_refund', $credit->fresh()->status);
        $this->assertEquals('reversed_for_cash_refund', $pendingCredit->fresh()->status);
        $this->assertEquals('0.00', CustomerWalletCredit::getAvailableBalance($this->customer->id));
    }

    /**
     * Finding 6 & 7: Doorstep manifest rejects duplicate item IDs and omitted items
     */
    public function test_doorstep_manifest_rejects_duplicates_and_omissions(): void
    {
        $order = $this->buildBaseOrder([
            'logistics_company_id' => $this->logisticsCompany->id,
            'rider_id' => $this->rider->id,
            'order_status' => 'out_for_delivery',
            'verification_code' => '654321',
            'merchandise_subtotal' => '15000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '16500.00',
            'vendor_net_90' => '13500.00',
            'customer_cashback_5' => '750.00',
            'platform_margin_5' => '750.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
        ]);

        $item1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        $item2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product2->id,
            'product_name' => $this->product2->name,
            'quantity' => 1,
            'unit_price' => '5000.00',
            'line_total' => '5000.00',
        ]);

        // Test Omission: manifest only supplies item1, completely omitting item2
        $omittedResult = $this->doorstepService->processDoorstepHandover(
            $order,
            $this->rider,
            [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
            ],
            '654321',
            'proofs/doorstep.jpg'
        );

        $this->assertFalse($omittedResult['success']);
        $this->assertStringContainsString('Delivery manifest is incomplete', $omittedResult['message']);

        // Test Duplicate Line: manifest supplies item1 twice
        $duplicateResult = $this->doorstepService->processDoorstepHandover(
            $order,
            $this->rider,
            [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item1->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
            ],
            '654321',
            'proofs/doorstep.jpg'
        );

        $this->assertFalse($duplicateResult['success']);
        $this->assertStringContainsString('Duplicate order_item_id', $duplicateResult['message']);
    }

    /**
     * Finding 8: Return calculation accounts for allocated discounts
     */
    public function test_return_calculation_accounts_for_allocated_discount(): void
    {
        $deliveredAt = Carbon::now()->subHours(1);
        $order = $this->buildBaseOrder([
            'order_status' => 'delivered',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'cashback_redeemed' => '2000.00',
            'grand_total' => '9000.00',
            'total_amount' => '9000.00',
            'delivered_at' => $deliveredAt,
            'received_at' => $deliveredAt,
            'refund_window_expires_at' => $deliveredAt->copy()->addHours(24),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        $result = $this->returnService->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            null,
            null,
            ['evidence/photo.jpg']
        );


        $this->assertTrue($result['success']);
        $case = $result['case'];
        $this->assertEquals('8000.00', $case->total_refundable_amount);

        $returnItem = ReturnItem::where('return_case_id', $case->id)->first();
        $this->assertEquals(0, bccomp((string)$returnItem->allocated_discount, '2000.00', 2));
        $this->assertEquals(0, bccomp((string)$returnItem->refundable_amount, '8000.00', 2));
    }
}
