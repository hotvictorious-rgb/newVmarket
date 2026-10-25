<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\Lga;
use App\Models\LogisticsCollectionPoint;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\ReturnInspection;
use App\Models\ReturnItem;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\DoorstepHandshakeService;
use App\Services\ReturnAndRefundService;
use App\Services\RetailerSupplierPayoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReverseLogisticsChainOfCustodyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customer;
    protected User $riderUser;
    protected RiderProfile $riderProfile;
    protected User $bulkRiderUser;
    protected RiderProfile $bulkRiderProfile;
    protected LogisticsCompanyProfile $logisticsCompany;
    protected User $supplierUser;
    protected VendorProfile $supplierProfile;
    protected Shop $supplierShop;
    protected LogisticsCollectionPoint $collectionPoint;
    protected Product $standardProduct;
    protected Product $bulkyProduct;
    protected Lga $uyoLga;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedActorsAndLocations();
    }

    private function seedActorsAndLocations(): void
    {
        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
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

        // 1. Super Admin
        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@vmarket.ng',
            'password' => bcrypt('password'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // 2. Customer
        $this->customer = User::create([
            'name' => 'Iniobong Customer',
            'email' => 'iniobong@example.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'Iniobong Customer',
            'phone' => '+2348011223344',
            'address_line' => '14 Aka Road',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_default' => true,
        ]);


        // 3. Logistics Company & Standard Motorcycle Rider
        $fleetUser = User::create([
            'name' => 'Uyo Express Fleet',
            'email' => 'fleet@uyoexpress.ng',
            'password' => bcrypt('password'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->logisticsCompany = LogisticsCompanyProfile::create([
            'user_id' => $fleetUser->id,
            'company_name' => 'Uyo Express Logistics Ltd',
            'contact_phone' => '+2348022334455',
            'business_registration_number' => 'RC-887766',
            'status' => 'active',
        ]);

        $this->riderUser = User::create([
            'name' => 'Bassey Rider',
            'email' => 'bassey@uyoexpress.ng',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $this->riderProfile = RiderProfile::create([
            'user_id' => $this->riderUser->id,
            'logistics_company_id' => $this->logisticsCompany->id,
            'assigned_lga_id' => $this->uyoLga->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-123-AA',
            'is_active' => true,
            'is_online' => true,
        ]);

        // 4. Bulky Keke/Cargo Rider
        $this->bulkRiderUser = User::create([
            'name' => 'Keke Cargo Driver',
            'email' => 'keke@uyoexpress.ng',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $this->bulkRiderProfile = RiderProfile::create([
            'user_id' => $this->bulkRiderUser->id,
            'logistics_company_id' => $this->logisticsCompany->id,
            'assigned_lga_id' => $this->uyoLga->id,
            'vehicle_type' => 'keke',
            'license_plate' => 'UYO-789-KE',
            'is_active' => true,
            'is_online' => true,
        ]);

        // 5. Supplier
        $this->supplierUser = User::create([
            'name' => 'Uyo Electronics Hub',
            'email' => 'supplier@uyo-elec.ng',
            'password' => bcrypt('password'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->supplierProfile = VendorProfile::create([
            'user_id' => $this->supplierUser->id,
            'business_name' => 'Uyo Electronics Wholesalers',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
            'bank_name' => 'First Bank',
            'bank_code' => '011',
            'account_number' => '3012345678',
            'account_name' => 'Uyo Electronics Wholesalers',
            'bank_updated_at' => Carbon::now()->subDays(5),
        ]);

        $this->supplierShop = Shop::create([
            'vendor_id' => $this->supplierProfile->id,
            'name' => 'Main Electronics Store',
            'slug' => 'main-electronics-store',
            'address' => '22 Oron Road, Uyo',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'phone' => '+2348033445566',
            'is_active' => true,
        ]);



        // 6. Accredited Collection Hub
        $hubManager = User::create([
            'name' => 'Hub Manager',
            'email' => 'hub@vmarket.ng',
            'password' => bcrypt('password'),
            'user_type' => 'admin_employee',
            'status' => 'active',
        ]);

        $this->collectionPoint = LogisticsCollectionPoint::create([
            'logistics_company_id' => $this->logisticsCompany->id,
            'state_id' => $state->id,
            'name' => 'Victorious MARKET Central Hub Uyo',
            'code' => 'CP-UYO-01',
            'address' => '100 Abak Road, Uyo',
            'lga_id' => $this->uyoLga->id,
            'contact_person' => 'Hub Supervisor',
            'contact_phone' => '+2348055667788',
            'is_active' => true,
        ]);


        // 7. Products
        $category = Category::create([
            'name' => 'Home Electronics',
            'slug' => 'home-electronics',
            'is_active' => true,
        ]);

        $this->standardProduct = Product::create([
            'category_id' => $category->id,
            'vendor_id' => $this->supplierProfile->id,
            'shop_id' => $this->supplierShop->id,
            'name' => 'Electric Kettle 2L',
            'slug' => 'electric-kettle-2l',
            'sku' => 'EK-001',
            'unit_price' => '12000.00',
            'inventory_quantity' => 50,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'package_tier' => 'standard',
            'is_active' => true,
        ]);

        $this->bulkyProduct = Product::create([
            'category_id' => $category->id,
            'vendor_id' => $this->supplierProfile->id,
            'shop_id' => $this->supplierShop->id,
            'name' => 'Smart TV 55 Inch',
            'slug' => 'smart-tv-55-inch',
            'sku' => 'TV-002',
            'unit_price' => '250000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'package_tier' => 'bulky',
            'is_active' => true,
        ]);
    }

    private function createDeliveredOrder(Product $product, int $quantity = 1): array
    {
        $deliveredAt = Carbon::now()->subHours(2);
        $subtotal = bcmul((string) $product->unit_price, (string) $quantity, 2);
        
        // Note: vendor_net_90, customer_cashback_5, and platform_margin_5 columns are historical
        // legacy database table columns retained only for MySQL NOT NULL constraint integrity.
        // Under active Victorious MARKET retailer model policies (2026-10-22 / 2026-10-24),
        // 90/5/5 merchandise splits are historical only. Sourcing and supplier liabilities are
        // item-level wholesale costs managed proactively through RetailerSupplierPayoutService,
        // and customer cashback is managed through CustomerCashbackLedger with explicit funding source.
        $vendorNet90 = bcmul($subtotal, '0.90', 2);
        $customerCashback5 = bcmul($subtotal, '0.05', 2);
        $platformMargin5 = bcmul($subtotal, '0.05', 2);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->supplierProfile->id,
            'shop_id' => $this->supplierShop->id,
            'order_type' => 'delivery',
            'retailer_model_order' => true,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'currency' => 'NGN',
            'merchandise_subtotal' => $subtotal,
            'delivery_fee' => '1500.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => bcadd($subtotal, '1500.00', 2),
            'total_amount' => bcadd($subtotal, '1500.00', 2),
            'vendor_net_90' => $vendorNet90,
            'customer_cashback_5' => $customerCashback5,
            'platform_margin_5' => $platformMargin5,
            'logistics_fee_retained' => '1350.00',
            'logistics_company_id' => $this->logisticsCompany->id,
            'rider_id' => $this->riderProfile->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'paid_at' => $deliveredAt->copy()->subHours(2),
            'verification_code' => '654321',
            'delivered_at' => $deliveredAt,
            'received_at' => $deliveredAt,
            'delivery_confirmed_at' => $deliveredAt,
            'refund_window_expires_at' => $deliveredAt->copy()->addHours(24),
            'is_settled' => false,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'fulfillment_supplier_vendor_id' => $this->supplierProfile->id,
            'quantity' => $quantity,
            'accepted_quantity' => $quantity,
            'rejected_quantity' => 0,
            'unit_price' => $product->unit_price,
            'line_total' => $subtotal,
            'item_delivery_status' => 'accepted',
        ]);

        // Customer cashback lot
        $cashbackAmount = round((float)$subtotal * 0.05, 2);
        CustomerCashbackLedger::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'amount' => $cashbackAmount,
            'status' => 'pending_maturation',
            'matures_at' => $deliveredAt->copy()->addHours(24),
            'expires_at' => $deliveredAt->copy()->addMonths(6),
            'notes' => '5% reward from Order #' . $order->order_number,
        ]);

        return [$order, $item];
    }

    /**
     * Test 1: Customer return request strictly fails without photo evidence.
     */
    public function test_customer_return_request_strictly_requires_photo_evidence(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        // Submitting with empty evidence array must fail-closed
        $result = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Element does not heat up',
            null,
            [] // Empty evidence!
        );

        $this->assertFalse($result['success']);
        $this->assertSame('PHOTO_EVIDENCE_REQUIRED', $result['code'] ?? null);
        $this->assertStringContainsString('Mandatory photo evidence', $result['message']);
        $this->assertSame(0, ReturnCase::count());
    }

    /**
     * Test 2: Valid customer return creates sovereign case with OTP, status requested, and zero return fee.
     */
    public function test_valid_customer_return_creates_case_with_otp_and_zero_return_fee(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        $result = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Kettle base sparks when turned on',
            null,
            ['evidence/kettle_spark_defect.jpg']
        );

        $this->assertTrue($result['success']);
        $case = $result['case'];
        $this->assertSame('requested', $case->status);
        $this->assertNotNull($case->customer_return_otp);
        $this->assertSame(6, strlen($case->customer_return_otp));
        $this->assertSame('0.00', (string) $case->return_delivery_fee_charged);
        $this->assertSame(1, $case->evidence()->count());
    }

    /**
     * Test 3: Admin route-based rider assignment assigns rider, preserves zero return fee, and records audit trail.
     */
    public function test_admin_assigns_travelling_rider_without_customer_fee(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Coil defective',
            null,
            ['evidence/kettle_coil.jpg']
        );
        $case = $createRes['case'];

        $assignRes = $service->assignReturnCollectionRider(
            $this->adminUser,
            $case,
            $this->riderProfile,
            'hub',
            'Rider already travelling along Aka Road route'
        );

        $this->assertTrue($assignRes['success']);
        $updatedCase = $assignRes['case']->fresh();

        $this->assertSame('approved_for_return', $updatedCase->status);
        $this->assertSame($this->riderUser->id, (int) $updatedCase->returned_by_rider_id);
        $this->assertSame('hub', $updatedCase->destination_type);
        $this->assertSame('0.00', (string) $updatedCase->return_delivery_fee_charged);
        $this->assertSame('Rider already travelling along Aka Road route', $updatedCase->assigned_logistics_route_notes);

        // Binding rule: No duplicate delivery fee, automatic rider payment, or duplicate logistics-company payable
        $this->assertSame(0, \App\Models\FinancialJournalEntry::where('order_id', $order->id)->where('account_name', 'logistics_payable')->count());
        $this->assertSame(0, \App\Models\LogisticsPayoutRequest::count());
    }

    /**
     * Test 4: Bulky return rejects motorcycle/bicycle; strictly requires bulky-capable vehicle (keke/cargo).
     */
    public function test_bulky_return_rejects_motorcycle_and_requires_keke_cargo(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->bulkyProduct);
        $service = app(ReturnAndRefundService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            '55 inch screen cracked',
            null,
            ['evidence/screen_cracked.jpg']
        );
        $case = $createRes['case'];

        // Attempt assignment to standard motorcycle rider
        $rejectRes = $service->assignReturnCollectionRider(
            $this->adminUser,
            $case,
            $this->riderProfile, // Motorcycle!
            'hub'
        );

        $this->assertFalse($rejectRes['success']);
        $this->assertSame('INCOMPATIBLE_VEHICLE_FOR_BULKY_RETURN', $rejectRes['code'] ?? null);
        $this->assertStringContainsString('Bulky return items require a dedicated bulky-capable vehicle', $rejectRes['message']);

        // Now assign to keke cargo driver
        $successRes = $service->assignReturnCollectionRider(
            $this->adminUser,
            $case,
            $this->bulkRiderProfile, // Keke cargo!
            'hub',
            'Assigned dedicated keke cargo transport for bulky TV'
        );

        $this->assertTrue($successRes['success']);
        $this->assertSame('approved_for_return', $case->fresh()->status);
        $this->assertSame($this->bulkRiderUser->id, (int) $case->fresh()->returned_by_rider_id);
    }

    /**
     * Test 5: Customer collection fails with wrong, expired, or replayed OTP.
     */
    public function test_customer_collection_rejects_wrong_or_replayed_otp(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Switch broken',
            null,
            ['evidence/broken_switch.jpg']
        );
        $case = $createRes['case'];

        $service->assignReturnCollectionRider($this->adminUser, $case, $this->riderProfile, 'hub');
        $validOtp = $case->fresh()->customer_return_otp;

        // 1. Wrong OTP
        $wrongRes = $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            '000000',
            'returns/collection_photo.jpg'
        );
        $this->assertFalse($wrongRes['success']);
        $this->assertStringContainsString('Invalid customer return-release OTP', $wrongRes['message']);
        $this->assertSame('approved_for_return', $case->fresh()->status);

        // 2. Missing photo
        $noPhotoRes = $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            $validOtp,
            '' // Empty photo
        );
        $this->assertFalse($noPhotoRes['success']);
        $this->assertStringContainsString('Mandatory photo proof', $noPhotoRes['message']);

        // 3. Successful verification
        $validRes = $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            $validOtp,
            'returns/collection_photo.jpg'
        );
        $this->assertTrue($validRes['success']);
        $this->assertSame('in_transit', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->customer_collected_at);
        $this->assertNull($case->fresh()->customer_return_otp); // Invalidated after use!

        // 4. Replay attack: attempting to use the same OTP again
        $replayRes = $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            $validOtp,
            'returns/collection_photo.jpg'
        );
        $this->assertFalse($replayRes['success']);
        $this->assertStringContainsString('Return case is not awaiting collection', $replayRes['message']);
    }

    /**
     * Test 6: Failed customer collection records failure reason without advancing custody.
     */
    public function test_failed_customer_collection_preserves_customer_custody(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Handle cracked',
            null,
            ['evidence/cracked_handle.jpg']
        );
        $case = $createRes['case'];

        $service->assignReturnCollectionRider($this->adminUser, $case, $this->riderProfile, 'hub');

        // Customer absent when rider arrived at doorstep
        $failRes = $service->recordFailedCustomerCollection(
            $case->fresh(),
            $this->riderProfile,
            'Customer was not at residence; gate locked after 3 calls'
        );

        $this->assertTrue($failRes['success']);
        $refreshed = $case->fresh();
        $this->assertSame('collection_failed', $refreshed->status);
        $this->assertStringContainsString('Customer was not at residence', $refreshed->collection_failed_reason);
        $this->assertNotNull($refreshed->collection_failed_at);
        $this->assertNull($refreshed->customer_collected_at); // Not collected!
    }

    /**
     * Test 7: Hub return receipt requires arrival photo proof and accredited collection point.
     */
    public function test_hub_return_receipt_and_subsequent_inspection(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Base loose',
            null,
            ['evidence/base_loose.jpg']
        );
        $case = $createRes['case'];

        $service->assignReturnCollectionRider($this->adminUser, $case, $this->riderProfile, 'hub');
        $validOtp = $case->fresh()->customer_return_otp;

        $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            $validOtp,
            'returns/pickup_photo.jpg'
        );
        $this->assertSame('in_transit', $case->fresh()->status);

        // 1. Hub receipt without photo must fail
        $noPhotoHub = $service->verifyHubReturnReceipt(
            $this->adminUser,
            $case->fresh(),
            $this->collectionPoint->id,
            ''
        );
        $this->assertFalse($noPhotoHub['success']);

        // 2. Hub receipt with photo succeeds
        $hubRes = $service->verifyHubReturnReceipt(
            $this->adminUser,
            $case->fresh(),
            $this->collectionPoint->id,
            'returns/hub_arrival_inspection_area.jpg'
        );
        $this->assertTrue($hubRes['success']);
        $this->assertSame('received_at_hub', $case->fresh()->status);
        $this->assertSame($this->collectionPoint->id, (int) $case->fresh()->collection_point_id);
        $this->assertNotNull($case->fresh()->hub_received_at);

        // 3. Physical inspection at hub
        $inspectRes = $service->recordHubInspection(
            $this->adminUser,
            $case->fresh(),
            $this->collectionPoint->id,
            'defective_as_claimed',
            'SN-KETTLE-998811',
            true,
            'Tested at bench: internal heating element failed conductivity test',
            'accept_for_refund'
        );
        $this->assertTrue($inspectRes['success']);
        $this->assertSame('inspected', $case->fresh()->status);

        $inspection = ReturnInspection::where('return_case_id', $case->id)->first();
        $this->assertNotNull($inspection);
        $this->assertSame('defective_as_claimed', $inspection->condition_rating);
        $this->assertSame('SN-KETTLE-998811', $inspection->serial_number_or_imei);
    }

    /**
     * Test 8: Supplier return handover requires counter photo and supplier OTP; handles failed handover cleanly.
     */
    public function test_supplier_handover_requires_counter_photo_and_otp_and_failed_handover_preserves_custody(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);
        $doorstepService = app(DoorstepHandshakeService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Kettle defect',
            null,
            ['evidence/kettle.jpg']
        );
        $case = $createRes['case'];

        // Assign to supplier destination
        $service->assignReturnCollectionRider($this->adminUser, $case, $this->riderProfile, 'supplier');
        $validCustomerOtp = $case->fresh()->customer_return_otp;

        $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            $validCustomerOtp,
            'returns/customer_pickup.jpg'
        );

        // Set supplier return OTP on case
        $case->refresh();
        $case->vendor_id = $this->supplierProfile->id;
        $case->vendor_return_otp = '456789';
        $case->status = 'in_transit_back_to_vendor';
        $case->save();

        // 1. Handover without photo must fail
        $noPhotoVendor = $doorstepService->verifyVendorReturnOtp(
            $case->fresh(),
            $this->riderProfile,
            '456789',
            ''
        );
        $this->assertFalse($noPhotoVendor['success']);
        $this->assertStringContainsString('Mandatory counter return photo proof', $noPhotoVendor['message']);

        // 2. Failed supplier handover (shop closed)
        $failVendor = $doorstepService->recordFailedVendorHandover(
            $case->fresh(),
            $this->riderProfile,
            'Supplier counter was closed; shopkeeper not present'
        );
        $this->assertTrue($failVendor['success']);
        $this->assertSame('supplier_handover_failed', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->supplier_handover_failed_at);
        $this->assertNull($case->fresh()->returned_to_vendor_at); // Not returned!

        // 3. Retry and succeed with supplier OTP + counter photo
        $case->refresh();
        $case->status = 'in_transit_back_to_vendor';
        $case->vendor_return_otp = '456789';
        $case->save();

        $successVendor = $doorstepService->verifyVendorReturnOtp(
            $case->fresh(),
            $this->riderProfile,
            '456789',
            'returns/vendor_counter_received.jpg'
        );
        $this->assertTrue($successVendor['success'], 'Failed verifying vendor return OTP: ' . ($successVendor['message'] ?? ''));
        $this->assertSame('returned_to_vendor', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->returned_to_vendor_at);
        $this->assertNull($case->fresh()->vendor_return_otp); // OTP cleared!

        // 4. Replay attempt
        $replayVendor = $doorstepService->verifyVendorReturnOtp(
            $case->fresh(),
            $this->riderProfile,
            '456789',
            'returns/vendor_counter_received.jpg'
        );
        $this->assertFalse($replayVendor['success']);
    }

    /**
     * Test 9: Active return case places hold on supplier payable eligibility, preventing duplicate disbursement.
     */
    public function test_active_return_holds_supplier_payout_eligibility(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);
        $payoutService = app(RetailerSupplierPayoutService::class);

        // Open an active return case
        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Thermostat blown',
            null,
            ['evidence/thermostat.jpg']
        );
        $this->assertTrue($createRes['success']);

        // Check supplier payout eligibility: must be held because an active return case exists!
        $preview = $payoutService->previewEligibleItems($this->supplierProfile);
        $this->assertEmpty($preview, 'Active return case must hold supplier payout eligibility.');

        // Attempting to request payout for this item must fail
        $payoutRes = $payoutService->requestPayout($this->supplierProfile, [$item->id]);
        $this->assertFalse($payoutRes['success']);
        $this->assertStringContainsString('not eligible', $payoutRes['message']);
    }

    /**
     * Test 10: When no suitable travelling rider/route is available, admin arranges appropriate transport without charging customer an additional delivery fee.
     */
    public function test_admin_arranges_transport_when_no_suitable_route_without_customer_fee(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Heater not functioning',
            null,
            ['evidence/heater.jpg']
        );
        $case = $createRes['case'];

        // Admin arranges dedicated transport when no travelling rider is available on the corridor
        $assignRes = $service->assignReturnCollectionRider(
            $this->adminUser,
            $case,
            $this->riderProfile,
            'hub',
            'Admin arranged dedicated transport as no travelling rider was on route'
        );

        $this->assertTrue($assignRes['success']);
        $updatedCase = $assignRes['case']->fresh();

        $this->assertSame('approved_for_return', $updatedCase->status);
        $this->assertSame('0.00', (string) $updatedCase->return_delivery_fee_charged);
        $this->assertSame('Admin arranged dedicated transport as no travelling rider was on route', $updatedCase->assigned_logistics_route_notes);
        $this->assertSame(0, \App\Models\LogisticsPayoutRequest::count());
    }

    /**
     * Test 11: P0 Correct OTP Binding:
     * - Case A collection OTP cannot collect Case B.
     * - Case A collection OTP cannot authorize supplier receipt.
     * - Supplier return OTP cannot authorize customer collection.
     * - Original delivery OTP cannot authorize return collection or supplier return.
     */
    public function test_otp_binding_strict_isolation_across_cases_and_handover_types(): void
    {
        [$orderA, $itemA] = $this->createDeliveredOrder($this->standardProduct);
        [$orderB, $itemB] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);
        $doorstepService = app(DoorstepHandshakeService::class);

        $resA = $service->createReturnCase(
            $this->customer,
            $orderA,
            [['order_item_id' => $itemA->id, 'quantity' => 1]],
            'defective_product',
            'Defect on A',
            null,
            ['evidence/photo_a.jpg']
        );
        $caseA = $resA['case'];

        $resB = $service->createReturnCase(
            $this->customer,
            $orderB,
            [['order_item_id' => $itemB->id, 'quantity' => 1]],
            'defective_product',
            'Defect on B',
            null,
            ['evidence/photo_b.jpg']
        );
        $caseB = $resB['case'];

        $service->assignReturnCollectionRider($this->adminUser, $caseA, $this->riderProfile, 'supplier');
        $service->assignReturnCollectionRider($this->adminUser, $caseB, $this->riderProfile, 'supplier');

        $otpA = $caseA->fresh()->customer_return_otp;
        $otpB = $caseB->fresh()->customer_return_otp;

        $this->assertNotNull($otpA);
        $this->assertNotNull($otpB);

        // 1. Cross-case OTP isolation: Case A's OTP cannot collect Case B
        $crossCaseRes = $service->verifyCustomerReturnCollection(
            $caseB->fresh(),
            $this->riderProfile,
            $otpA, // Wrong case OTP!
            'returns/photo.jpg'
        );
        $this->assertFalse($crossCaseRes['success']);
        $this->assertStringContainsString('Invalid customer return-release OTP', $crossCaseRes['message']);
        $this->assertSame('approved_for_return', $caseB->fresh()->status);

        // 2. Original delivery OTP cannot authorize return collection
        $deliveryOtpRes = $service->verifyCustomerReturnCollection(
            $caseA->fresh(),
            $this->riderProfile,
            $orderA->verification_code, // Delivery OTP!
            'returns/photo.jpg'
        );
        $this->assertFalse($deliveryOtpRes['success']);
        $this->assertStringContainsString('Invalid customer return-release OTP', $deliveryOtpRes['message']);

        // 3. Customer collection OTP cannot authorize supplier receipt
        $caseA->status = 'in_transit_back_to_vendor';
        $caseA->vendor_return_otp = '999111';
        $caseA->save();

        $collectionAsSupplierRes = $doorstepService->verifyVendorReturnOtp(
            $caseA->fresh(),
            $this->riderProfile,
            $otpA, // Attempting customer OTP at supplier counter!
            'returns/vendor_counter.jpg'
        );
        $this->assertFalse($collectionAsSupplierRes['success']);
        $this->assertStringContainsString('Invalid Vendor Return OTP', $collectionAsSupplierRes['message']);
        $this->assertSame('in_transit_back_to_vendor', $caseA->fresh()->status);

        // 4. Supplier return OTP cannot authorize customer collection
        $supplierAsCustomerRes = $service->verifyCustomerReturnCollection(
            $caseB->fresh(),
            $this->riderProfile,
            '999111', // Attempting supplier OTP at customer doorstep!
            'returns/photo.jpg'
        );
        $this->assertFalse($supplierAsCustomerRes['success']);
        $this->assertStringContainsString('Invalid customer return-release OTP', $supplierAsCustomerRes['message']);
    }

    /**
     * Test 12: P0 Concurrency Safety on Customer Collection:
     * Two simultaneous requests using the exact same OTP cannot both succeed.
     * Exactly one advances state and invalidates OTP; competing request fails closed.
     */
    public function test_concurrency_race_condition_prevents_duplicate_customer_collection(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Defect',
            null,
            ['evidence/photo.jpg']
        );
        $case = $createRes['case'];

        $service->assignReturnCollectionRider($this->adminUser, $case, $this->riderProfile, 'hub');
        $validOtp = $case->fresh()->customer_return_otp;

        // Simulate Worker 1 (wins race, processes under row lock)
        $worker1Result = $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            $validOtp,
            'returns/pickup_worker1.jpg'
        );
        $this->assertTrue($worker1Result['success']);
        $this->assertSame('in_transit', $case->fresh()->status);
        $this->assertNull($case->fresh()->customer_return_otp);

        // Simulate Worker 2 (competing simultaneous request with same OTP)
        $worker2Result = $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            $validOtp,
            'returns/pickup_worker2.jpg'
        );
        $this->assertFalse($worker2Result['success']);
        $this->assertStringContainsString('not awaiting collection', $worker2Result['message']);

        // Exactly one transition and one audit log
        $auditCount = \App\Models\AdminAuditLog::where('target_id', $case->id)
            ->where('action', 'customer_return_collection_verified')
            ->count();
        $this->assertSame(1, $auditCount);
    }

    /**
     * Test 13: P0 Concurrency Safety on Supplier Handover:
     * Two simultaneous requests using the exact same supplier OTP cannot both succeed.
     */
    public function test_concurrency_race_condition_prevents_duplicate_supplier_handover(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);
        $doorstepService = app(DoorstepHandshakeService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Defect',
            null,
            ['evidence/photo.jpg']
        );
        $case = $createRes['case'];

        $service->assignReturnCollectionRider($this->adminUser, $case, $this->riderProfile, 'supplier');
        $customerOtp = $case->fresh()->customer_return_otp;
        $service->verifyCustomerReturnCollection($case->fresh(), $this->riderProfile, $customerOtp, 'returns/pickup.jpg');

        $case->refresh();
        $case->vendor_id = $this->supplierProfile->id;
        $case->vendor_return_otp = '789123';
        $case->status = 'in_transit_back_to_vendor';
        $case->save();

        // Worker 1 executes
        $worker1 = $doorstepService->verifyVendorReturnOtp(
            $case->fresh(),
            $this->riderProfile,
            '789123',
            'returns/supplier_counter1.jpg'
        );
        $this->assertTrue($worker1['success']);
        $this->assertSame('returned_to_vendor', $case->fresh()->status);
        $this->assertNull($case->fresh()->vendor_return_otp);

        // Worker 2 competing
        $worker2 = $doorstepService->verifyVendorReturnOtp(
            $case->fresh(),
            $this->riderProfile,
            '789123',
            'returns/supplier_counter2.jpg'
        );
        $this->assertFalse($worker2['success']);
        $this->assertStringContainsString('not in transit to vendor', $worker2['message']);

        $auditCount = \App\Models\AdminAuditLog::where('target_id', $case->id)
            ->where('action', 'vendor_return_otp_handshake_verified')
            ->count();
        $this->assertSame(1, $auditCount);
    }

    /**
     * Test 14: P1 End-to-End Missing Photo Proof:
     * Missing photos at each handover strictly block custody advancement.
     */
    public function test_missing_photos_strictly_prevent_state_advancement_at_all_custody_points(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);
        $doorstepService = app(DoorstepHandshakeService::class);

        // 1. Intake without photo fails
        $intakeFail = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Missing proof test',
            null,
            []
        );
        $this->assertFalse($intakeFail['success']);
        $this->assertSame('PHOTO_EVIDENCE_REQUIRED', $intakeFail['code']);

        // Create valid case
        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Defect',
            null,
            ['evidence/photo.jpg']
        );
        $case = $createRes['case'];
        $service->assignReturnCollectionRider($this->adminUser, $case, $this->riderProfile, 'hub');
        $validOtp = $case->fresh()->customer_return_otp;

        // 2. Collection without photo fails
        $collectFail = $service->verifyCustomerReturnCollection(
            $case->fresh(),
            $this->riderProfile,
            $validOtp,
            '   ' // Whitespace / empty photo
        );
        $this->assertFalse($collectFail['success']);
        $this->assertSame('approved_for_return', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->customer_return_otp);

        // Now collect properly
        $service->verifyCustomerReturnCollection($case->fresh(), $this->riderProfile, $validOtp, 'returns/valid_pickup.jpg');
        $this->assertSame('in_transit', $case->fresh()->status);

        // 3. Hub arrival without photo fails
        $hubFail = $service->verifyHubReturnReceipt(
            $this->adminUser,
            $case->fresh(),
            $this->collectionPoint->id,
            ''
        );
        $this->assertFalse($hubFail['success']);
        $this->assertSame('in_transit', $case->fresh()->status);

        // Hub arrival with photo succeeds
        $service->verifyHubReturnReceipt(
            $this->adminUser,
            $case->fresh(),
            $this->collectionPoint->id,
            'returns/hub_valid.jpg'
        );
        $this->assertSame('received_at_hub', $case->fresh()->status);
    }

    /**
     * Test 15: P1 Transaction Exception / Rollback Safety:
     * When an unhandled error occurs mid-transaction, DB rolls back completely, preserving OTP and original custody.
     */
    public function test_transaction_exception_rolls_back_custody_and_preserves_otp(): void
    {
        [$order, $item] = $this->createDeliveredOrder($this->standardProduct);
        $service = app(ReturnAndRefundService::class);

        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Defect',
            null,
            ['evidence/photo.jpg']
        );
        $case = $createRes['case'];
        $service->assignReturnCollectionRider($this->adminUser, $case, $this->riderProfile, 'hub');
        $validOtp = $case->fresh()->customer_return_otp;

        // Simulate an aborted attempt via transaction rollback
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($case, $validOtp) {
                $locked = ReturnCase::where('id', $case->id)->lockForUpdate()->first();
                $locked->status = 'in_transit';
                $locked->customer_return_otp = null;
                $locked->save();

                throw new \RuntimeException('Simulated sudden storage / connection drop mid-handover');
            });
        } catch (\RuntimeException $e) {
            // Expected simulation exception
        }

        // Verify pristine state after rollback: OTP remains intact, status was NOT advanced
        $refreshed = $case->fresh();
        $this->assertSame('approved_for_return', $refreshed->status);
        $this->assertSame($validOtp, $refreshed->customer_return_otp);
        $this->assertNull($refreshed->customer_collected_at);
    }
}
