<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\DeliveryLane;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\ManualBankTransferPayment;
use App\Models\ManualBankTransferRefundCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RetailerSupplierPayoutAllocation;
use App\Models\RetailerSupplierPayoutRequest;
use App\Models\ReturnCase;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\RetailerSupplierPayoutService;
use App\Services\ReturnAndRefundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditorDiscrepancyAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $customer;
    private VendorProfile $supplier;
    private Shop $shop;
    private Lga $uyo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'email' => 'admin@victoriousmarket.test',
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
        config(['auth.designated_super_admin_email' => 'admin@victoriousmarket.test']);

        $this->customer = User::factory()->create(['user_type' => 'customer', 'status' => 'active']);

        $country = Country::firstOrCreate(['iso_code' => 'NG'], ['name' => 'Nigeria', 'phone_code' => '+234', 'currency_code' => 'NGN', 'is_active' => true]);
        $state = State::firstOrCreate(['name' => 'Akwa Ibom'], ['country_id' => $country->id, 'state_code' => 'AK', 'is_active' => true]);
        $this->uyo = Lga::firstOrCreate(['name' => 'Uyo', 'state_id' => $state->id], ['is_active' => true]);

        $supplierUser = User::factory()->create(['user_type' => 'vendor', 'status' => 'active']);
        $this->supplier = VendorProfile::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Aligned Test Supplier',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
            'bank_name' => 'First Bank',
            'bank_code' => '011',
            'account_number' => '3012345678',
            'account_name' => 'Aligned Test Supplier',
            'bank_updated_at' => now()->subDays(5),
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->supplier->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyo->id,
            'name' => 'Aligned Supplier Shop',
            'slug' => 'aligned-supplier-shop-' . uniqid(),
            'address' => '25 Aka Road, Uyo',
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    private function createOrder(array $attributes = []): Order
    {
        $defaults = [
            'order_number' => 'VM-TEST-' . uniqid(),
            'customer_id' => $this->customer->id,
            'origin_lga_id' => $this->uyo->id,
            'destination_lga_id' => $this->uyo->id,
            'retailer_model_order' => true,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'manual_bank_transfer',
            'payment_reference' => 'PAY-REF-' . uniqid(),
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '500.00',
            'merchandise_subtotal' => '10000.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ];

        return Order::create(array_merge($defaults, $attributes));
    }

    public function test_manual_bank_transfer_order_return_disburses_manual_bank_refund_to_case(): void
    {
        Storage::fake('local');

        $order = $this->createOrder();

        $manualPayment = ManualBankTransferPayment::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'amount_kobo' => 1050000,
            'currency' => 'NGN',
            'status' => 'approved',
            'bank_transaction_reference' => 'IN-BANK-REF-' . uniqid(),
            'receiving_bank_name' => 'Zenith Bank',
            'receiving_account_number' => '1012345678',
            'evidence_path' => 'evidence/test.png',
            'evidence_mime' => 'image/png',
            'evidence_size_bytes' => 1024,
            'evidence_sha256' => hash('sha256', 'dummy-test-proof-content'),
            'submitted_at' => now(),
            'approved_by_admin_id' => $this->superAdmin->id,
            'approved_at' => now(),
            'idempotency_key' => 'IN-BANK-KEY-' . uniqid(),
        ]);

        \App\Models\CustomerCashbackLedger::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'amount' => '500.00',
            'status' => 'pending_maturation',
            'funding_source' => 'manual_bank_transfer',
            'matures_at' => now()->addDays(7),
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => 'RET-' . uniqid(),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'admin_ruling' => 'uphold_customer_claim',
            'total_refundable_amount' => '10000.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
            'admin_ruled_at' => now(),
            'reason' => 'Customer received damaged goods',
        ]);

        $service = app(ReturnAndRefundService::class);
        $bankDispatchRef = 'OUT-REFUND-BANK-' . uniqid();

        $result = $service->adminDisburseRefund(
            $this->superAdmin,
            $returnCase,
            $bankDispatchRef,
            'settlement-proofs/refund_receipt.png',
            false
        );

        $this->assertTrue($result['success'] ?? false, $result['message'] ?? 'Disbursement failed.');
        $this->assertSame('refunded', $returnCase->fresh()->status);
        $this->assertSame(0, bccomp('10000.00', (string) $returnCase->fresh()->refunded_amount, 2));
        $this->assertSame($bankDispatchRef, $returnCase->fresh()->refund_reference);

        // Verify ManualBankTransferRefundCase created in dispatched_awaiting_reconciliation status
        // Not prematurely marked reconciled/completed until actual bank debit reconciliation!
        $refundCase = ManualBankTransferRefundCase::where('order_id', $order->id)->first();
        $this->assertNotNull($refundCase);
        $this->assertSame('dispatched_awaiting_reconciliation', $refundCase->status);
        $this->assertNull($refundCase->manual_dispatch_confirmed_at);
        $this->assertSame($bankDispatchRef, $refundCase->manual_dispatch_reference);
        $this->assertSame(1000000, (int) $refundCase->amount_kobo);

        // Verify Financial Journal postings exist and record the cash outflow
        $creditEntry = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'cash_in_escrow')
            ->where('entry_type', 'credit')
            ->first();
        $this->assertNotNull($creditEntry);
        $this->assertSame(0, bccomp('10000.00', (string) $creditEntry->amount, 2));
    }

    public function test_manual_bank_transfer_refund_fails_closed_when_manual_payment_missing(): void
    {
        // An order with manual_bank_transfer payment method but NO ManualBankTransferPayment record
        $order = $this->createOrder([
            'payment_method' => 'manual_bank_transfer',
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => 'RET-NOPAY-' . uniqid(),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'admin_ruling' => 'uphold_customer_claim',
            'total_refundable_amount' => '5000.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
            'admin_ruled_at' => now(),
            'reason' => 'Customer claim',
        ]);

        $service = app(ReturnAndRefundService::class);
        $bankDispatchRef = 'OUT-REFUND-FAIL-' . uniqid();

        $result = $service->adminDisburseRefund(
            $this->superAdmin,
            $returnCase,
            $bankDispatchRef,
            'settlement-proofs/test.png',
            false
        );

        // Fail-closed: Must return failure and NEVER mark return case as refunded
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No ManualBankTransferPayment record exists', $result['message']);
        $this->assertSame('refund_pending', $returnCase->fresh()->status);
        $this->assertNull(ManualBankTransferRefundCase::where('order_id', $order->id)->first());
    }

    public function test_manual_bank_refund_rejects_duplicate_bank_reference(): void
    {
        $order = $this->createOrder();

        ManualBankTransferPayment::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'amount_kobo' => 1050000,
            'currency' => 'NGN',
            'status' => 'approved',
            'bank_transaction_reference' => 'IN-BANK-REF-DUP',
            'receiving_bank_name' => 'Zenith Bank',
            'receiving_account_number' => '1012345678',
            'evidence_path' => 'evidence/test.png',
            'evidence_mime' => 'image/png',
            'evidence_size_bytes' => 1024,
            'evidence_sha256' => hash('sha256', 'dummy-proof'),
            'submitted_at' => now(),
            'approved_by_admin_id' => $this->superAdmin->id,
            'approved_at' => now(),
            'idempotency_key' => 'IN-KEY-DUP',
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => 'RET-DUP-' . uniqid(),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'admin_ruling' => 'uphold_customer_claim',
            'total_refundable_amount' => '10000.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
            'admin_ruled_at' => now(),
            'reason' => 'Customer claim',
        ]);

        $service = app(ReturnAndRefundService::class);
        // Attempt to disburse using the same reference already used in manual_bank_transfer_payments
        $result = $service->adminDisburseRefund(
            $this->superAdmin,
            $returnCase,
            'IN-BANK-REF-DUP',
            'settlement-proofs/test.png'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already been recorded in the system and cannot be reused', $result['message']);
        $this->assertSame('refund_pending', $returnCase->fresh()->status);
    }

    public function test_paystack_order_rejects_manual_bank_reference_for_refund(): void
    {
        $order = $this->createOrder([
            'payment_method' => 'paystack',
            'paystack_transaction_id' => 'PSTX-' . uniqid(),
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => 'RET-PSTK-' . uniqid(),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '10000.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
            'admin_ruled_at' => now(),
            'reason' => 'Customer claim',
        ]);

        $service = app(ReturnAndRefundService::class);
        $res = $service->adminDisburseRefund($this->superAdmin, $returnCase, 'MANUAL-REF-999');

        $this->assertFalse($res['success']);
        $this->assertSame('disabled', $res['status']);
        $this->assertStringContainsString('disabled at launch', $res['message']);
    }

    public function test_manual_supplier_payout_disbursement_supported_via_cleared_collections(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-10-24 12:00:00')); // Monday-Saturday disbursement window

        $order = $this->createOrder([
            'merchandise_subtotal' => '8000.00',
            'grand_total' => '8500.00',
            'refund_window_expires_at' => now()->subDay(),
        ]);

        $category = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Payout Item',
            'slug' => 'payout-item-' . uniqid(),
            'unit_price' => '8000.00',
            'cost_price' => '0.00',
            'is_in_stock' => true,
            'inventory_quantity' => 10,
            'is_approved' => true,
            'listing_status' => 'listed',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Payout Item',
            'product_sku' => 'SKU-' . uniqid(),
            'quantity' => 1,
            'unit_price' => '8000.00',
            'line_total' => '8000.00',
            'fulfillment_supplier_vendor_id' => $this->supplier->id,
            'fulfillment_supplier_shop_id' => $this->shop->id,
            'fulfillment_supplier_cost_snapshot' => '6000.00',
        ]);

        // Post supplier payable journal entry to reflect earned payable from delivery
        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'transaction_reference' => 'TX-SUP-' . $order->id,
            'event_id' => 'EVT-SUP-' . uniqid(),
            'idempotency_key' => 'IDEMP-SUP-CR-' . uniqid(),
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->supplier->id,
            'account_name' => 'supplier_payable',
            'entry_type' => 'credit',
            'amount' => '6000.00',
            'currency' => 'NGN',
            'description' => 'Supplier payable capture',
        ]);

        $payoutService = app(RetailerSupplierPayoutService::class);
        $requested = $payoutService->requestPayout($this->supplier, [$item->id]);
        $this->assertTrue($requested['success'], $requested['message'] ?? 'Payout request failed.');
        $payout = $requested['payout'];

        // Verify cleared collection funding works without a Paystack treasury batch!
        $fundingCheck = $payoutService->verifyClearedCollectionFunding($payout->id);
        $this->assertTrue($fundingCheck['success'], $fundingCheck['message'] ?? 'Funding check failed.');

        // Execute disbursement via Admin Controller
        $file = UploadedFile::fake()->image('proof.png');
        $uniqueBankRef = 'BNK-DISBURSE-' . uniqid();

        $response = $this->actingAs($this->superAdmin)->post(
            route('admin.retailer-supplier-payouts.disburse', $payout->id),
            [
                'bank_transaction_reference' => $uniqueBankRef,
                'settlement_proof' => $file,
                'admin_notes' => 'Disbursed directly via FirstBank mobile transfer',
            ]
        );

        $response->assertRedirect(route('admin.retailer-supplier-payouts.index'));

        $payout->refresh();
        $this->assertSame('processing', $payout->status);
        $this->assertSame('manual_transfer_dispatched', $payout->gateway_status);
        $this->assertSame($uniqueBankRef, $payout->bank_transaction_reference);
        // Verify real null is stored, not the literal 'MANUAL_BANK_TRANSFER'
        $this->assertNull($payout->recipient_code);
    }

    public function test_order_status_timeline_contains_no_hardcoded_times(): void
    {
        $order = $this->createOrder([
            'order_status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'paid_at' => Carbon::now()->subMinutes(10),
        ]);

        $token = 'guest_tok_' . bin2hex(random_bytes(16));
        session(['order_guest_token_' . $order->order_number => $token]);

        $response = $this->get(route('order.status', ['order_number' => $order->order_number, 'token' => $token]));
        $response->assertOk();

        // Must not contain the hardcoded 11:15 AM or 12:30 PM strings
        $content = $response->getContent();
        $this->assertStringNotContainsString('11:15 AM', $content);
        $this->assertStringNotContainsString('12:30 PM (ETA)', $content);
    }

    public function test_order_status_timeline_renders_cleanly_without_timestamps(): void
    {
        // An order with no timestamps populated yet (fresh order)
        $order = $this->createOrder([
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'manual_bank_transfer',
            'paid_at' => null,
            'dispatched_at' => null,
            'delivered_at' => null,
        ]);

        $token = 'guest_tok_' . bin2hex(random_bytes(16));
        session(['order_guest_token_' . $order->order_number => $token]);

        $response = $this->get(route('order.status', ['order_number' => $order->order_number, 'token' => $token]));
        $response->assertOk();

        $content = $response->getContent();
        // Verifies no invented ETA or fabricated event time is rendered
        $this->assertStringNotContainsString('11:15 AM', $content);
        $this->assertStringNotContainsString('12:30 PM (ETA)', $content);
        $this->assertStringNotContainsString('(ETA)', $content);
    }

    public function test_concurrent_refund_claim_prevents_duplicate_disbursement(): void
    {
        $order = $this->createOrder();

        ManualBankTransferPayment::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'amount_kobo' => 1050000,
            'currency' => 'NGN',
            'status' => 'approved',
            'bank_transaction_reference' => 'IN-BANK-REF-CONCUR',
            'receiving_bank_name' => 'Zenith Bank',
            'receiving_account_number' => '1012345678',
            'evidence_path' => 'evidence/test.png',
            'evidence_mime' => 'image/png',
            'evidence_size_bytes' => 1024,
            'evidence_sha256' => hash('sha256', 'dummy-proof-concur'),
            'submitted_at' => now(),
            'approved_by_admin_id' => $this->superAdmin->id,
            'approved_at' => now(),
            'idempotency_key' => 'IN-KEY-CONCUR',
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => 'RET-CONCUR-' . uniqid(),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'admin_ruling' => 'uphold_customer_claim',
            'total_refundable_amount' => '10000.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
            'admin_ruled_at' => now(),
            'reason' => 'Customer claim',
            // Active concurrency claim token placed by another worker
            'refund_claim_token' => 'CLAIM-ACTIVE-123',
            'refund_claimed_at' => Carbon::now()->subMinutes(1),
        ]);

        $service = app(ReturnAndRefundService::class);
        $result = $service->adminDisburseRefund(
            $this->superAdmin,
            $returnCase,
            'OUT-BANK-REF-CONCUR',
            'settlement-proofs/test.png'
        );

        // Must reject disbursement because another worker holds the claim lock
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('currently being processed by another worker', $result['message']);
        $this->assertSame('refund_pending', $returnCase->fresh()->status);
        $this->assertNull(ManualBankTransferRefundCase::where('order_id', $order->id)->first());
    }

    public function test_vendor_settlement_status_forward_only_migration_preserves_runtime_statuses(): void
    {
        $order = $this->createOrder([
            'vendor_settlement_status' => 'refund_pending',
        ]);

        $this->assertSame('refund_pending', $order->fresh()->vendor_settlement_status);

        // Execute down() on the migration to verify forward-only safety
        $migration = require database_path('migrations/2026_10_24_000019_expand_vendor_settlement_status_on_orders_table.php');
        $migration->down();

        // The column must remain VARCHAR(50) and retain the runtime status without data truncation
        $this->assertSame('refund_pending', $order->fresh()->vendor_settlement_status);
    }
}

