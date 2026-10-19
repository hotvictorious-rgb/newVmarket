<?php

namespace Tests\Feature;

use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TwoStatusInventoryAndPaymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer1;
    protected User $customer2;
    protected User $vendorUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Product $product;
    protected string $secretKey = 'sk_test_mock_paystack_secret_key_twostatus';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Config::set('services.paystack.secret_key', $this->secretKey);
        Config::set('services.paystack.public_key', 'pk_test_mock_paystack_public_key');
        Config::set('services.paystack.payment_url', 'https://api.paystack.co');

        $this->customer1 = User::where('user_type', 'customer')->first();
        if (!$this->customer1) {
            $this->customer1 = User::factory()->create([
                'user_type' => 'customer',
                'status' => 'active',
                'email' => 'customer1@example.com',
            ]);
        }

        $this->customer2 = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
            'email' => 'customer2@example.com',
        ]);

        $this->vendorUser = User::where('user_type', 'vendor')->first();
        $this->vendorProfile = $this->vendorUser->vendorProfile ?? VendorProfile::first();
        $this->shop = Shop::where('vendor_id', $this->vendorProfile->id)->first();
        $this->product = Product::where('shop_id', $this->shop->id)->first();

        // Ensure product is freshly listed and In Stock
        $this->product->update([
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHours(2),
        ]);
    }

    /**
     * Test 1: In Stock + Fresh product allows checkout creation without decrementing numeric stock.
     */
    public function test_in_stock_fresh_product_allows_checkout_creation_without_decrementing_stock(): void
    {
        $response = $this->actingAs($this->customer1)->post(route('checkout.process'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer1->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '10 Marketplace Road, Uyo',
        ]);

        $response->assertRedirect();

        $order = Order::where('customer_id', $this->customer1->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals('pending', $order->order_status);
        $this->assertNotNull($order->expires_at, 'Order must establish a 30-minute payment safety reservation window.');
        $this->assertTrue($order->expires_at->isFuture());

        // Under the two-status model, product remains In Stock
        $freshProduct = $this->product->fresh();
        $this->assertTrue((bool)$freshProduct->is_in_stock);
        $this->assertTrue($freshProduct->is_available_for_sale);
    }

    /**
     * Test 2: Out of Stock product is strictly rejected at checkout.
     */
    public function test_out_of_stock_product_is_strictly_rejected_at_checkout(): void
    {
        // Vendor marks product Out of Stock
        $this->product->update(['is_in_stock' => false]);

        $response = $this->actingAs($this->customer1)->post(route('checkout.process'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer1->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '10 Marketplace Road, Uyo',
        ]);

        $response->assertSessionHasErrors(['product_id']);
        $this->assertFalse($this->product->fresh()->is_available_for_sale);
    }

    /**
     * Test 3: Stale product exceeding seven-day freshness is rejected at checkout.
     */
    public function test_stale_product_exceeding_seven_day_freshness_is_rejected_at_checkout(): void
    {
        // Product confirmed 8 days ago (> 7 days)
        $this->product->update([
            'is_in_stock' => true,
            'listing_confirmed_at' => Carbon::now()->subDays(8),
        ]);

        $this->assertFalse($this->product->isFresh());
        $this->assertFalse($this->product->is_available_for_sale);

        $response = $this->actingAs($this->customer1)->post(route('checkout.process'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer1->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '10 Marketplace Road, Uyo',
        ]);

        $response->assertSessionHasErrors(['product_id']);
    }

    /**
     * Test 4: Concurrent checkouts under two-status model succeed independently within payment window.
     */
    public function test_concurrent_checkouts_under_two_status_model_succeed_independently(): void
    {
        // Customer 1 initiates checkout
        $response1 = $this->actingAs($this->customer1)->post(route('checkout.process'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer1->name,
            'customer_phone' => '08011111111',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '1 Customer One Way, Uyo',
        ]);
        $response1->assertRedirect();

        // Customer 2 initiates checkout concurrently for the same product
        $response2 = $this->actingAs($this->customer2)->post(route('checkout.process'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer2->name,
            'customer_phone' => '08022222222',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '2 Customer Two Way, Uyo',
        ]);
        $response2->assertRedirect();

        $order1 = Order::where('customer_id', $this->customer1->id)->latest('id')->first();
        $order2 = Order::where('customer_id', $this->customer2->id)->latest('id')->first();

        $this->assertNotNull($order1);
        $this->assertNotNull($order2);
        $this->assertNotEquals($order1->id, $order2->id);

        $this->assertEquals('pending', $order1->order_status);
        $this->assertEquals('pending', $order2->order_status);
        $this->assertTrue((bool)$this->product->fresh()->is_in_stock);
    }

    /**
     * Test 5: In-store pickup reservation cancellation does not alter inventory quantities.
     */
    public function test_pickup_reservation_cancellation_preserves_two_status_model_without_altering_stock(): void
    {
        $reservation = PickupReservation::create([
            'reservation_code' => PickupReservation::generateCode(),
            'customer_id' => $this->customer1->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => $this->product->unit_price,
            'estimated_total' => $this->product->unit_price,
            'status' => 'reserved',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->actingAs($this->customer1)->postJson(
            "/api/v1/customer/pickup-reservations/{$reservation->reservation_code}/cancel"
        );

        $response->assertStatus(200);
        $this->assertEquals('canceled', $reservation->fresh()->status);
        $this->assertTrue((bool)$this->product->fresh()->is_in_stock);
    }

    /**
     * Test 6: Scheduled expiration worker cancels abandoned pending orders and linked reservations.
     */
    public function test_scheduled_expiration_cancels_abandoned_orders_and_linked_pickup_reservations(): void
    {
        $order = Order::create([
            'order_number' => 'VM-ORD-EXPIRE-TEST',
            'customer_id' => $this->customer1->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->shop->lga_id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'merchandise_subtotal' => '5000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '5000.00',
            'vendor_net_90' => '4500.00',
            'customer_cashback_5' => '250.00',
            'platform_margin_5' => '250.00',
            'expires_at' => Carbon::now()->subMinutes(35), // Expired
            'pickup_verification_code' => '123456',
            'verification_code' => Order::generateUniqueDeliveryOtp(),
        ]);

        $reservation = PickupReservation::create([
            'reservation_code' => 'RES-EXPIRE-TEST',
            'customer_id' => $this->customer1->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => '5000.00',
            'estimated_total' => '5000.00',
            'status' => 'reserved',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $settlementService = app(OrderSettlementService::class);
        $expiredCount = $settlementService->expireAbandonedPendingOrders();

        $this->assertGreaterThanOrEqual(1, $expiredCount);
        $this->assertEquals('cancelled', $order->fresh()->order_status);
        $this->assertEquals('canceled', $reservation->fresh()->status);
    }

    /**
     * Test 7: Delayed Paystack payment when vendor marked product Out of Stock triggers safe refund pipeline.
     */
    public function test_delayed_paystack_payment_when_vendor_marked_product_out_of_stock_queues_refund(): void
    {
        $order = Order::create([
            'order_number' => 'VM-ORD-LATEPAY-OOS',
            'customer_id' => $this->customer1->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->shop->lga_id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'cancelled', // Order was cancelled due to expiration
            'payment_status' => 'pending',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '11000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'payment_reference' => 'VM-REF-LATEPAY-OOS-01',
            'verification_code' => Order::generateUniqueDeliveryOtp(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        // Vendor marks product Out of Stock while customer completed payment late
        $this->product->update(['is_in_stock' => false]);

        $settlementService = app(OrderSettlementService::class);
        $paystackPayload = [
            'status' => 'success',
            'reference' => 'VM-REF-LATEPAY-OOS-01',
            'amount' => 1100000, // â‚¦11,000.00 in Kobo
            'currency' => 'NGN',
            'channel' => 'card',
            'id' => 99887711,
            'customer' => ['email' => $this->customer1->email],
        ];

        $result = $settlementService->settlePaystackOrder($order, $paystackPayload, $this->customer1);

        $this->assertTrue($result['success']);
        $this->assertEquals('REFUND_PENDING', $result['status']);

        $freshOrder = $order->fresh();
        $this->assertEquals('paid_stock_unavailable', $freshOrder->order_status);
        $this->assertEquals('refund_pending', $freshOrder->vendor_settlement_status);
        $this->assertEquals('paid', $freshOrder->payment_status);

        // Verify strictly zero vendor payout
        $vendorJournalEntries = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'vendor_payable')
            ->count();
        $this->assertEquals(0, $vendorJournalEntries, 'Vendor must NOT be credited when product is out of stock.');

        // Verify balanced double-entry refund liability journal
        $escrowDebit = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'cash_in_escrow')
            ->where('entry_type', 'debit')
            ->value('amount');
        $this->assertEquals(11000.00, (float)$escrowDebit);
    }

    /**
     * Test 8: Delayed Paystack payment when product remains In Stock reinstates and confirms order.
     */
    public function test_delayed_paystack_payment_when_product_remains_in_stock_reinstates_and_confirms_order(): void
    {
        $order = Order::create([
            'order_number' => 'VM-ORD-LATEPAY-INSTOCK',
            'customer_id' => $this->customer1->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->shop->lga_id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'cancelled', // Order was cancelled due to expiration
            'payment_status' => 'pending',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '11000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'payment_reference' => 'VM-REF-LATEPAY-INSTOCK-01',
            'verification_code' => Order::generateUniqueDeliveryOtp(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        // Product remains In Stock & Fresh
        $this->product->update([
            'is_in_stock' => true,
            'listing_confirmed_at' => Carbon::now()->subHours(1),
        ]);

        $settlementService = app(OrderSettlementService::class);
        $paystackPayload = [
            'status' => 'success',
            'reference' => 'VM-REF-LATEPAY-INSTOCK-01',
            'amount' => 1100000, // â‚¦11,000.00 in Kobo
            'currency' => 'NGN',
            'channel' => 'card',
            'id' => 99887722,
            'customer' => ['email' => $this->customer1->email],
        ];

        $result = $settlementService->settlePaystackOrder($order, $paystackPayload, $this->customer1);

        $this->assertTrue($result['success']);
        $this->assertEquals('PAID', $result['status']);

        $freshOrder = $order->fresh();
        $this->assertEquals('confirmed', $freshOrder->order_status);
        $this->assertEquals('held', $freshOrder->vendor_settlement_status);
        $this->assertEquals('paid', $freshOrder->payment_status);
    }
}
