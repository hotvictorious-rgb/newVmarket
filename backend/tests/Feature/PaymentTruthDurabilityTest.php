<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTruthDurabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $supplierUser;
    protected VendorProfile $supplier;
    protected Shop $shop;
    protected Product $product;
    protected SupplierOffer $offer;
    protected Lga $uyoLga;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.paystack.secret_key', 'sk_test_mock_paystack_secret_key_1234567890');
        Config::set('services.paystack.simulate_in_tests', false); // Test real gateway payload parsing

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);

        $state = State::create(['country_id' => $country->id, 'name' => 'Akwa Ibom', 'code' => 'AK', 'is_active' => true]);
        $this->uyoLga = Lga::create(['state_id' => $state->id, 'name' => 'Uyo', 'code' => 'UYO', 'is_active' => true]);

        $this->customer = User::create([
            'name' => 'Ime Akpan',
            'email' => 'ime@gmail.com',
            'phone' => '08012345670',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $this->supplierUser = User::create([
            'name' => 'Supplier Merchant',
            'email' => 'supplier@durability.ng',
            'phone' => '08099990000',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->supplier = VendorProfile::create([
            'user_id' => $this->supplierUser->id,
            'business_name' => 'Durability Wholesalers Ltd',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->supplier->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'name' => 'Durability Central Depot',
            'slug' => 'durability-central-depot',
            'address' => '22 Ikot Ekpene Road, Uyo',
            'phone' => '08099990000',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $category = Category::create([
            'name' => 'Electronics & Solar',
            'slug' => 'electronics-solar',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Solar Battery 200AH',
            'slug' => 'solar-battery-200ah',
            'unit_price' => '150000.00',
            'package_tier' => 'bulky',
            'condition' => 'brand_new',
            'inventory_quantity' => 5,
            'status' => 'active',
            'is_verified' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'is_in_stock' => true,
            'listing_confirmed_at' => Carbon::now()->subMinute(),
        ]);

        $this->offer = SupplierOffer::create([
            'product_id' => $this->product->id,
            'vendor_id' => $this->supplier->id,
            'shop_id' => $this->shop->id,
            'wholesale_cost' => '130000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now()->subMinute(),
            'availability_confirmed_by' => $this->supplierUser->id,
            'availability_expires_at' => Carbon::now()->addDays(7),
        ]);
    }

    /**
     * P0 Control Test: Payment Truth Outranks Expiration
     * When order expires_at is in the past, but Paystack confirms payment was captured,
     * the expiration scanner MUST NOT cancel the order. It must settle and activate it.
     */
    public function test_payment_truth_outranks_expiration_when_gateway_paid(): void
    {
        $order = Order::create([
            'order_number' => 'VM-TRUTH-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->supplier->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'sourcing_status' => 'awaiting_payment',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '2500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'merchandise_subtotal' => '150000.00',
            'grand_total' => '152500.00',
            'supplier_cost_total_snapshot' => '130000.00',
            'payment_reference' => 'PAY-TRUTH-REF-01',
            'expires_at' => Carbon::now()->subMinutes(10), // Expired 10 minutes ago!
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '150000.00',
            'line_total' => '150000.00',
            'supplier_offer_id' => $this->offer->id,
            'supplier_vendor_id' => $this->supplier->id,
            'supplier_shop_id' => $this->shop->id,
            'supplier_cost_snapshot' => '130000.00',
            'package_tier' => 'bulky',
        ]);

        OrderSupplierAssignment::create([
            'order_id' => $order->id,
            'supplier_vendor_id' => $this->supplier->id,
            'supplier_shop_id' => $this->shop->id,
            'status' => 'awaiting_payment',
        ]);

        // Mock Paystack API: Payment was captured successfully!
        Http::fake([
            'https://api.paystack.co/transaction/verify/PAY-TRUTH-REF-01' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 999888,
                    'reference' => 'PAY-TRUTH-REF-01',
                    'status' => 'success',
                    'amount' => 15250000, // ₦152,500.00 in kobo
                    'currency' => 'NGN',
                    'channel' => 'card',
                    'customer' => ['email' => 'ime@gmail.com'],
                ],
            ], 200),
        ]);

        // Run the expiry scanner
        $settlementService = app(OrderSettlementService::class);
        $cancelledCount = $settlementService->expireAbandonedPendingOrders();

        $order->refresh();
        $this->product->refresh();

        // Must NOT be cancelled
        $this->assertSame(0, $cancelledCount, 'Paid order must not be cancelled by expiration worker.');
        $this->assertSame('paid', $order->payment_status, 'Order must transition to paid via gateway truth.');
        $this->assertSame('confirmed', $order->order_status, 'Order must be confirmed.');
        $this->assertSame('awaiting_supplier_confirmation', $order->sourcing_status);

        // Inventory must be claimed (decremented from 5 to 4)
        $this->assertSame(4, (int) $this->product->inventory_quantity);
    }

    /**
     * Regression Test: Zero legacy inventory on canonical supplier product
     * When order expires_at is in the past, but Paystack confirms payment was captured,
     * the expiration scanner MUST NOT cancel the order. It must settle and activate it.
     */
    public function test_canonical_supplier_product_zero_legacy_inventory_does_not_trigger_paid_stockout_hold(): void
    {
        // New canonical catalog products use supplier offers for availability; zero is not a tracked stock counter.
        $this->product->update(['inventory_quantity' => 0, 'vendor_id' => null, 'shop_id' => null]);

        $order = Order::create([
            'order_number' => 'VM-TRUTH-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->supplier->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'sourcing_status' => 'awaiting_payment',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '2500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'merchandise_subtotal' => '150000.00',
            'grand_total' => '152500.00',
            'supplier_cost_total_snapshot' => '130000.00',
            'payment_reference' => 'PAY-TRUTH-REF-01',
            'expires_at' => Carbon::now()->subMinutes(10), // Expired 10 minutes ago!
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '150000.00',
            'line_total' => '150000.00',
            'supplier_offer_id' => $this->offer->id,
            'supplier_vendor_id' => $this->supplier->id,
            'supplier_shop_id' => $this->shop->id,
            'supplier_cost_snapshot' => '130000.00',
            'package_tier' => 'bulky',
        ]);

        OrderSupplierAssignment::create([
            'order_id' => $order->id,
            'supplier_vendor_id' => $this->supplier->id,
            'supplier_shop_id' => $this->shop->id,
            'status' => 'awaiting_payment',
        ]);

        // Mock Paystack API: Payment was captured successfully!
        Http::fake([
            'https://api.paystack.co/transaction/verify/PAY-TRUTH-REF-01' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 999888,
                    'reference' => 'PAY-TRUTH-REF-01',
                    'status' => 'success',
                    'amount' => 15250000, // ₦152,500.00 in kobo
                    'currency' => 'NGN',
                    'channel' => 'card',
                    'customer' => ['email' => 'ime@gmail.com'],
                ],
            ], 200),
        ]);

        // Run the expiry scanner
        $settlementService = app(OrderSettlementService::class);
        $cancelledCount = $settlementService->expireAbandonedPendingOrders();

        $order->refresh();
        $this->product->refresh();

        // Must NOT be cancelled
        $this->assertSame(0, $cancelledCount, 'Paid order must not be cancelled by expiration worker.');
        $this->assertSame('paid', $order->payment_status, 'Order must transition to paid via gateway truth.');
        $this->assertSame('confirmed', $order->order_status, 'Order must be confirmed.');
        $this->assertSame('awaiting_supplier_confirmation', $order->sourcing_status);

        // Canonical products with zero legacy inventory rely on the verified supplier offer.
        // Settlement must not misclassify the sentinel as a stockout or alter it.
        $this->assertSame(0, (int) $this->product->inventory_quantity);
        $this->assertSame('confirmed', $order->order_status);
        $this->assertNotSame('stockout_revalidation_hold', $order->sourcing_status);
    }

    /**
     * Verify abandoned retailer orders are cancelled without inventing a stock restoration that never occurred.
     */
    public function test_supplier_backed_retailer_settlement_ignores_legacy_inventory_quantity(): void
    {
        $this->product->update(['inventory_quantity' => 5, 'vendor_id' => null, 'shop_id' => null, 'is_in_stock' => true]);
        $order = Order::create([
            'order_number' => 'VM-LEGACY-COUNT-' . uniqid(),
            'customer_id' => $this->customer->id,
            'vendor_id' => null,
            'shop_id' => null,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'sourcing_status' => 'awaiting_payment',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '2500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'merchandise_subtotal' => '150000.00',
            'grand_total' => '152500.00',
            'payment_reference' => 'PAY-LEGACY-COUNT-' . uniqid(),
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $this->product->id,
            'product_name' => $this->product->name, 'quantity' => 1,
            'unit_price' => '150000.00', 'line_total' => '150000.00',
            'supplier_offer_id' => $this->offer->id, 'supplier_vendor_id' => $this->supplier->id,
            'supplier_shop_id' => $this->shop->id, 'supplier_cost_snapshot' => '130000.00',
            'package_tier' => 'bulky',
        ]);
        OrderSupplierAssignment::create([
            'order_id' => $order->id, 'supplier_vendor_id' => $this->supplier->id,
            'supplier_shop_id' => $this->shop->id, 'status' => 'awaiting_payment',
        ]);
        $result = app(OrderSettlementService::class)->settlePaystackOrder($order, [
            'id' => 998877, 'reference' => $order->payment_reference, 'status' => 'success',
            'amount' => 15250000, 'currency' => 'NGN', 'channel' => 'card',
            'customer' => ['email' => 'ime@gmail.com'],
        ], $this->customer);
        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'Settlement failed.');
        $this->assertSame(5, (int) $this->product->fresh()->inventory_quantity);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_genuinely_abandoned_retailer_order_is_cancelled_without_fabricating_inventory(): void
    {
        $this->product->inventory_quantity = 3;
        $this->product->save();

        $order = Order::create([
            'order_number' => 'VM-ABANDON-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->supplier->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'sourcing_status' => 'awaiting_payment',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '2500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'merchandise_subtotal' => '150000.00',
            'grand_total' => '152500.00',
            'supplier_cost_total_snapshot' => '130000.00',
            'payment_reference' => 'PAY-ABANDON-REF-01',
            'expires_at' => Carbon::now()->subMinutes(15),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '150000.00',
            'line_total' => '150000.00',
            'supplier_offer_id' => $this->offer->id,
            'supplier_vendor_id' => $this->supplier->id,
            'supplier_shop_id' => $this->shop->id,
            'supplier_cost_snapshot' => '130000.00',
            'package_tier' => 'bulky',
        ]);

        // Mock Paystack: Customer abandoned payment
        Http::fake([
            'https://api.paystack.co/transaction/verify/PAY-ABANDON-REF-01' => Http::response([
                'status' => true,
                'message' => 'Transaction not found or abandoned',
                'data' => [
                    'reference' => 'PAY-ABANDON-REF-01',
                    'status' => 'abandoned',
                ],
            ], 200),
        ]);

        $settlementService = app(OrderSettlementService::class);
        $cancelledCount = $settlementService->expireAbandonedPendingOrders();

        $order->refresh();
        $this->product->refresh();

        $this->assertSame(1, $cancelledCount);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame('pending', $order->payment_status);
        // Retailer checkout did not reserve/decrement stock before payment; expiry must not add phantom inventory.
        $this->assertSame(3, (int) $this->product->inventory_quantity);
    }

    /**
     * P1 Test: finance:reconcile executes all 3 engines: payouts, stale webhooks, and refunds.
     */
    public function test_finance_reconcile_command_executes_refund_reconciliation_pipeline(): void
    {
        $exitCode = Artisan::call('finance:reconcile');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Payouts Reconciled', $output);
        $this->assertStringContainsString('Stale Webhook Events Reset for Retry', $output);
        $this->assertStringContainsString('Customer Refunds Reconciled', $output);
    }
}
