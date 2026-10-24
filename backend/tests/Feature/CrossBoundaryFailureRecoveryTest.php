<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialOperation;
use App\Models\FinancialReservation;
use App\Models\GatewayAttempt;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\ProcessedWebhookEvent;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\DoorstepHandshakeService;
use App\Services\FinancialOperationService;
use App\Services\OrderSettlementService;
use App\Services\SupplierReassignmentRecoveryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrossBoundaryFailureRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;
    protected User $supplierUser1;
    protected VendorProfile $supplier1;
    protected Shop $shop1;
    protected User $supplierUser2;
    protected VendorProfile $supplier2;
    protected Shop $shop2;
    protected LogisticsCompanyProfile $logisticsCompany;
    protected RiderProfile $rider;
    protected Product $product;
    protected SupplierOffer $offer1;
    protected SupplierOffer $offer2;
    protected Lga $uyoLga;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.paystack.secret_key', 'sk_test_cross_boundary_audit_secret_key');
        Config::set('services.paystack.simulate_in_tests', false);

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);

        $state = State::create(['country_id' => $country->id, 'name' => 'Akwa Ibom', 'code' => 'AK', 'is_active' => true]);
        $this->uyoLga = Lga::create(['state_id' => $state->id, 'name' => 'Uyo', 'code' => 'UYO', 'is_active' => true]);

        // 1. Super Admin
        $this->superAdmin = User::create([
            'name' => 'Root Super Admin',
            'email' => 'superadmin@vmarket.ng',
            'phone' => '08011112222',
            'password' => bcrypt('AdminPass123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // 2. Customer
        $this->customer = User::create([
            'name' => 'Aniefiok Udoh',
            'email' => 'aniefiok@gmail.com',
            'phone' => '08022223333',
            'password' => bcrypt('CustPass123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // 3. Supplier 1
        $this->supplierUser1 = User::create([
            'name' => 'Supplier Alpha Owner',
            'email' => 'alpha@suppliers.ng',
            'phone' => '08033334444',
            'password' => bcrypt('SuppPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->supplier1 = VendorProfile::create([
            'user_id' => $this->supplierUser1->id,
            'business_name' => 'Alpha Sourcing Enterprises Ltd',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);

        $this->shop1 = Shop::create([
            'vendor_id' => $this->supplier1->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'name' => 'Alpha Depot Oron Rd',
            'slug' => 'alpha-depot-oron-rd',
            'address' => '45 Oron Road, Uyo',
            'phone' => '08033334444',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        // 4. Supplier 2 (for failover / reassignment)
        $this->supplierUser2 = User::create([
            'name' => 'Supplier Beta Owner',
            'email' => 'beta@suppliers.ng',
            'phone' => '08044445555',
            'password' => bcrypt('SuppPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->supplier2 = VendorProfile::create([
            'user_id' => $this->supplierUser2->id,
            'business_name' => 'Beta Sourcing Hub Ltd',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);

        $this->shop2 = Shop::create([
            'vendor_id' => $this->supplier2->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'name' => 'Beta Warehouse Nwaniba',
            'slug' => 'beta-warehouse-nwaniba',
            'address' => '88 Nwaniba Road, Uyo',
            'phone' => '08044445555',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        // 5. Logistics Company & Rider
        $fleetUser = User::create([
            'name' => 'Central Logistics Admin',
            'email' => 'fleet@speedylogistics.ng',
            'phone' => '08055556666',
            'password' => bcrypt('FleetPass123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->logisticsCompany = LogisticsCompanyProfile::create([
            'user_id' => $fleetUser->id,
            'company_name' => 'Speedy Central Logistics Ltd',
            'contact_phone' => '08055556666',
            'status' => 'active',
            'wallet_balance' => '0.00',
        ]);

        $riderUser = User::create([
            'name' => 'Kufre Rider',
            'email' => 'kufre@speedylogistics.ng',
            'phone' => '08077778888',
            'password' => bcrypt('RiderPass123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $this->rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => $this->logisticsCompany->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $category = Category::create([
            'name' => 'Appliances',
            'slug' => 'appliances',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Heavy Duty Inverter Blender 2000W',
            'slug' => 'heavy-duty-inverter-blender-2000w',
            'category_id' => $category->id,
            'unit_price' => '45000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 10,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $this->offer1 = SupplierOffer::create([
            'product_id' => $this->product->id,
            'vendor_id' => $this->supplier1->id,
            'shop_id' => $this->shop1->id,
            'wholesale_cost' => '38000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now()->subMinutes(10),
            'availability_confirmed_by' => $this->superAdmin->id,
            'availability_expires_at' => Carbon::now()->addHours(4),
        ]);

        $this->offer2 = SupplierOffer::create([
            'product_id' => $this->product->id,
            'vendor_id' => $this->supplier2->id,
            'shop_id' => $this->shop2->id,
            'wholesale_cost' => '39000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now()->subMinutes(5),
            'availability_confirmed_by' => $this->superAdmin->id,
            'availability_expires_at' => Carbon::now()->addHours(4),
        ]);
    }

    protected function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'VM-ORD-' . strtoupper(Str::random(8)),
            'customer_id' => $this->customer->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_type' => 'doorstep_delivery',
            'grand_total' => '47500.00',
            'subtotal' => '45000.00',
            'merchandise_subtotal' => '45000.00',
            'delivery_fee' => '2500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'supplier_cost_total_snapshot' => null,
            'logistics_company_id' => $this->logisticsCompany->id,
            'logistics_company_payable_amount' => '1800.00',
            'logistics_company_payable_default_amount' => '1800.00',
            'logistics_company_payable_ledger_company_id' => $this->logisticsCompany->id,
            'verification_code' => '987654',
            'delivery_state' => 'Akwa Ibom',
            'delivery_city' => 'Uyo',
            'delivery_address' => '12 Oron Road, Uyo',
            'customer_name' => 'Aniefiok Udoh',
            'customer_email' => 'aniefiok@gmail.com',
            'customer_phone' => '08022223333',
            'recipient_name' => 'Aniefiok Udoh',
            'recipient_phone' => '08022223333',
        ], $attributes));
    }

    protected function createOrderItem(Order $order, array $attributes = []): OrderItem
    {
        return OrderItem::create(array_merge([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '45000.00',
            'line_total' => '45000.00',
            'cost_price' => '38000.00',
            'supplier_offer_id' => null,
            'supplier_vendor_id' => null,
            'supplier_shop_id' => null,
            'supplier_cost_snapshot' => null,
            'supplier_snapshot_at' => null,
            'fulfillment_supplier_vendor_id' => null,
            'fulfillment_supplier_shop_id' => null,
            'fulfillment_supplier_cost_snapshot' => null,
        ], $attributes));
    }

    /**
     * Scenario 1: Paystack accepts payment, but the application crashes before recording it.
     * Proof: Expiry worker checks gateway truth, heals the pending state, settles order,
     * preserves inventory, and subsequent callbacks return ALREADY_PAID (replayed => true).
     */
    public function test_scenario_1_gateway_captures_payment_but_client_crashes_expiry_heals_and_retries_are_idempotent(): void
    {
        $ref = 'PAY-SCENARIO1-' . strtoupper(Str::random(10));

        $order = $this->createOrder([
            'order_number' => 'VM-ORD-SCENARIO1',
            'payment_method' => 'paystack',
            'payment_reference' => $ref,
            'customer_cashback_5' => '2250.00',
            'expires_at' => Carbon::now()->subMinutes(5), // Deadline already expired!
        ]);

        $this->createOrderItem($order);

        $initialStock = $this->product->fresh()->inventory_quantity;

        // Mock Paystack API: Gateway confirms customer was successfully charged ₦47,500
        Http::fake([
            'https://api.paystack.co/transaction/verify/' . $ref => Http::response([
                'status' => true,
                'data' => [
                    'id' => 99887711,
                    'status' => 'success',
                    'reference' => $ref,
                    'amount' => 4750000, // ₦47,500.00 in kobo
                    'currency' => 'NGN',
                    'paid_at' => Carbon::now()->toIso8601String(),
                ],
            ], 200),
        ]);

        // Background expiry cron runs: `php artisan orders:expire-unpaid`
        $settlementService = app(OrderSettlementService::class);
        $expiredCount = $settlementService->expireAbandonedPendingOrders();

        $order->refresh();

        // 1. Payment Truth Defense Invariant:
        // Order must NOT be cancelled because gateway captured the money!
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->order_status);
        $this->assertNotSame('cancelled', $order->order_status);
        $this->assertSame(0, $expiredCount, 'Order was captured by Paystack; expiry counter must be 0.');
        $this->assertSame($initialStock, $this->product->fresh()->inventory_quantity, 'Stock must not be falsely replenished on a paid order.');

        // 2. Client / Webhook retry arrives later:
        // Client connection recovers and calls settlePaystackOrder again
        $replayResult = $settlementService->settlePaystackOrder(
            $order,
            [
                'id' => 99887711,
                'status' => 'success',
                'reference' => $ref,
                'amount' => 4750000,
                'currency' => 'NGN',
            ],
            null,
            'delayed_client_callback'
        );

        $this->assertTrue($replayResult['success']);
        $this->assertTrue($replayResult['is_replayed'] ?? false);
        $this->assertSame('ALREADY_PAID', $replayResult['status']);

        // 3. Double-entry financial invariants:
        $supplierPayables = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'supplier_payable')
            ->where('entry_type', 'credit')
            ->count();
        $this->assertSame(1, $supplierPayables, 'Supplier payable must be credited strictly once.');

        $cashbackLiabilities = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'cashback_liability')
            ->count();
        $this->assertSame(1, $cashbackLiabilities, 'Cashback liability must be booked strictly once.');
    }

    /**
     * Scenario 2: Two workers attempt to process the same webhook or expire the same order.
     * Proof: Stale worker is fenced out via generation lock (`attempts == claimed_attempts`).
     * Order lock serializes state transitions and prevents duplicate captures or double stock restoration.
     */
    public function test_scenario_2_concurrent_webhook_and_expiry_workers_are_fenced_and_prevent_duplicate_settlement(): void
    {
        $eventId = 'evt_race_' . Str::random(12);
        $eventHash = hash('sha256', 'mock_signed_payload_' . $eventId);

        // 1. Worker A claims the webhook inbox event at generation 1
        $claimedEvent = ProcessedWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => 'charge.success',
            'payload_hash' => $eventHash,
            'payload' => json_encode(['event' => 'charge.success', 'id' => $eventId]),
            'status' => 'processing',
            'attempts' => 1,
            'created_at' => Carbon::now()->subSeconds(90),
            'updated_at' => Carbon::now()->subSeconds(90), // Exceeded 60s processing timeout!
        ]);

        $order = $this->createOrder([
            'order_number' => 'VM-ORD-SCENARIO2',
            'payment_reference' => 'PAY-RACE-' . Str::random(8),
            'expires_at' => Carbon::now()->subMinutes(2),
        ]);

        $this->createOrderItem($order);

        // 2. Worker B claims ownership of the stalled event, bumping attempts from 1 to 2
        $claimedEvent->increment('attempts'); // Now attempts = 2
        $this->assertSame(2, $claimedEvent->fresh()->attempts);

        // 3. Worker A (stale worker) attempts to settle the order under its claimed generation 1
        $settlementService = app(OrderSettlementService::class);
        $staleResult = $settlementService->settlePaystackOrder(
            $order,
            ['id' => 112233, 'status' => 'success', 'reference' => $order->payment_reference, 'amount' => 4750000, 'currency' => 'NGN'],
            null,
            'paystack_webhook',
            $claimedEvent->id,
            1 // Worker A passes stale attempt generation 1!
        );

        $this->assertFalse($staleResult['success']);
        $this->assertSame('STALE_WEBHOOK_CLAIM', $staleResult['status']);
        $this->assertSame('pending', $order->fresh()->payment_status, 'Stale worker must NOT settle the order.');

        // 4. Worker B settles the order with the valid current generation (attempts = 2)
        $winningResult = $settlementService->settlePaystackOrder(
            $order,
            ['id' => 112233, 'status' => 'success', 'reference' => $order->payment_reference, 'amount' => 4750000, 'currency' => 'NGN'],
            null,
            'paystack_webhook',
            $claimedEvent->id,
            2 // Current generation!
        );

        $this->assertTrue($winningResult['success']);
        $this->assertSame('paid', $order->fresh()->payment_status);

        // 5. Expiry worker concurrently tries to cancel under row lock:
        // Re-checking under lock prevents cancelling an already paid order
        $expiredCount = $settlementService->expireAbandonedPendingOrders();
        $this->assertSame(0, $expiredCount);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->order_status);
    }

    /**
     * Scenario 3: A supplier confirms stock, but reassignment or dispatch fails partway through.
     * Proof: Transactional rollback leaves no orphaned assignments or payables.
     * Retrying with the same idempotency key succeeds without duplicating financial records.
     */
    public function test_scenario_3_supplier_stock_reassignment_fails_midway_rolls_back_cleanly_and_permits_safe_idempotent_retry(): void
    {
        $order = $this->createOrder([
            'order_number' => 'VM-ORD-SCENARIO3',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'sourcing_status' => 'supplier_rejected', // On authorized sourcing hold
            'payment_reference' => 'PAY-SCENARIO3-' . Str::random(8),
            'supplier_cost_total_snapshot' => '38000.00',
            'fulfillment_supplier_cost_total_snapshot' => '38000.00',
            'vendor_id' => $this->supplier1->id,
            'shop_id' => $this->shop1->id,
        ]);

        $item = $this->createOrderItem($order, [
            'supplier_offer_id' => $this->offer1->id,
            'supplier_vendor_id' => $this->supplier1->id,
            'supplier_shop_id' => $this->shop1->id,
            'supplier_cost_snapshot' => '38000.00',
            'supplier_snapshot_at' => Carbon::now(),
            'fulfillment_supplier_vendor_id' => $this->supplier1->id,
            'fulfillment_supplier_shop_id' => $this->shop1->id,
            'fulfillment_supplier_cost_snapshot' => '38000.00',
        ]);

        // Establish the baseline supplier payable created at payment settlement
        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'transaction_reference' => 'PAY-SCENARIO3-SETTLED',
            'event_id' => 'EVT-SUP1-PAYABLE-' . $order->id,
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->supplier1->id,
            'account_name' => 'supplier_payable',
            'entry_type' => 'credit',
            'amount' => '38000.00',
            'currency' => 'NGN',
            'description' => 'Baseline supplier payable entry from payment settlement',
        ]);

        $rejectedAssignment = OrderSupplierAssignment::create([
            'order_id' => $order->id,
            'supplier_vendor_id' => $this->supplier1->id,
            'supplier_shop_id' => $this->shop1->id,
            'status' => 'supplier_rejected',
            'assigned_at' => Carbon::now()->subMinutes(30),
            'supplier_rejected_at' => Carbon::now()->subMinutes(10),
        ]);

        $reassignmentService = app(SupplierReassignmentRecoveryService::class);
        $idempotencyKey = 'IDEM-RECOVER-' . Str::random(12);

        // 1. Simulate mid-flight database or network crash inside transaction
        try {
            DB::transaction(function () use ($reassignmentService, $order, $rejectedAssignment, $item, $idempotencyKey) {
                // Call reassignment but deliberately throw before commit to simulate crash
                $reassignmentService->reassignRejectedItems(
                    $order->id,
                    $rejectedAssignment->id,
                    $this->supplier2->id,
                    $this->shop2->id,
                    [$item->id],
                    $this->superAdmin,
                    'First attempt simulated crash test.',
                    $idempotencyKey
                );

                throw new \RuntimeException('Simulated power drop / server crash before commit.');
            });
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated power drop', $e->getMessage());
        }

        // State check: DB was rolled back cleanly! No additional journal rows or assignments created.
        $this->assertSame('supplier_rejected', $order->fresh()->sourcing_status);
        $this->assertSame(1, OrderSupplierAssignment::where('order_id', $order->id)->count());
        $this->assertSame(1, FinancialJournalEntry::where('order_id', $order->id)->count(), 'Rolled back attempt must not orphan any journal entries.');

        // 2. Retry with the SAME idempotency key after recovering:
        $successResult = $reassignmentService->reassignRejectedItems(
            $order->id,
            $rejectedAssignment->id,
            $this->supplier2->id,
            $this->shop2->id,
            [$item->id],
            $this->superAdmin,
            'Recovered reassignment to Supplier Beta.',
            $idempotencyKey
        );

        $this->assertTrue($successResult['success']);
        $this->assertFalse($successResult['replayed']);
        $this->assertSame('awaiting_supplier_confirmation', $order->fresh()->sourcing_status);

        // 3. Replay with the same idempotency key:
        $replayResult = $reassignmentService->reassignRejectedItems(
            $order->id,
            $rejectedAssignment->id,
            $this->supplier2->id,
            $this->shop2->id,
            [$item->id],
            $this->superAdmin,
            'Recovered reassignment to Supplier Beta.',
            $idempotencyKey
        );

        $this->assertTrue($replayResult['success']);
        $this->assertTrue($replayResult['replayed']);
        $this->assertSame('awaiting_supplier_confirmation', $order->fresh()->sourcing_status);

        // Invariant: Total active assignments for supplier 2 is exactly 1 (no duplicate assignment rows created)
        $this->assertSame(1, OrderSupplierAssignment::where('order_id', $order->id)->where('supplier_vendor_id', $this->supplier2->id)->count());
    }

    /**
     * Scenario 4: Delivery succeeds physically, but evidence or completion recording fails.
     * Proof: Doorstep handshake is idempotent against client retries; company wallet balance delta is strictly ₦0 on retry.
     */
    public function test_scenario_4_physical_delivery_succeeds_but_rider_network_drops_retry_is_idempotent_without_double_crediting_wallet(): void
    {
        $order = $this->createOrder([
            'order_number' => 'VM-ORD-SCENARIO4',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'customer_cashback_5' => '2250.00',
            'rider_id' => $this->rider->id,
            'supplier_cost_total_snapshot' => '38000.00',
            'fulfillment_supplier_cost_total_snapshot' => '38000.00',
        ]);

        $item = $this->createOrderItem($order, [
            'supplier_offer_id' => $this->offer1->id,
            'supplier_vendor_id' => $this->supplier1->id,
            'supplier_shop_id' => $this->shop1->id,
            'supplier_cost_snapshot' => '38000.00',
            'supplier_snapshot_at' => Carbon::now(),
            'fulfillment_supplier_vendor_id' => $this->supplier1->id,
            'fulfillment_supplier_shop_id' => $this->shop1->id,
            'fulfillment_supplier_cost_snapshot' => '38000.00',
        ]);

        // Establish the baseline payable entry created at payment settlement
        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'transaction_reference' => 'PAY-REF-4',
            'event_id' => 'EVT-PAYABLE-' . $order->id,
            'beneficiary_type' => LogisticsCompanyProfile::class,
            'beneficiary_id' => $this->logisticsCompany->id,
            'account_name' => 'logistics_company_payable',
            'entry_type' => 'credit',
            'amount' => '1800.00',
            'currency' => 'NGN',
            'description' => 'Baseline payable entry',
        ]);

        CustomerCashbackLedger::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'amount' => '2250.00',
            'status' => 'pending_maturation',
        ]);

        $handshakeService = app(DoorstepHandshakeService::class);
        $manifest = [
            [
                'order_item_id' => $item->id,
                'accepted_quantity' => 1,
                'rejected_quantity' => 0,
            ],
        ];

        // 1. Initial Handover Execution
        $result = $handshakeService->processDoorstepHandover(
            $order,
            $this->rider,
            $manifest,
            '987654', // Correct OTP
            'proofs/doorstep_photo_proof_123.jpg'
        );

        $this->assertTrue($result['success']);
        $order->refresh();
        $this->assertSame('delivered', $order->order_status);
        $this->assertNotNull($order->logistics_company_wallet_credited_at);

        $walletBalanceAfterFirst = $this->logisticsCompany->fresh()->wallet_balance;
        $this->assertSame('1800.00', (string) $walletBalanceAfterFirst);

        // 2. Rider phone lost 4G connection and retried the exact same handover payload
        $retryResult = $handshakeService->processDoorstepHandover(
            $order,
            $this->rider,
            $manifest,
            '987654',
            'proofs/doorstep_photo_proof_123.jpg'
        );

        $this->assertTrue($retryResult['success']);
        $this->assertTrue($retryResult['replayed']);
        $this->assertSame('Doorstep delivery was already confirmed and recorded.', $retryResult['message']);

        // Invariant: Company wallet balance delta is strictly ₦0 on retry!
        $walletBalanceAfterRetry = $this->logisticsCompany->fresh()->wallet_balance;
        $this->assertSame((string) $walletBalanceAfterFirst, (string) $walletBalanceAfterRetry, 'Wallet must NEVER be double-credited on retry.');

        // Invariant: Exactly one wallet debit and one wallet credit entry across all journal logs
        $walletCredits = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'logistics_company_wallet')
            ->where('entry_type', 'credit')
            ->count();
        $this->assertSame(1, $walletCredits, 'Logistics wallet journal entry must be recorded strictly once.');
    }

    /**
     * Scenario 5: A refund reaches the gateway, but the response is lost and the system retries.
     * Proof: Duplicate dispatch is suppressed; operation remains initiated; reservation remains active;
     * ledger reconciliation confirms exact outcome once.
     */
    public function test_scenario_5_refund_dispatch_encounters_gateway_timeout_retry_is_suppressed_pending_reconciliation(): void
    {
        $order = $this->createOrder([
            'order_number' => 'VM-ORD-SCENARIO5',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_reference' => 'PAY-REF-SCENARIO5-' . Str::random(8),
            'supplier_cost_total_snapshot' => '38000.00',
            'fulfillment_supplier_cost_total_snapshot' => '38000.00',
        ]);

        $item = $this->createOrderItem($order, [
            'supplier_offer_id' => $this->offer1->id,
            'supplier_vendor_id' => $this->supplier1->id,
            'supplier_shop_id' => $this->shop1->id,
            'supplier_cost_snapshot' => '38000.00',
            'supplier_snapshot_at' => Carbon::now(),
            'fulfillment_supplier_vendor_id' => $this->supplier1->id,
            'fulfillment_supplier_shop_id' => $this->shop1->id,
            'fulfillment_supplier_cost_snapshot' => '38000.00',
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => 'RET-SCENARIO5-01',
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '45000.00',
            'refund_delivery_fee' => false,
            'reviewed_by_admin_id' => $this->superAdmin->id,
            'reviewed_at' => Carbon::now(),
        ]);

        ReturnItem::create([
            'return_case_id' => $returnCase->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => '45000.00',
            'line_total' => '45000.00',
            'refundable_amount' => '45000.00',
            'status' => 'approved',
        ]);

        $financialOpService = app(FinancialOperationService::class);

        // 1. Create refund operation with authorized Super Admin reviewer
        $operation = $financialOpService->createRefundOperation(
            $order,
            '45000.00',
            'Damaged item doorstep return refund',
            $returnCase,
            'IDEM-RFD-' . Str::random(10),
            [
                'type' => 'admin_approved_return',
                'approved_by_user_id' => $this->superAdmin->id,
                'refund_delivery_fee' => false,
            ]
        );

        $this->assertSame('holding_reservation', $operation->status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)->where('status', 'active')->count());

        // 2. Mock Paystack Gateway Timeout / Connection Exception:
        Http::fake([
            'https://api.paystack.co/refund' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds with 0 bytes received');
            },
        ]);

        // Dispatch refund:
        $firstDispatch = $financialOpService->dispatchRefund($operation);

        $this->assertFalse($firstDispatch['success']);
        $this->assertSame('UNCERTAIN_TIMEOUT', $firstDispatch['code']);
        $this->assertSame('initiated', $operation->fresh()->status);

        // Invariant: GatewayAttempt records uncertain_timeout, and reservation remains ACTIVE
        $attempt = GatewayAttempt::where('financial_operation_id', $operation->id)->latest('id')->first();
        $this->assertSame('uncertain_timeout', $attempt->outcome);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)->where('status', 'active')->count(), 'Reservation must NOT be released on uncertain timeout.');

        // 3. Worker or admin retries dispatchRefund while status is initiated:
        // P0 Defense: Blind duplicate dispatch MUST be suppressed!
        $retryDispatch = $financialOpService->dispatchRefund($operation->fresh());

        $this->assertFalse($retryDispatch['success']);
        $this->assertSame('initiated', $retryDispatch['status']);
        $this->assertStringContainsString('duplicate dispatch suppressed pending gateway reconciliation', $retryDispatch['message']);

        // Invariant: Exactly one GatewayAttempt exists (no second request was sent to Paystack)
        $this->assertSame(1, GatewayAttempt::where('financial_operation_id', $operation->id)->count(), 'Duplicate HTTP request must be suppressed.');

        // 4. Final reconciliation: Paystack webhook or reconciliation arrives confirming the refund
        $reconciliationPayload = [
            'status' => 'processed',
            'refund_reference' => 'RFD-GW-REF-998877',
            'data' => [
                'reference' => 'RFD-GW-REF-998877',
                'amount' => 4500000,
                'currency' => 'NGN',
                'status' => 'processed',
            ],
        ];

        $finalizeResult = $financialOpService->finalizeRefundConfirmation($operation->fresh(), $reconciliationPayload);
        $this->assertTrue($finalizeResult['success']);

        $operation->refresh();
        $this->assertSame('confirmed', $operation->status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)->where('status', 'committed')->count());
        $this->assertSame(0, FinancialReservation::where('financial_operation_id', $operation->id)->where('status', 'active')->count());
    }
}
