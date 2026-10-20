<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\DisputeAndReturn;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BcMathService;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorSettlementProtectionWindowTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $vendorUser;
    protected User $customerUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Category $category;
    protected Product $product;
    protected Lga $uyoLga;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'currency_code' => 'NGN',
            'phone_code' => '+234',
            'is_active' => true,
        ]);

        $state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'code' => 'AK',
            'is_active' => true,
        ]);

        $this->uyoLga = Lga::create([
            'state_id' => $state->id,
            'name' => 'Uyo',
            'code' => 'UYO',
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@victoriousmarket.com.ng',
            'password' => bcrypt('SuperSecurePassword123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // Vendor Setup
        $this->vendorUser = User::create([
            'name' => 'Tech Depot Merchant',
            'email' => 'merchant@techdepot.ng',
            'password' => bcrypt('MerchantPassword123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendorProfile = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Tech Depot Akwa Ibom',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1029384756',
            'account_name' => 'Tech Depot Akwa Ibom',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);
        $this->shop = Shop::create([
            'vendor_id' => $this->vendorProfile->id,
            'name' => 'Tech Depot Uyo Central',
            'slug' => 'tech-depot-uyo-central',
            'address' => '88 Abak Road',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_primary_branch' => true,
            'is_active' => true,
        ]);
        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendorProfile->id,
            'category_id' => $this->category->id,
            'name' => 'Wireless Noise Canceling Headphones',
            'slug' => 'wireless-noise-canceling-headphones',
            'unit_price' => '10000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // Customer Setup
        $this->customerUser = User::create([
            'name' => 'Chidi Customer',
            'email' => 'chidi@example.com',
            'password' => bcrypt('CustomerPassword123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);
    }

        /**
     * Test 2: Complete Elimination of Rider Wallet Domain Capabilities.
     * Couriers are purely operational dispatch agents; creditBalance and debitBalance do not exist.
     */


    public function test_vendor_cannot_be_disbursed_what_they_did_not_earn_or_before_refund_window(): void
    {
        $service = app(OrderSettlementService::class);

        $order = Order::create([
            'order_number' => 'VM-ORD-PROOF-008',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'received_at' => Carbon::now(),
            'refund_window_expires_at' => Carbon::now()->addHours(24), // 24-hour refund window active!
            'vendor_settlement_status' => 'held',
            'merchandise_subtotal' => '10000.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);

        // 1. Attempt disbursement during refund window -> strictly rejected
        $disburseResult1 = $service->disburseVendorSettlement($order, $this->superAdmin, 'storage/proofs/test.png');
        $this->assertFalse($disburseResult1['success']);
        $this->assertEquals('Order is not eligible for disbursement (current state: held).', $disburseResult1['message']);

        // 2. Advance time past 24 hours, but file an active dispute
        Carbon::setTestNow(Carbon::now()->addHours(25));
        DisputeAndReturn::create([
            'order_id' => $order->id,
            'customer_id' => $this->customerUser->id,
            'reason' => 'materially_defective',
            'customer_description' => 'Defective merchandise component missing.',
            'status' => 'under_review',
        ]);

        $service->evaluateMaturation($order);
        $order->refresh();

        $this->assertEquals('disputed', $order->vendor_settlement_status);

        // Attempt disbursement on disputed order -> strictly rejected
        $disburseResult2 = $service->disburseVendorSettlement($order, $this->superAdmin, 'storage/proofs/test.png');
        $this->assertFalse($disburseResult2['success']);
        $this->assertEquals('Order is not eligible for disbursement (current state: disputed).', $disburseResult2['message']);

        // Reset test time
        Carbon::setTestNow();
    }
}
