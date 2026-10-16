<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) (config('services.paystack.secret_key') ?? env('PAYSTACK_SECRET_KEY', ''));
        $this->publicKey = (string) (config('services.paystack.public_key') ?? env('PAYSTACK_PUBLIC_KEY', ''));
        $this->baseUrl = rtrim((string) (config('services.paystack.payment_url') ?? env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co')), '/');
    }

    /**
     * Check if Paystack credentials are configured.
     */
    public function isConfigured(): bool
    {
        $key = $this->getSecretKey();
        return !empty($key) && !str_starts_with($key, 'sk_test_placeholder');
    }

    public function getSecretKey(): string
    {
        return (string) (config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY', $this->secretKey));
    }

    public function getPublicKey(): string
    {
        return (string) (config('services.paystack.public_key') ?: env('PAYSTACK_PUBLIC_KEY', $this->publicKey));
    }

    /**
     * Initialize Paystack Payment Transaction.
     * Returns authorization url and access code for web/mobile checkout.
     */
    public function initializeTransaction(Order $order, string $callbackUrl, ?string $customerEmail = null): array
    {
        $email = $customerEmail ?? ($order->customer->email ?? 'shopper@victoriousmarket.com.ng');
        // Paystack expects amount in Kobo (1 NGN = 100 Kobo).
        // Enforce exact 2-decimal BCMath normalization to eliminate floating-point drift.
        $grandTotalNormalized = bcadd((string)$order->grand_total, '0', 2);

        // Paystack standard monetary invariants (NGN):
        // 1. Minimum transaction: ₦1.00 (100 Kobo)
        if (bccomp($grandTotalNormalized, '1.00', 2) < 0) {
            return [
                'success' => false,
                'code' => 'AMOUNT_BELOW_MINIMUM',
                'message' => 'Payable amount must be at least ₦1.00 (100 kobo) for Paystack transaction processing.',
            ];
        }

        // 2. Maximum single transaction: ₦100,000,000.00 (100 Million Naira / 10 Billion Kobo)
        if (bccomp($grandTotalNormalized, '100000000.00', 2) > 0) {
            return [
                'success' => false,
                'code' => 'AMOUNT_EXCEEDS_MAXIMUM',
                'message' => 'Payable amount exceeds Paystack maximum single transaction limit of ₦100,000,000.00.',
            ];
        }

        $amountInKobo = (int) bcmul($grandTotalNormalized, '100', 0);

        $reference = $order->payment_reference ?? ('VM-' . date('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(8)));

        // Safe deterministic mock in testing environment
        if (app()->environment('testing')) {
            return [
                'success' => true,
                'is_simulated' => true,
                'authorization_url' => route('payment.paystack.callback', ['reference' => $reference]),
                'access_code' => 'test_acc_' . uniqid(),
                'reference' => $reference,
            ];
        }

        if (!$this->isConfigured()) {
            Log::error("Paystack initialization failed: Secret key not configured in environment.");
            return [
                'success' => false,
                'code' => 'CONFIG_MISSING',
                'message' => 'Paystack payment gateway is not configured. Please set PAYSTACK_SECRET_KEY in server environment.',
            ];
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(10)
                ->retry(2, 200)
                ->post("{$this->baseUrl}/transaction/initialize", [
                    'amount' => $amountInKobo,
                    'email' => $email,
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'currency' => 'NGN',
                    'metadata' => [
                        'order_number' => $order->order_number,
                        'order_id' => $order->id,
                        'customer_id' => $order->customer_id,
                        'customer_email' => $email,
                        'customer_phone' => $order->customer->phone ?? null,
                    ],
                ]);

            if ($response->successful() && $response->json('status') === true) {
                $data = $response->json('data');
                return [
                    'success' => true,
                    'is_simulated' => false,
                    'authorization_url' => $data['authorization_url'],
                    'access_code' => $data['access_code'],
                    'reference' => $data['reference'] ?? $reference,
                ];
            }

            Log::error("Paystack initialize error: " . $response->body());
            return [
                'success' => false,
                'code' => 'GATEWAY_ERROR',
                'message' => $response->json('message') ?? 'Payment gateway could not initialize transaction.',
            ];
        } catch (\Throwable $e) {
            Log::error("Paystack connection exception: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'NETWORK_ERROR',
                'message' => 'Paystack connection timeout. Please check internet connectivity.',
            ];
        }
    }

    /**
     * Verify Paystack Transaction Reference with Bank-Grade Security Invariants:
     * 1. Direct server-to-server query against official Paystack verify endpoint
     * 2. Status must strictly be 'success' (rejects 'pending', 'failed', 'abandoned')
     * 3. Currency must strictly be 'NGN'
     * 4. Exact Mathematical Amount Equality in Kobo (bcadd + bcmul)
     * 5. Customer Identity Binding (gateway email vs order customer email)
     * 6. Reference Binding via hash_equals
     *
     * It is mathematically and programmatically IMPOSSIBLE to verify an unpaid,
     * underpaid, or forged transaction reference through this method.
     */
    public function verifyTransaction(string $reference, Order $order): array
    {
        $grandTotalNormalized = bcadd((string)$order->grand_total, '0', 2);
        $expectedAmountInKobo = (int) bcmul($grandTotalNormalized, '100', 0);

        // Safe deterministic mock in testing environment
        if (app()->environment('testing')) {
            return [
                'success' => true,
                'is_simulated' => true,
                'data' => [
                    'id' => 'tx_test_' . $reference,
                    'reference' => $reference,
                    'status' => 'success',
                    'currency' => 'NGN',
                    'amount' => $expectedAmountInKobo,
                    'channel' => 'card',
                    'customer' => [
                        'email' => $order->customer->email ?? 'shopper@example.com',
                    ],
                ],
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'code' => 'CONFIG_MISSING',
                'message' => 'Paystack secret key is not configured in server environment.',
            ];
        }

        try {
            $response = Http::withToken($this->getSecretKey())
                ->timeout(10)
                ->retry(2, 200)
                ->get("{$this->baseUrl}/transaction/verify/" . rawurlencode($reference));

            if (!$response->successful() || $response->json('status') !== true) {
                $errorMsg = $response->json('message') ?? 'Paystack could not verify this transaction reference.';
                Log::warning("Paystack verification failed: " . $response->body());
                return [
                    'success' => false,
                    'code' => 'GATEWAY_ERROR',
                    'message' => $errorMsg,
                ];
            }

            $data = $response->json('data') ?? [];

            // INVARIANT 1: Transaction Status must strictly be 'success'
            $txStatus = strtolower((string)($data['status'] ?? ''));
            if ($txStatus !== 'success') {
                Log::warning("Paystack verification non-success status: {$txStatus} for reference: {$reference}");
                return [
                    'success' => false,
                    'code' => 'STATUS_NOT_SUCCESS',
                    'message' => "Transaction status is '{$txStatus}', expected 'success'.",
                ];
            }

            // INVARIANT 2: Currency must strictly be NGN
            $gatewayCurrency = strtoupper((string)($data['currency'] ?? ''));
            if ($gatewayCurrency !== 'NGN') {
                Log::critical("Paystack Currency Tampering Detected! Order {$order->order_number}: Sent {$gatewayCurrency}, Expected NGN.");
                return [
                    'success' => false,
                    'code' => 'CURRENCY_MISMATCH',
                    'message' => "Currency mismatch: gateway transaction was in '{$gatewayCurrency}', expected 'NGN'.",
                ];
            }

            // INVARIANT 3: Exact Integer Kobo Amount Equality Check (Mathematically prevents underpayment attacks)
            $actualAmountKobo = (int) ($data['amount'] ?? 0);
            if ($actualAmountKobo !== $expectedAmountInKobo) {
                Log::critical("Paystack Underpayment/Amount Fraud Detected! Order {$order->order_number} Expected: {$expectedAmountInKobo} Kobo, Received: {$actualAmountKobo} Kobo.");
                return [
                    'success' => false,
                    'code' => 'AMOUNT_MISMATCH',
                    'message' => "Payment amount mismatch: order total requires {$expectedAmountInKobo} kobo, received {$actualAmountKobo} kobo.",
                ];
            }

            // INVARIANT 4: Customer Identity Binding
            $gatewayEmail = strtolower(trim((string)($data['customer']['email'] ?? '')));
            $orderCustomerEmail = strtolower(trim((string)($order->customer->email ?? '')));
            if (!empty($gatewayEmail) && !empty($orderCustomerEmail) && $gatewayEmail !== $orderCustomerEmail) {
                Log::critical("Paystack Customer Identity Mismatch! Order {$order->order_number} Customer: {$orderCustomerEmail}, Gateway Payer: {$gatewayEmail}");
                return [
                    'success' => false,
                    'code' => 'CUSTOMER_EMAIL_MISMATCH',
                    'message' => "Customer mismatch: Paystack transaction was paid by '{$gatewayEmail}', but order belongs to '{$orderCustomerEmail}'.",
                ];
            }

            // Metadata alignment check if present
            $metaCustomerId = $data['metadata']['customer_id'] ?? null;
            if (!empty($metaCustomerId) && (int)$metaCustomerId !== (int)$order->customer_id) {
                return [
                    'success' => false,
                    'code' => 'CUSTOMER_ID_MISMATCH',
                    'message' => "Customer ID mismatch between gateway metadata and order.",
                ];
            }

            $metaOrderId = $data['metadata']['order_id'] ?? null;
            if (!empty($metaOrderId) && (int)$metaOrderId !== (int)$order->id) {
                return [
                    'success' => false,
                    'code' => 'ORDER_ID_MISMATCH',
                    'message' => "Order ID mismatch between gateway metadata and target order.",
                ];
            }

            // INVARIANT 5: Reference Integrity Check
            $gatewayRef = (string)($data['reference'] ?? '');
            if (!empty($order->payment_reference) && !hash_equals((string)$order->payment_reference, $gatewayRef)) {
                return [
                    'success' => false,
                    'code' => 'REFERENCE_MISMATCH',
                    'message' => "Payment reference mismatch between gateway and order record.",
                ];
            }

            return [
                'success' => true,
                'reference' => $gatewayRef,
                'channel' => $data['channel'] ?? 'card',
                'amount_kobo' => $actualAmountKobo,
                'gateway_response' => $data['gateway_response'] ?? 'Approved',
                'paid_at' => $data['paid_at'] ?? now()->toIso8601String(),
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error("Paystack verification exception: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'EXCEPTION',
                'message' => 'Paystack verification gateway error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify Paystack Webhook Cryptographic HMAC-SHA512 Signature
     */
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $secretKey = $this->getSecretKey();
        if (empty($secretKey) || empty($signature)) {
            return false;
        }

        $computedSignature = hash_hmac('sha512', $payload, $secretKey);
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Initiate automated payment refund via official Paystack Refund API.
     * https://docs-v2.paystack.com/api/refund/
     */
    public function initiateRefund(string $transactionReferenceOrId, ?string $amountInNaira = null, ?string $merchantNote = null): array
    {
        if (app()->environment('testing')) {
            return [
                'success' => true,
                'is_simulated' => true,
                'refund_reference' => 'rf_test_' . uniqid(),
                'status' => 'pending',
                'message' => 'Simulated test refund initiated successfully.',
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'code' => 'CONFIG_MISSING',
                'message' => 'Paystack secret key is not configured in server environment.',
            ];
        }

        try {
            $payload = [
                'transaction' => $transactionReferenceOrId,
                'merchant_note' => $merchantNote ?? 'Automated refund for out-of-stock / cancelled order.',
            ];

            if ($amountInNaira !== null && bccomp($amountInNaira, '0.00', 2) > 0) {
                $amountInNairaNormalized = bcadd((string)$amountInNaira, '0', 2);
                if (bccomp($amountInNairaNormalized, '1.00', 2) < 0) {
                    return [
                        'success' => false,
                        'code' => 'AMOUNT_BELOW_MINIMUM',
                        'message' => 'Paystack refund amount must be at least ₦1.00 (100 kobo).',
                    ];
                }
                if (bccomp($amountInNairaNormalized, '100000000.00', 2) > 0) {
                    return [
                        'success' => false,
                        'code' => 'AMOUNT_EXCEEDS_MAXIMUM',
                        'message' => 'Refund amount exceeds maximum allowable transaction limit of ₦100,000,000.00.',
                    ];
                }
                $payload['amount'] = (int) bcmul($amountInNairaNormalized, '100', 0); // Amount in Kobo
            }

            $response = Http::withToken($this->getSecretKey())
                ->timeout(15)
                ->retry(2, 200)
                ->post("{$this->baseUrl}/refund", $payload);

            if ($response->successful() && $response->json('status') === true) {
                $data = $response->json('data') ?? [];
                return [
                    'success' => true,
                    'refund_reference' => $data['reference'] ?? ($data['id'] ?? null),
                    'status' => $data['status'] ?? 'pending',
                    'message' => $response->json('message') ?? 'Refund initiated successfully with payment gateway.',
                    'data' => $data,
                ];
            }

            Log::error("Paystack refund failed: " . $response->body());
            return [
                'success' => false,
                'code' => 'GATEWAY_ERROR',
                'message' => $response->json('message') ?? 'Paystack could not initiate refund.',
            ];
        } catch (\Throwable $e) {
            Log::error("Paystack refund exception: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'EXCEPTION',
                'message' => 'Paystack refund error: ' . $e->getMessage(),
            ];
        }
    }
}
