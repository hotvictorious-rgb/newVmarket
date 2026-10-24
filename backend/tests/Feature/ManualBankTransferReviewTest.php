<?php

namespace Tests\Feature;

use App\Models\ManualBankTransferPayment;
use App\Models\Order;
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
}
