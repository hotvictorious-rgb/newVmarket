<?php

namespace Tests\Feature;

use App\Models\ManualBankTransferPayment;
use App\Models\ManualBankTransferRefundCase;
use App\Models\FinancialOperation;
use App\Models\Order;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Services\CheckoutExecutionService;
use App\Services\OrderSettlementService;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualBankTransferReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $admin;
    protected VendorProfile $vendor;
    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->customer = User::where('user_type', 'customer')->first()
            ?? User::factory()->create(['user_type' => 'customer', 'status' => 'active']);

        $this->admin = User::where('user_type', 'super_admin')->first()
            ?? User::factory()->create(['user_type' => 'super_admin', 'status' => 'active']);

        $vendorUser = User::where('user_type', 'vendor')->firstOrFail();
        $this->vendor = $vendorUser->vendorProfile ?? VendorProfile::firstOrFail();
        $this->shop = Shop::where('vendor_id', $this->vendor->id)->firstOrFail();
        Storage::fake('local');
    }

    private function manualOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'VM-MANUAL-' . strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'retailer_model_order' => false,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'manual_bank_transfer',
            'payment_reference' => 'VM-PAY-' . strtoupper(bin2hex(random_bytes(6))),
            'manual_bank_transfer_account_snapshot' => [
                'bank_name' => 'Test Bank',
                'account_name' => 'Victorious MARKET',
                'account_number' => '0123456789',
                'customer_instructions' => 'Copy the order number into narration.',
            ],
            'guest_access_token' => bin2hex(random_bytes(32)),
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->shop->lga_id,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '2500.50',
            'cashback_redeemed' => '0.00',
            'wallet_credit_applied' => '0.00',
            'grand_total' => '12500.50',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '2250.45',
            'platform_delivery_fee' => '250.05',
            'pickup_verification_code' => Order::generateUniquePickupOtp(),
            'verification_code' => Order::generateUniqueDeliveryOtp(),
            'vendor_settlement_status' => 'held',
            'sourcing_status' => 'awaiting_payment',
            'expires_at' => now()->addMinutes(30),
        ], $overrides));
    }

    private function submitReceipt(Order $order): ManualBankTransferPayment
    {
        $response = $this->actingAs($this->customer)->post(
            route('customer.manual-bank-transfer.submit', ['order_number' => $order->order_number]),
            ['payment_screenshot' => UploadedFile::fake()->image('receipt.jpg', 640, 480)]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('manual_bank_transfer_payments', [
            'order_id' => $order->id,
            'status' => 'pending_review',
        ]);

        return ManualBankTransferPayment::where('order_id', $order->id)->firstOrFail();
    }

    public function test_receipt_upload_is_private_and_does_not_mark_order_paid(): void
    {
        $order = $this->manualOrder();
        $payment = $this->submitReceipt($order);

        $this->assertTrue(Storage::disk('local')->exists($payment->evidence_path));
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame('pending_review', $payment->fresh()->status);
    }

    public function test_admin_approval_requires_exact_amount_and_unique_bank_reference(): void
    {
        $order = $this->manualOrder();
        $payment = $this->submitReceipt($order);

        $this->actingAs($this->admin)
            ->from(route('admin.manual-bank-transfers.index'))
            ->post(route('admin.manual-bank-transfers.approve', $payment->id), [
                'bank_transaction_reference' => 'BANK-REF-1001',
                'credited_amount' => '12500.49',
                'confirm_bank_credit' => '1',
            ])
            ->assertSessionHasErrors('credited_amount');

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending_review', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->bank_transaction_reference);
    }

    public function test_correct_admin_approval_settles_only_the_bound_order_once(): void
    {
        $order = $this->manualOrder();
        $payment = $this->submitReceipt($order);

        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.approve', $payment->id), [
                'bank_transaction_reference' => 'BANK-REF-UNIQUE-1002',
                'credited_amount' => '12500.50',
                'confirm_bank_credit' => '1',
                'decision_reason' => 'Verified against available credit in receiving bank account.',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'));

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('manual_bank_transfer', $order->fresh()->payment_channel);
        $this->assertNull($order->fresh()->paystack_transaction_id);
        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame('BANK-REF-UNIQUE-1002', $payment->fresh()->bank_transaction_reference);

        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.approve', $payment->id), [
                'bank_transaction_reference' => 'BANK-REF-UNIQUE-1002',
                'credited_amount' => '12500.50',
                'confirm_bank_credit' => '1',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertSame(1, ManualBankTransferPayment::where('order_id', $order->id)->count());
    }

    public function test_rejection_is_terminal_and_prevents_a_second_payment_submission(): void
    {
        $order = $this->manualOrder();
        $payment = $this->submitReceipt($order);

        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.reject', $payment->id), [
                'decision_reason' => 'The receipt does not match the bank account credit.',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'));

        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);

        $this->actingAs($this->customer)
            ->from(route('order.status', ['order_number' => $order->order_number]))
            ->post(route('customer.manual-bank-transfer.submit', ['order_number' => $order->order_number]), [
                'payment_screenshot' => UploadedFile::fake()->image('another-receipt.jpg'),
            ])
            ->assertSessionHasErrors('payment_screenshot');

        $this->assertSame(1, ManualBankTransferPayment::where('order_id', $order->id)->count());
    }

    public function test_same_bank_transaction_reference_cannot_pay_two_orders(): void
    {
        $firstOrder = $this->manualOrder();
        $firstPayment = $this->submitReceipt($firstOrder);
        $bankReference = 'BANK-REF-ONE-ORDER-ONLY';

        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.approve', $firstPayment->id), [
                'bank_transaction_reference' => $bankReference,
                'credited_amount' => '12500.50',
                'confirm_bank_credit' => '1',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'));

        $secondOrder = $this->manualOrder();
        $secondPayment = $this->submitReceipt($secondOrder);

        $this->actingAs($this->admin)
            ->from(route('admin.manual-bank-transfers.index'))
            ->post(route('admin.manual-bank-transfers.approve', $secondPayment->id), [
                'bank_transaction_reference' => $bankReference,
                'credited_amount' => '12500.50',
                'confirm_bank_credit' => '1',
            ])
            ->assertSessionHasErrors('bank_transaction_reference');

        $this->assertSame('paid', $firstOrder->fresh()->payment_status);
        $this->assertSame('pending', $secondOrder->fresh()->payment_status);
        $this->assertSame('pending_review', $secondPayment->fresh()->status);
        $this->assertSame(1, ManualBankTransferPayment::where('bank_transaction_reference', $bankReference)->count());
    }

    public function test_payment_method_cannot_be_changed_after_order_creation(): void
    {
        $order = $this->manualOrder();
        $this->expectException(\LogicException::class);
        $order->payment_method = 'paystack';
        $order->save();
    }

    public function test_late_manual_transfer_is_recorded_but_never_reactivates_order_or_sends_paystack_refund(): void
    {
        $order = $this->manualOrder(['expires_at' => now()->subMinute()]);
        $payment = $this->submitReceipt($order);

        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.approve', $payment->id), [
                'bank_transaction_reference' => 'BANK-REF-LATE-3001',
                'credited_amount' => '12500.50',
                'confirm_bank_credit' => '1',
                'decision_reason' => 'Verified actual credit; order had expired.',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'));

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid_stock_unavailable', $order->fresh()->order_status);
        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertDatabaseHas('manual_bank_transfer_refund_cases', [
            'order_id' => $order->id,
            'manual_bank_transfer_payment_id' => $payment->id,
            'status' => 'pending_admin_review',
        ]);
        $this->assertDatabaseMissing('financial_operations', [
            'idempotency_key' => 'PAYSTACK-LATE-REFUND-' . $order->id,
        ]);
    }

    public function test_paystack_settlement_cannot_settle_a_manual_transfer_order(): void
    {
        $order = $this->manualOrder();

        $result = app(OrderSettlementService::class)->settlePaystackOrder($order, [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 1250050,
            'reference' => $order->payment_reference,
            'id' => 'FAKE-PAYSTACK-CAPTURE',
            'channel' => 'card',
            'customer' => ['email' => $this->customer->email],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('PAYMENT_METHOD_MISMATCH', $result['status']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->paystack_transaction_id);
    }

    public function test_admin_payment_configuration_supports_simultaneous_coexistence(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.settings'), [
                'paystack_enabled' => '1',
                'manual_bank_transfer_enabled' => '1',
                'bank_name' => 'First Bank of Nigeria',
                'account_name' => 'Victorious MARKET Ltd',
                'account_number' => '0123456789',
                'customer_instructions' => 'Copy the order code into bank narration.',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'))
            ->assertSessionHas('status');

        $config = CheckoutExecutionService::getPaymentConfiguration();
        $this->assertTrue($config['paystack_enabled']);
        $this->assertTrue($config['manual_bank_transfer_enabled']);
        $this->assertSame('coexistence', $config['active_method']);
        $this->assertTrue($config['is_account_configured']);
        $this->assertSame('0123456789', $config['manual_bank_transfer_account']['account_number']);
        $this->assertSame('First Bank of Nigeria', $config['manual_bank_transfer_account']['bank_name']);
    }

    public function test_admin_payment_configuration_supports_single_rail_and_disabled_states(): void
    {
        // 1. Paystack only
        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.settings'), [
                'paystack_enabled' => '1',
                'manual_bank_transfer_enabled' => '0',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'));

        $config = CheckoutExecutionService::getPaymentConfiguration();
        $this->assertTrue($config['paystack_enabled']);
        $this->assertFalse($config['manual_bank_transfer_enabled']);
        $this->assertSame('paystack', $config['active_method']);

        // 2. Manual Bank Transfer only
        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.settings'), [
                'paystack_enabled' => '0',
                'manual_bank_transfer_enabled' => '1',
                'bank_name' => 'Zenith Bank',
                'account_name' => 'Victorious MARKET Ltd',
                'account_number' => '9876543210',
                'customer_instructions' => 'Include order code.',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'));

        $config = CheckoutExecutionService::getPaymentConfiguration();
        $this->assertFalse($config['paystack_enabled']);
        $this->assertTrue($config['manual_bank_transfer_enabled']);
        $this->assertSame('manual_bank_transfer', $config['active_method']);

        // 3. Both disabled
        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.settings'), [
                'paystack_enabled' => '0',
                'manual_bank_transfer_enabled' => '0',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'));

        $config = CheckoutExecutionService::getPaymentConfiguration();
        $this->assertFalse($config['paystack_enabled']);
        $this->assertFalse($config['manual_bank_transfer_enabled']);
        $this->assertSame('disabled', $config['active_method']);
    }

    public function test_admin_payment_configuration_requires_valid_bank_account_when_manual_transfer_enabled(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.settings'), [
                'paystack_enabled' => '1',
                'manual_bank_transfer_enabled' => '1',
                'bank_name' => '',
                'account_name' => '',
                'account_number' => 'not-10-digits',
            ])
            ->assertSessionHasErrors(['bank_name', 'account_name', 'account_number']);
    }

    public function test_paystack_order_cannot_accept_manual_transfer_receipt(): void
    {
        $order = $this->manualOrder(['payment_method' => 'paystack']);

        $this->actingAs($this->customer)
            ->from(route('order.status', ['order_number' => $order->order_number]))
            ->post(route('customer.manual-bank-transfer.submit', ['order_number' => $order->order_number]), [
                'payment_screenshot' => UploadedFile::fake()->image('receipt.jpg'),
            ])
            ->assertSessionHasErrors('payment_screenshot');

        $this->assertDatabaseCount('manual_bank_transfer_payments', 0);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_manual_bank_transfer_approval_accrues_cashback_with_explicit_funding_source_and_balanced_ledger(): void
    {
        $order = $this->manualOrder([
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '2500.50',
            'grand_total' => '12500.50',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'retailer_model_order' => false,
        ]);
        $payment = $this->submitReceipt($order);

        $this->actingAs($this->admin)
            ->post(route('admin.manual-bank-transfers.approve', $payment->id), [
                'bank_transaction_reference' => 'BANK-REF-CASHBACK-2026',
                'credited_amount' => '12500.50',
                'confirm_bank_credit' => '1',
                'decision_reason' => 'Deposit verified in manual bank account.',
            ])
            ->assertRedirect(route('admin.manual-bank-transfers.index'));

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('manual_bank_transfer', $order->fresh()->payment_channel);

        // Verify CustomerCashbackLedger accrued with explicit funding_source
        $cashback = \App\Models\CustomerCashbackLedger::where('order_id', $order->id)->first();
        $this->assertNotNull($cashback, 'Cashback ledger entry must be created on verified manual bank transfer');
        $this->assertSame('500.00', number_format((float)$cashback->amount, 2, '.', ''));
        $this->assertSame('pending_maturation', $cashback->status);
        $this->assertSame('manual_bank_transfer', $cashback->funding_source, 'Funding source must explicitly record manual_bank_transfer');

        // Zero-discrepancy balance verification
        $debits = \App\Models\FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'debit')->sum('amount');
        $credits = \App\Models\FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'credit')->sum('amount');
        $this->assertEqualsWithDelta((float)$debits, (float)$credits, 0.001, 'Double-entry journal must balance with zero discrepancy');
    }

    public function test_payment_method_coexistence_allows_customer_selection_and_honors_choice(): void
    {
        SystemSetting::set('checkout.payment_configuration', [
            'active_method' => 'both',
            'paystack_enabled' => true,
            'manual_bank_transfer_enabled' => true,
            'manual_bank_transfer_account' => [
                'bank_name' => 'Zenith Bank',
                'account_name' => 'Victorious MARKET Ltd',
                'account_number' => '1012345678',
                'customer_instructions' => 'Include order code in narration.',
            ],
        ]);

        $product = \App\Models\Product::first();
        $this->assertNotNull($product, 'Test requires at least one product');
        $product->update([
            'is_approved' => true,
            'listing_status' => 'listed',
            'is_available_for_sale' => true,
            'is_in_stock' => true,
            'listing_confirmed_at' => now(),
        ]);
        \App\Models\SupplierOffer::updateOrCreate([
            'product_id' => $product->id,
        ], [
            'vendor_id' => $product->vendor_id,
            'shop_id' => $product->shop_id,
            'wholesale_cost' => '1000.00',
            'is_available' => true,
            'is_admin_disabled' => false,
            'availability_confirmed_at' => now(),
            'availability_confirmed_by' => $product->vendor?->user_id ?? 1,
            'availability_expires_at' => now()->addHours(24),
        ]);
        // Customer accesses checkout page; both methods should be available
        $viewResponse = $this->actingAs($this->customer)->get(route('checkout', ['product_id' => $product->id]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Paystack Online');
        $viewResponse->assertSee('Manual Bank Transfer');
        $viewResponse->assertViewHas('paystackEnabled', true);
        $viewResponse->assertViewHas('manualBankTransferEnabled', true);

        // Customer selects manual_bank_transfer
        $manualResponse = $this->actingAs($this->customer)->post(route('checkout.process'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+2348011112222',
            'delivery_address' => '12 Shelter Afrique, Uyo',
            'destination_lga_id' => $this->shop->lga_id,
            'payment_method' => 'manual_bank_transfer',
        ]);
        $manualResponse->assertRedirect();
        $createdManualOrder = Order::where('customer_id', $this->customer->id)->latest('id')->first();
        $this->assertSame('manual_bank_transfer', $createdManualOrder->payment_method);
        $this->assertSame('pending', $createdManualOrder->payment_status);

        // Customer selects paystack
        $paystackResponse = $this->actingAs($this->customer)->post(route('checkout.process'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+2348011112222',
            'delivery_address' => '12 Shelter Afrique, Uyo',
            'destination_lga_id' => $this->shop->lga_id,
            'payment_method' => 'paystack',
        ]);
        $paystackResponse->assertRedirect();
        $createdPaystackOrder = Order::where('customer_id', $this->customer->id)->latest('id')->first();
        $this->assertSame('paystack', $createdPaystackOrder->payment_method);
        $this->assertSame('pending', $createdPaystackOrder->payment_status);
    }

    public function test_replacement_evidence_submission_while_review_is_open_preserves_history(): void
    {
        $order = $this->manualOrder();
        $payment = $this->submitReceipt($order);
        $this->assertSame('pending_review', $payment->status);
        $firstProof = $payment->evidence_path;
        $this->assertNotNull($firstProof);

        // Admin requests replacement evidence
        $requestReplacement = $this->actingAs($this->admin)->post(route('admin.manual-bank-transfers.request-replacement', $payment->id), [
            'request_reason' => 'Receipt is blurry, please upload clearer screenshot with transaction reference.',
        ]);
        $requestReplacement->assertRedirect();
        $payment->refresh();
        $this->assertSame('evidence_requested', $payment->status);

        // Customer submits replacement receipt
        $file2 = UploadedFile::fake()->image('receipt2.png');
        $submit2 = $this->actingAs($this->customer)->post(route('customer.manual-bank-transfer.submit', ['order_number' => $order->order_number]), [
            'payment_screenshot' => $file2,
        ]);
        $submit2->assertRedirect();
        $payment->refresh();
        $this->assertSame('pending_review', $payment->status);
        $this->assertNotSame($firstProof, $payment->evidence_path);
        $history = $payment->evidence_history;
        $this->assertIsArray($history);
        $this->assertCount(2, $history);
        $this->assertSame($firstProof, $history[1]['evidence_path']);

        // Reject payment to establish terminal lock
        $reject = $this->actingAs($this->admin)->post(route('admin.manual-bank-transfers.reject', $payment->id), [
            'rejection_reason' => 'Transaction reference invalid on statement',
        ]);
        $reject->assertRedirect();
        $payment->refresh();
        $this->assertSame('rejected', $payment->status);

        // Customer attempt to upload after terminal rejection is blocked
        $file3 = UploadedFile::fake()->image('receipt3.png');
        $submit3 = $this->actingAs($this->customer)->post(route('customer.manual-bank-transfer.submit', ['order_number' => $order->order_number]), [
            'payment_screenshot' => $file3,
        ]);
        $submit3->assertSessionHasErrors('payment_screenshot');
    }

    public function test_manual_bank_refund_case_approval_dispatch_and_reconciliation_workflow(): void
    {
        $order = $this->manualOrder();
        $payment = $this->submitReceipt($order);
        $payment->update(['status' => 'rejected']);

        $amountKobo = (int) round(((float)$order->grand_total) * 100);
        $refundCase = ManualBankTransferRefundCase::create([
            'order_id' => $order->id,
            'manual_bank_transfer_payment_id' => $payment->id,
            'idempotency_key' => 'REFUND-CASE-' . $payment->id . '-' . bin2hex(random_bytes(4)),
            'amount_kobo' => $amountKobo,
            'currency' => 'NGN',
            'status' => 'pending_admin_review',
            'reason' => 'Transfer arrived after order cancellation',
            'created_by_admin_id' => $this->admin->id,
        ]);

        // 1. Super Admin approves refund
        $approveResponse = $this->actingAs($this->admin)->post(route('admin.manual-bank-transfers.refunds.approve', $refundCase->id));
        $approveResponse->assertRedirect();
        $refundCase->refresh();
        $this->assertSame('approved_for_dispatch', $refundCase->status);
        $this->assertSame($this->admin->id, $refundCase->reviewed_by_admin_id);

        // 2. Super Admin dispatches refund with bank reference and proof upload
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-10-24 12:00:00')); // Saturday
        $proofFile = UploadedFile::fake()->create('refund_wire.pdf', 120);
        $dispatchResponse = $this->actingAs($this->admin)->post(route('admin.manual-bank-transfers.refunds.dispatch', $refundCase->id), [
            'manual_dispatch_reference' => 'REFUND-TXN-WIRE-001',
            'dispatch_proof' => $proofFile,
            'decision_notes' => 'Dispatched via business banking portal',
        ]);
        $dispatchResponse->assertRedirect();
        $refundCase->refresh();
        $this->assertSame('dispatched_awaiting_reconciliation', $refundCase->status);
        $this->assertSame('REFUND-TXN-WIRE-001', $refundCase->manual_dispatch_reference);
        $this->assertNotNull($refundCase->manual_dispatch_proof_path);

        // 3. Super Admin reconciles refund against bank statement debit
        $reconcileResponse = $this->actingAs($this->admin)->post(route('admin.manual-bank-transfers.refunds.reconcile', $refundCase->id));
        $reconcileResponse->assertRedirect();
        $refundCase->refresh();
        $this->assertSame('completed', $refundCase->status);

        // 4. Verify balanced double-entry financial journal entry posted
        $refundDebits = \App\Models\FinancialJournalEntry::where('order_id', $order->id)
            ->where('entry_type', 'debit')
            ->where('account_name', 'escrow_holding')
            ->sum('amount');
        $refundCredits = \App\Models\FinancialJournalEntry::where('order_id', $order->id)
            ->where('entry_type', 'credit')
            ->where('account_name', 'cash_in_escrow')
            ->sum('amount');

        $expectedNaira = bcdiv((string) $amountKobo, '100', 2);
        $this->assertSame(
            $expectedNaira,
            number_format((float)$refundDebits, 2, '.', ''),
            'Refund debit leg must balance refund amount'
        );
        $this->assertSame(
            number_format((float)$refundDebits, 2, '.', ''),
            number_format((float)$refundCredits, 2, '.', ''),
            'Refund double entry must balance with zero discrepancy'
        );
    }
}
