<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialOperation;
use App\Models\ProcessedWebhookEvent;
use App\Services\PaystackService;
use App\Services\FinancialOperationService;
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
use Laravel\Sanctum\Sanctum;
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
        $this->assertEquals(10000.00, (float) $case->total_refundable_amount);
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



    public function test_settled_vendor_is_not_debited_for_refund_without_authorised_fault_ruling(): void
    {
        $fixture = $this->createDeliveredOrder(Carbon::now()->subHours(4), 1);
        $order = $fixture['order'];
        $order->update([
            'vendor_settlement_status' => 'settled',
            'payment_reference' => 'REFUND-LOSS-TEST-' . strtoupper(uniqid()),
        ]);

        $case = ReturnCase::create([
            'case_number' => 'RET-LOSS-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => null,
            'return_reason' => 'defective_product',
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '10000.00',
            'refund_delivery_fee' => false,
            'delivery_fee_refunded' => '0.00',
            'refunded_amount' => '0.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
            // No admin_ruling/cost_allocation: approval to refund is not a vendor-fault ruling.
        ]);

        $paystack = \Mockery::mock(\App\Services\PaystackService::class);
        $paystack->shouldReceive('initiateRefund')
            ->once()
            ->withAnyArgs()
            ->andReturn([
                'success' => true,
                'status' => 'completed',
                'refund_reference' => 'rf_platform_loss_no_fault',
                'data' => ['status' => 'completed', 'reference' => 'rf_platform_loss_no_fault'],
            ]);
        $this->app->instance(\App\Services\PaystackService::class, $paystack);

        $result = app(ReturnAndRefundService::class)->adminDisburseRefund($this->superAdmin, $case);

        $this->assertTrue($result['success']);
        $this->assertSame('refunded', $case->fresh()->status);
        $this->assertSame(0, \App\Models\VendorRecoveryLedger::where('return_case_id', $case->id)->count());
        $this->assertSame(0, FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'vendor_recovery_receivable')->count());
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'platform_refund_loss_pending_mapping',
            'entry_type' => 'debit',
            'amount' => '9000.00',
        ]);

        $debits = (float) FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'debit')->sum('amount');
        $credits = (float) FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'credit')->sum('amount');
        $this->assertEquals($credits, $debits, 'The platform-funded refund must remain balanced without creating vendor debt.');
    }

    public function test_settled_vendor_recovery_is_posted_only_after_persisted_fault_ruling_and_refund(): void
    {
        $fixture = $this->createDeliveredOrder(Carbon::now()->subHours(4), 1);
        $order = $fixture['order'];
        $order->update([
            'vendor_settlement_status' => 'settled',
            'payment_reference' => 'REFUND-FAULT-TEST-' . strtoupper(uniqid()),
        ]);

        $case = ReturnCase::create([
            'case_number' => 'RET-FAULT-' . strtoupper(uniqid()),
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
            'admin_ruling' => 'uphold_customer_claim',
            'admin_ruled_at' => Carbon::now(),
            'cost_allocation' => 'vendor_100',
        ]);

        $paystack = \Mockery::mock(\App\Services\PaystackService::class);
        $paystack->shouldReceive('initiateRefund')
            ->once()
            ->withAnyArgs()
            ->andReturn([
                'success' => true,
                'status' => 'completed',
                'refund_reference' => 'rf_authorised_fault_recovery',
                'data' => ['status' => 'completed', 'reference' => 'rf_authorised_fault_recovery'],
            ]);
        $this->app->instance(\App\Services\PaystackService::class, $paystack);

        $result = app(ReturnAndRefundService::class)->adminDisburseRefund($this->superAdmin, $case);
        $this->assertTrue($result['success']);
        $this->assertSame('refunded', $case->fresh()->status);

        $recovery = \App\Models\VendorRecoveryLedger::where('return_case_id', $case->id)->firstOrFail();
        $this->assertSame("return-case-{$case->id}-merchandise-recovery", $recovery->idempotency_key);
        $this->assertEquals(9000.00, (float) $recovery->amount);
        $this->assertSame('pending_recovery', $recovery->status);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'vendor_recovery_receivable',
            'entry_type' => 'debit',
            'amount' => '9000.00',
        ]);

        $debits = (float) FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'debit')->sum('amount');
        $credits = (float) FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'credit')->sum('amount');
        $this->assertEquals($credits, $debits, 'Authorised vendor recovery must not unbalance the refund journal.');

        $retry = app(ReturnAndRefundService::class)->adminDisburseRefund($this->superAdmin, $case->fresh());
        $this->assertFalse($retry['success']);
        $this->assertSame(1, \App\Models\VendorRecoveryLedger::where('return_case_id', $case->id)->count());
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
    public function test_in_shop_handover_requires_reservation_pass_customer_otp_and_photo_once()
    {
        $fixture = $this->createDeliveredOrder(Carbon::now()->subHours(2), 1);
        $order = $fixture['order'];
        $order->update([
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'verification_code' => '112233', // customer-facing pickup OTP
            'pickup_verification_code' => '998877', // vendor-to-rider custody OTP
        ]);

        $reservationCode = 'RES-' . strtoupper(bin2hex(random_bytes(4)));
        $reservation = \App\Models\PickupReservation::create([
            'reservation_code' => $reservationCode,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'estimated_total' => '10000.00',
            'status' => 'payment_completed',
            'order_id' => $order->id,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $service = app(\App\Services\OrderSettlementService::class);

        // Vendor-to-rider custody OTP must never authorize release to the customer.
        $wrongOtp = $service->verifyPickupHandoverOtp(
            $order,
            '998877',
            $this->vendorUserA,
            $reservationCode,
            'storage/customer_pickup_proofs/wrong-attempt.jpg'
        );
        $this->assertFalse($wrongOtp['success']);
        $this->assertSame('confirmed', $order->fresh()->order_status);
        $this->assertSame('payment_completed', $reservation->fresh()->status);

        // Customer OTP succeeds only with the matching reservation pass and photo evidence.
        $result = $service->verifyPickupHandoverOtp(
            $order->fresh(),
            '112233',
            $this->vendorUserA,
            $reservationCode,
            'storage/customer_pickup_proofs/verified-pickup.jpg'
        );
        $this->assertTrue($result['success']);
        $this->assertSame('picked_up', $order->fresh()->order_status);
        $this->assertNull($order->fresh()->verification_code);
        $this->assertSame('998877', $order->fresh()->pickup_verification_code);
        $this->assertSame('storage/customer_pickup_proofs/verified-pickup.jpg', $order->fresh()->customer_pickup_photo_path);
        $this->assertSame('handed_over', $reservation->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $this->vendorUserA->id,
            'action' => 'in_shop_customer_pickup_verified',
            'target_type' => \App\Models\Order::class,
            'target_id' => $order->id,
        ]);

        // The customer OTP is one-time and cannot be replayed after handover.
        $replay = $service->verifyPickupHandoverOtp(
            $order->fresh(),
            '112233',
            $this->vendorUserA,
            $reservationCode,
            'storage/customer_pickup_proofs/replay.jpg'
        );
        $this->assertFalse($replay['success']);
    }

    public function test_customer_order_api_only_exposes_correct_pickup_otp_after_payment(): void
    {
        $fixture = $this->createDeliveredOrder(Carbon::now()->subHours(2), 1);
        $order = $fixture['order'];
        $order->update([
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'confirmed',
            'payment_status' => 'pending',
            'verification_code' => '112233',
            'pickup_verification_code' => '998877',
        ]);

        $this->actingAs($this->customer, 'sanctum');
        $pending = $this->getJson('/api/v1/customer/orders/' . $order->order_number);
        $pending->assertOk();
        $pending->assertJsonPath('data.customer_pickup_otp', null);
        $pending->assertJsonPath('data.delivery_otp', null);
        $this->assertStringNotContainsString('998877', $pending->getContent());

        $order->update(['payment_status' => 'paid']);
        $paid = $this->getJson('/api/v1/customer/orders/' . $order->order_number);
        $paid->assertOk();
        $paid->assertJsonPath('data.customer_pickup_otp', '112233');
        $paid->assertJsonPath('data.delivery_otp', null);
        $this->assertStringNotContainsString('998877', $paid->getContent());

        $order->update(['order_status' => 'picked_up', 'verification_code' => null]);
        $completed = $this->getJson('/api/v1/customer/orders/' . $order->order_number);
        $completed->assertOk();
        $completed->assertJsonPath('data.customer_pickup_otp', null);
    }

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

    public function test_public_order_tracking_requires_guest_token_and_does_not_allow_order_number_enumeration(): void
    {
        $created = $this->createDeliveredOrder(Carbon::now()->subHours(2));
        $order = $created['order'];
        $token = str_repeat('a', 64);
        $order->forceFill([
            'guest_access_token' => $token,
            'verification_code' => '123456',
            'order_status' => 'out_for_delivery',
        ])->save();

        $this->getJson('/api/v1/order/track?order_number=' . urlencode($order->order_number))
            ->assertUnauthorized();

        $this->getJson('/api/v1/order/track?order_number=' . urlencode($order->order_number) . '&guest_access_token=' . str_repeat('b', 64))
            ->assertNotFound();

        $response = $this->getJson('/api/v1/order/track?order_number=' . urlencode($order->order_number) . '&guest_access_token=' . $token);
        $response->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.delivery_otp', '123456');
    }

    public function test_authenticated_customer_cannot_track_another_customers_order(): void
    {
        $created = $this->createDeliveredOrder(Carbon::now()->subHours(2));
        $order = $created['order'];

        $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson('/api/v1/order/track?order_number=' . urlencode($order->order_number))
            ->assertNotFound();
    }


    public function test_public_pickup_reservation_lookup_requires_authentication(): void
    {
        $this->getJson('/api/v1/pickup-reservations/RES-ABC123')
            ->assertUnauthorized();
    }


    public function test_public_delivery_confirmation_aliases_require_authentication(): void
    {
        $payload = [
            'order_number' => 'ORD-UNKNOWN',
            'delivery_otp' => '123456',
        ];

        $this->postJson('/api/v1/customer/order/confirm-doorstep-delivery', $payload)
            ->assertUnauthorized();
        $this->postJson('/api/v1/customer/order/confirm-inshop-pickup', $payload)
            ->assertUnauthorized();
    }

    public function test_late_refund_success_after_terminal_failure_is_quarantined(): void
    {
        $operation = FinancialOperation::create([
            'operation_reference' => 'OP-RFD-FAILED-THEN-LATE-SUCCESS',
            'idempotency_key' => 'refund-failed-late-success-key',
            'operation_type' => 'customer_refund',
            'amount_kobo' => 125000,
            'currency' => 'NGN',
            'status' => 'failed',
            'gateway_provider' => 'paystack',
            'metadata' => [
                'refund_reference' => 'PAYSTACK-REFUND-LATE-SUCCESS-01',
                'payment_reference' => 'PAYMENT-LATE-SUCCESS-01',
            ],
        ]);

        $payloadData = [
            'id' => 'evt_refund_success_after_terminal_failure_01',
            'event' => 'refund.processed',
            'data' => [
                'refund_reference' => 'PAYSTACK-REFUND-LATE-SUCCESS-01',
                'transaction_reference' => 'PAYMENT-LATE-SUCCESS-01',
                'amount' => 125000,
                'currency' => 'NGN',
                'status' => 'processed',
            ],
        ];
        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);
        $journalCountBefore = FinancialJournalEntry::count();

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
        $operation->refresh();
        $this->assertSame('failed', $operation->status);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => 'evt_refund_success_after_terminal_failure_01',
            'status' => 'unmatched',
            'error_reason' => 'REFUND_TERMINAL_STATE_CONFLICT: Late success cannot reopen a failed/reversed refund operation; manual reconciliation required.',
        ]);
    }

    public function test_late_refund_failed_webhook_cannot_downgrade_confirmed_refund(): void
    {
        $operation = FinancialOperation::create([
            'operation_reference' => 'OP-RFD-CONFIRMED-THEN-LATE-FAIL',
            'idempotency_key' => 'refund-confirmed-late-fail-key',
            'operation_type' => 'customer_refund',
            'amount_kobo' => 125000,
            'currency' => 'NGN',
            'status' => 'confirmed',
            'gateway_provider' => 'paystack',
            'metadata' => [
                'refund_reference' => 'PAYSTACK-REFUND-CONFIRMED-01',
                'payment_reference' => 'PAYMENT-CONFIRMED-01',
            ],
        ]);

        $payloadData = [
            'id' => 'evt_refund_late_failure_after_success_01',
            'event' => 'refund.failed',
            'data' => [
                'refund_reference' => 'PAYSTACK-REFUND-CONFIRMED-01',
                'transaction_reference' => 'PAYMENT-CONFIRMED-01',
                'amount' => 125000,
                'currency' => 'NGN',
                'message' => 'Delayed failure notification',
            ],
        ];
        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);
        $journalCountBefore = FinancialJournalEntry::count();

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
        $operation->refresh();
        $this->assertSame('confirmed', $operation->status);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => 'evt_refund_late_failure_after_success_01',
            'status' => 'completed',
        ]);
    }

    public function test_stale_refund_failed_generation_cannot_fail_operation_or_release_reservation(): void
    {
        $operation = FinancialOperation::create([
            'operation_reference' => 'OP-RFD-STALE-FAIL-GENERATION',
            'idempotency_key' => 'refund-stale-failure-generation-key',
            'operation_type' => 'customer_refund',
            'amount_kobo' => 125000,
            'currency' => 'NGN',
            'status' => 'initiated',
            'gateway_provider' => 'paystack',
            'metadata' => [
                'refund_reference' => 'PAYSTACK-REFUND-STALE-FAIL-01',
                'payment_reference' => 'PAYMENT-STALE-FAIL-01',
            ],
        ]);

        $reservation = \App\Models\FinancialReservation::create([
            'financial_operation_id' => $operation->id,
            'source_type' => Order::class,
            'source_id' => 999999,
            'reserved_amount_kobo' => 125000,
            'purpose' => 'customer_refund_hold',
            'status' => 'active',
        ]);

        $payloadData = [
            'id' => 'evt_refund_stale_failure_generation_01',
            'event' => 'refund.failed',
            'data' => [
                'refund_reference' => 'PAYSTACK-REFUND-STALE-FAIL-01',
                'transaction_reference' => 'PAYMENT-STALE-FAIL-01',
                'amount' => 125000,
                'currency' => 'NGN',
                'message' => 'Stale generation regression',
            ],
        ];
        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);
        $journalCountBefore = FinancialJournalEntry::count();
        $bumpedGeneration = false;

        // Simulate a newer worker reclaiming the inbox event after this request has
        // claimed generation 1 but before the refund-failure transaction locks it.
        FinancialOperation::retrieved(function (FinancialOperation $retrieved) use (&$bumpedGeneration): void {
            if ($bumpedGeneration || $retrieved->operation_reference !== 'OP-RFD-STALE-FAIL-GENERATION') {
                return;
            }

            $bumpedGeneration = true;
            ProcessedWebhookEvent::where('event_id', 'evt_refund_stale_failure_generation_01')
                ->where('status', 'processing')
                ->update(['attempts' => 2]);
        });

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(429);
        $this->assertTrue($bumpedGeneration, 'The test did not simulate a reclaimed webhook generation.');
        $this->assertSame('initiated', $operation->fresh()->status);
        $this->assertSame('active', $reservation->fresh()->status);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => 'evt_refund_stale_failure_generation_01',
            'status' => 'processing',
            'attempts' => 2,
        ]);
    }

    public function test_refund_processed_webhook_quarantines_ambiguous_gateway_reference(): void
    {
        foreach ([1, 2] as $suffix) {
            FinancialOperation::create([
                'operation_reference' => 'OP-RFD-AMBIGUOUS-' . $suffix,
                'idempotency_key' => 'refund-ambiguous-key-' . $suffix,
                'operation_type' => 'customer_refund',
                'amount_kobo' => 125000,
                'currency' => 'NGN',
                'status' => 'initiated',
                'gateway_provider' => 'paystack',
                'metadata' => [
                    'refund_reference' => 'PAYSTACK-REFUND-AMBIGUOUS-01',
                    'payment_reference' => 'PAYMENT-AMBIGUOUS-01',
                ],
            ]);
        }

        $payloadData = [
            'id' => 'evt_refund_ambiguous_reference_01',
            'event' => 'refund.processed',
            'data' => [
                'refund_reference' => 'PAYSTACK-REFUND-AMBIGUOUS-01',
                'transaction_reference' => 'PAYMENT-AMBIGUOUS-01',
                'amount' => 125000,
                'currency' => 'NGN',
            ],
        ];
        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);
        $journalCountBefore = FinancialJournalEntry::count();

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => true]);
        $this->assertSame(2, FinancialOperation::where('operation_type', 'customer_refund')->count());
        $this->assertSame(0, FinancialOperation::where('operation_type', 'customer_refund')->where('status', 'confirmed')->count());
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => 'evt_refund_ambiguous_reference_01',
            'status' => 'unmatched',
            'error_reason' => 'REFUND_OPERATION_AMBIGUOUS: Gateway refund reference matches multiple customer refund operations.',
        ]);
    }

    public function test_stale_webhook_generation_cannot_finalize_refund_financial_side_effects(): void
    {
        $operation = FinancialOperation::create([
            'operation_reference' => 'OP-RFD-STALE-WORKER-01',
            'idempotency_key' => 'refund-stale-worker-key',
            'operation_type' => 'customer_refund',
            'amount_kobo' => 125000,
            'currency' => 'NGN',
            'status' => 'initiated',
            'gateway_provider' => 'paystack',
            'metadata' => [
                'refund_reference' => 'PAYSTACK-REFUND-STALE-01',
                'payment_reference' => 'PAYMENT-STALE-01',
            ],
        ]);

        $event = ProcessedWebhookEvent::create([
            'event_id' => 'evt_refund_stale_generation_01',
            'event_type' => 'refund.processed',
            'payload_hash' => hash('sha256', '{"event":"refund.processed"}'),
            'payload' => '{"event":"refund.processed"}',
            'status' => 'processing',
            'attempts' => 2,
        ]);

        $journalCountBefore = FinancialJournalEntry::count();
        $result = app(FinancialOperationService::class)->finalizeRefundConfirmation(
            $operation,
            [
                'status' => 'processed',
                'amount' => 125000,
                'currency' => 'NGN',
                'refund_reference' => 'PAYSTACK-REFUND-STALE-01',
            ],
            null,
            (int) $event->id,
            1 // stale worker's generation; inbox has already been reclaimed as generation 2
        );

        $this->assertTrue($result['protected'] ?? false);
        $this->assertTrue($result['stale_webhook_claim'] ?? false);
        $this->assertSame('initiated', $operation->fresh()->status);
        $this->assertSame('processing', $event->fresh()->status);
        $this->assertSame(2, (int) $event->fresh()->attempts);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }


    public function test_retailer_refund_reverses_supplier_cost_and_active_cashback_not_legacy_vendor_split(): void
    {
        $fixture = $this->createDeliveredOrder(Carbon::now()->subHours(4), 1);
        $order = $fixture['order'];
        $order->update([
            'retailer_model_order' => true,
            'payment_reference' => 'RETAILER-REFUND-' . strtoupper(uniqid()),
            'supplier_cost_total_snapshot' => '5000.00',
            'fulfillment_supplier_cost_total_snapshot' => '5000.00',
            'retailer_merchandise_margin' => '4500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '0.00',
            'logistics_company_payable_default_amount' => '1350.00',
            'logistics_company_payable_amount' => '1350.00',
            'vendor_settlement_status' => 'held',
        ]);
        $fixture['item']->update([
            'supplier_vendor_id' => $this->vendorA->id,
            'supplier_shop_id' => $this->shopA->id,
            'supplier_name_snapshot' => $this->vendorA->business_name,
            'supplier_shop_name_snapshot' => $this->shopA->name,
            'supplier_cost_snapshot' => '5000.00',
            'supplier_cost_currency' => 'NGN',
            'supplier_snapshot_at' => Carbon::now(),
            'fulfillment_supplier_vendor_id' => $this->vendorB->id,
            'fulfillment_supplier_shop_id' => $this->shopB->id,
            'fulfillment_supplier_name_snapshot' => $this->vendorB->business_name,
            'fulfillment_supplier_shop_name_snapshot' => $this->shopB->name,
            'fulfillment_supplier_cost_snapshot' => '5000.00',
            'fulfillment_supplier_snapshot_at' => Carbon::now(),
        ]);

        $saleEntries = [
            ['event_id' => 'TEST-RETAIL-SALE-CASH-' . $order->id, 'account_name' => 'cash_in_escrow', 'entry_type' => 'debit', 'amount' => '11500.00', 'beneficiary_type' => User::class, 'beneficiary_id' => null],
            ['event_id' => 'TEST-RETAIL-SALE-SUPPLIER-' . $order->id, 'account_name' => 'supplier_payable', 'entry_type' => 'credit', 'amount' => '5000.00', 'beneficiary_type' => VendorProfile::class, 'beneficiary_id' => $this->vendorA->id],
            ['event_id' => 'TEST-RETAIL-SALE-CASHBACK-' . $order->id, 'account_name' => 'cashback_liability', 'entry_type' => 'credit', 'amount' => '500.00', 'beneficiary_type' => User::class, 'beneficiary_id' => $this->customer->id],
            ['event_id' => 'TEST-RETAIL-SALE-MARGIN-' . $order->id, 'account_name' => 'retailer_merchandise_margin', 'entry_type' => 'credit', 'amount' => '4500.00', 'beneficiary_type' => User::class, 'beneficiary_id' => null],
            ['event_id' => 'TEST-RETAIL-SALE-LOGISTICS-' . $order->id, 'account_name' => 'logistics_company_payable', 'entry_type' => 'credit', 'amount' => '1350.00', 'beneficiary_type' => null, 'beneficiary_id' => null],
            ['event_id' => 'TEST-RETAIL-SALE-DELIVERY-MARGIN-' . $order->id, 'account_name' => 'platform_delivery_revenue', 'entry_type' => 'credit', 'amount' => '150.00', 'beneficiary_type' => User::class, 'beneficiary_id' => null],
        ];
        foreach ($saleEntries as $entry) {
            FinancialJournalEntry::create(array_merge($entry, [
                'order_id' => $order->id,
                'transaction_reference' => $order->payment_reference,
                'currency' => 'NGN',
                'description' => 'Balanced retailer refund test opening entry.',
            ]));
        }

        // Simulate the balanced recovery adjustment: capture-time supplier A is
        // reversed and the current operational supplier B receives the payable.
        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'transaction_reference' => 'TEST-RECOVERY-' . $order->id,
            'event_id' => 'TEST-RECOVERY-OLD-' . $order->id,
            'idempotency_key' => 'TEST-RECOVERY-OLD-' . $order->id,
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendorA->id,
            'account_name' => 'supplier_payable',
            'entry_type' => 'debit',
            'amount' => '5000.00',
            'currency' => 'NGN',
            'description' => 'Reverse captured supplier payable for recovery refund test.',
        ]);
        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'transaction_reference' => 'TEST-RECOVERY-' . $order->id,
            'event_id' => 'TEST-RECOVERY-NEW-' . $order->id,
            'idempotency_key' => 'TEST-RECOVERY-NEW-' . $order->id,
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendorB->id,
            'account_name' => 'supplier_payable',
            'entry_type' => 'credit',
            'amount' => '5000.00',
            'currency' => 'NGN',
            'description' => 'Credit current operational supplier payable for recovery refund test.',
        ]);

        $case = ReturnCase::create([
            'case_number' => 'RET-RETAIL-' . strtoupper(uniqid()),
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
        $this->assertNull($case->vendor_id, 'Retailer return cases are centrally owned and must not be attached to a private supplier.');
        ReturnItem::create([
            'return_case_id' => $case->id,
            'order_item_id' => $fixture['item']->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
            'allocated_discount' => '0.00',
            'refundable_amount' => '10000.00',
            'item_condition' => 'opened_tested',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($this->customer, ['*']);
        $customerReturnList = $this->getJson('/api/v1/customer/returns')->assertOk();
        $customerReturn = $customerReturnList->json('data.0');
        $this->assertArrayNotHasKey('vendor_id', $customerReturn);
        $this->assertArrayNotHasKey('vendor', $customerReturn);
        $this->assertArrayNotHasKey('vendor_return_otp', $customerReturn);
        $customerReturnDetail = $this->getJson('/api/v1/customer/returns/' . $case->case_number)->assertOk()->json('data');
        $this->assertArrayNotHasKey('vendor_id', $customerReturnDetail);
        $this->assertArrayNotHasKey('vendor', $customerReturnDetail);
        $this->assertArrayNotHasKey('vendor_return_otp', $customerReturnDetail);

        $paystack = \Mockery::mock(\App\Services\PaystackService::class);
        $paystack->shouldReceive('initiateRefund')
            ->once()
            ->withAnyArgs()
            ->andReturn([
                'success' => true,
                'status' => 'completed',
                'refund_reference' => 'rf_retailer_cost_cashback',
                'data' => ['status' => 'completed', 'reference' => 'rf_retailer_cost_cashback'],
            ]);
        $this->app->instance(\App\Services\PaystackService::class, $paystack);

        $this->assertTrue((bool) $order->fresh()->retailer_model_order, 'The refund fixture must exercise the retailer accounting branch.');
        $this->assertDatabaseMissing('financial_journal_entries', ['order_id' => $order->id, 'account_name' => 'vendor_payable']);
        $result = app(ReturnAndRefundService::class)->adminDisburseRefund($this->superAdmin, $case);
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'supplier_payable',
            'entry_type' => 'debit',
            'amount' => '5000.00',
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendorB->id,
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'cashback_liability',
            'entry_type' => 'debit',
            'amount' => '500.00',
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'retailer_merchandise_margin',
            'entry_type' => 'debit',
            'amount' => '4500.00',
        ]);
        $this->assertDatabaseMissing('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'vendor_payable',
        ]);
        $this->assertSame('forfeited', $fixture['cashback']->fresh()->status);
        $this->assertSame(1, FinancialJournalEntry::where('order_id', $order->id)->where('account_name', 'cash_in_escrow')->where('entry_type', 'credit')->count(), 'The confirmed gateway refund must post exactly one cash outflow journal.');

        $debits = (string) FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'debit')->sum('amount');
        $credits = (string) FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'credit')->sum('amount');
        $this->assertSame(0, bccomp($debits, $credits, 2), 'Retailer refund reversals must balance against the original retailer sale.');
    }

}
