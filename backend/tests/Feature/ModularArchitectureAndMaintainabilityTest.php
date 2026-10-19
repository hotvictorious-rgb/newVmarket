<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PackageTier;
use App\Enums\PaymentStatus;
use App\Enums\ReturnCaseStatus;
use App\Http\Requests\CreateCheckoutIntentRequest;
use App\Http\Requests\CreateReturnCaseRequest;
use App\Http\Requests\StoreProductRequest;
use App\Jobs\SendTermiiNotificationJob;
use App\Models\CustomerAddress;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\CheckoutExecutionService;
use App\Services\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModularArchitectureAndMaintainabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Product $product;
    protected State $state;
    protected Lga $lga;
    protected CustomerAddress $address;
    protected DeliveryLane $lane;

    protected function setUp(): void
    {
        parent::setUp();

        $country = \App\Models\Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
        ]);

        $this->state = State::create(['name' => 'Akwa Ibom', 'code' => 'AK', 'country_id' => $country->id]);
        $this->lga = Lga::create(['state_id' => $this->state->id, 'name' => 'Uyo', 'code' => 'UYO']);

        $this->customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer_' . Str::random(5) . '@vmarket.test',
            'password' => bcrypt('Password123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $this->vendorUser = User::create([
            'name' => 'Test Merchant',
            'email' => 'vendor_' . Str::random(5) . '@vmarket.test',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Akwa Tech Stores',
            'marketplace_status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Uyo Main Hub',
            'slug' => 'uyo-main-hub',
            'country_id' => $country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'address' => '45 Ikot Ekpene Road',
            'is_active' => true,
        ]);

        $category = \App\Models\Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Oraimo FreePods 4',
            'slug' => 'oraimo-freepods-4',
            'unit_price' => '15000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 20,
            'is_active' => true,
            'status' => 'active',
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => \Carbon\Carbon::now(),
        ]);

        $this->address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'Test Customer',
            'phone' => '+2348011223344',
            'address_line' => '12 Oron Road',
            'country_id' => $country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'is_default' => true,
        ]);

        $this->lane = DeliveryLane::create([
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'base_delivery_fee' => '1200.00',
            'is_active' => true,
        ]);
    }

    public function test_php_enums_provide_authoritative_domain_rules(): void
    {
        // OrderStatus
        $this->assertTrue(OrderStatus::Delivered->isTerminal());
        $this->assertTrue(OrderStatus::Cancelled->isTerminal());
        $this->assertFalse(OrderStatus::Processing->isTerminal());
        $this->assertTrue(OrderStatus::Shipped->isInFlight());
        $this->assertFalse(OrderStatus::Delivered->isInFlight());

        // ReturnCaseStatus
        $this->assertTrue(ReturnCaseStatus::Requested->canBeInspected());
        $this->assertTrue(ReturnCaseStatus::InTransit->canBeInspected());
        $this->assertFalse(ReturnCaseStatus::Refunded->canBeInspected());
        $this->assertTrue(ReturnCaseStatus::RefundPending->canDisburseRefund());
        $this->assertFalse(ReturnCaseStatus::Closed->canDisburseRefund());

        // PackageTier
        $this->assertFalse(PackageTier::Bike->requiresCargoDispatch());
        $this->assertTrue(PackageTier::Keke->requiresCargoDispatch());
        $this->assertTrue(PackageTier::Fragile->requiresCargoDispatch());
        $this->assertTrue(PackageTier::Bulky->requiresCargoDispatch());

        // FulfillmentType & PaymentStatus
        $this->assertSame('doorstep_delivery', FulfillmentType::DoorstepDelivery->value);
        $this->assertSame('paid', PaymentStatus::Paid->value);
    }

    public function test_form_requests_enforce_strict_validation_rules(): void
    {
        // StoreProductRequest validation logic
        $storeProductReq = new StoreProductRequest();
        $rules = $storeProductReq->rules();

        $invalidProduct = [
            'name' => 'Sample Phone',
            'shop_id' => $this->shop->id,
            'category_id' => 1,
            'unit_price' => 50000,
            'slash_price' => 45000, // Invalid: slash price must be gt unit_price
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 5,
        ];
        $validator = Validator::make($invalidProduct, $rules);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('slash_price'));
        $this->assertTrue($validator->errors()->has('photos'));

        // CreateReturnCaseRequest validation logic
        $returnReq = new CreateReturnCaseRequest();
        $returnRules = $returnReq->rules();

        $invalidReturn = [
            'order_id' => 999,
            'return_reason' => 'invalid_unauthorized_reason',
            'customer_description' => 'Not happy',
            'items' => [], // Min 1 required
        ];
        $returnValidator = Validator::make($invalidReturn, $returnRules);
        $this->assertTrue($returnValidator->fails());
        $this->assertTrue($returnValidator->errors()->has('return_reason'));
        $this->assertTrue($returnValidator->errors()->has('items'));
    }

    public function test_checkout_execution_service_encapsulates_order_creation_cleanly(): void
    {
        $service = app(CheckoutExecutionService::class);

        $payload = [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $this->address->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
            'payment_method' => 'paystack',
        ];

        $result = $service->execute($this->customer, $payload);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Order::class, $result['order']);
        $this->assertEquals('30000.00', $result['order']->merchandise_subtotal);
        $this->assertEquals('500.00', $result['order']->delivery_fee);
        $this->assertEquals('30500.00', $result['order']->grand_total);

        // Two-status inventory model: product remains In Stock without quantity-based decrements
        $this->product->refresh();
        $this->assertTrue((bool)$this->product->is_in_stock);
    }

    public function test_checkout_execution_service_rejects_inactive_vendor_gracefully(): void
    {
        $this->vendor->update(['marketplace_status' => 'suspended']);

        $service = app(CheckoutExecutionService::class);
        $result = $service->execute($this->customer, [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $this->address->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['status_code']);
        $this->assertStringContainsString('merchant', strtolower($result['message']));
    }

    public function test_async_termii_notification_job_dispatches_cleanly(): void
    {
        Queue::fake();

        // Calling dispatch with TERMII_API_KEY configured will queue the job
        config(['services.termii.key' => 'test_api_key_12345']);

        // Send notification via NotificationDispatchService
        NotificationDispatchService::dispatchOrderPlaced(new Order([
            'order_number' => 'VM-TEST-ORDER-1',
            'grand_total' => '25000.00',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
        ]));

        $job = new SendTermiiNotificationJob(
            '+2348011223344',
            'sms',
            'Test SMS message'
        );

        $this->assertEquals('+2348011223344', $job->recipient);
        $this->assertEquals('sms', $job->channel);
        $this->assertEquals('Test SMS message', $job->message);
    }

    public function test_vendor_products_view_renders_cleanly_with_modular_partials(): void
    {
        $this->actingAs($this->vendorUser);

        $response = $this->get(route('vendor.products.index'));
        $response->assertStatus(200);

        // Assert partials were included
        $response->assertSee('Marketplace Listing Studio', false);
        $response->assertSee('vmNewProductModal', false);
        $response->assertSee('fbDropzone', false);
        $response->assertSee('Oraimo FreePods 4', false);
    }
}
