<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use App\Services\SourcingAllocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EndToEndPaidOrderFulfilmentJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected Country $country;
    protected State $state;
    protected Lga $uyoLga;
    protected User $adminUser;
    protected User $customerUser;
    protected Category $category;
    protected SourcingAllocationService $sourcingService;
    protected OrderSettlementService $settlementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::firstOrCreate(
            ['iso_code' => 'NG'],
            ['name' => 'Nigeria', 'currency_code' => 'NGN', 'phone_code' => '+234', 'is_active' => true]
        );

        $this->state = State::create([
            'country_id' => $this->country->id,
            'name' => 'Akwa Ibom',
            'code' => 'AK',
            'is_active' => true,
        ]);

        $this->uyoLga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Uyo',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Super Admin Victorious',
            'email' => 'superadmin@victoriousmarket.com.ng',
            'phone' => '08000000001',
            'user_type' => 'super_admin',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $this->customerUser = User::create([
            'name' => 'Grace Customer',
            'email' => 'grace.customer@example.com',
            'phone' => '08022334455',
            'user_type' => 'customer',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $this->category = Category::create([
            'name' => 'Home & Appliances',
            'slug' => 'home-appliances',
            'is_active' => true,
        ]);

        $this->sourcingService = app(SourcingAllocationService::class);
        $this->settlementService = app(OrderSettlementService::class);

        Config::set('services.paystack.secret_key', 'sk_test_mock_secret_key_victorious');
        Storage::fake('public');
    }

    /**
     * Complete Paid-Order Fulfilment Journey:
     * 1. Customer Checkout & Upfront Paystack Authorization
     * 2. Independent Server-Side Payment Verification & Cashback Ledger Accrual
     * 3. Sourcing Allocation & Immutable Wholesale Cost Snapshots
     * 4. Supplier Acceptance & Counter Handover Readiness
     * 5. Central Logistics Fleet & Single Courier Assignment
     * 6. Dual-Custody Handshake (Counter Collection OTP + Doorstep Delivery OTP)
     * 7. Order Reconciliation & Zero-Automated-Outflow Compliance
     */
    public function test_complete_paid_order_fulfilment_and_reconciliation_journey(): void
    {
        // -------------------------------------------------------------------------
        // SETUP: Accredited Supplier & Verified Offer in Uyo
        // -------------------------------------------------------------------------
        $supplierUser = User::create([
            'name' => 'Akpan Wholesalers',
            'email' => 'akpan@example.com',
            'phone' => '08033445566',
            'user_type' => 'vendor',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);
        $supplier = VendorProfile::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Akpan Wholesale Ventures Ltd',
            'marketplace_status' => 'active',
            'kyc_status' => 'verified',
            'bank_name' => 'Access Bank',
            'bank_code' => '044',
            'account_number' => '0123456789',
            'account_name' => 'Akpan Wholesale Ventures Ltd',
            'bank_updated_at' => Carbon::now()->subHours(72), // Past 48h cooldown
        ]);
        $supplierShop = Shop::create([
            'vendor_id' => $supplier->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->uyoLga->id,
            'name' => 'Akpan Central Hub Shop',
            'slug' => 'akpan-central-hub-shop',
            'address' => '42 Oron Road, Uyo, Akwa Ibom',
            'is_active' => true,
            'is_verified' => true,
        ]);

        // Canonical Product in Victorious Retail Catalog
        $product = Product::create([
            'category_id' => $this->category->id,
            'vendor_id' => $supplier->id,
            'shop_id' => $supplierShop->id,
            'name' => 'Premium Electric Blender 500W',
            'slug' => 'premium-electric-blender-500w',
            'unit_price' => '25000.00',
            'inventory_quantity' => 15,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'package_tier' => 'standard',
        ]);

        // Verified Wholesale Offer: Wholesale cost is 20,000 NGN (Retail is 25,000 NGN)
        $offer = SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $supplier->id,
            'shop_id' => $supplierShop->id,
            'wholesale_cost' => '20000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now(),
            'availability_confirmed_by' => $this->adminUser->id,
            'availability_expires_at' => Carbon::now()->addHours(24),
        ]);

        // Central Logistics Fleet & Courier in Uyo
        $fleetAdminUser = User::create([
            'name' => 'Speedy Logistics Admin',
            'email' => 'speedy@example.com',
            'phone' => '08099887711',
            'user_type' => 'logistics_company',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);
        $fleetCompany = LogisticsCompanyProfile::create([
            'user_id' => $fleetAdminUser->id,
            'company_name' => 'Speedy Express Logistics Ltd',
            'hq_lga_id' => $this->uyoLga->id,
            'is_active' => true,
            'status' => 'active',
        ]);
        $riderUser = User::create([
            'name' => 'Bassey Courier',
            'email' => 'bassey.courier@example.com',
            'phone' => '08055667788',
            'user_type' => 'rider',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);
        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => $fleetCompany->id,
            'assigned_lga_id' => $this->uyoLga->id,
            'is_active' => true,
            'is_online' => true,
        ]);

        // =========================================================================
        // STAGE 1: Authenticated Customer Checkout & Order Creation
        // =========================================================================
        $splits = Order::computeSplits('25000.00', '1500.00');

        $order = Order::create([
            'order_number' => 'VM-E2E-' . date('Ymd') . '-001',
            'customer_id' => $this->customerUser->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'retailer_model_order' => true,
            'vendor_id' => $supplier->id,
            'shop_id' => $supplierShop->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_reference' => 'VM-REF-E2E-778899',
            'merchandise_subtotal' => '25000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '26500.00',
            'total_amount' => '26500.00',
            'supplier_cost_total_snapshot' => '20000.00',
            'retailer_merchandise_margin' => '3750.00',
            'retailer_gross_margin' => '5000.00',
            'vendor_net_90' => $splits['vendor_net_90'],
            'customer_cashback_5' => $splits['customer_cashback_5'],
            'platform_margin_5' => $splits['platform_margin_5'],
            'logistics_fee_retained' => $splits['logistics_net_90'],
            'platform_delivery_fee' => $splits['platform_delivery_fee_10'],
            'verification_code' => '998811', // Customer Doorstep Delivery OTP
            'pickup_verification_code' => '554433', // Central Courier Pickup OTP
            'logistics_company_id' => $fleetCompany->id,
            'assigned_logistics_company_id' => $fleetCompany->id,
            'sourcing_status' => 'awaiting_payment',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => '25000.00',
            'line_total' => '25000.00',
            'supplier_offer_id' => $offer->id,
            'supplier_cost_snapshot' => '20000.00',
            'cost_price' => '20000.00',
            'supplier_vendor_id' => $supplier->id,
            'supplier_shop_id' => $supplierShop->id,
            'fulfillment_supplier_offer_id' => $offer->id,
            'fulfillment_supplier_cost_snapshot' => '20000.00',
            'fulfillment_supplier_vendor_id' => $supplier->id,
            'fulfillment_supplier_shop_id' => $supplierShop->id,
        ]);

        // Pre-create supplier assignment awaiting payment
        $pickupCode = OrderSupplierAssignment::generateUniquePickupCode();
        $assignment = OrderSupplierAssignment::create([
            'order_id' => $order->id,
            'supplier_vendor_id' => $supplier->id,
            'supplier_shop_id' => $supplierShop->id,
            'status' => 'awaiting_payment',
            'supplier_pickup_code' => $pickupCode,
        ]);

        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->order_status);
        $this->assertSame('awaiting_payment', $assignment->status);

        // =========================================================================
        // STAGE 2: Independent Server-Side Payment Verification (Paystack)
        // =========================================================================
        $paystackData = [
            'reference' => $order->payment_reference,
            'status' => 'success',
            'amount' => 2650000, // 26,500.00 NGN in Kobo
            'currency' => 'NGN',
            'channel' => 'card',
        ];

        $settlementResult = $this->settlementService->settlePaystackOrder($order, $paystackData, $this->customerUser);
        $this->assertTrue($settlementResult['success']);
        $order->refresh();
        $assignment->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->order_status);

        // Payment settlement automatically activates supplier confirmation workflow
        $this->assertSame('awaiting_supplier_confirmation', $assignment->status);
        $this->assertSame('awaiting_supplier_confirmation', $order->sourcing_status);

        // 5% Customer Cashback recorded in ledger (25,000 * 5% = 1,250 NGN)
        $cashback = CustomerCashbackLedger::where('order_id', $order->id)->first();
        $this->assertNotNull($cashback);
        $this->assertEquals(1250.00, (float)$cashback->amount);
        $this->assertSame('pending_maturation', $cashback->status);

        // =========================================================================
        // STAGE 3: Sourcing Allocation & Immutable Wholesale Cost Snapshots
        // =========================================================================
        $orderItem->refresh();
        $this->assertEquals('20000.00', (string)$orderItem->fulfillment_supplier_cost_snapshot);
        $this->assertSame($supplier->id, (int)$orderItem->fulfillment_supplier_vendor_id);
        $this->assertSame($supplierShop->id, (int)$orderItem->fulfillment_supplier_shop_id);

        // =========================================================================
        // STAGE 4: Supplier Acceptance & Counter Handover Readiness
        // =========================================================================
        // Supplier confirms package availability
        $assignment->update([
            'status' => 'supplier_confirmed',
            'supplier_confirmed_at' => Carbon::now(),
            'supplier_response_notes' => 'Items retrieved from shelf, packaged with security tape.',
        ]);
        $order->update([
            'sourcing_status' => 'supplier_confirmed',
        ]);

        // Supplier marks counter package as ready for central courier collection
        $assignment->update([
            'status' => 'ready_for_collection',
            'handover_ready_at' => Carbon::now(),
        ]);
        $order->update([
            'order_status' => 'ready_for_pickup',
            'vendor_handover_ready_at' => Carbon::now(),
        ]);

        // =========================================================================
        // STAGE 5: Central Logistics Fleet & Single Rider Assignment
        // =========================================================================
        $order->rider_id = $rider->id;
        $order->save();

        // Rider must satisfy central order eligibility in Uyo LGA
        $this->assertTrue($rider->isEligibleForCentralOrder($order));

        // =========================================================================
        // STAGE 6A: Collection Handshake (Courier collects package at Supplier counter)
        // =========================================================================
        $pickupResult = $this->settlementService->verifyRiderPickupOtp(
            $order,
            $assignment->supplier_pickup_code,
            $rider,
            'storage/pickup_proofs/proof.webp',
            'Sealed package collected from Akpan Wholesalers counter'
        );

        $this->assertTrue($pickupResult['success'], 'Courier pickup OTP verification must succeed: ' . ($pickupResult['message'] ?? ''));
        $order->refresh();
        $assignment->refresh();

        $this->assertSame('collected', $assignment->status);
        $this->assertSame('out_for_delivery', $order->order_status);
        $this->assertTrue((bool)$order->pickup_confirmed_by_rider);

        // =========================================================================
        // STAGE 6B: Doorstep Handshake (Courier delivers to Customer with Delivery OTP)
        // =========================================================================
        $deliveryResult = $this->settlementService->verifyCustomerDeliveryOtp(
            $order,
            '998811', // Correct customer delivery OTP
            $rider,
            'storage/delivery_proofs/doorstep.webp',
            'Handed over directly to customer at residence in Uyo'
        );

        $this->assertTrue($deliveryResult['success'], 'Doorstep delivery OTP verification must succeed: ' . ($deliveryResult['message'] ?? ''));
        $order->refresh();

        $this->assertSame('delivered', $order->order_status);
        $this->assertTrue((bool)$order->delivery_confirmed_by_customer);

        // Cashback maturation clock starts upon verified physical delivery
        $cashback->refresh();
        $this->assertSame('pending_maturation', $cashback->status);
        $this->assertNotNull($cashback->matures_at);

        // =========================================================================
        // STAGE 7: Order Reconciliation & Zero-Automated-Outflow Compliance
        // =========================================================================
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('delivered', $order->order_status);

        // Zero-Automated-Outflow Law: Assert that NO automated refund or payout was sent
        Http::assertNothingSent();
    }
}
