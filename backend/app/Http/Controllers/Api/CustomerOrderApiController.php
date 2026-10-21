<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckoutIntent;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\CustomerWalletCredit;
use App\Models\DeliveryLane;
use App\Models\DisputeAndReturn;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCollectionPoint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Services\BcMathService;
use App\Services\CheckoutExecutionService;
use App\Services\FulfillmentAvailabilityService;
use App\Services\NotificationDispatchService;
use App\Services\OrderSettlementService;
use App\Services\PaystackService;
use App\Services\ReturnAndRefundService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerOrderApiController extends Controller
{
    protected FulfillmentAvailabilityService $fulfillmentService;
    protected OrderSettlementService $settlementService;
    protected PaystackService $paystackService;

    public function __construct(
        FulfillmentAvailabilityService $fulfillmentService,
        OrderSettlementService $settlementService,
        PaystackService $paystackService
    ) {
        $this->fulfillmentService = $fulfillmentService;
        $this->settlementService = $settlementService;
        $this->paystackService = $paystackService;
    }

    /**
     * List all orders belonging to authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::where('customer_id', $user->id)
            ->with(['shop', 'vendor', 'items.product', 'rider.user'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        $formatted = $orders->getCollection()->map(fn (Order $o) => $this->formatOrderBrief($o));

        return response()->json([
            'status' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Show single order details with full 5-stage tracking and handshake status.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $user = $request->user();

        $order = Order::where('customer_id', $user->id)
            ->where('order_number', trim($orderNumber))
            ->with(['shop.state', 'shop.lga', 'vendor', 'items.product', 'rider.user', 'collectionPoint'])
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found or unauthorized access.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->formatOrderFull($order),
        ]);
    }

    /**
     * Finalize checkout intent into active Order and return Paystack payment credentials.
     */
    public function executeCheckout(Request $request, CheckoutExecutionService $checkoutExecutionService): JsonResponse
    {
        $validated = $request->validate([
            'intent_id' => 'nullable|integer|exists:checkout_intents,id',
            'shop_id' => 'required|integer|exists:shops,id',
            'fulfillment_type' => 'required|in:doorstep_delivery,in_shop_pickup',
            'customer_address_id' => 'nullable|required_if:fulfillment_type,doorstep_delivery|integer|exists:customer_addresses,id',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'redeem_cashback' => 'nullable|boolean',
            'redeem_wallet_credit' => 'prohibited',
            'payment_method' => 'nullable|string|in:paystack',
            'rider_id' => 'prohibited',
            'logistics_company_id' => 'prohibited',
        ], [
            'items.*.quantity.max' => 'Maximum allowed quantity per line item is 1,000 units.',
        ]);

        if (!empty($validated['redeem_wallet_credit'])) {
            return response()->json([
                'status' => false,
                'message' => 'Wallet credit cannot be used to pay for orders at launch. Use an approved payment method.',
            ], 422);
        }

        $user = $request->user();
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey !== null) {
            $idempotencyKey = trim($idempotencyKey);
            if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
                return response()->json([
                    'status' => false,
                    'message' => 'Idempotency-Key must contain between 1 and 100 characters.',
                ], 422);
            }

            $canonical = [
                'intent_id' => isset($validated['intent_id']) ? (int) $validated['intent_id'] : null,
                'shop_id' => (int) $validated['shop_id'],
                'fulfillment_type' => $validated['fulfillment_type'],
                'customer_address_id' => isset($validated['customer_address_id']) ? (int) $validated['customer_address_id'] : null,
                'items' => array_map(static fn (array $item): array => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ], $validated['items']),
                'redeem_cashback' => (bool) ($validated['redeem_cashback'] ?? false),
                'payment_method' => $validated['payment_method'] ?? 'paystack',
            ];
            usort($canonical['items'], static fn (array $a, array $b): int =>
                [$a['product_id'], $a['quantity']] <=> [$b['product_id'], $b['quantity']]
            );
            $validated['_checkout_idempotency_key'] = $idempotencyKey;
            $validated['_checkout_request_hash'] = hash('sha256', json_encode($canonical));
        }
        $result = $checkoutExecutionService->execute($user, $validated);

        if (!$result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['status_code'] ?? 422);
        }

        $order = $result['order'];

        return response()->json([
            'status' => true,
            'message' => $result['message'],
            'data' => [
                'order_number' => $order->order_number,
                'grand_total' => $order->grand_total,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'wallet_credit_applied' => $order->wallet_credit_applied,
                'paystack' => $result['paystack'],
                'order' => $this->formatOrderFull($order),
            ],
        ], $result['http_status'] ?? 201);
    }

    /**
     * Authoritative Paystack Payment Verification endpoint for Mobile & SPA clients.
     * Enforces customer ownership, exact kobo equality, reference validation,
     * and idempotent order settlement.
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string',
            'reference' => 'required|string',
        ]);

        $user = $request->user();

        $order = Order::where('order_number', trim($validated['order_number']))
            ->where('customer_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found or unauthorized access.',
            ], 404);
        }

        // Authoritative server-to-server gateway verification
        $verification = $this->paystackService->verifyTransaction($validated['reference'], $order);

        if (!$verification['success']) {
            return response()->json([
                'status' => false,
                'code' => $verification['code'] ?? 'VERIFICATION_FAILED',
                'message' => $verification['message'] ?? 'Payment verification failed.',
            ], 422);
        }

        // Settle under pessimistic lock
        $settlement = $this->settlementService->settlePaystackOrder(
            $order,
            $verification['data'],
            $user,
            'paystack_api'
        );

        if (!$settlement['success']) {
            return response()->json([
                'status' => false,
                'code' => $settlement['status'] ?? 'SETTLEMENT_FAILED',
                'message' => $settlement['message'],
            ], 422);
        }

        return response()->json([
            'status' => true,
            'settlement' => $settlement['status'],
            'message' => $settlement['message'],
            'data' => [
                'order' => $this->formatOrderFull($settlement['order']),
            ],
        ], 200);
    }

    /**
     * Customer Delivery Handshake Confirmation on phone.
     */
    public function confirmDelivery(Request $request, string $orderNumber): JsonResponse
    {
        $validated = $request->validate([
            'delivery_otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'proof_photo' => ['required', 'image', 'max:10240'],
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Authentication is required to confirm delivery.'], 401);
        }

        $order = Order::where('customer_id', $user->id)
            ->where('order_number', trim($orderNumber))
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $proofPath = '/storage/' . $request->file('proof_photo')->store('delivery_proofs', 'public');

        $result = $this->settlementService->verifyCustomerDeliveryOtp(
            $order,
            $validated['delivery_otp'],
            null,
            $proofPath,
            'Confirmed via customer mobile application.'
        );

        if (!$result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], 422);
        }

        $order->refresh();

        return response()->json([
            'status' => true,
            'message' => 'Delivery confirmed. Cashback remains subject to the claim window and maturity rules.',
            'data' => $this->formatOrderFull($order),
        ]);
    }

    /**
     * Format brief summary for order list cards.
     */
    protected function formatOrderBrief(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'grand_total' => (float)$order->grand_total,
            'formatted_total' => '₦' . number_format((float)$order->grand_total, 2),
            'items_count' => $order->items->sum('quantity'),
            'shop_name' => $order->shop->name ?? 'Verified Merchant',
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            'is_handshake_pending' => $order->isCustomerHandshakePending(),
        ];
    }

    /**
     * Format complete details with 5-stage tracking and PIN.
     */
    protected function formatOrderFull(Order $order): array
    {
        $stages = [
            'confirmed' => 1,
            'processing' => 2,
            'ready_for_pickup' => 2,
            'out_for_delivery' => $order->hasRiderArrived() ? 4 : 3,
            'delivered' => 5,
            'cancelled' => 0,
        ];

        $currentStage = $stages[$order->order_status] ?? 1;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'fulfillment_type' => $order->fulfillment_type,
            'tracking_stage' => $currentStage,
            'stage_description' => $this->getStageDescription($order, $currentStage),
            'delivery_otp' => (
                $order->fulfillment_type === 'doorstep_delivery'
                && $order->payment_status === 'paid'
                && !in_array($order->order_status, ['delivered', 'cancelled'], true)
            ) ? $order->verification_code : null,
            'customer_pickup_otp' => (
                $order->fulfillment_type === 'in_shop_pickup'
                && $order->payment_status === 'paid'
                && !in_array($order->order_status, ['picked_up', 'delivered', 'cancelled'], true)
            ) ? $order->verification_code : null,
            'is_handshake_pending' => $order->isCustomerHandshakePending(),
            'has_rider_arrived' => $order->hasRiderArrived(),
            'financials' => [
                'merchandise_subtotal' => (float)$order->merchandise_subtotal,
                'delivery_fee' => (float)$order->delivery_fee,
                'delivery_base_fee' => (float) ($order->delivery_base_fee_snapshot ?? $order->delivery_fee),
                'delivery_bulky_surcharge_per_unit' => (float) ($order->delivery_bulky_surcharge_per_unit_snapshot ?? 0),
                'delivery_bulky_quantity' => (int) ($order->delivery_bulky_quantity_snapshot ?? 0),
                'delivery_bulky_surcharge_total' => (float) \App\Services\BcMathService::mul(
                    (string) ($order->delivery_bulky_surcharge_per_unit_snapshot ?? '0.00'),
                    (string) ($order->delivery_bulky_quantity_snapshot ?? 0)
                ),
                'cashback_redeemed' => (float)$order->cashback_redeemed,
                'grand_total' => (float)$order->grand_total,
                'customer_cashback_earned' => (float)$order->customer_cashback_5,
                'formatted_grand_total' => '₦' . number_format((float)$order->grand_total, 2),
            ],
            'shop' => $order->shop ? [
                'id' => $order->shop->id,
                'name' => $order->shop->name,
                'address' => $order->shop->address,
            ] : null,
            'rider' => $order->rider ? [
                'name' => $order->rider->user->name ?? 'Assigned Rider',
                'phone' => $order->rider->user->phone ?? null,
                'vehicle_type' => $order->rider->vehicle_type ?? 'Motorcycle',
            ] : null,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'accepted_quantity' => $item->accepted_quantity,
                'rejected_quantity' => $item->rejected_quantity,
                'rejection_category' => $item->rejection_category,
                'defect_photo' => $item->defect_photo_path ? url($item->defect_photo_path) : null,
                'unit_price' => (float)$item->unit_price,
                'line_total' => (float)$item->line_total,
                'thumbnail' => $item->product && $item->product->thumbnail ? url($item->product->thumbnail) : null,
            ]),
            'visual_audit_trail' => [
                'pickup_counter_photo' => $order->pickup_proof_image ? url($order->pickup_proof_image) : null,
                'doorstep_delivery_photo' => $order->proof_of_delivery_image ? url($order->proof_of_delivery_image) : null,
                'delivery_attempt_photo' => $order->delivery_attempt_photo_path ? url($order->delivery_attempt_photo_path) : null,
                'delivery_failure_reason' => $order->delivery_failure_reason,
                'item_rejection_photos' => $order->doorstep_rejection_photos,
            ],
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            'delivered_at' => $order->received_at ? $order->received_at->toIso8601String() : null,
        ];
    }

    protected function getStageDescription(Order $order, int $stage): string
    {
        return match ($stage) {
            1 => 'Order placed and confirmed. Awaiting merchant packaging.',
            2 => 'Merchant is preparing parcel at plaza counter.',
            3 => 'Courier has picked up package and is in transit.',
            4 => 'Rider has arrived at your location! Present your 6-digit PIN below.',
            5 => 'Delivered and verified. 5% Cashback credited to wallet.',
            default => 'Order in progress.',
        };
    }

    /**
     * File a post-delivery dispute/return during the 24-hour return window.
     * Automatically freezes vendor escrow settlement.
     */
    public function createDispute(Request $request, string $orderNumber): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'reason' => 'required|string|in:wrong_item,damaged_item,materially_defective,different_from_advertised,missing_component',
            'customer_description' => 'required|string|max:1000',
            'evidence_photos' => 'nullable|array',
            'evidence_photos.*' => 'string',
        ]);

        $order = Order::where('customer_id', $user->id)
            ->where('order_number', trim($orderNumber))
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found or unauthorized access.',
            ], 404);
        }

        // Serialize dispute creation on the order row. The duplicate check, source-state
        // validation, dispute insert and settlement hold must be one atomic transition.
        $result = DB::transaction(function () use ($order, $user, $validated) {
            $lockedOrder = Order::whereKey($order->id)
                ->where('customer_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedOrder) {
                return ['error' => 'Order not found or unauthorized access.', 'code' => 404];
            }

            if ($lockedOrder->order_status !== 'delivered') {
                return ['error' => 'Disputes can only be filed on delivered orders.', 'code' => 422];
            }

            if (!$lockedOrder->isRefundWindowActive()) {
                return ['error' => 'The return and dispute window for this order has expired.', 'code' => 422];
            }

            $existingDispute = DisputeAndReturn::where('order_id', $lockedOrder->id)
                ->where('customer_id', $user->id)
                ->whereIn('status', ['filed', 'under_review', 'approved_refund'])
                ->lockForUpdate()
                ->first();

            if ($existingDispute) {
                return ['error' => 'A dispute has already been filed for this order.', 'code' => 422];
            }

            $dispute = DisputeAndReturn::create([
                'order_id' => $lockedOrder->id,
                'customer_id' => $user->id,
                'reason' => $validated['reason'],
                'customer_description' => strip_tags(trim($validated['customer_description'])),
                'evidence_photos' => $validated['evidence_photos'] ?? [],
                'status' => 'filed',
            ]);

            // Freeze vendor escrow release in the same transaction as dispute creation.
            $lockedOrder->vendor_settlement_status = 'disputed';
            $lockedOrder->save();

            return ['dispute' => $dispute, 'order' => $lockedOrder];
        });

        if (isset($result['error'])) {
            return response()->json([
                'status' => false,
                'message' => $result['error'],
            ], $result['code']);
        }

        $dispute = $result['dispute'];
        $lockedOrder = $result['order'];

        return response()->json([
            'status' => true,
            'message' => 'Dispute filed successfully. Vendor escrow has been frozen pending marketplace arbitration.',
            'data' => [
                'dispute_id' => $dispute->id,
                'order_number' => $lockedOrder->order_number,
                'reason' => $dispute->reason,
                'status' => $dispute->status,
                'created_at' => $dispute->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * View customer dispute status and admin resolution.
     */
    public function getDispute(Request $request, string $orderNumber): JsonResponse
    {
        $user = $request->user();

        $order = Order::where('customer_id', $user->id)
            ->where('order_number', trim($orderNumber))
            ->first();

        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Order not found.'], 404);
        }

        $dispute = DisputeAndReturn::where('order_id', $order->id)
            ->where('customer_id', $user->id)
            ->latest('id')
            ->first();

        if (!$dispute) {
            return response()->json(['status' => false, 'message' => 'No dispute found for this order.'], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $dispute->id,
                'order_number' => $order->order_number,
                'reason' => $dispute->reason,
                'customer_description' => $dispute->customer_description,
                'evidence_photos' => $dispute->evidence_photos,
                'status' => $dispute->status,
                'resolution_notes' => $dispute->resolution_notes,
                'created_at' => $dispute->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Customer App Tracking Route Alias: GET /api/v1/order/track?order_id= or order_number=
     */
    public function trackOrder(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        $orderId = $request->input('order_id');
        $orderNumber = $request->input('order_number');
        $guestToken = $request->input('guest_access_token');

        if (!$orderNumber && !$orderId) {
            return response()->json(['status' => false, 'message' => 'order_id or order_number is required.'], 422);
        }

        // Order numbers and integer IDs are not credentials. This public/hybrid
        // route must require either an authenticated owner or the unguessable
        // guest access token issued for the order before returning tracking data
        // or a customer handover OTP.
        if (!$user && (!is_string($guestToken) || !preg_match('/^[a-f0-9]{64}$/i', $guestToken))) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication or a valid guest access token is required to track this order.',
            ], 401);
        }

        $query = Order::query();
        if ($user) {
            $query->where('customer_id', $user->id);
        } else {
            $query->where('guest_access_token', $guestToken);
        }

        if ($orderNumber) {
            $query->where('order_number', trim((string) $orderNumber));
        } else {
            $query->where('id', (int) $orderId);
        }

        $order = $query->with(['shop', 'vendor', 'items.product', 'rider.user'])->first();

        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Order not found or unauthorized access.'], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->formatOrderFull($order),
        ]);
    }

    /**
     * Customer Delivery Handshake Confirmation accepting order identifier from payload.
     */
    public function confirmDeliveryByBody(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return response()->json(['status' => false, 'message' => 'Authentication is required to confirm delivery.'], 401);
        }

        $orderNumber = $request->input('order_number');
        if (!$orderNumber && $request->filled('order_id')) {
            $ord = Order::where('customer_id', $request->user()->id)->find($request->input('order_id'));
            $orderNumber = $ord?->order_number;
        }

        if (!$orderNumber) {
            return response()->json(['status' => false, 'message' => 'order_number or order_id is required.'], 422);
        }

        return $this->confirmDelivery($request, $orderNumber);
    }

    /**
     * Customer submits an Item-Level Return Request within the 24h Refund Window.
     */
    public function createReturnCase(Request $request, ReturnAndRefundService $returnService): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'return_reason' => 'required|string|in:defective_product,wrong_item_sent,misdescribed_counterfeit,damaged_in_transit,change_of_mind,missing_item,other',
            'customer_description' => 'nullable|string|max:1000',
            'collection_point_id' => 'nullable|integer|exists:logistics_collection_points,id',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.item_condition' => 'nullable|string|in:unopened_sealed,opened_tested,damaged_defective,used_worn,missing_parts',
            'evidence' => 'nullable|array',
        ]);

        $user = $request->user();
        $order = Order::findOrFail($validated['order_id']);

        if ((int)$order->customer_id !== (int)$user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: This order does not belong to your account.',
            ], 403);
        }

        $result = $returnService->createReturnCase(
            $user,
            $order,
            $validated['items'],
            $validated['return_reason'],
            $validated['customer_description'] ?? null,
            $validated['collection_point_id'] ?? null,
            $validated['evidence'] ?? []
        );

        if (!$result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => $result['message'],
            'data' => $result['case'],
        ], 201);
    }

    /**
     * List all Return Cases belonging to authenticated customer.
     */
    public function listReturnCases(Request $request): JsonResponse
    {
        $user = $request->user();

        $cases = ReturnCase::where('customer_id', $user->id)
            ->with(['order:id,order_number', 'vendor:id,business_name', 'collectionPoint:id,name,address', 'items.product'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => true,
            'data' => $cases->items(),
            'meta' => [
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'total' => $cases->total(),
            ],
        ]);
    }

    /**
     * View specific Return Case details with item status and inspection notes.
     */
    public function showReturnCase(Request $request, string $caseNumber): JsonResponse
    {
        $user = $request->user();

        $case = ReturnCase::where('customer_id', $user->id)
            ->where('case_number', trim($caseNumber))
            ->with([
                'order:id,order_number',
                'vendor:id,business_name',
                'collectionPoint',
                'items.product',
                'evidence',
                'inspections.collectionPoint',
            ])
            ->first();

        if (!$case) {
            return response()->json([
                'status' => false,
                'message' => 'Return case not found or unauthorized access.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $case,
        ]);
    }

    /**
     * Customer 100% Spendable Refund Wallet Summary
     */
    public function walletSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $balance = \App\Models\CustomerWalletCredit::getAvailableBalance($user->id);
        $credits = \App\Models\CustomerWalletCredit::where('customer_id', $user->id)
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'available_wallet_credit' => $balance,
                'currency' => 'NGN',
                'spendable_rate' => '100%',
                'history' => $credits,
            ],
        ]);
    }

    /**
     * 1-Click Re-Order for Rejected Doorstep Items using Instant Wallet Credit
     */
    public function reorderRejectedItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'case_number' => 'required|string',
        ]);

        $user = $request->user();
        $case = ReturnCase::where('customer_id', $user->id)
            ->where('case_number', trim($validated['case_number']))
            ->with(['items.product.shop', 'order'])
            ->first();

        if (!$case) {
            return response()->json(['status' => false, 'message' => 'Return case not found.'], 404);
        }

        $availableWallet = \App\Models\CustomerWalletCredit::getAvailableBalance($user->id);

        $reorderManifest = [];
        $totalCost = '0.00';

        foreach ($case->items as $item) {
            $product = $item->product;
            if (!$product || !$product->is_in_stock) {
                continue;
            }

            $subtotal = bcmul((string)$product->unit_price, (string)$item->quantity, 2);
            $totalCost = bcadd($totalCost, $subtotal, 2);

            $reorderManifest[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'shop_id' => $product->shop_id,
                'quantity' => $item->quantity,
                'unit_price' => $product->unit_price,
                'subtotal' => $subtotal,
            ];
        }

        if (empty($reorderManifest)) {
            return response()->json(['status' => false, 'message' => 'Products are currently out of stock.'], 422);
        }

        // This endpoint is quote-only. Legacy wallet credit is not launch checkout
        // tender and must never be presented as applied to a replacement order.
        $walletCreditApplied = '0.00';
        $remainingPayable = $totalCost;

        return response()->json([
            'status' => true,
            'message' => 'Replacement quote prepared. Wallet credit cannot be used for checkout at launch; pay using an approved method if you choose to proceed.',
            'data' => [
                'operation' => 'quote_preview',
                'is_quote' => true,
                'items' => $reorderManifest,
                'merchandise_subtotal' => $totalCost,
                'wallet_credit_available' => $availableWallet,
                'wallet_credit_applied' => $walletCreditApplied,
                'net_payable_cash' => $remainingPayable,
            ],
        ]);
    }
}
