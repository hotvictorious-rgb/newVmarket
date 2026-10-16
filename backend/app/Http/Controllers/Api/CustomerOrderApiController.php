<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckoutIntent;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\DeliveryLane;
use App\Models\DisputeAndReturn;
use App\Models\LogisticsCollectionPoint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Services\BcMathService;
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
    public function executeCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'intent_id' => 'nullable|integer|exists:checkout_intents,id',
            'shop_id' => 'required|integer|exists:shops,id',
            'fulfillment_type' => 'required|in:doorstep_delivery,in_shop_pickup',
            'customer_address_id' => 'nullable|required_if:fulfillment_type,doorstep_delivery|integer|exists:customer_addresses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'redeem_cashback' => 'nullable|boolean',
            'payment_method' => 'nullable|string|in:paystack',
        ]);

        $user = $request->user();
        $shop = Shop::with('lga')->findOrFail($validated['shop_id']);

        $result = DB::transaction(function () use ($validated, $user, $shop) {
            $merchandiseSubtotal = '0.00';
            $itemsToCreate = [];

            foreach ($validated['items'] as $item) {
                $product = Product::marketplaceEligible()->lockForUpdate()->findOrFail($item['product_id']);
                
                if ((int)$product->shop_id !== (int)$shop->id) {
                    return [
                        'error' => true,
                        'message' => "Product '{$product->name}' does not belong to the selected shop branch.",
                    ];
                }

                if ((int)$product->vendor_id !== (int)$shop->vendor_id) {
                    return [
                        'error' => true,
                        'message' => "Product '{$product->name}' does not belong to the selected merchant.",
                    ];
                }

                if ($product->inventory_quantity < $item['quantity']) {
                    return [
                        'error' => true,
                        'message' => "Insufficient stock for {$product->name}. Only {$product->inventory_quantity} available.",
                    ];
                }

                $lineTotal = BcMathService::mul($product->unit_price, (string)$item['quantity']);
                $merchandiseSubtotal = BcMathService::add($merchandiseSubtotal, $lineTotal);

                $itemsToCreate[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->unit_price,
                    'line_total' => $lineTotal,
                ];
            }

            // Delivery Fee & Corridor Resolution
            $deliveryFee = '0.00';
            $destLgaId = $shop->lga_id;
            $laneId = null;
            $collectionPointId = null;

            if ($validated['fulfillment_type'] === 'doorstep_delivery') {
                $address = CustomerAddress::where('user_id', $user->id)->findOrFail($validated['customer_address_id']);
                $destLgaId = $address->lga_id;

                $availability = $this->fulfillmentService->checkDeliveryAvailability($shop->id, $destLgaId);
                if (!$availability['is_available']) {
                    return [
                        'error' => true,
                        'message' => $availability['message'],
                    ];
                }

                $deliveryFee = $availability['fee'];
                $laneId = $availability['lane_id'] ?? null;
                $collectionPointId = $availability['collection_point_id'] ?? null;
            }

            // Cashback Redemption Check under Pessimistic Balance Reservation
            $cashbackApplied = '0.00';
            if (!empty($validated['redeem_cashback'])) {
                $now = Carbon::now();
                $availableLotsSum = CustomerCashbackLedger::where('customer_id', $user->id)
                    ->where('status', 'available')
                    ->where('matures_at', '<=', $now)
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->sum('amount');

                $activeReservedSum = \App\Models\CashbackReservation::where('customer_id', $user->id)
                    ->where('status', 'reserved')
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->sum('amount');

                $effectiveAvailable = bcsub((string)$availableLotsSum, (string)$activeReservedSum, 2);
                if (bccomp($effectiveAvailable, '0.00', 2) > 0) {
                    $maxRedemption = BcMathService::mul($merchandiseSubtotal, '0.50');
                    $cashbackApplied = bccomp($effectiveAvailable, $maxRedemption) > 0 ? $maxRedemption : $effectiveAvailable;
                }
            }

            // Mathematical Splits (90% Vendor Net, 5% Margin, 5% Cashback)
            $vendorNet90 = BcMathService::mul($merchandiseSubtotal, '0.90');
            $customerCashback5 = BcMathService::mul($merchandiseSubtotal, '0.05');
            $platformMargin5 = BcMathService::mul($merchandiseSubtotal, '0.05');

            // Logistics Splits (90% Rider/Fleet, 10% Platform Delivery Fee)
            $logisticsNet90 = BcMathService::mul($deliveryFee, '0.90');
            $platformDelivery10 = BcMathService::mul($deliveryFee, '0.10');

            $grandTotal = BcMathService::add(BcMathService::sub($merchandiseSubtotal, $cashbackApplied), $deliveryFee);

            // Assign active accredited courier from fleet if collection point assigned
            $rider = null;
            $logisticsCompanyId = null;
            if ($collectionPointId) {
                $cp = LogisticsCollectionPoint::find($collectionPointId);
                if ($cp && $cp->logistics_company_id) {
                    $logisticsCompanyId = $cp->logistics_company_id;
                    $rider = RiderProfile::where('logistics_company_id', $cp->logistics_company_id)
                        ->where('is_active', true)
                        ->where('is_online', true)
                        ->whereHas('user', fn($q) => $q->where('status', 'active'))
                        ->whereHas('logisticsCompany', fn($q) => $q->where('status', 'active'))
                        ->first()
                        ?? RiderProfile::where('logistics_company_id', $cp->logistics_company_id)
                            ->where('is_active', true)
                            ->whereHas('user', fn($q) => $q->where('status', 'active'))
                            ->whereHas('logisticsCompany', fn($q) => $q->where('status', 'active'))
                            ->first();
                }
            }
            if (!$rider && $validated['fulfillment_type'] === 'doorstep_delivery') {
                $rider = RiderProfile::where('is_active', true)
                    ->where('is_online', true)
                    ->whereHas('user', fn($q) => $q->where('status', 'active'))
                    ->whereHas('logisticsCompany', fn($q) => $q->where('status', 'active'))
                    ->first()
                    ?? RiderProfile::where('is_active', true)
                        ->whereHas('user', fn($q) => $q->where('status', 'active'))
                        ->whereHas('logisticsCompany', fn($q) => $q->where('status', 'active'))
                        ->first();
                if ($rider && $rider->logistics_company_id) {
                    $logisticsCompanyId = $rider->logistics_company_id;
                }
            }
            if (!$logisticsCompanyId && $laneId) {
                $lane = DeliveryLane::find($laneId);
                if ($lane && $lane->logistics_company_id) {
                    $logisticsCompanyId = $lane->logistics_company_id;
                }
            }

            $orderNumber = 'VM-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $paymentRef = Order::generateUniquePaymentReference();
            $paymentMethod = $validated['payment_method'] ?? 'paystack';

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $user->id,
                'vendor_id' => $shop->vendor_id,
                'shop_id' => $shop->id,
                'collection_point_id' => $collectionPointId,
                'rider_id' => $rider ? $rider->id : null,
                'logistics_company_id' => $logisticsCompanyId,
                'fulfillment_type' => $validated['fulfillment_type'],
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentRef,
                'guest_access_token' => bin2hex(random_bytes(32)),
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $destLgaId,
                'delivery_lane_id' => $laneId,
                'merchandise_subtotal' => $merchandiseSubtotal,
                'delivery_fee' => $deliveryFee,
                'cashback_redeemed' => $cashbackApplied,
                'grand_total' => $grandTotal,
                'vendor_net_90' => $vendorNet90,
                'customer_cashback_5' => $customerCashback5,
                'platform_margin_5' => $platformMargin5,
                'logistics_fee_retained' => $logisticsNet90,
                'platform_delivery_fee' => $platformDelivery10,
                'pickup_verification_code' => Order::generateUniquePickupOtp(),
                'verification_code' => Order::generateUniqueDeliveryOtp(),
                'vendor_settlement_status' => 'held',
                'expires_at' => Carbon::now()->addMinutes(30),
            ]);

            // Atomic Cashback Pre-Payment Reservation
            if (bccomp($cashbackApplied, '0.00', 2) > 0) {
                \App\Models\CashbackReservation::create([
                    'customer_id' => $user->id,
                    'order_id' => $order->id,
                    'amount' => $cashbackApplied,
                    'status' => 'reserved',
                    'expires_at' => Carbon::now()->addMinutes(30),
                ]);
            }

            // Deduct inventory and insert items
            foreach ($itemsToCreate as $itemData) {
                $itemData['product']->decrement('inventory_quantity', $itemData['quantity']);
                $itemData['product']->is_in_stock = ($itemData['product']->inventory_quantity > 0);
                $itemData['product']->save();

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product']->id,
                    'product_name' => $itemData['product']->name,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'line_total' => $itemData['line_total'],
                ]);
            }

            return ['error' => false, 'order' => $order];
        });

        if (!empty($result['error'])) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], 422);
        }

        $order = $result['order'];

        // Initialize Paystack Checkout Transaction OUTSIDE the DB transaction (releases locks immediately)
        $callbackUrl = route('storefront') . '?order_success=' . $order->order_number;
        $paystackInit = null;
        if ($order->payment_method === 'paystack') {
            $paystackInit = $this->paystackService->initializeTransaction($order, $callbackUrl, $user->email);
        }

        return response()->json([
            'status' => true,
            'message' => 'Order created successfully. Proceed to payment.',
            'data' => [
                'order_number' => $order->order_number,
                'grand_total' => $order->grand_total,
                'payment_method' => $order->payment_method,
                'paystack' => $paystackInit,
                'order' => $this->formatOrderFull($order),
            ],
        ], 201);
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
        ]);

        $user = $request->user();
        $order = Order::where('customer_id', $user->id)
            ->where('order_number', trim($orderNumber))
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $result = $this->settlementService->verifyCustomerDeliveryOtp(
            $order,
            $validated['delivery_otp'],
            null,
            null,
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
            'message' => 'Delivery confirmed! Escrow released and cashback rewarded.',
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
            'delivery_otp' => $order->verification_code,
            'is_handshake_pending' => $order->isCustomerHandshakePending(),
            'has_rider_arrived' => $order->hasRiderArrived(),
            'financials' => [
                'merchandise_subtotal' => (float)$order->merchandise_subtotal,
                'delivery_fee' => (float)$order->delivery_fee,
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
                'unit_price' => (float)$item->unit_price,
                'line_total' => (float)$item->line_total,
                'thumbnail' => $item->product && $item->product->thumbnail ? url($item->product->thumbnail) : null,
            ]),
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

        if ($order->order_status !== 'delivered') {
            return response()->json([
                'status' => false,
                'message' => 'Disputes can only be filed on delivered orders.',
            ], 422);
        }

        if (!$order->isRefundWindowActive()) {
            return response()->json([
                'status' => false,
                'message' => 'The return and dispute window for this order has expired.',
            ], 422);
        }

        // Prevent duplicate disputes
        $existingDispute = DisputeAndReturn::where('order_id', $order->id)
            ->where('customer_id', $user->id)
            ->whereIn('status', ['filed', 'under_review', 'approved_refund'])
            ->first();

        if ($existingDispute) {
            return response()->json([
                'status' => false,
                'message' => 'A dispute has already been filed for this order.',
            ], 422);
        }

        $dispute = DB::transaction(function () use ($order, $user, $validated) {
            $dispute = DisputeAndReturn::create([
                'order_id' => $order->id,
                'customer_id' => $user->id,
                'reason' => $validated['reason'],
                'customer_description' => strip_tags(trim($validated['customer_description'])),
                'evidence_photos' => $validated['evidence_photos'] ?? [],
                'status' => 'filed',
            ]);

            // Freeze vendor escrow release under dispute
            $order->vendor_settlement_status = 'disputed';
            $order->save();

            return $dispute;
        });

        return response()->json([
            'status' => true,
            'message' => 'Dispute filed successfully. Vendor escrow has been frozen pending marketplace arbitration.',
            'data' => [
                'dispute_id' => $dispute->id,
                'order_number' => $order->order_number,
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
        $user = $request->user();
        $orderId = $request->input('order_id');
        $orderNumber = $request->input('order_number');

        $query = Order::query();
        if ($user) {
            $query->where('customer_id', $user->id);
        }

        if ($orderNumber) {
            $query->where('order_number', trim($orderNumber));
        } elseif ($orderId) {
            $query->where('id', (int)$orderId);
        } else {
            return response()->json(['status' => false, 'message' => 'order_id or order_number is required.'], 422);
        }

        $order = $query->with(['shop', 'vendor', 'items.product', 'rider.user'])->first();

        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Order not found.'], 404);
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

        // Apply wallet credit up to 100% of product subtotal!
        $walletCreditApplied = \App\Services\BcMathService::comp($availableWallet, $totalCost) >= 0
            ? $totalCost
            : $availableWallet;

        $remainingPayable = \App\Services\BcMathService::sub($totalCost, $walletCreditApplied);

        return response()->json([
            'status' => true,
            'message' => 'Re-order manifest prepared. 100% wallet credit applied.',
            'data' => [
                'items' => $reorderManifest,
                'merchandise_subtotal' => $totalCost,
                'wallet_credit_available' => $availableWallet,
                'wallet_credit_applied' => $walletCreditApplied,
                'net_payable_cash' => $remainingPayable,
            ],
        ]);
    }
}
