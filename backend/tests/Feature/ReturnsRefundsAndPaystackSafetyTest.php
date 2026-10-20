<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Models\Shop;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\ReturnAndRefundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnsRefundsAndPaystackSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;
    protected User $otherCustomer;
    protected User $vendorUserA;
    protected User $vendorUserB;
    protected VendorProfile $vendorA;
    protected VendorProfile $vendorB;
    protected Shop $shopA;
    protected Shop $shopB;
    protected Product $productA;
    protected Product $productB;
    protected State $state;
    protected Lga $lgaUyo;
    protected Lga $lgaIkotEkpene;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'currency_symbol' => '₦',
            'is_active' => true,
        ]);

        $this->state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);

        $this->lgaUyo = Lga::create(['state_id' => $this->state->id, 'name' => 'Uyo', 'is_active' => true]);
        $this->lgaIkotEkpene = Lga::create(['state_id' => $this->state->id, 'name' => 'Ikot Ekpene', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Finance Admin',
            'email' => 'admin@victoriousmarket.com.ng',
            'password' => bcrypt('SecretAdmin123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Shopper Grace',
            'email' => 'grace@example.com',
            'password' => bcrypt('GracePass123!'),
            'user_type' => 'customer',
            'phone' => '08011112222',
            'status' => 'active',
        ]);

        $this->otherCustomer = User::create([
            'name' => 'Attacker Bob',
            'email' => 'bob@example.com',
            'password' => bcrypt('BobPass123!'),
            'user_type' => 'customer',
            'phone' => '08033334444',
            'status' => 'active',
        ]);

        // Vendor A
        $this->vendorUserA = User::create([
            'name' => 'Vendor Alpha User',
            'email' => 'alpha@merchant.com',
            'password' => bcrypt('VendorPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendorA = VendorProfile::create([
            'user_id' => $this->vendorUserA->id,
            'business_name' => 'Alpha Gadgets Store',
            'business_phone' => '08077778888',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1000000001',
            'account_name' => 'Alpha Gadgets Store',
            'marketplace_status' => 'active',
        ]);
        $this->shopA = Shop::create([
            'vendor_id' => $this->vendorA->id,
            'country_id' => $country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lgaUyo->id,
            'name' => 'Alpha Flagship Shop',
            'slug' => 'alpha-flagship-shop',
            'address' => '10 Abak Road, Uyo',
            'phone' => '08077778888',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        // Vendor B
        $this->vendorUserB = User::create([
            'name' => 'Vendor Beta User',
            'email' => 'beta@merchant.com',
            'password' => bcrypt('VendorPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendorB = VendorProfile::create([
            'user_id' => $this->vendorUserB->id,
            'business_name' => 'Beta Fashion Hub',
            'business_phone' => '08099991111',
            'bank_name' => 'First Bank',
            'account_number' => '2000000002',
            'account_name' => 'Beta Fashion Hub',
            'marketplace_status' => 'active',
        ]);
        $this->shopB = Shop::create([
            'vendor_id' => $this->vendorB->id,
            'country_id' => $country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lgaIkotEkpene->id,
            'name' => 'Beta Fashion Boutique',
            'slug' => 'beta-fashion-boutique',
            'address' => '5 Market Road, Ikot Ekpene',
            'phone' => '08099991111',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $category = Category::create([
            'name' => 'Consumer Tech',
            'slug' => 'consumer-tech',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'shop_id' => $this->shopA->id,
            'vendor_id' => $this->vendorA->id,
            'category_id' => $category->id,
            'name' => 'Wireless Headset Pro',
            'slug' => 'wireless-headset-pro',
            'sku' => 'WHP-001',
            'unit_price' => '10000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'is_vmarket_flagship' => true,
        ]);

        $this->productB = Product::create([
            'shop_id' => $this->shopB->id,
            'vendor_id' => $this->vendorB->id,
            'category_id' => $category->id,
            'name' => 'Designer Leather Bag',
            'slug' => 'designer-leather-bag',
            'sku' => 'DLB-002',
            'unit_price' => '25000.00',
            'inventory_quantity' => 15,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'is_vmarket_flagship' => true,
        ]);

        SystemSetting::set('refund_window_hours', 24);
    }

    /**
     * Helper to create a delivered child order for testing returns
     */
    protected function createDeliveredOrder(Carbon $deliveredAt, int $quantity = 2): array
    {
        $unitPrice = 10000.00;
        $productTotal = $unitPrice * $quantity;
        $deliveryFee = 1500.00;
        $totalAmount = $productTotal + $deliveryFee;

        $order = Order::create([
            'order_number' => 'ORD-TEST-' . strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'order_type' => 'delivery',
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'merchandise_subtotal' => $productTotal,
            'delivery_fee' => $deliveryFee,
            'cashback_redeemed' => 0.00,
            'grand_total' => $totalAmount,
            'total_amount' => $totalAmount,
            'vendor_net_90' => round($productTotal * 0.90, 2),
            'customer_cashback_5' => round($productTotal * 0.05, 2),
            'platform_margin_5' => round($productTotal * 0.05, 2),
            'logistics_fee_retained' => round($deliveryFee * 0.90, 2),
            'platform_delivery_fee' => round($deliveryFee * 0.10, 2),
            'currency' => 'NGN',
            'origin_lga_id' => $this->lgaUyo->id,
            'destination_lga_id' => $this->lgaUyo->id,
            'paid_at' => $deliveredAt->copy()->subHours(2),
            'received_at' => $deliveredAt,
            'delivered_at' => $deliveredAt,
            'delivery_confirmed_at' => $deliveredAt,
            'refund_window_expires_at' => $deliveredAt->copy()->addHours(24),
            'is_settled' => false,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $productTotal,
        ]);

        // Issue 5% cashback lot in pending_maturation
        $cashbackAmount = round($productTotal * 0.05, 2);
        $cashback = CustomerCashbackLedger::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'amount' => $cashbackAmount,
            'status' => 'pending_maturation',
            'matures_at' => $deliveredAt->copy()->addHours(24),
            'expires_at' => $deliveredAt->copy()->addMonths(6),
            'notes' => '5% reward from Order #' . $order->order_number,
        ]);

        return ['order' => $order, 'item' => $orderItem, 'cashback' => $cashback];
    }

    public function test_customer_can_request_item_level_return_within_24h_window()
    {
        $deliveredAt = Carbon::now()->subHours(3); // within 24h
        $fixture = $this->createDeliveredOrder($deliveredAt, 2);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $this->actingAs($this->customer);

        // Customer submits return for 1 of the 2 units
        $response = $this->postJson('/api/v1/customer/returns/create', [
            'order_id' => $order->id,
            'collection_point_id' => null,
            'return_reason' => 'defective_product',
            'customer_description' => 'Defective power switch on unit',
            'items' => [
                [
                    'order_item_id' => $item->id,
                    'quantity' => 1,
                    'item_condition' => 'damaged_defective',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
        ]);

        $caseNumber = $response->json('data.case_number');
        $this->assertNotNull($caseNumber);

        $returnCase = ReturnCase::where('case_number', $caseNumber)->first();
        $this->assertNotNull($returnCase);
        $this->assertEquals('requested', $returnCase->status);
        $this->assertEquals(10000.00, (float)$returnCase->total_refundable_amount);
        $this->assertNull($returnCase->collection_point_id);
        $this->assertEquals(0, (float)$returnCase->refunded_amount); // Zero funds moved!

        $this->assertDatabaseHas('return_items', [
            'return_case_id' => $returnCase->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
            'line_total' => 10000.00,
            'status' => 'requested',
        ]);
    }

    /**
     * 3. Customer cannot submit return after 24-hour window expires
     */
    public function test_customer_cannot_request_return_after_24h_window_expired()
    {
        $deliveredAt = Carbon::now()->subHours(25); // Exceeded 24 hours!
        $fixture = $this->createDeliveredOrder($deliveredAt, 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $this->actingAs($this->customer);

        $response = $this->postJson('/api/v1/customer/returns/create', [
            'order_id' => $order->id,
            'collection_point_id' => null,
            'return_reason' => 'change_of_mind',
            'customer_description' => 'Changed my mind',
            'items' => [
                [
                    'order_item_id' => $item->id,
                    'quantity' => 1,
                    'item_condition' => 'unopened_sealed',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => false,
            'message' => 'The 24-hour return and refund window for this order has expired.',
        ]);

        $this->assertEquals(0, ReturnCase::count());
    }

    public function test_super_admin_can_open_late_direct_contact_case_with_audit_without_approving_refund(): void
    {
        $fixture = $this->createDeliveredOrder(Carbon::now()->subHours(25), 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $response = $this->actingAs($this->superAdmin)->post(route('admin.returns.open_late'), [
            'order_id' => $order->id,
            'return_reason' => 'defective_product',
            'customer_description' => 'Customer contacted support after discovering a faulty power switch.',
            'override_reason' => 'Customer contacted our support desk within the statutory remedy period; opening for evidence review.',
            'items' => [
                [
                    'order_item_id' => $item->id,
                    'quantity' => 1,
                    'item_condition' => 'damaged_defective',
                ],
            ],
        ]);

        $response->assertRedirect();
        $case = ReturnCase::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('requested', $case->status);
        $this->assertSame('admin_late_override', $case->source);
        $this->assertNull($case->reviewed_by_admin_id);
        $this->assertSame('10000.00', (string) $case->total_refundable_amount);
        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $this->superAdmin->id,
            'action' => 'return_case_opened_after_window_by_admin',
            'target_type' => ReturnCase::class,
            'target_id' => $case->id,
        ]);
    }

    /**
     * 4. Zero IDOR: Customer cannot submit returns for another user's order
     */
    public function test_zero_idor_customer_cannot_return_items_from_another_customer_order()
    {
        $deliveredAt = Carbon::now()->subHours(2);
        $fixture = $this->createDeliveredOrder($deliveredAt, 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        // Attacker Bob attempts to return Grace's order
        $this->actingAs($this->otherCustomer);

        $response = $this->postJson('/api/v1/customer/returns/create', [
            'order_id' => $order->id,
            'collection_point_id' => null,
            'return_reason' => 'defective_product',
            'customer_description' => 'Malicious return attempt',
            'items' => [
                [
                    'order_item_id' => $item->id,
                    'quantity' => 1,
                    'item_condition' => 'damaged_defective',
                ],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertEquals(0, ReturnCase::count());
    }

    /**
     * 5. Quantity validation: Cannot return more items than purchased
     */
    public function test_customer_cannot_return_more_than_purchased_quantity()
    {
        $deliveredAt = Carbon::now()->subHours(2);
        $fixture = $this->createDeliveredOrder($deliveredAt, 2); // Customer bought 2
        $order = $fixture['order'];
        $item = $fixture['item'];

        $this->actingAs($this->customer);

        // Attempt to return 5 units
        $response = $this->postJson('/api/v1/customer/returns/create', [
            'order_id' => $order->id,
            'collection_point_id' => null,
            'return_reason' => 'defective_product',
            'customer_description' => 'Invalid quantity attempt',
            'items' => [
                [
                    'order_item_id' => $item->id,
                    'quantity' => 5,
                    'item_condition' => 'damaged_defective',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Cannot return 5 units of 'Wireless Headset Pro'. Only 2 eligible units remain.",
        ]);
        $this->assertEquals(0, ReturnCase::count());
    }

    /**
     * Manual bank transfers are disabled; customers must receive refunds through Paystack.
     */
    public function test_manual_bank_refund_is_disabled_at_launch_without_posting_ledger_entries(): void
    {
        $deliveredAt = Carbon::now()->subHours(4);
        $fixture = $this->createDeliveredOrder($deliveredAt, 1);
        $order = $fixture['order'];
        $item = $fixture['item'];
        $cashback = $fixture['cashback'];

        $service = app(ReturnAndRefundService::class);
        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'item_condition' => 'damaged_defective',
            ]],
            'defective_product',
            'Headband cracked upon inspection',
            null
        );
        $this->assertTrue($createRes['success']);
        $case = $createRes['case'];

        $refundRes = $service->adminDisburseRefund(
            $this->superAdmin,
            $case,
            'BANK-REF-MUST-NOT-BYPASS-PAYSTACK',
            'storage/refund_proofs/manual-bank-proof.pdf',
            true
        );

        $this->assertFalse($refundRes['success']);
        $this->assertSame('disabled', $refundRes['status']);
        $this->assertStringContainsString('disabled at launch', $refundRes['message']);
        $this->assertSame(0.00, (float)$case->fresh()->refunded_amount);
        $this->assertNull($case->fresh()->refund_reference);
        $this->assertSame(0, FinancialJournalEntry::where('order_id', $order->id)->count());
        $this->assertDatabaseMissing('admin_audit_logs', [
            'action' => 'return_refund_disbursed_manually',
            'target_id' => $case->id,
        ]);
        $this->assertNotSame('forfeited', $cashback->fresh()->status);
    }



    public function test_retry_of_pending_paystack_refund_does_not_post_settlement_journals(): void
    {
        $fixture = $this->createDeliveredOrder(Carbon::now()->subHours(4), 1);
        $order = $fixture['order'];
        $order->update(['payment_reference' => 'PAYSTACK-PENDING-REFUND-' . uniqid()]);

        $case = ReturnCase::create([
            'case_number' => 'RET-PENDING-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'return_reason' => 'defective_product',
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '10000.00',
            'refund_delivery_fee' => false,
            'delivery_fee_refunded' => '0.00',
            'refunded_amount' => '0.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
        ]);

        $paystack = \Mockery::mock(\App\Services\PaystackService::class);
        $paystack->shouldReceive('initiateRefund')
            ->once()
            ->withAnyArgs()
            ->andReturn([
                'success' => true,
                'status' => 'pending',
                'refund_reference' => 'rf_pending_retry_test',
                'data' => ['status' => 'pending', 'reference' => 'rf_pending_retry_test'],
            ]);
        $this->app->instance(\App\Services\PaystackService::class, $paystack);

        $service = app(ReturnAndRefundService::class);
        $first = $service->adminDisburseRefund($this->superAdmin, $case);
        $this->assertTrue($first['success']);
        $this->assertSame('initiated', $first['status']);
        $this->assertSame('rf_pending_retry_test', $case->fresh()->paystack_refund_id);
        $this->assertSame('refund_pending', $case->fresh()->status);

        // A retry must remain pending and must not duplicate or prematurely post
        // the refund journal while the gateway has not confirmed settlement.
        $second = $service->adminDisburseRefund($this->superAdmin, $case->fresh());
        $this->assertTrue($second['success']);
        $this->assertSame('initiated', $second['status']);
        $this->assertSame(0, \App\Models\FinancialJournalEntry::where('order_id', $order->id)->count());
        $this->assertSame('refund_pending', $case->fresh()->status);
        $this->assertSame('initiated', \App\Models\FinancialOperation::where('metadata->return_case_id', $case->id)->value('status'));

        // A wallet credit is also a refund disbursement; it must not bypass the
        // existing Paystack reference and create a second customer payout.
        $walletRetry = $service->adminDisburseRefund($this->superAdmin, $case->fresh(), 'wallet');
        $this->assertFalse($walletRetry['success']);
        $this->assertSame('disabled', $walletRetry['status']);
        $this->assertSame(0, \App\Models\CustomerWalletCredit::where('return_case_id', $case->id)->count());
        $this->assertSame(0, \App\Models\FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    /**
     * 7. Multi-vendor cart: Returns on Vendor A do not affect Vendor B
     */
    public function test_multi_vendor_child_order_returns_are_strictly_isolated()
    {
        $deliveredAt = Carbon::now()->subHours(2);

        // Child Order A (Vendor A)
        $orderA = Order::create([
            'order_number' => 'ORD-CHILD-VEND-A',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'order_type' => 'delivery',
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'merchandise_subtotal' => 10000.00,
            'delivery_fee' => 1500.00,
            'cashback_redeemed' => 0.00,
            'grand_total' => 11500.00,
            'total_amount' => 11500.00,
            'vendor_net_90' => 9000.00,
            'customer_cashback_5' => 500.00,
            'platform_margin_5' => 500.00,
            'logistics_fee_retained' => 1350.00,
            'platform_delivery_fee' => 150.00,
            'currency' => 'NGN',
            'origin_lga_id' => $this->lgaUyo->id,
            'destination_lga_id' => $this->lgaUyo->id,
            'received_at' => $deliveredAt,
            'delivered_at' => $deliveredAt,
            'delivery_confirmed_at' => $deliveredAt,
            'refund_window_expires_at' => $deliveredAt->copy()->addHours(24),
            'is_settled' => false,
        ]);
        $itemA = OrderItem::create([
            'order_id' => $orderA->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'quantity' => 1,
            'unit_price' => 10000.00,
            'line_total' => 10000.00,
        ]);

        // Child Order B (Vendor B)
        $orderB = Order::create([
            'order_number' => 'ORD-CHILD-VEND-B',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorB->id,
            'shop_id' => $this->shopB->id,
            'order_type' => 'delivery',
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'merchandise_subtotal' => 25000.00,
            'delivery_fee' => 2000.00,
            'cashback_redeemed' => 0.00,
            'grand_total' => 27000.00,
            'total_amount' => 27000.00,
            'vendor_net_90' => 22500.00,
            'customer_cashback_5' => 1250.00,
            'platform_margin_5' => 1250.00,
            'logistics_fee_retained' => 1800.00,
            'platform_delivery_fee' => 200.00,
            'currency' => 'NGN',
            'origin_lga_id' => $this->lgaIkotEkpene->id,
            'destination_lga_id' => $this->lgaUyo->id,
            'received_at' => $deliveredAt,
            'delivered_at' => $deliveredAt,
            'delivery_confirmed_at' => $deliveredAt,
            'refund_window_expires_at' => $deliveredAt->copy()->addHours(24),
            'is_settled' => false,
        ]);
        $itemB = OrderItem::create([
            'order_id' => $orderB->id,
            'product_id' => $this->productB->id,
            'product_name' => $this->productB->name,
            'quantity' => 1,
            'unit_price' => 25000.00,
            'line_total' => 25000.00,
        ]);

        $service = app(ReturnAndRefundService::class);

        // Customer files return for Order A ONLY
        $createRes = $service->createReturnCase(
            $this->customer,
            $orderA,
            [
                [
                    'order_item_id' => $itemA->id,
                    'quantity' => 1,
                    'item_condition' => 'damaged_defective',
                ],
            ],
            'defective_product',
            'Vendor A item has issue',
            null
        );
        $this->assertTrue($createRes['success']);
        $caseA = $createRes['case'];

        $manualRefund = $service->adminDisburseRefund($this->superAdmin, $caseA, 'REF-VEND-A-1234');

        // A legacy manual-bank path must not refund a child order or affect siblings.
        $this->assertFalse($manualRefund['success']);
        $this->assertSame('disabled', $manualRefund['status']);
        $this->assertEquals('requested', $caseA->fresh()->status);
        $this->assertSame(0, FinancialJournalEntry::where('order_id', $orderA->id)->count());

        // Verify Vendor B's order is completely untouched
        $this->assertEquals('delivered', $orderB->fresh()->order_status);
        $this->assertEquals(0, ReturnCase::where('order_id', $orderB->id)->count());
        $this->assertEquals(27000.00, (float)$orderB->fresh()->grand_total);
    }
}
