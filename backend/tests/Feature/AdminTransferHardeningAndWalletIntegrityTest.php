<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserRole;
use App\Models\Category;
use App\Models\Country;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\RetailerSupplierPayoutAllocation;
use App\Models\RetailerSupplierPayoutRequest;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorPayoutRequest;
use App\Models\VendorProfile;
use App\Models\VendorRecoveryLedger;
use App\Services\FinancialReconciliationEngine;
use App\Services\PaystackService;
use App\Services\RetailerSupplierPayoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AdminTransferHardeningAndWalletIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularAdmin;
    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected User $logisticsUser;
    protected LogisticsCompanyProfile $company;
    protected Shop $shop;
    protected Lga $uyoLga;
    protected Lga $eketLga;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Geographic reference setup
        $country = Country::firstOrCreate(
            ['iso_code' => 'NG'],
            ['name' => 'Nigeria', 'phone_code' => '+234', 'currency_code' => 'NGN', 'is_active' => true]
        );
        $state = State::firstOrCreate(
            ['code' => 'AK'],
            ['country_id' => $country->id, 'name' => 'Akwa Ibom', 'is_active' => true]
        );
        $this->uyoLga = Lga::firstOrCreate(
            ['code' => 'UYO'],
            ['state_id' => $state->id, 'name' => 'Uyo', 'is_active' => true]
        );
        $this->eketLga = Lga::firstOrCreate(
            ['code' => 'EKT'],
            ['state_id' => $state->id, 'name' => 'Eket', 'is_active' => true]
        );

        // Admins
        $this->superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
        $this->regularAdmin = User::factory()->create([
            'user_type' => 'admin_employee',
            'status' => 'active',
        ]);

        // Customer
        $this->customer = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // Vendor
        $this->vendorUser = User::factory()->create([
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Victorious Electronics Ltd',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
            'bank_name' => 'Zenith Bank PLC',
            'bank_code' => '057',
            'account_number' => '1012345678',
            'account_name' => 'Victorious Electronics Ltd',
            'bank_updated_at' => Carbon::now()->subDays(5),
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Victorious Electronics Main Shop',
            'slug' => 'victorious-electronics-main-shop',
            'address' => '12 Oron Road, Uyo',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
        ]);

        // Logistics Company
        $this->logisticsUser = User::factory()->create([
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);
        $this->company = LogisticsCompanyProfile::create([
            'user_id' => $this->logisticsUser->id,
            'company_name' => 'Swift Courier Express',
            'status' => 'active',
            'wallet_balance' => '50000.00',
            'bank_name' => 'Access Bank PLC',
            'bank_code' => '044',
            'account_number' => '0123456789',
            'account_name' => 'Swift Courier Express Ltd',
            'bank_updated_at' => Carbon::now()->subDays(5),
        ]);

        // Mock Paystack
        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('createTransferRecipient')->andReturn([
            'success' => true,
            'recipient_code' => 'RCP_MOCK_123',
        ]);
        $mock->shouldReceive('initiateTransfer')->andReturn([
            'success' => true,
            'status' => 'success',
            'transfer_code' => 'TRF_MOCK_777',
            'message' => 'Transfer queued for execution',
        ]);
        $this->app->instance(PaystackService::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createTestOrder(array $attributes = []): Order
    {
        $amount = $attributes['merchandise_subtotal'] ?? '10000.00';
        $net90 = $attributes['vendor_net_90'] ?? bcmul((string)$amount, '0.90', 2);
        $cb5 = bcmul((string)$amount, '0.05', 2);
        $pm5 = bcmul((string)$amount, '0.05', 2);

        return Order::create(array_merge([
            'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_number' => 'ORD-TST-' . uniqid(),
            'payment_reference' => 'REF-TST-' . uniqid(),
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'vendor_settlement_status' => 'eligible',
            'merchandise_subtotal' => $amount,
            'grand_total' => $amount,
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '0.00',
            'vendor_net_90' => $net90,
            'customer_cashback_5' => $cb5,
            'platform_margin_5' => $pm5,
        ], $attributes));
    }

    protected function createSupplierPayableOrderItem(string $supplierCost = '7000.00'): array
    {
        $category = Category::create([
            'name' => 'Supplier Payout Test Category',
            'slug' => 'supplier-payout-test-' . uniqid(),
            'is_active' => true,
            'priority' => 1,
        ]);
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Supplier Payout Test Product',
            'slug' => 'supplier-payout-product-' . uniqid(),
            'unit_price' => '10000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $order = $this->createTestOrder([
            'retailer_model_order' => true,
            'refund_window_expires_at' => Carbon::now()->subDay(),
            'delivered_at' => Carbon::now()->subDays(2),
            'supplier_cost_total_snapshot' => $supplierCost,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
            'supplier_vendor_id' => $this->vendor->id,
            'supplier_shop_id' => $this->shop->id,
            'supplier_cost_snapshot' => $supplierCost,
            'supplier_snapshot_at' => Carbon::now(),
            'fulfillment_supplier_vendor_id' => $this->vendor->id,
            'fulfillment_supplier_shop_id' => $this->shop->id,
            'fulfillment_supplier_cost_snapshot' => $supplierCost,
            'fulfillment_supplier_snapshot_at' => Carbon::now(),
        ]);

        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'transaction_reference' => $order->payment_reference,
            'event_id' => 'EVT-SUPPLIER-PAYABLE-' . $order->id . '-ITEM-' . $item->id . '-TEST',
            'idempotency_key' => 'SUPPAYOUT-TEST-CAPTURE-' . $order->id . '-' . $item->id,
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendor->id,
            'account_name' => 'supplier_payable',
            'entry_type' => 'credit',
            'amount' => $supplierCost,
            'currency' => 'NGN',
            'description' => 'Agreed wholesale supplier payable for order item #' . $item->id,
        ]);

        return [$order, $item];
    }

    public function test_supplier_payout_request_does_not_reserve_funds_during_bank_cooldown(): void
    {
        $this->vendor->update(['bank_updated_at' => Carbon::now()]);

        $result = app(RetailerSupplierPayoutService::class)->requestPayout($this->vendor->fresh());

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected'] ?? false);
        $this->assertStringContainsString('48-hour security cooldown', $result['message']);
        $this->assertDatabaseCount('retailer_supplier_payout_requests', 0);
        $this->assertDatabaseCount('retailer_supplier_payout_allocations', 0);
    }

    public function test_supplier_payout_request_rejects_shop_outside_uyo(): void
    {
        $eketLga = Lga::firstOrCreate(
            ['state_id' => $this->uyoLga->state_id, 'name' => 'Eket'],
            ['is_active' => true]
        );
        $this->shop->update(['lga_id' => $eketLga->id]);

        $result = app(RetailerSupplierPayoutService::class)->requestPayout($this->vendor->fresh());

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected'] ?? false);
        $this->assertStringContainsString('Uyo, Akwa Ibom State', $result['message']);
        $this->assertDatabaseCount('retailer_supplier_payout_requests', 0);
        $this->assertDatabaseCount('retailer_supplier_payout_allocations', 0);
    }

    public function test_retailer_supplier_payout_reserves_and_finalizes_item_payable_idempotently(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('7000.00');
        $service = app(RetailerSupplierPayoutService::class);

        $requestResult = $service->requestPayout($this->vendor);
        $this->assertTrue($requestResult['success'], $requestResult['message'] ?? 'Supplier payout request failed.');
        /** @var RetailerSupplierPayoutRequest $payout */
        $payout = $requestResult['payout'];
        $this->assertSame('7000.00', (string) $payout->amount);
        $this->assertSame(1, $payout->allocations->count());
        $this->assertTrue($service->requestJournalComplete($payout));

        $duplicateRequest = $service->requestPayout($this->vendor);
        $this->assertFalse($duplicateRequest['success']);
        $this->assertSame(1, RetailerSupplierPayoutRequest::where('supplier_vendor_id', $this->vendor->id)->count());

        $processing = $service->markProcessing($payout->id, $this->superAdmin->id, 'RCP_TEST_SUPPLIER', 'SUPPAYOUT-TEST-PROCESSING');
        $this->assertTrue($processing['success'], $processing['message'] ?? 'Supplier payout processing claim failed.');

        $finalized = $service->finalizeSuccess($payout->id, $payout->transfer_reference, 'TRF_TEST_SUPPLIER', '7000.00');
        $this->assertTrue($finalized['success'], $finalized['message'] ?? 'Supplier payout finalization failed.');
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('settled', $payout->allocations()->first()->fresh()->status);

        $audit = app(FinancialReconciliationEngine::class)->reconcileRetailerSupplierPayables($order->id);
        $this->assertSame(0, $audit['mismatches'], json_encode($audit['errors'], JSON_UNESCAPED_SLASHES));

        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();
        $repeatFinalize = $service->finalizeSuccess($payout->id, $payout->transfer_reference, 'TRF_TEST_SUPPLIER', '7000.00');
        $this->assertTrue($repeatFinalize['success']);
        $this->assertTrue($repeatFinalize['idempotent'] ?? false);
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_retailer_supplier_payout_reversal_restores_item_payable_once(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('9000.00');
        $service = app(RetailerSupplierPayoutService::class);
        $requestResult = $service->requestPayout($this->vendor);
        $this->assertTrue($requestResult['success'], $requestResult['message'] ?? 'Supplier payout request failed.');
        $payout = $requestResult['payout'];

        $reversed = $service->reverse($payout->id, 'not_submitted', 'Admin rejected before gateway submission.');
        $this->assertTrue($reversed['success'], $reversed['message'] ?? 'Supplier payout reservation release failed.');
        $this->assertSame('rejected', $payout->fresh()->status);
        $this->assertSame('reversed', $payout->allocations()->first()->fresh()->status);

        $netSupplierPayable = FinancialJournalEntry::where('order_item_id', $item->id)
            ->where('account_name', 'supplier_payable')
            ->where('beneficiary_type', VendorProfile::class)
            ->where('beneficiary_id', $this->vendor->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE -amount END), 0) AS net_amount")
            ->value('net_amount');
        $this->assertEquals('9000.00', number_format((float) $netSupplierPayable, 2, '.', ''));

        $audit = app(FinancialReconciliationEngine::class)->reconcileRetailerSupplierPayables($order->id);
        $this->assertSame(0, $audit['mismatches'], json_encode($audit['errors'], JSON_UNESCAPED_SLASHES));

        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();
        $repeatReverse = $service->reverse($payout->id, 'not_submitted', 'Duplicate rejection retry.');
        $this->assertTrue($repeatReverse['success']);
        $this->assertTrue($repeatReverse['idempotent'] ?? false);
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_legacy_vendor_payout_request_is_also_blocked_by_open_supplier_recovery_debt(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $returnCase = ReturnCase::create([
            'case_number' => 'RET-LEGACY-REC-' . strtoupper(substr(sha1((string) uniqid('', true)), 0, 8)),
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'defective_product',
            'status' => 'under_review',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '8000.00',
        ]);
        VendorRecoveryLedger::create([
            'vendor_id' => $this->vendor->id,
            'return_case_id' => $returnCase->id,
            'order_id' => $order->id,
            'amount' => '1000.00',
            'status' => 'pending_recovery',
            'notes' => 'Unresolved supplier recovery fixture.',
        ]);

        $response = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '5000.00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        Sanctum::actingAs($this->vendorUser);
        $apiResponse = $this->postJson('/api/v1/vendor/payouts', ['amount' => '5000.00']);
        $apiResponse->assertStatus(409)->assertJsonPath('code', 'SUPPLIER_RECOVERY_DEBT_HOLD');
        $this->assertSame(0, VendorPayoutRequest::where('vendor_id', $this->vendor->id)->count());
    }

    public function test_supplier_payout_is_held_while_recovery_debt_is_unresolved(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $returnCase = ReturnCase::create([
            'case_number' => 'RET-RECOVERY-' . strtoupper(substr(sha1((string) uniqid('', true)), 0, 10)),
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'defective_product',
            'status' => 'under_review',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '8000.00',
        ]);
        VendorRecoveryLedger::create([
            'vendor_id' => $this->vendor->id,
            'return_case_id' => $returnCase->id,
            'order_id' => $order->id,
            'amount' => '1000.00',
            'status' => 'pending_recovery',
            'notes' => 'Unresolved supplier recovery fixture.',
        ]);
        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();

        $result = app(RetailerSupplierPayoutService::class)->requestPayout($this->vendor);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected'] ?? false);
        $this->assertStringContainsString('recovery debt', strtolower($result['message'] ?? ''));
        $this->assertSame(0, RetailerSupplierPayoutRequest::where('supplier_vendor_id', $this->vendor->id)->count());
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_supplier_payout_is_blocked_until_supplier_kyc_is_verified(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $this->vendor->update(['kyc_status' => 'submitted']);
        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();

        $result = app(RetailerSupplierPayoutService::class)->requestPayout($this->vendor->fresh());

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected'] ?? false);
        $this->assertStringContainsString('KYC', $result['message'] ?? '');
        $this->assertSame(0, RetailerSupplierPayoutRequest::where('supplier_vendor_id', $this->vendor->id)->count());
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_supplier_payout_rejects_bank_snapshot_changed_after_request(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $service = app(RetailerSupplierPayoutService::class);
        $requested = $service->requestPayout($this->vendor);
        $this->assertTrue($requested['success'], $requested['message'] ?? 'Supplier payout request failed.');
        $payout = $requested['payout'];

        $this->vendor->update([
            'account_number' => '1098765432',
            'bank_updated_at' => Carbon::now()->subDays(5),
        ]);
        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();
        $processing = $service->markProcessing($payout->id, $this->superAdmin->id, 'RCP_TEST_SUPPLIER', 'SUPPAYOUT-BANK-RECHECK');

        $this->assertFalse($processing['success']);
        $this->assertTrue($processing['protected'] ?? false);
        $this->assertSame('pending', $payout->fresh()->status);
        $this->assertSame('allocated', $payout->allocations()->first()->fresh()->status);
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_supplier_payout_is_held_if_owner_account_becomes_inactive(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $service = app(RetailerSupplierPayoutService::class);
        $requested = $service->requestPayout($this->vendor);
        $this->assertTrue($requested['success'], $requested['message'] ?? 'Supplier payout request failed.');
        $payout = $requested['payout'];

        $this->vendorUser->update(['status' => 'suspended']);
        $processing = $service->markProcessing($payout->id, $this->superAdmin->id, 'RCP_TEST_SUPPLIER', 'SUPPAYOUT-OWNER-INACTIVE');

        $this->assertFalse($processing['success']);
        $this->assertTrue($processing['protected'] ?? false);
        $this->assertSame('pending', $payout->fresh()->status);
        $this->assertSame('allocated', $payout->allocations()->first()->fresh()->status);
    }

    public function test_supplier_payout_revalidates_kyc_before_gateway_submission(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $service = app(RetailerSupplierPayoutService::class);
        $requested = $service->requestPayout($this->vendor);
        $this->assertTrue($requested['success'], $requested['message'] ?? 'Supplier payout request failed.');
        $payout = $requested['payout'];

        $this->vendor->update(['kyc_status' => 'submitted']);
        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();
        $processing = $service->markProcessing($payout->id, $this->superAdmin->id, 'RCP_TEST_SUPPLIER', 'SUPPAYOUT-KYC-RECHECK');

        $this->assertFalse($processing['success']);
        $this->assertTrue($processing['protected'] ?? false);
        $this->assertSame('pending', $payout->fresh()->status);
        $this->assertSame('allocated', $payout->allocations()->first()->fresh()->status);
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_treasury_cannot_reconcile_supplier_transfer_before_submission_response_is_persisted(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $service = app(RetailerSupplierPayoutService::class);
        $requested = $service->requestPayout($this->vendor);
        $this->assertTrue($requested['success'], $requested['message'] ?? 'Supplier payout request failed.');
        $payout = $requested['payout'];
        $processing = $service->markProcessing($payout->id, $this->superAdmin->id, 'RCP_TEST_SUPPLIER', 'SUPPAYOUT-IN-FLIGHT-RECON');
        $this->assertTrue($processing['success'], $processing['message'] ?? 'Supplier payout processing claim failed.');

        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();
        $response = $this->actingAs($this->superAdmin)->post(
            route('admin.retailer-supplier-payouts.reconcile', $payout->id)
        );

        $response->assertRedirect();
        $this->assertSame('uncertain', $payout->fresh()->status);
        $this->assertSame('submission_outcome_unknown', $payout->fresh()->gateway_status);
        $this->assertSame('allocated', $payout->allocations()->first()->fresh()->status);
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_verified_success_can_finalize_supplier_payout_after_uncertain_hold(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $service = app(RetailerSupplierPayoutService::class);
        $requested = $service->requestPayout($this->vendor);
        $this->assertTrue($requested['success'], $requested['message'] ?? 'Supplier payout request failed.');
        $payout = $requested['payout'];

        $processing = $service->markProcessing($payout->id, $this->superAdmin->id, 'RCP_TEST_SUPPLIER', 'SUPPAYOUT-UNCERTAIN-SUCCESS');
        $this->assertTrue($processing['success'], $processing['message'] ?? 'Supplier payout processing claim failed.');
        $held = $service->markUncertain($payout->id, 'Timeout after Paystack may have accepted the transfer.', 'UNCERTAIN_TIMEOUT');
        $this->assertTrue($held['success']);
        $this->assertSame('uncertain', $payout->fresh()->status);

        $finalized = $service->finalizeSuccess($payout->id, $payout->transfer_reference, 'TRF_CONFIRMED_AFTER_TIMEOUT', '8000.00');
        $this->assertTrue($finalized['success'], $finalized['message'] ?? 'Verified uncertain payout could not be finalized.');
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('settled', $payout->allocations()->first()->fresh()->status);

        $audit = app(FinancialReconciliationEngine::class)->reconcileRetailerSupplierPayables($order->id);
        $this->assertSame(0, $audit['mismatches'], json_encode($audit['errors'], JSON_UNESCAPED_SLASHES));
    }

    public function test_supplier_payout_uncertain_outcome_cannot_be_released_as_unsubmitted(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8000.00');
        $service = app(RetailerSupplierPayoutService::class);
        $requested = $service->requestPayout($this->vendor);
        $this->assertTrue($requested['success'], $requested['message'] ?? 'Supplier payout request failed.');
        $payout = $requested['payout'];

        $processing = $service->markProcessing($payout->id, $this->superAdmin->id, 'RCP_TEST_SUPPLIER', 'SUPPAYOUT-UNCERTAIN-TEST');
        $this->assertTrue($processing['success'], $processing['message'] ?? 'Supplier payout processing claim failed.');
        $held = $service->markUncertain($payout->id, 'Simulated connection timeout after transfer submission.', 'UNCERTAIN_TIMEOUT');
        $this->assertTrue($held['success']);
        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();

        $unsafeRelease = $service->reverse($payout->id, 'not_submitted', 'Attempt to release an uncertain transfer.');
        $this->assertFalse($unsafeRelease['success']);
        $this->assertTrue($unsafeRelease['protected'] ?? false);
        $this->assertSame('uncertain', $payout->fresh()->status);
        $this->assertSame('allocated', $payout->allocations()->first()->fresh()->status);
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_supplier_payout_reversal_holds_on_partial_gateway_journal_evidence(): void
    {
        [$order, $item] = $this->createSupplierPayableOrderItem('8500.00');
        $service = app(RetailerSupplierPayoutService::class);
        $requested = $service->requestPayout($this->vendor);
        $this->assertTrue($requested['success'], $requested['message'] ?? 'Supplier payout request failed.');
        $payout = $requested['payout'];

        $processing = $service->markProcessing($payout->id, $this->superAdmin->id, 'RCP_TEST_SUPPLIER', 'SUPPAYOUT-PARTIAL-TEST');
        $this->assertTrue($processing['success'], $processing['message'] ?? 'Supplier payout processing claim failed.');

        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'transaction_reference' => $payout->transfer_reference,
            'event_id' => 'EVT-SUPPLIER-PAYOUT-' . $payout->id . '-ITEM-' . $item->id,
            'idempotency_key' => 'SUPPAYOUT-PARTIAL-' . $payout->id . '-' . $item->id,
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendor->id,
            'account_name' => 'supplier_payout_payable',
            'entry_type' => 'debit',
            'amount' => '8500.00',
            'currency' => 'NGN',
            'description' => 'Intentionally incomplete gateway payout journal fixture.',
        ]);
        $journalCount = FinancialJournalEntry::where('order_id', $order->id)->count();

        $reversal = $service->reverse($payout->id, 'reversed', 'Simulated reversal with incomplete local payout journal.');
        $this->assertFalse($reversal['success']);
        $this->assertTrue($reversal['protected'] ?? false);
        $this->assertSame('processing', $payout->fresh()->status);
        $this->assertSame('allocated', $payout->allocations()->first()->fresh()->status);
        $this->assertSame($journalCount, FinancialJournalEntry::where('order_id', $order->id)->count());
    }

    public function test_vendor_payout_disbursement_blocked_if_vendor_is_suspended_or_trashed()
    {
        $this->vendor->update(['marketplace_status' => 'suspended']);

        $this->createTestOrder();

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Merchant store is inactive, suspended, or archived', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    public function test_vendor_payout_disbursement_blocked_if_vendor_user_account_is_suspended()
    {
        $this->vendorUser->update(['status' => 'suspended']);

        $this->createTestOrder();

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Merchant user account is inactive or suspended', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    public function test_vendor_payout_disbursement_blocked_if_vendor_has_never_sold_or_delivered()
    {
        // No orders created for vendor
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Merchant has no verified delivered sales records', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    public function test_vendor_withdrawable_balance_does_not_automatically_offset_unrecovered_debts()
    {
        $order = $this->createTestOrder([
            'merchandise_subtotal' => '100000.00',
            'vendor_net_90' => '90000.00',
            'grand_total' => '100000.00',
        ]);

        $this->assertEquals('90000.00', $this->vendor->getWithdrawableBalance());

        // Create resolved return case as parent reference
        $returnCase = ReturnCase::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'case_number' => 'RET-CASE-999',
            'return_reason' => 'defective_product',
            'status' => 'refunded',
            'total_refundable_amount' => '25000.00',
        ]);

        // Incur unrecovered debt in VendorRecoveryLedger: ₦25,000.00
        VendorRecoveryLedger::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'return_case_id' => $returnCase->id,
            'amount' => '25000.00',
            'status' => 'pending_recovery',
            'notes' => 'Damaged return compensation',
        ]);

        // Recovery debt remains a separate receivable; no automatic offset without authorised approval.
        $this->assertEquals('90000.00', $this->vendor->getWithdrawableBalance());
    }

    public function test_vendor_withdrawable_balance_quarantines_orders_with_active_return_cases()
    {
        $order = $this->createTestOrder([
            'merchandise_subtotal' => '50000.00',
            'vendor_net_90' => '45000.00',
            'grand_total' => '50000.00',
        ]);

        $this->assertEquals('45000.00', $this->vendor->getWithdrawableBalance());

        // Create an active ReturnCase
        ReturnCase::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'case_number' => 'RET-ORD-002',
            'return_reason' => 'damaged_in_transit',
            'status' => 'requested',
            'total_refundable_amount' => '20000.00',
        ]);

        // Order is immediately quarantined from withdrawable balance!
        $this->assertEquals('0.00', $this->vendor->fresh()->getWithdrawableBalance());
    }

    public function test_vendor_payout_rejection_records_balanced_double_entry_reversal()
    {
        $this->createTestOrder([
            'merchandise_subtotal' => '33333.33',
            'vendor_net_90' => '30000.00',
            'grand_total' => '33333.33',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '30000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-MERCHANT-REQ-' . $payout->id,
            'event_id' => 'EVT-MERCHANT-REQ-' . $payout->id,
            'beneficiary_type' => \App\Models\VendorProfile::class,
            'beneficiary_id' => $this->vendor->id,
            'account_name' => 'vendor_payable',
            'entry_type' => 'debit',
            'amount' => '30000.00',
            'currency' => 'NGN',
            'description' => 'Test request journal debit.',
        ]);
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-MERCHANT-REQ-' . $payout->id,
            'event_id' => 'EVT-MERCHANT-REQ-' . $payout->id,
            'beneficiary_type' => \App\Models\VendorProfile::class,
            'beneficiary_id' => $this->vendor->id,
            'account_name' => 'vendor_payout_payable',
            'entry_type' => 'credit',
            'amount' => '30000.00',
            'currency' => 'NGN',
            'description' => 'Test request journal credit.',
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.vendor_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Invalid BVN details provided.',
        ]);

        $response->assertSessionHas('status');
        $this->assertEquals('rejected', $payout->fresh()->status);
        $this->assertEquals('Invalid BVN details provided.', $payout->fresh()->rejection_reason);

        // Verify balanced journal reversal entries
        $debitEntry = FinancialJournalEntry::where('beneficiary_id', $this->vendor->id)
            ->where('account_name', 'vendor_payout_payable')
            ->where('entry_type', 'debit')
            ->latest('id')
            ->first();

        $creditEntry = FinancialJournalEntry::where('beneficiary_id', $this->vendor->id)
            ->where('account_name', 'vendor_payable')
            ->where('entry_type', 'credit')
            ->latest('id')
            ->first();

        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertEquals('30000.00', number_format((float)$debitEntry->amount, 2, '.', ''));
        $this->assertEquals('30000.00', number_format((float)$creditEntry->amount, 2, '.', ''));
    }

    /**
     * P0 regression: an uncertain logistics transfer response must remain on reconciliation hold.
     */
    public function test_logistics_payout_initiation_timeout_keeps_reconciliation_hold(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '20000.00',
            'status' => 'pending',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('createTransferRecipient')->once()->andReturn([
            'success' => true,
            'recipient_code' => 'RCP_FLEET_TIMEOUT',
        ]);
        $mock->shouldReceive('initiateTransfer')->once()->andReturn([
            'success' => false,
            'code' => 'UNCERTAIN_TIMEOUT',
            'is_definitive' => false,
            'message' => 'Connection timed out after submitting transfer request.',
        ]);
        $this->app->instance(PaystackService::class, $mock);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.logistics_payout'), [
            'payout_id' => $payout->id,
            'notes' => 'Regression test for uncertain gateway outcome',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('HOLD_TIMEOUT_RECONCILIATION', session('error'));

        $payout->refresh();
        $this->assertEquals('pending', $payout->status);
        $this->assertEquals('HOLD_TIMEOUT_RECONCILIATION', $payout->processing_token);
        $this->assertEquals('processing', $payout->gateway_status);
        $this->assertNotNull($payout->transfer_reference);
    }

    public function test_logistics_payout_disbursement_blocked_if_company_or_user_suspended()
    {
        $this->logisticsUser->update(['status' => 'suspended']);

        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '20000.00',
            'status' => 'pending',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.logistics_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Logistics company user account is inactive or suspended', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    public function test_logistics_payout_rejection_records_balanced_double_entry_reversal()
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        // Model the request-time reservation journal created by logisticsRequestPayout().
        foreach ([
            ['account_name' => 'logistics_company_wallet', 'entry_type' => 'debit'],
            ['account_name' => 'logistics_payout_payable', 'entry_type' => 'credit'],
        ] as $index => $entry) {
            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => 'TEST-FLEET-REQ-' . $payout->id,
                'event_id' => 'EVT-FLEET-REQ-' . $payout->id,
                'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                'beneficiary_id' => $this->company->id,
                'account_name' => $entry['account_name'],
                'entry_type' => $entry['entry_type'],
                'amount' => '15000.00',
                'currency' => 'NGN',
                'description' => 'Test request-time wallet reservation.',
            ]);
        }

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.logistics_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Discrepancy in dispatch records.',
        ]);

        $response->assertSessionHas('status');
        $this->assertEquals('rejected', $payout->fresh()->status);

        // Verify balanced journal reversal entries
        $debitEntry = FinancialJournalEntry::where('beneficiary_id', $this->company->id)
            ->where('account_name', 'logistics_payout_payable')
            ->where('entry_type', 'debit')
            ->latest('id')
            ->first();

        $creditEntry = FinancialJournalEntry::where('beneficiary_id', $this->company->id)
            ->where('account_name', 'logistics_company_wallet')
            ->where('entry_type', 'credit')
            ->latest('id')
            ->first();

        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertEquals('15000.00', number_format((float)$debitEntry->amount, 2, '.', ''));
        $this->assertEquals('15000.00', number_format((float)$creditEntry->amount, 2, '.', ''));
    }

    public function test_logistics_payout_rejection_is_blocked_while_gateway_outcome_is_uncertain(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'transfer_reference' => 'FLEET-PAYOUT-UNCERTAIN-001',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);
        $walletBefore = (string) $this->company->fresh()->wallet_balance;
        $journalCountBefore = FinancialJournalEntry::where('event_id', 'EVT-FLEET-REJECT-' . $payout->id)->count();

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.logistics_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Gateway result requires reconciliation.',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('pending', $payout->fresh()->status);
        $this->assertEquals($walletBefore, (string) $this->company->fresh()->wallet_balance);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::where('event_id', 'EVT-FLEET-REJECT-' . $payout->id)->count());
    }

    public function test_vendor_payout_rejection_is_blocked_while_processing_claim_exists(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'processing_token' => 'CLAIM-VND-UNCERTAIN-001',
            'transfer_reference' => 'VND-PAYOUT-UNCERTAIN-001',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);
        $journalCountBefore = FinancialJournalEntry::where('event_id', 'EVT-MERCHANT-REJECT-' . $payout->id)->count();

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.vendor_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Gateway result requires reconciliation.',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('pending', $payout->fresh()->status);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::where('event_id', 'EVT-MERCHANT-REJECT-' . $payout->id)->count());
    }

    public function test_stale_vendor_and_logistics_processing_claims_are_not_automatically_retried(): void
    {
        $vendorPayout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'processing_token' => 'CLAIM-VND-STALE-001',
            'processing_started_at' => now()->subMinutes(10),
            'transfer_reference' => 'VND-PAYOUT-STALE-001',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $vendorResponse = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $vendorPayout->id,
        ]);
        $vendorResponse->assertSessionHas('error');
        $this->assertEquals('pending', $vendorPayout->fresh()->status);
        $this->assertEquals('CLAIM-VND-STALE-001', $vendorPayout->fresh()->processing_token);

        $logisticsPayout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'processing_token' => 'CLAIM-FLEET-STALE-001',
            'processing_started_at' => now()->subMinutes(10),
            'transfer_reference' => 'FLEET-PAYOUT-STALE-001',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $logisticsResponse = $this->actingAs($this->superAdmin)->post(route('admin.disburse.logistics_payout'), [
            'payout_id' => $logisticsPayout->id,
        ]);
        $logisticsResponse->assertSessionHas('error');
        $this->assertEquals('pending', $logisticsPayout->fresh()->status);
        $this->assertEquals('CLAIM-FLEET-STALE-001', $logisticsPayout->fresh()->processing_token);
    }

    public function test_logistics_payout_rejection_does_not_credit_wallet_without_request_journal(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);
        $walletBefore = (string) $this->company->fresh()->wallet_balance;

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.logistics_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Legacy request requires accounting review.',
        ]);

        $response->assertSessionHas('status');
        $this->assertEquals('rejected', $payout->fresh()->status);
        $this->assertEquals($walletBefore, (string) $this->company->fresh()->wallet_balance);
        $this->assertStringContainsString('REJECTION_JOURNAL_MISSING', $payout->fresh()->admin_notes);
        $this->assertSame(0, FinancialJournalEntry::where('event_id', 'EVT-FLEET-REJECT-' . $payout->id)->count());
    }

    public function test_logistics_gateway_failure_does_not_restore_wallet_without_request_journal(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'transfer_reference' => 'FLEET-FAIL-NO-REQUEST-JOURNAL',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);
        $walletBefore = (string) $this->company->fresh()->wallet_balance;
        $journalCountBefore = FinancialJournalEntry::count();

        $result = app(\App\Services\PayoutFinalizationService::class)
            ->revertLogisticsPayout($payout, 'Authoritative gateway failure');

        $this->assertTrue($result['success']);
        $this->assertEquals('rejected', $payout->fresh()->status);
        $this->assertEquals('failed', $payout->fresh()->gateway_status);
        $this->assertEquals($walletBefore, (string) $this->company->fresh()->wallet_balance);
        $this->assertStringContainsString('REQUEST_JOURNAL_MISSING', $payout->fresh()->admin_notes);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }

    public function test_logistics_payout_reversal_holds_when_company_profile_is_missing(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'transfer_reference' => 'FLEET-MISSING-COMPANY-001',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);
        $journalCountBefore = FinancialJournalEntry::count();
        $this->company->delete();

        $result = app(\App\Services\PayoutFinalizationService::class)
            ->revertLogisticsPayout($payout, 'Authoritative gateway failure');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected']);
        $this->assertEquals('pending', $payout->fresh()->status);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }

    public function test_rejection_cannot_repeat_after_terminal_payout_journal_exists(): void
    {
        $logisticsPayout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-FLEET-TERMINAL',
            'event_id' => 'EVT-FLEET-PAYOUT-' . $logisticsPayout->id,
            'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
            'beneficiary_id' => $this->company->id,
            'account_name' => 'logistics_payout_payable',
            'entry_type' => 'debit',
            'amount' => '15000.00',
            'currency' => 'NGN',
            'description' => 'Test terminal accounting marker.',
        ]);
        $walletBefore = (string) $this->company->fresh()->wallet_balance;
        $logisticsResponse = $this->actingAs($this->superAdmin)->post(route('admin.reject.logistics_payout'), [
            'payout_id' => $logisticsPayout->id,
            'rejection_reason' => 'Attempted duplicate rejection.',
        ]);
        $logisticsResponse->assertSessionHas('error');
        $this->assertEquals('pending', $logisticsPayout->fresh()->status);
        $this->assertEquals($walletBefore, (string) $this->company->fresh()->wallet_balance);
        $this->assertSame(0, FinancialJournalEntry::where('event_id', 'EVT-FLEET-REJECT-' . $logisticsPayout->id)->count());

        $vendorPayout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-VND-TERMINAL',
            'event_id' => 'EVT-VND-PAYOUT-' . $vendorPayout->id,
            'beneficiary_type' => \App\Models\VendorProfile::class,
            'beneficiary_id' => $this->vendor->id,
            'account_name' => 'vendor_payout_payable',
            'entry_type' => 'debit',
            'amount' => '15000.00',
            'currency' => 'NGN',
            'description' => 'Test terminal accounting marker.',
        ]);
        $vendorResponse = $this->actingAs($this->superAdmin)->post(route('admin.reject.vendor_payout'), [
            'payout_id' => $vendorPayout->id,
            'rejection_reason' => 'Attempted duplicate rejection.',
        ]);
        $vendorResponse->assertSessionHas('error');
        $this->assertEquals('pending', $vendorPayout->fresh()->status);
        $this->assertSame(0, FinancialJournalEntry::where('event_id', 'EVT-MERCHANT-REJECT-' . $vendorPayout->id)->count());
    }

    public function test_payout_request_journal_evidence_requires_both_exact_legs(): void
    {
        $service = app(\App\Services\PayoutFinalizationService::class);
        $vendorPayout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-VND-REQ-EVIDENCE',
            'event_id' => 'EVT-MERCHANT-REQ-' . $vendorPayout->id,
            'beneficiary_type' => \App\Models\VendorProfile::class,
            'beneficiary_id' => $this->vendor->id,
            'account_name' => 'vendor_payable',
            'entry_type' => 'debit',
            'amount' => '15000.00',
            'currency' => 'NGN',
            'description' => 'Single leg must not qualify as a request journal.',
        ]);
        $this->assertFalse($service->hasVendorRequestJournalEvidence($vendorPayout));

        $wrongCredit = FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-VND-REQ-EVIDENCE',
            'event_id' => 'EVT-MERCHANT-REQ-' . $vendorPayout->id,
            'beneficiary_type' => \App\Models\VendorProfile::class,
            'beneficiary_id' => $this->vendor->id,
            'account_name' => 'vendor_payout_payable',
            'entry_type' => 'credit',
            'amount' => '14999.00',
            'currency' => 'NGN',
            'description' => 'Wrong amount must not qualify as complete evidence.',
        ]);
        $this->assertFalse($service->hasVendorRequestJournalEvidence($vendorPayout));
        $wrongCredit->delete();

        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-VND-REQ-EVIDENCE',
            'event_id' => 'EVT-MERCHANT-REQ-' . $vendorPayout->id,
            'beneficiary_type' => \App\Models\VendorProfile::class,
            'beneficiary_id' => $this->vendor->id,
            'account_name' => 'vendor_payout_payable',
            'entry_type' => 'credit',
            'amount' => '15000.00',
            'currency' => 'NGN',
            'description' => 'Exact matching liability leg.',
        ]);
        $this->assertTrue($service->hasVendorRequestJournalEvidence($vendorPayout));

        $fleetPayout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '12000.00',
            'status' => 'pending',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-FLEET-REQ-EVIDENCE',
            'event_id' => 'EVT-FLEET-REQ-' . $fleetPayout->id,
            'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
            'beneficiary_id' => $this->company->id,
            'account_name' => 'logistics_company_wallet',
            'entry_type' => 'debit',
            'amount' => '12000.00',
            'currency' => 'NGN',
            'description' => 'Single fleet request leg.',
        ]);
        $this->assertFalse($service->hasLogisticsRequestJournalEvidence($fleetPayout));

        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'TEST-FLEET-REQ-EVIDENCE',
            'event_id' => 'EVT-FLEET-REQ-' . $fleetPayout->id,
            'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
            'beneficiary_id' => $this->company->id,
            'account_name' => 'logistics_payout_payable',
            'entry_type' => 'credit',
            'amount' => '12000.00',
            'currency' => 'NGN',
            'description' => 'Exact matching fleet liability leg.',
        ]);
        $this->assertTrue($service->hasLogisticsRequestJournalEvidence($fleetPayout));
    }

    public function test_vendor_payout_hard_cap_applies_even_to_super_admin()
    {
        $this->createTestOrder([
            'merchandise_subtotal' => '15000000.00',
            'vendor_net_90' => '13500000.00',
            'grand_total' => '15000000.00',
        ]);

        // Launch policy caps a single vendor payout at ₦500,000 for every role.
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '12000000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        // Regular admin (even if bypassed past route middleware) is rejected at controller level
        $response = $this->withoutMiddleware(EnsureUserRole::class)
            ->actingAs($this->regularAdmin)
            ->post(route('admin.disburse.vendor_payout'), [
                'payout_id' => $payout->id,
            ]);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('between ₦5,000 and ₦500,000', session('error'));

        // Super Admin must not bypass the hard launch cap either.
        $responseSuccess = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);
        $responseSuccess->assertSessionHas('error');
        $this->assertStringContainsString('between ₦5,000 and ₦500,000', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }
}